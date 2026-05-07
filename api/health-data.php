<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();
header('Content-Type: application/json');

$uid    = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? '';

switch ($action) {

    case 'weekly_metrics':
        $stmt = $pdo->prepare("
            SELECT metric_date, heart_rate, blood_pressure_systolic, blood_pressure_diastolic,
                   spo2, steps_count, sleep_hours, calories_burned
            FROM health_metrics WHERE patient_id=?
            ORDER BY metric_date DESC LIMIT 14
        ");
        $stmt->execute([$uid]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'compare':
        // This week vs last week averages
        $stmt = $pdo->prepare("
            SELECT
              AVG(CASE WHEN metric_date >= DATE_SUB(CURDATE(),INTERVAL 7 DAY) THEN heart_rate END) hr_w1,
              AVG(CASE WHEN metric_date BETWEEN DATE_SUB(CURDATE(),INTERVAL 14 DAY) AND DATE_SUB(CURDATE(),INTERVAL 7 DAY) THEN heart_rate END) hr_w2,
              AVG(CASE WHEN metric_date >= DATE_SUB(CURDATE(),INTERVAL 7 DAY) THEN blood_pressure_systolic END) bp_w1,
              AVG(CASE WHEN metric_date BETWEEN DATE_SUB(CURDATE(),INTERVAL 14 DAY) AND DATE_SUB(CURDATE(),INTERVAL 7 DAY) THEN blood_pressure_systolic END) bp_w2,
              AVG(CASE WHEN metric_date >= DATE_SUB(CURDATE(),INTERVAL 7 DAY) THEN steps_count END) steps_w1,
              AVG(CASE WHEN metric_date BETWEEN DATE_SUB(CURDATE(),INTERVAL 14 DAY) AND DATE_SUB(CURDATE(),INTERVAL 7 DAY) THEN steps_count END) steps_w2,
              AVG(CASE WHEN metric_date >= DATE_SUB(CURDATE(),INTERVAL 7 DAY) THEN sleep_hours END) sleep_w1,
              AVG(CASE WHEN metric_date BETWEEN DATE_SUB(CURDATE(),INTERVAL 14 DAY) AND DATE_SUB(CURDATE(),INTERVAL 7 DAY) THEN sleep_hours END) sleep_w2
            FROM health_metrics WHERE patient_id=?
        ");
        $stmt->execute([$uid]);
        echo json_encode($stmt->fetch());
        break;

    case 'diet_weekly':
        $stmt = $pdo->prepare("
            SELECT log_date,
                   SUM(calories) cal,
                   SUM(protein) protein,
                   SUM(carbs) carbs,
                   SUM(fats) fats
            FROM diet_logs WHERE patient_id=?
            AND log_date >= DATE_SUB(CURDATE(),INTERVAL 7 DAY)
            GROUP BY log_date ORDER BY log_date
        ");
        $stmt->execute([$uid]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'unread_count':
        $msgs   = getUnreadMessages($pdo, $uid);
        $notifs = getUnreadCount($pdo, $uid);
        echo json_encode(['messages' => $msgs, 'notifications' => $notifs]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
