/* Landing-page flow through the real front controller: option selection,
   authentication guards, and the availability/status handling. */
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const temporaryRoot = process.env.ONE_TIME_TEST_TEMP || 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(temporaryRoot).isDirectory(), 'Approved test temporary directory exists');
const temporary = fs.mkdtempSync(path.join(temporaryRoot, 'landing-flow-'));
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
        env: { ...process.env, ONE_TIME_TEST_DB: path.join(temporary, 'landing.sqlite') },
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

    const locationOf = (response) => String(response.headers.get('location') || '');
    const seed = (payload) => request('/_test/seed', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const sessionSet = (payload) => request('/_test/session-set', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const csrfFrom = (html) => {
        const match = html.match(/<meta name="csrf-token" content="([^"]+)"/);
        return match ? match[1] : '';
    };
    const postJson = (url, body, cookie, csrf) => request(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            ...(cookie ? { Cookie: cookie } : {}),
            ...(csrf ? { 'X-CSRF-Token': csrf } : {})
        },
        body: JSON.stringify(body)
    });

    // --- 1. Fresh visitor: scan opens the landing, both options work ---------
    await seed({ case: 'history', lat: 15.548218, lng: 120.933455, age: 10 });
    const scan = await request('/passenger/scan.php?bus=Bus-12');
    eq(scan.status, 302, 'A valid scan redirects');
    ok(locationOf(scan).includes('/passenger/one-time/landing'), 'The scan opens the landing page');
    const cookie = cookieOf(scan);
    ok(cookie, 'The scan starts a session');

    const landing = await request('/passenger/one-time/landing', { headers: { Cookie: cookie } });
    eq(landing.status, 200, 'The landing page renders for the scanned visitor');
    const landingHtml = await landing.text();
    ok(landingHtml.includes('href="/passenger/one-time/register"'), 'The One-Time option is selectable');
    ok(landingHtml.includes('href="/passenger/login"'), 'The Regular option is selectable');
    ok(landingHtml.includes('One-Time User'), 'The One-Time option is shown');
    ok(landingHtml.includes('Regular User'), 'The Regular option is shown');

    const csrf = csrfFrom(landingHtml);
    ok(csrf.length > 10, 'The landing page exposes a CSRF token for its trip request');

    const tripResponse = await postJson('/passenger/one-time/trip', { bus: 'Bus-12' }, cookie, csrf);
    eq(tripResponse.status, 200, 'The landing trip request is accepted');
    const trip = await tripResponse.json();
    ok(trip.success, 'The landing trip request succeeds');

    const registerPage = await request('/passenger/one-time/register', { headers: { Cookie: cookie } });
    eq(registerPage.status, 200, 'Selecting One-Time opens the registration form without login');
    const registerHtml = await registerPage.text();
    ok(registerHtml.includes('id="step-boarding"'), 'The registration form is served');

    const loginPage = await request('/passenger/login');
    eq(loginPage.status, 200, 'Selecting Regular opens the login page');
    const loginHtml = await loginPage.text();
    ok(!loginHtml.includes('showAuthAlert('), 'The Regular choice opens a clean login page without an error banner');

    // --- 2. One-time booking then returning to the landing --------------------
    const bookingBody = {
        bus_no: 'Bus-12',
        trip_no: '77',
        full_name: 'Juan Dela Cruz',
        passenger_type: 'regular',
        payment_method: 'cash',
        submission_token: 'tok-landing-1',
        boarding_id: trip.data.boarding.id,
        destination_id: trip.data.destinations[trip.data.destinations.length - 1].id,
        latitude: 15.5,
        longitude: 121.0
    };
    const booking = await (await postJson('/passenger/one-time/register', bookingBody, cookie, csrf)).json();
    ok(booking.success, 'The One-Time booking saves from the landing flow');
    const oneTimeCookie = cookieOf(await postJson('/passenger/one-time/register', bookingBody, cookie, csrf)) || cookie;

    const landingAgain = await request('/passenger/one-time/landing', { headers: { Cookie: oneTimeCookie } });
    eq(landingAgain.status, 200, 'A one-time passenger can reopen the landing without login');
    const registerAgain = await request('/passenger/one-time/register', { headers: { Cookie: oneTimeCookie } });
    eq(registerAgain.status, 200, 'A one-time passenger can reopen the receipt without login');

    // --- 3. Stale one-time session (passenger row gone) ------------------------
    await sessionSet({ passenger_id: 999999, is_one_time: true, qr_bus: 'Bus-12', qr_trip: '77' });
    const staleCookie = 'PHPSESSID=' + 'stale1';
    const staleSet = await request('/_test/session-set', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Cookie: 'PHPSESSID=stale1' },
        body: JSON.stringify({ passenger_id: 999999, is_one_time: true, qr_bus: 'Bus-12', qr_trip: '77' })
    });
    eq(staleSet.status, 200, 'A stale one-time session can be prepared');
    const staleCookieHeader = cookieOf(staleSet) || 'PHPSESSID=stale1';

    const staleLanding = await request('/passenger/one-time/landing', { headers: { Cookie: staleCookieHeader } });
    eq(staleLanding.status, 200, 'A stale one-time session still reaches the landing page');
    ok(!locationOf(staleLanding).includes('/passenger/login'), 'A stale one-time session is not sent to login');
    const staleLandingHtml = await staleLanding.text();
    ok(staleLandingHtml.includes('/passenger/one-time/register'), 'The stale one-time session still sees the options');

    const staleRegister = await request('/passenger/one-time/register', { headers: { Cookie: staleCookieHeader } });
    eq(staleRegister.status, 200, 'A stale one-time session reaches the registration form');

    const staleScan = await request('/passenger/scan.php?bus=Bus-12', { headers: { Cookie: staleCookieHeader } });
    eq(staleScan.status, 302, 'The stale one-time session can still scan');
    ok(locationOf(staleScan).includes('/passenger/one-time/landing'), 'The stale scan reaches the landing, not login');

    // --- 4. Stale regular session (passenger row gone) -------------------------
    const regularSet = await request('/_test/session-set', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Cookie: 'PHPSESSID=stale2' },
        body: JSON.stringify({ passenger_id: 999998, qr_bus: 'Bus-12', qr_trip: '77' })
    });
    const regularCookie = cookieOf(regularSet) || 'PHPSESSID=stale2';

    const regularScan = await request('/passenger/scan.php?bus=Bus-12', { headers: { Cookie: regularCookie } });
    eq(regularScan.status, 302, 'A stale regular session can still scan');
    const regularScanLocation = locationOf(regularScan);
    ok(!regularScanLocation.includes('/passenger/login'), 'A stale regular session is not bounced to login before choosing an option');
    ok(regularScanLocation.includes('/passenger/one-time/landing'), 'A stale regular session reaches the landing as a visitor');

    const regularLanding = await request('/passenger/one-time/landing', { headers: { Cookie: regularCookie } });
    eq(regularLanding.status, 200, 'The landing renders after the stale regular session is cleared');

    // A genuinely protected route with a dead identity still asks for login,
    // but with the session-expired reason, never the misleading "unavailable".
    const home = await request('/passenger/home', { headers: { Cookie: regularCookie } });
    eq(home.status, 302, 'A protected route with a dead session redirects');
    ok(locationOf(home).includes('/passenger/login'), 'The protected route asks for login');
    ok(!locationOf(home).includes('reason=unavailable'), 'A dead identity never reports the misleading unavailable status');

    // --- 5. Direct landing/register access with a dead identity (no scan) ------
    const deadSet = await request('/_test/session-set', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Cookie: 'PHPSESSID=dead1' },
        body: JSON.stringify({ passenger_id: 999997, qr_bus: 'Bus-12', qr_trip: '77' })
    });
    const deadCookie = cookieOf(deadSet) || 'PHPSESSID=dead1';

    const deadLanding = await request('/passenger/one-time/landing', { headers: { Cookie: deadCookie } });
    eq(deadLanding.status, 200, 'Direct landing access with a dead identity renders instead of redirecting');
    ok(!locationOf(deadLanding).includes('/passenger/login'), 'Direct landing access never bounces a dead identity to login');
    const deadLandingHtml = await deadLanding.text();
    ok(deadLandingHtml.includes('href="/passenger/one-time/register"'), 'The One-Time option is selectable after a dead identity is demoted');
    ok(deadLandingHtml.includes('href="/passenger/login"'), 'The Regular option is selectable after a dead identity is demoted');

    const deadRegister = await request('/passenger/one-time/register', { headers: { Cookie: deadCookie } });
    eq(deadRegister.status, 200, 'Direct registration access with a dead identity renders the form');

    const deadSession = await (await request('/_test/session', { headers: { Cookie: deadCookie } })).json();
    ok(!deadSession.passenger_id, 'The dead identity is cleared from the session');
    eq(deadSession.qr_bus, 'Bus-12', 'The scanned bus survives the dead-identity demotion');

    // --- 6. Revoked device session (identity exists, session revoked) ----------
    await request('/_test/passenger-row', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: 5, full_name: 'Revoked Passenger' })
    });
    const revokedSet = await request('/_test/session-set', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Cookie: 'PHPSESSID=revoked1' },
        body: JSON.stringify({ passenger_id: 5, qr_bus: 'Bus-12', qr_trip: '77' })
    });
    const revokedCookie = cookieOf(revokedSet) || 'PHPSESSID=revoked1';
    await request('/_test/session-row', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Cookie: revokedCookie },
        body: JSON.stringify({ passenger_id: 5, revoked: true })
    });

    const revokedScan = await request('/passenger/scan.php?bus=Bus-12', { headers: { Cookie: revokedCookie } });
    eq(revokedScan.status, 302, 'A revoked device session can still scan');
    ok(!locationOf(revokedScan).includes('/passenger/login'), 'A revoked device session is not bounced to login');
    ok(locationOf(revokedScan).includes('/passenger/one-time/landing'), 'A revoked device session reaches the landing');

    const revokedLanding = await request('/passenger/one-time/landing', { headers: { Cookie: revokedCookie } });
    eq(revokedLanding.status, 200, 'The landing reopens for a revoked device session without login');
    ok(!locationOf(revokedLanding).includes('reason='), 'The revoked session never triggers a login reason');

    // --- 7. Existing account whose session-state read fails --------------------
    await request('/_test/passenger-row', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: 6, full_name: 'Unverifiable Passenger' })
    });
    const unverifiableSet = await request('/_test/session-set', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Cookie: 'PHPSESSID=unver1' },
        body: JSON.stringify({ passenger_id: 6, qr_bus: 'Bus-12', qr_trip: '77' })
    });
    const unverifiableCookie = cookieOf(unverifiableSet) || 'PHPSESSID=unver1';
    await request('/_test/state-fail', { method: 'POST', headers: { Cookie: unverifiableCookie } });

    const unverifiableScan = await request('/passenger/scan.php?bus=Bus-12', { headers: { Cookie: unverifiableCookie } });
    eq(unverifiableScan.status, 302, 'An unverifiable session can still scan');
    const unverifiableScanLocation = locationOf(unverifiableScan);
    ok(!unverifiableScanLocation.includes('reason=unavailable'), 'An unverifiable scan never reports the unavailable reason');
    ok(!unverifiableScanLocation.includes('/passenger/login'), 'An unverifiable scan is not bounced to login');
    ok(unverifiableScanLocation.includes('/passenger/one-time/landing'), 'An unverifiable scan reaches the landing as a visitor');

    const unverifiableLanding = await request('/passenger/one-time/landing', { headers: { Cookie: unverifiableCookie } });
    eq(unverifiableLanding.status, 200, 'The landing renders while the session state cannot be verified');
    ok(!locationOf(unverifiableLanding).includes('reason=unavailable'), 'The landing never redirects to login?reason=unavailable');

    const unverifiableSession = await (await request('/_test/session', { headers: { Cookie: unverifiableCookie } })).json();
    eq(unverifiableSession.passenger_id, 6, 'An unverifiable identity is not cleared');

    // A protected route with an unverifiable state still asks for login with
    // the genuine unavailable reason (protected-route behavior is preserved).
    const unverifiableHome = await request('/passenger/home', { headers: { Cookie: unverifiableCookie } });
    eq(unverifiableHome.status, 302, 'A protected route with an unverifiable session redirects');
    ok(locationOf(unverifiableHome).includes('reason=unavailable'), 'A protected route keeps the genuine unavailable reason');

    await request('/_test/state-restore', { method: 'POST', headers: { Cookie: unverifiableCookie } });

    // --- 8. Suspended account keeps the restriction fence ----------------------
    await request('/_test/passenger-row', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: 8,
            full_name: 'Suspended Passenger',
            status: 'Suspended',
            account_status: 'SUSPENDED',
            suspension_starts_at: '2026-01-01 00:00:00',
            suspension_ends_at: '2030-01-01 00:00:00',
            status_reason: 'Fare discrepancy'
        })
    });
    const suspendedSet = await request('/_test/session-set', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Cookie: 'PHPSESSID=suspended1' },
        body: JSON.stringify({ passenger_id: 8, qr_bus: 'Bus-12', qr_trip: '77' })
    });
    const suspendedCookie = cookieOf(suspendedSet) || 'PHPSESSID=suspended1';

    const suspendedScan = await request('/passenger/scan.php?bus=Bus-12', { headers: { Cookie: suspendedCookie } });
    eq(suspendedScan.status, 302, 'A suspended account scan redirects');
    ok(locationOf(suspendedScan).includes('/passenger/account/restriction'), 'A suspended account is fenced to the restriction notice from a scan');
    ok(!locationOf(suspendedScan).includes('/passenger/one-time/landing'), 'A suspended account never reaches the landing');

    const suspendedLanding = await request('/passenger/one-time/landing', { headers: { Cookie: suspendedCookie } });
    eq(suspendedLanding.status, 302, 'A suspended account landing access redirects');
    ok(locationOf(suspendedLanding).includes('/passenger/account/restriction'), 'A suspended account is fenced from the landing too');
    ok(!locationOf(suspendedLanding).includes('/passenger/login'), 'A suspended account is not sent to plain login');

    // --- 9. Usable regular session keeps the homepage shortcut -----------------
    await request('/_test/passenger-row', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: 9, full_name: 'Active Passenger' })
    });
    const activeSet = await request('/_test/session-set', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Cookie: 'PHPSESSID=active1' },
        body: JSON.stringify({ passenger_id: 9, qr_bus: 'Bus-12', qr_trip: '77' })
    });
    const activeCookie = cookieOf(activeSet) || 'PHPSESSID=active1';
    await request('/_test/session-row', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Cookie: activeCookie },
        body: JSON.stringify({ passenger_id: 9, revoked: false })
    });

    const activeScan = await request('/passenger/scan.php?bus=Bus-12', { headers: { Cookie: activeCookie } });
    eq(activeScan.status, 302, 'A usable regular session still scans');
    ok(locationOf(activeScan).includes('/passenger/home'), 'A usable regular session keeps the homepage shortcut after scanning');

    const activeLanding = await request('/passenger/one-time/landing', { headers: { Cookie: activeCookie } });
    eq(activeLanding.status, 302, 'A usable regular session landing access still redirects');
    ok(locationOf(activeLanding).includes('/passenger/home'), 'A usable regular session keeps the homepage shortcut on the landing path');
}

run()
    .then(() => {
        console.log(`${checks} landing flow checks passed.`);
        if (php) { php.kill(); }
    })
    .catch((error) => {
        console.error(serverOutput.slice(-2500));
        if (php) { php.kill(); }
        console.error(error);
        process.exit(1);
    });
