<?php

declare(strict_types=1);

namespace PactLaravel\Runtime;

use Illuminate\Support\Str;
use Pact\Client;
use Pact\Federation\DelegationState;
use Pact\Pact;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Outbox;
use PactLaravel\Models\Task;
use PactLaravel\OAuth\ConnectionService;
use PactLaravel\Support\HttpTransport;
use PactLaravel\Support\RetryableDelivery;

final class PeerDelivery
{
    public function __construct(private readonly Store $store, private readonly HttpTransport $http, private readonly ConnectionService $connections) {}

    public function resume(Outbox $job): void
    {
        $p = $job->payload;
        $connection = Connection::query()->whereKey($p['connection_id'])->where('status', 'active')->firstOrFail();
        $credentials = $connection->credentials ?? [];
        if (($credentials['expires_at'] ?? 0) <= now()->timestamp + 30) {
            $connection = $this->connections->refresh($connection);
            $credentials = $connection->credentials;
        }
        $url = rtrim($connection->remote_url, '/').'/'.trim(config('pact.prefix', 'pact'), '/').'/agents/'.rawurlencode($p['recipient']).'/resume';
        $response = $this->http->request('POST', $url, ['Authorization' => 'Bearer '.$credentials['access_token'], 'Content-Type' => 'application/json'], $p['raw']);
        if ($response['status'] >= 500 || in_array($response['status'], [408, 409, 429], true)) {
            throw RetryableDelivery::response($response);
        }
        if ($response['status'] !== 200 || ! ($response['body']['accepted'] ?? false)) {
            throw new \DomainException('Peer rejected continuation');
        }
    }

