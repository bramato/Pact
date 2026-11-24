<?php

declare(strict_types=1);

namespace PactLaravel\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;

final class DatabaseInstallation
{
    private const TABLE = 'pact_installation_state';

    public static function enabled(): bool
    {
        return match (config('pact.state_store', 'files')) {
            'files' => false,
            'database' => true,
            default => throw new \InvalidArgumentException('Set PACT_STATE_STORE to files or database.'),
        };
    }

    public function load(): ?array
    {
        $database = $this->connection();
        try {
            $payload = $database->table(self::TABLE)->useWritePdo()->where('id', 1)->value('payload');
        } catch (QueryException $error) {
            if (! $database->getSchemaBuilder()->hasTable(self::TABLE)) {
                return null;
            }
            throw $error;
        }

        if ($payload === null) {
            return null;
        }
        try {
            $state = json_decode(app('encrypter')->decryptString($payload), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException) {
            throw new \RuntimeException('Cannot decrypt the PACT installation. Verify the original APP_KEY and active Laravel configuration.');
        }
        if (($state['version'] ?? null) !== 1 || ! is_array($state['installation'] ?? null) || ! is_array($state['keys'] ?? null)) {
            throw new \RuntimeException('Unsupported or invalid PACT installation state in the database.');
        }

        return $state;
    }

    /** Commit a complete installation before it becomes visible on the local filesystem. */
    public function save(array $state): array
    {
        $state['version'] = 1;
        $database = $this->connection();
        $schema = $database->getSchemaBuilder();
        if (! $schema->hasTable(self::TABLE)) {
            try {
                $schema->create(self::TABLE, function (Blueprint $table): void {
                    $table->unsignedTinyInteger('id')->primary();
                    $table->text('payload');
                });
            } catch (QueryException $error) {
                if (! $schema->hasTable(self::TABLE)) {
                    throw $error;
                }
            }
        }

        try {
            $database->table(self::TABLE)->insert([
                'id' => 1,
                'payload' => app('encrypter')->encryptString(json_encode($state, JSON_THROW_ON_ERROR)),
            ]);

            return $state;
        } catch (UniqueConstraintViolationException) {
            return $this->load() ?? throw new \RuntimeException('The PACT database installation disappeared during setup.');
        }
    }

    private function connection(): Connection
    {
        return app('db')->connection();
    }
}
