<?php

declare(strict_types=1);

namespace Pact\Federation;

use PDO;
use RuntimeException;

/** SQLite durable ledger and transactional outbox. Use one instance per PDO connection. */
final class SqliteLedger implements Ledger
{
    public function __construct(private readonly PDO $db)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE IF NOT EXISTS federation_ledger (federation TEXT NOT NULL, sender TEXT NOT NULL, id TEXT NOT NULL, destination TEXT NOT NULL, payload TEXT NOT NULL, digest TEXT NOT NULL, receipt TEXT, PRIMARY KEY (federation,sender,id))');
        $db->exec('CREATE TABLE IF NOT EXISTS federation_outbox (federation TEXT NOT NULL, sender TEXT NOT NULL, id TEXT NOT NULL, PRIMARY KEY (federation,sender,id), FOREIGN KEY (federation,sender,id) REFERENCES federation_ledger(federation,sender,id))');
    }

    public function append(string $federation, string $sender, string $id, string $destination, string $payload): bool
    {
        if ($federation === '' || $sender === '' || $id === '' || $destination === '') {
            throw new \InvalidArgumentException('Ledger identity and destination are required');
        }
        $digest = hash('sha256', strlen($destination).':'.$destination.$payload);
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $q = $this->db->prepare('SELECT digest FROM federation_ledger WHERE federation=? AND sender=? AND id=?');
            $q->execute([$federation, $sender, $id]);
            $existing = $q->fetchColumn();
            if ($existing !== false) {
                if (! hash_equals($existing, $digest)) {
                    throw new RuntimeException('Ledger id reused with different content');
                }
                $this->db->exec('COMMIT');
                return false;
            }
            $q = $this->db->prepare('INSERT INTO federation_ledger (federation,sender,id,destination,payload,digest) VALUES (?,?,?,?,?,?)');
            $q->execute([$federation, $sender, $id, $destination, $payload, $digest]);
            $q = $this->db->prepare('INSERT INTO federation_outbox VALUES (?,?,?)');
            $q->execute([$federation, $sender, $id]);
            $this->db->exec('COMMIT');
            return true;
        } catch (\Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }
    }

    public function receipt(string $federation, string $sender, string $id, string $receipt): void
    {
        if ($receipt === '') throw new \InvalidArgumentException('Receipt is required');
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $q = $this->db->prepare('SELECT receipt FROM federation_ledger WHERE federation=? AND sender=? AND id=?');
            $q->execute([$federation, $sender, $id]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if ($row === false || ($row['receipt'] !== null && ! hash_equals($row['receipt'], $receipt))) throw new RuntimeException('Unknown entry or conflicting receipt');
            $q = $this->db->prepare('UPDATE federation_ledger SET receipt=? WHERE federation=? AND sender=? AND id=?');
            $q->execute([$receipt, $federation, $sender, $id]);
            $q = $this->db->prepare('DELETE FROM federation_outbox WHERE federation=? AND sender=? AND id=?');
            $q->execute([$federation, $sender, $id]);
            $this->db->exec('COMMIT');
        } catch (\Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> Pending deliveries in insertion order. */
    public function pending(): array
    {
        return $this->db->query('SELECT l.* FROM federation_outbox o JOIN federation_ledger l USING (federation,sender,id) ORDER BY o.rowid')->fetchAll(PDO::FETCH_ASSOC);
    }
}
