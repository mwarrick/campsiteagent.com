#!/usr/bin/env php
<?php

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Services\ScraperService;

$svc = new ScraperService();
$results = $svc->checkNow();

foreach ($results as $r) {
    if (isset($r['error'])) {
        fwrite(STDERR, "{$r['park']}: ERROR {$r['error']}\n");
    } else {
        echo "{$r['park']}: weekendFound=" . ($r['weekendFound'] ? 'yes' : 'no') . "\n";
    }
}
