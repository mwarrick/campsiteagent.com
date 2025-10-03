#!/usr/bin/env php
<?php
/**
 * Daily Automated Scraping Script
 * Runs at 6 AM daily to check availability for all active parks
 * 
 * Usage: php daily-scrape.php [options]
 * Options:
 *   --months=6     Number of months to scrape (default: 6)
 *   --weekend-only Only check weekend availability (default: true)
 *   --parks=1,2,3  Comma-separated list of park IDs (default: all active)
 *   --dry-run      Show what would be scraped without actually scraping
 *   --verbose      Show detailed output
 */

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Services\ScraperService;
use CampsiteAgent\Repositories\ParkRepository;
use CampsiteAgent\Repositories\RunRepository;

// Parse command line arguments
$options = getopt('', [
    'months::',
    'weekend-only::',
    'parks::',
    'dry-run',
    'verbose',
    'help'
]);

if (isset($options['help'])) {
    echo "Daily Automated Scraping Script\n";
    echo "Usage: php daily-scrape.php [options]\n\n";
    echo "Options:\n";
    echo "  --months=6        Number of months to scrape (default: 6)\n";
    echo "  --weekend-only=1  Only check weekend availability (default: 1)\n";
    echo "  --parks=1,2,3     Comma-separated list of park IDs (default: all active)\n";
    echo "  --dry-run         Show what would be scraped without actually scraping\n";
    echo "  --verbose         Show detailed output\n";
    echo "  --help            Show this help message\n";
    exit(0);
}

// Configuration
$monthsToScrape = isset($options['months']) ? (int)$options['months'] : 6;
$weekendOnly = !isset($options['weekend-only']) || $options['weekend-only'] !== '0';
$parkIds = isset($options['parks']) ? array_map('intval', explode(',', $options['parks'])) : null;
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);

// Logging function
$log = function($message, $type = 'info') use ($verbose) {
    $timestamp = date('Y-m-d H:i:s');
    $prefix = match($type) {
        'error' => '❌',
        'warning' => '⚠️',
        'success' => '✅',
        'info' => 'ℹ️',
        'debug' => '🔍',
        default => '📝'
    };
    
    if ($type === 'debug' && !$verbose) {
        return; // Skip debug messages unless verbose mode
    }
    
    echo "[$timestamp] $prefix $message\n";
};

$log("Starting daily automated scraping...", 'info');
$log("Configuration: months=$monthsToScrape, weekend-only=" . ($weekendOnly ? 'yes' : 'no'), 'info');

try {
    // Initialize services
    $parkRepo = new ParkRepository();
    $runRepo = new RunRepository();
    $scraper = new ScraperService(false); // Disable notifications for automated runs
    
    // Get parks to scrape
    if ($parkIds) {
        $parks = [];
        foreach ($parkIds as $id) {
            $park = $parkRepo->findById($id);
            if ($park && $park['active']) {
                $parks[] = $park;
            } else {
                $log("Park ID $id not found or inactive", 'warning');
            }
        }
    } else {
        $parks = $parkRepo->findActiveParks();
    }
    
    if (empty($parks)) {
        $log("No active parks found to scrape", 'warning');
        exit(1);
    }
    
    $log("Found " . count($parks) . " park(s) to scrape:", 'info');
    foreach ($parks as $park) {
        $log("  - {$park['name']} (ID: {$park['id']})", 'info');
    }
    
    if ($dryRun) {
        $log("DRY RUN: Would scrape " . count($parks) . " parks for $monthsToScrape months", 'info');
        $log("DRY RUN: Weekend only: " . ($weekendOnly ? 'yes' : 'no'), 'info');
        exit(0);
    }
    
    // Create a run record
    $runId = $runRepo->createDailyRun([
        'type' => 'daily_automated',
        'months' => $monthsToScrape,
        'weekend_only' => $weekendOnly,
        'park_ids' => $parkIds,
        'park_count' => count($parks)
    ]);
    
    $log("Created run record with ID: $runId", 'info');
    
    // Progress callback for scraping
    $progressCallback = function($data) use ($log) {
        $message = $data['message'] ?? $data['type'] ?? 'Unknown';
        $type = match($data['type'] ?? 'info') {
            'error' => 'error',
            'warning' => 'warning',
            'debug' => 'debug',
            'completed' => 'success',
            default => 'info'
        };
        $log($message, $type);
    };
    
    // Start scraping
    $log("Starting scraping process...", 'info');
    $startTime = microtime(true);
    
    $results = $scraper->checkNow($progressCallback, $monthsToScrape, null);
    
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    // Update run record
    $runRepo->updateDailyRun($runId, [
        'status' => 'completed',
        'finished_at' => date('Y-m-d H:i:s'),
        'error' => null
    ]);
    
    $log("Scraping completed successfully in {$duration}s", 'success');
    $log("Results: " . json_encode($results, JSON_PRETTY_PRINT), 'info');
    
    // Summary
    $totalSites = 0;
    $totalParks = 0;
    foreach ($results as $result) {
        if (isset($result['sites']) && is_array($result['sites'])) {
            $totalSites += count($result['sites']);
            $totalParks++;
        }
    }
    
    $log("Summary: Found $totalSites available sites across $totalParks parks", 'success');
    
} catch (\Throwable $e) {
    $log("Fatal error: " . $e->getMessage(), 'error');
    $log("File: " . $e->getFile() . ":" . $e->getLine(), 'error');
    $log("Stack trace: " . $e->getTraceAsString(), 'debug');
    
    // Update run record if it exists
    if (isset($runId)) {
        try {
            $runRepo->updateDailyRun($runId, [
                'status' => 'error',
                'finished_at' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage()
            ]);
        } catch (\Throwable $updateError) {
            $log("Failed to update run record: " . $updateError->getMessage(), 'error');
        }
    }
    
    exit(1);
}

$log("Daily scraping completed successfully", 'success');
exit(0);
