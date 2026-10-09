# Local tooling

Install the pinned dependencies with `npm ci --ignore-scripts` using Node.js 22 or newer. Validation is offline after installation. No environment variables, application server, model credentials, or A2A SDK are required.

| Command | Result |
| --- | --- |
| `npm run check` | Runtime tests, portable corpus, integrity/examples/local-link/asset checks |
| `npm test` | Native Node.js runtime tests |
| `npm run conformance` | Portable resources and behavior scenarios |
| `node tools/conformance.mjs --json` | JSON result with per-case expected/actual values |
| `npm run validate -- progress examples/progress.json` | One resource's shape and applicable semantics |
| `node tools/validate.mjs progress examples/progress.json --json` | Structured success/error result |
| `node tools/validate.mjs --help` | Supported resource names and usage |

The validators return exit 0 on success, 1 on rejected input or mismatched corpus expectation, and 2 on invalid command usage. JSON errors include `code`, `message`, and optional schema `details`. Library functions in [validation.mjs](../tools/lib/validation.mjs) and [reliability.mjs](../tools/lib/reliability.mjs) are local conformance models, not a published SDK or transport API.

## Semantic boundaries

`validateResource` validates a resource's shape; progress, event, swarm, and nested metadata also receive stateless semantic checks. It cannot establish stateful history, a question's answer, or a contract's data from one resource alone.

`validateContractData` resolves a descriptor using an explicit local registry, verifies exact bytes/dialect/$id, and validates data. Contract schemas can use internal fragment references; undeclared external references fail compilation without network access. `validateAnswer` also needs trusted task/context state and an explicit clock. `expireQuestion` implements the documented required-question failure policy.

`negotiate` checks the advertised PACT version and exact contract descriptors after extension activation has been confirmed by the adapter. It allows fallback only when `allowDowngrade` is explicitly true. `assertLifecycle` checks terminal immutability and output/question gates; it is not a complete A2A lifecycle implementation.

`EventJournal` accepts exact serialized event bytes under authenticated tenant/producer context. It records duplicates and stale events and rejects conflicting bytes. `IdempotencyStore` binds accepted command bytes to a task under tenant/caller/key context. Their snapshots illustrate receipt restoration. They do not provide persistent storage, atomic multi-process transactions, crash-safe task creation, or an outbox.

`evaluateSwarm` evaluates documented DAG and aggregation rules against local validation verdicts. `cancellationTargets` returns outstanding child IDs without changing their states. Adapters must derive verdicts from actual outputs and wait for remote cancellation acknowledgment.

## Error families

| Prefix / code | Meaning |
| --- | --- |
| `SCHEMA_*`, `CONTRACT_*` | Invalid shape, unsupported descriptor, URI/digest/dialect mismatch, or invalid contract data |
| `INTEGER_PRECISION` | Value exceeds the JavaScript harness's safe integer range |
| `PROGRESS_*`, `EVENT_SEQUENCE` | Arithmetic or sequence mismatch |
| `QUESTION_*` | Wrong correlation/context, closed/expired question, invalid clock, or completion gate |
| `TASK_*`, `OUTPUT_REQUIRED` | Unknown/immutable state or missing validated output |
| `DAG_*`, `QUORUM_*`, `REQUIRED_EMPTY`, `PROVENANCE_*` | Invalid graph, eligibility, dependency readiness, or audit records |
| `EVENT_CONFLICT`, `COMMAND_CONFLICT` | Previously accepted ID/key reused with different bytes |
| `SECURITY_SCOPE`, `PRODUCER_IDENTITY` | Missing trusted scope or peer/producer mismatch |
| `PACT_*` | Extension activation or requested version unsupported |

Unexpected local I/O and JSON parse failures are reported using their native error code/name. Production adapters should map PACT errors into the chosen A2A binding's standard error envelope, with profile details in namespaced metadata where supported.
