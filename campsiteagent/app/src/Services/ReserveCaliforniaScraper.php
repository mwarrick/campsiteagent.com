<?php

namespace CampsiteAgent\Services;

use CampsiteAgent\Infrastructure\HttpClient;

class ReserveCaliforniaScraper
{
    private HttpClient $http;
    private string $baseUrl;
    private string $rdrBaseUrl;
    private ?BrowserScraper $browserScraper;
    private bool $useBrowser;

    public function __construct(?string $userAgentOverride = null)
    {
        $this->http = new HttpClient($userAgentOverride);
        $this->baseUrl = rtrim(getenv('RC_BASE_URL') ?: 'https://www.reservecalifornia.com', '/');
        $this->rdrBaseUrl = 'https://calirdr.usedirect.com/rdr/rdr';
        
        // Try to initialize browser scraper
        // Use browser by default since API is blocked
        $this->useBrowser = getenv('RC_USE_BROWSER') !== 'false';
        try {
            $this->browserScraper = new BrowserScraper();
        } catch (\Throwable $e) {
            error_log("ReserveCaliforniaScraper: Browser scraper not available: " . $e->getMessage());
            $this->browserScraper = null;
            $this->useBrowser = false;
        }
    }

    /**
     * Fetch facilities for a given park number
     * Returns array of [ ['facility_id' => 'xxx', 'name' => 'Bluff Camp (sites 44-66)'], ... ]
     * 
     * @param string $parkNumber Park number/PlaceId
     * @param array|null $facilityFilter Optional array of facility IDs to include (null = all)
     */
    public function fetchParkFacilities(string $parkNumber, ?array $facilityFilter = null): array
    {
        error_log("ReserveCaliforniaScraper: fetchParkFacilities called for park {$parkNumber}, useBrowser=" . ($this->useBrowser ? 'true' : 'false') . ", browserScraper=" . ($this->browserScraper ? 'set' : 'null'));
        
        // Try browser method first (since API is blocked)
        if ($this->useBrowser && $this->browserScraper) {
            try {
                error_log("ReserveCaliforniaScraper: Attempting browser method for park {$parkNumber}");
                $browserFacilities = $this->browserScraper->fetchFacilities($parkNumber);
                error_log("ReserveCaliforniaScraper: Browser method returned " . (is_array($browserFacilities) ? count($browserFacilities) : 'non-array') . " facility(ies) for park {$parkNumber}");
                
                if (!is_array($browserFacilities)) {
                    error_log("ReserveCaliforniaScraper: Browser method returned non-array data for park {$parkNumber}: " . gettype($browserFacilities));
                    // Fall through to API method
                } else {
                    // Convert browser format to our format
                    $facilities = [];
                    foreach ($browserFacilities as $facility) {
                        $facilityId = (string)($facility['FacilityId'] ?? $facility['facility_id'] ?? '');
                        $name = $facility['Name'] ?? $facility['name'] ?? '';
                        
                        if (empty($facilityId) || empty($name)) {
                            error_log("ReserveCaliforniaScraper: Skipping facility with missing ID or name: " . json_encode($facility));
                            continue;
                        }
                        
                        // Apply facility filter if provided
                        if ($facilityFilter !== null && !in_array($facilityId, $facilityFilter)) {
                            error_log("ReserveCaliforniaScraper: Facility {$facilityId} filtered out by facilityFilter");
                            continue;
                        }
                        
                        $facilities[] = [
                            'facility_id' => $facilityId,
                            'name' => $name
                        ];
                    }
                    
                    error_log("ReserveCaliforniaScraper: After conversion, found " . count($facilities) . " facility(ies) for park {$parkNumber}");
                    
                    if (!empty($facilities)) {
                        return $facilities;
                    } else {
                        error_log("ReserveCaliforniaScraper: Browser method returned empty facilities array for park {$parkNumber} - API fallback disabled (blocked)");
                    }
                }
            } catch (\Throwable $e) {
                error_log("ReserveCaliforniaScraper: Browser method failed for facilities (park {$parkNumber}): " . $e->getMessage());
                error_log("ReserveCaliforniaScraper: Stack trace: " . $e->getTraceAsString());
            }
        } else {
            error_log("ReserveCaliforniaScraper: Browser scraper not available (useBrowser: " . ($this->useBrowser ? 'true' : 'false') . ", browserScraper: " . ($this->browserScraper ? 'set' : 'null') . ")");
        }
        
        // API method is blocked - don't try it
        error_log("ReserveCaliforniaScraper: Returning empty array for park {$parkNumber} (browser method returned 0 facilities, API is blocked)");
        return [];
    }

