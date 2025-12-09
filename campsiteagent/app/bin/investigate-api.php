#!/usr/bin/env php
<?php
/**
 * Comprehensive API Investigation Script
 * Tests all possible endpoint patterns and configurations
 */

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Infrastructure\HttpClient;
use CampsiteAgent\Repositories\SettingsRepository;

// Get user agent from settings
$ua = null;
try {
    $settings = new SettingsRepository();
    $ua = $settings->get('rc_user_agent');
    echo "Using User-Agent: " . ($ua ?: 'DEFAULT') . "\n\n";
} catch (\Throwable $e) {
    echo "No user agent in settings\n\n";
}

$baseUrl = 'https://calirdr.usedirect.com/rdr/rdr/';
$testParks = [
    ['name' => 'San Onofre', 'id' => '712'],
    ['name' => 'Chino Hills', 'id' => '627'],
];

echo "=== COMPREHENSIVE API INVESTIGATION ===\n\n";

// Test 1: Facilities endpoints
echo "TEST 1: Facilities Endpoints\n";
echo str_repeat("=", 60) . "\n";

$facilityEndpoints = [
    "fd/facilities?PlaceId={PLACE_ID}",
    "fd/places/{PLACE_ID}/facilities",
    "fd/place/{PLACE_ID}/facilities",
    "places/{PLACE_ID}/facilities",
    "place/{PLACE_ID}/facilities",
    "fd/facilities/{PLACE_ID}",
    "facilities/{PLACE_ID}",
];

foreach ($testParks as $park) {
    echo "\n--- Testing {$park['name']} (PlaceId: {$park['id']}) ---\n";
    
    foreach ($facilityEndpoints as $endpointPattern) {
        $endpoint = str_replace('{PLACE_ID}', $park['id'], $endpointPattern);
        $url = $baseUrl . $endpoint;
        
        echo "  Trying: {$endpoint}\n";
        
        // Test with different header configurations
        $headerConfigs = [
            'default' => [],
            'with_referer' => ['Referer: https://www.reservecalifornia.com/'],
            'with_origin' => ['Origin: https://www.reservecalifornia.com'],
            'with_both' => [
                'Referer: https://www.reservecalifornia.com/',
                'Origin: https://www.reservecalifornia.com'
            ],
        ];
        
        foreach ($headerConfigs as $configName => $headers) {
            try {
                $http = new HttpClient($ua);
                [$status, $body] = $http->get($url, $headers);
                
                $isJson = substr(trim($body), 0, 1) !== '<';
                $isHtml = !$isJson && substr(trim($body), 0, 9) === '<!DOCTYPE';
                
                if ($isJson) {
                    $data = json_decode($body, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                        echo "    ✅ {$configName}: SUCCESS! Found " . count($data) . " items\n";
                        if (count($data) > 0) {
                            echo "      First item keys: " . implode(', ', array_keys($data[0])) . "\n";
                            // Save successful response
                            file_put_contents(
                                "/tmp/api_success_{$park['id']}_{$configName}.json",
                                json_encode($data, JSON_PRETTY_PRINT)
                            );
                            echo "      Saved to: /tmp/api_success_{$park['id']}_{$configName}.json\n";
                        }
                        break 2; // Found working endpoint, move to next park
                    }
                } else if ($isHtml) {
                    echo "    ⚠️  {$configName}: HTML response (blocked?)\n";
                } else {
                    echo "    ❌ {$configName}: Status {$status}, Unknown format\n";
                }
            } catch (\Throwable $e) {
                echo "    ❌ {$configName}: Error - " . $e->getMessage() . "\n";
            }
        }
    }
}

// Test 2: Grid/Availability endpoints
echo "\n\nTEST 2: Grid/Availability Endpoints\n";
echo str_repeat("=", 60) . "\n";

$testFacilityId = '674'; // Known facility from San Onofre
$testDate = date('Y-m-d', strtotime('+30 days'));
$testNights = 1;

$gridEndpoints = [
    "search/grid",  // POST
    "fd/search/grid",  // POST
    "search/availability",  // POST
    "fd/availability",  // POST
];

