<?php
require_once __DIR__ . '/core.php';
$user   = requireAuth();
$uid    = (int)$user['id'];
$action = $_GET['action'] ?? 'list';

switch ($action) {

    case 'list': {
        $limit = min((int)($_GET['limit'] ?? 20), 50);
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$uid, $limit]);
        ok($stmt->fetchAll());
        break;
    }

    case 'unread': {
        $count = (int)$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0")
            ->execute([$uid]) ? $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id=$uid AND is_read=0")->fetchColumn() : 0;
        ok(['count' => $count]);
        break;
    }

    case 'mark_read': {
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$id, $uid]);
        } else {
            $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
        }
        ok(['message' => 'Marked as read']);
        break;
    }

    default: err('Unknown action. Use: list | unread | mark_read');
}
