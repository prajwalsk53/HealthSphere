<?php
// Shared sidebar — role-based nav
$user     = getCurrentUser();
$role     = $user['role'];
$initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
$notifCount = isLoggedIn() ? getUnreadCount($pdo, $user['id']) : 0;
$msgCount   = isLoggedIn() ? getUnreadMessages($pdo, $user['id']) : 0;

$base = defined('BASE_PATH') ? BASE_PATH : '/HealthSphere';

$navByRole = [
    'patient' => [
        ['icon' => 'fa-th-large',        'label' => 'Dashboard',         'href' => "$base/patient/dashboard.php"],
        ['icon' => 'fa-calendar-check',  'label' => 'Appointments',      'href' => "$base/patient/appointments.php"],
        ['icon' => 'fa-calendar-alt',    'label' => 'Calendar View',     'href' => "$base/patient/calendar.php"],
        ['icon' => 'fa-file-medical',    'label' => 'Medical Records',   'href' => "$base/patient/medical-records.php"],
        ['icon' => 'fa-folder-medical',  'label' => 'Documents',         'href' => "$base/patient/documents.php"],
        ['icon' => 'fa-utensils',        'label' => 'Diet Tracker',      'href' => "$base/patient/diet-tracker.php"],
        ['icon' => 'fa-shield-heart',    'label' => 'Safe Appetite',     'href' => "$base/patient/safe-appetite.php"],
        ['icon' => 'fa-heartbeat',       'label' => 'Health Insights',   'href' => "$base/patient/health-insights.php"],
        ['icon' => 'fa-chart-bar',       'label' => 'Health Analysis',   'href' => "$base/patient/health-analysis.php"],
        ['icon' => 'fa-map-marked-alt',  'label' => 'Health Map',        'href' => "$base/patient/map.php"],
        ['icon' => 'fa-robot',           'label' => 'AI Assistant',      'href' => "$base/patient/ai-assistant.php"],
        ['icon' => 'fa-comment-medical', 'label' => 'Messages',          'href' => "$base/patient/messages.php", 'badge' => $msgCount],
        ['icon' => 'fa-bell',            'label' => 'Notifications',     'href' => "$base/patient/notifications.php", 'badge' => $notifCount],
        ['icon' => 'fa-user',            'label' => 'My Profile',        'href' => "$base/patient/profile.php"],
    ],
    'doctor' => [
        ['icon' => 'fa-th-large',        'label' => 'Dashboard',         'href' => "$base/doctor/dashboard.php"],
        ['icon' => 'fa-users',           'label' => 'My Patients',       'href' => "$base/doctor/patients.php"],
        ['icon' => 'fa-calendar-alt',    'label' => 'Schedule',          'href' => "$base/doctor/schedule.php"],
        ['icon' => 'fa-flask',           'label' => 'Lab Results',       'href' => "$base/doctor/lab-results.php"],
        ['icon' => 'fa-pills',           'label' => 'Prescriptions',     'href' => "$base/doctor/prescriptions.php"],
        ['icon' => 'fa-comment-medical', 'label' => 'Messages',          'href' => "$base/doctor/messages.php", 'badge' => $msgCount],
        ['icon' => 'fa-bell',            'label' => 'Alerts & Tasks',    'href' => "$base/doctor/alerts.php", 'badge' => $notifCount],
        ['icon' => 'fa-user-md',         'label' => 'My Profile',        'href' => "$base/doctor/profile.php"],
    ],
    'admin' => [
        ['icon' => 'fa-th-large',        'label' => 'Dashboard',         'href' => "$base/admin/dashboard.php"],
        ['icon' => 'fa-chart-bar',       'label' => 'Analytics',         'href' => "$base/admin/analytics.php"],
        ['icon' => 'fa-user-check',      'label' => 'Approvals',         'href' => "$base/admin/approvals.php", 'badge' => (function() use($pdo){ try{return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE approval_status='pending'")->fetchColumn();}catch(\Exception $e){return 0;} })()],
        ['icon' => 'fa-users',           'label' => 'User Management',   'href' => "$base/admin/users.php"],
        ['icon' => 'fa-user-md',         'label' => 'Doctor Access',     'href' => "$base/admin/doctors.php"],
        ['icon' => 'fa-drumstick-bite',  'label' => 'Food Database',     'href' => "$base/admin/food-data.php"],
        ['icon' => 'fa-dna',             'label' => 'Genetic Diseases',  'href' => "$base/admin/diseases.php"],
        ['icon' => 'fa-shield-alt',      'label' => 'Access Logs',       'href' => "$base/admin/access-logs.php"],
        ['icon' => 'fa-envelope',        'label' => 'Email Testing',     'href' => "$base/admin/test-email.php"],
        ['icon' => 'fa-cog',             'label' => 'Settings',          'href' => "$base/admin/settings.php"],
    ],
    'government' => [
        ['icon' => 'fa-th-large',        'label' => 'Public Dashboard',  'href' => "$base/government/dashboard.php"],
        ['icon' => 'fa-map-marked-alt',  'label' => 'Regional Map',      'href' => "$base/government/regional.php"],
        ['icon' => 'fa-globe',           'label' => 'Live Health Map',   'href' => "$base/government/map.php"],
        ['icon' => 'fa-chart-line',      'label' => 'Trend Analysis',    'href' => "$base/government/trends.php"],
        ['icon' => 'fa-bell',            'label' => 'Alerts',            'href' => "$base/government/alerts.php"],
        ['icon' => 'fa-file-alt',        'label' => 'Reports',           'href' => "$base/government/reports.php"],
    ],
];

$nav   = $navByRole[$role] ?? [];
$currentPath = $_SERVER['REQUEST_URI'];
?>

<aside class="hs-sidebar">
  <!-- Logo -->
  <div class="hs-sidebar-logo">
    <div class="logo-icon"><i class="fas fa-heartbeat"></i></div>
    <div class="logo-text">
      <h5>HealthSphere</h5>
      <small>NHS Connected</small>
    </div>
  </div>

  <!-- User info -->
  <div class="hs-sidebar-user">
    <div class="avatar"><?= $initials ?></div>
    <div class="user-info">
      <div class="user-name"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></div>
      <div class="user-role"><?= ucfirst($role) ?></div>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="hs-sidebar-nav">
    <div class="nav-section-label">Navigation</div>
    <?php foreach ($nav as $item): ?>
    <?php
      $isActive = str_contains($currentPath, basename($item['href']));
      $badge = $item['badge'] ?? 0;
    ?>
    <a href="<?= $item['href'] ?>" class="nav-link <?= $isActive ? 'active' : '' ?>">
      <i class="fas <?= $item['icon'] ?>"></i>
      <?= $item['label'] ?>
      <?php if ($badge > 0): ?>
      <span class="badge bg-danger"><?= $badge ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <!-- Footer -->
  <div class="hs-sidebar-footer">
    <?php if ($role === 'patient'): ?>
    <div style="background:rgba(255,255,255,.06);border-radius:8px;padding:10px 12px;margin-bottom:12px;font-size:12px;color:rgba(255,255,255,.6);">
      <div style="font-size:10px;letter-spacing:.8px;text-transform:uppercase;margin-bottom:4px;">NHS ID</div>
      <div style="font-weight:700;color:#fff;font-family:monospace;"><?= e($user['nhs_id']) ?></div>
    </div>
    <?php endif; ?>
    <a href="<?= $base ?>/logout.php" class="nav-link" style="color:rgba(239,68,68,.8);">
      <i class="fas fa-sign-out-alt"></i> Sign Out
    </a>
  </div>
</aside>
