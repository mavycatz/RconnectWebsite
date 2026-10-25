/* OTP flow resilience through the real front controller: login 2FA completion,
   registration verification completion, and the auxiliary-storage guards that
   keep those flows from being replaced by the generic service error page. */
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const temporaryRoot = process.env.ONE_TIME_TEST_TEMP || 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(temporaryRoot).isDirectory(), 'Approved test temporary directory exists');
const temporary = fs.mkdtempSync(path.join(temporaryRoot, 'otp-flow-'));
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
        env: { ...process.env, ONE_TIME_TEST_DB: path.join(temporary, 'otp.sqlite') },
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
        headers: { 'Content-Type': 'application/json', ...(cookie ? { Cookie: cookie } : {}) },
        body: JSON.stringify(body)
    });
    const postForm = (url, body, cookie, csrf) => request(url, {
        method: 'POST',
        headers: {
            Cookie: cookie,
            'Content-Type': 'application/x-www-form-urlencoded',
            ...(csrf ? { 'X-CSRF-Token': csrf } : {})
        },
        body
    });
    const csrfOf = async (cookie) => {
        const response = await request('/passenger/login', { headers: { Cookie: cookie } });
        const html = await response.text();
        const match = html.match(/<meta name="csrf-token" content="([^"]+)"/);
        return match ? match[1] : '';
    };
    const sessionPut = async (cookie, payload) => {
        const response = await postJson('/_test/session-put', payload, cookie);
        return cookieOf(response) || cookie;
    };
    const isErrorPage = (html) => html.includes('<h3>Something went wrong</h3>');

    const loginOtpSeed = {
        otp: '123456',
        otp_created: Math.floor(Date.now() / 1000),
        otp_attempts: 0,
        otp_user: { id: 21, full_name: 'Otp Login Passenger', email: 'otplogin@example.com', password: 'x' }
    };

    await postJson('/_test/passenger-row', {
        id: 21,
        full_name: 'Otp Login Passenger',
        email: 'otplogin@example.com',
        password: 'Secret123',
        status: 'Active',
        account_status: 'ACTIVE'
    });

    // --- 1. Login OTP completion succeeds -------------------------------------
    const loginCookieA = await sessionPut('PHPSESSID=otpA', loginOtpSeed);
    const loginCsrfA = await csrfOf(loginCookieA);
    const loginResA = await postForm('/passenger/login', 'otp=123456', loginCookieA, loginCsrfA);
    const loginHtmlA = await loginResA.text();
    eq(loginResA.status, 200, 'The login OTP completion responds');
    ok(loginHtmlA.includes('window.location.href'), 'The login OTP success redirects to the homepage');
    ok(!isErrorPage(loginHtmlA), 'The login OTP completion never shows the generic error page');
    const loginCookiesA = typeof loginResA.headers.getSetCookie === 'function' ? loginResA.headers.getSetCookie() : [];
    ok(loginCookiesA.some((value) => value.startsWith('remember_passenger=')), 'The trusted-device cookie is issued on success');

    // --- 2. Login OTP completion survives broken remember-token storage ---------
    await postJson('/_test/remember-tokens-break', {}, '');
    const loginCookieB = await sessionPut('PHPSESSID=otpB', loginOtpSeed);
    const loginCsrfB = await csrfOf(loginCookieB);
    const loginResB = await postForm('/passenger/login', 'otp=123456', loginCookieB, loginCsrfB);
    const loginHtmlB = await loginResB.text();
    eq(loginResB.status, 200, 'A login with unreadable remember-token storage still responds');
    ok(loginHtmlB.includes('window.location.href'), 'The login still redirects to the homepage without the remember token');
    ok(!isErrorPage(loginHtmlB), 'Broken remember-token storage never produces the generic error page');
    ok(serverOutput.includes('Passenger trusted-device token creation failed'), 'The remember-token failure is logged');

    // --- 3. Failure-path login survives a missing audit table -------------------
    await postJson('/_test/audit-trail-drop', {}, '');
    const failCookie = await sessionPut('PHPSESSID=otpC', {});
    const failCsrf = await csrfOf(failCookie);
    const failRes = await postForm('/passenger/login', 'login=otplogin%40example.com&password=WrongPassword1', failCookie, failCsrf);
    const failHtml = await failRes.text();
    eq(failRes.status, 302, 'A failed login redirects back to the login page');
    ok(String(failRes.headers.get('location') || '').includes('/passenger/login'), 'The failed login keeps its redirect outcome');
    ok(!isErrorPage(failHtml), 'A missing audit table never produces the generic error page');
    ok(serverOutput.includes('Passenger login audit failed'), 'The audit failure is logged');

    // --- 4. Registration OTP verification completes -----------------------------
    const registrationSeed = (email, phone) => ({
        otp: '654321',
        otp_expiry: Math.floor(Date.now() / 1000) + 300,
        otp_attempts: 0,
        passenger_password_hash: '$2y$10$abcdefghijklmnopqrstuv',
        privacy_accepted_at: '2026-10-16 16:00:00',
        discount_id_path: '',
        discount_id_back_path: '',
        passenger_data: {
            full_name: 'Otp Register Passenger',
            email,
            phone,
            discount: 'Regular',
            date_of_birth: '2000-01-01',
            address: 'Somewhere',
            student_id: '',
            id_type: '',
            verification_hint: 'pending'
        }
    });

    const regCookie = await sessionPut('PHPSESSID=otpD', registrationSeed('otpregister@example.com', '09171112222'));
    const regCsrf = await csrfOf(regCookie);
    const regRes = await postForm('/passenger/passengers/verify-otp', 'otp=654321', regCookie, regCsrf);
    const regBody = await regRes.text();
    eq(regRes.status, 200, 'The registration OTP verification responds');
    let regJson = null;
    try { regJson = JSON.parse(regBody); } catch {}
    ok(regJson && regJson.success === true, 'The registration completes with a success response: ' + regBody.slice(0, 160));
    ok(regJson && String(regJson.passenger_code || '').length > 0, 'The new account receives a passenger code');
    ok(!isErrorPage(regBody), 'The registration completion never shows the generic error page');

    const listAfterReg = await (await request('/_test/passenger-list')).json();
    ok(listAfterReg.rows.some((row) => row.email === 'otpregister@example.com' && String(row.passenger_code || '') !== ''),
        'The registered passenger row exists with its code');

    // --- 5. Registration failure stays a clean JSON error -----------------------
    await postJson('/_test/identity-claims-break', {}, '');
    const regCookie2 = await sessionPut('PHPSESSID=otpE', registrationSeed('otpsecond@example.com', '09173334444'));
    const regCsrf2 = await csrfOf(regCookie2);
    const regRes2 = await postForm('/passenger/passengers/verify-otp', 'otp=654321', regCookie2, regCsrf2);
    const regBody2 = await regRes2.text();
    eq(regRes2.status, 200, 'The broken-claims registration still responds with JSON');
    let regJson2 = null;
    try { regJson2 = JSON.parse(regBody2); } catch {}
    ok(regJson2 && regJson2.success === false, 'The broken-claims registration reports a clean failure');
    ok(!isErrorPage(regBody2), 'The broken-claims registration never shows the generic error page');

    const listAfterFail = await (await request('/_test/passenger-list')).json();
    ok(!listAfterFail.rows.some((row) => row.email === 'otpsecond@example.com'), 'The failed registration leaves no passenger row behind');

    // --- 6. Login lookup failure degrades to a friendly redirect ----------------
    await postJson('/_test/passengers-break', {}, '');
    const lookupCookie = await sessionPut('PHPSESSID=otpF', {});
    const lookupCsrf = await csrfOf(lookupCookie);
    const lookupRes = await postForm('/passenger/login', 'login=otplogin%40example.com&password=Secret123', lookupCookie, lookupCsrf);
    const lookupHtml = await lookupRes.text();
    eq(lookupRes.status, 302, 'A login lookup failure redirects');
    ok(String(lookupRes.headers.get('location') || '').includes('/passenger/login'), 'The lookup failure stays on the login surface');
    ok(!isErrorPage(lookupHtml), 'A login lookup failure never shows the generic error page');
    ok(serverOutput.includes('Passenger login lookup failed'), 'The lookup failure is logged');

    // --- 7. The OTP modal loader rejects non-OK responses -----------------------
    const scriptsSource = fs.readFileSync(path.join(__dirname, '../views/Create_Account/assets/scripts.php'), 'utf8');
    const loader = scriptsSource.slice(scriptsSource.indexOf('async function loadOTPModal'), scriptsSource.indexOf('function styleOtpInput'));
    ok(loader.includes('if(!response.ok)'), 'The OTP modal loader checks the response status');
    ok(loader.includes('id="otpVerifyForm"'), 'The OTP modal loader rejects responses without the OTP form');
}

run()
    .then(() => {
        console.log(`${checks} OTP flow checks passed.`);
        if (php) { php.kill(); }
    })
    .catch((error) => {
        console.error(serverOutput.slice(-2500));
        if (php) { php.kill(); }
        console.error(error);
        process.exit(1);
    });
