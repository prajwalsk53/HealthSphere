<?php
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
$user = getCurrentUser(); $uid = $user['id'];

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim($_POST['first_name'] ?? '');
    $lname = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $addr  = trim($_POST['address'] ?? '');
    $city  = trim($_POST['city'] ?? '');
    $blood = $_POST['blood_type'] ?? '';
    if ($fname && $lname) {
        $pdo->prepare("UPDATE users SET first_name=?,last_name=?,phone=?,address=?,city=?,blood_type=? WHERE id=?")->execute([$fname,$lname,$phone,$addr,$city,$blood,$uid]);
        $_SESSION['user_first'] = $fname; $_SESSION['user_last'] = $lname;
        $success = 'Profile updated successfully!';
    } else { $error = 'Name is required.'; }
}

$u = $pdo->prepare("SELECT * FROM users WHERE id=?"); $u->execute([$uid]); $u = $u->fetch();
$notifCount = getUnreadCount($pdo, $uid); $msgCount = getUnreadMessages($pdo, $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div><div class="page-title"><i class="fas fa-user" style="color:var(--hs-blue);"></i> My Profile</div></div>
  </div>
  <div class="hs-content">
    <div style="display:grid;grid-template-columns:280px 1fr;gap:20px;max-width:900px;">
      <!-- Profile card -->
      <div class="hs-card" style="text-align:center;padding:24px;">
        <div style="width:80px;height:80px;border-radius:50%;background:var(--hs-blue-grad);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:30px;color:#fff;font-weight:800;">
          <?= strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) ?>
        </div>
        <h5 style="margin:0;color:var(--hs-navy);"><?= e($u['first_name'].' '.$u['last_name']) ?></h5>
        <p style="color:var(--hs-blue);font-size:13px;margin:4px 0;">Patient</p>
        <div style="background:var(--hs-off-white);border-radius:8px;padding:10px;margin-top:12px;font-family:monospace;font-size:13px;">
          <div style="font-size:10px;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.5px;">NHS ID</div>
          <strong><?= e($u['nhs_id']) ?></strong>
        </div>
        <div class="divider-hs"></div>
        <div style="font-size:13px;color:var(--hs-muted);text-align:left;">
          <div style="margin-bottom:8px;"><i class="fas fa-envelope" style="width:18px;color:var(--hs-blue);"></i> <?= e($u['email']) ?></div>
          <div style="margin-bottom:8px;"><i class="fas fa-phone" style="width:18px;color:var(--hs-blue);"></i> <?= e($u['phone'] ?: '—') ?></div>
          <div style="margin-bottom:8px;"><i class="fas fa-tint" style="width:18px;color:var(--hs-blue);"></i> <?= e($u['blood_type'] ?: '—') ?></div>
          <div><i class="fas fa-birthday-cake" style="width:18px;color:var(--hs-blue);"></i> <?= $u['date_of_birth'] ? formatDate($u['date_of_birth']) : '—' ?></div>
        </div>
        <div class="divider-hs"></div>
        <div style="font-size:12px;color:var(--hs-muted);">Member since <?= formatDate($u['created_at'], 'M Y') ?></div>
      </div>

      <!-- Edit form -->
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-edit"></i> Edit Profile</span></div>
        <div class="hs-card-body">
          <?php if ($success): ?><div style="background:#DCFCE7;border-radius:8px;padding:10px 14px;margin-bottom:16px;color:#166534;font-size:13px;font-weight:600;"><i class="fas fa-check-circle"></i> <?= $success ?></div><?php endif; ?>
          <?php if ($error): ?><div style="background:#FEE2E2;border-radius:8px;padding:10px 14px;margin-bottom:16px;color:#991B1B;font-size:13px;"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>
          <form method="POST">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
              <div><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-control" value="<?= e($u['first_name']) ?>" required></div>
              <div><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-control" value="<?= e($u['last_name']) ?>" required></div>
              <div><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control" value="<?= e($u['phone'] ?? '') ?>"></div>
              <div><label class="form-label">Blood Type</label>
                <select name="blood_type" class="form-select">
                  <option value="">Select</option>
                  <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                  <option value="<?= $bt ?>" <?= $u['blood_type']===$bt?'selected':'' ?>><?= $bt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div style="grid-column:1/-1"><label class="form-label">Address</label><input type="text" name="address" class="form-control" value="<?= e($u['address'] ?? '') ?>"></div>
              <div><label class="form-label">City</label><input type="text" name="city" class="form-control" value="<?= e($u['city'] ?? '') ?>"></div>
            </div>
            <button type="submit" class="btn-hs btn-primary-hs"><i class="fas fa-save"></i> Save Changes</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
