/* Profile photo preview: selecting a file in the edit modal updates the
   preview immediately, replaces (and revokes) the previous object URL, and
   ignores change events from unrelated inputs. */
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../public/js/profile-preview.js'), 'utf8');

let checks = 0;
const eq = (actual, expected, message) => { assert.equal(actual, expected, message); checks++; };

const listeners = {};
const previewEl = { src: '/assets/images/passenger/profile.png' };
const inputEl = { id: 'profileInput', files: [{ name: 'photo.jpg', type: 'image/jpeg' }] };
const created = [];
const revoked = [];

const sandbox = {
    document: {
        addEventListener: (type, fn) => { (listeners[type] = listeners[type] || []).push(fn); },
        getElementById: (id) => (id === 'profilePreview' ? previewEl : null)
    },
    URL: {
        createObjectURL: (file) => {
            const url = 'blob:preview-' + file.name;
            created.push(url);
            return url;
        },
        revokeObjectURL: (url) => { revoked.push(url); }
    },
    window: {}
};

vm.runInNewContext(source, sandbox);

assert.ok(listeners.change && listeners.change.length === 1, 'The preview registers one change listener');
checks++;

listeners.change[0]({ target: inputEl });
eq(previewEl.src, 'blob:preview-photo.jpg', 'Selecting a photo shows it in the preview');

inputEl.files = [{ name: 'second.png', type: 'image/png' }];
listeners.change[0]({ target: inputEl });
eq(previewEl.src, 'blob:preview-second.png', 'A new selection replaces the preview');
eq(revoked.length, 1, 'The previous object URL is revoked');
eq(revoked[0], 'blob:preview-photo.jpg', 'The revoked URL is the previous preview');

previewEl.src = 'unchanged';
listeners.change[0]({ target: { id: 'discount_id', files: [{ name: 'other.jpg' }] } });
eq(previewEl.src, 'unchanged', 'Change events from other inputs are ignored');

inputEl.files = [];
listeners.change[0]({ target: inputEl });
eq(previewEl.src, 'unchanged', 'An empty selection changes nothing');

assert.ok(sandbox.window.PassengerProfilePreview && typeof sandbox.window.PassengerProfilePreview.preview === 'function',
    'The preview helper is exposed for reuse');
checks++;

console.log(`${checks} profile preview checks passed.`);
