<?php
require_once __DIR__ . '/../config/config.php';
requireRole('doctor');
$user = getCurrentUser(); $uid = $user['id'];
$records = $pdo->prepare("SELECT r.*, u.first_name, u.last_name, u.nhs_id FROM medical_records r JOIN users u ON r.patient_id=u.id WHERE r.doctor_id=? ORDER BY r.test_date DESC");
$records->execute([$uid]); $records = $records->fetchAll();
$notifCount = getUnreadCount($pdo, $uid); $msgCount = getUnreadMessages($pdo, $uid);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Lab Results — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css"></head>
<body><?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar"><div><div class="page-title"><i class="fas fa-flask" style="color:var(--hs-blue);"></i> Lab Results</div></div></div>
  <div class="hs-content">
    <div class="hs-card">
      <div class="hs-card-header"><span class="card-title"><i class="fas fa-flask"></i> Lab Results (<?= count($records) ?>)</span></div>
      <div class="hs-card-body p-0">
        <table class="hs-table">
          <thead><tr><th>Patient</th><th>NHS ID</th><th>Test</th><th>Result Summary</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($records as $r): ?>
            <tr>
              <td><strong><?= e($r['first_name'].' '.$r['last_name']) ?></strong></td>
              <td style="font-family:monospace;font-size:12px;"><?= e($r['nhs_id']) ?></td>
              <td><?= e($r['title']) ?></td>
              <td style="font-size:12px;max-width:200px;"><?= e(substr($r['result'],0,80)) ?>...</td>
              <td><?= getStatusBadge($r['result_status']) ?></td>
              <td><?= formatDate($r['test_date']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="../assets/js/main.js"></script></body></html>
