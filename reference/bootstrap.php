<?php

declare(strict_types=1);
require_once __DIR__.'/../sdk/php/vendor/autoload.php';
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'PactReference\\')) {
        require __DIR__.'/src/'.substr($class, strlen('PactReference\\')).'.php';
    }
});
function pact_config(): stdClass
{
    $path = getenv('PACT_CONFIG') ?: __DIR__.'/../storage/demo-config.json';
    if (! is_file($path)) {
        throw new RuntimeException('Run php reference/bin/setup.php first');
    }

    return json_decode(file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
}
