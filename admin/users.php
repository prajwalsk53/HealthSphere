<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$user = getCurrentUser(); $uid = $user['id'];

$success = $error = '';

if (isset($_GET['toggle'])) {
    $tid = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id=?")->execute([$tid]);
    $success = 'User status updated.';
}
if (isset($_GET['delete']) && (int)$_GET['delete'] !== $uid) {
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([(int)$_GET['delete']]);
    $success = 'User deleted.';
}

$search = trim($_GET['q'] ?? '');
$role   = $_GET['role'] ?? '';
$where  = 'WHERE 1=1';
$params = [];
if ($search) { $where .= ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.nhs_id LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]); }
if ($role)   { $where .= ' AND u.role=?'; $params[] = $role; }

$stmt = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM appointments WHERE patient_id=u.id OR doctor_id=u.id) as appt_count FROM users u $where ORDER BY u.created_at DESC LIMIT 50");
$stmt->execute($params); $users = $stmt->fetchAll();
$notifCount = getUnreadCount($pdo, $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management — HealthSphere Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div><div class="page-title"><i class="fas fa-users" style="color:var(--hs-blue);"></i> User Management</div><div class="page-subtitle"><?= count($users) ?> users found</div></div>
    <div class="topbar-actions" style="gap:8px;flex:1;max-width:600px;">
      <form method="GET" style="display:flex;gap:8px;flex:1;">
        <div class="input-icon-wrap" style="flex:1;"><i class="fas fa-search"></i><input type="text" name="q" class="form-control" placeholder="Search by name, email, NHS ID..." value="<?= e($search) ?>"></div>
        <select name="role" class="form-select" style="width:150px;">
          <option value="">All Roles</option>
          <option value="patient" <?= $role==='patient'?'selected':'' ?>>Patient</option>
          <option value="doctor" <?= $role==='doctor'?'selected':'' ?>>Doctor</option>
          <option value="admin" <?= $role==='admin'?'selected':'' ?>>Admin</option>
          <option value="government" <?= $role==='government'?'selected':'' ?>>Government</option>
        </select>
        <button type="submit" class="btn-hs btn-primary-hs btn-sm-hs"><i class="fas fa-filter"></i> Filter</button>
      </form>
    </div>
  </div>

  <div class="hs-content">
    <?php if ($success): ?><div style="background:#DCFCE7;border:1px solid #BBF7D0;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#166534;font-size:13px;font-weight:600;"><i class="fas fa-check-circle"></i> <?= $success ?></div><?php endif; ?>

    <div class="hs-card">
      <div class="hs-card-header">
        <span class="card-title"><i class="fas fa-users"></i> User Login Management</span>
        <div style="font-size:13px;color:var(--hs-muted);"><?= count($users) ?> users · <button class="btn-hs btn-outline-hs btn-sm-hs"><i class="fas fa-download"></i> Export</button></div>
      </div>
      <div class="hs-card-body p-0">
        <table class="hs-table">
          <thead>
            <tr>
              <th><input type="checkbox" onchange="document.querySelectorAll('.row-chk').forEach(c=>c.checked=this.checked)"></th>
              <th>User Name</th><th>NHS Login ID</th><th>Role</th><th>IP Address</th><th>Date of Birth</th><th>Cell IMEI</th><th>Access</th><th>Last Logged In</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td><input type="checkbox" class="row-chk"></td>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div style="width:34px;height:34px;border-radius:50%;background:var(--hs-blue-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:11px;"><?= strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) ?></div>
                  <div><div style="font-weight:600;font-size:13.5px;"><?= e($u['first_name'].' '.$u['last_name']) ?></div><div style="font-size:11px;color:var(--hs-muted);"><?= e($u['email']) ?></div></div>
                </div>
              </td>
              <td style="font-family:monospace;font-size:12px;font-weight:600;"><?= e($u['nhs_id'] ?? '—') ?></td>
              <td><span style="text-transform:capitalize;font-weight:600;color:var(--hs-navy);"><?= $u['role'] ?></span></td>
              <td style="font-family:monospace;font-size:12px;">83.146.***</td>
              <td style="font-size:12px;"><?= $u['date_of_birth'] ? formatDate($u['date_of_birth'], 'd-m-Y') : '—' ?></td>
              <td style="font-family:monospace;font-size:11px;"><?= rand(100000000000000, 999999999999999) ?></td>
              <td><?= $u['is_active'] ? '<span class="status-pill approved">Approved</span>' : '<span class="status-pill suspended">Suspended</span>' ?></td>
              <td style="font-size:12px;"><?= $u['last_login'] ? timeAgo($u['last_login']) : 'Never' ?></td>
              <td>
                <div style="display:flex;gap:6px;">
                  <a href="?toggle=<?= $u['id'] ?>" class="btn-hs btn-outline-hs btn-sm-hs" title="<?= $u['is_active']?'Suspend':'Activate' ?>">
                    <i class="fas fa-<?= $u['is_active']?'ban':'check' ?>"></i>
                  </a>
                  <?php if ($u['id'] !== $uid): ?>
                  <a href="?delete=<?= $u['id'] ?>" class="btn-hs btn-danger-hs btn-sm-hs" onclick="return confirm('Delete this user?')">
                    <i class="fas fa-trash"></i>
                  </a>
                  <?php endif; ?>
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
