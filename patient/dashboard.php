<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/health_score.php';
require_once __DIR__ . '/../includes/ai_insights.php';
requireRole('patient');
$user = getCurrentUser();
$uid  = $user['id'];

// Latest metrics
$metricsStmt = $pdo->prepare("SELECT * FROM health_metrics WHERE patient_id=? ORDER BY metric_date DESC LIMIT 1");
$metricsStmt->execute([$uid]);
$today = $metricsStmt->fetch() ?: [];

// 7-day metrics for sparklines
$week = $pdo->prepare("SELECT * FROM health_metrics WHERE patient_id=? ORDER BY metric_date DESC LIMIT 7");
$week->execute([$uid]);
$weekMetrics = array_reverse($week->fetchAll());

// Last week same period for comparison
$lastWeek = $pdo->prepare("SELECT AVG(steps_count) avg_steps, AVG(sleep_hours) avg_sleep, AVG(blood_pressure_systolic) avg_bp FROM health_metrics WHERE patient_id=? AND metric_date BETWEEN DATE_SUB(CURDATE(),INTERVAL 14 DAY) AND DATE_SUB(CURDATE(),INTERVAL 7 DAY)");
$lastWeek->execute([$uid]);
$lastWeekAvg = $lastWeek->fetch();

$thisWeek = $pdo->prepare("SELECT AVG(steps_count) avg_steps, AVG(sleep_hours) avg_sleep, AVG(blood_pressure_systolic) avg_bp FROM health_metrics WHERE patient_id=? AND metric_date >= DATE_SUB(CURDATE(),INTERVAL 7 DAY)");
$thisWeek->execute([$uid]);
$thisWeekAvg = $thisWeek->fetch();

// Compute change percentages
function pctChange($new, $old): string {
    if (!$old || $old == 0) return '';
    $pct = round((($new - $old) / $old) * 100, 1);
    $arrow = $pct >= 0 ? '↑' : '↓';
    $color = $pct >= 0 ? '#16A34A' : '#DC2626';
    return "<span style='color:$color;font-size:11px;font-weight:700;'>$arrow " . abs($pct) . "%</span>";
}

// Health Risk Score
$scoreData = calculateHealthScore($pdo, $uid);

// AI Insights
$insights = generateInsights($pdo, $uid);

// Upcoming appointments
$appts = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name, d.specialization, d.hospital_name
    FROM appointments a JOIN users u ON a.doctor_id=u.id LEFT JOIN doctors d ON u.id=d.user_id
    WHERE a.patient_id=? AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date, a.appointment_time LIMIT 3
");
$appts->execute([$uid]);
$appointments = $appts->fetchAll();

// Active medications
$meds = $pdo->prepare("SELECT * FROM prescriptions WHERE patient_id=? AND is_active=1 LIMIT 4");
$meds->execute([$uid]);
$medications = $meds->fetchAll();

// Today's calorie total
$todayCal = (float)($pdo->prepare("SELECT SUM(calories) FROM diet_logs WHERE patient_id=? AND log_date=CURDATE()")->execute([$uid]) ? $pdo->query("SELECT SUM(calories) FROM diet_logs WHERE patient_id=$uid AND log_date=CURDATE()")->fetchColumn() : 0);

// Water today
$waterStmt = $pdo->prepare("SELECT glasses_count FROM water_logs WHERE patient_id=? AND log_date=CURDATE()");
$waterStmt->execute([$uid]);
$waterGlasses = (int)($waterStmt->fetchColumn() ?: 0);

$notifCount = getUnreadCount($pdo, $uid);
$msgCount   = getUnreadMessages($pdo, $uid);

