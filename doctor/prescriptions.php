<?php
require_once __DIR__ . '/../config/config.php';
requireRole('doctor');
$user = getCurrentUser(); $uid = $user['id'];
$meds = $pdo->prepare("SELECT p.*, u.first_name, u.last_name FROM prescriptions p JOIN users u ON p.patient_id=u.id WHERE p.doctor_id=? ORDER BY p.is_active DESC, p.created_at DESC");
$meds->execute([$uid]); $meds = $meds->fetchAll();
$notifCount = getUnreadCount($pdo, $uid); $msgCount = getUnreadMessages($pdo, $uid);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Prescriptions — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css"></head>
<body><?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar"><div><div class="page-title"><i class="fas fa-pills" style="color:var(--hs-blue);"></i> Prescriptions</div></div></div>
  <div class="hs-content">
    <div class="hs-card">
      <div class="hs-card-header"><span class="card-title"><i class="fas fa-pills"></i> All Prescriptions (<?= count($meds) ?>)</span></div>
      <div class="hs-card-body p-0">
        <table class="hs-table">
          <thead><tr><th>Patient</th><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($meds as $m): ?>
            <tr>
              <td><strong><?= e($m['first_name'].' '.$m['last_name']) ?></strong></td>
              <td><?= e($m['medication_name']) ?></td>
              <td><?= e($m['dosage']) ?></td>
              <td><?= e($m['frequency']) ?></td>
              <td><?= $m['start_date'] ? formatDate($m['start_date']) : '—' ?></td>
              <td><?= $m['end_date'] ? formatDate($m['end_date']) : '—' ?></td>
              <td><?= $m['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Ended</span>' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="../assets/js/main.js"></script></body></html>
