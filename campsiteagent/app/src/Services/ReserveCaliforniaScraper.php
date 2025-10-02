<?php

namespace CampsiteAgent\Services;

use CampsiteAgent\Infrastructure\HttpClient;

class ReserveCaliforniaScraper
{
    private HttpClient $http;
    private string $baseUrl;
    private string $rdrBaseUrl;

    public function __construct(?string $userAgentOverride = null)
    {
        $this->http = new HttpClient($userAgentOverride);
        $this->baseUrl = rtrim(getenv('RC_BASE_URL') ?: 'https://www.reservecalifornia.com', '/');
        $this->rdrBaseUrl = 'https://calirdr.usedirect.com/rdr/rdr';
    }

    /**
     * Fetch facilities for a given park number from UseDirect API
     * Returns array of [ ['facility_id' => 'xxx', 'name' => 'Bluff Camp (sites 44-66)'], ... ]
     * 
     * @param string $parkNumber Park number/PlaceId
     * @param array|null $facilityFilter Optional array of facility IDs to include (null = all)
     */
    public function fetchParkFacilities(string $parkNumber, ?array $facilityFilter = null): array
    {
        // First get all facilities (includes all parks)
        $url = $this->rdrBaseUrl . '/fd/facilities?PlaceId=' . rawurlencode($parkNumber);
        
        try {
            [$status, $body] = $this->http->get($url);
            if ($status >= 200 && $status < 300) {
                $allFacilities = json_decode($body, true);
                if (!is_array($allFacilities)) {
                    return [];
                }
                
                // Filter to only facilities for this park
                $facilities = [];
                foreach ($allFacilities as $facility) {
                    if (isset($facility['PlaceId']) && $facility['PlaceId'] == $parkNumber) {
                        $facilityId = (string)$facility['FacilityId'];
                        
                        // Apply facility filter if provided
                        if ($facilityFilter !== null && !in_array($facilityId, $facilityFilter)) {
                            continue; // Skip this facility
                        }
                        
                        $facilities[] = [
                            'facility_id' => $facilityId,
                            'name' => $facility['Name']
                        ];
                    }
                }
                
                return $facilities;
            }
        } catch (\Throwable $e) {
            // Log error and return empty
        }
        
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
            // Log error
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
    public function fetchMonthlyAvailability(string $parkNumber, string $yearMonth, ?callable $facilityMapper = null, ?array $facilityFilter = null): array
    {
        // Parse year-month
        $startDate = $yearMonth . '-01';
        $endDate = date('Y-m-t', strtotime($startDate)); // Last day of month
        $days = (int)date('t', strtotime($startDate));
        
        // Get all facilities for this park (with optional filter)
        $facilities = $this->fetchParkFacilities($parkNumber, $facilityFilter);
        if (empty($facilities)) {
            return [];
        }
        
        $allSites = [];
        
        // Fetch availability for each facility
        foreach ($facilities as $facility) {
            // Get DB facility ID if mapper provided
            $facilityDbId = null;
            if ($facilityMapper) {
                $facilityDbId = $facilityMapper($facility['facility_id'], $facility['name']);
            }
            
            // Request 1 night to get all single-date availability
            // The API will return slices for all days in the range
            $gridData = $this->fetchFacilityAvailability(
                $parkNumber,
                $facility['facility_id'],
                $startDate,
                1  // Changed from $days to 1 to get single-night availability
            );
            
            if (empty($gridData) || !isset($gridData['Facility']['Units'])) {
                continue;
            }
            
            // Parse units/sites
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
                if (isset($unit['Slices']) && is_array($unit['Slices'])) {
                    foreach ($unit['Slices'] as $slice) {
                        $date = $slice['Date'];
                        // Site is available if IsFree=true and not blocked/reserved
                        $isAvailable = ($slice['IsFree'] ?? false) 
                            && !($slice['IsBlocked'] ?? false)
                            && ($slice['ReservationId'] ?? 0) == 0;
                        
                        $siteData['dates'][$date] = $isAvailable;
                    }
                }
                
                $allSites[] = $siteData;
            }
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
