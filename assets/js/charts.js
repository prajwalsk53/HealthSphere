// HealthSphere — Chart Configurations (Chart.js)

const HS_COLORS = {
  blue:    '#1565C0',
  cyan:    '#00B4D8',
  green:   '#16A34A',
  yellow:  '#D97706',
  red:     '#DC2626',
  purple:  '#7C3AED',
  navy:    '#0A1F44',
  muted:   '#94A3B8',
};

Chart.defaults.font.family = "'Inter','Segoe UI',system-ui,sans-serif";
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#5E7A99';

// Gradient helper
function makeGradient(ctx, colorTop, colorBottom) {
  const g = ctx.createLinearGradient(0, 0, 0, 300);
  g.addColorStop(0, colorTop);
  g.addColorStop(1, colorBottom);
  return g;
}

// ── Heart Rate / Vitals Sparkline ──────────────────────
function initHeartRateChart(canvasId, data) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: data.labels || ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
      datasets: [{
        data: data.values || [72, 74, 78, 70, 76, 73, 74],
        borderColor: '#EF4444',
        borderWidth: 2,
        fill: true,
        backgroundColor: ctx => makeGradient(ctx.chart.ctx, 'rgba(239,68,68,.2)', 'rgba(239,68,68,.01)'),
        tension: 0.4,
        pointRadius: 3,
        pointBackgroundColor: '#EF4444',
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { display: false },
        y: { display: false },
      },
    },
  });
}

// ── Steps / Walking Bar ────────────────────────────────
function initStepsChart(canvasId, data) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: data.labels || ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
      datasets: [{
        data: data.values || [8200,7495,5100,9200,6300,10500,4800],
        backgroundColor: data.values?.map((v,i) => i === data.todayIndex ? '#1565C0' : '#DBEAFE') ||
          ['#DBEAFE','#1565C0','#DBEAFE','#DBEAFE','#DBEAFE','#DBEAFE','#DBEAFE'],
        borderRadius: 6,
        borderSkipped: false,
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false } },
        y: {
          grid: { color: '#F0F4FF' },
          ticks: { callback: v => v >= 1000 ? (v/1000).toFixed(0)+'k' : v },
        },
      },
    },
  });
}

// ── Nutrition Donut ─────────────────────────────────────
function initNutritionChart(canvasId, data) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Protein','Carbs','Fats','Fiber'],
      datasets: [{
        data: data || [528, 173, 199, 42],
        backgroundColor: ['#1565C0','#00B4D8','#D97706','#16A34A'],
        borderWidth: 2, borderColor: '#fff',
        hoverBorderWidth: 3,
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      cutout: '72%',
      plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 16 } },
      },
    },
  });
}

// ── Blood Pressure Line ────────────────────────────────
function initBPChart(canvasId, systolic, diastolic, labels) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels || ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
      datasets: [
        {
          label: 'Systolic',
          data: systolic || [120, 118, 122, 116, 124, 119, 118],
          borderColor: '#1565C0', borderWidth: 2, tension: 0.4,
          fill: false, pointRadius: 4, pointBackgroundColor: '#1565C0',
        },
        {
          label: 'Diastolic',
          data: diastolic || [78, 76, 79, 74, 81, 77, 76],
          borderColor: '#00B4D8', borderWidth: 2, tension: 0.4,
          fill: false, pointRadius: 4, pointBackgroundColor: '#00B4D8',
        },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: true, position: 'top' } },
      scales: {
        x: { grid: { display: false } },
        y: {
          grid: { color: '#EFF6FF' },
          min: 60, max: 160,
          ticks: { stepSize: 20 },
        },
      },
    },
  });
}

// ── Sleep Bar ──────────────────────────────────────────
function initSleepChart(canvasId, data) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: data.labels || ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
      datasets: [{
        label: 'Sleep Hours',
        data: data.values || [8.0, 6.5, 7.5, 7.0, 8.2, 6.0, 7.83],
        backgroundColor: data.values?.map(v => v >= 7 ? '#16A34A' : v >= 6 ? '#D97706' : '#DC2626')
          || ['#16A34A','#D97706','#16A34A','#16A34A','#16A34A','#DC2626','#16A34A'],
        borderRadius: 6, borderSkipped: false,
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false } },
        y: { grid: { color: '#EFF6FF' }, min: 0, max: 12 },
      },
    },
  });
}

