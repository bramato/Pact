---
name: pact-release-handoff
description: Maintain the bilingual PACT homepage release history and Laravel master and client handoffs when package behavior, installation requirements, public APIs, or a release version changes.
---

# PACT release handoff

1. Review the package diff against the previous tag and identify changes that affect installation, migration, runtime, peer compatibility or host integration. Record user-visible changes in the root [changelog](../../../CHANGELOG.md). Use an Unreleased entry while the work has no assigned release version.
2. When preparing a version in [release.json](../../../release.json), add it to [website/config/releases.php](../../../website/config/releases.php) in descending version order. Keep the wire protocol version separate. Add the Italian and English changes and role-specific instructions in the two `website/lang/{it,en}/releases.php` catalogs. Write the changes in terms of what integrators can use and what they need to update.
3. Check the install command, OAuth flow, API signatures and upgrade steps against that version. The homepage pins both Composer packages and source links to the selected version. For each version-specific document, use a path present in its tag or release candidate. Keep earlier handoffs usable by adding a new variant in the two `handoff.php` catalogs if the shared installation, execution, or linking instructions diverge.
4. In the client handoff, cover a server-side agent supplied by the host app, including how to create one when absent, connect its model with server-side credentials, register and authorize its tools, and wire it through `AgentHandler`. Verify a real task and tool call after the diagnostic check. In the master handoff, cover the linking flow, authorized task and question handling, and compatibility with the selected client's capabilities.
5. Run `php website/tests/localization.php` with website dependencies installed. Check both language pages, the current release and an older version, including the selected handoffs, Composer pins, and tagged documentation links. Follow the [release guide](../../../docs/releases.md) for build and distribution verification.
