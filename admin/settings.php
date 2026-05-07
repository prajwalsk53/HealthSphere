<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$user = getCurrentUser(); $uid = $user['id'];
$notifCount = getUnreadCount($pdo, $uid);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Settings — HealthSphere Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css"></head>
<body><?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar"><div><div class="page-title"><i class="fas fa-cog" style="color:var(--hs-blue);"></i> System Settings</div></div></div>
  <div class="hs-content">
    <div style="max-width:700px;">
      <div class="hs-card" style="margin-bottom:16px;">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-cog"></i> General Settings</span></div>
        <div class="hs-card-body">
          <div style="margin-bottom:16px;"><label class="form-label">Application Name</label><input type="text" class="form-control" value="HealthSphere"></div>
          <div style="margin-bottom:16px;"><label class="form-label">System Email</label><input type="email" class="form-control" value="admin@healthsphere.nhs.uk"></div>
          <div style="margin-bottom:16px;"><label class="form-label">NHS Login Integration</label><select class="form-select"><option>Enabled</option><option>Disabled</option></select></div>
          <button class="btn-hs btn-primary-hs"><i class="fas fa-save"></i> Save Settings</button>
        </div>
      </div>
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-database"></i> Database Status</span></div>
        <div class="hs-card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;">
            <?php
            $tables = ['users','appointments','medical_records','allergies','vaccinations','prescriptions','diet_logs','health_metrics','messages','food_database','genetic_diseases'];
            foreach ($tables as $t):
              $cnt = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
            ?>
            <div style="background:var(--hs-off-white);padding:10px 14px;border-radius:8px;display:flex;justify-content:space-between;">
              <span style="font-family:monospace;"><?= $t ?></span>
              <span style="font-weight:700;color:var(--hs-blue);"><?= number_format($cnt) ?> rows</span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="../assets/js/main.js"></script></body></html>
