#!/usr/bin/env php
<?php
/**
 * Test Gmail API Configuration
 * Checks if Gmail API is properly configured and can send emails
 */

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Services\GmailApiService;
use CampsiteAgent\Services\NotificationService;

echo "🧪 Testing Gmail API Configuration...\n\n";

// Check environment variables
echo "1. Environment Variables:\n";
$credentialsPath = getenv('GOOGLE_CREDENTIALS_JSON');
echo "   GOOGLE_CREDENTIALS_JSON: " . ($credentialsPath ?: 'NOT SET') . "\n";

if ($credentialsPath) {
    echo "   Credentials file exists: " . (file_exists($credentialsPath) ? 'YES' : 'NO') . "\n";
    if (file_exists($credentialsPath)) {
        echo "   File size: " . filesize($credentialsPath) . " bytes\n";
        echo "   File readable: " . (is_readable($credentialsPath) ? 'YES' : 'NO') . "\n";
    }
}

echo "\n2. Testing Gmail API Service:\n";
try {
    $gmail = new GmailApiService();
    echo "   ✅ GmailApiService initialized successfully\n";
    
    // Try to get the service (this will test authentication)
    $service = $gmail->getService();
    echo "   ✅ Gmail API service created successfully\n";
    
} catch (\Throwable $e) {
    echo "   ❌ Gmail API Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n3. Testing Notification Service:\n";
try {
    $notify = new NotificationService();
    echo "   ✅ NotificationService initialized successfully\n";
    
} catch (\Throwable $e) {
    echo "   ❌ NotificationService Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n4. Testing Email Sending:\n";
$testEmail = getenv('ALERT_TEST_EMAIL') ?: 'test@example.com';
echo "   Test email: $testEmail\n";

try {
    if (isset($notify)) {
        $success = $notify->sendAvailabilityAlert(
            $testEmail,
            'Test Park',
            'Test Date Range',
            [
                [
                    'site_id' => 1,
                    'site_number' => '001',
                    'site_name' => 'Test Site',
                    'site_type' => 'Tent',
                    'facility_name' => 'Test Facility',
                    'park_name' => 'Test Park',
                    'park_number' => '123',
                    'weekend_dates' => [
                        ['fri' => '2025-12-13', 'sat' => '2025-12-14']
                    ]
                ]
            ],
            [],
            1, // Test user ID
            'https://www.parks.ca.gov/?page_id=123' // Test park website URL
        );
        
        if ($success) {
            echo "   ✅ Test email sent successfully!\n";
        } else {
            echo "   ❌ Test email failed to send\n";
        }
    }
} catch (\Throwable $e) {
    echo "   ❌ Email sending error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n5. Database Email Logs:\n";
try {
    $pdo = \CampsiteAgent\Infrastructure\Database::getConnection();
    $stmt = $pdo->query("SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 5");
    $logs = $stmt->fetchAll();
    
    if (empty($logs)) {
        echo "   No email logs found\n";
    } else {
        echo "   Recent email logs:\n";
        foreach ($logs as $log) {
            $status = $log['status'] === 'sent' ? '✅' : '❌';
            echo "   $status {$log['to_email']} - {$log['subject']} ({$log['status']}) - {$log['created_at']}\n";
            if ($log['error']) {
                echo "      Error: {$log['error']}\n";
            }
        }
    }
} catch (\Throwable $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n🎯 Summary:\n";
echo "If you see ❌ errors above, those need to be fixed for emails to work.\n";
echo "If everything shows ✅, then emails should be working.\n";
