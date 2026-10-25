const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const { spawn } = require('node:child_process');
const assert = require('node:assert/strict');
const tempRoot = 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(tempRoot).isDirectory());
const temp = fs.mkdtempSync(path.join(tempRoot, 'party-lock-'));
let php;
(async () => {
    const server = net.createServer();
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const port = server.address().port;
    await new Promise(resolve => server.close(resolve));
    const base = `http://127.0.0.1:${port}`;
    php = spawn('php', ['-S', `127.0.0.1:${port}`, path.join(__dirname, 'fixtures/regular_party_http.php')], {
        env: { ...process.env, REGULAR_PARTY_TEST_DB: path.join(temp, 'lock.sqlite') }, stdio: 'ignore'
    });
    const call = async (url, body) => {
        const r = await fetch(base + url, body ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {});
        return { status: r.status, data: await r.json() };
    };
    for (let i = 0; i < 100; i++) {
        try { await call('/passenger/companions/party'); break; } catch { await new Promise(resolve => setTimeout(resolve, 30)); }
    }
    await call('/_test/before-destination', {});
    let result = await call('/passenger/companions/party', { trip_id: 20, revision: 0, companions: [
        { profile_id: 1, companion_type: 'child' }, { profile_id: 2, companion_type: 'senior' }, { profile_id: 3, companion_type: 'other' }, { profile_id: 4, companion_type: 'pwd' }
    ] });
    assert.equal(result.status, 200);
    assert.equal(result.data.companions_locked, false);
    result = await call('/passenger/trip/select-destination', { destination_id: 200 });
    assert.equal(result.data.party.party_total, 132);
    assert.equal(result.data.party.breakdown.lines[4].type_label, 'PWD');
    assert.equal(result.data.party.breakdown.lines[4].discount_amount, 6);
    assert.equal(result.data.party.companions_locked, true);
    assert.equal(result.data.destination_locked, true);
    const party = result.data.party;
    const changedDestination = await call('/passenger/trip/select-destination', { destination_id: 300 });
    assert.equal(changedDestination.status, 409);
    assert.equal(changedDestination.data.code, 'DESTINATION_LOCKED');
    for (const companions of [[], party.companions.map(c => ({ ...c, companion_type: 'other' })), [...party.companions, { profile_id: 4, companion_type: 'child' }]]) {
        const rejected = await call('/passenger/companions/party', { trip_id: 20, revision: party.revision, companions });
        assert.equal(rejected.status, 409);
        assert.equal(rejected.data.code, 'COMPANIONS_LOCKED');
    }
    result = await call('/passenger/companions/party?trip_id=20');
    assert.equal(result.data.party_total, 132);
    assert.equal(result.data.companions.length, 4);
    assert.equal(result.data.companions_locked, true);
    const payment = await call('/_test/payment');
    assert.equal(payment.data.amount_centavos, 13200);
    console.log('Regular destination selection, locked companion mutations, restore, and payment checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (php && php.exitCode === null) { const done = new Promise(resolve => php.once('close', resolve)); php.kill(); await done; }
    fs.rmSync(temp, { recursive: true, force: true });
});
