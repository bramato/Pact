<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use Pact\Federation\DelegationState;
use Pact\PactError;
use PactLaravel\Mcp\Coordinator;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Member;
use PactLaravel\Models\Outbox;
use PactLaravel\Models\Task;
use PactLaravel\Runtime\Store;
use PactLaravel\Runtime\Worker;

final class RuntimeReliabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePact();
    }

    private function family(bool $expired = false): array
    {
        $root = '11111111-1111-4111-8111-111111111111';
        $child = '22222222-2222-4222-8222-222222222222';
        $now = now()->timestamp;
        $state = DelegationState::start($root, 'a', $now - ($expired ? 301 : 0));
        $state = DelegationState::reserve($state, $root, $child, '33333333-3333-4333-8333-333333333333', 'b', $expired ? $now - 300 : $now);
        $base = ['user_id' => 'alice', 'federation_id' => 'fed', 'root_id' => $root, 'caller_id' => 'a', 'state' => 'TASK_STATE_WORKING', 'value' => ['status' => ['state' => 'TASK_STATE_WORKING']], 'version' => 1];
        $parent = Task::query()->create($base + ['id' => $root, 'checkpoint' => ['delegation_state' => $state, 'waiting_for' => $child]]);
        $task = Task::query()->create($base + ['id' => $child, 'parent_id' => $root, 'delegation_id' => '33333333-3333-4333-8333-333333333333', 'checkpoint' => []]);

        return [$parent, $task];
    }

    public function test_expired_root_fails_and_cancels_descendants(): void
    {
        [$root,$child] = $this->family(true);
        $store = new Store;
        $sibling = Task::query()->create(['id' => '44444444-4444-4444-8444-444444444444', 'connection_id' => '55555555-5555-4555-8555-555555555555', 'user_id' => 'alice', 'federation_id' => 'fed', 'root_id' => $root->id, 'parent_id' => $root->id, 'caller_id' => 'a', 'state' => 'TASK_STATE_WORKING', 'value' => ['status' => ['state' => 'TASK_STATE_WORKING']], 'checkpoint' => ['remote_task_id' => 'remote', 'recipient' => 'c', 'contract' => ['contract_id' => 'test']], 'version' => 1]);
        $child->connection_id = '55555555-5555-4555-8555-555555555555';
        $child->checkpoint = ['remote_task_id' => 'child-remote', 'recipient' => 'b', 'contract' => ['contract_id' => 'test']];
        $child->save();
        $job = $store->enqueue('peer_poll', $child->id, ['connection_id' => 'missing']);
        self::assertTrue((new Worker($store))->workOnce());
        self::assertSame('TASK_STATE_FAILED', $root->fresh()->state);
        self::assertSame('TASK_STATE_FAILED', $child->fresh()->state);
        self::assertSame('TASK_STATE_CANCELED', $sibling->fresh()->state);
        self::assertSame(2, Outbox::query()->where('kind', 'peer_cancel')->count());
        self::assertSame([$child->id, $sibling->id], Outbox::query()->where('kind', 'peer_cancel')->orderBy('task_id')->pluck('task_id')->all());
        self::assertSame('failed', $job->fresh()->state);
        self::assertSame('canceled', $root->fresh()->checkpoint['delegation_state']['tasks'][$root->id]['status']);
    }

    public function test_federation_has_a_stable_local_owner(): void
    {
        $store = new Store;
        $federation = $store->federationFor('alice');
        self::assertSame($store->ownerId('alice'), $store->roster($federation->id)->members[0]->id);
        self::assertSame('user', $store->roster($federation->id)->members[0]->kind);
        self::assertSame($federation->id, $store->federationFor('alice')->id);
        self::assertSame(1, Member::query()->count());
    }

    public function test_renewals_reach_more_than_one_hundred_connections_with_busy_queue(): void
    {
        config(['pact.role' => 'client']);
        $store = new Store;
        for ($i = 0; $i < 101; $i++) {
            Connection::query()->create(['user_id' => 'alice', 'federation_id' => 'fed'.$i, 'member_id' => 'member'.$i, 'remote_url' => 'https://peer.example', 'remote_user_id' => 'alice', 'status' => 'active', 'metadata' => ['credential_renew_after' => now()->subMinute()->toISOString()]]);
        }
        for ($i = 0; $i < 1010; $i++) {
            $store->enqueue('invalid-kind', null, []);
        }
        $worker = new Worker($store);
        for ($i = 0; $i < 1010; $i++) {
            $worker->workOnce();
        }
        self::assertSame(101, Connection::query()->get()->filter(fn ($c) => strtotime($c->metadata['credential_renew_after']) > now()->timestamp)->count());
        self::assertGreaterThan(0, Outbox::query()->where('state', 'pending')->count());
    }

    public function test_terminal_child_failure_unblocks_parent(): void
    {
        [$root,$child] = $this->family();
        $store = new Store;
        $store->enqueue('invalid-kind', $child->id, []);
        self::assertTrue((new Worker($store))->workOnce());
        $checkpoint = $root->fresh()->checkpoint;
        self::assertArrayNotHasKey('waiting_for', $checkpoint);
        self::assertSame('TASK_STATE_FAILED', $checkpoint['delegation_result']['status']['state']);
        self::assertSame('failed', $checkpoint['delegation_state']['tasks'][$child->id]['status']);
        self::assertSame(1, Outbox::query()->where('kind', 'execute')->count());
    }

    public function test_second_pending_delegation_is_rejected_before_budget_changes(): void
    {
        [$root] = $this->family();
        $connection = Connection::query()->create(['user_id' => 'alice', 'federation_id' => 'fed', 'member_id' => 'a', 'remote_url' => 'https://client.example', 'remote_user_id' => 'alice', 'status' => 'active']);
        $other = Connection::query()->create(['user_id' => 'alice', 'federation_id' => 'fed', 'member_id' => 'b', 'remote_url' => 'https://peer.example', 'remote_user_id' => 'alice', 'status' => 'active']);
        Member::query()->create(['user_id' => 'alice', 'federation_id' => 'fed', 'member_id' => 'b', 'connection_id' => $other->id, 'active' => true, 'manifest' => []]);
        try {
            (new Coordinator(new Store))->dispatch($connection, 'a', 'request_delegation', ['id' => 'second', 'recipient_id' => 'b', 'task_id' => $root->id, 'input' => ['message' => 'test']]);
            self::fail('Expected rejection');
        } catch (PactError $e) {
            self::assertSame('FEDERATION_DELEGATION', $e->errorCode);
        }
        self::assertSame(1, $root->fresh()->checkpoint['delegation_state']['delegations']);
        self::assertSame(0,Outbox::query()->count());
    }
}
