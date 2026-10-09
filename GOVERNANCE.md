# Governance

PACT is an independent proposal maintained in this repository. Marco Bramato is the initial maintainer, as recorded by the existing repository license. No A2A, Linux Foundation, or third-party endorsement is asserted.

Protocol proposals should describe an interoperability problem, alternatives, compatibility effects, security boundaries, and executable fixtures. Maintainers accept changes through review, documenting unresolved questions in the roadmap. The owner of the repository controls release and publication decisions.

Versioning follows semantic versioning. The current version is `0.2.0`, with draft status rather than a published release. Before 1.0, incompatible protocol changes require a new minor version and compatibility notes; editorial corrections may use a patch version. Contract versions are independent of the PACT version. Exact bytes determine a contract schema's SHA-256 digest; formatting changes also change that digest.

Core and optional Swarm now have separate identifiers under the controlled `bramato/Pact` repository; see the [manifest](specification/0.2/manifest.json). They await document publication when the owner releases this draft. Before a public release, maintainers must review the name, freeze identifier contents, compatibility target and conformance scope, and establish private security and conduct contacts. Archived `example.org` identifiers are illustrative only. A2A's official extension namespace cannot be claimed by this project.

The existing license remains MIT. The source proposal suggests Apache-2.0 for later review; that suggestion is an open governance decision and does not change the license of this repository.
