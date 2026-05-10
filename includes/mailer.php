<?php
@include_once __DIR__ . '/../config/mail.php';

// Fallback constants if mail.php is missing or incomplete
if (!defined('MAIL_HOST'))      define('MAIL_HOST',      '');
if (!defined('MAIL_PORT'))      define('MAIL_PORT',      465);
if (!defined('MAIL_USER'))      define('MAIL_USER',      '');
if (!defined('MAIL_PASS'))      define('MAIL_PASS',      '');
if (!defined('MAIL_FROM'))      define('MAIL_FROM',      '');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'HealthSphere');
if (!defined('MAIL_ADMIN'))     define('MAIL_ADMIN',     '');
if (!defined('APP_URL'))        define('APP_URL',        defined('BASE_URL') ? BASE_URL : '');

// ══════════════════════════════════════════════════════════════════
//  HealthSphere — Email Service
//  Sends HTML emails via Gmail SMTP (SSL port 465)
// ══════════════════════════════════════════════════════════════════

function hsSendEmail(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]
    ]);

    $sock = @stream_socket_client(
        'ssl://'.MAIL_HOST.':'.MAIL_PORT,
        $errno, $errstr, 15,
        STREAM_CLIENT_CONNECT, $ctx
    );

    if (!$sock) {
        error_log("HealthSphere Mail: Could not connect to SMTP. Error: $errstr ($errno)");
        return false;
    }

    $read = function() use ($sock): string { return fgets($sock, 512); };
    $send = function(string $cmd) use ($sock): void { fputs($sock, $cmd."\r\n"); };

    $read(); // greeting

    $send("EHLO localhost");
    while ($l = $read()) { if (substr($l,3,1)===' ') break; }

    // AUTH LOGIN
    $send("AUTH LOGIN");
    $read();
    $send(base64_encode(MAIL_USER));
    $read();
    $send(base64_encode(MAIL_PASS));
    $auth = $read();

    if (strpos($auth, '235') === false) {
        error_log("HealthSphere Mail: Auth failed. Response: $auth");
        fclose($sock);
        return false;
    }

    $send("MAIL FROM:<".MAIL_FROM.">");  $read();
    $send("RCPT TO:<$toEmail>");         $read();
    $send("DATA");                        $read();

    $boundary = md5(uniqid());
    $msg  = "From: ".MAIL_FROM_NAME." <".MAIL_FROM.">\r\n";
    $msg .= "To: $toName <$toEmail>\r\n";
    $msg .= "Subject: =?UTF-8?B?".base64_encode($subject)."?=\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n";
    $msg .= "X-Mailer: HealthSphere\r\n";
    $msg .= "\r\n";
    $msg .= chunk_split(base64_encode($htmlBody));
    $msg .= "\r\n.\r\n";

    fputs($sock, $msg);
    $res = $read();
    $send("QUIT");
    fclose($sock);

    $ok = strpos($res, '250') !== false || strpos($res, '2.0.0') !== false;
    if (!$ok) error_log("HealthSphere Mail: Send failed. Response: $res");
    return $ok;
}

