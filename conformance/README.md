# Archived v0.1 conformance corpus

Current implementations and live tests use [v0.2 vectors](v0.2/README.md). This page documents the retained source-proposal corpus.

The portable interface consists of JSON files, independent of any A2A SDK:

- [manifest.json](manifest.json) records draft version, dialect, reviewed upstream source release, placeholder identity, and SHA-256 digests of the two supplied documents.
- [cases.json](cases.json) declares 32 resource fixtures by ID, resource schema, relative file path, expected validity, and exact error code for invalid cases.
- [fixtures/](fixtures/) contains their raw resource payloads.
- [scenarios.json](scenarios.json) declares 7 behavior scenarios with 56 ordered input/output expectations. Each scenario starts with fresh receipt stores. `restart` means JSON serialization/restoration of the receipt snapshot, not a running coordinator restart.

The JavaScript CLI is a reference runner for this corpus. Another language can implement the same schema/semantic rules and compare outputs with the same expectations. Native runtime tests additionally check contract integrity, precision, tenant scoping, path containment, and CLI exit behavior.

```sh
npm run conformance
node tools/conformance.mjs --json
npm test
```

An expected invalid fixture is a passing check when rejected with its documented code. The runner exits 1 for a mismatch and 2 for invalid command usage.

| Area | Covered locally | Requires production / interoperability work |
| --- | --- | --- |
| Resources | Required fields, unknown fields, formats, versions | Peer payload exchange |
| Contracts | Exact-byte digest, URI/$id match, schema validation, local resolution | Publication, constrained remote resolvers, compatibility registry |
| Progress | Null totals, units, half-up arithmetic, monotonic sequence | Multi-producer integration |
| Questions | Task/context correlation, typed answer, closure, inclusive deadline, fail-on-expiry | Live interruption/resumption |
| Lifecycle | Terminal immutability, required output and question gates | Complete A2A transport/lifecycle compliance |
| Reliability | Replay/conflict detection, stale sequence, scoped idempotency, snapshot restoration | Atomic durable storage, outbox, distributed acceptance, retry jitter |
| Swarm | Unique children, dependency existence/cycles/readiness, eligibility, parent validation, cancellation targets | Live scheduler, retries/timeouts, cancellation acknowledgments, contributor authentication |
| Security | Trusted scope required, producer mismatch, local schema containment, no network schema fetch | Tenant authorization, SSRF-safe artifact retrieval, MIME/size/retention/delegation policies |

The corpus is a minimum draft suite, not a certification program. It does not verify truth of model outputs, remote endpoint safety, end-to-end signatures, exactly-once delivery, or coordinator failover. New requirements should add portable positive and negative fixtures alongside the implementation.
