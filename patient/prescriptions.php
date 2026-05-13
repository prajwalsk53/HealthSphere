<?php
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
$user = getCurrentUser();
$uid  = $user['id'];
$notifCount = getUnreadCount($pdo, $uid);
$msgCount   = getUnreadMessages($pdo, $uid);

// Auto-create table
$pdo->exec("CREATE TABLE IF NOT EXISTS prescription_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prescription_id INT NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    status ENUM('pending','approved','preparing','dispatched','delivered','rejected','cancelled') DEFAULT 'pending',
    delivery_method ENUM('collection','delivery') DEFAULT 'collection',
    delivery_address TEXT,
    pharmacy_name VARCHAR(150),
    patient_notes TEXT,
    doctor_notes TEXT,
    estimated_ready DATETIME NULL,
    ordered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Fetch active prescriptions with order status
$prescriptions = $pdo->prepare("
    SELECT p.*,
           u.first_name as doc_first, u.last_name as doc_last, d.specialization, d.hospital_name,
           (SELECT po.id FROM prescription_orders po WHERE po.prescription_id=p.id AND po.patient_id=? AND po.status NOT IN ('delivered','rejected','cancelled') ORDER BY po.ordered_at DESC LIMIT 1) as active_order_id,
           (SELECT po.status FROM prescription_orders po WHERE po.prescription_id=p.id AND po.patient_id=? AND po.status NOT IN ('delivered','rejected','cancelled') ORDER BY po.ordered_at DESC LIMIT 1) as order_status,
           (SELECT po.delivery_method FROM prescription_orders po WHERE po.prescription_id=p.id AND po.patient_id=? ORDER BY po.ordered_at DESC LIMIT 1) as last_method,
           (SELECT po.doctor_notes FROM prescription_orders po WHERE po.prescription_id=p.id AND po.patient_id=? ORDER BY po.ordered_at DESC LIMIT 1) as last_doctor_notes,
           (SELECT po.pharmacy_name FROM prescription_orders po WHERE po.prescription_id=p.id AND po.patient_id=? ORDER BY po.ordered_at DESC LIMIT 1) as pharmacy_name
    FROM prescriptions p
    LEFT JOIN users u ON p.doctor_id=u.id
    LEFT JOIN doctors d ON u.id=d.user_id
    WHERE p.patient_id=?
    ORDER BY p.is_active DESC, p.created_at DESC
");
$prescriptions->execute([$uid,$uid,$uid,$uid,$uid,$uid]);
$prescriptions = $prescriptions->fetchAll();

// Fetch order history
$orderHistory = $pdo->prepare("
    SELECT po.*, p.medication_name, p.dosage, p.frequency,
           u.first_name as doc_first, u.last_name as doc_last
    FROM prescription_orders po
    JOIN prescriptions p ON po.prescription_id=p.id
    JOIN users u ON po.doctor_id=u.id
    WHERE po.patient_id=?
    ORDER BY po.ordered_at DESC
    LIMIT 20
");
$orderHistory->execute([$uid]);
$orderHistory = $orderHistory->fetchAll();

$statusConfig = [
    'pending'    => ['color'=>'#F59E0B','bg'=>'#FEF3C7','icon'=>'fa-clock',          'label'=>'Pending Approval'],
    'approved'   => ['color'=>'#1565C0','bg'=>'#DBEAFE','icon'=>'fa-check-circle',   'label'=>'Approved'],
    'preparing'  => ['color'=>'#0891B2','bg'=>'#E0F2FE','icon'=>'fa-mortar-pestle',  'label'=>'Being Prepared'],
    'dispatched' => ['color'=>'#7C3AED','bg'=>'#EDE9FE','icon'=>'fa-truck',          'label'=>'Dispatched'],
    'delivered'  => ['color'=>'#16A34A','bg'=>'#DCFCE7','icon'=>'fa-check-double',   'label'=>'Delivered / Ready'],
    'rejected'   => ['color'=>'#DC2626','bg'=>'#FEE2E2','icon'=>'fa-times-circle',   'label'=>'Not Approved'],
    'cancelled'  => ['color'=>'#6B7280','bg'=>'#F3F4F6','icon'=>'fa-ban',            'label'=>'Cancelled'],
];
$activeTab = $_GET['tab'] ?? 'prescriptions';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Prescriptions — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.rx-card { background:#fff; border:1px solid var(--hs-border); border-radius:14px; padding:18px 20px; margin-bottom:14px; transition:.2s; }
.rx-card:hover { box-shadow:0 4px 16px rgba(10,31,68,.08); }
.rx-card.inactive { opacity:.65; }
.status-pill { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700; }
.timeline { display:flex; align-items:center; gap:0; margin:14px 0 0; }
.tl-step { display:flex; flex-direction:column; align-items:center; flex:1; }
.tl-dot { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; border:2px solid; flex-shrink:0; }
.tl-line { height:2px; flex:1; }
.tl-label { font-size:9px; font-weight:700; margin-top:4px; text-align:center; }
.order-modal-bg { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:3000; align-items:center; justify-content:center; padding:20px; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">

  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-pills" style="color:var(--hs-blue);"></i> My Prescriptions</div>
      <div class="page-subtitle">Request, track and manage your medication orders</div>
    </div>
  </div>

  <div class="hs-content">

    <!-- Tabs -->
    <div style="display:flex;gap:4px;background:#fff;border-radius:10px;padding:5px;border:1px solid var(--hs-border);margin-bottom:20px;width:fit-content;">
      <a href="?tab=prescriptions" style="padding:8px 18px;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:6px;<?= $activeTab==='prescriptions'?'background:var(--hs-blue);color:#fff;':'color:var(--hs-muted);' ?>">
        <i class="fas fa-pills"></i> My Prescriptions
      </a>
      <a href="?tab=orders" style="padding:8px 18px;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:6px;<?= $activeTab==='orders'?'background:var(--hs-blue);color:#fff;':'color:var(--hs-muted);' ?>">
        <i class="fas fa-box"></i> Order History
        <?php $pending = count(array_filter($orderHistory, fn($o)=>in_array($o['status'],['pending','approved','preparing','dispatched']))); ?>
        <?php if ($pending): ?><span style="background:#DC2626;color:#fff;border-radius:20px;padding:1px 7px;font-size:10px;"><?= $pending ?></span><?php endif; ?>
      </a>
    </div>

    <?php if ($activeTab === 'prescriptions'): ?>

    <!-- How it works banner -->
    <div style="background:linear-gradient(135deg,#EFF6FF,#F0FDF4);border:1px solid #BFDBFE;border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;gap:24px;flex-wrap:wrap;">
      <?php foreach ([['1','fa-pills','Find your medication','Your active prescriptions are listed below'],['2','fa-mouse-pointer','Click Order','Choose collection or home delivery'],['3','fa-user-md','Doctor approves','Your GP reviews and approves within 24h'],['4','fa-truck','Track delivery','Get real-time status updates']] as [$n,$ic,$t,$d]): ?>
      <div style="display:flex;align-items:flex-start;gap:10px;flex:1;min-width:160px;">
        <div style="width:28px;height:28px;background:var(--hs-blue);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0;"><?= $n ?></div>
        <div><div style="font-size:12px;font-weight:700;color:var(--hs-navy);"><?= $t ?></div><div style="font-size:11px;color:var(--hs-muted);"><?= $d ?></div></div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if (!$prescriptions): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--hs-muted);">
      <i class="fas fa-pills" style="font-size:48px;opacity:.2;margin-bottom:16px;display:block;"></i>
      <p>No prescriptions found. Your doctor will add prescriptions after your appointment.</p>
      <a href="appointments.php" class="btn-hs btn-primary-hs" style="margin-top:16px;display:inline-flex;">Book an Appointment</a>
    </div>
    <?php endif; ?>

    <?php foreach ($prescriptions as $rx): ?>
    <?php
      $hasActiveOrder = !empty($rx['active_order_id']);
      $sc = $rx['order_status'] ? ($statusConfig[$rx['order_status']] ?? $statusConfig['pending']) : null;
    ?>
    <div class="rx-card <?= !$rx['is_active'] ? 'inactive' : '' ?>">
      <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;">
        <div style="width:46px;height:46px;border-radius:12px;background:<?= $rx['is_active']?'linear-gradient(135deg,#1565C0,#7C3AED)':'#e5e7eb' ?>;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">💊</div>
        <div style="flex:1;min-width:200px;">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
            <span style="font-weight:800;font-size:16px;color:var(--hs-navy);"><?= e($rx['medication_name']) ?></span>
            <span style="font-weight:700;font-size:13px;color:var(--hs-blue);"><?= e($rx['dosage']) ?></span>
            <?php if (!$rx['is_active']): ?>
            <span style="background:#F3F4F6;color:#6B7280;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;">ENDED</span>
            <?php endif; ?>
          </div>
          <div style="font-size:12px;color:var(--hs-muted);margin-bottom:6px;">
            <i class="fas fa-clock"></i> <?= e($rx['frequency']) ?>
            <?php if ($rx['duration']): ?> &nbsp;·&nbsp; <i class="fas fa-calendar"></i> <?= e($rx['duration']) ?><?php endif; ?>
            <?php if ($rx['instructions']): ?> &nbsp;·&nbsp; <i class="fas fa-info-circle"></i> <?= e($rx['instructions']) ?><?php endif; ?>
          </div>
          <div style="font-size:12px;color:var(--hs-muted);">
            <i class="fas fa-user-md"></i> Dr. <?= e($rx['doc_first'].' '.$rx['doc_last']) ?>
            <?php if ($rx['hospital_name']): ?> &nbsp;·&nbsp; <?= e($rx['hospital_name']) ?><?php endif; ?>
          </div>
          <?php if ($rx['start_date'] || $rx['end_date']): ?>
          <div style="font-size:11px;color:var(--hs-muted);margin-top:4px;">
            <?= $rx['start_date'] ? formatDate($rx['start_date']) : '' ?> → <?= $rx['end_date'] ? formatDate($rx['end_date']) : 'Ongoing' ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Action -->
        <div style="text-align:right;flex-shrink:0;">
          <?php if ($hasActiveOrder && $sc): ?>
          <div class="status-pill" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;margin-bottom:8px;">
            <i class="fas <?= $sc['icon'] ?>"></i> <?= $sc['label'] ?>
          </div>
          <?php if ($rx['order_status'] === 'pending'): ?>
          <button onclick="cancelOrder(<?= $rx['active_order_id'] ?>)" style="font-size:11px;color:#DC2626;background:none;border:1px solid #FECACA;border-radius:5px;padding:3px 10px;cursor:pointer;">Cancel Order</button>
          <?php endif; ?>
          <?php elseif ($rx['is_active']): ?>
          <button onclick="openOrderModal(<?= $rx['id'] ?>, '<?= e(addslashes($rx['medication_name'])) ?>', '<?= e(addslashes($rx['dosage'])) ?>')"
            style="background:linear-gradient(135deg,#1565C0,#7C3AED);color:#fff;border:none;border-radius:9px;padding:9px 18px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-shopping-cart"></i> Order / Renew
          </button>
          <?php else: ?>
          <span style="font-size:12px;color:var(--hs-muted);">Prescription ended</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Status timeline (if active order) -->
      <?php if ($hasActiveOrder && $sc): ?>
      <?php
        $steps = ['pending'=>0,'approved'=>1,'preparing'=>2,'dispatched'=>3,'delivered'=>4,'rejected'=>-1,'cancelled'=>-1];
        $curStep = $steps[$rx['order_status']] ?? 0;
        $tlSteps = [
          ['label'=>'Ordered',  'icon'=>'fa-pills'],
          ['label'=>'Approved', 'icon'=>'fa-check'],
          ['label'=>'Preparing','icon'=>'fa-mortar-pestle'],
          ['label'=>'Dispatched','icon'=>'fa-truck'],
          ['label'=>'Delivered','icon'=>'fa-check-double'],
        ];
      ?>
      <?php if ($curStep >= 0): ?>
      <div class="timeline" style="margin-top:14px;">
        <?php foreach ($tlSteps as $i => $s):
          $done    = $i < $curStep;
          $current = $i === $curStep;
          $dotColor= $done||$current ? '#1565C0' : '#E2E8F0';
          $lineColor= $done ? '#1565C0' : '#E2E8F0';
        ?>
        <?php if ($i > 0): ?>
        <div class="tl-line" style="background:<?= $lineColor ?>;"></div>
        <?php endif; ?>
        <div class="tl-step">
          <div class="tl-dot" style="background:<?= $done||$current?'#1565C0':'#fff' ?>;border-color:<?= $dotColor ?>;color:<?= $done||$current?'#fff':'#9CA3AF' ?>;">
            <i class="fas <?= $s['icon'] ?>" style="font-size:10px;"></i>
          </div>
          <div class="tl-label" style="color:<?= $current?'#1565C0':($done?'#374151':'#9CA3AF') ?>;"><?= $s['label'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($rx['pharmacy_name']): ?>
      <div style="margin-top:10px;font-size:12px;color:var(--hs-muted);"><i class="fas fa-store"></i> <?= e($rx['pharmacy_name']) ?></div>
      <?php endif; ?>
      <?php if ($rx['last_doctor_notes']): ?>
      <div style="margin-top:6px;background:#F4F8FF;border-radius:6px;padding:8px 12px;font-size:12px;color:var(--hs-navy);"><i class="fas fa-comment-medical" style="color:var(--hs-blue);"></i> Dr. note: <?= e($rx['last_doctor_notes']) ?></div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php else: /* Order history tab */ ?>
    <div class="hs-card">
      <div class="hs-card-header"><span class="card-title"><i class="fas fa-history"></i> Order History</span></div>
      <div class="hs-card-body p-0">
        <?php if (!$orderHistory): ?>
        <div style="padding:40px;text-align:center;color:var(--hs-muted);">No orders yet. Go to My Prescriptions to place an order.</div>
        <?php else: ?>
        <table class="hs-table">
          <thead><tr><th>Medication</th><th>Doctor</th><th>Method</th><th>Status</th><th>Pharmacy</th><th>Ordered</th><th>Updated</th></tr></thead>
          <tbody>
            <?php foreach ($orderHistory as $o):
              $sc2 = $statusConfig[$o['status']] ?? $statusConfig['pending'];
            ?>
            <tr>
              <td><strong><?= e($o['medication_name']) ?></strong><br><span style="font-size:11px;color:var(--hs-muted);"><?= e($o['dosage']) ?> · <?= e($o['frequency']) ?></span></td>
              <td>Dr. <?= e($o['doc_first'].' '.$o['doc_last']) ?></td>
              <td><span style="text-transform:capitalize;"><?= e($o['delivery_method']) ?></span></td>
              <td><span class="status-pill" style="background:<?= $sc2['bg'] ?>;color:<?= $sc2['color'] ?>;"><i class="fas <?= $sc2['icon'] ?>"></i> <?= $sc2['label'] ?></span></td>
              <td><?= $o['pharmacy_name'] ? e($o['pharmacy_name']) : '—' ?></td>
              <td><?= timeAgo($o['ordered_at']) ?></td>
              <td><?= timeAgo($o['updated_at']) ?></td>
            </tr>
            <?php if ($o['doctor_notes']): ?>
            <tr>
              <td colspan="7" style="padding:6px 16px;background:#F4F8FF;font-size:12px;color:var(--hs-navy);">
                <i class="fas fa-comment-medical" style="color:var(--hs-blue);"></i> Doctor note: <?= e($o['doctor_notes']) ?>
              </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- Order Modal -->
<div id="orderModal" class="order-modal-bg" onclick="if(event.target===this)closeOrderModal()">
  <div style="background:#fff;border-radius:20px;width:100%;max-width:480px;box-shadow:0 24px 80px rgba(10,31,68,.25);overflow:hidden;">
    <div style="background:linear-gradient(135deg,#1565C0,#7C3AED);color:#fff;padding:20px 24px;">
      <h4 style="margin:0;font-size:17px;font-weight:800;"><i class="fas fa-shopping-cart"></i> Order Prescription</h4>
      <div id="orderModalSub" style="font-size:13px;opacity:.8;margin-top:4px;"></div>
    </div>
    <div style="padding:24px;">
      <input type="hidden" id="orderRxId">

      <div style="margin-bottom:16px;">
        <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:8px;">Collection or Delivery?</label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <label style="border:2px solid var(--hs-border);border-radius:10px;padding:14px;cursor:pointer;text-align:center;transition:.2s;" id="opt-collection" onclick="selectMethod('collection')">
            <input type="radio" name="method" value="collection" checked style="display:none;">
            <i class="fas fa-store" style="font-size:20px;color:#1565C0;display:block;margin-bottom:6px;"></i>
            <div style="font-weight:700;font-size:13px;">Collect from pharmacy</div>
            <div style="font-size:11px;color:var(--hs-muted);">Ready in 1–2 working days</div>
          </label>
          <label style="border:2px solid var(--hs-border);border-radius:10px;padding:14px;cursor:pointer;text-align:center;transition:.2s;" id="opt-delivery" onclick="selectMethod('delivery')">
            <input type="radio" name="method" value="delivery" style="display:none;">
            <i class="fas fa-truck" style="font-size:20px;color:#7C3AED;display:block;margin-bottom:6px;"></i>
            <div style="font-weight:700;font-size:13px;">Home delivery</div>
            <div style="font-size:11px;color:var(--hs-muted);">3–5 working days</div>
          </label>
        </div>
      </div>

      <div id="addressField" style="display:none;margin-bottom:16px;">
        <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Delivery Address</label>
        <textarea id="deliveryAddress" class="form-control" rows="2" placeholder="Enter full delivery address..."></textarea>
      </div>

      <div style="margin-bottom:20px;">
        <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Notes for your doctor (optional)</label>
        <textarea id="patientNotes" class="form-control" rows="2" placeholder="e.g. I've run out early, need urgent refill..."></textarea>
      </div>

      <div style="background:#EFF6FF;border-radius:8px;padding:12px;font-size:12px;color:#1e3a5f;margin-bottom:20px;">
        <i class="fas fa-info-circle" style="color:#1565C0;"></i>
        Your doctor will review this request within <strong>24 hours</strong>. You'll get a notification when it's approved.
      </div>

      <div style="display:flex;gap:10px;">
        <button onclick="submitOrder()" id="submitOrderBtn"
          style="flex:1;background:linear-gradient(135deg,#1565C0,#7C3AED);color:#fff;border:none;border-radius:10px;padding:12px;font-size:14px;font-weight:700;cursor:pointer;">
          <i class="fas fa-check"></i> Confirm Order
        </button>
        <button onclick="closeOrderModal()" style="padding:12px 20px;border:1.5px solid var(--hs-border);border-radius:10px;background:#fff;cursor:pointer;font-weight:600;color:var(--hs-muted);">Cancel</button>
      </div>
    </div>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
let selectedMethod = 'collection';

function openOrderModal(rxId, name, dosage) {
  document.getElementById('orderRxId').value = rxId;
  document.getElementById('orderModalSub').textContent = name + ' — ' + dosage;
  document.getElementById('orderModal').style.display = 'flex';
  selectMethod('collection');
}
function closeOrderModal() {
  document.getElementById('orderModal').style.display = 'none';
}
function selectMethod(m) {
  selectedMethod = m;
  document.getElementById('opt-collection').style.borderColor = m==='collection' ? '#1565C0' : 'var(--hs-border)';
  document.getElementById('opt-delivery').style.borderColor   = m==='delivery'   ? '#7C3AED' : 'var(--hs-border)';
  document.getElementById('addressField').style.display = m==='delivery' ? 'block' : 'none';
}
function submitOrder() {
  const btn = document.getElementById('submitOrderBtn');
  btn.textContent = 'Placing order...'; btn.disabled = true;
  fetch('../api/prescription-order.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({
      action: 'place',
      prescription_id: parseInt(document.getElementById('orderRxId').value),
      delivery_method: selectedMethod,
      delivery_address: document.getElementById('deliveryAddress').value,
      patient_notes: document.getElementById('patientNotes').value,
    })
  }).then(r=>r.json()).then(d => {
    if (d.success) { showToast(d.message, 'success'); setTimeout(()=>location.reload(), 1500); }
    else { showToast(d.error, 'error'); btn.textContent='Confirm Order'; btn.disabled=false; }
  }).catch(()=>{ showToast('Network error','error'); btn.textContent='Confirm Order'; btn.disabled=false; });
}
function cancelOrder(orderId) {
  if (!confirm('Cancel this prescription order?')) return;
  fetch('../api/prescription-order.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ action:'cancel', order_id: orderId })
  }).then(r=>r.json()).then(d => {
    showToast(d.success ? 'Order cancelled' : d.error, d.success ? 'success' : 'error');
    if (d.success) setTimeout(()=>location.reload(), 1000);
  });
}
</script>
</body>
</html>
