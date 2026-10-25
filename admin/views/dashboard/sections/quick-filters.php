<div id="dashFilters" class="dash-filters">
    <button type="button" class="dash-filter-fab" id="toggleFilter" aria-expanded="false" aria-controls="dashAdvancedPanel" title="Advanced filters" aria-label="Toggle advanced filters">
        <i class="bi bi-funnel"></i>
    </button>

    <div class="dash-adv" id="dashAdvancedPanel" hidden>
        <div class="dash-adv-grid">
            <label class="dash-adv-field">
                <span>From date</span>
                <input type="date" id="dashFromDate">
            </label>

            <label class="dash-adv-field">
                <span>To date</span>
                <input type="date" id="dashToDate">
            </label>

            <label class="dash-adv-field">
                <span>Route</span>
                <select id="dashRoute">
                    <option value="">All routes</option>
                </select>
            </label>

            <label class="dash-adv-field">
                <span>Bus</span>
                <select id="dashBus">
                    <option value="">All buses</option>
                </select>
            </label>
        </div>

        <div class="dash-adv-actions">
            <button type="button" class="dash-btn dash-btn-primary" id="dashApplyAdv">Apply</button>
            <button type="button" class="dash-btn dash-btn-ghost" id="dashClearAdv">Clear</button>
        </div>
    </div>
</div>

