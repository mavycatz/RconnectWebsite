<?php
$drvViewAllUrl = isset($finUrl) ? $finUrl('remittance') : '/finance/remittance.php';
?>
<div class="fin-panel" id="driverRemittancePanel">
    <div class="fin-panel-head">
        <div>
            <h2 class="fin-panel-title"><i class="bi bi-person-check"></i> Driver Performance Analytics</h2>
            <p class="fin-panel-sub">Top drivers by remittance compliance for the selected period</p>
        </div>
        <a class="fin-panel-link" href="<?= htmlspecialchars($drvViewAllUrl) ?>">
            View All <i class="bi bi-box-arrow-up-right"></i>
        </a>
    </div>

    <div class="drv-list" id="drvList">
        <div class="drv-loading">Loading driver remittances...</div>
    </div>
</div>

<style>
#driverRemittancePanel .fin-panel-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
#driverRemittancePanel .fin-panel-title {
    font-size: 1rem;
    font-weight: 700;
    color: #2e4836;
    margin: 0 0 2px;
}
#driverRemittancePanel .fin-panel-title i {
    color: #295738;
    margin-right: 6px;
}
#driverRemittancePanel .fin-panel-sub {
    font-size: 0.72rem;
    color: #8a9389;
    margin: 0;
}
#driverRemittancePanel .fin-panel-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.74rem;
    font-weight: 700;
    color: #295738;
    text-decoration: none;
    border: 1px solid #cdd9b5;
    background: #f9f4da;
    border-radius: 999px;
    padding: 5px 14px;
    white-space: nowrap;
}
#driverRemittancePanel .fin-panel-link:hover {
    background: #efe9c9;
}

.drv-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.drv-loading,
.drv-empty {
    padding: 18px;
    text-align: center;
    font-size: 0.78rem;
    color: #8a9389;
}
.drv-empty i {
    display: block;
    font-size: 1.5rem;
    margin-bottom: 6px;
    color: #b0b9a8;
}

.drv-row {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #fef8f3;
    border: 1px solid #eae3d4;
    border-radius: 12px;
    padding: 10px 12px;
}
.drv-rank {
    width: 30px;
    height: 30px;
    min-width: 30px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f9f4da;
    color: #2e4836;
    font-size: 0.78rem;
    font-weight: 800;
}
.drv-row.is-top .drv-rank {
    background: #2e4836;
    color: #f9f4da;
}
.drv-main {
    flex: 1;
    min-width: 0;
}
.drv-name {
    font-size: 0.8rem;
    font-weight: 700;
    color: #2e4836;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.drv-id {
    font-size: 0.64rem;
    font-weight: 600;
    color: #8a9389;
    margin-left: 6px;
}
.drv-meta {
    font-size: 0.68rem;
    color: #8a9389;
    margin-top: 2px;
}
.drv-bar {
    height: 6px;
    border-radius: 999px;
    background: #eee7d2;
    overflow: hidden;
    margin-top: 6px;
}
.drv-bar span {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: #295738;
}
.drv-row.is-pending .drv-bar span {
    background: #c4b417;
}
.drv-row.is-discrepancy .drv-bar span {
    background: #b3261e;
}
.drv-right {
    text-align: right;
    min-width: 108px;
}
.drv-status {
    display: inline-block;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    border-radius: 999px;
    padding: 3px 10px;
}
.drv-status.is-completed {
    background: #eef8f0;
    color: #1c7c3f;
    border: 1px solid #bfe3c6;
}
.drv-status.is-pending {
    background: #fdf7e3;
    color: #8a7b0a;
    border: 1px solid #e5d79a;
}
.drv-status.is-discrepancy {
    background: #fdf1f0;
    color: #b3261e;
    border: 1px solid #f0c8c4;
}
.drv-status.is-none {
    background: #f4f4ef;
    color: #7c8a7c;
    border: 1px solid #e2e2d8;
}
.drv-due {
    font-size: 0.68rem;
    font-weight: 700;
    color: #8a7b0a;
    margin-top: 4px;
}
.drv-due.is-clear {
    color: #8a9389;
    font-weight: 600;
}

