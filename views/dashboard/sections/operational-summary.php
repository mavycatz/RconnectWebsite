<div class="dash-panel" id="opsSummaryPanel">
    <div class="dash-panel-head">
        <div>
            <h2 class="dash-panel-title"><i class="bi bi-clipboard-data"></i> Operational Summary</h2>
            <p class="dash-panel-sub">Key indicators for the selected period</p>
        </div>
    </div>

    <div class="ops-list" id="opsList">
        <div class="ops-loading">Loading operational summary...</div>
    </div>
</div>

<style>
    .ops-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .ops-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 12px;
        background: #fef8f3;
        border: 1px solid #eae3d4;
    }

    .ops-item.is-alert {
        background: #fdf7e3;
        border-color: #e5d79a;
    }

    .ops-icon {
        width: 36px;
        height: 36px;
        min-width: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f9f4da;
        color: #295738;
        font-size: 1rem;
    }

    .ops-item.is-alert .ops-icon {
        background: #f5e9b8;
        color: #8a7b0a;
    }

    .ops-body {
        min-width: 0;
        flex: 1;
    }

    .ops-label {
        font-size: 0.66rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #8a9389;
    }

    .ops-value {
        font-size: 0.84rem;
        font-weight: 700;
        color: #2e4836;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ops-sub {
        font-size: 0.68rem;
        color: #8a9389;
    }

    .ops-loading,
    .ops-empty {
        padding: 18px;
        text-align: center;
        font-size: 0.76rem;
        color: #8a9389;
    }

    .ops-empty i {
        font-size: 1.6rem;
        color: #8a9389;
    }

    .ops-empty.is-error i {
        color: #b3261e;
    }
</style>

<script>
    (function () {
        "use strict";

        var requestSeq = 0;

        function item(icon, label, value, sub, isAlert) {
            return {
                icon: icon,
                label: label,
                value: value,
                sub: sub || "",
                alert: !!isAlert
            };
        }

        function formatAt(value) {
            if (!value) {
                return "";
            }

            var normalized = String(value).replace(" ", "T");
            var date = new Date(normalized);

            if (isNaN(date.getTime())) {
                return String(value);
            }

            return date.toLocaleString("en-US", {
                month: "short",
                day: "numeric",
                hour: "numeric",
                minute: "2-digit"
            });
        }

        function buildItems(data) {
            var items = [];

            if (data.route) {
                items.push(item("bi-signpost-split", "Most active route", data.route.label, data.route.trips.toLocaleString() + " trips"));
            } else {
                items.push(item("bi-signpost-split", "Most active route", "No data", ""));
            }

            if (data.bus) {
                items.push(item("bi-bus-front", "Most utilized bus", data.bus.label, data.bus.trips.toLocaleString() + " trips"));
            } else {
                items.push(item("bi-bus-front", "Most utilized bus", "No data", ""));
            }

            if (data.passenger_activity) {
                items.push(item("bi-people", "Highest passenger activity", data.passenger_activity.label, data.passenger_activity.passengers.toLocaleString() + " passengers"));
            } else {
                items.push(item("bi-people", "Highest passenger activity", "No data", ""));
            }

            if (data.trip_status) {
                items.push(item("bi-geo-alt", "Trip status", data.trip_status.status, [data.trip_status.detail, formatAt(data.trip_status.at)].filter(Boolean).join(" • ")));
            } else {
                items.push(item("bi-geo-alt", "Trip status", "No data", ""));
            }

            if (data.issue) {
                items.push(item(
                "bi-exclamation-triangle",
                "Requires attention",
                data.issue.label + " (" + Number(data.issue.count || 0).toLocaleString() + ")",
                data.issue.latest_at ? "Latest " + formatAt(data.issue.latest_at) : "",
                true
                ));
            }

            return items;
        }

        function render(items) {
            var list = document.getElementById("opsList");
            if (!list) {
                return;
            }

            list.innerHTML = "";

            items.forEach(function (row) {
                var wrap = document.createElement("div");
                wrap.className = "ops-item" + (row.alert ? " is-alert" : "");

                var icon = document.createElement("div");
                icon.className = "ops-icon";
                icon.innerHTML = '<i class="bi ' + row.icon + '"></i>';

                var body = document.createElement("div");
                body.className = "ops-body";

                var label = document.createElement("div");
                label.className = "ops-label";
                label.textContent = row.label;

                var value = document.createElement("div");
                value.className = "ops-value";
                value.textContent = row.value;

                body.appendChild(label);
                body.appendChild(value);

                if (row.sub) {
                    var sub = document.createElement("div");
                    sub.className = "ops-sub";
                    sub.textContent = row.sub;
                    body.appendChild(sub);
                }

                wrap.appendChild(icon);
                wrap.appendChild(body);
                list.appendChild(wrap);
            });
        }

        function renderError() {
            var list = document.getElementById("opsList");
            if (list) {
                list.innerHTML = '<div class="ops-empty is-error"><i class="bi bi-exclamation-circle"></i>Couldn\'t load operational summary.</div>';
            }
        }

        function loadOperationalSummary(query) {
            var requestId = ++requestSeq;

            if (window.__dashLoadBegin) {
                window.__dashLoadBegin();
            }

            fetch("/admin/api/dashboard/operational-summary.php?" + (query || ""), { cache: "no-store" })
            .then(function (res) { return res.json(); })
            .then(function (payload) {
                if (requestId !== requestSeq) {
                    return;
                }

                if (!payload || !payload.success) {
                    renderError();
                    return;
                }

                render(buildItems(payload.data || {}));
            })
            .catch (function () {
                if (requestId !== requestSeq) {
                    return;
                }
                renderError();
            })
            .finally(function () {
                if (window.__dashLoadEnd) {
                    window.__dashLoadEnd();
                }
            });
        }

        window.loadOperationalSummary = loadOperationalSummary;

        document.addEventListener("DOMContentLoaded", function () {
            if (!window.adminFilterQuery) {
                loadOperationalSummary("");
            }
        });
    })();
</script>
