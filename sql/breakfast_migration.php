<?php
/**
 * Breakfast Foods Expansion — UK Full English, Continental & South Indian
 * Run once: http://localhost/HealthSphere/sql/breakfast_migration.php
 */
require_once __DIR__ . '/../config/config.php';

$foods = [

  // ══════════════════════════════════════════════════════════════════
  // UK FULL ENGLISH BREAKFAST
  // ══════════════════════════════════════════════════════════════════
  ['FD301','Fried Eggs',          'UK Breakfast',196,14,0.4,15,0,  147,'good',    'High Cholesterol','Egg',  'Vit D, B12, Choline, Selenium',  '2 eggs / 120g'],
  ['FD302','Scrambled Eggs',      'UK Breakfast',149,10,1.0,11,0,  140,'good',    'High Cholesterol','Egg',  'Vit D, B12, Choline',            '2 eggs / 120g'],
  ['FD303','Poached Eggs',        'UK Breakfast',143,13,0.4,10,0,  124,'good',    'High Cholesterol','Egg',  'Vit D, B12, Choline, Selenium',  '2 eggs / 120g'],
  ['FD304','Back Bacon (Grilled)','UK Breakfast',215,25,0.0,13,0,  1650,'moderate','High BP, Heart','None', 'B1, B6, B12, Selenium, Zinc',    '2 rashers / 60g'],
  ['FD305','Pork Sausages',       'UK Breakfast',301,13,2.0,25,0.5,830,'moderate','Heart Disease','None',   'B1, B12, Zinc, Iron',            '2 sausages / 100g'],
  ['FD306','Baked Beans',         'UK Breakfast', 81, 5,5.0, 0.4,3.8,  480,'good',    'IBS (gas)','None',    'Iron, Fibre, Folate, Protein',   '200g / ½ tin'],
  ['FD307','Grilled Tomatoes',    'UK Breakfast', 22, 1,3.0, 0.3,1.2,    5,'excellent','Acid reflux','None', 'Lycopene, Vit C, K',             '2 halves / 100g'],
  ['FD308','Fried Mushrooms',     'UK Breakfast', 87, 2.5,1.0,7,1.5,    2,'good',    'None','None',         'Selenium, B2, B3, Vit D',        '80g / 1 portion'],
  ['FD309','Black Pudding',       'UK Breakfast',297,13,0.5,22,0.5,1000,'poor',    'Heart Disease, High BP','None','Iron, B12, Zinc',          '2 slices / 60g'],
  ['FD310','Toast with Butter',   'UK Breakfast',305, 9,4.0,14,2.5, 480,'moderate','Gluten, Lactose','Dairy, Gluten','B vits, Iron',           '2 slices / 80g'],
  ['FD311','Hash Browns',         'UK Breakfast',250, 3,0.5,14,2.5, 400,'moderate','Diabetes (starch)','None','Vit C, B6, Potassium',         '2 pieces / 100g'],
  ['FD312','Omelette (Plain)',    'UK Breakfast',154,11,0.4,12,0,  150,'good',    'High Cholesterol','Egg',  'Vit D, B12, Choline',            '2-egg / 120g'],

  // ══════════════════════════════════════════════════════════════════
  // UK CONTINENTAL & LIGHT BREAKFAST
  // ══════════════════════════════════════════════════════════════════
  ['FD313','Porridge with Milk',    'UK Breakfast',130, 5,5.0, 3,2.5,  50,'excellent','Gluten (oat sens.)','Dairy, Gluten','Beta-glucan, B vits, Calcium','250ml bowl / 40g oats'],
  ['FD314','Cereal with Milk',      'UK Breakfast',130, 5,10,  2,2.0, 180,'moderate','Diabetes','Dairy, Gluten','B vits, Calcium, Iron',          '30g cereal + 150ml milk'],
  ['FD315','Granola & Yogurt',      'UK Breakfast',210, 8,12,  7,3.0,  80,'moderate','Nuts, Dairy','Dairy, Gluten, Nuts','Calcium, Probiotics, Fibre',  '45g granola + 150g yogurt'],
  ['FD316','Toast with Jam',        'UK Breakfast',270, 7,15,  4,2.5, 350,'moderate','Diabetes, Gluten','Gluten','B vits, Iron (small)',           '2 slices / 80g'],
  ['FD317','Toast with Marmalade',  'UK Breakfast',265, 7,14,  4,2.5, 350,'moderate','Diabetes, Gluten','Gluten','B vits, Iron (small)',           '2 slices / 80g'],
  ['FD318','Crumpets',              'UK Breakfast',185, 7,3.0, 0.5,1.5,500,'moderate','Gluten, High sodium','Gluten','B vits, Iron',                '2 crumpets / 100g'],
  ['FD319','English Muffins',       'UK Breakfast',230, 8,4.0, 2,2.0, 400,'moderate','Gluten','Gluten','Iron, B vits, Calcium',                  '1 muffin / 58g'],
  ['FD320','Fruit Salad (Fresh)',   'UK Breakfast', 55, 0.7,12,0.2,2.0,   5,'excellent','Diabetes (fructose)','None','Vit C, Folate, Potassium',    '150g / 1 bowl'],
  ['FD321','Yogurt Bowl',           'UK Breakfast', 80, 5,8.0, 1,0.5,  60,'excellent','Lactose intol.','Dairy','Calcium, Probiotics, B12',         '150g / 1 bowl'],
  ['FD322','Smoothie (Fruit)',      'UK Breakfast', 60, 1,12,  0.3,1.5, 10,'good',    'Diabetes (sugar)','None','Vit C, Potassium, Antioxidants',  '250ml / 1 glass'],
  ['FD323','Croissant',             'UK Breakfast',406, 8,6.0,22,2.0, 400,'poor',    'Weight gain, Heart','Gluten, Dairy','Vit B1, Iron (small)',  '1 croissant / 57g'],
  ['FD324','Pain au Chocolat',      'UK Breakfast',383, 7,12, 20,1.5, 300,'poor',    'Diabetes, Weight gain','Gluten, Dairy','Iron (small)',        '1 piece / 85g'],
  ['FD325','Danish Pastry',         'UK Breakfast',370, 6,15, 18,1.0, 350,'poor',    'Diabetes, Weight gain','Gluten, Dairy, Egg','Vit B1',        '1 piece / 90g'],
  ['FD326','Scones with Jam & Cream','UK Breakfast',364,7,9.0,15,2.0,  450,'poor',  'Diabetes, Weight gain','Gluten, Dairy, Egg','Calcium, Iron',  '1 scone / 60g'],
  ['FD327','Tea Cakes',             'UK Breakfast',270, 7,12,  4,2.2, 300,'moderate','Gluten, Diabetes','Gluten, Dairy','B vits, Iron',            '1 tea cake / 55g'],
  ['FD328','Hot Cross Buns',        'UK Breakfast',289, 7.5,18,7,2.5, 350,'moderate','Gluten, Diabetes','Gluten, Dairy, Egg','B vits, Iron',       '1 bun / 80g'],
  ['FD329','Sweet Muffins',         'UK Breakfast',380, 6,30, 15,1.0, 400,'poor',    'Diabetes, Weight gain','Gluten, Dairy, Egg','B vits',         '1 muffin / 130g'],
  ['FD330','Banana Bread (Slice)',  'UK Breakfast',326, 5,30, 14,2.0, 260,'poor',    'Diabetes, Weight gain','Gluten, Egg, Dairy','Potassium, B6', '1 slice / 80g'],
  ['FD331','Brioche Toast',         'UK Breakfast',370, 9,8.0,18,1.5, 400,'poor',    'Weight gain, Heart','Gluten, Dairy, Egg','B vits',            '2 slices / 70g'],
  ['FD332','Pancakes (Plain)',      'UK Breakfast',227, 6,8.0, 7,0.8, 330,'moderate','Gluten, Diabetes','Gluten, Dairy, Egg','Calcium, B vits',     '2 pancakes / 120g'],

  // ══════════════════════════════════════════════════════════════════
  // UK COOKED BREAKFAST SANDWICHES & WRAPS
  // ══════════════════════════════════════════════════════════════════
  ['FD333','Egg Sandwich',          'UK Breakfast',230,13,3.0, 9,1.5, 450,'good',    'Gluten, Cholesterol','Gluten, Egg','Protein, B12, B vits',   '1 sandwich / 140g'],
  ['FD334','Bacon Sandwich (Bap)',  'UK Breakfast',248,14,3.0,10,1.8, 950,'moderate','High BP, Heart','Gluten','Protein, B1, B12',                 '1 bap / 160g'],
  ['FD335','Sausage Sandwich',      'UK Breakfast',290,12,3.0,14,2.0, 700,'moderate','Heart Disease','Gluten','B1, B12, Protein',                  '1 sandwich / 180g'],
  ['FD336','Beans on Toast',        'UK Breakfast',160, 8,5.0, 2,4.0, 480,'good',    'Gluten, IBS','Gluten','Fibre, Iron, Protein, Folate',        '200g beans + 2 toast'],
  ['FD337','Avocado Toast',         'UK Breakfast',200, 6,1.5,10,5.0, 300,'excellent','Gluten','Gluten','Healthy fats, Fibre, Vit K, Folate',      '2 slices + ½ avocado'],
  ['FD338','Cheese Toastie',        'UK Breakfast',300,14,2.0,15,1.5, 600,'moderate','Heart, Lactose','Gluten, Dairy','Calcium, Protein, B12',     '1 toastie / 130g'],
  ['FD339','Breakfast Wrap',        'UK Breakfast',250,14,3.0,12,2.0, 650,'moderate','High Cholesterol','Gluten, Egg, Dairy','Protein, B vits',    '1 wrap / 170g'],
  ['FD340','Egg & Soldiers',        'UK Breakfast',200,13,2.0, 9,1.5, 350,'good',    'High Cholesterol','Gluten, Egg','Vit D, B12, Choline',       '1 egg + 2 toast strips'],

  // ══════════════════════════════════════════════════════════════════
  // SOUTH INDIAN BREAKFAST
  // ══════════════════════════════════════════════════════════════════
  ['FD341','Idli (Steamed)',        'South Indian', 58, 2.1,0.4, 0.2,0.5,  180,'excellent','None','None','Iron (small), Probiotics (fermented)','2 idlis / 80g'],
  ['FD342','Plain Dosa',            'South Indian',133, 3.8,0.4, 2.5,0.5,  300,'good',    'None','None','B1, Iron, Carbohydrates',               '1 dosa / 90g'],
  ['FD343','Masala Dosa',           'South Indian',163, 4.5,1.0, 5,1.8,   350,'good',    'None','None','Vit B6, Iron, Potassium, Fibre',         '1 dosa / 180g'],
  ['FD344','Rava Dosa',             'South Indian',145, 3.5,0.5, 4,0.5,   320,'good',    'Gluten (rava)','Gluten','B1, Iron',                     '1 dosa / 90g'],
  ['FD345','Uttapam',               'South Indian',100, 3.5,1.0, 2,1.0,   250,'good',    'None','None','B vits, Vit C (tomato topping), Iron',   '1 uttapam / 100g'],
  ['FD346','Medu Vada',             'South Indian',197, 8.4,0.8, 9,2.2,   400,'good',    'IBS (lentils)','None','Protein, Iron, B1, B6',           '2 vadas / 100g'],
  ['FD347','Upma (Rava/Semolina)',  'South Indian',140, 3.5,1.0, 5,1.5,   300,'good',    'Gluten (rava)','Gluten','B1, Iron, Protein',             '1 bowl / 150g'],
  ['FD348','Poha (Flattened Rice)', 'South Indian',110, 2.1,0.5, 2.5,0.6, 200,'good',    'None','None','Iron (fortified), B vits',               '1 bowl / 120g'],
  ['FD349','Ven Pongal',            'South Indian',150, 4,0.5, 5,1.0,    200,'good',    'None','None','Protein, B1, Iron, Zinc',                  '1 bowl / 150g'],
  ['FD350','Appam',                 'South Indian',120, 3,1.0, 1.5,0.5,  150,'good',    'None','None','Carbohydrates, B vits, Iron',               '2 appams / 100g'],
  ['FD351','Puttu (Steamed)',       'South Indian',145, 3,0.5, 0.5,1.2,   50,'excellent','None','None','Iron, Fibre, B vits',                     '1 cylinder / 100g'],
  ['FD352','Idiyappam (String Hoppers)','South Indian',120,2,0.2,0.4,0.3,  20,'excellent','None','None','Carbohydrates (light, easy to digest)',  '3 pieces / 100g'],
  ['FD353','Pesarattu (Moong Dosa)','South Indian',140, 8,0.5, 2,3.0,    250,'excellent','None','None','Protein, Iron, Folate, Fibre',            '1 dosa / 100g'],
  ['FD354','Rava Idli',             'South Indian',100, 3.5,0.5, 2,0.5,  300,'good',    'Gluten (rava)','Gluten','B1, Iron',                      '2 idlis / 80g'],
  ['FD355','Set Dosa (Soft)',       'South Indian',155, 4,0.5, 4,0.5,    250,'good',    'None','None','B1, Iron, Carbohydrates',                  '3 small dosas / 120g'],
  ['FD356','Oothappam / Onion Uttapam','South Indian',95,3.2,2.0,2,1.2,  240,'good',    'None','None','Vit C, B vits, Iron (onion adds quercetin)','1 piece / 100g'],
  ['FD357','Sambar',                'South Indian', 45, 2.5,2.0, 1.5,2.5, 300,'excellent','IBS (lentils)','None','Iron, Fibre, Folate, Antioxidants','1 bowl / 150ml'],
  ['FD358','Coconut Chutney',       'South Indian',200, 2,2.0,18,3.0,    150,'good',    'None','None','Healthy fats, Manganese, Lauric acid',    '2 tbsp / 30g'],
  ['FD359','Tomato Chutney',        'South Indian', 60, 1,5.0, 3,1.5,    200,'excellent','Acid reflux','None','Lycopene, Vit C, K',               '2 tbsp / 40g'],
  ['FD360','Curd Rice (Thayir Sadam)','South Indian',130,4,3.0, 2,0.3,    50,'good',    'Lactose intol.','Dairy','Probiotics, Calcium, B12',       '1 bowl / 200g'],
  ['FD361','Mysore Masala Dosa',    'South Indian',190, 5,1.5, 8,2.0,    400,'good',    'None','None','Vit B6, Iron, Potassium, Fibre',          '1 large dosa / 220g'],
  ['FD362','Kesari Bath (Semolina Halwa)','South Indian',280,3,28,12,0.5, 100,'poor',   'Diabetes, Weight gain','Gluten','Vit A (saffron), Iron', '1 serving / 80g'],
  ['FD363','Semiya Upma (Vermicelli)','South Indian',160,4,2.0, 4,1.0,   250,'good',    'Gluten','Gluten','B1, Iron',                            '1 bowl / 150g'],
  ['FD364','Banana Appam (Sweet Paniyaram)','South Indian',170,3,10, 5,1.0,60,'moderate','Diabetes','None','Potassium, B6, Carbohydrates',         '4 pieces / 80g'],
];

