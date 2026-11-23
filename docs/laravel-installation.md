# Laravel master and client installation

The same `pact-protocol/laravel` package runs as a **master**, owning users' teams (federations), or as a **client**, exposing an agent for each application user. The application supplies its login and its own AI implementation. The database worker does not require Redis.

## Install a versioned release

For an existing installation, follow the [redeploy procedure](#redeploy-an-existing-installation) before running any installation command.

```sh
composer require pact-protocol/laravel:^0.3.0
```

Composer installs the Core dependency automatically. Public version tags and upgrade instructions are described in the [release guide](releases.md).

For development from a checkout, add Composer path repositories pointing at its absolute `sdk/php` and `sdk/laravel` directories. Assign both packages the version of the checkout, using the path repository structure in `reference/composer.json`. Standalone Composer release archives are also supported.

Before using Composer path repositories from a fresh checkout or building archives, run `npm ci --ignore-scripts` and `npm run build:sdk` in the PACT repository to bundle Core schemas and Federation result contracts. The consuming Laravel application does not need Node.js. Use Laravel 12.41.1+ or 13 with the compatible PHP version and OpenSSL, JSON, mbstring, BCMath, cURL and the database extension.

On a first installation, configure the Laravel application's database, `APP_KEY`, existing authentication and `APP_URL`, then choose its role:

```sh
php artisan pact:install
# Equivalent scripted choices, in separate applications:
php artisan pact:install --role=master --no-interaction
php artisan pact:install --role=client --no-interaction
```

The first installation accepts `--url`, `--guard`, `--provider` and `--connection`. Missing required non-interactive configuration fails explicitly. Only PACT migrations and any missing client-side Passport migrations run; unrelated application migrations are excluded.

Herd development uses the project folder's `.test` domain and its existing HTTP/HTTPS scheme. Explicitly allow local HTTP peers:

```sh
php artisan pact:install --role=client --url=http://client.test \
  --allow-local-host=client.test --allow-local-host=master.test
```

Include every local peer in `pact.local_hosts`. Public origins require HTTPS. Existing Passport keys and host authentication are reused. Clear the configuration cache before installation and rebuild the configuration and route caches afterward.

### Preserve installation state on ephemeral releases

This procedure applies to Laravel masters and clients. PACT 0.3.0 reads its installation state and signing keys at local paths under `storage_path()`. Setting `FILESYSTEM_DISK=s3` alone leaves those files local. Use encrypted, private S3 storage for the backup and restore it into each new release. An existing persistent volume is an alternative if the project mounts it at the expected local paths and makes it available to every web and worker instance before startup.

| File | What to preserve |
| --- | --- |
| `storage/app/pact/installation.json` | The installed role, product ID, origin, guard and provider, database connection and local-host settings. |
| `storage/app/pact/private.pem` and `storage/app/pact/public.pem` | The original PACT signing key pair on both roles. |
| `storage/oauth-private.key` and `storage/oauth-public.key` | The original Passport pair on clients that use file-based keys. Use the application's configured local paths if they differ from these defaults. Keep inline Passport keys in the host's secret manager. |

Keep the same `APP_KEY` for this application and environment in the secret manager. Preserve the existing database, including PACT connections and work in progress and, on clients, Passport OAuth state. The database and key must carry over together so encrypted records remain readable.

#### First installation

Run the role-specific installation command only when the deployment is explicitly provisioning a new PACT instance. After a successful `pact:install`, save the files as a complete set in private S3, scoped to the application and environment, before starting the new release or discarding its filesystem. Record file checksums and the expected role and product ID. Use a new backup location or object version for an intentional update, and retain the last verified copy. On a persistent volume, install directly into the mounted paths and verify the complete set there before starting the release.

#### Redeploy an existing installation

1. Use an existing, verified backup for this application and environment. When adding persistence to an existing deployment, capture the initial set from the verified working release and check it before replacing that release. Keep restore access to S3 read-only. With a persistent volume, mount it before running the same checks on the files already there.
2. Before Laravel boots in the new release, stage the download in a private directory. Verify that all expected files are present and readable, their checksums match the recorded set, `installation.json` is valid and has the expected role and product ID, and each public key matches its private key. Place the validated files at the expected local paths on every web and worker instance. Use restrictive ownership and permissions, for example `0700` for the directory and `0600` for the files when deploy, web and worker use the same account. If they use separate accounts, grant access through a restricted group and verify readability for each process. Keep key contents out of logs and web-served paths.
3. If the backup is missing, incomplete, inaccessible or fails validation, stop the deployment before activating the new release. Treat conflicting local files as a failed check. Recover the original set from a verified copy, volume or working release. Do not run `pact:install`, generate replacement keys or overwrite the known backup while recovery is pending. If `installation.json` is missing, supplying a role to `pact:install` can create a new product ID and generate any missing key pairs even if the database already contains PACT data.
4. Once the restore passes, load the original `APP_KEY` and database configuration. Run any Laravel configuration commands and apply any required PACT upgrade only now. An ordinary redeploy does not need `pact:install`. Rebuild the configuration and route caches before starting or restarting the web processes and supervised `php artisan pact:work` worker on the new release.
5. After the new worker is running, run `php artisan pact:doctor` and check its key, credential and recent worker heartbeat results. Then use a connection that was active before the deploy. Send a diagnostic task from the master, verify the client processes it and returns the result, and check an authenticated client call to the master roster. Use the same connection throughout without relinking. If the deployment has no existing connection, record the continuity check as pending.

## Connect an agent from its domain

Domain-only linking requires `pact-protocol/laravel` 0.2.1 or newer. Package release 0.3.0 uses the Core wire protocol 0.2.0 and adds optional [question forms v1](question-forms.md). See [the upgrade steps](#upgrade-existing-installations) for existing master and client applications.

On the **master**, open `/pact/agents` and click **Collega agente**. Enter only the client domain (for example `documents.example.com`); an explicit origin such as `https://documents.example.com` is also accepted. A bare domain uses HTTPS. For local Herd peers, enter their existing full HTTP/HTTPS origin and allow every local host on both installations. Paths, embedded passwords, queries and fragments are rejected.

The master discovers `/.well-known/pact` on that origin and automatically registers its public OAuth client with the agent server. No command or copied client ID is needed on the client. Both installations must support this discovery/registration flow and use the same `pact.prefix` (default `/pact`). A missing or incompatible discovery document produces an error; there is no silent fallback to an unverified endpoint. Published endpoints must remain on the selected origin at the expected paths. Public peer DNS results are checked and pinned to public IP addresses; local development is explicitly allowlisted. Redirects are not followed during server-to-server requests.

The browser opens the **client's** existing login and consent page. That application manages account and tenant selection. The master never receives the user's login password. The authorization-code exchange uses PKCE S256 and random, expiring, one-use state tied to the initiating master identity. Email addresses need not match.

After consent, the browser returns to the master and displays **Dove mettiamo l’agente?**. Choose an owned team or create a named team. OAuth credentials remain encrypted in a temporary, ten-minute link request until this choice; no member is activated and no work can be sent before completion. Cancellation clears that temporary payload. Expired payloads are cleared by the worker and when a new domain link starts. Repeating the same team submission reuses its connection and activation receipt; changing a completed submission is rejected.

The worker then completes the bidirectional handshake:

| Direction | Credential issuer | Purpose |
| --- | --- | --- |
| Master → agent server | Agent server | OAuth access/refresh tokens for the authorized local account/tenant |
| Agent → master | Master | Separate signed credentials for roster, MCP, renewal and disconnect, bound to team, member and connection generation |

The master delivers the return credentials over the authenticated activation request. The client verifies the coordinator URL and published public key, validates each credential and acknowledges activation. Only then does the master mark the member active. The UI distinguishes pending, failed and confirmed activation; refresh the agents page to see the worker's result. Keep `php artisan pact:work` running. Private signing keys are never exchanged.

Every user retains their original personal team and can create more teams. One remote account's agent can belong to **one team per master**; reconnecting to that team preserves identity and increments the authorization generation. To move it, disconnect it first, let the worker confirm remote revocation, then reconnect and select its new team. Other remote accounts or tenants can expose separate agents. Disconnect is available from the agents page.

For an existing authenticated application page:

```php
use PactLaravel\Facades\Pact;

// Redirect the browser to the returned URL; callback and team choice are supplied.
return redirect()->away(Pact::connectDomain('documents.example.com'));
```

Link to `route('pact.agents')` or `route('pact.connect')` from the host navigation. Publish the included pages for visual customisation with `php artisan vendor:publish --tag=pact-views`. The source theme shares the PACT landing's editorial tokens across master pages and client consent; see the [connection interface guidelines](connection-interface.md) for the shared partials, palette, responsive behavior and integration instructions. The shared theme is included from package version 0.2.2; existing view overrides must be reconciled with the updated layout and partials.

A CLI equivalent also discovers and registers automatically:

```sh
php artisan pact:connect documents.example.com --domain --user=THE_MASTER_USER_ID
```

Open the returned URL while signed into the master as that user, consent on the client and choose the team after returning.

### Teams and application APIs

The authenticated facade provides `teams()`, `createTeam($name)`, `members($teamId)`, `sendMessage($memberId, $text, $idempotencyKey, $teamId)`, `task($taskId, $teamId)`, `answer($taskId, $answers, $idempotencyKey, $teamId)` and `cancel($taskId, $teamId)`. Omit the team argument to use the original personal team. Every explicit team ID is checked against the current identity, including task reads, answers and cancellation. These teams are owned by a single local identity; application-wide shared-team ACLs are outside this binding.

### Client account and tenant integration

The client controls tenant selection through its existing login/tenant flow. Add any required selection/authorization middleware to `pact.auth.middleware`; it runs on consent routes after the existing login middleware. Bind `PactLaravel\Contracts\IdentityResolver` when the authorized agent identity includes an account or tenant. `id()` must return a stable, unique local subject (maximum 191 bytes), such as an opaque tenant/user identifier, and `find()` must resolve that subject. Authorization, `/oauth/subject` and task execution preserve it. The selected identity is bound at consent and checked again at approval; switching account or tenant requires a fresh consent request. Do not resolve the tenant from a master-supplied value or infer identity from matching emails.

### Upgrade existing installations

Follow the [redeploy procedure](#redeploy-an-existing-installation) before clearing or rebuilding Laravel caches or rerunning the installer. After updating the package on **both** installations and verifying the restored files, clear the configuration cache and rerun `php artisan pact:install --no-interaction`, then rebuild configuration and route caches. The additive migration preserves existing connections, federations and credentials, names the original federation “Squadra personale” and allows more teams. Re-publish customised config and views deliberately to include the new pages and middleware option.

The previous manual registration commands (`pact:oauth-client`, `pact:application:add` and `pact:connect NAME --user=…`) and `Pact::connectUrl(NAME)` remain supported. That legacy path queues the member into the personal team without the new team-selection step. It is intended for existing integrations; new UI integrations should use `connectDomain()`.

## Run work

Keep `php artisan pact:work` running under the application's process supervisor; `--once` processes at most one available job. The master exposes authenticated roster and MCP endpoints under `/pact`; clients expose Agent Cards and A2A endpoints under `/pact/agents/{agent_id}`.

The default execution lease is sixty seconds. Set `pact.worker.lease_seconds` above the handler's maximum call duration, within the five-minute Federation root deadline. Delivery retries use bounded jitter and honor a bounded `Retry-After`; nonretryable protocol failures settle immediately.

Federation tools are `list_members`, `get_manifest`, `send_message`, `request_delegation`, `get_task`, `read_conversation` and `reserve_step`. The [Laravel Federation binding](../specification/PACT-LARAVEL-BINDING-v0.1.md) defines HTTP resources and versioned tool results. Writes return durable receipts, and retries reuse persisted command bytes. The consumer validates actual output artifacts; an HTTP success or a percentage does not establish completion. Version one permits one outstanding delegation per parent. Root budget/cycle rules apply; nested Swarm is not enabled.

### Connect the client application's agent

The default diagnostic contract works without an AI provider. To perform real tasks, the client supplies its own server-side agent. Reuse the application's agent or build a small one for a specific task. Choose a model and provider SDK supported by the application, keep the credentials in server configuration or a secret manager, and define the agent's instructions and tool input schemas. A first tool can look up a document visible to the local user. Resolve the account and authorized tenant on the server and enforce those permissions on every tool call.

Configure `handler`, `contract_path` and `contract_registry` in `config/pact.php`. Register the input, output and answer schemas locally and pin their exact-byte digests. The archive includes the diagnostic schemas and the agent-chat contract.

```php
use PactLaravel\Contracts\AgentHandler;
use PactLaravel\Execution\AgentContext;
use PactLaravel\Execution\AgentResult;

final class PactAgent implements AgentHandler
{
    public function handle(AgentContext $context): AgentResult
    {
        // Invoke the application's own agent, authorized for context->userId.
        return AgentResult::completed(['text' => 'Processed successfully.']);
    }
}
```

The returned data must match the selected output schema. `AgentResult::question()` persists typed questions, `waiting()` persists a continuation checkpoint, and `error()` records failure. The context carries task/user/federation/root identities, invocation identity, answers, checkpoint and delegated results. Applications must use the invocation identity to make external effects idempotent; a database lease cannot make arbitrary remote tools execute exactly once.

Use the diagnostic contract to check connectivity, then run a real task from the master and verify an authorized tool call and a validated output. For question forms introduced in 0.3.0, confirm that the receiving coordinator advertises and can render v1 before emitting a form.

Override `IdentityResolver` through Laravel's container when local identity resolution differs. `ConversationReader` denies reads by default; a configured reader must authorize actual participants. Federation membership never grants general conversation, file or tool access. Conversations remain in their owning apps.

## Credentials and recovery

OAuth tokens and credential-bearing delivery payloads are encrypted. Master credentials use separate audiences for roster, MCP, renewal and disconnect, with current membership and generation checks.

```sh
php artisan pact:disconnect CONNECTION_ID --user=LOCAL_USER_ID
php artisan pact:doctor
```

Disconnect immediately prevents new local work and queues remote revocation. Exact replay recovers a lost revocation response. Delivery attempts and credentials have bounded lifetimes: extended outages may leave an audited, unconfirmed failure requiring operational recovery. Doctor reports schema, keys, routes, credentials, failed deliveries and worker heartbeat without printing tokens. Do not delete the database to recover a delivery.

## Verify the package

```sh
composer install --working-dir=sdk/laravel --no-interaction
npm run test:laravel
npm run test:laravel:http
npm run check:all
npm run package:smoke
```

Testbench tests use isolated storage and SQLite databases. CI resolves Laravel 12 and 13 independently. The Core conformance suite and reference demo remain independent of application AI providers.
