
<style>
.oa-loader {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid #dce8b2;
    border-top-color: #198754;
    animation: oaSpin 0.9s linear infinite;
    flex-shrink: 0;
}

@keyframes oaSpin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.oa-skel {
    position: relative;
    overflow: hidden;
    background: #ece7cf;
    border-radius: 8px;
}

.oa-skel::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: 40%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.65), transparent);
    animation: oaShimmer 1.3s infinite;
}

@keyframes oaShimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(260%);
    }
}

.oa-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #198754;
}
</style>
<div class="row g-2 align-items-end mb-2 mt-1">

  <div class="col-12 col-md-4">
    <label class="form-label small fw-semibold mb-1" style="color:#295738;">Compare:</label>
    <select id="opsPeriod" class="form-select form-select-sm">
      <option value="ytd" selected>Year to Date</option>
      <option value="last6">Last 6 Months</option>
      <option value="last12">Last 12 Months</option>
      <option value="month">This Month</option>
      <option value="custom">Custom Date Range</option>
    </select>
    <div id="opsCurCustomWrap" class="d-none mt-2">
      <div class="small mb-1" style="font-size:.68rem; color:#295738;"><i class="bi bi-calendar3 me-1"></i>Current period dates</div>
      <div class="d-flex gap-2">
        <span class="input-group input-group-sm d-flex align-items-center">
          <span class="input-group-text" style="font-size:.7rem; background:#f3f7dc; border-color:#dee4ba; color:#295738;">From</span>
          <input type="date" id="opsCurFrom" class="form-control form-control-sm" title="Current period start date">
        </span>
        <span class="input-group input-group-sm d-flex align-items-center">
          <span class="input-group-text" style="font-size:.7rem; background:#f3f7dc; border-color:#dee4ba; color:#295738;">To</span>
          <input type="date" id="opsCurTo" class="form-control form-control-sm" title="Current period end date">
        </span>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-4">
    <label class="form-label small fw-semibold mb-1" style="color:#295738;">With:</label>
    <select id="opsAgainst" class="form-select form-select-sm"></select>
    <div id="opsPrevCustomWrap" class="d-none mt-2">
      <div class="small mb-1" style="font-size:.68rem; color:#c48b00;"><i class="bi bi-calendar3 me-1"></i>Comparison period dates</div>
      <div class="d-flex gap-2">
        <span class="input-group input-group-sm d-flex align-items-center">
          <span class="input-group-text" style="font-size:.7rem; background:#f3f7dc; border-color:#dee4ba; color:#295738;">From</span>
          <input type="date" id="opsPrevFrom" class="form-control form-control-sm" title="Comparison period start date">
        </span>
        <span class="input-group input-group-sm d-flex align-items-center">
          <span class="input-group-text" style="font-size:.7rem; background:#f3f7dc; border-color:#dee4ba; color:#295738;">To</span>
          <input type="date" id="opsPrevTo" class="form-control form-control-sm" title="Comparison period end date">
        </span>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-4">
    <button id="opsCompareBtn" class="btn btn-success w-100" style="background:#03580a; border-color:#03580a;">
      <i class="bi bi-arrow-repeat me-1"></i>Compare
    </button>
    <div id="opsCustomHint" class="d-none mt-2 small text-danger" style="font-size:.7rem;">
      <i class="bi bi-exclamation-circle me-1"></i>Fill all four date fields (current and comparison periods) to run the comparison.
    </div>
  </div>

</div>


<div id="opsRangeCaption" class="small mb-3 text-muted" style="font-size:.75rem; color:#94a3b8;">
  <span class="oa-chip"><div class="oa-loader"></div> Loading date ranges...</span>
</div>


<div id="opsEmptyState" class="text-center text-muted py-5 d-none">
  <i class="bi bi-inbox" style="font-size:2rem; color:#dee4ba;"></i>
  <div class="mt-2">No data available for the selected periods.</div>
</div>

