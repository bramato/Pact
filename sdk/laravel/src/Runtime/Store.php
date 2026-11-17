<?php

declare(strict_types=1);

namespace PactLaravel\Runtime;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pact\Pact;
use PactLaravel\Models\Federation;
use PactLaravel\Models\Member;
use PactLaravel\Models\Outbox;
use stdClass;

final class Store
{
    public function atomic(callable $work): mixed
    {
        return DB::connection(config('pact.database_connection'))->transaction($work, 3);
    }

    public function enqueue(string $kind, ?string $taskId, array $payload, ?string $key = null): Outbox
    {
        $hash = hash('sha256', $kind."\0".$key);
        $id = $key === null ? (string) Str::uuid() : substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-'.substr($hash, 12, 4).'-'.substr($hash, 16, 4).'-'.substr($hash, 20, 12);
        $existing = Outbox::query()->find($id);
        if ($existing) {
            if ($existing->kind !== $kind || $existing->task_id !== $taskId || Pact::canonical(json_decode(json_encode($existing->payload, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR)) !== Pact::canonical(json_decode(json_encode($payload, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR))) {
                throw new \DomainException('Outbox key conflict');
            }

            return $existing;
        }

        return Outbox::query()->create(['id' => $id, 'key' => $key === null ? null : $kind.':'.hash('sha256', $key), 'kind' => $kind, 'task_id' => $taskId, 'payload' => $payload, 'state' => 'pending', 'attempts' => 0, 'available_at' => now()]);
    }

    public function ownerId(string $userId): string
    {
        return 'user_'.hash('sha256', $userId);
    }

    public function federationFor(string $userId): Federation
    {
        return $this->atomic(function () use ($userId) {
            $existing = Federation::query()->where('default_user_id', $userId)->lockForUpdate()->first();
            $federation = $existing ?? Federation::query()->createOrFirst(['default_user_id' => $userId], ['id' => (string) Str::uuid(), 'user_id' => $userId, 'name' => 'Squadra personale', 'revision' => 1, 'state' => []]);
            $owner = Member::query()->firstOrCreate(['federation_id' => $federation->id, 'member_id' => $this->ownerId($userId)], ['user_id' => $userId, 'connection_id' => null, 'active' => true, 'manifest' => []]);
            if ($existing && $owner->wasRecentlyCreated) {
                $federation->increment('revision');
            }

            return $federation;
        });
    }

    public function teamFor(string $userId, string $teamId): Federation
    {
        return Federation::query()->whereKey($teamId)->where('user_id', $userId)->firstOrFail();
    }

    public function teamsFor(string $userId): array
    {
        $this->federationFor($userId);

        return Federation::query()->where('user_id', $userId)->orderByRaw('CASE WHEN default_user_id IS NULL THEN 1 ELSE 0 END')->orderBy('name')->get()->map(fn ($team) => ['id' => $team->id, 'name' => $team->name])->all();
    }

    public function createTeam(string $userId, string $name): Federation
    {
        $name = trim($name);
        if ($userId === '' || $name === '' || mb_strlen($name) > 100) {
            throw new \InvalidArgumentException('Il nome della squadra deve contenere da 1 a 100 caratteri.');
        }

        return $this->atomic(function () use ($userId, $name): Federation {
            $team = Federation::query()->create(['user_id' => $userId, 'name' => $name, 'revision' => 1, 'state' => []]);
            Member::query()->create(['federation_id' => $team->id, 'member_id' => $this->ownerId($userId), 'user_id' => $userId, 'active' => true, 'manifest' => []]);

            return $team;
        });
    }

    public function bump(string $fedId): void
    {
        Federation::query()->whereKey($fedId)->increment('revision');
    }

    public function roster(string $federationId): stdClass
    {
        $federation = Federation::query()->findOrFail($federationId);
        $base = rtrim((string) config('pact.url'), '/').'/'.trim((string) config('pact.prefix', 'pact'), '/');
        $members = Member::query()->where('federation_id', $federationId)->orderBy('member_id')->get()->map(fn ($m) => (object) ['id' => $m->member_id, 'kind' => $m->connection_id ? 'agent' : 'user', 'active' => (bool) $m->active])->all();

        return (object) ['federation_id' => (string) $federation->id, 'coordinator_url' => rtrim((string) config('pact.url'), '/'), 'revision' => (int) $federation->revision, 'mcp_endpoint' => $base.'/mcp', 'members' => $members];
    }
}
