/*
 * Bus Management UI harness (tasks 5.2 - 5.7).
 *
 * Runs admin/assets/js/bus-management.js inside a vm context with a miniature
 * DOM, a mock fetch router, and mocked QRCode/jsPDF/bootstrap libraries, then
 * asserts the rendered markup and request flows for:
 *   5.2 summary cards, filters, table, sorting, pagination, skeleton, empty state
 *   5.3 add/edit wizard: preview ID, sections, validation, duplicate warning,
 *       unsaved-changes guard, duplicate-submit prevention, review refresh
 *   5.4 detail profile: six tabs for a full and a sparse bus
 *   5.4+5.5 QR panel render, downloads (PNG/PDF), print, prepare, replace, revoke
 *   5.6 bulk QR listing, backfill, printable PDF
 *   5.7 CSV export (filtered + empty), archived modal restore, notifications,
 *       accessible action labels, security settings modal
 *
 *   node admin/tests/bus-management_ui_test.cjs [--deep-link]
 */

const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const deepLink = process.argv.includes('--deep-link');

/* ------------------------------- mock state ------------------------------- */

let listMode = 'normal'; // normal | empty | many
let qrMode = 'active'; // active | missing
let duplicateMode = 'none'; // none | dup
let exportMode = 'csv'; // csv | json
let driverConflictOnce = false;
let addedBus = false;
let bulkMissing = 1;
let confirmInputValue = 'Corrected per inspection report.';
let confirmCancelNext = false;
let securityConfigured = true;
const archivedRefs = {};
const restoreApplied = {};
const wrongCode = '999999';

const createdLinks = [];
const alerts = [];
const confirms = [];
const qrRenders = [];
const pdfInstances = [];
const pdfSaves = [];
const popups = [];
const html2canvasCalls = [];
const urlWrites = [];
const fetchLog = [];
const addCalls = [];
const editCalls = [];
const archiveCalls = [];
const restoreCalls = [];
const qrReplaceCalls = [];
const duplicateChecks = [];
const exportUrls = [];
let backfillRuns = 0;

/* ------------------------------ mock data ------------------------------ */

function busItem(overrides) {
    return Object.assign({
        ref: 1,
        bus_id: 'BUS-001',
        plate: 'NCR 1234',
        unit_number: '1201',
        bus_type: 'Standard',
        route_name: 'Cubao - Baguio',
        driver_ref: 'DRV-001',
        driver_name: 'Juan Dela Cruz',
        inspector_name: 'Ana Reyes',
        assigned_inspector_id: '1',
        seats: 49,
        standing_capacity: 10,
        manufacturer: 'Hino',
        model: 'RK',
        year_model: '2020',
        operational_status: 'Not Available',
        classification: 'active',
        classification_label: 'Active',
        raw_status: 'Active',
        status: 'Active',
        created_at: '2026-03-01 08:00:00',
        created_by: 'admin',
        updated_at: '2026-03-05 09:00:00',
        registration_month: 'February',
        registration_expiry: '2027-01-01',
        insurance_expiry: '2026-12-01',
        inspection_date: '2026-02-01',
        next_inspection_date: '2026-08-01',
        registration_state: 'valid',
        insurance_state: 'expiring',
        inspection_state: 'valid',
        from_terminal_id: '1',
        to_terminal_id: '2',
        schedule: '06:00 AM - 06:00 PM',
        accessible_features: 'Priority seats',
        can_manage: true
    }, overrides);
}

function items() {
    const rows = [
        busItem({}),
        busItem({
            ref: 2, bus_id: 'BUS-002', plate: 'NCR 5678', unit_number: '', bus_type: 'Deluxe',
            route_name: '', driver_ref: '', driver_name: '', inspector_name: '', assigned_inspector_id: '',
            classification: 'inactive', classification_label: 'Inactive', raw_status: 'Inactive',
            operational_status: 'Available',
            manufacturer: '', model: '', year_model: '', standing_capacity: 0, insurance_state: 'valid',
            registration_month: '',
            from_terminal_id: '', to_terminal_id: '', schedule: '', accessible_features: ''
        })
    ];
    if (addedBus) {
        rows.push(busItem({
            ref: 7, bus_id: 'BUS-007', plate: 'NCR 8888', unit_number: '777',
            classification: 'active', classification_label: 'Active', operational_status: ''
        }));
    }
    return rows.map(row => {
        if (archivedRefs[String(row.ref)]) {
            return Object.assign({}, row, {
                classification: 'archived', classification_label: 'Archived',
                raw_status: 'Archived', status: 'Archived', operational_status: ''
            });
        }
        return row;
    });
}

function summaryOf(rows) {
    const out = {total: rows.length, active: 0, inactive: 0, maintenance: 0, archived: 0};
    rows.forEach(row => {
        out[row.classification] = (out[row.classification] || 0) + 1;
    });
    return out;
}

/* ------------------------------ fetch router ------------------------------ */

function qp(url, key) {
    const idx = url.indexOf('?');
    if (idx === -1) return '';
    return new URLSearchParams(url.slice(idx + 1)).get(key) || '';
}

function jsonResponse(data, status) {
    const code = status || 200;
    return {
        ok: code < 400,
        status: code,
        headers: {get: () => 'application/json; charset=utf-8'},
        json: async () => data,
        text: async () => JSON.stringify(data)
    };
}

function csvResponse() {
    return {
        ok: true,
        status: 200,
        headers: {get: () => 'text/csv; charset=UTF-8'},
        blob: async () => new Blob(['Bus ID,Plate\nBUS-001,NCR 1234']),
        text: async () => 'Bus ID,Plate\nBUS-001,NCR 1234'
    };
}

function listResponse(url) {
    const params = new URLSearchParams(url.split('?')[1] || '');
    let rows;
    if (listMode === 'many') {
        rows = [];
        for (let i = 0; i < 30; i += 1) {
            rows.push(busItem({
                ref: 200 + i, bus_id: 'BUS-' + String(200 + i), plate: 'PLT ' + String(1000 + i),
                unit_number: String(500 + i), classification: 'active', classification_label: 'Active',
                operational_status: '', created_at: '2026-02-01 08:00:00'
            }));
        }
    } else {
        rows = items();
    }
    if (listMode === 'empty') rows = [];
    const status = params.get('status') || '';
    if (status) rows = rows.filter(row => row.classification === status);
    const search = (params.get('search') || '').toLowerCase();
    if (search) {
        rows = rows.filter(row => (row.bus_id + ' ' + row.plate + ' ' + row.unit_number).toLowerCase().indexOf(search) !== -1);
    }
    rows = rows.slice().sort((a, b) => String(a.bus_id).localeCompare(String(b.bus_id)));
    const perPage = parseInt(params.get('per_page') || '10', 10) || 10;
    const page = Math.max(1, parseInt(params.get('page') || '1', 10) || 1);
    const pages = Math.max(1, Math.ceil(rows.length / perPage));
    return {
        success: true,
        data: rows.slice((page - 1) * perPage, page * perPage),
        counts: {}, errors: {}, statuses: {}, filters: {},
        summary: summaryOf(rows),
        total: rows.length,
        page: Math.min(page, pages),
        per_page: perPage,
        pages,
        is_super: true
    };
}

