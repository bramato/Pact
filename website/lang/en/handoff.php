<?php

return [
    'heading' => 'Handoff for Laravel.',
    'intro' => 'Choose the package version you want to install, then copy the master or client handoff into your project assistant chat.',
    'versions' => 'Package versions',
    'changes' => 'What changed in :version',
    'protocol' => 'Core protocol :version',
    'references' => 'Documentation for this version',
    'documents' => [
        'guide' => 'Laravel installation',
        'core' => 'Core specification',
        'binding' => 'Laravel binding specification',
        'question_forms' => 'Question forms v1',
        'theme' => 'Connection interface guide',
        'upgrade' => 'Upgrade handoff',
        'changelog' => 'Changelog',
    ],
    'preview' => 'Read the full handoff',
    'copied' => 'Handoff copied. Paste it into your project chat.',
    'copy_error' => 'Automatic copying is unavailable. The text is selected for manual copying.',
    'prompt' => <<<'HANDOFF'
Integrate PACT :version into this Laravel application with the :role role. Use source tag v:version from bramato/Pact. The Core wire protocol version is :protocol.

:goal

Read the documentation for this release:
:sources

Inspect the host's Laravel/PHP versions, database, authentication, existing agent and tools. Reuse the application's login, guard and provider. Keep its existing Herd scheme and .test domain in APP_URL in local development, and use HTTPS for public peers.

Use the exact package versions below for installation, including when a linked guide shows a different Composer example:
composer require pact-protocol/laravel::version pact-protocol/core::version --with-all-dependencies

Verify both versions with composer show and keep composer.json and composer.lock in the project. If a Composer path or VCS override interferes, check it against the selected tag. For development from source, check out v:version, run npm ci --ignore-scripts and npm run build:sdk there, and set both Composer path package versions to :version.

Verify the database, APP_KEY and APP_URL. For a fresh install, run:
php artisan config:clear
php artisan pact:install --role=:role --no-interaction

For local HTTP peers include one --allow-local-host per host, including both applications. For an existing installation, clear config and rerun php artisan pact:install --no-interaction after updating the packages. Preserve the database, APP_KEY, Passport keys and storage/app/pact. Review published config and views against the selected version. Run php artisan pact:doctor, rebuild the config and route caches, and supervise php artisan pact:work as a persistent process. Use pact:work --once for a single check.

:linking

:execution

:release_instructions

:verification

Report the working integration, the tests run, the worker setup and any checks awaiting a peer or user consent. Keep tokens and private keys out of the report.
HANDOFF,
    'linking' => [
        'manual' => [
            'master' => <<<'HANDOFF'
Ask the client to run php artisan pact:oauth-client --master=https://master.example. Register its public client ID and start the connection on the master:
php artisan pact:application:add --name=documents --url=https://documents.example --client-id=THE_CLIENT_ID
php artisan pact:connect documents --user=MASTER_USER_ID

Use the returned URL or Pact::connectUrl('documents') on an authenticated master page. The user signs into the client and consents. Wait for the worker to confirm activation in the master's personal federation.
HANDOFF,
            'client' => <<<'HANDOFF'
Register the master on the client with php artisan pact:oauth-client --master=https://master.example. Give the master this application's origin and the returned public client ID so it can run pact:application:add and pact:connect. Reuse the client's login and Passport keys for consent. The worker completes activation in the master's personal federation.
HANDOFF,
        ],
        'domain' => [
            'master' => <<<'HANDOFF'
Link from the master using route('pact.agents'), route('pact.connect') or Pact::connectDomain(). The user chooses «Collega agente» and enters the client domain. The master reads /.well-known/pact and registers its public OAuth client automatically. The CLI equivalent is php artisan pact:connect CLIENT_DOMAIN --domain --user=MASTER_USER_ID.

The user signs into the client, consents and returns to the master to choose or create an owned team. Both peers use the same pact.prefix. Show pending, failed and confirmed activation. Wait for the worker to confirm both credential directions before sending work. Confirm revocation before moving a remote agent to another team.

Use Pact::teams() and createTeam() and pass the selected team to members(), sendMessage(), task(), answer() and cancel(). Omitting the team uses the personal team.
HANDOFF,
            'client' => <<<'HANDOFF'
Verify /.well-known/pact and automatic public OAuth client registration. Give the master this application's domain. The user starts linking there, signs in and consents here, then returns to the master to choose a team. Both peers use the same pact.prefix. The master worker confirms credentials in both directions.

For tenant selection before consent, use the application's authorized tenant flow in pact.auth.middleware. Bind IdentityResolver to a stable subject for the local user and tenant and require fresh consent if that identity changes before approval.
HANDOFF,
        ],
    ],
    'roles' => [
        'master' => [
            'title' => 'Master',
            'description' => 'Connect agents, assign work and review results in the coordinating application.',
            'copy' => 'Copy master handoff',
            'goal' => 'Let each signed-in user connect remote agents, send them work and review questions, progress and validated results.',
            'execution' => <<<'HANDOFF'
Use PactLaravel\Facades\Pact to list members, send work, read tasks, answer questions and cancel requests. Scope every operation to the authorized local identity and its teams or personal federation, according to the installed version. Reuse idempotency keys on retry and validate output artifacts against the negotiated contract. Use the binding specification for authenticated roster and MCP calls.
HANDOFF,
            'verification' => <<<'HANDOFF'
Test consent, confirmed activation, one authenticated task and one authenticated client call to the master roster. Cover another user's access, denied or expired consent, duplicate submissions, questions and answers, cancellation and revocation with php artisan pact:disconnect CONNECTION_ID --user=MASTER_USER_ID.
HANDOFF,
        ],
        'client' => [
            'title' => 'Client',
            'description' => 'Connect the agent and tools that run on your application’s own server.',
            'copy' => 'Copy client handoff',
            'goal' => 'Expose a real server-side agent for each authorized account. The PACT package handles connection and task delivery and includes a diagnostic handler. The client application supplies its own agent, model connection and tools.',
            'execution' => <<<'HANDOFF'
Reuse the application's server-side agent if it has one. Otherwise build one in the client application. Choose a model provider and SDK supported by the project, configure the model and its credentials in server-only configuration or a secret manager, and define instructions for one concrete task. Register a small list of server-side tools with explicit input schemas, starting with a read-only tool such as looking up a document the current user can access. Authorize each tool call with the local user and tenant and test the agent directly before connecting it to PACT.

Implement PactLaravel\Contracts\AgentHandler with handle(PactLaravel\Execution\AgentContext $context): PactLaravel\Execution\AgentResult. Resolve the local user from $context->userId, pass $context->input and any $context->answers and $context->checkpoint into the agent, and map the result to AgentResult::completed(), question(), waiting() or error(). Use $context->invocationId to make tool side effects idempotent.

Set handler, contract_path and contract_registry in config/pact.php. Use locally registered input, output and answer schemas with exact-byte digests. Reuse the bundled agent-chat contract when appropriate. ConversationReader denies access by default; any custom reader must check actual participants.
HANDOFF,
            'verification' => <<<'HANDOFF'
First use the diagnostic contract to check connectivity. Then send a real task from the master and verify that the client agent calls an authorized tool and returns a validated artifact. Check user and tenant isolation, retry idempotency, questions and resumption, roster access with the return credentials and revocation with php artisan pact:disconnect CONNECTION_ID --user=CLIENT_USER_ID. Report the client domain and the capabilities actually available to the master.
HANDOFF,
        ],
    ],
];
