#!/usr/bin/env node
import { readJson, validateResource } from './lib/validation.mjs';

const args = process.argv.slice(2);
if (args.includes('--help')) {
  console.log('Usage: node tools/validate.mjs <resource> <file.json> [--json]\nResources: progress, contract, question, answer, event, child, aggregation-policy, provenance, swarm, metadata, error, schema-reference, extension-params');
  process.exit(0);
}
const json = args.includes('--json');
const positional = args.filter(arg => arg !== '--json');
if (positional.length !== 2 || positional.some(arg => arg.startsWith('--'))) {
  console.error('Usage: node tools/validate.mjs <resource> <file.json> [--json]');
  process.exit(2);
}
const [resource, path] = positional;
try {
  validateResource(resource, readJson(path));
  console.log(json ? JSON.stringify({ valid: true, resource }) : `Valid PACT ${resource}: ${path}`);
} catch (error) {
  const result = { valid: false, resource, code: error.code ?? error.name, message: error.message, details: error.details ?? [] };
  console.error(json ? JSON.stringify(result) : `${result.code}: ${result.message}`);
  process.exitCode = 1;
}
