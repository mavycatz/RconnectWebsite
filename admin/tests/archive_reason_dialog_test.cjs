const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
class Element {
    constructor() { this.style = {}; this.children = []; this.value = ''; this.classList = { add() {}, remove() {} }; }
    set textContent(value) { this.text = value; this.children = []; }
    get textContent() { return this.text; }
    appendChild(child) { this.children.push(child); }
    setAttribute() {}
    focus() {}
}
const nodes = new Map();
const node = key => { if (!nodes.has(key)) nodes.set(key, new Element()); return nodes.get(key); };
const overlay = new Element(); overlay.querySelector = node;
const context = { window: {}, console, document: { getElementById: () => overlay, createElement: () => new Element() } };
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(root, '../public/login/custom-alert.js'), 'utf8'), context);
context.window.showConfirm = context.showConfirm;
vm.runInContext(fs.readFileSync(path.join(root, 'assets/js/management-alert.js'), 'utf8'), context);
(async () => {
    let resolved = false;
    const result = context.window.adminPromptReason('Archive record?', 'This will be recorded.', 'Archive', 500).then(v => { resolved = true; return v; });
    const input = node('.custom-alert-message').children[1];
    input.value = '   '; node('.custom-alert-btn').onclick(); await Promise.resolve();
    assert.equal(resolved, false, 'Whitespace reason cannot complete confirmation');
    input.value = 'abc'; node('.custom-alert-btn').onclick(); await Promise.resolve();
    assert.equal(resolved, false, 'Short reason cannot complete confirmation');
    input.value = '  Valid archive reason  '; node('.custom-alert-btn').onclick();
    assert.equal(await result, 'Valid archive reason', 'Valid confirmation returns trimmed reason');
    const cancelled = context.window.adminPromptReason('Archive record?', 'This will be recorded.', 'Archive');
    node('.custom-alert-btn-cancel').onclick();
    assert.equal(await cancelled, null, 'Cancel returns no reason and permits no write');
    console.log('Real custom overlay mandatory reason, valid confirmation, and cancellation checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