@media (max-width: 575px) {
    .drv-right {
        min-width: 92px;
    }
    .drv-meta {
        white-space: normal;
    }
}
</style>

<script>
(function () {
    "use strict";

    var requestSeq = 0;

    function money(value) {
        return (
            "₱" +
            Number(value || 0).toLocaleString("en-US", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })
        );
    }

    function statusClass(status) {
        switch (String(status || "").toLowerCase()) {
            case "completed":
                return "is-completed";
            case "pending":
                return "is-pending";
            case "discrepancy":
                return "is-discrepancy";
            default:
                return "is-none";
        }
    }

    function render(drivers, driverCount) {
        var list = document.getElementById("drvList");
        if (!list) {
            return;
        }

        var top = (drivers || []).slice(0, 5);

        if (top.length === 0) {
            list.innerHTML =
                '<div class="drv-empty"><i class="bi bi-people"></i>No driver remittance activity for this period.</div>';
            return;
        }

        list.innerHTML = "";
        top.forEach(function (driver, index) {
            var pending = Number(driver.pending || 0);
            var completed = Number(driver.completed || 0);
            var trips = Number(driver.trips || 0);
            var compliance = Math.max(0, Math.min(100, Number(driver.compliance || 0)));

            var row = document.createElement("div");
            row.className =
                "drv-row" +
                (index === 0 ? " is-top" : "") +
                (driver.status === "Pending" ? " is-pending" : "") +
                (driver.status === "Discrepancy" ? " is-discrepancy" : "");

            var meta =
                trips +
                " trip" +
                (trips === 1 ? "" : "s") +
                " · " +
                completed +
                " completed" +
                " · " +
                pending +
                " pending";

            row.innerHTML =
                '<div class="drv-rank">' +
                (index + 1) +
                "</div>" +
                '<div class="drv-main">' +
                '<div class="drv-name">' +
                escapeHtml(driver.name || "Unknown driver") +
                '<span class="drv-id">' +
                escapeHtml(driver.driver_id || "") +
                "</span></div>" +
                '<div class="drv-meta">' +
                meta +
                "</div>" +
                '<div class="drv-bar"><span style="width:' +
                compliance.toFixed(1) +
                '%"></span></div>' +
                "</div>" +
                '<div class="drv-right">' +
                '<span class="drv-status ' +
                statusClass(driver.status) +
                '">' +
                escapeHtml(driver.status || "No activity") +
                "</span>" +
                (pending > 0
                    ? '<div class="drv-due">' + money(driver.pending_amount) + " due</div>"
                    : '<div class="drv-due is-clear">No pending</div>') +
                "</div>";

            list.appendChild(row);
        });

        if (Number(driverCount) > top.length) {
            var more = document.createElement("div");
            more.className = "drv-meta";
            more.style.textAlign = "center";
            more.style.paddingTop = "4px";
            more.textContent =
                "+" +
                (Number(driverCount) - top.length) +
                " more driver" +
                (Number(driverCount) - top.length === 1 ? "" : "s") +
                " — use View All for the full list.";
            list.appendChild(more);
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function renderError() {
        var list = document.getElementById("drvList");
        if (list) {
            list.innerHTML =
                '<div class="drv-empty"><i class="bi bi-exclamation-circle"></i>Couldn\'t load driver remittances. Select a period to retry.</div>';
        }
    }

    function loadDriverRemittance(query) {
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
                render(data.drivers || [], data.driver_count || 0);
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

    window.loadDriverRemittance = loadDriverRemittance;

    document.addEventListener("DOMContentLoaded", function () {
        if (!window.__financeFilterQuery) {
            loadDriverRemittance("range=year");
        }
    });
})();
</script>
