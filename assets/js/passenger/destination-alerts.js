/* Passenger destination alerts.
 * Shares the destination-alert presentation and once-per-state tracking
 * between the logged-in homepage and the one-time receipt page.
 *
 * The server owns the durable once-per-trip claims and reports only newly
 * claimed alerts in the location response. This module keeps an in-memory
 * state machine keyed to the trip id so a trip change resets every alert,
 * and it delivers each alert through the R-Connect banner/voice experience,
 * vibration (best effort), and a browser/PWA notification when permission
 * is already granted (never prompted here).
 */
(function () {
    'use strict';

    var STATES = {
        NOT_STARTED: 'NOT_STARTED',
        MONITORING: 'MONITORING',
        APPROACHING: 'APPROACHING',
        DESTINATION_REACHED: 'DESTINATION_REACHED',
        PASSED_DESTINATION: 'PASSED_DESTINATION'
    };

    var VIBRATION_PATTERNS = {
        near: 120,
        arrival: [200, 80, 200],
        passed: [300, 120, 300, 120, 300]
    };

    var DEFAULT_STRINGS = {
        nearTitle: 'Destination Nearby',
        nearLead: 'Your destination is nearby.',
        nearBody: 'Please prepare to get off at:',
        reachedTitle: 'Destination Reached',
        reachedBody: 'The bus is now near your destination:',
        reachedHint: 'Please prepare to get off.',
        passedTitle: 'Destination May Have Been Passed',
        passedBody: 'Your selected destination was:',
        passedLead: 'The bus may have passed your selected destination.',
        passedHint: 'Please notify the driver if you still need to get off.',
        notifyNear: 'Your destination is nearby.',
        notifyReached: 'You are near your destination.',
        notifyPassed: 'Your destination may have been passed.',
        continueLabel: 'Continue Journey',
        dismissLabel: 'Dismiss',
        unknownDestination: 'Your selected destination'
    };

    var NOTIFICATION_TITLE = 'R-Connect';
    var NOTIFICATION_ICON = '/assets/images/logo/logoR.png';

    var hooks = {
        getTripId: null,
        showBanner: null,
        speak: null,
        onContinueJourney: null,
        debugEnabled: false,
        strings: null
    };

    var state = freshState();

    function freshState() {
        return {
            tripId: null,
            phase: STATES.NOT_STARTED,
            approachingTriggered: false,
            reachedTriggered: false,
            passedTriggered: false,
            lastAlert: null,
            lastFix: null
        };
    }

    function documentRef() {
        return (typeof document !== 'undefined') ? document : null;
    }

    function navigatorRef() {
        return (typeof navigator !== 'undefined') ? navigator : null;
    }

    function mergedStrings() {
        var merged = {};
        var overrides = hooks.strings || {};
        var key;

        for (key in DEFAULT_STRINGS) {
            if (Object.prototype.hasOwnProperty.call(DEFAULT_STRINGS, key)) {
                merged[key] = DEFAULT_STRINGS[key];
            }
        }
        for (key in overrides) {
            if (Object.prototype.hasOwnProperty.call(overrides, key) && overrides[key]) {
                merged[key] = overrides[key];
            }
        }

        return merged;
    }

    function destinationName(alert) {
        var strings = mergedStrings();
        var raw = alert && alert.destination_name;

        if (raw === undefined || raw === null) {
            return strings.unknownDestination;
        }

        var name = String(raw).trim();
        if (name === '' || name === 'undefined' || name === 'null' || name === 'NaN') {
            return strings.unknownDestination;
        }

        return name;
    }

    function tripIdFor(alert) {
        var id = null;

        if (alert && alert.trip_id !== undefined && alert.trip_id !== null) {
            id = Number(alert.trip_id);
        }
        if ((id === null || !isFinite(id) || id <= 0) && typeof hooks.getTripId === 'function') {
            id = Number(hooks.getTripId());
        }

        return (id !== null && isFinite(id) && id > 0) ? id : null;
    }

    function ensureTrip(tripId) {
        if (tripId === null || tripId === state.tripId) {
            return;
        }

        state = freshState();
        state.tripId = tripId;
        state.phase = STATES.MONITORING;
        hidePassedCard();
    }

    function setTrip(tripId) {
        var id = Number(tripId);
        if (id > 0) {
            ensureTrip(id);
        }
    }

    function vibrate(kind) {
        var nav = navigatorRef();
        if (!nav || typeof nav.vibrate !== 'function') {
            return false;
        }

        try {
            return nav.vibrate(VIBRATION_PATTERNS[kind]) !== false;
        } catch (err) {
            return false;
        }
    }

    function notificationBody(kind, name) {
        var strings = mergedStrings();

        if (kind === 'near') {
            return strings.notifyNear + '\n' + name;
        }
        if (kind === 'arrival') {
            return strings.notifyReached + '\n' + name;
        }

        return strings.notifyPassed + '\n' + strings.passedHint;
    }

    function fallbackNotification(title, options) {
        try {
            if (typeof Notification !== 'function') {
                return;
            }
            var shown = new Notification(title, options);
            if (shown && typeof shown.close === 'function') {
                setTimeout(function () {
                    try { shown.close(); } catch (err) {}
                }, 8000);
            }
        } catch (err) {
            // Notifications are best effort; in-app alerts always work.
        }
    }

    function notify(kind, name) {
        try {
            if (typeof Notification === 'undefined' || Notification.permission !== 'granted') {
                return;
            }
        } catch (err) {
            return;
        }

        var title = NOTIFICATION_TITLE;
        var options = {
            body: notificationBody(kind, name),
            tag: 'rconnect-dest-' + (state.tripId !== null ? state.tripId : 'trip') + '-' + kind,
            icon: NOTIFICATION_ICON,
            badge: NOTIFICATION_ICON,
            data: { url: '/passenger/home' }
        };

        var nav = navigatorRef();
        var ready = nav && nav.serviceWorker ? nav.serviceWorker.ready : null;

        if (ready && typeof ready.then === 'function') {
            ready.then(function (registration) {
                if (registration && typeof registration.showNotification === 'function') {
                    try {
                        return registration.showNotification(title, options);
                    } catch (err) {
                        fallbackNotification(title, options);
                        return null;
                    }
                }
                fallbackNotification(title, options);
                return null;
            }).catch(function () {
                fallbackNotification(title, options);
            });
            return;
        }

        fallbackNotification(title, options);
    }

    function speak(kind, name) {
        if (typeof hooks.speak === 'function') {
            try {
                hooks.speak(kind, name);
            } catch (err) {
                // Voice is best effort.
            }
        }
    }

    function createEl(tag, id, styles, text) {
        var doc = documentRef();
        var el = doc.createElement(tag);
        if (id) { el.id = id; }
        if (styles) { el.style.cssText = styles; }
        if (text !== undefined && text !== null) { el.textContent = text; }
        return el;
    }

    function showCard(kind, name) {
        var doc = documentRef();
        var strings = mergedStrings();
        var cardId = 'rconnectDestCard-' + kind;
        var card = doc.getElementById(cardId);

        if (!card) {
            card = createEl('div', cardId, 'position:fixed;left:12px;right:12px;top:12px;z-index:10044;background:#fffdf5;border:1.5px solid #f0dca8;border-radius:14px;padding:12px 14px;box-shadow:0 6px 20px rgba(0,0,0,.16);font-family:Poppins,sans-serif;');
            var row = createEl('div', null, 'display:flex;align-items:flex-start;gap:8px;');
            var body = createEl('div', null, 'min-width:0;flex:1;');
            body.appendChild(createEl('div', null, 'font-size:13px;font-weight:800;color:#2f6b43;', kind === 'arrival' ? strings.reachedTitle : strings.nearTitle));

            if (kind === 'arrival') {
                body.appendChild(createEl('div', null, 'font-size:12px;color:#7b8a74;margin-top:2px;', strings.reachedBody));
                body.appendChild(createEl('div', null, 'font-size:14px;font-weight:700;color:#24513c;margin-top:2px;overflow-wrap:anywhere;', name));
                body.appendChild(createEl('div', null, 'font-size:12px;color:#7b8a74;margin-top:4px;', strings.reachedHint));
            } else {
                body.appendChild(createEl('div', null, 'font-size:12px;color:#7b8a74;margin-top:2px;', strings.nearLead));
                body.appendChild(createEl('div', null, 'font-size:12px;color:#7b8a74;margin-top:2px;', strings.nearBody));
                body.appendChild(createEl('div', null, 'font-size:14px;font-weight:700;color:#24513c;margin-top:2px;overflow-wrap:anywhere;', name));
            }

            row.appendChild(body);
            card.appendChild(row);
            (doc.body || doc.documentElement).appendChild(card);
        }

        card.style.display = 'block';
    }

    function hidePassedCard() {
        var doc = documentRef();
        if (!doc) { return; }
        var card = doc.getElementById('rconnectDestPassedCard');
        if (card) { card.style.display = 'none'; }
    }

    function showPassedCard(name) {
        var doc = documentRef();
        var strings = mergedStrings();
        var card = doc.getElementById('rconnectDestPassedCard');

        if (!card) {
            card = createEl('div', 'rconnectDestPassedCard', 'display:none;position:fixed;left:12px;right:12px;top:12px;z-index:10045;background:#fffdf5;border:1.5px solid #e8c9a0;border-radius:14px;padding:12px 14px;box-shadow:0 6px 20px rgba(0,0,0,.16);font-family:Poppins,sans-serif;');
            card.setAttribute('role', 'alert');

            var row = createEl('div', null, 'display:flex;align-items:flex-start;gap:8px;');
            var marker = createEl('span', null, 'width:22px;height:22px;border-radius:50%;background:#fdecec;color:#a93226;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;flex-shrink:0;', '!');
            var body = createEl('div', null, 'min-width:0;flex:1;');
            body.appendChild(createEl('div', 'rconnectDestPassedTitle', 'font-size:13px;font-weight:800;color:#a93226;', strings.passedTitle));
            body.appendChild(createEl('div', 'rconnectDestPassedBody', 'font-size:12px;color:#7b8a74;margin-top:4px;', strings.passedBody));
            body.appendChild(createEl('div', 'rconnectDestPassedName', 'font-size:14px;font-weight:700;color:#24513c;margin-top:2px;overflow-wrap:anywhere;', name));
            body.appendChild(createEl('div', 'rconnectDestPassedLead', 'font-size:12px;color:#7b8a74;margin-top:6px;', strings.passedLead));
            body.appendChild(createEl('div', 'rconnectDestPassedHint', 'font-size:12px;color:#7b8a74;margin-top:2px;', strings.passedHint));

            var continueBtn = createEl('button', 'rconnectDestPassedContinue', 'margin-top:10px;border:0;border-radius:10px;padding:9px 14px;background:#47613b;color:#ffffff;font-family:Poppins,sans-serif;font-size:12.5px;font-weight:800;cursor:pointer;', strings.continueLabel);
            continueBtn.type = 'button';
            continueBtn.addEventListener('click', function () {
                hidePassedCard();
                if (typeof hooks.onContinueJourney === 'function') {
                    try {
                        hooks.onContinueJourney();
                    } catch (err) {
                        // The existing continuation screen handles its own errors.
                    }
                }
            });
            body.appendChild(continueBtn);

            var dismissBtn = createEl('button', 'rconnectDestPassedDismiss', 'border:0;background:transparent;color:#b09a63;font-size:18px;line-height:1;padding:0 2px;flex-shrink:0;cursor:pointer;', '\u00d7');
            dismissBtn.type = 'button';
            dismissBtn.setAttribute('aria-label', strings.dismissLabel);
            dismissBtn.addEventListener('click', function () {
                hidePassedCard();
            });

            row.appendChild(marker);
            row.appendChild(body);
            row.appendChild(dismissBtn);
            card.appendChild(row);
            (doc.body || doc.documentElement).appendChild(card);
        } else {
            var nameEl = doc.getElementById('rconnectDestPassedName');
            if (nameEl) { nameEl.textContent = name; }
        }

        card.style.display = 'block';
    }

    function present(kind, name) {
        vibrate(kind);
        notify(kind, name);
        speak(kind, name);

        if (kind === 'passed') {
            showPassedCard(name);
            return;
        }

        if (typeof hooks.showBanner === 'function') {
            try {
                hooks.showBanner(kind, name);
                return;
            } catch (err) {
                // Fall through to the built-in card.
            }
        }

        showCard(kind, name);
    }

    function debugEnabled() {
        if (hooks.debugEnabled === true) {
            return true;
        }

        try {
            if (typeof window !== 'undefined' && window.location &&
                /(?:^|[?&])dest_debug=1(?:&|$)/.test(window.location.search || '')) {
                return true;
            }
            if (typeof localStorage !== 'undefined' && localStorage.getItem('rconnect_dest_debug') === '1') {
                return true;
            }
        } catch (err) {
            // Storage can be unavailable; the flag stays off.
        }

        return false;
    }

    function debugRow(label, value) {
        var doc = documentRef();
        var row = createEl('div', null, 'display:flex;justify-content:space-between;gap:10px;font-size:10.5px;color:#24513c;');
        row.appendChild(createEl('span', null, 'color:#7b8a74;font-weight:700;', label));
        row.appendChild(createEl('span', null, 'font-weight:700;overflow-wrap:anywhere;text-align:right;', value));
        return row;
    }

    function updateDebug(alert) {
        var doc = documentRef();
        if (!doc || !debugEnabled()) {
            return;
        }

        var panel = doc.getElementById('rconnectDestDebug');
        if (!panel) {
            panel = createEl('div', 'rconnectDestDebug', 'position:fixed;left:12px;bottom:12px;z-index:10060;background:rgba(20,30,18,.9);color:#eef4e6;border-radius:10px;padding:8px 10px;font-family:monospace;font-size:10.5px;line-height:1.5;max-width:88vw;box-shadow:0 4px 14px rgba(0,0,0,.3);');
            (doc.body || doc.documentElement).appendChild(panel);
        }

        panel.textContent = '';
        var fix = state.lastFix || {};
        var distance = alert && alert.distance_m !== undefined && alert.distance_m !== null ? alert.distance_m + ' m' : '—';
        var previous = alert && alert.previous_distance_m !== undefined && alert.previous_distance_m !== null ? alert.previous_distance_m + ' m' : '—';
        var destinationGps = alert && alert.destination_lat !== undefined && alert.destination_lat !== null
            ? alert.destination_lat + ', ' + alert.destination_lng
            : '—';
        var routeProgress = alert && alert.route_progress_m !== undefined && alert.route_progress_m !== null ? alert.route_progress_m + ' m' : '—';
        var direction = alert && alert.direction ? alert.direction : (alert && alert.direction_source ? alert.direction_source : '—');
        var gpsSource = (fix && fix.source) ? fix.source : (alert && alert.gps_source ? alert.gps_source : 'passenger');
        var busAge = alert && alert.bus_age_seconds !== undefined && alert.bus_age_seconds !== null ? alert.bus_age_seconds + ' s' : '—';
        var driverGps = alert && alert.driver_lat !== undefined && alert.driver_lat !== null
            ? alert.driver_lat + ', ' + alert.driver_lng
            : '—';
        var driverAge = alert && alert.driver_age_seconds !== undefined && alert.driver_age_seconds !== null
            ? alert.driver_age_seconds + ' s'
            : '—';
        var driverUpdated = alert && alert.driver_updated_at ? alert.driver_updated_at : '—';
        var driverCreated = alert && alert.driver_created_at ? alert.driver_created_at : driverUpdated;
        var freshness = alert && alert.gps_freshness ? alert.gps_freshness : '—';
        // alert.gps_source is the authoritative table the server actually read,
        // so it wins over the client's coarse "driver"/"passenger" label.
        var gpsSource = (alert && alert.gps_source) ? alert.gps_source : (fix && fix.source ? fix.source : 'passenger');
        var stateLabel = alert && alert.state
            ? (alert.state + (alert.message ? ' — ' + alert.message : ''))
            : '—';
        var alertReason = alert && alert.destination_alert_reason ? alert.destination_alert_reason : null;
        var trail = alert && alert.trail_distances_m && alert.trail_distances_m.length
            ? alert.trail_distances_m.join(' → ') + ' m'
            : '—';
        var timeBasis = alert && alert.driver_time_basis ? alert.driver_time_basis : '—';
        var coordsSource = alert && alert.destination_coordinates_source
            ? alert.destination_coordinates_source
            : '—';

        // Why the reported record is (or is not) the newest one. Rendered
        // because "the newest reading was not used" is otherwise invisible.
        function historyProbe() {
            var rows = alert && alert.driver_history_probe;
            if (!rows || !rows.length) {
                return '—';
            }

            return rows.map(function (row) {
                var bits = ['#' + (row.id === null || row.id === undefined ? '?' : row.id), row.status || '?'];
                if (row.created_at) {
                    bits.push(row.created_at);
                }
                if (row.age_seconds !== null && row.age_seconds !== undefined) {
                    bits.push(row.age_seconds + 's/' + (row.time_basis || '?'));
                }
                return bits.join(' ');
            }).join('\n');
        }

        panel.appendChild(debugRow('Trip ID', state.tripId !== null ? String(state.tripId) : '—'));
        panel.appendChild(debugRow('Trip no', alert && alert.trip_no ? alert.trip_no : '—'));
        panel.appendChild(debugRow('Driver', alert && alert.driver_id ? alert.driver_id : '—'));
        panel.appendChild(debugRow('Bus', alert && alert.bus_id ? alert.bus_id : '—'));
        panel.appendChild(debugRow('Bus match', alert && alert.bus_match ? alert.bus_match : '—'));
        panel.appendChild(debugRow('Route', alert && alert.route ? alert.route : '—'));
        panel.appendChild(debugRow('Direction', direction));
        panel.appendChild(debugRow('Destination', alert ? destinationName(alert) : '—'));
        panel.appendChild(debugRow('Destination id', alert && alert.destination_id ? alert.destination_id : '—'));
        panel.appendChild(debugRow('Destination GPS', destinationGps));
        panel.appendChild(debugRow('Destination source', alert && alert.destination_source ? alert.destination_source : '—'));
        panel.appendChild(debugRow('Destination coordinates', coordsSource));
        panel.appendChild(debugRow('GPS source', gpsSource));
        panel.appendChild(debugRow('Position record', alert && alert.driver_location_history_id
            ? ('#' + alert.driver_location_history_id + ' via ' + (alert.driver_timestamp_column || '—'))
            : '—'));
        panel.appendChild(debugRow('Current GPS', fix.latitude !== undefined && fix.latitude !== null ? fix.latitude + ', ' + fix.longitude : '—'));
        panel.appendChild(debugRow('Driver GPS', driverGps));
        panel.appendChild(debugRow('Driver record time', driverCreated));
        panel.appendChild(debugRow('Driver age', driverAge));
        panel.appendChild(debugRow('Clock basis', timeBasis));
        panel.appendChild(debugRow('Freshness', freshness));
        panel.appendChild(debugRow('Bus GPS age', busAge));
        panel.appendChild(debugRow('Newest readings', historyProbe()));
        panel.appendChild(debugRow('GPS accuracy', fix.accuracy !== undefined && fix.accuracy !== null ? fix.accuracy + ' m' : '—'));
        panel.appendChild(debugRow('Distance', distance));
        panel.appendChild(debugRow('Previous distance', previous));
        panel.appendChild(debugRow('Trail distances', trail));
        panel.appendChild(debugRow('Nearest route segment', alert && alert.nearest_segment ? alert.nearest_segment : '—'));
        panel.appendChild(debugRow('Route progress', routeProgress));
        panel.appendChild(debugRow('Route position', alert && alert.route_state ? alert.route_state : '—'));
        panel.appendChild(debugRow('Reason', alertReason || '—'));
        panel.appendChild(debugRow('Result', stateLabel));
        panel.appendChild(debugRow('Alert state', state.phase));
        panel.appendChild(debugRow('Approaching triggered', state.approachingTriggered ? 'yes' : 'no'));
        panel.appendChild(debugRow('Reached triggered', state.reachedTriggered ? 'yes' : 'no'));
        panel.appendChild(debugRow('Passed triggered', state.passedTriggered ? 'yes' : 'no'));
    }

    function handleAlert(alert, fix) {
        if (fix && typeof fix === 'object') {
            state.lastFix = fix;
        }

        if (!alert || typeof alert !== 'object') {
            updateDebug(null);
            return null;
        }

        ensureTrip(tripIdFor(alert));

        if (state.phase === STATES.NOT_STARTED && state.tripId !== null) {
            state.phase = STATES.MONITORING;
        }

        state.lastAlert = alert;

        // The server may answer with a structured result that explains a
        // blocked or monitoring decision (for example blocked_stale_location).
        // Those states are diagnostic only: never present them as an alert.
        var structured = typeof alert.state === 'string' && alert.state !== '';
        var blocked = structured && alert.state.indexOf('blocked_') === 0;
        var monitoring = structured && alert.state === 'monitoring';
        var canPresent = !blocked && !monitoring;
        if (structured && alert.triggered === false) {
            canPresent = false;
        }

        var processed = { near: false, arrival: false, passed: false };
        var name = destinationName(alert);

        if (canPresent && alert.near && !state.approachingTriggered) {
            state.approachingTriggered = true;
            state.phase = STATES.APPROACHING;
            present('near', name);
            processed.near = true;
        }

        if (canPresent && alert.arrival && !state.reachedTriggered) {
            state.reachedTriggered = true;
            state.phase = STATES.DESTINATION_REACHED;
            present('arrival', name);
            processed.arrival = true;
        }

        if (canPresent && alert.passed && !state.passedTriggered) {
            state.passedTriggered = true;
            state.phase = STATES.PASSED_DESTINATION;
            present('passed', name);
            processed.passed = true;
        }

        updateDebug(alert);

        return processed;
    }

    function configure(options) {
        if (!options || typeof options !== 'object') {
            return window.RConnectDestinationAlerts;
        }

        if (typeof options.getTripId === 'function') { hooks.getTripId = options.getTripId; }
        if (typeof options.showBanner === 'function') { hooks.showBanner = options.showBanner; }
        if (typeof options.speak === 'function') { hooks.speak = options.speak; }
        if (typeof options.onContinueJourney === 'function') { hooks.onContinueJourney = options.onContinueJourney; }
        if (options.debugEnabled === true) { hooks.debugEnabled = true; }
        if (options.strings && typeof options.strings === 'object') { hooks.strings = options.strings; }

        if (typeof options.tripId !== 'undefined' && options.tripId !== null) {
            setTrip(options.tripId);
        }

        return window.RConnectDestinationAlerts;
    }

    function getState() {
        return {
            tripId: state.tripId,
            phase: state.phase,
            approachingTriggered: state.approachingTriggered,
            reachedTriggered: state.reachedTriggered,
            passedTriggered: state.passedTriggered
        };
    }

    function reset() {
        state = freshState();
        hidePassedCard();
    }

    if (typeof window !== 'undefined') {
        window.RConnectDestinationAlerts = {
            STATES: STATES,
            configure: configure,
            handleAlert: handleAlert,
            setTrip: setTrip,
            getState: getState,
            reset: reset
        };
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = {
            STATES: STATES,
            configure: configure,
            handleAlert: handleAlert,
            setTrip: setTrip,
            getState: getState,
            reset: reset
        };
    }
})();
