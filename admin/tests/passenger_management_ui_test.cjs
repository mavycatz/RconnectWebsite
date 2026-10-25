const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../views/passenger');
class Element {
    constructor() { this.style = {}; this.value = ''; this.events = {}; this.classList = { toggle() {}, add() {}, remove() {} }; this.innerHTML = ''; this.textContent = ''; }
    addEventListener(name, cb) { this.events[name] = cb; }
    setAttribute() {}
    querySelector() { return new Element(); }
    querySelectorAll() { return []; }
}
const elements = new Map();
const el = id => { if (!elements.has(id)) elements.set(id, new Element()); return elements.get(id); };
const requests = [], modals = new Map();
const getModal = node => { if (!modals.has(node)) modals.set(node, { shown: false, show() { this.shown = true; }, hide() { this.shown = false; } }); return modals.get(node); };
let response = { success: true, total: 1, page: 1, pages: 1, per_page: 15, data: [{ id: 42, source: 'live', passenger_code: 'STD-042',
    full_name: 'Existing Passenger', email: 'passenger@example.com', phone: '09171234567', discount_type: 'Regular',
    status: 'Active', verification: 'Verified', total_trips: 2, registered_date: '2026-10-13', last_trip: '2026-10-13 08:00:00' }] };
const context = { window: { PSGR_CSRF: 'fixture', addEventListener() {}, open() {} },
    document: { getElementById: el, querySelectorAll: () => [], querySelector: () => new Element(), addEventListener() {}, body: new Element() },
    bootstrap: { Modal: { getInstance: getModal, getOrCreateInstance: getModal } },
    URLSearchParams, console, setTimeout, clearTimeout, FormData,
    fetch: async url => { requests.push(String(url));
        return { ok: true, json: async () => String(url).includes('get_passenger_detail') ? { success: true, data: response.data[0] }
            : String(url).includes('get_passenger_trips') ? { success: true, data: [] }
                : { ...response, per_page: Number(new URLSearchParams(String(url).split('?')[1]).get('per_page')) || response.per_page } }; }
};
vm.createContext(context);
for (const file of ['sections/passenger-cards.php','sections/stats.php','scanner.php','layout/scripts.php']) {
    const src = fs.readFileSync(path.join(root, file), 'utf8');
    for (const match of src.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)) {
        const script = match[1].replace(/<\?(?:php|=)[\s\S]*?\?>/g, 'null');
        new vm.Script(script);
        if (file === 'sections/passenger-cards.php') vm.runInContext(script, context);
    }
}
const settle = async () => { for (let i=0;i<5;i++) await new Promise(resolve => setImmediate(resolve)); };
(async () => {
    el('searchInput').value = 'Existing'; el('filterDiscount').value = 'Regular';
    context.loadPassengers(); await settle();
    assert.ok(requests[0].includes('search=Existing') && requests[0].includes('discount_type=Regular'));
    assert.ok(el('psgrCount').innerHTML.includes('1 passenger') && el('psgrCount').innerHTML.includes('bi-people'));
    assert.ok(el('psgrBody').innerHTML.includes('STD-042') && el('psgrBody').innerHTML.includes('rmg-avatar'));
    assert.ok(el('psgrBody').innerHTML.includes('data-label="Passenger ID"'));
    for (const action of ['viewProfile','editPassenger','viewTrips','openStatus']) assert.ok(el('psgrBody').innerHTML.includes(action + '(0)'));
    assert.ok(el('psgrPag').innerHTML.includes('rmg-pg-btn'));
    context.psgrSetSort('p.created_at|DESC'); await settle();
    assert.ok(requests.some(url => url.includes('sort_by=p.created_at') && url.includes('sort_dir=DESC')));
    context.psgrChangePerPage('25'); await settle(); assert.equal(el('psgrPerPageSel').value, '25');
    context.viewProfile(0); await settle();
    assert.equal(getModal(el('psgrDetailsPanel')).shown, true);
    assert.equal(el('psgrPanelTitle').textContent, 'Existing Passenger');
    assert.ok(el('panelTabProfile').innerHTML.includes('passenger@example.com'));
    context.psgrPanelTab('payments'); assert.equal(el('panelTabPayments').style.display, 'block');
    context.psgrClosePanel(); assert.equal(getModal(el('psgrDetailsPanel')).shown, false);
    el('filterStatus').value = 'Archived'; context.loadPassengers(); await settle();
    assert.ok(requests.some(url => url.includes('archived=1')));
    assert.ok(el('psgrBody').innerHTML.includes('restorePassenger(0)'));
    response = { success:true,total:0,data:[],page:1,pages:1,per_page:25 }; context.loadPassengers(); await settle();
    assert.equal(el('psgrEmpty').style.display, 'flex'); assert.equal(el('psgrTableWrap').style.display, 'none');
    console.log('Passenger directory filtering, shared rows/actions, sort, pagination, profile Modal/tabs, archive and empty-state checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
