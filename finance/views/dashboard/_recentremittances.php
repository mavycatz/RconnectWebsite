<?php
$recentRemitViewAllUrl = isset($finUrl) ? $finUrl('history') : '/finance/history.php';
?>
<div class="fin-panel" id="recentRemittancesPanel">
    <div class="fin-panel-head">
        <div>
            <h2 class="fin-panel-title"><i class="bi bi-clock-history"></i> Recent Remittances</h2>
            <p class="fin-panel-sub">Latest finalized remittances for the selected period</p>
        </div>
        <a class="fin-panel-link" href="<?= htmlspecialchars($recentRemitViewAllUrl) ?>">
            View All <i class="bi bi-box-arrow-up-right"></i>
        </a>
    </div>

    <div class="rr-wrap" id="rrWrap">
        <div class="rr-loading">Loading recent remittances...</div>
    </div>
</div>

<style>
#recentRemittancesPanel .fin-panel-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
#recentRemittancesPanel .fin-panel-title {
    font-size: 1rem;
    font-weight: 700;
    color: #2e4836;
    margin: 0 0 2px;
}
#recentRemittancesPanel .fin-panel-title i {
    color: #295738;
    margin-right: 6px;
}
#recentRemittancesPanel .fin-panel-sub {
    font-size: 0.72rem;
    color: #8a9389;
    margin: 0;
}
#recentRemittancesPanel .fin-panel-link {
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
#recentRemittancesPanel .fin-panel-link:hover {
    background: #efe9c9;
}

.rr-wrap {
    overflow-x: auto;
}
.rr-loading,
.rr-empty {
    padding: 18px;
    text-align: center;
    font-size: 0.78rem;
    color: #8a9389;
}
.rr-empty i {
    display: block;
    font-size: 1.5rem;
    margin-bottom: 6px;
    color: #b0b9a8;
}

.rr-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.76rem;
}
.rr-table th,
.rr-table td {
    padding: 8px 10px;
    text-align: left;
    border-bottom: 1px solid #eef0e6;
    white-space: nowrap;
}
.rr-table th {
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #8a9389;
    font-weight: 800;
    background: #f7faf4;
}
.rr-table td.is-amount {
    text-align: right;
    font-weight: 700;
    color: #2e4836;
    font-variant-numeric: tabular-nums;
}
.rr-table tbody tr:hover {
    background: #f9f6ef;
}
.rr-badge {
    display: inline-block;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-radius: 999px;
    padding: 3px 9px;
}
.rr-badge.is-completed {
    background: #eef8f0;
    color: #1c7c3f;
    border: 1px solid #bfe3c6;
}
.rr-badge.is-pending {
    background: #fdf7e3;
    color: #8a7b0a;
    border: 1px solid #e5d79a;
}
.rr-badge.is-discrepancy {
    background: #fdf1f0;
    color: #b3261e;
    border: 1px solid #f0c8c4;
}
.rr-badge.is-no-remit {
    background: #f4f4ef;
    color: #6f7d6f;
    border: 1px solid #e2e2d8;
}
.rr-badge.is-other {
    background: #f4f4ef;
    color: #6f7d6f;
    border: 1px solid #e2e2d8;
}

@media (max-width: 575px) {
    .rr-table th,
    .rr-table td {
        padding: 7px 6px;
        font-size: 0.7rem;
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

    function formatAt(value) {
        if (!value) {
            return "—";
        }
        var d = new Date(String(value).replace(" ", "T"));
        if (isNaN(d.getTime())) {
            return String(value);
        }
        return d.toLocaleString("en-US", {
            month: "short",
            day: "numeric",
            hour: "numeric",
            minute: "2-digit",
        });
    }

    function statusClass(status) {
        switch (String(status || "").toLowerCase()) {
            case "completed":
            case "matched":
            case "paid":
            case "remitted":
                return "is-completed";
            case "pending":
                return "is-pending";
            case "discrepancy":
                return "is-discrepancy";
            case "no remit":
                return "is-no-remit";
            default:
                return "is-other";
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function render(rows) {
        var wrap = document.getElementById("rrWrap");
        if (!wrap) {
            return;
        }

        rows = rows || [];

        if (rows.length === 0) {
            wrap.innerHTML =
                '<div class="rr-empty"><i class="bi bi-inbox"></i>No finalized remittances for this period.</div>';
            return;
        }

        var html =
            '<table class="rr-table"><thead><tr>' +
            "<th>Date</th><th>Driver</th><th>Bus</th><th>Amount</th><th>Status</th>" +
            "</tr></thead><tbody>";

        rows.forEach(function (row) {
            html +=
                "<tr>" +
                "<td>" +
                escapeHtml(formatAt(row.at)) +
                "</td>" +
                "<td>" +
                escapeHtml(row.driver || "—") +
                "</td>" +
                "<td>" +
                escapeHtml(row.bus || "—") +
                "</td>" +
                '<td class="is-amount">' +
                money(row.amount) +
                "</td>" +
                '<td><span class="rr-badge ' +
                statusClass(row.status) +
                '">' +
                escapeHtml(row.status || "—") +
                "</span></td>" +
                "</tr>";
        });

        html += "</tbody></table>";
        wrap.innerHTML = html;
    }

    function renderError() {
        var wrap = document.getElementById("rrWrap");
        if (wrap) {
            wrap.innerHTML =
                '<div class="rr-empty"><i class="bi bi-exclamation-circle"></i>Couldn\'t load recent remittances. Select a period to retry.</div>';
        }
    }

    function loadRecentRemittances(query) {
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
                render(data.recent || []);
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

    window.loadRecentRemittances = loadRecentRemittances;

    document.addEventListener("DOMContentLoaded", function () {
        if (!window.__financeFilterQuery) {
            loadRecentRemittances("range=year");
        }
    });
})();
</script>
