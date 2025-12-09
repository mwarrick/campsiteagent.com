#!/usr/bin/env php
<?php

/**
 * Investigate ReserveCalifornia website HTML structure
 * to find embedded data or API endpoints
 */

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Infrastructure\HttpClient;
use CampsiteAgent\Repositories\SettingsRepository;

$ua = null;
try {
    $settings = new SettingsRepository();
    $ua = $settings->get('rc_user_agent');
} catch (\Throwable $e) {
    // Ignore
}

$http = new HttpClient($ua);

echo "=== WEBSITE HTML INVESTIGATION ===\n\n";

// Test URLs
$urls = [
    'https://www.reservecalifornia.com/',
    'https://www.reservecalifornia.com/CaliforniaWebHome/Facilities/MapView.aspx?PlaceId=627',
    'https://www.reservecalifornia.com/CaliforniaWebHome/Facilities/Details.aspx?PlaceId=627',
    'https://www.reservecalifornia.com/CaliforniaWebHome/Facilities/Search.aspx?PlaceId=627',
];

foreach ($urls as $url) {
    echo "Fetching: {$url}\n";
    try {
        [$status, $html] = $http->get($url, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Referer: https://www.reservecalifornia.com/',
        ]);
        
        if ($status >= 200 && $status < 300) {
            echo "  ✅ Status: {$status}, Length: " . strlen($html) . " bytes\n";
            
            // 1. Look for embedded JavaScript variables
            echo "  Checking for embedded JavaScript data...\n";
            $jsVars = [];
            if (preg_match_all('/var\s+(\w+)\s*=\s*(\{.*?\}|\[.*?\]);/s', $html, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $varName = $match[1];
                    $varValue = $match[2];
                    if (strlen($varValue) < 5000) { // Only show reasonable-sized vars
                        $jsVars[$varName] = $varValue;
                    }
                }
            }
            if (!empty($jsVars)) {
                echo "    ✅ Found JavaScript variables: " . implode(', ', array_keys($jsVars)) . "\n";
                foreach ($jsVars as $name => $value) {
                    echo "      {$name}: " . substr($value, 0, 200) . "...\n";
                }
            }
            
            // 2. Look for API endpoints in JavaScript
            echo "  Checking for API endpoints...\n";
            $apiEndpoints = [];
            if (preg_match_all('/(https?:\/\/[^\s\'"<>]+calirdr[^\s\'"<>]*)/i', $html, $matches)) {
                $apiEndpoints = array_unique($matches[1]);
            }
            if (!empty($apiEndpoints)) {
                echo "    ✅ Found " . count($apiEndpoints) . " API endpoint(s):\n";
                foreach ($apiEndpoints as $endpoint) {
                    echo "      - {$endpoint}\n";
                }
            }
            
            // 3. Look for facility data in select dropdowns
            echo "  Checking for facility dropdowns...\n";
            $facilities = [];
            if (preg_match_all('/<select[^>]*id=["\']?facility[^>]*>.*?<\/select>/is', $html, $selectMatches)) {
                foreach ($selectMatches[0] as $selectHtml) {
                    if (preg_match_all('/<option[^>]+value=["\']?(\d+)["\']?[^>]*>([^<]+)<\/option>/i', $selectHtml, $optMatches, PREG_SET_ORDER)) {
                        foreach ($optMatches as $opt) {
                            if ($opt[1] !== '0' && $opt[1] !== '') {
                                $facilities[] = [
                                    'id' => $opt[1],
                                    'name' => trim($opt[2])
                                ];
                            }
                        }
                    }
                }
            }
            if (!empty($facilities)) {
                echo "    ✅ Found " . count($facilities) . " facility(ies) in dropdowns:\n";
                foreach (array_slice($facilities, 0, 10) as $fac) {
                    echo "      - {$fac['name']} (ID: {$fac['id']})\n";
                }
            }
            
            // 4. Look for JSON data embedded in script tags
            echo "  Checking for JSON in script tags...\n";
            $jsonData = [];
            if (preg_match_all('/<script[^>]*>(.*?)<\/script>/is', $html, $scriptMatches)) {
                foreach ($scriptMatches[1] as $scriptContent) {
                    // Look for JSON objects
                    if (preg_match_all('/(\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\})/s', $scriptContent, $jsonMatches)) {
                        foreach ($jsonMatches[1] as $jsonStr) {
                            $decoded = json_decode($jsonStr, true);
                            if (is_array($decoded) && !empty($decoded)) {
                                $jsonData[] = $jsonStr;
                            }
                        }
                    }
                }
            }
            if (!empty($jsonData)) {
                echo "    ✅ Found " . count($jsonData) . " JSON object(s) in scripts\n";
                foreach (array_slice($jsonData, 0, 3) as $json) {
                    echo "      " . substr($json, 0, 200) . "...\n";
                }
            }
            
            // 5. Look for AJAX/fetch calls
            echo "  Checking for AJAX/fetch calls...\n";
            $ajaxCalls = [];
            if (preg_match_all('/(\.(get|post|ajax|fetch)\([^)]+\))/i', $html, $matches)) {
                $ajaxCalls = array_unique($matches[1]);
            }
            if (!empty($ajaxCalls)) {
                echo "    ✅ Found " . count($ajaxCalls) . " AJAX/fetch call(s):\n";
                foreach (array_slice($ajaxCalls, 0, 10) as $call) {
                    echo "      - " . substr($call, 0, 150) . "\n";
                }
            }
            
            // 6. Save HTML for manual inspection
            $filename = '/tmp/reservecal_' . preg_replace('/[^a-z0-9]/i', '_', parse_url($url, PHP_URL_PATH)) . '.html';
            file_put_contents($filename, $html);
            echo "  💾 Saved HTML to: {$filename}\n";
            
        } else {
            echo "  ⚠️  Status: {$status}\n";
        }
    } catch (\Throwable $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "=== INVESTIGATION COMPLETE ===\n";
echo "Check /tmp/ for saved HTML files\n";

