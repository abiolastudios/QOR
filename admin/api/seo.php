<?php
/**
 * SEO API
 *
 * POST (admin): ?action=save        — save page SEO settings
 * GET  (admin): ?action=generate_sitemap — generate sitemap.xml
 * POST (admin): ?action=save_robots — save robots.txt
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/logger.php';

startSecureSession();
requireRole('super_admin', 'editor');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ===== Save Page SEO =====
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRF($token)) { setFlash('error', 'Invalid request.'); redirect('../seo.php'); }

    $db = getDB();
    $db->exec(file_get_contents(__DIR__ . '/../includes/schema_seo.sql'));

    $pageFile = sanitize($_POST['page_file'] ?? '');
    $pageName = sanitize($_POST['page_name'] ?? '');
    $metaTitle = sanitize($_POST['meta_title'] ?? '');
    $metaDesc = sanitize($_POST['meta_description'] ?? '');
    $ogTitle = sanitize($_POST['og_title'] ?? '');
    $ogDesc = sanitize($_POST['og_description'] ?? '');
    $ogImage = sanitize($_POST['og_image'] ?? '');
    $canonical = sanitize($_POST['canonical_url'] ?? '');
    $noIndex = isset($_POST['no_index']) ? 1 : 0;
    $customHead = $_POST['custom_head'] ?? '';

    if (!$pageFile) { setFlash('error', 'Page file required.'); redirect('../seo.php'); }

    // Upsert
    $stmt = $db->prepare('SELECT id FROM seo_pages WHERE page_file = ?');
    $stmt->execute([$pageFile]);

    if ($stmt->fetch()) {
        $stmt = $db->prepare('UPDATE seo_pages SET page_name=?, meta_title=?, meta_description=?, og_title=?, og_description=?, og_image=?, canonical_url=?, no_index=?, custom_head=? WHERE page_file=?');
        $stmt->execute([$pageName, $metaTitle, $metaDesc, $ogTitle, $ogDesc, $ogImage, $canonical, $noIndex, $customHead, $pageFile]);
    } else {
        $stmt = $db->prepare('INSERT INTO seo_pages (page_file, page_name, meta_title, meta_description, og_title, og_description, og_image, canonical_url, no_index, custom_head) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$pageFile, $pageName, $metaTitle, $metaDesc, $ogTitle, $ogDesc, $ogImage, $canonical, $noIndex, $customHead]);
    }

    logActivity($_SESSION['admin_id'], 'update_seo', 'seo', null, ['page' => $pageFile]);
    setFlash('success', "SEO for '{$pageName}' updated.");
    redirect('../seo.php?edit=' . urlencode($pageFile));
}

// ===== Generate Sitemap =====
if ($action === 'generate_sitemap') {
    $db = getDB();

    $baseUrl = rtrim(APP_URL, '/');
    $pages = [
        ['loc' => '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
        ['loc' => '/about.html', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['loc' => '/ecosystem.html', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['loc' => '/tokenomics.html', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['loc' => '/security.html', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['loc' => '/blog.html', 'priority' => '0.9', 'changefreq' => 'daily'],
        ['loc' => '/contact.html', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['loc' => '/compliance.html', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['loc' => '/zk-compression.html', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['loc' => '/cross-chain.html', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['loc' => '/privacy.html', 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['loc' => '/terms.html', 'priority' => '0.3', 'changefreq' => 'yearly'],
    ];

    // Check noindex pages
    try {
        $db->exec(file_get_contents(__DIR__ . '/../includes/schema_seo.sql'));
        $noIndexPages = $db->query("SELECT page_file FROM seo_pages WHERE no_index = 1")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $noIndexPages = [];
    }

    // Add published blog posts
    try {
        $posts = $db->query("SELECT slug, updated_at FROM posts WHERE status = 'published' ORDER BY published_at DESC")->fetchAll();
        foreach ($posts as $post) {
            $pages[] = [
                'loc' => '/blog-post.html?slug=' . $post['slug'],
                'priority' => '0.6',
                'changefreq' => 'weekly',
                'lastmod' => date('Y-m-d', strtotime($post['updated_at']))
            ];
        }
    } catch (Exception $e) {}

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($pages as $page) {
        $file = ltrim($page['loc'], '/');
        if ($file === '') $file = 'index.html';
        if (in_array($file, $noIndexPages)) continue;

        $xml .= "  <url>\n";
        $xml .= "    <loc>{$baseUrl}{$page['loc']}</loc>\n";
        if (isset($page['lastmod'])) {
            $xml .= "    <lastmod>{$page['lastmod']}</lastmod>\n";
        } else {
            $xml .= "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
        }
        $xml .= "    <changefreq>{$page['changefreq']}</changefreq>\n";
        $xml .= "    <priority>{$page['priority']}</priority>\n";
        $xml .= "  </url>\n";
    }

    $xml .= '</urlset>';

    $sitemapPath = realpath(__DIR__ . '/../../') . '/sitemap.xml';
    file_put_contents($sitemapPath, $xml);

    logActivity($_SESSION['admin_id'], 'generate_sitemap', 'seo');
    setFlash('success', 'sitemap.xml generated with ' . count($pages) . ' URLs.');
    redirect('../seo.php');
}

// ===== Save robots.txt =====
if ($action === 'save_robots' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRF($token)) { setFlash('error', 'Invalid request.'); redirect('../seo.php'); }

    $content = $_POST['robots_content'] ?? '';
    $robotsPath = realpath(__DIR__ . '/../../') . '/robots.txt';
    file_put_contents($robotsPath, $content);

    logActivity($_SESSION['admin_id'], 'update_robots', 'seo');
    setFlash('success', 'robots.txt saved.');
    redirect('../seo.php?tab=robots');
}

jsonResponse(['error' => 'Invalid action.'], 400);
