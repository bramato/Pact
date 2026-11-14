<?php

declare(strict_types=1);

namespace Pact\Federation;

/** Durable implementations must commit an entry and its delivery together. */
interface Ledger
{
    /** Returns false for an identical replay; throws on key reuse with different content. */
    public function append(string $federation, string $sender, string $id, string $destination, string $payload): bool;

    /** Marks an accepted delivery; repeating the same receipt is harmless. */
    public function receipt(string $federation, string $sender, string $id, string $receipt): void;
}
