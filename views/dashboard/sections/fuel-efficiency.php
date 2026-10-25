<div class="dash-panel" id="fuelEfficiencyPanel">
    <div class="dash-panel-head">
        <div>
            <h2 class="dash-panel-title"><i class="bi bi-fuel-pump"></i> Fuel Consumption</h2>
            <p class="dash-panel-sub">Per-bus km/L from ODO and KPL records · "vs fleet" is the gap to the fleet average</p>
        </div>
    </div>

    <div id="fuelEfficiencyBody">
        <div class="fuel-loading">Loading fuel consumption...</div>
    </div>
</div>

<style>
    .fuel-fleet {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 8px 12px;
        border: 1px solid #cdd9b5;
        border-radius: 12px;
        background: linear-gradient(135deg, #f9f4da 0%, #fef8f3 60%);
        margin-bottom: 10px;
    }

    .fuel-fleet-label {
        font-size: 0.58rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #6f7d6f;
        margin-bottom: 2px;
    }

    .fuel-fleet-value {
        font-size: 1.5rem;
        font-weight: 800;
        color: #2e4836;
        line-height: 1;
    }

    .fuel-fleet-unit {
        font-size: 0.72rem;
        font-weight: 700;
        color: #6f7d6f;
        margin-left: 4px;
    }

    .fuel-fleet-icon {
        flex: 0 0 auto;
        width: 34px;
        height: 34px;
        border-radius: 11px;
        background: #295738;
        color: #f9f4da;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .fuel-rank {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #cdd9b5 transparent;
    }

    .fuel-rank::-webkit-scrollbar {
        width: 6px;
    }

    .fuel-rank::-webkit-scrollbar-thumb {
        background: #cdd9b5;
        border-radius: 999px;
    }

    #dashOpsRow #fuelEfficiencyBody {
        min-height: 0;
    }

    #fuelEfficiencyBody > .fuel-fleet,
    #fuelEfficiencyBody > .fuel-rank-title,
    #fuelEfficiencyBody > .fuel-rank-head,
    #fuelEfficiencyBody > .fuel-rank-note {
        flex: 0 0 auto;
    }

    .fuel-rank-title {
        font-size: 0.6rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #6f7d6f;
        margin: 0 0 4px;
    }

    .fuel-rank-head,
    .fuel-rank-row {
        display: grid;
        grid-template-columns: 22px minmax(0, 1fr) 56px 68px;
        align-items: center;
        gap: 6px;
    }

    .fuel-rank-head {
        padding: 0 4px 4px;
        font-size: 0.56rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #8a9389;
        border-bottom: 1px solid #e6dfc9;
    }

    .fuel-rank-head .fuel-rank-kmpl,
    .fuel-rank-head .fuel-rank-delta-head {
        text-align: right;
    }

    .fuel-rank-row {
        padding: 3px 4px;
        border-bottom: 1px solid #f1ecdd;
        border-radius: 7px;
        font-size: 0.68rem;
        color: #2e4836;
        transition: background 0.15s ease;
    }

    .fuel-rank-row:hover {
        background: #f9f6ea;
    }

    .fuel-rank-row:last-child {
        border-bottom: none;
    }

    .fuel-rank-pos {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        justify-self: center;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #f1ecdd;
        color: #6f7d6f;
        font-size: 0.56rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    .fuel-rank-row.is-first .fuel-rank-pos {
        background: #295738;
        color: #f9f4da;
    }

    .fuel-rank-row.is-top .fuel-rank-pos {
        background: #cdd9b5;
        color: #2e4836;
    }

    .fuel-rank-name {
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .fuel-rank-kmpl {
        text-align: right;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    .fuel-rank-delta {
        justify-self: end;
        min-width: 50px;
        padding: 1px 7px;
        border-radius: 999px;
        text-align: center;
        font-size: 0.6rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    .fuel-rank-delta.is-up {
        background: #e4f0e2;
        color: #1e5b3a;
    }

    .fuel-rank-delta.is-down {
        background: #fbe7e4;
        color: #a32018;
    }

    .fuel-rank-delta.is-flat {
        background: #f1ecdd;
        color: #6f7d6f;
    }

    .fuel-rank-note {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 8px;
        padding: 6px 9px;
        border: 1px solid #e8ddb0;
        border-radius: 9px;
        background: #fdf6dd;
        font-size: 0.64rem;
        font-weight: 600;
        color: #7a6a12;
    }

    .fuel-rank-note .fuel-note-value {
        margin-left: auto;
        font-variant-numeric: tabular-nums;
    }

    .fuel-loading,
    .fuel-empty {
        padding: 18px;
        text-align: center;
        font-size: 0.76rem;
        color: #8a9389;
    }

    .fuel-empty i {
        font-size: 1.6rem;
        color: #8a9389;
    }

    .fuel-empty.is-error i {
        color: #b3261e;
    }

    @media(max-width:575px) {
        .fuel-rank-head,
        .fuel-rank-row {
            grid-template-columns: 20px minmax(0, 1fr) 48px 60px;
            gap: 5px;
        }

        .fuel-rank-delta {
            min-width: 46px;
            padding: 1px 5px;
        }
    }
</style>

<script>
    (function () {
        "use strict";

        var requestSeq = 0;

        function deltaText(kmpl, fleet) {
            var d = Number(kmpl) - fleet;
            return (d >= 0 ? '+' : '-') + Math.abs(d).toFixed(2);
        }

        function rowHtml(bus, rank, fleet) {
            var kmpl = Number(bus.kmpl);
            var d = kmpl - fleet;
            var deltaClass = d > 0.004 ? 'is-up' : (d < -0.004 ? 'is-down' : 'is-flat');
            var rankClass = rank === 1 ? ' is-first' : (rank <= 3 ? ' is-top' : '');

            return '<div class="fuel-rank-row' + rankClass + '">' +
            '<span class="fuel-rank-pos">' + rank + '</span>' +
            '<span class="fuel-rank-name" title="' + bus.bus_id + '">' + bus.bus_id + '</span>' +
            '<span class="fuel-rank-kmpl">' + kmpl.toFixed(2) + '</span>' +
            '<span class="fuel-rank-delta ' + deltaClass + '">' + deltaText(kmpl, fleet) + '</span>' +
            '</div>';
        }

        function render(data) {
            var body = document.getElementById("fuelEfficiencyBody");
            if (!body) {
                return;
            }

            var buses = data.buses || [];
            var fleet = Number(data.fleet_kmpl || 0);

            if (buses.length === 0 || fleet <= 0) {
                body.innerHTML = '<div class="fuel-empty"><i class="bi bi-fuel-pump"></i>No fuel data available for this period.</div>';
                return;
            }

            var top = buses.slice(0, 10);
            var rows = top.map(function (bus, index) {
                return rowHtml(bus, index + 1, fleet);
            }).join("");

            var listTitle = top.length < buses.length
            ? 'Top ' + top.length + ' of ' + buses.length + ' buses by consumption'
            : 'Bus consumption ranking';

            var lowest = data.lowest;
            var note = '';
            if (lowest && lowest.bus_id && !top.some(function (bus) { return bus.bus_id === lowest.bus_id; })) {
                note = '<div class="fuel-rank-note">' +
                '<i class="bi bi-exclamation-triangle"></i>' +
                '<span>Highest consumption: ' + lowest.bus_id + '</span>' +
                '<span class="fuel-note-value">' + Number(lowest.kmpl).toFixed(2) + ' km/L (' + deltaText(lowest.kmpl, fleet) + ')</span>' +
                '</div>';
            }

            body.innerHTML =
            '<div class="fuel-fleet">' +
            '<div>' +
            '<div class="fuel-fleet-label">Fleet average</div>' +
            '<div><span class="fuel-fleet-value">' + fleet.toFixed(2) + '</span><span class="fuel-fleet-unit">km/L</span></div>' +
            '</div>' +
            '<div class="fuel-fleet-icon"><i class="bi bi-fuel-pump-fill"></i></div>' +
            '</div>' +
            '<div class="fuel-rank-title">' + listTitle + '</div>' +
            '<div class="fuel-rank-head"><span>#</span><span>Bus</span><span class="fuel-rank-kmpl">km/L</span><span class="fuel-rank-delta-head">vs fleet</span></div>' +
            '<div class="fuel-rank">' + rows + '</div>' +
            note;
        }

        function renderError() {
            var body = document.getElementById("fuelEfficiencyBody");
            if (body) {
                body.innerHTML = '<div class="fuel-empty is-error"><i class="bi bi-exclamation-circle"></i>Couldn\'t load fuel consumption.</div>';
            }
        }

        function loadFuelEfficiency(query) {
            var requestId = ++requestSeq;

            if (window.__dashLoadBegin) {
                window.__dashLoadBegin();
            }

            fetch("/admin/api/dashboard/fuel-efficiency.php?" + (query || ""), { cache: "no-store" })
            .then(function (res) { return res.json(); })
            .then(function (payload) {
                if (requestId !== requestSeq) {
                    return;
                }

                if (!payload || !payload.success) {
                    renderError();
                    return;
                }

                render(payload.data || {});
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

        window.loadFuelEfficiency = loadFuelEfficiency;

        document.addEventListener("DOMContentLoaded", function () {
            if (!window.adminFilterQuery) {
                loadFuelEfficiency("");
            }
        });
    })();
</script>
