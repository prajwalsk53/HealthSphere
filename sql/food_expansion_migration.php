<?php
/**
 * Food Database Expansion — adds 70+ common foods
 * Run once: http://localhost/HealthSphere/sql/food_expansion_migration.php
 */
require_once __DIR__ . '/../config/config.php';

$foods = [
  // ── Proteins ──────────────────────────────────────────────────────
  ['FD201','Chicken Breast (Grilled)','Protein',165,31,0,3.6,0,74,'good','High Cholesterol','None','B12, Iron, Zinc','150g / 1 breast'],
  ['FD202','Beef Mince (Lean)','Protein',215,26,0,12,0,75,'moderate','High Cholesterol','None','Iron, B12, Zinc','100g / 1 serving'],
  ['FD203','Turkey Breast','Protein',135,30,0,1,0,69,'good','Gout','None','B6, B12, Selenium','150g / 1 serving'],
  ['FD204','Tuna (Canned in Water)','Fish',116,26,0,1,0,330,'excellent','Gout, High Mercury','Fish','Omega-3, B12, Selenium','100g / ½ tin'],
  ['FD205','Eggs (Whole)','Protein',155,13,0.4,11,0,124,'good','High Cholesterol','Egg','Choline, Vit D, B12','2 eggs / 120g'],
  ['FD206','Egg Whites','Protein',52,11,0.7,0.2,0,166,'excellent','None','Egg','Protein, B2','3 whites / 100g'],
  ['FD207','Prawns / Shrimp','Fish',99,24,0,0.3,0,111,'excellent','Gout','Shellfish','Selenium, B12, Iodine','100g / 8 large'],
  ['FD208','Lentils (Cooked)','Legume',116,9,1.8,0.4,7.9,2,'excellent','None','None','Iron, Folate, Fibre','150g / ½ cup'],
  ['FD209','Chickpeas (Cooked)','Legume',164,9,7.9,2.6,7.6,24,'excellent','IBS (gas)','None','Iron, Folate, Manganese','150g / ½ cup'],
  ['FD210','Black Beans (Cooked)','Legume',132,8.9,0.3,0.5,8.7,1,'excellent','IBS','None','Iron, Magnesium, Folate','150g / ½ cup'],
  ['FD211','Tofu (Firm)','Protein',76,8,0.9,4.2,0.3,7,'excellent','None','Soy','Calcium, Iron, Manganese','100g / ½ block'],
  ['FD212','Cottage Cheese','Dairy',98,11,3.4,4.3,0,364,'good','Lactose intol.','Dairy','Calcium, B12, Selenium','100g / ½ cup'],
  ['FD213','Cheddar Cheese','Dairy',403,25,0.5,33,0,621,'moderate','High Cholesterol','Dairy','Calcium, Vit A, B12','30g / 1 slice'],
  ['FD214','Pork Tenderloin','Protein',143,26,0,3.5,0,62,'good','High Cholesterol','None','B1, B6, Selenium, Zinc','150g / 1 serving'],

  // ── Grains ────────────────────────────────────────────────────────
  ['FD215','Quinoa (Cooked)','Grain',120,4.4,0,1.9,2.8,7,'excellent','None','None','Manganese, Phosphorus, Folate','75g dry / 1 cup cooked'],
  ['FD216','White Rice (Cooked)','Grain',130,2.7,0,0.3,0.4,5,'moderate','Diabetes (monitor)','None','B vitamins, Iron','75g dry / 1 cup cooked'],
  ['FD217','Pasta (Whole Wheat, Cooked)','Grain',124,5,0,0.5,4.5,3,'good','Gluten intol.','Gluten','Manganese, Selenium, B vits','75g dry / 1 cup cooked'],
  ['FD218','Pasta (White, Cooked)','Grain',131,5,0.6,1.1,1.8,1,'moderate','Diabetes, Gluten intol.','Gluten','Selenium, B vitamins','75g dry / 1 cup cooked'],
  ['FD219','Whole Wheat Bread','Grain',247,13,5,3.4,6.9,472,'good','Diabetes, Gluten intol.','Gluten','Fibre, Iron, B vitamins','30g / 1 slice'],
  ['FD220','Rolled Oats (Dry)','Grain',389,17,1.1,7,10.6,2,'excellent','Coeliac','Gluten (oats)','Manganese, Phosphorus, Beta-glucan','40g / 1 bowl cooked'],
  ['FD221','Granola','Grain',471,10,18,20,5.3,24,'moderate','Diabetes, Weight gain','Gluten, Nuts','Manganese, Fibre','45g / ¼ cup'],
  ['FD222','Cornflakes','Grain',357,7.5,8.9,0.4,1.2,800,'moderate','Diabetes, High BP','Gluten (malt)','B vitamins, Iron','30g / 1 bowl'],
  ['FD223','Sweet Potato (Baked)','Vegetable',90,2,4.2,0.1,3,36,'excellent','Diabetes (moderate)','None','Vit A, B6, Potassium, Fibre','150g / 1 medium'],
  ['FD224','Potato (Boiled)','Vegetable',87,1.9,0.9,0.1,1.8,6,'good','Diabetes (monitor)','None','Vit C, B6, Potassium','150g / 1 medium'],

  // ── Vegetables ────────────────────────────────────────────────────
  ['FD225','Tomatoes','Vegetable',18,0.9,2.6,0.2,1.2,5,'excellent','Acid reflux','None','Lycopene, Vit C, K','80g / 1 medium'],
  ['FD226','Cucumber','Vegetable',15,0.7,1.7,0.1,0.5,2,'excellent','None','None','Vit K, Hydration','80g / ½ medium'],
  ['FD227','Carrots (Raw)','Vegetable',41,0.9,4.7,0.2,2.8,69,'excellent','None','None','Vit A, K, Biotin','80g / 1 medium'],
  ['FD228','Bell Pepper (Red)','Vegetable',31,1,4.2,0.3,2.1,4,'excellent','None','None','Vit C (3x orange), B6, Folate','80g / ½ pepper'],
  ['FD229','Mushrooms (Button)','Vegetable',22,3.1,1,0.3,1,5,'excellent','None','None','Selenium, B2, B3, Vit D','80g / 5 mushrooms'],
  ['FD230','Cauliflower','Vegetable',25,1.9,1.9,0.3,2,30,'excellent','IBS (gas)','None','Vit C, K, B6, Folate','80g / 1 cup florets'],
  ['FD231','Kale (Raw)','Vegetable',49,4.3,0.9,0.9,3.6,38,'excellent','Blood thinners (Vit K)','None','Vit K, A, C, Calcium, Iron','80g / 2 cups'],
  ['FD232','Green Beans','Vegetable',31,1.8,3.3,0.1,2.7,6,'excellent','None','None','Vit K, C, Folate','80g / 1 cup'],
  ['FD233','Courgette (Zucchini)','Vegetable',17,1.2,1.7,0.3,1,8,'excellent','None','None','Vit C, B6, Potassium','80g / 1 medium'],
  ['FD234','Onion','Vegetable',40,1.1,4.2,0.1,1.7,4,'excellent','IBS (FODMAP)','None','Quercetin, Vit C, B6','80g / 1 medium'],
  ['FD235','Lettuce (Mixed Leaves)','Vegetable',15,1.4,1.2,0.2,1.3,28,'excellent','None','None','Vit K, A, Folate','80g / 2 cups'],
  ['FD236','Peas (Frozen)','Vegetable',81,5.4,3.7,0.4,5.5,108,'excellent','None','None','Vit K, C, Thiamin, Folate','80g / ½ cup'],
  ['FD237','Sweetcorn (Cooked)','Vegetable',96,3.4,5,1.5,2.7,15,'good','Diabetes (monitor)','None','Vit B1, B5, Folate, Manganese','80g / 1 cob'],

  // ── Fruits ────────────────────────────────────────────────────────
  ['FD238','Apple','Fruit',52,0.3,10.4,0.2,2.4,1,'excellent','None','None','Vit C, Quercetin, Fibre','120g / 1 medium'],
  ['FD239','Banana','Fruit',89,1.1,12.2,0.3,2.6,1,'good','Diabetes (ripe ones)','None','Potassium, B6, Vit C, Magnesium','120g / 1 medium'],
  ['FD240','Orange','Fruit',47,0.9,8.5,0.1,2.4,0,'excellent','Acid reflux','None','Vit C, Folate, Thiamin','130g / 1 medium'],
  ['FD241','Strawberries','Fruit',32,0.7,4.9,0.3,2,1,'excellent','None','None','Vit C, Manganese, Folate','80g / 8 berries'],
  ['FD242','Blueberries','Fruit',57,0.7,10,0.3,2.4,1,'excellent','None','None','Vit C, K, Manganese, Anthocyanins','80g / ½ cup'],
  ['FD243','Grapes','Fruit',69,0.7,15.5,0.2,0.9,2,'moderate','Diabetes','None','Vit K, C, Resveratrol','80g / 15 grapes'],
  ['FD244','Mango','Fruit',60,0.8,13.7,0.4,1.6,1,'good','Diabetes (monitor)','None','Vit A, C, B6, Folate','80g / ½ mango'],
  ['FD245','Pineapple','Fruit',50,0.5,9.9,0.1,1.4,1,'excellent','None','None','Vit C, Manganese, Bromelain','80g / 2 rings'],
  ['FD246','Pear','Fruit',57,0.4,9.8,0.1,3.1,1,'excellent','IBS (fructose)','None','Vit C, K, Copper, Fibre','120g / 1 medium'],
  ['FD247','Watermelon','Fruit',30,0.6,6.2,0.2,0.4,1,'excellent','Diabetes (monitor)','None','Vit C, A, Lycopene, Citrulline','200g / 2 cups'],
  ['FD248','Kiwi','Fruit',61,1.1,9,0.5,3,3,'excellent','Latex allergy','None','Vit C (2x orange), K, Folate','80g / 1 large'],

  // ── Dairy & Eggs ──────────────────────────────────────────────────
  ['FD249','Whole Milk','Dairy',61,3.2,4.8,3.3,0,44,'good','Lactose intol.','Dairy','Calcium, B12, Vit D','200ml / 1 glass'],
  ['FD250','Skimmed Milk','Dairy',35,3.4,4.8,0.2,0,44,'excellent','Lactose intol.','Dairy','Calcium, B12, Vit D','200ml / 1 glass'],
  ['FD251','Butter','Fats & Oils',717,0.9,0.1,81,0,11,'poor','High Cholesterol, Heart Disease','Dairy','Vit A, E, K2','10g / 1 tsp'],
  ['FD252','Mozzarella','Dairy',280,28,1.2,17,0,627,'moderate','High Cholesterol','Dairy','Calcium, Phosphorus, Zinc','30g / 2 slices'],

  // ── Nuts, Seeds & Oils ────────────────────────────────────────────
  ['FD253','Almonds','Nuts & Seeds',579,21,4.4,50,12.5,1,'excellent','Nut allergy','Tree Nuts','Vit E, Magnesium, Fibre','28g / small handful'],
  ['FD254','Walnuts','Nuts & Seeds',654,15,2.6,65,6.7,2,'excellent','Nut allergy','Tree Nuts','Omega-3, Manganese, Copper','28g / small handful'],
  ['FD255','Cashews','Nuts & Seeds',553,18,5.9,44,3.3,12,'good','Nut allergy','Tree Nuts','Copper, Magnesium, Manganese','28g / small handful'],
  ['FD256','Peanut Butter (Natural)','Nuts & Seeds',588,25,3.6,50,6,17,'good','Peanut allergy','Peanuts','Niacin, Vit E, Magnesium','32g / 2 tbsp'],
  ['FD257','Sunflower Seeds','Nuts & Seeds',584,21,2.6,51,8.6,9,'excellent','Seed allergy','None','Vit E, Selenium, Magnesium','28g / 2 tbsp'],
  ['FD258','Olive Oil','Fats & Oils',884,0,0,100,0,2,'good','High Calorie','None','Vit E, K, Polyphenols','15ml / 1 tbsp'],
  ['FD259','Chia Seeds','Nuts & Seeds',486,17,0,31,34,16,'excellent','None','None','Omega-3, Fibre, Calcium, Iron','28g / 2 tbsp'],
  ['FD260','Flaxseeds','Nuts & Seeds',534,18,1.6,42,27,30,'excellent','None','None','Omega-3, Fibre, Lignans','14g / 1 tbsp'],

  // ── Snacks & Others ───────────────────────────────────────────────
  ['FD261','Hummus','Condiments',166,8,3,9.6,6,303,'good','None','Sesame','Iron, Fibre, Folate','30g / 2 tbsp'],
  ['FD262','Dark Chocolate (70%+)','Sweets',598,8,24,43,11,20,'moderate','Caffeine sens., Migraines','None','Iron, Magnesium, Antioxidants','30g / 2 squares'],
  ['FD263','Honey','Sweets',304,0.3,82,0,0.2,4,'moderate','Diabetes, Infants','None','Antioxidants, small trace minerals','21g / 1 tbsp'],
  ['FD264','Orange Juice (Fresh)','Drinks',45,0.7,8.4,0.2,0.2,1,'good','Diabetes, Acid reflux','None','Vit C, Folate, Potassium','200ml / 1 glass'],
  ['FD265','Protein Shake (Whey)','Protein',400,80,5,5,2,200,'good','Kidney disease','Dairy','Protein, BCAAs, Calcium','30g / 1 scoop'],
  ['FD266','Energy Bar (Generic)','Processed',380,8,30,12,4,150,'moderate','Diabetes, Weight gain','Gluten, Nuts','Varies by brand','50g / 1 bar'],
  ['FD267','Soup (Tomato, Canned)','Processed',56,1.1,7.1,1.8,0.5,450,'moderate','High BP, IBS','None','Vit C, Lycopene','240ml / 1 serving'],
  ['FD268','Vegetable Stir-Fry (Mixed)','Vegetable',50,2.5,5,1.5,3,200,'excellent','None','Soy (sauce)','Vit C, A, Fibre, Iron','150g / 1 portion'],
];

