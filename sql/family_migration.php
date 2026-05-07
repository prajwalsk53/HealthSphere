<?php
$pdo = new PDO('mysql:host=localhost;dbname=healthsphere;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

// 1. Extend ENUM
$pdo->exec("ALTER TABLE family_history MODIFY COLUMN relation ENUM(
  'father','mother','brother','sister',
  'grandfather','grandmother',
  'grandfather_paternal','grandmother_paternal',
  'grandfather_maternal','grandmother_maternal',
  'uncle_paternal','aunt_paternal',
  'uncle_maternal','aunt_maternal',
  'cousin_paternal','cousin_maternal',
  'other'
) NOT NULL DEFAULT 'other'");
echo "Enum extended\n";

// 2. Remove duplicates
$pdo->exec("DELETE fh FROM family_history fh
  INNER JOIN (
    SELECT MIN(id) keep_id, patient_id, relation, COALESCE(relation_name,'') rn, condition_name
    FROM family_history
    GROUP BY patient_id, relation, COALESCE(relation_name,''), condition_name
    HAVING COUNT(*) > 1
  ) d ON fh.patient_id=d.patient_id AND fh.relation=d.relation
    AND COALESCE(fh.relation_name,'')=d.rn
    AND fh.condition_name=d.condition_name
    AND fh.id != d.keep_id");
echo "Duplicates removed\n";

// 3. Insert extended family
$ins = $pdo->prepare("INSERT IGNORE INTO family_history
  (patient_id,relation,relation_name,condition_name,year_diagnosed,year_deceased,notes)
  VALUES (?,?,?,?,?,?,?)");

$rows = [
  // PATERNAL GRANDPARENTS
  [2,'grandfather_paternal','George Patel','Coronary Heart Disease',1962,2008,'Severe CAD, bypass surgery. Strong hereditary component.'],
  [2,'grandfather_paternal','George Patel','Type 2 Diabetes',1970,2008,'Insulin-dependent in later years.'],
  [2,'grandfather_paternal','George Patel','Hypertension',1958,2008,'Lifelong, BP frequently above 160/100.'],
  [2,'grandmother_paternal','Margaret Patel','Hypertension',1975,2015,'Controlled with medication from age 52.'],
  [2,'grandmother_paternal','Margaret Patel','Rheumatoid Arthritis',1980,2015,'Autoimmune, hands and knees affected.'],
  [2,'grandmother_paternal','Margaret Patel','Osteoporosis',1990,2015,'Diagnosed after hip fracture, severe bone loss.'],
  // MATERNAL GRANDPARENTS
  [2,'grandfather_maternal','Raj Sharma','Ischaemic Stroke',1982,2018,'Major stroke at 62. Partial left-side paralysis.'],
  [2,'grandfather_maternal','Raj Sharma','High Cholesterol',1972,2018,'LDL above 5.2 mmol/L. Statin therapy from 1980.'],
  [2,'grandfather_maternal','Raj Sharma','Atrial Fibrillation',1978,2018,'Paroxysmal AF, cardioversion twice.'],
  [2,'grandmother_maternal','Priya Sharma','Type 2 Diabetes',1988,null,'Well-controlled with metformin.'],
  [2,'grandmother_maternal','Priya Sharma','Hypothyroidism',1978,null,'Levothyroxine since diagnosis.'],
  [2,'grandmother_maternal','Priya Sharma','Macular Degeneration',2002,null,'Dry AMD, vision loss in left eye.'],
  // FATHERS SIBLINGS
  [2,'uncle_paternal','Vikram Patel','Type 2 Diabetes',2005,null,'Diagnosed at 42, managed with diet and metformin.'],
  [2,'uncle_paternal','Vikram Patel','Hypertension',2008,null,'BP 145/92 at diagnosis, on Ramipril 5mg.'],
  [2,'uncle_paternal','Vikram Patel','Non-alcoholic Fatty Liver Disease',2018,null,'Grade 2 NAFLD related to metabolic syndrome.'],
  [2,'aunt_paternal','Nita Patel','Breast Cancer (Stage 2)',2010,null,'ER+ invasive ductal carcinoma. Currently in remission.'],
  [2,'aunt_paternal','Nita Patel','BRCA2 Gene Variant',2011,null,'Identified during genetic testing. Consider counselling.'],
  [2,'aunt_paternal','Nita Patel','Hypertension',2015,null,'Developed post-chemotherapy, on Amlodipine.'],
  // MOTHERS SIBLINGS
  [2,'uncle_maternal','Arjun Sharma','Asthma (Moderate)',1985,null,'Exercise-induced and seasonal. One hospital admission 2002.'],
  [2,'uncle_maternal','Arjun Sharma','Atopic Eczema',1980,null,'Chronic, managed with topical steroids.'],
  [2,'uncle_maternal','Arjun Sharma','Allergic Rhinitis',1980,null,'Pollen and dust mite triggered.'],
  [2,'aunt_maternal','Sunita Sharma','Papillary Thyroid Cancer',2016,null,'Successful thyroidectomy, annual thyroglobulin monitoring.'],
  [2,'aunt_maternal','Sunita Sharma','Hypothyroidism',2016,null,'Post-thyroidectomy, well-controlled on levothyroxine.'],
  [2,'aunt_maternal','Sunita Sharma','Generalised Anxiety Disorder',2012,null,'CBT and low-dose sertraline.'],
  // PATERNAL COUSINS
  [2,'cousin_paternal','Kiran Patel (Vikram\'s Son)','Obesity (BMI 34)',2019,null,'Class 2 obesity, NHS weight management programme.'],
  [2,'cousin_paternal','Kiran Patel (Vikram\'s Son)','Pre-Diabetes',2021,null,'Fasting glucose 6.4 mmol/L, lifestyle intervention.'],
  [2,'cousin_paternal','Riya Patel (Nita\'s Daughter)','BRCA2 Carrier',2020,null,'Prophylactic testing, annual MRI screening.'],
  [2,'cousin_paternal','Riya Patel (Nita\'s Daughter)','Anxiety Disorder',2019,null,'Likely triggered by BRCA2 result, counselling ongoing.'],
  // MATERNAL COUSINS
  [2,'cousin_maternal','Dev Sharma (Arjun\'s Son)','Mild Asthma',2005,null,'Inherited from father, well-controlled with preventer inhaler.'],
  [2,'cousin_maternal','Dev Sharma (Arjun\'s Son)','Atopic Eczema',2003,null,'Mild, managed with emollients.'],
  [2,'cousin_maternal','Aisha Sharma (Sunita\'s Daughter)','Hashimoto\'s Thyroiditis',2023,null,'Autoimmune, possibly hereditary given family pattern.'],
  [2,'cousin_maternal','Aisha Sharma (Sunita\'s Daughter)','Generalised Anxiety',2022,null,'Mild-moderate, receiving CBT.'],
];

$ok=0; $skip=0;
foreach ($rows as $r) {
    try { $ins->execute($r); $ok++; }
    catch(Exception $e) { $skip++; }
}
echo "Inserted: $ok  Skipped: $skip\n";
echo "All done!\n";
