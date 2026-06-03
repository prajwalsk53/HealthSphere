<?php
/**
 * NHS Website Content API v2 — Conditions proxy
 * Docs: https://digital.nhs.uk/developer/api-catalogue/nhs-website-content/v2
 *
 * Sandbox (no key): https://sandbox.api.service.nhs.uk/nhs-website-content/
 * Production (key): https://api.service.nhs.uk/nhs-website-content/
 * Header: apikey: YOUR_KEY
 */
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
header('Content-Type: application/json');

$condition = trim($_GET['condition'] ?? '');
if (!$condition) { echo json_encode(['error' => 'No condition specified']); exit; }

$slug   = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $condition), '-'));
$apiKey = defined('NHS_API_KEY') ? NHS_API_KEY : '';

$baseUrl = $apiKey
    ? 'https://api.service.nhs.uk/nhs-website-content'
    : 'https://sandbox.api.service.nhs.uk/nhs-website-content';

function nhsCondFetch(string $url, string $apiKey): ?array {
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

function nhsCondClean(mixed $val, int $max = 500): string {
    $text = is_array($val) ? implode(' ', $val) : (string)$val;
    $text = preg_replace('/\s+/', ' ', strip_tags($text));
    return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
}

// ── Try NHS Website Content API v2 ─────────────────────────────────
$data = nhsCondFetch("{$baseUrl}/conditions/{$slug}/", $apiKey);

if ($data && !empty($data['name'])) {
    $sections = [];
    foreach ($data['hasPart'] ?? [] as $part) {
        $heading = $part['headline'] ?? ($part['name'] ?? '');
        $body    = $part['text'] ?? ($part['description'] ?? '');
        $content = nhsCondClean($body);
        if ($heading && strlen($content) > 20) {
            $sections[] = ['heading' => $heading, 'content' => $content];
        }
    }

    $author  = $data['author'] ?? [];
    $nhsUrl  = $data['url'] ?? "https://www.nhs.uk/conditions/{$slug}/";

    echo json_encode([
        'source'        => 'nhs_api_v2',
        'name'          => $data['name'],
        'url'           => $nhsUrl,
        'summary'       => nhsCondClean($data['description'] ?? '', 600),
        'sections'      => $sections,
        'last_reviewed' => $data['lastReviewed']['endDate'] ?? null,
        'attribution'   => [
            'logo' => $author['logo'] ?? 'https://assets.nhs.uk/nhsuk-cms/images/nhs-attribution.width-510.png',
            'url'  => $nhsUrl,
        ],
    ]);
    exit;
}

// ── Fallback: scrape nhs.uk/conditions/ ─────────────────────────────
$pageUrl = "https://www.nhs.uk/conditions/{$slug}/";
$ctx     = stream_context_create(['http' => [
    'timeout'       => 8,
    'user_agent'    => 'HealthSphere/1.0',
    'ignore_errors' => true,
    'header'        => "Accept: text/html\r\n",
]]);
$html = @file_get_contents($pageUrl, false, $ctx);

if (!$html) {
    echo json_encode(['error' => 'Condition not found on NHS website', 'url' => $pageUrl]);
    exit;
}

preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches);
$condData = null;
foreach ($matches[1] as $block) {
    $d = json_decode(trim($block), true);
    if (!$d) continue;
    if (isset($d['@graph'])) {
        foreach ($d['@graph'] as $item) {
            if (isset($item['@type']) && in_array($item['@type'], ['MedicalCondition','MedicalWebPage','WebPage'])) {
                $condData = $item; break 2;
            }
        }
    }
    if (isset($d['@type']) && in_array($d['@type'], ['MedicalCondition','MedicalWebPage','WebPage','FAQPage'])) {
        $condData = $d; break;
    }
}

preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/i', $html, $metaM);
preg_match_all('/<h2[^>]*>(.*?)<\/h2>.*?<p[^>]*>(.*?)<\/p>/si', $html, $sections, PREG_SET_ORDER);
$sectionList = [];
foreach (array_slice($sections, 0, 6) as $sec) {
    $h = strip_tags($sec[1]);
    $t = preg_replace('/\s+/', ' ', strip_tags($sec[2]));
    if (strlen($h) > 2 && strlen($t) > 20) {
        $sectionList[] = ['heading' => $h, 'content' => mb_substr($t, 0, 300)];
    }
}

$name = $condData['name'] ?? ucwords(str_replace('-', ' ', $slug));
$desc = '';
if (!empty($condData['description'])) {
    $desc = is_array($condData['description'])
        ? strip_tags(implode(' ', $condData['description']))
        : strip_tags($condData['description']);
} elseif (!empty($metaM[1])) {
    $desc = $metaM[1];
}

echo json_encode([
    'source'      => 'nhs_scrape',
    'name'        => $name,
    'url'         => $pageUrl,
    'summary'     => mb_substr($desc, 0, 600),
    'sections'    => $sectionList,
    'attribution' => [
        'logo' => 'https://assets.nhs.uk/nhsuk-cms/images/nhs-attribution.width-510.png',
        'url'  => $pageUrl,
    ],
]);
