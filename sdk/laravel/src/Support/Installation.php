<?php

declare(strict_types=1);

namespace PactLaravel\Support;

use Closure;
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

        $installed = json_decode(self::read(self::path()), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($installed) || ! in_array($installed['role'] ?? null, ['master', 'client'], true)
            || ! is_string($installed['product_id'] ?? null) || $installed['product_id'] === '') {
            throw new RuntimeException('Invalid PACT installation metadata. Recover the original installation before continuing.');
        }

        return $installed;
    }

    /** Restore DB-backed state before PACT and Passport start serving requests. */
    public function restore(): array
    {
        $state = (new DatabaseInstallation)->load();
        if ($state === null) {
            $local = self::load();
            if ($local === [] && config('pact.product_id')) {
                throw new RuntimeException('The configured PACT installation is missing. Restore the original database state or installation files.');
            }

            return $local;
        }

        $installed = $state['installation'];
        $files = $this->files($installed, $state['keys']);
        if (! is_file(self::path()) || $this->missingFiles($files) !== []) {
            return $this->locked(fn () => $this->materialize($installed, $files));
        }
        $this->assertSameInstallation($installed);

        return $installed;
    }

    public function install(string $role, array $options): array
    {
        if (! in_array($role, ['master', 'client'], true)) {
            throw new RuntimeException('Choose role master or client.');
        }

        return $this->locked(function () use ($role, $options) {
            $database = DatabaseInstallation::enabled() ? new DatabaseInstallation : null;
            if ($state = $database?->load()) {
                $this->assertRole($role, $state['installation']);

                return $this->materialize($state['installation'], $this->files($state['installation'], $state['keys']));
            }

            $existing = self::load();
            if ($existing !== []) {
                $this->assertRole($role, $existing);
                if ($database === null) {
                    return $existing;
                }
            }
            $installed = $existing ?: $this->prepare($role, $options);
            $keys = $this->collectKeys($role, $existing === []);
            if ($database !== null) {
                $state = $database->save(['installation' => $installed, 'keys' => $keys]);
                $installed = $state['installation'];
                $keys = $state['keys'];
                $this->assertRole($role, $installed);
            }

            return $this->materialize($installed, $this->files($installed, $keys));
        });
    }

    private function prepare(string $role, array $options): array
    {
        $url = UrlPolicy::assert((string) ($options['url'] ?? config('pact.url')));
        $guard = $options['guard'] ?? config('pact.auth.guard', 'web');
        $provider = $options['provider'] ?? (isset($options['guard']) ? config('auth.guards.'.$guard.'.provider') : null)
            ?? config('pact.auth.provider', 'users');
        if (! config('auth.guards.'.$guard) || ! config('auth.providers.'.$provider)) {
            throw new RuntimeException('Configure an existing authentication guard and user provider before installing PACT.');
        }
        if (! config('app.key')) {
            throw new RuntimeException('APP_KEY is required to encrypt PACT credentials.');
        }
        return [
            'role' => $role,
            'product_id' => (string) Str::uuid(),
            'url' => $url,
            'auth' => ['guard' => $guard, 'provider' => $provider],
            'database_connection' => $options['database_connection'] ?? config('pact.database_connection'),
            'local_hosts' => config('pact.local_hosts', []),
        ];
    }

    private function collectKeys(string $role, bool $generate): array
    {
        $paths = $this->keyPaths($role);
        $keys = [];
        foreach (array_chunk($paths, 2, true) as $pair) {
            [$private, $public] = array_values($pair);
            if (is_file($private) && is_file($public)) {
                $pem = self::read($private);
                $publicPem = self::read($public);
                if (! $this->matchingKeys($pem, $publicPem)) {
                    throw new RuntimeException('Existing PACT or Passport keys do not form a valid pair.');
                }
            } elseif ($generate && ! file_exists($private) && ! file_exists($public)) {
                $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
                if ($key === false || ! openssl_pkey_export($key, $pem)) {
                    throw new RuntimeException('Cannot generate signing keys.');
                }
                $publicPem = openssl_pkey_get_details($key)['key'];
            } else {
                throw new RuntimeException('Incomplete key pair. Restore the original PACT or Passport keys.');
            }
            $keys += array_combine(array_keys($pair), [$pem, $publicPem]);
        }

        return $keys;
    }

    private function matchingKeys(string $private, string $public): bool
    {
        $key = openssl_pkey_get_private($private);
        $publicKey = openssl_pkey_get_public($public);

        return $key !== false && $publicKey !== false
            && openssl_pkey_get_details($key)['key'] === openssl_pkey_get_details($publicKey)['key'];
    }

    private function keyPaths(string $role): array
    {
        $paths = ['private' => dirname(self::path()).'/private.pem', 'public' => dirname(self::path()).'/public.pem'];
        if ($role !== 'client') {
            return $paths;
        }
        $private = config('passport.private_key');
        $public = config('passport.public_key');
        if ((bool) $private !== (bool) $public) {
            throw new RuntimeException('Configure both Passport keys together.');
        }
        if ($private && $public) {
            // Passport reads these credentials from the host configuration.
            if (str_contains($private, '-----BEGIN') && str_contains($public, '-----BEGIN')) {
                return $paths;
            }
            $paths += ['passport_private' => preg_replace('#^file://#', '', $private), 'passport_public' => preg_replace('#^file://#', '', $public)];
        } else {
            $paths += ['passport_private' => Passport::keyPath('oauth-private.key'), 'passport_public' => Passport::keyPath('oauth-public.key')];
        }

        return $paths;
    }

    private function files(array $installed, array $keys): array
    {
        $paths = $this->keyPaths($installed['role']);
        if (array_diff_key($paths, $keys) || array_diff_key($keys, $paths)) {
            throw new RuntimeException('Passport key configuration differs from the stored PACT installation. Restore the original configuration.');
        }

        $files = [];
        foreach ($paths as $key => $path) {
            $files[$path] = $keys[$key];
        }

        return $files;
    }

    private function missingFiles(array $files): array
    {
        $missing = [];
        foreach ($files as $path => $contents) {
            if (file_exists($path) || is_link($path)) {
                if (! hash_equals($contents, self::read($path))) {
                    throw new RuntimeException('Local PACT or Passport signing keys differ from the stored installation.');
                }
            } else {
                $missing[$path] = $contents;
            }
        }

        return $missing;
    }

    private function materialize(array $installed, array $files): array
    {
        $this->assertSameInstallation($installed);
        foreach ($this->missingFiles($files) as $path => $contents) {
            $this->write($path, $contents);
        }
        if (! is_file(self::path())) {
            $this->write(self::path(), json_encode($installed, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n");
        }

        return $installed;
    }

    private function assertSameInstallation(array $installed): void
    {
        if (is_link(self::path()) && ! is_file(self::path())) {
            throw new RuntimeException('The PACT installation metadata points to a missing file. Restore the original state.');
        }
        $local = self::load();
        if ($local !== [] && $local != $installed) {
            throw new RuntimeException('Local PACT installation metadata differs from the stored installation.');
        }
    }

    private function assertRole(string $role, array $installed): void
    {
        if ($role !== $installed['role']) {
            throw new RuntimeException('PACT already has a different role; reinstalling cannot change it.');
        }
    }

    private static function read(string $path): string
    {
        $contents = is_file($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            throw new RuntimeException('Cannot read PACT installation state or signing keys.');
        }

        return $contents;
    }

    private function locked(Closure $action): array
    {
        $directory = dirname(self::path());
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create PACT installation directory.');
        }
        $lock = fopen($directory.'/install.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot open PACT installation lock.');
        }
        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Cannot lock PACT installation.');
            }

            return $action();
        } finally {
            fclose($lock);
        }
    }

    private function write(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create the configured PACT or Passport signing key directory.');
        }
        $temporary = $directory.'/.pact-'.bin2hex(random_bytes(12));
        try {
            // Create a private temporary file before writing any key bytes to it.
            if (! touch($temporary) || ! chmod($temporary, 0600)
                || file_put_contents($temporary, $contents) !== strlen($contents) || ! rename($temporary, $path)) {
                throw new RuntimeException('Cannot persist PACT installation state or signing keys.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
