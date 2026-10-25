<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <div class="fw-bold" style="color:#05590c;font-size:1rem;">
      <i class="bi bi-clock-history me-1"></i> Audits
    </div>
    <small style="color:#6b7280;font-size:0.75rem;display:block;margin-top:2px;max-width:720px;">
      Who submitted or verified remittances, handled discrepancies, exported reports, and generated audits.
    </small>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
  <div class="fw-bold" style="color:#05590c;font-size:0.9rem;">Recorded Financial Actions</div>
  <div class="d-flex align-items-center gap-2 ea-filter-row" style="flex-wrap:wrap;">
    <select id="feedTypeFilter" class="form-select form-select-sm" onchange="feedPage=1;loadFeed()"
            style="width:170px;border:2px solid #e7e3af;border-radius:8px;font-size:0.8rem;">
      <option value="">All actions</option>
      <option value="remittance">Remittances</option>
      <option value="discrepancy">Discrepancies</option>
      <option value="export">Reports &amp; exports</option>
      <option value="audit">Audit snapshots</option>
      <option value="admin">Administrative</option>
    </select>
    <input type="text" id="feedSearch" placeholder="Search actor, action, record..." oninput="scheduleFeed()"
           style="width:220px;max-width:100%;padding:6px 12px;border:2px solid #e7e3af;border-radius:8px;font-size:0.8rem;outline:none;">
  </div>
</div>
<div id="feedCoverageNote" style="font-size:0.74rem;color:#8a9a8a;margin-bottom:8px;"></div>

<div class="table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead style="color:#05590c;font-size:0.82rem;">
      <tr>
        <th style="width:130px;">When</th>
        <th>Action</th>
        <th>Actor</th>
        <th>Role</th>
        <th>Record</th>
        <th>Details</th>
        <th style="width:100px;">Amount</th>
        <th style="width:90px;">Status</th>
        <th style="width:120px;">Source</th>
      </tr>
    </thead>
    <tbody id="feedBody" style="font-size:0.78rem;">
      <tr><td colspan="9" style="text-align:center;color:#999;padding:30px;">Loading recorded actions...</td></tr>
    </tbody>
  </table>
</div>

<div class="d-flex justify-content-between align-items-center mt-2">
  <small style="color:#888;font-size:0.75rem;" id="feedPageInfo"></small>
  <nav><ul class="pagination pagination-sm mb-0" id="feedPagination"></ul></nav>
</div>

<div class="d-flex justify-content-between align-items-center mt-4 mb-2 flex-wrap gap-2">
  <div>
    <div class="fw-bold" style="color:#05590c;font-size:0.9rem;">Generated Financial Audits</div>
    <small style="color:#6b7280;font-size:0.74rem;display:block;margin-top:2px;">
      Financial snapshots created with Generate New Audit.
    </small>
  </div>
  <div class="d-flex align-items-center gap-2 ea-filter-row" style="flex-wrap:wrap;">
    <select id="auditStatusFilter" class="form-select form-select-sm" onchange="loadAudits()"
            style="width:120px;border:2px solid #e7e3af;border-radius:8px;font-size:0.8rem;">
      <option value="">All Status</option>
      <option value="Completed">Completed</option>
      <option value="Pending">Pending</option>
      <option value="Ongoing">Ongoing</option>
    </select>
    <button class="btn btn-sm d-flex align-items-center gap-1" onclick="generateAudit()"
            style="background:#fff;border:2px solid #e7e3af;border-radius:8px;font-size:0.8rem;color:#05590c;">
      <i class="bi bi-plus-lg"></i> Generate New Audit
    </button>
  </div>
</div>

<div class="table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead style="color:#05590c;font-size:0.85rem;">
      <tr>
        <th>Audit ID</th>
        <th>Date</th>
        <th>Type</th>
        <th>Performed By</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="auditTableBody" style="font-size:0.8rem;">
      <tr><td colspan="6" style="text-align:center;color:#999;padding:30px;">Loading audits...</td></tr>
    </tbody>
  </table>
</div>

<div class="modal fade" id="auditDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:20px;background:#fffdf6;border:1px solid #ece7c8;">
      <div class="modal-header" style="border-bottom:1px solid #ece7c8;padding:20px 24px;">
        <h5 style="margin:0;font-weight:800;color:#295738;" id="auditDetailTitle">
          <i class="bi bi-file-earmark-text me-2" style="color:#57694d;"></i>Audit Details
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:24px;" id="auditDetailBody">Loading...</div>
      <div class="modal-footer" style="border-top:1px solid #ece7c8;padding:16px 24px;">
        <button type="button" class="btn" data-bs-dismiss="modal"
                style="height:44px;border-radius:10px;background:#fff;border:1.5px solid #cdd9b5;color:#57694d;font-weight:600;">Close</button>
      </div>
    </div>
  </div>
