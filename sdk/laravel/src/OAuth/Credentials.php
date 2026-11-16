<?php

declare(strict_types=1);

namespace PactLaravel\OAuth;

use InvalidArgumentException;
use RuntimeException;

/** RS256 credentials for federation endpoints; OAuth access tokens are separate. */
final class Credentials
{
    public static function issue(string $subject, string $audience, int $ttl = 3600, ?string $federationId = null, ?int $generation = null): string
    {
        if ($subject === '' || $audience === '' || $ttl < 1 || $ttl > 86400) {
            throw new InvalidArgumentException('Invalid federation credential parameters.');
        }
        $issuer = rtrim((string) config('pact.url'), '/');
        if ($issuer === '') {
            throw new RuntimeException('PACT issuer is not configured.');
        }
        $key = @file_get_contents(storage_path('app/pact/private.pem'));
        if ($key === false) {
            throw new RuntimeException('PACT signing key is unavailable.');
        }
        $now = time();
        $header = self::encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = self::encode(json_encode(['iss' => $issuer, 'sub' => $subject, 'aud' => $audience, 'iat' => $now, 'nbf' => $now, 'exp' => $now + $ttl, 'jti' => bin2hex(random_bytes(16)), 'federation_id' => $federationId, 'generation' => $generation], JSON_THROW_ON_ERROR));
        $input = $header.'.'.$payload;
        if (! openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign PACT credential.');
        }

        return $input.'.'.self::encode($signature);
    }

    /** @return array<string, mixed> */
    public static function verify(string $token, string $issuer, string $audience, string $publicKey): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3 || strlen($token) > 16384) {
            throw new InvalidArgumentException('Invalid federation credential.');
        }
        try {
            $header = json_decode(self::decode($parts[0]), true, 16, JSON_THROW_ON_ERROR);
            $claims = json_decode(self::decode($parts[1]), true, 16, JSON_THROW_ON_ERROR);
            $signature = self::decode($parts[2]);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Invalid federation credential.', 0, $e);
        }
        if (! is_array($header) || ($header['alg'] ?? null) !== 'RS256' || ($header['typ'] ?? null) !== 'JWT'
            || ! is_array($claims) || openssl_verify($parts[0].'.'.$parts[1], $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1
            || ($claims['iss'] ?? null) !== $issuer || ($claims['aud'] ?? null) !== $audience
            || ! is_string($claims['sub'] ?? null) || $claims['sub'] === ''
            || ! is_int($claims['exp'] ?? null) || $claims['exp'] <= time()
            || ! is_int($claims['nbf'] ?? null) || $claims['nbf'] > time()) {
            throw new InvalidArgumentException('Invalid federation credential.');
        }

        return $claims;
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string
    {
        if ($value === '' || preg_match('/[^A-Za-z0-9_-]/', $value) || strlen($value) % 4 === 1) {
            throw new InvalidArgumentException('Invalid base64url.');
        }
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false || self::encode($decoded) !== $value) {
            throw new InvalidArgumentException('Invalid base64url.');
        }

        return $decoded;
    }
}
