<?php

namespace CampsiteAgent\Services;

use CampsiteAgent\Repositories\ParkAlertRepository;
use CampsiteAgent\Infrastructure\HttpClient;
use Exception;
use PDO;

class ParkAlertScraperService
{
    private ParkAlertRepository $alertRepository;
    private HttpClient $httpClient;

    public function __construct(ParkAlertRepository $alertRepository, HttpClient $httpClient)
    {
        $this->alertRepository = $alertRepository;
        $this->httpClient = $httpClient;
    }

    /**
     * Scrape alerts for a specific park
     */
    public function scrapeParkAlerts(int $parkId, string $parkWebsiteUrl): array
    {
        $alerts = [];
        
        try {
            // Fetch the park page first
            $response = $this->httpClient->get($parkWebsiteUrl);
            if (!$response || $response[0] !== 200) {
                error_log("Failed to fetch park page: $parkWebsiteUrl (Status: " . ($response[0] ?? 'unknown') . ")");
                return $alerts;
            }
            
            $html = $response[1];
            
            // Parse different types of alerts
            $alerts = array_merge(
                $this->parseCurrentRestrictions($html, $parkId, $parkWebsiteUrl),
                $this->parseAdvisoriesAndNotices($html, $parkId, $parkWebsiteUrl),
                $this->parseParkHours($html, $parkId, $parkWebsiteUrl),
                $this->parseSeasonalClosures($html, $parkId, $parkWebsiteUrl),
                $this->parseSilverwoodLakeAlerts($html, $parkId, $parkWebsiteUrl),
                $this->parseCallToActionAlerts($html, $parkId, $parkWebsiteUrl)
            );
            
            // Track current alert titles for deactivation logic
            $currentAlertTitles = [];
            $today = new \DateTime();
            
            // Process each alert found on the website
            foreach ($alerts as $alert) {
                $currentAlertTitles[] = $alert['title'];
                
                // Check if this alert is expired before processing
                $isExpired = false;
                if (!empty($alert['expiration_date'])) {
                    $expDate = new \DateTime($alert['expiration_date']);
                    if ($expDate < $today) {
                        $isExpired = true;
                    }
                }
                
                // Skip expired alerts
                if ($isExpired) {
                    continue;
                }
                
                // Check if alert already exists (regardless of active status)
                if (!$this->alertRepository->alertExists($parkId, $alert['title'], $alert['alert_type'])) {
                    // New alert - create it
                    $this->alertRepository->createAlert(
                        $parkId,
                        $alert['alert_type'],
                        $alert['severity'],
                        $alert['title'],
                        $alert['description'] ?? null,
                        $alert['effective_date'] ?? null,
                        $alert['expiration_date'] ?? null,
                        $parkWebsiteUrl
                    );
                } else {
                    // Alert exists - make sure it's active
                    if (!$this->alertRepository->alertExistsAndActive($parkId, $alert['title'], $alert['alert_type'])) {
                        $this->alertRepository->reactivateAlert($parkId, $alert['title'], $alert['alert_type']);
                    }
                }
            }
            
            // Deactivate alerts that are no longer on the website (not in current scrape)
            // This handles alerts that were removed from the website
            if (!empty($currentAlertTitles)) {
                $this->alertRepository->deactivateAlertsNotInList($parkId, $currentAlertTitles);
            }
            
            // Also deactivate any expired alerts
            $this->alertRepository->deactivateExpiredAlerts($parkId);
            
        } catch (Exception $e) {
            error_log("Error scraping park alerts for park $parkId: " . $e->getMessage());
        }
        
        return $alerts;
    }

