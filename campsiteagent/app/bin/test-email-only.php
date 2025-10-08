#!/usr/bin/env php
<?php
/**
 * Test Email Sending Script
 * Sends test availability alerts without doing any scraping
 * 
 * Usage: php test-email-only.php [options]
 * Options:
 *   --to=email@example.com  Email address to send test to (required)
 *   --park="Park Name"      Park name for the test alert (default: "Test Park")
 *   --sites=3               Number of fake sites to include (default: 3)
 *   --verbose               Show detailed output
 */

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Services\NotificationService;

// Parse command line arguments
$options = getopt('', [
    'to:',
    'park::',
    'sites::',
    'verbose',
    'help'
]);

if (isset($options['help'])) {
    echo "Test Email Sending Script\n";
    echo "Usage: php test-email-only.php [options]\n\n";
    echo "Options:\n";
    echo "  --to=email@example.com  Email address to send test to (required)\n";
    echo "  --park=\"Park Name\"      Park name for the test alert (default: \"Test Park\")\n";
    echo "  --sites=3               Number of fake sites to include (default: 3)\n";
    echo "  --verbose               Show detailed output\n";
    echo "  --help                  Show this help message\n";
    exit(0);
}

$toEmail = $options['to'] ?? null;
$parkName = $options['park'] ?? 'Test Park';
$siteCount = isset($options['sites']) ? (int)$options['sites'] : 3;
$verbose = isset($options['verbose']);

if (!$toEmail) {
    echo "❌ Error: --to email address is required\n";
    echo "Usage: php test-email-only.php --to=your@email.com\n";
    exit(1);
}

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
        return;
    }
    
    echo "[$timestamp] $prefix $message\n";
};

$log("Starting email test...", 'info');
$log("Target: $toEmail", 'info');
$log("Park: $parkName", 'info');
$log("Sites: $siteCount", 'info');

try {
    // Check environment
    $credentialsPath = getenv('GOOGLE_CREDENTIALS_JSON');
    if (!$credentialsPath || !file_exists($credentialsPath)) {
        $log("GOOGLE_CREDENTIALS_JSON not set or file not found: " . ($credentialsPath ?: 'not set'), 'error');
        exit(1);
    }
    $log("Gmail credentials found: $credentialsPath", 'debug');
    
    // Create fake availability data
    $fakeSites = [];
    $today = new DateTime();
    $friday = clone $today;
    $saturday = clone $today;
    
    // Find next Friday
    while ($friday->format('N') != 5) {
        $friday->modify('+1 day');
    }
    $saturday = clone $friday;
    $saturday->modify('+1 day');
    
    $dateRange = $friday->format('n/j') . '-' . $saturday->format('n/j/Y');
    
    for ($i = 1; $i <= $siteCount; $i++) {
        $fakeSites[] = [
            'site_number' => (string)(10 + $i),
            'site_name' => "Test Site $i",
            'site_type' => $i % 2 ? 'Tent' : 'RV',
            'facility_name' => 'Test Facility',
            'weekend_dates' => [
                [
                    'fri' => $friday->format('Y-m-d'),
                    'sat' => $saturday->format('Y-m-d')
                ]
            ]
        ];
    }
    
    $log("Created $siteCount fake sites for $dateRange", 'info');
    if ($verbose) {
        foreach ($fakeSites as $site) {
            $log("  - Site {$site['site_number']} ({$site['site_type']})", 'debug');
        }
    }
    
    // Initialize notification service
    $log("Initializing notification service...", 'info');
    $notify = new NotificationService();
    
    // Send test email
    $log("Sending test email...", 'info');
    $success = $notify->sendAvailabilityAlert($toEmail, $parkName, $dateRange, $fakeSites, [], null, 'https://www.parks.ca.gov/?page_id=123');
    
    if ($success) {
        $log("✅ Test email sent successfully!", 'success');
        $log("Check your inbox for the test alert", 'info');
    } else {
        $log("❌ Failed to send test email", 'error');
        exit(1);
    }
    
} catch (\Throwable $e) {
    $log("Fatal error: " . $e->getMessage(), 'error');
    $log("File: " . $e->getFile() . ":" . $e->getLine(), 'error');
    if ($verbose) {
        $log("Stack trace: " . $e->getTraceAsString(), 'debug');
    }
    exit(1);
}

$log("Email test completed successfully", 'success');
exit(0);
