const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');

const storageSource = fs.readFileSync(path.join(__dirname, '../public/js/passenger-storage.js'), 'utf8');
const layoutSource = fs.readFileSync(path.join(__dirname, '../views/layout/csrf.php'), 'utf8');

function makeStorage(seed) {
    const data = Object.assign({}, seed);
    return {
        data,
        get length() { return Object.keys(data).length; },
        key(i) { return Object.keys(data)[i] ?? null; },
        getItem(k) { return Object.prototype.hasOwnProperty.call(data, k) ? data[k] : null; },
        setItem(k, v) { data[k] = String(v); },
        removeItem(k) { delete data[k]; },
        clear() { for (const k of Object.keys(data)) delete data[k]; }
    };
}

const passengerKeys = {
    remember_passenger: 'selector:token',
    passenger_homepage_state: '{"paymentMethod":"cash"}',
    passenger_active_trip_info: '{"trip_id":20}',
    rconnect_continuation_token: 'abc',
    rconnect_companion_dismissed: '1',
    rconnect_ot_trip: '{"passenger_trip_id":5}',
    rconnect_ot_trip_history: '[{"passenger_trip_id":5}]',
    passenger_cached_trip: 'legacy',
    active_trip_info: 'legacy'
};
const preferenceKeys = {
    passenger_lang: 'en',
    appInstalled: 'true',
    hideInstallPrompt: 'true'
};

const localStorage = makeStorage(Object.assign({}, passengerKeys, preferenceKeys));
const sessionStorage = makeStorage({
    ot_receipt_token_77: 'tok',
    registered_passenger_code: 'PSG-0001'
});

const sandbox = {
    window: {},
    localStorage,
    sessionStorage,
    document: {
        querySelector: () => null,
        readyState: 'complete',
        addEventListener: () => {},
        createElement: () => ({ className: '', innerHTML: '' }),
        getElementById: () => null,
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

/* The shared registry file defines window.PassengerStorage. */
vm.runInNewContext(storageSource, sandbox);

const registry = sandbox.window.PassengerStorage;
assert.ok(registry && typeof registry.reset === 'function', 'passenger-storage.js exposes PassengerStorage.reset');

/* Registry exposes every key the app writes today. */
const expectedKeys = [
    'identityToken', 'language', 'homepageState', 'activeTripInfo', 'continuationToken',
    'companionDismissed', 'oneTimeTrip', 'oneTimeTripHistory', 'appInstalled', 'hideInstallPrompt'
];
for (const key of expectedKeys) {
    assert.ok(registry.KEYS[key], 'KEYS.' + key + ' is registered');
}

/* reset() clears passenger-scoped keys, keeps preferences, clears session. */
registry.reset();

for (const key of Object.keys(passengerKeys)) {
    assert.equal(localStorage.getItem(key), null, key + ' is removed by reset()');
}
for (const key of Object.keys(preferenceKeys)) {
    assert.equal(localStorage.getItem(key), preferenceKeys[key], key + ' survives reset()');
}
assert.equal(sessionStorage.length, 0, 'sessionStorage is cleared by reset()');

/* clearSession() in the layout page delegates to the shared reset routine. */
const sessionStart = layoutSource.indexOf('var PassengerSession = {');
const sessionEnd = layoutSource.indexOf('window.PassengerSession = PassengerSession;');
assert.ok(sessionStart !== -1 && sessionEnd !== -1, 'PassengerSession is present in csrf.php');

vm.runInNewContext(
    layoutSource.slice(sessionStart, sessionEnd) + '\nwindow.PassengerSession = PassengerSession;',
    sandbox
);

localStorage.setItem('passenger_active_trip_info', '{"trip_id":9}');
localStorage.setItem('rconnect_ot_trip', '{"passenger_trip_id":9}');
localStorage.setItem('remember_passenger', 'selector:token2');
sessionStorage.setItem('ot_receipt_token_9', 'tok');
sandbox.window.PassengerSession.clearSession();

assert.equal(localStorage.getItem('passenger_active_trip_info'), null, 'clearSession removes active trip cache');
assert.equal(localStorage.getItem('rconnect_ot_trip'), null, 'clearSession removes one-time cache');
assert.equal(localStorage.getItem('remember_passenger'), null, 'clearSession removes the local trusted-device token');
assert.equal(localStorage.getItem('passenger_lang'), 'en', 'clearSession keeps the language preference');
assert.equal(sessionStorage.length, 0, 'clearSession clears sessionStorage');

console.log('PassengerStorage registry, reset allow-list, and clearSession reuse checks passed.');
