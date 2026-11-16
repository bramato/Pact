<?php

declare(strict_types=1);

namespace PactLaravel\Support;

use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use RuntimeException;

final class Installation
{
    public static function path(): string
    {
        return storage_path('app/pact/installation.json');
    }

    public static function load(): array
    {
        if (! is_file(self::path())) {
            return [];
        }

        return json_decode(file_get_contents(self::path()), true, 512, JSON_THROW_ON_ERROR);
    }

    public function install(string $role, array $options): array
    {
        if (! in_array($role, ['master', 'client'], true)) {
            throw new RuntimeException('Choose role master or client.');
        }
        $directory = dirname(self::path());
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create PACT installation directory.');
        }
        $lock = fopen($directory.'/install.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Cannot lock PACT installation.');
        }
        try {
            $existing = self::load();
            if ($existing !== []) {
                if ($existing['role'] !== $role) {
                    throw new RuntimeException('PACT already has a different role; reinstalling cannot change it.');
                }

                return $existing;
            }
            $url = UrlPolicy::assert((string) ($options['url'] ?? config('pact.url')));
            $guard = $options['guard'] ?? config('pact.auth.guard', 'web');
            $provider = $options['provider'] ?? config('pact.auth.provider', 'users');
            if (! config('auth.guards.'.$guard) || ! config('auth.providers.'.$provider)) {
                throw new RuntimeException('Configure an existing authentication guard and user provider before installing PACT.');
            }
            if (! config('app.key')) {
                throw new RuntimeException('APP_KEY is required to encrypt PACT credentials.');
            }
            $installed = ['role' => $role, 'product_id' => (string) Str::uuid(), 'url' => $url,
                'auth' => ['guard' => $guard, 'provider' => $provider], 'database_connection' => $options['database_connection'] ?? config('pact.database_connection'),
                'local_hosts' => config('pact.local_hosts', [])];
            $this->keys($directory.'/private.pem', $directory.'/public.pem');
            if ($role === 'client') {
                $private = config('passport.private_key') ?: Passport::keyPath('oauth-private.key');
                $public = config('passport.public_key') ?: Passport::keyPath('oauth-public.key');
                // Existing inline Passport keys belong to the host application.
                if (! str_contains($private, '-----BEGIN')) {
                    $this->keys($private, $public);
                }
            }
            $temporary = $directory.'/installation.'.bin2hex(random_bytes(6)).'.tmp';
            file_put_contents($temporary, json_encode($installed, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n", LOCK_EX);
            chmod($temporary, 0600);
            if (! rename($temporary, self::path())) {
                throw new RuntimeException('Cannot persist PACT installation.');
            }

            return $installed;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function keys(string $private, string $public): void
    {
        if (is_file($private) && is_file($public)) {
            return;
        }
        if (is_file($private) || is_file($public)) {
            throw new RuntimeException('Incomplete key pair; existing keys will not be replaced.');
        }
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($key === false || ! openssl_pkey_export($key, $pem)) {
            throw new RuntimeException('Cannot generate signing keys.');
        }
        file_put_contents($private, $pem, LOCK_EX);
        chmod($private, 0600);
        file_put_contents($public, openssl_pkey_get_details($key)['key'], LOCK_EX);
        chmod($public, 0600);
    }
}
