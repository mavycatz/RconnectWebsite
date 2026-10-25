const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const nodes = new Map();
const node = selector => { if (!nodes.has(selector)) nodes.set(selector, { style: {}, classList: { add() {}, remove() {} } }); return nodes.get(selector); };
const overlay = { querySelector: node, classList: { add() {}, remove() {} } };
const context = { document: { getElementById: () => overlay } };
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(root, '../public/login/custom-alert.js'), 'utf8'), context);
const announcement = fs.readFileSync(path.join(root, 'views/announcement/index.php'), 'utf8');
vm.runInContext(announcement.slice(announcement.indexOf('function annAlert('), announcement.indexOf('function escapeHtml(')), context);
context.annAlert('success', 'Saved', 'Announcement posted');
assert.equal(node('.custom-alert-title').textContent, 'Saved');
context.annAlert('error', 'Failed', 'Migration required');
assert.equal(node('.custom-alert-message').textContent, 'Migration required');
let deleted = 0;
context.annConfirm('Delete?', 'Confirm deletion', () => deleted++);
node('.custom-alert-btn-cancel').onclick(); assert.equal(deleted, 0);
context.annConfirm('Delete?', 'Confirm deletion', () => deleted++);
node('.custom-alert-btn').onclick(); assert.equal(deleted, 1);
for (const page of ['announcement', 'role-management', 'bus']) {
    const src = fs.readFileSync(path.join(root, `views/${page}/index.php`), 'utf8');
    assert.equal((src.match(/layout\/custom-alert.php/g) || []).length, 1);
    assert.ok(src.indexOf('layout/custom-alert.php') < src.indexOf('/public/login/custom-alert.js'));
    assert.ok(!/sweetalert|Swal/.test(src));
}
for (const file of ['role-management.js', 'bus-management.js']) {
    const src = fs.readFileSync(path.join(root, 'assets/js', file), 'utf8');
    new vm.Script(src);
    assert.ok(!/window\.(?:Swal|alert|confirm)\b/.test(src));
}
console.log('Existing custom alert success/error/confirm/cancel and all scoped page integrations passed.');
