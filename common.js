/* ============================================================
   common.js v6 – Auth · i18n · Shared Utilities
   ============================================================ */
'use strict';

/* ── file:// guard ───────────────────────────────────────────── */
(function () {
  if (location.protocol === 'file:' && !location.pathname.endsWith('login.html')) {
    document.addEventListener('DOMContentLoaded', function () {
      document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;background:#0a1628;color:#fff;font-family:sans-serif;text-align:center;padding:20px"><div><div style="font-size:3rem;margin-bottom:16px">⚠</div><h2 style="margin-bottom:10px">Wrong way to open this app</h2><p>Open via <strong>http://localhost/comboni/</strong></p></div></div>';
    });
  }
})();

/* ── i18n System ─────────────────────────────────────────────── */
const i18n = {
  lang: localStorage.getItem('cl_lang') || 'en',
  data: {},
  async load(lang) {
    try {
      const r = await fetch('assets/i18n/' + lang + '.json?v=6');
      this.data = await r.json();
      this.lang = lang;
      localStorage.setItem('cl_lang', lang);
      document.documentElement.lang = lang;
      this.apply();
    } catch (e) { console.warn('i18n load failed:', e); }
  },
  t(key) { return this.data[key] || key; },
  apply() {
    document.querySelectorAll('[data-i18n]').forEach(function (el) {
      var key = el.getAttribute('data-i18n');
      var val = i18n.t(key);
      if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
        if (el.getAttribute('data-i18n-placeholder') || el.hasAttribute('placeholder')) el.placeholder = val;
      } else { el.textContent = val; }
    });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(function (el) { el.placeholder = i18n.t(el.getAttribute('data-i18n-placeholder')); });
    document.querySelectorAll('[data-i18n-title]').forEach(function (el) { el.title = i18n.t(el.getAttribute('data-i18n-title')); });
  },
  setLang(lang) { this.load(lang); }
};

