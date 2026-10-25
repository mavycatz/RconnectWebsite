const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(path.join(__dirname, '../views/OneTime/register.php'), 'utf8');
for (const match of source.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)) {
    new vm.Script(match[1].replace(/<\?(?:php|=)[\s\S]*?\?>/g, 'null'));
}
assert.ok(!source.includes('id="otCompanionAdd"'), 'One-Time separate Add button removed');
const regularSource = fs.readFileSync(path.join(__dirname, '../views/Homepage/Index.php'), 'utf8');
assert.ok(!regularSource.includes('id="companionAddBtn"'), 'Regular separate Add button removed');
class Element {
    constructor() { this.style = {}; this.events = {}; this.children = []; this.value = ''; }
    set innerHTML(value) { this.children = []; }
    addEventListener(name, fn) { this.events[name] = fn; }
    appendChild(child) { this.children.push(child); }
    setAttribute() {}
    focus() {}
    contains(value) { return value === this; }
}
const elements = new Map();
const el = id => { if (!elements.has(id)) elements.set(id, new Element()); return elements.get(id); };
let quotes = 0, summaries = 0;
const state = { companions: [] };
const context = { state, el, document: { createElement: () => new Element() },
    recalcPartyFare: () => { quotes++; }, renderSummary: () => { summaries++; }, otTypeShort: type => type };
vm.runInNewContext(source.slice(source.indexOf('function otAddCompanion(){'), source.indexOf("el('otConfirmBtn').addEventListener")), context);
const enter = () => el('otCompanionName').events.keydown({ key: 'Enter', preventDefault() {} });
el('otCompanionType').value = 'child'; el('otCompanionName').value = 'Child Rider'; enter();
assert.equal(state.companions.length, 1); assert.equal(state.companions[0].companion_type, 'child');
assert.equal(el('otCompanionName').value, '');
assert.ok(el('otCompanionSuccess').textContent.includes('2 passengers'));
el('otCompanionType').value = 'pwd'; el('otCompanionName').value = 'PWD Rider';
el('otCompanionBlock').events.focusout({ relatedTarget: el('otDetailsNext') });
assert.equal(state.companions.length, 2); assert.equal(state.companions[1].companion_type, 'pwd');
assert.equal(el('otCompanionList').children.length, 2);
el('otCompanionName').value = 'PWD Rider'; enter();
assert.equal(state.companions.length, 2, 'Duplicate is rejected');
assert.ok(el('otCompanionError').textContent.includes('already'));
el('otCompanionName').value = '123'; enter();
assert.equal(state.companions.length, 2, 'Invalid required name rejected');
el('otCompanionType').value = 'senior'; el('otCompanionName').value = 'Senior Rider'; enter();
assert.equal(state.companions.length, 3);
assert.equal(quotes, 3, 'Every successful auto-add triggers fare recalculation');
assert.equal(summaries, 3, 'Trip summary is updated on every addition');
context.otRemoveCompanion(1);
assert.equal(state.companions.length, 2); assert.equal(quotes, 4);
console.log('Companion Enter/completion submission, PWD type, duplicate validation, confirmation, summary and fare refresh checks passed.');