    /**
     * Fetch availability for a given facility and date range
     * 
     * @param string $parkNumber PlaceId (e.g., '712')
     * @param string $facilityId FacilityId (e.g., '674')
     * @param string $startDate Start date YYYY-MM-DD
     * @param int $nights Number of nights to check
     * @return array Grid response with Units and Slices
     */
    public function fetchFacilityAvailability(string $parkNumber, string $facilityId, string $startDate, int $nights): array
    {
        // Try browser method first (since API is blocked)
        if ($this->useBrowser && $this->browserScraper) {
            try {
                error_log("ReserveCaliforniaScraper: Attempting browser method for availability (park {$parkNumber}, facility {$facilityId}, date {$startDate})");
                $data = $this->browserScraper->fetchAvailability($parkNumber, $facilityId, $startDate, $nights);
                error_log("ReserveCaliforniaScraper: Browser method returned " . (is_array($data) ? 'array with ' . count($data) . ' keys' : gettype($data)));
                
                // Validate structure
                if (is_array($data) && isset($data['Facility']['Units'])) {
                    error_log("ReserveCaliforniaScraper: Browser method returned valid grid data with " . count($data['Facility']['Units']) . " units");
                    return $data;
                }
                
                // If browser returned data but wrong structure, try to normalize
                if (is_array($data) && !empty($data)) {
                    error_log("ReserveCaliforniaScraper: Browser returned data but wrong structure. Keys: " . implode(', ', array_keys($data)));
                    // Browser might return data in different format, try to adapt
                    if (isset($data['Units'])) {
                        error_log("ReserveCaliforniaScraper: Found Units at top level, normalizing structure");
                        return ['Facility' => ['Units' => $data['Units']]];
                    }
                } else {
                    error_log("ReserveCaliforniaScraper: Browser method returned empty or invalid data");
                }
            } catch (\Throwable $e) {
                error_log("ReserveCaliforniaScraper: Browser method failed for availability: " . $e->getMessage());
                error_log("ReserveCaliforniaScraper: Stack trace: " . $e->getTraceAsString());
                // Fall through to API method
            }
        }
        
        // Fallback to API method (may be blocked, but try anyway)
        $url = $this->rdrBaseUrl . '/search/grid';
        
        $params = [
            'PlaceId' => (int)$parkNumber,
            'FacilityId' => (int)$facilityId,
            'StartDate' => $startDate,
            'Nights' => $nights,
            'InSeasonOnly' => false,  // Include out-of-season dates
            'WebOnly' => true,        // Only web-bookable sites
            'UnitCategoryId' => 0,    // All unit types
            'UnitTypeGroupId' => 0,   // All unit type groups
            'SleepingUnitId' => 0     // Any sleeping arrangement
        ];
        
        try {
            [$status, $body] = $this->http->post($url, $params);
            if ($status >= 200 && $status < 300) {
                $data = json_decode($body, true);
                if (is_array($data) && isset($data['Facility']['Units'])) {
                    return $data;
                }
            }
        } catch (\Throwable $e) {
            error_log("ReserveCaliforniaScraper: API method failed for availability: " . $e->getMessage());
        }
        
        return [];
    }