/* ── Auth ────────────────────────────────────────────────────── */
const Auth = {
  role: null,
  async check() {
    if (location.pathname.endsWith('login.html') || location.pathname.endsWith('developer.html')) return true;
    try {
      var r = await fetch('api/auth.php?action=check');
      var d = await r.json();
      this.role = d.role;
      if (!d.role || d.role === 'guest') { location.href = 'login.html'; return false; }
      document.body.dataset.role = d.role;
      return d.role;
    } catch (e) { return false; }
  },
  async logout() {
    await fetch('api/auth.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'logout' }) });
    location.href = 'login.html';
  }
};

/* ── API caller ──────────────────────────────────────────────── */
const API = {
  async call(url, opts) {
    opts = opts || {};
    var method = opts.method || 'GET', body = opts.body || null, params = opts.params || {};
    var qs = new URLSearchParams(params).toString();
    var fullUrl = qs ? url + '?' + qs : url;
    var fetchOpts = { method: method, headers: { 'Content-Type': 'application/json' } };
    if (body && method !== 'GET') fetchOpts.body = JSON.stringify(body);
    var res;
    try { res = await fetch(fullUrl, fetchOpts); }
    catch (netErr) {
      var msg = 'Cannot reach server — Is Apache running?';
      Toast.show(msg, 'error', 6000); throw new Error(msg);
    }
    var data;
    try { data = await res.json(); }
    catch (e) {
      var t = await res.clone().text().catch(function () { return ''; });
      var preview = t.replace(/<[^>]+>/g, '').trim().slice(0, 120);
      Toast.show('PHP error: ' + preview, 'error', 8000); throw new Error('Parse error: ' + preview);
    }
    if (!res.ok) {
      if (res.status === 401 || res.status === 403) { if (!url.includes('auth.php')) location.href = 'login.html'; }
      var errMsg = data.error || ('HTTP ' + res.status);
      Toast.show(errMsg, 'error'); throw new Error(errMsg);
    }
    return data;
  },
  get: function (url, params) { return API.call(url, { method: 'GET', params: params || {} }); },
  post: function (url, body) { return API.call(url, { method: 'POST', body: body }); },
  put: function (url, body, params) { return API.call(url, { method: 'PUT', body: body, params: params || {} }); },
  delete: function (url, params) { return API.call(url, { method: 'DELETE', params: params || {} }); },
};

/* ── Toast ───────────────────────────────────────────────────── */
const Toast = {
  container: null,
  init() {
    if (!this.container) {
      this.container = document.createElement('div');
      this.container.id = 'toast-container';
      document.body.appendChild(this.container);
    }
  },
  show(msg, type, duration) {
    type = type || 'info'; duration = duration || 3500; this.init();
    var el = document.createElement('div'); el.className = 'toast ' + type;
    var icons = { success: 'check_circle', error: 'error', warning: 'warning', info: 'info' };
    el.innerHTML = '<span class="material-icons">' + (icons[type] || 'info') + '</span><span>' + msg + '</span>';
    this.container.appendChild(el);
    setTimeout(function () { el.classList.add('toast-exit'); el.addEventListener('animationend', function () { el.remove(); }); }, duration);
  }
};

/* ── Unsaved Changes ─────────────────────────────────────────── */
const UnsavedTracker = {
  count: 0, badge: null, saveHandlers: [],
  init() {
    this.badge = document.getElementById('unsaved-badge');
    var self = this;
    window.addEventListener('beforeunload', function (e) {
      if (self.count > 0) { e.preventDefault(); e.returnValue = ''; }
    });
  },
  mark() { this.count++; this._update(); },
  clear() { this.count = Math.max(0, this.count - 1); this._update(); },
  clearAll() { this.count = 0; this._update(); },
  _update() {
    if (!this.badge) return;
    if (this.count > 0) {
      this.badge.style.display = 'flex'; this.badge.classList.remove('saved');
      this.badge.innerHTML = '<span class="material-icons" style="font-size:14px">edit</span>' + this.count + ' unsaved';
    } else {
      this.badge.classList.add('saved');
      this.badge.innerHTML = '<span class="material-icons" style="font-size:14px">check</span>Saved';
      var b = this.badge; setTimeout(function () { if (b) b.style.display = 'none'; }, 2000);
    }
  },
  onSaveAll(fn) { this.saveHandlers.push(fn); },
  async saveAll() { for (var i = 0; i < this.saveHandlers.length; i++) await this.saveHandlers[i](); this.clearAll(); Toast.show('All changes saved', 'success'); }
};

/* ── Modal ───────────────────────────────────────────────────── */
const Modal = {
  open(id) { var el = document.getElementById(id); if (el) el.classList.add('open'); },
  close(id) { var el = document.getElementById(id); if (el) el.classList.remove('open'); },
  create(opts) {
    var id = opts.id, title = opts.title || '', icon = opts.icon || 'info',
      body = opts.body || '', onConfirm = opts.onConfirm || null, confirmLabel = opts.confirmLabel || 'Confirm';
    var el = document.getElementById(id);
    if (!el) { el = document.createElement('div'); el.className = 'modal-overlay'; el.id = id; document.body.appendChild(el); }
    el.innerHTML = '<div class="modal"><div class="modal-header"><h3><span class="material-icons">' + icon + '</span>' + title + '</h3>' +
      '<button class="modal-close" onclick="Modal.close(\'' + id + '\')"><span class="material-icons">close</span></button></div>' +
      '<div class="modal-body">' + body + '</div>' +
      (onConfirm ? '<div class="modal-footer"><button class="btn btn-secondary" onclick="Modal.close(\'' + id + '\')">Cancel</button>' +
        '<button class="btn btn-primary" id="' + id + '-confirm">' + confirmLabel + '</button></div>' : '') + '</div>';
    if (onConfirm) { document.getElementById(id + '-confirm').addEventListener('click', function () { onConfirm(); Modal.close(id); }); }
    el.addEventListener('click', function (e) { if (e.target === el) Modal.close(id); });
    return el;
  }
};

/* ── DateFmt ─────────────────────────────────────────────────── */
const DateFmt = {
  fmt(d) { if (!d) return '—'; var dt = new Date(d); if (isNaN(dt)) return d; return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }); },
  fmtDT(d) { if (!d) return '—'; var dt = new Date(d); if (isNaN(dt)) return d; return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + dt.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }); },
  timeAgo(d) { if (!d) return ''; var s = Math.floor((Date.now() - new Date(d)) / 1000); if (s < 60) return 'just now'; if (s < 3600) return Math.floor(s / 60) + 'm ago'; if (s < 86400) return Math.floor(s / 3600) + 'h ago'; return Math.floor(s / 86400) + 'd ago'; },
  nowLocal() { var d = new Date(), pad = function (n) { return String(n).padStart(2, '0'); }; return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }
};

