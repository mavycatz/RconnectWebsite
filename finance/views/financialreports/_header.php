<?php if (!defined('APP_LOADED')) exit; ?>

<?php
$filterFrom   = $_GET['from'] ?? '';
$filterTo     = $_GET['to'] ?? '';
$filterCat    = $_GET['category'] ?? '';
$filterSearch = $_GET['search'] ?? '';
?>

<style>
.fin-select {
    min-width: 150px;
    height: 38px;
    padding: 0 34px 0 12px;
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    background: #faf9e5;
    color: #295738;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' fill='none' stroke='%23e0d68a' stroke-width='2' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
}
.fin-select:hover {
    border-color: #d9cf86;
}
.fin-select:focus {
    outline: none;
    border-color: #d9cf86;
    box-shadow: 0 0 0 3px rgba(217, 207, 134, 0.25);
}

.fin-filter-form {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    padding: 8px 14px;
    max-width: 100%;
    min-width: 0;
    border: 2px solid #e0d68a;
    border-radius: 14px;
    background: #faf9e5;
}
.fin-filter-search {
    position: relative;
    flex: 1 1 170px;
    min-width: 150px;
    max-width: 260px;
}
.fin-filter-search i {
    position: absolute;
    left: 11px;
    top: 50%;
    transform: translateY(-50%);
    color: #9aa892;
    font-size: 0.8rem;
    pointer-events: none;
}
.fin-filter-input {
    height: 38px;
    border: 1.5px solid #e7e3af;
    border-radius: 10px;
    background: #fff;
    color: #333;
    font-family: "Poppins", sans-serif;
    font-size: 0.8rem;
    padding: 0 12px;
    outline: none;
    box-sizing: border-box;
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}
.fin-filter-input:focus {
    border-color: #d9cf86;
    box-shadow: 0 0 0 3px rgba(217, 207, 134, 0.25);
}
.fin-filter-search .fin-filter-input {
    width: 100%;
    padding-left: 32px;
}
.fin-filter-dates {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.fin-filter-date {
    width: 140px;
}
.fin-filter-sep {
    color: #b9b78a;
    font-weight: 600;
    font-size: 0.75rem;
}

.filter-pipe {
    color: #cdd9b5;
    font-size: 0.9rem;
    font-weight: 200;
}
.filter-clear {
    font-size: 0.75rem;
    color: #999;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s ease;
    padding: 4px 8px;
    border-radius: 8px;
}
.filter-clear:hover {
    color: #dc3545;
    background: rgba(220, 53, 69, 0.06);
}

@media (max-width: 768px) {
    #financialFilter {
        flex: 1 1 100%;
    }
    .fin-filter-search {
        flex: 1 1 100%;
        max-width: none;
    }
    .fin-filter-date {
        flex: 1 1 auto;
        width: auto;
        min-width: 130px;
    }
}
</style>

<div class="fr-page-header">

    
    <div style="flex:1 1 auto;min-width:0;">
        <h1 class="fr-page-title">
            Financial Reports
        </h1>
        <p style="font-weight:500;font-size:0.82rem;color:#8a9a8a;margin:0;max-width:100%;overflow-wrap:anywhere;">
            View and generate financial reports and summaries
        </p>
    </div>

    
<form id="financialFilter" method="GET" class="fin-filter-form">

        <div class="fin-filter-search">
            <i class="bi bi-search"></i>
            <input type="text" id="finReportsSearch" name="search" class="fin-filter-input"
                   value="<?= htmlspecialchars($filterSearch) ?>" placeholder="Search reports..." autocomplete="off">
        </div>

        <select name="category" class="fin-select">
            <option value="">All Reports</option>
            <option value="full" <?= $filterCat === 'full' ? 'selected' : '' ?>>Comprehensive</option>
            <option value="revenue" <?= $filterCat === 'revenue' ? 'selected' : '' ?>>Revenue</option>
            <option value="expenses" <?= $filterCat === 'expenses' ? 'selected' : '' ?>>Expenses</option>
            <option value="fleet" <?= $filterCat === 'fleet' ? 'selected' : '' ?>>Fleet</option>
            <option value="routes" <?= $filterCat === 'routes' ? 'selected' : '' ?>>Routes</option>
            <option value="drivers" <?= $filterCat === 'drivers' ? 'selected' : '' ?>>Drivers</option>
            <option value="summary" <?= $filterCat === 'summary' ? 'selected' : '' ?>>Summary</option>
            <option value="risk" <?= $filterCat === 'risk' ? 'selected' : '' ?>>Risk</option>
        </select>

        <div class="fin-filter-dates">
            <input type="date" id="finReportsFrom" name="from" class="fin-filter-input fin-filter-date"
                   value="<?= htmlspecialchars($filterFrom) ?>" title="From date" aria-label="From date">
            <span class="fin-filter-sep">&ndash;</span>
            <input type="date" id="finReportsTo" name="to" class="fin-filter-input fin-filter-date"
                   value="<?= htmlspecialchars($filterTo) ?>" title="To date" aria-label="To date">
        </div>

        <?php if ($filterFrom || $filterTo || $filterCat || $filterSearch): ?>
            <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="filter-clear" title="Clear filters">✕</a>
        <?php endif; ?>

    </form>

