<?php
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    redirectByRole($_SESSION['user_role']);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email && $password) {
        // reCAPTCHA check first
        if (!verifyCaptcha()) {
            $error = 'Please complete the reCAPTCHA verification.';
        } else
        // Check by email regardless of is_active first
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        // Check pending/rejected BEFORE checking password
        if ($user && isset($user['approval_status'])) {
            if ($user['approval_status'] === 'pending') {
                $error = '⏳ Your account is pending admin approval. You will be notified by email once approved.';
                $user = null;
            } elseif ($user['approval_status'] === 'rejected') {
                $error = '❌ Your application was not approved. Reason: '.($user['rejection_reason']??'Please contact admin@healthsphere.info');
                $user = null;
            } elseif (!$user['is_active']) {
                $error = 'Your account has been suspended. Please contact admin@healthsphere.info';
                $user = null;
            }
        }
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user_first']  = $user['first_name'];
            $_SESSION['user_last']   = $user['last_name'];
            $_SESSION['user_email']  = $user['email'];
            $_SESSION['user_role']   = $user['role'];
            $_SESSION['user_nhs_id'] = $user['nhs_id'];
            $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
            logAccess($pdo, $user['id'], null, 'LOGIN');
            redirectByRole($user['role']);
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    } else {
        $error = 'Please enter your email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HealthSphere — Sign In</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; font-family: 'Inter', sans-serif; }

/* ── PAGE — full-screen background ──────────────── */
.page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px 16px;
  background: #0f1923;
  position: relative;
  overflow: hidden;
}

/* Subtle background pattern */
.page::before {
  content: '';
  position: fixed; inset: 0;
  background:
    radial-gradient(ellipse 80% 60% at 20% 30%, rgba(66,133,232,.18) 0%, transparent 60%),
    radial-gradient(ellipse 60% 50% at 80% 70%, rgba(90,79,206,.20) 0%, transparent 60%),
    radial-gradient(ellipse 40% 40% at 60% 10%, rgba(94,168,240,.10) 0%, transparent 50%);
  pointer-events: none;
}

/* Floating dot decorations */
.bg-dot {
  position: fixed; border-radius: 50%;
  background: rgba(255,255,255,.035);
  pointer-events: none;
}
.bg-dot-1 { width:400px;height:400px; top:-80px; left:-100px; }
.bg-dot-2 { width:300px;height:300px; bottom:-60px; right:-60px; background:rgba(90,79,206,.08); }
.bg-dot-3 { width:180px;height:180px; top:40%; right:8%; background:rgba(66,133,232,.07); }

/* ══════════════════════════════════════════════════
   MAIN CARD — floating, split left/right
══════════════════════════════════════════════════ */
.card-shell {
  position: relative; z-index: 10;
  display: flex;
  width: 100%; max-width: 960px;
  min-height: 600px;
  border-radius: 24px;
  overflow: hidden;
  box-shadow:
    0 32px 100px rgba(0,0,0,.55),
    0 4px 24px rgba(0,0,0,.30);
}

