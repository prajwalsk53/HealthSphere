<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$user = getCurrentUser(); $uid = $user['id'];

$logs = $pdo->query("SELECT al.*, u.first_name, u.last_name, u.role, u.email FROM access_logs al JOIN users u ON al.user_id=u.id ORDER BY al.created_at DESC LIMIT 100")->fetchAll();
$notifCount = getUnreadCount($pdo, $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Access Logs — HealthSphere Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div><div class="page-title"><i class="fas fa-shield-alt" style="color:var(--hs-blue);"></i> Access Logs</div><div class="page-subtitle">Security audit trail</div></div>
  </div>
  <div class="hs-content">
    <div class="hs-card">
      <div class="hs-card-header"><span class="card-title"><i class="fas fa-history"></i> System Access Logs</span><span style="font-size:12px;color:var(--hs-muted);"><?= count($logs) ?> records</span></div>
      <div class="hs-card-body p-0">
        <table class="hs-table">
          <thead><tr><th>User</th><th>Role</th><th>Email</th><th>Action</th><th>IP Address</th><th>Time</th></tr></thead>
          <tbody>
            <?php foreach ($logs as $log):
              $actionColor = str_contains($log['action_type'],'DELETE')||str_contains($log['action_type'],'SUSPEND') ? 'var(--hs-danger)' : (str_contains($log['action_type'],'LOGIN') ? 'var(--hs-success)' : 'var(--hs-blue)');
            ?>
            <tr>
              <td><strong><?= e($log['first_name'].' '.$log['last_name']) ?></strong></td>
              <td style="text-transform:capitalize;"><?= $log['role'] ?></td>
              <td style="font-size:12px;color:var(--hs-muted);"><?= e($log['email']) ?></td>
              <td><code style="background:var(--hs-off-white);padding:2px 8px;border-radius:4px;font-size:12px;color:<?= $actionColor ?>;"><?= e($log['action_type']) ?></code></td>
              <td style="font-family:monospace;font-size:12px;"><?= e($log['ip_address']) ?></td>
              <td style="font-size:12px;"><?= formatDate($log['created_at'], 'd M Y H:i') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
