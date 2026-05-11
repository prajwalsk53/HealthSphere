<?php
/**
 * Safe Appetite — AI Ingredient Scanning API
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/ai.php';
requireRole('patient');

header('Content-Type: application/json');

$uid = getCurrentUser()['id'];

// ── Auto-create missing tables ────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS diet_preferences (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    preference VARCHAR(100) NOT NULL,
    is_active  TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_patient_pref (patient_id, preference),
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS food_intolerances (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    patient_id  INT NOT NULL,
    intolerance VARCHAR(100) NOT NULL,
    severity    ENUM('mild','moderate','severe') DEFAULT 'mild',
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_patient_intol (patient_id, intolerance),
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS ingredient_dislikes (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    ingredient VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS ingredient_scans (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    patient_id      INT NOT NULL,
    product_name    VARCHAR(200),
    ingredients_raw TEXT,
    scan_result     ENUM('safe','warning','danger') DEFAULT 'safe',
    alerts_json     TEXT,
    ai_summary      TEXT,
    scanned_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
)");

$body = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? 'scan';

// ── Save Preferences ─────────────────────────────────────────────────
if ($action === 'save_preferences') {
    try {
        // Diet preferences
        $pdo->prepare("DELETE FROM diet_preferences WHERE patient_id=?")->execute([$uid]);
        foreach (($body['diet_prefs'] ?? []) as $pref) {
            $pref = trim($pref);
            if ($pref) {
                $pdo->prepare("INSERT INTO diet_preferences (patient_id, preference) VALUES (?,?) ON DUPLICATE KEY UPDATE is_active=1")
                    ->execute([$uid, $pref]);
            }
        }

        // Food intolerances
        $pdo->prepare("DELETE FROM food_intolerances WHERE patient_id=?")->execute([$uid]);
        foreach (($body['intolerances'] ?? []) as $item) {
            $name = trim($item['name'] ?? '');
            $sev  = $item['severity'] ?? 'moderate';
            if ($name) {
                $pdo->prepare("INSERT INTO food_intolerances (patient_id, intolerance, severity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE severity=?, is_active=1")
                    ->execute([$uid, $name, $sev, $sev]);
            }
        }

        // Ingredient dislikes
        $pdo->prepare("DELETE FROM ingredient_dislikes WHERE patient_id=?")->execute([$uid]);
        foreach (($body['dislikes'] ?? []) as $d) {
            $d = trim($d);
            if ($d) {
                $pdo->prepare("INSERT INTO ingredient_dislikes (patient_id, ingredient) VALUES (?,?)")->execute([$uid, $d]);
            }
        }

        // Allergies — upsert food allergies
        foreach (($body['allergies'] ?? []) as $item) {
            $allergen = trim($item['allergen'] ?? '');
            $severity = $item['severity'] ?? 'moderate';
            if (!$allergen) continue;
            $exists = $pdo->prepare("SELECT id FROM allergies WHERE patient_id=? AND allergen=? AND allergy_type='food'");
            $exists->execute([$uid, $allergen]);
            if ($exists->fetch()) {
                $pdo->prepare("UPDATE allergies SET severity=?, is_active=1 WHERE patient_id=? AND allergen=? AND allergy_type='food'")
                    ->execute([$severity, $uid, $allergen]);
            } else {
                $pdo->prepare("INSERT INTO allergies (patient_id, allergen, allergy_type, severity, is_active) VALUES (?,?,'food',?,1)")
                    ->execute([$uid, $allergen, $severity]);
            }
        }

        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── Remove single allergy ─────────────────────────────────────────────
if ($action === 'remove_allergy') {
    $allergen = trim($body['allergen'] ?? '');
    if ($allergen) {
        $pdo->prepare("UPDATE allergies SET is_active=0 WHERE patient_id=? AND allergen=? AND allergy_type='food'")->execute([$uid, $allergen]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ── Ingredient Scan ───────────────────────────────────────────────────
if ($action === 'scan') {
    try {
    $productName    = trim($body['product_name'] ?? '');
    $ingredientsRaw = trim($body['ingredients'] ?? '');

    if (!$ingredientsRaw && !$productName) {
        echo json_encode(['error' => 'No ingredients provided.']);
        exit;
    }

    // Load user profile — wrap each query in try/catch so missing tables never crash the scan
    $allergies = [];
    try {
        $stmt = $pdo->prepare("SELECT allergen, severity FROM allergies WHERE patient_id=? AND is_active=1 AND allergy_type='food'");
        $stmt->execute([$uid]);
        $allergies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}

    $intolerances = [];
    try {
        $stmt = $pdo->prepare("SELECT intolerance, severity FROM food_intolerances WHERE patient_id=? AND is_active=1");
        $stmt->execute([$uid]);
        $intolerances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}

    $dietPrefs = [];
    try {
        $stmt = $pdo->prepare("SELECT preference FROM diet_preferences WHERE patient_id=? AND is_active=1");
        $stmt->execute([$uid]);
        $dietPrefs = array_column($stmt->fetchAll(), 'preference');
    } catch (Exception $e) {}

    $dislikes = [];
    try {
        $stmt = $pdo->prepare("SELECT ingredient FROM ingredient_dislikes WHERE patient_id=? AND is_active=1");
        $stmt->execute([$uid]);
        $dislikes = array_column($stmt->fetchAll(), 'ingredient');
    } catch (Exception $e) {}

    // ── Local rule-based ingredient scan ────────────────────────────────
    // Synonym/hidden-name dictionary: maps a canonical allergen key → all ingredient
    // strings that indicate its presence (checked case-insensitively).
    $ALLERGEN_SYNONYMS = [
        'peanuts'        => ['peanut','groundnut','arachis oil','monkey nut','earth nut','beer nuts'],
        'tree nuts'      => ['almond','cashew','walnut','pecan','pistachio','hazelnut','macadamia','brazil nut','pine nut','chestnut','coconut','praline','marzipan','nougat','gianduja'],
        'milk / dairy'   => ['milk','dairy','lactose','whey','casein','caseinate','lactalbumin','lactoglobulin','butter','cream','cheese','yogurt','ghee','lactulose','skimmed milk','dried milk','milk powder','milk solids','fromage'],
        'eggs'           => ['egg','albumin','globulin','lysozyme','mayonnaise','meringue','ovalbumin','ovomucin','ovomucoid','ovovitellin','silici albuminate','livetin','lecithin (egg)'],
        'wheat / gluten' => ['wheat','gluten','flour','semolina','spelt','kamut','durum','emmer','einkorn','farro','bulgur','seitan','triticum','bread crumbs','rusk','bran','starch (wheat)','wheat starch','wheat flour','modified wheat starch'],
        'soy'            => ['soy','soya','tofu','tempeh','miso','edamame','tamari','textured vegetable protein','tvp','soy sauce','soy lecithin','soybean','kinako'],
        'fish'           => ['fish','anchovy','anchovies','cod','haddock','salmon','tuna','trout','mackerel','sardine','herring','pilchard','plaice','sole','tilapia','worcestershire sauce','fish sauce','nam pla','nuoc mam','garum','caviar','roe'],
        'shellfish'      => ['shrimp','prawn','crab','lobster','crayfish','langoustine','scampi','barnacle','krill'],
        'sesame'         => ['sesame','tahini','til','gingelly','benne','sesame oil','sesame seed','sesame flour'],
        'mustard'        => ['mustard','mustard seed','mustard oil','mustard flour','mustard leaves'],
        'celery'         => ['celery','celeriac','celery seed','celery oil','celery salt'],
        'lupin'          => ['lupin','lupine','lupin flour','lupin seed'],
        'molluscs'       => ['squid','octopus','mussel','clam','oyster','scallop','abalone','snail','escargot','whelk','periwinkle'],
        'sulphites'      => ['sulphite','sulfite','sulphur dioxide','sulfur dioxide','e220','e221','e222','e223','e224','e225','e226','e227','e228','so2','sodium metabisulphite'],
    ];

    // Dietary preference violation rules
    $DIET_VIOLATIONS = [
        'Vegan'        => ['meat','chicken','beef','pork','lamb','fish','seafood','milk','dairy','egg','honey','gelatin','gelatine','lard','suet','tallow','casein','whey','albumin','anchovy','anchovies','isinglass','cochineal','carmine','e120','e441','l-cysteine','e920'],
        'Vegetarian'   => ['meat','chicken','beef','pork','lamb','fish','seafood','gelatin','gelatine','lard','suet','tallow','anchovy','anchovies','isinglass','cochineal','carmine','e120','e441','l-cysteine','e920'],
        'Pescatarian'  => ['meat','chicken','beef','pork','lamb','gelatin','gelatine','lard','suet','tallow'],
        'Gluten-Free'  => ['wheat','gluten','barley','rye','spelt','kamut','semolina','flour','bread','oat','oats','malt','triticale','farro','bulgur','seitan'],
        'Dairy-Free'   => ['milk','dairy','lactose','whey','casein','butter','cream','cheese','yogurt','ghee','lactalbumin'],
        'Keto'         => ['sugar','glucose','fructose','maltose','sucrose','dextrose','honey','maple syrup','corn syrup','wheat flour','rice','potato','bread','pasta','oat','cereal','starch'],
        'Halal'        => ['pork','lard','gelatin','gelatine','bacon','ham','pepperoni','salami','suet','tallow','e471 (pork)','alcohol','wine','beer','liqueur'],
        'Kosher'       => ['pork','bacon','ham','shellfish','shrimp','prawn','crab','lobster','crayfish','scallop'],
        'Low Sodium'   => ['salt','sodium','brine','monosodium glutamate','msg','soy sauce','miso','pickle'],
        'Low Sugar'    => ['sugar','glucose','fructose','maltose','sucrose','dextrose','honey','maple syrup','corn syrup','agave','molasses','treacle'],
    ];

    // Intolerance trigger keywords
    $INTOLERANCE_KEYWORDS = [
        'Lactose'              => ['lactose','milk','dairy','whey','casein','cream','butter','cheese','yogurt','lactulose'],
        'Gluten'               => ['gluten','wheat','barley','rye','spelt','semolina','flour','oat','malt','triticale'],
        'Fructose'             => ['fructose','high-fructose corn syrup','hfcs','honey','agave','apple','pear','mango','sorbitol','e420'],
        'Histamine'            => ['fermented','aged cheese','wine','beer','vinegar','sauerkraut','soy sauce','fish sauce','smoked','anchovy','anchovies','salami','pepperoni','spinach','tomato','aubergine'],
        'Caffeine'             => ['caffeine','coffee','tea','guarana','green tea extract','matcha','cola','cacao','chocolate','cocoa'],
        'Sorbitol'             => ['sorbitol','e420','polyol','xylitol','mannitol','maltitol','erythritol','isomalt'],
        'Salicylates'          => ['aspirin','mint','peppermint','tomato','berry','berries','grape','almonds','honey'],
        'Artificial Sweeteners'=> ['aspartame','saccharin','sucralose','acesulfame','acesulfame k','e951','e954','e955','stevia','steviol'],
        'MSG'                  => ['msg','monosodium glutamate','e621','glutamate','yeast extract','hydrolysed protein','hydrolyzed protein','autolysed yeast'],
        'Food Colourings'      => ['tartrazine','e102','sunset yellow','e110','carmoisine','e122','amaranth','e123','ponceau','e124','erythrosine','e127','allura red','e129','brilliant blue','e133','green s','e142','e102','artificial colour','food colouring','food color','artificial dye'],
    ];

    // ── Normalise ingredient list ─────────────────────────────────────
    // Split on commas, semicolons, full stops, parens content, and newlines
    $ingredientsText = strtolower($ingredientsRaw ?: $productName);
    // Strip E-number brackets for cleaner matching, keep the E-number itself
    $ingredientsText = preg_replace('/\(([^)]+)\)/', ' $1 ', $ingredientsText);
    // Tokenise
    $tokens = preg_split('/[,;\.\n\r\/]+/', $ingredientsText);
    $tokens = array_map('trim', $tokens);
    $tokens = array_filter($tokens);

    $alerts      = [];
    $flaggedKeys = []; // prevent duplicate alerts for same allergen

    // Helper: check if any token or the raw text contains the needle
    $rawLower = strtolower($ingredientsRaw . ' ' . $productName);
    $tokenContains = function(string $needle) use ($tokens, $rawLower): bool {
        foreach ($tokens as $t) {
            if (str_contains($t, $needle)) return true;
        }
        return str_contains($rawLower, $needle);
    };

    // 1. Check allergies (DANGER)
    foreach ($allergies as $a) {
        $aKey = strtolower($a['allergen']);
        $aLabel = $a['allergen'];
        $severity = $a['severity'];

        // Direct name match
        $foundIngredient = null;
        if ($tokenContains($aKey)) {
            $foundIngredient = $aLabel;
        }

        // Synonym map match
        if (!$foundIngredient) {
            foreach ($ALLERGEN_SYNONYMS as $mapKey => $synonyms) {
                if (str_contains($mapKey, $aKey) || str_contains($aKey, explode(' ', $mapKey)[0])) {
                    foreach ($synonyms as $syn) {
                        if ($tokenContains($syn)) {
                            $foundIngredient = ucfirst($syn);
                            break 2;
                        }
                    }
                }
            }
        }

        // Also try exact synonym lookup by allergen key
        if (!$foundIngredient && isset($ALLERGEN_SYNONYMS[$aKey])) {
            foreach ($ALLERGEN_SYNONYMS[$aKey] as $syn) {
                if ($tokenContains($syn)) {
                    $foundIngredient = ucfirst($syn);
                    break;
                }
            }
        }

        if ($foundIngredient && !isset($flaggedKeys['allergy_' . $aKey])) {
            $flaggedKeys['allergy_' . $aKey] = true;
            $alerts[] = [
                'type'       => 'DANGER',
                'ingredient' => $foundIngredient,
                'reason'     => "Contains your {$severity} {$aLabel} allergy.",
                'matches'    => "{$aLabel} allergy ({$severity})",
            ];
        }
    }

    // 2. Check intolerances (CAUTION)
    foreach ($intolerances as $intol) {
        $iKey = strtolower($intol['intolerance']);
        $keywords = $INTOLERANCE_KEYWORDS[$intol['intolerance']] ?? [$iKey];
        foreach ($keywords as $kw) {
            if ($tokenContains($kw) && !isset($flaggedKeys['intol_' . $iKey])) {
                $flaggedKeys['intol_' . $iKey] = true;
                $alerts[] = [
                    'type'       => 'CAUTION',
                    'ingredient' => ucfirst($kw),
                    'reason'     => "May trigger your {$intol['intolerance']} intolerance ({$intol['severity']}).",
                    'matches'    => "{$intol['intolerance']} intolerance",
                ];
                break;
            }
        }
    }

    // 3. Check dietary preference violations (CAUTION)
    foreach ($dietPrefs as $pref) {
        $violations = $DIET_VIOLATIONS[$pref] ?? [];
        foreach ($violations as $viol) {
            if ($tokenContains($viol) && !isset($flaggedKeys['diet_' . $pref . '_' . $viol])) {
                $flaggedKeys['diet_' . $pref . '_' . $viol] = true;
                $alerts[] = [
                    'type'       => 'CAUTION',
                    'ingredient' => ucfirst($viol),
                    'reason'     => "Not suitable for your {$pref} diet.",
                    'matches'    => "{$pref} preference",
                ];
            }
        }
    }

    // 4. Check ingredient dislikes (INFO)
    foreach ($dislikes as $dislike) {
        $dk = strtolower(trim($dislike));
        if ($dk && $tokenContains($dk) && !isset($flaggedKeys['dislike_' . $dk])) {
            $flaggedKeys['dislike_' . $dk] = true;
            $alerts[] = [
                'type'       => 'INFO',
                'ingredient' => ucfirst($dislike),
                'reason'     => "This product contains an ingredient you dislike.",
                'matches'    => "Personal dislike",
            ];
        }
    }

    // 5. Determine overall result
    $hasDanger  = !empty(array_filter($alerts, fn($a) => $a['type'] === 'DANGER'));
    $hasCaution = !empty(array_filter($alerts, fn($a) => $a['type'] === 'CAUTION'));
    $overall    = $hasDanger ? 'DANGER' : ($hasCaution ? 'CAUTION' : 'SAFE');
    $scanResult = strtolower($overall);

    // 6. Safe highlights — notable "free-from" aspects
    $safeHighlights = [];
    if (!$tokenContains('gluten') && !$tokenContains('wheat') && !$tokenContains('flour')) {
        $safeHighlights[] = 'No gluten detected';
    }
    if (!$tokenContains('milk') && !$tokenContains('dairy') && !$tokenContains('lactose') && !$tokenContains('whey') && !$tokenContains('casein')) {
        $safeHighlights[] = 'No dairy detected';
    }
    if (!$tokenContains('peanut') && !$tokenContains('groundnut')) {
        $safeHighlights[] = 'No peanuts detected';
    }
    if (!$tokenContains('egg') && !$tokenContains('albumin')) {
        $safeHighlights[] = 'No egg detected';
    }
    if (!$tokenContains('sugar') && !$tokenContains('glucose') && !$tokenContains('fructose') && !$tokenContains('sucrose')) {
        $safeHighlights[] = 'No added sugars detected';
    }
    // Only show a max of 3 highlights
    $safeHighlights = array_slice($safeHighlights, 0, 3);

    // 7. Build human-readable summary
    $productLabel = $productName ?: 'This product';
    if ($overall === 'SAFE') {
        $summary = "{$productLabel} appears safe based on your dietary profile. No allergens, intolerances, or dietary conflicts were detected in the ingredients list.";
        if (!$allergies && !$intolerances && !$dietPrefs) {
            $summary .= " Set up your allergy and diet profile for more personalised results.";
        }
        $tip = "Always double-check the label for 'may contain' warnings, especially for severe allergies.";
    } elseif ($overall === 'CAUTION') {
        $count = count(array_filter($alerts, fn($a) => $a['type'] === 'CAUTION'));
        $summary = "{$productLabel} contains {$count} ingredient(s) that may not suit your dietary needs. These are flagged as caution — they may cause discomfort but are not severe allergens for you.";
        $tip = "Check the full label and consider whether the quantity of these ingredients is significant.";
    } else {
        $count = count(array_filter($alerts, fn($a) => $a['type'] === 'DANGER'));
        $summary = "⚠️ {$productLabel} contains {$count} ingredient(s) that match your known food allerg" . ($count > 1 ? 'ies' : 'y') . ". Avoid this product.";
        $tip = "Look for an allergen-free alternative and always carry your medication if prescribed.";
    }

    $parsed = [
        'overall'         => $overall,
        'alerts'          => $alerts,
        'safe_highlights' => $safeHighlights,
        'summary'         => $summary,
        'tip'             => $tip,
    ];

    // Save scan to history — silently skip if table doesn't exist yet
    try {
        $pdo->prepare("INSERT INTO ingredient_scans (patient_id, product_name, ingredients_raw, scan_result, alerts_json, ai_summary) VALUES (?,?,?,?,?,?)")
            ->execute([
                $uid,
                $productName ?: 'Unknown Product',
                substr($ingredientsRaw, 0, 2000),
                $scanResult,
                json_encode($parsed['alerts'] ?? []),
                substr($parsed['summary'] ?? '', 0, 500),
            ]);
    } catch (Exception $e) {}

    echo json_encode(['ok' => true, 'result' => $parsed, 'scan_result' => $scanResult]);
    exit;
    } catch (Throwable $e) {
        echo json_encode(['error' => 'Scan error: ' . $e->getMessage()]);
        exit;
    }
}

// ── Delete scan history item ──────────────────────────────────────────
if ($action === 'delete_scan') {
    $scanId = (int)($body['scan_id'] ?? 0);
    if ($scanId) {
        $pdo->prepare("DELETE FROM ingredient_scans WHERE id=? AND patient_id=?")->execute([$scanId, $uid]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
