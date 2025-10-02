#!/usr/bin/env php
<?php
/**
 * Test grid and facility endpoints with different patterns
 */

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Infrastructure\HttpClient;
use CampsiteAgent\Repositories\SettingsRepository;

$ua = null;
try {
    $settings = new SettingsRepository();
    $ua = $settings->get('rc_user_agent');
} catch (\Throwable $e) {}

$http = new HttpClient($ua);
$baseUrl = 'https://calirdr.usedirect.com/rdr/rdr/';

// San Onofre State Beach
$placeId = 712;
$startDate = '2025-12-01'; // December when we know sites are available
$nights = 2;

echo "Testing Grid and Facility APIs for San Onofre (PlaceId: $placeId)\n\n";

// Test different grid endpoint patterns
echo "==> Test 1: Grid/availability endpoints\n";
$gridEndpoints = [
    "search/grid",
    "fd/search/grid",
    "search/availability",
    "fd/availability",
];

foreach ($gridEndpoints as $endpoint) {
    $url = $baseUrl . $endpoint . "?PlaceId=$placeId&StartDate=$startDate&Nights=$nights";
    echo "Trying: $url\n";
    
    try {
        [$status, $body] = $http->get($url);
        echo "Status: $status\n";
        
        if ($status >= 200 && $status < 300) {
            echo "✅ SUCCESS! Response length: " . strlen($body) . " bytes\n";
            $data = json_decode($body, true);
            if ($data) {
                echo "Data keys: " . implode(', ', array_keys($data)) . "\n";
                
                // Save sample to file for inspection
                file_put_contents('/tmp/grid_response.json', json_encode($data, JSON_PRETTY_PRINT));
                echo "Full response saved to /tmp/grid_response.json\n";
            }
            break;
        }
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// Test facilities endpoint patterns
echo "\n==> Test 2: Facilities for place $placeId\n";
$facilityEndpoints = [
    "fd/facilities?PlaceId=$placeId",
    "facilities?PlaceId=$placeId",
    "search/facilities?PlaceId=$placeId",
    "fd/search/facilities?PlaceId=$placeId",
];

foreach ($facilityEndpoints as $endpoint) {
    $url = $baseUrl . $endpoint;
    echo "Trying: $url\n";
    
    try {
        [$status, $body] = $http->get($url);
        echo "Status: $status\n";
        
        if ($status >= 200 && $status < 300) {
            echo "✅ SUCCESS! Response:\n";
            $data = json_decode($body, true);
            if ($data) {
                if (is_array($data)) {
                    echo "Found " . count($data) . " facilities:\n";
                    foreach ($data as $facility) {
                        echo "  - [{$facility['FacilityId']}] {$facility['Name']}\n";
                    }
                }
                echo "\n";
            }
            break;
        }
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// Maybe they return facilities as part of the place data?
echo "\n==> Test 3: Check if place endpoint includes facilities\n";
$url = $baseUrl . "fd/places/$placeId";
try {
    [$status, $body] = $http->get($url);
    if ($status >= 200 && $status < 300) {
        $data = json_decode($body, true);
        if ($data) {
            echo "Place data keys: " . implode(', ', array_keys($data)) . "\n";
            if (isset($data['Facilities'])) {
                echo "✅ Found Facilities in place data!\n";
                echo json_encode($data['Facilities'], JSON_PRETTY_PRINT) . "\n";
            } else {
                echo "❌ No 'Facilities' key in place data\n";
            }
        }
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Try the mapdata endpoint (seen in JS)
echo "\n==> Test 4: Map data endpoint\n";
$mapEndpoints = [
    "mapdata/$placeId",
    "fd/mapdata/$placeId",
    "search/mapdata?PlaceId=$placeId",
];

foreach ($mapEndpoints as $endpoint) {
    $url = $baseUrl . $endpoint;
    echo "Trying: $url\n";
    
    try {
        [$status, $body] = $http->get($url);
        echo "Status: $status\n";
        
        if ($status >= 200 && $status < 300) {
            echo "✅ SUCCESS! Response length: " . strlen($body) . " bytes\n";
            $data = json_decode($body, true);
            if ($data) {
                echo "Data structure: " . json_encode(array_keys($data), JSON_PRETTY_PRINT) . "\n";
                file_put_contents('/tmp/mapdata_response.json', json_encode($data, JSON_PRETTY_PRINT));
                echo "Full response saved to /tmp/mapdata_response.json\n";
            }
            break;
        }
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "\n==> Done!\n";



