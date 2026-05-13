<?php
require_once __DIR__ . '/../config/config.php';
requireRole('doctor');
$user = getCurrentUser(); $uid = $user['id'];

// Ensure availability table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS doctor_availability (
    id INT PRIMARY KEY AUTO_INCREMENT,
    doctor_id INT NOT NULL,
    day_of_week TINYINT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_duration INT DEFAULT 30,
    is_active TINYINT(1) DEFAULT 1,
    UNIQUE KEY uq_doc_day (doctor_id, day_of_week),
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
)");

// ── Save availability ──────────────────────────────────────────────
$saveMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_availability'])) {
    $days = $_POST['days'] ?? [];
    // Delete all current
    $pdo->prepare("DELETE FROM doctor_availability WHERE doctor_id=?")->execute([$uid]);
    $duration = max(15, min(60, (int)($_POST['slot_duration'] ?? 30)));
    foreach ([1,2,3,4,5,6,0] as $dow) {
        if (!isset($days[$dow])) continue;
        $start = $_POST['start'][$dow] ?? '09:00';
        $end   = $_POST['end'][$dow]   ?? '17:00';
        if ($start >= $end) continue;
        $pdo->prepare("INSERT INTO doctor_availability (doctor_id,day_of_week,start_time,end_time,slot_duration) VALUES (?,?,?,?,?)")
            ->execute([$uid, $dow, $start, $end, $duration]);
    }
    $saveMsg = 'Availability saved. Patients can now book slots based on your schedule.';
}

// Load current availability
$availRows = $pdo->prepare("SELECT * FROM doctor_availability WHERE doctor_id=? ORDER BY day_of_week");
$availRows->execute([$uid]);
$availMap = [];
foreach ($availRows->fetchAll() as $r) $availMap[$r['day_of_week']] = $r;

// Weekly schedule
$activeTab = $_GET['tab'] ?? 'availability';
$week = (int)($_GET['week'] ?? 0);
$startDate = date('Y-m-d', strtotime("monday this week +{$week} week"));
$endDate   = date('Y-m-d', strtotime("sunday this week +{$week} week"));
$appts = $pdo->prepare("SELECT a.*, u.first_name, u.last_name FROM appointments a JOIN users u ON a.patient_id=u.id WHERE a.doctor_id=? AND a.appointment_date BETWEEN ? AND ? ORDER BY a.appointment_date, a.appointment_time");
$appts->execute([$uid, $startDate, $endDate]); $schedule = $appts->fetchAll();
$byDate = [];
foreach ($schedule as $s) $byDate[$s['appointment_date']][] = $s;
$days = [];
for ($i=0;$i<5;$i++) $days[] = date('Y-m-d', strtotime($startDate." +$i day"));