/* ── Autocomplete ────────────────────────────────────────────── */
function setupAutocomplete(opts) {
  var input = opts.input, dropdown = opts.dropdown, fetchFn = opts.fetchFn,
    renderItem = opts.renderItem, onSelect = opts.onSelect;
  var activeIdx = -1, results = [], timer;
  function show(items) {
    results = items; activeIdx = -1;
    dropdown.innerHTML = items.length
      ? items.map(function (it, i) { return '<div class="autocomplete-item" data-idx="' + i + '">' + renderItem(it) + '</div>'; }).join('')
      : '<div class="autocomplete-item" style="color:var(--gray-400)">No results</div>';
    dropdown.style.display = 'block';
    dropdown.querySelectorAll('.autocomplete-item[data-idx]').forEach(function (el) {
      el.addEventListener('click', function () { onSelect(results[+el.dataset.idx]); hide(); });
    });
  }
  function hide() { dropdown.style.display = 'none'; activeIdx = -1; }
  input.addEventListener('input', function () { clearTimeout(timer); var q = input.value.trim(); if (!q) { hide(); return; } timer = setTimeout(function () { fetchFn(q).then(show).catch(function () { }); }, 220); });
  input.addEventListener('keydown', function (e) {
    var items = dropdown.querySelectorAll('.autocomplete-item[data-idx]');
    if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx = Math.min(activeIdx + 1, items.length - 1); items.forEach(function (el, i) { el.classList.toggle('focused', i === activeIdx); }); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = Math.max(activeIdx - 1, 0); items.forEach(function (el, i) { el.classList.toggle('focused', i === activeIdx); }); }
    else if (e.key === 'Enter' && activeIdx >= 0) { e.preventDefault(); onSelect(results[activeIdx]); hide(); }
    else if (e.key === 'Escape') { hide(); }
  });
  document.addEventListener('click', function (e) { if (!input.contains(e.target) && !dropdown.contains(e.target)) hide(); });
}