    /**
     * Fetch availability for a given park number and month (YYYY-MM).
     * Returns array of sites with their availability
     * 
     * @param string $parkNumber Park ID
     * @param string $yearMonth YYYY-MM
     * @param callable|null $facilityMapper Optional callback to map facility_id to DB ID: function(facilityId, name) => dbId
     * @param array|null $facilityFilter Optional array of facility IDs to include (null = all)
     * @return array [ ['site_number' => '12', 'site_name' => 'Bluff #12', 'facility_id' => '674', 'dates' => ['YYYY-MM-DD' => true/false, ...]], ... ]
     */
    public function fetchMonthlyAvailability(string $parkNumber, string $yearMonth, ?callable $facilityMapper = null, ?array $facilityFilter = null, ?callable $debugCallback = null): array
    {
        // Parse year-month
        // For the current month, start from today to get more relevant data
        // For future months, start from the 1st
        $today = new \DateTime();
        $requestedMonth = new \DateTime($yearMonth . '-01');
        $isCurrentMonth = ($today->format('Y-m') === $yearMonth);
        
        if ($isCurrentMonth) {
            // For current month, use today's date to get data starting from today
            $startDate = $today->format('Y-m-d');
            if ($debugCallback) {
                $debugCallback("    📅 Current month detected, using today's date: {$startDate}");
            }
        } else {
            // For future months, start from the 1st
            $startDate = $yearMonth . '-01';
        }
        
        $endDate = date('Y-m-t', strtotime($yearMonth . '-01')); // Last day of month
        $days = (int)date('t', strtotime($yearMonth . '-01'));
        
        // Get all facilities for this park (with optional filter)
        $facilities = $this->fetchParkFacilities($parkNumber, $facilityFilter);
        if ($debugCallback) {
            $debugCallback("🔍 Found " . count($facilities) . " facility(ies) for park {$parkNumber} in {$yearMonth}");
        }
        if (empty($facilities)) {
            return [];
        }
        
        $allSites = [];
        
        // Fetch availability for each facility
        foreach ($facilities as $facility) {
            if ($debugCallback) {
                $debugCallback("  → Fetching facility: {$facility['name']} (ID: {$facility['facility_id']})");
            }
            // Get DB facility ID if mapper provided
            $facilityDbId = null;
            if ($facilityMapper) {
                $facilityDbId = $facilityMapper($facility['facility_id'], $facility['name']);
            }
            
            // Request 1 night to get all single-date availability
            // The API will return slices for all days in the range
            if ($debugCallback) {
                $debugCallback("    📅 Fetching availability for {$yearMonth} (start date: {$startDate})");
            }
            $gridData = $this->fetchFacilityAvailability(
                $parkNumber,
                $facility['facility_id'],
                $startDate,
                1  // Changed from $days to 1 to get single-night availability
            );
            
            if ($debugCallback) {
                if (empty($gridData)) {
                    $debugCallback("    ⚠️ No grid data returned for {$yearMonth}");
                } else {
                    $unitsCount = isset($gridData['Facility']['Units']) ? count($gridData['Facility']['Units']) : 0;
                    $debugCallback("    ✅ Got grid data with {$unitsCount} units for {$yearMonth}");
                    
                    // Check date range in response
                    if (isset($gridData['StartDate'])) {
                        $debugCallback("    📅 API returned StartDate: {$gridData['StartDate']}");
                    }
                    if (isset($gridData['Facility']['Units']) && count($gridData['Facility']['Units']) > 0) {
                        $firstUnit = reset($gridData['Facility']['Units']);
                        if (isset($firstUnit['Slices']) && count($firstUnit['Slices']) > 0) {
                            $firstSlice = reset($firstUnit['Slices']);
                            $lastSlice = end($firstUnit['Slices']);
                            $firstDate = $firstSlice['Date'] ?? 'unknown';
                            $lastDate = $lastSlice['Date'] ?? 'unknown';
                            $debugCallback("    📅 Slice date range: {$firstDate} to {$lastDate}");
                        }
                    }
                }
            }
            
            if (empty($gridData) || !isset($gridData['Facility']['Units'])) {
                if ($debugCallback) {
                    $debugCallback("    ⚠️ No grid data or units returned for facility {$facility['name']}");
                }
                continue;
            }
            
            // Parse units/sites
            $unitsCount = count($gridData['Facility']['Units']);
            if ($debugCallback) {
                $debugCallback("    📊 Found {$unitsCount} unit(s) in facility {$facility['name']}");
            }
            foreach ($gridData['Facility']['Units'] as $unitId => $unit) {
                $siteData = [
                    'site_number' => $unit['ShortName'] ?? $unitId,
                    'site_name' => $unit['Name'] ?? '',
                    'facility_id' => $facility['facility_id'], // External facility ID
                    'facility_db_id' => $facilityDbId, // Our DB ID
                    'facility_name' => $facility['name'],
                    'unit_type_id' => $unit['UnitTypeId'] ?? 0,
                    'is_ada' => $unit['IsAda'] ?? false,
                    'vehicle_length' => $unit['VehicleLength'] ?? 0,
                    'dates' => []
                ];
                
                // Parse availability slices
                if (isset($unit['Slices'])) {
                    $slices = $unit['Slices'];
                    $slicesArray = is_array($slices) ? $slices : (array)$slices;
                    $slicesCount = count($slicesArray);
                    $availableCount = 0;
                    $unavailableCount = 0;
                    foreach ($slicesArray as $slice) {
                        // Skip null slices
                        if ($slice === null || !is_array($slice)) {
                            continue;
                        }
                        
                        $date = $slice['Date'] ?? null;
                        if (!$date) {
                            continue;
                        }
                        
                        // Only filter out dates that are clearly unreasonable (more than 2 years in the future)
                        // This prevents storing dates from way outside the requested range while allowing
                        // the API's natural response window when Nights=1
                        $maxReasonableDate = date('Y-m-d', strtotime('+2 years'));
                        if ($date > $maxReasonableDate) {
                            continue;
                        }
                        
                        // Site is available if IsFree=true and not blocked/reserved
                        $isFree = $slice['IsFree'] ?? false;
                        $isBlocked = $slice['IsBlocked'] ?? false;
                        $reservationId = $slice['ReservationId'] ?? 0;
                        $isAvailable = $isFree && !$isBlocked && $reservationId == 0;
                        
                        if ($isAvailable) {
                            $availableCount++;
                        } else {
                            $unavailableCount++;
                        }
                        
                        $siteData['dates'][$date] = $isAvailable;
                    }
                    
                    if ($debugCallback && $slicesCount > 0) {
                        $siteNum = $siteData['site_number'];
                        $debugCallback("      Site {$siteNum}: {$slicesCount} slice(s) - {$availableCount} available, {$unavailableCount} unavailable");
                    }
                } else {
                    // No slices for this unit - still add the site but with empty dates
                    if ($debugCallback) {
                        $siteNum = $siteData['site_number'];
                        $debugCallback("      Site {$siteNum}: No slices returned");
                    }
                }
                
                $allSites[] = $siteData;
            }
        }
        
        if ($debugCallback) {
            $totalSites = count($allSites);
            $sitesWithDates = 0;
            $totalAvailableDates = 0;
            foreach ($allSites as $site) {
                if (!empty($site['dates'])) {
                    $sitesWithDates++;
                    foreach ($site['dates'] as $date => $avail) {
                        if ($avail) {
                            $totalAvailableDates++;
                        }
                    }
                }
            }
            $debugCallback("📊 Summary for {$yearMonth}: {$totalSites} site(s) found, {$sitesWithDates} with date data, {$totalAvailableDates} available date(s)");
        }
        
        return $allSites;
    }

