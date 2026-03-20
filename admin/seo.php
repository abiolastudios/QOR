<?php
require_once 'includes/auth.php';
require_once 'includes/layout.php';

requireRole('super_admin', 'editor');

$db = getDB();
try { $db->exec(file_get_contents(__DIR__ . '/includes/schema_seo.sql')); } catch (Exception $e) {}

$tab = $_GET['tab'] ?? 'pages';
$editPage = $_GET['edit'] ?? '';

// All site pages
$sitePages = [
    ['file' => 'index.html', 'name' => 'Home'],
    ['file' => 'about.html', 'name' => 'About'],
    ['file' => 'ecosystem.html', 'name' => 'Ecosystem'],
    ['file' => 'tokenomics.html', 'name' => 'Tokenomics'],
    ['file' => 'security.html', 'name' => 'Security'],
    ['file' => 'blog.html', 'name' => 'Blog'],
    ['file' => 'contact.html', 'name' => 'Contact + FAQ'],
    ['file' => 'compliance.html', 'name' => 'ISO 20022 Compliance'],
    ['file' => 'zk-compression.html', 'name' => 'ZK Compression'],
    ['file' => 'cross-chain.html', 'name' => 'Cross-Chain'],
    ['file' => 'privacy.html', 'name' => 'Privacy Policy'],
    ['file' => 'terms.html', 'name' => 'Terms of Service'],
];

// Fetch saved SEO data
$seoData = [];
try {
    $rows = $db->query('SELECT * FROM seo_pages')->fetchAll();
    foreach ($rows as $row) { $seoData[$row['page_file']] = $row; }
} catch (Exception $e) {}

// Editing a specific page
$editing = null;
if ($editPage) {
    $editing = $seoData[$editPage] ?? null;
    // Find page name
    foreach ($sitePages as $p) {
        if ($p['file'] === $editPage) {
            if (!$editing) {
                $editing = ['page_file' => $editPage, 'page_name' => $p['name'], 'meta_title' => '', 'meta_description' => '', 'og_title' => '', 'og_description' => '', 'og_image' => '', 'canonical_url' => '', 'no_index' => 0, 'custom_head' => ''];
            }
            break;
        }
    }
}

// Read robots.txt
$robotsPath = realpath(__DIR__ . '/../') . '/robots.txt';
$robotsContent = file_exists($robotsPath) ? file_get_contents($robotsPath) : "User-agent: *\nAllow: /\n\nSitemap: " . APP_URL . "/sitemap.xml";

// Check sitemap
$sitemapExists = file_exists(realpath(__DIR__ . '/../') . '/sitemap.xml');

renderHeader('SEO Manager', 'seo');
?>

<!-- Tabs -->
<div class="tabs">
    <a href="seo.php?tab=pages" class="tab <?= $tab === 'pages' ? 'tab-active' : '' ?>">Pages</a>
    <a href="seo.php?tab=robots" class="tab <?= $tab === 'robots' ? 'tab-active' : '' ?>">robots.txt</a>
    <a href="seo.php?tab=sitemap" class="tab <?= $tab === 'sitemap' ? 'tab-active' : '' ?>">Sitemap</a>
</div>

<?php if ($tab === 'pages' && $editing): ?>
<!-- Edit Page SEO -->
<div class="msg-back">
    <a href="seo.php" class="btn btn-ghost btn-sm">
        <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/></svg>
        Back to Pages
    </a>
</div>