async function fetchMock(url, opts) {
    const u = String(url);
    const options = opts || {};
    fetchLog.push({url: u, method: options.method || 'GET'});

    if (u.indexOf('/admin/api/RoleManagement/Security.php') === 0) {
        const action = qp(u, 'action');
        if (action === 'status') {
            return jsonResponse({
                success: true, configured: securityConfigured, locked_until: null,
                last_updated: {at: '2026-03-01 08:00:00', by: 1}
            });
        }
        if (action === 'activity') {
            return jsonResponse({
                success: true,
                events: [{event: 'verified', detail: 'Bus change verified', username: 'root', at: '2026-03-01 08:00:00'}]
            });
        }
        if (action === 'verify') {
            const body = JSON.parse(options.body || '{}');
            if (body.code === wrongCode) {
                return jsonResponse({success: false, code: 'incorrect_code', message: 'Incorrect Security Authorization Code.'});
            }
            return jsonResponse({success: true, token: 'tok-verify', expires_in: 300});
        }
        return jsonResponse({success: true, message: 'The Security Authorization Code was updated.'});
    }

    if (u.indexOf('/admin/api/Bus/list.php') === 0) return jsonResponse(listResponse(u));
    if (u.indexOf('/admin/api/Bus/NextBusId.php') === 0) return jsonResponse({success: true, bus_id: 'BUS-007', preview: true});

    if (u.indexOf('/admin/api/Bus/Options.php') === 0) {
        const type = qp(u, 'type');
        if (type === 'lists') {
            return jsonResponse({success: true, type: 'lists', lists: {
                types: ['Standard', 'Deluxe'],
                bus_type_capacities: {
                    'HINO Bus': {seats_default: 24, standing_default: 11, seats_min: 23, seats_max: 24, total_max: 35},
                    'Higer Bus': {seats_default: 26, standing_default: 14, seats_min: 26, seats_max: 26, total_max: 40}
                },
                fuel_types: ['Diesel', 'Gasoline'],
                transmissions: ['Manual', 'Automatic'],
                air_conditioning: ['Air-conditioned', 'Non-air-conditioned'],
                ownership: ['Company', 'Leased'],
                operational_statuses: ['Available', 'On Trip', 'Under Maintenance', 'Out of Service']
            }});
        }
        if (type === 'types') return jsonResponse({success: true, options: [{id: 'Standard', label: 'Standard'}, {id: 'Deluxe', label: 'Deluxe'}, {id: 'HINO Bus', label: 'HINO Bus'}, {id: 'Higer Bus', label: 'Higer Bus'}]});
        if (type === 'manufacturers') return jsonResponse({success: true, options: [{id: 'Hino', label: 'Hino'}]});
        if (type === 'models') return jsonResponse({success: true, options: [{id: 'RK', label: 'RK'}]});
        if (type === 'years') return jsonResponse({success: true, options: [{id: '2020', label: '2020'}]});
        if (type === 'inspectors') return jsonResponse({success: true, options: [{id: '1', label: 'Ana Reyes (INS-001)'}]});
        return jsonResponse({success: true, options: []});
    }

    if (u.indexOf('/admin/api/RoleManagement/Options.php') === 0) {
        const type = qp(u, 'type');
        if (type === 'routes') return jsonResponse({success: true, options: [{id: 'Cubao - Baguio', label: 'Cubao - Baguio'}]});
        return jsonResponse({success: true, options: [{id: '1', label: 'Cubao Terminal'}, {id: '2', label: 'Baguio Terminal'}]});
    }

    if (u.indexOf('/admin/api/Schedule/ScheduleApi.php') === 0) {
        return jsonResponse({success: true, data: [{driver_id: 'DRV-001', full_name: 'Juan Dela Cruz'}]});
    }

    if (u.indexOf('/admin/api/Bus/Duplicates.php') === 0) {
        const body = JSON.parse(options.body || '{}');
        duplicateChecks.push(body);
        if (duplicateMode === 'dup') {
            return jsonResponse({
                success: true,
                duplicate: {ref: 2, bus_id: 'BUS-002', plate: String(body.plate_number || ''), status: 'Active'},
                duplicates: [{ref: 2, bus_id: 'BUS-002', plate: String(body.plate_number || ''), status: 'Active'}],
                message: 'Plate number already used.'
            });
        }
        return jsonResponse({success: true, duplicate: null, duplicates: []});
    }

    if (u.indexOf('/admin/api/Bus/QrInfo.php') === 0) {
        if (qrMode === 'missing') {
            return jsonResponse({success: true, needs_preparation: true, qr: null, history: [], legacy: {}});
        }
        const ref = qp(u, 'bus_ref') || '1';
        return jsonResponse({
            success: true,
            needs_preparation: false,
            qr: {
                token: 'abcdef0123456789abcdef0123456789',
                payload_url: '/passenger/scan.php?bus=BUS-00' + ref + '&t=abcdef0123456789abcdef0123456789',
                status: 'active',
                created_at: '2026-03-01 08:00:00',
                replaced_at: null,
                image_path: ''
            },
            history: [], legacy: {}
        });
    }

    if (u.indexOf('/admin/api/Bus/History.php') === 0) {
        return jsonResponse({
            success: true,
            data: [{action: 'Registered', description: 'Bus registered with automatic QR', username: 'admin', created_at: '2026-03-01 08:00:00'}]
        });
    }

    if (u.indexOf('/admin/api/Bus/QrBackfill.php') === 0) {
        if ((options.method || 'GET') === 'POST') {
            backfillRuns += 1;
            bulkMissing = 0;
            qrMode = 'active';
            return jsonResponse({success: true, message: '1 QR code prepared.', created: 1, skipped: 1});
        }
        return jsonResponse({
            success: true,
            buses: bulkMissing ? [{bus_id: 'BUS-005', plate: 'XYZ 999', unit_number: '95'}] : [],
            total: bulkMissing
        });
    }

    if (u.indexOf('/admin/api/Bus/QrList.php') === 0) {
        return jsonResponse({
            success: true,
            buses: [
                {bus_id: 'BUS-001', unit_number: '1201', payload_url: '/passenger/scan.php?bus=BUS-001&t=t1'},
                {bus_id: 'BUS-002', unit_number: '', payload_url: '/passenger/scan.php?bus=BUS-002&t=t2'}
            ],
            missing: [], total: 2
        });
    }

    if (u.indexOf('/admin/api/Bus/QrReplace.php') === 0) {
        const body = JSON.parse(options.body || '{}');
        qrReplaceCalls.push(body);
        return jsonResponse({
            success: true,
            bus_id: body.bus_id || '',
            message: body.mode === 'revoke' ? 'The QR code was revoked.' : 'A new QR code was issued.',
            qr: {payload_url: '/passenger/scan.php?bus=' + (body.bus_id || '') + '&t=tok-new', status: 'active'}
        });
    }

    if (u.indexOf('/admin/api/Bus/export.php') === 0) {
        exportUrls.push(u);
        if (exportMode === 'json') return jsonResponse({success: false, message: 'No data available to export.'});
        return csvResponse();
    }

    if (u.indexOf('/admin/api/add_bus.php') === 0) {
        const form = options.body;
        addCalls.push({
            plate: form.get('plate_number'), unit: form.get('unit_number'), type: form.get('bus_type'),
            seats: form.get('seats'), standing: form.get('standing_capacity'),
            status: form.get('status'), token: form.get('security_token')
        });
        addedBus = true;
        return jsonResponse({success: true, bus_id: 'BUS-007', qr: {token: 'tok', payload_url: '/passenger/scan.php?bus=BUS-007&t=tok'}});
    }

    if (u.indexOf('/admin/api/edit_bus.php') === 0) {
        const form = options.body;
        editCalls.push({
            plate: form.get('plate_number'), unit: form.get('unit_number'), reason: form.get('reason'),
            type: form.get('bus_type'), seats: form.get('seats'), standing: form.get('standing_capacity'),
            bus_ref: form.get('bus_ref'), token: form.get('security_token'), allow_move: form.get('allow_move'),
            registration_month: form.get('registration_month')
        });
        if (driverConflictOnce) {
            driverConflictOnce = false;
            return jsonResponse({success: false, code: 'driver_conflict', message: 'Driver Juan Dela Cruz is assigned to BUS-002.'});
        }
        return jsonResponse({success: true, message: 'Bus updated.'});
    }

    if (u.indexOf('/admin/api/archive_bus.php') === 0) {
        const body = JSON.parse(options.body || '{}');
        archiveCalls.push(body);
        archivedRefs[String(body.bus_ref)] = true;
        return jsonResponse({success: true, message: 'Bus archived.'});
    }

    if (u.indexOf('/admin/api/restore_bus.php') === 0) {
        const body = JSON.parse(options.body || '{}');
        restoreCalls.push(body);
        archivedRefs[String(body.bus_ref)] = false;
        restoreApplied[String(body.bus_ref)] = body;
        return jsonResponse({success: true, message: 'Bus restored.'});
    }

    throw new Error('unexpected fetch ' + u);
}

/* -------------------------------- mini DOM -------------------------------- */

const nodes = new Map();
let createdCount = 0;