// ── Email base template ──────────────────────────────────────────
function hsMailTemplate(string $title, string $body, string $ctaLabel = '', string $ctaUrl = ''): string {
    $cta = $ctaLabel
        ? "<div style='text-align:center;margin:28px 0;'><a href='$ctaUrl' style='background:linear-gradient(135deg,#1565C0,#5A4FCE);color:#fff;padding:14px 32px;border-radius:10px;text-decoration:none;font-weight:700;font-size:15px;display:inline-block;'>$ctaLabel</a></div>"
        : '';
    return "<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1.0'></head>
<body style='margin:0;padding:0;background:#EEF4FF;font-family:Inter,Segoe UI,system-ui,sans-serif;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background:#EEF4FF;padding:32px 16px;'>
  <tr><td align='center'>
    <table width='600' cellpadding='0' cellspacing='0' style='max-width:600px;width:100%;'>

      <!-- Header -->
      <tr><td style='background:linear-gradient(135deg,#071330 0%,#1565C0 100%);border-radius:16px 16px 0 0;padding:28px 36px;text-align:center;'>
        <div style='display:inline-flex;align-items:center;gap:12px;'>
          <div style='width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,.15);display:inline-flex;align-items:center;justify-content:center;font-size:22px;'>💙</div>
          <div style='text-align:left;'>
            <div style='font-size:20px;font-weight:800;color:#fff;'>HealthSphere</div>
            <div style='font-size:11px;color:rgba(255,255,255,.65);letter-spacing:1px;text-transform:uppercase;'>NHS Connected Platform</div>
          </div>
        </div>
        <h1 style='margin:20px 0 0;font-size:22px;font-weight:800;color:#fff;'>$title</h1>
      </td></tr>

      <!-- Body -->
      <tr><td style='background:#fff;padding:36px;'>
        $body
        $cta
        <hr style='border:none;border-top:1px solid #E2E8F0;margin:28px 0;'>
        <p style='font-size:12px;color:#94A3B8;text-align:center;margin:0;'>
          This email was sent by HealthSphere NHS Platform. Do not reply to this email.<br>
          <a href='".APP_URL."' style='color:#1565C0;'>www.healthsphere.nhs.uk</a>
        </p>
      </td></tr>

      <!-- Footer -->
      <tr><td style='background:#F8FAFF;border-radius:0 0 16px 16px;padding:16px 36px;text-align:center;'>
        <p style='font-size:11px;color:#94A3B8;margin:0;'>
          🔒 End-to-end encrypted &nbsp;·&nbsp; GDPR compliant &nbsp;·&nbsp; NHS certified
        </p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body></html>";
}

function p(string $text): string { return "<p style='font-size:15px;color:#374151;line-height:1.7;margin:0 0 14px;'>$text</p>"; }
function info(string $text): string { return "<div style='background:#EFF6FF;border-left:4px solid #1565C0;padding:12px 16px;border-radius:0 8px 8px 0;margin:14px 0;font-size:14px;color:#1E40AF;'>$text</div>"; }
function warn(string $text): string { return "<div style='background:#FEF3C7;border-left:4px solid #D97706;padding:12px 16px;border-radius:0 8px 8px 0;margin:14px 0;font-size:14px;color:#92400E;'>$text</div>"; }
function success(string $text): string { return "<div style='background:#DCFCE7;border-left:4px solid #16A34A;padding:12px 16px;border-radius:0 8px 8px 0;margin:14px 0;font-size:14px;color:#166534;'>$text</div>"; }
function dataRow(string $label, string $value): string {
    return "<tr><td style='padding:8px 14px;background:#F8FAFF;font-size:13px;color:#5E7A99;font-weight:600;border-bottom:1px solid #E2E8F0;width:40%;'>$label</td><td style='padding:8px 14px;font-size:13px;color:#0A1F44;font-weight:700;border-bottom:1px solid #E2E8F0;'>$value</td></tr>";
}
function dataTable(array $rows): string {
    $html = "<table width='100%' cellpadding='0' cellspacing='0' style='border:1px solid #E2E8F0;border-radius:10px;overflow:hidden;margin:16px 0;'>";
    foreach ($rows as $l=>$v) $html .= dataRow($l,$v);
    return $html."</table>";
}

// ══════════════════════════════════════════════════════════════════
//  EMAIL TEMPLATES
// ══════════════════════════════════════════════════════════════════

// 1. Patient welcome
function mailPatientWelcome(string $email, string $name, string $nhsId): bool {
    $body = p("Welcome to HealthSphere, <strong>$name</strong>! 🎉 Your account has been successfully created.")
          . success("✅ Your account is <strong>active and ready</strong> — you can sign in right away.")
          . dataTable(['Your Name'=>$name,'NHS ID'=>"<span style='font-family:monospace;font-size:15px;'>$nhsId</span>",'Email'=>$email,'Account Type'=>'Patient'])
          . p("Your HealthSphere account gives you access to:")
          . "<ul style='font-size:14px;color:#374151;line-height:2;padding-left:20px;'>
              <li>📋 Medical records, lab results &amp; prescriptions</li>
              <li>📅 Book &amp; manage doctor appointments</li>
              <li>🥗 Diet tracker &amp; nutrition monitoring</li>
              <li>❤️ Health insights &amp; wearable data</li>
              <li>💬 Secure messaging with your doctor</li>
              <li>🤖 AI-powered health assistant</li>
            </ul>"
          . warn("⚠️ Keep your NHS ID safe: <strong>$nhsId</strong>. Never share your password with anyone.");
    return hsSendEmail($email, $name, "Welcome to HealthSphere — Your Account is Ready", hsMailTemplate("Welcome to HealthSphere! 🎉", $body, "Sign In to HealthSphere", APP_URL."/index.php"));
}

