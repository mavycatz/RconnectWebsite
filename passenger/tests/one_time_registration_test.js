/* Registration -> confirm-time revalidation, duplicate replay, snapshot
   persistence, companion parity, and fare quoting. Isolated SQLite. */
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const temporaryRoot = process.env.ONE_TIME_TEST_TEMP || 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(temporaryRoot).isDirectory(), 'Approved test temporary directory exists');
const temporary = fs.mkdtempSync(path.join(temporaryRoot, 'one-time-reg-'));
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

    php = spawn('php', ['-S', `127.0.0.1:${port}`, path.join(__dirname, 'fixtures/one_time_http.php')], {
        cwd: root,
        env: { ...process.env, ONE_TIME_TEST_DB: path.join(temporary, 'one_time.sqlite') },
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

    const seed = async (payload) => {
        const response = await request('/_test/seed', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        assert.ok(response.ok, 'Seed accepted: ' + JSON.stringify(payload));
    };

    const cookieOf = (response) => {
        const raw = typeof response.headers.getSetCookie === 'function'
            ? response.headers.getSetCookie()
            : [response.headers.get('set-cookie') || ''];
        return raw.map((value) => String(value).split(';')[0]).filter(Boolean).join('; ');
    };

    const scanSession = async (bus) => {
        const response = await request('/passenger/scan.php?bus=' + encodeURIComponent(bus));
        const cookie = cookieOf(response);
        assert.ok(cookie, 'Scan established a session for ' + bus);
        return cookie;
    };

    const postJson = async (url, body, cookie) => {
        const response = await request(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...(cookie ? { Cookie: cookie } : {}) },
            body: JSON.stringify(body)
        });
        return response.json();
    };

    const tripRow = async (id) => (await (await request('/_test/trip-row?id=' + id)).json());
    const tripByToken = async (token) => (await (await request('/_test/trip-by-token?token=' + encodeURIComponent(token))).json());

    // --- 0. A validated scan still gates boarding and registration -----------
    const noScanTrip = await (await request('/passenger/one-time/trip', { method: 'POST' })).json();
    eq(noScanTrip.success, false, 'Trip info without a scan is refused');
    eq(noScanTrip.code, 'SCAN_REQUIRED', 'Trip info without a scan asks for the QR');

    const noScan = await postJson('/passenger/one-time/register', {
        bus_no: 'Bus-12',
        trip_no: '77',
        full_name: 'Juan Dela Cruz',
        passenger_type: 'regular',
        payment_method: 'cash',
        submission_token: 'tok-noscan-1',
        boarding_id: 105,
        destination_id: 119
    }, null);
    eq(noScan.success, false, 'A registration without a scan is refused');
    eq(noScan.code, 'SCAN_REQUIRED', 'The registration refusal asks for the QR');

    // --- 1. Valid booking: bus-derived boarding, fare, snapshot persisted ----
    await seed({ case: 'history', lat: 15.548218, lng: 120.933455, age: 10 });
    let cookie = await scanSession('Bus-12');

    let result = await postJson('/passenger/one-time/register', {
        bus_no: 'Bus-12',
        trip_no: '77',
        full_name: 'Juan Dela Cruz',
        passenger_type: 'regular',
        payment_method: 'cash',
        submission_token: 'tok-valid-1',
        boarding_id: 105,
        destination_id: 119,
        latitude: 15.5,
        longitude: 121.0
    }, cookie);

    ok(result.success, 'A valid booking saves: ' + JSON.stringify(result).slice(0, 300));
    eq(result.data.boarding_point, 'Pinagpanaan, Talavera', 'Boarding point is the bus-matched stop');
    eq(result.data.destination, 'Rizal/Pob Sur Terminal', 'Destination is the remaining-ahead stop');
    eq(Number(result.data.fare), 28, 'Fare comes from the stop-to-stop fare data');
    const firstTripId = Number(result.data.trip_id);
    ok(firstTripId > 0, 'The booking returns a trip id');

    const saved = await tripRow(firstTripId);
    eq(saved.row.bus_no, 'Bus-12', 'Saved trip keeps the scanned bus');
    eq(Number(saved.row.trip_no), 77, 'Saved trip keeps the active trip id');
    eq(Number(saved.row.boarding_stop_id), 1005, 'Saved trip keeps the matched route_stops id');
    eq(Number(saved.row.scan_latitude).toFixed(6), '15.548218', 'Scan latitude is persisted');
    eq(Number(saved.row.scan_longitude).toFixed(6), '120.933455', 'Scan longitude is persisted');
    eq(Number(saved.row.scan_stop_id), 1005, 'Scan matched stop id is persisted');
    eq(saved.row.scan_location_source, 'driver_location_history', 'Scan source is persisted');
    ok(saved.row.scan_recorded_at, 'Scan timestamp is persisted');
    eq(Number(saved.row.latitude), 15.5, 'The passenger phone GPS is still stored as supporting data');
    ok(saved.row.scan_latitude !== saved.row.latitude, 'The scan snapshot is not the passenger location');

    // --- 2. Passed stops are refused; the registered boarding still stands ---
    // The scan registers Homestead 1; the bus then moves on. A destination the
    // bus has passed must be refused, while an ahead destination still books
    // with the registered boarding point.
    await seed({ case: 'history', lat: 15.566468, lng: 120.953142, age: 10 });
    cookie = await scanSession('Bus-12');
    await seed({ case: 'history', lat: 15.594368, lng: 120.976804, age: 5 });

    result = await postJson('/passenger/one-time/register', {
        bus_no: 'Bus-12',
        trip_no: '77',
        full_name: 'Maria Santos',
        passenger_type: 'regular',
        payment_method: 'cash',
        submission_token: 'tok-moved-1',
        boarding_id: 107,
        destination_id: 108,
        latitude: 15.566,
        longitude: 120.953
    }, cookie);

    eq(result.success, false, 'A destination the bus has already passed is refused');
    eq(result.code, 'DESTINATION_PASSED', 'The refusal names the passed destination');
    eq((await tripByToken('tok-moved-1')).count, 0, 'No trip is saved for the passed destination');

    const aheadBooking = await postJson('/passenger/one-time/register', {
        bus_no: 'Bus-12',
        trip_no: '77',
        full_name: 'Maria Santos',
        passenger_type: 'regular',
        payment_method: 'cash',
        submission_token: 'tok-ahead-1',
        boarding_id: 107,
        destination_id: 119,
        latitude: 15.566,
        longitude: 120.953
    }, cookie);

    ok(aheadBooking.success, 'A destination ahead of the bus still books: ' + JSON.stringify(aheadBooking).slice(0, 300));
    eq(aheadBooking.data.boarding_point, 'Homestead 1, Talavera', 'The moved-bus booking keeps the registered boarding');
    eq(aheadBooking.data.destination, 'Rizal/Pob Sur Terminal', 'The ahead destination is saved');
    eq((await tripByToken('tok-ahead-1')).count, 1, 'The ahead booking is saved once');

    // A different boarding point is refused once one is registered.
    await seed({ case: 'history', lat: 15.548218, lng: 120.933455, age: 10 });
    cookie = await scanSession('Bus-12');
    const changedBoarding = await postJson('/passenger/one-time/register', {
        bus_no: 'Bus-12',
        trip_no: '77',
        full_name: 'Pedro Reyes',
        passenger_type: 'regular',
        payment_method: 'cash',
        submission_token: 'tok-changed-1',
        boarding_id: 107,
        destination_id: 119,
        latitude: 15.548,
        longitude: 120.933
    }, cookie);
    eq(changedBoarding.success, false, 'A boarding point different from the registered one is refused');
    eq(changedBoarding.code, 'INVALID_BOARDING', 'The refusal names the registered boarding point');

    // --- 3. Destination behind the bus is refused ----------------------------
    await seed({ case: 'history', lat: 15.566468, lng: 120.953142, age: 10 });
    cookie = await scanSession('Bus-12');

    result = await postJson('/passenger/one-time/register', {
        bus_no: 'Bus-12',
        trip_no: '77',
        full_name: 'Pedro Reyes',
        passenger_type: 'regular',
        payment_method: 'cash',
        submission_token: 'tok-behind-1',
        boarding_id: 107,
        destination_id: 100,
        latitude: 15.566,
        longitude: 120.953
    }, cookie);

    eq(result.success, false, 'A destination behind the boarding point is refused');
    eq(result.code, 'INVALID_DESTINATION', 'The refusal reports an invalid destination');
    eq((await tripByToken('tok-behind-1')).count, 0, 'No trip is saved for the behind-the-bus destination');

    // --- 4. Replayed confirmation after movement returns the original trip ---
    await seed({ case: 'history', lat: 15.594368, lng: 120.976804, age: 5 });
    const replay = await postJson('/passenger/one-time/register', {
        bus_no: 'Bus-12',
        trip_no: '77',
        full_name: 'Juan Dela Cruz',
        passenger_type: 'regular',
        payment_method: 'cash',
        submission_token: 'tok-valid-1',
        boarding_id: 105,
        destination_id: 119,
        latitude: 15.5,
        longitude: 121.0
    }, cookie);

    ok(replay.success, 'A replayed confirmation still succeeds');
    eq(Number(replay.data.trip_id), firstTripId, 'The replay returns the original trip');
    const tokenRow = await tripByToken('tok-valid-1');
    eq(tokenRow.count, 1, 'The replayed confirmation created no duplicate trip');
    eq(Number(tokenRow.row.id), firstTripId, 'The stored token belongs to the original trip');

    // --- 5. Fare quote prices the selected pair from fare_pricing ------------
    // The quote deliberately does not re-read the live bus position: the bus
    // moving while the passenger decides must never turn a valid selection
    // into "Fare unavailable". The booking below still revalidates movement.
    await seed({ case: 'history', lat: 15.548218, lng: 120.933455, age: 10 });
    cookie = await scanSession('Bus-12');

    const quote = await postJson('/passenger/one-time/fare', {
        bus_no: 'Bus-12',
        trip_no: '77',
        passenger_type: 'regular',
        boarding_id: 105,
        destination_id: 119
    }, cookie);
    ok(quote.success, 'A remaining destination quotes successfully');
    eq(Number(quote.data.fare), 28, 'The quote matches the booking fare');

    const staleQuote = await postJson('/passenger/one-time/fare', {
        bus_no: 'Bus-12',
        trip_no: '77',
        passenger_type: 'regular',
        boarding_id: 105,
        destination_id: 100
    }, cookie);
    eq(staleQuote.success, false, 'A destination behind the boarding point is refused');
    eq(staleQuote.code, 'INVALID_DESTINATION', 'The quote refusal uses the same reason code');

    const movedBoardingQuote = await postJson('/passenger/one-time/fare', {
        bus_no: 'Bus-12',
        trip_no: '77',
        passenger_type: 'regular',
        boarding_id: 110,
        destination_id: 119
    }, cookie);
    ok(movedBoardingQuote.success, 'A moved bus does not retract the quote for a valid pair');
    eq(Number(movedBoardingQuote.data.fare), 18, 'The moved-pair quote comes from the fare_pricing rows');
    eq(movedBoardingQuote.data.boarding_point, 'Gen. Luna, Llanera', 'The quote reports the selected boarding point');
    eq(movedBoardingQuote.data.destination, 'Rizal/Pob Sur Terminal', 'The quote reports the selected destination');

    const noPosition = await (async () => {
        await seed({ case: 'clear' });
        const freshCookie = await scanSession('Bus-12');
        return postJson('/passenger/one-time/fare', {
            bus_no: 'Bus-12',
            trip_no: '77',
            passenger_type: 'regular',
            boarding_id: 105,
            destination_id: 119
        }, freshCookie);
    })();
    ok(noPosition.success, 'A quote without a live bus position still prices the selected pair');
    eq(Number(noPosition.data.fare), 28, 'The no-position quote still comes from the fare_pricing rows');

    const sameStopTerminal = await postJson('/passenger/one-time/fare', {
        bus_no: 'Bus-12',
        trip_no: '77',
        passenger_type: 'regular',
        boarding_id: 119,
        destination_id: 119
    }, cookie);
    ok(sameStopTerminal.success, 'The last stop quotes the same-stop destination');
    eq(Number(sameStopTerminal.data.fare), 15, 'The same-stop quote is the minimum fare');
    eq(sameStopTerminal.data.boarding_point, 'Rizal/Pob Sur Terminal', 'The same-stop quote reports the boarding stop');
    eq(sameStopTerminal.data.destination, 'Rizal/Pob Sur Terminal', 'The same-stop destination is the boarding stop');

    const sameStopMidRoute = await postJson('/passenger/one-time/fare', {
        bus_no: 'Bus-12',
        trip_no: '77',
        passenger_type: 'regular',
        boarding_id: 105,
        destination_id: 105
    }, cookie);
    ok(sameStopMidRoute.success, 'The local option quotes at the boarding stop');
    eq(Number(sameStopMidRoute.data.fare), 15, 'The local option is priced at the minimum fare');
    eq(sameStopMidRoute.data.destination, 'Pinagpanaan, Talavera', 'The local option names the boarding stop');

    const localBooking = await postJson('/passenger/one-time/register', {
        bus_no: 'Bus-12',
        trip_no: '77',
        full_name: 'Local Rider',
        passenger_type: 'regular',
        payment_method: 'cash',
        submission_token: 'tok-local-1',
        boarding_id: 105,
        destination_id: 105,
        latitude: 15.548,
        longitude: 120.933
    }, cookie);
    ok(localBooking.success, 'The local option books: ' + JSON.stringify(localBooking).slice(0, 300));
    eq(Number(localBooking.data.fare), 15, 'The local booking is the minimum fare');
    eq(localBooking.data.boarding_point, 'Pinagpanaan, Talavera', 'The local booking keeps the boarding point');
    eq(localBooking.data.destination, 'Pinagpanaan, Talavera', 'The local booking uses the boarding stop as the destination');
    eq((await tripByToken('tok-local-1')).count, 1, 'The local booking is saved once');

    // --- 6. Companions inherit the primary bus/trip/boarding/destination -----
    await seed({ case: 'history', lat: 15.566468, lng: 120.953142, age: 10 });
    cookie = await scanSession('Bus-12');

    result = await postJson('/passenger/one-time/register', {
        bus_no: 'Bus-12',
        trip_no: '77',
        full_name: 'Ana Bautista',
        passenger_type: 'regular',
        payment_method: 'cash',
        submission_token: 'tok-companion-1',
        boarding_id: 107,
        destination_id: 110,
        latitude: 15.566,
        longitude: 120.953,
        companions: [{ full_name: 'Kid Rider', companion_type: 'child' }]
    }, cookie);

    ok(result.success, 'A booking with a companion saves: ' + JSON.stringify(result).slice(0, 300));
    const partyTripId = Number(result.data.trip_id);
    const party = await tripRow(partyTripId);
    eq(party.row.boarding_point, 'Homestead 1, Talavera', 'Primary boarding point is the bus-matched stop');
    eq(party.row.destination, 'Gen. Luna, Llanera', 'Primary destination is a remaining-ahead stop');
    eq(party.companions.length, 1, 'The companion row is stored');
    eq(Number(party.companions[0].companion_of), partyTripId, 'The companion links to the primary trip');
    eq(party.companions[0].boarding_point, party.row.boarding_point, 'The companion shares the primary boarding point');
    eq(party.companions[0].destination, party.row.destination, 'The companion shares the primary destination');
    eq(party.companions[0].trip_status, 'In Progress', 'The companion trip is in progress');
    eq(Number(result.data.party_fare), 30, 'Party fare sums the primary and companion fares');

    // Refreshing the register page must resume the receipt with the whole
    // party's fare, not only the primary row's own fare.
    const resumedReceipt = await request('/passenger/one-time/register', { headers: { Cookie: cookie } });
    const resumedHtml = await resumedReceipt.text();
    eq(resumedReceipt.status, 200, 'The register page renders on refresh');
    ok(Number((resumedHtml.match(/const OT_RESUME_TRIP_ID = (\d+);/) || [])[1]) === partyTripId, 'The refreshed page resumes the saved trip');
    eq(Number((resumedHtml.match(/const OT_RESUME_FARE = ([\d.]+);/) || [])[1]), 30, 'The refreshed receipt keeps the companion fare');

    // A companion submission cannot carry a behind-the-bus destination.
    await seed({ case: 'history', lat: 15.566468, lng: 120.953142, age: 10 });
    cookie = await scanSession('Bus-12');
    result = await postJson('/passenger/one-time/register', {
        bus_no: 'Bus-12',
        trip_no: '77',
        full_name: 'Liza Ramos',
        passenger_type: 'regular',
        payment_method: 'cash',
        submission_token: 'tok-companion-behind',
        boarding_id: 107,
        destination_id: 100,
        latitude: 15.566,
        longitude: 120.953,
        companions: [{ full_name: 'Hidden Rider', companion_type: 'child' }]
    }, cookie);
    eq(result.success, false, 'A party with a behind-the-bus destination is refused');
    eq(result.code, 'INVALID_DESTINATION', 'The party refusal reports the invalid destination');
    eq((await tripByToken('tok-companion-behind')).count, 0, 'No companion rows are created for the refused party');
}

run()
    .then(() => {
        console.log(`${checks} one-time registration checks passed.`);
        if (php) { php.kill(); }
    })
    .catch((error) => {
        console.error(serverOutput.slice(-2500));
        if (php) { php.kill(); }
        console.error(error);
        process.exit(1);
    });
