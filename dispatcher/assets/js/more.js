/* More screen: bus problem reports, replacement assignments, reports,
   notifications, and settings. Shared helpers live in core.js. */

let problemStatusFilter = '';
let problemReportsCache = [];
let resolveProblemId = null;
let replacementReportId = null;
let replacementSelectedBus = null;
let replacementCandidates = [];

function setProblemStatusFilter(chip, status) {
  problemStatusFilter = status;
  document.querySelectorAll('#problemFilterRow .filter-chip').forEach(c => c.classList.remove('active'));
  chip.classList.add('active');
  loadProblemReports();
}

function fmtReportTime(v) {
  if (!v) return '-';
  return String(v).replace('T', ' ').substring(0, 16);
}

function problemReportCard(r) {
  const status = esc(r.status || '');
  const badge = '<span class="status-badge status-' + status + '">' + status.toUpperCase() + '</span>';
  const tripLabel = r.trip_no ? ('Trip #' + esc(r.trip_no)) : (r.trip_id ? ('Trip #' + esc(r.trip_id)) : esc(t('problems.no_trip', 'No trip')));
  const route = r.route_name ? ' &bull; ' + esc(r.route_name) : '';
  const driverName = r.driver_name ? esc(r.driver_name) + ' <span style="color:var(--text-muted);">(' + esc(r.driver_id || '') + ')</span>' : esc(r.driver_id || '-');
  const busLabel = r.plate_number ? esc(r.plate_number) + ' <span style="color:var(--text-muted);">(' + esc(r.bus_id || '') + ')</span>' : esc(r.bus_id || '-');

  let html = '<div class="dispatch-card">';
  html += '<div style="display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap;">'
        + '<strong><i class="bi bi-exclamation-triangle"></i> #' + esc(r.id) + ' &bull; ' + tripLabel + route + '</strong>' + badge + '</div>';
  html += '<div class="bpr-meta">'
        + '<span><i class="bi bi-person"></i> ' + driverName + '</span>'
        + '<span><i class="bi bi-bus-front"></i> ' + busLabel + '</span>'
        + '<span><i class="bi bi-people"></i> ' + esc(r.passenger_count ?? 0) + ' ' + esc(t('problems.pax', 'pax')) + '</span>'
        + '</div>';
  html += '<div style="margin-top:6px;font-size:12px;"><strong>' + esc(r.problem_type || '-') + '</strong></div>';
  if (r.problem_detail && String(r.problem_detail).trim() !== '') {
    html += '<div class="bpr-detail"><i class="bi bi-chat-left-text"></i> ' + esc(r.problem_detail) + '</div>';
  }
  if (r.remarks && String(r.remarks).trim() !== '') {
    html += '<div class="bpr-detail"><i class="bi bi-card-text"></i> ' + esc(r.remarks) + '</div>';
  }
  const hasLat = r.latitude !== null && r.latitude !== undefined && r.latitude !== '';
  const hasLng = r.longitude !== null && r.longitude !== undefined && r.longitude !== '';
  if (hasLat || hasLng || r.location_status) {
    const coords = (hasLat && hasLng) ? (esc(r.latitude) + ', ' + esc(r.longitude)) : esc(t('problems.location_unavailable', 'Location unavailable'));
    const locStatus = r.location_status ? ' &bull; ' + esc(r.location_status) : '';
    html += '<div class="bpr-meta"><span><i class="bi bi-geo"></i> ' + coords + locStatus + '</span></div>';
  }
  html += '<div class="bpr-meta"><span><i class="bi bi-clock"></i> ' + fmtReportTime(r.created_at)
        + (r.updated_at && r.updated_at !== r.created_at ? ' &bull; ' + esc(t('problems.updated', 'Updated')) + ' ' + fmtReportTime(r.updated_at) : '')
        + '</span></div>';
  const hasReplacement = r.replacement_bus_id && String(r.replacement_bus_id).trim() !== '';
  if (hasReplacement) {
    html += '<div class="bpr-detail" style="border-color:#5b8f4e;background:#f2f9f4;">'
          + '<i class="bi bi-arrow-left-right"></i> <strong>' + esc(t('problems.replacement_label', 'Replacement Bus')) + ': ' + esc(r.replacement_bus_id) + '</strong>'
          + (r.replacement_driver_id ? ' &bull; ' + esc(t('problems.driver_label', 'Driver')) + ': ' + esc(r.replacement_driver_id) : '')
          + (r.replacement_assigned_at ? '<div style="font-size:11px;color:var(--text-muted);margin-top:2px;">' + esc(t('problems.assigned_at', 'Assigned')) + ' ' + fmtReportTime(r.replacement_assigned_at) + '</div>' : '')
          + '</div>';
  }
  html += '<div class="bpr-actions">';
  if (r.status === 'waiting') {
    html += '<button type="button" class="btn-action bpr-btn-primary" onclick="acknowledgeProblemReport(' + parseInt(r.id, 10) + ')"><i class="bi bi-check2-circle"></i> ' + esc(t('problems.acknowledge', 'Acknowledge')) + '</button>';
  }
  if (r.status === 'acknowledged') {
    html += '<button type="button" class="btn-action bpr-btn-primary" onclick="openReplacementBusModal(' + parseInt(r.id, 10) + ')"><i class="bi bi-arrow-left-right"></i> '
          + esc(hasReplacement ? t('problems.change_replacement', 'Change Replacement Bus') : t('problems.assign_replacement', 'Assign Replacement Bus')) + '</button>';
  }
  if (r.status === 'waiting' || r.status === 'acknowledged') {
    html += '<button type="button" class="btn-action bpr-btn-secondary" onclick="openResolveProblemModal(' + parseInt(r.id, 10) + ')"><i class="bi bi-check-circle"></i> ' + esc(t('problems.resolve_action', 'Resolve')) + '</button>';
  }
  html += '</div></div>';
  return html;
}