<div id="opsSections">

  
  <div id="opsSummaryView" class="d-none">
    <div class="card shadow-sm p-3 mb-3" style="background:#fffdf2; border:1px solid #e7e3af; border-radius:14px;">
      <h6 id="opsSummaryTitle" class="fw-semibold mb-0" style="font-size:0.8rem; color:#2f4f2f;">
        <i class="bi bi-arrow-left-right text-success me-2"></i>Performance Summary
      </h6>
      <small id="opsSummarySubtitle" class="d-block" style="color:#94a3b8; font-size:.7rem;">
        Total performance for the selected periods
      </small>

      <div class="row g-2 mt-2" id="opsComparisonGrid">
        <div class="col-12 text-center text-muted py-3">
          <div class="oa-chip"><div class="oa-loader"></div> Loading analytics...</div>
        </div>
      </div>

      
      <div class="table-responsive mt-2">
        <table class="table table-sm table-bordered align-middle mb-0" id="opsComparisonTable" style="font-size:.72rem; border-color:#dee4ba;">
          <thead>
            <tr style="background:#f3f7dc; color:#295738;">
              <th class="fw-semibold px-2">Metric</th>
              <th class="fw-semibold px-2 text-end">Current Period</th>
              <th class="fw-semibold px-2 text-end">Previous Period</th>
              <th class="fw-semibold px-2 text-end">Change</th>
              <th class="fw-semibold px-2 text-end">% Change</th>
            </tr>
          </thead>
          <tbody id="opsComparisonTableBody"></tbody>
        </table>
      </div>

      
      <div id="opsMeaningWrap" class="mt-3 d-none">
        <div class="row g-2">
          <div class="col-12 col-xl-7">
            <div class="h-100 p-3" style="background:#f3f7dc; border:1px solid #dee4ba; border-radius:12px;">
              <div class="fw-semibold mb-2" style="font-size:.75rem; color:#295738;">
                <i class="bi bi-lightbulb text-success me-1"></i>What this means
              </div>
              <ul id="opsMeaningList" class="list-unstyled mb-0" style="font-size:.72rem;"></ul>
            </div>
          </div>
          <div class="col-12 col-xl-5 d-flex flex-column gap-2">
            <button id="opsDetailsBtn" class="btn btn-sm btn-outline-success flex-grow-1" style="border-color:#03580a; color:#03580a; font-size:.75rem;">
              <i class="bi bi-graph-up me-1"></i>View Monthly Details
            </button>
            <div id="opsTrendWrap" class="d-none p-3" style="background:#fffdf2; border:1px solid #e7e3af; border-radius:12px;">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold" style="font-size:.72rem; color:#295738;">Monthly Performance Trend</span>
                <select id="opsTrendMetric" class="form-select form-select-sm" style="width:auto; font-size:.72rem;">
                  <option value="revenue">Revenue</option>
                  <option value="passengers">Passengers</option>
                  <option value="trips">Trips</option>
                  <option value="discrepancies">Discrepancies</option>
                </select>
              </div>
              <canvas id="opsTrendChart" style="max-height:220px;"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  
  <div id="opsPairView" class="d-none">
    <div class="card shadow-sm p-3 mb-3" style="background:#fffdf2; border:1px solid #e7e3af; border-radius:14px;">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
        <div>
          <h6 id="opsPairTitle" class="fw-semibold mb-0" style="font-size:0.8rem; color:#2f4f2f;">
            <i class="bi bi-bar-chart-line text-success me-2"></i>6-Month Comparison
          </h6>
          <small id="opsPairSubtitle" class="d-block" style="color:#94a3b8; font-size:.7rem;">
            Month-by-month comparison of the selected periods
          </small>
        </div>
        <select id="opsMetricSelect" class="form-select form-select-sm" style="width:auto; font-size:.75rem;">
          <option value="revenue">Revenue</option>
          <option value="passengers">Passengers</option>
          <option value="trips">Trips</option>
          <option value="discrepancies">Discrepancies</option>
        </select>
      </div>

      <div class="row g-2 mt-2" id="opsPairSummary"></div>

      <div class="table-responsive mt-2">
        <table class="table table-sm table-bordered align-middle mb-0" id="opsPairTable" style="font-size:.72rem; border-color:#dee4ba;">
          <thead>
            <tr style="background:#f3f7dc; color:#295738;">
              <th class="fw-semibold px-2">Metric</th>
              <th class="fw-semibold px-2 text-end">Current Period</th>
              <th class="fw-semibold px-2 text-end">Previous Period</th>
              <th class="fw-semibold px-2 text-end">Change</th>
              <th class="fw-semibold px-2 text-end">% Change</th>
            </tr>
          </thead>
          <tbody id="opsPairTableBody"></tbody>
        </table>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-12 col-xl-6">
          <div class="p-3" style="background:#fffdf2; border:1px solid #e7e3af; border-radius:12px;">
            <div class="fw-semibold mb-1 text-center" id="opsCurChartTitle" style="font-size:.72rem; color:#295738;">Current Period</div>
            <canvas id="opsCurChart" style="max-height:220px;"></canvas>
          </div>
        </div>
        <div class="col-12 col-xl-6">
          <div class="p-3" style="background:#fffdf2; border:1px solid #e7e3af; border-radius:12px;">
            <div class="fw-semibold mb-1 text-center" id="opsPrevChartTitle" style="font-size:.72rem; color:#295738;">Previous Period</div>
            <canvas id="opsPrevChart" style="max-height:220px;"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  
  <div class="card shadow-sm p-3 mb-3" style="background:#fffdf2; border:1px solid #e7e3af; border-radius:14px;">
    <h6 class="fw-semibold mb-0" style="font-size:0.8rem; color:#2f4f2f;">
      <i class="bi bi-speedometer2 text-success me-2"></i>Operational Performance
    </h6>
    <small class="d-block" style="color:#94a3b8; font-size:.7rem;">
      Key efficiency indicators
    </small>
    <div class="row g-2 mt-2 row-cols-2 row-cols-lg-3 row-cols-xxl-5" id="opsOperationalGrid">
      <div class="col-12 text-center text-muted py-3">
        <div class="oa-chip"><div class="oa-loader"></div> Loading analytics...</div>
      </div>
    </div>
  </div>

  
  <div class="card shadow-sm p-3 mb-3" style="background:#fffdf2; border:1px solid #e7e3af; border-radius:14px;">
    <h6 class="fw-semibold mb-0" style="font-size:0.8rem; color:#2f4f2f;">
      <i class="bi bi-people-fill text-success me-2"></i>Passenger Demand Summary
    </h6>
    <small class="d-block" style="color:#94a3b8; font-size:.7rem;">
      Total passengers, daily average, and highest/lowest demand
    </small>

    <div class="row g-2 mt-2">
      <div class="col-6 col-md-3">
        <div class="text-center p-3" style="background:#f3f7dc; border:1px solid #dee4ba; border-radius:12px; height:100%;">
          <div class="small text-muted" style="font-size:.68rem;">Total Passengers</div>
          <div class="fw-bold mt-1" id="opsDemandTotal" style="color:#03580a; font-size:1.1rem;">—</div>
          <div class="text-muted" style="font-size:.65rem;" id="opsDemandTotalCaption">selected period</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="text-center p-3" style="background:#f3f7dc; border:1px solid #dee4ba; border-radius:12px; height:100%;">
          <div class="small text-muted" style="font-size:.68rem;">Average Passengers / Day</div>
          <div class="fw-bold mt-1" id="opsDemandAvgDay" style="color:#03580a; font-size:1.1rem;">—</div>
          <div class="text-muted" style="font-size:.65rem;">passengers</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="text-center p-3" style="background:#f3f7dc; border:1px solid #dee4ba; border-radius:12px; height:100%;">
          <div class="small text-muted" style="font-size:.68rem;">Highest Demand Month</div>
          <div class="fw-bold mt-1" id="opsDemandHighest" style="color:#03580a; font-size:.95rem;">—</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="text-center p-3" style="background:#f3f7dc; border:1px solid #dee4ba; border-radius:12px; height:100%;">
          <div class="small text-muted" style="font-size:.68rem;">Lowest Demand Month</div>
          <div class="fw-bold mt-1" id="opsDemandLowest" style="color:#c48b00; font-size:.95rem;">—</div>
        </div>
      </div>
    </div>

    <div class="row g-3 mt-1">
      <div class="col-12 col-xl-7">
        <div class="p-3" style="background:#fffdf2; border:1px solid #e7e3af; border-radius:12px;">
          <canvas id="opsDemandChart" style="max-height:220px;"></canvas>
        </div>
      </div>
      <div class="col-12 col-xl-5">
        <div class="mt-3 d-none p-3" id="opsPeakWrap" style="background:#fffdf2; border:1px solid #e7e3af; border-radius:12px;">
          <div class="small fw-semibold mb-1" style="color:#295738; font-size:.75rem;">Peak Periods</div>
          <div id="opsPeakSplit" class="d-flex flex-column gap-2"></div>
          <div class="small text-muted mt-2" style="font-size:.65rem;">
            <i class="bi bi-info-circle me-1"></i>Based on available trip time data (live &amp; archived)
          </div>
        </div>
      </div>
    </div>
  </div>

  
  <div class="card shadow-sm p-3 mb-3" style="background:#fffdf2; border:1px solid #e7e3af; border-radius:14px;" id="opsFuelAnalyticsSection">
    
    
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
      <div>
        <h6 class="fw-semibold mb-0" style="font-size:0.82rem; color:#2f4f2f;">
          <i class="bi bi-fuel-pump-fill text-success me-2"></i>Fuel Consumption &amp; Estimated Cost Analytics
        </h6>
        <small class="d-block" style="color:#94a3b8; font-size:.7rem;">
          Fuel consumption (L), estimated fuel costs (₱), and actual refuel tracking across the fleet
        </small>
      </div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge" id="opsFuelAvgTripBadge" style="background:#e8f5e9; color:#1b5e20; border:1px solid #c8e6c9; font-weight:600; font-size:0.75rem; padding:6px 12px; border-radius:8px;">
          Avg Consumed / Trip: —
        </span>
        <span class="badge" id="opsFuelTotalBadge" style="background:#f3f7dc; color:#295738; border:1px solid #dee4ba; font-weight:600; font-size:0.75rem; padding:6px 12px; border-radius:8px;">
          Total Est. Cost: —
        </span>
        <span class="badge" id="opsFuelAvgKplBadge" style="background:#fef3c7; color:#92400e; border:1px solid #fde68a; font-weight:600; font-size:0.75rem; padding:6px 12px; border-radius:8px;">
          Actual Refuels: —
        </span>
      </div>
    </div>

    
    <div class="row g-3 mt-1">
      <div class="col-12">
        <div class="p-3 position-relative" style="background:#fffdf2; border:1px solid #e7e3af; border-radius:12px;">
          <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-1">
            <span class="fw-semibold" style="font-size:.75rem; color:#295738;">
              <i class="bi bi-graph-up-arrow text-success me-1"></i>Estimated Fuel Cost Trend
            </span>
            <small class="text-muted" style="font-size:.68rem;">
              Temporal trend of estimated fuel cost (₱) based on trip distance, KPL, and fuel price
            </small>
          </div>
          <div style="position:relative; min-height:220px;">
            <canvas id="opsFuelTrendChart" style="max-height:230px;"></canvas>
          </div>
        </div>
      </div>
    </div>

    
    <div class="row g-3 mt-1">
      
      <div class="col-12 col-xl-6">
        <div class="p-3 position-relative h-100" style="background:#fffdf2; border:1px solid #e7e3af; border-radius:12px;">
          <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-1">
            <span class="fw-semibold" style="font-size:.75rem; color:#295738;">
              <i class="bi bi-bar-chart-fill text-success me-1"></i>Estimated Fuel Cost by Bus
            </span>
            <small class="text-muted" style="font-size:.68rem;">Total estimated fuel cost per bus (₱)</small>
          </div>
          <div style="position:relative; min-height:260px;">
            <canvas id="opsFuelBusChart" style="max-height:270px;"></canvas>
          </div>
        </div>
      </div>

      
      <div class="col-12 col-xl-6">
        <div class="p-3 position-relative h-100" style="background:#fffdf2; border:1px solid #e7e3af; border-radius:12px;">
          <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-1">
            <span class="fw-semibold" style="font-size:.75rem; color:#295738;">
              <i class="bi bi-arrow-left-right text-success me-1"></i>Estimated vs Actual Fuel Cost
            </span>
            
            <div class="d-flex align-items-center gap-2" style="font-size:.68rem;">
              <span class="d-inline-flex align-items-center gap-1">
                <span style="width:10px; height:10px; background:#295738; border-radius:2px; display:inline-block;"></span>
                <span style="color:#295738; font-weight:600;">Estimated Cost</span>
              </span>
              <span class="d-inline-flex align-items-center gap-1">
                <span style="width:10px; height:10px; background:#c48b00; border-radius:2px; display:inline-block;"></span>
                <span style="color:#c48b00; font-weight:600;">Actual Cost</span>
              </span>
            </div>
          </div>
          <div style="position:relative; min-height:260px;">
            <canvas id="opsFuelEffChart" style="max-height:270px;"></canvas>
          </div>
        </div>
      </div>
    </div>

  </div>

