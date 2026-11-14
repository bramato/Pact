<?php

declare(strict_types=1);

require getenv('PACT_TEST_AUTOLOAD') ?: __DIR__.'/../reference/vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Pact\HttpBinding;
use PactLaravel\Routes;

$container = new Container;
Container::setInstance($container);
$container->bind(HttpBinding::class, fn () => new class implements HttpBinding {
    public function handle(string $method, string $path, array $headers, string $raw): array
    {
        return [200, [], ['path' => $path, 'raw' => $raw]];
    }
});
$container->instance(\Illuminate\Routing\Contracts\CallableDispatcher::class, new \Illuminate\Routing\CallableDispatcher($container));
$router = new Router(new Dispatcher($container), $container);
Routes::register($router, null, ['prefixes' => ['agents/custom'], 'events' => 'custom-events']);
$request = Request::create('/agents/custom/a2a', 'POST', [], [], [], [], '{}');
$container->instance(Request::class, $request);
$response = $router->dispatch($request);
if ($response->getStatusCode() !== 200 || $response->getData()->path !== '/agents/custom/a2a') throw new RuntimeException('Configured route failed');
if ($router->getRoutes()->match(Request::create('/custom-events', 'POST'))->uri() !== 'custom-events') throw new RuntimeException('Events route failed');
echo "Laravel adapter passed\n";