async function loadProblemReports() {
  const el = document.getElementById('problemReportsList');
  if (!el) return;
  el.innerHTML = '<div class="loading">' + esc(t('problems.loading', 'Loading problem reports...')) + '</div>';
  let params = '';
  if (problemStatusFilter) params += 'status=' + encodeURIComponent(problemStatusFilter);
  const q = (document.getElementById('problemSearchInput').value || '').trim();
  if (q) params += (params ? '&' : '') + 'search=' + encodeURIComponent(q);
  try {
    const r = await fetch(API + 'get_bus_problem_reports' + (params ? '?' + params : ''), { headers: apiHeaders() });
    const j = await parseApiResponse(r);
    if (r.status === 401) {
      el.innerHTML = '<div class="empty-state"><i class="bi bi-shield-lock" style="color:#f57c00;"></i><p>' + esc(t('state.session_expired', 'Your session has expired. Please log in again.')) + '</p></div>';
      return;
    }
    if (!j || !j.success) {
      const msg = extractErrorMessage(r, j, t('problems.load_failed', 'Failed to load problem reports. Please try again.'));
      el.innerHTML = '<div class="empty-state"><i class="bi bi-exclamation-triangle" style="color:#d32f2f;"></i><p>' + esc(msg) + '</p><button class="btn-primary-app" style="max-width:140px;margin:10px auto;padding:6px 12px;font-size:12px;" onclick="loadProblemReports()">' + esc(t('common.retry', 'Retry')) + '</button></div>';
      return;
    }
    const reports = j.reports || [];
    problemReportsCache = reports;
    updateProblemBadge(reports);
    if (!reports.length) {
      el.innerHTML = '<div class="empty-state"><i class="bi bi-check-circle"></i><p>' + esc(t('problems.empty', 'No problem reports found')) + '</p></div>';
      return;
    }
    el.innerHTML = reports.map(problemReportCard).join('');
  } catch (e) {
    console.error('loadProblemReports error:', e);
    el.innerHTML = '<div class="empty-state"><i class="bi bi-wifi-off" style="color:#d32f2f;"></i><p>' + esc(t('problems.network_error', 'Network error loading problem reports') + ': ' + (e.message || 'Server unreachable')) + '</p><button class="btn-primary-app" style="max-width:140px;margin:10px auto;padding:6px 12px;font-size:12px;" onclick="loadProblemReports()">' + esc(t('common.retry', 'Retry')) + '</button></div>';
  }
}

