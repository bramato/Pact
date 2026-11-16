<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use Illuminate\Support\Facades\Http;
use PactLaravel\Runtime\Store;
use PactLaravel\Runtime\Worker;

final class RetryPolicyTest extends TestCase
{
    public function test_request_timeout_honors_retry_after_and_reuses_command_bytes(): void
    {
        $this->migratePact();
        Http::fake(['https://peer.example/a2a' => Http::sequence()->push([], 408, ['Retry-After' => '2'])->push(['accepted' => true], 200)]);
        $store = new Store;
        $job = $store->enqueue('http', null, ['method' => 'POST', 'url' => 'https://peer.example/a2a', 'body' => 'exact-command-bytes']);
        $worker = new Worker($store);
        $before = now();
        self::assertTrue($worker->workOnce());
        self::assertSame('pending', $job->fresh()->state);
        self::assertGreaterThanOrEqual($before->timestamp + 2, $job->fresh()->available_at->timestamp);
        $job->refresh()->update(['available_at' => now()->subSecond()]);
        self::assertTrue($worker->workOnce());
        self::assertSame('done', $job->fresh()->state);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->body() === 'exact-command-bytes');
    }

    public function test_nonretryable_authorization_failure_is_recorded_once(): void
    {
        $this->migratePact();
        Http::fake(['https://peer.example/a2a' => Http::response([], 401)]);
        $store = new Store;
        $job = $store->enqueue('http', null, ['method' => 'POST', 'url' => 'https://peer.example/a2a', 'body' => 'rejected-command']);
        self::assertTrue((new Worker($store))->workOnce());
        self::assertSame('failed', $job->fresh()->state);
        self::assertSame(1, $job->fresh()->attempts);
        Http::assertSentCount(1);
    }
}
