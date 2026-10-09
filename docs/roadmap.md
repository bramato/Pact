# Delivery roadmap

The six implementation improvements are tracked with direct evidence in [the delivery audit](delivery-plan.md).

| Area | Delivered in draft v0.2 | Next work |
| --- | --- | --- |
| Specification | Core/Swarm split, exact event integrity, typed catalog, concurrent questions, errors/retries/cancellation | Public review and freeze |
| Interoperability | Independent SDKs, typed answers and validated outputs over HTTP | Independent ecosystem peer implementations |
| Swarm | Three contributors, actual dependencies, distinct retry tasks, optional failure and provenance | Quorum runtime scheduler, nested plans |
| Durability | Atomic SQLite acceptance/outbox, leased dispatch, process-kill recovery, response-loss reconciliation | Shared coordination and ownership transfer |
| Progress | Fixed weights, execution/aggregation/validation, null totals, retry regression, separate high watermark | Production measured-unit adapters |
| Developer experience | SDK archives, live CLI, copyable examples, navigable local docs, controlled identifiers | Owner-authorized package/specification release |

## Release gate

Review the MIT licensing and package dependency licenses, establish a private reporting channel, freeze schemas and identifier contents, verify fresh installs/archives and independent live peers, then authorize package and specification publication. The owner handles pushes, tags and publication. Draft IDs live under the controlled repository; no domain purchase is needed to test locally.

Production adapters still need application-owned authentication, retention/resource budgets and operational monitoring. A2A streaming, signed contract distribution, trust in contributor content, distributed coordinator failover and enforceable cancellation guarantees remain explicit future work.
