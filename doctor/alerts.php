<?php
require_once __DIR__ . '/../config/config.php';
requireRole('doctor');
$user = getCurrentUser(); $uid = $user['id'];
$notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC"); $notifs->execute([$uid]); $notifs = $notifs->fetchAll();
$notifCount = getUnreadCount($pdo, $uid); $msgCount = getUnreadMessages($pdo, $uid);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Alerts — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css"></head>
<body><?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar"><div><div class="page-title"><i class="fas fa-bell" style="color:var(--hs-blue);"></i> Alerts & Tasks</div></div></div>
  <div class="hs-content">
    <div class="hs-card" style="max-width:700px;margin:0 auto;">
      <div class="hs-card-header"><span class="card-title"><i class="fas fa-bell"></i> All Alerts</span></div>
      <?php foreach ($notifs as $n): ?>
      <div class="notif-item <?= $n['is_read']?'':'unread' ?>">
        <div class="notif-icon bg-danger" style="color:#fff;"><i class="fas fa-exclamation-triangle"></i></div>
        <div style="flex:1;"><div style="font-size:13.5px;font-weight:700;color:var(--hs-navy);"><?= e($n['title']) ?></div><div style="font-size:12px;color:var(--hs-muted);"><?= e($n['message']) ?></div></div>
        <div style="font-size:11px;color:var(--hs-muted);"><?= timeAgo($n['created_at']) ?></div>
      </div>
      <?php endforeach; ?>
      <?php if (!$notifs): ?><div style="padding:40px;text-align:center;color:var(--hs-muted);">No alerts.</div><?php endif; ?>
    </div>
  </div>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
