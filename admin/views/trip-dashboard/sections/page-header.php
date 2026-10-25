<div class="ops-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <h1 class="ops-page-title mb-0">
                Real Time Monitoring
            </h1>
            <div id="live-data-badge" class="live-data-indicator">
                <span class="pulse-dot"></span>
                <span id="live-data-status-text">LIVE DATA</span>
            </div>
        </div>
        <p class="ops-page-subtitle mb-0">
            Monitor live bus locations, routes, and trip activity in real time.
        </p>
    </div>

    <div class="d-flex align-items-center gap-2">
        <span id="ops-clock" class="ops-live-clock d-none d-sm-inline-block"></span>
        <button type="button" onclick="viewAllBuses()" class="btn ops-btn-secondary btn-sm">
            <i class="bi bi-arrows-fullscreen me-1"></i> Back to All Buses
        </button>
    </div>
</div>