#!/usr/bin/env php
<?php
/**
 * Test Daily Scraping Script
 * Quick test of the daily scrape functionality with limited scope
 * 
 * Usage: php test-daily-scrape.php [options]
 * Options:
 *   --parks=1,2,3  Comma-separated list of park IDs (default: first 2 active parks)
 *   --months=1     Number of months to scrape (default: 1)
 *   --send-emails  Enable email notifications (default: disabled for testing)
 *   --verbose      Show detailed output
 */

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Services\ScraperService;
use CampsiteAgent\Repositories\ParkRepository;
use CampsiteAgent\Repositories\RunRepository;

// Parse command line arguments
$options = getopt('', [
    'parks::',
    'months::',
    'send-emails',
    'verbose',
    'help'
]);

if (isset($options['help'])) {
    echo "Test Daily Scraping Script\n";
    echo "Usage: php test-daily-scrape.php [options]\n\n";
    echo "Options:\n";
    echo "  --parks=1,2,3     Comma-separated list of park IDs (default: first 2 active parks)\n";
    echo "  --months=1        Number of months to scrape (default: 1)\n";
    echo "  --send-emails     Enable email notifications (default: disabled for testing)\n";
    echo "  --verbose         Show detailed output\n";
    echo "  --help            Show this help message\n";
    exit(0);
}

// Configuration - limited scope for testing
$monthsToScrape = isset($options['months']) ? (int)$options['months'] : 1;
$parkIds = isset($options['parks']) ? array_map('intval', explode(',', $options['parks'])) : null;
$sendEmails = isset($options['send-emails']);
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

$log("🧪 Starting TEST daily scraping (limited scope)...", 'info');
$log("Configuration: months=$monthsToScrape, send-emails=" . ($sendEmails ? 'yes' : 'no'), 'info');

try {
    // Initialize services
    $parkRepo = new ParkRepository();
    $runRepo = new RunRepository();
    $scraper = new ScraperService($sendEmails); // Enable/disable notifications based on flag
    
    // Get parks to scrape (limited for testing)
    if ($parkIds) {
        $parks = [];
        $allParks = $parkRepo->listAll();
        foreach ($parkIds as $id) {
            $park = null;
            foreach ($allParks as $p) {
                if ($p['id'] == $id) {
                    $park = $p;
                    break;
                }
            }
            if ($park && $park['active']) {
                $parks[] = $park;
            } else {
                $log("Park ID $id not found or inactive", 'warning');
            }
        }
    } else {
        // Default: get first 2 active parks for testing
        $allParks = $parkRepo->findActiveParks();
        $parks = array_slice($allParks, 0, 2);
        $log("Using first 2 active parks for testing", 'info');
    }
    
    if (empty($parks)) {
        $log("No active parks found to scrape", 'warning');
        exit(1);
    }
    
    $log("Found " . count($parks) . " park(s) to test:", 'info');
    foreach ($parks as $park) {
        $log("  - {$park['name']} (ID: {$park['id']})", 'info');
    }
    
    // Create a test run record
    $runId = $runRepo->createDailyRun([
        'type' => 'test_limited',
        'months' => $monthsToScrape,
        'weekend_only' => true,
        'park_ids' => array_column($parks, 'id'),
        'park_count' => count($parks)
    ]);
    
    $log("Created TEST run record with ID: $runId", 'info');
    
    // Progress callback for scraping
    $progressCallback = function($data) use ($log) {
        $message = $data['message'] ?? $data['type'] ?? 'Unknown';
        $type = match($data['type'] ?? 'info') {
            'error' => 'error',
            'warning' => 'warning',
            'debug' => 'debug',
            'completed' => 'success',
            'park_complete' => 'success',
            default => 'info'
        };
        $log($message, $type);
    };
    
    // Start scraping - only scrape the specific parks we want to test
    $log("Starting TEST scraping process...", 'info');
    $startTime = microtime(true);
    
    $results = [];
    foreach ($parks as $park) {
        $log("Testing park: {$park['name']} (ID: {$park['id']})", 'info');
        $parkResults = $scraper->checkNow($progressCallback, $monthsToScrape, $park['id']);
        $results = array_merge($results, $parkResults);
    }
    
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    // Update run record with 'success' status (this is what we're testing!)
    $log("Updating run record with status='success'...", 'info');
    $runRepo->updateDailyRun($runId, [
        'status' => 'success',
        'finished_at' => date('Y-m-d H:i:s'),
        'error' => null
    ]);
    
    $log("✅ Run record updated successfully!", 'success');
    $log("TEST scraping completed successfully in {$duration}s", 'success');
    
    // Summary
    $totalSites = 0;
    $totalParks = 0;
    foreach ($results as $result) {
        if (isset($result['sites']) && is_array($result['sites'])) {
            $totalSites += count($result['sites']);
            $totalParks++;
        }
    }
    
    $log("TEST Summary: Found $totalSites available sites across $totalParks parks", 'success');
    $log("✅ Database status update test PASSED - no truncation errors!", 'success');
    
} catch (\Throwable $e) {
    $log("❌ TEST FAILED: " . $e->getMessage(), 'error');
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

$log("🎉 TEST daily scraping completed successfully", 'success');
$log("The status='success' fix is working correctly!", 'success');
exit(0);
