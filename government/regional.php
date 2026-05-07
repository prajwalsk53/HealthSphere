<?php
require_once __DIR__ . '/../config/config.php';
requireRole('government');
$user = getCurrentUser();
$uid  = $user['id'];
$notifCount = getUnreadCount($pdo, $uid);

$regions = [
    ['name'=>'East Midlands',    'risk'=>'critical', 'primary'=>'Hypertension',    'cases'=>4821,'change'=>34, 'pop'=>4900000,'hosp'=>12,'color'=>'#DC2626','bg'=>'#FEE2E2'],
    ['name'=>'North West',       'risk'=>'critical', 'primary'=>'Type 2 Diabetes', 'cases'=>6103,'change'=>22, 'pop'=>7400000,'hosp'=>18,'color'=>'#DC2626','bg'=>'#FEE2E2'],
    ['name'=>'North East',       'risk'=>'attention','primary'=>'Obesity',          'cases'=>2987,'change'=>18, 'pop'=>2700000,'hosp'=>8, 'color'=>'#D97706','bg'=>'#FEF3C7'],
    ['name'=>'Yorkshire',        'risk'=>'attention','primary'=>'Mental Health',    'cases'=>3412,'change'=>12, 'pop'=>5500000,'hosp'=>14,'color'=>'#D97706','bg'=>'#FEF3C7'],
    ['name'=>'South East',       'risk'=>'attention','primary'=>'Respiratory',      'cases'=>2190,'change'=>10, 'pop'=>9200000,'hosp'=>22,'color'=>'#D97706','bg'=>'#FEF3C7'],
    ['name'=>'West Midlands',    'risk'=>'attention','primary'=>'Cardiovascular',   'cases'=>3750,'change'=>8,  'pop'=>5900000,'hosp'=>15,'color'=>'#D97706','bg'=>'#FEF3C7'],
    ['name'=>'London',           'risk'=>'healthy',  'primary'=>'Hypertension',     'cases'=>5210,'change'=>2,  'pop'=>9000000,'hosp'=>30,'color'=>'#16A34A','bg'=>'#DCFCE7'],
    ['name'=>'South West',       'risk'=>'healthy',  'primary'=>'Diabetes',         'cases'=>1820,'change'=>-3, 'pop'=>5700000,'hosp'=>11,'color'=>'#16A34A','bg'=>'#DCFCE7'],
    ['name'=>'East of England',  'risk'=>'healthy',  'primary'=>'Obesity',          'cases'=>2100,'change'=>1,  'pop'=>6300000,'hosp'=>13,'color'=>'#16A34A','bg'=>'#DCFCE7'],
    ['name'=>'Wales',            'risk'=>'healthy',  'primary'=>'Diabetes',         'cases'=>1450,'change'=>-5, 'pop'=>3200000,'hosp'=>9, 'color'=>'#16A34A','bg'=>'#DCFCE7'],
    ['name'=>'Scotland',         'risk'=>'healthy',  'primary'=>'Obesity',          'cases'=>2340,'change'=>-8, 'pop'=>5500000,'hosp'=>14,'color'=>'#16A34A','bg'=>'#DCFCE7'],
    ['name'=>'Northern Ireland', 'risk'=>'healthy',  'primary'=>'Hypertension',     'cases'=>890, 'change'=>-2, 'pop'=>1900000,'hosp'=>6, 'color'=>'#16A34A','bg'=>'#DCFCE7'],
];

