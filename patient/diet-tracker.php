<?php
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
$user = getCurrentUser();
$uid  = $user['id'];
$today = date('Y-m-d');

$success = '';
// Add meal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_meal'])) {
    $stmt = $pdo->prepare("INSERT INTO diet_logs (patient_id,log_date,meal_type,food_name,calories,protein,carbs,fats,fiber) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$uid, $today, $_POST['meal_type'], $_POST['food_name'], $_POST['calories'] ?: 0, $_POST['protein'] ?: 0, $_POST['carbs'] ?: 0, $_POST['fats'] ?: 0, $_POST['fiber'] ?: 0]);
    $success = 'Meal logged!';
}
// Water log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['water'])) {
    $glasses = (int)$_POST['water_glasses'];
    $existing = $pdo->prepare("SELECT id FROM water_logs WHERE patient_id=? AND log_date=?"); $existing->execute([$uid, $today]); $existing = $existing->fetch();
    if ($existing) {
        $pdo->prepare("UPDATE water_logs SET glasses_count=?, total_ml=? WHERE id=?")->execute([$glasses, $glasses*250, $existing['id']]);
    } else {
        $pdo->prepare("INSERT INTO water_logs (patient_id,log_date,glasses_count,total_ml) VALUES (?,?,?,?)")->execute([$uid, $today, $glasses, $glasses*250]);
    }
    $success = 'Water intake updated!';
}

// Today's meals
$meals = $pdo->prepare("SELECT * FROM diet_logs WHERE patient_id=? AND log_date=? ORDER BY FIELD(meal_type,'breakfast','lunch','snack','dinner'), id");
$meals->execute([$uid, $today]); $meals = $meals->fetchAll();

// Totals
$totals = ['calories'=>0,'protein'=>0,'carbs'=>0,'fats'=>0,'fiber'=>0];
$byType = ['breakfast'=>[],'lunch'=>[],'snack'=>[],'dinner'=>[]];
foreach ($meals as $m) {
    foreach ($totals as $k => $_) $totals[$k] += $m[$k];
    $byType[$m['meal_type']][] = $m;
}

// Water
$waterLog = $pdo->prepare("SELECT * FROM water_logs WHERE patient_id=? AND log_date=?"); $waterLog->execute([$uid, $today]); $waterLog = $waterLog->fetch();
$waterGlasses = $waterLog['glasses_count'] ?? 0;

// Weekly totals
$weekly = $pdo->prepare("SELECT log_date, SUM(calories) as cal FROM diet_logs WHERE patient_id=? AND log_date >= DATE_SUB(CURDATE(),INTERVAL 7 DAY) GROUP BY log_date ORDER BY log_date");
$weekly->execute([$uid]); $weekly = $weekly->fetchAll();

// Food DB for search
$foodDb = $pdo->query("SELECT * FROM food_database ORDER BY health_rating DESC LIMIT 20")->fetchAll();

$notifCount = getUnreadCount($pdo, $uid);
$msgCount   = getUnreadMessages($pdo, $uid);

$GOALS = ['calories'=>2500, 'protein'=>60, 'carbs'=>300, 'fats'=>65, 'fiber'=>25];

// ── AI Daily Meal Recommendations (health-aware) ───────────────────
$metrics = $pdo->prepare("SELECT * FROM health_metrics WHERE patient_id=? ORDER BY metric_date DESC LIMIT 1");
$metrics->execute([$uid]); $metrics = $metrics->fetch();
$bpSys = (int)($metrics['blood_pressure_systolic'] ?? 120);
$allergyNames = array_column($pdo->prepare("SELECT allergen FROM allergies WHERE patient_id=? AND is_active=1")->execute([$uid]) ? $pdo->query("SELECT allergen FROM allergies WHERE patient_id=$uid AND is_active=1")->fetchAll() : [], 'allergen');

