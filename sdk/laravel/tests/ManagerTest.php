<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\ClientRepository;
use Pact\Pact;
use Pact\PactError;
use PactLaravel\Contracts\IdentityResolver;
use PactLaravel\Mcp\Coordinator;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Member;
use PactLaravel\Models\Outbox;
use PactLaravel\Models\Task;
use PactLaravel\OAuth\ConnectionService;
use PactLaravel\PactManager;
use PactLaravel\Runtime\Store;
use PactLaravel\Runtime\Worker;
use PactLaravel\Support\HttpTransport;

final class ManagerTest extends TestCase
{
    private Store $store;

    private PactManager $manager;

    private TestIdentity $identity;

    private Connection $connection;

    private array $contract;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pact.role' => 'master']);
        $this->migratePact();
        $this->store = new Store;
        $this->identity = new TestIdentity;
        $this->manager = new PactManager($this->identity, new ConnectionService($this->store, $this->createStub(ClientRepository::class), new HttpTransport), $this->store, new Coordinator($this->store), new Pact);
        $federation = $this->store->federationFor('alice');
        $this->contract = json_decode(file_get_contents(__DIR__.'/../resources/contracts/contract.json'), true);
        $this->connection = Connection::query()->create(['user_id' => 'alice', 'federation_id' => $federation->id, 'member_id' => 'agent', 'remote_url' => 'https://client.example', 'remote_user_id' => 'remote', 'status' => 'active']);
        Member::query()->create(['user_id' => 'alice', 'federation_id' => $federation->id, 'member_id' => 'agent', 'connection_id' => $this->connection->id, 'active' => true, 'manifest' => ['capabilities' => ['extensions' => [['uri' => Pact::CORE_URI, 'params' => ['contracts' => [$this->contract]]]]]]]);
    }

    public function test_owner_sends_idempotently_and_only_owner_can_read_task(): void
    {
        self::assertCount(2, $this->manager->members());
        $first = $this->manager->sendMessage('agent', 'hello', 'message-1');
        self::assertSame($first, $this->manager->sendMessage('agent', 'hello', 'message-1'));
        self::assertSame($first['task_id'], $this->manager->task($first['task_id'])['id']);
        self::assertSame(1, Outbox::query()->count());
        $this->identity->id = 'bob';
        $this->expectException(ModelNotFoundException::class);
        $this->manager->task($first['task_id']);
    }

    public function test_owner_answers_remote_question_and_replays_without_duplicate_work(): void
    {
        $id = $this->manager->sendMessage('agent', 'hello', 'initial')['task_id'];
        $task = Task::query()->findOrFail($id);
        $question = ['question_id' => 'q1', 'task_id' => 'remote-task', 'prompt' => 'Continue?', 'answer_schema' => $this->contract['question_answer'], 'required' => true, 'expires_at' => null, 'status' => 'open'];
        $observation = ['id' => 'remote-task', 'contextId' => 'context', 'status' => ['state' => 'TASK_STATE_INPUT_REQUIRED', 'timestamp' => now()->toISOString()], 'metadata' => [Pact::CORE_URI => ['version' => Pact::VERSION, 'contract' => $this->contract, 'questions' => [$question]]]];
        $checkpoint = $task->checkpoint;
        $checkpoint['remote_task_id'] = 'remote-task';
        $checkpoint['last_observation'] = $observation;
        $task->update(['state' => 'TASK_STATE_INPUT_REQUIRED', 'checkpoint' => $checkpoint, 'value' => $observation]);
        $answers = [['response_to' => 'q1', 'task_id' => 'remote-task', 'value' => ['yes' => true]]];
        $result = $this->manager->answer($id, $answers, 'answer-1');
        self::assertSame($result, $this->manager->answer($id, $answers, 'answer-1'));
        $wireKey = 'answer_'.hash('sha256', 'answer:'.$id."\0".'answer-1');
        $job = Outbox::query()->where('task_id', $id)->get()->first(fn ($j) => json_decode($j->payload['raw'], true)['id'] === $wireKey);
        self::assertNotNull($job);
        $wire = json_decode($job->payload['raw'], true);
        self::assertSame($wireKey, $wire['params']['message']['messageId']);
        self::assertSame($wireKey, $wire['params']['message']['metadata'][Pact::CORE_URI]['idempotency_key']);
        self::assertSame('remote-task', $wire['params']['message']['taskId']);
        self::assertSame('context', $wire['params']['message']['contextId']);
        self::assertSame('TASK_STATE_WORKING', $task->fresh()->state);
        $this->expectException(PactError::class);
        $answers[0]['value']['yes'] = false;
        $this->manager->answer($id, $answers, 'answer-1');
    }

    public function test_answer_fences_poll_started_after_commit_until_acknowledged(): void
    {
        $this->connection->update(['credentials' => ['access_token' => 'token', 'expires_at' => time() + 3600]]);
        $id = $this->manager->sendMessage('agent', 'hello', 'initial')['task_id'];
        Outbox::query()->update(['state' => 'done']);
        $task = Task::query()->findOrFail($id);
        $question = ['question_id' => 'q1', 'task_id' => 'remote-task', 'prompt' => 'Continue?', 'answer_schema' => $this->contract['question_answer'], 'required' => true, 'expires_at' => null, 'status' => 'open'];
        $remote = ['id' => 'remote-task', 'contextId' => 'context', 'status' => ['state' => 'TASK_STATE_INPUT_REQUIRED', 'timestamp' => now()->toISOString()], 'metadata' => [Pact::CORE_URI => ['version' => Pact::VERSION, 'contract' => $this->contract, 'questions' => [$question]]]];
        $checkpoint = $task->checkpoint;
        $checkpoint['remote_task_id'] = 'remote-task';
        $checkpoint['last_observation'] = $remote;
        $task->update(['state' => 'TASK_STATE_INPUT_REQUIRED', 'checkpoint' => $checkpoint, 'value' => $remote]);
        $raw = json_encode(['jsonrpc' => '2.0', 'id' => 'poll', 'method' => 'GetTask', 'params' => ['id' => 'remote-task']]);
        $poll = $this->store->enqueue('peer_poll', $id, ['connection_id' => $this->connection->id, 'recipient' => 'agent', 'contract' => $this->contract, 'raw' => $raw]);
        $poll->available_at = now()->subSecond();
        $poll->save();
        $this->manager->answer($id, [['response_to' => 'q1', 'task_id' => 'remote-task', 'value' => ['yes' => true]]], 'answer');
        $wireKey = 'answer_'.hash('sha256', 'answer:'.$id."\0".'answer');
        Http::fake(function ($request) use ($remote) {
            $wire = json_decode($request->body(), true);
            $remote['status']['state'] = 'TASK_STATE_WORKING';

            return Http::response(['jsonrpc' => '2.0', 'id' => $wire['id'], 'result' => $remote], 200, ['A2A-Extensions' => Pact::CORE_URI]);
        });
        $worker = new Worker($this->store);
        $worker->workOnce();
        Http::assertNothingSent();
        self::assertSame('TASK_STATE_WORKING', $task->fresh()->state);
        self::assertSame($wireKey, $task->fresh()->checkpoint['pending_answer']);
        $worker->workOnce();
        self::assertArrayNotHasKey('pending_answer', $task->fresh()->checkpoint);
        self::assertSame('TASK_STATE_WORKING', $task->fresh()->state);
        self::assertSame(1, Outbox::query()->where('kind', 'peer_poll')->where('state', 'pending')->count());
        try {
            $this->manager->answer($id, [['response_to' => 'q1', 'task_id' => 'remote-task', 'value' => ['yes' => true]]], 'another');
            self::fail('The answered question must stay closed.');
        } catch (PactError $e) {
            self::assertStringContainsString('not awaiting', $e->getMessage());
        }
    }

    public function test_owner_cancel_queues_remote_cancel_and_hides_other_owner_tasks(): void
    {
        $id = $this->manager->sendMessage('agent', 'hello', 'initial')['task_id'];
        $task = Task::query()->findOrFail($id);
        $checkpoint = $task->checkpoint;
        $checkpoint['remote_task_id'] = 'remote-task';
        $task->update(['checkpoint' => $checkpoint]);
        $this->identity->id = 'bob';
        try {
            $this->manager->cancel($id);
            self::fail('Other user must not cancel');
        } catch (ModelNotFoundException) {
        }
        $this->identity->id = 'alice';
        $this->manager->cancel($id);
        $this->manager->cancel($id);
        self::assertSame('TASK_STATE_CANCELED', $task->fresh()->state);
        self::assertSame(1, Outbox::query()->where('kind', 'peer_cancel')->count());
        self::assertSame('remote-task', json_decode(Outbox::query()->where('kind', 'peer_cancel')->firstOrFail()->payload['raw'], true)['params']['id']);
    }

    public function test_manager_requires_authenticated_user(): void
    {
        $this->identity->authenticated = false;
        $this->expectException(AuthenticationException::class);
        $this->manager->members();
    }

    public function test_owner_cannot_read_agent_private_task_in_same_federation(): void
    {
        $id = $this->manager->sendMessage('agent', 'hello', 'initial')['task_id'];
        Task::query()->whereKey($id)->update(['caller_id' => 'other-agent']);
        $this->expectException(ModelNotFoundException::class);
        $this->manager->task($id);
    }

    public function test_answer_rejects_wrong_remote_task_without_queuing_work(): void
    {
        $id = $this->manager->sendMessage('agent', 'hello', 'initial')['task_id'];
        $task = Task::query()->findOrFail($id);
        $question = ['question_id' => 'q1', 'task_id' => 'remote-task', 'prompt' => 'Continue?', 'answer_schema' => $this->contract['question_answer'], 'required' => true, 'expires_at' => null, 'status' => 'open'];
        $observation = ['id' => 'remote-task', 'contextId' => 'context', 'metadata' => [Pact::CORE_URI => ['questions' => [$question]]]];
        $checkpoint = $task->checkpoint;
        $checkpoint['remote_task_id'] = 'remote-task';
        $checkpoint['last_observation'] = $observation;
        $task->update(['state' => 'TASK_STATE_INPUT_REQUIRED', 'checkpoint' => $checkpoint]);
        try {
            $this->manager->answer($id, [['response_to' => 'q1', 'task_id' => 'wrong-task', 'value' => ['yes' => true]]], 'answer');
            self::fail('Wrong task correlation must fail');
        } catch (PactError $e) {
            self::assertStringContainsString('differs', $e->getMessage());
        }
        self::assertSame(1, Outbox::query()->count());
        self::assertSame('TASK_STATE_INPUT_REQUIRED', $task->fresh()->state);
    }
}

final class TestIdentity implements IdentityResolver
{
    public string $id = 'alice';

    public bool $authenticated = true;

    public function current(): ?Authenticatable
    {
        return $this->authenticated ? new GenericUser(['id' => $this->id]) : null;
    }

    public function find(string $id): ?Authenticatable
    {
        return new GenericUser(['id' => $id]);
    }

    public function id(Authenticatable $user): string
    {
        return (string) $user->getAuthIdentifier();
    }
}
