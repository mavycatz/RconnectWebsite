/* Queue screen runtime. Moved from the previous single-page console with minimal
   edits: shared helpers live in core.js; trip detail/arrival/fuel live on Trips. */

const queueState = {
  entries: [],
  history: [],
  summary: {},
  availableDrivers: [],
  adminQueue: null,
  filter: 'all',
  route: '',
  search: '',
  lastSuccess: 0,
  lastPayload: '',
  loading: false,
  started: false
};
let pollTimer = null;
let dispatchInFlight = false;
let skipTargetBus = null;
let pendingDispatch = null;
let overtakeContinue = null;
let queueFocusApplied = false;

let cacheBuses = [], cacheDrivers = [], cacheRoutes = [];
let currentFuelPrice = 65.55;
let isPreparingTrip = false;
let assignDriverTarget = null;
let assignDriverIsChange = false;
let prepareTargetBus = null;

function setQueueFilter(el, status){
  queueState.filter = status || 'all';
  document.querySelectorAll('#queueFilterRow .filter-chip').forEach(c=>c.classList.remove('active'));
  if(el) el.classList.add('active');
  renderQueue();
}
function onRouteFilter(){
  queueState.route = document.getElementById('routeFilter').value || '';
  renderQueue();
}
function findQueueEntry(busId){
  return queueState.entries.find(e => String(e.bus_id) === String(busId)) || null;
}
function adminQueueLocked(){
  return !!(queueState.adminQueue && queueState.adminQueue.locked);
}

