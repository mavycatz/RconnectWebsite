/* Logout through the real front controller: the remember cookie is expired,
   only this browser's trusted-device token is revoked, the session row is
   marked logged out, and other passengers' tokens are untouched. */
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const temporaryRoot = process.env.ONE_TIME_TEST_TEMP || 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(temporaryRoot).isDirectory(), 'Approved test temporary directory exists');
const temporary = fs.mkdtempSync(path.join(temporaryRoot, 'logout-reset-'));
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
        env: { ...process.env, ONE_TIME_TEST_DB: path.join(temporary, 'logout.sqlite') },
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

    // --- Seed two passengers' trusted-device tokens -------------------------
    await postJson('/_test/remember-row', { passenger_id: 1, selector: 'aaaaaaaaaaaaaaaa', token_hash: 'hash-a' });
    await postJson('/_test/remember-row', { passenger_id: 2, selector: 'bbbbbbbbbbbbbbbb', token_hash: 'hash-b' });

    // --- Log passenger 1 in (fixture session) and get the CSRF token --------
    const sessionSet = await postJson('/_test/session-set', { passenger_id: 1 });
    eq(sessionSet.status, 200, 'The fixture session is created for passenger 1');
    const sessionCookie = cookieOf(sessionSet);
    ok(sessionCookie.includes('PHPSESSID='), 'The fixture session sets a PHP session cookie');

    const sessionInfo = await request('/_test/session', { headers: { Cookie: sessionCookie } });
    const session = await sessionInfo.json();
    const csrf = session.csrf;
    ok(csrf && csrf.length > 10, 'The session exposes a CSRF token for the logout POST');

    const sessionRow = await postJson('/_test/session-row', { passenger_id: 1 }, { Cookie: sessionCookie });
    eq(sessionRow.status, 200, 'A device-session row is seeded for the current session');

    // --- Logout with this browser's remember cookie -------------------------
    const logout = await request('/passenger/logout', {
        method: 'POST',
        headers: {
            Cookie: `${sessionCookie}; remember_passenger=aaaaaaaaaaaaaaaa:token-a`,
            'X-CSRF-Token': csrf
        }
    });

    eq(logout.status, 302, 'Logout redirects');
    ok(String(logout.headers.get('location')).includes('/passenger/login'), 'Logout redirects to the login page');

    const setCookies = typeof logout.headers.getSetCookie === 'function'
        ? logout.headers.getSetCookie()
        : [logout.headers.get('set-cookie') || ''];
    const rememberCookieClear = setCookies.find((value) => String(value).startsWith('remember_passenger='));
    ok(rememberCookieClear, 'Logout sends a Set-Cookie for remember_passenger');
    ok(/remember_passenger=deleted/.test(rememberCookieClear), 'The remember cookie is expired by the server');

    // --- Only this browser's token is revoked; others are untouched ---------
    const report = await (await request('/_test/auth-report')).json();
    const tokenA = report.remember.find((row) => row.selector === 'aaaaaaaaaaaaaaaa');
    const tokenB = report.remember.find((row) => row.selector === 'bbbbbbbbbbbbbbbb');
    ok(tokenA && tokenA.revoked_at, "This browser's trusted-device token is revoked");
    ok(tokenB && !tokenB.revoked_at, "Another passenger's trusted-device token is untouched");

    ok(report.sessions.some((row) => row.revoked_reason === 'logout'), 'The session row is revoked as logout');

    console.log(`Logout reset checks passed (${checks} assertions).`);
}

run().catch((error) => {
    console.error(error);
    console.error(serverOutput.slice(-1200));
    process.exit(1);
}).finally(() => {
    if (php) { php.kill(); }
});
