<?php

// ── reCAPTCHA v2 verification ──────────────────────────────────────
function verifyCaptcha(): bool {
    $secret = defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '';
    // Skip verification if no secret key configured (dev mode)
    if (empty($secret)) return true;

    $response = $_POST['g-recaptcha-response'] ?? '';
    if (!$response) return false;

    $url  = 'https://www.google.com/recaptcha/api/siteverify';
    $data = http_build_query(['secret'=>$secret,'response'=>$response,'remoteip'=>$_SERVER['REMOTE_ADDR']??'']);

    $ctx = stream_context_create(['http'=>[
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $data,
        'timeout' => 5,
    ]]);
    $res = @file_get_contents($url, false, $ctx);
    if (!$res) return true; // Skip on network error (dev/localhost)
    $json = json_decode($res, true);
    return !empty($json['success']);
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(string $redirect = '/HealthSphere/index.php'): void {
    if (!isLoggedIn()) {
        header("Location: $redirect");
        exit;
    }
}

function requireRole(string|array $roles, string $redirect = '/HealthSphere/index.php'): void {
    requireLogin($redirect);
    $allowed = is_array($roles) ? $roles : [$roles];
    if (!in_array($_SESSION['user_role'] ?? '', $allowed)) {
        header("Location: $redirect");
        exit;
    }
}

function getCurrentUser(): array {
    return [
        'id'         => $_SESSION['user_id'] ?? null,
        'name'       => ($_SESSION['user_first'] ?? '') . ' ' . ($_SESSION['user_last'] ?? ''),
        'first_name' => $_SESSION['user_first'] ?? '',
        'last_name'  => $_SESSION['user_last'] ?? '',
        'email'      => $_SESSION['user_email'] ?? '',
        'role'       => $_SESSION['user_role'] ?? '',
        'nhs_id'     => $_SESSION['user_nhs_id'] ?? '',
    ];
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function formatDate(string $date, string $format = 'd M Y'): string {
    if (empty($date) || $date === '0000-00-00') return 'N/A';
    return date($format, strtotime($date));
}

function timeAgo(string $datetime): string {
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);

    if ($diff->d === 0 && $diff->h === 0) return $diff->i . 'm ago';
    if ($diff->d === 0) return $diff->h . 'h ago';
    if ($diff->d === 1) return 'Yesterday';
    if ($diff->d < 7) return $diff->d . ' days ago';
    return formatDate($datetime, 'd M Y');
}

function getStatusBadge(string $status): string {
    $map = [
        'completed'  => ['bg-success', 'Completed'],
        'upcoming'   => ['bg-primary', 'Upcoming'],
        'confirmed'  => ['bg-info', 'Confirmed'],
        'arrived'    => ['bg-success', 'Arrived'],
        'waiting'    => ['bg-warning text-dark', 'Waiting'],
        'late'       => ['bg-danger', 'Late'],
        'cancelled'  => ['bg-secondary', 'Cancelled'],
        'pending'    => ['bg-warning text-dark', 'Pending'],
        'normal'     => ['bg-success', 'Normal'],
        'elevated'   => ['bg-warning text-dark', 'Elevated'],
        'critical'   => ['bg-danger', 'Critical'],
        'approved'   => ['bg-success', 'Approved'],
        'suspended'  => ['bg-danger', 'Suspended'],
    ];
    $s = strtolower(trim($status));
    [$cls, $label] = $map[$s] ?? ['bg-secondary', ucfirst($s)];
    return "<span class=\"badge $cls\">$label</span>";
}

function getSeverityColor(string $severity): string {
    return match(strtolower($severity)) {
        'mild'     => 'success',
        'moderate' => 'warning',
        'severe'   => 'danger',
        default    => 'secondary',
    };
}

function calculateBMI(float $weight, float $height): float {
    if ($height <= 0) return 0;
    return round($weight / ($height * $height), 1);
}

function getBMICategory(float $bmi): string {
    if ($bmi < 18.5) return 'Underweight';
    if ($bmi < 25)   return 'Normal';
    if ($bmi < 30)   return 'Overweight';
    return 'Obese';
}

function getUnreadCount(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function getUnreadMessages(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function logAccess(PDO $pdo, int $userId, ?int $patientId, string $action): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, accessed_patient_id, action_type, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $patientId, $action, $ip]);
}

function redirectByRole(string $role): void {
    $routes = [
        'patient'    => BASE_URL . '/patient/dashboard.php',
        'doctor'     => BASE_URL . '/doctor/dashboard.php',
        'admin'      => BASE_URL . '/admin/dashboard.php',
        'government' => BASE_URL . '/government/dashboard.php',
    ];
    header('Location: ' . ($routes[$role] ?? BASE_URL . '/index.php'));
    exit;
}
