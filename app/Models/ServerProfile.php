<?php

declare(strict_types=1);

namespace PostgreManager\Models;

use PDO;

class ServerProfile
{
    public function __construct(private PDO $db) {}

    public function allForUser(int $userId, bool $includeShared = true): array
    {
        $sql = 'SELECT * FROM pm_server_profiles WHERE user_id = :uid';
        if ($includeShared) {
            $sql = 'SELECT * FROM pm_server_profiles WHERE user_id = :uid OR is_shared = TRUE';
        }
        $stmt = $this->db->prepare($sql . ' ORDER BY label ASC');
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pm_server_profiles WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pm_server_profiles (user_id, label, host, port, db_name, pg_username, pg_password_enc, is_shared)
             VALUES (:user_id, :label, :host, :port, :db_name, :pg_username, :pg_password_enc, :is_shared)'
        );
        $stmt->execute([
            ':user_id'         => $userId,
            ':label'           => $data['label'],
            ':host'            => $data['host'],
            ':port'            => (int) ($data['port'] ?? 5432),
            ':db_name'         => $data['db_name'] ?? 'postgres',
            ':pg_username'     => $data['pg_username'],
            ':pg_password_enc' => $this->encrypt($data['pg_password']),
            ':is_shared'       => isset($data['is_shared']) && $data['is_shared'] ? 1 : 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $params = [
            ':id'          => $id,
            ':label'       => $data['label'],
            ':host'        => $data['host'],
            ':port'        => (int) ($data['port'] ?? 5432),
            ':db_name'     => $data['db_name'] ?? 'postgres',
            ':pg_username' => $data['pg_username'],
            ':is_shared'   => isset($data['is_shared']) && $data['is_shared'] ? 1 : 0,
        ];

        $sql = 'UPDATE pm_server_profiles SET label=:label, host=:host, port=:port,
                db_name=:db_name, pg_username=:pg_username, is_shared=:is_shared';

        if (!empty($data['pg_password'])) {
            $sql .= ', pg_password_enc=:pg_password_enc';
            $params[':pg_password_enc'] = $this->encrypt($data['pg_password']);
        }

        $this->db->prepare($sql . ' WHERE id = :id')->execute($params);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM pm_server_profiles WHERE id = :id')->execute([':id' => $id]);
    }

    public function decryptPassword(string $encrypted): string
    {
        return $this->decrypt($encrypted);
    }

    private function encrypt(string $value): string
    {
        $key    = substr(hash('sha256', $_ENV['APP_SECRET'] ?? 'secret'), 0, 32);
        $iv     = random_bytes(16);
        $enc    = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $enc);
    }

    private function decrypt(string $value): string
    {
        $key    = substr(hash('sha256', $_ENV['APP_SECRET'] ?? 'secret'), 0, 32);
        $data   = base64_decode($value);
        $iv     = substr($data, 0, 16);
        $enc    = substr($data, 16);
        return openssl_decrypt($enc, 'AES-256-CBC', $key, 0, $iv);
    }
}
