<?php
require_once __DIR__ . '/config/config.php';
if (isLoggedIn()) redirectByRole($_SESSION['user_role']);

$error = $success = '';
$step  = 1;

// Read PRG success from session
if (isset($_GET['done'], $_SESSION['reg_success'])) {
    $success = $_SESSION['reg_success'];
    unset($_SESSION['reg_success']);
    $step = 3;
}
$selectedRole = $_GET['role'] ?? 'patient';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role     = $_POST['role']       ?? 'patient';
    $first    = trim($_POST['first_name'] ?? '');
    $last     = trim($_POST['last_name']  ?? '');
    $email    = trim($_POST['email']      ?? '');
    $phone    = trim($_POST['phone']      ?? '');
    $dob      = $_POST['dob']             ?? '';
    $gender   = $_POST['gender']          ?? '';
    $blood    = $_POST['blood_type']      ?? '';
    $password = $_POST['password']        ?? '';
    $confirm  = $_POST['confirm']         ?? '';

    // TODO: re-enable reCAPTCHA when ready
    if (!$first || !$last || !$email || !$password)
        $error = 'Please fill in all required fields.';
    elseif ($password !== $confirm)
        $error = 'Passwords do not match.';
    elseif (strlen($password) < 8)
        $error = 'Password must be at least 8 characters.';
    else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'This email address is already registered.';
        } else {
            try {
                $pdo->beginTransaction();

                $prefix  = ['patient'=>'PT','doctor'=>'DR','government'=>'GV'][$role] ?? 'US';
                $nhsId   = strtoupper($prefix) . strtoupper(substr($first,0,2)) . rand(100000,999999);
                $hash    = password_hash($password, PASSWORD_DEFAULT);

                $isActive       = ($role === 'patient') ? 1 : 0;
                $approvalStatus = ($role === 'patient') ? 'approved' : 'pending';

                $stmt = $pdo->prepare("
                    INSERT INTO users
                    (nhs_id, first_name, last_name, email, password, role, phone,
                     date_of_birth, gender, blood_type, is_active, approval_status, applied_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())
                ");
                $stmt->execute([
                    $nhsId,$first,$last,$email,$hash,$role,$phone,
                    $dob?:null,$gender?:null,$blood?:null,
                    $isActive,$approvalStatus,
                ]);
                $newUserId = (int)$pdo->lastInsertId();

                // ── Doctor extra profile ───────────────────────────
                if ($role === 'doctor') {
                    $hcpc     = trim($_POST['hcpc_number'] ?? '');
                    $spec     = trim($_POST['specialization'] ?? '');
                    $hosp     = trim($_POST['hospital_name'] ?? '');
                    $hospAddr = trim($_POST['hospital_address'] ?? '');
                    $exp      = (int)($_POST['experience_years'] ?? 0);
                    $fee      = (float)($_POST['consultation_fee'] ?? 0);
                    $bio      = trim($_POST['bio'] ?? '');

                    $hcpcCheck = $pdo->prepare("SELECT id FROM doctors WHERE hcpc_number=?");
                    $hcpcCheck->execute([$hcpc]);
                    if ($hcpcCheck->fetch()) {
                        throw new Exception('This HCPC number is already registered. Please check and try again.');
                    }

                    $pdo->prepare("
                        INSERT INTO doctors
                        (user_id,hcpc_number,specialization,hospital_name,hospital_address,experience_years,consultation_fee,bio,is_verified)
                        VALUES (?,?,?,?,?,?,?,?,0)
                    ")->execute([$newUserId,$hcpc,$spec,$hosp,$hospAddr,$exp,$fee,$bio]);
                }

                $pdo->commit();

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = $e->getMessage();
            }

            // ── Only proceed if no error ───────────────────────────
            if (!$error) {

            // ── Notify all admins ──────────────────────────────────
            if ($role !== 'patient') {
                $admins = $pdo->query("SELECT id FROM users WHERE role='admin' AND is_active=1")->fetchAll();
                $roleLabel = $role === 'doctor' ? 'Doctor' : 'Government Analyst';
                foreach ($admins as $admin) {
                    $pdo->prepare("
                        INSERT INTO notifications
                        (user_id,title,message,notification_type,related_user_id)
                        VALUES (?,?,?,'system',?)
                    ")->execute([
                        $admin['id'],
                        "New {$roleLabel} Registration Pending Approval",
                        "{$first} {$last} has registered as a {$roleLabel} and is awaiting account approval.",
                        $newUserId,
                    ]);
                }
            }

            // ── Send emails (non-blocking, ignore failures) ───────
            @include_once __DIR__ . '/includes/mailer.php';
            if ($role === 'patient') {
                @mailPatientWelcome($email, "$first $last", $nhsId);
                $successData = "patient_ok|{$nhsId}|{$first}";
            } else {
                @mailApplicationReceived($email, "$first $last", $role, $nhsId);
                $extras = $role==='doctor'
                    ? ['HCPC'=>trim($_POST['hcpc_number']??'—'),'Specialization'=>trim($_POST['specialization']??'—'),'Hospital'=>trim($_POST['hospital_name']??'—')]
                    : ['Department'=>trim($_POST['gov_department']??'—'),'Staff ID'=>trim($_POST['gov_staff_id']??'—'),'Job Title'=>trim($_POST['gov_job_title']??'—')];
                @mailAdminNewApplication("$first $last", $email, $role, $nhsId, $extras);
                $successData = "pending|{$nhsId}|{$first}|{$role}";
            }
            // PRG: redirect to avoid form resubmission on back/reload
            $_SESSION['reg_success'] = $successData;
            header('Location: ' . BASE_PATH . '/register.php?done=1');
            exit;

            } // end if (!$error)
        }
    }
    $selectedRole = $role;
}

