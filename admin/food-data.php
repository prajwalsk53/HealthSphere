<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$user = getCurrentUser(); $uid = $user['id'];

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_food'])) {
    $stmt = $pdo->prepare("INSERT INTO food_database (food_code,food_name,category,calories_per_100g,protein_g,sugar_g,fats_g,fiber_g,sodium_mg,health_rating,avoid_if,allergy_risk,vitamins_minerals,portion_size) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $code = 'FD'.rand(200,999);
    try {
        $stmt->execute([$code,$_POST['food_name'],$_POST['category'],$_POST['calories'],$_POST['protein'],$_POST['sugar'],$_POST['fats'],$_POST['fiber'],$_POST['sodium'],$_POST['health_rating'],$_POST['avoid_if'],$_POST['allergy_risk'],$_POST['vitamins'],$_POST['portion']]);
        $success = 'Food item added!';
    } catch (\Exception $e) { $error = 'Error: '.$e->getMessage(); }
}
if (isset($_GET['delete'])) { $pdo->prepare("DELETE FROM food_database WHERE id=?")->execute([(int)$_GET['delete']]); $success='Food item deleted.'; }

$search = trim($_GET['q'] ?? '');
$where  = $search ? "WHERE food_name LIKE ? OR category LIKE ?" : '';
$foods  = $pdo->prepare("SELECT * FROM food_database $where ORDER BY health_rating, food_name LIMIT 50");
$foods->execute($search ? ["%$search%","%$search%"] : []);
$foods = $foods->fetchAll();
$notifCount = getUnreadCount($pdo, $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Food Database — HealthSphere Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div><div class="page-title"><i class="fas fa-drumstick-bite" style="color:var(--hs-blue);"></i> Food Database</div><div class="page-subtitle"><?= count($foods) ?> items</div></div>
    <div class="topbar-actions">
      <form method="GET" style="display:flex;gap:8px;">
        <div class="input-icon-wrap" style="width:240px;"><i class="fas fa-search"></i><input type="text" name="q" class="form-control" placeholder="Search foods..." value="<?= e($search) ?>"></div>
        <button type="submit" class="btn-hs btn-primary-hs btn-sm-hs"><i class="fas fa-search"></i></button>
      </form>
      <button class="btn-hs btn-primary-hs btn-sm-hs" onclick="document.getElementById('addFoodModal').style.display='flex'"><i class="fas fa-plus"></i> Add Food Item</button>
    </div>
  </div>
  <div class="hs-content">
    <?php if ($success): ?><div style="background:#DCFCE7;border:1px solid #BBF7D0;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#166534;font-size:13px;font-weight:600;"><i class="fas fa-check-circle"></i> <?= $success ?></div><?php endif; ?>
    <div class="hs-card">
      <div class="hs-card-header"><span class="card-title"><i class="fas fa-table"></i> Food Diets Data Table</span></div>
      <div class="hs-card-body p-0">
        <table class="hs-table" style="min-width:1100px;">
          <thead><tr><th><input type="checkbox"></th><th>Food ID</th><th>Food Name</th><th>Category</th><th>Cal/100g</th><th>Protein</th><th>Sugar</th><th>Fats</th><th>Fiber</th><th>Health Rating</th><th>Avoid If</th><th>Allergy Risk</th><th>Vitamins/Mineral</th><th>Portion/Limit</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($foods as $f):
              $ratingMap = ['excellent'=>['bg-success','Excellent'],'good'=>['bg-primary','Good'],'moderate'=>['bg-warning text-dark','Moderate'],'poor'=>['bg-danger','Poor']];
              [$rc, $rl] = $ratingMap[$f['health_rating']] ?? ['bg-secondary','—'];
            ?>
            <tr>
              <td><input type="checkbox"></td>
              <td style="font-family:monospace;font-size:12px;font-weight:600;"><?= e($f['food_code']) ?></td>
              <td><strong><?= e($f['food_name']) ?></strong></td>
              <td><span style="background:var(--hs-off-white);padding:2px 8px;border-radius:4px;font-size:12px;"><?= e($f['category']) ?></span></td>
              <td><?= $f['calories_per_100g'] ?> kcal</td>
              <td><?= $f['protein_g'] ?>g</td>
              <td><?= $f['sugar_g'] ?>g</td>
              <td><?= $f['fats_g'] ?>g</td>
              <td><?= $f['fiber_g'] ?>g</td>
              <td><span class="badge <?= $rc ?>"><?= $rl ?></span></td>
              <td style="font-size:12px;"><?= e($f['avoid_if'] ?: '—') ?></td>
              <td style="font-size:12px;"><?= e($f['allergy_risk'] ?: 'None') ?></td>
              <td style="font-size:12px;"><?= e($f['vitamins_minerals'] ?: '—') ?></td>
              <td style="font-size:12px;"><?= e($f['portion_size'] ?: '—') ?></td>
              <td>
                <div style="display:flex;gap:4px;">
                  <button class="btn-hs btn-outline-hs btn-sm-hs"><i class="fas fa-eye"></i></button>
                  <a href="?delete=<?= $f['id'] ?>" class="btn-hs btn-danger-hs btn-sm-hs" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Add Food Modal -->
<div id="addFoodModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2000;align-items:center;justify-content:center;padding:20px;overflow-y:auto;">
  <div style="background:#fff;border-radius:16px;width:100%;max-width:580px;box-shadow:var(--shadow-lg);overflow:hidden;">
    <div style="background:var(--hs-navy);color:#fff;padding:18px 24px;display:flex;justify-content:space-between;align-items:center;">
      <h5 style="margin:0;font-size:16px;font-weight:700;"><i class="fas fa-plus"></i> Add Food Item</h5>
      <button onclick="document.getElementById('addFoodModal').style.display='none'" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;">×</button>
    </div>
    <form method="POST" style="padding:24px;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
        <div><label class="form-label">Food Name *</label><input type="text" name="food_name" class="form-control" required placeholder="e.g. Grilled Salmon"></div>
        <div><label class="form-label">Category *</label><input type="text" name="category" class="form-control" required placeholder="e.g. Fish"></div>
        <div><label class="form-label">Calories per 100g</label><input type="number" name="calories" class="form-control" placeholder="0" step="0.1"></div>
        <div><label class="form-label">Protein (g)</label><input type="number" name="protein" class="form-control" placeholder="0" step="0.1"></div>
        <div><label class="form-label">Sugar (g)</label><input type="number" name="sugar" class="form-control" placeholder="0" step="0.1"></div>
        <div><label class="form-label">Fats (g)</label><input type="number" name="fats" class="form-control" placeholder="0" step="0.1"></div>
        <div><label class="form-label">Fiber (g)</label><input type="number" name="fiber" class="form-control" placeholder="0" step="0.1"></div>
        <div><label class="form-label">Sodium (mg)</label><input type="number" name="sodium" class="form-control" placeholder="0" step="0.1"></div>
        <div><label class="form-label">Health Rating</label><select name="health_rating" class="form-select"><option value="excellent">Excellent</option><option value="good" selected>Good</option><option value="moderate">Moderate</option><option value="poor">Poor</option></select></div>
        <div><label class="form-label">Allergy Risk</label><input type="text" name="allergy_risk" class="form-control" placeholder="e.g. Fish Allergy"></div>
        <div style="grid-column:1/-1"><label class="form-label">Avoid If</label><input type="text" name="avoid_if" class="form-control" placeholder="e.g. High Cholesterol"></div>
        <div><label class="form-label">Vitamins/Minerals</label><input type="text" name="vitamins" class="form-control" placeholder="e.g. Omega-3, Vitamin D"></div>
        <div><label class="form-label">Portion/Limit</label><input type="text" name="portion" class="form-control" placeholder="e.g. 150g / 1 serving"></div>
      </div>
      <div style="display:flex;gap:12px;">
        <button type="submit" name="add_food" class="btn-hs btn-primary-hs" style="flex:1;justify-content:center;"><i class="fas fa-plus"></i> Add Food</button>
        <button type="button" onclick="document.getElementById('addFoodModal').style.display='none'" class="btn-hs btn-outline-hs">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
