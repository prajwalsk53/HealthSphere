<?php
require_once __DIR__ . '/core.php';
$user   = requireAuth();
$uid    = (int)$user['id'];
$action = $_GET['action'] ?? 'today';

switch ($action) {

    case 'today': {
        $stmt = $pdo->prepare("SELECT * FROM diet_logs WHERE patient_id=? AND log_date=CURDATE() ORDER BY created_at DESC");
        $stmt->execute([$uid]);
        $logs = $stmt->fetchAll();
        $total = array_sum(array_column($logs, 'calories'));
        ok(['logs' => $logs, 'total_calories' => (int)$total, 'goal' => 2500]);
        break;
    }

    case 'list': {
        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
        $to   = $_GET['to']   ?? date('Y-m-d');
        $stmt = $pdo->prepare("SELECT * FROM diet_logs WHERE patient_id=? AND log_date BETWEEN ? AND ? ORDER BY log_date DESC, created_at DESC");
        $stmt->execute([$uid, $from, $to]);
        ok($stmt->fetchAll());
        break;
    }

    case 'add': {
        $b = body();
        $stmt = $pdo->prepare("INSERT INTO diet_logs (patient_id,log_date,meal_type,food_name,calories,protein,carbs,fats) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $uid,
            $b['log_date']  ?? date('Y-m-d'),
            $b['meal_type'] ?? 'snack',
            $b['food_name'] ?? '',
            $b['calories']  ?? 0,
            $b['protein']   ?? null,
            $b['carbs']     ?? null,
            $b['fats']      ?? null,
        ]);
        ok(['id' => (int)$pdo->lastInsertId(), 'message' => 'Logged'], 201);
        break;
    }

    case 'delete': {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) err('id required');
        $stmt = $pdo->prepare("DELETE FROM diet_logs WHERE id=? AND patient_id=?");
        $stmt->execute([$id, $uid]);
        if (!$stmt->rowCount()) err('Entry not found', 404);
        ok(['message' => 'Deleted']);
        break;
    }

    case 'summary': {
        $stmt = $pdo->prepare("
            SELECT log_date, SUM(calories) AS total_cal, SUM(protein) AS total_protein,
                   SUM(carbs) AS total_carbs, SUM(fats) AS total_fat
            FROM diet_logs WHERE patient_id=? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY log_date ORDER BY log_date ASC
        ");
        $stmt->execute([$uid]);
        ok($stmt->fetchAll());
        break;
    }

    default: err('Unknown action. Use: today | list | add | delete | summary');
}
