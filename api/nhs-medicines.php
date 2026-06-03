<?php
/**
 * NHS Website Content API v2 — Medicines proxy
 * Docs: https://digital.nhs.uk/developer/api-catalogue/nhs-website-content/v2
 *
 * Sandbox (no key): https://sandbox.api.service.nhs.uk/nhs-website-content/
 * Production (key required + onboarding): https://api.service.nhs.uk/nhs-website-content/
 * Header: apikey: YOUR_KEY
 * Attribution required when displaying content (NHS logo linking back to source).
 */
require_once __DIR__ . '/../config/config.php';
requireLogin();
header('Content-Type: application/json');

$drug = trim($_GET['drug'] ?? '');
if (!$drug) { echo json_encode(['error' => 'No drug name provided']); exit; }

$slug   = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $drug), '-'));
$apiKey = defined('NHS_API_KEY') ? NHS_API_KEY : '';

// Use production if key set, sandbox if not (sandbox is open access, no key needed)
$baseUrl = $apiKey
    ? 'https://api.service.nhs.uk/nhs-website-content'
    : 'https://sandbox.api.service.nhs.uk/nhs-website-content';

function nhsMedFetch(string $url, string $apiKey): ?array {
    $headers = ['Accept: application/json', 'User-Agent: HealthSphere/1.0'];
    if ($apiKey) $headers[] = "apikey: {$apiKey}";
    $ctx = stream_context_create(['http' => [
        'timeout'       => 8,
        'ignore_errors' => true,
        'header'        => implode("\r\n", $headers) . "\r\n",
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return null;
    if (!empty($http_response_header)) {
        preg_match('/HTTP\/\S+ (\d+)/', $http_response_header[0], $m);
        if ((int)($m[1] ?? 200) !== 200) return null;
    }
    return json_decode($raw, true) ?: null;
}

function nhsMedClean(mixed $val, int $max = 500): string {
    $text = is_array($val) ? implode(' ', $val) : (string)$val;
    $text = preg_replace('/\s+/', ' ', strip_tags($text));
    return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
}

// ── Try NHS Website Content API v2 ─────────────────────────────────
$data = nhsMedFetch("{$baseUrl}/medicines/{$slug}/", $apiKey);

if ($data && !empty($data['name'])) {
    $sections = [];
    foreach ($data['hasPart'] ?? [] as $part) {
        $heading = $part['headline'] ?? ($part['name'] ?? '');
        $body    = $part['text'] ?? ($part['description'] ?? '');
        $content = nhsMedClean($body);
        if ($heading && strlen($content) > 20) {
            $sections[] = ['heading' => $heading, 'content' => $content];
        }
    }

    $author   = $data['author'] ?? [];
    $logoUrl  = $author['logo'] ?? 'https://assets.nhs.uk/nhsuk-cms/images/nhs-attribution.width-510.png';
    $nhsUrl   = $data['url'] ?? "https://www.nhs.uk/medicines/{$slug}/";

    echo json_encode([
        'source'       => 'nhs_api_v2',
        'name'         => $data['name'],
        'description'  => nhsMedClean($data['description'] ?? ''),
        'url'          => $nhsUrl,
        'last_reviewed'=> $data['lastReviewed']['endDate'] ?? null,
        'sections'     => $sections,
        'attribution'  => ['logo' => $logoUrl, 'url' => $nhsUrl],
    ]);
    exit;
}

// ── Fallback: scrape nhs.uk/medicines/ ─────────────────────────────
$pageUrl = "https://www.nhs.uk/medicines/{$slug}/";
$ctx     = stream_context_create(['http' => [
    'timeout'       => 8,
    'user_agent'    => 'HealthSphere/1.0',
    'ignore_errors' => true,
]]);
$html = @file_get_contents($pageUrl, false, $ctx);

if (!$html) {
    echo json_encode(['error' => "'{$drug}' not found. Try the generic name (e.g. 'paracetamol' instead of 'Calpol')."]);
    exit;
}

if (!empty($http_response_header) && str_contains($http_response_header[0], '404')) {
    echo json_encode(['error' => "'{$drug}' not found on NHS Medicines. Try the generic/active ingredient name."]);
    exit;
}

preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/i', $html, $metaM);
$description = html_entity_decode($metaM[1] ?? '', ENT_QUOTES);

preg_match_all('/<h2[^>]*>(.*?)<\/h2>(.*?)(?=<h2|<\/article|$)/si', $html, $sectionM, PREG_SET_ORDER);
$sections = [];
foreach (array_slice($sectionM, 0, 8) as $sec) {
    $heading = strip_tags($sec[1]);
    preg_match_all('/<p[^>]*>(.*?)<\/p>/si', $sec[2], $pm);
    $content = preg_replace('/\s+/', ' ', strip_tags(implode(' ', array_slice($pm[1], 0, 3))));
    if (strlen($heading) > 2 && strlen($content) > 30) {
        $sections[] = ['heading' => $heading, 'content' => mb_substr($content, 0, 450)];
    }
}

preg_match('/<h1[^>]*>(.*?)<\/h1>/si', $html, $titleM);
$name = strip_tags($titleM[1] ?? ucwords(str_replace('-', ' ', $slug)));

echo json_encode([
    'source'      => 'nhs_scrape',
    'name'        => $name,
    'description' => mb_substr($description, 0, 400),
    'url'         => $pageUrl,
    'sections'    => $sections,
    'attribution' => [
        'logo' => 'https://assets.nhs.uk/nhsuk-cms/images/nhs-attribution.width-510.png',
        'url'  => $pageUrl,
    ],
]);
