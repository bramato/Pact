<?php

declare(strict_types=1);

namespace PactLaravel\Runtime;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Laravel\Mcp\Facades\Mcp;
use Pact\Federation\Policy;
use Pact\Pact;
use Pact\PactError;
use PactLaravel\Mcp\Server;
use PactLaravel\Models\Connection;
use PactLaravel\Models\EventReceipt;
use PactLaravel\Models\Receipt;
use PactLaravel\Models\Task;
use PactLaravel\OAuth\PeerMiddleware;

final class Routes
{
    public static function register(Router $router): void
    {
        $prefix = trim((string) config('pact.prefix', 'pact'), '/');
        $router->get($prefix.'/agents/{agent}/.well-known/agent-card.json', fn (string $agent) => response()->json(app(TaskService::class)->card($agent)));
        $router->post($prefix.'/agents/{agent}/a2a', function (Request $request, string $agent) {
            abort_unless(config('pact.role') === 'client', 404);
            $id = null;
            try {
                Pact::ensure(strlen($request->getContent()) <= 1048576, 'REQUEST_SIZE', 'Request too large');
                Pact::ensure($request->header('A2A-Version') === '1.0', 'A2A_VERSION', 'A2A version 1.0 required');
                Pact::ensure(in_array(Pact::CORE_URI, array_map('trim', explode(',', (string) $request->header('A2A-Extensions'))), true), 'PACT_UNSUPPORTED', 'Core activation required');
                $rpc = Pact::parse($request->getContent());
                $id = $rpc->id ?? null;
                $result = app(TaskService::class)->rpc($request->attributes->get('pact.connection'), $agent, $rpc, $request->getContent());

                return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result], 200, ['A2A-Extensions' => Pact::CORE_URI]);
            } catch (PactError $e) {
                $code = match ($e->errorCode) {
                    'TASK_NOT_FOUND' => -32001,'TASK_NOT_CANCELABLE' => -32002,'PACT_UNSUPPORTED' => -32008,'A2A_VERSION' => -32009,'METHOD_NOT_FOUND' => -32601,'INVALID_REQUEST' => -32600,'JSON_SYNTAX' => -32700,default => -32602
                };
                $headers = in_array(Pact::CORE_URI, array_map('trim', explode(',', (string) $request->header('A2A-Extensions'))), true) ? ['A2A-Extensions' => Pact::CORE_URI] : [];

                return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $e->getMessage(), 'data' => [Pact::CORE_URI => ['code' => $e->errorCode, 'message' => $e->getMessage(), 'retryable' => $e->retryable]]]], 200, $headers);
            } catch (\JsonException $e) {
                return response()->json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error']], 200);
            }
        })->middleware(PeerMiddleware::class.':a2a');
        $router->post($prefix.'/agents/{agent}/resume', function (Request $request) {
            $p = $request->json()->all();
            $connection = $request->attributes->get('pact.connection');
            abort_unless(is_string($p['task_id'] ?? null) && is_string($p['delegation_id'] ?? null) && is_array($p['result'] ?? null), 400);
            app(Store::class)->atomic(function () use ($connection, $p, $request) {
                $task = Task::query()->where('connection_id', $connection->id)->whereKey($p['task_id'])->lockForUpdate()->firstOrFail();
                $scope = 'resume:'.$task->id;
                $digest = hash('sha256', $request->getContent());
                $prior = Receipt::query()->where('scope', $scope)->where('key', $p['delegation_id'])->first();
                if ($prior) {
                    abort_unless(hash_equals($prior->digest, $digest), 409);

                    return;
                }
                $checkpoint = $task->checkpoint ?? [];
                abort_unless(($checkpoint['_waiting'] ?? false) && ! Pact::isTerminal($task->state), 409);
                $status = $p['result']['status']['state'] ?? null;
                abort_unless(in_array($status, ['TASK_STATE_COMPLETED', 'TASK_STATE_FAILED', 'TASK_STATE_CANCELED', 'TASK_STATE_REJECTED'], true), 400);
                if ($status === 'TASK_STATE_COMPLETED') {
                    $output = $p['result']['artifacts'][0]['parts'][0]['data'] ?? null;
                    abort_unless(is_array($output), 400);
                    $contract = json_decode(file_get_contents(__DIR__.'/../../resources/contracts/agent-chat/contract.json'));
                    app(Pact::class)->contractData($contract->output, json_decode(json_encode($output, JSON_THROW_ON_ERROR)), __DIR__.'/../../resources/contracts/agent-chat/registry.json');
                }
                unset($checkpoint['_waiting']);
                $checkpoint['delegation_result'] = $p['result'];
                $task->checkpoint = $checkpoint;
                $task->version++;
                $task->save();
                Receipt::query()->create(['id' => (string) Str::uuid(), 'scope' => $scope, 'key' => $p['delegation_id'], 'digest' => $digest, 'response' => ['accepted' => true]]);
                app(Store::class)->enqueue('execute', $task->id, [], 'resume:'.$p['delegation_id']);
            });

            return response()->json(['accepted' => true]);
        })->middleware(PeerMiddleware::class.':a2a');
        $router->post($prefix.'/agents/{agent}/events', function (Request $request) {
            try {
                $raw = $request->getContent();
                Pact::ensure(strlen($raw) <= 1048576, 'REQUEST_SIZE', 'Request too large');
                $event = Pact::parse($raw);
                app(Pact::class)->event($event);
                $connection = $request->attributes->get('pact.connection');
                $producer = $request->attributes->get('pact.producer_id');
                Pact::ensure(is_string($producer) && $producer !== '' && $event->producer_id === $producer, 'PRODUCER_IDENTITY', 'Authenticated producer differs');
                app(Store::class)->atomic(function () use ($connection, $producer, $event, $raw) {
                    Connection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();
                    $scope = 'event:'.hash('sha256', Pact::canonical([(string) $connection->id, $producer]));
                    $digest = hash('sha256', $raw);
                    $prior = EventReceipt::query()->where('scope', $scope)->where('event_id', $event->event_id)->first();
                    if ($prior) {
                        Pact::ensure(hash_equals($prior->digest, $digest), 'EVENT_CONFLICT', 'Event ID conflict');

                        return;
                    }
                    $task = Task::query()->where('connection_id', $connection->id)->whereKey($event->task_id)->lockForUpdate()->firstOrFail();
                    $last = EventReceipt::query()->where('scope', $scope)->where('task_id', $task->id)->max('sequence');
                    $disposition = $last !== null && $event->sequence <= $last ? 'stale' : 'accepted';
                    EventReceipt::query()->create(['id' => (string) Str::uuid(), 'scope' => $scope, 'event_id' => $event->event_id, 'task_id' => $task->id, 'disposition' => $disposition, 'sequence' => $event->sequence, 'digest' => $digest, 'raw' => $raw, 'payload' => json_decode($raw, true)]);
                });

                return response()->json(['accepted' => true]);
            } catch (PactError $e) {
                return response()->json(['error' => $e->errorCode], 400);
            }
        })->middleware(PeerMiddleware::class.':a2a');
        $router->get($prefix.'/roster', function (Request $request) {
            abort_unless(config('pact.role') === 'master', 404);
            $roster = app(Store::class)->roster($request->attributes->get('pact.connection')->federation_id);
            $etag = Policy::etag($roster);

            return $request->header('If-None-Match') === $etag ? response('', 304, ['ETag' => $etag]) : response()->json($roster, 200, ['ETag' => $etag]);
        })->middleware(PeerMiddleware::class.':roster');
        if (config('pact.role') === 'master') {
            Mcp::web($prefix.'/mcp', Server::class)->middleware(PeerMiddleware::class.':mcp');
        }
    }
}
