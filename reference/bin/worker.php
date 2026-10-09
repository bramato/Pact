<?php

declare(strict_types=1);
use PactReference\Service;
use PactReference\Store;
use PactReference\Worker;

require __DIR__.'/../bootstrap.php';
$config = pact_config();
$worker = new Worker(new Service(new Store($config->database), $config));
if (($argv[1] ?? '') === '--once') {
    $worker->step();
    exit;
}
$watch = in_array('--watch', $argv, true);
$deadline = $watch ? INF : microtime(true) + 30;
$idle = 0;
do {
    if ($worker->step()) {
        $idle = 0;
    } else {
        usleep(100000);
        $idle++;
    }
} while (microtime(true) < $deadline && ($watch || $idle < 20));
