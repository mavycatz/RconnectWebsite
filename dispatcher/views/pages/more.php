<div class="page-title-row">
  <h1 class="page-title"><i class="bi bi-grid-3x3-gap"></i> <?php echo htmlspecialchars($translations['nav.more'] ?? 'More', ENT_QUOTES, 'UTF-8'); ?></h1>
</div>

<div class="page-wrap">
  <div class="section-card">
    <div class="section-head">
      <h6><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($translations['problems.title'] ?? 'Bus Problem Reports', ENT_QUOTES, 'UTF-8'); ?></h6>
      <span class="section-count" id="moreProblemCount" hidden>0</span>
    </div>
    <div class="filter-row" id="problemFilterRow" role="group" aria-label="<?php echo htmlspecialchars($translations['problems.title'] ?? 'Bus Problem Reports', ENT_QUOTES, 'UTF-8'); ?>">
      <button type="button" class="filter-chip active" onclick="setProblemStatusFilter(this,'')"><?php echo htmlspecialchars($translations['summary.all'] ?? 'All', ENT_QUOTES, 'UTF-8'); ?></button>
      <button type="button" class="filter-chip" onclick="setProblemStatusFilter(this,'waiting')"><?php echo htmlspecialchars($translations['problems.waiting'] ?? 'Waiting', ENT_QUOTES, 'UTF-8'); ?></button>
      <button type="button" class="filter-chip" onclick="setProblemStatusFilter(this,'acknowledged')"><?php echo htmlspecialchars($translations['problems.acknowledged'] ?? 'Acknowledged', ENT_QUOTES, 'UTF-8'); ?></button>
      <button type="button" class="filter-chip" onclick="setProblemStatusFilter(this,'resolved')"><?php echo htmlspecialchars($translations['problems.resolved'] ?? 'Resolved', ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
    <div class="queue-tools">
      <input type="text" id="problemSearchInput" placeholder="<?php echo htmlspecialchars($translations['problems.search_placeholder'] ?? 'Search driver, bus, trip, or type...', ENT_QUOTES, 'UTF-8'); ?>" onkeydown="if(event.key==='Enter'){loadProblemReports();}" aria-label="<?php echo htmlspecialchars($translations['problems.search_placeholder'] ?? 'Search problem reports', ENT_QUOTES, 'UTF-8'); ?>">
      <button type="button" class="btn-dispatch" onclick="loadProblemReports()" aria-label="<?php echo htmlspecialchars($translations['common.refresh'] ?? 'Refresh', ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-arrow-clockwise"></i><span class="txt"><?php echo htmlspecialchars($translations['common.refresh'] ?? 'Refresh', ENT_QUOTES, 'UTF-8'); ?></span></button>
    </div>
    <div class="dispatch-list" id="problemReportsList" aria-live="polite"></div>
  </div>

  <div class="section-card more-section">
    <div class="section-head">
      <h6><i class="bi bi-printer"></i> <?php echo htmlspecialchars($translations['more.reports_title'] ?? 'Reports', ENT_QUOTES, 'UTF-8'); ?></h6>
    </div>
    <button type="button" class="settings-row" onclick="printReport()">
      <span class="sr-main"><i class="bi bi-printer"></i> <?php echo htmlspecialchars($translations['common.print_report'] ?? 'Print Report', ENT_QUOTES, 'UTF-8'); ?></span>
      <i class="bi bi-chevron-right"></i>
    </button>
  </div>

  <div class="section-card">
    <div class="section-head">
      <h6><i class="bi bi-bell"></i> <?php echo htmlspecialchars($translations['more.notifications_title'] ?? 'Notifications', ENT_QUOTES, 'UTF-8'); ?></h6>
      <span class="section-count" id="notificationsCount" hidden>0</span>
    </div>
    <div id="notificationsList" aria-live="polite">
      <div class="loading"><?php echo htmlspecialchars($translations['common.loading'] ?? 'Loading...', ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
  </div>

  <div class="section-card more-section">
    <div class="section-head">
      <h6><i class="bi bi-gear"></i> <?php echo htmlspecialchars($translations['more.settings_title'] ?? 'Settings', ENT_QUOTES, 'UTF-8'); ?></h6>
    </div>
    <label class="skip-label" for="settingsLanguage"><?php echo htmlspecialchars($translations['app.language'] ?? 'Language', ENT_QUOTES, 'UTF-8'); ?></label>
    <select id="settingsLanguage" class="form-field" onchange="changeLanguage(this.value)" aria-label="<?php echo htmlspecialchars($translations['app.language'] ?? 'Language', ENT_QUOTES, 'UTF-8'); ?>">
      <?php foreach ($languages as $code => $label): ?>
      <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $code === $lang ? ' selected' : ''; ?>><?php echo htmlspecialchars($translations[$langKeys[$code] ?? 'app.english'] ?? $label, ENT_QUOTES, 'UTF-8'); ?></option>
      <?php endforeach; ?>
    </select>
    <a class="settings-row" href="<?php echo htmlspecialchars('/dispatcher/?d=' . urlencode($_SESSION['dashboard_secret'] ?? '') . '&route=clear-terminal', ENT_QUOTES, 'UTF-8'); ?>">
      <span class="sr-main"><i class="bi bi-arrow-left-right"></i> <?php echo htmlspecialchars($translations['common.switch_terminal'] ?? 'Switch Terminal', ENT_QUOTES, 'UTF-8'); ?></span>
      <i class="bi bi-chevron-right"></i>
    </a>
    <button type="button" class="settings-row" onclick="triggerInstall()">
      <span class="sr-main"><i class="bi bi-download"></i> <?php echo htmlspecialchars($translations['common.install_app'] ?? 'Install App', ENT_QUOTES, 'UTF-8'); ?></span>
      <i class="bi bi-chevron-right"></i>
    </button>
    <button type="button" class="settings-row" onclick="logout()">
      <span class="sr-main"><i class="bi bi-box-arrow-right"></i> <?php echo htmlspecialchars($translations['common.logout'] ?? 'Logout', ENT_QUOTES, 'UTF-8'); ?></span>
      <i class="bi bi-chevron-right"></i>
    </button>
  </div>
</div>

<div class="modal-overlay" id="resolveProblemModal">
  <div class="modal-content modal-sticky-content">
    <div class="modal-header">
      <h6><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($translations['problems.resolve'] ?? 'Resolve Problem Report', ENT_QUOTES, 'UTF-8'); ?></h6>
      <button class="modal-close" onclick="closeModal('resolveProblemModal')" aria-label="<?php echo htmlspecialchars($translations['common.close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-sticky-scroll">
      <div id="resolveProblemSummary" style="margin-bottom:12px;padding:10px 12px;background:#fff;border:1px solid var(--border-light);border-radius:var(--radius-sm);font-size:12px;color:var(--text-primary);"></div>
      <label class="skip-option">
        <input type="checkbox" id="resolveReplacementRequired">
        <span><?php echo htmlspecialchars($translations['problems.replacement_required'] ?? 'Trip requires bus replacement', ENT_QUOTES, 'UTF-8'); ?></span>
      </label>
    </div>
    <div class="modal-sticky-actions">
      <div class="confirm-actions">
        <button type="button" class="btn-action btn-view" style="flex:1;min-height:44px;" onclick="closeModal('resolveProblemModal')"><i class="bi bi-x-lg"></i> <?php echo htmlspecialchars($translations['common.cancel'] ?? 'Cancel', ENT_QUOTES, 'UTF-8'); ?></button>
        <button type="button" class="btn-primary-app" id="resolveProblemConfirmBtn" style="flex:1;" onclick="resolveProblemReport()"><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($translations['problems.resolve_action'] ?? 'RESOLVE', ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="replacementBusModal">
  <div class="modal-content modal-sticky-content">
    <div class="modal-header">
      <h6><i class="bi bi-arrow-left-right"></i> <?php echo htmlspecialchars($translations['problems.assign_replacement'] ?? 'Assign Replacement Bus', ENT_QUOTES, 'UTF-8'); ?></h6>
      <button class="modal-close" onclick="closeModal('replacementBusModal')" aria-label="<?php echo htmlspecialchars($translations['common.close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-sticky-scroll">
      <div id="replacementReportSummary" style="margin-bottom:8px;padding:10px 12px;background:#fff;border:1px solid var(--border-light);border-radius:var(--radius-sm);font-size:12px;color:var(--text-primary);"></div>
      <div id="replacementBusList"></div>
    </div>
    <div class="modal-sticky-actions">
      <div id="replacementDriverRow" style="display:none;margin-bottom:10px;">
        <label class="skip-label" for="replacementDriverSelect"><i class="bi bi-person-fill-gear"></i> <?php echo htmlspecialchars($translations['problems.replacement_driver'] ?? 'Replacement Driver', ENT_QUOTES, 'UTF-8'); ?></label>
        <select id="replacementDriverSelect" class="form-field"></select>
        <div style="font-size:10.5px;color:var(--text-muted);margin-top:3px;"><?php echo htmlspecialchars($translations['problems.replacement_driver_hint'] ?? 'Defaults to the bus\u2019s assigned driver when left empty.', ENT_QUOTES, 'UTF-8'); ?></div>
      </div>
      <div class="modal-action-hint" id="replacementSelectedLabel"><?php echo htmlspecialchars($translations['problems.select_bus_hint'] ?? 'Select a bus to continue', ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="confirm-actions">
        <button type="button" class="btn-action btn-view" style="flex:1;min-height:44px;" onclick="closeModal('replacementBusModal')"><i class="bi bi-x-lg"></i> <?php echo htmlspecialchars($translations['common.cancel'] ?? 'Cancel', ENT_QUOTES, 'UTF-8'); ?></button>
        <button type="button" class="btn-primary-app" id="replacementAssignBtn" style="flex:1;" disabled onclick="assignReplacementBus()"><i class="bi bi-arrow-left-right"></i> <?php echo htmlspecialchars($translations['problems.assign_action'] ?? 'ASSIGN', ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
    </div>
  </div>
</div>