<form method="POST" action="api/seo.php?action=save">
    <?= csrfField() ?>
    <input type="hidden" name="page_file" value="<?= sanitize($editing['page_file']) ?>">
    <input type="hidden" name="page_name" value="<?= sanitize($editing['page_name']) ?>">

    <div class="editor-grid">
        <div class="editor-main">
            <div class="card">
                <div class="card-header"><h2>SEO — <?= sanitize($editing['page_name']) ?></h2></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Meta Title <span class="char-count" id="titleCount">0/60</span></label>
                        <input type="text" name="meta_title" value="<?= sanitize($editing['meta_title']) ?>" placeholder="Page title for search engines" maxlength="70" oninput="updateCount(this,'titleCount',60)">
                    </div>
                    <div class="form-group">
                        <label>Meta Description <span class="char-count" id="descCount">0/160</span></label>
                        <textarea name="meta_description" rows="3" placeholder="Page description for search results" maxlength="200" oninput="updateCount(this,'descCount',160)"><?= sanitize($editing['meta_description']) ?></textarea>
                    </div>

                    <h3 class="seo-section-title">Open Graph (Social Sharing)</h3>
                    <div class="form-group">
                        <label>OG Title</label>
                        <input type="text" name="og_title" value="<?= sanitize($editing['og_title']) ?>" placeholder="Title shown on Twitter/Facebook">
                    </div>
                    <div class="form-group">
                        <label>OG Description</label>
                        <textarea name="og_description" rows="2" placeholder="Description shown on social cards"><?= sanitize($editing['og_description']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>OG Image URL</label>
                        <input type="text" name="og_image" value="<?= sanitize($editing['og_image']) ?>" placeholder="assets/images/qor-logo.png">
                    </div>

                    <h3 class="seo-section-title">Advanced</h3>
                    <div class="form-group">
                        <label>Canonical URL</label>
                        <input type="text" name="canonical_url" value="<?= sanitize($editing['canonical_url']) ?>" placeholder="https://corechain.io/page">
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="no_index" value="1" <?= $editing['no_index'] ? 'checked' : '' ?>>
                            <span>noindex — Hide from search engines</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Custom Head Code</label>
                        <textarea name="custom_head" rows="3" placeholder="Additional HTML for <head> section" style="font-family:monospace;font-size:0.8rem;"><?= htmlspecialchars($editing['custom_head'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="editor-sidebar">
            <!-- Google Preview -->
            <div class="card">
                <div class="card-header"><h2>Google Preview</h2></div>
                <div class="card-body">
                    <div class="seo-preview-google">
                        <div class="seo-goog-title" id="previewTitle"><?= sanitize($editing['meta_title'] ?: $editing['page_name'] . ' — Core Chain') ?></div>
                        <div class="seo-goog-url"><?= APP_URL ?>/<?= $editing['page_file'] ?></div>
                        <div class="seo-goog-desc" id="previewDesc"><?= sanitize($editing['meta_description'] ?: 'No description set.') ?></div>
                    </div>
                </div>
            </div>

            <!-- Twitter Preview -->
            <div class="card">
                <div class="card-header"><h2>Twitter Preview</h2></div>
                <div class="card-body">
                    <div class="seo-preview-twitter">
                        <div class="seo-tw-image"></div>
                        <div class="seo-tw-body">
                            <div class="seo-tw-domain"><?= parse_url(APP_URL, PHP_URL_HOST) ?></div>
                            <div class="seo-tw-title" id="previewOgTitle"><?= sanitize($editing['og_title'] ?: $editing['meta_title'] ?: $editing['page_name']) ?></div>
                            <div class="seo-tw-desc" id="previewOgDesc"><?= sanitize($editing['og_description'] ?: $editing['meta_description'] ?: '') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="editor-actions">
                <button type="submit" class="btn btn-primary btn-full">Save SEO Settings</button>
            </div>
        </div>
    </div>
</form>

<script>
function updateCount(el, countId, max) {
    const len = el.value.length;
    const counter = document.getElementById(countId);
    counter.textContent = len + '/' + max;
    counter.style.color = len > max ? 'var(--red)' : 'var(--text-muted)';
}
// Init counts
document.querySelectorAll('[oninput]').forEach(el => { const e = new Event('input'); el.dispatchEvent(e); });

// Live preview
const titleInput = document.querySelector('[name="meta_title"]');
const descInput = document.querySelector('[name="meta_description"]');
const ogTitleInput = document.querySelector('[name="og_title"]');
const ogDescInput = document.querySelector('[name="og_description"]');

if (titleInput) titleInput.addEventListener('input', () => { document.getElementById('previewTitle').textContent = titleInput.value || '<?= sanitize($editing['page_name']) ?> — Core Chain'; });
if (descInput) descInput.addEventListener('input', () => { document.getElementById('previewDesc').textContent = descInput.value || 'No description set.'; });
if (ogTitleInput) ogTitleInput.addEventListener('input', () => { document.getElementById('previewOgTitle').textContent = ogTitleInput.value || titleInput.value || '<?= sanitize($editing['page_name']) ?>'; });
if (ogDescInput) ogDescInput.addEventListener('input', () => { document.getElementById('previewOgDesc').textContent = ogDescInput.value || descInput.value || ''; });
</script>

<?php elseif ($tab === 'pages'): ?>
<!-- Pages List -->
<div class="card">
    <div class="card-body no-pad">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Page</th>
                        <th>Meta Title</th>
                        <th>Description</th>
                        <th>Indexed</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sitePages as $p): ?>
                    <?php $seo = $seoData[$p['file']] ?? null; ?>
                    <tr>
                        <td><strong><?= $p['name'] ?></strong><br><code style="font-size:0.7rem;color:var(--text-muted)"><?= $p['file'] ?></code></td>
                        <td class="msg-preview"><?= $seo ? sanitize(substr($seo['meta_title'], 0, 40)) . (strlen($seo['meta_title']) > 40 ? '...' : '') : '<span class="text-muted">Not set</span>' ?></td>
                        <td class="msg-preview"><?= $seo ? sanitize(substr($seo['meta_description'], 0, 50)) . (strlen($seo['meta_description']) > 50 ? '...' : '') : '<span class="text-muted">Not set</span>' ?></td>
                        <td><?= ($seo && $seo['no_index']) ? '<span class="badge-red">No</span>' : '<span class="badge-green">Yes</span>' ?></td>
                        <td>
                            <?php if ($seo && $seo['meta_title'] && $seo['meta_description']): ?>
                            <span class="badge-green">Complete</span>
                            <?php elseif ($seo): ?>
                            <span class="badge-orange">Partial</span>
                            <?php else: ?>
                            <span class="badge-gray">Default</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="seo.php?edit=<?= urlencode($p['file']) ?>" class="btn btn-secondary btn-sm">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($tab === 'robots'): ?>
<!-- robots.txt -->
<div class="card">
    <div class="card-header"><h2>robots.txt</h2></div>
    <div class="card-body">
        <form method="POST" action="api/seo.php?action=save_robots">
            <?= csrfField() ?>
            <div class="form-group">
                <textarea name="robots_content" rows="12" style="font-family:monospace;font-size:0.85rem;"><?= htmlspecialchars($robotsContent) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:12px">Save robots.txt</button>
        </form>
    </div>
</div>

<?php elseif ($tab === 'sitemap'): ?>
<!-- Sitemap -->
<div class="card">
    <div class="card-header"><h2>Sitemap</h2></div>
    <div class="card-body">
        <div class="info-list">
            <div class="info-item">
                <span class="info-label">sitemap.xml</span>
                <span class="info-value"><?= $sitemapExists ? '<span class="badge-green">Exists</span>' : '<span class="badge-red">Not generated</span>' ?></span>
            </div>
            <?php if ($sitemapExists): ?>
            <div class="info-item">
                <span class="info-label">Last Modified</span>
                <span class="info-value"><?= date('M j, Y g:i A', filemtime(realpath(__DIR__ . '/../') . '/sitemap.xml')) ?></span>
            </div>
            <div class="info-item" style="border:none">
                <span class="info-label">View</span>
                <span class="info-value"><a href="../sitemap.xml" target="_blank" style="color:var(--blue)">Open sitemap.xml</a></span>
            </div>
            <?php endif; ?>
        </div>

        <div style="margin-top:20px; display:flex; gap:8px;">
            <a href="api/seo.php?action=generate_sitemap" class="btn btn-primary">
                <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/></svg>
                <?= $sitemapExists ? 'Regenerate' : 'Generate' ?> Sitemap
            </a>
        </div>

        <p style="font-size:0.8rem;color:var(--text-muted);margin-top:16px;">Includes all site pages + published blog posts. Excludes noindex pages.</p>
    </div>
</div>
<?php endif; ?>

<?php renderFooter(); ?>
