# PACT Core v0.2.0

**Normative independent A2A profile · Draft · 2026-10-09**

The key words MUST, MUST NOT, SHOULD and MAY are normative. Base A2A 1.0 requirements take precedence. This document supersedes the executable v0.1 conventions for peers explicitly negotiating v0.2; it does not alter the archived source proposal or confer official A2A status.

## 1. Identity and capabilities

The Core extension URI is `https://github.com/bramato/Pact/blob/main/specification/PACT-CORE-v0.2.md`. It is a project-controlled identifier backed by this repository, not an A2A-owned namespace. Publication of the document is a repository release operation. Implementations MUST use this exact identifier for v0.2 and MUST NOT deploy the archived `example.org` identifier.

An Agent Card declares Core in `capabilities.extensions`. Its `params` object contains `versions`, `contracts`, and `features`. Core requires `contracts`, `questions`, `events`, and `progress`; Swarm is a separately negotiated optional extension. Versions are exact semantic versions; unsupported required versions/contracts fail explicitly. A caller MAY downgrade only with explicit caller authorization. Core has no dependencies other than A2A. Swarm depends on Core.

HTTP requests MUST send `A2A-Version: 1.0` and request activation through `A2A-Extensions`. The reference binding MUST echo the activated extension URIs; Core callers verify activation before interpreting constrained results. Native Message/Artifact `extensions` label contributed content and do not negotiate activation. A2A 1.0 JSON uses `SendMessage`, `GetTask`, `CancelTask`, `ROLE_*`, `TASK_STATE_*`, and structured Part `data`.

## 2. Contracts and schemas

A contract has immutable `contract_id`, semantic `version`, and `input`/`output` descriptors, plus optional `question_answer`. A descriptor has absolute `schema_uri` and lowercase hexadecimal `sha256`. The digest covers the exact schema UTF-8 bytes, including the final newline. Resolution MUST be allowlisted and size bounded; `$id` MUST match the declared URI. Validation uses Draft 2020-12 with formats asserted. A changed schema requires a new descriptor; receivers MUST NOT substitute schemas or recompute an expected digest silently.

The reference resolver uses local registry mappings and never retrieves arbitrary URLs. Internal JSON Schema fragment references are supported. External references must be separately registered and integrity pinned by a future resolution profile; they are rejected by this version's SDK resolvers. Schemas enforce shape, not content truth or authorization.

Input and output values are native A2A structured Part data. Core metadata exists only at `metadata[CORE_URI]` on native objects. Its version is `0.2.0`; the resource schemas under `0.2/schemas/` define permitted fields. Authentication and tenant context are trusted adapter state, never public metadata credentials. Identifiers inside resources MUST match authoritative surrounding A2A task/context references.

## 3. Questions and concurrent answers

A question records `question_id`, `task_id`, `prompt`, `answer_schema`, `required`, nullable `expires_at`, and `status`. IDs are unique within a task. An answer records `response_to`, `task_id`, and `value`. It is carried by a new user message addressed to the same task/context. The answer value is checked against the exact pinned schema before any state mutation.

Core metadata can contain `questions[]` and `answers[]`. At most one open record exists per question ID. Distinct concurrent questions are allowed. A batch MUST have distinct `response_to` IDs and MUST validate atomically; if one answer is invalid, none is committed. A repeated transport command returns its receipt, while a new command attempting to answer a closed question is rejected. Required open questions move a blocked task to `INPUT_REQUIRED`; the task resumes only after every blocking required question is answered or explicitly withdrawn. Optional unanswered questions do not block completion.

At `now >= expires_at`, an open question becomes expired. An expired required blocking question fails its task; optional expiration does not gate completion. A closed, withdrawn or expired question rejects late answers. Withdrawal is an audited coordinator decision releasing that question's gate. Profile timestamps use at most three fractional second digits. Deadlines are UTC RFC 3339 timestamps; all native A2A timestamps use `Z`. Expiration checks and answer acceptance MUST be in the same transaction as the task update, using the receiver's clock.

## 4. Lifecycle and output

The native A2A state is authoritative. Repeated observation of the same state is allowed; a terminal state cannot change. `UNSPECIFIED` establishes neither acceptance nor success. The following Core profile transitions are permitted; all others fail:

| From | New state |
| --- | --- |
| UNSPECIFIED | SUBMITTED, REJECTED |
| SUBMITTED | WORKING, INPUT_REQUIRED, AUTH_REQUIRED, FAILED, CANCELED, REJECTED |
| WORKING | INPUT_REQUIRED, AUTH_REQUIRED, COMPLETED, FAILED, CANCELED, REJECTED |
| INPUT_REQUIRED | WORKING, AUTH_REQUIRED, FAILED, CANCELED, REJECTED |
| AUTH_REQUIRED | WORKING, INPUT_REQUIRED, FAILED, CANCELED, REJECTED |
| COMPLETED, FAILED, CANCELED, REJECTED | No different state |

