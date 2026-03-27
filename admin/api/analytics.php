<?php
/**
 * Analytics API
 *
 * POST (public): ?action=track     — track page view (with UTM + duration)
 * POST (public): ?action=event     — track custom event
 * POST (public): ?action=duration  — update page view duration
 * GET  (admin):  ?action=dashboard — dashboard stats (bounce rate, avg duration)
 * GET  (admin):  ?action=pages     — top pages
 * GET  (admin):  ?action=referrers — top referrers
 * GET  (admin):  ?action=chart     — daily views chart data
 * GET  (admin):  ?action=realtime  — real-time visitor count
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

$action = $_GET['action'] ?? '';

// ===== PUBLIC: Track Page View =====
if ($action === 'track' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $pagePath = sanitize($input['path'] ?? '/');
    $pageTitle = sanitize($input['title'] ?? '');
    $referrer = sanitize($input['referrer'] ?? '');
    $sessionId = sanitize($input['session_id'] ?? '');
    $utmSource = sanitize($input['utm_source'] ?? '');
    $utmMedium = sanitize($input['utm_medium'] ?? '');
    $utmCampaign = sanitize($input['utm_campaign'] ?? '');

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $device = detectDevice($ua);
    $browser = detectBrowser($ua);
    $os = detectOS($ua);

    try {
        $db = getDB();
        $db->exec(file_get_contents(__DIR__ . '/../includes/schema_analytics.sql'));

        $stmt = $db->prepare('INSERT INTO page_views (page_path, page_title, referrer, ip_address, user_agent, device_type, browser, os, session_id, utm_source, utm_medium, utm_campaign, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            $pagePath, $pageTitle, $referrer,
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($ua, 0, 500),
            $device, $browser, $os, $sessionId,
            $utmSource ?: null, $utmMedium ?: null, $utmCampaign ?: null
        ]);

        $viewId = $db->lastInsertId();

        // Check goals (pageview type)
        try {
            $goals = $db->query("SELECT id, goal_target FROM analytics_goals WHERE is_active = 1 AND goal_type = 'pageview'")->fetchAll();
            foreach ($goals as $g) {
                if ($pagePath === $g['goal_target'] || fnmatch($g['goal_target'], $pagePath)) {
                    $db->prepare('INSERT INTO analytics_conversions (goal_id, ip_address, session_id) VALUES (?, ?, ?)')
                        ->execute([$g['id'], $_SERVER['REMOTE_ADDR'] ?? '', $sessionId]);
                }
            }
        } catch (Exception $e) {}

        jsonResponse(['success' => true, 'view_id' => (int)$viewId]);
    } catch (Exception $e) {
        jsonResponse(['success' => false], 500);
    }
}

// ===== PUBLIC: Track Event =====
if ($action === 'event' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $eventName = sanitize($input['event'] ?? '');
    $eventCategory = sanitize($input['category'] ?? 'general');
    $eventData = isset($input['data']) ? json_encode($input['data']) : null;
    $pagePath = sanitize($input['path'] ?? '');
    $sessionId = sanitize($input['session_id'] ?? '');

    if (!$eventName) { jsonResponse(['success' => false, 'message' => 'Event name required.'], 400); }

    try {
        $db = getDB();
        $db->exec(file_get_contents(__DIR__ . '/../includes/schema_analytics.sql'));

        $stmt = $db->prepare('INSERT INTO analytics_events (event_name, event_category, event_data, page_path, ip_address, session_id) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$eventName, $eventCategory, $eventData, $pagePath, $_SERVER['REMOTE_ADDR'] ?? '', $sessionId]);

        // Check goals (event type)
        try {
            $goals = $db->query("SELECT id, goal_target FROM analytics_goals WHERE is_active = 1 AND goal_type = 'event'")->fetchAll();
            foreach ($goals as $g) {
                if ($eventName === $g['goal_target']) {
                    $db->prepare('INSERT INTO analytics_conversions (goal_id, ip_address, session_id) VALUES (?, ?, ?)')
                        ->execute([$g['id'], $_SERVER['REMOTE_ADDR'] ?? '', $sessionId]);
                }
            }
        } catch (Exception $e) {}

        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false], 500);
    }
}

// ===== PUBLIC: Update Duration =====
if ($action === 'duration' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $viewId = (int)($input['view_id'] ?? 0);
    $duration = (int)($input['duration'] ?? 0);

    if (!$viewId || $duration < 0) { jsonResponse(['success' => false], 400); }

    try {
        $db = getDB();
        $db->prepare('UPDATE page_views SET duration = ? WHERE id = ?')->execute([min($duration, 3600), $viewId]);

        // Check duration goals
        try {
            $goals = $db->query("SELECT id, goal_target FROM analytics_goals WHERE is_active = 1 AND goal_type = 'duration'")->fetchAll();
            foreach ($goals as $g) {
                if ($duration >= (int)$g['goal_target']) {
                    $sessionId = $db->query("SELECT session_id FROM page_views WHERE id = {$viewId}")->fetchColumn();
                    // Only record once per session per goal
                    $exists = $db->prepare("SELECT 1 FROM analytics_conversions WHERE goal_id = ? AND session_id = ?");
                    $exists->execute([$g['id'], $sessionId]);
                    if (!$exists->fetch()) {
                        $db->prepare('INSERT INTO analytics_conversions (goal_id, ip_address, session_id) VALUES (?, ?, ?)')
                            ->execute([$g['id'], $_SERVER['REMOTE_ADDR'] ?? '', $sessionId]);
                    }
                }
            }
        } catch (Exception $e) {}

        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false], 500);
    }
}

// ===== ADMIN ENDPOINTS =====
if (in_array($action, ['dashboard', 'pages', 'referrers', 'chart', 'realtime', 'conversions', 'geo', 'performance', 'export_csv', 'comparison'])) {
    require_once '../includes/auth.php';
    startSecureSession();
    requireLogin();

    $db = getDB();
    try { $db->exec(file_get_contents(__DIR__ . '/../includes/schema_analytics.sql')); } catch (Exception $e) {}

    $range = max(1, (int)($_GET['range'] ?? 7));
    $rangeSQL = "created_at >= DATE_SUB(NOW(), INTERVAL {$range} DAY)";
}

// ===== Dashboard Stats =====
if ($action === 'dashboard') {
    $totalViews = $db->query("SELECT COUNT(*) FROM page_views WHERE {$rangeSQL}")->fetchColumn();
    $uniqueVisitors = $db->query("SELECT COUNT(DISTINCT ip_address) FROM page_views WHERE {$rangeSQL}")->fetchColumn();

    // Bounce rate: sessions with only 1 page view
    $totalSessionsA = $db->query("SELECT COUNT(DISTINCT session_id) FROM page_views WHERE {$rangeSQL} AND session_id IS NOT NULL AND session_id != ''")->fetchColumn();
    $bouncedSessions = $db->query("SELECT COUNT(*) FROM (SELECT session_id, COUNT(*) as cnt FROM page_views WHERE {$rangeSQL} AND session_id IS NOT NULL AND session_id != '' GROUP BY session_id HAVING cnt = 1) t")->fetchColumn();
    $bounceRate = $totalSessionsA > 0 ? round(($bouncedSessions / $totalSessionsA) * 100) : 0;

    // Average duration
    $avgDuration = $db->query("SELECT COALESCE(ROUND(AVG(duration)), 0) FROM page_views WHERE {$rangeSQL} AND duration > 0")->fetchColumn();

    // Device breakdown
    $devices = $db->query("SELECT device_type, COUNT(*) as cnt FROM page_views WHERE {$rangeSQL} GROUP BY device_type ORDER BY cnt DESC")->fetchAll();

    // Top browsers
    $browsers = $db->query("SELECT browser, COUNT(*) as cnt FROM page_views WHERE {$rangeSQL} AND browser IS NOT NULL GROUP BY browser ORDER BY cnt DESC LIMIT 5")->fetchAll();

    // Top OS
    $oses = $db->query("SELECT os, COUNT(*) as cnt FROM page_views WHERE {$rangeSQL} AND os IS NOT NULL GROUP BY os ORDER BY cnt DESC LIMIT 5")->fetchAll();

    jsonResponse([
        'total_views' => (int)$totalViews,
        'unique_visitors' => (int)$uniqueVisitors,
        'bounce_rate' => (int)$bounceRate,
        'avg_duration' => (int)$avgDuration,
        'today_views' => (int)$db->query("SELECT COUNT(*) FROM page_views WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
        'avg_per_day' => $range > 0 ? round($totalViews / $range) : (int)$totalViews,
        'devices' => $devices,
        'browsers' => $browsers,
        'oses' => $oses,
    ]);
}

// ===== Top Pages =====
if ($action === 'pages') {
    $pages = $db->query("SELECT page_path, page_title, COUNT(*) as views, COUNT(DISTINCT ip_address) as unique_views FROM page_views WHERE {$rangeSQL} GROUP BY page_path, page_title ORDER BY views DESC LIMIT 20")->fetchAll();
    jsonResponse(['pages' => $pages]);
}

// ===== Top Referrers =====
if ($action === 'referrers') {
    $referrers = $db->query("SELECT referrer, COUNT(*) as cnt FROM page_views WHERE {$rangeSQL} AND referrer != '' AND referrer IS NOT NULL GROUP BY referrer ORDER BY cnt DESC LIMIT 15")->fetchAll();
    jsonResponse(['referrers' => $referrers]);
}

// ===== Daily Chart =====
if ($action === 'chart') {
    $data = $db->query("SELECT DATE(created_at) as date, COUNT(*) as views, COUNT(DISTINCT ip_address) as visitors FROM page_views WHERE {$rangeSQL} GROUP BY DATE(created_at) ORDER BY date ASC")->fetchAll();
    jsonResponse(['chart' => $data]);
}

// ===== Real-Time =====
if ($action === 'realtime') {
    $count = $db->query("SELECT COUNT(DISTINCT ip_address) FROM page_views WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
    $pages = $db->query("SELECT page_path, page_title, COUNT(*) as cnt FROM page_views WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) GROUP BY page_path, page_title ORDER BY cnt DESC LIMIT 10")->fetchAll();
    jsonResponse(['active_visitors' => (int)$count, 'pages' => $pages]);
}

// ===== Conversions (waitlist, contacts, subscribers) =====
if ($action === 'conversions') {
    $waitlist = 0; $contacts = 0; $subscribers = 0;
    try { $waitlist = $db->query("SELECT COUNT(*) FROM waitlist WHERE {$rangeSQL}")->fetchColumn(); } catch (Exception $e) {}
    try { $contacts = $db->query("SELECT COUNT(*) FROM contacts WHERE {$rangeSQL}")->fetchColumn(); } catch (Exception $e) {}
    try { $subscribers = $db->query("SELECT COUNT(*) FROM subscribers WHERE {$rangeSQL}")->fetchColumn(); } catch (Exception $e) {}

    // Previous period for comparison
    $prevRange = $range * 2;
    $prevSQL = "created_at >= DATE_SUB(NOW(), INTERVAL {$prevRange} DAY) AND created_at < DATE_SUB(NOW(), INTERVAL {$range} DAY)";
    $prevWaitlist = 0; $prevContacts = 0; $prevSubscribers = 0;
    try { $prevWaitlist = $db->query("SELECT COUNT(*) FROM waitlist WHERE {$prevSQL}")->fetchColumn(); } catch (Exception $e) {}
    try { $prevContacts = $db->query("SELECT COUNT(*) FROM contacts WHERE {$prevSQL}")->fetchColumn(); } catch (Exception $e) {}
    try { $prevSubscribers = $db->query("SELECT COUNT(*) FROM subscribers WHERE {$prevSQL}")->fetchColumn(); } catch (Exception $e) {}

    jsonResponse([
        'waitlist' => (int)$waitlist, 'prev_waitlist' => (int)$prevWaitlist,
        'contacts' => (int)$contacts, 'prev_contacts' => (int)$prevContacts,
        'subscribers' => (int)$subscribers, 'prev_subscribers' => (int)$prevSubscribers,
    ]);
}

// ===== Geographic Breakdown =====
if ($action === 'geo') {
    // Country from stored field (or try to derive from IP)
    $countries = $db->query("SELECT COALESCE(country, 'Unknown') as country, COUNT(*) as cnt FROM page_views WHERE {$rangeSQL} GROUP BY country ORDER BY cnt DESC LIMIT 15")->fetchAll();
    jsonResponse(['countries' => $countries]);
}

// ===== Page Performance =====
if ($action === 'performance') {
    $avgLoad = $db->query("SELECT page_path, ROUND(AVG(duration)) as avg_duration, COUNT(*) as views FROM page_views WHERE {$rangeSQL} AND duration > 0 GROUP BY page_path ORDER BY avg_duration DESC LIMIT 15")->fetchAll();
    $slowest = $db->query("SELECT page_path, MAX(duration) as max_duration FROM page_views WHERE {$rangeSQL} AND duration > 0 GROUP BY page_path ORDER BY max_duration DESC LIMIT 10")->fetchAll();
    jsonResponse(['avg_load' => $avgLoad, 'slowest' => $slowest]);
}

// ===== Export CSV =====
if ($action === 'export_csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="analytics_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Page Path', 'Page Title', 'Referrer', 'IP', 'Device', 'Browser', 'OS', 'Country', 'UTM Source', 'UTM Medium', 'UTM Campaign', 'Duration', 'Timestamp']);
    $rows = $db->query("SELECT * FROM page_views WHERE {$rangeSQL} ORDER BY created_at DESC");
    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [$row['id'], $row['page_path'], $row['page_title'], $row['referrer'], $row['ip_address'], $row['device_type'], $row['browser'], $row['os'], $row['country'], $row['utm_source'], $row['utm_medium'], $row['utm_campaign'], $row['duration'], $row['created_at']]);
    }
    fclose($out);
    exit;
}

// ===== Comparison Periods =====
if ($action === 'comparison') {
    $prevRange = $range * 2;
    $prevSQL = "created_at >= DATE_SUB(NOW(), INTERVAL {$prevRange} DAY) AND created_at < DATE_SUB(NOW(), INTERVAL {$range} DAY)";

    $currentViews = (int)$db->query("SELECT COUNT(*) FROM page_views WHERE {$rangeSQL}")->fetchColumn();
    $prevViews = (int)$db->query("SELECT COUNT(*) FROM page_views WHERE {$prevSQL}")->fetchColumn();
    $currentVisitors = (int)$db->query("SELECT COUNT(DISTINCT ip_address) FROM page_views WHERE {$rangeSQL}")->fetchColumn();
    $prevVisitors = (int)$db->query("SELECT COUNT(DISTINCT ip_address) FROM page_views WHERE {$prevSQL}")->fetchColumn();

    $currentBounce = 0; $prevBounce = 0;
    $cs = $db->query("SELECT COUNT(DISTINCT session_id) FROM page_views WHERE {$rangeSQL} AND session_id IS NOT NULL AND session_id != ''")->fetchColumn();
    $cb = $db->query("SELECT COUNT(*) FROM (SELECT session_id FROM page_views WHERE {$rangeSQL} AND session_id IS NOT NULL AND session_id != '' GROUP BY session_id HAVING COUNT(*) = 1) t")->fetchColumn();
    $currentBounce = $cs > 0 ? round(($cb / $cs) * 100) : 0;

    $ps = $db->query("SELECT COUNT(DISTINCT session_id) FROM page_views WHERE {$prevSQL} AND session_id IS NOT NULL AND session_id != ''")->fetchColumn();
    $pb = $db->query("SELECT COUNT(*) FROM (SELECT session_id FROM page_views WHERE {$prevSQL} AND session_id IS NOT NULL AND session_id != '' GROUP BY session_id HAVING COUNT(*) = 1) t")->fetchColumn();
    $prevBounce = $ps > 0 ? round(($pb / $ps) * 100) : 0;

    $currentDuration = (int)$db->query("SELECT COALESCE(ROUND(AVG(duration)), 0) FROM page_views WHERE {$rangeSQL} AND duration > 0")->fetchColumn();
    $prevDuration = (int)$db->query("SELECT COALESCE(ROUND(AVG(duration)), 0) FROM page_views WHERE {$prevSQL} AND duration > 0")->fetchColumn();

    jsonResponse([
        'current' => ['views' => $currentViews, 'visitors' => $currentVisitors, 'bounce_rate' => $currentBounce, 'avg_duration' => $currentDuration],
        'previous' => ['views' => $prevViews, 'visitors' => $prevVisitors, 'bounce_rate' => $prevBounce, 'avg_duration' => $prevDuration],
    ]);
}

// ===== HELPERS =====
function detectDevice(string $ua): string {
    $ua = strtolower($ua);
    if (preg_match('/tablet|ipad|playbook|silk/i', $ua)) return 'tablet';
    if (preg_match('/mobile|android|iphone|ipod|opera mini|iemobile/i', $ua)) return 'mobile';
    return 'desktop';
}

function detectBrowser(string $ua): string {
    if (strpos($ua, 'Edg/') !== false) return 'Edge';
    if (strpos($ua, 'Chrome/') !== false) return 'Chrome';
    if (strpos($ua, 'Firefox/') !== false) return 'Firefox';
    if (strpos($ua, 'Safari/') !== false && strpos($ua, 'Chrome') === false) return 'Safari';
    if (strpos($ua, 'Opera') !== false || strpos($ua, 'OPR/') !== false) return 'Opera';
    return 'Other';
}

function detectOS(string $ua): string {
    if (stripos($ua, 'Windows') !== false) return 'Windows';
    if (stripos($ua, 'Mac') !== false) return 'macOS';
    if (stripos($ua, 'Linux') !== false) return 'Linux';
    if (stripos($ua, 'Android') !== false) return 'Android';
    if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) return 'iOS';
    return 'Other';
}

jsonResponse(['error' => 'Invalid action.'], 400);
