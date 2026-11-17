<?php

declare(strict_types=1);

namespace PactLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use PactLaravel\PactManager;

/**
 * @method static string connectUrl(string $application)
 * @method static string connectDomain(string $domain)
 * @method static array teams()
 * @method static array createTeam(string $name)
 * @method static array members(?string $teamId = null)
 * @method static array sendMessage(string $recipientId, string $text, ?string $idempotencyKey = null, ?string $teamId = null)
 * @method static array task(string $taskId, ?string $teamId = null)
 * @method static array answer(string $taskId, array $answers, ?string $idempotencyKey = null, ?string $teamId = null)
 * @method static void cancel(string $taskId, ?string $teamId = null)
 */
final class Pact extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PactManager::class;
    }
}
