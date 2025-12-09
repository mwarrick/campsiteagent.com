#!/usr/bin/env php
<?php
/**
 * Sync facilities for all active parks
 * This script fetches and caches facility data for all active parks
 */

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Repositories\ParkRepository;
use CampsiteAgent\Repositories\FacilityRepository;
use CampsiteAgent\Services\ReserveCaliforniaScraper;
use CampsiteAgent\Repositories\SettingsRepository;

echo "🏕️  Facility Sync Tool\n";
echo str_repeat("=", 60) . "\n\n";

$parkRepo = new ParkRepository();
$facilityRepo = new FacilityRepository();

// Get user agent from settings
$ua = null;
try {
    $settings = new SettingsRepository();
    $ua = $settings->get('rc_user_agent');
} catch (\Throwable $e) {
    echo "⚠️  Warning: Could not get user agent from settings\n";
}

$scraper = new ReserveCaliforniaScraper($ua);

// Get all active parks
$parks = $parkRepo->findActiveParks();
echo "Found " . count($parks) . " active parks to sync\n\n";

$totalFacilities = 0;
$errors = [];

foreach ($parks as $park) {
    $parkNumber = $park['park_number'] ?? $park['external_id'];
    $parkName = $park['name'];
    
    echo "🔍 Syncing facilities for: {$parkName} (Park #{$parkNumber})\n";
    
    try {
        // Parse facility filter if present
        $facilityFilter = null;
        if (!empty($park['facility_filter'])) {
            $facilityFilter = json_decode($park['facility_filter'], true);
            if ($facilityFilter) {
                echo "   📋 Using facility filter: " . implode(', ', $facilityFilter) . "\n";
            }
        }
        
        // Fetch facilities from API
        $facilities = $scraper->fetchParkFacilities($parkNumber, $facilityFilter);
        
        if (empty($facilities)) {
            echo "   ⚠️  No facilities found for this park\n";
            continue;
        }
        
        echo "   📦 Found " . count($facilities) . " facilities:\n";
        
        // Save each facility
        foreach ($facilities as $facility) {
            $facilityId = $facility['facility_id'];
            $facilityName = $facility['name'];
            
            $dbId = $facilityRepo->upsertFacility(
                (int)$park['id'],
                $facilityName,
                $facilityId,
                null, // description
                $facilityId // external_facility_id (same as facility_id)
            );
            
            echo "      ✅ [{$facilityId}] {$facilityName} (DB ID: {$dbId})\n";
            $totalFacilities++;
        }
        
        echo "   ✅ Synced " . count($facilities) . " facilities for {$parkName}\n\n";
        
    } catch (\Throwable $e) {
        $error = "Error syncing {$parkName}: " . $e->getMessage();
        echo "   ❌ {$error}\n\n";
        $errors[] = $error;
    }
}

echo str_repeat("=", 60) . "\n";
echo "📊 Summary:\n";
echo "   • Parks processed: " . count($parks) . "\n";
echo "   • Total facilities synced: {$totalFacilities}\n";
echo "   • Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\n❌ Errors encountered:\n";
    foreach ($errors as $error) {
        echo "   • {$error}\n";
    }
}

echo "\n✅ Facility sync complete!\n";
echo "\n💡 Tip: Run this script periodically to keep facility data up to date.\n";
echo "   You can also run it manually when you notice 'Unknown facility' issues.\n";
