-- ══════════════════════════════════════════════════════
--  HealthSphere — Doctor Test Data Seeder
--  Run once in phpMyAdmin to populate charts & tables
-- ══════════════════════════════════════════════════════

-- ── 1. Rename Emma Hall → Jessica Johns ──────────────
UPDATE users
SET first_name='Jessica', last_name='Johns',
    email='jessica.johns@leicesterhospital.nhs.uk'
WHERE email='emma.hall@leicesterhospital.nhs.uk';

UPDATE doctors
SET hospital_name='Leicester Royal Infirmary',
    specialization='General Practice',
    experience_years=12, consultation_fee=50.00,
    bio='Dr. Jessica Johns is a senior GP with 12 years experience at Leicester Royal Infirmary.'
WHERE user_id=(SELECT id FROM users WHERE email='jessica.johns@leicesterhospital.nhs.uk' LIMIT 1);

-- ── 2. Add 12 extra test patients ────────────────────
INSERT IGNORE INTO users (nhs_id,first_name,last_name,email,password,role,phone,date_of_birth,gender,blood_type,is_active,approval_status) VALUES
('PTRS112201','Robert','Smith',   'robert.smith@email.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient','07700111001','1985-03-12','male','O+',1,'approved'),
('PTLJ112202','Laura','Johnson',  'laura.johnson@email.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient','07700111002','1990-07-22','female','A+',1,'approved'),
('PTMW112203','Michael','Wilson', 'michael.wilson@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient','07700111003','1978-11-05','male','B-',1,'approved'),
('PTSB112204','Sophie','Brown',   'sophie.brown@email.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient','07700111004','1995-01-30','female','AB+',1,'approved'),
('PTDT112205','David','Taylor',   'david.taylor@email.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient','07700111005','1972-06-18','male','O-',1,'approved'),
('PTOM112206','Olivia','Martinez','olivia.martinez@email.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient','07700111006','1988-09-14','female','A-',1,'approved'),
('PTCL112207','Chris','Lee',      'chris.lee@email.com',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient','07700111007','1965-04-25','male','B+',1,'approved'),
('PTND112208','Natalie','Davis',  'natalie.davis@email.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient','07700111008','1993-12-08','female','O+',1,'approved'),
('PTGP112209','George','Parker',  'george.parker@email.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient','07700111009','1980-08-17','male','A+',1,'approved'),
('PTEM112210','Emily','Moore',    'emily.moore@email.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient','07700111010','1997-02-28','female','AB-',1,'approved'),
('PTJW112211','Jack','White',     'jack.white@email.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient','07700111011','1970-10-11','male','O+',1,'approved'),
('PTAC112212','Anna','Clark',     'anna.clark@email.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','patient','07700111012','1983-05-03','female','B+',1,'approved');

-- ── 3. Add appointments across Jan–May 2026 ──────────
-- (uses subqueries for doctor_id and patient_ids)
INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,reason,status) VALUES
-- January
((SELECT id FROM users WHERE nhs_id='PTRS112201'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-01-06','09:00','Annual health check','completed'),
((SELECT id FROM users WHERE nhs_id='PTLJ112202'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-01-08','10:00','Blood pressure review','completed'),
((SELECT id FROM users WHERE nhs_id='PTMW112203'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-01-10','11:00','Diabetes follow-up','completed'),
((SELECT id FROM users WHERE nhs_id='PTSB112204'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-01-14','09:30','Headache consultation','completed'),
((SELECT id FROM users WHERE nhs_id='PTDT112205'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-01-15','14:00','Chest pain evaluation','completed'),
((SELECT id FROM users WHERE nhs_id='PTOM112206'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-01-20','11:30','Routine check-up','completed'),
((SELECT id FROM users WHERE nhs_id='PTCL112207'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-01-22','10:00','Back pain assessment','completed'),
-- February
((SELECT id FROM users WHERE nhs_id='PTND112208'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-02-03','09:00','Thyroid follow-up','completed'),
((SELECT id FROM users WHERE nhs_id='PTGP112209'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-02-05','10:30','Hypertension review','completed'),
((SELECT id FROM users WHERE nhs_id='PTEM112210'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-02-07','11:00','Asthma check','completed'),
((SELECT id FROM users WHERE nhs_id='PTJW112211'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-02-11','14:00','Skin rash consultation','completed'),
((SELECT id FROM users WHERE nhs_id='PTAC112212'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-02-14','09:00','Fatigue assessment','completed'),
((SELECT id FROM users WHERE nhs_id='PTRS112201'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-02-18','10:00','Follow-up visit','completed'),
((SELECT id FROM users WHERE nhs_id='PTLJ112202'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-02-25','11:30','Medication review','completed'),
-- March
((SELECT id FROM users WHERE nhs_id='PTMW112203'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-03-04','09:00','Blood test review','completed'),
((SELECT id FROM users WHERE nhs_id='PTSB112204'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-03-06','10:00','Migraine follow-up','completed'),
((SELECT id FROM users WHERE nhs_id='PTDT112205'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-03-10','11:00','ECG review','completed'),
((SELECT id FROM users WHERE nhs_id='PTOM112206'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-03-12','09:30','Anxiety consultation','completed'),
((SELECT id FROM users WHERE nhs_id='PTCL112207'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-03-17','14:00','Joint pain review','completed'),
((SELECT id FROM users WHERE nhs_id='PTND112208'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-03-19','10:30','Annual check-up','completed'),
((SELECT id FROM users WHERE nhs_id='PTGP112209'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-03-24','11:00','Medication adjustment','completed'),
((SELECT id FROM users WHERE nhs_id='PTEM112210'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-03-26','09:00','Respiratory review','completed'),
-- April
((SELECT id FROM users WHERE nhs_id='PTJW112211'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-04-02','10:00','Cholesterol review','completed'),
((SELECT id FROM users WHERE nhs_id='PTAC112212'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-04-07','11:30','Iron deficiency follow-up','completed'),
((SELECT id FROM users WHERE nhs_id='PTRS112201'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-04-09','09:00','Weight management','completed'),
((SELECT id FROM users WHERE nhs_id='PTLJ112202'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-04-14','10:00','Hormone check','completed'),
((SELECT id FROM users WHERE nhs_id='PTMW112203'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-04-16','11:00','Kidney function review','completed'),
((SELECT id FROM users WHERE nhs_id='PTSB112204'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-04-21','14:00','Sleep disorder consult','completed'),
((SELECT id FROM users WHERE nhs_id='PTDT112205'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-04-23','09:30','Cardiac monitoring','completed'),
((SELECT id FROM users WHERE nhs_id='PTOM112206'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-04-28','10:30','Mental health review','completed'),
-- May
((SELECT id FROM users WHERE nhs_id='PTCL112207'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-05-05','09:00','Arthritis review','completed'),
((SELECT id FROM users WHERE nhs_id='PTND112208'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-05-06','10:00','Diabetes check','completed'),
((SELECT id FROM users WHERE nhs_id='PTGP112209'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-05-07','11:00','Blood pressure','completed'),
((SELECT id FROM users WHERE nhs_id='PTEM112210'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-05-08','09:30','Routine check-up','completed'),
((SELECT id FROM users WHERE nhs_id='PTJW112211'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-05-09','14:00','Heart monitor review','completed'),
((SELECT id FROM users WHERE nhs_id='PTAC112212'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-05-12','09:00','Follow-up consultation','confirmed'),
((SELECT id FROM users WHERE nhs_id='PTRS112201'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-05-12','10:00','Annual review','confirmed'),
((SELECT id FROM users WHERE nhs_id='PTLJ112202'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-05-12','11:00','Medication review','arrived'),
((SELECT id FROM users WHERE nhs_id='PTMW112203'),
 (SELECT u.id FROM users u WHERE u.email='jessica.johns@leicesterhospital.nhs.uk'),'2026-05-12','14:00','Diabetes follow-up','waiting');

-- ── 4. Add health metrics for new patients ────────────
INSERT INTO health_metrics (patient_id,metric_date,heart_rate,blood_pressure_systolic,blood_pressure_diastolic,spo2,steps_count,sleep_hours,calories_burned,weight_kg,source)
SELECT id,'2026-05-11',74,122,78,98.2,7800,7.5,420,72.0,'manual' FROM users WHERE nhs_id='PTRS112201'
UNION ALL
SELECT id,'2026-05-11',68,118,76,99.0,9200,8.0,380,64.5,'manual' FROM users WHERE nhs_id='PTLJ112202'
UNION ALL
SELECT id,'2026-05-11',82,138,88,97.5,4200,6.0,510,88.0,'manual' FROM users WHERE nhs_id='PTMW112203'
UNION ALL
SELECT id,'2026-05-11',72,115,74,98.8,8500,7.0,350,58.0,'manual' FROM users WHERE nhs_id='PTSB112204'
UNION ALL
SELECT id,'2026-05-11',78,135,85,97.8,5100,6.5,470,91.0,'manual' FROM users WHERE nhs_id='PTDT112205'
UNION ALL
SELECT id,'2026-05-11',65,112,70,99.2,10200,8.5,320,55.0,'manual' FROM users WHERE nhs_id='PTOM112206'
UNION ALL
SELECT id,'2026-05-11',88,142,92,96.8,3200,5.5,540,95.0,'manual' FROM users WHERE nhs_id='PTCL112207'
UNION ALL
SELECT id,'2026-05-11',71,119,77,98.5,7100,7.5,390,62.0,'manual' FROM users WHERE nhs_id='PTND112208'
UNION ALL
SELECT id,'2026-05-11',76,128,82,98.0,6800,7.0,430,80.0,'manual' FROM users WHERE nhs_id='PTGP112209'
UNION ALL
SELECT id,'2026-05-11',67,116,73,98.9,9100,8.0,340,57.5,'manual' FROM users WHERE nhs_id='PTEM112210'
UNION ALL
SELECT id,'2026-05-11',84,140,89,97.2,4100,6.0,520,93.0,'manual' FROM users WHERE nhs_id='PTJW112211'
UNION ALL
SELECT id,'2026-05-11',70,120,76,98.7,8200,7.5,370,66.0,'manual' FROM users WHERE nhs_id='PTAC112212';

-- ── 5. Add lab results for some patients ─────────────
INSERT INTO medical_records (patient_id,doctor_id,record_type,title,result,result_status,test_date)
SELECT
    (SELECT id FROM users WHERE nhs_id='PTMW112203'),
    (SELECT id FROM users WHERE email='jessica.johns@leicesterhospital.nhs.uk'),
    'blood_test','HbA1c Blood Test','HbA1c: 7.8% — Above target range. Diabetes management review needed.','elevated','2026-05-08'
UNION ALL
SELECT
    (SELECT id FROM users WHERE nhs_id='PTCL112207'),
    (SELECT id FROM users WHERE email='jessica.johns@leicesterhospital.nhs.uk'),
    'ecg','12-Lead ECG','Mild ST-segment changes noted. Recommend cardiologist referral.','elevated','2026-05-09'
UNION ALL
SELECT
    (SELECT id FROM users WHERE nhs_id='PTDT112205'),
    (SELECT id FROM users WHERE email='jessica.johns@leicesterhospital.nhs.uk'),
    'lipid_profile','Lipid Profile','Total cholesterol: 6.2 mmol/L. LDL: 4.1 mmol/L — elevated.','elevated','2026-05-07'
UNION ALL
SELECT
    (SELECT id FROM users WHERE nhs_id='PTRS112201'),
    (SELECT id FROM users WHERE email='jessica.johns@leicesterhospital.nhs.uk'),
    'blood_test','Full Blood Count','All parameters within normal range. Haemoglobin 14.2 g/dL.','normal','2026-05-06'
UNION ALL
SELECT
    (SELECT id FROM users WHERE nhs_id='PTLJ112202'),
    (SELECT id FROM users WHERE email='jessica.johns@leicesterhospital.nhs.uk'),
    'thyroid','Thyroid Function Test','TSH: 5.8 mIU/L — mildly elevated. T3/T4 normal.','elevated','2026-05-05';

-- ── 6. Add prescriptions ─────────────────────────────
INSERT INTO prescriptions (patient_id,doctor_id,medication_name,dosage,frequency,duration,start_date,end_date,is_active)
SELECT
    (SELECT id FROM users WHERE nhs_id='PTMW112203'),
    (SELECT id FROM users WHERE email='jessica.johns@leicesterhospital.nhs.uk'),
    'Metformin','500mg','Twice daily','3 months','2026-05-08','2026-08-08',1
UNION ALL
SELECT
    (SELECT id FROM users WHERE nhs_id='PTDT112205'),
    (SELECT id FROM users WHERE email='jessica.johns@leicesterhospital.nhs.uk'),
    'Atorvastatin','20mg','Once daily at night','6 months','2026-05-07','2026-11-07',1
UNION ALL
SELECT
    (SELECT id FROM users WHERE nhs_id='PTCL112207'),
    (SELECT id FROM users WHERE email='jessica.johns@leicesterhospital.nhs.uk'),
    'Amlodipine','5mg','Once daily','3 months','2026-05-09','2026-08-09',1
UNION ALL
SELECT
    (SELECT id FROM users WHERE nhs_id='PTLJ112202'),
    (SELECT id FROM users WHERE email='jessica.johns@leicesterhospital.nhs.uk'),
    'Levothyroxine','50mcg','Once daily in morning','6 months','2026-05-05','2026-11-05',1;
