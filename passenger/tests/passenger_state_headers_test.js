/* State-bearing JSON endpoints must never be cached by the browser or PWA:
   trip/active, one-time/trip and trip/restore-state send Cache-Control no-store. */
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const temporaryRoot = process.env.ONE_TIME_TEST_TEMP || 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(temporaryRoot).isDirectory(), 'Approved test temporary directory exists');
const temporary = fs.mkdtempSync(path.join(temporaryRoot, 'state-headers-'));
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

    php = spawn('php', ['-S', `127.0.0.1:${port}`, path.join(__dirname, 'fixtures/passenger_router_http.php')], {
        cwd: root,
        env: { ...process.env, ONE_TIME_TEST_DB: path.join(temporary, 'headers.sqlite') },
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

    const cookieOf = (response) => {
        const raw = typeof response.headers.getSetCookie === 'function'
            ? response.headers.getSetCookie()
            : [response.headers.get('set-cookie') || ''];
        return raw.map((value) => String(value).split(';')[0]).filter(Boolean).join('; ');
    };

    const postJson = (url, body, headers = {}) => request(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...headers },
        body: JSON.stringify(body)
    });

    const cacheControlOf = (response) => String(response.headers.get('cache-control') || '').toLowerCase();

    // --- /passenger/trip/active ---------------------------------------------
    const active = await request('/passenger/trip/active');
    ok(cacheControlOf(active).includes('no-store'), 'trip/active sends Cache-Control no-store');

    // --- /passenger/one-time/trip -------------------------------------------
    const sessionSet = await postJson('/_test/session-set', { qr_bus: 'Bus-12', qr_trip: '77' });
    const cookie = cookieOf(sessionSet);
    const trip = await request('/passenger/one-time/trip', { headers: { Cookie: cookie } });
    ok(cacheControlOf(trip).includes('no-store'), 'one-time/trip sends Cache-Control no-store');

    // --- /passenger/trip/restore-state --------------------------------------
    await postJson('/_test/passenger-row', { id: 1, full_name: 'Header Passenger', status: 'Active' });
    const authSession = await postJson('/_test/session-set', { passenger_id: 1 });
    const authCookie = cookieOf(authSession);
    await postJson('/_test/session-row', { passenger_id: 1 }, { Cookie: authCookie });
    const restore = await request('/passenger/trip/restore-state', { headers: { Cookie: authCookie } });
    eq(restore.status, 200, 'trip/restore-state is reachable for an authenticated passenger');
    ok(cacheControlOf(restore).includes('no-store'), 'trip/restore-state sends Cache-Control no-store');

    console.log(`State endpoint cache-header checks passed (${checks} assertions).`);
}

run().catch((error) => {
    console.error(error);
    console.error(serverOutput.slice(-1200));
    process.exit(1);
}).finally(() => {
    if (php) { php.kill(); }
});
