<?php
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
$user = getCurrentUser();
$uid  = $user['id'];

// Upcoming appointments for sidebar list
$upcomingStmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name, d.specialization, d.hospital_name, d.rating
    FROM appointments a
    JOIN users u ON a.doctor_id = u.id
    LEFT JOIN doctors d ON u.id = d.user_id
    WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status != 'cancelled'
    ORDER BY a.appointment_date, a.appointment_time
    LIMIT 5
");
$upcomingStmt->execute([$uid]);
$upcoming = $upcomingStmt->fetchAll();

// Doctors for booking
$doctors = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, d.specialization, d.hospital_name, d.rating, d.consultation_fee
    FROM users u JOIN doctors d ON u.id=d.user_id
    WHERE u.is_active=1 AND d.is_verified=1
    ORDER BY d.rating DESC
")->fetchAll();

// Stats
$totalAppts     = (int)$pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id=?")->execute([$uid]) ? $pdo->query("SELECT COUNT(*) FROM appointments WHERE patient_id=$uid")->fetchColumn() : 0;
$completedAppts = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE patient_id=$uid AND status='completed'")->fetchColumn();
$cancelledAppts = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE patient_id=$uid AND status='cancelled'")->fetchColumn();
$todayAppts     = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE patient_id=$uid AND appointment_date=CURDATE()")->fetchColumn();

$notifCount = getUnreadCount($pdo, $uid);
$msgCount   = getUnreadMessages($pdo, $uid);

$statusColors = [
    'confirmed'=>'#1565C0','upcoming'=>'#1565C0','pending'=>'#D97706',
    'arrived'=>'#0891B2','waiting'=>'#7C3AED','completed'=>'#16A34A',
    'cancelled'=>'#94A3B8','late'=>'#DC2626',
];

$timeSlots = ['09:00','09:30','10:00','10:30','11:00','11:30','13:00','13:30','14:00','14:30','15:00','15:30','16:00','16:30'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Appointment Calendar — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<!-- FullCalendar v6 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
<style>
/* ── FullCalendar Overrides ────────────────────────────────────── */
.fc { font-family: 'Inter', system-ui, sans-serif; font-size: 13px; }

.fc .fc-toolbar-title {
  font-size: 18px !important; font-weight: 800 !important; color: var(--hs-navy) !important;
}
.fc .fc-button {
  background: var(--hs-off-white) !important;
  border: 1px solid var(--hs-border) !important;
  color: var(--hs-navy) !important;
  font-weight: 600 !important;
  font-size: 12px !important;
  border-radius: 8px !important;
  padding: 6px 14px !important;
  transition: var(--transition) !important;
  box-shadow: none !important;
}
.fc .fc-button:hover, .fc .fc-button-active {
  background: var(--hs-blue) !important;
  border-color: var(--hs-blue) !important;
  color: #fff !important;
}
.fc .fc-button-primary:not(:disabled).fc-button-active {
  background: var(--hs-blue) !important;
  border-color: var(--hs-blue) !important;
  color: #fff !important;
}
.fc .fc-today-button {
  background: var(--hs-blue) !important;
  border-color: var(--hs-blue) !important;
  color: #fff !important;
}
.fc .fc-col-header-cell-cushion {
  font-weight: 700 !important; color: var(--hs-navy) !important; font-size: 12px !important;
  text-transform: uppercase; letter-spacing: .5px;
}
.fc .fc-daygrid-day-number {
  font-weight: 700; color: var(--hs-navy); font-size: 13px;
}
.fc .fc-daygrid-day.fc-day-today {
  background: rgba(21,101,192,.06) !important;
}
.fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
  background: var(--hs-blue); color: #fff;
  border-radius: 50%; width: 28px; height: 28px;
  display: flex; align-items: center; justify-content: center;
  margin: 4px;
}
.fc-event {
  border-radius: 6px !important;
  font-size: 11.5px !important;
  font-weight: 600 !important;
  padding: 3px 6px !important;
  cursor: pointer !important;
  border: none !important;
  box-shadow: 0 2px 4px rgba(0,0,0,.12) !important;
  transition: var(--transition) !important;
}
.fc-event:hover { opacity: .88 !important; transform: scale(1.02); }
.fc-event-title { font-weight: 600 !important; }
.fc .fc-timegrid-slot { height: 36px !important; }
.fc .fc-timegrid-slot-label-cushion { font-size: 11px !important; color: var(--hs-muted) !important; font-weight: 600 !important; }
.fc .fc-list-event-title { font-weight: 600 !important; }
.fc .fc-list-event:hover td { background: var(--hs-off-white) !important; }
.fc .fc-scrollgrid { border-radius: 12px; overflow: hidden; border: 1px solid var(--hs-border) !important; }
.fc .fc-scrollgrid-sync-table td, .fc .fc-scrollgrid-sync-table th { border-color: var(--hs-border) !important; }
.fc .fc-toolbar { margin-bottom: 16px !important; }

