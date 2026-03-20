<?php
/**
 * Email API — admin email management
 *
 * POST: ?action=test_smtp     — test SMTP connection
 * POST: ?action=send_campaign — actually send a campaign to all active subscribers
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/mailer.php';
require_once '../includes/logger.php';

startSecureSession();
requireRole('super_admin');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ===== Test SMTP =====
if ($action === 'test_smtp' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRF($token)) { setFlash('error', 'Invalid request.'); redirect('../email.php'); }

    $testEmail = sanitize($_POST['test_email'] ?? '');
    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        setFlash('error', 'Enter a valid email address.');
        redirect('../email.php');
    }

    $mailer = new Mailer();
    $html = getEmailWrapper('
        <h2 style="font-family:\'Space Grotesk\',sans-serif;font-size:24px;font-weight:700;color:#f0f0f5;margin:0 0 16px;">SMTP Test Successful!</h2>
        <p style="color:#9999aa;">If you\'re reading this, your Core Chain admin email system is working correctly.</p>
        <p style="color:#9999aa;">Server: ' . SMTP_HOST . '<br>Port: ' . SMTP_PORT . '<br>From: ' . SMTP_FROM_EMAIL . '</p>
        <p style="color:#9999aa;">— Core Chain Admin</p>
    ');

    if ($mailer->send($testEmail, 'Core Chain — SMTP Test', $html)) {
        logActivity($_SESSION['admin_id'], 'test_smtp', 'email', null, ['to' => $testEmail]);
        setFlash('success', "Test email sent to {$testEmail}!");
    } else {
        $errors = implode('; ', $mailer->getErrors());
        setFlash('error', "SMTP test failed: {$errors}");
    }
    redirect('../email.php');
}

// ===== Send Campaign =====
if ($action === 'send_campaign' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRF($token)) { setFlash('error', 'Invalid request.'); redirect('../newsletter.php?tab=campaigns'); }

    $campaignId = (int)($_POST['campaign_id'] ?? 0);
    if (!$campaignId) { setFlash('error', 'Invalid campaign.'); redirect('../newsletter.php?tab=campaigns'); }

    $db = getDB();

    // Get campaign
    $stmt = $db->prepare('SELECT * FROM campaigns WHERE id = ?');
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch();

    if (!$campaign) { setFlash('error', 'Campaign not found.'); redirect('../newsletter.php?tab=campaigns'); }
    if ($campaign['status'] === 'sent') { setFlash('error', 'Campaign already sent.'); redirect('../newsletter.php?tab=campaigns'); }

    // Get active subscribers
    $subscribers = $db->query("SELECT id, email, unsubscribe_token FROM subscribers WHERE status = 'active'")->fetchAll();

    if (empty($subscribers)) {
        setFlash('error', 'No active subscribers to send to.');
        redirect('../newsletter.php?tab=campaigns');
    }

    // Build email with unsubscribe link
    $template = getEmailWrapper($campaign['content'], '{{unsubscribe_url}}');

    $mailer = new Mailer();
    $results = $mailer->sendBulk($subscribers, $campaign['subject'], $template);

    // Log each send
    $stmtLog = $db->prepare('INSERT INTO campaign_logs (campaign_id, subscriber_id, status, sent_at) VALUES (?, ?, ?, NOW())');
    foreach ($subscribers as $sub) {
        $stmtLog->execute([$campaignId, $sub['id'], 'sent']);
    }

    // Update campaign
    $stmt = $db->prepare('UPDATE campaigns SET status = ?, sent_at = NOW(), sent_count = ? WHERE id = ?');
    $stmt->execute(['sent', $results['sent'], $campaignId]);

    logActivity($_SESSION['admin_id'], 'send_campaign', 'campaign', $campaignId, [
        'sent' => $results['sent'],
        'failed' => $results['failed']
    ]);

    if ($results['failed'] > 0) {
        setFlash('success', "Campaign sent to {$results['sent']} subscribers. {$results['failed']} failed.");
    } else {
        setFlash('success', "Campaign sent successfully to {$results['sent']} subscribers!");
    }
    redirect('../newsletter.php?tab=campaigns');
}

jsonResponse(['error' => 'Invalid action.'], 400);
