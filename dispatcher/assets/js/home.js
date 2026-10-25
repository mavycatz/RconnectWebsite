/* Home screen: terminal-scoped operational summary, pending tasks, and alerts.
   Data comes from get_trip_queue (summary/entries) and get_bus_problem_reports. */

const homeState = {
  entries: [],
  summary: null,
  reports: [],
  arriving: [],
  queueLoaded: false,
  problemsLoaded: false,
  arrivingLoaded: false,
  lastSuccess: 0,
  loadingQueue: false,
  loadingProblems: false,
  loadingArrivals: false
};

function homeActionMeta(action) {
  if (action === 'assign_driver') return { icon: 'bi-person-plus', label: t('action.assign_driver', 'Assign Driver') };
  if (action === 'check_in') return { icon: 'bi-person-check', label: t('action.check_in', 'Check In') };
  if (action === 'prepare') return { icon: 'bi-clipboard-check', label: t('action.prepare_trip', 'Prepare Trip') };
  if (action === 'dispatch') return { icon: 'bi-send', label: t('action.dispatch', 'Dispatch') };
  return { icon: 'bi-list', label: t('common.view_details', 'View Details') };
}

function homeUnresolvedProblemCount() {
  return homeState.reports.filter(function (r) { return r.status === 'waiting' || r.status === 'acknowledged'; }).length;
}

function renderHomeSummary() {
  const el = document.getElementById('homeSummary');
  if (!el) return;
  const s = homeState.summary;
  if (!s) {
    el.innerHTML = '<div class="empty-state" style="grid-column:1/-1;padding:14px 4px;"><i class="bi bi-exclamation-triangle" style="color:#d32f2f;"></i><p>' + esc(t('home.summary_error', 'The summary could not be loaded.')) + '</p><button type="button" class="btn-primary-app" style="max-width:160px;margin:6px auto;padding:8px 12px;font-size:13px;" onclick="loadHomeQueue({force:true})">' + esc(t('common.retry', 'Retry')) + '</button></div>';
    return;
  }
  const problemsValue = homeState.problemsLoaded ? String(homeUnresolvedProblemCount()) : '—';
  const cards = [
    { num: s.waiting_for_driver || 0, lbl: t('home.stat_waiting', 'Waiting') },
    { num: s.ready || 0, lbl: t('home.stat_ready', 'Ready') },
    { num: s.dispatched || 0, lbl: t('home.stat_on_route', 'On Route') },
    { num: s.completed || 0, lbl: t('home.stat_completed', 'Completed') },
    { num: problemsValue, lbl: t('home.stat_problems', 'Problems') }
  ];
  el.innerHTML = cards.map(function (c) {
    return '<div class="home-stat"><div class="num">' + esc(String(c.num)) + '</div><div class="lbl">' + esc(c.lbl) + '</div></div>';
  }).join('');
}

function renderHomePending() {
  const el = document.getElementById('homePending');
  const countEl = document.getElementById('homePendingCount');
  if (!el) return;
  if (!homeState.queueLoaded) {
    el.innerHTML = '<div class="empty-state" style="padding:14px 4px;"><i class="bi bi-exclamation-triangle" style="color:#d32f2f;"></i><p>' + esc(t('home.pending_error', 'Pending tasks could not be loaded.')) + '</p><button type="button" class="btn-primary-app" style="max-width:160px;margin:6px auto;padding:8px 12px;font-size:13px;" onclick="loadHomeQueue({force:true})">' + esc(t('common.retry', 'Retry')) + '</button></div>';
    if (countEl) countEl.hidden = true;
    return;
  }
  const pending = homeState.entries
    .filter(function (e) { return e.next_action && e.status !== 'departed'; })
    .sort(function (a, b) { return (Number(a.queue_position) || 0) - (Number(b.queue_position) || 0); });
  if (!pending.length) {
    el.innerHTML = '<div class="empty-state" style="padding:14px 4px;"><i class="bi bi-check-circle"></i><p>' + esc(t('home.pending_empty', 'No pending tasks right now.')) + '</p></div>';
    if (countEl) countEl.hidden = true;
    return;
  }
  if (countEl) { countEl.hidden = false; countEl.textContent = String(pending.length); }
  el.innerHTML = pending.map(function (e) {
    const meta = homeActionMeta(e.next_action);
    return '<button type="button" class="pending-item" onclick="goToQueueBus(\'' + esc(e.bus_id) + '\')">'
      + '<span class="pi-icon"><i class="bi ' + meta.icon + '"></i></span>'
      + '<span class="pi-main"><strong>' + esc(e.bus_id) + '</strong><small>' + esc((e.driver_name || t('card.no_driver', 'No driver assigned')) + ' · ' + e.short_reason) + '</small></span>'
      + '<span class="pi-main" style="flex:0 0 auto;align-items:flex-end;"><strong style="color:var(--primary-dark);">' + esc(meta.label) + '</strong><small>#' + esc(String(e.queue_position)) + '</small></span>'
      + '<span class="pi-chevron"><i class="bi bi-chevron-right"></i></span>'
      + '</button>';
  }).join('');
}

