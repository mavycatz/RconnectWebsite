



<style>
.action-btn {
    height: 44px;
    padding: 0 18px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    white-space: nowrap;
}
.action-btn-outline {
    background: #ffffff;
    border: 1.5px solid #cdd9b5;
    color: #57694d;
}
.action-btn-outline:hover {
    border-color: #295738;
    background: #f0f7f0;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(41, 87, 56, 0.1);
}
</style>

<div class="fr-toolbar">

    <?php if (\App\Auth\Auth::role() === 'finance_manager'): ?>
    <button type="button" class="action-btn action-btn-outline" data-bs-toggle="modal" data-bs-target="#importCSVModal">
        <i class="bi bi-upload"></i> Import Data
    </button>

    <button type="button" class="action-btn action-btn-outline" data-bs-toggle="modal" data-bs-target="#generateReportModal">
        <i class="bi bi-file-earmark-text"></i> Generate Report
    </button>
    <?php endif; ?>

    <button type="button" class="action-btn action-btn-outline" onclick="openReportsModal(false)">
        <i class="bi bi-list-ul"></i> Reports
    </button>

    <button type="button" class="action-btn action-btn-outline" onclick="openReportsModal(true)">
        <i class="bi bi-archive"></i> Archived
    </button>

</div>





<div class="modal fade fin-report-modal" id="importCSVModal" tabindex="-1" aria-labelledby="importFinancialTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <h5 id="importFinancialTitle">
                    <i class="bi bi-upload"></i> Import Data — Trip History (CSV)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close import" onclick="resetHistoryImport()"></button>
            </div>

            <div class="modal-body">

                <div class="fin-modal-callout">
                    <strong>Import passenger trip history into Financial Reports</strong>
                    <p>Use the same CSV format as the Trip History import. Records are validated and previewed before anything is written; imported rows land in the existing trip-history table and feed the financial reports and calculations.</p>
                </div>
                <ol class="fin-modal-steps" aria-label="Import workflow"><li>Choose your file</li><li>Review the preview</li><li>Confirm the import</li></ol>

                <div class="fin-modal-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

                    <div>
                        <div class="mb-3">
                            <label style="display:block;margin-bottom:6px;font-size:0.85rem;font-weight:600;color:#57694d;">Select CSV File</label>
                            <div>
                                <label for="csvFileInput" id="importDropZone" class="btn"
                                       style="height:48px;padding:0 24px;border-radius:12px;background:#ffffff;border:1.5px solid #cdd9b5;color:#57694d;font-weight:600;cursor:pointer;width:100%;text-align:center;">
                                    <i class="bi bi-folder2-open me-2"></i><span id="csvFileLabel">Choose CSV file…</span>
                                </label>
                                <input type="file" id="csvFileInput" accept=".csv" aria-label="Choose trip history CSV file" aria-describedby="importFileHelp">
                            </div>
                            <div id="importFileHelp" class="fin-modal-help">
                                <strong>Required columns</strong>: <code>passenger_name</code>, <code>boarding_point</code>, <code>destination</code>, <code>drivers_full_name</code>.<br>
                                <strong>Optional</strong>: <code>passenger_type</code>, <code>student_id</code>, <code>senior_pwd_id</code>, <code>beneficiary_id</code>, <code>coop_disc_id</code>, <code>bus_number</code>, <code>trip_id</code>, <code>fare</code>, <code>payment_method</code>, <code>date_booked</code>, <code>trip_date</code>, <code>time_of_departure</code>, <code>time_of_arrival</code>, <code>remarks</code>.<br>
                                Drag the file onto the button or click to browse. One CSV per import.
                            </div>
                            <div id="importStatus" role="status" aria-live="polite" class="fin-import-status" hidden>
                                <span class="spinner-border spinner-border-sm me-1"></span><span id="importStatusText">Reading file…</span>
                            </div>
                        </div>

                        <div class="fin-modal-guide">
                            <h6><i class="bi bi-table me-1" aria-hidden="true"></i> Trip history format</h6>
                            <p>Use column headers in the first row. Dates accept <code>m/d/yyyy</code>, <code>yyyy-mm-dd</code>, or <code>Month d, yyyy</code>; times accept <code>HH:MM</code> or <code>HH:MM:SS</code>. Payment methods: cash, gcash, hybrid.</p>
                            <pre aria-label="Example CSV header">passenger_name,passenger_type,boarding_point,destination,drivers_full_name,bus_number,fare,payment_method,trip_date,time_of_departure</pre>
                            <p class="fin-modal-help">Duplicate records (same passenger, trip id and trip date) are skipped so the same file cannot be imported twice.</p>
                        </div>
                    </div>

                    <div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span style="font-size:0.85rem;font-weight:600;color:#57694d;">Preview</span>
                                <small class="text-muted" id="csvTotalRows"></small>
                            </div>
                            <div id="csvPreview" style="background:#ffffff;border:1.5px solid #cdd9b5;border-radius:12px;padding:14px;max-height:300px;overflow:auto;">
                                <div class="fin-modal-empty"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i><span>Your file preview will appear here.<br>Rows are checked against the trip-history rules before importing.</span></div>
                            </div>
                            <div id="importCounts" class="fin-import-counts" hidden></div>
                        </div>
                    </div>

                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn" data-bs-dismiss="modal" onclick="resetHistoryImport()"
                        style="height:44px;border-radius:10px;background:#ffffff;border:1.5px solid #cdd9b5;color:#57694d;font-weight:600;">Cancel</button>
                <button type="button" id="importConfirmBtn" class="btn text-white" onclick="confirmHistoryImport()" disabled
                        style="height:44px;border-radius:10px;border:none;background:#57694d;font-weight:600;opacity:0.5;">
                    <i class="bi bi-check2 me-1"></i>Confirm Import
                </button>
            </div>

        </div>
    </div>