    /**
     * Parse "Current Restrictions" section
     */
    private function parseCurrentRestrictions(string $html, int $parkId, string $sourceUrl): array
    {
        $alerts = [];
        
        // Look for "Current Restrictions" section - handle both h4 and h5 tags
        if (preg_match('/Current Restrictions.*?<\/h[4-6]>(.*?)(?=<h[1-6]|$)/is', $html, $matches)) {
            $restrictionsHtml = $matches[1];
            
            // Extract individual restriction items - look for bold text patterns
            if (preg_match_all('/<strong[^>]*>(.*?)<\/strong>/is', $restrictionsHtml, $strongMatches)) {
                foreach ($strongMatches[1] as $restriction) {
                    $text = strip_tags($restriction);
                    $text = trim($text);
                    
                    // Skip metadata like "Last Checked" and very short text
                    if (!empty($text) && strlen($text) > 20 && 
                        !preg_match('/^(Last Checked|Updated|Modified):/i', $text)) {
                        $alerts[] = [
                            'alert_type' => 'restriction',
                            'severity' => 'critical',
                            'title' => $text,
                            'description' => $text,
                            'source_url' => $sourceUrl
                        ];
                    }
                }
            }
            
            // Also look for paragraph content after Current Restrictions
            if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $restrictionsHtml, $pMatches)) {
                foreach ($pMatches[1] as $restriction) {
                    $text = strip_tags($restriction);
                    $text = trim($text);
                    
                    // Skip metadata like "Last Checked" and very short text
                    if (!empty($text) && strlen($text) > 20 && 
                        !preg_match('/^(Last Checked|Updated|Modified):/i', $text) &&
                        !preg_match('/^Close$/', $text)) {
                        $alerts[] = [
                            'alert_type' => 'restriction',
                            'severity' => 'critical',
                            'title' => $text,
                            'description' => $text,
                            'source_url' => $sourceUrl
                        ];
                    }
                }
            }
        }
        
        return $alerts;
    }

    /**
     * Parse "Advisories and Notices" section
     */
    private function parseAdvisoriesAndNotices(string $html, int $parkId, string $sourceUrl): array
    {
        $alerts = [];
        
        // Look for "Advisories and Notices" section - handle both h4 and h5 tags
        // Also look for "Current Advisories and Notices" variant
        if (preg_match('/(?:Current\s+)?Advisories and Notices.*?<\/h[4-6]>(.*?)(?=<h[1-6]|$)/is', $html, $matches)) {
            $advisoriesHtml = $matches[1];
            
            // Extract advisory items - look for bold text patterns first
            if (preg_match_all('/<strong[^>]*>(.*?)<\/strong>/is', $advisoriesHtml, $strongMatches)) {
                foreach ($strongMatches[1] as $advisory) {
                    $text = strip_tags($advisory);
                    $text = trim($text);
                    
                    if (!empty($text) && strlen($text) > 20 && 
                        !preg_match('/^(Last Checked|Updated|Modified):/i', $text)) {
                        $alerts[] = [
                            'alert_type' => 'advisory',
                            'severity' => 'warning',
                            'title' => $text,
                            'description' => $text,
                            'source_url' => $sourceUrl
                        ];
                    }
                }
            }
            
            // Also look for paragraph content
            if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $advisoriesHtml, $pMatches)) {
                foreach ($pMatches[1] as $advisory) {
                    $text = strip_tags($advisory);
                    $text = trim($text);
                    
                    if (!empty($text) && strlen($text) > 20 && 
                        !preg_match('/^(Last Checked|Updated|Modified):/i', $text) &&
                        !preg_match('/^Close$/', $text)) {
                        $alerts[] = [
                            'alert_type' => 'advisory',
                            'severity' => 'warning',
                            'title' => $text,
                            'description' => $text,
                            'source_url' => $sourceUrl
                        ];
                    }
                }
            }
            
            // Also look for list items (li tags) which might contain alerts
            if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $advisoriesHtml, $liMatches)) {
                foreach ($liMatches[1] as $advisory) {
                    $text = strip_tags($advisory);
                    $text = trim($text);
                    
                    if (!empty($text) && strlen($text) > 20 && 
                        !preg_match('/^(Last Checked|Updated|Modified):/i', $text) &&
                        !preg_match('/^Close$/', $text)) {
                        $alerts[] = [
                            'alert_type' => 'advisory',
                            'severity' => 'warning',
                            'title' => $text,
                            'description' => $text,
                            'source_url' => $sourceUrl
                        ];
                    }
                }
            }
            
            // Also look for div content that might contain alerts (for more flexible HTML structures)
            if (preg_match_all('/<div[^>]*class="[^"]*alert[^"]*"[^>]*>(.*?)<\/div>/is', $advisoriesHtml, $divMatches)) {
                foreach ($divMatches[1] as $advisory) {
                    $text = strip_tags($advisory);
                    $text = trim($text);
                    
                    if (!empty($text) && strlen($text) > 20 && 
                        !preg_match('/^(Last Checked|Updated|Modified):/i', $text) &&
                        !preg_match('/^Close$/', $text)) {
                        $alerts[] = [
                            'alert_type' => 'advisory',
                            'severity' => 'warning',
                            'title' => $text,
                            'description' => $text,
                            'source_url' => $sourceUrl
                        ];
                    }
                }
            }
        }
        
        return $alerts;
    }

    /**
     * Parse "Park Hours" section for operational changes
     */
    private function parseParkHours(string $html, int $parkId, string $sourceUrl): array
    {
        $alerts = [];
        
        // Look for "Park Hours" section
        if (preg_match('/Park Hours.*?<\/h[1-6]>(.*?)(?=<h[1-6]|$)/is', $html, $matches)) {
            $hoursHtml = $matches[1];
            
            // Extract hour information
            if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $hoursHtml, $pMatches)) {
                foreach ($pMatches[1] as $hours) {
                    $text = strip_tags($hours);
                    $text = trim($text);
                    
                    if (!empty($text) && (strpos($text, 'closed') !== false || strpos($text, 'open') !== false)) {
                        $alerts[] = [
                            'alert_type' => 'hours_change',
                            'severity' => 'info',
                            'title' => $text,
                            'description' => $text,
                            'source_url' => $sourceUrl
                        ];
                    }
                }
            }
        }
        
        return $alerts;
    }

    /**
     * Parse seasonal closures and restrictions
     */
    private function parseSeasonalClosures(string $html, int $parkId, string $sourceUrl): array
    {
        $alerts = [];
        
        // Look for seasonal closure patterns - be more specific to avoid duplicates
        if (preg_match_all('/([A-Za-z\s]+(?:closed|Closed)[^<>\n]*(?:June|September|October|November|December|January|February|March|April|May)[^<>\n]*\.)/i', $html, $matches)) {
            $seen = [];
            foreach ($matches[1] as $closure) {
                $text = trim($closure);
                
                // Skip incomplete sentences that start with "and" or "or"
                if (!empty($text) && strlen($text) > 20 && !in_array($text, $seen) && 
                    !preg_match('/^(and|or)\s+/i', $text)) {
                    $seen[] = $text;
                    
                    // Parse dates from the text (e.g., "June 1-September 30" or "June 1 to September 30")
                    $dates = $this->parseDateRangeFromText($text);
                    
                    $alert = [
                        'alert_type' => 'closure',
                        'severity' => 'warning',
                        'title' => $text,
                        'description' => $text,
                        'source_url' => $sourceUrl
                    ];
                    
                    // Add dates if parsed
                    if ($dates) {
                        $alert['effective_date'] = $dates['effective_date'];
                        $alert['expiration_date'] = $dates['expiration_date'];
                    }
                    
                    $alerts[] = $alert;
                }
            }
        }
        
        return $alerts;
    }
    
    /**
     * Parse date range from alert text
     * Examples: 
     *   - "June 1-September 30" (no year, assumes current year)
     *   - "June 1 to September 30" (no year, assumes current year)
     *   - "September 29, 2025 through October 29, 2025" (with years)
     *   - "September 29, 2025, through October 29, 2025" (with comma after year)
     * When no year is specified, assume dates apply to the current year
     */
    private function parseDateRangeFromText(string $text): ?array
    {
        // Pattern to match month day - month day patterns, optionally with years
        // Matches: 
        //   - "June 1-September 30" (no years)
        //   - "September 29, 2025 through October 29, 2025" (with years)
        //   - "September 29, 2025, through October 29, 2025" (with comma after year)
        $pattern = '/(\w+)\s+(\d{1,2})(?:,\s*(\d{4}))?[\s,]*[\s-]+(?:to|through|-)[\s,]*(\w+)\s+(\d{1,2})(?:,\s*(\d{4}))?/i';
        
        if (preg_match($pattern, $text, $matches)) {
            $startMonth = $matches[1];
            $startDay = (int)$matches[2];
            $startYear = !empty($matches[3]) ? (int)$matches[3] : null;
            $endMonth = $matches[4];
            $endDay = (int)$matches[5];
            $endYear = !empty($matches[6]) ? (int)$matches[6] : null;
            
            // Get current year - dates without year always apply to current year
            $currentYear = (int)date('Y');
            $today = new \DateTime();
            
            // Convert month names to numbers
            $monthNames = [
                'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
                'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
                'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12
            ];
            
            $startMonthNum = $monthNames[strtolower($startMonth)] ?? null;
            $endMonthNum = $monthNames[strtolower($endMonth)] ?? null;
            
            if ($startMonthNum && $endMonthNum) {
                // Determine years
                if ($startYear !== null && $endYear !== null) {
                    // Both years specified - use them
                    $finalStartYear = $startYear;
                    $finalEndYear = $endYear;
                } elseif ($startYear !== null) {
                    // Only start year specified - assume end is same year or next year
                    $finalStartYear = $startYear;
                    $finalEndYear = $endMonthNum < $startMonthNum ? $startYear + 1 : $startYear;
                } elseif ($endYear !== null) {
                    // Only end year specified - assume start is same year or previous year
                    $finalEndYear = $endYear;
                    $finalStartYear = $endMonthNum < $startMonthNum ? $endYear - 1 : $endYear;
                } else {
                    // No years specified - use current year logic
                    $finalStartYear = $currentYear;
                    $finalEndYear = $currentYear;
                    
                    if ($endMonthNum < $startMonthNum) {
                        // Cross-year range (e.g., November to February)
                        $finalEndYear = $currentYear + 1;
                    }
                }
                
                // Create date strings
                $effectiveDate = sprintf('%04d-%02d-%02d', $finalStartYear, $startMonthNum, $startDay);
                $expirationDate = sprintf('%04d-%02d-%02d', $finalEndYear, $endMonthNum, $endDay);
                
                // Validate dates
                if (checkdate($startMonthNum, $startDay, $finalStartYear) && 
                    checkdate($endMonthNum, $endDay, $finalEndYear)) {
                    
                    // If the expiration date has already passed, return null to skip creating this alert
                    $expDateObj = new \DateTime($expirationDate);
                    if ($expDateObj < $today) {
                        // Alert is already expired, don't create it
                        return null;
                    }
                    
                    return [
                        'effective_date' => $effectiveDate,
                        'expiration_date' => $expirationDate
                    ];
                }
            }
        }
        
        return null;
    }

    /**
     * Parse Silverwood Lake specific alert patterns
     */
    private function parseSilverwoodLakeAlerts(string $html, int $parkId, string $sourceUrl): array
    {
        $alerts = [];
        
        // Look for specific Silverwood Lake patterns
        
        // 1. Bear activity warning
        if (preg_match('/INCREASED BEAR ACTIVITY IN CAMPGROUNDS.*?Learn more on our Bear Safety page/is', $html, $matches)) {
            $text = strip_tags($matches[0]);
            $text = trim($text);
            if (!empty($text) && strlen($text) > 20) {
                $alerts[] = [
                    'alert_type' => 'restriction',
                    'severity' => 'critical',
                    'title' => 'INCREASED BEAR ACTIVITY IN CAMPGROUNDS',
                    'description' => $text,
                    'source_url' => $sourceUrl
                ];
            }
        }
        
        // 2. Golden Mussel detection
        if (preg_match('/GOLDEN MUSSEL.*?ARE AT SILVERWOOD LAKE.*?new boating protocols have been implemented/is', $html, $matches)) {
            $text = strip_tags($matches[0]);
            $text = trim($text);
            if (!empty($text) && strlen($text) > 20) {
                $alerts[] = [
                    'alert_type' => 'restriction',
                    'severity' => 'warning',
                    'title' => 'GOLDEN MUSSEL DETECTED - New Boating Protocols',
                    'description' => $text,
                    'source_url' => $sourceUrl
                ];
            }
        }
        
        // 3. Salmon Poisoning Disease warning
        if (preg_match('/Salmon Poisoning Disease.*?California State Parks urges dog owners/is', $html, $matches)) {
            $text = strip_tags($matches[0]);
            $text = trim($text);
            if (!empty($text) && strlen($text) > 20) {
                $alerts[] = [
                    'alert_type' => 'advisory',
                    'severity' => 'warning',
                    'title' => 'Salmon Poisoning Disease Warning for Dogs',
                    'description' => $text,
                    'source_url' => $sourceUrl
                ];
            }
        }
        
        // 4. Water Quality and Blue Green Algae
        if (preg_match('/Water Quality and Blue Green Algae Blooms.*?Current Advisory Level.*?No Advisory/is', $html, $matches)) {
            $text = strip_tags($matches[0]);
            $text = trim($text);
            if (!empty($text) && strlen($text) > 20) {
                $alerts[] = [
                    'alert_type' => 'advisory',
                    'severity' => 'info',
                    'title' => 'Water Quality Information - Blue Green Algae',
                    'description' => $text,
                    'source_url' => $sourceUrl
                ];
            }
        }
        
        return $alerts;
    }

    /**
     * Parse call-to-action alerts (e.g., divs with class "call-to-action featured featured-secondary mb-2")
     * These are typically informational alerts displayed prominently on the page
     */
    private function parseCallToActionAlerts(string $html, int $parkId, string $sourceUrl): array
    {
        $alerts = [];
        
        // Look for divs with call-to-action classes
        // Pattern matches: "call-to-action featured featured-secondary mb-2" or similar variations
        // Match divs that contain "call-to-action" in their class attribute
        // Use a more precise pattern to avoid matching nested divs incorrectly
        $pattern = '/<div[^>]*class="[^"]*call-to-action[^"]*"[^>]*>((?:[^<]|<(?!\/div>))*?)<\/div>/is';
        
        if (preg_match_all($pattern, $html, $matches)) {
            foreach ($matches[1] as $ctaContent) {
                // Extract text content, removing HTML tags but preserving structure
                $text = strip_tags($ctaContent);
                $text = trim($text);
                
                // Clean up whitespace (multiple spaces, newlines, etc.)
                $text = preg_replace('/\s+/', ' ', $text);
                $text = trim($text);
                
                // Skip if too short or empty
                if (empty($text) || strlen($text) < 20) {
                    continue;
                }
                
                // Skip if it's just navigation or common UI text
                if (preg_match('/^(Skip to|Menu|Close|×|Back|Next|Previous|Search|Login|Register)$/i', $text)) {
                    continue;
                }
                
                // Skip if it's mostly just whitespace or special characters
                if (preg_match('/^[\s\W]+$/', $text)) {
                    continue;
                }
                
                // Skip if it looks like a button or link text only
                if (preg_match('/^(Click|View|Read|Learn|More|Here|Link)$/i', $text)) {
                    continue;
                }
                
                // Use first 200 characters as title, full text as description
                $title = strlen($text) > 200 ? substr($text, 0, 197) . '...' : $text;
                $description = $text;
                
                $alerts[] = [
                    'alert_type' => 'advisory',
                    'severity' => 'info',
                    'title' => $title,
                    'description' => $description,
                    'source_url' => $sourceUrl
                ];
            }
        }
        
        return $alerts;
    }

    /**
     * Scrape alerts for all parks with website URLs
     */
    public function scrapeAllParkAlerts(): array
    {
        $results = [];
        
        try {
            $pdo = \CampsiteAgent\Infrastructure\Database::getConnection();
            $stmt = $pdo->query('SELECT id, name, website_url FROM parks WHERE website_url IS NOT NULL AND website_url != ""');
            $parks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($parks as $park) {
                // Don't echo - this breaks JSON responses. Use error_log for debugging if needed
                // error_log("Scraping alerts for {$park['name']}...");
                $alerts = $this->scrapeParkAlerts($park['id'], $park['website_url']);
                $results[$park['name']] = count($alerts);
            }
            
        } catch (Exception $e) {
            error_log("Error scraping all park alerts: " . $e->getMessage());
        }
        
        return $results;
    }
}