</div>

<script>
let opsCurChart = null;
let opsPrevChart = null;
let opsTrendChart = null;
let opsDemandChart = null;
let opsFuelTrendChart = null;
let opsFuelBusChart = null;
let opsFuelEffChart = null;
let opsFuelAbort = null;
let opsState = { metric: "revenue" };

function opsLoadingHTML() {
    return `<div class="text-center" style="padding:6px 0;">
        <div class="oa-chip"><div class="oa-loader"></div> Loading analytics...</div>
        <div class="row g-2 mt-2">
            ${[1, 2, 3, 4]
                .map(
                    () => `
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="p-3" style="background:#fffdf2; border:1px solid #e7e3af; border-radius:12px;">
                    <div class="oa-skel" style="width:45%;height:11px;margin-bottom:10px;"></div>
                    <div class="oa-skel" style="width:60%;height:18px;margin-bottom:10px;"></div>
                    <div class="oa-skel" style="width:80%;height:9px;"></div>
                </div>
            </div>`,
                )
                .join("")}
        </div>
    </div>`;
}

const OPS_AGAINST_OPTIONS = {
    ytd: [{ value: "prev_year", label: "Previous Year" }],
    last6: [
        { value: "prev6", label: "Previous 6 Months" },
        { value: "yoy_6", label: "Same 6 Months Last Year" },
    ],
    last12: [
        { value: "prev12", label: "Previous 12 Months" },
        { value: "yoy_12", label: "Same 12 Months Last Year" },
    ],
    month: [
        { value: "prev_month", label: "Previous Month" },
        { value: "yoy_month", label: "Same Month Last Year" },
    ],
    custom: [{ value: "custom", label: "Custom Date Range" }],
};

function opsFmtMoney(v) {
    if (v === null || v === undefined || isNaN(v)) return "—";
    return (
        "₱" +
        Number(v).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    );
}

function opsFmtInt(v) {
    if (v === null || v === undefined || isNaN(v)) return "—";
    return Number(v).toLocaleString("en-PH");
}

function opsFmtNum(v, decimals) {
    if (v === null || v === undefined || isNaN(v)) return "—";
    return Number(v).toLocaleString("en-PH", {
        minimumFractionDigits: decimals || 0,
        maximumFractionDigits: decimals || 0,
    });
}

function opsFmtPct(v) {
    if (v === null || v === undefined || isNaN(v)) return "—";
    const sign = v > 0 ? "+" : "";
    return sign + Number(v).toLocaleString("en-PH", { maximumFractionDigits: 2 }) + "%";
}

function opsMonthSpan(from, to) {
    const months = [
        "Jan",
        "Feb",
        "Mar",
        "Apr",
        "May",
        "Jun",
        "Jul",
        "Aug",
        "Sep",
        "Oct",
        "Nov",
        "Dec",
    ];
    const f = new Date(from + "T00:00:00");
    const t = new Date(to + "T00:00:00");
    if (isNaN(f) || isNaN(t)) {
        return (from || "?") + " – " + (to || "?");
    }
    if (f.getFullYear() === t.getFullYear()) {
        return months[f.getMonth()] + "–" + months[t.getMonth()] + " " + f.getFullYear();
    }
    return (
        months[f.getMonth()] +
        " " +
        f.getFullYear() +
        "–" +
        months[t.getMonth()] +
        " " +
        t.getFullYear()
    );
}

function opsFmtDate(ymd) {
    const months = [
        "Jan",
        "Feb",
        "Mar",
        "Apr",
        "May",
        "Jun",
        "Jul",
        "Aug",
        "Sep",
        "Oct",
        "Nov",
        "Dec",
    ];
    const d = new Date(ymd + "T00:00:00");
    if (isNaN(d)) return ymd || "?";
    return months[d.getMonth()] + " " + d.getDate() + ", " + d.getFullYear();
}

function opsMonthLabel(ymd) {
    const months = [
        "January",
        "February",
        "March",
        "April",
        "May",
        "June",
        "July",
        "August",
        "September",
        "October",
        "November",
        "December",
    ];
    const d = new Date(ymd + "T00:00:00");
    if (isNaN(d)) return ymd || "?";
    return months[d.getMonth()] + " " + d.getFullYear();
}

function opsValueFmt(metric, v) {
    if (metric === "revenue") return opsFmtMoney(v);
    return opsFmtInt(v);
}

function opsRenderRangeCaption(data) {
    const cur = data.current_range;
    const prev = data.previous_range;
    const el = document.getElementById("opsRangeCaption");
    let notice = "";
    if (document.getElementById("opsAgainst").value === "custom" && prev.label === "Custom Range") {
        const wantFrom = document.getElementById("opsPrevFrom").value;
        const wantTo = document.getElementById("opsPrevTo").value;
        if (wantFrom && wantTo && (wantFrom !== prev.from || wantTo !== prev.to)) {
            notice = ` <span class="ms-1" style="color:#c48b00;">(comparison dates not usable — using ${opsFmtDate(prev.from)} – ${opsFmtDate(prev.to)})</span>`;
        }
    }
    el.innerHTML = `${opsFmtDate(cur.from)} – ${opsFmtDate(cur.to)} <span class="mx-1">vs</span> ${opsFmtDate(prev.from)} – ${opsFmtDate(prev.to)}${notice}`;
}

const opsComparisonCards = [
    { key: "revenue", label: "Revenue", icon: "bi-cash-stack", fmt: opsFmtMoney, invert: false },
    {
        key: "passengers",
        label: "Passengers",
        icon: "bi-people-fill",
        fmt: opsFmtInt,
        invert: false,
    },
    { key: "trips", label: "Trips", icon: "bi-signpost-split", fmt: opsFmtInt, invert: false },
    {
        key: "discrepancies",
        label: "Discrepancies",
        icon: "bi-exclamation-triangle-fill",
        fmt: opsFmtInt,
        invert: true,
    },
];

function opsComparisonStatus(percent, invert) {
    if (percent === null || percent === undefined || isNaN(percent)) {
        return `<span class="text-muted" style="font-size:.68rem;">No comparison data</span>`;
    }
    if (percent === 0) {
        return `<span class="text-muted" style="font-size:.72rem;">No change</span>`;
    }
    const up = percent > 0;
    const good = invert ? !up : up;
    const label = invert ? (up ? "Increased" : "Improved") : up ? "Increase" : "Decrease";
    const color = good ? "#198754" : "#dc3545";
    const arrow = up ? "↑" : "↓";
    return `<span style="color:${color}; font-weight:600; font-size:.9rem;">${arrow} ${opsFmtPct(percent)}</span>
            <span style="color:${color}; font-size:.68rem; font-weight:600;">${label}</span>`;
}

function opsRenderSummaryCards(data) {
    const grid = document.getElementById("opsComparisonGrid");
    grid.innerHTML = opsComparisonCards
        .map((c) => {
            const m = data.comparison[c.key];
            return `
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="h-100 p-3" style="background:#fffdf2; border:1px solid #e7e3af; border-radius:12px;">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi ${c.icon} text-success" style="font-size:.85rem;"></i>
                    <span class="fw-semibold" style="font-size:.72rem; color:#295738;">${c.label}</span>
                </div>
                <div class="fw-bold" style="color:#03580a; font-size:1.35rem; line-height:1.2;">${c.fmt(m.current)}</div>
                <div class="text-muted mb-2" style="font-size:.72rem;">
                    vs <span class="fw-semibold" style="color:#2f4f2f;">${c.fmt(m.previous)}</span>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    ${opsComparisonStatus(m.percent, c.invert)}
                </div>
            </div>
        </div>`;
        })
        .join("");
}

function opsTableStatus(percent, invert) {
    if (percent === null || percent === undefined || isNaN(percent)) {
        return `<span class="text-muted">—</span>`;
    }
    if (percent === 0) {
        return `<span class="text-muted">No change</span>`;
    }
    const up = percent > 0;
    const good = invert ? !up : up;
    const label = invert ? (up ? "Increased" : "Improved") : up ? "Increase" : "Decrease";
    const color = good ? "#198754" : "#dc3545";
    const arrow = up ? "↑" : "↓";
    return `<span style="color:${color}; font-weight:600;">${arrow} ${label}</span>`;
}