</div>





<div class="modal fade fin-report-modal" id="reportPreviewModal" tabindex="-1" aria-labelledby="reportPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:20px;background:#fffdf6;border:1px solid #ece7c8;">

            <div class="modal-header" style="border-bottom:1px solid #ece7c8;padding:20px 24px;">
                <h5 style="margin:0;font-weight:800;color:#295738;" id="reportPreviewTitle">
                    <i class="bi bi-file-earmark-text me-2" style="color:#57694d;"></i>Generated Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close report preview"></button>
            </div>

            <div class="modal-body" style="padding:24px;">
                <p class="fin-modal-help mb-3">This is the original saved report text. Its content remains tied to the records available when it was generated.</p>
                <div id="reportPreviewContent" style="
                    background:#ffffff;
                    border:2px solid #e7e3af;
                    border-radius:16px;
                    padding:24px;
                    font-size:0.95rem;
                    line-height:1.8;
                    white-space:pre-wrap;
                    color:#333;
                    max-height:70vh;
                    overflow-y:auto;
                ">Loading...</div>
            </div>

            <div class="modal-footer" style="border-top:1px solid #ece7c8;padding:16px 24px;">
                <button type="button" class="btn" data-bs-dismiss="modal"
                        style="height:44px;border-radius:10px;background:#ffffff;border:1.5px solid #cdd9b5;color:#57694d;font-weight:600;">Close</button>
            </div>

        </div>
    </div>
</div>





