<?php
require_once 'includes/auth.php';
require_once 'includes/layout.php';

requireLogin();

$db = getDB();
try { $db->exec(file_get_contents(__DIR__ . '/includes/schema_chat.sql')); } catch (Exception $e) {}

$tab = $_GET['tab'] ?? 'sessions';

// Handle config update
if (isPost() && $_GET['action'] === 'save_config') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (validateCSRF($token)) {
        $fields = ['enabled', 'greeting', 'bot_name', 'suggested_questions', 'fallback_message', 'primary_color'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) {
                $val = $f === 'enabled' ? '1' : sanitize($_POST[$f]);
                $db->prepare('INSERT INTO chat_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = ?')
                    ->execute([$f, $val, $val]);
            }
        }
        if (!isset($_POST['enabled'])) {
            $db->prepare('UPDATE chat_config SET config_value = ? WHERE config_key = ?')->execute(['0', 'enabled']);
        }
        require_once 'includes/logger.php';
        logActivity($_SESSION['admin_id'], 'update_chatbot', 'chatbot');
        setFlash('success', 'Chatbot settings saved.');
    }
    redirect('chatbot.php?tab=settings');
}

// Get config
$config = [];
try {
    $rows = $db->query('SELECT config_key, config_value FROM chat_config')->fetchAll();
    foreach ($rows as $r) { $config[$r['config_key']] = $r['config_value']; }
} catch (Exception $e) {}

