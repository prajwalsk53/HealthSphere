<?php
require_once __DIR__ . '/../config/config.php';
requireRole('government');
$user = getCurrentUser();
$uid  = $user['id'];
$notifCount = getUnreadCount($pdo, $uid);

// Pull real DB stats for live report cards
$totalPatients = $pdo->query("SELECT COUNT(*) FROM users WHERE role='patient'")->fetchColumn();
$totalAppts    = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$totalMeds     = $pdo->query("SELECT COUNT(*) FROM prescriptions WHERE is_active=1")->fetchColumn();
$criticalCases = $pdo->query("SELECT COUNT(*) FROM medical_records WHERE result_status='critical'")->fetchColumn();
$totalFoods    = $pdo->query("SELECT COUNT(*) FROM food_database")->fetchColumn();
$totalDiseases = $pdo->query("SELECT COUNT(*) FROM genetic_diseases")->fetchColumn();

$reports = [
    [
        'id'       => 'RPT-2025-Q3',
        'title'    => 'Q3 2025 National Health Summary',
        'type'     => 'quarterly',
        'status'   => 'published',
        'date'     => '2025-10-01',
        'author'   => 'William Jayson',
        'pages'    => 42,
        'size'     => '3.8 MB',
        'summary'  => 'Comprehensive quarterly review covering hypertension trends, diabetes prevalence, vaccination rates, and regional disparities across England and Wales.',
        'highlights'=> ['Hypertension cases up 34% in East Midlands','Diabetes under-40 cohort growing fastest','Vaccination booster at 78% — below 85% target','London active travel reducing CVD by 6%'],
        'icon'     => 'fas fa-file-chart-line',
        'color'    => '#1565C0',
        'bg'       => '#DBEAFE',
    ],
    [
        'id'       => 'RPT-2025-EM',
        'title'    => 'East Midlands Sodium Intervention Study',
        'type'     => 'regional',
        'status'   => 'published',
        'date'     => '2025-09-15',
        'author'   => 'Sarah Mitchell',
        'pages'    => 28,
        'size'     => '2.1 MB',
        'summary'  => 'In-depth analysis of sodium consumption patterns in East Midlands and their correlation with hypertension hospitalisations. Includes proposed NHS campaign framework.',
        'highlights'=> ['Average salt intake: 11.2g/day (NHS limit: 6g)','4,821 new hypertension cases in 8 months','Campaign projected to reduce intake by 18%','Estimated NHS saving: £24M over 3 years'],
        'icon'     => 'fas fa-map-marker-alt',
        'color'    => '#DC2626',
        'bg'       => '#FEE2E2',
    ],
    [
        'id'       => 'RPT-2025-MH',
        'title'    => 'Mental Health Capacity & Demand Analysis',
        'type'     => 'thematic',
        'status'   => 'under_review',
        'date'     => '2025-10-20',
        'author'   => 'Dr. Priya Sharma',
        'pages'    => 36,
        'size'     => '2.9 MB',
        'summary'  => 'National review of CAMHS and adult mental health service capacity against rising demand. Identifies structural gaps and makes workforce recommendations.',
        'highlights'=> ['Referrals up 12% — capacity up only 4%','18+ week average waiting time','400 additional counsellors needed','Digital NHS app services as interim measure'],
        'icon'     => 'fas fa-brain',
        'color'    => '#7C3AED',
        'bg'       => '#EDE9FE',
    ],
    [
        'id'       => 'RPT-2025-VACC',
        'title'    => 'Winter Vaccination Campaign Assessment',
        'type'     => 'campaign',
        'status'   => 'draft',
        'date'     => '2025-10-28',
        'author'   => 'Mark Henderson',
        'pages'    => 18,
        'size'     => '1.4 MB',
        'summary'  => 'Assessment of 2025 winter booster campaign effectiveness and recommendations for closing the gap in under-40 uptake before the winter respiratory season.',
        'highlights'=> ['78% national uptake vs 85% target','Under-40 uptake at only 54%','Mobile unit deployment recommended','GP text-reminder pilot showed +12% conversion'],
        'icon'     => 'fas fa-syringe',
        'color'    => '#16A34A',
        'bg'       => '#DCFCE7',
    ],
    [
        'id'       => 'RPT-2025-OB',
        'title'    => 'Childhood Obesity Prevention Programme — 2025',
        'type'     => 'annual',
        'status'   => 'published',
        'date'     => '2025-07-12',
        'author'   => 'Claire Watson',
        'pages'    => 54,
        'size'     => '5.2 MB',
        'summary'  => 'Annual review of childhood obesity rates and the effectiveness of school nutrition programmes, sugar taxes, and NHS healthy eating campaigns.',
        'highlights'=> ['Childhood obesity plateaued at 22.1%','School meal reform reduced processed food by 31%','Sugar tax contributes £1.2B annually to NHS','Scotland model reducing rates by 8% — replication recommended'],
        'icon'     => 'fas fa-child',
        'color'    => '#D97706',
        'bg'       => '#FEF3C7',
    ],
    [
        'id'       => 'RPT-2024-Q4',
        'title'    => 'Q4 2024 National Health Summary',
        'type'     => 'quarterly',
        'status'   => 'archived',
        'date'     => '2025-01-10',
        'author'   => 'William Jayson',
        'pages'    => 40,
        'size'     => '3.5 MB',
        'summary'  => 'Q4 2024 comprehensive health review — baseline data against which Q1-Q3 2025 trends are measured.',
        'highlights'=> ['Hypertension baseline: 142k cases','Diabetes: 98k cases','Obesity: 78k cases','Vaccination rate: 74%'],
        'icon'     => 'fas fa-archive',
        'color'    => '#5E7A99',
        'bg'       => '#F1F5F9',
    ],
];

