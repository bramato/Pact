<?php

declare(strict_types=1);

namespace PactReference;

use Pact\Client;
use Pact\Pact;
use Pact\PactError;
use stdClass;

final class Worker
{
    private readonly string $owner;

    public function __construct(private readonly Service $service)
    {
        $this->owner = Service::uuid();
    }

    public function step(): bool
    {
        $store = $this->service->store;
        $row = $store->claim($this->owner);
        if (! $row) {
            return false;
        }
        $principal = array_values(array_filter($this->service->config->tokens, fn ($t) => $t->role === 'coordinator' && $t->tenant === $row['tenant']))[0] ?? null;
        if (! $principal) {
            $this->failed($row, new PactError('SECURITY_SCOPE', 'No coordinator credential'));

            return true;
        }
        try {
            $record = $store->task($row['tenant'], 'coordinator', $row['parent']);
            if ($row['kind'] === 'child' && $row['attempts'] === 1 && $record && Pact::isTerminal($record[0]->status->state)) {
                $store->atomic(function () use ($row, $record) {
                    $owned = $this->service->store->query('SELECT owner FROM outbox WHERE id=?', [$row['id']]);
                    if (($owned[0]['owner'] ?? null) !== $this->owner) {
                        return;
                    }
                    [$task,$internal] = $record;
                    foreach ($internal->children as $c) {
                        if ($c->child_id === $row['child'] && ! $c->task_id) {
                            $c->state = 'TASK_STATE_CANCELED';
                            $c->settled = true;
                        }
                    }
                    $this->service->store->execute('UPDATE outbox SET state="abandoned",owner=NULL,lease_until=0 WHERE id=? AND owner=?', [$row['id'], $this->owner]);
                    $task->metadata->{Pact::SWARM_URI}->contributors = array_map(fn ($c) => (object) ['child_id' => $c->child_id, 'required' => $c->required, 'history' => $c->history], $internal->children);
                    $this->service->store->save($row['tenant'], 'coordinator', $task, $internal);
                });

                return true;
            }
            if ($row['kind'] === 'event') {
                $result = $this->notify($row, $principal->token);
            } else {
                $client = new Client($row['endpoint'], $principal->token, $this->service->contract, $this->service->registry, true);
                try {
                    $result = $client->raw($row['raw']);
                } catch (PactError$e) {
                    if ($row['kind'] !== 'cancel' || $e->errorCode !== 'TASK_NOT_CANCELABLE') {
                        throw $e;
                    }
                    $request = json_decode($row['raw']);
                    $result = $client->raw(Store::encode((object) ['jsonrpc' => '2.0', 'id' => Service::uuid(), 'method' => 'GetTask', 'params' => (object) ['id' => $request->params->id]]));
                }
                $task = $row['kind'] === 'child' ? $result->task : $result;
                $client->task($task);
                // A real remote commit has returned, but local acknowledgment has not happened.
                if ($row['kind'] === 'child' && getenv('PACT_CRASH_MARKER')) {
                    file_put_contents(getenv('PACT_CRASH_MARKER'), $task->id);
                    posix_kill(getmypid(), 9);
                    exit(99);
                }
                $result = $task;
            }
            $store->atomic(function () use ($row, $result) {
                $owned = $this->service->store->query('SELECT owner FROM outbox WHERE id=?', [$row['id']]);
                Pact::ensure(($owned[0]['owner'] ?? null) === $this->owner, 'LEASE_LOST', 'Worker lease was replaced');
                $this->service->store->execute('UPDATE outbox SET state="delivered",result=?,owner=NULL,lease_until=0 WHERE id=?', [Store::encode($result), $row['id']]);
                if ($row['kind'] !== 'event') {
                    $this->reconcile($row, $result);
                }
            });
        } catch (PactError$e) {
            $this->failed($row, $e);
        } catch (\Throwable$e) {
            $this->failed($row, new PactError('WORKER_ERROR', 'Worker processing failed', false));
        }

        return true;
    }

