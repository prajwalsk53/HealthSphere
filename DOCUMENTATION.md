# HealthSphere — Complete Application Documentation

> Version 2.0 | NHS Connected Healthcare Platform  
> Live: https://healthsphere.info/HealthSphere  
> Stack: PHP 7.4+, MySQL/MariaDB 10.11, Vanilla JS

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Architecture](#2-architecture)
3. [Directory Structure](#3-directory-structure)
4. [User Roles & Access](#4-user-roles--access)
5. [Features by Role](#5-features-by-role)
6. [Database Schema](#6-database-schema)
7. [API Reference](#7-api-reference)
8. [External Integrations](#8-external-integrations)
9. [Email System](#9-email-system)
10. [Health Scoring Engine](#10-health-scoring-engine)
11. [AI Insights Engine](#11-ai-insights-engine)
12. [Security & Compliance](#12-security--compliance)
13. [Configuration](#13-configuration)
14. [Deployment & Infrastructure](#14-deployment--infrastructure)
15. [Demo Accounts](#15-demo-accounts)
16. [Flutter Mobile App](#16-flutter-mobile-app)

---

## 1. Project Overview

HealthSphere is a full-stack NHS-integrated healthcare management platform. It connects patients, doctors, administrators, and government health analysts on a single platform with role-based dashboards, AI-powered health insights, appointment management, medical records, diet tracking, and public health analytics.

### Key Highlights

- **4 user roles** with completely separate dashboards and permissions
- **AI health assistant** powered by Google Gemini 2.5 Flash
- **Real-time health metrics** with automated risk scoring (0–100)
- **Secure messaging** between patients and doctors
- **Government analytics** on anonymised population health data
- **13 automated email templates** for every patient interaction
- **REST API** with JWT authentication for mobile app integration
- **Flutter mobile app** (Android/iOS) connecting to the same backend

---

## 2. Architecture

```
Browser / Flutter App
        │
        ▼
Cloudflare CDN (DDoS protection, caching, SSL termination)
        │
        ▼
IONOS Shared Hosting — PHP 7.4+ / MariaDB 10.11
  ├── config/          (DB, AI, mail, Sentry, reCAPTCHA)
  ├── includes/        (functions, mailer, health engine, AI insights)
  ├── api/             (REST endpoints — session + JWT auth)
  ├── patient/         (patient dashboard pages)
  ├── doctor/          (doctor dashboard pages)
  ├── admin/           (admin dashboard pages)
  ├── government/      (government analytics pages)
  └── assets/          (CSS, JS, images)
        │
        ▼
External Services
  ├── Google Gemini AI  (health assistant)
  ├── Spoonacular API   (food/nutrition data)
  ├── MedlinePlus API   (genetic disease data — free)
  ├── Google reCAPTCHA  (bot prevention)
  ├── Gmail SMTP        (transactional emails)
  ├── Sentry.io         (error monitoring)
  └── UptimeRobot       (availability monitoring)
```

### Request Flow

1. Browser hits Cloudflare → proxied to IONOS server IP `217.160.0.48`
2. `.htaccess` enforces HTTPS redirect + security headers
3. `config/config.php` boots session, DB connection, Sentry error handler
4. `includes/functions.php` `requireRole()` validates session before any protected page
5. Pages query the DB via PDO prepared statements and render HTML
6. API endpoints (`/api/`) additionally support JWT Bearer tokens for mobile

---

## 3. Directory Structure

```
HealthSphere/
├── index.php                  Login page (reCAPTCHA, NHS SSO button)
├── register.php               Registration (patient / doctor / government)
├── logout.php                 Session termination + redirect
├── redirect_index.php         Role-based routing after login
├── setup.php                  Database initialisation wizard
├── .htaccess                  HTTPS redirect, cache headers, security headers
│
├── config/
│   ├── config.php             App constants, session, BASE_URL, reCAPTCHA keys
│   ├── db.php                 PDO connection (IONOS MariaDB)
│   ├── ai.php                 Gemini API key, Spoonacular API key
│   ├── mail.php               Gmail SMTP credentials (gitignored)
│   ├── sentry.php             Sentry error handler (production only)
│   ├── db.example.php         Template — copy to db.php
│   ├── ai.example.php         Template — copy to ai.php
│   └── mail.example.php       Template — copy to mail.php
│
├── includes/
│   ├── functions.php          Auth helpers, formatters, DB utilities
│   ├── mailer.php             Email templates + SMTP sender
│   ├── health_score.php       Vital signs → 0–100 risk score
│   ├── ai_insights.php        Rule-based health insight generator
│   └── sidebar.php            Shared navigation sidebar component
│
├── api/
│   ├── v1/                    REST API (JWT auth, used by Flutter app)
│   │   ├── core.php           JWT helpers, CORS, JSON response wrappers
│   │   ├── auth.php           Login, register, token refresh, /me
│   │   ├── dashboard.php      Patient dashboard data bundle
│   │   ├── health.php         Health metrics CRUD + score
│   │   ├── appointments.php   Calendar events (FullCalendar format)
│   │   ├── messages.php       Chat send/receive/mark-read
│   │   ├── notifications.php  Unread notifications list
│   │   ├── profile.php        User profile read/update
│   │   └── diet.php           Diet log CRUD + aggregates
│   ├── ai-assistant.php       Gemini AI chatbot endpoint
│   ├── food-search.php        Local food database search
│   ├── spoonacular-search.php Spoonacular ingredient search proxy
│   ├── spoonacular-info.php   Spoonacular nutrition detail proxy
│   ├── medlineplus-search.php MedlinePlus genetics search proxy
│   ├── appointments.php       Web appointment booking handler
│   ├── health-data.php        Health metrics submission
│   ├── family-history.php     Family history CRUD
│   ├── meal-ai.php            AI meal recommendation
│   ├── safe-appetite-scan.php Allergy-safe food scanner
│   └── chat-poll.php          Long-poll for new messages
│
├── patient/
│   ├── dashboard.php          Home: health score, vitals, AI insights, appointments
│   ├── appointments.php       Book / view / cancel appointments
│   ├── medical-records.php    Lab results, prescriptions, allergies, vaccinations
│   ├── health-insights.php    Wearable data, 7-day trends, risk factor breakdown
│   ├── health-analysis.php    Personalised recommendations
│   ├── messages.php           Secure chat with doctor
│   ├── notifications.php      In-app alerts and reminders
│   ├── profile.php            Personal info, medical history, family history
│   ├── documents.php          File upload / download (medical documents)
│   ├── diet-tracker.php       Log meals, nutrition charts, calorie goals
│   ├── safe-appetite.php      Allergy-aware meal suggestions
│   ├── calendar.php           Visual appointment calendar (FullCalendar)
│   ├── map.php                Hospital / clinic location finder
│   └── ai-assistant.php       Gemini AI health chat interface
│
├── doctor/
│   ├── dashboard.php          Today's appointments, risk-prioritised patient list
│   ├── patients.php           Full patient roster management
│   ├── appointments.php       Manage appointment details and statuses
│   ├── schedule.php           Set availability and schedule slots
│   ├── prescriptions.php      Issue medications to patients
│   ├── lab-results.php        View and enter diagnostic test results
│   ├── add-note.php           Add clinical notes to patient records
│   ├── alerts.php             Emergency messages from patients
│   ├── messages.php           Chat with patients
│   ├── profile.php            Doctor profile, specialization, ratings
│   └── map.php                Refer patients to other hospitals
│
├── admin/
│   ├── dashboard.php          System stats, user counts, pending approvals
│   ├── users.php              Create, edit, suspend, delete users
│   ├── doctors.php            Verify HCPC credentials, approve doctor accounts
│   ├── approvals.php          Review all pending registrations
│   ├── analytics.php          Platform usage analytics
│   ├── access-logs.php        Privacy audit trail (who accessed what)
│   ├── settings.php           System-wide configuration
│   ├── diseases.php           Genetic disease registry + MedlinePlus search
│   ├── food-data.php          Food database management + Spoonacular import
│   └── test-email.php         Test SMTP email delivery
│
├── government/
│   ├── dashboard.php          Public health KPIs (anonymised population data)
│   ├── regional.php           Health statistics by NHS region
│   ├── trends.php             Disease trend analysis and outbreak detection
│   ├── alerts.php             Issue national / regional health alerts
│   ├── reports.php            Export anonymised population health reports
│   └── map.php                Disease prevalence heat maps by location
│
├── assets/
│   ├── css/style.css          Global stylesheet (navy-blue NHS theme)
│   └── js/
│       ├── main.js            Shared utilities, sidebar toggle
│       └── charts.js          Chart.js wrappers for health trend graphs
│
├── sql/
│   ├── healthsphere.sql               Full schema + seed data
│   └── healthsphere_ionos.sql         Production-adjusted schema
│
├── uploads/                   User-uploaded medical documents (gitignored)
├── flutter_app/               Flutter mobile app source (Dart)
├── .github/
│   ├── workflows/deploy.yml   GitHub Actions auto-deploy to IONOS via SFTP
│   └── scripts/deploy.py      Python paramiko SFTP deployment script
└── DOCUMENTATION.md           This file
```

---

## 4. User Roles & Access

| Role | Registration | Activation | Dashboard |
|------|-------------|------------|-----------|
| **Patient** | Self-register | Instant | `/patient/dashboard.php` |
| **Doctor** | Self-register + HCPC number | Admin approval required | `/doctor/dashboard.php` |
| **Admin** | Created by existing admin | Instant | `/admin/dashboard.php` |
| **Government** | Self-register + Staff ID | Admin approval required | `/government/dashboard.php` |

### Approval Flow (Doctor / Government)

```
User registers → DB: is_active=0, approval_status='pending'
      → Admin notification (in-app + email)
      → Admin reviews credentials
      → Approve: is_active=1, approval_status='approved' → email sent to user
      → Reject: approval_status='rejected', rejection_reason saved → email sent to user
```

### Session Security

- PHP `$_SESSION` with 1-hour lifetime
- `session_set_cookie_params`: `httponly=true`, `samesite=Strict`, `secure=true` (production)
- Every protected page calls `requireRole()` which validates `$_SESSION['user_role']`
- Suspended users (`is_active=0`) are blocked at login

---

## 5. Features by Role

### Patient

| Feature | Page | Description |
|---------|------|-------------|
| Health Dashboard | `patient/dashboard.php` | Health score ring, latest vitals, AI-generated insights, upcoming appointments, active medications, unread message count |
| Health Insights | `patient/health-insights.php` | 7-day charts for heart rate, BP, SpO₂, steps, sleep; wearable data import; BMI tracker |
| Health Analysis | `patient/health-analysis.php` | Personalised risk factor breakdown with actionable recommendations |
| Appointments | `patient/appointments.php` | Browse available doctors, book slots, view status, cancel appointments |
| Appointment Calendar | `patient/calendar.php` | FullCalendar monthly/weekly view colour-coded by appointment status |
| Medical Records | `patient/medical-records.php` | Lab results (blood, urine, lipid, thyroid, X-ray, MRI, ECG), prescriptions, allergies, vaccinations |
| Diet Tracker | `patient/diet-tracker.php` | Log breakfast/lunch/dinner/snacks, calorie/macro charts, daily goals, food search |
| Safe Appetite | `patient/safe-appetite.php` | Allergy-aware meal suggestions via Spoonacular; filters out known allergens |
| AI Assistant | `patient/ai-assistant.php` | Multi-turn health chatbot (Gemini 2.5 Flash) with patient context (meds, allergies, vitals) injected |
| Secure Messaging | `patient/messages.php` | Real-time chat with assigned doctor; emergency message flag (red alert) |
| Notifications | `patient/notifications.php` | Appointment reminders, medication alerts, lab result notifications, admin messages |
| Documents | `patient/documents.php` | Upload and download medical documents (PDFs, images) |
| Profile | `patient/profile.php` | Edit personal info, medical history, family history (hereditary conditions), emergency contacts |
| Hospital Map | `patient/map.php` | Find nearby NHS hospitals and clinics |

### Doctor

| Feature | Page | Description |
|---------|------|-------------|
| Dashboard | `doctor/dashboard.php` | Today's appointments sorted by patient risk score; quick access to patient records |
| Patient Roster | `doctor/patients.php` | Full list of assigned patients with health status indicators |
| Appointments | `doctor/appointments.php` | View, confirm, reschedule, mark arrived/completed; add notes per appointment |
| Schedule | `doctor/schedule.php` | Set weekly availability, block dates, set consultation slots |
| Prescriptions | `doctor/prescriptions.php` | Issue medications (name, dosage, frequency, duration); patient receives email |
| Lab Results | `doctor/lab-results.php` | Enter and review diagnostic test results; critical values flagged automatically |
| Clinical Notes | `doctor/add-note.php` | Structured notes (general, follow-up, diagnosis, prescription, referral) |
| Emergency Alerts | `doctor/alerts.php` | Receive and respond to patient emergency messages |
| Messaging | `doctor/messages.php` | Secure chat with patients |
| Profile | `doctor/profile.php` | Update specialization, hospital, consultation fee, bio; view ratings |
| Referral Map | `doctor/map.php` | Find specialist hospitals to refer patients |

### Admin

| Feature | Page | Description |
|---------|------|-------------|
| Dashboard | `admin/dashboard.php` | Total users, pending approvals, recent registrations, system health |
| User Management | `admin/users.php` | View all users, edit profiles, suspend/reactivate, reset passwords, delete |
| Doctor Approvals | `admin/doctors.php` | Verify HCPC numbers, review credentials, approve or reject with reason |
| Pending Approvals | `admin/approvals.php` | Unified queue of all pending doctor and government registrations |
| Analytics | `admin/analytics.php` | Appointment volumes, user growth, diagnosis trends |
| Access Logs | `admin/access-logs.php` | Full audit trail: who accessed which patient's data, when, from where |
| Food Database | `admin/food-data.php` | Manage nutrition DB; search and import from Spoonacular with one click |
| Disease Registry | `admin/diseases.php` | Manage genetic disease definitions; search and import from MedlinePlus Genetics |
| Settings | `admin/settings.php` | Configure system-wide parameters |
| Email Test | `admin/test-email.php` | Send a test email to verify SMTP is working |

### Government Analyst

| Feature | Page | Description |
|---------|------|-------------|
| Public Health Dashboard | `government/dashboard.php` | KPIs: total registered patients, total appointments, prescriptions issued, critical alerts — all anonymised |
| Regional Statistics | `government/regional.php` | Health data broken down by NHS region (East Midlands, NW, NE, etc.) |
| Trend Analysis | `government/trends.php` | Multi-year disease trends, seasonal patterns, outbreak detection signals |
| Health Alerts | `government/alerts.php` | Issue and manage national/regional public health alerts |
| Reports | `government/reports.php` | Generate and export anonymised population health reports (CSV/PDF) |
| Epidemiology Map | `government/map.php` | Interactive heat maps of disease prevalence by geographic area |

---

## 6. Database Schema

**Database:** `dbs15649976` (MariaDB 10.11)  
**Host:** `db5020406791.hosting-data.io`  
**Charset:** `utf8mb4`

---

### `users` — Central user accounts

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| nhs_id | VARCHAR(20) UNIQUE | e.g. `PTAB123456` |
| first_name | VARCHAR(50) | |
| last_name | VARCHAR(50) | |
| email | VARCHAR(100) UNIQUE | |
| password | VARCHAR(255) | bcrypt hash |
| role | ENUM | `patient`, `doctor`, `admin`, `government` |
| phone | VARCHAR(20) | |
| date_of_birth | DATE | |
| gender | ENUM | `male`, `female`, `other` |
| blood_type | VARCHAR(5) | `A+`, `O-`, etc. |
| address, city, postcode | VARCHAR | |
| profile_photo | VARCHAR(255) | path to uploaded file |
| is_active | TINYINT(1) | 0=suspended/pending, 1=active |
| approval_status | ENUM | `pending`, `approved`, `rejected` |
| rejection_reason | TEXT | set when admin rejects |
| applied_at | DATETIME | when registration submitted |
| last_login | DATETIME | |
| created_at, updated_at | TIMESTAMP | |

---

### `doctors` — Extended doctor profiles

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| user_id | INT FK → users | |
| hcpc_number | VARCHAR(20) UNIQUE | Health & Care Professions Council number |
| specialization | VARCHAR(100) | e.g. `Cardiology` |
| hospital_name | VARCHAR(150) | |
| hospital_address | VARCHAR(255) | |
| experience_years | INT | |
| consultation_fee | DECIMAL(8,2) | in GBP |
| rating | DECIMAL(3,2) | 0.00–5.00 |
| total_reviews | INT | |
| available_days | VARCHAR(100) | comma-separated days |
| bio | TEXT | professional summary |
| is_verified | TINYINT(1) | 1 = admin-verified credentials |

---

### `appointments`

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| patient_id | INT FK → users | |
| doctor_id | INT FK → users | |
| appointment_date | DATE | |
| appointment_time | TIME | |
| reason | TEXT | patient's reason for visit |
| notes | TEXT | doctor's notes |
| prescription_issued | TINYINT(1) | |
| status | ENUM | `pending`, `confirmed`, `arrived`, `waiting`, `completed`, `cancelled`, `late` |
| created_at, updated_at | TIMESTAMP | |

---

### `medical_records`

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| patient_id | INT FK → users | |
| doctor_id | INT FK → users | |
| record_type | ENUM | `blood_test`, `urine_test`, `lipid_profile`, `thyroid`, `xray`, `mri`, `ecg`, `other` |
| title | VARCHAR(200) | |
| description | TEXT | |
| result | TEXT | raw result text |
| result_status | ENUM | `normal`, `elevated`, `low`, `critical` |
| file_path | VARCHAR(255) | attached file |
| test_date | DATE | |
| created_at | TIMESTAMP | |

---

### `allergies`

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| patient_id | INT FK → users | |
| allergen | VARCHAR(100) | e.g. `Penicillin`, `Pollen` |
| allergy_type | ENUM | `medication`, `food`, `environmental`, `other` |
| severity | ENUM | `mild`, `moderate`, `severe` |
| symptoms | TEXT | |
| diagnosed_date | DATE | |
| is_active | TINYINT(1) | |

---

### `vaccinations`

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| patient_id | INT FK → users | |
| vaccine_name | VARCHAR(100) | |
| dose_number | INT | |
| administered_date | DATE | |
| next_due_date | DATE | |
| administered_by | VARCHAR(100) | |
| batch_number | VARCHAR(50) | |
| is_completed | TINYINT(1) | |

---

### `prescriptions`

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| patient_id | INT FK → users | |
| doctor_id | INT FK → users | |
| appointment_id | INT FK → appointments | |
| medication_name | VARCHAR(150) | |
| dosage | VARCHAR(100) | e.g. `500mg` |
| frequency | VARCHAR(100) | e.g. `Twice daily` |
| duration | VARCHAR(100) | e.g. `7 days` |
| instructions | TEXT | special notes |
| start_date, end_date | DATE | |
| is_active | TINYINT(1) | |

---

### `health_metrics` — Vitals & wearable data

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| patient_id | INT FK → users | |
| metric_date | DATE | |
| metric_time | TIME | |
| heart_rate | INT | bpm |
| blood_pressure_systolic | INT | mmHg |
| blood_pressure_diastolic | INT | mmHg |
| spo2 | DECIMAL(5,2) | % oxygen saturation |
| temperature | DECIMAL(5,2) | °C |
| steps_count | INT | |
| distance_km | DECIMAL(6,2) | |
| calories_burned | INT | |
| sleep_hours | DECIMAL(4,2) | |
| sleep_quality | INT | 1–10 |
| stress_level | INT | 0–100 |
| weight_kg | DECIMAL(5,2) | |
| bmi | DECIMAL(5,2) | calculated |
| source | ENUM | `wearable`, `manual`, `device` |

---

### `diet_logs`

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| patient_id | INT FK → users | |
| log_date | DATE | |
| meal_type | ENUM | `breakfast`, `lunch`, `dinner`, `snack` |
| food_name | VARCHAR(150) | |
| calories | DECIMAL(8,2) | |
| protein_g | DECIMAL(8,2) | |
| carbs_g | DECIMAL(8,2) | |
| fats_g | DECIMAL(8,2) | |
| fiber_g | DECIMAL(8,2) | |
| sugar_g | DECIMAL(8,2) | |
| quantity | DECIMAL(8,2) | grams |

---

### `water_logs`

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| patient_id | INT FK → users | |
| log_date | DATE | |
| glasses_count | INT | |
| total_ml | INT | |

---

### `exercise_logs`

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| patient_id | INT FK → users | |
| log_date | DATE | |
| exercise_type | VARCHAR(100) | e.g. `Running`, `Cycling` |
| duration_minutes | INT | |
| calories_burned | INT | |
| intensity | ENUM | `low`, `moderate`, `high` |
| notes | TEXT | |

---

### `family_history`

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| patient_id | INT FK → users | |
| relation | ENUM | `father`, `mother`, `brother`, `sister`, `grandfather`, `grandmother`, `other` |
| relation_name | VARCHAR(100) | |
| condition_name | VARCHAR(150) | medical condition name |
| year_diagnosed | INT | |
| year_deceased | INT | null if living |
| notes | TEXT | |

---

### `messages`

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| sender_id | INT FK → users | |
| receiver_id | INT FK → users | |
| message | TEXT | |
| message_type | ENUM | `text`, `voice`, `file`, `emergency` |
| is_read | TINYINT(1) | |
| is_emergency | TINYINT(1) | triggers red alert to doctor |
| created_at | TIMESTAMP | |

---

### `notifications`

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| user_id | INT FK → users | |
| title | VARCHAR(200) | |
| message | TEXT | |
| notification_type | ENUM | `appointment`, `medication`, `lab_result`, `message`, `alert`, `system` |
| is_read | TINYINT(1) | |
| created_at | TIMESTAMP | |

---

### `clinical_notes`

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| patient_id | INT FK → users | |
| doctor_id | INT FK → users | |
| appointment_id | INT FK → appointments | |
| note_text | TEXT | |
| note_type | ENUM | `general`, `follow_up`, `diagnosis`, `prescription`, `referral` |
| created_at | TIMESTAMP | |

---

### `food_database`

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| food_code | VARCHAR(20) UNIQUE | internal reference |
| food_name | VARCHAR(150) | |
| category | VARCHAR(100) | e.g. `Protein`, `Vegetable` |
| calories_per_100g | DECIMAL(8,2) | |
| protein_g | DECIMAL(8,2) | per 100g |
| sugar_g | DECIMAL(8,2) | per 100g |
| fats_g | DECIMAL(8,2) | per 100g |
| fiber_g | DECIMAL(8,2) | per 100g |
| sodium_mg | DECIMAL(8,2) | per 100g |
| health_rating | ENUM | `excellent`, `good`, `moderate`, `poor` |
| avoid_if | TEXT | conditions that should avoid this food |
| allergy_risk | VARCHAR(255) | common allergens present |
| vitamins_minerals | TEXT | key micronutrients |
| portion_size | VARCHAR(50) | recommended serving |

---

### `genetic_diseases`

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| disease_name | VARCHAR(200) | |
| inheritance_type | ENUM | `autosomal_dominant`, `autosomal_recessive`, `x_linked`, `mitochondrial`, `complex` |
| patient_count | INT | registered patients with this condition |
| key_symptoms | TEXT | comma-separated |
| food_triggers | TEXT | foods to avoid |
| recommended_foods | TEXT | beneficial foods |
| exercise_guidance | TEXT | |
| care_plan | ENUM | `intensive`, `moderate`, `standard`, `preventive` |

---

### `access_logs` — Privacy audit trail

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| user_id | INT FK → users | who performed the action |
| accessed_patient_id | INT FK → users | which patient's data (nullable) |
| action_type | VARCHAR(100) | e.g. `view_records`, `update_prescription` |
| ip_address | VARCHAR(45) | IPv4 or IPv6 |
| user_agent | TEXT | browser/device info |
| created_at | TIMESTAMP | |

---

### `documents`

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK AI | |
| user_id | INT FK → users | |
| file_name | VARCHAR(255) | |
| file_path | VARCHAR(255) | server path |
| file_type | VARCHAR(100) | MIME type |
| file_size | INT | bytes |
| uploaded_at | TIMESTAMP | |

---

## 7. API Reference

### Authentication

All `/api/v1/` endpoints require a `Authorization: Bearer <token>` header except login and register.  
Web dashboard pages use PHP `$_SESSION` instead.

---

### POST `/api/v1/auth.php?action=login`

**Body:**
```json
{ "email": "patient@example.com", "password": "Secret@123" }
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "token": "eyJ...",
    "expires_in": 2592000,
    "user": {
      "id": 1, "nhs_id": "PTAB123456",
      "first_name": "Emma", "last_name": "Patel",
      "email": "emma@example.com", "role": "patient",
      "blood_type": "AB+", "phone": "07700123456"
    }
  }
}
```

**Error responses:** `401` invalid credentials | `403` pending/rejected/suspended

---

### POST `/api/v1/auth.php?action=register`

**Body:**
```json
{
  "role": "patient",
  "first_name": "Emma", "last_name": "Patel",
  "email": "emma@example.com", "password": "Secret@123",
  "phone": "07700123456", "dob": "1990-05-15",
  "gender": "female", "blood_type": "AB+"
}
```

Doctor additional fields: `hcpc_number`, `specialization`, `hospital_name`, `experience_years`, `consultation_fee`, `bio`

**Response 201:**
```json
{
  "success": true,
  "data": { "message": "Account created", "nhs_id": "PTAB123456", "status": "active" }
}
```

**Side effects:** Welcome email (patient) OR pending-review email (doctor/government) + admin notification

---

### GET `/api/v1/auth.php?action=me`

**Response 200:**
```json
{
  "success": true,
  "data": {
    "id": 1, "first_name": "Emma", "role": "patient",
    "doctor_profile": null
  }
}
```

---

### GET `/api/v1/dashboard.php`

**Response 200:**
```json
{
  "success": true,
  "data": {
    "vitals": {
      "heart_rate": 72, "bp": "120/80",
      "bp_systolic": 120, "bp_diastolic": 80,
      "spo2": 98.5, "steps": 7500,
      "sleep": 7.5, "calories_burned": 450, "temperature": 36.6
    },
    "calories_today": 1850,
    "calorie_goal": 2500,
    "upcoming_appointments": [
      {
        "id": 42, "appointment_date": "2026-05-15",
        "appointment_time": "10:30:00", "reason": "Annual checkup",
        "status": "confirmed", "first_name": "Emma", "last_name": "Hall",
        "specialization": "General Practice", "hospital_name": "Leicester Royal"
      }
    ],
    "active_medications": [
      { "medication_name": "Metformin", "dosage": "500mg", "frequency": "Twice daily" }
    ],
    "unread_notifications": 3,
    "unread_messages": 1,
    "health_trend": [
      { "metric_date": "2026-05-10", "heart_rate": 70, "blood_pressure_systolic": 118, "steps_count": 8200, "sleep_hours": 7.0 }
    ]
  }
}
```

---

### GET `/api/v1/health.php?action=list`

**Params:** `limit=30` (default, max 100)

**Response:** Array of health_metrics objects (last N records, newest first)

---

### POST `/api/v1/health.php?action=add`

**Body:** Any subset of metric fields:
```json
{
  "metric_date": "2026-05-11",
  "heart_rate": 72, "blood_pressure_systolic": 120, "blood_pressure_diastolic": 80,
  "spo2": 98.5, "steps_count": 8000, "sleep_hours": 7.5,
  "calories_burned": 400, "temperature": 36.6, "weight": 70.5
}
```

**Response 201:**
```json
{ "success": true, "data": { "id": 123, "message": "Metrics saved" } }
```

---

### GET `/api/v1/health.php?action=score`

**Response:**
```json
{
  "success": true,
  "data": {
    "score": 78,
    "level": "Good",
    "breakdown": {
      "bp": 20, "heart_rate": 15, "spo2": 15,
      "sleep": 12, "activity": 8, "diet": 5, "stress": 3
    }
  }
}
```

---

### GET `/api/appointments.php?action=calendar`

**Response:** FullCalendar-compatible event array:
```json
[
  {
    "id": 42,
    "title": "Dr. Emma Hall",
    "start": "2026-05-15T10:30:00",
    "end": "2026-05-15T11:00:00",
    "backgroundColor": "#10B981",
    "extendedProps": {
      "doctor": "Dr. Emma Hall",
      "sub": "General Practice",
      "reason": "Annual checkup",
      "status": "confirmed"
    }
  }
]
```

Status colour map: `confirmed`=green, `pending`=yellow, `completed`=blue, `cancelled`=red, `arrived`=teal

---

### POST `/api/ai-assistant.php`

**Body:**
```json
{
  "message": "What does my blood pressure reading mean?",
  "history": [
    { "role": "user", "content": "Hello" },
    { "role": "assistant", "content": "Hi! How can I help?" }
  ]
}
```

**Response:**
```json
{
  "reply": "Your reading of 120/80 mmHg is considered optimal...",
  "source": "gemini"
}
```

**Patient context injected automatically:** Active medications, known allergies, latest vital signs, assigned doctor name. The AI will not override medical advice.

---

### GET `/api/food-search.php`

**Params:** `q=chicken` or `category=Protein`  
**Response:**
```json
[
  {
    "id": 5, "food_name": "Chicken Breast",
    "category": "Protein", "calories_per_100g": 165,
    "protein_g": 31, "fats_g": 3.6, "carbs_g": 0,
    "health_rating": "excellent", "portion_size": "150g"
  }
]
```

---

### GET `/api/spoonacular-search.php?q=broccoli`

**Response:** Same schema as food-search, sourced from Spoonacular API

---

### GET `/api/medlineplus-search.php`

**Params:** `q=sickle cell` OR `type=detail&slug=sickle-cell-disease`

**Search response:**
```json
{
  "results": [
    { "name": "Sickle Cell Disease", "slug": "sickle-cell-disease", "snippet": "...", "url": "..." }
  ]
}
```

**Detail response:**
```json
{
  "name": "Sickle Cell Disease",
  "slug": "sickle-cell-disease",
  "inheritance": "autosomal_recessive",
  "inheritance_label": "Autosomal Recessive",
  "symptoms": "Anaemia, pain crises, swelling...",
  "genes": "HBB",
  "summary": "Sickle cell disease is...",
  "url": "https://medlineplus.gov/genetics/condition/sickle-cell-disease/",
  "synonyms": "SCD, HbSS disease"
}
```

---

## 8. External Integrations

### Google Gemini AI
- **Model:** `gemini-2.5-flash`
- **Endpoint:** `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent`
- **Auth:** API key from Google AI Studio (aistudio.google.com)
- **Config:** `config/ai.php` → `GEMINI_API_KEY`
- **Usage:** Patient AI assistant with health context injection
- **Max tokens:** 900
- **System prompt:** NHS-safe health guidance; no diagnosis; recommends doctor consultation

### Spoonacular Food API
- **Base URL:** `https://api.spoonacular.com`
- **Endpoints used:**
  - `GET /food/ingredients/search?query=X` — ingredient lookup
  - `GET /food/ingredients/{id}/information` — nutrition detail
- **Auth:** `apiKey` query parameter
- **Config:** `config/ai.php` → `SPOONACULAR_API_KEY`
- **Usage:** Admin food import, patient diet tracker, safe appetite suggestions

### MedlinePlus Genetics (NIH)
- **Base URL:** `https://medlineplus.gov` / `https://wsearch.nlm.nih.gov`
- **Endpoints:**
  - `GET https://wsearch.nlm.nih.gov/ws/query?db=ghr&term=X` — search (XML)
  - `GET https://medlineplus.gov/download/genetics/condition/{slug}.json` — detail
- **Auth:** None (free public API)
- **Usage:** Admin genetic disease registry enrichment

### Google reCAPTCHA v2
- **Widget:** `https://www.google.com/recaptcha/api.js`
- **Verify:** `POST https://www.google.com/recaptcha/api/siteverify`
- **Config:** `config/config.php` → `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET_KEY`
- **Domain registered:** `healthsphere.info`
- **Usage:** Login page + registration form; submit button disabled until ticked

### Gmail SMTP
- **Server:** `smtp.gmail.com:465` (SSL)
- **Auth:** App-specific password (not main Gmail password)
- **Config:** `config/mail.php` (gitignored — upload manually)
- **Usage:** All 13 transactional email templates

### Sentry.io
- **DSN:** `https://04f15ffda1b7c2e5af186e9a1fb13430@o4511370836705280.ingest.de.sentry.io/4511370838605904`
- **Config:** `config/sentry.php`
- **Active:** Production only (`IS_LOCAL === false`)
- **Captures:** Unhandled exceptions, PHP errors, stack traces, request context
- **Implementation:** Custom handlers (no Composer required), uses `curl` to POST to Sentry Store API

### UptimeRobot
- **Monitor type:** HTTPS
- **URL:** `https://healthsphere.info/HealthSphere/`
- **Interval:** Every 5 minutes
- **Alerts:** Email to `prajwalkateel0@gmail.com` on downtime

### Cloudflare
- **Plan:** Free
- **Nameservers:** `edward.ns.cloudflare.com`, `lauryn.ns.cloudflare.com`
- **SSL mode:** Full (end-to-end encryption)
- **Features active:** CDN, DDoS protection, HTTPS enforcement, static asset caching

---

## 9. Email System

**Implementation:** `includes/mailer.php` — custom PHP raw socket SMTP (no Composer/PHPMailer)

### Email Templates

| Function | Trigger | Recipient |
|----------|---------|-----------|
| `mailPatientWelcome()` | Patient registers | Patient |
| `mailApplicationReceived()` | Doctor/Gov registers | Applicant |
| `mailAdminNewApplication()` | Doctor/Gov registers | Admin |
| `mailAccountApproved()` | Admin approves account | Applicant |
| `mailAccountRejected()` | Admin rejects account | Applicant |
| `mailAppointmentPatient()` | Appointment booked | Patient |
| `mailAppointmentDoctor()` | Appointment booked | Doctor |
| `mailAppointmentCancelled()` | Appointment cancelled | Both parties |
| `mailEmergencyAlert()` | Patient sends emergency message | Doctor |
| `mailNewMessage()` | New chat message | Recipient |
| `mailPrescriptionIssued()` | Doctor issues prescription | Patient |
| `mailCriticalLabResult()` | Lab result = critical | Patient + Doctor |
| `mailAccountNotice()` | Security / account events | User |

### Email Design
- NHS branding (navy/blue gradient header)
- Responsive HTML layout
- HealthSphere logo + tagline
- Data tables for structured info (NHS ID, appointment details, etc.)
- Colour-coded alerts (red=emergency, yellow=warning, green=success, blue=info)
- Footer: security disclaimer, admin contact (`admin@healthsphere.info`)

---

## 10. Health Scoring Engine

**File:** `includes/health_score.php`

Analyses the patient's last 7 days of `health_metrics` and produces a score from 0–100.

| Factor | Max Points | Thresholds |
|--------|-----------|-----------|
| Blood Pressure | 25 | <120/80=25, elevated=20, high-normal=13, Grade 1=7, Grade 2+=2 |
| Heart Rate | 20 | 60–75 bpm=20, 55–85=15, 50–100=10, outside=4 |
| SpO₂ | 15 | ≥98%=15, ≥95%=12, ≥90%=8, <90%=0 |
| Sleep | 15 | 7–9h=15, 6–10h=12, <6 or >10h=4 |
| Activity (steps) | 12 | ≥8000=12, ≥6000=8, <6000=2 |
| Diet quality | 8 | Balanced macros, low sodium |
| Stress level | 5 | <40=5, 40–60=2, >60=0 |
| BMI | 5 | Normal=5, Overweight=2, Obese=0 |

**Score interpretation:**
- 0–49 → Red (High Risk — immediate attention)
- 50–69 → Amber (Caution — monitor closely)
- 70–84 → Blue (Good)
- 85–100 → Green (Excellent)

---

## 11. AI Insights Engine

**File:** `includes/ai_insights.php`

Analyses 14-day metric history using rule-based logic and generates personalised, prioritised health cards on the patient dashboard.

### Insight Categories

| Level | Colour | Example Trigger |
|-------|--------|----------------|
| Critical | Red | BP ≥ 140, SpO₂ < 90%, emergency flag |
| Warning | Orange | BP 130–140, irregular HR, <6h sleep for 3+ days |
| Info | Blue | Low steps, low water intake, hydration reminder |
| Success | Green | Improved metric vs prior week |

### Rules Checked
- Blood pressure escalation (with 7-day trend direction)
- Heart rate anomalies (resting HR > 100 or < 50)
- Sleep deprivation (< 6h average)
- Physical inactivity (< 4000 steps average)
- Dehydration (water intake < 4 glasses/day)
- Active severe allergies
- Medication compliance reminders
- Unhealthy weight trajectory (BMI change)
- Positive improvements (congratulations cards)

---

## 12. Security & Compliance

### Authentication Layers
- **Web:** PHP `$_SESSION` (httponly, SameSite=Strict, secure=true on prod)
- **API:** JWT (HS256, 30-day expiry, stored in app)
- **Passwords:** bcrypt (`PASSWORD_DEFAULT`)
- **Bot prevention:** reCAPTCHA v2 on login + registration

### Access Control
- `requireRole($roles)` enforced on every protected page
- Doctors can only access their own patients
- Government dashboard shows only anonymised aggregates (no PII)
- Admins have full access + audit log of their own actions

### Input Security
- All DB queries use PDO prepared statements (no SQL injection)
- All HTML output uses `e()` = `htmlspecialchars` (no XSS)
- File uploads: type and size validation

### HTTP Security Headers (`.htaccess`)
```
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

### Audit & Monitoring
- **Access logs table:** Every patient data access logged (user, action, IP, timestamp)
- **Sentry:** All unhandled PHP exceptions reported to Sentry.io (production)
- **UptimeRobot:** 5-minute availability checks with email alerts
- **IONOS backups:** Automatic daily database backups, 14-day retention

### GDPR Considerations
- Government analytics: population-level only, no patient PII exposed
- Data collected: only what is necessary for healthcare delivery
- Account deletion: available via admin panel

---

## 13. Configuration

### Environment Detection

```php
// config/config.php
$_isLocal = ($_host === 'localhost' || strpos($_host, '127.') === 0 || strpos($_host, '192.168.') === 0);
define('BASE_URL', $_isLocal ? 'http://localhost/HealthSphere' : 'https://healthsphere.info/HealthSphere');
```

### Key Constants

| Constant | Value | File |
|----------|-------|------|
| `APP_NAME` | `HealthSphere` | config.php |
| `APP_VERSION` | `2.0` | config.php |
| `SESSION_LIFETIME` | `3600` (1 hour) | config.php |
| `BASE_URL` | auto-detected | config.php |
| `BASE_PATH` | `/HealthSphere` | config.php |
| `RECAPTCHA_SITE_KEY` | `6Ld5QeMsAAAAA...` | config.php |
| `GEMINI_API_KEY` | `AIzaSy...` | ai.php |
| `AI_MODEL` | `gemini-2.5-flash` | ai.php |
| `AI_MAX_TOKENS` | `900` | ai.php |
| `SPOONACULAR_API_KEY` | `98a0f198...` | ai.php |
| `MAIL_HOST` | `smtp.gmail.com` | mail.php |
| `MAIL_PORT` | `465` | mail.php |
| `MAIL_ADMIN` | `admin@healthsphere.info` | mail.php |
| `SENTRY_DSN` | `https://04f1...` | sentry.php |

### Gitignored Files (upload manually via FileZilla/CI)

- `config/mail.php` — email credentials
- `uploads/` — user files
- `logs/` — PHP error logs

---

## 14. Deployment & Infrastructure

### Hosting
- **Provider:** IONOS Web Hosting Plus
- **Server:** `access-5020406432.webspace-host.com` (SFTP port 22)
- **Web root:** `/public/HealthSphere/`
- **Database:** MariaDB 10.11 at `db5020406791.hosting-data.io:3306`
- **PHP:** 7.4+

### CI/CD Pipeline

**Trigger:** `git push` to `main` branch on GitHub

**Workflow:** `.github/workflows/deploy.yml`

```
git push → GitHub Actions runner (ubuntu-latest)
  → actions/checkout@v4
  → pip install paramiko
  → python .github/scripts/deploy.py
      → SFTP connect to IONOS
      → Upload all changed files to /public/HealthSphere/
      → Skip: config/mail.php, logs/, sql/, .git/
```

**Secrets required in GitHub:**
- `SFTP_USER` = `su852728`
- `SFTP_PASSWORD` = IONOS SFTP password

### DNS
- **Registrar/DNS:** Cloudflare
- **Nameservers:** `edward.ns.cloudflare.com`, `lauryn.ns.cloudflare.com`
- **A record:** `healthsphere.info` → `217.160.0.48` (proxied)
- **SSL:** Cloudflare Full mode + IONOS Sectigo wildcard cert (`*.healthsphere.info`)

### Local Development Setup

```bash
# 1. Clone the repo
git clone https://github.com/prajwalsk53/HealthSphere.git
cd HealthSphere

# 2. Copy config templates
cp config/db.example.php config/db.php        # update DB credentials
cp config/ai.example.php config/ai.php        # add API keys
cp config/mail.example.php config/mail.php    # add SMTP credentials

# 3. Import database
# Open phpMyAdmin → create database → import sql/healthsphere.sql

# 4. Access locally
# http://localhost/HealthSphere/
```

---

## 15. Demo Accounts

All demo passwords: `password`

| Name | Role | Email | NHS ID |
|------|------|-------|--------|
| System Admin | Admin | admin@healthsphere.info | ADMIN001 |
| Emma Patel | Patient | emma.patel@email.com | RT44656GRG |
| James Hall | Patient | james.hall@email.com | PT556677AA |
| Aisha Khan | Patient | aisha.khan@email.com | PT998877BB |
| Dr. Emma Hall | Doctor | emma.hall@leicesterhospital.nhs.uk | DR001EMMA |
| Dr. Jacob Lopez | Doctor | jacob.lopez@leicesterhospital.nhs.uk | DR002JACOB |
| Dr. Quinn Cooper | Doctor | quinn.cooper@leicesterhospital.nhs.uk | DR003QUINN |
| Dr. James Fletcher | Doctor | james.fletcher@starlight.nhs.uk | DR004JAMES |
| Dr. Alina Fatima | Doctor | alina.fatima@apexcare.nhs.uk | DR005ALINA |
| William Jayson | Government | w.jayson@dhsc.gov.uk | GOV001WJ |

### Emma Patel's Pre-loaded Medical Data
- **Allergies:** Insulin (severe), Codeine (moderate), Pollen (mild), Latex (moderate)
- **Conditions:** High cholesterol, thyroid issues
- **Blood type:** AB+
- **Assigned doctor:** Dr. Emma Hall (General Practice)

---

## 16. Flutter Mobile App

**Location:** `/flutter_app/`  
**Framework:** Flutter (Dart)  
**Targets:** Android, iOS, Web

### Screens
- Authentication (login, register)
- Patient dashboard
- Appointments calendar
- Diet tracker
- Health metrics / vitals
- Messaging
- Notifications
- Profile

### API Connection
Communicates with the same `/api/v1/` REST endpoints using JWT Bearer token authentication. Token stored in device secure storage.

### Build
```bash
cd flutter_app
flutter pub get
flutter run          # development
flutter build apk    # Android release
flutter build ios    # iOS release
```

---

## Summary Statistics

| Metric | Count |
|--------|-------|
| User roles | 4 |
| Database tables | 20 |
| PHP pages | 66 |
| REST API endpoints | 18+ |
| Email templates | 13 |
| Demo accounts | 10 |
| External integrations | 6 |
| Appointment statuses | 7 |
| Health metric types | 13 |
| Health score factors | 8 |
| AI insight rules | 10+ |

---

*Generated: May 2026 | HealthSphere v2.0*
