<?php

namespace CampsiteAgent\Repositories;

use CampsiteAgent\Infrastructure\Database;
use PDO;

class UserPreferencesRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /**
     * Get all preferences for a user
     */
    public function getByUserId(int $userId): array
    {
        $sql = 'SELECT p.*, pk.name as park_name 
                FROM user_alert_preferences p
                LEFT JOIN parks pk ON p.park_id = pk.id
                WHERE p.user_id = :user_id
                ORDER BY p.created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a specific preference by ID
     */
    public function getById(int $id): ?array
    {
        $sql = 'SELECT p.*, pk.name as park_name 
                FROM user_alert_preferences p
                LEFT JOIN parks pk ON p.park_id = pk.id
                WHERE p.id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Create a new preference
     */
    public function create(
        int $userId,
        ?int $parkId,
        ?string $startDate,
        ?string $endDate,
        string $frequency = 'immediate',
        bool $weekendOnly = true,
        bool $enabled = true
    ): int {
        $sql = 'INSERT INTO user_alert_preferences 
                (user_id, park_id, start_date, end_date, frequency, weekend_only, enabled) 
                VALUES (:user_id, :park_id, :start_date, :end_date, :frequency, :weekend_only, :enabled)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':park_id' => $parkId,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':frequency' => $frequency,
            ':weekend_only' => $weekendOnly ? 1 : 0,
            ':enabled' => $enabled ? 1 : 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update an existing preference
     */
    public function update(
        int $id,
        ?int $parkId,
        ?string $startDate,
        ?string $endDate,
        string $frequency,
        bool $weekendOnly,
        bool $enabled
    ): bool {
        $sql = 'UPDATE user_alert_preferences 
                SET park_id = :park_id, 
                    start_date = :start_date, 
                    end_date = :end_date, 
                    frequency = :frequency, 
                    weekend_only = :weekend_only, 
                    enabled = :enabled
                WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':park_id' => $parkId,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':frequency' => $frequency,
            ':weekend_only' => $weekendOnly ? 1 : 0,
            ':enabled' => $enabled ? 1 : 0,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a preference
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_alert_preferences WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get users who should receive immediate alerts for a specific park and date
     */
    public function getUsersForImmediateAlert(int $parkId, string $date, bool $isWeekend): array
    {
        $sql = 'SELECT DISTINCT u.id, u.email, u.first_name, p.park_id
                FROM users u
                INNER JOIN user_alert_preferences p ON u.id = p.user_id
                WHERE p.enabled = 1
                  AND p.frequency = "immediate"
                  AND u.verified_at IS NOT NULL
                  AND (p.park_id IS NULL OR p.park_id = :park_id)
                  AND (p.start_date IS NULL OR p.start_date <= :date)
                  AND (p.end_date IS NULL OR p.end_date >= :date)
                  AND (p.weekend_only = 0 OR :is_weekend = 1)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':park_id' => $parkId,
            ':date' => $date,
            ':is_weekend' => $isWeekend ? 1 : 0,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Toggle a preference on/off
     */
    public function toggleEnabled(int $id): bool
    {
        $sql = 'UPDATE user_alert_preferences SET enabled = NOT enabled WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Check if user has any enabled preferences
     */
    public function hasEnabledPreferences(int $userId): bool
    {
        $sql = 'SELECT COUNT(*) as count FROM user_alert_preferences WHERE user_id = :user_id AND enabled = 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($row['count'] ?? 0) > 0;
    }
}

