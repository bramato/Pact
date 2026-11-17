<?php

declare(strict_types=1);

namespace PactLaravel;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;
use Pact\Federation\DelegationState;
use Pact\Pact;
use PactLaravel\Contracts\IdentityResolver;
use PactLaravel\Mcp\Coordinator;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Receipt;
use PactLaravel\Models\Task;
use PactLaravel\OAuth\ConnectionService;
use PactLaravel\Runtime\Store;

final class PactManager
{
    public function __construct(private IdentityResolver $identities, private ConnectionService $connections, private Store $store, private Coordinator $coordinator, private Pact $pact) {}

    private function identity(?string $teamId = null): array
    {
        if (config('pact.role') !== 'master') {
            throw new \DomainException('This operation requires a PACT master.');
        }
        $user = $this->identities->current();
        if (! $user) {
            throw new AuthenticationException;
        }
        $id = $this->identities->id($user);
        if ($id === '') {
            throw new AuthenticationException;
        }

        return [$id, ($teamId === null ? $this->store->federationFor($id) : $this->store->teamFor($id, $teamId))->id, $this->store->ownerId($id)];
    }

    public function connectDomain(string $domain): string
    {
        [$user] = $this->identity();

        return $this->connections->connectDomain($domain, $user);
    }

    public function teams(): array
    {
        [$user] = $this->identity();

        return $this->store->teamsFor($user);
    }

    public function createTeam(string $name): array
    {
        [$user] = $this->identity();
        $team = $this->store->createTeam($user, $name);

        return ['id' => $team->id, 'name' => $team->name];
    }

    public function connectUrl(string $application): string
    {
        [$user] = $this->identity();

        return $this->connections->connectUrl($application, $user);
    }

    public function members(?string $teamId = null): array
    {
        [, $federation] = $this->identity($teamId);

        return $this->store->roster($federation)->members;
    }

    public function sendMessage(string $recipientId, string $text, ?string $idempotencyKey = null, ?string $teamId = null): array
    {
        [$user, $federation, $owner] = $this->identity($teamId);
        $context = new Connection(['user_id' => $user, 'federation_id' => $federation, 'member_id' => $owner]);

        return $this->coordinator->dispatch($context, $owner, 'send_message', ['id' => $idempotencyKey ?? (string) Str::uuid(), 'recipient_id' => $recipientId, 'text' => $text]);
    }

