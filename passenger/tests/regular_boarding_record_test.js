/* Regular scan/store: the recorded boarding point comes from the bus's
   current (simulated) position, is fixed once recorded, and a re-scan returns
   the same boarding record. The passenger's phone location is only the
   fallback when the trip has no usable bus position. */
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '../..');
const temporaryRoot = process.env.REGULAR_PARTY_TEST_TEMP || 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(temporaryRoot).isDirectory(), 'Approved test temporary directory exists');
const temporary = fs.mkdtempSync(path.join(temporaryRoot, 'regular-boarding-'));

let php;
let serverOutput = '';
let checks = 0;
const eq = (actual, expected, message) => { assert.equal(actual, expected, message); checks++; };
const ok = (value, message) => { assert.ok(value, message); checks++; };
const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const VALDEFUENTE = [15.506807, 120.966642];
const MAYAPYAP = [15.514560, 120.956086];
const CABANATUAN = [15.472229, 120.969183];

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
            const response = await fetch(base + '/_test/trip-row?id=0');
            if (response.ok) { ready = true; break; }
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

    const scan = async (passengerId, body) => {
        const response = await request('/passenger/scan/store?passenger_id=' + passengerId, {
            method: 'POST',
            body: JSON.stringify(body)
        });
        return response.json();
    };

    const tripRow = async (id) => (await (await request('/_test/trip-row?id=' + id)).json()).row;

    // --- The bus is at Valdefuente; the phone sits at the Cabanatuan terminal
    await seedPosition(VALDEFUENTE);
    let result = await scan(6, {
        bus_no: 'Bus-2',
        trip_no: 11,
        latitude: CABANATUAN[0],
        longitude: CABANATUAN[1],
        accuracy: 10,
        payment_method: 'cash'
    });
    ok(result.success, 'The boarding scan succeeds: ' + JSON.stringify(result).slice(0, 300));
    eq(result.type, 'boarding', 'The scan records a boarding');
    const tripId = Number(result.trip_id);

    let row = await tripRow(tripId);
    eq(row.boarding_point, 'Valdefuente', 'The boarding point is the bus position, not the phone location');
    eq(Number(row.boarding_stop_id), 4001, 'The bus stop route-stop id is recorded');
    eq(Number(row.boarding_km_marker), 5, 'The bus stop km marker is recorded');
    eq(Number(Number(row.latitude).toFixed(4)), 15.4722, 'The passenger phone location is still stored as supporting data');
    eq(row.passenger_type, 'Regular', 'The boarding records the account passenger type');

    // --- The bus moves on; the recorded boarding cannot change --------------
    await seedPosition(MAYAPYAP);
    result = await scan(6, {
        bus_no: 'Bus-2',
        trip_no: 11,
        latitude: MAYAPYAP[0],
        longitude: MAYAPYAP[1],
        accuracy: 10,
        payment_method: 'cash'
    });
    row = await tripRow(tripId);
    ok(row, 'The recorded trip still exists after the re-scan');
    eq(row.boarding_point, 'Valdefuente', 'The recorded boarding point is fixed once recorded');
    eq(Number(row.boarding_stop_id), 4001, 'The recorded boarding stop id is unchanged');

    // --- No bus position: the phone location is the fallback ----------------
    await seedPosition(null);
    result = await scan(7, {
        bus_no: 'Bus-2',
        trip_no: 11,
        latitude: MAYAPYAP[0],
        longitude: MAYAPYAP[1],
        accuracy: 10,
        payment_method: 'cash'
    });
    ok(result.success, 'The fallback scan succeeds: ' + JSON.stringify(result).slice(0, 300));
    const fallbackRow = await tripRow(Number(result.trip_id));
    eq(fallbackRow.boarding_point, 'Mayapyap', 'Without a bus position the phone location records the stop');
    eq(fallbackRow.passenger_type, 'Student', 'The boarding records the account discount type for discounted passengers');

    console.log(`${checks} regular boarding record checks passed.`);
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
