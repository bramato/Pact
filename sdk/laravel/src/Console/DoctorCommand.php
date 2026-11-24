<?php

declare(strict_types=1);

namespace PactLaravel\Console;

use Illuminate\Console\Command;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Heartbeat;
use PactLaravel\Models\Outbox;
use PactLaravel\Runtime\ExecutionQueue;
use PactLaravel\Support\DatabaseInstallation;
use PactLaravel\Support\UrlPolicy;

final class DoctorCommand extends Command
{
    protected $signature = 'pact:doctor';

    protected $description = 'Check PACT configuration, schema, credentials and worker health without revealing secrets';

    public function handle(): int
    {
        $failed = false;
        $check = function (string $name, callable $probe) use (&$failed): void {
            try {
                if ($probe() === false) {
                    throw new \RuntimeException('Check failed');
                }
                $this->info('OK '.$name);
            } catch (\Throwable) {
                $this->error('FAIL '.$name);
                $failed = true;
            }
        };
        $check('installed role', fn () => in_array(config('pact.role'), ['master', 'client'], true));
        $check('public URL policy', fn () => UrlPolicy::assert((string) config('pact.url')) !== '');
        $check('encryption key', fn () => (bool) config('app.key'));
        $check('signing keys', fn () => is_readable(storage_path('app/pact/private.pem')) && is_readable(storage_path('app/pact/public.pem')));
        if (DatabaseInstallation::enabled()) {
            $check('encrypted database installation', fn () => (new DatabaseInstallation)->load() !== null);
        }
        $check('package migrations', function () {
            $schema = $this->laravel['db']->connection(config('pact.database_connection'))->getSchemaBuilder();
            foreach (['pact_applications', 'pact_connections', 'pact_federations', 'pact_members', 'pact_tasks', 'pact_outbox', 'pact_receipts', 'pact_oauth_states', 'pact_oauth_clients', 'pact_link_requests', 'pact_event_receipts', 'pact_heartbeats'] as $table) {
                if (! $schema->hasTable($table)) {
                    return false;
                }
            }

            return true;
        });
        $check('authentication provider', fn () => (bool) config('auth.providers.'.config('pact.auth.provider')));
        $check('runtime routes', fn () => collect($this->laravel['router']->getRoutes()->getRoutes())->contains(fn ($route) => str_ends_with($route->uri(), '/agents/{agent}/a2a')));
        $check('connection credentials', function () {
            foreach (Connection::query()->where('status', 'active')->get() as $connection) {
                if (! is_array($connection->credentials) || $connection->credentials === []) {
                    return false;
                }
            }

            return true;
        });
        $check('outbox deliveries', fn () => ! Outbox::query()->where('state', 'failed')->exists());
        $check('Laravel execution queue configuration', fn () => ExecutionQueue::fromConfig());
        $check('worker heartbeat (last 2 minutes)', fn () => Heartbeat::query()->whereKey('worker')->where('updated_at', '>=', now()->subMinutes(2))->exists());

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
