<?php

namespace CampsiteAgent\Services;

use CampsiteAgent\Repositories\ParkRepository;
use CampsiteAgent\Repositories\FacilityRepository;
use CampsiteAgent\Repositories\SiteRepository;
use CampsiteAgent\Repositories\SettingsRepository;

/**
 * Service for syncing park/facility/site metadata without availability data
 * This is much faster than a full scrape and should be run manually/rarely
 */
class MetadataSyncService
{
    private ParkRepository $parks;
    private FacilityRepository $facilities;
    private SiteRepository $sites;
    private ReserveCaliforniaScraper $scraper;

    public function __construct()
    {
        $this->parks = new ParkRepository();
        $this->facilities = new FacilityRepository();
        $this->sites = new SiteRepository();
        
        // Get user agent from settings
        $ua = null;
        try {
            $settings = new SettingsRepository();
            $ua = $settings->get('rc_user_agent');
        } catch (\Throwable $e) {
            // Settings table may not exist
        }
        
        $this->scraper = new ReserveCaliforniaScraper($ua);
    }

    /**
     * Sync metadata for all active parks
     * @return array Results for each park
     */
    public function syncAllActiveParks(): array
    {
        $parks = $this->parks->findActiveParks();
        $results = [];

        foreach ($parks as $park) {
            $parkNumber = $park['park_number'] ?? $park['external_id'];
            // Parse facility_filter JSON if present
            $facilityFilter = null;
            if (!empty($park['facility_filter'])) {
                $facilityFilter = json_decode($park['facility_filter'], true);
            }
            
            try {
                $result = $this->syncParkMetadata((int)$park['id'], (string)$parkNumber, $facilityFilter);
                $results[] = [
                    'park' => $park['name'],
                    'success' => true,
                    'facilities' => $result['facilities'],
                    'sites' => $result['sites']
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'park' => $park['name'],
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Sync metadata for a specific park
     * @param int $parkId Database park ID
     * @param string $parkNumber External park number (e.g., '712')
     * @return array Stats about what was synced
     */
    public function syncParkMetadata(int $parkId, string $parkNumber, ?array $facilityFilter = null): array
    {
        // 1. Fetch facilities from API (with optional filter)
        $apiFacilities = $this->scraper->fetchParkFacilities($parkNumber, $facilityFilter);
        
        $facilitiesCount = 0;
        $sitesCount = 0;
        
        // 2. Save facilities and collect their DB IDs
        $facilityMap = []; // external_id => db_id
        foreach ($apiFacilities as $facility) {
            $dbId = $this->facilities->upsertFacility(
                $parkId,
                $facility['name'],
                $facility['facility_id']
            );
            $facilityMap[$facility['facility_id']] = $dbId;
            $facilitiesCount++;
        }
        
        // 3. Fetch one month of data to get site metadata (we don't need all 6 months)
        $currentMonth = date('Y-m');
        
        $facilityMapper = function($facilityId, $facilityName) use ($facilityMap) {
            return $facilityMap[$facilityId] ?? null;
        };
        
        $sites = $this->scraper->fetchMonthlyAvailability($parkNumber, $currentMonth, $facilityMapper, $facilityFilter);
        
        // 4. Save site metadata (without availability data)
        foreach ($sites as $site) {
            $this->sites->upsertSite(
                $parkId,
                $site['site_number'],
                $site['site_type'] ?? null,
                [], // attributes_json
                $site['facility_db_id'] ?? null,
                $site['site_name'] ?? null,
                $site['unit_type_id'] ?? null,
                $site['is_ada'] ?? false,
                $site['vehicle_length'] ?? 0
            );
            $sitesCount++;
        }
        
        return [
            'facilities' => $facilitiesCount,
            'sites' => $sitesCount
        ];
    }
}