function decodeEntities(text) {
    return String(text)
        .replace(/&#0?39;/g, "'")
        .replace(/&quot;/g, '"')
        .replace(/&gt;/g, '>')
        .replace(/&lt;/g, '<')
        .replace(/&amp;/g, '&');
}

function stripTags(text) {
    return String(text).replace(/<[^>]*>/g, '');
}

function parseAttrs(attrText) {
    const attrs = {};
    const re = /([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*"([^"]*)"/g;
    let match;
    while ((match = re.exec(attrText)) !== null) attrs[match[1].toLowerCase()] = decodeEntities(match[2]);
    const bare = attrText.match(/(?:^|\s)(readonly|disabled|required|selected|checked)(?=\s|$)/gi) || [];
    bare.forEach(name => { attrs[name.trim().toLowerCase()] = ''; });
    return attrs;
}

function makeFieldNode(tag, attrs, content) {
    const node = {
        tagName: tag.toUpperCase(),
        type: 'text',
        options: [],
        selectedIndex: -1,
        value: '',
        textContent: '',
        className: '',
        disabled: false,
        hidden: false,
        _invalid: false,
        _attrs: attrs,
        classList: {
            add(name) { if (name === 'is-invalid') node._invalid = true; },
            remove(name) { if (name === 'is-invalid') node._invalid = false; },
            contains(name) { return name === 'is-invalid' ? node._invalid : false; },
            toggle() {}
        },
        getAttribute(name) { const value = node._attrs[name]; return value === undefined ? null : value; },
        setAttribute(name, value) { node._attrs[name] = String(value); },
        removeAttribute(name) { delete node._attrs[name]; },
        focus() {},
        remove() {},
        scrollIntoView() {},
        querySelector() { return null; },
        querySelectorAll() { return []; },
        listeners: {},
        addEventListener(name, fn) { node.listeners[name] = fn; }
    };
    if (tag === 'input') node.type = attrs.type || 'text';
    if (tag === 'select') {
        const optRe = /<option\b([^>]*)>([\s\S]*?)<\/option>/gi;
        let match;
        let index = 0;
        let selected = -1;
        while ((match = optRe.exec(content)) !== null) {
            const optionAttrs = parseAttrs(match[1]);
            if (optionAttrs.selected !== undefined && selected === -1) selected = index;
            node.options.push({
                value: optionAttrs.value === undefined ? '' : optionAttrs.value,
                textContent: decodeEntities(stripTags(match[2]))
            });
            index += 1;
        }
        node.selectedIndex = selected === -1 ? (node.options.length ? 0 : -1) : selected;
        node.value = node.selectedIndex >= 0 ? node.options[node.selectedIndex].value : '';
    } else if (tag === 'textarea') {
        node.value = decodeEntities(stripTags(content).trim());
        node.textContent = node.value;
    } else {
        node.value = attrs.value === undefined ? '' : attrs.value;
    }
    return node;
}

function parseFields(node, html) {
    node._fields = [];
    const add = (tag, attrs, content) => {
        node._fields.push({attrs, node: makeFieldNode(tag, attrs, content)});
    };
    let match;
    const inputRe = /<input\b([^>]*)>/gi;
    while ((match = inputRe.exec(html)) !== null) add('input', parseAttrs(match[1]), '');
    const selectRe = /<select\b([^>]*)>([\s\S]*?)<\/select>/gi;
    while ((match = selectRe.exec(html)) !== null) add('select', parseAttrs(match[1]), match[2]);
    const textareaRe = /<textarea\b([^>]*)>([\s\S]*?)<\/textarea>/gi;
    while ((match = textareaRe.exec(html)) !== null) add('textarea', parseAttrs(match[1]), match[2]);
}

function makeNode(id) {
    const node = {
        id,
        hidden: false,
        disabled: false,
        value: '',
        textContent: '',
        className: '',
        dataset: {},
        style: {},
        options: [],
        listeners: {},
        _html: '',
        _fields: [],
        _canvas: null,
        _attrs: {},
        classList: {add() {}, remove() {}, toggle() {}, contains() { return false; }},
        setAttribute(name, value) { node._attrs[name] = String(value); },
        getAttribute(name) {
            if (node.id === 'busQrCanvas' && name === 'data-payload') {
                const match = String(element('busDetailBody').innerHTML).match(/id="busQrCanvas" data-payload="([^"]*)"/);
                return match ? decodeEntities(match[1]) : '';
            }
            return node._attrs[name] === undefined ? null : node._attrs[name];
        },
        removeAttribute(name) { delete node._attrs[name]; },
        remove() {},
        click() {},
        focus() {},
        scrollIntoView() {},
        insertAdjacentHTML(position, text) {
            const previous = node._fields.slice();
            node._html += String(text);
            parseFields(node, node._html);
            // Re-parsing replaces field nodes; keep the listeners attached before.
            node._fields.forEach(field => {
                const old = previous.find(prev => JSON.stringify(prev.attrs) === JSON.stringify(field.attrs));
                if (old && old.node.listeners) Object.assign(field.node.listeners, old.node.listeners);
            });
        },
        appendChild(child) {
            if (Array.isArray(node.options) && child && child.tagName === 'OPTION') node.options.push(child);
        },
        addEventListener(name, fn) { node.listeners[name] = fn; },
        querySelector(selector) {
            const sel = String(selector);
            const attrMatch = sel.match(/^\[([a-zA-Z-]+)(?:="([^"]*)")?\]$/);
            if (attrMatch) {
                const attr = attrMatch[1].toLowerCase();
                const value = attrMatch[2];
                for (const field of node._fields) {
                    if (field.attrs[attr] === undefined) continue;
                    if (value === undefined || String(field.attrs[attr]) === value) return field.node;
                }
            }
            if (/^canvas$/i.test(sel) && node._canvas) return node._canvas;
            return null;
        },
        querySelectorAll(selector) {
            const attrMatch = String(selector).match(/^\[([a-zA-Z-]+)\]$/);
            if (!attrMatch) return [];
            const attr = attrMatch[1].toLowerCase();
            return node._fields.filter(field => field.attrs[attr] !== undefined).map(field => field.node);
        },
        closest() { return null; }
    };
    Object.defineProperty(node, 'innerHTML', {
        get() { return node._html; },
        set(value) {
            node._html = String(value);
            node._fields = [];
            parseFields(node, node._html);
        }
    });
    return node;
}

function element(id) {
    if (!nodes.has(id)) nodes.set(id, makeNode(id));
    return nodes.get(id);
}

function createElement(tag) {
    if (String(tag).toLowerCase() === 'canvas') {
        return {
            tagName: 'CANVAS',
            width: 0,
            height: 0,
            style: {},
            getContext: () => ({fillStyle: '#000000', fillRect() {}, drawImage() {}, clearRect() {}, strokeRect() {}}),
            toDataURL: () => 'data:image/png;base64,EXPORT'
        };
    }
    const node = makeNode(String(tag) + ':' + (++createdCount));
    node.tagName = String(tag).toUpperCase();
    if (String(tag).toLowerCase() === 'a') {
        node.click = function () { createdLinks.push({href: node.href, download: node.download}); };
    }
    return node;
}

const documentMock = {
    getElementById: element,
    createElement,
    body: {appendChild() {}},
    addEventListener() {},
    querySelector() { return null; },
    querySelectorAll() { return []; },
    readyState: 'complete'
};

/* --------------------------- library mocks --------------------------- */

const modalInstances = new Map();
const bootstrapMock = {
    Modal: {
        getOrCreateInstance(node) {
            if (!modalInstances.has(node)) {
                modalInstances.set(node, {show() { node.hidden = false; }, hide() { node.hidden = true; }});
            }
            return modalInstances.get(node);
        },
        getInstance(node) { return modalInstances.get(node) || null; }
    }
};

function QRCodeMock(host, options) {
    qrRenders.push(options.text);
    const canvas = {
        width: options.width,
        height: options.height,
        toDataURL: () => 'data:image/png;base64,QR' + qrRenders.length
    };
    host._canvas = canvas;
    host.querySelector = selector => (String(selector).toLowerCase() === 'canvas' ? canvas : null);
    host.innerHTML = '<canvas width="' + options.width + '" height="' + options.height + '"></canvas>';
}
QRCodeMock.CorrectLevel = {H: 2};

function JsPdfMock(options) {
    this.options = options || {};
    this.pages = 1;
    this.fontSize = 0;
    this.images = [];
    this.texts = [];
    pdfInstances.push(this);
}
JsPdfMock.prototype.addPage = function () { this.pages += 1; };
JsPdfMock.prototype.setFontSize = function (size) { this.fontSize = size; };
JsPdfMock.prototype.addImage = function (data, format, x, y, w, h) { this.images.push({data, format, x, y, w, h}); };
JsPdfMock.prototype.text = function (text) { this.texts.push(String(text)); };
JsPdfMock.prototype.save = function (name) { this.saved = name; pdfSaves.push(name); };

function html2canvasMock(el, options) {
    html2canvasCalls.push({el, options});
    return Promise.resolve({
        width: 750,
        height: 1050,
        toDataURL: () => 'data:image/png;base64,POSTER'
    });
}

/* ------------------------------- sandbox ------------------------------- */

const windowMock = {
    BUS_CONFIG: {
        statuses: {
            active: 'Active', inactive: 'Inactive', maintenance: 'Maintenance', archived: 'Archived'
        },
        isSuper: true,
        openBus: deepLink ? '2' : ''
    },
    location: {search: '', pathname: '/admin/bus'},
    history: {replaceState(state, title, url) { urlWrites.push(String(url)); }},
    alert() {},
    confirm: () => true,
    prompt: () => null,
    open() {
        const popup = {
            html: '',
            printed: false,
            focused: false,
            document: {readyState: 'complete', write(chunk) { popup.html += chunk; }, close() {}},
            focus() { popup.focused = true; },
            print() { popup.printed = true; }
        };
        popups.push(popup);
        return popup;
    },
    showAlert(kind, title, text) { alerts.push({kind, title, text}); },
    adminPromptReason(title, text, confirmText, maxLength) {
        const record = {title, text, options: {input: true, confirmText, maxLength}, confirmed: false, cancelled: false, value: null};
        confirms.push(record);
        if (confirmCancelNext) { confirmCancelNext = false; record.cancelled = true; return Promise.resolve(null); }
        const value = String(confirmInputValue == null ? '' : confirmInputValue);
        if (value.trim().length < 5) { record.cancelled = true; record.rejected = true; return Promise.resolve(null); }
        record.confirmed = true; record.value = value;
        return Promise.resolve(value);
    },
    showConfirm(title, text, onConfirm, onCancel, options) {
        const record = {title, text, options: options || null, confirmed: false, cancelled: false, value: null};
        confirms.push(record);
        if (confirmCancelNext) {
            confirmCancelNext = false;
            record.cancelled = true;
            if (onCancel) onCancel();
            return;
        }
        record.confirmed = true;
        if (options && options.input) {
            record.value = confirmInputValue;
            onConfirm(confirmInputValue);
            return;
        }
        onConfirm();
    },
    QRCode: QRCodeMock,
    jspdf: {jsPDF: JsPdfMock},
    html2canvas: html2canvasMock
};

const sandbox = {
    window: windowMock,
    document: documentMock,
    bootstrap: bootstrapMock,
    fetch: fetchMock,
    console,
    setTimeout,
    clearTimeout,
    URLSearchParams,
    FormData,
    Blob,
    URL: {createObjectURL: () => 'blob:mock', revokeObjectURL() {}},
    TextEncoder,
    history: windowMock.history,
    alert: () => {},
    QRCode: QRCodeMock,
    html2canvas: html2canvasMock,
    Promise
};
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync('admin/assets/js/qr-poster.js', 'utf8'), sandbox);
vm.runInContext(fs.readFileSync('admin/assets/js/bus-management.js', 'utf8'), sandbox);

