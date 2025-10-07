<?php

require __DIR__ . '/../app/bootstrap.php';

use CampsiteAgent\Repositories\LoginTokenRepository;
use CampsiteAgent\Repositories\UserRepository;
use CampsiteAgent\Repositories\UserPreferencesRepository;
use CampsiteAgent\Services\AuthService;
use CampsiteAgent\Infrastructure\Database;
use CampsiteAgent\Services\ScraperService;
use CampsiteAgent\Repositories\ParkRepository;
use CampsiteAgent\Repositories\SettingsRepository;
use CampsiteAgent\Repositories\FacilityRepository;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function json($status, $data) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect($path) {
    header('Location: ' . $path, true, 302);
    exit;
}

function requireAuth(): int {
    if (!isset($_SESSION['user_id'])) {
        json(401, ['error' => 'Unauthorized']);
    }
    return (int)$_SESSION['user_id'];
}

function requireAdmin(int $userId): void {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $role = $stmt->fetchColumn();
    if ($role !== 'admin') {
        json(403, ['error' => 'Forbidden']);
    }
}


$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Public: list parks
if ($method === 'GET' && $uri === '/api/parks') {
    $repo = new ParkRepository();
    json(200, ['parks' => $repo->listAll()]);
}

// Public (auth required): list active facilities for a park
if ($method === 'GET' && preg_match('#^/api/parks/(\\d+)/facilities$#', $uri, $m)) {
    $uid = requireAuth();
    $parkId = (int)$m[1];
    $facRepo = new \CampsiteAgent\Repositories\FacilityRepository();
    $facilities = $facRepo->findActiveFacilities($parkId);
    // Return only essential fields
    $out = array_map(function($f) {
        return [
            'id' => (int)$f['id'],
            'name' => $f['name'],
            'facility_id' => $f['facility_id'],
        ];
    }, $facilities);
    json(200, ['facilities' => $out]);
}

// Favorites: list current user's favorite site IDs
if ($method === 'GET' && $uri === '/api/favorites') {
    $uid = requireAuth();
    $repo = new \CampsiteAgent\Repositories\UserFavoritesRepository();
    json(200, ['siteIds' => $repo->listFavoriteSiteIds($uid)]);
}

// Favorites: toggle a site favorite
if ($method === 'POST' && preg_match('#^/api/favorites/(\d+)/toggle$#', $uri, $m)) {
    $uid = requireAuth();
    $siteId = (int)$m[1];
    $repo = new \CampsiteAgent\Repositories\UserFavoritesRepository();
    $nowFav = $repo->toggleFavorite($uid, $siteId);
    json(200, ['favorite' => $nowFav]);
}

// Facilities: list sites for a facility (metadata, not availability)
if ($method === 'GET' && preg_match('#^/api/facilities/(\d+)/sites$#', $uri, $m)) {
    $uid = requireAuth();
    $facilityDbId = (int)$m[1];

    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('SELECT id AS site_id, site_number, site_name, site_type FROM sites WHERE facility_id = :fid ORDER BY CAST(site_number AS UNSIGNED), site_number');
    $stmt->execute([':fid' => $facilityDbId]);
    $sites = $stmt->fetchAll();

    // Mark favorites for this user
    $favRepo = new \CampsiteAgent\Repositories\UserFavoritesRepository();
    $favSet = array_fill_keys($favRepo->listFavoriteSiteIds($uid), true);
    foreach ($sites as &$s) {
        $s['favorite'] = isset($favSet[(int)$s['site_id']]);
    }
    unset($s);

    json(200, ['sites' => $sites]);
}

// Admin: create park
if ($method === 'POST' && $uri === '/api/admin/parks') {
    $uid = requireAuth();
    requireAdmin($uid);
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $name = trim((string)($input['name'] ?? ''));
    $parkNumber = trim((string)($input['park_number'] ?? ''));
    if ($name === '' || $parkNumber === '') {
        json(400, ['error' => 'name and park_number are required']);
    }
    $parks = new ParkRepository();
    try {
        $id = $parks->createPark($name, $parkNumber, true);
        json(201, ['message' => 'Park created', 'id' => $id]);
    } catch (\Throwable $e) {
        json(500, ['error' => 'Failed to create park', 'details' => $e->getMessage()]);
    }
}

// Admin: delete park
if ($method === 'DELETE' && preg_match('#^/api/admin/parks/(\d+)$#', $uri, $m)) {
    $uid = requireAuth();
    requireAdmin($uid);
    $parkId = (int)$m[1];

    // Optional safety: prevent delete if facilities or sites exist
    $pdo = Database::getConnection();
    $hasDeps = false;
    $c1 = $pdo->prepare('SELECT COUNT(*) FROM facilities WHERE park_id = :id');
    $c1->execute([':id' => $parkId]);
    if ((int)$c1->fetchColumn() > 0) { $hasDeps = true; }
    $c2 = $pdo->prepare('SELECT COUNT(*) FROM sites WHERE park_id = :id');
    $c2->execute([':id' => $parkId]);
    if ((int)$c2->fetchColumn() > 0) { $hasDeps = true; }
    if ($hasDeps) {
        json(409, ['error' => 'Park has dependent facilities or sites. Remove them first.']);
    }

    $parks = new ParkRepository();
    try {
        $parks->delete($parkId);
        json(200, ['message' => 'Park deleted']);
    } catch (\Throwable $e) {
        json(500, ['error' => 'Failed to delete park', 'details' => $e->getMessage()]);
    }
}
// Admin: settings get
if ($method === 'GET' && $uri === '/api/admin/settings') {
    $uid = requireAuth();
    requireAdmin($uid);
    $settings = new SettingsRepository();
    $ua = $settings->get('rc_user_agent');
    json(200, [
        'rc_user_agent' => $ua ?? (getenv('RC_USER_AGENT') ?: null),
    ]);
}

// Admin: update user agent
if ($method === 'POST' && $uri === '/api/admin/settings/user-agent') {
    $uid = requireAuth();
    requireAdmin($uid);
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $ua = isset($input['rc_user_agent']) ? trim((string)$input['rc_user_agent']) : '';
    if ($ua === '') {
        json(400, ['error' => 'rc_user_agent required']);
    }
    $settings = new SettingsRepository();
    $settings->set('rc_user_agent', $ua);
    json(200, ['message' => 'Updated', 'rc_user_agent' => $ua]);
}

