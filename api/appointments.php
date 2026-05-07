<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';
requireLogin();

header('Content-Type: application/json');
$uid    = (int)$_SESSION['user_id'];
$role   = $_SESSION['user_role'];
$action = $_GET['action'] ?? 'calendar';

$statusColors = [
    'confirmed'  => '#1565C0',
    'upcoming'   => '#1565C0',
    'pending'    => '#D97706',
    'arrived'    => '#0891B2',
    'waiting'    => '#7C3AED',
    'completed'  => '#16A34A',
    'cancelled'  => '#94A3B8',
    'late'       => '#DC2626',
];

switch ($action) {

    // ── Return appointments as FullCalendar events ─────────────────
    case 'calendar':
        if ($role === 'patient') {
            $stmt = $pdo->prepare("
                SELECT a.id, a.appointment_date, a.appointment_time, a.reason, a.status,
                       u.first_name, u.last_name, d.specialization, d.hospital_name
                FROM appointments a
                JOIN users u ON a.doctor_id = u.id
                LEFT JOIN doctors d ON u.id = d.user_id
                WHERE a.patient_id = ?
                ORDER BY a.appointment_date, a.appointment_time
            ");
            $stmt->execute([$uid]);
        } elseif ($role === 'doctor') {
            $stmt = $pdo->prepare("
                SELECT a.id, a.appointment_date, a.appointment_time, a.reason, a.status,
                       u.first_name, u.last_name, '' as specialization, '' as hospital_name
                FROM appointments a
                JOIN users u ON a.patient_id = u.id
                WHERE a.doctor_id = ?
                ORDER BY a.appointment_date, a.appointment_time
            ");
            $stmt->execute([$uid]);
        } else {
            echo json_encode([]); exit;
        }

        $rows   = $stmt->fetchAll();
        $events = [];

        foreach ($rows as $row) {
            $start   = $row['appointment_date'] . 'T' . $row['appointment_time'];
            // Duration: 30 min default
            $endTime = date('H:i:s', strtotime($row['appointment_time']) + 1800);
            $end     = $row['appointment_date'] . 'T' . $endTime;
            $color   = $statusColors[$row['status']] ?? '#1565C0';

            if ($role === 'patient') {
                $title = 'Dr. ' . $row['first_name'] . ' ' . $row['last_name'];
                $sub   = $row['specialization'] ?? 'General Practice';
            } else {
                $title = $row['first_name'] . ' ' . $row['last_name'];
                $sub   = $row['reason'] ?: 'General';
            }

            $events[] = [
                'id'             => $row['id'],
                'title'          => $title,
                'start'          => $start,
                'end'            => $end,
                'backgroundColor'=> $color,
                'borderColor'    => $color,
                'textColor'      => '#fff',
                'extendedProps'  => [
                    'doctor'     => ($role === 'patient') ? 'Dr. '.$row['first_name'].' '.$row['last_name'] : '',
                    'patient'    => ($role === 'doctor')  ? $row['first_name'].' '.$row['last_name'] : '',
                    'sub'        => $sub,
                    'status'     => $row['status'],
                    'reason'     => $row['reason'] ?: 'General consultation',
                    'hospital'   => $row['hospital_name'] ?? '',
                    'time'       => date('H:i', strtotime($row['appointment_time'])),
                    'date'       => $row['appointment_date'],
                ],
            ];
        }

        // Doctor availability background events (Mon–Fri 09:00–17:00)
        if ($role === 'patient') {
            $start_range = date('Y-m-d', strtotime('first day of this month'));
            $end_range   = date('Y-m-d', strtotime('last day of next month'));
            $dt = new DateTime($start_range);
            $end_dt = new DateTime($end_range);
            while ($dt <= $end_dt) {
                $dow = (int)$dt->format('N'); // 1=Mon, 7=Sun
                if ($dow <= 5) {
                    $events[] = [
                        'start'           => $dt->format('Y-m-d') . 'T09:00:00',
                        'end'             => $dt->format('Y-m-d') . 'T17:00:00',
                        'display'         => 'background',
                        'backgroundColor' => 'rgba(21,101,192,0.04)',
                        'classNames'      => ['fc-avail'],
                    ];
                }
                $dt->modify('+1 day');
            }
        }

        echo json_encode($events);
        break;

    // ── Move appointment (drag & drop) ────────────────────────────
    case 'move':
        $data  = json_decode(file_get_contents('php://input'), true) ?? [];
        $apptId = (int)($data['id'] ?? 0);
        $newDate = $data['date'] ?? '';
        $newTime = $data['time'] ?? '';

        if (!$apptId || !$newDate) {
            echo json_encode(['ok' => false, 'error' => 'Missing data']);
            break;
        }

        // Verify ownership
        $col = $role === 'patient' ? 'patient_id' : 'doctor_id';
        $check = $pdo->prepare("SELECT id FROM appointments WHERE id=? AND $col=?");
        $check->execute([$apptId, $uid]);
        if (!$check->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Not authorised']);
            break;
        }

        $upd = $pdo->prepare("UPDATE appointments SET appointment_date=?, appointment_time=?, status='confirmed', updated_at=NOW() WHERE id=?");
        $upd->execute([$newDate, $newTime ?: '09:00:00', $apptId]);

        // Notification
        $pdo->prepare("INSERT INTO notifications (user_id,title,message,notification_type) VALUES (?,'Appointment Rescheduled','Your appointment has been moved to ".date('d M Y',strtotime($newDate))."','appointment')")->execute([$uid]);

        echo json_encode(['ok' => true]);
        break;

    // ── Cancel appointment ─────────────────────────────────────────
    case 'cancel':
        $data   = json_decode(file_get_contents('php://input'), true) ?? [];
        $apptId = (int)($data['id'] ?? 0);
        $col    = $role === 'patient' ? 'patient_id' : 'doctor_id';
        $check  = $pdo->prepare("SELECT id FROM appointments WHERE id=? AND $col=?");
        $check->execute([$apptId, $uid]);
        if (!$check->fetch()) { echo json_encode(['ok'=>false,'error'=>'Not authorised']); break; }
        $pdo->prepare("UPDATE appointments SET status='cancelled' WHERE id=?")->execute([$apptId]);
        echo json_encode(['ok' => true]);
        break;

    // ── Quick-book a new appointment ───────────────────────────────
    case 'book':
        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $doctorId = (int)($data['doctor_id'] ?? 0);
        $date     = $data['date'] ?? '';
        $time     = $data['time'] ?? '09:00:00';
        $reason   = trim($data['reason'] ?? '');

        if (!$doctorId || !$date) { echo json_encode(['ok'=>false,'error'=>'Missing data']); break; }
        if ($role !== 'patient')  { echo json_encode(['ok'=>false,'error'=>'Patients only']); break; }

        $stmt = $pdo->prepare("INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,reason,status) VALUES (?,?,?,?,?,'confirmed')");
        $stmt->execute([$uid, $doctorId, $date, $time, $reason]);
        $newId = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO notifications (user_id,title,message,notification_type) VALUES (?,'Appointment Booked','Your appointment on ".date('d M Y',strtotime($date))." has been confirmed.','appointment')")->execute([$uid]);

        // Send confirmation emails
        $pat = $pdo->prepare("SELECT first_name,last_name,email FROM users WHERE id=?"); $pat->execute([$uid]); $pat=$pat->fetch();
        $doc = $pdo->prepare("SELECT u.first_name,u.last_name,u.email,u.nhs_id,d.hospital_name FROM users u LEFT JOIN doctors d ON u.id=d.user_id WHERE u.id=?"); $doc->execute([$doctorId]); $doc=$doc->fetch();
        if ($pat && $doc) {
            $fDate = date('l, d F Y', strtotime($date));
            $fTime = date('H:i', strtotime($time));
            @mailAppointmentPatient($pat['email'],$pat['first_name'].' '.$pat['last_name'],$doc['first_name'].' '.$doc['last_name'],$fDate,$fTime,$reason??'',$doc['hospital_name']??'HealthSphere Clinic');
            @mailAppointmentDoctor($doc['email'],$doc['first_name'].' '.$doc['last_name'],$pat['first_name'].' '.$pat['last_name'],$pat['nhs_id']??'',$fDate,$fTime,$reason??'');
        }
        echo json_encode(['ok'=>true,'id'=>$newId]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
