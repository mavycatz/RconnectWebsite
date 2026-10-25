/* Homepage render for a valid regular session: full 200 page, and resilience
   when account-notification storage is unavailable (unreadable table). */
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const temporaryRoot = process.env.ONE_TIME_TEST_TEMP || 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(temporaryRoot).isDirectory(), 'Approved test temporary directory exists');
const temporary = fs.mkdtempSync(path.join(temporaryRoot, 'homepage-render-'));
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
        env: { ...process.env, ONE_TIME_TEST_DB: path.join(temporary, 'homepage.sqlite') },
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

    const postJson = (url, body, cookie) => request(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            ...(cookie ? { Cookie: cookie } : {})
        },
        body: JSON.stringify(body)
    });

    await postJson('/_test/passenger-row', { id: 9, full_name: 'Homepage Render Passenger' }, '');

    const sessionSet = await postJson('/_test/session-set', { passenger_id: 9 }, 'PHPSESSID=homerender1');
    const cookie = cookieOf(sessionSet) || 'PHPSESSID=homerender1';
    await postJson('/_test/session-row', { passenger_id: 9, revoked: false }, cookie);

    // --- 1. Valid regular session renders the full homepage -------------------
    const home = await request('/passenger/home', { headers: { Cookie: cookie } });
    eq(home.status, 200, 'The homepage renders for a valid regular session');
    const html = await home.text();
    ok(html.includes('Hello, Passenger!'), 'The greeting renders with the name fallback');
    ok(html.includes('bottom-nav-container'), 'The homepage navigation renders');
    ok(html.includes('camera-card'), 'The scan card renders');
    ok(html.includes('<meta name="csrf-token"'), 'The CSRF bootstrap renders');
    ok(!html.includes('<h3>Something went wrong</h3>'), 'The generic service error page is not rendered');
    ok(!html.includes('passengerAccountAlert'), 'No notification modal is rendered without notifications');
    ok(!serverOutput.includes('Undefined array key "passenger_name"'), 'No undefined passenger_name warning is emitted');

    // --- 2. Unreadable notification storage keeps the homepage alive -----------
    const breakResponse = await postJson('/_test/account-notifications-break', {}, cookie);
    eq(breakResponse.status, 200, 'The fixture switches notification storage into a broken state');

    const brokenHome = await request('/passenger/home', { headers: { Cookie: cookie } });
    eq(brokenHome.status, 200, 'The homepage still renders when notification storage is unreadable');
    const brokenHtml = await brokenHome.text();
    ok(brokenHtml.includes('Hello, Passenger!'), 'The broken-storage render still shows the homepage greeting');
    ok(brokenHtml.includes('camera-card'), 'The broken-storage render still shows the scan card');
    ok(!brokenHtml.includes('<h3>Something went wrong</h3>'), 'Notification storage failure never produces the generic error page');
    ok(!brokenHtml.includes('passengerAccountAlert'), 'No notification modal is rendered when the read fails');
    ok(serverOutput.includes('Passenger account notifications read failed'), 'The notification read failure is logged');

    // --- 3. Dismissing a notification tolerates unreadable storage -------------
    const csrfMatch = brokenHtml.match(/<meta name="csrf-token" content="([^"]+)"/);
    ok(csrfMatch, 'The homepage exposes the CSRF token for the dismiss request');

    const dismiss = await request('/passenger/account/notifications/read', {
        method: 'POST',
        headers: {
            Cookie: cookie,
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': csrfMatch[1]
        },
        body: 'notification_id=1'
    });
    eq(dismiss.status, 302, 'The dismiss redirects instead of returning a server error');
    ok(String(dismiss.headers.get('location') || '').includes('/passenger/home'), 'The dismiss redirects back to the homepage');

    // --- 4. Dropped notification storage heals on the next request -------------
    const dropResponse = await postJson('/_test/account-notifications-drop', {}, cookie);
    eq(dropResponse.status, 200, 'The fixture drops the notification table');

    const healedHome = await request('/passenger/home', { headers: { Cookie: cookie } });
    eq(healedHome.status, 200, 'The homepage renders through the healed notification storage');
    const healedHtml = await healedHome.text();
    ok(healedHtml.includes('Hello, Passenger!'), 'The healed render is the full homepage');

    const healState = await (await request('/_test/account-notifications-state', { headers: { Cookie: cookie } })).json();
    eq(healState.object, 'table', 'The readiness check recreates the dropped notification table');
}

run()
    .then(() => {
        console.log(`${checks} homepage render checks passed.`);
        if (php) { php.kill(); }
    })
    .catch((error) => {
        console.error(serverOutput.slice(-2500));
        if (php) { php.kill(); }
        console.error(error);
        process.exit(1);
    });
