-- HealthSphere Database Schema
-- Complete Healthcare Management System


-- Users table (patients, doctors, admins, government)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nhs_id VARCHAR(20) UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('patient','doctor','admin','government') DEFAULT 'patient',
    phone VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('male','female','other'),
    blood_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-'),
    address TEXT,
    city VARCHAR(50),
    postcode VARCHAR(10),
    profile_photo VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    approval_status ENUM('pending','approved','rejected') DEFAULT 'approved',
    applied_at TIMESTAMP NULL,
    rejection_reason TEXT,
    device_registered TINYINT(1) DEFAULT 0,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Doctors extended info
CREATE TABLE IF NOT EXISTS doctors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    hcpc_number VARCHAR(20) UNIQUE,
    specialization VARCHAR(100),
    hospital_name VARCHAR(150),
    hospital_address TEXT,
    experience_years INT DEFAULT 0,
    rating DECIMAL(3,2) DEFAULT 0.00,
    total_reviews INT DEFAULT 0,
    consultation_fee DECIMAL(10,2) DEFAULT 0.00,
    available_days VARCHAR(100) DEFAULT 'Mon,Tue,Wed,Thu,Fri',
    bio TEXT,
    is_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Appointments
CREATE TABLE IF NOT EXISTS appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    reason TEXT,
    status ENUM('pending','confirmed','arrived','waiting','completed','cancelled','late') DEFAULT 'pending',
    notes TEXT,
    prescription_issued TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Medical records
CREATE TABLE IF NOT EXISTS medical_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT,
    record_type ENUM('blood_test','urine_test','lipid_profile','thyroid','xray','mri','ecg','other') NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    result TEXT,
    result_status ENUM('normal','elevated','low','critical') DEFAULT 'normal',
    file_path VARCHAR(255),
    test_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Allergies
CREATE TABLE IF NOT EXISTS allergies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    allergen VARCHAR(100) NOT NULL,
    allergy_type ENUM('medication','food','environmental','other') DEFAULT 'other',
    severity ENUM('mild','moderate','severe') DEFAULT 'moderate',
    symptoms TEXT,
    diagnosed_date DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Vaccinations
CREATE TABLE IF NOT EXISTS vaccinations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    vaccine_name VARCHAR(100) NOT NULL,
    dose_number INT DEFAULT 1,
    administered_date DATE,
    next_due_date DATE,
    administered_by VARCHAR(100),
    batch_number VARCHAR(50),
    is_completed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Prescriptions / Medications
CREATE TABLE IF NOT EXISTS prescriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT,
    appointment_id INT,
    medication_name VARCHAR(150) NOT NULL,
    dosage VARCHAR(100),
    frequency VARCHAR(100),
    duration VARCHAR(100),
    instructions TEXT,
    start_date DATE,
    end_date DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- Family medical history
CREATE TABLE IF NOT EXISTS family_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    relation ENUM('father','mother','brother','sister','grandfather','grandmother','other') NOT NULL,
    relation_name VARCHAR(100),
    condition_name VARCHAR(200) NOT NULL,
    year_diagnosed INT,
    year_deceased INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Diet / Meal logs
CREATE TABLE IF NOT EXISTS diet_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    log_date DATE NOT NULL,
    meal_type ENUM('breakfast','lunch','dinner','snack') NOT NULL,
    food_name VARCHAR(200) NOT NULL,
    calories DECIMAL(8,2) DEFAULT 0,
    protein DECIMAL(8,2) DEFAULT 0,
    carbs DECIMAL(8,2) DEFAULT 0,
    fats DECIMAL(8,2) DEFAULT 0,
    fiber DECIMAL(8,2) DEFAULT 0,
    sugar DECIMAL(8,2) DEFAULT 0,
    quantity VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Water intake logs