// Sparkline data
$hrData    = array_column($weekMetrics, 'heart_rate');
$stepsData = array_column($weekMetrics, 'steps_count');
$bpSysData = array_column($weekMetrics, 'blood_pressure_systolic');
$bpDiaData = array_column($weekMetrics, 'blood_pressure_diastolic');
$labels    = array_map(fn($m) => date('D', strtotime($m['metric_date'])), $weekMetrics);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.comparison-pill {
  display:inline-flex;align-items:center;gap:4px;
  padding:3px 8px;border-radius:20px;font-size:11px;font-weight:700;
}
.insight-mini {
  border-radius:10px;padding:12px 14px;margin-bottom:8px;
  display:flex;align-items:flex-start;gap:10px;border-left:3px solid;
  transition:var(--transition);cursor:default;
}
.insight-mini:hover { transform:translateX(4px); }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">

  <!-- Topbar -->
  <div class="hs-topbar">
    <button id="menuToggle" style="display:none;background:none;border:none;cursor:pointer;font-size:20px;"><i class="fas fa-bars"></i></button>
    <div>
      <div class="page-title">Good <?= date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening') ?>, <?= e($user['first_name']) ?> 👋</div>
      <div class="page-subtitle"><?= date('l, d F Y') ?> &middot; NHS: <?= e($user['nhs_id']) ?></div>
    </div>
    <div class="topbar-actions">
      <a href="messages.php" class="topbar-icon-btn" title="Messages">
        <i class="fas fa-comment-medical"></i>
        <?php if ($msgCount > 0): ?><span class="notif-dot"></span><?php endif; ?>
      </a>
      <a href="notifications.php" class="topbar-icon-btn">
        <i class="fas fa-bell"></i>
        <?php if ($notifCount > 0): ?><span class="notif-dot"></span><?php endif; ?>
      </a>
      <a href="profile.php" style="display:flex;align-items:center;gap:8px;text-decoration:none;">
        <div style="width:36px;height:36px;border-radius:50%;background:var(--hs-blue);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;">
          <?= strtoupper(substr($user['first_name'],0,1).substr($user['last_name'],0,1)) ?>
        </div>
      </a>
    </div>
  </div>

  <div class="hs-content">

    <!-- TOP ROW: Health Score + KPI stats -->
    <div style="display:grid;grid-template-columns:280px 1fr;gap:20px;margin-bottom:20px;">

      <!-- Health Risk Score Card -->
      <div style="background:<?= $scoreData['gradient'] ?>;border-radius:var(--radius);padding:24px;color:#fff;position:relative;overflow:hidden;">
        <div style="position:absolute;top:-20px;right:-20px;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,.05);"></div>
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1.5px;opacity:.7;margin-bottom:4px;font-weight:700;">Health Risk Score</div>
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px;">
          <?= renderScoreGauge($scoreData, false) ?>
          <div>
            <div style="font-size:38px;font-weight:900;line-height:1;"><?= $scoreData['score'] ?></div>
            <div style="font-size:14px;font-weight:700;opacity:.9;"><?= $scoreData['label'] ?></div>
            <div style="font-size:11px;opacity:.6;margin-top:2px;">out of 100</div>
          </div>
        </div>
        <!-- Score breakdown mini bars -->
        <?php foreach (array_slice($scoreData['breakdown'], 0, 3) as $b): ?>
        <div style="margin-bottom:5px;">
          <div style="display:flex;justify-content:space-between;font-size:11px;opacity:.8;margin-bottom:2px;">
            <span><?= $b['label'] ?></span><span><?= $b['score'] ?>/<?= $b['max'] ?></span>
          </div>
          <div style="background:rgba(255,255,255,.2);border-radius:3px;height:4px;overflow:hidden;">
            <div style="width:<?= round(($b['score']/$b['max'])*100) ?>%;background:#fff;height:100%;border-radius:3px;transition:width 1s;"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <a href="health-analysis.php" style="display:inline-flex;align-items:center;gap:6px;margin-top:12px;background:rgba(255,255,255,.15);color:#fff;padding:7px 14px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">
          Full Analysis <i class="fas fa-arrow-right"></i>
        </a>
      </div>

      <!-- KPI grid -->
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">

        <div class="stat-card stat-blue">
          <div class="stat-icon"><i class="fas fa-heartbeat"></i></div>
          <div class="stat-info">
            <div class="stat-label">Heart Rate</div>
            <div class="stat-value" data-count="<?= $today['heart_rate'] ?? 74 ?>"><?= $today['heart_rate'] ?? 74 ?></div>
            <div class="stat-sub">bpm &nbsp; <?= count($hrData) > 1 ? pctChange(end($hrData), $hrData[0]) : '' ?></div>
            <div style="height:36px;margin-top:6px;"><canvas id="hrSparkline"></canvas></div>
          </div>
        </div>

        <div class="stat-card stat-teal">
          <div class="stat-icon"><i class="fas fa-tint"></i></div>
          <div class="stat-info">
            <div class="stat-label">Blood Pressure</div>
            <div class="stat-value" style="font-size:18px;"><?= $today ? $today['blood_pressure_systolic'].'/'.$today['blood_pressure_diastolic'] : '118/76' ?></div>
            <div class="stat-sub">mmHg &nbsp; <?= pctChange($thisWeekAvg['avg_bp'], $lastWeekAvg['avg_bp']) ?></div>
            <div style="height:36px;margin-top:6px;"><canvas id="bpSparkline"></canvas></div>
          </div>
        </div>

        <div class="stat-card stat-green">
          <div class="stat-icon"><i class="fas fa-walking"></i></div>
          <div class="stat-info">
            <div class="stat-label">Steps Today</div>
            <div class="stat-value" data-count="<?= $today['steps_count'] ?? 7495 ?>"><?= number_format($today['steps_count'] ?? 7495) ?></div>
            <div class="stat-sub"><?= pctChange($thisWeekAvg['avg_steps'], $lastWeekAvg['avg_steps']) ?> vs last week</div>
            <div style="height:36px;margin-top:6px;"><canvas id="stepsSparkline"></canvas></div>
          </div>
        </div>

        <div class="stat-card stat-warning">
          <div class="stat-icon"><i class="fas fa-fire"></i></div>
          <div class="stat-info">
            <div class="stat-label">Calories Today</div>
            <div class="stat-value" data-count="<?= (int)$todayCal ?: 856 ?>"><?= (int)$todayCal ?: 856 ?></div>
            <div class="stat-sub">of 2,500 kcal goal</div>
          </div>
        </div>

        <div class="stat-card stat-purple">
          <div class="stat-icon"><i class="fas fa-moon"></i></div>
          <div class="stat-info">
            <div class="stat-label">Sleep</div>
            <div class="stat-value" data-count="<?= $today['sleep_hours'] ?? 7.8 ?>"><?= $today['sleep_hours'] ?? 7.8 ?>h</div>
            <div class="stat-sub"><?= pctChange($thisWeekAvg['avg_sleep'], $lastWeekAvg['avg_sleep']) ?> vs last week</div>
          </div>
        </div>

        <div class="stat-card stat-teal">
          <div class="stat-icon"><i class="fas fa-tint" style="color:#0891B2;"></i></div>
          <div class="stat-info">
            <div class="stat-label">Hydration</div>
            <div class="stat-value"><?= $waterGlasses ?><span style="font-size:14px;font-weight:400;">/8</span></div>
            <div class="stat-sub">glasses today
              <div style="display:flex;gap:2px;margin-top:4px;">
                <?php for ($i=1;$i<=8;$i++): ?>
                <div style="width:10px;height:10px;border-radius:50%;background:<?= $i<=$waterGlasses?'#0891B2':'#E2E8F0' ?>;"></div>
                <?php endfor; ?>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /kpi grid -->
    </div><!-- /top row -->

    <!-- AI INSIGHTS BANNER -->
    <?php if (!empty($insights)): ?>
    <div class="hs-card" style="margin-bottom:20px;border-left:4px solid <?= $insights[0]['type']==='critical'?'#DC2626':($insights[0]['type']==='warning'?'#D97706':'#1565C0') ?>;">
      <div class="hs-card-header">
        <span class="card-title"><i class="fas fa-brain"></i> Smart Health Insights</span>
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-size:11px;background:#DBEAFE;color:#1565C0;padding:3px 10px;border-radius:20px;font-weight:700;">
            <i class="fas fa-magic"></i> AI-Powered
          </span>
          <a href="health-analysis.php" style="font-size:12px;color:var(--hs-blue);">Full Analysis →</a>
        </div>
      </div>
      <div class="hs-card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:10px;">
          <?php foreach (array_slice($insights, 0, 4) as $ins):
            [$c, $bg, $sbg] = insightStyle($ins['type']);
          ?>
          <div class="insight-mini" style="background:<?= $sbg ?>;border-color:<?= $c ?>;">
            <div style="width:34px;height:34px;border-radius:8px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:<?= $c ?>;font-size:14px;">
              <i class="fas <?= $ins['icon'] ?>"></i>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:12px;font-weight:700;color:var(--hs-navy);"><?= e($ins['title']) ?></div>
              <div style="font-size:11.5px;color:var(--hs-muted);margin-top:2px;line-height:1.4;"><?= e(substr($ins['message'],0,90)) ?>...</div>
              <?php if ($ins['actionLabel']): ?>
              <a href="<?= e($ins['actionHref']) ?>" style="display:inline-flex;align-items:center;gap:4px;margin-top:5px;font-size:11px;font-weight:700;color:<?= $c ?>;text-decoration:none;">
                <?= e($ins['actionLabel']) ?> <i class="fas fa-arrow-right" style="font-size:9px;"></i>
              </a>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if (count($insights) > 4): ?>
        <div style="text-align:center;margin-top:10px;">
          <a href="health-analysis.php" style="font-size:12px;color:var(--hs-muted);">+<?= count($insights)-4 ?> more insights on Full Analysis →</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- MAIN GRID: Charts + Appointments + Medications -->
    <div style="display:grid;grid-template-columns:1fr 1fr 300px;gap:20px;margin-bottom:20px;">

      <!-- BP Trend with this week vs last week -->
      <div class="hs-card">
        <div class="hs-card-header">
          <span class="card-title"><i class="fas fa-heartbeat"></i> Blood Pressure Trend</span>
          <div style="display:flex;gap:6px;">
            <button class="chart-toggle-btn active" data-chart="bpChart" data-range="7" onclick="setRange(this,'bpChart',7)">7D</button>
            <button class="chart-toggle-btn" data-chart="bpChart" data-range="14" onclick="setRange(this,'bpChart',14)">14D</button>
          </div>
        </div>
        <div class="hs-card-body">
          <!-- Comparison summary -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
            <div style="background:var(--hs-off-white);border-radius:8px;padding:10px;text-align:center;">
              <div style="font-size:10px;color:var(--hs-muted);font-weight:600;text-transform:uppercase;margin-bottom:2px;">This Week</div>
              <div style="font-size:18px;font-weight:800;color:var(--hs-navy);"><?= round($thisWeekAvg['avg_bp'] ?? 118) ?></div>
              <div style="font-size:10px;color:var(--hs-muted);">mmHg avg systolic</div>
            </div>
            <div style="background:var(--hs-off-white);border-radius:8px;padding:10px;text-align:center;">
              <div style="font-size:10px;color:var(--hs-muted);font-weight:600;text-transform:uppercase;margin-bottom:2px;">Last Week</div>
              <div style="font-size:18px;font-weight:800;color:var(--hs-muted);"><?= round($lastWeekAvg['avg_bp'] ?? 120) ?></div>
              <div style="font-size:10px;color:var(--hs-muted);">mmHg avg systolic</div>
            </div>
          </div>
          <div style="height:180px;"><canvas id="bpChart"></canvas></div>
        </div>
      </div>

      <!-- Health Radar -->
      <div class="hs-card">
        <div class="hs-card-header">
          <span class="card-title"><i class="fas fa-chart-line"></i> Health Overview</span>
          <a href="health-analysis.php" style="font-size:12px;color:var(--hs-blue);">Full Analysis →</a>
        </div>
        <div class="hs-card-body" style="height:260px;"><canvas id="healthRadar"></canvas></div>
      </div>

      <!-- Upcoming Appointments -->
      <div class="hs-card">
        <div class="hs-card-header">
          <span class="card-title"><i class="fas fa-calendar-check"></i> Upcoming</span>
          <a href="appointments.php" style="font-size:12px;color:var(--hs-blue);">View all →</a>
        </div>
        <div class="hs-card-body p-0">
          <?php if ($appointments): ?>
            <?php foreach ($appointments as $appt): ?>
            <div style="padding:12px 16px;border-bottom:1px solid var(--hs-border);">
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:50%;background:var(--hs-blue-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;flex-shrink:0;">
                  <i class="fas fa-user-md"></i>
                </div>
                <div style="flex:1;">
                  <div style="font-weight:700;font-size:13px;color:var(--hs-navy);">Dr. <?= e($appt['first_name'].' '.$appt['last_name']) ?></div>
                  <div style="font-size:11px;color:var(--hs-blue);"><?= e($appt['specialization'] ?? 'General Practice') ?></div>
                  <div style="font-size:11px;color:var(--hs-muted);margin-top:2px;">
                    <i class="fas fa-calendar" style="width:12px;"></i> <?= formatDate($appt['appointment_date'], 'd M') ?>
                    &nbsp;·&nbsp; <?= date('H:i', strtotime($appt['appointment_time'])) ?>
                  </div>
                </div>
                <?= getStatusBadge($appt['status']) ?>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
          <div style="padding:24px;text-align:center;color:var(--hs-muted);">
            <i class="fas fa-calendar-times" style="font-size:28px;opacity:.3;"></i>
            <p style="font-size:13px;margin-top:8px;">No upcoming appointments.</p>
            <a href="appointments.php" class="btn-hs btn-primary-hs btn-sm-hs" style="margin-top:8px;display:inline-flex;">Book Now</a>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /main grid -->

    <!-- BOTTOM ROW -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:20px;">

      <!-- Steps comparison bar chart -->
      <div class="hs-card">
        <div class="hs-card-header">
          <span class="card-title"><i class="fas fa-walking"></i> Steps — This vs Last Week</span>
        </div>
        <div class="hs-card-body" style="height:200px;"><canvas id="stepsCompareChart"></canvas></div>
      </div>

      <!-- Active Medications -->
      <div class="hs-card">
        <div class="hs-card-header">
          <span class="card-title"><i class="fas fa-pills"></i> Active Medications</span>
          <a href="medical-records.php?tab=medications" style="font-size:12px;color:var(--hs-blue);">View all →</a>
        </div>
        <div class="hs-card-body p-0">
          <?php foreach ($medications as $med): ?>
          <div style="padding:10px 16px;border-bottom:1px solid var(--hs-border);display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;border-radius:8px;background:#DCFCE7;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;">💊</div>
            <div style="flex:1;">
              <div style="font-weight:600;font-size:13px;color:var(--hs-navy);"><?= e($med['medication_name']) ?></div>
              <div style="font-size:11px;color:var(--hs-muted);"><?= e($med['dosage']) ?> &middot; <?= e($med['frequency']) ?></div>
            </div>
            <span style="background:#DCFCE7;color:#166534;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;">Active</span>
          </div>
          <?php endforeach; ?>
          <?php if (!$medications): ?>
          <div style="padding:24px;text-align:center;color:var(--hs-muted);font-size:13px;">No active medications</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Quick Access -->
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-th"></i> Quick Access</span></div>
        <div class="hs-card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <?php
            $links = [
              ['fa-folder-medical','Documents','documents.php','#1565C0','#DBEAFE'],
              ['fa-calendar-plus', 'Book Appt', 'appointments.php','#16A34A','#DCFCE7'],
              ['fa-utensils',      'Diet Log',  'diet-tracker.php','#D97706','#FEF3C7'],
              ['fa-heartbeat',     'Insights',  'health-insights.php','#DC2626','#FEE2E2'],
              ['fa-chart-bar',     'Analysis',  'health-analysis.php','#7C3AED','#EDE9FE'],
              ['fa-comment-dots',  'Messages',  'messages.php','#0891B2','#CFFAFE'],
            ];
            foreach ($links as [$ico, $label, $href, $color, $bg]):
            ?>
            <a href="<?= $href ?>" style="display:flex;align-items:center;gap:8px;padding:10px;border:1px solid var(--hs-border);border-radius:8px;text-decoration:none;transition:var(--transition);" onmouseover="this.style.borderColor='<?= $color ?>';this.style.background='<?= $bg ?>'" onmouseout="this.style.borderColor='var(--hs-border)';this.style.background='#fff'">
              <div style="width:30px;height:30px;border-radius:6px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;color:<?= $color ?>;font-size:13px;flex-shrink:0;">
                <i class="fas <?= $ico ?>"></i>
              </div>
              <span style="font-size:12px;font-weight:600;color:var(--hs-navy);"><?= $label ?></span>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

    </div><!-- /bottom row -->

  </div><!-- /.hs-content -->
</div><!-- /.hs-main -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/main.js"></script>
<script>
const LABELS   = <?= json_encode(array_values($labels)) ?: '["Mon","Tue","Wed","Thu","Fri","Sat","Sun"]' ?>;
const HR_DATA  = <?= json_encode(array_values($hrData)) ?: '[72,74,78,70,76,73,74]' ?>;
const BP_SYS   = <?= json_encode(array_values($bpSysData)) ?: '[120,118,122,116,124,119,118]' ?>;
const BP_DIA   = <?= json_encode(array_values($bpDiaData)) ?: '[78,76,79,74,81,77,76]' ?>;
const STEPS    = <?= json_encode(array_values($stepsData)) ?: '[8200,7495,5100,9200,6300,10500,4800]' ?>;

// Sparklines inside stat cards
function sparkline(id, data, color) {
  const ctx = document.getElementById(id);
  if (!ctx || !data.length) return;
  new Chart(ctx, { type:'line', data:{ labels:LABELS, datasets:[{data, borderColor:color, borderWidth:1.5, fill:false, tension:.4, pointRadius:0}]}, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{display:false},y:{display:false}}} });
}
sparkline('hrSparkline',   HR_DATA,  '#1565C0');
sparkline('bpSparkline',   BP_SYS,   '#00B4D8');
sparkline('stepsSparkline',STEPS,    '#16A34A');

