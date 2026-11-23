<?php

declare(strict_types=1);

namespace PactLaravel\Runtime;

use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;

final class ExecutionQueue
{
    private function __construct(
        private readonly string $connection,
        private readonly ?string $name,
    ) {}

    public static function fromConfig(): ?self
    {
        $connection = config('pact.queue.connection');
        $name = config('pact.queue.name');
        if (! $connection && ! $name) {
            return null;
        }

        $connection = $connection ?: config('queue.default');
        $connections = config('queue.connections', []);
        $driver = $connections[$connection]['driver'] ?? null;
        if (! $driver || in_array($driver, ['sync', 'null', 'deferred', 'background'], true)) {
            throw new InvalidArgumentException('PACT execution requires an asynchronous Laravel queue connection. Check pact.queue.connection and queue.connections.');
        }

        return new self($connection, $name ?: null);
    }

    public function dispatch(string $outboxId, string $reservation): void
    {
        Queue::connection($this->connection)->push(new ExecuteTaskJob($outboxId, $reservation), '', $this->name);
    }
}
