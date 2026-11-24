<?php

return [
    'enabled' => true,
    'role' => null,
    'product_id' => null,
    'url' => null, // Defaults to app.url during provider registration.
    'prefix' => 'pact',
    'database_connection' => null,
    'state_store' => env('PACT_STATE_STORE', 'files'),
    'auth' => ['guard' => 'web', 'provider' => 'users', 'middleware' => []], // Client tenant/account selection before consent.
    'handler' => null,
    'contract_path' => null,
    'contract_registry' => null,
    'conversation_reader' => null,
    'local_hosts' => [],
    'queue' => [
        'connection' => env('PACT_QUEUE_CONNECTION'),
        'name' => env('PACT_QUEUE'),
    ],
    'worker' => ['max_attempts' => 5, 'lease_seconds' => 60, 'poll_ms' => 250],
    'prefixes' => [''],
    'events' => 'events',
    'middleware' => [],
];