// 2. Doctor/Gov application received (to applicant)
function mailApplicationReceived(string $email, string $name, string $role, string $nhsId): bool {
    $roleLabel = $role === 'doctor' ? 'Doctor / Clinician' : 'Government Health Analyst';
    $body = p("Thank you for registering, <strong>$name</strong>. Your <strong>$roleLabel</strong> application has been received.")
          . warn("⏳ Your account is <strong>pending admin review</strong>. You cannot sign in until your account is approved.")
          . dataTable(['Applicant'=>$name,'Role'=>$roleLabel,'Reference'=>"<span style='font-family:monospace;'>$nhsId</span>",'Status'=>'⏳ Pending Review'])
          . "<div style='background:#F8FAFF;border-radius:10px;padding:20px;margin:16px 0;'>"
          . "<h3 style='margin:0 0 12px;font-size:15px;color:#0A1F44;'>📋 What happens next?</h3>"
          . "<ol style='font-size:14px;color:#374151;line-height:2.2;padding-left:20px;margin:0;'>"
          . "<li>The HealthSphere admin team will review your credentials</li>"
          . "<li>Your HCPC/GMC/Staff ID will be verified</li>"
          . "<li>You will receive an email with the decision</li>"
          . "<li>Typical review time: <strong>24–48 hours</strong></li>"
          . "</ol></div>";
    return hsSendEmail($email, $name, "HealthSphere — Application Received ($roleLabel)", hsMailTemplate("Application Received ⏳", $body));
}

// 3. Admin notification — new pending application
function mailAdminNewApplication(string $applicantName, string $applicantEmail, string $role, string $nhsId, array $extras = []): bool {
    $roleLabel = $role === 'doctor' ? 'Doctor / Clinician' : 'Government Analyst';
    $rows = ['Applicant'=>$applicantName,'Email'=>$applicantEmail,'Role'=>$roleLabel,'Reference'=>$nhsId];
    $rows = array_merge($rows, $extras);
    $body = warn("⚠️ A new <strong>$roleLabel</strong> registration is awaiting your approval.")
          . dataTable($rows)
          . p("Please review the application and approve or reject from the admin console.");
    return hsSendEmail(MAIL_ADMIN, 'HealthSphere Admin', "⏳ New $roleLabel Application — Action Required", hsMailTemplate("New Registration Pending", $body, "Review in Admin Console", APP_URL."/admin/approvals.php"));
}

// 4. Account approved
function mailAccountApproved(string $email, string $name, string $role): bool {
    $roleLabel = $role === 'doctor' ? 'Doctor / Clinician' : 'Government Health Analyst';
    $body = success("✅ Congratulations, <strong>$name</strong>! Your <strong>$roleLabel</strong> account has been approved.")
          . p("You can now sign in to HealthSphere using your registered email address and password.")
          . "<div style='background:#F8FAFF;border-radius:10px;padding:18px;margin:16px 0;font-size:14px;color:#374151;'>"
          . ($role==='doctor'
              ? "As a <strong>Clinician</strong>, you have access to:<br><br>• Patient management &amp; medical records<br>• Appointment scheduling<br>• Prescription management<br>• Lab results &amp; clinical notes<br>• Secure patient messaging"
              : "As a <strong>Government Analyst</strong>, you have access to:<br><br>• National health trend dashboards<br>• Regional disease heatmaps<br>• Policy brief generation<br>• Anonymised population data<br>• Public health alert management")
          . "</div>";
    return hsSendEmail($email, $name, "HealthSphere — Account Approved ✅", hsMailTemplate("Your Account is Approved! ✅", $body, "Sign In to HealthSphere", APP_URL."/index.php"));
}

