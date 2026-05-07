<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$user = getCurrentUser();
$uid  = $user['id'];

// ── Real DB Stats ──────────────────────────────────────────────────
$totalPatients  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='patient'")->fetchColumn();
$totalDoctors   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='doctor'")->fetchColumn();
$totalAppts     = (int)$pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$apptThisMonth  = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE MONTH(appointment_date)=MONTH(CURDATE()) AND YEAR(appointment_date)=YEAR(CURDATE())")->fetchColumn();
$apptLastMonth  = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE MONTH(appointment_date)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))")->fetchColumn();
$totalMsgs      = (int)$pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
$activePrescriptions = (int)$pdo->query("SELECT COUNT(*) FROM prescriptions WHERE is_active=1")->fetchColumn();
$criticalCases  = (int)$pdo->query("SELECT COUNT(*) FROM medical_records WHERE result_status='critical'")->fetchColumn();
$totalDocuments = (int)$pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();

// Patient registration by month (this year)
$regByMonth = [];
for ($m = 1; $m <= 12; $m++) {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='patient' AND MONTH(created_at)=? AND YEAR(created_at)=YEAR(CURDATE())");
    $cnt->execute([$m]);
    $regByMonth[] = (int)$cnt->fetchColumn();
}

// Appointments by month (this year)
$apptByMonth = [];
for ($m = 1; $m <= 12; $m++) {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE MONTH(appointment_date)=? AND YEAR(appointment_date)=YEAR(CURDATE())");
    $cnt->execute([$m]);
    $apptByMonth[] = (int)$cnt->fetchColumn();
}