// Family risk flags
$famRisk = $pdo->query("SELECT condition_name FROM family_history WHERE patient_id=$uid AND condition_name REGEXP 'diabet|cholesterol|hypertension|heart'")->fetchAll(PDO::FETCH_COLUMN);
$hasHeartRisk   = count(array_filter($famRisk, fn($r)=>str_contains(strtolower($r),'heart')||str_contains(strtolower($r),'cholesterol'))) > 0;
$hasDiabRisk    = count(array_filter($famRisk, fn($r)=>str_contains(strtolower($r),'diabet'))) > 0;
$hasHypertRisk  = $bpSys >= 130 || count(array_filter($famRisk, fn($r)=>str_contains(strtolower($r),'hypertension'))) > 0;

$recs = [
  'breakfast' => [
    'meal'  => $hasDiabRisk  ? 'Overnight Oats with Berries & Flaxseeds'  : 'Greek Yogurt Parfait with Granola',
    'why'   => $hasDiabRisk  ? 'Low GI oats stabilise blood sugar. Berries add antioxidants.' : 'High protein, probiotic-rich start to the day.',
    'kcal'  => 320, 'icon'=>'🌅', 'tags'=>['Low GI','High Fibre','Heart-Healthy'],
  ],
  'lunch' => [
    'meal'  => $hasHeartRisk ? 'Grilled Salmon with Quinoa & Spinach Salad' : 'Chicken & Avocado Wholegrain Wrap',
    'why'   => $hasHeartRisk ? 'Omega-3 from salmon directly reduces cardiovascular risk from your family history.' : 'Lean protein and healthy fats for sustained energy.',
    'kcal'  => 480, 'icon'=>'☀️', 'tags'=>['Omega-3 Rich','Low Sodium','Protein'],
  ],
  'snack' => [
    'meal'  => 'Walnuts, Almonds & Apple Slices',
    'why'   => 'Walnuts provide plant-based Omega-3. Apple fibre supports cholesterol management.',
    'kcal'  => 200, 'icon'=>'🍎', 'tags'=>['Heart-Healthy','Low Sugar'],
  ],
  'dinner' => [
    'meal'  => $hasHypertRisk ? 'Herb-Baked Chicken with Roasted Vegetables & Brown Rice' : 'Lentil & Vegetable Curry with Brown Rice',
    'why'   => $hasHypertRisk ? 'No added salt — uses herbs for flavour. Brown rice is low GI. Potassium from veg supports BP.' : 'High in plant protein and fibre. Excellent for heart health.',
    'kcal'  => 520, 'icon'=>'🌙', 'tags'=>[$hasHypertRisk?'Low Sodium':'High Protein','Complex Carbs','Filling'],
  ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diet Tracker — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
/* AI chat panel */
.ai-panel { display:flex;flex-direction:column;height:100%;min-height:520px; }
.ai-msgs  { flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px;background:#F4F8FF;border-radius:10px;margin-bottom:10px;max-height:340px; }
.ai-msg   { display:flex;gap:8px;max-width:92%; }
.ai-msg.ai-out { align-self:flex-end;flex-direction:row-reverse; }
.ai-msg.ai-in  { align-self:flex-start; }
.ai-bubble { padding:10px 13px;border-radius:14px;font-size:13px;line-height:1.6; }
.ai-out .ai-bubble { background:var(--hs-blue);color:#fff;border-radius:14px 14px 4px 14px; }
.ai-in  .ai-bubble { background:#fff;color:var(--hs-text);border:1px solid var(--hs-border);border-radius:14px 14px 14px 4px;box-shadow:0 2px 6px rgba(10,31,68,.06); }
.ai-msg .ai-av { width:28px;height:28px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:12px;margin-top:2px; }
.ai-out .ai-av { background:var(--hs-blue);color:#fff;font-weight:700; }
.ai-in  .ai-av { background:linear-gradient(135deg,#0A1F44,#1565C0);color:#fff; }
.ai-typing { display:flex;gap:4px;padding:10px 14px;background:#fff;border-radius:14px;border:1px solid var(--hs-border); }
.ai-typing span { width:6px;height:6px;border-radius:50%;background:var(--hs-muted);animation:aiDot 1.3s ease-in-out infinite; }
.ai-typing span:nth-child(2){animation-delay:.2s;} .ai-typing span:nth-child(3){animation-delay:.4s;}
@keyframes aiDot { 0%,60%,100%{transform:translateY(0);opacity:.4;} 30%{transform:translateY(-5px);opacity:1;} }
.yt-btn { display:inline-flex;align-items:center;gap:6px;background:#FF0000;color:#fff;padding:6px 14px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;margin-top:8px;transition:var(--transition); }
.yt-btn:hover { background:#CC0000;color:#fff;transform:scale(1.03); }
.quick-pill { padding:5px 12px;border-radius:20px;border:1.5px solid var(--hs-border);background:#fff;font-size:11.5px;font-weight:600;cursor:pointer;transition:var(--transition);color:var(--hs-text); }
.quick-pill:hover { border-color:var(--hs-blue);color:var(--hs-blue);background:#EFF6FF; }
/* Rec cards */
.rec-card { background:#fff;border:1px solid var(--hs-border);border-radius:12px;padding:14px;transition:var(--transition);cursor:pointer; }
.rec-card:hover { border-color:var(--hs-blue);box-shadow:0 4px 14px rgba(10,31,68,.1);transform:translateY(-2px); }
.rec-tag { padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;background:#DBEAFE;color:#1E40AF; }
/* Markdown in AI bubble */
.ai-md h2 { font-size:14px;font-weight:800;color:var(--hs-navy);margin:8px 0 4px; }
.ai-md h3 { font-size:13px;font-weight:700;color:var(--hs-navy);margin:6px 0 3px; }
.ai-md strong { font-weight:700; }
.ai-md ul,.ai-md ol { padding-left:16px;margin:4px 0; }
.ai-md li { margin-bottom:2px; }
.ai-md p  { margin:4px 0; }
.ai-md .ai-warn { background:#FEF3C7;border-left:3px solid #D97706;padding:6px 10px;border-radius:0 6px 6px 0;margin:6px 0;font-size:12px; }
.ai-md .ai-good { background:#DCFCE7;border-left:3px solid #16A34A;padding:6px 10px;border-radius:0 6px 6px 0;margin:6px 0;font-size:12px; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-utensils" style="color:var(--hs-blue);"></i> Diet & Nutrition Tracker</div>
      <div class="page-subtitle"><?= date('l, d F Y') ?> · Goal: 2,500 kcal/day</div>
    </div>
    <div class="topbar-actions">
      <button class="btn-hs btn-primary-hs btn-sm-hs" onclick="document.getElementById('addMealModal').style.display='flex'">
        <i class="fas fa-plus"></i> Log Meal
      </button>
    </div>
  </div>

  <div class="hs-content">
    <?php if ($success): ?><div style="background:#DCFCE7;border:1px solid #BBF7D0;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#166534;font-size:13px;font-weight:600;"><i class="fas fa-check-circle"></i> <?= e($success) ?></div><?php endif; ?>

    <!-- ── AI Daily Meal Recommendations ─────────────────────────── -->
    <div class="hs-card" style="margin-bottom:18px;border-left:4px solid #16A34A;">
      <div class="hs-card-header">
        <span class="card-title"><i class="fas fa-brain" style="color:#16A34A;"></i> AI Meal Plan — Personalised for You</span>
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-size:11px;background:#DCFCE7;color:#166534;padding:3px 10px;border-radius:20px;font-weight:700;"><i class="fas fa-magic"></i> Based on your health data</span>
          <span style="font-size:11px;color:var(--hs-muted);"><?= date('l, d M Y') ?></span>
        </div>
      </div>
      <div class="hs-card-body">
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
          <?php foreach ($recs as $type=>$rec): ?>
          <div class="rec-card" onclick="askMeal('How do I prepare <?= addslashes($rec['meal']) ?>?')">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
              <span style="font-size:22px;"><?= $rec['icon'] ?></span>
              <div>
                <div style="font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--hs-muted);"><?= ucfirst($type) ?></div>
                <div style="font-size:12.5px;font-weight:800;color:var(--hs-navy);line-height:1.3;"><?= e($rec['meal']) ?></div>
              </div>
            </div>
            <div style="font-size:11.5px;color:var(--hs-muted);margin-bottom:8px;line-height:1.5;"><?= e($rec['why']) ?></div>
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:4px;">
              <div style="display:flex;gap:4px;flex-wrap:wrap;">
                <?php foreach ($rec['tags'] as $tag): ?>
                <span class="rec-tag"><?= e($tag) ?></span>
                <?php endforeach; ?>
              </div>
              <span style="font-size:12px;font-weight:700;color:var(--hs-blue);">~<?= $rec['kcal'] ?> kcal</span>
            </div>
            <div style="margin-top:8px;font-size:11px;color:var(--hs-blue);display:flex;align-items:center;gap:4px;">
              <i class="fas fa-robot"></i> Click for recipe &amp; steps
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:280px 1fr 320px;gap:20px;">

      <!-- Left: Nutrition Summary -->
      <div>
        <div class="hs-card" style="margin-bottom:16px;">
          <div class="hs-card-header"><span class="card-title"><i class="fas fa-chart-pie"></i> Today's Nutrition</span></div>
          <div class="hs-card-body">
            <div style="position:relative;height:180px;margin-bottom:16px;">
              <canvas id="nutritionChart"></canvas>
              <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;">
                <div style="font-size:22px;font-weight:900;color:var(--hs-navy);"><?= number_format($totals['calories']) ?></div>
                <div style="font-size:11px;color:var(--hs-muted);">kcal</div>
              </div>
            </div>
            <?php
            $macros = [
              ['label'=>'Calories',  'val'=>$totals['calories'],  'goal'=>$GOALS['calories'],  'color'=>'#1565C0'],
              ['label'=>'Protein',   'val'=>$totals['protein'],   'goal'=>$GOALS['protein'],   'color'=>'#16A34A'],
              ['label'=>'Carbs',     'val'=>$totals['carbs'],     'goal'=>$GOALS['carbs'],     'color'=>'#00B4D8'],
              ['label'=>'Fats',      'val'=>$totals['fats'],      'goal'=>$GOALS['fats'],      'color'=>'#D97706'],
              ['label'=>'Fiber',     'val'=>$totals['fiber'],     'goal'=>$GOALS['fiber'],     'color'=>'#7C3AED'],
            ];
            foreach ($macros as $m):
              $pct = min(round(($m['val'] / $m['goal']) * 100), 100);
            ?>
            <div class="macro-bar-wrap">
              <div class="macro-label">
                <span><?= $m['label'] ?></span>
                <span><?= round($m['val'],1) ?>g / <?= $m['goal'] ?></span>
              </div>
              <div class="macro-bar">
                <div class="macro-fill" style="width:<?= $pct ?>%;background:<?= $m['color'] ?>;" data-width="<?= $pct ?>%"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Water Tracker -->
        <div class="hs-card">
          <div class="hs-card-header"><span class="card-title"><i class="fas fa-tint"></i> Water Intake</span></div>
          <div class="hs-card-body">
            <p style="font-size:13px;color:var(--hs-muted);margin-bottom:12px;"><?= $waterGlasses ?> / 8 glasses today</p>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:16px;">
              <?php for ($i = 1; $i <= 8; $i++): ?>
              <div class="water-glass <?= $i <= $waterGlasses ? 'filled' : '' ?>" title="Glass <?= $i ?>">
                <i class="fas fa-tint" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:14px;color:<?= $i <= $waterGlasses ? '#fff' : 'var(--hs-border)' ?>;"></i>
              </div>
              <?php endfor; ?>
            </div>
            <form method="POST">
              <input type="hidden" name="water_glasses" id="waterCount" value="<?= $waterGlasses ?>">
              <button type="submit" name="water" class="btn-hs btn-primary-hs btn-sm-hs" style="width:100%;justify-content:center;">
                <i class="fas fa-save"></i> Save Water Log
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Center: Meals by type -->
      <div>
        <?php
        $mealMeta = [
          'breakfast' => ['icon'=>'☀️', 'label'=>'Breakfast', 'range'=>'300–1170 Cal'],
          'lunch'     => ['icon'=>'🌤', 'label'=>'Lunch',     'range'=>'225–370 Cal'],
          'snack'     => ['icon'=>'🍎', 'label'=>'Snack',     'range'=>'195–210 Cal'],
          'dinner'    => ['icon'=>'🌙', 'label'=>'Dinner',    'range'=>'355–570 Cal'],
        ];
        foreach ($mealMeta as $type => $meta):
          $typeMeals = $byType[$type];
          $typeCal   = array_sum(array_column($typeMeals, 'calories'));
        ?>
        <div class="hs-card" style="margin-bottom:16px;">
          <div class="hs-card-header">
            <span class="card-title"><?= $meta['icon'] ?> <?= $meta['label'] ?></span>
            <div style="display:flex;align-items:center;gap:8px;">
              <span style="font-size:12px;color:var(--hs-muted);">Rec. <?= $meta['range'] ?></span>
              <button class="btn-hs btn-outline-hs btn-sm-hs" onclick="openAddMeal('<?= $type ?>')" title="Add <?= $meta['label'] ?>">
                <i class="fas fa-plus"></i> Add
              </button>
            </div>
          </div>
          <div class="hs-card-body">
            <?php if ($typeMeals): ?>
              <?php foreach ($typeMeals as $meal): ?>
              <div class="meal-row">
                <div class="meal-icon">🍽</div>
                <div class="meal-info">
                  <div class="meal-name"><?= e($meal['food_name']) ?></div>
                  <div class="meal-meta">
                    P: <?= round($meal['protein'],1) ?>g &nbsp;·&nbsp; C: <?= round($meal['carbs'],1) ?>g &nbsp;·&nbsp; F: <?= round($meal['fats'],1) ?>g
                  </div>
                </div>
                <div class="meal-cal"><?= round($meal['calories']) ?> kcal</div>
              </div>
              <?php endforeach; ?>
              <div style="text-align:right;font-size:13px;font-weight:700;color:var(--hs-blue);margin-top:8px;">
                Total: <?= round($typeCal) ?> kcal
              </div>
            <?php else: ?>
              <div style="text-align:center;padding:16px;color:var(--hs-muted);font-size:13px;">
                <i class="fas fa-utensils" style="opacity:.3;font-size:24px;"></i>
                <p style="margin-top:8px;">No <?= strtolower($meta['label']) ?> logged yet.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Right: AI Meal Assistant -->
      <div>
        <div class="hs-card" style="height:100%;">
          <div class="hs-card-header" style="background:linear-gradient(135deg,#0A1F44,#1565C0);border-radius:11px 11px 0 0;">
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:16px;">🤖</div>
              <div>
                <div style="font-weight:800;font-size:14px;color:#fff;">AI Meal Assistant</div>
                <div style="font-size:11px;color:rgba(255,255,255,.65);display:flex;align-items:center;gap:5px;"><span style="width:6px;height:6px;border-radius:50%;background:#22C55E;display:inline-block;"></span> Online &middot; Personalised for you</div>
              </div>
            </div>
          </div>
          <div class="hs-card-body ai-panel" style="padding:14px;">

            <!-- Quick prompts -->
            <div style="margin-bottom:10px;">
              <div style="font-size:10.5px;font-weight:700;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:7px;">Quick Questions</div>
              <div style="display:flex;flex-wrap:wrap;gap:5px;">
                <button class="quick-pill" onclick="askMeal('What should I eat today based on my health?')">🥗 Today\'s plan</button>
                <button class="quick-pill" onclick="askMeal('How to make grilled salmon?')">🐟 Grilled salmon</button>
                <button class="quick-pill" onclick="askMeal('How to make heart-healthy oatmeal?')">🌾 Oatmeal recipe</button>
                <button class="quick-pill" onclick="askMeal('What foods should I avoid with high blood pressure?')">⚠️ Avoid foods</button>
                <button class="quick-pill" onclick="askMeal('Give me a high protein low sodium meal idea')">💪 High protein</button>
                <button class="quick-pill" onclick="askMeal('How many calories do I have left today?')">🔥 Calories left</button>
              </div>
            </div>

            <!-- Messages -->
            <div id="aiMsgs" class="ai-msgs">
              <div class="ai-msg ai-in">
                <div class="ai-av"><i class="fas fa-robot" style="font-size:11px;"></i></div>
                <div>
                  <div class="ai-bubble ai-md">
                    <strong>Hi <?= e($user['first_name']) ?>! 👋</strong> I'm your AI Meal Assistant.<br><br>
                    I know your health data — I'll give you personalised recipes, step-by-step cooking instructions, and YouTube videos.<br><br>
                    <em>Try clicking one of the quick questions above or ask me anything!</em>
                  </div>
                </div>
              </div>
            </div>

            <!-- Input -->
            <div style="display:flex;gap:8px;">
              <input type="text" id="aiInput"
                placeholder="e.g. How do I make grilled chicken?"
                class="form-control" style="font-size:13px;border-radius:20px;"
                onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMealMsg();}">
              <button onclick="sendMealMsg()" style="width:38px;height:38px;border-radius:50%;background:var(--hs-blue);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas fa-paper-plane" style="font-size:14px;"></i>
              </button>
            </div>
            <div style="text-align:center;margin-top:8px;font-size:10.5px;color:var(--hs-muted);">
              <i class="fas fa-shield-alt" style="color:var(--hs-blue);"></i> AI considers your allergies &amp; health profile
            </div>
          </div>
        </div>
      </div>

    </div><!-- /grid -->
  </div>
</div>

<!-- Add Meal Modal -->
<div id="addMealModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2000;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:16px;width:100%;max-width:480px;box-shadow:var(--shadow-lg);overflow:hidden;">
    <div style="background:var(--hs-navy);color:#fff;padding:18px 24px;display:flex;justify-content:space-between;align-items:center;">
      <h5 style="margin:0;font-size:16px;font-weight:700;"><i class="fas fa-utensils"></i> Log Meal</h5>
      <button onclick="document.getElementById('addMealModal').style.display='none'" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;">×</button>
    </div>
    <form method="POST" style="padding:24px;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
        <div>
          <label class="form-label">Meal Type *</label>
          <select name="meal_type" id="mealTypeSelect" class="form-select" required>
            <option value="breakfast">Breakfast</option>
            <option value="lunch">Lunch</option>
            <option value="snack">Snack</option>
            <option value="dinner">Dinner</option>
          </select>
        </div>
        <div>
          <label class="form-label">Food Name *</label>
          <input type="text" name="food_name" id="foodNameInput" class="form-control" placeholder="e.g. Grilled Chicken" required>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
        <div>
          <label class="form-label">Calories (kcal)</label>
          <input type="number" name="calories" id="calInput" class="form-control" placeholder="0" min="0" step="0.1">
        </div>
        <div>
          <label class="form-label">Protein (g)</label>
          <input type="number" name="protein" id="protInput" class="form-control" placeholder="0" min="0" step="0.1">
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:20px;">
        <div>
          <label class="form-label">Carbs (g)</label>
          <input type="number" name="carbs" class="form-control" placeholder="0" min="0" step="0.1">
        </div>
        <div>
          <label class="form-label">Fats (g)</label>
          <input type="number" name="fats" class="form-control" placeholder="0" min="0" step="0.1">
        </div>
        <div>
          <label class="form-label">Fiber (g)</label>
          <input type="number" name="fiber" class="form-control" placeholder="0" min="0" step="0.1">
        </div>
      </div>
      <div style="display:flex;gap:12px;">
        <button type="submit" name="add_meal" class="btn-hs btn-primary-hs" style="flex:1;justify-content:center;">
          <i class="fas fa-plus"></i> Add Meal
        </button>
        <button type="button" onclick="document.getElementById('addMealModal').style.display='none'" class="btn-hs btn-outline-hs">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/main.js"></script>
<script>
// ── AI Meal Assistant ──────────────────────────────────────────────
let aiHistory = [];
let aiLoading = false;

const aiMsgs  = document.getElementById('aiMsgs');
const aiInput = document.getElementById('aiInput');
const INITIALS = '<?= strtoupper(substr($user['first_name'],0,1)) ?>';

function scrollAI() { aiMsgs.scrollTop = aiMsgs.scrollHeight; }

// Render markdown-lite
function aiMd(text) {
  return text
    .replace(/^## (.+)$/gm, '<h2>$1</h2>')
    .replace(/^### (.+)$/gm, '<h3>$1</h3>')
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.+?)\*/g, '<em>$1</em>')
    .replace(/^(\d+)\. (.+)$/gm, '<li>$2</li>')
    .replace(/^- (.+)$/gm, '<li>$1</li>')
    .replace(/(<li>.*<\/li>\n?)+/g, m => '<ul>'+m+'</ul>')
    .replace(/### ⚠️(.+)/g, '<div class="ai-warn">⚠️$1</div>')
    .replace(/### 🥗(.+)/g, '<div class="ai-good">🥗$1</div>')
    .replace(/\n\n/g, '<br><br>')
    .replace(/\n/g, '<br>');
}

function appendAiMsg(role, html, ytUrl, ytLabel) {
  const wrap = document.createElement('div');
  wrap.className = 'ai-msg ' + (role==='user' ? 'ai-out' : 'ai-in');
  const av = role==='user'
    ? `<div class="ai-av">${INITIALS}</div>`
    : `<div class="ai-av"><i class="fas fa-robot" style="font-size:11px;"></i></div>`;
  const ytBtn = ytUrl
    ? `<a href="${ytUrl}" target="_blank" class="yt-btn"><i class="fab fa-youtube"></i> Watch on YouTube${ytLabel?' — '+ytLabel:''}</a>`
    : '';
  wrap.innerHTML = `${av}<div><div class="ai-bubble ai-md">${html}</div>${ytBtn}</div>`;
  aiMsgs.appendChild(wrap);
  scrollAI();
  return wrap;
}

function showTyping() {
  const el = document.createElement('div');
  el.className = 'ai-msg ai-in'; el.id='aiTyping';
  el.innerHTML = `<div class="ai-av"><i class="fas fa-robot" style="font-size:11px;"></i></div><div class="ai-typing"><span></span><span></span><span></span></div>`;
  aiMsgs.appendChild(el); scrollAI();
}
function hideTyping() { document.getElementById('aiTyping')?.remove(); }

async function sendMealMsg() {
  const text = aiInput.value.trim();
  if (!text || aiLoading) return;
  aiLoading = true; aiInput.value = '';

  appendAiMsg('user', text.replace(/</g,'&lt;'), '', '');
  aiHistory.push({role:'user', content:text});
  showTyping();

  try {
    const res = await fetch('/HealthSphere/api/meal-ai.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({message:text, history:aiHistory.slice(-8)}),
    });
    const data = await res.json();
    hideTyping();

    const reply   = data.reply || 'Sorry, I could not process that. Please try again.';
    const ytUrl   = data.yt_url   || '';
    const ytSearch= data.yt_search || '';

    appendAiMsg('assistant', aiMd(reply), ytUrl, ytSearch);
    aiHistory.push({role:'assistant', content:reply});
  } catch(e) {
    hideTyping();
    appendAiMsg('assistant', '⚠️ Connection error. Please try again.', '', '');
  }
  aiLoading = false;
}

// Called by recommendation cards & quick pills
function askMeal(question) {
  aiInput.value = question;
  sendMealMsg();
  // Scroll to chat
  document.querySelector('.ai-panel')?.scrollIntoView({behavior:'smooth',block:'start'});
}
</script>
<script>
// Override nutritionChart with actual data
document.addEventListener('DOMContentLoaded', () => {
  const ctx = document.getElementById('nutritionChart');
  if (ctx && typeof Chart !== 'undefined') {
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Protein','Carbs','Fats','Fiber'],
        datasets: [{ data: [<?= round($totals['protein']),round($totals['carbs']),round($totals['fats']),round($totals['fiber']) ?>], backgroundColor:['#1565C0','#00B4D8','#D97706','#16A34A'], borderWidth:2, borderColor:'#fff' }],
      },
      options: { responsive:true, maintainAspectRatio:false, cutout:'72%', plugins:{legend:{display:false}} }
    });
  }
});

function openAddMeal(type) {
  document.getElementById('mealTypeSelect').value = type;
  document.getElementById('addMealModal').style.display = 'flex';
}

function quickAddFood(name, cal, prot, sugar, fat, fiber) {
  document.getElementById('foodNameInput').value = name;
  document.getElementById('calInput').value = cal;
  document.getElementById('protInput').value = prot;
  document.getElementById('addMealModal').style.display = 'flex';
}

document.getElementById('foodSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.food-item').forEach(item => {
    item.style.display = item.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});
</script>
</body>
</html>
