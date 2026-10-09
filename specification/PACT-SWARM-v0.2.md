# PACT Swarm v0.2.0

**Normative optional extension · Draft · 2026-10-09**

The extension URI is `https://github.com/bramato/Pact/blob/main/specification/PACT-SWARM-v0.2.md`. Swarm depends on [Core v0.2](PACT-CORE-v0.2.md), which MUST also be activated. A Core-only peer remains interoperable for single tasks. A client MUST NOT send Swarm-constrained work unless both extensions are advertised and activated.

## 1. Ownership and graph

One authenticated coordinator owns a parent A2A task. It validates a static DAG of uniquely identified logical children before dispatch. Each child records its parent, assignee, contract, required flag, positive integer weight, dependency IDs, current attempt and authoritative A2A task/state. Dependencies MUST exist and be acyclic. A logical child starts only after every referenced predecessor has a completed and independently validated output. Rejected/canceled descendants may be terminal without starting.

Each attempt is a distinct A2A task. A failed terminal attempt never resumes. A bounded retry creates a new task, increments attempt, and retains the failed task, artifacts, error and validation verdict. Acceptance keys and exact outbound command bytes are persisted before dispatch. A lost response is retried under the same acceptance key; it cannot create a second effect. Unauthorized delegation and cross-tenant child references are rejected server-side.

## 2. Aggregation and cancellation

`all_required` needs at least one required child and successful validated outputs from every required child. Optional failures do not gate it. `quorum` explicitly fixes unique `eligible_child_ids` and a positive `min_success` no larger than that set. Only valid completed outputs from those IDs count. Weights do not change either success policy. Parent success additionally requires assembly and independent validation against the parent output contract.

Each contributor has provenance with logical ID, attempt task IDs, artifacts, exact artifact-data digests and independent validation verdicts. Failures, timeouts and retries remain recorded. Required failure after retry exhaustion fails the parent. An input-required child keeps a required parent interrupted while progress and contributor history remain available.

Native parent cancellation atomically records cancellation intent and an outbox request for every outstanding child. Remote child state is unchanged until acknowledged/observed. Retries use Core's bounded delivery policy; timeout/exhaustion means cancellation is unconfirmed, never acknowledged. Late observations remain audited without reopening the terminal parent. Peers retain enough context to reconcile an accepted command after coordinator restart.

## 3. Weighted progress

Weights and stage plans are fixed before execution and cannot change within a progress generation. A changed plan needs an explicit new generation retaining the previous snapshot. Each stage and each child has a positive integer weight. `scope` is `required` for all-required or `eligible` for quorum; other children remain visible as separate optional work and do not dilute the scoped execution progress.

For each included logical child, use its **current attempt** percentage; a retry may regress this percentage. A terminal settled logical child contributes 100% measured effort whether it succeeded, failed or was canceled; a failed attempt awaiting retry is not settled. A child with an unknown total makes scoped execution percentage null unless it is settled. Stage changes use a fixed weighted stage plan rather than resetting total task progress.

Execution percentage is the weighted mean of scoped child percentages. Parent percentage is the weighted mean of execution, aggregation and validation stages (reference default weights 80:10:10). Compute the full rational expression before one final round-half-up to two decimals; do not round each contribution early. If an included stage has unknown progress, parent percentage is null. Non-execution stage percentages are reported from measured work, not inferred from a lifecycle flag.

Every snapshot records `generation`, increasing `sequence`, `percentage`, `high_watermark`, `execution_percentage`, stage weights/percentages and contributor attempt/state/progress. `high_watermark` is the maximum known parent percentage seen in that generation; it never replaces the current percentage. Unknown current percentage preserves the last known maximum. The maximum resets only in a new documented plan generation.

Quorum may establish valid completion while additional eligible work is outstanding; its execution percentage may therefore be below 100%. A2A completion records result validity, while progress records the declared work scope. A settled optional failure may show 100% effort but never counts as a valid quorum output. Parent output and aggregation gates remain mandatory regardless of either progress value.

## 4. Recovery

The reference coordinator persists parent/child tasks, open questions, command receipts, progress generation/sequence, attempt histories and outbox records. Acceptance and state changes share database transactions. Workers use expiring leases and recover accepted commands with the same bytes/keys. A coordinator process may be killed after a remote effect commits but before local acknowledgment; recovery MUST reconcile/replay the existing command and MUST NOT repeat that committed effect.

Conformance requires both shared arithmetic/graph vectors and a real process-kill/restart demonstration against the same durable database. Snapshot serialization alone is insufficient recovery evidence. Distributed leader failover and ownership transfer beyond one durable coordinator remain outside this version.
