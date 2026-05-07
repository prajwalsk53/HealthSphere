<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';
requireRole('patient');
$user = getCurrentUser();
$uid  = $user['id'];

$success = $error = '';

// Book appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book'])) {
    $doctorId = (int) ($_POST['doctor_id'] ?? 0);
    $date     = $_POST['appt_date'] ?? '';
    $time     = $_POST['appt_time'] ?? '';
    $reason   = trim($_POST['reason'] ?? '');

    if ($doctorId && $date && $time) {
        $stmt = $pdo->prepare("INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,reason,status) VALUES (?,?,?,?,?,'confirmed')");
        $stmt->execute([$uid, $doctorId, $date, $time, $reason]);
        // Notify patient
        $pdo->prepare("INSERT INTO notifications (user_id,title,message,notification_type) VALUES (?,'Appointment Confirmed','Your appointment has been scheduled.','appointment')")->execute([$uid]);
        // Send confirmation emails
        $pat = $pdo->prepare("SELECT first_name,last_name,email,nhs_id FROM users WHERE id=?"); $pat->execute([$uid]); $pat=$pat->fetch();
        $doc = $pdo->prepare("SELECT u.first_name,u.last_name,u.email,d.hospital_name FROM users u LEFT JOIN doctors d ON u.id=d.user_id WHERE u.id=?"); $doc->execute([$doctorId]); $doc=$doc->fetch();
        if ($pat && $doc) {
            $fDate = date('l, d F Y', strtotime($date)); $fTime = date('H:i', strtotime($time));
            @mailAppointmentPatient($pat['email'],$pat['first_name'].' '.$pat['last_name'],$doc['first_name'].' '.$doc['last_name'],$fDate,$fTime,$reason??'',$doc['hospital_name']??'');
            @mailAppointmentDoctor($doc['email'],$doc['first_name'].' '.$doc['last_name'],$pat['first_name'].' '.$pat['last_name'],$pat['nhs_id']??'',$fDate,$fTime,$reason??'');
        }
        $success = 'Appointment booked successfully!';
    } else {
        $error = 'Please fill in all required fields.';
    }
}

// Cancel appointment
if (isset($_GET['cancel'])) {
    $pdo->prepare("UPDATE appointments SET status='cancelled' WHERE id=? AND patient_id=?")->execute([(int)$_GET['cancel'], $uid]);
    $success = 'Appointment cancelled.';
}

// All appointments
$allAppts = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name, d.specialization, d.hospital_name, d.rating
    FROM appointments a
    JOIN users u ON a.doctor_id = u.id
    LEFT JOIN doctors d ON u.id = d.user_id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$allAppts->execute([$uid]);
$appointments = $allAppts->fetchAll();

// Doctors for booking
$doctors = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, d.specialization, d.hospital_name, d.rating, d.experience_years, d.consultation_fee
    FROM users u JOIN doctors d ON u.id=d.user_id
    WHERE u.is_active=1 AND d.is_verified=1
    ORDER BY d.rating DESC
")->fetchAll();

$notifCount = getUnreadCount($pdo, $uid);
$msgCount   = getUnreadMessages($pdo, $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Appointments — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <button id="menuToggle" style="display:none;background:none;border:none;cursor:pointer;font-size:20px;"><i class="fas fa-bars"></i></button>
    <div>
      <div class="page-title"><i class="fas fa-calendar-check" style="color:var(--hs-blue);"></i> Doctor Appointments</div>
      <div class="page-subtitle">Book, view and manage your consultations</div>
    </div>
    <div class="topbar-actions">
      <button class="btn-hs btn-primary-hs btn-sm-hs" onclick="document.getElementById('bookModal').style.display='flex'">
        <i class="fas fa-plus"></i> Book Appointment
      </button>
    </div>
  </div>

  <div class="hs-content">
    <?php if ($success): ?><div style="background:#DCFCE7;border:1px solid #BBF7D0;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#166534;font-size:13px;font-weight:600;"><i class="fas fa-check-circle"></i> <?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div style="background:#FEE2E2;border:1px solid #FECACA;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#991B1B;font-size:13px;"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div><?php endif; ?>

    <!-- Doctor cards -->
    <div style="margin-bottom:24px;">
      <h6 style="font-weight:700;color:var(--hs-navy);margin-bottom:14px;">Available Specialists</h6>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;">
        <?php foreach ($doctors as $doc): ?>
        <div class="doctor-card" onclick="selectDoctor(<?= $doc['id'] ?>, '<?= addslashes('Dr. '.$doc['first_name'].' '.$doc['last_name']) ?>', '<?= addslashes($doc['specialization']) ?>')">
          <div class="doc-avatar"><i class="fas fa-user-md"></i></div>
          <div class="doc-info">
            <div class="doc-name">Dr. <?= e($doc['first_name'].' '.$doc['last_name']) ?></div>
            <div class="doc-spec"><?= e($doc['specialization']) ?></div>
            <div class="doc-meta">
              <span class="star-rating"><?= str_repeat('★', (int)$doc['rating']) ?></span>
              <strong><?= $doc['rating'] ?></strong> · <?= $doc['experience_years'] ?>yr exp
            </div>
            <div class="doc-meta" style="margin-top:4px;">
              <i class="fas fa-hospital" style="font-size:11px;"></i> <?= e($doc['hospital_name']) ?>
            </div>
            <div style="margin-top:8px;">
              <button class="btn-hs btn-primary-hs btn-sm-hs">
                <i class="fas fa-calendar-plus"></i> Book
              </button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Appointments table -->
    <div class="hs-card">
      <div class="hs-card-header">
        <span class="card-title"><i class="fas fa-list"></i> All Appointments</span>
        <div style="display:flex;gap:8px;">
          <button class="btn-hs btn-outline-hs btn-sm-hs" onclick="filterAppts('all')">All</button>
          <button class="btn-hs btn-outline-hs btn-sm-hs" onclick="filterAppts('upcoming')">Upcoming</button>
          <button class="btn-hs btn-outline-hs btn-sm-hs" onclick="filterAppts('completed')">Completed</button>
        </div>
      </div>
      <div class="hs-card-body p-0">
        <table class="hs-table">
          <thead>
            <tr>
              <th>Doctor</th><th>Specialization</th><th>Date & Time</th><th>Reason</th><th>Status</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($appointments as $a): ?>
            <tr class="appt-row" data-status="<?= $a['status'] ?>">
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div style="width:36px;height:36px;border-radius:50%;background:var(--hs-blue-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;"><i class="fas fa-user-md"></i></div>
                  <div>
                    <div style="font-weight:600;">Dr. <?= e($a['first_name'].' '.$a['last_name']) ?></div>
                    <div style="font-size:11px;color:var(--hs-muted);"><?= e($a['hospital_name'] ?? '') ?></div>
                  </div>
                </div>
              </td>
              <td><?= e($a['specialization'] ?? 'General Practice') ?></td>
              <td>
                <div style="font-weight:600;"><?= formatDate($a['appointment_date']) ?></div>
                <div style="font-size:12px;color:var(--hs-muted);"><?= date('H:i', strtotime($a['appointment_time'])) ?></div>
              </td>
              <td><?= e($a['reason'] ?: '—') ?></td>
              <td><?= getStatusBadge($a['status']) ?></td>
              <td>
                <?php if (in_array($a['status'], ['pending','confirmed']) && $a['appointment_date'] >= date('Y-m-d')): ?>
                <a href="?cancel=<?= $a['id'] ?>" class="btn-hs btn-danger-hs btn-sm-hs"
                   onclick="return confirm('Cancel this appointment?')">
                  <i class="fas fa-times"></i> Cancel
                </a>
                <?php else: ?>
                <span style="font-size:12px;color:var(--hs-muted);">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$appointments): ?>
            <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--hs-muted);">No appointments found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Book Modal -->
