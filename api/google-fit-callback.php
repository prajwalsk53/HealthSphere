<?php
/**
 * Google Fit OAuth callback — exchanges code for tokens and stores them
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/fitness.php';
requireRole('patient');

$uid = $_SESSION['user_id'];

// Verify state to prevent CSRF
if (($_GET['state'] ?? '') !== ($_SESSION['google_fit_state'] ?? '')) {
    die('Invalid state. Please try connecting again. <a href="../patient/wearable.php">Go back</a>');
}
unset($_SESSION['google_fit_state']);

$code  = $_GET['code']  ?? '';
$error = $_GET['error'] ?? '';

if ($error || !$code) {
    header('Location: ' . BASE_URL . '/patient/wearable.php?error=access_denied');
    exit;
}

// Exchange code for tokens
$tokenResponse = @file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create(['http' => [
    'method'  => 'POST',
    'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
    'timeout' => 10,
    'content' => http_build_query([
        'code'          => $code,
        'client_id'     => GOOGLE_FIT_CLIENT_ID,
        'client_secret' => GOOGLE_FIT_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_FIT_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]),
]]));

if (!$tokenResponse) {
    header('Location: ' . BASE_URL . '/patient/wearable.php?error=token_failed');
    exit;
}

$tokens = json_decode($tokenResponse, true);
if (empty($tokens['access_token'])) {
    header('Location: ' . BASE_URL . '/patient/wearable.php?error=token_failed');
    exit;
}

// Create wearable_tokens table if it doesn't exist
$pdo->exec("CREATE TABLE IF NOT EXISTS wearable_tokens (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    user_id      INT NOT NULL,
    provider     VARCHAR(20) NOT NULL DEFAULT 'google_fit',
    access_token TEXT,
    refresh_token TEXT,
    expires_at   DATETIME,
    last_sync    DATETIME NULL,
    connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_provider (user_id, provider),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Store tokens (upsert)
$expiresAt = date('Y-m-d H:i:s', time() + ($tokens['expires_in'] ?? 3600));
$stmt = $pdo->prepare("
    INSERT INTO wearable_tokens (user_id, provider, access_token, refresh_token, expires_at)
    VALUES (?, 'google_fit', ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        access_token  = VALUES(access_token),
        refresh_token = COALESCE(VALUES(refresh_token), refresh_token),
        expires_at    = VALUES(expires_at)
");
$stmt->execute([$uid, $tokens['access_token'], $tokens['refresh_token'] ?? null, $expiresAt]);

header('Location: ' . BASE_URL . '/patient/wearable.php?connected=1');
exit;
