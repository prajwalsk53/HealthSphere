<?php
require_once __DIR__ . '/../config/config.php';
requireRole('government');
$user = getCurrentUser();
$uid  = $user['id'];
$notifCount = getUnreadCount($pdo, $uid);

// UK Regional disease data for heat/choropleth
$regionData = [
  ['name'=>'East Midlands','lat'=>52.6369,'lng'=>-1.1398,'risk'=>'critical','condition'=>'Hypertension','change'=>34,'cases'=>4821,'color'=>'#DC2626','radius'=>38],
  ['name'=>'North West','lat'=>53.4808,'lng'=>-2.2426,'risk'=>'critical','condition'=>'Type 2 Diabetes','change'=>22,'cases'=>6103,'color'=>'#DC2626','radius'=>42],
  ['name'=>'North East','lat'=>54.9783,'lng'=>-1.6178,'risk'=>'attention','condition'=>'Obesity','change'=>18,'cases'=>2987,'color'=>'#D97706','radius'=>30],
  ['name'=>'Yorkshire','lat'=>53.8008,'lng'=>-1.5491,'risk'=>'attention','condition'=>'Mental Health','change'=>12,'cases'=>3412,'color'=>'#D97706','radius'=>32],
  ['name'=>'South East','lat'=>51.2787,'lng'=>-0.5217,'risk'=>'attention','condition'=>'Respiratory','change'=>10,'cases'=>2190,'color'=>'#D97706','radius'=>35],
  ['name'=>'West Midlands','lat'=>52.4862,'lng'=>-1.8904,'risk'=>'attention','condition'=>'Cardiovascular','change'=>8,'cases'=>3750,'color'=>'#D97706','radius'=>33],
  ['name'=>'London','lat'=>51.5074,'lng'=>-0.1278,'risk'=>'healthy','condition'=>'Hypertension','change'=>2,'cases'=>5210,'color'=>'#16A34A','radius'=>40],
  ['name'=>'South West','lat'=>50.7772,'lng'=>-3.9990,'risk'=>'healthy','condition'=>'Diabetes','change'=>-3,'cases'=>1820,'color'=>'#16A34A','radius'=>28],
  ['name'=>'East of England','lat'=>52.2405,'lng'=>0.5050,'risk'=>'healthy','condition'=>'Obesity','change'=>1,'cases'=>2100,'color'=>'#16A34A','radius'=>30],
  ['name'=>'Wales','lat'=>52.1307,'lng'=>-3.7837,'risk'=>'healthy','condition'=>'Diabetes','change'=>-5,'cases'=>1450,'color'=>'#16A34A','radius'=>25],
  ['name'=>'Scotland','lat'=>56.4907,'lng'=>-4.2026,'risk'=>'healthy','condition'=>'Obesity','change'=>-8,'cases'=>2340,'color'=>'#16A34A','radius'=>35],
  ['name'=>'Northern Ireland','lat'=>54.7877,'lng'=>-6.4923,'risk'=>'healthy','condition'=>'Hypertension','change'=>-2,'cases'=>890,'color'=>'#16A34A','radius'=>22],
];

$heatData = [
  [52.6369,-1.1398,0.95],[53.4808,-2.2426,0.88],[54.9783,-1.6178,0.72],
  [53.8008,-1.5491,0.68],[51.5074,-0.1278,0.45],[52.4862,-1.8904,0.65],
  [51.2787,-0.5217,0.58],[50.7772,-3.999,0.38],[52.2405,0.505,0.42],
  [52.1307,-3.7837,0.35],[56.4907,-4.2026,0.32],[54.7877,-6.4923,0.28],
  // Extra density points
  [52.6200,-1.1200,0.85],[52.6500,-1.1500,0.78],[53.5000,-2.3000,0.82],
  [53.4500,-2.1000,0.75],[52.6300,-1.1600,0.90],[52.6100,-1.1000,0.70],
];

