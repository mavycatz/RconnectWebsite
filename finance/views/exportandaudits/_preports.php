<div class="d-flex justify-content-between align-items-start mb-3">
  <div class="fw-bold" style="color:#05590c;line-height:1;">
    <div style="font-size:1.5rem;">PDF</div>
    <div style="font-size:1.5rem;">Reports</div>
  </div>
  <small style="color:#8a9a8a;font-size:10px;text-align:right;">Uses the period selected in the page header.</small>
</div>

<div class="d-flex flex-column gap-2">
  <div class="card p-3" style="border:2px solid #e7e3af;background:#fff;">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h6 class="mb-1" style="color:#05590c;font-size:0.9rem;">
          <i class="bi bi-file-text me-1"></i> Financial Summary
        </h6>
        <div style="display:flex;align-items:baseline;gap:6px;margin-top:2px;flex-wrap:wrap;">
          <span style="font-size:0.78rem;color:#295738;">Rev: <strong id="pdfRev">₱0</strong></span>
          <span style="font-size:0.78rem;color:#295738;">Exp: <strong id="pdfExp">₱0</strong></span>
          <span style="font-size:0.78rem;color:#295738;">Net: <strong id="pdfNet">₱0</strong></span>
        </div>
      </div>
      <button class="btn btn-sm ms-3" onclick="exportPdf()"
              style="background:#efd772;border:2px solid #05590c;color:#05590c;font-size:0.75rem;padding:3px 10px;">Export</button>
    </div>
  </div>
  <div class="card p-3" style="border:2px solid #e7e3af;background:#fff;">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h6 class="mb-1" style="color:#05590c;font-size:0.9rem;">
          <i class="bi bi-pie-chart me-1"></i> Expense Analysis
        </h6>
        <div style="display:flex;align-items:baseline;gap:6px;margin-top:2px;flex-wrap:wrap;">
          <span style="font-size:0.78rem;color:#295738;">Total: <strong id="pdfExpTotal">₱0</strong></span>
          <span style="font-size:0.78rem;color:#888;" id="pdfExpCat">0 entries</span>
        </div>
      </div>
      <button class="btn btn-sm ms-3" onclick="exportPdf()"
              style="background:#efd772;border:2px solid #05590c;color:#05590c;font-size:0.75rem;padding:3px 10px;">Export</button>
    </div>
  </div>
  <div class="card p-3" style="border:2px solid #e7e3af;background:#fff;">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h6 class="mb-1" style="color:#05590c;font-size:0.9rem;">
          <i class="bi bi-wallet2 me-1"></i> Collection Summary
        </h6>
        <div style="display:flex;align-items:baseline;gap:6px;margin-top:2px;flex-wrap:wrap;">
          <span style="font-size:0.78rem;color:#295738;">Collected: <strong id="pdfCol">₱0</strong></span>
          <span style="font-size:0.78rem;color:#888;" id="pdfColCount">0 records</span>
        </div>
      </div>
      <button class="btn btn-sm ms-3" onclick="exportPdf()"
              style="background:#efd772;border:2px solid #05590c;color:#05590c;font-size:0.75rem;padding:3px 10px;">Export</button>
    </div>
  </div>
</div>

<script>
function exportPdf() {
    var df = window.__eaDateFilter || {};
    var params = new URLSearchParams({ action: "export_pdf" });
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
            "Exported financial summary to PDF",
            null,
            null,
            { period: df.period, from: df.from, to: df.to },
        );
    }
}
function loadPdfCardData() {
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
            document.getElementById("pdfRev").textContent =
                "₱" + Number(dt.total_revenue || 0).toLocaleString();
            document.getElementById("pdfExp").textContent =
                "₱" + Number(dt.total_expenses || 0).toLocaleString();
            document.getElementById("pdfNet").textContent =
                "₱" + Number(dt.net_profit || 0).toLocaleString();
            document.getElementById("pdfExpTotal").textContent =
                "₱" + Number(dt.total_expenses || 0).toLocaleString();
            document.getElementById("pdfExpCat").textContent = (dt.expense_count || 0) + " entries";
            document.getElementById("pdfCol").textContent =
                "₱" + Number(dt.total_revenue || 0).toLocaleString();
            document.getElementById("pdfColCount").textContent =
                (dt.remittance_count || 0) + " records";
        });
}
document.addEventListener("DOMContentLoaded", loadPdfCardData);
</script>
