<?php
/**
 * Diagnostic script to check for orphaned records in the database
 * This will help identify why we're seeing "Unknown Facility" on the dashboard
 */

require_once __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Infrastructure\Database;

try {
    $pdo = Database::getConnection();
    
    echo "🔍 Diagnosing orphaned records in database...\n\n";
    
    // 1. Check for sites with facility_id that don't exist in facilities table
    echo "1. Sites with invalid facility_id (orphaned sites):\n";
    $query = "
        SELECT s.id, s.site_number, s.facility_id, s.park_id, p.name as park_name
        FROM sites s
        LEFT JOIN facilities f ON s.facility_id = f.id
        LEFT JOIN parks p ON s.park_id = p.id
        WHERE s.facility_id IS NOT NULL AND f.id IS NULL
        ORDER BY s.park_id, s.site_number
    ";
    
    $stmt = $pdo->query($query);
    $orphanedSites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($orphanedSites)) {
        echo "   ✅ No orphaned sites found\n";
    } else {
        echo "   ❌ Found " . count($orphanedSites) . " orphaned sites:\n";
        foreach ($orphanedSites as $site) {
            echo "   - Site {$site['site_number']} (ID: {$site['id']}) in {$site['park_name']} (Park ID: {$site['park_id']}) has facility_id {$site['facility_id']} which doesn't exist\n";
        }
    }
    
    echo "\n";
    
    // 2. Check for sites with NULL facility_id
    echo "2. Sites with NULL facility_id:\n";
    $query = "
        SELECT s.id, s.site_number, s.park_id, p.name as park_name
        FROM sites s
        LEFT JOIN parks p ON s.park_id = p.id
        WHERE s.facility_id IS NULL
        ORDER BY s.park_id, s.site_number
    ";
    
    $stmt = $pdo->query($query);
    $nullFacilitySites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($nullFacilitySites)) {
        echo "   ✅ No sites with NULL facility_id found\n";
    } else {
        echo "   ⚠️ Found " . count($nullFacilitySites) . " sites with NULL facility_id:\n";
        foreach ($nullFacilitySites as $site) {
            echo "   - Site {$site['site_number']} (ID: {$site['id']}) in {$site['park_name']} (Park ID: {$site['park_id']})\n";
        }
    }
    
    echo "\n";
    
    // 3. Check for facilities that exist but have no sites
    echo "3. Facilities with no associated sites:\n";
    $query = "
        SELECT f.id, f.name, f.park_id, p.name as park_name
        FROM facilities f
        LEFT JOIN parks p ON f.park_id = p.id
        LEFT JOIN sites s ON f.id = s.facility_id
        WHERE s.id IS NULL
        ORDER BY f.park_id, f.name
    ";
    
    $stmt = $pdo->query($query);
    $unusedFacilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($unusedFacilities)) {
        echo "   ✅ No unused facilities found\n";
    } else {
        echo "   ⚠️ Found " . count($unusedFacilities) . " unused facilities:\n";
        foreach ($unusedFacilities as $facility) {
            echo "   - Facility '{$facility['name']}' (ID: {$facility['id']}) in {$facility['park_name']} (Park ID: {$facility['park_id']})\n";
        }
    }
    
    echo "\n";
    
    // 4. Check for sites with invalid park_id
    echo "4. Sites with invalid park_id (orphaned from parks):\n";
    $query = "
        SELECT s.id, s.site_number, s.park_id, s.facility_id
        FROM sites s
        LEFT JOIN parks p ON s.park_id = p.id
        WHERE p.id IS NULL
        ORDER BY s.park_id, s.site_number
    ";
    
    $stmt = $pdo->query($query);
    $orphanedFromParks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($orphanedFromParks)) {
        echo "   ✅ No sites orphaned from parks found\n";
    } else {
        echo "   ❌ Found " . count($orphanedFromParks) . " sites orphaned from parks:\n";
        foreach ($orphanedFromParks as $site) {
            echo "   - Site {$site['site_number']} (ID: {$site['id']}) has park_id {$site['park_id']} which doesn't exist, facility_id: {$site['facility_id']}\n";
        }
    }
    
    echo "\n";
    
    // 5. Summary statistics
    echo "5. Summary statistics:\n";
    
    // Total sites
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sites");
    $totalSites = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Total sites: {$totalSites}\n";
    
    // Sites with valid facility_id
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sites WHERE facility_id IS NOT NULL");
    $sitesWithFacility = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Sites with facility_id: {$sitesWithFacility}\n";
    
    // Sites with NULL facility_id
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sites WHERE facility_id IS NULL");
    $sitesWithoutFacility = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Sites with NULL facility_id: {$sitesWithoutFacility}\n";
    
    // Total facilities
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM facilities");
    $totalFacilities = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Total facilities: {$totalFacilities}\n";
    
    // Total parks
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM parks");
    $totalParks = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "   - Total parks: {$totalParks}\n";
    
    echo "\n";
    
    // 6. Sample of recent site_availability records that might be causing the issue
    echo "6. Recent site_availability records (last 10):\n";
    $query = "
        SELECT sa.id, sa.site_id, s.site_number, s.facility_id, f.name as facility_name, 
               sa.available, sa.date, p.name as park_name
        FROM site_availability sa
        LEFT JOIN sites s ON sa.site_id = s.id
        LEFT JOIN facilities f ON s.facility_id = f.id
        LEFT JOIN parks p ON s.park_id = p.id
        ORDER BY sa.id DESC
        LIMIT 10
    ";
    
    $stmt = $pdo->query($query);
    $recentAvailability = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($recentAvailability as $record) {
        $facilityName = $record['facility_name'] ?: 'NULL';
        $facilityId = $record['facility_id'] ?: 'NULL';
        echo "   - Availability ID {$record['id']}: Site {$record['site_number']} (ID: {$record['site_id']}) in {$record['park_name']}, facility_id: {$facilityId}, facility_name: {$facilityName}\n";
    }
    
    echo "\n";
    
    // 7. Provide SQL queries for manual investigation
    echo "7. SQL queries for manual investigation:\n";
    echo "   -- Check for orphaned sites:\n";
    echo "   SELECT s.id, s.site_number, s.facility_id, s.park_id, p.name as park_name\n";
    echo "   FROM sites s\n";
    echo "   LEFT JOIN facilities f ON s.facility_id = f.id\n";
    echo "   LEFT JOIN parks p ON s.park_id = p.id\n";
    echo "   WHERE s.facility_id IS NOT NULL AND f.id IS NULL;\n\n";
    
    echo "   -- Check for sites with NULL facility_id:\n";
    echo "   SELECT s.id, s.site_number, s.park_id, p.name as park_name\n";
    echo "   FROM sites s\n";
    echo "   LEFT JOIN parks p ON s.park_id = p.id\n";
    echo "   WHERE s.facility_id IS NULL;\n\n";
    
    echo "   -- Check recent site_availability with facility info:\n";
    echo "   SELECT sa.id, sa.site_id, s.site_number, s.facility_id, f.name as facility_name, \n";
    echo "          sa.available, sa.date, p.name as park_name\n";
    echo "   FROM site_availability sa\n";
    echo "   LEFT JOIN sites s ON sa.site_id = s.id\n";
    echo "   LEFT JOIN facilities f ON s.facility_id = f.id\n";
    echo "   LEFT JOIN parks p ON s.park_id = p.id\n";
    echo "   ORDER BY sa.id DESC\n";
    echo "   LIMIT 20;\n\n";
    
    echo "✅ Diagnosis completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error during diagnosis: " . $e->getMessage() . "\n";
    exit(1);
}