/* ── Mini calendar ─────────────────────────────────────────────── */
.mini-cal table { width: 100%; border-collapse: collapse; }
.mini-cal th { font-size: 11px; font-weight: 700; color: var(--hs-muted); text-align: center; padding: 4px; text-transform: uppercase; letter-spacing: .5px; }
.mini-cal td { text-align: center; padding: 3px; }
.mini-cal .day-num {
  width: 28px; height: 28px; border-radius: 50%;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 500; cursor: pointer; color: var(--hs-text);
  transition: var(--transition);
}
.mini-cal .day-num:hover { background: var(--hs-bg); }
.mini-cal .day-num.today { background: var(--hs-blue); color: #fff; font-weight: 700; }
.mini-cal .day-num.other-month { color: var(--hs-border); }
.mini-cal .day-num.has-appt::after {
  content: '';
  width: 4px; height: 4px; border-radius: 50%; background: var(--hs-blue);
  display: block; margin: 0 auto; margin-top: -2px;
}
.mini-cal .day-num.selected { background: var(--hs-navy); color: #fff; }

/* ── Event detail popup ────────────────────────────────────────── */
.event-popup {
  position: fixed; z-index: 3000;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 20px 60px rgba(10,31,68,.22);
  border: 1px solid var(--hs-border);
  width: 320px;
  overflow: hidden;
  animation: popIn .18s ease;
}
@keyframes popIn { from { transform: scale(.92); opacity: 0; } }
.popup-header { background: var(--hs-blue); color: #fff; padding: 14px 18px; }
.popup-body   { padding: 16px 18px; }
.popup-row {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 0; border-bottom: 1px solid var(--hs-border); font-size: 13px;
}
.popup-row:last-child { border-bottom: none; }
.popup-row i { width: 18px; color: var(--hs-blue); font-size: 14px; }
.popup-actions { padding: 12px 18px; background: var(--hs-off-white); display: flex; gap: 8px; }

/* ── Book modal extras ─────────────────────────────────────────── */
.time-slot-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 6px; max-height: 160px; overflow-y: auto; }
.doc-option {
  border: 1.5px solid var(--hs-border); border-radius: 10px; padding: 10px;
  cursor: pointer; transition: var(--transition);
}
.doc-option:hover, .doc-option.selected { border-color: var(--hs-blue); background: #EFF6FF; }
.doc-option input { display: none; }
.legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">

  <!-- Topbar -->
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-calendar-alt" style="color:var(--hs-blue);"></i> Appointment Calendar</div>
      <div class="page-subtitle"><?= date('F Y') ?> &middot; <?= $totalAppts ?> total appointments</div>
    </div>
    <div class="topbar-actions">
      <a href="appointments.php" class="btn-hs btn-outline-hs btn-sm-hs">
        <i class="fas fa-list"></i> List View
      </a>
      <button class="btn-hs btn-primary-hs" onclick="openBookModal()">
        <i class="fas fa-plus"></i> New Appointment
      </button>
    </div>
  </div>

  <div class="hs-content" style="display:grid;grid-template-columns:260px 1fr;gap:20px;height:calc(100vh - 88px);overflow:hidden;">

    <!-- LEFT SIDEBAR -->
    <div style="display:flex;flex-direction:column;gap:16px;overflow-y:auto;">

      <!-- KPI mini cards -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <?php
        $miniStats = [
          ['Total','fa-calendar',$totalAppts,'#1565C0','#DBEAFE'],
          ['Today','fa-clock',$todayAppts,'#0891B2','#CFFAFE'],
          ['Done','fa-check-circle',$completedAppts,'#16A34A','#DCFCE7'],
          ['Cancelled','fa-times',$cancelledAppts,'#DC2626','#FEE2E2'],
        ];
        foreach ($miniStats as [$label, $icon, $val, $color, $bg]):
        ?>
        <div style="background:#fff;border:1px solid var(--hs-border);border-radius:10px;padding:12px;text-align:center;">
          <div style="width:32px;height:32px;border-radius:8px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;margin:0 auto 6px;color:<?= $color ?>;font-size:14px;">
            <i class="fas <?= $icon ?>"></i>
          </div>
          <div style="font-size:20px;font-weight:800;color:var(--hs-navy);"><?= $val ?></div>
          <div style="font-size:10px;color:var(--hs-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Mini calendar -->
      <div class="hs-card">
        <div class="hs-card-header" style="padding:12px 14px;">
          <span class="card-title" style="font-size:13px;"><i class="fas fa-calendar"></i> <?= date('F Y') ?></span>
          <div style="display:flex;gap:4px;">
            <button id="miniPrev" style="background:none;border:none;cursor:pointer;color:var(--hs-muted);padding:2px 6px;border-radius:4px;" title="Previous">‹</button>
            <button id="miniNext" style="background:none;border:none;cursor:pointer;color:var(--hs-muted);padding:2px 6px;border-radius:4px;" title="Next">›</button>
          </div>
        </div>
        <div class="hs-card-body" style="padding:10px;" id="miniCalContainer">
          <!-- Rendered by JS -->
        </div>
      </div>

      <!-- Status legend -->
      <div class="hs-card">
        <div class="hs-card-body" style="padding:14px;">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--hs-muted);margin-bottom:10px;">Status Legend</div>
          <?php foreach ($statusColors as $status => $color): ?>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
            <span class="legend-dot" style="background:<?= $color ?>;"></span>
            <span style="font-size:12px;color:var(--hs-text);text-transform:capitalize;font-weight:500;"><?= $status ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Doctor Appointment List -->
      <div class="hs-card" style="flex:1;">
        <div class="hs-card-header" style="padding:12px 14px;">
          <span class="card-title" style="font-size:13px;"><i class="fas fa-stethoscope"></i> Upcoming</span>
          <a href="appointments.php" style="font-size:11px;color:var(--hs-blue);">See all →</a>
        </div>
        <div class="hs-card-body p-0">
          <?php if ($upcoming): foreach ($upcoming as $appt):
            $dot = $statusColors[$appt['status']] ?? '#5E7A99';
          ?>
          <div style="padding:10px 14px;border-bottom:1px solid var(--hs-border);cursor:pointer;transition:var(--transition);" onmouseover="this.style.background='#F4F8FF'" onmouseout="this.style.background='#fff'">
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="width:36px;height:36px;border-radius:50%;background:<?= $dot ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0;">
                <i class="fas fa-user-md"></i>
              </div>
              <div style="flex:1;">
                <div style="font-weight:700;font-size:12.5px;color:var(--hs-navy);">Dr. <?= e($appt['first_name'].' '.$appt['last_name']) ?></div>
                <div style="font-size:11px;color:var(--hs-muted);"><?= e($appt['specialization'] ?? '') ?></div>
              </div>
              <div style="text-align:right;">
                <div style="font-size:11px;font-weight:700;color:var(--hs-navy);"><?= date('d M', strtotime($appt['appointment_date'])) ?></div>
                <div style="font-size:10px;color:var(--hs-muted);"><?= date('H:i', strtotime($appt['appointment_time'])) ?></div>
              </div>
            </div>
          </div>
          <?php endforeach; else: ?>
          <div style="padding:24px;text-align:center;color:var(--hs-muted);font-size:12px;">
            <i class="fas fa-calendar-check" style="font-size:24px;opacity:.3;"></i>
            <p style="margin-top:8px;">No upcoming appointments</p>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /left sidebar -->

    <!-- MAIN CALENDAR -->
    <div class="hs-card" style="overflow:hidden;display:flex;flex-direction:column;">
      <div id="mainCalendar" style="flex:1;padding:20px;overflow-y:auto;"></div>
    </div>

  </div><!-- /content grid -->
</div><!-- /hs-main -->

<!-- ═══════════════════════════════════════════════════════════════
     EVENT DETAIL POPUP
═══════════════════════════════════════════════════════════════ -->
<div id="eventPopup" class="event-popup" style="display:none;">
  <div class="popup-header">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;">
      <div>
        <div id="popupTitle" style="font-size:15px;font-weight:800;"></div>
        <div id="popupSub" style="font-size:12px;opacity:.75;margin-top:2px;"></div>
      </div>
      <button onclick="closePopup()" style="background:rgba(255,255,255,.15);border:none;color:#fff;width:26px;height:26px;border-radius:50%;cursor:pointer;font-size:14px;">&times;</button>
    </div>
  </div>
  <div class="popup-body">
    <div class="popup-row"><i class="fas fa-calendar"></i><div id="popupDate"></div></div>
    <div class="popup-row"><i class="fas fa-clock"></i><div id="popupTime"></div></div>
    <div class="popup-row"><i class="fas fa-notes-medical"></i><div id="popupReason"></div></div>
    <div class="popup-row"><i class="fas fa-hospital"></i><div id="popupHospital" style="font-size:12px;color:var(--hs-muted);"></div></div>
    <div class="popup-row"><i class="fas fa-circle" id="popupStatusIcon" style="font-size:8px;"></i><div id="popupStatus" style="font-weight:700;text-transform:capitalize;"></div></div>
  </div>
  <div class="popup-actions">
    <button id="popupCancelBtn" class="btn-hs btn-danger-hs btn-sm-hs" style="flex:1;justify-content:center;" onclick="cancelEvent()">
      <i class="fas fa-times"></i> Cancel
    </button>
    <button class="btn-hs btn-outline-hs btn-sm-hs" onclick="closePopup()" style="flex:1;justify-content:center;">
      Close
    </button>
  </div>
</div>
<div id="popupOverlay" onclick="closePopup()" style="display:none;position:fixed;inset:0;z-index:2999;"></div>

<!-- ═══════════════════════════════════════════════════════════════
     BOOK APPOINTMENT MODAL
═══════════════════════════════════════════════════════════════ -->
<div id="bookModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:4000;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:18px;width:100%;max-width:680px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 64px rgba(10,31,68,.25);">
    <!-- Modal header -->
    <div style="background:var(--hs-navy);color:#fff;padding:20px 24px;display:flex;justify-content:space-between;align-items:center;border-radius:18px 18px 0 0;position:sticky;top:0;z-index:1;">
      <div>
        <h5 style="margin:0;font-size:17px;font-weight:800;"><i class="fas fa-calendar-plus"></i> Book New Appointment</h5>
        <div id="bookingDateLabel" style="font-size:12px;opacity:.75;margin-top:2px;"></div>
      </div>
      <button onclick="document.getElementById('bookModal').style.display='none'" style="background:rgba(255,255,255,.15);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:18px;">&times;</button>
    </div>

    <div style="padding:24px;">

      <!-- Step indicator -->
      <div style="display:flex;align-items:center;gap:0;margin-bottom:24px;">
        <?php foreach (['Select Doctor','Choose Time','Confirm'] as $i => $step): ?>
        <div style="display:flex;align-items:center;flex:1;">
          <div style="display:flex;flex-direction:column;align-items:center;flex:1;">
            <div id="step<?= $i+1 ?>indicator" style="width:30px;height:30px;border-radius:50%;background:<?= $i===0?'var(--hs-blue)':'var(--hs-border)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $i===0?'#fff':'var(--hs-muted)' ?>;font-weight:700;font-size:13px;transition:.3s;"><?= $i+1 ?></div>
            <div style="font-size:10px;font-weight:600;color:var(--hs-muted);margin-top:4px;text-align:center;"><?= $step ?></div>
          </div>
          <?php if ($i < 2): ?>
          <div style="height:2px;flex:1;background:var(--hs-border);margin:0 -10px;margin-bottom:16px;"></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Step 1: Doctor selection -->
      <div id="stepPanel1">
        <div style="font-size:13px;font-weight:600;color:var(--hs-navy);margin-bottom:12px;">Choose a Specialist</div>
        <div class="input-icon-wrap" style="margin-bottom:14px;">
          <i class="fas fa-search"></i>
          <input type="text" id="docSearch" placeholder="Search doctors..." class="form-control" oninput="filterDoctors(this.value)">
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;" id="specFilters">
          <button onclick="filterSpec('')" class="spec-btn active" style="padding:5px 14px;border-radius:20px;border:1.5px solid var(--hs-blue);background:var(--hs-blue);color:#fff;font-size:12px;font-weight:600;cursor:pointer;">All</button>
          <?php
          $specs = array_unique(array_column($doctors, 'specialization'));
          foreach ($specs as $sp): ?>
          <button onclick="filterSpec('<?= e($sp) ?>')" class="spec-btn" style="padding:5px 14px;border-radius:20px;border:1.5px solid var(--hs-border);background:#fff;color:var(--hs-muted);font-size:12px;font-weight:600;cursor:pointer;"><?= e($sp) ?></button>
          <?php endforeach; ?>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;max-height:280px;overflow-y:auto;padding-right:4px;" id="doctorGrid">
          <?php foreach ($doctors as $doc): ?>
          <label class="doc-option" data-name="<?= strtolower($doc['first_name'].' '.$doc['last_name']) ?>" data-spec="<?= strtolower($doc['specialization']) ?>">
            <input type="radio" name="doc_radio" value="<?= $doc['id'] ?>" data-name="Dr. <?= e($doc['first_name'].' '.$doc['last_name']) ?>" data-spec="<?= e($doc['specialization']) ?>" onchange="selectDoctor(this)">
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="width:42px;height:42px;border-radius:50%;background:var(--hs-blue-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:16px;flex-shrink:0;">
                <?= strtoupper(substr($doc['first_name'],0,1)) ?>
              </div>
              <div>
                <div style="font-weight:700;font-size:13px;color:var(--hs-navy);">Dr. <?= e($doc['first_name'].' '.$doc['last_name']) ?></div>
                <div style="font-size:11px;color:var(--hs-blue);"><?= e($doc['specialization']) ?></div>
                <div style="font-size:10px;color:var(--hs-muted);display:flex;align-items:center;gap:4px;">
                  <span style="color:#F59E0B;"><?= str_repeat('★', (int)$doc['rating']) ?></span>
                  <?= $doc['rating'] ?> &middot; £<?= $doc['consultation_fee'] ?>
                </div>
              </div>
            </div>
            <div style="font-size:11px;color:var(--hs-muted);margin-top:6px;padding-top:6px;border-top:1px solid var(--hs-border);">
              <i class="fas fa-hospital" style="width:14px;"></i> <?= e($doc['hospital_name']) ?>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:16px;text-align:right;">
          <button onclick="goStep(2)" id="step1Next" class="btn-hs btn-primary-hs" disabled style="opacity:.5;">
            Next: Choose Time <i class="fas fa-arrow-right"></i>
          </button>
        </div>
      </div>

      <!-- Step 2: Date & Time -->
      <div id="stepPanel2" style="display:none;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
          <div>
            <label class="form-label">Appointment Date</label>
            <input type="date" id="bookDate" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" onchange="updateTimeSlots()">
          </div>
          <div>
            <label class="form-label">Reason / Symptoms</label>
            <input type="text" id="bookReason" class="form-control" placeholder="e.g. Annual check-up">
          </div>
        </div>

        <label class="form-label">Available Time Slots</label>
        <div class="time-slot-grid" id="timeSlotGrid">
          <?php foreach ($timeSlots as $slot): ?>
          <button type="button" class="time-slot" data-time="<?= $slot ?>:00" onclick="selectTimeSlot(this)">
            <?= $slot ?>
          </button>
          <?php endforeach; ?>
        </div>

        <div style="margin-top:8px;font-size:12px;color:var(--hs-muted);">
          <span style="display:inline-flex;align-items:center;gap:4px;margin-right:12px;"><span style="width:10px;height:10px;border-radius:2px;background:var(--hs-blue);display:inline-block;"></span> Selected</span>
          <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:var(--hs-bg);border:1px dashed var(--hs-border);display:inline-block;"></span> Available</span>
        </div>

        <div style="display:flex;gap:10px;margin-top:20px;">
          <button onclick="goStep(1)" class="btn-hs btn-outline-hs" style="flex:1;justify-content:center;">
            <i class="fas fa-arrow-left"></i> Back
          </button>
          <button onclick="goStep(3)" id="step2Next" class="btn-hs btn-primary-hs" style="flex:2;justify-content:center;" disabled style="opacity:.5;">
            Review Booking <i class="fas fa-arrow-right"></i>
          </button>
        </div>
      </div>

      <!-- Step 3: Confirm -->
      <div id="stepPanel3" style="display:none;">
        <div style="background:var(--hs-off-white);border-radius:12px;padding:20px;margin-bottom:20px;">
          <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--hs-muted);margin-bottom:16px;">Booking Summary</div>
          <div style="display:grid;gap:12px;">
            <div style="display:flex;align-items:center;gap:12px;">
              <div style="width:44px;height:44px;border-radius:50%;background:var(--hs-blue-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;"><i class="fas fa-user-md"></i></div>
              <div>
                <div id="confirmDoctor" style="font-weight:800;font-size:15px;color:var(--hs-navy);"></div>
                <div id="confirmSpec" style="font-size:12px;color:var(--hs-blue);"></div>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
              <?php foreach ([['fa-calendar','Date','confirmDate'],['fa-clock','Time','confirmTime'],['fa-notes-medical','Reason','confirmReason'],['fa-shield-alt','Status','confirmStatusText']] as [$ico,$lbl,$id]): ?>
              <div style="background:#fff;border-radius:8px;padding:10px 14px;border:1px solid var(--hs-border);">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--hs-muted);margin-bottom:3px;"><i class="fas <?= $ico ?>" style="color:var(--hs-blue);margin-right:4px;"></i><?= $lbl ?></div>
                <div id="<?= $id ?>" style="font-weight:600;font-size:13px;color:var(--hs-navy);">—</div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div style="display:flex;align-items:center;gap:10px;background:#EFF6FF;border-radius:8px;padding:12px;margin-bottom:20px;font-size:13px;color:#1E40AF;">
          <i class="fas fa-info-circle"></i>
          <span>You'll receive a confirmation notification immediately after booking.</span>
        </div>

        <div style="display:flex;gap:10px;">
          <button onclick="goStep(2)" class="btn-hs btn-outline-hs" style="flex:1;justify-content:center;">
            <i class="fas fa-arrow-left"></i> Back
          </button>
          <button onclick="confirmBooking()" class="btn-hs btn-primary-hs" style="flex:2;justify-content:center;" id="confirmBookBtn">
            <i class="fas fa-check"></i> Confirm Appointment
          </button>
        </div>
      </div>

    </div><!-- /padding -->
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="../assets/js/main.js"></script>
<script>
// ── State ────────────────────────────────────────────────────────
let calendar;
let currentPopupId = null;
let selectedDoctorId = null, selectedDoctorName = '', selectedDoctorSpec = '';
let selectedTime = '', selectedDate = '';
let miniCalDate = new Date();

