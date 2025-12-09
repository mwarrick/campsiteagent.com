#!/usr/bin/env php
<?php
/**
 * Test script to debug Chino Hills SP API calls
 * Shows raw API responses for facilities and availability
 */

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Infrastructure\HttpClient;
use CampsiteAgent\Services\ReserveCaliforniaScraper;
use CampsiteAgent\Repositories\SettingsRepository;

$parkNumber = '627'; // Chino Hills SP
$yearMonth = date('Y-m'); // Current month

echo "=== Testing Chino Hills SP (Park Number: {$parkNumber}) ===\n\n";

// Get user agent from settings (same as production)
$ua = null;
try {
    $settings = new SettingsRepository();
    $ua = $settings->get('rc_user_agent');
    echo "Using User-Agent from settings: " . ($ua ?: 'DEFAULT') . "\n\n";
} catch (\Throwable $e) {
    echo "No user agent in settings, using default\n\n";
}

$http = new HttpClient($ua);
$scraper = new ReserveCaliforniaScraper($ua);

// Test 0: First verify the user agent works with a known park
echo "0. Testing with known working park (San Onofre = 712) to verify user agent...\n";
$testFacilities = $scraper->fetchParkFacilities('712');
echo "   San Onofre (712): " . count($testFacilities) . " facility(ies) found\n";
if (count($testFacilities) > 0) {
    echo "   ✅ User agent is working! First facility: {$testFacilities[0]['name']}\n\n";
} else {
    echo "   ⚠️  Even San Onofre returns 0 facilities - user agent might be blocked!\n\n";
}

// Test 1: Fetch facilities
echo "1. Testing fetchParkFacilities() for Chino Hills...\n";
echo "   URL: https://calirdr.usedirect.com/rdr/rdr/fd/facilities?PlaceId={$parkNumber}\n";
$facilities = $scraper->fetchParkFacilities($parkNumber);
echo "   Result: " . count($facilities) . " facility(ies) found\n";

if (empty($facilities)) {
    echo "\n   ⚠️  NO FACILITIES FOUND!\n";
    echo "   Let's check the raw API response...\n\n";
    
    // Try alternative endpoint patterns
    echo "   Trying alternative endpoint patterns...\n\n";
    $baseUrl = 'https://calirdr.usedirect.com/rdr/rdr/';
    $endpoints = [
        "fd/facilities?PlaceId={$parkNumber}",
        "fd/places/{$parkNumber}/facilities",
        "fd/place/{$parkNumber}/facilities",
        "places/{$parkNumber}/facilities",
    ];
    
    foreach ($endpoints as $endpoint) {
        $url = $baseUrl . $endpoint;
        echo "   Trying: {$url}\n";
        
        try {
            [$status, $body] = $http->get($url);
            echo "   HTTP Status: {$status}\n";
            echo "   Response length: " . strlen($body) . " bytes\n";
            
            if ($status >= 200 && $status < 300) {
                // Check if it's JSON or HTML
                if (substr(trim($body), 0, 1) === '<') {
                    echo "   ⚠️  Response is HTML (not JSON)\n";
                    echo "   First 200 chars: " . substr($body, 0, 200) . "\n\n";
                    continue;
                }
                
                $data = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    echo "   ✅ JSON decoded successfully!\n";
                    echo "   Response type: " . gettype($data) . "\n";
                    
                    if (is_array($data)) {
                        echo "   Array count: " . count($data) . "\n";
                        if (count($data) > 0) {
                            echo "   First item keys: " . implode(', ', array_keys($data[0])) . "\n";
                            echo "   First item:\n";
                            echo "   " . json_encode($data[0], JSON_PRETTY_PRINT) . "\n";
                            
                            // Show all facilities
                            echo "\n   All facilities found:\n";
                            foreach ($data as $facility) {
                                $facId = $facility['FacilityId'] ?? $facility['Id'] ?? 'N/A';
                                $facName = $facility['Name'] ?? 'N/A';
                                echo "     - [{$facId}] {$facName}\n";
                            }
                        }
                        echo "\n   ✅ THIS ENDPOINT WORKS! Use: {$endpoint}\n";
                        break;
                    } else {
                        echo "   Response is not an array\n";
                        echo "   Response (first 500 chars):\n";
                        echo "   " . substr($body, 0, 500) . "\n";
                    }
                } else {
                    echo "   JSON decode error: " . json_last_error_msg() . "\n";
                    if (substr(trim($body), 0, 1) === '<') {
                        echo "   Response appears to be HTML\n";
                    }
                }
            } else {
                echo "   HTTP Error: Status {$status}\n";
            }
        } catch (\Throwable $e) {
            echo "   Exception: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
} else {
    echo "   Facilities found:\n";
    foreach ($facilities as $facility) {
        echo "     - {$facility['name']} (ID: {$facility['facility_id']})\n";
    }
    
    // Test 2: Fetch availability for first facility
    if (!empty($facilities)) {
        $facility = $facilities[0];
        echo "\n2. Testing fetchFacilityAvailability() for facility {$facility['facility_id']}...\n";
        echo "   Facility: {$facility['name']}\n";
        echo "   Start Date: {$yearMonth}-01\n";
        echo "   Nights: 1\n";
        
        $gridData = $scraper->fetchFacilityAvailability($parkNumber, $facility['facility_id'], "{$yearMonth}-01", 1);
        
        if (empty($gridData)) {
            echo "   ⚠️  No grid data returned\n";
        } else {
            echo "   ✅ Grid data returned\n";
            if (isset($gridData['Facility']['Units'])) {
                $unitsCount = count($gridData['Facility']['Units']);
                echo "   Units found: {$unitsCount}\n";
                
                if ($unitsCount > 0) {
                    $firstUnit = reset($gridData['Facility']['Units']);
                    $unitId = key($gridData['Facility']['Units']);
                    echo "   First unit ID: {$unitId}\n";
                    echo "   First unit keys: " . implode(', ', array_keys($firstUnit)) . "\n";
                    
                    if (isset($firstUnit['Slices'])) {
                        $slicesCount = count($firstUnit['Slices']);
                        echo "   Slices in first unit: {$slicesCount}\n";
                        
                        if ($slicesCount > 0) {
                            $firstSlice = $firstUnit['Slices'][0];
                            echo "   First slice keys: " . implode(', ', array_keys($firstSlice)) . "\n";
                            echo "   First slice data:\n";
                            echo "   " . json_encode($firstSlice, JSON_PRETTY_PRINT) . "\n";
                        }
                    } else {
                        echo "   ⚠️  No slices in first unit\n";
                    }
                }
            } else {
                echo "   ⚠️  No 'Facility.Units' in response\n";
                echo "   Response keys: " . implode(', ', array_keys($gridData)) . "\n";
                echo "   Response (first 1000 chars):\n";
                echo "   " . substr(json_encode($gridData, JSON_PRETTY_PRINT), 0, 1000) . "\n";
            }
        }
    }
}

echo "\n=== Test Complete ===\n";

