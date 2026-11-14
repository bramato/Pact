<?php

declare(strict_types=1);

namespace PactLaravel;

use Illuminate\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Pact\HttpBinding;

final class Routes
{
    /** @param array{prefixes?: list<string>, middleware?: list<string>, events?: string} $options */
    public static function register(Router $router, ?HttpBinding $binding = null, array $options = []): void
    {
        $handler = static function (Request $request) use ($binding): JsonResponse {
            $headers = [];
            foreach ($request->headers->all() as $key => $values) {
                $headers[$key] = $values[0] ?? '';
            }
            $transport = $binding ?? Container::getInstance()->make(HttpBinding::class);
            [$status, $responseHeaders, $body] = $transport->handle($request->method(), '/'.$request->path(), $headers, $request->getContent());

            $version = \Composer\InstalledVersions::getVersion('illuminate/routing');
            $major = $version === null ? '' : ' '.explode('.', $version)[0];
            return new JsonResponse($body, $status, $responseHeaders + ['X-PACT-Adapter' => 'Illuminate'.$major]);
        };
        $router->group(['middleware' => $options['middleware'] ?? []], static function (Router $router) use ($handler, $options): void {
            foreach ($options['prefixes'] ?? ['', 'agents/extract', 'agents/review', 'agents/assemble'] as $prefix) {
                $prefix = trim($prefix, '/');
                $base = $prefix === '' ? '' : $prefix.'/';
                $router->post('/'.$base.'a2a', $handler);
                $router->get('/'.$base.'.well-known/agent-card.json', $handler);
            }
            $router->post('/'.trim($options['events'] ?? 'events', '/'), $handler);
        });
    }
}
