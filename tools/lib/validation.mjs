import { createHash } from 'node:crypto';
import { readFileSync, readdirSync, realpathSync } from 'node:fs';
import { dirname, isAbsolute, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import Ajv2020 from 'ajv/dist/2020.js';
import addFormats from 'ajv-formats';

export const ROOT = fileURLToPath(new URL('../../', import.meta.url));
export const EXTENSION_URI = 'https://example.org/pact/v1';
export const STATES = new Set(['UNSPECIFIED', 'SUBMITTED', 'WORKING', 'INPUT_REQUIRED',
  'AUTH_REQUIRED', 'COMPLETED', 'FAILED', 'CANCELED', 'REJECTED'].map(s => `TASK_STATE_${s}`));
export const TERMINAL = new Set(['COMPLETED', 'FAILED', 'CANCELED', 'REJECTED'].map(s => `TASK_STATE_${s}`));

export class PactError extends Error {
  constructor(code, message, details = []) {
    super(message);
    this.name = 'PactError';
    this.code = code;
    this.details = details;
  }
}

export function requireRule(condition, code, message) {
  if (!condition) throw new PactError(code, message);
}

export function readJson(path) { return JSON.parse(readFileSync(path, 'utf8')); }
export function sha256(bytes) { return createHash('sha256').update(bytes).digest('hex'); }
function newValidator() {
  const validator = new Ajv2020({ allErrors: true, strict: true, strictTypes: false });
  addFormats(validator);
  return validator;
}

const ajv = newValidator();
for (const file of readdirSync(resolve(ROOT, 'specification/schemas'))) {
  if (file.endsWith('.schema.json')) ajv.addSchema(readJson(resolve(ROOT, 'specification/schemas', file)));
}
// Compile every schema, including resources without a standalone fixture.
for (const file of readdirSync(resolve(ROOT, 'specification/schemas'))) {
  if (file.endsWith('.schema.json')) ajv.getSchema(`${EXTENSION_URI}/schemas/${file}`);
}

export function validateShape(name, value) {
  const validator = ajv.getSchema(`${EXTENSION_URI}/schemas/${name}.schema.json`);
  requireRule(validator, 'SCHEMA_UNKNOWN', `Unknown PACT schema: ${name}`);
  if (!validator(value)) {
    throw new PactError('SCHEMA_INVALID', `Invalid ${name} resource`, structuredClone(validator.errors));
  }
  return value;
}

function safeCounter(value) {
  requireRule(Number.isSafeInteger(value), 'INTEGER_PRECISION', 'Runtime counters must be safe integers');
}

export function validateProgress(progress, previousSequence = null) {
  validateShape('progress', progress);
  safeCounter(progress.sequence);
  safeCounter(progress.completed_units);
  if (previousSequence !== null) {
    safeCounter(previousSequence);
    requireRule(progress.sequence > previousSequence, 'PROGRESS_SEQUENCE', 'Progress sequence must increase');
  }
  if (progress.total_units !== null) {
    safeCounter(progress.total_units);
    requireRule(progress.completed_units <= progress.total_units, 'PROGRESS_UNITS', 'Completed units exceed total');
    const numerator = BigInt(progress.completed_units) * 10000n;
    const denominator = BigInt(progress.total_units);
    const hundredths = (2n * numerator + denominator) / (2n * denominator);
    const expected = Number(hundredths) / 100;
    requireRule(progress.percentage === expected, 'PROGRESS_PERCENTAGE', `Expected percentage ${expected}`);
  }
  return progress;
}

export function validateEvent(event) {
  validateShape('event', event);
  safeCounter(event.sequence);
  if (event.type === 'progress') {
    validateProgress(event.payload);
    requireRule(event.sequence === event.payload.sequence, 'EVENT_SEQUENCE', 'Event/progress sequences differ');
  }
  return event;
}

function childSucceeded(child, contributors) {
  const verdict = contributors.get(child.child_id);
  return child.state === 'TASK_STATE_COMPLETED' && child.result_artifact_ids.length > 0 &&
    verdict?.validation_verdict === 'valid' &&
    child.result_artifact_ids.every(id => verdict.artifact_ids.includes(id));
}

export function validateSwarm(swarm) {
  validateShape('swarm', swarm);
  const children = new Map();
  for (const child of swarm.children) {
    requireRule(!children.has(child.child_id), 'DAG_DUPLICATE', 'Child IDs must be unique');
    requireRule(child.parent_task_id === swarm.parent_task_id, 'DAG_PARENT', 'Child references another parent');
    children.set(child.child_id, child);
  }
  const visiting = new Set();
  const visited = new Set();
  function visit(id) {
    requireRule(!visiting.has(id), 'DAG_CYCLE', 'Dependencies contain a cycle');
    if (visited.has(id)) return;
    visiting.add(id);
    for (const dependency of children.get(id).depends_on) {
      requireRule(children.has(dependency), 'DAG_DEPENDENCY', `Unknown dependency: ${dependency}`);
      visit(dependency);
    }
    visiting.delete(id);
    visited.add(id);
  }
  for (const id of children.keys()) visit(id);
  requireRule(swarm.provenance.coordinator_id === swarm.coordinator_id, 'PROVENANCE_OWNER', 'Provenance owner differs');
  const contributors = new Map();
  for (const contributor of swarm.provenance.contributors) {
    requireRule(children.has(contributor.child_id) && !contributors.has(contributor.child_id),
      'PROVENANCE_CHILD', 'Unknown or repeated provenance child');
    contributors.set(contributor.child_id, contributor);
  }
  requireRule(contributors.size === children.size, 'PROVENANCE_MISSING', 'Each child needs an audit record');
  const started = new Set(['WORKING', 'INPUT_REQUIRED', 'AUTH_REQUIRED', 'COMPLETED', 'FAILED'].map(s => `TASK_STATE_${s}`));
  for (const child of children.values()) {
    if (started.has(child.state)) {
      requireRule(child.depends_on.every(id => childSucceeded(children.get(id), contributors)),
        'DAG_NOT_READY', `Child ${child.child_id} began before its dependencies succeeded`);
    }
  }
  const policy = swarm.policy;
  if (policy.type === 'all_required') {
    requireRule(swarm.children.some(child => child.required), 'REQUIRED_EMPTY', 'all_required needs a required child');
  } else {
    safeCounter(policy.min_success);
    requireRule(policy.eligible_child_ids.every(id => children.has(id)), 'QUORUM_ELIGIBILITY', 'Unknown eligible child');
    requireRule(policy.min_success <= policy.eligible_child_ids.length, 'QUORUM_BOUNDS', 'Quorum exceeds eligible count');
  }
  return swarm;
}

export function evaluateSwarm(swarm) {
  validateSwarm(swarm);
  const contributors = new Map(swarm.provenance.contributors.map(c => [c.child_id, c]));
  const successful = swarm.children.filter(child => childSucceeded(child, contributors)).map(c => c.child_id);
  const aggregationReady = swarm.policy.type === 'all_required'
    ? swarm.children.filter(c => c.required).every(c => successful.includes(c.child_id))
    : swarm.policy.eligible_child_ids.filter(id => successful.includes(id)).length >= swarm.policy.min_success;
  return { successful, aggregationReady, canComplete: aggregationReady && swarm.parent_output_validated };
}

export function validateResource(name, value) {
  validateShape(name, value);
  if (name === 'progress') validateProgress(value);
  if (name === 'event') validateEvent(value);
  if (name === 'swarm') validateSwarm(value);
  if (name === 'metadata') {
    for (const nested of ['progress', 'event', 'swarm']) {
      if (value[nested]) validateResource(nested, value[nested]);
    }
  }
  return value;
}

export function resolveSchema(descriptor, registryPath) {
  validateShape('schema-reference', descriptor);
  const registry = readJson(registryPath);
  const mapping = Object.hasOwn(registry, descriptor.schema_uri) ? registry[descriptor.schema_uri] : undefined;
  requireRule(typeof mapping === 'string' && !isAbsolute(mapping), 'SCHEMA_URI', 'Schema URI is not in the local registry');
  const registryRoot = realpathSync(dirname(resolve(registryPath)));
  const path = realpathSync(resolve(registryRoot, mapping));
  const within = relative(registryRoot, path);
  requireRule(within !== '..' && !within.startsWith('../') && !isAbsolute(within),
    'SCHEMA_PATH', 'Schema path escapes the registry directory');
  const bytes = readFileSync(path);
  requireRule(bytes.length <= 1024 * 1024, 'SCHEMA_SIZE', 'Local schema exceeds 1 MiB');
  requireRule(sha256(bytes) === descriptor.sha256, 'SCHEMA_DIGEST', 'Schema bytes do not match the declared digest');
  const schema = JSON.parse(bytes.toString('utf8'));
  requireRule(schema.$id === descriptor.schema_uri, 'SCHEMA_ID', 'Schema ID differs from declared URI');
  requireRule(schema.$schema === 'https://json-schema.org/draft/2020-12/schema',
    'SCHEMA_DIALECT', 'Expected JSON Schema Draft 2020-12');
  return schema;
}

export function validateContractData(descriptor, value, registryPath) {
  const schema = resolveSchema(descriptor, registryPath);
  const validator = newValidator().compile(schema);
  if (!validator(value)) throw new PactError('CONTRACT_INVALID', 'Data violates the exact declared contract', validator.errors);
  return value;
}

export function validateAnswer(question, answer, { contextId, questionContextId, now, registryPath }) {
  validateShape('question', question);
  validateShape('answer', answer);
  requireRule(question.task_id === answer.task_id && question.question_id === answer.response_to,
    'QUESTION_CORRELATION', 'Answer references another question or task');
  requireRule(typeof contextId === 'string' && contextId.length > 0 && contextId === questionContextId,
    'QUESTION_CONTEXT', 'Answer context differs from question context');
  requireRule(question.status === 'open', 'QUESTION_CLOSED', 'Question no longer accepts answers');
  const time = Date.parse(now);
  requireRule(Number.isFinite(time), 'QUESTION_TIME', 'An explicit valid clock is required');
  if (question.expires_at !== null) {
    requireRule(time < Date.parse(question.expires_at), 'QUESTION_EXPIRED', 'Question deadline has passed');
  }
  validateContractData(question.answer_schema, answer.value, registryPath);
  return { ...question, status: 'answered' };
}

export function expireQuestion(question, now) {
  validateShape('question', question);
  const time = Date.parse(now);
  requireRule(Number.isFinite(time), 'QUESTION_TIME', 'An explicit valid clock is required');
  const expired = question.status === 'open' && question.expires_at !== null && time >= Date.parse(question.expires_at);
  return {
    question: expired ? { ...question, status: 'expired' } : { ...question },
    taskState: expired && question.required ? 'TASK_STATE_FAILED' : null,
  };
}

export function negotiate(extensionParams, { version, contract, activated, allowDowngrade = false }) {
  if (!extensionParams || !activated) {
    requireRule(allowDowngrade === true, 'PACT_UNSUPPORTED', 'PACT activation was not confirmed');
    return { mode: 'a2a' };
  }
  validateShape('extension-params', extensionParams);
  validateShape('contract', contract);
  requireRule(extensionParams.versions.includes(version), 'PACT_VERSION', 'Unsupported required PACT version');
  const advertised = extensionParams.contracts.find(c => c.contract_id === contract.contract_id && c.version === contract.version);
  requireRule(advertised, 'CONTRACT_UNSUPPORTED', 'Exact contract version is not advertised');
  for (const field of ['input', 'output', 'question_answer']) {
    const expected = contract[field];
    const actual = advertised[field];
    requireRule((!expected && !actual) || (expected && actual && expected.schema_uri === actual.schema_uri && expected.sha256 === actual.sha256),
      'CONTRACT_MISMATCH', 'Advertised contract URI or digest differs');
  }
  return { mode: 'pact', version };
}

export function assertLifecycle(previous, next, { outputValidated = false, requiredQuestions = [] } = {}) {
  requireRule(STATES.has(previous) && STATES.has(next), 'TASK_STATE', 'Unknown A2A task state');
  requireRule(!TERMINAL.has(previous) || next === previous, 'TASK_TERMINAL', 'Terminal task state is immutable');
  if (next === 'TASK_STATE_COMPLETED') {
    requireRule(outputValidated === true, 'OUTPUT_REQUIRED', 'Completion requires validated output artifacts');
    requireRule(!requiredQuestions.some(q => q.required && ['open', 'expired'].includes(q.status)),
      'QUESTION_REQUIRED', 'Completion is blocked by an unresolved required question');
  }
  return next;
}

export function cancellationTargets(swarm) {
  validateSwarm(swarm);
  return swarm.children.filter(child => !TERMINAL.has(child.state)).map(child => child.child_id);
}
