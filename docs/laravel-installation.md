# Laravel master and client installation

The same `pact-protocol/laravel` package runs as a **master**, owning users' personal federations, or as a **client**, exposing an agent for each application user. The application supplies its login and its own AI implementation. The database worker does not require Redis.

## Install locally

Packages have not been published. Add Composer path repositories pointing at this checkout's absolute `sdk/php` and `sdk/laravel` directories, assigning local version `0.2.0`, as shown in `reference/composer.json`. Then require `pact-protocol/laravel:^0.2`. Standalone Composer archives are also supported. Package-name installation without a configured repository requires a future owner-authorized release.

Before archiving, run `npm ci --ignore-scripts` and `npm run build:sdk` in the PACT repository to bundle Core schemas. The consuming Laravel application does not need Node.js. Use Laravel 12.41.1+ or 13 with the compatible PHP version and OpenSSL, JSON, mbstring, BCMath, cURL and the database extension.

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

## Register and connect

On the client, register the master once:

```sh
php artisan pact:oauth-client --master=https://master.example
```

On the master, register the returned public client ID and start a user's connection:

```sh
php artisan pact:application:add --name=documents \
  --url=https://documents.example --client-id=THE_CLIENT_ID
php artisan pact:connect documents --user=THE_MASTER_USER_ID
```

Open the returned URL. The user logs in to the client, approves agent access and returns to the master as the same master user who initiated the connection. Email addresses need not match; authorization explicitly links the two local identities. OAuth uses authorization codes, PKCE S256 and expiring one-use state. A worker completes activation; pending members cannot receive work. Reauthorization preserves agent identity and fences stale jobs with a new generation.

An existing authenticated page can use `PactLaravel\Facades\Pact::connectUrl('documents')`. Publish the minimal consent page using `php artisan vendor:publish --tag=pact-views`. No administrative dashboard or Passport trait on the host User model is required.

The same authenticated facade provides `members()`, `sendMessage($memberId, $text, $idempotencyKey)`, `task($taskId)`, `answer($taskId, $answers, $idempotencyKey)` and `cancel($taskId)` for the application's own interface. These methods scope every operation to the current user's federation and tasks. Replies are queued as correlated native A2A answers; cancellation also reconciles work whose remote acceptance acknowledgment is still pending.

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