<div class="modal fade fin-report-modal" id="generateReportModal" tabindex="-1" aria-labelledby="generateFinancialTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:20px;background:#fffdf6;border:1px solid #ece7c8;max-width:860px;">

            <div class="modal-header" style="border-bottom:1px solid #ece7c8;padding:20px 28px;">
                <h5 id="generateFinancialTitle" style="margin:0;font-weight:800;color:#295738;">
                    <i class="bi bi-file-earmark-text me-2" style="color:#57694d;"></i>Generate Financial Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close report generation"></button>
            </div>

            <div class="modal-body" style="padding:24px 28px;">

                <div class="fin-modal-callout">
                    <strong>Turn recorded financial activity into a clear report</strong>
                    <p>Choose the analysis focus and reporting period. The report combines verified totals, AI findings, charts, and transaction details from the same saved snapshot.</p>
                </div>
                <ol class="fin-modal-steps" aria-label="Report workflow"><li>Choose the scope</li><li>Generate analysis</li><li>Preview and export</li></ol>

                <div class="fin-modal-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

                    <div>
                        <label for="reportType" style="display:block;margin-bottom:6px;font-size:0.85rem;font-weight:600;color:#57694d;">Report Type</label>
                        <select id="reportType" class="form-control" style="border-radius:10px;border:1.5px solid #cdd9b5;padding:10px;width:100%;">
                            <option value="full">Comprehensive — All Operations</option>
                            <option value="revenue">Trip Revenue & Passenger Analysis</option>
                            <option value="expenses">Expense Breakdown by Category</option>
                            <option value="fleet">Bus Fleet Performance</option>
                            <option value="routes">Route Performance & Demand</option>
                            <option value="drivers">Driver Performance & Remittances</option>
                            <option value="summary">Financial Summary</option>
                            <option value="risk">Risk Assessment & KPIs</option>
                        </select>
                        <div style="font-size:0.78rem;color:#8aa09a;margin-top:4px;">Choose what area to analyze</div>
                    </div>

                    <div>
                        <label for="fprGenerationPeriod" style="display:block;margin-bottom:6px;font-size:0.85rem;font-weight:600;color:#57694d;">Reporting Period</label>
                        <select id="fprGenerationPeriod" class="form-control" style="border-radius:10px;border:1.5px solid #cdd9b5;padding:10px;width:100%;">
                            <?php foreach (['today' => 'Today', 'this_week' => 'This Week', 'this_month' => 'This Month', 'last6' => 'Last 6 Months', 'this_year' => 'This Year', 'custom' => 'Custom Date Range'] as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $value === (($filterFrom || $filterTo) ? 'custom' : 'this_month') ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div style="font-size:0.78rem;color:#8aa09a;margin-top:4px;">Charts, totals and PDF use this same reporting period.</div>
                    </div>

                </div>

                <div id="fprGenerationCustom" style="margin-top:16px;" <?= ($filterFrom || $filterTo) ? '' : 'hidden' ?>>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                        <label style="flex:1;min-width:140px;font-size:0.82rem;">From
                            <input type="date" id="fprGenerationFrom" class="form-control" value="<?= htmlspecialchars($filterFrom, ENT_QUOTES) ?>">
                        </label>
                        <label style="flex:1;min-width:140px;font-size:0.82rem;">To
                            <input type="date" id="fprGenerationTo" class="form-control" value="<?= htmlspecialchars($filterTo, ENT_QUOTES) ?>">
                        </label>
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <label for="reportDescription" style="display:block;margin-bottom:6px;font-size:0.85rem;font-weight:600;color:#57694d;">
                        Describe what you need <span style="font-weight:400;color:#999;">(optional)</span>
                    </label>
                    <textarea id="reportDescription" rows="3" class="form-control"
                              style="border-radius:10px;border:1.5px solid #cdd9b5;padding:10px;width:100%;"
                              placeholder="E.g. Focus on fuel cost trends this month and compare with last month.&#10;&#10;Leave empty for a standard report covering everything."></textarea>
                </div>
                <div class="fin-modal-guide"><h6>What to expect</h6><p>Dates use Philippine time and include both the start and end day. Custom ranges require both dates.</p><p>The preview opens automatically while the analysis is prepared. Once complete, you can read the report and download a PDF with the same data and findings.</p><p class="fin-modal-help">Revenue uses recorded fares; remittances are shown as settlement. Analysis is limited to available records, so missing route or assignment information is not estimated.</p></div>

            </div>

            <div class="modal-footer" style="border-top:1px solid #ece7c8;padding:16px 28px;">
                <button type="button" class="btn" data-bs-dismiss="modal"
                        style="height:44px;border-radius:10px;background:#ffffff;border:1.5px solid #cdd9b5;color:#57694d;font-weight:600;">Cancel</button>
                <button type="button" class="btn text-white" id="generateReportSubmit"
                        style="height:44px;border-radius:10px;border:none;background:#57694d;font-weight:600;">
                    <i class="bi bi-send me-1"></i>Generate Report
                </button>
            </div>

        </div>
    </div>
</div>

<script>
let historyImportFile = null;

const importFileInput = document.getElementById("csvFileInput");
const importDropZone = document.getElementById("importDropZone");

function setImportStatus(text){
    const status = document.getElementById("importStatus");
    const label = document.getElementById("importStatusText");
    if(!status || !label){ return; }
    if(!text){ status.hidden = true; label.textContent = ""; return; }
    label.textContent = text;
    status.hidden = false;
}

function setImportConfirm(enabled, label){
    const btn = document.getElementById("importConfirmBtn");
    if(!btn){ return; }
    btn.disabled = !enabled;
    btn.style.opacity = enabled ? "1" : "0.5";
    btn.innerHTML = label || '<i class="bi bi-check2 me-1"></i>Confirm Import';
}

function resetHistoryImport(){
    historyImportFile = null;
    importFileInput.value = "";
    document.getElementById("csvFileLabel").textContent = "Choose CSV file…";
    document.getElementById("csvPreview").innerHTML =
        '<div class="fin-modal-empty"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i><span>Your file preview will appear here.<br>Rows are checked against the trip-history rules before importing.</span></div>';
    document.getElementById("csvTotalRows").textContent = "";
    document.getElementById("importCounts").hidden = true;
    document.getElementById("importCounts").innerHTML = "";
    setImportStatus("");
    setImportConfirm(false);
}

function historyPreviewMessage(message){
    document.getElementById("csvPreview").innerHTML =
        '<p style="color:#c62828;font-weight:600;padding:10px;margin:0;">' + message + "</p>";
    document.getElementById("importCounts").hidden = true;
    setImportConfirm(false);
}

function previewHistoryFile(file){
    if(!file){ return; }
    if(!/\.csv$/i.test(file.name)){
        historyImportFile = null;
        document.getElementById("csvFileLabel").textContent = file.name;
        historyPreviewMessage("Only CSV files are supported.");
        return;
    }

    historyImportFile = file;
    document.getElementById("csvFileLabel").textContent = file.name;
    document.getElementById("csvTotalRows").textContent = "";
    document.getElementById("importCounts").hidden = true;
    setImportConfirm(false);
    setImportStatus("Validating " + file.name + "…");

    const formData = new FormData();
    formData.append("file", file);

    fetch("/finance/api/financial_reports_api.php?action=trip_history_preview", {
        method: "POST",
        body: formData
    })
        .then((r) => r.json())
        .then((data) => {
            setImportStatus("");
            if(!data.success){
                historyPreviewMessage(data.message || "The file could not be validated.");
                return;
            }
            renderHistoryPreview(data);
        })
        .catch((err) => {
            setImportStatus("");
            historyPreviewMessage(err.message || "The file could not be validated.");
        });
}

function renderHistoryPreview(data){
    const counts = data.counts || {};
    const columns = Array.isArray(data.columns) ? data.columns : [];
    const knownColumns = Array.isArray(data.known_columns) ? data.known_columns : [];
    const ignoredHeaders = Array.isArray(data.ignored_headers) ? data.ignored_headers : [];
    const rows = Array.isArray(data.rows) ? data.rows : [];

    let html = '<div class="fin-import-chips">';
    knownColumns.forEach((column) => {
        const tone = column.found ? (column.required ? "ok" : "muted") : (column.required ? "bad" : "warn");
        html += '<span class="fin-import-chip ' + tone + '">' + (column.found ? "✓" : "✗") + " " + column.name +
            " <em>(" + (column.required ? "Required" : "Optional") + ")</em></span>";
    });
    html += "</div>";

    if(ignoredHeaders.length){
        html += '<div class="fin-import-note">Ignored column(s) not part of the trip-history format: ' +
            ignoredHeaders.join(", ") + "</div>";
    }

    if(Array.isArray(data.missing_required) && data.missing_required.length){
        html += '<div class="fin-import-alert bad">Missing required column(s): ' +
            data.missing_required.join(", ") + "</div>";
    }

    html += '<div style="max-height:220px;overflow:auto;border:1px solid #dce5d3;border-radius:10px;">';
    if(rows.length){
        const headers = columns.length ? columns.map((c) => c.name) : Object.keys(rows[0].values || {});
        html += '<table class="fin-import-table"><thead><tr>';
        headers.forEach((header) => { html += "<th>" + header + "</th>"; });
        html += "<th>Status</th></tr></thead><tbody>";
        rows.forEach((row) => {
            const values = row.values || {};
            const status = row.status;
            const rowClass = status === "valid" ? "ok" : (status === "duplicate" ? "warn" : "bad");
            const reasons = (row.errors || []).join("; ");
            html += '<tr class="' + rowClass + '">';
            headers.forEach((header) => {
                const value = values[header] || "";
                html += "<td>" + (value === "" ? "—" : value) + "</td>";
            });
            html += "<td>";
            if(status === "valid"){
                html += '<span class="fin-import-state ok">✓ Valid</span>';
            } else if(status === "duplicate"){
                html += '<span class="fin-import-state warn" title="' + reasons + '">⚠ Duplicate</span>';
            } else {
                html += '<span class="fin-import-state bad" title="' + reasons + '">✗ Invalid</span>';
            }
            html += "</td></tr>";
        });
        html += "</tbody></table>";
    } else {
        html += '<div class="fin-modal-empty"><i class="bi bi-inbox" aria-hidden="true"></i><span>No data rows found in this file.</span></div>';
    }
    html += "</div>";

    document.getElementById("csvPreview").innerHTML = html;
    document.getElementById("csvTotalRows").textContent =
        (counts.total || 0) + " row(s) found" + (data.truncated ? " — first " + rows.length + " shown" : "");

    const countsEl = document.getElementById("importCounts");
    countsEl.hidden = false;
    countsEl.innerHTML =
        '<span class="fin-import-count ok">✓ ' + (counts.valid || 0) + " valid</span>" +
        '<span class="fin-import-count warn">⚠ ' + (counts.duplicate || 0) + " duplicates</span>" +
        '<span class="fin-import-count bad">✗ ' + (counts.error || 0) + " invalid</span>" +
        '<span class="fin-import-count muted">' + (counts.total || 0) + " total rows</span>";

    setImportConfirm((counts.valid || 0) > 0);
}

function confirmHistoryImport(){
    if(!historyImportFile){
        return;
    }

    const formData = new FormData();
    formData.append("file", historyImportFile);

    setImportConfirm(false, '<span class="spinner-border spinner-border-sm me-1"></span> Importing…');
    setImportStatus("Importing " + historyImportFile.name + "…");

    fetch("/finance/api/financial_reports_api.php?action=trip_history_import", {
        method: "POST",
        body: formData
    })
        .then((r) => r.json())
        .then((data) => {
            setImportStatus("");
            if(typeof data.imported === "undefined"){
                throw new Error(data.message || "The import failed.");
            }

            let message = "Imported: " + (data.imported || 0) +
                " · Skipped duplicates: " + (data.skipped || 0) +
                " · Invalid rows: " + (data.failed || 0);
            if(data.report_id){
                message += " · Report generated: " + (data.report_title || ("#" + data.report_id));
            }
            if(Array.isArray(data.errors) && data.errors.length){
                message += " · " + data.errors.slice(0, 6).join(" · ");
            }

            showAlert("success", "Import Complete", message, function () {
                const closeBtn = document.querySelector("#importCSVModal .btn-close");
                if(closeBtn){ closeBtn.click(); }

                if(data.report_id && typeof loadReportById === "function"){
                    // Refresh the saved Reports library and open the report
                    // generated for this import.
                    if(typeof loadReportsList === "function" && typeof buildFilterQuery === "function"){
                        try { loadReportsList(buildFilterQuery(), false); } catch(e) {}
                    }
                    try { loadReportById(data.report_id, true); } catch(e) { location.reload(); }
                    return;
                }

                location.reload();
            });
        })
        .catch((err) => {
            setImportStatus("");
            showAlert("error", "Import Failed", err.message || "The import failed.");
            setImportConfirm(true);
        });
}

importFileInput.addEventListener("change", function (e) {
    previewHistoryFile(e.target.files && e.target.files[0]);
});

if(importDropZone){
    ["dragenter", "dragover"].forEach((name) => {
        importDropZone.addEventListener(name, function (e) {
            e.preventDefault();
            e.stopPropagation();
            importDropZone.classList.add("is-dragging");
        });
    });
    ["dragleave", "drop"].forEach((name) => {
        importDropZone.addEventListener(name, function (e) {
            e.preventDefault();
            e.stopPropagation();
            importDropZone.classList.remove("is-dragging");
        });
    });
    importDropZone.addEventListener("drop", function (e) {
        const file = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files[0] : null;
        if(file){ previewHistoryFile(file); }
    });
}

document.getElementById('fprGenerationPeriod').addEventListener('change', function () {
    document.getElementById('fprGenerationCustom').hidden = this.value !== 'custom';
});

document.getElementById("generateReportSubmit").addEventListener("click", async function () {
    const reportType = document.getElementById("reportType").value;
    const description = document.getElementById("reportDescription").value.trim();

    if (!window.FinanceFinancialPerformance) {
        showAlert('error', 'Report preview unavailable', 'Reload the page to load the report preview component.');
        return;
    }
    const period = document.getElementById('fprGenerationPeriod').value;
    const from = document.getElementById('fprGenerationFrom').value;
    const to = document.getElementById('fprGenerationTo').value;
    if (period === 'custom' && (!from || !to || from > to)) {
        showAlert('error', 'Invalid date range', 'Choose both dates with the start on or before the end.');
        return;
    }

    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';

    try {
        if (!await window.FinanceFinancialPerformance.prepare()) return;
        const params = new URLSearchParams();
        params.append("action", "generate");
        params.append("report_type", reportType);
        if (description) params.append("description", description);
        params.set('snapshot', window.FinanceFinancialPerformance.getSnapshot());
        params.set('_csrf_token', document.getElementById('financialPerformance').dataset.csrf);
        const response = await window.FinanceFinancialPerformance.requestFinance("/finance/api/financial_performance.php", {method: 'POST', body: params, cache: 'no-store'});
        const result = await window.FinanceFinancialPerformance.readJson(response);
        if (!response.ok || !result.success) throw new Error(result.message || 'Could not generate report.');
        window.FinanceFinancialPerformance.showGenerated(result);
        loadReportsList(buildFilterQuery(), false);
        loadReportById(result.report_id, false);
    } catch (error) {
        window.FinanceFinancialPerformance.showError(error.message || 'Network error. Could not generate report.');
    } finally {
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-send me-1"></i>Generate Report';
    }
});
</script>
