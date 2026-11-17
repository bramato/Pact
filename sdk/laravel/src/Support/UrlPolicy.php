<?php

declare(strict_types=1);

namespace PactLaravel\Support;

use InvalidArgumentException;

final class UrlPolicy
{
    /** Accept a domain or origin, never an arbitrary URL path. */
    public static function origin(string $domain): string
    {
        $url = trim($domain);
        if (! str_contains($url, '://')) {
            $url = 'https://'.$url;
        }
        $url = self::assert($url);
        $parts = parse_url($url);
        if (($parts['path'] ?? '') !== '') {
            throw new InvalidArgumentException('Inserisci solo il dominio del server, senza percorsi.');
        }
        $scheme = strtolower($parts['scheme']);
        $port = $parts['port'] ?? null;

        return $scheme.'://'.strtolower($parts['host']).($port && $port !== ($scheme === 'https' ? 443 : 80) ? ':'.$port : '');
    }

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
