/* Trip info -> auto boarding + remaining destinations, at the five required
   positions in both directions and across every error state. Isolated SQLite. */
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const temporaryRoot = process.env.ONE_TIME_TEST_TEMP || 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(temporaryRoot).isDirectory(), 'Approved test temporary directory exists');
const temporary = fs.mkdtempSync(path.join(temporaryRoot, 'one-time-trip-'));
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

    const tripInfo = async (bus, seedPayload) => {
        await seed(seedPayload);
        const scan = await request('/passenger/scan.php?bus=' + encodeURIComponent(bus));
        const cookie = cookieOf(scan);
        assert.ok(cookie, 'Scan established a session for ' + bus);
        const response = await request('/passenger/one-time/trip', { headers: { Cookie: cookie } });
        const body = await response.json();
        assert.equal(body.success, true, 'Trip info succeeds for ' + bus);
        return body.data;
    };

    const boardingOf = (data) => data.boarding || null;
    const destinationNames = (data) => (data.destinations || []).map((stop) => stop.destination);
    const destinationIds = (data) => (data.destinations || []).map((stop) => stop.id);

    // --- Outbound: the five required positions ------------------------------
    let data = await tripInfo('Bus-12', { case: 'history', lat: 15.472229, lng: 120.969183, age: 10 });
    eq(boardingOf(data).destination, 'Cabanatuan Central Terminal → Daang Sarile', 'Cabanatuan: terminal boarding stop');
    eq(destinationNames(data).length, 20, 'Cabanatuan: the local stop plus every later stop remains');
    eq(destinationNames(data)[0], 'Cabanatuan Central Terminal → Daang Sarile', 'Cabanatuan: the boarding stop leads the list as the local option');
    eq(data.destinations[0].is_boarding, true, 'Cabanatuan: the local option is flagged');
    eq(destinationNames(data)[1], 'Valdefuente', 'Cabanatuan: first stop ahead is Valdefuente');
    eq(data.bus_position.source, 'driver_location_history', 'Cabanatuan: live position source');

    data = await tripInfo('Bus-12', { case: 'history', lat: 15.548218, lng: 120.933455, age: 10 });
    eq(boardingOf(data).id, 105, 'Pinagpanaan: boarding fare id');
    eq(boardingOf(data).destination, 'Pinagpanaan, Talavera', 'Pinagpanaan: boarding stop itself');
    eq(destinationNames(data).length, 15, 'Pinagpanaan: the local stop plus the stops ahead remain');
    eq(destinationNames(data)[0], 'Pinagpanaan, Talavera', 'Pinagpanaan: the boarding stop leads the list as the local option');
    eq(data.destinations[0].is_boarding, true, 'Pinagpanaan: the local option is flagged');
    eq(destinationNames(data)[1], 'Baluga/Sto. Niño, Talavera', 'Pinagpanaan: next stop is first stop ahead');
    ok(!destinationNames(data).includes('Valdefuente'), 'Pinagpanaan: passed stops are not destinations');
    eq(data.boarding_error, null, 'Pinagpanaan: no boarding error');

    data = await tripInfo('Bus-12', { case: 'history', lat: 15.566468, lng: 120.953142, age: 10 });
    eq(boardingOf(data).destination, 'Homestead 1, Talavera', 'Homestead 1: matched stop');
    eq(destinationNames(data)[1], 'Homestead 2, Talavera', 'Homestead 1: destinations start at Homestead 2');

    data = await tripInfo('Bus-12', { case: 'history', lat: 15.689167, lng: 121.078555, age: 10 });
    eq(boardingOf(data).destination, 'Del Pilar, Rizal', 'Del Pilar: matched stop');
    eq(destinationNames(data).join(','), 'Del Pilar, Rizal,Rizal/Pob Sur Terminal', 'Del Pilar: the local stop and the terminal remain');

    data = await tripInfo('Bus-12', { case: 'history', lat: 15.705381, lng: 121.099504, age: 10 });
    eq(boardingOf(data).destination, 'Rizal/Pob Sur Terminal', 'Rizal terminal: matched stop');
    eq(destinationNames(data).length, 1, 'Rizal terminal: only the same-stop destination remains');
    eq(destinationNames(data)[0], 'Rizal/Pob Sur Terminal', 'Rizal terminal: the boarding stop is the destination');
    eq(data.destinations[0].same_as_boarding, true, 'Rizal terminal: the same-stop fallback is flagged');
    eq(data.boarding_error, null, 'Rizal terminal: boarding is still resolved');

    // --- The registered boarding point survives refresh and bus movement -----
    await seed({ case: 'history', lat: 15.548218, lng: 120.933455, age: 10 });
    const lockedScan = await request('/passenger/scan.php?bus=Bus-12');
    const lockedCookie = cookieOf(lockedScan);
    assert.ok(lockedCookie, 'Lock scan established a session');

    let lockedInfo = await (await request('/passenger/one-time/trip', { headers: { Cookie: lockedCookie } })).json();
    eq(lockedInfo.success, true, 'Locked trip info succeeds');
    eq(lockedInfo.data.boarding.id, 105, 'The first resolution registers Pinagpanaan');
    eq(lockedInfo.data.boarding_locked, true, 'The boarding is reported as registered');
    eq(lockedInfo.data.destinations.length, 15, 'The local option plus the stops ahead are offered');
    eq(lockedInfo.data.destinations[0].destination, 'Pinagpanaan, Talavera', 'The registered stop leads as the local option');
    eq(lockedInfo.data.destinations[0].is_boarding, true, 'The local option is flagged');

    await seed({ case: 'history', lat: 15.689167, lng: 121.078555, age: 10 });
    lockedInfo = await (await request('/passenger/one-time/trip', { headers: { Cookie: lockedCookie } })).json();
    eq(lockedInfo.data.boarding.id, 105, 'Bus movement does not change the registered boarding point');
    eq(lockedInfo.data.boarding_locked, true, 'The boarding stays registered after movement');
    eq(lockedInfo.data.destinations.length, 1, 'Only stops ahead of the bus remain once it has passed the registered area');
    eq(lockedInfo.data.destinations[0].destination, 'Rizal/Pob Sur Terminal', 'The terminal is the only stop left ahead of the bus');
    ok(!lockedInfo.data.destinations.some((stop) => stop.is_boarding === true), 'No local option remains after the bus passed the stop');

    await seed({ case: 'clear' });
    lockedInfo = await (await request('/passenger/one-time/trip', { headers: { Cookie: lockedCookie } })).json();
    eq(lockedInfo.data.boarding.id, 105, 'A missing live position does not drop the registered boarding point');
    eq(lockedInfo.data.boarding_locked, true, 'The boarding stays registered without a live position');
    eq(lockedInfo.data.boarding_error, null, 'No boarding error while the registered boarding stands');
    eq(lockedInfo.data.destinations.length, 15, 'Without a bus position the registered boarding order is the floor');
    eq(lockedInfo.data.destinations[0].is_boarding, true, 'The local option returns while the bus position is unknown');

    // --- Return direction ----------------------------------------------------
    data = await tripInfo('Bus-13', { case: 'history', bus_id: 'Bus-13', driver_id: 'DRV-2', lat: 15.688171, lng: 121.078333, age: 10 });
    eq(boardingOf(data).destination, 'Aglipay, Rizal', 'Return: first stop is Aglipay');
    eq(destinationNames(data).length, 19, 'Return: the local stop plus the remaining stops follow the return order');
    eq(destinationNames(data)[0], 'Aglipay, Rizal', 'Return: the boarding stop leads as the local option');
    eq(destinationNames(data)[1], 'Mangahan, Rizal', 'Return: next stop is Mangahan');

    data = await tripInfo('Bus-13', { case: 'history', bus_id: 'Bus-13', driver_id: 'DRV-2', lat: 15.472336, lng: 120.969126, age: 10 });
    eq(boardingOf(data).destination, 'Cabanatuan Central Terminal', 'Return: terminal boarding stop');
    eq(destinationNames(data).length, 1, 'Return: the end of the return route offers the same-stop destination');
    eq(destinationNames(data)[0], 'Cabanatuan Central Terminal', 'Return: the boarding stop is the destination');
    eq(data.destinations[0].same_as_boarding, true, 'Return: the same-stop fallback is flagged');

    data = await tripInfo('Bus-13', { case: 'history', bus_id: 'Bus-13', driver_id: 'DRV-2', lat: 15.705381, lng: 121.099504, age: 10 });
    eq(boardingOf(data).destination, 'Rizal/Pob Sur Terminal', 'Return origin: the Rizal terminal is the boarding stop');
    eq(data.boarding_error, null, 'Return origin: the bus at the terminal is matched, not off-route');
    eq(destinationNames(data).length, 21, 'Return origin: the local stop and every later stop remain');
    eq(destinationNames(data)[1], 'Del Pilar, Rizal', 'Return origin: Del Pilar is the first stop ahead');

    data = await tripInfo('Bus-13', { case: 'history', bus_id: 'Bus-13', driver_id: 'DRV-2', lat: 15.689167, lng: 121.078555, age: 10 });
    eq(boardingOf(data).destination, 'Del Pilar, Rizal', 'Return Del Pilar: matched stop');
    eq(destinationNames(data)[1], 'Aglipay, Rizal', 'Return Del Pilar: Aglipay is the next stop ahead');

    // --- Simulated position is treated like live GPS -------------------------
    data = await tripInfo('Bus-12', { case: 'simulation', lat: 15.566468, lng: 120.953142 });
    eq(data.bus_position.source, 'simulation', 'Simulation: position source is the simulation');
    eq(data.bus_position.usable, true, 'Simulation: position is usable');
    eq(boardingOf(data).destination, 'Homestead 1, Talavera', 'Simulation: same boarding result as live GPS');

    // --- Error states ---------------------------------------------------------
    data = await tripInfo('Bus-12', { case: 'clear' });
    eq(boardingOf(data), null, 'Missing position: no boarding stop');
    eq(data.boarding_error.code, 'BUS_LOCATION_UNAVAILABLE', 'Missing position: unavailable error');
    eq(data.bus_position.latitude, null, 'Missing position: no coordinates substituted');
    eq(destinationIds(data).length, 0, 'Missing position: no destinations offered');

    data = await tripInfo('Bus-12', { case: 'history', lat: 15.548218, lng: 120.933455, age: 600 });
    eq(data.boarding_error.code, 'BUS_LOCATION_STALE', 'Stale position: stale error');
    eq(boardingOf(data), null, 'Stale position: no boarding stop');

    data = await tripInfo('Bus-12', { case: 'history', lat: 14.0, lng: 119.0, age: 10 });
    eq(data.boarding_error.code, 'BUS_OFF_ROUTE', 'Off-route bus: off-route error');
    eq(boardingOf(data), null, 'Off-route bus: no boarding stop');

    data = await tripInfo('Bus-14', { case: 'history', bus_id: 'Bus-14', driver_id: 'DRV-3', lat: 15.55, lng: 120.94, age: 10 });
    eq(data.boarding_error.code, 'ROUTE_UNSUPPORTED', 'Unknown route: unsupported error');
    eq(boardingOf(data), null, 'Unknown route: no boarding stop');

    // The legacy stops list stays available for compatibility.
    data = await tripInfo('Bus-12', { case: 'history', lat: 15.548218, lng: 120.933455, age: 10 });
    eq(Array.isArray(data.stops) && data.stops.length, 20, 'The existing stops list is preserved');
}

run()
    .then(() => {
        console.log(`${checks} one-time trip info checks passed.`);
        if (php) { php.kill(); }
    })
    .catch((error) => {
        console.error(serverOutput.slice(-2500));
        if (php) { php.kill(); }
        console.error(error);
        process.exit(1);
    });