    private function notify(array $row, string $token): stdClass
    {
        $curl = curl_init($row['endpoint']);
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $row['raw'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT_MS => 5000, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer '.$token, 'A2A-Version: 1.0', 'A2A-Extensions: '.Pact::CORE_URI.', '.Pact::SWARM_URI]]);
        $raw = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($raw === false) {
            throw new PactError('CONNECTION', 'Notification connection failed', true);
        }
        if ($status < 200 || $status >= 300) {
            throw new PactError('HTTP_'.$status, 'Notification HTTP failure', $status >= 500 || in_array($status, [408, 429], true));
        }
        $result = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        if (isset($result->error)) {
            throw new PactError($result->error->data->{Pact::CORE_URI}->code ?? 'REMOTE_ERROR', 'Notification rejected');
        }
        Pact::ensure(in_array($result->disposition ?? null, ['accepted', 'duplicate', 'stale'], true), 'DELIVERY_RECEIPT', 'Missing committed receipt');

        return $result;
    }

    private function reconcile(array $row, stdClass $childTask): void
    {
        [$task,$internal] = $this->service->store->task($row['tenant'], 'coordinator', $row['parent']);
        $child = null;
        foreach ($internal->children as $c) {
            if ($c->child_id === $row['child']) {
                $child = $c;
            }
        }Pact::ensure($child !== null, 'DAG_DEPENDENCY', 'Unknown child');
        $child->task_id = $childTask->id;
        $child->state = $childTask->status->state;
        $child->percentage = $childTask->metadata->{Pact::CORE_URI}->progress->percentage ?? 0;
        $output = $childTask->artifacts[0]->parts[0]->data ?? null;
        $child->output = $output;
        if (Pact::isTerminal($child->state)) {
            if (! count(array_filter($child->history, fn ($entry) => $entry->task_id === $childTask->id))) {
                $child->history[] = (object) ['attempt' => $child->attempt, 'task_id' => $childTask->id, 'state' => $child->state, 'artifact_id' => $childTask->artifacts[0]->artifactId ?? null, 'sha256' => $output === null ? null : hash('sha256', Pact::canonical($output)), 'validated' => $output !== null && $child->state === 'TASK_STATE_COMPLETED', 'error' => $childTask->metadata->{Pact::CORE_URI}->error ?? null];
            }
            if ($child->state === 'TASK_STATE_FAILED' && $child->required && $child->attempt < 2 && ! Pact::isTerminal($task->status->state)) {
                $child->percentage = 100;
                $this->service->progress($row['tenant'], $task, $internal);
                $child->attempt++;
                $child->task_id = null;
                $child->state = 'TASK_STATE_SUBMITTED';
                $child->percentage = 0;
                $child->output = null;
            } else {
                $child->settled = true;
            }
        } elseif ($row['kind'] !== 'cancel') {
            $poll = Store::encode((object) ['jsonrpc' => '2.0', 'id' => Service::uuid(), 'method' => 'GetTask', 'params' => (object) ['id' => $childTask->id]]);
            $this->service->store->outbox($row['tenant'], $task->id, $child->child_id, 'poll', $row['endpoint'], $poll);
            $last = $this->service->store->db->lastInsertId();
            $this->service->store->execute('UPDATE outbox SET due=? WHERE id=?', [microtime(true) + 1, $last]);
        }
        if (Pact::isTerminal($task->status->state)) {
            if (isset($task->metadata->{Pact::SWARM_URI}->cancellation) && Pact::isTerminal($childTask->status->state)) {
                if (! count(array_filter($task->metadata->{Pact::SWARM_URI}->cancellation->observed, fn ($entry) => $entry->task_id === $childTask->id))) {
                    $task->metadata->{Pact::SWARM_URI}->cancellation->observed[] = (object) ['child_id' => $child->child_id, 'task_id' => $childTask->id, 'state' => $childTask->status->state];
                    $this->service->notification($row['tenant'], $task, $internal, 'cancellation.observed', (object) ['child_id' => $child->child_id, 'task_id' => $childTask->id, 'state' => $childTask->status->state]);
                }
            } elseif (! Pact::isTerminal($childTask->status->state)) {
                $raw = Store::encode((object) ['jsonrpc' => '2.0', 'id' => Service::uuid(), 'method' => 'CancelTask', 'params' => (object) ['id' => $childTask->id]]);
                $this->service->store->outbox($row['tenant'], $task->id, $child->child_id, 'cancel', $row['endpoint'], $raw);
            }
        } else {
            $this->advance($row['tenant'], $task, $internal);
        }
        $task->metadata->{Pact::SWARM_URI}->contributors = array_map(fn ($c) => (object) ['child_id' => $c->child_id, 'required' => $c->required, 'history' => $c->history], $internal->children);
        $this->service->store->save($row['tenant'], 'coordinator', $task, $internal);
    }

    private function advance(string $tenant, stdClass $task, stdClass $internal): void
    {
        $requiredFailure = count(array_filter($internal->children, fn ($c) => $c->required && $c->settled && $c->state !== 'TASK_STATE_COMPLETED')) > 0;
        if ($requiredFailure) {
            $this->service->state($task, 'TASK_STATE_FAILED');
        }
        $this->service->progress($tenant, $task, $internal);
        $this->service->schedule($tenant, $task, $internal);
        if (! Pact::isTerminal($task->status->state) && ! count(array_filter($internal->children, fn ($c) => ! $c->settled))) {
            $this->service->progress($tenant, $task, $internal, 100, 0);
            $assembled = array_values(array_filter($internal->children, fn ($c) => $c->child_id === 'assemble'))[0]->output;
            $this->service->pact->contractData($this->service->contract->output, $assembled, $this->service->registry);
            $this->service->progress($tenant, $task, $internal, 100, 100);
            $this->service->complete($task, $internal, $assembled);
        }
        $this->service->notification($tenant, $task, $internal, 'task.status', (object) ['state' => $task->status->state]);
    }

    private function failed(array $row, PactError $error): void
    {
        $exhausted = ! $error->retryable || $row['attempts'] >= 5;
        $maximum = min(2000, 100 * 2 ** ($row['attempts'] - 1));
        $delay = random_int(0, $maximum) / 1000;
        $this->service->store->atomic(function () use ($row, $error, $exhausted, $delay) {
            $owned = $this->service->store->query('SELECT owner FROM outbox WHERE id=?', [$row['id']]);
            if (($owned[0]['owner'] ?? null) !== $this->owner) {
                return;
            }
            $this->service->store->execute('UPDATE outbox SET state=?,due=?,lease_until=0,owner=NULL,error=? WHERE id=? AND owner=?', [$exhausted ? 'failed' : 'pending', microtime(true) + $delay, Store::encode((object) ['code' => $error->errorCode, 'retryable' => $error->retryable]), $row['id'], $this->owner]);
            if ($exhausted && $row['kind'] !== 'event') {
                $record = $this->service->store->task($row['tenant'], 'coordinator', $row['parent']);
                if ($record) {
                    [$task,$internal] = $record;
                    if (! Pact::isTerminal($task->status->state)) {
                        $child = array_values(array_filter($internal->children, fn ($c) => $c->child_id === $row['child']))[0] ?? null;
                        if ($child && ! $child->required && $row['kind'] !== 'cancel') {
                            $child->state = 'TASK_STATE_FAILED';
                            $child->settled = true;
                            $child->percentage = 100;
                            $child->delivery_error = (object) ['code' => 'DELIVERY_FAILED', 'confirmed' => false];
                            $this->advance($row['tenant'], $task, $internal);
                        } else {
                            $this->service->state($task, 'TASK_STATE_FAILED');
                            $task->metadata->{Pact::CORE_URI}->error = (object) ['code' => 'DELIVERY_FAILED', 'message' => 'Child delivery could not be confirmed', 'retryable' => false];
                            $this->service->notification($row['tenant'], $task, $internal, 'task.status', (object) ['state' => $task->status->state]);
                        }
                        $this->service->store->save($row['tenant'], 'coordinator', $task, $internal);
                    }
                }
            }
        });
    }
}
