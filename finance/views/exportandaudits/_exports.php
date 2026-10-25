<div class="d-flex justify-content-between align-items-center mb-3 ea-header-row" style="flex-wrap:wrap;gap:8px;">
  <div>
    <div style="font-weight:700;font-size:1.1rem;color:#295738;">
      <i class="bi bi-download me-1"></i> Exports
    </div>
    <small style="color:#6b7280;font-size:0.75rem;display:block;margin-top:2px;max-width:720px;">
      Generate financial reports from saved records and review recorded export history. Uses the period selected in the page header.
    </small>
  </div>
  <input type="text" id="exportReportSearch" placeholder="Search reports..." oninput="filterExportReports(this.value)"
         class="ea-search-input" style="width:180px;max-width:100%;min-width:0;box-sizing:border-box;padding:6px 12px;border:2px solid #e7e3af;border-radius:8px;font-size:0.8rem;outline:none;">
</div>

<div id="exportReportsContainer" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;max-height:520px;overflow-y:auto;padding:4px;">
  <div style="text-align:center;padding:40px;color:#999;font-size:0.85rem;">Loading reports...</div>
</div>

<script>
var allExportReports = [];
var renderedExportReports = [];

function loadExportReports() {
    var params = new URLSearchParams({ action: "get_generated_reports" });
    var df = window.__eaDateFilter || {};
    if (df.period) params.set("period", df.period);
    if (df.period === "custom") {
        if (df.from) params.set("from", df.from);
        if (df.to) params.set("to", df.to);
    }
    fetch("/finance/api/exportandaudits_api.php?" + params.toString())
        .then(function (r) {
            return r.json();
        })
        .then(function (data) {
            var c = document.getElementById("exportReportsContainer");
            if (!c) return;
            if (!data.success || !data.reports || !data.reports.length) {
                allExportReports = [];
                c.innerHTML =
                    '<div style="text-align:center;padding:40px;color:#999;font-size:0.85rem;">No generated reports yet. Create one from Financial Reports first.</div>';
                return;
            }
            allExportReports = data.reports;
            renderExportCards(data.reports);
        })
        .catch(function () {
            var c = document.getElementById("exportReportsContainer");
            if (c)
                c.innerHTML =
                    '<div style="text-align:center;padding:40px;color:#dc3545;font-size:0.85rem;">Failed to load reports.</div>';
        });
}

