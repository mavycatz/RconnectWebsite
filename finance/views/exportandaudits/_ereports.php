<div class="d-flex justify-content-between align-items-start mb-3 ea-header-row">
  <div class="fw-bold" style="color:#05590c;line-height:1;">
    <div style="font-size:1.5rem;">Excel</div>
    <div style="font-size:1.5rem;">Reports</div>
  </div>
  <small style="color:#8a9a8a;font-size:10px;text-align:right;">Uses the period selected in the page header.</small>
</div>

<div class="d-flex flex-column gap-2">
  <div class="card p-3" style="border:2px solid #e7e3af;background:#fff;">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h6 class="mb-1" style="color:#05590c;font-size:0.9rem;">
          <i class="bi bi-graph-up-arrow me-1"></i> Revenue Data
        </h6>
        <div style="display:flex;align-items:baseline;gap:8px;margin-top:2px;">
          <span class="fw-bold" style="color:#295738;font-size:1.1rem;" id="revTotal">₱0</span>
          <small style="color:#888;font-size:0.75rem;" id="revCount">0 trips</small>
        </div>
      </div>
      <button class="btn btn-sm ms-3" onclick="exportExcel('revenue')"
              style="background:#efd772;border:2px solid #05590c;color:#05590c;font-size:0.75rem;padding:3px 10px;">Export</button>
    </div>
  </div>
  <div class="card p-3" style="border:2px solid #e7e3af;background:#fff;">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h6 class="mb-1" style="color:#05590c;font-size:0.9rem;">
          <i class="bi bi-cash-stack me-1"></i> Expense Data
        </h6>
        <div style="display:flex;align-items:baseline;gap:8px;margin-top:2px;">
          <span class="fw-bold" style="color:#295738;font-size:1.1rem;" id="expTotal">₱0</span>
          <small style="color:#888;font-size:0.75rem;" id="expCount">0 entries</small>
        </div>
      </div>
      <button class="btn btn-sm ms-3" onclick="exportExcel('expenses')"
              style="background:#efd772;border:2px solid #05590c;color:#05590c;font-size:0.75rem;padding:3px 10px;">Export</button>
    </div>
  </div>
  <div class="card p-3" style="border:2px solid #e7e3af;background:#fff;">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h6 class="mb-1" style="color:#05590c;font-size:0.9rem;">
          <i class="bi bi-receipt me-1"></i> Remittance Data
        </h6>
        <div style="display:flex;align-items:baseline;gap:8px;margin-top:2px;">
          <span class="fw-bold" style="color:#295738;font-size:1.1rem;" id="remTotal">₱0</span>
          <small style="color:#888;font-size:0.75rem;" id="remCount">0 records</small>
        </div>
      </div>
      <button class="btn btn-sm ms-3" onclick="exportExcel('remittances')"
              style="background:#efd772;border:2px solid #05590c;color:#05590c;font-size:0.75rem;padding:3px 10px;">Export</button>
    </div>
  </div>
</div>

<script>
function exportExcel(type) {
    var df = window.__eaDateFilter || {};
    var params = new URLSearchParams({ action: "export_excel", type: type });
    if (df.period) params.set("period", df.period);
    if (df.period === "custom") {
        if (df.from) params.set("from", df.from);
        if (df.to) params.set("to", df.to);
    }
    window.open("/finance/api/exportandaudits_api.php?" + params.toString(), "_blank");
    if (typeof auditLog === "function") {
        auditLog(
            "Finance",
            "Export",
            "Exported " + type + " data to Excel",
            null,
            null,
            { type: type, period: df.period, from: df.from, to: df.to },
        );
    }
}
function loadExcelCardData() {
    var df = window.__eaDateFilter || {};
    var params = new URLSearchParams({ action: "get_export_summary" });
    if (df.period) params.set("period", df.period);
    if (df.period === "custom") {
        if (df.from) params.set("from", df.from);
        if (df.to) params.set("to", df.to);
    }
    fetch("/finance/api/exportandaudits_api.php?" + params.toString())
        .then(function (r) {
            return r.json();
        })
        .then(function (d) {
            if (!d.success) return;
            var dt = d.data;
            document.getElementById("revTotal").textContent =
                "₱" + Number(dt.total_revenue || 0).toLocaleString();
            document.getElementById("revCount").textContent = (dt.trip_count || 0) + " trips";
            document.getElementById("expTotal").textContent =
                "₱" + Number(dt.total_expenses || 0).toLocaleString();
            document.getElementById("expCount").textContent = (dt.expense_count || 0) + " entries";
            document.getElementById("remTotal").textContent =
                "₱" + Number(dt.total_revenue || 0).toLocaleString();
            document.getElementById("remCount").textContent =
                (dt.remittance_count || 0) + " records";
        });
}
document.addEventListener("DOMContentLoaded", loadExcelCardData);
</script>