// 5. Account rejected
function mailAccountRejected(string $email, string $name, string $role, string $reason): bool {
    $roleLabel = $role === 'doctor' ? 'Doctor / Clinician' : 'Government Analyst';
    $body = "<div style='background:#FEE2E2;border-left:4px solid #DC2626;padding:12px 16px;border-radius:0 8px 8px 0;margin:0 0 16px;font-size:14px;color:#991B1B;'>
              ❌ Your <strong>$roleLabel</strong> application has not been approved at this time.
            </div>"
          . dataTable(['Applicant'=>$name,'Role'=>$roleLabel,'Reason'=>"<span style='color:#DC2626;'>$reason</span>"])
          . p("If you believe this is an error or would like to reapply with updated credentials, please contact the HealthSphere administration team.")
          . info("📧 Contact: <strong>".MAIL_ADMIN."</strong>");
    return hsSendEmail($email, $name, "HealthSphere — Application Decision", hsMailTemplate("Application Not Approved", $body));
}

// 6. Appointment confirmed (patient)
function mailAppointmentPatient(string $email, string $name, string $doctorName, string $date, string $time, string $reason, string $hospital): bool {
    $body = success("✅ Your appointment has been <strong>confirmed</strong>!")
          . dataTable(['Doctor'=>"Dr. $doctorName",'Date'=>$date,'Time'=>$time,'Hospital'=>$hospital,'Reason'=>$reason?:'-','Status'=>'✅ Confirmed'])
          . warn("📋 <strong>Please remember to:</strong> Arrive 10 minutes early &nbsp;·&nbsp; Bring your NHS ID &nbsp;·&nbsp; Bring any current medications");
    return hsSendEmail($email, $name, "Appointment Confirmed — Dr. $doctorName on $date", hsMailTemplate("Appointment Confirmed ✅", $body, "View in HealthSphere", APP_URL."/patient/appointments.php"));
}

// 7. Appointment notification (doctor)
function mailAppointmentDoctor(string $email, string $doctorName, string $patientName, string $patientNhsId, string $date, string $time, string $reason): bool {
    $body = info("📅 A new appointment has been booked with you.")
          . dataTable(['Patient'=>$patientName,'NHS ID'=>$patientNhsId,'Date'=>$date,'Time'=>$time,'Reason'=>$reason?:'General Consultation'])
          . p("You can view the patient's full medical records in your HealthSphere dashboard.");
    return hsSendEmail($email, "Dr. $doctorName", "New Appointment — $patientName on $date at $time", hsMailTemplate("New Appointment Booked 📅", $body, "Open Doctor Dashboard", APP_URL."/doctor/dashboard.php"));
}

// 8. Appointment cancelled
function mailAppointmentCancelled(string $email, string $name, string $doctorName, string $date, string $time): bool {
    $body = "<div style='background:#FEF3C7;border-left:4px solid #D97706;padding:12px 16px;border-radius:0 8px 8px 0;margin:0 0 16px;font-size:14px;color:#92400E;'>
              ⚠️ Your appointment has been <strong>cancelled</strong>.
            </div>"
          . dataTable(['Doctor'=>"Dr. $doctorName",'Date'=>$date,'Time'=>$time,'Status'=>'❌ Cancelled'])
          . p("You can book a new appointment through HealthSphere at any time.");
    return hsSendEmail($email, $name, "Appointment Cancelled — Dr. $doctorName on $date", hsMailTemplate("Appointment Cancelled ⚠️", $body, "Book New Appointment", APP_URL."/patient/appointments.php"));
}

