<?php
require_once __DIR__ . '/../config/config.php';
requireRole('doctor');
$user = getCurrentUser(); $uid = $user['id'];

$week = $_GET['week'] ?? 0;
$startDate = date('Y-m-d', strtotime("monday this week +{$week} week"));
$endDate   = date('Y-m-d', strtotime("sunday this week +{$week} week"));

$appts = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name, u.nhs_id
    FROM appointments a JOIN users u ON a.patient_id=u.id
    WHERE a.doctor_id=? AND a.appointment_date BETWEEN ? AND ?
    ORDER BY a.appointment_date, a.appointment_time
");
$appts->execute([$uid, $startDate, $endDate]); $schedule = $appts->fetchAll();

// Group by date
$byDate = [];
foreach ($schedule as $s) $byDate[$s['appointment_date']][] = $s;

$days = [];
for ($i=0;$i<5;$i++) $days[] = date('Y-m-d', strtotime($startDate." +$i day"));

$notifCount = getUnreadCount($pdo, $uid); $msgCount = getUnreadMessages($pdo, $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Schedule — HealthSphere Doctor</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div><div class="page-title"><i class="fas fa-calendar-alt" style="color:var(--hs-blue);"></i> My Schedule</div><div class="page-subtitle"><?= formatDate($startDate, 'd M') ?> – <?= formatDate($endDate, 'd M Y') ?></div></div>
    <div class="topbar-actions">
      <a href="?week=<?= $week-1 ?>" class="btn-hs btn-outline-hs btn-sm-hs"><i class="fas fa-chevron-left"></i></a>
      <span style="font-size:13px;font-weight:600;padding:0 8px;">Week <?= date('W') + $week ?></span>
      <a href="?week=<?= $week+1 ?>" class="btn-hs btn-outline-hs btn-sm-hs"><i class="fas fa-chevron-right"></i></a>
    </div>
  </div>
  <div class="hs-content">
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;">
      <?php foreach ($days as $day): ?>
      <div class="hs-card">
        <div class="hs-card-header" style="background:<?= $day===date('Y-m-d')?'var(--hs-blue)':'var(--hs-off-white)' ?>;border-radius:11px 11px 0 0;">
          <span class="card-title" style="color:<?= $day===date('Y-m-d')?'#fff':'var(--hs-navy)' ?>;">
            <?= date('D', strtotime($day)) ?><br>
            <span style="font-size:20px;font-weight:900;"><?= date('d', strtotime($day)) ?></span>
          </span>
          <?php if (!empty($byDate[$day])): ?>
          <span class="badge bg-<?= $day===date('Y-m-d')?'warning text-dark':'primary' ?>"><?= count($byDate[$day]) ?></span>
          <?php endif; ?>
        </div>
        <div class="hs-card-body p-0">
          <?php if (!empty($byDate[$day])): ?>
            <?php foreach ($byDate[$day] as $a): ?>
            <div style="padding:10px 12px;border-bottom:1px solid var(--hs-border);">
              <div style="font-size:12px;font-weight:700;color:var(--hs-blue);"><?= date('H:i',strtotime($a['appointment_time'])) ?></div>
              <div style="font-size:13px;font-weight:600;color:var(--hs-navy);"><?= e($a['first_name'].' '.$a['last_name']) ?></div>
              <div style="font-size:11px;color:var(--hs-muted);"><?= e($a['reason'] ?: 'General') ?></div>
              <div style="margin-top:6px;"><?= getStatusBadge($a['status']) ?></div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
          <div style="padding:20px;text-align:center;color:var(--hs-muted);font-size:12px;">
            <i class="fas fa-calendar" style="opacity:.3;font-size:20px;"></i><br>No appointments
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
