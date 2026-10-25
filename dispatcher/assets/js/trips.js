/* Trips screen: active, delayed, and completed trip monitoring with GPS status,
   trip details, arrival recording, and older-trip search. Shared helpers live in
   core.js; the queue screen keeps its own runtime. */

const tripsState = {
  trips: [],
  filter: 'all',
  lastSuccess: 0,
  loading: false,
  started: false
};
const tripDetailCache = {};
let arriveId = null;
let arriveStartOdo = null;
let arriveKplRef = null;
let isRecordingArrival = false;
let isReportingArrival = false;
let isSavingFuel = false;
let editFuelTrip = null;
let historyPage = 1;
let tripsFocusApplied = false;
let tripsArriveFocusApplied = false;
let currentFuelPrice = 65.55;

function isInboundTrip(v) {
  if (typeof v.is_inbound !== 'undefined' && v.is_inbound !== null) {
    return v.is_inbound === true || v.is_inbound === 1 || v.is_inbound === '1';
  }
  return Number(v.to_terminal_id) === TERMINAL_ID;
}

function tripArrivalPill(v) {
  if (v.status !== 'Departed' || !isInboundTrip(v)) return '';
  if (v.arrival_status === 'arrived_awaiting_confirmation') {
    return '<span class="arrival-pill arrival-awaiting"><i class="bi bi-flag"></i> ' + esc(t('trips.arrival_awaiting', 'Arrived · awaiting confirmation')) + '</span>';
  }
  return '<span class="arrival-pill arrival-onroute"><i class="bi bi-truck"></i> ' + esc(t('trips.arrival_on_route', 'On route')) + '</span>';
}

function tripArrivalKplText(v) {
  if (v.historical_kpl !== null && v.historical_kpl !== undefined && Number(v.historical_kpl) > 0) {
    return Number(v.historical_kpl).toFixed(2) + ' ' + t('form.kpl_unit', 'KM/L');
  }
  return t('form.no_historical_kpl', 'No historical KPL available');
}

function tripArrivalInfoHTML(v) {
  if (v.status !== 'Departed' || !isInboundTrip(v)) return '';
  const from = v.from_terminal_name || '—';
  const to = v.to_terminal_name || '—';
  const startOdo = (v.starting_odo !== null && v.starting_odo !== undefined && v.starting_odo !== '')
    ? Number(v.starting_odo).toFixed(1) + ' KM'
    : t('trips.not_recorded', 'Not recorded');
  return '<div class="arrival-info">'
    + '<span><i class="bi bi-geo-alt"></i> ' + esc(from) + ' → ' + esc(to) + '</span>'
    + '<span><i class="bi bi-speedometer2"></i> ' + esc(t('form.starting_odo', 'Starting Odometer (KM)')) + ': ' + esc(startOdo) + '</span>'
    + '<span><i class="bi bi-fuel-pump"></i> ' + esc(t('form.historical_kpl', 'Historical KPL (KM/L)')) + ': ' + esc(tripArrivalKplText(v)) + '</span>'
    + '</div>';
}

function tripGpsPill(v) {
  const g = v.gps || {};
  if (!g.available) {
    return '<span class="gps-pill gps-none"><i class="bi bi-geo-alt"></i> ' + esc(t('trips.gps_unavailable', 'GPS unavailable')) + '</span>';
  }
  if (g.fresh) {
    return '<span class="gps-pill gps-live"><i class="bi bi-broadcast"></i> ' + esc(t('trips.gps_live', 'GPS live')) + (g.speed_kmh != null ? ' · ' + esc(String(Math.round(Number(g.speed_kmh)))) + ' km/h' : '') + '</span>';
  }
  return '<span class="gps-pill gps-stale"><i class="bi bi-clock-history"></i> ' + esc(t('trips.gps_stale', 'GPS stale')) + (g.updated_at ? ' · ' + esc(formatClock12(g.updated_at)) : '') + '</span>';
}

function tripDelayPill(v) {
  const d = v.delay || {};
  if (!d.delayed) return '';
  let extra = '';
  if (d.recorded_minutes != null) extra = ' · ' + esc(String(d.recorded_minutes)) + ' min';
  else if (d.overdue) extra = ' · ' + esc(t('trips.delay_overdue', 'overdue'));
  return '<span class="delay-pill"><i class="bi bi-exclamation-triangle"></i> ' + esc(t('trips.delayed', 'Delayed')) + extra + '</span>';
}

function nextActionFor(v) {
  if (v.status === 'Scheduled') return { icon: 'bi-send', text: t('trips.next_waiting_dispatch', 'Next: waiting for dispatch') };
  if (v.status === 'Departed' && Number(v.to_terminal_id) === TERMINAL_ID) return { icon: 'bi-flag', text: t('trips.next_record_arrival', 'Next: record arrival') };
  if (v.status === 'Completed') return { icon: 'bi-check-circle', text: t('trips.next_arrived', 'Trip arrived') };
  if (v.status === 'Interrupted') return { icon: 'bi-exclamation-triangle', text: t('trips.next_problem', 'Next: trip problem report pending') };
  return null;
}

