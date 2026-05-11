<?php
/**
 * Sync last 7 days of Google Fit data into health_metrics table
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/fitness.php';
requireRole('patient');
header('Content-Type: application/json');

$uid = $_SESSION['user_id'];

// ── Helper: get a valid access token (refresh if expired) ──────────
function getAccessToken(PDO $pdo, int $uid): ?string {
    $row = $pdo->prepare("SELECT * FROM wearable_tokens WHERE user_id=? AND provider='google_fit'");
    $row->execute([$uid]);
    $token = $row->fetch();
    if (!$token) return null;

    // Refresh if expired (or expiring in 5 min)
    if (strtotime($token['expires_at']) < time() + 300) {
        if (!$token['refresh_token']) return null;
        $resp = @file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'timeout' => 10,
            'content' => http_build_query([
                'refresh_token' => $token['refresh_token'],
                'client_id'     => GOOGLE_FIT_CLIENT_ID,
                'client_secret' => GOOGLE_FIT_CLIENT_SECRET,
                'grant_type'    => 'refresh_token',
            ]),
        ]]));
        if (!$resp) return null;
        $new = json_decode($resp, true);
        if (empty($new['access_token'])) return null;
        $expires = date('Y-m-d H:i:s', time() + ($new['expires_in'] ?? 3600));
        $pdo->prepare("UPDATE wearable_tokens SET access_token=?, expires_at=? WHERE user_id=? AND provider='google_fit'")
            ->execute([$new['access_token'], $expires, $uid]);
        return $new['access_token'];
    }
    return $token['access_token'];
}

// ── Helper: Google Fit aggregate API call ──────────────────────────
function fitAggregate(string $token, array $dataTypes, int $days = 7): ?array {
    $endMs   = time() * 1000;
    $startMs = ($endMs - $days * 86400 * 1000);

    $body = json_encode([
        'aggregateBy'    => array_map(fn($t) => ['dataTypeName' => $t], $dataTypes),
        'bucketByTime'   => ['durationMillis' => 86400000],
        'startTimeMillis'=> $startMs,
        'endTimeMillis'  => $endMs,
    ]);

    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'timeout' => 12,
        'header'  => implode("\r\n", [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json",
            "Content-Length: " . strlen($body),
        ]),
        'content'       => $body,
        'ignore_errors' => true,
    ]]);

    $raw = @file_get_contents('https://www.googleapis.com/fitness/v1/users/me/dataset:aggregate', false, $ctx);
    return $raw ? json_decode($raw, true) : null;
}

// ── Get token ──────────────────────────────────────────────────────
$accessToken = getAccessToken($pdo, $uid);
if (!$accessToken) {
    echo json_encode(['success' => false, 'error' => 'Not connected to Google Fit or token expired. Please reconnect.']);
    exit;
}

// ── Fetch activity + body data ─────────────────────────────────────
$actData = fitAggregate($accessToken, [
    'com.google.step_count.delta',
    'com.google.calories.expended',
    'com.google.heart_rate.bpm',
    'com.google.weight',
]);

// ── Fetch sleep data separately ────────────────────────────────────
$sleepData = fitAggregate($accessToken, ['com.google.sleep.segment']);

if (!$actData) {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch data from Google Fit. Please try again.']);
    exit;
}

// ── Parse buckets into daily records ──────────────────────────────
$dailyData = [];

foreach ($actData['bucket'] ?? [] as $bucket) {
    $dayMs = (int)$bucket['startTimeMillis'];
    $date  = date('Y-m-d', $dayMs / 1000);
    if (!isset($dailyData[$date])) $dailyData[$date] = [];

    foreach ($bucket['dataset'] as $ds) {
        $type   = $ds['dataSourceId'];
        $points = $ds['point'] ?? [];
        if (!$points) continue;

        if (str_contains($type, 'step_count')) {
            $dailyData[$date]['steps'] = array_sum(array_column(
                array_column($points, 'value'), null
            ));
            // Flatten: sum all intVal
            $steps = 0;
            foreach ($points as $p) foreach ($p['value'] as $v) $steps += ($v['intVal'] ?? 0);
            $dailyData[$date]['steps'] = $steps;

        } elseif (str_contains($type, 'calories')) {
            $cals = 0;
            foreach ($points as $p) foreach ($p['value'] as $v) $cals += ($v['fpVal'] ?? 0);
            $dailyData[$date]['calories'] = round($cals);

        } elseif (str_contains($type, 'heart_rate')) {
            $hrs = [];
            foreach ($points as $p) foreach ($p['value'] as $v) if (isset($v['fpVal'])) $hrs[] = $v['fpVal'];
            if ($hrs) $dailyData[$date]['heart_rate'] = (int)round(array_sum($hrs) / count($hrs));

        } elseif (str_contains($type, 'weight')) {
            $weights = [];
            foreach ($points as $p) foreach ($p['value'] as $v) if (isset($v['fpVal'])) $weights[] = $v['fpVal'];
            if ($weights) $dailyData[$date]['weight'] = round(end($weights), 1);
        }
    }
}

// ── Parse sleep ────────────────────────────────────────────────────
foreach ($sleepData['bucket'] ?? [] as $bucket) {
    $date = date('Y-m-d', (int)$bucket['startTimeMillis'] / 1000);
    foreach ($bucket['dataset'] as $ds) {
        foreach ($ds['point'] ?? [] as $p) {
            $startNs = (int)($p['startTimeNanos'] ?? 0);
            $endNs   = (int)($p['endTimeNanos']   ?? 0);
            $durMins = ($endNs - $startNs) / 1e9 / 60;
            $dailyData[$date]['sleep_mins'] = ($dailyData[$date]['sleep_mins'] ?? 0) + $durMins;
        }
    }
}

// ── Delete existing wearable rows for these dates then re-insert ───
$dates = array_keys(array_filter($dailyData, fn($d) => !empty($d)));
if ($dates) {
    $placeholders = implode(',', array_fill(0, count($dates), '?'));
    $pdo->prepare("DELETE FROM health_metrics WHERE patient_id=? AND source='wearable' AND metric_date IN ($placeholders)")
        ->execute(array_merge([$uid], $dates));
}

$inserted = 0;
$stmt = $pdo->prepare("
    INSERT INTO health_metrics
        (patient_id, metric_date, steps_count, calories_burned, heart_rate, weight_kg, sleep_hours, source)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'wearable')
");

foreach ($dailyData as $date => $d) {
    if (empty($d)) continue;
    $stmt->execute([
        $uid,
        $date,
        $d['steps']        ?? null,
        $d['calories']     ?? null,
        $d['heart_rate']   ?? null,
        $d['weight']       ?? null,
        isset($d['sleep_mins']) ? round($d['sleep_mins'] / 60, 1) : null,
    ]);
    $inserted++;
}

// ── Update last sync time ──────────────────────────────────────────
$pdo->prepare("UPDATE wearable_tokens SET last_sync=NOW() WHERE user_id=? AND provider='google_fit'")
    ->execute([$uid]);

echo json_encode([
    'success'  => true,
    'synced'   => $inserted,
    'days'     => array_keys($dailyData),
    'summary'  => array_map(fn($d, $date) => array_merge(['date' => $date], $d), array_values($dailyData), array_keys($dailyData)),
    'message'  => "Synced {$inserted} days of health data from Google Fit",
]);
