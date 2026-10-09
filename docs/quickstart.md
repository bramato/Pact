# Run two implementations

PACT v0.2 is an independent draft over A2A 1.0. The TypeScript caller verifies PHP/Laravel outputs itself. The demo is deterministic and needs no model API or provider key.

## Install

Use Node.js 22+, PHP 8.2+ with JSON, mbstring, BCMath, cURL and SQLite, and Composer 2. The crash test also uses POSIX process signals on macOS/Linux.

```sh
npm ci --ignore-scripts
npm run build:sdk
composer install --working-dir=sdk/php --no-interaction
composer install --working-dir=reference --no-interaction
php reference/bin/setup.php
npm run check
npm run docs:build
```

The local Herd demo runs at **http://pact.test** over HTTP. Herd serves `public/`; storage and dependencies remain outside the document root. A deployed authenticated peer uses HTTPS and an authentication adapter supplied by its application.

Setup creates private random tokens and a SQLite database under ignored `storage/`. It preserves existing configuration. Tokens do not appear in Agent Cards, transcripts, fixtures or CLI output. `PACT_CONFIG` can select a separate local configuration file. Never commit that file.

## One task with clarification

```sh
node examples/v0.2/demo.mjs
```

The caller discovers Core, sends a document, receives two concurrent required questions, answers them, and independently validates the completed artifact. The result is five words and an approved summary. The exchange is saved to `storage/demo-transcript.json` without transport credentials.

## Three collaborators

In one terminal run the coordinator worker, then start the demo in another:

```sh
php reference/bin/worker.php --watch
```

```sh
node examples/v0.2/demo.mjs --swarm
```

The static plan dispatches `extract`, then `review`, then `assemble` through three authenticated HTTP endpoints. Validated predecessor data is included in subsequent dispatches. An optional audit fails without blocking required work; the first review attempt fails and a distinct second task succeeds. Contributor provenance retains both attempts.

`--watch` keeps the worker running until interrupted. Without it, the worker drains available work and exits after two idle seconds; `--once` processes one leased outbox item. Restart against the same configuration/database to resume. Do not remove the database during work.

## Test a peer

```sh
node tools/pact.mjs test http://pact.test/a2a \
  --config storage/demo-config.json --reference-suite
npm run test:integration
```

The CLI emits a JSON report and exits nonzero on any mismatch. `--reference-suite` exercises the document-review fixture's concurrent questions, expiry and event endpoint. Baseline checks exercise this profile's synchronous completed task; another contract can be supplied with `--contract`, `--registry` and `--input`. For another service use `PACT_TOKEN` and omit local configuration. This is a bounded PACT binding suite, not a certification of every A2A feature or every asynchronous peer.

Integration tests start disposable loopback HTTP fixtures, separate from the Herd site, and use temporary databases. They kill a worker after a remote commit, restart it, replace a lease, drop an HTTP response, exhaust required and optional retries, reconcile cancellation after a lost acknowledgment and reject a broken peer. They clean up their servers and databases.

## Read the profile

Run `npm run docs:build`, then open [the local docs](http://pact.test/docs/). The build writes an ignored `site/` directory with navigation and search. Specifications and SDK manuals remain normal Markdown source files in Git.

Core and Swarm have separate [project-controlled identifiers](../specification/0.2/manifest.json). Publishing the corresponding documents and packages requires the repository owner's release. This checkout does not push or publish them.
