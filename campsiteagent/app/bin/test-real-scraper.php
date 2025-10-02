#!/usr/bin/env php
<?php

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Services\ReserveCaliforniaScraper;
use CampsiteAgent\Repositories\SettingsRepository;

echo "=== TESTING REAL SCRAPER ===\n\n";

// Get user agent
$ua = null;
try {
    $settings = new SettingsRepository();
    $ua = $settings->get('rc_user_agent');
} catch (\Throwable $e) {}

$scraper = new ReserveCaliforniaScraper($ua);

// Test 1: Fetch facilities
echo "1. Fetching facilities for San Onofre (712)...\n";
$facilities = $scraper->fetchParkFacilities('712');
echo "Found " . count($facilities) . " facilities:\n";
foreach ($facilities as $fac) {
    echo "  - [{$fac['facility_id']}] {$fac['name']}\n";
}

// Test 2: Fetch December availability
echo "\n2. Fetching December 2025 availability...\n";
$sites = $scraper->fetchMonthlyAvailability('712', '2025-12');
echo "Found " . count($sites) . " total sites across all facilities\n\n";

// Count available sites
$totalAvailable = 0;
$facilityCounts = [];

foreach ($sites as $site) {
    $facilityId = $site['facility_id'];
    if (!isset($facilityCounts[$facilityId])) {
        $facilityCounts[$facilityId] = [
            'name' => $site['facility_name'],
            'total' => 0,
            'available' => 0
        ];
    }
    
    $facilityCounts[$facilityId]['total']++;
    
    // Check if site has any available dates
    $hasAvailability = false;
    foreach ($site['dates'] as $date => $isAvailable) {
        if ($isAvailable) {
            $hasAvailability = true;
            break;
        }
    }
    
    if ($hasAvailability) {
        $facilityCounts[$facilityId]['available']++;
        $totalAvailable++;
    }
}

echo "Availability by facility:\n";
foreach ($facilityCounts as $facId => $counts) {
    echo "  [{$facId}] {$counts['name']}: {$counts['available']}/{$counts['total']} sites have availability\n";
}

echo "\n✅ Scraper working! Total sites with availability: $totalAvailable\n";



