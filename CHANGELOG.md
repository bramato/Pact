# Changelog

## 0.2.0 — draft, 2026-10-09

- Separate mandatory Core and optional Swarm with repository-controlled identifiers.
- Define typed event payloads and whole-envelope JCS SHA-256, exact-byte replay, concurrent atomic answers, lifecycle/output gates and bounded delivery.
- Ship independent TypeScript and PHP SDKs plus a Laravel 12 routing adapter.
- Add authenticated deterministic task/question/artifact and three-contributor HTTP demos.
- Persist acceptance, questions, task state, provenance and exact outbox bytes in SQLite; verify SIGKILL recovery and lost-response reconciliation.
- Calculate scoped weighted progress across execution, aggregation and validation, retain retry regression and a separate high watermark.
- Add shared positive/negative vectors, a live endpoint CLI, broken-peer and fault tests, standalone package smoke tests and navigable documentation.

The original supplied proposal/progress schema and v0.1 corpus remain unchanged. This is a locally implemented draft; packages and specification identifiers await owner-authorized publication.

## 0.1.0 — Unreleased draft

- Import the supplied PACT proposal and progress schema unchanged.
- Define draft resource schemas and explicit wire conventions for an A2A 1.0 binding.
- Add local contract schemas, pinned digests, canonical message examples, and a swarm example.
- Add a portable JSON conformance corpus, offline validation CLI, semantic checks, and CI.
- Establish contribution, conduct, security, compatibility, and release documentation.

This entry describes repository development; no package or protocol release has been published.
