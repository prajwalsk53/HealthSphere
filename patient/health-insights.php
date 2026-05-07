<?php
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
$user = getCurrentUser();
$uid  = $user['id'];

// Latest metrics
$latest = $pdo->prepare("SELECT * FROM health_metrics WHERE patient_id=? ORDER BY metric_date DESC, id DESC LIMIT 1");
$latest->execute([$uid]); $latest = $latest->fetch() ?: [];

// 7-day metrics
$weekly = $pdo->prepare("SELECT * FROM health_metrics WHERE patient_id=? ORDER BY metric_date DESC LIMIT 7");
$weekly->execute([$uid]); $weekly = $weekly->fetchAll();

// Exercise logs
$exercises = $pdo->prepare("SELECT * FROM exercise_logs WHERE patient_id=? ORDER BY log_date DESC LIMIT 5");
$exercises->execute([$uid]); $exercises = $exercises->fetchAll();

// Build chart data arrays
$labels = []; $hr = []; $sys = []; $dia = []; $steps = []; $sleep = [];
foreach (array_reverse($weekly) as $m) {
    $labels[] = date('D', strtotime($m['metric_date']));
    $hr[]     = (int)$m['heart_rate'];
    $sys[]    = (int)$m['blood_pressure_systolic'];
    $dia[]    = (int)$m['blood_pressure_diastolic'];
    $steps[]  = (int)$m['steps_count'];
    $sleep[]  = (float)$m['sleep_hours'];
}

