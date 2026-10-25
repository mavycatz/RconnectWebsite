/* R-Connect Dispatcher — shared shell runtime.
   Consumes window.DISPATCHER_CONFIG emitted by views/layout/app.php. */
(function () {
  'use strict';
  var CFG = window.DISPATCHER_CONFIG || {};
  window.API = CFG.apiBase || '';
  window.TOKEN = CFG.token || '';
  window.CSRF_TOKEN = CFG.csrfToken || '';
  window.TERMINAL_ID = Number(CFG.terminalId || 0);
  window.ACTIVE_TAB = CFG.activeTab || 'home';
  window.LANG = CFG.lang || 'en';
  window.I18N = CFG.i18n || {};
  window.DASH_BASE = CFG.dashBase || '/dispatcher/';
  window.LOGOUT_URL = CFG.logoutUrl || '/dispatcher/';
  window.cacheTerminals = CFG.terminals || [];
  window.defaultRoute = CFG.defaultRoute || '';
  window.defaultDestTerminalId = Number(CFG.defaultDestTerminalId || 0);
})();

function t(key, fallback, replace) {
  var text = (window.I18N && Object.prototype.hasOwnProperty.call(I18N, key)) ? I18N[key] : (fallback !== undefined ? fallback : key);
  if (replace) {
    Object.keys(replace).forEach(function (k) { text = text.split(':' + k).join(String(replace[k])); });
  }
  return text;
}

function changeLanguage(lang) {
  try { localStorage.setItem('dispatcher_lang', lang); } catch (e) {}
  var url = new URL(window.location.href);
  url.searchParams.set('lang', lang);
  window.location.href = url.toString();
}

(function () {
  try {
    var stored = localStorage.getItem('dispatcher_lang');
    var params = new URLSearchParams(window.location.search);
    if (stored && stored !== LANG && !params.get('lang')) {
      params.set('lang', stored);
      window.location.replace(window.location.pathname + '?' + params.toString());
    }
  } catch (e) {}
})();

/* Fallback showAlert if /public/login/custom-alert.js is unavailable */
if (typeof window.showAlert !== 'function') {
  window.showAlert = function (type, title, message, onConfirm) {
    var overlay = document.getElementById('customAlertOverlay');
    if (!overlay) {
      console.log('[' + type.toUpperCase() + '] ' + title + ': ' + message);
      if (typeof onConfirm === 'function') onConfirm();
      return;
    }
    var header = overlay.querySelector('.custom-alert-header');
    var iconEl = overlay.querySelector('.custom-alert-icon');
    var titleEl = overlay.querySelector('.custom-alert-title');
    var msgEl = overlay.querySelector('.custom-alert-message');
    var btn = overlay.querySelector('.custom-alert-btn');
    var btnCancel = overlay.querySelector('.custom-alert-btn-cancel');
    var actions = overlay.querySelector('.custom-alert-actions');

    if (header) header.className = 'custom-alert-header ' + type;
    if (type === 'success') {
      if (iconEl) iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#2e4836" stroke-width="3"/><path d="M16 24l6 6 10-10" stroke="#2e4836" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>';
      if (btnCancel) btnCancel.style.display = 'none';
      if (actions) actions.style.display = '';
      if (btn) { btn.className = 'custom-alert-btn success'; btn.textContent = 'Continue'; }
    } else {
      if (iconEl) iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#fff" stroke-width="3"/><path d="M18 18l12 12M30 18l-12 12" stroke="#fff" stroke-width="3" stroke-linecap="round"/></svg>';
      if (btnCancel) btnCancel.style.display = 'none';
      if (actions) actions.style.display = '';
      if (btn) { btn.className = 'custom-alert-btn error'; btn.textContent = 'Close'; }
    }
    if (titleEl) titleEl.textContent = title || '';
    if (msgEl) msgEl.textContent = message || '';
    if (btn) {
      btn.onclick = function () {
        overlay.classList.remove('active');
        if (typeof onConfirm === 'function') onConfirm();
      };
    }
    overlay.classList.add('active');
  };
}

/* Robust API response parser: handles pure JSON, text with BOM, whitespace, or PHP output/notices */
async function parseApiResponse(r) {
  var text = '';
  try {
    text = await r.text();
  } catch (e) {
    console.error('Failed to read response text:', e);
    return null;
  }
  if (!text || typeof text !== 'string') return null;
  try { return JSON.parse(text); } catch (_) {}
  var clean = text.replace(/^\uFEFF/, '').trim();
  try { return JSON.parse(clean); } catch (_) {}
  for (var start = 0; start < clean.length; start++) {
    if (clean[start] !== '{') continue;
    var depth = 0, inString = false, escape = false;
    for (var i = start; i < clean.length; i++) {
      var ch = clean[i];
      if (escape) { escape = false; continue; }
      if (ch === '\\' && inString) { escape = true; continue; }
      if (ch === '"') { inString = !inString; continue; }
      if (!inString) {
        if (ch === '{') depth++;
        else if (ch === '}') {
          depth--;
          if (depth === 0) {
            try {
              var res = JSON.parse(clean.substring(start, i + 1));
              if (res && typeof res === 'object') return res;
            } catch (_) {}
            break;
          }
        }
      }
    }
  }
  console.error('Non-JSON API response:', text);
  return null;
}

