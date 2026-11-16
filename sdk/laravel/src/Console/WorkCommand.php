<?php

declare(strict_types=1);

namespace PactLaravel\Console;

use Illuminate\Console\Command;
use PactLaravel\Runtime\Worker;

final class WorkCommand extends Command
{
    protected $signature = 'pact:work {--once} {--max-jobs=0}';

    protected $description = 'Process durable PACT execution and delivery work';

    public function handle(Worker $worker): int
    {
        if (! config('pact.role')) {
            $this->error('Install PACT before starting its worker.');

            return self::FAILURE;
        }
        $jobs = 0;
        do {
            $worked = $worker->workOnce();
            if ($worked) {
                $jobs++;
            } else {
                usleep(max(10, (int) config('pact.worker.poll_ms', 250)) * 1000);
            }
        } while (! $this->option('once') && (! (int) $this->option('max-jobs') || $jobs < (int) $this->option('max-jobs')));

        return self::SUCCESS;
    }
}