// Stats
$totalSessions = $db->query('SELECT COUNT(*) FROM chat_sessions')->fetchColumn();
$todaySessions = $db->query("SELECT COUNT(*) FROM chat_sessions WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$totalMessages = $db->query('SELECT COUNT(*) FROM chat_messages')->fetchColumn();
$emailsCaptured = $db->query("SELECT COUNT(*) FROM chat_sessions WHERE visitor_email IS NOT NULL")->fetchColumn();

// Sessions list
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;
$totalPages = max(1, ceil($totalSessions / $perPage));

$sessions = $db->prepare("SELECT cs.*, (SELECT COUNT(*) FROM chat_messages WHERE session_id = cs.id) as msg_count, (SELECT message FROM chat_messages WHERE session_id = cs.id AND role = 'user' ORDER BY created_at ASC LIMIT 1) as first_message FROM chat_sessions cs ORDER BY cs.updated_at DESC LIMIT {$perPage} OFFSET {$offset}");
$sessions->execute();
$sessionList = $sessions->fetchAll();

// View single session
$viewId = (int)($_GET['view'] ?? 0);
$viewSession = null;
$viewMessages = [];
if ($viewId) {
    $stmt = $db->prepare('SELECT * FROM chat_sessions WHERE id = ?');
    $stmt->execute([$viewId]);
    $viewSession = $stmt->fetch();
    if ($viewSession) {
        $stmt = $db->prepare('SELECT * FROM chat_messages WHERE session_id = ? ORDER BY created_at ASC');
        $stmt->execute([$viewId]);
        $viewMessages = $stmt->fetchAll();
    }
}

renderHeader('Chatbot', 'chatbot');
?>

<div class="stats-row">
    <div class="stat-widget">
        <div class="stat-widget-icon blue">
            <svg viewBox="0 0 20 20" fill="currentColor" width="22" height="22"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/></svg>
        </div>
        <div class="stat-widget-data">
            <span class="stat-widget-value"><?= number_format($totalSessions) ?></span>
            <span class="stat-widget-label">Total Sessions</span>
        </div>
    </div>
    <div class="stat-widget">
        <div class="stat-widget-icon green">
            <svg viewBox="0 0 20 20" fill="currentColor" width="22" height="22"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
        </div>
        <div class="stat-widget-data">
            <span class="stat-widget-value"><?= number_format($todaySessions) ?></span>
            <span class="stat-widget-label">Today</span>
        </div>
    </div>
    <div class="stat-widget">
        <div class="stat-widget-icon orange">
            <svg viewBox="0 0 20 20" fill="currentColor" width="22" height="22"><path d="M2 5a2 2 0 012-2h7a2 2 0 012 2v4a2 2 0 01-2 2H9l-3 3v-3H4a2 2 0 01-2-2V5z"/><path d="M15 7v2a4 4 0 01-4 4H9.828l-1.766 1.767c.28.149.599.233.938.233h2l3 3v-3h2a2 2 0 002-2V9a2 2 0 00-2-2h-1z"/></svg>
        </div>
        <div class="stat-widget-data">
            <span class="stat-widget-value"><?= number_format($totalMessages) ?></span>
            <span class="stat-widget-label">Messages</span>
        </div>
    </div>
    <div class="stat-widget">
        <div class="stat-widget-icon purple">
            <svg viewBox="0 0 20 20" fill="currentColor" width="22" height="22"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
        </div>
        <div class="stat-widget-data">
            <span class="stat-widget-value"><?= number_format($emailsCaptured) ?></span>
            <span class="stat-widget-label">Emails Captured</span>
        </div>
    </div>
</div>

<div class="tabs">
    <a href="chatbot.php?tab=sessions" class="tab <?= $tab === 'sessions' && !$viewId ? 'tab-active' : '' ?>">Sessions</a>
    <a href="chatbot.php?tab=settings" class="tab <?= $tab === 'settings' ? 'tab-active' : '' ?>">Settings</a>
</div>

<?php if ($viewSession): ?>
<!-- Chat History -->
<div class="msg-back">
    <a href="chatbot.php" class="btn btn-ghost btn-sm">
        <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/></svg>
        Back to Sessions
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h2>Chat #<?= $viewSession['id'] ?></h2>
        <div style="display:flex;gap:8px;align-items:center">
            <?php if ($viewSession['visitor_email']): ?>
            <span class="badge-blue"><?= sanitize($viewSession['visitor_email']) ?></span>
            <?php endif; ?>
            <span class="msg-date"><?= date('M j, Y g:i A', strtotime($viewSession['created_at'])) ?></span>
        </div>
    </div>
    <div class="card-body">
        <div class="chat-history">
            <?php foreach ($viewMessages as $msg): ?>
            <div class="chat-msg chat-msg-<?= $msg['role'] ?>">
                <div class="chat-msg-bubble">
                    <div class="chat-msg-role"><?= $msg['role'] === 'user' ? 'Visitor' : ($msg['role'] === 'bot' ? ($config['bot_name'] ?? 'Bot') : 'Admin') ?></div>
                    <div class="chat-msg-text"><?= nl2br(sanitize($msg['message'])) ?></div>
                    <div class="chat-msg-time"><?= date('g:i A', strtotime($msg['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php elseif ($tab === 'settings'): ?>
<!-- Settings -->
<form method="POST" action="chatbot.php?action=save_config">
    <?= csrfField() ?>
    <div class="editor-grid">
        <div class="editor-main">
            <div class="card">
                <div class="card-header"><h2>Chatbot Settings</h2></div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="enabled" value="1" <?= ($config['enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                            <span>Enable chatbot widget on website</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Bot Name</label>
                        <input type="text" name="bot_name" value="<?= sanitize($config['bot_name'] ?? 'Core Chain Bot') ?>">
                    </div>
                    <div class="form-group">
                        <label>Greeting Message</label>
                        <textarea name="greeting" rows="3"><?= sanitize($config['greeting'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Fallback Message (when bot can't answer)</label>
                        <textarea name="fallback_message" rows="2"><?= sanitize($config['fallback_message'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Suggested Questions (JSON array)</label>
                        <textarea name="suggested_questions" rows="3" style="font-family:monospace;font-size:0.8rem"><?= sanitize($config['suggested_questions'] ?? '[]') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Primary Color</label>
                        <input type="color" name="primary_color" value="<?= $config['primary_color'] ?? '#4FC3F7' ?>" style="width:60px;height:36px;padding:2px;cursor:pointer">
                    </div>
                </div>
            </div>
        </div>
        <div class="editor-sidebar">
            <div class="card">
                <div class="card-header"><h2>Info</h2></div>
                <div class="card-body">
                    <p style="font-size:0.85rem;color:var(--text-secondary);line-height:1.6;">The chatbot uses a rule-based knowledge base trained on Core Chain content. It responds to questions about biometrics, tokenomics, security, ZK, staking, and more.</p>
                    <p style="font-size:0.8rem;color:var(--text-muted);margin-top:12px;">Knowledge base: <code>admin/includes/chatbot_knowledge.php</code></p>
                    <button type="submit" class="btn btn-primary btn-full" style="margin-top:16px">Save Settings</button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php else: ?>
<!-- Sessions List -->
<div class="card">
    <div class="card-body no-pad">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Visitor</th>
                        <th>First Message</th>
                        <th>Messages</th>
                        <th>Status</th>
                        <th>Started</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sessionList)): ?>
                    <tr><td colspan="6" class="empty-state">No chat sessions yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($sessionList as $s): ?>
                    <tr class="msg-row" onclick="window.location='chatbot.php?view=<?= $s['id'] ?>'" style="cursor:pointer">
                        <td class="text-muted"><?= $s['id'] ?></td>
                        <td><?= $s['visitor_email'] ? '<span class="badge-blue">' . sanitize($s['visitor_email']) . '</span>' : '<span class="text-muted">Anonymous</span>' ?></td>
                        <td class="msg-preview"><?= $s['first_message'] ? sanitize(substr($s['first_message'], 0, 60)) : '<span class="text-muted">—</span>' ?></td>
                        <td><?= $s['msg_count'] ?></td>
                        <td><span class="status-dot status-dot-<?= $s['status'] === 'active' ? 'new' : 'archived' ?>"></span></td>
                        <td><?= timeAgo($s['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>" class="btn btn-secondary btn-sm">Previous</a><?php endif; ?>
    <span class="pagination-info">Page <?= $page ?> of <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>" class="btn btn-secondary btn-sm">Next</a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php renderFooter(); ?>
