<?php

declare(strict_types=1);

namespace PactLaravel\Mcp;

use Illuminate\Support\Str;
use Pact\Federation\DelegationState;
use Pact\Pact;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Member;
use PactLaravel\Models\Receipt;
use PactLaravel\Models\Task;
use PactLaravel\Runtime\Store;

final class Coordinator
{
    public function __construct(private readonly Store $store) {}

    public function taskForActor(string $federation, string $actor, string $id): Task
    {
        $tasks = Task::query()->where('federation_id', $federation)->lockForUpdate()->get();
        foreach ($tasks as $task) {
            $checkpoint = $task->checkpoint ?? [];
            if (($task->caller_id === $actor && $task->id === $id) || (($checkpoint['recipient'] ?? null) === $actor && ($checkpoint['remote_task_id'] ?? null) === $id)) {
                return $task;
            }
        }
        throw new \DomainException('Task not found');
    }

    public function dispatch(Connection $caller, string $actor, string $method, array $args): array
    {
        return $this->store->atomic(function () use ($caller, $actor, $method, $args) {
            $id = $args['id'] ?? null;
            $recipient = $args['recipient_id'] ?? null;
            Pact::ensure(is_string($id) && $id !== '' && strlen($id) <= 191 && is_string($recipient) && $recipient !== '', 'FEDERATION_ARGUMENTS', 'ID and recipient required');
            $scope = 'exchange:'.$caller->federation_id.':'.$actor;
            $digest = hash('sha256', json_encode([$method, $args], JSON_THROW_ON_ERROR));
            $receipt = Receipt::query()->where('scope', $scope)->where('key', $id)->lockForUpdate()->first();
            if ($receipt) {
                Pact::ensure(hash_equals($receipt->digest, $digest), 'IDEMPOTENCY_CONFLICT', 'Exchange ID already used');

                return $receipt->response;
            }
            $member = Member::query()->where('federation_id', $caller->federation_id)->where('member_id', $recipient)->where('active', true)->firstOrFail();
            $remote = Connection::query()->whereKey($member->connection_id)->where('status', 'active')->firstOrFail();
            $parent = null;
            if ($method === 'request_delegation') {
                $parent = $this->taskForActor((string) $caller->federation_id, $actor, (string) ($args['task_id'] ?? ''));
                Pact::ensure(! Pact::isTerminal($parent->state), 'TASK_TERMINAL', 'Parent is terminal');
                Pact::ensure(empty(($parent->checkpoint ?? [])['waiting_for']), 'FEDERATION_DELEGATION', 'Parent already has a pending delegation');
            }
            $manifest = $member->manifest ?? [];
            $contract = null;
            $diagnostic = null;
            foreach (($manifest['capabilities']['extensions'] ?? []) as $extension) {
                if (($extension['uri'] ?? null) === Pact::CORE_URI) {
                    foreach (($extension['params']['contracts'] ?? []) as $candidate) {
                        if (($candidate['contract_id'] ?? null) === 'pact.agent.chat.v1') {
                            $contract = $candidate;
                        } if (($candidate['contract_id'] ?? null) === 'diagnostic-ping') {
                            $diagnostic = $candidate;
                        }
                    }
                }
            }
            if (! $contract && $method === 'send_message') {
                $contract = $diagnostic;
            }
            Pact::ensure(is_array($contract), 'CONTRACT_UNSUPPORTED', 'Member has not advertised a supported contract');
            $child = (string) Str::uuid();
            $delegation = (string) Str::uuid();
            $rootId = $parent ? $parent->root_id : $child;
            if ($parent) {
                $root = Task::query()->whereKey($rootId)->lockForUpdate()->firstOrFail();
                $checkpoint = $root->checkpoint ?? [];
                $state = $checkpoint['delegation_state'] ?? DelegationState::start((string) $rootId, ($checkpoint['recipient'] ?? $actor), $root->created_at->timestamp);
                $checkpoint['delegation_state'] = DelegationState::reserve($state, (string) $parent->id, $child, $delegation, $recipient, now()->timestamp);
                $root->checkpoint = $checkpoint;
                $root->save();
                $parent->refresh();
                $p = $parent->checkpoint ?? [];
                $p['waiting_for'] = $child;
                $parent->checkpoint = $p;
                $parent->version++;
                $parent->save();
            }
            $base = rtrim((string) config('pact.url'), '/').'/'.trim(config('pact.prefix', 'pact'), '/');
            if ($method === 'request_delegation') {
                Pact::ensure(is_array($args['input'] ?? null) && count(array_diff(array_keys($args['input']), ['message'])) === 0, 'FEDERATION_SWARM', 'Unsupported or nested delegation input');
            }
            $text = $method === 'send_message' ? ($args['text'] ?? '') : ($args['input']['message'] ?? '');
            Pact::ensure(is_string($text) && $text !== '', 'FEDERATION_ARGUMENTS', 'Message required');
            $input = ['message' => $text, 'coordinator' => ['endpoint' => $base.'/mcp', 'federation_id' => $caller->federation_id, 'member_id' => $recipient]];
            if ($parent) {
                $input['parent_task_id'] = $parent->id;
            }
            if (($contract['contract_id'] ?? null) === 'diagnostic-ping') {
                $input = ['message' => $text];
            }
            $wireKey = 'exchange_'.hash('sha256', $scope."\0".$id);
            $wire = ['jsonrpc' => '2.0', 'id' => $wireKey, 'method' => 'SendMessage', 'params' => ['message' => ['messageId' => $wireKey, 'role' => 'ROLE_USER', 'parts' => [['data' => $input]], 'metadata' => [Pact::CORE_URI => ['version' => Pact::VERSION, 'contract' => $contract, 'idempotency_key' => $wireKey]]]]];
            app(Pact::class)->contractData(json_decode(json_encode($contract['input'], JSON_THROW_ON_ERROR)), json_decode(json_encode($input, JSON_THROW_ON_ERROR)), __DIR__.'/../../resources/contracts/'.(($contract['contract_id'] ?? null) === 'diagnostic-ping' ? 'registry.json' : 'agent-chat/registry.json'));
            $raw = json_encode($wire, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            Task::query()->create(['id' => $child, 'connection_id' => $remote->id, 'user_id' => $caller->user_id, 'federation_id' => $caller->federation_id, 'root_id' => $rootId, 'parent_id' => $parent?->id, 'delegation_id' => $parent ? $delegation : null, 'caller_id' => $actor, 'state' => 'TASK_STATE_SUBMITTED', 'value' => ['id' => $child, 'status' => ['state' => 'TASK_STATE_SUBMITTED']], 'checkpoint' => ['recipient' => $recipient, 'contract' => $contract] + ($parent ? [] : ['delegation_state' => DelegationState::start($child, $recipient, now()->timestamp)]), 'version' => 1]);
            $this->store->enqueue('peer_send', $child, ['connection_id' => $remote->id, 'raw' => $raw, 'recipient' => $recipient, 'contract' => $contract], $scope.':'.$id);
            $result = ['task_id' => $child, 'delegation_id' => $parent ? $delegation : null, 'accepted' => true];
            Receipt::query()->create(['id' => (string) Str::uuid(), 'scope' => $scope, 'key' => $id, 'digest' => $digest, 'response' => $result]);

            return $result;
        });
    }
}
