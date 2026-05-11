<?php
/**
 * NHS Hospital & GP finder via OpenStreetMap (Nominatim + Overpass)
 * No API key required — fully free and open
 */
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
header('Content-Type: application/json');

$postcode = trim($_GET['postcode'] ?? '');
$type     = $_GET['type'] ?? 'all'; // all | hospital | gp | pharmacy

if (!$postcode) { echo json_encode(['error' => 'Postcode required']); exit; }

// Step 1 — Geocode postcode to lat/lng via Nominatim
$geocodeUrl = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
    'q'              => $postcode . ', UK',
    'format'         => 'json',
    'limit'          => 1,
    'countrycodes'   => 'gb',
]);
$ctx = stream_context_create(['http' => [
    'timeout'    => 6,
    'user_agent' => 'HealthSphere/1.0 (Educational project)',
]]);
$geoRaw = @file_get_contents($geocodeUrl, false, $ctx);
if (!$geoRaw) { echo json_encode(['error' => 'Could not geocode postcode']); exit; }
$geoData = json_decode($geoRaw, true);
if (empty($geoData)) { echo json_encode(['error' => 'Postcode not found']); exit; }

$lat = (float)$geoData[0]['lat'];
$lng = (float)$geoData[0]['lon'];
$radius = 5000; // 5km

// Step 2 — Build Overpass query based on type
$typeFilters = match($type) {
    'hospital'  => '["amenity"="hospital"]',
    'gp'        => '["amenity"="doctors"]',
    'pharmacy'  => '["amenity"="pharmacy"]',
    default     => '["amenity"~"hospital|doctors|pharmacy|clinic"]',
};

$query = <<<OVP
[out:json][timeout:12];
(
  node{$typeFilters}(around:{$radius},{$lat},{$lng});
  way{$typeFilters}(around:{$radius},{$lat},{$lng});
  relation{$typeFilters}(around:{$radius},{$lat},{$lng});
);
out center tags;
OVP;

$overpassUrl = 'https://overpass-api.de/api/interpreter';
$ctxPost = stream_context_create(['http' => [
    'method'     => 'POST',
    'timeout'    => 15,
    'user_agent' => 'HealthSphere/1.0 (Educational project)',
    'header'     => "Content-Type: application/x-www-form-urlencoded\r\n",
    'content'    => 'data=' . urlencode($query),
]]);
$overpassRaw = @file_get_contents($overpassUrl, false, $ctxPost);
if (!$overpassRaw) { echo json_encode(['error' => 'OpenStreetMap service unavailable']); exit; }

$overpassData = json_decode($overpassRaw, true);
if (empty($overpassData['elements'])) {
    echo json_encode(['results' => [], 'center' => ['lat' => $lat, 'lng' => $lng]]);
    exit;
}

// Step 3 — Normalise results
$results = [];
foreach ($overpassData['elements'] as $el) {
    $tags = $el['tags'] ?? [];
    $name = $tags['name'] ?? ($tags['operator'] ?? '');
    if (!$name) continue;

    // Get lat/lng (nodes have it directly, ways/relations have center)
    $elLat = $el['lat'] ?? ($el['center']['lat'] ?? null);
    $elLng = $el['lon'] ?? ($el['center']['lon'] ?? null);
    if (!$elLat || !$elLng) continue;

    // Determine type
    $amenity = $tags['amenity'] ?? 'other';
    $typeLabel = match($amenity) {
        'hospital'  => 'hospital',
        'doctors'   => 'gp',
        'pharmacy'  => 'pharmacy',
        'clinic'    => 'gp',
        default     => 'other',
    };

    // Calculate distance (Haversine)
    $R = 6371;
    $dLat = deg2rad($elLat - $lat);
    $dLng = deg2rad($elLng - $lng);
    $a = sin($dLat/2)*sin($dLat/2) + cos(deg2rad($lat))*cos(deg2rad($elLat))*sin($dLng/2)*sin($dLng/2);
    $dist = round($R * 2 * atan2(sqrt($a), sqrt(1-$a)), 2);

    $isNHS = isset($tags['operator']) && stripos($tags['operator'], 'NHS') !== false;

    $results[] = [
        'name'     => $name,
        'type'     => $typeLabel,
        'subtype'  => $tags['healthcare'] ?? $tags['amenity'] ?? '',
        'lat'      => $elLat,
        'lng'      => $elLng,
        'address'  => trim(implode(', ', array_filter([
            $tags['addr:housenumber'] ?? '', $tags['addr:street'] ?? '',
            $tags['addr:city'] ?? '', $tags['addr:postcode'] ?? '',
        ]))),
        'phone'    => $tags['phone'] ?? $tags['contact:phone'] ?? '',
        'hours'    => $tags['opening_hours'] ?? '',
        'website'  => $tags['website'] ?? $tags['contact:website'] ?? '',
        'nhs'      => $isNHS,
        'distance' => $dist,
        'operator' => $tags['operator'] ?? '',
    ];
}

// Sort by distance
usort($results, fn($a, $b) => $a['distance'] <=> $b['distance']);

echo json_encode([
    'results' => array_slice($results, 0, 25),
    'center'  => ['lat' => $lat, 'lng' => $lng],
    'postcode'=> strtoupper($postcode),
    'total'   => count($results),
]);