function acknowledgeProblemReport(id) {
  showConfirm(t('problems.ack_title', 'Acknowledge Report'), t('problems.ack_message', 'Mark this problem report as acknowledged?'), async function () {
    try {
      const r = await fetch(API + 'acknowledge_bus_problem_report', {
        method: 'POST',
        headers: apiPostHeaders(),
        body: JSON.stringify({ report_id: id, csrf_token: CSRF_TOKEN })
      });
      const j = await parseApiResponse(r);
      if (!isResponseSuccess(r, j)) {
        showAlert('error', t('problems.ack_failed', 'Acknowledge Failed'), extractErrorMessage(r, j, 'Failed to acknowledge the report.'));
        return;
      }
      showAlert('success', t('problems.ack_done', 'Report Acknowledged'), t('problems.ack_done_msg', 'The report status is now acknowledged.'));
      loadProblemReports();
    } catch (e) {
      showAlert('error', t('problems.ack_failed', 'Acknowledge Failed'), t('problems.network', 'Network error') + ': ' + (e.message || 'Server unreachable'));
    }
  });
}

function openResolveProblemModal(id) {
  resolveProblemId = id;
  const rep = problemReportsCache.find(x => parseInt(x.id, 10) === parseInt(id, 10));
  document.getElementById('resolveReplacementRequired').checked = false;
  let summary = '<strong>#' + esc(id) + '</strong>';
  if (rep) {
    summary = '<strong>#' + esc(rep.id) + ' &bull; ' + (rep.trip_no ? 'Trip #' + esc(rep.trip_no) : (rep.trip_id ? 'Trip #' + esc(rep.trip_id) : t('problems.no_trip', 'No trip'))) + '</strong>';
    summary += '<div style="margin-top:4px;">' + esc(rep.problem_type || '-')
             + (rep.problem_detail && String(rep.problem_detail).trim() !== '' ? ': ' + esc(rep.problem_detail) : '')
             + '</div>';
  }
  document.getElementById('resolveProblemSummary').innerHTML = summary;
  document.getElementById('resolveProblemConfirmBtn').disabled = false;
  showModal('resolveProblemModal');
}

async function resolveProblemReport() {
  const btn = document.getElementById('resolveProblemConfirmBtn');
  const replacement = document.getElementById('resolveReplacementRequired').checked ? 1 : 0;
  btn.disabled = true;
  try {
    const r = await fetch(API + 'resolve_bus_problem_report', {
      method: 'POST',
      headers: apiPostHeaders(),
      body: JSON.stringify({ report_id: resolveProblemId, replacement_required: replacement, csrf_token: CSRF_TOKEN })
    });
    const j = await parseApiResponse(r);
    if (!isResponseSuccess(r, j)) {
      btn.disabled = false;
      showAlert('error', t('problems.resolve_failed', 'Resolve Failed'), extractErrorMessage(r, j, 'Failed to resolve the report.'));
      return;
    }
    closeModal('resolveProblemModal');
    showAlert('success', t('problems.resolve_done', 'Report Resolved'),
      replacement ? t('problems.resolve_done_replacement', 'The report is resolved and the trip was flagged for bus replacement.')
                  : t('problems.resolve_done_msg', 'The report status is now resolved.'));
    loadProblemReports();
  } catch (e) {
    btn.disabled = false;
    showAlert('error', t('problems.resolve_failed', 'Resolve Failed'), t('problems.network', 'Network error') + ': ' + (e.message || 'Server unreachable'));
  }
}

