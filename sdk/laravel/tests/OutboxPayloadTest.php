<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use PactLaravel\Models\Outbox;
use PactLaravel\Runtime\Store;

final class OutboxPayloadTest extends TestCase
{
    public function test_equivalent_object_order_reuses_job_but_changed_raw_conflicts(): void
    {
        $this->migratePact();
        $store = new Store;
        $payload = ['connection_id' => 1, 'raw' => '{"id":1}', 'metadata' => ['encrypted' => ['iv' => 'a', 'value' => 'b']], 'items' => [1, 2]];
        $job = $store->enqueue('peer_cancel', null, $payload, 'same-key');
        $reordered = ['items' => [1, 2], 'metadata' => ['encrypted' => ['value' => 'b', 'iv' => 'a']], 'raw' => '{"id":1}', 'connection_id' => 1];

        self::assertSame($job->id, $store->enqueue('peer_cancel', null, $reordered, 'same-key')->id);
        self::assertSame(1, Outbox::query()->count());

        $reordered['raw'] = '{ "id":1}';
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Outbox key conflict');
        $store->enqueue('peer_cancel', null, $reordered, 'same-key');
    }
}
