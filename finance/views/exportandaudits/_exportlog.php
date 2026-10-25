<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="fw-bold" style="color:#05590c;font-size:1rem;">
    <i class="bi bi-download me-1"></i> Report Export Log
  </div>
  <small style="color:#888;font-size:0.78rem;" id="exportLogCount"></small>
</div>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <small style="color:#05590c;font-weight:600;">Search</small>
    <input type="text" id="elSearch" class="form-control form-control-sm" placeholder="Title, username..." oninput="scheduleEL()"
           style="border:2px solid #e7e3af;border-radius:8px;font-size:0.78rem;">
  </div>
  <div class="col-6 col-md-2">
    <small style="color:#05590c;font-weight:600;">Format</small>
    <select id="elFormat" class="form-select form-select-sm" onchange="loadEL()"
            style="border:2px solid #e7e3af;border-radius:8px;font-size:0.78rem;">
      <option value="">All</option>
      <option value="Excel">Excel</option>
      <option value="PDF">PDF</option>
      <option value="CSV">CSV</option>
    </select>
  </div>
  <div class="col-6 col-md-2">
    <small style="color:#05590c;font-weight:600;">Report Type</small>
    <select id="elType" class="form-select form-select-sm" onchange="loadEL()"
            style="border:2px solid #e7e3af;border-radius:8px;font-size:0.78rem;">
      <option value="">All</option>
      <option value="Financial Report">Financial Report</option>
      <option value="AI Report">AI Report</option>
    </select>
  </div>
  <div class="col-6 col-md-1">
    <small style="color:#05590c;font-weight:600;">Sort</small>
    <select id="elSort" class="form-select form-select-sm" onchange="loadEL()"
            style="border:2px solid #e7e3af;border-radius:8px;font-size:0.78rem;">
      <option value="DESC">Newest</option>
      <option value="ASC">Oldest</option>
    </select>
  </div>
  <div class="col-6 col-md-1 d-flex align-items-end">
    <button class="btn btn-sm w-100" onclick="resetEL()"
            style="background:#fff;border:2px solid #e7e3af;border-radius:8px;font-size:0.78rem;color:#05590c;">
      <i class="bi bi-arrow-counterclockwise"></i>
    </button>
  </div>
</div>

<div class="table-responsive">
  <table class="table table-sm align-middle mb-0" style="font-size:0.78rem;">
    <thead style="color:#05590c;">
      <tr>
        <th style="width:50px;">#</th>
        <th>Report Title</th>
        <th style="width:90px;">Type</th>
        <th style="width:70px;">Format</th>
        <th style="width:90px;">User</th>
        <th style="width:80px;">Role</th>
        <th style="width:60px;">File</th>
        <th style="width:130px;">Date</th>
        <th style="width:130px;">Device</th>
      </tr>
    </thead>
    <tbody id="elBody" style="font-size:0.76rem;">
      <tr><td colspan="9" style="text-align:center;color:#999;padding:30px;">Loading...</td></tr>
    </tbody>
  </table>
</div>

<div class="d-flex justify-content-between align-items-center mt-2">
  <small style="color:#888;font-size:0.75rem;" id="elPageInfo"></small>
  <nav><ul class="pagination pagination-sm mb-0" id="elPagination"></ul></nav>
</div>

<script>
var elPage = 1,
    elTimer = null;