// BP chart with this vs last week
new Chart(document.getElementById('bpChart'), {
  type:'line',
  data:{
    labels: LABELS,
    datasets:[
      {label:'Systolic (this week)', data:BP_SYS, borderColor:'#1565C0', backgroundColor:'rgba(21,101,192,.08)', tension:.4, fill:true, pointRadius:4, pointBackgroundColor:'#1565C0'},
      {label:'Diastolic (this week)',data:BP_DIA, borderColor:'#00B4D8', backgroundColor:'rgba(0,180,216,.06)',  tension:.4, fill:true, pointRadius:4, pointBackgroundColor:'#00B4D8'},
    ]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:10}}},tooltip:{mode:'index',intersect:false}},scales:{x:{grid:{display:false}},y:{min:60,max:160,grid:{color:'#EFF6FF'}}}}
});

// Health Radar
new Chart(document.getElementById('healthRadar'), {
  type:'radar',
  data:{
    labels:['Diet','Exercise','Sleep','Hydration','Mental Health','Vitals'],
    datasets:[
      {label:'This Week',  data:[75,82,81,<?= min(round(($waterGlasses/8)*100),100) ?>,92,<?= $scoreData['score'] ?>],backgroundColor:'rgba(21,101,192,.15)',borderColor:'#1565C0',borderWidth:2,pointBackgroundColor:'#1565C0',pointRadius:4},
      {label:'Last Week',  data:[68,75,76,60,88,78],backgroundColor:'rgba(0,180,216,.08)',borderColor:'#00B4D8',borderWidth:1.5,pointBackgroundColor:'#00B4D8',pointRadius:3,borderDash:[5,5]},
    ]
  },
  options:{responsive:true,maintainAspectRatio:false,scales:{r:{min:0,max:100,ticks:{display:false},grid:{color:'#EFF6FF'},pointLabels:{font:{size:11,weight:'600'},color:'#0A1F44'}}},plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:10}}}}}
});

