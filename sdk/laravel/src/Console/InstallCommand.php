<?php

declare(strict_types=1);

namespace PactLaravel\Console;

use Illuminate\Console\Command;
use Laravel\Passport\Passport;
use PactLaravel\Support\Installation;

final class InstallCommand extends Command
{
    protected $signature = 'pact:install {--role=} {--url=} {--guard=} {--provider=} {--connection=} {--allow-local-host=*}';

    protected $description = 'Install a durable PACT master or client without replacing existing credentials';

    public function handle(Installation $installation): int
    {
        try {
            if ($this->laravel->configurationIsCached()) {
                $this->error('Clear the configuration cache before installing; rebuild it after installation.');

                return self::FAILURE;
            }
            $previous = Installation::load();
            $role = $this->option('role') ?: ($previous['role'] ?? null);
            if (! $role && $this->input->isInteractive()) {
                $role = $this->choice('PACT installation role', ['master', 'client']);
            }
            if (! $role) {
                throw new \RuntimeException('--role=master or --role=client is required in non-interactive mode.');
            }
            $local = array_values(array_unique(array_merge(config('pact.local_hosts', []), $this->option('allow-local-host'))));
            config(['pact.local_hosts' => $local]);
            $options = array_filter(['url' => $this->option('url'), 'guard' => $this->option('guard'), 'provider' => $this->option('provider'), 'database_connection' => $this->option('connection')], fn ($value) => $value !== null);
            // Validate the database before persisting a fresh installation.
            $this->laravel['db']->connection($options['database_connection'] ?? config('pact.database_connection'))->getPdo();
            $installed = $installation->install((string) $role, $options);
            foreach ($installed as $key => $value) {
                config(['pact.'.$key => $value]);
            }
            $path = config_path('pact.php');
            if (! is_file($path)) {
                copy(__DIR__.'/../../config/pact.php', $path);
            }
            $paths = [realpath(__DIR__.'/../../database/migrations')];
            if ($installed['role'] === 'client') {
                $reflection = new \ReflectionClass(Passport::class);
                $passport = dirname($reflection->getFileName(), 2).'/database/migrations';
                // Already migrated Passport tables belong to the app; do not recreate them.
                if (! $this->laravel['db']->connection()->getSchemaBuilder()->hasTable('oauth_clients')) {
                    $paths[] = $passport;
                }
            }
            $result = $this->call('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true]);
            if ($result !== 0) {
                return $result;
            }
            $this->callSilent('route:clear');
            $this->info('PACT '.$installed['role'].' installed. Product identity: '.$installed['product_id']);
            $this->line('Run php artisan pact:doctor and keep php artisan pact:work running.');

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
