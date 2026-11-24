# Publishing and versioning Composer packages

The source of truth is `bramato/Pact`. Public distribution repositories contain generated, standalone package snapshots:

| Composer package | Distribution repository |
| --- | --- |
| `pact-protocol/core` | https://github.com/bramato/pact-php |
| `pact-protocol/laravel` | https://github.com/bramato/pact-laravel |

Consumers install releases normally:

```sh
composer require pact-protocol/laravel:^0.3.2
```

The Core dependency is resolved automatically. Neither a local path repository nor Node.js is needed in the consuming application.

## Release policy

Composer versions come from immutable Git tags such as `v0.2.0`; do not add a `version` field to exported `composer.json`. Patch versions contain compatible fixes. Before 1.0, incompatible package API changes use a new minor version. Never reuse a released version or move its tag; publish the next version instead.

The package version is independent of the wire protocol and contract versions. A Laravel `0.2.2` bug fix can still negotiate PACT Core `0.2.0`. Do not reformat released schema bytes: a schema change requires an appropriate new descriptor and digest.

## Prepare a release

Update the changelog, `release.json` and the bilingual homepage using the repository skill at `.agents/skills/pact-release-handoff/SKILL.md`. Run the applicable checks and commit the intended source changes.

```sh
npm run check:all
npm run test:laravel
npm run test:laravel:http
npm run package:smoke
node --test tests/release.test.mjs tests/publish-release.test.mjs
composer install --working-dir=website
php website/tests/localization.php
```

Build an explicit version from a committed ref:

```sh
npm run release:build -- --version 0.2.2 --output storage/releases/0.2.2 --ref HEAD
```

The builder reads Git objects, not the working tree. It exports only the two SDK packages, includes generated schemas/result contracts, strips development-only repositories and dependencies, and writes ZIP files, package checksums and a release manifest with the exact source commit. A repeated build must match an existing output directory byte for byte or fail.

Validate the exported Composer manifests and verify installation in a disposable application before publication. Source commits must be available in the public upstream repository so release provenance can be inspected.

## Publish

The publication tool uses the authenticated GitHub CLI account. It first validates the release files locally; without `--publish` it performs no external writes:

```sh
npm run release:publish -- --directory storage/releases/0.2.2
npm run release:publish -- --directory storage/releases/0.2.2 --publish
```

The tool publishes the generated package trees, creates version tags and attaches the ZIP/checksum/provenance files to GitHub releases. Existing conflicting tags or artifacts cause failure rather than replacement. Distribution repositories are generated outputs; edit the upstream SDK sources instead of editing them directly.

On the first release, submit each public distribution repository to Packagist under the maintainer's account. Configure Packagist's GitHub integration for automatic updates, or request an update from the package page after publishing a new tag. Verify both packages' public version metadata and install the Laravel package in a fresh application with default Composer repositories before declaring publication complete.

The tag-triggered GitHub Actions workflow also builds release artifacts from the tagged source. It does not store maintainer credentials or grant the default repository token access to other repositories. The maintainer's explicit publication command controls distribution updates.

## Upgrade a consuming Laravel application

Select the target package version in the homepage Handoff section for copyable master and client instructions. Release 0.3.2 adds an optional [database installation store](laravel-installation.md#keep-state-in-the-database-on-ephemeral-deployments). Release 0.3.1 adds optional [Laravel queue configuration for task execution](laravel-installation.md#choose-the-laravel-queue-for-task-execution) and readable task progress. For [question forms v1](question-forms.md), verify that both peers support the form and that the coordinator can render it. For earlier upgrades, see the [0.2.2 upgrade handoff](upgrade-handoff-v0.2.2.md).

Before running the commands below, [restore and verify the existing installation files](laravel-installation.md#redeploy-an-existing-installation) and keep the original `APP_KEY` and database. For an existing project adopting the database store, configure it on the working release first and run the installer with the original files available. Refresh Laravel's configuration cache if you changed the configuration.

```sh
composer require pact-protocol/laravel:0.3.2 pact-protocol/core:0.3.2 --with-all-dependencies && php artisan pact:install --no-interaction
```

After starting the new web and worker processes, run `php artisan pact:doctor` and verify continuity with an existing connection.
