<?php
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
$user = getCurrentUser();
$uid  = $user['id'];

// Fetch all data
$userFull = $pdo->prepare("SELECT * FROM users WHERE id = ?"); $userFull->execute([$uid]); $userFull = $userFull->fetch();
$allergies = $pdo->prepare("SELECT * FROM allergies WHERE patient_id = ? ORDER BY is_active DESC, id DESC"); $allergies->execute([$uid]); $allergies = $allergies->fetchAll();
$vaccinations = $pdo->prepare("SELECT * FROM vaccinations WHERE patient_id = ? ORDER BY is_completed ASC, next_due_date ASC"); $vaccinations->execute([$uid]); $vaccinations = $vaccinations->fetchAll();
$records = $pdo->prepare("SELECT r.*, u.first_name, u.last_name FROM medical_records r LEFT JOIN users u ON r.doctor_id = u.id WHERE r.patient_id = ? ORDER BY r.test_date DESC"); $records->execute([$uid]); $records = $records->fetchAll();
$prescriptions = $pdo->prepare("SELECT p.*, u.first_name, u.last_name FROM prescriptions p LEFT JOIN users u ON p.doctor_id = u.id WHERE p.patient_id = ? ORDER BY p.is_active DESC, p.created_at DESC"); $prescriptions->execute([$uid]); $prescriptions = $prescriptions->fetchAll();
$familyHxRaw = $pdo->prepare("SELECT * FROM family_history WHERE patient_id = ? ORDER BY relation, relation_name, id"); $familyHxRaw->execute([$uid]); $familyHxRaw = $familyHxRaw->fetchAll();
// Deduplicate by relation+name+condition
$seen = []; $familyHx = [];
foreach ($familyHxRaw as $f) {
    $key = $f['relation'].'|'.($f['relation_name']??'').'|'.$f['condition_name'];
    if (!in_array($key, $seen)) { $seen[] = $key; $familyHx[] = $f; }
}
// Group by relation for the tree
$famByRel = [];
foreach ($familyHx as $f) { $famByRel[$f['relation']][] = $f; }
// Group cousins by person name
$patCousins = []; $matCousins = [];
foreach ($famByRel['cousin_paternal'] ?? [] as $c) {
    $patCousins[$c['relation_name']][] = $c;
}
foreach ($famByRel['cousin_maternal'] ?? [] as $c) {
    $matCousins[$c['relation_name']][] = $c;
}
$appointments = $pdo->prepare("SELECT a.*, u.first_name, u.last_name, d.specialization FROM appointments a JOIN users u ON a.doctor_id=u.id LEFT JOIN doctors d ON u.id=d.user_id WHERE a.patient_id=? ORDER BY a.appointment_date DESC LIMIT 10"); $appointments->execute([$uid]); $appointments = $appointments->fetchAll();

