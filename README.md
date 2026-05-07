# HealthSphere — Full-Stack Healthcare Management System

> A comprehensive, multi-role healthcare web application built with PHP, MySQL, and vanilla CSS. Designed to connect patients, doctors, administrators, and government health officials on a single platform — inspired by NHS digital infrastructure.

---

## Overview

HealthSphere is a complete healthcare management system I built from scratch as a full-stack project. It covers every layer of a real-world healthcare workflow — from patients booking appointments and tracking their diet, to doctors managing prescriptions and lab results, to government officials monitoring regional health trends.

The platform features AI-powered tools (meal planning, health insights, ingredient safety scanning), a real-time messaging system, family health history tracking, an interactive health map, and a REST API for mobile integration.

---

## Features

### Patient Portal
- **Dashboard** — Health score, upcoming appointments, recent metrics, AI-generated insights
- **Appointments** — Book, reschedule, and cancel appointments with doctors
- **Medical Records** — View diagnoses, lab results, and treatment history
- **Documents** — Upload and manage health documents (PDFs, images)
- **Diet & Nutrition Tracker** — Log meals, track macros, water intake, weekly trends
- **Safe Appetite** — Ingredient scanner that checks food labels against personal allergies, intolerances, and dietary preferences with smart synonym detection (detects hidden names like "casein" for dairy, "semolina" for gluten)
- **Health Insights** — Personalised health metrics with Chart.js visualisations
- **Health Analysis** — Deep analysis of trends across blood pressure, glucose, BMI, and more
- **Health Map** — Interactive map showing nearby NHS facilities
- **AI Assistant** — Conversational AI for health queries, meal recipes, and personalised advice
- **Family History** — Track hereditary conditions across family members
- **Messages** — Real-time secure messaging with doctors
- **Notifications** — Smart alerts for appointments, results, and health reminders
- **Calendar** — Full calendar view of all health events

### Doctor Portal
- **Dashboard** — Patient overview, today's appointments, recent alerts
- **My Patients** — Full patient list with medical history access
- **Schedule** — Manage appointment availability
- **Lab Results** — Upload and annotate patient lab results
- **Prescriptions** — Issue and manage prescriptions with medication tracking
- **Clinical Notes** — Add secure notes to patient records
- **Messages** — Direct messaging with patients
- **Alerts & Tasks** — Priority-sorted health alerts

### Admin Portal
- **Dashboard** — Platform-wide statistics and activity overview
- **Analytics** — User growth, appointment trends, system health charts
- **User Management** — Manage patients, doctors, and staff accounts
- **Doctor Access Control** — Approve doctor registrations and manage permissions
- **Approval Queue** — Review pending account registrations
- **Food Database** — Curated food nutrition database used by the diet tracker
- **Genetic Diseases Registry** — Database of hereditary conditions and food triggers
- **Access Logs** — Full audit trail of system access
- **Email Testing** — SMTP integration testing

### Government Portal
- **Public Health Dashboard** — Aggregated anonymised health statistics
- **Regional Map** — Geographic distribution of health conditions
- **Live Health Map** — Real-time health data visualised on a map
- **Trend Analysis** — Population-level disease and condition trends
- **Alerts** — Public health alert management
- **Reports** — Generate and export health reports

### Safe Appetite (Food Safety Module)
- Set up a personal food safety profile with allergies (Big 14 EU allergens), intolerances, dietary preferences, and ingredient dislikes
- Paste any food label's ingredient list — the scanner checks against 170+ ingredient synonyms and hidden names
- Instant SAFE / CAUTION / DANGER result with specific flagged ingredients and reasons
- Scan history saved for quick reference
- Works fully offline — no API calls required

### REST API (v1)
- JWT-style session authentication
- Endpoints for auth, dashboard data, health metrics, appointments, notifications, profile, messages, and diet

### Flutter Mobile App (Companion)
- Android & iOS companion app scaffolded with Flutter
- Connects to the same PHP/MySQL backend via the REST API

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 |
| Database | MySQL 8 via PDO |
| Frontend | Vanilla HTML/CSS/JS (no Bootstrap) |
| Charts | Chart.js |
| Icons | Font Awesome 6 |
| Fonts | Google Fonts (Inter) |
| AI | Anthropic Claude API (claude-haiku-4-5) |
| Email | SMTP via PHPMailer / cURL |
| Maps | Leaflet.js |
| Mobile | Flutter (Dart) |
| Local Server | XAMPP (Apache + MySQL) |

---

## Project Structure

```
HealthSphere/
├── config/                 # Database, AI, mail config (use *.example.php)
├── includes/               # Shared PHP: sidebar, functions, health score, AI insights
├── assets/
│   ├── css/style.css       # Full custom CSS design system (navy/blue theme)
│   └── js/main.js          # Shared JS utilities
├── patient/                # All patient-facing pages
├── doctor/                 # All doctor-facing pages
├── admin/                  # Admin portal pages
├── government/             # Government portal pages
├── api/                    # AJAX endpoints (meal AI, appointments, chat, etc.)
│   └── v1/                 # REST API for mobile
├── sql/
│   ├── healthsphere.sql    # Full database schema + seed data
│   └── *_migration.php     # Incremental schema migrations
├── flutter_app/            # Flutter mobile companion app
├── setup.php               # One-click database setup
└── index.php               # Login page
```

