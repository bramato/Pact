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
