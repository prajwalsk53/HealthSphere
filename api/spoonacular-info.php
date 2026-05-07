<?php
/**
 * Spoonacular ingredient nutrition info.
 * Returns per-100g macros for a given ingredient id.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/ai.php';
requireLogin();
header('Content-Type: application/json');

$id  = (int)($_GET['id'] ?? 0);
$key = defined('SPOONACULAR_API_KEY') ? SPOONACULAR_API_KEY : '';

if (!$id || !$key) {
    echo json_encode(['error' => 'Missing id or API key.']);
    exit;
}

$url = "https://api.spoonacular.com/food/ingredients/{$id}/information?"
     . http_build_query([
         'amount' => 100,
         'unit'   => 'grams',
         'apiKey' => $key,
     ]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$raw      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$raw) {
    echo json_encode(['error' => 'Spoonacular API error ' . $httpCode]);
    exit;
}

$data      = json_decode($raw, true);
$nutrients = $data['nutrition']['nutrients'] ?? [];

// Helper to find nutrient value by name
$get = function(string $name) use ($nutrients): float {
    foreach ($nutrients as $n) {
        if (strcasecmp($n['name'], $name) === 0) return (float)($n['amount'] ?? 0);
    }
    return 0.0;
};

echo json_encode([
    'ok'               => true,
    'id'               => $id,
    'name'             => ucwords($data['name'] ?? ''),
    'calories_per_100g'=> round($get('Calories'),   1),
    'protein_g'        => round($get('Protein'),     1),
    'carbs_g'          => round($get('Carbohydrates'), 1),
    'sugar_g'          => round($get('Sugar'),       1),
    'fats_g'           => round($get('Fat'),         1),
    'fiber_g'          => round($get('Fiber'),       1),
    'sodium_mg'        => round($get('Sodium'),      1),
    'category_path'    => $data['categoryPath'] ?? [],
    'image'            => 'https://spoonacular.com/cdn/ingredients_100x100/' . ($data['image'] ?? 'food.jpg'),
]);
