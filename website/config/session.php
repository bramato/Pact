<?php

return [
    'driver' => 'array',
    'lifetime' => 120,
    'encrypt' => false,
    'files' => storage_path('framework/sessions'),
    'cookie' => 'pact_website_session',
    'path' => '/',
    'domain' => null,
    'secure' => true,
    'http_only' => true,
    'same_site' => 'lax',
];
