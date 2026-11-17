# Laravel master and client installation

The same `pact-protocol/laravel` package runs as a **master**, owning users' teams (federations), or as a **client**, exposing an agent for each application user. The application supplies its login and its own AI implementation. The database worker does not require Redis.

## Install a versioned release

```sh
composer require pact-protocol/laravel:^0.2
```

Composer installs the Core dependency automatically. Public version tags and upgrade instructions are described in the [release guide](releases.md).

For development from a checkout, add Composer path repositories pointing at its absolute `sdk/php` and `sdk/laravel` directories, assigning local version `0.2.0`, as shown in `reference/composer.json`. Standalone Composer release archives are also supported.

Before using Composer path repositories from a fresh checkout or building archives, run `npm ci --ignore-scripts` and `npm run build:sdk` in the PACT repository to bundle Core schemas and Federation result contracts. The consuming Laravel application does not need Node.js. Use Laravel 12.41.1+ or 13 with the compatible PHP version and OpenSSL, JSON, mbstring, BCMath, cURL and the database extension.

Configure the Laravel application's database, `APP_KEY`, existing authentication and `APP_URL`, then choose its role:

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

Include every local peer in `pact.local_hosts`. Public origins require HTTPS. Installation persists role, product identity and initial settings in `storage/app/pact/installation.json`; reinstalling preserves them and signing keys. Existing Passport keys and host authentication are reused. Preserve this storage directory and `APP_KEY` across deployments. Clear configuration caches before installation; rebuild configuration and route caches afterward.

## Connect an agent from its domain

This flow is included in source commit `d120ac0` and descendants containing that implementation. Verify that the installed Laravel package exposes `PactManager::connectDomain()` and the domain-linking routes before using it; satisfying the `^0.2` constraint alone does not prove the feature is included. Until a distribution containing it is published, use the source-checkout installation described above at that commit.

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

Link to `route('pact.agents')` or `route('pact.connect')` from the host navigation. Publish the included pages for visual customisation with `php artisan vendor:publish --tag=pact-views`.

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

Keep the database, `APP_KEY`, Passport keys and `storage/app/pact`. After updating the package on **both** installations, clear the configuration cache and rerun `php artisan pact:install --no-interaction`, then rebuild configuration/route caches. The additive migration preserves existing connections, federations and credentials, names the original federation “Squadra personale” and allows more teams. It does not modify released Core schemas. Re-publish customised config/views deliberately to include the new pages and middleware option.

The previous manual registration commands (`pact:oauth-client`, `pact:application:add` and `pact:connect NAME --user=…`) and `Pact::connectUrl(NAME)` remain supported. That legacy path queues the member into the personal team without the new team-selection step. It is intended for existing integrations; new UI integrations should use `connectDomain()`.

## Run work

Keep `php artisan pact:work` running under the application's process supervisor; `--once` processes at most one available job. The master exposes authenticated roster and MCP endpoints under `/pact`; clients expose Agent Cards and A2A endpoints under `/pact/agents/{agent_id}`.

The default execution lease is sixty seconds. Set `pact.worker.lease_seconds` above the handler's maximum call duration, within the five-minute Federation root deadline. Delivery retries use bounded jitter and honor a bounded `Retry-After`; nonretryable protocol failures settle immediately.

Federation tools are `list_members`, `get_manifest`, `send_message`, `request_delegation`, `get_task`, `read_conversation` and `reserve_step`. The [Laravel Federation binding](../specification/PACT-LARAVEL-BINDING-v0.1.md) defines HTTP resources and versioned tool results. Writes return durable receipts, and retries reuse persisted command bytes. The consumer validates actual output artifacts; an HTTP success or a percentage does not establish completion. Version one permits one outstanding delegation per parent. Root budget/cycle rules apply; nested Swarm is not enabled.

The default diagnostic contract works without an AI provider. To expose application capabilities, configure `handler`, `contract_path` and `contract_registry` in `config/pact.php`. Schemas must be locally registered and pinned by their exact-byte digest. The archive includes the diagnostic schemas and the existing agent-chat contract.

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
