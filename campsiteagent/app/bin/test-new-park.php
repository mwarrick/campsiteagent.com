#!/usr/bin/env php
<?php
/**
 * Test scraping for a newly added park to verify it works
 */

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Services\ReserveCaliforniaScraper;
use CampsiteAgent\Repositories\SettingsRepository;

echo "🧪 Testing New Park Scraping\n";
echo str_repeat("=", 60) . "\n\n";

// Test parks (PlaceId => Name)
$testParks = [
    '639' => 'Doheny State Beach',
    '709' => 'San Elijo State Beach',
    '720' => 'South Carlsbad State Beach',
];

echo "Select a park to test:\n";
$i = 1;
foreach ($testParks as $placeId => $name) {
    echo "  $i. $name (PlaceId: $placeId)\n";
    $i++;
}
echo "\nEnter number (1-" . count($testParks) . "): ";
$choice = trim(fgets(STDIN));

$selectedParks = array_values($testParks);
$selectedIds = array_keys($testParks);

if (!isset($selectedParks[$choice - 1])) {
    echo "❌ Invalid choice\n";
    exit(1);
}

$parkName = $selectedParks[$choice - 1];
$placeId = $selectedIds[$choice - 1];

echo "\n🔍 Testing: $parkName (PlaceId: $placeId)\n\n";

// Get user agent
$settings = new SettingsRepository();
$userAgent = $settings->get('rc_user_agent');

// Initialize scraper
$scraper = new ReserveCaliforniaScraper($userAgent);

// Step 1: Fetch facilities
echo "Step 1: Fetching facilities...\n";
try {
    $facilities = $scraper->fetchParkFacilities($placeId);
    if (empty($facilities)) {
        echo "❌ No facilities found. This park may not have online reservations.\n";
        exit(1);
    }
    
    echo "✅ Found " . count($facilities) . " facilities:\n";
    foreach ($facilities as $facility) {
        echo "   - [{$facility['facility_id']}] {$facility['name']}\n";
    }
    echo "\n";
    
    // Step 2: Test fetching availability for first facility
    $testFacility = $facilities[0];
    echo "Step 2: Testing availability fetch for '{$testFacility['name']}'...\n";
    
    $today = new DateTime();
    $startDate = $today->format('Y-m-d');
    
    $grid = $scraper->fetchFacilityAvailability(
        $placeId,
        $testFacility['facility_id'],
        $startDate,
        30  // 30 days
    );
    
    if (!$grid || !isset($grid['Facility']['Units'])) {
        echo "⚠️  No availability data returned (park might be closed or fully booked)\n\n";
    } else {
        $units = $grid['Facility']['Units'];
        echo "✅ Found " . count($units) . " sites/units\n";
        
        // Count available sites
        $availableCount = 0;
        foreach ($units as $unit) {
            if (isset($unit['IsAda'])) {
                $availableCount++;
            }
        }
        echo "   Sites with data: $availableCount\n\n";
    }
    
    // Step 3: Show activation SQL
    echo "Step 3: Ready to activate this park?\n";
    echo str_repeat("-", 60) . "\n";
    echo "To activate and enable scraping, run:\n\n";
    echo "mysql -u campsitechecker -p campsitechecker -e \"UPDATE parks SET active = 1 WHERE park_number = '$placeId';\"\n\n";
    echo "Then it will appear in the dashboard dropdown!\n\n";
    
    echo "✅ Test complete! This park looks good for multi-park support.\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nThis park may not support online reservations through ReserveCalifornia.\n";
    exit(1);
}

