<?php
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
$user = getCurrentUser();
$uid  = $user['id'];

if (isset($_GET['mark_all'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
    header("Location: notifications.php"); exit;
}
if (isset($_GET['read'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([(int)$_GET['read'], $uid]);
}

$notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC");
$notifs->execute([$uid]); $notifs = $notifs->fetchAll();

$notifCount = getUnreadCount($pdo, $uid);
$msgCount   = getUnreadMessages($pdo, $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div><div class="page-title"><i class="fas fa-bell" style="color:var(--hs-blue);"></i> Notifications</div></div>
    <div class="topbar-actions">
      <?php if ($notifCount > 0): ?>
      <a href="?mark_all=1" class="btn-hs btn-outline-hs btn-sm-hs">Mark all read</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="hs-content">
    <div class="hs-card" style="max-width:700px;margin:0 auto;">
      <div class="hs-card-header">
        <span class="card-title"><i class="fas fa-bell"></i> All Notifications</span>
        <?php if ($notifCount > 0): ?><span class="badge bg-danger"><?= $notifCount ?> unread</span><?php endif; ?>
      </div>
      <?php
      $icons = ['appointment'=>['fa-calendar-check','#1565C0'],'medication'=>['fa-pills','#D97706'],'lab_result'=>['fa-flask','#0891B2'],'system'=>['fa-info-circle','#5E7A99'],'alert'=>['fa-exclamation-triangle','#DC2626'],'message'=>['fa-comment','#16A34A']];
      $lastDate = ''; $today = date('Y-m-d'); $yesterday = date('Y-m-d', strtotime('-1 day'));
      foreach ($notifs as $n):
        $nDate = substr($n['created_at'],0,10);
        $dateLabel = $nDate === $today ? 'Today' : ($nDate === $yesterday ? 'Yesterday' : formatDate($nDate));
        if ($dateLabel !== $lastDate):
          $lastDate = $dateLabel;
      ?>
      <div style="padding:8px 20px;background:var(--hs-off-white);font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--hs-muted);border-bottom:1px solid var(--hs-border);">
        <?= $dateLabel ?>
      </div>
      <?php endif;
        [$ico, $col] = $icons[$n['notification_type']] ?? ['fa-bell','#5E7A99'];
      ?>
      <a href="?read=<?= $n['id'] ?>" style="text-decoration:none;">
        <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
          <div class="notif-icon" style="background:<?= $col ?>22;color:<?= $col ?>;"><i class="fas <?= $ico ?>"></i></div>
          <div style="flex:1;">
            <div style="font-size:13.5px;font-weight:<?= $n['is_read']?'500':'700' ?>;color:var(--hs-navy);"><?= e($n['title']) ?></div>
            <div style="font-size:13px;color:var(--hs-muted);margin-top:2px;"><?= e($n['message']) ?></div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
            <span style="font-size:11px;color:var(--hs-muted);"><?= timeAgo($n['created_at']) ?></span>
            <?php if (!$n['is_read']): ?><span style="width:8px;height:8px;border-radius:50%;background:var(--hs-blue);"></span><?php endif; ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
      <?php if (!$notifs): ?>
      <div style="padding:40px;text-align:center;color:var(--hs-muted);">
        <i class="fas fa-bell-slash" style="font-size:40px;opacity:.3;"></i>
        <p style="margin-top:12px;">No notifications yet.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
