<?php

namespace CampsiteAgent\Repositories;

use CampsiteAgent\Infrastructure\Database;
use PDO;

class RunRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function startRun(int $parkId): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO availability_runs (park_id, started_at, status) VALUES (:park_id, NOW(), "pending")');
        $stmt->execute([':park_id' => $parkId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function finishRunSuccess(int $runId): void
    {
        $stmt = $this->pdo->prepare('UPDATE availability_runs SET finished_at = NOW(), status = "success", error = NULL WHERE id = :id');
        $stmt->execute([':id' => $runId]);
    }

    public function finishRunError(int $runId, string $error): void
    {
        $stmt = $this->pdo->prepare('UPDATE availability_runs SET finished_at = NOW(), status = "error", error = :error WHERE id = :id');
        $stmt->execute([':id' => $runId, ':error' => $error]);
    }

    public function createDailyRun(array $config): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO availability_runs (park_id, started_at, status, configuration) VALUES (NULL, NOW(), "running", :config)');
        $stmt->execute([':config' => json_encode($config)]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateDailyRun(int $runId, array $data): void
    {
        $fields = [];
        $values = [':id' => $runId];
        
        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
            $values[":$key"] = $value;
        }
        
        if (empty($fields)) {
            return;
        }
        
        $sql = 'UPDATE availability_runs SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
    }

    public function getRunConfig(int $runId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT configuration FROM availability_runs WHERE id = :id');
        $stmt->execute([':id' => $runId]);
        $row = $stmt->fetch();
        return $row ? $row['configuration'] : null;
    }
}
