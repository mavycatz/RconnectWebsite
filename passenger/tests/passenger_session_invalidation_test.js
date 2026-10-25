/* Session invalidation through the shared layout script: both the explicit
   handlers and the fetch interceptor (401/403) must remove the real
   passenger-scoped keys through the shared reset routine. */
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');

const layoutSource = fs.readFileSync(path.join(__dirname, '../views/layout/csrf.php'), 'utf8');
const storageSource = fs.readFileSync(path.join(__dirname, '../public/js/passenger-storage.js'), 'utf8');

const scriptStart = layoutSource.indexOf('(function () {');
const scriptEnd = layoutSource.indexOf('})();');
assert.ok(scriptStart !== -1 && scriptEnd !== -1, 'The layout inline script is present');
const layoutScript = layoutSource.slice(scriptStart, scriptEnd + '})();'.length);

function makeStorage(seed) {
    const data = Object.assign({}, seed);
    return {
        get length() { return Object.keys(data).length; },
        key(i) { return Object.keys(data)[i] ?? null; },
        getItem(k) { return Object.prototype.hasOwnProperty.call(data, k) ? data[k] : null; },
        setItem(k, v) { data[k] = String(v); },
        removeItem(k) { delete data[k]; },
        clear() { for (const k of Object.keys(data)) delete data[k]; }
    };
}

function makeSandbox(fetchStub) {
    const localStorage = makeStorage({
        passenger_active_trip_info: '{"trip_id":20}',
        passenger_homepage_state: '{"paymentMethod":"gcash"}',
        rconnect_ot_trip: '{"passenger_trip_id":5}',
        remember_passenger: 'selector:token',
        passenger_lang: 'en'
    });
    const sessionStorage = makeStorage({ ot_receipt_token_77: 'tok' });

    const sandbox = {
        window: {
            location: { pathname: '/passenger/home', href: '' },
            fetch: fetchStub
        },
        localStorage,
        sessionStorage,
        document: {
            querySelector: () => null,
            readyState: 'complete',
            addEventListener: () => {},
            getElementById: () => null,
            createElement: () => ({ className: '', innerHTML: '' }),
            body: { appendChild: () => {} }
        },
        console,
        navigator: { onLine: true },
        Promise,
        Headers: function () {},
        setInterval: () => 0,
        clearInterval: () => {}
    };
    sandbox.window.localStorage = localStorage;
    sandbox.window.sessionStorage = sessionStorage;
    return sandbox;
}

const passengerKeys = [
    'passenger_active_trip_info',
    'passenger_homepage_state',
    'rconnect_ot_trip',
    'remember_passenger'
];

function load(sandbox) {
    vm.runInNewContext(storageSource, sandbox);
    vm.runInNewContext(layoutScript, sandbox);

    const alerts = [];
    sandbox.window.showAlert = function (type, title, message, onConfirm, buttonText) {
        alerts.push({ type, title, message, onConfirm, buttonText });
    };
    return alerts;
}

function assertCleared(sandbox, label) {
    for (const key of passengerKeys) {
        assert.equal(sandbox.localStorage.getItem(key), null, label + ': ' + key + ' is removed');
    }
    assert.equal(sandbox.sessionStorage.length, 0, label + ': sessionStorage is cleared');
    assert.equal(sandbox.localStorage.getItem('passenger_lang'), 'en', label + ': preferences survive');
}

(async () => {
    /* --- Explicit expired handler ---------------------------------------- */
    const expiredSandbox = makeSandbox(() => Promise.resolve({ status: 200 }));
    const expiredAlerts = load(expiredSandbox);
    expiredSandbox.window.PassengerSession.handleSessionExpired();
    assertCleared(expiredSandbox, 'handleSessionExpired');
    assert.equal(expiredAlerts.length, 1, 'handleSessionExpired shows one modal');
    assert.equal(expiredAlerts[0].title, 'Session Expired', 'the expiry modal is shown');
    expiredAlerts[0].onConfirm();
    assert.ok(String(expiredSandbox.window.location.href).includes('/passenger/login'),
        'confirming the modal redirects to login');

    /* --- 401 through the fetch interceptor -------------------------------- */
    const unauthorizedSandbox = makeSandbox(() => Promise.resolve({ status: 401 }));
    const unauthorizedAlerts = load(unauthorizedSandbox);
    await unauthorizedSandbox.window.fetch('/passenger/api/active-trips');
    await new Promise((resolve) => setTimeout(resolve, 10));
    assertCleared(unauthorizedSandbox, 'fetch 401');
    assert.equal(unauthorizedAlerts.length, 1, 'fetch 401 shows one modal');
    assert.equal(unauthorizedAlerts[0].title, 'Session Expired', 'fetch 401 maps to session expiry');

    /* --- 403 session-invalidated through the fetch interceptor ------------ */
    const revokedSandbox = makeSandbox(() => Promise.resolve({
        status: 403,
        clone: () => ({ json: () => Promise.resolve({ code: 'session_invalidated' }) })
    }));
    const revokedAlerts = load(revokedSandbox);
    await revokedSandbox.window.fetch('/passenger/api/active-trips');
    await new Promise((resolve) => setTimeout(resolve, 10));
    assertCleared(revokedSandbox, 'fetch 403');
    assert.equal(revokedAlerts.length, 1, 'fetch 403 shows one modal');
    assert.equal(revokedAlerts[0].title, 'Session Ended', 'fetch 403 maps to session revocation');

    console.log('Session invalidation cleanup checks passed.');
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
