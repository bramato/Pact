#!/usr/bin/env node
import { resolve } from 'node:path';
import { ROOT, readJson, validateResource } from './lib/validation.mjs';
import { runScenario } from './lib/scenarios.mjs';

const args = process.argv.slice(2);
if (args.includes('--help')) {
  console.log('Usage: node tools/conformance.mjs [--json]\nRuns the portable PACT schema and semantic fixture corpus.');
  process.exit(0);
}
if (args.some(arg => arg !== '--json')) {
  console.error('Usage: node tools/conformance.mjs [--json]');
  process.exit(2);
}
const suite = readJson(resolve(ROOT, 'conformance/cases.json'));
const results = suite.cases.map(test => {
  let error = null;
  try {
    validateResource(test.schema, readJson(resolve(ROOT, 'conformance', test.file)));
  } catch (caught) {
    error = caught.code ?? caught.name;
  }
  const passed = test.valid ? error === null : error === test.error;
  return { id: test.id, passed, expected: test.valid ? 'valid' : test.error, actual: error ?? 'valid' };
});
for (const scenario of readJson(resolve(ROOT, 'conformance/scenarios.json')).scenarios) {
  results.push(...runScenario(scenario, resolve(ROOT, 'examples/schema-registry.json')));
}
const passed = results.filter(result => result.passed).length;
if (args.includes('--json')) {
  console.log(JSON.stringify({ version: suite.version, passed, total: results.length, results }, null, 2));
} else {
  for (const result of results.filter(result => !result.passed)) {
    console.error(`FAIL ${result.id}: expected ${JSON.stringify(result.expected)}, got ${JSON.stringify(result.actual)}`);
  }
  console.log(`PACT ${suite.version} conformance: ${passed}/${results.length} checks passed`);
}
process.exitCode = passed === results.length ? 0 : 1;
