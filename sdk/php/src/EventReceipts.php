<?php

declare(strict_types=1);

namespace Pact;

/** In-memory consumer helper; persistent adapters must transact these receipts. */
final class EventReceipts
{
    private array $receipts = [];

    private array $positions = [];

    public function __construct(private readonly Pact $pact = new Pact) {}

    public function accept(string $tenant, string $producer, string $raw, bool $swarmActive = false): string
    {
        Pact::ensure($tenant !== '' && $producer !== '', 'SECURITY_SCOPE', 'Trusted scope required');
        $event = Pact::parse($raw);
        Pact::ensure(($event->producer_id ?? null) === $producer, 'PRODUCER_IDENTITY', 'Authenticated producer differs');
        $key = Pact::canonical([$tenant, $producer, $event->event_id]);
        $digest = hash('sha256', $raw);
        if (isset($this->receipts[$key])) {
            Pact::ensure(hash_equals($this->receipts[$key], $digest), 'EVENT_CONFLICT', 'Changed bytes for accepted event ID');

            return 'duplicate';
        }
        $this->pact->event($event, $swarmActive);
        $position = Pact::canonical([$tenant, $producer, $event->task_id]);
        $previous = $this->positions[$position] ?? null;
        $this->receipts[$key] = $digest;
        if ($previous !== null && $event->sequence <= $previous) {
            return 'stale';
        }
        $this->positions[$position] = $event->sequence;

        return 'accepted';
    }
}