/* ── Sidebar builder ─────────────────────────────────────────── */
function buildChrome(pageTitle) {
  var sidebar = [
    '<nav id="sidebar">',
    '<div class="sidebar-brand">',
    '<button class="sidebar-burger" onclick="toggleSidebar()" title="Toggle sidebar"><span class="material-icons">menu</span></button>',
    '<img src="assets/images/logo.jfif" class="brand-logo-img" alt="Logo">',
    '<div class="brand-name">Comboni Library<span>Management System</span></div>',
    '</div>',
    '<div class="sidebar-nav">',
    '<div class="nav-group-label">Main</div>',
    '<a class="nav-item" href="index.html" data-page="index.html"><span class="material-icons">home</span><span class="nav-label" data-i18n="nav_home">Home</span></a>',
    '<div class="nav-group-label">Library</div>',
    '<a class="nav-item" href="books.html" data-page="books.html"><span class="material-icons">menu_book</span><span class="nav-label" data-i18n="nav_books">Books</span></a>',
    '<a class="nav-item" href="borrow-records.html" data-page="borrow-records.html"><span class="material-icons">list_alt</span><span class="nav-label">Borrow Records</span></a>',
    '<a class="nav-item" href="members.html" data-page="members.html"><span class="material-icons">groups</span><span class="nav-label">Members</span></a>',
    '<a class="nav-item" href="overdue.html" data-page="overdue.html"><span class="material-icons">warning_amber</span><span class="nav-label" data-i18n="nav_overdue">Overdue</span></a>',
    '<a class="nav-item" href="reports.html" data-page="reports.html"><span class="material-icons">bar_chart</span><span class="nav-label" data-i18n="nav_reports">Reports</span></a>',
    '<a class="nav-item" href="archive.html" data-page="archive.html"><span class="material-icons">archive</span><span class="nav-label" data-i18n="nav_archive">Archive</span></a>',
    '<div class="nav-group-label">System</div>',
    '<a class="nav-item" href="export-import.html" data-page="export-import.html"><span class="material-icons">import_export</span><span class="nav-label" data-i18n="nav_export_import">Export / Import</span></a>',
    '<a class="nav-item" href="tutorial.html" data-page="tutorial.html"><span class="material-icons">help_outline</span><span class="nav-label" data-i18n="nav_tutorial">Tutorial</span></a>',
    '<a class="nav-item" href="settings.html" data-page="settings.html"><span class="material-icons">settings</span><span class="nav-label" data-i18n="nav_settings">Settings</span></a>',
    '<div class="nav-item dev-nav-wrap" onclick="location.href=\'developer.html\'" style="cursor:pointer">',
    '<span class="material-icons">person</span>',
    '<span class="nav-label" data-i18n="nav_developer">Developer</span>',
    '<div class="dev-hover-card">',
    '<div class="dev-card-avatar">J</div>',
    '<div class="dev-card-name">Jumenty</div>',
    '<div class="dev-card-role">Grade 12 · St. Daniel Comboni · 2026</div>',
    '<div class="dev-card-links">',
    '<a class="dev-card-link" href="mailto:jumenty4@gmail.com" onclick="event.stopPropagation()" title="Email">✉</a>',
    '<a class="dev-card-link" href="https://t.me/" onclick="event.stopPropagation()" title="Telegram">✈</a>',
    '<a class="dev-card-link" href="https://instagram.com/" onclick="event.stopPropagation()" title="Instagram">📷</a>',
    '<a class="dev-card-link" href="tel:+251903932959" onclick="event.stopPropagation()" title="Call">📞</a>',
    '</div></div></div>',
    '</div>',
    '<div class="sidebar-footer admin-only">',
    '<a href="#" onclick="Auth.logout()" style="color:rgba(255,255,255,.3);font-size:.75rem;display:flex;align-items:center;gap:5px">',
    '<span class="material-icons" style="font-size:14px">logout</span><span class="nav-label" data-i18n="btn_logout">Logout</span>',
    '</a></div>',
    '</nav>'
  ].join('');

  var topbar = [
    '<header id="topbar">',
    '<span class="page-title">' + pageTitle + '</span>',
    '<div class="topbar-spacer"></div>',
    '<span id="topbar-date" class="topbar-date"></span>',
    '<div id="unsaved-badge"></div>',
    '<button class="btn-save-all admin-only" onclick="UnsavedTracker.saveAll()">',
    '<span class="material-icons">save</span><span data-i18n="btn_save_all">Save All</span>',
    '</button>',
    '</header>'
  ].join('');

  var unsavedModal = [
    '<div class="modal-overlay" id="unsaved-modal">',
    '<div class="modal">',
    '<div class="modal-header"><h3><span class="material-icons">warning</span>Unsaved Changes</h3></div>',
    '<div class="modal-body"><p>You have unsaved changes. Save before leaving?</p></div>',
    '<div class="modal-footer">',
    '<button class="btn btn-secondary" onclick="Modal.close(\'unsaved-modal\')">Discard</button>',
    '<button class="btn btn-primary" onclick="UnsavedTracker.saveAll();Modal.close(\'unsaved-modal\')">',
    '<span class="material-icons">save</span>Save All</button>',
    '</div></div></div>'
  ].join('');

  return { sidebar: sidebar, topbar: topbar, unsavedModal: unsavedModal };
}

/* ── Helpers ─────────────────────────────────────────────────── */
function setActiveNav() {
  var page = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-item[data-page]').forEach(function (el) { el.classList.toggle('active', el.dataset.page === page); });
}
function initDateDisplay() {
  var el = document.getElementById('topbar-date');
  if (el) el.textContent = new Date().toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
}
function statusBadge(status) {
  return status === 'taken'
    ? '<span class="badge badge-taken"><span class="material-icons">book</span>' + i18n.t('status_taken') + '</span>'
    : '<span class="badge badge-returned"><span class="material-icons">check</span>' + i18n.t('status_returned') + '</span>';
}
function initials(name) { return (name || '').split(' ').filter(Boolean).map(function (w) { return w[0]; }).join('').toUpperCase().slice(0, 3); }
function confirmAction(msg) {
  return new Promise(function (resolve) {
    Modal.create({ id: 'confirm-modal', title: 'Confirm', icon: 'help_outline', body: '<p>' + msg + '</p>', onConfirm: function () { resolve(true); }, confirmLabel: 'Confirm' });
    Modal.open('confirm-modal');
    document.getElementById('confirm-modal').addEventListener('click', function (e) { if (e.target.id === 'confirm-modal') resolve(false); }, { once: true });
  });
}

