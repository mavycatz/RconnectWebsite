/* Attendance screen: driver attendance verification for the terminal queue,
   with dispatcher check-in/check-out recording. Data comes from get_trip_queue
   (queue rows, presence, check-in state) and get_available_drivers. */

const attendanceState = {
  entries: [],
  availableDriverIds: null,
  lastSuccess: 0,
  loading: false,
  started: false
};

function attendanceSafeId(busId) {
  return String(busId).replace(/[^A-Za-z0-9_-]/g, '_');
}

function attendanceGpsPill(p) {
  p = p || {};
  if (!p.updated_at) {
    return '<span class="att-pill pill-gps-off"><i class="bi bi-geo-alt"></i> ' + esc(t('attendance.gps_none', 'Not reported')) + '</span>';
  }
  if (p.fresh === false) {
    return '<span class="att-pill pill-gps-warn"><i class="bi bi-clock-history"></i> ' + esc(t('attendance.gps_stale', 'Stale · :time', { time: formatClock12(p.updated_at) })) + '</span>';
  }
  if (p.at_terminal) {
    return '<span class="att-pill pill-gps-ok"><i class="bi bi-geo-alt-fill"></i> ' + esc(t('attendance.gps_at_terminal', 'At terminal')) + '</span>';
  }
  if (p.available) {
    const km = (p.distance_km !== null && p.distance_km !== undefined) ? Number(p.distance_km).toFixed(1) + ' km' : '';
    return '<span class="att-pill pill-gps-warn"><i class="bi bi-geo-alt"></i> ' + esc(t('attendance.gps_away', 'Away from terminal')) + (km ? ' · ' + esc(km) : '') + '</span>';
  }
  return '<span class="att-pill pill-gps-off"><i class="bi bi-geo-alt"></i> ' + esc(t('attendance.gps_no_terminal', 'Terminal location not configured')) + '</span>';
}

function attendanceAvailability(e) {
  const p = e.presence || {};
  if (!e.driver_id) {
    return '<span class="att-pill pill-none"><i class="bi bi-person-dash"></i> ' + esc(t('attendance.no_driver_short', 'No driver')) + '</span>';
  }
  if (e.trip && e.trip.status === 'Departed') {
    const tripLabel = (e.trip.trip_number != null) ? ('#' + e.trip.trip_number) : ('#' + e.trip.id);
    return '<span class="att-busy"><i class="bi bi-send"></i> ' + esc(t('attendance.on_trip', 'On trip :trip', { trip: tripLabel })) + '</span>';
  }
  if (attendanceState.availableDriverIds && attendanceState.availableDriverIds.has(String(e.driver_id))) {
    return '<span class="att-pill pill-in"><i class="bi bi-check-circle"></i> ' + esc(t('attendance.available', 'Available')) + '</span>';
  }
  if (p.checked_in) {
    return '<span class="att-pill pill-gps-warn"><i class="bi bi-hourglass-split"></i> ' + esc(t('attendance.on_standby', 'Checked in')) + '</span>';
  }
  return '<span class="att-pill pill-none"><i class="bi bi-question-circle"></i> ' + esc(t('attendance.unavailable', 'Unavailable')) + '</span>';
}

