<?php
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
$user = getCurrentUser();
$uid  = $user['id'];
$notifCount = getUnreadCount($pdo, $uid);
$msgCount   = getUnreadMessages($pdo, $uid);

// Ensure wearable_tokens table exists
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

// Fetch token status
$tokenRow = $pdo->prepare("SELECT * FROM wearable_tokens WHERE user_id=? AND provider='google_fit'");
$tokenRow->execute([$uid]);
$tokenRow = $tokenRow->fetch();
$isConnected = !empty($tokenRow['access_token']);

// Handle disconnect
if (isset($_GET['disconnect'])) {
    $pdo->prepare("DELETE FROM wearable_tokens WHERE user_id=? AND provider='google_fit'")->execute([$uid]);
    header('Location: ' . BASE_URL . '/patient/wearable.php?disconnected=1');
    exit;
}

// Recent synced metrics
$recentMetrics = $pdo->prepare("
    SELECT * FROM health_metrics
    WHERE patient_id=? AND source='wearable'
    ORDER BY metric_date DESC LIMIT 7
");
$recentMetrics->execute([$uid]);
$recentMetrics = $recentMetrics->fetchAll();

$connected  = isset($_GET['connected']);
$disconnected = isset($_GET['disconnected']);
$syncError  = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Wearable Sync — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.connect-card { background:#fff; border-radius:20px; border:1px solid var(--hs-border); padding:32px; max-width:620px; margin:0 auto; box-shadow:0 4px 24px rgba(10,31,68,.08); }
.provider-badge { display:flex; align-items:center; gap:16px; padding:18px 20px; border-radius:14px; border:2px solid var(--hs-border); margin-bottom:20px; }
.provider-badge.connected { border-color:#16A34A; background:#F0FDF4; }
.metric-chip { background:#F4F8FF; border:1px solid var(--hs-border); border-radius:10px; padding:12px 16px; text-align:center; }
.sync-row { display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--hs-border); font-size:13px; }
.sync-row:last-child { border:none; }
.status-dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">

  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-heartbeat" style="color:#EA4335;"></i> Wearable & Device Sync</div>
      <div class="page-subtitle">Connect your fitness tracker to auto-import health data</div>
    </div>
  </div>

  <div class="hs-content">
    <div style="max-width:900px;margin:0 auto;">

      <?php if ($connected): ?>
      <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-check-circle" style="color:#16A34A;font-size:18px;"></i>
        <div><strong style="color:#15803D;">Google Fit connected!</strong> <span style="font-size:13px;color:#166534;">Click "Sync Now" to import your health data.</span></div>
      </div>
      <?php endif; ?>

      <?php if ($disconnected): ?>
      <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:12px;padding:14px 18px;margin-bottom:20px;">
        <i class="fas fa-unlink" style="color:#DC2626;"></i> <strong style="color:#991B1B;">Google Fit disconnected.</strong>
      </div>
      <?php endif; ?>

      <?php if ($syncError): ?>
      <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:12px;padding:14px 18px;margin-bottom:20px;">
        <i class="fas fa-exclamation-circle" style="color:#DC2626;"></i>
        <strong style="color:#991B1B;">Connection failed.</strong>
        <span style="font-size:13px;color:#7F1D1D;">Please try again. (<?= e($syncError) ?>)</span>
      </div>
      <?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">

        <!-- Connection Card -->
        <div class="hs-card">
          <div class="hs-card-header"><span class="card-title"><i class="fas fa-link"></i> Google Fit</span></div>
          <div class="hs-card-body">

            <div class="provider-badge <?= $isConnected ? 'connected' : '' ?>">
              <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#4285F4,#34A853,#FBBC05,#EA4335);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">🏃</div>
              <div style="flex:1;">
                <div style="font-weight:700;font-size:15px;color:var(--hs-navy);">Google Fit</div>
                <div style="font-size:12px;color:<?= $isConnected ? '#16A34A' : 'var(--hs-muted)' ?>;font-weight:600;">
                  <span class="status-dot" style="background:<?= $isConnected ? '#16A34A' : '#9CA3AF' ?>;margin-right:4px;"></span>
                  <?= $isConnected ? 'Connected' : 'Not connected' ?>
                </div>
                <?php if ($isConnected && $tokenRow['last_sync']): ?>
                <div style="font-size:11px;color:var(--hs-muted);margin-top:2px;">Last sync: <?= timeAgo($tokenRow['last_sync']) ?></div>
                <?php elseif ($isConnected): ?>
                <div style="font-size:11px;color:var(--hs-muted);margin-top:2px;">Never synced — click Sync Now</div>
                <?php endif; ?>
              </div>
            </div>

            <?php if (!$isConnected): ?>
            <a href="../api/google-fit-connect.php"
               style="display:flex;align-items:center;justify-content:center;gap:10px;background:linear-gradient(135deg,#4285F4,#1565C0);color:#fff;padding:13px 20px;border-radius:12px;text-decoration:none;font-weight:700;font-size:14px;margin-bottom:12px;">
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                <path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" fill="#4285F4"/>
                <path d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332C2.438 15.983 5.482 18 9 18z" fill="#34A853"/>
                <path d="M3.964 10.71c-.18-.54-.282-1.117-.282-1.71s.102-1.17.282-1.71V4.958H.957C.347 6.173 0 7.548 0 9s.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/>
                <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.438 2.017.957 4.958L3.964 6.29C4.672 4.163 6.656 3.58 9 3.58z" fill="#EA4335"/>
              </svg>
              Connect Google Fit
            </a>
            <p style="font-size:12px;color:var(--hs-muted);text-align:center;line-height:1.6;">
              You'll be redirected to Google to grant HealthSphere read-only access to your fitness data.
            </p>
            <?php else: ?>
            <button id="syncBtn" onclick="syncNow()"
              style="width:100%;display:flex;align-items:center;justify-content:center;gap:10px;background:linear-gradient(135deg,#16A34A,#15803D);color:#fff;padding:13px 20px;border-radius:12px;border:none;font-weight:700;font-size:14px;cursor:pointer;margin-bottom:12px;">
              <i class="fas fa-sync-alt"></i> Sync Now
            </button>
            <a href="?disconnect=1"
               onclick="return confirm('Disconnect Google Fit? Your existing synced data will remain.')"
               style="display:block;text-align:center;font-size:12px;color:#DC2626;text-decoration:none;font-weight:600;">
              <i class="fas fa-unlink"></i> Disconnect Google Fit
            </a>
            <?php endif; ?>
          </div>
        </div>

        <!-- What we sync -->
        <div class="hs-card">
          <div class="hs-card-header"><span class="card-title"><i class="fas fa-database"></i> Data We Sync</span></div>
          <div class="hs-card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
              <div class="metric-chip">
                <div style="font-size:24px;margin-bottom:4px;">👣</div>
                <div style="font-weight:700;font-size:13px;color:var(--hs-navy);">Steps</div>
                <div style="font-size:11px;color:var(--hs-muted);">Daily step count</div>
              </div>
              <div class="metric-chip">
                <div style="font-size:24px;margin-bottom:4px;">❤️</div>
                <div style="font-weight:700;font-size:13px;color:var(--hs-navy);">Heart Rate</div>
                <div style="font-size:11px;color:var(--hs-muted);">Avg bpm per day</div>
              </div>
              <div class="metric-chip">
                <div style="font-size:24px;margin-bottom:4px;">😴</div>
                <div style="font-weight:700;font-size:13px;color:var(--hs-navy);">Sleep</div>
                <div style="font-size:11px;color:var(--hs-muted);">Hours per night</div>
              </div>
              <div class="metric-chip">
                <div style="font-size:24px;margin-bottom:4px;">🔥</div>
                <div style="font-weight:700;font-size:13px;color:var(--hs-navy);">Calories</div>
                <div style="font-size:11px;color:var(--hs-muted);">Calories burned</div>
              </div>
              <div class="metric-chip" style="grid-column:1/-1;">
                <div style="font-size:24px;margin-bottom:4px;">⚖️</div>
                <div style="font-weight:700;font-size:13px;color:var(--hs-navy);">Weight (if recorded)</div>
                <div style="font-size:11px;color:var(--hs-muted);">From Google Fit body data</div>
              </div>
            </div>
            <div style="margin-top:12px;background:#FEF3C7;border-radius:8px;padding:10px 12px;font-size:11px;color:#92400E;">
              <i class="fas fa-lock"></i> <strong>Read-only.</strong> HealthSphere never writes to your Google Fit account. Data syncs for the last 7 days.
            </div>
          </div>
        </div>

      </div>

      <!-- Sync result -->
      <div id="syncResult" style="display:none;margin-bottom:20px;"></div>

      <!-- Recent synced data -->
      <?php if ($recentMetrics): ?>
      <div class="hs-card">
        <div class="hs-card-header">
          <span class="card-title"><i class="fas fa-history"></i> Recently Synced Data</span>
          <span style="font-size:12px;color:var(--hs-muted);">Source: Google Fit · Last 7 days</span>
        </div>
        <div class="hs-card-body p-0">
          <table class="hs-table">
            <thead>
              <tr><th>Date</th><th>👣 Steps</th><th>❤️ Heart Rate</th><th>😴 Sleep</th><th>🔥 Calories</th><th>⚖️ Weight</th></tr>
            </thead>
            <tbody>
              <?php foreach ($recentMetrics as $m): ?>
              <tr>
                <td><strong><?= formatDate($m['metric_date']) ?></strong></td>
                <td><?= $m['steps_count'] ? number_format($m['steps_count']) . ' steps' : '—' ?></td>
                <td><?= $m['heart_rate'] ? $m['heart_rate'] . ' bpm' : '—' ?></td>
                <td><?= $m['sleep_hours'] ? $m['sleep_hours'] . ' hrs' : '—' ?></td>
                <td><?= $m['calories_burned'] ? number_format($m['calories_burned']) . ' kcal' : '—' ?></td>
                <td><?= $m['weight_kg'] ? $m['weight_kg'] . ' kg' : '—' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- Setup Instructions -->
      <?php if (!$isConnected): ?>
      <div class="hs-card" style="margin-top:20px;">
        <div class="hs-card-header"><span class="card-title"><i class="fas fa-question-circle"></i> How to set up Google Fit</span></div>
        <div class="hs-card-body">
          <div style="display:flex;flex-direction:column;gap:14px;">
            <?php foreach ([
              ['1', 'Install Google Fit', 'Download the Google Fit app on your Android phone from the Play Store.', '📱'],
              ['2', 'Enable tracking', 'Open Google Fit → tap your profile → turn on activity tracking. Walk, run or use any sport.', '🏃'],
              ['3', 'Connect here', 'Click "Connect Google Fit" above and approve the permissions. We only request read access.', '🔗'],
              ['4', 'Sync your data', 'Click "Sync Now" to import the last 7 days. Your health score will update automatically.', '✅'],
            ] as [$num, $title, $desc, $icon]): ?>
            <div style="display:flex;gap:14px;align-items:flex-start;">
              <div style="width:32px;height:32px;background:var(--hs-blue);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;flex-shrink:0;"><?= $num ?></div>
              <div>
                <div style="font-weight:700;font-size:13px;color:var(--hs-navy);"><?= $icon ?> <?= $title ?></div>
                <div style="font-size:12px;color:var(--hs-muted);margin-top:2px;line-height:1.6;"><?= $desc ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function syncNow() {
  const btn = document.getElementById('syncBtn');
  const result = document.getElementById('syncResult');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing from Google Fit...';
  btn.disabled = true;
  result.style.display = 'none';

  fetch('../api/google-fit-sync.php')
    .then(r => r.json())
    .then(data => {
      btn.innerHTML = '<i class="fas fa-sync-alt"></i> Sync Now';
      btn.disabled = false;
      if (data.success) {
        result.style.display = 'block';
        result.innerHTML = `
          <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:12px;padding:16px 18px;">
            <div style="font-weight:700;color:#15803D;margin-bottom:10px;">
              <i class="fas fa-check-circle"></i> ${data.message}
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
              ${data.summary.filter(d=>Object.keys(d).length>1).map(d=>`
                <div style="background:#fff;border:1px solid #BBF7D0;border-radius:8px;padding:8px 14px;font-size:12px;">
                  <strong>${d.date}</strong><br>
                  ${d.steps ? '👣 ' + d.steps.toLocaleString() + ' steps' : ''}
                  ${d.heart_rate ? ' · ❤️ ' + d.heart_rate + ' bpm' : ''}
                  ${d.sleep_mins ? ' · 😴 ' + (d.sleep_mins/60).toFixed(1) + 'h' : ''}
                  ${d.calories ? ' · 🔥 ' + d.calories + ' kcal' : ''}
                </div>`).join('')}
            </div>
            <div style="margin-top:12px;font-size:12px;color:#166534;">
              Your health score has been updated. <a href="health-insights.php" style="color:#15803D;font-weight:700;">View insights →</a>
            </div>
          </div>`;
        // Reload page after 3s to show updated table
        setTimeout(() => location.reload(), 4000);
      } else {
        result.style.display = 'block';
        result.innerHTML = `
          <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:12px;padding:14px 18px;color:#991B1B;">
            <i class="fas fa-exclamation-circle"></i> ${data.error}
            ${data.error.includes('reconnect') ? '<br><a href="../api/google-fit-connect.php" style="color:#DC2626;font-weight:700;">Reconnect Google Fit →</a>' : ''}
          </div>`;
      }
    })
    .catch(() => {
      btn.innerHTML = '<i class="fas fa-sync-alt"></i> Sync Now';
      btn.disabled = false;
      result.style.display = 'block';
      result.innerHTML = '<div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:12px;padding:14px;color:#991B1B;"><i class="fas fa-wifi"></i> Network error. Please check your connection.</div>';
    });
}
</script>
</body>
</html>
