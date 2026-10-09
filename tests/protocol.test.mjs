import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, writeFileSync, rmSync, symlinkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
import { ROOT, PactError, readJson, sha256, validateResource, validateProgress, validateContractData,
  validateAnswer, assertLifecycle, cancellationTargets, evaluateSwarm } from '../tools/lib/validation.mjs';
import { EventJournal, IdempotencyStore } from '../tools/lib/reliability.mjs';

const load = path => readJson(resolve(ROOT, path));
const registry = resolve(ROOT, 'examples/schema-registry.json');
const contract = load('examples/contract.json');
const progress = load('examples/progress.json');
const event = load('examples/event.json');
const swarm = load('examples/swarm.json');
const rejects = (fn, code) => assert.throws(fn, error => error instanceof PactError && error.code === code);

test('integer arithmetic rounds ties up and preserves two decimal precision', () => {
  for (const [completed_units, total_units, percentage] of [[1, 32, 3.13], [1, 3, 33.33], [2, 3, 66.67], [0, 1, 0], [1, 1, 100], [1, 20000, 0.01]]) {
    validateProgress({ ...progress, completed_units, total_units, percentage });
  }
});

test('schema-valid percentages cannot hide incorrect arithmetic', () => {
  rejects(() => validateProgress({ ...progress, percentage: 35.01 }), 'PROGRESS_PERCENTAGE');
});

test('stage changes do not reset a producer sequence', () => {
  rejects(() => validateProgress({ ...progress, stage: 'aggregating' }, 4), 'PROGRESS_SEQUENCE');
  rejects(() => validateProgress(progress, 5), 'PROGRESS_SEQUENCE');
  validateProgress(progress, 3);
});

test('unknown totals retain a null percentage and large safe counters work', () => {
  validateProgress({ ...progress, completed_units: Number.MAX_SAFE_INTEGER, total_units: null, percentage: null });
  validateProgress({ ...progress, completed_units: Number.MAX_SAFE_INTEGER, total_units: Number.MAX_SAFE_INTEGER, percentage: 100 });
});

test('unsafe runtime integers are rejected explicitly', () => {
  rejects(() => validateProgress({ ...progress, sequence: Number.MAX_SAFE_INTEGER + 1 }), 'INTEGER_PRECISION');
});

test('contract validation verifies bytes before validating otherwise valid data', () => {
  rejects(() => validateContractData({ ...contract.input, sha256: '0'.repeat(64) }, load('examples/input.json'), registry), 'SCHEMA_DIGEST');
  rejects(() => validateContractData(contract.input, { ...load('examples/input.json'), unexpected: true }, registry), 'CONTRACT_INVALID');
});

test('unregistered contract URLs are never fetched', () => {
  rejects(() => validateContractData({ ...contract.input, schema_uri: 'https://127.0.0.1/private' }, {}, registry), 'SCHEMA_URI');
});

test('registry traversal and symlinks cannot escape the registry tree', () => {
  const directory = mkdtempSync(resolve(tmpdir(), 'pact-registry-'));
  const outside = mkdtempSync(resolve(tmpdir(), 'pact-outside-'));
  try {
    const externalFile = resolve(outside, 'schema.json');
    writeFileSync(externalFile, '{}');
    const registryPath = resolve(directory, 'registry.json');
    writeFileSync(registryPath, JSON.stringify({ [contract.input.schema_uri]: `../${outside.split('/').at(-1)}/schema.json` }));
    rejects(() => validateContractData(contract.input, {}, registryPath), 'SCHEMA_PATH');
    symlinkSync(externalFile, resolve(directory, 'link.json'));
    writeFileSync(registryPath, JSON.stringify({ [contract.input.schema_uri]: 'link.json' }));
    rejects(() => validateContractData(contract.input, {}, registryPath), 'SCHEMA_PATH');
  } finally {
    rmSync(directory, { recursive: true, force: true });
    rmSync(outside, { recursive: true, force: true });
  }
});

test('a correctly hashed substituted schema cannot claim another URI', () => {
  const directory = mkdtempSync(resolve(tmpdir(), 'pact-schema-'));
  try {
    const schema = JSON.stringify({ $schema: 'https://json-schema.org/draft/2020-12/schema', $id: 'https://example.org/other', type: 'object' });
    writeFileSync(resolve(directory, 'schema.json'), schema);
    writeFileSync(resolve(directory, 'registry.json'), JSON.stringify({ [contract.input.schema_uri]: 'schema.json' }));
    rejects(() => validateContractData({ ...contract.input, sha256: sha256(schema) }, {}, resolve(directory, 'registry.json')), 'SCHEMA_ID');
  } finally { rmSync(directory, { recursive: true, force: true }); }
});

