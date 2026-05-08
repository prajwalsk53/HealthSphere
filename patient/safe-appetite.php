<?php
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
$user = getCurrentUser();
$uid  = $user['id'];

// Load existing profile data
$foodAllergies = [];
try {
    $stmt = $pdo->prepare("SELECT allergen, severity FROM allergies WHERE patient_id=? AND allergy_type='food' AND is_active=1 ORDER BY allergen");
    $stmt->execute([$uid]);
    $foodAllergies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$intolerances = [];
try {
    $stmt = $pdo->prepare("SELECT intolerance, severity FROM food_intolerances WHERE patient_id=? AND is_active=1 ORDER BY intolerance");
    $stmt->execute([$uid]);
    $intolerances = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$dietPrefs = [];
try {
    $stmt = $pdo->prepare("SELECT preference FROM diet_preferences WHERE patient_id=? AND is_active=1");
    $stmt->execute([$uid]);
    $dietPrefs = array_column($stmt->fetchAll(), 'preference');
} catch (Exception $e) {}

$dislikes = [];
try {
    $stmt = $pdo->prepare("SELECT id, ingredient FROM ingredient_dislikes WHERE patient_id=? AND is_active=1 ORDER BY ingredient");
    $stmt->execute([$uid]);
    $dislikes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Scan history
$scanHistory = [];
try {
    $stmt = $pdo->prepare("SELECT id, product_name, scan_result, ai_summary, scanned_at FROM ingredient_scans WHERE patient_id=? ORDER BY scanned_at DESC LIMIT 10");
    $stmt->execute([$uid]);
    $scanHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$notifCount = getUnreadCount($pdo, $uid);
$msgCount   = getUnreadMessages($pdo, $uid);

// Profile completeness
$profileScore = 0;
if ($foodAllergies) $profileScore += 35;
if ($intolerances)  $profileScore += 25;
if ($dietPrefs)     $profileScore += 25;
if ($dislikes)      $profileScore += 15;

// Common allergen presets
$COMMON_ALLERGENS = [
    'Peanuts'         => 'fa-seedling',
    'Tree Nuts'       => 'fa-tree',
    'Milk / Dairy'    => 'fa-glass-milk',
    'Eggs'            => 'fa-egg',
    'Wheat / Gluten'  => 'fa-bread-slice',
    'Soy'             => 'fa-leaf',
    'Fish'            => 'fa-fish',
    'Shellfish'       => 'fa-shrimp',
    'Sesame'          => 'fa-circle-dot',
    'Mustard'         => 'fa-droplet',
    'Celery'          => 'fa-carrot',
    'Lupin'           => 'fa-spa',
    'Molluscs'        => 'fa-circle',
    'Sulphites'       => 'fa-flask',
];

$COMMON_INTOLERANCES = [
    'Lactose','Gluten','Fructose','Histamine','Caffeine',
    'Sorbitol','Salicylates','Artificial Sweeteners','MSG','Food Colourings',
];

$DIET_OPTIONS = [
    'Vegan','Vegetarian','Pescatarian','Flexitarian',
    'Keto','Paleo','Low FODMAP','Gluten-Free','Dairy-Free',
    'Halal','Kosher','Low Sodium','Low Sugar','High Protein',
];

// Index existing allergens for quick JS lookup
$allergenMap = [];
foreach ($foodAllergies as $a) {
    $allergenMap[strtolower($a['allergen'])] = $a['severity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Safe Appetite — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
/* ── Safe Appetite specific styles ───────────────────────── */
:root {
  --sa-green:  #16A34A;
  --sa-amber:  #D97706;
  --sa-red:    #DC2626;
  --sa-blue:   #1565C0;
}

/* Hero banner */
.sa-hero {
  background: linear-gradient(135deg, #0A1F44 0%, #1565C0 60%, #0284C7 100%);
  border-radius: 16px;
  padding: 28px 32px;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
  position: relative;
  overflow: hidden;
}
.sa-hero::before {
  content: '';
  position: absolute;
  right: -40px;
  top: -40px;
  width: 200px;
  height: 200px;
  border-radius: 50%;
  background: rgba(255,255,255,.06);
}
.sa-hero::after {
  content: '';
  position: absolute;
  right: 60px;
  bottom: -60px;
  width: 150px;
  height: 150px;
  border-radius: 50%;
  background: rgba(255,255,255,.04);
}
.sa-hero-icon {
  width: 56px; height: 56px;
  border-radius: 14px;
  background: rgba(255,255,255,.15);
  display: flex; align-items: center; justify-content: center;
  font-size: 26px;
  margin-right: 18px;
  flex-shrink: 0;
}
.sa-score-ring {
  width: 80px; height: 80px;
  border-radius: 50%;
  background: conic-gradient(rgba(255,255,255,.9) 0deg, rgba(255,255,255,.9) calc(var(--score) * 3.6deg), rgba(255,255,255,.15) calc(var(--score) * 3.6deg));
  display: flex; align-items: center; justify-content: center;
  position: relative;
  flex-shrink: 0;
}
.sa-score-ring::after {
  content: '';
  position: absolute;
  width: 62px; height: 62px;
  border-radius: 50%;
  background: #1565C0;
}
.sa-score-inner {
  position: relative; z-index: 1;
  text-align: center;
}

/* Tab system */
.sa-tabs {
  display: flex;
  gap: 4px;
  background: var(--hs-bg);
  border: 1px solid var(--hs-border);
  border-radius: 12px;
  padding: 5px;
  margin-bottom: 20px;
}
.sa-tab {
  flex: 1;
  padding: 10px 16px;
  border-radius: 9px;
  border: none;
  background: transparent;
  font-size: 13px;
  font-weight: 600;
  color: var(--hs-muted);
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  white-space: nowrap;
}
.sa-tab.active {
  background: var(--hs-blue);
  color: #fff;
  box-shadow: 0 2px 8px rgba(21,101,192,.35);
}
.sa-tab:not(.active):hover { background: #EFF6FF; color: var(--hs-blue); }
.sa-panel { display: none; }
.sa-panel.active { display: block; }

/* Allergen chips */
.allergen-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 10px;
}
.allergen-chip {
  border: 2px solid var(--hs-border);
  border-radius: 12px;
  padding: 12px 14px;
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  flex-direction: column;
  gap: 6px;
  background: #fff;
  position: relative;
}
.allergen-chip:hover { border-color: var(--hs-blue); background: #EFF6FF; }
.allergen-chip.selected-mild    { border-color: #22C55E; background: #F0FDF4; }
.allergen-chip.selected-moderate { border-color: #F59E0B; background: #FFFBEB; }
.allergen-chip.selected-severe  { border-color: var(--sa-red); background: #FEF2F2; }
.allergen-chip .chip-name { font-size: 12.5px; font-weight: 700; color: var(--hs-navy); }
.allergen-chip .chip-check {
  position: absolute; top: 8px; right: 8px;
  width: 18px; height: 18px; border-radius: 50%;
  display: none; align-items: center; justify-content: center;
  font-size: 10px; color: #fff;
}
.allergen-chip.selected-mild    .chip-check { display: flex; background: #22C55E; }
.allergen-chip.selected-moderate .chip-check { display: flex; background: #F59E0B; }
.allergen-chip.selected-severe  .chip-check { display: flex; background: var(--sa-red); }
.sev-btns { display: flex; gap: 4px; }
.sev-btn {
  padding: 2px 7px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 700;
  border: 1.5px solid var(--hs-border);
  background: #fff;
  cursor: pointer;
  transition: var(--transition);
  color: var(--hs-muted);
}
.sev-btn:hover { border-color: var(--hs-blue); color: var(--hs-blue); }
.sev-btn.active-mild     { background: #22C55E; color: #fff; border-color: #22C55E; }
.sev-btn.active-moderate { background: #F59E0B; color: #fff; border-color: #F59E0B; }
.sev-btn.active-severe   { background: var(--sa-red); color: #fff; border-color: var(--sa-red); }

/* Diet preference badges */
.diet-grid {
  display: flex; flex-wrap: wrap; gap: 8px;
}
.diet-badge {
  padding: 8px 16px;
  border-radius: 24px;
  border: 2px solid var(--hs-border);
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: var(--transition);
  background: #fff;
  color: var(--hs-text);
}
.diet-badge:hover { border-color: var(--sa-blue); color: var(--sa-blue); }
.diet-badge.active { border-color: var(--sa-blue); background: #DBEAFE; color: var(--sa-blue); }

/* Intolerance rows */
.intol-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  background: #fff;
  border: 1.5px solid var(--hs-border);
  border-radius: 10px;
  margin-bottom: 8px;
}
.intol-row label { flex: 1; font-size: 13px; font-weight: 600; color: var(--hs-navy); cursor: pointer; }
.intol-row input[type=checkbox] { width: 16px; height: 16px; cursor: pointer; accent-color: var(--hs-blue); }

/* Scanner area */
.scan-box {
  background: linear-gradient(135deg, #F0F9FF, #EFF6FF);
  border: 2px dashed #93C5FD;
  border-radius: 16px;
  padding: 28px;
  text-align: center;
  transition: var(--transition);
  margin-bottom: 16px;
}
.scan-box:hover { border-color: var(--sa-blue); background: #EFF6FF; }
.scan-result-box {
  border-radius: 16px;
  padding: 24px;
  margin-top: 20px;
  animation: fadeIn .4s ease;
}
@keyframes fadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }
.scan-result-box.result-safe    { background: #F0FDF4; border: 2px solid #86EFAC; }
.scan-result-box.result-caution { background: #FFFBEB; border: 2px solid #FCD34D; }
.scan-result-box.result-danger  { background: #FEF2F2; border: 2px solid #FCA5A5; }

.result-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 20px;
  border-radius: 30px;
  font-size: 15px;
  font-weight: 800;
  margin-bottom: 16px;
}
.result-badge.safe    { background: var(--sa-green); color: #fff; }
.result-badge.caution { background: var(--sa-amber); color: #fff; }
.result-badge.danger  { background: var(--sa-red);   color: #fff; }

.alert-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 10px;
  margin-bottom: 8px;
  font-size: 13px;
}
.alert-item.t-DANGER  { background: #FEF2F2; border-left: 4px solid var(--sa-red); }
.alert-item.t-CAUTION { background: #FFFBEB; border-left: 4px solid var(--sa-amber); }
.alert-item.t-INFO    { background: #EFF6FF; border-left: 4px solid #60A5FA; }
.alert-icon { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 13px; }
.t-DANGER .alert-icon  { background: var(--sa-red);   color: #fff; }
.t-CAUTION .alert-icon { background: var(--sa-amber); color: #fff; }
.t-INFO .alert-icon    { background: #60A5FA; color: #fff; }

/* History cards */
.hist-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  background: #fff;
  border: 1.5px solid var(--hs-border);
  border-radius: 12px;
  margin-bottom: 10px;
  transition: var(--transition);
}
.hist-card:hover { border-color: var(--hs-blue); box-shadow: 0 3px 12px rgba(10,31,68,.08); }
.hist-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
.hist-dot.safe    { background: var(--sa-green); }
.hist-dot.caution { background: var(--sa-amber); }
.hist-dot.danger  { background: var(--sa-red); }

/* Scanning spinner */
.scan-spinner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding: 40px 20px;
}
.spin-ring {
  width: 54px; height: 54px;
  border: 4px solid #DBEAFE;
  border-top-color: var(--sa-blue);
  border-radius: 50%;
  animation: spinIt 0.8s linear infinite;
}
@keyframes spinIt { to { transform: rotate(360deg); } }

/* Dislike tags */
.dislike-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  background: #F3F4F6;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  color: var(--hs-text);
  margin: 3px;
}
.dislike-tag button {
  background: none; border: none; cursor: pointer;
  color: #9CA3AF; font-size: 13px; padding: 0; line-height: 1;
}
.dislike-tag button:hover { color: var(--sa-red); }

/* Safe highlights */
.safe-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 12px;
  background: #DCFCE7;
  border-radius: 20px;
  font-size: 11.5px;
  font-weight: 600;
  color: #166534;
  margin: 2px;
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">

  <!-- Topbar -->
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-shield-heart" style="color:#16A34A;"></i> Safe Appetite</div>
      <div class="page-subtitle">AI-powered food safety · Ingredient scanning · Personalised alerts</div>
    </div>
    <div class="topbar-actions">
      <span id="savingSpinner" style="display:none;font-size:12px;color:var(--hs-muted);align-items:center;gap:6px;">
        <i class="fas fa-circle-notch fa-spin" style="color:var(--hs-blue);"></i> Saving…
      </span>
      <button class="btn-hs btn-primary-hs btn-sm-hs" id="saveProfileBtn" onclick="saveProfile()">
        <i class="fas fa-save"></i> Save Profile
      </button>
    </div>
  </div>

  <div class="hs-content">

    <!-- Hero banner -->
    <div class="sa-hero">
      <div style="display:flex;align-items:center;gap:0;flex:1;">
        <div class="sa-hero-icon">🛡️</div>
        <div>
          <div style="font-size:22px;font-weight:900;letter-spacing:-.3px;">Your Food Safety Hub</div>
          <div style="font-size:13px;color:rgba(255,255,255,.7);margin-top:4px;max-width:540px;">
            Set your allergies, intolerances &amp; dietary preferences once. Then scan any food label instantly — AI checks every ingredient against your personal profile and warns you before you eat.
          </div>
          <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap;">
            <span style="background:rgba(255,255,255,.15);border-radius:20px;padding:5px 14px;font-size:11.5px;font-weight:700;"><i class="fas fa-ban"></i> <?= count($foodAllergies) ?> Food Allergies</span>
            <span style="background:rgba(255,255,255,.15);border-radius:20px;padding:5px 14px;font-size:11.5px;font-weight:700;"><i class="fas fa-triangle-exclamation"></i> <?= count($intolerances) ?> Intolerances</span>
            <span style="background:rgba(255,255,255,.15);border-radius:20px;padding:5px 14px;font-size:11.5px;font-weight:700;"><i class="fas fa-leaf"></i> <?= count($dietPrefs) ?> Diet Prefs</span>
            <span style="background:rgba(255,255,255,.15);border-radius:20px;padding:5px 14px;font-size:11.5px;font-weight:700;"><i class="fas fa-barcode"></i> <?= count($scanHistory) ?> Scans Done</span>
          </div>
        </div>
      </div>
      <div style="text-align:center;flex-shrink:0;margin-left:24px;">
        <div class="sa-score-ring" style="--score:<?= $profileScore ?>">
          <div class="sa-score-inner">
            <div style="font-size:18px;font-weight:900;color:#fff;"><?= $profileScore ?>%</div>
            <div style="font-size:9px;color:rgba(255,255,255,.7);font-weight:600;">PROFILE</div>
          </div>
        </div>
        <div style="font-size:11px;color:rgba(255,255,255,.6);margin-top:6px;">Profile complete</div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="sa-tabs">
      <button class="sa-tab active" onclick="switchTab('scanner')" id="tab-scanner">
        <i class="fas fa-magnifying-glass"></i> Ingredient Scanner
      </button>
      <button class="sa-tab" onclick="switchTab('allergies')" id="tab-allergies">
        <i class="fas fa-ban"></i> Allergies
        <?php if ($foodAllergies): ?><span style="background:rgba(220,38,38,.15);color:#DC2626;border-radius:10px;padding:1px 7px;font-size:10px;"><?= count($foodAllergies) ?></span><?php endif; ?>
      </button>
      <button class="sa-tab" onclick="switchTab('intolerances')" id="tab-intolerances">
        <i class="fas fa-triangle-exclamation"></i> Intolerances
      </button>
      <button class="sa-tab" onclick="switchTab('diet')" id="tab-diet">
        <i class="fas fa-leaf"></i> Diet &amp; Dislikes
      </button>
      <button class="sa-tab" onclick="switchTab('history')" id="tab-history">
        <i class="fas fa-clock-rotate-left"></i> Scan History
        <?php if ($scanHistory): ?><span style="background:#DBEAFE;color:var(--hs-blue);border-radius:10px;padding:1px 7px;font-size:10px;"><?= count($scanHistory) ?></span><?php endif; ?>
      </button>
    </div>

    <!-- ═══════════════════════════════════════════════
         PANEL 1: INGREDIENT SCANNER
    ═══════════════════════════════════════════════════ -->
    <div class="sa-panel active" id="panel-scanner">
      <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;">

        <!-- Scanner left -->
        <div>
          <div class="hs-card">
            <div class="hs-card-header">
              <span class="card-title"><i class="fas fa-magnifying-glass-plus" style="color:var(--sa-blue);"></i> Scan Ingredients</span>
              <span style="font-size:11px;background:#DBEAFE;color:var(--sa-blue);padding:3px 10px;border-radius:20px;font-weight:700;"><i class="fas fa-shield-heart"></i> Smart Scan</span>
            </div>
            <div class="hs-card-body">

              <!-- Product name -->
              <div style="margin-bottom:14px;">
                <label class="form-label">Product Name <span style="color:var(--hs-muted);font-weight:400;">(optional)</span></label>
                <input type="text" id="productName" class="form-control" placeholder="e.g. Cadbury Dairy Milk, Kellogg's Corn Flakes…">
              </div>

              <!-- Ingredients textarea -->
              <div class="scan-box" id="scanDropZone">
                <i class="fas fa-list-ul" style="font-size:28px;color:#93C5FD;margin-bottom:10px;"></i>
                <div style="font-size:14px;font-weight:700;color:var(--hs-navy);margin-bottom:6px;">Paste Ingredients List</div>
                <div style="font-size:12px;color:var(--hs-muted);margin-bottom:14px;">Copy from a food label or packaging</div>
                <textarea id="ingredientsInput"
                  rows="5"
                  style="width:100%;border:1.5px solid var(--hs-border);border-radius:10px;padding:12px;font-size:13px;font-family:inherit;resize:vertical;background:#fff;outline:none;"
                  placeholder="e.g. Wheat flour, Sugar, Palm oil, Cocoa (3%), Skimmed milk powder, Emulsifier (lecithin), Natural vanilla flavouring…"></textarea>
              </div>

              <!-- Quick examples -->
              <div style="margin-bottom:16px;">
                <div style="font-size:11px;font-weight:700;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px;">Quick Examples</div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                  <button class="quick-pill" onclick="loadExample('chocolate')">🍫 Chocolate Bar</button>
                  <button class="quick-pill" onclick="loadExample('bread')">🍞 White Bread</button>
                  <button class="quick-pill" onclick="loadExample('cereal')">🥣 Breakfast Cereal</button>
                  <button class="quick-pill" onclick="loadExample('yogurt')">🥛 Greek Yogurt</button>
                  <button class="quick-pill" onclick="loadExample('crisp')">🥔 Crisps / Chips</button>
                  <button class="quick-pill" onclick="loadExample('sauce')">🫙 Pasta Sauce</button>
                </div>
              </div>

              <div style="display:flex;gap:12px;">
                <button class="btn-hs btn-primary-hs" style="flex:1;justify-content:center;" onclick="runScan()">
                  <i class="fas fa-shield-heart"></i> Scan Ingredients
                </button>
                <button class="btn-hs btn-outline-hs" onclick="clearScan()">
                  <i class="fas fa-rotate-left"></i> Clear
                </button>
              </div>
            </div>
          </div>

          <!-- Result area -->
          <div id="scanResultArea"></div>
        </div>

        <!-- Right: Your active profile summary -->
        <div>
          <div class="hs-card" style="margin-bottom:16px;">
            <div class="hs-card-header"><span class="card-title"><i class="fas fa-id-card-clip"></i> Your Safety Profile</span></div>
            <div class="hs-card-body">
              <?php if ($foodAllergies): ?>
              <div style="margin-bottom:12px;">
                <div style="font-size:10.5px;font-weight:700;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;"><i class="fas fa-ban" style="color:var(--sa-red);"></i> ALLERGIES</div>
                <?php foreach ($foodAllergies as $a): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 10px;background:<?= $a['severity']==='severe' ? '#FEF2F2' : ($a['severity']==='moderate'?'#FFFBEB':'#F0FDF4') ?>;border-radius:8px;margin-bottom:4px;">
                  <span style="font-size:12.5px;font-weight:700;color:var(--hs-navy);"><?= e($a['allergen']) ?></span>
                  <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;background:<?= $a['severity']==='severe'?'#DC2626':($a['severity']==='moderate'?'#D97706':'#16A34A') ?>;color:#fff;"><?= strtoupper($a['severity']) ?></span>
                </div>
                <?php endforeach; ?>
              </div>
              <?php else: ?>
              <div style="font-size:12px;color:var(--hs-muted);padding:10px;background:#FEF9C3;border-radius:8px;margin-bottom:12px;"><i class="fas fa-circle-exclamation" style="color:#D97706;"></i> No allergies set — <a href="#" onclick="switchTab('allergies');return false;" style="color:var(--hs-blue);">add them</a></div>
              <?php endif; ?>

              <?php if ($intolerances): ?>
              <div style="margin-bottom:12px;">
                <div style="font-size:10.5px;font-weight:700;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;"><i class="fas fa-triangle-exclamation" style="color:var(--sa-amber);"></i> INTOLERANCES</div>
                <div style="display:flex;flex-wrap:wrap;gap:4px;">
                  <?php foreach ($intolerances as $i): ?>
                  <span style="background:#FFFBEB;border:1px solid #FCD34D;border-radius:8px;padding:3px 10px;font-size:11.5px;font-weight:600;color:#92400E;"><?= e($i['intolerance']) ?></span>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>

              <?php if ($dietPrefs): ?>
              <div>
                <div style="font-size:10.5px;font-weight:700;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;"><i class="fas fa-leaf" style="color:var(--sa-green);"></i> DIET PREFERENCES</div>
                <div style="display:flex;flex-wrap:wrap;gap:4px;">
                  <?php foreach ($dietPrefs as $p): ?>
                  <span style="background:#DCFCE7;border:1px solid #86EFAC;border-radius:8px;padding:3px 10px;font-size:11.5px;font-weight:600;color:#166534;"><?= e($p) ?></span>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>

              <?php if (!$foodAllergies && !$intolerances && !$dietPrefs): ?>
              <div style="text-align:center;padding:20px 10px;">
                <div style="font-size:32px;margin-bottom:10px;">👤</div>
                <div style="font-size:13px;font-weight:700;color:var(--hs-navy);margin-bottom:6px;">Profile Empty</div>
                <div style="font-size:12px;color:var(--hs-muted);">Set your allergies and preferences in the tabs above so the AI can protect you during scans.</div>
                <button class="btn-hs btn-primary-hs btn-sm-hs" style="margin-top:12px;" onclick="switchTab('allergies')"><i class="fas fa-plus"></i> Set Up Profile</button>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- How it works -->
          <div class="hs-card">
            <div class="hs-card-header"><span class="card-title"><i class="fas fa-circle-info"></i> How It Works</span></div>
            <div class="hs-card-body">
              <div style="display:flex;flex-direction:column;gap:12px;">
                <?php foreach ([
                  ['1','Paste the ingredients list from any food packaging into the scanner.','#1565C0'],
                  ['2','AI cross-checks every ingredient against your allergies, intolerances &amp; diet preferences — including hidden ingredient names.','#16A34A'],
                  ['3','Get an instant SAFE / CAUTION / DANGER result with specific alerts for anything flagged.','#D97706'],
                  ['4','Your scan history is saved so you can quickly check products you\'ve scanned before.','#7C3AED'],
                ] as $step): ?>
                <div style="display:flex;gap:12px;align-items:flex-start;">
                  <div style="width:26px;height:26px;border-radius:50%;background:<?= $step[2] ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0;"><?= $step[0] ?></div>
                  <div style="font-size:12.5px;color:var(--hs-text);line-height:1.5;"><?= $step[1] ?></div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         PANEL 2: FOOD ALLERGIES
    ═══════════════════════════════════════════════════ -->
    <div class="sa-panel" id="panel-allergies">
      <div class="hs-card">
        <div class="hs-card-header">
          <span class="card-title"><i class="fas fa-ban" style="color:var(--sa-red);"></i> Food Allergies</span>
          <span style="font-size:12px;color:var(--hs-muted);">Select all that apply and set severity</span>
        </div>
        <div class="hs-card-body">
          <div style="background:#FEF2F2;border:1px solid #FCA5A5;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#991B1B;">
            <i class="fas fa-circle-exclamation"></i> <strong>Important:</strong> This information helps the AI warn you about food products. Always consult your doctor for medical diagnosis.
          </div>

          <div style="font-size:13px;font-weight:600;color:var(--hs-navy);margin-bottom:14px;">Common Food Allergens (Big 14)</div>
          <div class="allergen-grid" id="allergenGrid">
            <?php foreach ($COMMON_ALLERGENS as $name => $icon):
              $key = strtolower($name);
              $sev = null;
              foreach ($foodAllergies as $a) {
                if (strtolower($a['allergen']) === $key) { $sev = $a['severity']; break; }
              }
              $selClass = $sev ? "selected-$sev" : '';
            ?>
            <div class="allergen-chip <?= $selClass ?>" id="chip-<?= htmlspecialchars($key, ENT_QUOTES) ?>" data-allergen="<?= e($name) ?>" onclick="toggleAllergen(this)">
              <div class="chip-check"><i class="fas fa-check"></i></div>
              <div style="display:flex;align-items:center;gap:8px;">
                <i class="fas <?= $icon ?>" style="font-size:18px;color:var(--hs-blue);"></i>
                <div class="chip-name"><?= e($name) ?></div>
              </div>
              <div class="sev-btns">
                <button class="sev-btn <?= $sev==='mild'?'active-mild':'' ?>" onclick="setSev(event,this,'mild')">Mild</button>
                <button class="sev-btn <?= $sev==='moderate'?'active-moderate':'' ?>" onclick="setSev(event,this,'moderate')">Moderate</button>
                <button class="sev-btn <?= $sev==='severe'?'active-severe':'' ?>" onclick="setSev(event,this,'severe')">Severe</button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Custom allergy add -->
          <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--hs-border);">
            <div style="font-size:13px;font-weight:600;color:var(--hs-navy);margin-bottom:12px;">Add a Custom Allergy</div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
              <input type="text" id="customAllergenInput" class="form-control" style="flex:1;min-width:200px;" placeholder="e.g. Kiwi, Latex, Pine nuts…">
              <select id="customAllergenSev" class="form-select" style="width:130px;">
                <option value="mild">Mild</option>
                <option value="moderate" selected>Moderate</option>
                <option value="severe">Severe</option>
              </select>
              <button class="btn-hs btn-primary-hs" onclick="addCustomAllergen()"><i class="fas fa-plus"></i> Add</button>
            </div>
            <!-- Custom allergens list -->
            <div id="customAllergenList" style="margin-top:14px;display:flex;flex-wrap:wrap;gap:8px;">
              <?php foreach ($foodAllergies as $a):
                $isPreset = false;
                foreach (array_keys($COMMON_ALLERGENS) as $preset) {
                  if (strtolower($preset) === strtolower($a['allergen'])) { $isPreset = true; break; }
                }
                if (!$isPreset):
              ?>
              <div class="dislike-tag" id="custom-<?= e(strtolower($a['allergen'])) ?>">
                <i class="fas fa-ban" style="color:var(--sa-red);font-size:10px;"></i>
                <?= e($a['allergen']) ?>
                <span style="font-size:10px;padding:1px 6px;background:<?= $a['severity']==='severe'?'#DC2626':($a['severity']==='moderate'?'#D97706':'#16A34A') ?>;color:#fff;border-radius:8px;"><?= $a['severity'] ?></span>
                <button onclick="removeCustomAllergen(this, '<?= e($a['allergen']) ?>')">×</button>
              </div>
              <?php endif; endforeach; ?>
            </div>
          </div>

          <div style="margin-top:20px;">
            <button class="btn-hs btn-primary-hs" onclick="saveProfile()"><i class="fas fa-save"></i> Save Allergies</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         PANEL 3: INTOLERANCES
    ═══════════════════════════════════════════════════ -->
    <div class="sa-panel" id="panel-intolerances">
      <div class="hs-card">
        <div class="hs-card-header">
          <span class="card-title"><i class="fas fa-triangle-exclamation" style="color:var(--sa-amber);"></i> Food Intolerances</span>
          <span style="font-size:12px;color:var(--hs-muted);">Intolerances cause discomfort but not severe allergic reactions</span>
        </div>
        <div class="hs-card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;" id="intoleranceGrid">
            <?php foreach ($COMMON_INTOLERANCES as $intol):
              $existing = null;
              foreach ($intolerances as $i) {
                if (strtolower($i['intolerance']) === strtolower($intol)) { $existing = $i; break; }
              }
              $checked = $existing !== null;
              $sev = $existing['severity'] ?? 'moderate';
            ?>
            <div class="intol-row">
              <input type="checkbox" id="intol-<?= e(strtolower(str_replace(' ','-',$intol))) ?>"
                data-intol="<?= e($intol) ?>"
                class="intol-cb"
                <?= $checked ? 'checked' : '' ?>>
              <label for="intol-<?= e(strtolower(str_replace(' ','-',$intol))) ?>"><?= e($intol) ?></label>
              <div style="display:flex;gap:4px;">
                <button class="sev-btn <?= ($sev==='mild'&&$checked)?'active-mild':'' ?>" data-level="mild"
                  onclick="setIntolSev(this,'<?= e($intol) ?>','mild')">Mild</button>
                <button class="sev-btn <?= ($sev==='moderate'&&$checked)?'active-moderate':'' ?>" data-level="moderate"
                  onclick="setIntolSev(this,'<?= e($intol) ?>','moderate')">Mod</button>
                <button class="sev-btn <?= ($sev==='severe'&&$checked)?'active-severe':'' ?>" data-level="severe"
                  onclick="setIntolSev(this,'<?= e($intol) ?>','severe')">Sev</button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <div style="margin-top:20px;">
            <button class="btn-hs btn-primary-hs" onclick="saveProfile()"><i class="fas fa-save"></i> Save Intolerances</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         PANEL 4: DIET PREFERENCES & DISLIKES
    ═══════════════════════════════════════════════════ -->
    <div class="sa-panel" id="panel-diet">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

        <!-- Dietary Preferences -->
        <div class="hs-card">
          <div class="hs-card-header">
            <span class="card-title"><i class="fas fa-leaf" style="color:var(--sa-green);"></i> Dietary Preferences</span>
          </div>
          <div class="hs-card-body">
            <p style="font-size:13px;color:var(--hs-muted);margin-bottom:16px;">Select all that apply to your diet. The AI will warn if a scanned product conflicts with these.</p>
            <div class="diet-grid" id="dietGrid">
              <?php foreach ($DIET_OPTIONS as $opt): ?>
              <button class="diet-badge <?= in_array($opt, $dietPrefs) ? 'active' : '' ?>"
                data-pref="<?= e($opt) ?>"
                onclick="toggleDiet(this)">
                <?= e($opt) ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Ingredient Dislikes -->
        <div class="hs-card">
          <div class="hs-card-header">
            <span class="card-title"><i class="fas fa-thumbs-down" style="color:var(--hs-muted);"></i> Ingredient Dislikes</span>
          </div>
          <div class="hs-card-body">
            <p style="font-size:13px;color:var(--hs-muted);margin-bottom:16px;">Ingredients you personally dislike. The AI will flag these as informational (not a safety warning).</p>
            <div style="display:flex;gap:8px;margin-bottom:14px;">
              <input type="text" id="dislikeInput" class="form-control" placeholder="e.g. Cilantro, Anchovies, Olives…"
                onkeydown="if(event.key==='Enter'){event.preventDefault();addDislike();}">
              <button class="btn-hs btn-primary-hs btn-sm-hs" onclick="addDislike()"><i class="fas fa-plus"></i></button>
            </div>
            <div id="dislikeList">
              <?php foreach ($dislikes as $d): ?>
              <span class="dislike-tag" id="dl-<?= $d['id'] ?>">
                <?= e($d['ingredient']) ?>
                <button onclick="removeDislike(this, <?= $d['id'] ?>, '<?= e($d['ingredient']) ?>')">×</button>
              </span>
              <?php endforeach; ?>
            </div>
            <?php if (!$dislikes): ?>
            <div style="font-size:12px;color:var(--hs-muted);font-style:italic;">No dislikes added yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div style="margin-top:16px;">
        <button class="btn-hs btn-primary-hs" onclick="saveProfile()"><i class="fas fa-save"></i> Save Diet Preferences &amp; Dislikes</button>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         PANEL 5: SCAN HISTORY
    ═══════════════════════════════════════════════════ -->
    <div class="sa-panel" id="panel-history">
      <div class="hs-card">
        <div class="hs-card-header">
          <span class="card-title"><i class="fas fa-clock-rotate-left"></i> Recent Scans</span>
          <span style="font-size:12px;color:var(--hs-muted);">Last 10 scans saved</span>
        </div>
        <div class="hs-card-body">
          <?php if ($scanHistory): ?>
          <?php foreach ($scanHistory as $scan): ?>
          <div class="hist-card" id="scan-<?= $scan['id'] ?>">
            <div class="hist-dot <?= $scan['scan_result'] ?>"></div>
            <div style="flex:1;">
              <div style="font-size:13.5px;font-weight:700;color:var(--hs-navy);"><?= e($scan['product_name'] ?: 'Unknown Product') ?></div>
              <?php if ($scan['ai_summary']): ?>
              <div style="font-size:12px;color:var(--hs-muted);margin-top:3px;line-height:1.4;"><?= e(substr($scan['ai_summary'], 0, 120)) ?>...</div>
              <?php endif; ?>
              <div style="font-size:11px;color:var(--hs-muted);margin-top:4px;"><i class="fas fa-clock" style="font-size:10px;"></i> <?= date('D d M Y, H:i', strtotime($scan['scanned_at'])) ?></div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
              <span class="result-badge <?= $scan['scan_result'] ?>" style="padding:5px 14px;font-size:12px;">
                <?php if ($scan['scan_result']==='safe'): ?>
                  <i class="fas fa-check-circle"></i> SAFE
                <?php elseif ($scan['scan_result']==='caution'): ?>
                  <i class="fas fa-triangle-exclamation"></i> CAUTION
                <?php else: ?>
                  <i class="fas fa-xmark-circle"></i> DANGER
                <?php endif; ?>
              </span>
              <button onclick="deleteScan(<?= $scan['id'] ?>)" style="background:none;border:none;cursor:pointer;color:#9CA3AF;font-size:14px;" title="Delete scan">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
          <?php else: ?>
          <div style="text-align:center;padding:40px 20px;">
            <div style="font-size:48px;margin-bottom:14px;">🔍</div>
            <div style="font-size:15px;font-weight:700;color:var(--hs-navy);margin-bottom:8px;">No Scans Yet</div>
            <div style="font-size:13px;color:var(--hs-muted);margin-bottom:16px;">Use the Ingredient Scanner to analyse food products. Your scan history will appear here.</div>
            <button class="btn-hs btn-primary-hs" onclick="switchTab('scanner')"><i class="fas fa-magnifying-glass"></i> Start Scanning</button>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div><!-- /hs-content -->
</div><!-- /hs-main -->

<script src="../assets/js/main.js"></script>
<script>
// ── State ────────────────────────────────────────────────────────────
// Allergies: { allergen => severity } (includes preset chips + custom)
const allergyState = {};
<?php foreach ($foodAllergies as $a): ?>
allergyState['<?= addslashes(strtolower($a['allergen'])) ?>'] = { allergen: '<?= addslashes($a['allergen']) ?>', severity: '<?= $a['severity'] ?>' };
<?php endforeach; ?>

// Intolerances: { name => severity }
const intoleranceState = {};
<?php foreach ($intolerances as $i): ?>
intoleranceState['<?= addslashes(strtolower($i['intolerance'])) ?>'] = { name: '<?= addslashes($i['intolerance']) ?>', severity: '<?= $i['severity'] ?>' };
<?php endforeach; ?>

// Diet prefs: Set of strings
const dietPrefState = new Set(<?= json_encode($dietPrefs) ?>);

// Dislikes: array of strings
const dislikeState = <?= json_encode(array_column($dislikes, 'ingredient')) ?>;

// ── Tab switching ────────────────────────────────────────────────────
function switchTab(tab) {
  document.querySelectorAll('.sa-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.sa-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  document.getElementById('panel-' + tab).classList.add('active');
}

// ── Allergen chip toggle ─────────────────────────────────────────────
function toggleAllergen(chip) {
  const name = chip.dataset.allergen;
  const key  = name.toLowerCase();
  if (allergyState[key]) {
    // Already selected — deselect
    delete allergyState[key];
    chip.className = 'allergen-chip';
    chip.querySelectorAll('.sev-btn').forEach(b => {
      b.classList.remove('active-mild','active-moderate','active-severe');
    });
  } else {
    // Select with moderate default
    allergyState[key] = { allergen: name, severity: 'moderate' };
    chip.className = 'allergen-chip selected-moderate';
    chip.querySelectorAll('.sev-btn').forEach(b => {
      b.classList.remove('active-mild','active-moderate','active-severe');
      if (b.textContent.trim().toLowerCase() === 'moderate') b.classList.add('active-moderate');
    });
  }
}

function setSev(event, btn, level) {
  event.stopPropagation();
  const chip = btn.closest('.allergen-chip');
  const name = chip.dataset.allergen;
  const key  = name.toLowerCase();

  // Ensure it's selected
  if (!allergyState[key]) allergyState[key] = { allergen: name, severity: level };
  allergyState[key].severity = level;

  // Update chip class
  chip.className = 'allergen-chip selected-' + level;

  // Update sev buttons in this chip
  chip.querySelectorAll('.sev-btn').forEach(b => {
    b.classList.remove('active-mild','active-moderate','active-severe');
  });
  btn.classList.add('active-' + level);
}

// ── Custom allergen ──────────────────────────────────────────────────
function addCustomAllergen() {
  const input = document.getElementById('customAllergenInput');
  const sev   = document.getElementById('customAllergenSev').value;
  const name  = input.value.trim();
  if (!name) return;
  const key = name.toLowerCase();

  // Check not already a preset chip
  if (document.getElementById('chip-' + key)) {
    const chip = document.getElementById('chip-' + key);
    allergyState[key] = { allergen: name, severity: sev };
    chip.className = 'allergen-chip selected-' + sev;
    chip.querySelectorAll('.sev-btn').forEach(b => {
      b.classList.remove('active-mild','active-moderate','active-severe');
      if (b.textContent.trim().toLowerCase() === sev) b.classList.add('active-' + sev);
    });
    input.value = '';
    return;
  }

  allergyState[key] = { allergen: name, severity: sev };

  const tag = document.createElement('div');
  tag.className = 'dislike-tag';
  tag.id = 'custom-' + key;
  tag.innerHTML = `<i class="fas fa-ban" style="color:var(--sa-red);font-size:10px;"></i> ${name}
    <span style="font-size:10px;padding:1px 6px;background:${sev==='severe'?'#DC2626':sev==='moderate'?'#D97706':'#16A34A'};color:#fff;border-radius:8px;">${sev}</span>
    <button onclick="removeCustomAllergen(this,'${name.replace(/'/g,"\\'")}')">×</button>`;
  document.getElementById('customAllergenList').appendChild(tag);
  input.value = '';
}

function removeCustomAllergen(btn, name) {
  const key = name.toLowerCase();
  delete allergyState[key];
  btn.closest('.dislike-tag')?.remove();
}

// ── Intolerance severity ─────────────────────────────────────────────
function setIntolSev(btn, name, level) {
  const row = btn.closest('.intol-row');
  const cb  = row.querySelector('.intol-cb');
  cb.checked = true;
  intoleranceState[name.toLowerCase()] = { name, severity: level };

  row.querySelectorAll('.sev-btn').forEach(b => {
    b.classList.remove('active-mild','active-moderate','active-severe');
  });
  btn.classList.add('active-' + level);
}

// Sync checkbox state with intoleranceState
document.querySelectorAll('.intol-cb').forEach(cb => {
  cb.addEventListener('change', () => {
    const name = cb.dataset.intol;
    const key  = name.toLowerCase();
    const row  = cb.closest('.intol-row');
    if (cb.checked) {
      const activeSev = [...row.querySelectorAll('.sev-btn')].find(b=>b.classList.contains('active-mild')||b.classList.contains('active-moderate')||b.classList.contains('active-severe'));
      const level = activeSev ? activeSev.dataset.level : 'moderate';
      intoleranceState[key] = { name, severity: level };
      if (!activeSev) {
        row.querySelectorAll('.sev-btn').forEach(b => {
          b.classList.remove('active-mild','active-moderate','active-severe');
          if (b.dataset.level === 'moderate') b.classList.add('active-moderate');
        });
      }
    } else {
      delete intoleranceState[key];
      row.querySelectorAll('.sev-btn').forEach(b => b.classList.remove('active-mild','active-moderate','active-severe'));
    }
  });
});

// ── Diet preferences ─────────────────────────────────────────────────
function toggleDiet(btn) {
  const pref = btn.dataset.pref;
  if (dietPrefState.has(pref)) {
    dietPrefState.delete(pref);
    btn.classList.remove('active');
  } else {
    dietPrefState.add(pref);
    btn.classList.add('active');
  }
}

// ── Ingredient dislikes ───────────────────────────────────────────────
let dislikeCounter = 0;
function addDislike() {
  const input = document.getElementById('dislikeInput');
  const name  = input.value.trim();
  if (!name) return;
  if (dislikeState.includes(name)) { input.value=''; return; }
  dislikeState.push(name);

  const tag = document.createElement('span');
  tag.className = 'dislike-tag';
  tag.id = 'dl-new-' + (++dislikeCounter);
  tag.innerHTML = `${name.replace(/</g,'&lt;')}<button onclick="removeDislike(this,null,'${name.replace(/'/g,"\\'")}')">×</button>`;
  document.getElementById('dislikeList').appendChild(tag);

  // Remove "No dislikes" placeholder if present
  const placeholder = document.querySelector('#panel-diet .hs-card-body [style*="font-style:italic"]');
  if (placeholder) placeholder.remove();
  input.value = '';
}

function removeDislike(btn, id, name) {
  const idx = dislikeState.indexOf(name);
  if (idx > -1) dislikeState.splice(idx, 1);
  btn.closest('.dislike-tag')?.remove();
}

// ── Save Profile ─────────────────────────────────────────────────────
async function saveProfile() {
  const spinner = document.getElementById('savingSpinner');
  spinner.style.display = 'flex';

  const payload = {
    action:     'save_preferences',
    allergies:  Object.values(allergyState),
    intolerances: Object.values(intoleranceState),
    diet_prefs: [...dietPrefState],
    dislikes:   dislikeState,
  };

  try {
    const res  = await fetch('<?= BASE_PATH ?>/api/safe-appetite-scan.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    spinner.style.display = 'none';

    if (data.ok) {
      showToast('Profile saved successfully!', 'success');
    } else {
      showToast('Save failed: ' + (data.error || 'Unknown error'), 'error');
    }
  } catch(e) {
    spinner.style.display = 'none';
    showToast('Connection error. Please try again.', 'error');
  }
}

// ── Ingredient Scanner ────────────────────────────────────────────────
const EXAMPLES = {
  chocolate: {
    name: 'Cadbury Dairy Milk Chocolate Bar',
    ingredients: 'Sugar, Cocoa butter, Dried whey (from Milk), Cocoa mass, Dried skimmed milk, Vegetable fats (Palm, Shea), Emulsifier (E442), Flavourings. Milk Chocolate contains: Milk solids 14% minimum, Cocoa solids 25% minimum. May contain nuts.'
  },
  bread: {
    name: 'Hovis Soft White Medium Bread',
    ingredients: 'Wheat Flour (with Calcium, Iron, Niacin, Thiamin), Water, Yeast, Salt, Soya Flour, Rapeseed Oil, Fermented Wheat Flour, Emulsifiers (E471, E481), Flour Treatment Agent (Ascorbic Acid).'
  },
  cereal: {
    name: 'Kellogg\'s Corn Flakes',
    ingredients: 'Maize (Corn), Sugar, Salt, Barley Malt Flavouring, Vitamins and Minerals: Niacinamide (B3), Reduced Iron, Zinc Oxide, Pyridoxine Hydrochloride (B6), Riboflavin (B2), Thiamin Hydrochloride (B1), Folic Acid, Vitamin D.'
  },
  yogurt: {
    name: 'Fage Total 0% Greek Yogurt',
    ingredients: 'Pasteurised Skimmed Milk, Live Cultures (L. Bulgaricus, S. Thermophilus).'
  },
  crisp: {
    name: 'Walkers Ready Salted Crisps',
    ingredients: 'Potatoes, Sunflower Oil, Salt. May contain: Milk, Wheat, Barley, Oats, Rye.'
  },
  sauce: {
    name: 'Dolmio Bolognese Pasta Sauce',
    ingredients: 'Tomatoes (83%), Tomato Puree, Onion, Starch (Modified), Salt, Garlic, Sugar, Herbs, Spices, Citric Acid. May contain: Celery, Gluten.'
  },
};

function loadExample(key) {
  const ex = EXAMPLES[key];
  if (!ex) return;
  document.getElementById('productName').value    = ex.name;
  document.getElementById('ingredientsInput').value = ex.ingredients;
}

function clearScan() {
  document.getElementById('productName').value    = '';
  document.getElementById('ingredientsInput').value = '';
  document.getElementById('scanResultArea').innerHTML = '';
}

let scanning = false;

async function runScan() {
  if (scanning) return;
  const product     = document.getElementById('productName').value.trim();
  const ingredients = document.getElementById('ingredientsInput').value.trim();
  if (!product && !ingredients) {
    showToast('Please enter a product name or paste an ingredients list.', 'error');
    return;
  }

  scanning = true;
  document.getElementById('scanResultArea').innerHTML = `
    <div class="scan-spinner">
      <div class="spin-ring"></div>
      <div style="font-size:14px;font-weight:700;color:var(--hs-navy);">Scanning ingredients…</div>
      <div style="font-size:12px;color:var(--hs-muted);">AI is checking against your personal profile</div>
    </div>`;

  try {
    const res  = await fetch('<?= BASE_PATH ?>/api/safe-appetite-scan.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'scan', product_name: product, ingredients }),
    });
    const data = await res.json();

    if (data.error) {
      document.getElementById('scanResultArea').innerHTML =
        `<div class="scan-result-box result-caution"><b><i class="fas fa-circle-exclamation"></i> Error:</b> ${data.error}</div>`;
      scanning = false;
      return;
    }

    renderScanResult(data.result, data.scan_result);
  } catch(e) {
    document.getElementById('scanResultArea').innerHTML =
      `<div class="scan-result-box result-caution"><b>Connection error.</b> Please try again.</div>`;
  }
  scanning = false;
}

function renderScanResult(r, resultKey) {
  const overall  = (r.overall || resultKey).toLowerCase();
  const alerts   = r.alerts || [];
  const safeH    = r.safe_highlights || [];
  const summary  = r.summary || '';
  const tip      = r.tip || '';

  const iconMap = { safe: 'fa-check-circle', caution: 'fa-triangle-exclamation', danger: 'fa-xmark-circle' };
  const labelMap = { safe: 'SAFE', caution: 'CAUTION', danger: 'DANGER' };
  const emojiMap = { safe: '✅', caution: '⚠️', danger: '🚨' };

  let alertsHtml = '';
  if (alerts.length) {
    alertsHtml = '<div style="margin-bottom:16px;">';
    alertsHtml += '<div style="font-size:12px;font-weight:700;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px;">Flagged Ingredients</div>';
    for (const a of alerts) {
      const typeClass = a.type || 'INFO';
      const iconFA = typeClass==='DANGER' ? 'fa-ban' : typeClass==='CAUTION' ? 'fa-triangle-exclamation' : 'fa-circle-info';
      alertsHtml += `<div class="alert-item t-${typeClass}">
        <div class="alert-icon"><i class="fas ${iconFA}"></i></div>
        <div>
          <div style="font-weight:700;color:var(--hs-navy);">${(a.ingredient||'').replace(/</g,'&lt;')}</div>
          <div style="font-size:12px;color:var(--hs-muted);margin-top:2px;">${(a.reason||'').replace(/</g,'&lt;')}</div>
          ${a.matches ? `<div style="font-size:11px;color:var(--hs-blue);margin-top:3px;font-weight:600;"><i class="fas fa-link"></i> Matches: ${a.matches}</div>` : ''}
        </div>
      </div>`;
    }
    alertsHtml += '</div>';
  }

  let safeHtml = '';
  if (safeH.length) {
    safeHtml = '<div style="margin-bottom:16px;">';
    safeHtml += '<div style="font-size:12px;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px;"><i class="fas fa-thumbs-up"></i> Safe Highlights</div>';
    safeHtml += '<div>';
    for (const s of safeH) {
      safeHtml += `<span class="safe-pill"><i class="fas fa-check"></i> ${s.replace(/</g,'&lt;')}</span>`;
    }
    safeHtml += '</div></div>';
  }

  const product = document.getElementById('productName').value.trim() || 'Product';

  document.getElementById('scanResultArea').innerHTML = `
    <div class="scan-result-box result-${overall}">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
        <div>
          <span class="result-badge ${overall}">
            <i class="fas ${iconMap[overall]}"></i> ${emojiMap[overall]} ${labelMap[overall] || overall.toUpperCase()}
          </span>
          <div style="font-size:14px;font-weight:700;color:var(--hs-navy);margin-top:6px;">${product.replace(/</g,'&lt;')}</div>
        </div>
        ${alerts.length ? `<div style="font-size:13px;font-weight:700;color:${overall==='danger'?'var(--sa-red)':overall==='caution'?'var(--sa-amber)':'var(--sa-green)'};">${alerts.length} item${alerts.length>1?'s':''} flagged</div>` : ''}
      </div>

      ${summary ? `<div style="font-size:13px;color:var(--hs-text);margin-bottom:16px;padding:12px 16px;background:rgba(255,255,255,.6);border-radius:10px;line-height:1.6;">${summary.replace(/</g,'&lt;')}</div>` : ''}

      ${alertsHtml}
      ${safeHtml}

      ${tip ? `<div style="background:rgba(255,255,255,.7);border-radius:10px;padding:12px 16px;font-size:12.5px;color:var(--hs-muted);border-left:3px solid var(--sa-blue);"><i class="fas fa-lightbulb" style="color:var(--sa-blue);"></i> <strong>Tip:</strong> ${tip.replace(/</g,'&lt;')}</div>` : ''}

      ${!alerts.length && overall === 'safe' ? `<div style="text-align:center;padding:10px;"><div style="font-size:32px;margin-bottom:8px;">🎉</div><div style="font-size:14px;font-weight:700;color:var(--sa-green);">All clear! No allergens or violations detected.</div></div>` : ''}
    </div>`;

  // Scroll to result
  document.getElementById('scanResultArea').scrollIntoView({ behavior: 'smooth', block: 'start' });

  // Update history tab badge
  const histBadge = document.querySelector('#tab-history span');
  if (histBadge) {
    const cur = parseInt(histBadge.textContent) || 0;
    histBadge.textContent = cur + 1;
  }
}

// ── Delete scan history ───────────────────────────────────────────────
async function deleteScan(id) {
  if (!confirm('Delete this scan from history?')) return;
  await fetch('<?= BASE_PATH ?>/api/safe-appetite-scan.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'delete_scan', scan_id: id }),
  });
  document.getElementById('scan-' + id)?.remove();
  showToast('Scan deleted.', 'success');
}

// ── Toast notifications ───────────────────────────────────────────────
function showToast(msg, type) {
  const existing = document.getElementById('sa-toast');
  if (existing) existing.remove();

  const t = document.createElement('div');
  t.id = 'sa-toast';
  t.style.cssText = `
    position:fixed;bottom:24px;right:24px;z-index:9999;
    background:${type==='success'?'#16A34A':'#DC2626'};
    color:#fff;padding:12px 20px;border-radius:10px;
    font-size:13px;font-weight:700;
    box-shadow:0 4px 20px rgba(0,0,0,.25);
    animation:fadeIn .3s ease;
    display:flex;align-items:center;gap:8px;
  `;
  t.innerHTML = `<i class="fas ${type==='success'?'fa-check-circle':'fa-xmark-circle'}"></i> ${msg}`;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

// ── Quick pill style (borrow from diet-tracker) ───────────────────────
document.querySelectorAll('.quick-pill').forEach(btn => {
  btn.style.cssText += 'padding:5px 12px;border-radius:20px;border:1.5px solid var(--hs-border);background:#fff;font-size:11.5px;font-weight:600;cursor:pointer;transition:var(--transition);color:var(--hs-text);';
});
</script>
</body>
</html>
