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

    /**
     * Get latest scrape runs with optional limit
     * @param int $limit Maximum number of runs to return
     * @return array Array of run records with park information
     */
    public function getLatestRuns(int $limit = 20): array
    {
        $sql = 'SELECT 
                    ar.id,
                    ar.park_id,
                    ar.started_at,
                    ar.finished_at,
                    ar.status,
                    ar.error,
                    ar.configuration,
                    ar.created_at,
                    p.name AS park_name,
                    p.park_number,
                    TIMESTAMPDIFF(SECOND, ar.started_at, COALESCE(ar.finished_at, NOW())) AS duration_seconds
                FROM availability_runs ar
                LEFT JOIN parks p ON ar.park_id = p.id
                ORDER BY ar.started_at DESC
                LIMIT :limit';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