function opsRenderComparisonTable(data, tbodyId) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    tbody.innerHTML = opsComparisonCards
        .map((c) => {
            const m = data.comparison[c.key];
            const diff = m.difference;
            const diffFmt = c.fmt(diff);
            const diffColor =
                diff === null || diff === undefined || isNaN(diff) || diff === 0
                    ? "#94a3b8"
                    : c.invert
                      ? diff < 0
                          ? "#198754"
                          : "#dc3545"
                      : diff > 0
                        ? "#198754"
                        : "#dc3545";
            return `
        <tr>
            <td class="px-2" style="color:#295738;">
                <i class="bi ${c.icon} text-success me-1"></i>${c.label}
            </td>
            <td class="px-2 text-end fw-semibold" style="color:#03580a;">${c.fmt(m.current)}</td>
            <td class="px-2 text-end" style="color:#2f4f2f;">${c.fmt(m.previous)}</td>
            <td class="px-2 text-end fw-semibold" style="color:${diffColor};">${c.fmt(m.current) === "—" && c.fmt(m.previous) === "—" ? "—" : diffFmt}</td>
            <td class="px-2 text-end">${opsTableStatus(m.percent, c.invert)}</td>
        </tr>`;
        })
        .join("");
}

function opsRenderMeaning(data) {
    const c = data.comparison;
    const items = [];
    if (c.revenue.percent !== null) {
        items.push(
            c.revenue.percent > 0
                ? `<li class="mb-1"><i class="bi bi-check-circle-fill text-success me-1"></i>More revenue was generated. <span class="text-muted">(${opsFmtMoney(c.revenue.current)} vs ${opsFmtMoney(c.revenue.previous)})</span></li>`
                : `<li class="mb-1"><i class="bi bi-exclamation-circle-fill text-danger me-1"></i>Revenue decreased compared with the previous year.</li>`,
        );
    }
    if (c.passengers.percent !== null) {
        items.push(
            c.passengers.percent > 0
                ? `<li class="mb-1"><i class="bi bi-check-circle-fill text-success me-1"></i>More passengers were served. <span class="text-muted">(${opsFmtInt(c.passengers.current)} vs ${opsFmtInt(c.passengers.previous)})</span></li>`
                : `<li class="mb-1"><i class="bi bi-exclamation-circle-fill text-danger me-1"></i>Passenger volume decreased compared with the previous year.</li>`,
        );
    }
    if (c.trips.percent !== null) {
        items.push(
            c.trips.percent > 0
                ? `<li class="mb-1"><i class="bi bi-check-circle-fill text-success me-1"></i>More trips were completed. <span class="text-muted">(${opsFmtInt(c.trips.current)} vs ${opsFmtInt(c.trips.previous)})</span></li>`
                : `<li class="mb-1"><i class="bi bi-exclamation-circle-fill text-danger me-1"></i>Fewer trips were completed compared with the previous year.</li>`,
        );
    }
    if (c.discrepancies.percent !== null) {
        items.push(
            c.discrepancies.percent < 0
                ? `<li class="mb-1"><i class="bi bi-check-circle-fill text-success me-1"></i>Discrepancies were reduced. <span class="text-muted">(${opsFmtInt(c.discrepancies.current)} vs ${opsFmtInt(c.discrepancies.previous)})</span></li>`
                : `<li class="mb-1"><i class="bi bi-exclamation-circle-fill text-danger me-1"></i>Discrepancies increased compared with the previous year.</li>`,
        );
    }
    document.getElementById("opsMeaningList").innerHTML = items.length
        ? items.join("")
        : `<li class="text-muted">No comparable data for this period.</li>`;
}

function opsShowSummaryView(data, title, subtitle, opts = {}) {
    document.getElementById("opsPairView").classList.add("d-none");
    document.getElementById("opsSummaryView").classList.remove("d-none");
    document.getElementById("opsSummaryTitle").innerHTML =
        `<i class="bi bi-arrow-left-right text-success me-2"></i>${title}`;
    document.getElementById("opsSummarySubtitle").innerText = subtitle;
    opsRenderSummaryCards(data);
    opsRenderComparisonTable(data, "opsComparisonTableBody");

    const meaningWrap = document.getElementById("opsMeaningWrap");
    const trendWrap = document.getElementById("opsTrendWrap");
    const detailsBtn = document.getElementById("opsDetailsBtn");

    meaningWrap.classList.toggle("d-none", !opts.withMeaning);
    if (opts.withMeaning) {
        opsRenderMeaning(data);
    }
    trendWrap.classList.add("d-none");
    if (!opts.showTrend) {
        detailsBtn.classList.add("d-none");
    } else if (opts.forceTrend) {
        detailsBtn.classList.add("d-none");
        trendWrap.classList.remove("d-none");
        opsRenderTrend(data);
    } else {
        detailsBtn.classList.remove("d-none");
        detailsBtn.innerHTML = `<i class="bi bi-graph-up me-1"></i>View Monthly Details`;
    }
}

function opsRenderTrend(data) {
    const canvas = document.getElementById("opsTrendChart");
    if (opsTrendChart) opsTrendChart.destroy();

    const metric = opsState.metric;
    const cur = data.trend[metric] || [];
    const labels = data.trend.labels || [];
    const prevData = (data.trend_previous && data.trend_previous[metric]) || [];
    const prevLabels = (data.trend_previous && data.trend_previous.labels) || [];
    const fmt = (v) => opsValueFmt(metric, v);

    const aligned = prevLabels.length === labels.length;

    try {
        opsTrendChart = new Chart(canvas, {
            type: "line",
            data: {
                labels,
                datasets: [
                    {
                        label: "Current Period",
                        data: cur,
                        borderColor: "#03580a",
                        backgroundColor: "rgba(3,88,10,0.2)",
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                    },
                    ...(aligned
                        ? [
                              {
                                  label: "Previous Period",
                                  data: prevData,
                                  borderColor: "#c48b00",
                                  backgroundColor: "rgba(196,139,0,0.08)",
                                  borderDash: [5, 5],
                                  fill: false,
                                  tension: 0.4,
                                  pointRadius: 3,
                              },
                          ]
                        : []),
                ],
            },
            options: {
                responsive: true,
                interaction: { mode: "index", intersect: false },
                plugins: {
                    legend: { labels: { color: "#03580a", font: { size: 10 } } },
                    tooltip: {
                        callbacks: {
                            label: (item) => item.dataset.label + ": " + fmt(item.parsed.y),
                        },
                    },
                },
                scales: {
                    x: { ticks: { color: "#295738", font: { size: 10 } } },
                    y: { beginAtZero: true, ticks: { color: "#295738", font: { size: 10 } } },
                },
            },
        });
    } catch (e) {
        console.error("Trend chart error:", e);
    }
}

function opsRenderPairSummary(data) {
    const wrap = document.getElementById("opsPairSummary");
    wrap.innerHTML = opsComparisonCards
        .map((c) => {
            const m = data.comparison[c.key];
            const up = m.percent > 0;
            const good = c.invert ? !up : up;
            const color =
                m.percent === null || m.percent === undefined || isNaN(m.percent)
                    ? "#94a3b8"
                    : good
                      ? "#198754"
                      : "#dc3545";
            const badge =
                m.percent === null || m.percent === undefined || isNaN(m.percent)
                    ? `<span class="text-muted" style="font-size:.68rem;">No comparison data</span>`
                    : m.percent === 0
                      ? `<span class="text-muted" style="font-size:.68rem;">No change</span>`
                      : `<span style="color:${color}; font-weight:600; font-size:.72rem;">${up ? "↑" : "↓"} ${opsFmtPct(m.percent)} ${c.invert ? (up ? "Increased" : "Improved") : up ? "Increase" : "Decrease"}</span>`;
            return `
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="p-2 text-center" style="background:#f3f7dc; border:1px solid #dee4ba; border-radius:12px; height:100%;">
                <div class="small text-muted" style="font-size:.66rem;">${c.label}</div>
                <div class="fw-bold" style="color:#03580a; font-size:.95rem;">${c.fmt(m.current)}</div>
                <div class="text-muted" style="font-size:.66rem;">vs <span class="fw-semibold" style="color:#2f4f2f;">${c.fmt(m.previous)}</span></div>
                <div class="mt-1">${badge}</div>
            </div>
        </div>`;
        })
        .join("");
}

