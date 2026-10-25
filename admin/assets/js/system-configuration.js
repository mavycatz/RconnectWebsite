(function () {
    'use strict';

    var API_BASE = '/admin/api/Admin/SystemConfiguration.php';

    var els = {};
    var vehicleModal = null;
    var fuelModal = null;
    var toast = null;

    var vehicleState = {
        rows: [],
        loading: false,
        saving: false
    };

    var fuelState = {
        rows: [],
        loading: false,
        saving: false
    };

    var vehicleFieldMap = {
        vehicle_name: 'vehicleTypeName',
        seats: 'vehicleTypeSeats',
        standing_capacity: 'vehicleTypeStanding'
    };

    var fuelFieldMap = {
        fuel_type: 'fuelTypeName',
        price_per_liter: 'fuelTypePrice'
    };


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

    function toWholeNumber(value) {
        var raw = String(value === null || value === undefined ? '' : value).trim();
        if (!/^\d+$/.test(raw)) {
            return null;
        }
        return parseInt(raw, 10);
    }

    function formatPriceInput(value) {
        var number = toNumberOrNull(value);
        if (number === null || number < 0) {
            return '';
        }
        return number.toFixed(2);
    }

    function getToast() {
        if (!toast && typeof Swal !== 'undefined') {
            toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2600,
                timerProgressBar: true,
                didOpen: function (element) {
                    element.addEventListener('mouseenter', Swal.stopTimer);
                    element.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
        }
        return toast;
    }

    function showToast(icon, title) {
        var instance = getToast();
        if (instance) {
            instance.fire({
                icon: icon,
                title: title
            });
        }
    }

    function requestJson(url, options) {
        return fetch(url, options || {}).then(function (response) {
            return response.text().then(function (raw) {
                var result;

                try {
                    result = JSON.parse(raw);
                } catch (error) {
                    throw new Error('Unexpected server response. ' + raw.slice(0, 160));
                }

                if (!result || result.success !== true) {
                    var failure = new Error(
                    (result && result.message) || 'Request failed.'
                    );
                    if (result && result.field) {
                        failure.field = result.field;
                    }
                    throw failure;
                }

                return result;
            });
        });
    }

    function setAreaState(element, html) {
        if (html) {
            element.innerHTML = html;
            element.hidden = false;
        } else {
            element.innerHTML = '';
            element.hidden = true;
        }
    }

    function errorStateHtml(area) {
        return '<div class="sc-state-card">'
        + '<i class="bi bi-exclamation-triangle"></i>'
        + '<div class="sc-state-title">Unable to load data</div>'
        + '<p class="sc-state-text">Something went wrong while retrieving this information.</p>'
        + '<button type="button" class="btn sc-btn sc-btn-light" data-retry="' + area + '">'
        + '<i class="bi bi-arrow-clockwise"></i> Retry</button>'
        + '</div>';
    }

    function emptyStateHtml(message) {
        return '<div class="sc-state-card sc-state-empty">'
        + '<i class="bi bi-inbox"></i>'
        + '<p>' + escapeHtml(message) + '</p>'
        + '</div>';
    }


    function findVehicle(id) {
        for (var i = 0; i < vehicleState.rows.length; i += 1) {
            if (Number(vehicleState.rows[i].id) === Number(id)) {
                return vehicleState.rows[i];
            }
        }
        return null;
    }

    function vehicleSkeletonHtml() {
        var row = '<tr class="sc-skeleton-row">'
        + '<td><span class="sc-skeleton sc-skeleton-text"></span></td>'
        + '<td><span class="sc-skeleton sc-skeleton-num"></span></td>'
        + '<td><span class="sc-skeleton sc-skeleton-num"></span></td>'
        + '<td><span class="sc-skeleton sc-skeleton-icon"></span></td>'
        + '</tr>';
        return row + row + row;
    }

    function vehicleRowHtml(row) {
        var name = escapeHtml(row.vehicle_name);
        var id = escapeHtml(row.id);

        return '<tr data-id="' + id + '">'
        + '<td><span class="sc-vehicle-name">' + name + '</span></td>'
        + '<td class="sc-num">' + escapeHtml(row.seats) + '</td>'
        + '<td class="sc-num">' + escapeHtml(row.standing_capacity) + '</td>'
        + '<td class="sc-actions-col">'
        + '<div class="dropdown sc-row-menu">'
        + '<button class="sc-icon-btn" type="button" data-bs-toggle="dropdown"'
        + ' data-bs-boundary="viewport" aria-expanded="false"'
        + ' aria-label="Actions for ' + name + '">'
        + '<i class="bi bi-three-dots-vertical"></i>'
        + '</button>'
        + '<ul class="dropdown-menu dropdown-menu-end">'
        + '<li><button class="dropdown-item" type="button" data-action="edit" data-id="' + id + '">'
        + '<i class="bi bi-pencil"></i> Edit</button></li>'
        + '<li><button class="dropdown-item sc-danger" type="button" data-action="delete" data-id="' + id + '">'
        + '<i class="bi bi-trash"></i> Delete</button></li>'
        + '</ul>'
        + '</div>'
        + '</td>'
        + '</tr>';
    }

    function loadVehicleTypes() {
        vehicleState.loading = true;
        els.vehicleTypesTable.innerHTML = vehicleSkeletonHtml();
        setAreaState(els.vehicleTypesState, '');

        requestJson(API_BASE + '?action=getVehicleTypes')
        .then(function (result) {
            vehicleState.loading = false;
            vehicleState.rows = result.data || [];

            els.vehicleTypesTable.innerHTML = vehicleState.rows
            .map(vehicleRowHtml)
            .join('');

            setAreaState(
            els.vehicleTypesState,
            vehicleState.rows.length === 0
            ? emptyStateHtml('No vehicle types yet. Add one to get started.')
            : ''
            );
        })
        .catch (function (error) {
            vehicleState.loading = false;
            vehicleState.rows = [];
            els.vehicleTypesTable.innerHTML = '';
            setAreaState(els.vehicleTypesState, errorStateHtml('vehicles'));
            showToast('error', error && error.message ? error.message : 'Unable to load data');
        });
    }

    function clearVehicleErrors() {
        Object.keys(vehicleFieldMap).forEach(function (field) {
            markVehicleField(field, '');
        });
    }

    function markVehicleField(field, message) {
        var input = els[vehicleFieldMap[field]];
        var error = els[vehicleFieldMap[field] + 'Error'];

        if (!input || !error) {
            return;
        }

        if (message) {
            input.classList.add('is-invalid');
            error.textContent = message;
            error.hidden = false;
        } else {
            input.classList.remove('is-invalid');
            error.textContent = '';
            error.hidden = true;
        }
    }

    function openVehicleModal(row) {
        clearVehicleErrors();
        els.vehicleTypeId.value = row ? row.id : '';
        els.vehicleTypeModalLabel.textContent = row ? 'Edit Vehicle Type' : 'Add Vehicle Type';
        els.vehicleTypeName.value = row ? row.vehicle_name : '';
        els.vehicleTypeSeats.value = row ? row.seats : '';
        els.vehicleTypeStanding.value = row ? row.standing_capacity : '';
        els.saveVehicleTypeLabel.textContent = 'Save Vehicle';
        els.saveVehicleTypeBtn.disabled = false;
        vehicleModal.show();
        window.setTimeout(function () {
            els.vehicleTypeName.focus();
        }, 200);
    }

    function validateVehicleForm() {
        clearVehicleErrors();

        var id = Number(els.vehicleTypeId.value) || 0;
        var name = els.vehicleTypeName.value.trim();
        var seats = toWholeNumber(els.vehicleTypeSeats.value);
        var standing = toWholeNumber(els.vehicleTypeStanding.value);

        if (name === '') {
            return {
                field: 'vehicle_name',
                message: 'Vehicle name is required.'
            };
        }

        if (name.length > 100) {
            return {
                field: 'vehicle_name',
                message: 'Vehicle name must be 100 characters or fewer.'
            };
        }

        var duplicate = vehicleState.rows.some(function (row) {
            return Number(row.id) !== id
            && String(row.vehicle_name).trim().toLowerCase() === name.toLowerCase();
        });

        if (duplicate) {
            return {
                field: 'vehicle_name',
                message: 'A vehicle type with this name already exists.'
            };
        }

        if (seats === null || seats < 1 || seats > 999) {
            return {
                field: 'seats',
                message: 'Seats must be a whole number between 1 and 999.'
            };
        }

        if (standing === null || standing < 0 || standing > 999) {
            return {
                field: 'standing_capacity',
                message: 'Standing capacity must be a whole number between 0 and 999.'
            };
        }

        return null;
    }

    function saveVehicleType() {
        if (vehicleState.saving) {
            return;
        }

        var error = validateVehicleForm();

        if (error) {
            markVehicleField(error.field, error.message);
            var invalidInput = els[vehicleFieldMap[error.field]];
            if (invalidInput) {
                invalidInput.focus();
            }
            showToast('error', error.message);
            return;
        }

        var id = Number(els.vehicleTypeId.value) || 0;
        var payload = {
            vehicle_name: els.vehicleTypeName.value.trim(),
            seats: Number(els.vehicleTypeSeats.value),
            standing_capacity: Number(els.vehicleTypeStanding.value)
        };

        if (id > 0) {
            payload.id = id;
        }

        vehicleState.saving = true;
        els.saveVehicleTypeBtn.disabled = true;
        els.saveVehicleTypeLabel.textContent = 'Saving...';

        requestJson(
        API_BASE + '?action=' + (id > 0 ? 'updateVehicleType' : 'addVehicleType'),
        {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        }
        )
        .then(function () {
            vehicleState.saving = false;
            els.saveVehicleTypeBtn.disabled = false;
            els.saveVehicleTypeLabel.textContent = 'Save Vehicle';
            vehicleModal.hide();
            showToast('success', id > 0 ? 'Vehicle type updated' : 'Vehicle type added');
            loadVehicleTypes();
        })
        .catch (function (requestError) {
            vehicleState.saving = false;
            els.saveVehicleTypeBtn.disabled = false;
            els.saveVehicleTypeLabel.textContent = 'Save Vehicle';

            if (requestError && requestError.field) {
                markVehicleField(requestError.field, requestError.message);
            }

            showToast(
            'error',
            requestError && requestError.message
            ? requestError.message
            : 'Unable to save changes'
            );
        });
    }

    function confirmDeleteVehicle(id) {
        var row = findVehicle(id);

        if (!row) {
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Delete vehicle type?',
            text: 'Are you sure you want to remove "' + row.vehicle_name + '"? This cannot be undone.',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#b02a37',
            reverseButtons: true
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            requestJson(
            API_BASE + '?action=deleteVehicleType',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: Number(id) })
            }
            )
            .then(function () {
                showToast('success', 'Vehicle type deleted');
                loadVehicleTypes();
            })
            .catch (function (error) {
                showToast(
                'error',
                error && error.message ? error.message : 'Unable to delete vehicle type.'
                );
            });
        });
    }

    function handleVehicleTableClick(event) {
        var button = event.target instanceof Element
        ? event.target.closest('[data-action]')
        : null;

        if (!button || !els.vehicleTypesTable.contains(button)) {
            return;
        }

        var id = Number(button.dataset.id);

        if (button.dataset.action === 'edit') {
            var row = findVehicle(id);
            if (row) {
                openVehicleModal(row);
            }
        } else if (button.dataset.action === 'delete') {
            confirmDeleteVehicle(id);
        }
    }


    function findFuel(id) {
        for (var i = 0; i < fuelState.rows.length; i += 1) {
            if (Number(fuelState.rows[i].id) === Number(id)) {
                return fuelState.rows[i];
            }
        }
        return null;
    }

    function fuelSkeletonHtml() {
        var row = '<tr class="sc-skeleton-row">'
        + '<td><span class="sc-skeleton sc-skeleton-text"></span></td>'
        + '<td><span class="sc-skeleton sc-skeleton-price"></span></td>'
        + '<td><span class="sc-skeleton sc-skeleton-icon"></span></td>'
        + '</tr>';
        return row + row;
    }

    function fuelRowHtml(row) {
        var name = escapeHtml(row.fuel_type);
        var id = escapeHtml(row.id);

        return '<tr data-id="' + id + '">'
        + '<td><span class="sc-fuel-name">' + name + '</span></td>'
        + '<td class="sc-num">'
        + '<div class="sc-price-input">'
        + '<span class="sc-peso">\u20B1</span>'
        + '<input type="number" class="sc-input sc-fuel-price"'
        + ' data-id="' + id + '"'
        + ' value="' + escapeHtml(row.price_per_liter) + '"'
        + ' min="0" step="0.01" inputmode="decimal"'
        + ' aria-label="' + name + ' price per liter">'
        + '</div>'
        + '</td>'
        + '<td class="sc-actions-col">'
        + '<button class="sc-icon-btn is-danger" type="button"'
        + ' data-action="delete" data-id="' + id + '"'
        + ' aria-label="Delete ' + name + '">'
        + '<i class="bi bi-trash"></i>'
        + '</button>'
        + '</td>'
        + '</tr>';
    }

    function loadFuelPrices() {
        fuelState.loading = true;
        els.fuelPricesTable.innerHTML = fuelSkeletonHtml();
        setAreaState(els.fuelPricesState, '');
        refreshFuelState();

        requestJson(API_BASE + '?action=getFuelPrices')
        .then(function (result) {
            fuelState.loading = false;
            fuelState.rows = (result.data || []).map(function (fuel) {
                var normalized = formatPriceInput(fuel.price_per_liter);
                return {
                    id: fuel.id,
                    fuel_type: String(fuel.fuel_type === null || fuel.fuel_type === undefined
                    ? ''
                    : fuel.fuel_type),
                    price_per_liter: normalized,
                    original: normalized
                };
            });

            els.fuelPricesTable.innerHTML = fuelState.rows
            .map(fuelRowHtml)
            .join('');

            setAreaState(
            els.fuelPricesState,
            fuelState.rows.length === 0
            ? emptyStateHtml('No fuel prices configured yet.')
            : ''
            );

            refreshFuelState();
        })
        .catch (function (error) {
            fuelState.loading = false;
            fuelState.rows = [];
            els.fuelPricesTable.innerHTML = '';
            setAreaState(els.fuelPricesState, errorStateHtml('fuel'));
            refreshFuelState();
            showToast('error', error && error.message ? error.message : 'Unable to load data');
        });
    }

    function fuelIsDirty() {
        return fuelState.rows.some(function (row) {
            var number = toNumberOrNull(row.price_per_liter);
            if (number === null || number < 0) {
                return true;
            }
            return number.toFixed(2) !== row.original;
        });
    }

    function refreshFuelState() {
        var dirty = fuelIsDirty();
        var className = 'sc-status is-clean';
        var label = 'No changes';

        if (fuelState.saving) {
            className = 'sc-status is-saving';
            label = 'Saving...';
        } else if (fuelState.loading) {
            label = 'Loading...';
        } else if (dirty) {
            className = 'sc-status is-dirty';
            label = 'Unsaved changes';
        }

        els.fuelSaveStatus.className = className;
        els.fuelSaveStatus.textContent = label;
        els.saveFuelPricesBtn.disabled = !dirty || fuelState.saving || fuelState.loading;
    }

    function handleFuelInput(event) {
        var input = event.target instanceof Element
        ? event.target.closest('.sc-fuel-price')
        : null;

        if (!input) {
            return;
        }

        var row = findFuel(input.dataset.id);
        if (!row) {
            return;
        }

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

        row.price_per_liter = cleaned;
        input.classList.remove('is-invalid');
        refreshFuelState();
    }

    function handleFuelFocusOut(event) {
        var input = event.target instanceof Element
        ? event.target.closest('.sc-fuel-price')
        : null;

        if (!input) {
            return;
        }

        var row = findFuel(input.dataset.id);
        if (!row) {
            return;
        }

        var number = toNumberOrNull(row.price_per_liter);
        if (number !== null && number >= 0) {
            row.price_per_liter = number.toFixed(2);
            input.value = row.price_per_liter;
        }

        refreshFuelState();
    }

    function saveFuelPrices() {
        if (fuelState.saving || fuelState.loading) {
            return;
        }

        var firstInvalid = null;

        fuelState.rows.forEach(function (row) {
            var input = els.fuelPricesTable.querySelector(
            '.sc-fuel-price[data-id="' + row.id + '"]'
            );
            var number = toNumberOrNull(row.price_per_liter);

            if (number === null || number < 0) {
                if (input) {
                    input.classList.add('is-invalid');
                }
                if (!firstInvalid) {
                    firstInvalid = input;
                }
            } else if (input) {
                input.classList.remove('is-invalid');
            }
        });

        if (firstInvalid) {
            firstInvalid.focus();
            showToast('error', 'Please correct the highlighted fields.');
            return;
        }

        var payload = {
            prices: fuelState.rows.map(function (row) {
                return {
                    id: row.id,
                    fuel_type: row.fuel_type,
                    price_per_liter: Number(Number(row.price_per_liter).toFixed(2))
                };
            })
        };

        fuelState.saving = true;
        refreshFuelState();

        requestJson(
        API_BASE + '?action=saveFuelPrices',
        {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        }
        )
        .then(function () {
            fuelState.saving = false;

            fuelState.rows.forEach(function (row) {
                var number = toNumberOrNull(row.price_per_liter);
                if (number !== null && number >= 0) {
                    row.original = number.toFixed(2);
                    row.price_per_liter = row.original;
                }
            });

            Array.prototype.forEach.call(
            els.fuelPricesTable.querySelectorAll('.sc-fuel-price'),
            function (input) {
                var row = findFuel(input.dataset.id);
                if (row) {
                    input.value = row.original;
                    input.classList.remove('is-invalid');
                }
            }
            );

            refreshFuelState();
            showToast('success', 'Fuel prices saved');
        })
        .catch (function (error) {
            fuelState.saving = false;
            refreshFuelState();
            showToast(
            'error',
            error && error.message
            ? error.message
            : 'Unable to update fuel prices. Please try again.'
            );
        });
    }

    function clearFuelErrors() {
        Object.keys(fuelFieldMap).forEach(function (field) {
            markFuelField(field, '');
        });
    }

    function markFuelField(field, message) {
        var input = els[fuelFieldMap[field]];
        var error = els[fuelFieldMap[field] + 'Error'];

        if (!input || !error) {
            return;
        }

        if (message) {
            input.classList.add('is-invalid');
            error.textContent = message;
            error.hidden = false;
        } else {
            input.classList.remove('is-invalid');
            error.textContent = '';
            error.hidden = true;
        }
    }

    function withFuelEditsGuard(action) {
        if (!fuelIsDirty()) {
            action();
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Unsaved price changes',
            text: 'This will reload the list and discard your unsaved price changes. Continue?',
            showCancelButton: true,
            confirmButtonText: 'Discard and continue',
            cancelButtonText: 'Keep editing',
            confirmButtonColor: '#b02a37',
            reverseButtons: true
        }).then(function (result) {
            if (result.isConfirmed) {
                action();
            }
        });
    }

    function openFuelModal() {
        clearFuelErrors();
        els.fuelTypeName.value = '';
        els.fuelTypePrice.value = '';
        els.saveFuelTypeLabel.textContent = 'Save Fuel Type';
        els.saveFuelTypeBtn.disabled = false;
        fuelModal.show();
        window.setTimeout(function () {
            els.fuelTypeName.focus();
        }, 200);
    }

    function validateFuelForm() {
        clearFuelErrors();

        var name = els.fuelTypeName.value.trim();
        var price = toNumberOrNull(els.fuelTypePrice.value);

        if (name === '') {
            return {
                field: 'fuel_type',
                message: 'Fuel type name is required.'
            };
        }

        if (name.length > 50) {
            return {
                field: 'fuel_type',
                message: 'Fuel type name must be 50 characters or fewer.'
            };
        }

        var duplicate = fuelState.rows.some(function (row) {
            return String(row.fuel_type).trim().toLowerCase() === name.toLowerCase();
        });

        if (duplicate) {
            return {
                field: 'fuel_type',
                message: 'A fuel type with this name already exists.'
            };
        }

        if (price === null || price < 0) {
            return {
                field: 'price_per_liter',
                message: 'Price per liter must be a non-negative number.'
            };
        }

        return null;
    }

    function saveFuelType() {
        if (fuelState.saving) {
            return;
        }

        var error = validateFuelForm();

        if (error) {
            markFuelField(error.field, error.message);
            var invalidInput = els[fuelFieldMap[error.field]];
            if (invalidInput) {
                invalidInput.focus();
            }
            showToast('error', error.message);
            return;
        }

        fuelState.saving = true;
        els.saveFuelTypeBtn.disabled = true;
        els.saveFuelTypeLabel.textContent = 'Saving...';

        requestJson(
        API_BASE + '?action=addFuelPrice',
        {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                fuel_type: els.fuelTypeName.value.trim(),
                price_per_liter: Number(Number(els.fuelTypePrice.value).toFixed(2))
            })
        }
        )
        .then(function () {
            fuelState.saving = false;
            els.saveFuelTypeBtn.disabled = false;
            els.saveFuelTypeLabel.textContent = 'Save Fuel Type';
            fuelModal.hide();
            showToast('success', 'Fuel type added');
            loadFuelPrices();
        })
        .catch (function (requestError) {
            fuelState.saving = false;
            els.saveFuelTypeBtn.disabled = false;
            els.saveFuelTypeLabel.textContent = 'Save Fuel Type';

            if (requestError && requestError.field) {
                markFuelField(requestError.field, requestError.message);
            }

            showToast(
            'error',
            requestError && requestError.message
            ? requestError.message
            : 'Unable to save fuel type'
            );
        });
    }

    function confirmDeleteFuel(id) {
        var row = findFuel(id);

        if (!row) {
            return;
        }

        var discardNote = fuelIsDirty()
        ? ' Your unsaved price changes will be discarded.'
        : '';

        Swal.fire({
            icon: 'warning',
            title: 'Delete fuel type?',
            text: 'Are you sure you want to remove "' + row.fuel_type + '"?' + discardNote + ' This cannot be undone.',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#b02a37',
            reverseButtons: true
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            requestJson(
            API_BASE + '?action=deleteFuelPrice',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: Number(id) })
            }
            )
            .then(function () {
                showToast('success', 'Fuel type deleted');
                loadFuelPrices();
            })
            .catch (function (error) {
                showToast(
                'error',
                error && error.message ? error.message : 'Unable to delete fuel type.'
                );
            });
        });
    }

    function handleFuelTableClick(event) {
        var button = event.target instanceof Element
        ? event.target.closest('[data-action]')
        : null;

        if (!button || !els.fuelPricesTable.contains(button)) {
            return;
        }

        if (button.dataset.action === 'delete') {
            confirmDeleteFuel(Number(button.dataset.id));
        }
    }


    function cacheElements() {
        els.section = document.getElementById('systemConfigurationSection');
        els.vehicleTypesTable = document.getElementById('vehicleTypesTable');
        els.vehicleTypesState = document.getElementById('vehicleTypesState');
        els.addVehicleTypeBtn = document.getElementById('addVehicleTypeBtn');
        els.vehicleTypeModal = document.getElementById('vehicleTypeModal');
        els.vehicleTypeModalLabel = document.getElementById('vehicleTypeModalLabel');
        els.vehicleTypeId = document.getElementById('vehicleTypeId');
        els.vehicleTypeName = document.getElementById('vehicleTypeName');
        els.vehicleTypeNameError = document.getElementById('vehicleTypeNameError');
        els.vehicleTypeSeats = document.getElementById('vehicleTypeSeats');
        els.vehicleTypeSeatsError = document.getElementById('vehicleTypeSeatsError');
        els.vehicleTypeStanding = document.getElementById('vehicleTypeStanding');
        els.vehicleTypeStandingError = document.getElementById('vehicleTypeStandingError');
        els.saveVehicleTypeBtn = document.getElementById('saveVehicleTypeBtn');
        els.saveVehicleTypeLabel = document.getElementById('saveVehicleTypeLabel');
        els.fuelPricesTable = document.getElementById('fuelPricesTable');
        els.fuelPricesState = document.getElementById('fuelPricesState');
        els.fuelSaveStatus = document.getElementById('fuelSaveStatus');
        els.saveFuelPricesBtn = document.getElementById('saveFuelPricesBtn');
        els.addFuelTypeBtn = document.getElementById('addFuelTypeBtn');
        els.fuelTypeModal = document.getElementById('fuelTypeModal');
        els.fuelTypeName = document.getElementById('fuelTypeName');
        els.fuelTypeNameError = document.getElementById('fuelTypeNameError');
        els.fuelTypePrice = document.getElementById('fuelTypePrice');
        els.fuelTypePriceError = document.getElementById('fuelTypePriceError');
        els.saveFuelTypeBtn = document.getElementById('saveFuelTypeBtn');
        els.saveFuelTypeLabel = document.getElementById('saveFuelTypeLabel');
    }

    function handleStateClick(event) {
        var button = event.target instanceof Element
        ? event.target.closest('[data-retry]')
        : null;

        if (!button) {
            return;
        }

        if (button.dataset.retry === 'vehicles') {
            loadVehicleTypes();
        } else if (button.dataset.retry === 'fuel') {
            loadFuelPrices();
        }
    }

    function bindEvents() {
        els.addVehicleTypeBtn.addEventListener('click', function () {
            openVehicleModal(null);
        });

        els.saveVehicleTypeBtn.addEventListener('click', saveVehicleType);
        els.vehicleTypesTable.addEventListener('click', handleVehicleTableClick);
        els.section.addEventListener('click', handleStateClick);

        els.vehicleTypeModal.addEventListener('hidden.bs.modal', clearVehicleErrors);

        els.fuelPricesTable.addEventListener('input', handleFuelInput);
        els.fuelPricesTable.addEventListener('focusout', handleFuelFocusOut);
        els.fuelPricesTable.addEventListener('click', handleFuelTableClick);
        els.saveFuelPricesBtn.addEventListener('click', saveFuelPrices);

        els.addFuelTypeBtn.addEventListener('click', function () {
            withFuelEditsGuard(openFuelModal);
        });

        els.saveFuelTypeBtn.addEventListener('click', saveFuelType);
        els.fuelTypeModal.addEventListener('hidden.bs.modal', clearFuelErrors);
    }

    function init() {
        cacheElements();

        if (!els.section) {
            return;
        }

        vehicleModal = new bootstrap.Modal(els.vehicleTypeModal);
        fuelModal = new bootstrap.Modal(els.fuelTypeModal);
        bindEvents();
        loadVehicleTypes();
        loadFuelPrices();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
