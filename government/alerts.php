<?php
require_once __DIR__ . '/../config/config.php';
requireRole('government');
$user = getCurrentUser();
$uid  = $user['id'];
$notifCount = getUnreadCount($pdo, $uid);

// Filter
$filterStatus = $_GET['status'] ?? 'all';
$filterRegion = $_GET['region'] ?? 'all';

$alerts = [
    ['id'=>1,'type'=>'critical','icon'=>'🚨','title'=>'East Midlands — Hypertension Spike',
     'region'=>'East Midlands','condition'=>'Hypertension','change'=>'+34%',
     'detail'=>'Diet-related hypertension 34% above national average. Salt intake per capita recorded at 11.2g/day vs NHS recommended 6g/day. 4,821 new cases logged Q3 2025.',
     'recommendation'=>'Draft NHS reduced-salt campaign targeting East Midlands supermarkets, schools, and community centres. Estimated reach: 2.1M residents.',
     'deadline'=>'2025-11-15','status'=>'open','affected'=>'4,821 patients'],
    ['id'=>2,'type'=>'critical','icon'=>'🚨','title'=>'North West — Type 2 Diabetes Surge',
     'region'=>'North West','condition'=>'Type 2 Diabetes','change'=>'+22%',
     'detail'=>'6,103 new Type 2 Diabetes diagnoses in North West this year. Under-40 cohort accounts for 38% of new cases — a 5-year high. Processed food consumption and physical inactivity are primary drivers.',
     'recommendation'=>'Commission workplace wellness programmes and subsidised gym access for low-income households. Recommend school nutrition intervention pilot.',
     'deadline'=>'2025-11-30','status'=>'open','affected'=>'6,103 patients'],
    ['id'=>3,'type'=>'warning','icon'=>'⚠️','title'=>'North East — Obesity Trend',
     'region'=>'North East','condition'=>'Obesity','change'=>'+18%',
     'detail'=>'BMI above 30 recorded in 31% of North East adults — up from 26% in 2023. Strong correlation with sedentary commuting patterns and reduced green space access.',
     'recommendation'=>'Active transport policy proposal: subsidised cycling infrastructure and 10,000-step challenge campaign through NHS app.',
     'deadline'=>'2025-12-15','status'=>'in_review','affected'=>'2,987 patients'],
    ['id'=>4,'type'=>'warning','icon'=>'⚠️','title'=>'Yorkshire — Mental Health Waiting Times',
     'region'=>'Yorkshire','condition'=>'Mental Health','change'=>'+12%',
     'detail'=>'NHS mental health referrals up 12% in Yorkshire but capacity only increased 4%. Average CAMHS waiting time now 18.4 weeks, far exceeding the 4-week target.',
     'recommendation'=>'Emergency funding allocation for 400 additional counsellors. Expand digital mental health services via NHS app for interim support.',
     'deadline'=>'2025-11-01','status'=>'in_review','affected'=>'3,412 referrals'],
    ['id'=>5,'type'=>'warning','icon'=>'⚠️','title'=>'National — Vaccination Coverage Below Target',
     'region'=>'National','condition'=>'Covid-19 Booster','change'=>'78% (target: 85%)',
     'detail'=>'Booster uptake at 78% nationally. Over-65s: 91% — well above target. Under-40s: only 54%. Gap widening by 2% per quarter. Risk of winter wave if not addressed.',
     'recommendation'=>'Targeted mobile vaccination unit deployment in urban under-40 hotspots. GP text-reminder campaign with personalised booking links.',
     'deadline'=>'2025-10-31','status'=>'escalated','affected'=>'~8.2M unvaccinated'],
    ['id'=>6,'type'=>'info','icon'=>'ℹ️','title'=>'London — Positive Active Travel Trend',
     'region'=>'London','condition'=>'Cardiovascular / Obesity','change'=>'+2% (below avg)',
     'detail'=>'London showing only +2% growth in obesity vs national +18%. Cycle Superhighway and ULEZ expansion credited with 14% increase in active commuting. CVD hospitalisations down 6%.',
     'recommendation'=>'Share London model findings with transport ministers in North East and West Midlands. Commission feasibility study for regional replication.',
     'deadline'=>'2026-01-15','status'=>'resolved','affected'=>'Positive — 9M residents'],
    ['id'=>7,'type'=>'info','icon'=>'ℹ️','title'=>'Scotland — Diet Intervention Success',
     'region'=>'Scotland','condition'=>'Obesity / Diabetes','change'=>'-8%',
     'detail'=>'Scotland recording −8% in diet-related illness following 2023 sugar tax extension and school meal programme reform. Progress exceeds WHO targets.',
     'recommendation'=>'Document findings for cross-UK policy brief. Recommend extending sugar tax to ultra-processed foods nationally.',
     'deadline'=>'2026-02-01','status'=>'resolved','affected'=>'Positive — 5.5M residents'],
];

