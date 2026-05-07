<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/health_score.php';
requireRole('doctor');
$user = getCurrentUser();
$uid  = $user['id'];
$today = date('Y-m-d');

// Today's appointments with rich patient data
$todayAppts = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name, u.nhs_id, u.blood_type, u.date_of_birth,
        (SELECT GROUP_CONCAT(al.allergen SEPARATOR ', ') FROM allergies al WHERE al.patient_id=u.id AND al.is_active=1 AND al.severity='severe') as severe_allergies,
        (SELECT hm.blood_pressure_systolic FROM health_metrics hm WHERE hm.patient_id=u.id ORDER BY hm.metric_date DESC LIMIT 1) as bp_sys,
        (SELECT hm.blood_pressure_diastolic FROM health_metrics hm WHERE hm.patient_id=u.id ORDER BY hm.metric_date DESC LIMIT 1) as bp_dia,
        (SELECT hm.heart_rate FROM health_metrics hm WHERE hm.patient_id=u.id ORDER BY hm.metric_date DESC LIMIT 1) as heart_rate,
        (SELECT hm.spo2 FROM health_metrics hm WHERE hm.patient_id=u.id ORDER BY hm.metric_date DESC LIMIT 1) as spo2,
        (SELECT hm.stress_level FROM health_metrics hm WHERE hm.patient_id=u.id ORDER BY hm.metric_date DESC LIMIT 1) as stress
    FROM appointments a JOIN users u ON a.patient_id=u.id
    WHERE a.doctor_id=? AND a.appointment_date=?
    ORDER BY a.appointment_time
");
$todayAppts->execute([$uid, $today]);
$appointments = $todayAppts->fetchAll();

// Build priority queue — score each patient
$priorityQueue = [];
foreach ($appointments as &$appt) {
    $riskFlags = [];
    $riskScore = 0;

    if ($appt['bp_sys'] && $appt['bp_sys'] >= 140)      { $riskFlags[] = 'High BP'; $riskScore += 30; }
    elseif ($appt['bp_sys'] && $appt['bp_sys'] >= 130)  { $riskFlags[] = 'Elevated BP'; $riskScore += 15; }
    if ($appt['heart_rate'] && $appt['heart_rate'] > 100){ $riskFlags[] = 'Tachycardia'; $riskScore += 25; }
    if ($appt['spo2'] && $appt['spo2'] < 93)             { $riskFlags[] = '⚠️ Low SpO₂'; $riskScore += 40; }
    if ($appt['severe_allergies'])                        { $riskFlags[] = 'Severe Allergy'; $riskScore += 20; }
    if ($appt['stress'] && $appt['stress'] > 70)         { $riskFlags[] = 'High Stress'; $riskScore += 10; }
    if ($appt['status'] === 'late')                       { $riskScore += 5; }

    $appt['risk_score'] = $riskScore;
    $appt['risk_flags'] = $riskFlags;
    $appt['risk_level'] = $riskScore >= 50 ? 'critical' : ($riskScore >= 25 ? 'warning' : 'low');

    if ($riskScore >= 25) {
        $priorityQueue[] = &$appt;
    }
}
unset($appt);

// Sort priority queue: highest risk first
usort($priorityQueue, fn($a, $b) => $b['risk_score'] - $a['risk_score']);

// KPI stats
$totalToday   = count($appointments);
$pendingLabs  = $pdo->prepare("SELECT COUNT(*) FROM medical_records WHERE doctor_id=? AND result_status='critical'"); $pendingLabs->execute([$uid]); $pendingLabs = (int)$pendingLabs->fetchColumn();
$unreadMsgs   = getUnreadMessages($pdo, $uid);
$notifCount   = getUnreadCount($pdo, $uid);
$criticalPts  = count(array_filter($appointments, fn($a) => $a['risk_level'] === 'critical'));

// Recent messages
$recentMsgs = $pdo->prepare("SELECT m.*, u.first_name, u.last_name FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.receiver_id=? AND m.is_read=0 AND m.is_emergency=1 ORDER BY m.created_at DESC LIMIT 3"); $recentMsgs->execute([$uid]); $emergencyMsgs = $recentMsgs->fetchAll();

