<?php
/**
 * MedlinePlus Genetics search proxy.
 * Uses NLM Web Search (free, no key) then fetches condition JSON.
 */
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');
$type  = $_GET['type'] ?? 'search'; // search | detail
$slug  = trim($_GET['slug'] ?? '');

if (!$query && !$slug) { echo json_encode(['results' => []]); exit; }

// ── Detail: fetch full condition JSON by slug ───────────────────
if ($type === 'detail' && $slug) {
    $url = "https://medlineplus.gov/download/genetics/condition/{$slug}.json";
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'user_agent' => 'HealthSphere/1.0']]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) { echo json_encode(['error' => 'Condition not found']); exit; }
    $data = json_decode($raw, true);
    if (!$data) { echo json_encode(['error' => 'Invalid response']); exit; }

    $inheritanceMap = [
        'autosomal dominant'  => 'autosomal_dominant',
        'autosomal recessive' => 'autosomal_recessive',
        'x-linked'            => 'x_linked',
        'x linked'            => 'x_linked',
        'mitochondrial'       => 'mitochondrial',
        'complex'             => 'complex',
        'multifactorial'      => 'complex',
    ];
    $inheritanceRaw = strtolower($data['inheritance'] ?? '');
    $inheritance = 'complex';
    foreach ($inheritanceMap as $k => $v) {
        if (strpos($inheritanceRaw, $k) !== false) { $inheritance = $v; break; }
    }

    $symptoms = '';
    if (!empty($data['signs_and_symptoms'])) {
        $items = array_slice($data['signs_and_symptoms'], 0, 8);
        $symptoms = implode(', ', array_map(function($s) { return $s['text'] ?? ''; }, $items));
    } elseif (!empty($data['summary'])) {
        $symptoms = substr(strip_tags($data['summary']), 0, 300);
    }

    $genes = [];
    if (!empty($data['genes'])) {
        foreach (array_slice($data['genes'], 0, 6) as $g) {
            $genes[] = $g['symbol'] ?? '';
        }
    }

    echo json_encode([
        'name'              => $data['name'] ?? ucwords(str_replace('-', ' ', $slug)),
        'slug'              => $slug,
        'inheritance'       => $inheritance,
        'inheritance_label' => ucwords(str_replace('_', ' ', $inheritance)),
        'symptoms'          => $symptoms,
        'genes'             => implode(', ', array_filter($genes)),
        'summary'           => substr(strip_tags($data['summary'] ?? ''), 0, 500),
        'url'               => "https://medlineplus.gov/genetics/condition/{$slug}/",
        'synonyms'          => implode(', ', array_slice($data['synonyms'] ?? [], 0, 3)),
    ]);
    exit;
}

// ── Search: MedlinePlus Genetics via site-restricted search ─────────
// Try NLM wsearch with MedlinePlus database (ghr was retired in 2023)
$results = [];

$searchUrl = 'https://wsearch.nlm.nih.gov/ws/query?' . http_build_query([
    'db'      => 'MedlinePlus',
    'term'    => $query . ' genetics condition',
    'retmax'  => 15,
    'rettype' => 'brief',
]);
$ctx = stream_context_create(['http' => ['timeout' => 10, 'user_agent' => 'HealthSphere/1.0 (Educational)']]);
$xml = @file_get_contents($searchUrl, false, $ctx);

if ($xml) {
    libxml_use_internal_errors(true);
    $xmlObj = simplexml_load_string($xml);
    if ($xmlObj) {
        foreach ($xmlObj->list->document ?? [] as $docNode) {
            $title = $urlRaw = $snippet = '';
            foreach ($docNode->content as $c) {
                $name = (string)$c['name'];
                if ($name === 'title')    $title   = strip_tags((string)$c);
                if ($name === 'url')      $urlRaw  = (string)$c;
                if ($name === 'snippets') $snippet = strip_tags((string)$c);
            }
            if (preg_match('/genetics\/condition\/([a-z0-9\-]+)\/?/', $urlRaw, $m)) {
                $results[] = [
                    'name'    => $title,
                    'slug'    => $m[1],
                    'snippet' => substr($snippet, 0, 200),
                    'url'     => $urlRaw,
                ];
            }
        }
    }
}

// ── Fallback: try direct slug guess from query ───────────────────────
if (empty($results)) {
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $query), '-'));
    $testUrl = "https://medlineplus.gov/download/genetics/condition/{$slug}.json";
    $testCtx = stream_context_create(['http' => ['timeout' => 6, 'user_agent' => 'HealthSphere/1.0']]);
    $raw = @file_get_contents($testUrl, false, $testCtx);
    if ($raw) {
        $data = json_decode($raw, true);
        if (!empty($data['name'])) {
            $results[] = [
                'name'    => $data['name'],
                'slug'    => $slug,
                'snippet' => substr(strip_tags($data['summary'] ?? ''), 0, 200),
                'url'     => "https://medlineplus.gov/genetics/condition/{$slug}/",
            ];
        }
    }
}

echo json_encode(['results' => $results]);
