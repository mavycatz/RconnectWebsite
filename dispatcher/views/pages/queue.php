<div class="page-title-row">
  <h1 class="page-title"><i class="bi bi-list-check"></i> <?php echo htmlspecialchars($translations['app.title'] ?? 'Manage Trip Queues', ENT_QUOTES, 'UTF-8'); ?></h1>
</div>

<div class="queue-view" id="queueView">
  <div class="filter-row" id="queueFilterRow" role="group" aria-label="<?php echo htmlspecialchars($translations['app.title'] ?? 'Manage Trip Queues', ENT_QUOTES, 'UTF-8'); ?>">
    <button type="button" class="filter-chip active" data-status="all" data-label="<?php echo htmlspecialchars($translations['summary.all'] ?? 'All', ENT_QUOTES, 'UTF-8'); ?>" onclick="setQueueFilter(this,'all')"><?php echo htmlspecialchars($translations['summary.all'] ?? 'All', ENT_QUOTES, 'UTF-8'); ?></button>
    <button type="button" class="filter-chip" data-status="waiting_for_driver" data-label="<?php echo htmlspecialchars($translations['summary.waiting_for_driver'] ?? 'Waiting for Driver', ENT_QUOTES, 'UTF-8'); ?>" onclick="setQueueFilter(this,'waiting_for_driver')"><?php echo htmlspecialchars($translations['summary.waiting_for_driver'] ?? 'Waiting for Driver', ENT_QUOTES, 'UTF-8'); ?></button>
    <button type="button" class="filter-chip" data-status="driver_assigned" data-label="<?php echo htmlspecialchars($translations['summary.driver_assigned'] ?? 'Driver Assigned', ENT_QUOTES, 'UTF-8'); ?>" onclick="setQueueFilter(this,'driver_assigned')"><?php echo htmlspecialchars($translations['summary.driver_assigned'] ?? 'Driver Assigned', ENT_QUOTES, 'UTF-8'); ?></button>
    <button type="button" class="filter-chip" data-status="checked_in" data-label="<?php echo htmlspecialchars($translations['summary.checked_in'] ?? 'Checked In', ENT_QUOTES, 'UTF-8'); ?>" onclick="setQueueFilter(this,'checked_in')"><?php echo htmlspecialchars($translations['summary.checked_in'] ?? 'Checked In', ENT_QUOTES, 'UTF-8'); ?></button>
    <button type="button" class="filter-chip" data-status="ready" data-label="<?php echo htmlspecialchars($translations['summary.ready'] ?? 'Ready to Dispatch', ENT_QUOTES, 'UTF-8'); ?>" onclick="setQueueFilter(this,'ready')"><?php echo htmlspecialchars($translations['summary.ready'] ?? 'Ready to Dispatch', ENT_QUOTES, 'UTF-8'); ?></button>
    <button type="button" class="filter-chip" data-status="departed" data-label="<?php echo htmlspecialchars($translations['summary.on_route'] ?? 'On Route', ENT_QUOTES, 'UTF-8'); ?>" onclick="setQueueFilter(this,'departed')"><?php echo htmlspecialchars($translations['summary.on_route'] ?? 'On Route', ENT_QUOTES, 'UTF-8'); ?></button>
  </div>

  <div class="next-dispatch" id="nextDispatchPanel" aria-live="polite"></div>

  <div class="admin-queue-banner" id="adminQueueBanner" style="display:none;" aria-live="polite"></div>

  <div class="why-not-card" id="whyNotPanel" style="display:none;" aria-live="polite"></div>

  <div class="queue-tools">
    <input type="text" id="searchInput" placeholder="<?php echo htmlspecialchars($translations['common.search_placeholder'] ?? 'Search bus, driver, or trip...', ENT_QUOTES, 'UTF-8'); ?>" oninput="onSearch()" aria-label="<?php echo htmlspecialchars($translations['common.search_placeholder'] ?? 'Search bus, driver, or trip...', ENT_QUOTES, 'UTF-8'); ?>">
    <select id="routeFilter" class="form-field route-filter" onchange="onRouteFilter()" aria-label="<?php echo htmlspecialchars($translations['common.all_routes'] ?? 'All Routes', ENT_QUOTES, 'UTF-8'); ?>"><option value=""><?php echo htmlspecialchars($translations['common.all_routes'] ?? 'All Routes', ENT_QUOTES, 'UTF-8'); ?></option></select>
    <button class="btn-dispatch" onclick="showCreateModal()" aria-label="<?php echo htmlspecialchars($translations['common.dispatch_bus'] ?? 'Dispatch Bus', ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-send"></i><span class="txt"><?php echo htmlspecialchars($translations['common.dispatch_bus'] ?? 'Dispatch Bus', ENT_QUOTES, 'UTF-8'); ?></span></button>
  </div>

  <div class="queue-updated-row">
    <span class="queue-updated" id="queueUpdatedLabel" aria-live="polite"></span>
    <span class="queue-stale" id="queueStaleLabel" style="display:none;"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($translations['common.stale'] ?? 'Queue may be outdated', ENT_QUOTES, 'UTF-8'); ?></span>
    <button type="button" class="btn-refresh" onclick="loadQueue({force:true})" aria-label="<?php echo htmlspecialchars($translations['common.refresh'] ?? 'Refresh', ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-arrow-clockwise"></i> <?php echo htmlspecialchars($translations['common.refresh'] ?? 'Refresh', ENT_QUOTES, 'UTF-8'); ?></button>
  </div>

  <div class="queue-grid" id="queueList" aria-live="polite"></div>
