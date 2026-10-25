<div class="d-flex justify-content-between align-items-center mb-3 ea-header-row" style="flex-wrap:wrap;gap:8px;">
  <div class="fw-bold" style="color:#05590c;font-size:1rem;">
    <i class="bi bi-file-earmark-text me-1"></i> Generated Reports
  </div>
  <input type="text" id="reportSearch" placeholder="Search reports..." oninput="filterReports(this.value)"
         class="ea-search-input" style="width:160px;max-width:100%;min-width:0;box-sizing:border-box;padding:5px 10px;border:2px solid #e7e3af;border-radius:8px;font-size:0.78rem;outline:none;">
</div>

<div id="generatedReportsContainer" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;max-height:400px;overflow-y:auto;padding:4px;">
  <div style="text-align:center;padding:30px;color:#999;font-size:0.85rem;">Loading reports...</div>
</div>

<div class="modal fade" id="reportPreviewModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:20px;background:#fffdf6;border:1px solid #ece7c8;">
      <div class="modal-header" style="border-bottom:1px solid #ece7c8;padding:20px 24px;">
        <h5 style="margin:0;font-weight:800;color:#295738;" id="reportPreviewTitle">
          <i class="bi bi-file-earmark-text me-2" style="color:#57694d;"></i>Report Preview
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:24px;">
        <div id="reportPreviewContent" style="background:#fff;border:2px solid #e7e3af;border-radius:16px;padding:24px;font-size:0.92rem;line-height:1.8;white-space:pre-wrap;color:#333;max-height:65vh;overflow-y:auto;">Loading...</div>
      </div>
      <div class="modal-footer" style="border-top:1px solid #ece7c8;padding:16px 24px;gap:8px;">
        <button type="button" class="btn" onclick="exportReportTxt()"
                style="height:40px;border-radius:10px;background:#fff;border:1.5px solid #cdd9b5;color:#57694d;font-weight:600;font-size:0.82rem;"><i class="bi bi-download me-1"></i> TXT</button>
        <button type="button" onclick="exportReportPdf()"
                style="height:40px;border-radius:10px;background:#fff;border:1.5px solid #cdd9b5;color:#57694d;font-weight:600;font-size:0.82rem;"><i class="bi bi-file-pdf me-1"></i> PDF</button>
        <button type="button" onclick="exportReportExcel()"
                style="height:40px;border-radius:10px;background:#fff;border:1.5px solid #cdd9b5;color:#57694d;font-weight:600;font-size:0.82rem;"><i class="bi bi-file-earmark-excel me-1"></i> Excel</button>
        <button type="button" class="btn" data-bs-dismiss="modal"
                style="height:40px;border-radius:10px;background:#fff;border:1.5px solid #cdd9b5;color:#57694d;font-weight:600;font-size:0.82rem;">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
var allGeneratedReports = [],
    _currentFolder = "",
    _currentFile = "";
