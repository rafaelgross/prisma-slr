/**
 * PRISMA-SLR — app.js
 * Funções globais compartilhadas por todas as páginas.
 */

'use strict';

/* ─── Escape Helpers ───────────────────────────────────────── */
function escHtml(str) {
  if (str == null) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function escAttr(str) {
  if (str == null) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

/* ─── Date Formatter ─────────────────────────────────────────── */
function formatDate(dateStr) {
  if (!dateStr) return '—';
  try {
    return new Date(dateStr).toLocaleDateString('pt-BR', {
      day: '2-digit', month: '2-digit', year: 'numeric',
    });
  } catch { return dateStr; }
}

function formatDateTime(dateStr) {
  if (!dateStr) return '—';
  try {
    return new Date(dateStr).toLocaleString('pt-BR');
  } catch { return dateStr; }
}

/* ─── Toast Notifications ───────────────────────────────────── */
let _toastContainer = null;
function _getToastContainer() {
  if (!_toastContainer) {
    _toastContainer = document.createElement('div');
    _toastContainer.id = 'toast-container';
    _toastContainer.style.cssText =
      'position:fixed;bottom:24px;right:24px;display:flex;flex-direction:column;gap:10px;z-index:9999;pointer-events:none';
    document.body.appendChild(_toastContainer);
  }
  return _toastContainer;
}

function showToast(message, type = 'info') {
  const container = _getToastContainer();
  const typeMap = {
    success: { icon: 'fa-circle-check',    color: 'var(--accent-green)' },
    error:   { icon: 'fa-circle-xmark',    color: 'var(--accent-red)'   },
    warning: { icon: 'fa-triangle-exclamation', color: '#ffd43b'         },
    info:    { icon: 'fa-circle-info',     color: 'var(--primary)'       },
  };
  const { icon, color } = typeMap[type] || typeMap.info;

  const toast = document.createElement('div');
  toast.style.cssText = `
    display:flex;align-items:center;gap:10px;
    background:var(--bg-card);border:1px solid ${color};
    border-left:4px solid ${color};border-radius:8px;
    padding:12px 16px;min-width:240px;max-width:360px;
    box-shadow:0 8px 24px rgba(0,0,0,.4);
    color:var(--text-primary);font-size:.85rem;
    pointer-events:all;opacity:0;
    transform:translateX(20px);transition:all .3s ease;
  `.trim();
  toast.innerHTML = `<i class="fa ${icon}" style="color:${color};flex-shrink:0"></i><span>${escHtml(message)}</span>`;
  container.appendChild(toast);

  // Animate in
  requestAnimationFrame(() => {
    toast.style.opacity = '1';
    toast.style.transform = 'translateX(0)';
  });

  // Auto-remove
  const ttl = type === 'error' ? 5000 : 3000;
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(20px)';
    setTimeout(() => toast.remove(), 300);
  }, ttl);
}

/* ─── Project Switcher ──────────────────────────────────────── */
function switchProject(id) {
  const url = new URL(window.location.href);
  if (id) {
    url.searchParams.set('project_id', id);
  } else {
    url.searchParams.delete('project_id');
  }
  // Keep current page if it makes sense
  window.location.href = url.toString();
}

/* ─── Confirm Dialog ─────────────────────────────────────────── */
function confirmDialog(message, onConfirm, opts = {}) {
  const modal = document.getElementById('confirm-modal');
  if (!modal) {
    // fallback to native confirm
    if (confirm(message)) onConfirm();
    return;
  }
  document.getElementById('confirm-message').textContent = message;
  document.getElementById('confirm-btn').textContent     = opts.confirmLabel || 'Confirmar';
  document.getElementById('confirm-btn').className       = `btn ${opts.danger ? 'btn-danger' : 'btn-primary'}`;
  document.getElementById('confirm-title').textContent   = opts.title || 'Confirmação';
  _pendingConfirm = onConfirm;
  modal.classList.add('open');
}

let _pendingConfirm = null;
function execConfirm() {
  const modal = document.getElementById('confirm-modal');
  modal && modal.classList.remove('open');
  if (_pendingConfirm) { _pendingConfirm(); _pendingConfirm = null; }
}
function cancelConfirm() {
  const modal = document.getElementById('confirm-modal');
  modal && modal.classList.remove('open');
  _pendingConfirm = null;
}

/* ─── Debounce ───────────────────────────────────────────────── */
function debounce(fn, delay = 350) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}

/* ─── Number Formatter ───────────────────────────────────────── */
function fmtNum(n) {
  if (n == null) return '0';
  return Number(n).toLocaleString('pt-BR');
}

/* ─── Copy to Clipboard ──────────────────────────────────────── */
async function copyToClipboard(text) {
  try {
    await navigator.clipboard.writeText(text);
    showToast('Copiado para a área de transferência', 'success');
  } catch {
    showToast('Erro ao copiar', 'error');
  }
}

/* ─── Modal global: close on overlay click ───────────────────── */
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
  }
});

/* ─── Keyboard shortcuts ─────────────────────────────────────── */
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
  }
});

/* ─── Sidebar active state ───────────────────────────────────── */
(function setActiveSidebarItem() {
  const page = new URLSearchParams(window.location.search).get('page') || 'dashboard';
  document.querySelectorAll('.nav-item').forEach(el => {
    el.classList.toggle('active', el.dataset.page === page);
  });
})();

/* ─── Auto-highlight table rows ──────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  // Add hover row highlighting dynamically
  document.addEventListener('mouseover', (e) => {
    const row = e.target.closest('tr');
    if (row && row.closest('tbody')) row.style.background = 'rgba(255,255,255,.03)';
  });
  document.addEventListener('mouseout', (e) => {
    const row = e.target.closest('tr');
    if (row && row.closest('tbody')) row.style.background = '';
  });
});

console.info('%cPRISMA-SLR v1.0 carregado', 'color:#00D4FF;font-weight:bold');
