# PACT Laravel adapter

`pact-protocol/laravel` adapts a `Pact\HttpBinding` implementation to Laravel 12 routing. It keeps framework dependencies out of the PHP protocol core. The reference demo executes these routes using Illuminate's real Router, Request and JsonResponse components; it is not a full generated Laravel application.

Use the [reference Composer project](https://github.com/bramato/Pact/blob/main/reference/composer.json) until these packages are published. In an existing application, bind your durable authenticated transport implementation to `Pact\HttpBinding`, then register the routes from a service provider:

```php
use Illuminate\Routing\Router;
use Pact\HttpBinding;
use PactLaravel\Routes;

public function boot(Router $router, HttpBinding $binding): void
{
    Routes::register($router, $binding);
}
```

The adapter registers `/a2a`, `/events`, Agent Card discovery and the three reference contributor paths. The binding owns authentication, tenant authorization, error mapping and atomic storage. Mount these as stateless API routes with the application's authentication/limits policy. The reference binding performs its bearer authentication itself. It should not be mounted under browser session/CSRF middleware without an intentional API integration.

[Service](https://github.com/bramato/Pact/blob/main/reference/src/Service.php) implements the interface; [the front controller](https://github.com/bramato/Pact/blob/main/public/index.php) shows container setup. [Quickstart](https://github.com/bramato/Pact/blob/main/docs/quickstart.md) installs locked dependencies and reaches an actual HTTP exchange through these routes. Laravel 13 and other bindings have not been verified by this adapter.
