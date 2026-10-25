/* One-Time trip release: a completed/cancelled/archived/ended trip no longer
   forces the receipt, a new scan starts the new-trip flow, uncertain statuses
   keep the trip open without creating rows, history is preserved, and a paid
   trip cannot be charged twice. */
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const temporaryRoot = process.env.ONE_TIME_TEST_TEMP || 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(temporaryRoot).isDirectory(), 'Approved test temporary directory exists');
const temporary = fs.mkdtempSync(path.join(temporaryRoot, 'trip-release-'));
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
        env: { ...process.env, ONE_TIME_TEST_DB: path.join(temporary, 'release.sqlite') },
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

    const postJson = (url, body, headers = {}) => request(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...headers },
        body: JSON.stringify(body)
    });

    const csrfFrom = (html) => {
        const match = html.match(/<meta name="csrf-token" content="([^"]+)"/);
        return match ? match[1] : '';
    };

    const trips = async () => (await (await request('/_test/trip-list')).json()).rows;

    // --- Setup: passenger + a live one-time row on bus trip 77 --------------
    await postJson('/_test/seed', { case: 'history', lat: 15.548218, lng: 120.933455, age: 10 });
    await postJson('/_test/passenger-row', { id: 1, full_name: 'Release Rider', status: 'Active' });
    const insert = await (await postJson('/_test/trip-insert', {
        passenger_id: 1, bus_no: 'Bus-12', trip_no: 77, driver_id: 'DRV-1', passenger_type: 'Regular',
        boarding_point: 'Stop A', destination: 'Stop C', fare: 30.0, payment_method: 'cash',
        payment_status: 'pending', trip_status: 'In Progress', identification_source: 'one_time',
        archived_status: 'no', submission_token: 'tok-release-1'
    })).json();
    const liveTripId = insert.id;
    ok(liveTripId > 0, 'A live one-time trip row is seeded');

    const sessionSet = await postJson('/_test/session-set', { passenger_id: 1, is_one_time: true, qr_bus: 'Bus-12', qr_trip: '77' });
    const cookie = cookieOf(sessionSet);

    const scan = () => request('/passenger/scan.php?bus=Bus-12', { headers: { Cookie: cookie } });

    // --- 1. Live row: scan reopens the receipt ------------------------------
    let response = await scan();
    eq(response.status, 302, 'The live scan redirects');
    ok(locationOf(response).includes('/passenger/one-time/register'), 'A live trip reopens the receipt');

    // --- 2. Completed row releases the same bus trip ------------------------
    await postJson('/_test/trip-update', { id: liveTripId, trip_status: 'Completed' });
    response = await scan();
    ok(locationOf(response).includes('/passenger/one-time/landing'), 'A completed trip starts the new-trip flow');

    // --- 3. Cancelled releases ----------------------------------------------
    await postJson('/_test/trip-update', { id: liveTripId, trip_status: 'Cancelled' });
    response = await scan();
    ok(locationOf(response).includes('/passenger/one-time/landing'), 'A cancelled trip starts the new-trip flow');

    // --- 4. Archived releases -----------------------------------------------
    await postJson('/_test/trip-update', { id: liveTripId, trip_status: 'In Progress', archived_status: 'yes' });
    response = await scan();
    ok(locationOf(response).includes('/passenger/one-time/landing'), 'An archived row starts the new-trip flow');
    await postJson('/_test/trip-update', { id: liveTripId, archived_status: 'no' });

    // --- 5. Terminal dropoff releases ---------------------------------------
    await postJson('/_test/trip-update', { id: liveTripId, dropoff_time: '2026-10-16 10:00:00' });
    response = await scan();
    ok(locationOf(response).includes('/passenger/one-time/landing'), 'A terminal dropoff starts the new-trip flow');
    await postJson('/_test/trip-update', { id: liveTripId, dropoff_time: null });

    // --- 6. Ended bus trip releases the row left behind ---------------------
    await postJson('/_test/bus-trip-update', { id: 77, status: 'Completed' });
    response = await scan();
    ok(locationOf(response).includes('/passenger/one-time/landing'), 'An ended bus trip starts the new-trip flow');
    await postJson('/_test/bus-trip-update', { id: 77, status: 'Departed' });

    // --- 7. Boot constants follow the live filter ---------------------------
    let register = await request('/passenger/one-time/register', { headers: { Cookie: cookie } });
    let registerHtml = await register.text();
    ok(registerHtml.includes(`const OT_RESUME_TRIP_ID = ${liveTripId};`), 'A live row resumes the receipt at boot');

    await postJson('/_test/trip-update', { id: liveTripId, trip_status: 'Completed' });
    register = await request('/passenger/one-time/register', { headers: { Cookie: cookie } });
    registerHtml = await register.text();
    ok(registerHtml.includes('const OT_RESUME_TRIP_ID = 0;'), 'A completed trip does not resume at boot');
    ok(registerHtml.includes("localStorage.removeItem(otStorageKey('oneTimeTrip'"), 'A new-trip boot removes the stale local cache');
    ok(!registerHtml.includes('storedCheck'), 'The boot no longer resumes from the local cache');
    eq(registerHtml.includes('id="step-boarding"') || registerHtml.includes('id="step-agreement"'), true, 'The new-trip form is served');

    // --- 8. Uncertain status (no resolvable bus trip): stays open, no rows --
    await postJson('/_test/trip-update', { id: liveTripId, trip_status: 'In Progress', trip_no: 999 });
    await postJson('/_test/bus-trip-update', { id: 77, status: 'Completed' });
    const beforeUncertain = (await trips()).length;
    response = await scan();
    ok(locationOf(response).includes('/passenger/one-time/register'), 'An unresolved bus trip keeps the receipt/recovery path');
    eq((await trips()).length, beforeUncertain, 'An uncertain status creates no new trip row');
    await postJson('/_test/bus-trip-update', { id: 77, status: 'Departed' });
    await postJson('/_test/trip-update', { id: liveTripId, trip_no: 77, trip_status: 'Completed' });

    // --- 9. Lookup failure keeps the trip open and creates nothing ----------
    const beforeFailure = (await trips()).length;
    await postJson('/_test/trip-table-swap', { mode: 'fail' });
    response = await scan();
    ok(locationOf(response).includes('/passenger/one-time/register'), 'A failing status lookup keeps the recovery path');
    await postJson('/_test/trip-table-swap', { mode: 'restore' });
    eq((await trips()).length, beforeFailure, 'A failing lookup creates no new trip row');

    // --- 10. History preserved; a new bus booking adds a second row ---------
    // Book on bus 13 (trip 78) as a fresh visitor through the real flow.
    await postJson('/_test/seed', { case: 'simulation', bus_id: 'Bus-13', driver_id: 'DRV-2', trip_id: 78, lat: 15.688171, lng: 121.078333 });
    const freshScan = await request('/passenger/scan.php?bus=Bus-13');
    const freshCookie = cookieOf(freshScan);
    const landing = await request('/passenger/one-time/landing', { headers: { Cookie: freshCookie } });
    const landingHtml = await landing.text();
    const csrf = csrfFrom(landingHtml);
    const tripInfo = await (await postJson('/passenger/one-time/trip', { bus: 'Bus-13' }, { Cookie: freshCookie, 'X-CSRF-Token': csrf })).json();
    ok(tripInfo.success && tripInfo.data.boarding, 'The second bus reports a boarding point');
    const booking = await (await postJson('/passenger/one-time/register', {
        bus_no: 'Bus-13',
        trip_no: '78',
        full_name: 'Second Rider',
        passenger_type: 'regular',
        payment_method: 'gcash',
        submission_token: 'tok-release-2',
        boarding_id: tripInfo.data.boarding.id,
        destination_id: tripInfo.data.destinations[tripInfo.data.destinations.length - 1].id,
        latitude: 15.68,
        longitude: 121.07
    }, { Cookie: freshCookie, 'X-CSRF-Token': csrf })).json();
    ok(booking.success, 'A new booking on another bus succeeds after the first trip completed');

    const rowsAfter = await trips();
    const firstRow = rowsAfter.find((row) => row.id === liveTripId);
    ok(firstRow, 'The first trip row still exists');
    eq(firstRow.trip_status, 'Completed', 'The first trip keeps its terminal status');
    eq(Number(firstRow.fare), 30, 'The first trip keeps its fare');
    ok(rowsAfter.length >= beforeFailure + 1, 'Both trips appear together in history');

    // --- 11. Checkout replay on a paid trip creates no second payment -------
    const secondRow = rowsAfter.find((row) => row.submission_token === 'tok-release-2');
    ok(secondRow, 'The new booking row is present');
    await postJson('/_test/trip-update', { id: secondRow.id, payment_status: 'paid' });

    const ownerSet = await postJson('/_test/session-set', { passenger_id: secondRow.passenger_id, is_one_time: true });
    const ownerCookie = cookieOf(ownerSet);
    const ownerPage = await request('/passenger/one-time/register', { headers: { Cookie: ownerCookie } });
    const ownerCsrf = csrfFrom(await ownerPage.text()) || csrf;
    const checkout = await postJson('/passenger/paymongo/checkout', { trip_id: secondRow.id }, { Cookie: ownerCookie, 'X-CSRF-Token': ownerCsrf });
    const checkoutBody = await checkout.json();
    ok(checkoutBody.success === false, 'Checkout on a paid trip is refused');

    const afterCheckout = (await trips()).find((row) => row.id === secondRow.id);
    eq(afterCheckout.payment_status, 'paid', 'The paid trip keeps its payment status');
    eq((await trips()).filter((row) => row.submission_token === 'tok-release-2').length, 1, 'No duplicate trip row is created by the payment replay');

    console.log(`One-Time trip release checks passed (${checks} assertions).`);
}

run().catch((error) => {
    console.error(error);
    console.error(serverOutput.slice(-1600));
    process.exit(1);
}).finally(() => {
    if (php) { php.kill(); }
});
