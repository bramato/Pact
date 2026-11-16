<?php

return [
    'enabled' => true,
    'role' => null,
    'product_id' => null,
    'url' => null, // Defaults to app.url during provider registration.
    'prefix' => 'pact',
    'database_connection' => null,
    'auth' => ['guard' => 'web', 'provider' => 'users'],
    'handler' => null,
    'contract_path' => null,
    'contract_registry' => null,
    'conversation_reader' => null,
    'local_hosts' => [],
    'worker' => ['max_attempts' => 5, 'lease_seconds' => 60, 'poll_ms' => 250],
    'prefixes' => [''],
    'events' => 'events',
    'middleware' => [],
];
