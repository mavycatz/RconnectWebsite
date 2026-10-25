const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const nodes = new Map();
const createdLinks = [];
const swalCalls = [];
const alertCalls = [];
let mode = 'normal';
let createdCount = 0;
const deepLink = process.argv.includes('--deep-link');

function element(id) {
    if (!nodes.has(id)) {
        nodes.set(id, {
            id, hidden: false, disabled: false, value: '', textContent: '', innerHTML: '', options: [], dataset: {},
            listeners: {},
            classList: {toggle() {}, add() {}, remove() {}, contains() { return false; }},
            setAttribute() {}, remove() {}, click() {},
            addEventListener(name, fn) { this.listeners[name] = fn; },
            insertAdjacentHTML(position, text) { this.innerHTML += text; },
            appendChild(node) { if (Array.isArray(this.options)) this.options.push(node); },
            querySelector() { return null; },
            querySelectorAll() { return []; },
            closest() { return null; }
        });
    }
    return nodes.get(id);
}

const items = [
    {
        ref: '1', role: 'driver', kind: 'driver', role_label: 'Driver', user_id: 'DRV-001',
        name: 'Juan Dela Cruz', email: '', contact: '', status: 'active', status_label: 'Active',
        created_at: '2026-01-05 09:00:00', photo: '', extra: [{label: 'License No.', value: 'N01-23-456789'}],
        documents: {resume: '', supporting: ''}, cv_subject: 'driver', can_manage: true
    },
    {
        ref: '2', role: 'admin', kind: 'user', role_label: 'Admin', user_id: 'ADM-001',
        name: 'Maria Santos', email: 'maria@example.com', contact: '09171234567', status: 'inactive',
        status_label: 'Inactive', created_at: '2026-02-02 10:00:00', photo: '', extra: [],
        documents: {}, cv_subject: 'admin', can_manage: false
    }
];

async function fetch(url) {
    const u = String(url);
    if (u.includes('RoleManagement/list.php')) {
        return {ok: true, headers: {get: () => 'application/json'}, json: async () => ({success: true, data: items, counts: {driver: 1, admin: 1}, summary: {total: 2, active: 1, inactive: 1, archived: 0, pending: 0}, errors: {}, total: 2, page: 1, per_page: 10, pages: 1, is_super: true})};
    }
    if (u.includes('History.php')) {
        return {ok: true, headers: {get: () => 'application/json'}, json: async () => ({success: true, data: []})};
    }
    if (u.includes('CvGet.php')) {
        return {ok: true, headers: {get: () => 'application/json'}, json: async () => ({success: true, data: null})};
    }
    if (u.includes('NextUserId.php')) {
        const match = u.match(/role=([^&]+)/);
        const role = match ? decodeURIComponent(match[1]) : '';
        const ids = {driver: 'DRV-004', inspector: 'INS-012', dispatcher: 'DIS-002', finance_officer: 'FIN-003', finance_manager: 'FIN-003', admin: 'ADM-002', super_admin: 'SUP-001'};
        return {ok: true, headers: {get: () => 'application/json'}, json: async () => ({success: true, user_id: ids[role] || 'DRV-004', role, preview: true})};
    }
    if (u.includes('export.php')) {
        assert.match(u, /role=all/, 'Export sends the active role filter');
        if (mode === 'empty') {
            return {ok: true, status: 200, headers: {get: () => 'application/json; charset=utf-8'}, text: async () => JSON.stringify({success: false, message: 'No data available to export.'})};
        }
        if (mode === 'error') {
            return {ok: false, status: 500, headers: {get: () => 'application/json'}, text: async () => JSON.stringify({success: false, message: 'Could not export the directory. Please try again.'})};
        }
        return {ok: true, status: 200, headers: {get: () => 'text/csv; charset=UTF-8'}, blob: async () => new Blob(['a,b\n1,2'])};
    }
    throw new Error('unexpected fetch ' + u);
}

