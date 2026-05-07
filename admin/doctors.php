<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$user = getCurrentUser(); $uid = $user['id'];

$success = '';
if (isset($_GET['verify'])) { $pdo->prepare("UPDATE doctors SET is_verified=1 WHERE id=?")->execute([(int)$_GET['verify']]); $success='Doctor verified.'; }
if (isset($_GET['revoke'])) { $pdo->prepare("UPDATE doctors SET is_verified=0 WHERE id=?")->execute([(int)$_GET['revoke']]); $success='Access revoked.'; }

$doctors = $pdo->query("
    SELECT u.*, d.id as doc_id, d.hcpc_number, d.specialization, d.hospital_name, d.rating, d.experience_years, d.is_verified
    FROM users u JOIN doctors d ON u.id=d.user_id
    WHERE u.role='doctor' ORDER BY d.is_verified ASC, u.created_at DESC
")->fetchAll();
$notifCount = getUnreadCount($pdo, $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctor Access — HealthSphere Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div><div class="page-title"><i class="fas fa-user-md" style="color:var(--hs-blue);"></i> Doctor Access Control</div></div>
  </div>
  <div class="hs-content">
    <?php if ($success): ?><div style="background:#DCFCE7;border:1px solid #BBF7D0;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#166534;font-size:13px;font-weight:600;"><i class="fas fa-check-circle"></i> <?= $success ?></div><?php endif; ?>
    <div class="hs-card">
      <div class="hs-card-header"><span class="card-title"><i class="fas fa-user-md"></i> Doctors Access</span><span style="font-size:12px;color:var(--hs-muted);"><?= count($doctors) ?> total</span></div>
      <div class="hs-card-body p-0">
        <table class="hs-table">
          <thead><tr><th><input type="checkbox"></th><th>User Name</th><th>Email Address</th><th>Hospital Name</th><th>IP Address</th><th>HCPC No.</th><th>Access</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($doctors as $d): ?>
            <tr>
              <td><input type="checkbox"></td>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div style="width:36px;height:36px;border-radius:50%;background:var(--hs-blue-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:12px;"><?= strtoupper(substr($d['first_name'],0,1).substr($d['last_name'],0,1)) ?></div>
                  <div><div style="font-weight:700;"><?= e($d['first_name'].' '.$d['last_name']) ?></div><div style="font-size:11px;color:var(--hs-blue);"><?= e($d['specialization']) ?></div></div>
                </div>
              </td>
              <td style="font-size:12px;"><?= e($d['email']) ?></td>
              <td style="font-size:13px;"><?= e($d['hospital_name']) ?></td>
              <td style="font-family:monospace;font-size:12px;">83.146.***.***</td>
              <td style="font-family:monospace;font-size:12px;font-weight:600;"><?= e($d['hcpc_number']) ?></td>
              <td><?= $d['is_verified'] ? '<span class="status-pill approved">Approved</span>' : '<span class="status-pill suspended">Pending</span>' ?></td>
              <td>
                <div style="display:flex;gap:6px;">
                  <?php if (!$d['is_verified']): ?>
                  <a href="?verify=<?= $d['doc_id'] ?>" class="btn-hs btn-success-hs btn-sm-hs"><i class="fas fa-check"></i> Verify</a>
                  <?php else: ?>
                  <a href="?revoke=<?= $d['doc_id'] ?>" class="btn-hs btn-danger-hs btn-sm-hs" onclick="return confirm('Revoke access?')"><i class="fas fa-ban"></i> Revoke</a>
                  <?php endif; ?>
                </div>
              </td>
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
