<?php
/**
 * Migration 020: Create park alerts table
 * Run this script to create the park_alerts table for storing park alerts and restrictions
 */

require_once __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Infrastructure\Database;

echo "Starting Migration 020: Create park alerts table...\n";

try {
    $pdo = Database::getConnection();
    
    // Read the migration SQL file
    $migrationFile = __DIR__ . '/../migrations/020_create_park_alerts.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    if ($sql === false) {
        throw new Exception("Failed to read migration file");
    }
    
    // Execute the migration
    echo "Executing: CREATE TABLE park_alerts...\n";
    $pdo->exec($sql);
    
    echo "✅ Migration 020 completed successfully!\n";
    echo "Created park_alerts table for storing park alerts and restrictions.\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
