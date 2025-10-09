<?php
/**
 * Migration 021: Fix park alerts title column length
 * Run this script to increase the title column length in park_alerts table
 */

require_once __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Infrastructure\Database;

echo "Starting Migration 021: Fix park alerts title column length...\n";

try {
    $pdo = Database::getConnection();
    
    // Read the migration SQL file
    $migrationFile = __DIR__ . '/../migrations/021_fix_park_alerts_title_length.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    if ($sql === false) {
        throw new Exception("Failed to read migration file");
    }
    
    // Execute the migration
    echo "Executing: ALTER TABLE park_alerts MODIFY COLUMN title VARCHAR(500)...\n";
    $pdo->exec($sql);
    
    echo "✅ Migration 021 completed successfully!\n";
    echo "Increased park_alerts.title column length to 500 characters.\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
