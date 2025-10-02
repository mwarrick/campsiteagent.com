<?php

namespace CampsiteAgent\Repositories;

use CampsiteAgent\Infrastructure\Database;
use PDO;

class ParkRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function findActiveParks(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM parks WHERE active = 1');
        return $stmt->fetchAll();
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM parks ORDER BY name');
        return $stmt->fetchAll();
    }

    public function upsert(string $name, string $externalId, bool $active = true): void
    {
        $sql = 'INSERT INTO parks (name, external_id, active) VALUES (:name, :external_id, :active)
                ON DUPLICATE KEY UPDATE name = VALUES(name), active = VALUES(active)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':external_id' => $externalId,
            ':active' => $active ? 1 : 0,
        ]);
    }

    public function setActive(int $parkId, bool $active): void
    {
        $stmt = $this->pdo->prepare('UPDATE parks SET active = :active WHERE id = :id');
        $stmt->execute([':active' => $active ? 1 : 0, ':id' => $parkId]);
    }
}
