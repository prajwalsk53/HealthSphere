<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';
requireRole('admin');
$user = getCurrentUser();
$uid  = $user['id'];

$success = $error = '';

// ── Approve ────────────────────────────────────────────────────────
if (isset($_GET['approve'])) {
    $tid = (int)$_GET['approve'];
    $pdo->prepare("UPDATE users SET is_active=1, approval_status='approved' WHERE id=? AND approval_status='pending'")->execute([$tid]);
    // Also verify doctor profile
    $pdo->prepare("UPDATE doctors SET is_verified=1 WHERE user_id=?")->execute([$tid]);
    // Notify the user
    $u = $pdo->prepare("SELECT first_name, last_name, email, role FROM users WHERE id=?"); $u->execute([$tid]); $u=$u->fetch();
    if ($u) {
        $pdo->prepare("INSERT INTO notifications (user_id,title,message,notification_type) VALUES (?,?,?,'system')")->execute([
            $tid,
            'Account Approved — Welcome to HealthSphere',
            "Your {$u['role']} account has been approved by the administration team. You can now sign in.",
        ]);
        // Send approval email
        @mailAccountApproved($u['email'], $u['first_name'].' '.$u['last_name'], $u['role']);
    }
    logAccess($pdo, $uid, $tid, 'APPROVE_USER');
    $success = "Account approved successfully.";
}

// ── Reject ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_id'])) {
    $tid    = (int)$_POST['reject_id'];
    $reason = trim($_POST['reason'] ?? 'Application did not meet requirements.');
    $pdo->prepare("UPDATE users SET is_active=0, approval_status='rejected', rejection_reason=? WHERE id=?")->execute([$reason, $tid]);
    $u = $pdo->prepare("SELECT first_name, last_name, email, role FROM users WHERE id=?"); $u->execute([$tid]); $u=$u->fetch();
    if ($u) {
        $pdo->prepare("INSERT INTO notifications (user_id,title,message,notification_type) VALUES (?,?,?,'system')")->execute([
            $tid,
            'Account Application — Decision',
            "Your application has not been approved. Reason: {$reason}. Please contact admin@healthsphere.nhs.uk for further information.",
        ]);
        // Send rejection email
        @mailAccountRejected($u['email'], $u['first_name'].' '.$u['last_name'], $u['role'], $reason);
    }
    logAccess($pdo, $uid, $tid, 'REJECT_USER');
    $success = "Application rejected.";
}

