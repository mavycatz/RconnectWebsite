<div class="row g-3 mb-3 align-items-stretch" id="main-monitoring-area">

    <div class="col-12 col-lg-8 d-flex flex-column">
        <div class="ops-map-wrapper flex-grow-1 position-relative">

            <div class="ops-map-controls">
                <div class="btn-group btn-group-sm ops-mode-group" role="group">
                    <button type="button" class="btn ops-mode-btn active" id="btn-mode-live" onclick="setMapMode('live')" title="Follow active bus">
                        <i class="bi bi-geo-alt-fill me-1"></i> Live Map
                    </button>
                    <button type="button" class="btn ops-mode-btn" id="btn-mode-route" onclick="setMapMode('route')" title="Fit complete route corridor">
                        <i class="bi bi-map-fill me-1"></i> Route View
                    </button>
                </div>
            </div>

            <div id="floating-bus-card" class="ops-floating-card shadow-sm" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-1 gap-2">
                    <span class="ops-live-pill">
                        <span class="pulse-dot"></span> LIVE
                    </span>
                    <select id="floating-bus-selector" class="ops-floating-select" onchange="selectBus(this.value)">
                    </select>
                </div>
                <div class="ops-floating-route" id="floating-bus-route">Cabanatuan → Rizal</div>
                <div class="ops-floating-metrics">
                    <span id="floating-bus-speed">0.0 km/h</span>
                    <span class="ops-dot-sep">·</span>
                    <span id="floating-bus-gps" class="ops-gps-badge online">● GPS Online</span>
                </div>
            </div>

            <div id="map"></div>

            <div class="ops-map-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="ops-legend-bar d-flex align-items-center flex-wrap gap-2">
                    <div class="ops-legend-item">
                        <i class="bi bi-bus-front-fill legend-bus-icon"></i>
                        <span>Current Bus</span>
                    </div>
                    <div class="ops-legend-item">
                        <span class="legend-dot completed"></span>
                        <span>Completed Stop</span>
                    </div>
                    <div class="ops-legend-item">
                        <span class="legend-dot current"></span>
                        <span>Current Stop</span>
                    </div>
                    <div class="ops-legend-item">
                        <span class="legend-dot upcoming"></span>
                        <span>Upcoming Stop</span>
                    </div>
                    <div class="ops-legend-item">
                        <span class="legend-line traveled"></span>
                        <span>Traveled Route</span>
                    </div>
                    <div class="ops-legend-item">
                        <span class="legend-line remaining"></span>
                        <span>Remaining Route</span>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <div id="map-provider-status" class="map-provider-badge" style="display:none;">
                        <i class="bi bi-map me-1" id="map-provider-icon"></i>
                        <span id="map-provider-text">OpenStreetMap</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="col-12 col-lg-4 d-flex flex-column">
        <div class="ops-details-card flex-grow-1" id="live-bus-details-card">

            <div id="bus-details-active-state">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="ops-bus-title" id="detail-bus-id">BUS-—</span>
                            <span class="ops-plate-compact" id="detail-plate-number">—</span>
                        </div>
                        <div class="ops-meta-sub mt-1" id="detail-route-name">Loading Route...</div>
                    </div>
                    <span class="ops-status-badge in-progress" id="detail-trip-status">● ON TRIP</span>
                </div>

                <div class="ops-hr my-2"></div>

                <div class="ops-detail-group mb-2">
                    <span class="ops-label-compact">GPS</span>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="ops-gps-badge online" id="detail-gps-status">● Online</span>
                        <strong class="ops-val-compact" id="detail-speed">0.0 km/h</strong>
                    </div>
                    <div class="ops-meta-sub mt-1" id="detail-last-update-row">
                        Last update: <span id="detail-last-update">—</span>
                    </div>
                </div>

                <div class="ops-hr my-2"></div>

                <div class="ops-detail-group mb-2">
                    <span class="ops-label-compact">DRIVER</span>
                    <div class="ops-val-compact mt-1" id="detail-driver-name">Loading Driver...</div>
                    <div class="ops-meta-sub" id="detail-driver-sub">Assigned</div>
                </div>

                <div class="ops-hr my-2"></div>

                <div class="ops-detail-group mb-2">
                    <span class="ops-label-compact">INSPECTOR</span>
                    <div class="mt-1" id="detail-inspector-block">
                        <span class="ops-val-compact" id="detail-inspector-name">Not Assigned</span>
                    </div>
                    <div class="ops-meta-sub" id="detail-inspector-sub"></div>
                </div>

                <div class="ops-hr my-2"></div>

                <div class="ops-detail-group mb-2">
                    <span class="ops-label-compact">PASSENGERS</span>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <strong class="ops-val-compact" id="detail-occupancy-ratio">0 / 0</strong>
                        <span class="ops-meta-sub" id="detail-passengers-sub">0 boarded · 0 dropped</span>
                    </div>
                    <div class="progress ops-progress-bar-sm mt-1">
                        <div class="progress-bar" id="detail-occupancy-bar" style="width: 0%;"></div>
                    </div>
                </div>

                <div class="ops-hr my-2"></div>

                <div class="ops-status-compact-row mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="ops-label-compact">INSPECTION</span>
                        <span class="ops-val-compact" id="detail-inspection-status">Not Started</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="ops-label-compact">PAYMENTS</span>
                        <span class="ops-val-compact" id="detail-payment-status">Paid</span>
                    </div>
                </div>

                <button type="button" class="ops-btn-primary w-100" id="btn-view-bus-full-details" onclick="openCurrentBusModal()">
                    <span class="ops-btn-icon-bubble"><i class="bi bi-info-circle-fill"></i></span>
                    <span class="ops-btn-text">View Full Details</span>
                    <i class="bi bi-arrow-right-circle-fill ops-btn-arrow ms-auto"></i>
                </button>
            </div>

            <div id="bus-details-empty-state" style="display:none;" class="text-center py-4">
                <div class="mb-2" style="font-size:28px;color:#295738;"><i class="bi bi-bus-front"></i></div>
                <div class="ops-val-compact mb-1">No Active Buses Right Now</div>
                <p class="ops-meta-sub mb-2">
                    When buses depart on trips, their live locations and occupancy will appear here.
                </p>
                <button type="button" class="btn ops-btn-secondary btn-sm" onclick="loadLiveDrivers()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh Status
                </button>
            </div>

        </div>
    </div>

</div>