// NHS hospitals for map markers
$hospitals = [
  ['name'=>'Leicester Royal Infirmary','lat'=>52.6362,'lng'=>-1.1388,'type'=>'Major A&E'],
  ['name'=>'Manchester Royal Infirmary','lat'=>53.4763,'lng'=>-2.2355,'type'=>'Major A&E'],
  ['name'=>'Newcastle RVI','lat'=>54.9793,'lng'=>-1.6210,'type'=>'Major A&E'],
  ['name'=>'Leeds General Infirmary','lat'=>53.8008,'lng'=>-1.5517,'type'=>'Major A&E'],
  ['name'=>'University Hospital Birmingham','lat'=>52.4490,'lng'=>-1.9397,'type'=>'Major A&E'],
  ['name'=>'Kings College Hospital','lat'=>51.4679,'lng'=>-0.0927,'type'=>'Major A&E'],
  ['name'=>'Southmead Hospital Bristol','lat'=>51.4989,'lng'=>-2.5937,'type'=>'Major A&E'],
  ['name'=>'Edinburgh Royal Infirmary','lat'=>55.9228,'lng'=>-3.1684,'type'=>'Major A&E'],
  ['name'=>'Cardiff University Hospital','lat'=>51.4816,'lng'=>-3.2005,'type'=>'Major A&E'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Regional Health Map — HealthSphere Gov</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<style>
html,body { height:100%; overflow:hidden; }
.hs-main { height:100vh; overflow:hidden; }
.hs-content { padding:0 !important; height:calc(100vh - 64px); display:flex; gap:0; overflow:hidden; }
#govMap { width:100%; height:100%; }
.map-panel { width:340px; flex-shrink:0; background:#fff; border-right:1px solid var(--hs-border); overflow-y:auto; }
.map-panel-section { padding:16px; border-bottom:1px solid var(--hs-border); }
.region-card-mini {
  padding:10px 14px; border-radius:8px; margin-bottom:8px;
  border-left:4px solid; cursor:pointer; transition:var(--transition);
  background:#fff; border:1px solid var(--hs-border); border-left-width:4px;
}
.region-card-mini:hover { transform:translateX(4px); box-shadow:var(--shadow-md); }
.leaflet-popup-content-wrapper { border-radius:12px !important; box-shadow:0 8px 24px rgba(10,31,68,.18) !important; padding:0 !important; overflow:hidden; }
.leaflet-popup-content { margin:0 !important; min-width:240px !important; }
.leaflet-popup-tip-container { display:none; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-map-marked-alt" style="color:var(--hs-blue);"></i> Regional Health Risk Map — UK</div>
      <div class="page-subtitle">Anonymised population-level disease distribution &middot; DHSC Data &middot; <?= date('d M Y') ?></div>
    </div>
    <div class="topbar-actions">
      <button onclick="toggleHeat()" id="heatBtn" class="btn-hs btn-outline-hs btn-sm-hs">
        <i class="fas fa-fire"></i> <span id="heatBtnLabel">Show Disease Heatmap</span>
      </button>
      <button onclick="toggleHospitals()" id="hospBtn" class="btn-hs btn-outline-hs btn-sm-hs">
        <i class="fas fa-hospital"></i> <span id="hospBtnLabel">Show Hospitals</span>
      </button>
      <button class="btn-hs btn-primary-hs btn-sm-hs" onclick="window.open('reports.php')">
        <i class="fas fa-file-alt"></i> Draft Policy Brief
      </button>
    </div>
  </div>

  <div class="hs-content">
    <!-- Sidebar panel -->
    <div class="map-panel">
      <!-- Legend -->
      <div class="map-panel-section">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--hs-muted);margin-bottom:10px;">Risk Level Legend</div>
        <?php foreach ([['#DC2626','Critical — >20% above average'],['#D97706','Attention — 5–20% above'],['#16A34A','Healthy — Within range']] as [$c,$l]): ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;font-size:12px;">
          <span style="width:12px;height:12px;border-radius:50%;background:<?= $c ?>;display:inline-block;flex-shrink:0;"></span>
          <?= $l ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Filter -->
      <div class="map-panel-section">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--hs-muted);margin-bottom:8px;">Filter by Condition</div>
        <select id="conditionFilter" onchange="filterRegions()" class="form-select" style="font-size:12px;">
          <option value="">All Conditions</option>
          <option>Hypertension</option><option>Type 2 Diabetes</option>
          <option>Obesity</option><option>Mental Health</option>
          <option>Respiratory</option><option>Cardiovascular</option>
        </select>
      </div>

      <!-- Region list -->
      <div class="map-panel-section" style="padding-bottom:0;">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--hs-muted);margin-bottom:10px;">All Regions</div>
      </div>
      <div style="padding:0 14px 14px;">
        <?php foreach ($regionData as $r):
          $arrow = $r['change'] > 0 ? '↑' : '↓';
          $ac    = $r['change'] > 15 ? '#DC2626' : ($r['change'] > 4 ? '#D97706' : '#16A34A');
        ?>
        <div class="region-card-mini" style="border-left-color:<?= $r['color'] ?>;"
          onclick="flyToRegion(<?= $r['lat'] ?>,<?= $r['lng'] ?>,'<?= addslashes($r['name']) ?>')">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
            <div>
              <div style="font-weight:700;font-size:13px;color:var(--hs-navy);"><?= e($r['name']) ?></div>
              <div style="font-size:11px;color:<?= $r['color'] ?>;font-weight:600;"><?= e($r['condition']) ?></div>
            </div>
            <span style="color:<?= $ac ?>;font-weight:900;font-size:15px;"><?= $arrow ?> <?= abs($r['change']) ?>%</span>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:11px;color:var(--hs-muted);"><?= number_format($r['cases']) ?> cases</span>
            <span style="background:<?= $r['color'] ?>22;color:<?= $r['color'] ?>;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;"><?= ucfirst($r['risk']) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Map -->
    <div style="flex:1;position:relative;">
      <div id="govMap"></div>
    </div>
  </div>
</div>

<script>
const REGION_DATA = <?= json_encode($regionData) ?>;
const HEAT_DATA   = <?= json_encode($heatData) ?>;
const HOSPITALS   = <?= json_encode($hospitals) ?>;
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script src="../assets/js/main.js"></script>
<script>
let map, heatLayer, hospLayer, heatOn = false, hospOn = false;
let regionLayers = [];

document.addEventListener('DOMContentLoaded', () => {
  map = L.map('govMap', {
    center: [54.0, -2.5],
    zoom: 6,
    zoomControl: true,
  });

  // Clean CartoDB base layer
  L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; OpenStreetMap &copy; CartoDB', maxZoom: 18,
  }).addTo(map);

  // Region circles
  REGION_DATA.forEach(r => {
    const circle = L.circleMarker([r.lat, r.lng], {
      radius:    r.radius,
      color:     '#fff',
      weight:    2,
      fillColor: r.color,
      fillOpacity: 0.75,
    });

    circle.bindPopup(buildRegionPopup(r), { closeButton: false });
    circle.addTo(map);

    // Label
    const icon = L.divIcon({
      html: `<div style="font-family:'Inter',sans-serif;font-size:11px;font-weight:700;color:${r.color};white-space:nowrap;text-shadow:0 0 6px #fff,0 0 6px #fff,0 0 6px #fff;">${r.name}</div>`,
      className: '', iconAnchor: [0, 0],
    });
    L.marker([r.lat - 0.5, r.lng], { icon }).addTo(map);
    regionLayers.push(circle);
  });

  // Heat layer
  heatLayer = L.heatLayer(HEAT_DATA.map(d => [d[0], d[1], d[2]]), {
    radius: 50, blur: 35, maxZoom: 10,
    gradient: { 0.1:'#1565C0', 0.4:'#00B4D8', 0.65:'#D97706', 1.0:'#DC2626' },
  });

  // Hospital markers cluster
  hospLayer = L.markerClusterGroup({ maxClusterRadius:30 });
  HOSPITALS.forEach(h => {
    const icon = L.divIcon({
      html: `<div style="background:#0A1F44;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.3);">🏥</div>`,
      className:'', iconSize:[28,28], iconAnchor:[14,14],
    });
    const m = L.marker([h.lat, h.lng], { icon });
    m.bindPopup(`<div style="font-family:'Inter',sans-serif;padding:12px;"><strong>${h.name}</strong><br><small style="color:#5E7A99;">${h.type}</small></div>`, { closeButton:false });
    hospLayer.addLayer(m);
  });
});

