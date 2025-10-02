#!/usr/bin/env php
<?php
/**
 * Explore the UseDirect API based on successful endpoint discovery
 */

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Infrastructure\HttpClient;
use CampsiteAgent\Repositories\SettingsRepository;

// Get user agent from settings
$ua = null;
try {
    $settings = new SettingsRepository();
    $ua = $settings->get('rc_user_agent');
} catch (\Throwable $e) {
    // Use default
}

$http = new HttpClient($ua);
$baseUrl = 'https://calirdr.usedirect.com/rdr/rdr/';

echo "Exploring UseDirect API...\n\n";

// We know facility 712 is "Southern End" at park/place 720
// Let's explore what else we can find

echo "==> Test 1: Get place/park info (PlaceId: 720)\n";
$placeEndpoints = [
    "fd/places/720",
    "fd/place/720",
    "places/720",
];

foreach ($placeEndpoints as $endpoint) {
    $url = $baseUrl . $endpoint;
    echo "Trying: $url\n";
    
    try {
        [$status, $body] = $http->get($url);
        if ($status >= 200 && $status < 300) {
            echo "✅ SUCCESS! Response:\n";
            $data = json_decode($body, true);
            if ($data) {
                echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
            }
            break;
        }
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "\n==> Test 2: Get all facilities for place 720\n";
$facilityListEndpoints = [
    "fd/places/720/facilities",
    "facilities/720",
    "fd/facilities/place/720",
];

foreach ($facilityListEndpoints as $endpoint) {
    $url = $baseUrl . $endpoint;
    echo "Trying: $url\n";
    
    try {
        [$status, $body] = $http->get($url);
        if ($status >= 200 && $status < 300) {
            echo "✅ SUCCESS! Response:\n";
            $data = json_decode($body, true);
            if ($data) {
                if (is_array($data) && count($data) > 0) {
                    echo "Found " . count($data) . " facilities:\n";
                    foreach ($data as $facility) {
                        if (isset($facility['Name'])) {
                            echo "  - [{$facility['FacilityId']}] {$facility['Name']}\n";
                        }
                    }
                    echo "\nFull data:\n";
                }
                echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
            }
            break;
        }
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "\n==> Test 3: Get grid/availability data for facility 712\n";
$today = date('Y-m-d');
$gridEndpoints = [
    "search/grid?PlaceId=720&FacilityId=712&StartDate=$today&Nights=2",
    "fd/search/grid?PlaceId=720&FacilityId=712&StartDate=$today&Nights=2",
    "grid/712?StartDate=$today&Nights=2",
    "fd/grid/712/$today/2",
];

foreach ($gridEndpoints as $endpoint) {
    $url = $baseUrl . $endpoint;
    echo "Trying: $url\n";
    
    try {
        [$status, $body] = $http->get($url);
        if ($status >= 200 && $status < 300) {
            echo "✅ SUCCESS! Response length: " . strlen($body) . " bytes\n";
            $data = json_decode($body, true);
            if ($data) {
                echo "Data structure:\n";
                echo json_encode(array_keys($data), JSON_PRETTY_PRINT) . "\n";
                
                // Show first unit if available
                if (isset($data['Facility']['Units'])) {
                    $units = $data['Facility']['Units'];
                    $firstUnit = reset($units);
                    echo "\nExample unit structure:\n";
                    echo json_encode($firstUnit, JSON_PRETTY_PRINT) . "\n";
                }
            }
            break;
        }
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "\n==> Test 4: Search for all places/parks\n";
$placeListEndpoints = [
    "fd/places",
    "places",
    "search/places",
];

foreach ($placeListEndpoints as $endpoint) {
    $url = $baseUrl . $endpoint;
    echo "Trying: $url\n";
    
    try {
        [$status, $body] = $http->get($url);
        if ($status >= 200 && $status < 300) {
            echo "✅ SUCCESS! Response length: " . strlen($body) . " bytes\n";
            $data = json_decode($body, true);
            if ($data && is_array($data)) {
                echo "Found " . count($data) . " places\n";
                // Find San Onofre
                foreach ($data as $place) {
                    if (isset($place['Name']) && stripos($place['Name'], 'onofre') !== false) {
                        echo "\nFound San Onofre:\n";
                        echo json_encode($place, JSON_PRETTY_PRINT) . "\n";
                    }
                }
            }
            break;
        }
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "\n==> Summary:\n";
echo "If we found working endpoints, we can now:\n";
echo "1. Fetch all facilities for a park\n";
echo "2. Get availability grid data\n";
echo "3. Parse site information\n";