function attendanceCardHTML(e) {
  const p = e.presence || {};
  const co = p.checkout || {};
  const checkedIn = !!p.checked_in;
  const checkedOut = !!co.recorded;
  const stateClass = checkedOut ? 'att-checked_out' : (checkedIn ? 'att-checked_in' : 'att-none');
  const statePill = checkedOut
    ? '<span class="att-pill pill-out"><i class="bi bi-box-arrow-right"></i> ' + esc(t('attendance.state_out', 'Checked out')) + '</span>'
    : (checkedIn
      ? '<span class="att-pill pill-in"><i class="bi bi-check-circle"></i> ' + esc(t('attendance.state_in', 'Checked in')) + '</span>'
      : '<span class="att-pill pill-none"><i class="bi bi-hourglass-split"></i> ' + esc(t('attendance.state_none', 'Not checked in')) + '</span>');

  const checkinTime = checkedIn ? (formatClock12(p.checked_in_at || p.updated_at) || '—') : '—';
  const source = checkedIn
    ? (p.checked_in_by === 'dispatcher'
      ? t('attendance.source_dispatcher', 'By :name', { name: p.checked_in_by_name || t('details.dispatcher', 'Dispatcher') })
      : t('attendance.source_driver', 'Driver location report'))
    : '—';
  const checkoutTime = checkedOut ? (formatClock12(co.at) || '—') : '—';
  const checkoutBy = (checkedOut && co.by_name) ? (' · ' + co.by_name) : '';

  const sid = attendanceSafeId(e.bus_id);
  const actions = [];
  if (e.driver_id && !checkedIn) {
    actions.push('<button type="button" class="btn-action btn-dispatch-queue" id="attCheckIn_' + sid + '" onclick="attendanceCheckIn(\'' + esc(e.bus_id) + '\')"><i class="bi bi-person-check"></i> ' + esc(t('action.check_in_driver', 'CHECK IN DRIVER')) + '</button>');
  }
  if (checkedIn && !checkedOut) {
    actions.push('<button type="button" class="btn-action btn-view" id="attCheckOut_' + sid + '" onclick="attendanceCheckOut(\'' + esc(e.bus_id) + '\')"><i class="bi bi-box-arrow-right"></i> ' + esc(t('attendance.check_out', 'CHECK OUT DRIVER')) + '</button>');
  }
  if (!e.driver_id) {
    actions.push('<div class="qc-reason"><i class="bi bi-info-circle"></i> ' + esc(t('attendance.assign_first', 'Assign a driver on the Queue screen first.')) + '</div>');
  }

  return '<article class="att-card ' + stateClass + '" data-att-bus="' + esc(e.bus_id) + '">'
    + '<div class="att-top"><span class="att-bus"><i class="bi bi-bus-front"></i> ' + esc(e.bus_id) + (e.plate_number ? ' <span style="font-weight:500;color:var(--text-muted);font-size:12px;">(' + esc(e.plate_number) + ')</span>' : '') + '</span>' + statePill + '</div>'
    + '<div class="att-driver"><i class="bi bi-person-badge"></i> ' + esc(e.driver_name || t('card.no_driver', 'No driver assigned')) + '</div>'
    + '<div class="att-rows">'
    +   '<div class="att-row"><span class="ar-label"><i class="bi bi-clock"></i> ' + esc(t('attendance.check_in_time', 'Check-in')) + '</span><span class="ar-value">' + esc(checkinTime) + ' · ' + esc(source) + '</span></div>'
    +   '<div class="att-row"><span class="ar-label"><i class="bi bi-clock-history"></i> ' + esc(t('attendance.check_out_time', 'Check-out')) + '</span><span class="ar-value">' + esc(checkoutTime + checkoutBy) + '</span></div>'
    +   '<div class="att-row"><span class="ar-label"><i class="bi bi-geo"></i> ' + esc(t('attendance.gps', 'GPS')) + '</span><span class="ar-value">' + attendanceGpsPill(p) + '</span></div>'
    +   '<div class="att-row"><span class="ar-label"><i class="bi bi-person-check"></i> ' + esc(t('attendance.availability', 'Availability')) + '</span><span class="ar-value">' + attendanceAvailability(e) + '</span></div>'
    + '</div>'
    + (actions.length ? '<div class="att-actions">' + actions.join('') + '</div>' : '')
    + '</article>';
}

function renderAttendance() {
  const el = document.getElementById('attendanceList');
  if (!el) return;
  if (!attendanceState.entries.length) {
    el.innerHTML = '<div class="empty-state"><i class="bi bi-bus-front"></i><p><strong>' + esc(t('state.no_buses_title', 'NO BUSES ASSIGNED')) + '</strong></p><p>' + esc(t('state.no_buses_body', 'No active buses are assigned to this terminal. Assign buses to the terminal to start dispatching.')) + '</p></div>';
    return;
  }
  el.innerHTML = attendanceState.entries.map(attendanceCardHTML).join('');
}

function renderAttendanceLoading() {
  const el = document.getElementById('attendanceList');
  if (!el) return;
  el.innerHTML = Array.from({ length: 3 }).map(() => '<div class="att-card skeleton-card"><div class="sk-line sk-lg"></div><div class="sk-line"></div><div class="sk-line sk-sm"></div></div>').join('');
}

function renderAttendanceError(msg) {
  const el = document.getElementById('attendanceList');
  if (!el) return;
  el.innerHTML = '<div class="empty-state"><i class="bi bi-exclamation-triangle" style="color:#d32f2f;"></i>'
    + '<p>' + esc(msg || t('state.error_body', 'Your current queue information could not be retrieved.')) + '</p>'
    + '<button type="button" class="btn-primary-app" style="max-width:180px;margin:10px auto;padding:8px 12px;font-size:13px;" onclick="loadAttendance({force:true})">' + esc(t('common.try_again', 'Try Again')) + '</button></div>';
}

function updateAttendanceUpdatedLabel() {
  const label = document.getElementById('attendanceUpdatedLabel');
  if (!label) return;
  if (!attendanceState.lastSuccess) { label.textContent = ''; return; }
  const secs = Math.max(0, Math.floor((Date.now() - attendanceState.lastSuccess) / 1000));
  label.textContent = secs < 5 ? t('common.updated_just_now', 'Queue updated just now') : t('common.updated_ago', 'Queue updated :seconds s ago', { seconds: secs });
}

