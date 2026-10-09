# Contributing to PACT

PACT is an independent draft profile of A2A. Start with the [Core specification](specification/PACT-CORE-v0.2.md), [optional Swarm](specification/PACT-SWARM-v0.2.md), and [roadmap](docs/roadmap.md).

Use Node.js 22+, PHP 8.2+ and Composer 2:

```sh
npm ci --ignore-scripts
npm run build:sdk
composer install --working-dir=sdk/php --no-interaction
composer install --working-dir=reference --no-interaction
npm run check:all
```

Propose a concrete interoperability problem before extending the protocol. A protocol change should include its normative wording, schemas, positive and negative JSON fixtures, and changes to examples and compatibility notes. JSON fixtures are the portable interface of the conformance suite; both independent implementations must agree with the declared expectations.

Keep A2A transport, states, and core objects authoritative. Custom data belongs under the negotiated extension URI in metadata. Do not publish the example URI as a production identity or imply A2A endorsement. Keep portable fixture expectations independent of model internals or implementation-specific transports.

For incompatible draft changes, update the compatibility document and changelog. Released schemas and contracts are immutable; a new shape needs a new version and digest. Explain what changed and which checks passed in each contribution. Keep commits focused, use descriptive imperative messages, and follow the [code of conduct](CODE_OF_CONDUCT.md).

Contributions are under the repository's [MIT license](LICENSE.md). A license change requires an explicit maintainer decision and review of existing contributions. Maintainers review protocol requirements and conformance coverage together; passing the local harness alone is not a production compliance claim.
