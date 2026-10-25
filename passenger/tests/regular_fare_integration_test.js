/* Real homepage functions -> HTTP -> actual PHP controllers -> isolated SQLite. */
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const net = require('node:net');
const { spawn } = require('node:child_process');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const temporaryRoot = process.env.REGULAR_PARTY_TEST_TEMP || 'C:\\Users\\MAVHIL~1\\AppData\\Local\\Temp\\opencode';
assert.ok(fs.statSync(temporaryRoot).isDirectory(), 'Approved test temporary directory exists');
const temporary = fs.mkdtempSync(path.join(temporaryRoot, 'regular-fare-'));
let php;
let serverOutput = '';
let checks = 0;
const eq = (actual, expected, message) => { assert.equal(actual, expected, message); checks++; };
const pause = () => new Promise(resolve => setTimeout(resolve, 30));
class Element {
    constructor(id) { this.id = id; this.style = {}; this.children = []; this.events = {}; this.value = ''; this.attrs = {}; this.dataset = {}; this.options = []; this.selectedIndex = -1; }
    set innerHTML(value) { this.children = []; this._html = value; }
    get innerHTML() { return this._html || ''; }
    appendChild(child) { this.children.push(child); }
    setAttribute(name, value) { this.attrs[name] = value; }
    getAttribute(name) { return this.attrs[name]; }
    addEventListener(name, callback) { this.events[name] = callback; }
    click() { this.events.click?.({ target: this }); }
    focus() {}
}
async function run() {
    const listener = net.createServer();
    await new Promise(resolve => listener.listen(0, '127.0.0.1', resolve));
    const port = listener.address().port;
    await new Promise(resolve => listener.close(resolve));
    const base = `http://127.0.0.1:${port}`;
    php = spawn('php', ['-S', `127.0.0.1:${port}`, path.join(__dirname, 'fixtures/regular_party_http.php')], {
        cwd: root, env: { ...process.env, REGULAR_PARTY_TEST_DB: path.join(temporary, 'party.sqlite') }, stdio: ['ignore', 'pipe', 'pipe']
    });
    php.stdout.on('data', chunk => { serverOutput += chunk; });
    php.stderr.on('data', chunk => { serverOutput += chunk; });
    php.on('error', error => { serverOutput += error.message; });
    let ready = false;
    for (let attempt = 0; attempt < 100; attempt++) {
        try { const r = await fetch(base + '/passenger/companions/party'); if (r.ok) { ready = true; break; } } catch {}
        await pause();
    }
    assert.ok(ready, 'PHP fixture server ready');
    const elements = new Map();
    const el = id => { if (!elements.has(id)) elements.set(id, new Element(id)); return elements.get(id); };
    const types = ['child', 'senior', 'other'].map(type => { const e = new Element(); e.setAttribute('data-ctype', type); return e; });
    const select = el('destinationSelect');
    select.options = [{ dataset: { destination: 'Stop C' } }, { dataset: { destination: 'Stop D' } }];
    select.value = '200'; select.selectedIndex = 0;
    const window = { _activeTripId: 20, events: {}, addEventListener(name, callback) { this.events[name] = callback; } };
    const context = {
        window, document: { getElementById: el, createElement: () => new Element(), querySelectorAll: () => types },
        localStorage: { getItem: () => null, setItem() {} }, URLSearchParams, console, setTimeout: () => {},
        HOME_FARE_UNAVAILABLE: 'Fare unavailable', HOME_FARE_RETRY: 'Retry', activeDiscountType: 'regular',
        lastDestinationFetch: { tripNo: 10, boardingStopId: 1000, boardingPoint: 'Stop A' },
        refreshRouteMap: () => {}, maybePromptOnlinePayment: () => {}, resolveFallbackFare: async () => { throw new Error('Party must use backend quote, not a primary fallback'); },
        fetch: (url, options) => fetch(new URL(url, base), options)
    };
    const source = fs.readFileSync(path.join(root, 'passenger/views/Homepage/Index.php'), 'utf8');
    const scripts = [...source.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(m => m[1]);
    vm.runInNewContext(scripts.find(s => s.includes('window.__companionChannelInit')), context);
    vm.runInNewContext(source.slice(source.indexOf('async function showRecordedFare('), source.indexOf('const installPrompt =')), context);
    window.events.DOMContentLoaded();
    await window.companionBeforeBoard();
    const verify = async (total, count, message) => {
        eq(el('fareRate').textContent, '₱' + total.toFixed(2), message + ': homepage total');
        const party = await (await fetch(base + '/passenger/companions/party?trip_id=20')).json();
        const payment = await (await fetch(base + '/_test/payment')).json();
        eq(party.party_total, total, message + ': real backend quote');
        eq(payment.amount_centavos, total * 100, message + ': actual checkout amount calculation');
        eq(payment.companion_count, count, message + ': no duplicated companion rows');
    };
    const add = async (name, type) => {
        types[['child', 'senior', 'other'].indexOf(type)].click();
        el('companionNameInput').value = name; el('companionAddBtn').click();
        await window.companionBeforeBoard();
    };
    await verify(30, 0, 'Primary alone');
    await add('Child Rider', 'child');
    await verify(54, 1, 'Add Child');
    for (let n = 0; n < 50 && !el('companionSavedList').children.length; n++) await pause();
    const senior = el('companionSavedList').children.find(row => row.children[1].textContent === 'Senior Rider');
    assert.ok(senior, 'Saved senior profile restored');
    senior.children[0].checked = true; senior.children[0].events.change();
    await window.companionBeforeBoard();
    await verify(78, 2, 'Select saved Senior');
    await add('Regular Rider', 'other');
    await verify(108, 3, 'Mixed three companions');
    await context.updateFareDisplay();
    await verify(108, 3, 'Main fare updater does not overwrite party with 30');
    const data = await (await fetch(base + '/passenger/companions/party?trip_id=20')).json();
    eq(data.breakdown.lines[1].discount_amount, 6, 'Child discount applied once');
    eq(data.breakdown.lines[2].discount_amount, 6, 'Senior discount applied once');
    eq(data.breakdown.lines[3].discount_amount, 0, 'Regular has no discount');
    const childEdit = el('companionAttachList').children[0].children[1];
    childEdit.value = 'other'; childEdit.events.change();
    await window.companionBeforeBoard();
    await verify(114, 3, 'Edit Child to Regular');
    el('companionAttachList').children[1].children[2].click();
    await window.companionBeforeBoard();
    await verify(90, 2, 'Remove Senior');
    select.value = '300'; select.selectedIndex = 1;
    await context.updateFareDisplay();
    await verify(180, 2, 'Destination changes all traveller fares');
    eq(select.disabled, false, 'Unpaid destination remains editable');
    await add('Senior Rider', 'senior');
    await verify(228, 3, 'Add Senior at new destination');
    await fetch(base + '/_test/charge', { method: 'POST' });
    await window.regularPartyRefresh();
    await verify(233, 3, 'Existing additional balance counted once');
    select.value = '200'; select.selectedIndex = 0;
    await context.updateFareDisplay();
    await verify(119, 3, 'Destination change preserves existing additional balance');
    await add('Second Child', 'child');
    await add('Second Senior', 'senior');
    await verify(167, 5, 'Maximum five companions with additional balance');
    const accepted = await (await fetch(base + '/passenger/companions/party?trip_id=20')).json();
    await context.showRecordedFare({ estimated_fare: 30, party: accepted }, 200);
    await verify(167, 5, 'Reopen restore uses party total instead of primary estimate');
    const payment = await (await fetch(base + '/_test/payment')).json();
    eq(payment.primary_fare, 30, 'Primary stored fare is not replaced with party total');
    await fetch(base + '/_test/legacy', { method: 'POST' });
    await window.regularPartyRefresh();
    await context.showRecordedFare({ estimated_fare: 30 }, 200);
    eq(el('fareRate').textContent, '₱30.00', 'Legacy server estimate can be restored before attachment');
    types[0].click(); el('companionNameInput').value = 'Child Rider'; el('companionAddBtn').click();
    const destinationDuringAdd = context.updateFareDisplay();
    types[1].click(); el('companionNameInput').value = 'Senior Rider'; el('companionAddBtn').click();
    await window.companionBeforeBoard(); await destinationDuringAdd;
    await verify(78, 2, 'Rapid two additions interleaved with destination refresh');
    const repaired = await (await fetch(base + '/passenger/companions/party?trip_id=20')).json();
    eq(repaired.route.destination_id, 200, 'Selected-only destination promoted consistently for checkout');
    eq(repaired.breakdown.lines[1].destination, 'Stop C', 'Companion stored route follows repaired primary');
    console.log(`${checks} real HTTP regular fare integration checks passed.`);
}
run().catch(error => { console.error(error); console.error(serverOutput); process.exitCode = 1; }).finally(async () => {
    if (php && php.exitCode === null) {
        const closed = new Promise(resolve => php.once('close', resolve)); php.kill(); await closed;
    }
    fs.rmSync(temporary, { recursive: true, force: true });
});