Completion requires independently validated required output artifacts and no unresolved required question. In Swarm it additionally requires successful aggregation and parent output validation. A producer's validation flag, HTTP 2xx, or 100% progress is insufficient evidence by itself. Consumers MUST derive validation verdicts from actual artifact data.

## 5. Events and digest coverage

Each event has `event_id`, `task_id`, `producer_id`, nonnegative safe-integer `sequence`, `type`, UTC `occurred_at`, `correlation_id`, nullable `causation_id`, typed `payload`, and `sha256`. The digest is SHA-256 of UTF-8 **RFC 8785 JCS canonicalization of the complete event with only its top-level `sha256` member removed**. It binds identities, sequence, type, time, causal references and payload; it excludes only the digest itself. Duplicate JSON object members, invalid Unicode, nonfinite numbers, and integers outside ±9007199254740991 are rejected. No Unicode normalization is performed. This integrity rule is not a signature or proof of producer identity.

The event catalog is closed for this version:

| Type | Payload |
| --- | --- |
| `task.status` | Native `state`, with optional Core error |
| `task.progress` | Core progress resource |
| `question.opened`, `question.closed` | Full question record, with status consistent with event type |
| `artifact.validated` | `artifact_id`, `schema_uri`, artifact data digest (`SHA-256(JCS(data))`), verdict (`valid` / `invalid`) |
| `child.status` | Logical `child_id`, actual `task_id`, positive `attempt`, native state |
| `swarm.progress` | Swarm progress resource; Swarm activation required |
| `cancellation.requested` | Unique nonempty `child_ids` |
| `cancellation.observed` | Child ID, actual task ID, observed terminal state |

Consumers verify the event digest and payload before applying a new event. They deduplicate by authenticated `(tenant, producer_id, event_id)`. The exact accepted event bytes are retained: a replay with changed bytes is a conflict, including semantically equivalent JSON serialized differently. Event integrity canonicalization and byte-level replay identity serve different purposes. Producers MUST persist serialized notification bytes and reuse them for retries. Per `(tenant, producer, task)` sequence ordering prevents older/equal sequences overwriting later state. Valid stale events remain audited. An event referring to a terminal task cannot reverse its lifecycle.

## 6. Acceptance, delivery and errors

An idempotency key is scoped by authenticated tenant/caller and binds the exact command bytes to its accepted task. Receipt, task creation/update and pending outbound notifications MUST be committed atomically. Duplicate acceptance returns the original task; different bytes under an accepted key conflict. Concurrent independent servers require shared coordination/storage.

Notifications are delivered at least once. A transactional outbox retains their exact bytes, attempt count and next retry time. Retry only connection failures, HTTP 408/429/5xx and explicitly retryable remote errors. Use exponential full jitter, bounded by advertised policy (`max_attempts`, `base_delay_ms`, `max_delay_ms`); honor bounded Retry-After. Nonretryable failures are recorded immediately. Exhaustion is an audited delivery failure and never becomes task success. A consumer acknowledges only after receipt/state transaction commits. A timeout after commit may cause replay; deduplication handles it.

Core errors have `code`, safe human-readable `message`, boolean `retryable`, and optional bounded `details`. A2A's binding-level error envelope remains authoritative: invalid profile input maps to InvalidParams (`-32602`); missing task to TaskNotFound (`-32001`); immutable/noncancelable task to its native error; unsupported activation/version to A2A's supported errors. Profile detail is namespaced under Core URI inside error data. Authentication/authorization failure uses HTTP 401/403 without leaking another tenant's resource existence. Unexpected implementation errors must not expose stack traces or secrets.

Cancellation of the current task uses native `CancelTask`. Task cancellation does not assert that remote contributors stopped. Swarm records each request and separately observes acknowledgments/terminal states. A canceled task never resumes; a retry is a new A2A task with retained provenance.

## 7. Progress and conformance

Progress has `sequence`, `stage`, `completed_units`, nullable positive `total_units`, nullable `percentage`, and UTC `updated_at`. Known totals require `completed_units <= total_units` and round-half-up percentage to two decimals; unknown totals require null percentage. Counters are safe integers. Sequence increases across stages and attempts; percentage may regress when genuinely recomputed for a new attempt. Historical maxima may be reported separately, never as current work.

Normative resource schemas and portable behavioral vectors are authoritative alongside this prose. Both independent SDKs must consume the same vectors. Live conformance additionally checks discovery/activation, contracts, task/question/results, replay and terminal behavior. A passing suite applies only to the tested binding, profile, endpoint and declared capabilities; it is not certification of the whole A2A protocol or output truth.

References: [A2A v1.0.1 models](https://github.com/a2aproject/A2A/blob/v1.0.1/specification/a2a.proto), [extension guidance](https://github.com/a2aproject/A2A/blob/v1.0.1/docs/topics/extensions.md), [JSON Schema 2020-12](https://json-schema.org/draft/2020-12), [RFC 8785](https://www.rfc-editor.org/rfc/rfc8785).
