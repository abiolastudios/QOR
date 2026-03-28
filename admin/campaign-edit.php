<?php
require_once 'includes/auth.php';
require_once 'includes/layout.php';

requireRole('super_admin', 'editor');

$db = getDB();
try { $db->exec(file_get_contents(__DIR__ . '/includes/schema_subscribers.sql')); } catch (Exception $e) {}

$id = (int)($_GET['id'] ?? 0);
$campaign = null;

if ($id) {
    $stmt = $db->prepare('SELECT * FROM campaigns WHERE id = ?');
    $stmt->execute([$id]);
    $campaign = $stmt->fetch();
    if (!$campaign) { setFlash('error', 'Campaign not found.'); redirect('newsletter.php?tab=campaigns'); }
}

$activeCount = $db->query("SELECT COUNT(*) FROM subscribers WHERE status = 'active'")->fetchColumn();

// Load tags and segments for audience selector
$allTags = $db->query('SELECT t.*, (SELECT COUNT(*) FROM subscriber_tags WHERE tag_id = t.id) as sub_count FROM tags t ORDER BY t.name')->fetchAll();
$allSegments = [];
try { $allSegments = $db->query('SELECT * FROM segments ORDER BY name')->fetchAll(); } catch (Exception $e) {}

$pageTitle = $campaign ? 'Edit Campaign' : 'New Campaign';

renderHeader($pageTitle, 'newsletter');
?>

<div class="msg-back">
    <a href="newsletter.php?tab=campaigns" class="btn btn-ghost btn-sm">
        <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/></svg>
        Back to Campaigns
    </a>
</div>

