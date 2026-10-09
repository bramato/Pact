# PACT — Protocol for Agent Coordination and Tasks

**Draft specification v0.1.0 · 2026-10-09 · Proposal (not an A2A official extension)**

## 0. Status and intent

PACT is a proposed, language- and platform-neutral **A2A extension/profile**, not a replacement transport or an independently certified standard. It extends A2A with: strict interoperable input/output contracts, structured questions/answers, progress semantics, hierarchical swarm coordination, and reliable event processing. Terms **MUST**, **SHOULD**, **MAY** have their RFC 2119 meaning. Where A2A and PACT conflict, base A2A requirements take precedence and the extension MUST be revised.

**Compatibility rule:** an A2A agent without PACT remains a valid A2A peer. A PACT initiator MUST negotiate PACT support before sending PACT-constrained requests; it MUST fail explicitly or downgrade *only with caller authorization*. Extension discovery uses A2A Agent Card `capabilities.extensions`, URI negotiation and standard A2A metadata/Part/Artifact extension points. The canonical extension URI is **TBD** until a permanent project domain is controlled; `https://example.org/pact/v1` is a placeholder and MUST NOT be published as production identity.

## 1. Actors and directions

- **A → B:** originator dispatches a task to an executor over A2A.
- **B → A:** executor reports status, asks structured questions, and provides artifacts over A2A.
- **A → swarm:** originator dispatches a parent task to one swarm coordinator (an A2A agent).
- **Swarm → A:** coordinator returns progress and aggregate artifacts; members can remain private internally.
- A and B are *roles per task*, not permanently assigned identities. Every task has exactly one accountable owner/coordinator, even if execution is delegated.

## 2. Core resources

PACT metadata is a namespaced object under A2A message/task/artifact metadata using the negotiated PACT extension URI key. PACT objects MUST NOT add nonstandard required top-level fields to native A2A objects.

**Identifiers:** `task_id`, `parent_task_id`, `question_id`, `event_id`, `contract_id`, `coordinator_id`, `member_id`, `correlation_id`, `causation_id` are opaque strings, scoped to a tenant/security boundary. `contextId` and `taskId` from A2A remain authoritative identifiers for the A2A task; PACT correlation and hierarchy are additional metadata.

**Contract:** immutable `contract_id`, semantic version, a canonical JSON Schema 2020-12 URI for `input`, `output`, and optionally `question_answer`, content digest (sha256). A receiver MUST resolve and verify the exact declared schema before validation; no silent schema replacement. Unknown fields MUST be rejected if a schema sets `additionalProperties: false`.

**Question:** `question_id`, `task_id`, `prompt`, `answer_schema`, `required`, `expires_at` (nullable), `status` (`open|answered|expired|withdrawn`), `response_to` on answers. Open required questions transition the A2A task to `INPUT_REQUIRED` when execution cannot proceed; answers are new A2A messages associated to the same task/context and question identifier. Deadline expiry MUST have a deterministic documented policy (fail, cancel, or explicitly default).

**Progress:** `sequence`, `stage`, `completed_units`, `total_units`, `percentage` (nullable decimal 0..100), `updated_at`. When `total_units>0`, percentage MUST equal `round(100*completed_units/total_units,2)`; when total is unknown, percentage MUST be null. For a given event producer and task, sequence MUST increase monotonically. Progress MUST NOT be interpreted as a task terminal state. A2A TaskStatus remains authoritative for lifecycle.

**Event:** `event_id`, `task_id`, `sequence`, `type`, `occurred_at`, `correlation_id`, `causation_id`, `producer_id`, `payload`. Consumers MUST deduplicate by the tuple `(tenant, producer_id, event_id)`; the same event ID with different bytes MUST be rejected as a conflict. Out-of-order events MUST NOT overwrite a later accepted state. This profile does not promise exactly-once network delivery.

## 3. State model

**Authoritative A2A task states:** `SUBMITTED`, `WORKING`, `INPUT_REQUIRED`, `AUTH_REQUIRED`, `COMPLETED`, `FAILED`, `CANCELED`, `REJECTED`; `UNSPECIFIED` is not successful or terminal. Terminal states: completed, failed, canceled, rejected. Interrupted states: input-required, auth-required. PACT MUST NOT overload A2A status values with additional states. Optional PACT phases (e.g. planning, executing, aggregating, validating) are metadata only. Terminal state transitions are immutable. Completion implies validated required output artifacts; a reported 100% alone does not.

**Transport vs work:** HTTP 2xx acknowledges the relevant HTTP operation; it is NOT proof of completed work. A2A task status determines acceptance/progress/outcome. Duplicate submissions require a caller-supplied idempotency key handled atomically in the adapter; deduplication across independent servers requires shared storage or coordination.

## 4. Swarm DAG

Coordinator is an A2A endpoint and owns a parent task. Each child has `child_id`, `parent_task_id`, `assignee_id`, `depends_on[]`, `required`, `weight`, `contract_id`, `state` and `result_artifact_ids[]`. Dependencies MUST be acyclic. No child may begin before its required predecessor conditions are met. The coordinator MUST keep an audit trail of individual contributor results and validation verdicts.

**Aggregation policies (v0.1):**
1. `all_required`: all required child tasks complete successfully with valid outputs; optional children do not gate success.
2. `quorum`: at least `min_success` *eligible* child tasks complete successfully with valid outputs. The parent result is then assembled and checked against the parent output contract.

No policy claims a parent completed while its required aggregation or parent validation remains unfinished. Child failures, retries and timeouts are retained as provenance. Cancellation propagates to outstanding children on a best-effort basis and MUST NOT imply remote cancellation was completed until acknowledged/observed. `first_valid`, weighted consensus and dynamic membership are deferred.

