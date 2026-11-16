<?php

declare(strict_types=1);

namespace PactLaravel\Console;

use Illuminate\Console\Command;
use PactLaravel\Contracts\IdentityResolver;
use PactLaravel\OAuth\ConnectionService;

final class ConnectCommand extends Command
{
    protected $signature = 'pact:connect {application} {--user=}';

    protected $description = 'Print the browser URL for a user-authorized OAuth connection';

    public function handle(ConnectionService $connections, IdentityResolver $identities): int
    {
        try {
            $user = $this->option('user');
            if (! $user || ! $identities->find((string) $user)) {
                throw new \InvalidArgumentException('--user must identify an existing user.');
            }
            $this->line($connections->connectUrl($this->argument('application'), (string) $user));
            $this->info('Open this URL while signed in to the master as that user.');

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
