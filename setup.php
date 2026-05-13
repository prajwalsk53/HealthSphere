<?php
// HealthSphere — Database Setup Script
// Run this ONCE at: http://localhost/HealthSphere/setup.php
// Then DELETE or RESTRICT this file.

$host = 'localhost'; $user = 'root'; $pass = ''; $dbname = 'healthsphere';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $sqlFile = __DIR__ . '/sql/healthsphere.sql';
    if (!file_exists($sqlFile)) die('<b style="color:red">SQL file not found at: '.$sqlFile.'</b>');

    $sql = file_get_contents($sqlFile);
    // Split on ; followed by newline to handle multi-statement
    $statements = array_filter(array_map('trim', explode(";\n", $sql)));

    $ok = 0; $errors = [];
    foreach ($statements as $stmt) {
        if (!$stmt) continue;
        try { $pdo->exec($stmt); $ok++; } catch (\PDOException $e) { $errors[] = $e->getMessage(); }
    }
    echo '<!DOCTYPE html><html><head><title>HealthSphere Setup</title>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">';
    echo '<style>*{box-sizing:border-box}body{font-family:Inter,sans-serif;background:#EEF4FF;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}.box{background:#fff;border-radius:16px;padding:40px;max-width:600px;width:100%;box-shadow:0 8px 32px rgba(10,31,68,.12)}h2{color:#0A1F44;margin-bottom:20px}.err{background:#FEE2E2;border-radius:8px;padding:12px;font-size:12px;color:#991B1B;margin-bottom:8px;font-family:monospace}</style>';
    echo '</head><body><div class="box">';
    echo '<h2>🏥 HealthSphere Setup</h2>';
    if (empty($errors)) {
        echo '<div style="background:#DCFCE7;border-radius:10px;padding:20px;margin-bottom:20px;border-left:4px solid #16A34A;">';
        echo '<h3 style="color:#166534;margin:0 0 8px;">✅ Database Setup Complete!</h3>';
        echo '<p style="color:#166534;margin:0;">'.$ok.' statements executed successfully.</p>';
        echo '</div>';
    } else {
        echo '<div style="background:#FEF3C7;border-radius:10px;padding:16px;margin-bottom:16px;border-left:4px solid #D97706;">';
        echo '<p style="color:#92400E;margin:0;font-weight:600;">⚠️ Completed with '.count($errors).' warnings (usually duplicate inserts — safe to ignore).</p>';
        echo '</div>';
        foreach (array_slice($errors,0,5) as $err) echo "<div class='err'>".htmlspecialchars($err)."</div>";
    }
    echo '<h3 style="color:#0A1F44;margin-top:24px;">Demo Login Accounts</h3>';
    echo '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
    echo '<tr style="background:#EEF4FF;"><th style="padding:8px 12px;text-align:left;">Email</th><th style="padding:8px 12px;text-align:left;">Password</th><th style="padding:8px 12px;text-align:left;">Role</th></tr>';
    $accounts = [
        ['emma.patel007@gmail.com','password','Patient'],
        ['emma.hall@leicesterhospital.nhs.uk','password','Doctor'],
        ['admin@healthsphere.nhs.uk','password','Admin'],
        ['w.jayson@dhsc.gov.uk','password','Government'],
    ];
    foreach ($accounts as [$email,$pass,$role]) {
        echo "<tr style='border-bottom:1px solid #DBEAFE;'><td style='padding:8px 12px;font-family:monospace;'>$email</td><td style='padding:8px 12px;'>$pass</td><td style='padding:8px 12px;font-weight:600;'>$role</td></tr>";
    }
    echo '</table>';
    echo '<div style="margin-top:24px;text-align:center;">';
    echo '<a href="/HealthSphere/index.php" style="display:inline-block;background:#1565C0;color:#fff;padding:14px 28px;border-radius:10px;font-weight:700;text-decoration:none;font-size:15px;">🚀 Launch HealthSphere →</a>';
    echo '</div>';
    echo '<p style="font-size:12px;color:#5E7A99;text-align:center;margin-top:16px;">⚠️ Delete or restrict access to this setup.php file after setup.</p>';
    echo '</div></body></html>';

} catch (\PDOException $e) {
    die('<h2 style="color:red">Connection failed:</h2><pre>'.$e->getMessage().'</pre>');
}
