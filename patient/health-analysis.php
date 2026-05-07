<?php
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
$user = getCurrentUser();
$uid  = $user['id'];

$notifCount = getUnreadCount($pdo, $uid);
$msgCount   = getUnreadMessages($pdo, $uid);
$activeCategory = $_GET['cat'] ?? 'all';

// Get latest metrics
$latest = $pdo->prepare("SELECT * FROM health_metrics WHERE patient_id=? ORDER BY metric_date DESC LIMIT 1");
$latest->execute([$uid]); $latest = $latest->fetch() ?: [];

// Today's diet
$diet = $pdo->prepare("SELECT SUM(calories) cal, SUM(protein) prot, SUM(carbs) carbs, SUM(fats) fats, SUM(fiber) fiber FROM diet_logs WHERE patient_id=? AND log_date=CURDATE()");
$diet->execute([$uid]); $diet = $diet->fetch() ?: [];

$water = $pdo->prepare("SELECT glasses_count FROM water_logs WHERE patient_id=? AND log_date=CURDATE()");
$water->execute([$uid]); $water = $water->fetch();

// Build health analysis zones
$zones = [
    'Diet & Food' => [
        'healthy'   => [['title'=>'Balanced Breakfast', 'msg'=>'High in protein and fiber on target this week.', 'tags'=>['Oats + Fruit','Boiled Eggs','Unsweetened Yogurt']], ['title'=>'Calorie Goal', 'msg'=>'530 / 2,500 kcal consumed']],
        'attention' => [['title'=>'Low Fiber Detected', 'msg'=>'Add leafy greens or lentils to lunch. Swap white rice for brown.', 'tags'=>['Add 1 cup salad','Swap white rice for brown']], ['title'=>'Sugary Snacks', 'msg'=>'2 high-sugar items logged this week.', 'action'=>'Diet Recommendations']],
        'critical'  => [['title'=>'High Sodium Consumption', 'msg'=>'34% above recommended average. Reduce packaged snacks and processed meats. Cook with herbs instead of extra salt.', 'tags'=>['Reduce packaged foods','Use herbs instead of salt']], ['title'=>'Foods to Avoid', 'msg'=>'Doctor noted: limit instant noodles and crisps.']],
    ],
    'Exercise & Activity' => [
        'healthy'   => [['title'=>'Consistent Activity', 'msg'=>'4 active days this week. HR stable.', 'tags'=>['Morning Walk','Stretching']]],
        'attention' => [['title'=>'Low Moderate Intensity', 'msg'=>'Add 15 min cardio daily.', 'tags'=>['Cycling','Light Jog'], 'action'=>'View Exercise Plan']],
        'critical'  => [['title'=>'Sedentary Streak', 'msg'=>'No activity logged for 2 days.', 'action'=>'Start 10-min routine']],
    ],
    'Medical Checkups' => [
        'healthy'   => [['title'=>'Blood Pressure', 'msg'=>'Stable within target range.']],
        'attention' => [['title'=>'Vitamin D', 'msg'=>'Below range — add sunlight and supplements.']],
        'critical'  => [['title'=>'Glucose Spikes', 'msg'=>'Refined carbs causing fluctuations.', 'action'=>'View Doctor\'s Note']],
    ],
    'Mental & Emotional' => [
        'healthy'   => [['title'=>'Positivity Score', 'msg'=>'92% — excellent emotional wellbeing this week.']],
        'attention' => [['title'=>'Stress Detected', 'msg'=>'Moderate stress on 3 days. Try breathing exercises.']],
        'critical'  => [],
    ],
    'Sleep & Recovery' => [
        'healthy'   => [['title'=>'Sleep Duration', 'msg'=>'Average 7.8h — within recommended range.']],
        'attention' => [['title'=>'Sleep Inconsistency', 'msg'=>'2 nights below 6h. Maintain a fixed sleep schedule.']],
        'critical'  => [],
    ],
    'Hydration' => [
        'healthy'   => [['title'=>'Well Hydrated', 'msg'=>'Averaged 7 glasses daily this week.']],
        'attention' => [['title'=>'Today\'s Intake Low', 'msg'=>'Only 3 glasses so far. Target: 8.', 'action'=>'Log Water']],
        'critical'  => [],
    ],
    'Environment' => [
        'healthy'   => [['title'=>'Air Quality', 'msg'=>'Good air quality in Leicester today.']],
        'attention' => [['title'=>'Pollen Alert', 'msg'=>'Moderate pollen — take antihistamine if needed.']],
        'critical'  => [],
    ],
];