function scheduleEL() {
    clearTimeout(elTimer);
    elTimer = setTimeout(function () {
        elPage = 1;
        loadEL();
    }, 400);
}
function resetEL() {
    ["elSearch", "elFormat", "elType"].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.value = "";
    });
    document.getElementById("elSort").value = "DESC";
    elPage = 1;
    loadEL();
}
function loadEL() {
    var tbody = document.getElementById("elBody");
    if (!tbody) return;
    tbody.innerHTML =
        '<tr><td colspan="9" style="text-align:center;color:#999;padding:30px;">Loading...</td></tr>';
    var p = new URLSearchParams();
    p.append("action", "export_log_list");
    p.append("page", elPage);
    p.append("per_page", "25");
    p.append("sort", document.getElementById("elSort").value);
    var v = document.getElementById("elSearch").value;
    if (v) p.append("search", v);
    v = document.getElementById("elFormat").value;
    if (v) p.append("format", v);
    v = document.getElementById("elType").value;
    if (v) p.append("report_type", v);
    var df = window.__eaDateFilter || {};
    if (df.period) p.append("period", df.period);
    if (df.period === "custom") {
        if (df.from) p.append("from", df.from);
        if (df.to) p.append("to", df.to);
    }
    fetch("/finance/api/exportandaudits_api.php?" + p.toString())
        .then(function (r) {
            return r.json();
        })
        .then(function (data) {
            if (!data.success) {
                tbody.innerHTML =
                    '<tr><td colspan="9" style="text-align:center;color:#dc3545;padding:30px;">Error.</td></tr>';
                return;
            }
            document.getElementById("exportLogCount").textContent = data.total + " entries";
            if (!data.data || !data.data.length) {
                tbody.innerHTML =
                    '<tr><td colspan="9" style="text-align:center;color:#999;padding:30px;">No exports recorded for the selected period.</td></tr>';
                return;
            }
            var html = "";
            data.data.forEach(function (e) {
                var d = (e.created_at || "").split(" ");
                html +=
                    '<tr><td style="font-weight:600;color:#295738;">' +
                    e.id +
                    '</td><td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' +
                    (e.report_title || "-") +
                    "</td>" +
                    '<td><span class="badge" style="background:#e9ecef;color:#495057;font-size:0.7rem;">' +
                    (e.report_type || "-") +
                    "</span></td>" +
                    '<td><span class="badge" style="background:' +
                    (e.format === "PDF" ? "#f8d7da;color:#721c24;" : "#d1ecf1;color:#0c5460;") +
                    'font-size:0.7rem;">' +
                    (e.format || "") +
                    "</span></td>" +
                    "<td>" +
                    (e.username || "-") +
                    "</td><td>" +
                    (e.user_role || "-") +
                    "</td>" +
                    "<td>" +
                    (e.file_name
                        ? '<a href="#" onclick="return false;" style="color:#295738;">' +
                          e.file_name +
                          "</a>"
                        : "-") +
                    "</td>" +
                    '<td style="white-space:nowrap;font-size:0.72rem;">' +
                    (d[0] || "") +
                    "<br>" +
                    (d[1] || "") +
                    "</td>" +
                    '<td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.7rem;color:#888;">' +
                    (e.device ? "Shortened" : "-") +
                    "</td></tr>";
            });
            tbody.innerHTML = html;
            document.getElementById("elPageInfo").textContent =
                "Page " + data.page + " of " + data.pages + " (" + data.total + " entries)";
            var ph = "";
            if (data.pages > 1) {
                ph +=
                    '<li class="page-item' +
                    (data.page <= 1 ? " disabled" : "") +
                    '"><a class="page-link" href="#" onclick="elPage=' +
                    (data.page - 1) +
                    ';loadEL();return false;">&laquo;</a></li>';
                for (
                    var i = Math.max(1, data.page - 2);
                    i <= Math.min(data.pages, data.page + 2);
                    i++
                )
                    ph +=
                        '<li class="page-item' +
                        (i === data.page ? " active" : "") +
                        '"><a class="page-link" href="#" onclick="elPage=' +
                        i +
                        ';loadEL();return false;">' +
                        i +
                        "</a></li>";
                ph +=
                    '<li class="page-item' +
                    (data.page >= data.pages ? " disabled" : "") +
                    '"><a class="page-link" href="#" onclick="elPage=' +
                    (data.page + 1) +
                    ';loadEL();return false;">&raquo;</a></li>';
            }
            document.getElementById("elPagination").innerHTML = ph;
        })
        .catch(function () {
            tbody.innerHTML =
                '<tr><td colspan="9" style="text-align:center;color:#dc3545;padding:30px;">Network error.</td></tr>';
        });
}
document.addEventListener("DOMContentLoaded", loadEL);
</script>
