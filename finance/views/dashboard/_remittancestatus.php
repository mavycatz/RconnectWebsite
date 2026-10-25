<div class="fin-panel" id="remittanceStatusPanel">
    <div class="fin-panel-head">
        <div>
            <h2 class="fin-panel-title"><i class="bi bi-pie-chart"></i> Remittance Status Overview</h2>
            <p class="fin-panel-sub">Current state of remittances for the selected period</p>
        </div>
        <div class="fin-panel-meta">
            <span class="fin-pill" id="rsTotalPill"></span>
        </div>
    </div>

    <div class="rs-list" id="rsList">
        <div class="rs-loading">Loading remittance status...</div>
    </div>
</div>

<style>
#remittanceStatusPanel .fin-panel-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
#remittanceStatusPanel .fin-panel-title {
    font-size: 1rem;
    font-weight: 700;
    color: #2e4836;
    margin: 0 0 2px;
}
#remittanceStatusPanel .fin-panel-title i {
    color: #295738;
    margin-right: 6px;
}
#remittanceStatusPanel .fin-panel-sub {
    font-size: 0.72rem;
    color: #8a9389;
    margin: 0;
}
#remittanceStatusPanel .fin-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 999px;
    padding: 4px 12px;
    font-size: 0.7rem;
    font-weight: 700;
    background: #f9f4da;
    color: #2e4836;
    border: 1px solid #cdd9b5;
}
#remittanceStatusPanel .fin-pill:empty {
    display: none;
}

.rs-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.rs-loading,
.rs-empty {
    padding: 18px;
    text-align: center;
    font-size: 0.78rem;
    color: #8a9389;
}
.rs-empty i {
    display: block;
    font-size: 1.5rem;
    margin-bottom: 6px;
    color: #b0b9a8;
}

.rs-row-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 10px;
}
.rs-row-label {
    font-size: 0.76rem;
    font-weight: 700;
    color: #2e4836;
    display: inline-flex;
    align-items: center;
    gap: 7px;
}
.rs-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    display: inline-block;
}
.rs-row-count {
    font-size: 0.7rem;
    color: #8a9389;
    font-weight: 600;
}
.rs-row-amount {
    font-size: 0.74rem;
    font-weight: 800;
    color: #2e4836;
    font-variant-numeric: tabular-nums;
}
.rs-track {
    height: 8px;
    border-radius: 999px;
    background: #eee7d2;
    overflow: hidden;
    margin-top: 6px;
}
.rs-fill {
    display: block;
    height: 100%;
    border-radius: 999px;
}
.rs-fill.is-completed {
    background: #2e7d32;
}
.rs-fill.is-pending {
    background: #c4b417;
}
.rs-fill.is-discrepancy {
    background: #b3261e;
}
.rs-fill.is-none {
    background: #b0b9a8;
}
</style>

<script>
(function () {
    "use strict";

    var requestSeq = 0;

    var STATUS_COLORS = {
        completed: "#2e7d32",
        pending: "#c4b417",
        discrepancy: "#b3261e",
        no_remit: "#8a9389",
    };

    function money(value) {
        return (
            "₱" +
            Number(value || 0).toLocaleString("en-US", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })
        );
    }

    function render(data) {
        var list = document.getElementById("rsList");
        var pill = document.getElementById("rsTotalPill");
        if (!list) {
            return;
        }

        var statuses = (data && data.status) || [];
        var totalCount = statuses.reduce(function (sum, s) {
            return sum + Number(s.count || 0);
        }, 0);
        var totalAmount = statuses.reduce(function (sum, s) {
            return sum + Number(s.amount || 0);
        }, 0);

        if (pill) {
            pill.textContent =
                totalCount > 0
                    ? totalCount.toLocaleString("en-US") +
                      " record" +
                      (totalCount === 1 ? "" : "s") +
                      " · " +
                      money(totalAmount)
                    : "";
        }

        list.innerHTML = "";

        if (totalCount === 0) {
            list.innerHTML =
                '<div class="rs-empty"><i class="bi bi-inbox"></i>No remittance activity for this period.</div>';
            return;
        }

        statuses.forEach(function (status) {
            var key = String(status.key || "").toLowerCase();
            var count = Number(status.count || 0);
            var amount = Number(status.amount || 0);
            var pct = totalCount > 0 ? (count / totalCount) * 100 : 0;
            var color = STATUS_COLORS[key] || "#8a9389";

            var row = document.createElement("div");
            row.className = "rs-row";
            row.innerHTML =
                '<div class="rs-row-head">' +
                '<span class="rs-row-label"><span class="rs-dot" style="background:' +
                color +
                ';"></span>' +
                escapeHtml(status.label || key) +
                "</span>" +
                '<span class="rs-row-count">' +
                count.toLocaleString("en-US") +
                " record" +
                (count === 1 ? "" : "s") +
                "</span>" +
                '<span class="rs-row-amount">' +
                money(amount) +
                "</span>" +
                "</div>" +
                '<div class="rs-track"><span class="rs-fill is-' +
                (key === "no_remit" ? "none" : key) +
                '" style="width:' +
                pct.toFixed(1) +
                '%;"></span></div>';
            list.appendChild(row);
        });

        var total = document.createElement("div");
        total.className = "rs-row-head";
        total.style.marginTop = "2px";
        total.innerHTML =
            '<span class="rs-row-label">Total</span>' +
            '<span class="rs-row-amount">' +
            totalCount.toLocaleString("en-US") +
            " · " +
            money(totalAmount) +
            "</span>";
        list.appendChild(total);
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function renderError() {
        var list = document.getElementById("rsList");
        if (list) {
            list.innerHTML =
                '<div class="rs-empty"><i class="bi bi-exclamation-circle"></i>Couldn\'t load remittance status. Select a period to retry.</div>';
        }
        var pill = document.getElementById("rsTotalPill");
        if (pill) {
            pill.textContent = "";
        }
    }

    function loadRemittanceStatus(query) {
        var requestId = ++requestSeq;

        if (window.__dashLoadBegin) window.__dashLoadBegin();

        fetch("/finance/api/dashboard/finance_summary.php?" + (query || ""), { cache: "no-store" })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                if (requestId !== requestSeq) {
                    return;
                }
                if (!data || !data.success) {
                    renderError();
                    return;
                }
                render(data);
            })
            .catch(function () {
                if (requestId !== requestSeq) {
                    return;
                }
                renderError();
            })
            .finally(function () {
                if (window.__dashLoadEnd) window.__dashLoadEnd();
            });
    }

    window.loadRemittanceStatus = loadRemittanceStatus;

    document.addEventListener("DOMContentLoaded", function () {
        if (!window.__financeFilterQuery) {
            loadRemittanceStatus("range=year");
        }
    });
})();
</script>
