<?php

declare(strict_types=1);

namespace PactLaravel\OAuth;

use League\OAuth2\Server\ResourceServer;
use Pact\Federation\Policy;
use PactLaravel\Runtime\TaskService;
use Psr\Http\Message\ServerRequestInterface;

final class SubjectController
{
    public function __invoke(ServerRequestInterface $request, ResourceServer $server, TaskService $tasks): mixed
    {
        $identity = Access::authenticate($request, $server);
        $product = (string) config('pact.product_id');
        abort_if($product === '', 503);

        return response()->json(['product_id' => $product, 'user_id' => $identity['user_id'], 'agent_id' => Policy::externalAgentId($product, $identity['user_id']), 'manifest' => $tasks->manifest($identity['user_id'])]);
    }
}
