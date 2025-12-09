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
                if ($progressCallback) {
                    $progressCallback([
                        'type' => 'debug',
                        'message' => "🔍 Park: {$park['name']} | DB ID: {$park['id']} | Park Number: {$parkNumber} | External ID: " . ($park['external_id'] ?? 'N/A')
                    ]);
                }
                $siteEntries = $this->fetchMonths((string)$parkNumber, $monthsToScrape, $progressCallback);
                // No fallback stub data - if scraping fails, return empty array

                $weekendFound = false;
                $alertSites = [];
                $earliestDate = null;
                $latestDate = null;
                $availableSitesCount = 0; // Track sites with at least one available date
                $sitesWritten = 0; // Track how many sites we've successfully written

                // IMPORTANT: With PDO autocommit ON (default), each upsertSite/upsertAvailability 
                // commits immediately. So sites are saved to DB as they're processed, not batched.
                // If the process crashes, already-written sites remain in the database.
                foreach ($siteEntries as $s) {
                    // Validate facility_db_id before creating site
                    $facilityDbId = $s['facility_db_id'] ?? null;
                    if ($facilityDbId === null) {
                        error_log("ScraperService: Skipping site {$s['site_number']} in park {$park['name']} - no facility_db_id");
                        continue;
                    }
                    
                    try {
                        // Upsert site with all metadata
                        // NOTE: This commits immediately (PDO autocommit ON by default)
                        $siteId = $this->sites->upsertSite(
                            (int)$park['id'], 
                            $s['site_number'], 
                            $s['site_type'] ?? null,
                            [], // attributes_json (for future use)
                            $facilityDbId, // facility_id (our DB ID)
                            $s['site_name'] ?? null,
                            $s['unit_type_id'] ?? null,
                            $s['is_ada'] ?? false,
                            $s['vehicle_length'] ?? 0,
                            $s['site_number'], // external_site_id (API site number)
                            $s['unit_type_id'] ? (string)$s['unit_type_id'] : null, // external_unit_type_id
                            $s['facility_id'] // external_facility_id (API facility ID)
                        );
                        $sitesWritten++;
                    } catch (\Throwable $e) {
                        $errorMsg = "Failed to upsert site {$s['site_number']} in park {$park['name']}: " . $e->getMessage();
                        error_log("ScraperService: " . $errorMsg);
                        if ($progressCallback) {
                            $progressCallback([
                                'type' => 'error',
                                'message' => "⚠️ " . $errorMsg
                            ]);
                        }
                        continue; // Skip this site and continue with next
                    }
                    
                    $hasAvailableDates = false;
                    $datesWritten = 0;
                    foreach ($s['dates'] as $date => $avail) {
                        try {
                            // NOTE: This commits immediately (PDO autocommit ON by default)
                            $this->sites->upsertAvailability($siteId, $date, (bool)$avail);
                            $datesWritten++;
                            
                            // Track date range and availability
                            if ($avail) {
                                $hasAvailableDates = true;
                                if ($earliestDate === null || $date < $earliestDate) {
                                    $earliestDate = $date;
                                }
                                if ($latestDate === null || $date > $latestDate) {
                                    $latestDate = $date;
                                }
                            }
                        } catch (\Throwable $e) {
                            $errorMsg = "Failed to upsert availability for site {$s['site_number']} date {$date}: " . $e->getMessage();
                            error_log("ScraperService: " . $errorMsg);
                            if ($progressCallback) {
                                $progressCallback([
                                    'type' => 'error',
                                    'message' => "⚠️ " . $errorMsg
                                ]);
                            }
                            // Continue with next date
                        }
                    }
                    
                    // Count sites with at least one available date
                    if ($hasAvailableDates) {
                        $availableSitesCount++;
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

                // Report available sites count
                if ($progressCallback && !empty($siteEntries)) {
                    $progressCallback([
                        'type' => 'info',
                        'message' => "📊 Found {$availableSitesCount} site(s) with available dates for {$park['name']} (wrote {$sitesWritten} site(s) to database)"
                    ]);
                }

                // Reconcile: mark dates in the selected window that disappeared as unavailable
                if (!empty($siteEntries)) {
                    // Determine the scraped date window across all entries
                    $minScrapedDate = null;
                    $maxScrapedDate = null;
                    foreach ($siteEntries as $se) {
                        foreach ($se['dates'] as $d => $avail) {
                            if ($minScrapedDate === null || $d < $minScrapedDate) { $minScrapedDate = $d; }
                            if ($maxScrapedDate === null || $d > $maxScrapedDate) { $maxScrapedDate = $d; }
                        }
                    }

                    if ($minScrapedDate && $maxScrapedDate) {
                        $totalReconciled = 0;
                        // For each site we just processed, compute its now-available dates and reconcile
                        foreach ($siteEntries as $se) {
                            // Validate facility_db_id before creating site
                            $facilityDbId = $se['facility_db_id'] ?? null;
                            if ($facilityDbId === null) {
                                error_log("ScraperService: Skipping reconciliation for site {$se['site_number']} in park {$park['name']} - no facility_db_id");
                                continue;
                            }
                            
                            try {
                                $siteIdForRecon = $this->sites->upsertSite(
                                    (int)$park['id'],
                                    $se['site_number'],
                                    $se['site_type'] ?? null,
                                    [],
                                    $facilityDbId,
                                    $se['site_name'] ?? null,
                                    $se['unit_type_id'] ?? null,
                                    $se['is_ada'] ?? false,
                                    $se['vehicle_length'] ?? 0,
                                    $se['site_number'], // external_site_id (API site number)
                                    $se['unit_type_id'] ? (string)$se['unit_type_id'] : null, // external_unit_type_id
                                    $se['facility_id'] // external_facility_id (API facility ID)
                                );
                                // Build list of available dates from the latest scrape for this site
                                $nowAvailable = [];
                                foreach ($se['dates'] as $d => $avail) {
                                    if ($avail) { $nowAvailable[] = $d; }
                                }
                                $totalReconciled += $this->sites->reconcileUnavailableDates($siteIdForRecon, $minScrapedDate, $maxScrapedDate, $nowAvailable);
                            } catch (\Throwable $e) {
                                $errorMsg = "Failed to reconcile site {$se['site_number']} in park {$park['name']}: " . $e->getMessage();
                                error_log("ScraperService: " . $errorMsg);
                                if ($progressCallback) {
                                    $progressCallback([
                                        'type' => 'error',
                                        'message' => "⚠️ " . $errorMsg
                                    ]);
                                }
                                // Continue with next site
                            }
                        }

                        if ($progressCallback) {
                            $progressCallback([
                                'type' => 'debug',
                                'message' => "♻️ Reconciled {$totalReconciled} stale date(s) for {$park['name']} between {$minScrapedDate} and {$maxScrapedDate}"
                            ]);
                        }
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
                        
                        $this->notify->sendAvailabilityAlert($sample, $park['name'], $dateRangeStr, $alertSites, [], null, 'https://www.parks.ca.gov/?page_id=123');
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
                    $weekendSitesCount = count($alertSites);
                    $message = $weekendFound 
                        ? "✅ Weekend availability found! ({$weekendSitesCount} site(s) with weekend availability)" 
                        : "❌ No weekend availability ({$availableSitesCount} site(s) with available dates, but none for weekends)";
                    $progressCallback([
                        'type' => 'park_complete',
                        'park' => $park['name'],
                        'weekendFound' => $weekendFound,
                        'availableSites' => $availableSitesCount,
                        'weekendSites' => $weekendSitesCount,
                        'message' => $message
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
            // Validate inputs
            if (empty($facilityName)) {
                error_log("ScraperService: Empty facility name for facility_id: " . ($facilityId ?: 'null'));
                return null;
            }
            
            try {
                $dbId = $this->facilities->upsertFacility(
                    $parkId,
                    $facilityName,
                    $facilityId,
                    null, // description
                    $facilityId // external_facility_id (same as facility_id for now)
                );
                
                if ($dbId === null || $dbId <= 0) {
                    error_log("ScraperService: upsertFacility returned invalid ID for facility: {$facilityName} (ID: {$facilityId})");
                    return null;
                }
                
                return $dbId;
            } catch (Exception $e) {
                error_log("ScraperService: Failed to upsert facility {$facilityName} (ID: {$facilityId}): " . $e->getMessage());
                return null;
            }
        };
        
        $entries = [];
        // Start from first day of current month (use today's date to ensure we get current month)
        $today = new \DateTime();
        $start = new \DateTime($today->format('Y-m-01')); // First day of current month

        // Only apply the "6 months from today" logic if monthsToScrape is 6 (default/all)
        // Otherwise, respect the user's specific date range selection
        $actualMonthsToScrape = $monthsToScrape;
        if ($monthsToScrape >= 6) {
            // Calculate target: ensure we cover at least the month that contains "6 months from today"
            $today = new \DateTime();
            $sixMonthsFromToday = clone $today;
            $sixMonthsFromToday->modify('+6 months');
            $targetMonth = $sixMonthsFromToday->format('Y-m');

            // Calculate how many months we need to scrape to reach the target month
            $currentMonth = $start->format('Y-m');
            $monthsNeeded = 0;
            $checkMonth = clone $start;
            while ($checkMonth->format('Y-m') < $targetMonth) {
                $monthsNeeded++;
                $checkMonth->modify('+1 month');
            }
            // Add one more month to ensure we include the target month
            $monthsNeeded++;

            // Use the larger of: requested months or months needed to reach target
            $actualMonthsToScrape = max($monthsToScrape, $monthsNeeded);
        }
        
        for ($i = 0; $i < $actualMonthsToScrape; $i++) {
            $ym = $start->format('Y-m');
            
            if ($progressCallback) {
                $monthName = $start->format('F Y');
                $progressCallback([
                    'type' => 'month_progress',
                    'month' => $monthName,
                    'progress' => ($i + 1) . '/' . $actualMonthsToScrape,
                    'message' => "  → Checking {$monthName}..."
                ]);
            }
            
            // Pass facility filter to scraper with debug callback
            $debugCallback = function($message) use ($progressCallback) {
                if ($progressCallback) {
                    $progressCallback([
                        'type' => 'debug',
                        'message' => $message
                    ]);
                }
            };
            $monthEntries = $this->rc->fetchMonthlyAvailability($parkNumber, $ym, $facilityMapper, $facilityFilter, $debugCallback);
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