</div>

<script>

function getStatusBadge(status) {
  var m = { 'Completed':'background:#d4edda;color:#05590c;', 'Pending':'background:#fff3cd;color:#05590c;', 'Ongoing':'background:#cfe2ff;color:#05590c;' };
  var s = m[status] || 'background:#e9ecef;color:#6c757d;';
  return '<span class="badge" style="' + s + '">' + status + '</span>';
}
function loadAudits() {
  var status = document.getElementById('auditStatusFilter').value;
  var tbody = document.getElementById('auditTableBody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;padding:30px;">Loading audits...</td></tr>';
  var url = '/finance/api/exportandaudits_api.php?action=list_audits';
  if (status) url += '&status=' + encodeURIComponent(status);
  var df = window.__eaDateFilter || {};
  if (df.period) url += '&period=' + encodeURIComponent(df.period);
  if (df.period === 'custom') {
    if (df.from) url += '&from=' + encodeURIComponent(df.from);
    if (df.to) url += '&to=' + encodeURIComponent(df.to);
  }
  fetch(url).then(function(r){return r.json();}).then(function(data){
    if (!data.success || !data.audits || !data.audits.length) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;padding:30px;">No audits found</td></tr>'; return;
    }
    var html = '';
    data.audits.forEach(function(a){
      var aid = 'AUD-' + String(a.id).padStart(3,'0');
      var d = a.generated_at ? a.generated_at.split(' ')[0] : '-';
      html += '<tr><td>'+aid+'</td><td>'+d+'</td><td>'+(a.audit_type||'-')+'</td><td>'+(a.performed_by||'-')+'</td><td>'+getStatusBadge(a.status)+'</td>'+
        '<td><button class="btn btn-sm" onclick="viewAuditDetails('+a.id+')" style="background:#efd772;border:2px solid #05590c;color:#05590c;font-size:0.75rem;padding:2px 8px;">View Details</button></td></tr>';
    });
    tbody.innerHTML = html;
  }).catch(function(){tbody.innerHTML='<tr><td colspan="6" style="text-align:center;color:#dc3545;padding:30px;">Failed to load audits.</td></tr>';});
}
function generateAudit() {
  showConfirm('Generate New Audit?', 'This will create a new audit with a snapshot of current financial data.', function() { doGenerateAudit(); });
}
function doGenerateAudit() {
  var btn = document.querySelector('[onclick="generateAudit()"]');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generating...'; }
  var fd = new FormData();
  fd.append('action','generate_audit'); fd.append('audit_type','Financial Audit');
  fd.append('performed_by','<?php echo isset($currentUser) ? htmlspecialchars($currentUser) : 'System'; ?>');
  fetch('/finance/api/exportandaudits_api.php?action=generate_audit',{method:'POST',body:fd})
    .then(function(r){return r.json();}).then(function(data){
      if (data.success) {
        showAlert('success', 'Audit Generated!', 'Audit #AUD-'+String(data.audit_id).padStart(3,'0')+' created.');
        loadAudits();
        if (typeof auditLog === 'function') auditLog('Finance','Generate','Generated financial audit #AUD-'+String(data.audit_id).padStart(3,'0'),'AUD-'+String(data.audit_id).padStart(3,'0'),null,{audit_type:'Financial Audit',snapshot:data.snapshot});
      } else {
        showAlert('error', 'Failed', data.message||'Could not generate audit.');
      }
    }).catch(function(){showAlert('error', 'Error', 'Network error.');})
    .finally(function(){if(btn){btn.disabled=false;btn.innerHTML='<i class="bi bi-plus-lg"></i> Generate New Audit';}});
}
function viewAuditDetails(id) {
  var body = document.getElementById('auditDetailBody');
  if (!body) return;
  body.innerHTML = '<div style="text-align:center;padding:20px;"><span class="spinner-border spinner-border-sm me-1"></span> Loading...</div>';
  new bootstrap.Modal(document.getElementById('auditDetailModal')).show();
  fetch('/finance/api/exportandaudits_api.php?action=get_audit&id='+id).then(function(r){return r.json();}).then(function(data){
    if (!data.success||!data.audit) { body.innerHTML='<div style="color:#dc3545;text-align:center;padding:20px;">Audit not found.</div>'; return; }
    var a = data.audit, aid='AUD-'+String(a.id).padStart(3,'0'), fd=a.financial_data||{};
    var html='<div class="ea-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">'+
      '<div style="background:#faf9e5;border-radius:10px;padding:14px;"><div style="font-size:0.78rem;color:#888;">Audit ID</div><div style="font-weight:800;color:#295738;">'+aid+'</div></div>'+
      '<div style="background:#faf9e5;border-radius:10px;padding:14px;"><div style="font-size:0.78rem;color:#888;">Status</div><div>'+getStatusBadge(a.status)+'</div></div>'+
      '<div style="background:#faf9e5;border-radius:10px;padding:14px;"><div style="font-size:0.78rem;color:#888;">Date</div><div style="font-weight:800;color:#295738;">'+(a.generated_at||'-')+'</div></div>'+
      '<div style="background:#faf9e5;border-radius:10px;padding:14px;"><div style="font-size:0.78rem;color:#888;">Type</div><div style="font-weight:800;color:#295738;">'+(a.audit_type||'-')+'</div></div></div>';
    if (fd.total_revenue!==undefined) {
      html+='<h6 style="color:#295738;font-weight:800;margin:16px 0 8px 0;">Financial Snapshot</h6>'+
        '<div class="ea-grid-3" style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px;">'+
        '<div style="background:#fff;border:2px solid #e7e3af;border-radius:10px;padding:14px;text-align:center;"><div style="font-size:0.78rem;color:#888;margin-bottom:4px;">Revenue</div><div style="font-size:1.2rem;font-weight:800;color:#295738;">₱'+Number(fd.total_revenue||0).toLocaleString()+'</div></div>'+
        '<div style="background:#fff;border:2px solid #e7e3af;border-radius:10px;padding:14px;text-align:center;"><div style="font-size:0.78rem;color:#888;margin-bottom:4px;">Expenses</div><div style="font-size:1.2rem;font-weight:800;color:#295738;">₱'+Number(fd.total_expenses||0).toLocaleString()+'</div></div>'+
        '<div style="background:#fff;border:2px solid #e7e3af;border-radius:10px;padding:14px;text-align:center;"><div style="font-size:0.78rem;color:#888;margin-bottom:4px;">Net Profit</div><div style="font-size:1.2rem;font-weight:800;color:'+((fd.net_profit||0)>=0?'#295738':'#dc3545')+';">₱'+Number(fd.net_profit||0).toLocaleString()+'</div></div></div>';
    }
    if (fd.snapshot_date) html+='<div style="font-size:0.78rem;color:#888;text-align:right;">Snapshot taken: '+fd.snapshot_date+'</div>';
    html+='<div style="background:#faf9e5;border-radius:10px;padding:14px;margin-top:16px;"><div style="font-size:0.78rem;color:#888;">Update Status</div><div style="display:flex;gap:8px;margin-top:8px;">'+
      '<select id="auditStatusSelect" style="flex:1;border:2px solid #cdd9b5;border-radius:8px;padding:8px;font-size:0.82rem;">'+
      '<option value="Pending"'+(a.status==='Pending'?' selected':'')+'>Pending</option>'+
      '<option value="Ongoing"'+(a.status==='Ongoing'?' selected':'')+'>Ongoing</option>'+
      '<option value="Completed"'+(a.status==='Completed'?' selected':'')+'>Completed</option>'+
      '</select>'+
      '<button onclick="updateAuditStatus('+a.id+')" style="background:#2e4836;color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:0.82rem;font-weight:600;cursor:pointer;">Update</button></div></div>';
    document.getElementById('auditDetailTitle').innerHTML='<i class="bi bi-file-earmark-text me-2" style="color:#57694d;"></i>Audit '+aid;
    body.innerHTML = html;
  }).catch(function(){body.innerHTML='<div style="color:#dc3545;text-align:center;padding:20px;">Failed to load audit details.</div>';});
}
function updateAuditStatus(id) {
  var sel = document.getElementById('auditStatusSelect');
  if (!sel) return;
  var status = sel.value;
  var fd = new FormData();
  fd.append('action','update_audit_status'); fd.append('id',id); fd.append('status',status);
  fetch('/finance/api/exportandaudits_api.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
    if(d.success){
      showAlert('success', 'Status Updated', 'Audit status set to '+status+'.');
      loadAudits();
    } else {
      showAlert('error', 'Failed', d.message);
    }
  }).catch(function(){
    showAlert('error', 'Error', 'Could not update status.');
  });
}
var feedPage = 1, feedTimer = null;
function scheduleFeed() {
  clearTimeout(feedTimer);
  feedTimer = setTimeout(function () { feedPage = 1; loadFeed(); }, 400);
}
function escapeFeedHtml(v) {
  return String(v == null ? "" : v).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}
