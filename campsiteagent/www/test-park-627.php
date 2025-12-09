<?php
/**
 * Test park 627 specifically via web interface
 */

require __DIR__ . '/../app/bootstrap.php';

use CampsiteAgent\Services\ReserveCaliforniaScraper;

header('Content-Type: text/plain');

echo "=== Testing Park 627 (Chino Hills SP) ===\n\n";

try {
    $scraper = new ReserveCaliforniaScraper();
    echo "Scraper created\n";
    
    $facilities = $scraper->fetchParkFacilities('627');
    
    echo "Result: Found " . count($facilities) . " facility(ies)\n\n";
    
    if (empty($facilities)) {
        echo "❌ NO FACILITIES FOUND!\n";
        echo "\nCheck error logs for details.\n";
    } else {
        echo "✅ Facilities found:\n";
        foreach ($facilities as $f) {
            echo "  - {$f['name']} (ID: {$f['facility_id']})\n";
        }
    }
} catch (\Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

