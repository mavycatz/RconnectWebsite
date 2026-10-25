(function () {
    'use strict';

    var API_BASE = '/admin/api/Admin/FarePricing.php';
    var FARE_FIELDS = ['regular_fare', 'student_fare', 'special_fare'];

    var els = {};

    var state = {
        route: '',
        rows: [],
        selection: new Set(),
        editingUid: null,
        focusField: null,
        saving: false,
        justSaved: false,
        draggedUid: null
    };

    var uidCounter = 0;
    var toast = null;
    var beforeUnloadHandler = null;


    function nextUid() {
        uidCounter += 1;
        return 'fp-row-' + uidCounter;
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function toNumberOrNull(value) {
        var raw = String(value === null || value === undefined ? '' : value).trim();
        if (raw === '') {
            return null;
        }
        var number = Number(raw);
        return Number.isFinite(number) ? number : null;
    }

    function formatFare(value) {
        var number = toNumberOrNull(value);
        if (number === null || number < 0) {
            return '\u2014';
        }
        return '\u20B1' + number.toFixed(2);
    }

    function formatFareInput(value) {
        var number = toNumberOrNull(value);
        if (number === null || number < 0) {
            return '';
        }
        return number.toFixed(2);
    }

    function finalizeFare(value) {
        var number = toNumberOrNull(value);
        if (number === null || number < 0) {
            return String(value === null || value === undefined ? '' : value).trim();
        }
        return number.toFixed(2);
    }

    function findRow(uid) {
        for (var i = 0; i < state.rows.length; i += 1) {
            if (state.rows[i].uid === uid) {
                return state.rows[i];
            }
        }
        return null;
    }

    function getToast() {
        if (!toast && typeof Swal !== 'undefined') {
            toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2400,
                timerProgressBar: true,
                didOpen: function (element) {
                    element.addEventListener('mouseenter', Swal.stopTimer);
                    element.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
        }
        return toast;
    }

    function showToast(icon, title, text) {
        var instance = getToast();
        if (!instance) {
            return;
        }
        instance.fire({
            icon: icon,
            title: title,
            text: text || undefined
        });
    }


    function pendingChangeCount() {
        var count = 0;
        state.rows.forEach(function (row) {
            if (row.state !== 'clean' || row.orderChanged) {
                count += 1;
            }
        });
        return count;
    }

    function refreshOrderFlags() {
        state.rows.forEach(function (row, index) {
            if (row.state === 'new') {
                row.orderChanged = false;
                return;
            }
            row.orderChanged = row.originalIndex !== null && row.originalIndex !== index;
        });
    }

    function finalizeRow(row) {
        FARE_FIELDS.forEach(function (field) {
            row[field] = finalizeFare(row[field]);
        });
    }

    function updateUnloadGuard() {
        var hasPending = pendingChangeCount() > 0;

        if (hasPending && !beforeUnloadHandler) {
            beforeUnloadHandler = function (event) {
                event.preventDefault();
                event.returnValue = '';
                return '';
            };
            window.addEventListener('beforeunload', beforeUnloadHandler);
        } else if (!hasPending && beforeUnloadHandler) {
            window.removeEventListener('beforeunload', beforeUnloadHandler);
            beforeUnloadHandler = null;
        }
    }

    function updateSaveStatus() {
        var pending = pendingChangeCount();
        var className = 'fp-status is-clean';
        var label = 'No changes';

        if (state.saving) {
            className = 'fp-status is-saving';
            label = 'Saving...';
        } else if (pending > 0) {
            className = 'fp-status is-dirty';
            label = 'Unsaved changes';
        } else if (state.justSaved) {
            className = 'fp-status is-saved';
            label = 'Saved just now';
        }

        els.saveStatus.className = className;
        els.saveStatus.textContent = label;

        els.saveBtn.textContent = pending > 0
        ? 'Save Changes \u2022 ' + pending
        : 'Save Changes';
        els.saveBtn.disabled = pending === 0 || state.saving;
    }

    function updateSelectionUI() {
        var selectedCount = state.selection.size;

        els.toolbar.hidden = selectedCount === 0;
        els.selectionCount.textContent = selectedCount + ' selected';
        els.deleteSelectedBtn.disabled = selectedCount === 0;

        var total = state.rows.length;
        var selectedVisible = 0;

        state.rows.forEach(function (row) {
            if (state.selection.has(row.uid)) {
                selectedVisible += 1;
            }
        });

        els.selectAll.disabled = total === 0;
        els.selectAll.checked = total > 0 && selectedVisible === total;
        els.selectAll.indeterminate = selectedVisible > 0 && selectedVisible < total;

        Array.prototype.forEach.call(
        els.tbody.querySelectorAll('tr[data-uid]'),
        function (tr) {
            var selected = state.selection.has(tr.dataset.uid);
            tr.classList.toggle('is-selected', selected);
            var checkbox = tr.querySelector('.fp-row-check');
            if (checkbox) {
                checkbox.checked = selected;
            }
        }
        );
    }

    function updateCounters() {
        var total = state.rows.length;

        els.destinationCount.textContent = String(total);
        els.rangeText.hidden = total === 0;
        els.rangeText.textContent = total === 0
        ? 'Showing 0 of 0 destinations'
        : 'Showing 1\u2013' + total + ' of ' + total + ' destinations';

        updateSaveStatus();
        updateSelectionUI();
        updateUnloadGuard();
    }


    function rowBadgesHtml(row) {
        if (row.state === 'new') {
            return '<span class="fp-badge fp-badge-new">New</span>';
        }
        if (row.state === 'modified' || row.orderChanged) {
            return '<span class="fp-badge fp-badge-modified">Modified</span>';
        }
        return '';
    }

    function destinationCellHtml(row, editing, index) {
        var html = '<td class="fp-col-destination">';

        if (editing) {
            html += '<input type="text" class="fp-input fp-input-destination"'
            + ' data-field="destination"'
            + ' value="' + escapeHtml(row.destination) + '"'
            + ' placeholder="Destination"'
            + ' autocomplete="off"'
            + ' aria-label="Destination ' + (index + 1) + '">';
        } else {
            var destination = String(row.destination === null || row.destination === undefined
            ? ''
            : row.destination).trim();

            html += destination === ''
            ? '<span class="fp-dest-text fp-muted">Untitled destination</span>'
            : '<span class="fp-dest-text">' + escapeHtml(destination) + '</span>';
        }

        html += '<span class="fp-row-badges">' + rowBadgesHtml(row) + '</span>';
        html += '</td>';
        return html;
    }

    function fareCellHtml(row, field, editing, index) {
        if (editing) {
            return '<td class="fp-col-fare">'
            + '<input type="number" class="fp-input fp-input-fare"'
            + ' data-field="' + field + '"'
            + ' value="' + escapeHtml(formatFareInput(row[field])) + '"'
            + ' min="0" step="0.01" inputmode="decimal"'
            + ' aria-label="' + field.replace('_', ' ') + ' ' + (index + 1) + '">'
            + '</td>';
        }

        return '<td class="fp-col-fare">'
        + '<span class="fp-fare-cell">' + escapeHtml(formatFare(row[field])) + '</span>'
        + '</td>';
    }

    function rowHtml(row, index) {
        var editing = state.editingUid === row.uid;
        var selected = state.selection.has(row.uid);
        var classes = 'fp-row';

        if (selected) {
            classes += ' is-selected';
        }
        if (row.state === 'new') {
            classes += ' is-new';
        }

        var html = '<tr class="' + classes + '" data-uid="' + row.uid + '"'
        + ' data-id="' + escapeHtml(row.id) + '">';

        html += '<td class="fp-col-check">'
        + '<input type="checkbox" class="fp-row-check"' + (selected ? ' checked' : '')
        + ' aria-label="Select destination ' + (index + 1) + '">'
        + '</td>';

        html += '<td class="fp-col-num">' + (index + 1) + '</td>';

        html += '<td class="fp-col-order">'
        + '<span class="fp-drag-handle" draggable="true" title="Drag to reorder"'
        + ' aria-hidden="true">\u28FF</span>'
        + '</td>';

        html += destinationCellHtml(row, editing, index);
        html += fareCellHtml(row, 'regular_fare', editing, index);
        html += fareCellHtml(row, 'student_fare', editing, index);
        html += fareCellHtml(row, 'special_fare', editing, index);

        html += '<td class="fp-col-actions"><span class="fp-actions">';
        if (editing) {
            html += '<button type="button" class="fp-action-btn fp-action-btn-done"'
            + ' data-action="done">Done</button>';
        } else {
            html += '<button type="button" class="fp-action-btn"'
            + ' data-action="edit">Edit</button>'
            + '<button type="button" class="fp-action-btn fp-action-btn-danger"'
            + ' data-action="delete-row">Delete</button>';
        }
        html += '</span></td>';

        html += '</tr>';
        return html;
    }

    function focusEditingRow() {
        var editingRow = null;

        state.rows.forEach(function (row) {
            if (row.uid === state.editingUid) {
                editingRow = row;
            }
        });

        if (!editingRow) {
            return;
        }

        var field = state.focusField || 'destination';
        var input = els.tbody.querySelector(
        'tr[data-uid="' + editingRow.uid + '"] [data-field="' + field + '"]'
        );

        if (input) {
            input.focus();
            if (input.type !== 'number' && typeof input.setSelectionRange === 'function') {
                try {
                    input.setSelectionRange(input.value.length, input.value.length);
                } catch (error) {
                }
            }
        }

        state.focusField = null;
    }

    function renderRows() {
        els.tbody.innerHTML = state.rows.map(rowHtml).join('');
        els.empty.hidden = state.rows.length > 0;
        focusEditingRow();
    }

    function renderFarePricing() {
        renderRows();
        updateCounters();
    }

    function updateRowBadges(row) {
        var badges = els.tbody.querySelector(
        'tr[data-uid="' + row.uid + '"] .fp-row-badges'
        );
        if (badges) {
            badges.innerHTML = rowBadgesHtml(row);
        }
    }


    function loadFarePricing(keepSavedStatus) {
        var route = els.routeSelector.value;

        return fetch(API_BASE + '?route=' + encodeURIComponent(route))
        .then(function (response) {
            return response.text();
        })
        .then(function (raw) {
            var result;

            try {
                result = JSON.parse(raw);
            } catch (error) {
                throw new Error('Unexpected server response. ' + raw.slice(0, 160));
            }

            if (!result.success) {
                throw new Error(result.message || 'Unable to load fare pricing.');
            }

            state.route = route;
            state.rows = (result.data || []).map(function (fare, index) {
                return {
                    uid: nextUid(),
                    id: fare.id === null || fare.id === undefined ? '' : fare.id,
                    destination: fare.destination === null || fare.destination === undefined
                    ? ''
                    : String(fare.destination),
                    regular_fare: finalizeFare(fare.regular_fare),
                    student_fare: finalizeFare(fare.student_fare),
                    special_fare: finalizeFare(fare.special_fare),
                    state: 'clean',
                    orderChanged: false,
                    originalIndex: index
                };
            });
            state.selection = new Set();
            state.editingUid = null;
            state.focusField = null;
            state.draggedUid = null;
            state.justSaved = keepSavedStatus === true;

            renderFarePricing();
        })
        .catch (function (error) {
            showToast(
            'error',
            error && error.message ? error.message : 'Unable to load fare pricing.'
            );
        });
    }


    function startEditing(uid) {
        state.editingUid = uid;
        state.focusField = 'destination';
        renderFarePricing();
    }

    function exitEditing(uid) {
        if (state.editingUid !== uid) {
            return;
        }

        var row = findRow(uid);
        if (row) {
            finalizeRow(row);
        }

        state.editingUid = null;
        state.focusField = null;
        renderFarePricing();
    }

    function handleGridInput(event) {
        var input = event.target instanceof Element
        ? event.target.closest('[data-field]')
        : null;
        if (!input) {
            return;
        }

        var tr = input.closest('tr[data-uid]');
        if (!tr) {
            return;
        }

        var row = findRow(tr.dataset.uid);
        if (!row) {
            return;
        }

        var field = input.dataset.field;

        if (field === 'destination') {
            row.destination = input.value;
        } else {
            var rawValue = String(input.value === null || input.value === undefined
            ? ''
            : input.value);

            if (rawValue === '' && input.validity && input.validity.badInput) {
                return;
            }

            var cleaned = rawValue.replace(/-/g, '');
            if (cleaned !== rawValue) {
                input.value = cleaned;
            }
            row[field] = cleaned;
        }

        if (row.state === 'clean') {
            row.state = 'modified';
        }

        state.justSaved = false;
        updateRowBadges(row);
        updateCounters();
    }

    function handleGridKeyDown(event) {
        if (event.key !== 'Enter') {
            return;
        }

        var input = event.target instanceof Element
        ? event.target.closest('[data-field]')
        : null;
        if (!input || !els.tbody.contains(input)) {
            return;
        }

        event.preventDefault();

        var tr = input.closest('tr[data-uid]');
        if (tr) {
            exitEditing(tr.dataset.uid);
        }
    }

    function handleGridFocusOut(event) {
        var tr = event.target instanceof Element
        ? event.target.closest('tr[data-uid]')
        : null;
        if (!tr || tr.dataset.uid !== state.editingUid) {
            return;
        }

        var next = event.relatedTarget;
        if (next && tr.contains(next)) {
            return;
        }

        exitEditing(tr.dataset.uid);
    }

    function handleGridMouseDown(event) {
        if (event.button !== 0 || !(event.target instanceof Element)) {
            return;
        }

        var interactive = event.target.closest('button, input, select, a, .fp-drag-handle, [data-action]');
        if (!interactive) {
            return;
        }

        var tr = interactive.closest('tr[data-uid]');
        if (!tr) {
            return;
        }

        if (state.editingUid && tr.dataset.uid !== state.editingUid) {
            event.preventDefault();
        }
    }


    function handleGridChange(event) {
        var checkbox = event.target instanceof Element
        ? event.target.closest('.fp-row-check')
        : null;
        if (!checkbox) {
            return;
        }

        var tr = checkbox.closest('tr[data-uid]');
        if (!tr) {
            return;
        }

        if (checkbox.checked) {
            state.selection.add(tr.dataset.uid);
        } else {
            state.selection.delete (tr.dataset.uid);
        }

        updateSelectionUI();

        if (checkbox.checked) {
            var count = state.selection.size;
            showToast('info', count + ' destination' + (count === 1 ? '' : 's') + ' selected');
        }
    }

    function toggleSelectAll() {
        var checked = els.selectAll.checked;

        state.selection = new Set();
        if (checked) {
            state.rows.forEach(function (row) {
                state.selection.add(row.uid);
            });
        }

        updateSelectionUI();

        var count = state.selection.size;
        if (count > 0) {
            showToast('info', count + ' destination' + (count === 1 ? '' : 's') + ' selected');
        }
    }

    function clearSelection() {
        state.selection = new Set();
        updateSelectionUI();
    }


    function addDestination() {
        var row = {
            uid: nextUid(),
            id: '',
            destination: '',
            regular_fare: '0.00',
            student_fare: '0.00',
            special_fare: '0.00',
            state: 'new',
            orderChanged: false,
            originalIndex: null
        };

        state.rows.push(row);
        state.editingUid = row.uid;
        state.focusField = 'destination';
        state.justSaved = false;

        renderFarePricing();
        showToast('success', 'New destination added', 'Complete its fares before saving.');
    }

    function deleteFareRow(id) {
        return fetch(API_BASE + '?action=delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: Number(id) })
        })
        .then(function (response) {
            return response.text();
        })
        .then(function (raw) {
            var result;

            try {
                result = JSON.parse(raw);
            } catch (error) {
                throw new Error('Unexpected server response. ' + raw.slice(0, 160));
            }

            if (!result.success) {
                throw new Error(result.message || 'Unable to delete destination.');
            }

            return result;
        });
    }

    function removeRows(uids) {
        var removedOriginalIndexes = [];

        state.rows.forEach(function (row) {
            if (uids.indexOf(row.uid) === -1) {
                return;
            }
            if (row.originalIndex !== null && row.originalIndex !== undefined) {
                removedOriginalIndexes.push(row.originalIndex);
            }
        });

        state.rows = state.rows.filter(function (row) {
            return uids.indexOf(row.uid) === -1;
        });

        state.rows.forEach(function (row) {
            if (row.originalIndex === null || row.originalIndex === undefined) {
                return;
            }
            removedOriginalIndexes.forEach(function (originalIndex) {
                if (originalIndex < row.originalIndex) {
                    row.originalIndex -= 1;
                }
            });
        });

        state.selection = new Set();
        state.draggedUid = null;

        if (state.editingUid && uids.indexOf(state.editingUid) !== -1) {
            state.editingUid = null;
            state.focusField = null;
        }

        refreshOrderFlags();
        state.justSaved = false;
        renderFarePricing();
    }

    function deleteRows(uids) {
        if (uids.length === 0) {
            return;
        }

        var targets = state.rows.filter(function (row) {
            return uids.indexOf(row.uid) !== -1;
        });

        if (targets.length === 0) {
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Delete ' + targets.length + ' destination' + (targets.length === 1 ? '' : 's') + '?',
            text: 'This cannot be undone.',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#b02a37',
            reverseButtons: true
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            var persisted = targets.filter(function (row) {
                return Number(row.id) > 0;
            });

            var failures = [];
            var chain = Promise.resolve();

            persisted.forEach(function (row) {
                chain = chain.then(function () {
                    return deleteFareRow(row.id).catch (function (error) {
                        failures.push({ row: row, error: error });
                    });
                });
            });

            chain.then(function () {
                var failedUids = failures.map(function (failure) {
                    return failure.row.uid;
                });

                var removedUids = targets
                .filter(function (row) {
                    return failedUids.indexOf(row.uid) === -1;
                })
                .map(function (row) {
                    return row.uid;
                });

                removeRows(removedUids);

                if (removedUids.length > 0) {
                    showToast(
                    'success',
                    'Deleted ' + removedUids.length + ' destination'
                    + (removedUids.length === 1 ? '' : 's')
                    );
                }

                if (failures.length > 0) {
                    showToast(
                    'error',
                    failures[0].error && failures[0].error.message
                    ? failures[0].error.message
                    : 'Unable to delete destination.'
                    );
                }
            });
        });
    }

    function moveSelected(position) {
        var selected = [];
        var rest = [];

        state.rows.forEach(function (row) {
            if (state.selection.has(row.uid)) {
                selected.push(row);
            } else {
                rest.push(row);
            }
        });

        if (selected.length === 0) {
            return;
        }

        state.rows = position === 'top'
        ? selected.concat(rest)
        : rest.concat(selected);

        refreshOrderFlags();
        state.justSaved = false;
        renderFarePricing();

        showToast(
        'success',
        'Moved ' + selected.length + ' destination' + (selected.length === 1 ? '' : 's')
        + ' to ' + (position === 'top' ? 'top' : 'bottom')
        );
    }


    function handleDragStart(event) {
        var handle = event.target instanceof Element
        ? event.target.closest('.fp-drag-handle')
        : null;
        if (!handle) {
            event.preventDefault();
            return;
        }

        var tr = handle.closest('tr[data-uid]');
        if (!tr) {
            event.preventDefault();
            return;
        }

        state.draggedUid = tr.dataset.uid;
        tr.classList.add('is-dragging');

        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            try {
                event.dataTransfer.setData('text/plain', tr.dataset.uid);
            } catch (error) {
            }
        }
    }

    function handleDragOver(event) {
        if (!state.draggedUid) {
            return;
        }

        var tr = event.target instanceof Element
        ? event.target.closest('tr[data-uid]')
        : null;
        if (!tr || tr.dataset.uid === state.draggedUid) {
            return;
        }

        event.preventDefault();
        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move';
        }
    }

    function handleDrop(event) {
        if (!state.draggedUid) {
            return;
        }

        var tr = event.target instanceof Element
        ? event.target.closest('tr[data-uid]')
        : null;
        if (!tr) {
            return;
        }

        event.preventDefault();

        var draggedUid = state.draggedUid;
        var targetUid = tr.dataset.uid;

        state.draggedUid = null;

        if (draggedUid === targetUid) {
            return;
        }

        var from = -1;
        var to = -1;

        state.rows.forEach(function (row, index) {
            if (row.uid === draggedUid) {
                from = index;
            }
            if (row.uid === targetUid) {
                to = index;
            }
        });

        if (from === -1 || to === -1) {
            return;
        }

        var moved = state.rows.splice(from, 1)[0];
        state.rows.splice(to, 0, moved);

        refreshOrderFlags();
        state.justSaved = false;
        renderFarePricing();
    }

    function handleDragEnd() {
        state.draggedUid = null;
        Array.prototype.forEach.call(
        els.tbody.querySelectorAll('.is-dragging'),
        function (tr) {
            tr.classList.remove('is-dragging');
        }
        );
    }


    function closeMoveDropdown() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Dropdown) {
            return;
        }

        var toggle = document.getElementById('moveFareDropdown');
        var instance = toggle ? bootstrap.Dropdown.getInstance(toggle) : null;

        if (instance) {
            instance.hide();
        }
    }

    function performAction(button) {
        var action = button.dataset.action;
        var tr = button.closest('tr[data-uid]');
        var uid = tr ? tr.dataset.uid : null;

        if (action === 'edit' && uid) {
            startEditing(uid);
        } else if (action === 'done' && uid) {
            exitEditing(uid);
        } else if (action === 'delete-row' && uid) {
            deleteRows([uid]);
        } else if (action === 'move-top') {
            closeMoveDropdown();
            moveSelected('top');
        } else if (action === 'move-bottom') {
            closeMoveDropdown();
            moveSelected('bottom');
        }
    }

    function handleActionClick(event) {
        var button = event.target instanceof Element
        ? event.target.closest('[data-action]')
        : null;
        if (!button || !els.section.contains(button)) {
            return;
        }

        performAction(button);
    }


    function collectValidationErrors() {
        var errors = [];
        var seenDestinations = {};

        state.rows.forEach(function (row) {
            var destination = String(row.destination === null || row.destination === undefined
            ? ''
            : row.destination).trim();

            if (destination === '') {
                errors.push({
                    uid: row.uid,
                    field: 'destination',
                    message: 'Destination is required.'
                });
            } else {
                var key = destination.toLowerCase();
                if (Object.prototype.hasOwnProperty.call(seenDestinations, key)) {
                    errors.push({
                        uid: row.uid,
                        field: 'destination',
                        message: 'Duplicate destination "' + destination
                        + '". Destination names must be unique per route.'
                    });
                } else {
                    seenDestinations[key] = true;
                }
            }

            FARE_FIELDS.forEach(function (field) {
                var raw = String(row[field] === null || row[field] === undefined
                ? ''
                : row[field]).trim();
                var value = Number(raw);

                if (raw === '' || !Number.isFinite(value)) {
                    errors.push({
                        uid: row.uid,
                        field: field,
                        message: 'Enter a valid fare.'
                    });
                } else if (value < 0) {
                    errors.push({
                        uid: row.uid,
                        field: field,
                        message: 'Fare cannot be negative.'
                    });
                }
            });
        });

        return errors;
    }

    function showValidationError(error) {
        var row = findRow(error.uid);

        if (row) {
            state.editingUid = row.uid;
            state.focusField = error.field;
            renderFarePricing();

            var input = els.tbody.querySelector(
            'tr[data-uid="' + row.uid + '"] [data-field="' + error.field + '"]'
            );
            if (input) {
                input.classList.add('is-invalid');
                input.focus();
            }
        }

        showToast('error', error.message);
    }

    function buildSavePayload() {
        return {
            route_name: els.routeSelector.value,
            fares: state.rows.map(function (row, index) {
                return {
                    id: row.id || '',
                    display_order: index + 1,
                    destination: String(row.destination).trim(),
                    regular_fare: Number(Number(row.regular_fare).toFixed(2)),
                    student_fare: Number(Number(row.student_fare).toFixed(2)),
                    special_fare: Number(Number(row.special_fare).toFixed(2))
                };
            })
        };
    }

    function saveFarePricing() {
        if (state.saving) {
            return;
        }

        var errors = collectValidationErrors();
        if (errors.length > 0) {
            showValidationError(errors[0]);
            return;
        }

        var editingRow = state.editingUid ? findRow(state.editingUid) : null;
        if (editingRow) {
            finalizeRow(editingRow);
        }
        state.editingUid = null;
        state.focusField = null;
        state.rows.forEach(finalizeRow);

        state.saving = true;
        state.justSaved = false;
        renderFarePricing();

        fetch(API_BASE + '?action=save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(buildSavePayload())
        })
        .then(function (response) {
            return response.text();
        })
        .then(function (raw) {
            var result;

            try {
                result = JSON.parse(raw);
            } catch (error) {
                throw new Error('Unexpected server response. ' + raw.slice(0, 160));
            }

            if (!result.success) {
                throw new Error(result.message || 'Unable to save fare pricing.');
            }

            state.saving = false;
            state.justSaved = true;
            showToast('success', result.message || 'Fare pricing saved');

            return loadFarePricing(true);
        })
        .catch (function (error) {
            state.saving = false;
            state.justSaved = false;
            renderFarePricing();
            showToast(
            'error',
            error && error.message ? error.message : 'Unable to save fare pricing.'
            );
        });
    }


    function handleRouteChange() {
        var nextRoute = els.routeSelector.value;

        if (nextRoute === state.route || pendingChangeCount() === 0) {
            loadFarePricing(false);
            return;
        }

        var previousRoute = state.route;

        Swal.fire({
            icon: 'warning',
            title: 'Unsaved changes',
            text: 'Changing the route will discard your unsaved changes.',
            showCancelButton: true,
            confirmButtonText: 'Leave',
            cancelButtonText: 'Stay',
            confirmButtonColor: '#b02a37',
            reverseButtons: true
        }).then(function (result) {
            if (result.isConfirmed) {
                loadFarePricing(false);
            } else if (previousRoute) {
                els.routeSelector.value = previousRoute;
            }
        });
    }


    function printFarePricing() {
        var option = els.routeSelector.options[els.routeSelector.selectedIndex];
        var routeLabel = option ? option.text : els.routeSelector.value;

        var tableRows = state.rows.map(function (row) {
            return '<tr>'
            + '<td>' + escapeHtml(String(row.destination).trim()) + '</td>'
            + '<td class="num">' + escapeHtml(formatFare(row.regular_fare)) + '</td>'
            + '<td class="num">' + escapeHtml(formatFare(row.student_fare)) + '</td>'
            + '<td class="num">' + escapeHtml(formatFare(row.special_fare)) + '</td>'
            + '</tr>';
        }).join('');

        if (tableRows === '') {
            tableRows = '<tr><td colspan="4" class="empty">No destinations for this route.</td></tr>';
        }

        var printWindow = window.open('', '', 'width=1000,height=700');

        if (!printWindow) {
            showToast('error', 'Unable to open the print window. Check your popup blocker.');
            return;
        }

        printWindow.document.write('<html><head><title>R-Connect Fare Pricing</title>'
        + '<style>'
        + 'body{font-family:Arial,Helvetica,sans-serif;color:#2e4836;padding:32px;}'
        + '.header{text-align:center;margin-bottom:24px;}'
        + '.title{font-size:22px;font-weight:bold;}'
        + '.route{margin-top:6px;font-size:14px;color:#4a5c4d;}'
        + 'table{width:100%;border-collapse:collapse;font-size:13px;}'
        + 'th{background:#2e4836;color:#fff;padding:10px 12px;text-align:left;}'
        + 'th.num,td.num{text-align:right;}'
        + 'td{padding:9px 12px;border-bottom:1px solid #e0e0d8;}'
        + 'tbody tr:nth-child(even){background:#f7f8f2;}'
        + '.empty{text-align:center;color:#8a948b;padding:18px;}'
        + '.footer{margin-top:28px;text-align:right;font-size:11px;color:#6b7a6d;}'
        + '</style></head><body>'
        + '<div class="header">'
        + '<div class="title">R-Connect Fare Pricing</div>'
        + '<div class="route">' + escapeHtml(routeLabel) + '</div>'
        + '</div>'
        + '<table><thead><tr>'
        + '<th>Destination</th>'
        + '<th class="num">Regular</th>'
        + '<th class="num">Student / Senior</th>'
        + '<th class="num">Special</th>'
        + '</tr></thead><tbody>'
        + tableRows
        + '</tbody></table>'
        + '<div class="footer">Generated: ' + escapeHtml(new Date().toLocaleString()) + '</div>'
        + '</body></html>');

        printWindow.document.close();
        printWindow.focus();

        setTimeout(function () {
            printWindow.print();
            printWindow.close();
        }, 400);
    }


    function cacheElements() {
        els.section = document.getElementById('farePricingSection');
        els.routeSelector = document.getElementById('routeSelector');
        els.saveStatus = document.getElementById('fareSaveStatus');
        els.saveBtn = document.getElementById('saveFarePricingBtn');
        els.printBtn = document.getElementById('printFarePricingBtn');
        els.addBtn = document.getElementById('addFareRowBtn');
        els.toolbar = document.getElementById('fareSelectionToolbar');
        els.selectionCount = document.getElementById('fareSelectionCount');
        els.deleteSelectedBtn = document.getElementById('deleteFareRowsBtn');
        els.clearSelectionBtn = document.getElementById('clearFareSelectionBtn');
        els.selectAll = document.getElementById('selectAllFareRows');
        els.tbody = document.getElementById('farePricingTable');
        els.empty = document.getElementById('fareGridEmpty');
        els.destinationCount = document.getElementById('fareDestinationCount');
        els.rangeText = document.getElementById('fareRangeText');
    }

    function bindEvents() {
        els.routeSelector.addEventListener('change', handleRouteChange);
        els.saveBtn.addEventListener('click', saveFarePricing);
        els.printBtn.addEventListener('click', printFarePricing);
        els.addBtn.addEventListener('click', addDestination);
        els.clearSelectionBtn.addEventListener('click', clearSelection);
        els.selectAll.addEventListener('change', toggleSelectAll);

        els.deleteSelectedBtn.addEventListener('click', function () {
            deleteRows(Array.from(state.selection));
        });

        els.section.addEventListener('click', handleActionClick);

        els.tbody.addEventListener('input', handleGridInput);
        els.tbody.addEventListener('change', handleGridChange);
        els.tbody.addEventListener('keydown', handleGridKeyDown);
        els.tbody.addEventListener('focusout', handleGridFocusOut);
        els.tbody.addEventListener('mousedown', handleGridMouseDown);
        els.tbody.addEventListener('dragstart', handleDragStart);
        els.tbody.addEventListener('dragover', handleDragOver);
        els.tbody.addEventListener('drop', handleDrop);
        els.tbody.addEventListener('dragend', handleDragEnd);
    }

    function init() {
        cacheElements();

        if (!els.section) {
            return;
        }

        bindEvents();
        updateCounters();
        loadFarePricing(false);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
