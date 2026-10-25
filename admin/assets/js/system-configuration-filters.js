(function () {
    'use strict';

    var CONFIGS = [
        { input: 'fareDestinationFilter', table: 'farePricingTable', colspan: 8 },
        { input: 'vehicleTypeFilter', table: 'vehicleTypesTable', colspan: 4 },
        { input: 'fuelTypeFilter', table: 'fuelPricesTable', colspan: 3 }
    ];

    function applyFilter(config) {
        var input = document.getElementById(config.input);
        var tbody = document.getElementById(config.table);
        if (!input || !tbody) {
            return;
        }

        var term = input.value.trim().toLowerCase();
        var visible = 0;

        Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (row) {
            if (row.classList.contains('sc-filter-empty')) {
                return;
            }
            var show = term === '' || row.textContent.toLowerCase().indexOf(term) !== -1;
            row.style.display = show ? '' : 'none';
            if (show) {
                visible++;
            }
        });

        var emptyRow = tbody.querySelector('tr.sc-filter-empty');
        if (visible === 0 && term !== '') {
            if (!emptyRow) {
                emptyRow = document.createElement('tr');
                emptyRow.className = 'sc-filter-empty';
                var cell = document.createElement('td');
                cell.colSpan = config.colspan;
                cell.style.textAlign = 'center';
                cell.style.color = '#8a9a8a';
                cell.style.padding = '12px';
                cell.textContent = 'No matching records.';
                emptyRow.appendChild(cell);
                tbody.appendChild(emptyRow);
            }
            emptyRow.style.display = '';
        } else if (emptyRow) {
            emptyRow.style.display = 'none';
        }
    }

    function bind(config) {
        var input = document.getElementById(config.input);
        if (!input) {
            return;
        }
        input.addEventListener('input', function () { applyFilter(config); });

        var table = document.getElementById(config.table);
        if (table && window.MutationObserver) {
            new MutationObserver(function () { applyFilter(config); }).observe(table, { childList: true });
        }
    }

    function init() {
        CONFIGS.forEach(bind);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
