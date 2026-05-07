<?php
require_once __DIR__ . '/core.php';
$user = requireAuth();
$uid  = (int)$user['id'];
$action = $_GET['action'] ?? 'list';

switch ($action) {

    case 'list': {
        $limit = min((int)($_GET['limit'] ?? 30), 100);
        $stmt = $pdo->prepare("SELECT * FROM health_metrics WHERE patient_id=? ORDER BY metric_date DESC LIMIT ?");
        $stmt->execute([$uid, $limit]);
        ok($stmt->fetchAll());
        break;
    }

    case 'latest': {
        $stmt = $pdo->prepare("SELECT * FROM health_metrics WHERE patient_id=? ORDER BY metric_date DESC LIMIT 1");
        $stmt->execute([$uid]);
        ok($stmt->fetch() ?: null);
        break;
    }

    case 'add': {
        $b = body();
        $stmt = $pdo->prepare("INSERT INTO health_metrics
            (patient_id,metric_date,heart_rate,blood_pressure_systolic,blood_pressure_diastolic,
             spo2,steps_count,sleep_hours,calories_burned,temperature,weight,notes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $uid,
            $b['metric_date'] ?? date('Y-m-d'),
            $b['heart_rate']                  ?? null,
            $b['blood_pressure_systolic']     ?? null,
            $b['blood_pressure_diastolic']    ?? null,
            $b['spo2']                        ?? null,
            $b['steps_count']                 ?? null,
            $b['sleep_hours']                 ?? null,
            $b['calories_burned']             ?? null,
            $b['temperature']                 ?? null,
            $b['weight']                      ?? null,
            $b['notes']                       ?? null,
        ]);
        ok(['id' => (int)$pdo->lastInsertId(), 'message' => 'Metric recorded'], 201);
        break;
    }

    case 'score': {
        // Simple risk score calculation
        $stmt = $pdo->prepare("SELECT * FROM health_metrics WHERE patient_id=? ORDER BY metric_date DESC LIMIT 1");
        $stmt->execute([$uid]);
        $m = $stmt->fetch();
        if (!$m) { ok(['score' => 0, 'level' => 'unknown', 'breakdown' => []]); break; }

        $score = 100; $breakdown = [];
        // BP
        $sys = (int)($m['blood_pressure_systolic'] ?? 120);
        $dia = (int)($m['blood_pressure_diastolic'] ?? 80);
        $bpPts = 0;
        if ($sys > 180 || $dia > 120) $bpPts = 25;
        elseif ($sys > 140 || $dia > 90) $bpPts = 15;
        elseif ($sys > 130 || $dia > 85) $bpPts = 8;
        $score -= $bpPts; $breakdown['bp'] = $bpPts;

        // HR
        $hr = (int)($m['heart_rate'] ?? 72);
        $hrPts = 0;
        if ($hr > 120 || $hr < 40) $hrPts = 20;
        elseif ($hr > 100 || $hr < 50) $hrPts = 10;
        $score -= $hrPts; $breakdown['heart_rate'] = $hrPts;

        // SpO2
        $spo2 = (float)($m['spo2'] ?? 98);
        $spoPts = 0;
        if ($spo2 < 90) $spoPts = 15;
        elseif ($spo2 < 95) $spoPts = 8;
        $score -= $spoPts; $breakdown['spo2'] = $spoPts;

        // Sleep
        $sleep = (float)($m['sleep_hours'] ?? 7);
        $slPts = 0;
        if ($sleep < 4) $slPts = 20;
        elseif ($sleep < 6) $slPts = 12;
        elseif ($sleep < 7) $slPts = 6;
        $score -= $slPts; $breakdown['sleep'] = $slPts;

        // Steps
        $steps = (int)($m['steps_count'] ?? 8000);
        $stPts = 0;
        if ($steps < 2000) $stPts = 20;
        elseif ($steps < 5000) $stPts = 12;
        elseif ($steps < 8000) $stPts = 5;
        $score -= $stPts; $breakdown['activity'] = $stPts;

        $score = max(0, $score);
        $level = $score >= 80 ? 'good' : ($score >= 60 ? 'fair' : ($score >= 40 ? 'poor' : 'critical'));

        ok(['score' => $score, 'level' => $level, 'breakdown' => $breakdown]);
        break;
    }

    case 'insights': {
        $stmt = $pdo->prepare("SELECT * FROM health_metrics WHERE patient_id=? ORDER BY metric_date DESC LIMIT 7");
        $stmt->execute([$uid]);
        $metrics = $stmt->fetchAll();

        $insights = [];
        if (!empty($metrics)) {
            $latest = $metrics[0];
            $sys = (int)($latest['blood_pressure_systolic'] ?? 0);
            $dia = (int)($latest['blood_pressure_diastolic'] ?? 0);
            $hr  = (int)($latest['heart_rate'] ?? 0);
            $spo2= (float)($latest['spo2'] ?? 0);
            $sleep=(float)($latest['sleep_hours'] ?? 0);
            $steps=(int)($latest['steps_count'] ?? 0);

            if ($sys > 140 || $dia > 90)
                $insights[] = ['type'=>'warning','title'=>'High Blood Pressure','message'=>"Your BP is {$sys}/{$dia} mmHg. Consider reducing sodium intake and stress."];
            if ($hr > 100)
                $insights[] = ['type'=>'warning','title'=>'Elevated Heart Rate','message'=>"Resting HR {$hr} bpm is above normal. Track exertion and consult if persistent."];
            if ($spo2 > 0 && $spo2 < 95)
                $insights[] = ['type'=>'critical','title'=>'Low Blood Oxygen','message'=>"SpO2 at {$spo2}% is below normal range. Seek medical attention."];
            if ($sleep > 0 && $sleep < 6)
                $insights[] = ['type'=>'warning','title'=>'Insufficient Sleep','message'=>"You slept {$sleep} hours. Adults need 7-9 hours for optimal health."];
            if ($steps > 0 && $steps < 5000)
                $insights[] = ['type'=>'info','title'=>'Low Activity','message'=>"Only {$steps} steps today. Aim for 10,000 steps to maintain cardiovascular health."];
            if ($sys < 130 && $dia < 85 && $hr >= 60 && $hr <= 100)
                $insights[] = ['type'=>'positive','title'=>'Healthy Vitals','message'=>'Your blood pressure and heart rate are within normal ranges. Keep it up!'];
        }

        ok($insights);
        break;
    }

    default: err('Unknown action. Use: list | latest | add | score | insights');
}
