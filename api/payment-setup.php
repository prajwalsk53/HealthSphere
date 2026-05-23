<?php
// One-time setup script — run once then delete (or restrict access)
// Accessible by admin login OR from localhost
require_once __DIR__ . '/../config/config.php';
$fromLocalhost = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']);
if (!$fromLocalhost) {
    requireRole('admin');
}

$log = [];

// 1. Add pharmacy to users.role ENUM
try {
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('patient','doctor','admin','government','pharmacy') NOT NULL DEFAULT 'patient'");
    $log[] = '✅ users.role ENUM updated to include pharmacy';
} catch(PDOException $e) {
    $log[] = 'ℹ️ users.role: ' . $e->getMessage();
}

// 2. Add payment_intent_id to appointments
try {
    $pdo->exec('ALTER TABLE appointments ADD COLUMN payment_intent_id VARCHAR(100) NULL');
    $log[] = '✅ appointments.payment_intent_id column added';
} catch(PDOException $e) {
    $log[] = 'ℹ️ appointments col: ' . $e->getMessage();
}

// 3. Add payment_intent_id to prescription_orders
try {
    $pdo->exec('ALTER TABLE prescription_orders ADD COLUMN payment_intent_id VARCHAR(100) NULL');
    $log[] = '✅ prescription_orders.payment_intent_id column added';
} catch(PDOException $e) {
    $log[] = 'ℹ️ rx_orders col: ' . $e->getMessage();
}

// 4. Create payments table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        payment_type VARCHAR(20) NOT NULL,
        stripe_payment_intent_id VARCHAR(100) UNIQUE NOT NULL,
        amount INT NOT NULL,
        currency VARCHAR(3) DEFAULT 'gbp',
        status VARCHAR(30) DEFAULT 'succeeded',
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_pi (stripe_payment_intent_id)
    )");
    $log[] = '✅ payments table created';
} catch(PDOException $e) {
    $log[] = 'ℹ️ payments: ' . $e->getMessage();
}

// 5. Insert Medical Team demo user
$hash  = password_hash('password', PASSWORD_DEFAULT);
$nhsId = 'NHS-MED-TEAM01';
try {
    $pdo->prepare("INSERT IGNORE INTO users
        (nhs_id,first_name,last_name,email,password,role,phone,is_active,approval_status,created_at)
        VALUES (?,?,?,?,?,?,?,1,'approved',NOW())")
        ->execute([$nhsId,'Medical','Team','medteam@healthsphere.nhs.uk',$hash,'pharmacy','07700000000']);
    $log[] = '✅ Medical Team user ready (medteam@healthsphere.nhs.uk / password)';
} catch(PDOException $e) {
    $log[] = 'ℹ️ user: ' . $e->getMessage();
}

$log[] = '';
$log[] = '🎉 Setup complete. You can now delete this file.';
?>
<!DOCTYPE html><html><head><title>Payment Setup</title>
<style>body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:40px;} pre{background:#1e293b;padding:20px;border-radius:8px;line-height:1.8;}</style>
</head><body>
<h2 style="color:#38bdf8;">HealthSphere — Payment & Medical Team Setup</h2>
<pre><?= implode("\n", $log) ?></pre>
</body></html>
