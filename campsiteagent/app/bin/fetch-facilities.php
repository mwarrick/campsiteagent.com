#!/usr/bin/env php
<?php

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Services\ReserveCaliforniaScraper;
use CampsiteAgent\Repositories\ParkRepository;
use CampsiteAgent\Repositories\FacilityRepository;
use CampsiteAgent\Repositories\SettingsRepository;

if ($argc < 2) {
    echo "Usage: php fetch-facilities.php <park_id>\n";
    echo "Example: php fetch-facilities.php 1\n";
    exit(1);
}

$parkId = (int)$argv[1];

// Get park info
$parkRepo = new ParkRepository();
$parks = $parkRepo->listAll();
$park = null;
foreach ($parks as $p) {
    if ((int)$p['id'] === $parkId) {
        $park = $p;
        break;
    }
}

if (!$park) {
    echo "Error: Park ID {$parkId} not found\n";
    exit(1);
}

$parkNumber = $park['park_number'] ?? $park['external_id'];
echo "Fetching facilities for: {$park['name']} (park_number: {$parkNumber})\n\n";

// Get user agent from settings
$ua = null;
try {
    $settings = new SettingsRepository();
    $ua = $settings->get('rc_user_agent');
} catch (\Throwable $e) {
    // Ignore
}

// Fetch facilities
$scraper = new ReserveCaliforniaScraper($ua);
$facilities = $scraper->fetchParkFacilities($parkNumber);

if (empty($facilities)) {
    echo "No facilities found.\n";
    echo "The page might be using JavaScript to load data dynamically.\n";
    exit(1);
}

echo "Found " . count($facilities) . " facilities:\n\n";

$facilityRepo = new FacilityRepository();
foreach ($facilities as $facility) {
    echo "  - [{$facility['facility_id']}] {$facility['name']}\n";
    
    // Save to database
    $facilityRepo->upsertFacility(
        $parkId,
        $facility['name'],
        $facility['facility_id']
    );
}

echo "\n✅ Facilities saved to database!\n";



