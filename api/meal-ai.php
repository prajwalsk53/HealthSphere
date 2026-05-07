<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/ai.php';
requireLogin();

header('Content-Type: application/json');
$uid = (int)$_SESSION['user_id'];

$data    = json_decode(file_get_contents('php://input'), true) ?? [];
$message = trim($data['message'] ?? '');
$history = $data['history']  ?? [];

if (!$message) { echo json_encode(['error'=>'Empty message']); exit; }

// ── Patient context ────────────────────────────────────────────────
$u = $pdo->prepare("SELECT * FROM users WHERE id=?"); $u->execute([$uid]); $u=$u->fetch();

$allergies = $pdo->prepare("SELECT allergen, allergy_type, severity FROM allergies WHERE patient_id=? AND is_active=1");
$allergies->execute([$uid]); $allergies=$allergies->fetchAll();
$allergyList = $allergies ? implode(', ', array_map(fn($a)=>$a['allergen'].'('.$a['severity'].')', $allergies)) : 'None';

$meds = $pdo->prepare("SELECT medication_name FROM prescriptions WHERE patient_id=? AND is_active=1");
$meds->execute([$uid]); $meds=$meds->fetchAll();
$medList = $meds ? implode(', ', array_column($meds,'medication_name')) : 'None';

$metrics = $pdo->prepare("SELECT * FROM health_metrics WHERE patient_id=? ORDER BY metric_date DESC LIMIT 1");
$metrics->execute([$uid]); $metrics=$metrics->fetch();

$bpStr = $metrics ? $metrics['blood_pressure_systolic'].'/'.$metrics['blood_pressure_diastolic'].' mmHg' : 'N/A';

// Family risk summary
$famHighRisk = $pdo->prepare("SELECT condition_name FROM family_history WHERE patient_id=? AND condition_name REGEXP 'diabet|cholesterol|hypertension|heart|cancer' LIMIT 5");
$famHighRisk->execute([$uid]); $famHighRisk=$famHighRisk->fetchAll();
$famRisk = $famHighRisk ? implode(', ', array_column($famHighRisk,'condition_name')) : 'None';

// Today's calorie intake
$todayCal = (float)$pdo->query("SELECT COALESCE(SUM(calories),0) FROM diet_logs WHERE patient_id=$uid AND log_date=CURDATE()")->fetchColumn();

$systemPrompt = <<<PROMPT
You are HealthSphere Meal AI, a personalised nutrition and cooking assistant integrated into the NHS HealthSphere platform. You are helping {$u['first_name']} {$u['last_name']}.

PATIENT HEALTH CONTEXT:
- Known Allergies: {$allergyList}
- Current Medications: {$medList}
- Latest Blood Pressure: {$bpStr}
- Family Health Risks: {$famRisk}
- Calories logged today: {$todayCal} kcal (daily goal: 2500 kcal)

YOUR ROLE:
1. Answer meal preparation questions with clear, numbered step-by-step instructions
2. List all ingredients with quantities
3. Include cooking time, difficulty, and calorie estimate
4. Highlight how the meal benefits or affects the patient's specific health conditions
5. Warn if any ingredient conflicts with their allergies or medications
6. Suggest healthy modifications to make the recipe better for their health profile
7. For every recipe, include a YOUTUBE_SEARCH field with the ideal YouTube search query

RESPONSE FORMAT for recipe requests — always use this structure:
## [Recipe Name]
**⏱ Time:** X min | **🔥 Calories:** ~X kcal | **👨‍🍳 Difficulty:** Easy/Medium/Hard

### Ingredients
- [list with quantities]

### Steps
1. [step 1]
2. [step 2]
...

### 🥗 Health Notes for You
[personalised advice based on their health data]

### ⚠️ Allergy Check
[safe/warning based on their allergies]

### 📺 YOUTUBE_SEARCH: [exact search query for YouTube]

For non-recipe questions (nutrition advice, meal planning, etc.), answer conversationally but always reference their specific health data.

IMPORTANT: Always check allergies before recommending a recipe. If they have high BP, recommend low-sodium options. Given their family history of diabetes and heart disease, prefer whole grains, lean proteins, and Mediterranean-style meals.
PROMPT;

// Build messages
$messages = [];
foreach ($history as $h) {
    if (in_array($h['role']??'', ['user','assistant']) && !empty($h['content'])) {
        $messages[] = ['role'=>$h['role'], 'content'=>(string)$h['content']];
    }
}
$messages[] = ['role'=>'user', 'content'=>$message];