foreach ($testParks as $park) {
    echo "\n--- Testing {$park['name']} Grid (Facility: {$testFacilityId}, Date: {$testDate}) ---\n";
    
    foreach ($gridEndpoints as $endpoint) {
        $url = $baseUrl . $endpoint;
        $params = [
            'PlaceId' => (int)$park['id'],
            'FacilityId' => (int)$testFacilityId,
            'StartDate' => $testDate,
            'Nights' => $testNights,
            'InSeasonOnly' => false,
            'WebOnly' => true,
        ];
        
        echo "  Trying POST: {$endpoint}\n";
        
        try {
            $http = new HttpClient($ua);
            [$status, $body] = $http->post($url, $params);
            
            $isJson = substr(trim($body), 0, 1) !== '<';
            $isHtml = !$isJson && substr(trim($body), 0, 9) === '<!DOCTYPE';
            
            if ($isJson) {
                $data = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    echo "    ✅ SUCCESS! Response structure:\n";
                    echo "      Top-level keys: " . implode(', ', array_keys($data)) . "\n";
                    if (isset($data['Facility']['Units'])) {
                        $units = $data['Facility']['Units'];
                        echo "      Found " . count($units) . " units\n";
                        file_put_contents(
                            "/tmp/grid_success_{$park['id']}.json",
                            json_encode($data, JSON_PRETTY_PRINT)
                        );
                        echo "      Saved to: /tmp/grid_success_{$park['id']}.json\n";
                        break; // Found working endpoint
                    }
                }
            } else if ($isHtml) {
                echo "    ⚠️  HTML response (blocked?)\n";
            } else {
                echo "    ❌ Status {$status}, Unknown format\n";
            }
        } catch (\Throwable $e) {
            echo "    ❌ Error: " . $e->getMessage() . "\n";
        }
    }
}

// Test 3: Places endpoint
echo "\n\nTEST 3: Places/Parks List Endpoint\n";
echo str_repeat("=", 60) . "\n";

$placesEndpoints = [
    "fd/places",
    "places",
    "fd/place/list",
    "place/list",
];

foreach ($placesEndpoints as $endpoint) {
    $url = $baseUrl . $endpoint;
    echo "Trying: {$endpoint}\n";
    
    try {
        $http = new HttpClient($ua);
        [$status, $body] = $http->get($url);
        
        $isJson = substr(trim($body), 0, 1) !== '<';
        if ($isJson) {
            $data = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                echo "  ✅ SUCCESS! Found " . count($data) . " places\n";
                
                // Find our test parks
                foreach ($testParks as $testPark) {
                    foreach ($data as $place) {
                        if (isset($place['PlaceId']) && $place['PlaceId'] == $testPark['id']) {
                            echo "  Found {$testPark['name']}: " . ($place['Name'] ?? 'N/A') . "\n";
                        }
                    }
                }
                
                file_put_contents("/tmp/places_success.json", json_encode($data, JSON_PRETTY_PRINT));
                echo "  Saved to: /tmp/places_success.json\n";
                break;
            }
        } else {
            echo "  ⚠️  HTML response\n";
        }
    } catch (\Throwable $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n";
    }
}

// Test 4: Test ReserveCalifornia website directly
echo "\n\nTEST 4: ReserveCalifornia Website HTML\n";
echo str_repeat("=", 60) . "\n";

$websiteUrls = [
    "https://www.reservecalifornia.com/CaliforniaWebHome/Facilities/MapView.aspx",
    "https://www.reservecalifornia.com/",
];

foreach ($websiteUrls as $url) {
    echo "Fetching: {$url}\n";
    try {
        $http = new HttpClient($ua);
        [$status, $body] = $http->get($url, [
            'Referer: https://www.reservecalifornia.com/',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ]);
        
        if ($status >= 200 && $status < 300) {
            echo "  ✅ Status: {$status}, Length: " . strlen($body) . " bytes\n";
            
            // Look for embedded JSON
            if (preg_match('/var\s+(\w+)\s*=\s*(\{.*?\});/s', $body, $matches)) {
                echo "  Found JavaScript variable: {$matches[1]}\n";
            }
            
            // Look for API endpoints in JavaScript
            if (preg_match_all('/(https?:\/\/[^\s\'"]+calirdr[^\s\'"]*)/i', $body, $urlMatches)) {
                echo "  Found API URLs in page:\n";
                foreach (array_unique($urlMatches[1]) as $apiUrl) {
                    echo "    - {$apiUrl}\n";
                }
            }
            
            // Save HTML for inspection
            file_put_contents("/tmp/reservecal_html.html", $body);
            echo "  Saved HTML to: /tmp/reservecal_html.html\n";
        }
    } catch (\Throwable $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n\n=== INVESTIGATION COMPLETE ===\n";
echo "Check /tmp/ for successful API responses\n";

