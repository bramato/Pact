<?php

declare(strict_types=1);

namespace PactLaravel\Support;

class PeerResolver
{
    /** Resolve once; HttpTransport pins these vetted addresses for the TLS connection. */
    public function addresses(string $host): array
    {
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)) {
            return [trim($host, '[]')];
        }
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        return array_values(array_unique(array_filter(array_map(fn ($r) => $r['ip'] ?? $r['ipv6'] ?? null, $records ?: []))));
    }
}
