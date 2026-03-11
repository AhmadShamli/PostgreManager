<?php

declare(strict_types=1);

namespace PostgreManager\Models;

use PDO;

class DatabaseLock
{
    public function __construct(private PDO $db) {}

    public function getByServer(int $serverId): array
    {
        $stmt = $this->db->prepare(
            'SELECT database_name, is_locked
             FROM pm_database_locks
             WHERE server_id = :server_id'
        );
        $stmt->execute([':server_id' => $serverId]);

        $locks = [];
        foreach ($stmt->fetchAll() as $row) {
            $locks[$row['database_name']] = (bool) $row['is_locked'];
        }

        return $locks;
    }

    public function isLocked(int $serverId, string $databaseName): bool
    {
        $stmt = $this->db->prepare(
            'SELECT is_locked
             FROM pm_database_locks
             WHERE server_id = :server_id AND database_name = :database_name'
        );
        $stmt->execute([
            ':server_id' => $serverId,
            ':database_name' => $databaseName,
        ]);

        $value = $stmt->fetchColumn();
        return $value !== false && (bool) $value;
    }

    public function setLocked(int $serverId, string $databaseName, bool $locked): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pm_database_locks (server_id, database_name, is_locked, updated_at)
             VALUES (:server_id, :database_name, :is_locked, datetime(\'now\'))
             ON CONFLICT(server_id, database_name)
             DO UPDATE SET is_locked = excluded.is_locked, updated_at = datetime(\'now\')'
        );
        $stmt->execute([
            ':server_id' => $serverId,
            ':database_name' => $databaseName,
            ':is_locked' => $locked ? 1 : 0,
        ]);
    }
}
