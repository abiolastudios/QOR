<?php
/**
 * Chat API
 *
 * POST (public): ?action=start    — start chat session
 * POST (public): ?action=message  — send message, get bot response
 * POST (public): ?action=email    — capture visitor email
 * GET  (admin):  ?action=sessions — list chat sessions
 * GET  (admin):  ?action=history  — get session messages
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

$action = $_GET['action'] ?? '';

// ===== PUBLIC: Start Session =====
if ($action === 'start' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        $db->exec(file_get_contents(__DIR__ . '/../includes/schema_chat.sql'));

        $token = bin2hex(random_bytes(32));
        $stmt = $db->prepare('INSERT INTO chat_sessions (session_token, ip_address, created_at) VALUES (?, ?, NOW())');
        $stmt->execute([$token, $_SERVER['REMOTE_ADDR'] ?? '']);

        // Get config
        $config = [];
        $rows = $db->query('SELECT config_key, config_value FROM chat_config')->fetchAll();
        foreach ($rows as $r) { $config[$r['config_key']] = $r['config_value']; }

        jsonResponse([
            'success' => true,
            'session_token' => $token,
            'greeting' => $config['greeting'] ?? 'Hi! How can I help?',
            'bot_name' => $config['bot_name'] ?? 'Core Chain Bot',
            'suggested' => json_decode($config['suggested_questions'] ?? '[]', true),
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Chat unavailable.'], 500);
    }
}

// ===== PUBLIC: Send Message =====
if ($action === 'message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['session_token'] ?? '';
    $message = trim($input['message'] ?? '');

    if (!$token || !$message) { jsonResponse(['success' => false, 'message' => 'Invalid request.'], 400); }

    try {
        $db = getDB();

        // Find session
        $stmt = $db->prepare('SELECT id FROM chat_sessions WHERE session_token = ?');
        $stmt->execute([$token]);
        $session = $stmt->fetch();

        if (!$session) { jsonResponse(['success' => false, 'message' => 'Session expired.'], 404); }

        $sessionId = $session['id'];

        // Save user message
        $db->prepare('INSERT INTO chat_messages (session_id, role, message, created_at) VALUES (?, ?, ?, NOW())')
            ->execute([$sessionId, 'user', $message]);

        // Update session timestamp
        $db->prepare('UPDATE chat_sessions SET updated_at = NOW() WHERE id = ?')->execute([$sessionId]);

        // Get bot response
        require_once '../includes/chatbot_knowledge.php';
        $botResponse = findBotResponse($message);

        if (!$botResponse) {
            // Get fallback from config
            $stmt = $db->prepare("SELECT config_value FROM chat_config WHERE config_key = 'fallback_message'");
            $stmt->execute();
            $botResponse = $stmt->fetchColumn() ?: "I'm not sure about that. You can reach our team at hello@corechain.io or check our FAQ at the Contact page.";
        }

        // Save bot response
        $db->prepare('INSERT INTO chat_messages (session_id, role, message, created_at) VALUES (?, ?, ?, NOW())')
            ->execute([$sessionId, 'bot', $botResponse]);

        jsonResponse(['success' => true, 'response' => $botResponse]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Error processing message.'], 500);
    }
}

// ===== PUBLIC: Capture Email =====
if ($action === 'email' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['session_token'] ?? '';
    $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $name = sanitize($input['name'] ?? '');

    if (!$token || !$email) { jsonResponse(['success' => false], 400); }

    try {
        $db = getDB();
        $stmt = $db->prepare('UPDATE chat_sessions SET visitor_email = ?, visitor_name = ? WHERE session_token = ?');
        $stmt->execute([$email, $name, $token]);
        jsonResponse(['success' => true, 'message' => 'Thanks! Our team will follow up.']);
    } catch (Exception $e) {
        jsonResponse(['success' => false], 500);
    }
}

// ===== ADMIN: List Sessions =====
if ($action === 'sessions') {
    require_once '../includes/auth.php';
    startSecureSession(); requireLogin();

    $db = getDB();
    try { $db->exec(file_get_contents(__DIR__ . '/../includes/schema_chat.sql')); } catch (Exception $e) {}

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;

    $total = $db->query('SELECT COUNT(*) FROM chat_sessions')->fetchColumn();
    $stmt = $db->prepare("SELECT cs.*, (SELECT COUNT(*) FROM chat_messages WHERE session_id = cs.id) as msg_count FROM chat_sessions cs ORDER BY cs.updated_at DESC LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute();
    $sessions = $stmt->fetchAll();

    jsonResponse(['success' => true, 'sessions' => $sessions, 'total' => (int)$total]);
}

// ===== ADMIN: Session History =====
if ($action === 'history') {
    require_once '../includes/auth.php';
    startSecureSession(); requireLogin();

    $sessionId = (int)($_GET['session_id'] ?? 0);
    if (!$sessionId) { jsonResponse(['success' => false], 400); }

    $db = getDB();
    $session = $db->prepare('SELECT * FROM chat_sessions WHERE id = ?');
    $session->execute([$sessionId]);
    $sessionData = $session->fetch();

    $messages = $db->prepare('SELECT * FROM chat_messages WHERE session_id = ? ORDER BY created_at ASC');
    $messages->execute([$sessionId]);

    jsonResponse(['success' => true, 'session' => $sessionData, 'messages' => $messages->fetchAll()]);
}

jsonResponse(['error' => 'Invalid action.'], 400);
