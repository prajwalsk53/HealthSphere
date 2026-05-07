<?php
define('APP_NAME', 'HealthSphere');
define('APP_VERSION', '2.0');
define('BASE_URL', 'http://localhost/HealthSphere');
define('SESSION_LIFETIME', 3600);

// ── Google reCAPTCHA v2 ─────────────────────────────────────────────
define('RECAPTCHA_SITE_KEY',   '6Ld4cogsAAAAAG9o_s6-zM8Qh2FZM9ZXwXuMHLHg');
define('RECAPTCHA_SECRET_KEY', '');  // ← Paste your SECRET key here (from Google reCAPTCHA console)

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/functions.php';
