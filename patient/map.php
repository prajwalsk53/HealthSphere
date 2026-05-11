<?php
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
$user = getCurrentUser();
$uid  = $user['id'];
$notifCount = getUnreadCount($pdo, $uid);
$msgCount   = getUnreadMessages($pdo, $uid);

// ── Health facilities dataset (Leicester, UK — real NHS locations) ─
$facilities = [
  // ── Hospitals ───────────────────────────────────────────────────
  ['id'=>1,'name'=>'Leicester Royal Infirmary','type'=>'hospital','subtype'=>'Major Hospital','lat'=>52.6362,'lng'=>-1.1388,'address'=>'Infirmary Square, Leicester LE1 5WW','phone'=>'0116 254 1414','hours'=>'24/7 A&E','rating'=>4.3,'wait'=>'~45 min','distance'=>0.8,'open'=>true,'nhs'=>true,'emergency'=>true,'beds'=>1520],
  ['id'=>2,'name'=>'Leicester General Hospital','type'=>'hospital','subtype'=>'General Hospital','lat'=>52.6187,'lng'=>-1.1015,'address'=>'Gwendolen Rd, Leicester LE5 4PW','phone'=>'0116 249 0490','hours'=>'24/7','rating'=>4.1,'wait'=>'~30 min','distance'=>2.1,'open'=>true,'nhs'=>true,'emergency'=>false,'beds'=>820],
  ['id'=>3,'name'=>'Glenfield Hospital','type'=>'hospital','subtype'=>'Cardiology & Children','lat'=>52.6370,'lng'=>-1.2052,'address'=>'Groby Rd, Leicester LE3 9QP','phone'=>'0116 287 1471','hours'=>'24/7','rating'=>4.5,'wait'=>'~20 min','distance'=>3.4,'open'=>true,'nhs'=>true,'emergency'=>false,'beds'=>450],
  ['id'=>4,'name'=>'LOROS Hospice','type'=>'hospital','subtype'=>'Palliative Care','lat'=>52.6124,'lng'=>-1.1445,'address'=>'Groby Rd, Leicester LE3 9QE','phone'=>'0116 231 3771','hours'=>'09:00-17:00','rating'=>4.9,'wait'=>'N/A','distance'=>3.8,'open'=>true,'nhs'=>false,'emergency'=>false,'beds'=>36],
  ['id'=>5,'name'=>'BMI The Evington Hospital','type'=>'hospital','subtype'=>'Private Hospital','lat'=>52.6154,'lng'=>-1.0893,'address'=>'Gartree Rd, Leicester LE2 2FF','phone'=>'0116 273 2021','hours'=>'07:00-21:00','rating'=>4.4,'wait'=>'<10 min','distance'=>4.2,'open'=>true,'nhs'=>false,'emergency'=>false,'beds'=>95],

  // ── GPs / Clinics ────────────────────────────────────────────────
  ['id'=>6,'name'=>'Starlight Medical Centre','type'=>'gp','subtype'=>'General Practice','lat'=>52.6398,'lng'=>-1.1290,'address'=>'22 King Street, Leicester LE1 6RJ','phone'=>'0116 255 8877','hours'=>'08:00-18:30','rating'=>4.2,'wait'=>'~15 min','distance'=>0.4,'open'=>true,'nhs'=>true,'emergency'=>false,'beds'=>0],
  ['id'=>7,'name'=>'ApexCare GP Surgery','type'=>'gp','subtype'=>'General Practice','lat'=>52.6320,'lng'=>-1.1460,'address'=>'Park Lane, Leicester LE4 5GH','phone'=>'0116 262 1234','hours'=>'08:00-18:00','rating'=>4.0,'wait'=>'~20 min','distance'=>0.9,'open'=>true,'nhs'=>true,'emergency'=>false,'beds'=>0],
  ['id'=>8,'name'=>'Westcotes Health Centre','type'=>'gp','subtype'=>'General Practice','lat'=>52.6280,'lng'=>-1.1510,'address'=>'Fosse Rd South, Leicester LE3 0LH','phone'=>'0116 255 9900','hours'=>'08:00-18:30','rating'=>3.9,'wait'=>'~25 min','distance'=>1.5,'open'=>true,'nhs'=>true,'emergency'=>false,'beds'=>0],
  ['id'=>9,'name'=>'Belgrave Medical Centre','type'=>'gp','subtype'=>'General Practice','lat'=>52.6480,'lng'=>-1.1250,'address'=>'Belgrave Rd, Leicester LE4 5AT','phone'=>'0116 266 0080','hours'=>'08:00-17:30','rating'=>4.1,'wait'=>'~18 min','distance'=>1.9,'open'=>false,'nhs'=>true,'emergency'=>false,'beds'=>0],
  ['id'=>10,'name'=>'NHS Walk-In Centre','type'=>'gp','subtype'=>'Walk-In Centre','lat'=>52.6350,'lng'=>-1.1300,'address'=>'Granby Street, Leicester LE1 6EZ','phone'=>'0116 295 5000','hours'=>'07:00-22:00','rating'=>4.4,'wait'=>'~10 min','distance'=>0.6,'open'=>true,'nhs'=>true,'emergency'=>false,'beds'=>0],
  ['id'=>11,'name'=>'Bridge Street Medical Practice','type'=>'gp','subtype'=>'General Practice','lat'=>52.6410,'lng'=>-1.1180,'address'=>'Bridge St, Leicester LE1 4TD','phone'=>'0116 255 1122','hours'=>'08:00-18:00','rating'=>4.3,'wait'=>'~15 min','distance'=>0.7,'open'=>true,'nhs'=>true,'emergency'=>false,'beds'=>0],

  // ── Pharmacies ───────────────────────────────────────────────────
  ['id'=>12,'name'=>'Boots Pharmacy — Gallowtree Gate','type'=>'pharmacy','subtype'=>'Pharmacy Chain','lat'=>52.6369,'lng'=>-1.1316,'address'=>'Gallowtree Gate, Leicester LE1 5AD','phone'=>'0116 251 6626','hours'=>'08:00-21:00','rating'=>4.1,'wait'=>'~5 min','distance'=>0.3,'open'=>true,'nhs'=>true,'emergency'=>false,'beds'=>0],
  ['id'=>13,'name'=>"Lloyds Pharmacy — Charles Street",'type'=>'pharmacy','subtype'=>'Pharmacy Chain','lat'=>52.6355,'lng'=>-1.1270,'address'=>'Charles Street, Leicester LE1 3SH','phone'=>'0116 251 2200','hours'=>'09:00-18:00','rating'=>4.0,'wait'=>'<5 min','distance'=>0.6,'open'=>true,'nhs'=>true,'emergency'=>false,'beds'=>0],
  ['id'=>14,'name'=>'Rowlands Pharmacy','type'=>'pharmacy','subtype'=>'Independent Pharmacy','lat'=>52.6244,'lng'=>-1.1380,'address'=>'Evington Rd, Leicester LE2 1HN','phone'=>'0116 273 7711','hours'=>'09:00-19:00','rating'=>4.3,'wait'=>'<5 min','distance'=>1.8,'open'=>true,'nhs'=>true,'emergency'=>false,'beds'=>0],
  ['id'=>15,'name'=>'Well Pharmacy — Beaumont Leys','type'=>'pharmacy','subtype'=>'Pharmacy Chain','lat'=>52.6520,'lng'=>-1.1480,'address'=>'Beaumont Way, Leicester LE4 1DW','phone'=>'0116 235 0032','hours'=>'09:00-20:00','rating'=>3.8,'wait'=>'~10 min','distance'=>2.6,'open'=>false,'nhs'=>true,'emergency'=>false,'beds'=>0],

  // ── Emergency / Mental Health ────────────────────────────────────
  ['id'=>16,'name'=>'Leicester A&E — Royal Infirmary','type'=>'emergency','subtype'=>'Accident & Emergency','lat'=>52.6358,'lng'=>-1.1396,'address'=>'Infirmary Square, Leicester LE1 5WW','phone'=>'999 / 0116 254 1414','hours'=>'24/7','rating'=>4.5,'wait'=>'~45 min','distance'=>0.8,'open'=>true,'nhs'=>true,'emergency'=>true,'beds'=>0],
  ['id'=>17,'name'=>'Leicestershire Partnership — Mental Health','type'=>'mental','subtype'=>'Mental Health Services','lat'=>52.6290,'lng'=>-1.1550,'address'=>'Bradgate Rd, Leicester LE3 0BS','phone'=>'0116 295 0320','hours'=>'09:00-17:00','rating'=>4.2,'wait'=>'Appointment','distance'=>2.2,'open'=>true,'nhs'=>true,'emergency'=>false,'beds'=>120],
  ['id'=>18,'name'=>'Samaritans Leicester','type'=>'mental','subtype'=>'Mental Health Support','lat'=>52.6370,'lng'=>-1.1200,'address'=>'37-39 Millstone Lane, Leicester LE1 5JN','phone'=>'116 123','hours'=>'24/7','rating'=>4.8,'wait'=>'Immediate','distance'=>0.9,'open'=>true,'nhs'=>false,'emergency'=>false,'beds'=>0],
  ['id'=>19,'name'=>'Changes Mental Health','type'=>'mental','subtype'=>'Counselling','lat'=>52.6430,'lng'=>-1.1100,'address'=>'Forest Road, Leicester LE1 6TG','phone'=>'0116 253 6553','hours'=>'09:00-18:00','rating'=>4.6,'wait'=>'~2 weeks','distance'=>1.4,'open'=>true,'nhs'=>false,'emergency'=>false,'beds'=>0],

  // ── Dental / Optical ─────────────────────────────────────────────
  ['id'=>20,'name'=>'Leicester Dental Hospital','type'=>'dental','subtype'=>'Dental Hospital','lat'=>52.6337,'lng'=>-1.1345,'address'=>'University Rd, Leicester LE1 7HA','phone'=>'0116 252 2837','hours'=>'08:30-17:30','rating'=>4.0,'wait'=>'Appointment','distance'=>1.1,'open'=>true,'nhs'=>true,'emergency'=>false,'beds'=>0],
  ['id'=>21,'name'=>'Specsavers — Leicester','type'=>'optical','subtype'=>'Opticians','lat'=>52.6368,'lng'=>-1.1312,'address'=>'Gallowtree Gate, Leicester LE1 1ER','phone'=>'0116 251 7898','hours'=>'09:00-17:30','rating'=>4.2,'wait'=>'<10 min','distance'=>0.4,'open'=>true,'nhs'=>true,'emergency'=>false,'beds'=>0],

  // ── Blood / Testing ──────────────────────────────────────────────
  ['id'=>22,'name'=>'NHS Blood Donation Centre','type'=>'testing','subtype'=>'Blood Donation','lat'=>52.6344,'lng'=>-1.1224,'address'=>'Granby Street, Leicester LE1 6HD','phone'=>'0300 123 2323','hours'=>'10:00-19:00','rating'=>4.7,'wait'=>'<10 min','distance'=>0.8,'open'=>true,'nhs'=>true,'emergency'=>false,'beds'=>0],
  ['id'=>23,'name'=>'Nuffield Health Pathology','type'=>'testing','subtype'=>'Diagnostics Lab','lat'=>52.6222,'lng'=>-1.1180,'address'=>'Scraptoft Lane, Leicester LE5 1HY','phone'=>'0116 271 5800','hours'=>'08:00-18:00','rating'=>4.4,'wait'=>'Appointment','distance'=>2.8,'open'=>true,'nhs'=>false,'emergency'=>false,'beds'=>0],
];

