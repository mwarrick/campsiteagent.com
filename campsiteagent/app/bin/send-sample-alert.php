#!/usr/bin/env php
<?php

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Services\NotificationService;

function usage(): void {
    fwrite(STDERR, "Usage: php bin/send-sample-alert.php recipient@example.com\n");
}

$recipient = $argv[1] ?? null;
if (!$recipient) {
    usage();
    exit(1);
}

$service = new NotificationService();
$park = 'San Onofre State Beach';
$range = 'Fri-Sat';
$sites = [
    ['site_number' => '12', 'site_type' => 'Tent'],
    ['site_number' => '34', 'site_type' => 'RV']
];

try {
    $ok = $service->sendAvailabilityAlert($recipient, $park, $range, $sites);
    if ($ok) {
        echo "Sample alert sent to {$recipient}\n";
        exit(0);
    }
    fwrite(STDERR, "Failed to send sample alert to {$recipient}\n");
    exit(2);
} catch (Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(3);
}