const STATUS_COLORS = <?= json_encode($statusColors) ?>;

// ── FullCalendar init ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  calendar = new FullCalendar.Calendar(document.getElementById('mainCalendar'), {
    initialView: 'dayGridMonth',
    headerToolbar: {
      left:   'prev,next today',
      center: 'title',
      right:  'dayGridMonth,timeGridWeek,timeGridDay,listMonth',
    },
    buttonText: {
      today:        'Today',
      month:        'Month',
      week:         'Week',
      day:          'Day',
      listMonth:    'List',
    },
    height:        'auto',
    nowIndicator:  true,
    navLinks:      true,
    editable:      true,
    droppable:     true,
    selectable:    true,
    dayMaxEvents:  3,

    // Load events from API
    events: {
      url:    '<?= BASE_PATH ?>/api/appointments.php?action=calendar',
      method: 'GET',
    },

    // Click on event → show popup
    eventClick: (info) => {
      info.jsEvent.stopPropagation();
      showPopup(info.event, info.jsEvent);
    },

    // Click on date → open book modal
    dateClick: (info) => {
      openBookModal(info.dateStr);
    },

    // Drag & drop → reschedule via AJAX
    eventDrop: (info) => {
      const { event } = info;
      const newDate = event.startStr.split('T')[0];
      const newTime = event.startStr.split('T')[1] || '09:00:00';

      fetch('<?= BASE_PATH ?>/api/appointments.php?action=move', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: event.id, date: newDate, time: newTime }),
      })
      .then(r => r.json())
      .then(d => {
        if (d.ok) {
          showToast('Appointment rescheduled to ' + formatDateLabel(newDate), 'success');
          renderMiniCal();
        } else {
          showToast('Could not reschedule: ' + (d.error||'Error'), 'error');
          info.revert();
        }
      })
      .catch(() => { info.revert(); showToast('Network error', 'error'); });
    },

    // Event resize
    eventResize: (info) => {
      info.revert(); // Block resize — appointments are fixed 30min
    },

    // Sync mini cal when navigating
    datesSet: (info) => {
      miniCalDate = new Date(info.view.currentStart);
      miniCalDate.setDate(1);
      renderMiniCal();
    },

    // Custom event rendering — add icon
    eventContent: (arg) => {
      const props = arg.event.extendedProps;
      const icon  = props.status === 'completed' ? '✓' : (props.status === 'cancelled' ? '✗' : '●');
      return {
        html: `<div style="display:flex;align-items:center;gap:4px;white-space:nowrap;overflow:hidden;">
          <span style="font-size:9px;">${icon}</span>
          <span style="overflow:hidden;text-overflow:ellipsis;">${arg.event.title}</span>
        </div>`
      };
    },
  });

  calendar.render();
  renderMiniCal();
});

