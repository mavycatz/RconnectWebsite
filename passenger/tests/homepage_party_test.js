/* Execute the actual homepage companion script with a DOM/API fixture. */
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../views/Homepage/Index.php'), 'utf8');
let scripts = [...source.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(m => m[1]);
for (const script of scripts) {
    new vm.Script(script.replace(/<\?(?:php|=)[\s\S]*?\?>/g, 'null'));
}
const script = scripts.find(s => s.includes('window.__companionChannelInit'));
assert.ok(script, 'Homepage party script present');
class Element {
    constructor(id) { this.id = id; this.style = {}; this.children = []; this.events = {}; this.value = ''; this.attrs = {}; }
    set innerHTML(v) { this.children = []; this._html = v; }
    get innerHTML() { return this._html || ''; }
    appendChild(el) { this.children.push(el); }
    setAttribute(k, v) { this.attrs[k] = v; }
    getAttribute(k) { return this.attrs[k]; }
    addEventListener(event, cb) { this.events[event] = cb; }
    click() { this.events.click?.({ target: this }); }
    focus() {}
}
const elements = new Map();
const el = id => { if (!elements.has(id)) elements.set(id, new Element(id)); return elements.get(id); };
const typeButtons = ['child', 'senior', 'other'].map(type => { const e = new Element(); e.setAttribute('data-ctype', type); return e; });
let state = { revision: 0, companions: [] };
let rejectWrite = false;
let delayRead = false, releaseRead;
let requests = [];
const response = () => {
    const fares = state.companions.map(c => c.companion_type === 'other' ? 30 : 24);
    return { success: true, mode: 'trip', trip_id: 20, revision: state.revision,
        companions: structuredClone(state.companions), quote_available: true,
        party_total: 30 + fares.reduce((a, b) => a + b, 0),
        breakdown: { party_total: 30 + fares.reduce((a, b) => a + b, 0), lines: [
            { name: 'Main Rider', role: 'primary', type_label: 'Regular', base_fare: 30, discount_amount: 0, final_fare: 30 },
            ...state.companions.map((c, i) => ({ name: c.full_name, type_label: c.companion_type,
                base_fare: 30, discount_amount: 30 - fares[i], final_fare: fares[i] }))
        ] } };
};
const window = { _activeTripId: 20, events: {}, addEventListener(name, cb) { this.events[name] = cb; } };
const context = { window,
    document: { getElementById: el, createElement: () => new Element(), querySelectorAll: () => typeButtons },
    localStorage: { getItem: () => null, setItem() {} }, URLSearchParams, console, setTimeout: () => {},
    HOME_FARE_UNAVAILABLE: 'Fare unavailable', HOME_FARE_RETRY: 'Retry', activeDiscountType: 'regular',
    resolveFallbackFare: async () => 30, refreshRouteMap: () => {}, maybePromptOnlinePayment: () => {},
    fetch: async (url, options = {}) => {
        requests.push(url);
        if (url === '/passenger/companions') return { json: async () => ({ success: true, profiles: [] }) };
        if (url === '/passenger/trip/select-destination') {
            return { text: async () => JSON.stringify({ success: true, estimated_fare: 30, fare: 30, party: response() }) };
        }
        if (options.method === 'POST') {
            const body = JSON.parse(options.body);
            if (rejectWrite) { rejectWrite = false; return { json: async () => ({ success: false, code: 'STALE_PARTY', message: 'Refresh accepted party.' }) }; }
            assert.equal(body.revision, state.revision, 'Each queued write uses accepted revision');
            await new Promise(resolve => setImmediate(resolve));
            state = { revision: state.revision + 1, companions: body.companions.map((c, i) => ({ ...c, profile_id: c.profile_id || i + 1 })) };
        }
        const accepted = response();
        if (delayRead && options.method !== 'POST') {
            delayRead = false;
            await new Promise(resolve => { releaseRead = resolve; });
        }
        return { json: async () => accepted };
    }
};
vm.runInNewContext(script, context);
vm.runInNewContext(source.slice(source.indexOf('async function showRecordedFare('), source.indexOf('const installPrompt =')), context);
(async () => {
    window.events.DOMContentLoaded();
    await window.companionBeforeBoard();
    assert.equal(el('fareRate').textContent, '₱30.00');
    el('companionNameInput').value = 'Child Rider'; el('companionNameInput').events.keydown({ key: 'Enter', preventDefault() {} });
    await window.companionBeforeBoard();
    assert.equal(el('fareRate').textContent, '₱54.00', 'Add Child immediately displays whole party');
    const select = el('companionAttachList').children[0].children[1];
    select.value = 'other'; select.events.change();
    await window.companionBeforeBoard();
    assert.equal(el('fareRate').textContent, '₱60.00', 'Type edit refreshes backend total');
    el('companionAttachList').children[0].children[2].click();
    await window.companionBeforeBoard();
    assert.equal(el('fareRate').textContent, '₱30.00', 'Remove refreshes solo total');
    typeButtons[1].click();
    el('companionNameInput').value = 'Senior Rider'; el('companionNameInput').events.keydown({ key: 'Enter', preventDefault() {} });
    await window.companionBeforeBoard();
    assert.equal(el('fareRate').textContent, '₱54.00', 'Senior fare included');
    rejectWrite = true;
    el('companionNameInput').value = 'Rejected Rider'; el('companionNameInput').events.keydown({ key: 'Enter', preventDefault() {} });
    await assert.rejects(window.companionBeforeBoard(), /Refresh accepted party/);
    assert.equal(el('fareRate').textContent, '₱54.00', 'Rejected write restores accepted party rather than optimistic charge');
    assert.equal(state.companions.length, 1);
    delayRead = true;
    const oldRead = window.regularPartyRefresh();
    await new Promise(resolve => setImmediate(resolve));
    state.revision++;
    state.companions[0].companion_type = 'other';
    await window.regularPartyRefresh();
    assert.equal(el('fareRate').textContent, '₱60.00', 'Reopen reads changed party from another tab');
    releaseRead(); await oldRead;
    assert.equal(el('fareRate').textContent, '₱60.00', 'Slow old response cannot overwrite newer party estimate');
    el('fareRate').textContent = '₱30.00';
    await window.regularPartyRefresh();
    assert.equal(el('fareRate').textContent, '₱60.00', 'Party restore repairs a primary-only display');
    el('destinationSelect').value = '200';
    el('destinationSelect').selectedIndex = 0;
    el('destinationSelect').options = [{ dataset: { destination: 'Stop C' } }];
    el('destinationSelect').dataset = {};
    await context.updateFareDisplay();
    assert.equal(el('fareRate').textContent, '₱60.00', 'Main destination fare function must use party, not overwrite it with primary 30');
    await context.showRecordedFare({ estimated_fare: 30, party: response() }, 200);
    assert.equal(el('fareRate').textContent, '₱60.00', 'Recorded fare restore must preserve backend party total');
    typeButtons[0].click();
    el('companionNameInput').value = 'Auto Child';
    el('companionEntry').events.focusout({ relatedTarget: null });
    await window.companionBeforeBoard();
    assert.equal(el('fareRate').textContent, '₱84.00', 'Finishing regular companion form autosaves and reprices');
    assert.ok(el('companionSuccess').textContent.includes('Auto Child added'), 'Confirmation only after accepted backend addition');
    assert.ok(el('regularPartyPayment').textContent.includes('Cash'), 'Selected cash method explained with payment status');
    assert.ok(requests.every(url => !url.includes('/attach')), 'UI does not double-submit through legacy attach route');
    console.log('Homepage script syntax and party interaction checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
