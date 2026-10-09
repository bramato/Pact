import { phpExecutable } from "../../tools/runtime.mjs";
import test from "node:test";
import assert from "node:assert/strict";
import {
  mkdtempSync,
  readFileSync,
  existsSync,
  rmSync,
  writeFileSync,
} from "node:fs";
import { tmpdir } from "node:os";
import { resolve } from "node:path";
import { spawn, spawnSync } from "node:child_process";
import { createServer } from "node:http";
import { once } from "node:events";
import {
  PactClient,
  CORE_URI,
  SWARM_URI,
} from "../../sdk/typescript/dist/index.js";
import {
  runEndpoint,
  defaultContract,
  defaultRegistry,
} from "../../tools/endpoint-conformance.mjs";
const php = phpExecutable(),
  root = resolve(import.meta.dirname, "../.."),
  dir = mkdtempSync(resolve(tmpdir(), "pact-http-")),
  sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let config, server, base;
const listen = async (handler) => {
  const s = createServer(handler);
  await new Promise((r) => s.listen(0, "127.0.0.1", r));
  return s;
};
const runPhp = (script, args = [], env = {}) => {
  const r = spawnSync(php, [script, ...args], {
    cwd: root,
    env: {
      ...process.env,
      PACT_CONFIG: resolve(dir, "demo-config.json"),
      ...env,
    },
    encoding: "utf8",
  });
  assert.equal(r.status, 0, r.stderr);
  return r.stdout;
};
const db = (sql, params = []) =>
  JSON.parse(
    runPhp("-r", [
      `require 'reference/bootstrap.php'; $store=new PactReference\\Store(pact_config()->database); echo PactReference\\Store::encode($store->query($argv[1],json_decode($argv[2],true)));`,
      sql,
      JSON.stringify(params),
    ]),
  );
const worker = (extra = {}) => {
  const p = spawn(php, ["reference/bin/worker.php"], {
    cwd: root,
    env: {
      ...process.env,
      PACT_CONFIG: resolve(dir, "demo-config.json"),
      ...extra,
    },
    stdio: ["ignore", "pipe", "pipe"],
  });
  let stderr = "";
  p.stderr.on("data", (x) => (stderr += x));
  const done = once(p, "exit").then(([code, signal]) => ({
    code,
    signal,
    stderr,
  }));
  return { p, done };
};
const client = (swarm = false) =>
  new PactClient(base + "/a2a", {
    token: config.tokens.find((t) => t.producer === "client").token,
    contract: defaultContract,
    registryPath: defaultRegistry,
    swarm,
  });
