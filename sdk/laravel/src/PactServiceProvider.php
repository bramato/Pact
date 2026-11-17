<?php

declare(strict_types=1);

namespace PactLaravel;

use Illuminate\Support\ServiceProvider;
use Pact\HttpBinding;
use PactLaravel\Contracts\AgentHandler;
use PactLaravel\Contracts\ConversationReader;
use PactLaravel\Contracts\IdentityResolver;
use PactLaravel\Support\DefaultIdentityResolver;
use PactLaravel\Support\DenyConversationReader;
use PactLaravel\Support\Installation;

final class PactServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pact.php', 'pact');
        if (! $this->app['config']->get('pact.url')) {
            $this->app['config']->set('pact.url', $this->app['config']->get('app.url'));
        }
        if (method_exists($this->app, 'storagePath')) {
            foreach (Installation::load() as $key => $value) {
                if ($key === 'auth') {
                    // Persisted login settings must not hide the host's tenant middleware.
                    $value = array_replace($this->app['config']->get('pact.auth', []), $value);
                }
                $this->app['config']->set('pact.'.$key, $value);
            }
            $this->app->bindIf(IdentityResolver::class, DefaultIdentityResolver::class);
            $this->app->bindIf(ConversationReader::class, function ($app) {
                return $app->make($app['config']->get('pact.conversation_reader') ?: DenyConversationReader::class);
            });
            if ($handler = $this->app['config']->get('pact.handler')) {
                $this->app->bindIf(AgentHandler::class, $handler);
            }
        }
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/pact.php' => config_path('pact.php')], 'pact-config');
        if (method_exists($this->app, 'storagePath')) {
            $this->loadViewsFrom(__DIR__.'/../resources/views', 'pact');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/pact')], 'pact-views');
            $this->commands([
                Console\InstallCommand::class, Console\WorkCommand::class, Console\DoctorCommand::class,
                Console\OAuthClientCommand::class, Console\ApplicationCommand::class,
                Console\ConnectCommand::class, Console\DisconnectCommand::class,
            ]);
            if ($this->app['config']->get('pact.enabled', true) && $this->app['config']->get('pact.role')) {
                OAuth\Routes::register($this->app['router']);
                Runtime\Routes::register($this->app['router']);

                return;
            }
            // Installing a package alone must not expose an unconfigured endpoint.
            if (! $this->app->bound(HttpBinding::class)) {
                return;
            }
        }
        if ($this->app['config']->get('pact.enabled', true)) {
            Routes::register($this->app['router'], null, $this->app['config']->get('pact', []));
        }
    }
}
