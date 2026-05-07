<?php
/**
 * HealthSphere — AI-Style Insights Engine
 * Rule-based analysis producing smart, personalised recommendations.
 */

function generateInsights(PDO $pdo, int $patientId): array {
    $insights = [];

    // ── Fetch recent data ─────────────────────────────────────────
    $metricsStmt = $pdo->prepare("
        SELECT * FROM health_metrics WHERE patient_id=? ORDER BY metric_date DESC LIMIT 14
    ");
    $metricsStmt->execute([$patientId]);
    $metrics = $metricsStmt->fetchAll();

    $dietStmt = $pdo->prepare("
        SELECT log_date, SUM(calories) cal, SUM(carbs) carbs, SUM(fats) fats, SUM(fiber) fiber
        FROM diet_logs WHERE patient_id=? AND log_date >= DATE_SUB(CURDATE(),INTERVAL 7 DAY)
        GROUP BY log_date ORDER BY log_date DESC
    ");
    $dietStmt->execute([$patientId]);
    $dietDays = $dietStmt->fetchAll();

    $waterStmt = $pdo->prepare("SELECT AVG(glasses_count) avg_glasses FROM water_logs WHERE patient_id=? AND log_date >= DATE_SUB(CURDATE(),INTERVAL 7 DAY)");
    $waterStmt->execute([$patientId]);
    $water = $waterStmt->fetchColumn();

    $allergiesStmt = $pdo->prepare("SELECT COUNT(*) FROM allergies WHERE patient_id=? AND severity='severe' AND is_active=1");
    $allergiesStmt->execute([$patientId]);
    $severeAllergies = $allergiesStmt->fetchColumn();

    if (empty($metrics)) {
        return [['type'=>'info','category'=>'Data','title'=>'Connect your HealthSphere Band','message'=>'Link your wearable device to start receiving personalised health insights.','action_label'=>'Connect Device','action_href'=>'health-insights.php','icon'=>'fa-watch']];
    }

    $latest  = $metrics[0];
    $week1   = array_slice($metrics, 0, 7);
    $week2   = array_slice($metrics, 7, 7);

    $avgSys1  = avg(array_column($week1, 'blood_pressure_systolic'));
    $avgSys2  = empty($week2) ? $avgSys1 : avg(array_column($week2, 'blood_pressure_systolic'));
    $avgHr1   = avg(array_column($week1, 'heart_rate'));
    $avgHr2   = empty($week2) ? $avgHr1 : avg(array_column($week2, 'heart_rate'));
    $avgSleep = avg(array_column($week1, 'sleep_hours'));
    $avgSteps = avg(array_column($week1, 'steps_count'));
    $avgSpo2  = avg(array_column($week1, 'spo2'));
    $avgStress= avg(array_column($week1, 'stress_level'));
    $bpTrend  = $avgSys1 - $avgSys2;
    $hrTrend  = $avgHr1  - $avgHr2;

    // ── BLOOD PRESSURE RULES ──────────────────────────────────────
    if ($avgSys1 >= 140) {
        $insights[] = insight('critical','Blood Pressure','⚠️ Blood Pressure Critically High',
            "Your average systolic BP is {$avgSys1} mmHg over the past 7 days — significantly above the safe threshold of 120 mmHg. This requires immediate medical attention.",
            'Book Appointment','appointments.php','fa-heartbeat');
    } elseif ($avgSys1 >= 130) {
        $insights[] = insight('warning','Blood Pressure','Blood Pressure Elevated This Week',
            "Average BP {$avgSys1} mmHg — above the recommended 120 mmHg. Reduce sodium intake, stay hydrated, and avoid high-stress activities.",
            'View BP Trend','health-insights.php','fa-tint');
    } elseif ($bpTrend > 8) {
        $insights[] = insight('warning','Blood Pressure','Blood Pressure Rising This Week',
            "BP increased by ".round($bpTrend)." mmHg compared to last week. Monitor carefully and reduce salt consumption.",
            'View Trend','health-insights.php','fa-chart-line');
    } elseif ($avgSys1 < 120) {
        $insights[] = insight('positive','Blood Pressure','Excellent Blood Pressure Control',
            "Your average BP of {$avgSys1} mmHg is within optimal range. Keep up your current lifestyle habits.",
            null,null,'fa-check-circle');
    }

    // ── HEART RATE RULES ──────────────────────────────────────────
    if ($latest['heart_rate'] > 100) {
        $insights[] = insight('warning','Heart Rate','Resting Heart Rate Elevated',
            "Today's resting HR is {$latest['heart_rate']} bpm — above 100 bpm threshold. Avoid caffeine, ensure adequate sleep, and track over the next 24 hours.",
            'View HR Chart','health-insights.php','fa-heartbeat');
    } elseif ($hrTrend > 10) {
        $insights[] = insight('warning','Heart Rate','Heart Rate Trending Up',
            "Your heart rate has increased by ".round($hrTrend)." bpm this week. This could indicate increased stress or reduced fitness.",
            'View Heart Rate','health-insights.php','fa-heartbeat');
    } elseif ($avgHr1 >= 60 && $avgHr1 <= 72) {
        $insights[] = insight('positive','Heart Rate','Heart Rate in Athletic Range',
            "Your average resting HR of ".round($avgHr1)." bpm shows excellent cardiovascular fitness. Well done!",
            null,null,'fa-heart');
    }

    // ── SLEEP RULES ───────────────────────────────────────────────
    $lowSleepDays = count(array_filter($week1, fn($m) => (float)$m['sleep_hours'] < 6.0));
    if ($avgSleep < 6.0) {
        $insights[] = insight('critical','Sleep','Severe Sleep Deficit Detected',
            "You are averaging only ".round($avgSleep,1)."h of sleep — well below the recommended 7–9 hours. Chronic sleep deprivation raises hypertension risk by 35%.",
            'View Sleep Data','health-insights.php','fa-moon');
    } elseif ($lowSleepDays >= 3) {
        $insights[] = insight('warning','Sleep',"Poor Sleep on $lowSleepDays of 7 Days",
            "You slept less than 6 hours on $lowSleepDays nights this week. Try to maintain a consistent bedtime and limit screen time after 9pm.",
            'Sleep Tips','health-insights.php','fa-moon');
    } elseif ($avgSleep >= 7.5) {
        $insights[] = insight('positive','Sleep','Sleep Quality is Great',
            "You averaged ".round($avgSleep,1)."h of sleep this week — within the optimal 7–9 hour range. Quality rest is key to recovery.",
            null,null,'fa-moon');
    }

    // ── ACTIVITY RULES ────────────────────────────────────────────
    $sedentaryDays = count(array_filter($week1, fn($m) => (int)$m['steps_count'] < 3000));
    if ($avgSteps < 3000) {
        $insights[] = insight('critical','Activity','Very Low Activity Level',
            "Average daily steps: ".number_format(round($avgSteps)).". Sedentary behaviour is strongly linked to cardiovascular disease and diabetes.",
            'Start Exercise Plan','health-insights.php','fa-walking');
    } elseif ($sedentaryDays >= 2) {
        $insights[] = insight('warning','Activity',"Sedentary on $sedentaryDays Days This Week",
            "You had fewer than 3,000 steps on $sedentaryDays days. Even a 10-minute walk can reduce health risks significantly.",
            'View Activity','health-insights.php','fa-running');
    } elseif ($avgSteps >= 10000) {
        $insights[] = insight('positive','Activity','Step Goal Achieved — Great Work!',
            "You averaged ".number_format(round($avgSteps))." steps/day this week, exceeding the 10,000 step goal. Your cardiovascular health is benefiting.",
            null,null,'fa-medal');
    }

    // ── SpO2 RULES ────────────────────────────────────────────────
    if ($avgSpo2 < 93) {
        $insights[] = insight('critical','Oxygen','⚠️ Blood Oxygen Level Critical',
            "SpO₂ averaging ".round($avgSpo2,1)."% — below 95% threshold. This can indicate respiratory problems. Consult a doctor immediately.",
            'Emergency Contact','messages.php','fa-lungs');
    } elseif ($avgSpo2 < 96) {
        $insights[] = insight('warning','Oxygen','Blood Oxygen Slightly Low',
            "SpO₂ at ".round($avgSpo2,1)."%. Normal range is 95–100%. Deep breathing exercises and improved ventilation may help.",
            'View Details','health-insights.php','fa-wind');
    }

    // ── STRESS RULES ──────────────────────────────────────────────
    if ($avgStress > 65) {
        $insights[] = insight('warning','Mental Health','High Stress Levels Detected',
            "Your average stress score is ".round($avgStress)."/100 this week. Chronic stress elevates cortisol, disrupts sleep, and raises BP. Consider mindfulness.",
            'Track Mental Health','health-analysis.php','fa-brain');
    }

    // ── DIET RULES ────────────────────────────────────────────────
    if (!empty($dietDays)) {
        $avgCal  = avg(array_column($dietDays, 'cal'));
        $avgFiber= avg(array_column($dietDays, 'fiber'));
        if ($avgCal > 2800) {
            $insights[] = insight('warning','Diet','Calorie Intake Above Target',
                "You averaged ".round($avgCal)." kcal/day this week — above the 2,500 kcal recommended limit. Focus on portion control.",
                'View Diet','diet-tracker.php','fa-utensils');
        }
        if ($avgFiber < 10) {
            $insights[] = insight('warning','Diet','Low Fibre Intake Detected',
                "Your fibre intake is below recommended levels. Add more vegetables, legumes, and whole grains to support digestive and heart health.",
                'Log Meals','diet-tracker.php','fa-leaf');
        }
    }

    // ── WATER RULES ───────────────────────────────────────────────
    if ($water !== null && $water < 5) {
        $insights[] = insight('warning','Hydration','Low Water Intake This Week',
            "You averaged only ".round($water,1)." glasses/day — below the 8 glass target. Dehydration elevates BP and impairs kidney function.",
            'Log Water','diet-tracker.php','fa-tint');
    }

    // ── ALLERGY ALERT ─────────────────────────────────────────────
    if ($severeAllergies > 0) {
        $insights[] = insight('info','Safety','Severe Allergy on Record',
            "You have $severeAllergies severe allergy/allergies on file. Ensure your emergency contacts and doctor are aware.",
            'View Allergies','medical-records.php?tab=allergies','fa-exclamation-triangle');
    }

    // ── POSITIVE STREAK ───────────────────────────────────────────
    $allGoodDays = count(array_filter($week1, fn($m) => (float)$m['sleep_hours'] >= 7 && (int)$m['steps_count'] >= 7500 && (int)$m['heart_rate'] <= 85));
    if ($allGoodDays >= 5) {
        $insights[] = insight('positive','Overall','Outstanding Health Week! 🎉',
            "You had $allGoodDays healthy days in a row — great sleep, good activity levels, and stable vitals. Keep the momentum going!",
            null,null,'fa-star');
    }

    // Sort: critical → warning → info → positive
    $order = ['critical'=>0,'warning'=>1,'info'=>2,'positive'=>3];
    usort($insights, fn($a,$b) => ($order[$a['type']]??4) - ($order[$b['type']]??4));

    return array_slice($insights, 0, 8);
}

function insight(string $type, string $category, string $title, string $message, ?string $actionLabel, ?string $actionHref, string $icon): array {
    return compact('type','category','title','message','actionLabel','actionHref','icon');
}

function avg(array $values): float {
    $values = array_filter($values, fn($v) => $v !== null && $v !== '');
    return empty($values) ? 0 : array_sum($values) / count($values);
}

function insightStyle(string $type): array {
    return match($type) {
        'critical' => ['#DC2626','#FEE2E2','rgba(220,38,38,.12)'],
        'warning'  => ['#D97706','#FEF3C7','rgba(217,119,6,.1)'],
        'info'     => ['#0891B2','#CFFAFE','rgba(8,145,178,.1)'],
        'positive' => ['#16A34A','#DCFCE7','rgba(22,163,74,.1)'],
        default    => ['#5E7A99','#F1F5F9','rgba(94,122,153,.08)'],
    };
}
