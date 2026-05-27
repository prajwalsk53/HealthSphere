<?php
/**
 * Import Google Fit Takeout daily activity metrics into health_metrics.
 */
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
header('Content-Type: application/json');

$uid = (int)$_SESSION['user_id'];

function takeoutValue(array $row, string $key): ?float {
    if (!array_key_exists($key, $row)) return null;
    $value = trim((string)$row[$key]);
    if ($value === '' || !is_numeric($value)) return null;
    return (float)$value;
}

function takeoutInt(array $row, string $key): ?int {
    $value = takeoutValue($row, $key);
    return $value === null ? null : (int)round($value);
}

function readTakeoutCsvFromZip(string $zipPath): array {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive extension is not enabled.');
    }
    if (!is_file($zipPath)) {
        throw new RuntimeException('Takeout zip was not found at: ' . $zipPath);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('Could not open the Takeout zip file.');
    }

    $csvName = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $target = 'Fit/Daily activity metrics/Daily activity metrics.csv';
        if (substr($name, -strlen($target)) === $target) {
            $csvName = $name;
            break;
        }
    }
    if (!$csvName) {
        $zip->close();
        throw new RuntimeException('Daily activity metrics CSV was not found inside the Takeout zip.');
    }

    $csv = $zip->getFromName($csvName);
    $zip->close();
    if ($csv === false || trim($csv) === '') {
        throw new RuntimeException('Daily activity metrics CSV is empty or unreadable.');
    }

    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $csv);
    rewind($fh);

    $header = fgetcsv($fh);
    if (!$header) {
        fclose($fh);
        throw new RuntimeException('Daily activity metrics CSV has no header row.');
    }

    $rows = [];
    while (($values = fgetcsv($fh)) !== false) {
        if (count($values) < count($header)) {
            $values = array_pad($values, count($header), '');
        }
        $row = array_combine($header, array_slice($values, 0, count($header)));
        if (!$row || empty($row['Date'])) continue;

        $date = trim($row['Date']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;

        $rows[$date] = [
            'metric_date'     => $date,
            'heart_rate'      => takeoutInt($row, 'Average heart rate (bpm)'),
            'steps_count'     => takeoutInt($row, 'Step count') ?? 0,
            'distance_km'     => ($distance = takeoutValue($row, 'Distance (m)')) === null ? 0 : round($distance / 1000, 2),
            'calories_burned' => ($calories = takeoutValue($row, 'Calories (kcal)')) === null ? 0 : round($calories, 2),
            'weight_kg'       => ($weight = takeoutValue($row, 'Average weight (kg)')) === null ? null : round($weight, 2),
        ];
    }
    fclose($fh);

    krsort($rows);
    return $rows;
}

try {
    $zipPath = '';
    if (!empty($_FILES['takeout_zip']) && is_array($_FILES['takeout_zip'])) {
        if ($_FILES['takeout_zip']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Takeout ZIP upload failed. Please choose the file again.');
        }
        $originalName = $_FILES['takeout_zip']['name'] ?? '';
        $tmpName = $_FILES['takeout_zip']['tmp_name'] ?? '';
        $size = (int)($_FILES['takeout_zip']['size'] ?? 0);
        if ($size <= 0 || $size > 100 * 1024 * 1024) {
            throw new RuntimeException('Takeout ZIP must be smaller than 100 MB.');
        }
        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip') {
            throw new RuntimeException('Please upload the Google Takeout .zip file.');
        }
        if (!is_uploaded_file($tmpName)) {
            throw new RuntimeException('Uploaded Takeout file could not be verified.');
        }
        $zipPath = $tmpName;
    } elseif (defined('IS_LOCAL') && IS_LOCAL) {
        $zipPath = 'C:\\Users\\DELL\\Downloads\\takeout-20260523T084758Z-3-001.zip';
    } else {
        throw new RuntimeException('Please upload your Google Takeout ZIP file.');
    }

    $rows = readTakeoutCsvFromZip($zipPath);

    if (!$rows) {
        echo json_encode(['success' => false, 'error' => 'No daily activity rows were found in this Takeout file.']);
        exit;
    }

    $dates = array_keys($rows);
    $placeholders = implode(',', array_fill(0, count($dates), '?'));
    $pdo->prepare("DELETE FROM health_metrics WHERE patient_id=? AND source='wearable' AND metric_date IN ($placeholders)")
        ->execute(array_merge([$uid], $dates));

    $stmt = $pdo->prepare("
        INSERT INTO health_metrics
            (patient_id, metric_date, heart_rate, steps_count, distance_km, calories_burned, weight_kg, source)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'wearable')
    ");

    $inserted = 0;
    foreach ($rows as $row) {
        $stmt->execute([
            $uid,
            $row['metric_date'],
            $row['heart_rate'],
            $row['steps_count'],
            $row['distance_km'],
            $row['calories_burned'],
            $row['weight_kg'],
        ]);
        $inserted++;
    }

    echo json_encode([
        'success' => true,
        'imported' => $inserted,
        'latest_date' => $dates[0],
        'earliest_date' => end($dates),
        'message' => "Imported {$inserted} days of Google Fit Takeout data",
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
