<?php

return [
    'name' => env('APP_NAME', 'PACT'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://pact.test'),
    'timezone' => 'UTC',
    'locale' => 'it',
    'fallback_locale' => 'it',
    'faker_locale' => 'it_IT',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [],
    'maintenance' => ['driver' => 'file'],
];
