/* One-time contact numbers are not login identities and cannot be used to
   create a second account for the same person. */
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const temporaryRoot = process.env.ONE_TIME_TEST_TEMP || 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(temporaryRoot).isDirectory(), 'Approved test temporary directory exists');
const temporary = fs.mkdtempSync(path.join(temporaryRoot, 'ot-identity-'));
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
        env: { ...process.env, ONE_TIME_TEST_DB: path.join(temporary, 'identity.sqlite') },
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

    // --- Seed a one-time registration (number only in contact_number) -------
    await request('/_test/passenger-row', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: 1,
            full_name: 'One Time Rider',
            registration_type: 'one_time',
            contact_number: '09331234567',
            status: 'Active'
        })
    });

    // --- Login with the one-time number: generic invalid, no session --------
    const loginPage = await request('/passenger/login');
    const loginCookie = cookieOf(loginPage);
    const loginCsrf = csrfFrom(await loginPage.text());
    await postForm('/passenger/login', new URLSearchParams({ login: '09331234567', password: 'whatever' }).toString(), {
        Cookie: loginCookie, 'X-CSRF-Token': loginCsrf
    });
    const afterLogin = await request('/passenger/login', { headers: { Cookie: loginCookie } });
    const afterLoginHtml = await afterLogin.text();
    const alertMatch = afterLoginHtml.match(/showAuthAlert\('loginAlert',\s*['"](?:error|info|warning|locked|success)['"],\s*"((?:[^"\\]|\\.)*)",\s*"((?:[^"\\]|\\.)*)"\)/);
    const alertText = alertMatch ? decodeJsonString(alertMatch[1]) + ' ' + decodeJsonString(alertMatch[2]) : '';
    ok(/Invalid email\/phone or password\./.test(alertText), 'A one-time contact number cannot log in (generic response)');

    const loginSession = (await (await request('/_test/session', { headers: { Cookie: loginCookie } })).json()).session || {};
    ok(!loginSession.passenger_id, 'No session is created from a one-time contact number');

    // --- Registering with the same number adopts the one-time record --------
    const before = await (await request('/_test/passenger-list')).json();

    const registerPage = await request('/passenger/register');
    const registerCookie = cookieOf(registerPage);
    const registerCsrf = csrfFrom(await registerPage.text());
    const register = await postForm('/passenger/register', new URLSearchParams({
        privacy_accepted: '1',
        full_name: 'Adopted Rider',
        email: 'dup@example.com',
        phone: '09331234567',
        discount: 'Regular',
        date_of_birth: '1990-01-01',
        password: 'Passw0rd123',
        confirm_password: 'Passw0rd123'
    }).toString(), { Cookie: registerCookie, 'X-CSRF-Token': registerCsrf });

    const registerBody = await register.json();
    ok(
        !String(registerBody.message || '').includes('already exists'),
        'The store step no longer refuses a one-time contact number: ' + JSON.stringify(registerBody).slice(0, 160)
    );

    // Complete the registration (seeded OTP session, as the OTP flow suite
    // does) and confirm the live one-time record is adopted as the account.
    const adoptSeed = {
        otp: '654321',
        otp_expiry: Math.floor(Date.now() / 1000) + 300,
        otp_attempts: 0,
        passenger_password_hash: '$2y$10$abcdefghijklmnopqrstuv',
        privacy_accepted_at: '2026-10-16 16:00:00',
        discount_id_path: '',
        discount_id_back_path: '',
        passenger_data: {
            full_name: 'Adopted Rider',
            email: 'dup@example.com',
            phone: '09331234567',
            discount: 'Regular',
            date_of_birth: '1990-01-01',
            address: 'Somewhere',
            student_id: '',
            id_type: '',
            verification_hint: 'pending'
        }
    };

    const seeded = await request('/_test/session-put', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Cookie: 'PHPSESSID=adoptOne' },
        body: JSON.stringify(adoptSeed)
    });
    const adoptCookie = cookieOf(seeded) || 'PHPSESSID=adoptOne';
    const adoptCsrf = csrfFrom(await (await request('/passenger/login', { headers: { Cookie: adoptCookie } })).text());

    const adopt = await postForm('/passenger/passengers/verify-otp', 'otp=654321', {
        Cookie: adoptCookie, 'X-CSRF-Token': adoptCsrf
    });
    const adoptBody = await adopt.json();
    ok(adoptBody.success === true, 'Registering with a one-time number succeeds by adopting the record: ' + JSON.stringify(adoptBody).slice(0, 200));

    const after = await (await request('/_test/passenger-list')).json();
    eq(after.count, before.count, 'No second account is created for the one-time number');
    const adopted = after.rows.find((row) => row.id === 1);
    ok(adopted, 'The original one-time record is kept as the account row');
    eq(adopted.email, 'dup@example.com', 'The one-time record adopts the account email');
    eq(adopted.phone, '09331234567', 'The one-time record adopts the account phone');
    eq(adopted.registration_type, 'regular', 'The adopted record becomes a regular account');
    eq(adopted.contact_number, '09331234567', 'The original one-time contact number is kept');

    // --- A legacy one-time row (default registration_type) is adopted too ---
    // Older one-time rows predate registration_type, so they carry its
    // 'regular' default; having no email, phone or password still marks them
    // as one-time records that a registration adopts.
    await request('/_test/passenger-row', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: 2,
            full_name: 'Legacy One Time Rider',
            registration_type: 'regular',
            contact_number: '09441234567',
            status: 'Active'
        })
    });

    const beforeLegacy = await (await request('/_test/passenger-list')).json();

    const legacySeed = JSON.parse(JSON.stringify(adoptSeed));
    legacySeed.passenger_data.email = 'legacy@example.com';
    legacySeed.passenger_data.phone = '09441234567';
    legacySeed.passenger_data.full_name = 'Legacy Account';

    const seededLegacy = await request('/_test/session-put', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Cookie: 'PHPSESSID=adoptLegacy' },
        body: JSON.stringify(legacySeed)
    });
    const legacyCookie = cookieOf(seededLegacy) || 'PHPSESSID=adoptLegacy';
    const legacyCsrf = csrfFrom(await (await request('/passenger/login', { headers: { Cookie: legacyCookie } })).text());

    const legacyAdopt = await postForm('/passenger/passengers/verify-otp', 'otp=654321', {
        Cookie: legacyCookie, 'X-CSRF-Token': legacyCsrf
    });
    const legacyBody = await legacyAdopt.json();
    ok(legacyBody.success === true, 'A legacy one-time number is adopted: ' + JSON.stringify(legacyBody).slice(0, 200));

    const afterLegacy = await (await request('/_test/passenger-list')).json();
    eq(afterLegacy.count, beforeLegacy.count, 'No second account for the legacy one-time number');
    const legacyRow = afterLegacy.rows.find((row) => row.id === 2);
    ok(legacyRow, 'The legacy one-time record is kept as the account row');
    eq(legacyRow.email, 'legacy@example.com', 'The legacy record adopts the account email');
    eq(legacyRow.phone, '09441234567', 'The legacy record adopts the account phone');

    console.log(`One-time identity checks passed (${checks} assertions).`);
}

run().catch((error) => {
    console.error(error);
    console.error(serverOutput.slice(-1600));
    process.exit(1);
}).finally(() => {
    if (php) { php.kill(); }
});
