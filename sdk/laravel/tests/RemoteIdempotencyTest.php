<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
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
use PactLaravel\Support\HttpTransport;

final class RemoteIdempotencyTest extends TestCase
{
    public function test_actor_and_answer_scopes_produce_distinct_stable_wire_keys(): void
    {
        config(['pact.role' => 'master']);
        $this->migratePact();
        $store = new Store;
        $coordinator = new Coordinator($store);
        $identity = new RemoteIdempotencyIdentity;
        $manager = new PactManager($identity, new ConnectionService($store, $this->createStub(ClientRepository::class), new HttpTransport), $store, $coordinator, new Pact);
        $federation = $store->federationFor('alice');
        $contract = json_decode(file_get_contents(__DIR__.'/../resources/contracts/contract.json'), true);
        $connection = Connection::query()->create(['user_id' => 'alice', 'federation_id' => $federation->id, 'member_id' => 'agent', 'remote_url' => 'https://client.example', 'remote_user_id' => 'remote', 'status' => 'active']);
        Member::query()->create(['user_id' => 'alice', 'federation_id' => $federation->id, 'member_id' => 'agent', 'connection_id' => $connection->id, 'active' => true, 'manifest' => ['capabilities' => ['extensions' => [['uri' => Pact::CORE_URI, 'params' => ['contracts' => [$contract]]]]]]]);
        $args = ['id' => str_repeat('x', 191), 'recipient_id' => 'agent', 'text' => 'hello'];
        $a = $coordinator->dispatch($connection, 'actor-a', 'send_message', $args);
        self::assertSame($a, $coordinator->dispatch($connection, 'actor-a', 'send_message', $args));
        $coordinator->dispatch($connection, 'actor-b', 'send_message', $args);
        $ids = [];
        foreach (Outbox::query()->get() as $job) {
            $wire = json_decode($job->payload['raw'], true);
            $ids[] = $wire['id'];
            self::assertSame($wire['id'], $wire['params']['message']['messageId']);
            self::assertSame($wire['id'], $wire['params']['message']['metadata'][Pact::CORE_URI]['idempotency_key']);
            self::assertLessThanOrEqual(191, strlen($job->key));
        }
        self::assertCount(2, array_unique($ids));
        foreach (['first', 'second'] as $initial) {
            $id = $manager->sendMessage('agent', 'hello', $initial)['task_id'];
            $task = Task::query()->findOrFail($id);
            $question = ['question_id' => 'q1', 'task_id' => 'remote-task', 'prompt' => 'Continue?', 'answer_schema' => $contract['question_answer'], 'required' => true, 'expires_at' => null, 'status' => 'open'];
            $checkpoint = $task->checkpoint;
            $checkpoint['remote_task_id'] = 'remote-task';
            $checkpoint['last_observation'] = ['id' => 'remote-task', 'contextId' => 'context', 'metadata' => [Pact::CORE_URI => ['questions' => [$question]]]];
            $task->update(['state' => 'TASK_STATE_INPUT_REQUIRED', 'checkpoint' => $checkpoint]);
            $answers = [['response_to' => 'q1', 'task_id' => 'remote-task', 'value' => ['yes' => true]]];
            $result = $manager->answer($id, $answers, 'first');
            self::assertSame($result, $manager->answer($id, $answers, 'first'));
        }
        $jobs = Outbox::query()->get();
        self::assertCount(6, $jobs);
        $wireIds = $jobs->map(fn ($job) => json_decode($job->payload['raw'], true)['id'])->all();
        self::assertCount(6, array_unique($wireIds));
    }

    public function test_exchange_id_over_column_limit_is_rejected_before_writes(): void
    {
        $this->migratePact();
        $this->expectException(PactError::class);
        (new Coordinator(new Store))->dispatch(new Connection(['federation_id' => 'fed']), 'actor', 'send_message', ['id' => str_repeat('x', 192), 'recipient_id' => 'agent', 'text' => 'hello']);
    }
}

final class RemoteIdempotencyIdentity implements IdentityResolver
{
    public function current(): ?Authenticatable
    {
        return new GenericUser(['id' => 'alice']);
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
