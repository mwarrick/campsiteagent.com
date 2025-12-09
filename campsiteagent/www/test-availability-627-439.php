<?php
/**
 * Test availability scraping for park 627, facility 439
 */

require __DIR__ . '/../app/bootstrap.php';

use CampsiteAgent\Services\ReserveCaliforniaScraper;

header('Content-Type: text/plain');

echo "=== Testing Availability for Park 627, Facility 439 ===\n\n";

try {
    $scraper = new ReserveCaliforniaScraper();
    echo "Scraper created\n\n";
    
    // Test fetching availability for December 19, 2025 (should have 16 sites available)
    $startDate = '2025-12-19';
    $nights = 1;
    
    echo "Fetching availability for:\n";
    echo "  Park: 627 (Chino Hills SP)\n";
    echo "  Facility: 439 (Rolling M. Ranch Campground)\n";
    echo "  Start Date: {$startDate}\n";
    echo "  Nights: {$nights}\n";
    echo "  Expected: 16 sites available\n\n";
    
    $gridData = $scraper->fetchFacilityAvailability('627', '439', $startDate, $nights);
    
    if (empty($gridData)) {
        echo "❌ NO AVAILABILITY DATA RETURNED!\n";
        echo "\nCheck error logs for details.\n";
    } else {
        echo "✅ Availability data returned!\n\n";
        
        // Debug: show the structure of the data
        echo "=== Debug: Data Structure ===\n";
        echo "Top-level keys: " . implode(', ', array_keys($gridData)) . "\n";
        if (isset($gridData['Facility'])) {
            echo "Facility keys: " . implode(', ', array_keys($gridData['Facility'])) . "\n";
        }
        
        // Check if Units is an array or object
        if (isset($gridData['Facility']['Units'])) {
            $units = $gridData['Facility']['Units'];
            echo "Units type: " . gettype($units) . "\n";
            
            if (is_array($units)) {
                echo "Found " . count($units) . " unit(s) (array)\n";
            } else {
                echo "Units is an object, converting to array...\n";
                $units = (array)$units;
                echo "Found " . count($units) . " unit(s) (after conversion)\n";
            }
            
            // Check first unit structure
            if (count($units) > 0) {
                $firstUnitKey = array_key_first($units);
                $firstUnit = $units[$firstUnitKey];
                echo "\nFirst unit (key: {$firstUnitKey}):\n";
                echo "  Type: " . gettype($firstUnit) . "\n";
                if (is_array($firstUnit) || is_object($firstUnit)) {
                    $firstUnitArray = (array)$firstUnit;
                    echo "  Keys: " . implode(', ', array_keys($firstUnitArray)) . "\n";
                    
                    if (isset($firstUnitArray['Slices'])) {
                        $slices = $firstUnitArray['Slices'];
                        $slicesArray = is_array($slices) ? $slices : (array)$slices;
                        echo "  Has Slices: YES (" . count($slicesArray) . " slice(s))\n";
                        if (count($slicesArray) > 0) {
                            $firstSliceKey = array_key_first($slicesArray);
                            $firstSlice = $slicesArray[$firstSliceKey];
                            if ($firstSlice !== null) {
                                $firstSliceArray = is_array($firstSlice) ? $firstSlice : (array)$firstSlice;
                                echo "  First slice (key: {$firstSliceKey}) keys: " . implode(', ', array_keys($firstSliceArray)) . "\n";
                                echo "  First slice data: " . json_encode($firstSliceArray) . "\n";
                            } else {
                                echo "  ⚠️  First slice is null\n";
                            }
                        }
                    } else {
                        echo "  ⚠️  NO Slices key\n";
                        echo "  First unit sample (first 500 chars): " . substr(json_encode($firstUnitArray, JSON_PRETTY_PRINT), 0, 500) . "...\n";
                    }
                }
            }
        } else {
            echo "⚠️  No Facility.Units found in data\n";
            echo "Available paths:\n";
            if (isset($gridData['Facility'])) {
                echo "  Facility exists\n";
            }
            if (isset($gridData['Units'])) {
                echo "  Units at top level\n";
            }
            echo "Full data structure (first 1000 chars):\n";
            echo substr(json_encode($gridData, JSON_PRETTY_PRINT), 0, 1000) . "...\n";
        }
        echo "\n";
        
        if (isset($gridData['Facility']['Units'])) {
            $units = $gridData['Facility']['Units'];
            if (!is_array($units)) {
                $units = (array)$units;
            }
            echo "=== Unit Details ===\n";
            
            $totalAvailableDates = 0;
            $unitsWithAvailability = 0;
            
            $unitArray = is_array($units) ? $units : (array)$units;
            $idx = 0;
            foreach ($unitArray as $unitKey => $unit) {
                if ($idx >= 5) {
                    echo "... and " . (count($unitArray) - 5) . " more units\n";
                    break;
                }
                $unitData = is_array($unit) ? $unit : (array)$unit;
                echo "Unit " . ($idx + 1) . " (key: {$unitKey}):\n";
                echo "  Site Number: " . ($unitData['Site'] ?? $unitData['SiteNumber'] ?? $unitData['SiteId'] ?? $unitData['ShortName'] ?? $unitKey) . "\n";
                echo "  Site Name: " . ($unitData['SiteName'] ?? $unitData['Name'] ?? 'N/A') . "\n";
                echo "  Unit Type: " . ($unitData['UnitType'] ?? $unitData['UnitTypeName'] ?? 'N/A') . "\n";
                
                if (isset($unitData['Slices'])) {
                    $slices = $unitData['Slices'];
                    $slicesArray = is_array($slices) ? $slices : (array)$slices;
                    $availableDates = [];
                    $unavailableDates = [];
                    foreach ($slicesArray as $sliceKey => $slice) {
                        if ($slice === null) {
                            continue;
                        }
                        $sliceData = is_array($slice) ? $slice : (array)$slice;
                        $date = $sliceData['Date'] ?? $sliceData['date'] ?? null;
                        $isFree = $sliceData['IsFree'] ?? $sliceData['isFree'] ?? $sliceData['IsAvailable'] ?? false;
                        if ($date) {
                            if ($isFree) {
                                $availableDates[] = $date;
                            } else {
                                $unavailableDates[] = $date;
                            }
                        }
                    }
                    echo "  Total slices: " . count($slicesArray) . "\n";
                    echo "  Available dates: " . count($availableDates) . " date(s)\n";
                    echo "  Unavailable dates: " . count($unavailableDates) . " date(s)\n";
                    if (count($availableDates) > 0) {
                        $unitsWithAvailability++;
                        $totalAvailableDates += count($availableDates);
                        echo "  First 10 available dates: " . implode(', ', array_slice($availableDates, 0, 10)) . "\n";
                    } else {
                        echo "  ⚠️  No available dates for this unit\n";
                    }
                } else {
                    echo "  ⚠️  No slices data\n";
                    echo "  Unit keys: " . implode(', ', array_keys($unitData)) . "\n";
                }
                echo "\n";
                $idx++;
            }
            
            // Check specific date: 2025-12-19
            $targetDate = '2025-12-19';
            $availableOnTargetDate = [];
            $unitArray = is_array($units) ? $units : (array)$units;
            foreach ($unitArray as $unitKey => $unit) {
                $unitData = is_array($unit) ? $unit : (array)$unit;
                if (isset($unitData['Slices'])) {
                    $slices = $unitData['Slices'];
                    $slicesArray = is_array($slices) ? $slices : (array)$slices;
                    foreach ($slicesArray as $slice) {
                        if ($slice === null) {
                            continue;
                        }
                        $sliceData = is_array($slice) ? $slice : (array)$slice;
                        $date = $sliceData['Date'] ?? $sliceData['date'] ?? null;
                        $isFree = $sliceData['IsFree'] ?? $sliceData['isFree'] ?? $sliceData['IsAvailable'] ?? false;
                        if ($date === $targetDate && $isFree) {
                            $siteNumber = $unitData['Site'] ?? $unitData['SiteNumber'] ?? $unitData['SiteId'] ?? $unitData['ShortName'] ?? $unitKey;
                            $siteName = $unitData['SiteName'] ?? $unitData['Name'] ?? '';
                            $availableOnTargetDate[] = [
                                'site' => $siteNumber,
                                'name' => $siteName
                            ];
                            break;
                        }
                    }
                }
            }
            
            echo "\n=== Summary ===\n";
            echo "Total units: " . count($units) . "\n";
            echo "Units with availability: {$unitsWithAvailability}\n";
            echo "Total available date slots: {$totalAvailableDates}\n";
            echo "\n=== Availability on {$targetDate} ===\n";
            echo "Sites available: " . count($availableOnTargetDate) . " (expected: 16)\n";
            if (count($availableOnTargetDate) > 0) {
                echo "\nAvailable sites:\n";
                foreach ($availableOnTargetDate as $site) {
                    echo "  - Site " . $site['site'] . ($site['name'] ? " (" . $site['name'] . ")" : "") . "\n";
                }
            } else {
                echo "⚠️  No sites found available on {$targetDate}\n";
            }
        } else {
            echo "⚠️  Data structure unexpected:\n";
            echo json_encode($gridData, JSON_PRETTY_PRINT) . "\n";
        }
    }
} catch (\Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