function loadGeneratedReports() {
    fetch("/finance/api/exportandaudits_api.php?action=get_generated_reports")
        .then(function (r) {
            return r.json();
        })
        .then(function (data) {
            var c = document.getElementById("generatedReportsContainer");
            if (!c) return;
            if (!data.success || !data.reports || !data.reports.length) {
                c.innerHTML =
                    '<div style="text-align:center;padding:30px;color:#999;font-size:0.85rem;">No generated reports. Generate one from Financial Reports first.</div>';
                return;
            }
            allGeneratedReports = data.reports;
            renderReportCards(data.reports);
        })
        .catch(function () {
            var c = document.getElementById("generatedReportsContainer");
            if (c)
                c.innerHTML =
                    '<div style="text-align:center;padding:30px;color:#dc3545;font-size:0.85rem;">Failed to load reports.</div>';
        });
}
function renderReportCards(reports) {
    var c = document.getElementById("generatedReportsContainer");
    if (!c) return;
    var html = "";
    reports.forEach(function (r) {
        html +=
            "<div onclick=\"previewReport('" +
            r.folder +
            "','" +
            r.file +
            '\')" style="background:#fff;border-radius:12px;border:2px solid #e7e3af;padding:14px;cursor:pointer;transition:0.2s;" onmouseover="this.style.borderColor=\'#295738\'" onmouseout="this.style.borderColor=\'#e7e3af\'">' +
            '<div style="background:#edf7ee;color:#05590c;padding:3px 10px;border-radius:999px;font-size:0.72rem;font-weight:700;display:inline-block;margin-bottom:8px;">' +
            (r.category || "General") +
            "</div>" +
            '<div style="font-weight:700;color:#05590c;margin-bottom:6px;font-size:0.88rem;">' +
            (r.title || "Report") +
            "</div>" +
            '<div style="color:#888;font-size:0.75rem;">' +
            (r.generated_at || "") +
            "</div></div>";
    });
    c.innerHTML = html;
}
function filterReports(val) {
    if (!val) {
        renderReportCards(allGeneratedReports);
        return;
    }
    renderReportCards(
        allGeneratedReports.filter(function (r) {
            return (
                (r.title || "").toLowerCase().indexOf(val.toLowerCase()) !== -1 ||
                (r.category || "").toLowerCase().indexOf(val.toLowerCase()) !== -1
            );
        }),
    );
}
function previewReport(folder, file) {
    _currentFolder = folder;
    _currentFile = file;
    var body = document.getElementById("reportPreviewContent");
    if (!body) return;
    body.innerHTML =
        '<div style="text-align:center;padding:20px;"><span class="spinner-border spinner-border-sm me-1"></span> Loading...</div>';
    new bootstrap.Modal(document.getElementById("reportPreviewModal")).show();
    fetch(
        "/finance/api/exportandaudits_api.php?action=get_generated_report&folder=" +
            encodeURIComponent(folder) +
            "&file=" +
            encodeURIComponent(file),
    )
        .then(function (r) {
            return r.json();
        })
        .then(function (data) {
            if (!data.success || !data.report) {
                body.innerHTML =
                    '<div style="color:#dc3545;text-align:center;padding:20px;">Report not found.</div>';
                return;
            }
            document.getElementById("reportPreviewTitle").innerHTML =
                '<i class="bi bi-file-earmark-text me-2" style="color:#57694d;"></i>' +
                (data.report.title || "Report");
            body.innerHTML =
                '<div style="font-size:0.92rem;line-height:1.8;white-space:pre-wrap;color:#333;">' +
                (data.report.content || "").replace(/</g, "&lt;").replace(/>/g, "&gt;") +
                "</div>";
        })
        .catch(function () {
            body.innerHTML =
                '<div style="color:#dc3545;text-align:center;padding:20px;">Failed to load report.</div>';
        });
}
function exportReportTxt() {
    if (!_currentFile) return;
    fetch(
        "/finance/api/exportandaudits_api.php?action=get_generated_report&folder=" +
            encodeURIComponent(_currentFolder) +
            "&file=" +
            encodeURIComponent(_currentFile),
    )
        .then(function (r) {
            return r.json();
        })
        .then(function (data) {
            if (!data.success || !data.report) return;
            var b = new Blob([data.report.content || ""], { type: "text/plain;charset=utf-8" }),
                a = document.createElement("a");
            a.href = URL.createObjectURL(b);
            a.download = _currentFile.replace(/\.[^.]+$/, ".txt");
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        });
}
function exportReportPdf() {
    if (!_currentFile) return;
    fetch(
        "/finance/api/exportandaudits_api.php?action=get_generated_report&folder=" +
            encodeURIComponent(_currentFolder) +
            "&file=" +
            encodeURIComponent(_currentFile),
    )
        .then(function (r) {
            return r.json();
        })
        .then(function (data) {
            if (!data.success || !data.report) return;
            var c = data.report.content || "",
                w = window.open("", "_blank");
            if (!w) {
                alert("Please allow popups.");
                return;
            }
            w.document.write(
                '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' +
                    (data.report.title || "Report") +
                    "</title><style>body{font-family:Segoe UI,Arial,sans-serif;color:#222;max-width:800px;margin:20px auto;padding:20px;line-height:1.7;}h1{color:#295738;border-bottom:2px solid #e7e3af;}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ddd;padding:6px 10px}th{background:#f0f7f0;color:#295738}.footer{margin-top:30px;font-size:11px;color:#999;text-align:center;border-top:1px solid #eee;padding-top:10px;}</style></head><body>",
            );
            w.document.write(
                '<div style="text-align:center;margin-bottom:20px;"><h1 style="border:none;">RPMVFMPC</h1><div style="color:#666;">' +
                    (data.report.title || "Report") +
                    '</div><div style="font-size:11px;color:#999;">Generated: ' +
                    new Date().toLocaleDateString() +
                    "</div></div>",
            );
            w.document.write(
                "<div>" +
                    c.replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\n/g, "<br>") +
                    "</div>",
            );
            w.document.write(
                '<div class="footer">RPMVFMPC &mdash; Confidential</div></body></html>',
            );
            w.document.close();
            setTimeout(function () {
                w.print();
            }, 500);
        });
}
function exportReportExcel() {
    if (!_currentFile) return;
    fetch(
        "/finance/api/exportandaudits_api.php?action=get_generated_report&folder=" +
            encodeURIComponent(_currentFolder) +
            "&file=" +
            encodeURIComponent(_currentFile),
    )
        .then(function (r) {
            return r.json();
        })
        .then(function (data) {
            if (!data.success || !data.report) return;
            var l = (data.report.content || "").split("\n"),
                csv =
                    "Report: " +
                    (data.report.title || "") +
                    "\nGenerated: " +
                    new Date().toLocaleString() +
                    "\n\n";
            l.forEach(function (line) {
                csv += line + "\n";
            });
            var b = new Blob([csv], { type: "text/csv;charset=utf-8" }),
                a = document.createElement("a");
            a.href = URL.createObjectURL(b);
            a.download = _currentFile.replace(/\.[^.]+$/, ".csv");
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        });
}
document.addEventListener("DOMContentLoaded", loadGeneratedReports);
</script>
