<?php
require_once __DIR__ . '/../config/config.php';
requireRole('doctor');
$user = getCurrentUser(); $uid = $user['id'];

// Get patients this doctor has treated
$patients = $pdo->prepare("
    SELECT DISTINCT u.*, MAX(a.appointment_date) as last_visit,
        (SELECT al.allergen FROM allergies al WHERE al.patient_id=u.id AND al.is_active=1 LIMIT 1) as allergy,
        (SELECT hm.blood_pressure_systolic FROM health_metrics hm WHERE hm.patient_id=u.id ORDER BY hm.metric_date DESC LIMIT 1) as bp_sys,
        (SELECT hm.heart_rate FROM health_metrics hm WHERE hm.patient_id=u.id ORDER BY hm.metric_date DESC LIMIT 1) as heart_rate
    FROM appointments a JOIN users u ON a.patient_id=u.id
    WHERE a.doctor_id=? GROUP BY u.id ORDER BY last_visit DESC
");
$patients->execute([$uid]); $patients = $patients->fetchAll();
$notifCount = getUnreadCount($pdo, $uid); $msgCount = getUnreadMessages($pdo, $uid);
$viewId = (int)($_GET['id'] ?? 0);
$viewPatient = null;
if ($viewId) {
    $vp = $pdo->prepare("SELECT * FROM users WHERE id=?"); $vp->execute([$viewId]); $viewPatient = $vp->fetch();
    $vpRecords = $pdo->prepare("SELECT * FROM medical_records WHERE patient_id=? ORDER BY test_date DESC"); $vpRecords->execute([$viewId]); $vpRecords = $vpRecords->fetchAll();
    $vpMeds = $pdo->prepare("SELECT * FROM prescriptions WHERE patient_id=? ORDER BY is_active DESC, created_at DESC"); $vpMeds->execute([$viewId]); $vpMeds = $vpMeds->fetchAll();
    $vpNotes = $pdo->prepare("SELECT cn.*, u.first_name, u.last_name FROM clinical_notes cn JOIN users u ON cn.doctor_id=u.id WHERE cn.patient_id=? ORDER BY cn.created_at DESC"); $vpNotes->execute([$viewId]); $vpNotes = $vpNotes->fetchAll();
    logAccess($pdo, $uid, $viewId, 'VIEW_PATIENT_RECORD');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Patients — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div><div class="page-title"><i class="fas fa-users" style="color:var(--hs-blue);"></i> My Patients</div></div>
    <div class="topbar-actions">
      <div class="input-icon-wrap" style="width:250px;">
        <i class="fas fa-search"></i>
        <input type="text" id="patSearch" placeholder="Search patients..." class="form-control" style="font-size:13px;">
      </div>
    </div>
  </div>
  <div class="hs-content">
    <div style="display:grid;grid-template-columns:<?= $viewPatient ? '360px 1fr' : '1fr' ?>;gap:20px;">
      <!-- Patient list -->
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-list"></i> Patient List (<?= count($patients) ?>)</span></div>
        <div class="hs-card-body p-0">
          <?php foreach ($patients as $p): ?>
          <a href="?id=<?= $p['id'] ?>" style="text-decoration:none;display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--hs-border);transition:var(--transition);background:<?= $viewId===$p['id']?'#EFF6FF':'#fff' ?>;" onmouseover="this.style.background='#F4F8FF'" onmouseout="this.style.background='<?= $viewId===$p['id']?'#EFF6FF':'#fff' ?>'">
            <div style="width:40px;height:40px;border-radius:50%;background:var(--hs-blue-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;flex-shrink:0;"><?= strtoupper(substr($p['first_name'],0,1).substr($p['last_name'],0,1)) ?></div>
            <div style="flex:1;">
              <div style="font-weight:700;color:var(--hs-navy);font-size:13.5px;" class="pat-name"><?= e($p['first_name'].' '.$p['last_name']) ?></div>
              <div style="font-size:12px;color:var(--hs-muted);">NHS: <?= e($p['nhs_id']) ?> · Last: <?= $p['last_visit'] ? formatDate($p['last_visit'], 'd M') : 'N/A' ?></div>
              <?php if ($p['allergy']): ?>
              <div style="font-size:11px;color:var(--hs-danger);margin-top:2px;"><i class="fas fa-exclamation-triangle"></i> <?= e($p['allergy']) ?></div>
              <?php endif; ?>
            </div>
            <?php if ($p['bp_sys']): ?>
            <div style="text-align:right;font-size:12px;">
              <div style="font-weight:700;color:var(--hs-navy);"><?= $p['bp_sys'].'/'.(int)($p['bp_sys']-42) ?></div>
              <div style="color:var(--hs-muted);">mmHg</div>
            </div>
            <?php endif; ?>
          </a>
          <?php endforeach; ?>
          <?php if (!$patients): ?>
          <div style="padding:30px;text-align:center;color:var(--hs-muted);">No patients yet.</div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($viewPatient): ?>
      <!-- Patient detail -->
      <div>
        <div class="hs-card" style="margin-bottom:16px;">
          <div class="hs-card-body" style="background:var(--hs-navy);border-radius:11px;color:#fff;display:flex;align-items:center;gap:16px;">
            <div style="width:60px;height:60px;border-radius:50%;background:var(--hs-blue);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;"><?= strtoupper(substr($viewPatient['first_name'],0,1).substr($viewPatient['last_name'],0,1)) ?></div>
            <div style="flex:1;">
              <h4 style="margin:0;font-size:18px;"><?= e($viewPatient['first_name'].' '.$viewPatient['last_name']) ?></h4>
              <div style="font-size:13px;opacity:.7;">NHS ID: <?= e($viewPatient['nhs_id']) ?> · DOB: <?= $viewPatient['date_of_birth'] ? formatDate($viewPatient['date_of_birth']) : '—' ?> · Blood: <?= e($viewPatient['blood_type'] ?? '—') ?></div>
            </div>
            <a href="messages.php?with=<?= $viewPatient['id'] ?>" class="btn-hs btn-primary-hs btn-sm-hs"><i class="fas fa-comment"></i> Message</a>
          </div>
        </div>

        <!-- Records -->
        <div class="hs-card" style="margin-bottom:16px;">
          <div class="hs-card-header"><span class="card-title"><i class="fas fa-flask"></i> Lab Results</span></div>
          <div class="hs-card-body p-0">
            <table class="hs-table">
              <thead><tr><th>Test</th><th>Result</th><th>Status</th><th>Date</th></tr></thead>
              <tbody>
                <?php foreach ($vpRecords as $r): ?>
                <tr>
                  <td><strong><?= e($r['title']) ?></strong></td>
                  <td style="font-size:12px;max-width:200px;"><?= e(substr($r['result'],0,80)).'...' ?></td>
                  <td><?= getStatusBadge($r['result_status']) ?></td>
                  <td><?= formatDate($r['test_date']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Notes -->
        <div class="hs-card">
          <div class="hs-card-header"><span class="card-title"><i class="fas fa-notes-medical"></i> Clinical Notes</span></div>
          <div class="hs-card-body">
            <?php foreach ($vpNotes as $note): ?>
            <div style="background:var(--hs-off-white);border-radius:8px;padding:12px;margin-bottom:10px;border-left:3px solid var(--hs-blue);">
              <div style="font-size:12px;color:var(--hs-muted);margin-bottom:6px;">Dr. <?= e($note['first_name'].' '.$note['last_name']) ?> · <?= timeAgo($note['created_at']) ?></div>
              <p style="font-size:13px;margin:0;"><?= e($note['note_text']) ?></p>
            </div>
            <?php endforeach; ?>
            <form method="POST" action="add-note.php">
              <input type="hidden" name="patient_id" value="<?= $viewPatient['id'] ?>">
              <textarea name="note" class="form-control" rows="2" placeholder="Add clinical note..." style="margin-bottom:10px;"></textarea>
              <button type="submit" class="btn-hs btn-primary-hs btn-sm-hs"><i class="fas fa-save"></i> Add Note</button>
            </form>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="../assets/js/main.js"></script>
<script>
document.getElementById('patSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.pat-name').forEach(el => {
    el.closest('a').style.display = el.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});
</script>
</body>
</html>