/* ── Sidebar toggle ──────────────────────────────────────────── */
function toggleSidebar() {
  document.body.classList.toggle('sidebar-collapsed');
  localStorage.setItem('cl_sidebar', document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
}
function restoreSidebar() { if (localStorage.getItem('cl_sidebar') === '1') document.body.classList.add('sidebar-collapsed'); }

/* ── Screen Timeout ──────────────────────────────────────────── */
const ScreenTimeout = {
  timer: null, enabled: false, minutes: 15,
  init() {
    var self = this;
    API.get('api/settings.php').then(function(s) {
      self.enabled = s.screen_timeout_enabled === '1';
      self.minutes = parseInt(s.screen_timeout_minutes) || 15;
      if (self.enabled) self.start();
    }).catch(function(){});
  },
  start() {
    var self = this;
    this.resetTimer();
    ['mousemove','keydown','click','scroll','touchstart'].forEach(function(ev) {
      document.addEventListener(ev, function() { self.resetTimer(); }, { passive: true });
    });
  },
  resetTimer() {
    clearTimeout(this.timer);
    this.hideLock();
    var self = this;
    if (this.enabled) {
      this.timer = setTimeout(function() { self.showLock(); }, self.minutes * 60000);
    }
  },
  showLock() {
    if (document.getElementById('screen-lock')) return;
    var overlay = document.createElement('div');
    overlay.id = 'screen-lock';
    overlay.innerHTML = '<div class="lock-card">' +
      '<span class="material-icons" style="font-size:48px;color:var(--primary-400);margin-bottom:16px">lock</span>' +
      '<h2 style="margin-bottom:8px;color:var(--gray-900)">Session Locked</h2>' +
      '<p style="color:var(--gray-500);margin-bottom:20px;font-size:.875rem">Enter your password to continue</p>' +
      '<input type="password" id="lock-password" class="form-control" placeholder="Admin password" style="margin-bottom:12px">' +
      '<button class="btn btn-primary" onclick="ScreenTimeout.unlock()" style="width:100%">Unlock</button>' +
      '<div id="lock-error" style="color:var(--danger-500);font-size:.8rem;margin-top:8px;display:none"></div></div>';
    document.body.appendChild(overlay);
    document.getElementById('lock-password').focus();
    document.getElementById('lock-password').addEventListener('keydown', function(e) { if (e.key === 'Enter') ScreenTimeout.unlock(); });
  },
  hideLock() { var el = document.getElementById('screen-lock'); if (el) el.remove(); },
  async unlock() {
    var pw = document.getElementById('lock-password').value;
    try {
      var r = await API.post('api/auth.php', { action: 'login', role: 'admin', password: pw });
      if (r.ok) { this.hideLock(); this.resetTimer(); }
    } catch(e) {
      var err = document.getElementById('lock-error');
      if (err) { err.style.display = 'block'; err.textContent = 'Incorrect password'; }
    }
  }
};

/* ── Due Date Reminders ──────────────────────────────────────── */
const DueReminder = {
  async check() {
    try {
      var res = await API.get('api/borrow.php', { status: 'taken', limit: 100, offset: 0 });
      var settings = await API.get('api/settings.php');
      var rp = {
        teacher: parseInt(settings.return_period_teacher) || 30,
        student: parseInt(settings.return_period_student) || 14,
        reading_club: parseInt(settings.return_period_reading_club) || 14
      };
      var approaching = [];
      (res.data || []).forEach(function(r) {
        var period = rp[r.borrower_type] || 14;
        var daysOut = parseInt(r.days_stayed) || 0;
        var daysLeft = period - daysOut;
        if (daysLeft <= 3 && daysLeft > 0) {
          approaching.push(r.borrower_name + ' – "' + r.book_title + '" due in ' + daysLeft + ' day' + (daysLeft > 1 ? 's' : ''));
        }
      });
      if (approaching.length > 0) {
        Toast.show('📅 ' + approaching.length + ' book(s) due soon', 'warning', 5000);
      }
    } catch(e) {}
  }
};

/* ── Common DOMContentLoaded ─────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
  setActiveNav();
  initDateDisplay();
  UnsavedTracker.init();
  i18n.load(localStorage.getItem('cl_lang') || 'en');
  restoreSidebar();
  ScreenTimeout.init();
  // Check due reminders once per page load (only for admin)
  setTimeout(function() { if (Auth.role === 'admin') DueReminder.check(); }, 3000);
});