// ── Call Claude ────────────────────────────────────────────────────
$apiKey = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : '';
if ($apiKey) {
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>30,
        CURLOPT_HTTPHEADER=>[
            'x-api-key: '.$apiKey,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS=>json_encode([
            'model'=>AI_MODEL, 'max_tokens'=>1200,
            'system'=>$systemPrompt, 'messages'=>$messages,
        ]),
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code===200) {
        $result = json_decode($raw,true);
        $reply  = $result['content'][0]['text'] ?? '';
        if ($reply) {
            // Extract YouTube search query
            $ytSearch = '';
            if (preg_match('/📺 YOUTUBE_SEARCH:\s*(.+)/u', $reply, $m)) {
                $ytSearch = trim($m[1]);
                // Remove it from display text
                $reply = preg_replace('/\n?📺 YOUTUBE_SEARCH:\s*.+/u', '', $reply);
            }
            echo json_encode([
                'reply'    => $reply,
                'yt_search'=> $ytSearch,
                'yt_url'   => $ytSearch ? 'https://www.youtube.com/results?search_query='.urlencode($ytSearch) : '',
                'source'   => 'claude',
            ]);
            exit;
        }
    }
}

// ── Fallback: smart rule-based meal responses ──────────────────────
$q = strtolower($message);
$reply = '';
$ytSearch = '';

// Check if asking about a specific meal
$mealPatterns = [
    ['pattern'=>['grilled chicken','chicken breast'], 'recipe'=>grilled_chicken_recipe(),'yt'=>'grilled chicken healthy recipe NHS'],
    ['pattern'=>['salmon','grilled salmon'],           'recipe'=>salmon_recipe(),         'yt'=>'baked salmon healthy recipe'],
    ['pattern'=>['oatmeal','oat','porridge'],          'recipe'=>oatmeal_recipe(),        'yt'=>'healthy oatmeal recipe breakfast'],
    ['pattern'=>['salad','green salad'],               'recipe'=>salad_recipe(),          'yt'=>'healthy green salad recipe'],
    ['pattern'=>['quinoa'],                            'recipe'=>quinoa_recipe(),         'yt'=>'quinoa recipe healthy meal prep'],
];
foreach ($mealPatterns as $mp) {
    foreach ($mp['pattern'] as $pat) {
        if (str_contains($q,$pat)) { $reply=$mp['recipe']; $ytSearch=$mp['yt']; break 2; }
    }
}

if (!$reply) {
    if (str_contains($q,'recommend') || str_contains($q,'suggest') || str_contains($q,'what should')) {
        $reply = "**Personalised Meal Recommendations for {$u['first_name']}**\n\nBased on your health profile (BP: {$bpStr}, family history of hypertension and diabetes), here are today's suggestions:\n\n**Breakfast:** Porridge with berries and flaxseeds (low GI, heart-healthy)\n**Lunch:** Grilled salmon salad with quinoa and leafy greens\n**Dinner:** Chicken stir-fry with brown rice and broccoli\n**Snacks:** Handful of walnuts, Greek yogurt\n\nAsk me 'How to make [meal name]' for step-by-step instructions!";
    } elseif (str_contains($q,'calorie') || str_contains($q,'calories')) {
        $remaining = max(0, 2500 - $todayCal);
        $reply = "**Today's Calorie Summary**\n\nConsumed: **".round($todayCal)." kcal**\nRemaining: **".round($remaining)." kcal**\nDaily Goal: 2,500 kcal\n\nYou're ".($remaining>0?"".round($remaining)." kcal under goal — try a balanced dinner":"at your daily limit — stick to light snacks");
    } else {
        $reply = "Hi {$u['first_name']}! I can help you with:\n\n- **Recipe instructions** — try 'How do I make grilled salmon?'\n- **Meal recommendations** — try 'What should I eat today?'\n- **Calorie tracking** — try 'How many calories have I had?'\n- **Nutrition advice** — try 'What foods help with high blood pressure?'\n\nWhat would you like to know?";
    }
}

echo json_encode(['reply'=>$reply,'yt_search'=>$ytSearch,'yt_url'=>$ytSearch?'https://www.youtube.com/results?search_query='.urlencode($ytSearch):'','source'=>'local']);