function renderHomeAlerts() {
  const el = document.getElementById('homeAlerts');
  const countEl = document.getElementById('homeAlertsCount');
  if (!el) return;
  const alerts = [];

  homeState.reports
    .filter(function (r) { return r.status === 'waiting' || r.status === 'acknowledged'; })
    .forEach(function (r) {
      alerts.push({
        cls: 'alert-problem',
        icon: 'bi-exclamation-triangle',
        title: t('home.alert_problem', 'Bus :bus — :type', { bus: r.bus_id || '-', type: r.problem_type || t('home.alert_problem_generic', 'Problem report') }),
        detail: (r.status === 'waiting' ? t('problems.waiting', 'Waiting') : t('problems.acknowledged', 'Acknowledged')) + (r.driver_name ? ' · ' + r.driver_name : ''),
        action: "goToTab('more')"
      });
    });

  homeState.entries.filter(function (e) { return e.skip && e.skip.active; }).forEach(function (e) {
    alerts.push({
      cls: 'alert-skip',
      icon: 'bi-slash-circle',
      title: t('home.alert_skipped', 'Bus :bus skipped: :reason', { bus: e.bus_id, reason: (e.skip.reason_label || t('card.skipped_reason', 'reason recorded')) }),
      detail: t('home.alert_skipped_detail', 'Temporary skip in the queue'),
      action: 'goToQueueBus(\'' + esc(e.bus_id) + '\')'
    });
  });

  homeState.entries
    .filter(function (e) {
      return e.driver_id && e.status !== 'departed' && e.presence && !e.presence.updated_at;
    })
    .forEach(function (e) {
      alerts.push({
        cls: 'alert-presence',
        icon: 'bi-geo-alt',
        title: t('home.alert_presence_missing', 'No GPS report for :driver', { driver: e.driver_name || e.driver_id }),
        detail: e.bus_id + ' · ' + t('attendance.gps_none', 'Not reported'),
        action: "goToTab('attendance')"
      });
    });

  homeState.entries
    .filter(function (e) {
      return e.driver_id && e.status !== 'departed' && e.presence && e.presence.updated_at && e.presence.fresh === false;
    })
    .forEach(function (e) {
      alerts.push({
        cls: 'alert-presence',
        icon: 'bi-clock-history',
        title: t('home.alert_presence_stale', 'Stale GPS report for :driver', { driver: e.driver_name || e.driver_id }),
        detail: e.bus_id + ' · ' + formatClock12(e.presence.updated_at),
        action: "goToTab('attendance')"
      });
    });

  if (!homeState.queueLoaded) {
    el.innerHTML = '<div class="empty-state" style="padding:14px 4px;"><i class="bi bi-exclamation-triangle" style="color:#d32f2f;"></i><p>' + esc(t('home.alerts_error', 'Alerts could not be loaded.')) + '</p><button type="button" class="btn-primary-app" style="max-width:160px;margin:6px auto;padding:8px 12px;font-size:13px;" onclick="loadHomeQueue({force:true})">' + esc(t('common.retry', 'Retry')) + '</button></div>';
    if (countEl) countEl.hidden = true;
    return;
  }

  if (!alerts.length) {
    const problemNote = homeState.problemsLoaded ? '' : '<p style="margin-top:6px;">' + esc(t('home.alerts_problems_error', 'Problem reports could not be loaded.')) + ' <button type="button" class="link-btn" onclick="loadHomeProblems({force:true})">' + esc(t('common.retry', 'Retry')) + '</button></p>';
    el.innerHTML = '<div class="empty-state" style="padding:14px 4px;"><i class="bi bi-check-circle"></i><p>' + esc(t('home.alerts_empty', 'No alerts right now.')) + '</p>' + problemNote + '</div>';
    if (countEl) countEl.hidden = true;
    return;
  }

  if (countEl) { countEl.hidden = false; countEl.textContent = String(alerts.length); }
  let html = alerts.map(function (a) {
    return '<button type="button" class="alert-item ' + a.cls + '" onclick="' + a.action + '">'
      + '<span class="ai-icon"><i class="bi ' + a.icon + '"></i></span>'
      + '<span class="ai-main"><strong>' + esc(a.title) + '</strong><small>' + esc(a.detail) + '</small></span>'
      + '<span class="ai-chevron"><i class="bi bi-chevron-right"></i></span>'
      + '</button>';
  }).join('');
  if (!homeState.problemsLoaded) {
    html += '<div class="qc-reason">' + esc(t('home.alerts_problems_error', 'Problem reports could not be loaded.')) + ' <button type="button" class="link-btn" onclick="loadHomeProblems({force:true})">' + esc(t('common.retry', 'Retry')) + '</button></div>';
  }
  el.innerHTML = html;
}

