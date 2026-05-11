<?php
/**
 * Start Google Fit OAuth flow — redirects patient to Google consent screen
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/fitness.php';
requireRole('patient');

$state = bin2hex(random_bytes(16));
$_SESSION['google_fit_state'] = $state;

$url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
    'client_id'     => GOOGLE_FIT_CLIENT_ID,
    'redirect_uri'  => GOOGLE_FIT_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => GOOGLE_FIT_SCOPES,
    'access_type'   => 'offline',
    'prompt'        => 'consent',
    'state'         => $state,
]);

header('Location: ' . $url);
exit;