function buildRegionPopup(r) {
  const arrow = r.change > 0 ? '↑' : '↓';
  const urgency = r.risk === 'critical' ? '🚨 Requires immediate policy action' : (r.risk === 'attention' ? '⚠️ Under monitoring' : '✅ Within safe range');
  return `
  <div style="font-family:'Inter',sans-serif;">
    <div style="background:${r.color};color:#fff;padding:14px 16px;">
      <div style="font-size:16px;font-weight:800;">${r.name}</div>
      <div style="font-size:12px;opacity:.85;">${r.condition} &middot; ${r.risk.toUpperCase()}</div>
    </div>
    <div style="padding:14px 16px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
        <div><div style="font-size:28px;font-weight:900;color:${r.color};">${arrow} ${Math.abs(r.change)}%</div><div style="font-size:11px;color:#5E7A99;">vs national average</div></div>
        <div style="text-align:right;"><div style="font-size:20px;font-weight:800;color:#0A1F44;">${r.cases.toLocaleString()}</div><div style="font-size:11px;color:#5E7A99;">new cases</div></div>
      </div>
      <div style="font-size:12px;color:#5E7A99;padding:8px;background:#F8FAFF;border-radius:6px;">${urgency}</div>
      <div style="display:flex;gap:8px;margin-top:10px;">
        <a href="reports.php" style="flex:1;text-align:center;background:${r.color};color:#fff;padding:7px;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;display:block;">Draft Policy</a>
        <a href="alerts.php" style="padding:7px 12px;border:1px solid #E2E8F0;border-radius:6px;font-size:12px;font-weight:600;color:#0A1F44;text-decoration:none;">View Alert</a>
      </div>
    </div>
  </div>`;
}