function opsSharedScale(cur, prev) {
    const maxVal = Math.max(...cur, ...prev);
    if (!maxVal || maxVal <= 0) return 1;
    return maxVal * 1.15;
}

function opsRenderPairCharts(data) {
    const metric = opsState.metric;
    const cur = data.trend[metric] || [];
    const prev = (data.trend_previous && data.trend_previous[metric]) || [];
    const curLabels = data.trend.labels || [];
    const prevLabels = (data.trend_previous && data.trend_previous.labels) || [];
    const fmt = (v) => opsValueFmt(metric, v);
    const yMax = opsSharedScale(cur, prev);

    if (opsCurChart) opsCurChart.destroy();
    if (opsPrevChart) opsPrevChart.destroy();

    const barOpts = {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: (item) => fmt(item.parsed.y) } },
        },
        scales: {
            x: { ticks: { color: "#295738", font: { size: 10 } } },
            y: { beginAtZero: true, max: yMax, ticks: { color: "#295738", font: { size: 10 } } },
        },
    };

    opsCurChart = new Chart(document.getElementById("opsCurChart"), {
        type: "bar",
        data: {
            labels: curLabels,
            datasets: [
                {
                    label: "Current",
                    data: cur,
                    backgroundColor: "rgba(3,88,10,0.75)",
                    borderRadius: 4,
                },
            ],
        },
        options: barOpts,
    });

    opsPrevChart = new Chart(document.getElementById("opsPrevChart"), {
        type: "bar",
        data: {
            labels: prevLabels,
            datasets: [
                {
                    label: "Previous",
                    data: prev,
                    backgroundColor: "rgba(196,139,0,0.65)",
                    borderRadius: 4,
                },
            ],
        },
        options: barOpts,
    });
}

function opsShowPairView(data, monthsLabel) {
    document.getElementById("opsSummaryView").classList.add("d-none");
    document.getElementById("opsPairView").classList.remove("d-none");
    document.getElementById("opsPairTitle").innerHTML =
        `<i class="bi bi-bar-chart-line text-success me-2"></i>${monthsLabel} Comparison`;
    document.getElementById("opsPairSubtitle").innerText =
        "Monthly performance versus the previous period — " +
        opsMonthSpan(data.current_range.from, data.current_range.to) +
        " vs " +
        opsMonthSpan(data.previous_range.from, data.previous_range.to);
    document.getElementById("opsCurChartTitle").innerText =
        "Current " +
        data.trend.labels.length +
        " Months (" +
        opsMonthSpan(data.current_range.from, data.current_range.to) +
        ")";
    document.getElementById("opsPrevChartTitle").innerText =
        "Previous " +
        (data.trend_previous.labels || []).length +
        " Months (" +
        opsMonthSpan(data.previous_range.from, data.previous_range.to) +
        ")";
    opsRenderPairSummary(data);
    opsRenderComparisonTable(data, "opsPairTableBody");
    opsRenderPairCharts(data);
}

function opsRenderOperational(data) {
    const grid = document.getElementById("opsOperationalGrid");
    const ops = data.operational;
    const keys = Object.keys(ops);
    if (!keys.length) {
        grid.innerHTML = `<div class="col-12 text-center text-muted py-2"><i class="bi bi-info-circle me-1"></i>Operational metrics unavailable for this period.</div>`;
        return;
    }

    const cardMeta = {
        avg_passengers_per_trip: {
            label: "Average Passengers / Trip",
            unit: "passengers / trip",
            fmt: (v) => opsFmtNum(v, 1),
        },
        avg_revenue_per_trip: {
            label: "Average Revenue / Trip",
            unit: "per trip",
            fmt: opsFmtMoney,
        },
        completed_trip_rate: {
            label: "Trip Completion Rate",
            unit: "completed",
            fmt: (v) => opsFmtNum(v, 1) + "%",
        },
        bus_utilization: {
            label: "Bus Utilization",
            unit: "utilized",
            fmt: (v) => opsFmtNum(v, 1) + "%",
        },
        discrepancy_rate: {
            label: "Discrepancy Rate",
            unit: "of recorded trips",
            fmt: (v) => opsFmtNum(v, 1) + "%",
        },
    };

    function opStatus(key, m) {
        if (m.previous === null || m.previous === undefined || isNaN(m.previous)) {
            return `<span class="text-muted" style="font-size:.68rem;">No comparison data</span>`;
        }
        if (key === "discrepancy_rate") {
            const pp = m.current - m.previous;
            if (pp === 0)
                return `<span class="text-muted" style="font-size:.68rem;">No change</span>`;
            const improved = pp < 0;
            return `<span style="color:${improved ? "#198754" : "#dc3545"}; font-weight:600; font-size:.72rem;">
                ${pp < 0 ? "↓" : "↑"} ${Math.abs(pp).toLocaleString("en-PH", { maximumFractionDigits: 1 })} pp ${improved ? "Improved" : "Increased"}
            </span>`;
        }
        const pct = m.percent;
        if (pct === null || pct === undefined || isNaN(pct)) {
            return `<span class="text-muted" style="font-size:.68rem;">No comparison data</span>`;
        }
        if (pct === 0) return `<span class="text-muted" style="font-size:.68rem;">No change</span>`;
        const up = pct > 0;
        return `<span style="color:${up ? "#198754" : "#dc3545"}; font-weight:600; font-size:.72rem;">
            ${up ? "↑" : "↓"} ${opsFmtPct(pct)} ${up ? "Increase" : "Decrease"}
        </span>`;
    }

    grid.innerHTML = keys
        .map((key) => {
            const meta = cardMeta[key];
            const m = ops[key];
            const tip = "vs previous period";
            const prevRow =
                m.previous !== null && m.previous !== undefined && !isNaN(m.previous)
                    ? `<div class="text-muted" style="font-size:.63rem;">Previous: <span class="fw-semibold" style="color:#2f4f2f;">${meta.fmt(m.previous)}</span></div>`
                    : "";
            return `
        <div class="col">
            <div class="h-100 text-center p-3" style="background:#f3f7dc; border:1px solid #dee4ba; border-radius:12px;" title="${tip}">
                <div class="small text-muted" style="font-size:.68rem;">${m.label}</div>
                <div class="fw-bold mt-1" style="color:#03580a; font-size:1rem;">${meta.fmt(m.current)}</div>
                <div class="text-muted" style="font-size:.65rem;">${meta.unit}</div>
                ${prevRow}
                <div class="mt-1">${opStatus(key, m)}</div>
            </div>
        </div>`;
        })
        .join("");
}

function opsRenderDemand(data) {
    const canvas = document.getElementById("opsDemandChart");
    if (opsDemandChart) opsDemandChart.destroy();

    const d = data.demand;
    document.getElementById("opsDemandTotal").innerText = opsFmtInt(d.total);
    document.getElementById("opsDemandTotalCaption").innerText = (
        data.current_range.label || "selected period"
    ).toLowerCase();
    document.getElementById("opsDemandAvgDay").innerText = opsFmtInt(d.avg_per_day);
    document.getElementById("opsDemandHighest").innerHTML =
        d.highest !== null && d.highest !== undefined
            ? `${d.labels[d.highest] || "—"}<div class="text-muted" style="font-size:.65rem;">${opsFmtInt(d.counts[d.highest])} passengers</div>`
            : "—";
    document.getElementById("opsDemandLowest").innerHTML =
        d.lowest !== null && d.lowest !== undefined
            ? `${d.labels[d.lowest] || "—"}<div class="text-muted" style="font-size:.65rem;">${opsFmtInt(d.counts[d.lowest])} passengers</div>`
            : "—";

    const background = d.counts.map((_, i) => {
        if (i === d.highest) return "#03580a";
        if (i === d.lowest) return "#c48b00";
        return "rgba(41,87,56,0.45)";
    });

    try {
        opsDemandChart = new Chart(canvas, {
            type: "bar",
            data: {
                labels: d.labels,
                datasets: [
                    {
                        label: "Passengers",
                        data: d.counts,
                        backgroundColor: background,
                        borderRadius: 4,
                    },
                ],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: (items) => {
                                const idx = items[0]?.dataIndex;
                                if (idx === d.highest) return d.labels[idx] + " (Peak)";
                                if (idx === d.lowest) return d.labels[idx] + " (Lowest)";
                                return d.labels[idx];
                            },
                        },
                    },
                },
                scales: {
                    x: { ticks: { color: "#295738", font: { size: 10 } } },
                    y: { beginAtZero: true, ticks: { color: "#295738", font: { size: 10 } } },
                },
            },
        });
    } catch (e) {
        console.error("Demand chart error:", e);
    }

    const total = d.morning + d.afternoon + d.evening + (d.predawn ?? 0);
    const peakWrap = document.getElementById("opsPeakWrap");
    const peakEl = document.getElementById("opsPeakSplit");

    const timeTotal = d.time_total ?? d.total;
    const hasTimeData = timeTotal > 0 && total === timeTotal;
    peakWrap.classList.toggle("d-none", !hasTimeData);
    if (!hasTimeData) {
        peakEl.innerHTML = "";
    } else {
        const peakDefs = [
            { key: "predawn", label: "Pre-Dawn", hours: "00:00–06:00", color: "#6b7280" },
            { key: "morning", label: "Morning", hours: "06:00–10:00", color: "#c48b00" },
            { key: "afternoon", label: "Afternoon", hours: "10:00–16:00", color: "#295738" },
            { key: "evening", label: "Evening", hours: "16:00 onwards", color: "#03580a" },
        ];
        peakEl.innerHTML = peakDefs
            .map((p) => {
                const count = d[p.key] ?? 0;
                const share = total > 0 ? Math.round((count / total) * 100) : 0;
                return `
            <div>
                <div class="d-flex justify-content-between small" style="font-size:.72rem;">
                    <span style="color:#295738;">${p.label} <span class="text-muted">(${p.hours})</span></span>
                    <span class="fw-semibold" style="color:#03580a;">${opsFmtInt(count)} (${share}%)</span>
                </div>
                <div style="height:6px; background:#f3f7dc; border-radius:10px; overflow:hidden;">
                    <div style="width:${share}%; height:100%; background:${p.color};"></div>
                </div>
            </div>`;
            })
            .join("");
    }
}

