<?php
/**
 * Spoonacular ingredient search proxy.
 * Returns food items in the same shape as food-search.php so the
 * frontend can render them identically.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/ai.php';
requireLogin();
header('Content-Type: application/json');

$q   = trim($_GET['q'] ?? '');
$key = defined('SPOONACULAR_API_KEY') ? SPOONACULAR_API_KEY : '';

if (!$key) {
    echo json_encode([]);
    exit;
}

if ($q === '') {
    echo json_encode([]);
    exit;
}

// ── Ingredient search ─────────────────────────────────────────────────
$url = 'https://api.spoonacular.com/food/ingredients/search?'
     . http_build_query([
         'query'           => $q,
         'number'          => 10,
         'metaInformation' => 'true',
         'apiKey'          => $key,
     ]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);
$raw      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$raw) {
    echo json_encode([]);
    exit;
}

$data    = json_decode($raw, true);
$results = $data['results'] ?? [];

// Map Spoonacular aisle string → our category labels
function mapAisle(string $aisle): string {
    $aisle = strtolower($aisle);
    if (str_contains($aisle, 'meat') || str_contains($aisle, 'poultry'))     return 'Protein';
    if (str_contains($aisle, 'seafood') || str_contains($aisle, 'fish'))     return 'Fish';
    if (str_contains($aisle, 'dairy') || str_contains($aisle, 'cheese') || str_contains($aisle, 'egg')) return 'Dairy';
    if (str_contains($aisle, 'produce') || str_contains($aisle, 'vegetable')) return 'Vegetable';
    if (str_contains($aisle, 'fruit'))                                         return 'Fruit';
    if (str_contains($aisle, 'pasta') || str_contains($aisle, 'rice') || str_contains($aisle, 'cereal') || str_contains($aisle, 'grain') || str_contains($aisle, 'bread') || str_contains($aisle, 'bakery')) return 'Grain';
    if (str_contains($aisle, 'nut') || str_contains($aisle, 'seed'))          return 'Nuts & Seeds';
    if (str_contains($aisle, 'beverage') || str_contains($aisle, 'drink'))    return 'Drinks';
    if (str_contains($aisle, 'oil') || str_contains($aisle, 'fat'))           return 'Fats & Oils';
    if (str_contains($aisle, 'spice') || str_contains($aisle, 'herb'))        return 'Spices';
    if (str_contains($aisle, 'condiment') || str_contains($aisle, 'sauce'))   return 'Condiments';
    if (str_contains($aisle, 'sweet') || str_contains($aisle, 'candy') || str_contains($aisle, 'dessert')) return 'Sweets';
    return 'Other';
}

$foods = [];
foreach ($results as $item) {
    $aisle    = $item['aisle'] ?? '';
    $category = mapAisle($aisle);

    $foods[] = [
        'spoonacular_id'   => (int)$item['id'],
        'food_name'        => ucwords($item['name']),
        'category'         => $category,
        'image'            => 'https://spoonacular.com/cdn/ingredients_100x100/' . ($item['image'] ?? 'food.jpg'),
        // Macros unknown until info call — null signals "load on select"
        'calories_per_100g'=> null,
        'protein_g'        => null,
        'sugar_g'          => null,
        'fats_g'           => null,
        'fiber_g'          => null,
        'health_rating'    => 'good',
        'portion_size'     => '100g serving',
        'source'           => 'spoonacular',
    ];
}

echo json_encode($foods);