</div>




<div class="modal fade fin-report-modal" id="reportsModal" tabindex="-1" aria-labelledby="reportsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:20px;background:#fffdf6;border:1px solid #ece7c8;">

            <div class="modal-header" style="border-bottom:1px solid #ece7c8;padding:18px 22px;">
                <h5 style="margin:0;font-weight:800;color:#295738;" id="reportsModalTitle">
                    <i class="bi bi-list-ul me-2" style="color:#57694d;"></i>All Reports
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close report library"></button>
            </div>

            <div class="modal-body" style="padding:20px 22px;">
                <div class="fin-modal-callout"><strong>Your saved report library</strong><p>Search by report title or filter by analysis type. Select a report to read its saved content. Archived reports stay available for reference and can be restored to the active list.</p></div>

                
                <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center;padding:12px 16px;background:#fcfbf4;border:1px solid #e7e3af;border-radius:12px;">

                    <input type="text" id="reportsModalSearch" placeholder="Search reports..." 
                           style="flex:1;min-width:150px;padding:8px 12px;border:1.5px solid #e7e3af;border-radius:8px;font-size:0.82rem;outline:none;font-family:'Poppins',sans-serif;background:#fff;"
                           oninput="searchReportsModal(this.value)">

                    <select id="reportsModalCategory" class="fin-select" style="min-width:140px;height:36px;padding:0 34px 0 10px;font-size:0.78rem;"
                            onchange="applyReportsModalFilters()">
                        <option value="">All Types</option>
                        <option value="full">Comprehensive</option>
                        <option value="revenue">Revenue</option>
                        <option value="expenses">Expenses</option>
                        <option value="fleet">Fleet</option>
                        <option value="routes">Routes</option>
                        <option value="drivers">Drivers</option>
                        <option value="summary">Summary</option>
                        <option value="risk">Risk</option>
                    </select>

                </div>

                <div id="reportsModalList"></div>

            </div>

            <div class="modal-footer" style="border-top:1px solid #ece7c8;padding:14px 22px;">
                <button type="button" class="btn" data-bs-dismiss="modal"
                        style="height:42px;border-radius:10px;background:#ffffff;border:1.5px solid #cdd9b5;color:#57694d;font-weight:600;font-size:0.85rem;">Close</button>
            </div>

        </div>
    </div>
</div>

<script>
let reportsModalArchived = false;
let reportsModalSearchTimer = null;

function openReportsModal(archived) {
    reportsModalArchived = archived;
    document.getElementById("reportsModalTitle").innerHTML =
        '<i class="bi ' +
        (archived ? "bi-archive" : "bi-list-ul") +
        ' me-2" style="color:#57694d;"></i>' +
        (archived ? "Archived Reports" : "All Reports");
    document.getElementById("reportsModalSearch").value = "";
    document.getElementById("reportsModalCategory").value = "";
    document.getElementById("reportsModalList").innerHTML =
        '<div style="text-align:center;padding:40px;color:#aaa;"><div class="spinner-border spinner-border-sm me-2"></div>Loading...</div>';
    new bootstrap.Modal(document.getElementById("reportsModal")).show();
    applyReportsModalFilters();
}

function getReportsModalParams() {
    const p = new URLSearchParams();
    p.append("archived", reportsModalArchived ? "1" : "0");
    const search = document.getElementById("reportsModalSearch").value.trim();
    if (search) p.append("search", search);
    const cat = document.getElementById("reportsModalCategory").value;
    if (cat) p.append("category", cat);
    const fromEl = document.getElementById("finReportsFrom");
    const toEl = document.getElementById("finReportsTo");
    if (fromEl && fromEl.value) p.append("from", fromEl.value);
    if (toEl && toEl.value) p.append("to", toEl.value);
    for (const id of ['finReportsDriver', 'finReportsBus']) {
        const field = document.getElementById(id);
        if (field && field.value) p.append(field.name, field.value);
    }
    return p.toString();
}

