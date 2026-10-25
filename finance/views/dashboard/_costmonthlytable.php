<div class="container mb-5"
     style="
        background:linear-gradient(180deg,#ffffff 0%,#faf6ed 100%);
        border:1px solid #e7e3af;
        border-radius:14px;
        padding:24px;
        box-shadow:0 8px 24px rgba(0,0,0,0.05);
 ">

  <div class="row g-3">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h4 class="fw-bold mb-1" style="color:#295738; font-size:1.15rem;">
                <i class="bi bi-calculator-fill text-success me-2"></i>Financial Operations Analytics
            </h4>
            <small style="color:#94a3b8; font-size:0.8rem;">
                Financial performance, operational efficiency, and demand insights
            </small>
        </div>

        <div class="d-inline-flex align-items-center gap-1" style="
            background:#e8f6ef;
            border:1px solid #d5efe3;
            padding:5px 14px;
            border-radius:20px;
            font-size:0.7rem;
            font-weight:600;
            color:#198754;
        ">
            <i class="bi bi-activity" style="font-size:0.75rem;"></i> Live Analytics
        </div>

    </div>

  </div>

  
  <?php include __DIR__ . '/_operations_analytics.php'; ?>

  
  <?php if (\App\Auth\Auth::role() === 'finance_manager'): ?>
  <div class="card shadow-sm p-4 mt-4" style="
      background:#fffdf2;
      border:1px solid #e7e3af;
      border-radius:14px;
  ">

    <div class="d-flex justify-content-between align-items-center mb-3">

      <h5 class="fw-semibold mb-0" style="font-size:0.85rem; color:#2f4f2f;">
        <i class="bi bi-exclamation-triangle-fill text-success me-2"></i>Missing Input Records
      </h5>

      <small style="color:#94a3b8; font-size:0.7rem;">
        Trips with incomplete or missing required data
      </small>

      <span id="issueCount" style="
          background:#dee4ba;
          color:#03580a;
          padding:6px 14px;
          border-radius:50px;
          font-size:.75rem;
          font-weight:600;
      ">
        0 Active Issues
      </span>

    </div>

    <div class="table-responsive">

      <table class="table align-middle" style="border:1px solid #e5e0a4;">

        <thead style="
            background:linear-gradient(180deg,#03580a 0%,#024d09 100%);
            color:white;
            font-size:.85rem;
            text-transform:uppercase;
            letter-spacing:.5px;
        ">
          <tr>
            <th>Driver</th>
            <th>Total Trips</th>
            <th>Status</th>
            <th>Handled By</th>
          </tr>
        </thead>

        <tbody id="missingTableBody">
          <tr id="missingLoadingRow">
            <td colspan="4">
              <div style="display:flex; align-items:center; justify-content:center; gap:10px; padding:14px 0;">
                <div style="width:14px;height:14px;border-radius:50%;border:2px solid #dce8b2;border-top-color:#198754;animation:oaSpin 0.9s linear infinite;"></div>
                <span style="font-size:.8rem; font-weight:600; color:#198754;">Loading missing records...</span>
              </div>
              <div style="padding:0 0 10px;">
                <div class="oa-skel" style="width:55%;height:12px;margin:0 auto 8px;"></div>
                <div class="oa-skel" style="width:70%;height:12px;margin:0 auto;"></div>
              </div>
            </td>
          </tr>
        </tbody>

      </table>

    </div>

  </div>
  <?php endif; ?>

</div>

<script>

async function fetchJSON(url){
    try{
        const res = await fetch(url);
        const text = await res.text();
        return JSON.parse(text);
    }catch(e){
        console.error("Fetch error:", e);
        return {success:false};
    }
}

async function loadFinanceAnalytics(query = ""){
    window.__faReq = (window.__faReq || 0) + 1;
    const req = window.__faReq;
    const data = await fetchJSON('/finance/api/dashboard/finance_analytics.php?' + query);

    if (req !== window.__faReq) return;

    if(!data.success){
        console.error("API ERROR:", data);
        return;
    }

    window.__setAiPageData && window.__setAiPageData('finance_analytics', data);

    const loadingRow = document.getElementById("missingLoadingRow");
    if(loadingRow) loadingRow.remove();

    <?php if (\App\Auth\Auth::role() === 'finance_manager'): ?>
    const tbody = document.getElementById("missingTableBody");

    if(data.missing?.length){

    tbody.innerHTML = data.missing.map(row=>{

    const status = row.missing_records > 0
    ? `<span style="background:rgba(255,193,7,0.2);color:#c48b00;padding:6px 12px;border-radius:50px;font-size:.75rem;">Incomplete</span>`
    : `<span style="background:rgba(76,175,80,0.2);color:#2e7d32;padding:6px 12px;border-radius:50px;font-size:.75rem;">Complete</span>`;

    return `
    <tr>
    <td class="fw-semibold" style="color:#295738;">${row.full_name}</td>
    <td>${row.trips}</td>
    <td>${status}</td>
    <td>System</td>
    </tr>
    `;

    }).join("");

    document.getElementById("issueCount").innerText =
    `${data.missing.length} Active Issues`;

    }else{

    tbody.innerHTML = `
    <tr>
    <td colspan="4" class="text-center text-muted">
    <i class="bi bi-check-circle-fill text-success me-1"></i> No missing records
    </td>
    </tr>
    `;

    document.getElementById("issueCount").innerText = "0 Active Issues";

    }

    <?php endif; ?>
}

document.addEventListener("DOMContentLoaded", function(){
    loadFinanceAnalytics();
});
</script>