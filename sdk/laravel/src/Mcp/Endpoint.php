<?php

declare(strict_types=1);

namespace PactLaravel\Mcp;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Pact\Federation\DelegationState;
use Pact\Federation\McpDispatcher;
use Pact\PactError;
use PactLaravel\Models\Member;
use PactLaravel\Models\Receipt;
use PactLaravel\Models\Task;
use PactLaravel\Runtime\Store;
use PactLaravel\Support\ResultContracts;

final class Endpoint
{
    public function handle(Request $request)
    {
        $rpc = $request->json()->all();
        $id = $rpc['id'] ?? null;
        $method = $rpc['method'] ?? '';
        if ($method === 'initialize') {
            $result = ['protocolVersion' => '2025-03-26', 'capabilities' => ['tools' => (object) []], 'serverInfo' => ['name' => 'pact-federation', 'version' => '0.1.0']];
        } elseif ($method === 'tools/list') {
            $result = ['tools' => array_values(array_filter(McpDispatcher::definitions(), fn ($tool) => in_array($tool['name'], ['list_members', 'get_manifest', 'get_task', 'read_conversation', 'reserve_step', 'send_message', 'request_delegation'], true)))];
        } elseif ($method === 'tools/call') {
            $connection = $request->attributes->get('pact.connection');
            $actor = $request->attributes->get('pact.member_id');
            $name = $rpc['params']['name'] ?? '';
            $args = $rpc['params']['arguments'] ?? [];
            try {
                $definition = null;
                foreach (McpDispatcher::definitions() as $item) {
                    if ($item['name'] === $name) {
                        $definition = $item['inputSchema'];
                    }
                }
                if (! $definition || ! is_array($args)) {
                    throw new \DomainException('Invalid tool arguments');
                }
                $properties = (array) $definition['properties'];
                if (array_diff(array_keys($args), array_keys($properties)) || array_diff($definition['required'], array_keys($args))) {
                    throw new \DomainException('Invalid tool arguments');
                }
                foreach ($args as $key => $value) {
                    $spec = $properties[$key];
                    if (($spec['type'] === 'string' && (! is_string($value) || $value === '')) || ($spec['type'] === 'object' && ! is_array($value)) || (isset($spec['enum']) && ! in_array($value, $spec['enum'], true))) {
                        throw new \DomainException('Invalid tool argument '.$key);
                    }
                }
                if ($name === 'list_members') {
                    $value = app(Store::class)->roster($connection->federation_id)->members;
                } elseif ($name === 'get_manifest') {
                    $member = Member::query()->where('federation_id', $connection->federation_id)->where('member_id', $args['member_id'] ?? '')->where('active', true)->firstOrFail();
                    $value = (object) ($member->manifest ?? []);
                } elseif (in_array($name, ['send_message', 'request_delegation'], true)) {
                    $value = app(Coordinator::class)->dispatch($connection, $actor, $name, $args);
                } elseif ($name === 'get_task') {
                    $task = app(Coordinator::class)->taskForActor((string) $connection->federation_id, $actor, (string) ($args['task_id'] ?? ''));
                    $value = $task->value;
                } elseif ($name === 'read_conversation') {
                    $reader = config('pact.conversation_reader');
                    if (! $reader) {
                        throw new \DomainException('Conversation reading is disabled');
                    }
                    $value = app($reader)->read((string) $connection->user_id, $actor, (string) ($args['conversation_id'] ?? ''));
                } elseif ($name === 'reserve_step') {
                    $value = app(Store::class)->atomic(function () use ($connection, $actor, $args) {
                        $task = app(Coordinator::class)->taskForActor((string) $connection->federation_id, $actor, (string) ($args['task_id'] ?? ''));
                        $root = Task::query()->whereKey($task->root_id)->lockForUpdate()->firstOrFail();
                        $key = (string) ($args['step_id'] ?? '');
                        if ($key === '') {
                            throw new \DomainException('Step ID required');
                        }
                        $scope = 'step:'.$root->id.':'.$actor;
                        $existing = Receipt::query()->where('scope', $scope)->where('key', $key)->first();
                        if ($existing) {
                            if (! hash_equals($existing->digest, hash('sha256', $task->id))) {
                                throw new \DomainException('Step ID conflict');
                            }

return $existing->response;
                        }
                        $checkpoint = $root->checkpoint ?? [];
                        $state = $checkpoint['delegation_state'] ?? DelegationState::start((string) $root->id, ($checkpoint['recipient'] ?? $actor), $root->created_at->timestamp);
                        $state = DelegationState::step($state, now()->timestamp);
                        $checkpoint['delegation_state'] = $state;
                        $root->checkpoint = $checkpoint;
                        $root->save();
                        $result = ['reserved' => true, 'steps' => $state['steps']];
                        Receipt::query()->create(['id' => (string) Str::uuid(), 'scope' => $scope, 'key' => $key, 'digest' => hash('sha256', $task->id), 'response' => $result]);

                        return $result;
                    });
                } else {
                    throw new \DomainException('Tool is not available');
                }
                ResultContracts::validate($name, $value);
                $result = ['content' => [['type' => 'text', 'text' => json_encode($value, JSON_THROW_ON_ERROR)]]];
            } catch (\Throwable $e) {
                $result = ['isError' => true, 'content' => [['type' => 'text', 'text' => $e instanceof \DomainException || $e instanceof PactError ? $e->getMessage() : 'Tool request failed']]];
            }
        } else {
            return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => -32601, 'message' => 'Method not found']]);
        }

        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }
}
