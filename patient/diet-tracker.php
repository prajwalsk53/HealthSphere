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

<!-- ── Log Meal Modal ──────────────────────────────────────────────── -->
<style>
.meal-type-tabs { display:flex; gap:6px; margin-bottom:18px; }
.mtt-btn {
  flex:1; padding:9px 6px; border-radius:10px; border:2px solid var(--hs-border);
  background:#fff; font-size:12px; font-weight:700; cursor:pointer;
  display:flex; flex-direction:column; align-items:center; gap:4px;
  color:var(--hs-muted); transition:var(--transition);
}
.mtt-btn:hover { border-color:var(--hs-blue); color:var(--hs-blue); background:#EFF6FF; }
.mtt-btn.active { border-color:var(--hs-blue); background:var(--hs-blue); color:#fff; }
.mtt-emoji { font-size:18px; }

.food-search-wrap { position:relative; margin-bottom:14px; }
.food-search-wrap .fs-icon {
  position:absolute; left:12px; top:50%; transform:translateY(-50%);
  color:var(--hs-muted); font-size:14px; pointer-events:none;
}
.food-search-wrap input { padding-left:36px !important; }

#foodDropdown {
  position:absolute; top:calc(100% + 4px); left:0; right:0; z-index:3000;
  background:#fff; border:1.5px solid var(--hs-border); border-radius:12px;
  box-shadow:0 8px 24px rgba(10,31,68,.14); max-height:280px; overflow-y:auto;
  display:none;
}
.fd-item {
  display:flex; align-items:center; gap:12px; padding:10px 14px;
  cursor:pointer; transition:background .15s; border-bottom:1px solid #F3F4F6;
}
.fd-item:last-child { border-bottom:none; }
.fd-item:hover { background:#EFF6FF; }
.fd-icon {
  width:36px; height:36px; border-radius:10px; display:flex; align-items:center;
  justify-content:center; font-size:16px; flex-shrink:0;
}
.fd-info { flex:1; min-width:0; }
.fd-name { font-size:13px; font-weight:700; color:var(--hs-navy); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.fd-meta { font-size:11px; color:var(--hs-muted); margin-top:2px; }
.fd-cal { font-size:12px; font-weight:700; color:var(--hs-blue); flex-shrink:0; }
.fd-rating { font-size:10px; font-weight:700; padding:2px 7px; border-radius:8px; flex-shrink:0; }
.fd-rating.excellent { background:#DCFCE7; color:#166534; }
.fd-rating.good      { background:#DBEAFE; color:#1E40AF; }
.fd-rating.moderate  { background:#FEF3C7; color:#92400E; }
.fd-rating.poor      { background:#FEE2E2; color:#991B1B; }
.fd-empty { padding:20px; text-align:center; color:var(--hs-muted); font-size:13px; }

#selectedFoodCard {
  background: linear-gradient(135deg,#EFF6FF,#F0FDF4);
  border:1.5px solid #BFDBFE; border-radius:12px; padding:14px 16px;
  margin-bottom:14px; animation:fadeInCard .25s ease;
}
@keyframes fadeInCard { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:none} }
.macro-pills { display:flex; gap:6px; flex-wrap:wrap; margin-top:10px; }
.macro-pill {
  display:flex; flex-direction:column; align-items:center;
  padding:6px 12px; border-radius:10px; background:#fff;
  border:1.5px solid var(--hs-border); min-width:62px;
}
.macro-pill .mp-val { font-size:14px; font-weight:900; color:var(--hs-navy); }
.macro-pill .mp-lbl { font-size:9.5px; font-weight:700; color:var(--hs-muted); text-transform:uppercase; letter-spacing:.4px; margin-top:1px; }

.portion-row { display:flex; align-items:center; gap:10px; margin-top:12px; }
.portion-row label { font-size:12.5px; font-weight:700; color:var(--hs-navy); white-space:nowrap; }
.portion-row input { width:80px; text-align:center; font-weight:700; }
.portion-row .hint { font-size:11px; color:var(--hs-muted); }

.manual-toggle {
  font-size:12px; color:var(--hs-blue); background:none; border:none;
  cursor:pointer; text-decoration:underline; padding:0; margin-bottom:10px;
}
</style>

<div id="addMealModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:2000;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:18px;width:100%;max-width:540px;box-shadow:0 20px 60px rgba(10,31,68,.25);overflow:hidden;max-height:90vh;display:flex;flex-direction:column;">

    <!-- Header -->
    <div style="background:var(--hs-navy);color:#fff;padding:18px 24px;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;">
      <div>
        <div style="font-size:16px;font-weight:800;"><i class="fas fa-utensils"></i> Log Meal</div>
        <div style="font-size:11px;color:rgba(255,255,255,.6);margin-top:2px;">Search our food database or enter manually</div>
      </div>
      <button onclick="closeMealModal()" style="background:rgba(255,255,255,.15);border:none;color:#fff;width:30px;height:30px;border-radius:50%;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;">×</button>
    </div>

    <form method="POST" style="padding:20px 24px 24px;overflow-y:auto;flex:1;">

      <!-- Meal type tabs -->
      <div style="margin-bottom:16px;">
        <div style="font-size:11px;font-weight:700;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px;">Meal Type</div>
        <div class="meal-type-tabs">
          <button type="button" class="mtt-btn active" data-type="breakfast" onclick="setMealType('breakfast',this)">
            <span class="mtt-emoji">🌅</span>Breakfast
          </button>
          <button type="button" class="mtt-btn" data-type="lunch" onclick="setMealType('lunch',this)">
            <span class="mtt-emoji">☀️</span>Lunch
          </button>
          <button type="button" class="mtt-btn" data-type="snack" onclick="setMealType('snack',this)">
            <span class="mtt-emoji">🍎</span>Snack
          </button>
          <button type="button" class="mtt-btn" data-type="dinner" onclick="setMealType('dinner',this)">
            <span class="mtt-emoji">🌙</span>Dinner
          </button>
        </div>
        <input type="hidden" name="meal_type" id="mealTypeSelect" value="breakfast">
      </div>

      <!-- Food search -->
      <div style="margin-bottom:4px;">
        <div style="font-size:11px;font-weight:700;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px;">Search Food</div>
        <div class="food-search-wrap">
          <i class="fas fa-search fs-icon"></i>
          <input type="text" id="foodSearchInput" class="form-control"
            placeholder="e.g. Grilled Chicken, Oats, Salmon, Broccoli…"
            autocomplete="off"
            oninput="onFoodSearch(this.value)"
            onfocus="onFoodSearch(this.value)">
          <div id="foodDropdown"></div>
        </div>
      </div>

      <!-- Selected food card -->
      <div id="selectedFoodCard" style="display:none;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
          <span id="sfcEmoji" style="font-size:24px;"></span>
          <div style="flex:1;">
            <div id="sfcName" style="font-size:14px;font-weight:800;color:var(--hs-navy);"></div>
            <div style="display:flex;align-items:center;gap:6px;margin-top:3px;">
              <span id="sfcCategory" style="font-size:11px;background:#DBEAFE;color:#1E40AF;padding:2px 8px;border-radius:8px;font-weight:700;"></span>
              <span id="sfcRating" class="fd-rating"></span>
              <span id="sfcPortion" style="font-size:11px;color:var(--hs-muted);"></span>
            </div>
          </div>
          <button type="button" onclick="clearSelectedFood()" style="background:none;border:none;cursor:pointer;color:var(--hs-muted);font-size:16px;" title="Clear selection">×</button>
        </div>

        <div class="portion-row">
          <label>Portion:</label>
          <input type="number" id="portionInput" class="form-control" value="100" min="1" max="2000" oninput="recalcMacros()">
          <span style="font-size:13px;font-weight:700;color:var(--hs-muted);">g</span>
          <span class="hint" id="portionHint"></span>
        </div>

        <div class="macro-pills" id="macroPills">
          <div class="macro-pill"><div class="mp-val" id="mp-cal">0</div><div class="mp-lbl">kcal</div></div>
          <div class="macro-pill"><div class="mp-val" id="mp-prot">0g</div><div class="mp-lbl">Protein</div></div>
          <div class="macro-pill"><div class="mp-val" id="mp-carbs">0g</div><div class="mp-lbl">Carbs</div></div>
          <div class="macro-pill"><div class="mp-val" id="mp-fats">0g</div><div class="mp-lbl">Fats</div></div>
          <div class="macro-pill"><div class="mp-val" id="mp-fiber">0g</div><div class="mp-lbl">Fiber</div></div>
        </div>
      </div>

      <!-- Hidden actual form fields (populated by JS) -->
      <input type="hidden" name="food_name" id="foodNameInput">
      <input type="hidden" name="calories"  id="calInput">
      <input type="hidden" name="protein"   id="protInput">
      <input type="hidden" name="carbs"     id="carbsInput">
      <input type="hidden" name="fats"      id="fatsInput">
      <input type="hidden" name="fiber"     id="fiberInput">

      <!-- Manual entry toggle -->
      <div style="margin-top:12px;">
        <button type="button" class="manual-toggle" onclick="toggleManual()">
          <i class="fas fa-pencil-alt" style="font-size:10px;"></i> Food not found? Enter manually
        </button>
      </div>

      <!-- Manual fields (hidden by default) -->
      <div id="manualFields" style="display:none;border-top:1px solid var(--hs-border);padding-top:14px;margin-top:4px;">
        <div style="margin-bottom:12px;">
          <label class="form-label">Food Name *</label>
          <input type="text" id="manualFoodName" class="form-control" placeholder="e.g. Homemade Dal, Protein Bar…">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="form-label">Calories (kcal)</label>
            <input type="number" id="manualCal" class="form-control" placeholder="0" min="0" step="1">
          </div>
          <div>
            <label class="form-label">Protein (g)</label>
            <input type="number" id="manualProt" class="form-control" placeholder="0" min="0" step="0.1">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
          <div>
            <label class="form-label">Carbs (g)</label>
            <input type="number" id="manualCarbs" class="form-control" placeholder="0" min="0" step="0.1">
          </div>
          <div>
            <label class="form-label">Fats (g)</label>
            <input type="number" id="manualFats" class="form-control" placeholder="0" min="0" step="0.1">
          </div>
          <div>
            <label class="form-label">Fiber (g)</label>
            <input type="number" id="manualFiber" class="form-control" placeholder="0" min="0" step="0.1">
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div style="display:flex;gap:12px;margin-top:20px;">
        <button type="submit" name="add_meal" id="addMealBtn" class="btn-hs btn-primary-hs" style="flex:1;justify-content:center;" onclick="prepareSubmit()">
          <i class="fas fa-plus"></i> Add Meal
        </button>
        <button type="button" onclick="closeMealModal()" class="btn-hs btn-outline-hs">Cancel</button>
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
    const res = await fetch('<?= BASE_PATH ?>/api/meal-ai.php', {
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

// ── Meal modal helpers ────────────────────────────────────────────────
let selectedFood = null;   // currently picked food object from DB
let manualMode   = false;

const CATEGORY_EMOJI = {
  'Protein':'🍗','Fish':'🐟','Grain':'🌾','Vegetable':'🥦','Fruit':'🍎',
  'Dairy':'🥛','Processed':'🏭','Legume':'🫘','Nut':'🥜','default':'🍽️'
};

function openAddMeal(type) {
  setMealType(type || 'breakfast', document.querySelector(`.mtt-btn[data-type="${type||'breakfast'}"]`));
  document.getElementById('addMealModal').style.display = 'flex';
  // Load popular foods on open
  onFoodSearch('');
  setTimeout(() => document.getElementById('foodSearchInput').focus(), 120);
}

function closeMealModal() {
  document.getElementById('addMealModal').style.display = 'none';
  clearSelectedFood();
  document.getElementById('foodSearchInput').value = '';
  document.getElementById('foodDropdown').style.display = 'none';
  if (manualMode) toggleManual();
}

function setMealType(type, btn) {
  document.getElementById('mealTypeSelect').value = type;
  document.querySelectorAll('.mtt-btn').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  else document.querySelector(`.mtt-btn[data-type="${type}"]`)?.classList.add('active');
}

// ── Food search — calls local DB + Spoonacular in parallel ───────────
let searchTimer = null;

async function onFoodSearch(q) {
  clearTimeout(searchTimer);
  const delay = q.length >= 2 ? 280 : 0;
  searchTimer = setTimeout(async () => {
    const drop = document.getElementById('foodDropdown');
    drop.innerHTML = '<div class="fd-empty"><i class="fas fa-circle-notch fa-spin"></i> Searching…</div>';
    drop.style.display = 'block';

    try {
      // Fire both requests simultaneously
      const [localRes, spoonRes] = await Promise.allSettled([
        fetch(`<?= BASE_PATH ?>/api/food-search.php?q=${encodeURIComponent(q)}`),
        fetch(`<?= BASE_PATH ?>/api/spoonacular-search.php?q=${encodeURIComponent(q)}`),
      ]);

      const localFoods = localRes.status === 'fulfilled'
        ? await localRes.value.json().catch(() => [])
        : [];
      const spoonRaw   = spoonRes.status === 'fulfilled'
        ? await spoonRes.value.json().catch(() => [])
        : [];
      // _no_key is a sentinel object, not an array
      const spoonFoods = Array.isArray(spoonRaw) ? spoonRaw : [];
      const spoonNoKey = !Array.isArray(spoonRaw) && spoonRaw._no_key;

      renderDropdown(localFoods, spoonFoods, q, spoonNoKey);
    } catch(e) {
      drop.innerHTML = '<div class="fd-empty">Could not load foods.</div>';
    }
  }, delay);
}

const CAT_COLOR = {
  Protein:'#FEE2E2', Fish:'#DBEAFE', Grain:'#FEF3C7', Vegetable:'#DCFCE7',
  Fruit:'#FCE7F3', Dairy:'#F0F9FF', Processed:'#F3F4F6', 'Nuts & Seeds':'#FEF9C3',
  Drinks:'#E0F2FE', 'Fats & Oils':'#FFF7ED', Spices:'#F5F3FF',
  Condiments:'#FDF4FF', Sweets:'#FFF0F0', Other:'#F3F4F6',
};

function hl(str, q) {
  if (!q) return str;
  return str.replace(new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')})`, 'gi'),
    '<mark style="background:#FEF08A;border-radius:2px;padding:0 1px;">$1</mark>');
}

function foodItemHTML(f, q) {
  const emoji  = CATEGORY_EMOJI[f.category] || CATEGORY_EMOJI.default;
  const bg     = CAT_COLOR[f.category] || '#F3F4F6';
  const isSpoon = f.source === 'spoonacular';

  const thumb = isSpoon && f.image
    ? `<img src="${f.image}" style="width:36px;height:36px;border-radius:10px;object-fit:cover;" onerror="this.style.display='none'">`
    : `<div class="fd-icon" style="background:${bg};">${emoji}</div>`;

  const calStr = f.calories_per_100g != null
    ? `${Math.round(f.calories_per_100g)}<br><span style="font-size:9px;font-weight:500;">kcal/100g</span>`
    : `<i class="fas fa-circle-notch" style="font-size:11px;color:#9CA3AF;"></i><br><span style="font-size:9px;color:#9CA3AF;">tap to load</span>`;

  const sourceBadge = isSpoon
    ? `<span style="font-size:9px;font-weight:700;background:#E0F2FE;color:#0369A1;padding:1px 5px;border-radius:4px;margin-left:4px;">🌐 Spoonacular</span>`
    : '';

  const safeF = JSON.stringify(f).replace(/'/g, '&#39;');
  return `<div class="fd-item" onclick='handleFoodSelect(${safeF})'>
    ${thumb}
    <div class="fd-info">
      <div class="fd-name">${hl(f.food_name, q)}${sourceBadge}</div>
      <div class="fd-meta">${f.category} · ${f.portion_size || '100g serving'}</div>
    </div>
    <span class="fd-rating ${f.health_rating || 'good'}">${f.health_rating || 'good'}</span>
    <div class="fd-cal">${calStr}</div>
  </div>`;
}

function renderDropdown(localFoods, spoonFoods, q, spoonNoKey) {
  const drop = document.getElementById('foodDropdown');
  let html = '';

  if (localFoods.length) {
    html += `<div style="padding:6px 14px 4px;font-size:10px;font-weight:800;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.6px;background:#F9FAFB;border-bottom:1px solid #F3F4F6;">
      <i class="fas fa-database" style="color:var(--hs-blue);"></i> Health Database</div>`;
    html += localFoods.map(f => foodItemHTML(f, q)).join('');
  }

  if (spoonFoods.length) {
    html += `<div style="padding:6px 14px 4px;font-size:10px;font-weight:800;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.6px;background:#F0F9FF;border-bottom:1px solid #E0F2FE;border-top:2px solid #E0F2FE;">
      <i class="fas fa-globe" style="color:#0284C7;"></i> Spoonacular — 80,000+ Foods</div>`;
    html += spoonFoods.map(f => foodItemHTML(f, q)).join('');
  } else if (q.length >= 2 && !spoonNoKey) {
    html += `<div style="padding:8px 14px;font-size:11.5px;color:var(--hs-muted);background:#F0F9FF;border-top:1px solid #E0F2FE;display:flex;align-items:center;gap:8px;">
      <i class="fas fa-circle-notch fa-spin" style="color:#0284C7;"></i> Searching Spoonacular…</div>`;
  }

  if (!html) {
    html = `<div class="fd-empty">No foods found for "<b>${q || 'your search'}</b>".<br>
      <small>Try a different term or use "Enter manually" below.</small></div>`;
  }

  drop.innerHTML = html;
  drop.style.display = 'block';
}

// ── Handle food selection (local = instant, Spoonacular = fetch macros) ──
async function handleFoodSelect(food) {
  document.getElementById('foodDropdown').style.display = 'none';
  document.getElementById('foodSearchInput').value = food.food_name;
  if (manualMode) toggleManual();

  if (food.source === 'spoonacular' && food.calories_per_100g == null) {
    // Show card in loading state first
    showFoodCard(food, true);
    try {
      const res  = await fetch(`<?= BASE_PATH ?>/api/spoonacular-info.php?id=${food.spoonacular_id}`);
      const info = await res.json();
      if (info.ok) {
        // Merge full macro data into the food object
        food.calories_per_100g = info.calories_per_100g;
        food.protein_g         = info.protein_g;
        food.sugar_g           = info.carbs_g;   // map carbs → sugar field
        food.fats_g            = info.fats_g;
        food.fiber_g           = info.fiber_g;
        food.health_rating     = healthRating(info.calories_per_100g, info.fats_g, info.fiber_g);
        selectedFood = food;
        showFoodCard(food, false);
      } else {
        document.getElementById('sfcName').textContent += ' — nutrition unavailable';
      }
    } catch(e) {
      document.getElementById('sfcName').textContent += ' (could not load nutrition)';
    }
  } else {
    selectFood(food);
  }
}

// Estimate health rating from macros
function healthRating(cal, fat, fiber) {
  if (cal < 100 && fiber > 2)    return 'excellent';
  if (cal < 200 && fat < 10)     return 'good';
  if (cal < 400)                  return 'moderate';
  return 'poor';
}

function showFoodCard(food, loading) {
  const emoji = food.image && food.source === 'spoonacular'
    ? `<img src="${food.image}" style="width:40px;height:40px;border-radius:10px;object-fit:cover;" onerror="this.outerHTML='🍽️'">`
    : (CATEGORY_EMOJI[food.category] || '🍽️');

  document.getElementById('sfcEmoji').innerHTML   = typeof emoji === 'string' && emoji.length < 5 ? emoji : '';
  if (food.image && food.source === 'spoonacular') {
    document.getElementById('sfcEmoji').innerHTML = `<img src="${food.image}" style="width:40px;height:40px;border-radius:10px;object-fit:cover;" onerror="this.outerHTML='🍽️'">`;
  } else {
    document.getElementById('sfcEmoji').textContent = CATEGORY_EMOJI[food.category] || '🍽️';
  }

  document.getElementById('sfcName').textContent     = food.food_name;
  document.getElementById('sfcCategory').textContent = food.category;

  const ratingEl = document.getElementById('sfcRating');
  ratingEl.textContent = food.health_rating || 'good';
  ratingEl.className   = `fd-rating ${food.health_rating || 'good'}`;

  const portionMatch = (food.portion_size || '').match(/(\d+)g/);
  const suggestedG   = portionMatch ? parseInt(portionMatch[1]) : 100;
  document.getElementById('portionInput').value = suggestedG;
  document.getElementById('portionHint').textContent = food.portion_size ? `Suggested: ${food.portion_size}` : '';

  document.getElementById('selectedFoodCard').style.display = 'block';

  if (loading) {
    ['mp-cal','mp-prot','mp-carbs','mp-fats','mp-fiber'].forEach(id => {
      document.getElementById(id).innerHTML = '<i class="fas fa-circle-notch fa-spin" style="font-size:11px;color:#9CA3AF;"></i>';
    });
  } else {
    recalcMacros();
  }
}

function selectFood(food) {
  selectedFood = food;
  showFoodCard(food, false);
}

function recalcMacros() {
  if (!selectedFood || selectedFood.calories_per_100g == null) return;
  const portion = parseFloat(document.getElementById('portionInput').value) || 100;
  const factor  = portion / 100;

  const cal   = Math.round((selectedFood.calories_per_100g || 0) * factor);
  const prot  = ((selectedFood.protein_g || 0) * factor).toFixed(1);
  const carbs = ((selectedFood.sugar_g   || 0) * factor).toFixed(1);
  const fats  = ((selectedFood.fats_g    || 0) * factor).toFixed(1);
  const fiber = ((selectedFood.fiber_g   || 0) * factor).toFixed(1);

  document.getElementById('mp-cal').textContent   = cal;
  document.getElementById('mp-prot').textContent  = prot + 'g';
  document.getElementById('mp-carbs').textContent = carbs + 'g';
  document.getElementById('mp-fats').textContent  = fats + 'g';
  document.getElementById('mp-fiber').textContent = fiber + 'g';

  const calPill = document.getElementById('mp-cal').closest('.macro-pill');
  calPill.style.borderColor = cal > 600 ? '#FCA5A5' : cal > 300 ? '#FCD34D' : '#86EFAC';
}

function clearSelectedFood() {
  selectedFood = null;
  document.getElementById('selectedFoodCard').style.display = 'none';
  document.getElementById('foodSearchInput').value = '';
}

function toggleManual() {
  manualMode = !manualMode;
  document.getElementById('manualFields').style.display = manualMode ? 'block' : 'none';
  document.querySelector('.manual-toggle').innerHTML = manualMode
    ? '<i class="fas fa-search" style="font-size:10px;"></i> Search food database instead'
    : '<i class="fas fa-pencil-alt" style="font-size:10px;"></i> Food not found? Enter manually';
  if (manualMode) clearSelectedFood();
}

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('.food-search-wrap')) {
    document.getElementById('foodDropdown').style.display = 'none';
  }
});

// Populate hidden form fields just before submit
function prepareSubmit() {
  if (manualMode) {
    const name = document.getElementById('manualFoodName').value.trim();
    if (!name) { alert('Please enter a food name.'); return false; }
    document.getElementById('foodNameInput').value = name;
    document.getElementById('calInput').value   = document.getElementById('manualCal').value   || 0;
    document.getElementById('protInput').value  = document.getElementById('manualProt').value  || 0;
    document.getElementById('carbsInput').value = document.getElementById('manualCarbs').value || 0;
    document.getElementById('fatsInput').value  = document.getElementById('manualFats').value  || 0;
    document.getElementById('fiberInput').value = document.getElementById('manualFiber').value || 0;
  } else if (selectedFood) {
    const portion = parseFloat(document.getElementById('portionInput').value) || 100;
    const factor  = portion / 100;
    document.getElementById('foodNameInput').value = selectedFood.food_name;
    document.getElementById('calInput').value   = Math.round(selectedFood.calories_per_100g * factor);
    document.getElementById('protInput').value  = (selectedFood.protein_g * factor).toFixed(1);
    document.getElementById('carbsInput').value = (selectedFood.sugar_g   * factor).toFixed(1);
    document.getElementById('fatsInput').value  = (selectedFood.fats_g    * factor).toFixed(1);
    document.getElementById('fiberInput').value = (selectedFood.fiber_g   * factor).toFixed(1);
  } else {
    alert('Please search and select a food, or use "Enter manually".');
    return false;
  }
}

// quickAddFood: called from recommendation cards (kept for backwards compat)
function quickAddFood(name) {
  document.getElementById('addMealModal').style.display = 'flex';
  document.getElementById('foodSearchInput').value = name;
  onFoodSearch(name);
}
</script>
</body>
</html>
