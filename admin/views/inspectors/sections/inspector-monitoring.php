<div class="im-section" id="inspectorMonitoringSection">
    <style>
        .im-section { font-family: 'Poppins', sans-serif; }
        .im-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
        .im-title { font-weight: 700; font-size: 1.05rem; color: #295738; margin: 0 0 2px; }
        .im-sub { font-size: .75rem; color: #8a9a8a; margin: 0; }
        .im-input, .im-select { border: 1px solid #cdd9b5; border-radius: 8px; background: #fef8f3; font-family: 'Poppins', sans-serif; font-size: .75rem; color: #2e4836; padding: 6px 9px; }
        .im-btn { border: 1px solid #295738; background: #295738; color: #fff; border-radius: 8px; font-size: .72rem; font-weight: 600; padding: 6px 12px; cursor: pointer; }
        .im-btn:hover { background: #3c6e4f; }
        .im-btn-ghost { background: #f9f4da; color: #2e4836; border-color: #cdd9b5; }
        .im-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 14px; }
        .im-stat { background: #fff; border: 1px solid #e5e0a4; border-radius: 12px; padding: 10px 12px; }
        .im-stat .l { font-size: .62rem; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; color: #8a9a8a; }
        .im-stat .v { font-size: 1.35rem; font-weight: 800; color: #295738; line-height: 1.2; }
        .im-stat.is-alert .v { color: #b3261e; }
        .im-filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 12px; }
        .im-table-wrap { overflow-x: auto; border: 1px solid #eee7d2; border-radius: 12px; background: #fff; }
        .im-table { width: 100%; border-collapse: collapse; font-size: .72rem; color: #2e4836; }
        .im-table th { text-align: left; padding: 9px 10px; background: #f9f4da; font-size: .62rem; text-transform: uppercase; letter-spacing: .5px; color: #6f7d6f; white-space: nowrap; }
        .im-table td { padding: 9px 10px; border-top: 1px solid #f1ecdd; white-space: nowrap; }
        .im-table tr:hover td { background: #f9f6ea; }
        .im-badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 2px 9px; font-size: .64rem; font-weight: 700; }
        .im-badge.is-validated { background: #e4f0e2; color: #1e5b3a; }
        .im-badge.is-review { background: #fbe7e4; color: #a32018; }
        .im-badge.is-unverified { background: #fdf6dd; color: #7a6a12; }
        .im-link { color: #2563eb; font-weight: 600; cursor: pointer; text-decoration: none; }
        .im-link:hover { text-decoration: underline; }
        .im-empty { padding: 18px; text-align: center; color: #8a9a8a; font-size: .76rem; }
        .im-modal { position: fixed; inset: 0; z-index: 1080; background: rgba(20, 32, 22, .45); display: flex; align-items: center; justify-content: center; padding: 16px; }
        .im-modal[hidden] { display: none; }
        .im-modal-card { background: #fffdf8; border-radius: 16px; border: 1px solid #cdd9b5; width: 100%; max-width: 860px; max-height: 88vh; overflow-y: auto; overflow-x: hidden; padding: 18px 20px; }
        .im-modal-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .im-detail-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid #f1ecdd; border-radius: 10px; background: #fff; }
        .im-detail-table { width: 100%; min-width: 800px; border-collapse: collapse; font-size: .72rem; color: #2e4836; table-layout: auto; }
        .im-detail-table th { text-align: left; padding: 8px 10px; background: #f9f4da; font-size: .6rem; text-transform: uppercase; letter-spacing: .4px; color: #6f7d6f; white-space: nowrap; }
        .im-detail-table td { padding: 8px 10px; border-top: 1px solid #f1ecdd; vertical-align: top; overflow-wrap: break-word; word-break: break-word; }
        .im-detail-table tbody tr:nth-child(even) td { background: #fdfbf4; }
        .im-detail-table tbody tr:hover td { background: #f9f6ea; }
        .im-detail-table .im-badge { white-space: nowrap; }
        .im-detail-table td.col-passenger { min-width: 150px; }
        .im-detail-table td.col-type { min-width: 64px; }
        .im-detail-table td.col-route { min-width: 170px; }
        .im-detail-table td.col-fare { min-width: 68px; white-space: nowrap; }
        .im-detail-table td.col-payment { min-width: 90px; }
        .im-detail-table td.col-validation { min-width: 100px; }
        .im-detail-table td.col-verified { min-width: 110px; }
        .im-detail-table td.reasons { min-width: 180px; max-width: 240px; white-space: normal; }
        .im-detail-table td.checklist { min-width: 180px; max-width: 240px; white-space: normal; }
    </style>

    <div class="im-head">
        <div>
            <h3 class="im-title"><i class="bi bi-clipboard2-check"></i> Inspector Monitoring</h3>
            <p class="im-sub">Passenger validation summaries and exceptions from inspector inspection records</p>
        </div>
    </div>

    <div class="im-stats" id="imStats"></div>

    <div class="im-filters" id="imFilters">
        <input type="date" id="imDate" class="im-input" title="Inspection date">
        <select id="imInspector" class="im-select"><option value="">All inspectors</option></select>
        <select id="imBus" class="im-select"><option value="">All buses</option></select>
        <select id="imDriver" class="im-select"><option value="">All drivers</option></select>
        <select id="imTrip" class="im-select"><option value="">All trips</option></select>
        <select id="imStatus" class="im-select">
            <option value="">All statuses</option>
            <option value="VALIDATED">Validated</option>
            <option value="UNVERIFIED">Unverified</option>
            <option value="NEEDS REVIEW">Needs Review</option>
        </select>
        <button type="button" id="imApply" class="im-btn im-btn-ghost">Apply</button>
        <button type="button" id="imClear" class="im-btn im-btn-ghost">Clear</button>
    </div>

    <div class="im-table-wrap">
        <table class="im-table">
            <thead>
                <tr>
                    <th>Inspector</th><th>Trip</th><th>Bus</th><th>Passengers</th><th>Validated</th>
                    <th>Needs Review</th><th>Payment Disc.</th><th>Detail Disc.</th>
                    <th>Inspection Status</th><th>Last Verification</th><th></th>
                </tr>
            </thead>
            <tbody id="imBody">
                <tr><td colspan="11" class="im-empty">Loading inspector monitoring…</td></tr>
            </tbody>
        </table>
    </div>

    <div class="im-modal" id="imModal" hidden>
        <div class="im-modal-card">
            <div class="im-modal-head">
                <h3 class="im-title" id="imModalTitle">Inspection details</h3>
                <button type="button" class="im-btn im-btn-ghost" id="imModalClose"><i class="bi bi-x-lg"></i></button>
            </div>
            <div id="imModalBody"><div class="im-empty">Loading…</div></div>
        </div>
    </div>

    <script>
        (function () {
            "use strict";

            var IM_API = "/admin/api/Inspectors/InspectorsApi.php";
            var imTimer = null;
            var imFiltersLoaded = false;

            function $(id) { return document.getElementById(id); }
            function esc(value) {
                return String(value === null || value === undefined ? "" : value)
                .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
            }
            function statusBadge(status) {
                var s = String(status || "").toLowerCase();
                if (s === "validated") return "is-validated";
                if (s === "needs review") return "is-review";
                return "is-unverified";
            }
            function imQuery(extra) {
                var params = new URLSearchParams();
                ["date", "inspector", "bus", "driver", "trip", "status"].forEach(function (key) {
                    var el = $(key === "date" ? "imDate" : "im" + key.charAt(0).toUpperCase() + key.slice(1));
                    if (el && el.value) params.set(key, el.value);
                });
                if (extra) Object.keys(extra).forEach(function (k) { params.set(k, extra[k]); });
                return params.toString();
            }
            function imFillOptions(data) {
                if (imFiltersLoaded || !data) return;
                imFiltersLoaded = true;
                (data.inspectors || []).forEach(function (d) {
                    var o = document.createElement("option");
                    o.value = d.id; o.textContent = d.name || d.id;
                    $("imInspector").appendChild(o);
                });
                (data.buses || []).forEach(function (b) {
                    var o = document.createElement("option");
                    o.value = b; o.textContent = b;
                    $("imBus").appendChild(o);
                });
                (data.drivers || []).forEach(function (d) {
                    var o = document.createElement("option");
                    o.value = d; o.textContent = d;
                    $("imDriver").appendChild(o);
                });
                (data.trips || []).forEach(function (t) {
                    var o = document.createElement("option");
                    o.value = t; o.textContent = "TRIP-" + t;
                    $("imTrip").appendChild(o);
                });
            }
            function imRenderStats(totals) {
                var t = totals || {};
                var cards = [
                { l: "Inspector Groups", v: t.groups || 0, alert: false },
                { l: "Passengers", v: t.passengers || 0, alert: false },
                { l: "Validated", v: t.validated || 0, alert: false },
                { l: "Needs Review", v: t.needs_review || 0, alert: (t.needs_review || 0) > 0 },
                { l: "Payment Disc.", v: t.payment_discrepancies || 0, alert: (t.payment_discrepancies || 0) > 0 },
                { l: "Detail Disc.", v: t.detail_discrepancies || 0, alert: (t.detail_discrepancies || 0) > 0 }
                ];
                $("imStats").innerHTML = cards.map(function (c) {
                    return '<div class="im-stat' + (c.alert ? ' is-alert' : '') + '"><div class="l">' + c.l + '</div><div class="v">' + Number(c.v).toLocaleString() + '</div></div>';
                }).join("");
            }
            function imRenderRows(rows) {
                var body = $("imBody");
                if (!rows || !rows.length) {
                    body.innerHTML = '<tr><td colspan="11" class="im-empty">No inspector validation records for the selected filters.</td></tr>';
                    return;
                }
                body.innerHTML = rows.map(function (r) {
                    return "<tr>" +
                    "<td>" + esc(r.inspector || "—") + "</td>" +
                    "<td>" + esc(r.trip_label || "—") + "</td>" +
                    "<td>" + esc(r.bus_id || "—") + "</td>" +
                    "<td>" + Number(r.passenger_count || 0) + "</td>" +
                    "<td>" + Number(r.validated_count || 0) + "</td>" +
                    "<td>" + Number(r.needs_review_count || 0) + "</td>" +
                    "<td>" + Number(r.payment_discrepancies || 0) + "</td>" +
                    "<td>" + Number(r.detail_discrepancies || 0) + "</td>" +
                    '<td><span class="im-badge ' + statusBadge(r.inspection_status) + '">' + esc(r.inspection_status || "—") + "</span></td>" +
                    "<td>" + esc(r.last_verification || "—") + "</td>" +
                    '<td><a class="im-link" data-group="' + esc(r.group_key) + '">View Details</a></td>' +
                    "</tr>";
                }).join("");
            }
            function imLoadFilters() {
                fetch(IM_API + "?action=inspector_monitoring_filters", { cache: "no-store" })
                .then(function (r) { return r.json(); })
                .then(function (payload) { if (payload && payload.success) imFillOptions(payload); })
                .catch (function () {});
            }
            function imSetUpdated(ts) {
                if (typeof window.umSetUpdated === "function") {
                    window.umSetUpdated(ts);
                }
            }
            function imLoad() {
                fetch(IM_API + "?action=inspector_monitoring&" + imQuery(), { cache: "no-store" })
                .then(function (r) { return r.json(); })
                .then(function (payload) {
                    if (!payload || !payload.success) throw new Error("failed");
                    if (payload.available === false) {
                        imRenderStats({});
                        $("imBody").innerHTML = '<tr><td colspan="11" class="im-empty">' + esc(payload.message || "Inspector inspection records are unavailable.") + "</td></tr>";
                        imSetUpdated(payload.generated_at || new Date().toLocaleTimeString());
                        return;
                    }
                    imRenderStats(payload.totals);
                    imRenderRows(payload.rows);
                    imSetUpdated(payload.generated_at || new Date().toLocaleTimeString());
                })
                .catch (function () {
                    $("imBody").innerHTML = '<tr><td colspan="11" class="im-empty">Couldn\'t load inspector monitoring. Select filters and retry.</td></tr>';
                });
            }
            function imRenderDetail(data) {
                var rows = data.rows || [];
                if (!rows.length) {
                    $("imModalBody").innerHTML = '<div class="im-empty">No passenger-level records for this group.</div>';
                    return;
                }
                var head = '<div class="im-empty" style="padding:0 0 10px;text-align:left;">' +
                esc(rows[0].inspector_name || "Inspector") + " · " +
                esc(rows[0].trip_id !== "" ? ("TRIP-" + rows[0].trip_id) : "Trip unavailable") + " · " +
                esc(rows[0].bus_id || "Bus unavailable") +
                "</div>";
                var body = rows.map(function (r) {
                    return "<tr>" +
                    '<td class="col-passenger">' + esc(r.passenger_name || "—") + '<div style="color:#8a9389;">' + esc(r.passenger_code || r.passenger_id || "") + "</div></td>" +
                    '<td class="col-type">' + esc(r.passenger_type || "—") + "</td>" +
                    '<td class="col-route">' + esc(r.boarding_point || "—") + " → " + esc(r.destination || "—") + "</td>" +
                    '<td class="col-fare">' + (r.fare === null || r.fare === undefined ? "—" : "₱" + Number(r.fare).toFixed(2)) + "</td>" +
                    '<td class="col-payment">' + esc(r.payment_status || "—") + "</td>" +
                    '<td class="col-validation"><span class="im-badge ' + statusBadge(r.validation_status) + '">' + esc(r.validation_status || "—") + "</span></td>" +
                    '<td class="reasons">' + esc(r.discrepancy_reasons || "—") + "</td>" +
                    '<td class="checklist">' + esc(r.checklist_results || "—") + "</td>" +
                    '<td class="col-verified">' + esc(r.verified_at || r.created_at || "—") + "</td>" +
                    "</tr>";
                }).join("");
                $("imModalBody").innerHTML = head +
                '<div class="im-detail-wrap">' +
                '<table class="im-detail-table"><thead><tr>' +
                "<th>Passenger</th><th>Type</th><th>Boarding → Destination</th><th>Fare</th><th>Payment</th>" +
                "<th>Validation</th><th>Discrepancy Reasons</th><th>Checklist Results</th><th>Verified At</th>" +
                "</tr></thead><tbody>" + body + "</tbody></table>" +
                "</div>";
            }
            function imOpenDetail(groupKey, inspectionId) {
                $("imModal").hidden = false;
                $("imModalTitle").textContent = "Inspection details";
                $("imModalBody").innerHTML = '<div class="im-empty">Loading…</div>';
                var params = new URLSearchParams();
                params.set("action", "inspector_monitoring_detail");
                if (inspectionId) { params.set("inspection_id", inspectionId); }
                else if (groupKey) { params.set("group_key", groupKey); }
                fetch(IM_API + "?" + params.toString(), { cache: "no-store" })
                .then(function (r) { return r.json(); })
                .then(function (payload) {
                    if (!payload || !payload.success) throw new Error("failed");
                    if (payload.available === false) {
                        $("imModalBody").innerHTML = '<div class="im-empty">' + esc(payload.message || "Inspection records unavailable.") + "</div>";
                        return;
                    }
                    imRenderDetail(payload);
                })
                .catch (function () {
                    $("imModalBody").innerHTML = '<div class="im-empty">Couldn\'t load inspection details.</div>';
                });
            }

            function imInit() {
                if (!$("inspectorMonitoringSection")) return;
                imLoadFilters();
                imLoad();

                if (imTimer) clearInterval(imTimer);
                imTimer = setInterval(function () {
                    if (document.hidden) return;
                    if (window.umIsTabActive && !window.umIsTabActive("inspector")) return;
                    imLoad();
                }, 15000);

                $("imApply").addEventListener("click", imLoad);
                $("imClear").addEventListener("click", function () {
                    ["imDate", "imInspector", "imBus", "imDriver", "imTrip", "imStatus"].forEach(function (id) {
                        if ($(id)) $(id).value = "";
                    });
                    imLoad();
                });
                $("imDate").addEventListener("change", imLoad);
                $("imBody").addEventListener("click", function (e) {
                    var link = e.target.closest(".im-link");
                    if (link) imOpenDetail(link.dataset.group, "");
                });
                $("imModalClose").addEventListener("click", function () { $("imModal").hidden = true; });
                $("imModal").addEventListener("click", function (e) {
                    if (e.target === $("imModal")) $("imModal").hidden = true;
                });

                window.addEventListener("um:tab", function (e) {
                    if (e && e.detail && e.detail.tab === "inspector") imLoad();
                });

                var params = new URLSearchParams(window.location.search);
                var item = params.get("inspector_item");
                if (item) imOpenDetail("", item);

                window.imLoad = imLoad;
                window.umLoaders = window.umLoaders || {};
                window.umLoaders.inspector = imLoad;
            }

            document.addEventListener("DOMContentLoaded", imInit);
        })();
    </script>
</div>