$categories = array_keys($zones);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Health Analysis — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-chart-bar" style="color:var(--hs-blue);"></i> Overall Health Analysis</div>
      <div class="page-subtitle">Personalised health evaluation across 7 dimensions</div>
    </div>
    <div class="topbar-actions">
      <div style="display:flex;gap:4px;background:var(--hs-off-white);border-radius:8px;padding:4px;border:1px solid var(--hs-border);">
        <?php foreach (['Daily','Weekly','Monthly'] as $v): ?>
        <button style="padding:6px 14px;border-radius:6px;border:none;font-size:12px;font-weight:600;cursor:pointer;background:<?= $v==='Daily'?'var(--hs-blue)':'transparent' ?>;color:<?= $v==='Daily'?'#fff':'var(--hs-muted)' ?>;"><?= $v ?></button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="hs-content">
    <div style="display:grid;grid-template-columns:200px 1fr;gap:20px;">

      <!-- Left: Category filter -->
      <div>
        <div class="hs-card">
          <div class="hs-card-body" style="padding:12px;">
            <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--hs-muted);margin-bottom:10px;">Categories</div>
            <?php
            $catIcons = ['Diet & Food'=>'fa-drumstick-bite','Exercise & Activity'=>'fa-running','Medical Checkups'=>'fa-stethoscope','Mental & Emotional'=>'fa-brain','Sleep & Recovery'=>'fa-moon','Hydration'=>'fa-tint','Environment'=>'fa-leaf'];
            foreach ($categories as $cat):
              $isActive = $activeCategory === $cat || ($activeCategory === 'all' && $cat === $categories[0]);
            ?>
            <a href="?cat=<?= urlencode($cat) ?>" style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:8px;text-decoration:none;margin-bottom:4px;transition:var(--transition);background:<?= $activeCategory===$cat?'var(--hs-blue)':'transparent' ?>;color:<?= $activeCategory===$cat?'#fff':'var(--hs-text)' ?>;">
              <i class="fas <?= $catIcons[$cat] ?? 'fa-circle' ?>" style="width:16px;text-align:center;font-size:13px;"></i>
              <span style="font-size:13px;font-weight:500;"><?= $cat ?></span>
            </a>
            <?php endforeach; ?>
            <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--hs-border);">
              <a href="?cat=all" style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;color:var(--hs-blue);background:var(--hs-off-white);">
                <i class="fas fa-th"></i> View All
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Analysis zones -->
      <div>
        <?php
        $displayZones = $activeCategory === 'all' ? $zones : [$activeCategory => $zones[$activeCategory] ?? []];
        foreach ($displayZones as $catName => $sections):
        ?>
        <div style="margin-bottom:20px;">
          <h6 style="font-weight:800;color:var(--hs-navy);margin-bottom:14px;font-size:15px;display:flex;align-items:center;gap:8px;">
            <i class="fas <?= $catIcons[$catName] ?? 'fa-circle' ?>" style="color:var(--hs-blue);"></i>
            <?= e($catName) ?>
          </h6>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">

            <!-- Healthy column -->
            <div>
              <div style="display:flex;align-items:center;gap:6px;padding:6px 10px;background:#DCFCE7;border-radius:6px;margin-bottom:10px;">
                <span style="width:8px;height:8px;border-radius:50%;background:#16A34A;"></span>
                <span style="font-size:11px;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:.5px;">Healthy</span>
              </div>
              <?php foreach ($sections['healthy'] as $item): ?>
              <div class="health-zone zone-healthy">
                <div class="zone-title"><?= e($item['title']) ?></div>
                <div class="zone-items" style="font-size:12.5px;color:var(--hs-muted);"><?= e($item['msg']) ?></div>
                <?php if (!empty($item['tags'])): ?>
                <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:4px;">
                  <?php foreach ($item['tags'] as $tag): ?>
                  <span class="zone-tag green"><?= e($tag) ?></span>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
              <?php if (!$sections['healthy']): ?>
              <div style="font-size:12px;color:var(--hs-muted);padding:10px;text-align:center;opacity:.5;">—</div>
              <?php endif; ?>
            </div>

            <!-- Attention column -->
            <div>
              <div style="display:flex;align-items:center;gap:6px;padding:6px 10px;background:#FEF3C7;border-radius:6px;margin-bottom:10px;">
                <span style="width:8px;height:8px;border-radius:50%;background:#D97706;"></span>
                <span style="font-size:11px;font-weight:700;color:#92400E;text-transform:uppercase;letter-spacing:.5px;">Attention</span>
              </div>
              <?php foreach ($sections['attention'] as $item): ?>
              <div class="health-zone zone-attention">
                <div class="zone-title"><?= e($item['title']) ?></div>
                <div class="zone-items" style="font-size:12.5px;color:var(--hs-muted);"><?= e($item['msg']) ?></div>
                <?php if (!empty($item['tags'])): ?>
                <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:4px;">
                  <?php foreach ($item['tags'] as $tag): ?>
                  <span class="zone-tag yellow"><?= e($tag) ?></span>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($item['action'])): ?>
                <button class="btn-hs btn-outline-hs btn-sm-hs" style="margin-top:10px;"><?= e($item['action']) ?></button>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
              <?php if (!$sections['attention']): ?>
              <div style="font-size:12px;color:var(--hs-muted);padding:10px;text-align:center;opacity:.5;">—</div>
              <?php endif; ?>
            </div>

            <!-- Critical column -->
            <div>
              <div style="display:flex;align-items:center;gap:6px;padding:6px 10px;background:#FEE2E2;border-radius:6px;margin-bottom:10px;">
                <span style="width:8px;height:8px;border-radius:50%;background:#DC2626;"></span>
                <span style="font-size:11px;font-weight:700;color:#991B1B;text-transform:uppercase;letter-spacing:.5px;">Requires Action</span>
              </div>
              <?php foreach ($sections['critical'] as $item): ?>
              <div class="health-zone zone-critical">
                <div class="zone-title"><?= e($item['title']) ?></div>
                <div class="zone-items" style="font-size:12.5px;color:var(--hs-muted);"><?= e($item['msg']) ?></div>
                <?php if (!empty($item['tags'])): ?>
                <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:4px;">
                  <?php foreach ($item['tags'] as $tag): ?>
                  <span class="zone-tag red"><?= e($tag) ?></span>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($item['action'])): ?>
                <button class="btn-hs btn-danger-hs btn-sm-hs" style="margin-top:10px;"><?= e($item['action']) ?></button>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
              <?php if (!$sections['critical']): ?>
              <div style="font-size:12px;color:var(--hs-muted);padding:10px;text-align:center;opacity:.5;">No critical alerts</div>
              <?php endif; ?>
            </div>

          </div>
        </div>
        <?php endforeach; ?>
      </div>

    </div>
  </div>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
