<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use Illuminate\Support\Carbon;
use Pact\Client;
use Pact\Pact;
use Pact\PactError;
use PactLaravel\Contracts\AgentHandler;
use PactLaravel\Execution\AgentContext;
use PactLaravel\Execution\AgentResult;
use PactLaravel\Mcp\Coordinator;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Member;
use PactLaravel\Models\Outbox;
use PactLaravel\Models\Task;
use PactLaravel\Runtime\Store;
use PactLaravel\Runtime\TaskService;
use PactLaravel\Runtime\Worker;

final class RuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePact();
    }

    public function test_federations_are_stable_and_isolated_per_user(): void
    {
        $store = new Store;
        $alice = $store->federationFor('alice');
        $bob = $store->federationFor('bob');
        self::assertSame($alice->id, $store->federationFor('alice')->id);
        self::assertNotSame($alice->id, $bob->id);
        $store->bump($alice->id);
        self::assertSame(2, $store->roster($alice->id)->revision);
        self::assertSame(1, $store->roster($bob->id)->revision);
        self::assertSame('https://master.example/pact/mcp', $store->roster($alice->id)->mcp_endpoint);
    }

    public function test_outbox_idempotency_detects_changed_payload(): void
    {
        $store = new Store;
        $first = $store->enqueue('http', null, ['body' => 'secret'], 'request-1');
        self::assertSame($first->id, $store->enqueue('http', null, ['body' => 'secret'], 'request-1')->id);
        self::assertSame(1, Outbox::query()->count());
        self::assertStringNotContainsString('secret', (string) \DB::table('pact_outbox')->value('payload'));
        $this->expectException(\DomainException::class);
        $store->enqueue('http', null, ['body' => 'different'], 'request-1');
    }

    public function test_worker_respects_active_lease_and_recovers_expired_lease(): void
    {
        $store = new Store;
        $job = $store->enqueue('unknown-kind', null, []);
        $job->update(['owner' => 'crashed-worker', 'lease_until' => now()->addMinute()]);
        $worker = new Worker($store);
        self::assertFalse($worker->workOnce());
        $job->update(['lease_until' => now()->subMinute()]);
        self::assertTrue($worker->workOnce());
        $job->refresh();
        self::assertSame('failed', $job->state);
        self::assertSame(1, $job->attempts);
        self::assertNull($job->owner);
        self::assertNull($job->lease_until);
        self::assertFalse($worker->workOnce());
    }

    public function test_atomic_rolls_back_queued_work_on_failure(): void
    {
        $store = new Store;
        try {
            $store->atomic(function () use ($store): void {
                $store->enqueue('http', null, ['url' => 'https://client.example']);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $e) {
            self::assertSame('rollback', $e->getMessage());
        }
        self::assertSame(0, Outbox::query()->count());
    }

    public function test_task_replay_worker_output_and_reads_are_scoped_to_connection(): void
    {
        $store = new Store;
        $service = new TaskService($store, new Pact);
        $connection = Connection::query()->create(['user_id' => 'alice', 'federation_id' => 'fed-a', 'member_id' => 'agent-a', 'remote_url' => 'https://client.example', 'remote_user_id' => 'remote', 'status' => 'active']);
        $request = (object) ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'SendMessage', 'params' => (object) ['message' => (object) ['role' => 'ROLE_USER', 'messageId' => 'message-1', 'parts' => [(object) ['data' => (object) ['message' => 'hello']]], 'metadata' => (object) [Pact::CORE_URI => (object) ['version' => Pact::VERSION, 'contract' => $service->contract(), 'idempotency_key' => 'key-1']]]]];
        $raw = json_encode($request, JSON_THROW_ON_ERROR);
        $created = $service->rpc($connection, 'default', $request, $raw);
        self::assertSame($created, $service->rpc($connection, 'default', $request, $raw));
        self::assertSame(1, Task::query()->count());
        try {
            $service->rpc($connection, 'default', $request, $raw.' ');
            self::fail('Changed bytes with the same idempotency key must fail.');
        } catch (PactError $e) {
            self::assertStringContainsString('Key already used', $e->getMessage());
        }
        self::assertTrue((new Worker($store))->workOnce());
        $task = Task::query()->firstOrFail();
        self::assertSame('TASK_STATE_COMPLETED', $task->state);
        self::assertSame(['pong' => true, 'message' => 'hello'], $task->value['artifacts'][0]['parts'][0]['data']);
        $client = new Client('https://client.example/pact/a2a', 'unused', $service->contract(), __DIR__.'/../resources/contracts/registry.json');
        self::assertSame($task->id, $client->task(json_decode(json_encode($task->value, JSON_THROW_ON_ERROR)))->id);
        $other = Connection::query()->create(['user_id' => 'bob', 'federation_id' => 'fed-b', 'member_id' => 'agent-b', 'remote_url' => 'https://client.example', 'remote_user_id' => 'remote', 'status' => 'active']);
        $get = (object) ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'GetTask', 'params' => (object) ['id' => $task->id]];
        $this->expectException(PactError::class);
        $service->rpc($other, 'default', $get, json_encode($get));
    }

    public function test_canceled_task_is_not_executed_by_queued_worker(): void
    {
        $store = new Store;
        $service = new TaskService($store, new Pact);
        $connection = Connection::query()->create(['user_id' => 'alice', 'federation_id' => 'fed-a', 'member_id' => 'agent-a', 'remote_url' => 'https://client.example', 'remote_user_id' => 'remote', 'status' => 'active']);
        $request = (object) ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'SendMessage', 'params' => (object) ['message' => (object) ['role' => 'ROLE_USER', 'messageId' => 'message-1', 'parts' => [(object) ['data' => (object) []]], 'metadata' => (object) [Pact::CORE_URI => (object) ['version' => Pact::VERSION, 'contract' => $service->contract(), 'idempotency_key' => 'cancel-key']]]]];
        $created = $service->rpc($connection, 'default', $request, json_encode($request));
        $cancel = (object) ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'CancelTask', 'params' => (object) ['id' => $created['task']['id']]];
        $canceled = $service->rpc($connection, 'default', $cancel, json_encode($cancel));
        self::assertSame('TASK_STATE_CANCELED', $canceled['status']['state']);
        self::assertTrue((new Worker($store))->workOnce());
        $task = Task::query()->findOrFail($created['task']['id']);
        self::assertSame('TASK_STATE_CANCELED', $task->state);
        self::assertArrayNotHasKey('artifacts', $task->value);
    }

    public function test_question_answer_resumes_task_and_validates_context(): void
    {
        config(['pact.handler' => QuestionHandler::class]);
        $store = new Store;
        $service = new TaskService($store, new Pact);
        $connection = Connection::query()->create(['user_id' => 'alice', 'federation_id' => 'fed-a', 'member_id' => 'agent-a', 'remote_url' => 'https://client.example', 'remote_user_id' => 'remote', 'status' => 'active']);
        $meta = (object) ['version' => Pact::VERSION, 'contract' => $service->contract(), 'idempotency_key' => 'initial'];
        $message = (object) ['role' => 'ROLE_USER', 'messageId' => 'message-1', 'parts' => [(object) ['data' => (object) []]], 'metadata' => (object) [Pact::CORE_URI => $meta]];
        $request = (object) ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'SendMessage', 'params' => (object) ['message' => $message]];
        $created = $service->rpc($connection, 'default', $request, json_encode($request));
        self::assertTrue((new Worker($store))->workOnce());
        $task = Task::query()->findOrFail($created['task']['id']);
        self::assertSame('TASK_STATE_INPUT_REQUIRED', $task->state);
        $message->taskId = $task->id;
        $message->contextId = 'wrong';
        $meta->idempotency_key = 'answer';
        $meta->answers = [(object) ['response_to' => 'question-1', 'task_id' => $task->id, 'value' => (object) ['yes' => true]]];
        try {
            $service->rpc($connection, 'default', $request, json_encode($request));
            self::fail('Wrong context must not answer the question.');
        } catch (PactError $e) {
            self::assertStringContainsString('Context differs', $e->getMessage());
        }
        $message->contextId = $task->value['contextId'];
        $service->rpc($connection, 'default', $request, json_encode($request));
        self::assertSame('TASK_STATE_WORKING', $task->fresh()->state);
        self::assertTrue((new Worker($store))->workOnce());
        self::assertSame('TASK_STATE_COMPLETED', $task->fresh()->state);
        self::assertSame('answered', $task->fresh()->value['metadata'][Pact::CORE_URI]['questions'][0]['status']);
    }

    public function test_delegation_is_durable_idempotent_and_creates_child_work(): void
    {
        $store = new Store;
        $caller = Connection::query()->create(['user_id' => 'alice', 'federation_id' => 'fed-a', 'member_id' => 'agent-a', 'remote_url' => 'https://a.example', 'remote_user_id' => 'a', 'status' => 'active']);
        $remote = Connection::query()->create(['user_id' => 'alice', 'federation_id' => 'fed-a', 'member_id' => 'agent-b', 'remote_url' => 'https://b.example', 'remote_user_id' => 'b', 'status' => 'active']);
        $contract = json_decode(file_get_contents(__DIR__.'/../resources/contracts/agent-chat/contract.json'), true);
        Member::query()->create(['federation_id' => 'fed-a', 'member_id' => 'agent-b', 'user_id' => 'alice', 'connection_id' => $remote->id, 'active' => true, 'manifest' => ['capabilities' => ['extensions' => [['uri' => Pact::CORE_URI, 'params' => ['contracts' => [$contract]]]]]]]);
        $root = Task::query()->create(['id' => '11111111-1111-4111-8111-111111111111', 'connection_id' => $caller->id, 'user_id' => 'alice', 'federation_id' => 'fed-a', 'root_id' => '11111111-1111-4111-8111-111111111111', 'caller_id' => 'agent-a', 'state' => 'TASK_STATE_WORKING', 'value' => [], 'checkpoint' => [], 'version' => 1]);
        $coordinator = new Coordinator($store);
        $args = ['id' => 'delegate-1', 'recipient_id' => 'agent-b', 'task_id' => $root->id, 'input' => ['message' => 'help']];
        $result = $coordinator->dispatch($caller, 'agent-a', 'request_delegation', $args);
        self::assertSame($result, $coordinator->dispatch($caller, 'agent-a', 'request_delegation', $args));
        self::assertTrue($result['accepted']);
        $child = Task::query()->findOrFail($result['task_id']);
        self::assertSame($root->id, $child->parent_id);
        self::assertSame($root->id, $child->root_id);
        self::assertSame($result['delegation_id'], $child->delegation_id);
        self::assertSame($child->id, $root->fresh()->checkpoint['waiting_for']);
        self::assertSame(1, Outbox::query()->where('kind', 'peer_send')->count());
        $args['input']['message'] = 'changed';
        $this->expectException(PactError::class);
        $coordinator->dispatch($caller, 'agent-a', 'request_delegation', $args);
    }

    public function test_expired_execution_lease_is_reclaimed_and_cannot_publish_stale_result(): void
    {
        config(['pact.handler' => ExpiringHandler::class, 'pact.worker.lease_seconds' => 60]);
        ExpiringHandler::$calls = 0;
        $store = new Store;
        $service = new TaskService($store, new Pact);
        $connection = Connection::query()->create(['user_id' => 'alice', 'federation_id' => 'fed-a', 'member_id' => 'agent-a', 'remote_url' => 'https://client.example', 'remote_user_id' => 'remote', 'status' => 'active']);
        $message = (object) ['role' => 'ROLE_USER', 'messageId' => 'm', 'parts' => [(object) ['data' => (object) []]], 'metadata' => (object) [Pact::CORE_URI => (object) ['version' => Pact::VERSION, 'contract' => $service->contract(), 'idempotency_key' => 'lease']]];
        $request = (object) ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'SendMessage', 'params' => (object) ['message' => $message]];
        $created = $service->rpc($connection, 'default', $request, json_encode($request));
        $worker = new Worker($store);
        self::assertTrue($worker->workOnce());
        $task = Task::query()->findOrFail($created['task']['id']);
        self::assertSame('TASK_STATE_WORKING', $task->state);
        self::assertSame('pending', Outbox::query()->firstOrFail()->state);
        self::assertTrue($worker->workOnce());
        self::assertSame('TASK_STATE_COMPLETED', $task->fresh()->state);
        self::assertSame(2, Outbox::query()->firstOrFail()->attempts);
        Carbon::setTestNow();
    }
}

final class QuestionHandler implements AgentHandler
{
    public function handle(AgentContext $context): AgentResult
    {
        if ($context->answers !== []) {
            return AgentResult::completed(['pong' => true]);
        }
        $contract = json_decode(file_get_contents(__DIR__.'/../resources/contracts/contract.json'), true);

        return AgentResult::question([['question_id' => 'question-1', 'task_id' => $context->taskId, 'prompt' => 'Continue?', 'answer_schema' => $contract['question_answer'], 'required' => true, 'expires_at' => null, 'status' => 'open']]);
    }
}

final class ExpiringHandler implements AgentHandler
{
    public static int $calls = 0;

    public function handle(AgentContext $context): AgentResult
    {
        if (++self::$calls === 1) {
            Carbon::setTestNow(now()->addSeconds(61));
        }

        return AgentResult::completed(['pong' => true]);
    }
}
