# Independent v0.2 implementations

[vectors.json](vectors.json) contains portable input, expected value or exact rejection code. TypeScript and PHP independently evaluate every case; their agreement alone is insufficient, so both are compared to the declared expectation. The lifecycle matrix covers all pairs of native states.

```sh
npm test
php conformance/v0.2/runner.php
npm run test:integration
```

These cases cover JCS Unicode ordering and numeric boundaries, exact schema digests, concurrent/atomic answers, subsecond deadlines, terminal/output gates, Core-only and optional Swarm negotiation, static DAG validation, weighted scope, retries, null progress and one final rounding. The integration suite uses actual HTTP, SQLite transactions, child effects, an operating-system SIGKILL, recovery and response-loss faults. The legacy [v0.1 corpus](../README.md) remains separately runnable with `npm run conformance`.

The live CLI requires the declared task contract and a locally pinned registry. Its reference suite checks the deterministic document-review peer. Other asynchronous tasks and A2A features need their own scenarios; this is not general A2A certification.