// Export CSV of availability
if ($method === 'GET' && $uri === '/api/availability/export.csv') {
    $pdo = Database::getConnection();
    $parkId = isset($_GET['parkId']) ? (int)$_GET['parkId'] : null;
    $weekendOnly = isset($_GET['weekendOnly']) && $_GET['weekendOnly'] === '1';
    $dateRangeParam = $_GET['dateRange'] ?? 'all';
    $sortBy = in_array(($_GET['sortBy'] ?? 'date'), ['date','site'], true) ? $_GET['sortBy'] : 'date';
    $sortDir = (($_GET['sortDir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';

    $order = $sortBy === 'site'
        ? ' ORDER BY p.name, s.site_number ' . $sortDir . ', a.date ASC'
        : ' ORDER BY p.name, a.date ' . $sortDir . ', s.site_number ASC';

    $wherePark = $parkId ? ' AND p.id = :parkId' : '';
    
    // Date range filter
    $whereDateRange = '';
    $maxDate = null;
    
    if (is_numeric($dateRangeParam) && $dateRangeParam !== 'all') {
        $days = (int)$dateRangeParam;
        if ($days > 0) {
            $maxDate = date('Y-m-d', strtotime("+{$days} days"));
            $whereDateRange = ' AND a.date <= :maxDate';
        }
    }

    $sql = 'SELECT p.name AS park, s.site_number, s.site_type, a.date
            FROM parks p
            JOIN sites s ON s.park_id = p.id
            JOIN site_availability a ON a.site_id = s.id
            WHERE a.is_available = 1 AND a.date >= CURDATE()' . $wherePark . $whereDateRange . $order;
    $stmt = $pdo->prepare($sql);
    if ($parkId) { $stmt->bindValue(':parkId', $parkId, PDO::PARAM_INT); }
    if ($maxDate) { $stmt->bindValue(':maxDate', $maxDate, PDO::PARAM_STR); }
    $stmt->execute();
    $rows = $stmt->fetchAll();

    // Weekend filter applied at grouping stage
    $byKey = [];
    foreach ($rows as $r) {
        $key = $r['park'] . '|' . $r['site_number'] . '|' . ($r['site_type'] ?? '');
        if (!isset($byKey[$key])) {
            $byKey[$key] = ['park' => $r['park'], 'site_number' => $r['site_number'], 'site_type' => $r['site_type'], 'dates' => []];
        }
        $byKey[$key]['dates'][] = $r['date'];
    }

    if ($weekendOnly) {
        foreach ($byKey as $k => $s) {
            $set = array_fill_keys($s['dates'], true);
            $ok = false;
            foreach ($s['dates'] as $d) {
                $ts = strtotime($d);
                if ((int)date('w', $ts) === 5) { // Friday
                    $fri = date('Y-m-d', $ts);
                    $sat = date('Y-m-d', $ts + 86400);
                    if (isset($set[$fri]) && isset($set[$sat])) { $ok = true; break; }
                }
            }
            if (!$ok) unset($byKey[$k]);
        }
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="availability_export.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['park', 'site_number', 'site_type', 'dates']);
    foreach ($byKey as $s) {
        fputcsv($out, [$s['park'], $s['site_number'], $s['site_type'], implode(' ', $s['dates'])]);
    }
    fclose($out);
    exit;
}

// Availability latest (public)
if ($method === 'GET' && $uri === '/api/availability/latest') {
    $pdo = Database::getConnection();
    $parkId = isset($_GET['parkId']) ? (int)$_GET['parkId'] : null;
    $weekendOnly = isset($_GET['weekendOnly']) && $_GET['weekendOnly'] === '1';
    $dateRangeParam = $_GET['dateRange'] ?? 'all';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(100, max(10, (int)($_GET['pageSize'] ?? 20)));
    $offset = ($page - 1) * $pageSize;
    $sortBy = in_array(($_GET['sortBy'] ?? 'date'), ['date','site'], true) ? $_GET['sortBy'] : 'date';
    $sortDir = (($_GET['sortDir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';

    $order = $sortBy === 'site'
        ? ' ORDER BY p.name, s.site_number ' . $sortDir . ', a.date ASC'
        : ' ORDER BY p.name, a.date ' . $sortDir . ', s.site_number ASC';

    $wherePark = $parkId ? ' AND p.id = :parkId' : '';
    
    // Date range filter
    $whereDateRange = '';
    $maxDate = null;
    
    if (is_numeric($dateRangeParam) && $dateRangeParam !== 'all') {
        $days = (int)$dateRangeParam;
        if ($days > 0) {
            $maxDate = date('Y-m-d', strtotime("+{$days} days"));
            $whereDateRange = ' AND a.date <= :maxDate';
        }
    }

    // Don't LIMIT the raw query - we need all dates for each site to detect weekends
    $sql = 'SELECT p.id AS park_id, p.name AS park_name, p.park_number, s.id AS site_id, s.site_number, s.site_name, s.site_type, 
                   f.id AS facility_id, f.name AS facility_name, a.date, a.created_at AS found_at
            FROM parks p
            JOIN sites s ON s.park_id = p.id
            LEFT JOIN facilities f ON s.facility_id = f.id
            JOIN site_availability a ON a.site_id = s.id
            WHERE a.is_available = 1 AND a.date >= CURDATE()' . $wherePark . $whereDateRange . $order;
    $stmt = $pdo->prepare($sql);
    if ($parkId) { $stmt->bindValue(':parkId', $parkId, PDO::PARAM_INT); }
    if ($maxDate) { $stmt->bindValue(':maxDate', $maxDate, PDO::PARAM_STR); }
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $byPark = [];
    $parkInfo = []; // Track park_id and park_number for each park name
    foreach ($rows as $r) {
        $park = $r['park_name'];
        $parkId = $r['park_id'];
        $parkNumber = $r['park_number'];
        
        // Store park info for later
        if (!isset($parkInfo[$park])) {
            $parkInfo[$park] = ['id' => $parkId, 'park_number' => $parkNumber];
        }
        
        // Use site_number + site_name as unique key (in case same number in different facilities)
        $key = $r['site_number'] . '|' . ($r['site_name'] ?? '') . '|' . ($r['site_type'] ?? '');
        if (!isset($byPark[$park])) { $byPark[$park] = []; }
        if (!isset($byPark[$park][$key])) {
            $byPark[$park][$key] = [
                'site_id' => (int)$r['site_id'],
                'site_number' => $r['site_number'],
                'site_name' => $r['site_name'],
                'site_type' => $r['site_type'],
                'facility_name' => $r['facility_name'],
                'facility_id' => isset($r['facility_id']) ? (int)$r['facility_id'] : null,
                'dates' => [],
                'found_at' => $r['found_at']
            ];
        }
        $byPark[$park][$key]['dates'][] = $r['date'];
        // Track earliest discovery time for this site
        if (!empty($r['found_at'])) {
            if (empty($byPark[$park][$key]['found_at']) || $r['found_at'] < $byPark[$park][$key]['found_at']) {
                $byPark[$park][$key]['found_at'] = $r['found_at'];
            }
        }
    }

    if ($weekendOnly) {
        foreach ($byPark as $park => $sites) {
            foreach ($sites as $k => $s) {
                $dates = array_fill_keys($s['dates'], true);
                $hasWeekend = false;
                foreach ($s['dates'] as $d) {
                    $ts = strtotime($d);
                    if ((int)date('w', $ts) === 5) {
                        $fri = date('Y-m-d', $ts);
                        $sat = date('Y-m-d', $ts + 86400);
                        if (isset($dates[$fri]) && isset($dates[$sat])) { $hasWeekend = true; break; }
                    }
                }
                if (!$hasWeekend) { unset($byPark[$park][$k]); }
            }
            if (empty($byPark[$park])) { unset($byPark[$park]); }
        }
    }

    // Build output with all sites
    $allSites = [];
    foreach ($byPark as $park => $sites) {
        if ($sortBy === 'date' && $sortDir === 'DESC') {
            foreach ($sites as &$site) { rsort($site['dates']); }
            unset($site);
        }
        foreach ($sites as $site) {
            $allSites[] = ['park' => $park, 'site' => $site];
        }
    }
    
    // Apply pagination to sites
    $totalSites = count($allSites);
    $paginatedSites = array_slice($allSites, $offset, $pageSize);
    
    // Group back by park for output
    $out = [];
    foreach ($paginatedSites as $item) {
        $park = $item['park'];
        $found = false;
        foreach ($out as &$p) {
            if ($p['park'] === $park) {
                $p['sites'][] = $item['site'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $out[] = [
                'park' => $park, 
                'park_id' => $parkInfo[$park]['id'] ?? null,
                'park_number' => $parkInfo[$park]['park_number'] ?? null,
                'sites' => [$item['site']]
            ];
        }
    }
    
    json(200, ['data' => $out, 'page' => $page, 'pageSize' => $pageSize, 'total' => $totalSites]);
}

// Admin: list parks
if ($method === 'GET' && $uri === '/api/admin/parks') {
    $uid = requireAuth();
    requireAdmin($uid);
    $repo = new ParkRepository();
    json(200, ['parks' => $repo->listAll()]);
}

// Admin: upsert park
if ($method === 'POST' && $uri === '/api/admin/parks') {
    $uid = requireAuth();
    requireAdmin($uid);
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $name = trim($input['name'] ?? '');
    $externalId = trim($input['externalId'] ?? '');
    $active = isset($input['active']) ? (bool)$input['active'] : true;
    if ($name === '' || $externalId === '') {
        json(400, ['error' => 'name and externalId required']);
    }
    $repo = new ParkRepository();
    $repo->upsert($name, $externalId, $active);
    json(200, ['message' => 'Upserted']);
}

// Admin: activate/deactivate park
if ($method === 'POST' && preg_match('#^/api/admin/parks/(\d+)/(activate|deactivate)$#', $uri, $m)) {
    $uid = requireAuth();
    requireAdmin($uid);
    $parkId = (int)$m[1];
    $action = $m[2];
    $repo = new ParkRepository();
    $repo->setActive($parkId, $action === 'activate');
    json(200, ['message' => $action === 'activate' ? 'Activated' : 'Deactivated']);
}

if ($method === 'GET' && $uri === '/api/check-now') {
    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: text/event-stream');
        echo "data: " . json_encode(['type' => 'error', 'message' => 'Unauthorized']) . "\n\n";
        exit;
    }
    
    // Set up Server-Sent Events
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no'); // Disable nginx buffering
    
    // Disable all output buffering
    while (ob_get_level()) ob_end_clean();
    
    // Progress callback function
    $sendEvent = function($data) {
        echo "data: " . json_encode($data) . "\n\n";
        flush();
    };
    
    // Get user selections
    $parkIdFilter = isset($_GET['parkId']) ? (int)$_GET['parkId'] : null;
    $weekendOnly = isset($_GET['weekendOnly']) && $_GET['weekendOnly'] === '1';
    $dateRangeParam = $_GET['dateRange'] ?? 'all';
    
    $sendEvent(['type' => 'started', 'message' => 'Starting availability check...']);
    $sendEvent(['type' => 'debug', 'message' => "🔍 DEBUG: parkIdFilter = " . ($parkIdFilter ?: 'null')]);
    $sendEvent(['type' => 'debug', 'message' => "🔍 DEBUG: weekendOnly = " . ($weekendOnly ? 'true' : 'false')]);
    $sendEvent(['type' => 'debug', 'message' => "🔍 DEBUG: dateRange = " . $dateRangeParam]);
    
    // Calculate months to scrape based on selected date range
    $monthsToScrape = 6; // default for "all"
    if ($dateRangeParam === '30') {
        $monthsToScrape = 2;
    } elseif ($dateRangeParam === '60') {
        $monthsToScrape = 3;
    } elseif ($dateRangeParam === '90') {
        $monthsToScrape = 4;
    } elseif ($dateRangeParam === '180') {
        $monthsToScrape = 6;
    }
    
    // Build filter message
    $filterParts = [];
    if ($parkIdFilter) {
        $parkRepo = new \CampsiteAgent\Repositories\ParkRepository();
        $parks = $parkRepo->listAll();
        foreach ($parks as $p) {
            if ((int)$p['id'] === $parkIdFilter) {
                $filterParts[] = "Park: {$p['name']}";
                break;
            }
        }
    } else {
        $filterParts[] = "All parks";
    }
    $filterParts[] = $weekendOnly ? "Weekend only" : "All dates";
    $filterParts[] = "{$monthsToScrape} month" . ($monthsToScrape > 1 ? 's' : '');
    
    $sendEvent(['type' => 'info', 'message' => 'Filters: ' . implode(' | ', $filterParts)]);
    
    $svc = new ScraperService();
    
    try {
        $sendEvent(['type' => 'debug', 'message' => "🚀 Calling ScraperService->checkNow()..."]);
        $results = $svc->checkNow($sendEvent, $monthsToScrape, $parkIdFilter);
        $sendEvent(['type' => 'debug', 'message' => "✅ ScraperService->checkNow() completed successfully"]);
        $sendEvent(['type' => 'completed', 'results' => $results]);
    } catch (\Throwable $e) {
        $sendEvent(['type' => 'error', 'message' => "❌ Fatal error: " . $e->getMessage()]);
        $sendEvent(['type' => 'error', 'message' => "📍 File: " . $e->getFile() . ":" . $e->getLine()]);
        $sendEvent(['type' => 'error', 'message' => "🔍 Error type: " . get_class($e)]);
        if (strpos($e->getMessage(), 'SQLSTATE[HY093]') !== false) {
            $sendEvent(['type' => 'error', 'message' => "🚨 SQL Parameter Error Detected! This is the bug we're tracking."]);
        }
    }
    
    exit;
}

// Admin: send test daily digest email (no cron, for manual testing)
if ($method === 'POST' && $uri === '/api/admin/notifications/daily-test') {
    header('Content-Type: application/json');
    $uid = requireAuth();
    requireAdmin($uid);

    try {
        $pdo = Database::getConnection();

        // Build digest ONLY for the currently logged-in user's enabled preferences
        $prefStmt = $pdo->prepare('SELECT * FROM user_alert_preferences WHERE user_id = :uid AND enabled = 1');
        $prefStmt->execute([':uid' => $uid]);
        $prefs = $prefStmt->fetchAll();

        if (empty($prefs)) {
            json(200, ['sent' => false, 'message' => 'No enabled alert preferences for current user']);
        }

        $alertSites = [];
        $allFridays = [];

        foreach ($prefs as $pref) {
            $parkFilterSql = '';
            $params = [];
            if (!empty($pref['park_id'])) {
                $parkFilterSql = ' AND p.id = :parkId';
                $params[':parkId'] = (int)$pref['park_id'];
            }

            $sql = 'SELECT p.id AS park_id, p.name AS park_name, p.park_number,
                           s.id AS site_id, s.site_number, s.site_name, s.site_type,
                           f.name AS facility_name, f.facility_id AS facility_external_id, a.date
                    FROM parks p
                    JOIN sites s ON s.park_id = p.id
                    LEFT JOIN facilities f ON s.facility_id = f.id
                    JOIN site_availability a ON a.site_id = s.id
                    WHERE a.is_available = 1 AND a.date >= CURDATE()' . $parkFilterSql . '
                    ORDER BY p.name, a.date ASC, s.site_number ASC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $byPark = [];
            $parkNumberByName = [];
            foreach ($rows as $r) {
                $park = $r['park_name'];
                if (!isset($parkNumberByName[$park]) && !empty($r['park_number'])) {
                    $parkNumberByName[$park] = $r['park_number'];
                }
                $key = $r['site_number'] . '|' . ($r['site_name'] ?? '') . '|' . ($r['site_type'] ?? '');
                if (!isset($byPark[$park])) { $byPark[$park] = []; }
                if (!isset($byPark[$park][$key])) {
                    $byPark[$park][$key] = [
                        'site_id' => (int)$r['site_id'],
                        'site_number' => $r['site_number'],
                        'site_name' => $r['site_name'],
                        'site_type' => $r['site_type'],
                        'facility_name' => $r['facility_name'],
                        'facility_external_id' => $r['facility_external_id'] ?? null,
                        'dates' => []
                    ];
                }
                $byPark[$park][$key]['dates'][] = $r['date'];
            }

            $startDate = $pref['start_date'] ?? null;
            $endDate = $pref['end_date'] ?? null;

            foreach ($byPark as $park => $sites) {
                foreach ($sites as $s) {
                    $dateSet = array_fill_keys($s['dates'], true);
                    $weekendDates = [];
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
                    if (!empty($weekendDates)) {
                        $alertSites[] = [
                            'site_id' => $s['site_id'] ?? null,
                            'site_number' => $s['site_number'],
                            'site_name' => $s['site_name'] ?? '',
                            'site_type' => $s['site_type'] ?? '',
                            'facility_name' => $s['facility_name'] ?? '',
                            'facility_external_id' => $s['facility_external_id'] ?? null,
                            'park_name' => $park,
                            'park_number' => $parkNumberByName[$park] ?? null,
                            'weekend_dates' => $weekendDates
                        ];
                    }
                }
            }
        }

        if (empty($alertSites)) {
            json(200, ['sent' => false, 'message' => 'No weekend availability to include in digest']);
        }

        $earliest = !empty($allFridays) ? min($allFridays) : date('Y-m-d');
        $latest = !empty($allFridays) ? max($allFridays) : date('Y-m-d');
        $dateRangeStr = date('n/j', strtotime($earliest)) . '-' . date('n/j/Y', strtotime($latest));

        $to = getenv('ALERT_TEST_EMAIL');
        if (!$to) {
            json(400, ['error' => 'ALERT_TEST_EMAIL not configured in environment']);
        }

        // Include admin user's favorites to highlight in digest
        $favRepo = new \CampsiteAgent\Repositories\UserFavoritesRepository();
        $favoriteSiteIds = $favRepo->listFavoriteSiteIds($uid);

        $notify = new \CampsiteAgent\Services\NotificationService();
        $ok = $notify->sendAvailabilityAlert($to, 'Daily Digest', $dateRangeStr, $alertSites, $favoriteSiteIds, $userId);

        json(200, ['sent' => $ok, 'to' => $to, 'sites' => count($alertSites), 'dateRange' => $dateRangeStr]);
    } catch (\Throwable $e) {
        json(500, ['error' => $e->getMessage()]);
    }
}

// Admin: send scrape results email after manual scraping
if ($method === 'POST' && $uri === '/api/admin/notifications/scrape-results') {
    header('Content-Type: application/json');
    $uid = requireAuth();
    requireAdmin($uid);

    try {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $selectedParks = $input['selectedParks'] ?? [];
        $dateRange = $input['dateRange'] ?? '180';
        $weekendOnly = $input['weekendOnly'] ?? true;
        $scrapingMode = $input['scrapingMode'] ?? 'full';
        $stats = $input['stats'] ?? [];

        $pdo = Database::getConnection();

        // Build query based on scraping options
        $parkFilterSql = '';
        $params = [];
        if (!empty($selectedParks) && !in_array('all', $selectedParks)) {
            $placeholders = str_repeat('?,', count($selectedParks) - 1) . '?';
            $parkFilterSql = " AND p.id IN ($placeholders)";
            $params = array_map('intval', $selectedParks);
        }

        $sql = 'SELECT p.id AS park_id, p.name AS park_name, p.park_number,
                       s.id AS site_id, s.site_number, s.site_name, s.site_type,
                       f.name AS facility_name, f.facility_id AS facility_external_id, a.date
                FROM parks p
                JOIN sites s ON s.park_id = p.id
                LEFT JOIN facilities f ON s.facility_id = f.id
                JOIN site_availability a ON a.site_id = s.id
                WHERE a.is_available = 1 AND a.date >= CURDATE()' . $parkFilterSql . '
                ORDER BY p.name, a.date ASC, s.site_number ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Group by park and site, collect dates
        $byPark = [];
        $parkNumberByName = [];
        foreach ($rows as $r) {
            $park = $r['park_name'];
            if (!isset($parkNumberByName[$park]) && !empty($r['park_number'])) {
                $parkNumberByName[$park] = $r['park_number'];
            }
            $key = $r['site_number'] . '|' . ($r['site_name'] ?? '') . '|' . ($r['site_type'] ?? '');
            if (!isset($byPark[$park])) { $byPark[$park] = []; }
            if (!isset($byPark[$park][$key])) {
                $byPark[$park][$key] = [
                    'site_id' => (int)$r['site_id'],
                    'site_number' => $r['site_number'],
                    'site_name' => $r['site_name'],
                    'site_type' => $r['site_type'],
                    'facility_name' => $r['facility_name'],
                    'facility_external_id' => $r['facility_external_id'] ?? null,
                    'dates' => []
                ];
            }
            $byPark[$park][$key]['dates'][] = $r['date'];
        }

        // Build alert sites based on weekend-only setting
        $alertSites = [];
        $allFridays = [];
        foreach ($byPark as $park => $sites) {
            foreach ($sites as $s) {
                $dateSet = array_fill_keys($s['dates'], true);
                $weekendDates = [];
                
                if ($weekendOnly) {
                    // Only include Fri+Sat pairs
                    foreach ($s['dates'] as $d) {
                        $ts = strtotime($d);
                        if ((int)date('w', $ts) === 5) {
                            $fri = date('Y-m-d', $ts);
                            $sat = date('Y-m-d', $ts + 86400);
                            if (isset($dateSet[$sat])) {
                                $weekendDates[] = ['fri' => $fri, 'sat' => $sat];
                                $allFridays[] = $fri;
                            }
                        }
                    }
                } else {
                    // Include all available dates
                    foreach ($s['dates'] as $d) {
                        $weekendDates[] = ['fri' => $d, 'sat' => date('Y-m-d', strtotime($d) + 86400)];
                        $allFridays[] = $d;
                    }
                }
                
                if (!empty($weekendDates)) {
                    $alertSites[] = [
                        'site_id' => $s['site_id'] ?? null,
                        'site_number' => $s['site_number'],
                        'site_name' => $s['site_name'] ?? '',
                        'site_type' => $s['site_type'] ?? '',
                        'facility_name' => $s['facility_name'] ?? '',
                        'facility_external_id' => $s['facility_external_id'] ?? null,
                        'park_name' => $park,
                        'park_number' => $parkNumberByName[$park] ?? null,
                        'weekend_dates' => $weekendDates
                    ];
                }
            }
        }

        if (empty($alertSites)) {
            json(200, ['sent' => false, 'message' => 'No availability found matching scraping criteria']);
        }

        $earliest = !empty($allFridays) ? min($allFridays) : date('Y-m-d');
        $latest = !empty($allFridays) ? max($allFridays) : date('Y-m-d');
        $dateRangeStr = date('n/j', strtotime($earliest)) . '-' . date('n/j/Y', strtotime($latest));

        $to = getenv('ALERT_TEST_EMAIL');
        if (!$to) {
            json(400, ['error' => 'ALERT_TEST_EMAIL not configured in environment']);
        }

        // Include admin user's favorites to highlight in digest
        $favRepo = new \CampsiteAgent\Repositories\UserFavoritesRepository();
        $favoriteSiteIds = $favRepo->listFavoriteSiteIds($uid);

        $notify = new \CampsiteAgent\Services\NotificationService();
        $subject = 'Scrape Results - ' . count($alertSites) . ' sites found';
        $ok = $notify->sendAvailabilityAlert($to, $subject, $dateRangeStr, $alertSites, $favoriteSiteIds, $userId);

        json(200, ['sent' => $ok, 'to' => $to, 'sites' => count($alertSites), 'dateRange' => $dateRangeStr]);
    } catch (\Throwable $e) {
        json(500, ['error' => $e->getMessage()]);
    }
}

// Admin: send daily digests honoring each user's alert preferences (manual trigger)
if ($method === 'POST' && $uri === '/api/admin/notifications/daily-run') {
    header('Content-Type: application/json');
    $uid = requireAuth();
    requireAdmin($uid);

    try {
        $pdo = Database::getConnection();
        $notify = new \CampsiteAgent\Services\NotificationService();
        $favRepo = new \CampsiteAgent\Repositories\UserFavoritesRepository();

        // Users with at least one enabled preference
        $users = $pdo->query('SELECT DISTINCT u.id, u.email, u.first_name
                              FROM users u
                              JOIN user_alert_preferences p ON p.user_id = u.id AND p.enabled = 1
                              WHERE u.verified_at IS NOT NULL AND (u.active IS NULL OR u.active = 1)')->fetchAll();

        $sent = 0; $failed = 0; $skipped = 0; $details = [];

        foreach ($users as $user) {
            $userId = (int)$user['id'];

            // Load preferences
            $st = $pdo->prepare('SELECT * FROM user_alert_preferences WHERE user_id = :uid AND enabled = 1');
            $st->execute([':uid' => $userId]);
            $prefs = $st->fetchAll();
            if (empty($prefs)) { $skipped++; continue; }

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

                // Group by park/site and track park_number
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
                                // Represent single-date as fri=same, sat=next for consistent rendering
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

            if (empty($userAlertSites)) { $skipped++; continue; }

            $earliest = !empty($allFridays) ? min($allFridays) : date('Y-m-d');
            $latest = !empty($allFridays) ? max($allFridays) : date('Y-m-d');
            $dateRangeStr = date('n/j', strtotime($earliest)) . '-' . date('n/j/Y', strtotime($latest));

            $favoriteSiteIds = $favRepo->listFavoriteSiteIds($userId);
            $ok = $notify->sendAvailabilityAlert($user['email'], 'Daily Digest', $dateRangeStr, $userAlertSites, $favoriteSiteIds, $user['id']);
            if ($ok) { $sent++; $details[] = ['email' => $user['email'], 'sites' => count($userAlertSites)]; }
            else { $failed++; }
        }

        json(200, ['sent' => $sent, 'failed' => $failed, 'skipped' => $skipped, 'details' => $details]);
    } catch (\Throwable $e) {
        json(500, ['error' => $e->getMessage()]);
    }
}


if ($method === 'POST' && $uri === '/api/register') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $email = trim($input['email'] ?? '');
    $first = trim($input['firstName'] ?? '');
    $last = trim($input['lastName'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json(400, ['error' => 'Invalid email']);
    }
    
    // Check if user already exists and is verified
    $users = new UserRepository();
    $existingUser = $users->findByEmail($email);
    $isExistingVerified = $existingUser && !empty($existingUser['verified_at']);
    
    $auth = new AuthService();
    $ok = $auth->sendVerificationEmail($email, $first, $last);
    if ($ok) {
        if ($isExistingVerified) {
            json(200, ['message' => 'You already have an account! A login link has been sent to your email.']);
        } else {
            json(200, ['message' => 'Verification email sent']);
        }
    }
    json(500, ['error' => 'Failed to send email']);
}

if ($method === 'POST' && $uri === '/api/login') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $email = trim($input['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json(400, ['error' => 'Invalid email']);
    }
    $auth = new AuthService();
    $ok = $auth->sendLoginEmail($email);
    if ($ok) {
        json(200, ['message' => 'Login link sent']);
    }
    json(403, ['error' => 'User must be verified or does not exist']);
}

if ($method === 'GET' && $uri === '/api/auth/verify') {
    $token = $_GET['token'] ?? '';
    $wantsJson = isset($_GET['json']) && $_GET['json'] == '1';
    if (!$token) {
        $wantsJson ? json(400, ['error' => 'Token required']) : redirect('/verify.html');
    }
    $tokens = new LoginTokenRepository();
    $row = $tokens->consume($token, 'verify');
    if (!$row) {
        $wantsJson ? json(400, ['error' => 'Invalid or expired token']) : redirect('/verify.html');
    }
    $users = new UserRepository();
    $users->markVerified((int)$row['user_id']);
    $wantsJson ? json(200, ['message' => 'Email verified']) : redirect('/verify.html');
}

if ($method === 'GET' && $uri === '/api/auth/callback') {
    $token = $_GET['token'] ?? '';
    $wantsJson = isset($_GET['json']) && $_GET['json'] == '1';
    if (!$token) {
        $wantsJson ? json(400, ['error' => 'Token required']) : redirect('/login.html');
    }
    $tokens = new LoginTokenRepository();
    $row = $tokens->consume($token, 'login');
    if (!$row) {
        $wantsJson ? json(400, ['error' => 'Invalid or expired token']) : redirect('/login.html');
    }
    $_SESSION['user_id'] = (int)$row['user_id'];
    $wantsJson ? json(200, ['message' => 'Login confirmed']) : redirect('/dashboard.html');
}

if ($method === 'GET' && $uri === '/api/me') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id'])) {
        json(401, ['authenticated' => false]);
    }
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, role, verified_at FROM users WHERE id = :id');
    $stmt->execute([':id' => (int)$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    if (!$user) {
        json(401, ['authenticated' => false]);
    }
    json(200, ['authenticated' => true, 'user' => $user]);
}

if ($method === 'POST' && $uri === '/api/logout') {
    header('Content-Type: application/json');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    json(200, ['message' => 'Logged out']);
}

// Get user's alert preferences
if ($method === 'GET' && $uri === '/api/preferences') {
    $userId = requireAuth();
    $repo = new UserPreferencesRepository();
    $preferences = $repo->getByUserId($userId);
    json(200, ['preferences' => $preferences]);
}

// Create a new alert preference
if ($method === 'POST' && $uri === '/api/preferences') {
    $userId = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    
    $parkId = isset($input['park_id']) && $input['park_id'] !== '' ? (int)$input['park_id'] : null;
    $startDate = isset($input['start_date']) && $input['start_date'] !== '' ? $input['start_date'] : null;
    $endDate = isset($input['end_date']) && $input['end_date'] !== '' ? $input['end_date'] : null;
    $frequency = $input['frequency'] ?? 'immediate';
    $weekendOnly = isset($input['weekend_only']) ? (bool)$input['weekend_only'] : true;
    $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : true;
    
    // Frequency removed; always use immediate behavior (ignored)
    
    $repo = new UserPreferencesRepository();
    $id = $repo->create($userId, $parkId, $startDate, $endDate, $frequency, $weekendOnly, $enabled);
    json(201, ['id' => $id, 'message' => 'Preference created']);
}

// Update an alert preference
if ($method === 'PUT' && preg_match('#^/api/preferences/(\d+)$#', $uri, $matches)) {
    $userId = requireAuth();
    $prefId = (int)$matches[1];
    
    // Verify ownership
    $repo = new UserPreferencesRepository();
    $pref = $repo->getById($prefId);
    if (!$pref || (int)$pref['user_id'] !== $userId) {
        json(404, ['error' => 'Preference not found']);
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    
    $parkId = isset($input['park_id']) && $input['park_id'] !== '' ? (int)$input['park_id'] : null;
    $startDate = isset($input['start_date']) && $input['start_date'] !== '' ? $input['start_date'] : null;
    $endDate = isset($input['end_date']) && $input['end_date'] !== '' ? $input['end_date'] : null;
    $frequency = $input['frequency'] ?? 'immediate';
    $weekendOnly = isset($input['weekend_only']) ? (bool)$input['weekend_only'] : true;
    $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : true;
    
    // Validate frequency
    if (!in_array($frequency, ['immediate', 'daily_digest', 'weekly_digest'], true)) {
        json(400, ['error' => 'Invalid frequency']);
    }
    
    $repo->update($prefId, $parkId, $startDate, $endDate, 'immediate', $weekendOnly, $enabled);
    json(200, ['message' => 'Preference updated']);
}

// Delete an alert preference
if ($method === 'DELETE' && preg_match('#^/api/preferences/(\d+)$#', $uri, $matches)) {
    $userId = requireAuth();
    $prefId = (int)$matches[1];
    
    // Verify ownership
    $repo = new UserPreferencesRepository();
    $pref = $repo->getById($prefId);
    if (!$pref || (int)$pref['user_id'] !== $userId) {
        json(404, ['error' => 'Preference not found']);
    }
    
    $repo->delete($prefId);
    json(200, ['message' => 'Preference deleted']);
}

// Toggle a preference on/off
if ($method === 'POST' && preg_match('#^/api/preferences/(\d+)/toggle$#', $uri, $matches)) {
    $userId = requireAuth();
    $prefId = (int)$matches[1];
    
    // Verify ownership
    $repo = new UserPreferencesRepository();
    $pref = $repo->getById($prefId);
    if (!$pref || (int)$pref['user_id'] !== $userId) {
        json(404, ['error' => 'Preference not found']);
    }
    
    $repo->toggleEnabled($prefId);
    json(200, ['message' => 'Preference toggled']);
}

// Admin: Sync park metadata (facilities & sites)
if ($method === 'POST' && $uri === '/api/admin/sync-metadata') {
    header('Content-Type: application/json');
    $uid = requireAuth();
    requireAdmin($uid);
    
    try {
        $sync = new \CampsiteAgent\Services\MetadataSyncService();
        $results = $sync->syncAllActiveParks();
        json(200, ['results' => $results]);
    } catch (\Throwable $e) {
        json(500, ['error' => $e->getMessage()]);
    }
}

// Admin: List facilities for a park
if ($method === 'GET' && preg_match('#^/api/admin/parks/(\d+)/facilities$#', $uri, $matches)) {
    $uid = requireAuth();
    requireAdmin($uid);
    $parkId = (int)$matches[1];
    
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('
        SELECT f.id, f.facility_id, f.name, f.active, f.description, f.allow_web_booking, f.facility_type
        FROM facilities f 
        WHERE f.park_id = :park_id 
        ORDER BY f.name
    ');
    $stmt->execute([':park_id' => $parkId]);
    $facilities = $stmt->fetchAll();
    
    json(200, ['facilities' => $facilities]);
}

// Admin: Update facility activation status
if ($method === 'POST' && preg_match('#^/api/admin/facilities/(\d+)/toggle$#', $uri, $matches)) {
    $uid = requireAuth();
    requireAdmin($uid);
    $facilityId = (int)$matches[1];
    
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('UPDATE facilities SET active = NOT active WHERE id = :id');
    $stmt->execute([':id' => $facilityId]);
    
    if ($stmt->rowCount() > 0) {
        // Get updated facility info
        $stmt = $pdo->prepare('SELECT id, name, active FROM facilities WHERE id = :id');
        $stmt->execute([':id' => $facilityId]);
        $facility = $stmt->fetch();
        
        json(200, [
            'message' => 'Facility ' . ($facility['active'] ? 'activated' : 'deactivated'),
            'facility' => $facility
        ]);
    } else {
        json(404, ['error' => 'Facility not found']);
    }
}

// Admin: Bulk update facility activation
if ($method === 'POST' && $uri === '/api/admin/facilities/bulk-toggle') {
    $uid = requireAuth();
    requireAdmin($uid);
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    
    $facilityIds = $input['facility_ids'] ?? [];
    $active = isset($input['active']) ? (bool)$input['active'] : true;
    
    if (empty($facilityIds)) {
        json(400, ['error' => 'facility_ids required']);
    }
    
    $pdo = Database::getConnection();
    $placeholders = str_repeat('?,', count($facilityIds) - 1) . '?';
    $stmt = $pdo->prepare("UPDATE facilities SET active = ? WHERE id IN ($placeholders)");
    $stmt->execute(array_merge([$active ? 1 : 0], $facilityIds));
    
    json(200, [
        'message' => "Updated " . $stmt->rowCount() . " facilities to " . ($active ? 'active' : 'inactive')
    ]);
}

// Admin: Sync facilities only
if ($method === 'POST' && $uri === '/api/admin/sync-facilities') {
    header('Content-Type: application/json');
    $uid = requireAuth();
    requireAdmin($uid);
    
    try {
        $parkRepo = new \CampsiteAgent\Repositories\ParkRepository();
        $facilityRepo = new \CampsiteAgent\Repositories\FacilityRepository();
        $settings = new \CampsiteAgent\Repositories\SettingsRepository();
        
        // Get user agent from settings
        $ua = $settings->get('rc_user_agent');
        $scraper = new \CampsiteAgent\Services\ReserveCaliforniaScraper($ua);
        
        $parks = $parkRepo->findActiveParks();
        $results = [];
        $totalFacilities = 0;
        
        foreach ($parks as $park) {
            $parkNumber = $park['park_number'] ?? $park['external_id'];
            $parkName = $park['name'];
            
            try {
                // Parse facility filter if present
                $facilityFilter = null;
                if (!empty($park['facility_filter'])) {
                    $facilityFilter = json_decode($park['facility_filter'], true);
                }
                
                // Fetch facilities from API
                $facilities = $scraper->fetchParkFacilities($parkNumber, $facilityFilter);
                
                $facilityCount = 0;
                foreach ($facilities as $facility) {
                    $facilityRepo->upsertFacility(
                        (int)$park['id'],
                        $facility['name'],
                        $facility['facility_id']
                    );
                    $facilityCount++;
                    $totalFacilities++;
                }
                
                $results[] = [
                    'park' => $parkName,
                    'success' => true,
                    'facilities' => $facilityCount
                ];
                
            } catch (\Throwable $e) {
                $results[] = [
                    'park' => $parkName,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        json(200, [
            'message' => "Synced {$totalFacilities} facilities across " . count($parks) . " parks",
            'results' => $results
        ]);
        
    } catch (\Throwable $e) {
        json(500, ['error' => $e->getMessage()]);
    }
}

// Admin: List all users
if ($method === 'GET' && $uri === '/api/admin/users') {
    $uid = requireAuth();
    requireAdmin($uid);
    
    $repo = new UserRepository();
    $users = $repo->getAllUsers();
    json(200, ['users' => $users]);
}

// Admin: Update user status (active/inactive)
if ($method === 'POST' && preg_match('#^/api/admin/users/(\d+)/status$#', $uri, $matches)) {
    $uid = requireAuth();
    requireAdmin($uid);
    
    $userId = (int)$matches[1];
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $active = isset($input['active']) ? (bool)$input['active'] : false;
    
    $repo = new UserRepository();
    $success = $repo->updateUserStatus($userId, $active);
    
    if ($success) {
        json(200, ['message' => 'User status updated']);
    } else {
        json(404, ['error' => 'User not found']);
    }
}

// Admin: Update user role
if ($method === 'POST' && preg_match('#^/api/admin/users/(\d+)/role$#', $uri, $matches)) {
    $uid = requireAuth();
    requireAdmin($uid);
    
    $userId = (int)$matches[1];
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $role = $input['role'] ?? '';
    
    if (!in_array($role, ['admin', 'user'], true)) {
        json(400, ['error' => 'Invalid role']);
    }
    
    $repo = new UserRepository();
    $success = $repo->updateUserRole($userId, $role);
    
    if ($success) {
        json(200, ['message' => 'User role updated']);
    } else {
        json(404, ['error' => 'User not found']);
    }
}

// Admin: Update user names
if ($method === 'POST' && preg_match('#^/api/admin/users/(\d+)/names$#', $uri, $matches)) {
    header('Content-Type: application/json');
    $uid = requireAuth();
    requireAdmin($uid);
    
    $userId = (int)$matches[1];
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $firstName = trim($input['first_name'] ?? '');
    $lastName = trim($input['last_name'] ?? '');
    
    $repo = new UserRepository();
    $success = $repo->updateUserNames($userId, $firstName, $lastName);
    
    if ($success) {
        json(200, ['message' => 'User names updated']);
    } else {
        json(404, ['error' => 'User not found']);
    }
}

// User: Send personal digest email
if ($method === 'POST' && $uri === '/api/user/send-digest') {
    header('Content-Type: application/json');
    $userId = requireAuth();
    
    try {
        $pdo = Database::getConnection();
        $notify = new \CampsiteAgent\Services\NotificationService();
        $favRepo = new \CampsiteAgent\Repositories\UserFavoritesRepository();
        
        // Get user info
        $stmt = $pdo->prepare('SELECT id, email, first_name FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            json(404, ['error' => 'User not found']);
        }
        
        // Load user's enabled preferences
        $st = $pdo->prepare('SELECT * FROM user_alert_preferences WHERE user_id = :uid AND enabled = 1');
        $st->execute([':uid' => $userId]);
        $prefs = $st->fetchAll();
        
        if (empty($prefs)) {
            json(400, ['error' => 'No enabled alert preferences found. Please set up your preferences first.']);
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
            
            // Get current availability from database
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
            json(200, ['sent' => false, 'message' => 'No matching availability found for your preferences.']);
        }
        
        $earliest = !empty($allFridays) ? min($allFridays) : date('Y-m-d');
        $latest = !empty($allFridays) ? max($allFridays) : date('Y-m-d');
        $dateRangeStr = date('n/j', strtotime($earliest)) . '-' . date('n/j/Y', strtotime($latest));
        
        $favoriteSiteIds = $favRepo->listFavoriteSiteIds($userId);
        $ok = $notify->sendAvailabilityAlert($user['email'], 'Personal Digest', $dateRangeStr, $userAlertSites, $favoriteSiteIds, $userId);
        
        if ($ok) {
            json(200, ['sent' => true, 'message' => 'Digest sent successfully', 'sites' => count($userAlertSites), 'dateRange' => $dateRangeStr]);
        } else {
            json(500, ['error' => 'Failed to send digest email']);
        }
        
    } catch (\Throwable $e) {
        json(500, ['error' => 'Error sending digest: ' . $e->getMessage()]);
    }
}

// User: Disable all alerts (via email link)
if ($method === 'GET' && preg_match('#^/api/user/disable-alerts/([^/]+)$#', $uri, $matches)) {
    header('Content-Type: text/html; charset=utf-8');
    
    $token = $matches[1];
    
    try {
        $pdo = Database::getConnection();
        
        // Verify token and get user
        $stmt = $pdo->prepare('SELECT u.id, u.email, u.first_name FROM users u JOIN login_tokens lt ON u.id = lt.user_id WHERE lt.token = :token AND lt.expires_at > NOW()');
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo '<html><body style="font-family: Arial, sans-serif; text-align: center; padding: 50px;"><h2>❌ Invalid or expired link</h2><p>This link is invalid or has expired.</p><p><a href="/landing.html">Return to Campsite Agent</a></p></body></html>';
            exit;
        }
        
        // Disable all user's alert preferences
        $stmt = $pdo->prepare('UPDATE user_alert_preferences SET enabled = 0 WHERE user_id = :userId');
        $stmt->execute([':userId' => $user['id']]);
        
        $affectedRows = $stmt->rowCount();
        
        // Clean up the token
        $stmt = $pdo->prepare('DELETE FROM login_tokens WHERE token = :token');
        $stmt->execute([':token' => $token]);
        
        $name = $user['first_name'] ? htmlspecialchars($user['first_name']) : 'there';
        
        echo '<html><body style="font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f8fafc;"><div style="max-width: 500px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);"><h2 style="color: #059669;">✅ Alerts Disabled</h2><p>Hi ' . $name . ',</p><p>All your alert preferences have been disabled. You will no longer receive daily digest emails.</p><p><strong>' . $affectedRows . ' alert preferences</strong> were disabled.</p><p>You can re-enable alerts anytime by logging in to your <a href="/dashboard.html" style="color: #2563eb;">dashboard</a>.</p><p style="margin-top: 30px; font-size: 14px; color: #6b7280;"><a href="/landing.html">Return to Campsite Agent</a></p></div></body></html>';
        exit;
        
    } catch (\Throwable $e) {
        echo '<html><body style="font-family: Arial, sans-serif; text-align: center; padding: 50px;"><h2>❌ Error</h2><p>An error occurred while disabling alerts: ' . htmlspecialchars($e->getMessage()) . '</p><p><a href="/landing.html">Return to Campsite Agent</a></p></body></html>';
        exit;
    }
}

header('Content-Type: application/json');
http_response_code(404);
echo json_encode(['error' => 'Not found']);