## 5. Reliability and security

- Transport: A2A bindings over HTTPS/TLS, using the A2A Agent Card's advertised authentication mechanisms (such as OAuth2). Identity, tenant, authorized delegation scope and per-task access checks MUST be validated server-side.
- All external input and artifact URIs MUST be checked against SSRF, size, MIME, and execution policies. Schemas validate shape, not the safety/truth of content.
- Producers SHOULD provide event hashes and provenance; artifact SHA-256 integrity SHOULD be checked. End-to-end signatures are optional in v0.1 and require a separately specified canonicalization/signature profile.
- Consumers MUST treat retries and notifications as at-least-once and implement idempotent processing. Retry policy MUST include upper bounds, exponential backoff with jitter and explicit handling of nonretryable failures.
- Durable event store SHOULD persist command acceptance and outbound notifications atomically (transactional outbox). Progress history, questions, and child status SHOULD survive coordinator restarts.
- Contracts and artifact data MUST respect per-tenant isolation and retention policies. Never include secrets in publicly readable Agent Cards.

## 6. Minimum interoperable flow

1. A fetches B's A2A Agent Card and verifies the advertised PACT extension and the agent's authentication policy.
2. A and B agree on a specific PACT version/contract digest; unsupported required versions are rejected.
3. A sends a standard A2A message containing a validated `DataPart` and PACT metadata (`contract_id`, idempotency key and correlation ID).
4. B validates authorization, negotiated contract, schema and idempotency, and creates/continues an A2A Task.
5. B emits task status and progress; when input is needed, B emits an identified question and A2A `INPUT_REQUIRED` status.
6. A answers the matching question through A2A; B checks the answer schema and resumes.
7. B returns artifacts conforming to the declared output contract and marks A2A `COMPLETED`, or returns a terminal failure with an error code.
8. For a swarm, coordinator executes children according to the DAG and aggregation policy; provenance accompanies the final aggregate artifact.

## 7. Example PACT metadata (illustrative — not a full A2A request)

```json
{
  "https://example.org/pact/v1": {
    "version": "0.1.0",
    "correlation_id": "corr_7a8c",
    "contract": {
      "id": "document-review",
      "version": "1.0.0",
      "schema_uri": "https://example.org/contracts/document-review/1.0.0/output.schema.json",
      "sha256": "<64-character verified hex digest>"
    },
    "progress": {
      "sequence": 4,
      "stage": "extracting",
      "completed_units": 35,
      "total_units": 100,
      "percentage": 35.0,
      "updated_at": "2026-10-09T08:30:00Z"
    }
  }
}
```

The `example.org` URIs and placeholder hash above are **not** deployable identifiers.

## 8. Conformance suites and reference implementations

- **Spec fixtures:** valid/invalid payloads; A2A native envelope with PACT namespaced metadata; contract digest and URI checks.
- **Lifecycle tests:** task state authority; invalid output cannot complete; question correlation, timeout and late answers; cancellation propagation.
- **Reliability tests:** duplicate delivery, idempotent command receipt, out-of-order sequence, restart/replay, conflicting payload for same event ID.
- **Swarm tests:** DAG cycle rejection; all-required success/failure; quorum boundaries; one failed/one unanswered/one completed; aggregate validation.
- **Security tests:** cross-tenant references, unauthorized delegation, malicious artifact URL, schema poisoning, replay.

Implement a minimal language-neutral test harness. Candidate reference SDKs: TypeScript and PHP/Laravel initially, Python subsequently; conformance MUST be independent of any SDK. Suggested license: Apache-2.0, after repository policy and third-party compatibility review.

## 9. Delivery roadmap

**Phase 0 — governance and scope:** verify name/domain availability, choose permanent extension URI, license, CODE_OF_CONDUCT/CONTRIBUTING, versioning policy, compatibility matrix.

**Phase 1 — core specification:** formal vocabulary, JSON Schemas for contracts/questions/progress/events, canonical A2A examples, test fixtures.

**Phase 2 — two-agent MVP:** discovery, negotiation, send/task/status/answer/results, schema validation, idempotency and failure handling.

**Phase 3 — swarm MVP:** coordinator, DAG validation, `all_required`/`quorum`, per-child provenance, failure/timeout/cancellation behavior.

**Phase 4 — interoperability:** conformance CLI, TS/PHP reference SDKs, cross-language integration tests, README and diagrams, public technical review.

**Phase 5 — community release:** tag `v0.1.0`, publish specification and SDK packages, demo swarm, collect extension feedback from A2A ecosystem. No implication of official A2A endorsement.

## 10. Explicit non-goals for v0.1

Inventing a new HTTP transport; embedding hidden reasoning or chain-of-thought in messages; model selection/token accounting; fully peer-to-peer leaderless consensus; exactly-once network delivery; billing and marketplace discovery; trusted truth verification of model outputs; unlimited dynamic swarm membership.

## 11. Open design decisions for v0.2

- Full policy for coordinator failover and durable ownership transfer.
- Trust and attestation level for contributor artifacts.
- Contract publication/signing/discovery and compatibility across semantic versions.
- Whether concurrent independent questions should be bundled as one A2A interruption.
- Resource budgets: max tokens, money, time and agent count with enforceable delegation rules.
- Swarm nested depth and cancellation guarantees.

## References

- A2A extension guidance: https://github.com/a2aproject/A2A/blob/main/docs/topics/extensions.md
- A2A protocol specification: https://github.com/a2aproject/A2A/blob/main/docs/specification.md
- A2A protobuf models: https://github.com/a2aproject/A2A/blob/main/specification/a2a.proto
- JSON Schema 2020-12: https://json-schema.org/draft/2020-12
