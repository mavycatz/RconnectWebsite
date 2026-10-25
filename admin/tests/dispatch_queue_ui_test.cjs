/*
 * Bus Dispatch Queue UI harness.
 *
 * Runs admin/assets/js/dispatch-queue.js in a vm context with a miniature DOM,
 * a stateful mock fetch router, drag events, and mocked bootstrap/alerts, then
 * asserts:
 *   - numbered queue of every eligible BUS- bus, unavailable list, no add/remove
 *   - drag and drop reordering + dirty state + save-draft payload
 *   - assign modal lists every driver with the current assignment shown, blocks on-leave
 *   - assignment, override, publish (blocked + success), search
 *
 *   node admin/tests/dispatch_queue_ui_test.cjs
 */

const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');

/* ------------------------------- mock state ------------------------------- */

const alerts = [];
const confirms = [];
const fetchLog = [];
const assignCalls = [];
const overrideCalls = [];
const saveCalls = [];
const availabilityCalls = [];
const terminalCalls = [];
const verifyCalls = [];
const popups = [];
const publishedDates = {};
let activeDate = '2026-10-14';
let publishBlocked = false;
let deferConfirms = false;
let assignRejection = null;
let terminalRejection = null;

const terminalsMock = [
    {id: 1, name: 'Rizal Central Terminal'},
    {id: 2, name: 'Cabanatuan Central Terminal'}
];

function terminalById(id) {
    return terminalsMock.find(terminal => terminal.id === id) || null;
}

function busItem(busId, overrides) {
    return Object.assign({
        bus_id: busId,
        plate_number: 'PLT-' + busId,
        bus_type: 'Coach',
        schedule: '',
        queue_position: null,
        persisted: false,
        operational_status: 'Available',
        status: 'Active',
        eligible: true,
        eligibility_reason: '',
        origin_terminal_id: 1,
        origin_terminal: 'Rizal Central Terminal',
        destination_terminal_id: 2,
        destination_terminal: 'Cabanatuan Central Terminal',
        effective_terminal_id: 1,
        effective_terminal: 'Rizal Central Terminal',
        dispatch_origin_terminal_id: 1,
        dispatch_origin_terminal: 'Rizal Central Terminal',
        receiving_terminal_id: 2,
        receiving_terminal: 'Cabanatuan Central Terminal',
        terminal_id: null,
        terminal: null,
        current_location: 'At Rizal Central Terminal',
        assigned_driver: {
            driver_id: null, full_name: null, status: '', availability: '', eligible: true, reason: '',
            terminal_id: null, terminal: null, current_terminal_id: null, current_terminal: null, receiving_terminal: null
        },
        trip_status: 'Idle',
        active_trip: null,
        trips_today: 0
    }, overrides);
}

let entries = [
    busItem('Bus-1001', {
        plate_number: 'ABC-1001',
        trip_status: 'On Trip',
        active_trip: {id: 91, trip_number: 1, status: 'Departed', departure_time: '06:00:00'},
        trips_today: 1,
        current_location: 'En route: Rizal Central Terminal → Cabanatuan Central Terminal'
    }),
    busItem('BUS-2001', {
        plate_number: 'NGP-2212',
        origin_terminal_id: 2,
        origin_terminal: 'Cabanatuan Central Terminal',
        destination_terminal_id: 1,
        destination_terminal: 'Rizal Central Terminal',
        effective_terminal_id: 2,
        effective_terminal: 'Cabanatuan Central Terminal',
        dispatch_origin_terminal_id: 2,
        dispatch_origin_terminal: 'Cabanatuan Central Terminal',
        receiving_terminal_id: 1,
        receiving_terminal: 'Rizal Central Terminal',
        current_location: 'At Cabanatuan Central Terminal',
        assigned_driver: {driver_id: 'DRV-001', full_name: 'Juan Dela Cruz', status: 'Active', availability: 'Available', eligible: true, reason: ''}
    })
];

let unavailable = [
    busItem('BUS-2002', {
        plate_number: 'NGP-3385',
        operational_status: 'Under Maintenance',
        eligible: false,
        eligibility_reason: 'Bus is "Under Maintenance" and cannot be dispatched.',
        trip_status: 'Completed 1 trip',
        trips_today: 1
    })
];

const drivers = [
    {driver_id: 'DRV-001', full_name: 'Juan Dela Cruz', status: 'Active', availability: 'Available', eligible: true, reason: '', assigned_bus: 'BUS-2001', assigned_buses: ['BUS-2001'], occupied: true, occupied_bus: 'BUS-2001', occupied_reason: 'Driver is assigned to bus BUS-2001 until that trip completes and the arrival at the receiving terminal is confirmed.'},
    {driver_id: 'DRV-002', full_name: 'Pedro Reyes', status: 'Active', availability: 'On Leave', eligible: false, reason: 'Driver is "On Leave".', assigned_bus: null, assigned_buses: [], occupied: false, occupied_bus: null, occupied_reason: null},
    {driver_id: 'DRV-003', full_name: 'Ana Santos', status: 'Active', availability: 'Available', eligible: true, reason: '', assigned_bus: null, assigned_buses: [], occupied: false, occupied_bus: null, occupied_reason: null},
    {driver_id: 'DRV-004', full_name: 'Carla Reyes', status: 'Active', availability: 'Available', eligible: true, reason: '', assigned_bus: 'BUS-9001', assigned_buses: ['BUS-9001'], occupied: false, occupied_bus: null, occupied_reason: null}
];

function summary() {
    return {
        total: entries.length + unavailable.length,
        queued: entries.length,
        assigned: entries.filter(e => e.assigned_driver && e.assigned_driver.driver_id).length,
        unassigned: entries.filter(e => !(e.assigned_driver && e.assigned_driver.driver_id)).length,
        on_trip: entries.concat(unavailable).filter(b => b.active_trip !== null).length,
        maintenance: unavailable.length
    };
}

function payload() {
    return {
        operating_date: activeDate,
        published: publishedDates[activeDate] ? {at: publishedDates[activeDate], by: 'root'} : null,
        entries: entries.map((entry, index) => Object.assign({}, entry, {queue_position: index + 1})),
        unavailable: unavailable.map(item => Object.assign({}, item)),
        terminals: [
            {id: 2, name: 'Cabanatuan Central Terminal'},
            {id: 1, name: 'Rizal Central Terminal'}
        ],
        drivers: drivers.map(driver => Object.assign({}, driver)),
        summary: summary()
    };
}

