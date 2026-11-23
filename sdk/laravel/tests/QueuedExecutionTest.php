<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Pact\Pact;
use PactLaravel\Contracts\AgentHandler;
use PactLaravel\Execution\AgentResult;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Outbox;
use PactLaravel\Models\Task;
use PactLaravel\PactServiceProvider;
use PactLaravel\Runtime\TaskService;

final class QueuedExecutionTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PactServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePact();
        $this->freezeTime();
        config([
            'pact.role' => 'client',
            'pact.queue.connection' => 'pact-executor',
            'pact.queue.name' => 'pact-tasks',
            'database.connections.queue-storage' => config('database.connections.testing'),
            'queue.default' => 'sync',
            'queue.connections.pact-executor' => [
                'driver' => 'database',
                'connection' => 'queue-storage',
                'table' => 'jobs',
                'queue' => 'default-tasks',
                'retry_after' => 90,
            ],
        ]);
    }

    public function test_task_runs_only_on_the_configured_connection_and_queue(): void
    {
        $this->createQueueTable();
        $id = $this->sendTask();
        $handler = $this->createMock(AgentHandler::class);
        $handler->expects($this->once())->method('handle')->willReturn(AgentResult::completed(['pong' => true]));
        config(['pact.handler' => AgentHandler::class]);
        $this->app->instance(AgentHandler::class, $handler);

        $this->artisan('pact:work', ['--once' => true])->assertSuccessful();
        $job = Outbox::where('task_id', $id)->sole();
        $queue = DB::connection('queue-storage')->table('jobs');
        self::assertSame(['pact-tasks'], $queue->pluck('queue')->all());
        self::assertSame(0, $job->attempts);
        self::assertSame('TASK_STATE_WORKING', Task::findOrFail($id)->state);

        $this->travel(61)->seconds();
        $this->artisan('pact:work', ['--once' => true])->assertSuccessful();
        self::assertSame($job->owner, $job->fresh()->owner);
        self::assertSame(2, $queue->count());

        $this->runQueueOnce('pact-tasks');
        $task = Task::findOrFail($id);
        self::assertSame('TASK_STATE_COMPLETED', $task->state);
        self::assertTrue($task->value['artifacts'][0]['parts'][0]['data']['pong']);
        $this->runQueueOnce('pact-tasks');
        self::assertSame(0, $queue->count());
        self::assertSame('done', $job->fresh()->state);
        self::assertSame(1, $job->fresh()->attempts);
        self::assertSame($task->version, $task->fresh()->version);
    }

    public function test_failed_publication_retains_the_task_and_retries_on_the_driver_default_queue(): void
    {
        config(['pact.queue.name' => null]);
        $exceptions = Exceptions::fake();
        $id = $this->sendTask();

        $this->artisan('pact:work', ['--once' => true])->assertSuccessful();
        $exceptions->assertReported(QueryException::class);
        $job = Outbox::where('task_id', $id)->sole();
        self::assertSame('pending', $job->state);
        self::assertSame(0, $job->attempts);

        $this->createQueueTable();
        $this->travel(61)->seconds();
        $this->artisan('pact:work', ['--once' => true])->assertSuccessful();
        $this->runQueueOnce('default-tasks');
        self::assertSame('TASK_STATE_COMPLETED', Task::findOrFail($id)->state);
        self::assertSame(1, $job->fresh()->attempts);
    }

    private function sendTask(): string
    {
        $connection = Connection::query()->create(['user_id' => 'alice', 'federation_id' => 'fed', 'member_id' => 'agent', 'remote_url' => 'https://master.example', 'remote_user_id' => 'alice', 'status' => 'active']);
        $service = $this->app->make(TaskService::class);
        $message = (object) [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'SendMessage',
            'params' => (object) ['message' => (object) [
                'role' => 'ROLE_USER', 'messageId' => 'm', 'parts' => [(object) ['data' => (object) ['message' => 'hello']]],
                'metadata' => (object) [Pact::CORE_URI => (object) ['version' => Pact::VERSION, 'contract' => $service->contract(), 'idempotency_key' => 'queued']],
            ]],
        ];

        return $service->rpc($connection, 'agent', $message, json_encode($message, JSON_THROW_ON_ERROR))['task']['id'];
    }

    private function createQueueTable(): void
    {
        DB::connection('queue-storage')->getSchemaBuilder()->create('jobs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    private function runQueueOnce(string $queue): void
    {
        $this->artisan('queue:work', ['connection' => 'pact-executor', '--queue' => $queue, '--once' => true])->assertSuccessful();
    }
}
