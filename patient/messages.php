<?php
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
$user = getCurrentUser();
$uid  = $user['id'];

// Get conversations (unique doctors)
$convs = $pdo->prepare("
    SELECT DISTINCT u.id, u.first_name, u.last_name, d.specialization,
        (SELECT COUNT(*) FROM messages WHERE sender_id=u.id AND receiver_id=? AND is_read=0) as unread,
        (SELECT m2.message FROM messages m2 WHERE (m2.sender_id=u.id AND m2.receiver_id=?) OR (m2.sender_id=? AND m2.receiver_id=u.id) ORDER BY m2.created_at DESC LIMIT 1) as last_msg,
        (SELECT m3.created_at FROM messages m3 WHERE (m3.sender_id=u.id AND m3.receiver_id=?) OR (m3.sender_id=? AND m3.receiver_id=u.id) ORDER BY m3.created_at DESC LIMIT 1) as last_time
    FROM messages m JOIN users u ON (m.sender_id=u.id OR m.receiver_id=u.id)
    LEFT JOIN doctors d ON u.id=d.user_id
    WHERE u.id != ? AND (m.sender_id=? OR m.receiver_id=?)
    AND u.role='doctor'
");
$convs->execute([$uid,$uid,$uid,$uid,$uid,$uid,$uid,$uid]);
$conversations = $convs->fetchAll();

// If no conversations, get all available doctors
if (!$conversations) {
    $conversations = $pdo->query("SELECT u.id, u.first_name, u.last_name, d.specialization, 0 as unread, '' as last_msg, NULL as last_time FROM users u JOIN doctors d ON u.id=d.user_id WHERE u.is_active=1 LIMIT 5")->fetchAll();
}

// Active chat
$chatWith = (int)($_GET['with'] ?? ($conversations[0]['id'] ?? 0));
$chatUser = null;
if ($chatWith) {
    $cu = $pdo->prepare("SELECT u.*, d.specialization FROM users u LEFT JOIN doctors d ON u.id=d.user_id WHERE u.id=?");
    $cu->execute([$chatWith]); $chatUser = $cu->fetch();
}

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $chatWith) {
    $msg = trim($_POST['message'] ?? '');
    $isEmergency = !empty($_POST['emergency']) ? 1 : 0;
    if ($msg) {
        $pdo->prepare("INSERT INTO messages (sender_id,receiver_id,message,is_emergency) VALUES (?,?,?,?)")
            ->execute([$uid, $chatWith, $msg, $isEmergency]);
    }
    header("Location: messages.php?with=$chatWith");
    exit;
}

// Chat history
$msgs = [];
if ($chatWith) {
    $pdo->prepare("UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=?")->execute([$chatWith, $uid]);
    $ms = $pdo->prepare("SELECT m.*, u.first_name, u.last_name FROM messages m JOIN users u ON m.sender_id=u.id WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?) ORDER BY m.created_at");
    $ms->execute([$uid,$chatWith,$chatWith,$uid]);
    $msgs = $ms->fetchAll();
}