$notifCount = getUnreadCount($pdo, $uid); $msgCount = getUnreadMessages($pdo, $uid);
$dayNames = [0=>'Sunday',1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday'];
$weekdayOrder = [1,2,3,4,5,6,0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Schedule — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.day-row { display:grid; grid-template-columns:140px 60px 1fr; gap:14px; align-items:center; padding:14px 0; border-bottom:1px solid var(--hs-border); }
.day-row:last-child { border:none; }
.day-toggle { display:flex; align-items:center; gap:8px; }
.toggle-switch { width:44px; height:24px; background:#e2e8f0; border-radius:12px; position:relative; cursor:pointer; transition:.2s; }
.toggle-switch.on { background:var(--hs-blue); }
.toggle-switch::after { content:''; position:absolute; width:18px; height:18px; background:#fff; border-radius:50%; top:3px; left:3px; transition:.2s; box-shadow:0 1px 4px rgba(0,0,0,.2); }
.toggle-switch.on::after { left:23px; }
.time-inputs { display:flex; align-items:center; gap:8px; }
.time-inputs input { padding:7px 10px; border:1.5px solid var(--hs-border); border-radius:7px; font-size:13px; font-family:inherit; width:90px; }
.time-inputs input:focus { border-color:var(--hs-blue); outline:none; }
.slot-badge { background:#EFF6FF; color:var(--hs-blue); padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">

  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-calendar-alt" style="color:var(--hs-blue);"></i> My Schedule</div>
      <div class="page-subtitle">Set your availability and view weekly appointments</div>
    </div>
    <div class="topbar-actions">
      <?php if ($activeTab === 'weekly'): ?>
      <a href="?tab=weekly&week=<?= $week-1 ?>" class="btn-hs btn-outline-hs btn-sm-hs"><i class="fas fa-chevron-left"></i></a>
      <span style="font-size:13px;font-weight:600;padding:0 8px;"><?= formatDate($startDate,'d M') ?> – <?= formatDate($endDate,'d M Y') ?></span>
      <a href="?tab=weekly&week=<?= $week+1 ?>" class="btn-hs btn-outline-hs btn-sm-hs"><i class="fas fa-chevron-right"></i></a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tabs -->
  <div style="padding:0 24px;background:#f1f5f9;border-bottom:1px solid #e2e8f0;">
    <div style="display:flex;gap:4px;padding:8px 0 0;">
      <a href="?tab=availability" style="padding:9px 20px;border-radius:8px 8px 0 0;font-size:13px;font-weight:600;text-decoration:none;<?= $activeTab==='availability'?'background:#fff;color:var(--hs-blue);border:1px solid #e2e8f0;border-bottom:none;':'color:var(--hs-muted);' ?>">
        <i class="fas fa-clock"></i> Availability Settings
      </a>
      <a href="?tab=weekly" style="padding:9px 20px;border-radius:8px 8px 0 0;font-size:13px;font-weight:600;text-decoration:none;<?= $activeTab==='weekly'?'background:#fff;color:var(--hs-blue);border:1px solid #e2e8f0;border-bottom:none;':'color:var(--hs-muted);' ?>">
        <i class="fas fa-calendar-week"></i> Weekly View
      </a>
    </div>
  </div>

  <div class="hs-content">

  <?php if ($activeTab === 'availability'): ?>

    <?php if ($saveMsg): ?>
    <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:10px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:8px;">
      <i class="fas fa-check-circle" style="color:#16A34A;"></i><span style="color:#15803D;font-weight:600;"><?= e($saveMsg) ?></span>
    </div>
    <?php endif; ?>

    <div class="hs-card">
      <div class="hs-card-header">
        <span class="card-title"><i class="fas fa-clock"></i> Weekly Availability</span>
        <span style="font-size:12px;color:var(--hs-muted);">Patients will only see slots you mark as available</span>
      </div>
      <div class="hs-card-body">
        <form method="POST" action="?tab=availability">
          <input type="hidden" name="save_availability" value="1">

          <!-- Slot duration -->
          <div style="background:#F4F8FF;border-radius:10px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div>
              <label style="font-size:12px;font-weight:700;color:var(--hs-navy);display:block;margin-bottom:5px;">Appointment Slot Duration</label>
              <select name="slot_duration" class="form-select" style="width:160px;font-size:13px;">
                <?php foreach ([15=>'15 min',20=>'20 min',30=>'30 min',45=>'45 min',60=>'1 hour'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= (!empty($availMap) && (int)(array_values($availMap)[0]['slot_duration']??30)===$v)?'selected':($v===30?'selected':'') ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div style="font-size:12px;color:var(--hs-muted);max-width:400px;">
              <i class="fas fa-info-circle"></i> All days use the same slot duration. Patients will see only available time slots when booking.
            </div>
          </div>

          <!-- Day rows -->
          <?php foreach ($weekdayOrder as $dow):
            $avail = $availMap[$dow] ?? null;
            $isOn  = $avail ? true : false;
            $start = $avail['start_time'] ?? '09:00';
            $end   = $avail['end_time']   ?? '17:00';
            $dur   = $avail['slot_duration'] ?? 30;
            // Calculate slot count
            $slotCount = $isOn ? (int)((strtotime("2000-01-01 {$end}") - strtotime("2000-01-01 {$start}")) / ($dur * 60)) : 0;
          ?>
          <div class="day-row">
            <div class="day-toggle">
              <div class="toggle-switch <?= $isOn?'on':'' ?>" id="toggle-<?= $dow ?>" onclick="toggleDay(<?= $dow ?>)"></div>
              <label style="font-weight:700;font-size:13px;color:var(--hs-navy);cursor:pointer;" onclick="toggleDay(<?= $dow ?>)">
                <?= $dayNames[$dow] ?>
              </label>
              <input type="checkbox" name="days[<?= $dow ?>]" id="daycheck-<?= $dow ?>" <?= $isOn?'checked':'' ?> value="1" style="display:none;">
            </div>
            <div>
              <?php if ($isOn): ?>
              <span class="slot-badge"><?= $slotCount ?> slots</span>
              <?php endif; ?>
            </div>
            <div id="times-<?= $dow ?>" class="time-inputs" style="<?= $isOn?'':'opacity:.35;pointer-events:none;' ?>">
              <span style="font-size:12px;color:var(--hs-muted);">From</span>
              <input type="time" name="start[<?= $dow ?>]" value="<?= $start ?>" onchange="updateSlots(<?= $dow ?>)">
              <span style="font-size:12px;color:var(--hs-muted);">To</span>
              <input type="time" name="end[<?= $dow ?>]" value="<?= $end ?>" onchange="updateSlots(<?= $dow ?>)">
              <span id="slotcount-<?= $dow ?>" class="slot-badge" style="<?= $isOn?'':'display:none;' ?>"><?= $slotCount ?> slots</span>
            </div>
          </div>
          <?php endforeach; ?>

          <div style="margin-top:20px;display:flex;gap:12px;align-items:center;">
            <button type="submit" class="btn-hs btn-primary-hs">
              <i class="fas fa-save"></i> Save Availability
            </button>
            <span style="font-size:12px;color:var(--hs-muted);">Changes take effect immediately for new bookings</span>
          </div>
        </form>
      </div>
    </div>

    <!-- Preview -->
    <?php if (!empty($availMap)): ?>
    <div class="hs-card" style="margin-top:16px;">
      <div class="hs-card-header"><span class="card-title"><i class="fas fa-eye"></i> What Patients See</span></div>
      <div class="hs-card-body">
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <?php foreach ($weekdayOrder as $dow):
            if (!isset($availMap[$dow])) continue;
            $a = $availMap[$dow];
            $cnt = (int)((strtotime("2000-01-01 {$a['end_time']}") - strtotime("2000-01-01 {$a['start_time']}")) / ($a['slot_duration']*60));
          ?>
          <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;padding:12px 16px;text-align:center;min-width:110px;">
            <div style="font-weight:700;color:var(--hs-navy);"><?= $dayNames[$dow] ?></div>
            <div style="font-size:12px;color:var(--hs-blue);"><?= substr($a['start_time'],0,5) ?> – <?= substr($a['end_time'],0,5) ?></div>
            <div style="font-size:11px;color:var(--hs-muted);margin-top:4px;"><?= $cnt ?> × <?= $a['slot_duration'] ?>min slots</div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

  <?php else: /* Weekly view */ ?>

    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;">
      <?php foreach ($days as $day):
        $dow = (int)date('w', strtotime($day));
        $hasAvail = isset($availMap[$dow]);
      ?>
      <div class="hs-card">
        <div class="hs-card-header" style="background:<?= $day===date('Y-m-d')?'var(--hs-blue)':'var(--hs-off-white)' ?>;border-radius:11px 11px 0 0;">
          <span class="card-title" style="color:<?= $day===date('Y-m-d')?'#fff':'var(--hs-navy)' ?>;">
            <?= date('D', strtotime($day)) ?><br>
            <span style="font-size:20px;font-weight:900;"><?= date('d', strtotime($day)) ?></span>
          </span>
          <?php if (!empty($byDate[$day])): ?>
          <span class="badge bg-<?= $day===date('Y-m-d')?'warning text-dark':'primary' ?>"><?= count($byDate[$day]) ?></span>
          <?php endif; ?>
        </div>
        <div class="hs-card-body p-0">
          <?php if ($hasAvail): ?>
          <div style="padding:6px 12px;background:#EFF6FF;font-size:10px;color:var(--hs-blue);font-weight:700;border-bottom:1px solid var(--hs-border);">
            <i class="fas fa-clock"></i> <?= substr($availMap[$dow]['start_time'],0,5) ?>–<?= substr($availMap[$dow]['end_time'],0,5) ?>
          </div>
          <?php else: ?>
          <div style="padding:6px 12px;background:#FEF2F2;font-size:10px;color:#DC2626;font-weight:700;border-bottom:1px solid var(--hs-border);">
            <i class="fas fa-times"></i> Not available
          </div>
          <?php endif; ?>
          <?php if (!empty($byDate[$day])): ?>
            <?php foreach ($byDate[$day] as $a): ?>
            <div style="padding:10px 12px;border-bottom:1px solid var(--hs-border);">
              <div style="font-size:12px;font-weight:700;color:var(--hs-blue);"><?= date('H:i',strtotime($a['appointment_time'])) ?></div>
              <div style="font-size:13px;font-weight:600;color:var(--hs-navy);"><?= e($a['first_name'].' '.$a['last_name']) ?></div>
              <div style="font-size:11px;color:var(--hs-muted);"><?= e($a['reason'] ?: 'General') ?></div>
              <div style="margin-top:6px;"><?= getStatusBadge($a['status']) ?></div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
          <div style="padding:20px;text-align:center;color:var(--hs-muted);font-size:12px;">
            <i class="fas fa-calendar" style="opacity:.3;font-size:20px;"></i><br>No appointments
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function toggleDay(dow) {
  const cb     = document.getElementById('daycheck-'+dow);
  const toggle = document.getElementById('toggle-'+dow);
  const times  = document.getElementById('times-'+dow);
  cb.checked   = !cb.checked;
  toggle.classList.toggle('on', cb.checked);
  times.style.opacity       = cb.checked ? '1' : '0.35';
  times.style.pointerEvents = cb.checked ? 'auto' : 'none';
  document.getElementById('slotcount-'+dow).style.display = cb.checked ? '' : 'none';
  if (cb.checked) updateSlots(dow);
}
function updateSlots(dow) {
  const dur = parseInt(document.querySelector('[name="slot_duration"]').value);
  const s = document.querySelector(`[name="start[${dow}]"]`)?.value;
  const e = document.querySelector(`[name="end[${dow}]"]`)?.value;
  if (!s || !e) return;
  const diffMin = (new Date('2000-01-01T'+e) - new Date('2000-01-01T'+s)) / 60000;
  const cnt = Math.max(0, Math.floor(diffMin / dur));
  const el = document.getElementById('slotcount-'+dow);
  if (el) el.textContent = cnt + ' slots';
}
document.querySelector('[name="slot_duration"]')?.addEventListener('change', () => {
  [0,1,2,3,4,5,6].forEach(d => {
    if (document.getElementById('daycheck-'+d)?.checked) updateSlots(d);
  });
});
</script>
</body>
</html>
