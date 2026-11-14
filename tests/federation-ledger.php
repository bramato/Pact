<?php

declare(strict_types=1);

require __DIR__.'/../sdk/php/vendor/autoload.php';

use Pact\Federation\SqliteLedger;

$file = tempnam(sys_get_temp_dir(), 'pact-ledger-');
try {
    $ledger = new SqliteLedger(new PDO('sqlite:'.$file));
    if (! $ledger->append('f', 'alice', '1', 'https://peer/a2a', '{"x":1}')) throw new RuntimeException('first append');
    if ($ledger->append('f', 'alice', '1', 'https://peer/a2a', '{"x":1}')) throw new RuntimeException('replay');
    try {
        $ledger->append('f', 'alice', '1', 'https://peer/a2a', '{"x":2}');
        throw new LogicException('conflict accepted');
    } catch (RuntimeException $e) {
        if ($e instanceof LogicException) throw $e;
    }
    unset($ledger);
    $ledger = new SqliteLedger(new PDO('sqlite:'.$file));
    if (count($ledger->pending()) !== 1) throw new RuntimeException('outbox did not survive restart');
    $ledger->receipt('f', 'alice', '1', 'accepted');
    $ledger->receipt('f', 'alice', '1', 'accepted');
    if ($ledger->pending() !== []) throw new RuntimeException('receipt did not remove pending delivery');
    try {
        $ledger->receipt('f', 'alice', '1', 'different');
        throw new LogicException('conflicting receipt accepted');
    } catch (RuntimeException $e) {
        if ($e instanceof LogicException) throw $e;
    }
    echo "Federation ledger passed\n";
} finally {
    unlink($file);
}
