(function () {
    'use strict';

    var CFG = window.RMG_CONFIG || {};
    var state = {
        role: CFG.initialRole || 'all',
        search: '',
        status: '',
        department: '',
        employment: '',
        createdFrom: '',
        createdTo: '',
        sort: 'newest',
        page: 1,
        perPage: 10,
        items: [],
        counts: {},
        summary: {},
        total: 0,
        pages: 1,
        isSuper: !!CFG.isSuper,
        detailIndex: null,
        detailTab: 'overview',
        openProfile: String(CFG.openProfile || ''),
        cvCache: {},
        historyCache: {},
        sourceErrors: {},
        busy: false,
        userIdRequest: 0,
        addUserId: '',
        submittingAdd: false,
        submittingEdit: false,
        formDirty: {add: false, edit: false},
        formForceClose: {add: false, edit: false},
        wizard: {add: 0, edit: 0},
        addValues: null,
        editValues: null,
        editMeta: null,
        geoCache: {provinces: null, cities: {}, barangays: {}}
    };

    var UPDATE_ENDPOINTS = {
        driver: '/admin/api/Driver/DriverView.php?action=updateDriver',
        inspector: '/admin/api/Inspectors/UpdateInspector.php',
        dispatcher: '/admin/api/Inspectors/UpdateInspector.php',
        finance_officer: '/admin/api/Finance/getFinance.php?action=updateEmployee',
        finance_manager: '/admin/api/Finance/getFinance.php?action=updateEmployee',
        admin: '/admin/api/Admin/UpdateAdmin.php',
        super_admin: '/admin/api/Admin/UpdateAdmin.php'
    };

    var ARCHIVE_ENDPOINTS = {
        driver: {
            archive: { url: '/admin/api/Driver/DriverView.php?action=archiveDriver', mode: 'form', field: 'id' },
            restore: { url: '/admin/api/Driver/DriverView.php?action=unarchiveDriver', mode: 'form', field: 'id' }
        },
        inspector: {
            archive: { url: '/admin/api/Inspectors/ArchiveInspector.php', mode: 'form', field: 'inspector_pk' },
            restore: { url: '/admin/api/Inspectors/RestoreInspector.php', mode: 'form', field: 'inspector_pk' }
        },
        finance_officer: {
            archive: { url: '/admin/api/Finance/getFinance.php?action=archiveEmployee', mode: 'urlencoded', field: 'id' },
            restore: { url: '/admin/api/Finance/getFinance.php?action=restoreEmployee', mode: 'urlencoded', field: 'id' }
        },
        admin: {
            archive: { url: '/admin/api/Admin/ArchiveAdmin.php', mode: 'json', field: 'id' },
            restore: { url: '/admin/api/Admin/RestoreAdmin.php', mode: 'json', field: 'id' }
        }
    };
    ARCHIVE_ENDPOINTS.dispatcher = ARCHIVE_ENDPOINTS.inspector;
    ARCHIVE_ENDPOINTS.finance_manager = ARCHIVE_ENDPOINTS.finance_officer;
    ARCHIVE_ENDPOINTS.super_admin = ARCHIVE_ENDPOINTS.admin;

    var FIELD_SETS = {
        driver: [
            { name: 'full_name', label: 'Full Name', required: true },
            { name: 'email', label: 'Email', type: 'email' },
            { name: 'contact_number', label: 'Contact Number' },
            { name: 'status', label: 'Status', type: 'select', options: ['Active', 'Inactive', 'Pending'] },
            { name: 'license_number', label: 'License No.' },
            { name: 'license_type', label: 'License Type', type: 'select', options: ['Professional', 'Non-Professional'] },
            { name: 'license_expiry', label: 'License Expiry', type: 'date' },
            { name: 'experience_years', label: 'Experience (years)', type: 'number' },
            { name: 'birth_date', label: 'Date of Birth', type: 'date', age: true },
            { name: 'hired_date', label: 'Hired Date', type: 'date' },
            { name: 'approval_status', label: 'Approval', type: 'select', options: ['approved', 'pending', 'rejected'] },
            { name: 'assigned_buses', label: 'Assigned Buses', full: true },
            { name: 'routes', label: 'Routes', full: true },
            { name: 'resume_file', label: 'Resume / CV (PDF, DOC, DOCX)', type: 'file' },
            { name: 'supporting_docs', label: 'Supporting Docs (PDF, DOC, DOCX, JPG, PNG)', type: 'file' }
        ],
        inspector: [
            { name: 'inspector_name', label: 'Full Name', required: true },
            { name: 'inspector_identity', label: 'Identity' },
            { name: 'email', label: 'Email', type: 'email' },
            { name: 'contact_number', label: 'Contact Number' },
            { name: 'department', label: 'Department' },
            { name: 'experience_years', label: 'Experience (years)', type: 'number' },
            { name: 'birth_date', label: 'Date of Birth', type: 'date', age: true },
            { name: 'hired_date', label: 'Hired Date', type: 'date' },
            { name: 'operational_assignment', label: 'Operational Assignment', full: true },
            { name: 'assigned_routes', label: 'Assigned Terminal', type: 'options', optionKey: 'terminals', required: true },
            { name: 'additional_notes', label: 'Notes', type: 'textarea', full: true },
            { name: 'password', label: 'Reset Password (optional)', type: 'password' },
            { name: 'resume_file', label: 'Resume / CV', type: 'file' },
            { name: 'supporting_docs', label: 'Supporting Docs', type: 'file' }
        ],
        finance: [
            { name: 'full_name', label: 'Full Name', required: true },
            { name: 'email', label: 'Email', type: 'email' },
            { name: 'contact_no', label: 'Contact Number' },
            { name: 'birth_date', label: 'Date of Birth', type: 'date', age: true },
            { name: 'hired_date', label: 'Hired Date', type: 'date' },
            { name: 'status', label: 'Status', type: 'select', options: ['active', 'inactive', 'pending'] },
            { name: 'department', label: 'Department' },
            { name: 'assigned_area', label: 'Assigned Area' },
            { name: 'shift_status', label: 'Shift Status', type: 'select', options: ['', 'Day Shift', 'Night Shift', 'Flexible'] },
            { name: 'access_level', label: 'Access Level', type: 'select', options: ['1', '2', '3', '4'] },
            { name: 'shift_schedule', label: 'Shift Schedule' },
            { name: 'username', label: 'Username' },
            { name: 'password', label: 'Reset Password (optional)', type: 'password' },
            { name: 'address', label: 'Address', full: true },
            { name: 'bio', label: 'Bio', type: 'textarea', full: true },
            { name: 'resume_file', label: 'Resume / CV', type: 'file' },
            { name: 'supporting_docs', label: 'Supporting Docs', type: 'file' }
        ],
        admin: [
            { name: 'full_name', label: 'Full Name', required: true },
            { name: 'email', label: 'Email', type: 'email' },
            { name: 'contact_no', label: 'Contact Number' },
            { name: 'birth_date', label: 'Date of Birth', type: 'date', age: true },
            { name: 'hired_date', label: 'Hired Date', type: 'date' },
            { name: 'status', label: 'Status', type: 'select', options: ['active', 'inactive', 'pending'] },
            { name: 'assigned_department', label: 'Department' },
            { name: 'assigned_responsibility', label: 'Assigned Responsibility' },
            { name: 'shift_status', label: 'Shift Status', type: 'select', options: ['', 'Day Shift', 'Night Shift', 'Flexible'] },
            { name: 'access_level', label: 'Access Level', type: 'select', options: ['1', '2', '3', '4', '5'] },
            { name: 'username', label: 'Username' },
            { name: 'password', label: 'Reset Password (optional)', type: 'password' },
            { name: 'address', label: 'Address', full: true },
            { name: 'bio', label: 'Bio', type: 'textarea', full: true },
            { name: 'resume_file', label: 'Resume / CV', type: 'file' },
            { name: 'supporting_docs', label: 'Supporting Docs', type: 'file' }
        ]
    };

    var ADD_ENDPOINTS = {
        driver: '/admin/api/Driver/driver_accounts.php?action=add_driver',
        inspector: '/admin/api/Inspectors/AddInspector.php',
        dispatcher: '/admin/api/Inspectors/AddInspector.php',
        finance_officer: '/admin/api/Finance/AddEmployee.php',
        finance_manager: '/admin/api/Finance/AddEmployee.php',
        admin: '/admin/api/Admin/AddAdmin.php',
        super_admin: '/admin/api/Admin/AddAdmin.php'
    };

    var ADD_FIELD_SETS = {
        driver: [
            { name: 'full_name', label: 'Full Name', required: true },
            { name: 'email', label: 'Email', type: 'email', required: true },
            { name: 'contact_number', label: 'Contact Number', required: true },
            { name: 'birth_date', label: 'Date of Birth', type: 'date', age: true },
            { name: 'hired_date', label: 'Hired Date', type: 'date' },
            { name: 'license_number', label: 'License No.', required: true },
            { name: 'license_type', label: 'License Type', type: 'select', options: ['Professional', 'Non-Professional'], required: true },
            { name: 'license_expiry', label: 'License Expiry', type: 'date', required: true },
            { name: 'experience_years', label: 'Experience (years)', type: 'number', required: true },
            { name: 'bus_id', label: 'Assigned Bus', type: 'select-options', optionKey: 'buses', required: true },
            { name: 'assigned_buses', label: 'Assigned Buses (optional)' },
            { name: 'password', label: 'Password', type: 'password', required: true },
            { name: 'password_confirm', label: 'Confirm Password', type: 'password', required: true },
            { name: 'photo', label: 'Photo', type: 'file' },
            { name: 'resume_file', label: 'Resume / CV', type: 'file' },
            { name: 'supporting_docs', label: 'Supporting Docs', type: 'file' }
        ],
        inspector: [
            { name: 'inspector_name', label: 'Full Name', required: true },
            { name: 'inspector_identity', label: 'Identity', required: true },
            { name: 'department', label: 'Department', required: true },
            { name: 'experience_years', label: 'Experience (years)', type: 'number', required: true },
            { name: 'birth_date', label: 'Date of Birth', type: 'date', age: true },
            { name: 'hired_date', label: 'Hired Date', type: 'date' },
            { name: 'operational_assignment', label: 'Operational Assignment' },
            { name: 'assigned_routes', label: 'Assigned Terminal', type: 'select-options', optionKey: 'terminals', required: true },
            { name: 'contact_number', label: 'Contact Number', required: true },
            { name: 'email', label: 'Email', type: 'email', required: true },
            { name: 'password', label: 'Password', type: 'password', required: true },
            { name: 'password_confirm', label: 'Confirm Password', type: 'password', required: true },
            { name: 'additional_notes', label: 'Notes', type: 'textarea', full: true },
            { name: 'profile_picture', label: 'Photo', type: 'file' },
            { name: 'resume_file', label: 'Resume / CV', type: 'file' },
            { name: 'supporting_docs', label: 'Supporting Docs', type: 'file' }
        ],
        finance: [
            { name: 'full_name', label: 'Full Name', required: true },
            { name: 'username', label: 'Username', required: true },
            { name: 'email', label: 'Email', type: 'email', required: true },
            { name: 'contact_no', label: 'Contact Number', required: true },
            { name: 'birth_date', label: 'Date of Birth', type: 'date', age: true },
            { name: 'hired_date', label: 'Hired Date', type: 'date' },
            { name: 'status', label: 'Status', type: 'select', options: ['active', 'inactive', 'pending'] },
            { name: 'shift_status', label: 'Shift Status', type: 'select', options: ['', 'Day Shift', 'Night Shift', 'Flexible'] },
            { name: 'access_level', label: 'Access Level', type: 'select', options: ['1', '2', '3', '4'], required: true },
            { name: 'assigned_area', label: 'Assigned Area' },
            { name: 'shift_schedule', label: 'Shift Schedule' },
            { name: 'address', label: 'Address', full: true },
            { name: 'bio', label: 'Bio', type: 'textarea', full: true },
            { name: 'password', label: 'Password', type: 'password', required: true },
            { name: 'password_confirm', label: 'Confirm Password', type: 'password', required: true },
            { name: 'profile_picture', label: 'Photo', type: 'file' },
            { name: 'resume_file', label: 'Resume / CV', type: 'file' },
            { name: 'supporting_docs', label: 'Supporting Docs', type: 'file' }
        ],
        admin: [
            { name: 'full_name', label: 'Full Name', required: true },
            { name: 'username', label: 'Username', required: true },
            { name: 'email', label: 'Email', type: 'email', required: true },
            { name: 'contact_no', label: 'Contact Number', required: true },
            { name: 'birth_date', label: 'Date of Birth', type: 'date', age: true },
            { name: 'hired_date', label: 'Hired Date', type: 'date' },
            { name: 'status', label: 'Status', type: 'select', options: ['active', 'inactive', 'pending'] },
            { name: 'shift_status', label: 'Shift Status', type: 'select', options: ['', 'Day Shift', 'Night Shift', 'Flexible'] },
            { name: 'access_level', label: 'Access Level', type: 'select', options: ['1', '2', '3', '4', '5'], required: true },
            { name: 'assigned_department', label: 'Department' },
            { name: 'assigned_responsibility', label: 'Assigned Responsibility' },
            { name: 'address', label: 'Address', full: true },
            { name: 'bio', label: 'Bio', type: 'textarea', full: true },
            { name: 'password', label: 'Password', type: 'password', required: true },
            { name: 'password_confirm', label: 'Confirm Password', type: 'password', required: true },
            { name: 'profile_picture', label: 'Photo', type: 'file' },
            { name: 'resume_file', label: 'Resume / CV', type: 'file' },
            { name: 'supporting_docs', label: 'Supporting Docs', type: 'file' }
        ]
    };

    function addFieldSetForLegacy(role) {
        if (role === 'driver') return ADD_FIELD_SETS.driver;
        if (role === 'inspector' || role === 'dispatcher') return ADD_FIELD_SETS.inspector;
        if (role === 'finance_officer' || role === 'finance_manager') return ADD_FIELD_SETS.finance;
        return ADD_FIELD_SETS.admin;
    }

    function el(id) { return document.getElementById(id); }

    function esc(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function fmtDate(value, withTime) {
        var raw = String(value || '').trim();
        if (!raw) return '—';
        var parts = raw.split(' ');
        var d = parts[0].split('-');
        if (d.length !== 3) return raw;
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var m = parseInt(d[1], 10), day = parseInt(d[2], 10);
        if (!m || !day) return raw;
        var out = months[m - 1] + ' ' + day + ', ' + d[0];
        if (withTime && parts[1]) {
            var t = parts[1].split(':');
            var hh = parseInt(t[0], 10);
            if (!isNaN(hh)) {
                var ampm = hh >= 12 ? 'PM' : 'AM';
                var h12 = ((hh + 11) % 12) + 1;
                out += ' · ' + h12 + ':' + (t[1] || '00') + ' ' + ampm + ' (PHT)';
            }
        }
        return out;
    }

    function todayIso() {
        var now = new Date();
        var month = (now.getMonth() + 1 < 10 ? '0' : '') + (now.getMonth() + 1);
        var day = (now.getDate() < 10 ? '0' : '') + now.getDate();
        return now.getFullYear() + '-' + month + '-' + day;
    }

    function ageText(value) {
        var raw = String(value || '').trim();
        if (!raw) return '';
        var parts = raw.split('-');
        if (parts.length !== 3) return '';
        var birth = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        if (isNaN(birth.getTime())) return '';
        var today = new Date();
        var age = today.getFullYear() - birth.getFullYear();
        var month = today.getMonth() - birth.getMonth();
        if (month < 0 || (month === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        if (age < 0 || age > 130) return '';
        return 'Age: ' + age + ' years old';
    }

    function updateAgeNotes(container) {
        if (!container) return;
        container.querySelectorAll('[data-age-for]').forEach(function (note) {
            var name = note.getAttribute('data-age-for');
            var input = container.querySelector('[data-add-field="' + name + '"], [data-edit-field="' + name + '"]');
            note.textContent = input ? ageText(input.value) : '';
        });
    }

    function initials(name) {
        var parts = String(name || '').trim().split(/\s+/);
        var out = '';
        parts.slice(0, 2).forEach(function (p) { out += (p.charAt(0) || '').toUpperCase(); });
        return out || '?';
    }

    function statusClass(status) {
        return ['active', 'inactive', 'pending', 'rejected'].indexOf(status) !== -1 ? 'is-' + status : 'is-other';
    }

    function debounce(fn, wait) {
        var handle = null;
        return function () {
            clearTimeout(handle);
            var args = arguments;
            handle = setTimeout(function () { fn.apply(null, args); }, wait);
        };
    }

    /* ---------------------------------------------------------------------
     * Structured multi-step Add / Edit forms
     *
     * Every step is rendered as a section inside the modal and stays in the
     * DOM, so the existing collection, security-token hashing and validation
     * keep working unchanged. Composites (name parts, address) write into
     * hidden inputs that carry the legacy field names the endpoints expect.
     * ------------------------------------------------------------------- */

    var WIZARD_STEPS = [
        {key: 'personal', title: 'Personal Information', icon: 'bi-person'},
        {key: 'account', title: 'Account Details', icon: 'bi-person-vcard'},
        {key: 'employment', title: 'Employment Information', icon: 'bi-briefcase'},
        {key: 'role', title: 'Role-Specific Details', icon: 'bi-shield-check'},
        {key: 'review', title: 'Review & Confirm', icon: 'bi-clipboard-check'}
    ];

    var EMPLOYMENT_ROLES = ['driver', 'inspector', 'dispatcher', 'finance_officer', 'finance_manager'];

    var GEO_URL = '/admin/api/RoleManagement/Geo.php';

    function f(step, name, label, type, req, extra) {
        var field = {step: step, name: name, label: label, type: type || 'text', required: !!req};
        if (extra) {
            Object.keys(extra).forEach(function (key) { field[key] = extra[key]; });
        }
        return field;
    }

    function isUserRole(role) {
        return role === 'finance_officer' || role === 'finance_manager' || role === 'admin' || role === 'super_admin';
    }

    function nameFieldName(role) {
        return (role === 'inspector' || role === 'dispatcher') ? 'inspector_name' : 'full_name';
    }

    function contactFieldName(role) {
        return isUserRole(role) ? 'contact_no' : 'contact_number';
    }

    function userStatusValues() {
        return ['active', 'inactive'];
    }

    function wizardStepsFor(role) {
        return WIZARD_STEPS.filter(function (step) {
            return step.key !== 'employment' || EMPLOYMENT_ROLES.indexOf(role) !== -1;
        });
    }

    /**
     * Field definitions per role. Types:
     *   name-parts, address, meta  -> composite renderers
     *   select, select-options     -> native dropdowns (id values for options)
     *   datalist                   -> searchable free-ish dropdown
     *   text, email, tel, number, date, password, textarea, file
     */
    function wizardFieldsFor(role, mode) {
        var isAdd = mode === 'add';
        var fields = [];

        fields.push({step: 'personal', type: 'name-parts', required: isAdd});

        if (isUserRole(role)) {
            fields.push(f('personal', 'sex', 'Sex', 'select', false, {optionsKey: 'sex'}));
            fields.push(f('personal', 'birth_date', 'Date of Birth', 'date'));
            fields.push(f('personal', contactFieldName(role), 'Contact Number', 'tel', isAdd, {live: 'phone', placeholder: '0917 123 4567'}));
            fields.push(f('personal', 'email', 'Email Address', 'email', isAdd, {live: 'email'}));
            fields.push({step: 'personal', type: 'address'});
            fields.push(f('personal', 'profile_picture', 'Photo', 'file', !isAdd));
            fields.push(f('personal', 'resume_file', 'Resume / CV (PDF, DOC, DOCX)', 'file'));
            fields.push(f('personal', 'supporting_docs', 'Supporting Documents (PDF, DOC, DOCX, JPG, PNG)', 'file'));

            fields.push(f('account', 'username', 'Username', 'text', isAdd, {live: 'username', hint: 'Suggested from the name — you may change it.'}));
            fields.push(f('account', 'status', 'Account Status', 'select', isAdd, {options: userStatusValues(), labels: {active: 'Active', inactive: 'Inactive'}, default: 'active'}));
            fields.push({step: 'account', type: 'meta'});
            fields.push(f('account', 'password', 'Password', 'password', isAdd, {live: 'password'}));
            fields.push(f('account', 'password_confirm', 'Confirm Password', 'password', isAdd, {live: 'confirm'}));

            fields.push(f('employment', 'employment_type', 'Employment Type', 'select', false, {optionsKey: 'employment_types'}));
            fields.push(f('employment', 'employment_status', 'Employment Status', 'select', false, {optionsKey: 'employment_statuses'}));
            fields.push(f('employment', 'hired_date', 'Date Hired', 'date', false, {default: 'today'}));
            fields.push(f('employment', 'supervisor_id', 'Assigned Supervisor', 'select-options', false, {optionsKey: 'supervisors'}));

            if (role === 'finance_officer' || role === 'finance_manager') {
                fields.push(f('role', 'position_title', 'Finance Position', 'select', false, {optionsKey: 'finance_positions'}));
                fields.push(f('role', 'shift_status', 'Work Shift', 'select', false, {options: ['', 'Day Shift', 'Night Shift', 'Flexible']}));
                fields.push(f('role', 'access_level', 'Access Level', 'select', isAdd, {options: ['1', '2', '3', '4']}));
                fields.push(f('role', 'assigned_area', 'Assigned Area', 'text'));
                fields.push(f('role', 'shift_schedule', 'Shift Schedule', 'text'));
                fields.push(f('role', 'bio', 'Bio', 'textarea', false, {full: true}));
            } else {
                fields.push(f('role', 'position_title', 'Administrative Position', 'select', false, {optionsKey: 'admin_positions'}));
                fields.push(f('role', 'assigned_department', 'Department', 'datalist', false, {optionsKey: 'departments'}));
                fields.push(f('role', 'assigned_office', 'Assigned Office', 'text'));
                fields.push(f('role', 'access_level', 'Access Level', 'select', isAdd, {options: ['1', '2', '3', '4', '5']}));
                fields.push(f('role', 'shift_status', 'Shift Status', 'select', false, {options: ['', 'Day Shift', 'Night Shift', 'Flexible']}));
                fields.push(f('role', 'bio', 'Bio', 'textarea', false, {full: true}));
            }

            return fields;
        }

        // Driver / Inspector / Dispatcher
        fields.push(f('personal', 'sex', 'Sex', 'select', false, {optionsKey: 'sex'}));
        fields.push(f('personal', 'birth_date', 'Date of Birth', 'date'));
        fields.push(f('personal', contactFieldName(role), 'Contact Number', 'tel', isAdd, {live: 'phone', placeholder: '0917 123 4567'}));
        fields.push(f('personal', 'email', 'Email Address', 'email', isAdd, {live: 'email'}));
        fields.push({step: 'personal', type: 'address'});
        fields.push(f('personal', (role === 'driver' ? 'photo' : 'profile_picture'), 'Photo', 'file', !isAdd));
        fields.push(f('personal', 'resume_file', 'Resume / CV (PDF, DOC, DOCX)', 'file'));
        fields.push(f('personal', 'supporting_docs', 'Supporting Documents (PDF, DOC, DOCX, JPG, PNG)', 'file'));

        if (role === 'driver') {
            fields.push(f('account', 'status', 'Account Status', 'select', isAdd, {options: ['Active', 'Inactive'], default: 'Active'}));
        }
        fields.push({step: 'account', type: 'meta'});
        fields.push(f('account', 'password', 'Password', 'password', isAdd, {live: 'password'}));
        fields.push(f('account', 'password_confirm', 'Confirm Password', 'password', isAdd, {live: 'confirm'}));

        fields.push(f('employment', 'employment_type', 'Employment Type', 'select', false, {optionsKey: 'employment_types'}));
        fields.push(f('employment', 'employment_status', 'Employment Status', 'select', false, {optionsKey: 'employment_statuses'}));
        fields.push(f('employment', 'hired_date', 'Date Hired', 'date', false, {default: 'today'}));
        fields.push(f('employment', 'supervisor_id', 'Assigned Supervisor', 'select-options', false, {optionsKey: 'supervisors'}));

        if (role === 'driver') {
            fields.push(f('role', 'license_number', 'Driver\'s License Number', 'text', isAdd));
            fields.push(f('role', 'license_type', 'License Classification', 'select', isAdd, {optionsKey: 'license_classifications'}));
            fields.push(f('role', 'license_expiry', 'License Expiration Date', 'date', isAdd));
            fields.push(f('role', 'experience_years', 'Driving Experience (years)', 'number', isAdd));
            fields.push(f('role', 'bus_id', 'Assigned Bus', 'select-options', isAdd, {optionsKey: 'buses'}));
            fields.push(f('role', 'routes', 'Assigned Route', 'datalist', false, {optionsKey: 'routes'}));
            fields.push(f('role', 'availability', 'Driver Availability', 'select', false, {optionsKey: 'availability'}));
            fields.push(f('role', 'assigned_buses', 'Assigned Buses (optional)', 'text'));
        } else {
            fields.push(f('employment', 'inspector_identity', 'Inspector Identity / Badge No.', 'text', isAdd));
            fields.push(f('employment', 'department', 'Department', 'datalist', isAdd, {optionsKey: 'departments'}));
            fields.push(f('employment', 'experience_years', 'Experience (years)', 'number', isAdd));
            fields.push(f('employment', 'work_shift', 'Work Shift', 'select', false, {optionsKey: 'work_shifts'}));
            fields.push(f('role', 'operational_assignment', 'Operational Assignment', 'text'));
            fields.push(f('role', 'assigned_routes', 'Assigned Terminal', 'select-options', isAdd, {optionsKey: 'terminals'}));
            fields.push(f('role', 'assigned_area', 'Assigned Bus / Area', 'select-options', false, {optionsKey: 'buses'}));
            fields.push(f('role', 'availability', 'Inspector Availability', 'select', false, {optionsKey: 'availability'}));
            fields.push(f('role', 'additional_notes', 'Notes', 'textarea', false, {full: true}));
        }

        return fields;
    }

    function fieldSetsFor(role) {
        return wizardFieldsFor(role, 'edit');
    }

    function addFieldSetFor(role) {
        return wizardFieldsFor(role, 'add');
    }

    /* ------------------------- name / address helpers ------------------------- */

    var NAME_SUFFIXES = ['jr.', 'jr', 'sr.', 'sr', 'ii', 'iii', 'iv'];

    function splitFullName(full) {
        var parts = String(full || '').trim().split(/\s+/).filter(Boolean);
        var result = {first: '', middle: '', last: '', suffix: ''};
        if (!parts.length) return result;
        var tail = parts[parts.length - 1].toLowerCase().replace(/,$/, '');
        if (parts.length > 1 && NAME_SUFFIXES.indexOf(tail) !== -1) {
            result.suffix = parts.pop();
        }
        result.first = parts.shift() || '';
        result.last = parts.length ? parts.pop() : '';
        result.middle = parts.join(' ');
        return result;
    }

    function composeFullName(parts) {
        var out = [parts.first, parts.middle, parts.last].filter(function (p) { return String(p || '').trim() !== ''; });
        var name = out.join(' ').replace(/\s+/g, ' ').trim();
        if (String(parts.suffix || '').trim() !== '') {
            name += ' ' + String(parts.suffix).trim();
        }
        return name;
    }

    function suggestUsername(name) {
        var slug = String(name || '').toLowerCase()
            .replace(/[^a-z0-9 ]+/g, '')
            .trim()
            .replace(/\s+/g, '.');
        return slug.length >= 3 ? slug.slice(0, 30) : '';
    }

    function escAttr(value) {
        return esc(value);
    }

    /* ------------------------- geo (PSGC) address ------------------------- */

    /**
     * Load one address level from the server proxy (PSA API with a bundled
     * fallback). Results are cached per page so re-opening a dropdown is
     * instant.
     */
    function loadGeoSelect(mode, kind, params, selectId, placeholder) {
        var select = el(selectId);
        if (!select) return Promise.resolve();

        select.innerHTML = '<option value="">Loading…</option>';
        select.disabled = true;

        var qs = 'level=' + encodeURIComponent(kind);
        Object.keys(params || {}).forEach(function (key) {
            qs += '&' + key + '=' + encodeURIComponent(params[key]);
        });

        return fetch(GEO_URL + '?' + qs, {credentials: 'same-origin', cache: 'no-store'})
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var options = (res && res.success && Array.isArray(res.options)) ? res.options : [];
                if (kind === 'provinces') {
                    state.geoCache.provinces = options;
                } else if (kind === 'cities') {
                    state.geoCache.cities[params.province] = options;
                } else if (kind === 'barangays') {
                    state.geoCache.barangays[params.city] = options;
                }
                select.innerHTML = optionsHtml(options, placeholder);
                select.disabled = false;
                applyGeoFilterForSelect(select);
            })
            .catch(function () {
                select.innerHTML = '<option value="">' + esc(placeholder) + ' (unavailable — type instead)</option>';
                select.disabled = true;
                applyGeoFilterForSelect(select);
            });
    }

    function loadGeoProvinces(mode) {
        var id = mode === 'add' ? 'Add' : 'Edit';
        loadGeoSelect(mode, 'provinces', {}, 'rmg' + id + 'Province', 'Select province');
    }

    function onGeoProvinceChange(mode) {
        var id = mode === 'add' ? 'Add' : 'Edit';
        var provinceSelect = el('rmg' + id + 'Province');
        var citySelect = el('rmg' + id + 'City');
        var barangaySelect = el('rmg' + id + 'Barangay');
        if (!provinceSelect || !citySelect || !barangaySelect) return;

        clearGeoSearch(mode, 'city');
        clearGeoSearch(mode, 'barangay');
        barangaySelect.innerHTML = '<option value="">Barangay</option>';
        barangaySelect.disabled = true;

        if (provinceSelect.value === '') {
            citySelect.innerHTML = '<option value="">City / Municipality</option>';
            citySelect.disabled = true;
            applyGeoFilterForSelect(citySelect);
            return;
        }

        loadGeoSelect(mode, 'cities', {province: provinceSelect.value}, 'rmg' + id + 'City', 'Select city / municipality');
    }

    function onGeoCityChange(mode) {
        var id = mode === 'add' ? 'Add' : 'Edit';
        var citySelect = el('rmg' + id + 'City');
        var barangaySelect = el('rmg' + id + 'Barangay');
        if (!citySelect || !barangaySelect) return;

        clearGeoSearch(mode, 'barangay');

        if (citySelect.value === '') {
            barangaySelect.innerHTML = '<option value="">Barangay</option>';
            barangaySelect.disabled = true;
            applyGeoFilterForSelect(barangaySelect);
            return;
        }

        loadGeoSelect(mode, 'barangays', {city: citySelect.value}, 'rmg' + id + 'Barangay', 'Select barangay');
    }

    function geoSelectHtml(id, placeholder, role) {
        return '<div class="rmg-geo-box">' +
            '<input type="text" class="rmg-geo-search" id="' + id + 'Search" data-geo-search="' + role + '" autocomplete="off" spellcheck="false" placeholder="Search ' + escAttr(placeholder) + '..." aria-label="Search ' + escAttr(placeholder) + '">' +
            '<select id="' + id + '" data-geo-role="' + role + '" disabled><option value="">' + esc(placeholder) + '</option></select>' +
            '</div>';
    }

    /**
     * Filter one geo dropdown from its search box. Matching is
     * case-insensitive and substring-based; options are hidden, never
     * removed, so the selected value and composed address stay intact while
     * the administrator types.
     */
    function filterGeoSelect(searchInput) {
        if (!searchInput || !searchInput.parentNode) return;
        var select = searchInput.parentNode.querySelector('[data-geo-role]');
        if (!select || !select.options) return;

        var query = String(searchInput.value || '').trim().toLowerCase();
        var selected = select.value;
        var visible = 0;
        var emptyOption = select.querySelector('[data-geo-empty="1"]');

        if (emptyOption && emptyOption.parentNode) {
            emptyOption.parentNode.removeChild(emptyOption);
        }

        for (var i = 0; i < select.options.length; i++) {
            var option = select.options[i];
            if (option.getAttribute('data-geo-empty') === '1') continue;
            var text = String(option.textContent || '').toLowerCase();
            var keepSelected = option.value !== '' && option.value === selected;
            var match = query === '' || keepSelected || text.indexOf(query) !== -1;
            option.hidden = !match;
            option.style.display = match ? '' : 'none';
            if (match && i > 0) visible++;
        }

        if (query !== '' && visible === 0 && document.createElement) {
            var none = document.createElement('option');
            none.setAttribute('data-geo-empty', '1');
            none.disabled = true;
            none.textContent = 'No matching ' + (select.getAttribute('data-geo-role') || 'option') + 's';
            select.appendChild(none);
        }
    }

    function applyGeoFilterForSelect(select) {
        if (!select || !select.parentNode) return;
        var search = select.parentNode.querySelector('[data-geo-search]');
        if (search) filterGeoSelect(search);
    }

    function clearGeoSearch(mode, role) {
        var id = mode === 'add' ? 'Add' : 'Edit';
        var suffix = role === 'province' ? 'Province' : (role === 'city' ? 'City' : 'Barangay');
        var input = el('rmg' + id + suffix + 'Search');
        if (input) {
            input.value = '';
        }
    }

    /**
     * Enter picks the first option left visible by the current search, then
     * dispatches change so dependent levels reload through the usual flow.
     */
    function applyFirstGeoMatch(searchInput) {
        if (!searchInput || !searchInput.parentNode) return;
        var select = searchInput.parentNode.querySelector('[data-geo-role]');
        if (!select || !select.options) return;
        for (var i = 1; i < select.options.length; i++) {
            var option = select.options[i];
            if (option.getAttribute('data-geo-empty') === '1') continue;
            if (option.hidden || option.style.display === 'none') continue;
            select.value = option.value;
            if (select.dispatchEvent) {
                select.dispatchEvent(new Event('change', {bubbles: true}));
            }
            return;
        }
    }

    function optionsHtml(list, placeholder) {
        var html = '<option value="">' + esc(placeholder || 'Select...') + '</option>';
        (list || []).forEach(function (option) {
            html += '<option value="' + escAttr(option.value !== undefined ? option.value : option.id) + '">' + esc(option.label !== undefined ? option.label : option) + '</option>';
        });
        return html;
    }

    function wizardOptionsFor(field, mode) {
        var options = state.addOptions || {};
        if (field.options) {
            return field.options.map(function (value) {
                var label = field.labels && field.labels[value] !== undefined ? field.labels[value] : (value === '' ? '—' : value);
                return {value: value, label: label};
            });
        }
        if (!field.optionsKey) return [];
        if (field.optionsKey === 'terminals' || field.optionsKey === 'buses' || field.optionsKey === 'supervisors') {
            return options[field.optionsKey] || [];
        }
        if (field.optionsKey === 'routes' || field.optionsKey === 'departments') {
            return options[field.optionsKey] || [];
        }
        var lists = options.lists || {};
        var list = lists[field.optionsKey] || [];
        var mapped = list.map(function (value) {
            return {value: value, label: value === '' ? '—' : value};
        });
        if (!list.length || list[0] !== '') {
            mapped.unshift({value: '', label: '—'});
        }
        return mapped;
    }

    function renderWizardField(field, mode, values) {
        var prefix = mode === 'add' ? 'data-add-field' : 'data-edit-field';
        var name = field.name || '';
        var value = values && values[name] !== undefined && values[name] !== null ? String(values[name]) : '';
        if (value === '' && mode === 'add' && field.default === 'today') {
            value = todayIso();
        }
        var required = field.required ? ' data-required="1"' : '';
        var inputId = 'rmg' + (mode === 'add' ? 'Add' : 'Edit') + 'F' + name;

        var html = '<div class="rmg-form-field' + (field.full ? ' full' : '') + '" data-field-wrapper="' + escAttr(name) + '">';
        html += '<label' + (inputId ? ' for="' + escAttr(inputId) + '"' : '') + '>' + esc(field.label) + (field.required ? ' *' : '') + '</label>';

        if (field.type === 'select') {
            html += '<select ' + prefix + '="' + escAttr(name) + '" id="' + escAttr(inputId) + '"' + required + '>';
            var selected = value !== '' ? value : (field.default !== undefined ? field.default : '');
            wizardOptionsFor(field, mode).forEach(function (option) {
                html += '<option value="' + escAttr(option.value) + '"' + (String(selected) === String(option.value) ? ' selected' : '') + '>' + esc(option.label) + '</option>';
            });
            html += '</select>';
        } else if (field.type === 'select-options') {
            var list = wizardOptionsFor(field, mode);
            html += '<select ' + prefix + '="' + escAttr(name) + '" id="' + escAttr(inputId) + '"' + required + '>';
            html += '<option value="">' + (list.length ? 'Select...' : 'No options available') + '</option>';
            list.forEach(function (option) {
                html += '<option value="' + escAttr(option.id) + '"' + (value !== '' && String(value) === String(option.id) ? ' selected' : '') + '>' + esc(option.label) + '</option>';
            });
            html += '</select>';
            if (!list.length) {
                html += '<div class="rmg-field-hint">No records found yet — add them in their modules and they will appear here.</div>';
            }
        } else if (field.type === 'datalist') {
            var dataList = wizardOptionsFor(field, mode);
            var listId = inputId + 'List';
            html += '<input type="text" list="' + escAttr(listId) + '" ' + prefix + '="' + escAttr(name) + '" id="' + escAttr(inputId) + '" value="' + escAttr(value) + '"' + required + '>';
            html += '<datalist id="' + escAttr(listId) + '">';
            dataList.forEach(function (option) {
                html += '<option value="' + escAttr(option.label || option.id) + '"></option>';
            });
            html += '</datalist>';
        } else if (field.type === 'textarea') {
            html += '<textarea rows="2" ' + prefix + '="' + escAttr(name) + '" id="' + escAttr(inputId) + '"' + required + '>' + esc(value) + '</textarea>';
        } else if (field.type === 'file') {
            html += '<input type="file" ' + prefix + '="' + escAttr(name) + '" id="' + escAttr(inputId) + '"' + required + ' accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">';
        } else {
            var type = field.type === 'tel' ? 'tel' : (field.type === 'number' ? 'number' : field.type);
            html += '<input type="' + escAttr(type) + '" ' + prefix + '="' + escAttr(name) + '" id="' + escAttr(inputId) + '" value="' + (field.type === 'password' ? '' : escAttr(value)) + '"' + required +
                (field.placeholder ? ' placeholder="' + escAttr(field.placeholder) + '"' : '') +
                (field.live ? ' data-live="' + escAttr(field.live) + '"' : '') + '>';
        }

        if (field.hint) {
            html += '<div class="rmg-field-hint">' + esc(field.hint) + '</div>';
        }
        html += '</div>';
        return html;
    }

    function renderNamePartsField(mode, role, values, required) {
        var prefix = mode === 'add' ? 'data-add-field' : 'data-edit-field';
        var nameField = nameFieldName(role);
        var parts = (values && values._nameParts) || {first: '', middle: '', last: '', suffix: ''};
        var suffixes = (state.addOptions && state.addOptions.lists && state.addOptions.lists.suffixes) || ['', 'Jr.', 'Sr.', 'II', 'III', 'IV'];
        var mark = required ? ' *' : '';

        var html = '<div class="rmg-form-field full rmg-name-parts" data-name-parts="' + mode + '">';
        html += '<label>Full Name' + mark + '</label>';
        html += '<div class="rmg-grid-4">';
        html += '<div><input type="text" data-name-part="first" placeholder="First name' + mark + '" value="' + escAttr(parts.first) + '"><span class="rmg-mini-label">First name</span></div>';
        html += '<div><input type="text" data-name-part="middle" placeholder="Middle name" value="' + escAttr(parts.middle) + '"><span class="rmg-mini-label">Middle name</span></div>';
        html += '<div><input type="text" data-name-part="last" placeholder="Last name' + mark + '" value="' + escAttr(parts.last) + '"><span class="rmg-mini-label">Last name</span></div>';
        html += '<div><select data-name-part="suffix">';
        suffixes.forEach(function (suffix) {
            html += '<option value="' + escAttr(suffix) + '"' + (String(parts.suffix).toLowerCase() === String(suffix).toLowerCase() ? ' selected' : '') + '>' + esc(suffix === '' ? 'No suffix' : suffix) + '</option>';
        });
        html += '</select><span class="rmg-mini-label">Suffix</span></div>';
        html += '</div>';
        html += '<div class="rmg-field-hint">The full name is combined automatically from these fields.</div>';
        html += '<input type="hidden" ' + prefix + '="' + escAttr(nameField) + '" value="' + escAttr(composeFullName(parts)) + '" data-name-composite="1">';
        html += '</div>';
        return html;
    }

    function renderAddressField(mode, values) {
        var prefix = mode === 'add' ? 'data-add-field' : 'data-edit-field';
        var current = values && values.address ? String(values.address) : '';
        var id = mode === 'add' ? 'Add' : 'Edit';

        var html = '<div class="rmg-form-field full rmg-address" data-address-mode="' + mode + '">';
        html += '<label>Address</label>';
        html += '<div class="rmg-grid-3">';
        html += '<div>' + geoSelectHtml('rmg' + id + 'Province', 'Province', 'province') + '<span class="rmg-mini-label">Province</span></div>';
        html += '<div>' + geoSelectHtml('rmg' + id + 'City', 'City / Municipality', 'city') + '<span class="rmg-mini-label">City / Municipality</span></div>';
        html += '<div>' + geoSelectHtml('rmg' + id + 'Barangay', 'Barangay', 'barangay') + '<span class="rmg-mini-label">Barangay</span></div>';
        html += '</div>';
        html += '<div class="rmg-field-hint">Street / House No.</div>';
        html += '<input type="text" data-address-street="1" placeholder="House no., street, building" value="">';
        if (current !== '') {
            html += '<div class="rmg-field-hint">Current address: ' + esc(current) + ' — pick new locations above only to replace it.</div>';
        }
        html += '<input type="hidden" ' + prefix + '="address" value="' + escAttr(current) + '" data-address-composite="1">';
        html += '</div>';
        return html;
    }

    function renderMetaField(mode, meta) {
        var rows = mode === 'edit' ? [
            ['Employee No.', meta.user_id],
            ['Date created', meta.created_at],
            ['Created by', meta.created_by],
            ['Last updated', meta.updated_at]
        ] : [
            ['Employee No.', 'Assigned with the User ID'],
            ['Date created', 'Recorded automatically on save'],
            ['Created by', 'Your account is recorded automatically'],
            ['Last updated', 'Recorded automatically on every change']
        ];

        var html = '<div class="rmg-form-field full rmg-meta-box"><label>Account Record</label><div class="rmg-meta-grid">';
        rows.forEach(function (row) {
            html += '<div class="rmg-meta-item"><span class="k">' + esc(row[0]) + '</span><span class="v">' + displayValue(row[1]) + '</span></div>';
        });
        html += '</div></div>';
        return html;
    }

    function renderWizardSteps(containerId, mode, role) {
        var host = el(containerId);
        if (!host) return;
        var steps = wizardStepsFor(role);
        var active = state.wizard[mode] || 0;

        host.innerHTML = steps.map(function (step, index) {
            var marker = index < active
                ? '<span class="rmg-step-index"><i class="bi bi-check2"></i></span>'
                : '<span class="rmg-step-index">' + (index + 1) + '</span>';
            var chip = '<button type="button" class="rmg-step-chip' + (index === active ? ' is-active' : '') + (index < active ? ' is-done' : '') + '" data-wizard-step="' + index + '" data-wizard-mode="' + mode + '">' +
                marker + '<span class="rmg-step-label">' + esc(step.title) + '</span></button>';
            if (index < steps.length - 1) {
                chip += '<span class="rmg-step-sep" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>';
            }
            return chip;
        }).join('');
    }

    function renderWizardReview(mode) {
        var role = mode === 'add' ? el('rmgAddRole').value : (window.RMG_EDIT_ITEM ? window.RMG_EDIT_ITEM.role : '');
        if (!role) return '';
        var prefix = mode === 'add' ? 'data-add-field' : 'data-edit-field';
        var review = [['Role', (CFG.roles || {})[role] || role]];

        wizardFieldsFor(role, mode).forEach(function (field) {
            if (!field.name || field.type === 'file' || field.type === 'password') return;
            var prefix = mode === 'add' ? 'data-add-field' : 'data-edit-field';
            var host = el(mode === 'add' ? 'rmgAddFields' : 'rmgEditFields');
            var node = host ? host.querySelector('[' + prefix + '="' + field.name + '"]') : null;
            if (!node) return;
            var value = node.value;
            if (field.type === 'select-options') {
                var option = node.options[node.selectedIndex];
                value = option ? option.textContent : '';
            }
            if (String(value || '').trim() === '') return;
            review.push([field.label.replace(' *', ''), value]);
        });

        var preview = mode === 'add'
            ? (state.addUserId || (el('rmgAddUserId') ? el('rmgAddUserId').value : ''))
            : (window.RMG_EDIT_ITEM ? window.RMG_EDIT_ITEM.user_id : '');
        review.splice(1, 0, ['User ID', preview + (preview && preview.indexOf('Generating') === -1 && preview.indexOf('Assigned') === -1 ? ' (preview)' : '')]);

        var html = '<div class="rmg-review-title"><i class="bi bi-clipboard-check"></i> Review before saving</div>' +
            '<div class="rmg-review-grid">' +
            review.map(function (row) {
                return '<div class="rmg-review-item"><span class="k">' + esc(row[0]) + '</span><span class="v">' + esc(row[1]) + '</span></div>';
            }).join('') +
            '</div><div class="rmg-field-hint">Only these non-sensitive details are shown. Verify them, then confirm with the security code.</div>';

        return html;
    }

    function renderWizardFields(containerId, mode, role, values) {
        var host = el(containerId);
        if (!host) return;
        var fields = wizardFieldsFor(role, mode);
        var steps = wizardStepsFor(role);
        var html = '';

        steps.forEach(function (step, stepIndex) {
            if (step.key === 'review') return;
            var stepFields = fields.filter(function (field) { return field.step === step.key; });
            if (!stepFields.length) return;
            html += '<section class="rmg-step" data-step="' + escAttr(step.key) + '" data-step-index="' + stepIndex + '">' +
                '<div class="rmg-step-head"><span class="rmg-step-badge">' + (stepIndex + 1) + '</span>' +
                '<span class="rmg-step-icon"><i class="bi ' + escAttr(step.icon) + '"></i></span>' +
                '<span class="rmg-step-title">' + esc(step.title) + '</span></div>' +
                '<div class="rmg-form-grid">';
            stepFields.forEach(function (field) {
                if (field.type === 'name-parts') {
                    html += renderNamePartsField(mode, role, values, field.required);
                } else if (field.type === 'address') {
                    html += renderAddressField(mode, values);
                } else if (field.type === 'meta') {
                    html += renderMetaField(mode, state.editMeta || {});
                } else {
                    html += renderWizardField(field, mode, values);
                }
            });
            html += '</div></section>';
        });

        if (mode === 'edit') {
            html += '<section class="rmg-step" data-step="reason" data-step-index="' + steps.length + '">' +
                '<div class="rmg-step-head"><span class="rmg-step-badge">*</span>' +
                '<span class="rmg-step-icon"><i class="bi bi-chat-left-text"></i></span>' +
                '<span class="rmg-step-title">Change Reason</span></div>' +
                '<div class="rmg-form-grid"><div class="rmg-form-field full">' +
                '<label>Reason for this change (optional)</label>' +
                '<textarea rows="2" data-edit-field="reason" placeholder="Why does this profile need to be changed? (recorded with your username)">' + esc((window.RMG_EDIT_ITEM && window.RMG_EDIT_ITEM._reason) || '') + '</textarea>' +
                '</div></div></section>';
        }

        host.innerHTML = html;

        // Review section host.
        var reviewHost = el(mode === 'add' ? 'rmgAddReview' : 'rmgEditReview');
        if (reviewHost) {
            reviewHost.hidden = false;
            reviewHost.innerHTML = renderWizardReview(mode);
        }

        enhancePasswordFields(host);
        updateAgeNotes(host);
        wireWizardHost(mode);
        syncComposites(mode, values);
    }

    function syncComposites(mode, values) {
        if (!values) return;
        var host = el(mode === 'add' ? 'rmgAddFields' : 'rmgEditFields');
        if (!host || !host.querySelector) return;

        var nameComposite = host.querySelector('[data-name-composite="1"]');
        if (nameComposite && values._nameParts) {
            nameComposite.value = composeFullName(values._nameParts);
        }
        var addressComposite = host.querySelector('[data-address-composite="1"]');
        if (addressComposite && values.address) {
            addressComposite.value = values.address;
        }
    }

    /* ------------------------- composite live behaviour ------------------------- */

    function composeNameFromHost(host, mode, role) {
        var parts = {first: '', middle: '', last: '', suffix: ''};
        ['first', 'middle', 'last'].forEach(function (key) {
            var input = host.querySelector('[data-name-part="' + key + '"]');
            parts[key] = input ? input.value.trim() : '';
        });
        var suffix = host.querySelector('[data-name-part="suffix"]');
        parts.suffix = suffix ? suffix.value : '';

        var composite = host.querySelector('[data-name-composite="1"]');
        if (composite) {
            composite.value = composeFullName(parts);
        }

        // Auto-suggest the username while it has not been touched.
        var usernameInput = host.querySelector('[data-add-field="username"], [data-edit-field="username"]');
        if (usernameInput && usernameInput.getAttribute('data-rmg-touched') !== '1') {
            var suggestion = suggestUsername(parts.first + ' ' + parts.last);
            if (suggestion) usernameInput.value = suggestion;
        }
        return parts;
    }

    function addressPartsFromHost(host) {
        var province = host.querySelector('[data-geo-role="province"]');
        var city = host.querySelector('[data-geo-role="city"]');
        var barangay = host.querySelector('[data-geo-role="barangay"]');
        var street = host.querySelector('[data-address-street="1"]');
        return {
            province: province && province.selectedIndex > 0 ? province.options[province.selectedIndex].textContent : '',
            city: city && city.selectedIndex > 0 ? city.options[city.selectedIndex].textContent : '',
            barangay: barangay && barangay.selectedIndex > 0 ? barangay.options[barangay.selectedIndex].textContent : '',
            street: street ? street.value.trim() : ''
        };
    }

    function composeAddressFromHost(host, mode, original) {
        var parts = addressPartsFromHost(host);
        var composite = host.querySelector('[data-address-composite="1"]');
        if (!composite) return;
        var anyPart = [parts.province, parts.city, parts.barangay, parts.street].some(function (p) { return p !== ''; });
        if (!anyPart) {
            composite.value = mode === 'edit' ? (original || '') : '';
            return;
        }
        composite.value = [parts.street, parts.barangay, parts.city, parts.province]
            .filter(function (p) { return p !== ''; })
            .join(', ');
    }

    function fillGeoSelectsRemoved() {
        // Superseded by loadGeoProvinces / onGeoProvinceChange above.
    }

    /**
     * Attach the composite + geo behaviour to a freshly rendered host.
     */
    function wireWizardHost(mode) {
        var host = el(mode === 'add' ? 'rmgAddFields' : 'rmgEditFields');
        if (!host || !host.querySelectorAll) return;
        var role = mode === 'add' ? el('rmgAddRole').value : (window.RMG_EDIT_ITEM ? window.RMG_EDIT_ITEM.role : '');
        var originalAddress = mode === 'edit' && window.RMG_EDIT_ITEM ? window.RMG_EDIT_ITEM.address || '' : '';

        Array.prototype.forEach.call(host.querySelectorAll('[data-name-part]'), function (input) {
            input.addEventListener('input', function () { composeNameFromHost(host, mode, role); });
            input.addEventListener('change', function () { composeNameFromHost(host, mode, role); });
        });

        Array.prototype.forEach.call(host.querySelectorAll('[data-geo-search]'), function (input) {
            input.addEventListener('input', function () { filterGeoSelect(input); });
            input.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                applyFirstGeoMatch(input);
            });
        });

        var street = host.querySelector('[data-address-street="1"]');
        if (street) {
            street.addEventListener('input', function () { composeAddressFromHost(host, mode, originalAddress); });
        }

        var provinceSelect = host.querySelector('[data-geo-role="province"]');
        if (provinceSelect) {
            provinceSelect.addEventListener('change', function () { onGeoProvinceChange(mode); composeAddressFromHost(host, mode, originalAddress); });
        }
        var citySelect = host.querySelector('[data-geo-role="city"]');
        if (citySelect) {
            citySelect.addEventListener('change', function () { onGeoCityChange(mode); composeAddressFromHost(host, mode, originalAddress); });
        }
        var barangaySelect = host.querySelector('[data-geo-role="barangay"]');
        if (barangaySelect) {
            barangaySelect.addEventListener('change', function () { composeAddressFromHost(host, mode, originalAddress); });
        }

        loadGeoProvinces(mode);

        // Server-side field errors clear as soon as the field is edited.
        // Capture phase so a fresh live-validation message for the same
        // field is never wiped by this handler.
        if (!host.__rmgServerErrorWired) {
            host.__rmgServerErrorWired = true;
            host.addEventListener('input', function (event) { clearFieldServerError(event.target); }, true);
            host.addEventListener('change', function (event) { clearFieldServerError(event.target); }, true);
        }

        if (mode === 'edit' && window.RMG_EDIT_ITEM && window.RMG_EDIT_ITEM.address) {
            var addressComposite = host.querySelector('[data-address-composite="1"]');
            if (addressComposite) addressComposite.value = window.RMG_EDIT_ITEM.address;
        }
    }

    function stepIndexForRole(role, key) {
        var steps = wizardStepsFor(role);
        for (var i = 0; i < steps.length; i++) {
            if (steps[i].key === key) return i;
        }
        return -1;
    }

    function activeStepCard(mode) {
        var role = mode === 'add' ? el('rmgAddRole').value : (window.RMG_EDIT_ITEM ? window.RMG_EDIT_ITEM.role : '');
        var steps = wizardStepsFor(role);
        var index = state.wizard[mode] || 0;
        var key = steps[index] ? steps[index].key : 'personal';
        var host = el(mode === 'add' ? 'rmgAddFields' : 'rmgEditFields');
        return host && host.querySelector ? host.querySelector('[data-step="' + key + '"]') : null;
    }

    /**
     * Validate the required fields of the current step before moving forward.
     * Optional fields are only checked once touched.
     */
    function validateStepFields(mode) {
        var card = activeStepCard(mode);
        if (!card) return true;
        var prefix = mode === 'add' ? 'data-add-field' : 'data-edit-field';
        var ok = true;

        Array.prototype.forEach.call(card.querySelectorAll('[' + prefix + '][data-required="1"]'), function (input) {
            if (!liveValidateInput(input)) {
                ok = false;
            }
        });

        // Composite name requirements. Add mode still requires the full
        // name; the Edit form keeps every field optional except the photo.
        var first = mode === 'add' ? card.querySelector('[data-name-part="first"]') : null;
        var last = mode === 'add' ? card.querySelector('[data-name-part="last"]') : null;
        if (first && last) {
            var partsOk = first.value.trim() !== '' && last.value.trim() !== '';
            [first, last].forEach(function (input) {
                if (!input.classList) return;
                input.classList.toggle('is-invalid', !partsOk && (input === first || input.value.trim() !== ''));
            });
            if (!partsOk) ok = false;
        }

        return ok;
    }

    function goToWizardStep(mode, index) {
        var role = mode === 'add' ? el('rmgAddRole').value : (window.RMG_EDIT_ITEM ? window.RMG_EDIT_ITEM.role : '');
        var steps = wizardStepsFor(role);
        var max = steps.length - 1;
        index = Math.max(0, Math.min(max, index));

        if (index > (state.wizard[mode] || 0) && !validateStepFields(mode)) {
            rmgNotify('error', 'Check this step', 'Please complete the highlighted fields before continuing.');
            return;
        }

        state.wizard[mode] = index;
        renderWizardSteps(mode === 'add' ? 'rmgAddSteps' : 'rmgEditSteps', mode, role);

        var key = steps[index] ? steps[index].key : 'personal';
        if (key === 'review') {
            var reviewHost = el(mode === 'add' ? 'rmgAddReview' : 'rmgEditReview');
            if (reviewHost) {
                reviewHost.hidden = false;
                reviewHost.innerHTML = renderWizardReview(mode);
            }
        }

        var host = el(mode === 'add' ? 'rmgAddFields' : 'rmgEditFields');
        var reviewHost = el(mode === 'add' ? 'rmgAddReview' : 'rmgEditReview');
        var card = host && host.querySelector ? host.querySelector('[data-step="' + key + '"]') : null;
        if (key === 'review' && reviewHost) {
            card = reviewHost;
        }
        if (card && card.scrollIntoView) {
            card.scrollIntoView({behavior: 'smooth', block: 'start'});
        }
        updateWizardNav(mode);
    }

    function updateWizardNav(mode) {
        var role = mode === 'add' ? el('rmgAddRole').value : (window.RMG_EDIT_ITEM ? window.RMG_EDIT_ITEM.role : '');
        var steps = wizardStepsFor(role);
        var index = state.wizard[mode] || 0;
        var back = el(mode === 'add' ? 'rmgAddBack' : 'rmgEditBack');
        var next = el(mode === 'add' ? 'rmgAddNext' : 'rmgEditNext');
        if (back) back.disabled = index <= 0;
        if (next) {
            next.disabled = index >= steps.length - 1;
            next.innerHTML = index >= steps.length - 2 ? 'Review <i class="bi bi-arrow-right"></i>' : 'Next <i class="bi bi-arrow-right"></i>';
        }
    }

    function updateWizardReview(mode) {
        var reviewHost = el(mode === 'add' ? 'rmgAddReview' : 'rmgEditReview');
        if (!reviewHost || reviewHost.hidden) return;
        var reason = reviewHost.querySelector('[data-edit-field="reason"]');
        var reasonValue = reason ? reason.value : '';
        reviewHost.innerHTML = renderWizardReview(mode);
        var newReason = reviewHost.querySelector('[data-edit-field="reason"]');
        if (newReason) newReason.value = reasonValue;
    }

    function renderAddFields() {
        var role = el('rmgAddRole').value;
        var kicker = el('rmgAddKicker');
        if (kicker) {
            kicker.textContent = ((CFG.roles || {})[role] || 'New') + ' account';
        }
        state.wizard.add = 0;
        renderWizardFields('rmgAddFields', 'add', role, state.addValues || null);
        renderWizardSteps('rmgAddSteps', 'add', role);
        updateWizardNav('add');
        bindAddDuplicateCheck();
        renderDuplicates([]);
    }

    /* ------------------------- duplicate detection ------------------------- */

    var duplicateCheckTimer = null;

    function bindAddDuplicateCheck() {
        var host = el('rmgAddFields');
        if (!host || !host.querySelector) return;
        ['email', 'username', 'contact_number', 'contact_no'].forEach(function (name) {
            var input = host.querySelector('[data-add-field="' + name + '"]');
            if (!input || !input.addEventListener) return;
            input.addEventListener('input', scheduleDuplicateCheck);
            input.addEventListener('change', scheduleDuplicateCheck);
        });
    }

    function scheduleDuplicateCheck() {
        clearTimeout(duplicateCheckTimer);
        duplicateCheckTimer = setTimeout(runDuplicateCheck, 400);
    }

    function runDuplicateCheck() {
        var host = el('rmgAddFields');
        if (!host || !host.querySelector) return;

        function value(name) {
            var node = host.querySelector('[data-add-field="' + name + '"]');
            return node ? String(node.value || '').trim() : '';
        }

        var payload = {
            role: el('rmgAddRole') ? el('rmgAddRole').value : '',
            email: value('email'),
            username: value('username'),
            contact: value('contact_number') || value('contact_no')
        };

        if (payload.email === '' && payload.username === '' && payload.contact === '') {
            renderDuplicates([]);
            return;
        }

        fetch('/admin/api/RoleManagement/Duplicates.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (res) { renderDuplicates(res && res.duplicates); })
            .catch(function () { renderDuplicates([]); });
    }

    function renderDuplicates(list) {
        var host = el('rmgAddDuplicates');
        if (!host) return;
        if (!Array.isArray(list) || !list.length) {
            host.hidden = true;
            host.innerHTML = '';
            return;
        }
        host.hidden = false;
        host.innerHTML = '<div class="rmg-dup-title"><i class="bi bi-exclamation-triangle"></i> Possible duplicate ' + (list.length === 1 ? 'account' : 'accounts') + ' found</div>' +
            '<ul>' + list.map(function (match) {
                var matched = (match.matched || []).join(', ');
                return '<li><strong>' + esc(match.name) + '</strong> — ' + esc(match.role_label) + ' · ' + esc(match.user_id) + ' (matches ' + esc(matched) + ')</li>';
            }).join('') + '</ul>' +
            '<div class="rmg-field-hint">Review before creating. If this is the same person needing another role, review the existing account instead of creating a duplicate identity.</div>';
    }

    function fieldSetsForLegacy(role) {
        if (role === 'inspector' || role === 'dispatcher') return FIELD_SETS.inspector;
        if (role === 'finance_officer' || role === 'finance_manager') return FIELD_SETS.finance;
        if (role === 'driver') return FIELD_SETS.driver;
        return FIELD_SETS.admin;
    }

    function syncUrl() {
        if (!window.history || !history.replaceState) return;
        var params = new URLSearchParams();
        if (state.role !== 'all') params.set('role', state.role);
        if (state.search) params.set('search', state.search);
        if (state.status) params.set('status', state.status);
        if (state.department) params.set('department', state.department);
        if (state.employment) params.set('employment', state.employment);
        if (state.createdFrom) params.set('created_from', state.createdFrom);
        if (state.createdTo) params.set('created_to', state.createdTo);
        if (state.sort && state.sort !== 'newest') params.set('sort', state.sort);
        if (state.page > 1) params.set('page', String(state.page));
        if (state.perPage !== 10) params.set('per_page', String(state.perPage));
        var qs = params.toString();
        history.replaceState(null, '', '/admin/role-management' + (qs ? '?' + qs : ''));
    }

    var SUMMARY_DEFS = [
        {key: 'total', label: 'Total Accounts', icon: 'bi-people', tone: 'total'},
        {key: 'active', label: 'Active', icon: 'bi-person-check', tone: 'active'},
        {key: 'inactive', label: 'Inactive', icon: 'bi-person-dash', tone: 'inactive'},
        {key: 'archived', label: 'Archived', icon: 'bi-archive', tone: 'archived'},
        {key: 'pending', label: 'Pending Activation', icon: 'bi-hourglass-split', tone: 'pending'}
    ];

    /**
     * Compact role filter with live counts. Replaces the old oversized tabs;
     * the same counts still feed the "All roles" total so it can never
     * disagree with the per-role numbers.
     */
    function populateRoleFilter() {
        var select = el('rmgRole');
        if (!select) return;
        var roles = CFG.roles || {};
        var allCount = 0;
        Object.keys(roles).forEach(function (role) { allCount += state.counts[role] || 0; });

        var html = '<option value="all">All roles (' + allCount + ')</option>';
        Object.keys(roles).forEach(function (role) {
            html += '<option value="' + esc(role) + '">' + esc(roles[role]) + ' (' + (state.counts[role] || 0) + ')</option>';
        });
        select.innerHTML = html;
        select.value = state.role;
    }

    function renderSummary() {
        var host = el('rmgSummary');
        if (!host) return;
        var summary = state.summary || {};
        host.innerHTML = SUMMARY_DEFS.map(function (def) {
            var value = summary[def.key] !== undefined ? summary[def.key] : '—';
            return '<div class="rmg-sum-card tone-' + def.tone + '">' +
                '<span class="rmg-sum-icon"><i class="bi ' + def.icon + '"></i></span>' +
                '<div class="rmg-sum-body"><div class="rmg-sum-label">' + esc(def.label) + '</div>' +
                '<div class="rmg-sum-value">' + value + '</div></div></div>';
        }).join('');
    }

    function renderSkeleton() {
        var host = el('rmgList');
        if (!host) return;
        var rows = '';
        for (var i = 0; i < 6; i++) {
            rows += '<div class="rmg-skel-row">' +
                '<span class="rmg-skel rmg-skel-avatar"></span>' +
                '<span class="rmg-skel rmg-skel-line"></span>' +
                '<span class="rmg-skel rmg-skel-line short"></span>' +
                '<span class="rmg-skel rmg-skel-chip"></span></div>';
        }
        host.innerHTML = '<div class="rmg-skel-wrap" aria-busy="true" aria-label="Loading accounts">' + rows + '</div>';
    }

    function renderStatusOptions() {
        var select = el('rmgStatus');
        if (!select || select.options.length > 1) return;
        Object.keys(CFG.statuses || {}).forEach(function (key) {
            var option = document.createElement('option');
            option.value = key;
            option.textContent = CFG.statuses[key];
            select.appendChild(option);
        });
    }

    /**
     * Populate the Department and Employment filter selects from the same
     * database-backed option source the forms use.
     */
    function loadFilterOptions() {
        ensureAddOptionData(function () {
            var department = el('rmgDepartment');
            if (department) {
                var current = state.department;
                var html = '<option value="">All departments</option>';
                ((state.addOptions || {}).departments || []).forEach(function (option) {
                    html += '<option value="' + esc(option.id) + '">' + esc(option.label) + '</option>';
                });
                department.innerHTML = html;
                department.value = current || '';
            }

            var employment = el('rmgEmployment');
            if (employment) {
                var currentEmployment = state.employment;
                var list = ((state.addOptions || {}).lists || {}).employment_statuses || [];
                var empHtml = '<option value="">All employment statuses</option>';
                list.forEach(function (value) {
                    empHtml += '<option value="' + esc(value) + '">' + esc(value) + '</option>';
                });
                employment.innerHTML = empHtml;
                employment.value = currentEmployment || '';
            }
        });
    }

    function clearFilters() {
        state.role = 'all';
        state.search = '';
        state.status = '';
        state.department = '';
        state.employment = '';
        state.createdFrom = '';
        state.createdTo = '';
        state.sort = 'newest';
        state.page = 1;

        el('rmgSearch').value = '';
        el('rmgRole').value = 'all';
        el('rmgStatus').value = '';
        if (el('rmgDepartment')) el('rmgDepartment').value = '';
        if (el('rmgEmployment')) el('rmgEmployment').value = '';
        if (el('rmgCreatedFrom')) el('rmgCreatedFrom').value = '';
        if (el('rmgCreatedTo')) el('rmgCreatedTo').value = '';
        if (el('rmgSort')) el('rmgSort').value = 'newest';

        closeDetail(true);
        load();
    }

    /**
     * Row actions, limited by the actor's role permissions.
     */
    function rowActionsHtml(item, index) {
        var actions = '<div class="rmg-actions">';
        actions += '<button type="button" class="rmg-icon-btn is-view" data-action="details" data-index="' + index + '" title="View profile" aria-label="View profile for ' + esc(item.name) + '"><i class="bi bi-eye"></i></button>';

        if (item.can_manage) {
            actions += '<button type="button" class="rmg-icon-btn is-edit" data-action="edit" data-index="' + index + '" title="Edit user" aria-label="Edit ' + esc(item.name) + '"><i class="bi bi-pencil"></i></button>';

            var endpoints = ARCHIVE_ENDPOINTS[item.role];
            if (endpoints) {
                if (item.status === 'archived' || item.status === 'inactive') {
                    actions += '<button type="button" class="rmg-icon-btn is-restore" data-action="restore" data-index="' + index + '" title="Restore" aria-label="Restore ' + esc(item.name) + '"><i class="bi bi-arrow-counterclockwise"></i></button>';
                } else {
                    actions += '<button type="button" class="rmg-icon-btn is-archive" data-action="archive" data-index="' + index + '" title="Archive" aria-label="Archive ' + esc(item.name) + '"><i class="bi bi-archive"></i></button>';
                }
            }
        } else {
            actions += '<span class="rmg-locked-action" title="Only a Super Admin can manage this account"><i class="bi bi-shield-lock"></i></span>';
        }

        actions += '<button type="button" class="rmg-icon-btn is-history" data-action="history" data-index="' + index + '" title="Activity history" aria-label="Activity history for ' + esc(item.name) + '"><i class="bi bi-clock-history"></i></button>';
        actions += '</div>';
        return actions;
    }

    function renderList() {
        var host = el('rmgList');
        var warnings = sourceWarningsHtml();

        if (!state.items.length) {
            var emptyHeading = state.role === 'all' ? 'No accounts found' : 'No ' + ((CFG.roles || {})[state.role] || state.role) + ' accounts found';
            host.innerHTML = warnings + '<div class="rmg-state"><i class="bi bi-inbox"></i><h6>' + esc(emptyHeading) + '</h6>' +
                (state.role === 'all'
                    ? 'Try a different role, search term, or clear some filters.'
                    : 'Accounts appear here once they are created for this role, or try another filter.') +
                '<button type="button" class="rmg-btn rmg-btn-sm" id="rmgEmptyClear" style="margin-top:12px;"><i class="bi bi-x-circle"></i> Clear filters</button>' +
                '</div>';
            return;
        }

        function directoryText(value) {
            var text = esc(value);
            return '<span class="rmg-directory-text" title="' + text + '">' + text + '</span>';
        }

        var rows = state.items.map(function (item, index) {
            var photo = item.photo
                ? '<img src="' + esc(item.photo) + '" alt="">'
                : esc(initials(item.name));
            return '<tr data-index="' + index + '">' +
                '<td data-label="Account"><div class="rmg-person"><div class="rmg-avatar">' + photo + '</div><div style="min-width:0;"><div class="rmg-person-name" title="' + esc(item.name) + '">' + esc(item.name) + '</div><div class="rmg-person-sub" title="' + esc(item.user_id) + '">' + esc(item.user_id) + '</div></div></div></td>' +
                '<td data-label="User ID" class="rmg-td-id">' + directoryText(item.user_id || '—') + '</td>' +
                '<td data-label="Role"><span class="rmg-type-chip" title="' + esc(item.role_label) + '"><i class="bi bi-person-badge"></i> ' + esc(item.role_label) + '</span></td>' +
                '<td data-label="Email">' + directoryText(item.email || '—') + '</td>' +
                '<td data-label="Contact">' + directoryText(item.contact || '—') + '</td>' +
                '<td data-label="Status"><span class="rmg-status ' + statusClass(item.status) + '" title="' + esc(item.status_label) + '">' + esc(item.status_label) + '</span></td>' +
                '<td data-label="Created">' + (state.status === 'archived'
                    ? '<div class="rmg-directory-text" title="' + esc(item.archive_reason || 'Unavailable') + '"><strong>Reason:</strong> ' + esc(item.archive_reason || 'Unavailable') + '</div><div class="rmg-directory-text" title="' + esc(item.archived_at ? fmtDate(item.archived_at) : 'Unavailable') + '"><strong>Archived:</strong> ' + esc(item.archived_at ? fmtDate(item.archived_at) : 'Unavailable') + '</div><div class="rmg-directory-text" title="' + esc(item.archived_by || 'Unavailable') + '"><strong>By:</strong> ' + esc(item.archived_by || 'Unavailable') + '</div>'
                    : directoryText(fmtDate(item.created_at))) + '</td>' +
                '<td data-label="Actions" class="rmg-td-actions">' + rowActionsHtml(item, index) + '</td>' +
                '</tr>';
        }).join('');

        host.innerHTML = warnings +
            '<div class="rmg-table-wrap">' +
            '<table class="rmg-table"><colgroup>' +
            '<col style="width:20%;"><col><col><col style="width:20%;">' +
            '<col><col><col><col style="width:138px;">' +
            '</colgroup><thead><tr>' +
            '<th>Account</th><th>User ID</th><th>Role</th><th>Email</th><th>Contact</th><th>Status</th><th>' + (state.status === 'archived' ? 'Archive Details' : 'Created') + '</th><th class="rmg-th-actions">Actions</th>' +
            '</tr></thead><tbody>' + rows + '</tbody></table></div>';
    }

    function sourceWarningsHtml() {
        var errors = state.sourceErrors || {};
        var messages = [];
        Object.keys(errors).forEach(function (role) {
            var message = String(errors[role] || '').trim();
            if (message !== '' && messages.indexOf(message) === -1) {
                messages.push(message);
            }
        });
        if (!messages.length) {
            return '';
        }
        return messages.map(function (message) {
            return '<div class="rmg-note" style="margin-bottom:10px;"><i class="bi bi-exclamation-triangle"></i> ' + esc(message) + '</div>';
        }).join('');
    }

    /**
     * Render one displayed value. Missing values are explicitly marked so an
     * empty field is never mistaken for data or filled with another account's.
     */
    function displayValue(value) {
        if (value === null || value === undefined) {
            return '<span class="rmg-missing">Not provided</span>';
        }
        var text = String(value).trim();
        if (text === '') {
            return '<span class="rmg-missing">Not provided</span>';
        }
        return esc(text);
    }

    function detailField(label, value) {
        var text = String(value === null || value === undefined ? '' : value).trim();
        var display = /^\d{4}-\d{2}-\d{2}$/.test(text) ? fmtDate(text) : value;
        return '<div class="rmg-field"><div class="k">' + esc(label) + '</div><div class="v">' + displayValue(display) + '</div></div>';
    }

    function statTile(icon, label, value, tone) {
        return '<div class="rmg-stat">' +
            '<span class="rmg-stat-icon' + (tone ? ' tone-' + tone : '') + '"><i class="bi ' + icon + '"></i></span>' +
            '<div class="rmg-stat-body"><div class="rmg-stat-label">' + esc(label) + '</div>' +
            '<div class="rmg-stat-value">' + displayValue(value) + '</div></div></div>';
    }

    function detailSectionHtml(title, icon, rows, tone, extraHtml) {
        return '<section class="rmg-detail-section">' +
            '<div class="rmg-section-head"><span class="rmg-section-icon' + (tone ? ' tone-' + tone : '') + '"><i class="bi ' + icon + '"></i></span>' +
            '<div class="rmg-section-title">' + esc(title) + '</div></div>' +
            (extraHtml || '') +
            '<div class="rmg-detail-grid">' + rows.map(function (row) { return detailField(row[0], row[1]); }).join('') + '</div>' +
            '</section>';
    }

    /**
     * Compose the modal body from the account's real record. Fields are placed
     * by meaning; anything the record does not carry is left out of its
     * section rather than guessed.
     */
    function detailSectionsHtml(item) {
        var extras = (Array.isArray(item.extra) ? item.extra.slice() : []).map(function (field) {
            return { label: String(field.label || ''), value: field.value };
        });
        function pull(pattern) {
            var taken = [];
            for (var i = extras.length - 1; i >= 0; i--) {
                if (pattern.test(extras[i].label)) taken.unshift(extras.splice(i, 1)[0]);
            }
            return taken;
        }

        var personalExtras = pull(/birth|age|address|gender|sex|nationality/i);
        var accessExtras = pull(/access|level|privilege|permission|shift/i);

        var personalRows = [
            ['Full name', item.name],
            ['Email', item.email],
            ['Contact', item.contact],
            ['Sex', item.sex]
        ].concat(personalExtras.map(function (field) { return [field.label, field.value]; }));

        var accountRows = [
            ['User ID', item.user_id],
            ['Date created', item.created_at ? fmtDate(item.created_at, true) : ''],
            ['Created by', item.created_by],
            ['Last updated', item.updated_at ? fmtDate(item.updated_at, true) : ''],
            ['Department', item.department],
            ['Assignment', item.assignment],
            ['Address', item.address]
        ].concat(extras.map(function (field) { return [field.label, field.value]; }));

        var roleRows = [
            ['Role', item.role_label],
            ['Account type', item.kind === 'driver' ? 'Driver record' : (item.kind === 'inspector' ? 'Inspector record' : 'System user account')]
        ].concat(accessExtras.map(function (field) { return [field.label, field.value]; }));

        var history = state.historyCache[item.role + ':' + item.ref];
        var statusRows = [];
        if (Array.isArray(history) && history.length) {
            var latest = history[0] || {};
            statusRows.push(['Last change', (latest.action || 'Change') + ' · ' + (latest.username || 'unknown') + ' · ' + fmtDate(latest.created_at, true)]);
        } else {
            statusRows.push(['Last change', 'No recorded changes yet.']);
        }
        if (!item.can_manage) {
            statusRows.push(['Management', 'Only a Super Admin can manage this account.']);
        }

        var statusBanner = '<div class="rmg-status-banner ' + statusClass(item.status) + '">' +
            '<span class="rmg-status-dot" aria-hidden="true"></span>' +
            '<span class="rmg-status-text">' + displayValue(item.status_label) + '</span></div>';

        var stats = '<div class="rmg-detail-stats">' +
            statTile('bi-hash', 'User ID', item.user_id) +
            statTile('bi-person-badge', 'Role', item.role_label) +
            statTile('bi-activity', 'Status', item.status_label, 'status') +
            statTile('bi-calendar3', 'Date created', item.created_at ? fmtDate(item.created_at) : '', 'account') +
            '</div>';

        var docs = '';
        if (item.documents && item.documents.resume) {
            docs += '<a href="' + esc(item.documents.resume) + '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> Resume / CV</a>';
        }
        if (item.documents && item.documents.supporting) {
            docs += '<a href="' + esc(item.documents.supporting) + '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-text"></i> Supporting documents</a>';
        }
        var docsHtml = '<section class="rmg-detail-section">' +
            '<div class="rmg-section-head"><span class="rmg-section-icon tone-docs"><i class="bi bi-paperclip"></i></span>' +
            '<div class="rmg-section-title">Documents</div></div>' +
            '<div class="rmg-docs">' + (docs || '<span class="rmg-missing">No documents on file.</span>') + '</div></section>';

        var cvHtml = item.can_manage ? renderCvBlock(item, state.detailIndex) : '';

        var assignmentPattern = /bus|route|terminal|area|office|shift|assignment/i;
        var assignmentsRows = [
            ['Department', item.department],
            ['Assignment', item.assignment]
        ].concat(
            (Array.isArray(item.role_specific) ? item.role_specific : [])
                .filter(function (field) { return assignmentPattern.test(field.label); })
                .map(function (field) { return [field.label, field.value]; })
        ).concat(
            (Array.isArray(item.extra) ? item.extra : [])
                .filter(function (field) { return assignmentPattern.test(field.label); })
                .map(function (field) { return [field.label, field.value]; })
        );

        var employmentRows = Array.isArray(item.employment)
            ? item.employment.map(function (field) { return [field.label, field.value]; })
            : [];
        var roleSpecificRows = Array.isArray(item.role_specific)
            ? item.role_specific.map(function (field) { return [field.label, field.value]; })
            : [];

        var tabs = [
            {
                key: 'overview',
                title: 'Overview',
                html: stats + statusBanner +
                    detailSectionHtml('Account Information', 'bi-person-vcard', accountRows, 'account') +
                    docsHtml + cvHtml
            },
            {
                key: 'personal',
                title: 'Personal Information',
                html: detailSectionHtml('Personal Information', 'bi-person', personalRows, 'personal')
            },
            {
                key: 'employment',
                title: 'Employment Information',
                html: employmentRows.length
                    ? detailSectionHtml('Employment Information', 'bi-briefcase', employmentRows, 'account')
                    : '<div class="rmg-state rmg-state-sm"><i class="bi bi-briefcase"></i><h6>No employment information</h6>Employment details have not been recorded for this account yet.</div>'
            },
            {
                key: 'role',
                title: 'Role & Permissions',
                html: detailSectionHtml('Role and Access', 'bi-shield-lock', roleRows, 'role') +
                    (roleSpecificRows.length ? detailSectionHtml('Role-Specific Details', 'bi-shield-check', roleSpecificRows, 'role') : '')
            },
            {
                key: 'assignments',
                title: 'Assignments',
                html: assignmentsRows.length
                    ? detailSectionHtml('Assignments', 'bi-diagram-3', assignmentsRows, 'account')
                    : '<div class="rmg-state rmg-state-sm"><i class="bi bi-diagram-3"></i><h6>No assignments</h6>No bus, route, terminal, or office assignments are recorded.</div>'
            },
            {
                key: 'activity',
                title: 'Security & Activity',
                html: detailSectionHtml('Status', 'bi-activity', statusRows, 'status') + historyBlockHtml(item)
            }
        ];

        return detailTabsHtml(tabs);
    }

    /**
     * Bootstrap tab markup for the profile modal. The active tab is kept in
     * state so a background refresh (history/CV) does not reset it.
     */
    function detailTabsHtml(tabs) {
        var active = state.detailTab || 'overview';
        var known = false;
        tabs.forEach(function (tab) { if (tab.key === active) known = true; });
        if (!known) active = tabs[0] ? tabs[0].key : 'overview';

        var nav = '<ul class="nav nav-tabs rmg-detail-tabs" role="tablist">';
        var panes = '<div class="tab-content rmg-detail-tab-content">';

        tabs.forEach(function (tab) {
            var isActive = tab.key === active;
            nav += '<li class="nav-item" role="presentation">' +
                '<button class="nav-link' + (isActive ? ' active' : '') + '" data-rmg-tab="' + esc(tab.key) + '" data-bs-toggle="tab" data-bs-target="#rmgDetailPane-' + esc(tab.key) + '" type="button" role="tab" aria-selected="' + (isActive ? 'true' : 'false') + '">' + esc(tab.title) + '</button></li>';
            panes += '<div class="tab-pane fade' + (isActive ? ' show active' : '') + '" id="rmgDetailPane-' + esc(tab.key) + '" role="tabpanel">' + tab.html + '</div>';
        });

        return nav + '</ul>' + panes + '</div>';
    }

    function activateDetailTab(key) {
        state.detailTab = key;
        if (typeof document.querySelector !== 'function') return;
        var button = document.querySelector('.rmg-detail-tabs [data-rmg-tab="' + key + '"]');
        if (button && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            bootstrap.Tab.getOrCreateInstance(button).show();
        }
    }

    function detailActionsHtml(item) {
        if (!item.can_manage) {
            return '<span style="font-size:.72rem;color:#8a9a8a;align-self:center;"><i class="bi bi-shield-lock"></i> Only a Super Admin can manage this account.</span>';
        }
        var actions = '<button type="button" class="rmg-btn rmg-btn-sm rmg-btn-primary" data-action="edit" data-index="' + state.detailIndex + '"><i class="bi bi-pencil"></i> Edit profile</button>';
        var endpoints = ARCHIVE_ENDPOINTS[item.role];
        if (endpoints) {
            if (item.status === 'inactive') {
                actions += '<button type="button" class="rmg-btn rmg-btn-sm" data-action="restore" data-index="' + state.detailIndex + '"><i class="bi bi-arrow-counterclockwise"></i> Reactivate</button>';
            } else {
                actions += '<button type="button" class="rmg-btn rmg-btn-sm rmg-btn-danger" data-action="archive" data-index="' + state.detailIndex + '"><i class="bi bi-archive"></i> Deactivate / Archive</button>';
            }
        }
        return actions;
    }

    function renderDetailModal() {
        var item = findItem(state.detailIndex);
        if (!item || state.detailIndex === null) return;
        el('rmgDetailName').textContent = item.name || 'Account';
        el('rmgDetailKicker').textContent = (item.role_label || 'Account') + ' account';
        el('rmgDetailAvatarWrap').className = 'rmg-detail-avatar-wrap ' + statusClass(item.status);
        el('rmgDetailAvatar').innerHTML = item.photo
            ? '<img src="' + esc(item.photo) + '" alt="">'
            : esc(initials(item.name || ''));
        el('rmgDetailChips').innerHTML = '<span class="rmg-type-chip"><i class="bi bi-person-badge"></i> ' + esc(item.role_label || '') + '</span>' +
            '<span class="rmg-status ' + statusClass(item.status) + '">' + esc(item.status_label || '') + '</span>';
        el('rmgDetailBody').innerHTML = detailSectionsHtml(item);
        el('rmgDetailActions').innerHTML = detailActionsHtml(item);
    }

    function openDetail(index) {
        var item = findItem(index);
        if (!item) return;
        state.detailIndex = Number(index);
        renderDetailModal();
        loadCv(item);
        loadHistory(item);
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(el('rmgDetailModal')).show();
        }
    }

    function closeDetail(hide) {
        state.detailIndex = null;
        if (hide && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var modal = bootstrap.Modal.getInstance(el('rmgDetailModal'));
            if (modal) modal.hide();
        }
    }

    /**
     * Bootstrap 5 does not manage z-index for stacked modals. The details
     * modal sits last in the DOM, so anything opened while it is visible would
     * appear behind it. Close it first, then run the opener.
     */
    function closeDetailBefore(open) {
        var detailModal = el('rmgDetailModal');
        if (!detailModal || !detailModal.classList || !detailModal.classList.contains('show')) {
            open();
            return;
        }
        var onHidden = function () {
            detailModal.removeEventListener('hidden.bs.modal', onHidden);
            open();
        };
        detailModal.addEventListener('hidden.bs.modal', onHidden);
        closeDetail(true);
    }

    function renderCvBlock(item, index) {
        var key = item.cv_subject + ':' + item.ref;
        var cached = state.cvCache[key];
        var resumeLink = (item.documents && item.documents.resume)
            ? '<a class="rmg-cv-file" href="' + esc(item.documents.resume) + '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> Current resume</a>'
            : '<span class="rmg-cv-file">No resume on file.</span>';

        var editorHtml = cached ? cvEditorHtml(cached) : cvEditorHtml({ education: [], experience: [], skills: [], resume_text: '' });

        return '<div class="rmg-cv" data-cv-key="' + esc(key) + '" data-cv-index="' + index + '">' +
            '<div class="rmg-cv-head">' +
            '<h6 class="rmg-cv-title"><i class="bi bi-file-earmark-person"></i> CV / Resume &amp; Extraction</h6>' +
            '<div class="rmg-cv-actions">' + resumeLink +
            '<label class="rmg-btn rmg-btn-sm" style="cursor:pointer;"><i class="bi bi-upload"></i> Choose CV<input type="file" class="rmg-cv-input" accept=".pdf,.doc,.docx" style="display:none;"></label>' +
            '<button type="button" class="rmg-btn rmg-btn-sm rmg-btn-primary" data-action="cv-extract" data-index="' + index + '"><i class="bi bi-magic"></i> Extract CV text</button>' +
            (cached ? '<button type="button" class="rmg-btn rmg-btn-sm" data-action="cv-toggle" data-index="' + index + '"><i class="bi bi-pencil-square"></i> Review / edit</button>' : '') +
            '</div></div>' +
            '<div class="rmg-cv-note" data-cv-note hidden></div>' +
            '<div class="rmg-cv-editor' + (cached ? ' open' : '') + '" data-cv-editor>' + editorHtml + '</div>' +
            '</div>';
    }

    function cvEditorHtml(cv) {
        var edu = (cv.education || []).map(function (row) { return eduRowHtml(row); }).join('');
        var exp = (cv.experience || []).map(function (row) { return expRowHtml(row); }).join('');
        var skills = Array.isArray(cv.skills) ? cv.skills.join(', ') : String(cv.skills || '');
        var text = String(cv.resume_text || '');

        return '<div class="rmg-cv-block"><div class="rmg-cv-block-title">Education <button type="button" class="rmg-btn rmg-btn-sm" data-cv-add="edu"><i class="bi bi-plus"></i> Add</button></div>' +
            '<div data-cv-list="edu">' + edu + '</div></div>' +
            '<div class="rmg-cv-block"><div class="rmg-cv-block-title">Work experience <button type="button" class="rmg-btn rmg-btn-sm" data-cv-add="exp"><i class="bi bi-plus"></i> Add</button></div>' +
            '<div data-cv-list="exp">' + exp + '</div></div>' +
            '<div class="rmg-cv-block"><div class="rmg-cv-block-title">Skills (comma separated)</div><div class="rmg-skills"><input type="text" data-cv-skills value="' + esc(skills) + '" placeholder="e.g. Defensive driving, Dispatch coordination"></div></div>' +
            (text ? '<details class="rmg-cv-block"><summary style="font-size:.68rem;font-weight:700;color:#295738;cursor:pointer;">Extracted resume text</summary><textarea data-cv-text rows="6" style="width:100%;margin-top:6px;border:1px solid #e5e0a4;border-radius:8px;padding:8px;font-size:.7rem;">' + esc(text) + '</textarea></details>' : '<input type="hidden" data-cv-text value="">') +
            '<div class="rmg-cv-actions"><button type="button" class="rmg-btn rmg-btn-sm rmg-btn-primary" data-action="cv-save"><i class="bi bi-check2"></i> Save CV data</button>' +
            '<button type="button" class="rmg-btn rmg-btn-sm" data-action="cv-cancel">Cancel</button></div>';
    }

    function eduRowHtml(row) {
        row = row || {};
        return '<div class="rmg-cv-item edu" data-cv-item="edu">' +
            '<input type="text" data-field="degree" placeholder="Degree / course" value="' + esc(row.degree || '') + '">' +
            '<input type="text" data-field="institution" placeholder="Institution" value="' + esc(row.institution || '') + '">' +
            '<input type="text" data-field="year" placeholder="Year" value="' + esc(row.year || '') + '">' +
            '<button type="button" class="rmg-cv-remove" data-cv-remove title="Remove">&times;</button>' +
            '</div>';
    }

    function expRowHtml(row) {
        row = row || {};
        return '<div class="rmg-cv-item exp" data-cv-item="exp">' +
            '<input type="text" data-field="position" placeholder="Position" value="' + esc(row.position || '') + '">' +
            '<input type="text" data-field="organization" placeholder="Organization" value="' + esc(row.organization || '') + '">' +
            '<input type="text" data-field="period" placeholder="Period" value="' + esc(row.period || '') + '">' +
            '<input type="text" data-field="description" placeholder="Short description" value="' + esc(row.description || '') + '">' +
            '<button type="button" class="rmg-cv-remove" data-cv-remove title="Remove">&times;</button>' +
            '</div>';
    }

    function renderPager() {
        el('rmgCount').innerHTML = '<i class="bi bi-people"></i> ' + state.total + ' account' + (state.total === 1 ? '' : 's');
        var start = state.total === 0 ? 0 : (state.page - 1) * state.perPage + 1;
        var end = Math.min(state.total, state.page * state.perPage);
        el('rmgPgInfo').textContent = 'Showing ' + start + '–' + end + ' of ' + state.total;

        var host = el('rmgPager');
        if (state.pages <= 1) { host.innerHTML = ''; return; }

        var html = '<button type="button" class="rmg-pg-btn" data-page="' + (state.page - 1) + '"' + (state.page <= 1 ? ' disabled' : '') + '><i class="bi bi-chevron-left"></i></button>';
        var from = Math.max(1, state.page - 2);
        var to = Math.min(state.pages, from + 4);
        from = Math.max(1, to - 4);
        for (var p = from; p <= to; p++) {
            html += '<button type="button" class="rmg-pg-btn' + (p === state.page ? ' active' : '') + '" data-page="' + p + '">' + p + '</button>';
        }
        html += '<button type="button" class="rmg-pg-btn" data-page="' + (state.page + 1) + '"' + (state.page >= state.pages ? ' disabled' : '') + '><i class="bi bi-chevron-right"></i></button>';
        host.innerHTML = html;
    }

    function load() {
        if (el('rmgDirectoryTitle')) { el('rmgDirectoryTitle').textContent = state.status === 'archived' ? 'Archived Users' : 'Role Management'; }
        var archivedButton = el('rmgArchivedUsers');
        if (archivedButton) { archivedButton.textContent = state.status === 'archived' ? 'Back to Managed Users' : 'Archived Users'; }
        state.busy = true;
        renderSkeleton();
        var params = new URLSearchParams();
        params.set('role', state.role);
        if (state.search) params.set('search', state.search);
        if (state.status) params.set('status', state.status);
        if (state.department) params.set('department', state.department);
        if (state.employment) params.set('employment', state.employment);
        if (state.createdFrom) params.set('created_from', state.createdFrom);
        if (state.createdTo) params.set('created_to', state.createdTo);
        if (state.sort) params.set('sort', state.sort);
        params.set('page', String(state.page));
        params.set('per_page', String(state.perPage));

        fetch('/admin/api/RoleManagement/list.php?' + params.toString(), { cache: 'no-store', credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) throw new Error(res.message || 'Could not load the directory.');
                state.items = res.data || [];
                state.counts = res.counts || {};
                state.sourceErrors = res.errors || {};
                state.summary = res.summary || state.summary || {};
                state.counts.all = Object.keys(res.counts || {}).reduce(function (sum, key) { return sum + (res.counts[key] || 0); }, 0);
                state.total = res.total || 0;
                state.pages = res.pages || 1;
                state.page = res.page || 1;
                state.isSuper = !!res.is_super;
                closeDetail(true);
                syncUrl();
                renderSummary();
                populateRoleFilter();
                if (state.role !== 'all' && state.sourceErrors[state.role]) {
                    el('rmgList').innerHTML = '<div class="rmg-state error"><i class="bi bi-exclamation-circle"></i><h6>' +
                        esc((CFG.roles || {})[state.role] || state.role) + ' accounts could not be loaded</h6>' +
                        esc(state.sourceErrors[state.role]) + '</div>';
                } else {
                    renderList();
                }
                renderPager();

                if (state.openProfile) {
                    var target = String(state.openProfile);
                    var found = -1;
                    state.items.forEach(function (item, i) { if (String(item.ref) === target) found = i; });
                    if (found >= 0) {
                        openDetail(found);
                    }
                    state.openProfile = '';
                }
            })
            .catch(function (err) {
                el('rmgList').innerHTML = '<div class="rmg-state error"><i class="bi bi-exclamation-circle"></i><h6>Could not load accounts</h6>' + esc(err.message || 'Please try again.') + '</div>';
            })
            .finally(function () { state.busy = false; });
    }

    function loadCv(item) {
        var key = item.cv_subject + ':' + item.ref;
        if (state.cvCache[key]) return;
        fetch('/admin/api/RoleManagement/CvGet.php?subject_type=' + encodeURIComponent(item.cv_subject) + '&subject_ref=' + encodeURIComponent(item.ref), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.success && res.data) {
                    state.cvCache[key] = res.data;
                    if (state.detailIndex !== null) renderDetailModal();
                }
            })
            .catch(function () {});
    }

    function loadHistory(item) {
        var key = item.role + ':' + item.ref;
        if (state.historyCache[key]) return;
        fetch('/admin/api/RoleManagement/History.php?role=' + encodeURIComponent(item.role) + '&ref=' + encodeURIComponent(item.ref), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                state.historyCache[key] = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                if (state.detailIndex !== null) renderDetailModal();
            })
            .catch(function () {
                state.historyCache[key] = [];
            });
    }

    function historyBlockHtml(item) {
        var entries = state.historyCache[item.role + ':' + item.ref];
        var html = '<div class="rmg-history"><div class="rmg-block-title"><i class="bi bi-clock-history"></i> Recent changes (audit)</div>';
        if (entries && entries.length) {
            html += '<ul>' + entries.map(function (entry) {
                return '<li><span class="rmg-history-action">' + esc(entry.action || 'Change') + '</span> ' + esc(entry.description || '') +
                    ' <span class="rmg-history-meta">— ' + esc(entry.username || 'unknown') + ' · ' + esc(fmtDate(entry.created_at, true)) + '</span></li>';
            }).join('') + '</ul>';
        } else {
            html += '<div class="rmg-history-empty">No recorded changes yet.</div>';
        }
        return html + '</div>';
    }

    function findItem(index) {
        return state.items[Number(index)] || null;
    }

    /**
     * Show a message using the existing shared custom alert overlay.
     */
    function rmgNotify(kind, title, text) {
        if (typeof window.showAlert === 'function') {
            window.showAlert(kind === 'success' ? 'success' : 'error', title || '', text || '');
            return;
        }
        console.error('Role Management custom alert component unavailable:', title, text);
    }

    /**
     * Export the current filtered directory from the server. The response is
     * either JSON (empty result / error - show it and download nothing) or the
     * CSV file streamed from the real records.
     */
    function exportCsv() {
        var button = el('rmgExport');
        if (button && button.disabled) return;
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Exporting...';
        }

        var params = new URLSearchParams();
        params.set('role', state.role);
        if (state.search) params.set('search', state.search);
        if (state.status) params.set('status', state.status);

        fetch('/admin/api/RoleManagement/export.php?' + params.toString(), {cache: 'no-store', credentials: 'same-origin'})
            .then(function (response) {
                var type = (response.headers && response.headers.get('Content-Type')) || '';
                if (!response.ok || type.indexOf('application/json') !== -1) {
                    return response.text().then(function (text) {
                        var message = 'Could not export the directory.';
                        try {
                            var json = JSON.parse(text);
                            if (json && json.message) message = json.message;
                        } catch (e) {
                        }
                        throw new Error(message);
                    });
                }
                return response.blob().then(function (blob) {
                    if (!blob || blob.size === 0) {
                        throw new Error('The export returned no data. Please try again.');
                    }
                    var url = URL.createObjectURL(blob);
                    var link = document.createElement('a');
                    link.href = url;
                    link.download = 'role-management-' + state.role + '.csv';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
                });
            })
            .catch(function (error) {
                var message = (error && error.message) ? error.message : 'Could not export the directory. Please try again.';
                if (error && error.name === 'TypeError') {
                    message = 'Could not reach the server. Check your connection and try again.';
                }
                rmgNotify('error', 'Export failed', message);
            })
            .finally(function () {
                if (button) {
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-filetype-csv"></i> Export CSV';
                }
            });
    }

    function postForm(url, formData) {
        return fetch(url, { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(function (r) { return r.text().then(function (text) { return { status: r.status, text: text }; }); })
            .then(function (out) {
                var json = null;
                try { json = JSON.parse(out.text); } catch (e) { json = null; }
                if (json && json.success === false) throw new Error(json.message || json.error || 'The request was rejected.');
                if (!json && out.status >= 400) throw new Error('The request failed (HTTP ' + out.status + ').');
                return json || { success: true };
            });
    }

    function postFormJson(url, formData) {
        return fetch(url, { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || json.success === false) throw new Error((json && (json.message || json.error)) || 'The request was rejected.');
                return json;
            });
    }

    function postJson(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (json) {
            if (!json || json.success === false) throw new Error((json && (json.message || json.error)) || 'The request was rejected.');
            return json;
        });
    }

    /**
     * Security Authorization Code flow.
     *
     * Before any account write, the acting admin proves they hold the code the
     * Super Admin set. Verification returns a single-use token bound server-side
     * to the actor, action, target and the SHA-256 of the exact payload, so a
     * token cannot be reused, retargeted, or paired with edited data.
     *
     * The canonical payload string must match SecurityCodeService::canonicalHash
     * byte for byte: lowercased sorted keys, trimmed values, 'k=v' pairs joined
     * with '&', sensitive/transport keys skipped.
     */
    var SECURITY_URL = '/admin/api/RoleManagement/Security.php';
    var HASH_SKIP = ['security_token', 'action', 'verify_action', '_csrf', '_csrf_token', 'code', 'current_code', 'new_code', 'confirm_code'];
    var pendingSecurity = null;

    function securityTargetForRole(role) {
        role = String(role || '');
        if (role === 'inspector' || role === 'dispatcher') return 'inspector';
        if (role === 'finance_officer' || role === 'finance_manager') return 'finance';
        return role;
    }

    /**
     * Must mirror SecurityCodeService::guardTarget byte for byte: the token is
     * bound to "label" for creates and "label:ref" for existing records, with
     * empty/"0" refs omitted.
     */
    function securityTargetWithRef(role, ref) {
        var label = securityTargetForRole(role);
        var value = String(ref === null || ref === undefined ? '' : ref).trim();
        if (value !== '' && value !== '0') return label + ':' + value;
        return label;
    }

    function securityActionLabel(action) {
        if (action === 'add') return 'Create account';
        if (action === 'edit') return 'Edit account';
        if (action === 'archive') return 'Deactivate / archive';
        if (action === 'restore') return 'Reactivate';
        return action;
    }

    function securityTargetLabel(target) {
        var raw = String(target || '');
        var ref = '';
        var colon = raw.indexOf(':');
        if (colon !== -1) {
            ref = raw.slice(colon + 1).trim();
            raw = raw.slice(0, colon);
        }
        var labels = {
            driver: 'Driver',
            inspector: 'Inspector / Dispatcher',
            finance: 'Finance account',
            admin: 'Administrator',
            super_admin: 'Super Admin'
        };
        var base = labels[raw] || raw;
        return ref !== '' ? base + ' #' + ref : base;
    }

    function canonicalPayload(entries) {
        var clean = [];
        (entries || []).forEach(function (pair) {
            var key = String(pair[0]).toLowerCase().trim();
            if (!key || HASH_SKIP.indexOf(key) !== -1) return;

            var value = pair[1];
            if (value === null || value === undefined) value = '';
            else if (typeof value === 'boolean') value = value ? '1' : '0';
            else if (typeof value === 'object') return;
            else value = String(value);

            clean.push([key, value.trim()]);
        });

        clean.sort(function (a, b) { return a[0] < b[0] ? -1 : (a[0] > b[0] ? 1 : 0); });

        return clean.map(function (pair) { return pair[0] + '=' + pair[1]; }).join('&');
    }

    function utf8BinaryString(text) {
        if (typeof TextEncoder !== 'undefined') {
            var bytes = new TextEncoder().encode(text);
            var out = '';
            for (var i = 0; i < bytes.length; i++) out += String.fromCharCode(bytes[i]);
            return out;
        }
        return unescape(encodeURIComponent(text));
    }

    /**
     * Minimal SHA-256 over a binary string. Only used when crypto.subtle is
     * unavailable (the admin panel served over plain HTTP), so verification
     * still works without a secure origin.
     */
    function sha256HexAscii(ascii) {
        function rightRotate(value, amount) { return (value >>> amount) | (value << (32 - amount)); }
        var mathPow = Math.pow;
        var maxWord = mathPow(2, 32);
        var result = '';
        var words = [];
        var asciiBitLength = ascii.length * 8;
        var hash = sha256HexAscii.h = sha256HexAscii.h || [];
        var k = sha256HexAscii.k = sha256HexAscii.k || [];
        var primeCounter = k.length;
        var isComposite = {};
        for (var candidate = 2; primeCounter < 64; candidate++) {
            if (!isComposite[candidate]) {
                for (var c = 0; c < 313; c += candidate) isComposite[c] = candidate;
                hash[primeCounter] = (mathPow(candidate, 0.5) * maxWord) | 0;
                k[primeCounter++] = (mathPow(candidate, 1 / 3) * maxWord) | 0;
            }
        }
        var i, j;
        ascii += '\x80';
        while (ascii.length % 64 - 56) ascii += '\x00';
        for (i = 0; i < ascii.length; i++) {
            j = ascii.charCodeAt(i);
            words[i >> 2] |= j << ((3 - i) % 4) * 8;
        }
        words[words.length] = (asciiBitLength / maxWord) | 0;
        words[words.length] = asciiBitLength;
        for (j = 0; j < words.length;) {
            var w = words.slice(j, j += 16);
            var oldHash = hash;
            hash = hash.slice(0, 8);
            for (i = 0; i < 64; i++) {
                var w15 = w[i - 15], w2 = w[i - 2];
                var a = hash[0], e = hash[4];
                var temp1 = hash[7]
                    + (rightRotate(e, 6) ^ rightRotate(e, 11) ^ rightRotate(e, 25))
                    + ((e & hash[5]) ^ ((~e) & hash[6]))
                    + k[i]
                    + (w[i] = (i < 16) ? w[i] : (
                        w[i - 16]
                        + (rightRotate(w15, 7) ^ rightRotate(w15, 18) ^ (w15 >>> 3))
                        + w[i - 7]
                        + (rightRotate(w2, 17) ^ rightRotate(w2, 19) ^ (w2 >>> 10))
                    ) | 0);
                var temp2 = (rightRotate(a, 2) ^ rightRotate(a, 13) ^ rightRotate(a, 22))
                    + ((a & hash[1]) ^ (a & hash[2]) ^ (hash[1] & hash[2]));
                hash = [(temp1 + temp2) | 0].concat(hash);
                hash[4] = (hash[4] + temp1) | 0;
            }
            for (i = 0; i < 8; i++) hash[i] = (hash[i] + oldHash[i]) | 0;
        }
        for (i = 0; i < 8; i++) {
            for (j = 3; j + 1; j--) {
                var b = (hash[i] >> (j * 8)) & 255;
                result += ((b < 16) ? 0 : '') + b.toString(16);
            }
        }
        return result;
    }

    function hashEntries(entries) {
        var text = canonicalPayload(entries);
        if (window.crypto && window.crypto.subtle && typeof TextEncoder !== 'undefined') {
            return window.crypto.subtle.digest('SHA-256', new TextEncoder().encode(text)).then(function (buffer) {
                var bytes = new Uint8Array(buffer);
                var out = '';
                for (var i = 0; i < bytes.length; i++) out += (bytes[i] < 16 ? '0' : '') + bytes[i].toString(16);
                return out;
            });
        }
        return Promise.resolve(sha256HexAscii(utf8BinaryString(text)));
    }

    function fetchSecurityStatus() {
        return fetch(SECURITY_URL + '?action=status', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); });
    }

    function updateSecurityChip(status) {
        var chip = el('rmgSecurityChip');
        if (!chip) return;
        if (!status || status.success === false) { chip.hidden = true; return; }
        chip.hidden = false;
        chip.textContent = status.configured ? 'On' : 'Not set';
        chip.className = 'rmg-sec-chip ' + (status.configured ? 'is-on' : 'is-off');
    }

    /**
     * Open the verification modal and resolve with a single-use token (or null
     * when cancelled / not possible).
     */
    function beginSecurityVerify(action, target, entries, display) {
        if (pendingSecurity) {
            rmgNotify('warning', 'Verification in progress', 'Finish the open security authorization first.');
            return Promise.resolve(null);
        }
        return hashEntries(entries).then(function (hash) {
            return fetchSecurityStatus().then(function (status) {
                if (!status || status.success === false) {
                    rmgNotify('error', 'Security check failed', 'Could not check the security settings. Please try again.');
                    return null;
                }

                updateSecurityChip(status);

                if (!status.configured) {
                    rmgNotify('error', 'Security code not set up', 'A Super Admin must set the Security Authorization Code before accounts can be created or changed.');
                    return null;
                }

                if (status.locked_until) {
                    rmgNotify('error', 'Verification temporarily locked', 'Too many incorrect attempts. Please try again after ' + status.locked_until + ' UTC.');
                    return null;
                }

                return new Promise(function (resolve) {
                    pendingSecurity = { action: action, target: target, hash: hash, resolve: resolve, busy: false };
                    el('rmgSecuritySummary').textContent = display || (securityActionLabel(action) + ' · ' + securityTargetLabel(target));
                    el('rmgSecurityCode').value = '';
                    el('rmgSecurityError').hidden = true;
                    bootstrap.Modal.getOrCreateInstance(el('rmgSecurityModal')).show();
                    setTimeout(function () {
                        var input = el('rmgSecurityCode');
                        if (input) input.focus();
                    }, 350);
                });
            });
        });
    }

    function showSecurityError(message) {
        var box = el('rmgSecurityError');
        box.textContent = message;
        box.hidden = false;
    }

    function hideSecurityModal() {
        var modal = el('rmgSecurityModal');
        var instance = bootstrap.Modal.getInstance(modal);
        if (instance) instance.hide();
    }

    function submitSecurityVerify() {
        if (!pendingSecurity || pendingSecurity.busy) return;

        var code = el('rmgSecurityCode').value;
        if (String(code).trim() === '') {
            showSecurityError('Enter the Security Authorization Code.');
            return;
        }

        pendingSecurity.busy = true;
        var button = el('rmgSecurityConfirm');
        button.disabled = true;

        fetch(SECURITY_URL + '?action=verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                verify_action: pendingSecurity.action,
                target: pendingSecurity.target,
                payload_hash: pendingSecurity.hash,
                code: code
            }),
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (json) {
            json = json || {};

            if (json.success && json.token) {
                var resolve = pendingSecurity.resolve;
                pendingSecurity = null;
                hideSecurityModal();
                resolve(json.token);
                return;
            }

            if (json.code === 'incorrect_code') {
                showSecurityError(json.message || 'Incorrect Security Authorization Code.');
                el('rmgSecurityCode').value = '';
                el('rmgSecurityCode').focus();
                return;
            }

            var finish = pendingSecurity ? pendingSecurity.resolve : null;
            pendingSecurity = null;
            hideSecurityModal();

            if (json.code === 'locked') {
                rmgNotify('error', 'Verification locked', json.message || 'Too many incorrect attempts.');
            } else if (json.code === 'security_not_configured') {
                rmgNotify('error', 'Security code not set up', json.message || 'A Super Admin must set the security code first.');
            } else if (json.code === 'role_denied') {
                rmgNotify('error', 'Not allowed', json.message || 'You are not allowed to manage this account.');
            } else {
                rmgNotify('error', 'Verification failed', json.message || 'Could not verify the code. Please try again.');
            }

            if (finish) finish(null);
        }).catch(function () {
            showSecurityError('Could not reach the server. Please try again.');
        }).finally(function () {
            if (pendingSecurity) pendingSecurity.busy = false;
            var btn = el('rmgSecurityConfirm');
            if (btn) btn.disabled = false;
        });
    }

    function openSecurityButton() {
        if (state.isSuper) {
            openSecuritySettings();
            return;
        }
        fetchSecurityStatus().then(function (status) {
            var configured = !!(status && status.configured);
            rmgNotify(
                configured ? 'success' : 'error',
                'Security Authorization',
                configured
                    ? 'The Security Authorization Code is set. You will be asked for it each time you create, edit, deactivate, or reactivate an account.'
                    : 'A Super Admin must set the Security Authorization Code before account changes can be made.'
            );
        }).catch(function () {
            rmgNotify('error', 'Security Authorization', 'Could not check the security settings. Please try again.');
        });
    }

    function openSecuritySettings() {
        fetchSecurityStatus().then(function (status) {
            var configured = !!(status && status.configured);
            var stateEl = el('rmgSecSettingsState');
            stateEl.textContent = configured ? 'Security code is set' : 'Security code is not set';
            stateEl.className = 'rmg-sec-state ' + (configured ? 'is-on' : 'is-off');

            var updated = status && status.last_updated ? status.last_updated : null;
            el('rmgSecSettingsMeta').textContent = configured && updated && updated.at
                ? 'Last updated ' + fmtDate(updated.at, true) + (updated.by ? ' by user #' + updated.by : '')
                : 'No code has been set yet. Every account change is blocked until one is set.';

            el('rmgSecCurrentWrap').hidden = !configured;
            ['rmgSecCurrentCode', 'rmgSecNewCode', 'rmgSecConfirmCode', 'rmgSecPassword'].forEach(function (id) {
                el(id).value = '';
            });
            el('rmgSecSettingsError').hidden = true;
            el('rmgSecActivity').innerHTML = '<div class="rmg-sec-activity-empty">Loading activity…</div>';

            bootstrap.Modal.getOrCreateInstance(el('rmgSecuritySettingsModal')).show();
            loadSecurityActivity();
        }).catch(function () {
            rmgNotify('error', 'Security settings', 'Could not load the security settings. Please try again.');
        });
    }

    function loadSecurityActivity() {
        fetch(SECURITY_URL + '?action=activity', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var host = el('rmgSecActivity');
                if (!res || res.success === false) {
                    host.innerHTML = '<div class="rmg-sec-activity-empty">' + esc((res && res.message) || 'Could not load activity.') + '</div>';
                    return;
                }
                var events = Array.isArray(res.events) ? res.events : [];
                if (!events.length) {
                    host.innerHTML = '<div class="rmg-sec-activity-empty">No security activity yet.</div>';
                    return;
                }
                host.innerHTML = '<ul>' + events.map(function (event) {
                    var tone = (event.event === 'failed' || event.event === 'blocked') ? ' is-bad'
                        : ((event.event === 'used' || event.event === 'verified') ? ' is-good' : '');
                    return '<li class="rmg-sec-event' + tone + '">' +
                        '<span class="rmg-sec-event-name">' + esc(event.event) + '</span>' +
                        '<span class="rmg-sec-event-detail">' + esc(event.detail || '') + '</span>' +
                        '<span class="rmg-sec-event-meta">' + esc(event.username || 'unknown') + ' · ' + esc(fmtDate(event.at, true)) + '</span>' +
                        '</li>';
                }).join('') + '</ul>';
            })
            .catch(function () {
                el('rmgSecActivity').innerHTML = '<div class="rmg-sec-activity-empty">Could not load activity.</div>';
            });
    }

    function saveSecuritySettings() {
        var configured = !el('rmgSecCurrentWrap').hidden;
        var currentCode = el('rmgSecCurrentCode').value;
        var newCode = el('rmgSecNewCode').value;
        var confirmCode = el('rmgSecConfirmCode').value;
        var password = el('rmgSecPassword').value;
        var error = '';

        if (configured && String(currentCode).trim() === '') error = 'Enter the current security code.';
        else if (String(newCode).length < 6) error = 'The new code must be at least 6 characters.';
        else if (newCode !== confirmCode) error = 'The new code and its confirmation do not match.';
        else if (String(password).trim() === '') error = 'Enter your account password to confirm this change.';

        if (error) {
            var box = el('rmgSecSettingsError');
            box.textContent = error;
            box.hidden = false;
            return;
        }

        var button = el('rmgSecSettingsSave');
        button.disabled = true;

        fetch(SECURITY_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'set',
                password: password,
                current_code: currentCode,
                new_code: newCode,
                confirm_code: confirmCode
            }),
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (json) {
            json = json || {};
            if (json.success === false) {
                var box = el('rmgSecSettingsError');
                box.textContent = json.message || 'Could not update the security code.';
                box.hidden = false;
                return;
            }

            rmgNotify('success', 'Security code updated', json.message || 'The Security Authorization Code was updated.');
            ['rmgSecCurrentCode', 'rmgSecNewCode', 'rmgSecConfirmCode', 'rmgSecPassword'].forEach(function (id) {
                el(id).value = '';
            });
            el('rmgSecSettingsError').hidden = true;
            el('rmgSecCurrentWrap').hidden = false;
            fetchSecurityStatus().then(updateSecurityChip).catch(function () {});
            loadSecurityActivity();
        }).catch(function () {
            var box = el('rmgSecSettingsError');
            box.textContent = 'Could not reach the server. Please try again.';
            box.hidden = false;
        }).finally(function () {
            button.disabled = false;
        });
    }

    function ensureAddOptionData(callback) {
        state.addOptions = state.addOptions || {};

        if (state.addOptions.loaded) {
            callback();
            return;
        }
        if (state.addOptions.loading) {
            state.addOptions.queue = state.addOptions.queue || [];
            state.addOptions.queue.push(callback);
            return;
        }

        state.addOptions.loading = true;
        state.addOptions.queue = [callback];

        var types = ['buses', 'terminals', 'supervisors', 'routes', 'departments', 'lists'];
        var pending = types.length;

        function finish() {
            pending--;
            if (pending > 0) return;
            state.addOptions.loaded = true;
            state.addOptions.loading = false;
            var queue = state.addOptions.queue || [];
            state.addOptions.queue = [];
            queue.forEach(function (fn) { if (typeof fn === 'function') fn(); });
        }

        types.forEach(function (type) {
            fetch('/admin/api/RoleManagement/Options.php?type=' + encodeURIComponent(type), {credentials: 'same-origin', cache: 'no-store'})
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || res.success === false) {
                        if (type === 'lists') state.addOptions.lists = {};
                        else state.addOptions[type] = [];
                        return;
                    }
                    if (type === 'lists') {
                        state.addOptions.lists = res.lists || {};
                    } else {
                        state.addOptions[type] = res.options || [];
                    }
                })
                .catch(function () {
                    if (type === 'lists') state.addOptions.lists = {};
                    else state.addOptions[type] = [];
                })
                .finally(finish);
        });
    }

    /* ---------------------------------------------------------------------
     * User ID preview, live validation, password visibility, form guards
     * ------------------------------------------------------------------- */

    var NEXT_USER_ID_URL = '/admin/api/RoleManagement/NextUserId.php';

    var LIVE_VALIDATION_KINDS = {
        full_name: 'name',
        inspector_name: 'name',
        email: 'email',
        contact_number: 'phone',
        contact_no: 'phone',
        username: 'username',
        password: 'password',
        password_confirm: 'confirm'
    };

    function setUserIdState(mode, message) {
        var stateEl = el('rmgAddUserIdState');
        if (stateEl) {
            stateEl.className = 'rmg-id-state is-' + mode;
            stateEl.innerHTML = mode === 'loading'
                ? '<i class="bi bi-arrow-repeat"></i>'
                : (mode === 'error' ? '<i class="bi bi-exclamation-circle"></i>' : '<i class="bi bi-check2-circle"></i>');
        }
        var hint = el('rmgAddUserIdHint');
        if (hint && message) {
            hint.textContent = message;
        }
    }

    /**
     * Fetch the next available ID for the selected role. The value is a
     * preview: the real ID is allocated atomically when the account is saved.
     */
    function refreshAddUserId() {
        var roleSelect = el('rmgAddRole');
        var input = el('rmgAddUserId');
        if (!input) return;

        var role = roleSelect ? roleSelect.value : '';
        if (!role) {
            input.value = 'Select a role';
            return;
        }

        var requestId = ++state.userIdRequest;
        input.value = 'Generating...';
        setUserIdState('loading', 'Checking the next available ID...');

        fetch(NEXT_USER_ID_URL + '?role=' + encodeURIComponent(role), {credentials: 'same-origin', cache: 'no-store'})
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (requestId !== state.userIdRequest) return;
                if (!res || res.success === false) {
                    state.addUserId = '';
                    input.value = 'Assigned when saved';
                    setUserIdState('error', (res && res.message) || 'Preview unavailable — the ID is assigned securely when the account is saved.');
                    return;
                }
                state.addUserId = String(res.user_id || '');
                input.value = state.addUserId || 'Assigned when saved';
                setUserIdState('ready', 'Preview only. The final ID is assigned securely when the account is saved.');
                updateWizardReview('add');
            })
            .catch(function () {
                if (requestId !== state.userIdRequest) return;
                state.addUserId = '';
                input.value = 'Assigned when saved';
                setUserIdState('error', 'Preview unavailable — the ID is assigned securely when the account is saved.');
            });
    }

    function nearestGrid(input) {
        var node = input;
        while (node && node.classList) {
            if (node.classList.contains('rmg-form-grid')) return node;
            node = node.parentNode;
        }
        return null;
    }

    function fieldWrapper(input) {
        var node = input;
        while (node && node.classList) {
            if (node.classList.contains('rmg-form-field')) return node;
            node = node.parentNode;
        }
        return null;
    }

    function fieldErrorEl(wrapper) {
        if (!wrapper) return null;
        var errorEl = wrapper.querySelector ? wrapper.querySelector('.rmg-field-error') : null;
        if (!errorEl && wrapper.appendChild) {
            errorEl = document.createElement('div');
            errorEl.className = 'rmg-field-error';
            wrapper.appendChild(errorEl);
        }
        return errorEl;
    }

    /**
     * Server messages carry a field label ("Full name …", "Email …"); map
     * them back onto the field so the wizard can reveal the exact place
     * instead of a generic "Update failed" dialog.
     */
    var SERVER_ERROR_FIELDS = [
        {re: /^full name/i, name: 'full_name', composite: 'name'},
        {re: /^inspector name/i, name: 'inspector_name', composite: 'name'},
        {re: /^email/i, name: 'email'},
        {re: /^username/i, name: 'username'},
        {re: /^contact number/i, name: 'contact_number'},
        {re: /^new password|^password/i, name: 'password'},
        {re: /^address/i, name: 'address', composite: 'address'},
        {re: /^date hired/i, name: 'hired_date'},
        {re: /^date of birth/i, name: 'birth_date'},
        {re: /^license number/i, name: 'license_number'},
        {re: /^license expiry/i, name: 'license_expiry'},
        {re: /^driving experience|^experience years/i, name: 'experience_years'},
        {re: /^inspector identity|^identity/i, name: 'inspector_identity'},
        {re: /^select a valid supervisor/i, name: 'supervisor_id'},
        {re: /^reason|reason for this change/i, name: 'reason'}
    ];

    function serverErrorTarget(message) {
        var text = String(message || '');
        for (var i = 0; i < SERVER_ERROR_FIELDS.length; i++) {
            if (SERVER_ERROR_FIELDS[i].re.test(text)) {
                return SERVER_ERROR_FIELDS[i];
            }
        }
        return null;
    }

    function wrapperForServerField(mode, target) {
        var host = el(mode === 'add' ? 'rmgAddFields' : 'rmgEditFields');
        if (!host || !target) return null;
        if (target.composite === 'name') return host.querySelector('.rmg-name-parts');
        if (target.composite === 'address') return host.querySelector('.rmg-address');
        var prefix = mode === 'add' ? 'data-add-field' : 'data-edit-field';
        var input = host.querySelector('[' + prefix + '="' + target.name + '"]');
        return input ? fieldWrapper(input) : null;
    }

    function revealStepForWrapper(mode, wrapper) {
        if (!wrapper || !wrapper.closest) return;
        var step = wrapper.closest('[data-step]');
        if (!step) return;
        var role = mode === 'add' ? el('rmgAddRole').value : (window.RMG_EDIT_ITEM ? window.RMG_EDIT_ITEM.role : '');
        var steps = wizardStepsFor(role);
        var index = parseInt(step.getAttribute('data-step-index'), 10);
        if (!isNaN(index) && index >= 0 && index < steps.length && index !== (state.wizard[mode] || 0)) {
            state.wizard[mode] = index;
            renderWizardSteps(mode === 'add' ? 'rmgAddSteps' : 'rmgEditSteps', mode, role);
            updateWizardNav(mode);
        }
        if (step.scrollIntoView) {
            step.scrollIntoView({behavior: 'smooth', block: 'start'});
        }
    }

    function showFieldServerError(mode, message) {
        var target = serverErrorTarget(message);
        if (!target) return false;
        var wrapper = wrapperForServerField(mode, target);
        if (!wrapper) return false;
        var errorEl = fieldErrorEl(wrapper);
        if (errorEl) {
            errorEl.textContent = message;
            if (errorEl.classList) errorEl.classList.add('is-visible');
        }
        Array.prototype.forEach.call(wrapper.querySelectorAll('input, select, textarea'), function (input) {
            input.setAttribute('data-rmg-touched', '1');
            if (input.type !== 'file' && input.classList && input.classList.add) {
                input.classList.add('is-invalid');
            }
        });
        revealStepForWrapper(mode, wrapper);
        return true;
    }

    function clearFieldServerError(input) {
        if (!input || !input.closest) return;
        var wrapper = input.closest('.rmg-form-field');
        if (!wrapper) return;
        var errorEl = wrapper.querySelector('.rmg-field-error');
        if (errorEl && errorEl.classList) errorEl.classList.remove('is-visible');
        Array.prototype.forEach.call(wrapper.querySelectorAll('.is-invalid'), function (node) {
            node.classList.remove('is-invalid');
        });
    }

    /**
     * Run the shared live rules over every wizard field before saving.
     * Returns true when the form is clean; otherwise reveals the first
     * invalid field on its step. In edit mode, fields whose value still
     * matches the stored record are skipped so legacy data can never block
     * an unrelated change.
     */
    function validateWizardForSave(mode, originals) {
        var host = el(mode === 'add' ? 'rmgAddFields' : 'rmgEditFields');
        if (!host) return true;
        var prefix = mode === 'add' ? 'data-add-field' : 'data-edit-field';
        var firstBad = null;

        Array.prototype.forEach.call(host.querySelectorAll('[' + prefix + ']'), function (input) {
            if (input.type === 'file') {
                if (input.getAttribute('data-required') === '1' && !liveValidateInput(input) && !firstBad) {
                    firstBad = input;
                }
                return;
            }
            input.setAttribute('data-rmg-touched', '1');
            var name = input.getAttribute(prefix);
            if (originals && originals[name] !== undefined && String(originals[name]) === String(input.value)) {
                return;
            }
            if (!liveValidateInput(input) && !firstBad) {
                firstBad = input;
            }
        });

        // Add mode still requires the composite full name; the Edit form
        // keeps every field optional except the photo.
        var first = mode === 'add' ? host.querySelector('[data-name-part="first"]') : null;
        var last = mode === 'add' ? host.querySelector('[data-name-part="last"]') : null;
        var composite = host.querySelector('[data-name-composite="1"]');
        if (first && last) {
            var nameKey = composite ? composite.getAttribute(prefix) : '';
            var nameChanged = !originals || originals[nameKey] === undefined
                || String(composite ? composite.value : '') !== String(originals[nameKey]);
            if (nameChanged) {
                var partsOk = first.value.trim() !== '' && last.value.trim() !== '';
                [first, last].forEach(function (input) {
                    if (input.classList) input.classList.toggle('is-invalid', !partsOk);
                });
                if (!partsOk && !firstBad) firstBad = first;
            }
        }

        if (!firstBad) return true;

        var wrapper = fieldWrapper(firstBad) || (firstBad.closest ? firstBad.closest('.rmg-name-parts, .rmg-address') : null);
        if (wrapper) {
            var errorEl = fieldErrorEl(wrapper);
            if (errorEl && (errorEl.textContent || '').trim() === '') {
                errorEl.textContent = 'Please complete this field.';
            }
            if (errorEl && errorEl.classList) errorEl.classList.add('is-visible');
            revealStepForWrapper(mode, wrapper);
        }
        return false;
    }

    /**
     * Client mirror of UserValidation.php. Server-side rules remain
     * authoritative; this only gives immediate, friendly feedback.
     */
    function validateLiveValue(name, value, required, input) {
        value = String(value === null || value === undefined ? '' : value).trim();
        var kind = LIVE_VALIDATION_KINDS[name] || '';

        if (kind === 'confirm') {
            if (value === '') {
                return required ? 'Please re-enter the password.' : null;
            }
            var grid = input ? nearestGrid(input) : null;
            var passwordInput = grid && grid.querySelector ? grid.querySelector('[data-add-field="password"], [data-edit-field="password"]') : null;
            if (passwordInput && value !== passwordInput.value) {
                return 'Password confirmation does not match.';
            }
            return null;
        }

        if (value === '') {
            return required ? 'This field is required.' : null;
        }

        if (kind === 'name') {
            if (!/^[\p{L}][\p{L} .,'\-]*$/u.test(value)) {
                return 'Use letters, spaces, periods, commas, hyphens, or apostrophes only.';
            }
            if (/(^|[^\p{L}])[.,'\-]/u.test(value)) {
                return 'Punctuation must follow a letter (for example "D.", "Jr.", or "Cruz, Jr.").';
            }
            if (/[\s,'\-]$/.test(value)) {
                return 'The name cannot end with a space or punctuation.';
            }
            return null;
        }
        if (kind === 'email') {
            return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value) ? null : 'Enter a valid email address.';
        }
        if (kind === 'phone') {
            var clean = value.replace(/[\s\-()]/g, '');
            return (/^09\d{9}$/.test(clean) || /^(\+63|63)9\d{9}$/.test(clean))
                ? null
                : 'Use 09XXXXXXXXX or +639XXXXXXXXX.';
        }
        if (kind === 'username') {
            return /^[A-Za-z0-9._-]{3,30}$/.test(value)
                ? null
                : '3-30 characters: letters, numbers, dots, underscores, hyphens.';
        }
        if (kind === 'password') {
            if (value.length < 8 || !/[A-Za-z]/.test(value) || !/\d/.test(value)) {
                return 'At least 8 characters with a letter and a number.';
            }
        }

        return null;
    }

    function liveValidateInput(input) {
        if (!input || !input.getAttribute) return true;
        if (input.type === 'file') {
            var fileMissing = input.getAttribute('data-required') === '1' && !(input.files && input.files.length);
            if (input.classList && input.classList.toggle) {
                input.classList.toggle('is-invalid', fileMissing);
            }
            var fileErrorEl = fieldErrorEl(fieldWrapper(input));
            if (fileErrorEl) {
                fileErrorEl.textContent = fileMissing ? 'Please choose a file.' : '';
                if (fileErrorEl.classList) {
                    fileErrorEl.classList.toggle('is-visible', fileMissing);
                }
            }
            return !fileMissing;
        }
        var name = input.getAttribute('data-add-field') || input.getAttribute('data-edit-field') || '';
        if (!LIVE_VALIDATION_KINDS[name]) return true;

        var required = input.getAttribute('data-required') === '1';
        var message = validateLiveValue(name, input.value, required, input);

        if (input.classList && input.classList.toggle) {
            input.classList.toggle('is-invalid', message !== null);
        }
        var errorEl = fieldErrorEl(fieldWrapper(input));
        if (errorEl) {
            errorEl.textContent = message || '';
            if (errorEl.classList) {
                errorEl.classList.toggle('is-visible', message !== null);
            }
        }

        return message === null;
    }

    function onFormFieldInput(event) {
        var input = event && event.target;
        if (!input || !input.getAttribute) return;
        if (input.getAttribute('data-rmg-touched') === '1') {
            liveValidateInput(input);
        }
    }

    function onFormFieldBlur(event) {
        var input = event && event.target;
        if (!input || !input.getAttribute) return;
        var name = input.getAttribute('data-add-field') || input.getAttribute('data-edit-field') || '';
        if (!LIVE_VALIDATION_KINDS[name]) return;
        input.setAttribute('data-rmg-touched', '1');
        liveValidateInput(input);
        if (name === 'password') {
            var grid = nearestGrid(input);
            var confirmInput = grid && grid.querySelector ? grid.querySelector('[data-add-field="password_confirm"], [data-edit-field="password_confirm"]') : null;
            if (confirmInput && confirmInput.getAttribute('data-rmg-touched') === '1') {
                liveValidateInput(confirmInput);
            }
        }
    }

    /**
     * Wrap password inputs with a show/hide control.
     */
    function enhancePasswordFields(container) {
        if (!container || !container.querySelectorAll || !document.createElement) return;
        Array.prototype.forEach.call(container.querySelectorAll('input[type="password"]'), function (input) {
            var parent = input.parentNode;
            if (!parent || (parent.classList && parent.classList.contains('rmg-input-wrap'))) return;
            if (!parent.insertBefore || !parent.appendChild) return;

            var wrap = document.createElement('div');
            wrap.className = 'rmg-input-wrap';
            parent.insertBefore(wrap, input);
            wrap.appendChild(input);

            var toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'rmg-password-toggle';
            toggle.setAttribute('data-toggle-password', '1');
            toggle.setAttribute('aria-label', 'Show or hide password');
            toggle.innerHTML = '<i class="bi bi-eye"></i>';
            wrap.appendChild(toggle);
        });
    }

    function onFormFieldClick(event) {
        var toggle = event && event.target && event.target.closest ? event.target.closest('[data-toggle-password]') : null;
        if (!toggle || !toggle.parentNode) return;
        var input = toggle.parentNode.querySelector ? toggle.parentNode.querySelector('input') : null;
        if (!input) return;
        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        toggle.innerHTML = showing ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
    }

    /**
     * Warn before closing a form with unsaved changes.
     */
    function bindUnsavedGuard(modalId, dirtyKey, containerId) {
        var modal = el(modalId);
        var container = el(containerId);
        if (!modal || !container || !modal.addEventListener) return;

        container.addEventListener('input', function () { state.formDirty[dirtyKey] = true; });
        container.addEventListener('change', function () { state.formDirty[dirtyKey] = true; });

        modal.addEventListener('hide.bs.modal', function (event) {
            if (state.formForceClose[dirtyKey] || !state.formDirty[dirtyKey]) return;
            event.preventDefault();

            var proceed = function () {
                state.formForceClose[dirtyKey] = true;
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    var instance = bootstrap.Modal.getInstance(modal);
                    if (instance) instance.hide();
                }
            };

            if (typeof window.showConfirm === 'function') {
                window.showConfirm(
                    'Discard unsaved changes?',
                    'This form has unsaved changes. Close it and lose them?',
                    proceed,
                    function () {}
                );
            }
        });

        modal.addEventListener('hidden.bs.modal', function () {
            state.formDirty[dirtyKey] = false;
            state.formForceClose[dirtyKey] = false;
        });
    }

    function entryValue(entries, names) {
        for (var n = 0; n < names.length; n++) {
            for (var i = 0; i < entries.length; i++) {
                if (entries[i][0] === names[n]) {
                    var value = String(entries[i][1] === null || entries[i][1] === undefined ? '' : entries[i][1]).trim();
                    if (value !== '') return value;
                }
            }
        }
        return '';
    }

    /**
     * Non-sensitive creation summary shown before the security verification.
     */
    function confirmCreationSummary(role, entries) {
        var label = (CFG.roles || {})[role] || role;
        var preview = state.addUserId || (el('rmgAddUserId') ? el('rmgAddUserId').value : '');
        var previewText = (preview && preview.indexOf('Generating') === -1 && preview.indexOf('Assigned') === -1)
            ? preview + ' (preview)'
            : 'assigned securely on save';

        var message = [
            'Role: ' + label,
            'User ID: ' + previewText,
            'Full name: ' + (entryValue(entries, ['full_name', 'inspector_name']) || '—'),
            'Email: ' + (entryValue(entries, ['email']) || '—'),
            'Contact: ' + (entryValue(entries, ['contact_number', 'contact_no']) || '—')
        ].join('\n');

        return new Promise(function (resolve) {
            if (typeof window.showConfirm === 'function') {
                window.showConfirm(
                    'Create ' + label + ' account?',
                    message,
                    function () { resolve(true); },
                    function () { resolve(false); }
                );
                return;
            }
            resolve(true);
        });
    }

    function renderAddFieldsLegacy() {
        var role = el('rmgAddRole').value;
        var kicker = el('rmgAddKicker');
        if (kicker) {
            kicker.textContent = ((CFG.roles || {})[role] || 'New') + ' account';
        }
        var fields = addFieldSetFor(role);
        var options = state.addOptions || {};
        var html = '';

        fields.forEach(function (field) {
            html += '<div class="rmg-form-field' + (field.full ? ' full' : '') + '">' +
                '<label>' + esc(field.label) + (field.required ? ' *' : '') + '</label>';

            if (field.type === 'select') {
                html += '<select data-add-field="' + esc(field.name) + '"' + (field.required ? ' data-required="1"' : '') + '>';
                (field.options || []).forEach(function (option) {
                    html += '<option value="' + esc(option) + '">' + esc(option === '' ? '—' : option) + '</option>';
                });
                html += '</select>';
            } else if (field.type === 'select-options') {
                var list = options[field.optionKey] || [];
                html += '<select data-add-field="' + esc(field.name) + '"' + (field.required ? ' data-required="1"' : '') + '>';
                html += '<option value="">' + (list.length ? 'Select...' : 'No options available') + '</option>';
                list.forEach(function (option) {
                    html += '<option value="' + esc(option.id) + '">' + esc(option.label) + '</option>';
                });
                html += '</select>';
            } else if (field.type === 'textarea') {
                html += '<textarea rows="2" data-add-field="' + esc(field.name) + '"' + (field.required ? ' data-required="1"' : '') + '></textarea>';
            } else {
                html += '<input type="' + esc(field.type || 'text') + '" data-add-field="' + esc(field.name) + '"' +
                    (field.required ? ' data-required="1"' : '') +
                    (field.type === 'file' ? ' accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"' : '') + '>';
                if (field.age) {
                    html += '<div class="rmg-age-note" data-age-for="' + esc(field.name) + '"></div>';
                }
            }

            html += '</div>';
        });

        html += '</div>';

        el('rmgAddFields').innerHTML = html;
        el('rmgAddError').hidden = true;
        el('rmgAddNote').hidden = true;
        enhancePasswordFields(el('rmgAddFields'));
        updateAgeNotes(el('rmgAddFields'));
    }

    function openAdd() {
        closeDetailBefore(openAddForm);
    }

    function openAddForm() {
        var roleSelect = el('rmgAddRole');
        if (!roleSelect.options.length) {
            var options = ['driver', 'inspector', 'dispatcher', 'finance_officer', 'finance_manager'];
            if (state.isSuper) options.push('admin', 'super_admin');
            roleSelect.innerHTML = options.map(function (role) {
                var label = (CFG.roles || {})[role] || role;
                return '<option value="' + esc(role) + '">' + esc(label) + '</option>';
            }).join('');
        }
        if (state.role !== 'all' && (CFG.roles || {})[state.role]) {
            roleSelect.value = state.role;
        }
        state.addRoleLast = roleSelect.value;

        state.formDirty.add = false;
        state.formForceClose.add = false;
        state.addUserId = '';
        state.wizard.add = 0;
        state.addValues = null;

        el('rmgAddSave').disabled = true;
        el('rmgAddError').hidden = true;
        renderAddFields();
        refreshAddUserId();

        bootstrap.Modal.getOrCreateInstance(el('rmgAddModal')).show();

        ensureAddOptionData(function () {
            state.addValues = readValuesFromHost('add') || state.addValues;
            renderAddFields();
            refreshAddUserId();
            el('rmgAddSave').disabled = false;
        });
    }

    /**
     * Snapshot the current form values (used when options arrive after the
     * form was already rendered).
     */
    function readValuesFromHost(mode) {
        var host = el(mode === 'add' ? 'rmgAddFields' : 'rmgEditFields');
        if (!host || !host.querySelectorAll) return null;
        var prefix = mode === 'add' ? 'data-add-field' : 'data-edit-field';
        var values = {};
        var found = false;

        Array.prototype.forEach.call(host.querySelectorAll('[' + prefix + ']'), function (input) {
            var name = input.getAttribute(prefix);
            if (input.type === 'file') return;
            found = true;
            values[name] = input.value;
        });

        var first = host.querySelector('[data-name-part="first"]');
        if (first) {
            found = true;
            values._nameParts = {
                first: (host.querySelector('[data-name-part="first"]') || {}).value || '',
                middle: (host.querySelector('[data-name-part="middle"]') || {}).value || '',
                last: (host.querySelector('[data-name-part="last"]') || {}).value || '',
                suffix: (host.querySelector('[data-name-part="suffix"]') || {}).value || ''
            };
        }

        var addressComposite = host.querySelector('[data-address-composite="1"]');
        if (addressComposite) {
            values.address = addressComposite.value;
        }

        return found ? values : null;
    }

    function saveAdd() {
        if (state.submittingAdd) return;
        var role = el('rmgAddRole').value;
        var url = ADD_ENDPOINTS[role];
        if (!url) return;

        var formData = new FormData();
        formData.append('role', role);
        var entries = [['role', role]];
        var fields = el('rmgAddFields').querySelectorAll('[data-add-field]');
        var error = '';
        var password = '';
        var confirm = '';

        fields.forEach(function (input) {
            var name = input.getAttribute('data-add-field');
            if (input.type === 'file') {
                if (input.files && input.files[0]) formData.append(name, input.files[0]);
                return;
            }
            var value = input.value;
            formData.append(name, value);
            entries.push([name, value]);
            if (name === 'password') password = value;
            if (name === 'password_confirm') confirm = value;
        });

        Array.prototype.forEach.call(fields, function (input) {
            var name = input.getAttribute('data-add-field');
            if (input.type === 'file') return;
            var spec = addFieldSetFor(role).filter(function (f) { return f.name === name; })[0];
            if (spec && spec.required && String(input.value).trim() === '' && !error) {
                error = spec.label + ' is required.';
            }
        });

        if (!error && password !== confirm) {
            error = 'Password confirmation does not match.';
        }
        if (!error && password.length < 8) {
            error = 'Password must be at least 8 characters.';
        }
        if (!error) {
            var nameComposite = el('rmgAddFields').querySelector('[data-name-composite="1"]');
            if (nameComposite && String(nameComposite.value).trim() === '') {
                error = 'Full Name is required.';
            }
        }

        if (error) {
            el('rmgAddError').textContent = error;
            el('rmgAddError').hidden = false;
            return;
        }

        if (!validateWizardForSave('add', null)) {
            el('rmgAddError').textContent = 'Please fix the highlighted fields before saving.';
            el('rmgAddError').hidden = false;
            return;
        }
        el('rmgAddError').hidden = true;

        el('rmgAddSave').disabled = true;
        state.submittingAdd = true;

        beginSecurityVerify(
            'add',
            securityTargetForRole(role),
            entries,
            securityActionLabel('add') + ' · ' + ((CFG.roles || {})[role] || role)
        ).then(function (token) {
                if (!token) {
                    el('rmgAddSave').disabled = false;
                    state.submittingAdd = false;
                    return null;
                }

                formData.append('security_token', token);

                return postForm(url, formData).then(function (res) {
                    state.formForceClose.add = true;
                    bootstrap.Modal.getOrCreateInstance(el('rmgAddModal')).hide();
                    state.role = role;
                    state.page = 1;
                    closeDetail(true);
                    state.historyCache = {};
                    load();

                    var assigned = (res && (
                        res.assigned_user_id
                        || res.employee_id
                        || res.admin_id
                        || res.inspector_id
                        || (typeof res.user_id === 'string' ? res.user_id : '')
                    )) || state.addUserId || '';
                    var label = (CFG.roles || {})[role] || role;

                    if (window.showAlert) {
                        window.showAlert(
                            'success',
                            'Account created',
                            label + ' account successfully created.' + (assigned ? ' User ID: ' + assigned + '.' : '')
                        );
                    }
                }).catch(function (err) {
                    var message = err.message || 'Could not create the account.';
                    el('rmgAddError').textContent = message;
                    el('rmgAddError').hidden = false;
                    showFieldServerError('add', message);
                }).finally(function () {
                    el('rmgAddSave').disabled = false;
                    state.submittingAdd = false;
                });
        }).catch(function () {
            el('rmgAddSave').disabled = false;
            state.submittingAdd = false;
        });
    }

    function promptReason(title, message, confirmText) {
        if (typeof window.adminPromptReason === 'function') { return window.adminPromptReason(title, message, confirmText, 500); }
        rmgNotify('error', 'Confirmation unavailable', 'Please reload the page to load the required reason component.');
        return Promise.resolve(null);
    }

    function archiveAction(item, mode) {
        var endpoints = ARCHIVE_ENDPOINTS[item.role];
        if (!endpoints || !endpoints[mode]) return;
        var config = endpoints[mode];
        var verb = mode === 'archive' ? 'Deactivate / archive' : 'Reactivate';
        var securityAction = mode === 'archive' ? 'archive' : 'restore';

        promptReason(verb + ': ' + item.name, 'This change will be recorded in the audit trail.', verb).then(function (reason) {
            if (!reason) return;

            var entries = [[config.field, String(item.ref)], ['reason', reason]];

            beginSecurityVerify(
                securityAction,
                securityTargetWithRef(item.role, item.ref),
                entries,
                verb + ' · ' + item.name
            ).then(function (token) {
                if (!token) return;

                var request;
                if (config.mode === 'form') {
                    var fd = new FormData();
                    fd.append(config.field, item.ref);
                    fd.append('reason', reason);
                    fd.append('security_token', token);
                    request = postForm(config.url, fd);
                } else if (config.mode === 'urlencoded') {
                    var body = new URLSearchParams();
                    body.set(config.field, item.ref);
                    body.set('reason', reason);
                    body.set('security_token', token);
                    request = fetch(config.url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString(),
                        credentials: 'same-origin'
                    }).then(function (r) { return r.json(); }).then(function (json) {
                        if (!json || json.success === false) throw new Error((json && json.message) || 'The request was rejected.');
                        return json;
                    });
                } else {
                    request = postJson(config.url, { id: Number(item.ref), reason: reason, security_token: token });
                }

                request.then(function (res) { load(); rmgNotify('success', mode === 'archive' ? 'User archived' : 'User restored', (res && res.message) || 'The directory has been updated.'); }).catch(function (err) {
                    rmgNotify('error', 'Action failed', err.message || 'Please try again.');
                });
            });
        });
    }

    function openEdit(item) {
        closeDetailBefore(function () { openEditForm(item); });
    }

    function openEditForm(item) {
        if ((item.role === 'inspector' || item.role === 'dispatcher')
            && !(state.addOptions && state.addOptions.loaded)) {
            ensureAddOptionData(function () { openEditForm(item); });
            return;
        }

        el('rmgEditTitle').textContent = 'Edit ' + item.role_label;
        el('rmgEditSub').textContent = item.name + ' · ' + item.user_id;
        el('rmgEditError').hidden = true;
        el('rmgEditSave').disabled = true;

        var editKicker = el('rmgEditKicker');
        if (editKicker) {
            editKicker.textContent = 'Edit account';
        }
        var editAvatar = el('rmgEditAvatar');
        if (editAvatar) {
            editAvatar.className = 'rmg-form-avatar ' + statusClass(item.status);
            editAvatar.innerHTML = item.photo
                ? '<img src="' + esc(item.photo) + '" alt="">'
                : esc(initials(item.name || ''));
        }

        window.RMG_EDIT_ITEM = item;
        state.formDirty.edit = false;
        state.formForceClose.edit = false;
        state.wizard.edit = 0;

        var values = {};
        var edit = item.edit || {};
        Object.keys(edit).forEach(function (key) { values[key] = edit[key]; });
        values._nameParts = splitFullName(item.name || '');
        values.address = item.address || edit.address || '';
        state.editValues = values;
        state.editMeta = {
            user_id: item.user_id || '',
            created_at: item.created_at ? fmtDate(item.created_at, true) : '',
            created_by: item.created_by || '',
            updated_at: item.updated_at ? fmtDate(item.updated_at, true) : ''
        };

        renderWizardFields('rmgEditFields', 'edit', item.role, values);

        var host = el('rmgEditFields');
        if (host && host.insertAdjacentHTML) {
            host.insertAdjacentHTML('afterbegin', buildEditHiddenHtml(item));
        }

        renderWizardSteps('rmgEditSteps', 'edit', item.role);
        updateWizardNav('edit');
        el('rmgEditSave').disabled = false;
        bootstrap.Modal.getOrCreateInstance(el('rmgEditModal')).show();
    }

    function buildEditHiddenHtml(item) {
        var values = item.edit || {};
        var html = '<input type="hidden" data-edit-hidden="ref" value="' + esc(item.ref) + '">';

        if (item.role === 'driver') {
            html += '<input type="hidden" data-edit-hidden="id" value="' + esc(item.ref) + '">';
        } else if (item.role === 'inspector' || item.role === 'dispatcher') {
            html += '<input type="hidden" data-edit-hidden="inspector_pk" value="' + esc(item.ref) + '">';
            html += '<input type="hidden" data-edit-hidden="account_id" value="' + esc(values.account_id || item.account_ref) + '">';
            html += '<input type="hidden" data-edit-hidden="role" value="' + esc(values.role || item.role) + '">';
            html += '<input type="hidden" data-edit-hidden="system_inspector_id" value="' + esc(item.user_id) + '">';
        } else {
            html += '<input type="hidden" data-edit-hidden="id" value="' + esc(item.ref) + '">';
            html += '<input type="hidden" data-edit-hidden="role" value="' + esc(values.role || item.role) + '">';
            if (item.role === 'finance_officer' || item.role === 'finance_manager') {
                html += '<input type="hidden" data-edit-hidden="employee_id" value="' + esc(item.user_id) + '">';
            }
        }

        // Visible User ID display; not submitted, never editable.
        html += '<div class="rmg-form-field full rmg-readonly-id"><label>User ID (read-only)</label>' +
            '<input type="text" value="' + esc(item.user_id || '') + '" readonly aria-readonly="true" title="User IDs cannot be changed" tabindex="-1"></div>';

        return html;
    }

    function saveEdit() {
        var item = window.RMG_EDIT_ITEM;
        if (!item) return;
        var url = UPDATE_ENDPOINTS[item.role];
        if (!url) return;

        var formData = new FormData();
        var entries = [];
        var fields = el('rmgEditFields').querySelectorAll('[data-edit-field]');
        fields.forEach(function (input) {
            var name = input.getAttribute('data-edit-field');
            if (input.type === 'file') {
                if (input.files && input.files[0]) formData.append(name, input.files[0]);
                return;
            }
            if (input.tagName === 'SELECT' || input.type !== 'file') {
                formData.append(name, input.value);
                entries.push([name, input.value]);
            }
        });
        el('rmgEditFields').querySelectorAll('[data-edit-hidden]').forEach(function (input) {
            formData.append(input.getAttribute('data-edit-hidden'), input.value);
            entries.push([input.getAttribute('data-edit-hidden'), input.value]);
        });

        // The Edit form only requires the photo; every other field, including
        // the change reason, is optional.
        if (!validateWizardForSave('edit', item.edit || {})) {
            el('rmgEditError').textContent = 'Please fix the highlighted fields before saving.';
            el('rmgEditError').hidden = false;
            return;
        }
        el('rmgEditError').hidden = true;

        var doSave = function () {
            if (state.submittingEdit) return;
            state.submittingEdit = true;
            el('rmgEditSave').disabled = true;
            beginSecurityVerify(
                'edit',
                securityTargetWithRef(item.role, item.ref),
                entries,
                'Edit profile · ' + (item.name || securityTargetLabel(securityTargetWithRef(item.role, item.ref)))
            ).then(function (token) {
                if (!token) {
                    el('rmgEditSave').disabled = false;
                    state.submittingEdit = false;
                    return;
                }

                formData.append('security_token', token);

                postForm(url, formData).then(function () {
                    state.formForceClose.edit = true;
                    bootstrap.Modal.getOrCreateInstance(el('rmgEditModal')).hide();
                    state.historyCache = {};
                    load();
                    if (window.showAlert) {
                        window.showAlert('success', 'Changes saved', item.name + ' was updated and recorded in the audit trail.');
                    }
                }).catch(function (err) {
                    var message = err.message || 'Could not save the changes.';
                    if (showFieldServerError('edit', message)) {
                        el('rmgEditError').textContent = message;
                        el('rmgEditError').hidden = false;
                    } else if (window.showAlert) {
                        window.showAlert('error', 'Update failed', message);
                    } else {
                        el('rmgEditError').textContent = message;
                        el('rmgEditError').hidden = false;
                    }
                }).finally(function () {
                    el('rmgEditSave').disabled = false;
                    state.submittingEdit = false;
                });
            }).catch(function () {
                el('rmgEditSave').disabled = false;
                state.submittingEdit = false;
            });
        };

        if (window.showConfirm) {
            window.showConfirm(
                'Save changes?',
                'Update "' + item.name + '"? This will be recorded in the audit trail.',
                doSave
            );
        }
    }

    function cvContainer(index) {
        return document.querySelector('.rmg-cv[data-cv-index="' + index + '"]');
    }

    function collectCv(container) {
        var education = [];
        container.querySelectorAll('[data-cv-item="edu"]').forEach(function (row) {
            var entry = {};
            row.querySelectorAll('[data-field]').forEach(function (input) { entry[input.getAttribute('data-field')] = input.value.trim(); });
            if (Object.keys(entry).some(function (k) { return entry[k] !== ''; })) education.push(entry);
        });
        var experience = [];
        container.querySelectorAll('[data-cv-item="exp"]').forEach(function (row) {
            var entry = {};
            row.querySelectorAll('[data-field]').forEach(function (input) { entry[input.getAttribute('data-field')] = input.value.trim(); });
            if (Object.keys(entry).some(function (k) { return entry[k] !== ''; })) experience.push(entry);
        });
        var skillsInput = container.querySelector('[data-cv-skills]');
        var skills = skillsInput ? skillsInput.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean) : [];
        var textInput = container.querySelector('[data-cv-text]');
        return {
            education: education,
            experience: experience,
            skills: skills,
            resume_text: textInput ? textInput.value : ''
        };
    }

    function setCvEditor(container, cv) {
        var editor = container.querySelector('[data-cv-editor]');
        editor.innerHTML = cvEditorHtml(cv);
        editor.classList.add('open');
    }

    function extractCv(item, index) {
        var container = cvContainer(index);
        if (!container) return;
        var note = container.querySelector('[data-cv-note]');
        var fileInput = container.querySelector('.rmg-cv-input');
        var file = fileInput && fileInput.files ? fileInput.files[0] : null;
        if (!file) {
            note.textContent = 'Choose a PDF, DOC, or DOCX file first.';
            note.classList.add('error');
            note.hidden = false;
            return;
        }

        var formData = new FormData();
        formData.append('subject_type', item.cv_subject);
        formData.append('subject_ref', item.ref);
        formData.append('resume', file);

        note.textContent = 'Extracting… this can take a few seconds.';
        note.classList.remove('error');
        note.hidden = false;

        postFormJson('/admin/api/RoleManagement/CvExtract.php', formData).then(function (res) {
            var data = res.data || {};
            if (data.resume_path) {
                item.documents = item.documents || {};
                item.documents.resume = data.resume_path;
            }
            var cv = {
                education: data.education || [],
                experience: data.experience || [],
                skills: data.skills || [],
                resume_text: data.resume_text || '',
                resume_file: data.resume_path || ''
            };
            var key = item.cv_subject + ':' + item.ref;
            state.cvCache[key] = cv;
            setCvEditor(container, cv);
            note.textContent = data.warning ? data.warning : 'Extraction complete. Review and edit the fields, then save.';
            note.classList.toggle('error', !!data.warning && !cv.education.length && !cv.experience.length);
            note.hidden = false;
        }).catch(function (err) {
            note.textContent = err.message || 'Extraction failed. You can still enter the details manually.';
            note.classList.add('error');
            note.hidden = false;
            setCvEditor(container, { education: [], experience: [], skills: [], resume_text: '' });
        });
    }

    function saveCv(item, index) {
        var container = cvContainer(index);
        if (!container) return;
        var note = container.querySelector('[data-cv-note]');
        var payload = collectCv(container);
        payload.subject_type = item.cv_subject;
        payload.subject_ref = item.ref;
        var key = item.cv_subject + ':' + item.ref;
        if (state.cvCache[key] && state.cvCache[key].resume_file) payload.resume_file = state.cvCache[key].resume_file;

        postJson('/admin/api/RoleManagement/CvSave.php', payload).then(function (res) {
            note.textContent = 'CV data saved.';
            note.classList.remove('error');
            note.hidden = false;
            if (res.data) state.cvCache[key] = res.data;
        }).catch(function (err) {
            note.textContent = err.message || 'Could not save the CV data.';
            note.classList.add('error');
            note.hidden = false;
        });
    }

    function onListClick(event) {
        var clearButton = event.target.closest ? event.target.closest('#rmgEmptyClear') : null;
        if (clearButton) {
            clearFilters();
            return;
        }

        var addButton = event.target.closest('[data-cv-add]');
        if (addButton) {
            var addContainer = cvContainer((addButton.closest('.rmg-cv') || {}).getAttribute('data-cv-index'));
            var list = addContainer.querySelector('[data-cv-list="' + addButton.getAttribute('data-cv-add') + '"]');
            list.insertAdjacentHTML('beforeend', addButton.getAttribute('data-cv-add') === 'edu' ? eduRowHtml({}) : expRowHtml({}));
            return;
        }
        var removeButton = event.target.closest('[data-cv-remove]');
        if (removeButton) {
            removeButton.closest('[data-cv-item]').remove();
            return;
        }

        var target = event.target.closest('[data-action]');
        if (!target) return;
        var action = target.getAttribute('data-action');
        var index = target.getAttribute('data-index');
        var item = findItem(index);

        if (action === 'details' && index !== null) {
            state.detailTab = 'overview';
            openDetail(Number(index));
            return;
        }
        if (action === 'history' && index !== null) {
            state.detailTab = 'activity';
            openDetail(Number(index));
            activateDetailTab('activity');
            return;
        }
        if (!item) return;

        if (action === 'edit') openEdit(item);
        else if (action === 'archive') archiveAction(item, 'archive');
        else if (action === 'restore') archiveAction(item, 'restore');
        else if (action === 'cv-extract') extractCv(item, index);
        else if (action === 'cv-save') saveCv(item, index);
        else if (action === 'cv-toggle') {
            var container = cvContainer(index);
            container.querySelector('[data-cv-editor]').classList.toggle('open');
        } else if (action === 'cv-cancel') {
            var cancelContainer = cvContainer(index);
            var key = item.cv_subject + ':' + item.ref;
            setCvEditor(cancelContainer, state.cvCache[key] || { education: [], experience: [], skills: [], resume_text: '' });
        }
    }

    function init() {
        var url = new URLSearchParams(window.location.search);
        var urlRole = url.get('role');
        var roles = CFG.roles || {};
        if (urlRole && (urlRole === 'all' || roles[urlRole])) state.role = urlRole;
        if (url.get('search')) state.search = url.get('search');
        if (url.get('status') && (CFG.statuses || {})[url.get('status')]) state.status = url.get('status');
        if (url.get('department')) state.department = url.get('department');
        if (url.get('employment')) state.employment = url.get('employment');
        if (url.get('created_from') && /^\d{4}-\d{2}-\d{2}$/.test(url.get('created_from'))) state.createdFrom = url.get('created_from');
        if (url.get('created_to') && /^\d{4}-\d{2}-\d{2}$/.test(url.get('created_to'))) state.createdTo = url.get('created_to');
        if (['newest', 'oldest', 'name_asc', 'name_desc'].indexOf(url.get('sort')) !== -1) state.sort = url.get('sort');
        if (url.get('page')) state.page = Math.max(1, parseInt(url.get('page'), 10) || 1);
        if (url.get('per_page')) state.perPage = parseInt(url.get('per_page'), 10) || 10;

        el('rmgSearch').value = state.search;
        renderStatusOptions();
        el('rmgStatus').value = state.status;
        if (el('rmgArchivedUsers')) {
            el('rmgArchivedUsers').addEventListener('click', function () {
                state.status = state.status === 'archived' ? '' : 'archived';
                state.page = 1; el('rmgStatus').value = state.status; closeDetail(true); load();
            });
        }
        el('rmgPerPage').value = String(state.perPage);
        el('rmgRole').value = state.role;
        if (el('rmgCreatedFrom')) el('rmgCreatedFrom').value = state.createdFrom;
        if (el('rmgCreatedTo')) el('rmgCreatedTo').value = state.createdTo;
        if (el('rmgSort')) el('rmgSort').value = state.sort;
        renderSummary();
        loadFilterOptions();

        el('rmgRole').addEventListener('change', function () {
            state.role = el('rmgRole').value || 'all';
            state.page = 1;
            closeDetail(true);
            load();
        });

        el('rmgDepartment').addEventListener('change', function () {
            state.department = el('rmgDepartment').value;
            state.page = 1;
            closeDetail(true);
            load();
        });

        el('rmgEmployment').addEventListener('change', function () {
            state.employment = el('rmgEmployment').value;
            state.page = 1;
            closeDetail(true);
            load();
        });

        el('rmgCreatedFrom').addEventListener('change', function () {
            state.createdFrom = el('rmgCreatedFrom').value;
            state.page = 1;
            load();
        });

        el('rmgCreatedTo').addEventListener('change', function () {
            state.createdTo = el('rmgCreatedTo').value;
            state.page = 1;
            load();
        });

        el('rmgSort').addEventListener('change', function () {
            state.sort = el('rmgSort').value || 'newest';
            state.page = 1;
            load();
        });

        el('rmgClearFilters').addEventListener('click', clearFilters);

        el('rmgSearch').addEventListener('input', debounce(function () {
            state.search = el('rmgSearch').value.trim();
            state.page = 1;
            closeDetail(true);
            load();
        }, 300));

        el('rmgStatus').addEventListener('change', function () {
            state.status = el('rmgStatus').value;
            state.page = 1;
            closeDetail(true);
            load();
        });

        el('rmgPerPage').addEventListener('change', function () {
            state.perPage = parseInt(el('rmgPerPage').value, 10) || 10;
            state.page = 1;
            closeDetail(true);
            load();
        });

        el('rmgRefresh').addEventListener('click', load);

        el('rmgList').addEventListener('click', onListClick);
        el('rmgDetailBody').addEventListener('click', onListClick);
        el('rmgDetailActions').addEventListener('click', onListClick);
        el('rmgExport').addEventListener('click', exportCsv);

        el('rmgSecurityBtn').addEventListener('click', openSecurityButton);
        el('rmgSecurityConfirm').addEventListener('click', submitSecurityVerify);
        el('rmgSecurityCode').addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                submitSecurityVerify();
            }
        });
        el('rmgSecurityModal').addEventListener('hidden.bs.modal', function () {
            if (pendingSecurity) {
                var resolve = pendingSecurity.resolve;
                pendingSecurity = null;
                resolve(null);
            }
        });
        el('rmgSecSettingsSave').addEventListener('click', saveSecuritySettings);
        el('rmgSecuritySettingsModal').addEventListener('hidden.bs.modal', function () {
            el('rmgSecSettingsError').hidden = true;
        });
        fetchSecurityStatus().then(updateSecurityChip).catch(function () {});

        el('rmgPager').addEventListener('click', function (event) {
            var button = event.target.closest('[data-page]');
            if (!button || button.disabled) return;
            state.page = parseInt(button.getAttribute('data-page'), 10) || 1;
            closeDetail(true);
            load();
        });

        el('rmgEditSave').addEventListener('click', saveEdit);

        el('rmgAddBtn').addEventListener('click', openAdd);
        el('rmgAddRole').addEventListener('change', function () {
            var roleSelect = el('rmgAddRole');
            var proceed = function () {
                state.addRoleLast = roleSelect.value;
                state.addValues = null;
                renderAddFields();
                refreshAddUserId();
            };
            var revert = function () {
                if (state.addRoleLast) roleSelect.value = state.addRoleLast;
            };

            if (state.formDirty.add && state.addRoleLast && state.addRoleLast !== roleSelect.value && typeof window.showConfirm === 'function') {
                window.showConfirm(
                    'Change account role?',
                    'Switching roles clears the fields you have already filled in. Continue?',
                    proceed,
                    revert
                );
                return;
            }

            proceed();
        });
        el('rmgAddSave').addEventListener('click', saveAdd);

        ['rmgAddFields', 'rmgEditFields'].forEach(function (id) {
            var container = el(id);
            container.addEventListener('input', onFormFieldInput);
            container.addEventListener('focusout', onFormFieldBlur);
            container.addEventListener('click', onFormFieldClick);
        });

        el('rmgAddNext').addEventListener('click', function () { goToWizardStep('add', (state.wizard.add || 0) + 1); });
        el('rmgAddBack').addEventListener('click', function () { goToWizardStep('add', (state.wizard.add || 0) - 1); });
        el('rmgEditNext').addEventListener('click', function () { goToWizardStep('edit', (state.wizard.edit || 0) + 1); });
        el('rmgEditBack').addEventListener('click', function () { goToWizardStep('edit', (state.wizard.edit || 0) - 1); });

        ['rmgAddSteps', 'rmgEditSteps'].forEach(function (id) {
            el(id).addEventListener('click', function (event) {
                var chip = event.target.closest ? event.target.closest('[data-wizard-step]') : null;
                if (!chip) return;
                goToWizardStep(
                    chip.getAttribute('data-wizard-mode'),
                    parseInt(chip.getAttribute('data-wizard-step'), 10) || 0
                );
            });
        });

        var reviewUpdater = debounce(function () {
            updateWizardReview('add');
            updateWizardReview('edit');
        }, 250);
        ['rmgAddFields', 'rmgEditFields'].forEach(function (id) {
            var container = el(id);
            container.addEventListener('input', reviewUpdater);
            container.addEventListener('change', reviewUpdater);
        });

        bindUnsavedGuard('rmgAddModal', 'add', 'rmgAddFields');
        bindUnsavedGuard('rmgEditModal', 'edit', 'rmgEditFields');

        el('rmgAddFields').addEventListener('input', function () { updateAgeNotes(el('rmgAddFields')); });
        el('rmgAddFields').addEventListener('change', function () { updateAgeNotes(el('rmgAddFields')); });
        el('rmgEditFields').addEventListener('input', function () { updateAgeNotes(el('rmgEditFields')); });
        el('rmgEditFields').addEventListener('change', function () { updateAgeNotes(el('rmgEditFields')); });

        load();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
