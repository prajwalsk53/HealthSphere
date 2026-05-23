<?php
require_once __DIR__ . '/../config/config.php';
requireRole('pharmacy');
$user = getCurrentUser();
$uid  = $user['id'];
$notifCount = getUnreadCount($pdo, $uid);

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim($_POST['first_name'] ?? '');
    $lname = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if ($fname && $lname) {
        $pdo->prepare("UPDATE users SET first_name=?,last_name=?,phone=? WHERE id=?")
            ->execute([$fname, $lname, $phone, $uid]);
        $_SESSION['user_first'] = $fname;
        $_SESSION['user_last']  = $lname;
        $success = 'Profile updated successfully!';
    } else {
        $error = 'Name is required.';
    }
}

$u = $pdo->prepare("SELECT * FROM users WHERE id=?");
$u->execute([$uid]);
$u = $u->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Profile — HealthSphere Medical Team</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-user" style="color:var(--hs-blue);"></i> My Profile</div>
      <div class="page-subtitle">Medical Team — Pharmacy Staff</div>
    </div>
  </div>

  <div class="hs-content" style="max-width:680px;">

    <?php if ($success): ?>
    <div style="background:#DCFCE7;border:1px solid #BBF7D0;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#166534;font-size:13px;font-weight:600;">
      <i class="fas fa-check-circle"></i> <?= e($success) ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div style="background:#FEE2E2;border:1px solid #FECACA;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#991B1B;font-size:13px;">
      <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
    </div>
    <?php endif; ?>

    <!-- Identity card -->
    <div class="hs-card" style="margin-bottom:20px;">
      <div class="hs-card-body" style="display:flex;align-items:center;gap:20px;">
        <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#1565C0,#16A34A);display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:800;color:#fff;flex-shrink:0;">
          <?= strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) ?>
        </div>
        <div>
          <div style="font-size:20px;font-weight:800;color:var(--hs-navy);"><?= e($u['first_name'].' '.$u['last_name']) ?></div>
          <div style="font-size:13px;color:var(--hs-blue);font-weight:600;margin-top:2px;"><i class="fas fa-pills"></i> Medical Team — Pharmacy</div>
          <div style="font-size:12px;color:var(--hs-muted);margin-top:4px;"><i class="fas fa-id-badge"></i> NHS ID: <?= e($u['nhs_id'] ?? '—') ?></div>
        </div>
        <div style="margin-left:auto;">
          <span style="background:#F0FDF4;color:#166534;border:1px solid #BBF7D0;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:700;">
            <i class="fas fa-check-circle"></i> Active
          </span>
        </div>
      </div>
    </div>

    <!-- Edit form -->
    <div class="hs-card">
      <div class="hs-card-header">
        <span class="card-title"><i class="fas fa-edit"></i> Edit Details</span>
      </div>
      <div class="hs-card-body">
        <form method="POST">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
            <div>
              <label class="form-label">First Name *</label>
              <input type="text" name="first_name" class="form-control" value="<?= e($u['first_name']) ?>" required>
            </div>
            <div>
              <label class="form-label">Last Name *</label>
              <input type="text" name="last_name" class="form-control" value="<?= e($u['last_name']) ?>" required>
            </div>
          </div>
          <div style="margin-bottom:14px;">
            <label class="form-label">Email Address</label>
            <input type="email" class="form-control" value="<?= e($u['email']) ?>" disabled style="opacity:.6;cursor:not-allowed;">
          </div>
          <div style="margin-bottom:20px;">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="form-control" value="<?= e($u['phone'] ?? '') ?>" placeholder="07700000000">
          </div>
          <button type="submit" class="btn-hs btn-primary-hs">
            <i class="fas fa-save"></i> Save Changes
          </button>
        </form>
      </div>
    </div>

  </div>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