function cardHTML(v, historyMode) {
  const st = statusLabel(v);
  const dur = durationLabel(v);
  const route = routeArrow(v.route_name);
  const next = nextActionFor(v);
  tripDetailCache[v.id] = v;
  const orderInfo = [];
  if (v.dispatch_order) orderInfo.push('<span><i class="bi bi-list-ol"></i> ' + esc(t('trips.dispatch_order', 'Order')) + ' ' + ordinal(v.dispatch_order) + '</span>');
  if (v.queue_position != null) orderInfo.push('<span><i class="bi bi-hash"></i> ' + esc(t('trips.queue_position', 'Queue')) + ' #' + esc(String(v.queue_position)) + '</span>');
  let html = `<div class="dispatch-card" data-trip-id="${esc(String(v.id))}">
    <div class="dispatch-header">
      <span class="dispatch-id"><i class="bi bi-hash"></i> Trip ${esc(String(v.trip_number || v.id))}</span>
      <span class="trip-state-pills"><span class="status-badge status-${esc(v.status)}">${st}</span>${tripDelayPill(v)}${tripArrivalPill(v)}</span>
    </div>
    <div class="dispatch-busrow">
      <span class="bus-line"><i class="bi bi-bus-front"></i> ${esc(v.bus_id)}</span>
      <span class="driver-line"><i class="bi bi-person"></i> ${esc(v.driver_name || v.driver_id || '')}</span>
    </div>
    <div class="dispatch-route"><i class="bi bi-geo-alt"></i> ${esc(route)}</div>
    <div class="dispatch-meta">
      <span><i class="bi bi-clock"></i> ${v.departure_time || '-'}</span>
      ${v.arrival_time ? `<span><i class="bi bi-flag"></i> ${v.arrival_time}</span>` : ''}
      <span><i class="bi bi-people"></i> ${v.total_passengers || '0'} ${v.total_passengers == 1 ? 'passenger' : 'passengers'}</span>
      ${dur ? `<span><i class="bi bi-hourglass"></i> ${dur}</span>` : ''}
      ${v.starting_odo !== null && v.starting_odo !== undefined && v.starting_odo !== '' ? `<span><i class="bi bi-speedometer2"></i> ${v.starting_odo}${v.ending_odo ? '→' + v.ending_odo : ''} KM</span>` : ''}
      ${v.fuel_liters !== null && v.fuel_liters !== undefined && parseFloat(v.fuel_liters) > 0 ? `<span><i class="bi bi-fuel-pump"></i> ${parseFloat(v.fuel_liters).toFixed(1)}L</span>` : ''}
    </div>
    ${tripArrivalInfoHTML(v)}
    ${(v.status === 'Departed' || orderInfo.length) ? `<div class="trip-busy-row">${v.status === 'Departed' ? tripGpsPill(v) : ''}${orderInfo.join('')}</div>` : ''}
    <div class="dispatch-actions">
      <button class="btn-action btn-view" onclick="openTripDetail(${Number(v.id)})"><span class="btn-ico"><i class="bi bi-eye"></i></span> ${esc(t('action.view_trip', 'View Trip'))}</button>
      <button class="btn-action btn-view" onclick="openEditFuelModal(${Number(v.id)})" title="Fuel & ODO Record"><span class="btn-ico"><i class="bi bi-fuel-pump"></i></span> ${esc(t('action.fuel', 'Fuel/ODO'))}</button>
      ${(v.status === 'Departed' && isInboundTrip(v) && v.arrival_status === 'on_route') ? `<button class="btn-action btn-arrive" onclick="reportArrived(${Number(v.id)})"><i class="bi bi-flag"></i> ${esc(t('trips.arrived_button', 'Arrived'))}</button>` : ''}
      ${(v.status === 'Departed' && isInboundTrip(v)) ? `<button class="btn-action btn-arrive" onclick="openArrive(${Number(v.id)})"><i class="bi bi-check-circle"></i> ${esc(t('trips.confirm_arrival_button', 'Confirm Arrival'))}</button>` : ''}
      ${v.status === 'Scheduled' ? `<button class="btn-action btn-cancel" onclick="quickAction(${Number(v.id)},'cancel')">${esc(t('trips.cancel_trip', 'Cancel Trip'))}</button>` : ''}
      ${next ? `<span class="dispatch-next"><i class="bi ${next.icon}"></i> ${esc(next.text)}</span>` : ''}
    </div>
  </div>`;
  return html;
}

function setTripFilter(el, state) {
  tripsState.filter = state || 'all';
  document.querySelectorAll('#tripFilterRow .filter-chip').forEach(c => c.classList.remove('active'));
  if (el) el.classList.add('active');
  renderTrips();
}

function filteredTrips() {
  const list = tripsState.trips.slice();
  switch (tripsState.filter) {
    case 'active': return list.filter(v => v.status === 'Departed');
    case 'inbound': return list.filter(v => v.status === 'Departed' && isInboundTrip(v));
    case 'delayed': return list.filter(v => v.delay && v.delay.delayed);
    case 'completed': return list.filter(v => v.status === 'Completed');
    case 'Scheduled': return list.filter(v => v.status === 'Scheduled');
    case 'Cancelled': return list.filter(v => v.status === 'Cancelled');
    default: return list;
  }
}

