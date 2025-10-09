<?php

namespace CampsiteAgent\Repositories;

use CampsiteAgent\Infrastructure\Database;
use PDO;

class FacilityRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /**
     * Find or create a facility by park_id and facility_id
     * @return int facility ID (database ID)
     */
    public function upsertFacility(
        int $parkId, 
        string $name, 
        ?string $facilityId = null, 
        ?string $description = null,
        ?string $externalFacilityId = null
    ): int {
        // Try to find existing facility by external_facility_id first, then facility_id
        if ($externalFacilityId !== null) {
            $sql = 'SELECT id FROM facilities WHERE park_id = :park_id AND external_facility_id = :external_facility_id LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':park_id' => $parkId, ':external_facility_id' => $externalFacilityId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update existing facility
                $updateSql = 'UPDATE facilities SET 
                    name = :name,
                    description = :description,
                    facility_id = :facility_id,
                    external_facility_id = :external_facility_id
                    WHERE id = :id';
                $updateStmt = $this->pdo->prepare($updateSql);
                $updateStmt->execute([
                    ':name' => $name,
                    ':description' => $description,
                    ':facility_id' => $facilityId,
                    ':external_facility_id' => $externalFacilityId,
                    ':id' => $existing['id']
                ]);
                return (int)$existing['id'];
            }
        }
        
        // Fallback: try to find by facility_id (legacy)
        if ($facilityId !== null) {
            $sql = 'SELECT id FROM facilities WHERE park_id = :park_id AND facility_id = :facility_id LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':park_id' => $parkId, ':facility_id' => $facilityId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update existing facility with external_facility_id
                $updateSql = 'UPDATE facilities SET 
                    name = :name,
                    description = :description,
                    external_facility_id = :external_facility_id
                    WHERE id = :id';
                $updateStmt = $this->pdo->prepare($updateSql);
                $updateStmt->execute([
                    ':name' => $name,
                    ':description' => $description,
                    ':external_facility_id' => $externalFacilityId,
                    ':id' => $existing['id']
                ]);
                return (int)$existing['id'];
            }
        }

        // Create new facility
        $sql = 'INSERT INTO facilities (
            park_id, facility_id, name, description, external_facility_id
        ) VALUES (
            :park_id, :facility_id, :name, :description, :external_facility_id
        )';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':park_id' => $parkId,
            ':facility_id' => $facilityId,
            ':name' => $name,
            ':description' => $description,
            ':external_facility_id' => $externalFacilityId
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get all facilities for a park
     */
    public function findByParkId(int $parkId): array
    {
        $sql = 'SELECT * FROM facilities WHERE park_id = :park_id ORDER BY name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':park_id' => $parkId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get active facilities for a park
     */
    public function findActiveFacilities(int $parkId): array
    {
        $sql = 'SELECT * FROM facilities WHERE park_id = :park_id AND active = 1 ORDER BY name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':park_id' => $parkId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get facility IDs that are active for a park (for filtering)
     */
    public function getActiveFacilityIds(int $parkId): array
    {
        $sql = 'SELECT facility_id FROM facilities WHERE park_id = :park_id AND active = 1 AND facility_id IS NOT NULL';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':park_id' => $parkId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'facility_id');
    }
}

