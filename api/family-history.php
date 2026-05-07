<?php
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
$uid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $relation      = $_POST['relation'] ?? '';
    $relationName  = trim($_POST['relation_name'] ?? '');
    $condition     = trim($_POST['condition_name'] ?? '');
    $yearDiagnosed = (int)($_POST['year_diagnosed'] ?? 0) ?: null;
    $yearDeceased  = (int)($_POST['year_deceased'] ?? 0) ?: null;

    $validRelations = ['father','mother','brother','sister','grandfather','grandmother',
        'grandfather_paternal','grandmother_paternal','grandfather_maternal','grandmother_maternal',
        'uncle_paternal','aunt_paternal','uncle_maternal','aunt_maternal',
        'cousin_paternal','cousin_maternal','other'];
    $notes = trim($_POST['notes'] ?? '');
    if ($relation && $condition && in_array($relation, $validRelations)) {
        $pdo->prepare("INSERT INTO family_history (patient_id,relation,relation_name,condition_name,year_diagnosed,year_deceased,notes) VALUES (?,?,?,?,?,?,?)")
            ->execute([$uid, $relation, $relationName ?: null, $condition, $yearDiagnosed, $yearDeceased, $notes ?: null]);
    }
}
header('Location: /HealthSphere/patient/medical-records.php?tab=family');
exit;