    public function send(Outbox $job, string $owner): void
    {
        $task = Task::query()->find($job->task_id);
        if ($job->kind === 'peer_poll' && isset($task?->checkpoint['pending_answer'])) {
            return;
        }
        $observedVersion = $task?->version;
        $knownIdAtRequest = $task?->checkpoint['remote_task_id'] ?? null;
        if ($task) {
            $root = Task::query()->find($task->root_id);
            $state = $root?->checkpoint['delegation_state'] ?? null;
            if ($job->kind !== 'peer_cancel' && is_array($state) && now()->timestamp >= ($state['deadline'] ?? PHP_INT_MAX)) {
                throw new \DomainException('Delegation deadline expired');
            }
        }
        $p = $job->payload;
        $connection = Connection::query()->whereKey($p['connection_id'])->where('status', 'active')->firstOrFail();
        $credentials = $connection->credentials ?? [];
        if (($credentials['expires_at'] ?? 0) <= now()->timestamp + 30) {
            $connection = $this->connections->refresh($connection);
            $credentials = $connection->credentials;
        }
        $url = rtrim($connection->remote_url, '/').'/'.trim(config('pact.prefix', 'pact'), '/').'/agents/'.rawurlencode($p['recipient']).'/a2a';
        $response = $this->http->request('POST', $url, ['Authorization' => 'Bearer '.$credentials['access_token'], 'Content-Type' => 'application/json', 'A2A-Version' => '1.0', 'A2A-Extensions' => Pact::CORE_URI], $p['raw']);
        if ($response['status'] >= 500 || in_array($response['status'], [408, 429], true)) {
            throw RetryableDelivery::response($response);
        }
        $wire = json_decode($p['raw'], true, 512, JSON_THROW_ON_ERROR);
        $envelope = $response['body'];
        $extensions = array_map('trim', explode(',', (string) ($response['headers']['a2a-extensions'] ?? '')));
        if (! in_array(Pact::CORE_URI, $extensions, true) || ! is_array($envelope) || ($envelope['jsonrpc'] ?? null) !== '2.0' || ($envelope['id'] ?? null) !== ($wire['id'] ?? null)) {
            throw new \DomainException('Invalid peer response envelope or activation');
        }
        if (isset($envelope['error'])) {
            if (($envelope['error']['data'][Pact::CORE_URI]['retryable'] ?? false) === true) {
                throw new RetryableDelivery('Peer reported a retryable error');
            }
            throw new \DomainException('Peer rejected the command');
        }
        $remote = $response['body']['result']['task'] ?? $response['body']['result'] ?? null;
        if ($response['status'] !== 200 || ! is_array($remote) || ! is_string($remote['id'] ?? null) || ! is_string($remote['status']['state'] ?? null)) {
            throw new \DomainException('Invalid peer response');
        }
        $contract = json_decode(json_encode($p['contract'], JSON_THROW_ON_ERROR));
        (new Client($url, '', $contract, __DIR__.'/../../resources/contracts/'.(($contract->contract_id ?? null) === 'diagnostic-ping' ? 'registry.json' : 'agent-chat/registry.json')))->task(json_decode(json_encode($remote, JSON_THROW_ON_ERROR)));
        $expired = $this->store->atomic(function () use ($job, $owner, $remote, $p, $wire, $observedVersion, $knownIdAtRequest) {
            $lease = Outbox::query()->whereKey($job->id)->where('owner', $owner)->where('lease_until', '>', now())->lockForUpdate()->first();
            $task = Task::query()->whereKey($job->task_id)->lockForUpdate()->first();
            if (! $lease || ! $task) {
                return;
            }
            $root = Task::query()->whereKey($task->root_id)->lockForUpdate()->first();
            $state = $root?->checkpoint['delegation_state'] ?? null;
            $deadlineExpired = $job->kind !== 'peer_cancel' && is_array($state) && now()->timestamp >= ($state['deadline'] ?? PHP_INT_MAX);
            $checkpoint = $task->checkpoint ?? [];
            $knownId = $checkpoint['remote_task_id'] ?? null;
            $knownContext = $checkpoint['last_observation']['contextId'] ?? null;
            if (($knownId !== null && $remote['id'] !== $knownId) || ($knownContext !== null && ($remote['contextId'] ?? null) !== $knownContext)) {
                throw new \DomainException('Peer task identity changed');
            }
            if (in_array($job->kind, ['peer_poll', 'peer_cancel'], true) && (($wireId = json_decode($p['raw'], true)['params']['id'] ?? null) !== $knownId || $knownId === null)) {
                throw new \DomainException('Peer task request identity mismatch');
            }
            if ($deadlineExpired) {
                if ($job->kind === 'peer_send' && $knownId === null) {
                    $checkpoint['remote_task_id'] = $remote['id'];
                    $checkpoint['last_observation'] = ['id' => $remote['id'], 'contextId' => $remote['contextId'] ?? null];
                    $task->checkpoint = $checkpoint;
                    $task->save();
                    $cancel = $p;
                    $cancel['raw'] = json_encode(['jsonrpc' => '2.0', 'id' => 'cancel:'.$task->id, 'method' => 'CancelTask', 'params' => ['id' => $remote['id']]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                    $this->store->enqueue('peer_cancel', $task->id, $cancel, 'cancel:'.$task->id);
                }

                return true;
            }
            if ($job->kind === 'peer_poll' && isset($checkpoint['pending_answer'])) {
                return;
            }
            if ($job->kind === 'peer_send' && isset($checkpoint['pending_answer'])) {
                if ($checkpoint['pending_answer'] !== ($wire['id'] ?? null)) {
                    return;
                }
                unset($checkpoint['pending_answer']);
            }
            // A concurrent answer or cancellation makes an existing task's response obsolete.
            // Start a fresh poll without applying any part of the old observation.
            if (($job->kind === 'peer_poll' || ($job->kind === 'peer_send' && $knownIdAtRequest !== null)) && $task->version !== $observedVersion) {
                if (! Pact::isTerminal($task->state)) {
                    $next = $p;
                    $next['raw'] = json_encode(['jsonrpc' => '2.0', 'id' => (string) Str::uuid(), 'method' => 'GetTask', 'params' => ['id' => $knownId]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                    $nextJob = $this->store->enqueue('peer_poll', $task->id, $next);
                    $nextJob->available_at = now()->addSeconds(2);
                    $nextJob->save();
                }

                return;
            }
            $checkpoint['remote_task_id'] = $remote['id'];
            $checkpoint['last_observation'] = $remote;
            $task->checkpoint = $checkpoint;
            if (! Pact::isTerminal($task->state)) {
                $task->state = $remote['status']['state'];
                $value = $remote;
                $value['id'] = $task->id;
                $task->value = $value;
                $task->version++;
            }
            $task->save();
            if ($task->state === 'TASK_STATE_CANCELED' && ! Pact::isTerminal($remote['status']['state'])) {
                $cancel = $p;
                $cancel['raw'] = json_encode(['jsonrpc' => '2.0', 'id' => 'cancel:'.$task->id, 'method' => 'CancelTask', 'params' => ['id' => $remote['id']]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                $this->store->enqueue('peer_cancel', $task->id, $cancel, 'cancel:'.$task->id);
            }
            $status = $remote['status']['state'];
            $root = Task::query()->whereKey($task->root_id)->lockForUpdate()->first();
            if ($root) {
                $rootCheckpoint = $root->checkpoint ?? [];
                $state = $rootCheckpoint['delegation_state'] ?? null;
                if (is_array($state) && isset($state['tasks'][$task->id])) {
                    $observation = match ($status) {
                        'TASK_STATE_COMPLETED' => 'completed','TASK_STATE_FAILED','TASK_STATE_REJECTED' => 'failed','TASK_STATE_CANCELED' => 'canceled',default => 'working'
                    };
                    $rootCheckpoint['delegation_state'] = DelegationState::observe($state, (string) $task->id, $observation);
                    $root->checkpoint = $rootCheckpoint;
                    $root->save();
                }
            }
            if (! Pact::isTerminal($status) && ! Pact::isTerminal($task->state)) {
                $raw = json_encode(['jsonrpc' => '2.0', 'id' => (string) Str::uuid(), 'method' => 'GetTask', 'params' => ['id' => $remote['id']]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                $next = $p;
                $next['raw'] = $raw;
                $nextJob = $this->store->enqueue('peer_poll', $task->id, $next);
                $nextJob->available_at = now()->addSeconds(2);
                $nextJob->save();
            } elseif ($task->parent_id && in_array($status, ['TASK_STATE_COMPLETED', 'TASK_STATE_FAILED', 'TASK_STATE_CANCELED', 'TASK_STATE_REJECTED'], true)) {
                $parent = Task::query()->whereKey($task->parent_id)->lockForUpdate()->first();
                if ($parent && ! Pact::isTerminal($parent->state)) {
                    $checkpoint = $parent->checkpoint ?? [];
                    if (($checkpoint['waiting_for'] ?? null) === $task->id) {
                        unset($checkpoint['waiting_for']);
                        $checkpoint['delegation_result'] = $remote;
                        $parent->checkpoint = $checkpoint;
                        $parent->state = 'TASK_STATE_WORKING';
                        $parent->version++;
                        $parent->save();
                        $parentCheckpoint = $parent->checkpoint ?? [];
                        if (isset($parentCheckpoint['remote_task_id'],$parentCheckpoint['recipient'])) {
                            $resume = json_encode(['task_id' => $parentCheckpoint['remote_task_id'], 'delegation_id' => $task->delegation_id, 'result' => $remote], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                            $this->store->enqueue('peer_resume', $parent->id, ['connection_id' => $parent->connection_id, 'recipient' => $parentCheckpoint['recipient'], 'raw' => $resume], 'resume:'.$task->id);
                        } else {
                            $this->store->enqueue('execute', $parent->id, [], (string) Str::uuid());
                        }
                    }
                }
            }
        });
        if ($expired) {
            throw new \DomainException('Delegation deadline expired');
        }
    }
}
