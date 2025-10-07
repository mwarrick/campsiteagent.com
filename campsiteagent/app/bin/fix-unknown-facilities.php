<?php
/**
 * Fix "Unknown Facility" issue by linking sites to facilities
 * 
 * This script addresses the problem where sites have NULL facility_id,
 * causing the dashboard to display "Unknown Facility" instead of actual facility names.
 * 
 * Usage: php fix-unknown-facilities.php [--dry-run] [--verbose]
 * 
 * Options:
 *   --dry-run    Show what would be changed without making changes
 *   --verbose    Show detailed output
 */

require_once __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Infrastructure\Database;

// Parse command line arguments
$options = getopt('', ['dry-run', 'verbose']);
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);

function logMessage($message, $level = 'INFO') {
    global $verbose;
    $timestamp = date('Y-m-d H:i:s');
    $prefix = match($level) {
        'ERROR' => '❌',
        'WARNING' => '⚠️',
        'SUCCESS' => '✅',
        default => 'ℹ️'
    };
    
    if ($verbose || $level !== 'INFO') {
        echo "[{$timestamp}] {$prefix} {$message}\n";
    }
}

try {
    $pdo = Database::getConnection();
    
    logMessage("Starting Unknown Facility fix...");
    
    if ($dryRun) {
        logMessage("DRY RUN MODE - No changes will be made", 'WARNING');
    }
    
    // Step 1: Check current state
    logMessage("Checking current state...");
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sites WHERE facility_id IS NULL");
    $nullCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($nullCount == 0) {
        logMessage("No sites with NULL facility_id found. Nothing to fix!", 'SUCCESS');
        exit(0);
    }
    
    logMessage("Found {$nullCount} sites with NULL facility_id");
    
    // Step 2: Show affected parks
    $stmt = $pdo->query("
        SELECT p.name AS park_name, COUNT(*) AS null_sites
        FROM sites s
        JOIN parks p ON p.id = s.park_id
        WHERE s.facility_id IS NULL
        GROUP BY p.name
        ORDER BY null_sites DESC
    ");
    $affectedParks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Affected parks:");
    foreach ($affectedParks as $park) {
        logMessage("  - {$park['park_name']}: {$park['null_sites']} sites");
    }
    
    // Step 3: Preview the mapping
    logMessage("Previewing facility mappings...");
    
    $stmt = $pdo->query("
        SELECT p.name AS park_name,
               f.name AS facility_name,
               x.park_id,
               x.facility_id,
               x.n AS linked_site_count
        FROM (
          SELECT park_id, facility_id, COUNT(*) AS n,
                 ROW_NUMBER() OVER (PARTITION BY park_id ORDER BY COUNT(*) DESC) AS rn
          FROM sites
          WHERE facility_id IS NOT NULL
          GROUP BY park_id, facility_id
        ) x
        JOIN facilities f ON f.id = x.facility_id
        JOIN parks p ON p.id = x.park_id
        WHERE x.rn = 1
        ORDER BY p.name
    ");
    $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Facility mappings to be applied:");
    foreach ($mappings as $mapping) {
        logMessage("  - {$mapping['park_name']} → {$mapping['facility_name']} ({$mapping['linked_site_count']} existing sites)");
    }
    
    // Step 4: Apply the fix
    if (!$dryRun) {
        logMessage("Applying facility mappings...");
        
        $stmt = $pdo->prepare("
            UPDATE sites s
            JOIN (
              SELECT park_id, facility_id
              FROM (
                SELECT park_id, facility_id, COUNT(*) AS n,
                       ROW_NUMBER() OVER (PARTITION BY park_id ORDER BY COUNT(*) DESC) AS rn
                FROM sites
                WHERE facility_id IS NOT NULL
                GROUP BY park_id, facility_id
              ) t
              WHERE t.rn = 1
            ) m ON m.park_id = s.park_id
            SET s.facility_id = m.facility_id
            WHERE s.facility_id IS NULL
        ");
        
        $stmt->execute();
        $affectedRows = $stmt->rowCount();
        
        logMessage("Updated {$affectedRows} sites", 'SUCCESS');
    } else {
        logMessage("DRY RUN: Would update {$nullCount} sites");
    }
    
    // Step 5: Verify the fix
    logMessage("Verifying fix...");
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sites WHERE facility_id IS NULL");
    $remainingNulls = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($remainingNulls == 0) {
        logMessage("All sites now have facility_id linked!", 'SUCCESS');
    } else {
        logMessage("Warning: {$remainingNulls} sites still have NULL facility_id", 'WARNING');
    }
    
    // Step 6: Check for remaining "Unknown Facility" issues
    logMessage("Checking for remaining 'Unknown Facility' issues...");
    
    $stmt = $pdo->query("
        SELECT p.name AS park_name, COUNT(*) AS unknown_rows
        FROM site_availability a
        JOIN sites s ON s.id = a.site_id
        LEFT JOIN facilities f ON f.id = s.facility_id
        JOIN parks p ON p.id = s.park_id
        WHERE a.is_available = 1
          AND a.date >= CURDATE()
          AND (f.name IS NULL OR f.name = '')
        GROUP BY p.name
        ORDER BY unknown_rows DESC
    ");
    $unknownIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($unknownIssues)) {
        logMessage("No remaining 'Unknown Facility' issues found!", 'SUCCESS');
    } else {
        logMessage("Remaining 'Unknown Facility' issues:");
        foreach ($unknownIssues as $issue) {
            logMessage("  - {$issue['park_name']}: {$issue['unknown_rows']} rows");
        }
    }
    
    // Step 7: Test dashboard query
    logMessage("Testing dashboard API query...");
    
    try {
        $stmt = $pdo->query("
            SELECT p.name AS park_name, s.site_number, f.name AS facility_name, a.date
            FROM parks p
            JOIN sites s ON s.park_id = p.id
            LEFT JOIN facilities f ON s.facility_id = f.id
            JOIN site_availability a ON a.site_id = s.id
            WHERE a.is_available = 1
              AND a.date >= CURDATE()
            ORDER BY p.name, a.date DESC, s.site_number
            LIMIT 5
        ");
        $sampleResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        logMessage("Sample dashboard results:");
        foreach ($sampleResults as $result) {
            $facilityName = $result['facility_name'] ?: 'NULL';
            logMessage("  - {$result['park_name']} - Site {$result['site_number']} - Facility: {$facilityName}");
        }
        
        logMessage("Dashboard API query working correctly!", 'SUCCESS');
        
    } catch (Exception $e) {
        logMessage("Dashboard API query error: " . $e->getMessage(), 'ERROR');
    }
    
    logMessage("Unknown Facility fix completed!", 'SUCCESS');
    
    if ($dryRun) {
        logMessage("This was a dry run. Run without --dry-run to apply changes.", 'WARNING');
    }
    
} catch (Exception $e) {
    logMessage("Error during fix: " . $e->getMessage(), 'ERROR');
    exit(1);
}