$notifCount = getUnreadCount($pdo, $uid);
$msgCount   = getUnreadMessages($pdo, $uid);
$activeTab  = $_GET['tab'] ?? 'records';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medical Records — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-file-medical" style="color:var(--hs-blue);"></i> Medical Records</div>
      <div class="page-subtitle">Patient: <?= e($userFull['first_name'].' '.$userFull['last_name']) ?> · NHS: <?= e($user['nhs_id']) ?></div>
    </div>
    <div class="topbar-actions">
      <div style="font-size:12px;color:var(--hs-muted);display:flex;gap:16px;align-items:center;">
        <span><i class="fas fa-tint"></i> <?= e($userFull['blood_type'] ?? '—') ?></span>
        <span><i class="fas fa-birthday-cake"></i> <?= $userFull['date_of_birth'] ? formatDate($userFull['date_of_birth']) : '—' ?></span>
      </div>
    </div>
  </div>

  <div class="hs-content">
    <!-- Tab Navigation -->
    <div style="display:flex;gap:4px;margin-bottom:20px;background:#fff;border-radius:10px;padding:6px;border:1px solid var(--hs-border);width:fit-content;">
      <?php
      $tabs = [
        ['key'=>'records',       'icon'=>'fa-flask',       'label'=>'Lab Results'],
        ['key'=>'appointments',  'icon'=>'fa-calendar',    'label'=>'Appointments'],
        ['key'=>'allergies',     'icon'=>'fa-allergies',   'label'=>'Allergies'],
        ['key'=>'vaccinations',  'icon'=>'fa-syringe',     'label'=>'Vaccinations'],
        ['key'=>'medications',   'icon'=>'fa-pills',       'label'=>'Medications'],
        ['key'=>'family',        'icon'=>'fa-users',       'label'=>'Family History'],
      ];
      foreach ($tabs as $t):
      ?>
      <a href="?tab=<?= $t['key'] ?>" style="padding:8px 16px;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:6px;transition:var(--transition);<?= $activeTab===$t['key'] ? 'background:var(--hs-blue);color:#fff;' : 'color:var(--hs-muted);' ?>">
        <i class="fas <?= $t['icon'] ?>"></i> <?= $t['label'] ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Lab Results -->
    <?php if ($activeTab === 'records'): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
      <?php foreach ($records as $r):
        $statusColor = ['normal'=>'#16A34A','elevated'=>'#D97706','low'=>'#0891B2','critical'=>'#DC2626'][$r['result_status']] ?? '#5E7A99';
        $icons = ['blood_test'=>'🩸','urine_test'=>'🧪','lipid_profile'=>'💉','thyroid'=>'🦋','xray'=>'🔬','mri'=>'🧠','ecg'=>'❤️','other'=>'📋'];
      ?>
      <div class="hs-card" style="border-top:3px solid <?= $statusColor ?>;">
        <div class="hs-card-body">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
            <div style="font-size:28px;"><?= $icons[$r['record_type']] ?? '📋' ?></div>
            <div style="flex:1;">
              <div style="font-weight:700;font-size:14px;color:var(--hs-navy);"><?= e($r['title']) ?></div>
              <div style="font-size:12px;color:var(--hs-muted);"><?= formatDate($r['test_date']) ?></div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
              <?= getStatusBadge($r['result_status']) ?>
              <a href="../api/lab-report.php?id=<?= $r['id'] ?>" target="_blank"
                style="font-size:10px;padding:3px 8px;border:1px solid #DC2626;color:#DC2626;background:#FEF2F2;border-radius:5px;text-decoration:none;display:flex;align-items:center;gap:4px;font-weight:600;">
                <i class="fas fa-file-pdf"></i> PDF Report
              </a>
              <button onclick="showNHSGuide('<?= e(addslashes($r['title'])) ?>')"
                style="font-size:10px;padding:3px 8px;border:1px solid #005EB8;color:#005EB8;background:#EBF3FB;border-radius:5px;cursor:pointer;display:flex;align-items:center;gap:4px;font-weight:600;">
                <img src="https://www.nhs.uk/nhschoicesContent/imagecontent/icons/apple-touch-icon.png" style="width:12px;height:12px;border-radius:2px;"> NHS Guide
              </button>
            </div>
          </div>
          <p style="font-size:13px;color:var(--hs-text);line-height:1.6;margin-bottom:10px;"><?= e($r['result']) ?></p>
          <?php if ($r['first_name']): ?>
          <div style="font-size:12px;color:var(--hs-muted);border-top:1px solid var(--hs-border);padding-top:8px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;">
            <span>
              <i class="fas fa-user-md"></i> Dr. <?= e($r['first_name'].' '.$r['last_name']) ?>
              &nbsp;·&nbsp;
              <i class="fas fa-calendar"></i> <?= $r['test_date'] ? 'Added '.formatDate($r['test_date']) : '' ?>
            </span>
            <?php if (!empty($r['file_path'])): ?>
            <a href="../<?= e($r['file_path']) ?>" target="_blank" download
               style="font-size:11px;background:#16A34A;color:#fff;padding:3px 10px;border-radius:5px;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:4px;">
              <i class="fas fa-download"></i> Download Attached File
            </a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$records): ?>
      <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--hs-muted);">
        <i class="fas fa-flask" style="font-size:40px;opacity:.3;"></i><p style="margin-top:12px;">No medical records found.</p>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Appointments -->
    <?php if ($activeTab === 'appointments'): ?>
    <div class="hs-card">
      <div class="hs-card-header"><span class="card-title"><i class="fas fa-calendar-check"></i> All Appointments</span></div>
      <div class="hs-card-body p-0">
        <table class="hs-table">
          <thead><tr><th>Doctor</th><th>Specialization</th><th>Date & Time</th><th>Status</th><th>Notes</th></tr></thead>
          <tbody>
            <?php foreach ($appointments as $a): ?>
            <tr>
              <td><strong>Dr. <?= e($a['first_name'].' '.$a['last_name']) ?></strong></td>
              <td><?= e($a['specialization'] ?? '—') ?></td>
              <td><?= formatDate($a['appointment_date']) ?> <?= date('H:i', strtotime($a['appointment_time'])) ?></td>
              <td><?= getStatusBadge($a['status']) ?></td>
              <td style="font-size:12px;color:var(--hs-muted);"><?= e($a['notes'] ?: '—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Allergies -->
    <?php if ($activeTab === 'allergies'): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;">
      <?php foreach ($allergies as $a):
        $sev = $a['severity'];
        $sc = ['mild'=>'success','moderate'=>'warning','severe'=>'danger'][$sev] ?? 'secondary';
        $icons2 = ['medication'=>'💊','food'=>'🍎','environmental'=>'🌿','other'=>'⚠️'];
      ?>
      <div class="hs-card">
        <div class="hs-card-body">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <div style="display:flex;align-items:center;gap:10px;">
              <span style="font-size:24px;"><?= $icons2[$a['allergy_type']] ?? '⚠️' ?></span>
              <div>
                <div style="font-weight:700;font-size:15px;color:var(--hs-navy);"><?= e($a['allergen']) ?></div>
                <div style="font-size:12px;color:var(--hs-muted);text-transform:capitalize;"><?= e($a['allergy_type']) ?></div>
              </div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
              <span class="badge bg-<?= $sc ?>"><?= ucfirst($sev) ?></span>
              <button onclick="showNHSGuide('<?= e(addslashes($a['allergen'])) ?> allergy')"
                style="font-size:10px;padding:3px 8px;border:1px solid #005EB8;color:#005EB8;background:#EBF3FB;border-radius:5px;cursor:pointer;display:flex;align-items:center;gap:4px;font-weight:600;">
                <img src="https://www.nhs.uk/nhschoicesContent/imagecontent/icons/apple-touch-icon.png" style="width:12px;height:12px;border-radius:2px;"> NHS Guide
              </button>
            </div>
          </div>
          <p style="font-size:13px;color:var(--hs-muted);margin:8px 0 0;"><?= e($a['symptoms']) ?></p>
          <?php if (!$a['is_active']): ?>
          <span style="font-size:11px;color:var(--hs-muted);margin-top:6px;display:block;">Inactive</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Vaccinations -->
    <?php if ($activeTab === 'vaccinations'): ?>
    <div class="hs-card">
      <div class="hs-card-header"><span class="card-title"><i class="fas fa-syringe"></i> Immunisation History</span></div>
      <div class="hs-card-body p-0">
        <table class="hs-table">
          <thead><tr><th>Vaccine</th><th>Dose</th><th>Date</th><th>Next Due</th><th>By</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($vaccinations as $v): ?>
            <tr>
              <td><strong><?= e($v['vaccine_name']) ?></strong></td>
              <td>Dose <?= $v['dose_number'] ?></td>
              <td><?= $v['administered_date'] ? formatDate($v['administered_date']) : '—' ?></td>
              <td><?= $v['next_due_date'] ? formatDate($v['next_due_date']) : '—' ?></td>
              <td><?= e($v['administered_by'] ?? '—') ?></td>
              <td><?= $v['is_completed'] ? '<span class="badge bg-success">Completed</span>' : '<span class="badge bg-warning text-dark">Due</span>' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Medications -->
    <?php if ($activeTab === 'medications'): ?>
    <div class="hs-card">
      <div class="hs-card-header"><span class="card-title"><i class="fas fa-pills"></i> Prescriptions & Medications</span></div>
      <div class="hs-card-body p-0">
        <table class="hs-table">
          <thead><tr><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Prescribed By</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($prescriptions as $p): ?>
            <tr>
              <td><strong><?= e($p['medication_name']) ?></strong><div style="font-size:12px;color:var(--hs-muted);"><?= e($p['instructions'] ?: '') ?></div></td>
              <td><?= e($p['dosage']) ?></td>
              <td><?= e($p['frequency']) ?></td>
              <td><?= e($p['duration'] ?? '—') ?></td>
              <td><?= $p['first_name'] ? 'Dr. '.e($p['first_name'].' '.$p['last_name']) : '—' ?></td>
              <td><?= $p['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Ended</span>' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Family History — Full Interactive Tree -->
    <?php if ($activeTab === 'family'): ?>
    <?php
    function fRiskColor(array $conds): string {
        $t = strtolower(implode(' ', array_column($conds,'condition_name')));
        foreach (['heart','cancer','stroke','hypertension','cholesterol','diabetes','brca','coronary','atrial','angina'] as $k)
            if (str_contains($t,$k)) return '#DC2626';
        foreach (['thyroid','calcium','arthritis','anxiety','obesity','asthma','eczema','osteoporosis','fatty liver','degeneration'] as $k)
            if (str_contains($t,$k)) return '#D97706';
        return '#16A34A';
    }
    function fCondPills(array $conds, int $max=2): string {
        if (!$conds) return '<span class="cpill cpill-grey">No data</span>';
        $html=''; $map=['heart'=>'red','cancer'=>'red','stroke'=>'red','hypertension'=>'red','cholesterol'=>'red','brca'=>'red','diabetes'=>'red','coronary'=>'red','atrial'=>'red','thyroid'=>'orange','anxiety'=>'orange','obesity'=>'orange','asthma'=>'orange','arthritis'=>'orange','calcium'=>'orange','eczema'=>'orange','fatty'=>'orange'];
        foreach(array_slice($conds,0,$max) as $c){
            $cls='green'; $t=strtolower($c['condition_name']);
            foreach($map as $k=>$v){if(str_contains($t,$k)){$cls=$v;break;}}
            $sh=mb_strlen($c['condition_name'])>21?mb_substr($c['condition_name'],0,19).'…':$c['condition_name'];
            $html.="<span class='cpill cpill-{$cls}'>".htmlspecialchars($sh).'</span>';
        }
        $ex=count($conds)-$max; if($ex>0) $html.="<span class='cpill cpill-grey'>+{$ex} more</span>";
        return $html;
    }
    function fGrpByPerson(array $recs): array {
        $out=[];
        foreach($recs as $r) $out[$r['relation_name']][]=$r;
        return $out;
    }
    function fCard(string $av, string $name, string $rel, array $conds, bool $ph=false, bool $you=false, bool $dec=false, ?int $decYr=null): string {
        $cls='fcard'.($ph?' fcard-ph':'').($you?' fcard-you':'');
        $rc=!$ph&&!$you?fRiskColor($conds):'transparent';
        $dataAttr='';
        if(!$ph&&!$you){
            $obj=['name'=>$name,'rel'=>$rel,'av'=>$av,'conds'=>$conds,'dec'=>$dec,'dec_yr'=>$decYr];
            $dataAttr='onclick="showP(event,'.htmlspecialchars(json_encode($obj),ENT_QUOTES).')"';
        }
        $decBadge=$dec?"<span class='fcard-dec'>Deceased".($decYr?" {$decYr}":'').'</span>':'';
        $pills=$you?"<span style='font-size:11px;color:rgba(255,255,255,.7);font-weight:600;'>You</span>":fCondPills($conds);
        return "<div class='{$cls}' {$dataAttr}>{$decBadge}<span class='fcard-av'>{$av}</span><div class='fcard-name'>".htmlspecialchars($name)."</div><div class='fcard-rel'>".htmlspecialchars($rel)."</div><div class='fcard-pills'>{$pills}</div><div class='fcard-riskbar' style='background:{$rc};'></div></div>";
    }
    function fPH(string $av, string $rel): string { return fCard($av,'Unknown',$rel,[],true); }

    // Build records per type
    $pgfR  = $famByRel['grandfather_paternal'] ?? [];
    $pgmR  = $famByRel['grandmother_paternal'] ?? [];
    $mgfR  = $famByRel['grandfather_maternal'] ?? [];
    $mgmR  = $famByRel['grandmother_maternal'] ?? [];
    $fatR  = $famByRel['father']  ?? [];
    $motR  = $famByRel['mother']  ?? [];
    $brtR  = $famByRel['brother'] ?? [];
    $sisR  = $famByRel['sister']  ?? [];
    $uncPat  = fGrpByPerson($famByRel['uncle_paternal']  ?? []);
    $auntPat = fGrpByPerson($famByRel['aunt_paternal']   ?? []);
    $uncMat  = fGrpByPerson($famByRel['uncle_maternal']  ?? []);
    $auntMat = fGrpByPerson($famByRel['aunt_maternal']   ?? []);
    $couPat  = fGrpByPerson($famByRel['cousin_paternal'] ?? []);
    $couMat  = fGrpByPerson($famByRel['cousin_maternal'] ?? []);

    $totalMembers = count(array_unique(array_column($familyHx,'relation_name')));
    $totalConds   = count($familyHx);
    ?>

    <!-- ─── STYLES ────────────────────────────────────────────── -->
    <style>
    /* ── Card ── */
    .fcard{width:136px;flex-shrink:0;background:#fff;border:2px solid var(--hs-border);border-radius:14px;padding:11px 9px 14px;text-align:center;cursor:pointer;transition:all .22s;position:relative;z-index:2;box-shadow:0 2px 8px rgba(10,31,68,.07);}
    .fcard:hover{transform:translateY(-5px);box-shadow:0 10px 28px rgba(10,31,68,.16);}
    .fcard.fcard-ph{opacity:.4;cursor:default;border-style:dashed;}
    .fcard.fcard-ph:hover{transform:none;box-shadow:none;}
    .fcard.fcard-you{background:linear-gradient(140deg,#071330,#1565C0);border-color:#1565C0;cursor:default;box-shadow:0 8px 28px rgba(21,101,192,.35);}
    .fcard.fcard-you:hover{transform:none;}
    .fcard-av{font-size:24px;display:block;margin-bottom:5px;}
    .fcard-name{font-size:11px;font-weight:800;color:var(--hs-navy);line-height:1.3;margin-bottom:2px;}
    .fcard.fcard-you .fcard-name,.fcard.fcard-you .fcard-rel{color:#fff;}
    .fcard-rel{font-size:9px;font-weight:700;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;}
    .fcard.fcard-you .fcard-rel{color:rgba(255,255,255,.65);}
    .fcard-pills{display:flex;flex-direction:column;gap:3px;}
    .cpill{padding:2px 6px;border-radius:20px;font-size:9px;font-weight:700;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .cpill-red{background:#FEE2E2;color:#991B1B;}
    .cpill-orange{background:#FEF3C7;color:#92400E;}
    .cpill-green{background:#DCFCE7;color:#166534;}
    .cpill-grey{background:#F1F5F9;color:#5E7A99;font-style:italic;}
    .fcard-riskbar{position:absolute;bottom:0;left:0;right:0;height:3px;border-radius:0 0 12px 12px;}
    .fcard-dec{position:absolute;top:-9px;left:50%;transform:translateX(-50%);background:#DC2626;color:#fff;font-size:8px;font-weight:700;padding:1px 6px;border-radius:10px;white-space:nowrap;}

    /* ── Tree layout ── */
    .ftree-v2{display:flex;flex-direction:column;align-items:center;gap:0;width:100%;}

    /* Row containers */
    .ft-row{display:flex;align-items:flex-start;justify-content:center;gap:0;width:100%;}

    /* Horizontal couple join */
    .c-h{height:2px;background:#D0E4FF;flex-shrink:0;align-self:center;}
    /* Vertical drop */
    .c-v{width:2px;background:#D0E4FF;flex-shrink:0;}
    /* Center column for vertical lines */
    .c-center-v{display:flex;flex-direction:column;align-items:center;}

    /* Two-column half wrappers */
    .ft-half{display:flex;flex-direction:column;align-items:center;flex:1;}
    .ft-half.mat-side{align-items:flex-end;padding-right:40px;}
    .ft-half.pat-side{align-items:flex-start;padding-left:40px;}

    /* Section label chips */
    .sec-label{font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:1px;padding:3px 12px;border-radius:20px;margin-bottom:8px;text-align:center;}

    /* Divider line between sections */
    .ft-divider{display:flex;align-items:center;gap:10px;width:100%;margin:4px 0;}
    .ft-divider-line{flex:1;height:1.5px;background:linear-gradient(90deg,transparent,#D0E4FF,transparent);}
    .ft-divider-label{font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--hs-muted);padding:3px 12px;background:#F8FAFF;border-radius:20px;border:1px solid var(--hs-border);white-space:nowrap;}

    /* Full-width ft-page */
    .ft-page{padding:0 0 40px;}
    .ft-hdr{display:flex;align-items:center;gap:14px;padding:14px 18px;background:#fff;border-radius:12px;border:1px solid var(--hs-border);margin-bottom:20px;}
    .ft-scroll{overflow-x:auto;padding-bottom:12px;}
    .ft-inner{display:flex;flex-direction:column;align-items:center;min-width:840px;padding:10px 30px 30px;}

    /* Legend */
    .ft-legend{display:flex;gap:12px;flex-wrap:wrap;padding:12px 16px;background:#fff;border-radius:10px;border:1px solid var(--hs-border);margin-top:18px;font-size:11.5px;align-items:center;}
    .ftleg-i{display:flex;align-items:center;gap:6px;}
    .ftleg-d{width:10px;height:10px;border-radius:50%;flex-shrink:0;}

    /* Uncle/aunt cluster */
    .sibling-cluster{display:flex;flex-direction:column;align-items:center;gap:8px;}
    .sibling-cluster-row{display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;justify-content:center;}
    </style>

    <div class="ft-page">

      <!-- Header -->
      <div class="ft-hdr">
        <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#0A1F44,#1565C0);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">🧬</div>
        <div style="flex:1;">
          <div style="font-size:15px;font-weight:800;color:var(--hs-navy);">Family Medical Tree</div>
          <div style="font-size:12px;color:var(--hs-muted);"><?= $totalMembers ?> members &middot; <?= $totalConds ?> conditions &middot; Click any card for full details &amp; genetic risk</div>
        </div>
        <button class="btn-hs btn-primary-hs btn-sm-hs" onclick="document.getElementById('addMemberModal').style.display='flex'">
          <i class="fas fa-plus"></i> Add Member
        </button>
      </div>

      <div class="ft-scroll">
       <div class="ft-inner">
        <div class="ftree-v2">

          <?php
          // Helper: short first name
          function fShortName(string $n): string { return explode(' (',$n)[0]; }
          ?>

          <!-- ════════════════════════════════════
               ROW 1 — YOU (top centre) + SIBLINGS
          ════════════════════════════════════ -->
          <div class="ft-row" style="gap:0;justify-content:center;align-items:flex-start;">

            <!-- Siblings LEFT (brothers) -->
            <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;padding-top:30px;">
              <?php foreach (fGrpByPerson($brtR) as $nm=>$cs): ?>
              <div style="display:flex;align-items:center;">
                <?= fCard('👦',fShortName($nm),'Brother',$cs) ?>
                <div class="c-h" style="width:16px;"></div>
              </div>
              <?php endforeach; ?>
            </div>

            <!-- YOU -->
            <div style="display:flex;flex-direction:column;align-items:center;">
              <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--hs-blue);margin-bottom:8px;padding:3px 12px;background:#DBEAFE;border-radius:20px;">You</div>
              <?= fCard('🧑',$userFull['first_name'].' '.$userFull['last_name'],'You',[],false,true) ?>
            </div>

            <!-- Siblings RIGHT (sisters) -->
            <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-start;padding-top:30px;">
              <?php foreach (fGrpByPerson($sisR) as $nm=>$cs): ?>
              <div style="display:flex;align-items:center;">
                <div class="c-h" style="width:16px;"></div>
                <?= fCard('👧',fShortName($nm),'Sister',$cs) ?>
              </div>
              <?php endforeach; ?>
            </div>

          </div><!-- /emma row -->

          <!-- Vertical drop to parents -->
          <div class="c-center-v"><div class="c-v" style="height:36px;"></div></div>

          <!-- ════════════════════════════════════
               ROW 2 — PARENTS
          ════════════════════════════════════ -->
          <div class="ft-row" style="gap:0;justify-content:center;align-items:flex-start;">

            <!-- Mother side label -->
            <div style="display:flex;flex-direction:column;align-items:center;">
              <div class="sec-label" style="color:#1565C0;background:#DBEAFE;">Maternal Side</div>
              <?= $motR ? fCard('👩',$motR[0]['relation_name'],'Mother',$motR,false,false,!empty($motR[0]['year_deceased']),(int)($motR[0]['year_deceased']??0)?:null) : fPH('👩','Mother') ?>
            </div>

            <!-- Horizontal line between parents -->
            <div class="c-h" style="width:70px;margin-top:66px;"></div>

            <!-- Father side label -->
            <div style="display:flex;flex-direction:column;align-items:center;">
              <div class="sec-label" style="color:#7C3AED;background:#EDE9FE;">Paternal Side</div>
              <?= $fatR ? fCard('👨',$fatR[0]['relation_name'],'Father',$fatR,false,false,!empty($fatR[0]['year_deceased']),(int)($fatR[0]['year_deceased']??0)?:null) : fPH('👨','Father') ?>
            </div>

          </div><!-- /parents row -->

          <!-- Two drops to grandparents -->
          <div style="display:flex;width:100%;justify-content:space-around;padding:0 120px;">
            <div class="c-v" style="height:36px;"></div>
            <div class="c-v" style="height:36px;"></div>
          </div>

          <!-- ════════════════════════════════════
               ROW 3 — GRANDPARENTS
          ════════════════════════════════════ -->
          <div class="ft-row" style="gap:60px;justify-content:center;align-items:flex-start;">

            <!-- MATERNAL GRANDPARENTS -->
            <div style="display:flex;flex-direction:column;align-items:center;gap:0;">
              <div class="sec-label" style="color:#1565C0;background:#EFF6FF;">Mother's Parents</div>
              <div style="display:flex;align-items:center;">
                <?= $mgfR ? fCard('👴',$mgfR[0]['relation_name'],'Mat. Grandfather',$mgfR,false,false,!empty($mgfR[0]['year_deceased']),(int)($mgfR[0]['year_deceased']??0)?:null) : fPH('👴','Mat. Grandfather') ?>
                <div class="c-h" style="width:32px;"></div>
                <?= $mgmR ? fCard('👵',$mgmR[0]['relation_name'],'Mat. Grandmother',$mgmR,false,false,!empty($mgmR[0]['year_deceased']),(int)($mgmR[0]['year_deceased']??0)?:null) : fPH('👵','Mat. Grandmother') ?>
              </div>
            </div>

            <!-- PATERNAL GRANDPARENTS -->
            <div style="display:flex;flex-direction:column;align-items:center;gap:0;">
              <div class="sec-label" style="color:#7C3AED;background:#F5F3FF;">Father's Parents</div>
              <div style="display:flex;align-items:center;">
                <?= $pgfR ? fCard('👴',$pgfR[0]['relation_name'],'Pat. Grandfather',$pgfR,false,false,!empty($pgfR[0]['year_deceased']),(int)($pgfR[0]['year_deceased']??0)?:null) : fPH('👴','Pat. Grandfather') ?>
                <div class="c-h" style="width:32px;"></div>
                <?= $pgmR ? fCard('👵',$pgmR[0]['relation_name'],'Pat. Grandmother',$pgmR,false,false,!empty($pgmR[0]['year_deceased']),(int)($pgmR[0]['year_deceased']??0)?:null) : fPH('👵','Pat. Grandmother') ?>
              </div>
            </div>

          </div><!-- /grandparents row -->

          <!-- Two drops to aunts/uncles -->
          <div style="display:flex;width:100%;justify-content:space-around;padding:0 120px;">
            <div class="c-v" style="height:36px;"></div>
            <div class="c-v" style="height:36px;"></div>
          </div>

          <!-- ════════════════════════════════════
               ROW 4 — AUNTS & UNCLES
               (siblings of parents = children of grandparents)
          ════════════════════════════════════ -->
          <div class="ft-row" style="gap:60px;justify-content:center;align-items:flex-start;">

            <!-- MATERNAL AUNTS & UNCLES -->
            <div style="display:flex;flex-direction:column;align-items:center;gap:0;">
              <?php if ($uncMat || $auntMat): ?>
              <div class="sec-label" style="color:#1565C0;background:#EFF6FF;">Mother's Siblings</div>
              <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
                <?php foreach ($uncMat as $nm=>$cs): ?>
                <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                  <?= fCard('👨‍💼',fShortName($nm),"Mother's Brother",$cs) ?>
                </div>
                <?php endforeach; ?>
                <?php foreach ($auntMat as $nm=>$cs): ?>
                <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                  <?= fCard('👩‍💼',fShortName($nm),"Mother's Sister",$cs) ?>
                </div>
                <?php endforeach; ?>
              </div>
              <?php else: ?>
              <div style="width:136px;height:166px;border:2px dashed var(--hs-border);border-radius:14px;display:flex;align-items:center;justify-content:center;opacity:.35;">
                <div style="text-align:center;font-size:11px;color:var(--hs-muted);">No maternal<br>aunts/uncles<br>recorded</div>
              </div>
              <?php endif; ?>
            </div>

            <!-- PATERNAL AUNTS & UNCLES -->
            <div style="display:flex;flex-direction:column;align-items:center;gap:0;">
              <?php if ($uncPat || $auntPat): ?>
              <div class="sec-label" style="color:#7C3AED;background:#F5F3FF;">Father's Siblings</div>
              <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
                <?php foreach ($uncPat as $nm=>$cs): ?>
                <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                  <?= fCard('👨‍💼',fShortName($nm),"Father's Brother",$cs) ?>
                </div>
                <?php endforeach; ?>
                <?php foreach ($auntPat as $nm=>$cs): ?>
                <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                  <?= fCard('👩‍💼',fShortName($nm),"Father's Sister",$cs) ?>
                </div>
                <?php endforeach; ?>
              </div>
              <?php else: ?>
              <div style="width:136px;height:166px;border:2px dashed var(--hs-border);border-radius:14px;display:flex;align-items:center;justify-content:center;opacity:.35;">
                <div style="text-align:center;font-size:11px;color:var(--hs-muted);">No paternal<br>aunts/uncles<br>recorded</div>
              </div>
              <?php endif; ?>
            </div>

          </div><!-- /aunts-uncles row -->

          <!-- Two drops to cousins -->
          <?php if ($couMat || $couPat): ?>
          <div style="display:flex;width:100%;justify-content:space-around;padding:0 120px;">
            <div class="c-v" style="height:36px;"></div>
            <div class="c-v" style="height:36px;"></div>
          </div>

          <!-- ════════════════════════════════════
               ROW 5 — COUSINS
          ════════════════════════════════════ -->
          <div class="ft-row" style="gap:60px;justify-content:center;align-items:flex-start;">

            <!-- MATERNAL COUSINS -->
            <div style="display:flex;flex-direction:column;align-items:center;gap:0;">
              <?php if ($couMat): ?>
              <div class="sec-label" style="color:#1565C0;background:#EFF6FF;">Mother's Side Cousins</div>
              <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
                <?php foreach ($couMat as $nm=>$cs): ?>
                <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                  <?= fCard('🧒',fShortName($nm),'Maternal Cousin',$cs) ?>
                  <div style="font-size:8.5px;color:var(--hs-muted);text-align:center;max-width:136px;line-height:1.3;"><?= htmlspecialchars($nm) ?></div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php else: ?>
              <div style="width:136px;height:140px;border:2px dashed var(--hs-border);border-radius:14px;display:flex;align-items:center;justify-content:center;opacity:.3;">
                <div style="text-align:center;font-size:11px;color:var(--hs-muted);">No maternal<br>cousins recorded</div>
              </div>
              <?php endif; ?>
            </div>

            <!-- PATERNAL COUSINS -->
            <div style="display:flex;flex-direction:column;align-items:center;gap:0;">
              <?php if ($couPat): ?>
              <div class="sec-label" style="color:#7C3AED;background:#F5F3FF;">Father's Side Cousins</div>
              <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
                <?php foreach ($couPat as $nm=>$cs): ?>
                <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                  <?= fCard('🧒',fShortName($nm),'Paternal Cousin',$cs) ?>
                  <div style="font-size:8.5px;color:var(--hs-muted);text-align:center;max-width:136px;line-height:1.3;"><?= htmlspecialchars($nm) ?></div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php else: ?>
              <div style="width:136px;height:140px;border:2px dashed var(--hs-border);border-radius:14px;display:flex;align-items:center;justify-content:center;opacity:.3;">
                <div style="text-align:center;font-size:11px;color:var(--hs-muted);">No paternal<br>cousins recorded</div>
              </div>
              <?php endif; ?>
            </div>

          </div><!-- /cousins row -->
          <?php endif; ?>

        </div><!-- /ftree-v2 -->
       </div><!-- /ft-inner -->
      </div><!-- /ft-scroll -->

      <!-- Legend -->
      <div class="ft-legend">
        <strong style="font-size:10.5px;color:var(--hs-muted);text-transform:uppercase;letter-spacing:.8px;">Risk:</strong>
        <div class="ftleg-i"><span class="ftleg-d" style="background:#DC2626;"></span> High (Heart, Cancer, Stroke, BP, Diabetes)</div>
        <div class="ftleg-i"><span class="ftleg-d" style="background:#D97706;"></span> Moderate (Thyroid, Anxiety, Obesity, Asthma)</div>
        <div class="ftleg-i"><span class="ftleg-d" style="background:#16A34A;"></span> Low Risk</div>
        <div class="ftleg-i"><span class="ftleg-d" style="border:1.5px dashed #9BAEC8;background:none;"></span> No data</div>
        <div style="margin-left:auto;font-size:11px;color:var(--hs-muted);"><i class="fas fa-hand-pointer"></i> Click any card for full details</div>
      </div>

    </div><!-- /ft-page -->

    <!-- ═══ PERSON DETAIL MODAL ═══ -->
    <div id="personModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:3000;align-items:center;justify-content:center;padding:20px;overflow-y:auto;">
      <div style="background:#fff;border-radius:20px;width:100%;max-width:540px;box-shadow:0 24px 80px rgba(10,31,68,.3);overflow:hidden;">
        <div id="pmTop" style="padding:20px 24px;display:flex;align-items:center;gap:14px;">
          <span id="pmAv" style="font-size:36px;"></span>
          <div style="flex:1;"><div id="pmName" style="font-size:18px;font-weight:800;"></div><div id="pmRel" style="font-size:12px;opacity:.75;margin-top:2px;"></div></div>
          <button onclick="document.getElementById('personModal').style.display='none'" style="background:rgba(255,255,255,.2);border:none;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:18px;color:inherit;">&times;</button>
        </div>
        <div id="pmBody" style="padding:0 24px 24px;max-height:70vh;overflow-y:auto;"></div>
      </div>
    </div>

    <!-- ═══ ADD MEMBER MODAL ═══ -->
    <div id="addMemberModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3000;align-items:center;justify-content:center;padding:20px;">
      <div style="background:#fff;border-radius:16px;width:100%;max-width:460px;box-shadow:var(--shadow-lg);overflow:hidden;">
        <div style="background:var(--hs-navy);color:#fff;padding:18px 24px;display:flex;justify-content:space-between;align-items:center;">
          <h5 style="margin:0;font-size:16px;font-weight:700;"><i class="fas fa-user-plus"></i> Add Family Member</h5>
          <button onclick="document.getElementById('addMemberModal').style.display='none'" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;">&times;</button>
        </div>
        <form method="POST" action="../api/family-history.php" style="padding:22px;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
            <div>
              <label class="form-label">Relation *</label>
              <select name="relation" class="form-select" required>
                <option value="">Select...</option>
                <option value="father">Father</option><option value="mother">Mother</option>
                <option value="brother">Brother</option><option value="sister">Sister</option>
                <option value="grandfather_paternal">Grandfather (Father's side)</option>
                <option value="grandmother_paternal">Grandmother (Father's side)</option>
                <option value="grandfather_maternal">Grandfather (Mother's side)</option>
                <option value="grandmother_maternal">Grandmother (Mother's side)</option>
                <option value="uncle_paternal">Uncle (Father's Brother)</option>
                <option value="aunt_paternal">Aunt (Father's Sister)</option>
                <option value="uncle_maternal">Uncle (Mother's Brother)</option>
                <option value="aunt_maternal">Aunt (Mother's Sister)</option>
                <option value="cousin_paternal">Cousin (Father's side)</option>
                <option value="cousin_maternal">Cousin (Mother's side)</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div><label class="form-label">Name</label><input type="text" name="relation_name" class="form-control" placeholder="Full name"></div>
            <div style="grid-column:1/-1"><label class="form-label">Condition *</label><input type="text" name="condition_name" class="form-control" required placeholder="e.g. Type 2 Diabetes"></div>
            <div><label class="form-label">Year Diagnosed</label><input type="number" name="year_diagnosed" class="form-control" placeholder="e.g. 2010" min="1900" max="2030"></div>
            <div><label class="form-label">Year Deceased</label><input type="number" name="year_deceased" class="form-control" placeholder="Leave blank if alive" min="1900" max="2030"></div>
            <div style="grid-column:1/-1"><label class="form-label">Notes (optional)</label><textarea name="notes" class="form-control" rows="2" placeholder="Additional details..."></textarea></div>
          </div>
          <button type="submit" class="btn-hs btn-primary-hs" style="width:100%;justify-content:center;"><i class="fas fa-save"></i> Save</button>
        </form>
      </div>
    </div>

    <script>
    const HIGH_K=['heart','cancer','stroke','hypertension','cholesterol','diabetes','brca','coronary','atrial','angina','fibrillation'];
    const MID_K =['thyroid','calcium','arthritis','anxiety','obesity','asthma','eczema','osteoporosis','fatty liver','degeneration'];
    const RISK_NOTES={
      heart:'Family history of heart disease doubles your cardiovascular risk. Discuss heart health screening with your GP.',
      coronary:'Coronary artery disease has strong hereditary components. Regular BP and cholesterol checks are essential.',
      stroke:'Family history of stroke increases your risk by up to 50%. Monitor BP, cholesterol and AF symptoms.',
      hypertension:'Hypertension in a first-degree relative raises your lifetime risk by 25–50%. Regular monitoring is recommended.',
      cholesterol:'Familial hypercholesterolaemia is inherited. A fasting lipid panel is strongly recommended for you.',
      diabetes:'Type 2 diabetes risk increases up to 40% with an affected parent or sibling. Annual HbA1c screening advised.',
      cancer:'Some cancers have genetic components. Discuss family history and screening schedules with your GP.',
      brca:'BRCA gene variants significantly elevate breast/ovarian cancer risk. Genetic counselling is urgently recommended.',
      thyroid:'Thyroid disorders and thyroid cancer can run in families. Annual TSH monitoring is prudent.',
      anxiety:'Anxiety disorders have hereditary elements. CBT and mindfulness can be effective preventative strategies.',
      obesity:'Obesity risk is partly hereditary. Combined with your diabetes family history, weight management is important.',
      atrial:'Atrial fibrillation has genetic components and raises stroke risk. Report palpitations to your GP.',
      osteoporosis:'Osteoporosis is partly hereditary. Ensure adequate calcium/vitamin D and consider a DEXA scan.',
    };
    function condColor(n){const t=n.toLowerCase();if(HIGH_K.some(k=>t.includes(k)))return'#DC2626';if(MID_K.some(k=>t.includes(k)))return'#D97706';return'#16A34A';}
    function condBg(c){return{'#DC2626':'#FEE2E2','#D97706':'#FEF3C7','#16A34A':'#DCFCE7'}[c]||'#F1F5F9';}

    function showP(e,data){
      e.stopPropagation();
      const p=typeof data==='string'?JSON.parse(data):data;
      const conds=p.conds||[];
      let rc='#1565C0';
      const allT=conds.map(c=>c.condition_name.toLowerCase()).join(' ');
      if(HIGH_K.some(k=>allT.includes(k)))rc='#DC2626';
      else if(MID_K.some(k=>allT.includes(k)))rc='#D97706';
      else if(conds.length)rc='#16A34A';
      const top=document.getElementById('pmTop');
      top.style.background=rc; top.style.color='#fff';
      document.getElementById('pmAv').textContent=p.av;
      document.getElementById('pmName').textContent=p.name;
      document.getElementById('pmRel').textContent=p.rel+(p.dec?' · Deceased '+(p.dec_yr||''):'');
      let body='';
      if(!conds.length){
        body='<p style="padding:20px;text-align:center;color:#5E7A99;">No medical conditions recorded for this family member.</p>';
      } else {
        conds.forEach(c=>{
          const col=condColor(c.condition_name),bg=condBg(col);
          let note='';
          for(const[k,v]of Object.entries(RISK_NOTES)){if(c.condition_name.toLowerCase().includes(k)){note=v;break;}}
          body+=`<div style="border-left:4px solid ${col};border-radius:10px;padding:14px 16px;margin-bottom:12px;background:${bg}44;border:1px solid ${col}22;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:6px;">
              <div style="font-weight:800;font-size:14px;color:#0A1F44;">${c.condition_name}</div>
              ${c.year_diagnosed?`<span style="background:${bg};color:${col};padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;">Diagnosed ${c.year_diagnosed}</span>`:''}
            </div>
            ${c.notes?`<p style="font-size:13px;color:#374151;margin:0 0 8px;line-height:1.6;">${c.notes}</p>`:''}
            ${note?`<div style="display:flex;gap:8px;background:rgba(255,255,255,.8);border-radius:7px;padding:9px 12px;margin-top:8px;"><i class="fas fa-dna" style="color:${col};margin-top:2px;flex-shrink:0;font-size:13px;"></i><div style="font-size:12.5px;color:#374151;line-height:1.5;"><strong>Your genetic risk:</strong> ${note}</div></div>`:''}
          </div>`;
        });
        body+=`<div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;padding:12px 14px;display:flex;gap:10px;align-items:flex-start;">
          <i class="fas fa-lightbulb" style="color:#1565C0;margin-top:2px;flex-shrink:0;"></i>
          <div style="font-size:12.5px;color:#1E40AF;line-height:1.6;"><strong>NHS Recommendation:</strong> Share this family history with your GP. Genetic risk factors can be managed through early screening, lifestyle changes, and preventive care.
          <br><a href="appointments.php" style="color:#1565C0;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-top:6px;"><i class="fas fa-calendar-plus"></i> Book appointment with your doctor</a></div>
        </div>`;
      }
      document.getElementById('pmBody').innerHTML=body;
      document.getElementById('personModal').style.display='flex';
    }
    ['personModal','addMemberModal'].forEach(id=>{
      document.getElementById(id).addEventListener('click',function(e){if(e.target===this)this.style.display='none';});
    });
    </script>

    <?php endif; ?>


  </div>
</div>
<script src="../assets/js/main.js"></script>

<!-- ═══ NHS GUIDE MODAL ═══ -->
<div id="nhsModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:4000;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:20px;width:100%;max-width:560px;max-height:90vh;overflow:hidden;box-shadow:0 24px 80px rgba(10,31,68,.3);display:flex;flex-direction:column;">
    <div style="background:#005EB8;color:#fff;padding:18px 24px;display:flex;align-items:center;gap:12px;flex-shrink:0;">
      <div style="background:#fff;border-radius:6px;padding:4px 8px;"><img src="https://www.nhs.uk/nhschoicesContent/imagecontent/icons/apple-touch-icon.png" style="height:22px;vertical-align:middle;"></div>
      <div style="flex:1;">
        <div id="nhsModalTitle" style="font-size:17px;font-weight:800;"></div>
        <div style="font-size:11px;opacity:.8;">NHS Official Health Information</div>
      </div>
      <button onclick="closeNHSModal()" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:20px;">&times;</button>
    </div>
    <div id="nhsModalBody" style="overflow-y:auto;padding:20px 24px;flex:1;"></div>
    <div id="nhsModalFooter" style="padding:12px 24px;border-top:1px solid #e5e7eb;background:#f9fafb;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;">
      <span style="font-size:11px;color:#6b7280;">Source: NHS.uk — Official UK Health Information</span>
      <a id="nhsModalLink" href="#" target="_blank" style="font-size:12px;font-weight:700;color:#005EB8;text-decoration:none;">Read full NHS guide →</a>
    </div>
  </div>
</div>

<script>
function showNHSGuide(condition) {
  const modal = document.getElementById('nhsModal');
  const body  = document.getElementById('nhsModalBody');
  const title = document.getElementById('nhsModalTitle');
  const link  = document.getElementById('nhsModalLink');

  title.textContent = condition;
  link.href = '#';
  body.innerHTML = `<div style="text-align:center;padding:40px 0;">
    <div style="width:40px;height:40px;border:3px solid #005EB8;border-top-color:transparent;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 16px;"></div>
    <div style="color:#6b7280;font-size:13px;">Loading NHS information...</div>
  </div>`;
  modal.style.display = 'flex';

  fetch(`../api/nhs-condition.php?condition=${encodeURIComponent(condition)}`)
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        body.innerHTML = `
          <div style="text-align:center;padding:30px 0;">
            <div style="font-size:40px;margin-bottom:12px;">🔍</div>
            <p style="color:#6b7280;font-size:14px;">NHS information for "<strong>${condition}</strong>" wasn't found automatically.</p>
            <a href="https://www.nhs.uk/search/results/?q=${encodeURIComponent(condition)}" target="_blank"
               style="display:inline-flex;align-items:center;gap:8px;margin-top:16px;background:#005EB8;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:700;font-size:13px;">
              🔗 Search on NHS.uk
            </a>
          </div>`;
        return;
      }
      title.textContent = data.name || condition;
      link.href = data.url;

      let html = '';
      if (data.summary) {
        html += `<div style="background:#EFF6FF;border-left:4px solid #005EB8;border-radius:8px;padding:14px 16px;margin-bottom:16px;">
          <p style="margin:0;font-size:13.5px;color:#1e3a5f;line-height:1.7;">${data.summary}</p>
        </div>`;
      }
      if (data.sections && data.sections.length) {
        data.sections.forEach(sec => {
          html += `<div style="margin-bottom:14px;">
            <div style="font-weight:700;font-size:13px;color:#0A1F44;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
              <span style="width:6px;height:6px;background:#005EB8;border-radius:50%;display:inline-block;"></span>
              ${sec.heading}
            </div>
            <p style="font-size:13px;color:#374151;line-height:1.6;margin:0 0 0 12px;">${sec.text}</p>
          </div>`;
        });
      }
      html += `<div style="margin-top:16px;background:#FEF3C7;border:1px solid #F59E0B;border-radius:8px;padding:12px 14px;font-size:12px;color:#92400E;">
        <strong>⚠️ Important:</strong> This information is for guidance only. Always consult your doctor for personal medical advice.
      </div>`;
      body.innerHTML = html;
    })
    .catch(() => {
      body.innerHTML = `<p style="text-align:center;color:#6b7280;padding:30px;">Could not load NHS information. Please check your connection.</p>`;
    });
}
function closeNHSModal() {
  document.getElementById('nhsModal').style.display = 'none';
}
document.getElementById('nhsModal').addEventListener('click', function(e) {
  if (e.target === this) closeNHSModal();
});
</script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
</body>
</html>