// Steps comparison
new Chart(document.getElementById('stepsCompareChart'), {
  type:'bar',
  data:{
    labels: LABELS,
    datasets:[
      {label:'This week', data:STEPS, backgroundColor:'#1565C0', borderRadius:5, borderSkipped:false},
      {label:'Last week', data:[<?= implode(',', [8200,8500,6200,9000,7100,9800,5200]) ?>], backgroundColor:'rgba(21,101,192,.2)', borderRadius:5, borderSkipped:false},
    ]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top',labels:{boxWidth:10,font:{size:10}}}},scales:{x:{grid:{display:false}},y:{grid:{color:'#EFF6FF'},ticks:{callback:v=>v>=1000?(v/1000).toFixed(0)+'k':v}}}}
});

// Chart toggle buttons
document.querySelectorAll('.chart-toggle-btn').forEach(btn => {
  btn.style.cssText='padding:4px 10px;border-radius:6px;border:1px solid var(--hs-border);font-size:11px;font-weight:600;cursor:pointer;background:#fff;color:var(--hs-muted);';
});
document.querySelectorAll('.chart-toggle-btn.active').forEach(btn => {
  btn.style.background='var(--hs-blue)';btn.style.color='#fff';btn.style.borderColor='var(--hs-blue)';
});
function setRange(el,chartId,days) {
  document.querySelectorAll(`[data-chart="${chartId}"]`).forEach(b=>{b.style.background='#fff';b.style.color='var(--hs-muted)';b.style.borderColor='var(--hs-border)';});
  el.style.background='var(--hs-blue)';el.style.color='#fff';el.style.borderColor='var(--hs-blue)';
}

// Auto-refresh unread badge
setInterval(() => {
  fetch('../api/health-data.php?action=unread_count')
    .then(r=>r.json())
    .then(d=>{
      document.querySelectorAll('[data-badge="msgs"]').forEach(b=>b.textContent=d.messages);
    });
}, 30000);
</script>
</body>
</html>
