/* The register.php boarding/destination UI functions under a Node DOM harness. */
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'passenger/views/OneTime/register.php'), 'utf8');

let checks = 0;
const eq = (actual, expected, message) => { assert.equal(actual, expected, message); checks++; };
const ok = (value, message) => { assert.ok(value, message); checks++; };

// --- Source shape: read-only automatic boarding, no manual boarding list ----
ok(source.includes('id="otBoardingAuto"'), 'Boarding step renders an automatic boarding card');
ok(source.includes('id="otBoardingAutoName"'), 'Boarding card shows the determined stop');
ok(source.includes('id="otBoardingRetry"'), 'Boarding step offers a retry');
ok(!source.includes('id="otBoardingSearch"'), 'Manual boarding search is removed');
ok(!source.includes('renderBoardingStops'), 'Manual boarding list renderer is removed');
ok(!source.includes('otsBoardingList'), 'No manual boarding list container remains');
ok(source.includes('state.destinations'), 'Destination step reads the server destination list');

class Element {
    constructor(id) {
        this.id = id;
        this.style = {};
        this.dataset = {};
        this.attrs = {};
        this.children = [];
        this.events = {};
        this.className = '';
        this.disabled = false;
        this.checked = false;
        this.value = '';
        this._text = '';
        this._html = '';
        this.classList = { values: new Set(), toggle(name, on) { if (on) { this.values.add(name); } else { this.values.delete(name); } }, add(name) { this.values.add(name); }, contains(name) { return this.values.has(name); } };
    }
    set innerHTML(value) { this._html = value; this.children = []; }
    get innerHTML() { return this._html; }
    set textContent(value) { this._text = String(value); }
    get textContent() { return this._text; }
    appendChild(child) { this.children.push(child); }
    addEventListener(name, callback) { this.events[name] = callback; }
    setAttribute(name, value) { this.attrs[name] = value; }
    querySelectorAll() { return []; }
    focus() {}
}

const elements = new Map();
const el = (id) => { if (!elements.has(id)) { elements.set(id, new Element(id)); } return elements.get(id); };

const state = {
    stops: [], destinations: [], busPosition: null, boardingError: null,
    boardingId: 0, boardingName: '', boardingOrder: 0,
    destinationId: 0, destinationName: '', destinationOrder: 0, fare: 0
};
const OT_I18N = {
    boardingUnavailable: 'We could not determine your boarding point. Please retry.',
    boardingSimulated: 'Simulated bus position',
    boardingLiveGps: 'Live bus GPS',
    boardingLocked: 'Boarding point locked at scan',
    localStop: 'Local / Current Stop'
};
const context = {
    state,
    OT_I18N,
    el,
    document: { createElement: (tag) => new Element(tag), querySelectorAll: () => [] },
    destinationSearchTerm: '',
    console
};
vm.createContext(context);

const start = source.indexOf('function applyTripBoarding(data){');
const end = source.indexOf('function syncDeclaredIdField(');
ok(start > 0 && end > start, 'The boarding/destination UI functions can be extracted');
vm.runInContext(source.slice(start, end), context);

const forwardDestinations = [
    ['Baluga/Sto. Niño, Talavera', 106, 7],
    ['Homestead 1, Talavera', 107, 8],
    ['Homestead 2, Talavera', 108, 9],
    ['San Ricardo/Casile, Talavera', 109, 10],
    ['Gen. Luna, Llanera', 110, 11],
    ['Mataas na Kahoy, Llanera', 111, 12],
    ['General Ricarte, Llanera', 112, 13],
    ['Brgy. Mabini, Llanera, Nueva Ecija', 113, 14],
    ['Inanama, Llanera', 114, 15],
    ['Bicos, Rizal', 115, 16],
    ['Mangahan, Rizal', 116, 17],
    ['Aglipay, Rizal', 117, 18],
    ['Del Pilar, Rizal', 118, 19],
    ['Rizal/Pob Sur Terminal', 119, 20]
].map(([destination, id, display_order]) => ({ destination, id, display_order }));

// --- Located bus: automatic read-only boarding + remaining destinations -----
el('otDestinationAgreement').checked = true;
context.applyTripBoarding({
    bus_position: { source: 'driver_location_history', freshness: 'FRESH', age_seconds: 10, matched_distance_km: 0.02 },
    boarding: { id: 105, destination: 'Pinagpanaan, Talavera', display_order: 6 },
    destinations: forwardDestinations,
    boarding_error: null
});

eq(state.boardingId, 105, 'The server boarding id is applied');
eq(state.boardingName, 'Pinagpanaan, Talavera', 'The server boarding name is applied');
eq(el('otBoardingAuto').style.display, 'block', 'The automatic boarding card is visible');
eq(el('otBoardingAutoName').textContent, 'Pinagpanaan, Talavera', 'The boarding card names the determined stop');
ok(el('otBoardingAutoMeta').textContent.includes('Live bus GPS'), 'The boarding card shows the live GPS source');
ok(el('otBoardingAutoMeta').textContent.includes('FRESH'), 'The boarding card shows the freshness');
ok(el('otBoardingAutoMeta').textContent.includes('20 m'), 'The boarding card shows the match distance');
eq(el('otBoardingError').style.display, 'none', 'No boarding error is shown');
eq(el('otBoardingRetry').style.display, 'none', 'No retry is offered when boarding is resolved');
eq(el('otBoardingNext').disabled, false, 'Continue is enabled when boarding is resolved');
eq(el('otBoardingPillText').textContent, 'Pinagpanaan, Talavera', 'The destination step pill shows the boarding stop');