function fmtTimeOnly(v) {
  if (!v) return '-';
  const s = String(v);
  const m = s.match(/(\d{1,2}):(\d{2})(?::(\d{2}))?/);
  return m ? String(m[1]).padStart(2, '0') + ':' + m[2] : s.replace('T', ' ').substring(0, 16);
}

function openReplacementBusModal(reportId) {
  replacementReportId = reportId;
  replacementSelectedBus = null;
  const rep = problemReportsCache.find(x => parseInt(x.id, 10) === parseInt(reportId, 10));
  let summary = '<strong>#' + esc(reportId) + '</strong>';
  if (rep) {
    summary = '<strong>#' + esc(rep.id) + ' &bull; ' + (rep.trip_no ? 'Trip #' + esc(rep.trip_no) : (rep.trip_id ? 'Trip #' + esc(rep.trip_id) : t('problems.no_trip', 'No trip'))) + ' &bull; ' + esc(t('problems.affected_bus', 'Affected bus')) + ': ' + esc(rep.bus_id || '-') + (rep.plate_number ? ' (' + esc(rep.plate_number) + ')' : '') + '</strong>';
    summary += '<div style="margin-top:4px;">' + esc(rep.problem_type || '-')
             + (rep.problem_detail && String(rep.problem_detail).trim() !== '' ? ': ' + esc(rep.problem_detail) : '')
             + '</div>';
    const meta = [];
    if (rep.driver_name || rep.driver_id) {
      meta.push('<span><i class="bi bi-person"></i> ' + esc(t('problems.driver_label', 'Driver')) + ': ' + (rep.driver_name ? esc(rep.driver_name) : esc(rep.driver_id)) + (rep.driver_name && rep.driver_id && String(rep.driver_id) !== String(rep.driver_name) ? ' (' + esc(rep.driver_id) + ')' : '') + '</span>');
    }
    if (rep.route_name) meta.push('<span><i class="bi bi-signpost"></i> ' + esc(t('problems.route', 'Route')) + ': ' + esc(rep.route_name) + '</span>');
    if (rep.departure_time) meta.push('<span><i class="bi bi-clock"></i> ' + esc(t('problems.departure', 'Departure')) + ': <b>' + fmtTimeOnly(rep.departure_time) + '</b></span>');
    meta.push('<span><i class="bi bi-people"></i> ' + esc(rep.passenger_count ?? 0) + ' ' + esc(t('problems.pax', 'pax')) + '</span>');
    summary += '<div class="bpr-meta" style="margin-top:5px;">' + meta.join('') + '</div>';
    if (rep.replacement_bus_id && String(rep.replacement_bus_id).trim() !== '') {
      summary += '<div class="bpr-detail" style="border-color:#5b8f4e;background:#f2f9f4;margin-top:5px;"><i class="bi bi-arrow-left-right"></i>'
              + ' <strong>' + esc(t('problems.replacement_label', 'Replacement Bus')) + ': ' + esc(rep.replacement_bus_id) + '</strong>'
              + (rep.replacement_driver_id ? ' &bull; ' + esc(t('problems.driver_label', 'Driver')) + ': ' + esc(rep.replacement_driver_id) : '')
              + '</div>';
    }
  }
  document.getElementById('replacementReportSummary').innerHTML = summary;
  document.getElementById('replacementAssignBtn').disabled = true;
  document.getElementById('replacementSelectedLabel').textContent = t('problems.select_bus_hint', 'Select a bus to continue');
  document.getElementById('replacementBusList').innerHTML = '<div class="loading">' + esc(t('problems.loading_buses', 'Loading available buses...')) + '</div>';
  showModal('replacementBusModal');
  loadReplacementBuses();
}

