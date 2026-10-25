<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="fw-bold" style="color:#05590c;font-size:1rem;">
    <i class="bi bi-journal-text me-1"></i> Audit Trail
  </div>
  <small style="color:#888;font-size:0.78rem;" id="auditTrailCount"></small>
</div>

<div class="d-flex gap-2 mb-3 flex-wrap align-items-end">
  <div>
    <input type="text" id="atSearch" class="form-control form-control-sm" placeholder="Search..." oninput="scheduleAT()"
           style="width:160px;border:2px solid #e7e3af;border-radius:8px;font-size:0.75rem;padding:4px 8px;">
  </div>
  <div>
    <select id="atAction" class="form-select form-select-sm" onchange="loadAT()"
            style="width:100px;border:2px solid #e7e3af;border-radius:8px;font-size:0.75rem;padding:4px 8px;">
      <option value="">Action</option>
    </select>
  </div>
  <div>
    <select id="atRole" class="form-select form-select-sm" onchange="loadAT()"
            style="width:100px;border:2px solid #e7e3af;border-radius:8px;font-size:0.75rem;padding:4px 8px;">
      <option value="">Role</option>
    </select>
  </div>
  <div>
    <select id="atStatus" class="form-select form-select-sm" onchange="loadAT()"
            style="width:90px;border:2px solid #e7e3af;border-radius:8px;font-size:0.75rem;padding:4px 8px;">
      <option value="">Status</option>
      <option value="Success">Success</option>
      <option value="Failed">Failed</option>
    </select>
  </div>
  <div>
    <select id="atSort" class="form-select form-select-sm" onchange="loadAT()"
            style="width:90px;border:2px solid #e7e3af;border-radius:8px;font-size:0.75rem;padding:4px 8px;">
      <option value="DESC">Newest</option>
      <option value="ASC">Oldest</option>
    </select>
  </div>
  <div>
    <button class="btn btn-sm" onclick="resetATFilters()"
            style="border:2px solid #e7e3af;border-radius:8px;font-size:0.75rem;padding:4px 10px;background:#f9f4da;font-weight:500;">Reset</button>
  </div>
</div>

<div style="background:#fef8f3;border:2px solid #cdd9b5;border-radius:20px;overflow:hidden;">
  <div class="table-responsive">
  <table class="table table-sm mb-0" style="font-size:0.78rem;">
    <thead style="color:#05590c;">
      <tr>
        <th style="width:50px;">ID</th>
        <th style="width:105px;">Date/Time</th>
        <th style="width:75px;">User</th>
        <th style="width:75px;">Role</th>
        <th style="width:75px;">Action</th>
        <th>Description</th>
        <th style="width:58px;">Status</th>
        <th style="width:48px;"></th>
        <th style="width:100px;">Device</th>
      </tr>
    </thead>
    <tbody id="atTableBody" style="font-size:0.76rem;">
      <tr><td colspan="9" style="text-align:center;color:#999;padding:30px;">Loading...</td></tr>
    </tbody>
  </table>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-2">
  <small style="color:#888;font-size:0.75rem;" id="atPageInfo"></small>
  <nav><ul class="pagination pagination-sm mb-0" id="atPagination"></ul></nav>
</div>

<div class="modal fade" id="atDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:20px;background:#fffdf6;border:1px solid #ece7c8;">
      <div class="modal-header" style="border-bottom:1px solid #ece7c8;padding:18px 22px;">
        <h5 style="margin:0;font-weight:800;color:#295738;font-size:1rem;" id="atDetailTitle">
          <i class="bi bi-info-circle me-2" style="color:#57694d;"></i>Audit Trail Detail
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:20px;" id="atDetailBody">Loading...</div>
      <div class="modal-footer" style="border-top:1px solid #ece7c8;padding:12px 20px;">
        <button type="button" class="btn" data-bs-dismiss="modal"
                style="height:38px;border-radius:10px;background:#fff;border:1.5px solid #cdd9b5;color:#57694d;font-weight:600;font-size:0.82rem;">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
var atPage = 1,
    atTimer = null;
