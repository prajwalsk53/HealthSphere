<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';
requireLogin();

header('Content-Type: application/json');

$uid    = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? 'poll';
$with   = (int)($_GET['with'] ?? 0);

switch ($action) {

    // ── Poll for new messages ─────────────────────────────────────
    case 'poll':
        $since = $_GET['since'] ?? '1970-01-01 00:00:00';
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
            $since = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        }
        $stmt = $pdo->prepare("
            SELECT m.id, m.sender_id, m.message, m.message_type, m.is_emergency,
                   m.created_at, u.first_name, u.last_name
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?))
              AND m.created_at > ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$uid, $with, $with, $uid, $since]);
        $messages = $stmt->fetchAll();

        // Mark received as read
        $pdo->prepare("UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=? AND is_read=0")
            ->execute([$with, $uid]);

        // Check if other person is typing
        $typing = false;
        try {
            $ts = $pdo->prepare("SELECT last_typed FROM chat_typing WHERE user_id=? AND conversation_with=?");
            $ts->execute([$with, $uid]);
            $lastTyped = $ts->fetchColumn();
            $typing = $lastTyped && (time() - strtotime($lastTyped)) < 4;
        } catch (\Exception $e) {}

        echo json_encode([
            'messages' => $messages,
            'typing'   => $typing,
            'ts'       => date('Y-m-d H:i:s'),
        ]);
        break;

    // ── Send a message ────────────────────────────────────────────
    case 'send':
        $data      = json_decode(file_get_contents('php://input'), true) ?? [];
        $message   = trim($data['message'] ?? '');
        $emergency = !empty($data['emergency']) ? 1 : 0;

        if (!$message || !$with) {
            echo json_encode(['ok' => false, 'error' => 'Empty message']);
            break;
        }

        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, message, is_emergency)
            VALUES (?,?,?,?)
        ");
        $stmt->execute([$uid, $with, $message, $emergency]);
        $newId = $pdo->lastInsertId();

        // Remove typing indicator
        try { $pdo->prepare("DELETE FROM chat_typing WHERE user_id=? AND conversation_with=?")->execute([$uid,$with]); } catch(\Exception $e) {}

        // Emergency email alert to doctor
        if ($emergency) {
            $sender   = $pdo->prepare("SELECT first_name,last_name,nhs_id FROM users WHERE id=?"); $sender->execute([$uid]); $sender=$sender->fetch();
            $receiver = $pdo->prepare("SELECT first_name,last_name,email,role FROM users WHERE id=?"); $receiver->execute([$with]); $receiver=$receiver->fetch();
            if ($sender && $receiver && $receiver['role']==='doctor') {
                @mailEmergencyAlert(
                    $receiver['email'],
                    $receiver['first_name'].' '.$receiver['last_name'],
                    $sender['first_name'].' '.$sender['last_name'],
                    $sender['nhs_id']??'',
                    $message
                );
            }
        }

        echo json_encode([
            'ok' => true,
            'id' => $newId,
            'ts' => date('Y-m-d H:i:s'),
        ]);
        break;

    // ── Typing indicator ──────────────────────────────────────────
    case 'typing':
        try {
            $pdo->prepare("
                INSERT INTO chat_typing (user_id, conversation_with, last_typed)
                VALUES (?,?, NOW())
                ON DUPLICATE KEY UPDATE last_typed=NOW()
            ")->execute([$uid, $with]);
        } catch (\Exception $e) {}
        echo json_encode(['ok' => true]);
        break;

    // ── Mark all read ─────────────────────────────────────────────
    case 'read':
        $pdo->prepare("UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=?")
            ->execute([$with, $uid]);
        echo json_encode(['ok' => true]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