function updateHomeUpdatedLabel() {
  const label = document.getElementById('homeUpdatedLabel');
  if (!label) return;
  if (!homeState.lastSuccess) { label.textContent = ''; return; }
  const secs = Math.max(0, Math.floor((Date.now() - homeState.lastSuccess) / 1000));
  label.textContent = secs < 5 ? t('common.updated_just_now', 'Queue updated just now') : t('common.updated_ago', 'Queue updated :seconds s ago', { seconds: secs });
}

/* ── Arriving at this terminal: buses en route here / awaiting confirmation.
   The receiving terminal can report the arrival or open the confirmation
   dialog on the Trips screen from here. ─────────────────────────────────── */
function homeTripInbound(v) {
  if (typeof tripIsInboundTo === 'function') return tripIsInboundTo(v, TERMINAL_ID);
  return Number(v.to_terminal_id) === TERMINAL_ID;
}

function renderHomeArrivals() {
  const card = document.getElementById('homeArrivingCard');
  const el = document.getElementById('homeArriving');
  const countEl = document.getElementById('homeArrivingCount');
  if (!card || !el) return;

  if (!homeState.arrivingLoaded) {
    card.hidden = true;
    return;
  }

  const list = homeState.arriving.filter(function (v) {
    return v.status === 'Departed' && homeTripInbound(v);
  });

  if (!list.length) {
    card.hidden = true;
    if (countEl) countEl.hidden = true;
    return;
  }

  card.hidden = false;
  if (countEl) { countEl.hidden = false; countEl.textContent = String(list.length); }

  el.innerHTML = list.map(function (v) {
    const awaiting = v.arrival_status === 'arrived_awaiting_confirmation';
    const statusText = awaiting
      ? t('trips.arrival_awaiting', 'Arrived – awaiting confirmation')
      : t('trips.arrival_on_route', 'On route');
    const action = awaiting
      ? '<button type="button" class="btn-action btn-arrive" onclick="goToTab(\'trips\', {arrive_trip: ' + Number(v.id) + '})"><i class="bi bi-check-circle"></i> ' + esc(t('trips.confirm_arrival_button', 'Confirm Arrival')) + '</button>'
      : '<button type="button" class="btn-action btn-arrive" onclick="homeReportArrival(' + Number(v.id) + ', this)"><i class="bi bi-flag"></i> ' + esc(t('trips.arrived_button', 'Arrived')) + '</button>';
    const route = v.route_name || v.from_terminal_name || '';
    const sub = (v.driver_name || v.driver_id || t('card.no_driver', 'No driver assigned'))
      + (route ? ' · ' + route : '') + ' · ' + statusText;
    return '<div class="pending-item" style="cursor:default;align-items:center;">'
      + '<span class="pi-icon"><i class="bi bi-bus-front"></i></span>'
      + '<span class="pi-main"><strong>' + esc(String(v.bus_id || '')) + '</strong><small>' + esc(sub) + '</small></span>'
      + '<span style="flex:none;display:flex;align-items:center;">' + action + '</span>'
      + '</div>';
  }).join('');
}

