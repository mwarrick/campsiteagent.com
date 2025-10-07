<?php
/**
 * Simple database check to understand the current state
 */

require_once __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Infrastructure\Database;

try {
    $pdo = Database::getConnection();
    
    echo "🔍 Simple Database Check...\n\n";
    
    // Check what tables exist
    echo "1. Available tables:\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "   - {$table}\n";
    }
    
    echo "\n";
    
    // Check parks count
    echo "2. Parks count:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM parks");
    $parksCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Total parks: {$parksCount}\n";
    
    // Check sites count
    echo "3. Sites count:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sites");
    $sitesCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Total sites: {$sitesCount}\n";
    
    // Check facilities count
    echo "4. Facilities count:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM facilities");
    $facilitiesCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Total facilities: {$facilitiesCount}\n";
    
    // Check site_availability count
    echo "5. Site availability count:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM site_availability");
    $availabilityCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Total availability records: {$availabilityCount}\n";
    
    echo "\n";
    
    // Check site_availability table structure
    echo "6. Site availability table structure:\n";
    $stmt = $pdo->query("DESCRIBE site_availability");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "   - {$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\n";
    
    // Check sites table structure
    echo "7. Sites table structure:\n";
    $stmt = $pdo->query("DESCRIBE sites");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "   - {$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\n";
    
    // Check facilities table structure
    echo "8. Facilities table structure:\n";
    $stmt = $pdo->query("DESCRIBE facilities");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "   - {$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\n";
    
    // Sample some parks
    echo "9. Sample parks:\n";
    $stmt = $pdo->query("SELECT id, name FROM parks LIMIT 5");
    $parks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($parks as $park) {
        echo "   - Park {$park['id']}: {$park['name']}\n";
    }
    
    echo "\n";
    
    // Sample some sites if they exist
    if ($sitesCount > 0) {
        echo "10. Sample sites:\n";
        $stmt = $pdo->query("SELECT id, site_number, facility_id, park_id FROM sites LIMIT 5");
        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sites as $site) {
            $facilityId = $site['facility_id'] ?: 'NULL';
            echo "   - Site {$site['id']}: {$site['site_number']} (Park: {$site['park_id']}, Facility: {$facilityId})\n";
        }
    } else {
        echo "10. No sites found in database\n";
    }
    
    echo "\n";
    
    // Sample some facilities if they exist
    if ($facilitiesCount > 0) {
        echo "11. Sample facilities:\n";
        $stmt = $pdo->query("SELECT id, name, park_id FROM facilities LIMIT 5");
        $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($facilities as $facility) {
            echo "   - Facility {$facility['id']}: {$facility['name']} (Park: {$facility['park_id']})\n";
        }
    } else {
        echo "11. No facilities found in database\n";
    }
    
    echo "\n";
    
    // Sample some availability records if they exist
    if ($availabilityCount > 0) {
        echo "12. Sample availability records:\n";
        $stmt = $pdo->query("SELECT * FROM site_availability LIMIT 5");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($records as $record) {
            echo "   - Availability record: " . json_encode($record) . "\n";
        }
    } else {
        echo "12. No availability records found in database\n";
    }
    
    echo "\n✅ Database check completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error during database check: " . $e->getMessage() . "\n";
    exit(1);
}
