<?php

declare(strict_types=1);

namespace PactLaravel\Runtime;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Pact\Federation\DelegationState;
use Pact\Federation\Policy;
use Pact\Pact;
use Pact\PactError;
use PactLaravel\Execution\AgentContext;
use PactLaravel\Execution\AgentResult;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Outbox;
use PactLaravel\Models\Receipt;
use PactLaravel\Models\Task;
use stdClass;

final class TaskService
{
    public function __construct(private readonly Store $store, private readonly Pact $pact) {}

    private function progress(array $value, string $stage, bool $complete = false): array
    {
        $previous = $value['metadata'][Pact::CORE_URI]['progress']['sequence'] ?? null;
        $progress = (object) ['sequence' => $previous === null ? 0 : $previous + 1, 'stage' => $stage, 'completed_units' => $complete ? 1 : 0, 'total_units' => 1, 'percentage' => $complete ? 100 : 0, 'updated_at' => now()->utc()->format('Y-m-d\\TH:i:s.v\\Z')];
        $this->pact->progress($progress, $previous);
        $value['metadata'][Pact::CORE_URI]['progress'] = (array) $progress;

        return $value;
    }

    private function registry(): string
    {
        return config('pact.contract_registry') ?: __DIR__.'/../../resources/contracts/registry.json';
    }

