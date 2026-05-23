<?php
require_once __DIR__ . '/../config/config.php';
requireRole('pharmacy');
$user = getCurrentUser();
$uid  = $user['id'];
$notifCount = getUnreadCount($pdo, $uid);

// Stats
try {
    $stats = [
        'awaiting'   => (int)$pdo->query("SELECT COUNT(*) FROM prescription_orders WHERE status='approved'")->fetchColumn(),
        'preparing'  => (int)$pdo->query("SELECT COUNT(*) FROM prescription_orders WHERE status='preparing'")->fetchColumn(),
        'dispatched' => (int)$pdo->query("SELECT COUNT(*) FROM prescription_orders WHERE status='dispatched'")->fetchColumn(),
        'delivered'  => (int)$pdo->query("SELECT COUNT(*) FROM prescription_orders WHERE status='delivered'")->fetchColumn(),
        'today'      => (int)$pdo->query("SELECT COUNT(*) FROM prescription_orders WHERE DATE(ordered_at)=CURDATE()")->fetchColumn(),
        'payments'   => (int)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_type='prescription'")->fetchColumn(),
    ];
} catch(\Exception $e) {
    $stats = ['awaiting'=>0,'preparing'=>0,'dispatched'=>0,'delivered'=>0,'today'=>0,'payments'=>0];
}

