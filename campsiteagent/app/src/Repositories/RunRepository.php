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
}
