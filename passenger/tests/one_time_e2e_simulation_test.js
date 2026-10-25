/* End-to-end simulated-bus flow at the five required positions in both
   directions, plus the stale/unavailable gates. Isolated SQLite. */
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const temporaryRoot = process.env.ONE_TIME_TEST_TEMP || 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(temporaryRoot).isDirectory(), 'Approved test temporary directory exists');
const temporary = fs.mkdtempSync(path.join(temporaryRoot, 'one-time-e2e-'));
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

    const tripInfo = async (cookie) => {
        const response = await request('/passenger/one-time/trip', { headers: { Cookie: cookie } });
        const body = await response.json();
        assert.equal(body.success, true, 'Trip info succeeds');
        return body.data;
    };

    const tripRow = async (id) => (await (await request('/_test/trip-row?id=' + id)).json());

    // --- 7.1 End-to-end at the five positions, simulated bus = live GPS ------
    const outbound = [
        { label: 'Cabanatuan terminal', lat: 15.472229, lng: 120.969183, boarding: 'Cabanatuan Central Terminal → Daang Sarile', fareToTerminal: 58 },
        { label: 'Pinagpanaan', lat: 15.548218, lng: 120.933455, boarding: 'Pinagpanaan, Talavera', fareToTerminal: 28 },
        { label: 'Homestead 1', lat: 15.566468, lng: 120.953142, boarding: 'Homestead 1, Talavera', fareToTerminal: 24 },
        { label: 'Del Pilar', lat: 15.689167, lng: 121.078555, boarding: 'Del Pilar, Rizal', fareToTerminal: 15 }
    ];

    let token = 0;
    for (const position of outbound) {
        await seed({ case: 'simulation', bus_id: 'Bus-12', driver_id: 'DRV-1', trip_id: 77, lat: position.lat, lng: position.lng });
        const cookie = await scanSession('Bus-12');
        const info = await tripInfo(cookie);

        eq(info.bus_position.source, 'simulation', `${position.label}: position source is the simulation`);
        eq(info.boarding.destination, position.boarding, `${position.label}: boarding point`);
        ok(info.destinations.length > 0, `${position.label}: destinations remain ahead`);

        const terminal = info.destinations[info.destinations.length - 1];
        eq(terminal.destination, 'Rizal/Pob Sur Terminal', `${position.label}: the terminal remains a destination`);

        const submission = 'tok-e2e-' + (++token);
        const result = await postJson('/passenger/one-time/register', {
            bus_no: 'Bus-12',
            trip_no: '77',
            full_name: 'Juan Dela Cruz',
            passenger_type: 'regular',
            payment_method: 'cash',
            submission_token: submission,
            boarding_id: info.boarding.id,
            destination_id: terminal.id,
            latitude: 15.5,
            longitude: 121.0
        }, cookie);

        ok(result.success, `${position.label}: booking saves (${result.code || 'ok'})`);
        eq(result.data.boarding_point, position.boarding, `${position.label}: saved boarding point`);
        eq(result.data.destination, 'Rizal/Pob Sur Terminal', `${position.label}: saved destination`);
        eq(Number(result.data.fare), position.fareToTerminal, `${position.label}: saved fare`);

        const saved = await tripRow(Number(result.data.trip_id));
        eq(saved.row.boarding_point, position.boarding, `${position.label}: stored boarding point`);
        eq(saved.row.scan_location_source, 'simulation', `${position.label}: stored scan source is the simulation`);
        ok(saved.row.scan_latitude !== null && saved.row.scan_longitude !== null, `${position.label}: stored scan coordinates`);
    }

    // Rizal terminal: the boarding stop is offered as the only destination
    // (same-stop, minimum fare); the booking saves instead of the form
    // dead-ending on an empty destination list.
    await seed({ case: 'simulation', bus_id: 'Bus-12', driver_id: 'DRV-1', trip_id: 77, lat: 15.705381, lng: 121.099504 });
    let cookie = await scanSession('Bus-12');
    let info = await tripInfo(cookie);
    eq(info.boarding.destination, 'Rizal/Pob Sur Terminal', 'Rizal terminal: boarding point');
    eq(info.destinations.length, 1, 'Rizal terminal: the same-stop destination remains');
    eq(info.destinations[0].same_as_boarding, true, 'Rizal terminal: the same-stop fallback is flagged');
    eq(Number(info.destinations[0].id), Number(info.boarding.id), 'Rizal terminal: the destination is the boarding stop');
    let result = await postJson('/passenger/one-time/register', {
        bus_no: 'Bus-12',
        trip_no: '77',
        full_name: 'Juan Dela Cruz',
        passenger_type: 'regular',
        payment_method: 'cash',
        submission_token: 'tok-e2e-terminal',
        boarding_id: info.boarding.id,
        destination_id: info.destinations[0].id,
        latitude: 15.5,
        longitude: 121.0
    }, cookie);
    ok(result.success, 'Rizal terminal: the same-stop booking saves (' + (result.code || 'ok') + ')');
    eq(Number(result.data.fare), 15, 'Rizal terminal: the same-stop fare is the minimum fare');
    eq(result.data.destination, 'Rizal/Pob Sur Terminal', 'Rizal terminal: the saved destination is the boarding stop');

    // Return direction: Aglipay books toward the Cabanatuan terminal.
    await seed({ case: 'simulation', bus_id: 'Bus-13', driver_id: 'DRV-2', trip_id: 78, lat: 15.688171, lng: 121.078333 });
    cookie = await scanSession('Bus-13');
    info = await tripInfo(cookie);
    eq(info.boarding.destination, 'Aglipay, Rizal', 'Return trip: boarding at Aglipay');
    ok(info.destinations.length === 19, 'Return trip: the local option plus 18 destinations ahead');
    eq(info.destinations[0].destination, 'Aglipay, Rizal', 'Return trip: the boarding stop leads as the local option');
    eq(info.destinations[1].destination, 'Mangahan, Rizal', 'Return trip: Mangahan is the first stop ahead');
    eq(info.destinations[18].destination, 'Cabanatuan Central Terminal', 'Return trip: the Cabanatuan terminal is last');

    result = await postJson('/passenger/one-time/register', {
        bus_no: 'Bus-13',
        trip_no: '78',
        full_name: 'Maria Santos',
        passenger_type: 'regular',
        payment_method: 'cash',
        submission_token: 'tok-e2e-return',
        boarding_id: info.boarding.id,
        destination_id: info.destinations[18].id,
        latitude: 15.68,
        longitude: 121.07
    }, cookie);
    ok(result.success, 'Return trip: booking saves');
    eq(Number(result.data.fare), 36, 'Return trip: saved fare (destination minus boarding after the Rizal origin)');
    eq(result.data.boarding_point, 'Aglipay, Rizal', 'Return trip: saved boarding point');
    eq(result.data.destination, 'Cabanatuan Central Terminal', 'Return trip: saved destination');

    // Return terminal: the same-stop fallback offers the boarding stop.
    await seed({ case: 'simulation', bus_id: 'Bus-13', driver_id: 'DRV-2', trip_id: 78, lat: 15.472336, lng: 120.969126 });
    cookie = await scanSession('Bus-13');
    info = await tripInfo(cookie);
    eq(info.boarding.destination, 'Cabanatuan Central Terminal', 'Return terminal: boarding point');
    eq(info.destinations.length, 1, 'Return terminal: the same-stop destination remains');
    eq(info.destinations[0].same_as_boarding, true, 'Return terminal: the same-stop fallback is flagged');

    // --- 7.2 Stale / unavailable / disabled simulation gates ------------------
    await seed({ case: 'history', lat: 15.548218, lng: 120.933455, age: 600 });
    cookie = await scanSession('Bus-12');
    info = await tripInfo(cookie);
    eq(info.boarding_error.code, 'BUS_LOCATION_STALE', 'Stale live row: stale error');
    result = await postJson('/passenger/one-time/register', {
        bus_no: 'Bus-12',
        trip_no: '77',
        full_name: 'Pedro Reyes',
        passenger_type: 'regular',
        payment_method: 'cash',
        submission_token: 'tok-e2e-stale',
        boarding_id: 105,
        destination_id: 119,
        latitude: 15.5,
        longitude: 121.0
    }, cookie);
    eq(result.success, false, 'Stale live row: booking refused');
    eq(result.code, 'BUS_LOCATION_STALE', 'Stale live row: refusal names the stale location');
    eq((await (await request('/_test/trip-by-token?token=tok-e2e-stale')).json()).count, 0, 'Stale live row: no trip saved');

    await seed({ case: 'clear' });
    cookie = await scanSession('Bus-12');
    info = await tripInfo(cookie);
    eq(info.boarding_error.code, 'BUS_LOCATION_UNAVAILABLE', 'No position: unavailable error');
    result = await postJson('/passenger/one-time/register', {
        bus_no: 'Bus-12',
        trip_no: '77',
        full_name: 'Pedro Reyes',
        passenger_type: 'regular',
        payment_method: 'cash',
        submission_token: 'tok-e2e-none',
        boarding_id: 105,
        destination_id: 119,
        latitude: 15.548,
        longitude: 120.933
    }, cookie);
    eq(result.success, false, 'No position: booking refused even with passenger GPS supplied');
    eq(result.code, 'BUS_LOCATION_UNAVAILABLE', 'No position: refusal names the unavailable bus location');
    eq((await (await request('/_test/trip-by-token?token=tok-e2e-none')).json()).count, 0, 'No position: no trip saved');

    await seed({ case: 'simulation', status: 'disabled', lat: 15.548218, lng: 120.933455 });
    await seed({ case: 'history', lat: 15.548218, lng: 120.933455, age: 600 });
    cookie = await scanSession('Bus-12');
    info = await tripInfo(cookie);
    ok(info.bus_position.source !== 'simulation', 'Disabled simulation: the override is ignored');
    eq(info.boarding_error.code, 'BUS_LOCATION_STALE', 'Disabled simulation: falls back to the stale live row');
    eq(info.boarding, null, 'Disabled simulation: no boarding point is assigned');

    // The UI harness proves the error card + retry render for these codes; the
    // server side above proves no boarding record can be produced.
}

run()
    .then(() => {
        console.log(`${checks} one-time e2e simulation checks passed.`);
        if (php) { php.kill(); }
    })
    .catch((error) => {
        console.error(serverOutput.slice(-2500));
        if (php) { php.kill(); }
        console.error(error);
        process.exit(1);
    });
