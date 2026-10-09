# Durable reference binding

[Service](../reference/src/Service.php) handles A2A 1.0 `SendMessage`, `GetTask` and `CancelTask`; [Routes](../sdk/laravel/src/Routes.php) adapts it to Laravel's router. The Core SDK does not depend on Laravel or on this reference application.

## What commits together

SQLite `BEGIN IMMEDIATE` serializes acceptance. The task (including questions and progress), exact-byte command receipt, and outbox jobs commit in one transaction. Idempotency scope is tenant, caller, agent and acceptance key. Replay returns the original accepted response; a later `GetTask` retrieves current state. Changed bytes under the same key conflict.

The schema registry maps allowed URI identifiers to local files. The SDK checks path containment, size, `$id`, dialect and the pinned digest before independent data validation. It rejects external schema references. The adapter trusts server configuration for tenant, producer and delegation roles; public request metadata cannot grant those identities.

## Outbox and effects

The [Store](../reference/src/Store.php) persists tasks, command receipts, contributor effects, event receipts, observer projections and outbox jobs. The [Worker](../reference/src/Worker.php) leases work before HTTP dispatch. It retains the exact serialized command for every retry and records attempt count, result and failure. A replaced lease cannot acknowledge a result or fail the successor's work. The reference lease lasts six seconds; HTTP requests time out after five seconds.

A collaborator commits its task, receipt and effect ledger once. When its response is lost, a retry retrieves the existing accepted task. Delivery is at least once; the committed effect ledger establishes deduplicated effects in this reference binding, not universal exactly-once execution.

Connection errors, 408, 429 and 5xx retry with bounded exponential full jitter, five attempts, 100 ms base and 2 s cap. Other failures terminate delivery immediately. A required dispatch that exhausts retries fails the parent; exhausted optional work settles without blocking validated required output. Its delivery error remains explicitly unconfirmed and no remote task history is invented. Notification failure remains an audited outbox failure. The reference does not interpret Retry-After; the normative policy makes honoring a bounded value a SHOULD for adapters.

## Dependency work and progress

The plan is static within generation 1. `extract` has weight 1, `review` weight 2, `assemble` weight 1; optional audit work is displayed separately. Execution, aggregation and validation have weights 80:10:10. Exact rational arithmetic rounds once. Retry resets the current attempt while retaining `high_watermark` and snapshots. A settled optional failure is effort, not successful output.

Each accepted distinct child attempt has an A2A task ID, state, artifact ID, canonical artifact-data digest and independently derived verdict. The parent uses actual validated predecessor data and validates assembled output again. Cancellation intent is committed before dispatch; observed child terminal states are separate records. Previously attempted commands still reconcile after cancellation so a lost acknowledgment cannot hide an accepted child. Late observations retain the canceled parent state.

## Limits to preserve

This is a single coordinator reference service with SQLite and polling. It implements an all-required demo; portable SDK cases also validate quorum eligibility and scoped progress, but the demo does not execute a quorum scheduler. Streaming, distributed ownership transfer, credential rotation, configurable retention/resource budgets and nested swarm execution remain future adapter work. The local `/events` receiver demonstrates profile notification receipts; it is not an implementation of A2A push-notification configuration.

Use [the verification commands](quickstart.md) and [the delivery audit](delivery-plan.md) when reviewing changes. A fixture pass proves the declared profile behavior in this implementation, not the truth of document content.
