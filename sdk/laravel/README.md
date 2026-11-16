# PACT Laravel master and client

Install one package, choose `master` or `client`, and link users' agents through OAuth. Masters coordinate personal federations; clients execute work using their application's agent. The package includes persistence, a worker, Passport authorization with PKCE, and Laravel MCP/A2A endpoints.

```sh
php artisan pact:install --role=master --no-interaction
# In a different Laravel application:
php artisan pact:install --role=client --no-interaction
php artisan pact:work
```

Install with `composer require pact-protocol/laravel:^0.2`; Composer resolves the Core dependency. Read the [installation and operations guide](https://github.com/bramato/Pact/blob/main/docs/laravel-installation.md) for registration, OAuth linking, agent interfaces, diagnostics and recovery. Requires Laravel 12.41.1+ or 13 and compatible PHP. The default agent is a deterministic connectivity check; application AI capabilities require a configured handler and pinned contract.

## Existing HTTP bindings

`pact-protocol/laravel` adapts a `Pact\HttpBinding` implementation to Laravel 12 and 13 routing. It keeps framework dependencies out of the PHP protocol core. The reference demo executes these routes using Illuminate's real Router, Request and JsonResponse components; it is not a full generated Laravel application.

Use the [reference Composer project](https://github.com/bramato/Pact/blob/main/reference/composer.json) until these packages are published. In an existing application, bind your durable authenticated transport implementation to `Pact\HttpBinding`, and the auto-discovered provider registers the routes. Configure `pact.prefixes`, `pact.events`, and `pact.middleware` in the published configuration. For manual registration:

```php
use Illuminate\Routing\Router;
use Pact\HttpBinding;
use PactLaravel\Routes;

public function boot(Router $router, HttpBinding $binding): void
{
    Routes::register($router, $binding);
}
```

The provider defaults to `/a2a`, `/events`, and Agent Card discovery; add agent prefixes in configuration. Manual registration without options retains the reference contributor paths. The binding owns authentication, tenant authorization, error mapping and atomic storage. Mount these as stateless API routes with the application's authentication/limits policy. The reference binding performs its bearer authentication itself. It should not be mounted under browser session/CSRF middleware without an intentional API integration.

[Service](https://github.com/bramato/Pact/blob/main/reference/src/Service.php) implements the interface; [the front controller](https://github.com/bramato/Pact/blob/main/public/index.php) shows container setup. [Quickstart](https://github.com/bramato/Pact/blob/main/docs/quickstart.md) installs locked dependencies and reaches an actual HTTP exchange through these routes. Other bindings have not been verified by this adapter.