async function loadOperationsAnalytics() {
    const period = document.getElementById("opsPeriod").value;
    const against = document.getElementById("opsAgainst").value;

    const params = new URLSearchParams();
    params.set("period", period);
    params.set("against", against);
    if (period === "custom") {
        params.set("from", document.getElementById("opsCurFrom").value);
        params.set("to", document.getElementById("opsCurTo").value);
    }
    if (against === "custom") {
        params.set("from2", document.getElementById("opsPrevFrom").value);
        params.set("to2", document.getElementById("opsPrevTo").value);
    }

    if (!params.get("period")) return;

    if (period === "custom" && against === "custom") {
        const curFrom = document.getElementById("opsCurFrom").value;
        const curTo = document.getElementById("opsCurTo").value;
        const prevFrom = document.getElementById("opsPrevFrom").value;
        const prevTo = document.getElementById("opsPrevTo").value;
        const hint = document.getElementById("opsCustomHint");
        const valid =
            curFrom && curTo && prevFrom && prevTo && curFrom <= curTo && prevFrom <= prevTo;
        hint.classList.toggle("d-none", valid);
        const btn = document.getElementById("opsCompareBtn");
        btn.disabled = !valid;
        if (!valid) {
            return;
        }
    }

    const grid = document.getElementById("opsComparisonGrid");
    grid.innerHTML = opsLoadingHTML();

    let data;
    try {
        const res = await fetch(
            "/finance/api/dashboard/operations_analytics.php?" + params.toString(),
        );
        data = await res.json();
    } catch (e) {
        console.error("Operations analytics fetch error:", e);
        grid.innerHTML = `<div class="col-12 text-center text-danger py-3">Failed to load analytics.</div>`;
        return;
    }

    if (!data.success) {
        console.error("Operations analytics API error:", data);
        grid.innerHTML = `<div class="col-12 text-center text-danger py-3">${data.message || "Analytics unavailable."}</div>`;
        return;
    }

    opsRenderRangeCaption(data);
    window.__opsLastData = data;
    window.__setAiPageData && window.__setAiPageData("operations_analytics", data);

    const sections = document.getElementById("opsSections");
    const emptyEl = document.getElementById("opsEmptyState");

    if (data.empty) {
        emptyEl.classList.remove("d-none");
        sections.classList.add("d-none");
        return;
    }

    emptyEl.classList.add("d-none");
    sections.classList.remove("d-none");

    if (period === "ytd") {
        const year = new Date(data.current_range.from + "T00:00:00").getFullYear();
        const prevYear = new Date(data.previous_range.from + "T00:00:00").getFullYear();
        opsShowSummaryView(
            data,
            `${year} vs ${prevYear}`,
            "Total performance for the selected years",
            { withMeaning: true },
        );
    } else if (period === "last6" || period === "last12") {
        opsShowPairView(data, period === "last6" ? "6-Month" : "12-Month");
    } else if (period === "month") {
        opsShowSummaryView(
            data,
            opsMonthLabel(data.current_range.from) +
                " vs " +
                opsMonthLabel(data.previous_range.from),
            "Monthly totals for the selected months",
        );
    } else {
        const from = new Date(data.current_range.from + "T00:00:00");
        const to = new Date(data.current_range.to + "T00:00:00");
        const days = Math.round((to - from) / 86400000) + 1;
        opsShowSummaryView(
            data,
            "Custom Period Comparison",
            "Total performance for the selected periods",
            { forceTrend: days >= 60 },
        );
    }

    opsRenderOperational(data);
    opsRenderDemand(data);
    loadFuelAnalytics(params.toString());
}