$notifCount = getUnreadCount($pdo, $uid);
$msgCount   = getUnreadMessages($pdo, $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Health Insights — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  .hs-content { background: #0A1929; padding: 24px; min-height: calc(100vh - 64px); }
  .section-title { color: rgba(255,255,255,.6); font-size:11px; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; margin-bottom:12px; }
</style>
</head>
<body style="background:#0A1929;">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-heartbeat" style="color:var(--hs-blue);"></i> Health Insights</div>
      <div class="page-subtitle">Data from HealthSphere Band · <?= date('d M Y') ?></div>
    </div>
    <div class="topbar-actions">
      <div style="display:flex;gap:4px;background:rgba(255,255,255,.1);border-radius:8px;padding:4px;">
        <?php foreach (['Daily','Weekly','Monthly'] as $v): ?>
        <button style="padding:6px 14px;border-radius:6px;border:none;font-size:12px;font-weight:600;cursor:pointer;background:<?= $v==='Daily'?'var(--hs-blue)':'transparent' ?>;color:<?= $v==='Daily'?'#fff':'var(--hs-muted)' ?>;"><?= $v ?></button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="hs-content">

    <!-- Top row: 3 main insight cards -->
    <div class="section-title"><i class="fas fa-broadcast-tower"></i> Live Metrics</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">

      <!-- Nutrition -->
      <div class="insight-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
          <div>
            <div class="card-label"><i class="fas fa-utensils"></i> Nutrition</div>
            <div class="big-value">1,270<span class="big-unit">kcal</span></div>
            <div class="card-sub">1,882 kcal remaining</div>
          </div>
          <div class="progress-circle" data-pct="60" data-color="#1565C0" data-size="80" data-label="Quality"></div>
        </div>
        <div style="margin-top:16px;display:flex;gap:12px;">
          <span style="background:rgba(255,255,255,.1);padding:4px 10px;border-radius:6px;font-size:11px;">Protein: <?= round($latest['spo2'] ?? 528) ?>g</span>
          <span style="background:rgba(255,255,255,.1);padding:4px 10px;border-radius:6px;font-size:11px;">Carbs: 173g</span>
          <span style="background:rgba(255,255,255,.1);padding:4px 10px;border-radius:6px;font-size:11px;">Fat: 199g</span>
        </div>
        <div style="margin-top:16px;height:60px;"><canvas id="nutritionMiniChart"></canvas></div>
      </div>

      <!-- Exercise -->
      <div class="insight-card" style="background:linear-gradient(135deg,#0A2342 0%,#1B4332 100%);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
          <div>
            <div class="card-label"><i class="fas fa-running"></i> Exercise</div>
            <div class="big-value">950<span class="big-unit">kcal</span></div>
            <div class="card-sub">Burned today</div>
          </div>
          <div class="progress-circle" data-pct="81" data-color="#16A34A" data-size="80" data-label="Quality"></div>
        </div>
        <?php foreach ($exercises as $ex): ?>
        <div style="background:rgba(255,255,255,.07);border-radius:8px;padding:8px 12px;margin-top:8px;display:flex;align-items:center;gap:8px;font-size:12px;">
          <i class="fas fa-dumbbell" style="color:#22C55E;"></i>
          <span><?= e($ex['exercise_type']) ?></span>
          <span style="margin-left:auto;opacity:.7;"><?= $ex['duration_minutes'] ?>min · <?= round($ex['calories_burned']) ?>kcal</span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Sleep -->
      <div class="insight-card" style="background:linear-gradient(135deg,#1E1B4B 0%,#312E81 100%);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
          <div>
            <div class="card-label"><i class="fas fa-moon"></i> Sleep Analysis</div>
            <div class="big-value"><?= $latest['sleep_hours'] ?? 7.8 ?><span class="big-unit">h</span></div>
            <div class="card-sub">Sleep Duration</div>
          </div>
          <div class="progress-circle" data-pct="<?= $latest['sleep_quality'] ?? 81 ?>" data-color="#7C3AED" data-size="80" data-label="Quality"></div>
        </div>
        <div style="margin-top:16px;height:60px;"><canvas id="sleepMiniChart"></canvas></div>
        <div style="margin-top:12px;font-size:12px;color:rgba(255,255,255,.7);">
          ✨ You slept better than yesterday!
        </div>
      </div>
    </div>

    <!-- Bottom row: 3 more cards -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">

      <!-- Mental Health -->
      <div class="insight-card" style="background:linear-gradient(135deg,#064E3B 0%,#065F46 100%);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
          <div>
            <div class="card-label"><i class="fas fa-brain"></i> Mental Health</div>
            <div style="font-size:16px;font-weight:700;margin-bottom:4px;">Self Love &amp; Positivity</div>
            <div class="card-sub">Emotional wellbeing</div>
          </div>
          <div class="progress-circle" data-pct="92" data-color="#22C55E" data-size="80" data-label="Quality"></div>
        </div>
        <div style="margin-top:16px;display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <?php foreach (['Mood: Positive','Stress: Low','Focus: High','Energy: Good'] as $item): ?>
          <div style="background:rgba(255,255,255,.1);border-radius:6px;padding:6px 10px;font-size:11px;">✓ <?= $item ?></div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Heart Rate -->
      <div class="insight-card" style="background:linear-gradient(135deg,#7F1D1D 0%,#991B1B 100%);">
        <div class="card-label"><i class="fas fa-heartbeat"></i> Heart Rate</div>
        <div class="big-value"><?= $latest['heart_rate'] ?? 74 ?><span class="big-unit">bpm (avg)</span></div>
        <div class="card-sub">Resting HR: <?= ($latest['heart_rate'] ?? 74) - 2 ?> +/-2 bpm</div>

        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:12px;">
          <?php
          $chips = [
            'ECG: Normal', 'SpO₂: '.($latest['spo2']??97).'%',
            'Temp: '.($latest['temperature']??36.2).'°C',
            'Stress: '.(['Low','Moderate','High'][round(($latest['stress_level']??45)/40)] ?? 'Moderate')
          ];
          foreach ($chips as $c): ?>
          <span style="background:rgba(255,255,255,.15);border-radius:20px;padding:4px 12px;font-size:11px;font-weight:600;"><?= $c ?></span>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:16px;height:60px;"><canvas id="hrMiniChart"></canvas></div>
        <div style="margin-top:12px;">
          <details>
            <summary style="cursor:pointer;font-size:12px;color:rgba(255,255,255,.8);">Show details ▼</summary>
            <div style="margin-top:8px;font-size:11px;color:rgba(255,255,255,.6);line-height:1.8;">
              Heart rate: Continuous monitoring with alerts for high/slow rates.<br>
              ECG: Detects irregular rhythm (AFib).<br>
              SpO₂: Oxygen saturation for sleep &amp; respiration.<br>
              Temp: Tracks skin temperature &amp; health trends.
            </div>
          </details>
        </div>
      </div>

      <!-- Daily Walking -->
      <div class="insight-card" style="background:linear-gradient(135deg,#0C4A6E 0%,#0369A1 100%);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
          <div>
            <div class="card-label"><i class="fas fa-walking"></i> Daily Walking</div>
            <div class="big-value" style="font-size:42px;"><?= number_format($latest['steps_count'] ?? 7495) ?></div>
            <div class="card-sub">Steps today</div>
          </div>
          <i class="fas fa-chevron-up" style="font-size:20px;opacity:.5;"></i>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px;">
          <div style="background:rgba(255,255,255,.1);border-radius:8px;padding:10px;text-align:center;">
            <div style="font-size:20px;font-weight:800;"><?= $latest['distance_km'] ?? 3.5 ?></div>
            <div style="font-size:11px;opacity:.7;">km Distance</div>
          </div>
          <div style="background:rgba(255,255,255,.1);border-radius:8px;padding:10px;text-align:center;">
            <div style="font-size:20px;font-weight:800;"><?= round($latest['calories_burned'] ?? 195) ?></div>
            <div style="font-size:11px;opacity:.7;">kcal Burned</div>
          </div>
        </div>
        <!-- Step gauge -->
        <div style="margin-top:16px;">
          <div style="display:flex;justify-content:space-between;font-size:11px;opacity:.7;margin-bottom:4px;">
            <span>0</span><span>Goal: 10,000</span>
          </div>
          <div style="background:rgba(255,255,255,.15);border-radius:4px;height:8px;overflow:hidden;">
            <div style="width:<?= min(round(($latest['steps_count']??7495)/100),100) ?>%;background:#22C55E;height:100%;border-radius:4px;transition:width 1s ease;"></div>
          </div>
        </div>
      </div>

    </div>

    <!-- 7-Day Charts -->
    <div style="margin-top:24px;">
      <div class="section-title">7-Day Trend Analysis</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:20px;">
          <h6 style="color:#fff;margin-bottom:16px;font-size:13px;">Heart Rate Trend</h6>
          <div style="height:180px;"><canvas id="heartRateChart"></canvas></div>
        </div>
        <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:20px;">
          <h6 style="color:#fff;margin-bottom:16px;font-size:13px;">Blood Pressure</h6>
          <div style="height:180px;"><canvas id="bpChart"></canvas></div>
        </div>
        <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:20px;">
          <h6 style="color:#fff;margin-bottom:16px;font-size:13px;">Daily Steps</h6>
          <div style="height:180px;"><canvas id="stepsChart"></canvas></div>
        </div>
        <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:20px;">
          <h6 style="color:#fff;margin-bottom:16px;font-size:13px;">Sleep Hours</h6>
          <div style="height:180px;"><canvas id="sleepChart"></canvas></div>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const LABELS = <?= json_encode($labels ?: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']) ?>;
const HR_DATA = <?= json_encode($hr ?: [72,74,78,70,76,73,74]) ?>;
const SYS_DATA = <?= json_encode($sys ?: [120,118,122,116,124,119,118]) ?>;
const DIA_DATA = <?= json_encode($dia ?: [78,76,79,74,81,77,76]) ?>;
const STEPS_DATA = <?= json_encode($steps ?: [8200,7495,5100,9200,6300,10500,4800]) ?>;
const SLEEP_DATA = <?= json_encode($sleep ?: [8.0,6.5,7.5,7.0,8.2,6.0,7.83]) ?>;

Chart.defaults.color = 'rgba(255,255,255,0.5)';
Chart.defaults.borderColor = 'rgba(255,255,255,0.08)';

function sparkLine(id, data, color) {
  const ctx = document.getElementById(id);
  if (!ctx) return;
  new Chart(ctx, { type:'line', data:{ labels:LABELS, datasets:[{ data, borderColor:color, borderWidth:2, fill:false, tension:.4, pointRadius:0 }] }, options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ x:{display:false}, y:{display:false} } } });
}

sparkLine('nutritionMiniChart', [1100,980,1250,1400,1100,1300,1270], '#1565C0');
sparkLine('sleepMiniChart', SLEEP_DATA, '#7C3AED');
sparkLine('hrMiniChart', HR_DATA, '#EF4444');

function lineChart(id, datasets) {
  const ctx = document.getElementById(id); if (!ctx) return;
  new Chart(ctx, { type:'line', data:{labels:LABELS, datasets}, options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:datasets.length>1}}, scales:{ x:{grid:{color:'rgba(255,255,255,.05)'}}, y:{grid:{color:'rgba(255,255,255,.05)'}} } } });
}
function barChart(id, data, color) {
  const ctx = document.getElementById(id); if (!ctx) return;
  new Chart(ctx, { type:'bar', data:{labels:LABELS, datasets:[{data, backgroundColor:color, borderRadius:4, borderSkipped:false}]}, options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ x:{grid:{display:false}}, y:{grid:{color:'rgba(255,255,255,.05)'}, ticks:{color:'rgba(255,255,255,.5)'}} } } });
}

