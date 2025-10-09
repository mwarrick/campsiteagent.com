<?php
/**
 * Run Migration 019: Add external API identifiers
 * 
 * This migration adds columns to properly map ReserveCalifornia API data
 * to our database, addressing the site number mismatch issue.
 */

require_once __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Infrastructure\Database;

try {
    $pdo = Database::getConnection();
    
    echo "Starting Migration 019: Add external API identifiers...\n";
    
    // Read the migration file
    $migrationFile = __DIR__ . '/../migrations/019_add_external_api_identifiers.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Check which columns already exist
    echo "Checking existing columns...\n";
    
    // Check sites table columns
    $stmt = $pdo->query("SHOW COLUMNS FROM sites LIKE 'external_%'");
    $existingSitesColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Existing sites external columns: " . implode(', ', $existingSitesColumns) . "\n";
    
    // Check facilities table columns
    $stmt = $pdo->query("SHOW COLUMNS FROM facilities LIKE 'external_%'");
    $existingFacilitiesColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Existing facilities external columns: " . implode(', ', $existingFacilitiesColumns) . "\n";
    
    // Add missing columns to sites table
    if (!in_array('external_site_id', $existingSitesColumns)) {
        echo "Adding external_site_id to sites table...\n";
        $pdo->exec("ALTER TABLE sites ADD COLUMN external_site_id VARCHAR(50) NULL AFTER site_number");
    }
    
    if (!in_array('external_unit_type_id', $existingSitesColumns)) {
        echo "Adding external_unit_type_id to sites table...\n";
        $pdo->exec("ALTER TABLE sites ADD COLUMN external_unit_type_id VARCHAR(50) NULL AFTER unit_type_id");
    }
    
    if (!in_array('external_facility_id', $existingSitesColumns)) {
        echo "Adding external_facility_id to sites table...\n";
        $pdo->exec("ALTER TABLE sites ADD COLUMN external_facility_id VARCHAR(50) NULL AFTER facility_id");
    }
    
    // Add missing columns to facilities table
    if (!in_array('external_facility_id', $existingFacilitiesColumns)) {
        echo "Adding external_facility_id to facilities table...\n";
        $pdo->exec("ALTER TABLE facilities ADD COLUMN external_facility_id VARCHAR(50) NULL AFTER facility_id");
    }
    
    // Add indexes (ignore errors if they already exist)
    echo "Adding indexes...\n";
    try {
        $pdo->exec("CREATE INDEX idx_sites_external_site_id ON sites(external_site_id)");
    } catch (Exception $e) {
        echo "Index idx_sites_external_site_id already exists or failed: " . $e->getMessage() . "\n";
    }
    
    try {
        $pdo->exec("CREATE INDEX idx_sites_external_facility_id ON sites(external_facility_id)");
    } catch (Exception $e) {
        echo "Index idx_sites_external_facility_id already exists or failed: " . $e->getMessage() . "\n";
    }
    
    try {
        $pdo->exec("CREATE INDEX idx_facilities_external_facility_id ON facilities(external_facility_id)");
    } catch (Exception $e) {
        echo "Index idx_facilities_external_facility_id already exists or failed: " . $e->getMessage() . "\n";
    }
    
    // Update existing data
    echo "Updating existing data...\n";
    $pdo->exec("UPDATE facilities SET external_facility_id = facility_id WHERE external_facility_id IS NULL");
    $pdo->exec("UPDATE sites SET external_site_id = site_number WHERE external_site_id IS NULL");
    
    echo "✅ Migration 019 completed successfully!\n";
    echo "Added external API identifier columns to sites and facilities tables.\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