$riskMeta = [
    'critical'  => ['Requires Action',  '#DC2626', '#FEE2E2'],
    'attention' => ['Needs Attention',  '#D97706', '#FEF3C7'],
    'healthy'   => ['Healthy',          '#16A34A', '#DCFCE7'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Regional Map — HealthSphere Gov</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">

  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-map-marked-alt" style="color:var(--hs-blue);"></i> Regional Health Risk Map</div>
      <div class="page-subtitle">Anonymised population-level data &middot; UK Regions &middot; <?= date('d M Y') ?></div>
    </div>
    <div class="topbar-actions">
      <select class="form-select" style="width:180px;font-size:13px;">
        <option>All Conditions</option>
        <option>Hypertension</option><option>Type 2 Diabetes</option>
        <option>Obesity</option><option>Mental Health</option>
      </select>
      <button class="btn-hs btn-outline-hs btn-sm-hs"><i class="fas fa-download"></i> Export</button>
    </div>
  </div>

  <div class="hs-content">

    <!-- KPIs -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px;">
      <div class="stat-card stat-danger">
        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-info"><div class="stat-label">Critical Regions</div><div class="stat-value">2</div><div class="stat-sub">Immediate action required</div></div>
      </div>
      <div class="stat-card stat-warning">
        <div class="stat-icon"><i class="fas fa-eye"></i></div>
        <div class="stat-info"><div class="stat-label">Under Watch</div><div class="stat-value">4</div><div class="stat-sub">Needs monitoring</div></div>
      </div>
      <div class="stat-card stat-green">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info"><div class="stat-label">Healthy Regions</div><div class="stat-value">6</div><div class="stat-sub">Within safe thresholds</div></div>
      </div>
      <div class="stat-card stat-blue">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-info"><div class="stat-label">Population Covered</div><div class="stat-value">66.4M</div><div class="stat-sub">Across 12 regions</div></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:340px 1fr;gap:20px;">

      <!-- SVG Map -->
      <div class="hs-card">
        <div class="hs-card-header">
          <span class="card-title"><i class="fas fa-map"></i> UK Heat Map</span>
          <div style="display:flex;gap:10px;font-size:11px;font-weight:600;">
            <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;background:#DC2626;border-radius:2px;display:inline-block;"></span>Critical</span>
            <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;background:#D97706;border-radius:2px;display:inline-block;"></span>Attention</span>
            <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;background:#16A34A;border-radius:2px;display:inline-block;"></span>Healthy</span>
          </div>
        </div>
        <div class="hs-card-body" style="display:flex;justify-content:center;background:#0A1929;border-radius:0 0 12px 12px;">
          <svg viewBox="0 0 300 510" style="width:100%;max-height:480px;" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Scotland -->
            <path d="M82,16 L175,14 L194,52 L184,95 L158,115 L128,107 L100,122 L82,112 L70,82 L78,46 Z"
              fill="#16A34A" fill-opacity=".75" stroke="#fff" stroke-width="1.5"/>
            <text x="132" y="70" text-anchor="middle" fill="#fff" font-size="10" font-weight="700" font-family="Inter">Scotland</text>
            <text x="132" y="83" text-anchor="middle" fill="rgba(255,255,255,.8)" font-size="7.5">−8% Healthy</text>

            <!-- Northern Ireland -->
            <path d="M22,106 L68,97 L72,126 L52,138 L22,128 Z"
              fill="#16A34A" fill-opacity=".75" stroke="#fff" stroke-width="1.5"/>
            <text x="47" y="120" text-anchor="middle" fill="#fff" font-size="7" font-weight="700" font-family="Inter">N. Ireland</text>

            <!-- North East -->
            <path d="M148,124 L210,114 L218,152 L200,170 L158,165 L143,147 Z"
              fill="#D97706" fill-opacity=".85" stroke="#fff" stroke-width="1.5"/>
            <text x="180" y="145" text-anchor="middle" fill="#fff" font-size="8.5" font-weight="700" font-family="Inter">North East</text>
            <text x="180" y="157" text-anchor="middle" fill="rgba(255,255,255,.9)" font-size="7.5">+18% ↑</text>

            <!-- North West -->
            <path d="M88,142 L146,146 L158,164 L142,190 L100,194 L78,180 L76,160 Z"
              fill="#DC2626" fill-opacity=".88" stroke="#fff" stroke-width="1.5"/>
            <text x="117" y="170" text-anchor="middle" fill="#fff" font-size="8.5" font-weight="800" font-family="Inter">North West</text>
            <text x="117" y="182" text-anchor="middle" fill="rgba(255,255,255,.95)" font-size="7.5">+22% ⚠ CRITICAL</text>

            <!-- Yorkshire -->
            <path d="M158,164 L218,154 L226,190 L208,208 L165,204 L148,190 Z"
              fill="#D97706" fill-opacity=".82" stroke="#fff" stroke-width="1.5"/>
            <text x="188" y="185" text-anchor="middle" fill="#fff" font-size="8.5" font-weight="700" font-family="Inter">Yorkshire</text>
            <text x="188" y="197" text-anchor="middle" fill="rgba(255,255,255,.9)" font-size="7.5">+12% ↑</text>

            <!-- Wales -->
            <path d="M50,212 L100,204 L108,234 L94,258 L58,260 L40,244 L44,224 Z"
              fill="#16A34A" fill-opacity=".75" stroke="#fff" stroke-width="1.5"/>
            <text x="74" y="236" text-anchor="middle" fill="#fff" font-size="9" font-weight="700" font-family="Inter">Wales</text>
            <text x="74" y="248" text-anchor="middle" fill="rgba(255,255,255,.85)" font-size="7.5">−5% Healthy</text>

            <!-- West Midlands -->
            <path d="M102,204 L160,207 L168,240 L148,257 L106,254 L94,236 Z"
              fill="#D97706" fill-opacity=".82" stroke="#fff" stroke-width="1.5"/>
            <text x="132" y="234" text-anchor="middle" fill="#fff" font-size="8.5" font-weight="700" font-family="Inter">W. Midlands</text>
            <text x="132" y="246" text-anchor="middle" fill="rgba(255,255,255,.9)" font-size="7.5">+8% ↑</text>

            <!-- East Midlands — CRITICAL: thicker border + glow -->
            <path d="M163,207 L230,198 L238,234 L222,257 L168,255 L158,240 Z"
              fill="#DC2626" fill-opacity=".92" stroke="#FF6B6B" stroke-width="2.5"/>
            <text x="196" y="226" text-anchor="middle" fill="#fff" font-size="8.5" font-weight="800" font-family="Inter">East Midlands</text>
            <text x="196" y="238" text-anchor="middle" fill="rgba(255,255,255,.95)" font-size="7.5">+34% ⚠ CRITICAL</text>

            <!-- East of England -->
            <path d="M232,200 L278,196 L286,232 L270,255 L238,252 Z"
              fill="#16A34A" fill-opacity=".75" stroke="#fff" stroke-width="1.5"/>
            <text x="258" y="228" text-anchor="middle" fill="#fff" font-size="8" font-weight="700" font-family="Inter">East England</text>
            <text x="258" y="240" text-anchor="middle" fill="rgba(255,255,255,.8)" font-size="7">+1%</text>

            <!-- London -->
            <path d="M168,260 L232,257 L238,284 L220,300 L172,298 L160,282 Z"
              fill="#16A34A" fill-opacity=".75" stroke="#fff" stroke-width="1.5"/>
            <text x="198" y="280" text-anchor="middle" fill="#fff" font-size="10" font-weight="700" font-family="Inter">London</text>
            <text x="198" y="292" text-anchor="middle" fill="rgba(255,255,255,.85)" font-size="7.5">+2% Stable</text>

            <!-- South East -->
            <path d="M163,300 L248,294 L256,330 L234,352 L188,354 L160,338 Z"
              fill="#D97706" fill-opacity=".75" stroke="#fff" stroke-width="1.5"/>
            <text x="208" y="325" text-anchor="middle" fill="#fff" font-size="8.5" font-weight="700" font-family="Inter">South East</text>
            <text x="208" y="337" text-anchor="middle" fill="rgba(255,255,255,.9)" font-size="7.5">+10% ↑</text>

            <!-- South West -->
            <path d="M60,308 L160,302 L160,340 L138,362 L94,370 L56,354 L46,332 Z"
              fill="#16A34A" fill-opacity=".75" stroke="#fff" stroke-width="1.5"/>
            <text x="104" y="338" text-anchor="middle" fill="#fff" font-size="8.5" font-weight="700" font-family="Inter">South West</text>
            <text x="104" y="350" text-anchor="middle" fill="rgba(255,255,255,.85)" font-size="7.5">−3% Healthy</text>

            <!-- Compass rose -->
            <text x="268" y="450" text-anchor="middle" fill="rgba(255,255,255,.3)" font-size="11" font-family="Inter">N</text>
            <line x1="268" y1="455" x2="268" y2="470" stroke="rgba(255,255,255,.2)" stroke-width="1"/>
          </svg>
        </div>
      </div>

      <!-- Table -->
      <div class="hs-card">
        <div class="hs-card-header">
          <span class="card-title"><i class="fas fa-table"></i> Regional Health Data</span>
          <input type="text" id="regionSearch" placeholder="Search region..." class="form-control" style="width:180px;font-size:12px;">
        </div>
        <div class="hs-card-body p-0">
          <table class="hs-table" id="regionTable">
            <thead>
              <tr>
                <th>Region</th><th>Risk Level</th><th>Primary Condition</th>
                <th>Cases</th><th>Population</th><th>Hospitals</th><th>Change</th><th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($regions as $r):
                [$rLabel, $rColor, $rBg] = $riskMeta[$r['risk']];
                $changeColor = $r['change'] > 15 ? '#DC2626' : ($r['change'] > 4 ? '#D97706' : '#16A34A');
                $arrow = $r['change'] > 0 ? '↑' : '↓';
              ?>
              <tr class="region-row">
                <td>
                  <div style="display:flex;align-items:center;gap:8px;">
                    <span style="width:10px;height:10px;border-radius:50%;background:<?= $rColor ?>;flex-shrink:0;display:inline-block;"></span>
                    <strong><?= e($r['name']) ?></strong>
                  </div>
                </td>
                <td>
                  <span style="background:<?= $rBg ?>;color:<?= $rColor ?>;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">
                    <?= $rLabel ?>
                  </span>
                </td>
                <td><?= e($r['primary']) ?></td>
                <td style="font-weight:700;color:var(--hs-navy);"><?= number_format($r['cases']) ?></td>
                <td style="font-size:12px;color:var(--hs-muted);"><?= number_format($r['pop'] / 1000000, 1) ?>M</td>
                <td style="text-align:center;"><?= $r['hosp'] ?></td>
                <td>
                  <span style="color:<?= $changeColor ?>;font-weight:800;font-size:14px;">
                    <?= $arrow ?> <?= abs($r['change']) ?>%
                  </span>
                </td>
                <td>
                  <?php if ($r['risk'] === 'critical'): ?>
                    <button class="btn-hs btn-danger-hs btn-sm-hs"><i class="fas fa-file-alt"></i> Brief</button>
                  <?php elseif ($r['risk'] === 'attention'): ?>
                    <button class="btn-hs btn-outline-hs btn-sm-hs"><i class="fas fa-eye"></i> Monitor</button>
                  <?php else: ?>
                    <span style="color:#16A34A;font-weight:700;font-size:13px;">&#10003; OK</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /grid -->

    <!-- Bottom charts -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-chart-bar"></i> Cases by Region</span></div>
        <div class="hs-card-body" style="height:250px;"><canvas id="regionalBarChart"></canvas></div>
      </div>
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-chart-pie"></i> Condition Distribution</span></div>
        <div class="hs-card-body" style="height:250px;"><canvas id="conditionPieChart"></canvas></div>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/main.js"></script>
<script>
document.getElementById('regionSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.region-row').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});

new Chart(document.getElementById('regionalBarChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($regions, 'name')) ?>,
    datasets: [{
      label: 'Cases',
      data: <?= json_encode(array_column($regions, 'cases')) ?>,
      backgroundColor: <?= json_encode(array_map(fn($r) => $r['color'] . 'BB', $regions)) ?>,
      borderRadius: 5,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 40 } },
      y: { grid: { color: '#EFF6FF' }, ticks: { callback: v => v >= 1000 ? (v/1000).toFixed(0)+'k' : v } }
    }
  }
});

new Chart(document.getElementById('conditionPieChart'), {
  type: 'doughnut',
  data: {
    labels: ['Hypertension','Type 2 Diabetes','Obesity','Mental Health','Respiratory','Cardiovascular'],
    datasets: [{
      data: [10921, 7553, 6140, 3412, 2190, 3750],
      backgroundColor: ['#1565C0','#DC2626','#D97706','#7C3AED','#0891B2','#16A34A'],
      borderWidth: 2, borderColor: '#fff'
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'right', labels: { boxWidth: 12, padding: 12, font: { size: 11 } } } }
  }
});
</script>
</body>
</html>