function escapeReportHtml(value) {
    return String(value == null ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

function reportQuery(r) {
    if (r.source === "db" && r.id) {
        return "action=get_generated_report&id=" + encodeURIComponent(r.id);
    }
    return (
        "action=get_generated_report&folder=" +
        encodeURIComponent(r.folder || "") +
        "&file=" +
        encodeURIComponent(r.file || "")
    );
}

function reportDownloadName(r, ext) {
    if (r.source === "db") {
        var base = String(r.title || "report").replace(/[\\/:*?"<>|]+/g, " ").trim();
        return (base || "report") + "." + ext;
    }
    return String(r.file || "report").replace(/\.[^.]+$/, "." + ext);
}

function fetchReportContent(report, onSuccess) {
    fetch("/finance/api/exportandaudits_api.php?" + reportQuery(report))
        .then(function (r) {
            return r.json();
        })
        .then(function (data) {
            if (!data.success || !data.report) return;
            onSuccess(data.report);
        });
}

function renderExportCards(reports) {
    var c = document.getElementById("exportReportsContainer");
    if (!c) return;
    renderedExportReports = reports;
    var html = "";
    reports.forEach(function (r, i) {
        var archivedBadge = r.archived
            ? '<span style="background:#fdecea;color:#a3372a;padding:3px 10px;border-radius:999px;font-size:0.7rem;font-weight:700;display:inline-block;margin-left:6px;">Archived</span>'
            : "";
        html +=
            '<div style="background:#fff;border-radius:14px;border:2px solid #e7e3af;padding:16px;transition:0.2s;" onmouseover="this.style.borderColor=\'#295738\'" onmouseout="this.style.borderColor=\'#e7e3af\'">' +
            '<div style="margin-bottom:8px;">' +
            '<span style="background:#edf7ee;color:#05590c;padding:3px 10px;border-radius:999px;font-size:0.72rem;font-weight:700;display:inline-block;">' +
            escapeReportHtml(r.category || "General") +
            "</span>" +
            archivedBadge +
            "</div>" +
            '<div style="font-weight:700;color:#05590c;margin-bottom:4px;font-size:0.9rem;">' +
            escapeReportHtml(r.title || "Report") +
            "</div>" +
            '<div style="color:#888;font-size:0.75rem;">' +
            escapeReportHtml(r.generated_at || "") +
            "</div>" +
            (r.generated_by
                ? '<div style="color:#9aa79a;font-size:0.72rem;margin-top:2px;">By ' +
                  escapeReportHtml(r.generated_by) +
                  "</div>"
                : "") +
            '<div style="display:flex;gap:6px;margin-top:12px;">' +
            '<button type="button" data-export-report="' +
            i +
            '" data-format="pdf" style="flex:1;padding:6px 0;border:1.5px solid #cdd9b5;border-radius:8px;background:#fff;color:#57694d;font-size:0.75rem;font-weight:600;cursor:pointer;transition:0.2s;" onmouseover="this.style.borderColor=\'#295738\'" onmouseout="this.style.borderColor=\'#cdd9b5\'"><i class="bi bi-file-pdf me-1"></i> PDF</button>' +
            '<button type="button" data-export-report="' +
            i +
            '" data-format="excel" style="flex:1;padding:6px 0;border:1.5px solid #cdd9b5;border-radius:8px;background:#fff;color:#57694d;font-size:0.75rem;font-weight:600;cursor:pointer;transition:0.2s;" onmouseover="this.style.borderColor=\'#295738\'" onmouseout="this.style.borderColor=\'#cdd9b5\'"><i class="bi bi-file-earmark-excel me-1"></i> Excel</button>' +
            "</div></div>";
    });
    c.innerHTML = html;

    c.querySelectorAll("button[data-export-report]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var report = renderedExportReports[parseInt(btn.getAttribute("data-export-report"), 10)];
            if (!report) return;
            if (btn.getAttribute("data-format") === "excel") {
                exportReportExcel(report);
            } else {
                exportReportPdf(report);
            }
        });
    });
}

function filterExportReports(val) {
    if (!val) {
        renderExportCards(allExportReports);
        return;
    }
    var filtered = allExportReports.filter(function (r) {
        return (
            (r.title || "").toLowerCase().indexOf(val.toLowerCase()) !== -1 ||
            (r.category || "").toLowerCase().indexOf(val.toLowerCase()) !== -1
        );
    });
    renderExportCards(filtered);
}

function exportReportPdf(report) {
    fetchReportContent(report, function (data) {
        var c = data.content || "";
        var w = window.open("", "_blank");
        if (!w) {
            alert("Please allow popups.");
            return;
        }
        w.document.write(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' +
                (data.title || "Report") +
                "</title><style>" +
                "body{font-family:Segoe UI,Arial,sans-serif;color:#222;max-width:800px;margin:20px auto;padding:20px;line-height:1.7;}" +
                "h1{color:#295738;border-bottom:2px solid #e7e3af;}" +
                "table{border-collapse:collapse;width:100%}td,th{border:1px solid #ddd;padding:6px 10px}th{background:#f0f7f0;color:#295738}" +
                ".footer{margin-top:30px;font-size:11px;color:#999;text-align:center;border-top:1px solid #eee;padding-top:10px;}" +
                "</style></head><body>",
        );
        w.document.write(
            '<div style="text-align:center;margin-bottom:20px;"><h1 style="border:none;">RPMVFMPC</h1>' +
                '<div style="color:#666;">' +
                (data.title || "Report") +
                "</div>" +
                '<div style="font-size:11px;color:#999;">Generated: ' +
                (data.generated_at || new Date().toLocaleDateString()) +
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

function exportReportExcel(report) {
    fetchReportContent(report, function (data) {
        var lines = (data.content || "").split("\n");
        var csv =
            "Report: " + (data.title || "") + "\nGenerated: " + (data.generated_at || new Date().toLocaleString()) + "\n\n";
        lines.forEach(function (line) {
            csv += line + "\n";
        });
        var b = new Blob([csv], { type: "text/csv;charset=utf-8" });
        var a = document.createElement("a");
        a.href = URL.createObjectURL(b);
        a.download = reportDownloadName(report, "csv");
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(a.href);
    });
}

document.addEventListener("DOMContentLoaded", loadExportReports);
</script>
