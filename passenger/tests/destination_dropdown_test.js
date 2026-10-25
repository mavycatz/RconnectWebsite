const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../views/Homepage/Index.php'), 'utf8');
class Element {
    constructor() { this.style = {}; this.dataset = {}; this.children = []; this.events = {}; this.attrs = {}; this.value = ''; this.hidden = true; this.disabled = false; this.options = []; this.selectedIndex = 0; }
    addEventListener(name, callback) { this.events[name] = callback; }
    appendChild(child) { this.children.push(child); }
    set innerHTML(value) { this.children = []; }
    setAttribute(name, value) { this.attrs[name] = value; }
    focus() { this.focused = true; }
    contains() { return true; }
    dispatchEvent(event) { this.dispatched = event.type; }
}
const elements = new Map();
const el = id => { if (!elements.has(id)) elements.set(id, new Element()); return elements.get(id); };
const select = el('destinationSelect');
select.options = [{ value: '', textContent: 'Select Destination' }, { value: '1', textContent: 'Stop Alpha' }, { value: '2', textContent: 'Stop Beta' }];
let warning = false;
const context = { window: {}, document: { getElementById: el, createElement: () => new Element(), addEventListener() {} },
    Event: class { constructor(type) { this.type = type; } }, destinationWarningAcknowledged: true,
    showDestinationWarning: () => { warning = true; }, hideDestinationWarning: () => {}, saveHomepageState() {}, debouncedSaveTripState() {}, updateFareDisplay() {} };
vm.createContext(context);
vm.runInContext(source.slice(source.indexOf('window.openDestinationDropdown ='), source.indexOf("document.getElementById('destinationWarningCancel')")), context);
vm.runInContext(source.slice(source.indexOf('function acknowledgeDestinationWarning(){'), source.indexOf('window.openDestinationDropdown =')), context);
el('destinationAgreementCheck').checked = true;
context.acknowledgeDestinationWarning();
assert.equal(el('destinationDropdownPanel').hidden, false, 'Continue immediately opens the dropdown');
assert.equal(el('destinationSearch').focused, true, 'Search receives focus inside the opened menu');
assert.equal(el('destinationDropdownOptions').children.length, 2);
el('destinationSearch').value = 'beta'; context.window.renderDestinationDropdown();
assert.equal(el('destinationDropdownOptions').children.length, 1);
assert.equal(el('destinationDropdownOptions').children[0].textContent, 'Stop Beta');
assert.equal(select.options.length, 3, 'Searching does not delete the underlying saved option');
el('destinationDropdownOptions').children[0].events.click();
assert.equal(select.value, '2'); assert.equal(select.dispatched, 'change');
assert.equal(el('destinationDropdownPanel').hidden, true);
select.dataset.locked = '1'; select.disabled = true; context.window.renderDestinationDropdown();
context.window.openDestinationDropdown();
assert.equal(el('destinationDropdownToggle').disabled, true);
assert.equal(el('destinationDropdownPanel').hidden, true, 'Locked destination cannot reopen');
console.log('Destination Continue, inside-menu search, selection, and lock checks passed.');
