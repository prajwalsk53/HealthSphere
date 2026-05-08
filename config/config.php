<?php
define('APP_NAME', 'HealthSphere');
define('APP_VERSION', '2.0');
define('SESSION_LIFETIME', 3600);

// ── Environment detection ───────────────────────────────────────────
// Automatically uses the correct base path on localhost vs live domain.
$_isLocal = ($_SERVER['HTTP_HOST'] ?? '') === 'localhost'
         || str_starts_with($_SERVER['HTTP_HOST'] ?? '', '127.')
         || str_starts_with($_SERVER['HTTP_HOST'] ?? '', '192.168.');

define('IS_LOCAL',   $_isLocal);
define('BASE_URL',   $_isLocal ? 'http://localhost/HealthSphere' : 'https://healthsphere.info');
define('BASE_PATH',  $_isLocal ? '/HealthSphere' : '');

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