// Appointment status distribution
$statusDist = $pdo->query("SELECT status, COUNT(*) cnt FROM appointments GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

// Medical record status distribution
$recordDist = $pdo->query("SELECT result_status, COUNT(*) cnt FROM medical_records GROUP BY result_status")->fetchAll(PDO::FETCH_KEY_PAIR);

// Top doctors by appointment count
$topDoctors = $pdo->query("
    SELECT u.first_name, u.last_name, d.specialization, d.rating, d.hospital_name,
           COUNT(a.id) as appt_count
    FROM users u
    JOIN doctors d ON u.id=d.user_id
    LEFT JOIN appointments a ON a.doctor_id=u.id
    GROUP BY u.id ORDER BY appt_count DESC LIMIT 5
")->fetchAll();

// User roles distribution
$roleDist = $pdo->query("SELECT role, COUNT(*) cnt FROM users GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);

// Allergy types
$allergyDist = $pdo->query("SELECT allergen, COUNT(*) cnt FROM allergies GROUP BY allergen ORDER BY cnt DESC LIMIT 6")->fetchAll(PDO::FETCH_KEY_PAIR);

// Prescription trend: active vs inactive
$medDist = $pdo->query("SELECT medication_name, COUNT(*) cnt FROM prescriptions GROUP BY medication_name ORDER BY cnt DESC LIMIT 6")->fetchAll(PDO::FETCH_KEY_PAIR);

// Recent system activity
$recentActivity = $pdo->query("
    SELECT al.action_type, al.ip_address, al.created_at, u.first_name, u.last_name, u.role
    FROM access_logs al JOIN users u ON al.user_id=u.id
    ORDER BY al.created_at DESC LIMIT 10
")->fetchAll();

// Daily new users (last 7 days)
$dailyUsers = [];
$dailyLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $cnt  = $pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at)=?");
    $cnt->execute([$date]);
    $dailyUsers[]  = (int)$cnt->fetchColumn();
    $dailyLabels[] = date('D d', strtotime($date));
}

$notifCount = getUnreadCount($pdo, $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Analytics — HealthSphere Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-chart-bar" style="color:var(--hs-blue);"></i> System Analytics</div>
      <div class="page-subtitle">Real-time HealthSphere platform statistics &middot; <?= date('d M Y') ?></div>
    </div>
    <div class="topbar-actions">
      <button class="btn-hs btn-outline-hs btn-sm-hs"><i class="fas fa-sync"></i> Refresh</button>
      <button class="btn-hs btn-primary-hs btn-sm-hs"><i class="fas fa-download"></i> Export Report</button>
    </div>
  </div>

  <div class="hs-content">

    <!-- KPIs -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:22px;">
      <div class="stat-card stat-blue"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-info"><div class="stat-label">Total Patients</div><div class="stat-value" data-count="<?= $totalPatients ?>"><?= $totalPatients ?></div><div class="stat-sub"><span class="text-success">↑ Active</span></div></div></div>
      <div class="stat-card stat-green"><div class="stat-icon"><i class="fas fa-user-md"></i></div><div class="stat-info"><div class="stat-label">Doctors</div><div class="stat-value" data-count="<?= $totalDoctors ?>"><?= $totalDoctors ?></div><div class="stat-sub">All verified</div></div></div>
      <div class="stat-card stat-teal"><div class="stat-icon"><i class="fas fa-calendar-check"></i></div><div class="stat-info"><div class="stat-label">Appointments</div><div class="stat-value" data-count="<?= $totalAppts ?>"><?= $totalAppts ?></div><div class="stat-sub"><?= $apptThisMonth ?> this month</div></div></div>
      <div class="stat-card stat-warning"><div class="stat-icon"><i class="fas fa-pills"></i></div><div class="stat-info"><div class="stat-label">Active Prescriptions</div><div class="stat-value" data-count="<?= $activePrescriptions ?>"><?= $activePrescriptions ?></div></div></div>
      <div class="stat-card stat-danger"><div class="stat-icon"><i class="fas fa-flask"></i></div><div class="stat-info"><div class="stat-label">Critical Cases</div><div class="stat-value" data-count="<?= $criticalCases ?>"><?= $criticalCases ?></div><div class="stat-sub">Require review</div></div></div>
      <div class="stat-card stat-purple"><div class="stat-icon"><i class="fas fa-comment"></i></div><div class="stat-info"><div class="stat-label">Total Messages</div><div class="stat-value" data-count="<?= $totalMsgs ?>"><?= $totalMsgs ?></div></div></div>
      <div class="stat-card stat-blue"><div class="stat-icon"><i class="fas fa-file-medical"></i></div><div class="stat-info"><div class="stat-label">Documents</div><div class="stat-value" data-count="<?= $totalDocuments ?>"><?= $totalDocuments ?></div></div></div>
    </div>

    <!-- Main charts row -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px;">

      <!-- Patient registration + appointment trend -->
      <div class="hs-card">
        <div class="hs-card-header">
          <span class="card-title"><i class="fas fa-chart-line"></i> Growth Trends (2025)</span>
          <div style="display:flex;gap:8px;font-size:11px;font-weight:600;">
            <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:3px;background:#1565C0;border-radius:2px;display:inline-block;"></span>New Patients</span>
            <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:3px;background:#16A34A;border-radius:2px;display:inline-block;"></span>Appointments</span>
          </div>
        </div>
        <div class="hs-card-body" style="height:260px;"><canvas id="growthChart"></canvas></div>
      </div>

      <!-- Role distribution -->
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-users-cog"></i> User Role Distribution</span></div>
        <div class="hs-card-body" style="height:260px;"><canvas id="rolePieChart"></canvas></div>
      </div>

    </div>

    <!-- Second charts row -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:20px;">

      <!-- Appointment status -->
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-calendar"></i> Appointment Status</span></div>
        <div class="hs-card-body" style="height:220px;"><canvas id="statusDonut"></canvas></div>
      </div>

      <!-- Lab result status -->
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-flask"></i> Lab Results Distribution</span></div>
        <div class="hs-card-body" style="height:220px;"><canvas id="labDonut"></canvas></div>
      </div>

      <!-- Daily new users (7 day) -->
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-user-plus"></i> New Users (7 Days)</span></div>
        <div class="hs-card-body" style="height:220px;"><canvas id="dailyUsersChart"></canvas></div>
      </div>

    </div>

    <!-- Third row: Top doctors + common medications -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

      <!-- Top Doctors -->
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-medal"></i> Top Doctors by Appointments</span></div>
        <div class="hs-card-body p-0">
          <?php foreach ($topDoctors as $i => $doc): ?>
          <div style="display:flex;align-items:center;gap:12px;padding:12px 18px;border-bottom:1px solid var(--hs-border);">
            <div style="width:28px;height:28px;border-radius:50%;background:<?= ['#1565C0','#16A34A','#D97706','#7C3AED','#0891B2'][$i] ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:13px;flex-shrink:0;"><?= $i+1 ?></div>
            <div style="flex:1;">
              <div style="font-weight:700;font-size:13px;color:var(--hs-navy);">Dr. <?= e($doc['first_name'].' '.$doc['last_name']) ?></div>
              <div style="font-size:11px;color:var(--hs-muted);"><?= e($doc['specialization']) ?> &middot; <?= e($doc['hospital_name']) ?></div>
            </div>
            <div style="text-align:right;">
              <div style="font-size:18px;font-weight:900;color:var(--hs-navy);"><?= $doc['appt_count'] ?></div>
              <div style="font-size:10px;color:var(--hs-muted);">appointments</div>
            </div>
            <div style="width:80px;">
              <div style="background:var(--hs-bg);border-radius:4px;height:6px;overflow:hidden;">
                <div style="width:<?= $doc['appt_count'] > 0 ? min(round(($doc['appt_count'] / max(array_column($topDoctors,'appt_count'))) * 100),100) : 0 ?>%;background:<?= ['#1565C0','#16A34A','#D97706','#7C3AED','#0891B2'][$i] ?>;height:100%;border-radius:4px;transition:width 1s;"></div>
              </div>
              <div style="font-size:10px;color:#F59E0B;margin-top:2px;"><?= str_repeat('★', (int)$doc['rating']) ?> <?= $doc['rating'] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Common Prescriptions -->
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-pills"></i> Most Prescribed Medications</span></div>
        <div class="hs-card-body" style="height:300px;"><canvas id="medsChart"></canvas></div>
      </div>

    </div>

    <!-- Recent Activity -->
    <div class="hs-card">
      <div class="hs-card-header"><span class="card-title"><i class="fas fa-history"></i> Recent System Activity</span></div>
      <div class="hs-card-body p-0">
        <table class="hs-table">
          <thead><tr><th>User</th><th>Role</th><th>Action</th><th>IP</th><th>Time</th></tr></thead>
          <tbody>
            <?php foreach ($recentActivity as $act):
              $actionColor = str_contains($act['action_type'],'LOGIN') ? '#16A34A' : (str_contains($act['action_type'],'DELETE') ? '#DC2626' : '#1565C0');
            ?>
            <tr>
              <td><strong><?= e($act['first_name'].' '.$act['last_name']) ?></strong></td>
              <td><span style="text-transform:capitalize;font-size:12px;color:var(--hs-muted);"><?= $act['role'] ?></span></td>
              <td><code style="background:<?= $actionColor ?>18;color:<?= $actionColor ?>;padding:2px 8px;border-radius:4px;font-size:12px;"><?= e($act['action_type']) ?></code></td>
              <td style="font-family:monospace;font-size:12px;"><?= e($act['ip_address']) ?></td>
              <td style="font-size:12px;color:var(--hs-muted);"><?= formatDate($act['created_at'], 'd M Y H:i') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/main.js"></script>
<script>
const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// Growth trends
new Chart(document.getElementById('growthChart'), {
  type:'line',
  data:{
    labels:MONTHS,
    datasets:[
      {label:'New Patients', data:<?= json_encode($regByMonth) ?>, borderColor:'#1565C0', backgroundColor:'rgba(21,101,192,.08)', tension:.4, fill:true, pointRadius:4, pointBackgroundColor:'#1565C0'},
      {label:'Appointments', data:<?= json_encode($apptByMonth) ?>, borderColor:'#16A34A', backgroundColor:'rgba(22,163,74,.06)', tension:.4, fill:true, pointRadius:4, pointBackgroundColor:'#16A34A'},
    ]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top',labels:{boxWidth:12,font:{size:11}}},tooltip:{mode:'index',intersect:false}},scales:{x:{grid:{display:false}},y:{grid:{color:'#EFF6FF'},min:0}}}
});

// Role distribution
new Chart(document.getElementById('rolePieChart'), {
  type:'doughnut',
  data:{
    labels:<?= json_encode(array_map('ucfirst', array_keys($roleDist))) ?>,
    datasets:[{ data:<?= json_encode(array_values($roleDist)) ?>, backgroundColor:['#1565C0','#16A34A','#D97706','#7C3AED'], borderWidth:2, borderColor:'#fff' }]
  },
  options:{responsive:true,maintainAspectRatio:false,cutout:'60%',plugins:{legend:{position:'bottom',labels:{boxWidth:12,font:{size:11}}}}}
});

// Appointment status
const statusLabels=<?= json_encode(array_map('ucfirst', array_keys($statusDist))) ?>;
const statusData=<?= json_encode(array_values($statusDist)) ?>;
new Chart(document.getElementById('statusDonut'), {
  type:'doughnut',
  data:{labels:statusLabels,datasets:[{data:statusData,backgroundColor:['#1565C0','#16A34A','#D97706','#DC2626','#7C3AED','#0891B2'],borderWidth:2,borderColor:'#fff'}]},
  options:{responsive:true,maintainAspectRatio:false,cutout:'55%',plugins:{legend:{position:'bottom',labels:{font:{size:10},boxWidth:10}}}}
});

// Lab result status
new Chart(document.getElementById('labDonut'), {
  type:'doughnut',
  data:{
    labels:<?= json_encode(array_map('ucfirst', array_keys($recordDist))) ?>,
    datasets:[{data:<?= json_encode(array_values($recordDist)) ?>,backgroundColor:['#16A34A','#D97706','#DC2626','#0891B2'],borderWidth:2,borderColor:'#fff'}]
  },
  options:{responsive:true,maintainAspectRatio:false,cutout:'55%',plugins:{legend:{position:'bottom',labels:{font:{size:10},boxWidth:10}}}}
});

// Daily new users
new Chart(document.getElementById('dailyUsersChart'), {
  type:'bar',
  data:{
    labels:<?= json_encode($dailyLabels) ?>,
    datasets:[{label:'New Users',data:<?= json_encode($dailyUsers) ?>,backgroundColor:'#1565C0',borderRadius:6,borderSkipped:false}]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{grid:{color:'#EFF6FF'},ticks:{stepSize:1}}}}
});

// Medications horizontal bar
new Chart(document.getElementById('medsChart'), {
  type:'bar',
  data:{
    labels:<?= json_encode(array_keys($medDist)) ?>,
    datasets:[{label:'Prescriptions',data:<?= json_encode(array_values($medDist)) ?>,backgroundColor:['#1565C0','#16A34A','#D97706','#DC2626','#7C3AED','#0891B2'],borderRadius:6,borderSkipped:false}]
  },
  options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{grid:{color:'#EFF6FF'}},y:{grid:{display:false}}}}
});
</script>
</body>
</html>
