<?php

declare(strict_types=1);

namespace PactLaravel\Runtime;

use Illuminate\Support\Str;
use Pact\Federation\DelegationState;
use Pact\Pact;
use Pact\PactError;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Heartbeat;
use PactLaravel\Models\LinkRequest;
use PactLaravel\Models\Outbox;
use PactLaravel\Models\Task;
use PactLaravel\OAuth\ConnectionService;
use PactLaravel\Support\HttpTransport;
use PactLaravel\Support\RetryableDelivery;
use Throwable;

final class Worker
{
    private const QUEUE_RESERVATION_PREFIX = 'queue:';

    private const QUEUE_REDISPATCH_SECONDS = 60;

    public function __construct(private readonly Store $store) {}

    public function workOnce(): bool
    {
        LinkRequest::query()->where('expires_at', '<=', now())->whereNotNull('payload')->update(['payload' => null]);
        static $turn = 0;
        if (++$turn % 10 === 0 && $this->renewDue()) {
            return true;
        }
        $queue = ExecutionQueue::fromConfig();
        Heartbeat::query()->updateOrCreate(['id' => 'worker'], ['updated_at' => now()]);
        $job = $this->store->atomic(function () use ($queue) {
            $job = Outbox::query()->where('state', 'pending')->where('available_at', '<=', now())->where(function ($q) {
                $q->whereNull('lease_until')->orWhere('lease_until', '<', now());
            })->orderBy('available_at')->lockForUpdate()->first();
            if (! $job) {
                return null;
            }
            if ($queue && $job->kind === 'execute') {
                // Keep the reservation valid for an earlier copy still waiting in Laravel's queue.
                if (! str_starts_with((string) $job->owner, self::QUEUE_RESERVATION_PREFIX)) {
                    $job->owner = self::QUEUE_RESERVATION_PREFIX.Str::uuid();
                }
                $job->lease_until = now()->addSeconds(self::QUEUE_REDISPATCH_SECONDS);
                $job->save();
            } else {
                $this->claim($job);
            }

            return $job;
        });
        if (! $job) {
            return $this->renewDue();
        }

        if ($queue && $job->kind === 'execute') {
            try {
                $queue->dispatch($job->id, $job->owner);
            } catch (Throwable $e) {
                // The persisted reservation expires so the outbox can retry publication.
                report($e);
            }
        } else {
            $this->process($job);
        }

        return true;
    }

    public function executeQueued(string $outboxId, string $reservation): void
    {
        if (! str_starts_with($reservation, self::QUEUE_RESERVATION_PREFIX)) {
            return;
        }

        $job = $this->store->atomic(function () use ($outboxId, $reservation) {
            $job = Outbox::query()->whereKey($outboxId)->where('kind', 'execute')
                ->where('state', 'pending')->where('owner', $reservation)->lockForUpdate()->first();
            if ($job) {
                $this->claim($job);
            }

            return $job;
        });

        if ($job) {
            $this->process($job);
        }
    }

    private function claim(Outbox $job): void
    {
        $job->owner = (string) Str::uuid();
        $job->lease_until = now()->addSeconds((int) config('pact.worker.lease_seconds', 60));
        $job->attempts++;
        $job->save();
    }

    private function process(Outbox $job): void
    {
        $owner = $job->owner;
        try {
            $payload = $job->payload;
            if ($job->kind === 'activate') {
                app(ConnectionService::class)->activate(Connection::query()->findOrFail($payload['connection_id']), $payload['generation'] ?? null);
            } elseif ($job->kind === 'disconnect_client') {
                app(ConnectionService::class)->deliverClientDisconnect(Connection::query()->findOrFail($payload['connection_id']), $payload['generation'] ?? null);
            } elseif ($job->kind === 'disconnect') {
                app(ConnectionService::class)->deliverDisconnect(Connection::query()->findOrFail($payload['connection_id']), $payload['generation'] ?? null);
            } elseif ($job->kind === 'peer_resume') {
                app(PeerDelivery::class)->resume($job);
            } elseif (in_array($job->kind, ['peer_send', 'peer_poll', 'peer_cancel'], true)) {
                app(PeerDelivery::class)->send($job, $owner);
            } elseif ($job->kind === 'execute') {
                app(TaskService::class)->execute((string) $job->task_id, $owner);
            } elseif ($job->kind === 'http') {
                $result = app(HttpTransport::class)->request($payload['method'], $payload['url'], $payload['headers'] ?? [], $payload['body'] ?? null);
                if (($result['status'] ?? 500) >= 500 || in_array(($result['status'] ?? 0), [408, 429], true)) {
                    throw RetryableDelivery::response($result);
                }
                if (($result['status'] ?? 500) >= 400) {
                    throw new \DomainException('Remote rejected request');
                }
            } else {
                throw new \DomainException('Unknown outbox kind');
            }
            $this->finish($job->id, $owner, null, false);
        } catch (Throwable $e) {
            $nonretryable = $e instanceof \DomainException || ($e instanceof PactError && ! $e->retryable);
            $this->finish($job->id, $owner, $e, $nonretryable || $job->attempts >= (int) config('pact.worker.max_attempts', 5));
        }
    }