function applyReportsModalFilters() {
    clearTimeout(reportsModalSearchTimer);
    reportsModalSearchTimer = setTimeout(function () {
        const qs = getReportsModalParams();
        fetch("/finance/api/financial_reports_api.php?action=get_reports&" + qs)
            .then(function (r) {
                return r.json();
            })
            .then(function (d) {
                const container = document.getElementById("reportsModalList");
                if (!container) return;
                if (!d.reports || !d.reports.length) {
                    container.innerHTML =
                        '<div style="text-align:center;padding:50px;color:#bbb;"><i class="bi bi-inbox" style="font-size:2.5rem;display:block;margin-bottom:12px;color:#ddd;"></i><div style="font-weight:600;font-size:0.95rem;">' +
                        (reportsModalArchived
                            ? "No archived reports match your filters"
                            : "No reports match your filters") +
                        '</div><div style="font-size:0.82rem;color:#bbb;margin-top:4px;">Try adjusting your search or filters</div></div>';
                    return;
                }
                let html = '<div style="display:grid;gap:8px;">';
                d.reports.forEach(function (r) {
                    html +=
                        '<div style="display:flex;justify-content:space-between;align-items:center;padding:14px 18px;background:#ffffff;border:1.5px solid #e7e3af;border-radius:12px;transition:0.2s;cursor:pointer;" onclick="loadReportFromModal(' +
                        r.id +
                        ")\" onmouseover=\"this.style.borderColor='#295738';this.style.background='#fcfbf4'\" onmouseout=\"this.style.borderColor='#e7e3af';this.style.background='#ffffff'\">";
                    html +=
                        '<div><div style="font-weight:700;color:#295738;font-size:0.9rem;">' +
                        (r.title || "") +
                        '</div><div style="font-size:0.78rem;color:#888;margin-top:3px;">' +
                        (r.generated_at || "") +
                        "</div></div>";
                    if (reportsModalArchived) {
                        html +=
                            '<button onclick="event.stopPropagation();unarchiveReportModal(' +
                            r.id +
                            ')" style="border:1.5px solid #295738;border-radius:8px;padding:6px 14px;background:#fff;color:#295738;font-size:0.78rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:0.2s;" onmouseover="this.style.background=\'#edf7ee\'" onmouseout="this.style.background=\'#fff\'">Restore</button>';
                    } else {
                        html +=
                            '<button onclick="event.stopPropagation();archiveReportModal(' +
                            r.id +
                            ")\" style=\"border:1.5px solid #ddd;border-radius:8px;padding:6px 14px;background:#fff;color:#999;font-size:0.78rem;cursor:pointer;white-space:nowrap;transition:0.2s;\" onmouseover=\"this.style.borderColor='#dc3545';this.style.color='#dc3545'\" onmouseout=\"this.style.borderColor='#ddd';this.style.color='#999'\">Archive</button>";
                    }
                    html += "</div>";
                });
                html += "</div>";
                container.innerHTML = html;
            })
            .catch(function () {
                document.getElementById("reportsModalList").innerHTML =
                    '<div style="text-align:center;padding:30px;color:#dc3545;">Failed to load reports.</div>';
            });
    }, 300);
}

function searchReportsModal(val) {
    applyReportsModalFilters();
}

async function archiveReportModal(id) {
    await fetch("/finance/api/financial_reports_api.php?action=archive_report&id=" + id);
    applyReportsModalFilters();
}

async function unarchiveReportModal(id) {
    await fetch("/finance/api/financial_reports_api.php?action=unarchive_report&id=" + id);
    applyReportsModalFilters();
}

function loadReportFromModal(id) {
    const btn = document.getElementById("reportsModal").querySelector(".btn-close");
    if (btn) btn.click();
    if (typeof loadReportById === "function") loadReportById(id);
}

async function archiveReportModal(id) {
    await fetch("/finance/api/financial_reports_api.php?action=archive_report&id=" + id);
    loadReportsModal(document.getElementById("reportsModalSearch").value, false);
}

async function unarchiveReportModal(id) {
    await fetch("/finance/api/financial_reports_api.php?action=unarchive_report&id=" + id);
    loadReportsModal(document.getElementById("reportsModalSearch").value, true);
}

function loadReportFromModal(id) {
    const btn = document.getElementById("reportsModal").querySelector(".btn-close");
    if (btn) btn.click();
    if (typeof loadReportById === "function") loadReportById(id);
}

const finFilterForm = document.getElementById("financialFilter");
if (finFilterForm) {
    finFilterForm.addEventListener("change", function (event) {
        if (event.target && event.target.id === "finReportsSearch") return;
        finFilterForm.submit();
    });

    const finSearchInput = document.getElementById("finReportsSearch");
    if (finSearchInput) {
        let finSearchTimer = null;
        finSearchInput.addEventListener("keydown", function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
                clearTimeout(finSearchTimer);
                finFilterForm.submit();
            }
        });
        finSearchInput.addEventListener("input", function () {
            clearTimeout(finSearchTimer);
            finSearchTimer = setTimeout(function () {
                finFilterForm.submit();
            }, 700);
        });
    }
}
</script>
