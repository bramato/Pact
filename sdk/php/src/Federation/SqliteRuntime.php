<?php

declare(strict_types=1);

namespace Pact\Federation;

use PDO;
use RuntimeException;

/** Reusable SQLite transaction, receipt, checkpoint and outbox implementation. */
abstract class SqliteRuntime implements Runtime
{
    private bool $inTransaction = false;

    public function __construct(protected readonly PDO $db)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA busy_timeout=5000');
        $db->exec('CREATE TABLE IF NOT EXISTS pact_federation_exchange (federation TEXT NOT NULL, actor TEXT NOT NULL, id TEXT NOT NULL, destination TEXT NOT NULL, payload TEXT NOT NULL, digest TEXT NOT NULL, result TEXT, PRIMARY KEY(federation,actor,id));
            CREATE TABLE IF NOT EXISTS pact_federation_outbox (federation TEXT NOT NULL, actor TEXT NOT NULL, id TEXT NOT NULL, receipt TEXT, PRIMARY KEY(federation,actor,id));
            CREATE TABLE IF NOT EXISTS pact_federation_checkpoint (federation TEXT NOT NULL, task TEXT NOT NULL, state TEXT NOT NULL, PRIMARY KEY(federation,task))');
    }

    public function transaction(callable $work): mixed
    {
        if ($this->inTransaction) throw new RuntimeException('Nested Federation transactions are not supported');
        $this->db->exec('BEGIN IMMEDIATE');
        $this->inTransaction = true;
        try {
            $result = $work();
            $this->db->exec('COMMIT');
            return $result;
        } catch (\Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        } finally {
            $this->inTransaction = false;
        }
    }

    private function requireTransaction(): void
    {
        if (! $this->inTransaction) throw new RuntimeException('Federation transaction required');
    }

    public function appendExchange(string $federation, string $actor, string $id, string $destination, string $payload): bool
    {
        $this->requireTransaction();
        if ($federation === '' || $actor === '' || $id === '' || $destination === '') throw new \InvalidArgumentException('Exchange identity and destination required');
        $digest = hash('sha256', strlen($destination).':'.$destination.$payload);
        $q = $this->db->prepare('SELECT digest FROM pact_federation_exchange WHERE federation=? AND actor=? AND id=?');
        $q->execute([$federation, $actor, $id]);
        $previous = $q->fetchColumn();
        if ($previous !== false) {
            if (! hash_equals($previous, $digest)) throw new RuntimeException('Exchange id reused with different content');
            return false;
        }
        $q = $this->db->prepare('INSERT INTO pact_federation_exchange (federation,actor,id,destination,payload,digest) VALUES (?,?,?,?,?,?)');
        $q->execute([$federation, $actor, $id, $destination, $payload, $digest]);
        $q = $this->db->prepare('INSERT INTO pact_federation_outbox (federation,actor,id) VALUES (?,?,?)');
        $q->execute([$federation, $actor, $id]);
        return true;
    }

    public function replayResult(string $federation, string $actor, string $id): mixed
    {
        $this->requireTransaction();
        $q = $this->db->prepare('SELECT result FROM pact_federation_exchange WHERE federation=? AND actor=? AND id=?');
        $q->execute([$federation, $actor, $id]);
        $result = $q->fetchColumn();
        if ($result === false || $result === null) throw new RuntimeException('Committed exchange result missing');
        return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
    }

    public function saveResult(string $federation, string $actor, string $id, mixed $result): void
    {
        $this->requireTransaction();
        $q = $this->db->prepare('UPDATE pact_federation_exchange SET result=? WHERE federation=? AND actor=? AND id=? AND result IS NULL');
        $q->execute([json_encode($result, JSON_THROW_ON_ERROR), $federation, $actor, $id]);
        if ($q->rowCount() !== 1) throw new RuntimeException('Exchange result already saved or missing');
    }

    public function checkpoint(string $federation, string $task, array $state): void
    {
        $this->requireTransaction();
        $q = $this->db->prepare('INSERT INTO pact_federation_checkpoint VALUES (?,?,?) ON CONFLICT(federation,task) DO UPDATE SET state=excluded.state');
        $q->execute([$federation, $task, json_encode($state, JSON_THROW_ON_ERROR)]);
    }

    public function loadCheckpoint(string $federation, string $task): ?array
    {
        $q = $this->db->prepare('SELECT state FROM pact_federation_checkpoint WHERE federation=? AND task=?');
        $q->execute([$federation, $task]);
        $state = $q->fetchColumn();
        return $state === false ? null : json_decode($state, true, 512, JSON_THROW_ON_ERROR);
    }

    /** Pending work is visible only after its transaction commits. Deliveries must themselves be idempotent. */
    public function pending(): array
    {
        if ($this->inTransaction) throw new RuntimeException('Cannot deliver before commit');
        return $this->db->query('SELECT e.federation,e.actor,e.id,e.destination,e.payload FROM pact_federation_outbox o JOIN pact_federation_exchange e USING(federation,actor,id) WHERE o.receipt IS NULL ORDER BY o.rowid')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function acknowledge(string $federation, string $actor, string $id, string $receipt): void
    {
        if ($receipt === '') throw new \InvalidArgumentException('Receipt required');
        $this->transaction(function () use ($federation, $actor, $id, $receipt): void {
            $q = $this->db->prepare('SELECT receipt FROM pact_federation_outbox WHERE federation=? AND actor=? AND id=?');
            $q->execute([$federation, $actor, $id]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if ($row === false || ($row['receipt'] !== null && ! hash_equals($row['receipt'], $receipt))) throw new RuntimeException('Unknown exchange or conflicting receipt');
            $q = $this->db->prepare('UPDATE pact_federation_outbox SET receipt=? WHERE federation=? AND actor=? AND id=?');
            $q->execute([$receipt, $federation, $actor, $id]);
        });
    }
}