$stmt = $pdo->prepare("INSERT IGNORE INTO food_database
  (food_code,food_name,category,calories_per_100g,protein_g,sugar_g,fats_g,fiber_g,sodium_mg,health_rating,avoid_if,allergy_risk,vitamins_minerals,portion_size)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

$added = $skipped = 0;
$errors = [];
foreach ($foods as $f) {
    try {
        $stmt->execute($f);
        $stmt->rowCount() ? $added++ : $skipped++;
    } catch (PDOException $e) {
        $errors[] = $f[1] . ': ' . $e->getMessage();
    }
}

echo '<pre style="font-family:monospace;padding:24px;font-size:13px;line-height:1.8;">';
echo "✅ Breakfast Foods Migration Complete!\n";
echo str_repeat('─', 50) . "\n";
echo "Added:   {$added} new food items\n";
echo "Skipped: {$skipped} (already exist)\n\n";

$total = $pdo->query("SELECT COUNT(*) FROM food_database")->fetchColumn();
echo "Total foods in database: {$total}\n\n";

echo "Breakdown by category:\n";
$cats = $pdo->query("SELECT category, COUNT(*) as n FROM food_database GROUP BY category ORDER BY n DESC")->fetchAll();
foreach ($cats as $c) {
    echo "  " . str_pad($c['category'], 30) . $c['n'] . " foods\n";
}

if ($errors) {
    echo "\nErrors (" . count($errors) . "):\n";
    foreach ($errors as $e) echo "  ✗ $e\n";
}
echo '</pre>';