function loadFuelAnalytics(overrideQuery) {
    if (typeof Chart === "undefined") {
        setTimeout(function () {
            loadFuelAnalytics(overrideQuery);
        }, 100);
        return;
    }

    let q = overrideQuery !== undefined ? overrideQuery : window.__financeFilterQuery || "";
    if (!q) {
        const period = document.getElementById("opsPeriod")
            ? document.getElementById("opsPeriod").value
            : "";
        if (period) {
            const params = new URLSearchParams();
            params.set("period", period);
            if (period === "custom") {
                const f = document.getElementById("opsCurFrom")
                    ? document.getElementById("opsCurFrom").value
                    : "";
                const t = document.getElementById("opsCurTo")
                    ? document.getElementById("opsCurTo").value
                    : "";
                if (f) params.set("from", f);
                if (t) params.set("to", t);
            }
            q = params.toString();
        }
    }

    let url = "/finance/api/dashboard/fuel_analytics.php";
    if (q) url += (url.indexOf("?") === -1 ? "?" : "&") + q;

    if (opsFuelAbort) {
        opsFuelAbort.abort();
    }
    opsFuelAbort = new AbortController();

    fetch(url, { signal: opsFuelAbort.signal })
        .then(function (res) {
            return res.text().then(function (text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Non-JSON response from fuel_analytics.php:", text);
                    throw new Error(
                        "HTTP " + res.status + " - Invalid JSON: " + text.substring(0, 150),
                    );
                }
            });
        })
        .then(function (response) {
            opsFuelAbort = null;
            if (!response || !response.success) {
                throw new Error(
                    response && response.message
                        ? response.message
                        : "Failed to load fuel analytics",
                );
            }

            const summary = response.summary || {};
            const fuelByBus = response.fuel_by_bus || [];
            const costComparison = response.cost_comparison_by_bus || [];
            const fuelTrend = response.fuel_trend || {
                labels: [],
                estimated_cost: [],
                actual_cost: [],
                total_cost: [],
                trip_count: [],
            };

            const totalEstCost = Number(
                summary.total_estimated_fuel_cost || summary.total_fuel_cost || 0,
            );
            const totalActCost = Number(summary.total_actual_fuel_cost || 0);
            const avgConsumed = summary.avg_fuel_consumed_per_trip ?? summary.avg_liters_per_trip;

            const badgeAvgTrip = document.getElementById("opsFuelAvgTripBadge");
            if (badgeAvgTrip) {
                badgeAvgTrip.innerText =
                    "Avg Consumed / Trip: " +
                    (avgConsumed !== null && avgConsumed > 0
                        ? Number(avgConsumed).toFixed(2) + " L"
                        : "N/A");
            }
            const badgeTotal = document.getElementById("opsFuelTotalBadge");
            if (badgeTotal) {
                badgeTotal.innerText =
                    "Total Est. Cost: " +
                    (totalEstCost > 0
                        ? "₱" +
                          totalEstCost.toLocaleString("en-PH", {
                              minimumFractionDigits: 2,
                              maximumFractionDigits: 2,
                          })
                        : "₱0.00");
            }
            const badgeActual = document.getElementById("opsFuelAvgKplBadge");
            if (badgeActual) {
                badgeActual.innerText =
                    "Actual Refuels: " +
                    (totalActCost > 0
                        ? "₱" +
                          totalActCost.toLocaleString("en-PH", {
                              minimumFractionDigits: 2,
                              maximumFractionDigits: 2,
                          })
                        : "₱0.00");
            }

            if (opsFuelTrendChart) {
                opsFuelTrendChart.destroy();
                opsFuelTrendChart = null;
            }
            if (opsFuelBusChart) {
                opsFuelBusChart.destroy();
                opsFuelBusChart = null;
            }
            if (opsFuelEffChart) {
                opsFuelEffChart.destroy();
                opsFuelEffChart = null;
            }

            const hasAnyData = totalEstCost > 0 || totalActCost > 0 || fuelByBus.length > 0;

            if (!hasAnyData) {
                if (typeof window.__showChartNoData === "function") {
                    window.__showChartNoData(
                        "opsFuelTrendChart",
                        "No fuel log records found for the selected period.",
                    );
                    window.__showChartNoData(
                        "opsFuelBusChart",
                        "No fuel logs found for the selected period.",
                    );
                    window.__showChartNoData(
                        "opsFuelEffChart",
                        "No fuel cost comparison data available.",
                    );
                }
                return;
            }

            if (typeof window.__hideChartNoData === "function") {
                window.__hideChartNoData("opsFuelTrendChart");
                window.__hideChartNoData("opsFuelBusChart");
                window.__hideChartNoData("opsFuelEffChart");
            }

            const trendCanvas = document.getElementById("opsFuelTrendChart");
            if (trendCanvas && fuelTrend.labels && fuelTrend.labels.length > 0) {
                const trendCostData = fuelTrend.estimated_cost || fuelTrend.total_cost || [];
                opsFuelTrendChart = new Chart(trendCanvas, {
                    type: "line",
                    data: {
                        labels: fuelTrend.labels,
                        datasets: [
                            {
                                label: "Estimated Fuel Cost (₱)",
                                data: trendCostData,
                                borderColor: "#03580a",
                                backgroundColor: "rgba(3, 88, 10, 0.12)",
                                fill: true,
                                tension: 0.35,
                                borderWidth: 2.5,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                pointBackgroundColor: "#03580a",
                                pointBorderColor: "#ffffff",
                                pointBorderWidth: 2,
                                spanGaps: true,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: "index", intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        const idx = ctx.dataIndex;
                                        const val = ctx.parsed.y;
                                        if (val === null || val === undefined || isNaN(val)) {
                                            return "No valid fuel records";
                                        }
                                        const lit = fuelTrend.estimated_liters
                                            ? fuelTrend.estimated_liters[idx]
                                            : fuelTrend.consumed_liters
                                              ? fuelTrend.consumed_liters[idx]
                                              : 0;
                                        const act = fuelTrend.actual_cost
                                            ? fuelTrend.actual_cost[idx]
                                            : 0;
                                        const t = fuelTrend.trip_count
                                            ? fuelTrend.trip_count[idx]
                                            : 0;
                                        const lines = [
                                            "Estimated Cost: ₱" +
                                                Number(val).toLocaleString("en-PH", {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2,
                                                }),
                                            "Fuel Consumed: " +
                                                Number(lit || 0).toLocaleString("en-PH", {
                                                    minimumFractionDigits: 1,
                                                }) +
                                                " L",
                                            "Trips: " + (t || 0),
                                        ];
                                        if (act > 0) {
                                            lines.push(
                                                "Actual Refuels: ₱" +
                                                    Number(act).toLocaleString("en-PH", {
                                                        minimumFractionDigits: 2,
                                                        maximumFractionDigits: 2,
                                                    }),
                                            );
                                        }
                                        return lines;
                                    },
                                },
                            },
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { color: "#295738", font: { size: 10 } },
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: "#eef0e6" },
                                ticks: {
                                    color: "#295738",
                                    font: { size: 10 },
                                    callback: function (v) {
                                        return "₱" + Number(v).toLocaleString("en-PH");
                                    },
                                },
                            },
                        },
                    },
                });
            } else if (trendCanvas && typeof window.__showChartNoData === "function") {
                window.__showChartNoData(
                    "opsFuelTrendChart",
                    "No trip fuel trend data available for this period.",
                );
            }

            const busCanvas = document.getElementById("opsFuelBusChart");
            if (busCanvas && fuelByBus.length > 0) {
                const busLabels = fuelByBus.map(function (b) {
                    return b.bus_label;
                });
                const busCosts = fuelByBus.map(function (b) {
                    return b.estimated_cost;
                });
                const isBusH = busLabels.length > 4;

                opsFuelBusChart = new Chart(busCanvas, {
                    type: "bar",
                    data: {
                        labels: busLabels,
                        datasets: [
                            {
                                label: "Estimated Fuel Cost (₱)",
                                data: busCosts,
                                backgroundColor: "#295738",
                                hoverBackgroundColor: "#03580a",
                                borderRadius: 6,
                                borderSkipped: false,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: isBusH ? "y" : "x",
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        const idx = ctx.dataIndex;
                                        const item = fuelByBus[idx] || {};
                                        const val = ctx.parsed[isBusH ? "x" : "y"];
                                        const lines = [
                                            "Estimated Cost: ₱" +
                                                Number(val).toLocaleString("en-PH", {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2,
                                                }),
                                            "Fuel Consumed: " +
                                                Number(
                                                    item.consumed_liters ||
                                                        item.estimated_liters ||
                                                        item.total_liters ||
                                                        0,
                                                ).toLocaleString("en-PH", {
                                                    minimumFractionDigits: 1,
                                                }) +
                                                " L",
                                        ];
                                        if (item.total_distance > 0) {
                                            lines.push(
                                                "Distance: " +
                                                    Number(item.total_distance).toLocaleString(
                                                        "en-PH",
                                                        { minimumFractionDigits: 1 },
                                                    ) +
                                                    " km",
                                            );
                                        }
                                        if (item.trips_count > 0) {
                                            lines.push(
                                                "Trips: " +
                                                    item.trips_count +
                                                    (item.avg_cost_per_trip > 0
                                                        ? " (Avg ₱" +
                                                          Number(
                                                              item.avg_cost_per_trip,
                                                          ).toLocaleString("en-PH", {
                                                              minimumFractionDigits: 2,
                                                          }) +
                                                          "/trip)"
                                                        : ""),
                                            );
                                        }
                                        if (item.actual_cost > 0) {
                                            lines.push(
                                                "Actual Refuel: ₱" +
                                                    Number(item.actual_cost).toLocaleString(
                                                        "en-PH",
                                                        { minimumFractionDigits: 2 },
                                                    ),
                                            );
                                        }
                                        return lines;
                                    },
                                },
                            },
                        },
                        scales: isBusH
                            ? {
                                  x: {
                                      beginAtZero: true,
                                      grid: { color: "#eef0e6" },
                                      ticks: {
                                          color: "#295738",
                                          font: { size: 10 },
                                          callback: function (v) {
                                              return "₱" + Number(v).toLocaleString("en-PH");
                                          },
                                      },
                                  },
                                  y: {
                                      grid: { display: false },
                                      ticks: {
                                          color: "#295738",
                                          font: { size: 11, weight: "500" },
                                      },
                                  },
                              }
                            : {
                                  x: {
                                      grid: { display: false },
                                      ticks: {
                                          color: "#295738",
                                          font: { size: 11, weight: "500" },
                                      },
                                  },
                                  y: {
                                      beginAtZero: true,
                                      grid: { color: "#eef0e6" },
                                      ticks: {
                                          color: "#295738",
                                          font: { size: 10 },
                                          callback: function (v) {
                                              return "₱" + Number(v).toLocaleString("en-PH");
                                          },
                                      },
                                  },
                              },
                    },
                });
            } else if (busCanvas && typeof window.__showChartNoData === "function") {
                window.__showChartNoData(
                    "opsFuelBusChart",
                    "No bus fuel cost data for this period.",
                );
            }

            const effCanvas = document.getElementById("opsFuelEffChart");
            const compList = costComparison.length > 0 ? costComparison : fuelByBus;
            if (effCanvas && compList.length > 0) {
                const compLabels = compList.map(function (b) {
                    return b.bus_label;
                });
                const compEst = compList.map(function (b) {
                    return b.estimated_cost;
                });
                const compAct = compList.map(function (b) {
                    return b.actual_cost;
                });
                const isCompH = compLabels.length > 4;

                opsFuelEffChart = new Chart(effCanvas, {
                    type: "bar",
                    data: {
                        labels: compLabels,
                        datasets: [
                            {
                                label: "Estimated Cost (₱)",
                                data: compEst,
                                backgroundColor: "#295738",
                                hoverBackgroundColor: "#03580a",
                                borderRadius: 5,
                                borderSkipped: false,
                            },
                            {
                                label: "Actual Refuels (₱)",
                                data: compAct,
                                backgroundColor: "#c48b00",
                                hoverBackgroundColor: "#a16207",
                                borderRadius: 5,
                                borderSkipped: false,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: isCompH ? "y" : "x",
                        plugins: {
                            legend: {
                                display: true,
                                position: "top",
                                labels: {
                                    color: "#295738",
                                    boxWidth: 12,
                                    font: { size: 10, weight: "600" },
                                },
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        const idx = ctx.dataIndex;
                                        const item = compList[idx] || {};
                                        const isEstDataset = ctx.datasetIndex === 0;
                                        const val = ctx.parsed[isCompH ? "x" : "y"];
                                        const lines = [
                                            (isEstDataset
                                                ? "Estimated Cost: ₱"
                                                : "Actual Refuel: ₱") +
                                                Number(val).toLocaleString("en-PH", {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2,
                                                }),
                                        ];
                                        if (!isEstDataset) {
                                            if (item.has_actual) {
                                                const varCost =
                                                    item.variance_cost !== undefined
                                                        ? item.variance_cost
                                                        : item.actual_cost - item.estimated_cost;
                                                const sign = varCost > 0 ? "+" : "";
                                                lines.push(
                                                    "Variance: " +
                                                        sign +
                                                        "₱" +
                                                        Number(varCost).toLocaleString("en-PH", {
                                                            minimumFractionDigits: 2,
                                                        }) +
                                                        (item.variance_pct !== null &&
                                                        item.variance_pct !== undefined
                                                            ? " (" + sign + item.variance_pct + "%)"
                                                            : ""),
                                                );
                                            } else {
                                                lines.push("Status: No actual refuels logged");
                                            }
                                        }
                                        return lines;
                                    },
                                },
                            },
                        },
                        scales: isCompH
                            ? {
                                  x: {
                                      beginAtZero: true,
                                      grid: { color: "#eef0e6" },
                                      ticks: {
                                          color: "#295738",
                                          font: { size: 10 },
                                          callback: function (v) {
                                              return "₱" + Number(v).toLocaleString("en-PH");
                                          },
                                      },
                                  },
                                  y: {
                                      grid: { display: false },
                                      ticks: {
                                          color: "#295738",
                                          font: { size: 11, weight: "500" },
                                      },
                                  },
                              }
                            : {
                                  x: {
                                      grid: { display: false },
                                      ticks: {
                                          color: "#295738",
                                          font: { size: 11, weight: "500" },
                                      },
                                  },
                                  y: {
                                      beginAtZero: true,
                                      grid: { color: "#eef0e6" },
                                      ticks: {
                                          color: "#295738",
                                          font: { size: 10 },
                                          callback: function (v) {
                                              return "₱" + Number(v).toLocaleString("en-PH");
                                          },
                                      },
                                  },
                              },
                    },
                });
            } else if (effCanvas && typeof window.__showChartNoData === "function") {
                window.__showChartNoData(
                    "opsFuelEffChart",
                    "No fuel cost comparison data available for this period.",
                );
            }
        })
        .catch(function (err) {
            opsFuelAbort = null;
            if (err.name === "AbortError") return;
            console.error("Fuel Analytics load error:", err);
            if (typeof window.__showChartNoData === "function") {
                window.__showChartNoData("opsFuelTrendChart", "Unable to load fuel trend data.");
                window.__showChartNoData("opsFuelBusChart", "Unable to load fuel cost data.");
                window.__showChartNoData("opsFuelEffChart", "Unable to load fuel efficiency data.");
            }
        });
}

