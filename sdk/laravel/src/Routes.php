<?php

declare(strict_types=1);

namespace PactLaravel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Pact\HttpBinding;

final class Routes
{
    public static function register(Router $router, HttpBinding $binding): void
    {
        $handler = static function (Request $request) use ($binding): JsonResponse {
            $headers = [];
            foreach ($request->headers->all() as $key => $values) {
                $headers[$key] = $values[0] ?? '';
            }
            [$status,$responseHeaders,$body] = $binding->handle($request->method(), '/'.$request->path(), $headers, $request->getContent());

            return new JsonResponse($body, $status, $responseHeaders + ['X-PACT-Adapter' => 'Illuminate 12']);
        };
        foreach (['', 'agents/extract/', 'agents/review/', 'agents/assemble/'] as $prefix) {
            $router->post('/'.$prefix.'a2a', $handler);
            $router->get('/'.$prefix.'.well-known/agent-card.json', $handler);
        }
        $router->post('/events', $handler);
    }
}
