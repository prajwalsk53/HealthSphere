<?php
require_once __DIR__ . '/../config/config.php';
requireRole('doctor');
$user = getCurrentUser(); $uid = $user['id'];
$notifCount = getUnreadCount($pdo, $uid); $msgCount = getUnreadMessages($pdo, $uid);

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS prescription_orders (
    id INT PRIMARY KEY AUTO_INCREMENT, prescription_id INT NOT NULL, patient_id INT NOT NULL, doctor_id INT NOT NULL,
    status ENUM('pending','approved','preparing','dispatched','delivered','rejected','cancelled') DEFAULT 'pending',
    delivery_method ENUM('collection','delivery') DEFAULT 'collection', delivery_address TEXT,
    pharmacy_name VARCHAR(150), patient_notes TEXT, doctor_notes TEXT, estimated_ready DATETIME NULL,
    ordered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
)");

$activeTab = $_GET['tab'] ?? 'pending';

// Fetch orders by status group
$statusGroup = $activeTab === 'pending' ? "('pending','approved','preparing','dispatched')" : "('delivered','rejected','cancelled')";
$orders = $pdo->prepare("
    SELECT po.*, p.medication_name, p.dosage, p.frequency, p.instructions,
           u.first_name, u.last_name, u.nhs_id, u.date_of_birth
    FROM prescription_orders po
    JOIN prescriptions p ON po.prescription_id=p.id
    JOIN users u ON po.patient_id=u.id
    WHERE po.doctor_id=? AND po.status IN {$statusGroup}
    ORDER BY po.ordered_at DESC
");
$orders->execute([$uid]); $orders = $orders->fetchAll();

$pendingCount = $pdo->prepare("SELECT COUNT(*) FROM prescription_orders WHERE doctor_id=? AND status IN ('pending','approved','preparing','dispatched')");
$pendingCount->execute([$uid]); $pendingCount = (int)$pendingCount->fetchColumn();

$statusConfig = [
    'pending'    => ['color'=>'#F59E0B','bg'=>'#FEF3C7','label'=>'Pending Approval','next'=>'approve',  'nextLabel'=>'Approve','nextColor'=>'#1565C0'],
    'approved'   => ['color'=>'#1565C0','bg'=>'#DBEAFE','label'=>'Approved',         'next'=>'preparing','nextLabel'=>'Mark Preparing','nextColor'=>'#0891B2'],
    'preparing'  => ['color'=>'#0891B2','bg'=>'#E0F2FE','label'=>'Being Prepared',   'next'=>'dispatch', 'nextLabel'=>'Mark Dispatched','nextColor'=>'#7C3AED'],
    'dispatched' => ['color'=>'#7C3AED','bg'=>'#EDE9FE','label'=>'Dispatched',       'next'=>'deliver',  'nextLabel'=>'Mark Delivered','nextColor'=>'#16A34A'],
    'delivered'  => ['color'=>'#16A34A','bg'=>'#DCFCE7','label'=>'Delivered','next'=>null,'nextLabel'=>null,'nextColor'=>null],
    'rejected'   => ['color'=>'#DC2626','bg'=>'#FEE2E2','label'=>'Rejected','next'=>null,'nextLabel'=>null,'nextColor'=>null],
    'cancelled'  => ['color'=>'#6B7280','bg'=>'#F3F4F6','label'=>'Cancelled','next'=>null,'nextLabel'=>null,'nextColor'=>null],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Prescription Orders — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-clipboard-list" style="color:var(--hs-blue);"></i> Prescription Orders</div>
      <div class="page-subtitle">Review and process patient prescription requests</div>
    </div>
  </div>
  <div class="hs-content">

    <!-- Tabs -->
    <div style="display:flex;gap:4px;background:#fff;border-radius:10px;padding:5px;border:1px solid var(--hs-border);margin-bottom:20px;width:fit-content;">
      <a href="?tab=pending" style="padding:8px 18px;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:6px;<?= $activeTab==='pending'?'background:var(--hs-blue);color:#fff;':'color:var(--hs-muted);' ?>">
        <i class="fas fa-clock"></i> Active Orders
        <?php if ($pendingCount): ?><span style="background:#DC2626;color:#fff;border-radius:20px;padding:1px 7px;font-size:10px;"><?= $pendingCount ?></span><?php endif; ?>
      </a>
      <a href="?tab=history" style="padding:8px 18px;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:6px;<?= $activeTab==='history'?'background:var(--hs-blue);color:#fff;':'color:var(--hs-muted);' ?>">
        <i class="fas fa-history"></i> Completed
      </a>
    </div>

    <?php if (!$orders): ?>
    <div style="text-align:center;padding:60px;color:var(--hs-muted);">
      <i class="fas fa-clipboard-check" style="font-size:48px;opacity:.2;display:block;margin-bottom:16px;"></i>
      <p><?= $activeTab==='pending' ? 'No active prescription orders from patients.' : 'No completed orders yet.' ?></p>
    </div>
    <?php endif; ?>

    <?php foreach ($orders as $o):
      $sc = $statusConfig[$o['status']] ?? $statusConfig['pending'];
    ?>
    <div class="hs-card" style="margin-bottom:14px;border-left:4px solid <?= $sc['color'] ?>;">
      <div class="hs-card-body">
        <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;">

          <!-- Patient info -->
          <div style="width:44px;height:44px;border-radius:50%;background:var(--hs-blue-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:16px;flex-shrink:0;">
            <?= strtoupper(substr($o['first_name'],0,1).substr($o['last_name'],0,1)) ?>
          </div>
          <div style="flex:1;min-width:200px;">
            <div style="font-weight:800;font-size:15px;color:var(--hs-navy);"><?= e($o['first_name'].' '.$o['last_name']) ?></div>
            <div style="font-size:12px;color:var(--hs-muted);">NHS: <?= e($o['nhs_id']) ?> · <?= $o['date_of_birth'] ? formatDate($o['date_of_birth']) : '—' ?></div>
            <div style="margin-top:8px;background:var(--hs-off-white);border-radius:8px;padding:10px 14px;display:inline-block;">
              <span style="font-weight:700;font-size:14px;color:var(--hs-navy);">💊 <?= e($o['medication_name']) ?></span>
              <span style="font-size:13px;color:var(--hs-blue);margin-left:8px;font-weight:600;"><?= e($o['dosage']) ?></span>
              <div style="font-size:11px;color:var(--hs-muted);margin-top:3px;"><?= e($o['frequency']) ?><?= $o['instructions'] ? ' · '.$o['instructions'] : '' ?></div>
            </div>
            <?php if ($o['delivery_method'] === 'delivery'): ?>
            <div style="margin-top:6px;font-size:12px;color:#7C3AED;font-weight:600;"><i class="fas fa-truck"></i> Home delivery<?= $o['delivery_address'] ? ' — '.e($o['delivery_address']) : '' ?></div>
            <?php else: ?>
            <div style="margin-top:6px;font-size:12px;color:#1565C0;font-weight:600;"><i class="fas fa-store"></i> Collection from pharmacy</div>
            <?php endif; ?>
            <?php if ($o['patient_notes']): ?>
            <div style="margin-top:8px;background:#FEF3C7;border-radius:6px;padding:8px 12px;font-size:12px;color:#92400E;"><i class="fas fa-comment"></i> Patient note: <?= e($o['patient_notes']) ?></div>
            <?php endif; ?>
            <?php if ($o['doctor_notes']): ?>
            <div style="margin-top:6px;font-size:12px;color:var(--hs-muted);"><i class="fas fa-comment-medical"></i> Your note: <?= e($o['doctor_notes']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Status & Actions -->
          <div style="text-align:right;flex-shrink:0;">
            <div style="display:inline-flex;align-items:center;gap:6px;background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;margin-bottom:10px;">
              <?= $sc['label'] ?>
            </div>
            <div style="font-size:11px;color:var(--hs-muted);margin-bottom:10px;"><?= timeAgo($o['ordered_at']) ?></div>

            <?php if ($sc['next']): ?>
            <button onclick="openActionModal(<?= $o['id'] ?>, '<?= $sc['next'] ?>', '<?= e(addslashes($o['first_name'].' '.$o['last_name'])) ?>', '<?= e(addslashes($o['medication_name'])) ?>')"
              style="background:<?= $sc['nextColor'] ?>;color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:12px;font-weight:700;cursor:pointer;margin-bottom:6px;display:block;width:100%;">
              <?= $sc['nextLabel'] ?>
            </button>
            <?php endif; ?>
            <?php if ($o['status'] === 'pending'): ?>
            <button onclick="openActionModal(<?= $o['id'] ?>, 'reject', '<?= e(addslashes($o['first_name'].' '.$o['last_name'])) ?>', '<?= e(addslashes($o['medication_name'])) ?>')"
              style="background:#FEE2E2;color:#DC2626;border:1px solid #FECACA;border-radius:8px;padding:6px 14px;font-size:12px;font-weight:700;cursor:pointer;width:100%;">
              Reject
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

  </div>
</div>

<!-- Action Modal -->
<div id="actionModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:3000;align-items:center;justify-content:center;padding:20px;" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#fff;border-radius:16px;width:100%;max-width:420px;box-shadow:0 24px 80px rgba(10,31,68,.25);overflow:hidden;">
    <div style="background:var(--hs-navy);color:#fff;padding:18px 24px;">
      <h4 style="margin:0;font-size:16px;" id="actionModalTitle">Update Order</h4>
      <div id="actionModalSub" style="font-size:12px;opacity:.7;margin-top:3px;"></div>
    </div>
    <div style="padding:20px;">
      <input type="hidden" id="actionOrderId">
      <input type="hidden" id="actionType">

      <div id="pharmacyField" style="margin-bottom:14px;">
        <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Pharmacy Name (optional)</label>
        <input type="text" id="pharmacyName" class="form-control" placeholder="e.g. Boots Pharmacy, Leicester">
      </div>

      <div style="margin-bottom:16px;">
        <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Note to patient (optional)</label>
        <textarea id="doctorNote" class="form-control" rows="2" placeholder="e.g. Please collect from Boots on High St..."></textarea>
      </div>

      <div style="display:flex;gap:10px;">
        <button id="actionSubmitBtn" onclick="submitAction()"
          style="flex:1;background:var(--hs-blue);color:#fff;border:none;border-radius:9px;padding:11px;font-size:13px;font-weight:700;cursor:pointer;">
          Confirm
        </button>
        <button onclick="document.getElementById('actionModal').style.display='none'"
          style="padding:11px 18px;border:1.5px solid var(--hs-border);border-radius:9px;background:#fff;cursor:pointer;font-weight:600;color:var(--hs-muted);">
          Cancel
        </button>
      </div>
    </div>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function openActionModal(orderId, action, patient, medication) {
  document.getElementById('actionOrderId').value = orderId;
  document.getElementById('actionType').value = action;
  document.getElementById('actionModalTitle').textContent = {
    approve:'Approve Order', preparing:'Mark as Preparing', dispatch:'Mark as Dispatched',
    deliver:'Mark as Delivered', reject:'Reject Order'
  }[action] || 'Update Order';
  document.getElementById('actionModalSub').textContent = patient + ' — ' + medication;
  document.getElementById('pharmacyField').style.display = ['preparing','dispatch','deliver'].includes(action) ? 'block' : 'none';
  document.getElementById('doctorNote').placeholder = action==='reject' ? 'Reason for rejection...' : 'Note to patient (optional)...';
  document.getElementById('actionModal').style.display = 'flex';
}
function submitAction() {
  const btn = document.getElementById('actionSubmitBtn');
  btn.textContent = 'Updating...'; btn.disabled = true;
  fetch('../api/prescription-order.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({
      action: document.getElementById('actionType').value,
      order_id: parseInt(document.getElementById('actionOrderId').value),
      doctor_notes: document.getElementById('doctorNote').value,
      pharmacy_name: document.getElementById('pharmacyName').value,
    })
  }).then(r=>r.json()).then(d => {
    if (d.success) { showToast(d.message,'success'); setTimeout(()=>location.reload(),1200); }
    else { showToast(d.error,'error'); btn.textContent='Confirm'; btn.disabled=false; }
  });
}
</script>
</body>
</html>
