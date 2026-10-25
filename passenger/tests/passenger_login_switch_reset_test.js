/* Account switch through the real login controller: a successful trusted-device
   login runs the shared local-data reset before writing the new passenger's
   token, so a previous passenger's cached values cannot survive into the
   incoming session. */
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const temporaryRoot = process.env.ONE_TIME_TEST_TEMP || 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(temporaryRoot).isDirectory(), 'Approved test temporary directory exists');
const temporary = fs.mkdtempSync(path.join(temporaryRoot, 'login-switch-'));
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
        env: { ...process.env, ONE_TIME_TEST_DB: path.join(temporary, 'switch.sqlite') },
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

    // --- Seed passenger B and a valid trusted-device token ------------------
    const tokenSecret = 'secret-token-value';
    const seedPassenger = await postJson('/_test/passenger-row', {
        id: 1,
        full_name: 'Passenger B',
        email: 'switch@example.com',
        password: 'correct-horse-battery',
        status: 'Active'
    });
    eq(seedPassenger.status, 200, 'Passenger B is seeded with a hashed password');
    await postJson('/_test/remember-row', {
        passenger_id: 1,
        selector: 'cccccccccccccccc',
        token_hash: crypto.createHash('sha256').update(tokenSecret).digest('hex')
    });

    // --- Get the CSRF token from the login page -----------------------------
    const loginPage = await request('/passenger/login');
    eq(loginPage.status, 200, 'The login page renders');
    const loginHtml = await loginPage.text();
    const csrfMatch = loginHtml.match(/<meta name="csrf-token" content="([^"]+)"/);
    ok(csrfMatch, 'The login page exposes a CSRF token');
    const cookie = cookieOf(loginPage);

    // --- Login with the trusted token (OTP-free path) -----------------------
    const login = await request('/passenger/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            Cookie: cookie,
            'X-CSRF-Token': csrfMatch[1]
        },
        body: new URLSearchParams({
            login: 'switch@example.com',
            password: 'correct-horse-battery',
            trusted_token: 'cccccccccccccccc:' + tokenSecret
        }).toString()
    });

    eq(login.status, 200, 'The trusted-device login succeeds with a redirect page');
    const html = await login.text();

    const resetIndex = html.indexOf('PassengerStorage.reset()');
    const setItemIndex = html.indexOf('localStorage.setItem("remember_passenger"');
    ok(html.includes('/passenger/public/js/passenger-storage.js'), 'The success page loads the shared storage registry');
    ok(resetIndex !== -1, 'The success page runs the shared local-data reset');
    ok(setItemIndex !== -1, 'The success page stores the new trusted-device token');
    ok(resetIndex !== -1 && setItemIndex !== -1 && resetIndex < setItemIndex,
        'The reset runs before the new passenger token is written (no stale data survives)');
    ok(html.includes('/passenger/home') || html.includes('\\/passenger\\/home'),
        'The success page redirects to the homepage');

    console.log(`Login switch reset checks passed (${checks} assertions).`);
}

run().catch((error) => {
    console.error(error);
    console.error(serverOutput.slice(-1200));
    process.exit(1);
}).finally(() => {
    if (php) { php.kill(); }
});