async function loadAttendance(opts) {
  opts = opts || {};
  if (attendanceState.loading && !opts.force) return;
  attendanceState.loading = true;
  if (!attendanceState.started) renderAttendanceLoading();
  try {
    const r = await fetch(API + 'get_trip_queue', { headers: apiHeaders() });
    const j = await parseApiResponse(r);
    if (!j && (r.status === 404 || r.status === 410)) { recoverStaleApp(); return; }
    if (j && j.code === 'stale_api_route') { recoverStaleApp(); return; }
    if (r.status === 401) { renderAttendanceError(t('state.session_expired', 'Your session has expired. Please log in again.')); return; }
    if (!j || !j.success) {
      if (!attendanceState.started) renderAttendanceError(extractErrorMessage(r, j, t('state.error_body', 'Your current queue information could not be retrieved.')));
      return;
    }
    const d = j.data || {};
    attendanceState.entries = Array.isArray(d.entries) ? d.entries : [];
    attendanceState.lastSuccess = Date.now();
    attendanceState.started = true;
    renderAttendance();
    updateAttendanceUpdatedLabel();

    /* Availability set is best-effort: the screen still renders when it fails. */
    fetchJson(API + 'get_available_drivers', { headers: apiHeaders() }).then(function (ad) {
      const list = (ad && ad.success && Array.isArray(ad.data)) ? ad.data : [];
      attendanceState.availableDriverIds = new Set(list.map(function (x) { return String(x.driver_id); }));
      renderAttendance();
    }).catch(function () {});
  } catch (e) {
    console.error('loadAttendance error:', e);
    if (!attendanceState.started) renderAttendanceError(t('state.network_error', 'Network error. Please check your connection and try again.'));
  } finally {
    attendanceState.loading = false;
  }
}

function attendanceActionButton(busId, prefix) {
  return document.getElementById(prefix + attendanceSafeId(busId));
}

async function attendanceCheckIn(busId) {
  const btn = attendanceActionButton(busId, 'attCheckIn_');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + esc(t('action.check_in_driver', 'CHECK IN DRIVER')); }
  try {
    const r = await fetch(API + 'check_in_driver', {
      method: 'POST',
      headers: apiPostHeaders(),
      body: JSON.stringify({ bus_id: busId, csrf_token: CSRF_TOKEN })
    });
    const j = await parseApiResponse(r);
    if (isResponseSuccess(r, j)) {
      await loadAttendance({ force: true });
      showAlert('success', t('checkin.success_title', 'Driver Checked In'), t('checkin.success_body', 'You checked in driver :driver.', { driver: (j && j.data && j.data.driver_name) || busId }));
    } else {
      showAlert('error', t('alert.action_failed', 'Action Failed'), extractErrorMessage(r, j, t('checkin.error', 'The driver check-in could not be saved. Please try again.')));
    }
  } catch (e) {
    console.error('attendanceCheckIn error:', e);
    showAlert('error', t('alert.action_failed', 'Action Failed'), t('state.network_error', 'Network error. Please check your connection and try again.'));
  } finally {
    if (btn) { btn.disabled = false; }
  }
}

async function attendanceCheckOut(busId) {
  const btn = attendanceActionButton(busId, 'attCheckOut_');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + esc(t('attendance.checking_out', 'Checking out...')); }
  try {
    const r = await fetch(API + 'check_out_driver', {
      method: 'POST',
      headers: apiPostHeaders(),
      body: JSON.stringify({ bus_id: busId, csrf_token: CSRF_TOKEN })
    });
    const j = await parseApiResponse(r);
    if (isResponseSuccess(r, j)) {
      await loadAttendance({ force: true });
      showAlert('success', t('checkout.success_title', 'Driver Checked Out'), t('attendance.checkout_success', 'You checked out driver :driver.', { driver: (j && j.data && j.data.driver_name) || busId }));
    } else {
      showAlert('error', t('alert.action_failed', 'Action Failed'), extractErrorMessage(r, j, t('checkout.error', 'The driver check-out could not be saved. Please try again.')));
    }
  } catch (e) {
    console.error('attendanceCheckOut error:', e);
    showAlert('error', t('alert.action_failed', 'Action Failed'), t('state.network_error', 'Network error. Please check your connection and try again.'));
  } finally {
    if (btn) { btn.disabled = false; }
  }
}

loadAttendance({ force: true });
setInterval(updateAttendanceUpdatedLabel, 1000);
document.addEventListener('visibilitychange', function () {
  if (!document.hidden && attendanceState.started) loadAttendance();
});
