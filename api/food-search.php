<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');

if (strlen($q) < 1 && !$category) {
    // Return popular/top-rated foods when no query
    $stmt = $pdo->query("SELECT id, food_name, category, calories_per_100g, protein_g, sugar_g, fats_g, fiber_g, health_rating, portion_size FROM food_database ORDER BY FIELD(health_rating,'excellent','good','moderate','poor'), food_name LIMIT 20");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

$params = [];
$where  = [];

if ($q !== '') {
    $where[]  = "(food_name LIKE ? OR category LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($category !== '') {
    $where[]  = "category = ?";
    $params[] = $category;
}

$sql  = "SELECT id, food_name, category, calories_per_100g, protein_g, sugar_g, fats_g, fiber_g, health_rating, portion_size FROM food_database";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY FIELD(health_rating,'excellent','good','moderate','poor'), food_name LIMIT 12";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