function feedSourceLabel(source) {
  return {
    remittance_logs: "Remittance record",
    remittances: "Remittance record",
    discrepancy_status_history: "Discrepancy workflow",
    report_export_log: "Export log",
    audit_logs: "Audit snapshot",
    audit_trail: "Audit trail"
  }[source] || source || "-";
}
function loadFeed() {
  var tbody = document.getElementById("feedBody");
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#999;padding:30px;">Loading recorded actions...</td></tr>';
  document.getElementById("feedPageInfo").textContent = "";
  document.getElementById("feedPagination").innerHTML = "";
  var p = new URLSearchParams();
  p.append("action", "finance_audit_feed");
  p.append("page", feedPage);
  p.append("per_page", "25");
  var v = document.getElementById("feedTypeFilter").value;
  if (v) p.append("type", v);
  v = document.getElementById("feedSearch").value;
  if (v) p.append("search", v);
  var df = window.__eaDateFilter || {};
  if (df.period) p.append("period", df.period);
  if (df.period === "custom") {
    if (df.from) p.append("from", df.from);
    if (df.to) p.append("to", df.to);
  }
  fetch("/finance/api/exportandaudits_api.php?" + p.toString())
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data.success) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#dc3545;padding:30px;">Failed to load recorded actions: ' + escapeFeedHtml(data.message || "API error") + '</td></tr>';
        return;
      }
      var cov = data.coverage || {};
      var note = "";
      if (cov.first) {
        note = "Recorded actions available: " + cov.first + " to " + cov.last + " (" + (cov.months || []).join(", ") + ").";
        if (cov.missing_months && cov.missing_months.length) {
          note += " No recorded actions for: " + cov.missing_months.join(", ") + ".";
        }
      } else {
        note = "No recorded actions in the selected period.";
      }
      document.getElementById("feedCoverageNote").textContent = note;
      if (!data.events || !data.events.length) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#999;padding:30px;">No recorded financial actions for the selected period.</td></tr>';
        return;
      }
      var html = "";
      data.events.forEach(function (e) {
        var when = (e.when || "").replace("T", " ");
        var amount = (e.amount && Number(e.amount) > 0)
          ? "₱" + Number(e.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
          : "-";
        html += "<tr>"
          + '<td style="white-space:nowrap;font-size:0.74rem;">' + escapeFeedHtml(when) + "</td>"
          + "<td>" + escapeFeedHtml(e.action) + "</td>"
          + "<td>" + escapeFeedHtml(e.actor) + "</td>"
          + "<td>" + escapeFeedHtml(e.actor_role || "-") + "</td>"
          + "<td>" + escapeFeedHtml(e.record || "-") + "</td>"
          + '<td style="max-width:280px;">' + escapeFeedHtml(e.detail || "-") + "</td>"
          + '<td style="white-space:nowrap;">' + amount + "</td>"
          + "<td>" + escapeFeedHtml(e.status || "-") + "</td>"
          + '<td style="font-size:0.72rem;color:#888;">' + escapeFeedHtml(feedSourceLabel(e.source)) + "</td>"
          + "</tr>";
      });
      tbody.innerHTML = html;
      document.getElementById("feedPageInfo").textContent =
        "Page " + data.page + " of " + data.pages + " (" + data.total + " actions)";
      var ph = "";
      if (data.pages > 1) {
        ph += '<li class="page-item' + (data.page <= 1 ? " disabled" : "") + '"><a class="page-link" href="#" onclick="feedPage=' + (data.page - 1) + ';loadFeed();return false;">&laquo;</a></li>';
        for (var i = Math.max(1, data.page - 2); i <= Math.min(data.pages, data.page + 2); i++) {
          ph += '<li class="page-item' + (i === data.page ? " active" : "") + '"><a class="page-link" href="#" onclick="feedPage=' + i + ';loadFeed();return false;">' + i + "</a></li>";
        }
        ph += '<li class="page-item' + (data.page >= data.pages ? " disabled" : "") + '"><a class="page-link" href="#" onclick="feedPage=' + (data.page + 1) + ';loadFeed();return false;">&raquo;</a></li>';
      }
      document.getElementById("feedPagination").innerHTML = ph;
    })
    .catch(function () {
      tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#dc3545;padding:30px;">Failed to load recorded actions (network error).</td></tr>';
    });
}
document.addEventListener('DOMContentLoaded', function(){ loadAudits(); loadFeed(); });
</script>
