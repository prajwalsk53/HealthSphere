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
$successMsg = $errorMsg = '';

// ── Handle Add Prescription (POST) ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_prescription']) && $viewId) {
    $medName  = trim($_POST['medication_name'] ?? '');
    $dosage   = trim($_POST['dosage'] ?? '');
    $freq     = trim($_POST['frequency'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $instruct = trim($_POST['instructions'] ?? '');
    $start    = $_POST['start_date'] ?? date('Y-m-d');
    if ($medName && $dosage && $freq) {
        $end = date('Y-m-d', strtotime($start . ' +' . (intval($duration) ?: 30) . ' days'));
        $pdo->prepare("INSERT INTO prescriptions (patient_id,doctor_id,medication_name,dosage,frequency,duration,instructions,start_date,end_date,is_active) VALUES (?,?,?,?,?,?,?,?,?,1)")
            ->execute([$viewId,$uid,$medName,$dosage,$freq,$duration,$instruct,$start,$end]);
        $successMsg = "Prescription for {$medName} added successfully.";
    } else { $errorMsg = "Medication name, dosage and frequency are required."; }
}

// ── Handle Add Lab Result (POST) ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lab_result']) && $viewId) {
    $rType   = $_POST['record_type'] ?? 'blood_test';
    $title   = trim($_POST['title'] ?? '');
    $result  = trim($_POST['result'] ?? '');
    $status  = $_POST['result_status'] ?? 'normal';
    $testDt  = $_POST['test_date'] ?? date('Y-m-d');
    $desc    = trim($_POST['description'] ?? '');
    if ($title && $result) {
        $pdo->prepare("INSERT INTO medical_records (patient_id,doctor_id,record_type,title,result,result_status,description,test_date) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$viewId,$uid,$rType,$title,$result,$status,$desc,$testDt]);
        $successMsg = "Lab result '{$title}' added and is now visible to the patient.";
    } else { $errorMsg = "Title and result are required."; }
}

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

        <?php if ($successMsg): ?>
        <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:10px;padding:12px 16px;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
          <i class="fas fa-check-circle" style="color:#16A34A;"></i><span style="color:#15803D;font-weight:600;"><?= e($successMsg) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
        <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:12px 16px;margin-bottom:14px;">
          <i class="fas fa-exclamation-circle" style="color:#DC2626;"></i> <span style="color:#991B1B;"><?= e($errorMsg) ?></span>
        </div>
        <?php endif; ?>

        <!-- Patient header -->
        <div class="hs-card" style="margin-bottom:14px;">
          <div class="hs-card-body" style="background:var(--hs-navy);border-radius:11px;color:#fff;display:flex;align-items:center;gap:16px;">
            <div style="width:60px;height:60px;border-radius:50%;background:var(--hs-blue);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;"><?= strtoupper(substr($viewPatient['first_name'],0,1).substr($viewPatient['last_name'],0,1)) ?></div>
            <div style="flex:1;">
              <h4 style="margin:0;font-size:18px;"><?= e($viewPatient['first_name'].' '.$viewPatient['last_name']) ?></h4>
              <div style="font-size:13px;opacity:.7;">NHS ID: <?= e($viewPatient['nhs_id']) ?> · DOB: <?= $viewPatient['date_of_birth'] ? formatDate($viewPatient['date_of_birth']) : '—' ?> · Blood: <?= e($viewPatient['blood_type'] ?? '—') ?></div>
            </div>
            <a href="messages.php?with=<?= $viewPatient['id'] ?>" class="btn-hs btn-primary-hs btn-sm-hs"><i class="fas fa-comment"></i> Message</a>
          </div>
        </div>

        <!-- Tab nav -->
        <div style="display:flex;gap:4px;background:#fff;border-radius:10px;padding:5px;border:1px solid var(--hs-border);margin-bottom:14px;width:fit-content;">
          <?php foreach ([['labs','fa-flask','Lab Results'],['rx','fa-pills','Prescriptions'],['add-lab','fa-plus-circle','Add Lab Result'],['add-rx','fa-prescription','Add Prescription'],['notes','fa-notes-medical','Notes']] as [$k,$ic,$lbl]): ?>
          <button onclick="showPTab('<?= $k ?>')" id="ptab-<?= $k ?>" style="padding:7px 14px;border-radius:7px;border:none;font-size:12px;font-weight:600;cursor:pointer;background:<?= $k==='labs'?'var(--hs-blue)':'transparent' ?>;color:<?= $k==='labs'?'#fff':'var(--hs-muted)' ?>;font-family:inherit;white-space:nowrap;">
            <i class="fas <?= $ic ?>"></i> <?= $lbl ?>
          </button>
          <?php endforeach; ?>
        </div>

        <!-- Lab Results -->
        <div id="ppanel-labs">
        <div class="hs-card">
          <div class="hs-card-header">
            <span class="card-title"><i class="fas fa-flask"></i> Lab Results (<?= count($vpRecords) ?>)</span>
            <button onclick="showPTab('add-lab')" style="font-size:12px;background:#EFF6FF;color:var(--hs-blue);border:1px solid #BFDBFE;border-radius:6px;padding:4px 12px;cursor:pointer;font-weight:600;">+ Add Result</button>
          </div>
          <div class="hs-card-body p-0">
            <table class="hs-table">
              <thead><tr><th>Test</th><th>Result</th><th>Status</th><th>Date</th><th>Report</th></tr></thead>
              <tbody>
                <?php if (!$vpRecords): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--hs-muted);padding:20px;">No lab results yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($vpRecords as $r): ?>
                <tr>
                  <td><strong><?= e($r['title']) ?></strong><br><span style="font-size:11px;color:var(--hs-muted);text-transform:capitalize;"><?= str_replace('_',' ',$r['record_type']) ?></span></td>
                  <td style="font-size:12px;max-width:180px;color:var(--hs-text);"><?= e(strlen($r['result'])>80 ? substr($r['result'],0,80).'…' : $r['result']) ?></td>
                  <td><?= getStatusBadge($r['result_status']) ?></td>
                  <td><?= formatDate($r['test_date']) ?></td>
                  <td>
                    <a href="../api/lab-report.php?id=<?= $r['id'] ?>" target="_blank"
                       style="font-size:11px;background:#1565C0;color:#fff;padding:4px 10px;border-radius:5px;text-decoration:none;font-weight:600;white-space:nowrap;">
                      <i class="fas fa-file-pdf"></i> PDF
                    </a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        </div>

        <!-- Prescriptions -->
        <div id="ppanel-rx" style="display:none;">
        <div class="hs-card">
          <div class="hs-card-header">
            <span class="card-title"><i class="fas fa-pills"></i> Prescriptions (<?= count($vpMeds) ?>)</span>
            <button onclick="showPTab('add-rx')" style="font-size:12px;background:#EFF6FF;color:var(--hs-blue);border:1px solid #BFDBFE;border-radius:6px;padding:4px 12px;cursor:pointer;font-weight:600;">+ New Prescription</button>
          </div>
          <div class="hs-card-body p-0">
            <table class="hs-table">
              <thead><tr><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
              <tbody>
                <?php if (!$vpMeds): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--hs-muted);padding:20px;">No prescriptions yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($vpMeds as $m): ?>
                <tr>
                  <td><strong><?= e($m['medication_name']) ?></strong></td>
                  <td><?= e($m['dosage']) ?></td>
                  <td><?= e($m['frequency']) ?></td>
                  <td><?= e($m['duration'] ?: '—') ?></td>
                  <td><?= $m['start_date'] ? formatDate($m['start_date']) : '—' ?></td>
                  <td style="color:<?= $m['end_date'] && $m['end_date'] < date('Y-m-d') ? '#DC2626' : 'inherit' ?>;"><?= $m['end_date'] ? formatDate($m['end_date']) : '—' ?></td>
                  <td><span style="background:<?= $m['is_active']?'#DCFCE7':'#F3F4F6' ?>;color:<?= $m['is_active']?'#166534':'#6B7280' ?>;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;"><?= $m['is_active']?'Active':'Ended' ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        </div>

        <!-- Add Lab Result Form -->
        <div id="ppanel-add-lab" style="display:none;">
        <div class="hs-card">
          <div class="hs-card-header"><span class="card-title"><i class="fas fa-plus-circle" style="color:#16A34A;"></i> Add Lab Result</span></div>
          <div class="hs-card-body">
            <form method="POST" action="?id=<?= $viewId ?>">
              <input type="hidden" name="add_lab_result" value="1">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                <div>
                  <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Test Type <span style="color:#DC2626;">*</span></label>
                  <select name="record_type" class="form-control form-select">
                    <?php foreach (['blood_test'=>'Blood Test','urine_test'=>'Urinalysis','lipid_profile'=>'Lipid Profile','thyroid'=>'Thyroid Function','xray'=>'X-Ray','mri'=>'MRI','ecg'=>'ECG','other'=>'Other'] as $v=>$l): ?>
                    <option value="<?= $v ?>"><?= $l ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Result Status <span style="color:#DC2626;">*</span></label>
                  <select name="result_status" class="form-control form-select">
                    <option value="normal">Normal</option>
                    <option value="elevated">Elevated</option>
                    <option value="low">Low</option>
                    <option value="critical">Critical</option>
                  </select>
                </div>
                <div>
                  <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Test Title <span style="color:#DC2626;">*</span></label>
                  <input type="text" name="title" class="form-control" placeholder="e.g. Full Blood Count" required>
                </div>
                <div>
                  <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Test Date</label>
                  <input type="date" name="test_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
              </div>
              <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Result / Findings <span style="color:#DC2626;">*</span></label>
                <textarea name="result" class="form-control" rows="3" placeholder="e.g. Haemoglobin 13.5 g/dL — within normal range..." required></textarea>
              </div>
              <div style="margin-bottom:16px;">
                <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Clinical Notes (optional)</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Clinical interpretation, recommendations..."></textarea>
              </div>
              <div style="display:flex;gap:10px;">
                <button type="submit" class="btn-hs btn-primary-hs"><i class="fas fa-save"></i> Save & Notify Patient</button>
                <button type="button" onclick="showPTab('labs')" class="btn-hs btn-outline-hs">Cancel</button>
              </div>
              <p style="font-size:11px;color:var(--hs-muted);margin-top:10px;"><i class="fas fa-info-circle"></i> This result will immediately appear in the patient's Medical Records dashboard with a PDF download button.</p>
            </form>
          </div>
        </div>
        </div>

        <!-- Add Prescription Form -->
        <div id="ppanel-add-rx" style="display:none;">
        <div class="hs-card">
          <div class="hs-card-header"><span class="card-title"><i class="fas fa-prescription" style="color:#7C3AED;"></i> Add Prescription</span></div>
          <div class="hs-card-body">
            <form method="POST" action="?id=<?= $viewId ?>">
              <input type="hidden" name="add_prescription" value="1">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                <div>
                  <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Medication Name <span style="color:#DC2626;">*</span></label>
                  <input type="text" name="medication_name" class="form-control" placeholder="e.g. Metformin" required>
                </div>
                <div>
                  <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Dosage <span style="color:#DC2626;">*</span></label>
                  <input type="text" name="dosage" class="form-control" placeholder="e.g. 500mg" required>
                </div>
                <div>
                  <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Frequency <span style="color:#DC2626;">*</span></label>
                  <select name="frequency" class="form-control form-select" required>
                    <option value="">Select frequency...</option>
                    <option>Once daily (morning)</option>
                    <option>Once daily (night)</option>
                    <option>Twice daily</option>
                    <option>Three times daily</option>
                    <option>Every 8 hours</option>
                    <option>As needed (PRN)</option>
                    <option>Weekly</option>
                  </select>
                </div>
                <div>
                  <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Duration</label>
                  <select name="duration" class="form-control form-select">
                    <option value="7">7 days</option>
                    <option value="14">14 days</option>
                    <option value="30" selected>1 month</option>
                    <option value="60">2 months</option>
                    <option value="90">3 months</option>
                    <option value="180">6 months</option>
                  </select>
                </div>
                <div>
                  <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Start Date</label>
                  <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div>
                  <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Special Instructions</label>
                  <input type="text" name="instructions" class="form-control" placeholder="e.g. Take with food">
                </div>
              </div>
              <div style="display:flex;gap:10px;">
                <button type="submit" class="btn-hs btn-primary-hs"><i class="fas fa-pills"></i> Issue Prescription</button>
                <button type="button" onclick="showPTab('rx')" class="btn-hs btn-outline-hs">Cancel</button>
              </div>
              <p style="font-size:11px;color:var(--hs-muted);margin-top:10px;"><i class="fas fa-info-circle"></i> This prescription will immediately appear in the patient's Medications tab.</p>
            </form>
          </div>
        </div>
        </div>

        <!-- Clinical Notes -->
        <div id="ppanel-notes" style="display:none;">
        <div class="hs-card">
          <div class="hs-card-header"><span class="card-title"><i class="fas fa-notes-medical"></i> Clinical Notes</span></div>
          <div class="hs-card-body">
            <?php if (!$vpNotes): ?>
            <p style="color:var(--hs-muted);font-size:13px;margin-bottom:14px;">No notes yet.</p>
            <?php endif; ?>
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

function showPTab(key) {
  ['labs','rx','add-lab','add-rx','notes'].forEach(k => {
    const p = document.getElementById('ppanel-'+k);
    const b = document.getElementById('ptab-'+k);
    if (p) p.style.display = k===key ? 'block' : 'none';
    if (b) { b.style.background = k===key ? 'var(--hs-blue)' : 'transparent'; b.style.color = k===key ? '#fff' : 'var(--hs-muted)'; }
  });
}
</script>
</body>
</html>
