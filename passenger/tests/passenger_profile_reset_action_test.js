const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../views/ProfilePage/index.php'), 'utf8');

const start = source.indexOf('function clearCachedAppData(){');
const end = source.indexOf('/* end clearCachedAppData */');
assert.ok(start !== -1 && end !== -1, 'clearCachedAppData is defined in the Profile page');

let resetCalled = 0;
let confirmDialog = null;
let fetchCall = null;
let reloadCalled = 0;

const context = {
    window: {
        PassengerStorage: { reset: function () { resetCalled++; } },
        showConfirm: function (title, body, onConfirm, onCancel, confirmText) {
            confirmDialog = { title, body, onConfirm, onCancel, confirmText };
        },
        location: { reload: function () { reloadCalled++; } }
    },
    PROFILE_I18N: {
        clearCacheTitle: 'Clear cached app data',
        clearCacheBody: 'This removes locally cached trip and payment information on this device. Your account, trip history, and payment records are kept.',
        clearCacheConfirm: 'Clear data'
    },
    fetch: function (url, options) {
        fetchCall = { url, options };
        return Promise.resolve({});
    },
    console
};

vm.runInNewContext(source.slice(start, end + '/* end clearCachedAppData */'.length), context);

assert.equal(typeof context.clearCachedAppData, 'function', 'handler is defined in the profile script');
context.clearCachedAppData();

assert.ok(confirmDialog, 'a confirmation dialog is shown before clearing');
assert.equal(confirmDialog.title, 'Clear cached app data', 'dialog title matches');
assert.match(confirmDialog.body, /account, trip history, and payment records are kept/i, 'dialog states server records are preserved');
assert.equal(resetCalled, 0, 'nothing is cleared before the passenger confirms');

confirmDialog.onConfirm();

(async () => {
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(resetCalled, 1, 'confirmation runs the PassengerStorage reset');
    assert.ok(fetchCall, 'server trip state clear is requested');
    assert.equal(fetchCall.url, '/passenger/trip/clear-state', 'the existing clear-state endpoint is used');
    assert.equal(fetchCall.options.method, 'POST', 'clear-state is a POST');
    assert.equal(reloadCalled, 1, 'the page reloads after the reset');

    console.log('Profile clear-cached-app-data action: confirmation, registry reset, clear-state POST, and reload checks passed.');
})();
