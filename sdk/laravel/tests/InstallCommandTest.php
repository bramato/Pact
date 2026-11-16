<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use PactLaravel\PactServiceProvider;
use PactLaravel\Support\Installation;

final class InstallCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        mkdir($this->isolatedStorage.'/config');
        mkdir($this->isolatedStorage.'/bootstrap/cache', 0700, true);
        $this->app->useBootstrapPath($this->isolatedStorage.'/bootstrap');
        mkdir($this->isolatedStorage.'/database/migrations', 0700, true);
        $this->app->useConfigPath($this->isolatedStorage.'/config');
        $this->app->useDatabasePath($this->isolatedStorage.'/database');
        file_put_contents($this->isolatedStorage.'/database/migrations/2026_01_01_000000_host.php', <<<'PHP'
<?php
return new class extends \Illuminate\Database\Migrations\Migration {
    public function up(): void { \Illuminate\Support\Facades\Schema::create('host_unrelated', fn ($t) => $t->id()); }
    public function down(): void { \Illuminate\Support\Facades\Schema::dropIfExists('host_unrelated'); }
};
PHP);
        $this->app->register(PactServiceProvider::class);
    }

    public function test_master_command_installs_only_package_migrations_and_is_repeatable(): void
    {
        $arguments = ['--role' => 'master', '--url' => 'https://master.example', '--no-interaction' => true];
        self::assertSame(0, Artisan::call('pact:install', $arguments), Artisan::output());
        $first = Installation::load();
        $key = file_get_contents(storage_path('app/pact/private.pem'));
        self::assertTrue(Schema::hasTable('pact_tasks'));
        self::assertFalse(Schema::hasTable('host_unrelated'));
        self::assertFalse(Schema::hasTable('oauth_clients'));
        self::assertSame(0, Artisan::call('pact:install', $arguments), Artisan::output());
        self::assertSame($first, Installation::load());
        self::assertSame($key, file_get_contents(storage_path('app/pact/private.pem')));
        self::assertSame(1, Artisan::call('pact:install', ['--role' => 'client', '--no-interaction' => true]));
        self::assertSame($first, Installation::load());
    }

    public function test_client_command_installs_passport_without_running_host_migrations(): void
    {
        $arguments = ['--role' => 'client', '--url' => 'https://client.example', '--no-interaction' => true];
        self::assertSame(0, Artisan::call('pact:install', $arguments), Artisan::output());
        self::assertTrue(Schema::hasTable('pact_tasks'));
        self::assertTrue(Schema::hasTable('oauth_clients'));
        self::assertFalse(Schema::hasTable('host_unrelated'));
        $first = Installation::load();
        $key = file_get_contents(storage_path('oauth-private.key'));
        self::assertSame(0, Artisan::call('pact:install', $arguments), Artisan::output());
        self::assertSame($first, Installation::load());
        self::assertSame($key, file_get_contents(storage_path('oauth-private.key')));
    }

    public function test_cached_configuration_refuses_install_without_writing_keys_or_migrating(): void
    {
        file_put_contents($this->app->getCachedConfigPath(), '<?php return [];');
        $this->app->instance('config_loaded_from_cache', true);
        self::assertSame(1, Artisan::call('pact:install', ['--role' => 'master', '--url' => 'https://master.example', '--no-interaction' => true]));
        self::assertStringContainsString('configuration cache', Artisan::output());
        self::assertFileDoesNotExist(Installation::path());
        self::assertFileDoesNotExist(storage_path('app/pact/private.pem'));
        self::assertFalse(Schema::hasTable('pact_tasks'));
    }

    public function test_install_clears_existing_route_cache_after_registering_package_migrations(): void
    {
        $cache = $this->app->getCachedRoutesPath();
        file_put_contents($cache, '<?php return [];');
        self::assertFileExists($cache);
        self::assertSame(0, Artisan::call('pact:install', ['--role' => 'master', '--url' => 'https://master.example', '--no-interaction' => true]), Artisan::output());
        self::assertFileDoesNotExist($cache);
        self::assertTrue(Schema::hasTable('pact_tasks'));
    }
}
