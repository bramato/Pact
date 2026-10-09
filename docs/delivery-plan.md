# Six-point interoperability delivery

This work implements the six improvements requested on 2026-10-09. The objective remains all six points; a checked item needs direct evidence, not only a document or an in-memory simulation.

| Point | Required deliverable | Completion evidence | Status |
| --- | --- | --- | --- |
| 1 | Normative implementable profile: typed events, digest coverage, concurrent questions, errors, retries and cancellation | Versioned schemas, positive/negative portable cases, independent TS/PHP agreement | Complete locally |
| 2 | Deterministic TypeScript–PHP task/question/result exchange and three-contributor dependency demo | HTTP transcript, validated outputs, actual child dispatch and provenance | Complete locally |
| 3 | CLI testing a real endpoint: discovery, negotiation, messages, lifecycle and delivery failures | Passing live endpoint run, deliberate broken-peer rejection, duplicate/out-of-order/expiry/disconnection tests | Complete locally |
| 4 | Durable tasks, questions, receipts and outbound notifications; resume after a killed coordinator | Process killed during work, restart against same database, no duplicate committed effects | Complete locally |
| 5 | Weighted parent progress across stages, optional failure and retries | Shared numerical vectors plus runtime parent progress in the swarm demo | Complete locally |
| 6 | Small usable SDKs, copyable examples, navigable documentation, permanent identity and optional swarm negotiation | Independently usable npm/Composer packages, built/browsed docs, URI tied to controlled repository, core-only and swarm negotiation tests | Complete locally |

The original v0.1 proposal and supplied progress schema remain source records. Incompatible executable changes use a new v0.2 draft instead of mutating those source records. Reference service development uses Herd's existing `pact.test` site and scheme. Publication, Git pushes, tags and PRs remain under the user's control.

## Delivered architecture

- A small TypeScript core and HTTP client, built as an npm package.
- An independently implemented PHP core and HTTP client, with a Laravel integration adapter kept separate from the protocol rules.
- Versioned language-neutral schemas and behavior vectors shared by both implementations.
- An authenticated PHP reference A2A service, a SQLite coordinator with transactional acceptance/outbox, and deterministic contributor endpoints.
- A live conformance CLI and process-level fault tests.
- Static navigable documentation generated from repository sources.

## Completion audit

Verified on 2026-10-09 using Node.js 22.22.3, PHP 8.4.25 and Illuminate 12.69.3. The reference Composer lock resolves against PHP 8.2; CI additionally defines Node 22/PHP 8.2 and Node 24/PHP 8.4 jobs. Remote CI has not been run or claimed.

| Command / observation | Actual evidence |
| --- | --- |
| `npm run check:all` | 178 passing Node tests, including 147 shared expected-value/error vectors evaluated independently by TypeScript and PHP; 88/88 archived checks; source integrity, links, resources and docs build; 11 passing HTTP tests |
| `node tools/pact.mjs test http://pact.test/a2a --config storage/demo-config.json --reference-suite` | 14 passing live checks through Laravel routes: discovery, activation, contract/output, replay/conflict, terminal behavior, native/profile versions, authentication, tenants, concurrent atomic answers, expiry, cancellation and event ordering |
| `npm run demo:verify` | Core clarification/result exchange and a real three-agent HTTP dependency run complete with independently validated five-word output; optional audit failure and distinct failed/successful review tasks retained |
| HTTP kill/restart test | OS SIGKILL after child commit and before local acknowledgment; same SQLite database after lease expiry; identical raw command retried; original child task reconciled; committed child effect stays exactly one |
| HTTP dropped-response test | Remote commit succeeds, connection closes before response; two delivery attempts reuse command bytes and produce one committed child effect |
| HTTP cancellation/exhaustion/broken-peer tests | Child cancellation acknowledgment remains separate from parent intent; required dispatch fails after five 503 attempts; live CLI exits 1 for a deliberately invalid output; missing activation is rejected |
| HTTP lease/cancellation/optional-exhaustion races | A replaced worker leaves the successor's receipt and parent intact; cancellation after lost acknowledgment reconciles the original committed child; five failed optional deliveries retain an unconfirmed error while required output completes |
| Weighted progress vectors and runtime snapshots | Required scope excludes optional audit; fixed 80:10:10 stages; retry can lower current progress while preserving high watermark; null propagation, settled failure and one final half-up rounding agree across languages |
| `npm run package:smoke` | npm, PHP Composer and Laravel Composer archives install/import in separate temporary applications; package resources and licenses are present; no checkout-relative runtime dependency |
| `npm run docs:build` and browser inspection | 28 generated pages and 3 resource indexes; overview, search filter and Laravel navigation inspected on Herd; transparent README art retained and validated |

The original proposal and supplied progress schema pass their recorded exact-byte digests. Local tokens, databases, transcripts, generated site, compiled code and dependency directories are ignored and excluded from commits. Library dependencies are installed normally; schema validation itself never fetches arbitrary contract URLs.

## Release and implementation limits

All six deliverables are implemented and verified locally. The project-controlled Core/Swarm identifiers use the owner's repository namespace. Documents and npm/Composer packages are **not published**; the owner handles pushes and releases. No PR or tag was created.

The deterministic swarm executes all-required scheduling. Quorum eligibility and scoped arithmetic have portable tests; a runtime quorum scheduler, nested swarms, A2A streaming/push configuration and distributed coordinator ownership transfer remain future work. The reference uses one SQLite coordinator, polling, local configured bearer principals, a six-second lease and bounded retries. Application deployment/authentication/retention policy is separate. A passing suite proves this declared PACT binding and reference implementation, not general A2A certification or content truth.