    private function renewDue(): bool
    {
        if (config('pact.role') !== 'client') {
            return false;
        }
        foreach (Connection::query()->where('status', 'active')->orderBy('id')->cursor() as $candidate) {
            $connection = $this->store->atomic(function () use ($candidate) {
                $locked = Connection::query()->whereKey($candidate->id)->where('status', 'active')->lockForUpdate()->first();
                if (! $locked) {
                    return null;
                }
                $metadata = $locked->metadata ?? [];
                $due = $metadata['credential_renew_after'] ?? null;
                if (! $due || strtotime((string) $due) > now()->timestamp) {
                    return null;
                }
                $metadata['credential_renew_after'] = now()->addMinutes(5)->toISOString();
                $locked->metadata = $metadata;
                $locked->save();

                return $locked;
            });
            if (! $connection) {
                continue;
            }
            try {
                app(ConnectionService::class)->renewPeerCredentials($connection);
            } catch (Throwable $e) {
                report($e);
            }

            return true;
        }

        return false;
    }

    private function finish(string $id, string $owner, ?Throwable $error, bool $terminal): void
    {
        $this->store->atomic(function () use ($id, $owner, $error, $terminal) {
            $job = Outbox::query()->whereKey($id)->where('owner', $owner)->where('lease_until', '>', now())->lockForUpdate()->first();
            if (! $job) {
                return;
            }
            $job->state = $error ? ($terminal ? 'failed' : 'pending') : 'done';
            $job->error = $error ? substr($error->getMessage(), 0, 1000) : null;
            $delay = random_int(0, min(300, 2 ** min($job->attempts, 8)));
            if ($error instanceof RetryableDelivery) {
                $delay = max($delay, $error->retryAfter);
            }
            $job->available_at = now()->addSeconds($delay);
            $job->lease_until = null;
            $job->owner = null;
            $job->save();
            if ($error && $terminal && $job->task_id) {
                $task = Task::query()->whereKey($job->task_id)->lockForUpdate()->first();
                if ($task && ! Pact::isTerminal($task->state)) {
                    $task->state = 'TASK_STATE_FAILED';
                    $value = $task->value;
                    $value['status'] = ['state' => $task->state, 'timestamp' => now()->toISOString()];
                    $value['metadata'][Pact::CORE_URI]['error'] = ['code' => 'EXECUTION_FAILED', 'message' => 'Execution failed', 'retryable' => false];
                    $task->value = $value;
                    $task->version++;
                    $task->save();
                    if ($error->getMessage() === 'Delegation deadline expired') {
                        $family = Task::query()->where('federation_id', $task->federation_id)->where('root_id', $task->root_id)->lockForUpdate()->get();
                        foreach ($family as $member) {
                            if (Pact::isTerminal($member->state) && $member->id !== $task->id) {
                                continue;
                            }
                            if ($member->id !== $task->id) {
                                $member->state = $member->id === $task->root_id ? 'TASK_STATE_FAILED' : 'TASK_STATE_CANCELED';
                                $memberValue = $member->value;
                                $memberValue['status'] = ['state' => $member->state, 'timestamp' => now()->toISOString()];
                                $member->value = $memberValue;
                                $member->version++;
                                $member->save();
                            }
                            $c = $member->checkpoint ?? [];
                            if (isset($c['remote_task_id'],$c['recipient'],$c['contract'])) {
                                $raw = json_encode(['jsonrpc' => '2.0', 'id' => 'cancel:'.$member->id, 'method' => 'CancelTask', 'params' => ['id' => $c['remote_task_id']]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                                $this->store->enqueue('peer_cancel', $member->id, ['connection_id' => $member->connection_id, 'recipient' => $c['recipient'], 'contract' => $c['contract'], 'raw' => $raw], 'cancel:'.$member->id);
                            }
                        }
                        $root = Task::query()->whereKey($task->root_id)->lockForUpdate()->first();
                        if ($root) {
                            $c = $root->checkpoint ?? [];
                            if (isset($c['delegation_state']['tasks'][$root->id])) {
                                $c['delegation_state'] = DelegationState::cancel($c['delegation_state'], (string) $root->id);
                                $root->checkpoint = $c;
                                $root->save();
                            }
                        }
                    }
                    if ($task->parent_id) {
                        $root = Task::query()->whereKey($task->root_id)->lockForUpdate()->first();
                        if ($root) {
                            $state = $root->checkpoint ?? [];
                            if (isset($state['delegation_state']['tasks'][$task->id])) {
                                $state['delegation_state'] = DelegationState::observe($state['delegation_state'], (string) $task->id, 'failed');
                                $root->checkpoint = $state;
                                $root->save();
                            }
                        }
                        $parent = Task::query()->whereKey($task->parent_id)->lockForUpdate()->first();
                        $checkpoint = $parent?->checkpoint ?? [];
                        if ($parent && ! Pact::isTerminal($parent->state) && ($checkpoint['waiting_for'] ?? null) === $task->id) {
                            unset($checkpoint['waiting_for']);
                            $checkpoint['delegation_result'] = $value;
                            $parent->checkpoint = $checkpoint;
                            $parent->version++;
                            $parent->save();
                            if (isset($checkpoint['remote_task_id'],$checkpoint['recipient'])) {
                                $raw = json_encode(['task_id' => $checkpoint['remote_task_id'], 'delegation_id' => $task->delegation_id, 'result' => $value], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                                $this->store->enqueue('peer_resume', $parent->id, ['connection_id' => $parent->connection_id, 'recipient' => $checkpoint['recipient'], 'raw' => $raw], 'resume:'.$task->id);
                            } else {
                                $this->store->enqueue('execute', $parent->id, [], (string) Str::uuid());
                            }
                        }
                    }
                }
            }
        });
    }
}
