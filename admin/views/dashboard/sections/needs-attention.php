<div class="dash-panel" id="needsAttentionPanel">
    <div class="dash-panel-head">
        <div>
            <h2 class="dash-panel-title"><i class="bi bi-exclamation-triangle"></i> Needs Attention</h2>
            <p class="dash-panel-sub">Items that require Super Admin follow-up</p>
        </div>
    </div>

    <div class="attention-list" id="attentionList"></div>

    <div class="attention-empty" id="attentionEmpty">
        <i class="bi bi-check-circle" id="attentionEmptyIcon"></i>
        <span id="attentionEmptyText">Checking attention items...</span>
    </div>
</div>

<style>
    .attention-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .attention-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 12px;
        background: #fdf7e3;
        border: 1px solid #e5d79a;
    }

    .attention-icon {
        width: 36px;
        height: 36px;
        min-width: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f5e9b8;
        color: #8a7b0a;
        font-size: 1rem;
    }

    .attention-body {
        min-width: 0;
        flex: 1;
    }

    .attention-label {
        font-size: 0.8rem;
        font-weight: 700;
        color: #2e4836;
    }

    .attention-sub {
        font-size: 0.68rem;
        color: #8a9389;
    }

    .attention-count {
        font-size: 1.05rem;
        font-weight: 800;
        color: #8a7b0a;
        font-variant-numeric: tabular-nums;
    }

    .attention-empty {
        padding: 18px;
        text-align: center;
        font-size: 0.76rem;
        color: #8a9389;
    }

    .attention-empty i {
        font-size: 1.6rem;
        color: #295738;
    }

    .attention-empty.is-error i {
        color: #b3261e;
    }
</style>

<script>
    (function () {
        "use strict";

        var requestSeq = 0;

        var ICONS = {
            trip_discrepancy: "bi-cash-coin",
            fuel_verification: "bi-fuel-pump",
            system_alert: "bi-shield-exclamation",
            dispatcher_discrepancy: "bi-person-exclamation",
            inspector_review: "bi-clipboard2-check",
            bus_problem: "bi-cone-striped"
        };

        function formatAt(value) {
            if (!value) {
                return "";
            }

            var date = new Date(String(value).replace(" ", "T"));

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

        function render(items) {
            var list = document.getElementById("attentionList");
            if (!list) {
                return;
            }

            list.innerHTML = "";

            items.forEach(function (row) {
                var wrap = document.createElement("div");
                wrap.className = "attention-item";

                var icon = document.createElement("div");
                icon.className = "attention-icon";
                icon.innerHTML = '<i class="bi ' + (ICONS[row.type] || "bi-exclamation-triangle") + '"></i>';

                var body = document.createElement("div");
                body.className = "attention-body";

                var label = document.createElement("div");
                label.className = "attention-label";
                label.textContent = row.label;

                var sub = document.createElement("div");
                sub.className = "attention-sub";

                var details = row.details || null;
                if (details) {
                    var parts = [];

                    if (row.type === "bus_problem") {
                        if (details.bus_id) {
                            parts.push("BUS " + details.bus_id);
                        }
                        if (details.problem_type) {
                            parts.push(details.problem_type);
                        }
                        if (details.trip_no) {
                            parts.push("TRIP #" + details.trip_no);
                        }
                        if (details.driver_name) {
                            parts.push("Driver " + details.driver_name);
                        }
                        if (details.reported_at) {
                            parts.push("Reported " + formatAt(details.reported_at));
                        }
                        if (details.status) {
                            var stage = String(details.status);
                            parts.push(stage.charAt(0).toUpperCase() + stage.slice(1));
                        }
                        sub.textContent = parts.join(" · ");
                    } else {

                    if (details.trip_id) {
                        parts.push("TRIP #" + details.trip_id);
                    }
                    if (details.bus_id) {
                        parts.push(details.bus_id);
                    }
                    if (details.passenger_name) {
                        parts.push(details.passenger_name + (details.passenger_code ? " (" + details.passenger_code + ")" : ""));
                    }
                    if (details.dispatcher_id) {
                        parts.push("Dispatcher " + details.dispatcher_id);
                    }
                    if (details.driver_id) {
                        parts.push("Driver " + details.driver_id);
                    }
                    if (details.dispatcher_count !== undefined || details.driver_count !== undefined) {
                        var counts = Number(details.dispatcher_count || 0) + " / " + Number(details.driver_count || 0) +
                        (details.inspector_count === null || details.inspector_count === undefined ? "" : " / " + Number(details.inspector_count));
                        parts.push("Counts " + counts + " (diff " + Number(details.difference || 0) + ")");
                    }
                    if (details.validation_status) {
                        parts.push(details.validation_status);
                    }
                    if (details.reason) {
                        parts.push(details.reason);
                    }
                    if (details.review_status) {
                        parts.push(details.review_status);
                    }
                    sub.textContent = parts.join(" · ");

                    }
                } else {
                    sub.textContent = row.latest_at ? "Latest " + formatAt(row.latest_at) : "";
                }

                body.appendChild(label);
                body.appendChild(sub);

                var count = document.createElement("div");
                count.className = "attention-count";
                count.textContent = Number(row.count || 0).toLocaleString();

                wrap.appendChild(icon);
                wrap.appendChild(body);
                wrap.appendChild(count);

                if (row.link) {
                    wrap.style.cursor = "pointer";
                    wrap.title = "Open review";
                    wrap.addEventListener("click", function () {
                        window.location.href = row.link;
                    });
                }

                list.appendChild(wrap);
            });
        }

        function showEmpty(message, isError) {
            var list = document.getElementById("attentionList");
            var empty = document.getElementById("attentionEmpty");
            var text = document.getElementById("attentionEmptyText");
            var icon = document.getElementById("attentionEmptyIcon");

            if (list) {
                list.innerHTML = "";
            }

            if (empty) {
                empty.hidden = false;
                empty.classList.toggle("is-error", !!isError);
            }

            if (text) {
                text.textContent = message;
            }

            if (icon) {
                icon.className = isError ? "bi bi-exclamation-circle" : "bi bi-check-circle";
            }
        }

        function showItems(items) {
            var empty = document.getElementById("attentionEmpty");

            if (empty) {
                empty.hidden = true;
            }

            render(items);
        }

        function loadNeedsAttention(query) {
            var requestId = ++requestSeq;

            if (window.__dashLoadBegin) {
                window.__dashLoadBegin();
            }

            fetch("/admin/api/dashboard/needs-attention.php?" + (query || ""), { cache: "no-store" })
            .then(function (res) { return res.json(); })
            .then(function (payload) {
                if (requestId !== requestSeq) {
                    return;
                }

                if (!payload || !payload.success) {
                    showEmpty("Couldn't check attention items. Select a period to retry.", true);
                    return;
                }

                var total = Number(payload.total || 0);
                var items = payload.items || [];

                if (total > 0 && items.length > 0) {
                    showItems(items);
                } else {
                    showEmpty("Nothing needs attention right now.", false);
                }
            })
            .catch (function () {
                if (requestId !== requestSeq) {
                    return;
                }
                showEmpty("Couldn't check attention items. Select a period to retry.", true);
            })
            .finally(function () {
                if (window.__dashLoadEnd) {
                    window.__dashLoadEnd();
                }
            });
        }

        window.loadNeedsAttention = loadNeedsAttention;

        document.addEventListener("DOMContentLoaded", function () {
            if (!window.adminFilterQuery) {
                loadNeedsAttention("");
            }
        });
    })();
</script>