---

## Getting Started

### Prerequisites
- XAMPP (Apache + MySQL) — [download here](https://www.apachefriends.org/)
- PHP 8.0+
- A modern browser

### Installation

**1. Clone the repository**
```bash
git clone https://github.com/YOUR_USERNAME/HealthSphere.git
cd HealthSphere
```
Place the folder in your XAMPP `htdocs` directory.

**2. Set up configuration files**
```bash
# Copy example configs and fill in your details
cp config/db.example.php config/db.php
cp config/ai.example.php config/ai.php
cp config/mail.example.php config/mail.php
```
Edit each file with your credentials. The database config works out of the box for a default XAMPP install (root / no password).

**3. Set up the database**

Start Apache and MySQL in XAMPP, then visit:
```
http://localhost/HealthSphere/setup.php
```
This creates the `healthsphere` database, all tables, and loads seed data including demo accounts.

**4. Open the app**
```
http://localhost/HealthSphere
```

---

## Demo Accounts

All demo accounts use the password: **`password`**

| Role | Email | Access |
|---|---|---|
| Patient | patient@healthsphere.com | Full patient portal |
| Doctor | doctor@healthsphere.com | Doctor dashboard |
| Admin | admin@healthsphere.com | Admin control panel |
| Government | govt@healthsphere.com | Public health portal |

---

## AI Features

The platform uses the **Anthropic Claude API** for:
- **AI Meal Assistant** — Personalised recipe suggestions and cooking instructions based on your health profile
- **Health Insights** — AI-generated summaries of your health trends
- **AI Assistant** — General health Q&A chatbot
- **Safe Appetite Scanner** — Smart ingredient analysis (also has a full local fallback engine that works without API credits)

To enable AI features, add your Anthropic API key to `config/ai.php`. Get a free key at [console.anthropic.com](https://console.anthropic.com).

> The Safe Appetite scanner works fully without an API key using the built-in rule-based engine.

---

## Safe Appetite — Food Safety Module

A web-based equivalent of apps like Safe Food or Safe Appetite. Users set up a one-time food safety profile:

- **Food Allergies** — Select from the Big 14 EU allergens, set severity (Mild / Moderate / Severe), or add custom ones
- **Food Intolerances** — Lactose, Gluten, Histamine, Caffeine, MSG, and more
- **Dietary Preferences** — Vegan, Vegetarian, Keto, Halal, Kosher, Gluten-Free, etc.
- **Ingredient Dislikes** — Personal ingredients to flag

Then paste any food product's ingredient list — the scanner instantly checks 170+ ingredient synonyms and hidden names (e.g. detects "casein" as dairy, "semolina" as wheat, "albumin" as egg) and returns a colour-coded SAFE / CAUTION / DANGER result with specific alerts.

---

## Screenshots

> Add screenshots of your app here. Suggested pages to capture:
> - Patient Dashboard
<img width="1887" height="912" alt="image" src="https://github.com/user-attachments/assets/0072aa5a-9f6f-4464-8e55-c639faf534ed" />

> - Safe Appetite Scanner with results
<img width="1890" height="920" alt="image" src="https://github.com/user-attachments/assets/81ae562d-0d3b-48d4-a149-f8fad92e5a18" />

> - Diet Tracker
> - Health Analysis charts
> - Admin Dashboard
> - Doctor Patient view

---

## Database Schema

The schema covers 20+ tables including:

`users` · `doctors` · `appointments` · `medical_records` · `allergies` · `prescriptions` · `diet_logs` · `water_logs` · `health_metrics` · `food_database` · `family_history` · `messages` · `notifications` · `genetic_diseases` · `clinical_notes` · `access_logs` · `diet_preferences` · `food_intolerances` · `ingredient_dislikes` · `ingredient_scans`

Full schema with seed data: [`sql/healthsphere.sql`](sql/healthsphere.sql)

---

## Design System

Custom CSS design system built without any CSS framework:

- **Primary colour:** Dark Navy `#0A1F44`
- **Accent:** Blue `#1565C0`
- **Typography:** Inter (Google Fonts)
- Responsive sidebar layout
- Consistent card, button, badge, and form components
- Chart.js data visualisations

---

## API Reference (v1)

Base URL: `http://localhost/HealthSphere/api/v1/`

| Method | Endpoint | Description |
|---|---|---|
| POST | `/auth.php` | Login / logout |
| GET | `/dashboard.php` | Dashboard summary data |
| GET/POST | `/health.php` | Health metrics |
| GET/POST | `/appointments.php` | Appointments |
| GET | `/notifications.php` | Notifications |
| GET/POST | `/profile.php` | User profile |
| GET/POST | `/messages.php` | Messages |
| GET/POST | `/diet.php` | Diet logs |

---

## Roadmap

- [ ] Two-factor authentication (2FA)
- [ ] NHS API integration for real prescription data
- [ ] Telemedicine video call feature
- [ ] Barcode scanner for Safe Appetite (camera access)
- [ ] Push notifications via FCM
- [ ] Dark mode

---

## Author

**Prajwal** — Full-Stack Developer

Built as a portfolio project demonstrating end-to-end web development: database design, backend PHP/PDO, custom frontend CSS, REST API design, AI integration, and mobile companion app.

---

## License

This project is open-source and available under the [MIT License](LICENSE).
