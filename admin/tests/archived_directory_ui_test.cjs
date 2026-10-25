const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/role-management.js'), 'utf8');
const host = { innerHTML: '' };
const roles = ['driver','inspector','dispatcher','finance_officer','finance_manager','admin','super_admin'];
const context = { state: { status: 'archived', role: 'all', items: roles.map((role, i) => ({
    role, ref: i + 1, name: 'Existing User', user_id: 'ID-' + i, role_label: role,
    status: 'archived', status_label: 'Archived', can_manage: true,
    archive_reason: '<Required reason>', archived_at: '2026-10-13', archived_by: 'Responsible Admin'
})) }, ARCHIVE_ENDPOINTS: Object.fromEntries(roles.map(role => [role, {}])),
    el: () => host, CFG: { roles: {} }, sourceWarningsHtml: () => '',
    esc: value => String(value || '').replaceAll('<', '&lt;').replaceAll('>', '&gt;'),
    initials: () => 'EU', statusClass: () => 'archived', fmtDate: value => value };
vm.createContext(context);
vm.runInContext(source.slice(source.indexOf('function rowActionsHtml('), source.indexOf('function sourceWarningsHtml(')), context);
context.renderList();
assert.ok(host.innerHTML.includes('Archive Details'));
assert.equal((host.innerHTML.match(/data-action="restore"/g) || []).length, 7, 'Every role has its existing restore action');
assert.ok(host.innerHTML.includes('&lt;Required reason&gt;'), 'Reason is displayed safely');
assert.ok(host.innerHTML.includes('Responsible Admin'));
context.state.items[0].archive_reason = null; context.state.items[0].archived_at = null;
context.state.items[0].archived_by = null; context.state.items[0].can_manage = false;
context.renderList();
assert.ok(host.innerHTML.includes('Unavailable'), 'Missing legacy metadata is not invented');
assert.equal((host.innerHTML.match(/data-action="restore"/g) || []).length, 6, 'Unauthorized target has no restore action');
console.log('Archived Users seven-role restore controls, archive metadata, legacy values and permission UI checks passed.');
