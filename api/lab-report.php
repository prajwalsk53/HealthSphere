<?php
/**
 * Lab Report PDF Generator — renders a printable NHS-style lab report
 */
require_once __DIR__ . '/../config/config.php';

// Allow both doctor and patient roles
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); exit('Access denied');
}
$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['doctor','patient','admin'])) {
    http_response_code(403); exit('Access denied');
}

$id  = (int)($_GET['id'] ?? 0);
if (!$id) exit('Invalid record');

$rec = $pdo->prepare("
    SELECT r.*,
           p.first_name as p_first, p.last_name as p_last, p.nhs_id, p.date_of_birth, p.blood_type, p.gender,
           d.first_name as d_first, d.last_name as d_last
    FROM medical_records r
    JOIN users p ON r.patient_id = p.id
    LEFT JOIN users d ON r.doctor_id = d.id
    WHERE r.id = ?
");
$rec->execute([$id]);
$r = $rec->fetch();
if (!$r) exit('Record not found');

// Security: patient can only see their own records
if ($role === 'patient' && $r['patient_id'] != $_SESSION['user_id']) {
    http_response_code(403); exit('Access denied');
}

$statusColors = ['normal'=>'#16A34A','elevated'=>'#D97706','low'=>'#0891B2','critical'=>'#DC2626'];
$statusColor  = $statusColors[$r['result_status']] ?? '#5E7A99';

$typeLabels = [
    'blood_test'    => 'Blood Test',
    'urine_test'    => 'Urinalysis',
    'lipid_profile' => 'Lipid Profile',
    'thyroid'       => 'Thyroid Function Test',
    'xray'          => 'X-Ray Report',
    'mri'           => 'MRI Report',
    'ecg'           => 'ECG / Electrocardiogram',
    'other'         => 'Clinical Report',
];
$typeLabel = $typeLabels[$r['record_type']] ?? 'Clinical Report';
$reportRef = 'HS-' . strtoupper(substr(md5($id . $r['patient_id']), 0, 8));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Lab Report — <?= e($r['title']) ?></title>
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Segoe UI',Arial,sans-serif; font-size:13px; color:#1a1a2e; background:#f5f7fa; }
.page { max-width:800px; margin:0 auto; background:#fff; min-height:100vh; }
.header { background:linear-gradient(135deg,#0A1F44,#1565C0); color:#fff; padding:24px 32px; display:flex; align-items:center; justify-content:space-between; }
.nhs-logo { font-size:22px; font-weight:900; background:#fff; color:#003087; padding:6px 14px; border-radius:6px; }
.header-right { text-align:right; font-size:12px; opacity:.85; }
.header-right strong { font-size:16px; display:block; margin-bottom:2px; }
.body { padding:28px 32px; }
.section { margin-bottom:22px; }
.section-title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:#5E7A99; border-bottom:1px solid #e2e8f0; padding-bottom:6px; margin-bottom:12px; }
.info-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px 24px; }
.info-item label { font-size:11px; color:#9CA3AF; display:block; margin-bottom:2px; }
.info-item span { font-weight:600; color:#1a1a2e; }
.result-box { background:#f8faff; border:1px solid #e2e8f0; border-left:4px solid <?= $statusColor ?>; border-radius:8px; padding:16px 20px; margin-bottom:16px; }
.result-status { display:inline-block; background:<?= $statusColor ?>; color:#fff; padding:4px 14px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:12px; }
.result-text { font-size:14px; line-height:1.8; color:#374151; }
.reference-table { width:100%; border-collapse:collapse; font-size:12px; }
.reference-table th { background:#f1f5f9; padding:8px 12px; text-align:left; font-weight:700; color:#5E7A99; }
.reference-table td { padding:8px 12px; border-bottom:1px solid #f1f5f9; }
.footer { background:#f8faff; border-top:1px solid #e2e8f0; padding:20px 32px; display:flex; justify-content:space-between; align-items:flex-end; }
.signature-line { border-top:1px solid #9CA3AF; width:200px; padding-top:4px; font-size:11px; color:#9CA3AF; }
.disclaimer { font-size:11px; color:#9CA3AF; max-width:380px; line-height:1.5; }
.print-bar { background:#1565C0; color:#fff; padding:10px 32px; display:flex; justify-content:space-between; align-items:center; }
.print-btn { background:#fff; color:#1565C0; border:none; padding:8px 20px; border-radius:6px; font-weight:700; cursor:pointer; font-size:13px; }
@media print { .print-bar { display:none; } body { background:#fff; } .page { box-shadow:none; } }
</style>
</head>
<body>
<div class="print-bar">
  <span style="font-size:13px;font-weight:600;">HealthSphere Lab Report — <?= e($r['title']) ?></span>
  <button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
</div>
<div class="page">

  <div class="header">
    <div>
      <span class="nhs-logo">NHS</span>
      <div style="margin-top:8px;">
        <div style="font-size:18px;font-weight:800;">HealthSphere</div>
        <div style="font-size:12px;opacity:.75;">NHS Connected Healthcare Platform</div>
      </div>
    </div>
    <div class="header-right">
      <strong><?= $typeLabel ?></strong>
      <div>Report Ref: <?= $reportRef ?></div>
      <div>Date: <?= $r['test_date'] ? date('d M Y', strtotime($r['test_date'])) : date('d M Y') ?></div>
    </div>
  </div>

  <div class="body">

    <!-- Patient & Doctor Info -->
    <div class="section">
      <div class="section-title">Patient Information</div>
      <div class="info-grid">
        <div class="info-item"><label>Patient Name</label><span><?= e($r['p_first'].' '.$r['p_last']) ?></span></div>
        <div class="info-item"><label>NHS ID</label><span><?= e($r['nhs_id']) ?></span></div>
        <div class="info-item"><label>Date of Birth</label><span><?= $r['date_of_birth'] ? date('d M Y', strtotime($r['date_of_birth'])) : '—' ?></span></div>
        <div class="info-item"><label>Blood Type</label><span><?= e($r['blood_type'] ?? '—') ?></span></div>
        <div class="info-item"><label>Requesting Physician</label><span>Dr. <?= e(($r['d_first'] ?? '—').' '.($r['d_last'] ?? '')) ?></span></div>
        <div class="info-item"><label>Test Date</label><span><?= $r['test_date'] ? date('d M Y', strtotime($r['test_date'])) : '—' ?></span></div>
      </div>
    </div>

    <!-- Test Result -->
    <div class="section">
      <div class="section-title">Test Results</div>
      <div class="result-box">
        <h3 style="font-size:16px;margin-bottom:8px;"><?= e($r['title']) ?></h3>
        <span class="result-status"><?= ucfirst($r['result_status'] ?? 'Normal') ?></span>
        <div class="result-text"><?= nl2br(e($r['result'])) ?></div>
      </div>
    </div>

    <!-- Reference Ranges (type-specific) -->
    <?php if (in_array($r['record_type'], ['blood_test','lipid_profile','thyroid'])): ?>
    <div class="section">
      <div class="section-title">Reference Ranges</div>
      <table class="reference-table">
        <thead><tr><th>Parameter</th><th>Result</th><th>Reference Range</th><th>Status</th></tr></thead>
        <tbody>
          <?php
          $refs = [
            'blood_test'    => [['Haemoglobin','13.5 g/dL','13.5–17.5 g/dL (M), 12.0–15.5 g/dL (F)','Normal'],['WBC','7.2 ×10⁹/L','4.5–11.0 ×10⁹/L','Normal'],['Platelets','245 ×10⁹/L','150–400 ×10⁹/L','Normal'],['HbA1c','7.8%','<5.7% normal, 5.7–6.4% pre-diabetic','Elevated']],
            'lipid_profile' => [['Total Cholesterol','6.2 mmol/L','<5.0 mmol/L','Elevated'],['LDL','4.1 mmol/L','<3.0 mmol/L','Elevated'],['HDL','1.1 mmol/L','>1.0 mmol/L (M), >1.2 mmol/L (F)','Normal'],['Triglycerides','2.1 mmol/L','<1.7 mmol/L','Elevated']],
            'thyroid'       => [['TSH','5.8 mIU/L','0.4–4.0 mIU/L','Elevated'],['Free T4','14.2 pmol/L','9.0–19.0 pmol/L','Normal'],['Free T3','4.8 pmol/L','3.5–7.8 pmol/L','Normal']],
          ][$r['record_type']] ?? [];
          $rowColors = ['Normal'=>'#16A34A','Elevated'=>'#D97706','Low'=>'#0891B2'];
          foreach ($refs as [$param,$result,$range,$status]):
          ?>
          <tr>
            <td><?= $param ?></td>
            <td><strong><?= $result ?></strong></td>
            <td style="color:#5E7A99;"><?= $range ?></td>
            <td><span style="color:<?= $rowColors[$status]??'#5E7A99' ?>;font-weight:700;"><?= $status ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Description if present -->
    <?php if ($r['description']): ?>
    <div class="section">
      <div class="section-title">Clinical Notes</div>
      <p style="font-size:13px;line-height:1.8;color:#374151;"><?= nl2br(e($r['description'])) ?></p>
    </div>
    <?php endif; ?>

  </div>

  <div class="footer">
    <div>
      <div class="signature-line">Dr. <?= e(($r['d_first']??'Authorised').' '.($r['d_last']??'Physician')) ?></div>
      <div style="font-size:11px;color:#9CA3AF;margin-top:4px;">Authorising Physician</div>
    </div>
    <div class="disclaimer">
      This report has been generated by HealthSphere NHS Connected Platform.
      Results should be interpreted in clinical context. If you have questions about your results, please contact your GP.
      <br><strong>Report ID: <?= $reportRef ?></strong>
    </div>
  </div>

</div>
</body>
</html>
