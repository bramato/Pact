<?php

declare(strict_types=1);

namespace PactLaravel\Execution;

final class AgentResult
{
    public function __construct(public readonly string $state, public readonly array $value = [], public readonly array $checkpoint = [])
    {
        if (! in_array($state, ['completed', 'question', 'waiting', 'error'], true)) {
            throw new \InvalidArgumentException('Invalid agent result state');
        }
    }

    public static function completed(array $value): self
    {
        return new self('completed', $value);
    }

    public static function question(array $questions, array $checkpoint = []): self
    {
        return new self('question', $questions, $checkpoint);
    }

    public static function waiting(array $checkpoint): self
    {
        return new self('waiting', [], $checkpoint);
    }

    public static function error(array $error): self
    {
        return new self('error', $error);
    }
}
