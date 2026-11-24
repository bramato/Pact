<?php

declare(strict_types=1);

namespace PactLaravel\Console;

use Illuminate\Console\Command;
use Laravel\Passport\Passport;
use PactLaravel\Runtime\ExecutionQueue;
use PactLaravel\Support\DatabaseInstallation;
use PactLaravel\Support\Installation;

final class InstallCommand extends Command
{
    protected $signature = 'pact:install {--role=} {--url=} {--guard=} {--provider=} {--connection=} {--allow-local-host=*}';

    protected $description = 'Install a durable PACT master or client without replacing existing credentials';

    public function handle(Installation $installation): int
    {
        try {
            $previous = Installation::load();
            $role = $this->option('role') ?: ($previous['role'] ?? config('pact.role'));
            if (! $role && $this->input->isInteractive()) {
                $role = $this->choice('PACT installation role', ['master', 'client']);
            }
            if (! $role) {
                throw new \RuntimeException('--role=master or --role=client is required in non-interactive mode.');
            }
            $local = array_merge(config('pact.local_hosts', []), $this->option('allow-local-host'));
            $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
            if ($this->laravel->environment('local') && str_ends_with($host, '.test')) {
                $local[] = $host;
            }
            config(['pact.local_hosts' => array_values(array_unique($local))]);
            $options = array_filter(['url' => $this->option('url'), 'guard' => $this->option('guard'), 'provider' => $this->option('provider'), 'database_connection' => $this->option('connection')], fn ($value) => $value !== null);
            // Validate the database before persisting a fresh installation.
            $connectionName = $previous === []
                ? $options['database_connection'] ?? config('pact.database_connection')
                : config('pact.database_connection');
            $connection = $this->laravel['db']->connection($connectionName);
            $connection->getPdo();
            if ($previous === [] && $connection->getSchemaBuilder()->hasTable('pact_federations')) {
                throw new \RuntimeException('PACT tables already exist without installation metadata. Restore the original state and signing keys before installing.');
            }
            ExecutionQueue::fromConfig();
            $installed = $installation->install((string) $role, $options);
            foreach ($installed as $key => $value) {
                if ($key === 'auth') {
                    $value = array_replace(config('pact.auth', []), $value);
                }
                config(['pact.'.$key => $value]);
            }
            $path = config_path('pact.php');
            if (! is_file($path) && is_writable(dirname($path))) {
                copy(__DIR__.'/../../config/pact.php', $path);
            }
            $paths = [realpath(__DIR__.'/../../database/migrations')];
            if ($installed['role'] === 'client') {
                $reflection = new \ReflectionClass(Passport::class);
                $passport = dirname($reflection->getFileName(), 2).'/database/migrations';
                // Already migrated Passport tables belong to the app; do not recreate them.
                if (! $this->laravel['db']->connection(config('passport.connection'))->getSchemaBuilder()->hasTable('oauth_clients')) {
                    $paths[] = $passport;
                }
            }
            $result = $this->call('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true]);
            if ($result !== 0) {
                return $result;
            }
            if ($this->callSilent('route:clear') !== self::SUCCESS || $this->laravel->routesAreCached()) {
                throw new \RuntimeException('PACT is installed but the route cache could not be cleared.');
            }
            $this->info('PACT '.$installed['role'].' installed. Product identity: '.$installed['product_id']);
            if (DatabaseInstallation::enabled()) {
                $this->info('PACT installation and file-based keys are encrypted in the application database.');
            }
            $this->line('Run php artisan pact:doctor and keep php artisan pact:work running.');

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
