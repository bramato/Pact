<?php

declare(strict_types=1);

namespace Pact;

final class PactError extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly bool $retryable = false, public readonly mixed $details = null)
    {
        parent::__construct($message);
    }
}