// ── Fetch pending ──────────────────────────────────────────────────
$pending = $pdo->query("
    SELECT u.*, d.hcpc_number, d.specialization, d.hospital_name, d.experience_years, d.bio, d.consultation_fee
    FROM users u LEFT JOIN doctors d ON u.id=d.user_id
    WHERE u.approval_status='pending'
    ORDER BY u.created_at DESC
")->fetchAll();

$approved = $pdo->query("
    SELECT u.*, d.specialization, d.hospital_name
    FROM users u LEFT JOIN doctors d ON u.id=d.user_id
    WHERE u.approval_status='approved' AND u.role IN('doctor','government')
    ORDER BY u.created_at DESC LIMIT 20
")->fetchAll();

$rejected = $pdo->query("
    SELECT * FROM users
    WHERE approval_status='rejected'
    ORDER BY created_at DESC LIMIT 10
")->fetchAll();

$notifCount = getUnreadCount($pdo, $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Account Approvals — HealthSphere Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-user-check" style="color:var(--hs-blue);"></i> Account Approvals</div>
      <div class="page-subtitle"><?= count($pending) ?> pending &middot; <?= count($approved) ?> approved &middot; <?= count($rejected) ?> rejected</div>
    </div>
  </div>

  <div class="hs-content">
    <?php if ($success): ?><div style="background:#DCFCE7;border:1px solid #BBF7D0;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#166534;font-size:13px;font-weight:600;"><i class="fas fa-check-circle"></i> <?= e($success) ?></div><?php endif; ?>

    <!-- ══ PENDING ══ -->
    <div class="hs-card" style="margin-bottom:20px;">
      <div class="hs-card-header">
        <span class="card-title"><i class="fas fa-hourglass-half" style="color:#D97706;"></i> Pending Approval</span>
        <?php if ($pending): ?><span class="badge bg-danger"><?= count($pending) ?> waiting</span><?php endif; ?>
      </div>
      <?php if (!$pending): ?>
      <div class="hs-card-body" style="text-align:center;padding:40px;color:var(--hs-muted);">
        <i class="fas fa-check-double" style="font-size:36px;opacity:.3;"></i>
        <p style="margin-top:12px;">No pending applications — all caught up!</p>
      </div>
      <?php else: ?>
      <div class="hs-card-body p-0">
        <?php foreach ($pending as $p):
          $roleColor = $p['role']==='doctor' ? '#16A34A' : '#7C3AED';
          $roleBg    = $p['role']==='doctor' ? '#DCFCE7' : '#EDE9FE';
          $roleLabel = $p['role']==='doctor' ? 'Doctor' : 'Gov. Analyst';
          $roleIcon  = $p['role']==='doctor' ? 'fa-user-md' : 'fa-landmark';
        ?>
        <div style="padding:20px;border-bottom:1px solid var(--hs-border);">
          <div style="display:grid;grid-template-columns:1fr auto;gap:16px;align-items:flex-start;">

            <!-- Left: Info -->
            <div>
              <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                <div style="width:46px;height:46px;border-radius:50%;background:<?= $roleBg ?>;display:flex;align-items:center;justify-content:center;font-size:20px;color:<?= $roleColor ?>;flex-shrink:0;">
                  <i class="fas <?= $roleIcon ?>"></i>
                </div>
                <div>
                  <div style="display:flex;align-items:center;gap:8px;">
                    <h4 style="margin:0;font-size:15px;font-weight:800;color:var(--hs-navy);"><?= e($p['first_name'].' '.$p['last_name']) ?></h4>
                    <span style="background:<?= $roleBg ?>;color:<?= $roleColor ?>;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;"><?= $roleLabel ?></span>
                    <span style="background:#FEF3C7;color:#92400E;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;">⏳ Pending</span>
                  </div>
                  <div style="font-size:12.5px;color:var(--hs-muted);margin-top:2px;"><?= e($p['email']) ?> &middot; Applied <?= formatDate($p['created_at'], 'd M Y H:i') ?></div>
                </div>
              </div>

              <!-- Details grid -->
              <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;font-size:12.5px;">
                <?php if ($p['phone']): ?>
                <div style="background:var(--hs-off-white);border-radius:7px;padding:8px 12px;">
                  <div style="color:var(--hs-muted);font-size:10px;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Phone</div>
                  <div style="font-weight:600;color:var(--hs-navy);"><?= e($p['phone']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($p['hcpc_number']): ?>
                <div style="background:var(--hs-off-white);border-radius:7px;padding:8px 12px;">
                  <div style="color:var(--hs-muted);font-size:10px;font-weight:700;text-transform:uppercase;margin-bottom:2px;">HCPC / GMC Number</div>
                  <div style="font-weight:600;color:var(--hs-navy);font-family:monospace;"><?= e($p['hcpc_number']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($p['specialization']): ?>
                <div style="background:var(--hs-off-white);border-radius:7px;padding:8px 12px;">
                  <div style="color:var(--hs-muted);font-size:10px;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Specialization</div>
                  <div style="font-weight:600;color:var(--hs-navy);"><?= e($p['specialization']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($p['hospital_name']): ?>
                <div style="background:var(--hs-off-white);border-radius:7px;padding:8px 12px;">
                  <div style="color:var(--hs-muted);font-size:10px;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Hospital / Clinic</div>
                  <div style="font-weight:600;color:var(--hs-navy);"><?= e($p['hospital_name']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($p['experience_years']): ?>
                <div style="background:var(--hs-off-white);border-radius:7px;padding:8px 12px;">
                  <div style="color:var(--hs-muted);font-size:10px;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Experience</div>
                  <div style="font-weight:600;color:var(--hs-navy);"><?= $p['experience_years'] ?> years</div>
                </div>
                <?php endif; ?>
                <div style="background:var(--hs-off-white);border-radius:7px;padding:8px 12px;">
                  <div style="color:var(--hs-muted);font-size:10px;font-weight:700;text-transform:uppercase;margin-bottom:2px;">NHS ID Assigned</div>
                  <div style="font-weight:700;color:var(--hs-navy);font-family:monospace;"><?= e($p['nhs_id']) ?></div>
                </div>
              </div>

              <?php if ($p['bio']): ?>
              <div style="margin-top:10px;padding:10px 14px;background:var(--hs-off-white);border-radius:8px;font-size:12.5px;color:var(--hs-text);border-left:3px solid <?= $roleColor ?>;">
                <strong>Bio:</strong> <?= e($p['bio']) ?>
              </div>
              <?php endif; ?>
            </div>

            <!-- Right: Action buttons -->
            <div style="display:flex;flex-direction:column;gap:8px;min-width:120px;">
              <a href="?approve=<?= $p['id'] ?>" class="btn-hs btn-success-hs"
                onclick="return confirm('Approve <?= e($p['first_name'].' '.$p['last_name']) ?> as <?= $roleLabel ?>?')"
                style="justify-content:center;text-decoration:none;padding:10px 16px;">
                <i class="fas fa-check"></i> Approve
              </a>
              <button class="btn-hs btn-danger-hs" style="justify-content:center;"
                onclick="openReject(<?= $p['id'] ?>,'<?= addslashes($p['first_name'].' '.$p['last_name']) ?>')">
                <i class="fas fa-times"></i> Reject
              </button>
              <a href="mailto:<?= e($p['email']) ?>" class="btn-hs btn-outline-hs btn-sm-hs" style="justify-content:center;text-decoration:none;">
                <i class="fas fa-envelope"></i> Email
              </a>
            </div>

          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══ APPROVED ══ -->
    <div class="hs-card" style="margin-bottom:20px;">
      <div class="hs-card-header">
        <span class="card-title"><i class="fas fa-check-circle" style="color:#16A34A;"></i> Recently Approved</span>
        <span style="font-size:12px;color:var(--hs-muted);">Doctors &amp; Gov. Analysts</span>
      </div>
      <div class="hs-card-body p-0">
        <table class="hs-table">
          <thead><tr><th>Name</th><th>Role</th><th>Specialization / Organisation</th><th>NHS ID</th><th>Approved</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($approved as $a):
              $rc = $a['role']==='doctor' ? '#16A34A' : '#7C3AED';
              $rb = $a['role']==='doctor' ? '#DCFCE7' : '#EDE9FE';
            ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div style="width:34px;height:34px;border-radius:50%;background:<?= $rb ?>;display:flex;align-items:center;justify-content:center;color:<?= $rc ?>;font-weight:700;font-size:12px;">
                    <?= strtoupper(substr($a['first_name'],0,1).substr($a['last_name'],0,1)) ?>
                  </div>
                  <div>
                    <div style="font-weight:700;"><?= e($a['first_name'].' '.$a['last_name']) ?></div>
                    <div style="font-size:11px;color:var(--hs-muted);"><?= e($a['email']) ?></div>
                  </div>
                </div>
              </td>
              <td><span style="background:<?= $rb ?>;color:<?= $rc ?>;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:capitalize;"><?= $a['role'] ?></span></td>
              <td style="font-size:13px;"><?= e($a['specialization'] ?? $a['hospital_name'] ?? '—') ?></td>
              <td style="font-family:monospace;font-size:12px;font-weight:700;"><?= e($a['nhs_id']) ?></td>
              <td style="font-size:12px;"><?= formatDate($a['created_at'], 'd M Y') ?></td>
              <td><span class="status-pill approved">Approved</span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$approved): ?>
            <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--hs-muted);">No approved professionals yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══ REJECTED ══ -->
    <?php if ($rejected): ?>
    <div class="hs-card">
      <div class="hs-card-header"><span class="card-title"><i class="fas fa-times-circle" style="color:#DC2626;"></i> Rejected Applications</span></div>
      <div class="hs-card-body p-0">
        <table class="hs-table">
          <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Reason</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($rejected as $r): ?>
            <tr>
              <td><strong><?= e($r['first_name'].' '.$r['last_name']) ?></strong></td>
              <td style="font-size:12px;"><?= e($r['email']) ?></td>
              <td style="text-transform:capitalize;"><?= e($r['role']) ?></td>
              <td style="font-size:12px;color:var(--hs-muted);"><?= e($r['rejection_reason'] ?? '—') ?></td>
              <td style="font-size:12px;"><?= formatDate($r['created_at'], 'd M Y') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3000;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:16px;width:100%;max-width:440px;box-shadow:var(--shadow-lg);overflow:hidden;">
    <div style="background:#DC2626;color:#fff;padding:18px 24px;display:flex;justify-content:space-between;align-items:center;">
      <h5 style="margin:0;font-size:16px;font-weight:700;"><i class="fas fa-times-circle"></i> Reject Application</h5>
      <button onclick="document.getElementById('rejectModal').style.display='none'" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <form method="POST" style="padding:22px;">
      <input type="hidden" name="reject_id" id="rejectId">
      <p style="font-size:13px;color:var(--hs-muted);margin-bottom:14px;">Rejecting application for <strong id="rejectName"></strong>. Please provide a reason.</p>
      <label class="form-label">Reason for Rejection</label>
      <textarea name="reason" class="form-control" rows="4" placeholder="e.g. HCPC number could not be verified. Please resubmit with valid credentials." required style="margin-bottom:16px;"></textarea>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn-hs btn-danger-hs" style="flex:1;justify-content:center;"><i class="fas fa-times"></i> Confirm Rejection</button>
        <button type="button" onclick="document.getElementById('rejectModal').style.display='none'" class="btn-hs btn-outline-hs">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function openReject(id, name) {
  document.getElementById('rejectId').value = id;
  document.getElementById('rejectName').textContent = name;
  document.getElementById('rejectModal').style.display = 'flex';
}
document.getElementById('rejectModal').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});
</script>
</body>
</html>
