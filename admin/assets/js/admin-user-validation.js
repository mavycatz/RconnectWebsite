(function () {
    'use strict';

    var NAME_LETTER = 'A-Za-z\u00C0-\u024F\u1E00-\u1EFF';
    var NAME_RE = new RegExp('^[' + NAME_LETTER + '][' + NAME_LETTER + " .,'\\-]*$");
    var NAME_PUNCT_RE = new RegExp('(^|[^' + NAME_LETTER + "])[.,'\\-]");
    var NAME_TRAILING_RE = /[\s,'\-]$/;
    var USERNAME_RE = /^[A-Za-z0-9._-]{3,30}$/;
    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var LICENSE_RE = /^[A-Za-z0-9][A-Za-z0-9-]{4,29}$/;

    var REQUIRED_NAMES = [
        'fullname',
        'inspectorname',
        'inspectoridentity',
        'department',
        'licensenumber',
        'licensetype',
        'experienceyears',
        'contactnumber',
        'contactno',
        'email',
        'username'
    ];

    var NAME_FIELDS = ['fullname', 'inspectorname'];
    var PHONE_FIELDS = ['contactnumber', 'contactno', 'phone'];

    var REMOTE_TYPES = {
        finance: { username: 'user_username', email: 'user_email' },
        admin: { username: 'user_username', email: 'user_email' },
        driver: { email: 'driver_email', licensenumber: 'driver_license' },
        inspector: { email: 'inspector_email', inspectorid: 'inspector_id' }
    };

    var CHECK_URL = '/admin/api/UserValidation/CheckDuplicate.php';
    var DEBOUNCE_MS = 450;

    function clean(value) {
        return (value === null || value === undefined ? '' : String(value)).trim();
    }

    function fieldKey(field) {
        var key = String(field.name || field.id || '').toLowerCase();
        key = key.replace(/^edit[-_]?/, '');
        return key.replace(/[^a-z0-9]/g, '');
    }

    function labelOf(field) {
        var explicit = field.getAttribute('data-uv-label');
        if (explicit) {
            return explicit;
        }
        var key = String(field.name || field.id || '').replace(/^edit[-_]?/i, '').replace(/[_-]+/g, ' ').trim();
        if (!key) {
            return 'This field';
        }
        return key.charAt(0).toUpperCase() + key.slice(1);
    }

    function isCheckable(field) {
        if (field.type === 'hidden' || field.type === 'file' || field.type === 'submit') {
            return false;
        }
        if (field.disabled || field.readOnly) {
            return false;
        }
        return true;
    }

    function validPhone(value) {
        var compact = value.replace(/[\s\-()]/g, '');
        return /^09\d{9}$/.test(compact) || /^(\+63|63)9\d{9}$/.test(compact);
    }

    function validPassword(value) {
        return value.length >= 8
            && value.length <= 72
            && /[A-Za-z]/.test(value)
            && /\d/.test(value);
    }

    function ruleFor(field, value) {
        var key = fieldKey(field);
        if (key === 'experience') {
            key = 'experienceyears';
        }
        if (key === 'contact') {
            key = 'contactnumber';
        }
        var label = labelOf(field);
        var required = field.hasAttribute('required') || REQUIRED_NAMES.indexOf(key) !== -1;
        var text = clean(value);

        if (text === '') {
            if (key === 'password') {
                return null;
            }
            return required ? label + ' is required.' : null;
        }

        if (key === 'password') {
            if (!validPassword(text)) {
                return 'Password must be 8-72 characters and include at least one letter and one number.';
            }
            var form = field.form || (field.closest ? field.closest('form') : null);
            if (form) {
                var confirm = form.querySelector('[name="password_confirm"], [name="confirm_password"]');
                if (confirm && clean(confirm.value) !== text) {
                    return 'Password confirmation does not match.';
                }
            }
            return null;
        }

        if (PHONE_FIELDS.indexOf(key) !== -1) {
            if (!validPhone(text)) {
                return label + ' must be a valid Philippine mobile number (e.g., 09171234567 or +639171234567).';
            }
            return null;
        }

        if (key === 'email') {
            if (text.length > 150 || !EMAIL_RE.test(text)) {
                return 'Enter a valid email address.';
            }
            return null;
        }

        if (key === 'username') {
            if (!USERNAME_RE.test(text)) {
                return 'Username must be 3-30 characters and use only letters, numbers, dots, underscores, or hyphens.';
            }
            return null;
        }

        if (key === 'licensenumber') {
            if (!LICENSE_RE.test(text)) {
                return 'License number must be 5-30 characters using letters, numbers, or hyphens.';
            }
            return null;
        }

        if (key === 'licenseexpiry') {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(text)) {
                return 'License expiry date is not a valid date.';
            }
            var min = field.getAttribute('min');
            if (min && text < min) {
                return "Driver's license must be valid for more than 3 days.";
            }
            return null;
        }

        if (NAME_FIELDS.indexOf(key) !== -1) {
            if (text.length < 2 || text.length > 100 || !NAME_RE.test(text)) {
                return label + ' may only contain letters, spaces, periods, commas, hyphens, and apostrophes (2-100 characters).';
            }
            if (NAME_PUNCT_RE.test(text)) {
                return label + ' uses punctuation incorrectly (for example "D.", "Jr.", or "Cruz, Jr.").';
            }
            if (NAME_TRAILING_RE.test(text)) {
                return label + ' cannot end with a space or punctuation.';
            }
            return null;
        }

        if (key === 'experienceyears') {
            if (!/^\d+$/.test(text)) {
                return 'Experience years must be a whole number.';
            }
            var years = parseInt(text, 10);
            if (years < 0 || years > 80) {
                return 'Experience years must be between 0 and 80.';
            }
            return null;
        }

        if (field.tagName === 'SELECT') {
            for (var i = 0; i < field.options.length; i++) {
                if (field.options[i].value === text) {
                    return null;
                }
            }
            return 'Select a valid ' + label.toLowerCase() + '.';
        }

        var max = parseInt(field.getAttribute('maxlength') || '0', 10);
        if (max > 0 && text.length > max) {
            return label + ' must not exceed ' + max + ' characters.';
        }

        return null;
    }

    function errorAnchor(field) {
        if (field.closest) {
            var group = field.closest('.input-group');
            if (group) {
                return group;
            }
        }
        return field;
    }

    function errorElement(field, create) {
        var anchor = errorAnchor(field);
        var next = anchor.nextElementSibling;

        if (next && next.classList && next.classList.contains('uv-error')) {
            return next;
        }

        if (!create) {
            return null;
        }

        var element = document.createElement('div');
        element.className = 'uv-error';
        element.style.color = '#dc3545';
        element.style.fontSize = '12px';
        element.style.margin = '4px 0 0';
        element.style.lineHeight = '1.3';
        element.setAttribute('role', 'alert');
        anchor.insertAdjacentElement('afterend', element);
        return element;
    }

    function showError(field, message) {
        var element = errorElement(field, true);
        if (element) {
            element.textContent = message;
            element.style.display = 'block';
        }
        field.setAttribute('aria-invalid', 'true');
    }

    function clearError(field) {
        var element = errorElement(field, false);
        if (element) {
            element.textContent = '';
            element.style.display = 'none';
        }
        field.removeAttribute('aria-invalid');
    }

    function validateField(field) {
        if (!isCheckable(field)) {
            return null;
        }
        return ruleFor(field, field.value);
    }

    function fieldsOf(scope) {
        if (!scope || typeof scope.querySelectorAll !== 'function') {
            return [];
        }
        return scope.querySelectorAll('input, select, textarea');
    }

    function validateAll(scope) {
        var fields = fieldsOf(scope);
        var first = null;
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            if (!isCheckable(field)) {
                continue;
            }
            var message = ruleFor(field, field.value);
            if (message) {
                showError(field, message);
                if (!first) {
                    first = message;
                    try {
                        field.focus();
                    } catch (error) { }
                }
            } else {
                clearError(field);
            }
        }
        return first;
    }

    function remoteTypeFor(context, field) {
        var map = REMOTE_TYPES[context] || {};
        return map[fieldKey(field)] || null;
    }

    function excludeIdFor(scope) {
        var selectors = ['[name="inspector_pk"]', '[name="id"]', '#editDriverId', '#edit_id', '#editAdminId'];
        for (var i = 0; i < selectors.length; i++) {
            var element = scope.querySelector(selectors[i]);
            if (element) {
                var value = clean(element.value);
                if (/^\d+$/.test(value)) {
                    return value;
                }
            }
        }
        return null;
    }

    function scheduleRemoteCheck(field, scope, context) {
        var type = remoteTypeFor(context, field);
        if (!type) {
            return;
        }

        if (field.__uvTimer) {
            clearTimeout(field.__uvTimer);
        }

        field.__uvTimer = setTimeout(function () {
            var value = clean(field.value);

            if (value === '' || validateField(field)) {
                return;
            }

            var requestId = (field.__uvRequest || 0) + 1;
            field.__uvRequest = requestId;

            fetch(CHECK_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: type,
                    value: value,
                    exclude_id: excludeIdFor(scope)
                })
            }).then(function (response) {
                if (!response.ok) {
                    return null;
                }
                return response.json();
            }).then(function (data) {
                if (!data || field.__uvRequest !== requestId) {
                    return;
                }
                if (clean(field.value) !== value || validateField(field)) {
                    return;
                }
                if (data.available === false && data.message) {
                    showError(field, data.message);
                } else {
                    clearError(field);
                }
            }).catch(function () { });
        }, DEBOUNCE_MS);
    }

    function attach(scope, options) {
        if (!scope || scope.__uvAttached) {
            return;
        }
        scope.__uvAttached = true;

        var context = (options && options.context) || scope.getAttribute('data-uv-context') || '';
        var fields = fieldsOf(scope);

        var handler = function (event) {
            var field = event.target;
            if (!field || !isCheckable(field)) {
                return;
            }
            var message = ruleFor(field, field.value);
            if (message) {
                showError(field, message);
                return;
            }
            clearError(field);
            scheduleRemoteCheck(field, scope, context);
        };

        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            if (!isCheckable(field)) {
                continue;
            }
            field.addEventListener('input', handler);
            field.addEventListener('change', handler);
            field.addEventListener('blur', handler);
        }
    }

    function clearScope(scope) {
        var fields = fieldsOf(scope);
        for (var i = 0; i < fields.length; i++) {
            clearError(fields[i]);
        }
    }

    var KNOWN_SCOPES = {
        addDriverForm: 'driver',
        editDriverModal: 'driver',
        inspectorForm: 'inspector',
        editInspectorForm: 'inspector',
        employeeForm: 'finance',
        editEmployeeForm: 'finance',
        adminForm: 'admin',
        editAdminForm: 'admin'
    };

    function autoAttach() {
        var scopes = document.querySelectorAll('[data-uv-context]');
        for (var i = 0; i < scopes.length; i++) {
            attach(scopes[i], { context: scopes[i].getAttribute('data-uv-context') || '' });
        }
        for (var id in KNOWN_SCOPES) {
            if (!Object.prototype.hasOwnProperty.call(KNOWN_SCOPES, id)) {
                continue;
            }
            var element = document.getElementById(id);
            if (element) {
                attach(element, { context: KNOWN_SCOPES[id] });
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoAttach);
    } else {
        autoAttach();
    }

    window.AdminUserValidation = {
        attach: attach,
        clearScope: clearScope,
        validateField: validateField,
        showError: showError,
        clearError: clearError,
        checkForm: validateAll,
        checkScope: validateAll
    };
})();
