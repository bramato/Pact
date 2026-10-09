<?php

declare(strict_types=1);

namespace Pact;

use stdClass;

final class Client
{
    public function __construct(public readonly string $endpoint, private readonly string $token, private readonly stdClass $contract, private readonly string $registryPath, private readonly bool $swarm = false) {}

    public function raw(string $raw): mixed
    {
        $active = $this->swarm ? [Pact::CORE_URI, Pact::SWARM_URI] : [Pact::CORE_URI];
        $headers = [];
        $curl = curl_init($this->endpoint);
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $raw, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT_MS => 1000, CURLOPT_TIMEOUT_MS => 5000,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer '.$this->token, 'A2A-Version: 1.0', 'A2A-Extensions: '.implode(', ', $active)],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                if (str_contains($line, ':')) {
                    [$key, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($key))] = trim($value);
                }

return strlen($line);
            }]);
        $bytes = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($bytes === false) {
            throw new PactError('CONNECTION', 'Peer did not respond', true);
        }
        if ($status < 200 || $status >= 300) {
            throw new PactError('HTTP_'.$status, 'Peer HTTP failure', $status >= 500 || in_array($status, [408, 429], true));
        }
        $echoed = array_map('trim', explode(',', $headers['a2a-extensions'] ?? ''));
        foreach ($active as $uri) {
            Pact::ensure(in_array($uri, $echoed, true), 'ACTIVATION', 'Missing extension activation');
        }
        $response = Pact::parse($bytes);
        $request = Pact::parse($raw);
        Pact::ensure(($response->jsonrpc ?? null) === '2.0' && ($response->id ?? null) === $request->id && (isset($response->result) !== isset($response->error)), 'A2A_ENVELOPE', 'Invalid response envelope');
        if (isset($response->error)) {
            $detail = $response->error->data->{Pact::CORE_URI} ?? null;
            throw new PactError($detail->code ?? 'A2A_'.$response->error->code, $detail->message ?? $response->error->message, $detail->retryable ?? false);
        }

        return $response->result;
    }

    public function task(stdClass $task): stdClass
    {
        $pact = new Pact;
        Pact::ensure(isset($task->id, $task->contextId, $task->status->state, $task->status->timestamp) && str_ends_with($task->status->timestamp, 'Z'), 'A2A_TASK', 'Invalid task');
        $meta = $task->metadata->{Pact::CORE_URI} ?? null;
        $pact->shape('metadata', $meta);
        Pact::ensure(isset($meta->contract) && Pact::canonical($meta->contract) === Pact::canonical($this->contract), 'CONTRACT_MISMATCH', 'Task contract differs');
        if (isset($meta->progress)) {
            $pact->progress($meta->progress);
        }
        if ($this->swarm && isset($task->metadata->{Pact::SWARM_URI}->progress)) {
            $pact->swarmSnapshot($task->metadata->{Pact::SWARM_URI}->progress);
        }
        if (isset($meta->event)) {
            $pact->event($meta->event, $this->swarm);
            Pact::ensure($meta->event->task_id === $task->id, 'EVENT_CORRELATION', 'Task event differs');
        }
        foreach ($meta->questions ?? [] as $q) {
            $pact->shape('question', $q);
            Pact::ensure($q->task_id === $task->id, 'QUESTION_CORRELATION', 'Question task differs');
        }
        if ($task->status->state === 'TASK_STATE_COMPLETED') {
            Pact::ensure(count($task->artifacts ?? []) > 0, 'OUTPUT_REQUIRED', 'No output artifact');
            foreach ($task->artifacts as $artifact) {
                Pact::ensure(in_array(Pact::CORE_URI, $artifact->extensions ?? [], true) && count($artifact->parts ?? []) === 1 && isset($artifact->parts[0]->data), 'OUTPUT_REQUIRED', 'Structured Core artifact required');
                Pact::ensure(Pact::canonical($artifact->metadata->{Pact::CORE_URI}->contract) === Pact::canonical($this->contract), 'CONTRACT_MISMATCH', 'Artifact contract differs');
                $pact->contractData($this->contract->output, $artifact->parts[0]->data, $this->registryPath);
            }
            $pact->transition($task->status->state, $task->status->state, true, $meta->questions ?? []);
        } else {
            $pact->transition($task->status->state, $task->status->state);
        }

        return $task;
    }
}
