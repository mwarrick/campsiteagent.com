#!/usr/bin/env php
<?php

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Services\GmailApiService;

function usage(): void {
    fwrite(STDERR, "Usage: php bin/send-test-gmail-api.php recipient@example.com\n");
}

$recipient = $argv[1] ?? null;
if (!$recipient) {
    usage();
    exit(1);
}

$service = new GmailApiService();
$subject = 'Campsite Agent Gmail API Test';
$html = '<p>This is a test email sent via the Gmail API.</p>';
$text = 'This is a test email sent via the Gmail API.';

try {
    $ok = $service->send($recipient, $subject, $html, $text);
    if ($ok) {
        echo "Email sent successfully to {$recipient} via Gmail API\n";
        exit(0);
    }
    fwrite(STDERR, "Failed to send email to {$recipient} via Gmail API\n");
    exit(2);
} catch (Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(3);
}
