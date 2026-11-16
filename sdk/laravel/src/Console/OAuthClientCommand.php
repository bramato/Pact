<?php

declare(strict_types=1);

namespace PactLaravel\Console;

use Illuminate\Console\Command;
use PactLaravel\OAuth\ConnectionService;

final class OAuthClientCommand extends Command
{
    protected $signature = 'pact:oauth-client {--master=}';

    protected $description = 'Register a master callback as a public PKCE OAuth client';

    public function handle(ConnectionService $connections): int
    {
        try {
            if (! $this->option('master')) {
                throw new \InvalidArgumentException('--master is required.');
            }
            $client = $connections->registerMaster($this->option('master'));
            $this->info('OAuth client ID: '.$client->oauth_client_id);
            $this->line('Callback: '.$client->callback_url);

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
