<?php
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
$user = getCurrentUser();
$uid  = $user['id'];
$notifCount = getUnreadCount($pdo, $uid);
$msgCount   = getUnreadMessages($pdo, $uid);

$pdo->exec("CREATE TABLE IF NOT EXISTS wearable_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    provider VARCHAR(20) NOT NULL DEFAULT 'google_fit',
    access_token TEXT,
    refresh_token TEXT,
    expires_at DATETIME,
    last_sync DATETIME NULL,
    connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_provider (user_id, provider),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$tokenRow = $pdo->prepare("SELECT * FROM wearable_tokens WHERE user_id=? AND provider='google_fit'");
$tokenRow->execute([$uid]);
$tokenRow = $tokenRow->fetch();
$isConnected = !empty($tokenRow['access_token']);

if (isset($_GET['disconnect'])) {
    $pdo->prepare("DELETE FROM wearable_tokens WHERE user_id=? AND provider='google_fit'")->execute([$uid]);
    header('Location: ' . BASE_URL . '/patient/wearable.php?disconnected=1');
    exit;
}

$recentMetrics = $pdo->prepare("
    SELECT * FROM health_metrics
    WHERE patient_id=? AND source='wearable'
    ORDER BY metric_date DESC, id DESC
");
$recentMetrics->execute([$uid]);
$recentMetrics = $recentMetrics->fetchAll();

// Deduplicate by date (keep first/latest per date)
$seen = []; $uniqueMetrics = [];
foreach ($recentMetrics as $m) {
    if (!in_array($m['metric_date'], $seen)) {
        $seen[] = $m['metric_date'];
        $uniqueMetrics[] = $m;
    }
}

$connected    = isset($_GET['connected']);
$disconnected = isset($_GET['disconnected']);
$syncError    = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Wearable Sync — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">

  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-heartbeat" style="color:#EA4335;"></i> Wearable & Device Sync</div>
      <div class="page-subtitle">Google Fit — real-time health data import</div>
    </div>
    <div class="topbar-actions">
      <?php if ($isConnected): ?>
      <button id="syncBtn" onclick="syncNow()"
        style="display:flex;align-items:center;gap:8px;background:#16A34A;color:#fff;border:none;border-radius:9px;padding:9px 20px;font-size:13px;font-weight:700;cursor:pointer;">
        <i class="fas fa-sync-alt"></i> Sync Now
      </button>
      <a href="?disconnect=1" onclick="return confirm('Disconnect Google Fit?')"
        style="font-size:12px;color:#DC2626;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:5px;">
        <i class="fas fa-unlink"></i> Disconnect
      </a>
      <?php else: ?>
      <a href="../api/google-fit-connect.php"
        style="display:flex;align-items:center;gap:8px;background:#4285F4;color:#fff;border-radius:9px;padding:9px 20px;font-size:13px;font-weight:700;text-decoration:none;">
        <i class="fab fa-google"></i> Connect Google Fit
      </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="hs-content">
    <div style="max-width:960px;margin:0 auto;">

      <?php if ($connected): ?>
      <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:10px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-check-circle" style="color:#16A34A;"></i>
        <span><strong style="color:#15803D;">Google Fit connected!</strong> <span style="font-size:13px;color:#166534;">Click "Sync Now" in the top right to import your health data.</span></span>
      </div>
      <?php endif; ?>

      <?php if ($disconnected): ?>
      <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:12px 16px;margin-bottom:16px;">
        <i class="fas fa-unlink" style="color:#DC2626;"></i> <strong style="color:#991B1B;">Disconnected.</strong>
      </div>
      <?php endif; ?>

      <!-- Sync result -->
      <div id="syncResult" style="display:none;margin-bottom:16px;"></div>

      <!-- Status bar -->
      <div class="hs-card" style="margin-bottom:16px;">
        <div class="hs-card-body" style="padding:14px 20px;">
          <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#4285F4,#34A853,#FBBC05,#EA4335);display:flex;align-items:center;justify-content:center;font-size:18px;">🏃</div>
              <div>
                <div style="font-weight:700;font-size:14px;color:var(--hs-navy);">Google Fit</div>
                <div style="font-size:12px;font-weight:600;color:<?= $isConnected ? '#16A34A' : '#9CA3AF' ?>;">
                  <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= $isConnected ? '#16A34A' : '#9CA3AF' ?>;margin-right:4px;"></span>
                  <?= $isConnected ? 'Connected' : 'Not connected' ?>
                </div>
              </div>
            </div>
            <?php if ($isConnected && $tokenRow['last_sync']): ?>
            <div style="font-size:12px;color:var(--hs-muted);">Last sync: <strong><?= timeAgo($tokenRow['last_sync']) ?></strong></div>
            <?php endif; ?>
            <div style="margin-left:auto;display:flex;gap:16px;font-size:12px;color:var(--hs-muted);">
              <span>👣 Steps</span><span>❤️ Heart Rate</span><span>😴 Sleep</span><span>🔥 Calories</span><span>⚖️ Weight</span>
              <span style="background:#FEF3C7;color:#92400E;border-radius:4px;padding:2px 8px;font-weight:600;">🔒 Read-only · Last 7 days</span>
            </div>
          </div>
        </div>
      </div>

      <?php if (!$isConnected): ?>
      <!-- Connect prompt -->
      <div class="hs-card">
        <div class="hs-card-body" style="text-align:center;padding:40px;">
          <div style="font-size:48px;margin-bottom:16px;">🏃</div>
          <h3 style="font-size:18px;font-weight:800;color:var(--hs-navy);margin-bottom:8px;">Connect Google Fit</h3>
          <p style="font-size:13px;color:var(--hs-muted);margin-bottom:24px;line-height:1.7;">
            Sync your real steps, heart rate, sleep and calories automatically.<br>
            Your health score will update with live data from your phone.
          </p>
          <a href="../api/google-fit-connect.php"
            style="display:inline-flex;align-items:center;gap:10px;background:#4285F4;color:#fff;padding:13px 28px;border-radius:12px;text-decoration:none;font-weight:700;font-size:14px;">
            <i class="fab fa-google"></i> Connect Google Fit
          </a>
          <div style="margin-top:20px;display:flex;justify-content:center;gap:32px;font-size:12px;color:var(--hs-muted);">
            <div>1. Install Google Fit on Android</div>
            <div>2. Click Connect above</div>
            <div>3. Approve permissions</div>
            <div>4. Click Sync Now</div>
          </div>
        </div>
      </div>

      <?php else: ?>
      <!-- Synced data table -->
      <div class="hs-card">
        <div class="hs-card-header">
          <span class="card-title"><i class="fas fa-history"></i> Synced Health Data</span>
          <span style="font-size:12px;color:var(--hs-muted);">Source: Google Fit · <?= count($uniqueMetrics) ?> days</span>
        </div>
        <?php if ($uniqueMetrics): ?>
        <div class="hs-card-body p-0">
          <table class="hs-table">
            <thead>
              <tr><th>Date</th><th>👣 Steps</th><th>❤️ Heart Rate</th><th>😴 Sleep</th><th>🔥 Calories</th><th>⚖️ Weight</th></tr>
            </thead>
            <tbody>
              <?php foreach ($uniqueMetrics as $m): ?>
              <tr>
                <td><strong><?= formatDate($m['metric_date']) ?></strong></td>
                <td><?= $m['steps_count'] ? number_format($m['steps_count']) . ' steps' : '<span style="color:#ccc;">—</span>' ?></td>
                <td><?= $m['heart_rate'] ? $m['heart_rate'] . ' bpm' : '<span style="color:#ccc;">—</span>' ?></td>
                <td><?= $m['sleep_hours'] ? $m['sleep_hours'] . ' hrs' : '<span style="color:#ccc;">—</span>' ?></td>
                <td><?= $m['calories_burned'] ? number_format($m['calories_burned']) . ' kcal' : '<span style="color:#ccc;">—</span>' ?></td>
                <td><?= $m['weight_kg'] ? $m['weight_kg'] . ' kg' : '<span style="color:#ccc;">—</span>' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="hs-card-body" style="text-align:center;padding:30px;color:var(--hs-muted);">
          <i class="fas fa-sync" style="font-size:32px;opacity:.3;"></i>
          <p style="margin-top:12px;">No data synced yet. Click <strong>Sync Now</strong> above.</p>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function syncNow() {
  const btn    = document.getElementById('syncBtn');
  const result = document.getElementById('syncResult');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';
  btn.disabled  = true;

  fetch('../api/google-fit-sync.php')
    .then(r => r.json())
    .then(data => {
      btn.innerHTML = '<i class="fas fa-sync-alt"></i> Sync Now';
      btn.disabled  = false;
      if (data.success) {
        result.style.display = 'block';
        result.innerHTML = `<div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;">
          <i class="fas fa-check-circle" style="color:#16A34A;"></i>
          <span style="color:#15803D;font-weight:700;">${data.message}</span>
          <a href="health-insights.php" style="margin-left:auto;font-size:12px;color:#15803D;font-weight:700;">View health score →</a>
        </div>`;
        setTimeout(() => location.reload(), 2000);
      } else {
        result.style.display = 'block';
        result.innerHTML = `<div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:12px 16px;color:#991B1B;">
          <i class="fas fa-exclamation-circle"></i> ${data.error}
          ${data.error.includes('reconnect') ? ' <a href="../api/google-fit-connect.php" style="color:#DC2626;font-weight:700;">Reconnect →</a>' : ''}
        </div>`;
      }
    })
    .catch(() => {
      btn.innerHTML = '<i class="fas fa-sync-alt"></i> Sync Now';
      btn.disabled  = false;
    });
}
</script>
</body>
</html>
