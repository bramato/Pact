<?php

declare(strict_types=1);

namespace PactLaravel\Execution;

trait ReadsPactCalls
{
    protected function readablePactMessage(string $message): string
    {
        return app(StructuredAgentCall::class)->readable($message);
    }
}
