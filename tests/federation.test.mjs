import test from 'node:test';
import assert from 'node:assert/strict';
import { Pact } from '../sdk/typescript/dist/index.js';

const roster = { federation_id: 'f', coordinator_url: 'https://coordinator.example/', revision: 1, mcp_endpoint: 'https://coordinator.example/mcp', members: [{ id: 'alice', kind: 'user', active: true }] };
const delegation = { root_task_id: 'root', parent_task_id: 'parent', delegation_id: 'd1', path: ['a', 'b'], steps: 1, delegations: 1, task_id: 'child', created_at: '2026-11-14T11:59:00Z', deadline: '2026-11-14T12:00:00Z' };
test('federation roster and delegation shapes reject duplicate members, cycles and excess budgets', () => {
  const pact = new Pact();
  pact.federationRoster(roster);
  pact.federationDelegation(delegation);
  assert.throws(() => pact.federationRoster({ ...roster, members: [...roster.members, roster.members[0]] }));
  assert.throws(() => pact.federationDelegation({ ...delegation, path: ['a', 'b', 'a'] }));
  assert.throws(() => pact.federationDelegation({ ...delegation, delegations: 9 }));
  assert.throws(() => pact.federationRoster({ ...roster, coordinator_url: 'http://example.com' }));
});

import { readFileSync } from 'node:fs';
const vectors = JSON.parse(readFileSync(new URL('../conformance/federation/delegation-vectors.json', import.meta.url)));
for (const vector of vectors) test(`Federation vector ${vector.name}`, () => {
  const validate = () => new Pact().federationDelegation(vector.delegation);
  if (vector.valid) validate(); else assert.throws(validate);
});

import { federationAgentId, federationAuthenticate, federationRefresh, federationRosterEtag } from '../sdk/typescript/dist/index.js';
test('roster revisions, verified audience and identity policy', () => {
  const tag = federationRosterEtag(roster);
  assert.equal(federationRefresh(null, roster, roster.coordinator_url, tag, 200), roster);
  assert.equal(federationRefresh(roster, null, roster.coordinator_url, tag, 304), roster);
  const changed = { ...roster, mcp_endpoint: 'https://other.example/mcp' };
  assert.throws(() => federationRefresh(roster, changed, roster.coordinator_url, federationRosterEtag(changed), 200));
  assert.throws(() => federationRefresh(roster, null, roster.coordinator_url, '"wrong"', 304));
  const claims = { iss: 'issuer', aud: 'mcp', sub: 'alice', exp: 101 };
  assert.equal(federationAuthenticate(claims, 'issuer', 'mcp', 100), 'alice');
  assert.throws(() => federationAuthenticate(claims, 'issuer', 'peer', 100));
  assert.throws(() => federationAuthenticate(claims, 'issuer', 'mcp', 101));
  assert.notEqual(federationAgentId('ab', 'c'), federationAgentId('a', 'bc'));
});
test('shared roster and stable identity fixtures match PHP', () => {
  const fixture = JSON.parse(readFileSync(new URL('../conformance/federation/fixtures/roster.json', import.meta.url)));
  const expected = JSON.parse(readFileSync(new URL('../conformance/federation/fixtures/expected.json', import.meta.url)));
  assert.equal(federationRosterEtag(fixture), expected.etag);
  assert.equal(federationAgentId('prodotto', 'utente'), expected.agent_id);
});
test('HTTP requires explicit local allowlist and cannot allow public hosts', () => {
  const local = { ...roster, coordinator_url: 'http://octodorabot.test/', mcp_endpoint: 'http://octodorabot.test/mcp' };
  assert.throws(() => new Pact().federationRoster(local));
  new Pact().federationRoster(local, ['octodorabot.test']);
  const tag = federationRosterEtag(local, ['octodorabot.test']);
  assert.equal(federationRefresh(null, local, local.coordinator_url, tag, 200, ['octodorabot.test']), local);
  assert.throws(() => new Pact().federationRoster({ ...local, coordinator_url: 'http://example.com/' }, ['example.com', 'octodorabot.test']));
});

test('304 cannot change coordinator authority', () => { assert.throws(() => federationRefresh(roster, null, 'https://other.example/', federationRosterEtag(roster), 304)); });
