<?php
define('APP_NAME', 'HealthSphere');
define('APP_VERSION', '2.0');
define('SESSION_LIFETIME', 3600);

// ── Environment detection ───────────────────────────────────────────
// Automatically uses the correct base path on localhost vs live domain.
$_host    = $_SERVER['HTTP_HOST'] ?? '';
$_isLocal = $_host === 'localhost'
         || strpos($_host, '127.') === 0
         || strpos($_host, '192.168.') === 0;

define('IS_LOCAL',   $_isLocal);
define('BASE_URL',   $_isLocal ? 'http://localhost/HealthSphere' : 'https://healthsphere.info/HealthSphere');
define('BASE_PATH',  '/HealthSphere');

// ── Google reCAPTCHA v2 ─────────────────────────────────────────────
define('RECAPTCHA_SITE_KEY',   '6Ld5QeMsAAAAAPaWko98V0rq7tbvm5wBTdO6upfJ');
define('RECAPTCHA_SECRET_KEY', '6Ld5QeMsAAAAALVm5X2UdwVUKqk82uoQmBzi0aXx');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => !$_isLocal,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/sentry.php';
