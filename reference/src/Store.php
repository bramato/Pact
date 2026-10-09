<?php

declare(strict_types=1);

namespace PactReference;

use PDO;
use stdClass;

final class Store
{
    public readonly PDO $db;

    public function __construct(string $path)
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        $this->db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->db->exec('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000');
        $this->db->exec('CREATE TABLE IF NOT EXISTS tasks (tenant TEXT NOT NULL, agent TEXT NOT NULL, id TEXT NOT NULL, value TEXT NOT NULL, internal TEXT NOT NULL, PRIMARY KEY(tenant,agent,id));
            CREATE TABLE IF NOT EXISTS commands (tenant TEXT NOT NULL, caller TEXT NOT NULL, agent TEXT NOT NULL, key TEXT NOT NULL, digest TEXT NOT NULL, response TEXT NOT NULL, PRIMARY KEY(tenant,caller,agent,key));
            CREATE TABLE IF NOT EXISTS effects (tenant TEXT NOT NULL, agent TEXT NOT NULL, task_id TEXT NOT NULL, PRIMARY KEY(tenant,agent,task_id));
            CREATE TABLE IF NOT EXISTS outbox (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant TEXT NOT NULL, parent TEXT NOT NULL, child TEXT, kind TEXT NOT NULL, endpoint TEXT NOT NULL, raw TEXT NOT NULL, state TEXT NOT NULL DEFAULT "pending", attempts INTEGER NOT NULL DEFAULT 0, due REAL NOT NULL DEFAULT 0, lease_until REAL NOT NULL DEFAULT 0, owner TEXT, result TEXT, error TEXT);
            CREATE TABLE IF NOT EXISTS events (tenant TEXT NOT NULL, producer TEXT NOT NULL, id TEXT NOT NULL, task_id TEXT NOT NULL, raw TEXT NOT NULL, digest TEXT NOT NULL, disposition TEXT NOT NULL, PRIMARY KEY(tenant,producer,id));
            CREATE TABLE IF NOT EXISTS observed_states (tenant TEXT NOT NULL, producer TEXT NOT NULL, task_id TEXT NOT NULL, state TEXT NOT NULL, PRIMARY KEY(tenant,producer,task_id));
            CREATE TABLE IF NOT EXISTS projections (tenant TEXT NOT NULL, producer TEXT NOT NULL, task_id TEXT NOT NULL, sequence INTEGER NOT NULL, value TEXT NOT NULL, PRIMARY KEY(tenant,producer,task_id));');
    }

    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function atomic(callable $work): mixed
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $result = $work();
            $this->db->exec('COMMIT');

            return $result;
        } catch (\Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }
    }

    public function query(string $sql, array $args = []): array
    {
        $q = $this->db->prepare($sql);
        $q->execute($args);

        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function execute(string $sql, array $args = []): void
    {
        $q = $this->db->prepare($sql);
        $q->execute($args);
    }

    public function task(string $tenant, string $agent, string $id): ?array
    {
        $rows = $this->query('SELECT value,internal FROM tasks WHERE tenant=? AND agent=? AND id=?', [$tenant, $agent, $id]);
        if (! $rows) {
            return null;
        }

        return [json_decode($rows[0]['value'], false, 512, JSON_THROW_ON_ERROR), json_decode($rows[0]['internal'], false, 512, JSON_THROW_ON_ERROR)];
    }

    public function save(string $tenant, string $agent, stdClass $task, stdClass $internal): void
    {
        $this->execute('INSERT INTO tasks VALUES(?,?,?,?,?) ON CONFLICT(tenant,agent,id) DO UPDATE SET value=excluded.value,internal=excluded.internal', [$tenant, $agent, $task->id, self::encode($task), self::encode($internal)]);
    }

    public function outbox(string $tenant, string $parent, ?string $child, string $kind, string $endpoint, string $raw): void
    {
        $this->execute('INSERT INTO outbox(tenant,parent,child,kind,endpoint,raw) VALUES(?,?,?,?,?,?)', [$tenant, $parent, $child, $kind, $endpoint, $raw]);
    }

    public function claim(string $owner): ?array
    {
        return $this->atomic(function () use ($owner) {
            $now = microtime(true);
            $rows = $this->query('SELECT * FROM outbox WHERE state="pending" AND due<=? AND lease_until<=? ORDER BY CASE kind WHEN "cancel" THEN 0 ELSE 1 END,id LIMIT 1', [$now, $now]);
            if (! $rows) {
                return null;
            }
            $row = $rows[0];
            $this->execute('UPDATE outbox SET owner=?,lease_until=?,attempts=attempts+1 WHERE id=?', [$owner, $now + 6, $row['id']]);
            $row['owner'] = $owner;
            $row['attempts']++;

            return $row;
        });
    }
}
