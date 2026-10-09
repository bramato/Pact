import { phpExecutable } from "../tools/runtime.mjs";
import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync, existsSync } from "node:fs";
import { spawnSync } from "node:child_process";
import {
  Pact,
  canonicalJson,
  parseJson,
  signEvent,
  EventReceipts,
} from "../sdk/typescript/dist/index.js";
const pact = new Pact(),
  vectors = JSON.parse(
    readFileSync(new URL("../conformance/v0.2/vectors.json", import.meta.url)),
  ),
  registryPath = new URL(
    "../examples/v0.2/schema-registry.json",
    import.meta.url,
  ).pathname;
const php = spawnSync(phpExecutable(), ["conformance/v0.2/runner.php"], {
  encoding: "utf8",
});
assert.equal(php.status, 0, php.stderr);
const results = JSON.parse(php.stdout);
for (const [vIndex, v] of vectors.entries())
  test(v.id, () => {
    let actual;
    try {
      const args = structuredClone(v.args);
      if (v.op === "parse") actual = { value: parseJson(...args) };
      else if (v.op === "canonical") actual = { value: canonicalJson(...args) };
      else if (v.op === "contractData") {
        pact.contractData(...args, registryPath);
        actual = { value: "valid" };
      } else {
        if (v.op === "answers") args[2].registryPath = registryPath;
        actual = { value: pact[v.op](...args) };
      }
    } catch (e) {
      actual = { error: e.code || "IMPLEMENTATION_ERROR" };
    }
    const expected = v.error ? { error: v.error } : { value: v.expected };
    assert.deepEqual(actual, expected, "TypeScript");
    const { id, ...phpActual } = results[vIndex];
    assert.deepEqual(phpActual, expected, "PHP");
  });
test("event typed integrity and exact replay", () => {
  const event = signEvent({
    event_id: "e1",
    producer_id: "worker",
    task_id: "t1",
    sequence: 4,
    type: "task.status",
    occurred_at: "2026-10-09T12:00:00Z",
    correlation_id: "c1",
    causation_id: null,
    payload: { state: "TASK_STATE_WORKING" },
  });
  pact.event(event);
  assert.throws(() => pact.event({ ...event, task_id: "other" }), {
    code: "EVENT_DIGEST",
  });
  assert.throws(
    () =>
      pact.event(
        signEvent({
          ...event,
          payload: { state: "TASK_STATE_WORKING", untyped: true },
        }),
      ),
    { code: "SCHEMA_INVALID" },
  );
  const receipts = new EventReceipts();
  const raw = JSON.stringify(event);
  assert.equal(receipts.accept("tenant", "worker", raw), "accepted");
  assert.equal(receipts.accept("tenant", "worker", raw), "duplicate");
  assert.throws(
    () => receipts.accept("tenant", "worker", JSON.stringify(event, null, 2)),
    { code: "EVENT_CONFLICT" },
  );
  assert.equal(
    receipts.accept(
      "tenant",
      "worker",
      JSON.stringify(signEvent({ ...event, event_id: "e0", sequence: 3 })),
    ),
    "stale",
  );
});
