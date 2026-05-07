// HealthSphere — Main JavaScript

document.addEventListener('DOMContentLoaded', () => {

  // ── Sidebar mobile toggle ──────────────────────────────
  const menuToggle = document.getElementById('menuToggle');
  const sidebar    = document.querySelector('.hs-sidebar');
  if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', e => {
      if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    });
  }

  // ── Water glasses tracker ──────────────────────────────
  document.querySelectorAll('.water-glass').forEach((glass, i, all) => {
    glass.addEventListener('click', () => {
      const count = i + 1;
      all.forEach((g, j) => g.classList.toggle('filled', j < count));
      const input = document.getElementById('waterCount');
      if (input) input.value = count;
    });
  });

  // ── Time slot selector ─────────────────────────────────
  document.querySelectorAll('.time-slot:not(.booked)').forEach(slot => {
    slot.addEventListener('click', () => {
      document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
      slot.classList.add('selected');
      const inp = document.getElementById('selectedTime');
      if (inp) inp.value = slot.dataset.time || slot.textContent.trim();
    });
  });

  // ── Mark notification read ─────────────────────────────
  document.querySelectorAll('.notif-item.unread').forEach(item => {
    item.addEventListener('click', () => item.classList.remove('unread'));
  });

  // ── Auto-dismiss toasts ────────────────────────────────
  document.querySelectorAll('.hs-toast').forEach(toast => {
    setTimeout(() => toast.remove(), 5000);
  });

  // ── Animate stat values ────────────────────────────────
  document.querySelectorAll('[data-count]').forEach(el => {
    const target = parseFloat(el.dataset.count);
    const duration = 1200;
    const start = performance.now();
    const from = 0;
    const isFloat = target % 1 !== 0;
    const update = now => {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const ease = 1 - Math.pow(1 - progress, 3);
      el.textContent = isFloat
        ? (from + (target - from) * ease).toFixed(1)
        : Math.round(from + (target - from) * ease);
      if (progress < 1) requestAnimationFrame(update);
    };
    requestAnimationFrame(update);
  });

  // ── Progress circle render ─────────────────────────────
  document.querySelectorAll('.progress-circle').forEach(wrap => {
    const pct   = parseFloat(wrap.dataset.pct || 0);
    const color = wrap.dataset.color || '#1565C0';
    const size  = parseInt(wrap.dataset.size || 80);
    const stroke = parseInt(wrap.dataset.stroke || 6);
    const r = (size - stroke) / 2;
    const circ = 2 * Math.PI * r;
    const offset = circ - (pct / 100) * circ;

    wrap.innerHTML = `
      <svg width="${size}" height="${size}">
        <circle class="circle-bg" cx="${size/2}" cy="${size/2}" r="${r}" stroke-width="${stroke}"/>
        <circle class="circle-fill" cx="${size/2}" cy="${size/2}" r="${r}" stroke="${color}"
          stroke-width="${stroke}" stroke-dasharray="${circ}"
          stroke-dashoffset="${circ}" style="transition:stroke-dashoffset 1.2s ease"/>
      </svg>
      <div class="circle-text">
        <span class="circle-pct">${pct}%</span>
        <span class="circle-lbl">${wrap.dataset.label || ''}</span>
      </div>`;
    setTimeout(() => {
      wrap.querySelector('.circle-fill').style.strokeDashoffset = offset;
    }, 100);
  });

  // ── Macro progress bars animate ─────────────────────────
  document.querySelectorAll('.macro-fill').forEach(bar => {
    const w = bar.dataset.width || '0%';
    setTimeout(() => { bar.style.width = w; }, 200);
  });

  // ── Toast helper (global) ──────────────────────────────
  window.showToast = (msg, type = 'info', icon = '') => {
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle' };
    const toast = document.createElement('div');
    toast.className = `hs-toast ${type}`;
    toast.innerHTML = `<i class="fas ${icon || icons[type] || icons.info}"></i> ${msg}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
  };

  // ── Chat send ──────────────────────────────────────────
  const chatForm  = document.getElementById('chatForm');
  const chatInput = document.getElementById('chatInput');
  const chatMsgs  = document.getElementById('chatMessages');
  if (chatForm && chatInput && chatMsgs) {
    chatForm.addEventListener('submit', e => {
      e.preventDefault();
      const text = chatInput.value.trim();
      if (!text) return;
      const now = new Date();
      const time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      const msg = document.createElement('div');
      msg.className = 'chat-msg sent';
      msg.innerHTML = `<div class="bubble">${escHtml(text)}</div><span class="msg-time">${time}</span>`;
      chatMsgs.appendChild(msg);
      chatInput.value = '';
      chatMsgs.scrollTop = chatMsgs.scrollHeight;
      showToast('Message sent', 'success');
    });
  }

  // ── Appointment form doctor preview ───────────────────
  const docSelect = document.getElementById('doctorSelect');
  const docPreview = document.getElementById('doctorPreview');
  if (docSelect && docPreview) {
    docSelect.addEventListener('change', () => {
      docPreview.style.display = docSelect.value ? 'block' : 'none';
    });
  }

  // ── Active nav link ────────────────────────────────────
  const path = window.location.pathname;
  document.querySelectorAll('.hs-sidebar-nav .nav-link').forEach(link => {
    if (link.getAttribute('href') && path.includes(link.getAttribute('href').replace(/^.*\//, '').split('?')[0])) {
      link.classList.add('active');
    }
  });
});

function escHtml(str) {
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
