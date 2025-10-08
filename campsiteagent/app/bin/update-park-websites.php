#!/usr/bin/env php
<?php
/**
 * Update park website URLs with known mappings
 * 
 * Since ReserveCalifornia uses JavaScript to load park data, we'll use
 * a curated list of known park websites instead of scraping.
 * 
 * Usage:
 *  php update-park-websites.php [--dry-run] [--verbose]
 */

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Infrastructure\Database;

$options = getopt('', [
    'dry-run',
    'verbose',
    'help'
]);

if (isset($options['help'])) {
    echo "Update park website URLs with known mappings\n";
    echo "Usage: php update-park-websites.php [--dry-run] [--verbose]\n";
    echo "\nOptions:\n";
    echo "  --dry-run      Show what would be updated without making changes\n";
    echo "  --verbose      Print detailed output\n";
    exit(0);
}

$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);

$log = function(string $msg) use ($verbose) {
    if ($verbose) echo $msg . "\n";
};

// Known park website mappings
$parkWebsites = [
    // Crystal Cove State Park
    'Crystal Cove SP' => 'https://www.parks.ca.gov/?page_id=644',
    'Crystal Cove State Park' => 'https://www.parks.ca.gov/?page_id=644',
    
    // Anza-Borrego Desert State Park
    'Anza-Borrego Desert SP' => 'https://www.parks.ca.gov/?page_id=638',
    'Anza-Borrego Desert State Park' => 'https://www.parks.ca.gov/?page_id=638',
    
    // Chino Hills State Park
    'Chino Hills SP' => 'https://www.parks.ca.gov/?page_id=648',
    'Chino Hills State Park' => 'https://www.parks.ca.gov/?page_id=648',
    
    // San Onofre State Beach
    'San Onofre State Beach' => 'https://www.parks.ca.gov/?page_id=645',
    'San Onofre SB' => 'https://www.parks.ca.gov/?page_id=645',
    
    // Doheny State Beach
    'Doheny State Beach' => 'https://www.parks.ca.gov/?page_id=646',
    'Doheny SB' => 'https://www.parks.ca.gov/?page_id=646',
    
    // Huntington State Beach
    'Huntington State Beach' => 'https://www.parks.ca.gov/?page_id=647',
    'Huntington SB' => 'https://www.parks.ca.gov/?page_id=647',
    
    // Bolsa Chica State Beach
    'Bolsa Chica State Beach' => 'https://www.parks.ca.gov/?page_id=642',
    'Bolsa Chica SB' => 'https://www.parks.ca.gov/?page_id=642',
    
    // Torrey Pines State Natural Reserve
    'Torrey Pines State Natural Reserve' => 'https://www.parks.ca.gov/?page_id=659',
    'Torrey Pines SNR' => 'https://www.parks.ca.gov/?page_id=659',
    
    // Point Mugu State Park
    'Point Mugu State Park' => 'https://www.parks.ca.gov/?page_id=630',
    'Point Mugu SP' => 'https://www.parks.ca.gov/?page_id=630',
    
    // Malibu Creek State Park
    'Malibu Creek State Park' => 'https://www.parks.ca.gov/?page_id=622',
    'Malibu Creek SP' => 'https://www.parks.ca.gov/?page_id=622',
    
    // Leo Carrillo State Park
    'Leo Carrillo State Park' => 'https://www.parks.ca.gov/?page_id=616',
    'Leo Carrillo SP' => 'https://www.parks.ca.gov/?page_id=616',
    
    // Point Dume State Beach
    'Point Dume State Beach' => 'https://www.parks.ca.gov/?page_id=631',
    'Point Dume SB' => 'https://www.parks.ca.gov/?page_id=631',
    
    // Topanga State Park
    'Topanga State Park' => 'https://www.parks.ca.gov/?page_id=660',
    'Topanga SP' => 'https://www.parks.ca.gov/?page_id=660',
    
    // Will Rogers State Historic Park
    'Will Rogers State Historic Park' => 'https://www.parks.ca.gov/?page_id=626',
    'Will Rogers SHP' => 'https://www.parks.ca.gov/?page_id=626',
    
    // Griffith Park
    'Griffith Park' => 'https://www.laparks.org/griffithpark',
    
    // Add more parks as needed...
];

try {
    $pdo = Database::getConnection();
    
    // Get all active parks
    $stmt = $pdo->query('SELECT id, name FROM parks WHERE active = 1 ORDER BY name');
    $parks = $stmt->fetchAll();
    
    if (empty($parks)) {
        echo "No active parks found.\n";
        exit(0);
    }
    
    echo "Found " . count($parks) . " active parks" . ($dryRun ? " [DRY-RUN]" : "") . "\n\n";
    
    $updated = 0;
    $notFound = 0;
    
    foreach ($parks as $park) {
        $parkId = (int)$park['id'];
        $parkName = $park['name'];
        
        $log("Processing: {$parkName}");
        
        // Check if we have a website mapping for this park
        $websiteUrl = null;
        
        // Try exact match first
        if (isset($parkWebsites[$parkName])) {
            $websiteUrl = $parkWebsites[$parkName];
        } else {
            // Try partial matches
            foreach ($parkWebsites as $mappedName => $url) {
                if (stripos($parkName, $mappedName) !== false || stripos($mappedName, $parkName) !== false) {
                    $websiteUrl = $url;
                    break;
                }
            }
        }
        
        if ($websiteUrl) {
            $log("  ✅ Found website: {$websiteUrl}");
            
            if (!$dryRun) {
                // Update the database
                $updateStmt = $pdo->prepare('UPDATE parks SET website_url = :url WHERE id = :id');
                $updateStmt->execute([
                    ':url' => $websiteUrl,
                    ':id' => $parkId
                ]);
                
                if ($updateStmt->rowCount() > 0) {
                    $updated++;
                    $log("  💾 Updated database");
                }
            } else {
                $updated++;
                $log("  💾 Would update database");
            }
        } else {
            $log("  ⚠️  No website mapping found");
            $notFound++;
        }
    }
    
    echo "\n📊 SUMMARY:\n";
    echo "  Parks processed: " . count($parks) . "\n";
    echo "  Websites found: {$updated}\n";
    echo "  Not found: {$notFound}\n";
    
    if ($dryRun) {
        echo "\n💡 Run without --dry-run to update the database\n";
    }
    
    exit(0);
    
} catch (\Throwable $e) {
    fwrite(STDERR, "Fatal error: " . $e->getMessage() . "\n");
    fwrite(STDERR, "File: " . $e->getFile() . ":" . $e->getLine() . "\n");
    exit(1);
}
