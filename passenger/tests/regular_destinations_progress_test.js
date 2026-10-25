/* Adaptivity of /passenger/api/destinations: the recorded boarding stays the
   floor, the bus's current (simulated) position raises the threshold, and
   stops the driver has already passed drop out of the offered list. */
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const temporaryRoot = process.env.REGULAR_PARTY_TEST_TEMP || 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(temporaryRoot).isDirectory(), 'Approved test temporary directory exists');
const temporary = fs.mkdtempSync(path.join(temporaryRoot, 'regular-dest-'));

let php;
let serverOutput = '';
let checks = 0;
const eq = (actual, expected, message) => { assert.equal(actual, expected, message); checks++; };
const ok = (value, message) => { assert.ok(value, message); checks++; };
const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const BOARDING_NAME = 'Cabanatuan Central Terminal → Daang Sarile';
const VALDEFUENTE = [15.506807, 120.966642];
const MAYAPYAP = [15.514560, 120.956086];

async function run() {
    const listener = net.createServer();
    await new Promise((resolve) => listener.listen(0, '127.0.0.1', resolve));
    const port = listener.address().port;
    await new Promise((resolve) => listener.close(resolve));
    const base = `http://127.0.0.1:${port}`;

    php = spawn('php', ['-S', `127.0.0.1:${port}`, path.join(__dirname, 'fixtures/regular_party_http.php')], {
        cwd: root,
        env: { ...process.env, REGULAR_PARTY_TEST_DB: path.join(temporary, 'party.sqlite') },
        stdio: ['ignore', 'pipe', 'pipe']
    });
    php.stdout.on('data', (chunk) => { serverOutput += chunk; });
    php.stderr.on('data', (chunk) => { serverOutput += chunk; });

    let ready = false;
    for (let attempt = 0; attempt < 100; attempt++) {
        try {
            const response = await fetch(base + '/passenger/api/destinations', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ trip_no: 0 })
            });
            if (response.ok || response.status === 400) { ready = true; break; }
        } catch {}
        await pause(30);
    }
    assert.ok(ready, 'PHP fixture server ready: ' + serverOutput.slice(-800));

    const request = (url, options = {}) => fetch(base + url, {
        headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
        ...options
    });

    const seedPosition = async (position) => {
        const response = await request('/_test/bus-position', {
            method: 'POST',
            body: JSON.stringify(position ? { lat: position[0], lng: position[1] } : {})
        });
        assert.ok(response.ok, 'Bus position seeded');
    };

    const destinationsFor = async () => {
        const response = await request('/passenger/api/destinations', {
            method: 'POST',
            body: JSON.stringify({
                trip_no: 11,
                boarding_stop_id: 4000,
                boarding_point: BOARDING_NAME
            })
        });
        const body = await response.json();
        assert.equal(body.success, true, 'Destinations request succeeds: ' + JSON.stringify(body).slice(0, 300));
        return body;
    };

    const orders = (body) => body.destinations.map((stop) => Number(stop.display_order)).join(',');

    // --- No bus position: the recorded boarding order is the floor ----------
    await seedPosition(null);
    let body = await destinationsFor();
    eq(body.boarding_order, 1, 'The recorded boarding order is reported');
    eq(body.progress_order, 1, 'Progress falls back to the recorded boarding order');
    eq(orders(body), '4,3,2,1', 'Every stop from the boarding stop remains without a bus position');
    eq(body.destinations.find((stop) => Number(stop.display_order) === 1).is_boarding, true, 'The recorded boarding stop is flagged');
    ok(!body.destinations.some((stop) => stop.is_current === true), 'No current stop is flagged without a bus position');

    // --- Bus at Valdefuente: earlier stops drop out --------------------------
    await seedPosition(VALDEFUENTE);
    body = await destinationsFor();
    eq(body.progress_order, 2, 'The bus position raises the progress threshold');
    eq(orders(body), '4,3,2', 'Stops the bus has already passed are not offered');
    ok(!body.destinations.some((stop) => Number(stop.display_order) === 1), 'The passed boarding stop is excluded');
    eq(body.destinations.find((stop) => Number(stop.display_order) === 2).is_current, true, 'The bus-matched stop is flagged as current');
    ok(!body.destinations.some((stop) => stop.is_boarding === true), 'The recorded boarding flag follows the filtered list');

    // --- Bus at Mayapyap: the list keeps shrinking ---------------------------
    await seedPosition(MAYAPYAP);
    body = await destinationsFor();
    eq(body.progress_order, 3, 'The progress threshold follows the bus');
    eq(orders(body), '4,3', 'Only stops ahead of the bus remain');
    eq(body.destinations.find((stop) => Number(stop.display_order) === 3).is_current, true, 'The latest bus stop is flagged as current');

    // --- No position again: back to the recorded boarding floor --------------
    await seedPosition(null);
    body = await destinationsFor();
    eq(orders(body), '4,3,2,1', 'A missing bus position restores the recorded-boarding list');
    eq(body.progress_order, 1, 'Progress returns to the recorded boarding order');

    // --- The selection endpoint refuses passed stops --------------------------
    await seedPosition(MAYAPYAP);
    await request('/_test/seed-boarding', { method: 'POST' });

    const passedSelection = await request('/passenger/trip/select-destination', {
        method: 'POST',
        body: JSON.stringify({ destination_id: 401 })
    });
    const passedBody = await passedSelection.json();
    eq(passedBody.success, false, 'Selecting a stop the bus has passed is refused');
    eq(passedBody.code, 'DESTINATION_PASSED', 'The refusal names the passed stop');

    const aheadSelection = await request('/passenger/trip/select-destination', {
        method: 'POST',
        body: JSON.stringify({ destination_id: 403 })
    });
    const aheadBody = await aheadSelection.json();
    ok(aheadBody.success, 'Selecting a stop ahead of the bus succeeds: ' + JSON.stringify(aheadBody).slice(0, 300));
    eq(Number(aheadBody.selected_destination_id), 403, 'The ahead stop is saved as the destination');

    console.log(`${checks} regular destination progress checks passed.`);
}

run().catch((error) => {
    console.error(serverOutput);
    console.error(error);
    process.exitCode = 1;
}).finally(async () => {
    if (php && php.exitCode === null) {
        const closed = new Promise((resolve) => php.once('close', resolve));
        php.kill();
        await closed;
    }
    fs.rmSync(temporary, { recursive: true, force: true });
});