<div id="bookModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2000;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:16px;width:100%;max-width:520px;box-shadow:var(--shadow-lg);overflow:hidden;">
    <div style="background:var(--hs-navy);color:#fff;padding:20px 24px;display:flex;justify-content:space-between;align-items:center;">
      <h5 style="margin:0;font-size:16px;font-weight:700;"><i class="fas fa-calendar-plus"></i> Book Appointment</h5>
      <button onclick="document.getElementById('bookModal').style.display='none'" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;">×</button>
    </div>
    <form method="POST" style="padding:24px;">
      <div id="selectedDoctorInfo" style="display:none;background:var(--hs-off-white);border-radius:8px;padding:12px 16px;margin-bottom:16px;border:1px solid var(--hs-border);">
        <div id="selDocName" style="font-weight:700;color:var(--hs-navy);"></div>
        <div id="selDocSpec" style="font-size:12px;color:var(--hs-blue);"></div>
      </div>
      <div style="margin-bottom:14px;">
        <label class="form-label">Select Doctor *</label>
        <select name="doctor_id" id="doctorSelect" class="form-select" required>
          <option value="">Choose a doctor...</option>
          <?php foreach ($doctors as $doc): ?>
          <option value="<?= $doc['id'] ?>" data-name="Dr. <?= e($doc['first_name'].' '.$doc['last_name']) ?>" data-spec="<?= e($doc['specialization']) ?>">
            Dr. <?= e($doc['first_name'].' '.$doc['last_name']) ?> — <?= e($doc['specialization']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
        <div>
          <label class="form-label">Date *</label>
          <input type="date" name="appt_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
        </div>
        <div>
          <label class="form-label">Time *</label>
          <select name="appt_time" class="form-select" required>
            <option value="">Select time</option>
            <?php
            $slots = ['09:00','09:30','10:00','10:30','11:00','11:30','13:00','13:30','14:00','14:30','15:00','16:00'];
            foreach ($slots as $s): ?>
            <option value="<?= $s ?>"><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="margin-bottom:20px;">
        <label class="form-label">Reason / Symptoms</label>
        <textarea name="reason" class="form-control" rows="3" placeholder="Brief description of your concern..."></textarea>
      </div>
      <div style="display:flex;gap:12px;">
        <button type="submit" name="book" class="btn-hs btn-primary-hs" style="flex:1;justify-content:center;">
          <i class="fas fa-check"></i> Confirm Booking
        </button>
        <button type="button" onclick="document.getElementById('bookModal').style.display='none'" class="btn-hs btn-outline-hs">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function selectDoctor(id, name, spec) {
  document.getElementById('doctorSelect').value = id;
  document.getElementById('selDocName').textContent = name;
  document.getElementById('selDocSpec').textContent = spec;
  document.getElementById('selectedDoctorInfo').style.display = 'block';
  document.getElementById('bookModal').style.display = 'flex';
}
document.getElementById('doctorSelect').addEventListener('change', function() {
  const opt = this.options[this.selectedIndex];
  document.getElementById('selDocName').textContent = opt.dataset.name || '';
  document.getElementById('selDocSpec').textContent = opt.dataset.spec || '';
  document.getElementById('selectedDoctorInfo').style.display = this.value ? 'block' : 'none';
});
function filterAppts(status) {
  document.querySelectorAll('.appt-row').forEach(row => {
    row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
  });
}
</script>
</body>
</html>