async function loadReplacementBuses() {
  const el = document.getElementById('replacementBusList');
  if (!el) return;
  try {
    const r = await fetch(API + 'get_replacement_bus_candidates?report_id=' + encodeURIComponent(replacementReportId), { headers: apiHeaders() });
    const j = await parseApiResponse(r);
    if (r.status === 401) {
      el.innerHTML = '<div class="empty-state"><i class="bi bi-shield-lock" style="color:#f57c00;"></i><p>' + esc(t('state.session_expired', 'Your session has expired. Please log in again.')) + '</p></div>';
      return;
    }
    if (!j || !j.success) {
      const msg = extractErrorMessage(r, j, t('problems.load_buses_failed', 'Failed to load available buses.'));
      el.innerHTML = '<div class="empty-state"><i class="bi bi-exclamation-triangle" style="color:#d32f2f;"></i><p>' + esc(msg) + '</p><button class="btn-primary-app" style="max-width:140px;margin:10px auto;padding:6px 12px;font-size:12px;" onclick="loadReplacementBuses()">' + esc(t('common.retry', 'Retry')) + '</button></div>';
      return;
    }
    const buses = j.data || [];
    replacementCandidates = buses;
    replacementSelectedBus = null;
    document.getElementById('replacementAssignBtn').disabled = true;
    document.getElementById('replacementSelectedLabel').textContent = t('problems.select_bus_hint', 'Select a bus to continue');
    if (!buses.length) {
      el.innerHTML = '<div class="empty-state"><i class="bi bi-bus-front"></i><p>' + esc(t('problems.no_candidates', 'No available replacement buses right now')) + '</p></div>';
      return;
    }
    el.innerHTML = buses.map(replacementBusRow).join('');
  } catch (e) {
    console.error('loadReplacementBuses error:', e);
    el.innerHTML = '<div class="empty-state"><i class="bi bi-wifi-off" style="color:#d32f2f;"></i><p>' + esc(t('problems.network_error', 'Network error loading problem reports') + ': ' + (e.message || 'Server unreachable')) + '</p><button class="btn-primary-app" style="max-width:140px;margin:10px auto;padding:6px 12px;font-size:12px;" onclick="loadReplacementBuses()">' + esc(t('common.retry', 'Retry')) + '</button></div>';
  }
}

function replacementBusRow(b) {
  const loc = (b.location_status === 'located' && b.latitude !== null && b.latitude !== undefined && b.longitude !== null && b.longitude !== undefined)
    ? (esc(b.latitude) + ', ' + esc(b.longitude))
    : esc(t('problems.location_unavailable', 'Location unavailable'));
  const capacity = (b.seats !== null && b.seats !== undefined)
    ? (esc(b.seats) + ' ' + esc(t('problems.seats', 'seats')))
    : esc(t('problems.capacity_unknown', 'Capacity unknown'));
  const driver = b.driver_name ? esc(b.driver_name) : (b.driver_id ? esc(b.driver_id) : esc(t('problems.no_driver', 'No driver assigned')));
  let tripInfo = '';
  if (b.next_trip && b.next_trip.trip_no) {
    tripInfo = '<span><i class="bi bi-clipboard-check"></i> ' + esc(t('problems.next_trip', 'Next trip')) + ': #' + esc(b.next_trip.trip_no)
             + (b.next_trip.departure_time ? ' &bull; ' + fmtTimeOnly(b.next_trip.departure_time) : '')
             + (b.next_trip.status ? ' &bull; ' + esc(b.next_trip.status) : '') + '</span>';
  } else {
    tripInfo = '<span><i class="bi bi-check2-circle"></i> ' + esc(t('problems.no_trips_today', 'No trips scheduled today')) + '</span>';
  }
  return '<div class="dispatch-card bpr-candidate" id="cand_' + esc(b.bus_id) + '" role="button" tabindex="0" onclick="selectReplacementBus(\'' + esc(b.bus_id) + '\')">'
    + '<div style="display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap;">'
    + '<strong><i class="bi bi-bus-front"></i> ' + esc(b.bus_id) + ' &bull; ' + esc(b.plate_number) + '</strong>'
    + '<span class="status-badge status-available">' + esc(b.status) + '</span></div>'
    + '<div class="bpr-meta">'
    + '<span><i class="bi bi-person"></i> ' + driver + '</span>'
    + (b.route_name ? '<span><i class="bi bi-signpost"></i> ' + esc(b.route_name) + '</span>' : '')
    + '<span><i class="bi bi-people"></i> ' + capacity + '</span>'
    + '</div>'
    + '<div class="bpr-meta">'
    + tripInfo
    + '<span><i class="bi bi-geo"></i> ' + loc + '</span>'
    + '</div></div>';
}

