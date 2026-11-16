<?php

declare(strict_types=1);

namespace PactLaravel\Console;

use Illuminate\Console\Command;
use PactLaravel\OAuth\ConnectionService;

final class ApplicationCommand extends Command
{
    protected $signature = 'pact:application:add {--url=} {--client-id=} {--name=}';

    protected $description = 'Register a connectable application on the PACT master';

    public function handle(ConnectionService $connections): int
    {
        try {
            if (! $this->option('url') || ! $this->option('client-id')) {
                throw new \InvalidArgumentException('--url and --client-id are required.');
            }
            $app = $connections->addApplication($this->option('url'), $this->option('client-id'), $this->option('name'));
            $this->info('Application registered: '.$app->name.' ('.$app->id.')');

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
