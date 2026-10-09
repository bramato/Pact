<?php

declare(strict_types=1);

namespace PactReference;

use Pact\HttpBinding;
use Pact\Pact;
use Pact\PactError;
use stdClass;

final class Service implements HttpBinding
{
    public readonly Pact $pact;

    public readonly stdClass $contract;

    public readonly string $registry;

    public function __construct(public readonly Store $store, public readonly stdClass $config)
    {
        $this->pact = new Pact;
        $this->contract = json_decode(file_get_contents(__DIR__.'/../../examples/v0.2/contract.json'));
        $this->registry = __DIR__.'/../../examples/v0.2/schema-registry.json';
    }

    public static function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');
    }

    public static function uuid(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function card(string $agent): stdClass
    {
        $base = $this->config->url.($agent === 'coordinator' ? '' : '/agents/'.$agent);

        return (object) ['name' => 'PACT '.$agent, 'description' => 'Deterministic PACT v0.2 reference peer', 'version' => Pact::VERSION,
            'supportedInterfaces' => [(object) ['url' => $base.'/a2a', 'protocolBinding' => 'JSONRPC', 'protocolVersion' => '1.0']],
            'capabilities' => (object) ['streaming' => false, 'extensions' => [(object) ['uri' => Pact::CORE_URI, 'required' => true, 'params' => $this->params()], (object) ['uri' => Pact::SWARM_URI, 'required' => false, 'params' => (object) ['versions' => [Pact::VERSION], 'dependencies' => [Pact::CORE_URI]]]]],
            'securitySchemes' => (object) ['bearer' => (object) ['httpAuthSecurityScheme' => (object) ['scheme' => 'Bearer']]], 'securityRequirements' => [(object) ['schemes' => (object) ['bearer' => (object) ['list' => []]]]],
            'defaultInputModes' => ['application/json'], 'defaultOutputModes' => ['application/json'], 'skills' => [(object) ['id' => 'document-review', 'name' => 'Document review', 'description' => 'Count words and return an approved summary', 'tags' => ['pact', 'deterministic']]]];
    }

    public function params(): stdClass
    {
        return (object) ['versions' => [Pact::VERSION], 'contracts' => [$this->contract], 'features' => ['contracts', 'questions', 'events', 'progress', 'swarm']];
    }

    public function principal(string $authorization): stdClass
    {
        foreach ($this->config->tokens as $entry) {
            if (hash_equals('Bearer '.$entry->token, $authorization)) {
                return $entry;
            }
        }
        throw new PactError('AUTHENTICATION', 'Authentication required');
    }

    /** Framework-independent transport adapter. Returns [HTTP status, headers, JSON object]. */
    public function handle(string $method, string $path, array $headers, string $raw): array
    {
        $headers = array_change_key_case($headers, CASE_LOWER);
        $id = null;
        $active = [];
        try {
            if ($method === 'GET' && preg_match('#^(?:/agents/(extract|review|assemble))?/\.well-known/agent-card\.json$#', $path, $m)) {
                return [200, [], $this->card($m[1] ?? 'coordinator')];
            }
            $principal = $this->principal($headers['authorization'] ?? '');
            Pact::ensure(strlen($raw) <= 1048576, 'REQUEST_SIZE', 'Request exceeds 1 MiB');
            $active = array_values(array_unique(array_filter(array_map('trim', explode(',', $headers['a2a-extensions'] ?? '')), fn ($uri) => in_array($uri, [Pact::CORE_URI, Pact::SWARM_URI], true))));
            Pact::ensure(($headers['a2a-version'] ?? null) === '1.0', 'A2A_VERSION', 'A2A version is not supported');
            Pact::ensure(in_array(Pact::CORE_URI, $active, true), 'PACT_UNSUPPORTED', 'Core activation is required');
            if ($path === '/events') {
                return [200, ['A2A-Extensions' => implode(', ', $active)], $this->events($method, $principal, $raw, in_array(Pact::SWARM_URI, $active, true))];
            }
            Pact::ensure($method === 'POST' && preg_match('#^(?:/agents/(extract|review|assemble))?/a2a$#', $path, $matches) === 1, 'METHOD_NOT_FOUND', 'Unknown route');
            $agent = $matches[1] ?? 'coordinator';
            $request = Pact::parse($raw);
            $id = $request->id ?? null;
            Pact::ensure(($request->jsonrpc ?? null) === '2.0' && (is_string($id) || is_int($id)) && isset($request->method,$request->params), 'INVALID_REQUEST', 'Invalid JSON-RPC request');
            if ($agent !== 'coordinator') {
                Pact::ensure($principal->role === 'coordinator', 'AUTHORIZATION', 'Contributor accepts only coordinator delegation');
            }
            $result = $this->store->atomic(function () use ($agent, $principal, $request, $raw, $active) {
                try {
                    return $this->rpc($agent, $principal, $request, $raw, $active);
                } catch (PactError $e) {
                    if ($e->errorCode !== 'QUESTION_EXPIRED') {
                        throw $e;
                    }
                    $record = $this->store->task($principal->tenant, $agent, $request->params->message->taskId);
                    if ($record) {
                        [$expiredTask,$expiredInternal] = $record;
                        $this->expire($expiredTask, $expiredInternal);
                        $this->store->save($principal->tenant, $agent, $expiredTask, $expiredInternal);
                    }

                    return $e;
                }
            });
            if ($result instanceof PactError) {
                throw $result;
            }

            return [200, ['A2A-Extensions' => implode(', ', $active)], (object) ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]];
        } catch (PactError $e) {
            $status = match ($e->errorCode) {
                'AUTHENTICATION' => 401,'AUTHORIZATION' => 403,default => 200
            };
            $code = match ($e->errorCode) {
                'TASK_NOT_FOUND' => -32001,'TASK_NOT_CANCELABLE' => -32002,'PACT_UNSUPPORTED' => -32008,'A2A_VERSION' => -32009,'METHOD_NOT_FOUND' => -32601,'INVALID_REQUEST' => -32600,'JSON_SYNTAX' => -32700,default => -32602
            };

            return [$status, ['A2A-Extensions' => implode(', ', $active)], (object) ['jsonrpc' => '2.0', 'id' => $id, 'error' => (object) ['code' => $code, 'message' => $e->getMessage(), 'data' => (object) [Pact::CORE_URI => (object) ['code' => $e->errorCode, 'message' => $e->getMessage(), 'retryable' => $e->retryable]]]]];
        } catch (\JsonException $e) {
            return [200, [], (object) ['jsonrpc' => '2.0', 'id' => null, 'error' => (object) ['code' => -32700, 'message' => 'Invalid JSON']]];
        } catch (\Throwable $e) {
            error_log('PACT adapter: '.get_class($e));

            return [500, [], (object) ['error' => 'Internal adapter failure']];
        }
    }

    private function rpc(string $agent, stdClass $principal, stdClass $request, string $raw, array $active): mixed
    {
        $tenant = $principal->tenant;
        $params = $request->params;
        if ($request->method === 'GetTask' || $request->method === 'CancelTask') {
            Pact::ensure(is_string($params->id ?? null), 'INVALID_REQUEST', 'Task ID required');
            $record = $this->store->task($tenant, $agent, $params->id);
            Pact::ensure($record !== null, 'TASK_NOT_FOUND', 'Task not found');
            [$task,$internal] = $record;
            if ($request->method === 'CancelTask') {
                Pact::ensure(! Pact::isTerminal($task->status->state), 'TASK_NOT_CANCELABLE', 'Task is terminal');
                $this->state($task, 'TASK_STATE_CANCELED');
                if ($agent === 'coordinator' && $internal->swarm) {
                    $requested = [];
                    foreach ($internal->children as $child) {
                        if (! $child->settled) {
                            $requested[] = $child->child_id;
                            if ($child->task_id) {
                                $cancel = Store::encode((object) ['jsonrpc' => '2.0', 'id' => self::uuid(), 'method' => 'CancelTask', 'params' => (object) ['id' => $child->task_id]]);
                                $this->store->outbox($tenant, $task->id, $child->child_id, 'cancel', $this->childEndpoint($child->assignee), $cancel);
                            }
                        }
                    }
                    if ($requested) {
                        $task->metadata->{Pact::SWARM_URI}->cancellation = (object) ['requested' => $requested, 'observed' => []];
                        $this->notification($tenant, $task, $internal, 'cancellation.requested', (object) ['child_ids' => $requested]);
                    }
                }
                $this->notification($tenant, $task, $internal, 'task.status', (object) ['state' => $task->status->state]);
            } else {
                $this->expire($task, $internal);
                if ($agent !== 'coordinator' && $internal->deferred && $task->status->state === 'TASK_STATE_WORKING') {
                    $this->complete($task, $internal);
                    $internal->deferred = false;
                }
            }
            $this->store->save($tenant, $agent, $task, $internal);

            return $task;
        }
        Pact::ensure($request->method === 'SendMessage', 'METHOD_NOT_FOUND', 'Method not supported');
        $message = $params->message ?? null;
        Pact::ensure($message instanceof stdClass && ($message->role ?? null) === 'ROLE_USER' && is_string($message->messageId ?? null) && is_array($message->parts ?? null) && count($message->parts) === 1 && isset($message->parts[0]->data), 'INVALID_REQUEST', 'Structured user message required');
        Pact::ensure(count(array_intersect(['text', 'data', 'raw', 'url'], array_keys(get_object_vars($message->parts[0])))) === 1, 'INVALID_PARAMS', 'A2A Part must have exactly one content field');
        $meta = $message->metadata->{Pact::CORE_URI} ?? null;
        $this->pact->shape('metadata', $meta);
        Pact::ensure($meta->version === Pact::VERSION, 'PACT_VERSION', 'Version differs');
        Pact::ensure(is_string($meta->idempotency_key ?? null), 'IDEMPOTENCY_REQUIRED', 'Acceptance key required');
        $swarm = in_array(Pact::SWARM_URI, $active, true);
        $this->pact->negotiate($this->params(), (object) ['version' => $meta->version, 'contract' => $meta->contract, 'active' => $active, 'swarm' => $swarm]);
        $rows = $this->store->query('SELECT digest,response FROM commands WHERE tenant=? AND caller=? AND agent=? AND key=?', [$tenant, $principal->producer, $agent, $meta->idempotency_key]);
        if ($rows) {
            Pact::ensure(hash_equals($rows[0]['digest'], hash('sha256', $raw)), 'IDEMPOTENCY_CONFLICT', 'Acceptance key already binds different bytes');

            return json_decode($rows[0]['response']);
        }
        if (isset($message->taskId)) {
            $record = $this->store->task($tenant, $agent, $message->taskId);
            Pact::ensure($record !== null, 'TASK_NOT_FOUND', 'Task not found');
            [$task,$internal] = $record;
            Pact::ensure(! Pact::isTerminal($task->status->state), 'TASK_TERMINAL', 'Task is terminal');
            // Expiry is persisted by GetTask; reject answer without committing any batch mutation.
            $questions = $this->pact->answers($task->metadata->{Pact::CORE_URI}->questions ?? [], $meta->answers ?? [], (object) ['taskId' => $task->id, 'contextId' => $message->contextId ?? '', 'expectedContextId' => $task->contextId, 'now' => self::now(), 'registryPath' => $this->registry]);
            $task->metadata->{Pact::CORE_URI}->questions = $questions;
            foreach ($meta->answers as $answer) {
                $internal->answers->{$answer->response_to} = $answer->value;
            }
            if (! count(array_filter($questions, fn ($q) => $q->required && $q->status === 'open'))) {
                $this->state($task, 'TASK_STATE_WORKING');
                $this->notification($tenant, $task, $internal, 'task.status', (object) ['state' => 'TASK_STATE_WORKING']);
                $this->start($tenant, $agent, $task, $internal);
            }
        } else {
            Pact::ensure(! isset($message->contextId), 'QUESTION_CONTEXT', 'New reference task creates its context');
            $input = $message->parts[0]->data;
            $this->pact->contractData($this->contract->input, $input, $this->registry);
            Pact::ensure(! $input->swarm || $swarm, 'SWARM_REQUIRED', 'Swarm input needs activation');
            $id = self::uuid();
            $task = (object) ['id' => $id, 'contextId' => self::uuid(), 'status' => (object) ['state' => 'TASK_STATE_SUBMITTED', 'timestamp' => self::now()],
                'metadata' => (object) [Pact::CORE_URI => (object) ['version' => Pact::VERSION, 'contract' => $this->contract, 'questions' => []]]];
            $internal = (object) ['input' => $input, 'swarm' => $input->swarm && $agent === 'coordinator', 'children' => [], 'answers' => new stdClass, 'sequence' => 0, 'progress_history' => [], 'deferred' => false, 'caller' => $principal->producer, 'delegation' => $message->metadata->{Pact::SWARM_URI} ?? null];
            if ($input->clarify) {
                foreach (['approval', 'scope'] as $qid) {
                    $task->metadata->{Pact::CORE_URI}->questions[] = (object) ['question_id' => $qid, 'task_id' => $id, 'prompt' => $qid === 'approval' ? 'Approve this review?' : 'Approve the document scope?', 'answer_schema' => $this->contract->question_answer, 'required' => true, 'expires_at' => $input->deadline ?? null, 'status' => 'open'];
                }$this->state($task, 'TASK_STATE_INPUT_REQUIRED');
            } else {
                $this->state($task, 'TASK_STATE_WORKING');
                $this->notification($tenant, $task, $internal, 'task.status', (object) ['state' => 'TASK_STATE_WORKING']);
                $this->start($tenant, $agent, $task, $internal);
            }
        }
        $this->notification($tenant, $task, $internal, 'task.status', (object) ['state' => $task->status->state]);
        $this->store->save($tenant, $agent, $task, $internal);
        $result = (object) ['task' => $task];
        $this->store->execute('INSERT INTO commands VALUES(?,?,?,?,?,?)', [$tenant, $principal->producer, $agent, $meta->idempotency_key, hash('sha256', $raw), Store::encode($result)]);

        return $result;
    }

    private function start(string $tenant, string $agent, stdClass $task, stdClass $internal): void
    {
        if ($internal->swarm) {
            $plan = (object) ['children' => [(object) ['child_id' => 'extract', 'depends_on' => [], 'required' => true, 'weight' => 1], (object) ['child_id' => 'review', 'depends_on' => ['extract'], 'required' => true, 'weight' => 2], (object) ['child_id' => 'assemble', 'depends_on' => ['extract', 'review'], 'required' => true, 'weight' => 1]], 'aggregation_policy' => (object) ['type' => 'all_required']];
            if ($internal->input->optional_failure ?? false) {
                $plan->children[] = (object) ['child_id' => 'audit', 'depends_on' => [], 'required' => false, 'weight' => 1];
            }
            $this->pact->graph($plan);
            $task->metadata->{Pact::SWARM_URI} = (object) ['plan' => $plan, 'contributors' => []];
            foreach ($plan->children as $definition) {
                $child = clone $definition;
                $child->assignee = $child->child_id === 'audit' ? 'review' : $child->child_id;
                $child->attempt = 1;
                $child->task_id = null;
                $child->state = 'TASK_STATE_SUBMITTED';
                $child->percentage = 0;
                $child->settled = false;
                $child->history = [];
                $child->output = null;
                $internal->children[] = $child;
            }
            $this->progress($tenant, $task, $internal);
            $this->schedule($tenant, $task, $internal);
        } else {
            $delegation = $internal->delegation;
            $fail = $agent === 'review' && (($internal->input->optional_failure ?? false) && ($delegation->child_id ?? '') === 'audit' || ($internal->input->retry_review ?? false) && ($delegation->child_id ?? '') === 'review' && ($delegation->attempt ?? 1) === 1);
            if ($fail) {
                $this->state($task, 'TASK_STATE_FAILED');
                $task->metadata->{Pact::CORE_URI}->error = (object) ['code' => 'DEMO_ATTEMPT_FAILED', 'message' => 'Deterministic contributor failure', 'retryable' => false];
            } elseif ($agent === 'review' && ($internal->input->defer_review ?? false)) {
                $internal->deferred = true;
            } else {
                $this->complete($task, $internal);
            }
            if ($agent !== 'coordinator') {
                $this->store->execute('INSERT INTO effects VALUES(?,?,?)', [$tenant, $agent, $task->id]);
            }
        }
    }

    public function state(stdClass $task, string $next, bool $valid = false): void
    {
        $this->pact->transition($task->status->state, $next, $valid, $task->metadata->{Pact::CORE_URI}->questions ?? []);
        $task->status = (object) ['state' => $next, 'timestamp' => self::now()];
    }

    public function complete(stdClass $task, stdClass $internal, ?stdClass $output = null): void
    {
        $words = preg_split('/\s+/u', trim($internal->input->text), -1, PREG_SPLIT_NO_EMPTY);
        $approved = $internal->delegation->approval ?? true;
        foreach ($internal->answers as $answer) {
            $approved = $approved && $answer->approved;
        }
        $dependencies = $internal->delegation->dependencies ?? null;
        if ($dependencies instanceof stdClass) {
            foreach ($dependencies as $predecessor) {
                $this->pact->contractData($this->contract->output, $predecessor, $this->registry);
            }
            if (isset($dependencies->extract)) {
                Pact::ensure($dependencies->extract->word_count === count($words), 'DEPENDENCY_DATA', 'Predecessor word count differs');
                $words = preg_split('/\s+/u', $dependencies->extract->summary, -1, PREG_SPLIT_NO_EMPTY);
            }
            if (isset($dependencies->review)) {
                $approved = $dependencies->review->approved;
            }
        }
        $output ??= (object) ['summary' => implode(' ', array_slice($words, 0, 12)), 'word_count' => isset($dependencies->extract) ? $dependencies->extract->word_count : count($words), 'approved' => $approved];
        $this->pact->contractData($this->contract->output, $output, $this->registry);
        $task->artifacts = [(object) ['artifactId' => 'review', 'parts' => [(object) ['data' => $output]], 'extensions' => [Pact::CORE_URI], 'metadata' => (object) [Pact::CORE_URI => (object) ['version' => Pact::VERSION, 'contract' => $this->contract]]]];
        $task->metadata->{Pact::CORE_URI}->progress = (object) ['sequence' => ++$internal->sequence, 'stage' => 'validation', 'completed_units' => 1, 'total_units' => 1, 'percentage' => 100, 'updated_at' => self::now()];
        $this->state($task, 'TASK_STATE_COMPLETED', true);
    }

    private function expire(stdClass $task, stdClass $internal): void
    {
        if (Pact::isTerminal($task->status->state)) {
            return;
        }
        $required = false;
        foreach ($task->metadata->{Pact::CORE_URI}->questions ?? [] as $q) {
            if ($q->status === 'open' && $q->expires_at !== null && (float) (new \DateTimeImmutable($q->expires_at))->format('U.u') <= microtime(true)) {
                $q->status = 'expired';
                $required = $required || $q->required;
            }
        }
        if ($required) {
            $this->state($task, 'TASK_STATE_FAILED');
        }
    }

    public function childEndpoint(string $agent): string
    {
        return $this->config->url.'/agents/'.$agent.'/a2a';
    }

    public function schedule(string $tenant, stdClass $task, stdClass $internal): void
    {
        if (Pact::isTerminal($task->status->state)) {
            return;
        }
        foreach ($internal->children as $child) {
            if ($child->settled || $child->state !== 'TASK_STATE_SUBMITTED') {
                continue;
            }
            $ready = true;
            foreach ($child->depends_on as $dep) {
                $previous = array_values(array_filter($internal->children, fn ($c) => $c->child_id === $dep))[0];
                $ready = $ready && $previous->state === 'TASK_STATE_COMPLETED' && $previous->output !== null;
            }
            if (! $ready) {
                continue;
            }
            $input = clone $internal->input;
            $input->swarm = false;
            $input->clarify = false;
            $approval = true;
            foreach ($internal->answers as $answer) {
                $approval = $approval && $answer->approved;
            }
            $dependencies = [];
            foreach ($child->depends_on as $dep) {
                $previous = array_values(array_filter($internal->children, fn ($c) => $c->child_id === $dep))[0];
                $dependencies[$dep] = $previous->output;
            }
            $message = (object) ['messageId' => self::uuid(), 'role' => 'ROLE_USER', 'parts' => [(object) ['data' => $input]], 'extensions' => [Pact::CORE_URI, Pact::SWARM_URI],
                'metadata' => (object) [Pact::CORE_URI => (object) ['version' => Pact::VERSION, 'contract' => $this->contract, 'idempotency_key' => $task->id.':'.$child->child_id.':'.$child->attempt], Pact::SWARM_URI => (object) ['parent_task_id' => $task->id, 'child_id' => $child->child_id, 'attempt' => $child->attempt, 'approval' => $approval, 'dependencies' => (object) $dependencies]]];
            $raw = Store::encode((object) ['jsonrpc' => '2.0', 'id' => self::uuid(), 'method' => 'SendMessage', 'params' => (object) ['message' => $message]]);
            $child->state = 'TASK_STATE_WORKING';
            $this->store->outbox($tenant, $task->id, $child->child_id, 'child', $this->childEndpoint($child->assignee), $raw);
        }
    }

    public function progress(string $tenant, stdClass $task, stdClass $internal, int $aggregation = 0, int $validation = 0): void
    {
        $children = [];
        $scope = [];
        foreach ($internal->children as $c) {
            $children[] = (object) ['child_id' => $c->child_id, 'attempt' => $c->attempt, 'weight' => $c->weight, 'state' => $c->state, 'settled' => $c->settled, 'percentage' => $c->percentage];
            if ($c->required) {
                $scope[] = $c->child_id;
            }
        }
        $previous = $task->metadata->{Pact::SWARM_URI}->progress->high_watermark ?? null;
        $snapshot = $this->pact->swarmProgress((object) ['generation' => 1, 'sequence' => ++$internal->sequence, 'scope_child_ids' => $scope, 'children' => $children, 'stages' => [(object) ['id' => 'execution', 'weight' => 80, 'percentage' => null], (object) ['id' => 'aggregation', 'weight' => 10, 'percentage' => $aggregation], (object) ['id' => 'validation', 'weight' => 10, 'percentage' => $validation]], 'previous_high_watermark' => $previous, 'updated_at' => self::now()]);
        $task->metadata->{Pact::SWARM_URI}->progress = $snapshot;
        $internal->progress_history[] = $snapshot;
        $task->metadata->{Pact::SWARM_URI}->contributors = array_map(fn ($c) => (object) ['child_id' => $c->child_id, 'required' => $c->required, 'history' => $c->history], $internal->children);
        $this->notification($tenant, $task, $internal, 'swarm.progress', $snapshot);
    }

    public function notification(string $tenant, stdClass $task, stdClass $internal, string $type, stdClass $payload): void
    {
        if ($type !== 'task.status' && $type !== 'swarm.progress' && ! $internal->swarm) {
            return;
        }
        if ($type === 'swarm.progress' && ! $internal->swarm) {
            return;
        }
        $event = Pact::signEvent((object) ['event_id' => self::uuid(), 'task_id' => $task->id, 'producer_id' => 'coordinator', 'sequence' => ++$internal->sequence, 'type' => $type, 'occurred_at' => self::now(), 'correlation_id' => $task->contextId, 'causation_id' => null, 'payload' => $payload]);
        $this->pact->event($event, $internal->swarm);
        $task->metadata->{Pact::CORE_URI}->event = $event;
        $this->store->outbox($tenant, $task->id, null, 'event', $this->config->url.'/events', Store::encode($event));
    }

    private function events(string $method, stdClass $principal, string $raw, bool $swarm): mixed
    {
        Pact::ensure($method === 'POST', 'METHOD_NOT_FOUND', 'Events require POST');
        $event = Pact::parse($raw);
        Pact::ensure(($event->producer_id ?? null) === $principal->producer, 'PRODUCER_IDENTITY', 'Authenticated producer differs');

        return $this->store->atomic(function () use ($principal, $event, $raw, $swarm) {
            $rows = $this->store->query('SELECT digest,disposition FROM events WHERE tenant=? AND producer=? AND id=?', [$principal->tenant, $principal->producer, $event->event_id]);
            if ($rows) {
                Pact::ensure(hash_equals($rows[0]['digest'], hash('sha256', $raw)), 'EVENT_CONFLICT', 'Event ID changed bytes');

                return (object) ['disposition' => 'duplicate'];
            }
            $this->pact->event($event, $swarm);
            $rows = $this->store->query('SELECT sequence,value FROM projections WHERE tenant=? AND producer=? AND task_id=?', [$principal->tenant, $principal->producer, $event->task_id]);
            $stale = $rows && $event->sequence <= $rows[0]['sequence'];
            if (! $stale && $event->type === 'task.status') {
                $states = $this->store->query('SELECT state FROM observed_states WHERE tenant=? AND producer=? AND task_id=?', [$principal->tenant, $principal->producer, $event->task_id]);
                $validated = false;
                if ($event->payload->state === 'TASK_STATE_COMPLETED') {
                    $known = $this->store->query('SELECT value FROM tasks WHERE tenant=? AND id=?', [$principal->tenant, $event->task_id]);
                    Pact::ensure(count($known) === 1, 'OUTPUT_REQUIRED', 'Completion requires an independently available artifact');
                    $native = json_decode($known[0]['value']);
                    Pact::ensure(count($native->artifacts ?? []) > 0, 'OUTPUT_REQUIRED', 'Completion has no artifact');
                    foreach ($native->artifacts as $artifact) {
                        $this->pact->contractData($this->contract->output, $artifact->parts[0]->data, $this->registry);
                    }
                    $validated = true;
                }
                if ($states) {
                    $this->pact->transition($states[0]['state'], $event->payload->state, $validated);
                }
                $this->store->execute('INSERT INTO observed_states VALUES(?,?,?,?) ON CONFLICT(tenant,producer,task_id) DO UPDATE SET state=excluded.state', [$principal->tenant, $principal->producer, $event->task_id, $event->payload->state]);
            }
            $disposition = $stale ? 'stale' : 'accepted';
            $this->store->execute('INSERT INTO events VALUES(?,?,?,?,?,?,?)', [$principal->tenant, $principal->producer, $event->event_id, $event->task_id, $raw, hash('sha256', $raw), $disposition]);
            if (! $stale) {
                $this->store->execute('INSERT INTO projections VALUES(?,?,?,?,?) ON CONFLICT(tenant,producer,task_id) DO UPDATE SET sequence=excluded.sequence,value=excluded.value', [$principal->tenant, $principal->producer, $event->task_id, $event->sequence, Store::encode($event)]);
            }

            return (object) ['disposition' => $disposition];
        });
    }
}