CREATE TABLE IF NOT EXISTS water_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    log_date DATE NOT NULL,
    glasses_count INT DEFAULT 0,
    total_ml DECIMAL(8,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Health metrics (vitals from wearable/manual)
CREATE TABLE IF NOT EXISTS health_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    metric_date DATE NOT NULL,
    metric_time TIME,
    heart_rate INT,
    blood_pressure_systolic INT,
    blood_pressure_diastolic INT,
    spo2 DECIMAL(5,2),
    temperature DECIMAL(5,2),
    steps_count INT DEFAULT 0,
    distance_km DECIMAL(8,2) DEFAULT 0,
    calories_burned DECIMAL(8,2) DEFAULT 0,
    sleep_hours DECIMAL(4,2) DEFAULT 0,
    sleep_quality INT DEFAULT 0,
    stress_level INT DEFAULT 0,
    weight_kg DECIMAL(6,2),
    bmi DECIMAL(5,2),
    source ENUM('wearable','manual','device') DEFAULT 'manual',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Exercise logs
CREATE TABLE IF NOT EXISTS exercise_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    log_date DATE NOT NULL,
    exercise_type VARCHAR(100) NOT NULL,
    duration_minutes INT DEFAULT 0,
    calories_burned DECIMAL(8,2) DEFAULT 0,
    intensity ENUM('low','moderate','high') DEFAULT 'moderate',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Messages / Chat
CREATE TABLE IF NOT EXISTS messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text','voice','file','emergency') DEFAULT 'text',
    is_read TINYINT(1) DEFAULT 0,
    is_emergency TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT,
    notification_type ENUM('appointment','medication','lab_result','message','alert','system') DEFAULT 'system',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Food database (admin managed)
CREATE TABLE IF NOT EXISTS food_database (
    id INT PRIMARY KEY AUTO_INCREMENT,
    food_code VARCHAR(20) UNIQUE,
    food_name VARCHAR(200) NOT NULL,
    category VARCHAR(100),
    calories_per_100g DECIMAL(8,2) DEFAULT 0,
    protein_g DECIMAL(8,2) DEFAULT 0,
    sugar_g DECIMAL(8,2) DEFAULT 0,
    fats_g DECIMAL(8,2) DEFAULT 0,
    fiber_g DECIMAL(8,2) DEFAULT 0,
    sodium_mg DECIMAL(8,2) DEFAULT 0,
    health_rating ENUM('excellent','good','moderate','poor') DEFAULT 'good',
    avoid_if TEXT,
    allergy_risk VARCHAR(200),
    vitamins_minerals VARCHAR(300),
    portion_size VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Genetic diseases registry
CREATE TABLE IF NOT EXISTS genetic_diseases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    disease_name VARCHAR(200) NOT NULL,
    inheritance_type ENUM('autosomal_dominant','autosomal_recessive','x_linked','mitochondrial','complex') DEFAULT 'complex',
    patient_count INT DEFAULT 0,
    key_symptoms TEXT,
    food_triggers TEXT,
    recommended_foods TEXT,
    exercise_guidance TEXT,
    care_plan ENUM('intensive','moderate','standard','preventive') DEFAULT 'standard',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Doctor clinical notes
CREATE TABLE IF NOT EXISTS clinical_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_id INT,
    note_text TEXT NOT NULL,
    note_type ENUM('general','follow_up','diagnosis','prescription','referral') DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- Access logs (security/privacy)
CREATE TABLE IF NOT EXISTS access_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    accessed_patient_id INT,
    action_type VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100),
    file_size INT,
    doc_type ENUM('lab_report','prescription','xray','scan','discharge','referral','other') DEFAULT 'other',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================
-- SEED DATA
-- ========================================

-- Admin user (password: Admin@123)
INSERT INTO users (nhs_id, first_name, last_name, email, password, role, phone, date_of_birth, gender, city, is_active) VALUES
('ADMIN001', 'System', 'Admin', 'admin@healthsphere.info', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '07700000000', '1985-01-01', 'male', 'London', 1);

-- Patient: Emma Watson (password: Patient@123)
INSERT INTO users (nhs_id, first_name, last_name, email, password, role, phone, date_of_birth, gender, blood_type, address, city, postcode, is_active) VALUES
('RT44656GRG', 'Emma', 'Patel', 'emma.patel007@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', '07700900123', '1990-03-15', 'female', 'AB+', '14 Oak Street', 'Leicester', 'LE1 4DF', 1),
('PT556677AA', 'James', 'Hall', 'james.hall@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', '07700900456', '1975-07-22', 'male', 'O+', '5 Maple Avenue', 'Leicester', 'LE2 1AB', 1),
('PT998877BB', 'Aisha', 'Khan', 'aisha.khan@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', '07700900789', '1988-11-30', 'female', 'B+', '22 Elm Road', 'Leicester', 'LE3 5GH', 1);

-- Doctors (password: Doctor@123)
INSERT INTO users (nhs_id, first_name, last_name, email, password, role, phone, date_of_birth, gender, city, is_active) VALUES
('DR001EMMA', 'Emma', 'Hall', 'emma.hall@leicesterhospital.nhs.uk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', '07700100001', '1980-05-12', 'female', 'Leicester', 1),
('DR002JACOB', 'Jacob', 'Lopez', 'jacob.lopez@leicesterhospital.nhs.uk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', '07700100002', '1975-09-20', 'male', 'Leicester', 1),
('DR003QUINN', 'Quinn', 'Cooper', 'quinn.cooper@leicesterhospital.nhs.uk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', '07700100003', '1982-03-08', 'male', 'London', 1),
('DR004JAMES', 'James', 'Fletcher', 'james.fletcher@starlight.nhs.uk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', '07700100004', '1978-12-25', 'male', 'Leicester', 1),
('DR005ALINA', 'Alina', 'Fatima', 'alina.fatima@apexcare.nhs.uk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', '07700100005', '1985-06-15', 'female', 'Leicester', 1);

-- Government analyst (password: Gov@123)
INSERT INTO users (nhs_id, first_name, last_name, email, password, role, phone, city, is_active) VALUES
('GOV001WJ', 'William', 'Jayson', 'w.jayson@dhsc.gov.uk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'government', '02071234567', 'London', 1);

-- Doctor details
INSERT INTO doctors (user_id, hcpc_number, specialization, hospital_name, hospital_address, experience_years, rating, total_reviews, consultation_fee, is_verified) VALUES
(5, 'HCPC50348306', 'General Practice', 'Leicester Medical College Hospital', 'University Road, Leicester LE1 7RH', 12, 4.9, 96, 50.00, 1),
(6, 'HCPC50348307', 'Cardiology', 'Leicester Medical College Hospital', 'University Road, Leicester LE1 7RH', 18, 4.7, 84, 120.00, 1),
(7, 'HCPC50348308', 'Neurology', 'Starlight Medical Center', 'King Street, London WC2N 5DU', 10, 4.8, 72, 150.00, 1),
(8, 'HCPC50348309', 'Cardiology', 'Starlight Medical Center', 'King Street, London WC2N 5DU', 15, 4.9, 96, 130.00, 1),
(9, 'HCPC50348310', 'Odontology', 'ApexCare Hospital', 'Park Lane, Leicester LE4 5GH', 8, 5.0, 45, 80.00, 1);

-- Allergies for Emma
INSERT INTO allergies (patient_id, allergen, allergy_type, severity, symptoms, diagnosed_date) VALUES
(2, 'Insulin', 'medication', 'moderate', 'Skin redness, itching and swelling', '2019-03-10'),
(2, 'Codeine', 'medication', 'severe', 'Respiratory symptoms, wheezing, difficulty breathing', '2018-07-22'),
(2, 'Pollen', 'environmental', 'mild', 'Sneezing, runny nose, nasal congestion', '2015-04-05'),
(2, 'Latex', 'other', 'mild', 'Itching, redness, mild rashes', '2020-09-14');

-- Vaccinations for Emma
INSERT INTO vaccinations (patient_id, vaccine_name, dose_number, administered_date, next_due_date, administered_by, is_completed) VALUES
(2, 'Covid-19 (Pfizer)', 1, '2021-05-10', NULL, 'Dr. Emma Hall', 1),
(2, 'Covid-19 (Pfizer)', 2, '2021-06-07', NULL, 'Dr. Emma Hall', 1),
(2, 'Tetanus', 1, '2020-01-15', '2025-01-15', 'Dr. Jacob Lopez', 1),
(2, 'Hepatitis B', 1, '2018-03-20', NULL, 'Dr. Emma Hall', 1),
(2, 'Hepatitis B', 2, '2018-04-20', NULL, 'Dr. Emma Hall', 1),
(2, 'Covid-19 Booster', 3, NULL, '2026-12-08', 'TBC', 0),
(2, 'Tetanus Booster', 2, NULL, '2026-01-09', 'TBC', 0);

-- Medical records for Emma
INSERT INTO medical_records (patient_id, doctor_id, record_type, title, description, result, result_status, test_date) VALUES
(2, 5, 'blood_test', 'Blood Test - Glucose', 'Fasting blood glucose test', 'Glucose: 5.6 mmol/L - Elevated levels may indicate pre-diabetes risk. Monitor diet and lifestyle.', 'elevated', '2025-02-10'),
(2, 5, 'urine_test', 'Urine Test', 'Standard urinalysis', 'Color/Odor: Abnormalities may indicate infections. Further culture test recommended.', 'elevated', '2025-06-05'),
(2, 5, 'lipid_profile', 'Lipid Profile', 'Cholesterol panel', 'Triglycerides: Elevated levels may indicate cardiovascular risk. LDL: 128 mg/dL. HDL: 52 mg/dL.', 'elevated', '2024-10-20'),
(2, 5, 'thyroid', 'Thyroid Function Test', 'TSH and T4 levels', 'T3/T4: Abnormal levels may indicate thyroid dysfunction. TSH: 5.2 mIU/L (slightly elevated).', 'elevated', '2024-01-20');

-- Prescriptions for Emma
INSERT INTO prescriptions (patient_id, doctor_id, medication_name, dosage, frequency, duration, instructions, start_date, end_date, is_active) VALUES
(2, 5, 'Amlodipine', '5mg', 'Once daily (Morning)', '6 months', 'Take with water in the morning for blood pressure control', '2025-01-15', '2025-07-15', 1),
(2, 5, 'Losartan', '25mg', 'Once daily (Night)', '6 months', 'Take at bedtime for BP management', '2025-01-15', '2025-07-15', 1),
(2, 5, 'Vitamin D3', '1000 IU', 'Once daily', '3 months', 'Take with food to support bone health', '2025-03-01', '2025-06-01', 0);

-- Family history for Emma
INSERT INTO family_history (patient_id, relation, relation_name, condition_name, year_diagnosed, year_deceased) VALUES
(2, 'father', 'Edwin Patel', 'High Cholesterol, Hypertension', 1971, 2024),
(2, 'mother', 'Urvi Patel', 'Thyroid Disorder', 2005, NULL),
(2, 'brother', 'Uday Patel', 'Hypercalcemia (High Calcium)', 2018, NULL);

-- Appointments
INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, status, notes) VALUES
(2, 5, '2025-10-26', '09:30:00', 'Blood Pressure Review', 'completed', 'Patient BP stable. Continue medication.'),
(2, 6, '2025-10-29', '09:30:00', 'Cardiac Checkup', 'upcoming', NULL),
(2, 7, '2025-10-29', '09:30:00', 'Neurology Consultation', 'upcoming', NULL),
(3, 5, CURDATE(), '09:00:00', 'BP Review', 'arrived', NULL),
(3, 5, CURDATE(), '09:30:00', 'Skin Rash', 'waiting', NULL),
(4, 5, CURDATE(), '10:00:00', 'Diabetes Check', 'late', NULL);

-- Diet logs for Emma (last 7 days)
INSERT INTO diet_logs (patient_id, log_date, meal_type, food_name, calories, protein, carbs, fats, fiber) VALUES
(2, CURDATE(), 'breakfast', 'Oats with Fruit', 320, 12, 55, 6, 8),
(2, CURDATE(), 'breakfast', 'Boiled Eggs (x2)', 140, 12, 1, 10, 0),
(2, CURDATE(), 'breakfast', 'Greek Yogurt', 130, 10, 8, 4, 0),
(2, CURDATE(), 'lunch', 'Grilled Chicken Salad', 380, 35, 15, 12, 5),
(2, CURDATE(), 'snack', 'Mixed Nuts', 180, 5, 6, 16, 2),
(2, CURDATE(), 'dinner', 'Salmon with Vegetables', 450, 40, 20, 18, 6),
(2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'breakfast', 'Whole Grain Toast', 280, 10, 45, 5, 6),
(2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'lunch', 'Lentil Soup', 320, 18, 45, 5, 12),
(2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'dinner', 'Beef Stew', 520, 38, 30, 22, 4),
(2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'breakfast', 'Banana Smoothie', 290, 8, 50, 6, 3);

-- Water logs
INSERT INTO water_logs (patient_id, log_date, glasses_count, total_ml) VALUES
(2, CURDATE(), 3, 750),
(2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 6, 1500),
(2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 8, 2000);

-- Health metrics (last 7 days)
INSERT INTO health_metrics (patient_id, metric_date, heart_rate, blood_pressure_systolic, blood_pressure_diastolic, spo2, temperature, steps_count, distance_km, calories_burned, sleep_hours, sleep_quality, stress_level, weight_kg, source) VALUES
(2, CURDATE(), 74, 118, 76, 97, 36.2, 7495, 3.5, 195, 7.83, 81, 45, 65.2, 'wearable'),
(2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 72, 120, 78, 98, 36.4, 8200, 4.1, 210, 8.0, 85, 40, 65.1, 'wearable'),
(2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 78, 125, 80, 96, 36.3, 5100, 2.5, 155, 6.5, 72, 60, 65.3, 'wearable'),
(2, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 70, 116, 74, 98, 36.1, 9200, 4.6, 240, 7.5, 88, 35, 65.2, 'wearable'),
(2, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 76, 122, 79, 97, 36.5, 6300, 3.1, 170, 7.0, 78, 50, 65.4, 'wearable'),
(2, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 73, 119, 77, 98, 36.2, 10500, 5.2, 280, 8.2, 90, 30, 65.0, 'wearable'),
(2, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 77, 124, 81, 97, 36.4, 4800, 2.4, 145, 6.0, 65, 65, 65.5, 'wearable');

-- Exercise logs
INSERT INTO exercise_logs (patient_id, log_date, exercise_type, duration_minutes, calories_burned, intensity) VALUES
(2, CURDATE(), 'Morning Push Up', 20, 85, 'moderate'),
(2, CURDATE(), 'Aerobic Workout', 30, 195, 'high'),
(2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Morning Walk', 45, 180, 'low'),
(2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Cycling', 30, 220, 'moderate'),
(2, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'Stretching', 20, 60, 'low');

-- Messages
INSERT INTO messages (sender_id, receiver_id, message, message_type, is_read, is_emergency) VALUES
(2, 5, 'Hi Emma, I am feeling light headed and my feet rate has been higher than usual since this morning.', 'text', 1, 1),
(5, 2, 'Hi Emma, can you tell me if you are supervising any chest pain or shortness of breath?', 'text', 1, 1),
(2, 5, 'No chest pain, but I feel dizzy when I stand up quickly.', 'text', 1, 1),
(5, 2, 'Okay, stay calm. I will check your recent labs and call you. Try to sit down and hydrate.', 'text', 1, 1),
(5, 2, 'I will stay here. Thank you, I will stay where I am.', 'text', 0, 0),
(3, 5, 'Can I change my slot?', 'text', 0, 0);

-- Notifications for Emma
INSERT INTO notifications (user_id, title, message, notification_type, is_read) VALUES
(2, 'Scheduled Appointment', 'Your consultation with Dr. Jacob Lopez has been confirmed for 29 Oct at 9:30 AM.', 'appointment', 0),
(2, 'Medication Change', 'Your prescription has been updated by Dr. Emma Hall. Please review.', 'medication', 0),
(2, 'Medical Notes Updated', 'New medical notes have been added to your profile by Dr. Quinn Cooper.', 'lab_result', 1),
(2, 'Medical History Update', 'Your family health history records were updated successfully.', 'system', 1);

-- Notifications for doctors
INSERT INTO notifications (user_id, title, message, notification_type, is_read) VALUES
(5, 'Critical Lab Result', 'Patient Emma Patel - LDL > 160. Requires immediate review.', 'alert', 0),
(5, 'Medication Refill Due', 'Patient James Hall - Amlodipine refill due in 3 days.', 'medication', 0),
(5, 'New Message', 'Emma Patel: Light headache today.', 'message', 0);

-- Food database
INSERT INTO food_database (food_code, food_name, category, calories_per_100g, protein_g, sugar_g, fats_g, fiber_g, sodium_mg, health_rating, avoid_if, allergy_risk, vitamins_minerals, portion_size) VALUES
('FD101', 'Grilled Chicken', 'Protein', 165, 31, 0, 3.6, 7.4, 74, 'good', 'High Cholesterol', 'Low', 'High in B12, Iron', '150g / 1-2 servings'),
('FD102', 'Salmon', 'Fish', 208, 20, 0, 13, 0, 59, 'excellent', 'Gout', 'Fish Allergy', 'Omega-3, Vitamin D, B12', '150g / 1 fillet'),
('FD103', 'Oatmeal', 'Grain', 71, 2.5, 1.1, 1.4, 1.7, 49, 'excellent', 'Celiac Disease', 'Gluten', 'Iron, Magnesium, Fiber', '40g dry / 1 bowl'),
('FD104', 'Broccoli', 'Vegetable', 34, 2.8, 1.7, 0.4, 2.6, 33, 'excellent', 'Thyroid conditions', 'None', 'Vitamin C, K, Folate', '100g / 1 cup'),
('FD105', 'Brown Rice', 'Grain', 123, 2.7, 0.4, 1, 1.8, 5, 'good', 'Diabetes (monitor portions)', 'None', 'B vitamins, Manganese', '75g dry / 1 cup cooked'),
('FD106', 'Avocado', 'Fruit', 160, 2, 0.7, 15, 7, 7, 'excellent', 'None', 'None', 'Potassium, Vitamin K, E, C', '50g / half avocado'),
('FD107', 'White Bread', 'Grain', 265, 9, 5, 3.2, 2.7, 508, 'poor', 'Diabetes, High BP', 'Gluten', 'Low in nutrients', '30g / 1 slice'),
('FD108', 'Instant Noodles', 'Processed', 138, 4, 0.4, 7, 0.9, 890, 'poor', 'High BP, Heart Disease', 'Gluten, Soy', 'Very low nutritional value', '75g / 1 pack'),
('FD109', 'Greek Yogurt', 'Dairy', 59, 10, 3.2, 0.4, 0, 36, 'excellent', 'Lactose intolerance', 'Dairy', 'Calcium, Probiotics, B12', '150g / 1 cup'),
('FD110', 'Spinach', 'Vegetable', 23, 2.9, 0.4, 0.4, 2.2, 79, 'excellent', 'Kidney stones (oxalate)', 'None', 'Iron, Vitamin K, A, C, Folate', '80g / 2 cups raw');

-- Genetic diseases registry
INSERT INTO genetic_diseases (disease_name, inheritance_type, patient_count, key_symptoms, food_triggers, recommended_foods, exercise_guidance, care_plan) VALUES
('Sickle Cell Disease', 'autosomal_recessive', 298, 'Pain crises, anemia, fatigue, frequent infections, swelling', 'Alcohol excess, dehydration, acidic foods', 'Folate-rich veg, lean protein, hydrating foods, whole grains', 'Caution: high-intensity. Recommended: low-impact yoga, walking', 'intensive'),
('Type 2 Diabetes', 'complex', 1250, 'Increased thirst, frequent urination, blurred vision, fatigue', 'High sugar foods, refined carbs, alcohol, processed foods', 'Whole grains, leafy greens, lean protein, healthy fats, berries', 'Moderate: 30min daily walking, resistance training 3x/week', 'moderate'),
('Familial Hypercholesterolemia', 'autosomal_dominant', 456, 'Elevated LDL, xanthomas, premature heart disease', 'Saturated fats, trans fats, high cholesterol foods, processed meats', 'Oats, nuts, olive oil, fatty fish, fruits, vegetables', 'Aerobic exercise 150min/week, avoid high-intensity without supervision', 'moderate'),
('Cystic Fibrosis', 'autosomal_recessive', 89, 'Thick mucus, chronic cough, lung infections, digestive problems', 'Low-fat diets (need high calorie), gas-producing foods', 'High calorie, high protein, fat-soluble vitamins (A,D,E,K)', 'Airway clearance exercises, moderate cardio as tolerated', 'intensive'),
('BRCA Gene Mutation', 'autosomal_dominant', 340, 'High cancer risk (breast, ovarian), family history', 'Processed meats, alcohol, high-fat diets', 'Cruciferous vegetables, antioxidant-rich foods, omega-3', 'Regular moderate exercise reduces risk by up to 40%', 'preventive');

-- Clinical notes for doctors
INSERT INTO clinical_notes (patient_id, doctor_id, note_text, note_type) VALUES
(2, 5, 'Blood pressure readings have stabilised within target range. No reported side effects from medication. Patient compliant with diet restrictions.', 'general'),
(2, 5, 'Patient reports occasional headaches; advised salt reduction and hydration. Follow up in 4 weeks.', 'follow_up'),
(3, 5, 'Skin rash examination - mild contact dermatitis. Prescribed hydrocortisone cream. Monitor for 2 weeks.', 'diagnosis'),
(4, 5, 'Diabetes check - FBS: 132 mg/dL. HbA1c: 7.2%. Adjust metformin dose. Dietary counselling required.', 'diagnosis');

-- Access logs
INSERT INTO access_logs (user_id, accessed_patient_id, action_type, ip_address) VALUES
(5, 2, 'VIEW_MEDICAL_RECORD', '83.146.180.124'),
(5, 3, 'VIEW_APPOINTMENT', '83.146.180.124'),
(2, NULL, 'LOGIN', '200.50.116.244'),
(1, NULL, 'ADMIN_ACCESS', '83.146.180.124');
