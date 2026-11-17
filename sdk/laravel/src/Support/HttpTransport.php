<?php

declare(strict_types=1);

namespace PactLaravel\Support;

use Illuminate\Support\Facades\Http;

final class HttpTransport
{
    /** @return array{status:int,headers:array,body:mixed,raw:string} */
    public function request(string $method, string $url, array $headers = [], mixed $body = null): array
    {
        // A query belongs to a request, never to an installed origin.
        $base = explode('?', $url, 2)[0];
        UrlPolicy::assert($base);
        $request = Http::withHeaders($headers)->connectTimeout(3)->timeout(10)->withOptions(['allow_redirects' => false]);
        $host = (string) parse_url($base, PHP_URL_HOST);
        $development = in_array($host, config('pact.local_hosts', []), true)
            && (str_ends_with($host, '.test') || in_array($host, ['localhost', '127.0.0.1', '[::1]'], true));
        if (! $development) {
            $addresses = app(PeerResolver::class)->addresses($host);
            if ($addresses === [] || array_filter($addresses, fn ($address) => ! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE))) {
                throw new \InvalidArgumentException('PACT peers must resolve exclusively to public IP addresses.');
            }
            $port = parse_url($base, PHP_URL_PORT) ?: (parse_url($base, PHP_URL_SCHEME) === 'https' ? 443 : 80);
            $address = $addresses[0];
            $pinned = str_contains($address, ':') ? '['.$address.']' : $address;
            // Pin DNS while preserving the original hostname for TLS verification.
            $request = $request->withOptions(['proxy' => '', 'curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.$pinned]]]);
        }
        $options = $body === null ? [] : (is_string($body) ? ['body' => $body] : ['json' => $body]);
        $response = $request->send(strtoupper($method), $url, $options);
        $responseHeaders = [];
        foreach ($response->headers() as $key => $values) {
            $responseHeaders[strtolower($key)] = implode(', ', $values);
        }
        if (strlen($response->body()) > 1048576) {
            throw new \RuntimeException('PACT peer response exceeds 1 MiB.');
        }

        return ['status' => $response->status(), 'headers' => $responseHeaders, 'body' => $response->json(), 'raw' => $response->body()];
    }
}
