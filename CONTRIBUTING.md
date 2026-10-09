# Contributing to PACT

PACT is an independent draft profile of A2A. Start with the [specification](specification/PACT-SPEC-v0.1.md), [wire conventions](specification/wire-conventions.md), and [roadmap](docs/roadmap.md).

Use Node.js 22 or newer:

```sh
npm ci --ignore-scripts
npm run check
```

Propose a concrete interoperability problem before extending the protocol. A protocol change should include its normative wording, schemas, positive and negative JSON fixtures, and changes to examples and compatibility notes. JSON fixtures are the portable interface of the conformance suite; the JavaScript runner is replaceable.

Keep A2A transport, states, and core objects authoritative. Custom data belongs under the negotiated extension URI in metadata. Do not publish the example URI as a production identity or imply A2A endorsement. Do not add new transports, model internals, or SDK dependencies to the conformance corpus.

For incompatible draft changes, update the compatibility document and changelog. Released schemas and contracts are immutable; a new shape needs a new version and digest. Explain what changed and which checks passed in each contribution. Keep commits focused, use descriptive imperative messages, and follow the [code of conduct](CODE_OF_CONDUCT.md).

Contributions are under the repository's [MIT license](LICENSE.md). A license change requires an explicit maintainer decision and review of existing contributions. Maintainers review protocol requirements and conformance coverage together; passing the local harness alone is not a production compliance claim.
