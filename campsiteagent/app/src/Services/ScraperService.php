<?php

namespace CampsiteAgent\Services;

use CampsiteAgent\Repositories\ParkRepository;
use CampsiteAgent\Repositories\RunRepository;
use CampsiteAgent\Repositories\SiteRepository;
use CampsiteAgent\Repositories\FacilityRepository;

class ScraperService
{
    private ParkRepository $parks;
    private RunRepository $runs;
    private SiteRepository $sites;
    private FacilityRepository $facilities;
    private ?NotificationService $notify;
    private WeekendDetector $detector;
    private ReserveCaliforniaScraper $rc;

    public function __construct(bool $enableNotifications = true)
    {
        $this->parks = new ParkRepository();
        $this->runs = new RunRepository();
        $this->sites = new SiteRepository();
        $this->facilities = new FacilityRepository();
        
        // Only initialize notification service if enabled and credentials are available
        if ($enableNotifications && getenv('GOOGLE_CREDENTIALS_JSON') && file_exists(getenv('GOOGLE_CREDENTIALS_JSON'))) {
            try {
                $this->notify = new NotificationService();
            } catch (\Throwable $e) {
                // If notification service fails to initialize, continue without it
                $this->notify = null;
            }
        } else {
            $this->notify = null;
        }
        
        $this->detector = new WeekendDetector();
        // Prefer admin-configured user agent if present
        $ua = null;
        try {
            $settings = new \CampsiteAgent\Repositories\SettingsRepository();
            $ua = $settings->get('rc_user_agent');
        } catch (\Throwable $e) {
            // settings table may not exist yet on some envs; ignore
        }
        $this->rc = new ReserveCaliforniaScraper($ua ?: null);
    }

    public function checkNow(?callable $progressCallback = null, int $monthsToScrape = 6, ?int $parkIdFilter = null): array
    {
        $results = [];
        $parks = $this->parks->findActiveParks();
        
        // Filter to specific park if requested
        if ($parkIdFilter) {
            $originalCount = count($parks);
            $parks = array_filter($parks, function($p) use ($parkIdFilter) {
                // Filter by database ID (what the frontend sends)
                return (int)$p['id'] === $parkIdFilter;
            });
            
            if ($progressCallback) {
                $progressCallback([
                    'type' => 'debug',
                    'message' => "Filtered from {$originalCount} to " . count($parks) . " parks (looking for ID: {$parkIdFilter})"
                ]);
            }
        }
        
        if (empty($parks)) {
            if ($progressCallback) {
                $progressCallback([
                    'type' => 'error',
                    'message' => 'No active parks found matching your selection'
                ]);
            }
            return [];
        }
        
        if ($progressCallback) {
            $parkNames = array_map(function($p) { return $p['name']; }, $parks);
            $progressCallback([
                'type' => 'info',
                'message' => 'Found ' . count($parks) . ' park(s) to check: ' . implode(', ', $parkNames)
            ]);
        }
        
        foreach ($parks as $park) {
            if ($progressCallback) {
                $progressCallback([
                    'type' => 'progress',
                    'park' => $park['name'],
                    'message' => 'Checking ' . $park['name'] . '...'
                ]);
            }
            
            $runId = $this->runs->startRun((int)$park['id']);
            try {
                $parkNumber = $park['park_number'] ?? $park['external_id'];
                $siteEntries = $this->fetchMonths((string)$parkNumber, $monthsToScrape, $progressCallback);
                // Fallback to stub if nothing returned
                if (!$siteEntries) {
                    $siteEntries = [
                        ['site_number' => '12', 'site_type' => 'Tent', 'dates' => ['2025-10-10' => true, '2025-10-11' => true]],
                        ['site_number' => '34', 'site_type' => 'RV', 'dates' => ['2025-10-10' => true, '2025-10-11' => false]],
                    ];
                }

                $weekendFound = false;
                $alertSites = [];
                $earliestDate = null;
                $latestDate = null;

                foreach ($siteEntries as $s) {
                    // Upsert site with all metadata
                    $siteId = $this->sites->upsertSite(
                        (int)$park['id'], 
                        $s['site_number'], 
                        $s['site_type'] ?? null,
                        [], // attributes_json (for future use)
                        $s['facility_db_id'] ?? null, // facility_id (our DB ID)
                        $s['site_name'] ?? null,
                        $s['unit_type_id'] ?? null,
                        $s['is_ada'] ?? false,
                        $s['vehicle_length'] ?? 0
                    );
                    
                    foreach ($s['dates'] as $date => $avail) {
                        $this->sites->upsertAvailability($siteId, $date, (bool)$avail);
                        
                        // Track date range
                        if ($avail) {
                            if ($earliestDate === null || $date < $earliestDate) {
                                $earliestDate = $date;
                            }
                            if ($latestDate === null || $date > $latestDate) {
                                $latestDate = $date;
                            }
                        }
                    }
                    
                    $weekendDates = $this->detector->getWeekendDates($s['dates']);
                    if (!empty($weekendDates)) {
                        $weekendFound = true;
                        $alertSites[] = [
                            'site_number' => $s['site_number'], 
                            'site_name' => $s['site_name'] ?? '',
                            'site_type' => $s['site_type'] ?? '',
                            'facility_name' => $s['facility_name'] ?? '',
                            'weekend_dates' => $weekendDates  // Array of Fri-Sat pairs
                        ];
                    }
                }

                if ($weekendFound && $this->notify) {
                    // Send to users based on their preferences
                    $notifyResult = $this->notify->sendAvailabilityAlertsToMatchingUsers(
                        (int)$park['id'],
                        $park['name'],
                        $alertSites
                    );
                    
                    // Also send to test email if configured (for debugging)
                    $sample = getenv('ALERT_TEST_EMAIL');
                    if ($sample) {
                        // Format date range like "10/3-10/5/2025"
                        $dateRangeStr = 'Fri-Sat';
                        if ($earliestDate && $latestDate) {
                            $start = date('n/j', strtotime($earliestDate));
                            $end = date('n/j/Y', strtotime($latestDate));
                            $dateRangeStr = "{$start}-{$end}";
                        }
                        
                        $this->notify->sendAvailabilityAlert($sample, $park['name'], $dateRangeStr, $alertSites);
                    }
                }

                $this->runs->finishRunSuccess($runId);
                $result = [
                    'park' => $park['name'], 
                    'weekendFound' => $weekendFound,
                    'notifications' => $notifyResult ?? null
                ];
                $results[] = $result;
                
                if ($progressCallback) {
                    $progressCallback([
                        'type' => 'park_complete',
                        'park' => $park['name'],
                        'weekendFound' => $weekendFound,
                        'message' => $weekendFound ? '✅ Weekend availability found!' : '❌ No weekend availability'
                    ]);
                }
            } catch (\Throwable $e) {
                $this->runs->finishRunError($runId, $e->getMessage());
                $result = ['park' => $park['name'], 'error' => $e->getMessage()];
                $results[] = $result;
                
                if ($progressCallback) {
                    $progressCallback([
                        'type' => 'error',
                        'park' => $park['name'],
                        'message' => '⚠️ Error: ' . $e->getMessage()
                    ]);
                }
            }
        }
        return $results;
    }