test('undeclared external schema references fail without fetching them', () => {
  const directory = mkdtempSync(resolve(tmpdir(), 'pact-reference-'));
  try {
    const schema = JSON.stringify({ $schema: 'https://json-schema.org/draft/2020-12/schema', $id: contract.input.schema_uri, $ref: 'https://127.0.0.1/private.schema.json' });
    writeFileSync(resolve(directory, 'schema.json'), schema);
    writeFileSync(resolve(directory, 'registry.json'), JSON.stringify({ [contract.input.schema_uri]: 'schema.json' }));
    assert.throws(() => validateContractData({ ...contract.input, sha256: sha256(schema) }, {}, resolve(directory, 'registry.json')), /can't resolve reference/);
  } finally { rmSync(directory, { recursive: true, force: true }); }
});

test('answers validate the exact declared schema and do not mutate the question', () => {
  const question = load('examples/question.json');
  const answer = load('examples/answer.json');
  const result = validateAnswer(question, answer, { contextId: 'context-review', questionContextId: 'context-review', now: '2026-10-09T08:32:00Z', registryPath: registry });
  assert.equal(question.status, 'open');
  assert.equal(result.status, 'answered');
  rejects(() => validateAnswer(question, answer, { contextId: 'context-review', questionContextId: 'context-review', now: 'not-a-clock', registryPath: registry }), 'QUESTION_TIME');
});

for (const terminal of ['COMPLETED', 'FAILED', 'CANCELED', 'REJECTED']) {
  test(`terminal ${terminal} cannot resume`, () => {
    rejects(() => assertLifecycle(`TASK_STATE_${terminal}`, 'TASK_STATE_WORKING'), 'TASK_TERMINAL');
  });
}

test('an output validation flag must be a true boolean', () => {
  rejects(() => assertLifecycle('TASK_STATE_WORKING', 'TASK_STATE_COMPLETED', { outputValidated: 'true' }), 'OUTPUT_REQUIRED');
});

test('invalid notifications do not reserve event IDs', () => {
  const journal = new EventJournal();
  const scope = { tenant: 'tenant-a', producerId: event.producer_id };
  rejects(() => journal.accept({ ...scope, raw: JSON.stringify({ ...event, sequence: 99 }) }), 'EVENT_SEQUENCE');
  assert.equal(journal.accept({ ...scope, raw: JSON.stringify(event) }).outcome, 'accepted');
});

test('accepted event IDs reject changed bytes even when the changed payload is invalid', () => {
  const journal = new EventJournal();
  const scope = { tenant: 'tenant-a', producerId: event.producer_id };
  journal.accept({ ...scope, raw: JSON.stringify(event) });
  rejects(() => journal.accept({ ...scope, raw: JSON.stringify({ ...event, payload: {} }) }), 'EVENT_CONFLICT');
});

test('contract versions follow semantic versioning including prerelease identifiers', () => {
  validateResource('contract', { ...contract, version: '1.0.0-beta.1+build.01' });
  for (const version of ['v1.0.0', '01.0.0', '1.0.0-01', '1.0']) {
    rejects(() => validateResource('contract', { ...contract, version }), 'SCHEMA_INVALID');
  }
});

test('sequence is scoped by task while receipt conflicts are scoped by event ID', () => {
  const journal = new EventJournal();
  const scope = { tenant: 'tenant-a', producerId: event.producer_id };
  journal.accept({ ...scope, raw: JSON.stringify(event) });
  const other = { ...event, task_id: 'task-other', event_id: 'event-other', sequence: 1, payload: { ...event.payload, sequence: 1 } };
  assert.equal(journal.accept({ ...scope, raw: JSON.stringify(other) }).outcome, 'accepted');
  rejects(() => journal.accept({ ...scope, raw: JSON.stringify({ ...other, event_id: event.event_id }) }), 'EVENT_CONFLICT');
});

test('scope tuples cannot collide through delimiter-containing opaque IDs', () => {
  const journal = new EventJournal();
  assert.equal(journal.accept({ tenant: 'a|b', producerId: 'c', raw: JSON.stringify({ ...event, producer_id: 'c' }) }).outcome, 'accepted');
  assert.equal(journal.accept({ tenant: 'a', producerId: 'b|c', raw: JSON.stringify({ ...event, producer_id: 'b|c' }) }).outcome, 'accepted');
});

test('receipt creation is skipped on duplicate commands', () => {
  const store = new IdempotencyStore();
  const input = { tenant: 'tenant-a', callerId: 'caller-a', key: 'command-1', raw: '{}' };
  let created = 0;
  const createTask = () => `task-${++created}`;
  assert.equal(store.accept({ ...input, createTask }).taskId, 'task-1');
  assert.equal(store.accept({ ...input, createTask }).taskId, 'task-1');
  assert.equal(created, 1);
});

test('failed task creation does not leave an accepted command receipt', () => {
  const store = new IdempotencyStore();
  const input = { tenant: 'tenant-a', callerId: 'caller-a', key: 'command-1', raw: '{}' };
  assert.throws(() => store.accept({ ...input, createTask: () => { throw new Error('storage failure'); } }), /storage failure/);
  assert.deepEqual(store.snapshot(), []);
  assert.equal(store.accept({ ...input, createTask: () => 'task-retry' }).duplicate, false);
});

test('untrusted missing scope cannot create receipts', () => {
  rejects(() => new EventJournal().accept({ tenant: '', producerId: event.producer_id, raw: JSON.stringify(event) }), 'SECURITY_SCOPE');
  rejects(() => new IdempotencyStore().accept({ tenant: 'tenant-a', key: 'key', raw: '{}', createTask: () => 'task' }), 'SECURITY_SCOPE');
});

test('cancellation targets outstanding children and leaves observed states intact', () => {
  const pending = structuredClone(swarm);
  pending.children[1].state = 'TASK_STATE_WORKING';
  pending.children[1].result_artifact_ids = [];
  pending.provenance.contributors[1].artifact_ids = [];
  pending.provenance.contributors[1].validation_verdict = 'pending';
  const before = structuredClone(pending);
  assert.deepEqual(cancellationTargets(pending), ['review']);
  assert.deepEqual(pending, before);
});

test('a completed child without matching artifact evidence does not count', () => {
  const missing = structuredClone(swarm);
  missing.provenance.contributors[1].artifact_ids = ['another-artifact'];
  assert.equal(evaluateSwarm(missing).canComplete, false);
});

test('duplicate children and wrong parent references are rejected', () => {
  const duplicate = structuredClone(swarm);
  duplicate.children[1].child_id = duplicate.children[0].child_id;
  rejects(() => evaluateSwarm(duplicate), 'DAG_DUPLICATE');
  const wrongParent = structuredClone(swarm);
  wrongParent.children[0].parent_task_id = 'other-parent';
  rejects(() => evaluateSwarm(wrongParent), 'DAG_PARENT');
});

test('two-agent fixture flow reaches completion after a correlated answer and validated output', () => {
  validateContractData(contract.input, load('examples/a2a/send-message.json').params.message.parts[0].data, registry);
  assertLifecycle('TASK_STATE_SUBMITTED', 'TASK_STATE_WORKING');
  assertLifecycle('TASK_STATE_WORKING', 'TASK_STATE_INPUT_REQUIRED');
  const answered = validateAnswer(load('examples/question.json'), load('examples/answer.json'), { contextId: 'context-review', questionContextId: 'context-review', now: '2026-10-09T08:32:00Z', registryPath: registry });
  assertLifecycle('TASK_STATE_INPUT_REQUIRED', 'TASK_STATE_WORKING');
  const completed = load('examples/a2a/completed-task.json').result.task;
  validateContractData(contract.output, completed.artifacts[0].parts[0].data, registry);
  assert.equal(assertLifecycle('TASK_STATE_WORKING', completed.status.state, { outputValidated: true, requiredQuestions: [answered] }), 'TASK_STATE_COMPLETED');
});

test('validation CLI returns machine-readable rejection and nonzero exit', () => {
  const result = spawnSync(process.execPath, [resolve(ROOT, 'tools/validate.mjs'), 'progress', resolve(ROOT, 'conformance/fixtures/progress-wrong-percentage.json'), '--json'], { encoding: 'utf8' });
  assert.equal(result.status, 1);
  assert.equal(JSON.parse(result.stderr).code, 'PROGRESS_PERCENTAGE');
});

test('CLI reports usage errors separately from validation failures', () => {
  for (const [tool, args] of [['validate', []], ['conformance', ['--unknown']]]) {
    const result = spawnSync(process.execPath, [resolve(ROOT, `tools/${tool}.mjs`), ...args], { encoding: 'utf8' });
    assert.equal(result.status, 2);
  }
});
