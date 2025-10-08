#!/usr/bin/env php
<?php

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Services\ScraperService;

// Check for dry-run mode
$dryRun = in_array('--dry-run', $argv) || in_array('-d', $argv);

if ($dryRun) {
    echo "🔍 DRY RUN MODE: Building emails from existing database data (no scraping)\n\n";
    
    // Use the existing daily-run endpoint logic to build emails from DB
    $pdo = \CampsiteAgent\Infrastructure\Database::getConnection();
    $notify = new \CampsiteAgent\Services\NotificationService();
    $favRepo = new \CampsiteAgent\Repositories\UserFavoritesRepository();

    // Get all users with enabled preferences
    $users = $pdo->query('SELECT DISTINCT u.id, u.email, u.first_name
                          FROM users u
                          JOIN user_alert_preferences p ON p.user_id = u.id AND p.enabled = 1
                          WHERE u.verified_at IS NOT NULL AND (u.active IS NULL OR u.active = 1)')->fetchAll();

    $sent = 0; $failed = 0; $skipped = 0; $details = [];

    foreach ($users as $user) {
        $userId = (int)$user['id'];
        echo "Processing user: {$user['email']}...\n";

        // Load preferences
        $st = $pdo->prepare('SELECT * FROM user_alert_preferences WHERE user_id = :uid AND enabled = 1');
        $st->execute([':uid' => $userId]);
        $prefs = $st->fetchAll();
        if (empty($prefs)) { 
            echo "  ⏭️  No enabled preferences\n";
            $skipped++; 
            continue; 
        }

        $userAlertSites = [];
        $allFridays = [];

        foreach ($prefs as $pref) {
            $parkFilterSql = '';
            $params = [];
            if (!empty($pref['park_id'])) {
                $parkFilterSql = ' AND p.id = :parkId';
                $params[':parkId'] = (int)$pref['park_id'];
            }

            // Get existing availability from database
            $sql = 'SELECT p.id AS park_id, p.name AS park_name, p.park_number,
                           s.id AS site_id, s.site_number, s.site_name, s.site_type,
                           f.name AS facility_name, a.date
                    FROM parks p
                    JOIN sites s ON s.park_id = p.id
                    LEFT JOIN facilities f ON s.facility_id = f.id
                    JOIN site_availability a ON a.site_id = s.id
                    WHERE a.is_available = 1 AND a.date >= CURDATE()' . $parkFilterSql . '
                    ORDER BY p.name, a.date ASC, s.site_number ASC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            // Group by park/site
            $byPark = [];
            $parkNumberByName = [];
            foreach ($rows as $r) {
                $parkName = $r['park_name'];
                if (!isset($parkNumberByName[$parkName]) && !empty($r['park_number'])) {
                    $parkNumberByName[$parkName] = $r['park_number'];
                }
                $key = $r['site_number'] . '|' . ($r['site_name'] ?? '') . '|' . ($r['site_type'] ?? '');
                if (!isset($byPark[$parkName])) { $byPark[$parkName] = []; }
                if (!isset($byPark[$parkName][$key])) {
                    $byPark[$parkName][$key] = [
                        'site_id' => (int)$r['site_id'],
                        'site_number' => $r['site_number'],
                        'site_name' => $r['site_name'],
                        'site_type' => $r['site_type'],
                        'facility_name' => $r['facility_name'],
                        'dates' => []
                    ];
                }
                $byPark[$parkName][$key]['dates'][] = $r['date'];
            }

            $startDate = $pref['start_date'] ?? null;
            $endDate = $pref['end_date'] ?? null;
            $weekendOnlyPref = isset($pref['weekend_only']) ? ((int)$pref['weekend_only'] === 1) : true;

            foreach ($byPark as $parkName => $sites) {
                foreach ($sites as $s) {
                    $dateSet = array_fill_keys($s['dates'], true);
                    $weekendDates = [];
                    if ($weekendOnlyPref) {
                        foreach ($s['dates'] as $d) {
                            $ts = strtotime($d);
                            if ((int)date('w', $ts) === 5) {
                                $fri = date('Y-m-d', $ts);
                                $sat = date('Y-m-d', $ts + 86400);
                                if (isset($dateSet[$sat])) {
                                    if ($startDate && $fri < $startDate) continue;
                                    if ($endDate && $fri > $endDate) continue;
                                    $weekendDates[] = ['fri' => $fri, 'sat' => $sat];
                                    $allFridays[] = $fri;
                                }
                            }
                        }
                    } else {
                        // Non-weekend mode: include individual available dates within window
                        foreach ($s['dates'] as $d) {
                            if ($startDate && $d < $startDate) continue;
                            if ($endDate && $d > $endDate) continue;
                            $fri = $d; $sat = date('Y-m-d', strtotime($d) + 86400);
                            $weekendDates[] = ['fri' => $fri, 'sat' => $sat];
                            $allFridays[] = $fri;
                        }
                    }

                    if (!empty($weekendDates)) {
                        $userAlertSites[] = [
                            'site_id' => $s['site_id'],
                            'site_number' => $s['site_number'],
                            'site_name' => $s['site_name'] ?? '',
                            'site_type' => $s['site_type'] ?? '',
                            'facility_name' => $s['facility_name'] ?? '',
                            'park_name' => $parkName,
                            'park_number' => $parkNumberByName[$parkName] ?? null,
                            'weekend_dates' => $weekendDates
                        ];
                    }
                }
            }
        }

        if (empty($userAlertSites)) { 
            echo "  ⏭️  No matching availability\n";
            $skipped++; 
            continue; 
        }

        $earliest = !empty($allFridays) ? min($allFridays) : date('Y-m-d');
        $latest = !empty($allFridays) ? max($allFridays) : date('Y-m-d');
        $dateRangeStr = date('n/j', strtotime($earliest)) . '-' . date('n/j/Y', strtotime($latest));

        $favoriteSiteIds = $favRepo->listFavoriteSiteIds($userId);
        echo "  📧 Sending email with " . count($userAlertSites) . " sites ($dateRangeStr)\n";
        
        // Get park website URL for the first park in the results
        $parkWebsiteUrl = null;
        if (!empty($userAlertSites)) {
            $firstParkName = $userAlertSites[0]['park_name'];
            $stmt = $pdo->prepare('SELECT website_url FROM parks WHERE name = :name LIMIT 1');
            $stmt->execute([':name' => $firstParkName]);
            $parkWebsiteUrl = $stmt->fetchColumn();
        }
        
        $ok = $notify->sendAvailabilityAlert($user['email'], 'Daily Digest', $dateRangeStr, $userAlertSites, $favoriteSiteIds, $userId, $parkWebsiteUrl);
        if ($ok) { 
            $sent++; 
            $details[] = ['email' => $user['email'], 'sites' => count($userAlertSites)];
            echo "  ✅ Sent successfully\n";
        } else { 
            $failed++; 
            echo "  ❌ Failed to send\n";
        }
    }

    echo "\n📊 SUMMARY:\n";
    echo "  Sent: $sent\n";
    echo "  Failed: $failed\n";
    echo "  Skipped: $skipped\n";
    if (!empty($details)) {
        echo "\n📧 DETAILS:\n";
        foreach ($details as $d) {
            echo "  {$d['email']}: {$d['sites']} sites\n";
        }
    }
} else {
    // Normal scraping mode
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
}
