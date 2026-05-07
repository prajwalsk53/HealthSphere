<?php
require_once __DIR__ . '/core.php';
$user = requireAuth();
$uid  = (int)$user['id'];

$latest = $pdo->prepare("SELECT * FROM health_metrics WHERE patient_id=? ORDER BY metric_date DESC LIMIT 1");
$latest->execute([$uid]); $latest = $latest->fetch() ?: [];

$upcomingAppts = $pdo->prepare("
    SELECT a.id,a.appointment_date,a.appointment_time,a.reason,a.status,
           u.first_name,u.last_name,d.specialization,d.hospital_name
    FROM appointments a JOIN users u ON a.doctor_id=u.id LEFT JOIN doctors d ON u.id=d.user_id
    WHERE a.patient_id=? AND a.appointment_date>=CURDATE() AND a.status!='cancelled'
    ORDER BY a.appointment_date,a.appointment_time LIMIT 3
");
$upcomingAppts->execute([$uid]); $upcoming = $upcomingAppts->fetchAll();

$todayCal = (float)$pdo->query("SELECT COALESCE(SUM(calories),0) FROM diet_logs WHERE patient_id=$uid AND log_date=CURDATE()")->fetchColumn();

$activeMeds = $pdo->prepare("SELECT medication_name,dosage,frequency FROM prescriptions WHERE patient_id=? AND is_active=1 LIMIT 4");
$activeMeds->execute([$uid]); $meds = $activeMeds->fetchAll();

$unreadNotifs = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id=$uid AND is_read=0")->fetchColumn();
$unreadMsgs   = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE receiver_id=$uid AND is_read=0")->fetchColumn();

// Last 7 days health trend
$trend = $pdo->prepare("SELECT metric_date,heart_rate,blood_pressure_systolic,steps_count,sleep_hours FROM health_metrics WHERE patient_id=? ORDER BY metric_date DESC LIMIT 7");
$trend->execute([$uid]); $trend = array_reverse($trend->fetchAll());

ok([
    'vitals' => [
        'heart_rate'   => (int)($latest['heart_rate']               ?? 0),
        'bp'           => ($latest['blood_pressure_systolic']??0).'/'.($latest['blood_pressure_diastolic']??0),
        'bp_systolic'  => (int)($latest['blood_pressure_systolic']  ?? 0),
        'bp_diastolic' => (int)($latest['blood_pressure_diastolic'] ?? 0),
        'spo2'         => (float)($latest['spo2']                   ?? 0),
        'steps'        => (int)($latest['steps_count']              ?? 0),
        'sleep'        => (float)($latest['sleep_hours']            ?? 0),
        'calories_burned'=> (int)($latest['calories_burned']        ?? 0),
        'temperature'  => (float)($latest['temperature']            ?? 0),
    ],
    'calories_today'   => round($todayCal),
    'calorie_goal'     => 2500,
    'upcoming_appointments' => $upcoming,
    'active_medications'    => $meds,
    'unread_notifications'  => $unreadNotifs,
    'unread_messages'       => $unreadMsgs,
    'health_trend'          => $trend,
]);
