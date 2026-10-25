(function () {
    'use strict';

    var CFG = window.DQ_CONFIG || {};

    var state = {
        date: CFG.today || '',
        payload: null,
        search: '',
        terminal: '',
        dirty: false,
        busy: false,
        assignBusId: null,
        overrideBusId: null,
        availabilityBusId: null,
        terminalBusId: null,
        dragBusId: null,
        pendingSecurity: null
    };

    var SECURITY_URL = '/admin/api/RoleManagement/Security.php';
    var HASH_SKIP = ['security_token', 'action', 'verify_action', '_csrf', '_csrf_token', 'code', 'current_code', 'new_code', 'confirm_code'];

    var AVAILABILITY_STATUSES = ['Available', 'Under Maintenance', 'Out of Service'];

    function el(id) { return document.getElementById(id); }

    function esc(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function displayValue(value, fallback) {
        var raw = String(value === null || value === undefined ? '' : value).trim();
        if (raw === '' || raw === 'Unassigned' || raw === 'TBD') {
            return '<span class="rmg-missing">' + esc(fallback || 'Not recorded') + '</span>';
        }
        return esc(raw);
    }

    function dqNotify(kind, title, text) {
        if (typeof window.showAlert === 'function') {
            window.showAlert(kind === 'success' ? 'success' : 'error', title || '', text || '');
            return;
        }
        console.error('Dispatch queue alert component unavailable:', title, text);
    }

    function fmtDisplayDate(value) {
        var raw = String(value || '').trim();
        var parts = raw.split('-');
        if (parts.length !== 3) return raw || '—';
        var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        var month = parseInt(parts[1], 10);
        if (!month || month > 12) return raw;
        return months[month - 1] + ' ' + parseInt(parts[2], 10) + ', ' + parts[0];
    }

    var PUBLISH_BUTTON_HTML = '<i class="bi bi-megaphone"></i> Publish queue';

    /* A past operating date is read-only history: it can be viewed but never
       changed or published. */
    function isHistory() {
        return String(state.date || '') !== '' && String(state.date) < String(CFG.today || '');
    }

    /* Once an operating date is published its official order is locked for that
       date: it can no longer be edited or published again. Other operating dates
       carry their own state, so this never locks them. */
    function isPublished() {
        return !!(state.payload && state.payload.published);
    }

    function isLocked() {
        return isHistory() || isPublished();
    }

    /* --------------------------------- requests --------------------------------- */

    function apiGet(url) {
        return fetch(url, {credentials: 'same-origin', cache: 'no-store'}).then(function (response) {
            return response.json().then(function (json) {
                if (!json || json.success !== true) {
                    var error = new Error((json && json.message) || 'The request failed.');
                    error.payload = json || null;
                    throw error;
                }
                return json;
            });
        });
    }

    function apiPost(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(body)
        }).then(function (response) {
            return response.json().catch(function () {
                throw new Error('The server returned an unexpected response.');
            }).then(function (json) {
                if (!json || json.success !== true) {
                    var error = new Error((json && json.message) || 'The request failed.');
                    error.payload = json || null;
                    throw error;
                }
                return json;
            });
        });
    }

    /* ---------------------------------- load ---------------------------------- */

    function load() {
        if (state.busy) return;
        state.busy = true;
        renderHistoryMode();
        renderSkeleton();

        apiGet('/admin/api/Dispatch/queue.php?date=' + encodeURIComponent(state.date)).then(function (res) {
            state.payload = res.data;
            state.dirty = false;
            render();
        }).catch(function (error) {
            el('dqList').innerHTML = '<div class="rmg-state error"><i class="bi bi-exclamation-circle"></i><h6>Dispatch queue could not be loaded</h6>' + esc(error.message || 'Please try again.') + '</div>';
            dqNotify('error', 'Load failed', error.message || 'Please try again.');
        }).finally(function () {
            state.busy = false;
        });
    }

    /* --------------------------------- summary --------------------------------- */

    var SUMMARY_DEFS = [
        {key: 'total', label: 'Valid Buses', icon: 'bi-bus-front', tone: 'total'},
        {key: 'queued', label: 'In Queue', icon: 'bi-list-ol', tone: 'active'},
        {key: 'assigned', label: 'Drivers Assigned', icon: 'bi-person-check', tone: 'available'},
        {key: 'on_trip', label: 'On Trip', icon: 'bi-signpost-split', tone: 'pending'},
        {key: 'maintenance', label: 'Unavailable', icon: 'bi-wrench-adjustable', tone: 'inactive'}
    ];

    function renderSummary() {
        var host = el('dqSummary');
        if (!host) return;
        var summary = (state.payload && state.payload.summary) || {};
        host.innerHTML = SUMMARY_DEFS.map(function (def) {
            var value = summary[def.key] !== undefined ? summary[def.key] : '—';
            return '<div class="rmg-sum-card tone-' + def.tone + '">' +
                '<span class="rmg-sum-icon"><i class="bi ' + def.icon + '"></i></span>' +
                '<div class="rmg-sum-body"><div class="rmg-sum-label">' + esc(def.label) + '</div>' +
                '<div class="rmg-sum-value">' + value + '</div></div></div>';
        }).join('');
    }

    function renderStateChip() {
        var chip = el('dqStateChip');
        if (!chip) return;
        var payload = state.payload;
        if (!payload) {
            chip.hidden = true;
            return;
        }
        chip.hidden = false;
        if (state.dirty) {
            chip.className = 'dq-chip is-dirty';
            chip.innerHTML = '<i class="bi bi-pencil-square"></i> Unsaved changes';
            return;
        }
        if (payload.published) {
            chip.className = 'dq-chip is-published';
            chip.innerHTML = '<i class="bi bi-patch-check"></i> Published ' + esc(payload.published.at) +
                (payload.published.by ? ' by ' + esc(payload.published.by) : '');
            return;
        }
        if (isHistory()) {
            chip.className = 'dq-chip is-history';
            chip.innerHTML = '<i class="bi bi-clock-history"></i> History - read-only';
            return;
        }
        chip.className = 'dq-chip is-draft';
        chip.innerHTML = '<i class="bi bi-pencil"></i> Draft - not published';
    }

    function renderHistoryMode() {
        var history = isHistory();
        var published = isPublished();
        var locked = history || published;
        var section = el('dispatchQueueSection');
        if (section && section.classList && section.classList.toggle) {
            section.classList.toggle('is-history', history);
            section.classList.toggle('is-published-lock', published && !history);
        }

        var dateLabel = esc(fmtDisplayDate(state.date));
        var status = el('dqDateStatus');
        if (status) {
            status.className = 'dq-date-status' + (locked ? ' is-locked' : '');
            if (history) {
                status.innerHTML = '<i class="bi bi-clock-history"></i> History - ' + dateLabel + ' - read-only';
            } else if (published) {
                status.innerHTML = '<i class="bi bi-lock-fill"></i> Published - ' + dateLabel + ' - locked';
            } else if (String(state.date) === String(CFG.today || '')) {
                status.innerHTML = '<i class="bi bi-calendar-check"></i> Today - ' + dateLabel;
            } else {
                status.innerHTML = '<i class="bi bi-calendar-plus"></i> Scheduled - ' + dateLabel;
            }
        }

        var notice = el('dqHistoryNotice');
        if (notice) {
            notice.hidden = !locked;
            if (history) {
                notice.innerHTML = '<i class="bi bi-clock-history"></i> Showing history for ' + dateLabel +
                    '. Past queues are read-only and cannot be published - pick today to make changes.';
            } else if (published) {
                notice.innerHTML = '<i class="bi bi-lock-fill"></i> The official dispatch order for ' + dateLabel +
                    ' has been published and is locked. It can no longer be edited or re-published - choose another operating date to make changes.';
            } else {
                notice.innerHTML = '';
            }
        }

        var publishBtn = el('dqPublishBtn');
        if (publishBtn) {
            if (history) {
                publishBtn.disabled = true;
                publishBtn.title = 'This operating date is in the past - its queue is read-only and cannot be published.';
                publishBtn.innerHTML = '<i class="bi bi-clock-history"></i> History date - cannot publish';
            } else if (published) {
                publishBtn.disabled = true;
                publishBtn.title = 'This operating date is already published - its official order is locked and cannot be published again.';
                publishBtn.innerHTML = '<i class="bi bi-lock-fill"></i> Published - locked';
            } else {
                publishBtn.disabled = false;
                publishBtn.title = '';
                publishBtn.innerHTML = PUBLISH_BUTTON_HTML;
            }
        }

        var saveBtn = el('dqSaveBtn');
        if (saveBtn) {
            if (locked) {
                saveBtn.disabled = true;
                saveBtn.title = history
                    ? 'This operating date is in the past - its queue is read-only.'
                    : 'This operating date is published - its official order is locked.';
            } else {
                saveBtn.disabled = false;
                saveBtn.title = '';
            }
        }
    }

    /* ---------------------------------- list ---------------------------------- */

    function renderSkeleton() {
        var host = el('dqList');
        if (!host) return;
        var rows = '';
        for (var i = 0; i < 6; i++) {
            rows += '<div class="rmg-skel-row">' +
                '<span class="rmg-skel rmg-skel-avatar"></span>' +
                '<span class="rmg-skel rmg-skel-line"></span>' +
                '<span class="rmg-skel rmg-skel-line short"></span>' +
                '<span class="rmg-skel rmg-skel-chip"></span></div>';
        }
        host.innerHTML = '<div class="rmg-skel-wrap" aria-busy="true" aria-label="Loading dispatch queue">' + rows + '</div>';
    }

    function matchesSearch(item) {
        if (!state.search) return true;
        var haystack = [
            item.bus_id, item.plate_number, item.bus_type,
            item.route_name, item.route,
            item.origin_terminal, item.destination_terminal, item.current_location,
            item.effective_terminal, item.terminal, item.dispatch_origin_terminal, item.receiving_terminal,
            item.assigned_driver && item.assigned_driver.full_name,
            item.assigned_driver && item.assigned_driver.driver_id,
            item.assigned_driver && item.assigned_driver.current_terminal
        ].join(' ').toLowerCase();
        return haystack.indexOf(state.search.toLowerCase()) !== -1;
    }

    /* The terminal a bus is organized under: where it currently is (active trip
       origin, confirmed arrival, or its operating-date assignment). */
    function effectiveTerminalId(item) {
        var value = item.effective_terminal_id;
        if (value === undefined || value === null || value === '') value = item.current_terminal_id;
        if (value === undefined || value === null || value === '') value = item.dispatch_origin_terminal_id;
        if (value === undefined || value === null || value === '') value = item.origin_terminal_id;
        return value === undefined || value === null || value === '' ? '' : String(value);
    }

    function matchesTerminal(item) {
        if (state.terminal === '') return true;
        return effectiveTerminalId(item) === String(state.terminal);
    }

    function driverCellHtml(item) {
        var driver = item.assigned_driver || {};
        var terminalLine = driver.current_terminal
            ? '<span class="dq-driver-terminal"><i class="bi bi-geo-alt"></i> ' + esc(driver.current_terminal) + '</span>'
            : '';
        if (!driver.driver_id) {
            return '<span class="rmg-missing">Unassigned</span>';
        }
        var name = esc(driver.full_name || driver.driver_id);
        var meta = '<span class="dq-driver-id">' + esc(driver.driver_id) + '</span>';
        if (!driver.eligible) {
            return '<div class="dq-driver-blocked" title="' + esc(driver.reason || '') + '">' + name + meta + terminalLine +
                '<div class="dq-warn"><i class="bi bi-exclamation-triangle"></i> ' + esc(driver.reason || 'Driver unavailable') + '</div></div>';
        }
        return name + meta + terminalLine;
    }

    function terminalCellHtml(item) {
        var current = String(item.effective_terminal || '').trim();
        var origin = String(item.dispatch_origin_terminal || '').trim();
        var receiving = String(item.receiving_terminal || '').trim();
        if (current === '' && origin === '' && receiving === '') {
            return '<span class="rmg-missing">No terminal</span>';
        }
        var html = '<div class="dq-terminal-cell">' +
            '<div class="dq-terminal-current">' + (current !== '' ? esc(current) : '<span class="rmg-missing">Unknown</span>') + '</div>';
        if (origin !== '' || receiving !== '') {
            html += '<div class="dq-terminal-route">' + esc(origin !== '' ? origin : '—') + ' \u2192 ' + esc(receiving !== '' ? receiving : '—') + '</div>';
        }
        if (item.terminal && String(item.terminal) !== current) {
            html += '<div class="dq-terminal-assigned">Assigned: ' + esc(item.terminal) + '</div>';
        }
        return html + '</div>';
    }

    function availabilityCellHtml(item) {
        if (!item.eligible) {
            return '<span class="rmg-status is-inactive">Unavailable</span>' +
                '<div class="dq-warn"><i class="bi bi-exclamation-triangle"></i> ' + esc(item.eligibility_reason || '') + '</div>';
        }
        var operational = String(item.operational_status || '').trim();
        if (operational !== '' && operational.toLowerCase() !== 'available') {
            return '<span class="rmg-status is-other">' + esc(operational) + '</span>';
        }
        return '<span class="rmg-status is-active">Ready</span>';
    }

    function tripCellHtml(item) {
        if (item.active_trip) {
            var number = item.active_trip.trip_number ? 'Trip #' + esc(item.active_trip.trip_number) + ' · ' : '';
            return '<span class="rmg-status is-pending">On Trip</span><div class="dq-trip-meta">' + number + esc(item.active_trip.status || '') + '</div>';
        }
        if (item.trips_today > 0) {
            return '<span class="rmg-status is-other">' + esc(item.trip_status) + '</span>';
        }
        return '<span class="rmg-missing">Idle</span>';
    }

    function rowHtml(item, position) {
        var bus = esc(item.bus_id);
        var locked = isLocked();
        var lockAttr = locked ? ' disabled' : '';
        var lockNote = isHistory() ? 'Read-only history date' : 'This operating date is published and locked';
        var title = function (base) { return locked ? lockNote : base; };
        var actions = '<div class="rmg-actions">' +
            '<button type="button" class="rmg-icon-btn is-edit" data-action="assign" data-bus="' + bus + '" title="' + esc(title('Assign driver')) + '"' + lockAttr + ' aria-label="Assign driver to ' + bus + '"><i class="bi bi-person-plus"></i></button>' +
            '<button type="button" class="rmg-icon-btn is-edit" data-action="terminal" data-bus="' + bus + '" title="' + esc(title('Assign terminal')) + '"' + lockAttr + ' aria-label="Assign terminal for ' + bus + '"><i class="bi bi-geo-alt"></i></button>' +
            '<button type="button" class="rmg-icon-btn is-view" data-action="override" data-bus="' + bus + '" title="' + esc(title('Set queue position')) + '"' + lockAttr + ' aria-label="Set queue position for ' + bus + '"><i class="bi bi-arrows-move"></i></button>' +
            '<button type="button" class="rmg-icon-btn is-archive" data-action="availability" data-bus="' + bus + '" title="' + esc(title('Set availability')) + '"' + lockAttr + ' aria-label="Set availability for ' + bus + '"><i class="bi bi-slash-circle"></i></button>' +
            '</div>';

        return '<tr' + (locked ? '' : ' draggable="true"') + ' data-entry="1" data-bus="' + bus + '" data-index="' + position + '">' +
            '<td data-label="#" class="dq-pos"><span class="dq-grip" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></span><strong>#' + position + '</strong></td>' +
            '<td data-label="Bus"><div class="rmg-person"><div class="rmg-avatar"><i class="bi bi-bus-front"></i></div>' +
            '<div style="min-width:0;"><div class="rmg-person-name">' + esc(item.bus_id) + '</div>' +
            '<div class="rmg-person-sub">' + displayValue(item.plate_number, 'No plate') + (item.route_name ? ' · ' + esc(item.route_name) : '') + '</div></div></div></td>' +
            '<td data-label="Type">' + displayValue(item.bus_type) + '</td>' +
            '<td data-label="Driver">' + driverCellHtml(item) + '</td>' +
            '<td data-label="Terminal">' + terminalCellHtml(item) + '</td>' +
            '<td data-label="Availability">' + availabilityCellHtml(item) + '</td>' +
            '<td data-label="Trip">' + tripCellHtml(item) + '</td>' +
            '<td data-label="Actions" class="rmg-td-actions">' + actions + '</td>' +
            '</tr>';
    }

    function unavailableHtml(items) {
        if (!items.length) return '';
        var locked = isLocked();
        var rows = items.map(function (item) {
            var lifecycle = String(item.status || '').toLowerCase();
            var action = (lifecycle === 'archived' || lifecycle === 'inactive')
                ? ''
                : '<button type="button" class="rmg-btn rmg-btn-sm" data-action="availability" data-bus="' + esc(item.bus_id) + '"' + (locked ? ' disabled' : '') + '><i class="bi bi-slash-circle"></i> Availability</button>';
            return '<div class="dq-unavailable-row">' +
                '<span class="dq-unavailable-bus"><i class="bi bi-bus-front"></i> ' + esc(item.bus_id) + '</span>' +
                '<span class="dq-unavailable-plate">' + displayValue(item.plate_number, 'No plate') + '</span>' +
                '<span class="dq-warn"><i class="bi bi-exclamation-triangle"></i> ' + esc(item.eligibility_reason || 'Unavailable') + '</span>' +
                action +
                '</div>';
        }).join('');
        return '<div class="dq-unavailable">' +
            '<div class="dq-unavailable-head"><i class="bi bi-exclamation-triangle"></i> Unavailable buses (' + items.length + ') - excluded from the queue and dispatch</div>' +
            '<div class="dq-unavailable-list">' + rows + '</div>' +
            '</div>';
    }

    function render() {
        renderSummary();
        renderStateChip();
        renderHistoryMode();
        var payload = state.payload;
        var host = el('dqList');
        if (!payload || !host) return;

        var entries = payload.entries || [];
        var unavailable = payload.unavailable || [];
        var visibleEntries = entries.filter(matchesSearch).filter(matchesTerminal);
        el('dqCount').innerHTML = '<i class="bi bi-list-ol"></i> ' + entries.length + ' queued / ' + payload.summary.total + ' valid buses';

        var terminalSelect = el('dqTerminal');
        if (terminalSelect) {
            var currentTerminal = state.terminal;
            var terminalOptions = ['<option value="">All terminals</option>'];
            (payload.terminals || []).forEach(function (terminal) {
                terminalOptions.push('<option value="' + esc(String(terminal.id)) + '">' + esc(terminal.name) + '</option>');
            });
            terminalSelect.innerHTML = terminalOptions.join('');
            var stillThere = (payload.terminals || []).some(function (terminal) {
                return String(terminal.id) === String(currentTerminal);
            });
            terminalSelect.value = stillThere ? currentTerminal : '';
            if (!stillThere) state.terminal = '';
        }

        var tableHtml = '';
        if (visibleEntries.length) {
            var rows = visibleEntries.map(function (item) {
                var pos = item.queue_position !== undefined && item.queue_position !== null ? item.queue_position : (entries.indexOf(item) + 1);
                return rowHtml(item, pos);
            }).join('');
            var colgroup = '<colgroup>' +
                '<col class="dq-col-pos"><col class="dq-col-bus"><col class="dq-col-type"><col class="dq-col-driver">' +
                '<col class="dq-col-terminal"><col class="dq-col-availability"><col class="dq-col-trip"><col class="dq-col-actions">' +
                '</colgroup>';
            tableHtml = '<div class="rmg-table-wrap"><table class="rmg-table">' + colgroup + '<thead><tr>' +
                '<th>#</th><th>Bus</th><th>Type</th><th>Driver</th><th>Terminal</th><th>Availability</th><th>Trip Status</th><th class="rmg-th-actions">Actions</th>' +
                '</tr></thead><tbody id="dqQueueBody">' + rows + '</tbody></table></div>';
        } else if (entries.length) {
            tableHtml = '<div class="rmg-state"><i class="bi bi-inbox"></i><h6>No buses match the search</h6>Clear the search to see the queue.</div>';
        } else {
            tableHtml = '<div class="rmg-state"><i class="bi bi-inbox"></i><h6>No dispatchable buses</h6>Register active BUS- buses first.</div>';
        }

        host.innerHTML = tableHtml + unavailableHtml(unavailable);
    }

    /* ------------------------------ drag and drop ------------------------------ */

    function dqEntryIndex(busId) {
        var entries = (state.payload && state.payload.entries) || [];
        for (var i = 0; i < entries.length; i++) {
            if (entries[i].bus_id === busId) return i;
        }
        return -1;
    }

    function moveEntryTo(dragBusId, targetBusId) {
        var entries = state.payload.entries;
        var from = dqEntryIndex(dragBusId);
        var to = dqEntryIndex(targetBusId);
        if (from < 0 || to < 0 || from === to) return;
        var moved = entries.splice(from, 1)[0];
        entries.splice(to, 0, moved);
        entries.forEach(function (entry, index) {
            entry.queue_position = index + 1;
        });
        state.dirty = true;
        render();
    }

    function rowFromEvent(event, selector) {
        if (!event || !event.target || typeof event.target.closest !== 'function') return null;
        return event.target.closest(selector);
    }

    function bindDragAndDrop() {
        var host = el('dqList');
        if (!host) return;

        host.addEventListener('dragstart', function (event) {
            var row = rowFromEvent(event, '[data-entry]');
            if (!row) return;
            if (isLocked()) {
                if (event.preventDefault) event.preventDefault();
                return;
            }
            state.dragBusId = row.getAttribute('data-bus') || '';
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                try { event.dataTransfer.setData('text/plain', state.dragBusId); } catch (e) {}
            }
            if (row.classList && row.classList.add) row.classList.add('dq-dragging');
        });

        host.addEventListener('dragover', function (event) {
            if (!state.dragBusId) return;
            var row = rowFromEvent(event, '[data-entry]');
            if (!row) return;
            if (event.preventDefault) event.preventDefault();
            if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
            if (row.classList && row.classList.add) row.classList.add('dq-drop-target');
        });

        host.addEventListener('drop', function (event) {
            var row = rowFromEvent(event, '[data-entry]');
            if (!row || !state.dragBusId) return;
            if (isLocked()) return;
            if (event.preventDefault) event.preventDefault();
            var targetBusId = row.getAttribute('data-bus') || '';
            var dragBusId = state.dragBusId;
            state.dragBusId = null;
            moveEntryTo(dragBusId, targetBusId);
        });

        host.addEventListener('dragend', function () {
            state.dragBusId = null;
            if (host.querySelectorAll) {
                Array.prototype.forEach.call(host.querySelectorAll('.dq-dragging, .dq-drop-target'), function (node) {
                    if (node.classList && node.classList.remove) node.classList.remove('dq-dragging');
                    if (node.classList && node.classList.remove) node.classList.remove('dq-drop-target');
                });
            }
        });
    }

    function clearFilters() {
        state.search = '';
        state.terminal = '';
        el('dqSearch').value = '';
        if (el('dqTerminal')) el('dqTerminal').value = '';
        render();
    }

    /* --------------------------- server writes --------------------------- */

    /* ------------------------- security authorization ------------------------- */

    function canonicalPayload(entries) {
        var clean = [];
        (entries || []).forEach(function (pair) {
            var key = String(pair[0]).toLowerCase().trim();
            if (!key || HASH_SKIP.indexOf(key) !== -1) return;
            var value = pair[1];
            if (value === null || value === undefined) value = '';
            else if (typeof value === 'boolean') value = value ? '1' : '0';
            else if (typeof value === 'object') return;
            else value = String(value);
            clean.push([key, value.trim()]);
        });
        clean.sort(function (a, b) { return a[0] < b[0] ? -1 : (a[0] > b[0] ? 1 : 0); });
        return clean.map(function (pair) { return pair[0] + '=' + pair[1]; }).join('&');
    }

    function utf8BinaryString(text) {
        if (typeof TextEncoder !== 'undefined') {
            var bytes = new TextEncoder().encode(text);
            var out = '';
            for (var i = 0; i < bytes.length; i++) out += String.fromCharCode(bytes[i]);
            return out;
        }
        return unescape(encodeURIComponent(text));
    }

    function sha256HexAscii(ascii) {
        function rightRotate(value, amount) { return (value >>> amount) | (value << (32 - amount)); }
        var mathPow = Math.pow;
        var maxWord = mathPow(2, 32);
        var result = '';
        var words = [];
        var asciiBitLength = ascii.length * 8;
        var hash = sha256HexAscii.h = sha256HexAscii.h || [];
        var k = sha256HexAscii.k = sha256HexAscii.k || [];
        var primeCounter = k.length;
        var isComposite = {};
        for (var candidate = 2; primeCounter < 64; candidate++) {
            if (!isComposite[candidate]) {
                for (var c = 0; c < 313; c += candidate) isComposite[c] = candidate;
                hash[primeCounter] = (mathPow(candidate, 0.5) * maxWord) | 0;
                k[primeCounter++] = (mathPow(candidate, 1 / 3) * maxWord) | 0;
            }
        }
        var i, j;
        ascii += '\x80';
        while (ascii.length % 64 - 56) ascii += '\x00';
        for (i = 0; i < ascii.length; i++) {
            j = ascii.charCodeAt(i);
            words[i >> 2] |= j << ((3 - i) % 4) * 8;
        }
        words[words.length] = (asciiBitLength / maxWord) | 0;
        words[words.length] = asciiBitLength;
        for (j = 0; j < words.length;) {
            var w = words.slice(j, j += 16);
            var oldHash = hash;
            hash = hash.slice(0, 8);
            for (i = 0; i < 64; i++) {
                var w15 = w[i - 15], w2 = w[i - 2];
                var a = hash[0], e = hash[4];
                var temp1 = hash[7]
                    + (rightRotate(e, 6) ^ rightRotate(e, 11) ^ rightRotate(e, 25))
                    + ((e & hash[5]) ^ ((~e) & hash[6]))
                    + k[i]
                    + (w[i] = (i < 16) ? w[i] : (
                        w[i - 16]
                        + (rightRotate(w15, 7) ^ rightRotate(w15, 18) ^ (w15 >>> 3))
                        + w[i - 7]
                        + (rightRotate(w2, 17) ^ rightRotate(w2, 19) ^ (w2 >>> 10))
                    ) | 0);
                var temp2 = (rightRotate(a, 2) ^ rightRotate(a, 13) ^ rightRotate(a, 22))
                    + ((a & hash[1]) ^ (a & hash[2]) ^ (hash[1] & hash[2]));
                hash = [(temp1 + temp2) | 0].concat(hash);
                hash[4] = (hash[4] + temp1) | 0;
            }
            for (i = 0; i < 8; i++) hash[i] = (hash[i] + oldHash[i]) | 0;
        }
        for (i = 0; i < 8; i++) {
            for (j = 3; j + 1; j--) {
                var b = (hash[i] >> (j * 8)) & 255;
                result += ((b < 16) ? 0 : '') + b.toString(16);
            }
        }
        return result;
    }

    function hashEntries(entries) {
        var text = canonicalPayload(entries);
        if (window.crypto && window.crypto.subtle && typeof TextEncoder !== 'undefined') {
            return window.crypto.subtle.digest('SHA-256', new TextEncoder().encode(text)).then(function (buffer) {
                var bytes = new Uint8Array(buffer);
                var out = '';
                for (var i = 0; i < bytes.length; i++) out += (bytes[i] < 16 ? '0' : '') + bytes[i].toString(16);
                return out;
            });
        }
        return Promise.resolve(sha256HexAscii(utf8BinaryString(text)));
    }

    function fetchSecurityStatus() {
        return fetch(SECURITY_URL + '?action=status', {credentials: 'same-origin', cache: 'no-store'})
            .then(function (r) { return r.json(); });
    }

    function beginSecurityVerify(action, target, entries, display) {
        if (state.pendingSecurity) {
            dqNotify('error', 'Verification in progress', 'Finish the open security authorization first.');
            return Promise.resolve(null);
        }
        return hashEntries(entries).then(function (hash) {
            return fetchSecurityStatus().then(function (status) {
                if (!status || status.success === false) {
                    dqNotify('error', 'Security check failed', 'Could not check the security settings. Please try again.');
                    return null;
                }
                if (!status.configured) {
                    dqNotify('error', 'Security code not set up', 'A Super Admin must set the Security Authorization Code before the official order can be published.');
                    return null;
                }
                if (status.locked_until) {
                    dqNotify('error', 'Verification temporarily locked', 'Too many incorrect attempts. Please try again after ' + status.locked_until + ' UTC.');
                    return null;
                }
                return new Promise(function (resolve) {
                    state.pendingSecurity = {action: action, target: target, hash: hash, resolve: resolve, busy: false};
                    el('dqSecuritySummary').textContent = display || action;
                    el('dqSecurityCode').value = '';
                    el('dqSecurityError').hidden = true;
                    bootstrap.Modal.getOrCreateInstance(el('dqSecurityModal')).show();
                    setTimeout(function () { var input = el('dqSecurityCode'); if (input) input.focus(); }, 350);
                });
            });
        });
    }

    function submitSecurityVerify() {
        if (!state.pendingSecurity || state.pendingSecurity.busy) return;
        var code = el('dqSecurityCode').value;
        if (String(code).trim() === '') {
            showSecurityError('Enter the Security Authorization Code.');
            return;
        }
        state.pendingSecurity.busy = true;
        el('dqSecurityConfirm').disabled = true;

        fetch(SECURITY_URL + '?action=verify', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                verify_action: state.pendingSecurity.action,
                target: state.pendingSecurity.target,
                payload_hash: state.pendingSecurity.hash,
                code: code
            }),
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (json) {
            json = json || {};
            if (json.success && json.token) {
                var resolve = state.pendingSecurity.resolve;
                state.pendingSecurity = null;
                hideSecurityModal();
                resolve(json.token);
                return;
            }
            if (json.code === 'incorrect_code') {
                showSecurityError(json.message || 'Incorrect Security Authorization Code.');
                el('dqSecurityCode').value = '';
                el('dqSecurityCode').focus();
                return;
            }
            var finish = state.pendingSecurity ? state.pendingSecurity.resolve : null;
            state.pendingSecurity = null;
            hideSecurityModal();
            if (json.code === 'locked') dqNotify('error', 'Verification locked', json.message || 'Too many incorrect attempts.');
            else if (json.code === 'security_not_configured') dqNotify('error', 'Security code not set up', json.message || '');
            else if (json.code === 'role_denied') dqNotify('error', 'Not allowed', json.message || 'You are not allowed to publish the dispatch queue.');
            else dqNotify('error', 'Verification failed', json.message || 'Could not verify the code. Please try again.');
            if (finish) finish(null);
        }).catch(function () {
            showSecurityError('Could not reach the server. Please try again.');
        }).finally(function () {
            if (state.pendingSecurity) state.pendingSecurity.busy = false;
            var btn = el('dqSecurityConfirm');
            if (btn) btn.disabled = false;
        });
    }

    function showSecurityError(message) {
        var box = el('dqSecurityError');
        box.textContent = message;
        box.hidden = false;
    }

    function hideSecurityModal() {
        var instance = bootstrap.Modal.getInstance(el('dqSecurityModal'));
        if (instance) instance.hide();
    }

    /* ------------------------------ server writes ------------------------------ */

    function postOrder() {
        var order = ((state.payload && state.payload.entries) || [])
            .filter(function (entry) { return entry.eligible !== false; })
            .map(function (entry) { return entry.bus_id; });
        return apiPost('/admin/api/Dispatch/save_queue.php', {date: state.date, order: order}).then(function (res) {
            state.payload = res.data;
            state.dirty = false;
            render();
            return res;
        });
    }

    function saveDraft() {
        if (!state.payload) return;
        if (isLocked()) {
            dqNotify('error', isHistory() ? 'History date' : 'Queue locked', lockMessage());
            return;
        }
        el('dqSaveBtn').disabled = true;
        postOrder().then(function (res) {
            dqNotify('success', 'Draft saved', res.message || 'The dispatch queue was saved.');
        }).catch(function (error) {
            dqNotify('error', 'Save failed', error.message || 'Please try again.');
        }).finally(function () {
            el('dqSaveBtn').disabled = isLocked();
        });
    }

    /* The explanation shown when an edit or a second publish is attempted on a
       locked operating date. */
    function lockMessage() {
        if (isHistory()) {
            return 'This operating date is in the past. Past queues are read-only - switch to today to make changes.';
        }
        return 'The official dispatch order for ' + fmtDisplayDate(state.date) +
            ' has already been published and is locked. Choose another operating date to make changes.';
    }

    function publish() {
        if (!state.payload) return;
        if (isLocked()) {
            dqNotify('error', isHistory() ? 'History date' : 'Queue locked', lockMessage());
            return;
        }
        if (!state.payload.entries.length) {
            dqNotify('error', 'Nothing to publish', 'There are no dispatchable buses for this date.');
            return;
        }
        /* Show the complete order and details first; publishing only proceeds
           from the preview's explicit confirmation. */
        openPreview();
    }

    function doPublish() {
        if (isLocked()) {
            dqNotify('error', isHistory() ? 'History date' : 'Queue locked', lockMessage());
            return;
        }
        var run = function () {
            el('dqPublishBtn').disabled = true;
            var payload = {date: state.date};
            return beginSecurityVerify(
                'publish',
                'dispatch',
                [['date', state.date]],
                'Publish the official dispatch order for ' + fmtDisplayDate(state.date)
            ).then(function (token) {
                if (!token) {
                    el('dqPublishBtn').disabled = false;
                    return;
                }
                payload.security_token = token;
                return apiPost('/admin/api/Dispatch/publish.php', payload).then(function (res) {
                    state.payload = res.data;
                    state.dirty = false;
                    render();
                    dqNotify('success', 'Dispatch order published', res.message || '');
                }).catch(function (error) {
                    var failure = error.payload || {};
                    var text = failure.message || error.message || 'Please try again.';
                    if (failure.blockers && failure.blockers.length) {
                        text += ' ' + failure.blockers.join(' ');
                    }
                    var code = String(failure.code || '');
                    var title = (code.indexOf('security') === 0 || ['role_denied', 'rate_limited', 'locked'].indexOf(code) !== -1)
                        ? 'Security authorization'
                        : 'Publish blocked';
                    dqNotify('error', title, text);
                }).finally(function () {
                    el('dqPublishBtn').disabled = isLocked();
                });
            });
        };

        /* A freshly loaded queue is only a suggestion until it is saved: make
           sure the visible order is persisted before publishing, otherwise the
           server has no rows to publish. */
        var hasUnpersisted = ((state.payload && state.payload.entries) || []).some(function (entry) {
            return entry.persisted !== true;
        });

        if (state.dirty || hasUnpersisted || !(state.payload && state.payload.published)) {
            el('dqPublishBtn').disabled = true;
            postOrder().then(run).catch(function (error) {
                el('dqPublishBtn').disabled = false;
                dqNotify('error', 'Publish failed', error.message || 'The queue could not be saved first.');
            });
            return;
        }
        run();
    }

    /* --------------------------------- modals --------------------------------- */

    var HISTORY_ICONS = {
        'Queue Published': 'bi-patch-check',
        'Queue Updated': 'bi-pencil-square',
        'Queue Override': 'bi-arrows-move',
        'Terminal Assigned': 'bi-geo-alt'
    };

    function openHistory() {
        if (!state.payload) return;
        el('dqHistoryDate').textContent = fmtDisplayDate(state.date);
        el('dqHistoryBody').innerHTML = '<div class="rmg-state"><i class="bi bi-hourglass-split"></i><h6>Loading history...</h6>Fetching the audit trail.</div>';
        bootstrap.Modal.getOrCreateInstance(el('dqHistoryModal')).show();

        apiGet('/admin/api/Dispatch/history.php?date=' + encodeURIComponent(state.date)).then(function (res) {
            renderHistory(res.data || {});
        }).catch(function (error) {
            el('dqHistoryBody').innerHTML = '<div class="rmg-state error"><i class="bi bi-exclamation-circle"></i><h6>History could not be loaded</h6>' + esc(error.message || 'Please try again.') + '</div>';
        });
    }

    function renderHistory(data) {
        var activity = data.activity || [];
        var published = data.published_dates || [];

        var activityHtml = activity.length ? activity.map(function (row) {
            var icon = HISTORY_ICONS[row.action] || 'bi-dot';
            var meta = [row.action, row.username, row.created_at].filter(function (part) { return String(part || '').trim() !== ''; }).join(' · ');
            return '<div class="dq-history-row">' +
                '<span class="dq-history-icon"><i class="bi ' + esc(icon) + '"></i></span>' +
                '<div class="dq-history-main">' +
                '<div class="dq-history-text">' + esc(row.description || row.action || 'Queue activity') + '</div>' +
                '<div class="dq-history-meta">' + esc(meta) + '</div>' +
                '</div></div>';
        }).join('') : '<div class="dq-history-empty">No queue activity recorded for this date yet.</div>';

        var publishedHtml = published.length ? published.map(function (row) {
            return '<div class="dq-history-row">' +
                '<span class="dq-history-icon"><i class="bi bi-calendar-check"></i></span>' +
                '<div class="dq-history-main">' +
                '<div class="dq-history-text">' + esc(fmtDisplayDate(row.operating_date)) + ' · ' + row.buses + ' bus' + (row.buses === 1 ? '' : 'es') + '</div>' +
                '<div class="dq-history-meta">' + esc([row.published_at, row.published_by].filter(function (part) { return String(part || '').trim() !== ''; }).join(' · ')) + '</div>' +
                '</div>' +
                '<button type="button" class="rmg-btn rmg-btn-sm" data-action="load-date" data-date="' + esc(row.operating_date) + '">Load</button>' +
                '</div>';
        }).join('') : '<div class="dq-history-empty">No orders have been published yet.</div>';

        el('dqHistoryBody').innerHTML =
            '<div class="dq-history-block">' +
            '<div class="dq-block-title"><i class="bi bi-activity"></i> Activity for this date</div>' +
            activityHtml +
            '</div>' +
            '<div class="dq-history-block">' +
            '<div class="dq-block-title"><i class="bi bi-journal-check"></i> Recently published orders</div>' +
            publishedHtml +
            '</div>';
    }

    function bindHistoryActions() {
        var host = el('dqHistoryBody');
        if (!host) return;
        host.addEventListener('click', function (event) {
            var target = rowFromEvent(event, '[data-action="load-date"]');
            if (!target) return;
            var date = target.getAttribute('data-date') || '';
            if (date === '') return;
            state.date = date;
            state.dirty = false;
            if (el('dqDate')) el('dqDate').value = date;
            bootstrap.Modal.getOrCreateInstance(el('dqHistoryModal')).hide();
            load();
        });
    }

    /* ------------------------------- print copy ------------------------------- */

    function printOrder() {
        if (!state.payload) return;
        var payload = state.payload;
        var entries = payload.entries || [];

        var rows = entries.map(function (item) {
            var driver = item.assigned_driver || {};
            var trip = item.active_trip ? 'On Trip' : (item.trip_status || 'Idle');
            var terminal = String(item.effective_terminal || '').trim();
            return '<tr>' +
                '<td class="num">#' + (item.queue_position || '') + '</td>' +
                '<td><strong>' + esc(item.bus_id) + '</strong></td>' +
                '<td>' + esc(item.plate_number || '-') + '</td>' +
                '<td>' + esc(item.bus_type || '-') + '</td>' +
                '<td>' + esc(driver.full_name || 'Unassigned') + '</td>' +
                '<td>' + esc(terminal !== '' ? terminal : '-') + '</td>' +
                '<td>' + esc(trip) + '</td>' +
                '</tr>';
        }).join('');
        if (rows === '') {
            rows = '<tr><td colspan="7" class="empty">No dispatchable buses for this date.</td></tr>';
        }

        var status = payload.published
            ? 'OFFICIAL - published ' + esc(payload.published.at) + (payload.published.by ? ' by ' + esc(payload.published.by) : '')
            : 'DRAFT - not yet published';
        var excluded = (payload.unavailable || []).map(function (item) { return item.bus_id; }).join(', ');
        var generatedBy = CFG.user ? ' by ' + esc(CFG.user) : '';

        var win = window.open('', '', 'width=1000,height=700');
        if (!win) {
            dqNotify('error', 'Print blocked', 'Unable to open the print window. Check your popup blocker.');
            return;
        }

        win.document.write('<html><head><title>R-Connect Dispatch Order</title><style>'
            + 'body{font-family:Arial,Helvetica,sans-serif;color:#2e4836;padding:32px;}'
            + '.header{text-align:center;margin-bottom:20px;}'
            + '.title{font-size:22px;font-weight:bold;}'
            + '.subtitle{margin-top:6px;font-size:14px;color:#4a5c4d;}'
            + '.status{display:inline-block;margin-top:8px;padding:4px 12px;border:1px solid #b6d29a;border-radius:999px;font-size:11px;font-weight:bold;color:#295738;background:#e7f0de;}'
            + '.status.draft{border-color:#e5d3a4;color:#7a6f12;background:#fdf6ee;}'
            + 'table{width:100%;border-collapse:collapse;font-size:13px;margin-top:16px;}'
            + 'th{background:#2e4836;color:#fff;padding:10px 12px;text-align:left;}'
            + 'th.num,td.num{text-align:right;}'
            + 'td{padding:9px 12px;border-bottom:1px solid #e0e0d8;}'
            + 'tbody tr:nth-child(even){background:#f7f8f2;}'
            + '.empty{text-align:center;color:#8a948b;padding:18px;}'
            + '.note{margin-top:14px;font-size:11px;color:#8a5a4a;}'
            + '.footer{margin-top:28px;text-align:right;font-size:11px;color:#6b7a6d;}'
            + '@media print{body{padding:12px;}}'
            + '</style></head><body>'
            + '<div class="header">'
            + '<div class="title">R-Connect TMS - Official Dispatch Order</div>'
            + '<div class="subtitle">Operating date: ' + esc(fmtDisplayDate(payload.operating_date)) + '</div>'
            + '<div class="status' + (payload.published ? '' : ' draft') + '">' + status + '</div>'
            + '</div>'
            + '<table><thead><tr>'
            + '<th class="num">#</th><th>Bus</th><th>Plate</th><th>Type</th><th>Assigned Driver</th><th>Terminal</th><th>Trip Status</th>'
            + '</tr></thead><tbody>' + rows + '</tbody></table>'
            + (excluded !== '' ? '<div class="note">Excluded (unavailable): ' + esc(excluded) + '</div>' : '')
            + '<div class="footer">Generated: ' + esc(new Date().toLocaleString()) + generatedBy + '</div>'
            + '</body></html>');

        win.document.close();
        win.focus();
        setTimeout(function () {
            win.print();
            win.close();
        }, 400);
    }

    /* ---------------------------- publish preview ---------------------------- */

    /* The dispatch status shown for one preview row: the recorded trip status
       for a bus on a trip, otherwise its status label for the date. */
    function previewStatusHtml(item) {
        var status = String(item.dispatch_status || '').trim();
        if (item.active_trip) {
            var number = item.active_trip.trip_number ? 'Trip #' + esc(item.active_trip.trip_number) + ' · ' : '';
            var label = status !== '' ? status : (item.active_trip.status || 'On Trip');
            return '<span class="rmg-status is-pending">On Trip</span><span class="dq-preview-meta">' + number + esc(label) + '</span>';
        }
        if (status !== '' && status.toLowerCase() !== 'idle') {
            return '<span class="rmg-status is-other">' + esc(status) + '</span>';
        }
        if (item.trips_today > 0) {
            return '<span class="rmg-status is-other">' + esc(item.trip_status) + '</span>';
        }
        return '<span class="rmg-missing">Idle</span>';
    }

    function previewRowHtml(item, index) {
        var position = item.queue_position !== undefined && item.queue_position !== null ? item.queue_position : (index + 1);
        var driver = item.assigned_driver || {};
        var driverHtml = driver.driver_id
            ? '<strong>' + esc(driver.full_name || driver.driver_id) + '</strong><span class="dq-driver-id">' + esc(driver.driver_id) + '</span>' +
                (driver.current_terminal ? '<span class="dq-driver-terminal"><i class="bi bi-geo-alt"></i> ' + esc(driver.current_terminal) + '</span>' : '')
            : '<span class="rmg-missing">Unassigned</span>';
        var routeHtml = item.route_name
            ? esc(item.route_name)
            : '<span class="rmg-missing">No route</span>';
        var currentTerminal = String(item.effective_terminal || '').trim();
        var receivingTerminal = String(item.receiving_terminal || '').trim();
        var terminalHtml = currentTerminal !== '' || receivingTerminal !== ''
            ? '<strong>' + esc(currentTerminal !== '' ? currentTerminal : '—') + '</strong>' +
                (receivingTerminal !== '' ? '<span class="dq-preview-meta">Receiving: ' + esc(receivingTerminal) + '</span>' : '')
            : '<span class="rmg-missing">No terminal</span>';
        var plate = displayValue(item.plate_number, 'No plate');
        return '<tr>' +
            '<td data-label="#" class="dq-preview-pos">#' + esc(String(position)) + '</td>' +
            '<td data-label="Bus" class="dq-preview-bus">' + esc(item.bus_id) + '<span class="dq-preview-meta">' + plate + '</span></td>' +
            '<td data-label="Driver">' + driverHtml + '</td>' +
            '<td data-label="Terminal">' + terminalHtml + '</td>' +
            '<td data-label="Route">' + routeHtml + '</td>' +
            '<td data-label="Status" class="dq-preview-status">' + previewStatusHtml(item) + '</td>' +
            '</tr>';
    }

    /* Renders the current in-memory order (including unsaved drag reorders) so
       the admin confirms exactly the order that will be published. */
    function renderPreview() {
        var body = el('dqPreviewBody');
        if (!body) return;
        var payload = state.payload || {};
        var entries = payload.entries || [];
        var sub = el('dqPreviewSub');
        if (sub) sub.textContent = fmtDisplayDate(state.date) + ' · ' + entries.length + ' bus' + (entries.length === 1 ? '' : 'es');

        var note = el('dqPreviewNote');
        if (!entries.length) {
            if (note) note.hidden = true;
            body.innerHTML = '<div class="rmg-state"><i class="bi bi-inbox"></i><h6>No dispatchable buses</h6>There is nothing to publish for this date.</div>';
            return;
        }

        var rows = entries.map(previewRowHtml).join('');
        body.innerHTML = '<div class="rmg-table-wrap"><table class="rmg-table dq-preview-table">' +
            '<thead><tr><th>#</th><th>Bus</th><th>Assigned Driver</th><th>Terminal</th><th>Route</th><th>Dispatch Status</th></tr></thead>' +
            '<tbody>' + rows + '</tbody></table></div>';

        var blocked = entries.filter(function (entry) { return entry.eligible === false; });
        if (note) {
            if (blocked.length) {
                note.hidden = false;
                note.innerHTML = '<i class="bi bi-exclamation-triangle"></i><span>' + blocked.length + ' bus' + (blocked.length === 1 ? '' : 'es') +
                    ' cannot be published: ' + blocked.map(function (entry) { return esc(entry.bus_id); }).join(', ') +
                    '. Publishing is blocked until they are available again.</span>';
            } else {
                note.hidden = true;
            }
        }
    }

    function openPreview() {
        renderPreview();
        bootstrap.Modal.getOrCreateInstance(el('dqPreviewModal')).show();
    }

    function confirmPreviewPublish() {
        if (isLocked()) {
            var lockedInstance = bootstrap.Modal.getInstance(el('dqPreviewModal'));
            if (lockedInstance) lockedInstance.hide();
            dqNotify('error', isHistory() ? 'History date' : 'Queue locked', lockMessage());
            return;
        }
        var instance = bootstrap.Modal.getInstance(el('dqPreviewModal'));
        if (instance) instance.hide();
        doPublish();
    }

    function openAssign(busId) {
        if (!state.payload || isLocked()) return;
        var entries = state.payload.entries || [];
        var item = null;
        for (var i = 0; i < entries.length; i++) {
            if (entries[i].bus_id === busId) item = entries[i];
        }
        if (!item) return;

        state.assignBusId = busId;
        var currentDriver = item.assigned_driver || {};

        el('dqAssignBus').innerHTML = esc(item.bus_id) + ' · ' + displayValue(item.plate_number, 'No plate');
        el('dqAssignError').hidden = true;

        var select = el('dqAssignDriver');
        var busTerminalId = effectiveTerminalId(item);
        var options = ['<option value="">Select a driver...</option>'];
        var selectable = 0;
        var seen = {};

        /* The bus's current driver stays listed (selected) so the assignment
           can be reviewed or kept, even while they are unavailable. */
        if (currentDriver.driver_id) {
            seen[String(currentDriver.driver_id)] = true;
            options.push('<option value="' + esc(currentDriver.driver_id) + '" selected>' +
                esc(currentDriver.full_name || currentDriver.driver_id) + ' (' + esc(currentDriver.driver_id) + ') · Current driver</option>');
        }

        /* Only eligible, unassigned drivers at this terminal are selectable:
           occupied drivers are removed until their trip completes and the
           arrival at the receiving terminal is confirmed, and drivers at
           another terminal stay off this terminal's list. */
        ((state.payload && state.payload.drivers) || []).forEach(function (option) {
            var driverId = String(option.driver_id || '');
            if (driverId === '' || seen[driverId]) return;
            seen[driverId] = true;
            if (!option.eligible || option.occupied) return;
            var terminalMismatch = option.terminal_id !== undefined && option.terminal_id !== null && option.terminal_id !== ''
                && busTerminalId !== ''
                && String(option.terminal_id) !== busTerminalId;
            if (terminalMismatch) return;

            var label = (option.full_name || driverId) + ' (' + driverId + ')';
            if (option.assigned_bus) label += ' · Assigned to ' + option.assigned_bus;
            if (option.terminal) label += ' · At ' + option.terminal;
            if (option.availability && String(option.availability).toLowerCase() !== 'available') label += ' · ' + option.availability;
            options.push('<option value="' + esc(driverId) + '">' + esc(label) + '</option>');
            selectable++;
        });
        if (selectable === 0 && !currentDriver.driver_id) {
            options.push('<option value="" disabled>No eligible drivers available</option>');
        }
        select.innerHTML = options.join('');
        el('dqAssignHint').textContent = 'Only eligible, unassigned drivers at this terminal are listed. A driver assigned to another bus shows that bus, and choosing them asks for confirmation before the assignment moves; drivers assigned for this operating date are removed until their trip completes and the arrival at the receiving terminal is confirmed.';
        bootstrap.Modal.getOrCreateInstance(el('dqAssignModal')).show();
    }

    function applyAssignSuccess(res) {
        state.payload = res.data;
        state.dirty = false;
        render();
        bootstrap.Modal.getOrCreateInstance(el('dqAssignModal')).hide();
        dqNotify('success', 'Driver assignment saved', res.message || '');
    }

    function showAssignError(error) {
        var payload = error.payload || {};
        el('dqAssignError').textContent = payload.message || error.message || 'The driver assignment could not be saved.';
        el('dqAssignError').hidden = false;
    }

    function submitAssign() {
        var busId = state.assignBusId;
        var driverId = el('dqAssignDriver').value;
        if (!busId || !driverId) {
            el('dqAssignError').textContent = 'Select a driver to assign.';
            el('dqAssignError').hidden = false;
            return;
        }
        el('dqAssignSave').disabled = true;
        apiPost('/admin/api/Dispatch/assign_driver.php', {
            bus_id: busId,
            driver_id: driverId,
            date: state.date
        }).then(function (res) {
            applyAssignSuccess(res);
        }).catch(function (error) {
            var payload = error.payload || {};
            if (payload.code === 'driver_conflict' && payload.conflict_bus) {
                return new Promise(function (resolve) {
                    var moveDriver = function () {
                        apiPost('/admin/api/Dispatch/assign_driver.php', {
                            bus_id: busId,
                            driver_id: driverId,
                            date: state.date,
                            allow_move: true
                        }).then(function (res) {
                            applyAssignSuccess(res);
                        }).catch(function (moveError) {
                            showAssignError(moveError);
                        }).then(resolve);
                    };
                    var text = 'This driver is assigned to ' + payload.conflict_bus + '. Move the assignment to ' + busId + '?';
                    if (typeof window.showConfirm === 'function') {
                        window.showConfirm('Move driver assignment?', text, moveDriver, resolve);
                    } else {
                        moveDriver();
                    }
                });
            }
            showAssignError(error);
        }).finally(function () {
            el('dqAssignSave').disabled = false;
        });
    }

    function openOverride(busId) {
        if (!state.payload || isLocked()) return;
        var entries = state.payload.entries || [];
        var item = null;
        for (var i = 0; i < entries.length; i++) {
            if (entries[i].bus_id === busId) item = entries[i];
        }
        if (!item) return;

        state.overrideBusId = busId;
        el('dqOverrideBus').innerHTML = esc(item.bus_id) + ' · currently position #' + item.queue_position;
        el('dqOverridePosition').max = Math.max(1, entries.length);
        el('dqOverridePosition').value = String(item.queue_position);
        el('dqOverrideReason').value = '';
        el('dqOverrideError').hidden = true;
        bootstrap.Modal.getOrCreateInstance(el('dqOverrideModal')).show();
    }

    function submitOverride() {
        var busId = state.overrideBusId;
        var position = parseInt(el('dqOverridePosition').value, 10);
        var reason = String(el('dqOverrideReason').value || '').trim();
        if (!busId) return;
        if (!position || position < 1) {
            el('dqOverrideError').textContent = 'Enter a queue position of 1 or higher.';
            el('dqOverrideError').hidden = false;
            return;
        }
        if (reason.length < 5) {
            el('dqOverrideError').textContent = 'A reason for this override is required (at least 5 characters).';
            el('dqOverrideError').hidden = false;
            return;
        }
        el('dqOverrideSave').disabled = true;
        apiPost('/admin/api/Dispatch/override.php', {
            date: state.date,
            bus_id: busId,
            position: position,
            reason: reason
        }).then(function (res) {
            state.payload = res.data;
            state.dirty = false;
            render();
            bootstrap.Modal.getOrCreateInstance(el('dqOverrideModal')).hide();
            dqNotify('success', 'Queue position overridden', res.message || '');
        }).catch(function (error) {
            el('dqOverrideError').textContent = error.message || 'The override could not be saved.';
            el('dqOverrideError').hidden = false;
        }).finally(function () {
            el('dqOverrideSave').disabled = false;
        });
    }

    function findBusItem(busId) {
        var payload = state.payload || {};
        var list = (payload.entries || []).concat(payload.unavailable || []);
        for (var i = 0; i < list.length; i++) {
            if (list[i].bus_id === busId) return list[i];
        }
        return null;
    }

    function openAvailability(busId) {
        if (isLocked()) return;
        var item = findBusItem(busId);
        if (!item) return;

        state.availabilityBusId = busId;
        el('dqAvailabilityBus').innerHTML = esc(item.bus_id) + ' · ' + displayValue(item.plate_number, 'No plate');

        var current = String(item.operational_status || '').trim();
        var matched = '';
        AVAILABILITY_STATUSES.forEach(function (status) {
            if (status.toLowerCase() === current.toLowerCase()) matched = status;
        });
        el('dqAvailabilityStatus').value = matched !== '' ? matched : 'Under Maintenance';
        el('dqAvailabilityHint').textContent = item.problem
            ? 'This bus also has an open problem report (' + (item.problem.problem_type || 'bus problem') + '); the Dispatcher must resolve it before the bus can dispatch again.'
            : 'Unavailable buses are excluded from the queue and cannot be published until they are available again.';
        el('dqAvailabilityReason').value = '';
        el('dqAvailabilityError').hidden = true;
        bootstrap.Modal.getOrCreateInstance(el('dqAvailabilityModal')).show();
    }

    function submitAvailability() {
        var busId = state.availabilityBusId;
        var status = el('dqAvailabilityStatus').value;
        var reason = String(el('dqAvailabilityReason').value || '').trim();
        if (!busId) return;
        if (AVAILABILITY_STATUSES.indexOf(status) === -1) {
            el('dqAvailabilityError').textContent = 'Select a valid availability status.';
            el('dqAvailabilityError').hidden = false;
            return;
        }
        if (reason.length < 5) {
            el('dqAvailabilityError').textContent = 'A reason for this change is required (at least 5 characters).';
            el('dqAvailabilityError').hidden = false;
            return;
        }
        el('dqAvailabilitySave').disabled = true;
        apiPost('/admin/api/Dispatch/set_availability.php', {
            bus_id: busId,
            operational_status: status,
            reason: reason,
            date: state.date
        }).then(function (res) {
            state.payload = res.data;
            state.dirty = false;
            render();
            bootstrap.Modal.getOrCreateInstance(el('dqAvailabilityModal')).hide();
            dqNotify('success', 'Bus availability updated', res.message || '');
        }).catch(function (error) {
            el('dqAvailabilityError').textContent = error.message || 'The bus availability could not be changed.';
            el('dqAvailabilityError').hidden = false;
        }).finally(function () {
            el('dqAvailabilitySave').disabled = false;
        });
    }

    /* --------------------------- terminal assignment --------------------------- */

    function findEntry(busId) {
        var entries = (state.payload && state.payload.entries) || [];
        for (var i = 0; i < entries.length; i++) {
            if (entries[i].bus_id === busId) return entries[i];
        }
        return null;
    }

    function openTerminal(busId) {
        if (!state.payload || isLocked()) return;
        var item = findEntry(busId);
        if (!item) return;

        state.terminalBusId = busId;
        el('dqTerminalBus').innerHTML = esc(item.bus_id) + ' · ' + displayValue(item.plate_number, 'No plate');
        el('dqTerminalError').hidden = true;

        var terminals = (state.payload && state.payload.terminals) || [];
        var options = ['<option value="">Select a terminal...</option>'];
        terminals.forEach(function (terminal) {
            options.push('<option value="' + esc(String(terminal.id)) + '">' + esc(terminal.name) + '</option>');
        });
        var select = el('dqTerminalSelect');
        select.innerHTML = options.join('');
        var currentId = item.terminal_id !== undefined && item.terminal_id !== null && item.terminal_id !== ''
            ? String(item.terminal_id)
            : effectiveTerminalId(item);
        select.value = currentId !== '' ? currentId : '';
        el('dqTerminalBusHint').textContent = terminals.length
            ? 'The bus enters this terminal\'s queue and is dispatched from there until a confirmed arrival moves it. The assigned driver follows the bus terminal automatically.'
            : 'No terminals are configured, so assignments are unavailable.';

        bootstrap.Modal.getOrCreateInstance(el('dqTerminalModal')).show();
    }

    function submitTerminal() {
        var busId = state.terminalBusId;
        var terminalId = el('dqTerminalSelect').value;
        if (!busId) return;
        if (!terminalId) {
            el('dqTerminalError').textContent = 'Select a terminal for this bus.';
            el('dqTerminalError').hidden = false;
            return;
        }
        var payload = {
            date: state.date,
            bus_id: busId,
            terminal_id: parseInt(terminalId, 10)
        };
        var entry = findEntry(busId) || {};
        var driver = entry.assigned_driver || {};
        if (driver.driver_id) {
            /* The driver always follows the bus terminal: no separate driver
               terminal is sent, so the service scopes the driver here. */
            payload.driver_id = driver.driver_id;
        }

        el('dqTerminalSave').disabled = true;
        apiPost('/admin/api/Dispatch/assign_terminal.php', payload).then(function (res) {
            state.payload = res.data;
            state.dirty = false;
            render();
            bootstrap.Modal.getOrCreateInstance(el('dqTerminalModal')).hide();
            dqNotify('success', 'Terminal assignment saved', res.message || '');
        }).catch(function (error) {
            el('dqTerminalError').textContent = error.message || 'The terminal assignment could not be saved.';
            el('dqTerminalError').hidden = false;
        }).finally(function () {
            el('dqTerminalSave').disabled = false;
        });
    }

    /* -------------------------------- listeners -------------------------------- */

    function bindListActions() {
        var host = el('dqList');
        if (!host) return;
        host.addEventListener('click', function (event) {
            var target = rowFromEvent(event, '[data-action]');
            if (!target) return;
            if (isLocked()) {
                dqNotify('error', isHistory() ? 'History date' : 'Queue locked', lockMessage());
                return;
            }
            var action = target.getAttribute('data-action');
            var busId = target.getAttribute('data-bus') || '';
            if (action === 'assign') openAssign(busId);
            else if (action === 'terminal') openTerminal(busId);
            else if (action === 'override') openOverride(busId);
            else if (action === 'availability') openAvailability(busId);
        });
        bindDragAndDrop();
    }

    function init() {
        renderHistoryMode();
        var dateInput = el('dqDate');
        if (dateInput) {
            dateInput.value = state.date;
            dateInput.addEventListener('change', function () {
                if (state.dirty && typeof window.showConfirm === 'function') {
                    var next = dateInput.value;
                    window.showConfirm('Discard unsaved changes?', 'The current order has unsaved edits.', function () {
                        state.date = next;
                        state.dirty = false;
                        load();
                    }, function () {
                        dateInput.value = state.date;
                    });
                    return;
                }
                state.date = dateInput.value;
                state.dirty = false;
                load();
            });
        }

        var search = el('dqSearch');
        if (search) {
            var timer = null;
            search.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    state.search = search.value.trim();
                    render();
                }, 150);
            });
        }

        var terminalFilter = el('dqTerminal');
        if (terminalFilter) {
            terminalFilter.addEventListener('change', function () {
                state.terminal = terminalFilter.value;
                render();
            });
        }

        var clear = el('dqClearFilters');
        if (clear) clear.addEventListener('click', clearFilters);
        var refresh = el('dqRefresh');
        if (refresh) {
            refresh.addEventListener('click', function () {
                if (state.dirty && typeof window.showConfirm === 'function') {
                    window.showConfirm('Discard unsaved changes?', 'The current order has unsaved edits.', function () {
                        state.dirty = false;
                        load();
                    }, function () {});
                    return;
                }
                load();
            });
        }
        var save = el('dqSaveBtn');
        if (save) save.addEventListener('click', saveDraft);
        var publishBtn = el('dqPublishBtn');
        if (publishBtn) publishBtn.addEventListener('click', publish);
        var previewConfirm = el('dqPreviewConfirm');
        if (previewConfirm) previewConfirm.addEventListener('click', confirmPreviewPublish);
        var secConfirm = el('dqSecurityConfirm');
        if (secConfirm) secConfirm.addEventListener('click', submitSecurityVerify);
        var secCode = el('dqSecurityCode');
        if (secCode) secCode.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') { event.preventDefault(); submitSecurityVerify(); }
        });
        var secModal = el('dqSecurityModal');
        if (secModal) secModal.addEventListener('hidden.bs.modal', function () {
            if (state.pendingSecurity) {
                var resolve = state.pendingSecurity.resolve;
                state.pendingSecurity = null;
                resolve(null);
            }
        });
        var historyBtn = el('dqHistoryBtn');
        if (historyBtn) historyBtn.addEventListener('click', openHistory);
        var printBtn = el('dqPrintBtn');
        if (printBtn) printBtn.addEventListener('click', printOrder);
        var assignSave = el('dqAssignSave');
        if (assignSave) assignSave.addEventListener('click', submitAssign);
        var overrideSave = el('dqOverrideSave');
        if (overrideSave) overrideSave.addEventListener('click', submitOverride);
        var availabilitySave = el('dqAvailabilitySave');
        if (availabilitySave) availabilitySave.addEventListener('click', submitAvailability);
        var terminalSave = el('dqTerminalSave');
        if (terminalSave) terminalSave.addEventListener('click', submitTerminal);

        bindListActions();
        bindHistoryActions();
        load();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
