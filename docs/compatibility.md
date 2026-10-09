# Compatibility

| Component | Current target | Evidence / limit |
| --- | --- | --- |
| PACT | 0.1.0 draft | Imported proposal, schemas, local fixtures |
| A2A binding | 1.0 | Examples checked against upstream release v1.0.1 models; no live peer test |
| JSON Schema | Draft 2020-12 | Ajv 2020 validator with format checks enabled |
| Local tooling | Node.js >=22 | Native test runner, no A2A SDK dependency |
| TypeScript / PHP SDKs | Planned | No packages or transport adapters implemented |
| Other A2A versions | Unverified | Need separate binding examples and interoperability tests |

The wire version is `1.0`; `v1.0.1` identifies the upstream source release used for review. A2A 1.0 uses `SendMessage`, `ROLE_USER` / `ROLE_AGENT`, `TASK_STATE_*`, and structured Parts with a `data` member. Older examples using `message/send`, `kind: "data"`, and lowercase states must not be mixed into this binding.

PACT is a profile over existing A2A objects and extension negotiation. A2A peers without PACT remain valid A2A peers. A PACT caller must explicitly negotiate support and either fail or obtain caller authorization before downgrading. Metadata, progress, or HTTP 200 cannot establish task completion.

The fixture runner checks PACT resources and documented semantics. The example checker verifies selected fields and nesting against the reviewed A2A models, not the entire upstream protocol. Passing these checks is not certification, full A2A conformance, or evidence of secure production operation.

Primary sources:

- [A2A v1.0.1 specification](https://github.com/a2aproject/A2A/blob/v1.0.1/docs/specification.md)
- [A2A v1.0.1 protobuf models](https://github.com/a2aproject/A2A/blob/v1.0.1/specification/a2a.proto)
- [A2A v1.0.1 extension guidance](https://github.com/a2aproject/A2A/blob/v1.0.1/docs/topics/extensions.md)
- [JSON Schema Draft 2020-12](https://json-schema.org/draft/2020-12)
