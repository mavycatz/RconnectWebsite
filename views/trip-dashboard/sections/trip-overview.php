<div class="row g-3 mb-3" id="trip-overview-section">
    <div class="col-12">
        <div class="ops-overview-card">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2 pb-2 border-bottom">
                <div>
                    <h3 class="ops-section-title mb-0" id="overview-title">
                        Trip Overview — <span id="overview-bus-id">BUS-—</span>
                    </h3>
                    <span class="ops-meta-sub" id="overview-route-name">Cabanatuan → Rizal</span>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn ops-btn-secondary btn-sm" onclick="centerOnFocusedBus()">
                        <i class="bi bi-crosshair me-1"></i> View on Map
                    </button>
                </div>
            </div>

            <div class="ops-trip-progress-block mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="ops-label-compact">Trip Progress</span>
                    <strong class="ops-progress-percent" id="overview-progress-percent">0%</strong>
                </div>
                <div class="progress ops-progress-bar-sm mb-1">
                    <div class="progress-bar" id="overview-progress-bar" style="width: 0%;"></div>
                </div>
                <div class="d-flex justify-content-between ops-meta-sub">
                    <span id="overview-traveled-dist">0 km traveled</span>
                    <span id="overview-remaining-dist">— km remaining</span>
                </div>
            </div>

            <div class="ops-overview-stats-grid mb-3">
                <div class="ops-overview-stat-col">
                    <span class="ops-label-compact">BOARDED</span>
                    <div class="ops-val-compact" id="overview-boarded-stat">0</div>
                </div>
                <div class="ops-overview-stat-col">
                    <span class="ops-label-compact">DROPPED</span>
                    <div class="ops-val-compact" id="overview-dropped-stat">0</div>
                </div>
                <div class="ops-overview-stat-col">
                    <span class="ops-label-compact">OCCUPANCY</span>
                    <div class="ops-val-compact" id="overview-occupancy-stat">0 / 0</div>
                </div>
                <div class="ops-overview-stat-col">
                    <span class="ops-label-compact">NEXT STOP</span>
                    <div class="ops-val-compact text-truncate" id="overview-next-stop-name">Cabanatuan Terminal</div>
                    <div class="ops-meta-sub" id="overview-next-stop-meta">In transit</div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center pt-2 border-top ops-meta-sub">
                <div>Inspection: <strong id="overview-inspection-summary">Not Started</strong></div>
                <div>Payments: <strong id="overview-payment-summary">Paid</strong></div>
            </div>

        </div>
    </div>
</div>
