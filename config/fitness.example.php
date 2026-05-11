<?php
// Copy this file to fitness.php and fill in your credentials
// Get these from: console.cloud.google.com → APIs & Services → Credentials
define('GOOGLE_FIT_CLIENT_ID',     'YOUR_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_FIT_CLIENT_SECRET', 'YOUR_CLIENT_SECRET');
define('GOOGLE_FIT_REDIRECT_URI',  'https://healthsphere.info/HealthSphere/api/google-fit-callback.php');

define('GOOGLE_FIT_SCOPES', implode(' ', [
    'https://www.googleapis.com/auth/fitness.activity.read',
    'https://www.googleapis.com/auth/fitness.heart_rate.read',
    'https://www.googleapis.com/auth/fitness.sleep.read',
    'https://www.googleapis.com/auth/fitness.body.read',
]));
