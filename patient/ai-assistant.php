<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/ai.php';
requireRole('patient');
$user = getCurrentUser();
$uid  = $user['id'];

// Patient context for sidebar
$u = $pdo->prepare("SELECT * FROM users WHERE id=?"); $u->execute([$uid]); $u = $u->fetch();
$meds = $pdo->prepare("SELECT * FROM prescriptions WHERE patient_id=? AND is_active=1"); $meds->execute([$uid]); $meds = $meds->fetchAll();
$allergies = $pdo->prepare("SELECT * FROM allergies WHERE patient_id=? AND is_active=1"); $allergies->execute([$uid]); $allergies = $allergies->fetchAll();
$metrics = $pdo->prepare("SELECT * FROM health_metrics WHERE patient_id=? ORDER BY metric_date DESC LIMIT 1"); $metrics->execute([$uid]); $metrics = $metrics->fetch();
$notifCount = getUnreadCount($pdo, $uid);
$msgCount   = getUnreadMessages($pdo, $uid);
$hasApiKey  = defined('ANTHROPIC_API_KEY') && ANTHROPIC_API_KEY !== '';

$quickQuestions = [
    ['icon'=>'💊','text'=>'What are the side effects of Amlodipine?'],
    ['icon'=>'🩺','text'=>'My blood pressure is 135/85 — is that high?'],
    ['icon'=>'😴','text'=>'How can I improve my sleep quality?'],
    ['icon'=>'🧂','text'=>'How do I reduce my sodium intake?'],
    ['icon'=>'❤️','text'=>'My heart rate is 95 bpm — should I be concerned?'],
    ['icon'=>'🏃','text'=>'What exercise is best for high blood pressure?'],
    ['icon'=>'😰','text'=>'I feel anxious — what can I do?'],
    ['icon'=>'💧','text'=>'How much water should I drink daily?'],
    ['icon'=>'📅','text'=>'When should I book a GP appointment?'],
    ['icon'=>'🌡️','text'=>'I have a fever of 38.5°C — what should I do?'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>AI Health Assistant — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
/* Full-height layout */
html,body { height:100%; overflow:hidden; }
.hs-main  { height:100vh; overflow:hidden; }
.hs-content { padding:0 !important; height:calc(100vh - 64px); display:flex; overflow:hidden; }

/* ── Left context panel ──────────────────────────────────────── */
.ai-context {
  width:280px; flex-shrink:0;
  background:#fff; border-right:1px solid var(--hs-border);
  display:flex; flex-direction:column; overflow-y:auto;
}
.ctx-section { padding:14px 16px; border-bottom:1px solid var(--hs-border); }
.ctx-title   { font-size:10px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; color:var(--hs-muted); margin-bottom:10px; }
.ctx-item {
  display:flex; align-items:center; gap:8px;
  padding:7px 0; border-bottom:1px solid var(--hs-bg); font-size:12.5px;
}
.ctx-item:last-child { border-bottom:none; }
.ctx-item .ci-label { color:var(--hs-muted); font-size:11px; min-width:60px; }
.ctx-item .ci-val   { font-weight:600; color:var(--hs-navy); }
.ctx-item .ci-icon  { font-size:16px; flex-shrink:0; }

/* Quick questions */
.qq-btn {
  width:100%; text-align:left;
  display:flex; align-items:flex-start; gap:8px;
  padding:8px 10px; border-radius:8px; border:none;
  background:var(--hs-bg); cursor:pointer; margin-bottom:5px;
  font-size:12px; font-family:inherit; color:var(--hs-text);
  transition:var(--transition); line-height:1.4;
}
.qq-btn:hover { background:var(--hs-off-white); border-left:3px solid var(--hs-blue); color:var(--hs-navy); }
.qq-btn .qq-icon { font-size:14px; flex-shrink:0; margin-top:1px; }

/* ── Main chat area ──────────────────────────────────────────── */
.ai-chat { flex:1; display:flex; flex-direction:column; background:#F4F8FF; overflow:hidden; }

/* Chat messages */
.chat-messages {
  flex:1; overflow-y:auto; padding:24px 32px;
  display:flex; flex-direction:column; gap:18px;
}

/* Message bubbles */
.msg { display:flex; gap:12px; max-width:76%; animation:msgIn .25s ease; }
@keyframes msgIn { from{opacity:0;transform:translateY(10px)} }
.msg.user { align-self:flex-end; flex-direction:row-reverse; }
.msg.ai   { align-self:flex-start; }

.msg-avatar {
  width:36px; height:36px; border-radius:50%; flex-shrink:0;
  display:flex; align-items:center; justify-content:center; font-size:16px;
}
.msg.ai   .msg-avatar { background:linear-gradient(135deg,#0A1F44,#1565C0); color:#fff; font-size:14px; }
.msg.user .msg-avatar { background:var(--hs-blue); color:#fff; font-size:13px; font-weight:700; }

.msg-content { display:flex; flex-direction:column; gap:4px; }
.msg.user .msg-content { align-items:flex-end; }

.bubble {
  padding:12px 16px; border-radius:16px; font-size:13.5px; line-height:1.7;
  max-width:100%;
}
.msg.user .bubble {
  background:var(--hs-blue); color:#fff;
  border-radius:16px 16px 4px 16px;
}
.msg.ai .bubble {
  background:#fff; color:var(--hs-text);
  border:1px solid var(--hs-border);
  border-radius:16px 16px 16px 4px;
  box-shadow:0 2px 8px rgba(10,31,68,.06);
}

/* Markdown-like formatting in AI bubble */
.msg.ai .bubble strong { color:var(--hs-navy); }
.msg.ai .bubble br+br  { display:block; content:''; margin:.3em 0; }

.msg-time { font-size:11px; color:var(--hs-muted); }
.msg-source { font-size:10px; color:var(--hs-muted); display:flex; align-items:center; gap:4px; margin-top:2px; }

/* Typing indicator */
.typing-wrap { display:flex; gap:12px; align-items:flex-end; }
.typing-bubble {
  background:#fff; border:1px solid var(--hs-border);
  border-radius:16px 16px 16px 4px;
  padding:14px 18px; display:flex; gap:5px; align-items:center;
  box-shadow:0 2px 8px rgba(10,31,68,.06);
}
.typing-dot {
  width:7px; height:7px; border-radius:50%; background:var(--hs-muted);
  animation:typingDot 1.4s ease-in-out infinite;
}
.typing-dot:nth-child(2) { animation-delay:.2s; }
.typing-dot:nth-child(3) { animation-delay:.4s; }
@keyframes typingDot {
  0%,60%,100% { transform:translateY(0); opacity:.4; }
  30%          { transform:translateY(-6px); opacity:1; }
}

/* Welcome screen */
.welcome-screen {
  flex:1; display:flex; flex-direction:column;
  align-items:center; justify-content:center;
  padding:40px; text-align:center;
}
.welcome-orb {
  width:88px; height:88px; border-radius:50%;
  background:linear-gradient(135deg,#0A1F44,#1565C0);
  display:flex; align-items:center; justify-content:center;
  font-size:36px; color:#fff; margin:0 auto 20px;
  box-shadow:0 12px 40px rgba(21,101,192,.3);
  animation:orbPulse 3s ease-in-out infinite;
}
@keyframes orbPulse {
  0%,100%{ box-shadow:0 12px 40px rgba(21,101,192,.3); }
  50%    { box-shadow:0 12px 60px rgba(21,101,192,.5); }
}
.welcome-title { font-size:22px; font-weight:800; color:var(--hs-navy); margin-bottom:8px; }
.welcome-sub   { font-size:14px; color:var(--hs-muted); margin-bottom:28px; line-height:1.6; max-width:420px; }
.capability-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; width:100%; max-width:480px; }
.cap-card {
  background:#fff; border:1px solid var(--hs-border); border-radius:12px;
  padding:14px; text-align:left; display:flex; gap:10px; align-items:flex-start;
}
.cap-card .cap-icon { font-size:20px; flex-shrink:0; }
.cap-card .cap-text { font-size:12.5px; color:var(--hs-text); font-weight:500; }
.cap-card .cap-title { font-size:13px; font-weight:700; color:var(--hs-navy); margin-bottom:2px; }

/* Input bar */
.chat-input-area {
  border-top:1px solid var(--hs-border);
  background:#fff; padding:14px 24px 18px;
  flex-shrink:0;
}
.disclaimer-bar {
  display:flex; align-items:center; gap:6px;
  font-size:11px; color:var(--hs-muted);
  margin-bottom:10px; text-align:center; justify-content:center;
}
.input-row { display:flex; gap:10px; align-items:flex-end; }
.input-wrap { flex:1; position:relative; }
#chatInput {
  width:100%;
  border:1.5px solid var(--hs-border);
  border-radius:16px;
  padding:13px 16px;
  font-size:14px; font-family:inherit;
  resize:none; outline:none; line-height:1.5;
  max-height:120px; overflow-y:auto;
  transition:border-color .2s;
}
#chatInput:focus { border-color:var(--hs-blue); box-shadow:0 0 0 3px rgba(21,101,192,.1); }
.send-btn {
  width:46px; height:46px; border-radius:50%;
  background:var(--hs-blue); color:#fff; border:none;
  display:flex; align-items:center; justify-content:center;
  cursor:pointer; font-size:18px; flex-shrink:0;
  transition:var(--transition);
}
.send-btn:hover { background:#1251A0; transform:scale(1.08); }
.send-btn:disabled { background:var(--hs-border); cursor:not-allowed; transform:none; }
.clear-btn {
  padding:8px 14px; border-radius:8px; border:1px solid var(--hs-border);
  background:#fff; cursor:pointer; font-size:12px; font-weight:600;
  color:var(--hs-muted); display:flex; align-items:center; gap:5px;
  transition:var(--transition);
}
.clear-btn:hover { border-color:var(--hs-danger); color:var(--hs-danger); }

/* API key notice */
.api-notice {
  background:linear-gradient(135deg,#FEF3C7,#FFF7ED);
  border:1px solid #F59E0B; border-radius:10px;
  padding:10px 14px; margin-bottom:10px;
  font-size:12px; color:#92400E;
  display:flex; align-items:flex-start; gap:8px;
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">

  <!-- Topbar -->
  <div class="hs-topbar">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#0A1F44,#1565C0);display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;">
        <i class="fas fa-robot"></i>
      </div>
      <div>
        <div class="page-title">HealthSphere AI Assistant</div>
        <div class="page-subtitle" style="display:flex;align-items:center;gap:6px;">
          <span style="width:7px;height:7px;border-radius:50%;background:#22C55E;display:inline-block;"></span>
          Powered by Claude AI &middot; Medical knowledge base
        </div>
      </div>
    </div>
    <div class="topbar-actions">
      <button onclick="clearChat()" class="btn-hs btn-outline-hs btn-sm-hs">
        <i class="fas fa-trash"></i> Clear Chat
      </button>
    </div>
  </div>

  <div class="hs-content">

    <!-- LEFT: Patient Context -->
    <div class="ai-context">

      <!-- Patient summary -->
      <div class="ctx-section">
        <div class="ctx-title">Your Health Summary</div>
        <div class="ctx-item">
          <span class="ci-icon">🩸</span>
          <span class="ci-label">Blood</span>
          <span class="ci-val"><?= e($u['blood_type'] ?? 'N/A') ?></span>
        </div>
        <?php if ($metrics): ?>
        <div class="ctx-item">
          <span class="ci-icon">💓</span>
          <span class="ci-label">BP</span>
          <span class="ci-val"><?= $metrics['blood_pressure_systolic'].'/'.$metrics['blood_pressure_diastolic'] ?> mmHg</span>
        </div>
        <div class="ctx-item">
          <span class="ci-icon">❤️</span>
          <span class="ci-label">Heart</span>
          <span class="ci-val"><?= $metrics['heart_rate'] ?> bpm</span>
        </div>
        <div class="ctx-item">
          <span class="ci-icon">🫁</span>
          <span class="ci-label">SpO₂</span>
          <span class="ci-val"><?= $metrics['spo2'] ?>%</span>
        </div>
        <div class="ctx-item">
          <span class="ci-icon">🌙</span>
          <span class="ci-label">Sleep</span>
          <span class="ci-val"><?= $metrics['sleep_hours'] ?>h</span>
        </div>
        <?php endif; ?>
      </div>

      <!-- Active meds -->
      <?php if ($meds): ?>
      <div class="ctx-section">
        <div class="ctx-title">Active Medications</div>
        <?php foreach (array_slice($meds, 0, 4) as $m): ?>
        <div class="ctx-item" style="cursor:pointer;" onclick="askAbout('Tell me about <?= addslashes($m['medication_name']) ?>')">
          <span class="ci-icon">💊</span>
          <div>
            <div style="font-weight:600;font-size:12.5px;color:var(--hs-navy);"><?= e($m['medication_name']) ?></div>
            <div style="font-size:11px;color:var(--hs-muted);"><?= e($m['dosage']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Allergies -->
      <?php if ($allergies): ?>
      <div class="ctx-section">
        <div class="ctx-title">Allergies</div>
        <?php foreach (array_slice($allergies, 0, 3) as $a): ?>
        <div class="ctx-item">
          <span class="ci-icon">⚠️</span>
          <div>
            <div style="font-weight:600;font-size:12.5px;color:var(--hs-navy);"><?= e($a['allergen']) ?></div>
            <div style="font-size:11px;color:<?= $a['severity']==='severe'?'var(--hs-danger)':'var(--hs-muted)' ?>;text-transform:capitalize;"><?= $a['severity'] ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Quick questions -->
      <div class="ctx-section" style="flex:1;">
        <div class="ctx-title">Quick Questions</div>
        <?php foreach ($quickQuestions as $qq): ?>
        <button class="qq-btn" onclick="askAbout('<?= addslashes($qq['text']) ?>')">
          <span class="qq-icon"><?= $qq['icon'] ?></span>
          <span><?= e($qq['text']) ?></span>
        </button>
        <?php endforeach; ?>
      </div>

    </div><!-- /ai-context -->

    <!-- MAIN: Chat -->
    <div class="ai-chat">

      <!-- Messages container -->
      <div id="messagesContainer" class="chat-messages">

        <!-- Welcome (shown until first message) -->
        <div id="welcomeScreen" class="welcome-screen">
          <div class="welcome-orb"><i class="fas fa-robot"></i></div>
          <div class="welcome-title">Hello, <?= e($user['first_name']) ?>! 👋</div>
          <div class="welcome-sub">
            I'm your HealthSphere AI health assistant. I can answer your health questions, explain your metrics, help with medications, and more — using your personal health data for personalised advice.
          </div>
          <div class="capability-grid">
            <?php
            $caps = [
              ['💊','Medications','Explain medications & side effects'],
              ['📊','Your Metrics','Interpret your BP, HR, SpO₂, sleep'],
              ['🥗','Diet & Lifestyle','NHS nutrition & exercise advice'],
              ['🩺','Symptoms','Understand symptoms & when to act'],
              ['🧠','Mental Health','Wellbeing support & NHS resources'],
              ['📅','Appointments','When & how to book your GP'],
            ];
            foreach ($caps as [$icon, $title, $text]):
            ?>
            <div class="cap-card">
              <span class="cap-icon"><?= $icon ?></span>
              <div>
                <div class="cap-title"><?= $title ?></div>
                <div class="cap-text"><?= $text ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div><!-- /chat-messages -->

      <!-- Input area -->
      <div class="chat-input-area">


        <div class="disclaimer-bar">
          <i class="fas fa-shield-alt" style="color:var(--hs-blue);"></i>
          AI health information only — not a replacement for medical diagnosis. Always consult your doctor.
        </div>

        <div class="input-row">
          <div class="input-wrap">
            <textarea id="chatInput" rows="1"
              placeholder="Ask me anything about your health... (e.g. 'What causes high blood pressure?')"
              onkeydown="handleKey(event)"
              oninput="autoResize(this)"></textarea>
          </div>
          <button class="send-btn" id="sendBtn" onclick="sendMessage()" title="Send (Enter)">
            <i class="fas fa-paper-plane" style="font-size:16px;margin-left:2px;"></i>
          </button>
        </div>

      </div><!-- /input area -->

    </div><!-- /ai-chat -->

  </div>
</div>

<script>
const PATIENT_NAME = '<?= e($user['first_name']) ?>';
let chatHistory   = [];   // [{role, content}]
let isLoading     = false;

// ── DOM helpers ────────────────────────────────────────────────────
const container  = document.getElementById('messagesContainer');
const welcome    = document.getElementById('welcomeScreen');
const input      = document.getElementById('chatInput');
const sendBtn    = document.getElementById('sendBtn');

function now() {
  return new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
}

function escHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Render markdown-lite formatting ───────────────────────────────
function formatAI(text) {
  return text
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/🚨 \*\*(.+?)\*\*/g, '<span style="color:#DC2626;font-weight:800;">🚨 $1</span>')
    .replace(/\n\n/g, '<br><br>')
    .replace(/\n- /g, '<br>• ')
    .replace(/\n(\d+)\. /g, '<br>$1. ')
    .replace(/\n/g, '<br>');
}

// ── Append a message bubble ────────────────────────────────────────
function appendMessage(role, content, meta = '') {
  if (welcome && welcome.parentNode) welcome.remove();

  const wrap = document.createElement('div');
  wrap.className = 'msg ' + (role === 'user' ? 'user' : 'ai');

  const initials = PATIENT_NAME.charAt(0).toUpperCase();
  const avatarHTML = role === 'user'
    ? `<div class="msg-avatar">${initials}</div>`
    : `<div class="msg-avatar"><i class="fas fa-robot"></i></div>`;

  const bubbleContent = role === 'user'
    ? escHtml(content)
    : formatAI(content);

  wrap.innerHTML = `
    ${avatarHTML}
    <div class="msg-content">
      <div class="bubble">${bubbleContent}</div>
      <div class="msg-time">${now()}${meta ? ' &nbsp;·&nbsp; '+meta : ''}</div>
    </div>`;

  container.appendChild(wrap);
  container.scrollTop = container.scrollHeight;
  return wrap;
}

// ── Typing indicator ───────────────────────────────────────────────
let typingEl = null;
function showTyping() {
  typingEl = document.createElement('div');
  typingEl.className = 'msg ai';
  typingEl.id = 'typingIndicator';
  typingEl.innerHTML = `
    <div class="msg-avatar"><i class="fas fa-robot"></i></div>
    <div class="msg-content">
      <div class="typing-bubble">
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
      </div>
      <div class="msg-time">HealthSphere AI is thinking...</div>
    </div>`;
  container.appendChild(typingEl);
  container.scrollTop = container.scrollHeight;
}
function hideTyping() {
  if (typingEl) { typingEl.remove(); typingEl = null; }
}

// ── Text animation for AI response ────────────────────────────────
function animateText(bubble, html) {
  bubble.innerHTML = '';
  const words = html.split(' ');
  let i = 0;
  const interval = setInterval(() => {
    if (i >= words.length) { clearInterval(interval); bubble.innerHTML = html; return; }
    bubble.innerHTML = words.slice(0, i+1).join(' ') + (i < words.length-1 ? '...' : '');
    container.scrollTop = container.scrollHeight;
    i += 3; // Speed up by doing 3 words at a time
  }, 18);
}

// ── Send message ───────────────────────────────────────────────────
async function sendMessage() {
  const text = input.value.trim();
  if (!text || isLoading) return;

  isLoading = true;
  sendBtn.disabled = true;
  input.value = '';
  autoResize(input);

  // Append user bubble
  appendMessage('user', text);

  // Add to history
  chatHistory.push({ role:'user', content:text });

  // Show typing
  showTyping();

  try {
    const res = await fetch('/HealthSphere/api/ai-assistant.php', {
      method:  'POST',
      headers: {'Content-Type':'application/json'},
      body:    JSON.stringify({ message:text, history:chatHistory.slice(-10) }),
    });

    const data = await res.json();
    hideTyping();

    if (data.error) throw new Error(data.error);

    const reply  = data.reply || 'I could not generate a response. Please try again.';
    const source = data.source === 'claude'
      ? `<i class="fas fa-magic" style="color:var(--hs-blue);"></i> Claude AI`
      : `<i class="fas fa-database" style="color:var(--hs-muted);"></i> Built-in`;

    const msgEl = appendMessage('ai', '', source);
    const bubble = msgEl.querySelector('.bubble');
    animateText(bubble, formatAI(reply));

    chatHistory.push({ role:'assistant', content:reply });

  } catch (err) {
    hideTyping();
    appendMessage('ai', '⚠️ Sorry, I encountered an error. Please try again or contact your doctor directly.\n\n**Error:** ' + (err.message || 'Connection issue'));
  }

  isLoading = false;
  sendBtn.disabled = false;
  input.focus();
}

// ── Quick question helper ──────────────────────────────────────────
function askAbout(question) {
  input.value = question;
  autoResize(input);
  sendMessage();
}

// ── Keyboard shortcut (Enter = send, Shift+Enter = newline) ───────
function handleKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
}

// ── Auto-resize textarea ───────────────────────────────────────────
function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

// ── Clear chat ─────────────────────────────────────────────────────
function clearChat() {
  if (!chatHistory.length) return;
  if (!confirm('Clear chat history?')) return;
  chatHistory = [];
  container.innerHTML = '';
  // Re-add welcome screen
  const ws = document.createElement('div');
  ws.id = 'welcomeScreen';
  ws.className = 'welcome-screen';
  ws.innerHTML = `<div class="welcome-orb"><i class="fas fa-robot"></i></div><div class="welcome-title">Chat cleared</div><div class="welcome-sub">Ask me anything about your health.</div>`;
  container.appendChild(ws);
}

// Focus input on load
input.focus();
</script>
</body>
</html>
