import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { phpExecutable } from '../tools/runtime.mjs';
for (const script of ['federation-shapes.php', 'federation-policy.php', 'federation-ledger.php', 'federation-runtime.php', 'laravel-adapter.php', 'laravel-provider.php']) {
  test(`PHP ${script}`, () => {
    const result = spawnSync(phpExecutable(), [`tests/${script}`], { encoding: 'utf8' });
    assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
  });
}
