<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use Pact\Pact;
use PactLaravel\Contracts\AgentHandler;
use PactLaravel\Execution\AgentContext;
use PactLaravel\Execution\AgentResult;
use PactLaravel\Models\Connection;
use PactLaravel\Models\EventReceipt;
use PactLaravel\Models\Task;
use PactLaravel\OAuth\PeerMiddleware;
use PactLaravel\Runtime\Routes;
use PactLaravel\Runtime\Store;
use PactLaravel\Runtime\TaskService;
use PactLaravel\Runtime\Worker;

final class EventProgressTest extends TestCase
{
    public function test_host_progress_is_observable_without_interrupting_execution(): void
    {
        $this->migratePact();
        config(['pact.role' => 'client', 'pact.handler' => AgentHandler::class]);
        $connection = Connection::query()->create(['user_id' => 'alice', 'federation_id' => 'fed', 'member_id' => 'agent', 'remote_url' => 'https://master.example', 'remote_user_id' => 'alice', 'status' => 'active']);
        $service = $this->app->make(TaskService::class);
        $store = $this->app->make(Store::class);
        $message = (object) [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'SendMessage',
            'params' => (object) ['message' => (object) [
                'role' => 'ROLE_USER', 'messageId' => 'm', 'parts' => [(object) ['data' => (object) ['message' => 'hello']]],
                'metadata' => (object) [Pact::CORE_URI => (object) ['version' => Pact::VERSION, 'contract' => $service->contract(), 'idempotency_key' => 'progress']],
            ]],
        ];
        $id = $service->rpc($connection, 'agent', $message, json_encode($message, JSON_THROW_ON_ERROR))['task']['id'];
        $get = (object) ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'GetTask', 'params' => (object) ['id' => $id]];
        $read = fn () => $service->rpc($connection, 'agent', $get, json_encode($get, JSON_THROW_ON_ERROR));
        $handler = $this->createMock(AgentHandler::class);
        $handler->expects($this->exactly(2))->method('handle')->willReturnCallback(
            function (AgentContext $context) use ($service, $read): AgentResult {
                if ($context->checkpoint['handed_off'] ?? false) {
                    return AgentResult::completed(['pong' => true]);
                }
                self::assertTrue($service->reportProgress($context->taskId, 'Sto cercando nei documenti…'));
                $active = $read();
                self::assertSame('TASK_STATE_WORKING', $active['status']['state']);
                self::assertSame('Sto cercando nei documenti…', $active['metadata'][Pact::CORE_URI]['progress']['stage']);
                self::assertNull($active['metadata'][Pact::CORE_URI]['progress']['percentage']);
                self::assertFalse($service->reportProgress($context->taskId, 'Sto cercando nei documenti…'));

                return AgentResult::waiting(['handed_off' => true]);
            }
        );
        $this->app->instance(AgentHandler::class, $handler);
        $worker = $this->app->make(Worker::class);
        self::assertTrue($worker->workOnce());
        $waiting = $read()['metadata'][Pact::CORE_URI]['progress'];
        self::assertSame(1, $waiting['sequence']);

        self::assertTrue($service->reportProgress($id, 'Sto verificando le fonti…'));
        $verifying = $read()['metadata'][Pact::CORE_URI]['progress'];
        self::assertSame(2, $verifying['sequence']);
        self::assertSame('Sto verificando le fonti…', $verifying['stage']);
        $store->enqueue('execute', $id, [], 'resume-progress');
        self::assertTrue($worker->workOnce());

        $completed = $read();
        self::assertSame('TASK_STATE_COMPLETED', $completed['status']['state']);
        self::assertSame(100, $completed['metadata'][Pact::CORE_URI]['progress']['percentage']);
        self::assertFalse($service->reportProgress($id, 'Late update'));
        self::assertSame($completed, $read());
    }

    public function test_audited_events_and_native_progress(): void
    {
        $this->migratePact();
        $connection = Connection::query()->create(['user_id' => 'alice', 'federation_id' => 'fed', 'member_id' => 'agent', 'remote_url' => 'https://master.example', 'remote_user_id' => 'alice', 'status' => 'active']);
        $service = new TaskService(new Store, new Pact);
        $message = (object) ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'SendMessage', 'params' => (object) ['message' => (object) ['role' => 'ROLE_USER', 'messageId' => 'm', 'parts' => [(object) ['data' => (object) ['message' => 'hello']]], 'metadata' => (object) [Pact::CORE_URI => (object) ['version' => Pact::VERSION, 'contract' => $service->contract(), 'idempotency_key' => 'key']]]]];
        $id = $service->rpc($connection, 'agent', $message, json_encode($message))['task']['id'];
        $initial = Task::findOrFail($id)->value['metadata'][Pact::CORE_URI]['progress'];
        self::assertSame(0, $initial['completed_units']);
        self::assertTrue((new Worker(new Store))->workOnce());
        $task = Task::findOrFail($id);
        $complete = $task->value['metadata'][Pact::CORE_URI]['progress'];
        self::assertSame(1, $complete['completed_units']);
        self::assertGreaterThan($initial['sequence'], $complete['sequence']);
        $this->app->instance(PeerMiddleware::class, new class($connection)
        {
            public function __construct(private Connection $connection) {}

            public function handle($request, $next, $audience)
            {
                $request->attributes->set('pact.connection', $this->connection);
                $request->attributes->set('pact.producer_id', 'producer');

                return $next($request);
            }
        });
        Routes::register($this->app['router']);
        $event = static function (string $eventId, int $sequence, string $producer = 'producer', ?string $taskId = null) use ($id): string {
            $progress = (object) ['sequence' => $sequence, 'stage' => 'execution', 'completed_units' => 0, 'total_units' => 1, 'percentage' => 0, 'updated_at' => '2026-11-16T10:00:00.000Z'];

            return json_encode(Pact::signEvent((object) ['event_id' => $eventId, 'task_id' => $taskId ?? $id, 'producer_id' => $producer, 'sequence' => $sequence, 'type' => 'task.progress', 'occurred_at' => '2026-11-16T10:00:00.000Z', 'correlation_id' => 'c', 'causation_id' => null, 'payload' => $progress]), JSON_THROW_ON_ERROR);
        };
        $post = fn (string $raw) => $this->call('POST', '/pact/agents/agent/events', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $raw);
        $first = $event('first', 4);
        $post($first)->assertOk();
        $post($first)->assertOk();
        $post($event('old', 3))->assertOk();
        $post($event('first', 5, 'producer', '22222222-2222-4222-8222-222222222222'))->assertStatus(400)->assertJsonPath('error', 'EVENT_CONFLICT');
        $post($first.' ')->assertStatus(400)->assertJsonPath('error', 'EVENT_CONFLICT');
        $post($event('other', 5, 'impostor'))->assertStatus(400)->assertJsonPath('error', 'PRODUCER_IDENTITY');
        self::assertSame(['accepted', 'stale'], EventReceipt::query()->orderBy('sequence', 'desc')->pluck('disposition')->all());
        self::assertSame($task->value,Task::findOrFail($id)->value);
    }
}
