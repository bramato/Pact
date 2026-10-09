import assert from "node:assert/strict";
import { randomUUID } from "node:crypto";
import { readFileSync } from "node:fs";
import {
  PactClient,
  CORE_URI,
  SWARM_URI,
  signEvent,
} from "../sdk/typescript/dist/index.js";
export const defaultContract = JSON.parse(
  readFileSync(new URL("../examples/v0.2/contract.json", import.meta.url)),
);
export const defaultRegistry = new URL(
  "../examples/v0.2/schema-registry.json",
  import.meta.url,
).pathname;
export async function runEndpoint(
  endpoint,
  {
    token,
    otherToken,
    referenceSuite = false,
    contract = defaultContract,
    registryPath = defaultRegistry,
    input = {
      text: "PACT connects three careful agents",
      clarify: false,
      swarm: false,
    },
  },
) {
  const client = new PactClient(endpoint, { token, contract, registryPath }),
    checks = [];
  const check = async (name, fn) => {
    await fn();
    checks.push(name);
  };
  await check("A2A 1.0 discovery and Core activation", () => client.discover());
  let completed;
  await check(
    "structured input and independently validated output",
    async () => {
      const task = await client.send(client.message(input));
      assert.equal(task.status.state, "TASK_STATE_COMPLETED");
      completed = task;
    },
  );
  await check("exact acceptance replay", async () => {
    const raw = client.prepare("SendMessage", {
      message: client.message(input),
    });
    const [first, second] = await Promise.all([
      client.raw(raw),
      client.raw(raw),
    ]);
    assert.equal(first.task.id, second.task.id);
    const changed = JSON.parse(raw);
    changed.params.message.messageId = randomUUID();
    await assert.rejects(client.raw(JSON.stringify(changed)), {
      code: "IDEMPOTENCY_CONFLICT",
    });
  });
  await check("terminal task cannot resume", () =>
    assert.rejects(
      client.send(
        client.message({}, randomUUID(), completed, [
          {
            response_to: "approval",
            task_id: completed.id,
            value: { approved: true },
          },
        ]),
      ),
      { code: "TASK_TERMINAL" },
    ),
  );
  await check("unsupported version fails explicitly", async () => {
    const message = client.message(input);
    message.metadata[CORE_URI].version = "9.0.0";
    await assert.rejects(client.send(message), { code: "SCHEMA_INVALID" });
  });
  await check("authentication is enforced", () =>
    assert.rejects(
      new PactClient(endpoint, { token: "", contract, registryPath }).get(
        completed.id,
      ),
      { code: "HTTP_401" },
    ),
  );
  if (otherToken)
    await check("tenant isolation", () =>
      assert.rejects(
        new PactClient(endpoint, {
          token: otherToken,
          contract,
          registryPath,
        }).get(completed.id),
        { code: "TASK_NOT_FOUND" },
      ),
    );
  await check(
    "required Core activation is enforced by the endpoint",
    async () => {
      const raw = client.prepare("SendMessage", {
        message: client.message(input),
      });
      const response = await fetch(endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
          "A2A-Version": "1.0",
        },
        body: raw,
      });
      const result = await response.json();
      assert.equal(result.error.code, -32008);
      assert.equal(result.error.data[CORE_URI].code, "PACT_UNSUPPORTED");
    },
  );
  await check("unsupported native A2A version is rejected", async () => {
    const raw = client.prepare("SendMessage", {
      message: client.message(input),
    });
    const response = await fetch(endpoint, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
        "A2A-Version": "0.3",
        "A2A-Extensions": CORE_URI,
      },
      body: raw,
    });
    assert.equal((await response.json()).error.code, -32009);
  });
  if (referenceSuite) {
    await check("Swarm work requires separate activation", () =>
      assert.rejects(client.send(client.message({ ...input, swarm: true })), {
        code: "SWARM_REQUIRED",
      }),
    );
    await check(
      "two concurrent questions and atomic invalid batch",
      async () => {
        let task = await client.send(
          client.message({ ...input, clarify: true }),
        );
        assert.equal(task.status.state, "TASK_STATE_INPUT_REQUIRED");
        let questions = task.metadata[CORE_URI].questions;
        assert.equal(questions.length, 2);
        const answers = questions.map((q) => ({
          response_to: q.question_id,
          task_id: task.id,
          value: { approved: true },
        }));
        await assert.rejects(
          client.send(
            client.message({}, randomUUID(), task, [
              answers[0],
              { ...answers[1], value: { approved: "yes" } },
            ]),
          ),
          { code: "CONTRACT_INVALID" },
        );
        task = await client.get(task.id);
        assert.ok(
          task.metadata[CORE_URI].questions.every((q) => q.status === "open"),
        );
        const wrong = { ...task, contextId: "wrong" };
        await assert.rejects(
          client.send(client.message({}, randomUUID(), wrong, [answers[0]])),
          { code: "QUESTION_CONTEXT" },
        );
        task = await client.send(
          client.message({}, randomUUID(), task, [answers[0]]),
        );
        assert.equal(task.status.state, "TASK_STATE_INPUT_REQUIRED");
        task = await client.send(
          client.message({}, randomUUID(), task, [answers[1]]),
        );
        assert.equal(task.status.state, "TASK_STATE_COMPLETED");
      },
    );
    await check("deadline and required expiry", async () => {
      const task = await client.send(
        client.message({
          ...input,
          clarify: true,
          deadline: new Date(Date.now() - 10000).toISOString(),
        }),
      );
      const answers = task.metadata[CORE_URI].questions.map((q) => ({
        response_to: q.question_id,
        task_id: task.id,
        value: { approved: true },
      }));
      await assert.rejects(
        client.send(client.message({}, randomUUID(), task, answers)),
        { code: "QUESTION_EXPIRED" },
      );
      assert.equal(
        (await client.get(task.id)).status.state,
        "TASK_STATE_FAILED",
      );
    });
    await check("native cancellation and terminal guard", async () => {
      const task = await client.send(
        client.message({ ...input, clarify: true }),
      );
      assert.equal(
        (await client.cancel(task.id)).status.state,
        "TASK_STATE_CANCELED",
      );
      await assert.rejects(client.cancel(task.id), {
        code: "TASK_NOT_CANCELABLE",
      });
    });
    await check(
      "durable event duplicate, conflict and out-of-order receipts",
      async () => {
        const e = {
          event_id: randomUUID(),
          task_id: randomUUID(),
          producer_id: "client",
          sequence: 2,
          type: "task.progress",
          occurred_at: new Date().toISOString(),
          correlation_id: randomUUID(),
          causation_id: null,
          payload: {
            sequence: 2,
            stage: "execution",
            completed_units: 1,
            total_units: 2,
            percentage: 50,
            updated_at: new Date().toISOString(),
          },
        };
        const signed = signEvent(e),
          raw = JSON.stringify(signed),
          url = endpoint.replace(/\/a2a$/, "/events");
        const send = async (bytes) => {
          const r = await fetch(url, {
            method: "POST",
            headers: {
              Authorization: `Bearer ${token}`,
              "Content-Type": "application/json",
              "A2A-Version": "1.0",
              "A2A-Extensions": CORE_URI,
            },
            body: bytes,
          });
          assert.equal(r.status, 200);
          return r.json();
        };
        assert.equal((await send(raw)).disposition, "accepted");
        assert.equal((await send(raw)).disposition, "duplicate");
        assert.equal(
          (await send(JSON.stringify(signed, null, 2))).error.data[CORE_URI]
            .code,
          "EVENT_CONFLICT",
        );
        const stale = signEvent({
          ...e,
          event_id: randomUUID(),
          sequence: 1,
          payload: { ...e.payload, sequence: 1 },
        });
        assert.equal((await send(JSON.stringify(stale))).disposition, "stale");
        const invalid = {
          ...signEvent({
            ...e,
            event_id: randomUUID(),
            sequence: 3,
            payload: { ...e.payload, sequence: 3 },
          }),
          sha256: "0".repeat(64),
        };
        assert.equal(
          (await send(JSON.stringify(invalid))).error.data[CORE_URI].code,
          "EVENT_DIGEST",
        );
      },
    );
  }
  return {
    profile: "PACT Core 0.2.0",
    binding: "A2A 1.0 JSONRPC",
    endpoint,
    passed: checks.length,
    checks,
  };
}