// ── Event popup ───────────────────────────────────────────────────
function showPopup(event, jsEvent) {
  const props   = event.extendedProps;
  const popup   = document.getElementById('eventPopup');
  const overlay = document.getElementById('popupOverlay');

  currentPopupId = event.id;

  // Populate
  document.getElementById('popupTitle').textContent   = event.title;
  document.getElementById('popupSub').textContent     = props.sub || props.specialization || '';
  document.getElementById('popupDate').textContent    = formatDateLabel(props.date || event.startStr.split('T')[0]);
  document.getElementById('popupTime').textContent    = props.time || event.startStr.split('T')[1]?.substring(0,5) || '—';
  document.getElementById('popupReason').textContent  = props.reason || '—';
  document.getElementById('popupHospital').textContent= props.hospital || '—';
  document.getElementById('popupStatus').textContent  = props.status || '—';
  document.getElementById('popupStatusIcon').style.color = STATUS_COLORS[props.status] || '#5E7A99';

  // Position near click, keep on screen
  popup.style.display = 'block';
  overlay.style.display = 'block';
  const x = Math.min(jsEvent.clientX + 10, window.innerWidth - 340);
  const y = Math.min(jsEvent.clientY - 20, window.innerHeight - 420);
  popup.style.left = x + 'px';
  popup.style.top  = Math.max(10, y) + 'px';

  // Hide cancel btn if already cancelled/completed
  const cancelBtn = document.getElementById('popupCancelBtn');
  cancelBtn.style.display = ['cancelled','completed'].includes(props.status) ? 'none' : '';

  // Popup header color
  popup.querySelector('.popup-header').style.background = STATUS_COLORS[props.status] || 'var(--hs-blue)';
}

