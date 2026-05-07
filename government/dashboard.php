<?php
require_once __DIR__ . '/../config/config.php';
requireRole('government');
$user = getCurrentUser(); $uid = $user['id'];

// Aggregate anonymised stats
$totalPatients   = $pdo->query("SELECT COUNT(*) FROM users WHERE role='patient'")->fetchColumn();
$totalAppts      = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$activePrescriptions = $pdo->query("SELECT COUNT(*) FROM prescriptions WHERE is_active=1")->fetchColumn();
$criticalAlerts  = $pdo->query("SELECT COUNT(*) FROM medical_records WHERE result_status='critical'")->fetchColumn();

$notifCount = getUnreadCount($pdo, $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Public Health Dashboard — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-landmark" style="color:var(--hs-blue);"></i> Public Health Analytics Dashboard</div>
      <div class="page-subtitle">Dept. of Health & Social Care · Anonymised Data · <?= date('d M Y') ?></div>
    </div>
    <div class="topbar-actions">
      <div style="display:flex;gap:4px;background:var(--hs-off-white);border-radius:8px;padding:4px;border:1px solid var(--hs-border);">
        <?php foreach (['Daily','Weekly','Monthly','Yearly'] as $v): ?>
        <button style="padding:6px 14px;border-radius:6px;border:none;font-size:12px;font-weight:600;cursor:pointer;background:<?= $v==='Monthly'?'var(--hs-blue)':'transparent' ?>;color:<?= $v==='Monthly'?'#fff':'var(--hs-muted)' ?>;"><?= $v ?></button>
        <?php endforeach; ?>
      </div>
      <button class="btn-hs btn-outline-hs btn-sm-hs"><i class="fas fa-download"></i> Export Report</button>
    </div>
  </div>

  <div class="hs-content">
    <!-- KPI Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px;">
      <div class="stat-card stat-blue"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-info"><div class="stat-label">Registered Patients</div><div class="stat-value" data-count="<?= $totalPatients ?>"><?= number_format($totalPatients) ?></div><div class="stat-sub"><span class="text-success">↑ 12%</span> this month</div></div></div>
      <div class="stat-card stat-warning"><div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div><div class="stat-info"><div class="stat-label">Critical Alerts</div><div class="stat-value" data-count="<?= $criticalAlerts ?>"><?= $criticalAlerts ?></div><div class="stat-sub"><span class="text-danger">↑ 8%</span> Requires action</div></div></div>
      <div class="stat-card stat-teal"><div class="stat-icon"><i class="fas fa-calendar-check"></i></div><div class="stat-info"><div class="stat-label">Total Appointments</div><div class="stat-value" data-count="<?= $totalAppts ?>"><?= number_format($totalAppts) ?></div></div></div>
      <div class="stat-card stat-green"><div class="stat-icon"><i class="fas fa-pills"></i></div><div class="stat-info"><div class="stat-label">Active Prescriptions</div><div class="stat-value" data-count="<?= $activePrescriptions ?>"><?= $activePrescriptions ?></div></div></div>
      <div class="stat-card stat-danger"><div class="stat-icon"><i class="fas fa-map-marker-alt"></i></div><div class="stat-info"><div class="stat-label">High-Risk Regions</div><div class="stat-value">3</div><div class="stat-sub">East Midlands, NW, NE</div></div></div>
    </div>

    <!-- Charts row 1 -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px;">
      <!-- National Trend -->
      <div class="hs-card">
        <div class="hs-card-header">
          <span class="card-title"><i class="fas fa-chart-line"></i> National Disease Trends (2025)</span>
          <div style="display:flex;gap:6px;">
            <span style="background:#DBEAFE;color:#1565C0;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:700;">● Hypertension</span>
            <span style="background:#FEE2E2;color:#DC2626;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:700;">● Diabetes</span>
            <span style="background:#FEF3C7;color:#D97706;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:700;">● Obesity</span>
          </div>
        </div>
        <div class="hs-card-body" style="height:280px;"><canvas id="govTrendChart"></canvas></div>
      </div>

      <!-- Disease distribution -->
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-chart-pie"></i> Disease Distribution</span></div>
        <div class="hs-card-body" style="height:280px;"><canvas id="diseasePieChart"></canvas></div>
      </div>
    </div>

    <!-- Region map + alerts -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
      <!-- UK Region Map placeholder -->
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-map-marked-alt"></i> Regional Health Risk Map — UK</span></div>
        <div class="hs-card-body">
          <div class="map-placeholder" style="min-height:320px;">
            <!-- SVG UK Regions (simplified) -->
            <svg viewBox="0 0 400 600" style="width:100%;max-height:320px;" fill="none">
              <text x="200" y="30" text-anchor="middle" fill="rgba(255,255,255,.4)" font-size="12" font-family="Inter">UK Regional Heat Map — High Sodium Risk</text>
              <!-- Scotland -->
              <ellipse cx="200" cy="100" rx="80" ry="70" fill="rgba(21,101,192,.4)" stroke="#1565C0" stroke-width="1"/>
              <text x="200" y="105" text-anchor="middle" fill="rgba(255,255,255,.8)" font-size="11">Scotland</text>
              <!-- England North -->
              <rect x="130" y="180" width="140" height="80" rx="8" fill="rgba(220,38,38,.5)" stroke="#DC2626" stroke-width="1"/>
              <text x="200" y="225" text-anchor="middle" fill="rgba(255,255,255,.9)" font-size="11">North England</text>
              <text x="200" y="240" text-anchor="middle" fill="rgba(255,255,255,.7)" font-size="9">HIGH RISK ▲</text>
              <!-- East Midlands -->
              <rect x="140" y="270" width="120" height="70" rx="8" fill="rgba(220,38,38,.7)" stroke="#DC2626" stroke-width="2"/>
              <text x="200" y="308" text-anchor="middle" fill="#fff" font-size="11" font-weight="700">East Midlands</text>
              <text x="200" y="323" text-anchor="middle" fill="rgba(255,255,255,.8)" font-size="9">CRITICAL ⚠</text>
              <!-- South -->
              <ellipse cx="195" cy="400" rx="100" ry="60" fill="rgba(217,119,6,.4)" stroke="#D97706" stroke-width="1"/>
              <text x="195" y="405" text-anchor="middle" fill="rgba(255,255,255,.8)" font-size="11">South England</text>
              <!-- Wales -->
              <ellipse cx="100" cy="310" rx="45" ry="55" fill="rgba(22,163,74,.4)" stroke="#16A34A" stroke-width="1"/>
              <text x="100" y="315" text-anchor="middle" fill="rgba(255,255,255,.8)" font-size="9">Wales</text>
            </svg>
          </div>
          <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;">
            <span style="display:flex;align-items:center;gap:6px;font-size:12px;"><span style="width:12px;height:12px;border-radius:2px;background:#DC2626;"></span> Critical Risk</span>
            <span style="display:flex;align-items:center;gap:6px;font-size:12px;"><span style="width:12px;height:12px;border-radius:2px;background:#D97706;"></span> Moderate Risk</span>
            <span style="display:flex;align-items:center;gap:6px;font-size:12px;"><span style="width:12px;height:12px;border-radius:2px;background:#1565C0;"></span> Low Risk</span>
            <span style="display:flex;align-items:center;gap:6px;font-size:12px;"><span style="width:12px;height:12px;border-radius:2px;background:#16A34A;"></span> Healthy</span>
          </div>
        </div>
      </div>

      <!-- Regional breakdown table -->
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-table"></i> Regional Breakdown</span></div>
        <div class="hs-card-body p-0">
          <?php
          $regions = [
            ['East Midlands','Hypertension / Sodium','critical','+34%'],
            ['North West','Type 2 Diabetes','critical','+22%'],
            ['North East','Obesity','attention','+18%'],
            ['South East','Respiratory','attention','+10%'],
            ['Yorkshire','Mental Health','attention','+12%'],
            ['London','Hypertension','healthy','+2%'],
            ['Wales','Diabetes','healthy','-5%'],
            ['Scotland','Obesity','healthy','-8%'],
          ];
          foreach ($regions as [$region,$condition,$zone,$change]):
            $color = ['critical'=>'#DC2626','attention'=>'#D97706','healthy'=>'#16A34A'][$zone];
          ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:11px 20px;border-bottom:1px solid var(--hs-border);">
            <div style="display:flex;align-items:center;gap:10px;">
              <span style="width:10px;height:10px;border-radius:50%;background:<?= $color ?>;flex-shrink:0;"></span>
              <div>
                <div style="font-weight:600;font-size:13px;color:var(--hs-navy);"><?= $region ?></div>
                <div style="font-size:12px;color:var(--hs-muted);"><?= $condition ?></div>
              </div>
            </div>
            <span style="font-size:14px;font-weight:800;color:<?= $color ?>;"><?= $change ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Hospital admissions + Policy alerts -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
      <!-- Hospital admissions -->
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-hospital"></i> Hospital Admissions (2025)</span></div>
        <div class="hs-card-body" style="height:250px;"><canvas id="admissionsChart"></canvas></div>
      </div>

      <!-- Policy alerts -->
      <div class="hs-card">
        <div class="hs-card-header">
          <span class="card-title"><i class="fas fa-bell"></i> Active Public Health Alerts</span>
          <span class="badge bg-danger">3 critical</span>
        </div>
        <div class="hs-card-body p-0">
          <?php
          $alerts = [
            ['🚨','CRITICAL','East Midlands Sodium Spike','Diet-related hypertension 34% above national average. Draft NHS campaign recommended.','#DC2626','#FEE2E2'],
            ['⚠️','WARNING','Type 2 Diabetes — NW England','New cases increased 22% over 6 months. School nutrition intervention flagged.','#D97706','#FEF3C7'],
            ['⚠️','WARNING','Obesity Trends — NE England','Sedentary behaviour up 18%. Active transport policy proposed.','#D97706','#FEF3C7'],
            ['ℹ️','INFO','Vaccination Coverage — Q4 2025','Covid booster uptake at 78%. Target: 85%. Push campaign scheduled.','#0891B2','#CFFAFE'],
          ];
          foreach ($alerts as [$ico, $type, $title, $msg, $color, $bg]):
          ?>
          <div style="padding:14px 20px;border-bottom:1px solid var(--hs-border);display:flex;gap:12px;align-items:flex-start;background:<?= $bg ?>08;">
            <span style="font-size:22px;"><?= $ico ?></span>
            <div style="flex:1;">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                <span style="font-size:11px;font-weight:700;color:<?= $color ?>;background:<?= $bg ?>;padding:2px 8px;border-radius:4px;letter-spacing:.5px;"><?= $type ?></span>
                <span style="font-size:13px;font-weight:700;color:var(--hs-navy);"><?= $title ?></span>
              </div>
              <p style="font-size:12px;color:var(--hs-muted);margin:0;"><?= $msg ?></p>
            </div>
            <button class="btn-hs btn-outline-hs btn-sm-hs" style="flex-shrink:0;">Draft Brief</button>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/charts.js"></script>
<script src="../assets/js/main.js"></script>
</body>
</html>