/* ── LEFT PANEL ── */
.panel-left {
  width: 42%;
  background: linear-gradient(160deg, #0D2137 0%, #112240 40%, #1A1060 100%);
  display: flex;
  flex-direction: column;
  padding: 36px 32px;
  position: relative;
  overflow: hidden;
}

/* Soft glow inside left panel */
.panel-left::before {
  content: '';
  position: absolute; inset: 0;
  background:
    radial-gradient(ellipse 80% 60% at 50% 30%, rgba(66,133,232,.18) 0%, transparent 70%),
    radial-gradient(ellipse 60% 40% at 80% 90%, rgba(90,79,206,.22) 0%, transparent 60%);
  pointer-events: none;
}

/* Back link */
.back-link {
  position: relative; z-index: 2;
  color: rgba(255,255,255,.5);
  font-size: 12px; font-weight: 500;
  text-decoration: none;
  display: inline-flex; align-items: center; gap: 6px;
  transition: color .2s;
  margin-bottom: 32px;
}
.back-link:hover { color: rgba(255,255,255,.85); }

/* Logo row */
.pl-logo {
  position: relative; z-index: 2;
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 36px;
}
.pl-logo-mark {
  width: 40px; height: 40px;
  border-radius: 12px;
  background: linear-gradient(135deg, #2C5FBF, #5A4FCE);
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; color: #fff;
  box-shadow: 0 4px 16px rgba(66,133,244,.4);
}
.pl-logo h2 { font-size: 17px; font-weight: 800; color: #fff; margin: 0; }
.pl-logo p  { font-size: 9px; color: rgba(255,255,255,.45); margin: 2px 0 0; letter-spacing: 1px; text-transform: uppercase; }

/* Doctor image */
.pl-img-wrap {
  position: relative; z-index: 2;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.pl-circle {
  width: 210px; height: 210px;
  border-radius: 50%;
  overflow: hidden;
  border: 4px solid rgba(255,255,255,.15);
  box-shadow:
    0 0 0 10px rgba(255,255,255,.05),
    0 20px 60px rgba(0,0,0,.4);
  background: rgba(255,255,255,.08);
  margin-bottom: 24px;
}
.pl-circle img { width:100%; height:100%; object-fit:cover; object-position:top center; display:block; }

.pl-tagline { position: relative; z-index: 2; text-align: center; }
.pl-tagline h3 { font-size: 20px; font-weight: 800; color: #fff; margin-bottom: 6px; line-height: 1.3; }
.pl-tagline p  { font-size: 12.5px; color: rgba(255,255,255,.55); line-height: 1.5; }

/* Bottom pills */
.pl-pills {
  position: relative; z-index: 2;
  display: flex; flex-wrap: wrap; gap: 6px;
  margin-top: 28px;
}
.pl-pill {
  background: rgba(255,255,255,.07);
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 20px; padding: 5px 11px;
  font-size: 10.5px; font-weight: 600;
  color: rgba(255,255,255,.7);
  display: flex; align-items: center; gap: 5px;
}
.pl-pill i { font-size: 9px; color: #90DFFF; }

/* ── RIGHT PANEL ── */
.panel-right {
  flex: 1;
  background: #fff;
  padding: 36px 40px;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
}

.pr-head { margin-bottom: 24px; }
.pr-head h3 { font-size: 22px; font-weight: 800; color: #0A1F44; margin-bottom: 4px; }
.pr-head p  { font-size: 13px; color: #5E7A99; }
.pr-head p a { color: #4285E8; font-weight: 600; text-decoration: none; }
.pr-head p a:hover { text-decoration: underline; }

/* NHS button */
.nhs-btn {
  width: 100%;
  background: #003087;
  color: #fff;
  border: none; border-radius: 10px;
  padding: 12px 20px; margin-bottom: 18px;
  font-size: 14px; font-weight: 700; font-family: inherit;
  display: flex; align-items: center; justify-content: center; gap: 10px;
  cursor: pointer; transition: all .25s;
}
.nhs-btn:hover { background: #001E5A; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,48,135,.3); }
.nhs-logo-inline {
  background: #fff; color: #003087;
  font-weight: 900; font-size: 12px;
  padding: 2px 7px; border-radius: 4px; letter-spacing: .5px;
}

/* Divider */
.fc-divider {
  display: flex; align-items: center; gap: 10px;
  font-size: 11.5px; font-weight: 500; color: #9BAEC8;
  margin-bottom: 16px;
}
.fc-divider::before, .fc-divider::after { content:''; flex:1; height:1px; background:#E8F0FF; }

/* Error */
.fc-error {
  background: #FEE2E2; border: 1px solid #FECACA; border-radius: 10px;
  padding: 10px 13px; margin-bottom: 14px;
  font-size: 12.5px; color: #991B1B;
  display: flex; align-items: center; gap: 7px;
}

/* Fields */
.fgroup { margin-bottom: 14px; }
.flabel {
  display: flex; justify-content: space-between; align-items: center;
  font-size: 12.5px; font-weight: 700; color: #0A1F44;
  margin-bottom: 6px;
}
.flabel a { font-size: 12px; color: #4285E8; font-weight: 500; text-decoration: none; }
.flabel a:hover { text-decoration: underline; }
.fwrap { position: relative; }
.fwrap .ficon {
  position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
  color: #9BAEC8; font-size: 13px; pointer-events: none;
}
.finput {
  width: 100%;
  border: 1.5px solid #E0ECFF;
  border-radius: 10px;
  padding: 11px 14px 11px 38px;
  font-size: 13.5px; color: #0A1F44;
  background: #F6F9FF;
  font-family: inherit; outline: none;
  transition: all .2s;
}
.finput:focus { border-color: #4285E8; background: #fff; box-shadow: 0 0 0 3px rgba(66,133,244,.12); }
.finput::placeholder { color: #B8CFEC; }
.eye-btn {
  position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer;
  color: #9BAEC8; font-size: 14px; padding: 4px;
}
.eye-btn:hover { color: #4285E8; }

/* Remember */
.rem-row {
  display: flex; align-items: center; gap: 8px;
  margin-bottom: 16px; font-size: 12.5px; color: #5E7A99;
}
.rem-row input { width:16px; height:16px; accent-color:#4285E8; cursor:pointer; }
.rem-row label { cursor: pointer; user-select: none; }

/* Submit */
.sub-btn {
  width: 100%;
  background: linear-gradient(135deg, #2C5FBF 0%, #5A4FCE 100%);
  color: #fff; border: none; border-radius: 10px;
  padding: 13px 20px;
  font-size: 14px; font-weight: 700; font-family: inherit;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: all .25s;
}
.sub-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(66,100,200,.35); }
.sub-btn:active { transform: translateY(0); }

/* Register */
.reg-row { text-align:center; margin-top:12px; font-size:13px; color:#5E7A99; }
.reg-row a { color:#4285E8; font-weight:700; text-decoration:none; }

/* Demo accounts */
.demo-sec { margin-top:16px; border-top:1px solid #EEF4FF; padding-top:14px; }
.demo-hd  { text-align:center; font-size:10px; font-weight:700; letter-spacing:1.5px;
            text-transform:uppercase; color:#9BAEC8; margin-bottom:10px; }
.demo-grid { display:grid; grid-template-columns:1fr 1fr; gap:7px; }
.demo-item {
  display:flex; align-items:center; gap:9px;
  padding:8px 10px; border-radius:10px;
  border:1.5px solid #E8F0FF; background:#F6F9FF;
  cursor:pointer; font-family:inherit;
  transition:all .2s; text-align:left;
}
.demo-item:hover { border-color:#4285E8; background:#EEF5FF; transform:translateY(-2px); box-shadow:0 4px 12px rgba(66,133,244,.12); }
.di-av { width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0; }
.di-info { flex:1;min-width:0; }
.di-name  { font-weight:700;font-size:11.5px;color:#0A1F44;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.di-role  { font-size:9.5px;color:#9BAEC8;margin-top:1px; }
.rbadge { padding:2px 7px;border-radius:20px;font-size:9px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;flex-shrink:0; }
.rb-p{background:#DBEAFE;color:#1E40AF;} .rb-d{background:#DCFCE7;color:#166534;}
.rb-a{background:#FEF3C7;color:#92400E;} .rb-g{background:#EDE9FE;color:#5B21B6;}

/* Security */
.sec-foot { text-align:center;margin-top:14px;font-size:11px;color:#9BAEC8;display:flex;align-items:center;justify-content:center;gap:5px; }

/* Responsive */
@media(max-width:760px) {
  .panel-left { display:none; }
  .card-shell { max-width:440px; }
  .panel-right { padding:28px 24px; }
  .demo-grid { grid-template-columns:1fr; }
}
</style>
</head>
<body>
<div class="page">

  <!-- Background decorations -->
  <div class="bg-dot bg-dot-1"></div>
  <div class="bg-dot bg-dot-2"></div>
  <div class="bg-dot bg-dot-3"></div>

  <!-- ══════════════════════════════════════
       FLOATING CARD
  ══════════════════════════════════════ -->
  <div class="card-shell">

    <!-- ── LEFT PANEL ── -->
    <div class="panel-left">
      <a href="#" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>

      <div class="pl-logo">
        <div class="pl-logo-mark"><i class="fas fa-heartbeat"></i></div>
        <div>
          <h2>HealthSphere</h2>
          <p>NHS Connected Platform</p>
        </div>
      </div>

      <div class="pl-img-wrap">
        <div class="pl-circle">
          <img
            src="https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?w=500&h=600&q=85&auto=format&fit=crop&crop=top"
            alt="Healthcare Professional"
            onerror="this.style.display='none'">
        </div>
        <div class="pl-tagline">
          <h3>Your Health,<br>Connected</h3>
          <p>Secure NHS portal for patients,<br>doctors &amp; government analysts</p>
        </div>
      </div>

      <div class="pl-pills">
        <div class="pl-pill"><i class="fas fa-lock"></i> Encrypted</div>
        <div class="pl-pill"><i class="fas fa-shield-alt"></i> GDPR</div>
        <div class="pl-pill"><i class="fas fa-hospital"></i> NHS Certified</div>
        <div class="pl-pill"><i class="fas fa-users"></i> 66M+ Records</div>
      </div>
    </div>

    <!-- ── RIGHT PANEL ── -->
    <div class="panel-right">
      <div class="pr-head">
        <h3>Sign In to HealthSphere</h3>
        <p>Already registered? <a href="#">Use your NHS credentials</a></p>
      </div>

      <!-- NHS button -->
      <button class="nhs-btn" type="button" id="nhsSignInBtn">
        <span class="nhs-logo-inline">NHS</span>
        Secure sign in via NHS Login
      </button>

      <div class="fc-divider">or continue with email</div>

      <?php if ($error): ?>
      <div class="fc-error">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php endif; ?>

      <form method="POST">
        <div class="fgroup">
          <div class="flabel">Email Address</div>
          <div class="fwrap">
            <i class="fas fa-envelope ficon"></i>
            <input type="email" name="email" class="finput"
              placeholder="your@nhs.uk"
              value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              autocomplete="email" required>
          </div>
        </div>

        <div class="fgroup">
          <div class="flabel">
            Password
            <a href="#">Forgot password?</a>
          </div>
          <div class="fwrap">
            <i class="fas fa-lock ficon"></i>
            <input type="password" name="password" id="passInput" class="finput"
              placeholder="Enter your password"
              autocomplete="current-password" required style="padding-right:40px;">
            <button type="button" class="eye-btn" id="eyeBtn">
              <i class="fas fa-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <div class="rem-row">
          <input type="checkbox" id="remDev" name="remember">
          <label for="remDev">Remember this device</label>
        </div>

        <!-- reCAPTCHA -->
        <div style="display:flex;justify-content:center;margin-bottom:16px;">
          <div class="g-recaptcha"
               data-sitekey="<?= RECAPTCHA_SITE_KEY ?>"
               data-theme="light"
               data-size="normal"
               data-callback="onCaptchaSuccess"
               data-expired-callback="onCaptchaExpired">
          </div>
        </div>

        <button type="submit" class="sub-btn" id="loginSubmitBtn" disabled
          style="opacity:.5;cursor:not-allowed;">
          <i class="fas fa-arrow-right"></i> Sign In
        </button>
      </form>

      <div class="reg-row">
        Don't have an account? <a href="register.php">Create Account</a>
      </div>

      <!-- Demo accounts — 2-column grid -->
      <div class="demo-sec">
        <div class="demo-hd">Demo Accounts — click to fill</div>
        <div class="demo-grid">
          <?php
          $demos = [
            ['Emma Patel',     'emma.patel@email.com',               'Patient',      'rb-p','🧑','#DBEAFE'],
            ['Dr. Jessica Johns',  'jessica.johns@leicesterhospital.nhs.uk', 'Doctor',       'rb-d','👩‍⚕️','#DCFCE7'],
            ['System Admin',   'admin@healthsphere.info',          'Admin',        'rb-a','🛡️','#FEF3C7'],
            ['W. Jayson',      'w.jayson@dhsc.gov.uk',               'Gov. Analyst', 'rb-g','🏛️','#EDE9FE'],
          ];
          foreach ($demos as [$name, $email, $role, $cls, $icon, $bg]):
          ?>
          <button class="demo-item" onclick="fillLogin('<?= $email ?>')">
            <div class="di-av" style="background:<?= $bg ?>;"><?= $icon ?></div>
            <div class="di-info">
              <div class="di-name"><?= htmlspecialchars($name) ?></div>
              <div class="di-role"><?= $role ?></div>
            </div>
          </button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="sec-foot">
        <i class="fas fa-lock"></i>
        End-to-end encrypted &middot; GDPR compliant &middot; NHS certified
      </div>
    </div>
  </div>

</div>

<script>
// Eye toggle
document.getElementById('eyeBtn').addEventListener('click', function() {
  const p = document.getElementById('passInput');
  const i = document.getElementById('eyeIcon');
  p.type = p.type === 'password' ? 'text' : 'password';
  i.classList.toggle('fa-eye-slash');
  i.classList.toggle('fa-eye');
});

// Fill demo
function fillLogin(email) {
  document.querySelector('[name=email]').value    = email;
  document.querySelector('[name=password]').value = 'password';
  const btn = document.querySelector('.sub-btn');
  btn.style.background = 'linear-gradient(135deg,#166534,#16A34A)';
  setTimeout(() => { btn.style.background = ''; }, 900);
}

// NHS button quick login — bypasses captcha for demo accounts
document.getElementById('nhsSignInBtn').addEventListener('click', () => {
  fillLogin('emma.patel@email.com');
  // Enable button so demo fill can submit
  const btn = document.getElementById('loginSubmitBtn');
  btn.disabled = false; btn.style.opacity = '1'; btn.style.cursor = 'pointer';
  setTimeout(() => document.querySelector('form').submit(), 300);
});

// Demo fill also enables submit button
const origFill = window.fillLogin;
window.fillLogin = function(email) {
  origFill(email);
  const btn = document.getElementById('loginSubmitBtn');
  if (btn) { btn.disabled=false; btn.style.opacity='1'; btn.style.cursor='pointer'; }
};
</script>

<!-- reCAPTCHA — enable submit button after user solves it -->
<script>
function onCaptchaSuccess(token) {
  const btn = document.getElementById('loginSubmitBtn');
  if (btn) { btn.disabled=false; btn.style.opacity='1'; btn.style.cursor='pointer'; }
}
function onCaptchaExpired() {
  const btn = document.getElementById('loginSubmitBtn');
  if (btn) { btn.disabled=true; btn.style.opacity='.5'; btn.style.cursor='not-allowed'; }
}
</script>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
</body>
</html>
