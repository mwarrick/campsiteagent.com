<?php

namespace CampsiteAgent\Repositories;

use CampsiteAgent\Infrastructure\Database;
use PDO;

class SettingsRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function get(string $key): ?string
    {
        $stmt = $this->pdo->prepare('SELECT `value` FROM settings WHERE `key` = :key');
        $stmt->execute([':key' => $key]);
        $val = $stmt->fetchColumn();
        return $val === false ? null : (string)$val;
    }

    public function set(string $key, ?string $value): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO settings(`key`, `value`) VALUES(:key, :value) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
        $stmt->execute([':key' => $key, ':value' => $value]);
    }
}


