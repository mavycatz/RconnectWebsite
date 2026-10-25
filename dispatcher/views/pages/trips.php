<div class="page-title-row">
  <h1 class="page-title"><i class="bi bi-signpost-split"></i> <?php echo htmlspecialchars($translations['nav.trips'] ?? 'Trips', ENT_QUOTES, 'UTF-8'); ?></h1>
</div>

<div class="page-wrap">
  <div class="filter-row" id="tripFilterRow" role="group" aria-label="<?php echo htmlspecialchars($translations['trips.filter_label'] ?? 'Trip state', ENT_QUOTES, 'UTF-8'); ?>">
    <button type="button" class="filter-chip active" data-state="all" onclick="setTripFilter(this,'all')"><?php echo htmlspecialchars($translations['summary.all'] ?? 'All', ENT_QUOTES, 'UTF-8'); ?></button>
    <button type="button" class="filter-chip" data-state="active" onclick="setTripFilter(this,'active')"><?php echo htmlspecialchars($translations['trips.filter_active'] ?? 'Active', ENT_QUOTES, 'UTF-8'); ?></button>
    <button type="button" class="filter-chip" data-state="inbound" onclick="setTripFilter(this,'inbound')"><?php echo htmlspecialchars($translations['trips.filter_arriving'] ?? 'Arriving', ENT_QUOTES, 'UTF-8'); ?></button>
    <button type="button" class="filter-chip" data-state="delayed" onclick="setTripFilter(this,'delayed')"><?php echo htmlspecialchars($translations['trips.filter_delayed'] ?? 'Delayed', ENT_QUOTES, 'UTF-8'); ?></button>
    <button type="button" class="filter-chip" data-state="completed" onclick="setTripFilter(this,'completed')"><?php echo htmlspecialchars($translations['trips.filter_completed'] ?? 'Completed', ENT_QUOTES, 'UTF-8'); ?></button>
    <button type="button" class="filter-chip" data-state="Scheduled" onclick="setTripFilter(this,'Scheduled')"><?php echo htmlspecialchars($translations['trips.filter_scheduled'] ?? 'Scheduled', ENT_QUOTES, 'UTF-8'); ?></button>
    <button type="button" class="filter-chip" data-state="Cancelled" onclick="setTripFilter(this,'Cancelled')"><?php echo htmlspecialchars($translations['trips.filter_cancelled'] ?? 'Cancelled', ENT_QUOTES, 'UTF-8'); ?></button>
  </div>

  <div class="queue-updated-row">
    <span class="queue-updated" id="tripsUpdatedLabel" aria-live="polite"></span>
    <button type="button" class="btn-refresh" onclick="loadTodayTrips({force:true})"><i class="bi bi-arrow-clockwise"></i> <?php echo htmlspecialchars($translations['common.refresh'] ?? 'Refresh', ENT_QUOTES, 'UTF-8'); ?></button>
  </div>

  <div class="dispatch-list" id="tripsList" aria-live="polite"></div>

  <div class="history-section">
    <div class="history-head">
      <h6><i class="bi bi-clock-history"></i> <?php echo htmlspecialchars($translations['history.search'] ?? 'Search Older Trips', ENT_QUOTES, 'UTF-8'); ?></h6>
      <button type="button" class="btn-history-toggle" id="historySearchBtn" onclick="toggleHistorySearch()" aria-expanded="false" aria-controls="historySearchPanel"><i class="bi bi-search"></i> <?php echo htmlspecialchars($translations['history.open_search'] ?? 'Open', ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
    <div id="historySearchPanel" style="display:none;">
      <div class="hist-bar" id="historyControls">
        <span class="hb-label"><i class="bi bi-clock-history"></i> <?php echo htmlspecialchars($translations['history.label'] ?? 'History', ENT_QUOTES, 'UTF-8'); ?></span>
        <div class="hb-dates">
          <input type="date" id="histDateFrom" aria-label="<?php echo htmlspecialchars($translations['history.from'] ?? 'From date', ENT_QUOTES, 'UTF-8'); ?>">
          <span class="hb-sep">—</span>
          <input type="date" id="histDateTo" aria-label="<?php echo htmlspecialchars($translations['history.to'] ?? 'To date', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <button class="hb-go" onclick="loadHistory()" aria-label="<?php echo htmlspecialchars($translations['history.search'] ?? 'Search Older Trips', ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-search"></i></button>
        <span class="hb-count" id="histResultBadge">all</span>
      </div>
      <div class="hist-bar" id="historyBusBar">
        <input type="text" id="histBusInput" placeholder="<?php echo htmlspecialchars($translations['trips.history_bus_placeholder'] ?? 'Filter by bus...', ENT_QUOTES, 'UTF-8'); ?>" onkeydown="if(event.key==='Enter'){loadHistory();}" aria-label="<?php echo htmlspecialchars($translations['trips.history_bus_placeholder'] ?? 'Filter by bus...', ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="pg-row" id="paginationControls">
        <button class="pg-btn" id="prevPageBtn" onclick="changePage(-1)"><i class="bi bi-chevron-left"></i> <?php echo htmlspecialchars($translations['trips.prev'] ?? 'Prev', ENT_QUOTES, 'UTF-8'); ?></button>
        <span class="pg-info" id="pageInfo"><?php echo htmlspecialchars($translations['trips.page'] ?? 'Page', ENT_QUOTES, 'UTF-8'); ?> 1</span>
        <button class="pg-btn" id="nextPageBtn" onclick="changePage(1)"><?php echo htmlspecialchars($translations['trips.next'] ?? 'Next', ENT_QUOTES, 'UTF-8'); ?> <i class="bi bi-chevron-right"></i></button>
      </div>
      <div class="dispatch-list" id="dispatchList"></div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="tripDetailModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6 id="tripDetailTitle"><i class="bi bi-info-circle"></i> Trip Details</h6>
      <button class="modal-close" onclick="closeModal('tripDetailModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="trip-detail-grid" id="tripDetailBody"></div>
    <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;" id="tripDetailActions"></div>
  </div>
</div>

<div class="modal-overlay" id="arriveModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6><i class="bi bi-flag"></i> <?php echo htmlspecialchars($translations['trips.confirm_arrival_title'] ?? 'Confirm Arrival', ENT_QUOTES, 'UTF-8'); ?></h6>
      <button class="modal-close" onclick="closeModal('arriveModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <p style="font-size:14px;color:var(--text-muted);margin-bottom:8px;"><?php echo htmlspecialchars($translations['trips.confirm_arrival'] ?? 'Confirm arrival for this trip?', ENT_QUOTES, 'UTF-8'); ?></p>

    <div id="arriveFuelBox" style="margin-bottom:12px;padding:12px;background:#fff;border:1px solid var(--border-light);border-radius:var(--radius-sm);">
      <div style="font-weight:600;font-size:12px;color:var(--text-primary);margin-bottom:4px;"><i class="bi bi-speedometer2" style="color:var(--primary);"></i> <?php echo htmlspecialchars($translations['trips.arrival_odometer_title'] ?? 'Arrival Odometer Confirmation', ENT_QUOTES, 'UTF-8'); ?></div>
      <div style="font-size:11px;color:var(--text-muted);margin-bottom:8px;" id="arriveTripOdoInfo">Starting ODO: - KM</div>
      <label for="arriveEndingOdo" style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Ending ODO (KM) <span style="color:#c62828;">*</span></label>
      <input type="number" step="any" id="arriveEndingOdo" class="form-field" placeholder="e.g. 1076" oninput="onArriveOdoChange()">

      <div id="arriveCalcPreview" style="display:none;margin-top:8px;padding:8px 10px;background:var(--bg-card);border:1px solid var(--border-card);border-radius:10px;font-size:11px;color:var(--text-primary);">
        <div style="display:flex;justify-content:space-between;margin-bottom:2px;">
          <span>Distance Traveled:</span> <strong id="arriveDistText">-</strong>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span><?php echo htmlspecialchars($translations['trips.expected_fuel_estimate'] ?? 'Expected Fuel Consumption (estimate):', ENT_QUOTES, 'UTF-8'); ?></span> <strong id="arriveExpFuelText">-</strong>
        </div>
      </div>
    </div>

    <p style="font-size:12px;color:var(--text-muted);margin:6px 0 12px;"><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($translations['trips.arrival_auto'] ?? 'Arrival time and passenger count will be auto-calculated.', ENT_QUOTES, 'UTF-8'); ?></p>
    <div class="form-error" id="arriveError" style="display:none;"></div>
    <button class="btn-primary-app" id="arriveConfirmBtn" onclick="recordArrival()"><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($translations['trips.confirm_arrival_button'] ?? 'Confirm Arrival', ENT_QUOTES, 'UTF-8'); ?></button>
  </div>
</div>

<div class="modal-overlay" id="editFuelModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6><i class="bi bi-fuel-pump"></i> Fuel & Odometer Record</h6>
      <button class="modal-close" onclick="closeModal('editFuelModal')"><i class="bi bi-x-lg"></i></button>
    </div>

    <div id="editFuelTripBadge" style="margin-bottom:12px;padding:8px 12px;background:var(--bg-card);border:1px solid var(--border-card);border-radius:var(--radius-sm);font-size:12px;color:var(--text-primary);">
      <div><strong>Trip #<span id="editFuelTripNo">-</span></strong> &bull; <span id="editFuelRoute">-</span></div>
      <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">Bus: <span id="editFuelBus">-</span> &bull; Driver: <span id="editFuelDriver">-</span></div>
    </div>

    <input type="hidden" id="editFuelTripId" value="">
    <input type="hidden" id="editFuelLogId" value="">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
      <div>
        <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Starting ODO (KM)</label>
        <input type="number" step="any" id="editStartingOdo" class="form-field" placeholder="1001" oninput="onEditFuelCalc()">
      </div>
      <div>
        <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Ending ODO (KM) <span style="color:#c62828;">*</span></label>
        <input type="number" step="any" id="editEndingOdo" class="form-field" placeholder="1076" oninput="onEditFuelCalc()">
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
      <div>
        <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Bus KPL (KM/L)</label>
        <input type="number" step="0.01" id="editKpl" class="form-field" placeholder="9.0" oninput="onEditFuelCalc()">
      </div>
      <div>
        <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Actual Liters (L)</label>
        <input type="number" step="0.01" id="editActualLiters" class="form-field" placeholder="50" oninput="onEditFuelCalc()">
      </div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 10px;background:#fff;border:1px solid var(--border-light);border-radius:10px;font-size:11px;color:var(--text-muted);margin-bottom:10px;">
      <span>Fuel Price (Diesel):</span>
      <strong id="editFuelPriceText" style="color:var(--primary-dark);">₱--/L</strong>
    </div>

    <div style="background:var(--bg-card);border:1.5px solid var(--border-card);border-radius:var(--radius-sm);padding:10px 12px;margin-bottom:14px;font-size:12px;">
      <div style="font-weight:700;color:var(--primary-dark);margin-bottom:6px;font-size:11px;text-transform:uppercase;letter-spacing:0.3px;"><i class="bi bi-calculator"></i> System Calculations</div>
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
        <span style="color:var(--text-muted);">Distance Traveled:</span>
        <strong id="calcDistanceText" style="color:var(--text-primary);">-</strong>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
        <span style="color:var(--text-muted);">Actual KPL (Distance &divide; Liters):</span>
        <strong id="calcActualKplText" style="color:var(--primary-dark);">-</strong>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
        <span style="color:var(--text-muted);">Expected Fuel Consumption:</span>
        <strong id="calcExpectedFuelText" style="color:var(--text-primary);">-</strong>
      </div>
      <div style="display:flex;justify-content:space-between;">
        <span style="color:var(--text-muted);">Total Fuel Cost:</span>
        <strong id="calcFuelCostText" style="color:#2e7d32;">-</strong>
      </div>
    </div>

    <div style="margin-bottom:12px;">
      <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Reason for Edit <span style="color:#c62828;">*</span></label>
      <input type="text" id="editFuelReason" class="form-field" placeholder="Why is this record being corrected?">
    </div>

    <button class="btn-primary-app" id="saveFuelBtn" onclick="saveTripFuelEdit()"><i class="bi bi-check-circle"></i> Save Fuel Record</button>
  </div>
</div>
