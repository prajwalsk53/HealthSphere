<?php
require_once __DIR__ . '/core.php';
$user   = requireAuth();
$uid    = (int)$user['id'];
$action = $_GET['action'] ?? 'list';
$role   = $user['role'];

switch ($action) {

    case 'list': {
        $status = $_GET['status'] ?? null;
        $upcoming = $_GET['upcoming'] ?? null;
        if ($role === 'patient') {
            $where = "a.patient_id=?";
            $params = [$uid];
        } else {
            $where = "a.doctor_id=?";
            $params = [$uid];
        }
        if ($status) { $where .= " AND a.status=?"; $params[] = $status; }
        if ($upcoming) { $where .= " AND a.appointment_date>=CURDATE()"; }

        $stmt = $pdo->prepare("
            SELECT a.*,
                   p.first_name AS patient_first, p.last_name AS patient_last,
                   d.first_name AS doctor_first,  d.last_name AS doctor_last,
                   doc.specialization, doc.hospital_name
            FROM appointments a
            JOIN users p ON a.patient_id=p.id
            JOIN users d ON a.doctor_id=d.id
            LEFT JOIN doctors doc ON d.id=doc.user_id
            WHERE $where
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT 50
        ");
        $stmt->execute($params);
        ok($stmt->fetchAll());
        break;
    }

    case 'book': {
        if ($role !== 'patient') err('Only patients can book appointments', 403);
        $b = body();
        $doctorId = (int)($b['doctor_id'] ?? 0);
        $date     = $b['date'] ?? '';
        $time     = $b['time'] ?? '';
        $reason   = trim($b['reason'] ?? '');
        if (!$doctorId || !$date || !$time) err('doctor_id, date and time are required');

        // Check doctor exists
        $d = $pdo->prepare("SELECT u.id,u.first_name,u.last_name FROM users u JOIN doctors doc ON u.id=doc.user_id WHERE u.id=? AND u.role='doctor'");
        $d->execute([$doctorId]);
        $doctor = $d->fetch();
        if (!$doctor) err('Doctor not found', 404);

        // Check no clash
        $clash = $pdo->prepare("SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time=? AND status NOT IN ('cancelled','rejected')");
        $clash->execute([$doctorId, $date, $time]);
        if ($clash->fetch()) err('That time slot is already booked', 409);

        $stmt = $pdo->prepare("INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,reason,status) VALUES (?,?,?,?,?,'pending')");
        $stmt->execute([$uid, $doctorId, $date, $time, $reason]);
        ok(['id' => (int)$pdo->lastInsertId(), 'message' => 'Appointment booked'], 201);
        break;
    }

    case 'cancel': {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) err('id required');
        $col = $role === 'patient' ? 'patient_id' : 'doctor_id';
        $stmt = $pdo->prepare("UPDATE appointments SET status='cancelled' WHERE id=? AND $col=?");
        $stmt->execute([$id, $uid]);
        if (!$stmt->rowCount()) err('Appointment not found or not yours', 404);
        ok(['message' => 'Appointment cancelled']);
        break;
    }

    case 'doctors': {
        $spec = $_GET['specialization'] ?? null;
        $q    = $_GET['q'] ?? null;
        $where = "u.role='doctor' AND u.is_active=1";
        $params = [];
        if ($spec) { $where .= " AND doc.specialization=?"; $params[] = $spec; }
        if ($q) { $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR doc.specialization LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }

        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.email,
                   doc.specialization, doc.hospital_name, doc.experience_years,
                   doc.consultation_fee, doc.bio, doc.is_verified
            FROM users u JOIN doctors doc ON u.id=doc.user_id
            WHERE $where
            ORDER BY doc.is_verified DESC, u.first_name
            LIMIT 50
        ");
        $stmt->execute($params);
        ok($stmt->fetchAll());
        break;
    }

    case 'slots': {
        $doctorId = (int)($_GET['doctor_id'] ?? 0);
        $date = $_GET['date'] ?? date('Y-m-d');
        if (!$doctorId) err('doctor_id required');

        $booked = $pdo->prepare("SELECT appointment_time FROM appointments WHERE doctor_id=? AND appointment_date=? AND status NOT IN ('cancelled','rejected')");
        $booked->execute([$doctorId, $date]);
        $bookedTimes = array_column($booked->fetchAll(), 'appointment_time');

        $all = ['09:00','09:30','10:00','10:30','11:00','11:30','14:00','14:30','15:00','15:30','16:00','16:30'];
        $slots = array_map(fn($t) => ['time'=>$t,'available'=>!in_array($t, $bookedTimes)], $all);
        ok($slots);
        break;
    }

    default: err('Unknown action. Use: list | book | cancel | doctors | slots');
}
