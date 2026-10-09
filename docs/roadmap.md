# Delivery roadmap

Status is based on the files and executable checks in this repository. Draft definitions are reviewable but remain open to revision.

| Phase | Delivered here | Remaining |
| --- | --- | --- |
| 0 — Governance and scope | Contribution, conduct, security, version policy; existing MIT license recorded | Name/domain review, permanent URI, private reporting contacts, license review |
| 1 — Core specification | Supplied proposal, resource schemas, wire conventions, vocabulary, A2A examples, fixtures | Public review and freezing a normative draft |
| 2 — Two-agent MVP | Offline input/output/answer validation, lifecycle and idempotency models | Two live authenticated agents, durable acceptance, streaming/polling, transport adapters |
| 3 — Swarm MVP | DAG rules, aggregation checks, provenance example, failure/cancellation scenarios | Live coordinator, durable child execution, retries, timeouts, remote cancellation acknowledgments |
| 4 — Interoperability | SDK-independent JSON corpus, local conformance CLI, CI | TypeScript and PHP/Laravel SDKs, cross-language tests, peer interoperability |
| 5 — Community release | Release checklist | Permanent identifiers, v0.1.0 tag, specification/package publication, live demo, ecosystem feedback |

## Open design work

Coordinator failover and ownership transfer; contributor artifact trust; publication/signing/discovery of contracts; concurrent question bundling; enforceable resource budgets; nested swarm depth and cancellation guarantees belong to the proposed v0.2 work.

The release checklist is to resolve permanent identity and reporting channels, review license compatibility, freeze schemas/contracts and byte digests, pass checks and live interoperability tests, document production limits, then authorize publication. A release must not imply official A2A endorsement.
