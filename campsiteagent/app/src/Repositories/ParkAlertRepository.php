<?php

namespace CampsiteAgent\Repositories;

use PDO;

class ParkAlertRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a new park alert
     */
    public function createAlert(
        int $parkId,
        string $alertType,
        string $severity,
        string $title,
        ?string $description = null,
        ?string $effectiveDate = null,
        ?string $expirationDate = null,
        ?string $sourceUrl = null
    ): int {
        // Truncate title if it's too long (500 chars max)
        if (strlen($title) > 500) {
            $title = substr($title, 0, 497) . '...';
        }
        $sql = 'INSERT INTO park_alerts (
            park_id, alert_type, severity, title, description, 
            effective_date, expiration_date, source_url
        ) VALUES (
            :park_id, :alert_type, :severity, :title, :description,
            :effective_date, :expiration_date, :source_url
        )';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':park_id' => $parkId,
            ':alert_type' => $alertType,
            ':severity' => $severity,
            ':title' => $title,
            ':description' => $description,
            ':effective_date' => $effectiveDate,
            ':expiration_date' => $expirationDate,
            ':source_url' => $sourceUrl
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get active alerts for a park
     */
    public function getActiveAlertsForPark(int $parkId): array
    {
        $sql = 'SELECT * FROM park_alerts 
                WHERE park_id = :park_id 
                  AND is_active = 1 
                  AND (expiration_date IS NULL OR expiration_date >= CURDATE())
                ORDER BY severity DESC, created_at DESC';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':park_id' => $parkId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all active alerts for multiple parks
     */
    public function getActiveAlertsForParks(array $parkIds): array
    {
        if (empty($parkIds)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($parkIds) - 1) . '?';
        // Filter out alerts where expiration_date has passed
        // Also filter alerts with date ranges in the past by checking if expiration_date is before today
        $sql = "SELECT * FROM park_alerts 
                WHERE park_id IN ($placeholders)
                  AND is_active = 1 
                  AND (expiration_date IS NULL OR expiration_date >= CURDATE())
                  AND (effective_date IS NULL OR effective_date <= CURDATE())
                ORDER BY park_id, severity DESC, created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parkIds);
        
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Additional filter: check if alert text contains past date ranges
        $filtered = [];
        foreach ($alerts as $alert) {
            // If expiration_date is NULL, check if the text mentions dates that have passed
            if ($alert['expiration_date'] === null) {
                // Check if text contains month ranges that are in the past
                if (!$this->isAlertTextExpired($alert['title'] . ' ' . ($alert['description'] ?? ''))) {
                    $filtered[] = $alert;
                }
            } else {
                $filtered[] = $alert;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Check if alert text contains date ranges that are in the past
     */
    private function isAlertTextExpired(string $text): bool
    {
        // Pattern to match month day - month day patterns
        if (preg_match('/(\w+)\s+(\d{1,2})[\s-]+(?:to|through|-)\s+(\w+)\s+(\d{1,2})/i', $text, $matches)) {
            $startMonth = $matches[1];
            $startDay = (int)$matches[2];
            $endMonth = $matches[3];
            $endDay = (int)$matches[4];
            
            $currentYear = (int)date('Y');
            $today = new \DateTime();
            
            $monthNames = [
                'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
                'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
                'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12
            ];
            
            $startMonthNum = $monthNames[strtolower($startMonth)] ?? null;
            $endMonthNum = $monthNames[strtolower($endMonth)] ?? null;
            
            if ($startMonthNum && $endMonthNum) {
                // Determine year - if end month is before start month, end is next year
                $endYear = $currentYear;
                if ($endMonthNum < $startMonthNum) {
                    $endYear = $currentYear + 1;
                }
                
                // Check if the end date has passed
                $endDate = new \DateTime("$endYear-$endMonthNum-$endDay");
                if ($endDate < $today) {
                    return true; // Alert is expired
                }
            }
        }
        
        return false;
    }

    /**
     * Deactivate old alerts for a park (before scraping new ones)
     */
    public function deactivateOldAlerts(int $parkId): int
    {
        $sql = 'UPDATE park_alerts 
                SET is_active = 0, updated_at = NOW() 
                WHERE park_id = :park_id AND is_active = 1';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':park_id' => $parkId]);
        
        return $stmt->rowCount();
    }

    /**
     * Check if an alert already exists (to avoid duplicates)
     */
    public function alertExists(int $parkId, string $title, string $alertType): bool
    {
        $sql = 'SELECT COUNT(*) FROM park_alerts 
                WHERE park_id = :park_id 
                  AND title = :title 
                  AND alert_type = :alert_type 
                  AND is_active = 1';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':park_id' => $parkId,
            ':title' => $title,
            ':alert_type' => $alertType
        ]);
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get alert statistics
     */
    public function getAlertStats(): array
    {
        $sql = 'SELECT 
                    COUNT(*) as total_alerts,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_alerts,
                    SUM(CASE WHEN severity = "critical" AND is_active = 1 THEN 1 ELSE 0 END) as critical_alerts,
                    SUM(CASE WHEN severity = "warning" AND is_active = 1 THEN 1 ELSE 0 END) as warning_alerts,
                    SUM(CASE WHEN severity = "info" AND is_active = 1 THEN 1 ELSE 0 END) as info_alerts
                FROM park_alerts';
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