function selectReplacementBus(busId) {
  replacementSelectedBus = replacementCandidates.find(b => b.bus_id === busId) || null;
  document.querySelectorAll('#replacementBusList .bpr-candidate').forEach(el => { el.style.borderColor = ''; el.style.boxShadow = ''; });
  const el = document.getElementById('cand_' + busId);
  if (el) { el.style.borderColor = 'var(--primary)'; el.style.boxShadow = '0 2px 10px rgba(71,97,59,.3)'; }
  document.getElementById('replacementAssignBtn').disabled = !replacementSelectedBus;
  const label = document.getElementById('replacementSelectedLabel');
  if (label) {
    label.textContent = replacementSelectedBus
      ? t('problems.selected_bus', 'Selected') + ': ' + replacementSelectedBus.bus_id + (replacementSelectedBus.plate_number ? ' • ' + replacementSelectedBus.plate_number : '')
      : t('problems.select_bus_hint', 'Select a bus to continue');
  }
  const driverRow = document.getElementById('replacementDriverRow');
  const driverSel = document.getElementById('replacementDriverSelect');
  if (!replacementSelectedBus) {
    if (driverRow) driverRow.style.display = 'none';
    return;
  }
  const drivers = (replacementSelectedBus.drivers && replacementSelectedBus.drivers.length) ? replacementSelectedBus.drivers : [];
  if (driverSel) {
    if (drivers.length > 1) {
      let opts = '<option value="">' + esc(t('problems.driver_auto', '— Use assigned driver (:driver) —', { driver: replacementSelectedBus.driver_id })) + '</option>';
      drivers.forEach(d => {
        const dname = d.driver_name ? (d.driver_name + ' (' + d.driver_id + ')') : d.driver_id;
        opts += '<option value="' + esc(d.driver_id) + '"' + (String(d.driver_id) === String(replacementSelectedBus.driver_id) ? ' selected' : '') + '>' + esc(dname) + '</option>';
      });
      driverSel.innerHTML = opts;
      if (driverRow) driverRow.style.display = 'block';
    } else if (drivers.length === 1) {
      driverSel.innerHTML = '<option value="' + esc(drivers[0].driver_id) + '" selected>' + esc((drivers[0].driver_name ? (drivers[0].driver_name + ' (' + drivers[0].driver_id + ')') : drivers[0].driver_id)) + '</option>';
      if (driverRow) driverRow.style.display = 'none';
    } else {
      driverSel.innerHTML = '';
      if (driverRow) driverRow.style.display = 'none';
    }
  }
}

async function assignReplacementBus() {
  if (!replacementSelectedBus) return;
  const btn = document.getElementById('replacementAssignBtn');
  const selected = replacementSelectedBus;
  const driverSel = document.getElementById('replacementDriverSelect');
  const driverId = driverSel ? driverSel.value.trim() : '';
  btn.disabled = true;
  try {
    const r = await fetch(API + 'assign_replacement_bus', {
      method: 'POST',
      headers: apiPostHeaders(),
      body: JSON.stringify({
        report_id: replacementReportId,
        bus_id: selected.bus_id,
        driver_id: driverId,
        csrf_token: CSRF_TOKEN
      })
    });
    const j = await parseApiResponse(r);
    if (!isResponseSuccess(r, j)) {
      btn.disabled = false;
      showAlert('error', t('problems.assign_failed', 'Assignment Failed'), extractErrorMessage(r, j, 'Failed to assign the replacement bus.'));
      return;
    }
    closeModal('replacementBusModal');
    if (driverId) {
      showAlert('success', t('problems.assign_done', 'Replacement Bus Assigned'), t('problems.assign_done_driver_msg', ':bus is now the replacement bus, driven by :driver.', { bus: selected.bus_id, driver: driverId }));
    } else {
      showAlert('success', t('problems.assign_done', 'Replacement Bus Assigned'), t('problems.assign_done_msg', ':bus is now the replacement bus.', { bus: selected.bus_id }));
    }
    loadProblemReports();
  } catch (e) {
    btn.disabled = false;
    showAlert('error', t('problems.assign_failed', 'Assignment Failed'), t('problems.network', 'Network error') + ': ' + (e.message || 'Server unreachable'));
  }
}

