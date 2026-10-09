# PACT TypeScript SDK

`@pact-protocol/core` provides typed resources, offline validation, JCS event integrity, correlated answer batches, lifecycle gates, negotiation, graph validation, weighted progress, scoped in-memory receipts and an A2A 1.0 JSON-RPC client.

Build this checkout with `npm ci --ignore-scripts && npm run build:sdk` from its root. The package has not been published; do not assume an npm install by name currently works. `npm pack --workspace @pact-protocol/core` creates a standalone package containing compiled code, declarations, schemas and MIT license. It requires Node.js 22+.

```ts
import { PactClient } from '@pact-protocol/core';

const client = new PactClient('https://peer.example.org/a2a', {
  token: process.env.PACT_TOKEN!,
  contract,                 // exact pinned descriptor from your registry
  registryPath: '/absolute/path/schema-registry.json',
});
await client.discover();
const task = await client.send(client.message({
  text: 'PACT connects three careful agents', clarify: false, swarm: false,
}));
console.log(task.artifacts?.[0].parts[0].data);
```

[The executable demo](https://github.com/bramato/Pact/blob/main/examples/v0.2/demo.mjs) loads the local descriptors and private configuration. Use `client.prepare()` and persist its returned raw bytes before retrying with `client.raw()`; constructing a new message changes the acceptance bytes. The client verifies response ID, activation, task/contract correlation and completed output data. It does not own a scheduler or persistent database. `EventReceipts` is an in-memory helper; durable adapters must transact their own receipt and projection records.

`new Pact()` loads bundled schemas. `shape`, `progress`, `event`, `contractData`, `answers`, `transition`, `negotiate`, `graph` and `swarmProgress` are synchronous offline checks. `PactError.code` is stable for documented vectors. Schema resolution uses local mappings and never fetches arbitrary URLs.

See [Core](https://github.com/bramato/Pact/blob/main/specification/PACT-CORE-v0.2.md), [Swarm](https://github.com/bramato/Pact/blob/main/specification/PACT-SWARM-v0.2.md) and [shared vectors](https://github.com/bramato/Pact/blob/main/conformance/v0.2/vectors.json). The package is independent of the legacy v0.1 fixture validator.
