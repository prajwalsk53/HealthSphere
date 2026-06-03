<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();
header('Content-Type: application/json');

$q    = trim(preg_replace('/[^\w\s\-]/', '', $_GET['q'] ?? ''));
$type = in_array($_GET['type'] ?? '', ['recall', 'event', 'label']) ? ($_GET['type']) : 'label';

if ($q === '') { echo json_encode(['error' => 'No drug name provided']); exit; }

function fdaFetch(string $url): ?array {
    $ctx = stream_context_create(['http' => [
        'timeout'       => 10,
        'ignore_errors' => true,
        'header'        => "User-Agent: HealthSphere/1.0\r\n",
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return null;
    return json_decode($raw, true) ?: null;
}

function truncate(string $s, int $n = 500): string {
    $s = preg_replace('/\s+/', ' ', trim($s));
    return mb_strlen($s) > $n ? mb_substr($s, 0, $n) . '…' : $s;
}

function fieldVal(array $r, string $key, int $maxChars = 500): ?string {
    return isset($r[$key]) ? truncate(implode(' ', (array)$r[$key]), $maxChars) : null;
}

$enc  = rawurlencode('"' . $q . '"');
$enc2 = rawurlencode($q);

switch ($type) {

    // ── Drug recalls ────────────────────────────────────────────────
    case 'recall':
        $data = fdaFetch("https://api.fda.gov/drug/recall.json?search=product_description:{$enc}&limit=5");
        if (empty($data['results'])) {
            $data = fdaFetch("https://api.fda.gov/drug/recall.json?search=product_description:{$enc2}&limit=5");
        }
        $recalls = [];
        foreach ($data['results'] ?? [] as $r) {
            $recalls[] = [
                'date'           => substr($r['report_date'] ?? '', 0, 8),
                'reason'         => $r['reason_for_recall'] ?? '',
                'status'         => $r['status'] ?? '',
                'product'        => $r['product_description'] ?? '',
                'classification' => $r['classification'] ?? '',
            ];
        }
        echo json_encode(['type' => 'recall', 'drug' => $q, 'recalls' => $recalls, 'count' => count($recalls)]);
        exit;

    // ── Top reported adverse events ──────────────────────────────────
    case 'event':
        $data = fdaFetch("https://api.fda.gov/drug/event.json?search=patient.drug.medicinalproduct:{$enc}&count=patient.reaction.reactionmeddrapt.exact");
        if (empty($data['results'])) {
            $data = fdaFetch("https://api.fda.gov/drug/event.json?search=patient.drug.medicinalproduct:{$enc2}&count=patient.reaction.reactionmeddrapt.exact");
        }
        $reactions = array_slice($data['results'] ?? [], 0, 12);
        echo json_encode(['type' => 'event', 'drug' => $q, 'reactions' => $reactions]);
        exit;

    // ── Drug label (default) ─────────────────────────────────────────
    default:
        $data = fdaFetch("https://api.fda.gov/drug/label.json?search=(openfda.brand_name:{$enc}+openfda.generic_name:{$enc})&limit=1");
        if (empty($data['results'])) {
            $data = fdaFetch("https://api.fda.gov/drug/label.json?search={$enc2}&limit=1");
        }
        $r = $data['results'][0] ?? null;
        if (!$r) {
            echo json_encode(['error' => "No FDA label found for \"{$q}\". Try the generic or brand name."]);
            exit;
        }
        $openfda = $r['openfda'] ?? [];
        echo json_encode([
            'type'              => 'label',
            'drug'              => $q,
            'brand_name'        => $openfda['brand_name'][0] ?? null,
            'generic_name'      => $openfda['generic_name'][0] ?? null,
            'manufacturer'      => $openfda['manufacturer_name'][0] ?? null,
            'route'             => $openfda['route'][0] ?? null,
            'indications'       => fieldVal($r, 'indications_and_usage'),
            'warnings'          => fieldVal($r, 'warnings', 400),
            'adverse_reactions' => fieldVal($r, 'adverse_reactions', 400),
            'dosage'            => fieldVal($r, 'dosage_and_administration', 300),
            'drug_interactions' => fieldVal($r, 'drug_interactions', 400),
            'contraindications' => fieldVal($r, 'contraindications', 400),
            'purpose'           => fieldVal($r, 'purpose', 200),
        ]);
}