$specializations = ['General Practice','Cardiology','Neurology','Oncology','Orthopaedics','Gynaecology','Psychiatry','Dermatology','Ophthalmology','Paediatrics','Radiology','Emergency Medicine','Anaesthesiology','Odontology','Gastroenterology','Endocrinology','Urology','Nephrology'];
$departments = ['Department of Health & Social Care (DHSC)','NHS England','Public Health England (PHE)','NICE (National Institute for Health & Care Excellence)','NHS Digital','Local Authority Public Health','NHS Improvement','Care Quality Commission (CQC)','Other Government Body'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>HealthSphere — Register</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;font-family:'Inter',sans-serif;overflow:hidden;}
:root{--navy:#0A1F44;--blue:#1565C0;--cyan:#00B4D8;--muted:#5E7A99;--border:#D0E4FF;--off:#F4F8FF;}

/* ── LAYOUT ── */
.page{display:grid;grid-template-columns:42% 58%;height:100vh;}

/* ── LEFT PANEL ── */
.hero{background:linear-gradient(150deg,#5EA8F0 0%,#4285E8 25%,#3270D6 55%,#5A4FCE 100%);position:relative;overflow:hidden;display:flex;flex-direction:column;}
.hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 70% 60% at 30% 40%,rgba(255,255,255,.1) 0%,transparent 70%);}
.blob{position:absolute;border-radius:50%;filter:blur(55px);opacity:.3;animation:drift 12s ease-in-out infinite alternate;}
.blob-1{width:380px;height:380px;background:#7C3AED;top:-100px;right:-60px;}
.blob-2{width:280px;height:280px;background:#00B4D8;bottom:-60px;left:-40px;animation-delay:-5s;}
@keyframes drift{from{transform:translate(0,0);}to{transform:translate(25px,-25px);}}

.hero-top{position:relative;z-index:5;padding:24px 30px;display:flex;align-items:center;gap:12px;}
.hero-logo-mark{width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff;}
.hero-logo-text h2{font-size:18px;font-weight:800;color:#fff;margin:0;}
.hero-logo-text p{font-size:10px;color:rgba(255,255,255,.6);margin:0;letter-spacing:.5px;}

.hero-center{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px 30px;position:relative;z-index:5;}
.hero-title{font-size:26px;font-weight:900;color:#fff;text-align:center;margin-bottom:8px;}
.hero-sub{font-size:13px;color:rgba(255,255,255,.75);text-align:center;margin-bottom:32px;line-height:1.6;}

/* Role selector cards */
.role-cards{display:flex;flex-direction:column;gap:10px;width:100%;max-width:340px;}
.role-card{padding:14px 18px;border-radius:14px;border:2px solid rgba(255,255,255,.2);background:rgba(255,255,255,.1);cursor:pointer;transition:all .22s;display:flex;align-items:center;gap:14px;text-decoration:none;}
.role-card:hover{background:rgba(255,255,255,.18);border-color:rgba(255,255,255,.4);transform:translateX(4px);}
.role-card.active{background:rgba(255,255,255,.22);border-color:#fff;box-shadow:0 4px 20px rgba(0,0,0,.15);}
.role-card .rc-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.role-card .rc-text h4{font-size:14px;font-weight:800;color:#fff;margin:0;}
.role-card .rc-text p{font-size:11px;color:rgba(255,255,255,.7);margin:0;margin-top:1px;}
.role-card .rc-arrow{margin-left:auto;color:rgba(255,255,255,.5);font-size:14px;}
.role-card.active .rc-arrow{color:#fff;}

.approval-badge{background:rgba(255,165,0,.2);border:1px solid rgba(255,165,0,.4);border-radius:6px;padding:2px 8px;font-size:10px;font-weight:700;color:#FFD580;margin-left:auto;}

.hero-footer{position:relative;z-index:5;padding:16px 30px;border-top:1px solid rgba(255,255,255,.12);display:flex;gap:16px;flex-wrap:wrap;}
.hf-pill{display:flex;align-items:center;gap:5px;font-size:11px;color:rgba(255,255,255,.7);}
.hf-pill i{font-size:10px;color:#90DFFF;}

/* ── RIGHT PANEL ── */
.form-side{background:var(--off);display:flex;align-items:center;justify-content:center;padding:20px 32px;overflow-y:auto;position:relative;}
.form-side::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,#C8D8F8 1px,transparent 1px);background-size:26px 26px;opacity:.45;pointer-events:none;}
.form-card{position:relative;z-index:1;background:#fff;border-radius:22px;padding:28px 32px;width:100%;max-width:520px;box-shadow:0 10px 50px rgba(10,40,120,.1);border:1px solid var(--border);}

/* Steps indicator */
.steps{display:flex;align-items:center;gap:0;margin-bottom:24px;}
.step-dot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;transition:.3s;flex-shrink:0;}
.step-dot.done{background:var(--blue);color:#fff;}
.step-dot.active{background:var(--blue);color:#fff;box-shadow:0 0 0 4px rgba(21,101,192,.2);}
.step-dot.todo{background:#E2E8F0;color:var(--muted);}
.step-line{flex:1;height:2px;background:#E2E8F0;transition:.3s;}
.step-line.done{background:var(--blue);}
.step-label{font-size:10px;font-weight:600;color:var(--muted);text-align:center;margin-top:4px;}

/* Form fields */
.fc-logo{text-align:center;margin-bottom:20px;}
.fc-logo-mark{width:46px;height:46px;border-radius:14px;background:linear-gradient(135deg,#2C5FBF,#5A4FCE);display:inline-flex;align-items:center;justify-content:center;font-size:20px;color:#fff;margin-bottom:8px;box-shadow:0 6px 20px rgba(21,101,192,.3);}
.fc-logo h3{font-size:20px;font-weight:800;color:var(--navy);margin:0;}
.fc-logo p{font-size:12px;color:var(--muted);margin:2px 0 0;}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;}
.fgroup{margin-bottom:12px;}
.flabel{font-size:12px;font-weight:700;color:var(--navy);margin-bottom:5px;display:flex;align-items:center;gap:4px;}
.flabel .req{color:#DC2626;}
.finput{width:100%;border:1.5px solid var(--border);border-radius:9px;padding:10px 13px;font-size:13.5px;color:var(--navy);background:var(--off);font-family:inherit;outline:none;transition:all .2s;}
.finput:focus{border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px rgba(21,101,192,.1);}
.finput::placeholder{color:#B8CFEC;}
.ficon-wrap{position:relative;}
.ficon-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;pointer-events:none;}
.ficon-wrap .finput{padding-left:36px;}
.submit-btn{width:100%;background:linear-gradient(135deg,#2C5FBF 0%,#5A4FCE 100%);color:#fff;border:none;border-radius:11px;padding:13px 20px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .25s;margin-top:16px;}
.submit-btn:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(21,101,192,.35);}
.fc-error{background:#FEE2E2;border:1px solid #FECACA;border-radius:9px;padding:10px 13px;margin-bottom:14px;font-size:12.5px;color:#991B1B;display:flex;align-items:center;gap:7px;}
.section-hd{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin:16px 0 10px;padding-bottom:6px;border-bottom:1px solid var(--border);}
.eye-btn{position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:14px;padding:3px;}
.pending-notice{background:linear-gradient(135deg,#FEF3C7,#FFF7ED);border:1px solid #F59E0B;border-radius:12px;padding:16px 18px;margin-bottom:16px;}
.pending-notice h4{font-size:14px;font-weight:800;color:#92400E;margin:0 0 6px;}
.pending-notice p{font-size:12.5px;color:#78350F;margin:0;line-height:1.6;}
.success-box{text-align:center;padding:20px 0;}
.success-icon{width:70px;height:70px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 16px;}
@media(max-width:860px){.page{grid-template-columns:1fr;}.hero{display:none;}.form-side{padding:20px 14px;}}
</style>
</head>
<body>
<div class="page">

  <!-- ══ LEFT HERO PANEL ══ -->
  <div class="hero">
    <div class="blob blob-1"></div><div class="blob blob-2"></div>
    <div class="hero-top">
      <div class="hero-logo-mark"><i class="fas fa-heartbeat"></i></div>
      <div class="hero-logo-text"><h2>HealthSphere</h2><p>NHS CONNECTED PLATFORM</p></div>
    </div>
    <div class="hero-center">
      <h1 class="hero-title">Join HealthSphere</h1>
      <p class="hero-sub">Choose your account type to get started with the NHS connected health platform</p>
      <div class="role-cards">

        <a href="?role=patient" class="role-card <?= $selectedRole==='patient'?'active':'' ?>">
          <div class="rc-icon" style="background:rgba(21,101,192,.3);">🧑‍💼</div>
          <div class="rc-text">
            <h4>Patient</h4>
            <p>Track health, book appointments, manage records</p>
          </div>
          <div class="rc-arrow"><i class="fas fa-chevron-right"></i></div>
        </a>

        <a href="?role=doctor" class="role-card <?= $selectedRole==='doctor'?'active':'' ?>">
          <div class="rc-icon" style="background:rgba(22,163,74,.3);">👨‍⚕️</div>
          <div class="rc-text">
            <h4>Doctor / Clinician</h4>
            <p>Manage patients, view records, issue prescriptions</p>
          </div>
          <span class="approval-badge">⏳ Admin Approval</span>
        </a>

        <a href="?role=government" class="role-card <?= $selectedRole==='government'?'active':'' ?>">
          <div class="rc-icon" style="background:rgba(124,58,237,.3);">🏛️</div>
          <div class="rc-text">
            <h4>Gov. Health Analyst</h4>
            <p>Access public health analytics and regional data</p>
          </div>
          <span class="approval-badge">⏳ Admin Approval</span>
        </a>

      </div>
    </div>
    <div class="hero-footer">
      <div class="hf-pill"><i class="fas fa-lock"></i> End-to-end encrypted</div>
      <div class="hf-pill"><i class="fas fa-shield-alt"></i> GDPR compliant</div>
      <div class="hf-pill"><i class="fas fa-hospital"></i> NHS certified</div>
    </div>
  </div>

  <!-- ══ RIGHT FORM PANEL ══ -->
  <div class="form-side">
   <div class="form-card">

    <?php if ($step === 3 && $success): ?>
    <!-- ── SUCCESS SCREEN ─────────────────────────── -->
    <?php
    $parts = explode('|', $success);
    $sType = $parts[0]; $sNhs = $parts[1]; $sName = $parts[2]; $sRole = $parts[3]??'';
    ?>
    <div class="success-box">
      <?php if ($sType === 'patient_ok'): ?>
        <div class="success-icon" style="background:#DCFCE7;"><i class="fas fa-check" style="color:#16A34A;"></i></div>
        <h3 style="font-size:20px;font-weight:800;color:var(--navy);margin-bottom:8px;">Welcome, <?= e($sName) ?>! 🎉</h3>
        <p style="font-size:13px;color:var(--muted);margin-bottom:20px;">Your account has been created successfully.</p>
        <div style="background:var(--off);border-radius:10px;padding:14px 18px;margin-bottom:20px;text-align:center;">
          <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:4px;">Your NHS ID</div>
          <div style="font-size:20px;font-weight:900;color:var(--navy);font-family:monospace;"><?= e($sNhs) ?></div>
          <div style="font-size:11px;color:var(--muted);margin-top:3px;">Keep this safe — you'll need it for NHS services</div>
        </div>
        <a href="index.php" style="display:flex;align-items:center;justify-content:center;gap:8px;background:linear-gradient(135deg,#2C5FBF,#5A4FCE);color:#fff;padding:13px 24px;border-radius:11px;font-weight:700;font-size:14px;text-decoration:none;transition:all .25s;">
          <i class="fas fa-sign-in-alt"></i> Sign In to HealthSphere
        </a>
      <?php else: ?>
        <div class="success-icon" style="background:#FEF3C7;"><i class="fas fa-hourglass-half" style="color:#D97706;"></i></div>
        <h3 style="font-size:20px;font-weight:800;color:var(--navy);margin-bottom:8px;">Application Submitted!</h3>
        <p style="font-size:13px;color:var(--muted);margin-bottom:20px;line-height:1.7;">
          Thank you, <strong><?= e($sName) ?></strong>. Your <?= $sRole==='doctor'?'clinician':'Government Analyst' ?> application has been received and is <strong>pending review</strong> by the HealthSphere administration team.
        </p>
        <div style="background:#FEF3C7;border:1px solid #F59E0B;border-radius:12px;padding:16px 18px;margin-bottom:20px;text-align:left;">
          <div style="font-weight:700;color:#92400E;margin-bottom:8px;"><i class="fas fa-clock"></i> What happens next?</div>
          <div style="font-size:12.5px;color:#78350F;line-height:1.8;">
            1. The admin team will review your credentials<br>
            2. You will receive an <strong>email notification</strong> once approved<br>
            3. Typical review time: <strong>24–48 hours</strong><br>
            4. You cannot sign in until your account is approved
          </div>
        </div>
        <div style="background:var(--off);border-radius:10px;padding:12px 16px;margin-bottom:20px;font-family:monospace;text-align:center;">
          <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;">Reference Number</div>
          <div style="font-size:18px;font-weight:900;color:var(--navy);"><?= e($sNhs) ?></div>
        </div>
        <a href="index.php" style="display:flex;align-items:center;justify-content:center;gap:8px;border:1.5px solid var(--border);color:var(--navy);padding:12px 24px;border-radius:11px;font-weight:700;font-size:14px;text-decoration:none;">
          <i class="fas fa-home"></i> Back to Home
        </a>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- ── REGISTRATION FORM ───────────────────────── -->

    <!-- Logo -->
    <div class="fc-logo">
      <div class="fc-logo-mark"><i class="fas fa-user-plus"></i></div>
      <h3>Create Account</h3>
      <p style="color:var(--muted);">
        <?php if ($selectedRole==='doctor'): ?>
          <span style="background:#DCFCE7;color:#166534;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;">👨‍⚕️ Doctor / Clinician</span>
        <?php elseif ($selectedRole==='government'): ?>
          <span style="background:#EDE9FE;color:#5B21B6;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;">🏛️ Government Analyst</span>
        <?php else: ?>
          <span style="background:#DBEAFE;color:#1E40AF;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;">🧑‍💼 Patient Account</span>
        <?php endif; ?>
      </p>
    </div>

    <?php if ($selectedRole !== 'patient'): ?>
    <div class="pending-notice">
      <h4>⏳ Admin Approval Required</h4>
      <p>
        <?= $selectedRole==='doctor' ? 'Doctor accounts require HCPC verification and admin review.' : 'Government Analyst accounts require staff ID verification and admin review.' ?>
        You will receive an email notification when your account is approved. Approval typically takes <strong>24–48 hours</strong>.
      </p>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="fc-error"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="role" value="<?= e($selectedRole) ?>">

      <!-- ── PERSONAL DETAILS (all roles) ── -->
      <div class="section-hd">Personal Information</div>
      <div class="grid-2">
        <div class="fgroup">
          <div class="flabel">First Name <span class="req">*</span></div>
          <input type="text" name="first_name" class="finput" placeholder="Emma" required value="<?= e($_POST['first_name']??'') ?>">
        </div>
        <div class="fgroup">
          <div class="flabel">Last Name <span class="req">*</span></div>
          <input type="text" name="last_name" class="finput" placeholder="Patel" required value="<?= e($_POST['last_name']??'') ?>">
        </div>
      </div>

      <div class="fgroup">
        <div class="flabel">Email Address <span class="req">*</span></div>
        <div class="ficon-wrap">
          <i class="fas fa-envelope"></i>
          <input type="email" name="email" class="finput" placeholder="<?= $selectedRole==='doctor'?'doctor@hospital.nhs.uk':($selectedRole==='government'?'analyst@dhsc.gov.uk':'your@email.com') ?>" required value="<?= e($_POST['email']??'') ?>">
        </div>
      </div>

      <div class="grid-2">
        <div class="fgroup">
          <div class="flabel">Phone Number</div>
          <input type="tel" name="phone" class="finput" placeholder="07700..." value="<?= e($_POST['phone']??'') ?>">
        </div>
        <?php if ($selectedRole === 'patient'): ?>
        <div class="fgroup">
          <div class="flabel">Blood Type</div>
          <select name="blood_type" class="finput">
            <option value="">Select</option>
            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
            <option value="<?= $bt ?>" <?= ($_POST['blood_type']??'')===$bt?'selected':'' ?>><?= $bt ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php else: ?>
        <div class="fgroup">
          <div class="flabel">Date of Birth</div>
          <input type="date" name="dob" class="finput" value="<?= e($_POST['dob']??'') ?>">
        </div>
        <?php endif; ?>
      </div>

      <?php if ($selectedRole === 'patient'): ?>
      <div class="grid-2">
        <div class="fgroup">
          <div class="flabel">Date of Birth</div>
          <input type="date" name="dob" class="finput" value="<?= e($_POST['dob']??'') ?>">
        </div>
        <div class="fgroup">
          <div class="flabel">Gender</div>
          <select name="gender" class="finput">
            <option value="">Select</option>
            <option value="male"   <?= ($_POST['gender']??'')==='male'?'selected':'' ?>>Male</option>
            <option value="female" <?= ($_POST['gender']??'')==='female'?'selected':'' ?>>Female</option>
            <option value="other">Other / Prefer not to say</option>
          </select>
        </div>
      </div>
      <?php endif; ?>

      <!-- ── DOCTOR FIELDS ── -->
      <?php if ($selectedRole === 'doctor'): ?>
      <div class="section-hd">Professional Credentials</div>
      <div class="grid-2">
        <div class="fgroup">
          <div class="flabel">HCPC / GMC Number <span class="req">*</span></div>
          <input type="text" name="hcpc_number" class="finput" placeholder="HCPC12345678" required value="<?= e($_POST['hcpc_number']??'') ?>">
        </div>
        <div class="fgroup">
          <div class="flabel">Specialization <span class="req">*</span></div>
          <select name="specialization" class="finput" required>
            <option value="">Select specialization...</option>
            <?php foreach ($specializations as $s): ?>
            <option value="<?= $s ?>" <?= ($_POST['specialization']??'')===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="fgroup">
        <div class="flabel">Hospital / Clinic Name <span class="req">*</span></div>
        <input type="text" name="hospital_name" class="finput" placeholder="Leicester Royal Infirmary" required value="<?= e($_POST['hospital_name']??'') ?>">
      </div>
      <div class="fgroup">
        <div class="flabel">Hospital Address</div>
        <input type="text" name="hospital_address" class="finput" placeholder="Infirmary Square, Leicester LE1 5WW" value="<?= e($_POST['hospital_address']??'') ?>">
      </div>
      <div class="grid-2">
        <div class="fgroup">
          <div class="flabel">Years of Experience</div>
          <input type="number" name="experience_years" class="finput" placeholder="10" min="0" max="60" value="<?= e($_POST['experience_years']??'') ?>">
        </div>
        <div class="fgroup">
          <div class="flabel">Consultation Fee (£)</div>
          <input type="number" name="consultation_fee" class="finput" placeholder="0.00" step="0.01" min="0" value="<?= e($_POST['consultation_fee']??'') ?>">
        </div>
      </div>
      <div class="fgroup">
        <div class="flabel">Professional Bio <span style="font-weight:400;color:var(--muted);">(optional)</span></div>
        <textarea name="bio" class="finput" rows="3" placeholder="Brief description of your experience and specialties..." style="resize:vertical;"><?= e($_POST['bio']??'') ?></textarea>
      </div>
      <?php endif; ?>

      <!-- ── GOVERNMENT ANALYST FIELDS ── -->
      <?php if ($selectedRole === 'government'): ?>
      <div class="section-hd">Government / Analyst Details</div>
      <div class="fgroup">
        <div class="flabel">Department / Organisation <span class="req">*</span></div>
        <select name="gov_department" class="finput" required>
          <option value="">Select department...</option>
          <?php foreach ($departments as $dept): ?>
          <option value="<?= e($dept) ?>" <?= ($_POST['gov_department']??'')===$dept?'selected':'' ?>><?= $dept ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid-2">
        <div class="fgroup">
          <div class="flabel">Staff / Government ID <span class="req">*</span></div>
          <input type="text" name="gov_staff_id" class="finput" placeholder="GOV-12345" required value="<?= e($_POST['gov_staff_id']??'') ?>">
        </div>
        <div class="fgroup">
          <div class="flabel">Job Title <span class="req">*</span></div>
          <input type="text" name="gov_job_title" class="finput" placeholder="Senior Health Analyst" required value="<?= e($_POST['gov_job_title']??'') ?>">
        </div>
      </div>
      <div class="fgroup">
        <div class="flabel">Region Responsibility</div>
        <select name="gov_region" class="finput">
          <option value="">Select region...</option>
          <?php foreach (['National (England & Wales)','East Midlands','North West','North East','Yorkshire','South East','South West','West Midlands','London','East of England','Wales','Scotland','Northern Ireland'] as $r): ?>
          <option value="<?= $r ?>" <?= ($_POST['gov_region']??'')===$r?'selected':'' ?>><?= $r ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fgroup">
        <div class="flabel">Reason for Access <span class="req">*</span></div>
        <textarea name="gov_reason" class="finput" rows="3" required placeholder="Please describe your role and why you need access to HealthSphere data..." style="resize:vertical;"><?= e($_POST['gov_reason']??'') ?></textarea>
      </div>
      <?php endif; ?>

      <!-- ── PASSWORD ── -->
      <div class="section-hd">Security</div>
      <div class="grid-2">
        <div class="fgroup">
          <div class="flabel">Password <span class="req">*</span></div>
          <div class="ficon-wrap" style="position:relative;">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" id="pw1" class="finput" placeholder="Min. 8 characters" required style="padding-right:38px;">
            <button type="button" class="eye-btn" onclick="togglePw('pw1','eyePw1')"><i class="fas fa-eye" id="eyePw1"></i></button>
          </div>
        </div>
        <div class="fgroup">
          <div class="flabel">Confirm Password <span class="req">*</span></div>
          <div class="ficon-wrap" style="position:relative;">
            <i class="fas fa-lock"></i>
            <input type="password" name="confirm" id="pw2" class="finput" placeholder="Repeat password" required style="padding-right:38px;">
            <button type="button" class="eye-btn" onclick="togglePw('pw2','eyePw2')"><i class="fas fa-eye" id="eyePw2"></i></button>
          </div>
        </div>
      </div>

      <!-- Password strength -->
      <div style="margin-bottom:14px;">
        <div style="height:4px;background:#E2E8F0;border-radius:4px;overflow:hidden;">
          <div id="pwStrengthBar" style="height:100%;width:0%;border-radius:4px;transition:all .3s;"></div>
        </div>
        <div id="pwStrengthLabel" style="font-size:11px;color:var(--muted);margin-top:3px;"></div>
      </div>

      <!-- Terms -->
      <div style="display:flex;align-items:flex-start;gap:9px;margin-bottom:4px;">
        <input type="checkbox" id="terms" required style="width:15px;height:15px;accent-color:var(--blue);margin-top:1px;flex-shrink:0;">
        <label for="terms" style="font-size:12px;color:var(--muted);cursor:pointer;line-height:1.6;">
          I agree to the <a href="#" style="color:var(--blue);">Terms of Service</a> and <a href="#" style="color:var(--blue);">Privacy Policy</a>. I understand that my data will be processed in accordance with NHS data protection standards.
        </label>
      </div>

      <!-- reCAPTCHA disabled temporarily -->
      <!-- <div style="display:flex;justify-content:center;margin:16px 0 4px;">
        <div class="g-recaptcha"
             data-sitekey="<?= RECAPTCHA_SITE_KEY ?>"
             data-theme="light"
             data-callback="onRegCaptcha"
             data-expired-callback="onRegExpired">
        </div>
      </div> -->

      <button type="submit" class="submit-btn" id="regSubmitBtn" disabled
        style="opacity:.5;cursor:not-allowed;">
        <i class="fas fa-<?= $selectedRole==='patient'?'user-plus':($selectedRole==='doctor'?'user-md':'landmark') ?>"></i>
        <?= $selectedRole==='patient' ? 'Create Patient Account' : ($selectedRole==='doctor' ? 'Submit Doctor Application' : 'Submit Analyst Application') ?>
      </button>
    </form>

    <div style="text-align:center;margin-top:16px;font-size:12.5px;color:var(--muted);">
      Already have an account? <a href="index.php" style="color:var(--blue);font-weight:700;">Sign In</a>
    </div>

    <?php endif; ?>
   </div>
  </div>

</div>

<script>
function togglePw(id, iconId) {
  const p = document.getElementById(id);
  const i = document.getElementById(iconId);
  p.type = p.type === 'password' ? 'text' : 'password';
  i.classList.toggle('fa-eye-slash'); i.classList.toggle('fa-eye');
}

// Password strength
document.getElementById('pw1')?.addEventListener('input', function() {
  const v = this.value;
  let score = 0;
  if (v.length >= 8)  score++;
  if (v.length >= 12) score++;
  if (/[A-Z]/.test(v)) score++;
  if (/[0-9]/.test(v)) score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;
  const bar = document.getElementById('pwStrengthBar');
  const lbl = document.getElementById('pwStrengthLabel');
  const levels = [
    ['0%',  '#E2E8F0', ''],
    ['25%', '#DC2626', 'Weak — too short'],
    ['50%', '#D97706', 'Fair — add uppercase & numbers'],
    ['75%', '#1565C0', 'Good — add special characters'],
    ['90%', '#16A34A', 'Strong password ✓'],
    ['100%','#16A34A', 'Very strong ✓'],
  ];
  const l = levels[score] || levels[0];
  bar.style.width = l[0]; bar.style.background = l[1];
  lbl.textContent = l[2]; lbl.style.color = l[1];
});

// Highlight active role on left panel when form role changes
const roleParam = '<?= e($selectedRole) ?>';
document.querySelectorAll('.role-card').forEach(c => {
  const href = c.getAttribute('href');
  if (href && href.includes(roleParam)) c.classList.add('active');
});
</script>

<!-- reCAPTCHA callbacks -->
<script>
function onRegCaptcha(token) {
  const btn = document.getElementById('regSubmitBtn');
  if (btn) { btn.disabled=false; btn.style.opacity='1'; btn.style.cursor='pointer'; }
}
function onRegExpired() {
  const btn = document.getElementById('regSubmitBtn');
  if (btn) { btn.disabled=true; btn.style.opacity='.5'; btn.style.cursor='not-allowed'; }
}
</script>
<!-- <script src="https://www.google.com/recaptcha/api.js" async defer></script> -->
</body>
</html>
