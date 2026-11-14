<?php

declare(strict_types=1);

namespace Pact\Federation;

/** Host-owned, tenant-scoped persistence and execution boundary. */
interface Runtime
{
    /** Execute atomically; on failure no checkpoint, ledger or outbox changes may persist. */
    public function transaction(callable $work): mixed;

    /** Within transaction: persist an immutable exchange and outbound work before delivery. */
    public function appendExchange(string $federation, string $actor, string $id, string $destination, string $payload): bool;

    /** Within transaction: return the previously committed result for an identical replay. */
    public function replayResult(string $federation, string $actor, string $id): mixed;

    /** Within transaction: persist a result alongside the corresponding exchange. */
    public function saveResult(string $federation, string $actor, string $id, mixed $result): void;

    /** Within transaction: persist the task checkpoint, wait and continuation together. */
    public function checkpoint(string $federation, string $task, array $state): void;

    /** Execute an authorized MCP method; no external delivery before the enclosing transaction commits. */
    public function invoke(string $method, string $actor, array $arguments): mixed;
}
