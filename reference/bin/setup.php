<?php

declare(strict_types=1);
use PactReference\Store;

require __DIR__.'/../bootstrap.php';
$directory = $argv[1] ?? __DIR__.'/../../storage';
$url = $argv[2] ?? 'http://127.0.0.1:8080';
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$path = $directory.'/demo-config.json';
if (! is_file($path)) {
    $tokens = [];
    foreach ([['client', 'tenant-a', 'client'], ['other', 'tenant-b', 'client'], ['coordinator', 'tenant-a', 'coordinator']] as [$producer,$tenant,$role]) {
        $tokens[] = (object) ['producer' => $producer, 'tenant' => $tenant, 'role' => $role, 'token' => bin2hex(random_bytes(32))];
    }
    file_put_contents($path, Store::encode((object) ['url' => $url, 'database' => $directory.'/pact.sqlite', 'tokens' => $tokens]));
    chmod($path, 0600);
}
$config = json_decode(file_get_contents($path));
new Store($config->database);
echo 'Reference configuration ready: '.$path.PHP_EOL;
