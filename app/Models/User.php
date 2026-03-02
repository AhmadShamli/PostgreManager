<?php

declare(strict_types=1);

namespace PostgreManager\Models;

use PDO;

class User
{
    public function __construct(private PDO $db) {}

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pm_users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pm_users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function all(): array
    {
        return $this->db->query('SELECT id, name, email, role, is_active, created_at FROM pm_users ORDER BY created_at ASC')
                        ->fetchAll();
    }

    public function create(string $name, string $email, string $password, string $role = 'viewer'): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pm_users (name, email, password_hash, role)
             VALUES (:name, :email, :hash, :role)'
        );
        $stmt->execute([
            ':name'  => $name,
            ':email' => $email,
            ':hash'  => password_hash($password, PASSWORD_BCRYPT),
            ':role'  => $role,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $fields): void
    {
        $allowed = ['name', 'email', 'role', 'is_active'];
        $set     = [];
        $params  = [':id' => $id];

        foreach ($fields as $key => $val) {
            if (!in_array($key, $allowed, true)) continue;
            // SQLite: store is_active as integer
            if ($key === 'is_active') {
                $val = ($val === 'true' || $val === true || $val === 1) ? 1 : 0;
            }
            $set[]             = "{$key} = :{$key}";
            $params[":{$key}"] = $val;
        }

        if (empty($set)) return;

        $this->db->prepare('UPDATE pm_users SET ' . implode(', ', $set) . ' WHERE id = :id')
                 ->execute($params);
    }

    public function changePassword(int $id, string $newPassword): void
    {
        $this->db->prepare('UPDATE pm_users SET password_hash = :hash WHERE id = :id')
                 ->execute([':hash' => password_hash($newPassword, PASSWORD_BCRYPT), ':id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM pm_users WHERE id = :id')->execute([':id' => $id]);
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM pm_users')->fetchColumn();
    }
}
