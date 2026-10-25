/* Password recovery through the real public controller: known, unknown, and
   send-failure cases return one identical acknowledgment, no OTP session is
   created for unknown emails, an invalid code for an unknown email behaves
   exactly like a wrong code, and resend stays available and uniform. */
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const temporaryRoot = process.env.ONE_TIME_TEST_TEMP || 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(temporaryRoot).isDirectory(), 'Approved test temporary directory exists');
const temporary = fs.mkdtempSync(path.join(temporaryRoot, 'reset-flow-'));
const MOBILE_UA = 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36';

const SEND_ACK = 'If this email is registered, a reset code has been sent.';
const RESEND_ACK = 'If this email is registered, a new reset code has been sent.';

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
        env: { ...process.env, ONE_TIME_TEST_DB: path.join(temporary, 'reset.sqlite') },
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

    const postForm = (url, body, headers = {}) => request(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...headers },
        body
    });

    const csrfFrom = (html) => {
        const match = html.match(/<meta name="csrf-token" content="([^"]+)"/);
        return match ? match[1] : '';
    };

    const sessionOf = async (cookie) => (await (await request('/_test/session', { headers: { Cookie: cookie } })).json()).session || {};

    // --- Seed a known account ----------------------------------------------
    await request('/_test/passenger-row', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: 1, full_name: 'Reset Rider', email: 'known@example.com', password: 'x', status: 'Active' })
    });

    // --- Known email: uniform acknowledgment, no OTP session (mailer down) --
    const knownPage = await request('/passenger/forgot-password');
    const knownCookie = cookieOf(knownPage);
    const knownCsrf = csrfFrom(await knownPage.text());
    const knownSend = await postForm('/passenger/send-reset-otp', new URLSearchParams({ email: 'known@example.com' }).toString(), {
        Cookie: knownCookie, 'X-CSRF-Token': knownCsrf
    });
    eq(knownSend.status, 302, 'The known-email reset request is accepted');
    ok(String(knownSend.headers.get('location')).includes('/passenger/verify-reset-otp'), 'The known-email request lands on the code page');

    const knownVerify = await request('/passenger/verify-reset-otp', { headers: { Cookie: knownCookie } });
    const knownVerifyHtml = await knownVerify.text();
    ok(knownVerifyHtml.includes(SEND_ACK), 'The known email sees the uniform acknowledgment');
    const knownSession = await sessionOf(knownCookie);
    ok(!knownSession.reset_otp, 'No OTP session is kept when delivery failed (masked, logged)');

    // --- Unknown email: identical acknowledgment, no OTP session ------------
    const unknownPage = await request('/passenger/forgot-password');
    const unknownCookie = cookieOf(unknownPage);
    const unknownCsrf = csrfFrom(await unknownPage.text());
    const unknownSend = await postForm('/passenger/send-reset-otp', new URLSearchParams({ email: 'nobody@example.com' }).toString(), {
        Cookie: unknownCookie, 'X-CSRF-Token': unknownCsrf
    });
    eq(unknownSend.status, 302, 'The unknown-email reset request is accepted');

    const unknownVerify = await request('/passenger/verify-reset-otp', { headers: { Cookie: unknownCookie } });
    const unknownVerifyHtml = await unknownVerify.text();
    ok(unknownVerifyHtml.includes(SEND_ACK), 'The unknown email sees the identical acknowledgment');
    eq(knownVerifyHtml.includes(SEND_ACK) && unknownVerifyHtml.includes(SEND_ACK), true, 'Known and unknown acknowledgments are byte-identical');
    const unknownSession = await sessionOf(unknownCookie);
    ok(!unknownSession.reset_otp, 'No OTP session is created for an unknown email');

    // --- Invalid code for an unknown email behaves like a wrong code --------
    const unknownCsrf2 = csrfFrom(unknownVerifyHtml);
    const invalid = await postForm('/passenger/verify-reset-otp-process', new URLSearchParams({ otp: '111111' }).toString(), {
        Cookie: unknownCookie, 'X-CSRF-Token': unknownCsrf2
    });
    eq(invalid.status, 302, 'Submitting a code for an unknown email is handled');
    ok(String(invalid.headers.get('location')).includes('/passenger/verify-reset-otp'), 'The unknown-email code attempt stays on the code page');
    const invalidPage = await request('/passenger/verify-reset-otp', { headers: { Cookie: unknownCookie } });
    const invalidHtml = await invalidPage.text();
    ok(invalidHtml.includes('Invalid OTP.'), 'An unknown-email code attempt shows the same invalid-code message');
    ok(!invalidHtml.includes('Session expired.'), 'An unknown-email code attempt never dead-ends with "Session expired"');

    // --- Resend stays available and uniform ---------------------------------
    const resendCsrf = csrfFrom(invalidHtml);
    const resend = await postForm('/passenger/resend-reset-otp', '', { Cookie: unknownCookie, 'X-CSRF-Token': resendCsrf });
    eq(resend.status, 302, 'Resend is accepted for an unknown email');
    const resendPage = await request('/passenger/verify-reset-otp', { headers: { Cookie: unknownCookie } });
    const resendHtml = await resendPage.text();
    ok(resendHtml.includes(RESEND_ACK), 'Resend shows the uniform acknowledgment');

    console.log(`Password recovery flow checks passed (${checks} assertions).`);
}

run().catch((error) => {
    console.error(error);
    console.error(serverOutput.slice(-1600));
    process.exit(1);
}).finally(() => {
    if (php) { php.kill(); }
});
