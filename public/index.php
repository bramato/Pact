<?php

declare(strict_types=1);
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\CallableDispatcher;
use Illuminate\Routing\Router;
use PactLaravel\Routes;
use PactReference\Service;
use PactReference\Store;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

ini_set('display_errors', '0');
try {
    require __DIR__.'/../reference/bootstrap.php';
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($path === '/' || $path === '/docs' || str_starts_with($path, '/docs/')) {
        require __DIR__.'/../reference/docs.php';
        exit;
    }
    $config = pact_config();
    $service = new Service(new Store($config->database), $config);
    if (is_file(__DIR__.'/../reference/vendor/autoload.php')) {
        require __DIR__.'/../reference/vendor/autoload.php';
        $container = new Container;
        Container::setInstance($container);
        $container->instance(Illuminate\Routing\Contracts\CallableDispatcher::class, new CallableDispatcher($container));
        $events = new Dispatcher($container);
        $router = new Router($events, $container);
        $request = Request::capture();
        $container->instance(Request::class, $request);
        $container->instance('request', $request);
        Routes::register($router, $service);
        $router->dispatch($request)->send();
        exit;
    }
    [$status,$headers,$body] = $service->handle($_SERVER['REQUEST_METHOD'], $path, getallheaders(), file_get_contents('php://input'));
    http_response_code($status);
    header('Content-Type: application/json');
    foreach ($headers as $key => $value) {
        header($key.': '.$value);
    }echo Store::encode($body);
} catch (HttpExceptionInterface $e) {
    http_response_code($e->getStatusCode());
    header('Content-Type: application/json');
    echo '{"error":"Route unavailable"}';
} catch (Throwable $e) {
    error_log('PACT front controller: '.get_class($e));
    http_response_code(500);
    header('Content-Type: application/json');
    echo '{"error":"Reference service unavailable; check local setup"}';
}