/* ------------------------------ fetch router ------------------------------ */

function jsonResponse(data, status) {
    const code = status || 200;
    return {
        ok: code < 400,
        status: code,
        json: async () => data,
        text: async () => JSON.stringify(data)
    };
}

async function fetchMock(url, opts) {
    const options = opts || {};
    const body = options.body ? JSON.parse(options.body) : null;
    fetchLog.push({url: String(url), method: options.method || 'GET', body});

    if (String(url).indexOf('/admin/api/Dispatch/queue.php') === 0) {
        const asked = String(url).match(/[?&]date=([0-9-]+)/);
        if (asked) activeDate = asked[1];
        return jsonResponse({success: true, data: payload()});
    }
    if (String(url).indexOf('/admin/api/Dispatch/history.php') === 0) {
        return jsonResponse({success: true, data: {
            operating_date: '2026-10-14',
            activity: [
                {action: 'Queue Published', description: 'Official dispatch order published for 2026-10-14 (2 buses).', username: 'root', created_at: '2026-10-14 06:00:00'},
                {action: 'Queue Override', description: 'Queue override for 2026-10-14: Bus BUS-2001 moved from position #1 to #2. Reason: Depot departure delayed.', username: 'root', created_at: '2026-10-14 05:59:00'}
            ],
            published_dates: [{operating_date: '2026-10-13', published_at: '2026-10-13 05:55:00', published_by: 'root', buses: 2}]
        }});
    }
    if (String(url).indexOf('/admin/api/Dispatch/save_queue.php') === 0) {
        saveCalls.push(body);
        const byId = {};
        entries.forEach(item => { byId[item.bus_id] = item; });
        entries = body.order.map(id => byId[id]).filter(Boolean).map(item => Object.assign({}, item, {persisted: true}));
        delete publishedDates[activeDate];
        return jsonResponse({success: true, message: 'Queue saved as a draft.', data: payload()});
    }
    if (String(url).indexOf('/admin/api/Dispatch/publish.php') === 0) {
        if (publishBlocked) {
            return jsonResponse({
                success: false,
                message: 'The queue cannot be published while buses are unavailable.',
                blockers: ['Bus BUS-2002: Bus is "Under Maintenance" and cannot be dispatched.']
            }, 409);
        }
        publishedDates[activeDate] = '2026-10-14 06:00:00';
        return jsonResponse({success: true, message: 'Official dispatch order published.', data: payload()});
    }
    if (String(url).indexOf('/admin/api/Dispatch/assign_driver.php') === 0) {
        assignCalls.push(body);
        if (assignRejection) {
            return jsonResponse({success: false, code: 'assignment_blocked', message: assignRejection}, 409);
        }
        const driver = drivers.find(item => item.driver_id === body.driver_id) || null;
        const otherBuses = (driver && driver.assigned_buses ? driver.assigned_buses : []).filter(bus => bus !== body.bus_id);
        if (otherBuses.length && body.allow_move !== true) {
            return jsonResponse({
                success: false,
                code: 'driver_conflict',
                conflict_bus: otherBuses[0],
                message: 'This driver is already assigned to bus ' + otherBuses[0] + '. Confirm to move the assignment.'
            }, 409);
        }
        const previous = entries.find(item => item.bus_id === body.bus_id);
        if (previous && previous.assigned_driver && previous.assigned_driver.driver_id && previous.assigned_driver.driver_id !== body.driver_id) {
            const released = drivers.find(item => item.driver_id === previous.assigned_driver.driver_id) || null;
            if (released) {
                released.assigned_bus = null;
                released.assigned_buses = [];
                released.occupied = false;
                released.occupied_bus = null;
            }
        }
        otherBuses.forEach(bus => {
            entries = entries.map(item => item.bus_id === bus
                ? Object.assign({}, item, {assigned_driver: {driver_id: null, full_name: null, status: '', availability: '', eligible: true, reason: ''}})
                : item);
        });
        if (driver) {
            driver.assigned_bus = body.bus_id;
            driver.assigned_buses = [body.bus_id];
            driver.occupied = true;
            driver.occupied_bus = body.bus_id;
        }
        entries = entries.map(item => item.bus_id === body.bus_id
            ? Object.assign({}, item, {
                assigned_driver: {driver_id: body.driver_id, full_name: driver ? driver.full_name : body.driver_id, status: 'Active', availability: 'Available', eligible: true, reason: ''}
            })
            : item);
        return jsonResponse({success: true, message: 'Driver assignment saved.', data: payload()});
    }
    if (String(url).indexOf('/admin/api/Dispatch/assign_terminal.php') === 0) {
        terminalCalls.push(body);
        if (terminalRejection) {
            return jsonResponse({success: false, message: terminalRejection}, 409);
        }
        const target = entries.find(item => item.bus_id === body.bus_id);
        if (target) {
            const busTerminal = terminalById(body.terminal_id);
            target.terminal_id = body.terminal_id;
            target.terminal = busTerminal ? busTerminal.name : null;
            target.effective_terminal_id = body.terminal_id;
            target.effective_terminal = busTerminal ? busTerminal.name : null;
            target.dispatch_origin_terminal_id = body.terminal_id;
            target.dispatch_origin_terminal = busTerminal ? busTerminal.name : null;
            if (target.origin_terminal_id === body.terminal_id) {
                target.receiving_terminal_id = target.destination_terminal_id;
                target.receiving_terminal = target.destination_terminal;
            } else if (target.destination_terminal_id === body.terminal_id) {
                target.receiving_terminal_id = target.origin_terminal_id;
                target.receiving_terminal = target.origin_terminal;
            }
            const driverId = target.assigned_driver ? target.assigned_driver.driver_id : null;
            if (driverId) {
                const driverTerminalId = body.driver_terminal_id || body.terminal_id;
                const driverTerminal = terminalById(driverTerminalId);
                target.assigned_driver.terminal_id = driverTerminalId;
                target.assigned_driver.terminal = driverTerminal ? driverTerminal.name : null;
                target.assigned_driver.current_terminal_id = driverTerminalId;
                target.assigned_driver.current_terminal = driverTerminal ? driverTerminal.name : null;
                const driver = drivers.find(item => item.driver_id === driverId) || null;
                if (driver) {
                    driver.terminal_id = driverTerminalId;
                    driver.terminal = driverTerminal ? driverTerminal.name : null;
                }
            }
        }
        return jsonResponse({success: true, message: 'Terminal assignment saved.', data: payload()});
    }
    if (String(url).indexOf('/admin/api/Dispatch/override.php') === 0) {
        overrideCalls.push(body);
        const next = entries.map(item => item.bus_id).filter(id => id !== body.bus_id);
        next.splice(Math.max(0, body.position - 1), 0, body.bus_id);
        const byId = {};
        entries.forEach(item => { byId[item.bus_id] = item; });
        entries = next.map(id => byId[id]).filter(Boolean);
        delete publishedDates[activeDate];
        return jsonResponse({success: true, message: 'Queue position overridden.', data: payload()});
    }
    if (String(url).indexOf('/admin/api/Dispatch/set_availability.php') === 0) {
        availabilityCalls.push(body);
        const all = entries.concat(unavailable);
        const target = all.find(item => item.bus_id === body.bus_id);
        if (target) {
            target.operational_status = body.operational_status;
            const blocked = body.operational_status !== 'Available';
            target.eligible = !blocked;
            target.eligibility_reason = blocked ? 'Bus is "' + body.operational_status + '" and cannot be dispatched.' : '';
            if (blocked) {
                entries = entries.filter(item => item !== target);
                unavailable = unavailable.concat([target]);
            } else {
                unavailable = unavailable.filter(item => item !== target);
                if (entries.indexOf(target) === -1) entries.push(target);
            }
        }
        return jsonResponse({success: true, message: 'Bus availability updated.', data: payload()});
    }

    if (String(url).indexOf('/admin/api/RoleManagement/Security.php') === 0) {
        if (String(url).indexOf('action=status') !== -1) {
            return jsonResponse({success: true, configured: true, is_super: true, locked_until: null});
        }
        if (String(url).indexOf('action=verify') !== -1) {
            verifyCalls.push(body);
            return jsonResponse({success: true, token: 'sec-token-' + verifyCalls.length, expires_in: 300, action: 'publish', target: 'dispatch'});
        }
        return jsonResponse({success: false, message: 'Unexpected security action.'}, 400);
    }

    throw new Error('unexpected fetch ' + url);
}

