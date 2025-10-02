<?php

namespace CampsiteAgent\Repositories;

use CampsiteAgent\Infrastructure\Database;
use PDO;

class SiteRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function upsertSite(
        int $parkId, 
        string $siteNumber, 
        ?string $siteType = null, 
        array $attributes = [],
        ?int $facilityId = null,
        ?string $siteName = null,
        ?int $unitTypeId = null,
        ?bool $isAda = null,
        ?int $vehicleLength = null
    ): int {
        // Find existing by park_id, facility_id, and site_number
        $sql = 'SELECT id FROM sites WHERE park_id = :park_id AND site_number = :site_number';
        if ($facilityId) {
            $sql .= ' AND facility_id = :facility_id';
        }
        $sql .= ' LIMIT 1';
        
        $stmt = $this->pdo->prepare($sql);
        $params = [':park_id' => $parkId, ':site_number' => $siteNumber];
        if ($facilityId) {
            $params[':facility_id'] = $facilityId;
        }
        $stmt->execute($params);
        $row = $stmt->fetch();
        
        if ($row) {
            // Update existing site
            $upd = $this->pdo->prepare('UPDATE sites SET 
                site_name = :site_name,
                site_type = :site_type, 
                facility_id = :facility_id,
                unit_type_id = :unit_type_id,
                is_ada = :is_ada,
                vehicle_length = :vehicle_length,
                attributes_json = :attributes_json, 
                updated_at = NOW() 
                WHERE id = :id');
            $updateParams = [
                ':site_name' => $siteName,
                ':site_type' => $siteType,
                ':facility_id' => $facilityId,
                ':unit_type_id' => $unitTypeId,
                ':is_ada' => $isAda ? 1 : 0,
                ':vehicle_length' => $vehicleLength ?? 0,
                ':attributes_json' => json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':id' => $row['id'],
            ];
            
            try {
                $upd->execute($updateParams);
                return (int)$row['id'];
            } catch (\PDOException $e) {
                error_log("SQL Error in upsertSite UPDATE: " . $e->getMessage());
                error_log("Parameters: " . json_encode($updateParams));
                throw $e;
            }
        }
        
        // Insert new site
        $ins = $this->pdo->prepare('INSERT INTO sites (
            park_id, facility_id, site_number, site_name, site_type, 
            unit_type_id, is_ada, vehicle_length, attributes_json
        ) VALUES (
            :park_id, :facility_id, :site_number, :site_name, :site_type,
            :unit_type_id, :is_ada, :vehicle_length, :attributes_json
        )');
        $params = [
            ':park_id' => $parkId,
            ':facility_id' => $facilityId,
            ':site_number' => $siteNumber,
            ':site_name' => $siteName,
            ':site_type' => $siteType,
            ':unit_type_id' => $unitTypeId,
            ':is_ada' => $isAda ? 1 : 0,
            ':vehicle_length' => $vehicleLength ?? 0,
            ':attributes_json' => json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        
        try {
            $ins->execute($params);
            return (int)$this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            error_log("SQL Error in upsertSite INSERT: " . $e->getMessage());
            error_log("Parameters: " . json_encode($params));
            throw $e;
        }
    }

    public function upsertAvailability(int $siteId, string $date, bool $isAvailable): void
    {
        $sql = 'INSERT INTO site_availability (site_id, date, is_available) VALUES (:site_id, :date, :avail)
                ON DUPLICATE KEY UPDATE is_available = VALUES(is_available)';
        $stmt = $this->pdo->prepare($sql);
        
        try {
            $stmt->execute([
                ':site_id' => $siteId,
                ':date' => $date,
                ':avail' => $isAvailable ? 1 : 0,
            ]);
        } catch (\PDOException $e) {
            error_log("SQL Error in upsertAvailability: " . $e->getMessage());
            error_log("Parameters: siteId=$siteId, date=$date, isAvailable=" . ($isAvailable ? 'true' : 'false'));
            throw $e;
        }
    }
}
