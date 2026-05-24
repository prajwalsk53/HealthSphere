<?php
require_once __DIR__ . '/config/config.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$userName = htmlspecialchars(trim(($_SESSION['user_first'] ?? '') . ' ' . ($_SESSION['user_last'] ?? '')));
$userRole = ucfirst($_SESSION['user_role'] ?? 'User');
if ($userRole === 'Pharmacy') $userRole = 'Medical Team';
$today = date('d F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HealthSphere — Application Guide</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'DM Sans', sans-serif;
    font-size: 10.5pt;
    color: #1a202c;
    background: #f0f4f8;
    line-height: 1.65;
  }

  /* ── Toolbar ── */
  .toolbar {
    position: fixed; top: 0; left: 0; right: 0; z-index: 100;
    background: #0A1F44;
    padding: 0.875rem 2rem;
    display: flex; align-items: center; justify-content: space-between;
    box-shadow: 0 2px 12px rgba(0,0,0,0.3);
  }
  .toolbar-brand { color: #fff; font-family: 'Playfair Display', serif; font-size: 1.1rem; }
  .toolbar-brand span { color: #64B5F6; }
  .toolbar-actions { display: flex; gap: 0.75rem; align-items: center; }
  .btn-download {
    background: #1565C0; color: #fff; border: none; border-radius: 8px;
    padding: 0.6rem 1.4rem; font-family: 'DM Sans', sans-serif;
    font-weight: 600; font-size: 0.875rem; cursor: pointer;
    display: flex; align-items: center; gap: 0.5rem;
    text-decoration: none; transition: background 0.2s;
  }
  .btn-download:hover { background: #0D47A1; }
  .btn-back {
    color: rgba(255,255,255,0.7); text-decoration: none;
    font-size: 0.875rem; display: flex; align-items: center; gap: 0.4rem;
  }
  .btn-back:hover { color: #fff; }

  /* ── Document ── */
  .doc-wrap { max-width: 820px; margin: 5rem auto 3rem; padding: 0 1.5rem; }

  /* ── Cover ── */
  .cover {
    background: linear-gradient(135deg, #0A1F44 0%, #0D2A5C 100%);
    border-radius: 16px; padding: 4rem 3.5rem;
    margin-bottom: 2.5rem; color: #fff;
    position: relative; overflow: hidden;
  }
  .cover::before {
    content: ''; position: absolute; top: -60px; right: -60px;
    width: 300px; height: 300px; border-radius: 50%;
    background: rgba(21,101,192,0.2);
  }
  .cover::after {
    content: ''; position: absolute; bottom: -40px; right: 80px;
    width: 180px; height: 180px; border-radius: 50%;
    background: rgba(21,101,192,0.12);
  }
  .cover-label {
    font-size: 0.75rem; letter-spacing: 0.12em; text-transform: uppercase;
    color: #64B5F6; font-weight: 600; margin-bottom: 1rem;
  }
  .cover-title {
    font-family: 'Playfair Display', serif;
    font-size: 2.6rem; line-height: 1.2; margin-bottom: 0.5rem;
  }
  .cover-title span { color: #64B5F6; }
  .cover-subtitle { font-size: 1rem; color: rgba(255,255,255,0.65); margin-bottom: 2.5rem; }
  .cover-meta {
    display: flex; gap: 2.5rem; flex-wrap: wrap;
    border-top: 1px solid rgba(255,255,255,0.12);
    padding-top: 1.5rem; font-size: 0.85rem; color: rgba(255,255,255,0.55);
  }
  .cover-meta strong { display: block; color: #fff; font-size: 0.9rem; }

  /* ── TOC ── */
  .toc {
    background: #fff; border-radius: 12px; padding: 2rem 2.5rem;
    margin-bottom: 2.5rem; border: 1px solid #e2e8f0;
  }
  .toc h2 {
    font-family: 'Playfair Display', serif; font-size: 1.15rem; color: #0A1F44;
    margin-bottom: 1.25rem; padding-bottom: 0.75rem;
    border-bottom: 2px solid #1565C0; display: inline-block;
  }
  .toc-list { list-style: none; }
  .toc-list li {
    display: flex; justify-content: space-between; align-items: baseline;
    padding: 0.35rem 0; border-bottom: 1px dotted #e2e8f0;
  }
  .toc-list li:last-child { border-bottom: none; }
  .toc-list a { color: #0A1F44; text-decoration: none; font-weight: 500; }
  .toc-list a:hover { color: #1565C0; }
  .toc-num { font-family: 'DM Mono', monospace; font-size: 0.8rem; color: #94a3b8; }

  /* ── Sections ── */
  .section {
    background: #fff; border-radius: 12px; padding: 2.5rem;
    margin-bottom: 2rem; border: 1px solid #e2e8f0;
  }
  .section-header {
    display: flex; align-items: center; gap: 1rem;
    margin-bottom: 1.5rem; padding-bottom: 1rem;
    border-bottom: 1px solid #f1f5f9;
  }
  .section-num {
    background: #0A1F44; color: #fff;
    font-family: 'DM Mono', monospace; font-size: 0.85rem;
    width: 2rem; height: 2rem; border-radius: 6px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  }
  .section h2 { font-family: 'Playfair Display', serif; font-size: 1.35rem; color: #0A1F44; }
  .section h3 { font-size: 0.95rem; font-weight: 600; color: #0A1F44; margin: 1.25rem 0 0.5rem; }
  .section p { color: #374151; margin-bottom: 0.75rem; }
  .section ul, .section ol { padding-left: 1.5rem; color: #374151; margin-bottom: 0.75rem; }
  .section li { margin-bottom: 0.35rem; }

  /* ── Role cards ── */
  .role-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem; margin-top: 1rem;
  }
  .role-card {
    border: 1.5px solid #e2e8f0; border-radius: 10px;
    padding: 1.25rem; position: relative; overflow: hidden;
  }
  .role-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
  }
  .role-card.patient::before  { background: #1565C0; }
  .role-card.doctor::before   { background: #059669; }
  .role-card.admin::before    { background: #0A1F44; }
  .role-card.govt::before     { background: #7c3aed; }
  .role-card.pharmacy::before { background: #d97706; }
  .role-icon { font-size: 1.75rem; margin-bottom: 0.5rem; }
  .role-card h4 { font-size: 0.9rem; font-weight: 600; color: #0A1F44; margin-bottom: 0.4rem; }
  .role-card p  { font-size: 0.82rem; color: #64748b; margin: 0; }

  /* ── Workflow steps ── */
  .workflow { display: flex; flex-direction: column; gap: 0; margin: 1rem 0; }
  .step {
    display: flex; gap: 1rem; align-items: flex-start;
    position: relative; padding-bottom: 1.25rem;
  }
  .step:last-child { padding-bottom: 0; }
  .step-line {
    position: absolute; left: 1rem; top: 2rem; bottom: 0;
    width: 2px; background: #e2e8f0;
  }
  .step:last-child .step-line { display: none; }
  .step-num {
    width: 2rem; height: 2rem; border-radius: 50%;
    background: #1565C0; color: #fff;
    font-size: 0.8rem; font-weight: 600;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; position: relative; z-index: 1;
  }
  .step-body h4 { font-size: 0.9rem; font-weight: 600; color: #0A1F44; margin-bottom: 0.2rem; }
  .step-body p  { font-size: 0.85rem; color: #64748b; margin: 0; }

  /* ── Feature table ── */
  .feat-table { width: 100%; border-collapse: collapse; margin-top: 0.75rem; }
  .feat-table th {
    text-align: left; padding: 0.6rem 0.875rem;
    background: #EEF4FF; color: #0A1F44;
    font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
    border-bottom: 2px solid #DBEAFE;
  }
  .feat-table td {
    padding: 0.7rem 0.875rem; border-bottom: 1px solid #f1f5f9;
    font-size: 0.875rem; vertical-align: top;
  }
  .feat-table tr:last-child td { border-bottom: none; }
  .feat-table td:first-child { font-weight: 500; color: #0A1F44; width: 30%; }

  /* ── Badges ── */
  .badge {
    display: inline-block; padding: 0.2rem 0.6rem;
    border-radius: 999px; font-size: 0.75rem; font-weight: 600;
    margin: 1px;
  }
  .badge-blue   { background: #DBEAFE; color: #1e40af; }
  .badge-green  { background: #D1FAE5; color: #065f46; }
  .badge-navy   { background: #EEF4FF; color: #0A1F44; }
  .badge-purple { background: #EDE9FE; color: #5b21b6; }
  .badge-amber  { background: #FEF3C7; color: #92400e; }
  .badge-red    { background: #FEE2E2; color: #991b1b; }

  /* ── Info box ── */
  .info-box {
    background: #EEF4FF; border: 1px solid #BFDBFE;
    border-radius: 8px; padding: 1rem 1.25rem;
    margin: 1rem 0; font-size: 0.875rem; color: #1e3a5f;
  }

  /* ── Print ── */
  @media print {
    body { background: #fff; font-size: 10pt; }
    .toolbar { display: none !important; }
    .doc-wrap { margin: 0; padding: 0; max-width: 100%; }
    .cover { border-radius: 0; margin-bottom: 0; }
    .cover::before, .cover::after { display: none; }
    .section, .toc { border-radius: 0; border: none; border-bottom: 1px solid #e2e8f0; }
    .section { page-break-inside: avoid; }
    .page-break { page-break-before: always; }
    a { color: inherit; text-decoration: none; }
    .role-grid { grid-template-columns: repeat(3, 1fr); }
  }
</style>
</head>
<body>

<div class="toolbar">
  <div class="toolbar-brand">Health<span>Sphere</span> — Application Guide</div>
  <div class="toolbar-actions">
    <a href="javascript:history.back()" class="btn-back">← Back</a>
    <button class="btn-download" onclick="window.print()">⬇ Download as PDF</button>
  </div>
</div>

<div class="doc-wrap">

  <!-- COVER -->
  <div class="cover">
    <div class="cover-label">NHS Connected Healthcare Platform</div>
    <h1 class="cover-title">Health<span>Sphere</span><br>Application Guide</h1>
    <p class="cover-subtitle">A complete overview of HealthSphere — roles, workflows, features, and how everything connects.</p>
    <div class="cover-meta">
      <div><strong>Prepared for</strong><?= $userName ?> (<?= $userRole ?>)</div>
      <div><strong>Generated</strong><?= $today ?></div>
      <div><strong>Version</strong>1.0 — NHS Digital Platform</div>
    </div>
  </div>

  <!-- TABLE OF CONTENTS -->
  <div class="toc">
    <h2>Contents</h2>
    <ul class="toc-list">
      <li><a href="#s1">1. What is HealthSphere?</a><span class="toc-num">Overview</span></li>
      <li><a href="#s2">2. User Roles</a><span class="toc-num">Roles</span></li>
      <li><a href="#s3">3. Patient Journey</a><span class="toc-num">Workflow</span></li>
      <li><a href="#s4">4. Doctor Journey</a><span class="toc-num">Workflow</span></li>
      <li><a href="#s5">5. Medical Team (Pharmacy)</a><span class="toc-num">Workflow</span></li>
      <li><a href="#s6">6. Admin &amp; Government</a><span class="toc-num">Workflow</span></li>
      <li><a href="#s7">7. Key Features</a><span class="toc-num">Features</span></li>
      <li><a href="#s8">8. Payments &amp; Stripe</a><span class="toc-num">Payments</span></li>
      <li><a href="#s9">9. Security &amp; Access</a><span class="toc-num">Security</span></li>
    </ul>
  </div>

  <!-- SECTION 1 — WHAT IS HEALTHSPHERE -->
  <div class="section" id="s1">
    <div class="section-header">
      <div class="section-num">1</div>
      <h2>What is HealthSphere?</h2>
    </div>
    <p>
      <strong>HealthSphere</strong> is a full-stack NHS-connected healthcare management platform that digitises the complete patient care lifecycle — from booking a GP appointment and ordering prescriptions through to government-level health analytics.
    </p>
    <p>
      The platform connects five key parties: <strong>Patients</strong>, who manage their own health records and bookings; <strong>Doctors</strong>, who review appointments and manage clinical records; <strong>Medical Team / Pharmacy</strong>, who dispense approved prescriptions; <strong>Administrators</strong>, who govern the platform and approve accounts; and <strong>Government Analysts</strong>, who monitor population health trends.
    </p>
    <div class="info-box">
      HealthSphere is built with PHP 8, MySQL (MariaDB), and standard web technologies. It is hosted on IONOS and accessible at <strong>healthsphere.info/HealthSphere</strong>. Payments are processed via Stripe in GBP.
    </div>
    <h3>Core Objectives</h3>
    <ul>
      <li>Give patients a single digital portal for appointments, prescriptions, records, and health tracking</li>
      <li>Enable doctors to manage their clinical schedule, patient notes, and prescription approvals</li>
      <li>Allow the Medical Team to queue, prepare, and dispatch approved prescriptions</li>
      <li>Provide admins with full platform governance — user approvals, audit logs, analytics</li>
      <li>Give government analysts a live view of population health trends for policy decisions</li>
      <li>Process all financial transactions securely via Stripe (doctor consultations &amp; NHS prescription fees)</li>
    </ul>
  </div>

  <!-- SECTION 2 — USER ROLES -->
  <div class="section" id="s2">
    <div class="section-header">
      <div class="section-num">2</div>
      <h2>User Roles</h2>
    </div>
    <p>HealthSphere has five distinct roles, each with a dedicated dashboard and access permissions:</p>

    <div class="role-grid">
      <div class="role-card patient">
        <div class="role-icon">🧑‍⚕️</div>
        <h4>Patient</h4>
        <p>Books appointments, orders prescriptions, tracks health metrics, views medical records, and manages their profile.</p>
      </div>
      <div class="role-card doctor">
        <div class="role-icon">👨‍⚕️</div>
        <h4>Doctor</h4>
        <p>Reviews appointment requests, manages availability slots, approves prescriptions, adds clinical notes and lab results.</p>
      </div>
      <div class="role-card pharmacy">
        <div class="role-icon">💊</div>
        <h4>Medical Team</h4>
        <p>Receives doctor-approved prescription orders, prepares medicines, dispatches to patients, and marks deliveries complete.</p>
      </div>
      <div class="role-card admin">
        <div class="role-icon">⚙️</div>
        <h4>Administrator</h4>
        <p>Approves new doctor accounts, manages all users, monitors platform analytics, and maintains system configuration.</p>
      </div>
      <div class="role-card govt">
        <div class="role-icon">🏛️</div>
        <h4>Government Analyst</h4>
        <p>Views anonymised population health dashboards — disease prevalence, appointment trends, medication usage, and demographics.</p>
      </div>
    </div>

    <h3>Registration &amp; Account Activation</h3>
    <p>
      <strong>Patients</strong> self-register and gain immediate access. Their account is marked <em>active</em> upon registration.
    </p>
    <p>
      <strong>Doctors</strong> register through the same form but their account is held in a <em>pending</em> state until an Administrator approves it. They receive an email notification once approved.
    </p>
    <p>
      <strong>Admins, Government Analysts, and Medical Team</strong> accounts are created directly by Administrators or seeded into the database. They do not self-register.
    </p>
    <p>
      All passwords are hashed using PHP <code>PASSWORD_BCRYPT</code>. Demo accounts use the password <strong>password</strong>.
    </p>
  </div>

  <!-- SECTION 3 — PATIENT JOURNEY -->
  <div class="section page-break" id="s3">
    <div class="section-header">
      <div class="section-num">3</div>
      <h2>Patient Journey</h2>
    </div>
    <p>The following steps describe the complete journey a patient takes within HealthSphere:</p>

    <div class="workflow">
      <div class="step">
        <div class="step-num">1</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Register &amp; Sign In</h4>
          <p>Patient registers with email and password. Account is immediately active. They are redirected to their personal dashboard.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">2</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Book a Doctor Appointment</h4>
          <p>Patient selects a doctor, chooses an available date and time slot, enters a reason for the visit, then proceeds to payment. The consultation fee is charged via Stripe (the doctor's set fee in GBP) before the booking is confirmed.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">3</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Attend Appointment &amp; Receive Notes</h4>
          <p>Once the appointment status is updated by the doctor, the patient can view clinical notes, lab results, and any prescriptions issued from their Medical Records section.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">4</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Order a Prescription</h4>
          <p>From the Prescriptions page, the patient can request medicine for any active prescription. After selecting the prescription, they pay the standard NHS fee (£9.90) via Stripe. The order is sent to the doctor for approval.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">5</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Order Approved by Doctor</h4>
          <p>The doctor reviews the prescription order request and marks it approved. The order moves to the Medical Team's queue for dispensing.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">6</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Medicine Dispatched by Pharmacy</h4>
          <p>The Medical Team prepares, dispatches, and delivers the medication. The patient can track the status (Approved → Preparing → Dispatched → Delivered) from their prescriptions page.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">7</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Track Health Metrics</h4>
          <p>Patients can log daily health metrics (weight, blood pressure, heart rate, steps, sleep, water intake, calories, mood) and view trends over time using interactive charts on their dashboard.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">8</div><div class="step-line"></div>
        <div class="step-body">
          <h4>View Medical Records</h4>
          <p>All clinical notes, lab results, vaccination records, allergies, family history, and uploaded documents are accessible in the Medical Records section — organised by type and date.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">9</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Manage Profile</h4>
          <p>Patients keep their NHS profile up to date — personal details, emergency contact, GP registration, blood type, and profile photo.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- SECTION 4 — DOCTOR JOURNEY -->
  <div class="section" id="s4">
    <div class="section-header">
      <div class="section-num">4</div>
      <h2>Doctor Journey</h2>
    </div>
    <p>Doctors manage their clinical schedule, patient records, and prescription approvals through HealthSphere:</p>

    <div class="workflow">
      <div class="step">
        <div class="step-num">1</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Account Approval</h4>
          <p>Doctor registers and waits for an Administrator to approve the account. Once approved, they can sign in and are directed to their clinical dashboard.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">2</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Set Availability</h4>
          <p>Doctors configure their weekly availability — which days and time slots they are open for appointments. Patients can only book slots that have been published by the doctor.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">3</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Review Appointment Requests</h4>
          <p>Incoming appointment bookings appear in the doctor's dashboard. The doctor can view patient details, the reason for the visit, and the appointment time, then mark it as confirmed or completed.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">4</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Add Clinical Notes &amp; Lab Results</h4>
          <p>After a consultation, the doctor can record clinical notes, upload lab result files (images, PDFs), and update the patient's medical record directly through HealthSphere.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">5</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Issue Prescriptions</h4>
          <p>Doctors write prescriptions for patients (medicine name, dosage, instructions) from the prescription management section. Issued prescriptions are visible to the patient immediately.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">6</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Approve Prescription Orders</h4>
          <p>When a patient places an order for a prescription, the doctor receives it in their queue. After reviewing, they approve (sending it to the Medical Team) or reject the order with a reason.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">7</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Manage Profile &amp; Consultation Fee</h4>
          <p>Doctors maintain their NHS profile, specialisation, hospital affiliation, bio, and set their consultation fee — the amount patients are charged when booking an appointment.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- SECTION 5 — MEDICAL TEAM -->
  <div class="section page-break" id="s5">
    <div class="section-header">
      <div class="section-num">5</div>
      <h2>Medical Team (Pharmacy) Journey</h2>
    </div>
    <p>The Medical Team is the pharmacy role that physically prepares and dispatches medicines to patients after a doctor has approved an order:</p>

    <div class="workflow">
      <div class="step">
        <div class="step-num">1</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Sign In to the Medical Team Portal</h4>
          <p>The Medical Team logs in using their NHS pharmacy credentials (e.g. <em>medteam@healthsphere.nhs.uk</em>) and lands on the Medical Team Dashboard showing key order statistics.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">2</div><div class="step-line"></div>
        <div class="step-body">
          <h4>View the Medicine Queue</h4>
          <p>The Medicine Queue page shows all prescription orders that have been approved by a doctor across all doctors on the platform. Orders are visible with patient name, medicine, dosage, and current status.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">3</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Start Preparing (Approved → Preparing)</h4>
          <p>The team member clicks the action button to mark an order as <em>Preparing</em>. They can optionally add a pharmacy note (e.g. batch number, preparation instructions).</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">4</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Dispatch the Order (Preparing → Dispatched)</h4>
          <p>Once the medication is packaged, the team marks it as <em>Dispatched</em>. Courier or collection details can be added in the notes field.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">5</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Confirm Delivery (Dispatched → Delivered)</h4>
          <p>When the patient confirms receipt — or the courier confirms delivery — the Medical Team marks the order <em>Delivered</em>. This closes the order and it moves to the Completed tab.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">6</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Dashboard Statistics</h4>
          <p>The dashboard shows: orders awaiting preparation, orders in progress, dispatched today, total delivered, and revenue collected from NHS prescription payments (from the payments table).</p>
        </div>
      </div>
    </div>

    <h3>Order Status Lifecycle</h3>
    <p>Every prescription order moves through this strict one-directional chain:</p>
    <p style="margin:0.5rem 0;">
      <span class="badge badge-blue">Pending</span> →
      <span class="badge badge-green">Approved</span> →
      <span class="badge badge-amber">Preparing</span> →
      <span class="badge badge-purple">Dispatched</span> →
      <span class="badge badge-navy">Delivered</span>
    </p>
    <p style="margin-top:0.75rem;">Doctors control Pending → Approved. The Medical Team controls Approved → Preparing → Dispatched → Delivered. Neither role can skip steps or move backwards.</p>
  </div>

  <!-- SECTION 6 — ADMIN & GOVERNMENT -->
  <div class="section" id="s6">
    <div class="section-header">
      <div class="section-num">6</div>
      <h2>Admin &amp; Government Analyst</h2>
    </div>

    <h3>Administrator</h3>
    <p>The Administrator has full platform governance. Key responsibilities include:</p>
    <ul>
      <li><strong>Doctor Approvals</strong> — review pending doctor registrations, approve or reject with a reason, and notify the applicant by email</li>
      <li><strong>User Management</strong> — view all users by role, activate or deactivate accounts, edit profiles, and delete accounts</li>
      <li><strong>Platform Analytics</strong> — monitor total patients, doctors, appointments booked, prescriptions issued, and revenue processed</li>
      <li><strong>Appointment Oversight</strong> — view all appointments across all doctors with full filtering and export</li>
      <li><strong>System Configuration</strong> — manage database settings, email configuration, and application-level parameters</li>
      <li><strong>Audit Logs</strong> — review access logs showing login events, data changes, and API calls with timestamps and IP addresses</li>
    </ul>

    <h3>Government Analyst</h3>
    <p>The Government Analyst has a read-only, population-level view of anonymised health data. Key features include:</p>
    <ul>
      <li><strong>Demographics Dashboard</strong> — age distribution, gender split, blood type prevalence across registered patients</li>
      <li><strong>Disease Prevalence</strong> — most common diagnoses, conditions, and allergies recorded across the patient population</li>
      <li><strong>Appointment Trends</strong> — booking volume over time, peak periods, specialisation demand breakdown</li>
      <li><strong>Medication Usage</strong> — most prescribed medicines, prescription order volumes, NHS cost trends</li>
      <li><strong>Geographic Distribution</strong> — patient location data by city and postcode region</li>
      <li><strong>Health Metrics Trends</strong> — aggregate BMI, blood pressure, heart rate, and fitness data from patient health logs</li>
    </ul>
    <div class="info-box">
      All data presented in the Government Analyst view is aggregated and anonymised — no individual patient name, email, or NHS ID is accessible through this role.
    </div>
  </div>

  <!-- SECTION 7 — KEY FEATURES -->
  <div class="section page-break" id="s7">
    <div class="section-header">
      <div class="section-num">7</div>
      <h2>Key Features</h2>
    </div>

    <table class="feat-table">
      <thead>
        <tr><th>Feature</th><th>Description</th><th>Who Uses It</th></tr>
      </thead>
      <tbody>
        <tr>
          <td>Appointment Booking</td>
          <td>Patients book from real-time doctor availability slots. Payment is required before confirmation. Doctors set their own schedule and consultation fee.</td>
          <td><span class="badge badge-blue">Patient</span> <span class="badge badge-green">Doctor</span></td>
        </tr>
        <tr>
          <td>Stripe Payment Processing</td>
          <td>Consultation fees (doctor-set, in pence) and NHS prescription fees (fixed £9.90) are charged via Stripe Elements with full PaymentIntent verification before any booking or order is recorded.</td>
          <td><span class="badge badge-blue">Patient</span></td>
        </tr>
        <tr>
          <td>Prescription Management</td>
          <td>Doctors issue prescriptions; patients order them with payment; doctors approve/reject; the Medical Team prepares and delivers. Full end-to-end tracked workflow.</td>
          <td><span class="badge badge-blue">Patient</span> <span class="badge badge-green">Doctor</span> <span class="badge badge-amber">Med Team</span></td>
        </tr>
        <tr>
          <td>Medical Records</td>
          <td>Centralised patient record covering clinical notes, lab results (with file attachments), vaccinations, allergies, family history, and documents. Doctors write; patients read.</td>
          <td><span class="badge badge-blue">Patient</span> <span class="badge badge-green">Doctor</span></td>
        </tr>
        <tr>
          <td>Health Metrics Tracking</td>
          <td>Patients log daily readings — weight, blood pressure, heart rate, steps, sleep, mood, water, calories. Interactive Chart.js graphs show trends over 7 / 30 / 90 days.</td>
          <td><span class="badge badge-blue">Patient</span></td>
        </tr>
        <tr>
          <td>Doctor Availability Slots</td>
          <td>Doctors publish weekly time slots per day. Patients can only book slots that are available. Already-booked slots are removed from the selection.</td>
          <td><span class="badge badge-green">Doctor</span> <span class="badge badge-blue">Patient</span></td>
        </tr>
        <tr>
          <td>Messaging System</td>
          <td>In-app direct messaging between patients and doctors. Unread message counts displayed as sidebar badges. Messages are threaded by conversation partner.</td>
          <td><span class="badge badge-blue">Patient</span> <span class="badge badge-green">Doctor</span></td>
        </tr>
        <tr>
          <td>Notifications</td>
          <td>Real-time in-app notifications for appointment status changes, prescription updates, and new messages. Bell icon with unread count in the navigation bar.</td>
          <td>All roles</td>
        </tr>
        <tr>
          <td>Medicine Queue</td>
          <td>The Medical Team's dedicated view of all approved prescription orders across all doctors — tabbed by Active and Completed, with one-click status advancement.</td>
          <td><span class="badge badge-amber">Med Team</span></td>
        </tr>
        <tr>
          <td>Doctor Approval Workflow</td>
          <td>New doctor registrations require explicit Administrator approval. Pending accounts cannot log in. Admins receive a list of all pending applications with approve/reject controls.</td>
          <td><span class="badge badge-navy">Admin</span> <span class="badge badge-green">Doctor</span></td>
        </tr>
        <tr>
          <td>Government Health Dashboard</td>
          <td>Anonymised, aggregate population health data — demographics, disease trends, medication usage, appointment volumes — presented via charts for policy-level analysis.</td>
          <td><span class="badge badge-purple">Government</span></td>
        </tr>
        <tr>
          <td>File &amp; Image Uploads</td>
          <td>Lab results, prescriptions, and medical documents can be uploaded as images or PDFs. Files are stored securely and accessible only to authorised users.</td>
          <td><span class="badge badge-green">Doctor</span> <span class="badge badge-blue">Patient</span></td>
        </tr>
        <tr>
          <td>Audit Access Logs</td>
          <td>Every login event, data modification, and API call is logged with user ID, action type, timestamp, and IP address for compliance and security review.</td>
          <td><span class="badge badge-navy">Admin</span></td>
        </tr>
        <tr>
          <td>reCAPTCHA Login Protection</td>
          <td>Google reCAPTCHA v2 is required at the login page to prevent brute-force and automated login attempts.</td>
          <td>All roles</td>
        </tr>
        <tr>
          <td>NHS Login Button</td>
          <td>A prominent NHS SSO login button is displayed on the login page for users who prefer to authenticate via NHS credentials (decorative in demo mode).</td>
          <td>All roles</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- SECTION 8 — PAYMENTS -->
  <div class="section" id="s8">
    <div class="section-header">
      <div class="section-num">8</div>
      <h2>Payments &amp; Stripe</h2>
    </div>
    <p>HealthSphere uses <strong>Stripe</strong> for all financial transactions. Payments are processed in GBP using Stripe's secure PaymentIntent API — card details never touch the HealthSphere server.</p>

    <h3>Payment Types</h3>
    <table class="feat-table">
      <thead>
        <tr><th>Type</th><th>Amount</th><th>When</th></tr>
      </thead>
      <tbody>
        <tr>
          <td>Doctor Appointment</td>
          <td>Doctor's consultation fee (set per doctor)</td>
          <td>Before the appointment booking is confirmed</td>
        </tr>
        <tr>
          <td>NHS Prescription Fee</td>
          <td>Fixed £9.90</td>
          <td>Before a prescription order is submitted to the doctor</td>
        </tr>
      </tbody>
    </table>

    <h3>Payment Flow</h3>
    <div class="workflow" style="margin-top:1rem;">
      <div class="step">
        <div class="step-num">1</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Patient Fills in Details (Step 1)</h4>
          <p>Patient selects doctor, date, time, and enters a reason. For prescriptions, they select which prescription to order.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">2</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Stripe Card Entry (Step 2)</h4>
          <p>The modal advances to the payment step. The server creates a PaymentIntent via <code>api/create-payment-intent.php</code> and returns a <code>client_secret</code>. Stripe Elements renders a secure card input field.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">3</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Payment Confirmed by Stripe</h4>
          <p>The browser calls <code>stripe.confirmCardPayment()</code>. Stripe processes the card and returns success or an error message shown to the patient.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">4</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Booking / Order Recorded</h4>
          <p>On success, the <code>payment_intent_id</code> is submitted with the form. The server re-verifies the PaymentIntent status with Stripe before writing the appointment or prescription order to the database.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-num">5</div><div class="step-line"></div>
        <div class="step-body">
          <h4>Payment Stored</h4>
          <p>A record is written to the <code>payments</code> table with the Stripe PaymentIntent ID, amount, currency, status, and description — providing a full audit trail of all financial transactions.</p>
        </div>
      </div>
    </div>

    <div class="info-box">
      <strong>Test Mode:</strong> HealthSphere currently runs with Stripe <em>test</em> API keys. Use test card number <strong>4242 4242 4242 4242</strong> with any future expiry date and any 3-digit CVC to simulate a successful payment. No real money is charged.
    </div>
  </div>

  <!-- SECTION 9 — SECURITY -->
  <div class="section" id="s9">
    <div class="section-header">
      <div class="section-num">9</div>
      <h2>Security &amp; Access Control</h2>
    </div>

    <h3>Authentication</h3>
    <ul>
      <li>All users authenticate with email and password. Passwords are hashed with PHP <code>PASSWORD_BCRYPT</code> — never stored in plain text.</li>
      <li>Sessions are used for authenticated state. Each page calls <code>requireRole()</code> which validates the session role matches the expected role for that page.</li>
      <li>A reCAPTCHA v2 challenge is required at the login page to prevent automated brute-force attacks.</li>
    </ul>

    <h3>Role-Based Access Control</h3>
    <ul>
      <li>Every PHP page begins with <code>requireRole('role')</code>. If the session role doesn't match, the user is redirected to their own dashboard.</li>
      <li>API endpoints check role before processing any action — a patient cannot call doctor-only API routes and vice versa.</li>
      <li>The Medical Team (pharmacy) role is isolated — it cannot approve/reject prescriptions (doctor action) or view patient health metrics.</li>
    </ul>

    <h3>Payment Security</h3>
    <ul>
      <li>Card data is never sent to HealthSphere's servers. Stripe Elements handles all card input client-side over Stripe's PCI-DSS compliant infrastructure.</li>
      <li>The Stripe secret key is stored in <code>config/stripe.php</code> which is gitignored and must be manually uploaded to the server — it is never committed to version control.</li>
      <li>Every PaymentIntent is re-verified server-side by calling <code>PaymentIntent::retrieve()</code> from the Stripe PHP SDK before writing any booking or order to the database.</li>
    </ul>

    <h3>Input Handling &amp; SQL Safety</h3>
    <ul>
      <li>All user-facing output is escaped with <code>htmlspecialchars()</code> to prevent XSS.</li>
      <li>All database queries use PDO prepared statements with bound parameters — SQL injection is not possible through any user-facing input.</li>
      <li>File uploads are validated by MIME type and extension, renamed to a UUID-based filename, and stored outside the web root where possible.</li>
    </ul>

    <h3>Data Access Scoping</h3>
    <ul>
      <li>Patients only see their own records — all queries are scoped with <code>WHERE user_id = :current_user</code>.</li>
      <li>Doctors only see appointments and prescriptions assigned to them.</li>
      <li>Government Analysts only receive aggregated data — no query returns individual patient identifiers.</li>
    </ul>

    <div class="info-box" style="margin-top:1.25rem;">
      This guide was generated automatically by HealthSphere on <?= $today ?> for <?= $userName ?> (<?= $userRole ?>). It reflects the current state of the platform.
    </div>
  </div>

</div><!-- /.doc-wrap -->

<script>
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
        e.preventDefault();
        const target = document.querySelector(a.getAttribute('href'));
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});
</script>
</body>
</html>