function renderTrips() {
  const el = document.getElementById('tripsList');
  if (!el) return;
  const list = filteredTrips();
  if (!list.length) {
    if (!tripsState.trips.length) {
      el.innerHTML = '<div class="empty-state"><i class="bi bi-signpost-split"></i><p><strong>' + esc(t('trips.empty_title', 'NO TRIPS TODAY')) + '</strong></p><p>' + esc(t('trips.empty_body', 'Trips will appear here once they are scheduled or dispatched.')) + '</p></div>';
    } else {
      el.innerHTML = '<div class="empty-state"><i class="bi bi-funnel"></i><p>' + esc(t('trips.no_match', 'No trips match this filter.')) + '</p></div>';
    }
    return;
  }
  el.innerHTML = list.map(v => cardHTML(v)).join('');
}

function renderTripsLoading() {
  const el = document.getElementById('tripsList');
  if (!el) return;
  el.innerHTML = Array.from({ length: 3 }).map(() => '<div class="dispatch-card skeleton-card"><div class="sk-line sk-lg"></div><div class="sk-line"></div><div class="sk-line sk-sm"></div></div>').join('');
}

function renderTripsError(msg) {
  const el = document.getElementById('tripsList');
  if (!el) return;
  el.innerHTML = '<div class="empty-state"><i class="bi bi-exclamation-triangle" style="color:#d32f2f;"></i><p>' + esc(msg || t('state.error_body', 'Your current queue information could not be retrieved.')) + '</p><button type="button" class="btn-primary-app" style="max-width:180px;margin:10px auto;padding:8px 12px;font-size:13px;" onclick="loadTodayTrips({force:true})">' + esc(t('common.try_again', 'Try Again')) + '</button></div>';
}

function updateTripsUpdatedLabel() {
  const label = document.getElementById('tripsUpdatedLabel');
  if (!label) return;
  if (!tripsState.lastSuccess) { label.textContent = ''; return; }
  const secs = Math.max(0, Math.floor((Date.now() - tripsState.lastSuccess) / 1000));
  label.textContent = secs < 5 ? t('common.updated_just_now', 'Queue updated just now') : t('common.updated_ago', 'Queue updated :seconds s ago', { seconds: secs });
}

async function loadTodayTrips(opts) {
  opts = opts || {};
  if (tripsState.loading && !opts.force) return;
  tripsState.loading = true;
  if (!tripsState.started) renderTripsLoading();
  try {
    const r = await fetch(API + 'get_dispatches?date=' + getPHTDateStr() + '&terminal_id=' + TERMINAL_ID, { headers: apiHeaders() });
    const j = await parseApiResponse(r);
    if (!j && (r.status === 404 || r.status === 410)) { recoverStaleApp(); return; }
    if (j && j.code === 'stale_api_route') { recoverStaleApp(); return; }
    if (r.status === 401) { renderTripsError(t('state.session_expired', 'Your session has expired. Please log in again.')); return; }
    if (!j || !j.success) {
      if (!tripsState.started) renderTripsError(extractErrorMessage(r, j));
      return;
    }
    const list = Array.isArray(j.data) ? j.data : [];
    tripsState.trips = list;
    list.forEach(v => { tripDetailCache[v.id] = v; });
    tripsState.lastSuccess = Date.now();
    tripsState.started = true;
    renderTrips();
    updateTripsUpdatedLabel();
    focusTripIfRequested();
    focusArriveIfRequested();
  } catch (e) {
    console.error('loadTodayTrips error:', e);
    if (!tripsState.started) renderTripsError(t('state.network_error', 'Network error. Please check your connection and try again.'));
  } finally {
    tripsState.loading = false;
  }
}