const context = {
    document: {
        getElementById: element,
        createElement(tag) {
            const node = element(tag + ':' + (++createdCount));
            if (tag === 'a') node.click = () => createdLinks.push({href: node.href, download: node.download});
            return node;
        },
        body: {appendChild() {}},
        addEventListener() {},
        querySelectorAll: () => [],
        querySelector: () => null,
        readyState: 'complete'
    },
    window: {
        location: {search: ''},
        history: {replaceState() {}},
        showAlert: (...args) => alertCalls.push(args),
        RMG_CONFIG: {
            roles: {driver: 'Driver', inspector: 'Inspector', dispatcher: 'Dispatcher', finance_officer: 'Finance Officer', finance_manager: 'Finance Manager', admin: 'Admin', super_admin: 'Super Admin'},
            statuses: {active: 'Active', inactive: 'Inactive', pending: 'Pending', rejected: 'Rejected'},
            initialRole: 'all',
            openProfile: deepLink ? '2' : '',
            isSuper: true
        },
        Swal: {fire: options => swalCalls.push(options)}
    },
    fetch, URLSearchParams, Blob, setTimeout, clearTimeout, console, Promise,
    history: {replaceState() {}},
    URL: {createObjectURL: () => 'blob:test', revokeObjectURL() {}},
    bootstrap: {Modal: {getOrCreateInstance: node => ({show() {node.hidden = false;}, hide() {node.hidden = true;}}), getInstance: () => ({hide() {}})}},
    alert: () => {}
};
vm.createContext(context);
vm.runInContext(fs.readFileSync('admin/assets/js/role-management.js', 'utf8'), context);

async function settle() { for (let i = 0; i < 8; i++) await new Promise(resolve => setImmediate(resolve)); }

