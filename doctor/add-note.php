<?php
require_once __DIR__ . '/../config/config.php';
requireRole('doctor');
$uid = $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)$_POST['patient_id'];
    $note = trim($_POST['note'] ?? '');
    if ($patientId && $note) {
        $pdo->prepare("INSERT INTO clinical_notes (patient_id,doctor_id,note_text,note_type) VALUES (?,?,?,'general')")->execute([$patientId, $uid, $note]);
    }
    header("Location: patients.php?id=$patientId");
}
header("Location: patients.php");
