#!/usr/bin/env php
<?php
/**
 * Test script to explore the UseDirect API endpoints
 * ReserveCalifornia uses the UseDirect reservation system
 * Base URL: https://calirdr.usedirect.com/rdr/rdr/
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

echo "Testing UseDirect API endpoints...\n\n";

// Test 1: Try to get park/place data
echo "==> Test 1: Fetching place/park data for San Onofre (712)\n";
$placeId = 712;

$endpoints = [
    "place/$placeId",
    "fd/place/$placeId",
    "places/$placeId",
    "park/$placeId",
];

foreach ($endpoints as $endpoint) {
    $url = $baseUrl . $endpoint;
    echo "Trying: $url\n";
    
    try {
        [$status, $body] = $http->get($url);
        echo "Status: $status\n";
        
        if ($status >= 200 && $status < 300) {
            echo "SUCCESS! Response:\n";
            
            $data = json_decode($body, true);
            if ($data) {
                echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
            } else {
                echo substr($body, 0, 500) . "...\n";
            }
            echo "\n";
            break;
        } else {
            echo "Failed\n\n";
        }
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }
}

// Test 2: Try to get facilities for the park
echo "\n==> Test 2: Fetching facilities for park 712\n";
$facilityEndpoints = [
    "facilities/place/$placeId",
    "fd/facilities/$placeId",
    "place/$placeId/facilities",
];

foreach ($facilityEndpoints as $endpoint) {
    $url = $baseUrl . $endpoint;
    echo "Trying: $url\n";
    
    try {
        [$status, $body] = $http->get($url);
        echo "Status: $status\n";
        
        if ($status >= 200 && $status < 300) {
            echo "SUCCESS! Response:\n";
            
            $data = json_decode($body, true);
            if ($data) {
                echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
            } else {
                echo substr($body, 0, 1000) . "...\n";
            }
            echo "\n";
            break;
        } else {
            echo "Failed\n\n";
        }
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }
}

echo "\n==> Next steps:\n";
echo "1. If any endpoints worked, we can use them directly\n";
echo "2. If they all failed, we may need to:\n";
echo "   - Add proper headers (Referer, Origin, etc.)\n";
echo "   - Handle Cloudflare challenges\n";
echo "   - Use a headless browser approach\n";



