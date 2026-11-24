<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

    public function test_install_uses_cached_configuration_and_preserves_it(): void
    {
        $cache = $this->app->getCachedConfigPath();
        file_put_contents($cache, '<?php return [];');
        $this->app->instance('config_loaded_from_cache', true);
        self::assertSame(0, Artisan::call('pact:install', ['--role' => 'master', '--no-interaction' => true]), Artisan::output());
        self::assertSame('https://master.example', Installation::load()['url']);
        self::assertFileExists($cache);
        self::assertTrue(Schema::hasTable('pact_tasks'));
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

    public function test_install_refuses_to_create_a_replacement_identity_from_an_existing_database(): void
    {
        $this->migratePact();
        self::assertSame(1, Artisan::call('pact:install', ['--role' => 'master', '--no-interaction' => true]));
        self::assertStringContainsString('Restore the original state', Artisan::output());
        self::assertFileDoesNotExist(Installation::path());
        self::assertFileDoesNotExist(storage_path('app/pact/private.pem'));
    }

    public function test_install_uses_the_configured_role_and_guard_provider_for_herd(): void
    {
        $this->app->instance('env', 'local');
        config([
            'app.url' => 'http://pact.test',
            'pact.url' => 'http://pact.test',
            'pact.role' => 'master',
            'auth.guards.team' => ['driver' => 'session', 'provider' => 'staff'],
            'auth.providers.staff' => ['driver' => 'eloquent', 'model' => 'App\\Models\\User'],
        ]);
        self::assertSame(0, Artisan::call('pact:install', ['--guard' => 'team', '--no-interaction' => true]), Artisan::output());
        self::assertSame(['guard' => 'team', 'provider' => 'staff'], Installation::load()['auth']);
        self::assertSame(['pact.test'], Installation::load()['local_hosts']);
    }

    public function test_existing_file_installation_can_be_adopted_and_restored_from_the_database(): void
    {
        self::assertSame(0, Artisan::call('pact:install', ['--role' => 'client', '--no-interaction' => true]), Artisan::output());
        $original = Installation::load();
        $paths = ['app/pact/private.pem', 'app/pact/public.pem', 'oauth-private.key', 'oauth-public.key'];
        $keys = array_map(fn ($path) => file_get_contents(storage_path($path)), $paths);
        self::assertFalse(Schema::hasTable('pact_installation_state'));

        config(['pact.state_store' => 'database']);
        self::assertSame(0, Artisan::call('pact:install', ['--no-interaction' => true]), Artisan::output());
        $encrypted = DB::table('pact_installation_state')->value('payload');
        self::assertIsString($encrypted);
        self::assertStringNotContainsString($original['product_id'], $encrypted);
        self::assertStringNotContainsString('PRIVATE KEY', $encrypted);
        self::assertSame(0, Artisan::call('pact:install', ['--no-interaction' => true]), Artisan::output());
        self::assertSame($encrypted, DB::table('pact_installation_state')->value('payload'));

        unlink(Installation::path());
        foreach ($paths as $path) {
            unlink(storage_path($path));
        }
        self::assertSame($original, (new Installation)->restore());
        self::assertSame($original, Installation::load());
        self::assertSame($keys, array_map(fn ($path) => file_get_contents(storage_path($path)), $paths));

        file_put_contents(storage_path('app/pact/public.pem'), 'conflicting key');
        $this->expectExceptionMessage('Local PACT or Passport signing keys differ');
        (new Installation)->restore();
    }

    public function test_database_installation_preserves_identity_when_the_encryption_key_changes(): void
    {
        config(['pact.state_store' => 'database']);
        self::assertSame(0, Artisan::call('pact:install', ['--role' => 'master', '--no-interaction' => true]), Artisan::output());
        $encrypted = DB::table('pact_installation_state')->value('payload');
        unlink(Installation::path());
        config(['app.key' => 'base64:'.base64_encode(str_repeat('z', 32))]);
        $this->app->forgetInstance('encrypter');

        try {
            (new Installation)->restore();
            self::fail('Restoring with another encryption key should fail.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('original APP_KEY', $error->getMessage());
        }
        self::assertFileDoesNotExist(Installation::path());
        self::assertSame($encrypted, DB::table('pact_installation_state')->value('payload'));
    }
}