function toggleHeat() {
  heatOn = !heatOn;
  heatOn ? heatLayer.addTo(map) : heatLayer.remove();
  document.getElementById('heatBtn').className = 'btn-hs btn-sm-hs ' + (heatOn ? 'btn-primary-hs' : 'btn-outline-hs');
  document.getElementById('heatBtnLabel').textContent = heatOn ? 'Hide Disease Heatmap' : 'Show Disease Heatmap';
}

function toggleHospitals() {
  hospOn = !hospOn;
  hospOn ? hospLayer.addTo(map) : hospLayer.remove();
  document.getElementById('hospBtn').className = 'btn-hs btn-sm-hs ' + (hospOn ? 'btn-primary-hs' : 'btn-outline-hs');
  document.getElementById('hospBtnLabel').textContent = hospOn ? 'Hide Hospitals' : 'Show Hospitals';
}

function flyToRegion(lat, lng, name) {
  map.flyTo([lat, lng], 9, { duration: 1.4 });
}

function filterRegions() {
  const cond = document.getElementById('conditionFilter').value.toLowerCase();
  regionLayers.forEach((layer, i) => {
    const r = REGION_DATA[i];
    layer.setStyle({ fillOpacity: (!cond || r.condition.toLowerCase().includes(cond)) ? 0.75 : 0.1 });
  });
}
</script>
</body>
</html>