async function loadHomeArrivals(opts) {
  opts = opts || {};
  if (homeState.loadingArrivals && !opts.force) return;
  homeState.loadingArrivals = true;
  try {
    const r = await fetch(API + 'get_dispatches?date=' + encodeURIComponent(getPHTDateStr()) + '&terminal_id=' + TERMINAL_ID, { headers: apiHeaders() });
    const j = await parseApiResponse(r);
    if (!j || !j.success) { renderHomeArrivals(); return; }
    homeState.arriving = Array.isArray(j.data) ? j.data : [];
    homeState.arrivingLoaded = true;
    renderHomeArrivals();
  } catch (e) {
    console.error('loadHomeArrivals error:', e);
    renderHomeArrivals();
  } finally {
    homeState.loadingArrivals = false;
  }
}

async function homeReportArrival(id, btn) {
  if (btn) btn.disabled = true;
  try {
    const r = await fetch(API + 'update_dispatch', {
      method: 'POST',
      headers: apiPostHeaders(),
      body: JSON.stringify({ dispatch_id: id, action: 'report_arrival', source: 'staff', csrf_token: CSRF_TOKEN })
    });
    const j = await parseApiResponse(r);
    if (isResponseSuccess(r, j)) {
      showAlert('success', t('trips.arrival_reported_title', 'Arrival Reported'), t('trips.arrival_reported_body', 'The trip is marked arrived and awaits confirmation with the ending odometer.'));
      loadHomeArrivals({ force: true });
      loadHomeQueue({ force: true });
    } else {
      showAlert('error', t('trips.arrival_failed', 'Arrival Failed'), extractErrorMessage(r, j, t('trips.arrival_report_failed', 'Unable to report the arrival. Please try again.')));
    }
  } catch (e) {
    console.error('homeReportArrival error:', e);
    showAlert('error', t('trips.arrival_failed', 'Arrival Failed'), t('state.network_error', 'Network error. Please check your connection and try again.'));
  } finally {
    if (btn) btn.disabled = false;
  }
}

function setHomeDateLabel() {
  const el = document.getElementById('homeDateLabel');
  if (!el) return;
  const d = getPHTDate();
  el.textContent = d.toLocaleDateString('en-PH', { timeZone: 'Asia/Manila', month: 'short', day: 'numeric' });
}

async function loadHomeQueue(opts) {
  opts = opts || {};
  if (homeState.loadingQueue && !opts.force) return;
  homeState.loadingQueue = true;
  try {
    const r = await fetch(API + 'get_trip_queue', { headers: apiHeaders() });
    const j = await parseApiResponse(r);
    if (!j && (r.status === 404 || r.status === 410)) { recoverStaleApp(); return; }
    if (j && j.code === 'stale_api_route') { recoverStaleApp(); return; }
    if (!j || !j.success) {
      if (!homeState.queueLoaded) {
        homeState.summary = null;
        renderHomeSummary();
        renderHomePending();
        renderHomeAlerts();
      }
      return;
    }
    const d = j.data || {};
    homeState.entries = Array.isArray(d.entries) ? d.entries : [];
    homeState.summary = d.summary || {};
    homeState.queueLoaded = true;
    homeState.lastSuccess = Date.now();
    renderHomeSummary();
    renderHomePending();
    renderHomeAlerts();
    updateHomeUpdatedLabel();
  } catch (e) {
    console.error('loadHomeQueue error:', e);
    if (!homeState.queueLoaded) { renderHomeSummary(); renderHomePending(); renderHomeAlerts(); }
  } finally {
    homeState.loadingQueue = false;
  }
}

async function loadHomeProblems(opts) {
  opts = opts || {};
  if (homeState.loadingProblems && !opts.force) return;
  homeState.loadingProblems = true;
  try {
    const r = await fetch(API + 'get_bus_problem_reports', { headers: apiHeaders() });
    const j = await parseApiResponse(r);
    if (!j || !j.success) { renderHomeAlerts(); return; }
    homeState.reports = Array.isArray(j.reports) ? j.reports : [];
    homeState.problemsLoaded = true;
    updateProblemBadge(homeState.reports);
    renderHomeSummary();
    renderHomeAlerts();
  } catch (e) {
    console.error('loadHomeProblems error:', e);
    renderHomeAlerts();
  } finally {
    homeState.loadingProblems = false;
  }
}

function loadHome(opts) {
  loadHomeQueue(opts);
  loadHomeProblems(opts);
  loadHomeArrivals(opts);
}

setHomeDateLabel();
loadHome({ force: true });
setInterval(updateHomeUpdatedLabel, 1000);
setInterval(function () {
  if (!document.hidden) loadHome({ force: true });
}, 30000);
document.addEventListener('visibilitychange', function () {
  if (!document.hidden && homeState.queueLoaded) loadHome({ force: true });
});