lineChart('heartRateChart',[{label:'HR (bpm)',data:HR_DATA,borderColor:'#EF4444',backgroundColor:'rgba(239,68,68,.1)',fill:true,tension:.4,pointBackgroundColor:'#EF4444',pointRadius:3}]);
lineChart('bpChart',[
  {label:'Systolic',data:SYS_DATA,borderColor:'#1565C0',fill:false,tension:.4,pointRadius:3},
  {label:'Diastolic',data:DIA_DATA,borderColor:'#00B4D8',fill:false,tension:.4,pointRadius:3}
]);
barChart('stepsChart', STEPS_DATA, STEPS_DATA.map((_,i)=>i===1?'#22C55E':'#1E40AF'));
barChart('sleepChart', SLEEP_DATA, SLEEP_DATA.map(v=>v>=7?'#7C3AED':'#EF4444'));

// Progress circles
document.querySelectorAll('.progress-circle').forEach(wrap => {
  const pct=parseFloat(wrap.dataset.pct||0), color=wrap.dataset.color||'#1565C0', size=parseInt(wrap.dataset.size||80), stroke=parseInt(wrap.dataset.stroke||6);
  const r=(size-stroke)/2, circ=2*Math.PI*r, offset=circ-(pct/100)*circ;
  wrap.innerHTML=`<svg width="${size}" height="${size}"><circle class="circle-bg" cx="${size/2}" cy="${size/2}" r="${r}" stroke-width="${stroke}" fill="none" stroke="rgba(255,255,255,.15)"/><circle class="circle-fill" cx="${size/2}" cy="${size/2}" r="${r}" stroke="${color}" stroke-width="${stroke}" stroke-dasharray="${circ}" stroke-dashoffset="${circ}" fill="none" stroke-linecap="round" style="transform-origin:center;transform:rotate(-90deg);transition:stroke-dashoffset 1.2s ease"/></svg><div class="circle-text"><span class="circle-pct">${pct}%</span><span class="circle-lbl">${wrap.dataset.label||''}</span></div>`;
  setTimeout(()=>{ wrap.querySelector('.circle-fill').style.strokeDashoffset=offset; },100);
});
</script>
</body>
</html>