// Apply filters
$filtered = array_filter($alerts, function($a) use ($filterStatus, $filterRegion) {
    $matchStatus = $filterStatus === 'all' || $a['status'] === $filterStatus || $a['type'] === $filterStatus;
    $matchRegion = $filterRegion === 'all' || $a['region'] === $filterRegion;
    return $matchStatus && $matchRegion;
});

$counts = ['critical'=>0,'warning'=>0,'in_review'=>0,'resolved'=>0];
foreach ($alerts as $a) {
    if ($a['type'] === 'critical') $counts['critical']++;
    if ($a['type'] === 'warning')  $counts['warning']++;
    if ($a['status'] === 'in_review') $counts['in_review']++;
    if ($a['status'] === 'resolved')  $counts['resolved']++;
}

$regions = array_unique(array_column($alerts, 'region'));

$typeStyles = [
    'critical' => ['#DC2626','#FEE2E2','Critical'],
    'warning'  => ['#D97706','#FEF3C7','Warning'],
    'info'     => ['#0891B2','#CFFAFE','Info'],
];
$statusStyles = [
    'open'       => ['#DC2626','#FEE2E2','Open'],
    'in_review'  => ['#D97706','#FEF3C7','In Review'],
    'escalated'  => ['#7C3AED','#EDE9FE','Escalated'],
    'resolved'   => ['#16A34A','#DCFCE7','Resolved'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Alerts — HealthSphere Gov</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">

  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-bell" style="color:var(--hs-blue);"></i> Public Health Alerts</div>
      <div class="page-subtitle">Real-time alerts requiring policy intervention &middot; <?= date('d M Y') ?></div>
    </div>
    <div class="topbar-actions">
      <button class="btn-hs btn-outline-hs btn-sm-hs"><i class="fas fa-download"></i> Export PDF</button>
      <button class="btn-hs btn-primary-hs btn-sm-hs"><i class="fas fa-plus"></i> Raise Alert</button>
    </div>
  </div>

  <div class="hs-content">

    <!-- Summary KPIs -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px;">
      <div class="stat-card stat-danger">
        <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div class="stat-info"><div class="stat-label">Critical Alerts</div><div class="stat-value"><?= $counts['critical'] ?></div><div class="stat-sub">Immediate action needed</div></div>
      </div>
      <div class="stat-card stat-warning">
        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-info"><div class="stat-label">Warnings</div><div class="stat-value"><?= $counts['warning'] ?></div><div class="stat-sub">Under monitoring</div></div>
      </div>
      <div class="stat-card stat-blue">
        <div class="stat-icon"><i class="fas fa-search"></i></div>
        <div class="stat-info"><div class="stat-label">In Review</div><div class="stat-value"><?= $counts['in_review'] ?></div><div class="stat-sub">Policy team assigned</div></div>
      </div>
      <div class="stat-card stat-green">
        <div class="stat-icon"><i class="fas fa-check-double"></i></div>
        <div class="stat-info"><div class="stat-label">Resolved</div><div class="stat-value"><?= $counts['resolved'] ?></div><div class="stat-sub">Closed this quarter</div></div>
      </div>
    </div>

    <!-- Filters -->
    <div style="display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap;align-items:center;">
      <span style="font-size:13px;font-weight:600;color:var(--hs-navy);">Filter:</span>
      <?php
      $statusFilters = ['all'=>'All Alerts','critical'=>'Critical','warning'=>'Warning','in_review'=>'In Review','resolved'=>'Resolved'];
      foreach ($statusFilters as $key => $label):
        $active = $filterStatus === $key;
      ?>
      <a href="?status=<?= $key ?>&region=<?= $filterRegion ?>"
         style="padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;border:1.5px solid <?= $active?'var(--hs-blue)':'var(--hs-border)' ?>;background:<?= $active?'var(--hs-blue)':'#fff' ?>;color:<?= $active?'#fff':'var(--hs-muted)' ?>;">
        <?= $label ?>
      </a>
      <?php endforeach; ?>

      <select onchange="location='?status=<?= $filterStatus ?>&region='+this.value"
              style="padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;border:1.5px solid var(--hs-border);background:#fff;color:var(--hs-muted);cursor:pointer;">
        <option value="all" <?= $filterRegion==='all'?'selected':'' ?>>All Regions</option>
        <?php foreach ($regions as $reg): ?>
        <option value="<?= e($reg) ?>" <?= $filterRegion===$reg?'selected':'' ?>><?= e($reg) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Alerts list -->
    <div style="display:flex;flex-direction:column;gap:14px;">
      <?php foreach ($filtered as $alert):
        [$tc, $tbg, $tlbl] = $typeStyles[$alert['type']];
        [$sc, $sbg, $slbl] = $statusStyles[$alert['status']];
        $urgentBorder = $alert['type'] === 'critical' ? "border-left:4px solid $tc;" : ($alert['type']==='warning' ? "border-left:4px solid $tc;" : '');
      ?>
      <div class="hs-card" style="<?= $urgentBorder ?>" id="alert-<?= $alert['id'] ?>">
        <div class="hs-card-body">
          <div style="display:flex;gap:14px;align-items:flex-start;">

            <!-- Icon -->
            <div style="font-size:28px;flex-shrink:0;line-height:1;"><?= $alert['icon'] ?></div>

            <!-- Content -->
            <div style="flex:1;">
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap;">
                <span style="background:<?= $tbg ?>;color:<?= $tc ?>;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;"><?= $tlbl ?></span>
                <span style="background:<?= $sbg ?>;color:<?= $sc ?>;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;"><?= $slbl ?></span>
                <span style="background:var(--hs-off-white);color:var(--hs-muted);padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;">
                  <i class="fas fa-map-marker-alt"></i> <?= e($alert['region']) ?>
                </span>
                <span style="background:var(--hs-off-white);color:var(--hs-muted);padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;">
                  <?= e($alert['condition']) ?> &nbsp;·&nbsp; <?= e($alert['change']) ?>
                </span>
              </div>

              <h5 style="margin:0 0 8px;font-size:15px;font-weight:800;color:var(--hs-navy);"><?= e($alert['title']) ?></h5>
              <p style="font-size:13px;color:var(--hs-text);line-height:1.7;margin:0 0 10px;"><?= e($alert['detail']) ?></p>

              <div style="background:var(--hs-off-white);border-radius:8px;padding:12px 14px;margin-bottom:12px;border-left:3px solid var(--hs-blue);">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--hs-muted);margin-bottom:4px;">
                  <i class="fas fa-lightbulb" style="color:var(--hs-blue);"></i> Recommended Action
                </div>
                <p style="font-size:13px;color:var(--hs-text);margin:0;line-height:1.6;"><?= e($alert['recommendation']) ?></p>
              </div>

              <div style="display:flex;gap:16px;font-size:12px;color:var(--hs-muted);flex-wrap:wrap;">
                <span><i class="fas fa-calendar"></i> Deadline: <strong style="color:var(--hs-navy);"><?= formatDate($alert['deadline']) ?></strong></span>
                <span><i class="fas fa-users"></i> Affected: <strong style="color:var(--hs-navy);"><?= e($alert['affected']) ?></strong></span>
                <span><i class="fas fa-tag"></i> ID: <code style="background:var(--hs-bg);padding:1px 6px;border-radius:3px;">ALERT-<?= str_pad($alert['id'],3,'0',STR_PAD_LEFT) ?></code></span>
              </div>
            </div>

            <!-- Actions -->
            <div style="display:flex;flex-direction:column;gap:8px;flex-shrink:0;">
              <?php if ($alert['status'] !== 'resolved'): ?>
              <button class="btn-hs btn-primary-hs btn-sm-hs"><i class="fas fa-file-alt"></i> Draft Policy</button>
              <button class="btn-hs btn-outline-hs btn-sm-hs"><i class="fas fa-share"></i> Escalate</button>
              <button class="btn-hs btn-success-hs btn-sm-hs"><i class="fas fa-check"></i> Resolve</button>
              <?php else: ?>
              <button class="btn-hs btn-sm-hs" style="background:#DCFCE7;color:#16A34A;border:none;"><i class="fas fa-check-double"></i> Closed</button>
              <?php endif; ?>
            </div>

          </div>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if (empty($filtered)): ?>
      <div style="text-align:center;padding:60px;color:var(--hs-muted);">
        <i class="fas fa-bell-slash" style="font-size:50px;opacity:.2;"></i>
        <p style="margin-top:16px;font-size:14px;">No alerts match the selected filters.</p>
        <a href="alerts.php" class="btn-hs btn-outline-hs btn-sm-hs" style="margin-top:10px;display:inline-flex;">Clear filters</a>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