    /**
     * Parse facilities from HTML page
     */
    private function parseFacilitiesFromHtml(string $html): array
    {
        $facilities = [];
        
        // Look for facilities in various patterns
        // Pattern 1: Look for facility data in JavaScript variables
        if (preg_match_all('/facilityId[\'"]?\s*:\s*[\'"]([^\'"]+)[\'"].*?facilityName[\'"]?\s*:\s*[\'"]([^\'"]+)[\'"]/s', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $facilities[] = [
                    'facility_id' => $match[1],
                    'name' => $match[2]
                ];
            }
        }
        
        // Pattern 2: Look for facility dropdown options
        if (preg_match_all('/<option[^>]+value=[\'"](\d+)[\'"][^>]*>([^<]+)<\/option>/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $value = trim($match[1]);
                $name = trim($match[2]);
                // Skip empty or generic options
                if ($value && $value !== '0' && $name && !in_array(strtolower($name), ['select', 'all', 'any'])) {
                    $facilities[] = [
                        'facility_id' => $value,
                        'name' => $name
                    ];
                }
            }
        }
        
        // Pattern 3: Look for JSON data embedded in the page
        if (preg_match('/facilities\s*[=:]\s*(\[.*?\])/s', $html, $match)) {
            $jsonData = json_decode($match[1], true);
            if (is_array($jsonData)) {
                foreach ($jsonData as $facility) {
                    if (isset($facility['id']) && isset($facility['name'])) {
                        $facilities[] = [
                            'facility_id' => (string)$facility['id'],
                            'name' => $facility['name']
                        ];
                    }
                }
            }
        }
        
        // Remove duplicates based on facility_id
        $unique = [];
        foreach ($facilities as $facility) {
            $unique[$facility['facility_id']] = $facility;
        }
        
        return array_values($unique);
    }

    private function normalizeJson(array $data): array
    {
        $out = [];
        // Expected shape (example):
        // { sites: [ { number: "12", type: "Tent", availability: { "2025-10-10": true, ... } }, ... ] }
        $sites = $data['sites'] ?? [];
        foreach ($sites as $s) {
            $out[] = [
                'site_number' => (string)($s['number'] ?? ''),
                'site_type' => (string)($s['type'] ?? ''),
                'dates' => is_array($s['availability'] ?? null) ? $s['availability'] : [],
            ];
        }
        return $out;
    }
}
