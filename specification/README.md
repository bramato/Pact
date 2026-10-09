# PACT specifications

Start new implementations with [Core v0.2](PACT-CORE-v0.2.md) and separately negotiated [Swarm v0.2](PACT-SWARM-v0.2.md). [Versioned schemas](0.2/schemas/) and [portable vectors](../conformance/v0.2/README.md) accompany the normative draft. The manifest records the exact project-controlled identifiers.

The original proposal and its progress schema remain byte-for-byte source records. v0.1 wire conventions and examples are retained for historical review and run in their own legacy suite; do not combine their placeholder identity or digest convention with v0.2 peers.

## Archived draft materials

- [PACT v0.1 proposal](PACT-SPEC-v0.1.md): supplied document, preserved verbatim.
- [Wire conventions](wire-conventions.md): repository draft decisions needed to make the prose executable.
- [Vocabulary](vocabulary.md): resource names and authoritative A2A fields.
- [JSON Schemas](schemas/): Draft 2020-12 resource shapes; `progress.schema.json` is the supplied file, unchanged.
- [Compatibility](../docs/compatibility.md): reviewed binding and limits of verification.

All `example.org` schema IDs and extension URIs are illustrative identifiers. The harness maps them to local files and never fetches them. Production deployment needs a permanent URI and an independently reviewed adapter.
