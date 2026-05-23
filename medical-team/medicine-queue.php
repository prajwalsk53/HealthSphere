<?php
require_once __DIR__ . '/../config/config.php';
requireRole('pharmacy');
$user = getCurrentUser();
$uid  = $user['id'];
$notifCount = getUnreadCount($pdo, $uid);

$activeTab = $_GET['tab'] ?? 'active';
$statusGroup = $activeTab === 'active'
    ? "('approved','preparing','dispatched')"
    : "('delivered','rejected','cancelled')";

try {
    $orders = $pdo->query("
        SELECT po.*, p.medication_name, p.dosage, p.frequency, p.instructions,
               u.first_name, u.last_name, u.nhs_id, u.date_of_birth, u.phone,
               doc.first_name as doc_first, doc.last_name as doc_last
        FROM prescription_orders po
        JOIN prescriptions p ON po.prescription_id=p.id
        JOIN users u ON po.patient_id=u.id
        JOIN users doc ON po.doctor_id=doc.id
        WHERE po.status IN {$statusGroup}
        ORDER BY po.ordered_at ASC
    ")->fetchAll();
} catch(\Exception $e) { $orders = []; }

try {
    $activeCount = (int)$pdo->query("SELECT COUNT(*) FROM prescription_orders WHERE status IN ('approved','preparing','dispatched')")->fetchColumn();
} catch(\Exception $e) { $activeCount = 0; }

$statusConfig = [
    'approved'   => ['color'=>'#1565C0','bg'=>'#DBEAFE','icon'=>'fa-check-circle',  'label'=>'Awaiting Preparation',
                     'next'=>'preparing','nextLabel'=>'Start Preparation','nextColor'=>'#0891B2'],
    'preparing'  => ['color'=>'#0891B2','bg'=>'#E0F2FE','icon'=>'fa-mortar-pestle', 'label'=>'Being Prepared',
                     'next'=>'dispatch','nextLabel'=>'Mark Dispatched','nextColor'=>'#7C3AED'],
    'dispatched' => ['color'=>'#7C3AED','bg'=>'#EDE9FE','icon'=>'fa-truck',         'label'=>'Dispatched',
                     'next'=>'deliver','nextLabel'=>'Confirm Delivered','nextColor'=>'#16A34A'],
    'delivered'  => ['color'=>'#16A34A','bg'=>'#DCFCE7','icon'=>'fa-check-double',  'label'=>'Delivered', 'next'=>null,'nextLabel'=>null,'nextColor'=>null],
    'rejected'   => ['color'=>'#DC2626','bg'=>'#FEE2E2','icon'=>'fa-times-circle',  'label'=>'Rejected',  'next'=>null,'nextLabel'=>null,'nextColor'=>null],
    'cancelled'  => ['color'=>'#6B7280','bg'=>'#F3F4F6','icon'=>'fa-ban',           'label'=>'Cancelled', 'next'=>null,'nextLabel'=>null,'nextColor'=>null],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Medicine Queue — HealthSphere Medical Team</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-pills" style="color:var(--hs-blue);"></i> Medicine Queue</div>
      <div class="page-subtitle">Process and dispatch approved prescription orders</div>
    </div>
  </div>

  <div class="hs-content">

    <!-- Tabs -->
    <div style="display:flex;gap:4px;background:#fff;border-radius:10px;padding:5px;border:1px solid var(--hs-border);margin-bottom:20px;width:fit-content;">
      <a href="?tab=active" style="padding:8px 18px;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:6px;<?= $activeTab==='active'?'background:var(--hs-blue);color:#fff;':'color:var(--hs-muted);' ?>">
        <i class="fas fa-clock"></i> Active Orders
        <?php if ($activeCount): ?><span style="background:#DC2626;color:#fff;border-radius:20px;padding:1px 7px;font-size:10px;"><?= $activeCount ?></span><?php endif; ?>
      </a>
      <a href="?tab=history" style="padding:8px 18px;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:6px;<?= $activeTab==='history'?'background:var(--hs-blue);color:#fff;':'color:var(--hs-muted);' ?>">
        <i class="fas fa-history"></i> Completed
      </a>
    </div>

    <!-- Workflow guide -->
    <?php if ($activeTab === 'active'): ?>
    <div style="background:linear-gradient(135deg,#EFF6FF,#F0FDF4);border:1px solid #BFDBFE;border-radius:12px;padding:14px 20px;margin-bottom:20px;display:flex;gap:20px;flex-wrap:wrap;align-items:center;">
      <?php foreach ([
        ['#F59E0B','fa-clock','1. Doctor Approved','Order is ready to prepare'],
        ['#0891B2','fa-mortar-pestle','2. Start Preparation','Dispense the medication'],
        ['#7C3AED','fa-truck','3. Dispatch','Send out for delivery or notify patient'],
        ['#16A34A','fa-check-double','4. Delivered','Mark as collected / delivered'],
      ] as [$c,$ic,$t,$d]): ?>
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;background:<?= $c ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="fas <?= $ic ?>" style="color:#fff;font-size:14px;"></i>
        </div>
        <div>
          <div style="font-size:12px;font-weight:700;color:var(--hs-navy);"><?= $t ?></div>
          <div style="font-size:11px;color:var(--hs-muted);"><?= $d ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!$orders): ?>
    <div style="text-align:center;padding:60px;color:var(--hs-muted);">
      <i class="fas fa-<?= $activeTab==='active'?'check-circle':'history' ?>" style="font-size:48px;opacity:.2;display:block;margin-bottom:16px;"></i>
      <p><?= $activeTab==='active' ? 'No active orders — all caught up!' : 'No completed orders yet.' ?></p>
    </div>
    <?php endif; ?>

    <?php foreach ($orders as $o):
      $sc = $statusConfig[$o['status']] ?? $statusConfig['approved'];
    ?>
    <div class="hs-card" style="margin-bottom:14px;border-left:4px solid <?= $sc['color'] ?>;">
      <div class="hs-card-body">
        <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;">

          <!-- Avatar -->
          <div style="width:46px;height:46px;border-radius:50%;background:var(--hs-blue-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:17px;flex-shrink:0;">
            <?= strtoupper(substr($o['first_name'],0,1).substr($o['last_name'],0,1)) ?>
          </div>

          <!-- Patient + Medication info -->
          <div style="flex:1;min-width:220px;">
            <div style="font-weight:800;font-size:15px;color:var(--hs-navy);"><?= e($o['first_name'].' '.$o['last_name']) ?></div>
            <div style="font-size:12px;color:var(--hs-muted);margin-bottom:8px;">
              NHS: <?= e($o['nhs_id']) ?>
              <?php if ($o['phone']): ?> · <?= e($o['phone']) ?><?php endif; ?>
              <?php if ($o['date_of_birth']): ?> · DOB: <?= formatDate($o['date_of_birth']) ?><?php endif; ?>
            </div>

            <div style="background:var(--hs-off-white);border-radius:10px;padding:12px 16px;display:inline-block;min-width:240px;">
              <div style="font-weight:800;font-size:14px;color:var(--hs-navy);">💊 <?= e($o['medication_name']) ?></div>
              <div style="font-size:13px;color:var(--hs-blue);font-weight:600;margin-top:2px;"><?= e($o['dosage']) ?></div>
              <div style="font-size:11px;color:var(--hs-muted);margin-top:3px;">
                <?= e($o['frequency']) ?><?= $o['instructions'] ? ' · '.e($o['instructions']) : '' ?>
              </div>
              <div style="font-size:11px;color:var(--hs-muted);margin-top:4px;">
                Prescribed by Dr. <?= e($o['doc_first'].' '.$o['doc_last']) ?>
              </div>
            </div>

            <!-- Delivery method -->
            <div style="margin-top:10px;">
              <?php if ($o['delivery_method'] === 'delivery'): ?>
              <div style="font-size:12px;color:#7C3AED;font-weight:700;">
                <i class="fas fa-truck"></i> Home Delivery
                <?= $o['delivery_address'] ? ' — '.e($o['delivery_address']) : '' ?>
              </div>
              <?php else: ?>
              <div style="font-size:12px;color:#1565C0;font-weight:700;">
                <i class="fas fa-store"></i> Collection from pharmacy
                <?= $o['pharmacy_name'] ? ' — '.e($o['pharmacy_name']) : '' ?>
              </div>
              <?php endif; ?>
            </div>

            <?php if ($o['patient_notes']): ?>
            <div style="margin-top:8px;background:#FEF3C7;border-radius:6px;padding:8px 12px;font-size:12px;color:#92400E;">
              <i class="fas fa-comment"></i> Patient note: <?= e($o['patient_notes']) ?>
            </div>
            <?php endif; ?>
            <?php if ($o['doctor_notes']): ?>
            <div style="margin-top:6px;background:#F4F8FF;border-radius:6px;padding:8px 12px;font-size:12px;color:var(--hs-navy);">
              <i class="fas fa-user-md" style="color:var(--hs-blue);"></i> Doctor note: <?= e($o['doctor_notes']) ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- Status & Actions -->
          <div style="text-align:right;flex-shrink:0;min-width:160px;">
            <div style="display:inline-flex;align-items:center;gap:6px;background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;padding:5px 12px;border-radius:20px;font-size:11px;font-weight:700;margin-bottom:8px;">
              <i class="fas <?= $sc['icon'] ?>"></i> <?= $sc['label'] ?>
            </div>
            <div style="font-size:11px;color:var(--hs-muted);margin-bottom:12px;"><?= timeAgo($o['ordered_at']) ?></div>

            <?php if ($sc['next']): ?>
            <button onclick="openUpdateModal(<?= $o['id'] ?>, '<?= $sc['next'] ?>', '<?= e(addslashes($o['first_name'].' '.$o['last_name'])) ?>', '<?= e(addslashes($o['medication_name'])) ?>')"
              style="background:<?= $sc['nextColor'] ?>;color:#fff;border:none;border-radius:8px;padding:9px 18px;font-size:12px;font-weight:700;cursor:pointer;display:block;width:100%;margin-bottom:6px;">
              <i class="fas <?= $sc['icon'] ?>"></i> <?= $sc['nextLabel'] ?>
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

  </div>
</div>

<!-- Update Modal -->
<div id="updateModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:3000;align-items:center;justify-content:center;padding:20px;" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#fff;border-radius:16px;width:100%;max-width:420px;box-shadow:0 24px 80px rgba(10,31,68,.25);overflow:hidden;">
    <div style="background:var(--hs-navy);color:#fff;padding:18px 24px;">
      <h4 style="margin:0;font-size:16px;" id="updateModalTitle">Update Order</h4>
      <div id="updateModalSub" style="font-size:12px;opacity:.7;margin-top:3px;"></div>
    </div>
    <div style="padding:22px;">
      <input type="hidden" id="updateOrderId">
      <input type="hidden" id="updateAction">

      <div style="margin-bottom:14px;">
        <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Pharmacy / Collection Point</label>
        <input type="text" id="updatePharmacy" class="form-control" placeholder="e.g. Boots Pharmacy, High Street">
      </div>
      <div style="margin-bottom:18px;">
        <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Note to patient (optional)</label>
        <textarea id="updateNote" class="form-control" rows="2" placeholder="e.g. Ready for collection from 2pm..."></textarea>
      </div>
      <div style="display:flex;gap:10px;">
        <button id="updateSubmitBtn" onclick="submitUpdate()"
          style="flex:1;background:var(--hs-blue);color:#fff;border:none;border-radius:9px;padding:11px;font-size:13px;font-weight:700;cursor:pointer;">
          Confirm Update
        </button>
        <button onclick="document.getElementById('updateModal').style.display='none'"
          style="padding:11px 18px;border:1.5px solid var(--hs-border);border-radius:9px;background:#fff;cursor:pointer;font-weight:600;color:var(--hs-muted);">
          Cancel
        </button>
      </div>
    </div>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function openUpdateModal(orderId, action, patient, medication) {
  document.getElementById('updateOrderId').value = orderId;
  document.getElementById('updateAction').value = action;
  document.getElementById('updateModalTitle').textContent = {
    preparing: 'Start Preparation', dispatch: 'Mark as Dispatched', deliver: 'Confirm Delivered'
  }[action] || 'Update Order';
  document.getElementById('updateModalSub').textContent = patient + ' — ' + medication;
  document.getElementById('updatePharmacy').value = '';
  document.getElementById('updateNote').value = '';
  document.getElementById('updateModal').style.display = 'flex';
}

function submitUpdate() {
  const btn = document.getElementById('updateSubmitBtn');
  btn.textContent = 'Updating...'; btn.disabled = true;
  fetch('../api/prescription-order.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({
      action:        document.getElementById('updateAction').value,
      order_id:      parseInt(document.getElementById('updateOrderId').value),
      doctor_notes:  document.getElementById('updateNote').value,
      pharmacy_name: document.getElementById('updatePharmacy').value,
    })
  }).then(r=>r.json()).then(d => {
    if (d.success) { showToast('Order updated successfully!', 'success'); setTimeout(()=>location.reload(), 1200); }
    else { showToast(d.error, 'error'); btn.textContent='Confirm Update'; btn.disabled=false; }
  }).catch(()=>{ showToast('Network error', 'error'); btn.textContent='Confirm Update'; btn.disabled=false; });
}
</script>
</body>
</html>