/* -------------------------------- mini DOM -------------------------------- */

const nodes = new Map();

function makeNode(id) {
    const node = {
        id,
        hidden: false,
        disabled: false,
        value: '',
        textContent: '',
        max: '',
        _html: '',
        _attrs: {},
        listeners: {},
        classList: {add() {}, remove() {}, toggle() {}, contains() { return false; }},
        setAttribute(name, value) { node._attrs[name] = String(value); },
        getAttribute(name) { return node._attrs[name] === undefined ? null : node._attrs[name]; },
        removeAttribute(name) { delete node._attrs[name]; },
        addEventListener(name, fn) { node.listeners[name] = fn; },
        querySelector() { return null; },
        querySelectorAll() { return []; },
        closest() { return null; },
        focus() {},
        remove() {},
        click() {}
    };
    Object.defineProperty(node, 'innerHTML', {
        get() { return node._html; },
        set(value) { node._html = String(value); }
    });
    return node;
}

function element(id) {
    if (!nodes.has(id)) nodes.set(id, makeNode(id));
    return nodes.get(id);
}

const documentMock = {
    getElementById: element,
    readyState: 'complete',
    body: {appendChild() {}},
    addEventListener() {},
    querySelector() { return null; },
    querySelectorAll() { return []; },
    createElement(tag) { return makeNode(String(tag) + ':created'); }
};

/* ------------------------------ library mocks ------------------------------ */

const bootstrapMock = {
    Modal: {
        getOrCreateInstance(node) {
            return {
                show() { node.hidden = false; },
                hide() { node.hidden = true; }
            };
        },
        getInstance() { return null; }
    }
};

const windowMock = {
    DQ_CONFIG: {today: '2026-10-14', isSuper: true, user: 'root'},
    showAlert(kind, title, text) { alerts.push({kind, title, text}); },
    showConfirm(title, text, onConfirm, onCancel) {
        confirms.push({title, text, onConfirm, onCancel});
        if (!deferConfirms && typeof onConfirm === 'function') onConfirm();
    },
    location: {search: ''},
    history: {replaceState() {}},
    open() {
        const popup = {html: '', printed: false, closed: false, focused: false};
        popup.document = {write(chunk) { popup.html += String(chunk); }, close() {}};
        popup.focus = function () { popup.focused = true; };
        popup.print = function () { popup.printed = true; };
        popup.close = function () { popup.closed = true; };
        popups.push(popup);
        return popup;
    }
};

const sandbox = {
    window: windowMock,
    document: documentMock,
    bootstrap: bootstrapMock,
    fetch: fetchMock,
    console,
    setTimeout,
    clearTimeout,
    Promise,
    Date,
    JSON
};
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync('admin/assets/js/dispatch-queue.js', 'utf8'), sandbox);

/* --------------------------------- helpers --------------------------------- */

async function settle(times = 12) {
    for (let i = 0; i < times; i += 1) await new Promise(resolve => setImmediate(resolve));
}

function clickAction(action, busId) {
    element('dqList').listeners.click({
        target: {
            closest(selector) {
                if (selector !== '[data-action]') return null;
                return {
                    getAttribute(name) {
                        if (name === 'data-action') return action;
                        if (name === 'data-bus') return busId;
                        return null;
                    }
                };
            }
        }
    });
}

function dragStub(busId) {
    return {
        getAttribute(name) { return name === 'data-bus' ? busId : null; },
        classList: {add() {}, remove() {}, contains() { return false; }}
    };
}

function dragEvent(busId) {
    return {
        target: {closest: selector => (selector === '[data-entry]' ? dragStub(busId) : null)},
        preventDefault() {},
        dataTransfer: {setData() {}, effectAllowed: '', dropEffect: ''}
    };
}

function lastAlert() {
    return alerts[alerts.length - 1] || null;
}

/* ---------------------------------- tests ---------------------------------- */

