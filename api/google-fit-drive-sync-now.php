<?php
/**
 * Sync Now — pulls the latest Google Fit Takeout ZIP from Google Drive
 * and imports the daily activity metrics into health_metrics.
 *
 * Requirements (set in config/secrets.php):
 *   GOOGLE_DRIVE_API_KEY          — Drive API key (Drive API enabled in Cloud Console)
 *   GOOGLE_DRIVE_TAKEOUT_FOLDER_ID — ID of the Drive folder containing takeout ZIPs
 *   The folder/files must be shared "Anyone with the link" (read) so the API key can access them.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/fitness.php';
requireRole('patient');
header('Content-Type: application/json');

$uid = (int)$_SESSION['user_id'];

// ── Config check ────────────────────────────────────────────────────
$apiKey   = GOOGLE_DRIVE_API_KEY;
$folderId = GOOGLE_DRIVE_TAKEOUT_FOLDER_ID;

if (!$apiKey || !$folderId) {
    echo json_encode(['success' => false, 'error' => 'Google Drive sync is not configured. Set GOOGLE_DRIVE_API_KEY and GOOGLE_DRIVE_TAKEOUT_FOLDER_ID in config/secrets.php.']);
    exit;
}

// ── Helpers ─────────────────────────────────────────────────────────
function driveApiGet(string $url): array {
    $ctx = stream_context_create(['http' => [
        'timeout'       => 15,
        'ignore_errors' => true,
        'header'        => "User-Agent: HealthSphere/1.0\r\n",
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) throw new RuntimeException('Could not reach Google Drive API.');
    $json = json_decode($raw, true);
    if (!is_array($json)) throw new RuntimeException('Invalid response from Google Drive API.');
    if (!empty($json['error']['message'])) throw new RuntimeException('Drive API: ' . $json['error']['message']);
    return $json;
}

function driveDownloadZip(string $fileId, string $apiKey, int $maxBytes): string {
    $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?alt=media&key=' . rawurlencode($apiKey);
    $tmp = tempnam(sys_get_temp_dir(), 'hs_fit_');
    if (!$tmp) throw new RuntimeException('Could not create temp file.');
    $in  = @fopen($url, 'rb');
    if (!$in) { @unlink($tmp); throw new RuntimeException('Could not download ZIP from Google Drive. Ensure the file is shared "Anyone with the link".'); }
    $out = fopen($tmp, 'wb');
    $bytes = 0;
    while (!feof($in)) {
        $chunk = fread($in, 512 * 1024);
        if ($chunk === false) break;
        $bytes += strlen($chunk);
        if ($bytes > $maxBytes) {
            fclose($in); fclose($out); @unlink($tmp);
            throw new RuntimeException('Takeout ZIP exceeds the 100 MB limit.');
        }
        fwrite($out, $chunk);
    }
    fclose($in); fclose($out);
    if ($bytes < 1000) { @unlink($tmp); throw new RuntimeException('Downloaded file is too small — check sharing permissions on the Drive folder.'); }
    return $tmp;
}

function parseTakeoutZip(string $zipPath): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('PHP ZipArchive extension is required.');
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) throw new RuntimeException('Could not open the Takeout ZIP.');

    $csvName = null;
    $target  = 'Daily activity metrics.csv';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $n = $zip->getNameIndex($i);
        if (str_ends_with($n, $target)) { $csvName = $n; break; }
    }
    if (!$csvName) { $zip->close(); throw new RuntimeException('Daily activity metrics CSV not found inside the Takeout ZIP.'); }

    $csv = $zip->getFromName($csvName);
    $zip->close();
    if (!$csv || !trim($csv)) throw new RuntimeException('Daily activity metrics CSV is empty.');

    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $csv); rewind($fh);
    $header = fgetcsv($fh);
    if (!$header) { fclose($fh); throw new RuntimeException('CSV has no header row.'); }

    $val = fn($row, $key) => isset($row[$key]) && is_numeric(trim($row[$key])) ? (float)trim($row[$key]) : null;
    $rows = [];
    while (($values = fgetcsv($fh)) !== false) {
        $row = array_combine($header, array_slice(array_pad($values, count($header), ''), 0, count($header)));
        if (!$row || empty($row['Date'])) continue;
        $date = trim($row['Date']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
        $dist = $val($row, 'Distance (m)');
        $rows[$date] = [
            'metric_date'     => $date,
            'heart_rate'      => ($v = $val($row, 'Average heart rate (bpm)')) === null ? null : (int)round($v),
            'steps_count'     => (int)round($val($row, 'Step count') ?? 0),
            'distance_km'     => $dist === null ? 0 : round($dist / 1000, 2),
            'calories_burned' => ($v = $val($row, 'Calories (kcal)')) === null ? 0 : round($v, 2),
            'weight_kg'       => ($v = $val($row, 'Average weight (kg)')) === null ? null : round($v, 2),
        ];
    }
    fclose($fh);
    krsort($rows);
    return $rows;
}

// ── Ensure tracking table exists ─────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS google_fit_drive_imports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    drive_file_id VARCHAR(200) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    modified_time VARCHAR(50) NULL,
    imported_rows INT DEFAULT 0,
    latest_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_patient_file (patient_id, drive_file_id)
)");

try {
    // ── 1. List ZIPs in the Drive folder (newest first) ──────────────
    $q = sprintf("'%s' in parents and trashed=false and mimeType='application/zip' and name contains 'takeout-'",
        str_replace("'", "\\'", $folderId));
    $listUrl = 'https://www.googleapis.com/drive/v3/files?' . http_build_query([
        'q'       => $q,
        'fields'  => 'files(id,name,modifiedTime,size)',
        'orderBy' => 'modifiedTime desc',
        'pageSize'=> 10,
        'key'     => $apiKey,
    ]);

    $listed = driveApiGet($listUrl);
    $files  = $listed['files'] ?? [];

    if (!$files) {
        echo json_encode(['success' => false, 'error' => 'No Takeout ZIPs found in the configured Google Drive folder. Ensure the folder ID is correct and the folder is shared "Anyone with the link".']);
        exit;
    }

    // Prefer large files (activity data) over small ones (metadata only)
    usort($files, fn($a, $b) => (int)($b['size'] ?? 0) <=> (int)($a['size'] ?? 0));
    // Among large files, pick most recent
    $dataFiles = array_filter($files, fn($f) => ((int)($f['size'] ?? 0)) > 50000);
    if (!$dataFiles) $dataFiles = $files;
    // Re-sort by modifiedTime desc
    usort($dataFiles, fn($a, $b) => strcmp($b['modifiedTime'] ?? '', $a['modifiedTime'] ?? ''));
    $file = array_values($dataFiles)[0];

    $fileId       = $file['id'];
    $fileName     = $file['name'];
    $modifiedTime = $file['modifiedTime'] ?? null;

    // ── 2. Check if already imported and unchanged ───────────────────
    $check = $pdo->prepare("SELECT imported_rows, latest_date, modified_time FROM google_fit_drive_imports WHERE patient_id=? AND drive_file_id=? LIMIT 1");
    $check->execute([$uid, $fileId]);
    $existing = $check->fetch();

    if ($existing && (string)$existing['modified_time'] === (string)$modifiedTime) {
        echo json_encode([
            'success'         => true,
            'already_current' => true,
            'imported'        => (int)$existing['imported_rows'],
            'latest_date'     => $existing['latest_date'],
            'file'            => $fileName,
            'message'         => 'Already up to date — this is the latest Takeout ZIP from your Drive.',
        ]);
        exit;
    }

    // ── 3. Download and import ───────────────────────────────────────
    $maxBytes = GOOGLE_DRIVE_TAKEOUT_MAX_BYTES;
    $tmpPath  = driveDownloadZip($fileId, $apiKey, $maxBytes);

    try {
        $rows = parseTakeoutZip($tmpPath);
    } finally {
        @unlink($tmpPath);
    }

    if (!$rows) {
        echo json_encode(['success' => false, 'error' => 'No daily activity rows found in this Takeout file.']);
        exit;
    }

    $dates        = array_keys($rows);
    $placeholders = implode(',', array_fill(0, count($dates), '?'));
    $pdo->prepare("DELETE FROM health_metrics WHERE patient_id=? AND source='wearable' AND metric_date IN ($placeholders)")
        ->execute(array_merge([$uid], $dates));

    $stmt = $pdo->prepare("INSERT INTO health_metrics
        (patient_id, metric_date, heart_rate, steps_count, distance_km, calories_burned, weight_kg, source)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'wearable')");

    foreach ($rows as $row) {
        $stmt->execute([$uid, $row['metric_date'], $row['heart_rate'], $row['steps_count'],
            $row['distance_km'], $row['calories_burned'], $row['weight_kg']]);
    }

    // ── 4. Record import ─────────────────────────────────────────────
    if ($existing) {
        $pdo->prepare("UPDATE google_fit_drive_imports SET file_name=?, modified_time=?, imported_rows=?, latest_date=?, created_at=NOW() WHERE patient_id=? AND drive_file_id=?")
            ->execute([$fileName, $modifiedTime, count($rows), $dates[0], $uid, $fileId]);
    } else {
        $pdo->prepare("INSERT INTO google_fit_drive_imports (patient_id, drive_file_id, file_name, modified_time, imported_rows, latest_date) VALUES (?,?,?,?,?,?)")
            ->execute([$uid, $fileId, $fileName, $modifiedTime, count($rows), $dates[0]]);
    }

    echo json_encode([
        'success'     => true,
        'imported'    => count($rows),
        'latest_date' => $dates[0],
        'file'        => $fileName,
        'message'     => 'Synced ' . count($rows) . ' days from Google Drive',
    ]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
