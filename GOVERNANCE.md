# Governance

PACT is an independent proposal maintained in this repository. Marco Bramato is the initial maintainer, as recorded by the existing repository license. No A2A, Linux Foundation, or third-party endorsement is asserted.

Protocol proposals should describe an interoperability problem, alternatives, compatibility effects, security boundaries, and executable fixtures. Maintainers accept changes through review, documenting unresolved questions in the roadmap. The owner of the repository controls release and publication decisions.

Versioning follows semantic versioning. The wire protocol remains the independent `0.2.0` draft; public PHP and Laravel package releases have their own tagged versions, beginning at `0.2.0`. Before 1.0, incompatible protocol or package API changes require a new minor version and compatibility notes; compatible corrections may use a patch version. Contract versions are independent of the package and PACT versions. Exact bytes determine a contract schema's SHA-256 digest; formatting changes also change that digest. See [release operations](docs/releases.md) and the [changelog](CHANGELOG.md).

Core and optional Swarm have separate public identifiers under the controlled `bramato/Pact` repository; see the [manifest](specification/0.2/manifest.json). Tagged package distributions preserve the declared schema bytes, compatibility target and conformance scope. Security reports use the repository's private vulnerability-reporting channel when enabled. Archived `example.org` identifiers are illustrative only. A2A's official extension namespace cannot be claimed by this project.

The existing license remains MIT. The source proposal suggests Apache-2.0 for later review; that suggestion is an open governance decision and does not change the license of this repository.
