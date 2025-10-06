#!/usr/bin/env php
<?php

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Services\ScraperService;

$svc = new ScraperService();
$results = $svc->checkNow(function($data) {
    // Append minimal logs to file under /var/www/campsite-agent/logs
    $dir = '/var/www/campsite-agent/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . json_encode($data) . "\n";
    @file_put_contents($dir . '/scrape.log', $line, FILE_APPEND);
});

foreach ($results as $r) {
    if (isset($r['error'])) {
        fwrite(STDERR, "{$r['park']}: ERROR {$r['error']}\n");
    } else {
        echo "{$r['park']}: weekendFound=" . ($r['weekendFound'] ? 'yes' : 'no') . "\n";
    }
}