<form method="POST" action="api/newsletter.php?action=save_campaign">
    <?= csrfField() ?>
    <?php if ($campaign): ?><input type="hidden" name="id" value="<?= $campaign['id'] ?>"><?php endif; ?>

    <div class="editor-grid">
        <div class="editor-main">
            <div class="card">
                <div class="card-body">
                    <div class="form-group">
                        <label>Subject Line</label>
                        <input type="text" name="subject" value="<?= sanitize($campaign['subject'] ?? '') ?>" placeholder="Your email subject..." required class="editor-title-input">
                    </div>

                    <div class="form-group">
                        <label>Email Content</label>
                        <div class="editor-toolbar">
                            <button type="button" class="toolbar-btn" onclick="execCmd('bold')"><strong>B</strong></button>
                            <button type="button" class="toolbar-btn" onclick="execCmd('italic')"><em>I</em></button>
                            <button type="button" class="toolbar-btn" onclick="execCmd('underline')"><u>U</u></button>
                            <span class="toolbar-sep"></span>
                            <button type="button" class="toolbar-btn" onclick="execCmd('formatBlock', 'h2')">H2</button>
                            <button type="button" class="toolbar-btn" onclick="execCmd('formatBlock', 'h3')">H3</button>
                            <button type="button" class="toolbar-btn" onclick="execCmd('formatBlock', 'p')">P</button>
                            <span class="toolbar-sep"></span>
                            <button type="button" class="toolbar-btn" onclick="execCmd('insertUnorderedList')">&bull;</button>
                            <button type="button" class="toolbar-btn" onclick="insertLink()">&#128279;</button>
                            <span class="toolbar-sep"></span>
                            <button type="button" class="toolbar-btn" onclick="toggleSource()" id="sourceToggle">&lt;/&gt;</button>
                        </div>
                        <div class="editor-content" id="editorContent" contenteditable="true"><?= $campaign['content'] ?? '<p>Write your email content here...</p>' ?></div>
                        <textarea name="content" id="contentHidden" style="display:none"><?= htmlspecialchars($campaign['content'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="editor-sidebar">
            <!-- Audience -->
            <div class="card">
                <div class="card-header"><h2>Audience</h2></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Send To</label>
                        <select name="audience_type" id="audienceType" onchange="updateAudienceOptions()">
                            <option value="all" <?= ($campaign['audience_type'] ?? 'all') === 'all' ? 'selected' : '' ?>>All Active Subscribers (<?= number_format($activeCount) ?>)</option>
                            <?php if (!empty($allSegments)): ?>
                            <option value="segment" <?= ($campaign['audience_type'] ?? '') === 'segment' ? 'selected' : '' ?>>Segment</option>
                            <?php endif; ?>
                            <?php if (!empty($allTags)): ?>
                            <option value="tag" <?= ($campaign['audience_type'] ?? '') === 'tag' ? 'selected' : '' ?>>Tag</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group" id="segmentSelect" style="display:<?= ($campaign['audience_type'] ?? '') === 'segment' ? 'flex' : 'none' ?>;">
                        <label>Select Segment</label>
                        <select name="audience_segment_id">
                            <?php foreach ($allSegments as $seg): ?>
                            <option value="<?= $seg['id'] ?>" <?= ($campaign['audience_type'] ?? '') === 'segment' && ($campaign['audience_id'] ?? 0) == $seg['id'] ? 'selected' : '' ?>><?= sanitize($seg['name']) ?> (~<?= $seg['subscriber_count'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="tagSelect" style="display:<?= ($campaign['audience_type'] ?? '') === 'tag' ? 'flex' : 'none' ?>;">
                        <label>Select Tag</label>
                        <select name="audience_tag_id">
                            <?php foreach ($allTags as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= ($campaign['audience_type'] ?? '') === 'tag' && ($campaign['audience_id'] ?? 0) == $t['id'] ? 'selected' : '' ?>><?= sanitize($t['name']) ?> (<?= $t['sub_count'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Send Settings -->
            <div class="card">
                <div class="card-header"><h2>Send</h2></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="statusSelect">
                            <option value="draft" <?= ($campaign['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="scheduled" <?= ($campaign['status'] ?? '') === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                        </select>
                    </div>

                    <div class="form-group" id="scheduleGroup" style="display:<?= ($campaign['status'] ?? '') === 'scheduled' ? 'flex' : 'none' ?>">
                        <label>Schedule Date</label>
                        <input type="datetime-local" name="scheduled_at" value="<?= $campaign['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($campaign['scheduled_at'])) : '' ?>">
                    </div>

                    <div class="editor-actions">
                        <button type="submit" class="btn btn-primary btn-full" onclick="syncContent()">
                            <?= $campaign ? 'Update Campaign' : 'Save Campaign' ?>
                        </button>
                    </div>

                    <p style="font-size:0.75rem; color:var(--text-muted); margin-top:12px; text-align:center;">Save as draft, then send from the campaigns list.</p>

                    <?php if ($campaign): ?>
                    <div class="editor-meta">
                        <span>Created: <?= date('M j, Y', strtotime($campaign['created_at'])) ?></span>
                        <?php if ($campaign['sent_at'] ?? null): ?>
                        <span>Sent: <?= date('M j, Y g:i A', strtotime($campaign['sent_at'])) ?></span>
                        <span>Sent to: <?= number_format($campaign['sent_count']) ?></span>
                        <span>Opens: <?= number_format($campaign['open_count']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.getElementById('statusSelect').addEventListener('change', function() {
    document.getElementById('scheduleGroup').style.display = this.value === 'scheduled' ? 'flex' : 'none';
});

function updateAudienceOptions() {
    const type = document.getElementById('audienceType').value;
    document.getElementById('segmentSelect').style.display = type === 'segment' ? 'flex' : 'none';
    document.getElementById('tagSelect').style.display = type === 'tag' ? 'flex' : 'none';
}

function execCmd(cmd, value) { document.execCommand(cmd, false, value || null); document.getElementById('editorContent').focus(); }
function insertLink() { const url = prompt('Enter URL:'); if (url) document.execCommand('createLink', false, url); }

let sourceMode = false;
function toggleSource() {
    const editor = document.getElementById('editorContent');
    const btn = document.getElementById('sourceToggle');
    if (sourceMode) { editor.innerHTML = editor.innerText; btn.style.color = ''; sourceMode = false; }
    else { editor.innerText = editor.innerHTML; btn.style.color = 'var(--blue)'; sourceMode = true; }
}

function syncContent() {
    const editor = document.getElementById('editorContent');
    document.getElementById('contentHidden').value = sourceMode ? editor.innerText : editor.innerHTML;
}
</script>

<?php renderFooter(); ?>