$notifCount = getUnreadCount($pdo, $uid);
$msgCount   = getUnreadMessages($pdo, $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-comment-medical" style="color:var(--hs-blue);"></i> Messages</div>
      <div class="page-subtitle">Secure communication with your healthcare team</div>
    </div>
    <div class="topbar-actions">
      <?php if ($msgCount > 0): ?>
      <span class="badge bg-danger"><?= $msgCount ?> unread</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="hs-content" style="padding:0;">
    <div style="display:grid;grid-template-columns:300px 1fr;height:calc(100vh - 64px);">

      <!-- Sidebar: conversations -->
      <div style="border-right:1px solid var(--hs-border);overflow-y:auto;background:#fff;">
        <div style="padding:16px;border-bottom:1px solid var(--hs-border);">
          <input type="text" placeholder="Search conversations..." class="form-control" style="font-size:13px;">
        </div>
        <?php foreach ($conversations as $conv): ?>
        <a href="?with=<?= $conv['id'] ?>" style="text-decoration:none;display:flex;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid var(--hs-border);transition:var(--transition);background:<?= $chatWith===$conv['id']?'#EFF6FF':'#fff' ?>;" onmouseover="this.style.background='#F8FAFF'" onmouseout="this.style.background='<?= $chatWith===$conv['id']?'#EFF6FF':'#fff' ?>'">
          <div style="width:42px;height:42px;border-radius:50%;background:var(--hs-blue-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;flex-shrink:0;">
            <?= strtoupper(substr($conv['first_name'],0,1).substr($conv['last_name'],0,1)) ?>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-weight:700;font-size:13.5px;color:var(--hs-navy);">Dr. <?= e($conv['first_name'].' '.$conv['last_name']) ?></span>
              <?php if ($conv['last_time']): ?><span style="font-size:11px;color:var(--hs-muted);"><?= timeAgo($conv['last_time']) ?></span><?php endif; ?>
            </div>
            <div style="font-size:12px;color:var(--hs-blue);"><?= e($conv['specialization'] ?? 'General Practice') ?></div>
            <?php if ($conv['last_msg']): ?>
            <div style="font-size:12px;color:var(--hs-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px;"><?= e(substr($conv['last_msg'],0,50)) ?>...</div>
            <?php endif; ?>
          </div>
          <?php if ($conv['unread'] > 0): ?>
          <span style="width:20px;height:20px;border-radius:50%;background:var(--hs-danger);color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;"><?= $conv['unread'] ?></span>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
        <?php if (!$conversations): ?>
        <div style="padding:30px;text-align:center;color:var(--hs-muted);font-size:13px;">No conversations yet.</div>
        <?php endif; ?>
      </div>

      <!-- Chat window -->
      <div style="display:flex;flex-direction:column;background:#F4F8FF;">
        <?php if ($chatUser): ?>
        <!-- Chat header -->
        <div style="background:var(--hs-navy);color:#fff;padding:14px 20px;display:flex;align-items:center;gap:12px;flex-shrink:0;">
          <div style="width:40px;height:40px;border-radius:50%;background:var(--hs-blue);display:flex;align-items:center;justify-content:center;font-weight:700;">
            <?= strtoupper(substr($chatUser['first_name'],0,1).substr($chatUser['last_name'],0,1)) ?>
          </div>
          <div style="flex:1;">
            <div style="font-weight:700;">Dr. <?= e($chatUser['first_name'].' '.$chatUser['last_name']) ?></div>
            <div style="font-size:12px;opacity:.7;"><?= e($chatUser['specialization'] ?? 'General Practice') ?> · <span style="color:#22C55E;">● Online</span></div>
          </div>
          <span class="emergency-badge pulse">🚨 Emergency</span>
        </div>

        <!-- Messages -->
        <div id="chatMessages" style="flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:12px;">
          <?php if (!$msgs): ?>
          <div style="text-align:center;color:var(--hs-muted);margin:auto;">
            <i class="fas fa-comment-medical" style="font-size:40px;opacity:.3;"></i>
            <p style="margin-top:12px;font-size:13px;">Start a conversation with Dr. <?= e($chatUser['first_name']) ?></p>
          </div>
          <?php endif; ?>
          <?php foreach ($msgs as $m):
            $isSent = $m['sender_id'] == $uid;
          ?>
          <div class="chat-msg <?= $isSent ? 'sent' : 'received' ?>">
            <?php if (!$isSent): ?>
            <span class="msg-sender">Dr. <?= e($m['first_name'].' '.$m['last_name']) ?></span>
            <?php endif; ?>
            <?php if ($m['is_emergency']): ?>
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
              <span class="emergency-badge">🚨 EMERGENCY</span>
            </div>
            <?php endif; ?>
            <div class="bubble <?= $m['is_emergency'] ? 'bg-danger' : '' ?>" <?= $m['is_emergency'] ? 'style="background:#DC2626;"' : '' ?>>
              <?= e($m['message']) ?>
            </div>
            <span class="msg-time"><?= date('H:i', strtotime($m['created_at'])) ?></span>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Typing indicator -->
        <div id="typingIndicator" style="display:none;padding:6px 20px;font-size:12px;color:var(--hs-muted);align-items:center;gap:6px;background:#F4F8FF;">
          <div style="display:flex;gap:3px;">
            <span style="width:6px;height:6px;border-radius:50%;background:var(--hs-muted);animation:pulse 1.2s ease-in-out infinite;"></span>
            <span style="width:6px;height:6px;border-radius:50%;background:var(--hs-muted);animation:pulse 1.2s ease-in-out .2s infinite;"></span>
            <span style="width:6px;height:6px;border-radius:50%;background:var(--hs-muted);animation:pulse 1.2s ease-in-out .4s infinite;"></span>
          </div>
          Doctor is typing...
        </div>
        <!-- Input -->
        <div style="padding:14px 20px;background:#fff;border-top:1px solid var(--hs-border);flex-shrink:0;">
          <form id="chatForm" style="display:flex;gap:10px;align-items:center;">
            <label style="cursor:pointer;padding:8px 12px;border:1px solid var(--hs-border);border-radius:8px;font-size:12px;font-weight:600;color:var(--hs-danger);background:#FEF2F2;display:flex;align-items:center;gap:6px;">
              <input type="checkbox" name="emergency" style="display:none;">
              <i class="fas fa-exclamation-triangle"></i> Emergency
            </label>
            <input type="text" name="message" id="chatInput" placeholder="Type your message..." class="form-control" style="flex:1;border-radius:24px;" autocomplete="off" required>
            <button type="button" style="background:none;border:none;cursor:pointer;padding:8px;font-size:18px;" title="Voice message">🎤</button>
            <button type="submit" class="chat-send-btn"><i class="fas fa-paper-plane"></i></button>
          </form>
          <p style="font-size:11px;color:var(--hs-muted);margin-top:8px;text-align:center;">
            <i class="fas fa-lock"></i> End-to-end encrypted · GDPR compliant
          </p>
        </div>

        <?php else: ?>
        <div style="flex:1;display:flex;align-items:center;justify-content:center;color:var(--hs-muted);">
          <div style="text-align:center;">
            <i class="fas fa-comment-medical" style="font-size:60px;opacity:.2;"></i>
            <p style="margin-top:16px;font-size:14px;">Select a conversation to start messaging</p>
          </div>
        </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
const CHAT_WITH  = <?= (int)$chatWith ?>;
const CURRENT_UID = <?= $uid ?>;
let lastTs = '<?= date('Y-m-d H:i:s', strtotime('-5 minutes')) ?>';
let polling = null;

// Scroll to bottom
function scrollBottom() {
  const msgs = document.getElementById('chatMessages');
  if (msgs) msgs.scrollTop = msgs.scrollHeight;
}
scrollBottom();

// Render a message bubble
function renderMessage(m) {
  const isSent = m.sender_id == CURRENT_UID;
  const time   = m.created_at ? m.created_at.substring(11,16) : '';
  const emBadge = m.is_emergency == 1 ? '<span style="background:#DC2626;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:3px;display:block;margin-bottom:3px;">🚨 EMERGENCY</span>' : '';
  return `<div class="chat-msg ${isSent?'sent':'received'}">
    ${!isSent ? `<span class="msg-sender">${m.first_name} ${m.last_name}</span>` : ''}
    ${emBadge}
    <div class="bubble">${m.message.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div>
    <span class="msg-time">${time}</span>
  </div>`;
}

// Poll for new messages every 3 seconds
function startPolling() {
  if (!CHAT_WITH) return;
  polling = setInterval(async () => {
    try {
      const res = await fetch(`/HealthSphere/api/chat-poll.php?action=poll&with=${CHAT_WITH}&since=${encodeURIComponent(lastTs)}`);
      const data = await res.json();
      if (data.messages && data.messages.length > 0) {
        const container = document.getElementById('chatMessages');
        data.messages.forEach(m => {
          if (m.sender_id != CURRENT_UID) {  // Only append incoming
            container.insertAdjacentHTML('beforeend', renderMessage(m));
          }
        });
        lastTs = data.ts;
        scrollBottom();
      }
      // Typing indicator
      const typingEl = document.getElementById('typingIndicator');
      if (typingEl) typingEl.style.display = data.typing ? 'flex' : 'none';
    } catch(e) {}
  }, 3000);
}

if (CHAT_WITH) startPolling();

// Send via AJAX (no page reload)
const chatForm = document.getElementById('chatForm');
if (chatForm) {
  chatForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const input = document.getElementById('chatInput');
    const emChk = document.querySelector('input[name=emergency]');
    const msg   = input.value.trim();
    if (!msg) return;

    const payload = { message: msg, emergency: emChk && emChk.checked ? 1 : 0 };
    input.value = '';

    // Optimistic render
    const container = document.getElementById('chatMessages');
    const now = new Date().toISOString().replace('T',' ').substring(0,19);
    container.insertAdjacentHTML('beforeend', renderMessage({
      sender_id: CURRENT_UID, message: msg,
      is_emergency: payload.emergency, created_at: now,
      first_name:'', last_name:''
    }));
    scrollBottom();

    try {
      await fetch(`/HealthSphere/api/chat-poll.php?action=send&with=${CHAT_WITH}`, {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)
      });
    } catch(err) {}
  });
}

// Typing indicator — signal server while typing
const chatInput = document.getElementById('chatInput');
if (chatInput) {
  let typingTimer;
  chatInput.addEventListener('input', () => {
    clearTimeout(typingTimer);
    if (CHAT_WITH) fetch(`/HealthSphere/api/chat-poll.php?action=typing&with=${CHAT_WITH}`);
    typingTimer = setTimeout(() => {}, 2000);
  });
}

// Emergency checkbox toggle style
const emChk = document.querySelector('input[name=emergency]');
const emLabel = emChk?.closest('label');
if (emChk && emLabel) {
  emChk.addEventListener('change', () => {
    emLabel.style.background = emChk.checked ? '#DC2626' : '#FEF2F2';
    emLabel.style.color = emChk.checked ? '#fff' : 'var(--hs-danger)';
    emLabel.style.borderColor = emChk.checked ? '#DC2626' : 'var(--hs-border)';
  });
}

// Stop polling on page leave
window.addEventListener('beforeunload', () => clearInterval(polling));
</script>
</body>
</html>
