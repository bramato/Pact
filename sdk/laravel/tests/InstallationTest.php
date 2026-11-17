<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use PactLaravel\Support\Installation;
use RuntimeException;

final class InstallationTest extends TestCase
{
    public function test_loading_installation_preserves_host_tenant_selection_middleware(): void
    {
        (new Installation)->install('client', ['url' => 'https://client.example']);
        config(['pact.auth.middleware' => ['tenant.select']]);
        (new \PactLaravel\PactServiceProvider($this->app))->register();
        self::assertSame(['tenant.select'], config('pact.auth.middleware'));
        self::assertSame('web', config('pact.auth.guard'));
    }

    public function test_master_installation_is_persistent_and_repeatable(): void
    {
        $installer = new Installation;
        $first = $installer->install('master', ['url' => 'https://master.example']);
        $private = file_get_contents(storage_path('app/pact/private.pem'));
        $public = file_get_contents(storage_path('app/pact/public.pem'));
        self::assertSame($first, Installation::load());
        self::assertNotEmpty($first['product_id']);
        self::assertSame($first, $installer->install('master', ['url' => 'https://changed.example']));
        self::assertSame($private, file_get_contents(storage_path('app/pact/private.pem')));
        self::assertSame($public, file_get_contents(storage_path('app/pact/public.pem')));
        self::assertFileDoesNotExist(storage_path('oauth-private.key'));
    }

    public function test_client_preserves_both_pact_and_passport_keys_on_reinstall(): void
    {
        $installer = new Installation;
        $first = $installer->install('client', ['url' => 'https://client.example']);
        $paths = ['app/pact/private.pem', 'app/pact/public.pem', 'oauth-private.key', 'oauth-public.key'];
        $keys = array_map(fn ($path) => file_get_contents(storage_path($path)), $paths);
        self::assertSame($first, $installer->install('client', []));
        self::assertSame($keys, array_map(fn ($path) => file_get_contents(storage_path($path)), $paths));
    }

    public function test_role_cannot_be_changed_by_reinstall(): void
    {
        $installer = new Installation;
        $first = $installer->install('master', ['url' => 'https://master.example']);
        try {
            $installer->install('client', ['url' => 'https://client.example']);
            self::fail('Changing installation role should fail.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('role', $e->getMessage());
        }
        self::assertSame($first, Installation::load());
    }

    public function test_incomplete_existing_key_pair_is_not_overwritten(): void
    {
        mkdir(storage_path('app/pact'), 0700, true);
        file_put_contents(storage_path('app/pact/private.pem'), 'existing-key');
        try {
            (new Installation)->install('master', ['url' => 'https://master.example']);
            self::fail('Incomplete key pair should fail.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Incomplete key pair', $e->getMessage());
        }
        self::assertSame('existing-key', file_get_contents(storage_path('app/pact/private.pem')));
        self::assertFileDoesNotExist(Installation::path());
    }
}
