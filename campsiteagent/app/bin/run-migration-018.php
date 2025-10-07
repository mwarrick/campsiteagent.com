<?php

require_once __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Infrastructure\Database;

try {
    $pdo = Database::getConnection();
    
    $sql = "ALTER TABLE login_tokens MODIFY COLUMN type ENUM('verify','login','disable_alerts') NOT NULL";
    $pdo->exec($sql);
    
    echo "✅ Migration 018 completed successfully!\n";
    echo "Added 'disable_alerts' to login_tokens type enum.\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
