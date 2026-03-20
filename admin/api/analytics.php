<?php
/**
 * Analytics API
 *
 * POST (public): ?action=track     — track page view
 * GET  (admin):  ?action=dashboard — get dashboard stats
 * GET  (admin):  ?action=pages     — top pages
 * GET  (admin):  ?action=referrers — top referrers
 * GET  (admin):  ?action=chart     — daily views chart data
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

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $device = detectDevice($ua);
    $browser = detectBrowser($ua);
    $os = detectOS($ua);

    try {
        $db = getDB();
        $db->exec(file_get_contents(__DIR__ . '/../includes/schema_analytics.sql'));

        $stmt = $db->prepare('INSERT INTO page_views (page_path, page_title, referrer, ip_address, user_agent, device_type, browser, os, session_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            $pagePath, $pageTitle, $referrer,
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($ua, 0, 500),
            $device, $browser, $os, $sessionId
        ]);

        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false], 500);
    }
}

// ===== ADMIN ENDPOINTS =====
if (in_array($action, ['dashboard', 'pages', 'referrers', 'chart'])) {
    require_once '../includes/auth.php';
    startSecureSession();
    requireLogin();

    $db = getDB();
    try { $db->exec(file_get_contents(__DIR__ . '/../includes/schema_analytics.sql')); } catch (Exception $e) {}

    $range = $_GET['range'] ?? '7';
    $rangeSQL = "created_at >= DATE_SUB(NOW(), INTERVAL {$range} DAY)";
}

// ===== Dashboard Stats =====
if ($action === 'dashboard') {
    $totalViews = $db->query("SELECT COUNT(*) FROM page_views WHERE {$rangeSQL}")->fetchColumn();
    $uniqueVisitors = $db->query("SELECT COUNT(DISTINCT ip_address) FROM page_views WHERE {$rangeSQL}")->fetchColumn();
    $todayViews = $db->query("SELECT COUNT(*) FROM page_views WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $avgPerDay = $range > 0 ? round($totalViews / $range) : $totalViews;

    // Device breakdown
    $devices = $db->query("SELECT device_type, COUNT(*) as cnt FROM page_views WHERE {$rangeSQL} GROUP BY device_type ORDER BY cnt DESC")->fetchAll();

    // Top browsers
    $browsers = $db->query("SELECT browser, COUNT(*) as cnt FROM page_views WHERE {$rangeSQL} AND browser IS NOT NULL GROUP BY browser ORDER BY cnt DESC LIMIT 5")->fetchAll();

    // Top OS
    $oses = $db->query("SELECT os, COUNT(*) as cnt FROM page_views WHERE {$rangeSQL} AND os IS NOT NULL GROUP BY os ORDER BY cnt DESC LIMIT 5")->fetchAll();

    jsonResponse([
        'total_views' => (int)$totalViews,
        'unique_visitors' => (int)$uniqueVisitors,
        'today_views' => (int)$todayViews,
        'avg_per_day' => (int)$avgPerDay,
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
