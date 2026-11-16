<?php

declare(strict_types=1);

namespace PactLaravel\Execution;

final class AgentContext
{
    public function __construct(public readonly string $taskId, public readonly string $userId, public readonly array $input, public readonly array $checkpoint = [], public readonly array $answers = [], public readonly string $federationId = '', public readonly string $rootId = '', public readonly string $invocationId = '', public readonly array $delegationState = [], public readonly mixed $delegatedResult = null) {}
}