// ── Health Overview Radar ──────────────────────────────
function initHealthRadar(canvasId, data) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'radar',
    data: {
      labels: ['Diet','Exercise','Sleep','Hydration','Mental Health','Vitals'],
      datasets: [{
        label: 'Your Health',
        data: data || [75, 82, 81, 45, 92, 88],
        backgroundColor: 'rgba(21,101,192,.15)',
        borderColor: '#1565C0',
        borderWidth: 2,
        pointBackgroundColor: '#1565C0',
        pointRadius: 4,
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      scales: {
        r: {
          min: 0, max: 100,
          ticks: { display: false },
          grid: { color: '#EFF6FF' },
          pointLabels: { font: { size: 12, weight: 600 }, color: '#0A1F44' },
        },
      },
      plugins: { legend: { display: false } },
    },
  });
}

// ── Government / Public Health Multi-Line ─────────────
function initTrendChart(canvasId, datasets, labels) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'line',
    data: { labels, datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top' },
        tooltip: { mode: 'index', intersect: false },
      },
      scales: {
        x: { grid: { display: false } },
        y: { grid: { color: '#EFF6FF' } },
      },
    },
  });
}

// ── Hospital Patient Admission Bar ────────────────────
function initAdmissionsChart(canvasId, data, labels) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels || ['Jan','Feb','Mar','Apr','May','Jun','Jul'],
      datasets: [
        { label: 'New Patients', data: data.new || [80,95,112,98,125,110,135], backgroundColor: '#1565C0', borderRadius: 4 },
        { label: 'Follow-up',   data: data.followup || [45,58,72,65,82,70,90],   backgroundColor: '#00B4D8', borderRadius: 4 },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'top' } },
      scales: {
        x: { grid: { display: false }, stacked: false },
        y: { grid: { color: '#EFF6FF' } },
      },
    },
  });
}

// ── Calorie Gauge (horizontal bar) ───────────────────
function initCalorieGauge(canvasId, consumed, goal) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  const pct = Math.min((consumed / goal) * 100, 100);
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      datasets: [{
        data: [pct, 100 - pct],
        backgroundColor: [pct > 90 ? '#DC2626' : '#1565C0', '#EFF6FF'],
        borderWidth: 0,
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      cutout: '80%',
      rotation: -90, circumference: 180,
      plugins: { legend: { display: false }, tooltip: { enabled: false } },
    },
  });
}

// ── Disease Distribution Pie ──────────────────────────
function initDiseasePie(canvasId, data, labels) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'pie',
    data: {
      labels,
      datasets: [{
        data,
        backgroundColor: ['#1565C0','#00B4D8','#16A34A','#D97706','#DC2626','#7C3AED'],
        borderWidth: 2, borderColor: '#fff',
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'right', labels: { boxWidth: 14, padding: 14 } } },
    },
  });
}

// ── Auto-init on load ─────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // These will only run if the canvas elements exist on the page
  initHeartRateChart('heartRateChart', { values: [72,74,78,70,76,73,74] });
  initStepsChart('stepsChart', { values: [8200,7495,5100,9200,6300,10500,4800], todayIndex: 1 });
  initNutritionChart('nutritionChart', [528, 173, 199, 42]);
  initBPChart('bpChart');
  initSleepChart('sleepChart', { values: [8.0,6.5,7.5,7.0,8.2,6.0,7.83] });
  initHealthRadar('healthRadar');

  // Government charts
  initTrendChart('govTrendChart',
    [
      { label: 'Hypertension', data: [120,135,142,138,155,148,162,170,155,168,180,192], borderColor:'#1565C0', backgroundColor:'rgba(21,101,192,.08)', tension:.4, fill:true },
      { label: 'Diabetes',     data: [80,88,92,85,95,100,98,105,112,108,115,122],      borderColor:'#DC2626', backgroundColor:'rgba(220,38,38,.06)', tension:.4, fill:true },
      { label: 'Obesity',      data: [65,70,68,72,75,78,80,82,85,88,90,95],            borderColor:'#D97706', backgroundColor:'rgba(217,119,6,.06)', tension:.4, fill:true },
    ],
    ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
  );
  initAdmissionsChart('admissionsChart');
  initDiseasePie('diseasePieChart', [30,25,20,15,10],[
    'Hypertension','Type 2 Diabetes','Obesity','Thyroid','Other'
  ]);
});
