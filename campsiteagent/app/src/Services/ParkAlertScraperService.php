<?php

namespace CampsiteAgent\Services;

use CampsiteAgent\Repositories\ParkAlertRepository;
use CampsiteAgent\Infrastructure\HttpClient;

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
            // Deactivate old alerts first
            $this->alertRepository->deactivateOldAlerts($parkId);
            
            // Fetch the park page
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
                $this->parseSilverwoodLakeAlerts($html, $parkId, $parkWebsiteUrl)
            );
            
            // Save alerts to database
            foreach ($alerts as $alert) {
                if (!$this->alertRepository->alertExists($parkId, $alert['title'], $alert['alert_type'])) {
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
                }
            }
            
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
        if (preg_match('/Advisories and Notices.*?<\/h[4-6]>(.*?)(?=<h[1-6]|$)/is', $html, $matches)) {
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
     * Examples: "June 1-September 30", "June 1 to September 30", "closed June 1-September 30"
     */
    private function parseDateRangeFromText(string $text): ?array
    {
        // Pattern to match month day - month day patterns
        // Matches: "June 1-September 30", "June 1 to September 30", "June 1 through September 30"
        if (preg_match('/(\w+)\s+(\d{1,2})[\s-]+(?:to|through|-)\s+(\w+)\s+(\d{1,2})/i', $text, $matches)) {
            $startMonth = $matches[1];
            $startDay = (int)$matches[2];
            $endMonth = $matches[3];
            $endDay = (int)$matches[4];
            
            // Get current year
            $currentYear = (int)date('Y');
            
            // Convert month names to numbers
            $monthNames = [
                'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
                'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
                'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12
            ];
            
            $startMonthNum = $monthNames[strtolower($startMonth)] ?? null;
            $endMonthNum = $monthNames[strtolower($endMonth)] ?? null;
            
            if ($startMonthNum && $endMonthNum) {
                // Determine year - if end month is before start month, end is next year
                $endYear = $currentYear;
                if ($endMonthNum < $startMonthNum) {
                    $endYear = $currentYear + 1;
                }
                
                // Create date strings
                $effectiveDate = sprintf('%04d-%02d-%02d', $currentYear, $startMonthNum, $startDay);
                $expirationDate = sprintf('%04d-%02d-%02d', $endYear, $endMonthNum, $endDay);
                
                // Validate dates
                if (checkdate($startMonthNum, $startDay, $currentYear) && 
                    checkdate($endMonthNum, $endDay, $endYear)) {
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
                echo "Scraping alerts for {$park['name']}...\n";
                $alerts = $this->scrapeParkAlerts($park['id'], $park['website_url']);
                $results[$park['name']] = count($alerts);
            }
            
        } catch (Exception $e) {
            error_log("Error scraping all park alerts: " . $e->getMessage());
        }
        
        return $results;
    }
}
