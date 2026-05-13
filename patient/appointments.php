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
      <div style="margin-bottom:14px;">
        <label class="form-label">Date *</label>
        <input type="date" name="appt_date" id="apptDate" class="form-control" min="<?= date('Y-m-d') ?>" required onchange="loadSlots()">
      </div>

      <!-- Slot picker -->
      <div style="margin-bottom:14px;">
        <label class="form-label">Available Time Slots *</label>
        <input type="hidden" name="appt_time" id="apptTimeHidden" required>
        <div id="slotsContainer" style="min-height:60px;border:1.5px solid var(--hs-border);border-radius:9px;padding:12px;background:#FAFCFF;">
          <div id="slotsMsg" style="font-size:13px;color:var(--hs-muted);text-align:center;padding:8px 0;">
            <i class="fas fa-info-circle"></i> Select a doctor and date to see available slots
          </div>
          <div id="slotsGrid" style="display:flex;flex-wrap:wrap;gap:8px;display:none;"></div>
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
  loadSlots();
}
document.getElementById('doctorSelect').addEventListener('change', function() {
  const opt = this.options[this.selectedIndex];
  document.getElementById('selDocName').textContent = opt.dataset.name || '';
  document.getElementById('selDocSpec').textContent = opt.dataset.spec || '';
  document.getElementById('selectedDoctorInfo').style.display = this.value ? 'block' : 'none';
  loadSlots();
});

function loadSlots() {
  const doctorId = document.getElementById('doctorSelect').value;
  const date     = document.getElementById('apptDate').value;
  const msg      = document.getElementById('slotsMsg');
  const grid     = document.getElementById('slotsGrid');
  const hidden   = document.getElementById('apptTimeHidden');

  hidden.value = '';
  grid.style.display = 'none';
  grid.innerHTML = '';

  if (!doctorId || !date) {
    msg.style.display = 'block';
    msg.innerHTML = '<i class="fas fa-info-circle"></i> Select a doctor and date to see available slots';
    return;
  }

  msg.style.display = 'block';
  msg.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading available slots...';

  fetch(`../api/doctor-slots.php?doctor_id=${doctorId}&date=${date}`)
    .then(r => r.json())
    .then(data => {
      if (!data.slots || data.slots.length === 0) {
        msg.innerHTML = '<i class="fas fa-calendar-times" style="color:#DC2626;"></i> <strong>No availability</strong> — this doctor has no slots on ' + (data.message ? data.message.replace('Doctor is not available on this day','this day') : 'this date') + '.';
        return;
      }
      msg.style.display = 'none';
      grid.style.display = 'flex';
      data.slots.forEach(slot => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = slot.time;
        btn.style.cssText = `padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:${slot.available?'pointer':'not-allowed'};transition:.15s;border:2px solid ${slot.available?'var(--hs-blue)':'#e5e7eb'};background:${slot.available?'#fff':'#f9fafb'};color:${slot.available?'var(--hs-blue)':'#9CA3AF'};`;
        if (!slot.available) {
          btn.title = 'Already booked';
          btn.disabled = true;
          btn.style.textDecoration = 'line-through';
        } else {
          btn.onclick = () => selectSlot(slot.time, btn);
        }
        grid.appendChild(btn);
      });
      const avail = data.available;
      msg.style.display = 'block';
      msg.innerHTML = `<i class="fas fa-check-circle" style="color:#16A34A;"></i> <strong>${avail} slot${avail!==1?'s':''} available</strong> on ${data.day} — click to select`;
    })
    .catch(() => {
      msg.innerHTML = '<i class="fas fa-wifi" style="color:#DC2626;"></i> Could not load slots. Please try again.';
    });
}

function selectSlot(time, btn) {
  document.querySelectorAll('#slotsGrid button').forEach(b => {
    b.style.background = '#fff';
    b.style.color = 'var(--hs-blue)';
  });
  btn.style.background = 'var(--hs-blue)';
  btn.style.color = '#fff';
  document.getElementById('apptTimeHidden').value = time;
}
function filterAppts(status) {
  document.querySelectorAll('.appt-row').forEach(row => {
    row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
  });
}
</script>
</body>
</html>
