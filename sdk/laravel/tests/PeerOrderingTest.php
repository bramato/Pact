<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use Illuminate\Support\Facades\Http;
use Pact\Pact;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Outbox;
use PactLaravel\Models\Task;
use PactLaravel\Runtime\Store;
use PactLaravel\Runtime\Worker;

final class PeerOrderingTest extends TestCase
{
    private function scenario(string $kind, bool $known = false): array
    {
        $this->migratePact();
        $contract = json_decode(file_get_contents(__DIR__.'/../resources/contracts/contract.json'), true, 512, JSON_THROW_ON_ERROR);
        $connection = Connection::query()->create(['user_id' => 'alice', 'federation_id' => 'fed', 'member_id' => 'agent', 'remote_url' => 'https://client.example', 'remote_user_id' => 'remote', 'status' => 'active', 'credentials' => ['access_token' => 'token', 'expires_at' => time() + 3600]]);
        $question = ['question_id' => 'q1', 'task_id' => 'remote-task', 'prompt' => 'Continue?', 'answer_schema' => $contract['question_answer'], 'required' => true, 'expires_at' => null, 'status' => 'open'];
        $remote = ['id' => 'remote-task', 'contextId' => 'context', 'status' => ['state' => 'TASK_STATE_INPUT_REQUIRED', 'timestamp' => now()->toISOString()], 'metadata' => [Pact::CORE_URI => ['version' => Pact::VERSION, 'contract' => $contract, 'questions' => [$question]]]];
        $id = '11111111-1111-4111-8111-111111111111';
        $checkpoint = ($kind === 'peer_poll' || $known) ? ['remote_task_id' => 'remote-task', 'last_observation' => $remote] : [];
        $task = Task::query()->create(['id' => $id, 'connection_id' => $connection->id, 'user_id' => 'alice', 'federation_id' => 'fed', 'root_id' => $id, 'caller_id' => 'agent', 'state' => 'TASK_STATE_INPUT_REQUIRED', 'value' => $remote, 'checkpoint' => $checkpoint, 'version' => 1]);
        $raw = json_encode(['jsonrpc' => '2.0', 'id' => 'request', 'method' => $kind === 'peer_poll' ? 'GetTask' : 'SendMessage', 'params' => ['id' => 'remote-task']], JSON_THROW_ON_ERROR);
        $store = new Store;
        $job = $store->enqueue($kind, $id, ['connection_id' => $connection->id, 'recipient' => 'agent', 'contract' => $contract, 'raw' => $raw]);

        return [$store, $task, $job, $remote];
    }

    public function test_stale_poll_does_not_reopen_answered_questions_and_schedules_fresh_poll(): void
    {
        [$store, $task, $job, $remote] = $this->scenario('peer_poll');
        Http::fake(function () use ($task, $remote) {
            $checkpoint = $task->fresh()->checkpoint;
            $checkpoint['last_observation']['status']['state'] = 'TASK_STATE_WORKING';
            $task->update(['state' => 'TASK_STATE_WORKING', 'value' => ['status' => ['state' => 'TASK_STATE_WORKING']], 'checkpoint' => $checkpoint, 'version' => 2]);

            return Http::response(['jsonrpc' => '2.0', 'id' => 'request', 'result' => $remote], 200, ['A2A-Extensions' => Pact::CORE_URI]);
        });
        (new Worker($store))->workOnce();
        self::assertSame('done', $job->fresh()->state);
        self::assertSame('TASK_STATE_WORKING', $task->fresh()->state);
        self::assertSame('TASK_STATE_WORKING', $task->fresh()->checkpoint['last_observation']['status']['state']);
        self::assertSame(['status' => ['state' => 'TASK_STATE_WORKING']], $task->fresh()->value);
        self::assertSame(1, Outbox::query()->where('kind', 'peer_poll')->where('state', 'pending')->count());
    }

    public function test_stale_continuation_cannot_resurrect_a_canceled_task(): void
    {
        [$store, $task, $job, $remote] = $this->scenario('peer_send', true);
        Http::fake(function () use ($task, $remote) {
            $task->update(['state' => 'TASK_STATE_CANCELED', 'version' => 2]);
            $remote['status']['state'] = 'TASK_STATE_WORKING';

            return Http::response(['jsonrpc' => '2.0', 'id' => 'request', 'result' => $remote], 200, ['A2A-Extensions' => Pact::CORE_URI]);
        });
        (new Worker($store))->workOnce();
        self::assertSame('done', $job->fresh()->state);
        self::assertSame('TASK_STATE_CANCELED', $task->fresh()->state);
        self::assertSame('TASK_STATE_INPUT_REQUIRED', $task->fresh()->checkpoint['last_observation']['status']['state']);
    }

    public function test_initial_response_crossing_deadline_preserves_identity_and_queues_cancellation(): void
    {
        [$store, $task, $job, $remote] = $this->scenario('peer_send');
        $checkpoint = $task->checkpoint;
        $checkpoint['delegation_state'] = ['deadline' => now()->addSeconds(5)->timestamp];
        $checkpoint['recipient'] = 'agent';
        $checkpoint['contract'] = $remote['metadata'][Pact::CORE_URI]['contract'];
        $task->update(['checkpoint' => $checkpoint, 'state' => 'TASK_STATE_WORKING']);
        Http::fake(function () use ($remote) {
            $this->travel(6)->seconds();

            return Http::response(['jsonrpc' => '2.0', 'id' => 'request', 'result' => $remote], 200, ['A2A-Extensions' => Pact::CORE_URI]);
        });
        (new Worker($store))->workOnce();
        self::assertSame('failed', $job->fresh()->state);
        self::assertSame('TASK_STATE_FAILED', $task->fresh()->state);
        self::assertSame('remote-task', $task->fresh()->checkpoint['remote_task_id']);
        self::assertSame('remote-task', json_decode(Outbox::query()->where('kind', 'peer_cancel')->firstOrFail()->payload['raw'], true)['params']['id']);
        self::assertSame(1, Outbox::query()->where('kind', 'peer_cancel')->count());
    }

    public function test_initial_response_learns_remote_id_after_local_cancellation(): void
    {
        [$store, $task, $job, $remote] = $this->scenario('peer_send');
        Http::fake(function () use ($task, $remote) {
            $task->update(['state' => 'TASK_STATE_CANCELED', 'version' => 2]);

            return Http::response(['jsonrpc' => '2.0', 'id' => 'request', 'result' => $remote], 200, ['A2A-Extensions' => Pact::CORE_URI]);
        });
        (new Worker($store))->workOnce();
        self::assertSame('done', $job->fresh()->state);
        self::assertSame('TASK_STATE_CANCELED', $task->fresh()->state);
        self::assertSame('remote-task', $task->fresh()->checkpoint['remote_task_id']);
        self::assertSame('remote-task', json_decode(Outbox::query()->where('kind', 'peer_cancel')->firstOrFail()->payload['raw'], true)['params']['id']);
    }
}