function statusWord(entry){
  const map = {
    waiting_for_driver: t('summary.waiting_for_driver','Waiting for Driver'),
    driver_assigned: t('summary.driver_assigned','Driver Assigned'),
    checked_in: t('summary.checked_in','Checked In'),
    ready: t('summary.ready','Ready to Dispatch'),
    departed: t('summary.on_route','On Route')
  };
  if(!entry) return String(map.waiting_for_driver).toLowerCase();
  return String(map[entry.status] || map.waiting_for_driver).toLowerCase();
}
function nextActionMeta(entry){
  if(!entry) return null;
  const action = entry.next_action;
  if(action === 'assign_driver') return {action:action, icon:'bi-person-plus', label:t('action.assign_driver','Assign Driver')};
  if(action === 'check_in') return {action:action, icon:'bi-person-check', label:t('action.check_in','Check In')};
  if(action === 'prepare') return {action:action, icon:'bi-clipboard-check', label:t('action.prepare_trip','Prepare Trip')};
  if(action === 'dispatch') return {action:action, icon:'bi-send', label:t('action.dispatch','Dispatch')};
  return null;
}
function runNextAction(busId){
  const e = findQueueEntry(busId);
  if(!e) return;
  if(e.next_action === 'assign_driver' && adminQueueLocked()){ openQueueDetail(busId); return; }
  switch(e.next_action){
    case 'assign_driver': openAssignDriverModal(busId); break;
    case 'check_in': checkInDriver(busId); break;
    case 'prepare': openPrepareTrip(busId); break;
    case 'dispatch': openPrepareTrip(busId); break;
    default: openQueueDetail(busId);
  }
}
function isDispatchable(entry){
  return !!entry && entry.status === 'ready';
}
function canAdvance(entry){
  return !!entry && entry.status !== 'departed' && !(entry.skip && entry.skip.active);
}
function byQueuePosition(a, b){
  return (Number(a.queue_position) || 0) - (Number(b.queue_position) || 0);
}
function sortQueueEntries(list){
  /* The Admin's published master order governs this page: always list the
     queue positions in order instead of regrouping by readiness status. */
  return list.slice().sort((a, b) => {
    const pos = byQueuePosition(a, b);
    if(pos !== 0) return pos;
    return String(a.bus_id).localeCompare(String(b.bus_id));
  });
}
function nextEligibleEntry(){
  const list = queueState.entries.filter(canAdvance).sort(byQueuePosition);
  /* Strict queue order: the next action always belongs to the head of the
     dispatch queue - Queue #1 first, then #2, and so on. Departing buses are
     done and explicitly skipped buses are passed over by the dispatcher. */
  return list[0] || null;
}
function isNextInQueue(entry){
  if(!entry) return false;
  const eligible = nextEligibleEntry();
  return !!eligible && String(eligible.bus_id) === String(entry.bus_id);
}
function queueOrderMessage(){
  const eligible = nextEligibleEntry();
  if(!eligible) return t('alert.queue_order_none','No bus can be actioned right now.');
  return t('alert.queue_order_body','Only the next bus in the queue can be actioned. :bus is next.', {bus: eligible.bus_id + ' (QUEUE #' + eligible.queue_position + ')'});
}
function earliestReadyEntry(){
  const list = queueState.entries.filter(isDispatchable).sort(byQueuePosition);
  return list[0] || null;
}
function earliestActiveEntry(){
  const active = queueState.entries.filter(e => e.status !== 'departed').sort((a, b) => (Number(a.queue_position) || 0) - (Number(b.queue_position) || 0));
  return active[0] || null;
}
function filteredQueueEntries(){
  let list = queueState.entries.slice();
  if(queueState.filter && queueState.filter !== 'all') list = list.filter(e => e.status === queueState.filter);
  if(queueState.route) list = list.filter(e => String(e.route_name || '') === queueState.route);
  if(queueState.search){
    const q = queueState.search.toLowerCase();
    list = list.filter(e => {
      const tripNo = e.trip && e.trip.trip_number != null ? String(e.trip.trip_number) : '';
      return String(e.bus_id || '').toLowerCase().indexOf(q) !== -1
        || String(e.driver_name || '').toLowerCase().indexOf(q) !== -1
        || String(e.plate_number || '').toLowerCase().indexOf(q) !== -1
        || tripNo.toLowerCase().indexOf(q) !== -1;
    });
  }
  return sortQueueEntries(list);
}
function queueCardHTML(e){
  const skipLine = e.skip && e.skip.active
    ? '<div class="qc-skip"><i class="bi bi-slash-circle"></i> ' + esc(t('card.skipped','Temporarily skipped: :reason', {reason: e.skip.reason_label || t('card.skipped_reason','reason recorded')})) + '</div>'
    : '';
  const actions = ['<button type="button" class="btn-action btn-view" onclick="openQueueDetail(\'' + esc(e.bus_id) + '\')" aria-label="' + esc(t('common.view_details','View Details')) + ' ' + esc(e.bus_id) + '"><span class="btn-ico"><i class="bi bi-eye"></i></span> ' + esc(t('common.view_details','View Details')) + '</button>'];
  if(e.status === 'departed' && e.trip && e.trip.id){
    actions.push('<button type="button" class="btn-action btn-view" onclick="goToTrip(' + Number(e.trip.id) + ')" aria-label="' + esc(t('action.view_trip','View Trip')) + '"><i class="bi bi-info-circle"></i> ' + esc(t('action.view_trip','View Trip')) + '</button>');
  } else if(e.skip && e.skip.active){
    actions.push('<button type="button" class="btn-action btn-view" onclick="clearSkip(\'' + esc(e.bus_id) + '\')" aria-label="' + esc(t('action.clear_skip','Clear Skip')) + '"><i class="bi bi-arrow-counterclockwise"></i> ' + esc(t('action.clear_skip','Clear Skip')) + '</button>');
  } else {
    const meta = nextActionMeta(e);
    const lockedAssign = !!meta && meta.action === 'assign_driver' && adminQueueLocked();
    /* Queue order governs the page: only the next bus in the dispatch queue
       shows its action button. Later buses stay read-only until their turn. */
    if(meta && !lockedAssign && isNextInQueue(e)) actions.push('<button type="button" class="btn-action btn-dispatch-queue" onclick="runNextAction(\'' + esc(e.bus_id) + '\')" aria-label="' + esc(meta.label) + ' ' + esc(e.bus_id) + '"><i class="bi ' + meta.icon + '"></i> ' + esc(meta.label) + '</button>');
  }
  const eligibleEntry = nextEligibleEntry();
  const isNext = canAdvance(e) && !!eligibleEntry && String(eligibleEntry.bus_id) === String(e.bus_id);
  const nextBadge = isNext ? '<span class="qc-next">' + esc(eligibleEntry.status === 'ready' ? t('card.next_dispatch','Next to dispatch') : t('card.next_eligible','Next step')) + '</span>' : '';
  const cycleChip = (Number(e.cycle) > 1) ? '<span class="qc-cycle">' + esc(t('card.trip_n','Trip :cycle', {cycle: Number(e.cycle)})) + '</span>' : '';
  return '<article class="queue-card q-' + e.status + '" data-queue-bus="' + esc(e.bus_id) + '">'
    + '<div class="qc-top"><span style="display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap;"><span class="qc-pos">' + esc(t('card.queue','QUEUE #:position', {position: e.queue_position})) + '</span>' + cycleChip + '</span><span style="display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap;">' + nextBadge + statusPill(e.status) + '</span></div>'
    + '<div class="qc-bus"><i class="bi bi-bus-front"></i> ' + esc(e.bus_id) + '</div>'
    + '<div class="qc-driver"><i class="bi bi-person"></i> ' + esc(e.driver_name || t('card.no_driver','No driver assigned')) + '</div>'
    + '<div class="qc-route"><i class="bi bi-geo-alt"></i> ' + esc(routeArrow(e.route_name || t('card.no_route','No route assigned'))) + '</div>'
    + (e.current_location ? '<div class="qc-location"><i class="bi bi-pin-map"></i> ' + esc(e.current_location) + '</div>' : '')
    + '<div class="qc-reason">' + esc(e.short_reason || '') + '</div>'
    + skipLine
    + '<div class="qc-actions">' + actions.join('') + '</div>'
    + '</article>';
}
function renderAdminQueueBanner(){
  const banner = document.getElementById('adminQueueBanner');
  if(!banner) return;
  const admin = queueState.adminQueue;
  if(!admin || (!admin.locked && !admin.draft_pending)){
    banner.style.display = 'none';
    banner.innerHTML = '';
    return;
  }
  if(!admin.locked && admin.draft_pending){
    banner.className = 'admin-queue-banner is-draft';
    banner.innerHTML = '<i class="bi bi-hourglass-split"></i><div class="aq-body">'
      + '<strong>' + esc(t('admin_queue.draft_title','ADMIN QUEUE NOT YET PUBLISHED')) + '</strong>'
      + '<span>' + esc(t('admin_queue.draft','The Admin has arranged today\'s queue but it is not published yet. The Dispatcher keeps the previous order until it is published.')) + '</span>'
      + '</div>';
    banner.style.display = 'flex';
    return;
  }
  banner.className = 'admin-queue-banner';
  const nextText = admin.next_bus_id
    ? t('admin_queue.next_bus','Dispatch next: :bus (position #:position)', {bus: admin.next_bus_id, position: admin.next_position || '—'})
    : t('admin_queue.no_next','No bus is ready yet - complete the readiness checks.');
  banner.innerHTML = '<i class="bi bi-megaphone-fill"></i><div class="aq-body">'
    + '<strong>' + esc(t('admin_queue.title','OFFICIAL ADMIN DISPATCH ORDER')) + '</strong>'
    + '<span>' + esc(t('admin_queue.published','Published :at by :by.', {at: admin.published_at || '—', by: admin.published_by || '—'})) + '</span> '
    + '<span>' + esc(nextText) + '</span>'
    + '<span class="aq-lock">' + esc(t('admin_queue.locked','Queue order and driver assignment are managed by the Admin. Verify readiness, then dispatch the next bus.')) + '</span>'
    + '</div>';
  banner.style.display = 'flex';
}
function renderNextDispatch(){
  const panel = document.getElementById('nextDispatchPanel');
  if(!panel) return;
  if(!queueState.entries.length){
    panel.style.display = 'none';
    panel.innerHTML = '';
    return;
  }
  panel.style.display = '';
  const eligible = nextEligibleEntry();
  if(!eligible){
    panel.innerHTML = '<div class="nd-kicker"><i class="bi bi-send"></i> ' + esc(t('next.kicker','NEXT ACTION')) + '</div>'
      + '<div class="empty-state" style="padding:12px 4px;"><i class="bi bi-hourglass-split"></i><p><strong>' + esc(t('next.no_action_title','NO ACTION AVAILABLE')) + '</strong></p><p>' + esc(t('next.no_action_body','The next action will appear when a bus is available. Clear a skip or check the On Route list.')) + '</p></div>';
    return;
  }
  const meta = nextActionMeta(eligible);
  const presence = eligible.presence && eligible.presence.at_terminal ? t('next.at_terminal','At Terminal') : (eligible.presence && eligible.presence.available ? t('next.away','Away from terminal') : t('next.presence_unavailable','Presence unavailable'));
  panel.innerHTML = '<div class="nd-kicker"><i class="bi bi-send"></i> ' + esc(t('next.kicker','NEXT ACTION')) + '</div>'
    + '<div class="nd-bus"><i class="bi bi-bus-front"></i> ' + esc(eligible.bus_id) + ' ' + statusPill(eligible.status) + '</div>'
    + '<div class="nd-route"><i class="bi bi-signpost-split"></i> ' + esc(routeArrow(eligible.route_name || t('card.no_route','No route assigned'))) + '</div>'
    + '<div class="nd-driver"><i class="bi bi-person-badge"></i> ' + esc(eligible.driver_name || t('card.no_driver','No driver assigned')) + '</div>'
    + '<div class="nd-rows">'
    +   '<div class="nd-row"><span class="nd-label">' + esc(t('details.queue_position','Queue Position')) + '</span><span class="nd-value">#' + eligible.queue_position + '</span></div>'
    +   '<div class="nd-row"><span class="nd-label">' + esc(t('next.action','Next Action')) + '</span><span class="nd-value">' + esc(meta ? meta.label : '—') + '</span></div>'
    +   '<div class="nd-row"><span class="nd-label">' + esc(t('next.bus_presence','Bus Presence')) + '</span><span class="nd-value">' + esc(presence) + '</span></div>'
    +   '<div class="nd-row"><span class="nd-label">' + esc(t('next.schedule','Schedule')) + '</span><span class="nd-value">' + esc(eligible.schedule || '—') + '</span></div>'
    + '</div>'
    + '<div class="nd-actions">'
    +   '<button type="button" class="btn-action btn-view" onclick="openQueueDetail(\'' + esc(eligible.bus_id) + '\')" aria-label="' + esc(t('common.view_details','View Details')) + '"><span class="btn-ico"><i class="bi bi-eye"></i></span> ' + esc(t('common.view_details','View Details')) + '</button>'
    +   (meta ? '<button type="button" class="btn-action btn-dispatch-queue" onclick="runNextAction(\'' + esc(eligible.bus_id) + '\')" aria-label="' + esc(meta.label) + '"><i class="bi ' + meta.icon + '"></i> ' + esc(meta.label) + '</button>' : '')
    + '</div>';
}
function renderWhyNotPanel(){
  const panel = document.getElementById('whyNotPanel');
  if(!panel) return;
  const eligible = nextEligibleEntry();
  const earliest = earliestActiveEntry();
  if(!eligible || !earliest || String(earliest.bus_id) === String(eligible.bus_id)){
    panel.style.display = 'none';
    panel.innerHTML = '';
    return;
  }
  panel.innerHTML = '<div class="wn-title"><i class="bi bi-info-circle"></i> ' + esc(t('next.why_not','WHY NOT QUEUE #:position?', {position: earliest.queue_position})) + '</div>'
    + '<div class="wn-bus">' + esc(earliest.bus_id) + '</div>'
    + '<div class="wn-reason">' + esc(earliest.short_reason || '') + '</div>'
    + '<div class="wn-next">' + esc(t('next.why_not_next_action',':eligible has the next available action.', {eligible: eligible.bus_id})) + '</div>';
  panel.style.display = 'block';
}
function updateRouteFilter(){
  const sel = document.getElementById('routeFilter');
  if(!sel) return;
  const routes = [];
  queueState.entries.forEach(e => { if(e.route_name && routes.indexOf(e.route_name) === -1) routes.push(e.route_name); });
  routes.sort();
  const current = queueState.route;
  sel.innerHTML = '<option value="">' + esc(t('common.all_routes','All Routes')) + '</option>' + routes.map(r => '<option value="' + esc(r) + '">' + esc(r) + '</option>').join('');
  if(current && routes.indexOf(current) !== -1) sel.value = current;
  else { sel.value = ''; queueState.route = ''; }
}
function updateFilterChips(){
  const s = queueState.summary || {};
  const counts = {
    all: s.total || 0,
    waiting_for_driver: s.waiting_for_driver || 0,
    driver_assigned: s.driver_assigned || 0,
    checked_in: s.checked_in || 0,
    ready: s.ready || 0,
    departed: s.dispatched || 0
  };
  document.querySelectorAll('#queueFilterRow .filter-chip').forEach(chip => {
    const key = chip.dataset.status || 'all';
    const label = chip.dataset.label || key;
    chip.innerHTML = esc(label) + ' <span class="chip-count">' + (counts[key] || 0) + '</span>';
  });
}
function renderQueue(){
  const scrollY = window.scrollY;
  updateRouteFilter();
  renderAdminQueueBanner();
  renderNextDispatch();
  renderWhyNotPanel();
  updateFilterChips();
  const el = document.getElementById('queueList');
  if(!el) return;
  const list = filteredQueueEntries();
  let html;
  if(!queueState.entries.length){
    html = '<div class="empty-state" style="grid-column:1/-1;"><i class="bi bi-bus-front"></i><p><strong>' + esc(t('state.no_buses_title','NO BUSES ASSIGNED')) + '</strong></p><p>' + esc(t('state.no_buses_body','No active buses are assigned to this terminal. Assign buses to the terminal to start dispatching.')) + '</p></div>';
  } else if(!list.length){
    html = '<div class="empty-state" style="grid-column:1/-1;"><i class="bi bi-funnel"></i><p>' + esc(t('state.no_match','No buses match this filter.')) + '</p></div>';
  } else {
    html = list.map(queueCardHTML).join('');
  }
  el.innerHTML = html;
  window.scrollTo(0, scrollY);
  focusQueueBusIfRequested();
}
function focusQueueBusIfRequested(){
  if(queueFocusApplied) return;
  let busId = '';
  try { busId = new URLSearchParams(window.location.search).get('focus_bus') || ''; } catch(e) {}
  if(!busId) return;
  queueFocusApplied = true;
  const card = document.querySelector('[data-queue-bus="' + busId.replace(/["\\]/g, '\\$&') + '"]');
  if(!card) return;
  card.classList.add('qc-focus');
  try { card.scrollIntoView({behavior:'smooth', block:'center'}); } catch(e) { card.scrollIntoView(); }
  setTimeout(() => card.classList.remove('qc-focus'), 5000);
}
function renderQueueLoading(){
  const el = document.getElementById('queueList');
  if(!el) return;
  el.innerHTML = Array.from({length:3}).map(() => '<div class="queue-card skeleton-card"><div class="sk-line sk-lg"></div><div class="sk-line"></div><div class="sk-line sk-sm"></div></div>').join('');
  const panel = document.getElementById('nextDispatchPanel');
  if(panel) panel.innerHTML = '<div class="nd-kicker"><i class="bi bi-send"></i> ' + esc(t('next.kicker','NEXT BUS TO DISPATCH')) + '</div><div class="nd-loading"><span class="spinner-border spinner-border-sm me-2" role="status"></span>' + esc(t('common.loading_queue','Loading current queue...')) + '</div>';
}
function renderQueueError(msg, sessionExpired){
  const panel = document.getElementById('nextDispatchPanel');
  if(panel){ panel.style.display = 'none'; panel.innerHTML = ''; }
  const el = document.getElementById('queueList');
  if(!el) return;
  el.innerHTML = '<div class="empty-state queue-error" style="grid-column:1/-1;"><i class="bi bi-exclamation-triangle" style="color:#d32f2f;"></i>'
    + '<p><strong>' + esc(t('state.error_title','WE COULDN\'T LOAD THE QUEUE')) + '</strong></p>'
    + '<p>' + esc(msg || t('state.error_body','Your current queue information could not be retrieved.')) + '</p>'
    + (sessionExpired
        ? '<button type="button" class="btn-primary-app" style="max-width:180px;margin:10px auto;padding:8px 12px;font-size:13px;" onclick="logout()">' + esc(t('state.log_in_again','Log In Again')) + '</button>'
        : '<button type="button" class="btn-primary-app" style="max-width:180px;margin:10px auto;padding:8px 12px;font-size:13px;" onclick="loadQueue({force:true})">' + esc(t('common.try_again','Try Again')) + '</button>')
    + '</div>';
}
let queueRequestSeq = 0;
async function loadQueue(opts){
  opts = opts || {};
  if(queueState.loading && !opts.force) return;
  const reqId = ++queueRequestSeq;
  queueState.loading = true;
  if(!queueState.started) renderQueueLoading();
  try{
    const r = await fetch(API + 'get_trip_queue', {headers:apiHeaders()});
    const j = await parseApiResponse(r);
    if(reqId !== queueRequestSeq) return;
    if(!j && (r.status === 404 || r.status === 410)){
      console.warn('Queue API route is stale; reloading the dispatcher app.', r.status);
      recoverStaleApp();
      return;
    }
    if(j && j.code === 'stale_api_route'){
      console.warn('Queue API route is stale; reloading the dispatcher app.');
      recoverStaleApp();
      return;
    }
    if(r.status === 401){
      renderQueueError(t('state.session_expired','Your session has expired. Please log in again.'), true);
      return;
    }
    if(!j || !j.success){
      if(!queueState.started){
        renderQueueError(extractErrorMessage(r, j, t('state.error_body','Your current queue information could not be retrieved.')));
      } else {
        console.warn('Queue refresh failed:', (j && j.message) || r.status);
      }
      return;
    }
    const d = j.data || {};
    const payload = JSON.stringify(d);
    const changed = payload !== queueState.lastPayload;
    queueState.lastPayload = payload;
    queueState.entries = Array.isArray(d.entries) ? d.entries : [];
    queueState.history = Array.isArray(d.history) ? d.history : [];
    queueState.summary = d.summary || {};
    queueState.adminQueue = d.admin_queue || null;
    queueState.availableDrivers = Array.isArray(d.available_drivers) ? d.available_drivers : [];
    queueState.lastSuccess = Date.now();
    queueState.started = true;
    if(changed || opts.force) renderQueue();
    updateQueueUpdatedLabel();
  }catch(e){
    console.error('loadQueue error:', e);
    if(reqId === queueRequestSeq && !queueState.started) renderQueueError(t('state.network_error','Network error. Please check your connection and try again.'));
  }finally{
    if(reqId === queueRequestSeq) queueState.loading = false;
  }
}
function updateQueueUpdatedLabel(){
  const label = document.getElementById('queueUpdatedLabel');
  const stale = document.getElementById('queueStaleLabel');
  if(!label) return;
  if(!queueState.lastSuccess){
    label.textContent = '';
    if(stale) stale.style.display = 'none';
    return;
  }
  const secs = Math.max(0, Math.floor((Date.now() - queueState.lastSuccess) / 1000));
  label.textContent = secs < 5 ? t('common.updated_just_now','Queue updated just now') : t('common.updated_ago','Queue updated :seconds s ago', {seconds: secs});
  if(stale) stale.style.display = secs > 60 ? 'inline-flex' : 'none';
}
function startQueuePolling(){
  if(pollTimer) clearInterval(pollTimer);
  pollTimer = setInterval(function(){
    if(document.hidden) return;
    if(isAnyModalOpen()) return;
    const active = document.activeElement;
    if(active && (active.tagName === 'INPUT' || active.tagName === 'SELECT' || active.tagName === 'TEXTAREA')) return;
    loadQueue();
  }, 20000);
}
function initQueue(){
  loadQueue({force:true});
  startQueuePolling();
  setInterval(updateQueueUpdatedLabel, 1000);
}
document.addEventListener('visibilitychange', function(){
  updateQueueUpdatedLabel();
  if(!document.hidden && queueState.started) loadQueue();
});
window.addEventListener('pageshow', function(e){
  if(e.persisted && queueState.started) loadQueue({force:true});
});

function openQueueDetail(busId){
  const e = findQueueEntry(busId);
  if(!e) return;
  document.getElementById('tripDetailTitle').innerHTML = '<i class="bi bi-info-circle"></i> ' + esc(e.bus_id) + ' — #' + e.queue_position;
  let html = '';
  html += '<div class="detail-summary"><div class="ds-top"><span class="ds-bus"><i class="bi bi-bus-front"></i> ' + esc(e.bus_id) + '</span>' + statusPill(e.status) + '</div>'
    + '<div class="ds-route"><i class="bi bi-signpost-split"></i> ' + esc(routeArrow(e.route_name || t('card.no_route','No route assigned'))) + '</div></div>';
  html += '<div class="detail-sub"><i class="bi bi-person-badge"></i> ' + esc(e.driver_name || t('card.no_driver','No driver assigned')) + (e.schedule ? ' &bull; <i class="bi bi-clock"></i> ' + esc(e.schedule) : '') + '</div>';
  html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-list-ol"></i> ' + esc(t('details.queue_position','Queue Position')) + '</span><span class="qd-value">#' + e.queue_position + '</span></div>';
  html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-geo"></i> ' + esc(t('details.bus_presence','Bus Presence')) + '</span><span class="qd-value">' + esc(e.presence && e.presence.detail ? e.presence.detail : t('details.unavailable','Unavailable')) + '</span></div>';
  const checkInOk = !!(e.presence && e.presence.checked_in);
  const checkInAt = e.presence && (e.presence.checked_in_at || e.presence.updated_at);
  let checkInText;
  if(checkInOk && e.presence && e.presence.checked_in_by === 'dispatcher'){
    checkInText = t('details.check_in_by_dispatcher','Checked in by Dispatcher :name at :time', {name: e.presence.checked_in_by_name || t('details.dispatcher','Dispatcher'), time: formatClock12(checkInAt)});
  } else if(checkInOk){
    checkInText = t('details.check_in_ok','Checked in at :time', {time: formatClock12(checkInAt)});
  } else {
    checkInText = t('details.check_in_pending','Pending — driver must check in');
  }
  html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-person-check"></i> ' + esc(t('details.driver_check_in','Driver Check-In')) + '</span><span class="qd-value">' + esc(checkInText) + '</span></div>';
  html += '<div class="checkin-box">';
  if(checkInOk){
    html += '<div class="checkin-ok"><i class="bi bi-check-circle-fill"></i> <strong>' + esc(t('details.check_in_success','Driver Checked In')) + '</strong>'
      + '<div>' + esc(e.driver_name || '') + '</div>'
      + (e.presence && e.presence.checked_in_by === 'dispatcher' ? '<div>' + esc(t('details.checked_in_by','Checked in by: Dispatcher :name', {name: e.presence.checked_in_by_name || t('details.dispatcher','Dispatcher')})) + '</div>' : '')
      + '<div>' + esc(t('details.checked_in_time','Time: :time', {time: formatClock12(checkInAt)})) + '</div></div>';
  } else {
    html += '<div class="checkin-pending"><i class="bi bi-exclamation-triangle-fill"></i> ' + esc(t('details.check_in_pending','Pending — driver must check in')) + '</div>';
    if(e.driver_id){
      html += '<button type="button" class="btn-checkin" id="checkInBtn" onclick="checkInDriver(\'' + esc(e.bus_id) + '\')"><i class="bi bi-person-check"></i> ' + esc(t('action.check_in_driver','CHECK IN DRIVER')) + '</button>';
    }
  }
  html += '</div>';
  html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-signpost"></i> ' + esc(t('details.destination','Destination')) + '</span><span class="qd-value">' + esc(e.destination && e.destination.terminal_name ? e.destination.terminal_name : t('details.not_set','Not set')) + '</span></div>';
  if(e.current_location) html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-pin-map"></i> ' + esc(t('details.current_location','Current Location')) + '</span><span class="qd-value">' + esc(e.current_location) + '</span></div>';
  html += '<div class="detail-sub" style="margin-top:10px;"><i class="bi bi-clipboard-check"></i> <strong>' + esc(t('details.dispatch_readiness','DISPATCH READINESS')) + '</strong></div>';
  html += '<div class="checklist">' + (e.readiness || []).map(item => {
    const cls = item.ok ? 'ok' : (item.blocking ? 'bad' : 'warn');
    const icon = item.ok ? 'bi-check-circle-fill' : (item.blocking ? 'bi-x-octagon-fill' : 'bi-exclamation-triangle-fill');
    return '<div class="check-item ' + cls + '"><span class="ci-icon"><i class="bi ' + icon + '"></i></span><span class="ci-text"><strong>' + esc(item.label) + '</strong>' + (item.detail ? '<small>' + esc(item.detail) + '</small>' : '') + '</span></div>';
  }).join('') + '</div>';
  if(e.reasons && e.reasons.length){
    html += '<div class="issue-groups">' + e.reasons.map(g => {
      return '<div class="issue-group"><div class="ig-area">' + esc(g.area) + '</div><ul>' + g.items.map(i => '<li>' + esc(i) + '</li>').join('') + '</ul></div>';
    }).join('') + '</div>';
  }
  if(e.status === 'departed' && e.trip){
    html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-send"></i> ' + esc(t('details.dispatch_order','Dispatch Order')) + '</span><span class="qd-value">' + (e.trip.dispatch_order ? ordinal(e.trip.dispatch_order) : '—') + '</span></div>';
    html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-clock"></i> ' + esc(t('details.dispatch_time','Dispatch Time')) + '</span><span class="qd-value">' + esc(formatClock12(e.trip.departure_time)) + '</span></div>';
    html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-person-check"></i> ' + esc(t('details.dispatched_by','Dispatched By')) + '</span><span class="qd-value">' + esc(e.trip.dispatcher_name || '—') + '</span></div>';
    if(e.trip.overtake_reason) html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-exclamation-triangle"></i> ' + esc(t('details.out_of_order_reason','Out-of-order Reason')) + '</span><span class="qd-value">' + esc(e.trip.overtake_reason) + '</span></div>';
    if(e.trip.total_passengers != null) html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-people"></i> ' + esc(t('details.passengers','Passengers')) + '</span><span class="qd-value">' + e.trip.total_passengers + '</span></div>';
  }
  document.getElementById('tripDetailBody').innerHTML = html;
  const actions = [];
  const locked = adminQueueLocked();
  /* Status actions follow the dispatch queue: only the next bus in order can
     be assigned, checked in, prepared, or dispatched from here. */
  const nextInQueue = isNextInQueue(e);
  if(nextInQueue && e.status === 'waiting_for_driver'){ if(!locked) actions.push('<button type="button" class="btn-action btn-dispatch-queue" onclick="closeModal(\'tripDetailModal\');openAssignDriverModal(\'' + esc(e.bus_id) + '\')"><i class="bi bi-person-plus"></i> ' + esc(t('action.assign_driver','Assign Driver')) + '</button>'); }
  else if(nextInQueue && e.status === 'driver_assigned') actions.push('<button type="button" class="btn-action btn-dispatch-queue" onclick="closeModal(\'tripDetailModal\');checkInDriver(\'' + esc(e.bus_id) + '\')"><i class="bi bi-person-check"></i> ' + esc(t('action.check_in','Check In')) + '</button>');
  else if(nextInQueue && e.status === 'checked_in') actions.push('<button type="button" class="btn-action btn-dispatch-queue" onclick="closeModal(\'tripDetailModal\');openPrepareTrip(\'' + esc(e.bus_id) + '\')"><i class="bi bi-clipboard-check"></i> ' + esc(t('action.prepare_trip','Prepare Trip')) + '</button>');
  else if(nextInQueue && isDispatchable(e)) actions.push('<button type="button" class="btn-action btn-dispatch-queue" onclick="closeModal(\'tripDetailModal\');openPrepareTrip(\'' + esc(e.bus_id) + '\')"><i class="bi bi-send"></i> ' + esc(t('action.dispatch','Dispatch')) + '</button>');
  if(e.driver_id && !locked) actions.push('<button type="button" class="btn-action btn-view" onclick="closeModal(\'tripDetailModal\');openAssignDriverModal(\'' + esc(e.bus_id) + '\', true)"><i class="bi bi-arrow-left-right"></i> ' + esc(t('action.change_assignment','Change Bus Assignment')) + '</button>');
  if(e.status !== 'departed' && !locked) actions.push('<button type="button" class="btn-action btn-skip" onclick="closeModal(\'tripDetailModal\');openSkipModal(\'' + esc(e.bus_id) + '\')"><i class="bi bi-slash-circle"></i> ' + esc(t('action.skip','Skip')) + '</button>');
  if(e.skip && e.skip.active && !locked) actions.push('<button type="button" class="btn-action btn-view" onclick="closeModal(\'tripDetailModal\');clearSkip(\'' + esc(e.bus_id) + '\')"><i class="bi bi-arrow-counterclockwise"></i> ' + esc(t('action.clear_skip','Clear Skip')) + '</button>');
  if(e.status === 'departed' && e.trip && e.trip.id){
    actions.push('<button type="button" class="btn-action btn-view" onclick="closeModal(\'tripDetailModal\');goToTrip(' + Number(e.trip.id) + ')"><i class="bi bi-eye"></i> ' + esc(t('action.view_trip','View Trip')) + '</button>');
    actions.push('<button type="button" class="btn-action btn-view" onclick="closeModal(\'tripDetailModal\');goToTrip(' + Number(e.trip.id) + ')"><i class="bi bi-fuel-pump"></i> ' + esc(t('action.fuel','Fuel/ODO')) + '</button>');
    if(Number(e.destination && e.destination.terminal_id) === Number(TERMINAL_ID)) actions.push('<button type="button" class="btn-action btn-arrive" onclick="closeModal(\'tripDetailModal\');goToTrip(' + Number(e.trip.id) + ')"><i class="bi bi-flag"></i> ' + esc(t('action.arrive','Arrive')) + '</button>');
  }
  document.getElementById('tripDetailActions').innerHTML = actions.join('');
  showModal('tripDetailModal');
}
async function checkInDriver(busId){
  const btn = document.getElementById('checkInBtn');
  if(btn){ btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + esc(t('action.check_in_driver','CHECK IN DRIVER')); }
  try{
    const r = await fetch(API + 'check_in_driver', {
      method:'POST',
      headers:apiPostHeaders(),
      body:JSON.stringify({bus_id: busId, csrf_token: CSRF_TOKEN})
    });
    const j = await parseApiResponse(r);
    if(isResponseSuccess(r, j)){
      closeModal('tripDetailModal');
      await loadQueue({force:true});
      showAlert('success', t('checkin.success_title','Driver Checked In'), t('checkin.success_body','You checked in driver :driver.', {driver: (j && j.data && j.data.driver_name) || busId}));
    } else {
      showAlert('error', t('alert.action_failed','Action Failed'), extractErrorMessage(r, j, t('checkin.error','The driver check-in could not be saved. Please try again.')));
    }
  }catch(error){
    console.error('checkInDriver error:', error);
    showAlert('error', t('alert.action_failed','Action Failed'), t('state.network_error','Network error. Please check your connection and try again.'));
  }finally{
    if(btn){ btn.disabled = false; }
  }
}
function startQueueDispatch(busId, opts){
  const e = findQueueEntry(busId);
  if(!e) return;
  if(!isDispatchable(e)) return;
  if(!isNextInQueue(e)){
    showAlert('error', t('alert.queue_order_title','Follow the Dispatch Queue'), queueOrderMessage());
    loadQueue({force:true});
    return;
  }
  opts = opts || {};
  const readyEarlier = earliestReadyEntry();
  if(!adminQueueLocked() && !opts.overtakeReason && readyEarlier && String(readyEarlier.bus_id) !== String(e.bus_id) && Number(e.queue_position) > Number(readyEarlier.queue_position)){
    openOvertakeModal(e, readyEarlier, function(reason){
      openConfirmDispatchModal(e.bus_id, Object.assign({}, opts, {overtakeReason: reason}));
    });
    return;
  }
  openConfirmDispatchModal(e.bus_id, opts);
}
function openConfirmDispatchModal(busId, opts){
  const e = findQueueEntry(busId);
  if(!e) return;
  opts = opts || {};
  if(opts.override) opts.overrideNote = e.short_reason || 'Pending requirements acknowledged';
  pendingDispatch = {busId: busId, opts: opts};
  const passengerCount = (opts.passengerCount !== undefined && opts.passengerCount !== null) ? opts.passengerCount : 0;
  document.getElementById('confirmDispatchBody').innerHTML =
    '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-bus-front"></i> ' + esc(t('details.bus','Bus')) + '</span><span class="qd-value">' + esc(e.bus_id) + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-person"></i> ' + esc(t('details.driver','Driver')) + '</span><span class="qd-value">' + esc(e.driver_name || '—') + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-signpost-split"></i> ' + esc(t('details.route','Route')) + '</span><span class="qd-value">' + esc(routeArrow(e.route_name || '—')) + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-hash"></i> ' + esc(t('prepare.trip_number','Trip Number')) + '</span><span class="qd-value">' + esc(e.next_trip_number || e.cycle || 1) + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-people"></i> ' + esc(t('details.passengers','Passengers Onboard')) + '</span><span class="qd-value">' + esc(passengerCount) + '</span></div>'
    + ((opts.startingOdo !== undefined && opts.startingOdo !== null && opts.startingOdo !== '') ? '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-speedometer2"></i> ' + esc(t('prepare.starting_odo','Starting ODO')) + '</span><span class="qd-value">' + esc(opts.startingOdo) + ' KM</span></div>' : '')
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-list-ol"></i> ' + esc(t('details.queue_position','Queue Position')) + '</span><span class="qd-value">#' + e.queue_position + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-activity"></i> ' + esc(t('confirm.status','Status')) + '</span><span class="qd-value">' + esc(e.status_label || '') + '</span></div>';
  const warn = document.getElementById('confirmDispatchWarning');
  const warnings = [];
  if(opts.overtakeReason) warnings.push(esc(t('confirm.overtake_warning','Queue order: dispatching out of order (:reason).', {reason: opts.overtakeReason})));
  if(opts.overrideNote) warnings.push(esc(t('confirm.override_warning','Pending requirements: :note', {note: opts.overrideNote})));
  if(warnings.length){
    warn.innerHTML = '<i class="bi bi-exclamation-triangle"></i><span>' + warnings.join('<br>') + '</span>';
    warn.style.display = 'flex';
  } else {
    warn.style.display = 'none';
    warn.innerHTML = '';
  }
  showModal('confirmDispatchModal');
}
function confirmQueueDispatch(){
  if(!pendingDispatch) return;
  const e = findQueueEntry(pendingDispatch.busId);
  if(!e){ closeModal('confirmDispatchModal'); return; }
  submitQueueDispatch(e, pendingDispatch.opts || {});
}
async function submitQueueDispatch(e, opts){
  if(dispatchInFlight) return;
  opts = opts || {};
  dispatchInFlight = true;
  const btn = document.getElementById('confirmDispatchBtn');
  if(btn){ btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + esc(t('confirm.dispatching','Dispatching...')); }
  try{
    const r = await fetch(API + 'create_dispatch', {
      method:'POST',
      headers:apiPostHeaders(),
      body:JSON.stringify({
        bus_id: e.bus_id,
        driver_id: e.driver_id,
        route_name: e.route_name,
        to_terminal_id: e.destination ? e.destination.terminal_id : 0,
        passenger_count: (opts.passengerCount !== undefined && opts.passengerCount !== null) ? opts.passengerCount : 0,
        starting_odo: (opts.startingOdo !== undefined && opts.startingOdo !== null) ? opts.startingOdo : '',
        terminal_id: TERMINAL_ID,
        queue_position: e.queue_position,
        overtake_reason: opts.overtakeReason || '',
        override_note: opts.overrideNote || ''
      })
    });
    const j = await parseApiResponse(r);
    if(isResponseSuccess(r, j)){
      closeModal('confirmDispatchModal');
      pendingDispatch = null;
      await loadQueue({force:true});
      showDispatchSuccess(e, j);
    } else if(r.status === 409 && j && j.code === 'queue_overtake_required'){
      closeModal('confirmDispatchModal');
      openOvertakeModal(e, {bus_id:j.data.ready_bus_id, queue_position:j.data.ready_position}, function(reason){
        submitQueueDispatch(e, Object.assign({}, opts, {overtakeReason: reason}));
      });
    } else if(r.status === 409 && j && j.code === 'admin_queue_locked'){
      closeModal('confirmDispatchModal');
      pendingDispatch = null;
      showAlert('error', t('alert.queue_updated','Queue Updated'), extractErrorMessage(r, j, t('alert.admin_queue_locked','The Admin dispatch order is official. Dispatch the next bus in the order.')));
      await loadQueue({force:true});
    } else if(r.status === 409 && j && (j.code === 'bus_already_dispatched' || j.code === 'driver_already_dispatched')){
      closeModal('confirmDispatchModal');
      pendingDispatch = null;
      const when = j.data && j.data.departure_time ? (' ' + formatClock12(j.data.departure_time)) : '';
      const who = j.code === 'bus_already_dispatched' ? ('Bus ' + e.bus_id) : t('details.driver','Driver');
      showAlert('error', t('alert.queue_updated','Queue Updated'), t('alert.already_dispatched',':who was already dispatched:when by another dispatcher. The queue has been refreshed.', {who: who, when: when}));
      await loadQueue({force:true});
    } else {
      const msg = extractErrorMessage(r, j, t('alert.dispatch_error','The trip could not be dispatched. The queue may have changed. Please refresh and try again.'));
      showAlert('error', t('alert.dispatch_failed','Dispatch Failed'), msg);
    }
  }catch(err){
    console.error('submitQueueDispatch error:', err);
    showAlert('error', t('alert.dispatch_failed','Dispatch Failed'), t('state.network_error','Network error. Please check your connection and try again.'));
  }finally{
    dispatchInFlight = false;
    if(btn){ btn.disabled = false; btn.innerHTML = '<i class="bi bi-send"></i> ' + esc(t('confirm.button','CONFIRM DISPATCH')); }
  }
}
function showDispatchSuccess(e, j){
  const order = j && j.dispatch_order ? ordinal(j.dispatch_order) : '—';
  const time = j && j.dispatch_time ? j.dispatch_time : '';
  const tripId = j && j.dispatch_id ? j.dispatch_id : (e.trip ? e.trip.id : null);
  document.getElementById('dispatchSuccessBody').innerHTML =
    '<div class="detail-summary"><div class="ds-top"><span class="ds-bus"><i class="bi bi-check-circle"></i> ' + esc(e.bus_id) + '</span></div>'
    + '<div class="ds-route">' + esc(routeArrow(e.route_name || '')) + '</div></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-list-ol"></i> ' + esc(t('success.order','Actual Dispatch Order')) + '</span><span class="qd-value">' + order + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-clock"></i> ' + esc(t('success.time','Dispatch Time')) + '</span><span class="qd-value">' + esc(formatClock12(time) || '—') + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-list-ol"></i> ' + esc(t('details.original_queue','Original Queue')) + '</span><span class="qd-value">#' + e.queue_position + '</span></div>';
  const viewBtn = document.getElementById('successViewTripBtn');
  if(tripId){
    viewBtn.style.display = '';
    viewBtn.onclick = function(){ closeModal('dispatchSuccessModal'); goToTrip(tripId); };
  } else {
    viewBtn.style.display = 'none';
    viewBtn.onclick = null;
  }
  showModal('dispatchSuccessModal');
}
function openSkipModal(busId){
  const e = findQueueEntry(busId);
  if(!e) return;
  skipTargetBus = busId;
  document.getElementById('skipBusTitle').textContent = t('skip.title','SKIP BUS') + ' — #' + e.queue_position;
  document.getElementById('skipBusInfo').textContent = e.bus_id + ' • ' + (e.driver_name || t('card.no_driver','No driver assigned')) + ' • ' + routeArrow(e.route_name || t('card.no_route','No route assigned'));
  document.getElementById('skipBusReason').textContent = e.short_reason || '';
  document.querySelectorAll('input[name="skipReason"]').forEach(r => { r.checked = false; });
  document.getElementById('skipNotes').value = '';
  document.getElementById('skipError').style.display = 'none';
  showModal('skipModal');
}
async function submitSkip(){
  const e = findQueueEntry(skipTargetBus);
  if(!e) return;
  const selected = document.querySelector('input[name="skipReason"]:checked');
  const err = document.getElementById('skipError');
  if(!selected){
    err.textContent = t('skip.error_reason','Please select a reason before skipping this trip.');
    err.style.display = 'block';
    return;
  }
  const notes = document.getElementById('skipNotes').value.trim();
  const btn = document.getElementById('skipConfirmBtn');
  if(btn){ btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + esc(t('skip.skipping','Skipping...')); }
  try{
    const r = await fetch(API + 'skip_queue_bus', {
      method:'POST',
      headers:apiPostHeaders(),
      body:JSON.stringify({bus_id:e.bus_id, action:'skip', reason_category:selected.value, notes:notes, csrf_token:CSRF_TOKEN})
    });
    const j = await parseApiResponse(r);
    if(isResponseSuccess(r, j)){
      closeModal('skipModal');
      await loadQueue({force:true});
      showAlert('success', t('alert.queue_updated','Queue Updated'), t('skip.success','Bus :bus was temporarily skipped.', {bus: e.bus_id}));
    } else {
      err.textContent = extractErrorMessage(r, j, t('skip.error_reason','Please select a reason before skipping this trip.'));
      err.style.display = 'block';
    }
  }catch(error){
    console.error('submitSkip error:', error);
    err.textContent = t('state.network_error','Network error. Please check your connection and try again.');
    err.style.display = 'block';
  }finally{
    if(btn){ btn.disabled = false; btn.innerHTML = '<i class="bi bi-slash-circle"></i> ' + esc(t('skip.button','SKIP BUS')); }
  }
}
function clearSkip(busId){
  const e = findQueueEntry(busId);
  if(!e) return;
  showConfirm(t('action.clear_skip','Clear Skip'), e.bus_id + ' (#' + e.queue_position + ')', async function(){
    try{
      const r = await fetch(API + 'skip_queue_bus', {
        method:'POST',
        headers:apiPostHeaders(),
        body:JSON.stringify({bus_id:e.bus_id, action:'clear', csrf_token:CSRF_TOKEN})
      });
      const j = await parseApiResponse(r);
      if(isResponseSuccess(r, j)){
        await loadQueue({force:true});
        showAlert('success', t('alert.queue_updated','Queue Updated'), e.bus_id + ' — ' + t('action.clear_skip','Clear Skip'));
      } else {
        showAlert('error', t('alert.action_failed','Action Failed'), extractErrorMessage(r, j, t('state.network_error','Network error. Please check your connection and try again.')));
      }
    }catch(error){
      console.error('clearSkip error:', error);
      showAlert('error', t('alert.action_failed','Action Failed'), t('state.network_error','Network error. Please check your connection and try again.'));
    }
  });
}
function openAssignDriverModal(busId, isChange){
  const e = findQueueEntry(busId);
  if(!e) return;
  assignDriverTarget = busId;
  assignDriverIsChange = !!isChange || (!!e.driver_id && e.next_action !== 'assign_driver');
  const title = document.getElementById('assignDriverTitle');
  if(title) title.innerHTML = '<i class="bi bi-person-plus"></i> ' + esc(t(assignDriverIsChange ? 'assign.change_title' : 'assign.title', assignDriverIsChange ? 'CHANGE BUS ASSIGNMENT' : 'ASSIGN DRIVER'));
  const body = document.getElementById('assignDriverBody');
  const currentLine = '<div class="queue-detail-row"><span class="qd-label">' + esc(t('assign.current','Current driver')) + '</span><span class="qd-value">' + esc(e.driver_name || t('card.no_driver','No driver assigned')) + '</span></div>';
  body.innerHTML = '<div class="queue-detail-row"><span class="qd-label">' + esc(t('details.bus','Bus')) + '</span><span class="qd-value">' + esc(e.bus_id) + '</span></div>' + currentLine;
  const select = document.getElementById('assignDriverSelect');
  const drivers = (queueState.availableDrivers || []).filter(d => !assignDriverIsChange || String(d.driver_id) !== String(e.driver_id || ''));
  let html = '<option value="">' + esc(t('assign.select','Select driver')) + '</option>';
  drivers.forEach(d => { html += '<option value="' + esc(d.driver_id) + '">' + esc((d.full_name || d.driver_id) + ' (' + d.driver_id + ')') + '</option>'; });
  select.innerHTML = html;
  select.disabled = drivers.length === 0;
  const btn = document.getElementById('assignDriverConfirmBtn');
  btn.disabled = drivers.length === 0;
  btn.innerHTML = '<i class="bi bi-person-check"></i> ' + esc(t(assignDriverIsChange ? 'assign.change_confirm' : 'assign.confirm', assignDriverIsChange ? 'CHANGE ASSIGNMENT' : 'ASSIGN DRIVER'));
  const err = document.getElementById('assignDriverError');
  err.style.display = 'none';
  err.textContent = '';
  if(!drivers.length){
    err.textContent = t('assign.no_drivers','No available drivers right now. All active drivers are already assigned to a bus.');
    err.style.display = 'block';
  }
  showModal('assignDriverModal');
}
async function submitAssignDriver(){
  const e = findQueueEntry(assignDriverTarget);
  if(!e) return;
  const select = document.getElementById('assignDriverSelect');
  const driverId = select.value;
  const err = document.getElementById('assignDriverError');
  if(!driverId){
    err.textContent = t('assign.error_select','Please select a driver.');
    err.style.display = 'block';
    return;
  }
  const btn = document.getElementById('assignDriverConfirmBtn');
  const original = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + esc(t('assign.saving','Saving...'));
  try{
    const r = await fetch(API + 'assign_driver', {
      method:'POST',
      headers:apiPostHeaders(),
      body:JSON.stringify({bus_id:e.bus_id, driver_id:driverId, action: assignDriverIsChange ? 'change' : 'assign', csrf_token:CSRF_TOKEN})
    });
    const j = await parseApiResponse(r);
    if(isResponseSuccess(r, j)){
      closeModal('assignDriverModal');
      await loadQueue({force:true});
      await preloadDropdowns();
      const name = (j && j.data && j.data.driver_name) ? j.data.driver_name : driverId;
      showAlert('success', t('alert.queue_updated','Queue Updated'), assignDriverIsChange
        ? t('assign.change_success','Bus :bus is now assigned to :driver.', {bus:e.bus_id, driver:name})
        : t('assign.success',':driver is now assigned to Bus :bus.', {driver:name, bus:e.bus_id}));
    } else {
      err.textContent = extractErrorMessage(r, j, t('assign.error','The driver assignment could not be saved. Please try again.'));
      err.style.display = 'block';
    }
  }catch(error){
    console.error('submitAssignDriver error:', error);
    err.textContent = t('state.network_error','Network error. Please check your connection and try again.');
    err.style.display = 'block';
  }finally{
    btn.disabled = false;
    btn.innerHTML = original;
  }
}
function openPrepareTrip(busId){
  const e = findQueueEntry(busId);
  if(!e) return;
  if(!isNextInQueue(e)){
    showAlert('error', t('alert.queue_order_title','Follow the Dispatch Queue'), queueOrderMessage());
    loadQueue({force:true});
    return;
  }
  prepareTargetBus = busId;
  const ready = e.status === 'ready';
  const titleEl = document.getElementById('prepareTitle');
  if(titleEl) titleEl.innerHTML = '<i class="bi ' + (ready ? 'bi-send' : 'bi-clipboard-check') + '"></i> ' + esc(t(ready ? 'prepare.dispatch_title' : 'prepare.title', ready ? 'DISPATCH TRIP' : 'PREPARE TRIP'));
  const fuel = e.fuel_reference || {};
  const presence = e.presence || {};
  const dest = e.destination && e.destination.terminal_name ? e.destination.terminal_name : t('details.not_set','Not set');
  document.getElementById('prepareBody').innerHTML =
      '<div class="detail-summary"><div class="ds-top"><span class="ds-bus"><i class="bi bi-bus-front"></i> ' + esc(e.bus_id) + '</span>' + statusPill(e.status) + '</div>'
    + '<div class="ds-route"><i class="bi bi-signpost-split"></i> ' + esc(routeArrow(e.route_name || t('card.no_route','No route assigned'))) + '</div></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-person-badge"></i> ' + esc(t('details.driver','Driver')) + '</span><span class="qd-value">' + esc(e.driver_name || '—') + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-hash"></i> ' + esc(t('prepare.trip_number','Trip Number')) + '</span><span class="qd-value">' + esc(e.next_trip_number || e.cycle || 1) + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-geo"></i> ' + esc(t('details.destination','Destination')) + '</span><span class="qd-value">' + esc(dest) + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-geo-alt"></i> ' + esc(t('prepare.presence','Bus Presence')) + '</span><span class="qd-value">' + esc(presence.detail || '—') + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-speedometer2"></i> ' + esc(t('prepare.fuel','Fuel / Odometer')) + '</span><span class="qd-value">' + esc(fuel.detail || '—') + (fuel.starting_odo ? ' (' + fuel.starting_odo + ' KM)' : '') + '</span></div>'
    + ((e.prepared && e.prepared.ready) ? '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-clipboard-check"></i> ' + esc(t('prepare.prepared','Prepared')) + '</span><span class="qd-value">' + esc((e.prepared.by_name || '') + (e.prepared.at ? ' • ' + formatClock12(e.prepared.at) : '')) + '</span></div>' : '')
    + (ready ? '' : '<div class="confirm-warning" style="display:flex;"><i class="bi bi-exclamation-triangle"></i><span>' + esc(e.short_reason || t('prepare.not_ready','Complete the required pre-trip items before dispatching.')) + '</span></div>');
  const input = document.getElementById('preparePassengerCount');
  input.value = (e.passenger_count !== null && e.passenger_count !== undefined) ? e.passenger_count : 0;
  const odoInput = document.getElementById('prepareStartingOdo');
  const suggestedOdo = (fuel.ending_odo !== null && fuel.ending_odo !== undefined && fuel.ending_odo !== '')
    ? fuel.ending_odo
    : fuel.starting_odo;
  if(odoInput) odoInput.value = (suggestedOdo !== null && suggestedOdo !== undefined && suggestedOdo !== '') ? suggestedOdo : '';
  renderHistoricalKpl(document.getElementById('prepareHistoricalKpl'), fuel.historical_kpl);
  const prepared = !!(e.prepared && e.prepared.ready);
  const btn = document.getElementById('prepareDispatchBtn');
  if(ready){
    btn.disabled = false;
    btn.style.opacity = '';
    btn.innerHTML = '<i class="bi bi-send"></i> ' + esc(t('action.dispatch','Dispatch'));
    btn.onclick = dispatchFromPrepare;
  } else if(!prepared){
    btn.disabled = false;
    btn.style.opacity = '';
    btn.innerHTML = '<i class="bi bi-clipboard-check"></i> ' + esc(t('prepare.mark','MARK AS PREPARED'));
    btn.onclick = submitPrepareTrip;
  } else {
    btn.disabled = true;
    btn.style.opacity = '.55';
    btn.innerHTML = '<i class="bi bi-send"></i> ' + esc(t('action.dispatch','Dispatch'));
    btn.onclick = dispatchFromPrepare;
  }
  const err = document.getElementById('prepareError');
  err.style.display = 'none';
  err.textContent = '';
  showModal('prepareModal');
}
async function submitPrepareTrip(){
  const e = findQueueEntry(prepareTargetBus);
  if(!e || isPreparingTrip) return;
  isPreparingTrip = true;
  const btn = document.getElementById('prepareDispatchBtn');
  const original = btn.innerHTML;
  let reopened = false;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + esc(t('prepare.saving','Preparing...'));
  const err = document.getElementById('prepareError');
  err.style.display = 'none';
  try{
    const r = await fetch(API + 'prepare_trip', {
      method:'POST',
      headers:apiPostHeaders(),
      body:JSON.stringify({bus_id:e.bus_id, csrf_token:CSRF_TOKEN})
    });
    const j = await parseApiResponse(r);
    if(isResponseSuccess(r, j)){
      await loadQueue({force:true});
      reopened = true;
      openPrepareTrip(e.bus_id);
      return;
    }
    err.textContent = extractErrorMessage(r, j, t('prepare.error','The trip preparation could not be saved. Please try again.'));
    err.style.display = 'block';
  }catch(error){
    console.error('submitPrepareTrip error:', error);
    err.textContent = t('state.network_error','Network error. Please check your connection and try again.');
    err.style.display = 'block';
  }finally{
    isPreparingTrip = false;
    if(!reopened){
      btn.disabled = false;
      btn.innerHTML = original;
    }
  }
}
function dispatchFromPrepare(){
  const e = findQueueEntry(prepareTargetBus);
  if(!e) return;
  if(e.status !== 'ready'){
    const err = document.getElementById('prepareError');
    err.textContent = e.short_reason || t('prepare.not_ready','Complete the required pre-trip items before dispatching.');
    err.style.display = 'block';
    return;
  }
  const passengers = parseInt(document.getElementById('preparePassengerCount').value, 10);
  const startingOdo = document.getElementById('prepareStartingOdo') ? document.getElementById('prepareStartingOdo').value : '';
  const startingOdoValue = parseFloat(startingOdo);
  if(!startingOdo || isNaN(startingOdoValue) || startingOdoValue <= 0){
    const odoErr = document.getElementById('prepareError');
    odoErr.textContent = t('alert.starting_odo_required_body','Enter the bus\'s actual odometer reading before dispatching.');
    odoErr.style.display = 'block';
    return;
  }
  closeModal('prepareModal');
  startQueueDispatch(e.bus_id, {
    passengerCount: isNaN(passengers) ? 0 : Math.max(0, passengers),
    startingOdo: startingOdoValue
  });
}
function openOvertakeModal(target, ready, onContinue){
  overtakeContinue = onContinue;
  const readyPosition = ready.queue_position || ready.ready_position || '—';
  document.getElementById('overtakeReady').textContent = t('overtake.ready_body',':bus is Queue #:position and is also ready.', {bus: ready.bus_id || '', position: readyPosition});
  document.getElementById('overtakeTarget').textContent = t('overtake.body',':bus is Queue #:position.', {bus: target.bus_id, position: (target.queue_position || '—')});
  document.getElementById('overtakeReason').value = '';
  document.getElementById('overtakeError').style.display = 'none';
  showModal('overtakeModal');
}
function submitOvertake(){
  const reason = document.getElementById('overtakeReason').value;
  const err = document.getElementById('overtakeError');
  if(!reason){
    err.textContent = t('overtake.error','Please select a reason to continue.');
    err.style.display = 'block';
    return;
  }
  closeModal('overtakeModal');
  const cb = overtakeContinue;
  overtakeContinue = null;
  if(typeof cb === 'function') cb(reason);
}

async function preloadDropdowns(){
  const [b,d,r]=await Promise.all([
    fetchJson(API+'get_available_buses',{headers:apiHeaders()}),
    fetchJson(API+'get_available_drivers',{headers:apiHeaders()}),
    fetchJson(API+'get_routes',{headers:apiHeaders()})
  ]);
  cacheBuses=b.data||[]; cacheDrivers=d.data||[]; cacheRoutes=r.data||[];
  return true;
}
preloadDropdowns();

function setSelectIfOption(selectId, value){
  const sel = document.getElementById(selectId);
  if(!sel) return false;
  for(let i=0;i<sel.options.length;i++){
    if(String(sel.options[i].value) === String(value)){ sel.value = sel.options[i].value; return true; }
  }
  return false;
}
function resolveDriverName(driverId){
  const drv = cacheDrivers.find(d => String(d.driver_id) === String(driverId));
  if(drv) return drv.full_name;
  const queueDrv = queueState.entries.find(e => String(e.driver_id) === String(driverId));
  if(queueDrv && queueDrv.driver_name) return queueDrv.driver_name;
  return String(driverId);
}
async function populateDropdowns(){
  if(cacheBuses.length===0 || cacheDrivers.length===0) await preloadDropdowns();
  const activeEntries = queueState.entries.filter(e => e.status !== 'departed');
  const queueBusIds = activeEntries.map(e => String(e.bus_id));
  let bs='<option value="">Select Bus</option>';
  if(activeEntries.length){
    bs += '<optgroup label="Terminal Queue">';
    activeEntries.forEach(e => {
      const sw = statusWord(e);
      const statusWordText = sw.charAt(0).toUpperCase() + sw.slice(1);
      const pos = e.queue_position ? (' · Queue #'+e.queue_position) : '';
      const label = e.bus_id + (e.plate_number ? (' - '+e.plate_number) : '') + ' · ' + statusWordText + pos;
      bs += '<option value="'+esc(e.bus_id)+'"'+(e.status==='ready'?'':' disabled')+'>'+esc(label)+'</option>';
    });
    bs += '</optgroup>';
  }
  const otherBuses = cacheBuses.filter(b => queueBusIds.indexOf(String(b.bus_id)) === -1);
  if(otherBuses.length){
    bs += '<optgroup label="Other Available Buses">';
    otherBuses.forEach(b => { bs += '<option value="'+esc(b.bus_id)+'">'+esc(b.bus_id + (b.plate_number ? (' - '+b.plate_number) : '') + ' · Not in queue')+'</option>'; });
    bs += '</optgroup>';
  }
  document.getElementById('newBus').innerHTML=bs;

  let ds='<option value="">Select Driver</option>';
  const queueDrivers = [];
  activeEntries.forEach(e => {
    if(e.driver_id && !queueDrivers.some(d => String(d.driver_id) === String(e.driver_id))){
      queueDrivers.push({driver_id:e.driver_id, name:e.driver_name, checked_in: !!(e.presence && e.presence.checked_in)});
    }
  });
  if(queueDrivers.length){
    ds += '<optgroup label="Queue Drivers">';
    queueDrivers.forEach(d => { ds += '<option value="'+esc(d.driver_id)+'">'+esc((d.name||d.driver_id)+' ('+d.driver_id+') · '+(d.checked_in?'Checked in':'Not checked in'))+'</option>'; });
    ds += '</optgroup>';
  }
  const queueDriverIds = queueDrivers.map(d => String(d.driver_id));
  const otherDrivers = cacheDrivers.filter(d => queueDriverIds.indexOf(String(d.driver_id)) === -1);
  if(otherDrivers.length){
    ds += '<optgroup label="Other Available Drivers">';
    otherDrivers.forEach(d => { ds += '<option value="'+esc(d.driver_id)+'">'+esc((d.full_name||d.driver_id)+' ('+d.driver_id+')')+'</option>'; });
    ds += '</optgroup>';
  }
  document.getElementById('newDriver').innerHTML=ds;

  let rs='<option value="">Select Route</option>';
  cacheRoutes.forEach(r=>{rs+=`<option value="${r}">${r}</option>`;});
  document.getElementById('newRoute').innerHTML=rs;
  let ts='<option value="">Dest. Terminal</option>';
  cacheTerminals.forEach(t=>{ts+=`<option value="${t.id}">${t.terminal_name}</option>`;});
  document.getElementById('newDestTerminal').innerHTML=ts;
}

function renderBusQueueInfo(entry){
  const box = document.getElementById('busQueueBadge');
  if(!box) return;
  const submitBtn = document.querySelector('#createModal .btn-primary-app');
  if(!entry){
    box.style.display = 'none';
    box.innerHTML = '';
    if(submitBtn) submitBtn.disabled = false;
    return;
  }
  const items = (entry.readiness || []).map(item => {
    const cls = item.ok ? 'ok' : 'warn';
    const icon = item.ok ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
    return '<span class="qrb-item '+cls+'"><i class="bi '+icon+'"></i> '+esc(item.label)+'</span>';
  }).join('');
  box.innerHTML = '<div class="qrb-head">'+statusPill(entry.status)+'<span class="qrb-pos">Queue #'+entry.queue_position+'</span></div>'
    + '<div class="qrb-reason">'+esc(entry.short_reason || '')+'</div>'
    + '<div class="qrb-items">'+items+'</div>';
  box.style.display = 'block';
  if(submitBtn) submitBtn.disabled = entry.status !== 'ready';
}

function updateFuelLinkedBadge(bus, driverName){
  const badge = document.getElementById('fuelDispatchBadge');
  if(!badge) return;
  if(!bus && !driverName){
    badge.style.display = 'none';
    return;
  }
  const busTxt = bus ? (bus.bus_id + (bus.plate_number ? ' (' + bus.plate_number + ')' : '')) : 'Not selected';
  const drvTxt = driverName ? driverName : 'Not selected';
  document.getElementById('fuelLinkedBus').textContent = busTxt;
  document.getElementById('fuelLinkedDriver').textContent = drvTxt;
  badge.style.display = 'block';
}

function renderHistoricalKpl(el, kpl){
  if(!el) return;
  if(kpl !== null && kpl !== undefined && Number(kpl) > 0){
    el.textContent = Number(kpl).toFixed(2) + ' ' + t('form.kpl_unit','KM/L');
  } else {
    el.textContent = t('form.no_historical_kpl','No historical KPL available');
  }
}

/* Load the bus fuel/odometer reference for the dispatch form. Returns null on
   any failure, recovers a stale (rotated) app session, and never leaves the
   form silently showing the initial dash. */
async function loadBusFuelInfo(busId){
  const res = await fetch(API + 'get_bus_fuel_info?bus_id=' + encodeURIComponent(busId), {headers: apiHeaders()});
  if(res.status === 404 || res.status === 410){ recoverStaleApp(); return null; }
  let j = null;
  try { j = await res.json(); } catch(e){ j = null; }
  if(!j || j.success !== true || !j.data){ return null; }
  return j.data;
}

async function onBusSelected(busId){
  if(!busId){
    document.getElementById('fuelDispatchBadge').style.display='none';
    renderHistoricalKpl(document.getElementById('newHistoricalKpl'), null);
    renderBusQueueInfo(null);
    const emptyDriverSelect = document.getElementById('newDriver');
    if(emptyDriverSelect) emptyDriverSelect.disabled = false;
    return;
  }
  const bus = cacheBuses.find(b => String(b.bus_id) === String(busId));
  const entry = findQueueEntry(busId);
  renderBusQueueInfo(entry);
  if(entry){
    if(entry.route_name) setSelectIfOption('newRoute', entry.route_name);
    if(entry.destination && entry.destination.terminal_id) setSelectIfOption('newDestTerminal', String(entry.destination.terminal_id));
  }

  // Auto-fill the driver assigned to this bus whenever the current choice does
  // not match it: queue entry driver first, then the driver_bus_assignment
  // pairing (assigned_driver_id) resolved by the buses API.
  const driverSelect = document.getElementById('newDriver');
  const assignedDriverId = (entry && entry.driver_id)
    ? String(entry.driver_id)
    : (bus && bus.assigned_driver_id ? String(bus.assigned_driver_id) : '');
  if(driverSelect && assignedDriverId && String(driverSelect.value) !== assignedDriverId){
    setSelectIfOption('newDriver', assignedDriverId);
  }
  if(driverSelect) driverSelect.disabled = !!(entry && entry.driver_id);
  let driverName = '';
  if(driverSelect && driverSelect.value){
    driverName = resolveDriverName(driverSelect.value);
  }
  updateFuelLinkedBadge(bus, driverName);

  const kplEl = document.getElementById('newHistoricalKpl');
  try {
    const data = await loadBusFuelInfo(busId);
    if(data){
      currentFuelPrice = Number(data.price_per_liter) || 65.55;
      document.getElementById('fuelPriceBadge').textContent = 'Diesel: ₱' + currentFuelPrice.toFixed(2) + '/L';
      if(data.suggested_starting_odo !== null && data.suggested_starting_odo !== undefined){
        document.getElementById('newStartingOdo').value = data.suggested_starting_odo;
      }
      renderHistoricalKpl(kplEl, data.historical_kpl);
    } else if(kplEl){
      kplEl.textContent = t('form.kpl_load_failed', 'Could not load - reopen the app');
    }
  } catch(e){
    if(kplEl) kplEl.textContent = t('form.kpl_load_failed', 'Could not load - reopen the app');
  }
}

function onDriverSelected(){
  const busSelect = document.getElementById('newBus');
  const bus = cacheBuses.find(b => String(b.bus_id) === String(busSelect.value));
  const driverSelect = document.getElementById('newDriver');
  updateFuelLinkedBadge(bus, driverSelect.value ? resolveDriverName(driverSelect.value) : '');
}

async function showCreateModal(){
  showModal('createModal');
  const now=getPHTDate();
  document.getElementById('dispatchTimeDisplay').textContent=formatPHT(now);

  // Reset odometer fields
  document.getElementById('newStartingOdo').value = '';
  renderHistoricalKpl(document.getElementById('newHistoricalKpl'), null);
  document.getElementById('newEndingOdo').value = 'Not yet recorded';
  document.getElementById('fuelDispatchBadge').style.display = 'none';
  renderBusQueueInfo(null);

  if(!queueState.entries.length){ await loadQueue({force:true}); }
  // Refresh buses/drivers so the bus's current assignment (assigned_driver_id)
  // is always the option the modal offers.
  await preloadDropdowns();
  await populateDropdowns();
  if(defaultRoute){
    const rs=document.getElementById('newRoute');
    for(let i=0;i<rs.options.length;i++){if(rs.options[i].value===defaultRoute){rs.value=defaultRoute;break;}}
  }
  if(defaultDestTerminalId){
    const ts=document.getElementById('newDestTerminal');
    for(let i=0;i<ts.options.length;i++){if(ts.options[i].value==defaultDestTerminalId){ts.value=defaultDestTerminalId;break;}}
  }

  // Pre-fetch current fuel price for badge
  try {
    const pRes = await fetch(API + 'get_bus_fuel_info?bus_id=' + encodeURIComponent(cacheBuses[0]?.bus_id || 'BUS-01'), {headers: apiHeaders()});
    if(pRes.status === 404 || pRes.status === 410){ recoverStaleApp(); return; }
    const pj = await pRes.json();
    if(pj.success && pj.data && pj.data.price_per_liter){
      currentFuelPrice = Number(pj.data.price_per_liter);
      document.getElementById('fuelPriceBadge').textContent = 'Diesel: ₱' + currentFuelPrice.toFixed(2) + '/L';
    }
  } catch(e){}
}

document.getElementById('newBus')?.addEventListener('change', function(){
  onBusSelected(this.value);
});
document.getElementById('newDriver')?.addEventListener('change', function(){
  onDriverSelected();
});

async function createDispatch(){
  const bus_id=document.getElementById('newBus').value;
  const driver_id=document.getElementById('newDriver').value;
  const route_name=document.getElementById('newRoute').value;
  const to_terminal_id=document.getElementById('newDestTerminal').value;
  const passenger_count=document.getElementById('newPassengerCount').value||0;
  const starting_odo=document.getElementById('newStartingOdo').value;

  if(!bus_id||!driver_id||!route_name){
    showAlert('error', t('alert.incomplete_form','Incomplete Form'), t('alert.incomplete_form_body','Please select bus, driver, and route.'));
    return;
  }
  const startingOdoValue = parseFloat(starting_odo);
  if(!starting_odo || isNaN(startingOdoValue) || startingOdoValue <= 0){
    showAlert('error', t('alert.starting_odo_required_title','Starting Odometer Required'), t('alert.starting_odo_required_body','Enter the bus\'s actual odometer reading before dispatching.'));
    return;
  }
  const entry = findQueueEntry(bus_id);
  if(entry && !isDispatchable(entry)){
    showAlert('error', t('alert.dispatch_blocked','Dispatch Unavailable'), entry.short_reason || t('alert.prepare_required','Complete the required pre-trip items before dispatching this bus.'));
    return;
  }
  if(entry && !isNextInQueue(entry)){
    showAlert('error', t('alert.queue_order_title','Follow the Dispatch Queue'), queueOrderMessage());
    return;
  }
  submitAdhocDispatch({
    bus_id,
    driver_id,
    route_name,
    to_terminal_id,
    passenger_count,
    terminal_id:TERMINAL_ID,
    starting_odo: startingOdoValue,
    queue_position: entry ? entry.queue_position : undefined,
    override_note: ''
  });
}

async function submitAdhocDispatch(payload, overtakeReason){
  if(dispatchInFlight) return;
  dispatchInFlight = true;
  const btn = document.querySelector('#createModal .btn-primary-app');
  if(btn){ btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + esc(t('confirm.dispatching','Dispatching...')); }
  try{
    if(overtakeReason) payload.overtake_reason = overtakeReason;
    const r=await fetch(API+'create_dispatch',{
      method:'POST',
      headers:apiPostHeaders(),
      body:JSON.stringify(payload)
    });
    const j=await parseApiResponse(r);
    if(isResponseSuccess(r, j)){
      closeModal('createModal');
      await loadQueue({force:true});
      const order = j && j.dispatch_order ? (' ' + t('success.order','Actual Dispatch Order') + ': ' + ordinal(j.dispatch_order)) : '';
      showAlert('success', t('alert.bus_dispatched','Bus Dispatched'), t('alert.bus_dispatched_body','Trip created and bus dispatched successfully.') + order);
    } else if(r.status===409 && j && j.code==='queue_overtake_required'){
      openOvertakeModal(
        {bus_id:payload.bus_id, queue_position:j.data.target_position},
        {bus_id:j.data.ready_bus_id, queue_position:j.data.ready_position},
        function(reason){ submitAdhocDispatch(payload, reason); }
      );
    } else {
      const errMsg = extractErrorMessage(r, j, t('alert.dispatch_error','The trip could not be dispatched. The queue may have changed. Please refresh and try again.'));
      showAlert('error', t('alert.dispatch_failed','Dispatch Failed'), errMsg);
    }
  }catch(e){
    console.error('submitAdhocDispatch error:', e);
    showAlert('error', t('alert.dispatch_failed','Dispatch Failed'), t('state.network_error','Network error. Please check your connection and try again.'));
  }finally{
    dispatchInFlight = false;
    if(btn){ btn.disabled = false; btn.innerHTML = '<i class="bi bi-send"></i> ' + esc(t('common.dispatch_bus','Dispatch Bus')); }
  }
}

function onSearch(){
  queueState.search = document.getElementById('searchInput').value.trim();
  renderQueue();
}

initQueue();