function closePopup() {
  document.getElementById('eventPopup').style.display = 'none';
  document.getElementById('popupOverlay').style.display = 'none';
  currentPopupId = null;
}

function cancelEvent() {
  if (!currentPopupId) return;
  if (!confirm('Cancel this appointment?')) return;
  fetch('<?= BASE_PATH ?>/api/appointments.php?action=cancel', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: currentPopupId }),
  })
  .then(r => r.json())
  .then(d => {
    if (d.ok) {
      calendar.refetchEvents();
      closePopup();
      showToast('Appointment cancelled.', 'info');
    }
  });
}

// ── Book Modal ────────────────────────────────────────────────────
function openBookModal(dateStr = null) {
  selectedDate = dateStr || new Date().toISOString().split('T')[0];
  document.getElementById('bookingDateLabel').textContent = dateStr ? 'Selected: ' + formatDateLabel(selectedDate) : '';
  if (dateStr) document.getElementById('bookDate').value = dateStr;
  goStep(1);
  document.getElementById('bookModal').style.display = 'flex';
  selectedDoctorId = null; selectedTime = '';
  document.getElementById('step1Next').disabled = true;
  document.getElementById('step1Next').style.opacity = '.5';
  document.querySelectorAll('.doc-option').forEach(el => el.classList.remove('selected'));
  document.querySelectorAll('.time-slot').forEach(el => el.classList.remove('selected'));
}