<style>
    .dash-filters-label {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #2e4836;
    }

    .dash-seg {
        display: inline-flex;
        background: #f9f4da;
        border: 1px solid #cdd9b5;
        border-radius: 999px;
        padding: 3px;
        gap: 2px;
    }

    .dash-seg-btn {
        border: none;
        background: transparent;
        border-radius: 999px;
        padding: 5px 16px;
        font-size: 0.76rem;
        font-weight: 600;
        color: #295738;
        cursor: pointer;
        transition: background 0.15s ease, color 0.15s ease;
    }

    .dash-seg-btn:hover {
        background: #efe9c9;
    }

    .dash-seg-btn.active {
        background: #295738;
        color: #fef8f3;
    }

    .dash-filter-fab {
        background: #295738;
        color: #fef8f3;
        border: none;
        border-radius: 50%;
        font-size: 1.3rem;
        cursor: pointer;
        box-shadow: 0 10px 24px rgba(46, 72, 54, 0.32);
        transition: background 0.15s ease, transform 0.15s ease;
    }

    .dash-filter-fab:hover,
    .dash-filter-fab.is-open {
        background: #2e4836;
    }

    .dash-filter-fab:focus-visible {
        outline: 3px solid rgba(196, 180, 23, 0.55);
        outline-offset: 2px;
    }

    .dash-adv {
        position: fixed;
        right: 30px;
        bottom: 102px;
        z-index: 9998;
        width: min(440px, calc(100vw - 32px));
        background: #fdfbf6;
        border: 1px solid #cdd9b5;
        border-radius: 16px;
        padding: 16px;
        box-shadow: 0 18px 44px rgba(46, 72, 54, 0.26);
    }

    .dash-adv-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px 14px;
    }

    .dash-adv-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 0.7rem;
        font-weight: 600;
        color: #2e4836;
    }

    .dash-adv-field input,
    .dash-adv-field select {
        width: 100%;
        border: 1px solid #cdd9b5;
        border-radius: 10px;
        padding: 6px 10px;
        font-size: 0.76rem;
        color: #2e4836;
        background: #ffffff;
    }

    .dash-adv-field input:focus,
    .dash-adv-field select:focus {
        outline: none;
        border-color: #295738;
        box-shadow: 0 0 0 2px rgba(41, 87, 56, 0.12);
    }

    .dash-adv-actions {
        display: flex;
        gap: 8px;
        margin-top: 12px;
    }

    .dash-btn {
        border-radius: 10px;
        padding: 6px 18px;
        font-size: 0.76rem;
        font-weight: 600;
        cursor: pointer;
        border: 1px solid transparent;
    }

    .dash-btn-primary {
        background: #295738;
        color: #fef8f3;
    }

    .dash-btn-primary:hover {
        background: #2e4836;
    }

    .dash-btn-ghost {
        background: transparent;
        border-color: #295738;
        color: #295738;
    }

    .dash-btn-ghost:hover {
        background: #f9f4da;
    }

    @media(max-width:991px) {
        .dash-adv-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media(max-width:575px) {
        .dash-adv-grid {
            grid-template-columns: 1fr;
        }

        .dash-seg-btn {
            padding: 5px 10px;
        }
    }
</style>

<script>
    (function () {
        "use strict";

        var STORAGE_KEY = "adminFilterState";
        var DEFAULT_RANGE = "today";

        var quickButtons = document.querySelectorAll("#dashQuickFilter .dash-seg-btn");
        var toggleBtn = document.getElementById("toggleFilter");
        var panel = document.getElementById("dashAdvancedPanel");
        var fromInput = document.getElementById("dashFromDate");
        var toInput = document.getElementById("dashToDate");
        var routeSelect = document.getElementById("dashRoute");
        var busSelect = document.getElementById("dashBus");
        var applyBtn = document.getElementById("dashApplyAdv");
        var clearBtn = document.getElementById("dashClearAdv");

        function pad(value) {
            return String(value).padStart(2, "0");
        }

        function formatDate(date) {
            return date.getFullYear() + "-" + pad(date.getMonth() + 1) + "-" + pad(date.getDate());
        }

        function periodParams(range) {
            var now = new Date();

            if (range === "today") {
                var today = formatDate(now);
                return {
                    range: "today",
                    fromDate: today,
                    toDate: today,
                    day: String(now.getDate()),
                    month: String(now.getMonth() + 1),
                    year: String(now.getFullYear())
                };
            }

            if (range === "week") {
                var start = new Date(now.getTime());
                start.setDate(start.getDate() - 6);
                return {
                    range: "week",
                    fromDate: formatDate(start),
                    toDate: formatDate(now)
                };
            }

            if (range === "year") {
                return {
                    range: "year",
                    year: String(now.getFullYear())
                };
            }

            return {
                range: "month",
                month: String(now.getMonth() + 1),
                year: String(now.getFullYear())
            };
        }

        function activeRange() {
            var active = document.querySelector("#dashQuickFilter .dash-seg-btn.active");
            return active ? active.dataset.range : DEFAULT_RANGE;
        }

        function setActiveRange(range) {
            quickButtons.forEach(function (btn) {
                btn.classList.toggle("active", btn.dataset.range === range);
            });
        }

        function customDates() {
            var from = fromInput && fromInput.value ? fromInput.value : "";
            var to = toInput && toInput.value ? toInput.value : "";

            if (from === "" && to === "") {
                return null;
            }

            return { fromDate: from || to, toDate: to || from };
        }

        function buildQuery() {
            var params = new URLSearchParams();
            var custom = customDates();
            var selectedRange = activeRange();

            if (custom) {
                params.set("range", "custom");
                params.set("fromDate", custom.fromDate);
                params.set("toDate", custom.toDate);
            } else {
                var period = periodParams(selectedRange);

                Object.keys(period).forEach(function (key) {
                    params.set(key, period[key]);
                });
            }

            if (routeSelect && routeSelect.value) {
                params.set("route", routeSelect.value);
            }

            if (busSelect && busSelect.value) {
                params.set("bus", busSelect.value);
            }

            return params.toString();
        }

        function persist(query) {
            try {
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
                    range: activeRange(),
                    query: query,
                    fields: {
                        fromDate: fromInput ? fromInput.value : "",
                        toDate: toInput ? toInput.value : "",
                        route: routeSelect ? routeSelect.value : "",
                        bus: busSelect ? busSelect.value : ""
                    }
                }));
            } catch (e) {
            }
        }

        function refreshWidgets(query) {
            [
            "loadDashboard",
            "loadTripActivityTrend",
            "loadOperationalSummary",
            "loadFuelEfficiency",
            "loadNeedsAttention",
            "loadRecentActivity",
            "loadDriverStats",
            "loadDriverInsights",
            "loadDriverPerformance"
            ].forEach(function (name) {
                var fn = window[name];

                if (typeof fn !== "function") {
                    return;
                }

                try {
                    fn(query);
                } catch (err) {
                    console.error("Filter refresh failed for " + name + ":", err);
                }
            });
        }

        function applyFilters() {
            var query = buildQuery();

            window.adminFilterQuery = query;
            persist(query);
            refreshWidgets(query);
        }

        function applyRange(range) {
            setActiveRange(range);

            if (fromInput) {
                fromInput.value = "";
            }
            if (toInput) {
                toInput.value = "";
            }

            applyFilters();
        }

        function restoreState() {
            var saved = null;

            try {
                saved = sessionStorage.getItem(STORAGE_KEY);
            } catch (e) {
                saved = null;
            }

            if (!saved) {
                setActiveRange(DEFAULT_RANGE);
                applyFilters();
                return;
            }

            try {
                var state = JSON.parse(saved);
                var fields = state.fields || {};

                if (fromInput) {
                    fromInput.value = fields.fromDate || "";
                }
                if (toInput) {
                    toInput.value = fields.toDate || "";
                }
                if (routeSelect && fields.route) {
                    routeSelect.value = fields.route;
                }
                if (busSelect && fields.bus) {
                    busSelect.value = fields.bus;
                }

                if (state.range && state.range !== "custom") {
                    setActiveRange(state.range);
                } else if (state.range === "custom") {
                    setActiveRange("");
                }

                applyFilters();
            } catch (e) {
                setActiveRange(DEFAULT_RANGE);
                applyFilters();
            }
        }

        function loadFilterOptions() {
            fetch("/admin/api/dashboard/filter-options.php", { cache: "no-store" })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    return;
                }

                (data.routes || []).forEach(function (route) {
                    if (!routeSelect) {
                        return;
                    }
                    var option = document.createElement("option");
                    option.value = route;
                    option.textContent = route;
                    routeSelect.appendChild(option);
                });

                (data.buses || []).forEach(function (bus) {
                    if (!busSelect) {
                        return;
                    }
                    var option = document.createElement("option");
                    option.value = bus.bus_id;
                    option.textContent = bus.label;
                    busSelect.appendChild(option);
                });

                try {
                    var saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || "{}");
                    var fields = saved.fields || {};

                    if (routeSelect && fields.route) {
                        routeSelect.value = fields.route;
                    }
                    if (busSelect && fields.bus) {
                        busSelect.value = fields.bus;
                    }
                } catch (e) {
                }
            })
            .catch (function () {
            });
        }

        if (toggleBtn && panel) {
            toggleBtn.addEventListener("click", function () {
                var isOpen = !panel.hidden;
                panel.hidden = isOpen;
                toggleBtn.classList.toggle("is-open", !isOpen);
                toggleBtn.setAttribute("aria-expanded", String(!isOpen));
            });
        }

        quickButtons.forEach(function (btn) {
            btn.addEventListener("click", function () {
                applyRange(btn.dataset.range);
            });
        });

        if (applyBtn) {
            applyBtn.addEventListener("click", function () {
                var custom = customDates();

                if (custom && custom.fromDate > custom.toDate) {
                    var swap = custom.fromDate;
                    custom.fromDate = custom.toDate;
                    custom.toDate = swap;

                    if (fromInput) {
                        fromInput.value = custom.fromDate;
                    }
                    if (toInput) {
                        toInput.value = custom.toDate;
                    }
                }

                if (custom) {
                    setActiveRange("");
                }

                applyFilters();
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener("click", function () {
                if (fromInput) {
                    fromInput.value = "";
                }
                if (toInput) {
                    toInput.value = "";
                }
                if (routeSelect) {
                    routeSelect.value = "";
                }
                if (busSelect) {
                    busSelect.value = "";
                }

                applyRange(DEFAULT_RANGE);
            });
        }

        window.applyDashboardFilters = function (range) {
            applyRange(range || DEFAULT_RANGE);
        };

        document.addEventListener("DOMContentLoaded", function () {
            loadFilterOptions();
            restoreState();
        });

        window.addEventListener("pageshow", function (event) {
            if (event.persisted) {
                restoreState();
            }
        });

        document.addEventListener("visibilitychange", function () {
            if (document.visibilityState === "visible" && !window.adminFilterQuery) {
                restoreState();
            }
        });
    })();
</script>
