#!/usr/bin/env php
<?php
/**
 * Send Daily Digest Emails (CLI)
 *
 * Sends availability digest emails to all verified users who have at least one
 * enabled alert preference, honoring each user's park/date filters and the
 * weekend-only flag.
 *
 * Usage:
 *  php send-daily-digest.php [--user=email@example.com] [--dry-run] [--verbose]
 *
 * Options:
 *  --user=EMAIL   Only send to a specific user (by email)
 *  --dry-run      Build digests and print summary, but do not send emails
 *  --verbose      Print additional debug output
 */

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Infrastructure\Database;
use CampsiteAgent\Services\NotificationService;
use CampsiteAgent\Repositories\UserFavoritesRepository;
use CampsiteAgent\Repositories\EmailLogRepository;

// Parse CLI options
$options = getopt('', [
	'user::',
	'dry-run',
	'verbose',
	'help'
]);

if (isset($options['help'])) {
	echo "Send Daily Digest Emails (CLI)\n";
	echo "Usage: php send-daily-digest.php [--user=email@example.com] [--dry-run] [--verbose]\n";
	echo "\nOptions:\n";
	echo "  --user=EMAIL   Only send to a specific user (by email)\n";
	echo "  --dry-run      Build digests and print summary, but do not send emails\n";
	echo "  --verbose      Print additional debug output\n";
	exit(0);
}

$onlyUserEmail = $options['user'] ?? null;
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);

$log = function(string $msg) use ($verbose) {
	if ($verbose) echo $msg . "\n";
};

try {
$pdo = Database::getConnection();
$notify = new NotificationService();
$favRepo = new UserFavoritesRepository();
$emailLogs = new EmailLogRepository();

// Prevent overlapping runs via a simple lock row using GET_LOCK
$gotLock = $pdo->query("SELECT GET_LOCK('send_daily_digest_lock', 1) AS l")->fetchColumn();
if ((int)$gotLock !== 1) {
    fwrite(STDERR, "Another send-daily-digest process appears to be running. Exiting.\n");
    exit(3);
}

	// Select users with at least one enabled preference
	// Limit to a specific user if --user provided
	if ($onlyUserEmail) {
		$stmtUsers = $pdo->prepare('SELECT DISTINCT u.id, u.email, u.first_name
			FROM users u
			JOIN user_alert_preferences p ON p.user_id = u.id AND p.enabled = 1
			WHERE u.verified_at IS NOT NULL AND (u.active IS NULL OR u.active = 1) AND u.email = :email');
		$stmtUsers->execute([':email' => $onlyUserEmail]);
		$users = $stmtUsers->fetchAll();
	} else {
		$users = $pdo->query('SELECT DISTINCT u.id, u.email, u.first_name
			FROM users u
			JOIN user_alert_preferences p ON p.user_id = u.id AND p.enabled = 1
			WHERE u.verified_at IS NOT NULL AND (u.active IS NULL OR u.active = 1)')->fetchAll();
	}

	if (empty($users)) {
		fwrite(STDERR, $onlyUserEmail ? "No matching verified user with enabled preferences: {$onlyUserEmail}\n" : "No users found with enabled preferences.\n");
		exit(1);
	}

	$sent = 0; $failed = 0; $skipped = 0; $details = [];

    foreach ($users as $user) {
		$userId = (int)$user['id'];
		$email = $user['email'];
		$log("Processing user: {$email}");

        // Idempotency: skip if we already sent a Daily Digest to this email today
        if ($emailLogs->hasSentDailyDigestToday($email)) {
            $log("  ⏭️  Already sent Daily Digest today");
            $skipped++;
            continue;
        }

		// Load preferences (enabled only)
		$st = $pdo->prepare('SELECT * FROM user_alert_preferences WHERE user_id = :uid AND enabled = 1');
		$st->execute([':uid' => $userId]);
		$prefs = $st->fetchAll();
		if (empty($prefs)) {
			$log("  ⏭️  No enabled preferences");
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

			// Upcoming availability (limit by park if specified)
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

			// Group by park/site and track park_number for links
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
							if ((int)date('w', $ts) === 5) { // Friday
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
			$log("  ⏭️  No matching availability for user");
			$skipped++;
			continue;
		}

		$earliest = !empty($allFridays) ? min($allFridays) : date('Y-m-d');
		$latest = !empty($allFridays) ? max($allFridays) : date('Y-m-d');
		$dateRangeStr = date('n/j', strtotime($earliest)) . '-' . date('n/j/Y', strtotime($latest));

		$favoriteSiteIds = $favRepo->listFavoriteSiteIds($userId);
		$log("  Preparing email: " . count($userAlertSites) . " sites; range $dateRangeStr");

		if ($dryRun) {
			$details[] = ['email' => $email, 'sites' => count($userAlertSites), 'dateRange' => $dateRangeStr];
			$sent++; // Count as processed in dry-run
			continue;
		}

		// Get park website URL for the first park in the results
		$parkWebsiteUrl = null;
		if (!empty($userAlertSites)) {
			$firstParkName = $userAlertSites[0]['park_name'];
			$stmt = $pdo->prepare('SELECT website_url FROM parks WHERE name = :name LIMIT 1');
			$stmt->execute([':name' => $firstParkName]);
			$parkWebsiteUrl = $stmt->fetchColumn();
		}
		
		// Determine park name from sites (for daily digest)
		$parkNames = [];
		foreach ($userAlertSites as $site) {
			if (!empty($site['park_name']) && !in_array($site['park_name'], $parkNames, true)) {
				$parkNames[] = $site['park_name'];
			}
		}
		$parkNameForEmail = count($parkNames) === 1 
			? $parkNames[0] 
			: (count($parkNames) > 1 ? 'Multiple Parks' : 'Daily Digest');
		
		$ok = $notify->sendAvailabilityAlert($email, $parkNameForEmail, $dateRangeStr, $userAlertSites, $favoriteSiteIds, $userId, $parkWebsiteUrl);
		if ($ok) {
			$sent++;
			$details[] = ['email' => $email, 'sites' => count($userAlertSites)];
			$log("  ✅ Sent");
		} else {
			$failed++;
			$log("  ❌ Failed to send");
		}
	}

    // Release lock
    $pdo->query("DO RELEASE_LOCK('send_daily_digest_lock')");

    // Summary
	echo "\n📊 SUMMARY:\n";
	echo "  Sent: $sent\n";
	echo "  Failed: $failed\n";
	echo "  Skipped: $skipped\n";
	if (!empty($details)) {
		echo "\n📧 DETAILS:\n";
		foreach ($details as $d) {
			echo "  {$d['email']}: {$d['sites']} sites" . (isset($d['dateRange']) ? " ({$d['dateRange']})" : "") . "\n";
		}
	}

	exit($failed > 0 ? 2 : 0);
} catch (\Throwable $e) {
	fwrite(STDERR, "Fatal error: " . $e->getMessage() . "\n");
	fwrite(STDERR, "File: " . $e->getFile() . ":" . $e->getLine() . "\n");
	exit(1);
}