(async () => {
    await settle();

    assert.match(element('dqList').innerHTML, /rmg-table/, 'The queue table renders');
    assert.match(element('dqSummary').innerHTML, /In Queue/, 'Summary cards render');
    assert.match(element('dqCount').innerHTML, /2 queued \/ 3 valid buses/, 'Header count reflects the numbered queue');
    assert.match(element('dqStateChip').innerHTML, /Draft - not published/, 'Unpublished queue state is shown');
    assert.match(element('dqList').innerHTML, /<strong>#1<\/strong>[\s\S]*Bus-1001/, 'Every queue row carries a real position number');
    assert.match(element('dqList').innerHTML, /<strong>#2<\/strong>[\s\S]*BUS-2001/, 'Positions are numbered without gaps');
    assert.doesNotMatch(element('dqList').innerHTML, /rmg-missing">—</, 'The queue table shows no dash placeholders');
    assert.match(element('dqList').innerHTML, /Juan Dela Cruz/, 'Assigned driver renders in the queue');
    assert.match(element('dqList').innerHTML, /On Trip/, 'Active trip status renders');
    assert.match(element('dqTerminal').innerHTML, /Rizal Central Terminal/, 'The terminal filter lists the terminals');
    assert.match(element('dqTerminal').innerHTML, /Cabanatuan Central Terminal/, 'The terminal filter lists both terminals');
    assert.match(element('dqList').innerHTML, /data-label="Terminal"/, 'The queue table shows a Terminal column');
    assert.match(element('dqList').innerHTML, /Cabanatuan Central Terminal/, 'The row shows the current terminal');
    assert.match(element('dqList').innerHTML, /Rizal Central Terminal \u2192 Cabanatuan Central Terminal/, 'The row shows the dispatch origin and receiving terminal');
    assert.doesNotMatch(element('dqList').innerHTML, /En route: Rizal Central Terminal/, 'Current-location terminal text is no longer rendered in the queue');
    assert.doesNotMatch(element('dqList').innerHTML, /data-action="add"/, 'There is no add-to-queue action');
    assert.doesNotMatch(element('dqList').innerHTML, /data-action="remove"/, 'There is no remove-from-queue action');
    assert.match(element('dqList').innerHTML, /draggable="true"/, 'Queue rows are draggable');
    assert.match(element('dqList').innerHTML, /Unavailable buses \(1\)/, 'Unavailable buses are reported separately');
    assert.match(element('dqList').innerHTML, /Under Maintenance/, 'Unavailable reasons render');

    /* ----------------------------- actions column ----------------------------- */

    const queueHtml = element('dqList').innerHTML;
    assert.match(queueHtml, /<colgroup><col class="dq-col-pos"><col class="dq-col-bus"><col class="dq-col-type"><col class="dq-col-driver"><col class="dq-col-terminal"><col class="dq-col-availability"><col class="dq-col-trip"><col class="dq-col-actions"><\/colgroup>/, 'The queue table declares the eight-column budget');
    assert.match(queueHtml, /<th class="rmg-th-actions">Actions<\/th>/, 'The Actions header keeps its dedicated class');
    assert.equal((queueHtml.match(/class="rmg-td-actions"/g) || []).length, entries.length, 'Every queue row keeps an Actions cell');
    assert.equal((queueHtml.match(/class="rmg-actions"/g) || []).length, entries.length, 'Every queue row keeps a single action group');
    assert.equal((queueHtml.match(/class="rmg-icon-btn is-edit" data-action="assign"/g) || []).length, entries.length, 'Every queue row keeps the assign action');
    assert.equal((queueHtml.match(/class="rmg-icon-btn is-edit" data-action="terminal"/g) || []).length, entries.length, 'Every queue row keeps the assign-terminal action');
    assert.equal((queueHtml.match(/class="rmg-icon-btn is-view" data-action="override"/g) || []).length, entries.length, 'Every queue row keeps the set-position action');
    assert.equal((queueHtml.match(/class="rmg-icon-btn is-archive" data-action="availability"/g) || []).length, entries.length, 'Every queue row keeps the set-availability action');

    /* ------------------------------ history dates ------------------------------ */

    element('dqDate').value = '2026-10-13';
    element('dqDate').listeners.change();
    await settle();
    assert.match(element('dqList').innerHTML, /rmg-table/, 'A past operating date still loads its queue');
    assert.match(element('dqDateStatus').innerHTML, /History - October 13, 2026 - read-only/, 'The date filter marks the history date as read-only');
    assert.match(element('dqHistoryNotice').innerHTML, /Showing history for October 13, 2026/, 'A history notice explains the read-only rule');
    assert.equal(element('dqHistoryNotice').hidden, false, 'The history notice is visible');
    assert.match(element('dqStateChip').innerHTML, /History - read-only/, 'The state chip marks the date as history');
    assert.match(element('dqPublishBtn').innerHTML, /History date - cannot publish/, 'The publish button says the history date cannot be published');
    assert.equal(element('dqPublishBtn').disabled, true, 'Publishing is disabled for a history date');
    assert.equal(element('dqSaveBtn').disabled, true, 'Saving is disabled for a history date');
    assert.doesNotMatch(element('dqList').innerHTML, /draggable="true"/, 'History rows are not draggable');

    const alertsBeforeHistory = alerts.length;
    const publishesBeforeHistory = fetchLog.filter(entry => entry.url.indexOf('publish.php') !== -1).length;
    element('dqPublishBtn').listeners.click();
    await settle(2);
    assert.equal(alerts.length, alertsBeforeHistory + 1, 'Publishing a history date explains why it is blocked');
    assert.equal(lastAlert().kind, 'error', 'The history publish attempt surfaces an error');
    assert.equal(fetchLog.filter(entry => entry.url.indexOf('publish.php') !== -1).length, publishesBeforeHistory, 'Publishing a history date never reaches the server');

    clickAction('assign', 'Bus-1001');
    await settle(2);
    assert.equal(element('dqAssignBus').innerHTML, '', 'History mode does not open the assign dialog');
    assert.match(lastAlert().text, /read-only/i, 'The blocked action explains the read-only rule');

    element('dqList').listeners.dragstart(dragEvent('BUS-2001'));
    element('dqList').listeners.drop(dragEvent('Bus-1001'));
    await settle(2);
    assert.match(element('dqList').innerHTML, /<strong>#1<\/strong>[\s\S]*Bus-1001/, 'History queues cannot be reordered');

    element('dqDate').value = '2026-10-14';
    element('dqDate').listeners.change();
    await settle();
    assert.equal(element('dqPublishBtn').disabled, false, 'Returning to today enables publishing again');
    assert.match(element('dqPublishBtn').innerHTML, /Publish queue/, 'The publish button label is restored');
    assert.equal(element('dqHistoryNotice').hidden, true, 'The history notice is hidden on today');
    assert.match(element('dqList').innerHTML, /draggable="true"/, 'Today rows are draggable again');
    assert.match(element('dqDateStatus').innerHTML, /Today - October 14, 2026/, 'The date filter marks today');

    /* ------------------------------ drag and drop ------------------------------ */

    element('dqList').listeners.dragstart(dragEvent('BUS-2001'));
    element('dqList').listeners.drop(dragEvent('Bus-1001'));
    await settle(2);
    assert.match(element('dqList').innerHTML, /<strong>#1<\/strong>[\s\S]*BUS-2001/, 'Dragging a row onto another reorders the queue');
    assert.match(element('dqStateChip').innerHTML, /Unsaved changes/, 'Dragging marks the queue dirty');

    element('dqSaveBtn').listeners.click();
    await settle();
    assert.equal(saveCalls.length, 1, 'Save draft posts once');
    assert.deepEqual(saveCalls[0].order, ['BUS-2001', 'Bus-1001'], 'Save draft posts the dragged order');
    assert.equal(saveCalls[0].date, '2026-10-14', 'Save draft posts the operating date');
    assert.match(element('dqStateChip').innerHTML, /Draft - not published/, 'Saving clears the dirty state');

    /* ----------------------------- driver assignment ----------------------------- */

    clickAction('assign', 'Bus-1001');
    await settle(2);
    assert.equal(element('dqAssignModal').hidden, false, 'Assign driver opens the modal');
    assert.match(element('dqAssignBus').innerHTML, /Bus-1001/, 'Assign modal names the bus');
    assert.doesNotMatch(element('dqAssignDriver').innerHTML, /DRV-001/, 'A driver with an active assignment is removed from the available list');
    assert.match(element('dqAssignDriver').innerHTML, /value="DRV-004">Carla Reyes \(DRV-004\) · Assigned to BUS-9001/, 'A driver bound outside the queue is listed with its recorded bus shown');
    assert.match(element('dqAssignDriver').innerHTML, /value="DRV-003">Ana Santos \(DRV-003\)<\/option>/, 'An unassigned driver is listed without an assignment label');
    assert.doesNotMatch(element('dqAssignDriver').innerHTML, /DRV-002/, 'Ineligible drivers are removed from the available list');
    assert.match(element('dqAssignHint').textContent, /Only eligible, unassigned drivers at this terminal/, 'The hint explains the availability rule');
    element('dqAssignDriver').value = 'DRV-003';
    element('dqAssignSave').listeners.click();
    await settle();
    assert.equal(assignCalls.length, 1, 'Assignment posts once');
    assert.equal(assignCalls[0].driver_id, 'DRV-003', 'The chosen driver is posted');
    assert.equal(element('dqAssignModal').hidden, true, 'Successful assignment closes the modal');
    assert.match(lastAlert().title, /Driver assignment saved/, 'Assignment confirms success');

    /* The just-assigned driver disappears from every other bus's list at once. */
    clickAction('assign', 'BUS-2001');
    await settle(2);
    assert.match(element('dqAssignDriver').innerHTML, /value="DRV-001" selected>Juan Dela Cruz/, 'The bus\'s current driver stays selectable and selected');
    assert.doesNotMatch(element('dqAssignDriver').innerHTML, /DRV-003/, 'The assigned driver leaves other buses\' lists without a reload');
    element('dqAssignModal').hidden = true;

    /* Completing the trip and confirming the arrival releases the driver. */
    const releasedDriver = drivers.find(item => item.driver_id === 'DRV-003');
    releasedDriver.occupied = false;
    releasedDriver.occupied_bus = null;
    element('dqRefresh').listeners.click();
    await settle();
    clickAction('assign', 'BUS-2001');
    await settle(2);
    assert.match(element('dqAssignDriver').innerHTML, /value="DRV-003">Ana Santos \(DRV-003\)/, 'A released driver reappears in the available list');
    element('dqAssignModal').hidden = true;

    /* -------------------- conflict that arises after the modal opened -------------------- */

    clickAction('assign', 'Bus-1001');
    await settle(2);
    assert.match(element('dqAssignDriver').innerHTML, /value="DRV-004">Carla Reyes \(DRV-004\) · Assigned to BUS-9001/, 'A released driver bound elsewhere keeps its assignment label');
    element('dqAssignDriver').value = 'DRV-004';

    deferConfirms = true;
    element('dqAssignSave').listeners.click();
    await settle();
    assert.equal(assignCalls.length, 2, 'The conflicting assignment posts once before confirmation');
    assert.equal(assignCalls[1].allow_move, undefined, 'The first attempt does not move the driver');
    assert.equal(confirms[confirms.length - 1].title, 'Move driver assignment?', 'The conflict asks for confirmation');
    assert.match(confirms[confirms.length - 1].text, /BUS-9001/, 'The confirmation names the driver\'s current bus');
    assert.equal(element('dqAssignModal').hidden, false, 'The modal stays open until the move is confirmed');

    confirms[confirms.length - 1].onConfirm();
    await settle();
    deferConfirms = false;
    assert.equal(assignCalls.length, 3, 'The confirmed move posts the retry');
    assert.equal(assignCalls[2].allow_move, true, 'The retry carries the move flag');
    assert.equal(element('dqAssignModal').hidden, true, 'A confirmed move closes the modal');
    assert.match(lastAlert().title, /Driver assignment saved/, 'The move confirms success');
    assert.match(element('dqList').innerHTML, /Bus-1001[\s\S]*Carla Reyes/, 'The moved driver renders on the target bus');

    /* ----------------------- cancelling a move changes nothing ----------------------- */

    const releasedForCancel = drivers.find(item => item.driver_id === 'DRV-001');
    releasedForCancel.occupied = false;
    releasedForCancel.occupied_bus = null;
    element('dqRefresh').listeners.click();
    await settle();
    clickAction('assign', 'Bus-1001');
    await settle(2);
    assert.match(element('dqAssignDriver').innerHTML, /value="DRV-001">Juan Dela Cruz \(DRV-001\) · Assigned to BUS-2001/, 'A released driver is listed with the bus it is bound to');
    element('dqAssignDriver').value = 'DRV-001';
    deferConfirms = true;
    element('dqAssignSave').listeners.click();
    await settle();
    assert.equal(assignCalls.length, 4, 'The cancelled move posts once before confirmation');
    confirms[confirms.length - 1].onCancel();
    await settle();
    deferConfirms = false;
    assert.equal(assignCalls.length, 4, 'Cancelling posts nothing further');
    assert.equal(element('dqAssignModal').hidden, false, 'Cancelling keeps the modal open');
    assert.equal(element('dqAssignDriver').value, 'DRV-001', 'Cancelling keeps the selection');
    assert.equal(element('dqAssignSave').disabled, false, 'Cancelling re-enables the save button');

    /* ----------------------- rejected assignment keeps the modal ----------------------- */

    assignRejection = 'Bus BUS-2001 has an active trip and its driver cannot be changed now.';
    element('dqAssignSave').listeners.click();
    await settle();
    assert.equal(assignCalls.length, 5, 'The rejected assignment posts once');
    assert.equal(element('dqAssignError').hidden, false, 'The rejection is shown in the modal');
    assert.match(element('dqAssignError').textContent, /active trip/, 'The server reason is displayed');
    assert.equal(element('dqAssignModal').hidden, false, 'The modal stays open after a rejection');
    assert.equal(assignCalls[4].allow_move, undefined, 'A plain rejection is never retried as a move');
    assignRejection = null;
    element('dqAssignModal').hidden = true;

    /* ------------------------------- override ------------------------------- */

    clickAction('override', 'BUS-2001');
    await settle(2);
    assert.equal(element('dqOverrideModal').hidden, false, 'Override opens the modal');
    element('dqOverridePosition').value = '2';
    element('dqOverrideReason').value = 'Depot departure delayed.';
    element('dqOverrideSave').listeners.click();
    await settle();
    assert.equal(overrideCalls.length, 1, 'Override posts once');
    assert.equal(overrideCalls[0].position, 2, 'Override posts the requested position');
    assert.equal(overrideCalls[0].reason, 'Depot departure delayed.', 'Override posts the reason');
    assert.match(element('dqList').innerHTML, /<strong>#2<\/strong>[\s\S]*BUS-2001/, 'Override reorders the rendered queue');
    assert.match(element('dqStateChip').innerHTML, /Draft - not published/, 'An override invalidates the publication');

    /* ----------------------------- availability action ----------------------------- */

    assert.match(element('dqList').innerHTML, /data-action="availability" data-bus="BUS-2001"/, 'Queue rows expose the availability action');

    clickAction('availability', 'BUS-2001');
    await settle(2);
    assert.equal(element('dqAvailabilityModal').hidden, false, 'The availability action opens the modal');
    assert.match(element('dqAvailabilityBus').innerHTML, /BUS-2001/, 'The modal names the bus');
    assert.equal(element('dqAvailabilityStatus').value, 'Available', 'The current availability is preselected');
    element('dqAvailabilityStatus').value = 'Under Maintenance';
    element('dqAvailabilityReason').value = 'Brake pads worn, pulled from service.';
    element('dqAvailabilitySave').listeners.click();
    await settle();
    assert.equal(availabilityCalls.length, 1, 'The availability change posts once');
    assert.equal(availabilityCalls[0].operational_status, 'Under Maintenance', 'The chosen status is posted');
    assert.equal(availabilityCalls[0].reason, 'Brake pads worn, pulled from service.', 'The reason is posted');
    assert.match(element('dqList').innerHTML, /Unavailable buses \(2\)/, 'The marked bus moves to the unavailable list');
    assert.match(element('dqList').innerHTML, /data-action="availability" data-bus="BUS-2001"/, 'Unavailable rows expose the availability action too');

    clickAction('availability', 'BUS-2001');
    await settle(2);
    assert.equal(element('dqAvailabilityStatus').value, 'Under Maintenance', 'The current status is preselected when restoring');
    element('dqAvailabilityStatus').value = 'Available';
    element('dqAvailabilityReason').value = 'Repairs completed and road-tested.';
    element('dqAvailabilitySave').listeners.click();
    await settle();
    assert.equal(availabilityCalls.length, 2, 'Restoring availability posts');
    assert.match(element('dqList').innerHTML, /Unavailable buses \(1\)/, 'The restored bus leaves the unavailable list');

    /* -------------------------------- search -------------------------------- */

    element('dqSearch').value = '2001';
    element('dqSearch').listeners.input();
    await new Promise(resolve => setTimeout(resolve, 250));
    await settle(2);
    assert.match(element('dqList').innerHTML, /BUS-2001/, 'Search finds a bus by number');
    assert.doesNotMatch(element('dqList').innerHTML, /Bus-1001<\/div>/, 'Search hides non-matching buses');

    element('dqClearFilters').listeners.click();
    await settle(2);
    assert.match(element('dqList').innerHTML, /Bus-1001/, 'Clearing filters restores the queue view');

    /* ------------------------------ terminal filter ------------------------------ */

    element('dqTerminal').value = '2';
    element('dqTerminal').listeners.change();
    await settle(2);
    assert.match(element('dqList').innerHTML, /BUS-2001/, 'The terminal filter keeps that terminal\'s buses in the queue');
    assert.doesNotMatch(element('dqList').innerHTML, /Bus-1001<\/div>/, 'The terminal filter hides the other terminal\'s buses');

    element('dqClearFilters').listeners.click();
    await settle(2);
    assert.match(element('dqList').innerHTML, /Bus-1001/, 'Clearing filters restores both terminals');

    /* ----------------------------- terminal assignment ----------------------------- */

    clickAction('terminal', 'Bus-1001');
    await settle(2);
    assert.equal(element('dqTerminalModal').hidden, false, 'The terminal action opens the assignment modal');
    assert.match(element('dqTerminalBus').innerHTML, /Bus-1001/, 'The terminal modal names the bus');
    assert.match(element('dqTerminalSelect').innerHTML, /Cabanatuan Central Terminal/, 'The bus terminal select lists the terminals');
    element('dqTerminalSelect').value = '2';
    element('dqTerminalSave').listeners.click();
    await settle();
    assert.equal(terminalCalls.length, 1, 'The terminal assignment posts once');
    assert.equal(terminalCalls[0].bus_id, 'Bus-1001', 'The assigned bus is posted');
    assert.equal(terminalCalls[0].terminal_id, 2, 'The chosen bus terminal is posted');
    assert.equal(terminalCalls[0].driver_id, 'DRV-004', 'The bus driver travels with the terminal assignment');
    assert.equal(terminalCalls[0].driver_terminal_id, undefined, 'No separate driver terminal is posted - the driver follows the bus terminal');
    assert.equal(element('dqTerminalModal').hidden, true, 'A saved assignment closes the modal');
    assert.match(lastAlert().title, /Terminal assignment saved/, 'The assignment confirms success');
    assert.match(element('dqList').innerHTML, /Cabanatuan Central Terminal/, 'The saved terminal renders on the queue row');

    terminalRejection = 'Bus BUS-2001 has an active trip; its terminal cannot be changed until the arrival is confirmed.';
    clickAction('terminal', 'BUS-2001');
    await settle(2);
    element('dqTerminalSelect').value = '1';
    element('dqTerminalSave').listeners.click();
    await settle();
    assert.equal(element('dqTerminalError').hidden, false, 'A rejected assignment is shown in the modal');
    assert.match(element('dqTerminalError').textContent, /active trip/, 'The server reason is displayed');
    assert.equal(element('dqTerminalModal').hidden, false, 'The modal stays open after a rejection');
    terminalRejection = null;
    element('dqTerminalModal').hidden = true;

    /* ------------- the assigned driver follows the bus terminal ------------- */

    const terminalScoped = drivers.find(item => item.driver_id === 'DRV-004');
    terminalScoped.occupied = false;
    terminalScoped.occupied_bus = null;
    element('dqRefresh').listeners.click();
    await settle();
    clickAction('assign', 'BUS-2001');
    await settle(2);
    assert.match(element('dqAssignDriver').innerHTML, /DRV-004/, 'The released driver follows the bus terminal and is offered at this terminal');
    element('dqAssignModal').hidden = true;

    /* -------------------------------- history -------------------------------- */

    element('dqHistoryBtn').listeners.click();
    await settle();
    assert.equal(element('dqHistoryModal').hidden, false, 'History opens the modal');
    assert.match(element('dqHistoryBody').innerHTML, /Queue Published/, 'History lists publications for the date');
    assert.match(element('dqHistoryBody').innerHTML, /Queue Override/, 'History lists overrides for the date');
    assert.match(element('dqHistoryBody').innerHTML, /Official dispatch order published/, 'History shows the recorded descriptions');
    assert.match(element('dqHistoryBody').innerHTML, /October 13, 2026/, 'History lists recently published orders');
    assert.match(element('dqHistoryBody').innerHTML, /2 buses/, 'Published orders carry the bus count');

    element('dqHistoryBody').listeners.click({
        target: {
            closest(selector) {
                if (selector !== '[data-action="load-date"]') return null;
                return {
                    getAttribute(name) {
                        if (name === 'data-action') return 'load-date';
                        if (name === 'data-date') return '2026-10-13';
                        return null;
                    }
                };
            }
        }
    });
    await settle();
    const loadedUrl = fetchLog.filter(entry => entry.url.indexOf('queue.php') !== -1).pop().url;
    assert.match(loadedUrl, /date=2026-10-13/, 'Loading a history date fetches that operating date');
    assert.equal(element('dqDate').value, '2026-10-13', 'The date picker follows the loaded history date');
    assert.equal(element('dqHistoryModal').hidden, true, 'Loading a history date closes the modal');

    /* --------------------------------- print --------------------------------- */

    element('dqPrintBtn').listeners.click();
    await settle(2);
    assert.equal(popups.length, 1, 'Print opens a print window');
    assert.match(popups[0].html, /R-Connect TMS - Official Dispatch Order/, 'Print copy carries the official title');
    assert.match(popups[0].html, /<strong>Bus-1001<\/strong>/, 'Print copy lists the numbered buses');
    assert.match(popups[0].html, /<td class="num">#1<\/td>/, 'Print copy shows the queue positions');
    assert.match(popups[0].html, /Assigned Driver/, 'Print copy includes the driver column');
    assert.match(popups[0].html, /<th>Terminal<\/th>/, 'Print copy includes the terminal column');
    assert.match(popups[0].html, /Cabanatuan Central Terminal/, 'Print copy shows the bus\'s current terminal');
    assert.match(popups[0].html, /DRAFT - not yet published/, 'An unpublished order prints as a draft');
    await new Promise(resolve => setTimeout(resolve, 500));
    assert.equal(popups[0].printed, true, 'The print window is sent to the printer');

    /* ----------------------------- publish (preview) ----------------------------- */

    element('dqDate').value = '2026-10-14';
    element('dqDate').listeners.change();
    await settle();
    assert.equal(element('dqPublishBtn').disabled, false, 'An unpublished date can be published');

    publishBlocked = true;
    element('dqSecurityModal').hidden = true;
    element('dqPublishBtn').listeners.click();
    await settle();
    assert.equal(element('dqPreviewModal').hidden, false, 'Publishing first opens the order preview');
    assert.match(element('dqPreviewBody').innerHTML, /BUS-2001|Bus-1001/, 'The preview lists the ordered buses');
    assert.match(element('dqPreviewBody').innerHTML, /<th>Terminal<\/th>/, 'The preview shows the terminal column');
    assert.equal(element('dqSecurityModal').hidden, true, 'The security modal waits for the preview to be confirmed');

    element('dqPreviewConfirm').listeners.click();
    await settle();
    assert.equal(element('dqSecurityModal').hidden, false, 'Confirming the preview opens the security authorization modal');
    assert.equal(verifyCalls.length, 0, 'The code is not verified before it is entered');
    element('dqSecurityCode').value = 'secret123';
    element('dqSecurityConfirm').listeners.click();
    await settle();
    assert.equal(verifyCalls.length, 1, 'Entering the code verifies once');
    assert.equal(verifyCalls[0].verify_action, 'publish', 'Verification is bound to the publish action');
    assert.equal(verifyCalls[0].target, 'dispatch', 'Verification is bound to the dispatch target');
    assert.match(String(verifyCalls[0].payload_hash), /^[a-f0-9]{64}$/, 'Verification carries the payload hash');
    assert.equal(lastAlert().kind, 'error', 'Blocked publishing surfaces an error');
    assert.match(lastAlert().text, /BUS-2002/, 'Blocked publishing lists the offending bus');
    assert.equal(element('dqPublishBtn').disabled, false, 'A blocked publish leaves the date editable');

    publishBlocked = false;
    element('dqPublishBtn').listeners.click();
    await settle();
    element('dqPreviewConfirm').listeners.click();
    await settle();
    element('dqSecurityCode').value = 'secret123';
    element('dqSecurityConfirm').listeners.click();
    await settle();
    assert.equal(verifyCalls.length, 2, 'Each publish requires its own security verification');
    assert.match(element('dqStateChip').innerHTML, /Published 2026-10-14 06:00:00 by root/, 'Publishing stamps the chip');
    assert.match(lastAlert().title, /published/i, 'Publishing confirms success');

    /* ------------------------ published date is locked ------------------------ */

    assert.equal(element('dqPublishBtn').disabled, true, 'A published date cannot be published again');
    assert.match(element('dqPublishBtn').innerHTML, /Published - locked/, 'The publish button shows the locked state');
    assert.equal(element('dqSaveBtn').disabled, true, 'Saving is disabled on a published date');
    assert.match(element('dqDateStatus').innerHTML, /Published - October 14, 2026 - locked/, 'The date filter marks the published date as locked');
    assert.equal(element('dqHistoryNotice').hidden, false, 'A locked notice explains the published rule');
    assert.match(element('dqHistoryNotice').innerHTML, /published and is locked/, 'The notice states the order is locked');
    assert.doesNotMatch(element('dqList').innerHTML, /draggable="true"/, 'Published rows are not draggable');

    const publishesBeforeLock = fetchLog.filter(entry => entry.url.indexOf('publish.php') !== -1).length;
    element('dqPublishBtn').listeners.click();
    await settle(2);
    assert.equal(fetchLog.filter(entry => entry.url.indexOf('publish.php') !== -1).length, publishesBeforeLock, 'Re-publishing a locked date never reaches the server');

    const assignsBeforeLock = fetchLog.filter(entry => entry.url.indexOf('assign_driver.php') !== -1).length;
    clickAction('assign', 'Bus-1001');
    await settle(2);
    assert.equal(element('dqAssignModal').hidden, true, 'A locked date does not open the assign dialog');
    assert.equal(fetchLog.filter(entry => entry.url.indexOf('assign_driver.php') !== -1).length, assignsBeforeLock, 'A locked date never posts an assignment');
    assert.match(lastAlert().text, /locked/i, 'The blocked action explains that the published date is locked');

    const terminalsBeforeLock = fetchLog.filter(entry => entry.url.indexOf('assign_terminal.php') !== -1).length;
    clickAction('terminal', 'Bus-1001');
    await settle(2);
    assert.equal(element('dqTerminalModal').hidden, true, 'A locked date does not open the terminal dialog');
    assert.equal(fetchLog.filter(entry => entry.url.indexOf('assign_terminal.php') !== -1).length, terminalsBeforeLock, 'A locked date never posts a terminal assignment');

    element('dqList').listeners.dragstart(dragEvent('BUS-2001'));
    element('dqList').listeners.drop(dragEvent('Bus-1001'));
    await settle(2);
    assert.match(element('dqList').innerHTML, /<strong>#2<\/strong>[\s\S]*BUS-2001|<strong>#1<\/strong>[\s\S]*Bus-1001/, 'A published queue cannot be reordered');
    assert.doesNotMatch(element('dqStateChip').innerHTML, /Unsaved changes/, 'A locked date cannot become dirty');

    /* --------------------- other operating dates stay independent --------------------- */

    element('dqDate').value = '2026-10-15';
    element('dqDate').listeners.change();
    await settle();
    assert.equal(element('dqPublishBtn').disabled, false, 'Another operating date is not locked by the published one');
    assert.match(element('dqList').innerHTML, /draggable="true"/, 'Another operating date is still draggable');
    assert.equal(element('dqHistoryNotice').hidden, true, 'Another operating date shows no lock notice');

    element('dqDate').value = '2026-10-14';
    element('dqDate').listeners.change();
    await settle();
    assert.equal(element('dqPublishBtn').disabled, true, 'Returning to the published date restores its lock');
    assert.doesNotMatch(element('dqList').innerHTML, /draggable="true"/, 'The published date is still not draggable');

    /* --------- publishing a fresh unsaved queue persists the draft first --------- */

    element('dqDate').value = '2026-10-16';
    element('dqDate').listeners.change();
    await settle();
    delete publishedDates['2026-10-16'];
    entries = entries.map(item => Object.assign({}, item, {persisted: false}));
    element('dqRefresh').listeners.click();
    await settle();
    assert.match(element('dqStateChip').innerHTML, /Draft - not published/, 'The refreshed queue is an unsaved draft');

    const savesBefore = saveCalls.length;
    const publishesBefore = fetchLog.filter(entry => entry.url.indexOf('publish.php') !== -1).length;
    element('dqPublishBtn').listeners.click();
    await settle();
    element('dqPreviewConfirm').listeners.click();
    await settle();
    assert.equal(saveCalls.length, savesBefore + 1, 'Publishing an unsaved queue saves the draft first');
    await settle();
    assert.equal(element('dqSecurityModal').hidden, false, 'The security modal opens after the draft save');
    element('dqSecurityCode').value = 'secret123';
    element('dqSecurityConfirm').listeners.click();
    await settle();
    assert.equal(fetchLog.filter(entry => entry.url.indexOf('publish.php') !== -1).length, publishesBefore + 1, 'Publishing then posts the publish request');
    assert.match(element('dqStateChip').innerHTML, /Published/, 'The queue ends up published');

    console.log('Dispatch Queue UI checks passed: numbered queue, drag and drop, save, published-date lock (per date), publish (preview + security authorization, blocked + success + unsaved), driver availability (assigned drivers removed, released after arrival, move conflict), override, availability, search, terminal assignment, history, print.');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