function selectDoctor(radio) {
  selectedDoctorId   = parseInt(radio.value);
  selectedDoctorName = radio.dataset.name;
  selectedDoctorSpec = radio.dataset.spec;
  document.querySelectorAll('.doc-option').forEach(el => el.classList.remove('selected'));
  radio.closest('.doc-option').classList.add('selected');
  document.getElementById('step1Next').disabled = false;
  document.getElementById('step1Next').style.opacity = '1';
}

function selectTimeSlot(btn) {
  document.querySelectorAll('.time-slot').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  selectedTime = btn.dataset.time;
  document.getElementById('step2Next').disabled = false;
  document.getElementById('step2Next').style.opacity = '1';
}

function goStep(n) {
  [1,2,3].forEach(i => {
    document.getElementById('stepPanel'+i).style.display = i === n ? 'block' : 'none';
    const ind = document.getElementById('step'+i+'indicator');
    if (ind) {
      ind.style.background = i <= n ? 'var(--hs-blue)' : 'var(--hs-border)';
      ind.style.color = i <= n ? '#fff' : 'var(--hs-muted)';
    }
  });
  if (n === 3) {
    selectedDate = document.getElementById('bookDate').value;
    document.getElementById('confirmDoctor').textContent    = selectedDoctorName;
    document.getElementById('confirmSpec').textContent      = selectedDoctorSpec;
    document.getElementById('confirmDate').textContent      = formatDateLabel(selectedDate);
    document.getElementById('confirmTime').textContent      = selectedTime.substring(0,5);
    document.getElementById('confirmReason').textContent    = document.getElementById('bookReason').value || 'Not specified';
    document.getElementById('confirmStatusText').textContent= 'Will be Confirmed';
  }
}

