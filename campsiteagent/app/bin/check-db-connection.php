<?php
/**
 * Check database connection parameters and test connection
 */

require_once __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Infrastructure\Database;

echo "🔍 Database Connection Check...\n\n";

// Show environment variables
echo "1. Environment variables:\n";
echo "   - DB_HOST: " . (getenv('DB_HOST') ?: '127.0.0.1 (default)') . "\n";
echo "   - DB_PORT: " . (getenv('DB_PORT') ?: '3306 (default)') . "\n";
echo "   - DB_DATABASE: " . (getenv('DB_DATABASE') ?: 'campsitechecker (default)') . "\n";
echo "   - DB_USERNAME: " . (getenv('DB_USERNAME') ?: 'root (default)') . "\n";
echo "   - DB_PASSWORD: " . (getenv('DB_PASSWORD') ? '[SET]' : '[EMPTY - default]') . "\n";

echo "\n";

// Test connection
echo "2. Testing database connection...\n";
try {
    $pdo = Database::getConnection();
    echo "   ✅ Connection successful!\n";
    
    // Get database name
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $dbName = $stmt->fetch(PDO::FETCH_ASSOC)['db_name'];
    echo "   - Connected to database: {$dbName}\n";
    
    // Get user
    $stmt = $pdo->query("SELECT USER() as user");
    $user = $stmt->fetch(PDO::FETCH_ASSOC)['user'];
    echo "   - Connected as user: {$user}\n";
    
} catch (Exception $e) {
    echo "   ❌ Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// Check table counts again
echo "3. Table counts in connected database:\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM parks");
    $parksCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Parks: {$parksCount}\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sites");
    $sitesCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Sites: {$sitesCount}\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM facilities");
    $facilitiesCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Facilities: {$facilitiesCount}\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM site_availability");
    $availabilityCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Site availability: {$availabilityCount}\n";
    
} catch (Exception $e) {
    echo "   ❌ Error checking table counts: " . $e->getMessage() . "\n";
}

echo "\n";

// Test the actual API query
echo "4. Testing API query (what dashboard uses):\n";
try {
    $sql = 'SELECT p.id AS park_id, p.name AS park_name, p.park_number, s.id AS site_id, s.site_number, s.site_name, s.site_type, 
                   f.id AS facility_id, f.name AS facility_name, a.date, COALESCE(a.updated_at, a.created_at) AS found_at
            FROM parks p
            JOIN sites s ON s.park_id = p.id
            LEFT JOIN facilities f ON s.facility_id = f.id
            JOIN site_availability a ON a.site_id = s.id
            WHERE a.is_available = 1 AND a.date >= CURDATE()
            ORDER BY p.name, a.date ASC, s.site_number ASC
            LIMIT 10';
    
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   - API query returned " . count($rows) . " rows\n";
    
    if (count($rows) > 0) {
        echo "   - Sample results:\n";
        foreach (array_slice($rows, 0, 3) as $row) {
            $facilityName = $row['facility_name'] ?: 'NULL';
            echo "     * {$row['park_name']} - Site {$row['site_number']} - Facility: {$facilityName}\n";
        }
    } else {
        echo "   - No results returned (this explains 'Unknown Facility')\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Error testing API query: " . $e->getMessage() . "\n";
}

echo "\n✅ Database connection check completed!\n";
