<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected string $isolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedStorage = sys_get_temp_dir().'/pact-tests-'.bin2hex(random_bytes(8));
        mkdir($this->isolatedStorage.'/app', 0700, true);
        $this->app->useStoragePath($this->isolatedStorage);
        Passport::loadKeysFrom($this->isolatedStorage);
        $this->app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $this->app['config']->set('pact', require __DIR__.'/../config/pact.php');
        $this->app['config']->set('pact.url', 'https://master.example');
        $this->app['config']->set('database.default', 'testing');
        $this->app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
    }

    protected function migratePact(): void
    {
        (require __DIR__.'/../database/migrations/2026_11_16_000001_create_pact_runtime.php')->up();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        if (isset($this->isolatedStorage) && is_dir($this->isolatedStorage)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->isolatedStorage, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->isolatedStorage);
        }
        parent::tearDown();
    }
}
