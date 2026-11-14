<?php

declare(strict_types=1);
require __DIR__.'/../reference/vendor/autoload.php';
function config_path(string $path): string { return '/tmp/config/'.$path; }

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Pact\HttpBinding;
use PactLaravel\PactServiceProvider;

$composer = json_decode(file_get_contents(__DIR__.'/../sdk/laravel/composer.json'), true);
if (! in_array(PactServiceProvider::class, $composer['extra']['laravel']['providers'], true)) throw new LogicException('Provider discovery missing');
$container = new Container; Container::setInstance($container);
$container->instance(\Illuminate\Routing\Contracts\CallableDispatcher::class, new \Illuminate\Routing\CallableDispatcher($container));
$config = new class {
    private array $values = ['pact' => ['prefixes' => ['agents/one'], 'middleware' => ['probe']]];
    public function get(string $key, mixed $default = null): mixed {
        $value = $this->values;
        foreach (explode('.', $key) as $part) { if (! is_array($value) || ! array_key_exists($part, $value)) return $default; $value = $value[$part]; }
        return $value;
    }
    public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
};
$container->instance('config', $config);
$router = new Router(new Dispatcher($container), $container);
$router->aliasMiddleware('probe', ProbeMiddleware::class);
$container->instance('router', $router);
$container->bind(HttpBinding::class, fn () => new class implements HttpBinding {
    public function handle(string $method, string $path, array $headers, string $raw): array { return [200, [], ['path' => $path]]; }
});
$provider = new PactServiceProvider($container); $provider->register(); $provider->boot();
$request = Request::create('/agents/one/a2a', 'POST'); $container->instance(Request::class, $request);
$response = $router->dispatch($request);
if ($response->getData()->path !== '/agents/one/a2a' || $response->headers->get('X-Probe') !== 'yes') throw new LogicException('Provider routes/middleware failed');
echo "Laravel provider passed\n";

final class ProbeMiddleware {
    public function handle(Request $request, Closure $next): mixed { $response = $next($request); $response->headers->set('X-Probe', 'yes'); return $response; }
}