function scheduleAT() {
    clearTimeout(atTimer);
    atTimer = setTimeout(function () {
        atPage = 1;
        loadAT();
    }, 400);
}
function resetATFilters() {
    ["atSearch", "atAction", "atRole", "atStatus"].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.value = "";
    });
    document.getElementById("atSort").value = "DESC";
    atPage = 1;
    loadAT();
}
function getActionBadge(action) {
    var map = {
        Create: "background:#d4edda;color:#155724;",
        Update: "background:#cce5ff;color:#004085;",
        Delete: "background:#f8d7da;color:#721c24;",
        Archive: "background:#fff3cd;color:#856404;",
        Restore: "background:#d4edda;color:#155724;",
        Login: "background:#e2e3f5;color:#383d41;",
        Logout: "background:#e2e3f5;color:#383d41;",
        Export: "background:#d1ecf1;color:#0c5460;",
        Import: "background:#d1ecf1;color:#0c5460;",
        Generate: "background:#f8d7da;color:#721c24;",
    };
    for (var k in map) {
        if (action.indexOf(k) !== -1)
            return (
                '<span class="badge" style="' + map[k] + ';font-size:0.7rem;">' + action + "</span>"
            );
    }
    return (
        '<span class="badge" style="background:#e9ecef;color:#495057;font-size:0.7rem;">' +
        action +
        "</span>"
    );
}
function getStatusBadge(s) {
    return (
        '<span class="badge" style="background:' +
        (s === "Success" ? "#d4edda;color:#155724;" : "#f8d7da;color:#721c24;") +
        ';font-size:0.7rem;">' +
        s +
        "</span>"
    );
}
function formatRole(r) {
    return { finance_manager: "Finance Manager", finance_officer: "Finance Officer" }[r] || r;
}
function parseDevice(ua) {
    if (!ua) return "-";
    var os = "",
        br = "";
    if (/Windows NT 10/.test(ua)) os = "Win10";
    else if (/Windows NT 6\.3/.test(ua)) os = "Win8.1";
    else if (/Windows NT 6\.1/.test(ua)) os = "Win7";
    else if (/Mac OS X/.test(ua)) os = "macOS";
    else if (/Android/.test(ua)) os = "Android";
    else if (/Linux/.test(ua)) os = "Linux";
    else if (/iPhone/.test(ua) || /iPad/.test(ua)) os = "iOS";
    if (/Chrome/.test(ua) && !/OPR/.test(ua)) {
        var m = ua.match(/Chrome\/([\d.]+)/);
        br = "Chrome " + (m ? m[1].split(".")[0] : "");
    } else if (/OPR/.test(ua)) {
        var m = ua.match(/OPR\/([\d.]+)/);
        br = "Opera " + (m ? m[1].split(".")[0] : "");
    } else if (/Firefox/.test(ua)) {
        var m = ua.match(/Firefox\/([\d.]+)/);
        br = "Firefox " + (m ? m[1].split(".")[0] : "");
    } else if (/Edg/.test(ua)) {
        var m = ua.match(/Edg\/([\d.]+)/);
        br = "Edge " + (m ? m[1].split(".")[0] : "");
    } else if (/Safari/.test(ua) && !/Chrome/.test(ua)) {
        var m = ua.match(/Version\/([\d.]+)/);
        br = "Safari " + (m ? m[1].split(".")[0] : "");
    }
    return (os ? os + " / " : "") + (br || "Unknown");
}
function loadAT() {
    var tbody = document.getElementById("atTableBody");
    if (!tbody) return;
    tbody.innerHTML =
        '<tr><td colspan="9" style="text-align:center;color:#999;padding:30px;">Loading...</td></tr>';
    var p = new URLSearchParams();
    p.append("api", "audit_trail_list");
    p.append("page", atPage);
    p.append("per_page", "25");
    p.append("sort", document.getElementById("atSort").value);
    var filterParams = {
        atSearch: "search",
        atAction: "act",
        atRole: "user_role",
        atStatus: "status",
    };
    Object.keys(filterParams).forEach(function (id) {
        var v = document.getElementById(id)?.value;
        if (v) p.append(filterParams[id], v);
    });
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
            var entries = (data.data || []).filter(function (e) {
                var t = (e.description || "").toLowerCase();
                return t.indexOf("ratelimit") === -1 && t.indexOf("rate limit") === -1;
            });
            document.getElementById("auditTrailCount").textContent = entries.length + " entries";
            if (!entries.length) {
                tbody.innerHTML =
                    '<tr><td colspan="9" style="text-align:center;color:#999;padding:30px;">No entries found.</td></tr>';
                return;
            }
            var html = "";
            entries.forEach(function (e) {
                html +=
                    '<tr><td style="font-weight:600;color:#295738;">#' +
                    e.id +
                    '</td><td style="white-space:nowrap;font-size:0.72rem;">' +
                    (e.created_at || "").replace(" ", "<br>") +
                    "</td><td>" +
                    (e.username || "-") +
                    "</td><td>" +
                    (formatRole(e.user_role) || "-") +
                    "</td>" +
                    "<td>" +
                    getActionBadge(e.action) +
                    "</td>" +
                    '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' +
                    (e.description || "-") +
                    "</td><td>" +
                    getStatusBadge(e.status) +
                    "</td>" +
                    '<td><button class="btn btn-sm" onclick="viewAT(' +
                    e.id +
                    ')" style="background:#efd772;border:2px solid #05590c;color:#05590c;font-size:0.7rem;padding:1px 6px;">View</button></td>' +
                    '<td style="font-size:0.7rem;color:#888;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' +
                    (e.user_agent || "") +
                    '">' +
                    parseDevice(e.user_agent) +
                    "</td></tr>";
            });
            tbody.innerHTML = html;
            document.getElementById("atPageInfo").textContent =
                "Page " + data.page + " of " + data.pages + " (" + entries.length + " entries)";
            var ph = "";
            if (data.pages > 1) {
                ph +=
                    '<li class="page-item' +
                    (data.page <= 1 ? " disabled" : "") +
                    '"><a class="page-link" href="#" onclick="atPage=' +
                    (data.page - 1) +
                    ';loadAT();return false;">&laquo;</a></li>';
                for (
                    var i = Math.max(1, data.page - 2);
                    i <= Math.min(data.pages, data.page + 2);
                    i++
                )
                    ph +=
                        '<li class="page-item' +
                        (i === data.page ? " active" : "") +
                        '"><a class="page-link" href="#" onclick="atPage=' +
                        i +
                        ';loadAT();return false;">' +
                        i +
                        "</a></li>";
                ph +=
                    '<li class="page-item' +
                    (data.page >= data.pages ? " disabled" : "") +
                    '"><a class="page-link" href="#" onclick="atPage=' +
                    (data.page + 1) +
                    ';loadAT();return false;">&raquo;</a></li>';
            }
            document.getElementById("atPagination").innerHTML = ph;
        })
        .catch(function () {
            tbody.innerHTML =
                '<tr><td colspan="9" style="text-align:center;color:#dc3545;padding:30px;">Network error.</td></tr>';
        });
}
function viewAT(id) {
    var body = document.getElementById("atDetailBody");
    body.innerHTML =
        '<div style="text-align:center;padding:20px;"><span class="spinner-border spinner-border-sm me-1"></span> Loading...</div>';
    new bootstrap.Modal(document.getElementById("atDetailModal")).show();
    fetch("/finance/api/exportandaudits_api.php?action=audit_trail_detail&id=" + id)
        .then(function (r) {
            return r.json();
        })
        .then(function (d) {
            if (!d.success || !d.entry) {
                body.innerHTML =
                    '<div style="color:#dc3545;text-align:center;padding:20px;">Not found.</div>';
                return;
            }
            var e = d.entry;
            document.getElementById("atDetailTitle").innerHTML =
                '<i class="bi bi-info-circle me-2" style="color:#57694d;"></i>Audit Trail #' + e.id;
            var html =
                '<div class="ea-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">' +
                '<div style="background:#faf9e5;border-radius:8px;padding:10px;"><div style="font-size:0.7rem;color:#888;">Date/Time</div><div style="font-weight:600;color:#295738;font-size:0.85rem;">' +
                (e.created_at || "-") +
                "</div></div>" +
                '<div style="background:#faf9e5;border-radius:8px;padding:10px;"><div style="font-size:0.7rem;color:#888;">Status</div><div>' +
                getStatusBadge(e.status) +
                "</div></div>" +
                '<div style="background:#faf9e5;border-radius:8px;padding:10px;"><div style="font-size:0.7rem;color:#888;">User</div><div style="font-weight:600;color:#295738;">' +
                (e.username || "-") +
                "</div></div>" +
                '<div style="background:#faf9e5;border-radius:8px;padding:10px;"><div style="font-size:0.7rem;color:#888;">Role</div><div style="font-weight:600;color:#295738;">' +
                (formatRole(e.user_role) || "-") +
                "</div></div>" +
                '<div style="background:#faf9e5;border-radius:8px;padding:10px;"><div style="font-size:0.7rem;color:#888;">Action</div><div>' +
                getActionBadge(e.action) +
                "</div></div>" +
                '<div style="background:#faf9e5;border-radius:8px;padding:10px;"><div style="font-size:0.7rem;color:#888;">Record ID</div><div style="font-weight:600;color:#295738;">' +
                (e.record_id || "-") +
                "</div></div>" +
                '<div style="background:#faf9e5;border-radius:8px;padding:10px;"><div style="font-size:0.7rem;color:#888;">IP Address</div><div style="font-weight:600;color:#295738;font-size:0.78rem;">' +
                (e.ip_address || "-") +
                "</div></div></div>" +
                '<div style="background:#faf9e5;border-radius:8px;padding:10px;margin-bottom:12px;"><div style="font-size:0.7rem;color:#888;">Description</div><div style="color:#333;font-size:0.85rem;">' +
                (e.description || "-") +
                "</div></div>";
            if (e.previous_values && typeof e.previous_values === "object")
                html +=
                    '<h6 style="color:#295738;font-size:0.85rem;font-weight:800;margin:12px 0 6px 0;"><i class="bi bi-arrow-left-circle me-1"></i> Previous Values</h6><div style="background:#fff;border:2px solid #e7e3af;border-radius:8px;padding:10px;margin-bottom:12px;max-height:200px;overflow-y:auto;font-size:0.78rem;"><pre style="margin:0;white-space:pre-wrap;">' +
                    JSON.stringify(e.previous_values, null, 2) +
                    "</pre></div>";
            if (e.new_values && typeof e.new_values === "object")
                html +=
                    '<h6 style="color:#295738;font-size:0.85rem;font-weight:800;margin:12px 0 6px 0;"><i class="bi bi-arrow-right-circle me-1"></i> New Values</h6><div style="background:#fff;border:2px solid #e7e3af;border-radius:8px;padding:10px;margin-bottom:12px;max-height:200px;overflow-y:auto;font-size:0.78rem;"><pre style="margin:0;white-space:pre-wrap;">' +
                    JSON.stringify(e.new_values, null, 2) +
                    "</pre></div>";
            if (e.user_agent)
                html +=
                    '<div style="background:#faf9e5;border-radius:8px;padding:10px;margin-top:12px;"><div style="font-size:0.7rem;color:#888;">Device</div><div style="font-weight:600;color:#295738;font-size:0.82rem;">' +
                    parseDevice(e.user_agent) +
                    '</div><div style="font-size:0.65rem;color:#aaa;margin-top:4px;word-break:break-all;">' +
                    e.user_agent +
                    "</div></div>";
            body.innerHTML = html;
        })
        .catch(function () {
            body.innerHTML =
                '<div style="color:#dc3545;text-align:center;padding:20px;">Failed to load.</div>';
        });
}
function loadATFilterOptions() {
    fetch("/finance/api/exportandaudits_api.php?action=audit_trail_filters")
        .then(function (r) {
            return r.json();
        })
        .then(function (d) {
            if (!d.success) return;
            var s2 = document.getElementById("atAction");
            d.actions.forEach(function (a) {
                s2.innerHTML += '<option value="' + a + '">' + a + "</option>";
            });
            var s3 = document.getElementById("atRole");
            d.roles.forEach(function (r) {
                s3.innerHTML += '<option value="' + r + '">' + formatRole(r) + "</option>";
            });
        });
}
document.addEventListener("DOMContentLoaded", function () {
    loadATFilterOptions();
    loadAT();
});
</script>