$statusMeta = [
    'published'    => ['#16A34A','#DCFCE7','Published'],
    'under_review' => ['#D97706','#FEF3C7','Under Review'],
    'draft'        => ['#0891B2','#CFFAFE','Draft'],
    'archived'     => ['#5E7A99','#F1F5F9','Archived'],
];
$typeMeta = [
    'quarterly' => 'Quarterly Report',
    'regional'  => 'Regional Study',
    'thematic'  => 'Thematic Report',
    'campaign'  => 'Campaign Review',
    'annual'    => 'Annual Report',
];

$filterType = $_GET['type'] ?? 'all';
$filtered = $filterType === 'all' ? $reports : array_filter($reports, fn($r) => $r['type'] === $filterType || $r['status'] === $filterType);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Reports — HealthSphere Gov</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">

  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-file-alt" style="color:var(--hs-blue);"></i> Policy Reports &amp; Publications</div>
      <div class="page-subtitle">DHSC internal reports &middot; Anonymised HealthSphere data &middot; <?= date('d M Y') ?></div>
    </div>
    <div class="topbar-actions">
      <div class="input-icon-wrap" style="width:240px;">
        <i class="fas fa-search"></i>
        <input type="text" id="reportSearch" placeholder="Search reports..." class="form-control" style="font-size:13px;">
      </div>
      <button class="btn-hs btn-primary-hs btn-sm-hs"><i class="fas fa-plus"></i> New Report</button>
    </div>
  </div>

  <div class="hs-content">

    <!-- Live data summary -->
    <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:22px;">
      <?php
      $liveStats = [
          ['Registered Patients',  $totalPatients,   'fa-users',         'stat-blue'],
          ['Total Appointments',   $totalAppts,      'fa-calendar-check','stat-teal'],
          ['Active Prescriptions', $totalMeds,       'fa-pills',         'stat-green'],
          ['Critical Cases',       $criticalCases,   'fa-exclamation',   'stat-danger'],
          ['Food Items (DB)',      $totalFoods,      'fa-drumstick-bite','stat-warning'],
          ['Genetic Diseases (DB)',$totalDiseases,   'fa-dna',           'stat-purple'],
      ];
      foreach ($liveStats as [$label, $val, $ico, $cls]):
      ?>
      <div class="stat-card <?= $cls ?>" style="padding:14px;">
        <div class="stat-icon" style="width:38px;height:38px;font-size:16px;"><i class="fas <?= $ico ?>"></i></div>
        <div class="stat-info">
          <div class="stat-label" style="font-size:10px;"><?= $label ?></div>
          <div class="stat-value" style="font-size:20px;" data-count="<?= $val ?>"><?= number_format($val) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Filter tabs -->
    <div style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap;">
      <?php
      $tabs = ['all'=>'All Reports','published'=>'Published','under_review'=>'Under Review','draft'=>'Draft','archived'=>'Archived'];
      foreach ($tabs as $key => $label):
        $active = $filterType === $key;
      ?>
      <a href="?type=<?= $key ?>"
         style="padding:7px 16px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;border:1.5px solid <?= $active?'var(--hs-blue)':'var(--hs-border)' ?>;background:<?= $active?'var(--hs-blue)':'#fff' ?>;color:<?= $active?'#fff':'var(--hs-muted)' ?>;">
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Reports grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(380px,1fr));gap:18px;" id="reportGrid">
      <?php foreach ($filtered as $report):
        [$sc, $sbg, $slbl] = $statusMeta[$report['status']];
      ?>
      <div class="hs-card report-card" style="border-top:4px solid <?= $report['color'] ?>;">
        <div class="hs-card-body">

          <!-- Header row -->
          <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:14px;">
            <div style="width:48px;height:48px;border-radius:12px;background:<?= $report['bg'] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:20px;color:<?= $report['color'] ?>;">
              <i class="<?= $report['icon'] ?>"></i>
            </div>
            <div style="flex:1;">
              <div style="display:flex;gap:8px;margin-bottom:6px;flex-wrap:wrap;">
                <span style="background:<?= $sbg ?>;color:<?= $sc ?>;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;"><?= $slbl ?></span>
                <span style="background:var(--hs-off-white);color:var(--hs-muted);padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;"><?= $typeMeta[$report['type']] ?></span>
              </div>
              <h5 style="margin:0;font-size:14px;font-weight:800;color:var(--hs-navy);line-height:1.4;" class="report-title"><?= e($report['title']) ?></h5>
            </div>
          </div>

          <!-- Meta -->
          <div style="display:flex;gap:14px;font-size:11px;color:var(--hs-muted);margin-bottom:10px;flex-wrap:wrap;">
            <span><i class="fas fa-hashtag"></i> <?= e($report['id']) ?></span>
            <span><i class="fas fa-user"></i> <?= e($report['author']) ?></span>
            <span><i class="fas fa-calendar"></i> <?= formatDate($report['date']) ?></span>
            <span><i class="fas fa-file-pdf"></i> <?= $report['pages'] ?> pages &middot; <?= $report['size'] ?></span>
          </div>

          <!-- Summary -->
          <p style="font-size:13px;color:var(--hs-muted);line-height:1.6;margin-bottom:12px;"><?= e($report['summary']) ?></p>

          <!-- Highlights -->
          <div style="background:var(--hs-off-white);border-radius:8px;padding:10px 14px;margin-bottom:14px;">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--hs-muted);margin-bottom:6px;">Key Highlights</div>
            <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:4px;">
              <?php foreach ($report['highlights'] as $h): ?>
              <li style="font-size:12.5px;color:var(--hs-text);display:flex;align-items:flex-start;gap:6px;">
                <span style="color:<?= $report['color'] ?>;margin-top:1px;flex-shrink:0;">&#10003;</span>
                <?= e($h) ?>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>

          <!-- Actions -->
          <div style="display:flex;gap:8px;">
            <?php if ($report['status'] === 'published' || $report['status'] === 'archived'): ?>
            <button class="btn-hs btn-primary-hs btn-sm-hs" style="flex:1;justify-content:center;">
              <i class="fas fa-download"></i> Download PDF
            </button>
            <button class="btn-hs btn-outline-hs btn-sm-hs">
              <i class="fas fa-share-alt"></i> Share
            </button>
            <?php elseif ($report['status'] === 'under_review'): ?>
            <button class="btn-hs btn-outline-hs btn-sm-hs" style="flex:1;justify-content:center;">
              <i class="fas fa-eye"></i> View Draft
            </button>
            <button class="btn-hs btn-success-hs btn-sm-hs">
              <i class="fas fa-check"></i> Approve
            </button>
            <?php else: ?>
            <button class="btn-hs btn-outline-hs btn-sm-hs" style="flex:1;justify-content:center;">
              <i class="fas fa-edit"></i> Continue Editing
            </button>
            <?php endif; ?>
          </div>

        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Generate Report CTA -->
    <div class="hs-card" style="margin-top:20px;">
      <div class="hs-card-body" style="display:flex;align-items:center;gap:20px;background:linear-gradient(135deg,#0A1F44 0%,#1565C0 100%);border-radius:11px;color:#fff;">
        <div style="font-size:48px;">📊</div>
        <div style="flex:1;">
          <h4 style="margin:0 0 6px;font-size:18px;font-weight:800;">Generate Custom Report</h4>
          <p style="margin:0;font-size:13px;opacity:.8;">Select regions, conditions, date ranges and metrics to compile a bespoke anonymised health report from HealthSphere data.</p>
        </div>
        <div style="display:flex;gap:10px;flex-shrink:0;">
          <button class="btn-hs" style="background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.3);padding:10px 20px;">
            <i class="fas fa-cog"></i> Configure
          </button>
          <button class="btn-hs" style="background:#fff;color:var(--hs-blue);padding:10px 20px;font-weight:700;">
            <i class="fas fa-chart-bar"></i> Generate Now
          </button>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
document.getElementById('reportSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.report-card').forEach(card => {
    card.style.display = card.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});
</script>
</body>
</html>
