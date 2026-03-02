<?php

declare(strict_types=1);

namespace PostgreManager\Models;

use PDO;

class Setting
{
    public function __construct(private PDO $db) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $stmt = $this->db->prepare('SELECT value FROM pm_settings WHERE key = :key');
        $stmt->execute([':key' => $key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    }

    public function set(string $key, string $value): void
    {
        $this->db->prepare(
            'INSERT INTO pm_settings (key, value) VALUES (:key, :value)
             ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value'
        )->execute([':key' => $key, ':value' => $value]);
    }

    public function isSetupComplete(): bool
    {
        return $this->get('setup_complete') === 'true';
    }

    public function markSetupComplete(): void
    {
        $this->set('setup_complete', 'true');
    }
}