// 9. Emergency chat message (to doctor)
function mailEmergencyAlert(string $doctorEmail, string $doctorName, string $patientName, string $patientNhsId, string $message): bool {
    $body = "<div style='background:#FEE2E2;border:2px solid #DC2626;border-radius:10px;padding:16px;margin:0 0 16px;text-align:center;'>"
          . "<div style='font-size:28px;margin-bottom:8px;'>🚨</div>"
          . "<h2 style='margin:0;color:#DC2626;font-size:18px;'>EMERGENCY MESSAGE</h2>"
          . "</div>"
          . dataTable(['Patient'=>$patientName,'NHS ID'=>$patientNhsId,'Sent'=>date('d M Y H:i')])
          . "<div style='background:#FFF5F5;border:1px solid #FECACA;border-radius:10px;padding:16px;margin:14px 0;'>"
          . "<p style='margin:0 0 6px;font-size:12px;font-weight:700;color:#991B1B;text-transform:uppercase;letter-spacing:.5px;'>Patient Message:</p>"
          . "<p style='margin:0;font-size:15px;color:#374151;font-style:italic;'>\"".htmlspecialchars($message)."\"</p>"
          . "</div>"
          . "<div style='background:#FEE2E2;border-radius:10px;padding:14px;text-align:center;font-size:14px;color:#991B1B;font-weight:700;'>"
          . "⚡ Please respond immediately via the HealthSphere messaging system"
          . "</div>";
    return hsSendEmail($doctorEmail, "Dr. $doctorName", "🚨 EMERGENCY — Patient $patientName Requires Urgent Attention", hsMailTemplate("Emergency Alert 🚨", $body, "Respond Now", APP_URL."/doctor/messages.php"));
}

// 10. New message notification (non-emergency)
function mailNewMessage(string $email, string $name, string $senderName, string $preview, string $url): bool {
    $body = info("💬 You have a new message from <strong>$senderName</strong>.")
          . "<div style='background:#F8FAFF;border-radius:10px;padding:14px 16px;margin:14px 0;font-size:14px;color:#374151;border:1px solid #E2E8F0;'>"
          . "<em>\"".htmlspecialchars(substr($preview,0,200)).(strlen($preview)>200?'...':'')."\"</em>"
          . "</div>";
    return hsSendEmail($email, $name, "New Message from $senderName — HealthSphere", hsMailTemplate("New Message 💬", $body, "Reply in HealthSphere", $url));
}

// 11. Prescription issued
function mailPrescriptionIssued(string $email, string $name, string $doctorName, string $medication, string $dosage, string $frequency, string $instructions): bool {
    $body = success("💊 A new prescription has been issued for you by <strong>Dr. $doctorName</strong>.")
          . dataTable(['Medication'=>"<strong>$medication</strong>",'Dosage'=>$dosage,'Frequency'=>$frequency,'Prescribed by'=>"Dr. $doctorName",'Instructions'=>$instructions?:'-'])
          . warn("⚠️ Always take medications as prescribed. Never adjust dosage without consulting your doctor. Report any side effects immediately.");
    return hsSendEmail($email, $name, "New Prescription — $medication | HealthSphere", hsMailTemplate("New Prescription Issued 💊", $body, "View in Medical Records", APP_URL."/patient/medical-records.php?tab=medications"));
}

// 12. Critical lab result
function mailCriticalLabResult(string $email, string $name, string $testName, string $result): bool {
    $body = "<div style='background:#FEE2E2;border:2px solid #DC2626;border-radius:10px;padding:16px;margin:0 0 16px;text-align:center;'>"
          . "<div style='font-size:24px;'>⚠️</div><h3 style='color:#DC2626;margin:8px 0 0;'>Critical Lab Result Detected</h3>"
          . "</div>"
          . dataTable(['Test'=>$testName,'Result'=>"<span style='color:#DC2626;font-weight:700;'>$result</span>",'Status'=>'⚠️ Requires Attention'])
          . p("Please contact your GP as soon as possible to discuss these results.")
          . warn("If you experience any symptoms, please call <strong>NHS 111</strong> or visit <strong>A&E</strong>.");
    return hsSendEmail($email, $name, "⚠️ Action Required — Critical Lab Result | HealthSphere", hsMailTemplate("Critical Lab Result ⚠️", $body, "View Full Results", APP_URL."/patient/medical-records.php?tab=records"));
}

// 13. Password reset / account security (generic)
function mailAccountNotice(string $email, string $name, string $subject, string $message): bool {
    $body = info($message);
    return hsSendEmail($email, $name, $subject, hsMailTemplate($subject, $body));
}
