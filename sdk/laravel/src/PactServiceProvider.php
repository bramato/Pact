<?php

declare(strict_types=1);

namespace PactLaravel;

use Illuminate\Support\ServiceProvider;

final class PactServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pact.php', 'pact');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/pact.php' => config_path('pact.php')], 'pact-config');
        if ($this->app['config']->get('pact.enabled', true)) {
            Routes::register($this->app['router'], null, $this->app['config']->get('pact', []));
        }
    }
}
