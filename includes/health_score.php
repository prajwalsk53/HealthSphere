<?php
/**
 * HealthSphere — Health Risk Score Engine
 * Calculates a 0-100 score from real patient metrics.
 */

function calculateHealthScore(PDO $pdo, int $patientId): array {
    // Pull latest 7 days of metrics
    $stmt = $pdo->prepare("
        SELECT * FROM health_metrics
        WHERE patient_id = ? AND metric_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY metric_date DESC
    ");
    $stmt->execute([$patientId]);
    $metrics = $stmt->fetchAll();

    // Today's diet
    $diet = $pdo->prepare("
        SELECT SUM(calories) cal, SUM(sodium_mg) sodium, SUM(fiber) fiber, SUM(fats) fats
        FROM diet_logs d
        LEFT JOIN food_database f ON d.food_name = f.food_name
        WHERE d.patient_id = ? AND d.log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $diet->execute([$patientId]);
    $dietData = $diet->fetch();

    if (empty($metrics)) {
        return defaultScore();
    }

    $avgBpSys  = round(array_sum(array_column($metrics, 'blood_pressure_systolic'))  / count($metrics));
    $avgBpDia  = round(array_sum(array_column($metrics, 'blood_pressure_diastolic')) / count($metrics));
    $avgHr     = round(array_sum(array_column($metrics, 'heart_rate'))               / count($metrics));
    $avgSpo2   = round(array_sum(array_column($metrics, 'spo2'))                     / count($metrics), 1);
    $avgSleep  = round(array_sum(array_column($metrics, 'sleep_hours'))              / count($metrics), 1);
    $avgStress = round(array_sum(array_column($metrics, 'stress_level'))             / count($metrics));
    $avgSteps  = round(array_sum(array_column($metrics, 'steps_count'))              / count($metrics));
    $avgSleepQ = round(array_sum(array_column($metrics, 'sleep_quality'))            / count($metrics));

    // ── Blood Pressure score (max 25 pts) ─────────────────────────
    $bpScore = match(true) {
        $avgBpSys < 120 && $avgBpDia < 80  => 25,   // Optimal
        $avgBpSys < 130 && $avgBpDia < 85  => 20,   // Normal
        $avgBpSys < 140 && $avgBpDia < 90  => 13,   // High-normal
        $avgBpSys < 160 && $avgBpDia < 100 => 7,    // Grade 1 hypertension
        default                             => 2,    // Grade 2+
    };

    // ── Heart Rate score (max 20 pts) ─────────────────────────────
    $hrScore = match(true) {
        $avgHr >= 60 && $avgHr <= 75 => 20,
        $avgHr >= 55 && $avgHr <= 85 => 15,
        $avgHr >= 50 && $avgHr <= 100=> 10,
        default                       => 4,
    };

    // ── SpO2 score (max 15 pts) ───────────────────────────────────
    $spo2Score = match(true) {
        $avgSpo2 >= 98  => 15,
        $avgSpo2 >= 95  => 12,
        $avgSpo2 >= 92  => 7,
        $avgSpo2 >= 88  => 3,
        default          => 0,
    };

    // ── Sleep score (max 20 pts) ──────────────────────────────────
    $sleepScore = match(true) {
        $avgSleep >= 7 && $avgSleep <= 9 && $avgSleepQ >= 80 => 20,
        $avgSleep >= 7 && $avgSleep <= 9                     => 16,
        $avgSleep >= 6 && $avgSleep <= 10                    => 10,
        $avgSleep >= 5                                        => 5,
        default                                               => 1,
    };

    // ── Activity score (max 20 pts) ───────────────────────────────
    $activityScore = match(true) {
        $avgSteps >= 10000 => 20,
        $avgSteps >= 7500  => 16,
        $avgSteps >= 5000  => 11,
        $avgSteps >= 2500  => 6,
        default             => 2,
    };

    $total = $bpScore + $hrScore + $spo2Score + $sleepScore + $activityScore;
    $total = min(100, max(0, $total));

    $category = match(true) {
        $total >= 80 => 'excellent',
        $total >= 60 => 'good',
        $total >= 40 => 'fair',
        default      => 'poor',
    };

    // Cache in DB
    try {
        $pdo->prepare("
            INSERT INTO health_risk_scores (patient_id, score, category, bp_score, heart_score, sleep_score, activity_score)
            VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE score=VALUES(score),category=VALUES(category),
            bp_score=VALUES(bp_score),heart_score=VALUES(heart_score),
            sleep_score=VALUES(sleep_score),activity_score=VALUES(activity_score),
            calculated_at=NOW()
        ")->execute([$patientId, $total, $category, $bpScore, $hrScore, $sleepScore, $activityScore]);
    } catch (\Exception $e) {}

    return [
        'score'          => $total,
        'category'       => $category,
        'label'          => ucfirst($category),
        'color'          => scoreColor($category),
        'gradient'       => scoreGradient($category),
        'bp_score'       => $bpScore,
        'hr_score'       => $hrScore,
        'spo2_score'     => $spo2Score,
        'sleep_score'    => $sleepScore,
        'activity_score' => $activityScore,
        'avg_bp'         => "$avgBpSys/$avgBpDia",
        'avg_hr'         => $avgHr,
        'avg_spo2'       => $avgSpo2,
        'avg_sleep'      => $avgSleep,
        'avg_steps'      => $avgSteps,
        'breakdown'      => [
            ['label'=>'Blood Pressure', 'score'=>$bpScore,       'max'=>25, 'icon'=>'fa-tint',       'color'=>'#1565C0'],
            ['label'=>'Heart Rate',     'score'=>$hrScore,        'max'=>20, 'icon'=>'fa-heartbeat',  'color'=>'#DC2626'],
            ['label'=>'Blood Oxygen',   'score'=>$spo2Score,      'max'=>15, 'icon'=>'fa-lungs',      'color'=>'#0891B2'],
            ['label'=>'Sleep Quality',  'score'=>$sleepScore,     'max'=>20, 'icon'=>'fa-moon',       'color'=>'#7C3AED'],
            ['label'=>'Activity',       'score'=>$activityScore,  'max'=>20, 'icon'=>'fa-walking',    'color'=>'#16A34A'],
        ],
    ];
}

function defaultScore(): array {
    return ['score'=>0,'category'=>'fair','label'=>'No Data','color'=>'#5E7A99','gradient'=>'#5E7A99','breakdown'=>[],'avg_bp'=>'—','avg_hr'=>'—','avg_spo2'=>'—','avg_sleep'=>'—','avg_steps'=>'—'];
}

function scoreColor(string $cat): string {
    return match($cat) {
        'excellent' => '#16A34A',
        'good'      => '#1565C0',
        'fair'      => '#D97706',
        'poor'      => '#DC2626',
        default     => '#5E7A99',
    };
}

function scoreGradient(string $cat): string {
    return match($cat) {
        'excellent' => 'linear-gradient(135deg,#166534,#16A34A)',
        'good'      => 'linear-gradient(135deg,#0A1F44,#1565C0)',
        'fair'      => 'linear-gradient(135deg,#92400E,#D97706)',
        'poor'      => 'linear-gradient(135deg,#7F1D1D,#DC2626)',
        default     => 'linear-gradient(135deg,#1E293B,#5E7A99)',
    };
}

function renderScoreGauge(array $scoreData, bool $large = false): string {
    $score    = (float) $scoreData['score'];
    $color    = $scoreData['color'];
    $category = $scoreData['label'];
    $size     = $large ? 180 : 120;
    $stroke   = $large ? 14 : 10;
    $r        = ($size - $stroke) / 2;
    $circ     = 2 * M_PI * $r;
    // Arc is 270 degrees (from 135deg to 405deg / 3π/4 to -π/4)
    $arcLen   = ($circ * 0.75);
    $filled   = $arcLen * ($score / 100);
    $pctText  = $large ? "<text x='{$size}' y='".($size-10)."' text-anchor='middle' font-size='36' font-weight='900' font-family='Inter' fill='$color'>$score</text>"
                       : "<text x='{$size}' y='".($size-8)."' text-anchor='middle' font-size='22' font-weight='900' font-family='Inter' fill='$color'>$score</text>";

    $catText  = $large ? "<text x='{$size}' y='".($size+18)."' text-anchor='middle' font-size='14' font-weight='700' font-family='Inter' fill='$color'>$category</text>" : '';

    return "
    <svg width='".($size*2)."' height='".($size*2-20)."' viewBox='0 0 ".($size*2)." ".($size*2)."'>
      <circle cx='$size' cy='$size' r='$r' fill='none' stroke='#E2E8F0' stroke-width='$stroke'
        stroke-dasharray='$arcLen " . ($circ - $arcLen) . "' stroke-dashoffset='" . ($circ * 0.125) . "'
        stroke-linecap='round'/>
      <circle cx='$size' cy='$size' r='$r' fill='none' stroke='$color' stroke-width='$stroke'
        stroke-dasharray='$filled " . ($circ - $filled) . "' stroke-dashoffset='" . ($circ * 0.125) . "'
        stroke-linecap='round' style='transition:stroke-dasharray 1.4s ease;'/>
      $pctText
      $catText
    </svg>";
}