</div>

<div class="modal-overlay" id="createModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6><i class="bi bi-send"></i> <?php echo htmlspecialchars($translations['common.dispatch_bus'] ?? 'Dispatch Bus', ENT_QUOTES, 'UTF-8'); ?></h6>
      <button class="modal-close" onclick="closeModal('createModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <label class="skip-label" for="newBus"><?php echo htmlspecialchars($translations['details.bus'] ?? 'Bus', ENT_QUOTES, 'UTF-8'); ?></label>
    <select id="newBus" class="form-field"><option value=""><?php echo htmlspecialchars($translations['common.loading_queue'] ?? 'Loading...', ENT_QUOTES, 'UTF-8'); ?></option></select>
    <label class="skip-label" for="newDriver"><?php echo htmlspecialchars($translations['details.driver'] ?? 'Driver', ENT_QUOTES, 'UTF-8'); ?></label>
    <select id="newDriver" class="form-field"><option value=""><?php echo htmlspecialchars($translations['common.loading_queue'] ?? 'Loading...', ENT_QUOTES, 'UTF-8'); ?></option></select>
    <div id="busQueueBadge" class="queue-readiness-box" style="display:none;"></div>
    <label class="skip-label" for="newRoute"><?php echo htmlspecialchars($translations['details.route'] ?? 'Route', ENT_QUOTES, 'UTF-8'); ?></label>
    <select id="newRoute" class="form-field"><option value=""><?php echo htmlspecialchars($translations['common.loading_queue'] ?? 'Loading...', ENT_QUOTES, 'UTF-8'); ?></option></select>
    <label class="skip-label" for="newDestTerminal"><?php echo htmlspecialchars($translations['details.destination'] ?? 'Destination', ENT_QUOTES, 'UTF-8'); ?></label>
    <select id="newDestTerminal" class="form-field"><option value=""><?php echo htmlspecialchars($translations['common.loading_queue'] ?? 'Loading...', ENT_QUOTES, 'UTF-8'); ?></option></select>
    <label class="skip-label" for="newPassengerCount"><?php echo htmlspecialchars($translations['details.passengers'] ?? 'Passengers Onboard', ENT_QUOTES, 'UTF-8'); ?></label>
    <input type="number" id="newPassengerCount" class="form-field" placeholder="<?php echo htmlspecialchars($translations['details.passengers'] ?? 'Passengers Onboard', ENT_QUOTES, 'UTF-8'); ?>" min="0">

    <!-- Odometer & Fuel Reference Section -->
    <div class="fuel-section" style="margin:4px 0 12px;border:1px solid var(--border-light);border-radius:var(--radius-sm);padding:12px;background:#fff;">
      <div style="font-weight:700;color:var(--text-primary);font-size:13px;margin-bottom:8px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;">
        <span><i class="bi bi-speedometer2" style="color:var(--primary);margin-right:5px;"></i><?php echo htmlspecialchars($translations['form.odo_fuel_section'] ?? 'Odometer & Fuel Reference', ENT_QUOTES, 'UTF-8'); ?></span>
        <span id="fuelPriceBadge" style="font-size:11px;font-weight:600;color:var(--primary-dark);background:var(--accent-light);padding:2px 8px;border-radius:999px;">Diesel: Loading...</span>
      </div>

      <div id="fuelDispatchBadge" style="font-size:11px;color:var(--text-muted);margin-bottom:8px;padding:6px 10px;background:var(--bg-card);border-radius:10px;border:1px dashed var(--border-card);display:none;">
        <i class="bi bi-check-circle text-success"></i> Bus: <strong id="fuelLinkedBus">-</strong> &bull; Driver: <strong id="fuelLinkedDriver">-</strong>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
        <div>
          <label for="newStartingOdo" style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;"><?php echo htmlspecialchars($translations['form.starting_odo'] ?? 'Starting Odometer (KM)', ENT_QUOTES, 'UTF-8'); ?> *</label>
          <input type="number" step="any" id="newStartingOdo" class="form-field" style="margin-bottom:0;" placeholder="e.g. 1001" required>
        </div>
        <div>
          <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;"><?php echo htmlspecialchars($translations['form.historical_kpl'] ?? 'Historical KPL (KM/L)', ENT_QUOTES, 'UTF-8'); ?></label>
          <div id="newHistoricalKpl" class="form-field" style="margin-bottom:0;background:#f5f5f5;color:#666;cursor:not-allowed;min-height:38px;display:flex;align-items:center;">&mdash;</div>
        </div>
      </div>

      <div>
        <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Ending ODO</label>
        <input type="text" id="newEndingOdo" class="form-field" style="margin-bottom:0;background:#f5f5f5;color:#888;cursor:not-allowed;" value="Not yet recorded" disabled readonly>
      </div>
    </div>

    <div class="modal-time-box"><i class="bi bi-clock"></i> Dispatch Time: <span id="dispatchTimeDisplay">--:--:--</span></div>
    <button class="btn-primary-app" onclick="createDispatch()"><i class="bi bi-send"></i> Dispatch Now</button>
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

