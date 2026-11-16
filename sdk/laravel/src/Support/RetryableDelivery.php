<?php

declare(strict_types=1);

namespace PactLaravel\Support;

final class RetryableDelivery extends \RuntimeException
{
    public function __construct(string $message, public readonly int $retryAfter = 0)
    {
        parent::__construct($message);
    }

    public static function response(array $response): self
    {
        $value = $response['headers']['retry-after'] ?? '';
        $seconds = ctype_digit((string) $value) ? (int) $value : max(0, (strtotime((string) $value) ?: time()) - time());

        return new self('Peer temporarily unavailable', min(300, $seconds));
    }
}
