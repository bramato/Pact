import assert from 'node:assert/strict';
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { ROOT, EXTENSION_URI, STATES, readJson, sha256, validateResource,
  validateShape, validateContractData, validateAnswer, assertLifecycle, negotiate } from './lib/validation.mjs';

const registryPath = resolve(ROOT, 'examples/schema-registry.json');
const contract = readJson(resolve(ROOT, 'examples/contract.json'));
validateResource('contract', contract);
for (const field of ['input', 'output', 'question_answer']) {
  const data = readJson(resolve(ROOT, `examples/${field === 'question_answer' ? 'answer' : field}.json`));
  validateContractData(contract[field], field === 'question_answer' ? data.value : data, registryPath);
}

const manifest = readJson(resolve(ROOT, 'conformance/manifest.json'));
assert.equal(readFileSync(resolve(ROOT, 'VERSION'), 'utf8').trim(), manifest.version);
assert.equal(readJson(resolve(ROOT, 'package.json')).version, manifest.version);
for (const source of manifest.source_documents) {
  assert.equal(sha256(readFileSync(resolve(ROOT, 'conformance', source.file))), source.sha256, 'Supplied document changed');
}

function checkPart(part) {
  assert.equal(['text', 'data', 'raw', 'url'].filter(field => Object.hasOwn(part, field)).length, 1, 'A2A Part has one content member');
  assert.equal(part.kind, undefined, 'A2A 1.0 does not use the old Part.kind discriminator');
}
function checkMessage(message) {
  assert.ok(message.messageId);
  assert.ok(['ROLE_USER', 'ROLE_AGENT'].includes(message.role));
  assert.ok(message.parts.length > 0);
  message.parts.forEach(checkPart);
  if (message.role === 'ROLE_AGENT') assert.ok(message.contextId);
  const pact = message.metadata?.[EXTENSION_URI];
  if (pact?.question) {
    assert.equal(pact.question.task_id, message.taskId);
    assert.deepEqual(message.parts[0].data, pact.question);
  }
  if (pact?.answer) {
    assert.equal(pact.answer.task_id, message.taskId);
    assert.deepEqual(message.parts[0].data, pact.answer.value);
  }
}
function walk(value) {
  if (!value || typeof value !== 'object') return;
  if (value.metadata?.[EXTENSION_URI]) validateResource('metadata', value.metadata[EXTENSION_URI]);
  if (value.messageId) checkMessage(value);
  if (value.status?.state) {
    assert.ok(STATES.has(value.status.state));
    assert.ok(value.status.timestamp.endsWith('Z'));
    if (value.status.message) {
      assert.equal(value.status.message.taskId, value.id ?? value.taskId);
      assert.equal(value.status.message.contextId, value.contextId);
    }
  }
  if (value.artifactId) {
    assert.ok(value.parts.length > 0);
    value.parts.forEach(checkPart);
    const declared = value.metadata[EXTENSION_URI].contract;
    assert.deepEqual(declared, contract);
    validateContractData(declared.output, value.parts[0].data, registryPath);
  }
  for (const nested of Object.values(value)) walk(nested);
}
for (const file of readdirSync(resolve(ROOT, 'examples/a2a'))) {
  if (file.endsWith('.json')) walk(readJson(resolve(ROOT, 'examples/a2a', file)));
}
const card = readJson(resolve(ROOT, 'examples/a2a/agent-card.json'));
assert.equal(card.supportedInterfaces[0].protocolVersion, '1.0');
assert.equal(card.supportedInterfaces[0].protocolBinding, 'JSONRPC');
const params = card.capabilities.extensions.find(ext => ext.uri === EXTENSION_URI).params;
validateShape('extension-params', params);
negotiate(params, { version: manifest.version, contract, activated: true });
for (const file of ['send-message', 'answer-message']) {
  const request = readJson(resolve(ROOT, `examples/a2a/${file}.json`));
  assert.equal(request.method, 'SendMessage');
  assert.equal(request.jsonrpc, '2.0');
}
for (const file of ['working-task', 'completed-task']) {
  const response = readJson(resolve(ROOT, `examples/a2a/${file}.json`));
  assert.equal(response.jsonrpc, '2.0');
  assert.ok(response.result.task.id);
}
const answerMessage = readJson(resolve(ROOT, 'examples/a2a/answer-message.json')).params.message;
validateAnswer(readJson(resolve(ROOT, 'examples/question.json')), answerMessage.metadata[EXTENSION_URI].answer,
  { contextId: answerMessage.contextId, questionContextId: 'context-review', now: '2026-10-09T08:32:00Z', registryPath });
const completed = readJson(resolve(ROOT, 'examples/a2a/completed-task.json')).result.task;
assertLifecycle('TASK_STATE_WORKING', completed.status.state, { outputValidated: true });

function checkLinks(directory) {
  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    if (['node_modules', '.git'].includes(entry.name)) continue;
    const path = resolve(directory, entry.name);
    if (entry.isDirectory()) { checkLinks(path); continue; }
    if (!entry.name.endsWith('.md')) continue;
    const content = readFileSync(path, 'utf8').replace(/```[\s\S]*?```/g, '');
    const links = [...content.matchAll(/\[[^\]]*\]\(([^\s)]+)\)/g)].map(match => match[1]);
    links.push(...[...content.matchAll(/<img[^>]+src="([^"]+)"/g)].map(match => match[1]));
    for (const link of links) {
      if (/^[a-z]+:|^#/i.test(link)) continue;
      const target = decodeURIComponent(link.split('#')[0]);
      assert.ok(existsSync(resolve(dirname(path), target)), `Broken local link in ${entry.name}: ${link}`);
    }
  }
}
checkLinks(ROOT);
const readme = readFileSync(resolve(ROOT, 'README.md'), 'utf8');
for (const path of new Set([...readme.matchAll(/docs\/readme\/[^"\s)>]+\.png/g)].map(match => match[0]))) {
  const png = readFileSync(resolve(ROOT, path));
  assert.equal(png.subarray(1, 4).toString(), 'PNG');
  assert.equal(png[25], 6, 'Editorial asset must be an RGBA PNG');
}
console.log('Repository checks passed: source integrity, contracts, A2A examples, versions, local links and assets');
