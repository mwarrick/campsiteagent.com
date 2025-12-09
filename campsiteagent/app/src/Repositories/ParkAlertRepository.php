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
        // This catches alerts that don't have expiration_date set but are expired based on text
        $filtered = [];
        $today = new \DateTime();
        
        foreach ($alerts as $alert) {
            $isExpired = false;
            
            // Check expiration_date first
            if ($alert['expiration_date'] !== null) {
                $expDate = new \DateTime($alert['expiration_date']);
                if ($expDate < $today) {
                    $isExpired = true;
                }
            }
            
            // If not expired by date, check text for date ranges
            if (!$isExpired && $this->isAlertTextExpired($alert['title'] . ' ' . ($alert['description'] ?? ''))) {
                $isExpired = true;
            }
            
            // Only include non-expired alerts
            if (!$isExpired) {
                $filtered[] = $alert;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Check if alert text contains date ranges that are in the past
     * When no year is specified in the text, assume dates apply to the current year
     * Supports dates with years: "September 29, 2025 through October 29, 2025"
     */
    private function isAlertTextExpired(string $text): bool
    {
        // Pattern to match month day - month day patterns, optionally with years
        // Examples: 
        //   - "June 1 to September 30" (no year, assumes current year)
        //   - "September 29, 2025 through October 29, 2025" (with years)
        //   - "September 29, 2025, through October 29, 2025" (with comma after year)
        $pattern = '/(\w+)\s+(\d{1,2})(?:,\s*(\d{4}))?[\s,]*[\s-]+(?:to|through|-)[\s,]*(\w+)\s+(\d{1,2})(?:,\s*(\d{4}))?/i';
        
        if (preg_match($pattern, $text, $matches)) {
            $startMonth = $matches[1];
            $startDay = (int)$matches[2];
            $startYear = !empty($matches[3]) ? (int)$matches[3] : null;
            $endMonth = $matches[4];
            $endDay = (int)$matches[5];
            $endYear = !empty($matches[6]) ? (int)$matches[6] : null;
            
            // Dates without year always apply to current year
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
                // Determine years
                if ($startYear !== null && $endYear !== null) {
                    // Both years specified - use them
                    $finalEndYear = $endYear;
                } elseif ($startYear !== null) {
                    // Only start year specified - assume end is same year or next year
                    $finalEndYear = $endMonthNum < $startMonthNum ? $startYear + 1 : $startYear;
                } elseif ($endYear !== null) {
                    // Only end year specified - use it
                    $finalEndYear = $endYear;
                } else {
                    // No years specified - use current year logic
                    $finalEndYear = $currentYear;
                    
                    if ($endMonthNum < $startMonthNum) {
                        // Cross-year range (e.g., November to February)
                        $finalEndYear = $currentYear + 1;
                    }
                }
                
                // Check if the end date has passed
                $endDate = new \DateTime("$finalEndYear-$endMonthNum-$endDay");
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
     * Checks regardless of active status
     */
    public function alertExists(int $parkId, string $title, string $alertType): bool
    {
        $sql = 'SELECT COUNT(*) FROM park_alerts 
                WHERE park_id = :park_id 
                  AND title = :title 
                  AND alert_type = :alert_type';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':park_id' => $parkId,
            ':title' => $title,
            ':alert_type' => $alertType
        ]);
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Check if an alert exists and is active
     */
    public function alertExistsAndActive(int $parkId, string $title, string $alertType): bool
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
     * Reactivate an alert that was deactivated but is now found again
     */
    public function reactivateAlert(int $parkId, string $title, string $alertType): void
    {
        $sql = 'UPDATE park_alerts 
                SET is_active = 1, updated_at = NOW() 
                WHERE park_id = :park_id 
                  AND title = :title 
                  AND alert_type = :alert_type 
                  AND is_active = 0';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':park_id' => $parkId,
            ':title' => $title,
            ':alert_type' => $alertType
        ]);
    }

    /**
     * Deactivate alerts that are no longer on the website (not in current scrape list)
     */
    public function deactivateAlertsNotInList(int $parkId, array $currentAlertTitles): int
    {
        if (empty($currentAlertTitles)) {
            // If no current alerts, deactivate all alerts for this park
            $sql = 'UPDATE park_alerts 
                    SET is_active = 0, updated_at = NOW() 
                    WHERE park_id = :park_id AND is_active = 1';
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':park_id' => $parkId]);
            return $stmt->rowCount();
        }
        
        // Build placeholders for IN clause
        $placeholders = str_repeat('?,', count($currentAlertTitles) - 1) . '?';
        
        // Deactivate alerts that are not in the current list
        $sql = "UPDATE park_alerts 
                SET is_active = 0, updated_at = NOW() 
                WHERE park_id = ? 
                  AND is_active = 1 
                  AND title NOT IN ($placeholders)";
        
        $params = array_merge([$parkId], $currentAlertTitles);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }

    /**
     * Deactivate all expired alerts for a park
     * This includes alerts with expiration_date in the past AND alerts with date ranges in text that have expired
     */
    public function deactivateExpiredAlerts(int $parkId): int
    {
        // First, deactivate alerts with expiration_date in the past
        $sql = 'UPDATE park_alerts 
                SET is_active = 0, updated_at = NOW() 
                WHERE park_id = :park_id 
                  AND is_active = 1 
                  AND (expiration_date IS NOT NULL AND expiration_date < CURDATE())';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':park_id' => $parkId]);
        $count = $stmt->rowCount();
        
        // Also check alerts with NULL expiration_date but expired date ranges in text
        $sql2 = 'SELECT id, title, description FROM park_alerts 
                 WHERE park_id = :park_id 
                   AND is_active = 1 
                   AND expiration_date IS NULL';
        
        $stmt2 = $this->pdo->prepare($sql2);
        $stmt2->execute([':park_id' => $parkId]);
        $alertsWithoutExpDate = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        $expiredIds = [];
        foreach ($alertsWithoutExpDate as $alert) {
            $text = $alert['title'] . ' ' . ($alert['description'] ?? '');
            if ($this->isAlertTextExpired($text)) {
                $expiredIds[] = $alert['id'];
            }
        }
        
        // Deactivate alerts expired based on text
        if (!empty($expiredIds)) {
            $placeholders = str_repeat('?,', count($expiredIds) - 1) . '?';
            $sql3 = "UPDATE park_alerts 
                     SET is_active = 0, updated_at = NOW() 
                     WHERE id IN ($placeholders)";
            
            $stmt3 = $this->pdo->prepare($sql3);
            $stmt3->execute($expiredIds);
            $count += $stmt3->rowCount();
        }
        
        return $count;
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