const buttons = el('otDestinationList').children;
eq(buttons.length, 14, 'Only the remaining-ahead stops are listed');
const names = buttons.map((button) => button.children[1].textContent);
ok(!names.includes('Pinagpanaan, Talavera'), 'The boarding stop itself is not a destination');
ok(!names.includes('Valdefuente'), 'A passed stop is not a destination');
eq(names[0], 'Rizal/Pob Sur Terminal', 'Destinations keep the existing far-first display order');
eq(el('otDestinationEmpty').style.display, 'none', 'The empty-destinations message is hidden');
eq(el('otDestinationNext').disabled, false, 'Destination continue follows the agreement + list gates');

// --- Unavailable bus position: error card with retry, continue blocked ------
context.applyTripBoarding({
    bus_position: { source: null, freshness: 'STALE' },
    boarding: null,
    destinations: [],
    boarding_error: { code: 'BUS_LOCATION_STALE', message: 'The bus location is out of date. Please retry in a moment.' }
});

eq(state.boardingId, 0, 'No boarding id is applied when the position is unusable');
eq(el('otBoardingAuto').style.display, 'none', 'The boarding card is hidden on error');
eq(el('otBoardingError').style.display, 'block', 'The boarding error is shown');
eq(el('otBoardingError').textContent, 'The bus location is out of date. Please retry in a moment.', 'The server error message is displayed');
eq(el('otBoardingRetry').style.display, '', 'Retry is offered on error');
eq(el('otBoardingNext').disabled, true, 'Continue is blocked on error');
eq(el('otDestinationList').children.length, 0, 'No destinations are listed without a boarding point');
eq(el('otDestinationEmpty').style.display, 'block', 'The empty-destinations message is shown');
eq(el('otDestinationNext').disabled, true, 'Destination continue is disabled without destinations');

// --- Simulated bus position is labelled like a live source -------------------
context.applyTripBoarding({
    bus_position: { source: 'simulation', freshness: 'FRESH', age_seconds: 0, matched_distance_km: 0.03 },
    boarding: { id: 107, destination: 'Homestead 1, Talavera', display_order: 8 },
    destinations: forwardDestinations.slice(2),
    boarding_error: null
});

ok(el('otBoardingAutoMeta').textContent.includes('Simulated bus position'), 'The simulated source is labelled');
eq(el('otBoardingAutoName').textContent, 'Homestead 1, Talavera', 'The simulated boarding stop is shown');
eq(el('otBoardingRetry').style.display, 'none', 'Retry is hidden again after recovery');

// --- Last stop: the same-stop destination is preselected ---------------------
context.applyTripBoarding({
    bus_position: { source: 'driver_location_history', freshness: 'FRESH', age_seconds: 4, matched_distance_km: 0.01 },
    boarding: { id: 119, destination: 'Rizal/Pob Sur Terminal', display_order: 20 },
    destinations: [{ id: 119, destination: 'Rizal/Pob Sur Terminal', display_order: 20, same_as_boarding: true }],
    boarding_error: null
});

eq(state.boardingId, 119, 'The last-stop boarding is applied');
eq(state.destinationId, 119, 'The same-stop destination is preselected');
eq(state.destinationName, 'Rizal/Pob Sur Terminal', 'The same-stop destination names the boarding stop');
eq(el('otDestinationList').children.length, 1, 'The same-stop destination is the only option listed');
ok(el('otDestinationList').children[0].classList.contains('active'), 'The same-stop destination is shown selected');
eq(el('otDestinationEmpty').style.display, 'none', 'The empty-destinations message stays hidden');
eq(el('otDestinationNext').disabled, false, 'Continue unlocks for the same-stop destination');

// --- The registered boarding point is labelled as locked --------------------
context.applyTripBoarding({
    bus_position: { source: 'driver_location_history', freshness: 'FRESH', age_seconds: 45, matched_distance_km: 0.02, locked: true },
    boarding: { id: 105, destination: 'Pinagpanaan, Talavera', display_order: 6 },
    destinations: forwardDestinations,
    boarding_error: null
});
eq(state.boardingId, 105, 'The registered boarding id is applied');
ok(el('otBoardingAutoMeta').textContent.includes('Boarding point locked'), 'The registered boarding point is labelled as locked');
ok(el('otBoardingAutoMeta').textContent.includes('Live bus GPS'), 'The locked boarding still shows its scan source');

// --- The boarding stop is offered as the local option -----------------------
context.applyTripBoarding({
    bus_position: { source: 'driver_location_history', freshness: 'FRESH', age_seconds: 5, matched_distance_km: 0.02 },
    boarding: { id: 105, destination: 'Pinagpanaan, Talavera', display_order: 6 },
    destinations: [{ id: 105, destination: 'Pinagpanaan, Talavera', display_order: 6, is_boarding: true }].concat(forwardDestinations),
    boarding_error: null
});

const localButton = el('otDestinationList').children.find((button) => String(button.children[1].textContent).includes('(Local / Current Stop)'));
ok(localButton, 'The boarding stop is offered as the local option');
eq(state.destinationId, 0, 'The local option is not preselected while other stops remain');

// --- At the last stop the local option is the only one and is preselected ---
context.applyTripBoarding({
    bus_position: { source: 'driver_location_history', freshness: 'FRESH', age_seconds: 5, matched_distance_km: 0.02 },
    boarding: { id: 119, destination: 'Rizal/Pob Sur Terminal', display_order: 20 },
    destinations: [{ id: 119, destination: 'Rizal/Pob Sur Terminal', display_order: 20, is_boarding: true, same_as_boarding: true }],
    boarding_error: null
});
eq(state.destinationId, 119, 'The last-stop local option is preselected');
ok(String(el('otDestinationList').children[0].children[1].textContent).includes('(Local / Current Stop)'), 'The last-stop option is labelled local');

console.log(`${checks} one-time boarding UI checks passed.`);
