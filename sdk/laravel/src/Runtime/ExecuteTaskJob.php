<?php

declare(strict_types=1);

namespace PactLaravel\Runtime;

use Illuminate\Contracts\Queue\ShouldQueue;

final class ExecuteTaskJob implements ShouldQueue
{
    public int $tries = 1;

    public function __construct(
        private readonly string $outboxId,
        private readonly string $reservation,
    ) {}

    public function handle(Worker $worker): void
    {
        $worker->executeQueued($this->outboxId, $this->reservation);
    }
}
