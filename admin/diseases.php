<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$user = getCurrentUser(); $uid = $user['id'];
$diseases = $pdo->query("SELECT * FROM genetic_diseases ORDER BY patient_count DESC")->fetchAll();
$notifCount = getUnreadCount($pdo, $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Genetic Diseases — HealthSphere Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div><div class="page-title"><i class="fas fa-dna" style="color:var(--hs-blue);"></i> Genetic Diseases Registry</div><div class="page-subtitle"><?= count($diseases) ?> conditions</div></div>
  </div>
  <div class="hs-content">
    <div class="hs-card">
      <div class="hs-card-header"><span class="card-title"><i class="fas fa-dna"></i> Genetic Diseases</span></div>
      <div class="hs-card-body p-0" style="overflow-x:auto;">
        <table class="hs-table" style="min-width:1000px;">
          <thead><tr><th><input type="checkbox"></th><th>Name</th><th>Inheritance</th><th>Patients</th><th>Key Symptoms</th><th>Food Triggers</th><th>Recommended Foods</th><th>Exercise Guidance</th><th>Care Plan</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($diseases as $d):
              $cp = ['intensive'=>'bg-danger','moderate'=>'bg-warning text-dark','standard'=>'bg-info','preventive'=>'bg-success'][$d['care_plan']] ?? 'bg-secondary';
              $inherit = str_replace('_',' ',ucfirst($d['inheritance_type']));
            ?>
            <tr>
              <td><input type="checkbox"></td>
              <td><strong><?= e($d['disease_name']) ?></strong></td>
              <td style="font-size:12px;"><?= $inherit ?></td>
              <td style="font-weight:700;color:var(--hs-blue);"><?= number_format($d['patient_count']) ?></td>
              <td style="font-size:12px;max-width:150px;"><?= e(substr($d['key_symptoms'],0,80)).'...' ?></td>
              <td style="font-size:12px;max-width:120px;"><?= e(substr($d['food_triggers'],0,60)).'...' ?></td>
              <td style="font-size:12px;max-width:130px;"><?= e(substr($d['recommended_foods'],0,70)).'...' ?></td>
              <td style="font-size:12px;max-width:120px;"><?= e(substr($d['exercise_guidance'],0,70)).'...' ?></td>
              <td><span class="badge <?= $cp ?>"><?= ucfirst($d['care_plan']) ?></span></td>
              <td>
                <div style="display:flex;gap:4px;">
                  <button class="btn-hs btn-outline-hs btn-sm-hs" title="View"><i class="fas fa-eye"></i></button>
                  <button class="btn-hs btn-outline-hs btn-sm-hs" title="Edit"><i class="fas fa-edit"></i></button>
                  <button class="btn-hs btn-danger-hs btn-sm-hs" title="Delete"><i class="fas fa-trash"></i></button>
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
<script src="../assets/js/main.js"></script>
</body>
</html>
