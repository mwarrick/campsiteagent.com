#!/usr/bin/env php
<?php
/**
 * Discover all available parks from ReserveCalifornia API
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

echo "🔍 Discovering all parks from ReserveCalifornia...\n\n";

// Fetch all places/parks
$url = $baseUrl . 'fd/places';
echo "Fetching: $url\n\n";

try {
    [$status, $body] = $http->get($url);
    if ($status >= 200 && $status < 300) {
        $data = json_decode($body, true);
        if ($data && is_array($data)) {
            echo "✅ Found " . count($data) . " parks\n\n";
            
            // Filter for state parks and beaches with camping
            $campingParks = array_filter($data, function($place) {
                $name = $place['Name'] ?? '';
                // Look for State Beach, State Park, State Reserve, etc
                return (
                    stripos($name, 'State Beach') !== false ||
                    stripos($name, 'State Park') !== false ||
                    stripos($name, 'State Reserve') !== false ||
                    stripos($name, 'SB') !== false ||
                    stripos($name, 'SP') !== false
                );
            });
            
            echo "🏕️  Found " . count($campingParks) . " State Parks/Beaches:\n\n";
            
            // Sort by name
            usort($campingParks, function($a, $b) {
                return strcmp($a['Name'] ?? '', $b['Name'] ?? '');
            });
            
            // Display in formatted table
            echo str_pad("Park Name", 50) . " | " . str_pad("PlaceId", 10) . " | Facilities\n";
            echo str_repeat("-", 75) . "\n";
            
            foreach ($campingParks as $place) {
                $name = $place['Name'] ?? 'Unknown';
                $placeId = $place['PlaceId'] ?? 'N/A';
                
                // Try to fetch facilities count
                $facilityCount = '?';
                try {
                    $facilityUrl = $baseUrl . "fd/places/$placeId/facilities";
                    [$fStatus, $fBody] = $http->get($facilityUrl);
                    if ($fStatus >= 200 && $fStatus < 300) {
                        $facilities = json_decode($fBody, true);
                        if (is_array($facilities)) {
                            $facilityCount = count($facilities);
                        }
                    }
                } catch (\Throwable $e) {
                    // Skip
                }
                
                echo str_pad($name, 50) . " | " . str_pad($placeId, 10) . " | $facilityCount\n";
            }
            
            echo "\n\n📋 Popular Southern California Parks to Consider:\n";
            echo "---------------------------------------------\n";
            $popular = [
                'Crystal Cove',
                'Doheny',
                'Carlsbad',
                'San Onofre',
                'San Elijo',
                'South Carlsbad',
                'Leo Carrillo',
                'Malibu Creek',
                'Point Mugu',
                'Refugio'
            ];
            
            foreach ($popular as $searchName) {
                foreach ($campingParks as $place) {
                    if (stripos($place['Name'] ?? '', $searchName) !== false) {
                        echo "\n📍 {$place['Name']}\n";
                        echo "   PlaceId: {$place['PlaceId']}\n";
                        
                        // Get more details
                        try {
                            $facilityUrl = $baseUrl . "fd/places/{$place['PlaceId']}/facilities";
                            [$fStatus, $fBody] = $http->get($facilityUrl);
                            if ($fStatus >= 200 && $fStatus < 300) {
                                $facilities = json_decode($fBody, true);
                                if (is_array($facilities)) {
                                    echo "   Facilities: " . count($facilities) . "\n";
                                    foreach ($facilities as $facility) {
                                        if (isset($facility['Name'])) {
                                            echo "      - [{$facility['FacilityId']}] {$facility['Name']}\n";
                                        }
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            echo "   (Error fetching facilities)\n";
                        }
                        break;
                    }
                }
            }
            
        } else {
            echo "❌ No data received\n";
        }
    } else {
        echo "❌ HTTP $status received\n";
    }
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n\n✅ Discovery complete!\n";

