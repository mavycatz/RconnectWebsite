<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <div class="d-flex align-items-center gap-2">
            <h1 style="font-weight:800; font-size:1.3rem; color:#295738; margin:0;">
                Real Time System Logging
            </h1>
            <span style="background:#eef6ef;border:1px solid #bfe0c7;color:#198754;font-size:0.72rem;font-weight:600;padding:2px 8px;border-radius:20px;display:inline-flex;align-items:center;gap:5px;">
                <span class="sys-live-dot"></span> Live Monitoring
            </span>
        </div>
        <p style="font-weight:500; font-size:0.78rem; color:#788b78; margin:2px 0 0;">
            Real-time audit telemetry, active user sessions, and operational event tracking across all fleet modules.
        </p>
    </div>

    <div class="d-flex align-items-center gap-2">
        <span id="lastUpdatedTime" style="font-size:0.72rem;color:#7e8f7e;font-weight:500;">
            <i class="bi bi-clock-history"></i> Connecting...
        </span>
        <button type="button" class="sys-btn-clear" onclick="manualRefresh()" title="Refresh System Logs">
            <i class="bi bi-arrow-repeat" id="refreshIcon"></i> Refresh
        </button>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="sys-kpi-card sys-kpi-error">
            <div class="sys-kpi-label">
                <span><i class="bi bi-exclamation-octagon-fill me-1"></i> Errors</span>
                <i class="bi bi-shield-x text-danger opacity-50"></i>
            </div>
            <h2 id="countErrors" class="sys-kpi-value">0</h2>
            <div class="sys-kpi-trend">
                <span id="trendErrors"><i class="bi bi-hourglass-split"></i> Loading...</span>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="sys-kpi-card sys-kpi-success">
            <div class="sys-kpi-label">
                <span><i class="bi bi-check-circle-fill me-1"></i> Success</span>
                <i class="bi bi-check-all text-success opacity-50"></i>
            </div>
            <h2 id="countSuccess" class="sys-kpi-value">0</h2>
            <div class="sys-kpi-trend">
                <span id="trendSuccess"><i class="bi bi-hourglass-split"></i> Loading...</span>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="sys-kpi-card sys-kpi-warning">
            <div class="sys-kpi-label">
                <span><i class="bi bi-exclamation-triangle-fill me-1"></i> Warnings</span>
                <i class="bi bi-flag-fill text-warning opacity-50"></i>
            </div>
            <h2 id="countWarnings" class="sys-kpi-value">0</h2>
            <div class="sys-kpi-trend">
                <span id="trendWarnings"><i class="bi bi-hourglass-split"></i> Loading...</span>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="sys-kpi-card sys-kpi-monitor">
            <div class="sys-kpi-label">
                <span><i class="bi bi-info-circle-fill me-1"></i> Monitor</span>
                <i class="bi bi-activity text-primary opacity-50"></i>
            </div>
            <h2 id="countMonitor" class="sys-kpi-value">0</h2>
            <div class="sys-kpi-trend">
                <span id="trendMonitor"><i class="bi bi-broadcast"></i> Telemetry active</span>
            </div>
        </div>
    </div>
</div>
