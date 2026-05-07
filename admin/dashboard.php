<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$user = getCurrentUser(); $uid = $user['id'];

// Stats
$totalUsers    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='patient'")->fetchColumn();
$totalDoctors  = $pdo->query("SELECT COUNT(*) FROM users WHERE role='doctor'")->fetchColumn();
$totalAppts    = $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date >= DATE_SUB(CURDATE(),INTERVAL 30 DAY)")->fetchColumn();
$pendingDocs   = $pdo->query("SELECT COUNT(*) FROM doctors WHERE is_verified=0")->fetchColumn();
$totalFoods    = $pdo->query("SELECT COUNT(*) FROM food_database")->fetchColumn();
$totalDiseases = $pdo->query("SELECT COUNT(*) FROM genetic_diseases")->fetchColumn();

// Recent users
$recentUsers = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Recent access logs
$accessLogs = $pdo->query("
    SELECT al.*, u.first_name, u.last_name, u.role, u.email
    FROM access_logs al JOIN users u ON al.user_id=u.id
    ORDER BY al.created_at DESC LIMIT 8
")->fetchAll();

$notifCount = getUnreadCount($pdo, $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-shield-alt" style="color:var(--hs-blue);"></i> Admin Console</div>
      <div class="page-subtitle">HealthSphere Control Panel · <?= date('d M Y') ?></div>
    </div>
    <div class="topbar-actions">
      <?php if ($pendingDocs > 0): ?>
      <a href="doctors.php" class="btn-hs btn-danger-hs btn-sm-hs">
        <i class="fas fa-exclamation-triangle"></i> <?= $pendingDocs ?> Pending Doctors
      </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="hs-content">
    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px;">
      <div class="stat-card stat-blue"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-info"><div class="stat-label">Patients</div><div class="stat-value" data-count="<?= $totalUsers ?>"><?= $totalUsers ?></div><div class="stat-sub">Registered users</div></div></div>
      <div class="stat-card stat-green"><div class="stat-icon"><i class="fas fa-user-md"></i></div><div class="stat-info"><div class="stat-label">Doctors</div><div class="stat-value" data-count="<?= $totalDoctors ?>"><?= $totalDoctors ?></div><div class="stat-sub"><?= $pendingDocs ?> pending verification</div></div></div>
      <div class="stat-card stat-teal"><div class="stat-icon"><i class="fas fa-calendar-check"></i></div><div class="stat-info"><div class="stat-label">Appointments (30d)</div><div class="stat-value" data-count="<?= $totalAppts ?>"><?= $totalAppts ?></div></div></div>
      <div class="stat-card stat-warning"><div class="stat-icon"><i class="fas fa-drumstick-bite"></i></div><div class="stat-info"><div class="stat-label">Food Items</div><div class="stat-value" data-count="<?= $totalFoods ?>"><?= $totalFoods ?></div></div></div>
      <div class="stat-card stat-purple"><div class="stat-icon"><i class="fas fa-dna"></i></div><div class="stat-info"><div class="stat-label">Genetic Diseases</div><div class="stat-value" data-count="<?= $totalDiseases ?>"><?= $totalDiseases ?></div></div></div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
      <!-- Recent users -->
      <div class="hs-card">
        <div class="hs-card-header">
          <span class="card-title"><i class="fas fa-user-plus"></i> Recently Registered</span>
          <a href="users.php" style="font-size:12px;color:var(--hs-blue);">View all →</a>
        </div>
        <div class="hs-card-body p-0">
          <table class="hs-table">
            <thead><tr><th>User</th><th>Role</th><th>NHS ID</th><th>Status</th><th>Joined</th></tr></thead>
            <tbody>
              <?php foreach ($recentUsers as $u): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:34px;height:34px;border-radius:50%;background:var(--hs-blue-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:12px;"><?= strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) ?></div>
                    <div><div style="font-weight:600;"><?= e($u['first_name'].' '.$u['last_name']) ?></div><div style="font-size:11px;color:var(--hs-muted);"><?= e($u['email']) ?></div></div>
                  </div>
                </td>
                <td><span style="text-transform:capitalize;font-weight:600;"><?= $u['role'] ?></span></td>
                <td style="font-family:monospace;font-size:12px;"><?= e($u['nhs_id'] ?? '—') ?></td>
                <td><?= $u['is_active'] ? '<span class="status-pill approved">Approved</span>' : '<span class="status-pill suspended">Suspended</span>' ?></td>
                <td style="font-size:12px;"><?= timeAgo($u['created_at']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Quick links -->
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-bolt"></i> Quick Actions</span></div>
        <div class="hs-card-body">
          <?php
          $links = [
            ['fa-users','User Management','users.php','#1565C0','#DBEAFE'],
            ['fa-user-md','Doctor Access','doctors.php','#16A34A','#DCFCE7'],
            ['fa-drumstick-bite','Food Database','food-data.php','#D97706','#FEF3C7'],
            ['fa-dna','Genetic Diseases','diseases.php','#7C3AED','#EDE9FE'],
            ['fa-shield-alt','Access Logs','access-logs.php','#0891B2','#CFFAFE'],
            ['fa-cog','Settings','settings.php','#5E7A99','#F1F5F9'],
          ];
          foreach ($links as [$ico,$label,$href,$color,$bg]):
          ?>
          <a href="<?= $href ?>" style="display:flex;align-items:center;gap:12px;padding:12px;border:1px solid var(--hs-border);border-radius:8px;text-decoration:none;margin-bottom:8px;transition:var(--transition);" onmouseover="this.style.borderColor='<?= $color ?>';this.style.background='<?= $bg ?>'" onmouseout="this.style.borderColor='var(--hs-border)';this.style.background='#fff'">
            <div style="width:36px;height:36px;border-radius:8px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;color:<?= $color ?>;flex-shrink:0;"><i class="fas <?= $ico ?>"></i></div>
            <span style="font-weight:600;color:var(--hs-navy);font-size:13.5px;"><?= $label ?></span>
            <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--hs-muted);font-size:12px;"></i>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Access logs -->
    <div class="hs-card" style="margin-top:20px;">
      <div class="hs-card-header">
        <span class="card-title"><i class="fas fa-history"></i> Recent Access Logs</span>
        <a href="access-logs.php" style="font-size:12px;color:var(--hs-blue);">Full log →</a>
      </div>
      <div class="hs-card-body p-0">
        <table class="hs-table">
          <thead><tr><th>User</th><th>Role</th><th>Action</th><th>IP Address</th><th>Time</th></tr></thead>
          <tbody>
            <?php foreach ($accessLogs as $log): ?>
            <tr>
              <td><strong><?= e($log['first_name'].' '.$log['last_name']) ?></strong></td>
              <td style="text-transform:capitalize;"><?= $log['role'] ?></td>
              <td><code style="background:var(--hs-off-white);padding:2px 8px;border-radius:4px;font-size:12px;"><?= e($log['action_type']) ?></code></td>
              <td style="font-family:monospace;font-size:12px;"><?= e($log['ip_address']) ?></td>
              <td style="font-size:12px;color:var(--hs-muted);"><?= timeAgo($log['created_at']) ?></td>
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
