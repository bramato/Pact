import test from 'node:test';
import assert from 'node:assert/strict';
import { Pact } from '../sdk/typescript/dist/index.js';

const roster = { federation_id: 'f', coordinator_url: 'https://coordinator.example/', revision: 1, mcp_endpoint: 'https://coordinator.example/mcp', members: [{ id: 'alice', kind: 'user', active: true }] };
const delegation = { root_task_id: 'root', parent_task_id: 'parent', delegation_id: 'd1', path: ['a', 'b'], steps: 1, delegations: 1, deadline: '2026-11-14T12:00:00Z' };
test('federation roster and delegation shapes reject duplicate members, cycles and excess budgets', () => {
  const pact = new Pact();
  pact.federationRoster(roster);
  pact.federationDelegation(delegation);
  assert.throws(() => pact.federationRoster({ ...roster, members: [...roster.members, roster.members[0]] }));
  assert.throws(() => pact.federationDelegation({ ...delegation, path: ['a', 'b', 'a'] }));
  assert.throws(() => pact.federationDelegation({ ...delegation, delegations: 9 }));
  assert.throws(() => pact.federationRoster({ ...roster, coordinator_url: 'http://example.com' }));
});