function confirmBooking() {
  const btn = document.getElementById('confirmBookBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Booking...';

  fetch('<?= BASE_PATH ?>/api/appointments.php?action=book', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      doctor_id: selectedDoctorId,
      date:      document.getElementById('bookDate').value,
      time:      selectedTime || '09:00:00',
      reason:    document.getElementById('bookReason').value,
    }),
  })
  .then(r => r.json())
  .then(d => {
    if (d.ok) {
      document.getElementById('bookModal').style.display = 'none';
      calendar.refetchEvents();
      renderMiniCal();
      showToast('Appointment booked successfully! 🎉', 'success');
      // Reload sidebar after 500ms
      setTimeout(() => location.reload(), 1800);
    } else {
      showToast('Booking failed: ' + (d.error||'Please try again.'), 'error');
      btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Confirm Appointment';
    }
  });
}

// ── Doctor search / spec filter ───────────────────────────────────
function filterDoctors(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.doc-option').forEach(el => {
    el.style.display = (el.dataset.name.includes(q) || el.dataset.spec.includes(q)) ? '' : 'none';
  });
}

function filterSpec(spec) {
  document.querySelectorAll('.spec-btn').forEach(btn => {
    btn.style.background = '#fff'; btn.style.color = 'var(--hs-muted)'; btn.style.borderColor = 'var(--hs-border)';
  });
  event.target.style.background = 'var(--hs-blue)'; event.target.style.color = '#fff'; event.target.style.borderColor = 'var(--hs-blue)';
  document.querySelectorAll('.doc-option').forEach(el => {
    el.style.display = (!spec || el.dataset.spec === spec.toLowerCase()) ? '' : 'none';
  });
}