    private function ownedTask(string $user, string $federation, string $owner, string $id): Task
    {
        return Task::query()->where('user_id', $user)->where('federation_id', $federation)->where('caller_id', $owner)->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function task(string $taskId, ?string $teamId = null): array
    {
        [$user, $federation, $owner] = $this->identity($teamId);

        return $this->store->atomic(fn () => $this->ownedTask($user, $federation, $owner, $taskId)->value);
    }

    public function answer(string $taskId, array $answers, ?string $idempotencyKey = null, ?string $teamId = null): array
    {
        [$user, $federation, $owner] = $this->identity($teamId);
        $key = $idempotencyKey ?? (string) Str::uuid();
        if ($key === '' || strlen($key) > 191) {
            throw new \InvalidArgumentException('Invalid idempotency key.');
        }

        return $this->store->atomic(function () use ($user, $federation, $owner, $taskId, $answers, $key): array {
            $task = $this->ownedTask($user, $federation, $owner, $taskId);
            $scope = 'answer:'.$task->id;
            $digest = hash('sha256', json_encode($answers, JSON_THROW_ON_ERROR));
            $receipt = Receipt::query()->where('scope', $scope)->where('key', $key)->lockForUpdate()->first();
            if ($receipt) {
                Pact::ensure(hash_equals($receipt->digest, $digest), 'IDEMPOTENCY_CONFLICT', 'Answer key already used with different data');

                return $receipt->response;
            }
            Pact::ensure($task->state === 'TASK_STATE_INPUT_REQUIRED', 'QUESTION_CLOSED', 'Task is not awaiting answers');
            $checkpoint = $task->checkpoint ?? [];
            $remote = $checkpoint['last_observation'] ?? [];
            $remoteId = $checkpoint['remote_task_id'] ?? null;
            $context = $remote['contextId'] ?? null;
            $contract = $checkpoint['contract'] ?? null;
            $recipient = $checkpoint['recipient'] ?? null;
            Pact::ensure(is_string($remoteId) && is_string($context) && is_array($contract) && is_string($recipient), 'QUESTION_CONTEXT', 'Remote task context is unavailable');
            $questions = json_decode(json_encode($remote['metadata'][Pact::CORE_URI]['questions'] ?? [], JSON_THROW_ON_ERROR));
            $typedAnswers = json_decode(json_encode($answers, JSON_THROW_ON_ERROR));
            $registry = __DIR__.'/../resources/contracts/'.(($contract['contract_id'] ?? null) === 'diagnostic-ping' ? 'registry.json' : 'agent-chat/registry.json');
            $this->pact->answers($questions, $typedAnswers, (object) ['taskId' => $remoteId, 'contextId' => $context, 'expectedContextId' => $context, 'now' => now()->toISOString(), 'registryPath' => $registry]);
            $wireKey = 'answer_'.hash('sha256', $scope."\0".$key);
            $wire = ['jsonrpc' => '2.0', 'id' => $wireKey, 'method' => 'SendMessage', 'params' => ['message' => ['role' => 'ROLE_USER', 'messageId' => $wireKey, 'taskId' => $remoteId, 'contextId' => $context, 'parts' => [['data' => (object) []]], 'metadata' => [Pact::CORE_URI => ['version' => Pact::VERSION, 'contract' => $contract, 'idempotency_key' => $wireKey, 'answers' => $answers]]]]];
            $raw = json_encode($wire, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $this->store->enqueue('peer_send', $task->id, ['connection_id' => $task->connection_id, 'recipient' => $recipient, 'contract' => $contract, 'raw' => $raw], hash('sha256', $scope."\0".$key));
            $checkpoint['pending_answer'] = $wireKey;
            $task->checkpoint = $checkpoint;
            $task->state = 'TASK_STATE_WORKING';
            $value = $task->value;
            $value['status'] = ['state' => $task->state, 'timestamp' => now()->toISOString()];
            $task->value = $value;
            $task->version++;
            $task->save();
            $result = ['task_id' => $task->id, 'accepted' => true];
            Receipt::query()->create(['id' => (string) Str::uuid(), 'scope' => $scope, 'key' => $key, 'digest' => $digest, 'response' => $result]);

            return $result;
        });
    }

    public function cancel(string $taskId, ?string $teamId = null): void
    {
        [$user, $federation, $owner] = $this->identity($teamId);
        $this->store->atomic(function () use ($user, $federation, $owner, $taskId): void {
            $root = $this->ownedTask($user, $federation, $owner, $taskId);
            Pact::ensure($root->root_id === $root->id, 'TASK_NOT_CANCELABLE', 'Only root tasks can be canceled');
            if ($root->state === 'TASK_STATE_CANCELED') {
                return;
            }
            Pact::ensure(! Pact::isTerminal($root->state), 'TASK_NOT_CANCELABLE', 'Task is terminal');
            $family = Task::query()->where('user_id', $user)->where('federation_id', $federation)->where('root_id', $root->id)->lockForUpdate()->get();
            foreach ($family as $task) {
                if (Pact::isTerminal($task->state)) {
                    continue;
                }
                $checkpoint = $task->checkpoint ?? [];
                if ($task->id === $root->id && isset($checkpoint['delegation_state']['tasks'][$root->id])) {
                    $checkpoint['delegation_state'] = DelegationState::cancel($checkpoint['delegation_state'], $root->id);
                }
                $task->checkpoint = $checkpoint;
                $task->state = 'TASK_STATE_CANCELED';
                $value = $task->value;
                $value['status'] = ['state' => $task->state, 'timestamp' => now()->toISOString()];
                $task->value = $value;
                $task->version++;
                $task->save();
                if (isset($checkpoint['remote_task_id'], $checkpoint['recipient'], $checkpoint['contract'])) {
                    $raw = json_encode(['jsonrpc' => '2.0', 'id' => 'cancel:'.$task->id, 'method' => 'CancelTask', 'params' => ['id' => $checkpoint['remote_task_id']]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                    $this->store->enqueue('peer_cancel', $task->id, ['connection_id' => $task->connection_id, 'recipient' => $checkpoint['recipient'], 'contract' => $checkpoint['contract'], 'raw' => $raw], 'cancel:'.$task->id);
                }
            }
        });
    }
}