<div class="modal-overlay" id="skipModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6><i class="bi bi-slash-circle"></i> <?php echo htmlspecialchars($translations['skip.title'] ?? 'SKIP BUS', ENT_QUOTES, 'UTF-8'); ?></h6>
      <button class="modal-close" onclick="closeModal('skipModal')" aria-label="<?php echo htmlspecialchars($translations['common.close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="skip-title" id="skipBusTitle"></div>
    <div class="skip-info" id="skipBusInfo"></div>
    <div class="skip-reason" id="skipBusReason"></div>
    <fieldset class="skip-fieldset">
      <legend><?php echo htmlspecialchars($translations['skip.reason'] ?? 'Reason', ENT_QUOTES, 'UTF-8'); ?></legend>
      <label class="skip-option"><input type="radio" name="skipReason" value="bus_not_present"> <?php echo htmlspecialchars($translations['reasons.bus_not_present'] ?? 'Bus not present', ENT_QUOTES, 'UTF-8'); ?></label>
      <label class="skip-option"><input type="radio" name="skipReason" value="driver_unavailable"> <?php echo htmlspecialchars($translations['reasons.driver_unavailable'] ?? 'Driver unavailable', ENT_QUOTES, 'UTF-8'); ?></label>
      <label class="skip-option"><input type="radio" name="skipReason" value="inspection_issue"> <?php echo htmlspecialchars($translations['reasons.inspection_issue'] ?? 'Inspection issue', ENT_QUOTES, 'UTF-8'); ?></label>
      <label class="skip-option"><input type="radio" name="skipReason" value="fuel_issue"> <?php echo htmlspecialchars($translations['reasons.fuel_issue'] ?? 'Fuel issue', ENT_QUOTES, 'UTF-8'); ?></label>
      <label class="skip-option"><input type="radio" name="skipReason" value="operational_delay"> <?php echo htmlspecialchars($translations['reasons.operational_delay'] ?? 'Operational delay', ENT_QUOTES, 'UTF-8'); ?></label>
      <label class="skip-option"><input type="radio" name="skipReason" value="other"> <?php echo htmlspecialchars($translations['reasons.other'] ?? 'Other', ENT_QUOTES, 'UTF-8'); ?></label>
    </fieldset>
    <label class="skip-label" for="skipNotes"><?php echo htmlspecialchars($translations['skip.notes'] ?? 'Notes (optional)', ENT_QUOTES, 'UTF-8'); ?></label>
    <textarea id="skipNotes" class="form-field skip-notes" rows="2" maxlength="500" placeholder="<?php echo htmlspecialchars($translations['skip.notes_placeholder'] ?? 'Add context for this decision...', ENT_QUOTES, 'UTF-8'); ?>"></textarea>
    <div class="form-error" id="skipError" style="display:none;"></div>
    <div class="confirm-actions">
      <button type="button" class="btn-cancel-app" onclick="closeModal('skipModal')"><?php echo htmlspecialchars($translations['common.cancel'] ?? 'Cancel', ENT_QUOTES, 'UTF-8'); ?></button>
      <button type="button" class="btn-danger-app" id="skipConfirmBtn" onclick="submitSkip()"><i class="bi bi-slash-circle"></i> <?php echo htmlspecialchars($translations['skip.button'] ?? 'SKIP BUS', ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="overtakeModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($translations['overtake.title'] ?? 'OUT-OF-ORDER DISPATCH', ENT_QUOTES, 'UTF-8'); ?></h6>
      <button class="modal-close" onclick="closeModal('overtakeModal')" aria-label="<?php echo htmlspecialchars($translations['common.close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-x-lg"></i></button>
    </div>
    <p class="overtake-text" id="overtakeReady"></p>
    <p class="overtake-text"><?php echo htmlspecialchars($translations['overtake.body'] ?? ':bus is Queue #:position.', ENT_QUOTES, 'UTF-8'); ?> <strong id="overtakeTarget"></strong></p>
    <label class="skip-label" for="overtakeReason"><?php echo htmlspecialchars($translations['overtake.reason'] ?? 'Reason', ENT_QUOTES, 'UTF-8'); ?></label>
    <select id="overtakeReason" class="form-field">
      <option value=""><?php echo htmlspecialchars($translations['overtake.select'] ?? 'Select reason', ENT_QUOTES, 'UTF-8'); ?></option>
      <option value="<?php echo htmlspecialchars($translations['overtake.reason.operational_need'] ?? 'Operational need', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translations['overtake.reason.operational_need'] ?? 'Operational need', ENT_QUOTES, 'UTF-8'); ?></option>
      <option value="<?php echo htmlspecialchars($translations['overtake.reason.passenger_load'] ?? 'Passenger load', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translations['overtake.reason.passenger_load'] ?? 'Passenger load', ENT_QUOTES, 'UTF-8'); ?></option>
      <option value="<?php echo htmlspecialchars($translations['overtake.reason.route_demand'] ?? 'Route demand', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translations['overtake.reason.route_demand'] ?? 'Route demand', ENT_QUOTES, 'UTF-8'); ?></option>
      <option value="<?php echo htmlspecialchars($translations['overtake.reason.vehicle_swap'] ?? 'Vehicle swap', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translations['overtake.reason.vehicle_swap'] ?? 'Vehicle swap', ENT_QUOTES, 'UTF-8'); ?></option>
      <option value="<?php echo htmlspecialchars($translations['overtake.reason.other'] ?? 'Other', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translations['overtake.reason.other'] ?? 'Other', ENT_QUOTES, 'UTF-8'); ?></option>
    </select>
    <div class="form-error" id="overtakeError" style="display:none;"></div>
    <div class="confirm-actions">
      <button type="button" class="btn-cancel-app" onclick="closeModal('overtakeModal')"><?php echo htmlspecialchars($translations['common.cancel'] ?? 'Cancel', ENT_QUOTES, 'UTF-8'); ?></button>
      <button type="button" class="btn-primary-app" onclick="submitOvertake()"><?php echo htmlspecialchars($translations['overtake.continue'] ?? 'CONTINUE', ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="confirmDispatchModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6><i class="bi bi-send"></i> <?php echo htmlspecialchars($translations['confirm.title'] ?? 'CONFIRM DISPATCH', ENT_QUOTES, 'UTF-8'); ?></h6>
      <button class="modal-close" onclick="closeModal('confirmDispatchModal')" aria-label="<?php echo htmlspecialchars($translations['common.close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-x-lg"></i></button>
    </div>
    <div id="confirmDispatchBody"></div>
    <div class="confirm-warning" id="confirmDispatchWarning" style="display:none;"></div>
    <p class="overtake-text" style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars($translations['confirm.note'] ?? 'Dispatching this trip will record the official dispatch time and actual dispatch order.', ENT_QUOTES, 'UTF-8'); ?></p>
    <div class="confirm-actions">
      <button type="button" class="btn-cancel-app" onclick="closeModal('confirmDispatchModal')"><?php echo htmlspecialchars($translations['common.cancel'] ?? 'Cancel', ENT_QUOTES, 'UTF-8'); ?></button>
      <button type="button" class="btn-primary-app" id="confirmDispatchBtn" onclick="confirmQueueDispatch()"><i class="bi bi-send"></i> <?php echo htmlspecialchars($translations['confirm.button'] ?? 'CONFIRM DISPATCH', ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="assignDriverModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6 id="assignDriverTitle"><i class="bi bi-person-plus"></i> <?php echo htmlspecialchars($translations['assign.title'] ?? 'ASSIGN DRIVER', ENT_QUOTES, 'UTF-8'); ?></h6>
      <button class="modal-close" onclick="closeModal('assignDriverModal')" aria-label="<?php echo htmlspecialchars($translations['common.close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-x-lg"></i></button>
    </div>
    <div id="assignDriverBody"></div>
    <label class="skip-label" for="assignDriverSelect"><?php echo htmlspecialchars($translations['assign.driver'] ?? 'Driver', ENT_QUOTES, 'UTF-8'); ?></label>
    <select id="assignDriverSelect" class="form-field"><option value=""><?php echo htmlspecialchars($translations['assign.select'] ?? 'Select driver', ENT_QUOTES, 'UTF-8'); ?></option></select>
    <div class="form-error" id="assignDriverError" style="display:none;"></div>
    <div class="confirm-actions">
      <button type="button" class="btn-cancel-app" onclick="closeModal('assignDriverModal')"><?php echo htmlspecialchars($translations['common.cancel'] ?? 'Cancel', ENT_QUOTES, 'UTF-8'); ?></button>
      <button type="button" class="btn-primary-app" id="assignDriverConfirmBtn" onclick="submitAssignDriver()"><i class="bi bi-person-check"></i> <?php echo htmlspecialchars($translations['assign.confirm'] ?? 'ASSIGN DRIVER', ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="prepareModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6 id="prepareTitle"><i class="bi bi-clipboard-check"></i> <?php echo htmlspecialchars($translations['prepare.title'] ?? 'PREPARE TRIP', ENT_QUOTES, 'UTF-8'); ?></h6>
      <button class="modal-close" onclick="closeModal('prepareModal')" aria-label="<?php echo htmlspecialchars($translations['common.close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-x-lg"></i></button>
    </div>
    <div id="prepareBody"></div>
    <label class="skip-label" for="preparePassengerCount"><?php echo htmlspecialchars($translations['prepare.passengers'] ?? 'Passengers Onboard', ENT_QUOTES, 'UTF-8'); ?></label>
    <input type="number" id="preparePassengerCount" class="form-field" min="0" value="0">
    <div class="fuel-section" style="margin:4px 0 12px;border:1px solid var(--border-light);border-radius:var(--radius-sm);padding:12px;background:#fff;">
      <div style="font-weight:700;color:var(--text-primary);font-size:13px;margin-bottom:8px;"><i class="bi bi-speedometer2" style="color:var(--primary);margin-right:5px;"></i><?php echo htmlspecialchars($translations['form.odo_fuel_section'] ?? 'Odometer & Fuel Reference', ENT_QUOTES, 'UTF-8'); ?></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div>
          <label for="prepareStartingOdo" style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;"><?php echo htmlspecialchars($translations['form.starting_odo'] ?? 'Starting Odometer (KM)', ENT_QUOTES, 'UTF-8'); ?> *</label>
          <input type="number" step="any" id="prepareStartingOdo" class="form-field" style="margin-bottom:0;" placeholder="e.g. 1001" required>
        </div>
        <div>
          <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;"><?php echo htmlspecialchars($translations['form.historical_kpl'] ?? 'Historical KPL (KM/L)', ENT_QUOTES, 'UTF-8'); ?></label>
          <div id="prepareHistoricalKpl" class="form-field" style="margin-bottom:0;background:#f5f5f5;color:#666;cursor:not-allowed;min-height:38px;display:flex;align-items:center;">&mdash;</div>
        </div>
      </div>
    </div>
    <div class="form-error" id="prepareError" style="display:none;"></div>
    <div class="confirm-actions">
      <button type="button" class="btn-cancel-app" onclick="closeModal('prepareModal')"><?php echo htmlspecialchars($translations['common.cancel'] ?? 'Cancel', ENT_QUOTES, 'UTF-8'); ?></button>
      <button type="button" class="btn-primary-app" id="prepareDispatchBtn" onclick="dispatchFromPrepare()"><i class="bi bi-send"></i> <?php echo htmlspecialchars($translations['action.dispatch'] ?? 'Dispatch', ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="dispatchSuccessModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($translations['success.title'] ?? 'TRIP DISPATCHED', ENT_QUOTES, 'UTF-8'); ?></h6>
      <button class="modal-close" onclick="closeModal('dispatchSuccessModal')" aria-label="<?php echo htmlspecialchars($translations['common.close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-x-lg"></i></button>
    </div>
    <div id="dispatchSuccessBody"></div>
    <div class="confirm-actions">
      <button type="button" class="btn-action btn-view" id="successViewTripBtn" style="flex:1;min-height:44px;"><i class="bi bi-eye"></i> <?php echo htmlspecialchars($translations['success.view_trip'] ?? 'VIEW TRIP', ENT_QUOTES, 'UTF-8'); ?></button>
      <button type="button" class="btn-primary-app" style="flex:1;" onclick="closeModal('dispatchSuccessModal')"><?php echo htmlspecialchars($translations['success.back'] ?? 'BACK TO QUEUE', ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
  </div>
</div>
