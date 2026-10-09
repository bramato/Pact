# Executable draft wire conventions

These conventions resolve gaps in the supplied proposal for the repository's fixtures. They are proposed v0.1 decisions, open to technical review. The [source proposal](PACT-SPEC-v0.1.md) and supplied progress schema remain unchanged. Base A2A requirements take precedence.

## Identity and negotiation

Examples use the placeholder extension URI `https://example.org/pact/v1`. All example endpoints and contract IDs under `example.org` are illustrative and must be replaced before deployment. No URI ownership or official A2A extension registration is asserted.

An Agent Card declares `capabilities.extensions[]` with this URI and `params` containing `versions` and `contracts`. `contracts` contains full contract descriptors. Each HTTP request asks for activation with `A2A-Extensions` and declares `A2A-Version: 1.0`. The response should echo active extensions according to A2A. A PACT caller must verify support and exact contract ID/version/digests before submission. Missing support is an explicit failure unless the caller authorizes downgrade. A message's `extensions` member labels its content; it does not replace activation negotiation.

## Contracts and data

A `contract` contains `contract_id`, `version`, and `input`/`output` schema descriptors, with optional `question_answer`. Each descriptor has `schema_uri` and lowercase hexadecimal `sha256`. This normalizes the prose contract definition; the source's single-schema metadata snippet is illustrative rather than the final contract shape.

The digest is SHA-256 of the exact UTF-8 file bytes, including whitespace and final newline. A receiver resolves the URI through an allowlisted registry, checks the digest, and validates with JSON Schema Draft 2020-12 including formats. A schema's `$id` must equal the declared URI. Neither a substituted schema nor a newly calculated digest is accepted silently. The local harness permits only URI mappings to files inside the declared registry's directory tree; it never fetches remote references.

Request input and artifact output are in a native A2A Part's `data`. PACT metadata lives only under the extension URI on native objects. PACT metadata uses `version` and optional `contract`, `idempotency_key`, `correlation_id`, `parent_task_id`, `coordinator_id`, `progress`, `question`, `answer`, `event`, `swarm`, `provenance`, and `error`. Opaque IDs are nonempty strings. Unknown PACT resource fields are rejected by strict resource schemas; native A2A forward-compatibility behavior remains A2A's responsibility.

## Questions and progress

A question's `answer_schema` is a schema descriptor. The answer resource has `response_to`, `task_id`, and `value`. Its value is also the answer message's structured Part data. Task and context identifiers must match the original question. A required open question interrupts work when it blocks execution. A question is closed once answered, expired, or withdrawn; late or repeated answers are rejected.

For these v0.1 fixtures, expiration at `now >= expires_at` rejects the answer; an expired required blocking question fails the task. Optional expired questions do not gate completion. This fixed failure policy avoids an undocumented default. Defaults and configurable expiration policies are deferred.

`total_units` is a positive integer or null, as in the supplied schema. Unknown totals require null percentage. Completed units cannot exceed a known total. Percentage uses exact integer arithmetic with round-half-up to two decimal places; `1 / 32` is `3.13`. Runtime counters and sequences in the JavaScript harness are limited to safe integers; this is a tooling precision limit, not a new wire constraint. Sequence strictly increases per authenticated producer/task pair. Stage changes cannot reset sequence.

## Events and acceptance

Event sequence ordering is per tenant, producer, and task. Deduplication is per tenant, producer, and event ID, regardless of task. An exact byte replay is duplicate; reusing an ID with any changed bytes is a conflict, even for semantically equivalent JSON with different whitespace. The event store records valid stale events for deduplication/audit but never applies them over a later state. Event and nested progress sequences match when both are present. Terminal task states are immutable, and completion requires validated required output artifacts.

An idempotency receipt is scoped by authenticated tenant, caller, and caller-supplied key. It binds the exact accepted command bytes to the created task. Reuse with different bytes is a conflict. A production adapter must atomically persist acceptance and outbound work; the in-memory fixture model only demonstrates the rule. Event receipts and idempotency receipts are different stores. Tenant identity is trusted adapter context, not a caller-controlled PACT metadata field.

## Swarm evaluation

`children` is a static DAG with unique `child_id` values, existing dependencies, no self-reference/cycles, and matching `parent_task_id`. Here every `depends_on` reference gates execution until that predecessor completes with valid outputs. Child states may be terminal without ever starting when rejected or canceled. Child output validation is recorded in `provenance.contributors`, with artifact IDs and attempt history; a child-reported completion alone does not qualify.

`all_required` requires at least one required child and all required children to have valid completed outputs. Optional children do not gate that policy. `quorum` explicitly names `eligible_child_ids` and `min_success`; only named children with valid completed outputs count. Weight is recorded but not used by either v0.1 policy. Missing, duplicate, or unknown eligibility IDs are rejected. The parent can complete only after both aggregation and parent output validation finish.

Cancellation requests target every outstanding child on a best-effort basis. Requesting cancellation does not mark a child canceled; its observed state stays unchanged until the remote peer acknowledges or reports it. Attempts, failures, and validation verdicts remain provenance. Durable execution, ownership transfer, authenticated delegation, artifact fetching, retries, and remote cancellation are adapter work outside this harness.