/* ------------------------------- helpers ------------------------------- */

async function settle(times = 14) {
    for (let i = 0; i < times; i += 1) await new Promise(resolve => setImmediate(resolve));
}

function actionStub(action, index) {
    return {
        getAttribute(name) {
            if (name === 'data-action') return action;
            if (name === 'data-index') return index;
            return null;
        }
    };
}

function clickAction(hostId, action, index) {
    element(hostId).listeners.click({
        target: {closest: selector => (selector === '[data-action]' ? actionStub(action, index) : null)}
    });
}

async function submitSecurity(code) {
    element('busSecurityCode').value = code || '246810';
    element('busSecurityConfirm').listeners.click();
    await settle();
}

function lastFetch(match) {
    for (let i = fetchLog.length - 1; i >= 0; i -= 1) {
        if (fetchLog[i].url.indexOf(match) !== -1) return fetchLog[i];
    }
    return null;
}

function countMatches(text, pattern) {
    return (String(text).match(pattern) || []).length;
}

/* --------------------------------- tests --------------------------------- */

(async () => {
    assert.match(element('busList').innerHTML, /rmg-skel/, 'Skeleton renders while buses load');
    await settle();

    if (deepLink) {
        assert.equal(element('busDetailModal').hidden, false, 'Deep link opens the detail modal');
        assert.equal(element('busDetailName').textContent, 'BUS-002', 'Deep link opens the referenced bus');
        console.log('Bus Management deep-link check passed: open_bus opens the referenced bus profile.');
        return;
    }

    /* ---------------- 5.2 summary, filters, table, pager ---------------- */

    const list = element('busList');
    assert.match(element('busSummary').innerHTML, /Total Buses/, 'Summary cards render');
    assert.match(element('busSummary').innerHTML, /Inactive/, 'Summary includes the inactive lifecycle bucket');
    assert.match(element('busSummary').innerHTML, /Maintenance/, 'Summary includes the maintenance lifecycle bucket');
    assert.match(element('busSummary').innerHTML, /rmg-sum-value">2</, 'Total card equals the 2 registered buses');
    assert.equal(countMatches(element('busSummary').innerHTML, /rmg-sum-value/g), 5, 'Five summary buckets render');
    assert.match(element('busCount').innerHTML, /2 buses/, 'Header count matches the total');
    assert.match(element('busPgInfo').textContent, /Showing 1–2 of 2/, 'Pagination info matches');

    assert.match(list.innerHTML, /<th>Bus<\/th>/, 'Bus column heading');
    assert.match(list.innerHTML, /<th>Plate<\/th>/, 'Plate column heading');
    assert.match(list.innerHTML, /<th>Status<\/th>/, 'Status column heading');
    assert.match(list.innerHTML, /<th>Operational<\/th>/, 'Operational column heading renders separately from status');
    assert.match(list.innerHTML, /data-label="Operational"/, 'Operational cells render separately from status');
    assert.doesNotMatch(list.innerHTML, /<th>Route<\/th>/, 'Route column heading is removed');
    assert.doesNotMatch(list.innerHTML, /data-label="Route"/, 'Route cell is removed');
    assert.match(list.innerHTML, /<th class="rmg-th-actions">Actions<\/th>/, 'Actions column heading');
    assert.equal(countMatches(list.innerHTML, /data-action="details"/g), 2, 'One Details action per row');
    assert.equal(countMatches(list.innerHTML, /data-action="edit"/g), 2, 'One Edit action per row');
    assert.match(list.innerHTML, /aria-label="View profile for bus BUS-001"/, 'Row actions expose accessible labels');
    assert.match(list.innerHTML, /data-action="archive"/, 'Rows expose Archive');
    assert.doesNotMatch(list.innerHTML, /Cubao - Baguio/, 'Rows no longer show a route column');
    assert.doesNotMatch(list.innerHTML, /<th>Driver<\/th>/, 'Driver column heading is removed');
    assert.doesNotMatch(list.innerHTML, /data-label="Driver"/, 'Driver cell is removed');

    // Regression: an Active bus with an unknown operational value shows Active,
    // and the operational value stays separate.
    assert.match(list.innerHTML, /<span class="rmg-status is-active">Active<\/span>/, 'Active lifecycle status renders for BUS-001');
    assert.match(list.innerHTML, /Not Available/, 'Unknown operational value is shown in the Operational column');
    assert.match(list.innerHTML, /<span class="rmg-status is-inactive">Inactive<\/span>/, 'Inactive lifecycle status renders for BUS-002');
    assert.doesNotMatch(list.innerHTML, />Available<\/span><\/td>/, 'Operational labels never render as lifecycle statuses');
    const summaryValues = Array.from(element('busSummary').innerHTML.matchAll(/rmg-sum-value">(-?\d+)</g)).map(match => Number(match[1]));
    assert.deepEqual(summaryValues, [2, 1, 1, 0, 0], 'Summary buckets count lifecycle statuses only: total, active, inactive, maintenance, archived');

    assert.match(element('busType').innerHTML, /Standard/, 'Type filter is populated');
    assert.match(element('busRoute').innerHTML, /Cubao - Baguio/, 'Route filter is populated');
    assert.match(element('busDriver').innerHTML, /Juan Dela Cruz/, 'Driver filter is populated');
    assert.equal(element('busSort').value, 'bus_asc', 'Default sort is Bus ID A-Z');

    // Filter: cards equal counts for the same rule.
    element('busStatus').value = 'active';
    element('busStatus').listeners.change();
    await settle();
    assert.match(lastFetch('/admin/api/Bus/list.php').url, /status=active/, 'Status filter is requested from the server');
    assert.equal(countMatches(element('busList').innerHTML, /<tr data-index="/g), 1, 'Filtered table shows only matching rows');
    assert.match(element('busList').innerHTML, /BUS-001/, 'The Active bus with an unknown operational value filters as Active');
    assert.match(element('busSummary').innerHTML, /rmg-sum-value">1</, 'Summary equals the filtered counts');

    // Inactive lifecycle filter: the available operational value never moves a
    // bus out of its stored lifecycle status.
    element('busStatus').value = 'inactive';
    element('busStatus').listeners.change();
    await settle();
    assert.match(lastFetch('/admin/api/Bus/list.php').url, /status=inactive/, 'Inactive filter is requested from the server');
    assert.equal(countMatches(element('busList').innerHTML, /<tr data-index="/g), 1, 'Inactive filter returns only lifecycle-inactive rows');
    assert.match(element('busList').innerHTML, /BUS-002/, 'The inactive bus is listed under the inactive filter');
    assert.doesNotMatch(element('busList').innerHTML, /BUS-001/, 'The active bus is excluded from the inactive filter');

    assert.ok(urlWrites.length > 0, 'Bus page updates the URL for filters');
    assert.match(String(urlWrites[urlWrites.length - 1]), /^\/admin\/bus\?/, 'Bus page keeps the served route with the filter query');
    assert.ok(!String(urlWrites[urlWrites.length - 1]).includes('/admin/views/'), 'Bus page never writes the blocked internal view path');
    element('busStatus').value = '';
    element('busStatus').listeners.change();
    await settle();

    // Empty state + clear.
    listMode = 'empty';
    element('busRefresh').listeners.click();
    await settle();
    assert.match(element('busList').innerHTML, /No buses found/, 'Empty state renders');
    assert.match(element('busList').innerHTML, /busEmptyClear/, 'Empty state offers Clear filters');
    element('busEmptyClear').listeners.click();
    await settle();
    assert.doesNotMatch(lastFetch('/admin/api/Bus/list.php').url, /status=/, 'Empty-state clear resets filters');
    listMode = 'normal';
    element('busRefresh').listeners.click();
    await settle();

    // Pagination keeps filters.
    listMode = 'many';
    element('busStatus').value = 'active';
    element('busStatus').listeners.change();
    await settle();
    assert.match(element('busPager').innerHTML, /data-page="2"/, 'Pager renders multiple pages');
    element('busPager').listeners.click({
        target: {closest: selector => (selector === '[data-page]' ? {disabled: false, getAttribute: () => '2'} : null)}
    });
    await settle();
    const paged = lastFetch('/admin/api/Bus/list.php').url;
    assert.match(paged, /page=2/, 'Pager requests the clicked page');
    assert.match(paged, /status=active/, 'Filters survive paging');
    element('busPerPage').value = '25';
    element('busPerPage').listeners.change();
    await settle();
    assert.match(lastFetch('/admin/api/Bus/list.php').url, /per_page=25/, 'Per-page selection is requested');
    assert.match(lastFetch('/admin/api/Bus/list.php').url, /page=1/, 'Per-page change resets to page 1');
    listMode = 'normal';
    element('busClearFilters').listeners.click();
    await settle();

    /* --------------------- 5.4 detail profile tabs --------------------- */

    clickAction('busList', 'details', '0');
    await settle();
    assert.equal(element('busDetailModal').hidden, false, 'Details opens the profile modal');
    assert.equal(element('busDetailName').textContent, 'BUS-001', 'Profile shows the clicked bus');
    const body = element('busDetailBody').innerHTML;
    ['overview', 'assignments', 'qr', 'maintenance', 'documents', 'activity'].forEach(tab => {
        assert.match(body, new RegExp('data-bus-tab="' + tab + '"'), 'Profile has the ' + tab + ' tab');
    });
    assert.match(body, /Bus Information/, 'Overview section renders');
    assert.match(body, /rmg-status-text">Active<\/span>/, 'Profile shows the same lifecycle status the table displays');
    assert.match(body, /49 seats \+ 10 standing/, 'Capacity is composed from seats and standing');
    assert.match(body, /Registration Month/, 'Detail shows the registration month row');
    assert.match(body, /February/, 'Detail shows the stored registration month');
    assert.match(body, /bus-qr-panel/, 'QR panel renders once credentials load');
    assert.match(body, /data-payload="\/passenger\/scan\.php\?bus=BUS-001&amp;t=[0-9a-f]{32}"/, 'QR payload carries only the scan URL with its token');
    assert.match(body, /Download PNG/, 'QR panel offers PNG download');
    assert.match(body, /Download PDF/, 'QR panel offers PDF download');
    assert.match(body, /Replace QR/, 'QR panel offers Replace');
    assert.match(body, /Revoke QR/, 'QR panel offers Revoke');
    assert.match(body, /Registered/, 'Activity tab lists history entries');
    assert.match(body, /Bus registered with automatic QR/, 'Activity shows the recorded description');
    assert.match(element('busDetailActions').innerHTML, /Edit bus/, 'Profile offers Edit');
    assert.match(element('busDetailActions').innerHTML, /Bulk QR/, 'Profile offers Bulk QR');
    assert.ok(qrRenders.some(text => text.indexOf('/passenger/scan.php?bus=BUS-001') === 0), 'QR renderer received the payload URL');

    // Sparse bus: explicit not-recorded markers.
    qrMode = 'missing';
    clickAction('busList', 'details', '1');
    await settle();
    assert.equal(element('busDetailName').textContent, 'BUS-002', 'Sparse profile opens for the clicked bus');
    const sparseBody = element('busDetailBody').innerHTML;
    assert.match(sparseBody, /Not recorded/, 'Sparse fields show not-recorded markers');
    assert.doesNotMatch(sparseBody, /February/, 'Buses without a month show no month value');
    assert.match(sparseBody, /No QR code prepared yet/, 'Sparse profile flags missing QR');
    assert.match(sparseBody, /Prepare QR code/, 'Sparse profile offers QR preparation');
    assert.equal(backfillRuns, 0, 'Nothing is prepared until the button is used');

    clickAction('busDetailBody', 'qr-prepare', null);
    await settle();
    assert.equal(backfillRuns, 1, 'Prepare QR runs the backfill once');
    assert.match(element('busDetailBody').innerHTML, /Download PNG/, 'Prepared QR immediately renders the panel');
    assert.ok(alerts.some(alert => alert.title === 'QR prepared'), 'Preparation confirms success');

    /* -------------------- 5.5 QR downloads and changes -------------------- */

    clickAction('busList', 'details', '0');
    await settle();
    createdLinks.length = 0;

    clickAction('busDetailBody', 'qr-download-png', null);
    await new Promise(resolve => setTimeout(resolve, 400));
    assert.equal(createdLinks.length, 1, 'PNG download creates one file');
    assert.equal(createdLinks[0].download, 'RConnect_BUS-001_QR_Poster.png', 'PNG uses the designed poster filename');
    assert.match(String(createdLinks[0].href), /^data:image\/png/, 'PNG download uses the rendered poster');
    assert.equal(html2canvasCalls.length, 1, 'PNG poster is rendered through html2canvas');
    assert.equal(html2canvasCalls[0].options.scale, 3, 'Poster is captured at print scale');

    const pdfBefore = pdfInstances.length;
    clickAction('busDetailBody', 'qr-download-pdf', null);
    await new Promise(resolve => setTimeout(resolve, 400));
    assert.equal(pdfInstances.length, pdfBefore + 1, 'PDF download creates a document');
    const singlePdf = pdfInstances[pdfInstances.length - 1];
    assert.equal(singlePdf.saved, 'RConnect_BUS-001_QR_Poster.pdf', 'PDF uses the designed poster filename');
    assert.equal(singlePdf.images.length, 1, 'PDF embeds the poster image');
    assert.deepEqual(
        [singlePdf.images[0].x, singlePdf.images[0].y, singlePdf.images[0].w, singlePdf.images[0].h],
        [0, 0, 250, 350],
        'PDF embeds the poster full-page without stretching'
    );

    clickAction('busDetailBody', 'qr-print', null);
    await new Promise(resolve => setTimeout(resolve, 400));
    assert.equal(popups.length, 1, 'Print opens a print view');
    assert.match(popups[0].html, /BUS-001/, 'Print view includes the Bus ID');
    assert.match(popups[0].html, /SCAN TO RIDE/, 'Print view is the designed poster');
    assert.equal(countMatches(popups[0].html, /class="qr-target"/g), 1, 'Print view contains exactly one QR');
    assert.equal(popups[0].printed, true, 'Print view prints once the poster is ready');
    assert.equal(
        qrRenders[qrRenders.length - 1],
        '/passenger/scan.php?bus=BUS-001&t=abcdef0123456789abcdef0123456789',
        'Live page export QR re-encodes the exact panel payload'
    );

    // Cancelled prompt leaves the credential unchanged.
    const replaceBefore = qrReplaceCalls.length;
    confirmCancelNext = true;
    clickAction('busDetailBody', 'qr-replace', null);
    await settle(6);
    assert.equal(qrReplaceCalls.length, replaceBefore, 'Cancelling the reason prompt changes nothing');
    assert.equal(confirms[confirms.length - 1].title, 'Replace QR: BUS-001', 'Replace reason prompt is labeled');

    // Revoke: wrong code first, then success.
    confirmInputValue = 'Barcode damaged during cleaning.';
    clickAction('busDetailBody', 'qr-revoke', null);
    await settle(6);
    assert.equal(element('busSecurityModal').hidden, false, 'Revoke asks for the security code');
    assert.match(element('busSecuritySummary').textContent, /Revoke QR: BUS-001/, 'Security modal names the action');
    await submitSecurity('999999');
    assert.equal(element('busSecurityError').hidden, false, 'Wrong code shows an inline error');
    assert.match(element('busSecurityError').textContent, /Incorrect Security Authorization Code/, 'Wrong-code message is explicit');
    assert.equal(qrReplaceCalls.length, replaceBefore, 'Wrong code does not reach the endpoint');
    await submitSecurity('246810');
    assert.equal(qrReplaceCalls.length, replaceBefore + 1, 'Correct code revokes the QR');
    assert.deepEqual(qrReplaceCalls[qrReplaceCalls.length - 1].mode, 'revoke');
    assert.deepEqual(String(qrReplaceCalls[qrReplaceCalls.length - 1].reason), 'Barcode damaged during cleaning.');
    assert.ok(alerts.some(alert => alert.title === 'QR revoked'), 'Revoke confirms success');

    // Replace with a fresh reason.
    confirmInputValue = 'Rotating the token per security policy.';
    clickAction('busDetailBody', 'qr-replace', null);
    await settle(6);
    await submitSecurity('246810');
    assert.equal(qrReplaceCalls[qrReplaceCalls.length - 1].mode, 'replace', 'Replace rotates the credential');
    assert.ok(alerts.some(alert => alert.title === 'QR replaced'), 'Replace confirms success');

    /* ---------------- 5.3 add wizard, guard, duplicates ---------------- */

    element('busAddBtn').listeners.click();
    await settle();
    assert.equal(element('busAddModal').hidden, false, 'Add bus opens the wizard');
    assert.equal(element('busAddUserId').value, 'BUS-007', 'Bus ID preview is fetched on open');
    assert.equal(element('busAddSave').disabled, false, 'Save enables once options load');
    const stepTitles = element('busAddSteps').innerHTML;
    ['Basic Information', 'Vehicle Information', 'Operational Information', 'Registration &amp; Compliance', 'Review &amp; Confirm']
        .forEach(title => assert.match(stepTitles, new RegExp(title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')), 'Wizard step present: ' + title));
    const addFields = element('busAddFields').innerHTML;
    assert.match(addFields, /data-add-field="plate_number"/, 'Plate field renders');
    assert.match(addFields, /data-add-field="bus_type"/, 'Bus type field renders');
    assert.match(addFields, /data-add-field="seats"/, 'Seat capacity field renders');
    assert.match(addFields, /data-add-field="standing_capacity"/, 'Standing capacity field renders');
    assert.match(addFields, /data-add-field="operational_status"/, 'Operational status field renders');
    assert.match(addFields, /data-add-field="status"/, 'Account status field renders');
    assert.match(addFields, /data-add-field="registration_month"/, 'Registration month field renders');
    assert.match(addFields, /data-add-field="registration_expiry"/, 'Compliance dates render');
    assert.doesNotMatch(addFields, /data-add-field="route_name"/, 'Route selection is removed (the Dispatcher manages routes)');
    assert.match(element('busAddReview').innerHTML, /Bus ID/, 'Review panel starts with the Bus ID preview');

    // Choosing the bus type applies the fleet capacity standard.
    const typeField = element('busAddFields').querySelector('[data-add-field="bus_type"]');
    assert.ok(typeField && typeof typeField.listeners.change === 'function', 'The type select wires the capacity standard');
    typeField.value = 'HINO Bus';
    typeField.listeners.change();
    assert.equal(element('busAddFields').querySelector('[data-add-field="seats"]').value, '24', 'HINO Bus pre-fills 24 seats');
    assert.equal(element('busAddFields').querySelector('[data-add-field="standing_capacity"]').value, '11', 'HINO Bus pre-fills 11 standing');
    typeField.value = 'Higer Bus';
    typeField.listeners.change();
    assert.equal(element('busAddFields').querySelector('[data-add-field="seats"]').value, '26', 'Higer Bus pre-fills 26 seats');
    assert.equal(element('busAddFields').querySelector('[data-add-field="standing_capacity"]').value, '14', 'Higer Bus pre-fills 14 standing');

    // Invalid input blocks navigation.
    element('busAddNext').listeners.click();
    await settle();
    assert.equal(element('busAddBack').disabled, true, 'Invalid step does not advance');
    assert.equal(element('busAddFields').querySelector('[data-add-field="plate_number"]')._invalid, true, 'Missing required field is flagged');
    assert.ok(alerts.some(alert => alert.title === 'Check this step'), 'Blocked step notifies the user');

    // Fill the four required fields and walk to review.
    const setField = (name, value) => {
        const field = element('busAddFields').querySelector('[data-add-field="' + name + '"]');
        assert.ok(field, 'Field exists: ' + name);
        field.value = value;
        return field;
    };
    const setSelectField = (name, value) => {
        const field = setField(name, value);
        const index = (field.options || []).findIndex(option => String(option.value) === String(value));
        if (index >= 0) field.selectedIndex = index;
        return field;
    };
    setField('plate_number', 'NCR 8888');
    setSelectField('bus_type', 'HINO Bus');
    setField('seats', '24');
    setField('standing_capacity', '11');
    element('busAddNext').listeners.click();
    await settle(4);
    element('busAddNext').listeners.click();
    await settle(4);
    element('busAddNext').listeners.click();
    await settle(4);
    element('busAddNext').listeners.click();
    await settle(6);
    assert.equal(element('busAddNext').disabled, true, 'Review is the final step');
    assert.match(element('busAddReview').innerHTML, /Review before saving/, 'Review step renders live summary');
    assert.match(element('busAddReview').innerHTML, /NCR 8888/, 'Review includes typed values');
    assert.match(element('busAddReview').innerHTML, /HINO Bus/, 'Review includes the bus type');
    assert.match(element('busAddReview').innerHTML, /BUS-007 \(preview\)/, 'Review marks the ID as a preview');

    // Unsaved-changes guard: cancel keeps the form open, confirm closes it.
    element('busAddFields').listeners.input();
    confirmCancelNext = true;
    let prevented = false;
    element('busAddModal').listeners['hide.bs.modal']({preventDefault() { prevented = true; }});
    await settle(4);
    assert.equal(prevented, true, 'Guard cancels the close');
    assert.equal(element('busAddModal').hidden, false, 'Cancelling the guard keeps the form open');
    assert.equal(confirms[confirms.length - 1].title, 'Discard unsaved changes?', 'Guard asks before discarding');
    element('busAddModal').listeners['hide.bs.modal']({preventDefault() {}});
    await settle(4);
    assert.equal(element('busAddModal').hidden, true, 'Confirming the guard closes the form');

    // Reopen, refill, duplicate-submit prevention, successful save.
    element('busAddBtn').listeners.click();
    await settle();
    setField('plate_number', 'NCR 8888');
    setSelectField('bus_type', 'HINO Bus');
    setField('seats', '24');
    setField('standing_capacity', '11');
    element('busAddSave').listeners.click();
    element('busAddSave').listeners.click();
    await settle(6);
    assert.equal(addCalls.length, 0, 'Nothing is posted before the code is verified');
    assert.equal(element('busSecurityModal').hidden, false, 'Saving asks for the security code');
    assert.match(element('busSecuritySummary').textContent, /Register bus/, 'Security modal names the add action');
    await submitSecurity('246810');
    assert.equal(addCalls.length, 1, 'Double-click submits exactly once');
    assert.equal(addCalls[0].plate, 'NCR 8888', 'Add posts the plate');
    assert.equal(addCalls[0].type, 'HINO Bus', 'Add posts the bus type');
    assert.equal(addCalls[0].seats, '24', 'Add posts the seating capacity');
    assert.equal(addCalls[0].standing, '11', 'Add posts the standing capacity');
    assert.equal(addCalls[0].unit, '', 'Optional fields can stay empty');
    assert.equal(addCalls[0].token, 'tok-verify', 'Add posts the verified token');
    assert.ok(alerts.some(alert => alert.title === 'Bus registered'), 'Add confirms success with the assigned ID');
    assert.match(element('busCount').innerHTML, /3 buses/, 'List refreshes after registration');
    assert.equal(element('busDetailModal').hidden, false, 'Registered bus profile opens automatically');
    assert.equal(element('busDetailName').textContent, 'BUS-007', 'Opened profile is the newly registered bus');

    // Duplicate plate warning blocks the save.
    element('busAddBtn').listeners.click();
    await settle();
    setField('plate_number', 'NCR 5678');
    setSelectField('bus_type', 'HINO Bus');
    setField('seats', '24');
    setField('standing_capacity', '11');
    duplicateMode = 'dup';
    element('busAddSave').listeners.click();
    await settle(8);
    assert.equal(addCalls.length, 1, 'Duplicate plate never reaches the endpoint');
    assert.equal(element('busAddError').hidden, false, 'Duplicate plate shows the form error');
    assert.match(element('busAddError').textContent, /already belongs to another bus/, 'Duplicate error explains the conflict');
    assert.equal(element('busAddDuplicates').hidden, false, 'Duplicate warning lists the conflicting bus');
    assert.match(element('busAddDuplicates').innerHTML, /BUS-002/, 'Warning names the conflicting Bus ID');
    duplicateMode = 'none';

    /* ------------------ 5.3 edit wizard (full form, no reason) ------------------ */

    clickAction('busList', 'edit', '0');
    await settle();
    assert.equal(element('busEditModal').hidden, false, 'Edit bus opens the wizard');
    const editFields = element('busEditFields').innerHTML;
    assert.match(editFields, /data-edit-field="plate_number"/, 'Edit renders the plate field');
    assert.match(editFields, /data-edit-field="bus_type"/, 'Edit renders the bus type field');
    assert.match(editFields, /data-edit-field="seats"/, 'Edit renders the seating capacity');
    assert.match(editFields, /data-edit-field="standing_capacity"/, 'Edit renders the standing capacity');
    assert.match(editFields, /data-edit-field="registration_month"/, 'Edit renders the registration month');
    assert.doesNotMatch(editFields, /data-edit-field="route_name"/, 'Edit no longer selects a route (the Dispatcher manages routes)');
    assert.doesNotMatch(editFields, /Reason for this change/, 'Edit does not ask for a reason');
    assert.match(editFields, /readonly/, 'Edit shows the Bus ID read-only');
    assert.match(editFields, /BUS-001/, 'Edit shows the assigned Bus ID');
    assert.doesNotMatch(editFields, /data-edit-field="bus_id"/, 'Bus ID is never editable');
    assert.equal(element('busEditFields').querySelector('[data-edit-hidden="bus_ref"]').value, '1', 'Edit tracks the internal bus reference');

    const editNode = name => element('busEditFields').querySelector('[data-edit-field="' + name + '"]');
    assert.equal(editNode('plate_number').value, 'NCR 1234', 'Edit pre-fills the stored plate');
    assert.equal(editNode('seats').value, '49', 'Edit pre-fills the stored seating capacity');
    assert.equal(editNode('standing_capacity').value, '10', 'Edit pre-fills the stored standing capacity');
    assert.equal(editNode('registration_month').value, 'February', 'Edit pre-fills the stored registration month');

    editNode('plate_number').value = 'NCR 9999';
    editNode('bus_type').value = 'Higer Bus';
    editNode('bus_type').listeners.change();
    assert.equal(editNode('seats').value, '26', 'Switching the type applies the Higer seating standard');
    assert.equal(editNode('standing_capacity').value, '14', 'Switching the type applies the Higer standing standard');

    element('busEditSave').listeners.click();
    await settle(6);
    assert.equal(duplicateChecks[duplicateChecks.length - 1].exclude_ref, '1', 'Edit duplicate check excludes the bus itself');
    await submitSecurity('246810');
    assert.equal(editCalls.length, 1, 'Edit posts once');
    assert.equal(editCalls[0].plate, 'NCR 9999', 'Edit posts the changed plate');
    assert.equal(editCalls[0].type, 'Higer Bus', 'Edit posts the changed bus type');
    assert.equal(editCalls[0].seats, '26', 'Edit posts the capacity standard for the new type');
    assert.equal(editCalls[0].bus_ref, '1', 'Edit posts the internal reference');
    assert.equal(editCalls[0].registration_month, 'February', 'Edit round-trips the registration month');
    assert.equal(editCalls[0].reason, null, 'Edit posts no reason');
    assert.ok(alerts.some(alert => alert.title === 'Bus updated'), 'Edit confirms success');

    // Driver conflict: confirm, re-verify, retry with allow_move.
    clickAction('busList', 'edit', '0');
    await settle();
    editNode('unit_number').value = '1202';
    driverConflictOnce = true;
    element('busEditSave').listeners.click();
    await settle(6);
    await submitSecurity('246810');
    assert.equal(editCalls.length, 2, 'Conflict returns before saving');
    assert.equal(editCalls[1].allow_move, null, 'First attempt does not move the driver');
    assert.equal(confirms[confirms.length - 1].title, 'Move driver assignment?', 'Conflict asks for confirmation');
    await settle(6);
    assert.equal(element('busSecurityModal').hidden, false, 'Retry asks for a fresh code');
    assert.match(element('busSecuritySummary').textContent, /Move driver assignment/, 'Retry is labeled');
    await submitSecurity('246810');
    assert.equal(editCalls.length, 3, 'Retry posts after confirmation');
    assert.equal(editCalls[2].allow_move, '1', 'Retry carries allow_move');
    assert.ok(alerts.some(alert => alert.title === 'Bus updated'), 'Conflict retry confirms success');

    /* ------------------ 5.7 archive, restore, export ------------------ */

    confirmInputValue = 'Unit retired pending replacement.';
    clickAction('busList', 'archive', '0');
    await settle(6);
    await submitSecurity('246810');
    assert.equal(archiveCalls.length, 1, 'Archive posts once');
    assert.equal(String(archiveCalls[0].bus_ref), '1', 'Archive posts the reference');
    assert.equal(archiveCalls[0].reason, 'Unit retired pending replacement.', 'Archive posts the reason');
    assert.ok(alerts.some(alert => alert.title === 'Bus archived'), 'Archive confirms success');
    assert.match(element('busList').innerHTML, /data-action="restore"/, 'Archived row now exposes Restore');

    element('busArchivedBtn').listeners.click();
    await settle(6);
    assert.match(element('busArchivedList').innerHTML, /BUS-001/, 'Archived modal lists the bus');
    assert.match(element('busArchivedList').innerHTML, /Reason:/, 'Archived bus exposes archive reason');
    assert.match(element('busArchivedList').innerHTML, /Archived:/, 'Archived bus exposes archive date, not a creation-date substitute');
    assert.match(element('busArchivedList').innerHTML, /Unavailable/, 'Unknown legacy archive metadata is labeled');
    assert.match(element('busArchivedList').innerHTML, /data-action="archived-restore"/, 'Archived modal offers Restore');
    assert.match(element('busArchivedList').innerHTML, /aria-label="Restore bus BUS-001"/, 'Archived restore has an accessible label');

    // Restore opens the editable details form; nothing is posted yet.
    element('busArchivedList').listeners.click({
        target: {
            closest: selector => (selector === '[data-action]'
                ? {getAttribute: name => ({'data-action': 'archived-restore', 'data-ref': '1', 'data-name': 'BUS-001'})[name]}
                : null)
        }
    });
    await settle(6);
    assert.equal(element('busRestoreModal').hidden, false, 'Restore opens the details form');
    assert.equal(element('busRestoreTitle').textContent, 'Restore BUS-001', 'Restore form names the archived bus');
    assert.match(element('busRestoreSteps').innerHTML, /Basic Information/, 'Restore form renders the wizard steps');
    assert.match(element('busRestoreFields').innerHTML, /data-restore-field="plate_number"/, 'Restore form renders the plate field');
    assert.doesNotMatch(element('busRestoreFields').innerHTML, /data-restore-field="status"/, 'Lifecycle status is not editable in the restore form');
    assert.doesNotMatch(element('busRestoreFields').innerHTML, /data-restore-field="operational_status"/, 'Operational status is not editable in the restore form');
    assert.match(element('busRestoreFields').innerHTML, /Bus ID \(read-only\)/, 'Bus ID is shown read-only in the restore form');
    assert.match(element('busRestoreFields').innerHTML, /QR credential \(read-only\)/, 'QR credential is shown read-only in the restore form');
    assert.equal(restoreCalls.length, 0, 'Opening the restore form posts nothing');

    const restoreNode = name => element('busRestoreFields').querySelector('[data-restore-field="' + name + '"]');
    assert.equal(restoreNode('plate_number').value, 'NCR 1234', 'Restore form is pre-filled from the archived bus');
    assert.equal(restoreNode('from_terminal_id').value, '1', 'Restore form carries the stored terminal ids');
    assert.equal(restoreNode('to_terminal_id').value, '2', 'Restore form carries the stored destination terminal');
    assert.equal(restoreNode('accessible_features').value, 'Priority seats', 'Restore form carries the stored accessible features');
    assert.equal(restoreNode('driver').value, 'DRV-001', 'Restore form carries the stored driver');
    ['plate_number', 'unit_number', 'bus_type', 'seats', 'standing_capacity', 'engine_number', 'chassis_number',
        'fuel_type', 'transmission_type', 'air_conditioning', 'vehicle_ownership', 'driver',
        'assigned_inspector_id', 'schedule', 'from_terminal_id', 'to_terminal_id', 'accessible_features',
        'registration_month', 'registration_expiry', 'insurance_expiry', 'inspection_date', 'next_inspection_date'
    ].forEach(name => {
        const node = restoreNode(name);
        assert.ok(node, 'Restore field exists: ' + name);
        assert.equal(node.getAttribute('readonly'), null, 'Restore field accepts input: ' + name);
        assert.equal(node.disabled, false, 'Restore field is enabled: ' + name);
    });

    // Unsaved-changes guard: closing with dirty fields asks first.
    element('busRestoreFields').listeners.input();
    confirmCancelNext = true;
    let restorePrevented = false;
    element('busRestoreModal').listeners['hide.bs.modal']({preventDefault() { restorePrevented = true; }});
    await settle(6);
    assert.equal(restorePrevented, true, 'Restore form guards unsaved changes');
    assert.equal(confirms[confirms.length - 1].title, 'Discard unsaved changes?', 'Restore guard asks before discarding');
    assert.equal(element('busRestoreModal').hidden, false, 'Cancelling the guard keeps the restore form open');
    confirmCancelNext = false;
    element('busRestoreModal').listeners['hide.bs.modal']({preventDefault() {}});
    await settle(4);
    assert.equal(element('busRestoreModal').hidden, true, 'Confirming the guard closes the restore form');

    // Reopen the form for the remaining restore checks.
    element('busArchivedList').listeners.click({
        target: {
            closest: selector => (selector === '[data-action]'
                ? {getAttribute: name => ({'data-action': 'archived-restore', 'data-ref': '1', 'data-name': 'BUS-001'})[name]}
                : null)
        }
    });
    await settle(6);
    assert.equal(element('busRestoreModal').hidden, false, 'Restore form reopens after the guard');
    assert.equal(restoreNode('plate_number').value, 'NCR 1234', 'Reopened form is pre-filled again');

    // Cancelling the reason step and a too-short reason post nothing.
    confirmCancelNext = true;
    element('busRestoreSave').listeners.click();
    await settle(6);
    assert.equal(restoreCalls.length, 0, 'Cancelling the restore posts nothing');
    assert.equal(element('busRestoreModal').hidden, false, 'Cancelling keeps the restore form open');

    confirmCancelNext = false;
    confirmInputValue = 'no';
    element('busRestoreSave').listeners.click();
    await settle(6);
    assert.equal(restoreCalls.length, 0, 'A too-short reason posts nothing');
    assert.equal(element('busRestoreModal').hidden, false, 'Rejected reason keeps the restore form open');

    // A wrong security code posts nothing; the correct code saves the details.
    restoreNode('schedule').value = '08:00 AM - 08:00 PM';
    await settle(2);
    confirmInputValue = 'Archived in error; details verified on site.';
    element('busRestoreSave').listeners.click();
    await settle(6);
    assert.equal(element('busSecurityModal').hidden, false, 'Restore asks for the security code');
    assert.match(element('busSecuritySummary').textContent, /Restore BUS-001/, 'Security modal names the restore action');
    await submitSecurity('999999');
    assert.equal(restoreCalls.length, 0, 'A wrong code does not reach the restore endpoint');

    await submitSecurity('246810');
    await settle(8);
    assert.equal(restoreCalls.length, 1, 'Archived modal restore posts once with the correct code');
    const restoreBody = restoreCalls[0];
    assert.equal(String(restoreBody.bus_ref), '1', 'Restore posts the bus reference');
    assert.equal(restoreBody.plate_number, 'NCR 1234', 'Restore posts the pre-filled details');
    assert.equal(restoreBody.schedule, '08:00 AM - 08:00 PM', 'Restore posts the edited schedule');
    assert.equal(restoreBody.reason, 'Archived in error; details verified on site.', 'Restore posts the mandatory reason');
    assert.equal(restoreBody.security_token, 'tok-verify', 'Restore posts the verified token');
    assert.equal(restoreBody.status, undefined, 'Restore never posts a lifecycle status');
    assert.equal(restoreBody.operational_status, undefined, 'Restore never posts an operational status');
    assert.deepEqual(Object.keys(restoreApplied), ['1'], 'Only the chosen bus is restored');
    assert.equal(element('busRestoreModal').hidden, true, 'Successful restore closes the form');
    assert.ok(alerts.some(alert => alert.title === 'Bus restored'), 'Restore confirms success');
    assert.match(element('busArchivedList').innerHTML, /No archived buses/, 'Archived list refreshes after the restore');
    assert.match(element('busList').innerHTML, /BUS-002/, 'Other buses remain in the refreshed directory');
    assert.match(element('busList').innerHTML, /is-inactive">Inactive<\/span>/, 'Other lifecycle statuses stay unchanged');

    // Row-level restore opens the same form.
    clickAction('busList', 'archive', '0');
    await settle(6);
    await submitSecurity('246810');
    assert.equal(archiveCalls.length, 2, 'Row archive posts again');
    clickAction('busList', 'restore', '0');
    await settle(6);
    assert.equal(element('busRestoreModal').hidden, false, 'Row restore opens the details form');
    element('busRestoreSave').listeners.click();
    await settle(6);
    await submitSecurity('246810');
    await settle(8);
    assert.equal(restoreCalls.length, 2, 'Row restore posts once');
    assert.equal(String(restoreCalls[1].bus_ref), '1', 'Row restore posts the reference');
    assert.equal(restoreCalls[1].status, undefined, 'Row restore never posts a lifecycle status');

    // Export respects the active filter and reports empty results.
    createdLinks.length = 0;
    element('busStatus').value = 'archived';
    element('busStatus').listeners.change();
    await settle();
    element('busExport').listeners.click();
    await settle(6);
    assert.equal(createdLinks.length, 1, 'Filtered export downloads a file');
    assert.equal(createdLinks[0].download, 'bus-management-archived.csv', 'Export filename reflects the filter');
    assert.match(exportUrls[exportUrls.length - 1], /status=archived/, 'Export sends the active filters');
    element('busStatus').value = '';
    element('busStatus').listeners.change();
    await settle();

    exportMode = 'json';
    element('busExport').listeners.click();
    await settle(6);
    assert.equal(createdLinks.length, 1, 'Empty export downloads nothing');
    assert.ok(alerts.some(alert => alert.title === 'Export failed'), 'Export failure notifies the user');
    exportMode = 'csv';

    /* --------------------------- 5.6 bulk QR --------------------------- */

    // A newly imported bus has no QR yet (the earlier prepare already ran once).
    bulkMissing = 1;
    const backfillsBefore = backfillRuns;
    element('busBulkQr').listeners.click();
    await settle(6);
    assert.match(element('busBulkSummary').innerHTML, /have no QR code yet/, 'Bulk modal counts buses missing credentials');
    assert.match(element('busBulkList').innerHTML, /BUS-005/, 'Bulk modal lists the bus without a QR');
    element('busBulkPrepare').listeners.click();
    await settle(8);
    assert.equal(backfillRuns, backfillsBefore + 1, 'Bulk prepare runs the backfill once');
    assert.ok(alerts.some(alert => alert.title === 'QR preparation complete'), 'Bulk prepare confirms success');
    assert.match(element('busBulkSummary').innerHTML, /already has a QR code/, 'Re-check finds nothing missing');

    element('busBulkPdf').listeners.click();
    await new Promise(resolve => setTimeout(resolve, 300));
    await settle(6);
    assert.match(String(pdfSaves[pdfSaves.length - 1]), /^bus-qr-codes-\d{4}-\d{2}-\d{2}\.pdf$/, 'Bulk PDF is saved with a dated name');
    const bulkPdf = pdfInstances[pdfInstances.length - 1];
    assert.ok(bulkPdf.texts.indexOf('Bus ID: BUS-001') !== -1, 'Bulk PDF labels the first bus');
    assert.ok(bulkPdf.texts.indexOf('Bus ID: BUS-002') !== -1, 'Bulk PDF labels the second bus');
    assert.ok(bulkPdf.texts.indexOf('Unit: 1201') !== -1, 'Bulk PDF labels the unit number');
    assert.equal(bulkPdf.images.length, 2, 'Bulk PDF embeds one QR per bus');

    /* ---------------------- security settings modal ---------------------- */

    assert.equal(element('busSecurityChip').hidden, false, 'Security chip reflects the configured state');
    assert.equal(element('busSecurityChip').textContent, 'On', 'Security chip shows On');
    element('busSecurityBtn').listeners.click();
    await settle(6);
    assert.equal(element('busSecSettingsModal').hidden, false, 'Security settings modal opens for a Super Admin');
    assert.equal(element('busSecSettingsState').textContent, 'Security code is set', 'Settings modal shows the configured state');
    assert.match(element('busSecActivity').innerHTML, /verified/, 'Security activity loads');
    element('busSecCurrentCode').value = '246810';
    element('busSecNewCode').value = '123';
    element('busSecConfirmCode').value = '123';
    element('busSecPassword').value = 'secret';
    element('busSecSettingsSave').listeners.click();
    assert.equal(element('busSecSettingsError').hidden, false, 'Short security code is rejected');
    assert.match(element('busSecSettingsError').textContent, /at least 6 characters/, 'Short-code message is explicit');
    element('busSecNewCode').value = '246810';
    element('busSecConfirmCode').value = '246810';
    element('busSecSettingsSave').listeners.click();
    await settle(6);
    assert.ok(alerts.some(alert => alert.title === 'Security code updated'), 'Security settings save confirms success');

    console.log('Bus Management UI checks passed: summary/filters/table/pager, wizard validation + guard + duplicates, six profile tabs, QR render/download/print/replace/revoke, add/edit saves with security verification, driver-conflict retry, archive/restore, filtered export, bulk QR PDF, security settings.');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
