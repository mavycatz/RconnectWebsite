/* Profile edit flow through the real front controller: the update applies and
   is reported as successful, a duplicate identity is rejected with the
   friendly error, and the fixture's native-prepare guard pins the binding. */
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const temporaryRoot = process.env.ONE_TIME_TEST_TEMP || 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(temporaryRoot).isDirectory(), 'Approved test temporary directory exists');
const temporary = fs.mkdtempSync(path.join(temporaryRoot, 'profile-update-'));
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
        env: { ...process.env, ONE_TIME_TEST_DB: path.join(temporary, 'profile.sqlite') },
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
    const rowOf = async (id) => {
        const list = await (await request('/_test/passenger-list')).json();
        return list.rows.find((row) => row.id === id) || null;
    };

    await postJson('/_test/passenger-row', { id: 9, full_name: 'Profile User', email: 'profile@example.com', phone: '09170000001' });
    await postJson('/_test/passenger-row', { id: 10, full_name: 'Other User', email: 'other@example.com', phone: '09170000002' });

    const set = await postJson('/_test/session-set', { passenger_id: 9 }, 'PHPSESSID=profile1');
    const cookie = cookieOf(set) || 'PHPSESSID=profile1';
    await postJson('/_test/session-row', { passenger_id: 9, revoked: false }, cookie);
    const csrf = await csrfOf(cookie);

    // --- 1. A valid edit applies and reports success ---------------------------
    const update = await postForm('/passenger/profile/update',
        'full_name=Updated+Profile+User&email=updated%40example.com&phone=09170000009',
        cookie, csrf);
    eq(update.status, 302, 'The profile update responds with its redirect');
    ok(String(update.headers.get('location') || '').includes('/passenger/profile'), 'The update redirects back to the profile');

    const profileHtml = await (await request('/passenger/profile', { headers: { Cookie: cookie } })).text();
    ok(profileHtml.includes("showAlert('success', \"Profile Updated\""), 'The profile page reports the successful update');
    ok(!profileHtml.includes("showAlert('error', \"Update Failed\""), 'The profile page does not report a failure');
    ok(profileHtml.includes('/passenger/public/js/profile-preview.js'), 'The profile page loads the photo preview script');

    const modalRes = await request('/passenger/profile/edit-modal', { headers: { Cookie: cookie } });
    const modalHtml = await modalRes.text();
    eq(modalRes.status, 200, 'The edit modal renders');
    ok(modalHtml.includes('id="profilePreview"') && modalHtml.includes('id="profileInput"'), 'The modal exposes the preview element and file input');

    const updated = await rowOf(9);
    eq(updated.full_name, 'Updated Profile User', 'The stored name is updated');
    eq(updated.email, 'updated@example.com', 'The stored email is updated');
    eq(updated.phone, '09170000009', 'The stored phone is updated');

    // --- 2. A duplicate identity is rejected with the friendly error -----------
    const duplicate = await postForm('/passenger/profile/update',
        'full_name=Updated+Profile+User&email=other%40example.com&phone=09170000009',
        cookie, csrf);
    eq(duplicate.status, 302, 'The duplicate update still redirects');

    const duplicateHtml = await (await request('/passenger/profile', { headers: { Cookie: cookie } })).text();
    ok(duplicateHtml.includes("showAlert('error', \"Update Failed\""), 'The duplicate update reports the friendly failure');

    const unchanged = await rowOf(9);
    eq(unchanged.email, 'updated@example.com', 'The rejected update leaves the stored email unchanged');

    // --- 3. Validation failures show the specific reason ------------------------
    const invalidName = await postForm('/passenger/profile/update',
        'full_name=Juan&email=updated%40example.com&phone=09170000009',
        cookie, csrf);
    eq(invalidName.status, 302, 'The invalid-name update still redirects');

    const invalidNameHtml = await (await request('/passenger/profile', { headers: { Cookie: cookie } })).text();
    ok(invalidNameHtml.includes('Please enter your full name'), 'The invalid-name failure states the specific reason');
    ok(!invalidNameHtml.includes("showAlert('error', \"Update Failed\", \"Please try again\")"), 'The invalid-name failure is not a bare retry message');

    const invalidPhone = await postForm('/passenger/profile/update',
        'full_name=Updated+Profile+User&email=updated%40example.com&phone=12345',
        cookie, csrf);
    eq(invalidPhone.status, 302, 'The invalid-phone update still redirects');

    const invalidPhoneHtml = await (await request('/passenger/profile', { headers: { Cookie: cookie } })).text();
    ok(invalidPhoneHtml.includes('Please enter a valid Philippine mobile number'), 'The invalid-phone failure states the specific reason');

    const stillUnchanged = await rowOf(9);
    eq(stillUnchanged.full_name, 'Updated Profile User', 'Rejected edits leave the stored name unchanged');
    eq(stillUnchanged.phone, '09170000009', 'Rejected edits leave the stored phone unchanged');

    // --- 4. Identity claims follow profile email/phone changes ---------------
    // Registration claims both identities; a profile change must release the
    // values the account no longer holds (a stale claim would otherwise block
    // a future registration while no passenger row carries the value) and
    // claim the new ones.
    await postJson('/_test/passenger-row', { id: 30, full_name: 'Claims Owner', email: 'claims@example.com', phone: '09171000001', password: 'x' });
    await postJson('/_test/identity-claim-put', { identity_kind: 'email', identity_value: 'claims@example.com', passenger_id: 30 });
    await postJson('/_test/identity-claim-put', { identity_kind: 'phone', identity_value: '09171000001', passenger_id: 30 });

    const set30 = await postJson('/_test/session-set', { passenger_id: 30 }, 'PHPSESSID=claims30');
    const cookie30 = cookieOf(set30) || 'PHPSESSID=claims30';
    await postJson('/_test/session-row', { passenger_id: 30, revoked: false }, cookie30);
    const csrf30 = await csrfOf(cookie30);

    await postForm('/passenger/profile/update', 'full_name=Claims+Owner&email=moved%40example.com&phone=09171000002', cookie30, csrf30);

    const list30 = await rowOf(30);
    eq(list30.email, 'moved@example.com', 'The claims owner profile email is updated');

    const claimsAfter = (await (await request('/_test/claim-list')).json()).claims || [];
    ok(!claimsAfter.some((c) => c.identity_value === 'claims@example.com'), 'The old email claim is released by the profile change');
    ok(!claimsAfter.some((c) => c.identity_value === '09171000001'), 'The old phone claim is released by the profile change');
    ok(claimsAfter.some((c) => c.identity_value === 'moved@example.com' && Number(c.passenger_id) === 30), 'The new email claim belongs to the account');
    ok(claimsAfter.some((c) => c.identity_value === '09171000002' && Number(c.passenger_id) === 30), 'The new phone claim belongs to the account');

    const registerSeed = (email, phone, name) => ({
        otp: '654321',
        otp_expiry: Math.floor(Date.now() / 1000) + 300,
        otp_attempts: 0,
        passenger_password_hash: '$2y$10$abcdefghijklmnopqrstuv',
        privacy_accepted_at: '2026-10-17 01:00:00',
        discount_id_path: '',
        discount_id_back_path: '',
        passenger_data: {
            full_name: name, email, phone, discount: 'Regular',
            date_of_birth: '1990-01-01', address: 'Somewhere', student_id: '', id_type: '', verification_hint: 'pending'
        }
    });
    const registerWith = async (session, email, phone, name) => {
        const seeded = await postJson('/_test/session-put', registerSeed(email, phone, name), 'PHPSESSID=' + session);
        const cookie = cookieOf(seeded) || 'PHPSESSID=' + session;
        const csrf = await csrfOf(cookie);
        const res = await postForm('/passenger/passengers/verify-otp', 'otp=654321', cookie, csrf);
        return res.json();
    };

    // --- 5. A stale claim no longer blocks a registration ---------------------
    await postJson('/_test/identity-claim-put', { identity_kind: 'email', identity_value: 'stale@example.com', passenger_id: 30 });
    const stale = await registerWith('staleClaims', 'stale@example.com', '09171000003', 'Stale Claim Rider');
    ok(stale.success === true, 'A stale claim does not block registration: ' + JSON.stringify(stale).slice(0, 160));
    const claimsStale = (await (await request('/_test/claim-list')).json()).claims || [];
    ok(claimsStale.some((c) => c.identity_value === 'stale@example.com' && Number(c.passenger_id) === Number(stale.passenger_id)), 'The stale claim is reassigned to the new account');

    // --- 6. A live account that still holds the identity still blocks ---------
    const blocked = await registerWith('blockedClaims', 'moved@example.com', '09171000004', 'Blocked Rider');
    ok(blocked.success === false, 'A live account holding the identity still blocks the registration');
    ok(String(blocked.message || '').includes('already exists'), 'The genuine block reports the existing account');
}

run()
    .then(() => {
        console.log(`${checks} profile update checks passed.`);
        if (php) { php.kill(); }
    })
    .catch((error) => {
        console.error(serverOutput.slice(-2500));
        if (php) { php.kill(); }
        console.error(error);
        process.exit(1);
    });