// Recent orders needing attention
try {
    $urgent = $pdo->query("
        SELECT po.*, p.medication_name, p.dosage, p.frequency,
               u.first_name, u.last_name, u.nhs_id
        FROM prescription_orders po
        JOIN prescriptions p ON po.prescription_id=p.id
        JOIN users u ON po.patient_id=u.id
        WHERE po.status IN ('approved','preparing','dispatched')
        ORDER BY po.ordered_at ASC
        LIMIT 8
    ")->fetchAll();
} catch(\Exception $e) { $urgent = []; }

$statusConfig = [
    'approved'   => ['color'=>'#1565C0','bg'=>'#DBEAFE','icon'=>'fa-check-circle',  'label'=>'Awaiting Preparation'],
    'preparing'  => ['color'=>'#0891B2','bg'=>'#E0F2FE','icon'=>'fa-mortar-pestle', 'label'=>'Being Prepared'],
    'dispatched' => ['color'=>'#7C3AED','bg'=>'#EDE9FE','icon'=>'fa-truck',         'label'=>'Dispatched'],
    'delivered'  => ['color'=>'#16A34A','bg'=>'#DCFCE7','icon'=>'fa-check-double',  'label'=>'Delivered'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Medical Team Dashboard — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-hospital-user" style="color:var(--hs-blue);"></i> Medical Team Dashboard</div>
      <div class="page-subtitle">Medicine dispensing &amp; order management</div>
    </div>
    <div class="topbar-actions">
      <a href="medicine-queue.php" class="btn-hs btn-primary-hs btn-sm-hs">
        <i class="fas fa-pills"></i> Open Queue
      </a>
    </div>
  </div>

  <div class="hs-content">

    <!-- Stats row -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:24px;">

      <div class="hs-card" style="border-left:4px solid #F59E0B;">
        <div class="hs-card-body" style="padding:16px 18px;">
          <div style="font-size:11px;font-weight:700;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;">Awaiting Prep</div>
          <div style="font-size:32px;font-weight:800;color:#F59E0B;"><?= $stats['awaiting'] ?></div>
          <div style="font-size:12px;color:var(--hs-muted);margin-top:2px;">Approved orders</div>
        </div>
      </div>

      <div class="hs-card" style="border-left:4px solid #0891B2;">
        <div class="hs-card-body" style="padding:16px 18px;">
          <div style="font-size:11px;font-weight:700;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;">Preparing</div>
          <div style="font-size:32px;font-weight:800;color:#0891B2;"><?= $stats['preparing'] ?></div>
          <div style="font-size:12px;color:var(--hs-muted);margin-top:2px;">Being dispensed</div>
        </div>
      </div>

      <div class="hs-card" style="border-left:4px solid #7C3AED;">
        <div class="hs-card-body" style="padding:16px 18px;">
          <div style="font-size:11px;font-weight:700;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;">Dispatched</div>
          <div style="font-size:32px;font-weight:800;color:#7C3AED;"><?= $stats['dispatched'] ?></div>
          <div style="font-size:12px;color:var(--hs-muted);margin-top:2px;">In transit</div>
        </div>
      </div>

      <div class="hs-card" style="border-left:4px solid #16A34A;">
        <div class="hs-card-body" style="padding:16px 18px;">
          <div style="font-size:11px;font-weight:700;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;">Delivered Today</div>
          <div style="font-size:32px;font-weight:800;color:#16A34A;"><?= $stats['delivered'] ?></div>
          <div style="font-size:12px;color:var(--hs-muted);margin-top:2px;">All time total</div>
        </div>
      </div>

      <div class="hs-card" style="border-left:4px solid var(--hs-blue);">
        <div class="hs-card-body" style="padding:16px 18px;">
          <div style="font-size:11px;font-weight:700;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;">Orders Today</div>
          <div style="font-size:32px;font-weight:800;color:var(--hs-blue);"><?= $stats['today'] ?></div>
          <div style="font-size:12px;color:var(--hs-muted);margin-top:2px;">New requests</div>
        </div>
      </div>

      <div class="hs-card" style="border-left:4px solid #059669;">
        <div class="hs-card-body" style="padding:16px 18px;">
          <div style="font-size:11px;font-weight:700;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;">Revenue Collected</div>
          <div style="font-size:24px;font-weight:800;color:#059669;">£<?= number_format($stats['payments'] / 100, 2) ?></div>
          <div style="font-size:12px;color:var(--hs-muted);margin-top:2px;">Total prescription fees</div>
        </div>
      </div>

    </div>

    <!-- Active queue -->
    <div class="hs-card">
      <div class="hs-card-header">
        <span class="card-title"><i class="fas fa-clock" style="color:#F59E0B;"></i> Active Orders Queue</span>
        <a href="medicine-queue.php" class="btn-hs btn-outline-hs btn-sm-hs">View All</a>
      </div>
      <div class="hs-card-body p-0">
        <?php if (!$urgent): ?>
        <div style="padding:40px;text-align:center;color:var(--hs-muted);">
          <i class="fas fa-check-circle" style="font-size:40px;color:#16A34A;opacity:.4;display:block;margin-bottom:12px;"></i>
          <p>No active orders — queue is clear!</p>
        </div>
        <?php else: ?>
        <table class="hs-table">
          <thead>
            <tr><th>Patient</th><th>Medication</th><th>Delivery</th><th>Status</th><th>Ordered</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php foreach ($urgent as $o):
              $sc = $statusConfig[$o['status']] ?? $statusConfig['approved'];
              $nextMap = ['approved'=>['preparing','Mark Preparing','#0891B2'],'preparing'=>['dispatch','Mark Dispatched','#7C3AED'],'dispatched'=>['deliver','Mark Delivered','#16A34A']];
              $next = $nextMap[$o['status']] ?? null;
            ?>
            <tr>
              <td>
                <div style="font-weight:600;"><?= e($o['first_name'].' '.$o['last_name']) ?></div>
                <div style="font-size:11px;color:var(--hs-muted);">NHS: <?= e($o['nhs_id']) ?></div>
              </td>
              <td>
                <div style="font-weight:600;">💊 <?= e($o['medication_name']) ?></div>
                <div style="font-size:11px;color:var(--hs-muted);"><?= e($o['dosage']) ?> · <?= e($o['frequency']) ?></div>
              </td>
              <td>
                <?php if ($o['delivery_method'] === 'delivery'): ?>
                <span style="color:#7C3AED;font-size:12px;font-weight:600;"><i class="fas fa-truck"></i> Home Delivery</span>
                <?php else: ?>
                <span style="color:#1565C0;font-size:12px;font-weight:600;"><i class="fas fa-store"></i> Collection</span>
                <?php endif; ?>
              </td>
              <td>
                <span style="display:inline-flex;align-items:center;gap:5px;background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;">
                  <i class="fas <?= $sc['icon'] ?>"></i> <?= $sc['label'] ?>
                </span>
              </td>
              <td style="font-size:12px;color:var(--hs-muted);"><?= timeAgo($o['ordered_at']) ?></td>
              <td>
                <?php if ($next): ?>
                <button onclick="quickUpdate(<?= $o['id'] ?>, '<?= $next[0] ?>')"
                  style="background:<?= $next[2] ?>;color:#fff;border:none;border-radius:7px;padding:6px 14px;font-size:12px;font-weight:700;cursor:pointer;">
                  <?= $next[1] ?>
                </button>
                <?php else: ?>
                <span style="color:var(--hs-muted);font-size:12px;">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function quickUpdate(orderId, action) {
  if (!confirm('Update order status to "' + action + '"?')) return;
  fetch('../api/prescription-order.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ action: action, order_id: orderId, doctor_notes: '' })
  }).then(r=>r.json()).then(d => {
    showToast(d.success ? 'Order updated!' : d.error, d.success ? 'success' : 'error');
    if (d.success) setTimeout(()=>location.reload(), 1000);
  }).catch(()=>showToast('Network error','error'));
}
</script>
</body>
</html>
