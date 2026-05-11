<?php
/**
 * NHS Condition Info proxy
 * Fetches real condition data from nhs.uk structured JSON-LD (no key needed)
 */
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
header('Content-Type: application/json');

$condition = trim($_GET['condition'] ?? '');
if (!$condition) { echo json_encode(['error' => 'No condition specified']); exit; }

// Convert condition name to NHS URL slug
$slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $condition));
$slug = trim($slug, '-');

$url = "https://www.nhs.uk/conditions/{$slug}/";
$ctx = stream_context_create(['http' => [
    'timeout'    => 8,
    'user_agent' => 'HealthSphere/1.0 (Educational project)',
    'header'     => "Accept: text/html\r\n",
]]);

$html = @file_get_contents($url, false, $ctx);

if (!$html) {
    echo json_encode(['error' => 'Condition not found on NHS website', 'url' => $url]);
    exit;
}

// Extract all JSON-LD blocks
preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches);

$conditionData = null;
foreach ($matches[1] as $block) {
    $data = json_decode(trim($block), true);
    if (!$data) continue;
    // Handle @graph arrays
    if (isset($data['@graph'])) {
        foreach ($data['@graph'] as $item) {
            if (isset($item['@type']) && in_array($item['@type'], ['MedicalCondition','MedicalWebPage','WebPage'])) {
                $conditionData = $item;
                break 2;
            }
        }
    }
    if (isset($data['@type']) && in_array($data['@type'], ['MedicalCondition','MedicalWebPage','WebPage','FAQPage'])) {
        $conditionData = $data;
        break;
    }
}

// Extract meta description as fallback summary
preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/i', $html, $metaMatch);
$metaDesc = $metaMatch[1] ?? '';

// Extract main headings and first paragraphs for content
preg_match_all('/<h2[^>]*>(.*?)<\/h2>.*?<p[^>]*>(.*?)<\/p>/si', $html, $sections, PREG_SET_ORDER);
$sectionList = [];
foreach (array_slice($sections, 0, 6) as $sec) {
    $heading = strip_tags($sec[1]);
    $text    = strip_tags($sec[2]);
    if (strlen($heading) > 2 && strlen($text) > 20) {
        $sectionList[] = ['heading' => $heading, 'text' => substr($text, 0, 300)];
    }
}

// Build response
$name = $conditionData['name'] ?? ucwords(str_replace('-', ' ', $slug));
$desc = '';
if (!empty($conditionData['description'])) {
    $desc = is_array($conditionData['description'])
        ? strip_tags(implode(' ', $conditionData['description']))
        : strip_tags($conditionData['description']);
} elseif (!empty($conditionData['about']['description'])) {
    $desc = strip_tags($conditionData['about']['description']);
} elseif ($metaDesc) {
    $desc = $metaDesc;
}

echo json_encode([
    'name'     => $name,
    'url'      => $url,
    'summary'  => substr($desc, 0, 600),
    'sections' => $sectionList,
    'source'   => 'NHS UK',
]);
