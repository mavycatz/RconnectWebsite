<div class="dm-section" id="dispatcherMonitoringSection">
    <style>
        .dm-section { font-family: 'Poppins', sans-serif; margin-top: 24px; max-width: 100%; min-width: 0; }
        .dm-section h2, .dm-section h3, .dm-section p { font-family: 'Poppins', sans-serif; }
        .dm-panel { background: #fffdf8; border: 1px solid #e5e0a4; border-radius: 17px; padding: 1.1rem 1.25rem; box-shadow: 0 3px 10px rgba(0, 0, 0, .04); max-width: 100%; min-width: 0; }
        .dm-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
        .dm-title { font-weight: 700; font-size: 1.05rem; color: #295738; margin: 0 0 2px; }
        .dm-sub { font-size: .75rem; color: #8a9a8a; margin: 0; }
        .dm-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .dm-input, .dm-select { border: 1px solid #cdd9b5; border-radius: 8px; background: #fef8f3; font-family: 'Poppins', sans-serif; font-size: .75rem; color: #2e4836; padding: 6px 9px; }
        .dm-btn { border: 1px solid #295738; background: #295738; color: #fff; border-radius: 8px; font-size: .72rem; font-weight: 600; padding: 6px 12px; cursor: pointer; }
        .dm-btn:hover { background: #3c6e4f; }
        .dm-btn-ghost { background: #f9f4da; color: #2e4836; border-color: #cdd9b5; }
        .dm-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 14px; }
        .dm-stat { background: #fff; border: 1px solid #e5e0a4; border-radius: 12px; padding: 10px 12px; }
        .dm-stat .l { font-size: .62rem; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; color: #8a9a8a; }
        .dm-stat .v { font-size: 1.35rem; font-weight: 800; color: #295738; line-height: 1.2; }
        .dm-stat.is-alert .v { color: #b3261e; }
        .dm-filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 12px; }
        .dm-table-wrap { overflow-x: auto; max-width: 100%; border: 1px solid #eee7d2; border-radius: 12px; background: #fff; }
        .dm-table { width: 100%; border-collapse: collapse; font-size: .72rem; color: #2e4836; }
        .dm-table th { text-align: left; padding: 9px 10px; background: #f9f4da; font-size: .62rem; text-transform: uppercase; letter-spacing: .5px; color: #6f7d6f; white-space: nowrap; }
        .dm-table td { padding: 9px 10px; border-top: 1px solid #f1ecdd; white-space: nowrap; }
        #dmBody td:nth-child(4),
        #dmBprBody td:nth-child(2),
        #dmBprBody td:nth-child(4),
        #dmBprBody td:nth-child(7) { white-space: normal; overflow-wrap: anywhere; max-width: 260px; }
        .dm-table tr:hover td { background: #f9f6ea; }
        .dm-badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 2px 9px; font-size: .64rem; font-weight: 700; }
        .dm-badge.is-active { background: #e4f0e2; color: #1e5b3a; }
        .dm-badge.is-completed { background: #e8eef7; color: #1f4e79; }
        .dm-badge.is-cancelled { background: #f1ecdd; color: #6f7d6f; }
        .dm-badge.is-review { background: #fbe7e4; color: #a32018; }
        .dm-badge.is-ok { background: #f1ecdd; color: #6f7d6f; }
        .dm-badge.is-pending { background: #fdf6dd; color: #7a6a12; }
        .dm-link { color: #2563eb; font-weight: 600; cursor: pointer; text-decoration: none; }
        .dm-link:hover { text-decoration: underline; }
        .dm-empty { padding: 18px; text-align: center; color: #8a9a8a; font-size: .76rem; }
        .dm-modal { position: fixed; inset: 0; z-index: 1080; background: rgba(20, 32, 22, .45); display: flex; align-items: center; justify-content: center; padding: 16px; }
        .dm-modal[hidden] { display: none; }
        .dm-modal-card { background: #fffdf8; border-radius: 16px; border: 1px solid #cdd9b5; width: 100%; max-width: 720px; max-height: 88vh; overflow-y: auto; padding: 18px 20px; }
        .dm-modal-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .dm-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 8px; margin-bottom: 12px; }
        .dm-cell { background: #fff; border: 1px solid #eee7d2; border-radius: 10px; padding: 8px 10px; }
        .dm-cell .l { font-size: .6rem; text-transform: uppercase; letter-spacing: .5px; color: #8a9a8a; font-weight: 700; }
        .dm-cell .v { font-size: .82rem; font-weight: 700; color: #2e4836; }
        .dm-counts { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 10px 0 14px; }
        .dm-count { text-align: center; background: #fff; border: 1px solid #eee7d2; border-radius: 10px; padding: 10px; }
        .dm-count .n { font-size: 1.3rem; font-weight: 800; color: #295738; }
        .dm-count .l { font-size: .6rem; text-transform: uppercase; letter-spacing: .5px; color: #8a9a8a; font-weight: 700; }
        .dm-count.is-diff .n { color: #b3261e; }
        .dm-history-list { display: flex; flex-direction: column; gap: 6px; margin-top: 8px; }
        .dm-history-row { border: 1px solid #eee7d2; border-radius: 10px; padding: 8px 10px; font-size: .7rem; color: #2e4836; background: #fff; }
        .dm-history-row .a { font-weight: 700; }
        .dm-history-row .t { color: #8a9389; font-size: .64rem; }
        .dm-resolve { margin-top: 14px; padding-top: 12px; border-top: 1px dashed #d6dfc2; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .dm-resolve input { flex: 1 1 260px; }
    </style>

    <div class="dm-panel">
        <div class="dm-head">
            <div>
                <h3 class="dm-title"><i class="bi bi-broadcast"></i> Dispatcher Monitoring</h3>
                <p class="dm-sub">Live dispatcher activity, passenger-count cross-checks, and accountability · Last updated <span id="dmUpdated">—</span></p>
            </div>
            <div class="dm-actions">
                <input type="date" id="dmDate" class="dm-input" title="Activity date">
                <button type="button" id="dmToggleHistory" class="dm-btn"><i class="bi bi-clock-history"></i> Accountability</button>
            </div>
        </div>

        <div class="dm-stats" id="dmStats"></div>

        <div class="dm-filters" id="dmFilters">
            <select id="dmDispatcher" class="dm-select"><option value="">All dispatchers</option></select>
            <select id="dmBus" class="dm-select"><option value="">All buses</option></select>
            <select id="dmDriver" class="dm-select"><option value="">All drivers</option></select>
            <select id="dmStatus" class="dm-select">
                <option value="">All statuses</option>
                <option value="Scheduled">Scheduled</option>
                <option value="Departed">Departed</option>
                <option value="Completed">Completed</option>
                <option value="Cancelled">Cancelled</option>
            </select>
            <select id="dmDiscrepancy" class="dm-select">
                <option value="">Any discrepancy</option>
                <option value="Requires Review">Requires Review</option>
                <option value="None">None</option>
            </select>
            <button type="button" id="dmApply" class="dm-btn dm-btn-ghost">Apply</button>
            <button type="button" id="dmClear" class="dm-btn dm-btn-ghost">Clear</button>
        </div>

        <div class="dm-table-wrap">
            <table class="dm-table">
                <thead>
                    <tr>
                        <th>Trip</th><th>Dispatcher</th><th>Bus</th><th>Driver</th><th>Route</th>
                        <th>Dispatch</th><th>Status</th><th>Passengers</th><th>Inspection</th>
                        <th>Fuel</th><th>Discrepancy</th><th>Last update</th>
                    </tr>
                </thead>
                <tbody id="dmBody">
                    <tr><td colspan="12" class="dm-empty">Loading dispatcher activity…</td></tr>
                </tbody>
            </table>
        </div>

        <div style="margin-top:16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                <h3 class="dm-title" style="font-size:.95rem;margin:0;">Bus Problem Reports</h3>
                <span class="dm-sub" style="margin:0;">Reported → Acknowledged → Replacement Assigned → Passengers Transferred → Resolved</span>
            </div>
            <div class="dm-table-wrap" style="margin-top:8px;">
                <table class="dm-table">
                    <thead>
                        <tr>
                            <th>Bus</th><th>Problem</th><th>Trip</th><th>Driver</th>
                            <th>Reported</th><th>Status</th><th>Replacement</th><th>Transferred</th>
                        </tr>
                    </thead>
                    <tbody id="dmBprBody">
                        <tr><td colspan="8" class="dm-empty">Loading bus problems…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="dmHistory" hidden style="margin-top:16px;">
            <h3 class="dm-title" style="font-size:.95rem;">Accountability history</h3>
            <div class="dm-history-list" id="dmHistoryList"><div class="dm-empty">Choose a dispatcher and apply filters.</div></div>
        </div>
    </div>

    <div class="dm-modal" id="dmModal" hidden>
        <div class="dm-modal-card">
            <div class="dm-modal-head">
                <h3 class="dm-title" id="dmModalTitle">Trip detail</h3>
                <button type="button" class="dm-btn dm-btn-ghost" id="dmModalClose"><i class="bi bi-x-lg"></i></button>
            </div>
            <div id="dmModalBody"><div class="dm-empty">Loading…</div></div>
        </div>
    </div>

    <script>
        (function () {
            "use strict";

            var DM_API = "/admin/api/Inspectors/DispatcherMonitoring.php";
            var DM_INTERVAL = 15000;
            var dmTimer = null;
            var dmCurrentTrip = null;

            function $(id) { return document.getElementById(id); }
            function esc(value) {
                return String(value === null || value === undefined ? "" : value)
                .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
            }
            function money(value) {
                return "₱" + Number(value || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            function today() {
                var d = new Date();
                return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
            }
            function statusClass(status) {
                switch (String(status || "").toLowerCase()) {
                    case "departed": case "scheduled": return "is-active";
                    case "completed": return "is-completed";
                    case "cancelled": return "is-cancelled";
                    default: return "is-pending";
                }
            }
            function discrepancyClass(status) {
                if (status === "Requires Review") return "is-review";
                if (status === "Pending") return "is-pending";
                return "is-ok";
            }
            function dmQuery(extra) {
                var params = new URLSearchParams();
                var date = $("dmDate") && $("dmDate").value ? $("dmDate").value : today();
                params.set("date", date);
                var map = { dispatcher: "dmDispatcher", bus: "dmBus", driver: "dmDriver", status: "dmStatus", discrepancy: "dmDiscrepancy" };
                Object.keys(map).forEach(function (key) {
                    var el = $(map[key]);
                    if (el && el.value) params.set(key, el.value);
                });
                if (extra) Object.keys(extra).forEach(function (k) { params.set(k, extra[k]); });
                return params.toString();
            }
            function fillOptions(data) {
                var filters = data || {};
                var dispatchers = filters.dispatchers || [];
                var buses = filters.buses || [];
                var drivers = filters.drivers || [];
                var dSel = $("dmDispatcher");
                if (dSel && dSel.options.length <= 1 && dispatchers.length) {
                    dispatchers.forEach(function (d) {
                        var o = document.createElement("option");
                        o.value = d.id; o.textContent = d.name || d.id;
                        dSel.appendChild(o);
                    });
                }
                var bSel = $("dmBus");
                if (bSel && bSel.options.length <= 1 && buses.length) {
                    buses.forEach(function (b) {
                        var o = document.createElement("option");
                        o.value = b; o.textContent = b;
                        bSel.appendChild(o);
                    });
                }
                var rSel = $("dmDriver");
                if (rSel && rSel.options.length <= 1 && drivers.length) {
                    drivers.forEach(function (d) {
                        var o = document.createElement("option");
                        o.value = d.id; o.textContent = d.name || d.id;
                        rSel.appendChild(o);
                    });
                }
            }
            function renderStats(summary) {
                var s = summary || {};
                var cards = [
                { l: "Active", v: s.active || 0, alert: false },
                { l: "Completed", v: s.completed || 0, alert: false },
                { l: "Cancelled", v: s.cancelled || 0, alert: false },
                { l: "Requires Review", v: s.requires_review || 0, alert: (s.requires_review || 0) > 0 },
                { l: "Inspection Passed", v: s.inspection_passed || 0, alert: false },
                { l: "Fuel Records", v: s.fuel_records || 0, alert: false }
                ];
                $("dmStats").innerHTML = cards.map(function (c) {
                    return '<div class="dm-stat' + (c.alert ? ' is-alert' : '') + '"><div class="l">' + c.l + '</div><div class="v">' + Number(c.v).toLocaleString() + '</div></div>';
                }).join("");
            }
            function renderRows(rows) {
                var body = $("dmBody");
                if (!rows || !rows.length) {
                    body.innerHTML = '<tr><td colspan="12" class="dm-empty">No dispatcher activity for this date.</td></tr>';
                    return;
                }
                body.innerHTML = rows.map(function (r) {
                    var passengers = r.passenger_count + " / " + r.driver_count + (r.inspector_count === null || r.inspector_count === undefined ? "" : " / " + r.inspector_count);
                    var fuel = (Number(r.fuel_liters) > 0 || Number(r.fuel_cost) > 0)
                    ? (Number(r.fuel_liters).toFixed(1) + " L · " + money(r.fuel_cost))
                    : "—";
                    return "<tr>" +
                    '<td><a class="dm-link" data-trip="' + Number(r.trip_id) + '">#' + Number(r.trip_id) + (r.trip_number ? " (" + esc(r.trip_number) + ")" : "") + "</a></td>" +
                    "<td>" + esc(r.dispatcher || r.dispatcher_id || "—") + "</td>" +
                    "<td>" + esc(r.bus || r.bus_id || "—") + "</td>" +
                    "<td>" + esc(r.driver || r.driver_id || "—") + "</td>" +
                    "<td>" + esc(r.route_name || "—") + "</td>" +
                    "<td>" + esc(r.dispatch_time || "—") + "</td>" +
                    '<td><span class="dm-badge ' + statusClass(r.status) + '">' + esc(r.status || "—") + "</span></td>" +
                    "<td>" + esc(passengers) + "</td>" +
                    "<td>" + esc(r.inspection_status || "—") + "</td>" +
                    "<td>" + esc(fuel) + "</td>" +
                    '<td><span class="dm-badge ' + discrepancyClass(r.discrepancy_status) + '">' + esc(r.discrepancy_status || "—") + "</span></td>" +
                    "<td>" + esc(r.last_update || "—") + "</td>" +
                    "</tr>";
                }).join("");
            }
            function stageClass(stage) {
                switch (String(stage || "").toLowerCase()) {
                    case "resolved": return "is-ok";
                    case "passengers transferred": return "is-completed";
                    case "acknowledged": return "is-active";
                    default: return "is-review";
                }
            }
            function dmRenderBusProblems(rows) {
                var body = $("dmBprBody");
                if (!body) return;
                if (!rows || !rows.length) {
                    body.innerHTML = '<tr><td colspan="8" class="dm-empty">No bus problems for this date.</td></tr>';
                    return;
                }
                body.innerHTML = rows.map(function (r) {
                    var bus = r.plate_number ? (r.bus_id + " (" + r.plate_number + ")") : (r.bus_id || "—");
                    var problem = (r.problem_type || "—") + (r.problem_detail ? " · " + r.problem_detail : "");
                    var replacement = r.replacement_bus_id
                    ? ("BUS " + r.replacement_bus_id + (r.replacement_driver_id ? " / " + r.replacement_driver_id : "") + (r.replacement_assigned_at ? " · " + r.replacement_assigned_at : ""))
                    : "—";
                    var transferred = Number(r.transferred_count || 0);
                    return "<tr>" +
                    "<td>" + esc(bus) + "</td>" +
                    "<td>" + esc(problem) + "</td>" +
                    "<td>" + (r.trip_no ? ("#" + esc(r.trip_no)) : "—") + "</td>" +
                    "<td>" + esc(r.driver || r.driver_id || "—") + "</td>" +
                    "<td>" + esc(r.reported_at || "—") + "</td>" +
                    '<td><span class="dm-badge ' + stageClass(r.stage) + '">' + esc(r.stage || "—") + "</span></td>" +
                    "<td>" + esc(replacement) + "</td>" +
                    "<td>" + (transferred > 0 ? esc(String(transferred)) : "—") + "</td>" +
                    "</tr>";
                }).join("");
            }
            function dmLoadBusProblems() {
                if (!$("dmBprBody")) return;
                fetch(DM_API + "?action=bus_problems&" + dmQuery(), { cache: "no-store" })
                .then(function (r) { return r.json(); })
                .then(function (payload) {
                    if (!payload || !payload.success) throw new Error("failed");
                    var data = payload.data || {};
                    dmRenderBusProblems(data.rows || []);
                })
                .catch (function () {
                    $("dmBprBody").innerHTML = '<tr><td colspan="8" class="dm-empty">Couldn\'t load bus problems.</td></tr>';
                });
            }
            function dmLoadLive() {
                dmLoadBusProblems();
                fetch(DM_API + "?action=live&" + dmQuery(), { cache: "no-store" })
                .then(function (r) { return r.json(); })
                .then(function (payload) {
                    if (!payload || !payload.success) throw new Error("failed");
                    var data = payload.data || {};
                    fillOptions(data.filters);
                    renderStats(data.summary);
                    renderRows(data.rows);
                    var stamp = data.generated_at || new Date().toLocaleTimeString();
                    if ($("dmUpdated")) $("dmUpdated").textContent = stamp;
                    if (typeof window.umSetUpdated === "function") window.umSetUpdated(stamp);
                })
                .catch (function () {
                    $("dmBody").innerHTML = '<tr><td colspan="12" class="dm-empty">Couldn\'t load dispatcher activity. Select a date and retry.</td></tr>';
                });
            }
            function dmLoadHistory() {
                var dispatcher = $("dmDispatcher") ? $("dmDispatcher").value : "";
                if (!dispatcher) {
                    $("dmHistoryList").innerHTML = '<div class="dm-empty">Choose a dispatcher to see their full accountability history.</div>';
                    return;
                }
                fetch(DM_API + "?action=history&dispatcher=" + encodeURIComponent(dispatcher), { cache: "no-store" })
                .then(function (r) { return r.json(); })
                .then(function (payload) {
                    if (!payload || !payload.success) throw new Error("failed");
                    var data = payload.data || {};
                    var entries = data.entries || [];
                    if (!entries.length) {
                        $("dmHistoryList").innerHTML = '<div class="dm-empty">No accountability entries for this dispatcher.</div>';
                        return;
                    }
                    $("dmHistoryList").innerHTML = entries.map(function (e) {
                        return '<div class="dm-history-row">' +
                        '<div class="a">' + esc(e.action || "") + (e.record_id ? " · Trip #" + esc(e.record_id) : "") + "</div>" +
                        '<div>' + esc(e.description || "") + "</div>" +
                        '<div class="t">' + esc(e.username || "") + " · " + esc(e.created_at || "") + (e.status === "Warning" ? " · Warning" : "") + "</div>" +
                        "</div>";
                    }).join("");
                })
                .catch (function () {
                    $("dmHistoryList").innerHTML = '<div class="dm-empty">Couldn\'t load accountability history.</div>';
                });
            }
            function dmOpenDetail(tripId) {
                if (!tripId) return;
                dmCurrentTrip = tripId;
                $("dmModal").hidden = false;
                $("dmModalTitle").textContent = "Trip #" + tripId + " · Dispatcher review";
                $("dmModalBody").innerHTML = '<div class="dm-empty">Loading…</div>';
                fetch(DM_API + "?action=detail&trip_id=" + encodeURIComponent(tripId), { cache: "no-store" })
                .then(function (r) { return r.json(); })
                .then(function (payload) {
                    if (!payload || !payload.success) throw new Error("failed");
                    dmRenderDetail(payload.data || {});
                })
                .catch (function () {
                    $("dmModalBody").innerHTML = '<div class="dm-empty">Couldn\'t load trip detail.</div>';
                });
            }
            function dmRenderDetail(d) {
                var counts = '<div class="dm-counts">' +
                '<div class="dm-count"><div class="n">' + Number(d.passenger_count || 0) + '</div><div class="l">Dispatcher</div></div>' +
                '<div class="dm-count"><div class="n">' + Number(d.driver_count || 0) + '</div><div class="l">Driver</div></div>' +
                '<div class="dm-count"><div class="n">' + (d.inspector_count === null || d.inspector_count === undefined ? "n/a" : Number(d.inspector_count)) + '</div><div class="l">Inspector</div></div>' +
                '<div class="dm-count is-diff"><div class="n">' + Number(d.difference || 0) + '</div><div class="l">Difference</div></div>' +
                '</div>';

                var info = [
                ["Trip", "#" + d.trip_id + (d.trip_number ? " (" + d.trip_number + ")" : "")],
                ["Bus", d.bus || d.bus_id || "—"],
                ["Driver", d.driver || d.driver_id || "—"],
                ["Dispatcher", d.dispatcher || d.dispatcher_id || "—"],
                ["Route", d.route_name || "—"],
                ["Date / time", (d.trip_date || "") + " " + (d.dispatch_time || "")],
                ["Status", d.status || "—"],
                ["Review status", d.discrepancy_status || "—"],
                ["Count source", d.count_source || "—"]
                ];
                var grid = '<div class="dm-grid">' + info.map(function (pair) {
                    return '<div class="dm-cell"><div class="l">' + esc(pair[0]) + '</div><div class="v">' + esc(pair[1]) + '</div></div>';
                }).join("") + "</div>";

                var history = (d.history || []).map(function (h) {
                    var prev = h.previous_values ? ' <span class="t">from ' + esc(h.previous_values) + "</span>" : "";
                    var next = h.new_values ? ' <span class="t">to ' + esc(h.new_values) + "</span>" : "";
                    return '<div class="dm-history-row">' +
                    '<div class="a">' + esc(h.action || "") + "</div>" +
                    '<div>' + esc(h.description || "") + prev + next + "</div>" +
                    '<div class="t">' + esc(h.username || "") + " · " + esc(h.created_at || "") + "</div>" +
                    "</div>";
                }).join("");

                var resolve = "";
                if (d.discrepancy_status === "Requires Review") {
                    resolve = '<div class="dm-resolve">' +
                    '<input type="text" id="dmResolveReason" class="dm-input" placeholder="Reason for resolving this discrepancy">' +
                    '<button type="button" class="dm-btn" id="dmResolveBtn"><i class="bi bi-check2-circle"></i> Mark Reviewed / Resolved</button>' +
                    "</div>";
                }

                $("dmModalBody").innerHTML = grid + counts +
                '<h3 class="dm-title" style="font-size:.9rem;margin-top:10px;">Accountability history</h3>' +
                '<div class="dm-history-list">' + (history || '<div class="dm-empty">No entries for this trip.</div>') + "</div>" +
                resolve;

                var btn = $("dmResolveBtn");
                if (btn) {
                    btn.addEventListener("click", function () {
                        var reason = ($("dmResolveReason") && $("dmResolveReason").value || "").trim();
                        if (!reason) { alert("Please enter a reason."); return; }
                        btn.disabled = true;
                        fetch(DM_API + "?action=resolve", {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify({ trip_id: dmCurrentTrip, reason: reason })
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            if (!res || !res.success) throw new Error(res && res.message ? res.message : "failed");
                            dmOpenDetail(dmCurrentTrip);
                            dmLoadLive();
                        })
                        .catch (function (err) {
                            btn.disabled = false;
                            alert(err.message || "Couldn't resolve the discrepancy.");
                        });
                    });
                }
            }

            function dmInit() {
                if (!$("dispatcherMonitoringSection")) return;
                if ($("dmDate")) $("dmDate").value = today();
                dmLoadLive();
                if (dmTimer) clearInterval(dmTimer);
                dmTimer = setInterval(function () {
                    if (document.hidden) return;
                    if (window.umIsTabActive && !window.umIsTabActive("dispatcher")) return;
                    dmLoadLive();
                }, DM_INTERVAL);

                if ($("dmRefresh")) $("dmRefresh").addEventListener("click", dmLoadLive);
                $("dmApply").addEventListener("click", function () {
                    dmLoadLive();
                    if (!$("dmHistory").hidden) dmLoadHistory();
                });
                $("dmClear").addEventListener("click", function () {
                    ["dmDispatcher", "dmBus", "dmDriver", "dmStatus", "dmDiscrepancy"].forEach(function (id) {
                        if ($(id)) $(id).value = "";
                    });
                    dmLoadLive();
                });
                $("dmDate").addEventListener("change", dmLoadLive);
                $("dmToggleHistory").addEventListener("click", function () {
                    var box = $("dmHistory");
                    box.hidden = !box.hidden;
                    if (!box.hidden) dmLoadHistory();
                });
                $("dmModalClose").addEventListener("click", function () { $("dmModal").hidden = true; });
                $("dmModal").addEventListener("click", function (e) {
                    if (e.target === $("dmModal")) $("dmModal").hidden = true;
                });
                $("dmBody").addEventListener("click", function (e) {
                    var link = e.target.closest(".dm-link");
                    if (link) dmOpenDetail(Number(link.dataset.trip));
                });

                window.addEventListener("um:tab", function (e) {
                    if (e && e.detail && e.detail.tab === "dispatcher") dmLoadLive();
                });
                window.dmLoadLive = dmLoadLive;
                window.umLoaders = window.umLoaders || {};
                window.umLoaders.dispatcher = dmLoadLive;

                var params = new URLSearchParams(window.location.search);
                var discrepancyId = params.get("dispatcher_discrepancy");
                var tripId = params.get("dispatcher_trip");
                if (tripId) {
                    dmOpenDetail(Number(tripId));
                } else if (discrepancyId) {
                    fetch(DM_API + "?action=detail&audit_id=" + encodeURIComponent(discrepancyId), { cache: "no-store" })
                    .then(function (r) { return r.json(); })
                    .then(function (payload) {
                        if (payload && payload.success && payload.data) {
                            dmCurrentTrip = payload.data.trip_id;
                            $("dmModal").hidden = false;
                            $("dmModalTitle").textContent = "Trip #" + payload.data.trip_id + " · Dispatcher review";
                            dmRenderDetail(payload.data);
                        }
                    })
                    .catch (function () {});
                }
            }

            document.addEventListener("DOMContentLoaded", dmInit);
        })();
    </script>
</div>