$stmt = $pdo->prepare("INSERT IGNORE INTO food_database (food_code,food_name,category,calories_per_100g,protein_g,sugar_g,fats_g,fiber_g,sodium_mg,health_rating,avoid_if,allergy_risk,vitamins_minerals,portion_size) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

$added = 0;
$errors = [];
foreach ($foods as $f) {
    try {
        $stmt->execute($f);
        if ($stmt->rowCount()) $added++;
    } catch (PDOException $e) {
        $errors[] = $f[1] . ': ' . $e->getMessage();
    }
}

echo '<pre style="font-family:monospace;padding:20px;font-size:13px;">';
echo "✅ Food Database Expansion complete!\n\n";
echo "Added:  {$added} new food items\n";
echo "Total now available: " . $pdo->query("SELECT COUNT(*) FROM food_database")->fetchColumn() . " foods\n\n";

if ($errors) {
    echo "Errors (" . count($errors) . "):\n";
    foreach ($errors as $e) echo "  - $e\n";
}

echo "\nCategories added:\n";
$cats = $pdo->query("SELECT category, COUNT(*) as n FROM food_database GROUP BY category ORDER BY n DESC")->fetchAll();
foreach ($cats as $c) echo "  {$c['category']}: {$c['n']} items\n";
echo '</pre>';