// Critical lab alerts
$criticalLabs = $pdo->prepare("SELECT r.*, u.first_name, u.last_name FROM medical_records r JOIN users u ON r.patient_id=u.id WHERE r.doctor_id=? AND r.result_status='critical' ORDER BY r.created_at DESC LIMIT 3"); $criticalLabs->execute([$uid]); $criticalLabs = $criticalLabs->fetchAll();

// All patients (for priority queue)
$allPatients = $pdo->prepare("
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.nhs_id,
        MAX(a.appointment_date) as last_visit,
        (SELECT hm.blood_pressure_systolic FROM health_metrics hm WHERE hm.patient_id=u.id ORDER BY hm.metric_date DESC LIMIT 1) as bp_sys,
        (SELECT hm.heart_rate FROM health_metrics hm WHERE hm.patient_id=u.id ORDER BY hm.metric_date DESC LIMIT 1) as hr,
        (SELECT hm.spo2 FROM health_metrics hm WHERE hm.patient_id=u.id ORDER BY hm.metric_date DESC LIMIT 1) as spo2
    FROM appointments a JOIN users u ON a.patient_id=u.id
    WHERE a.doctor_id=? GROUP BY u.id ORDER BY last_visit DESC LIMIT 20
");
$allPatients->execute([$uid]);
$allPatients = $allPatients->fetchAll();

// Compute health score for each patient
foreach ($allPatients as &$pt) {
    $s = calculateHealthScore($pdo, (int)$pt['id']);
    $pt['health_score']    = $s['score'];
    $pt['score_category']  = $s['category'];
    $pt['score_color']     = $s['color'];
}
unset($pt);

// Sort by health score ascending (most at risk first)
usort($allPatients, fn($a,$b) => $a['health_score'] - $b['health_score']);

// Monthly appointment chart data
$monthlyData = [];
for ($m = 1; $m <= 12; $m++) {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND MONTH(appointment_date)=? AND YEAR(appointment_date)=YEAR(CURDATE())");
    $cnt->execute([$uid, $m]);
    $monthlyData[] = (int)$cnt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Doctor Dashboard — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.risk-badge { padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;letter-spacing:.3px; }
.risk-critical { background:#FEE2E2;color:#991B1B; }
.risk-warning  { background:#FEF3C7;color:#92400E; }
.risk-low      { background:#DCFCE7;color:#166534; }
.patient-row-risk { border-left:3px solid; }
.patient-row-risk.critical { border-color:#DC2626;background:#FEF2F2; }
.patient-row-risk.warning  { border-color:#D97706;background:#FFFBEB; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">

  <div class="hs-topbar">
    <button id="menuToggle" style="display:none;background:none;border:none;cursor:pointer;font-size:20px;"><i class="fas fa-bars"></i></button>
    <div>
      <div class="page-title">Doctor Dashboard &mdash; <?= date('D, d M Y') ?></div>
      <div class="page-subtitle">Dr. <?= e($user['first_name'].' '.$user['last_name']) ?> &nbsp;·&nbsp; <?= $totalToday ?> patients today</div>
    </div>
    <div class="topbar-actions">
      <div class="input-icon-wrap" style="width:250px;">
        <i class="fas fa-search"></i>
        <input type="text" id="globalSearch" placeholder="Search by name, NHS ID..." class="form-control" style="font-size:13px;">
      </div>
      <button class="btn-hs btn-outline-hs btn-sm-hs"><i class="fas fa-sticky-note"></i> Add Note</button>
      <button class="btn-hs btn-primary-hs btn-sm-hs"><i class="fas fa-download"></i> Export Day</button>
    </div>
  </div>

  <div class="hs-content">

    <!-- KPI Header Row -->
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:22px;">
      <div class="stat-card stat-blue">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-info"><div class="stat-label">Patients Today</div><div class="stat-value" data-count="<?= $totalToday ?>"><?= $totalToday ?></div><div class="stat-sub"><?= count(array_filter($appointments,fn($a)=>$a['status']==='arrived')) ?> arrived &middot; <?= count(array_filter($appointments,fn($a)=>$a['status']==='waiting')) ?> waiting</div></div>
      </div>
      <div class="stat-card stat-danger">
        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-info"><div class="stat-label">High Risk Patients</div><div class="stat-value" data-count="<?= $criticalPts ?>"><?= $criticalPts ?></div><div class="stat-sub">Require priority care</div></div>
      </div>
      <div class="stat-card stat-teal">
        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        <div class="stat-info"><div class="stat-label">Schedule</div><div class="stat-value" style="font-size:16px;">09:00–17:00</div><div class="stat-sub">3 slots available</div></div>
      </div>
      <div class="stat-card stat-warning">
        <div class="stat-icon"><i class="fas fa-flask"></i></div>
        <div class="stat-info"><div class="stat-label">Critical Labs</div><div class="stat-value" data-count="<?= $pendingLabs ?>"><?= $pendingLabs ?></div><div class="stat-sub">Require review</div></div>
      </div>
      <div class="stat-card stat-danger">
        <div class="stat-icon"><i class="fas fa-comment-medical"></i></div>
        <div class="stat-info"><div class="stat-label">Emergency Messages</div><div class="stat-value" data-count="<?= count($emergencyMsgs) ?>"><?= count($emergencyMsgs) ?></div><div class="stat-sub"><a href="messages.php" style="color:var(--hs-blue);font-size:12px;">Open inbox →</a></div></div>
      </div>
    </div>

    <!-- PRIORITY PATIENT QUEUE (if any at-risk patients) -->
    <?php if (!empty($priorityQueue)): ?>
    <div class="hs-card" style="margin-bottom:20px;border-left:4px solid #DC2626;">
      <div class="hs-card-header">
        <span class="card-title"><i class="fas fa-sort-amount-up" style="color:#DC2626;"></i> Priority Patient Queue</span>
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="background:#FEE2E2;color:#DC2626;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;"><?= count($priorityQueue) ?> at-risk patients</span>
          <span style="font-size:12px;color:var(--hs-muted);">Sorted by risk score</span>
        </div>
      </div>
      <div class="hs-card-body p-0">
        <table class="hs-table">
          <thead><tr><th>Patient</th><th>Risk Score</th><th>Risk Flags</th><th>Vitals</th><th>Time</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($priorityQueue as $pq): ?>
            <tr class="patient-row-risk <?= $pq['risk_level'] ?>">
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div style="width:36px;height:36px;border-radius:50%;background:<?= $pq['risk_level']==='critical'?'#DC2626':'#D97706' ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:12px;">
                    <?= strtoupper(substr($pq['first_name'],0,1).substr($pq['last_name'],0,1)) ?>
                  </div>
                  <div>
                    <div style="font-weight:700;"><?= e($pq['first_name'].' '.$pq['last_name']) ?></div>
                    <div style="font-size:11px;color:var(--hs-muted);font-family:monospace;"><?= e($pq['nhs_id']) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:6px;">
                  <div style="width:36px;height:36px;border-radius:50%;background:<?= $pq['risk_level']==='critical'?'#FEE2E2':'#FEF3C7' ?>;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:14px;color:<?= $pq['risk_level']==='critical'?'#DC2626':'#D97706' ?>;">
                    <?= $pq['risk_score'] ?>
                  </div>
                  <span class="risk-badge risk-<?= $pq['risk_level'] ?>"><?= ucfirst($pq['risk_level']) ?></span>
                </div>
              </td>
              <td>
                <?php foreach ($pq['risk_flags'] as $flag): ?>
                <span style="background:<?= str_contains($flag,'⚠️')?'#FEE2E2':'#FEF3C7' ?>;color:<?= str_contains($flag,'⚠️')?'#991B1B':'#92400E' ?>;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:700;margin-right:4px;display:inline-block;margin-bottom:2px;"><?= e($flag) ?></span>
                <?php endforeach; ?>
              </td>
              <td style="font-size:12px;">
                <div><strong>BP:</strong> <?= $pq['bp_sys'] ? $pq['bp_sys'].'/'.$pq['bp_dia'] : '—' ?></div>
                <div><strong>HR:</strong> <?= $pq['heart_rate'] ?? '—' ?> bpm</div>
                <div><strong>SpO₂:</strong> <?= $pq['spo2'] ?? '—' ?>%</div>
              </td>
              <td style="font-weight:600;"><?= date('H:i', strtotime($pq['appointment_time'])) ?></td>
              <td><?= getStatusBadge($pq['status']) ?></td>
              <td>
                <button class="btn-hs btn-danger-hs btn-sm-hs" onclick="openChart(<?= $pq['patient_id'] ?>,'<?= addslashes($pq['first_name'].' '.$pq['last_name']) ?>','<?= $pq['nhs_id'] ?>','<?= $pq['severe_allergies'] ?>','<?= $pq['bp_sys'].'/'.$pq['bp_dia'] ?>',<?= $pq['heart_rate'] ?>,<?= $pq['spo2'] ?>)">
                  <i class="fas fa-chart-area"></i> Open Chart
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- MAIN 3-COLUMN: Schedule + Patient List + Alerts -->
    <div style="display:grid;grid-template-columns:1fr 1.2fr 280px;gap:20px;margin-bottom:20px;">

      <!-- Today's Schedule -->
      <div class="hs-card">
        <div class="hs-card-header">
          <span class="card-title"><i class="fas fa-calendar-day"></i> Today's Schedule</span>
        </div>
        <div class="hs-card-body p-0">
          <?php if (!$appointments): ?>
          <div style="padding:30px;text-align:center;color:var(--hs-muted);"><i class="fas fa-calendar-times" style="font-size:32px;opacity:.3;"></i><p style="margin-top:12px;font-size:13px;">No appointments today.</p></div>
          <?php endif; ?>
          <?php foreach ($appointments as $a): ?>
          <div style="padding:12px 16px;border-bottom:1px solid var(--hs-border);display:flex;align-items:center;gap:10px;<?= $a['risk_level']==='critical'?'background:#FEF2F2;':($a['risk_level']==='warning'?'background:#FFFBEB;':'') ?>">
            <div style="min-width:44px;text-align:center;">
              <div style="font-size:12px;font-weight:700;color:var(--hs-navy);"><?= date('H:i',strtotime($a['appointment_time'])) ?></div>
            </div>
            <div style="flex:1;">
              <div style="font-weight:600;font-size:13px;color:var(--hs-navy);"><?= e($a['first_name'].' '.$a['last_name']) ?></div>
              <div style="font-size:11px;color:var(--hs-muted);"><?= e($a['reason'] ?: 'General') ?></div>
              <?php if (!empty($a['risk_flags'])): ?>
              <div style="margin-top:3px;">
                <?php foreach (array_slice($a['risk_flags'],0,2) as $f): ?>
                <span style="font-size:9px;background:#FEE2E2;color:#DC2626;padding:1px 5px;border-radius:3px;font-weight:700;margin-right:3px;"><?= e($f) ?></span>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
              <?= getStatusBadge($a['status']) ?>
              <button class="btn-hs btn-outline-hs btn-sm-hs" style="font-size:10px;padding:3px 8px;" onclick="openChart(<?= $a['patient_id'] ?>,'<?= addslashes($a['first_name'].' '.$a['last_name']) ?>','<?= $a['nhs_id'] ?>','<?= addslashes($a['severe_allergies'] ?? '') ?>','<?= $a['bp_sys'].'/'.$a['bp_dia'] ?>',<?= $a['heart_rate']??0 ?>,<?= $a['spo2']??97 ?>)">
                Open chart
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Patient health scores -->
      <div class="hs-card">
        <div class="hs-card-header">
          <span class="card-title"><i class="fas fa-list-ol"></i> All Patients &mdash; Health Score</span>
          <span style="font-size:12px;color:var(--hs-muted);">Lowest score = most at risk</span>
        </div>
        <div class="hs-card-body p-0">
          <?php foreach ($allPatients as $pt): ?>
          <a href="patients.php?id=<?= $pt['id'] ?>" style="text-decoration:none;display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid var(--hs-border);transition:var(--transition);background:#fff;" onmouseover="this.style.background='#F4F8FF'" onmouseout="this.style.background='#fff'">
            <div style="width:38px;height:38px;border-radius:50%;background:<?= $pt['score_color'] ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:13px;flex-shrink:0;">
              <?= $pt['health_score'] ?>
            </div>
            <div style="flex:1;">
              <div style="font-weight:700;font-size:13px;color:var(--hs-navy);"><?= e($pt['first_name'].' '.$pt['last_name']) ?></div>
              <div style="font-size:11px;color:var(--hs-muted);">NHS: <?= e($pt['nhs_id']) ?> &middot; Last: <?= $pt['last_visit'] ? formatDate($pt['last_visit'],'d M') : 'N/A' ?></div>
            </div>
            <div style="text-align:right;">
              <div style="width:80px;background:var(--hs-bg);border-radius:4px;height:6px;overflow:hidden;">
                <div style="width:<?= $pt['health_score'] ?>%;background:<?= $pt['score_color'] ?>;height:100%;border-radius:4px;transition:width 1s;"></div>
              </div>
              <div style="font-size:10px;color:<?= $pt['score_color'] ?>;font-weight:700;margin-top:2px;text-transform:capitalize;"><?= $pt['score_category'] ?></div>
            </div>
          </a>
          <?php endforeach; ?>
          <?php if (!$allPatients): ?>
          <div style="padding:30px;text-align:center;color:var(--hs-muted);">No patients yet.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Alerts & Emergency -->
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-bell"></i> Alerts</span></div>
        <div class="hs-card-body p-0">

          <?php if ($emergencyMsgs): ?>
          <div style="padding:10px 16px;background:#FEF2F2;border-bottom:1px solid var(--hs-border);">
            <div style="font-size:11px;font-weight:700;color:#DC2626;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">
              🚨 Emergency Messages
            </div>
            <?php foreach ($emergencyMsgs as $em): ?>
            <div style="margin-bottom:8px;">
              <div style="font-weight:700;font-size:12px;"><?= e($em['first_name'].' '.$em['last_name']) ?></div>
              <div style="font-size:11px;color:var(--hs-muted);">"<?= e(substr($em['message'],0,50)) ?>..."</div>
            </div>
            <?php endforeach; ?>
            <a href="messages.php" class="btn-hs btn-danger-hs btn-sm-hs" style="width:100%;justify-content:center;margin-top:4px;"><i class="fas fa-reply"></i> Respond Now</a>
          </div>
          <?php endif; ?>

          <?php foreach ($criticalLabs as $lab): ?>
          <div style="padding:12px 16px;border-bottom:1px solid var(--hs-border);display:flex;align-items:center;gap:10px;">
            <div style="flex:1;">
              <div style="font-size:12.5px;font-weight:600;color:var(--hs-navy);"><?= e($lab['title']) ?></div>
              <div style="font-size:11px;color:var(--hs-muted);"><?= e($lab['first_name'].' '.$lab['last_name']) ?></div>
            </div>
            <span class="badge bg-danger">Critical</span>
          </div>
          <?php endforeach; ?>

          <div style="padding:12px 16px;border-bottom:1px solid var(--hs-border);">
            <div style="font-size:12.5px;font-weight:600;color:var(--hs-navy);">Prescription refills due</div>
            <div style="font-size:11px;color:var(--hs-muted);">2 patients need renewal this week</div>
          </div>

          <a href="alerts.php" style="display:block;padding:12px 16px;text-align:center;font-size:13px;font-weight:600;color:var(--hs-blue);">View all tasks →</a>
        </div>
      </div>

    </div><!-- /main grid -->

    <!-- Monthly chart row -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-chart-bar"></i> Appointments (2025 — Monthly)</span></div>
        <div class="hs-card-body" style="height:220px;"><canvas id="admissionsChart"></canvas></div>
      </div>
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-chart-pie"></i> Patient Risk Distribution</span></div>
        <div class="hs-card-body" style="height:220px;"><canvas id="riskPieChart"></canvas></div>
      </div>
    </div>

  </div>
</div>

<!-- Patient Chart Modal -->
<div id="chartModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:2000;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:16px;width:100%;max-width:680px;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-lg);">
    <div style="background:var(--hs-navy);color:#fff;padding:18px 24px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;border-radius:16px 16px 0 0;">
      <div>
        <h5 style="margin:0;font-size:16px;font-weight:700;"><i class="fas fa-notes-medical"></i> Patient Chart</h5>
        <div id="modalSubtitle" style="font-size:12px;opacity:.7;"></div>
      </div>
      <button onclick="document.getElementById('chartModal').style.display='none'" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <div style="padding:24px;">
      <div style="display:flex;gap:4px;background:var(--hs-off-white);border-radius:8px;padding:4px;margin-bottom:20px;width:fit-content;">
        <?php foreach (['Vitals','Labs','Medications','Notes'] as $i => $tab): ?>
        <button onclick="switchTab('<?= strtolower($tab) ?>')" id="tab<?= $tab ?>" style="padding:8px 18px;border-radius:6px;border:none;font-size:13px;font-weight:600;cursor:pointer;background:<?= $i===0?'var(--hs-blue)':'transparent' ?>;color:<?= $i===0?'#fff':'var(--hs-muted)' ?>;"><?= $tab ?></button>
        <?php endforeach; ?>
      </div>

      <div id="panelVitals">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;">
          <div class="vital-item"><div class="vital-icon">❤️</div><div class="vital-value" id="modalBP">—</div><div class="vital-unit">mmHg</div><div class="vital-label">Blood Pressure</div></div>
          <div class="vital-item"><div class="vital-icon">💓</div><div class="vital-value" id="modalHR">—</div><div class="vital-unit">bpm</div><div class="vital-label">Heart Rate</div></div>
          <div class="vital-item"><div class="vital-icon">🫁</div><div class="vital-value" id="modalSpo2">—</div><div class="vital-unit">%</div><div class="vital-label">SpO₂</div></div>
        </div>
        <div id="modalAllergyAlert" style="display:none;background:#FEE2E2;border:1px solid #FECACA;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;color:#991B1B;">
          <strong><i class="fas fa-exclamation-triangle"></i> Severe Allergy:</strong> <span id="modalAllergy"></span>
        </div>
        <div style="height:140px;"><canvas id="modalHrChart"></canvas></div>
        <p style="font-size:11px;color:var(--hs-muted);text-align:center;margin-top:8px;">7-day heart rate trend</p>
      </div>

      <div id="panelLabs" style="display:none;">
        <div style="display:flex;flex-direction:column;gap:10px;">
          <?php foreach ([['HbA1c','6.4%','elevated'],['LDL Cholesterol','128 mg/dL','elevated'],['Vitamin D','18 ng/mL','low'],['Fasting Glucose','132 mg/dL','elevated'],['TSH','5.2 mIU/L','elevated']] as [$name,$val,$status]):
            $col=['elevated'=>'#D97706','low'=>'#0891B2','normal'=>'#16A34A'][$status]??'#5E7A99'; ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--hs-off-white);border-radius:8px;border-left:3px solid <?= $col ?>;">
            <div><div style="font-weight:600;color:var(--hs-navy);"><?= $name ?></div><div style="font-size:12px;color:var(--hs-muted);"><?= ucfirst($status) ?></div></div>
            <div style="font-size:16px;font-weight:800;color:<?= $col ?>;"><?= $val ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div id="panelMedications" style="display:none;">
        <?php foreach ([['Amlodipine','5 mg','Once daily (morning)'],['Atorvastatin','10 mg','Once daily (night)'],['Losartan','25 mg','Once daily (night)']] as [$name,$dose,$freq]): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid var(--hs-border);">
          <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:20px;">💊</span>
            <div><div style="font-weight:700;color:var(--hs-navy);"><?= $name ?></div><div style="font-size:11px;color:var(--hs-muted);"><?= $freq ?></div></div>
          </div>
          <strong style="font-size:15px;color:var(--hs-blue);"><?= $dose ?></strong>
        </div>
        <?php endforeach; ?>
      </div>

      <div id="panelNotes" style="display:none;">
        <div style="background:var(--hs-off-white);border-radius:8px;padding:14px;font-size:13px;color:var(--hs-text);line-height:1.7;margin-bottom:14px;">
          Patient reports occasional headaches; advised salt reduction and hydration. BP readings stabilising within target range. Patient compliant with diet restrictions.
        </div>
        <form>
          <textarea class="form-control" rows="3" placeholder="Add clinical observation..."></textarea>
          <button type="button" class="btn-hs btn-primary-hs btn-sm-hs" style="margin-top:10px;" onclick="showToast('Note saved','success')"><i class="fas fa-save"></i> Save Note</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/charts.js"></script>
<script src="../assets/js/main.js"></script>
<script>
function openChart(patientId, name, nhsId, allergies, bp, hr, spo2) {
  document.getElementById('chartModal').style.display = 'flex';
  document.getElementById('modalSubtitle').textContent = name + '  ·  NHS: ' + nhsId;
  document.getElementById('modalBP').textContent  = bp || '—';
  document.getElementById('modalHR').textContent  = hr || '—';
  document.getElementById('modalSpo2').textContent= spo2 || '—';
  if (allergies) {
    document.getElementById('modalAllergyAlert').style.display = 'block';
    document.getElementById('modalAllergy').textContent = allergies;
  } else { document.getElementById('modalAllergyAlert').style.display = 'none'; }
  switchTab('vitals');
  setTimeout(() => {
    const ctx = document.getElementById('modalHrChart');
    if (ctx && !ctx._chartInstance) {
      ctx._chartInstance = new Chart(ctx, { type:'line', data:{labels:['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],datasets:[{data:[72,74,78,70,76,73,74],borderColor:'#EF4444',backgroundColor:'rgba(239,68,68,.1)',fill:true,tension:.4,pointRadius:3}]}, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{min:55,max:110,grid:{color:'#EFF6FF'}}}} });
    }
  },100);
}

function switchTab(tab) {
  ['vitals','labs','medications','notes'].forEach(t => {
    document.getElementById('panel'+t.charAt(0).toUpperCase()+t.slice(1)).style.display = t === tab ? 'block' : 'none';
    const btn = document.getElementById('tab'+t.charAt(0).toUpperCase()+t.slice(1));
    if (btn) { btn.style.background = t===tab?'var(--hs-blue)':'transparent'; btn.style.color = t===tab?'#fff':'var(--hs-muted)'; }
  });
}

// Monthly admissions
new Chart(document.getElementById('admissionsChart'), {
  type:'bar',
  data:{ labels:['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
    datasets:[{ label:'Appointments', data:<?= json_encode($monthlyData) ?>, backgroundColor:'#1565C0', borderRadius:5, borderSkipped:false }]},
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{grid:{color:'#EFF6FF'}}}}
});

// Risk distribution
const riskCounts = {
  critical: <?= count(array_filter($allPatients, fn($p) => $p['score_category'] === 'poor')) ?>,
  warning:  <?= count(array_filter($allPatients, fn($p) => $p['score_category'] === 'fair')) ?>,
  good:     <?= count(array_filter($allPatients, fn($p) => $p['score_category'] === 'good')) ?>,
  excellent:<?= count(array_filter($allPatients, fn($p) => $p['score_category'] === 'excellent')) ?>,
};
new Chart(document.getElementById('riskPieChart'), {
  type:'doughnut',
  data:{ labels:['Poor (High Risk)','Fair','Good','Excellent'],
    datasets:[{ data:[riskCounts.critical,riskCounts.warning,riskCounts.good,riskCounts.excellent],
      backgroundColor:['#DC2626','#D97706','#1565C0','#16A34A'], borderWidth:2, borderColor:'#fff' }]},
  options:{responsive:true,maintainAspectRatio:false,cutout:'60%',plugins:{legend:{position:'bottom',labels:{font:{size:10},boxWidth:10}}}}
});

// Global search
document.getElementById('globalSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('tr').forEach(row => {
    if(row.closest('thead')) return;
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});
</script>
</body>
</html>
