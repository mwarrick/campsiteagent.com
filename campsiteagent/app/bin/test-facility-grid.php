#!/usr/bin/env php
<?php
/**
 * Test getting availability grid for a specific facility
 */

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Infrastructure\HttpClient;

$http = new HttpClient();
$baseUrl = 'https://calirdr.usedirect.com/rdr/rdr/';

// San Onofre facilities
$placeId = 712;
$facilityId = 674; // Bluff Camp (sites 1-23)
$startDate = '2025-12-01';
$nights = 2;

echo "Testing grid for Bluff Camp (Facility $facilityId)\n\n";

// Based on the JavaScript code we saw earlier:
// SearchUrl + "fd/availability/getbyunit/" + unit_id + "/startdate/" + arrival_date + "/nights/" + nights + "/false"
// But we need the facility-level grid first

$gridPatterns = [
    // POST endpoint (405 suggests it might need POST)
    ['method' => 'POST', 'url' => "search/grid", 'params' => ['PlaceId' => $placeId, 'FacilityId' => $facilityId, 'StartDate' => $startDate, 'Nights' => $nights]],
    
    // Path-based patterns
    ['method' => 'GET', 'url' => "search/grid/$placeId/$facilityId/$startDate/$nights"],
    ['method' => 'GET', 'url' => "fd/search/grid/$placeId/$facilityId/$startDate/$nights"],
    ['method' => 'GET', 'url' => "search/facility/$facilityId/grid?StartDate=$startDate&Nights=$nights"],
    ['method' => 'GET', 'url' => "fd/facility/$facilityId/grid?StartDate=$startDate&Nights=$nights"],
    ['method' => 'GET', 'url' => "facility/$facilityId/availability?StartDate=$startDate&Nights=$nights"],
    ['method' => 'GET', 'url' => "fd/facility/$facilityId/availability?StartDate=$startDate&Nights=$nights"],
];

foreach ($gridPatterns as $pattern) {
    $url = $baseUrl . $pattern['url'];
    echo "Trying {$pattern['method']}: $url\n";
    
    try {
        if ($pattern['method'] === 'POST' && isset($pattern['params'])) {
            $response = $http->post($url, $pattern['params']);
            $status = $response[0];
            $body = $response[1];
        } else {
            [$status, $body] = $http->get($url);
        }
        
        echo "Status: $status\n";
        
        if ($status >= 200 && $status < 300) {
            echo "✅ SUCCESS! Response length: " . strlen($body) . " bytes\n";
            $data = json_decode($body, true);
            
            if ($data) {
                echo "Data keys: " . implode(', ', array_keys($data)) . "\n";
                
                // Check for units/sites
                if (isset($data['Facility']['Units'])) {
                    $units = $data['Facility']['Units'];
                    echo "\n✅ Found " . count($units) . " units/sites!\n";
                    
                    // Show first 3 sites
                    $count = 0;
                    foreach ($units as $unitId => $unit) {
                        if ($count++ >= 3) break;
                        echo "\nSite #{$unit['UnitNum']}:\n";
                        echo "  - Unit ID: $unitId\n";
                        echo "  - Name: {$unit['Name']}\n";
                        echo "  - Type: {$unit['UnitCategoryName']}\n";
                        if (isset($unit['Slices'])) {
                            echo "  - Availability slices: " . count($unit['Slices']) . "\n";
                        }
                    }
                }
                
                // Save full response
                file_put_contents('/tmp/facility_grid_response.json', json_encode($data, JSON_PRETTY_PRINT));
                echo "\n✅ Full response saved to /tmp/facility_grid_response.json\n";
            }
            break;
        }
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "\nDone!\n";



