/* Real scan.php -> HTTP -> session snapshot -> diagnostics log, on isolated SQLite. */
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const temporaryRoot = process.env.ONE_TIME_TEST_TEMP || 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(temporaryRoot).isDirectory(), 'Approved test temporary directory exists');
const temporary = fs.mkdtempSync(path.join(temporaryRoot, 'one-time-scan-'));
const MOBILE_UA = 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36';

let php;
let serverOutput = '';
let checks = 0;
const eq = (actual, expected, message) => { assert.equal(actual, expected, message); checks++; };
const ok = (value, message) => { assert.ok(value, message); checks++; };
const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function run() {
    const listener = net.createServer();
    await new Promise((resolve) => listener.listen(0, '127.0.0.1', resolve));
    const port = listener.address().port;
    await new Promise((resolve) => listener.close(resolve));
    const base = `http://127.0.0.1:${port}`;

    php = spawn('php', ['-S', `127.0.0.1:${port}`, path.join(__dirname, 'fixtures/one_time_http.php')], {
        cwd: root,
        env: { ...process.env, ONE_TIME_TEST_DB: path.join(temporary, 'one_time.sqlite') },
        stdio: ['ignore', 'pipe', 'pipe']
    });
    php.stdout.on('data', (chunk) => { serverOutput += chunk; });
    php.stderr.on('data', (chunk) => { serverOutput += chunk; });

    let ready = false;
    for (let attempt = 0; attempt < 100; attempt++) {
        try {
            const response = await fetch(base + '/_test/ping');
            if (response.ok) { ready = true; break; }
        } catch {}
        await pause(30);
    }
    assert.ok(ready, 'PHP fixture server ready: ' + serverOutput.slice(-800));

    const request = (url, options = {}) => fetch(base + url, {
        redirect: 'manual',
        ...options,
        headers: { 'User-Agent': MOBILE_UA, ...(options.headers || {}) }
    });

    const seed = async (payload) => {
        const response = await request('/_test/seed', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        assert.ok(response.ok, 'Seed accepted: ' + JSON.stringify(payload));
    };

    const cookieOf = (response) => {
        const raw = typeof response.headers.getSetCookie === 'function'
            ? response.headers.getSetCookie()
            : [response.headers.get('set-cookie') || ''];
        return raw.map((value) => String(value).split(';')[0]).filter(Boolean).join('; ');
    };

    const scan = async (bus) => {
        const response = await request('/passenger/scan.php?bus=' + encodeURIComponent(bus));
        const cookie = cookieOf(response);
        const session = cookie
            ? await (await request('/_test/session', { headers: { Cookie: cookie } })).json()
            : null;
        return { response, session };
    };

    const locationOf = (response) => String(response.headers.get('location') || '');
    const logPath = path.join(root, 'storage', 'logs', 'passenger_qr_scan.log');
    const logSizeBefore = fs.existsSync(logPath) ? fs.statSync(logPath).size : 0;

    // --- 1. Located bus (Pinagpanaan) records a usable snapshot ------------
    await seed({ case: 'history', lat: 15.548218, lng: 120.933455, age: 10 });
    let result = await scan('Bus-12');
    eq(result.response.status, 302, 'A located scan still redirects');
    ok(locationOf(result.response).includes('/passenger/one-time/landing'), 'Located scan reaches the One-Time landing');
    ok(result.session && result.session.snapshot, 'The scan stores a location snapshot in the session');
    eq(result.session.snapshot.bus_id, 'Bus-12', 'Snapshot carries the scanned bus');
    eq(result.session.snapshot.trip_id, 77, 'Snapshot carries the active trip');
    eq(result.session.snapshot.available, true, 'Snapshot is available');
    eq(result.session.snapshot.usable, true, 'Snapshot is usable');
    eq(result.session.snapshot.source, 'driver_location_history', 'Snapshot source is the live history');
    eq(result.session.snapshot.direction, 'cabanatuan-rizal', 'Snapshot carries the travel direction');
    eq(result.session.snapshot.matched_stop, 'Pinagpanaan, Talavera', 'Snapshot matched the bus to Pinagpanaan');
    ok(result.session.snapshot.recorded_at, 'Snapshot carries the record timestamp');
    ok(result.session.snapshot.captured_at > 0, 'Snapshot carries the capture time');
    ok(result.session.boarding_lock, 'The scan registers a boarding lock in the session');
    eq(result.session.boarding_lock.boarding_id, 105, 'The lock stores the matched boarding fare id');
    eq(result.session.boarding_lock.bus, 'Bus-12', 'The lock stores the scanned bus');
    eq(result.session.boarding_lock.trip_id, 77, 'The lock stores the active trip');
    eq(result.session.boarding_lock.freshness, 'FRESH', 'The lock keeps the scan freshness');

    // --- 2. Stale position is recorded but unusable -------------------------
    await seed({ case: 'history', lat: 15.548218, lng: 120.933455, age: 600 });
    result = await scan('Bus-12');
    eq(result.response.status, 302, 'A stale-position scan still redirects');
    eq(result.session.snapshot.available, true, 'Stale position is still reported as available evidence');
    eq(result.session.snapshot.usable, false, 'Stale position is not usable');
    eq(result.session.snapshot.reason, 'stale', 'Stale reason is recorded');
    eq(result.session.snapshot.matched_stop, null, 'No boarding stop is matched from a stale position');
    eq(result.session.boarding_lock, null, 'A stale scan does not register a boarding lock');

    // --- 3. Missing position records an unavailable snapshot ----------------
    await seed({ case: 'clear' });
    result = await scan('Bus-12');
    eq(result.response.status, 302, 'A scan with no position still redirects');
    eq(result.session.snapshot.available, false, 'Missing position is unavailable');
    eq(result.session.snapshot.usable, false, 'Missing position is not usable');
    eq(result.session.snapshot.reason, 'no_driver_location', 'Missing-position reason is recorded');
    eq(result.session.snapshot.matched_stop, null, 'No stop is matched without a position');
    eq(result.session.boarding_lock, null, 'A scan without a position registers no boarding lock');

    // --- 4. An active simulation session is treated as the live position ----
    await seed({ case: 'simulation', lat: 15.566468, lng: 120.953142 });
    result = await scan('Bus-12');
    eq(result.session.snapshot.source, 'simulation', 'The snapshot uses the simulation override');
    eq(result.session.snapshot.usable, true, 'A simulated position is usable');
    eq(result.session.snapshot.matched_stop, 'Homestead 1, Talavera', 'The simulated bus matched Homestead 1');
    eq(result.session.boarding_lock.boarding_id, 107, 'The simulation scan locks its matched boarding stop');
    eq(result.session.boarding_lock.source, 'simulation', 'The lock keeps the simulated source');

    // --- 5. An unknown bus is still refused ---------------------------------
    result = await scan('Bus-404');
    eq(result.response.status, 302, 'An unknown bus redirects');
    ok(locationOf(result.response).includes('/passenger/login'), 'An unknown bus goes to login');
    eq(result.session.snapshot, null, 'No snapshot is stored for an unknown bus');
    eq(result.session.boarding_lock, null, 'No boarding lock is stored for an unknown bus');

    // --- 6. Diagnostics log captured both outcomes --------------------------
    const appended = fs.existsSync(logPath) ? fs.readFileSync(logPath, 'utf8').slice(logSizeBefore) : '';
    ok(appended.includes('"type":"bus_location_snapshot"'), 'Snapshot diagnostics are logged');
    ok(appended.includes('Bus location snapshot captured'), 'A captured outcome is logged');
    ok(appended.includes('Bus location snapshot unavailable'), 'An unavailable outcome is logged');

    assert.ok(appended.includes('"matched_stop":"Pinagpanaan, Talavera"'), 'The log records the matched stop');
    checks++;
}

run()
    .then(() => {
        console.log(`${checks} one-time scan flow checks passed.`);
        if (php) { php.kill(); }
    })
    .catch((error) => {
        console.error(serverOutput.slice(-2500));
        if (php) { php.kill(); }
        console.error(error);
        process.exit(1);
    });
