<?php

declare(strict_types=1);

namespace PactLaravel\Console;

use Illuminate\Console\Command;
use PactLaravel\OAuth\ConnectionService;

final class DisconnectCommand extends Command
{
    protected $signature = 'pact:disconnect {connection} {--user=}';

    protected $description = 'Revoke a user connection and queue remote revocation';

    public function handle(ConnectionService $connections): int
    {
        try {
            if (! $this->option('user')) {
                throw new \InvalidArgumentException('--user is required.');
            }
            $connections->disconnect($this->argument('connection'), (string) $this->option('user'));
            $this->info('Connection revoked; remote confirmation is processed by pact:work.');

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
