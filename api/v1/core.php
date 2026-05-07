<?php
/**
 * HealthSphere REST API v1 — Core helpers
 * JWT authentication, response helpers, middleware
 */
require_once __DIR__ . '/../../config/db.php';

define('JWT_SECRET',  'HS_NHS_SECRET_2025_'.md5('healthsphere'));
define('JWT_EXPIRE',  86400 * 30);  // 30 days

// ── CORS headers (allow Flutter/mobile app) ─────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ── Response helpers ─────────────────────────────────────────────────
function ok(mixed $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => true,  'data' => $data]);
    exit;
}
function err(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}
function body(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

// ── JWT ──────────────────────────────────────────────────────────────
function b64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function b64urlDecode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

function jwtCreate(int $userId, string $role): string {
    $h = b64url(json_encode(['typ'=>'JWT','alg'=>'HS256']));
    $p = b64url(json_encode(['sub'=>$userId,'role'=>$role,'iat'=>time(),'exp'=>time()+JWT_EXPIRE]));
    $s = b64url(hash_hmac('sha256', "$h.$p", JWT_SECRET, true));
    return "$h.$p.$s";
}

function jwtVerify(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$h,$p,$s] = $parts;
    $check = b64url(hash_hmac('sha256', "$h.$p", JWT_SECRET, true));
    if (!hash_equals($check, $s)) return null;
    $payload = json_decode(b64urlDecode($p), true);
    if (!$payload || ($payload['exp'] ?? 0) < time()) return null;
    return $payload;
}

// ── Auth middleware ───────────────────────────────────────────────────
function requireAuth(): array {
    global $pdo;
    $header = $_SERVER['HTTP_AUTHORIZATION']
           ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
           ?? (function_exists('getallheaders') ? (getallheaders()['Authorization'] ?? '') : '')
           ?? '';
    if (!preg_match('/Bearer\s+(.+)/i', $header, $m)) {
        err('Authorization token required', 401);
    }
    $payload = jwtVerify(trim($m[1]));
    if (!$payload) err('Invalid or expired token', 401);

    $stmt = $pdo->prepare("SELECT id,first_name,last_name,email,role,nhs_id,approval_status FROM users WHERE id=? AND is_active=1");
    $stmt->execute([$payload['sub']]);
    $user = $stmt->fetch();
    if (!$user) err('User not found or account inactive', 401);
    return $user;
}
