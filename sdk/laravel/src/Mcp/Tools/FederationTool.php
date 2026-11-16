<?php

declare(strict_types=1);

namespace PactLaravel\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Pact\Federation\McpDispatcher;
use PactLaravel\Mcp\Endpoint;

abstract class FederationTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        $definition = null;
        foreach (McpDispatcher::definitions() as $item) {
            if ($item['name'] === $this->name()) {
                $definition = $item['inputSchema'];
            }
        }
        $out = [];
        foreach ((array) ($definition['properties'] ?? []) as $key => $spec) {
            $value = ($spec['type'] ?? 'string') === 'object' ? $schema->object() : $schema->string();
            if (isset($spec['enum'])) {
                $value->enum($spec['enum']);
            }
            if (in_array($key, $definition['required'] ?? [], true)) {
                $value->required();
            }
            $out[$key] = $value;
        }

        return $out;
    }

    public function handle(Request $request): Response
    {
        $rpc = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $this->name(), 'arguments' => $request->all()]];
        $http = \Illuminate\Http\Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($rpc, JSON_THROW_ON_ERROR));
        $http->attributes->set('pact.connection', request()->attributes->get('pact.connection'));
        $http->attributes->set('pact.member_id', request()->attributes->get('pact.member_id'));
        $result = app(Endpoint::class)->handle($http)->getData(true)['result'] ?? [];
        $text = $result['content'][0]['text'] ?? 'Invalid tool response';

        return ($result['isError'] ?? false) ? Response::error($text) : Response::text($text);
    }
}