function focusTripIfRequested() {
  if (tripsFocusApplied) return;
  let tripId = '';
  try { tripId = new URLSearchParams(window.location.search).get('focus_trip') || ''; } catch (e) {}
  if (!tripId) return;
  tripsFocusApplied = true;
  const card = document.querySelector('[data-trip-id="' + tripId.replace(/["\\]/g, '\\$&') + '"]');
  if (card) {
    try { card.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) { card.scrollIntoView(); }
  }
  if (tripDetailCache[tripId]) openTripDetail(tripId);
}

/* Arrived at this terminal from the Home screen: open the confirmation
   dialog (ending odometer) for the requested trip once the list is loaded. */
function focusArriveIfRequested() {
  if (tripsArriveFocusApplied) return;
  let tripId = '';
  try { tripId = new URLSearchParams(window.location.search).get('arrive_trip') || ''; } catch (e) {}
  if (!tripId) return;
  const id = Number(tripId);
  if (!id || !tripDetailCache[id]) return;
  tripsArriveFocusApplied = true;
  openArrive(id);
}

function openTripDetail(id) {
  const v = tripDetailCache[id];
  if (!v) return;
  const st = statusLabel(v);
  const dur = durationLabel(v);
  const dist = v.distance_traveled !== null && v.distance_traveled !== undefined
    ? v.distance_traveled + ' KM'
    : (v.starting_odo !== null && v.ending_odo !== null && v.starting_odo !== undefined && v.ending_odo !== undefined
        ? (v.ending_odo - v.starting_odo).toFixed(1) + ' KM'
        : '-');
  const expFuel = v.expected_fuel !== null && v.expected_fuel !== undefined ? v.expected_fuel + ' L' : '-';

  const gpsLine = (v.status === 'Departed')
    ? `<div class="trip-detail-row"><span class="td-label"><i class="bi bi-geo"></i> ${esc(t('attendance.gps', 'GPS'))}</span><span class="td-value">${tripGpsPill(v)}${v.gps && v.gps.updated_at ? ' <small>' + esc(formatClock12(v.gps.updated_at)) + '</small>' : ''}</span></div>`
    : '';
  const delayLine = (v.delay && v.delay.delayed)
    ? `<div class="trip-detail-row"><span class="td-label"><i class="bi bi-exclamation-triangle"></i> ${esc(t('trips.delayed', 'Delayed'))}</span><span class="td-value">${v.delay.recorded_minutes != null ? esc(String(v.delay.recorded_minutes)) + ' min' : esc(t('trips.delay_overdue', 'overdue'))}</span></div>`
    : '';

  document.getElementById('tripDetailTitle').innerHTML = '<i class="bi bi-info-circle"></i> Trip ' + esc(String(v.trip_number || v.id));
  document.getElementById('tripDetailBody').innerHTML =
    `<div class="trip-hero">
       <div class="trip-hero-top">
         <span class="trip-hero-id"><i class="bi bi-hash"></i> Trip ${esc(String(v.trip_number || v.id))}</span>
         <span class="status-badge status-${esc(v.status)}">${st}</span>
       </div>
       <div class="trip-hero-route"><i class="bi bi-signpost-split"></i> ${esc(routeArrow(v.route_name))}</div>
       <div class="trip-hero-date"><i class="bi bi-calendar3"></i> ${v.trip_date || '-'}</div>
     </div>
     <div class="trip-info-grid">
       <div class="trip-info-card">
         <div class="tic-icon bus"><i class="bi bi-bus-front"></i></div>
         <div class="tic-label">Bus</div>
         <div class="tic-value">${esc(v.bus_id)}</div>
       </div>
       <div class="trip-info-card">
         <div class="tic-icon driver"><i class="bi bi-person-badge"></i></div>
         <div class="tic-label">Driver</div>
         <div class="tic-value">${esc(v.driver_name || v.driver_id || '-')}</div>
       </div>
     </div>
     <div class="trip-detail-grid">
       <div class="trip-detail-row"><span class="td-label"><i class="bi bi-person-check"></i> Dispatched By</span><span class="td-value">${esc(v.dispatcher_name || v.dispatcher_id || '-')}</span></div>
       <div class="trip-detail-row"><span class="td-label"><i class="bi bi-list-ol"></i> Queue Order</span><span class="td-value">${v.dispatch_order ? ordinal(v.dispatch_order) : '—'}${v.queue_position != null ? ' · #' + esc(String(v.queue_position)) : ''}</span></div>
       <div class="trip-detail-row"><span class="td-label"><i class="bi bi-send"></i> Dispatch Time</span><span class="td-value">${v.departure_time || '-'}</span></div>
        ${(v.from_terminal_name || v.to_terminal_name) ? `<div class="trip-detail-row"><span class="td-label"><i class="bi bi-signpost-split"></i> Terminal</span><span class="td-value">${esc(v.from_terminal_name || '-')} → ${esc(v.to_terminal_name || '-')}</span></div>` : ''}
        ${(v.status === 'Departed' && isInboundTrip(v)) ? `<div class="trip-detail-row"><span class="td-label"><i class="bi bi-flag"></i> Arrival Status</span><span class="td-value">${tripArrivalPill(v)}</span></div>` : ''}
       ${v.arrival_by ? `<div class="trip-detail-row"><span class="td-label"><i class="bi bi-person-check"></i> Arrived By</span><span class="td-value">${esc(v.arrival_by)}${v.arrival_recorded_at ? ' • ' + v.arrival_recorded_at : ''}</span></div>` : ''}
       ${v.arrival_time ? `<div class="trip-detail-row"><span class="td-label"><i class="bi bi-flag"></i> Arrival</span><span class="td-value">${v.arrival_time}</span></div>` : ''}
       ${dur ? `<div class="trip-detail-row"><span class="td-label"><i class="bi bi-hourglass"></i> Duration</span><span class="td-value">${dur}</span></div>` : ''}
       <div class="trip-detail-row"><span class="td-label"><i class="bi bi-people"></i> Passengers</span><span class="td-value">${v.total_passengers || '0'}</span></div>
       ${gpsLine}
       ${delayLine}
       <div class="trip-detail-row" style="flex-direction:column;align-items:stretch;gap:8px;background:#faf9e5;border-color:#e5e0a4;">
         <div style="display:flex;justify-content:space-between;align-items:center;font-weight:700;color:var(--text-primary);font-size:12px;">
           <span><i class="bi bi-fuel-pump" style="color:var(--primary);"></i> Fuel & Odometer Information</span>
           <span style="font-size:11px;color:var(--text-muted);">${v.price_per_liter ? 'Diesel: ₱' + Number(v.price_per_liter).toFixed(2) + '/L' : ''}</span>
         </div>
         <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:12px;">
           <div><span style="color:var(--text-muted);font-size:10px;display:block;">STARTING ODO</span><strong>${v.starting_odo !== null && v.starting_odo !== undefined ? v.starting_odo + ' KM' : '-'}</strong></div>
           <div><span style="color:var(--text-muted);font-size:10px;display:block;">ENDING ODO</span><strong>${v.ending_odo !== null && v.ending_odo !== undefined ? v.ending_odo + ' KM' : '<em style="color:#888;">Not yet recorded</em>'}</strong></div>
            <div><span style="color:var(--text-muted);font-size:10px;display:block;">DISTANCE TRAVELED</span><strong>${dist}</strong></div>
            <div><span style="color:var(--text-muted);font-size:10px;display:block;">ACTUAL KPL</span><strong>${(v.actual_kpl !== null && v.actual_kpl !== undefined) ? v.actual_kpl + ' KM/L' : '-'}</strong></div>
            <div><span style="color:var(--text-muted);font-size:10px;display:block;">BUS KPL</span><strong>${(v.historical_kpl !== null && v.historical_kpl !== undefined && Number(v.historical_kpl) > 0) ? Number(v.historical_kpl).toFixed(2) + ' KM/L' : (v.kpl ? v.kpl + ' KM/L' : '-')}</strong></div>
           <div><span style="color:var(--text-muted);font-size:10px;display:block;">ACTUAL FUEL</span><strong>${v.fuel_liters ? v.fuel_liters + ' L' : '-'}</strong></div>
           <div><span style="color:var(--text-muted);font-size:10px;display:block;">EXPECTED CONSUMPTION</span><strong>${expFuel}</strong></div>
         </div>
       </div>
     </div>`;
  const actions = [];
  actions.push(`<button class="btn-action btn-view" onclick="closeModal('tripDetailModal');openEditFuelModal(${Number(v.id)})"><i class="bi bi-fuel-pump"></i> ${esc(t('trips.edit_fuel', 'Edit Fuel & ODO'))}</button>`);
  if (v.status === 'Departed' && isInboundTrip(v) && v.arrival_status === 'on_route') actions.push(`<button class="btn-action btn-arrive" onclick="closeModal('tripDetailModal');reportArrived(${Number(v.id)})"><i class="bi bi-flag"></i> ${esc(t('trips.arrived_button', 'Arrived'))}</button>`);
  if (v.status === 'Departed' && isInboundTrip(v)) actions.push(`<button class="btn-action btn-arrive" onclick="closeModal('tripDetailModal');openArrive(${Number(v.id)})"><i class="bi bi-check-circle"></i> ${esc(t('trips.confirm_arrival_button', 'Confirm Arrival'))}</button>`);
  if (v.status === 'Scheduled') actions.push(`<button class="btn-action btn-cancel" onclick="quickAction(${Number(v.id)},'cancel')"><i class="bi bi-x-circle"></i> ${esc(t('trips.cancel_trip', 'Cancel Trip'))}</button>`);
  document.getElementById('tripDetailActions').innerHTML = actions.join('');
  showModal('tripDetailModal');
}

function openArrive(id) {
  arriveId = id;
  const v = tripDetailCache[id];
  arriveStartOdo = (v && v.starting_odo !== null && v.starting_odo !== undefined && v.starting_odo !== '')
    ? Number(v.starting_odo)
    : null;
  arriveKplRef = (v && v.historical_kpl !== null && v.historical_kpl !== undefined && Number(v.historical_kpl) > 0)
    ? Number(v.historical_kpl)
    : null;
  const infoEl = document.getElementById('arriveTripOdoInfo');
  if (infoEl) {
    infoEl.textContent = t('trips.starting_odo_label', 'Starting ODO') + ': '
      + (arriveStartOdo !== null ? arriveStartOdo + ' KM' : t('trips.not_recorded', 'Not recorded'))
      + ' • ' + t('form.historical_kpl', 'Historical KPL (KM/L)') + ': '
      + (arriveKplRef !== null ? arriveKplRef.toFixed(2) + ' ' + t('form.kpl_unit', 'KM/L') : t('form.no_historical_kpl', 'No historical KPL available'));
  }
  const endInput = document.getElementById('arriveEndingOdo');
  if (endInput) {
    endInput.value = '';
  }
  const err = document.getElementById('arriveError');
  if (err) { err.style.display = 'none'; err.textContent = ''; }
  onArriveOdoChange();
  showModal('arriveModal');
}

function onArriveOdoChange() {
  const endInput = document.getElementById('arriveEndingOdo');
  const endVal = parseFloat(endInput ? endInput.value : '');
  const preview = document.getElementById('arriveCalcPreview');
  if (!preview) return;
  const valid = !isNaN(endVal) && endVal > 0 && (arriveStartOdo === null || endVal >= arriveStartOdo);
  if (!valid) {
    preview.style.display = 'none';
    return;
  }
  let distText = t('trips.not_available', 'Not available');
  let expText = '-';
  if (arriveStartOdo !== null) {
    const dist = endVal - arriveStartOdo;
    distText = dist.toFixed(1) + ' KM';
    expText = (arriveKplRef !== null && arriveKplRef > 0)
      ? (dist / arriveKplRef).toFixed(2) + ' L'
      : t('trips.no_estimate', 'No estimate (no historical KPL)');
  }
  document.getElementById('arriveDistText').textContent = distText;
  document.getElementById('arriveExpFuelText').textContent = expText;
  preview.style.display = 'block';
}

async function recordArrival() {
  if (!arriveId || isRecordingArrival) return;
  const confirmBtn = document.getElementById('arriveConfirmBtn');
  const errEl = document.getElementById('arriveError');
  const showError = msg => {
    if (errEl) { errEl.textContent = msg; errEl.style.display = 'block'; }
  };
  if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }

  const endingRaw = document.getElementById('arriveEndingOdo')?.value || '';
  const ending_odo = parseFloat(endingRaw);
  if (!endingRaw || isNaN(ending_odo) || ending_odo <= 0) {
    showError(t('trips.ending_odo_required_body', "Enter the bus's actual ending odometer to confirm the arrival."));
    return;
  }
  if (arriveStartOdo !== null && ending_odo < arriveStartOdo) {
    showError(t('trips.invalid_odometer_body', 'Ending ODO cannot be less than Starting ODO.'));
    return;
  }

  const now = new Date();
  const pad2 = n => String(n).padStart(2, '0');
  const arrival_time = pad2(now.getHours()) + ':' + pad2(now.getMinutes()) + ':' + pad2(now.getSeconds());
  const body = { dispatch_id: arriveId, action: 'arrive', arrival_time, ending_odo: ending_odo, csrf_token: CSRF_TOKEN };

  isRecordingArrival = true;
  if (confirmBtn) {
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + esc(t('trips.confirming_arrival', 'Confirming Arrival...'));
  }

  try {
    const r = await fetch(API + 'update_dispatch', {
      method: 'POST',
      headers: apiPostHeaders(),
      body: JSON.stringify(body)
    });
    const j = await parseApiResponse(r);
    const confirmed = isResponseSuccess(r, j);

    if (confirmed) {
      closeModal('arriveModal');
      arriveId = null;
      arriveStartOdo = null;
      arriveKplRef = null;
      await loadTodayTrips({ force: true });

      let msg = t('trips.arrival_recorded_body', 'Trip arrival recorded successfully.');
      if (j && j.total_passengers !== undefined && j.duration_minutes !== undefined) {
        msg += '\nPassengers: ' + j.total_passengers + ' • Duration: ' + j.duration_minutes + ' min';
      }
      if (j && j.fuel_data && j.fuel_data.distance_traveled !== null && j.fuel_data.distance_traveled !== undefined) {
        msg += '\nDistance: ' + j.fuel_data.distance_traveled + ' KM';
        if (j.fuel_data.expected_fuel !== null && j.fuel_data.expected_fuel !== undefined) {
          msg += ' • Expected Fuel (estimate): ' + j.fuel_data.expected_fuel + ' L';
        }
      }
      showAlert('success', t('trips.arrival_recorded', 'Arrival Recorded'), msg);
    } else {
      console.error('recordArrival failed:', r ? r.status : 'no-response', j);
      showError(extractErrorMessage(r, j, t('trips.arrival_failed_body', 'Unable to record arrival. Please try again.')));
    }
  } catch (e) {
    console.error('recordArrival error:', e);
    showError(t('state.network_error', 'Network error. Please check your connection and try again.'));
  } finally {
    isRecordingArrival = false;
    if (confirmBtn) {
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = '<i class="bi bi-check-circle"></i> ' + esc(t('trips.confirm_arrival_button', 'Confirm Arrival'));
    }
  }
}

async function reportArrived(id) {
  if (isReportingArrival) return;
  isReportingArrival = true;
  try {
    const r = await fetch(API + 'update_dispatch', {
      method: 'POST',
      headers: apiPostHeaders(),
      body: JSON.stringify({ dispatch_id: id, action: 'report_arrival', csrf_token: CSRF_TOKEN })
    });
    const j = await parseApiResponse(r);
    if (isResponseSuccess(r, j)) {
      await loadTodayTrips({ force: true });
      showAlert('success', t('trips.arrival_reported_title', 'Arrival Reported'), t('trips.arrival_reported_body', 'The trip is marked arrived and awaits confirmation with the ending odometer.'));
    } else {
      showAlert('error', t('trips.arrival_failed', 'Arrival Failed'), extractErrorMessage(r, j, t('trips.arrival_report_failed', 'Unable to report the arrival. Please try again.')));
    }
  } catch (e) {
    console.error('reportArrived error:', e);
    showAlert('error', t('trips.arrival_failed', 'Arrival Failed'), t('state.network_error', 'Network error. Please check your connection and try again.'));
  } finally {
    isReportingArrival = false;
  }
}

function openEditFuelModal(id) {
  const v = tripDetailCache[id];
  if (!v) return;
  editFuelTrip = v;
  document.getElementById('editFuelTripId').value = v.id;
  document.getElementById('editFuelLogId').value = v.fuel_log_id || '';
  document.getElementById('editFuelTripNo').textContent = v.trip_number || v.id;
  document.getElementById('editFuelRoute').textContent = routeArrow(v.route_name);
  document.getElementById('editFuelBus').textContent = v.bus_id + (v.plate_number ? ' (' + v.plate_number + ')' : '');
  document.getElementById('editFuelDriver').textContent = v.driver_name || v.driver_id || '-';

  document.getElementById('editStartingOdo').value = (v.starting_odo !== null && v.starting_odo !== undefined) ? v.starting_odo : '';
  document.getElementById('editEndingOdo').value = (v.ending_odo !== null && v.ending_odo !== undefined) ? v.ending_odo : '';
  document.getElementById('editKpl').value = (v.kpl !== null && v.kpl !== undefined && Number(v.kpl) > 0) ? v.kpl : '';
  document.getElementById('editActualLiters').value = v.fuel_liters || '';
  document.getElementById('editFuelReason').value = '';

  const price = Number(v.price_per_liter || currentFuelPrice || 65.55);
  document.getElementById('editFuelPriceText').textContent = '₱' + price.toFixed(2) + '/L';

  onEditFuelCalc();
  showModal('editFuelModal');
}

function onEditFuelCalc() {
  const start = parseFloat(document.getElementById('editStartingOdo').value);
  const end = parseFloat(document.getElementById('editEndingOdo').value);
  const kpl = parseFloat(document.getElementById('editKpl').value);
  const liters = parseFloat(document.getElementById('editActualLiters').value) || 0;
  const price = editFuelTrip && editFuelTrip.price_per_liter ? Number(editFuelTrip.price_per_liter) : (currentFuelPrice || 65.55);

  if (!isNaN(start) && !isNaN(end) && end >= start) {
    const dist = (end - start).toFixed(1);
    document.getElementById('calcDistanceText').textContent = dist + ' KM';
    document.getElementById('calcExpectedFuelText').textContent = (kpl > 0) ? (dist / kpl).toFixed(2) + ' L' : '-';
    document.getElementById('calcActualKplText').textContent = (liters > 0 && (end - start) > 0)
      ? ((end - start) / liters).toFixed(2) + ' KM/L'
      : '-';
  } else if (!isNaN(start) && !isNaN(end) && end < start) {
    document.getElementById('calcDistanceText').textContent = 'Ending ODO must be ≥ Starting ODO';
    document.getElementById('calcExpectedFuelText').textContent = '-';
    document.getElementById('calcActualKplText').textContent = '-';
  } else {
    document.getElementById('calcDistanceText').textContent = '-';
    document.getElementById('calcExpectedFuelText').textContent = '-';
    document.getElementById('calcActualKplText').textContent = '-';
  }

  if (liters > 0) {
    document.getElementById('calcFuelCostText').textContent = '₱' + (liters * price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  } else {
    document.getElementById('calcFuelCostText').textContent = '-';
  }
}

async function saveTripFuelEdit() {
  if (isSavingFuel) return;
  const trip_id = document.getElementById('editFuelTripId').value;
  const fuel_log_id = document.getElementById('editFuelLogId').value;
  const starting_odo = document.getElementById('editStartingOdo').value;
  const ending_odo = document.getElementById('editEndingOdo').value;
  const kpl = document.getElementById('editKpl').value;
  const actual_liters = document.getElementById('editActualLiters').value;
  const reason = document.getElementById('editFuelReason').value.trim();
  const saveBtn = document.getElementById('saveFuelBtn');

  if (ending_odo !== '' && starting_odo !== '' && parseFloat(ending_odo) < parseFloat(starting_odo)) {
    showAlert('error', t('trips.invalid_odometer', 'Invalid Odometer'), t('trips.invalid_odometer_body', 'Ending ODO cannot be less than Starting ODO.'));
    return;
  }
  if (reason === '') {
    showAlert('error', t('trips.reason_required', 'Reason Required'), t('trips.reason_required_body', 'Please enter a reason for editing this fuel record.'));
    return;
  }

  isSavingFuel = true;
  if (saveBtn) {
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + esc(t('trips.saving_record', 'Saving Record...'));
  }

  try {
    const r = await fetch(API + 'update_trip_fuel', {
      method: 'POST',
      headers: apiPostHeaders(),
      body: JSON.stringify({
        trip_id,
        fuel_log_id,
        starting_odo,
        ending_odo,
        kpl,
        actual_liters,
        reason,
        csrf_token: CSRF_TOKEN
      })
    });
    const j = await parseApiResponse(r);
    const confirmed = isResponseSuccess(r, j);

    if (confirmed) {
      closeModal('editFuelModal');
      if (tripDetailCache[trip_id] && j && j.data) {
        const d = j.data;
        Object.assign(tripDetailCache[trip_id], {
          starting_odo: d.starting_odo,
          ending_odo: d.ending_odo,
          kpl: d.kpl,
          actual_kpl: d.actual_kpl,
          distance_traveled: d.distance_traveled,
          expected_fuel: d.expected_fuel,
          fuel_liters: d.actual_liters,
          fuel_cost: d.fuel_cost
        });
      }
      await loadTodayTrips({ force: true });
      showAlert('success', t('trips.record_saved', 'Record Saved'), t('trips.record_saved_body', 'Fuel & odometer record saved successfully.'));
    } else {
      const errMsg = extractErrorMessage(r, j, t('trips.save_failed_body', 'Unable to save fuel record. Please try again.'));
      showAlert('error', t('trips.save_failed', 'Save Failed'), errMsg);
    }
  } catch (e) {
    console.error('saveTripFuelEdit error:', e);
    showAlert('error', t('trips.save_failed', 'Save Failed'), t('state.network_error', 'Network error. Please check your connection and try again.'));
  } finally {
    isSavingFuel = false;
    if (saveBtn) {
      saveBtn.disabled = false;
      saveBtn.innerHTML = '<i class="bi bi-check-circle"></i> ' + esc(t('trips.save_fuel_record', 'Save Fuel Record'));
    }
  }
}

async function quickAction(id, action) {
  try {
    const r = await fetch(API + 'update_dispatch', { method: 'POST', headers: apiPostHeaders(), body: JSON.stringify({ dispatch_id: id, action, csrf_token: CSRF_TOKEN }) });
    const j = await parseApiResponse(r);
    if (isResponseSuccess(r, j)) {
      await loadTodayTrips({ force: true });
      const actionName = action === 'cancel' ? t('trips.cancelled_title', 'Trip Cancelled') : t('trips.action_done', 'Action Completed');
      const actionMsg = action === 'cancel' ? t('trips.cancelled_body', 'The trip has been cancelled successfully.') : t('trips.action_done_body', 'Action completed successfully.');
      showAlert('success', actionName, actionMsg);
    } else {
      const errMsg = extractErrorMessage(r, j, t('trips.action_failed_body', 'Action could not be completed. Please try again.'));
      showAlert('error', t('alert.action_failed', 'Action Failed'), errMsg);
    }
  } catch (e) {
    console.error('quickAction error:', e);
    showAlert('error', t('alert.action_failed', 'Action Failed'), t('state.network_error', 'Network error. Please check your connection and try again.'));
  }
}

function toggleHistorySearch() {
  const panel = document.getElementById('historySearchPanel');
  const btn = document.getElementById('historySearchBtn');
  const show = panel.style.display === 'none';
  if (show) {
    panel.style.display = 'block';
    document.getElementById('historyControls').style.display = 'flex';
    document.getElementById('paginationControls').style.display = 'none';
    btn.setAttribute('aria-expanded', 'true');
    btn.innerHTML = '<i class="bi bi-arrow-left"></i> ' + esc(t('history.hide_search', 'Hide Search'));
    loadHistory();
  } else {
    panel.style.display = 'none';
    document.getElementById('historyControls').style.display = 'none';
    document.getElementById('paginationControls').style.display = 'none';
    btn.setAttribute('aria-expanded', 'false');
    btn.innerHTML = '<i class="bi bi-search"></i> ' + esc(t('history.open_search', 'Open'));
  }
}

async function loadHistory() {
  const el = document.getElementById('dispatchList');
  el.innerHTML = '<div class="loading">' + esc(t('common.loading', 'Loading...')) + '</div>';
  let params = 'page=' + historyPage + '&limit=20';
  const df = document.getElementById('histDateFrom').value;
  const dt = document.getElementById('histDateTo').value;
  if (df) params += '&date_from=' + encodeURIComponent(df);
  if (dt) params += '&date_to=' + encodeURIComponent(dt);
  const q = (document.getElementById('histBusInput').value || '').trim();
  if (q) params += '&bus_id=' + encodeURIComponent(q);
  try {
    const r = await fetch(API + 'get_dispatch_history?' + params, { headers: apiHeaders() });
    const j = await parseApiResponse(r);

    if (r.status === 401) {
      el.innerHTML = '<div class="empty-state"><i class="bi bi-shield-lock" style="color:#f57c00;"></i><p>' + esc(t('state.session_expired', 'Your session has expired. Please log in again.')) + '</p></div>';
      return;
    }
    if (!j || !j.success) {
      const msg = extractErrorMessage(r, j, t('trips.history_failed', 'Failed to load history. Please try again.'));
      el.innerHTML = `<div class="empty-state"><i class="bi bi-exclamation-triangle" style="color:#d32f2f;"></i><p>${esc(msg)}</p><button class="btn-primary-app" style="max-width:140px;margin:10px auto;padding:6px 12px;font-size:12px;" onclick="loadHistory()">${esc(t('common.retry', 'Retry'))}</button></div>`;
      return;
    }
    const d = j.data || [];
    el.innerHTML = '';
    if (!d.length) { el.innerHTML = '<div class="empty-state"><i class="bi bi-clock-history"></i><p>' + esc(t('trips.history_empty', 'No trips found in this date range')) + '</p></div>'; return; }

    // Deduplicate history trips by id
    const seen = new Set();
    const uniqueHistory = [];
    for (const item of d) {
      const tripKey = item.id || item.trip_number;
      if (tripKey && !seen.has(tripKey)) {
        seen.add(tripKey);
        uniqueHistory.push(item);
      }
    }

    const totalLabel = (j.total ?? uniqueHistory.length) + ' ' + ((j.total ?? uniqueHistory.length) !== 1 ? t('trips.trips_plural', 'trips') : t('trips.trip_singular', 'trip'));
    document.getElementById('histResultBadge').textContent = totalLabel;
    el.innerHTML = uniqueHistory.map(v => cardHTML(v, true)).join('');
    document.getElementById('paginationControls').style.display = 'flex';
    document.getElementById('pageInfo').textContent = t('trips.page', 'Page') + ' ' + j.page + ' / ' + j.pages;
    document.getElementById('prevPageBtn').disabled = j.page <= 1;
    document.getElementById('nextPageBtn').disabled = j.page >= j.pages;
  } catch (e) {
    console.error('loadHistory error:', e);
    el.innerHTML = `<div class="empty-state"><i class="bi bi-wifi-off" style="color:#d32f2f;"></i><p>${esc(t('trips.history_network', 'Network error loading history') + ': ' + (e.message || 'Server unreachable'))}</p><button class="btn-primary-app" style="max-width:140px;margin:10px auto;padding:6px 12px;font-size:12px;" onclick="loadHistory()">${esc(t('common.retry', 'Retry'))}</button></div>`;
  }
}

function changePage(dir) {
  historyPage += dir;
  if (historyPage < 1) historyPage = 1;
  loadHistory();
}

loadTodayTrips({ force: true });
setInterval(updateTripsUpdatedLabel, 1000);
setInterval(function () {
  if (!document.hidden && !isAnyModalOpen()) loadTodayTrips();
}, 30000);
document.addEventListener('visibilitychange', function () {
  if (!document.hidden && tripsState.started) loadTodayTrips();
});
