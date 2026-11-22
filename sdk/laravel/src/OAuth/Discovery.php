<?php

declare(strict_types=1);

namespace PactLaravel\OAuth;

use PactLaravel\Support\HttpTransport;
use PactLaravel\Support\UrlPolicy;

/** PACT discovery fixes every endpoint to the selected origin and installed prefix. */
final class Discovery
{
    public function __construct(private readonly HttpTransport $http) {}

    public static function document(): array
    {
        $origin = UrlPolicy::origin((string) config('pact.url'));
        $base = $origin.'/'.trim((string) config('pact.prefix', 'pact'), '/');
        $common = ['pact_version' => '0.1', 'issuer' => $origin, 'role' => config('pact.role'), 'pact_endpoint' => $base, 'question_form_versions' => [1]];

        return $common + (config('pact.role') === 'master'
            ? ['redirect_uri' => $base.'/oauth/callback', 'public_key_endpoint' => $base.'/oauth/public-key']
            : ['authorization_endpoint' => $base.'/oauth/authorize', 'token_endpoint' => $base.'/oauth/token', 'registration_endpoint' => $base.'/oauth/register', 'response_types_supported' => ['code'], 'grant_types_supported' => ['authorization_code', 'refresh_token'], 'token_endpoint_auth_methods_supported' => ['none'], 'code_challenge_methods_supported' => ['S256']]);
    }

    public function fetch(string $domain, string $role): array
    {
        $origin = UrlPolicy::origin($domain);
        $response = $this->http->request('GET', $origin.'/.well-known/pact', ['Accept' => 'application/json']);
        $data = $response['body'];
        $base = $origin.'/'.trim((string) config('pact.prefix', 'pact'), '/');
        if ($response['status'] !== 200 || ! is_array($data) || ($data['issuer'] ?? null) !== $origin
            || ($data['pact_version'] ?? null) !== '0.1' || ($data['role'] ?? null) !== $role || ($data['pact_endpoint'] ?? null) !== $base) {
            throw new \DomainException('Il dominio non espone un server PACT compatibile con il ruolo e il prefisso richiesti.');
        }
        $expected = $role === 'master'
            ? ['redirect_uri' => $base.'/oauth/callback', 'public_key_endpoint' => $base.'/oauth/public-key']
            : ['authorization_endpoint' => $base.'/oauth/authorize', 'token_endpoint' => $base.'/oauth/token', 'registration_endpoint' => $base.'/oauth/register'];
        foreach ($expected as $key => $value) {
            if (($data[$key] ?? null) !== $value) {
                throw new \DomainException('Gli indirizzi PACT pubblicati non corrispondono al dominio selezionato.');
            }
        }
        if ($role === 'client' && (! is_array($data['code_challenge_methods_supported'] ?? null) || ! in_array('S256', $data['code_challenge_methods_supported'], true))) {
            throw new \DomainException('Il server deve supportare OAuth con PKCE S256.');
        }

        return $data;
    }
}
