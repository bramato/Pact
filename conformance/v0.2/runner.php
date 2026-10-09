<?php

declare(strict_types=1);
use Pact\PactError;

require __DIR__.'/../../sdk/php/vendor/autoload.php';
$pact = new Pact\Pact;
$vectors = json_decode(file_get_contents(__DIR__.'/vectors.json'), false, 512, JSON_THROW_ON_ERROR);
$results = [];
foreach ($vectors as $v) {
    try {
        $args = $v->args;
        if ($v->op === 'contractData') {
            $args[] = __DIR__.'/../../examples/v0.2/schema-registry.json';
            $pact->contractData(...$args);
            $value = 'valid';
        } elseif ($v->op === 'parse') {
            $value = Pact\Pact::parse(...$args);
        } elseif ($v->op === 'canonical') {
            $value = Pact\Pact::canonical(...$args);
        } else {
            if ($v->op === 'answers') {
                $args[2]->registryPath = __DIR__.'/../../examples/v0.2/schema-registry.json';
            } $value = $pact->{$v->op}(...$args);
        }
        $results[] = ['id' => $v->id, 'value' => $value];
    } catch (PactError $e) {
        $results[] = ['id' => $v->id, 'error' => $e->errorCode];
    } catch (Throwable $e) {
        $results[] = ['id' => $v->id, 'error' => 'IMPLEMENTATION_ERROR', 'message' => $e->getMessage()];
    }
}
echo json_encode($results, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), PHP_EOL;
