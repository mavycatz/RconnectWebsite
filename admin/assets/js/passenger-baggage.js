(function () {
    'use strict';

    var BAG = {
        page: 1,
        perPage: 15,
        pages: 1,
        total: 0,
        items: [],
        mode: 'add',
        editing: null,
        categoriesLoaded: false
    };

    function el(id) { return document.getElementById(id); }

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function fmtDateTime(v) {
        var s = String(v == null ? '' : v).replace('T', ' ').trim();
        if (!s) return '—';
        var p = s.split(' ');
        return p[0] + (p[1] ? ' ' + p[1].slice(0, 5) : '');
    }

    function money(v) {
        var n = Number(v || 0);
        if (isNaN(n)) return '₱0.00';
        return '₱' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function payBadge(status) {
        var s = String(status || '').toLowerCase();
        var cls = s === 'paid' ? 'paid' : s === 'pending' ? 'pending' : s === 'rejected' ? 'rejected' : 'other';
        var label = status && String(status).trim() !== '' ? String(status) : 'Not recorded';
        var tone = cls === 'paid' ? 'is-active' : cls === 'pending' ? 'is-pending' : cls === 'rejected' ? 'is-rejected' : 'is-other';
        return '<span class="bag-badge rmg-status ' + tone + ' ' + cls + '">' + esc(label) + '</span>';
    }

    function showNote(message, isError) {
        var note = el('bagNote');
        if (!message) { note.style.display = 'none'; return; }
        note.textContent = message;
        note.style.display = 'block';
        note.classList.toggle('error', !!isError);
    }

    function queryParams() {
        var p = new URLSearchParams();
        p.set('action', 'list');
        p.set('page', String(BAG.page));
        p.set('per_page', String(BAG.perPage));
        var map = {
            search: 'bagSearch',
            date_from: 'bagDateFrom',
            date_to: 'bagDateTo',
            trip: 'bagTrip',
            category: 'bagCategory',
            payment_status: 'bagPayment'
        };
        Object.keys(map).forEach(function (key) {
            var node = el(map[key]);
            if (node && String(node.value).trim() !== '') p.set(key, node.value);
        });
        return p;
    }

    function renderPagination() {
        var host = el('bagPag');
        var start = BAG.total === 0 ? 0 : (BAG.page - 1) * BAG.perPage + 1;
        var end = Math.min(BAG.page * BAG.perPage, BAG.total);
        host.innerHTML =
            '<span class="rmg-pg-info">Matching records: ' + BAG.total + ' &middot; showing ' + start + '–' + end + '</span>' +
            '<span class="rmg-pager">' +
            '<button type="button" class="rmg-pg-btn" aria-label="Previous page" data-page="' + (BAG.page - 1) + '"' + (BAG.page <= 1 ? ' disabled' : '') + '>&larr;</button> ' +
            '<span style="margin:0 8px;">Page ' + BAG.page + ' of ' + BAG.pages + '</span>' +
            '<button type="button" class="rmg-pg-btn" aria-label="Next page" data-page="' + (BAG.page + 1) + '"' + (BAG.page >= BAG.pages ? ' disabled' : '') + '>&rarr;</button>' +
            '</span>';
    }

    function renderRows() {
        var body = el('bagBody');
        if (!BAG.items.length) {
            body.innerHTML = '<tr><td colspan="11"><div class="bag-state"><i class="bi bi-inbox"></i>No baggage records match the filters.</div></td></tr>';
            return;
        }

        body.innerHTML = BAG.items.map(function (item, index) {
            var passenger = item.passenger_code && String(item.passenger_code).trim() !== ''
                ? item.passenger_code
                : (item.source === 'live' ? 'Record #' + item.passenger_id : 'History record');
            var baseLine = item.base_fare !== null && item.base_fare !== undefined
                ? money(item.base_fare) + ' × ' + item.spaces + ' = '
                : '';
            var actions = '<button type="button" class="rmg-icon-btn is-view" title="View baggage" aria-label="View baggage" data-action="view" data-index="' + index + '"><i class="bi bi-eye"></i></button>';
            if (item.source === 'live') {
                actions += ' <button type="button" class="rmg-icon-btn is-edit" title="Edit baggage" aria-label="Edit baggage" data-action="edit" data-index="' + index + '"><i class="bi bi-pencil"></i></button>';
            }
            return '<tr>' +
                '<td data-label="Baggage ID"><strong>' + esc(item.baggage_id) + '</strong></td>' +
                '<td data-label="Item">' + esc(item.description) + (item.archived ? ' <span class="rmg-status is-other">Archived</span>' : '') + '</td>' +
                '<td data-label="Category">' + (item.category ? esc(item.category) : '<span style="color:#8a9a8a;">Not categorized</span>') + '</td>' +
                '<td data-label="Passenger / Record">' + esc(passenger) + '</td>' +
                '<td data-label="Trip / Bus / Driver">' + esc(item.trip_no || '—') + ' · ' + esc(item.bus || '—') + ' · ' + esc(item.driver || '—') + '</td>' +
                '<td data-label="Route">' + esc((item.boarding_point || '—') + ' → ' + (item.destination || '—')) + '</td>' +
                '<td data-label="Spaces">' + esc(item.spaces) + '</td>' +
                '<td data-label="Fare">' + baseLine + '<strong>' + money(item.fare) + '</strong></td>' +
                '<td data-label="Payment">' + payBadge(item.payment_status) + '</td>' +
                '<td data-label="Created">' + esc(fmtDateTime(item.created_at)) + '</td>' +
                '<td data-label="Actions" class="rmg-td-actions"><div class="rmg-actions">' + actions + '</div></td>' +
                '</tr>';
        }).join('');
    }

    function load() {
        showNote('');
        el('bagBody').innerHTML = '<tr><td colspan="11"><div class="bag-state"><i class="bi bi-hourglass-split"></i>Loading baggage records...</div></td></tr>';
        fetch('/admin/api/Passenger/BaggageApi.php?' + queryParams().toString(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) throw new Error(res.message || 'Could not load baggage records.');
                BAG.items = res.data || [];
                BAG.total = Number(res.total || 0);
                BAG.pages = Math.max(1, Number(res.pages || 1));
                BAG.page = Math.min(Math.max(1, Number(res.page || 1)), BAG.pages);
                BAG.perPage = Number(res.per_page || BAG.perPage) || 15;

                el('bagCount').innerHTML = '<i class="bi bi-box-seam"></i> ' + BAG.total + ' record' + (BAG.total === 1 ? '' : 's');

                if (!BAG.categoriesLoaded) {
                    var categories = res.categories || [];
                    var select = el('bagCategory');
                    var formSelect = el('bagFCategory');
                    formSelect.innerHTML = '<option value="">Not categorized</option>' + categories.map(function (c) {
                        return '<option value="' + esc(c) + '">' + esc(c) + '</option>';
                    }).join('');
                    select.innerHTML = '<option value="">All categories</option><option value="Uncategorized">Not categorized</option>' + categories.map(function (c) {
                        return '<option value="' + esc(c) + '">' + esc(c) + '</option>';
                    }).join('');
                    BAG.categoriesLoaded = true;
                }

                var errors = res.errors || {};
                var messages = Object.keys(errors).map(function (k) { return errors[k]; });
                if (messages.length) {
                    showNote(messages.join(' '), true);
                }

                renderRows();
                renderPagination();
            })
            .catch(function (err) {
                el('bagBody').innerHTML = '<tr><td colspan="11"><div class="bag-state"><i class="bi bi-exclamation-circle" style="color:#b94a48;"></i>' + esc(err.message || 'Could not load baggage records.') + '</div></td></tr>';
                renderPagination();
            });
    }

    function openDetail(item) {
        el('bagDetailTitle').textContent = item.baggage_id + ' · ' + item.description;
        el('bagDetailSub').textContent = (item.source === 'history' ? 'Archived (trip history)' : 'Live record');
        var rows = [
            ['Description', item.description],
            ['Category', item.category || 'Not categorized'],
            ['Quantity (spaces)', String(item.spaces)],
            ['Multiplier', '× ' + item.multiplier],
            ['Base fare', item.base_fare !== null && item.base_fare !== undefined ? money(item.base_fare) : 'Derived only when divisible'],
            ['Calculated fare', money(item.fare)],
            ['Passenger code', item.passenger_code || 'Not assigned'],
            ['Passenger record', item.passenger_id || '—'],
            ['Trip no.', item.trip_no || '—'],
            ['Bus', item.bus || '—'],
            ['Driver', item.driver || '—'],
            ['Boarding', item.boarding_point || '—'],
            ['Destination', item.destination || '—'],
            ['Payment method', item.payment_method || '—'],
            ['Payment status', item.payment_status || '—'],
            ['Remittance', item.remittance_status || '—'],
            ['Created', fmtDateTime(item.created_at)]
        ];
        el('bagDetailBody').innerHTML = '<div class="bag-detail-grid">' + rows.map(function (row) {
            return '<div><span class="k">' + esc(row[0]) + '</span><br><span class="v">' + esc(row[1]) + '</span></div>';
        }).join('') + '</div>';
        bootstrap.Modal.getOrCreateInstance(el('bagDetailModal')).show();
    }

    function openForm(mode, item) {
        BAG.mode = mode;
        BAG.editing = item || null;
        el('bagFormTitle').textContent = mode === 'edit' ? 'Edit baggage · ' + item.baggage_id : 'Add baggage';
        el('bagFormError').style.display = 'none';

        el('bagFDescription').value = item ? item.description : '';
        el('bagFCategory').value = item && item.category ? item.category : '';
        el('bagFSpaces').value = item ? item.spaces : 1;
        el('bagFRoute').value = '';
        el('bagFBoarding').value = item ? item.boarding_point : '';
        el('bagFDestination').value = item ? item.destination : '';
        el('bagFTrip').value = item ? item.trip_no : '';
        el('bagFBus').value = item ? item.bus : '';
        el('bagFDriver').value = item ? item.passenger_id : '';
        el('bagFDriver').disabled = mode === 'edit';

        bootstrap.Modal.getOrCreateInstance(el('bagFormModal')).show();
    }

    function saveForm() {
        var description = el('bagFDescription').value.trim();
        var spaces = parseInt(el('bagFSpaces').value, 10) || 0;
        var payload = {
            description: description,
            category: el('bagFCategory').value,
            spaces: spaces,
            route_name: el('bagFRoute').value.trim(),
            boarding_point: el('bagFBoarding').value.trim(),
            destination: el('bagFDestination').value.trim(),
            trip_no: el('bagFTrip').value.trim(),
            bus_no: el('bagFBus').value.trim(),
            driver_id: el('bagFDriver').value.trim()
        };

        var error = '';
        if (!description) error = 'Item description is required.';
        else if (spaces < 1 || spaces > 50) error = 'Quantity must be between 1 and 50.';
        else if (!payload.route_name || !payload.boarding_point || !payload.destination) error = 'Route, boarding point, and destination are required.';
        if (error) {
            el('bagFormError').textContent = error;
            el('bagFormError').style.display = 'block';
            return;
        }

        var action = BAG.mode === 'edit' ? 'edit' : 'add';
        if (BAG.mode === 'edit') {
            payload.ref = BAG.editing.ref;
            payload.source = BAG.editing.source;
        }

        var doSave = function () {
            var url = '/admin/api/Passenger/BaggageApi.php?action=' + action;
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (!res.success) throw new Error(res.message || 'Could not save the baggage record.');
                bootstrap.Modal.getOrCreateInstance(el('bagFormModal')).hide();
                load();
                if (window.showAlert) {
                    window.showAlert('success', action === 'edit' ? 'Baggage updated' : 'Baggage added', description + ' was saved with the computed fare.');
                }
            }).catch(function (err) {
                el('bagFormError').textContent = err.message || 'Could not save the baggage record.';
                el('bagFormError').style.display = 'block';
            });
        };

        if (window.showConfirm) {
            window.showConfirm(
                action === 'edit' ? 'Save baggage changes?' : 'Add this baggage record?',
                description + ' · ' + spaces + ' space(s) · fare computed from the route rule.',
                doSave
            );
        } else {
            doSave();
        }
    }

    function bind() {
        el('bagBody').addEventListener('click', function (event) {
            var btn = event.target.closest('[data-action]');
            if (!btn) return;
            var item = BAG.items[Number(btn.getAttribute('data-index'))];
            if (!item) return;
            if (btn.getAttribute('data-action') === 'view') openDetail(item);
            if (btn.getAttribute('data-action') === 'edit') openForm('edit', item);
        });

        el('bagPag').addEventListener('click', function (event) {
            var btn = event.target.closest('[data-page]');
            if (!btn || btn.disabled) return;
            BAG.page = parseInt(btn.getAttribute('data-page'), 10) || 1;
            load();
        });

        var debounce = null;
        el('bagSearch').addEventListener('input', function () {
            clearTimeout(debounce);
            debounce = setTimeout(function () { BAG.page = 1; load(); }, 300);
        });

        ['bagDateFrom', 'bagDateTo', 'bagTrip', 'bagCategory', 'bagPayment'].forEach(function (id) {
            el(id).addEventListener('change', function () { BAG.page = 1; load(); });
        });

        el('bagPerPage').addEventListener('change', function () {
            BAG.perPage = parseInt(el('bagPerPage').value, 10) || 15;
            BAG.page = 1;
            load();
        });

        el('bagClear').addEventListener('click', function () {
            ['bagSearch', 'bagDateFrom', 'bagDateTo', 'bagTrip', 'bagCategory', 'bagPayment'].forEach(function (id) { el(id).value = ''; });
            BAG.page = 1;
            load();
        });

        el('bagAddBtn').addEventListener('click', function () { openForm('add', null); });
        el('bagFSave').addEventListener('click', saveForm);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { bind(); load(); });
    } else {
        bind();
        load();
    }
})();
