<?php
require_once __DIR__ . '/core.php';
$user   = requireAuth();
$uid    = (int)$user['id'];
$action = $_GET['action'] ?? 'conversations';

switch ($action) {

    case 'conversations': {
        // Subquery first gets distinct other_id — avoids alias-in-subquery MySQL limitation
        $stmt = $pdo->prepare("
            SELECT
                c.other_id,
                u.first_name, u.last_name, u.role,
                (SELECT message FROM messages
                 WHERE (sender_id=? AND receiver_id=c.other_id)
                    OR (sender_id=c.other_id AND receiver_id=?)
                 ORDER BY created_at DESC LIMIT 1) AS last_message,
                (SELECT created_at FROM messages
                 WHERE (sender_id=? AND receiver_id=c.other_id)
                    OR (sender_id=c.other_id AND receiver_id=?)
                 ORDER BY created_at DESC LIMIT 1) AS last_time,
                (SELECT COUNT(*) FROM messages
                 WHERE sender_id=c.other_id AND receiver_id=? AND is_read=0) AS unread
            FROM (
                SELECT DISTINCT IF(m.sender_id=?, m.receiver_id, m.sender_id) AS other_id
                FROM messages m
                WHERE m.sender_id=? OR m.receiver_id=?
            ) AS c
            JOIN users u ON u.id = c.other_id
            ORDER BY last_time DESC
        ");
        $stmt->execute([$uid,$uid,$uid,$uid,$uid,$uid,$uid,$uid]);
        ok($stmt->fetchAll());
        break;
    }

    case 'thread': {
        $otherId = (int)($_GET['user_id'] ?? 0);
        if (!$otherId) err('user_id required');

        $stmt = $pdo->prepare("
            SELECT m.*, u.first_name, u.last_name
            FROM messages m JOIN users u ON m.sender_id=u.id
            WHERE (m.sender_id=? AND m.receiver_id=?)
               OR (m.sender_id=? AND m.receiver_id=?)
            ORDER BY m.created_at ASC LIMIT 100
        ");
        $stmt->execute([$uid, $otherId, $otherId, $uid]);
        $msgs = $stmt->fetchAll();

        // Mark received messages as read
        $pdo->prepare("UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=? AND is_read=0")
            ->execute([$otherId, $uid]);

        ok($msgs);
        break;
    }

    case 'send': {
        $b    = body();
        $to   = (int)($b['receiver_id'] ?? 0);
        $text = trim($b['message'] ?? '');
        if (!$to || !$text) err('receiver_id and message required');

        $rx = $pdo->prepare("SELECT id FROM users WHERE id=? AND is_active=1");
        $rx->execute([$to]);
        if (!$rx->fetch()) err('Recipient not found', 404);

        $stmt = $pdo->prepare("INSERT INTO messages (sender_id,receiver_id,message,is_read,created_at) VALUES (?,?,?,0,NOW())");
        $stmt->execute([$uid, $to, $text]);
        ok(['id' => (int)$pdo->lastInsertId(), 'message' => 'Sent'], 201);
        break;
    }

    case 'contacts': {
        $role = $user['role'];
        if ($role === 'patient') {
            $stmt = $pdo->prepare("SELECT u.id,u.first_name,u.last_name,u.role,d.specialization,d.hospital_name FROM users u JOIN doctors d ON u.id=d.user_id WHERE u.role='doctor' AND u.is_active=1 ORDER BY u.first_name LIMIT 30");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("SELECT u.id,u.first_name,u.last_name,u.role FROM users u WHERE u.role='patient' AND u.is_active=1 ORDER BY u.first_name LIMIT 50");
            $stmt->execute();
        }
        ok($stmt->fetchAll());
        break;
    }

    default: err('Unknown action. Use: conversations | thread | send | contacts');
}
