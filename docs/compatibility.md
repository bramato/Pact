# Compatibility

| Component | Target | Evidence / limit |
| --- | --- | --- |
| PACT Core / Swarm | 0.2.0 draft | Normative text, schemas, independent PHP/TypeScript vectors |
| Archived proposal | 0.1.0 | Original documents preserved byte-for-byte; legacy corpus retained |
| A2A JSON-RPC | 1.0 | Models reviewed at upstream v1.0.1; authenticated live task/question/artifact exchange |
| JSON Schema | Draft 2020-12 | Ajv and Opis, formats asserted, pinned local resolver |
| TypeScript | Node.js 22+ | Typed SDK, HTTP client, compiled standalone archive |
| PHP | 8.2+ | SDK platform requirement, Composer dependencies; local tests use PHP 8.4 |
| Laravel | Illuminate 12 | Actual HTTP routes; reference lock resolved against PHP 8.2 |
| Durability | SQLite, one coordinator | Real SIGKILL/restart, exact-byte reconciliation, unique committed child effects |
| Streaming / other A2A bindings | Unverified | Polling JSON-RPC reference only |

The wire header is `A2A-Version: 1.0`; `v1.0.1` identifies the upstream model source. Use `SendMessage`, `ROLE_USER` / `ROLE_AGENT`, `TASK_STATE_*`, structured Part `data`, `GetTask` and `CancelTask`. Do not combine this binding with older `message/send` or `kind` examples.

Core is mandatory in the reference card. Swarm is optional and activated separately. Missing required support fails explicitly; any downgrade needs caller authorization. v0.2 identifiers are backed by this owner's repository and await publication when the owner pushes/releases the draft. Archived `example.org` IDs are not deployment identities.

Tests cover the declared PACT binding and deterministic peer. They do not certify all A2A features, output truth, distributed leader failover or production deployment policy. See [implementation limits](implementation.md).

Sources: [A2A models](https://github.com/a2aproject/A2A/blob/v1.0.1/specification/a2a.proto), [A2A specification](https://github.com/a2aproject/A2A/blob/v1.0.1/docs/specification.md), [extensions](https://github.com/a2aproject/A2A/blob/v1.0.1/docs/topics/extensions.md), [JSON Schema](https://json-schema.org/draft/2020-12), [RFC 8785](https://www.rfc-editor.org/rfc/rfc8785), [Laravel 12 routing](https://laravel.com/docs/12.x/routing).