    public function contract(): stdClass
    {
        $path = config('pact.contract_path') ?: __DIR__.'/../../resources/contracts/contract.json';

        return json_decode(file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
    }

    public function params(): stdClass
    {
        return (object) ['versions' => [Pact::VERSION], 'contracts' => [$this->contract()], 'features' => ['contracts', 'questions', 'events', 'progress']];
    }

    public function manifest(string $userId): array
    {
        return $this->card(Policy::externalAgentId((string) config('pact.product_id'), $userId));
    }

    public function card(string $agent): array
    {
        $base = rtrim(config('pact.url'), '/').'/'.trim(config('pact.prefix', 'pact'), '/').'/agents/'.rawurlencode($agent);

        return ['name' => 'PACT '.$agent, 'version' => Pact::VERSION, 'supportedInterfaces' => [['url' => $base.'/a2a', 'protocolBinding' => 'JSONRPC', 'protocolVersion' => '1.0']], 'capabilities' => ['streaming' => false, 'extensions' => [['uri' => Pact::CORE_URI, 'required' => true, 'params' => $this->params()]]], 'defaultInputModes' => ['application/json'], 'defaultOutputModes' => ['application/json'], 'skills' => config('pact.handler') ? [['id' => $this->contract()->contract_id, 'name' => 'Application agent', 'description' => 'Application configured agent', 'tags' => ['agent']]] : [['id' => 'diagnostic-ping', 'name' => 'Diagnostic ping', 'description' => 'Checks connectivity', 'tags' => ['diagnostic']]]];
    }

    public function rpc(Connection $connection, string $agent, stdClass $request, string $raw): mixed
    {
        Pact::ensure(($request->jsonrpc ?? null) === '2.0' && (is_string($request->id ?? null) || is_int($request->id ?? null)) && ($request->params ?? null) instanceof stdClass, 'INVALID_REQUEST', 'Invalid JSON-RPC request');

        return $this->store->atomic(function () use ($connection, $agent, $request, $raw) {
            $p = $request->params;
            if (in_array($request->method, ['GetTask', 'CancelTask'], true)) {
                Pact::ensure(is_string($p->id ?? null), 'INVALID_REQUEST', 'Task ID required');
                $task = Task::query()->where('connection_id', $connection->id)->where('user_id', $connection->user_id)->whereKey($p->id)->lockForUpdate()->first();
                Pact::ensure($task !== null, 'TASK_NOT_FOUND', 'Task not found');
                if ($request->method === 'GetTask' && $task->state === 'TASK_STATE_INPUT_REQUIRED') {
                    $value = $task->value;
                    $changed = false;
                    $requiredExpired = false;
                    foreach ($value['metadata'][Pact::CORE_URI]['questions'] as &$q) {
                        if ($q['status'] === 'open' && ! empty($q['expires_at']) && now()->greaterThanOrEqualTo(Carbon::parse($q['expires_at']))) {
                            $q['status'] = 'expired';
                            $changed = true;
                            if ($q['required']) {
                                $requiredExpired = true;
                            }
                        }
                    }
                    unset($q);
                    if ($changed) {
                        if ($requiredExpired) {
                            $task->state = 'TASK_STATE_FAILED';
                            $value['status'] = ['state' => $task->state, 'timestamp' => now()->toISOString()];
                            $value['metadata'][Pact::CORE_URI]['error'] = ['code' => 'QUESTION_EXPIRED', 'message' => 'Required question expired', 'retryable' => false];
                        } $task->value = $value;
                        $task->version++;
                        $task->save();
                    }
                }
                if ($request->method === 'CancelTask') {
                    Pact::ensure(! Pact::isTerminal($task->state), 'TASK_NOT_CANCELABLE', 'Task is terminal');
                    $queue = [(string) $task->id];
                    while ($queue) {
                        $cancelId = array_shift($queue);
                        $item = Task::query()->where('federation_id', $connection->federation_id)->whereKey($cancelId)->lockForUpdate()->first();
                        if (! $item) {
                            continue;
                        }
                        foreach (Task::query()->where('federation_id', $connection->federation_id)->where('parent_id', $cancelId)->pluck('id') as $childId) {
                            $queue[] = (string) $childId;
                        }
                        if (Pact::isTerminal($item->state)) {
                            continue;
                        }
                        $item->state = 'TASK_STATE_CANCELED';
                        $value = $item->value;
                        $value['status'] = ['state' => $item->state, 'timestamp' => now()->toISOString()];
                        $item->value = $value;
                        $item->version++;
                        $item->save();
                        $checkpoint = $item->checkpoint ?? [];
                        if (isset($checkpoint['remote_task_id'],$checkpoint['recipient']) && $item->connection_id) {
                            $raw = json_encode(['jsonrpc' => '2.0', 'id' => 'cancel:'.$item->id, 'method' => 'CancelTask', 'params' => ['id' => $checkpoint['remote_task_id']]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                            $this->store->enqueue('peer_cancel', $item->id, ['connection_id' => $item->connection_id, 'raw' => $raw, 'recipient' => $checkpoint['recipient'], 'contract' => $checkpoint['contract'] ?? []], 'cancel:'.$item->id);
                        }
                    }
                    $root = Task::query()->whereKey($task->root_id)->lockForUpdate()->first();
                    if ($root) {
                        $c = $root->checkpoint ?? [];
                        if (isset($c['delegation_state']['tasks'][$task->id])) {
                            $c['delegation_state'] = DelegationState::cancel($c['delegation_state'], (string) $task->id);
                            $root->checkpoint = $c;
                            $root->save();
                        }
                    }
                    $task->refresh();
                }

                return $task->value;
            }
            Pact::ensure($request->method === 'SendMessage', 'METHOD_NOT_FOUND', 'Unknown method');
            $message = $p->message ?? null;
            Pact::ensure($message instanceof stdClass && ($message->role ?? null) === 'ROLE_USER' && is_string($message->messageId ?? null) && is_array($message->parts ?? null) && count($message->parts) === 1 && isset($message->parts[0]->data), 'INVALID_REQUEST', 'Structured message required');
            Pact::ensure(count(array_intersect(['text', 'data', 'raw', 'url'], array_keys(get_object_vars($message->parts[0])))) === 1, 'INVALID_PARAMS', 'Part must contain one content field');
            $meta = $message->metadata->{Pact::CORE_URI} ?? null;
            $this->pact->shape('metadata', $meta);
            Pact::ensure(($meta->version ?? null) === Pact::VERSION && is_string($meta->idempotency_key ?? null), 'IDEMPOTENCY_REQUIRED', 'Version and idempotency key required');
            $this->pact->negotiate($this->params(), (object) ['version' => $meta->version, 'contract' => $meta->contract, 'active' => [Pact::CORE_URI], 'swarm' => false]);
            $scope = 'a2a:'.$connection->id.':'.$agent;
            $digest = hash('sha256', $raw);
            $receipt = Receipt::query()->where('scope', $scope)->where('key', $meta->idempotency_key)->lockForUpdate()->first();
            if ($receipt) {
                Pact::ensure(hash_equals($receipt->digest, $digest), 'IDEMPOTENCY_CONFLICT', 'Key already used with different bytes');

                return $receipt->response;
            }
            if (isset($message->taskId)) {
                $task = Task::query()->where('connection_id', $connection->id)->whereKey($message->taskId)->lockForUpdate()->first();
                Pact::ensure($task !== null && $task->state === 'TASK_STATE_INPUT_REQUIRED', 'TASK_NOT_FOUND', 'Open task required');
                $value = $task->value;
                $questions = json_decode(json_encode($value['metadata'][Pact::CORE_URI]['questions']));
                $questions = $this->pact->answers($questions, $meta->answers ?? [], (object) ['taskId' => $task->id, 'contextId' => $message->contextId ?? '', 'expectedContextId' => $value['contextId'], 'now' => now()->toISOString(), 'registryPath' => $this->registry()]);
                $value['metadata'][Pact::CORE_URI]['questions'] = json_decode(json_encode($questions), true);
                $checkpoint = $task->checkpoint;
                foreach ($meta->answers ?? [] as $answer) {
                    $checkpoint['answers'][$answer->response_to] = json_decode(json_encode($answer->value, JSON_THROW_ON_ERROR), true);
                }
                if (! count(array_filter($questions, fn ($q) => $q->required && $q->status === 'open'))) {
                    $task->state = 'TASK_STATE_WORKING';
                    $value['status'] = ['state' => $task->state, 'timestamp' => now()->toISOString()];
                    $this->store->enqueue('execute', $task->id, [], (string) Str::uuid());
                }
                $task->value = $value;
                $task->checkpoint = $checkpoint;
                $task->version++;
                $task->save();
            } else {
                Pact::ensure(! isset($message->contextId), 'QUESTION_CONTEXT', 'New task cannot provide a context');
                $this->pact->contractData($this->contract()->input, $message->parts[0]->data, $this->registry());
                $id = (string) Str::uuid();
                $value = ['id' => $id, 'contextId' => (string) Str::uuid(), 'status' => ['state' => 'TASK_STATE_WORKING', 'timestamp' => now()->toISOString()], 'metadata' => [Pact::CORE_URI => ['version' => Pact::VERSION, 'contract' => (array) $this->contract(), 'questions' => []]]];
                $value = $this->progress($value, 'execution');
                $task = Task::query()->create(['id' => $id, 'connection_id' => $connection->id, 'user_id' => $connection->user_id, 'federation_id' => $connection->federation_id, 'root_id' => $id, 'caller_id' => $connection->member_id, 'state' => 'TASK_STATE_WORKING', 'value' => $value, 'checkpoint' => ['input' => (array) $message->parts[0]->data], 'version' => 1]);
                $this->store->enqueue('execute', $id, [], $id);
            }
            $response = ['task' => $task->value];
            Receipt::query()->create(['id' => (string) Str::uuid(), 'scope' => $scope, 'key' => $meta->idempotency_key, 'digest' => $digest, 'response' => $response]);

            return $response;
        });
    }

    public function execute(string $id, string $owner): void
    {
        $snapshot = $this->store->atomic(function () use ($id, $owner) {
            $job = Outbox::query()->where('task_id', $id)->where('owner', $owner)->where('lease_until', '>', now())->lockForUpdate()->first();
            $task = Task::query()->whereKey($id)->lockForUpdate()->first();

            return $job && $task && $task->state === 'TASK_STATE_WORKING' ? ['task' => $task, 'job_id' => $job->id, 'version' => $task->version] : null;
        });
        if (! $snapshot) {
            return;
        }
        $task = $snapshot['task'];
        $checkpoint = $task->checkpoint ?? [];
        $input = $checkpoint['input'] ?? [];
        $root = Task::query()->find($task->root_id);
        $state = $root?->checkpoint['delegation_state'] ?? null;
        if (is_array($state) && now()->timestamp >= ($state['deadline'] ?? PHP_INT_MAX)) {
            throw new \DomainException('Delegation deadline expired');
        }
        $handler = config('pact.handler');
        if ($handler) {
            $result = app($handler)->handle(new AgentContext((string) $task->id, (string) $task->user_id, $input, $checkpoint, $checkpoint['answers'] ?? [], (string) $task->federation_id, (string) $task->root_id, (string) $snapshot['job_id'], $checkpoint['delegation_state'] ?? [], $checkpoint['delegation_result'] ?? null));
            if (! $result instanceof AgentResult) {
                throw new \UnexpectedValueException('Handler must return AgentResult');
            }
        } else {
            $output = ['pong' => true];
            if (isset($input['message'])) {
                $output['message'] = $input['message'];
            }
            $result = AgentResult::completed($output);
        }
        try {
            if ($result->state === 'completed') {
                $this->pact->contractData($this->contract()->output, json_decode(json_encode($result->value, JSON_THROW_ON_ERROR)), $this->registry());
            }
            if ($result->state === 'question') {
                foreach ($result->value as $question) {
                    $q = json_decode(json_encode($question, JSON_THROW_ON_ERROR));
                    $this->pact->shape('question', $q);
                    Pact::ensure($q->task_id === $id, 'QUESTION_CORRELATION', 'Question task differs');
                }
            }
        } catch (PactError $e) {
            $result = AgentResult::error(['code' => $e->errorCode, 'message' => $e->getMessage(), 'retryable' => false]);
        }
        $this->store->atomic(function () use ($id, $owner, $snapshot, $result) {
            $job = Outbox::query()->whereKey($snapshot['job_id'])->where('owner', $owner)->where('lease_until', '>', now())->lockForUpdate()->first();
            $task = Task::query()->whereKey($id)->lockForUpdate()->first();
            if (! $job || ! $task || $task->state !== 'TASK_STATE_WORKING' || $task->version !== $snapshot['version']) {
                return;
            }
            $root = Task::query()->find($task->root_id);
            $state = $root?->checkpoint['delegation_state'] ?? null;
            if (is_array($state) && now()->timestamp >= ($state['deadline'] ?? PHP_INT_MAX)) {
                throw new \DomainException('Delegation deadline expired');
            }
            $value = $task->value;
            $checkpoint = array_merge($task->checkpoint ?? [], $result->checkpoint);
            if ($result->state === 'completed') {
                foreach ($value['metadata'][Pact::CORE_URI]['questions'] ?? [] as $q) {
                    Pact::ensure(! ($q['required'] && in_array($q['status'], ['open', 'expired'], true)), 'QUESTION_OPEN', 'Required question remains open');
                }
                $task->state = 'TASK_STATE_COMPLETED';
                $value['artifacts'] = [['artifactId' => (string) Str::uuid(), 'extensions' => [Pact::CORE_URI], 'metadata' => [Pact::CORE_URI => ['contract' => (array) $this->contract()]], 'parts' => [['data' => $result->value]]]];
            } elseif ($result->state === 'question') {
                $task->state = 'TASK_STATE_INPUT_REQUIRED';
                $value['metadata'][Pact::CORE_URI]['questions'] = $result->value;
            } elseif ($result->state === 'error') {
                $task->state = 'TASK_STATE_FAILED';
                $value['metadata'][Pact::CORE_URI]['error'] = $result->value;
            } else {
                $task->state = 'TASK_STATE_WORKING';
                $checkpoint['_waiting'] = true;
            }
            $task->checkpoint = $checkpoint;
            $value['status'] = ['state' => $task->state, 'timestamp' => now()->toISOString()];
            $value = $this->progress($value,'execution',$task->state === 'TASK_STATE_COMPLETED');
            $task->value = $value;
            $task->version++;
            $task->save();
        });
    }
}
