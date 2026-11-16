<?php

declare(strict_types=1);

namespace PactLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use PactLaravel\PactManager;

/**
 * @method static string connectUrl(string $application)
 * @method static array members()
 * @method static array sendMessage(string $recipientId, string $text, ?string $idempotencyKey = null)
 * @method static array task(string $taskId)
 * @method static array answer(string $taskId, array $answers, ?string $idempotencyKey = null)
 * @method static void cancel(string $taskId)
 */
final class Pact extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PactManager::class;
    }
}
