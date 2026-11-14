import test from 'node:test';
import assert from 'node:assert/strict';
import {federationToolDefinitions, federationToolArguments} from '../sdk/typescript/dist/index.js';
test('federation send arguments normalize and validate optional fields', () => {
  assert.deepEqual(federationToolDefinitions().find(x => x.name === 'send_message').inputSchema.required, ['id','recipient_id','text']);
  assert.equal(federationToolArguments('send_message',{id:'1',recipient_id:'b',text:'hello'}).kind,'request');
  assert.throws(() => federationToolArguments('send_message',{id:'1',recipient_id:'b',text:'x',kind:'reply'}));
  assert.throws(() => federationToolArguments('send_message',{id:'1',recipient_id:'b',text:'x',kind:'other'}));
  assert.deepEqual(federationToolArguments('reserve_step',{task_id:'t',step_id:'s'}),{task_id:'t',step_id:'s'});
});