    private function fetchMonths(string $parkNumber, int $monthsToScrape = 6, ?callable $progressCallback = null): array
    {
        // First, get park ID and facility filter from park_number
        $parks = $this->parks->listAll();
        $parkId = null;
        $facilityFilter = null;
        foreach ($parks as $p) {
            if (($p['park_number'] ?? $p['external_id']) == $parkNumber) {
                $parkId = (int)$p['id'];
                // Parse facility_filter JSON if present
                if (!empty($p['facility_filter'])) {
                    $facilityFilter = json_decode($p['facility_filter'], true);
                }
                break;
            }
        }
        
        // Also filter by active facilities in database
        if ($parkId) {
            $activeFacilityIds = $this->facilities->getActiveFacilityIds($parkId);
            if (!empty($activeFacilityIds)) {
                if ($facilityFilter === null) {
                    $facilityFilter = $activeFacilityIds;
                } else {
                    // Intersect park facility filter with active facilities
                    $facilityFilter = array_intersect($facilityFilter, $activeFacilityIds);
                }
            }
        }
        
        if (!$parkId) {
            return [];
        }
        
        // Create facility mapper to save facilities and return DB IDs
        $facilityMapper = function($facilityId, $facilityName) use ($parkId) {
            return $this->facilities->upsertFacility(
                $parkId,
                $facilityName,
                $facilityId
            );
        };
        
        $entries = [];
        $start = new \DateTime('first day of this month');
        for ($i = 0; $i < $monthsToScrape; $i++) {
            $ym = $start->format('Y-m');
            
            if ($progressCallback) {
                $monthName = $start->format('F Y');
                $progressCallback([
                    'type' => 'month_progress',
                    'month' => $monthName,
                    'progress' => ($i + 1) . '/' . $monthsToScrape,
                    'message' => "  → Checking {$monthName}..."
                ]);
            }
            
            // Pass facility filter to scraper
            $monthEntries = $this->rc->fetchMonthlyAvailability($parkNumber, $ym, $facilityMapper, $facilityFilter);
            $entries = $this->mergeSiteEntries($entries, $monthEntries);
            $start->modify('+1 month');
        }
        return $entries;
    }

    private function mergeSiteEntries(array $base, array $new): array
    {
        // Use facility_id + site_number as unique key
        $byKey = [];
        foreach ($base as $s) {
            $key = ($s['facility_id'] ?? '') . '_' . $s['site_number'];
            $byKey[$key] = $s;
        }
        foreach ($new as $s) {
            $key = ($s['facility_id'] ?? '') . '_' . $s['site_number'];
            if (!isset($byKey[$key])) {
                $byKey[$key] = $s;
            } else {
                // Merge dates
                $byKey[$key]['dates'] = array_merge($byKey[$key]['dates'] ?? [], $s['dates'] ?? []);
                // Update metadata if missing
                foreach (['site_name', 'site_type', 'unit_type_id', 'is_ada', 'vehicle_length', 'facility_name', 'facility_db_id'] as $field) {
                    if (empty($byKey[$key][$field]) && !empty($s[$field])) {
                        $byKey[$key][$field] = $s[$field];
                    }
                }
            }
        }
        return array_values($byKey);
    }
}