function isResponseSuccess(r, j) {
  if (!r) return false;
  var isHttpOk = r.ok || (r.status >= 200 && r.status < 300);
  if (!isHttpOk) return false;
  if (j && typeof j === 'object') {
    if (j.success === true || j.success === 'true' || j.status === 'success') return true;
    if (j.success !== false && !j.error && (j.arrival_time || j.fuel_data || j.data || (j.message && !/failed|error|invalid|forbidden/i.test(j.message)))) return true;
    return false;
  }
  return false;
}

function extractErrorMessage(r, j, defaultMsg) {
  if (j && j.message && typeof j.message === 'string') {
    var m = j.message.trim();
    if (!/SQL|Database connection|exception|prepare failed|error_log|Stack trace|PDO/i.test(m)) return m;
  }
  if (r) {
    if (r.status === 401) return t('state.session_expired', 'Your session has expired. Please log in again.');
    if (r.status === 403) return (j && j.message) ? j.message : 'You are not authorized to perform this action.';
    if (r.status === 404) return 'The requested record was not found.';
  }
  return defaultMsg || 'An error occurred. Please try again.';
}

function apiHeaders() { return { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + TOKEN }; }
function apiPostHeaders() { return { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + TOKEN, 'X-CSRF-Token': CSRF_TOKEN }; }
function isAnyModalOpen() { return document.querySelector('.modal-overlay.show') !== null; }
function closeModal(id) { var el = document.getElementById(id); if (el) el.classList.remove('show'); if (!isAnyModalOpen()) document.body.classList.remove('modal-open'); }
function showModal(id) { var el = document.getElementById(id); if (el) el.classList.add('show'); document.body.classList.add('modal-open'); }
function esc(s) { return String(s).replace(/[&<>"']/g, ''); }

function getPHTDate() {
  var d = new Date();
  return new Date(d.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
}
function formatPHT(d) {
  return d.toLocaleString('en-PH', { timeZone: 'Asia/Manila', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
}
function getPHTDateStr() {
  var d = getPHTDate();
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}
/* Whether a trip row is inbound to a terminal: its recorded destination is
   that terminal, or - when no destination is recorded - the trip is on route
   and can be received by any terminal (mirrors dispatcher_trip_is_inbound). */
function tripIsInboundTo(v, terminalId) {
  var terminal = Number(terminalId);
  if (!v || !terminal) return false;
  if (typeof v.is_inbound !== 'undefined' && v.is_inbound !== null) {
    return v.is_inbound === true || v.is_inbound === 1 || v.is_inbound === '1';
  }
  if (Number(v.to_terminal_id) === terminal) return true;
  return Number(v.to_terminal_id || 0) <= 0 && v.status === 'Departed';
}
function liveClock() {
  var clock = document.getElementById('liveClock');
  var date = document.getElementById('liveDate');
  if (!clock || !date) return;
  var d = getPHTDate();
  clock.textContent = formatPHT(d) + ' (PHT)';
  date.textContent = d.toLocaleDateString('en-PH', { timeZone: 'Asia/Manila', weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}
setInterval(liveClock, 1000);
liveClock();

function statusLabel(v) {
  return v.status === 'Scheduled' ? 'Pending' : v.status === 'Departed' ? 'On Route' : v.status === 'Completed' ? 'Completed' : v.status === 'Interrupted' ? t('status.interrupted', 'Interrupted') : v.status;
}
function routeArrow(name) { return String(name || '').replace(/\s*-\s*/g, ' → '); }
function ordinal(n) {
  var num = Number(n);
  if (!num || num < 1) return '—';
  var mod100 = num % 100;
  if (mod100 >= 11 && mod100 <= 13) return num + 'th';
  switch (num % 10) { case 1: return num + 'st'; case 2: return num + 'nd'; case 3: return num + 'rd'; default: return num + 'th'; }
}
function formatClock(t) {
  if (!t) return '--:--';
  var s = String(t);
  var m = s.match(/(\d{2}):(\d{2})/);
  return m ? (m[1] + ':' + m[2]) : s;
}
function formatClock12(value, withSeconds) {
  if (!value) return '';
  var s = String(value);
  var m;
  if (s.length >= 16 && s.indexOf('T') > -1) {
    m = s.substring(11).match(/(\d{2}):(\d{2})(?::(\d{2}))?/);
  } else {
    m = s.match(/(\d{2}):(\d{2})(?::(\d{2}))?/);
  }
  if (!m) return s;
  var hours = parseInt(m[1], 10);
  var suffix = hours >= 12 ? 'PM' : 'AM';
  hours = hours % 12;
  if (hours === 0) hours = 12;
  return hours + ':' + m[2] + ((withSeconds && m[3]) ? (':' + m[3]) : '') + ' ' + suffix;
}
function statusPill(status) {
  var map = {
    waiting_for_driver: { cls: 'q-waiting_for_driver', icon: 'bi-person-dash', label: t('status.waiting_for_driver', 'WAITING FOR DRIVER') },
    driver_assigned: { cls: 'q-driver_assigned', icon: 'bi-person-check', label: t('status.driver_assigned', 'DRIVER ASSIGNED') },
    checked_in: { cls: 'q-checked_in', icon: 'bi-clipboard-check', label: t('status.checked_in', 'CHECKED IN') },
    ready: { cls: 'q-ready', icon: 'bi-check-circle-fill', label: t('status.ready', 'READY TO DISPATCH') },
    departed: { cls: 'q-departed', icon: 'bi-send-check-fill', label: t('status.on_route', 'ON ROUTE') },
    blocked: { cls: 'q-blocked', icon: 'bi-slash-circle-fill', label: t('status.blocked', 'UNAVAILABLE') }
  };
  var m = map[status] || map.waiting_for_driver;
  return '<span class="queue-status-pill ' + m.cls + '"><i class="bi ' + m.icon + '"></i> ' + m.label + '</span>';
}
function durationLabel(v) {
  if (v.duration_minutes) return v.duration_minutes + ' min';
  if (v.departure_time && v.arrival_time) {
    var t1 = new Date('1970-01-01T' + v.departure_time + ':00'), t2 = new Date('1970-01-01T' + v.arrival_time + ':00');
    var df = (t2 - t1) / 60000;
    return df > 0 ? Math.round(df) + ' min' : '';
  }
  return '';
}

async function fetchJson(url, opts) {
  try {
    var r = await fetch(url, opts);
    var text = await r.text();
    try { return JSON.parse(text); } catch (e) { console.error('JSON parse error from ' + url + ':', text); return { success: false, data: [] }; }
  } catch (e) {
    console.error('fetch error for ' + url + ':', e);
    return { success: false, data: [] };
  }
}

function logout() { window.location.href = window.LOGOUT_URL; }
function printReport() { window.open(API + 'print_report?date=' + getPHTDateStr(), '_blank', 'width=900,height=700'); }

function goToTab(tab, extraParams) {
  var url = window.DASH_BASE + tab;
  if (extraParams) {
    Object.keys(extraParams).forEach(function (k) {
      url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(extraParams[k]);
    });
  }
  window.location.href = url;
}
function goToQueueBus(busId) { goToTab('queue', { focus_bus: busId }); }
function goToTrip(tripId) { goToTab('trips', { focus_trip: tripId }); }

/* Rotated API URL recovery: reload the app onto the current URLs once. */
function recoverStaleApp() {
  try {
    var last = parseInt(sessionStorage.getItem('dispatcher_stale_reload') || '0', 10);
    if (Date.now() - last < 30000) return false;
    sessionStorage.setItem('dispatcher_stale_reload', String(Date.now()));
  } catch (e) {}
  window.location.replace('/dispatcher/?lang=' + encodeURIComponent(LANG));
  return true;
}

/* Shell badge: unresolved bus problem reports. Hidden when zero. */
function updateProblemBadge(reports) {
  var active = (reports || []).filter(function (r) { return r.status === 'waiting' || r.status === 'acknowledged'; }).length;
  var badge = document.getElementById('moreNavBadge');
  if (badge) {
    badge.hidden = active === 0;
    badge.textContent = active > 99 ? '99+' : String(active);
  }
  var count = document.getElementById('moreProblemCount');
  if (count) {
    count.hidden = active === 0;
    count.textContent = String(active);
  }
}
async function refreshProblemBadge() {
  try {
    var r = await fetch(API + 'get_bus_problem_reports', { headers: apiHeaders() });
    if (r.status !== 200) return;
    var j = await parseApiResponse(r);
    if (j && j.success) updateProblemBadge(j.reports || []);
  } catch (_) {}
}

/* PWA install banner */
(function () {
  var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var deferredPrompt = null;
  var banner = document.getElementById('installBanner');
  var installBtn = document.getElementById('installBtn');
  if (window.matchMedia('(display-mode: standalone)').matches) { if (banner) banner.style.display = 'none'; return; }
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferredPrompt = e; });
  if (installBtn) {
    installBtn.addEventListener('click', async function () {
      if (deferredPrompt) { deferredPrompt.prompt(); await deferredPrompt.userChoice; deferredPrompt = null; if (banner) banner.style.display = 'none'; return; }
      if (isIOS) showAlert('success', 'Install App', 'To install:\n\n1. Tap the Share icon at the bottom of Safari\n2. Scroll down and tap "Add to Home Screen"\n3. Tap "Add" in the top right');
      else showAlert('success', 'Install App', 'To install:\n\nOpen this page in Chrome or Edge and look for "Install" in the browser menu.');
    });
  }
  window.addEventListener('appinstalled', function () { if (banner) banner.style.display = 'none'; });
})();

if ('serviceWorker' in navigator) { navigator.serviceWorker.register('/dispatcher/sw.js'); }

refreshProblemBadge();
setInterval(refreshProblemBadge, 60000);
