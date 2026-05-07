<?php
require_once __DIR__ . '/../config/config.php';
requireRole('government');
$user = getCurrentUser();
$uid  = $user['id'];
$notifCount = getUnreadCount($pdo, $uid);

$findings = [
    ['High Sodium Diet Linked to Hypertension Spike',
     'East Midlands salt intake 34% above NHS recommended levels. Direct correlation found with hypertension hospitalisations in Q3 2025.',
     'critical','Draft NHS reduced-salt awareness campaign targeting East Midlands supermarkets and schools.'],
    ['Type 2 Diabetes Rising in Under-40s',
     'North West seeing 22% increase, notably in 25–40 age group. Sedentary lifestyle and processed food consumption identified as key drivers.',
     'critical','Commission workplace wellness programmes and subsidised gym access for low-income households.'],
    ['Obesity Trend Stabilising in London',
     'London showing only +2% growth vs national average of +18%. Cycle superhighway and active travel policy cited as contributing factors.',
     'healthy','Scale London active travel model to North East and West Midlands — potential 8% reduction projected.'],
    ['Mental Health Demand Exceeding Capacity',
     'Yorkshire mental health referrals up 12% but NHS capacity only increased 4%. Average waiting time now 18+ weeks.',
     'attention','Emergency funding review for CAMHS. Recruit 400 additional counsellors by Q1 2026.'],
    ['Vaccination Coverage Below Target',
     'Covid booster at 78% nationally vs 85% target. Over-65s: 91%. Under-40s: 54%. Gap widening each quarter.',
     'attention','Targeted outreach programme for under-40 demographic via GP surgeries and mobile vaccination units.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Trend Analysis — HealthSphere Gov</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">

  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-chart-line" style="color:var(--hs-blue);"></i> National Trend Analysis</div>
      <div class="page-subtitle">Population health trends &middot; England &amp; Wales &middot; 2025</div>
    </div>
    <div class="topbar-actions">
      <div style="display:flex;gap:4px;background:var(--hs-off-white);border-radius:8px;padding:4px;border:1px solid var(--hs-border);">
        <?php foreach (['3M','6M','1Y','3Y'] as $v): ?>
        <button style="padding:6px 14px;border-radius:6px;border:none;font-size:12px;font-weight:600;cursor:pointer;background:<?= $v==='1Y'?'var(--hs-blue)':'transparent' ?>;color:<?= $v==='1Y'?'#fff':'var(--hs-muted)' ?>;"><?= $v ?></button>
        <?php endforeach; ?>
      </div>
      <button class="btn-hs btn-outline-hs btn-sm-hs"><i class="fas fa-download"></i> Export CSV</button>
    </div>
  </div>

  <div class="hs-content">

    <!-- KPIs -->
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:22px;">
      <div class="stat-card stat-danger">
        <div class="stat-icon"><i class="fas fa-heart"></i></div>
        <div class="stat-info"><div class="stat-label">Hypertension</div><div class="stat-value">192k</div><div class="stat-sub"><span class="text-danger">↑ 34%</span> vs last year</div></div>
      </div>
      <div class="stat-card stat-warning">
        <div class="stat-icon"><i class="fas fa-tint"></i></div>
        <div class="stat-info"><div class="stat-label">Type 2 Diabetes</div><div class="stat-value">122k</div><div class="stat-sub"><span class="text-danger">↑ 22%</span> vs last year</div></div>
      </div>
      <div class="stat-card stat-blue">
        <div class="stat-icon"><i class="fas fa-weight"></i></div>
        <div class="stat-info"><div class="stat-label">Obesity</div><div class="stat-value">95k</div><div class="stat-sub"><span class="text-danger">↑ 18%</span> vs last year</div></div>
      </div>
      <div class="stat-card stat-purple">
        <div class="stat-icon"><i class="fas fa-brain"></i></div>
        <div class="stat-info"><div class="stat-label">Mental Health</div><div class="stat-value">84k</div><div class="stat-sub"><span class="text-danger">↑ 12%</span> vs last year</div></div>
      </div>
      <div class="stat-card stat-green">
        <div class="stat-icon"><i class="fas fa-syringe"></i></div>
        <div class="stat-info"><div class="stat-label">Vaccination Rate</div><div class="stat-value">78%</div><div class="stat-sub"><span style="color:var(--hs-warning);">Target: 85%</span></div></div>
      </div>
    </div>

    <!-- Main trend chart -->
    <div class="hs-card" style="margin-bottom:20px;">
      <div class="hs-card-header">
        <span class="card-title"><i class="fas fa-chart-line"></i> National Disease Trends — Jan to Dec 2025 (thousands)</span>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <?php foreach (['Hypertension'=>'#1565C0','Type 2 Diabetes'=>'#DC2626','Obesity'=>'#D97706','Mental Health'=>'#7C3AED','Respiratory'=>'#0891B2'] as $label => $color): ?>
          <span style="display:flex;align-items:center;gap:5px;font-size:11px;font-weight:600;">
            <span style="width:12px;height:3px;background:<?= $color ?>;border-radius:2px;display:inline-block;"></span><?= $label ?>
          </span>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="hs-card-body" style="height:300px;"><canvas id="mainTrendChart"></canvas></div>
    </div>

    <!-- Two sub-charts -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:20px;">
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-users"></i> Age Group Breakdown</span></div>
        <div class="hs-card-body" style="height:220px;"><canvas id="ageChart"></canvas></div>
      </div>
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-hospital"></i> Hospital Admissions</span></div>
        <div class="hs-card-body" style="height:220px;"><canvas id="admissionChart"></canvas></div>
      </div>
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-syringe"></i> Vaccination Coverage</span></div>
        <div class="hs-card-body" style="height:220px;"><canvas id="vaccChart"></canvas></div>
      </div>
    </div>

    <!-- Comparative year-on-year -->
    <div class="hs-card" style="margin-bottom:20px;">
      <div class="hs-card-header">
        <span class="card-title"><i class="fas fa-balance-scale"></i> Year-on-Year Comparison (2023 vs 2024 vs 2025)</span>
      </div>
      <div class="hs-card-body" style="height:260px;"><canvas id="yoyChart"></canvas></div>
    </div>

    <!-- Key findings -->
    <div class="hs-card">
      <div class="hs-card-header">
        <span class="card-title"><i class="fas fa-lightbulb"></i> Key Findings &amp; Recommendations</span>
        <span class="badge bg-danger">2 critical</span>
      </div>
      <div class="hs-card-body p-0">
        <?php
        $statusMeta = [
            'critical' => ['#DC2626','#FEE2E2','Requires Action'],
            'attention'=> ['#D97706','#FEF3C7','Needs Attention'],
            'healthy'  => ['#16A34A','#DCFCE7','Positive Trend'],
        ];
        foreach ($findings as [$title, $detail, $status, $rec]):
            [$c, $bg, $lbl] = $statusMeta[$status];
        ?>
        <div style="padding:18px 22px;border-bottom:1px solid var(--hs-border);display:flex;gap:16px;align-items:flex-start;">
          <span style="background:<?= $bg ?>;color:<?= $c ?>;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;margin-top:2px;">
            <?= $lbl ?>
          </span>
          <div style="flex:1;">
            <div style="font-weight:700;font-size:14px;color:var(--hs-navy);margin-bottom:5px;"><?= e($title) ?></div>
            <div style="font-size:13px;color:var(--hs-muted);margin-bottom:6px;line-height:1.6;"><?= e($detail) ?></div>
            <div style="font-size:12px;color:var(--hs-blue);display:flex;align-items:center;gap:6px;">
              <i class="fas fa-arrow-right"></i>
              <strong>Recommended:</strong> <?= e($rec) ?>
            </div>
          </div>
          <button class="btn-hs btn-outline-hs btn-sm-hs" style="flex-shrink:0;margin-top:2px;">
            <i class="fas fa-file-alt"></i> Draft Policy
          </button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/main.js"></script>
<script>
const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

new Chart(document.getElementById('mainTrendChart'), {
  type: 'line',
  data: {
    labels: MONTHS,
    datasets: [
      {label:'Hypertension', data:[120,135,142,138,155,148,162,170,155,168,180,192], borderColor:'#1565C0', backgroundColor:'rgba(21,101,192,.07)', tension:.4, fill:true, pointRadius:3, pointBackgroundColor:'#1565C0'},
      {label:'Type 2 Diabetes',data:[80,88,92,85,95,100,98,105,112,108,115,122],    borderColor:'#DC2626', backgroundColor:'rgba(220,38,38,.06)',    tension:.4, fill:true, pointRadius:3, pointBackgroundColor:'#DC2626'},
      {label:'Obesity',       data:[65,70,68,72,75,78,80,82,85,88,90,95],           borderColor:'#D97706', backgroundColor:'rgba(217,119,6,.06)',    tension:.4, fill:true, pointRadius:3, pointBackgroundColor:'#D97706'},
      {label:'Mental Health', data:[50,55,60,58,65,68,70,75,72,78,80,84],           borderColor:'#7C3AED', backgroundColor:'rgba(124,58,237,.05)',   tension:.4, fill:true, pointRadius:3, pointBackgroundColor:'#7C3AED'},
      {label:'Respiratory',   data:[40,55,48,42,38,32,30,28,35,42,52,58],           borderColor:'#0891B2', backgroundColor:'rgba(8,145,178,.05)',    tension:.4, fill:true, pointRadius:3, pointBackgroundColor:'#0891B2'},
    ]
  },
  options:{
    responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{display:false}, tooltip:{mode:'index',intersect:false} },
    scales:{ x:{grid:{display:false}}, y:{grid:{color:'#EFF6FF'}, ticks:{callback:v=>v+'k'}} }
  }
});

new Chart(document.getElementById('ageChart'), {
  type:'bar',
  data:{
    labels:['0-17','18-29','30-44','45-59','60-74','75+'],
    datasets:[
      {label:'Hypertension', data:[5,12,28,55,82,95], backgroundColor:'#1565C0', borderRadius:4},
      {label:'Diabetes',     data:[2,8,22,48,68,72],  backgroundColor:'#DC2626', borderRadius:4},
    ]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top',labels:{font:{size:10},boxWidth:10}}},scales:{x:{grid:{display:false}},y:{grid:{color:'#EFF6FF'}}}}
});

new Chart(document.getElementById('admissionChart'), {
  type:'line',
  data:{
    labels:MONTHS,
    datasets:[
      {label:'Emergency', data:[8200,8800,9100,8600,9400,9800,10200,10800,9900,10500,11200,12000], borderColor:'#DC2626', tension:.4, fill:false, pointRadius:2},
      {label:'Planned',   data:[6200,6500,6800,6600,7000,7200,7100,7500,7300,7600,7900,8200],     borderColor:'#1565C0', tension:.4, fill:false, pointRadius:2},
    ]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top',labels:{font:{size:10},boxWidth:10}}},scales:{x:{grid:{display:false},ticks:{font:{size:9}}},y:{grid:{color:'#EFF6FF'},ticks:{callback:v=>v>=1000?(v/1000).toFixed(0)+'k':v}}}}
});

new Chart(document.getElementById('vaccChart'), {
  type:'doughnut',
  data:{
    labels:['Vaccinated','Partially','Unvaccinated'],
    datasets:[{data:[78,8,14], backgroundColor:['#16A34A','#D97706','#DC2626'], borderWidth:2, borderColor:'#fff'}]
  },
  options:{responsive:true,maintainAspectRatio:false,cutout:'65%',plugins:{legend:{position:'bottom',labels:{font:{size:11},boxWidth:12}}}}
});

new Chart(document.getElementById('yoyChart'), {
  type:'bar',
  data:{
    labels:['Hypertension','Diabetes','Obesity','Mental Health','Respiratory','Cardiovascular'],
    datasets:[
      {label:'2023',data:[142,98,78,62,65,55],backgroundColor:'rgba(21,101,192,.4)',borderRadius:4},
      {label:'2024',data:[168,112,88,74,58,62],backgroundColor:'rgba(21,101,192,.7)',borderRadius:4},
      {label:'2025',data:[192,122,95,84,58,70],backgroundColor:'#1565C0',borderRadius:4},
    ]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}},scales:{x:{grid:{display:false}},y:{grid:{color:'#EFF6FF'},ticks:{callback:v=>v+'k'}}}}
});
</script>
</body>
</html>