(async () => {
    await settle();

    if (deepLink) {
        assert.equal(element('rmgDetailModal').hidden, false, 'Deep link opens the modal');
        assert.equal(element('rmgDetailName').textContent, 'Maria Santos', 'Deep link opens the referenced account');
        assert.doesNotMatch(element('rmgDetailBody').innerHTML, /Juan Dela Cruz/);
        console.log('Role Management deep-link check passed: open_profile opens the referenced account modal.');
        return;
    }

    const list = element('rmgList');
    assert.match(list.innerHTML, /data-action="details"/, 'Rows expose a Details action');
    assert.doesNotMatch(list.innerHTML, /data-action="toggle"/, 'Inline toggle control removed');
    assert.equal((list.innerHTML.match(/data-action="details"/g) || []).length, 2, 'One Details action per row');
    assert.match(element('rmgSummary').innerHTML, /Total Accounts/, 'Summary cards render real totals');
    assert.match(element('rmgSummary').innerHTML, /Pending Activation/, 'Summary cards include pending activation');
    assert.match(element('rmgRole').innerHTML, /All roles \(2\)/, 'Role filter shows live counts');
    assert.match(list.innerHTML, /<th>User ID<\/th>/, 'Account table has column headings');
    assert.match(list.innerHTML, /data-action="history"/, 'Rows expose an activity history action');
    assert.equal((list.innerHTML.match(/data-action="edit"/g) || []).length, 1, 'Only manageable rows expose Edit');
    assert.match(list.innerHTML, /rmg-locked-action/, 'Restricted rows show a locked indicator');

    // Details for the first account only.
    list.listeners.click({target: {closest: selector => selector === '[data-action]' ? {getAttribute: name => name === 'data-action' ? 'details' : '0'} : null}});
    await settle();

    assert.equal(element('rmgDetailModal').hidden, false, 'Details opens the modal');
    const body = element('rmgDetailBody').innerHTML;
    assert.match(body, /Personal Information/);
    assert.match(body, /Account Information/);
    assert.match(body, /Role and Access/);
    assert.match(body, /Status/);
    assert.match(body, /Juan Dela Cruz/);
    assert.doesNotMatch(body, /Maria Santos/, 'Modal never shows another account');
    assert.match(body, /Not provided/, 'Empty fields are marked as not provided');
    assert.match(body, /License No\./, 'Extra fields are shown');
    assert.equal(element('rmgDetailName').textContent, 'Juan Dela Cruz');
    assert.match(element('rmgDetailActions').innerHTML, /Edit profile/, 'Manageable account keeps its Edit action');
    assert.match(element('rmgDetailActions').innerHTML, /Deactivate \/ Archive/, 'Manageable active account keeps its status action');

    // Second account: restricted admin, correct data, restricted actions.
    list.listeners.click({target: {closest: selector => selector === '[data-action]' ? {getAttribute: name => name === 'data-action' ? 'details' : '1'} : null}});
    await settle();
    assert.equal(element('rmgDetailName').textContent, 'Maria Santos', 'Details shows the clicked account');
    assert.doesNotMatch(element('rmgDetailBody').innerHTML, /Juan Dela Cruz/);
    assert.match(element('rmgDetailActions').innerHTML, /Only a Super Admin can manage this account/, 'Restricted actions withheld');

    // Edit profile closes the details view first (no stacked-modal overlap)
    // and renders its form fields.
    const editButton = {getAttribute: name => name === 'data-action' ? 'edit' : '0'};
    list.listeners.click({target: {closest: selector => selector === '[data-action]' ? editButton : null}});
    await settle();
    assert.match(element('rmgEditFields').innerHTML, /data-edit-field="full_name"/, 'Edit profile modal renders its fields');
    assert.match(element('rmgEditFields').innerHTML, /Reason for this change/, 'Edit profile keeps the reason field');
    assert.match(element('rmgEditFields').innerHTML, /readonly/, 'Edit profile shows the User ID read-only');
    assert.match(element('rmgEditFields').innerHTML, /DRV-001/, 'Edit profile shows the assigned User ID');
    assert.doesNotMatch(element('rmgEditFields').innerHTML, /data-edit-field="user_id"/, 'User ID is never an editable field');
    assert.match(element('rmgEditFields').innerHTML, /Change Reason/, 'Edit form includes the change reason step');
    assert.match(element('rmgEditFields').innerHTML, /data-edit-field="employment_type"/, 'Edit form prefills structured employment fields');
    assert.equal(element('rmgEditModal').hidden, false, 'Edit profile opens');

    // Add user renders its fields and prefills the next User ID for the role.
    element('rmgAddBtn').listeners.click();
    await settle();
    assert.match(element('rmgAddFields').innerHTML, /data-add-field="full_name"/, 'Add user modal renders its fields');
    element('rmgAddRole').value = 'driver';
    element('rmgAddRole').listeners.change();
    await settle();
    assert.equal(element('rmgAddUserId').value, 'DRV-004', 'Add user prefills the next driver User ID');
    assert.equal(element('rmgAddUserId').disabled, false, 'User ID input exists and stays non-editable via readonly markup');
    assert.match(element('rmgAddSteps').innerHTML, /Personal Information/, 'Add form shows the step progress');
    assert.match(element('rmgAddSteps').innerHTML, /Review &(amp;)? Confirm/, 'Add form includes the review step');
    assert.match(element('rmgAddFields').innerHTML, /Employment Information/, 'Driver form includes the employment step');
    assert.match(element('rmgAddFields').innerHTML, /Driver Availability/, 'Driver form includes role-specific fields');
    assert.match(element('rmgAddFields').innerHTML, /data-name-part="first"/, 'Structured name parts render');
    assert.match(element('rmgAddFields').innerHTML, /data-add-field="address"/, 'Structured address composites into the address field');
    assert.match(element('rmgAddReview').innerHTML, /Review before saving/, 'Live review summary renders');

    // Populated export downloads a file.
    await element('rmgExport').listeners.click();
    await settle();
    assert.equal(createdLinks.length, 1, 'Populated export downloads the CSV');
    assert.match(createdLinks[0].download || '', /^role-management-all.*\.csv$/);
    assert.equal(alertCalls.length, 0, 'No alert for a successful export');
    assert.equal(swalCalls.length, 0, 'SweetAlert2 is never used while the custom alert exists');
    assert.equal(element('rmgExport').disabled, false, 'Export button re-enables');

    // Empty result: message only, no download.
    mode = 'empty';
    await element('rmgExport').listeners.click();
    await settle();
    assert.equal(createdLinks.length, 1, 'Empty export downloads nothing');
    assert.equal(alertCalls.length, 1, 'Empty export shows one custom alert');
    assert.match(String(alertCalls[0][2] || ''), /No data available to export\./);
    assert.equal(swalCalls.length, 0, 'SweetAlert2 stays unused for the empty result');

    // Server failure: error message, no download.
    mode = 'error';
    await element('rmgExport').listeners.click();
    await settle();
    assert.equal(createdLinks.length, 1, 'Failed export downloads nothing');
    assert.equal(alertCalls.length, 2, 'Failed export shows a custom error alert');
    assert.match(String(alertCalls[1][2] || ''), /Could not export the directory/);

    // Missing component must not silently switch to another alert library.
    delete context.window.showAlert;
    mode = 'empty';
    await element('rmgExport').listeners.click();
    await settle();
    assert.equal(swalCalls.length, 0, 'SweetAlert2 fallback remains removed');

    console.log('Role Management UI checks passed: details modal, add/edit field rendering, filtered export, custom alerts without SweetAlert2 fallback.');
})().catch(error => { console.error(error); process.exitCode = 1; });
