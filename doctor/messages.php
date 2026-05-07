<?php
require_once __DIR__ . '/../config/config.php';
requireRole('doctor');
$user = getCurrentUser(); $uid = $user['id'];

$convs = $pdo->prepare("
    SELECT DISTINCT u.id, u.first_name, u.last_name,
        (SELECT COUNT(*) FROM messages WHERE sender_id=u.id AND receiver_id=? AND is_read=0) as unread,
        (SELECT m2.message FROM messages m2 WHERE (m2.sender_id=u.id AND m2.receiver_id=?) OR (m2.sender_id=? AND m2.receiver_id=u.id) ORDER BY m2.created_at DESC LIMIT 1) as last_msg,
        (SELECT m2.created_at FROM messages m2 WHERE (m2.sender_id=u.id AND m2.receiver_id=?) OR (m2.sender_id=? AND m2.receiver_id=u.id) ORDER BY m2.created_at DESC LIMIT 1) as last_time,
        (SELECT m2.is_emergency FROM messages m2 WHERE (m2.sender_id=u.id AND m2.receiver_id=?) ORDER BY m2.created_at DESC LIMIT 1) as has_emergency
    FROM messages m JOIN users u ON (m.sender_id=u.id OR m.receiver_id=u.id)
    WHERE u.id!=? AND (m.sender_id=? OR m.receiver_id=?) AND u.role='patient'
");
$convs->execute([$uid,$uid,$uid,$uid,$uid,$uid,$uid,$uid,$uid]);
$conversations = $convs->fetchAll();

$chatWith = (int)($_GET['with'] ?? ($conversations[0]['id'] ?? 0));
$chatUser = null;
if ($chatWith) { $cu = $pdo->prepare("SELECT * FROM users WHERE id=?"); $cu->execute([$chatWith]); $chatUser = $cu->fetch(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $chatWith) {
    $msg = trim($_POST['message'] ?? '');
    if ($msg) { $pdo->prepare("INSERT INTO messages (sender_id,receiver_id,message) VALUES (?,?,?)")->execute([$uid,$chatWith,$msg]); }
    header("Location: messages.php?with=$chatWith"); exit;
}

$msgs = [];
if ($chatWith) {
    $pdo->prepare("UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=?")->execute([$chatWith,$uid]);
    $ms = $pdo->prepare("SELECT m.*, u.first_name, u.last_name FROM messages m JOIN users u ON m.sender_id=u.id WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?) ORDER BY m.created_at");
    $ms->execute([$uid,$chatWith,$chatWith,$uid]); $msgs = $ms->fetchAll();
}
$notifCount = getUnreadCount($pdo, $uid); $msgCount = getUnreadMessages($pdo, $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages — HealthSphere Doctor</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div><div class="page-title"><i class="fas fa-comment-medical" style="color:var(--hs-blue);"></i> Patient Messages</div></div>
  </div>
  <div class="hs-content" style="padding:0;">
    <div style="display:grid;grid-template-columns:300px 1fr;height:calc(100vh - 64px);">
      <div style="border-right:1px solid var(--hs-border);overflow-y:auto;background:#fff;">
        <div style="padding:16px;border-bottom:1px solid var(--hs-border);"><input type="text" placeholder="Search..." class="form-control" style="font-size:13px;"></div>
        <?php foreach ($conversations as $conv): ?>
        <a href="?with=<?= $conv['id'] ?>" style="text-decoration:none;display:flex;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid var(--hs-border);transition:var(--transition);background:<?= $chatWith===$conv['id']?'#EFF6FF':'#fff' ?>;" onmouseover="this.style.background='#F8FAFF'" onmouseout="this.style.background='<?= $chatWith===$conv['id']?'#EFF6FF':'#fff' ?>'">
          <div style="width:42px;height:42px;border-radius:50%;background:var(--hs-blue-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;flex-shrink:0;"><?= strtoupper(substr($conv['first_name'],0,1).substr($conv['last_name'],0,1)) ?></div>
          <div style="flex:1;min-width:0;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-weight:700;font-size:13px;color:var(--hs-navy);"><?= e($conv['first_name'].' '.$conv['last_name']) ?></span>
              <?php if ($conv['last_time']): ?><span style="font-size:11px;color:var(--hs-muted);"><?= timeAgo($conv['last_time']) ?></span><?php endif; ?>
            </div>
            <?php if ($conv['has_emergency']): ?><div style="font-size:11px;color:var(--hs-danger);font-weight:700;"><i class="fas fa-exclamation-triangle"></i> Emergency</div><?php endif; ?>
            <?php if ($conv['last_msg']): ?><div style="font-size:12px;color:var(--hs-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e(substr($conv['last_msg'],0,45)) ?>...</div><?php endif; ?>
          </div>
          <?php if ($conv['unread'] > 0): ?><span style="width:20px;height:20px;border-radius:50%;background:var(--hs-danger);color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;"><?= $conv['unread'] ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>

      <div style="display:flex;flex-direction:column;background:#F4F8FF;">
        <?php if ($chatUser): ?>
        <div style="background:var(--hs-navy);color:#fff;padding:14px 20px;display:flex;align-items:center;gap:12px;flex-shrink:0;">
          <div style="width:40px;height:40px;border-radius:50%;background:var(--hs-blue);display:flex;align-items:center;justify-content:center;font-weight:700;"><?= strtoupper(substr($chatUser['first_name'],0,1).substr($chatUser['last_name'],0,1)) ?></div>
          <div style="flex:1;">
            <div style="font-weight:700;"><?= e($chatUser['first_name'].' '.$chatUser['last_name']) ?></div>
            <div style="font-size:12px;opacity:.7;">Patient · NHS: <?= e($chatUser['nhs_id']) ?></div>
          </div>
          <a href="patients.php?id=<?= $chatUser['id'] ?>" class="btn-hs btn-primary-hs btn-sm-hs"><i class="fas fa-folder-open"></i> Open Record</a>
        </div>
        <div id="chatMessages" style="flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:12px;">
          <?php foreach ($msgs as $m): $isSent = $m['sender_id']==$uid; ?>
          <div class="chat-msg <?= $isSent?'sent':'received' ?>">
            <?php if (!$isSent): ?><span class="msg-sender"><?= e($m['first_name'].' '.$m['last_name']) ?></span><?php endif; ?>
            <?php if ($m['is_emergency']): ?><span class="emergency-badge">🚨 EMERGENCY</span><br><?php endif; ?>
            <div class="bubble"><?= e($m['message']) ?></div>
            <span class="msg-time"><?= date('H:i',strtotime($m['created_at'])) ?></span>
          </div>
          <?php endforeach; ?>
          <?php if (!$msgs): ?><div style="text-align:center;color:var(--hs-muted);margin:auto;"><i class="fas fa-comment" style="font-size:40px;opacity:.2;"></i><p style="margin-top:12px;">Start conversation</p></div><?php endif; ?>
        </div>
        <div style="padding:14px 20px;background:#fff;border-top:1px solid var(--hs-border);flex-shrink:0;">
          <form method="POST" style="display:flex;gap:10px;align-items:center;">
            <input type="text" name="message" class="form-control" placeholder="Type your response..." style="flex:1;border-radius:24px;" required autocomplete="off">
            <button type="submit" class="chat-send-btn"><i class="fas fa-paper-plane"></i></button>
          </form>
          <p style="font-size:11px;color:var(--hs-muted);margin-top:6px;text-align:center;"><i class="fas fa-lock"></i> Encrypted · All messages are audit-logged</p>
        </div>
        <?php else: ?>
        <div style="flex:1;display:flex;align-items:center;justify-content:center;color:var(--hs-muted);"><div style="text-align:center;"><i class="fas fa-comment-medical" style="font-size:60px;opacity:.2;"></i><p style="margin-top:16px;">Select a conversation</p></div></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script src="../assets/js/main.js"></script>
<script>const msgs = document.getElementById('chatMessages'); if(msgs) msgs.scrollTop=msgs.scrollHeight;</script>
</body>
</html>