/* ── Notifications feed (display only; stored read state) ── */

function notificationHTML(n) {
  const unread = !Number(n.is_read);
  const type = String(n.type || '');
  const icon = (type === 'success') ? 'bi-check-circle' : (type === 'warning' ? 'bi-exclamation-triangle' : 'bi-info-circle');
  return '<div class="notif-item' + (unread ? ' unread' : '') + '">'
    + '<span class="ni-icon"><i class="bi ' + icon + '"></i></span>'
    + '<span class="ni-main"><strong>' + esc(n.title || '') + '</strong>'
    + (n.description ? '<p>' + esc(n.description) + '</p>' : '')
    + '<span class="ni-meta">'
    + (type ? '<span>' + esc(type) + '</span>' : '')
    + '<span>' + esc(fmtReportTime(n.created_at)) + '</span>'
    + (unread ? '<span>' + esc(t('more.unread', 'Unread')) + '</span>' : '')
    + '</span></span>'
    + (unread ? '<span class="notif-dot"></span>' : '')
    + '</div>';
}

async function loadNotifications() {
  const el = document.getElementById('notificationsList');
  if (!el) return;
  const countEl = document.getElementById('notificationsCount');
  el.innerHTML = '<div class="loading">' + esc(t('common.loading', 'Loading...')) + '</div>';
  try {
    const r = await fetch(API + 'get_notifications', { headers: apiHeaders() });
    const j = await parseApiResponse(r);
    if (!j || !j.success) {
      el.innerHTML = '<div class="empty-state"><i class="bi bi-exclamation-triangle" style="color:#d32f2f;"></i><p>' + esc(t('more.notifications_error', 'Notifications could not be loaded.')) + '</p><button class="btn-primary-app" style="max-width:140px;margin:10px auto;padding:6px 12px;font-size:12px;" onclick="loadNotifications()">' + esc(t('common.retry', 'Retry')) + '</button></div>';
      if (countEl) countEl.hidden = true;
      return;
    }
    const list = Array.isArray(j.data) ? j.data : [];
    const unread = list.filter(function (n) { return !Number(n.is_read); }).length;
    if (countEl) { countEl.hidden = unread === 0; countEl.textContent = String(unread); }
    if (!list.length) {
      el.innerHTML = '<div class="empty-state"><i class="bi bi-bell-slash"></i><p>' + esc(t('more.notifications_empty', 'No notifications right now.')) + '</p></div>';
      return;
    }
    el.innerHTML = list.map(notificationHTML).join('');
  } catch (e) {
    console.error('loadNotifications error:', e);
    el.innerHTML = '<div class="empty-state"><i class="bi bi-wifi-off" style="color:#d32f2f;"></i><p>' + esc(t('state.network_error', 'Network error. Please check your connection and try again.')) + '</p><button class="btn-primary-app" style="max-width:140px;margin:10px auto;padding:6px 12px;font-size:12px;" onclick="loadNotifications()">' + esc(t('common.retry', 'Retry')) + '</button></div>';
    if (countEl) countEl.hidden = true;
  }
}

function triggerInstall() {
  const installBtn = document.getElementById('installBtn');
  if (installBtn) installBtn.click();
}

loadProblemReports();
loadNotifications();