// ── Mini calendar renderer ─────────────────────────────────────────
function renderMiniCal() {
  const year  = miniCalDate.getFullYear();
  const month = miniCalDate.getMonth();
  const today = new Date();
  const firstDay = new Date(year, month, 1).getDay(); // 0=Sun
  const daysInMonth = new Date(year, month+1, 0).getDate();
  const monthNames  = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  const dayNames    = ['Su','Mo','Tu','We','Th','Fr','Sa'];

  // Appointment dates for dot markers
  const apptDates = new Set();
  document.querySelectorAll('.fc-daygrid-day[data-date]').forEach(cell => {
    if (cell.querySelectorAll('.fc-event').length > 0) apptDates.add(cell.dataset.date);
  });

  let html = `<table class="mini-cal" style="width:100%;">
    <thead><tr>${dayNames.map(d=>`<th>${d}</th>`).join('')}</tr></thead>
    <tbody><tr>`;

  // Leading empty cells
  let dow = firstDay; // 0=Sun
  for (let i = 0; i < dow; i++) html += '<td></td>';

  let col = dow;
  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const isToday = today.getFullYear()===year && today.getMonth()===month && today.getDate()===d;
    const hasAppt = apptDates.has(dateStr);
    const cls     = [isToday?'today':'', hasAppt?'has-appt':''].join(' ').trim();

    html += `<td><span class="day-num ${cls}" onclick="goToDate('${dateStr}')">${d}</span></td>`;
    col++;
    if (col % 7 === 0 && d < daysInMonth) html += '</tr><tr>';
  }

  // Trailing empty cells
  while (col % 7 !== 0) { html += '<td></td>'; col++; }
  html += '</tr></tbody></table>';

  const container = document.getElementById('miniCalContainer');
  if (container) container.innerHTML = html;

  // Update header
  const hdr = document.querySelector('#miniCalContainer')?.closest('.hs-card')?.querySelector('.card-title');
  if (hdr) hdr.innerHTML = '<i class="fas fa-calendar"></i> ' + monthNames[month] + ' ' + year;
}

function goToDate(dateStr) {
  calendar.gotoDate(dateStr);
}

document.getElementById('miniPrev').addEventListener('click', () => {
  miniCalDate.setMonth(miniCalDate.getMonth() - 1);
  renderMiniCal();
  calendar.prev();
});
document.getElementById('miniNext').addEventListener('click', () => {
  miniCalDate.setMonth(miniCalDate.getMonth() + 1);
  renderMiniCal();
  calendar.next();
});

// ── Helpers ────────────────────────────────────────────────────────
function formatDateLabel(d) {
  if (!d) return '';
  const dt = new Date(d + 'T00:00:00');
  return dt.toLocaleDateString('en-GB', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
}

function updateTimeSlots() {
  selectedTime = '';
  document.querySelectorAll('.time-slot').forEach(b => b.classList.remove('selected'));
  document.getElementById('step2Next').disabled = true;
  document.getElementById('step2Next').style.opacity = '.5';
}

// Re-render mini cal after calendar renders events
setTimeout(renderMiniCal, 1500);
</script>
</body>
</html>
