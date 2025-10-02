#!/usr/bin/env php
<?php
/**
 * Test getting availability for December when sites should be available
 */

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Infrastructure\HttpClient;

$http = new HttpClient();
$baseUrl = 'https://calirdr.usedirect.com/rdr/rdr/';

// San Onofre - Bluff Camp
$placeId = 712;
$facilityId = 674; // Bluff Camp (sites 1-23)
$startDate = '2025-12-13'; // Saturday
$nights = 2; // Weekend stay

echo "Fetching December weekend availability for Bluff Camp\n";
echo "PlaceId: $placeId, FacilityId: $facilityId\n";
echo "Dates: $startDate for $nights nights\n\n";

$url = $baseUrl . "search/grid";
$params = [
    'PlaceId' => $placeId,
    'FacilityId' => $facilityId,
    'StartDate' => $startDate,
    'Nights' => $nights
];

try {
    [$status, $body] = $http->post($url, $params);
    
    if ($status >= 200 && $status < 300) {
        echo "✅ SUCCESS!\n\n";
        $data = json_decode($body, true);
        
        if ($data && isset($data['Facility']['Units'])) {
            $units = $data['Facility']['Units'];
            echo "Total sites: " . count($units) . "\n";
            
            $availableCount = 0;
            foreach ($units as $unitId => $unit) {
                if (isset($unit['Slices']) && count($unit['Slices']) > 0) {
                    $availableCount++;
                }
            }
            
            echo "Sites with availability data: $availableCount\n\n";
            
            // Show first site with availability
            foreach ($units as $unitId => $unit) {
                if (isset($unit['Slices']) && count($unit['Slices']) > 0) {
                    echo "=== Example Site with Availability ===\n";
                    echo "Unit ID: $unitId\n";
                    echo "Name: {$unit['Name']}\n";
                    echo "Short Name: {$unit['ShortName']}\n";
                    echo "Is ADA: " . ($unit['IsAda'] ? 'Yes' : 'No') . "\n";
                    echo "Unit Type ID: {$unit['UnitTypeId']}\n";
                    echo "Vehicle Length: {$unit['VehicleLength']}\n";
                    echo "\nAvailability Slices (" . count($unit['Slices']) . "):\n";
                    
                    foreach ($unit['Slices'] as $slice) {
                        echo json_encode($slice, JSON_PRETTY_PRINT) . "\n";
                    }
                    
                    // Save full unit data
                    file_put_contents('/tmp/sample_unit.json', json_encode($unit, JSON_PRETTY_PRINT));
                    echo "\n✅ Full unit data saved to /tmp/sample_unit.json\n";
                    break;
                }
            }
            
            // Save full response
            file_put_contents('/tmp/december_grid.json', json_encode($data, JSON_PRETTY_PRINT));
            echo "\n✅ Full grid saved to /tmp/december_grid.json\n";
            
        } else {
            echo "❌ No units found in response\n";
        }
    } else {
        echo "❌ HTTP Status: $status\n";
        echo "Response: $body\n";
    }
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\nDone!\n";