const input = (extra = {}) => ({
  text: "PACT connects three careful agents",
  clarify: false,
  swarm: true,
  ...extra,
});
const waitTask = async (c, id) => {
  const until = Date.now() + 12000;
  let t;
  do {
    t = await c.get(id);
    if (["TASK_STATE_COMPLETED", "TASK_STATE_FAILED"].includes(t.status.state))
      return t;
    await sleep(50);
  } while (Date.now() < until);
  throw new Error("Task timed out " + JSON.stringify(t.metadata));
};
await test("real HTTP interoperability and process recovery", async (t) => {
  try {
    const defaults = resolve(dir, "defaults");
    runPhp("reference/bin/setup.php", [defaults]);
    const original = readFileSync(resolve(defaults, "demo-config.json"), "utf8");
    assert.equal(JSON.parse(original).url, "http://127.0.0.1:8080");
    runPhp("reference/bin/setup.php", [defaults, "https://peer.example.org"]);
    assert.equal(
      readFileSync(resolve(defaults, "demo-config.json"), "utf8"),
      original,
      "Setup must preserve an existing URL, database path and private tokens",
    );
    const reservation = await listen((_, r) => r.end());
    const port = reservation.address().port;
    await new Promise((r) => reservation.close(r));
    base = `http://127.0.0.1:${port}`;
    runPhp("reference/bin/setup.php", [dir, base]);
    config = JSON.parse(readFileSync(resolve(dir, "demo-config.json")));
    server = spawn(
      php,
      ["-S", `127.0.0.1:${port}`, "-t", "public", "reference/router.php"],
      {
        cwd: root,
        env: { ...process.env, PACT_CONFIG: resolve(dir, "demo-config.json") },
        stdio: "ignore",
      },
    );
    for (let i = 0; i < 50; i++) {
      try {
        if ((await fetch(base + "/.well-known/agent-card.json")).ok) break;
      } catch {}
      await sleep(50);
    }
    await t.test(
      "live conformance exercises core-only PHP service",
      async () => {
        assert.equal(
          (await fetch(base + "/.well-known/agent-card.json")).headers.get(
            "X-PACT-Adapter",
          ),
          "Illuminate 12",
        );
        const report = await runEndpoint(base + "/a2a", {
          token: config.tokens[0].token,
          otherToken: config.tokens[1].token,
          referenceSuite: true,
        });
        assert.equal(report.passed, 14);
      },
    );
    await t.test(
      "three real contributors, dependency data, retry and optional failure",
      async () => {
        const c = client(true),
          task = await c.send(
            c.message(input({ retry_review: true, optional_failure: true })),
          );
        const w = worker();
        const completed = await waitTask(c, task.id);
        const end = await w.done;
        assert.equal(end.code, 0, end.stderr);
        assert.equal(completed.status.state, "TASK_STATE_COMPLETED");
        const meta = completed.metadata[SWARM_URI];
        assert.equal(meta.contributors.length, 4);
        assert.equal(
          meta.contributors.find((x) => x.child_id === "review").history.length,
          2,
        );
        assert.equal(
          meta.contributors.find((x) => x.child_id === "audit").history[0]
            .state,
          "TASK_STATE_FAILED",
        );
        assert.equal(meta.progress.percentage, 100);
        const record = db(`SELECT internal FROM tasks WHERE id=?`, [
          task.id,
        ])[0];
        const internal = JSON.parse(record.internal);
        assert.ok(
          internal.progress_history.some(
            (p, i, a) => i > 0 && p.percentage < a[i - 1].percentage,
          ),
          "retry actually regresses current progress",
        );
        assert.ok(
          internal.progress_history.some(
            (p) => p.high_watermark > p.percentage,
          ),
        );
        const dispatches = db(
          `SELECT raw FROM outbox WHERE parent=? AND kind='child'`,
          [task.id],
        ).map((r) => JSON.parse(r.raw).params.message);
        const assembly = dispatches.find(
          (m) => m.metadata[SWARM_URI].child_id === "assemble",
        );
        assert.deepEqual(
          Object.keys(assembly.metadata[SWARM_URI].dependencies),
          ["extract", "review"],
        );
        assert.equal(
          assembly.metadata[SWARM_URI].dependencies.extract.word_count,
          5,
        );
      },
    );
    await t.test(
      "SIGKILL after child commit before acknowledgment, then same-database restart",
      async () => {
        const c = client(true),
          task = await c.send(c.message(input()));
        const marker = resolve(dir, "crash-marker");
        const w = worker({ PACT_CRASH_MARKER: marker });
        const end = await w.done;
        assert.equal(end.signal, "SIGKILL", end.stderr);
        assert.ok(existsSync(marker));
        const accepted = readFileSync(marker, "utf8");
        assert.equal(
          db(`SELECT COUNT(*) AS n FROM effects WHERE task_id=?`, [accepted])[0]
            .n,
          1,
          JSON.stringify(db(`SELECT * FROM effects`)),
        );
        const pending = db(
          `SELECT raw,attempts FROM outbox WHERE parent=? AND kind='child' AND state='pending'`,
          [task.id],
        );
        assert.equal(pending.length, 1);
        assert.equal(pending[0].attempts, 1);
        await sleep(6100);
        const resumed = worker();
        const completed = await waitTask(c, task.id);
        const resumedEnd = await resumed.done;
        assert.equal(resumedEnd.code, 0, resumedEnd.stderr);
        assert.equal(completed.status.state, "TASK_STATE_COMPLETED");
        assert.equal(
          db(`SELECT COUNT(*) AS n FROM effects WHERE task_id=?`, [accepted])[0]
            .n,
          1,
          JSON.stringify(db(`SELECT * FROM effects`)),
        );
        const rows = db(
          `SELECT raw,attempts,state FROM outbox WHERE parent=? AND kind='child' ORDER BY id`,
          [task.id],
        );
        assert.equal(rows.length, 3);
        assert.equal(rows[0].raw, pending[0].raw);
        assert.equal(rows[0].attempts, 2);
        assert.equal(
          completed.metadata[SWARM_URI].contributors[0].history[0].task_id,
          accepted,
        );
      },
    );
    await t.test(
      "a replaced lease cannot acknowledge or fail the successor's work",
      async () => {
        const c = client(true),
          task = await c.send(c.message(input()));
        let replace = true;
        const proxy = await listen(async (req, res) => {
          let raw = "";
          for await (const chunk of req) raw += chunk;
          const upstream = await fetch(base + "/agents/extract/a2a", {
            method: "POST",
            headers: req.headers,
            body: raw,
          });
          const body = await upstream.text();
          if (replace) {
            replace = false;
            db(
              `UPDATE outbox SET owner='successor',lease_until=? WHERE parent=? AND kind='child'`,
              [Date.now() / 1000 + 60, task.id],
            );
          }
          res.writeHead(upstream.status, Object.fromEntries(upstream.headers));
          res.end(body);
        });
        try {
          db(`UPDATE outbox SET endpoint=? WHERE parent=? AND kind='child'`, [
            `http://127.0.0.1:${proxy.address().port}/a2a`,
            task.id,
          ]);
          const w = worker();
          assert.equal((await w.done).code, 0);
          assert.equal(
            (await c.get(task.id)).status.state,
            "TASK_STATE_WORKING",
          );
          const pending = db(
            `SELECT owner,state,result,error,raw FROM outbox WHERE parent=? AND kind='child'`,
            [task.id],
          )[0];
          assert.equal(pending.owner, "successor");
          assert.equal(pending.state, "pending");
          assert.equal(pending.result, null);
          assert.equal(pending.error, null);
          db(
            `UPDATE outbox SET owner=NULL,lease_until=0 WHERE parent=? AND kind='child'`,
            [task.id],
          );
          const resumed = worker();
          const completed = await waitTask(c, task.id);
          assert.equal((await resumed.done).code, 0);
          assert.equal(completed.status.state, "TASK_STATE_COMPLETED");
          const accepted =
            completed.metadata[SWARM_URI].contributors[0].history[0].task_id;
          assert.equal(
            db(`SELECT COUNT(*) AS n FROM effects WHERE task_id=?`, [
              accepted,
            ])[0].n,
            1,
          );
          assert.equal(
            db(
              `SELECT raw FROM outbox WHERE parent=? AND child='extract' AND kind='child'`,
              [task.id],
            )[0].raw,
            pending.raw,
          );
        } finally {
          await new Promise((r) => proxy.close(r));
        }
      },
    );
    await t.test(
      "cancellation after a lost acknowledgment still reconciles the accepted child",
      async () => {
        const c = client(true),
          task = await c.send(c.message(input()));
        const marker = resolve(dir, "cancel-crash-marker");
        const w = worker({ PACT_CRASH_MARKER: marker });
        assert.equal((await w.done).signal, "SIGKILL");
        const accepted = readFileSync(marker, "utf8");
        await c.cancel(task.id);
        await sleep(6100);
        const resumed = worker();
        assert.equal((await resumed.done).code, 0);
        const canceled = await c.get(task.id);
        assert.equal(canceled.status.state, "TASK_STATE_CANCELED");
        assert.ok(
          canceled.metadata[SWARM_URI].cancellation.observed.some(
            (entry) =>
              entry.task_id === accepted &&
              entry.state === "TASK_STATE_COMPLETED",
          ),
        );
        assert.equal(
          db(`SELECT COUNT(*) AS n FROM effects WHERE task_id=?`, [accepted])[0]
            .n,
          1,
        );
        const command = db(
          `SELECT state,attempts FROM outbox WHERE parent=? AND kind='child'`,
          [task.id],
        );
        assert.equal(command.length, 1);
        assert.equal(command[0].state, "delivered");
        assert.equal(command[0].attempts, 2);
      },
    );
    await t.test(
      "optional delivery exhaustion retains failure without blocking valid output",
      async () => {
        const c = client(true),
          task = await c.send(c.message(input({ optional_failure: true })));
        const broken = await listen((_, res) => {
          res.writeHead(503);
          res.end("unavailable");
        });
        try {
          db(
            `UPDATE outbox SET endpoint=? WHERE parent=? AND child='audit' AND kind='child'`,
            [`http://127.0.0.1:${broken.address().port}/a2a`, task.id],
          );
          const w = worker();
          const completed = await waitTask(c, task.id);
          assert.equal((await w.done).code, 0);
          assert.equal(completed.status.state, "TASK_STATE_COMPLETED");
          assert.equal(completed.metadata[SWARM_URI].progress.percentage, 100);
          const failure = db(
            `SELECT state,attempts,error FROM outbox WHERE parent=? AND child='audit' AND kind='child'`,
            [task.id],
          )[0];
          assert.equal(failure.state, "failed");
          assert.equal(failure.attempts, 5);
          assert.equal(JSON.parse(failure.error).code, "HTTP_503");
          assert.equal(
            completed.metadata[SWARM_URI].contributors.find(
              (child) => child.child_id === "audit",
            ).history.length,
            0,
          );
          const internal = JSON.parse(
            db(`SELECT internal FROM tasks WHERE id=?`, [task.id])[0].internal,
          );
          assert.deepEqual(
            internal.children.find((child) => child.child_id === "audit")
              .delivery_error,
            { code: "DELIVERY_FAILED", confirmed: false },
          );
        } finally {
          await new Promise((r) => broken.close(r));
        }
      },
    );
    await t.test(
      "connection dropped after remote commit is retried with the same bytes",
      async () => {
        const c = client(true),
          task = await c.send(c.message(input()));
        let drop = true;
        const proxy = await listen(async (req, res) => {
          let raw = "";
          for await (const chunk of req) raw += chunk;
          const upstream = await fetch(base + "/agents/extract/a2a", {
            method: "POST",
            headers: req.headers,
            body: raw,
          });
          const body = await upstream.text();
          if (drop) {
            drop = false;
            res.destroy();
            return;
          }
          res.writeHead(upstream.status, Object.fromEntries(upstream.headers));
          res.end(body);
        });
        try {
          const url = `http://127.0.0.1:${proxy.address().port}/a2a`;
          db(`UPDATE outbox SET endpoint=? WHERE parent=? AND kind='child'`, [
            url,
            task.id,
          ]);
          const w = worker();
          const completed = await waitTask(c, task.id);
          assert.equal((await w.done).code, 0);
          assert.equal(completed.status.state, "TASK_STATE_COMPLETED");
          const taskId =
            completed.metadata[SWARM_URI].contributors[0].history[0].task_id;
          assert.equal(
            db(`SELECT COUNT(*) AS n FROM effects WHERE task_id=?`, [taskId])[0]
              .n,
            1,
          );
          assert.equal(
            db(
              `SELECT attempts FROM outbox WHERE parent=? AND child='extract' AND kind='child'`,
              [task.id],
            )[0].attempts,
            2,
          );
        } finally {
          await new Promise((r) => proxy.close(r));
        }
      },
    );
    await t.test(
      "parent cancellation records remote acknowledgment separately",
      async () => {
        const c = client(true),
          task = await c.send(c.message(input({ defer_review: true })));
        let delegated;
        for (let i = 0; i < 30; i++) {
          runPhp("reference/bin/worker.php", ["--once"]);
          const record = JSON.parse(
            db(`SELECT internal FROM tasks WHERE id=?`, [task.id])[0].internal,
          );
          delegated = record.children.find((x) => x.child_id === "review");
          if (delegated.task_id && delegated.state === "TASK_STATE_WORKING")
            break;
        }
        assert.ok(delegated.task_id);
        const canceled = await c.cancel(task.id);
        assert.equal(canceled.status.state, "TASK_STATE_CANCELED");
        assert.equal(
          canceled.metadata[SWARM_URI].cancellation.observed.length,
          0,
        );
        const w = worker();
        assert.equal((await w.done).code, 0);
        const observed = await c.get(task.id);
        assert.equal(observed.status.state, "TASK_STATE_CANCELED");
        assert.ok(
          observed.metadata[SWARM_URI].cancellation.observed.some(
            (x) =>
              x.task_id === delegated.task_id &&
              x.state === "TASK_STATE_CANCELED",
          ),
        );
      },
    );
    await t.test(
      "bounded delivery exhaustion is persisted and fails required work",
      async () => {
        const c = client(true),
          task = await c.send(c.message(input()));
        const broken = await listen((_, res) => {
          res.writeHead(503);
          res.end("unavailable");
        });
        try {
          db(`UPDATE outbox SET endpoint=? WHERE parent=? AND kind='child'`, [
            `http://127.0.0.1:${broken.address().port}/a2a`,
            task.id,
          ]);
          const w = worker();
          const failed = await waitTask(c, task.id);
          assert.equal(failed.status.state, "TASK_STATE_FAILED");
          assert.equal((await w.done).code, 0);
          const row = db(
            `SELECT attempts,state,error FROM outbox WHERE parent=? AND child='extract' AND kind='child'`,
            [task.id],
          )[0];
          assert.equal(row.attempts, 5);
          assert.equal(row.state, "failed");
          assert.equal(JSON.parse(row.error).code, "HTTP_503");
        } finally {
          await new Promise((r) => broken.close(r));
        }
      },
    );
    await t.test("broken peer output and activation are rejected", async () => {
      const c = client();
      let missing = false,
        badPart = false;
      const broken = await listen(async (req, res) => {
        if (req.method === "GET") {
          res.writeHead(200, { "Content-Type": "application/json" });
          res.end(
            JSON.stringify({
              supportedInterfaces: [
                {
                  url: `http://127.0.0.1:${broken.address().port}/a2a`,
                  protocolBinding: "JSONRPC",
                  protocolVersion: "1.0",
                },
              ],
              capabilities: {
                extensions: [
                  {
                    uri: CORE_URI,
                    params: {
                      versions: ["0.2.0"],
                      contracts: [defaultContract],
                      features: [
                        "contracts",
                        "questions",
                        "events",
                        "progress",
                      ],
                    },
                  },
                ],
              },
            }),
          );
          return;
        }
        let raw = "";
        for await (const chunk of req) raw += chunk;
        const request = JSON.parse(raw);
        res.writeHead(200, {
          "Content-Type": "application/json",
          ...(missing ? {} : { "A2A-Extensions": CORE_URI }),
        });
        res.end(
          JSON.stringify({
            jsonrpc: "2.0",
            id: request.id,
            result: {
              task: {
                id: "bad",
                contextId: "ctx",
                status: {
                  state: "TASK_STATE_COMPLETED",
                  timestamp: new Date().toISOString(),
                },
                metadata: {
                  [CORE_URI]: { version: "0.2.0", contract: defaultContract },
                },
                artifacts: [
                  {
                    artifactId: "bad",
                    extensions: [CORE_URI],
                    parts: [
                      {
                        ...(badPart ? { text: "ambiguous" } : {}),
                        data: {
                          summary: "wrong",
                          word_count: badPart ? 5 : -1,
                          approved: true,
                        },
                      },
                    ],
                    metadata: {
                      [CORE_URI]: {
                        version: "0.2.0",
                        contract: defaultContract,
                      },
                    },
                  },
                ],
              },
            },
          }),
        );
      });
      try {
        const peer = new PactClient(
          `http://127.0.0.1:${broken.address().port}/a2a`,
          c.options,
        );
        await assert.rejects(peer.send(peer.message(input({ swarm: false }))), {
          code: "CONTRACT_INVALID",
        });
        const cli = spawn(
          process.execPath,
          [
            "tools/pact.mjs",
            "test",
            peer.endpoint,
            "--config",
            resolve(dir, "demo-config.json"),
          ],
          { cwd: root, stdio: ["ignore", "pipe", "pipe"] },
        );
        let error = "";
        cli.stderr.on("data", (chunk) => (error += chunk));
        const [exitCode] = await once(cli, "exit");
        assert.equal(exitCode, 1);
        assert.equal(JSON.parse(error).code, "CONTRACT_INVALID");
        badPart = true;
        await assert.rejects(peer.send(peer.message(input({ swarm: false }))), {
          code: "A2A_PART",
        });
        missing = true;
        await assert.rejects(peer.send(peer.message(input({ swarm: false }))), {
          code: "ACTIVATION",
        });
      } finally {
        await new Promise((r) => broken.close(r));
      }
    });
    const failures = db(
      `SELECT kind,error FROM outbox WHERE state='failed' AND kind='event'`,
    );
    assert.equal(failures.length, 0, JSON.stringify(failures));
  } finally {
    if (server) {
      server.kill();
      await once(server, "exit");
    }
    rmSync(dir, { recursive: true, force: true });
  }
});
