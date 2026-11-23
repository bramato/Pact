<?php

declare(strict_types=1);

namespace PactLaravel\Execution;

final class AgentContext
{
    use ReadsPactCalls;

    public function __construct(public readonly string $taskId, public readonly string $userId, public readonly array $input, public readonly array $checkpoint = [], public readonly array $answers = [], public readonly string $federationId = '', public readonly string $rootId = '', public readonly string $invocationId = '', public readonly array $delegationState = [], public readonly mixed $delegatedResult = null) {}

    public function readableMessage(): string
    {
        $message = $this->input['message'] ?? null;

        return is_string($message) ? $this->readablePactMessage($message) : '';
    }
}
