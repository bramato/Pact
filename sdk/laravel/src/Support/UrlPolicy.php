<?php

declare(strict_types=1);

namespace PactLaravel\Support;

use InvalidArgumentException;

final class UrlPolicy
{
    public static function assert(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $scheme = $parts['scheme'] ?? '';
        $local = str_ends_with($host, '.test') || in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if (! filter_var($url, FILTER_VALIDATE_URL) || isset($parts['user'], $parts['pass']) || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment']) || $host === ''
            || ! in_array($scheme, ['https', 'http'], true)
            || ($scheme === 'http' && (! $local || ! in_array($host, config('pact.local_hosts', []), true)))) {
            throw new InvalidArgumentException('PACT URLs require HTTPS, or an explicitly allowed local development host.');
        }

        return rtrim($url, '/');
    }
}