// Heat map data (simulated disease density around Leicester)
$heatData = [
  [52.6360, -1.1390, 0.95], [52.6340, -1.1310, 0.85],
  [52.6380, -1.1280, 0.70], [52.6290, -1.1400, 0.80],
  [52.6420, -1.1200, 0.65], [52.6480, -1.1250, 0.60],
  [52.6150, -1.1450, 0.55], [52.6200, -1.1100, 0.72],
  [52.6310, -1.1550, 0.68], [52.6450, -1.1480, 0.58],
  [52.6270, -1.1320, 0.75], [52.6395, -1.1365, 0.88],
  [52.6330, -1.1250, 0.62], [52.6210, -1.1290, 0.67],
  [52.6500, -1.1350, 0.52], [52.6350, -1.1430, 0.77],
];

$typeMeta = [
  'hospital'  => ['icon'=>'🏥','color'=>'#1565C0','label'=>'Hospitals'],
  'gp'        => ['icon'=>'🩺','color'=>'#16A34A','label'=>'GP / Clinics'],
  'pharmacy'  => ['icon'=>'💊','color'=>'#0891B2','label'=>'Pharmacies'],
  'emergency' => ['icon'=>'🚨','color'=>'#DC2626','label'=>'Emergency'],
  'mental'    => ['icon'=>'🧠','color'=>'#7C3AED','label'=>'Mental Health'],
  'dental'    => ['icon'=>'🦷','color'=>'#D97706','label'=>'Dental'],
  'optical'   => ['icon'=>'👁','color'=>'#0891B2','label'=>'Optical'],
  'testing'   => ['icon'=>'🧪','color'=>'#16A34A','label'=>'Testing / Labs'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Health Map — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<!-- Leaflet MarkerCluster CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">

<style>
html, body { margin:0; padding:0; height:100%; overflow:hidden; }
.hs-main { height:100vh; overflow:hidden; }
.hs-content { padding:0 !important; height:calc(100vh - 64px); display:flex; overflow:hidden; }

/* Sidebar */
.map-sidebar {
  width:340px; flex-shrink:0;
  background:#fff; border-right:1px solid var(--hs-border);
  display:flex; flex-direction:column; height:100%; overflow:hidden;
}
.sidebar-search { padding:14px; border-bottom:1px solid var(--hs-border); }
.sidebar-filters { padding:10px 14px; border-bottom:1px solid var(--hs-border); display:flex; flex-wrap:wrap; gap:6px; }
.filter-chip {
  padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600;
  border:1.5px solid var(--hs-border); background:#fff; cursor:pointer;
  transition:var(--transition); white-space:nowrap;
}
.filter-chip:hover { border-color:var(--hs-blue); color:var(--hs-blue); }
.filter-chip.active { background:var(--hs-blue); border-color:var(--hs-blue); color:#fff; }
.facility-list { flex:1; overflow-y:auto; }

/* Facility card */
.facility-item {
  display:flex; align-items:flex-start; gap:12px;
  padding:12px 14px; border-bottom:1px solid var(--hs-border);
  cursor:pointer; transition:var(--transition);
}
.facility-item:hover { background:#F4F8FF; }
.facility-item.active { background:#EFF6FF; border-left:3px solid var(--hs-blue); }
.facility-icon {
  width:42px; height:42px; border-radius:10px;
  display:flex; align-items:center; justify-content:center;
  font-size:20px; flex-shrink:0;
}
.facility-name { font-weight:700; font-size:13px; color:var(--hs-navy); margin-bottom:2px; }
.facility-type { font-size:11px; font-weight:600; margin-bottom:4px; }
.facility-meta { font-size:11px; color:var(--hs-muted); display:flex; flex-direction:column; gap:2px; }
.open-badge { padding:2px 7px; border-radius:4px; font-size:10px; font-weight:700; }
.open-badge.open { background:#DCFCE7; color:#166534; }
.open-badge.closed { background:#FEE2E2; color:#991B1B; }
.wait-badge { padding:2px 7px; border-radius:4px; font-size:10px; font-weight:600; background:#DBEAFE; color:#1E40AF; }

/* Map container */
#healthMap { flex:1; height:100%; }

/* Leaflet custom popup */
.leaflet-popup-content-wrapper {
  border-radius:14px !important;
  box-shadow:0 8px 32px rgba(10,31,68,.18) !important;
  border:none !important;
  padding:0 !important;
  overflow:hidden;
}
.leaflet-popup-content { margin:0 !important; width:280px !important; }
.leaflet-popup-tip-container { display:none; }

/* Custom marker */
.custom-marker {
  width:36px; height:36px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  font-size:16px; border:3px solid #fff;
  box-shadow:0 3px 10px rgba(0,0,0,.25);
  cursor:pointer; transition:.2s;
}
.custom-marker:hover { transform:scale(1.15); }

/* Map controls overlay */
.map-controls {
  position:absolute; top:14px; right:14px; z-index:1000;
  display:flex; flex-direction:column; gap:8px;
}
.map-ctrl-btn {
  background:#fff; border:1px solid var(--hs-border);
  border-radius:10px; padding:8px 14px; font-size:12px;
  font-weight:600; cursor:pointer; display:flex; align-items:center;
  gap:6px; color:var(--hs-navy); box-shadow:var(--shadow-sm);
  transition:var(--transition); white-space:nowrap;
}
.map-ctrl-btn:hover { background:var(--hs-blue); color:#fff; border-color:var(--hs-blue); }
.map-ctrl-btn.active { background:var(--hs-blue); color:#fff; border-color:var(--hs-blue); }

/* Stats bar */
.map-stats {
  position:absolute; bottom:0; left:340px; right:0; z-index:1000;
  background:rgba(255,255,255,.95); backdrop-filter:blur(8px);
  border-top:1px solid var(--hs-border);
  padding:8px 20px; display:flex; gap:24px; align-items:center;
  font-size:12px;
}
.stat-pill {
  display:flex; align-items:center; gap:6px;
  font-weight:600; color:var(--hs-navy);
}

/* Rating stars */
.stars { color:#F59E0B; font-size:11px; }

/* NHS badge */
.nhs-badge {
  display:inline-flex; align-items:center; gap:4px;
  background:#003087; color:#fff;
  padding:2px 8px; border-radius:4px; font-size:10px; font-weight:700;
}

/* Locate pulse */
@keyframes locatePulse {
  0%   { box-shadow: 0 0 0 0 rgba(21,101,192,.6); }
  70%  { box-shadow: 0 0 0 14px rgba(21,101,192,0); }
  100% { box-shadow: 0 0 0 0 rgba(21,101,192,0); }
}
.locate-marker { animation: locatePulse 2s infinite; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">

  <!-- Topbar -->
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-map-marked-alt" style="color:var(--hs-blue);"></i> NHS Health Map</div>
      <div class="page-subtitle">Hospitals, GPs, Pharmacies &amp; Emergency centres near Leicester</div>
    </div>
    <div class="topbar-actions">
      <button id="locateBtn" class="btn-hs btn-primary-hs btn-sm-hs" onclick="locateMe()">
        <i class="fas fa-crosshairs"></i> Locate Me
      </button>
      <button class="btn-hs btn-outline-hs btn-sm-hs" onclick="toggleHeatmap()">
        <i class="fas fa-fire"></i> <span id="heatLabel">Show Heatmap</span>
      </button>
      <a href="appointments.php" class="btn-hs btn-outline-hs btn-sm-hs">
        <i class="fas fa-calendar-plus"></i> Book Appointment
      </a>
    </div>
  </div>

  <div class="hs-content" style="position:relative;">

    <!-- LEFT SIDEBAR -->
    <div class="map-sidebar">
      <!-- Live NHS Postcode Search -->
      <div style="padding:12px 14px;background:#EFF6FF;border-bottom:2px solid #005EB8;">
        <div style="font-size:11px;font-weight:700;color:#005EB8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">
          <img src="https://www.nhs.uk/nhschoicesContent/imagecontent/icons/apple-touch-icon.png" style="height:12px;vertical-align:middle;border-radius:2px;"> Live NHS Facility Search
        </div>
        <div style="display:flex;gap:6px;">
          <input type="text" id="postcodeInput" placeholder="Enter postcode e.g. LE1 7RH"
            class="form-control" style="font-size:13px;flex:1;"
            onkeydown="if(event.key==='Enter')searchByPostcode()">
          <button onclick="searchByPostcode()" id="postcodeBtn"
            style="background:#005EB8;color:#fff;border:none;border-radius:8px;padding:8px 14px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;">
            Search
          </button>
        </div>
        <div id="postcodeStatus" style="font-size:11px;color:#1d4ed8;margin-top:5px;"></div>
      </div>

      <!-- Local Search -->
      <div class="sidebar-search">
        <div class="input-icon-wrap">
          <i class="fas fa-search"></i>
          <input type="text" id="facilitySearch" placeholder="Filter results..."
            class="form-control" oninput="filterFacilities()">
        </div>
        <div style="font-size:11px;color:var(--hs-muted);margin-top:6px;">
          <i class="fas fa-map-marker-alt" style="color:var(--hs-blue);"></i>
          <span id="locationLabel">Leicester, UK</span>
          &nbsp;&middot;&nbsp; <span id="visibleCount"><?= count($facilities) ?></span> facilities
        </div>
      </div>

      <!-- Type filters -->
      <div class="sidebar-filters">
        <button class="filter-chip active" data-type="all" onclick="setFilter('all',this)">All</button>
        <?php foreach ($typeMeta as $type => $meta): ?>
        <button class="filter-chip" data-type="<?= $type ?>" onclick="setFilter('<?= $type ?>',this)"
          style="--fc:<?= $meta['color'] ?>;">
          <?= $meta['icon'] ?> <?= $meta['label'] ?>
        </button>
        <?php endforeach; ?>
      </div>

      <!-- Sort + NHS filter -->
      <div style="padding:8px 14px;border-bottom:1px solid var(--hs-border);display:flex;gap:8px;align-items:center;">
        <select id="sortSelect" onchange="sortFacilities()" class="form-select" style="font-size:12px;padding:5px 10px;flex:1;">
          <option value="distance">Sort: Nearest first</option>
          <option value="rating">Sort: Highest rated</option>
          <option value="name">Sort: A–Z</option>
          <option value="open">Sort: Open now</option>
        </select>
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;white-space:nowrap;cursor:pointer;">
          <input type="checkbox" id="nhsOnly" onchange="filterFacilities()" style="accent-color:var(--hs-blue);width:14px;height:14px;">
          NHS only
        </label>
      </div>

      <!-- Facility list -->
      <div class="facility-list" id="facilityList">
        <!-- Rendered by JS -->
      </div>
    </div>

    <!-- MAP -->
    <div style="flex:1;position:relative;height:100%;">
      <div id="healthMap"></div>

      <!-- Layer / controls overlay -->
      <div class="map-controls">
        <!-- Layer switcher -->
        <div style="background:#fff;border:1px solid var(--hs-border);border-radius:10px;overflow:hidden;box-shadow:var(--shadow-sm);">
          <button id="layerStreet" class="map-ctrl-btn active" onclick="switchLayer('street')" style="border-radius:10px 10px 0 0;border-bottom:1px solid var(--hs-border);">
            <i class="fas fa-map"></i> Street
          </button>
          <button id="layerSat" class="map-ctrl-btn" onclick="switchLayer('satellite')" style="border-radius:0;border-bottom:1px solid var(--hs-border);">
            <i class="fas fa-satellite"></i> Satellite
          </button>
          <button id="layerClean" class="map-ctrl-btn" onclick="switchLayer('clean')" style="border-radius:0 0 10px 10px;">
            <i class="fas fa-paint-brush"></i> Clean
          </button>
        </div>

        <!-- Emergency callout -->
        <div style="background:#DC2626;color:#fff;border-radius:10px;padding:10px 14px;font-size:12px;font-weight:700;text-align:center;cursor:pointer;box-shadow:var(--shadow-md);" onclick="filterType='emergency';filterFacilities();flyToEmergency()">
          🚨 Nearest A&amp;E<br>
          <span style="font-size:10px;font-weight:400;opacity:.85;">LRI — 0.8 mi &middot; ~45 min wait</span>
        </div>
      </div>

      <!-- Bottom stats bar -->
      <div class="map-stats">
        <div class="stat-pill"><span style="width:10px;height:10px;border-radius:50%;background:#1565C0;display:inline-block;"></span><?= count(array_filter($facilities,fn($f)=>$f['type']==='hospital')) ?> Hospitals</div>
        <div class="stat-pill"><span style="width:10px;height:10px;border-radius:50%;background:#16A34A;display:inline-block;"></span><?= count(array_filter($facilities,fn($f)=>$f['type']==='gp')) ?> GP Clinics</div>
        <div class="stat-pill"><span style="width:10px;height:10px;border-radius:50%;background:#0891B2;display:inline-block;"></span><?= count(array_filter($facilities,fn($f)=>$f['type']==='pharmacy')) ?> Pharmacies</div>
        <div class="stat-pill"><span style="width:10px;height:10px;border-radius:50%;background:#DC2626;display:inline-block;"></span><?= count(array_filter($facilities,fn($f)=>$f['emergency'])) ?> A&amp;E Open</div>
        <div class="stat-pill"><span style="width:10px;height:10px;border-radius:50%;background:#7C3AED;display:inline-block;"></span><?= count(array_filter($facilities,fn($f)=>$f['type']==='mental')) ?> Mental Health</div>
        <div style="margin-left:auto;font-size:11px;color:var(--hs-muted);">
          <i class="fas fa-sync fa-spin" id="mapLoadingIcon" style="display:none;"></i>
          Data: NHS Open Data &middot; OpenStreetMap
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ─── Facility data for JS ─────────────────────────────────────── -->
<script>
const FACILITIES  = <?= json_encode($facilities) ?>;
const HEAT_DATA   = <?= json_encode($heatData) ?>;
const TYPE_META   = <?= json_encode($typeMeta) ?>;
</script>

<!-- Leaflet + Plugins -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script src="../assets/js/main.js"></script>
<script>
// ── State ──────────────────────────────────────────────────────────
let map, markers = {}, clusterGroup, heatLayer;
let heatmapOn   = false;
let filterType  = 'all';
let filterQuery = '';
let nhsOnly     = false;
let activeFacId = null;

// Tile layers
const TILES = {
  street:    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution:'&copy; OpenStreetMap', maxZoom:19 }),
  satellite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution:'&copy; ESRI', maxZoom:19 }),
  clean:     L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', { attribution:'&copy; CartoDB', maxZoom:19 }),
};
let currentLayer = 'street';

// ── Init map ────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  map = L.map('healthMap', {
    center:  [52.6369, -1.1398],
    zoom:    14,
    zoomControl: true,
    attributionControl: true,
  });

  TILES.street.addTo(map);

  // Cluster group
  clusterGroup = L.markerClusterGroup({
    maxClusterRadius: 40,
    iconCreateFunction: (cluster) => {
      const n = cluster.getChildCount();
      return L.divIcon({
        html: `<div style="background:var(--hs-blue);color:#fff;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;border:3px solid #fff;box-shadow:0 3px 10px rgba(0,0,0,.3);">${n}</div>`,
        className: '', iconSize: [36,36], iconAnchor: [18,18],
      });
    },
  });
  map.addLayer(clusterGroup);

  // Heat layer (hidden initially)
  heatLayer = L.heatLayer(HEAT_DATA.map(d => [d[0],d[1],d[2]]), {
    radius: 35, blur: 22, maxZoom: 17,
    gradient: { 0.1:'#1565C0', 0.4:'#00B4D8', 0.65:'#16A34A', 0.85:'#D97706', 1.0:'#DC2626' },
  });

  // Add markers
  addAllMarkers();
  renderList();

  // Map click — close popups
  map.on('click', () => {
    document.querySelectorAll('.facility-item').forEach(el => el.classList.remove('active'));
    activeFacId = null;
  });
});

// ── Create custom marker icon ──────────────────────────────────────
function makeIcon(type, open) {
  const meta  = TYPE_META[type] || TYPE_META.hospital;
  const color = open ? meta.color : '#94A3B8';
  return L.divIcon({
    html: `<div class="custom-marker" style="background:${color};font-size:14px;">${meta.icon}</div>`,
    className: '', iconSize:[36,36], iconAnchor:[18,36], popupAnchor:[0,-38],
  });
}

// ── Add markers ────────────────────────────────────────────────────
function addAllMarkers() {
  clusterGroup.clearLayers();
  markers = {};
  const visible = getFilteredFacilities();

  visible.forEach(fac => {
    const marker = L.marker([fac.lat, fac.lng], { icon: makeIcon(fac.type, fac.open) });
    marker.bindPopup(buildPopup(fac), { maxWidth:290, closeButton:false });

    marker.on('click', () => {
      highlightListItem(fac.id);
      activeFacId = fac.id;
    });

    clusterGroup.addLayer(marker);
    markers[fac.id] = marker;
  });
}

// ── Popup HTML ─────────────────────────────────────────────────────
function buildPopup(fac) {
  const meta   = TYPE_META[fac.type] || TYPE_META.hospital;
  const oColor = fac.open ? '#16A34A' : '#DC2626';
  const oText  = fac.open ? 'Open Now' : 'Closed';
  const stars  = '★'.repeat(Math.round(fac.rating)) + '☆'.repeat(5-Math.round(fac.rating));
  const nhs    = fac.nhs ? `<span style="background:#003087;color:#fff;padding:1px 7px;border-radius:3px;font-size:10px;font-weight:700;">NHS</span>` : '';
  const emg    = fac.emergency ? `<span style="background:#DC2626;color:#fff;padding:1px 7px;border-radius:3px;font-size:10px;font-weight:700;">A&E</span>` : '';

  return `
  <div style="font-family:'Inter',sans-serif;">
    <div style="background:${meta.color};color:#fff;padding:14px 16px;">
      <div style="font-size:16px;font-weight:800;margin-bottom:2px;">${fac.icon||meta.icon} ${fac.name}</div>
      <div style="font-size:12px;opacity:.85;">${fac.subtype} &middot; ${fac.distance} mi away</div>
    </div>
    <div style="padding:14px 16px;">
      <div style="display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap;">
        <span style="background:${oColor}22;color:${oColor};padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;">● ${oText}</span>
        ${nhs} ${emg}
      </div>
      <div style="display:grid;gap:6px;font-size:12px;">
        <div style="display:flex;gap:8px;"><span style="width:16px;color:${meta.color};"><i class="fas fa-map-marker-alt"></i></span><span>${fac.address}</span></div>
        <div style="display:flex;gap:8px;"><span style="width:16px;color:${meta.color};"><i class="fas fa-phone"></i></span><span><a href="tel:${fac.phone}" style="color:${meta.color};font-weight:600;">${fac.phone}</a></span></div>
        <div style="display:flex;gap:8px;"><span style="width:16px;color:${meta.color};"><i class="fas fa-clock"></i></span><span>${fac.hours}</span></div>
        ${fac.wait !== 'N/A' && fac.wait !== 'Appointment' && fac.wait !== 'Immediate' && fac.wait !== '<10 min' && fac.wait !== '<5 min' ? `<div style="display:flex;gap:8px;"><span style="width:16px;color:${meta.color};"><i class="fas fa-hourglass-half"></i></span><span>Est. wait: <strong>${fac.wait}</strong></span></div>` : ''}
        ${fac.rating ? `<div style="display:flex;align-items:center;gap:6px;"><span style="color:#F59E0B;font-size:13px;">${stars}</span><span style="font-weight:700;">${fac.rating}</span></div>` : ''}
      </div>
      <div style="display:flex;gap:8px;margin-top:12px;">
        <a href="appointments.php" style="flex:1;text-align:center;background:${meta.color};color:#fff;padding:8px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;display:block;">
          <i class="fas fa-calendar-plus"></i> Book
        </a>
        <a href="https://www.google.com/maps/dir/?api=1&destination=${fac.lat},${fac.lng}" target="_blank" style="padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:12px;font-weight:600;color:#0A1F44;text-decoration:none;">
          <i class="fas fa-directions"></i> Directions
        </a>
      </div>
    </div>
  </div>`;
}

// ── Render sidebar list ────────────────────────────────────────────
function renderList() {
  const list = document.getElementById('facilityList');
  const visible = getFilteredFacilities();
  document.getElementById('visibleCount').textContent = visible.length;

  if (!visible.length) {
    list.innerHTML = `<div style="padding:40px;text-align:center;color:var(--hs-muted);">
      <i class="fas fa-search-minus" style="font-size:36px;opacity:.3;"></i>
      <p style="margin-top:12px;font-size:13px;">No facilities match your filters.</p>
    </div>`;
    return;
  }

  list.innerHTML = visible.map(fac => {
    const meta   = TYPE_META[fac.type] || TYPE_META.hospital;
    const stars  = '★'.repeat(Math.round(fac.rating));
    return `
    <div class="facility-item ${activeFacId===fac.id?'active':''}" id="listItem${fac.id}" onclick="focusFacility(${fac.id})">
      <div class="facility-icon" style="background:${meta.color}18;">
        <span>${meta.icon}</span>
      </div>
      <div style="flex:1;min-width:0;">
        <div class="facility-name">${fac.name}</div>
        <div class="facility-type" style="color:${meta.color};">${fac.subtype}</div>
        <div style="display:flex;gap:6px;align-items:center;margin-bottom:4px;">
          <span class="open-badge ${fac.open?'open':'closed'}">${fac.open?'Open':'Closed'}</span>
          ${fac.nhs ? '<span class="nhs-badge"><svg width="20" height="10" viewBox="0 0 300 150"><rect width="300" height="150" fill="#003087"/><text x="150" y="115" font-family="Arial" font-weight="900" font-size="100" fill="white" text-anchor="middle">NHS</text></svg></span>' : ''}
          ${fac.emergency ? '<span style="background:#FEE2E2;color:#DC2626;padding:2px 6px;border-radius:3px;font-size:10px;font-weight:700;">A&E</span>' : ''}
        </div>
        <div class="facility-meta">
          <span><i class="fas fa-route" style="width:13px;color:var(--hs-blue);"></i> ${fac.distance} mi &nbsp;&middot;&nbsp; <i class="fas fa-clock" style="width:13px;"></i> ${fac.hours}</span>
          ${fac.wait && fac.wait!=='N/A'?`<span><i class="fas fa-hourglass-half" style="width:13px;color:var(--hs-muted);"></i> Wait: <span class="wait-badge">${fac.wait}</span></span>`:''}
          ${fac.rating?`<span style="color:#F59E0B;">${stars}</span> <span style="font-size:11px;font-weight:700;">${fac.rating}</span>`:''}
        </div>
      </div>
      <div style="text-align:right;flex-shrink:0;">
        <div style="font-size:13px;font-weight:800;color:var(--hs-blue);">${fac.distance}mi</div>
        <div style="font-size:10px;color:var(--hs-muted);">away</div>
      </div>
    </div>`;
  }).join('');
}

// ── Focus on facility ───────────────────────────────────────────────
function focusFacility(id) {
  const fac = FACILITIES.find(f => f.id === id);
  if (!fac) return;
  activeFacId = id;
  map.flyTo([fac.lat, fac.lng], 16, { duration:1.2 });
  if (markers[id]) {
    setTimeout(() => { markers[id].openPopup(); }, 1000);
  }
  highlightListItem(id);
}

function highlightListItem(id) {
  document.querySelectorAll('.facility-item').forEach(el => el.classList.remove('active'));
  const item = document.getElementById('listItem'+id);
  if (item) { item.classList.add('active'); item.scrollIntoView({behavior:'smooth',block:'nearest'}); }
}

// ── Filters ────────────────────────────────────────────────────────
function getFilteredFacilities() {
  nhsOnly = document.getElementById('nhsOnly')?.checked;
  let list = [...FACILITIES];
  if (filterType !== 'all') list = list.filter(f => f.type === filterType);
  if (nhsOnly)               list = list.filter(f => f.nhs);
  if (filterQuery)           list = list.filter(f => f.name.toLowerCase().includes(filterQuery) || f.subtype.toLowerCase().includes(filterQuery));

  const sortBy = document.getElementById('sortSelect')?.value || 'distance';
  if (sortBy === 'rating')   list.sort((a,b) => b.rating - a.rating);
  if (sortBy === 'distance') list.sort((a,b) => a.distance - b.distance);
  if (sortBy === 'name')     list.sort((a,b) => a.name.localeCompare(b.name));
  if (sortBy === 'open')     list.sort((a,b) => (b.open?1:0) - (a.open?1:0));
  return list;
}

function setFilter(type, btn) {
  filterType = type;
  document.querySelectorAll('.filter-chip').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  addAllMarkers();
  renderList();
}

function filterFacilities() {
  filterQuery = document.getElementById('facilitySearch').value.toLowerCase();
  addAllMarkers();
  renderList();
}

function sortFacilities() { renderList(); }

// ── Layer switcher ─────────────────────────────────────────────────
function switchLayer(name) {
  if (currentLayer === name) return;
  TILES[currentLayer].remove();
  TILES[name].addTo(map);
  currentLayer = name;
  document.querySelectorAll('#layerStreet,#layerSat,#layerClean').forEach(b => b.classList.remove('active'));
  document.getElementById('layer' + name.charAt(0).toUpperCase() + name.slice(1)).classList.add('active');
}

// ── Heat map toggle ────────────────────────────────────────────────
function toggleHeatmap() {
  heatmapOn = !heatmapOn;
  if (heatmapOn) {
    heatLayer.addTo(map);
    document.getElementById('heatLabel').textContent = 'Hide Heatmap';
    showToast('Disease density heatmap enabled — hot spots = high risk areas', 'info');
  } else {
    heatLayer.remove();
    document.getElementById('heatLabel').textContent = 'Show Heatmap';
  }
}

// ── Locate me ──────────────────────────────────────────────────────
function locateMe() {
  const btn = document.getElementById('locateBtn');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Locating...';
  btn.disabled = true;

  map.locate({ setView:true, maxZoom:15 });

  map.on('locationfound', (e) => {
    L.circleMarker(e.latlng, {
      radius:10, color:'#fff', weight:3,
      fillColor:'#1565C0', fillOpacity:1,
      className:'locate-marker',
    }).addTo(map).bindPopup('<strong>You are here</strong><br>Accuracy: ±' + Math.round(e.accuracy) + 'm').openPopup();
    document.getElementById('locationLabel').textContent = 'Your location';
    btn.innerHTML = '<i class="fas fa-crosshairs"></i> Located';
    btn.disabled = false;
    showToast('Location found! Showing nearby NHS facilities.', 'success');
  });

  map.on('locationerror', () => {
    showToast('Location access denied. Showing Leicester centre.', 'error');
    btn.innerHTML = '<i class="fas fa-crosshairs"></i> Locate Me';
    btn.disabled = false;
  });
}

// ── Fly to emergency ───────────────────────────────────────────────
function flyToEmergency() {
  const ae = FACILITIES.find(f => f.emergency);
  if (ae) { setTimeout(()=>focusFacility(ae.id), 100); }
}

// ── Live NHS Postcode Search ────────────────────────────────────────
let liveMarkers = [];

function searchByPostcode() {
  const postcode = document.getElementById('postcodeInput').value.trim();
  if (!postcode) { showToast('Please enter a postcode', 'error'); return; }

  const btn    = document.getElementById('postcodeBtn');
  const status = document.getElementById('postcodeStatus');
  btn.textContent = '...';
  btn.disabled = true;
  status.textContent = 'Searching NHS facilities...';

  // Clear previous live markers
  liveMarkers.forEach(m => map.removeLayer(m));
  liveMarkers = [];

  fetch(`../api/nhs-hospitals.php?postcode=${encodeURIComponent(postcode)}&type=all`)
    .then(r => r.json())
    .then(data => {
      btn.textContent = 'Search';
      btn.disabled = false;

      if (data.error) {
        status.textContent = '⚠️ ' + data.error;
        return;
      }
      if (!data.results || !data.results.length) {
        status.textContent = 'No NHS facilities found nearby.';
        return;
      }

      // Fly map to postcode centre
      map.flyTo([data.center.lat, data.center.lng], 14, { duration: 1.2 });

      // Add your location marker
      const youMarker = L.circleMarker([data.center.lat, data.center.lng], {
        radius: 10, color: '#fff', weight: 3,
        fillColor: '#005EB8', fillOpacity: 1,
      }).addTo(map).bindPopup(`<strong>📍 ${data.postcode}</strong><br>Your search location`).openPopup();
      liveMarkers.push(youMarker);

      const typeIcons = { hospital:'🏥', gp:'🩺', pharmacy:'💊', other:'🏢' };
      const typeColors = { hospital:'#1565C0', gp:'#16A34A', pharmacy:'#0891B2', other:'#6b7280' };

      data.results.forEach(f => {
        const icon = L.divIcon({
          className: '',
          html: `<div style="background:${typeColors[f.type]||'#6b7280'};color:#fff;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-size:14px;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.3);">${typeIcons[f.type]||'🏢'}</div>`,
          iconSize: [32, 32], iconAnchor: [16, 16],
        });
        const popup = `
          <div style="min-width:200px;">
            <strong style="font-size:13px;">${f.name}</strong><br>
            ${f.nhs ? '<span style="background:#003087;color:#fff;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:700;">NHS</span> ' : ''}
            <span style="font-size:11px;color:#6b7280;text-transform:capitalize;">${f.type}</span><br>
            ${f.address ? `<span style="font-size:11px;">📍 ${f.address}</span><br>` : ''}
            ${f.phone ? `<span style="font-size:11px;">📞 <a href="tel:${f.phone}">${f.phone}</a></span><br>` : ''}
            ${f.hours ? `<span style="font-size:11px;">🕐 ${f.hours}</span><br>` : ''}
            <span style="font-size:11px;color:#005EB8;">📏 ${f.distance} km away</span>
            ${f.website ? `<br><a href="${f.website}" target="_blank" style="font-size:11px;color:#005EB8;">🔗 Website</a>` : ''}
          </div>`;
        const m = L.marker([f.lat, f.lng], { icon })
          .addTo(map)
          .bindPopup(popup);
        liveMarkers.push(m);
      });

      status.innerHTML = `<span style="color:#16A34A;">✓ Found ${data.results.length} NHS facilities near ${data.postcode}</span>`;
      showToast(`Found ${data.results.length} NHS facilities near ${data.postcode}`, 'success');
    })
    .catch(() => {
      btn.textContent = 'Search';
      btn.disabled = false;
      status.textContent = '⚠️ Search failed. Please try again.';
    });
}
</script>
</body>
</html>
