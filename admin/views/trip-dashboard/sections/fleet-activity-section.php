<div class="row g-3 mb-3 align-items-stretch" id="fleet-activity-section">

    <div class="col-12 col-xl-8 d-flex flex-column">
        <div class="ops-fleet-card h-100 d-flex flex-column flex-grow-1">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2 pb-2 border-bottom flex-shrink-0">
                <h3 class="ops-section-title mb-0 d-flex align-items-center">
                    <i class="bi bi-bus-front me-2" style="color:#295738;"></i>
                    <span>Fleet Operations</span>
                </h3>

                <div class="ops-tabs-group" role="tablist">
                    <button type="button" class="ops-tab-btn active" id="tab-active-trips" onclick="setFleetTab('active')">
                        <i class="bi bi-play-circle-fill"></i>
                        <span>Active Trips</span>
                        <span class="badge ops-tab-badge" id="badge-active-count">0</span>
                    </button>
                    <button type="button" class="ops-tab-btn" id="tab-all-buses" onclick="setFleetTab('all')">
                        <i class="bi bi-grid-fill"></i>
                        <span>All Buses</span>
                        <span class="badge ops-tab-badge" id="badge-all-count">0</span>
                    </button>
                    <button type="button" class="ops-tab-btn" id="tab-upcoming-trips" onclick="setFleetTab('upcoming')">
                        <i class="bi bi-clock-fill"></i>
                        <span>Upcoming</span>
                        <span class="badge ops-tab-badge" id="badge-upcoming-count">0</span>
                    </button>
                </div>
            </div>

            <div class="ops-filter-bar mb-2 flex-shrink-0">
                <div class="ops-search-wrapper flex-grow-1">
                    <span class="ops-search-badge"><i class="bi bi-search"></i></span>
                    <input
                        type="text"
                        id="fleet-search-input"
                        class="ops-search-input"
                        placeholder="Search bus, plate, driver, route..."
                        oninput="handleFleetSearchInput(this)"
                    >
                    <button type="button" class="ops-search-clear" id="fleet-search-clear" onclick="clearFleetSearch()" title="Clear search" style="display:none;">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>

                <div class="ops-filter-item">
                    <div class="ops-filter-group">
                        <span class="ops-filter-badge"><i class="bi bi-signpost-2-fill"></i></span>
                        <select id="fleet-route-filter" class="ops-select" onchange="filterFleetTable()" title="Filter by Route">
                            <option value="all">All Routes</option>
                            <option value="Cabanatuan to Rizal Route">Cabanatuan → Rizal</option>
                        </select>
                    </div>
                </div>

                <div class="ops-filter-item">
                    <div class="ops-filter-group">
                        <span class="ops-filter-badge"><i class="bi bi-activity"></i></span>
                        <select id="fleet-status-filter" class="ops-select" onchange="handleStatusFilterChange(this.value)" title="Filter by Status">
                            <option value="all">All Status</option>
                            <option value="Departed">In Progress</option>
                            <option value="Scheduled">Scheduled</option>
                        </select>
                    </div>
                </div>

                <div class="ops-filter-item">
                    <div class="ops-filter-group">
                        <span class="ops-filter-badge"><i class="bi bi-shield-fill-check"></i></span>
                        <select id="fleet-inspector-filter" class="ops-select" onchange="filterFleetTable()" title="Filter by Inspector">
                            <option value="all">All Inspectors</option>
                            <option value="assigned">With Inspector</option>
                            <option value="unassigned">No Inspector</option>
                        </select>
                    </div>
                </div>

                <button type="button" class="ops-filter-reset-btn" id="fleet-filter-reset" onclick="resetFleetFilters()" title="Reset search & filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Reset</span>
                </button>
            </div>

            <div class="table-responsive ops-table-container flex-grow-1">
                <table class="table ops-table align-middle mb-0" id="fleet-table">
                    <thead>
                        <tr>
                            <th style="min-width: 140px;">Bus &amp; Route</th>
                            <th style="min-width: 150px;">Driver / Inspector</th>
                            <th style="min-width: 95px;">Status</th>
                            <th style="min-width: 95px;">Occupancy</th>
                            <th style="min-width: 100px;">Speed / GPS</th>
                            <th style="min-width: 70px; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="fleet-table-body">
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <div class="col-12 col-xl-4 d-flex flex-column">
        <div class="ops-activity-card h-100 d-flex flex-column flex-grow-1">

            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom flex-shrink-0">
                <h3 class="ops-section-title mb-0">
                    Recent Activity
                </h3>
                <span class="ops-meta-sub" id="recent-activity-status-tag">
                    <span class="pulse-dot"></span> Live Log
                </span>
            </div>

            <div class="ops-timeline-container flex-grow-1" id="recent-activity-timeline">
            </div>

            <div class="ops-activity-footer flex-shrink-0 pt-2 mt-auto border-top d-flex justify-content-between align-items-center ops-meta-sub">
                <span id="activity-fleet-sync-text">Synchronized with Fleet</span>
                <span id="activity-count-badge" class="badge ops-tab-badge">0 events</span>
            </div>

        </div>
    </div>

</div>
