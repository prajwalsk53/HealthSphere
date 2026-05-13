<?php
/**
 * Returns available appointment slots for a doctor on a given date
 * GET ?doctor_id=X&date=YYYY-MM-DD
 */
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

// Allow patient and doctor roles
if (!isLoggedIn()) { echo json_encode(['error'=>'Not logged in']); exit; }

$doctorId = (int)($_GET['doctor_id'] ?? 0);
$date     = $_GET['date'] ?? '';

if (!$doctorId || !$date || !strtotime($date)) {
    echo json_encode(['slots' => [], 'error' => 'Invalid parameters']);
    exit;
}

// Ensure availability table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS doctor_availability (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    doctor_id      INT NOT NULL,
    day_of_week    TINYINT NOT NULL COMMENT '0=Sun,1=Mon,...,6=Sat',
    start_time     TIME NOT NULL,
    end_time       TIME NOT NULL,
    slot_duration  INT DEFAULT 30 COMMENT 'minutes',
    is_active      TINYINT(1) DEFAULT 1,
    UNIQUE KEY uq_doc_day (doctor_id, day_of_week),
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
)");

$dow = (int)date('w', strtotime($date)); // 0=Sun ... 6=Sat

// Fetch doctor availability for this day
$avail = $pdo->prepare("SELECT * FROM doctor_availability WHERE doctor_id=? AND day_of_week=? AND is_active=1");
$avail->execute([$doctorId, $dow]);
$avail = $avail->fetch();

if (!$avail) {
    echo json_encode(['slots' => [], 'message' => 'Doctor is not available on this day']);
    exit;
}

// Generate all slots
$start    = strtotime($date . ' ' . $avail['start_time']);
$end      = strtotime($date . ' ' . $avail['end_time']);
$duration = (int)$avail['slot_duration'] * 60;

$allSlots = [];
for ($t = $start; $t < $end; $t += $duration) {
    $allSlots[] = date('H:i', $t);
}

// Fetch already booked slots for this doctor+date
$booked = $pdo->prepare("
    SELECT appointment_time FROM appointments
    WHERE doctor_id=? AND appointment_date=? AND status NOT IN ('cancelled','rejected')
");
$booked->execute([$doctorId, $date]);
$bookedTimes = array_column($booked->fetchAll(), 'appointment_time');
$bookedTimes = array_map(fn($t) => substr($t, 0, 5), $bookedTimes);

// Build response
$slots = [];
foreach ($allSlots as $slot) {
    $slots[] = [
        'time'      => $slot,
        'available' => !in_array($slot, $bookedTimes),
    ];
}

echo json_encode([
    'slots'     => $slots,
    'day'       => date('l', strtotime($date)),
    'start'     => $avail['start_time'],
    'end'       => $avail['end_time'],
    'duration'  => $avail['slot_duration'],
    'available' => count(array_filter($slots, fn($s) => $s['available'])),
]);
