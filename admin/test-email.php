<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';
requireRole('admin');
$user = getCurrentUser();
$uid  = $user['id'];

$result = '';
$sent   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? 'test';
    $to   = trim($_POST['to'] ?? MAIL_ADMIN);

    switch ($type) {
        case 'test':
            $html = hsMailTemplate(
                'Test Email ✅',
                p("This is a <strong>test email</strong> from HealthSphere.")
                . success("✅ If you received this, your email configuration is working correctly!")
                . dataTable(['SMTP Host'=>MAIL_HOST.':'.MAIL_PORT,'From'=>MAIL_FROM,'Sent at'=>date('d M Y H:i:s')])
            );
            $sent = hsSendEmail($to, 'Admin', 'HealthSphere — Email Test', $html);
            break;
        case 'welcome':
            $sent = mailPatientWelcome($to, 'Test Patient', 'PT123456TEST');
            break;
        case 'approval_request':
            $sent = mailAdminNewApplication('Dr. Test User', $to, 'doctor', 'DR123456', ['HCPC'=>'HCPC12345678','Specialization'=>'Cardiology','Hospital'=>'Test Hospital']);
            break;
        case 'approved':
            $sent = mailAccountApproved($to, 'Dr. Test User', 'doctor');
            break;
        case 'rejected':
            $sent = mailAccountRejected($to, 'Test User', 'doctor', 'HCPC number could not be verified.');
            break;
        case 'appointment':
            $sent = mailAppointmentPatient($to, 'Test Patient', 'Emma Hall', 'Monday, 15 May 2026', '09:30', 'BP Review', 'Leicester Royal Infirmary');
            break;
        case 'emergency':
            $sent = mailEmergencyAlert($to, 'Test Doctor', 'Test Patient', 'PT123456', 'I am experiencing severe chest pain and shortness of breath.');
            break;
        case 'prescription':
            $sent = mailPrescriptionIssued($to, 'Test Patient', 'Emma Hall', 'Amlodipine', '5mg', 'Once daily (Morning)', 'Take with water for blood pressure control');
            break;
    }
    $result = $sent ? 'success' : 'error';
}

$notifCount = getUnreadCount($pdo, $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Email Testing — HealthSphere Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-envelope" style="color:var(--hs-blue);"></i> Email System Test</div>
      <div class="page-subtitle">Test all email automations &middot; Sending from <?= e(MAIL_FROM) ?></div>
    </div>
  </div>
  <div class="hs-content">

    <?php if ($result === 'success'): ?>
    <div style="background:#DCFCE7;border:1px solid #BBF7D0;border-radius:10px;padding:14px 18px;margin-bottom:16px;color:#166534;font-size:14px;font-weight:600;display:flex;align-items:center;gap:10px;">
      <i class="fas fa-check-circle fa-lg"></i>
      <div>Email sent successfully! Check the inbox at <strong><?= e($_POST['to']??MAIL_ADMIN) ?></strong></div>
    </div>
    <?php elseif ($result === 'error'): ?>
    <div style="background:#FEE2E2;border:1px solid #FECACA;border-radius:10px;padding:14px 18px;margin-bottom:16px;color:#991B1B;font-size:14px;font-weight:600;display:flex;align-items:center;gap:10px;">
      <i class="fas fa-exclamation-circle fa-lg"></i>
      <div>Email sending failed. Check SMTP credentials in <code>config/mail.php</code> and ensure SSL extension is enabled in PHP.</div>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px;">

      <!-- Config status -->
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-cog"></i> Email Configuration</span></div>
        <div class="hs-card-body">
          <?php
          $rows = ['SMTP Host'=>MAIL_HOST,'Port'=>MAIL_PORT,'From Address'=>MAIL_FROM,'Admin Email'=>MAIL_ADMIN];
          foreach ($rows as $l=>$v): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--hs-border);font-size:13px;">
            <span style="color:var(--hs-muted);font-weight:600;"><?= $l ?></span>
            <span style="font-weight:700;color:var(--hs-navy);font-family:monospace;"><?= e((string)$v) ?></span>
          </div>
          <?php endforeach; ?>
          <div style="margin-top:12px;padding:10px 14px;background:var(--hs-off-white);border-radius:8px;font-size:12px;color:var(--hs-muted);">
            <i class="fas fa-info-circle" style="color:var(--hs-blue);"></i>
            SSL on port 465. Ensure <code>extension=openssl</code> is enabled in <code>php.ini</code>.
          </div>
        </div>
      </div>

      <!-- Test sender -->
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-paper-plane"></i> Send Test Email</span></div>
        <div class="hs-card-body">
          <form method="POST">
            <div style="margin-bottom:14px;">
              <label class="form-label">Send To</label>
              <input type="email" name="to" class="form-control" value="<?= e(MAIL_ADMIN) ?>" required>
            </div>
            <div style="margin-bottom:16px;">
              <label class="form-label">Email Type</label>
              <select name="type" class="form-select">
                <option value="test">✅ Basic Test Email</option>
                <option value="welcome">🎉 Patient Welcome</option>
                <option value="approval_request">⏳ Admin — New Application Pending</option>
                <option value="approved">✅ Account Approved</option>
                <option value="rejected">❌ Account Rejected</option>
                <option value="appointment">📅 Appointment Confirmed</option>
                <option value="emergency">🚨 Emergency Alert</option>
                <option value="prescription">💊 Prescription Issued</option>
              </select>
            </div>
            <button type="submit" class="btn-hs btn-primary-hs" style="width:100%;justify-content:center;">
              <i class="fas fa-paper-plane"></i> Send Test Email
            </button>
          </form>
        </div>
      </div>

    </div>

    <!-- Email trigger map -->
    <div class="hs-card" style="margin-top:20px;max-width:900px;">
      <div class="hs-card-header"><span class="card-title"><i class="fas fa-sitemap"></i> Automated Email Triggers</span></div>
      <div class="hs-card-body p-0">
        <table class="hs-table">
          <thead><tr><th>Trigger Event</th><th>Recipient</th><th>Template</th><th>Status</th></tr></thead>
          <tbody>
            <?php
            $triggers = [
              ['Patient registers','Patient','Welcome email with NHS ID','✅ Active'],
              ['Doctor/Gov registers','Applicant','Application received — pending review','✅ Active'],
              ['Doctor/Gov registers','Admin','New application pending approval (with details)','✅ Active'],
              ['Admin approves account','Doctor / Gov Analyst','Account approved — login now','✅ Active'],
              ['Admin rejects account','Doctor / Gov Analyst','Rejection with reason','✅ Active'],
              ['Appointment booked','Patient','Appointment confirmation with details','✅ Active'],
              ['Appointment booked','Doctor','New patient appointment notification','✅ Active'],
              ['Emergency chat message','Doctor','🚨 Urgent alert email with message preview','✅ Active'],
              ['Appointment cancelled','Patient','Cancellation confirmation','✅ Active'],
              ['Prescription issued','Patient','New prescription details','Available'],
              ['Critical lab result','Patient','Urgent lab result alert','Available'],
            ];
            foreach ($triggers as [$event,$recipient,$template,$status]):
              $cls = str_starts_with($status,'✅') ? 'bg-success' : 'bg-secondary';
            ?>
            <tr>
              <td style="font-weight:600;"><?= $event ?></td>
              <td><?= $recipient ?></td>
              <td style="font-size:12px;color:var(--hs-muted);"><?= $template ?></td>
              <td><span class="badge <?= $cls ?>" style="font-size:11px;"><?= $status ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