window.loadFuelAnalytics = loadFuelAnalytics;
window.loadFuelCostByBus = loadFuelAnalytics;

function populateAgainst() {
    const period = document.getElementById("opsPeriod").value;
    const sel = document.getElementById("opsAgainst");
    const prevVal = sel.value;
    sel.innerHTML =
        OPS_AGAINST_OPTIONS[period]
            ?.map((o) => `<option value="${o.value}">${o.label}</option>`)
            .join("") || "";
    if ([...sel.options].some((o) => o.value === prevVal)) {
        sel.value = prevVal;
    }
}

function toggleCustomWraps() {
    const period = document.getElementById("opsPeriod").value;
    const against = document.getElementById("opsAgainst").value;
    const curWrap = document.getElementById("opsCurCustomWrap");
    const prevWrap = document.getElementById("opsPrevCustomWrap");
    curWrap.classList.toggle("d-none", period !== "custom");
    prevWrap.classList.toggle("d-none", against !== "custom");

    const now = new Date();
    const cy = now.getFullYear();

    function seed(id, value) {
        const el = document.getElementById(id);
        if (!el.value) el.value = value;
    }

    if (period === "custom" && curWrap.classList.contains("d-none") === false) {
        seed("opsCurFrom", cy + "-01-01");
        seed("opsCurTo", cy + "-12-31");
    }
    if (against === "custom" && prevWrap.classList.contains("d-none") === false) {
        seed("opsPrevFrom", cy - 1 + "-01-01");
        seed("opsPrevTo", cy - 1 + "-12-31");
    }
}

document.addEventListener("DOMContentLoaded", function () {
    populateAgainst();
    toggleCustomWraps();

    const customDates = ["opsCurFrom", "opsCurTo", "opsPrevFrom", "opsPrevTo"];

    function validateCustomDates() {
        const period = document.getElementById("opsPeriod").value;
        if (period !== "custom") {
            document.getElementById("opsCustomHint").classList.add("d-none");
            document.getElementById("opsCompareBtn").disabled = false;
            return;
        }
        const curFrom = document.getElementById("opsCurFrom").value;
        const curTo = document.getElementById("opsCurTo").value;
        const prevFrom = document.getElementById("opsPrevFrom").value;
        const prevTo = document.getElementById("opsPrevTo").value;
        const valid =
            curFrom && curTo && prevFrom && prevTo && curFrom <= curTo && prevFrom <= prevTo;
        document.getElementById("opsCustomHint").classList.toggle("d-none", valid);
        document.getElementById("opsCompareBtn").disabled = !valid;
        if (valid) {
            loadOperationsAnalytics();
        }
    }

    customDates.forEach((id) =>
        document.getElementById(id).addEventListener("change", validateCustomDates),
    );
    validateCustomDates();

    document.getElementById("opsPeriod").addEventListener("change", function () {
        populateAgainst();
        toggleCustomWraps();
        validateCustomDates();
        if (
            document.getElementById("opsAgainst").value !== "custom" ||
            (document.getElementById("opsPrevFrom").value &&
                document.getElementById("opsPrevTo").value)
        ) {
            loadOperationsAnalytics();
        }
    });
    document.getElementById("opsAgainst").addEventListener("change", function () {
        toggleCustomWraps();
        validateCustomDates();
        if (
            this.value !== "custom" ||
            (document.getElementById("opsPrevFrom").value &&
                document.getElementById("opsPrevTo").value)
        ) {
            loadOperationsAnalytics();
        }
    });
    document.getElementById("opsCompareBtn").addEventListener("click", loadOperationsAnalytics);

    document.getElementById("opsDetailsBtn").addEventListener("click", function () {
        const wrap = document.getElementById("opsTrendWrap");
        const opening = wrap.classList.contains("d-none");
        wrap.classList.toggle("d-none");
        this.innerHTML = opening
            ? `<i class="bi bi-x-circle me-1"></i>Hide Monthly Details`
            : `<i class="bi bi-graph-up me-1"></i>View Monthly Details`;
        if (opening) {
            const data = window.__opsLastData;
            if (data) opsRenderTrend(data);
        }
    });

    document.getElementById("opsMetricSelect").addEventListener("change", function () {
        opsState.metric = this.value;
        const data = window.__opsLastData;
        if (data) opsRenderPairCharts(data);
    });

    document.getElementById("opsTrendMetric").addEventListener("change", function () {
        opsState.metric = this.value;
        const data = window.__opsLastData;
        if (data) opsRenderTrend(data);
    });

    loadOperationsAnalytics();
    loadFuelAnalytics();
});
</script>