(function () {
    'use strict';

    var CFG = window.BUS_CONFIG || {};
    var SECURITY_URL = '/admin/api/RoleManagement/Security.php';
    var HASH_SKIP = ['security_token', 'action', 'verify_action', '_csrf', '_csrf_token', 'code', 'current_code', 'new_code', 'confirm_code'];
    var MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    var state = {
        search: '',
        status: '',
        busType: '',
        route: '',
        driver: '',
        createdFrom: '',
        createdTo: '',
        sort: 'bus_asc',
        page: 1,
        perPage: 10,
        items: [],
        counts: {},
        summary: {},
        total: 0,
        pages: 1,
        isSuper: !!CFG.isSuper,
        busy: false,
        detailIndex: null,
        detailTab: 'overview',
        openBus: String(CFG.openBus || ''),
        wizard: {add: 0, edit: 0, restore: 0},
        addValues: null,
        editValues: null,
        restoreValues: null,
        addBusId: '',
        busIdRequest: 0,
        submittingAdd: false,
        submittingEdit: false,
        submittingRestore: false,
        formDirty: {add: false, edit: false, restore: false},
        formForceClose: {add: false, edit: false, restore: false},
        options: {},
        optionsLoaded: false,
        optionsLoading: false,
        optionsQueue: [],
        qrCache: {},
        historyCache: {},
        archivedItems: {},
        pendingSecurity: null,
        pendingSecuritySettings: null,
        duplicateTimer: null,
        bulkPayloads: null
    };

    function el(id) { return document.getElementById(id); }

    function esc(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function fmtDate(value, withTime) {
        var raw = String(value || '').trim();
        if (!raw) return '—';
        var parts = raw.split(' ');
        var d = parts[0].split('-');
        if (d.length !== 3) return raw;
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var m = parseInt(d[1], 10), day = parseInt(d[2], 10);
        if (!m || !day) return raw;
        var out = months[m - 1] + ' ' + day + ', ' + d[0];
        if (withTime && parts[1]) {
            var t = parts[1].split(':');
            var hh = parseInt(t[0], 10);
            if (!isNaN(hh)) {
                var ampm = hh >= 12 ? 'PM' : 'AM';
                var h12 = ((hh + 11) % 12) + 1;
                out += ' · ' + h12 + ':' + (t[1] || '00') + ' ' + ampm;
            }
        }
        return out;
    }

    function displayValue(value) {
        if (value === null || value === undefined) return '<span class="rmg-missing">Not recorded</span>';
        var text = String(value).trim();
        if (text === '') return '<span class="rmg-missing">Not recorded</span>';
        return esc(text);
    }

    function initials(value) {
        var parts = String(value || '').trim().split(/\s+/);
        var out = '';
        parts.slice(0, 2).forEach(function (p) { out += (p.charAt(0) || '').toUpperCase(); });
        return out || 'BUS';
    }

    function statusClass(classification) {
        if (classification === 'active') return 'is-active';
        if (classification === 'maintenance' || classification === 'inactive') return 'is-inactive';
        if (classification === 'archived') return 'is-other';
        return 'is-pending';
    }

    function debounce(fn, wait) {
        var handle = null;
        return function () {
            clearTimeout(handle);
            var args = arguments;
            handle = setTimeout(function () { fn.apply(null, args); }, wait);
        };
    }

    function busNotify(kind, title, text) {
        if (typeof window.showAlert === 'function') {
            window.showAlert(kind === 'success' ? 'success' : 'error', title || '', text || '');
            return;
        }
        console.error('Bus Management custom alert component unavailable:', title, text);
    }

    /* --------------------------- security authorization --------------------------- */

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

    function securityTargetWithRef(ref) {
        var value = String(ref === null || ref === undefined ? '' : ref).trim();
        return (value !== '' && value !== '0') ? 'bus:' + value : 'bus';
    }

    function fetchSecurityStatus() {
        return fetch(SECURITY_URL + '?action=status', {credentials: 'same-origin', cache: 'no-store'})
            .then(function (r) { return r.json(); });
    }

    function updateSecurityChip(status) {
        var chip = el('busSecurityChip');
        if (!chip) return;
        if (!status || status.success === false) { chip.hidden = true; return; }
        chip.hidden = false;
        chip.textContent = status.configured ? 'On' : 'Not set';
        chip.className = 'rmg-sec-chip ' + (status.configured ? 'is-on' : 'is-off');
    }

    function beginSecurityVerify(action, target, entries, display) {
        if (state.pendingSecurity) {
            busNotify('warning', 'Verification in progress', 'Finish the open security authorization first.');
            return Promise.resolve(null);
        }
        return hashEntries(entries).then(function (hash) {
            return fetchSecurityStatus().then(function (status) {
                if (!status || status.success === false) {
                    busNotify('error', 'Security check failed', 'Could not check the security settings. Please try again.');
                    return null;
                }
                updateSecurityChip(status);
                if (!status.configured) {
                    busNotify('error', 'Security code not set up', 'A Super Admin must set the Security Authorization Code before buses can be changed.');
                    return null;
                }
                if (status.locked_until) {
                    busNotify('error', 'Verification temporarily locked', 'Too many incorrect attempts. Please try again after ' + status.locked_until + ' UTC.');
                    return null;
                }
                return new Promise(function (resolve) {
                    state.pendingSecurity = {action: action, target: target, hash: hash, resolve: resolve, busy: false};
                    el('busSecuritySummary').textContent = display || action;
                    el('busSecurityCode').value = '';
                    el('busSecurityError').hidden = true;
                    bootstrap.Modal.getOrCreateInstance(el('busSecurityModal')).show();
                    setTimeout(function () { var input = el('busSecurityCode'); if (input) input.focus(); }, 350);
                });
            });
        });
    }

    function submitSecurityVerify() {
        if (!state.pendingSecurity || state.pendingSecurity.busy) return;
        var code = el('busSecurityCode').value;
        if (String(code).trim() === '') {
            showSecurityError('Enter the Security Authorization Code.');
            return;
        }
        state.pendingSecurity.busy = true;
        el('busSecurityConfirm').disabled = true;

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
                el('busSecurityCode').value = '';
                el('busSecurityCode').focus();
                return;
            }
            var finish = state.pendingSecurity ? state.pendingSecurity.resolve : null;
            state.pendingSecurity = null;
            hideSecurityModal();
            if (json.code === 'locked') busNotify('error', 'Verification locked', json.message || 'Too many incorrect attempts.');
            else if (json.code === 'security_not_configured') busNotify('error', 'Security code not set up', json.message || '');
            else if (json.code === 'role_denied') busNotify('error', 'Not allowed', json.message || 'You are not allowed to manage buses.');
            else busNotify('error', 'Verification failed', json.message || 'Could not verify the code. Please try again.');
            if (finish) finish(null);
        }).catch(function () {
            showSecurityError('Could not reach the server. Please try again.');
        }).finally(function () {
            if (state.pendingSecurity) state.pendingSecurity.busy = false;
            var btn = el('busSecurityConfirm');
            if (btn) btn.disabled = false;
        });
    }

    function showSecurityError(message) {
        var box = el('busSecurityError');
        box.textContent = message;
        box.hidden = false;
    }

    function hideSecurityModal() {
        var instance = bootstrap.Modal.getInstance(el('busSecurityModal'));
        if (instance) instance.hide();
    }

    function openSecurityButton() {
        if (state.isSuper) {
            openSecuritySettings();
            return;
        }
        fetchSecurityStatus().then(function (status) {
            var configured = !!(status && status.configured);
            busNotify(
                configured ? 'success' : 'error',
                'Security Authorization',
                configured
                    ? 'The Security Authorization Code is set. You will be asked for it for every bus change.'
                    : 'A Super Admin must set the Security Authorization Code before buses can be changed.'
            );
        }).catch(function () {
            busNotify('error', 'Security Authorization', 'Could not check the security settings. Please try again.');
        });
    }

    function openSecuritySettings() {
        fetchSecurityStatus().then(function (status) {
            var configured = !!(status && status.configured);
            var stateEl = el('busSecSettingsState');
            stateEl.textContent = configured ? 'Security code is set' : 'Security code is not set';
            stateEl.className = 'rmg-sec-state ' + (configured ? 'is-on' : 'is-off');
            var updated = status && status.last_updated ? status.last_updated : null;
            el('busSecSettingsMeta').textContent = configured && updated && updated.at
                ? 'Last updated ' + fmtDate(updated.at, true) + (updated.by ? ' by user #' + updated.by : '')
                : 'No code has been set yet. Account and bus changes are blocked until one is set.';
            el('busSecCurrentWrap').hidden = !configured;
            ['busSecCurrentCode', 'busSecNewCode', 'busSecConfirmCode', 'busSecPassword'].forEach(function (id) {
                el(id).value = '';
            });
            el('busSecSettingsError').hidden = true;
            el('busSecActivity').innerHTML = '<div class="rmg-sec-activity-empty">Loading activity…</div>';
            bootstrap.Modal.getOrCreateInstance(el('busSecSettingsModal')).show();
            loadSecurityActivity();
        }).catch(function () {
            busNotify('error', 'Security settings', 'Could not load the security settings. Please try again.');
        });
    }

    function loadSecurityActivity() {
        fetch(SECURITY_URL + '?action=activity', {credentials: 'same-origin', cache: 'no-store'})
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var host = el('busSecActivity');
                var events = (res && res.success && Array.isArray(res.events)) ? res.events : [];
                if (!events.length) {
                    host.innerHTML = '<div class="rmg-sec-activity-empty">No security activity yet.</div>';
                    return;
                }
                host.innerHTML = '<ul>' + events.map(function (event) {
                    var tone = (event.event === 'failed' || event.event === 'blocked') ? ' is-bad'
                        : ((event.event === 'used' || event.event === 'verified') ? ' is-good' : '');
                    return '<li class="rmg-sec-event' + tone + '">' +
                        '<span class="rmg-sec-event-name">' + esc(event.event) + '</span>' +
                        '<span class="rmg-sec-event-detail">' + esc(event.detail || '') + '</span>' +
                        '<span class="rmg-sec-event-meta">' + esc(event.username || 'unknown') + ' · ' + esc(fmtDate(event.at, true)) + '</span>' +
                        '</li>';
                }).join('') + '</ul>';
            })
            .catch(function () {
                el('busSecActivity').innerHTML = '<div class="rmg-sec-activity-empty">Could not load activity.</div>';
            });
    }

    function saveSecuritySettings() {
        var configured = !el('busSecCurrentWrap').hidden;
        var currentCode = el('busSecCurrentCode').value;
        var newCode = el('busSecNewCode').value;
        var confirmCode = el('busSecConfirmCode').value;
        var password = el('busSecPassword').value;
        var error = '';
        if (configured && String(currentCode).trim() === '') error = 'Enter the current security code.';
        else if (String(newCode).length < 6) error = 'The new code must be at least 6 characters.';
        else if (newCode !== confirmCode) error = 'The new code and its confirmation do not match.';
        else if (String(password).trim() === '') error = 'Enter your account password to confirm this change.';
        if (error) {
            var box = el('busSecSettingsError');
            box.textContent = error;
            box.hidden = false;
            return;
        }
        var button = el('busSecSettingsSave');
        button.disabled = true;
        fetch(SECURITY_URL, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'set',
                password: password,
                current_code: currentCode,
                new_code: newCode,
                confirm_code: confirmCode
            }),
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (json) {
            json = json || {};
            if (json.success === false) {
                var box = el('busSecSettingsError');
                box.textContent = json.message || 'Could not update the security code.';
                box.hidden = false;
                return;
            }
            busNotify('success', 'Security code updated', json.message || 'The Security Authorization Code was updated.');
            ['busSecCurrentCode', 'busSecNewCode', 'busSecConfirmCode', 'busSecPassword'].forEach(function (id) {
                el(id).value = '';
            });
            el('busSecSettingsError').hidden = true;
            el('busSecCurrentWrap').hidden = false;
            fetchSecurityStatus().then(updateSecurityChip).catch(function () {});
            loadSecurityActivity();
        }).catch(function () {
            var box = el('busSecSettingsError');
            box.textContent = 'Could not reach the server. Please try again.';
            box.hidden = false;
        }).finally(function () {
            button.disabled = false;
        });
    }

    /* ------------------------------ options loading ------------------------------ */

    function optionList(values) {
        return values.map(function (value) { return {id: value, label: value === '' ? '—' : value}; });
    }

    function ensureBusOptions(callback) {
        if (state.optionsLoaded) { callback(); return; }
        if (state.optionsLoading) { state.optionsQueue.push(callback); return; }
        state.optionsLoading = true;
        state.optionsQueue = [callback];

        var requests = [
            {key: 'lists', url: '/admin/api/Bus/Options.php?type=lists', shape: 'lists'},
            {key: 'types', url: '/admin/api/Bus/Options.php?type=types', shape: 'options'},
            {key: 'manufacturers', url: '/admin/api/Bus/Options.php?type=manufacturers', shape: 'options'},
            {key: 'models', url: '/admin/api/Bus/Options.php?type=models', shape: 'options'},
            {key: 'years', url: '/admin/api/Bus/Options.php?type=years', shape: 'options'},
            {key: 'inspectors', url: '/admin/api/Bus/Options.php?type=inspectors', shape: 'options'},
            {key: 'routes', url: '/admin/api/RoleManagement/Options.php?type=routes', shape: 'options'},
            {key: 'terminals', url: '/admin/api/RoleManagement/Options.php?type=terminals', shape: 'options'},
            {key: 'drivers', url: '/admin/api/Schedule/ScheduleApi.php?action=get_drivers', shape: 'drivers'}
        ];
        var pending = requests.length;

        function finish() {
            pending--;
            if (pending > 0) return;
            state.optionsLoaded = true;
            state.optionsLoading = false;
            var queue = state.optionsQueue || [];
            state.optionsQueue = [];
            queue.forEach(function (fn) { if (typeof fn === 'function') fn(); });
        }

        requests.forEach(function (request) {
            fetch(request.url, {credentials: 'same-origin', cache: 'no-store'})
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || res.success === false) throw new Error('empty');
                    if (request.shape === 'lists') {
                        state.options.lists = res.lists || {};
                    } else if (request.shape === 'drivers') {
                        var rows = Array.isArray(res.data) ? res.data : (Array.isArray(res.drivers) ? res.drivers : []);
                        state.options.drivers = rows.map(function (row) {
                            return {id: String(row.driver_id || ''), label: String(row.full_name || row.driver_id || '')};
                        }).filter(function (option) { return option.id !== ''; });
                    } else {
                        state.options[request.key] = Array.isArray(res.options) ? res.options : [];
                    }
                })
                .catch(function () {
                    if (request.shape === 'lists') state.options.lists = {};
                    else state.options[request.key] = [];
                })
                .finally(finish);
        });
    }

    function populateFilterOptions() {
        var types = el('busType');
        if (types) {
            var typeValue = state.busType;
            types.innerHTML = '<option value="">All types</option>' + (state.options.types || []).map(function (option) {
                return '<option value="' + esc(option.id) + '">' + esc(option.label) + '</option>';
            }).join('');
            types.value = typeValue;
        }
        var routes = el('busRoute');
        if (routes) {
            var routeValue = state.route;
            routes.innerHTML = '<option value="">All routes</option>' + (state.options.routes || []).map(function (option) {
                return '<option value="' + esc(option.id) + '">' + esc(option.label) + '</option>';
            }).join('');
            routes.value = routeValue;
        }
        var drivers = el('busDriver');
        if (drivers) {
            var driverValue = state.driver;
            drivers.innerHTML = '<option value="">All drivers</option>' + (state.options.drivers || []).map(function (option) {
                return '<option value="' + esc(option.id) + '">' + esc(option.label) + '</option>';
            }).join('');
            drivers.value = driverValue;
        }
    }

    /* ------------------------------ summary + list ------------------------------ */

    var SUMMARY_DEFS = [
        {key: 'total', label: 'Total Buses', icon: 'bi-bus-front', tone: 'total'},
        {key: 'active', label: 'Active', icon: 'bi-check-circle', tone: 'active'},
        {key: 'inactive', label: 'Inactive', icon: 'bi-dash-circle', tone: 'inactive'},
        {key: 'maintenance', label: 'Maintenance', icon: 'bi-wrench-adjustable', tone: 'inactive'},
        {key: 'archived', label: 'Archived', icon: 'bi-archive', tone: 'archived'}
    ];

    function renderSummary() {
        var host = el('busSummary');
        if (!host) return;
        var summary = state.summary || {};
        host.innerHTML = SUMMARY_DEFS.map(function (def) {
            var value = summary[def.key] !== undefined ? summary[def.key] : '—';
            return '<div class="rmg-sum-card tone-' + def.tone + '">' +
                '<span class="rmg-sum-icon"><i class="bi ' + def.icon + '"></i></span>' +
                '<div class="rmg-sum-body"><div class="rmg-sum-label">' + esc(def.label) + '</div>' +
                '<div class="rmg-sum-value">' + value + '</div></div></div>';
        }).join('');
    }

    function renderSkeleton() {
        var host = el('busList');
        if (!host) return;
        var rows = '';
        for (var i = 0; i < 6; i++) {
            rows += '<div class="rmg-skel-row">' +
                '<span class="rmg-skel rmg-skel-avatar"></span>' +
                '<span class="rmg-skel rmg-skel-line"></span>' +
                '<span class="rmg-skel rmg-skel-line short"></span>' +
                '<span class="rmg-skel rmg-skel-chip"></span></div>';
        }
        host.innerHTML = '<div class="rmg-skel-wrap" aria-busy="true" aria-label="Loading buses">' + rows + '</div>';
    }

    function rowActionsHtml(item, index) {
        var actions = '<div class="rmg-actions">';
        actions += '<button type="button" class="rmg-icon-btn is-view" data-action="details" data-index="' + index + '" title="View profile" aria-label="View profile for bus ' + esc(item.bus_id) + '"><i class="bi bi-eye"></i></button>';
        actions += '<button type="button" class="rmg-icon-btn is-edit" data-action="edit" data-index="' + index + '" title="Edit bus" aria-label="Edit bus ' + esc(item.bus_id) + '"><i class="bi bi-pencil"></i></button>';
        if (item.classification === 'archived') {
            actions += '<button type="button" class="rmg-icon-btn is-restore" data-action="restore" data-index="' + index + '" title="Restore bus" aria-label="Restore bus ' + esc(item.bus_id) + '"><i class="bi bi-arrow-counterclockwise"></i></button>';
        } else {
            actions += '<button type="button" class="rmg-icon-btn is-archive" data-action="archive" data-index="' + index + '" title="Archive bus" aria-label="Archive bus ' + esc(item.bus_id) + '"><i class="bi bi-archive"></i></button>';
        }
        actions += '<button type="button" class="rmg-icon-btn is-history" data-action="qr" data-index="' + index + '" title="QR code" aria-label="QR code for bus ' + esc(item.bus_id) + '"><i class="bi bi-qr-code"></i></button>';
        actions += '</div>';
        return actions;
    }

    function expiryChip(stateValue, label) {
        if (stateValue === 'expired') return '<span class="bus-expiry-warning">' + esc(label) + ' expired</span>';
        if (stateValue === 'expiring') return '<span class="bus-expiry-soon">' + esc(label) + ' expiring soon</span>';
        return '';
    }

    function renderList() {
        var host = el('busList');
        if (!host) return;
        if (!state.items.length) {
            host.innerHTML = '<div class="rmg-state"><i class="bi bi-inbox"></i><h6>No buses found</h6>' +
                'Try a different filter or register a bus.' +
                '<button type="button" class="rmg-btn rmg-btn-sm" id="busEmptyClear" style="margin-top:12px;"><i class="bi bi-x-circle"></i> Clear filters</button></div>';
            return;
        }

        var rows = state.items.map(function (item, index) {
            var warning = expiryChip(item.registration_state, 'Registration')
                || expiryChip(item.insurance_state, 'Insurance')
                || expiryChip(item.inspection_state, 'Inspection');
            return '<tr data-index="' + index + '">' +
                '<td data-label="Bus"><div class="rmg-person"><div class="rmg-avatar"><i class="bi bi-bus-front"></i></div><div style="min-width:0;"><div class="rmg-person-name">' + esc(item.bus_id) + '</div><div class="rmg-person-sub">' + esc(item.unit_number ? 'Unit ' + item.unit_number : (item.plate || '—')) + '</div></div></div></td>' +
                '<td data-label="Plate" class="rmg-td-id">' + esc(item.plate || '—') + '</td>' +
                '<td data-label="Type">' + esc(item.bus_type || '—') + '</td>' +
                '<td data-label="Status"><span class="rmg-status ' + statusClass(item.classification) + '">' + esc(item.classification_label) + '</span>' +
                (warning ? '<div style="font-size:.62rem;margin-top:2px;">' + warning + '</div>' : '') + '</td>' +
                '<td data-label="Operational" class="rmg-td-operational">' + (String(item.operational_status || '').trim() !== '' ? esc(item.operational_status) : '<span class="rmg-missing">Not recorded</span>') + '</td>' +
                '<td data-label="Actions" class="rmg-td-actions">' + rowActionsHtml(item, index) + '</td>' +
                '</tr>';
        }).join('');

        host.innerHTML = '<div class="rmg-table-wrap"><table class="rmg-table"><thead><tr>' +
            '<th>Bus</th><th>Plate</th><th>Type</th><th>Status</th><th>Operational</th><th class="rmg-th-actions">Actions</th>' +
            '</tr></thead><tbody>' + rows + '</tbody></table></div>';
    }

    function renderPager() {
        el('busCount').innerHTML = '<i class="bi bi-bus-front"></i> ' + state.total + ' bus' + (state.total === 1 ? '' : 'es');
        var start = state.total === 0 ? 0 : (state.page - 1) * state.perPage + 1;
        var end = Math.min(state.total, state.page * state.perPage);
        el('busPgInfo').textContent = 'Showing ' + start + '–' + end + ' of ' + state.total;

        var host = el('busPager');
        if (state.pages <= 1) { host.innerHTML = ''; return; }
        var html = '<button type="button" class="rmg-pg-btn" data-page="' + (state.page - 1) + '"' + (state.page <= 1 ? ' disabled' : '') + '><i class="bi bi-chevron-left"></i></button>';
        var from = Math.max(1, state.page - 2);
        var to = Math.min(state.pages, from + 4);
        from = Math.max(1, to - 4);
        for (var p = from; p <= to; p++) {
            html += '<button type="button" class="rmg-pg-btn' + (p === state.page ? ' active' : '') + '" data-page="' + p + '">' + p + '</button>';
        }
        html += '<button type="button" class="rmg-pg-btn" data-page="' + (state.page + 1) + '"' + (state.page >= state.pages ? ' disabled' : '') + '><i class="bi bi-chevron-right"></i></button>';
        host.innerHTML = html;
    }

    function syncUrl() {
        if (!window.history || !history.replaceState) return;
        var params = new URLSearchParams();
        if (state.search) params.set('search', state.search);
        if (state.status) params.set('status', state.status);
        if (state.busType) params.set('type', state.busType);
        if (state.route) params.set('route', state.route);
        if (state.driver) params.set('driver', state.driver);
        if (state.createdFrom) params.set('from', state.createdFrom);
        if (state.createdTo) params.set('to', state.createdTo);
        if (state.sort && state.sort !== 'bus_asc') params.set('sort', state.sort);
        if (state.page > 1) params.set('page', String(state.page));
        var qs = params.toString();
        var base = (window.location && window.location.pathname) ? window.location.pathname : '/admin/bus';
        history.replaceState(null, '', base + (qs ? '?' + qs : ''));
    }

    function load() {
        state.busy = true;
        renderSkeleton();
        var params = new URLSearchParams();
        if (state.search) params.set('search', state.search);
        if (state.status) params.set('status', state.status);
        if (state.busType) params.set('bus_type', state.busType);
        if (state.route) params.set('route', state.route);
        if (state.driver) params.set('driver', state.driver);
        if (state.createdFrom) params.set('registered_from', state.createdFrom);
        if (state.createdTo) params.set('registered_to', state.createdTo);
        params.set('sort', state.sort);
        params.set('page', String(state.page));
        params.set('per_page', String(state.perPage));

        fetch('/admin/api/Bus/list.php?' + params.toString(), {cache: 'no-store', credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) throw new Error(res.message || 'Could not load the bus directory.');
                state.items = res.data || [];
                state.counts = res.counts || {};
                state.summary = res.summary || {};
                state.total = res.total || 0;
                state.pages = res.pages || 1;
                state.page = res.page || 1;
                state.isSuper = res.is_super !== undefined ? !!res.is_super : state.isSuper;
                renderSummary();
                populateFilterOptions();
                if (res.errors && res.errors.buses) {
                    el('busList').innerHTML = '<div class="rmg-state error"><i class="bi bi-exclamation-circle"></i><h6>Buses could not be loaded</h6>' + esc(res.errors.buses) + '</div>';
                } else {
                    renderList();
                }
                renderPager();
                syncUrl();

                if (state.openBus) {
                    var target = String(state.openBus);
                    var found = -1;
                    state.items.forEach(function (item, i) {
                        if (String(item.bus_id) === target || String(item.ref) === target) found = i;
                    });
                    if (found >= 0) openDetail(found);
                    state.openBus = '';
                }
            })
            .catch(function (err) {
                el('busList').innerHTML = '<div class="rmg-state error"><i class="bi bi-exclamation-circle"></i><h6>Could not load buses</h6>' + esc(err.message || 'Please try again.') + '</div>';
            })
            .finally(function () {
                state.busy = false;
                var emptyClear = el('busEmptyClear');
                if (emptyClear) {
                    emptyClear.addEventListener('click', clearFilters);
                }
            });
    }

    function clearFilters() {
        state.search = '';
        state.status = '';
        state.busType = '';
        state.route = '';
        state.driver = '';
        state.createdFrom = '';
        state.createdTo = '';
        state.sort = 'bus_asc';
        state.page = 1;
        el('busSearch').value = '';
        el('busStatus').value = '';
        el('busType').value = '';
        el('busRoute').value = '';
        el('busDriver').value = '';
        el('busCreatedFrom').value = '';
        el('busCreatedTo').value = '';
        el('busSort').value = 'bus_asc';
        closeDetail(true);
        load();
    }

    /* ---------------------------- add / edit wizard ---------------------------- */

    var STEPS = [
        {key: 'basic', title: 'Basic Information'},
        {key: 'vehicle', title: 'Vehicle Information'},
        {key: 'operational', title: 'Operational Information'},
        {key: 'compliance', title: 'Registration & Compliance'},
        {key: 'review', title: 'Review & Confirm'}
    ];

    var MODES = {
        add: {label: 'Add', fields: 'busAddFields', steps: 'busAddSteps', review: 'busAddReview', back: 'busAddBack', next: 'busAddNext', save: 'busAddSave', modal: 'busAddModal', prefix: 'data-add-field'},
        edit: {label: 'Edit', fields: 'busEditFields', steps: 'busEditSteps', review: 'busEditReview', back: 'busEditBack', next: 'busEditNext', save: 'busEditSave', modal: 'busEditModal', prefix: 'data-edit-field'},
        restore: {label: 'Restore', fields: 'busRestoreFields', steps: 'busRestoreSteps', review: 'busRestoreReview', back: 'busRestoreBack', next: 'busRestoreNext', save: 'busRestoreSave', modal: 'busRestoreModal', prefix: 'data-restore-field'}
    };

    function modeUi(mode) {
        return MODES[mode] || MODES.edit;
    }

    function f(step, name, label, type, required, extra) {
        var field = {step: step, name: name, label: label, type: type || 'text', required: !!required};
        if (extra) Object.keys(extra).forEach(function (key) { field[key] = extra[key]; });
        return field;
    }

    /* Required to save: plate number, bus type, seating capacity, standing
       capacity. Everything else is optional, pre-filled when editing, and
       preserved when the request does not supply it. The restore form omits
       the lifecycle and operational status fields, which stay under the
       restore lifecycle and never change through this form. */
    function wizardFields(mode) {
        var lists = state.options.lists || {};
        var fields = [
            f('basic', 'plate_number', 'Plate Number', 'text', true, {placeholder: 'e.g. ABC 1234'}),
            f('basic', 'unit_number', 'Bus / Unit Number', 'text', false, {placeholder: 'e.g. 1201'}),
            f('basic', 'bus_type', 'Bus Type', 'select-options', true, {optionsKey: 'types'}),
            f('basic', 'manufacturer', 'Manufacturer', 'datalist', false, {optionsKey: 'manufacturers'}),
            f('basic', 'model', 'Model', 'datalist', false, {optionsKey: 'models'}),
            f('basic', 'year_model', 'Year Model', 'datalist', false, {optionsKey: 'years'}),
            f('basic', 'seats', 'Passenger Capacity (seats)', 'number', true),
            f('basic', 'standing_capacity', 'Standing Capacity', 'number', true),

            f('vehicle', 'engine_number', 'Engine Number', 'text'),
            f('vehicle', 'chassis_number', 'Chassis Number', 'text'),
            f('vehicle', 'fuel_type', 'Fuel Type', 'select', false, {options: lists.fuel_types || []}),
            f('vehicle', 'transmission_type', 'Transmission Type', 'select', false, {options: lists.transmissions || []}),
            f('vehicle', 'air_conditioning', 'Air Conditioning', 'select', false, {options: lists.air_conditioning || []}),
            f('vehicle', 'vehicle_ownership', 'Vehicle Ownership', 'select', false, {options: lists.ownership || []}),

            f('operational', 'driver', 'Assigned Driver', 'select-options', false, {optionsKey: 'drivers'}),
            f('operational', 'assigned_inspector_id', 'Assigned Inspector', 'select-options', false, {optionsKey: 'inspectors'}),
            f('operational', 'schedule', 'Assigned Schedule', 'text', false, {placeholder: 'e.g. 06:00 AM - 06:00 PM'}),
            f('operational', 'operational_status', 'Operational Status', 'select', false, {options: lists.operational_statuses || []}),
            f('operational', 'from_terminal_id', 'From Terminal', 'select-options', false, {optionsKey: 'terminals'}),
            f('operational', 'to_terminal_id', 'To Terminal', 'select-options', false, {optionsKey: 'terminals'}),
            f('operational', 'status', 'Account Status', 'select', false, {options: ['Active', 'Inactive', 'Pending QR'], default: 'Active'}),
            f('operational', 'accessible_features', 'Accessible Features', 'text', false, {placeholder: 'e.g. Priority Seats'}),

            f('compliance', 'registration_month', 'Registration Month', 'select', false, {options: MONTH_NAMES}),
            f('compliance', 'registration_expiry', 'Vehicle Registration Expiration', 'date'),
            f('compliance', 'insurance_expiry', 'Insurance Expiration', 'date'),
            f('compliance', 'inspection_date', 'Inspection Date', 'date'),
            f('compliance', 'next_inspection_date', 'Next Inspection Date', 'date')
        ];

        if (mode === 'restore') {
            fields = fields.filter(function (field) {
                return field.name !== 'status' && field.name !== 'operational_status';
            });
        }

        return fields;
    }

    function wizardOptions(field) {
        var key = field.optionsKey || '';
        if (field.options) {
            return optionList(field.options);
        }
        if (key === 'routes' || key === 'terminals' || key === 'drivers' || key === 'inspectors'
            || key === 'types' || key === 'manufacturers' || key === 'models' || key === 'years') {
            return state.options[key] || [];
        }
        return optionList([]);
    }

    /* ---------------------- per-type fleet capacity standard ---------------------- */

    function capacityRuleFor(type) {
        var capacities = (state.options.lists && state.options.lists.bus_type_capacities) || {};
        var needle = String(type || '').trim().toLowerCase();
        if (needle === '') return null;
        var found = null;
        Object.keys(capacities).forEach(function (label) {
            if (String(label).trim().toLowerCase() === needle) found = capacities[label];
        });
        return found;
    }

    function capacityHint(type) {
        var rule = capacityRuleFor(type);
        if (!rule) return '';
        var min = parseInt(rule.seats_min, 10);
        var max = parseInt(rule.seats_max, 10);
        var seats = min === max ? String(min) : min + '–' + max;
        var hint = type + ' standard: ' + seats + ' seats';
        var total = parseInt(rule.total_max, 10);
        if (total > 0) hint += ', up to ' + total + ' including standing';
        return hint + '.';
    }

    function applyTypeCapacity(mode) {
        var host = el(mode === 'add' ? 'busAddFields' : 'busEditFields');
        if (!host) return;
        var prefix = mode === 'add' ? 'data-add-field' : 'data-edit-field';
        var typeNode = host.querySelector('[' + prefix + '="bus_type"]');
        if (!typeNode) return;

        var rule = capacityRuleFor(typeNode.value);
        var hint = host.querySelector('[data-capacity-hint="' + mode + '"]');
        if (hint) hint.textContent = capacityHint(typeNode.value);
        if (!rule) return;

        var seatsNode = host.querySelector('[' + prefix + '="seats"]');
        if (seatsNode) {
            seatsNode.value = rule.seats_default !== undefined ? String(rule.seats_default) : '';
            if (seatsNode.classList && seatsNode.classList.remove) seatsNode.classList.remove('is-invalid');
        }
        var standingNode = host.querySelector('[' + prefix + '="standing_capacity"]');
        if (standingNode) standingNode.value = rule.standing_default !== undefined ? String(rule.standing_default) : '';
    }

    function renderWizardSteps(containerId, mode) {
        var host = el(containerId);
        if (!host) return;
        var active = state.wizard[mode] || 0;
        host.innerHTML = STEPS.map(function (step, index) {
            var marker = index < active
                ? '<span class="rmg-step-index"><i class="bi bi-check2"></i></span>'
                : '<span class="rmg-step-index">' + (index + 1) + '</span>';
            var chip = '<button type="button" class="rmg-step-chip' + (index === active ? ' is-active' : '') + (index < active ? ' is-done' : '') + '" data-bus-step="' + index + '" data-bus-mode="' + mode + '">' +
                marker + '<span class="rmg-step-label">' + esc(step.title) + '</span></button>';
            if (index < STEPS.length - 1) chip += '<span class="rmg-step-sep" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>';
            return chip;
        }).join('');
    }

    function renderWizardField(field, mode, values) {
        var prefix = modeUi(mode).prefix;
        var name = field.name || '';
        var value = values && values[name] !== undefined && values[name] !== null ? String(values[name]) : '';
        var required = field.required ? ' data-required="1"' : '';
        var inputId = 'bus' + modeUi(mode).label + 'F' + name;

        var html = '<div class="rmg-form-field' + (field.full ? ' full' : '') + '" data-field-wrapper="' + esc(name) + '">';
        html += '<label>' + esc(field.label) + (field.required ? ' *' : '') + '</label>';

        if (field.type === 'select') {
            html += '<select ' + prefix + '="' + esc(name) + '" id="' + esc(inputId) + '"' + required + '>';
            var options = wizardOptions(field);
            if (!field.required && (!options.length || String(options[0].id) !== '')) {
                options = [{id: '', label: '—'}].concat(options);
            }
            var hasCurrent = options.some(function (option) { return String(option.id) === String(value); });
            if (value !== '' && !hasCurrent) {
                options.push({id: value, label: value + ' (current)'});
            }
            var selected = value !== '' ? value : (field.default !== undefined ? field.default : '');
            options.forEach(function (option) {
                html += '<option value="' + esc(option.id) + '"' + (String(selected) === String(option.id) ? ' selected' : '') + '>' + esc(option.label) + '</option>';
            });
            html += '</select>';
        } else if (field.type === 'select-options') {
            var list = wizardOptions(field);
            html += '<select ' + prefix + '="' + esc(name) + '" id="' + esc(inputId) + '"' + required + '>';
            html += '<option value="">' + (list.length ? 'Select...' : 'No options available') + '</option>';
            list.forEach(function (option) {
                html += '<option value="' + esc(option.id) + '"' + (value !== '' && String(value) === String(option.id) ? ' selected' : '') + '>' + esc(option.label) + '</option>';
            });
            html += '</select>';
        } else if (field.type === 'datalist') {
            var dataList = wizardOptions(field);
            var listId = inputId + 'List';
            html += '<input type="text" list="' + esc(listId) + '" ' + prefix + '="' + esc(name) + '" id="' + esc(inputId) + '" value="' + esc(value) + '"' + required +
                (field.placeholder ? ' placeholder="' + esc(field.placeholder) + '"' : '') + '>';
            html += '<datalist id="' + esc(listId) + '">';
            dataList.forEach(function (option) {
                html += '<option value="' + esc(option.label || option.id) + '"></option>';
            });
            html += '</datalist>';
        } else if (field.type === 'textarea') {
            html += '<textarea rows="2" ' + prefix + '="' + esc(name) + '" id="' + esc(inputId) + '"' + required +
                (field.placeholder ? ' placeholder="' + esc(field.placeholder) + '"' : '') + '>' + esc(value) + '</textarea>';
        } else {
            html += '<input type="' + esc(field.type) + '" ' + prefix + '="' + esc(name) + '" id="' + esc(inputId) + '" value="' + esc(value) + '"' + required +
                (field.placeholder ? ' placeholder="' + esc(field.placeholder) + '"' : '') + '>';
        }

        if (field.name === 'seats') {
            var typeValue = values && values['bus_type'] !== undefined && values['bus_type'] !== null ? String(values['bus_type']) : '';
            html += '<div class="rmg-field-hint" data-capacity-hint="' + esc(mode) + '">' + esc(capacityHint(typeValue)) + '</div>';
        }

        html += '</div>';
        return html;
    }

    function renderWizardReview(mode) {
        var prefix = modeUi(mode).prefix;
        var host = el(modeUi(mode).fields);
        var review = [];
        wizardFields(mode).forEach(function (field) {
            if (!field.name || field.type === 'textarea') return;
            var node = host ? host.querySelector('[' + prefix + '="' + field.name + '"]') : null;
            if (!node) return;
            var value = node.value;
            if (field.type === 'select-options' || field.type === 'select') {
                var option = node.options[node.selectedIndex];
                value = option ? option.textContent : '';
            }
            if (String(value || '').trim() === '') return;
            review.push([field.label, value]);
        });

        var source = mode === 'restore' ? state.restoreValues : (mode === 'add' ? state.addBusId : state.editValues);
        var preview = mode === 'add'
            ? (state.addBusId || el('busAddUserId').value)
            : (source && source.bus_id ? source.bus_id : '');
        review.unshift(['Bus ID', preview + (mode === 'add' ? ' (preview)' : '')]);

        return '<div class="rmg-review-title"><i class="bi bi-clipboard-check"></i> Review before saving</div>' +
            '<div class="rmg-review-grid">' + review.map(function (row) {
                return '<div class="rmg-review-item"><span class="k">' + esc(row[0]) + '</span><span class="v">' + esc(row[1]) + '</span></div>';
            }).join('') + '</div>' +
            '<div class="rmg-field-hint">Verify these details. The security authorization code confirms the change.</div>';
    }

    function renderWizard(containerId, mode, values) {
        var host = el(containerId);
        if (!host) return;
        var fields = wizardFields(mode);
        var html = '';
        STEPS.forEach(function (step, stepIndex) {
            if (step.key === 'review') return;
            var stepFields = fields.filter(function (field) { return field.step === step.key; });
            if (!stepFields.length) return;
            html += '<section class="rmg-step" data-step="' + esc(step.key) + '" data-step-index="' + stepIndex + '">' +
                '<div class="rmg-step-head"><span class="rmg-step-badge">' + (stepIndex + 1) + '</span>' +
                '<span class="rmg-step-title">' + esc(step.title) + '</span></div>' +
                '<div class="rmg-form-grid">' +
                stepFields.map(function (field) { return renderWizardField(field, mode, values); }).join('') +
                '</div></section>';
        });
        host.innerHTML = html;

        /* Choosing HINO/Higer applies the fleet capacity standard immediately. */
        var typeSelect = host.querySelector('[' + modeUi(mode).prefix + '="bus_type"]');
        if (typeSelect && typeSelect.addEventListener) {
            typeSelect.addEventListener('change', function () { applyTypeCapacity(mode); });
        }

        var reviewHost = el(modeUi(mode).review);
        if (reviewHost) {
            reviewHost.hidden = false;
            reviewHost.innerHTML = renderWizardReview(mode);
        }
    }

    function goToStep(mode, index) {
        index = Math.max(0, Math.min(STEPS.length - 1, index));
        if (index > (state.wizard[mode] || 0) && !validateStep(mode)) {
            busNotify('error', 'Check this step', 'Please complete the highlighted fields before continuing.');
            return;
        }
        state.wizard[mode] = index;
        renderWizardSteps(modeUi(mode).steps, mode);

        var reviewHost = el(modeUi(mode).review);
        if (index === STEPS.length - 1 && reviewHost) {
            reviewHost.innerHTML = renderWizardReview(mode);
        }

        var host = el(modeUi(mode).fields);
        var card = index === STEPS.length - 1 ? reviewHost : (host ? host.querySelector('[data-step="' + STEPS[index].key + '"]') : null);
        if (card && card.scrollIntoView) card.scrollIntoView({behavior: 'smooth', block: 'start'});
        updateStepNav(mode);
    }

    function updateStepNav(mode) {
        var index = state.wizard[mode] || 0;
        var back = el(modeUi(mode).back);
        var next = el(modeUi(mode).next);
        if (back) back.disabled = index <= 0;
        if (next) {
            next.disabled = index >= STEPS.length - 1;
            next.innerHTML = index >= STEPS.length - 2 ? 'Review <i class="bi bi-arrow-right"></i>' : 'Next <i class="bi bi-arrow-right"></i>';
        }
    }

    function validateStep(mode) {
        var host = el(modeUi(mode).fields);
        var prefix = modeUi(mode).prefix;
        var ok = true;
        var fields = wizardFields(mode);
        fields.forEach(function (field) {
            if (!field.required || !field.name) return;
            var node = host.querySelector('[' + prefix + '="' + field.name + '"]');
            if (!node) return;
            if (String(node.value).trim() === '') {
                node.classList.add('is-invalid');
                ok = false;
            } else {
                node.classList.remove('is-invalid');
            }
        });
        return ok;
    }

    function refreshBusId() {
        var input = el('busAddUserId');
        if (!input) return;
        var requestId = ++state.busIdRequest;
        input.value = 'Generating...';
        var stateEl = el('busAddUserIdState');
        if (stateEl) stateEl.className = 'rmg-id-state is-loading';
        fetch('/admin/api/Bus/NextBusId.php', {credentials: 'same-origin', cache: 'no-store'})
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (requestId !== state.busIdRequest) return;
                if (!res || res.success === false) throw new Error('preview');
                state.addBusId = String(res.bus_id || res.user_id || '');
                input.value = state.addBusId || 'Assigned when saved';
                if (stateEl) stateEl.className = 'rmg-id-state is-ready';
            })
            .catch(function () {
                if (requestId !== state.busIdRequest) return;
                state.addBusId = '';
                input.value = 'Assigned when saved';
                if (stateEl) stateEl.className = 'rmg-id-state is-error';
            });
    }

    function openAddForm() {
        state.wizard.add = 0;
        state.addValues = null;
        state.addBusId = '';
        state.formDirty.add = false;
        state.formForceClose.add = false;
        el('busAddError').hidden = true;
        el('busAddDuplicates').hidden = true;
        el('busAddSave').disabled = true;
        renderWizard('busAddFields', 'add', null);
        renderWizardSteps('busAddSteps', 'add');
        updateStepNav('add');
        refreshBusId();
        bootstrap.Modal.getOrCreateInstance(el('busAddModal')).show();
        ensureBusOptions(function () {
            renderWizard('busAddFields', 'add', readValues('add'));
            renderWizardSteps('busAddSteps', 'add');
            el('busAddSave').disabled = false;
        });
    }

    function readValues(mode) {
        var host = el(modeUi(mode).fields);
        if (!host || !host.querySelectorAll) return null;
        var prefix = modeUi(mode).prefix;
        var values = {};
        var found = false;
        Array.prototype.forEach.call(host.querySelectorAll('[' + prefix + ']'), function (input) {
            found = true;
            values[input.getAttribute(prefix)] = input.value;
        });
        return found ? values : null;
    }

    function detailValues(item) {
        return {
            bus_id: item.bus_id,
            bus_ref: String(item.ref),
            plate_number: item.plate || '',
            unit_number: item.unit_number || '',
            bus_type: item.bus_type || '',
            manufacturer: item.manufacturer || '',
            model: item.model || '',
            year_model: item.year_model || '',
            seats: item.seats !== null && item.seats !== undefined ? String(item.seats) : '',
            standing_capacity: item.standing_capacity !== null && item.standing_capacity !== undefined ? String(item.standing_capacity) : '',
            engine_number: item.engine_number || '',
            chassis_number: item.chassis_number || '',
            fuel_type: item.fuel_type || '',
            transmission_type: item.transmission_type || '',
            air_conditioning: item.air_conditioning || '',
            vehicle_ownership: item.vehicle_ownership || '',
            driver: item.driver_ref && item.driver_ref !== 'Unassigned' ? item.driver_ref : '',
            assigned_inspector_id: item.assigned_inspector_id ? String(item.assigned_inspector_id) : '',
            schedule: item.schedule || '',
            operational_status: item.operational_status || '',
            from_terminal_id: item.from_terminal_id ? String(item.from_terminal_id) : '',
            to_terminal_id: item.to_terminal_id ? String(item.to_terminal_id) : '',
            status: item.raw_status || 'Active',
            accessible_features: item.accessible_features || '',
            registration_month: item.registration_month || '',
            registration_expiry: item.registration_expiry || '',
            insurance_expiry: item.insurance_expiry || '',
            inspection_date: item.inspection_date || '',
            next_inspection_date: item.next_inspection_date || ''
        };
    }

    function openEdit(item) {
        state.wizard.edit = 0;
        state.formDirty.edit = false;
        state.formForceClose.edit = false;
        el('busEditError').hidden = true;
        el('busEditSave').disabled = true;
        el('busEditTitle').textContent = 'Edit ' + item.bus_id;
        el('busEditSub').textContent = (item.plate || 'No plate') + (item.unit_number ? ' · Unit ' + item.unit_number : '');
        el('busEditAvatar').textContent = initials(item.bus_id);
        el('busEditAvatar').className = 'rmg-form-avatar ' + statusClass(item.classification);

        var values = detailValues(item);
        state.editValues = values;

        renderWizard('busEditFields', 'edit', values);
        renderWizardSteps('busEditSteps', 'edit');
        updateStepNav('edit');
        var host = el('busEditFields');
        if (host && host.insertAdjacentHTML) {
            host.insertAdjacentHTML('afterbegin',
                '<input type="hidden" data-edit-hidden="bus_ref" value="' + esc(item.ref) + '">' +
                '<div class="rmg-form-field full rmg-readonly-id"><label>Bus ID (read-only)</label>' +
                '<input type="text" value="' + esc(item.bus_id) + '" readonly aria-readonly="true" tabindex="-1"></div>');
        }
        el('busEditSave').disabled = false;
        bootstrap.Modal.getOrCreateInstance(el('busEditModal')).show();
    }

    function openRestoreForm(item) {
        if (!item) {
            busNotify('error', 'Bus unavailable', 'Reopen the Archived Buses list and choose the bus again.');
            return;
        }
        state.wizard.restore = 0;
        state.formDirty.restore = false;
        state.formForceClose.restore = false;
        el('busRestoreError').hidden = true;
        el('busRestoreSave').disabled = true;
        el('busRestoreTitle').textContent = 'Restore ' + item.bus_id;
        el('busRestoreSub').textContent = (item.plate || 'No plate') + (item.unit_number ? ' · Unit ' + item.unit_number : '') + ' · archived';
        el('busRestoreAvatar').textContent = initials(item.bus_id);
        el('busRestoreAvatar').className = 'rmg-form-avatar ' + statusClass('archived');

        var values = detailValues(item);
        state.restoreValues = values;

        renderWizard('busRestoreFields', 'restore', values);
        renderWizardSteps('busRestoreSteps', 'restore');
        updateStepNav('restore');
        var host = el('busRestoreFields');
        if (host && host.insertAdjacentHTML) {
            host.insertAdjacentHTML('afterbegin',
                '<input type="hidden" data-restore-hidden="bus_ref" value="' + esc(item.ref) + '">' +
                '<div class="rmg-form-field full rmg-readonly-id"><label>Bus ID (read-only)</label>' +
                '<input type="text" value="' + esc(item.bus_id) + '" readonly aria-readonly="true" tabindex="-1"></div>' +
                '<div class="rmg-form-field full rmg-readonly-id"><label>QR credential (read-only)</label>' +
                '<input type="text" value="' + esc(item.qr_status || 'Stored with the bus') + '" readonly aria-readonly="true" tabindex="-1"></div>');
        }
        el('busRestoreSave').disabled = false;
        bootstrap.Modal.getOrCreateInstance(el('busRestoreModal')).show();
    }

    function saveRestore() {
        if (state.submittingRestore) return;
        var values = state.restoreValues || {};
        var ref = values.bus_ref ? String(values.bus_ref) : '';
        if (ref === '') {
            busNotify('error', 'Restore unavailable', 'Reopen the Archived Buses list and choose the bus again.');
            return;
        }
        if (!validateStep('restore')) {
            busNotify('error', 'Check the form', 'Please complete the highlighted fields.');
            return;
        }
        var entries = collectEntries('busRestoreFields', 'data-restore-field');

        state.submittingRestore = true;
        el('busRestoreSave').disabled = true;
        el('busRestoreError').hidden = true;

        promptReason('Restore bus: ' + (values.bus_id || ''), 'The bus returns with the details you save here. Its original ID, QR code, and records are kept.', 'Restore').then(function (reason) {
            if (!reason) {
                state.submittingRestore = false;
                el('busRestoreSave').disabled = false;
                return null;
            }
            var secured = entries.concat([['reason', reason]]);
            return beginSecurityVerify('restore', securityTargetWithRef(ref), secured, 'Restore ' + (values.bus_id || '')).then(function (token) {
                if (!token) {
                    state.submittingRestore = false;
                    el('busRestoreSave').disabled = false;
                    return null;
                }
                var body = {};
                secured.forEach(function (pair) { body[pair[0]] = pair[1]; });
                body.security_token = token;
                return fetch('/admin/api/restore_bus.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(body),
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (!res || res.success === false) throw new Error((res && res.message) || 'Could not restore the bus.');
                    state.formForceClose.restore = true;
                    bootstrap.Modal.getOrCreateInstance(el('busRestoreModal')).hide();
                    busNotify('success', 'Bus restored', res.message || 'The bus details were saved and the bus was restored.');
                    openArchivedModal();
                    load();
                });
            });
        }).catch(function (err) {
            el('busRestoreError').textContent = err.message || 'Could not restore the bus.';
            el('busRestoreError').hidden = false;
        }).finally(function () {
            state.submittingRestore = false;
            el('busRestoreSave').disabled = false;
        });
    }

    function collectEntries(hostId, prefix) {
        var host = el(hostId);
        var entries = [];
        Array.prototype.forEach.call(host.querySelectorAll('[' + prefix + ']'), function (input) {
            if (input.type === 'file') return;
            entries.push([input.getAttribute(prefix), input.value]);
        });
        ['data-edit-hidden', 'data-restore-hidden'].forEach(function (hiddenAttr) {
            Array.prototype.forEach.call(host.querySelectorAll('[' + hiddenAttr + ']'), function (input) {
                entries.push([input.getAttribute(hiddenAttr), input.value]);
            });
        });
        return entries;
    }

    function postSecureForm(url, formData) {
        return fetch(url, {method: 'POST', body: formData, credentials: 'same-origin'})
            .then(function (r) { return r.text().then(function (text) { return {status: r.status, text: text}; }); })
            .then(function (out) {
                var json = null;
                try { json = JSON.parse(out.text); } catch (e) { json = null; }
                if (!json) {
                    throw new Error(out.status >= 400 ? 'The request failed (HTTP ' + out.status + ').' : 'The server returned an unexpected response.');
                }
                return json;
            });
    }

    function checkPlateDuplicate(mode) {
        var host = el(mode === 'add' ? 'busAddFields' : 'busEditFields');
        var prefix = mode === 'add' ? 'data-add-field' : 'data-edit-field';
        var plateInput = host.querySelector('[' + prefix + '="plate_number"]');
        var warningHost = mode === 'add' ? el('busAddDuplicates') : null;
        if (!plateInput || String(plateInput.value).trim() === '') {
            if (warningHost) warningHost.hidden = true;
            return Promise.resolve(false);
        }
        var ref = '';
        if (mode === 'edit' && state.editValues && state.editValues.bus_ref) {
            ref = state.editValues.bus_ref;
        }
        var payload = {plate_number: plateInput.value};
        if (ref) payload.exclude_ref = ref;
        return fetch('/admin/api/Bus/Duplicates.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (res) {
            var duplicates = (res && Array.isArray(res.duplicates)) ? res.duplicates : [];
            if (mode === 'add' && warningHost) {
                if (!duplicates.length) {
                    warningHost.hidden = true;
                    warningHost.innerHTML = '';
                } else {
                    warningHost.hidden = false;
                    warningHost.innerHTML = '<div class="rmg-dup-title"><i class="bi bi-exclamation-triangle"></i> Possible duplicate plate</div>' +
                        '<ul>' + duplicates.map(function (match) {
                            return '<li><strong>' + esc(match.bus_id || ('#' + match.ref)) + '</strong> — ' + esc(match.plate) + ' (' + esc(match.status || 'unknown status') + ')</li>';
                        }).join('') + '</ul>' +
                        '<div class="rmg-field-hint">Review before registering. A plate can only belong to one bus.</div>';
                }
            }
            return duplicates.length > 0;
        }).catch(function () {
            return false;
        });
    }

    function saveAdd() {
        if (state.submittingAdd) return;
        var host = el('busAddFields');
        if (!validateStep('add')) {
            busNotify('error', 'Check the form', 'Please complete the highlighted fields.');
            return;
        }
        var entries = collectEntries('busAddFields', 'data-add-field');
        var formData = new FormData();
        entries.forEach(function (pair) { formData.append(pair[0], pair[1]); });

        state.submittingAdd = true;
        el('busAddSave').disabled = true;
        el('busAddError').hidden = true;

        checkPlateDuplicate('add').then(function (isDuplicate) {
            if (isDuplicate) {
                throw new Error('This plate number already belongs to another bus.');
            }
            return beginSecurityVerify('add', 'bus', entries, 'Register bus (preview ' + (state.addBusId || 'auto') + ')');
        }).then(function (token) {
            if (!token) {
                state.submittingAdd = false;
                el('busAddSave').disabled = false;
                return null;
            }
            formData.append('security_token', token);
            return postSecureForm('/admin/api/add_bus.php', formData).then(function (res) {
                if (res.success === false) throw new Error(res.message || 'Could not register the bus.');
                state.formForceClose.add = true;
                bootstrap.Modal.getOrCreateInstance(el('busAddModal')).hide();
                state.openBus = String(res.bus_id || '');
                state.page = 1;
                closeDetail(true);
                load();
                busNotify('success', 'Bus registered', 'Bus ' + (res.bus_id || '') + ' was registered with its QR code.');
            });
        }).catch(function (err) {
            el('busAddError').textContent = err.message || 'Could not register the bus.';
            el('busAddError').hidden = false;
        }).finally(function () {
            state.submittingAdd = false;
            el('busAddSave').disabled = false;
        });
    }

    function saveEdit() {
        if (state.submittingEdit) return;
        var item = state.items[state.detailIndex];
        if (!validateStep('edit')) {
            busNotify('error', 'Check the form', 'Please complete the highlighted fields.');
            return;
        }

        var entries = collectEntries('busEditFields', 'data-edit-field');
        var formData = new FormData();
        entries.forEach(function (pair) { formData.append(pair[0], pair[1]); });

        var busRef = '';
        entries.forEach(function (pair) { if (pair[0] === 'bus_ref') busRef = pair[1]; });

        state.submittingEdit = true;
        el('busEditSave').disabled = true;
        el('busEditError').hidden = true;

        checkPlateDuplicate('edit').then(function (isDuplicate) {
            if (isDuplicate) {
                throw new Error('This plate number already belongs to another bus.');
            }
            return beginSecurityVerify('edit', securityTargetWithRef(busRef), entries, 'Edit bus');
        }).then(function (token) {
            if (!token) {
                state.submittingEdit = false;
                el('busEditSave').disabled = false;
                return null;
            }
            formData.append('security_token', token);
            return postSecureForm('/admin/api/edit_bus.php', formData).then(function (res) {
                if (res.success === false) {
                    if (res.code === 'driver_conflict') {
                        return new Promise(function (resolve) {
                            window.showConfirm(
                                'Move driver assignment?',
                                res.message || 'This driver is assigned elsewhere.',
                                function () {
                                    formData.set('allow_move', '1');
                                    resolve(beginSecurityVerify('edit', securityTargetWithRef(busRef), collectEntries('busEditFields', 'data-edit-field').concat([['allow_move', '1']]), 'Move driver assignment'));
                                },
                                function () { resolve(null); }
                            );
                        }).then(function (retryToken) {
                            if (!retryToken) return null;
                            formData.set('security_token', retryToken);
                            return postSecureForm('/admin/api/edit_bus.php', formData).then(function (retryRes) {
                                if (retryRes.success === false) throw new Error(retryRes.message || 'Could not update the bus.');
                                return retryRes;
                            });
                        });
                    }
                    throw new Error(res.message || 'Could not update the bus.');
                }
                return res;
            }).then(function (finalRes) {
                if (finalRes === null) return;
                state.formForceClose.edit = true;
                bootstrap.Modal.getOrCreateInstance(el('busEditModal')).hide();
                state.openBus = String(item ? item.bus_id : '');
                state.historyCache = {};
                load();
                busNotify('success', 'Bus updated', 'Changes were saved and recorded in the audit trail.');
            });
        }).catch(function (err) {
            el('busEditError').textContent = err.message || 'Could not update the bus.';
            el('busEditError').hidden = false;
        }).finally(function () {
            state.submittingEdit = false;
            el('busEditSave').disabled = false;
        });
    }

    /* ----------------------------- detail + tabs ----------------------------- */

    function findItem(index) {
        return state.items[Number(index)] || null;
    }

    function fieldRow(label, value) {
        return '<div class="rmg-field"><div class="k">' + esc(label) + '</div><div class="v">' + displayValue(value) + '</div></div>';
    }

    function sectionHtml(title, icon, tone, rows, extraHtml) {
        return '<section class="rmg-detail-section">' +
            '<div class="rmg-section-head"><span class="rmg-section-icon' + (tone ? ' tone-' + tone : '') + '"><i class="bi ' + icon + '"></i></span>' +
            '<div class="rmg-section-title">' + esc(title) + '</div></div>' +
            (extraHtml || '') +
            '<div class="rmg-detail-grid">' + rows.map(function (row) { return fieldRow(row[0], row[1]); }).join('') + '</div></section>';
    }

    function statsHtml(item) {
        var tiles = [
            ['bi-hash', 'Bus ID', item.bus_id, ''],
            ['bi-card-text', 'Plate', item.plate, ''],
            ['bi-bus-front', 'Bus Type', item.bus_type, ''],
            ['bi-people', 'Capacity', (item.seats || 0) + ' seats' + (item.standing_capacity ? ' + ' + item.standing_capacity + ' standing' : ''), 'account']
        ];
        return '<div class="rmg-detail-stats">' + tiles.map(function (tile) {
            return '<div class="rmg-stat"><span class="rmg-stat-icon ' + (tile[3] ? 'tone-' + tile[3] : '') + '"><i class="bi ' + tile[0] + '"></i></span>' +
                '<div class="rmg-stat-body"><div class="rmg-stat-label">' + esc(tile[1]) + '</div><div class="rmg-stat-value">' + displayValue(tile[2]) + '</div></div></div>';
        }).join('') + '</div>';
    }

    function detailTabs(item) {
        var qr = state.qrCache[item.ref] || null;
        var history = state.historyCache[item.ref] || null;

        var overviewRows = [
            ['Bus ID', item.bus_id],
            ['Plate Number', item.plate],
            ['Unit Number', item.unit_number],
            ['Bus Type', item.bus_type],
            ['Manufacturer', item.manufacturer],
            ['Model', item.model],
            ['Year Model', item.year_model],
            ['Capacity', (item.seats || 0) + ' seats' + (item.standing_capacity ? ' + ' + item.standing_capacity + ' standing' : '')],
            ['Operational Status', item.operational_status],
            ['Registration Date', item.created_at ? fmtDate(item.created_at, true) : ''],
            ['Created By', item.created_by],
            ['Last Updated', item.updated_at ? fmtDate(item.updated_at, true) : '']
        ];
        var statusBanner = '<div class="rmg-status-banner ' + statusClass(item.classification) + '">' +
            '<span class="rmg-status-dot" aria-hidden="true"></span>' +
            '<span class="rmg-status-text">' + displayValue(item.classification_label) + '</span></div>';

        var warnings = [];
        [['registration_state', 'Registration'], ['insurance_state', 'Insurance'], ['inspection_state', 'Inspection']].forEach(function (pair) {
            if (item[pair[0]] === 'expired') warnings.push(pair[1] + ' expired');
            else if (item[pair[0]] === 'expiring') warnings.push(pair[1] + ' expiring soon');
        });
        var warningHtml = warnings.length
            ? '<div class="rmg-note" style="margin-bottom:12px;"><i class="bi bi-exclamation-triangle"></i> ' + esc(warnings.join(' · ')) + '</div>'
            : '';

        var assignmentsRows = [
            ['Assigned Driver', item.driver_name || item.driver_ref],
            ['Assigned Inspector', item.inspector_name || item.assigned_inspector_id],
            ['Assigned Route', item.route_name],
            ['From Terminal', item.from_terminal_id],
            ['To Terminal', item.to_terminal_id],
            ['Assigned Schedule', item.schedule]
        ];

        var maintenanceRows = [
            ['Registration Month', item.registration_month],
            ['Registration Expiration', item.registration_expiry],
            ['Insurance Expiration', item.insurance_expiry],
            ['Last Inspection', item.inspection_date],
            ['Next Inspection', item.next_inspection_date],
            ['Operational Status', item.operational_status]
        ];
        var maintenanceHtml = sectionHtml('Compliance & Maintenance', 'bi-wrench-adjustable', 'account', maintenanceRows, warningHtml) +
            '<div class="rmg-field-hint" style="margin:-6px 0 14px;">Document dates are reminders only and never declare a bus roadworthy on their own.</div>';

        var documentsHtml = sectionHtml('Documents', 'bi-paperclip', 'docs', [
            ['Registration', item.registration_expiry ? 'On file (expires ' + item.registration_expiry + ')' : ''],
            ['Insurance', item.insurance_expiry ? 'On file (expires ' + item.insurance_expiry + ')' : ''],
            ['Inspection Records', item.inspection_date ? 'Last inspection ' + item.inspection_date : '']
        ]);

        var qrHtml = renderQrPanel(item, qr);
        var activityHtml = renderHistoryBlock(item, history);

        return detailTabsHtml([
            {key: 'overview', title: 'Overview', html: statsHtml(item) + statusBanner + sectionHtml('Bus Information', 'bi-person-vcard', 'account', overviewRows)},
            {key: 'assignments', title: 'Assignments', html: sectionHtml('Assignments', 'bi-diagram-3', 'role', assignmentsRows)},
            {key: 'qr', title: 'QR Code', html: qrHtml},
            {key: 'maintenance', title: 'Maintenance', html: maintenanceHtml},
            {key: 'documents', title: 'Documents', html: documentsHtml},
            {key: 'activity', title: 'Activity', html: activityHtml}
        ]);
    }

    function detailTabsHtml(tabs) {
        var active = state.detailTab || 'overview';
        var known = false;
        tabs.forEach(function (tab) { if (tab.key === active) known = true; });
        if (!known) active = 'overview';
        var nav = '<ul class="nav nav-tabs rmg-detail-tabs" role="tablist">';
        var panes = '<div class="tab-content rmg-detail-tab-content">';
        tabs.forEach(function (tab) {
            var isActive = tab.key === active;
            nav += '<li class="nav-item" role="presentation">' +
                '<button class="nav-link' + (isActive ? ' active' : '') + '" data-bus-tab="' + esc(tab.key) + '" data-bs-toggle="tab" data-bs-target="#busPane-' + esc(tab.key) + '" type="button" role="tab" aria-selected="' + (isActive ? 'true' : 'false') + '">' + esc(tab.title) + '</button></li>';
            panes += '<div class="tab-pane fade' + (isActive ? ' show active' : '') + '" id="busPane-' + esc(tab.key) + '" role="tabpanel">' + tab.html + '</div>';
        });
        return nav + '</ul>' + panes + '</div>';
    }

    function renderQrPanel(item, qr) {
        if (!qr) {
            return '<section class="rmg-detail-section">' +
                '<div class="rmg-section-head"><span class="rmg-section-icon tone-role"><i class="bi bi-qr-code"></i></span><div class="rmg-section-title">QR Code</div></div>' +
                '<div class="rmg-state rmg-state-sm"><i class="bi bi-hourglass-split"></i><h6>Preparing QR information...</h6></div></section>';
        }
        if (qr.needs_preparation) {
            return '<section class="rmg-detail-section">' +
                '<div class="rmg-section-head"><span class="rmg-section-icon tone-role"><i class="bi bi-qr-code"></i></span><div class="rmg-section-title">QR Code</div></div>' +
                '<div class="rmg-state rmg-state-sm"><i class="bi bi-qr-code"></i><h6>No QR code prepared yet</h6>This bus predates automatic QR creation. Prepare its QR now — the Bus ID stays the same.</div>' +
                '<div class="bus-qr-actions"><button type="button" class="rmg-btn rmg-btn-primary rmg-btn-sm" data-action="qr-prepare"><i class="bi bi-magic"></i> Prepare QR code</button></div></section>';
        }

        var token = qr.qr ? qr.qr.token : '';
        var payload = qr.qr ? qr.qr.payload_url : '';
        var meta = '<div class="bus-qr-meta">' +
            fieldRow('QR Status', qr.qr.status === 'active' ? 'Active' : qr.qr.status) +
            fieldRow('Created', qr.qr.created_at ? fmtDate(qr.qr.created_at, true) : '') +
            fieldRow('Last Replaced', qr.qr.replaced_at ? fmtDate(qr.qr.replaced_at, true) : '') +
            '</div>';

        var actions = '<div class="bus-qr-actions">' +
            '<button type="button" class="rmg-btn rmg-btn-sm" data-action="qr-download-png"><i class="bi bi-download"></i> Download PNG</button>' +
            '<button type="button" class="rmg-btn rmg-btn-sm" data-action="qr-download-pdf"><i class="bi bi-file-earmark-pdf"></i> Download PDF</button>' +
            '<button type="button" class="rmg-btn rmg-btn-sm" data-action="qr-print"><i class="bi bi-printer"></i> Print</button>' +
            '<button type="button" class="rmg-btn rmg-btn-sm" data-action="qr-replace"><i class="bi bi-arrow-repeat"></i> Replace QR</button>' +
            '<button type="button" class="rmg-btn rmg-btn-sm rmg-btn-danger" data-action="qr-revoke"><i class="bi bi-x-octagon"></i> Revoke QR</button>' +
            '</div>';

        return '<section class="rmg-detail-section">' +
            '<div class="rmg-section-head"><span class="rmg-section-icon tone-role"><i class="bi bi-qr-code"></i></span><div class="rmg-section-title">QR Code</div></div>' +
            '<div class="bus-qr-panel">' +
            '<div class="bus-qr-canvas" id="busQrCanvas" data-payload="' + esc(payload) + '"></div>' +
            '<div>' + meta + actions + '</div>' +
            '</div></section>';
    }

    function renderHistoryBlock(item, history) {
        var html = '<section class="rmg-detail-section"><div class="rmg-section-head"><span class="rmg-section-icon tone-docs"><i class="bi bi-clock-history"></i></span><div class="rmg-section-title">Activity History</div></div>' +
            '<div class="rmg-history">';
        if (Array.isArray(history) && history.length) {
            html += '<ul>' + history.map(function (entry) {
                return '<li><span class="rmg-history-action">' + esc(entry.action || 'Change') + '</span> ' + esc(entry.description || '') +
                    ' <span class="rmg-history-meta">— ' + esc(entry.username || 'unknown') + ' · ' + esc(fmtDate(entry.created_at, true)) + '</span></li>';
            }).join('') + '</ul>';
        } else {
            html += '<div class="rmg-history-empty">No recorded changes yet.</div>';
        }
        return html + '</div></section>';
    }

    function renderDetailModal() {
        var item = findItem(state.detailIndex);
        if (!item || state.detailIndex === null) return;
        el('busDetailName').textContent = item.bus_id || 'Bus';
        el('busDetailKicker').textContent = (item.bus_type || 'Bus') + ' · ' + (item.plate || 'No plate');
        el('busDetailAvatarWrap').className = 'rmg-detail-avatar-wrap ' + statusClass(item.classification);
        el('busDetailChips').innerHTML = '<span class="rmg-type-chip"><i class="bi bi-bus-front"></i> ' + esc(item.bus_type || 'Bus') + '</span>' +
            '<span class="rmg-status ' + statusClass(item.classification) + '">' + esc(item.classification_label) + '</span>';
        var body = el('busDetailBody');
        body.innerHTML = detailTabs(item);
        el('busDetailActions').innerHTML =
            '<button type="button" class="rmg-btn rmg-btn-sm rmg-btn-primary" data-action="edit" data-index="' + state.detailIndex + '"><i class="bi bi-pencil"></i> Edit bus</button>' +
            '<button type="button" class="rmg-btn rmg-btn-sm" data-action="bulk-qr"><i class="bi bi-qr-code-scan"></i> Bulk QR</button>';
        renderDetailQr();
    }

    function renderDetailQr() {
        var host = el('busQrCanvas');
        if (!host) return;
        var payload = host.getAttribute('data-payload') || '';
        if (payload === '' || typeof window.QRCode === 'undefined') return;
        host.innerHTML = '';
        new window.QRCode(host, {text: payload, width: 190, height: 190, correctLevel: window.QRCode.CorrectLevel.H});
    }

    function loadQrInfo(item) {
        if (state.qrCache[item.ref]) return;
        fetch('/admin/api/Bus/QrInfo.php?bus_ref=' + encodeURIComponent(item.ref), {credentials: 'same-origin', cache: 'no-store'})
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || res.success === false) throw new Error('qr');
                state.qrCache[item.ref] = res;
                if (state.detailIndex !== null) renderDetailModal();
                renderDetailQr();
            })
            .catch(function () {
                state.qrCache[item.ref] = {needs_preparation: true, qr: null};
                if (state.detailIndex !== null) renderDetailModal();
            });
    }

    function loadHistory(item) {
        if (state.historyCache[item.ref]) return;
        fetch('/admin/api/Bus/History.php?bus_id=' + encodeURIComponent(item.bus_id), {credentials: 'same-origin', cache: 'no-store'})
            .then(function (r) { return r.json(); })
            .then(function (res) {
                state.historyCache[item.ref] = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                if (state.detailIndex !== null) renderDetailModal();
            })
            .catch(function () {
                state.historyCache[item.ref] = [];
            });
    }

    function openDetail(index, tab) {
        var item = findItem(index);
        if (!item) return;
        state.detailIndex = Number(index);
        state.detailTab = tab || 'overview';
        renderDetailModal();
        loadQrInfo(item);
        loadHistory(item);
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(el('busDetailModal')).show();
        }
    }

    function closeDetail(hide) {
        state.detailIndex = null;
        if (hide && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var modal = bootstrap.Modal.getInstance(el('busDetailModal'));
            if (modal) modal.hide();
        }
    }

    /* ------------------------------- QR actions ------------------------------- */

    function qrCanvas() {
        var host = el('busQrCanvas');
        if (!host) return null;
        return host.querySelector('canvas') || null;
    }

    function qrPayload() {
        var host = el('busQrCanvas');
        return host ? (host.getAttribute('data-payload') || '') : '';
    }

    function currentDetailItem() {
        return findItem(state.detailIndex);
    }

    function posterBus(item) {
        return {
            bus_id: item.bus_id,
            plate_number: item.plate || item.plate_number || '',
            seats: item.seats,
            standing_capacity: item.standing_capacity
        };
    }

    function posterFromQr() {
        var poster = window.RConnectQrPoster;
        if (!poster) {
            busNotify('error', 'QR poster unavailable', 'The QR poster module failed to load. Refresh the page and try again.');
            return null;
        }
        var payload = qrPayload();
        if (!payload) {
            busNotify('error', 'QR not ready', 'Open the QR tab after the code has rendered.');
            return null;
        }
        var dataUrl = poster.makeExportQrDataUrl(payload);
        if (!dataUrl) {
            poster.showExportError('The QR code could not be generated. Please try again.');
            return null;
        }
        return {poster: poster, dataUrl: dataUrl};
    }

    function downloadQrPng() {
        var item = currentDetailItem();
        var canvas = qrCanvas();
        if (!item || !canvas) {
            busNotify('error', 'QR not ready', 'Open the QR tab after the code has rendered.');
            return;
        }
        var ready = posterFromQr();
        if (!ready) return;
        ready.poster.exportPosterPNG(ready.dataUrl, posterBus(item));
    }

    function downloadQrPdf() {
        var item = currentDetailItem();
        var canvas = qrCanvas();
        if (!item || !canvas) {
            busNotify('error', 'QR not ready', 'Open the QR tab after the code has rendered.');
            return;
        }
        var ready = posterFromQr();
        if (!ready) return;
        ready.poster.exportPosterPDF(ready.dataUrl, posterBus(item));
    }

    function printQr() {
        var item = currentDetailItem();
        var canvas = qrCanvas();
        if (!item || !canvas) {
            busNotify('error', 'QR not ready', 'Open the QR tab after the code has rendered.');
            return;
        }
        var ready = posterFromQr();
        if (!ready) return;
        ready.poster.printPoster(ready.dataUrl, posterBus(item));
    }

    function promptReason(title, text, confirmText) {
        if (typeof window.adminPromptReason === 'function') { return window.adminPromptReason(title, text, confirmText, 255); }
        busNotify('error', 'Confirmation unavailable', 'Please reload the page to load the required reason component.');
        return Promise.resolve(null);
    }

    function qrChange(mode) {
        var item = currentDetailItem();
        if (!item) return;
        var verb = mode === 'revoke' ? 'Revoke QR: ' + item.bus_id : 'Replace QR: ' + item.bus_id;
        promptReason(verb, 'This change is recorded in the audit trail and the QR history.', mode === 'revoke' ? 'Revoke' : 'Replace').then(function (reason) {
            if (!reason) return;
            var entries = [['mode', mode], ['reason', reason], ['bus_ref', item.ref]];
            beginSecurityVerify('edit', securityTargetWithRef(item.ref), entries, verb).then(function (token) {
                if (!token) return;
                fetch('/admin/api/Bus/QrReplace.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({bus_ref: item.ref, bus_id: item.bus_id, mode: mode, reason: reason, security_token: token}),
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (!res || res.success === false) throw new Error((res && res.message) || 'Could not update the QR code.');
                    delete state.qrCache[item.ref];
                    state.historyCache = {};
                    loadQrInfo(item);
                    loadHistory(item);
                    busNotify('success', mode === 'revoke' ? 'QR revoked' : 'QR replaced', res.message || '');
                }).catch(function (err) {
                    busNotify('error', 'QR change failed', err.message || 'Please try again.');
                });
            });
        });
    }

    function prepareQr() {
        var item = currentDetailItem();
        if (!item) return;
        fetch('/admin/api/Bus/QrBackfill.php', {method: 'POST', credentials: 'same-origin', cache: 'no-store'})
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || res.success === false) throw new Error((res && res.message) || 'Could not prepare the QR code.');
                delete state.qrCache[item.ref];
                loadQrInfo(item);
                busNotify('success', 'QR prepared', res.message || 'The bus QR code is ready.');
            })
            .catch(function (err) {
                busNotify('error', 'QR preparation failed', err.message || 'Please try again.');
            });
    }

    /* --------------------------- bulk + archived + export --------------------------- */

    function openBulkModal() {
        el('busBulkSummary').textContent = 'Checking buses without a QR code...';
        el('busBulkList').innerHTML = '';
        bootstrap.Modal.getOrCreateInstance(el('busBulkModal')).show();
        loadBulkMissing();
    }

    function loadBulkMissing() {
        fetch('/admin/api/Bus/QrBackfill.php?dry_run=1', {credentials: 'same-origin', cache: 'no-store'})
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || res.success === false) throw new Error((res && res.message) || 'Could not check the buses.');
                var missing = res.buses || [];
                el('busBulkSummary').innerHTML = missing.length
                    ? '<i class="bi bi-info-circle"></i> <strong>' + missing.length + '</strong> bus(es) have no QR code yet.'
                    : '<i class="bi bi-check2-circle"></i> Every registered bus already has a QR code.';
                el('busBulkList').innerHTML = missing.slice(0, 50).map(function (bus) {
                    return '<div class="bus-bulk-row"><strong>' + esc(bus.bus_id) + '</strong>' +
                        '<span class="bus-bulk-meta">' + esc(bus.plate || 'No plate') + (bus.unit_number ? ' · Unit ' + esc(bus.unit_number) : '') + '</span></div>';
                }).join('');
            })
            .catch(function (err) {
                el('busBulkSummary').textContent = err.message || 'Could not load the bus QR list.';
            });
    }

    function runBulkPrepare() {
        var button = el('busBulkPrepare');
        button.disabled = true;
        button.innerHTML = '<i class="bi bi-hourglass-split"></i> Preparing...';
        fetch('/admin/api/Bus/QrBackfill.php', {method: 'POST', credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || res.success === false) throw new Error((res && res.message) || 'Could not prepare the QR codes.');
                busNotify('success', 'QR preparation complete', res.message || '');
                state.qrCache = {};
                loadBulkMissing();
                load();
            })
            .catch(function (err) {
                busNotify('error', 'QR preparation failed', err.message || 'Please try again.');
            })
            .finally(function () {
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-magic"></i> Prepare missing QR codes';
            });
    }

    function generateBulkPdf() {
        var button = el('busBulkPdf');
        button.disabled = true;
        fetch('/admin/api/Bus/QrList.php', {credentials: 'same-origin', cache: 'no-store'})
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || res.success === false) throw new Error((res && res.message) || 'Could not load the bus QR list.');
                var buses = res.buses || [];
                if (!buses.length) {
                    busNotify('error', 'Nothing to print', 'No bus has a QR code yet. Prepare the missing codes first.');
                    return null;
                }
                return renderBulkPdf(buses);
            })
            .catch(function (err) {
                busNotify('error', 'QR PDF failed', err.message || 'Please try again.');
            })
            .finally(function () {
                button.disabled = false;
            });
    }

    function renderBulkPdf(buses) {
        if (!window.jspdf || !window.jspdf.jsPDF) {
            return Promise.reject(new Error('The PDF library is not available.'));
        }
        var pdf = new window.jspdf.jsPDF({unit: 'mm', format: 'a4', orientation: 'portrait'});
        var pageWidth = 210, pageHeight = 297;
        var cols = 2, rowsPerPage = 3;
        var cellWidth = pageWidth / cols;
        var cellHeight = (pageHeight - 24) / rowsPerPage;
        var qrSize = 62;
        var index = 0;

        function drawBus(bus) {
            return new Promise(function (resolve) {
                var holder = document.createElement('div');
                holder.style.cssText = 'position:fixed;left:-9999px;top:0;';
                document.body.appendChild(holder);
                try {
                    new window.QRCode(holder, {text: bus.payload_url, width: 180, height: 180, correctLevel: window.QRCode.CorrectLevel.H});
                } catch (e) {
                    holder.remove();
                    resolve(false);
                    return;
                }
                setTimeout(function () {
                    var canvas = holder.querySelector('canvas');
                    var col = index % cols;
                    var row = Math.floor((index % (cols * rowsPerPage)) / cols);
                    var pageIndex = Math.floor(index / (cols * rowsPerPage));
                    if (index > 0 && index % (cols * rowsPerPage) === 0) {
                        pdf.addPage();
                    }
                    var x = col * cellWidth + (cellWidth - qrSize) / 2;
                    var y = 18 + row * cellHeight;
                    if (canvas) {
                        pdf.addImage(canvas.toDataURL('image/png'), 'PNG', x, y, qrSize, qrSize);
                    }
                    pdf.setFontSize(11);
                    pdf.text('Bus ID: ' + bus.bus_id, col * cellWidth + cellWidth / 2, y + qrSize + 6, {align: 'center'});
                    pdf.setFontSize(10);
                    pdf.text('Unit: ' + (bus.unit_number || '—'), col * cellWidth + cellWidth / 2, y + qrSize + 12, {align: 'center'});
                    holder.remove();
                    index++;
                    resolve(true);
                }, 40);
            });
        }

        var chain = Promise.resolve();
        buses.forEach(function (bus) {
            chain = chain.then(function () { return drawBus(bus); });
        });
        return chain.then(function () {
            pdf.save('bus-qr-codes-' + new Date().toISOString().slice(0, 10) + '.pdf');
        });
    }

    function openArchivedModal() {
        var host = el('busArchivedList');
        host.innerHTML = '<div class="rmg-state"><i class="bi bi-hourglass-split"></i><h6>Loading archived buses...</h6></div>';
        bootstrap.Modal.getOrCreateInstance(el('busArchivedModal')).show();
        fetch('/admin/api/Bus/list.php?status=archived&per_page=100&sort=bus_asc', {credentials: 'same-origin', cache: 'no-store'})
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) throw new Error(res.message || 'Could not load archived buses.');
                var items = res.data || [];
                state.archivedItems = {};
                items.forEach(function (bus) { state.archivedItems[String(bus.ref)] = bus; });
                if (!items.length) {
                    host.innerHTML = '<div class="rmg-state"><i class="bi bi-archive"></i><h6>No archived buses</h6>Archived buses keep their Bus ID and QR history.</div>';
                    return;
                }
                host.innerHTML = items.map(function (bus) {
                    return '<div class="bus-archived-row" data-ref="' + esc(bus.ref) + '">' +
                        '<div class="bus-archived-who"><div class="bus-archived-name">' + esc(bus.bus_id) + '</div>' +
                        '<div class="bus-archived-sub">' + esc(bus.plate || 'No plate') + (bus.unit_number ? ' · Unit ' + esc(bus.unit_number) : '') + '</div>' +
                        '<div class="bus-archived-sub"><strong>Reason:</strong> ' + esc(bus.archive_reason || 'Unavailable') + '</div>' +
                        '<div class="bus-archived-sub"><strong>Archived:</strong> ' + esc(bus.archived_at ? fmtDate(bus.archived_at) : 'Unavailable') + ' · <strong>By:</strong> ' + esc(bus.archived_by || 'Unavailable') + '</div></div>' +
                        '<button type="button" class="rmg-btn rmg-btn-sm rmg-btn-primary" data-action="archived-restore" data-ref="' + esc(bus.ref) + '" data-name="' + esc(bus.bus_id) + '" aria-label="Restore bus ' + esc(bus.bus_id) + '"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>' +
                        '</div>';
                }).join('');
            })
            .catch(function (err) {
                host.innerHTML = '<div class="rmg-state error"><i class="bi bi-exclamation-circle"></i><h6>Could not load archived buses</h6>' + esc(err.message || 'Please try again.') + '</div>';
            });
    }

    function archiveBus(index) {
        var item = findItem(index);
        if (!item || state.busy) return;
        var verb = 'Archive bus: ' + item.bus_id;
        promptReason(verb, 'This change is recorded in the audit trail. The QR stops initiating boarding.', 'Archive').then(function (reason) {
            if (!reason) return;
            var entries = [['bus_ref', item.ref], ['reason', reason]];
            beginSecurityVerify('archive', securityTargetWithRef(item.ref), entries, verb).then(function (token) {
                if (!token) return;
                fetch('/admin/api/archive_bus.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({bus_ref: item.ref, reason: reason, security_token: token}),
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (!res || res.success === false) throw new Error((res && res.message) || 'Could not archive the bus.');
                    closeDetail(true);
                    load();
                    busNotify('success', 'Bus archived', res.message || '');
                }).catch(function (err) {
                    busNotify('error', 'Action failed', err.message || 'Please try again.');
                });
            });
        });
    }

    function exportCsv() {
        var button = el('busExport');
        if (button && button.disabled) return;
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Exporting...';
        }
        var params = new URLSearchParams();
        if (state.search) params.set('search', state.search);
        if (state.status) params.set('status', state.status);
        if (state.busType) params.set('bus_type', state.busType);
        if (state.route) params.set('route', state.route);
        if (state.driver) params.set('driver', state.driver);
        if (state.createdFrom) params.set('registered_from', state.createdFrom);
        if (state.createdTo) params.set('registered_to', state.createdTo);
        params.set('sort', state.sort);

        fetch('/admin/api/Bus/export.php?' + params.toString(), {cache: 'no-store', credentials: 'same-origin'})
            .then(function (response) {
                var type = (response.headers && response.headers.get('Content-Type')) || '';
                if (!response.ok || type.indexOf('application/json') !== -1) {
                    return response.text().then(function (text) {
                        var message = 'Could not export the bus directory.';
                        try {
                            var json = JSON.parse(text);
                            if (json && json.message) message = json.message;
                        } catch (e) {
                        }
                        throw new Error(message);
                    });
                }
                return response.blob().then(function (blob) {
                    if (!blob || blob.size === 0) throw new Error('The export returned no data. Please try again.');
                    var url = URL.createObjectURL(blob);
                    var link = document.createElement('a');
                    link.href = url;
                    link.download = 'bus-management-' + (state.status || 'all') + '.csv';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
                });
            })
            .catch(function (error) {
                busNotify('error', 'Export failed', (error && error.message) || 'Could not export the bus directory.');
            })
            .finally(function () {
                if (button) {
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-filetype-csv"></i> Export CSV';
                }
            });
    }

    /* ------------------------------ event wiring ------------------------------ */

    function onListClick(event) {
        var clearButton = event.target.closest ? event.target.closest('#busEmptyClear') : null;
        if (clearButton) {
            clearFilters();
            return;
        }
        var target = event.target.closest('[data-action]');
        if (!target) return;
        var action = target.getAttribute('data-action');
        var index = target.getAttribute('data-index');

        if (action === 'details' && index !== null) {
            openDetail(Number(index));
            return;
        }
        if (action === 'archive' && index !== null) {
            archiveBus(index);
            return;
        }
        if (action === 'restore' && index !== null) {
            var restoreItem = findItem(index);
            if (restoreItem) openRestoreForm(restoreItem);
            return;
        }
        if (action === 'edit' && index !== null) {
            var item = findItem(index);
            if (item) openEdit(item);
            return;
        }
        if (action === 'qr' && index !== null) {
            openDetail(Number(index), 'qr');
            return;
        }
        if (action === 'bulk-qr') {
            openBulkModal();
            return;
        }
        if (action === 'qr-prepare') {
            prepareQr();
            return;
        }
        if (action === 'qr-download-png') {
            downloadQrPng();
            return;
        }
        if (action === 'qr-download-pdf') {
            downloadQrPdf();
            return;
        }
        if (action === 'qr-print') {
            printQr();
            return;
        }
        if (action === 'qr-replace') {
            qrChange('replace');
            return;
        }
        if (action === 'qr-revoke') {
            qrChange('revoke');
            return;
        }
        if (action === 'archived-restore') {
            openRestoreForm(state.archivedItems[String(target.getAttribute('data-ref'))]);
        }
    }

    function onStepChipClick(event) {
        var chip = event.target.closest ? event.target.closest('[data-bus-step]') : null;
        if (!chip) return;
        goToStep(chip.getAttribute('data-bus-mode'), parseInt(chip.getAttribute('data-bus-step'), 10) || 0);
    }

    function bindUnsavedGuard(modalId, dirtyKey, containerId) {
        var modal = el(modalId);
        var container = el(containerId);
        if (!modal || !container || !modal.addEventListener) return;
        container.addEventListener('input', function () { state.formDirty[dirtyKey] = true; });
        container.addEventListener('change', function () { state.formDirty[dirtyKey] = true; });
        modal.addEventListener('hide.bs.modal', function (event) {
            if (state.formForceClose[dirtyKey] || !state.formDirty[dirtyKey]) return;
            event.preventDefault();
            var proceed = function () {
                state.formForceClose[dirtyKey] = true;
                var instance = bootstrap.Modal.getInstance(modal);
                if (instance) instance.hide();
            };
            if (typeof window.showConfirm === 'function') {
                window.showConfirm('Discard unsaved changes?', 'This form has unsaved changes. Close it and lose them?', proceed, function () {});
            }
        });
        modal.addEventListener('hidden.bs.modal', function () {
            state.formDirty[dirtyKey] = false;
            state.formForceClose[dirtyKey] = false;
        });
    }

    function init() {
        var url = new URLSearchParams(window.location.search);
        if (url.get('search')) state.search = url.get('search');
        if (url.get('status') && (CFG.statuses || {})[url.get('status')]) state.status = url.get('status');
        if (url.get('type')) state.busType = url.get('type');
        if (url.get('route')) state.route = url.get('route');
        if (url.get('driver')) state.driver = url.get('driver');
        if (url.get('from')) state.createdFrom = url.get('from');
        if (url.get('to')) state.createdTo = url.get('to');
        if (['bus_asc', 'bus_desc', 'plate_asc', 'plate_desc', 'newest', 'oldest'].indexOf(url.get('sort')) !== -1) state.sort = url.get('sort');
        if (url.get('page')) state.page = Math.max(1, parseInt(url.get('page'), 10) || 1);

        el('busSearch').value = state.search;
        var statusSelect = el('busStatus');
        Object.keys(CFG.statuses || {}).forEach(function (key) {
            var option = document.createElement('option');
            option.value = key;
            option.textContent = CFG.statuses[key];
            statusSelect.appendChild(option);
        });
        statusSelect.value = state.status;
        el('busSort').value = state.sort;
        el('busPerPage').value = String(state.perPage);
        el('busCreatedFrom').value = state.createdFrom;
        el('busCreatedTo').value = state.createdTo;
        renderSummary();
        ensureBusOptions(populateFilterOptions);

        el('busSearch').addEventListener('input', debounce(function () {
            state.search = el('busSearch').value.trim();
            state.page = 1;
            closeDetail(true);
            load();
        }, 300));

        statusSelect.addEventListener('change', function () {
            state.status = statusSelect.value;
            state.page = 1;
            closeDetail(true);
            load();
        });
        el('busType').addEventListener('change', function () {
            state.busType = el('busType').value;
            state.page = 1;
            closeDetail(true);
            load();
        });
        el('busRoute').addEventListener('change', function () {
            state.route = el('busRoute').value;
            state.page = 1;
            closeDetail(true);
            load();
        });
        el('busDriver').addEventListener('change', function () {
            state.driver = el('busDriver').value;
            state.page = 1;
            closeDetail(true);
            load();
        });
        el('busCreatedFrom').addEventListener('change', function () {
            state.createdFrom = el('busCreatedFrom').value;
            state.page = 1;
            load();
        });
        el('busCreatedTo').addEventListener('change', function () {
            state.createdTo = el('busCreatedTo').value;
            state.page = 1;
            load();
        });
        el('busSort').addEventListener('change', function () {
            state.sort = el('busSort').value || 'bus_asc';
            state.page = 1;
            load();
        });
        el('busPerPage').addEventListener('change', function () {
            state.perPage = parseInt(el('busPerPage').value, 10) || 10;
            state.page = 1;
            load();
        });
        el('busClearFilters').addEventListener('click', clearFilters);
        el('busArchivedBtn').addEventListener('click', openArchivedModal);
        el('busRefresh').addEventListener('click', load);
        el('busExport').addEventListener('click', exportCsv);
        el('busBulkQr').addEventListener('click', openBulkModal);
        el('busBulkPrepare').addEventListener('click', runBulkPrepare);
        el('busBulkPdf').addEventListener('click', generateBulkPdf);
        el('busAddBtn').addEventListener('click', openAddForm);
        el('busAddSave').addEventListener('click', saveAdd);
        el('busEditSave').addEventListener('click', saveEdit);
        el('busRestoreSave').addEventListener('click', saveRestore);

        ['busList', 'busDetailBody', 'busDetailActions', 'busArchivedList'].forEach(function (id) {
            var host = el(id);
            if (host) host.addEventListener('click', onListClick);
        });
        ['busAddSteps', 'busEditSteps', 'busRestoreSteps'].forEach(function (id) {
            var host = el(id);
            if (host) host.addEventListener('click', onStepChipClick);
        });
        el('busAddNext').addEventListener('click', function () { goToStep('add', (state.wizard.add || 0) + 1); });
        el('busAddBack').addEventListener('click', function () { goToStep('add', (state.wizard.add || 0) - 1); });
        el('busEditNext').addEventListener('click', function () { goToStep('edit', (state.wizard.edit || 0) + 1); });
        el('busEditBack').addEventListener('click', function () { goToStep('edit', (state.wizard.edit || 0) - 1); });
        el('busRestoreNext').addEventListener('click', function () { goToStep('restore', (state.wizard.restore || 0) + 1); });
        el('busRestoreBack').addEventListener('click', function () { goToStep('restore', (state.wizard.restore || 0) - 1); });

        el('busPager').addEventListener('click', function (event) {
            var button = event.target.closest('[data-page]');
            if (!button || button.disabled) return;
            state.page = parseInt(button.getAttribute('data-page'), 10) || 1;
            closeDetail(true);
            load();
        });

        el('busSecurityBtn').addEventListener('click', openSecurityButton);
        el('busSecurityConfirm').addEventListener('click', submitSecurityVerify);
        el('busSecurityCode').addEventListener('keydown', function (event) {
            if (event.key === 'Enter') { event.preventDefault(); submitSecurityVerify(); }
        });
        el('busSecurityModal').addEventListener('hidden.bs.modal', function () {
            if (state.pendingSecurity) {
                var resolve = state.pendingSecurity.resolve;
                state.pendingSecurity = null;
                resolve(null);
            }
        });
        el('busSecSettingsSave').addEventListener('click', saveSecuritySettings);
        el('busSecSettingsModal').addEventListener('hidden.bs.modal', function () {
            el('busSecSettingsError').hidden = true;
        });

        var reviewUpdater = debounce(function () {
            ['add', 'edit', 'restore'].forEach(function (mode) {
                var review = el(modeUi(mode).review);
                if (review && !review.hidden) review.innerHTML = renderWizardReview(mode);
            });
        }, 250);
        ['busAddFields', 'busEditFields', 'busRestoreFields'].forEach(function (id) {
            var container = el(id);
            container.addEventListener('input', reviewUpdater);
            container.addEventListener('change', reviewUpdater);
        });

        bindUnsavedGuard('busAddModal', 'add', 'busAddFields');
        bindUnsavedGuard('busEditModal', 'edit', 'busEditFields');
        bindUnsavedGuard('busRestoreModal', 'restore', 'busRestoreFields');

        fetchSecurityStatus().then(updateSecurityChip).catch(function () {});

        load();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
