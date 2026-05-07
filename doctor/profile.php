<?php
require_once __DIR__ . '/../config/config.php';
requireRole('doctor');
$user = getCurrentUser(); $uid = $user['id'];
$u = $pdo->prepare("SELECT u.*, d.specialization, d.hospital_name, d.hcpc_number, d.rating, d.experience_years, d.bio FROM users u LEFT JOIN doctors d ON u.id=d.user_id WHERE u.id=?");
$u->execute([$uid]); $u = $u->fetch();
$notifCount = getUnreadCount($pdo, $uid); $msgCount = getUnreadMessages($pdo, $uid);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Profile — HealthSphere Doctor</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css"></head>
<body><?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar"><div><div class="page-title"><i class="fas fa-user-md" style="color:var(--hs-blue);"></i> My Profile</div></div></div>
  <div class="hs-content">
    <div style="display:grid;grid-template-columns:280px 1fr;gap:20px;max-width:900px;">
      <div class="hs-card" style="text-align:center;padding:24px;">
        <div style="width:80px;height:80px;border-radius:50%;background:var(--hs-blue-grad);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:30px;color:#fff;font-weight:800;"><?= strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) ?></div>
        <h5 style="margin:0;color:var(--hs-navy);">Dr. <?= e($u['first_name'].' '.$u['last_name']) ?></h5>
        <p style="color:var(--hs-blue);font-size:13px;"><?= e($u['specialization'] ?? 'General Practice') ?></p>
        <div style="font-size:13px;color:var(--hs-muted);text-align:left;margin-top:12px;">
          <div style="margin-bottom:8px;"><i class="fas fa-hospital" style="width:18px;color:var(--hs-blue);"></i> <?= e($u['hospital_name'] ?? '—') ?></div>
          <div style="margin-bottom:8px;"><i class="fas fa-id-card" style="width:18px;color:var(--hs-blue);"></i> <?= e($u['hcpc_number'] ?? '—') ?></div>
          <div style="margin-bottom:8px;"><i class="fas fa-star" style="width:18px;color:#F59E0B;"></i> <?= $u['rating'] ?? '—' ?> rating</div>
          <div><i class="fas fa-briefcase" style="width:18px;color:var(--hs-blue);"></i> <?= $u['experience_years'] ?? '—' ?> years exp.</div>
        </div>
      </div>
      <div class="hs-card">
        <div class="hs-card-header"><span class="card-title">Doctor Details</span></div>
        <div class="hs-card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:13px;">
            <div><span style="color:var(--hs-muted);">Full Name</span><div style="font-weight:700;">Dr. <?= e($u['first_name'].' '.$u['last_name']) ?></div></div>
            <div><span style="color:var(--hs-muted);">Email</span><div style="font-weight:700;"><?= e($u['email']) ?></div></div>
            <div><span style="color:var(--hs-muted);">Specialization</span><div style="font-weight:700;"><?= e($u['specialization']??'—') ?></div></div>
            <div><span style="color:var(--hs-muted);">Hospital</span><div style="font-weight:700;"><?= e($u['hospital_name']??'—') ?></div></div>
            <div><span style="color:var(--hs-muted);">HCPC Number</span><div style="font-weight:700;font-family:monospace;"><?= e($u['hcpc_number']??'—') ?></div></div>
            <div><span style="color:var(--hs-muted);">NHS ID</span><div style="font-weight:700;font-family:monospace;"><?= e($u['nhs_id']??'—') ?></div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="../assets/js/main.js"></script></body></html>