function grilled_chicken_recipe(): string {
    return "## Grilled Chicken Breast\n**⏱ Time:** 25 min | **🔥 Calories:** ~165 kcal/100g | **👨‍🍳 Difficulty:** Easy\n\n### Ingredients\n- 2 chicken breasts (150g each)\n- 1 tbsp olive oil\n- 1 tsp paprika\n- ½ tsp garlic powder\n- ½ tsp black pepper\n- Fresh lemon juice\n- Fresh herbs (thyme/rosemary)\n\n### Steps\n1. Pat chicken dry with paper towel\n2. Mix olive oil, paprika, garlic powder and black pepper\n3. Coat chicken evenly with the mixture\n4. Preheat grill/pan to medium-high heat\n5. Cook 6-7 minutes each side until internal temp reaches 75°C\n6. Rest 5 minutes before serving\n7. Squeeze fresh lemon over before serving\n\n### 🥗 Health Notes\nHigh in protein (31g/100g), low in saturated fat. Excellent for managing blood pressure. Avoid adding salt — use herbs instead given your elevated BP reading.";
}
function salmon_recipe(): string {
    return "## Baked Lemon Herb Salmon\n**⏱ Time:** 20 min | **🔥 Calories:** ~208 kcal/100g | **👨‍🍳 Difficulty:** Easy\n\n### Ingredients\n- 2 salmon fillets (150g each)\n- 1 lemon (sliced)\n- 2 garlic cloves (minced)\n- 1 tbsp olive oil\n- Fresh dill or parsley\n- Black pepper\n\n### Steps\n1. Preheat oven to 200°C (180°C fan)\n2. Place salmon on lined baking tray\n3. Drizzle with olive oil, add garlic on top\n4. Layer lemon slices over fillets\n5. Season with black pepper and herbs\n6. Bake 12-15 minutes until flakes easily with a fork\n7. Serve with steamed greens or quinoa\n\n### 🥗 Health Notes\nRich in Omega-3 fatty acids — directly reduces cardiovascular risk from your family history. Also helps lower blood pressure naturally. Aim for 2 portions per week.";
}
function oatmeal_recipe(): string {
    return "## Heart-Healthy Porridge\n**⏱ Time:** 10 min | **🔥 Calories:** ~150 kcal | **👨‍🍳 Difficulty:** Easy\n\n### Ingredients\n- 40g rolled oats\n- 250ml semi-skimmed milk or oat milk\n- 1 tbsp flaxseeds\n- Handful of berries (blueberries/strawberries)\n- 1 tsp honey (optional)\n\n### Steps\n1. Add oats and milk to a saucepan\n2. Cook on medium heat 4-5 minutes, stirring constantly\n3. Add flaxseeds and stir in\n4. Pour into bowl\n5. Top with fresh berries\n6. Drizzle with honey if desired\n\n### 🥗 Health Notes\nBeta-glucan in oats lowers LDL cholesterol by up to 10%. Given your family history of high cholesterol and heart disease, this is an excellent breakfast choice. Low GI keeps blood sugar stable.";
}
function salad_recipe(): string {
    return "## Mediterranean Power Salad\n**⏱ Time:** 15 min | **🔥 Calories:** ~280 kcal | **👨‍🍳 Difficulty:** Easy\n\n### Ingredients\n- 100g mixed leafy greens (spinach, rocket)\n- ½ cucumber, sliced\n- 10 cherry tomatoes\n- ¼ red onion, thinly sliced\n- 30g walnuts\n- 1 tbsp olive oil\n- 1 tbsp balsamic vinegar\n- Optional: 50g feta cheese\n\n### Steps\n1. Wash and dry all vegetables\n2. Combine greens, cucumber, tomatoes and onion in a bowl\n3. Toast walnuts in dry pan 2-3 minutes\n4. Make dressing: mix olive oil and balsamic\n5. Add walnuts to salad, drizzle dressing\n6. Toss gently and serve immediately\n\n### 🥗 Health Notes\nLeafy greens contain nitrates that directly lower blood pressure. Walnuts provide Omega-3s. Avoid adding salt given your BP reading.";
}
function quinoa_recipe(): string {
    return "## Quinoa & Roasted Vegetable Bowl\n**⏱ Time:** 30 min | **🔥 Calories:** ~380 kcal | **👨‍🍳 Difficulty:** Easy\n\n### Ingredients\n- 75g dry quinoa\n- 1 red pepper, diced\n- 1 courgette, sliced\n- 1 red onion, quartered\n- 1 tbsp olive oil\n- 1 tsp cumin\n- Handful of spinach\n\n### Steps\n1. Preheat oven to 200°C\n2. Rinse quinoa, cook in 150ml water for 15 min\n3. Toss vegetables in olive oil and cumin\n4. Roast vegetables 20 minutes until golden\n5. Combine cooked quinoa with roasted veg\n6. Stir in fresh spinach (wilts from heat)\n7. Season with black pepper and lemon juice\n\n### 🥗 Health Notes\nQuinoa is a complete protein and low GI — ideal for diabetes prevention given your family history. High in magnesium which supports blood pressure regulation.";
}
