#!/usr/bin/env php
<?php

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Services\MetadataSyncService;

echo "=== Syncing Park Metadata ===\n";
echo "This will update facilities and site metadata for all active parks.\n\n";

try {
    $sync = new MetadataSyncService();
    $results = $sync->syncAllActiveParks();
    
    foreach ($results as $result) {
        if ($result['success']) {
            echo "✓ {$result['park']}: {$result['facilities']} facilities, {$result['sites']} sites\n";
        } else {
            echo "✗ {$result['park']}: ERROR - {$result['error']}\n";
        }
    }
    
    echo "\nMetadata sync complete!\n";
} catch (Throwable $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}



