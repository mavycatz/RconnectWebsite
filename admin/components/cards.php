<style>
    .stat-card-group {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
        width: 100%;
        max-width: none;
        margin-bottom: 20px;
    }

    .stat-item {
        width: 100%;
        min-width: 0;
        min-height: 104px;

        background: #fff;
        border: 1.5px solid #cdd9b5;
        border-radius: 18px;

        padding: 14px 16px;

        display: flex;
        align-items: center;
        gap: 14px;

        transition: .2s;
        cursor: default;

        box-shadow: 0 1px 4px rgba(46, 72, 54, 0.05);

        container-type: inline-size;
    }

    .stat-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(46, 72, 54, 0.09);
    }

    .stat-item.is-profit {
        grid-column: span 1;

        background: linear-gradient(135deg, #f9f4da 0%, #fef8f3 70%);
        border: 1.5px solid #c4b417;
        box-shadow: 0 4px 14px rgba(196, 180, 23, 0.14);
        min-height: 122px;
    }

    .stat-icon-box {
        width: 46px;
        height: 46px;
        min-width: 46px;

        border-radius: 12px;

        display: flex;
        align-items: center;
        justify-content: center;

        flex-shrink: 0;

        font-size: 20px;
    }

    .stat-item.is-profit .stat-icon-box {
        width: 52px;
        height: 52px;
        min-width: 52px;
        background: #2e4836;
        color: #f9f4da;
        font-size: 23px;
    }

    .stat-info {
        flex: 1;
        min-width: 0;

        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .stat-label {
        font-size: 11px;
        font-weight: 600;

        color: #6f7d6f;

        text-transform: uppercase;
        letter-spacing: .5px;

        margin-bottom: 4px;

        line-height: 1.2;
    }

    .stat-value {
        font-size: clamp(15px, 7.4cqi, 21px);
        font-weight: 700;

        color: #2e4836;

        line-height: 1.15;

        font-variant-numeric: tabular-nums;

        white-space: nowrap;
        overflow: hidden;
        overflow-wrap: normal;
        word-break: normal;
    }

    .stat-item.is-profit .stat-value {
        font-size: clamp(17px, 8.4cqi, 25px);
        font-weight: 800;
        color: #2e4836;
    }

    .stat-trend {
        font-size: 11.5px;
        font-weight: 600;

        margin-top: 5px;

        line-height: 1.2;
    }

    .stat-desc {
        font-size: 10.5px;

        color: #8a9389;

        margin-top: 2px;

        line-height: 1.2;
    }

    .skeleton-block {
        display: inline-block;

        width: 72%;
        height: 1.05em;

        border-radius: 6px;

        background: linear-gradient(
        90deg,
        #eef1e9 25%,
        #e1e8d7 37%,
        #eef1e9 63%
        );

        background-size: 400% 100%;

        animation: skeletonShimmer 1.3s ease-in-out infinite;

        vertical-align: middle;
    }

    @keyframes skeletonShimmer {
        0% {
            background-position: 100% 0;
        }

        100% {
            background-position: 0 0;
        }
    }

    .stat-value.is-loading {
        color: transparent;
    }

    .stat-desc.loading-msg {
        color: #9aa39a;
        font-style: italic;
    }

    .stat-desc.error-msg {
        color: #c0392b;
        font-style: normal;
        font-weight: 600;
    }

    .stat-desc.no-data-msg {
        color: #8a9a8a;
        font-weight: 500;
    }

    .stat-value.is-empty {
        color: #b0b9a8;
    }

    @media(max-width:1100px) {
        .stat-card-group {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .stat-item {
            min-height: 100px;
            padding: 14px;
        }
    }

    @media(max-width:575px) {
        .stat-card-group {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .stat-item {
            min-height: 96px;
            padding: 13px 14px;

            border-radius: 14px;

            gap: 12px;
        }

        .stat-item.is-profit {
            min-height: 108px;
        }

        .stat-icon-box {
            width: 44px;
            height: 44px;
            min-width: 44px;

            border-radius: 12px;

            font-size: 19px;
        }

        .stat-item.is-profit .stat-icon-box {
            width: 48px;
            height: 48px;
            min-width: 48px;
        }

        .stat-label {
            font-size: 10px;
        }

        .stat-trend {
            font-size: 11px;
        }

        .stat-desc {
            font-size: 10px;
        }
    }
</style>

<div class="stat-card-group">

    <div class="stat-item is-profit" data-type="profit">
        <div class="stat-icon-box">
            <i class="bi bi-graph-up-arrow"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Estimated Profit</div>
            <div class="stat-value sit-count" id="totalProfit">₱0.00</div>
            <div id="profitTrend" class="stat-trend"></div>
            <div class="stat-desc">Gross income less fuel cost</div>
        </div>
    </div>

    <div class="stat-item" data-type="passengers">
        <div class="stat-icon-box" style="background:#f9f4da;color:#295738;">
            <i class="bi bi-people"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Total Passengers</div>
            <div class="stat-value sit-count" id="totalPassengers">0</div>
            <div id="passengersTrend" class="stat-trend"></div>
            <div class="stat-desc">People served</div>
        </div>
    </div>

    <div class="stat-item" data-type="trips">
        <div class="stat-icon-box" style="background:#cdd9b5;color:#2e4836;">
            <i class="bi bi-bus-front"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Total Trips</div>
            <div class="stat-value sit-count" id="totalTrips">0</div>
            <div id="tripsTrend" class="stat-trend"></div>
            <div class="stat-desc">Recorded trips</div>
        </div>
    </div>

    <div class="stat-item" data-type="fuel">
        <div class="stat-icon-box" style="background:#fef8f3;color:#c4b417;border:1px solid #cdd9b5;">
            <i class="bi bi-fuel-pump"></i>
        </div>
        <div class="stat-info">
                <div class="stat-label">Average Fuel Consumption</div>
                <div class="stat-value sit-count" id="avgFuelEff">0.0 km/L</div>
                <div id="fuelEffTrend" class="stat-trend"></div>
                <div class="stat-desc">Fleet km/L (ODO distance / actual liters)</div>
        </div>
    </div>

</div>

<script>
    (function () {
        "use strict";

        var activeRequest = null;

        var LOADING_MSG = {
            today: "Loading daily data...",
            week: "Loading weekly data...",
            month: "Loading monthly data...",
            year: "Loading yearly data...",
            custom: "Loading selected range..."
        };

        var PERIOD_LABEL = {
            today: "yesterday",
            week: "previous week",
            month: "previous month",
            year: "previous year",
            custom: "previous period"
        };

        function formatPeso(num) {
            return "₱" + Number(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function currentRange() {
            var match = /(^|[?&])range=([^&]+)/.exec(window.adminFilterQuery || "");
            return match ? decodeURIComponent(match[2]) : "today";
        }

        function periodLabel(range) {
            return PERIOD_LABEL[range] || "previous period";
        }

        function captureDescriptions() {
            document.querySelectorAll(".stat-item .stat-desc").forEach(function (desc) {
                if (desc.dataset.orig === undefined) {
                    desc.dataset.orig = desc.textContent;
                }
            });
        }

        function renderSkeletons(range) {
            var msg = LOADING_MSG[range] || "Loading data...";
            captureDescriptions();

            ["totalProfit", "totalPassengers", "totalTrips", "avgFuelEff"].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) {
                    el.innerHTML = '<span class="skeleton-block"></span>';
                    el.classList.add("is-loading");
                    el.classList.remove("is-empty");
                }
            });

            ["profitTrend", "passengersTrend", "tripsTrend", "fuelEffTrend"].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) {
                    el.textContent = "";
                    el.className = "stat-trend";
                }
            });

            document.querySelectorAll(".stat-item .stat-desc").forEach(function (desc) {
                desc.textContent = msg;
                desc.classList.add("loading-msg");
                desc.classList.remove("error-msg", "no-data-msg");
            });
        }

        function renderError(detail) {
            document.querySelectorAll(".stat-item .stat-value").forEach(function (el) {
                el.classList.remove("is-loading");
                el.textContent = "—";
            });

            var msg = detail || "Couldn't load data. Select a period to retry.";

            document.querySelectorAll(".stat-item .stat-desc").forEach(function (desc) {
                desc.textContent = msg;
                desc.classList.add("error-msg");
                desc.classList.remove("loading-msg", "no-data-msg");
            });
        }

        function setTrend(id, cur, prev, label) {
            var el = document.getElementById(id);
            if (!el) {
                return;
            }

            el.className = "stat-trend";

            if (prev === null || prev === undefined || cur === null || cur === undefined) {
                el.innerHTML = "";
                return;
            }

            if (Number(prev) === 0) {
                el.innerHTML = "";
                return;
            }

            var pct = (Number(cur) - Number(prev)) / Math.abs(Number(prev)) * 100;
            var up = pct >= 0;
            var arrow = up ? '↑' : '↓';
            var color = up ? '#16a34a' : '#dc2626';

            el.innerHTML = '<span style="color:' + color + ';">' + arrow + ' ' + Math.abs(pct).toFixed(1) + '% vs ' + label + '</span>';
        }

        function renderData(cur, prev) {
            var label = periodLabel(currentRange());

            var profit = Number(cur.net_profit ?? 0);
            var roundTrips = Number(cur.round_trips ?? 0);
            var passengers = Number(cur.passengers_only ?? 0);
            var fuelEff = Number(cur.fuel_efficiency || cur.avg_kpl || 0);

            var gross = Number(cur.gross_income ?? 0);

            if (profit === 0 && gross <= 0 && roundTrips <= 0 && passengers <= 0 && fuelEff <= 0) {
                var quiet = {
                    totalProfit: "₱0.00",
                    totalPassengers: "0",
                    totalTrips: "0",
                    avgFuelEff: "0.0 km/L"
                };

                Object.keys(quiet).forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) {
                        el.textContent = quiet[id];
                        el.classList.remove("is-loading");
                        el.classList.add("is-empty");
                    }
                });

                ["profitTrend", "passengersTrend", "tripsTrend", "fuelEffTrend"].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) {
                        el.innerHTML = "";
                        el.className = "stat-trend";
                    }
                });

                document.querySelectorAll(".stat-item .stat-desc").forEach(function (desc) {
                    desc.textContent = "No data available for this period.";
                    desc.classList.remove("loading-msg", "error-msg");
                    desc.classList.add("no-data-msg");
                });

                fitCardValues();

                return;
            }

            var profitEl = document.getElementById("totalProfit");
            if (profitEl) {
                profitEl.textContent = formatPeso(profit);
                profitEl.classList.remove("is-loading", "is-empty");
            }

            var paxEl = document.getElementById("totalPassengers");
            if (paxEl) {
                paxEl.textContent = passengers.toLocaleString();
                paxEl.classList.remove("is-loading", "is-empty");
            }

            var tripsEl = document.getElementById("totalTrips");
            if (tripsEl) {
                tripsEl.textContent = roundTrips.toLocaleString();
                tripsEl.classList.remove("is-loading", "is-empty");
            }

            var fuelEl = document.getElementById("avgFuelEff");
            if (fuelEl) {
                fuelEl.textContent = fuelEff.toFixed(2) + " km/L";
                fuelEl.classList.remove("is-loading", "is-empty");
            }

            setTrend("profitTrend", profit, prev.net_profit, label);
            setTrend("passengersTrend", passengers, prev.passengers_only, label);
            setTrend("tripsTrend", roundTrips, prev.round_trips, label);
            setTrend("fuelEffTrend", fuelEff, prev.fuel_efficiency || prev.avg_kpl, label);

            document.querySelectorAll(".stat-item .stat-desc").forEach(function (desc) {
                desc.textContent = desc.dataset.orig !== undefined ? desc.dataset.orig : desc.textContent;
                desc.classList.remove("loading-msg", "error-msg", "no-data-msg");
            });

            fitCardValues();
        }

        function fitCardValues() {
            document.querySelectorAll(".stat-item .stat-value").forEach(function (el) {
                el.style.fontSize = "";

                var size = parseFloat(window.getComputedStyle(el).fontSize) || 20;
                var guard = 0;

                while (el.scrollWidth > el.clientWidth + 1 && size > 12 && guard < 60) {
                    size -= 0.5;
                    el.style.fontSize = size + "px";
                    guard += 1;
                }
            });
        }

        var fitTimer = null;

        window.addEventListener("resize", function () {
            clearTimeout(fitTimer);
            fitTimer = setTimeout(fitCardValues, 120);
        });

        function loadDashboard(filterQuery) {
            var range = currentRange();

            renderSkeletons(range);

            if (activeRequest) {
                activeRequest.abort();
            }

            var controller = new AbortController();
            activeRequest = controller;

            var query = filterQuery || "";
            var url = "/admin/api/dashboard/stats.php?" + query;

            if (!/(^|[?&])range=/.test(url)) {
                url += (query ? "&" : "") + "range=" + range;
            }

            if (window.__dashLoadBegin) {
                window.__dashLoadBegin();
            }

            fetch(url, { signal: controller.signal })
            .then(function (res) { return res.text(); })
            .then(function (text) {
                if (activeRequest !== controller) {
                    return;
                }

                var response;

                try {
                    response = JSON.parse(text);
                } catch (e) {
                    console.error("Dashboard API returned non-JSON:", text.slice(0, 500));
                    renderError("Couldn't load data. Select a period to retry.");
                    return;
                }

                if (!response.success) {
                    console.error("Dashboard API error:", response);
                    renderError("Couldn't load data. Select a period to retry.");
                    return;
                }

                renderData(response.data || {}, response.previous || {});
            })
            .catch (function (err) {
                if (err.name === "AbortError") {
                    return;
                }
                if (activeRequest !== controller) {
                    return;
                }

                console.error("Dashboard fetch failed:", err);
                renderError("Request failed: " + err.message);
            })
            .finally(function () {
                if (window.__dashLoadEnd) {
                    window.__dashLoadEnd();
                }
            });
        }

        window.loadDashboard = loadDashboard;

        document.addEventListener("DOMContentLoaded", function () {
            fitCardValues();

            if (!window.adminFilterQuery) {
                loadDashboard();
            }
        });
    })();
</script>
