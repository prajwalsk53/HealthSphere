<?php
/**
 * Safe Appetite — DB Migration
 * Run once: http://localhost/HealthSphere/sql/safe_appetite_migration.php
 */
require_once __DIR__ . '/../config/config.php';

$sqls = [];

// ── Diet Preferences (vegan, keto, halal, etc.) ──────────────────────
$sqls[] = "CREATE TABLE IF NOT EXISTS diet_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    preference VARCHAR(80) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_patient_pref (patient_id, preference),
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
)";

// ── Food Intolerances (lactose, gluten, fructose, etc.) ───────────────
$sqls[] = "CREATE TABLE IF NOT EXISTS food_intolerances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    intolerance VARCHAR(80) NOT NULL,
    severity ENUM('mild','moderate','severe') DEFAULT 'moderate',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_patient_intol (patient_id, intolerance),
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
)";

// ── Ingredient Dislikes ───────────────────────────────────────────────
$sqls[] = "CREATE TABLE IF NOT EXISTS ingredient_dislikes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    ingredient VARCHAR(120) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
)";

// ── Ingredient Scan History ───────────────────────────────────────────
$sqls[] = "CREATE TABLE IF NOT EXISTS ingredient_scans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    product_name VARCHAR(200) DEFAULT '',
    ingredients_raw TEXT NOT NULL,
    scan_result ENUM('safe','caution','danger') NOT NULL,
    alerts_json TEXT,
    ai_summary TEXT,
    scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
)";

$errors = [];
foreach ($sqls as $sql) {
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
}

echo '<pre style="font-family:monospace;padding:20px;">';
if ($errors) {
    echo "ERRORS:\n";
    foreach ($errors as $e) echo "  - $e\n";
} else {
    echo "✅ Safe Appetite tables created successfully!\n\n";
    echo "Tables created:\n";
    echo "  - diet_preferences\n";
    echo "  - food_intolerances\n";
    echo "  - ingredient_dislikes\n";
    echo "  - ingredient_scans\n\n";
    echo "Next: Visit /HealthSphere/patient/safe-appetite.php\n";
}
echo '</pre>';
