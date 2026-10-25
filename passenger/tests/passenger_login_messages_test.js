/* Login responses through the real controller: unknown accounts and wrong
   passwords return byte-identical generic text, while archived and locked
   states are only revealed after the password is verified. */
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const temporaryRoot = process.env.ONE_TIME_TEST_TEMP || 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(temporaryRoot).isDirectory(), 'Approved test temporary directory exists');
const temporary = fs.mkdtempSync(path.join(temporaryRoot, 'login-messages-'));
const MOBILE_UA = 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36';

let php;
let serverOutput = '';
let checks = 0;
const eq = (actual, expected, message) => { assert.equal(actual, expected, message); checks++; };
const ok = (value, message) => { assert.ok(value, message); checks++; };
const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function decodeJsonString(raw) {
    try {
        return JSON.parse('"' + raw + '"');
    } catch {
        return raw;
    }
}

async function run() {
    const listener = net.createServer();
    await new Promise((resolve) => listener.listen(0, '127.0.0.1', resolve));
    const port = listener.address().port;
    await new Promise((resolve) => listener.close(resolve));
    const base = `http://127.0.0.1:${port}`;

    php = spawn('php', ['-S', `127.0.0.1:${port}`, path.join(__dirname, 'fixtures/passenger_router_http.php')], {
        cwd: root,
        env: { ...process.env, ONE_TIME_TEST_DB: path.join(temporary, 'messages.sqlite') },
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

    const postForm = (url, body, headers = {}) => request(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...headers },
        body
    });

    const csrfFrom = (html) => {
        const match = html.match(/<meta name="csrf-token" content="([^"]+)"/);
        return match ? match[1] : '';
    };

    /* One login attempt on a fresh session; returns the rendered alert text. */
    const attempt = async (login, password) => {
        const page = await request('/passenger/login');
        const html = await page.text();
        const cookie = cookieOf(page);
        const csrf = csrfFrom(html);

        await postForm('/passenger/login', new URLSearchParams({ login, password }).toString(), {
            Cookie: cookie,
            'X-CSRF-Token': csrf
        });

        const after = await request('/passenger/login', { headers: { Cookie: cookie } });
        const afterHtml = await after.text();
        /* The view renders the alert through showAuthAlert('loginAlert', variant, title, detail);
           variants come from json_encode (double quotes) or a literal. */
        const match = afterHtml.match(/showAuthAlert\('loginAlert',\s*['"](?:error|info|warning|locked|success)['"],\s*"((?:[^"\\]|\\.)*)",\s*"((?:[^"\\]|\\.)*)"\)/);

        if (!match) {
            return { html: afterHtml, text: '' };
        }

        return { html: afterHtml, text: decodeJsonString(match[1]) + ' ' + decodeJsonString(match[2]) };
    };

    // --- Seed accounts ------------------------------------------------------
    const lockedUntil = new Date(Date.now() + 10 * 60 * 1000).toISOString().slice(0, 19).replace('T', ' ');
    await postJson('/_test/passenger-row', { id: 1, full_name: 'Active Rider', email: 'active@example.com', password: 'right-password', status: 'Active' });
    await postJson('/_test/passenger-row', { id: 2, full_name: 'Archived Rider', email: 'archived@example.com', password: 'right-password', status: 'Archived' });
    await postJson('/_test/passenger-row', { id: 3, full_name: 'Locked Rider', email: 'locked@example.com', password: 'right-password', status: 'Active', login_locked_until: lockedUntil });

    // --- Wrong password (known) and unknown identity: identical generic ----
    const unknown = await attempt('nobody@example.com', 'whatever');
    const wrong = await attempt('active@example.com', 'wrong-password');
    ok(unknown.text.length > 0, 'The unknown-account attempt renders an error alert');
    eq(wrong.text, unknown.text, 'A wrong password returns byte-identical text to an unknown account');
    ok(/Invalid email\/phone or password\./.test(unknown.text), 'The generic message is the invalid-credentials wording');

    // --- Restricted states only after password verification -----------------
    const archivedWrong = await attempt('archived@example.com', 'wrong-password');
    eq(archivedWrong.text, unknown.text, 'An archived account with a wrong password returns the generic text');

    const archivedRight = await attempt('archived@example.com', 'right-password');
    ok(/inactive/i.test(archivedRight.text), 'An archived account with the correct password sees the archived notice');
    ok(archivedRight.text !== unknown.text, 'The archived notice differs from the generic failure only after verification');

    const lockedWrong = await attempt('locked@example.com', 'wrong-password');
    eq(lockedWrong.text, unknown.text, 'A locked account with a wrong password returns the generic text');

    const lockedRight = await attempt('locked@example.com', 'right-password');
    ok(/temporarily locked/i.test(lockedRight.text), 'A locked account with the correct password sees the lock notice');

    // --- Deleted accounts resolve as nonexistent ----------------------------
    await postJson('/_test/passenger-row', { id: 4, full_name: 'Deleted Rider', email: 'deleted@example.com', password: 'right-password', status: 'deleted' });
    const deleted = await attempt('deleted@example.com', 'right-password');
    eq(deleted.text, unknown.text, 'A deleted account with valid-looking credentials returns the generic text');

    console.log(`Login message checks passed (${checks} assertions).`);
}

run().catch((error) => {
    console.error(error);
    console.error(serverOutput.slice(-1600));
    process.exit(1);
}).finally(() => {
    if (php) { php.kill(); }
});
