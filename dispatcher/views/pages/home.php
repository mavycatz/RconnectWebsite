<div class="page-title-row">
  <h1 class="page-title"><i class="bi bi-house-door"></i> <?php echo htmlspecialchars($translations['nav.home'] ?? 'Home', ENT_QUOTES, 'UTF-8'); ?></h1>
</div>
<div class="page-wrap">
  <div class="section-card">
    <div class="section-head">
      <h6><i class="bi bi-activity"></i> <?php echo htmlspecialchars($translations['home.summary_title'] ?? 'Operational Summary', ENT_QUOTES, 'UTF-8'); ?></h6>
      <span class="section-count" id="homeDateLabel"></span>
    </div>
    <div id="homeSummary" class="home-summary" aria-live="polite">
      <div class="loading"><?php echo htmlspecialchars($translations['common.loading'] ?? 'Loading...', ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
  </div>

  <div class="section-card">
    <div class="section-head">
      <h6><i class="bi bi-list-task"></i> <?php echo htmlspecialchars($translations['home.pending_title'] ?? 'Pending Tasks', ENT_QUOTES, 'UTF-8'); ?></h6>
      <span class="section-count" id="homePendingCount" hidden>0</span>
    </div>
    <div id="homePending" class="pending-list" aria-live="polite">
      <div class="loading"><?php echo htmlspecialchars($translations['common.loading'] ?? 'Loading...', ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
  </div>

  <div class="section-card" id="homeArrivingCard" hidden>
    <div class="section-head">
      <h6><i class="bi bi-bus-front"></i> <?php echo htmlspecialchars($translations['home.arriving_title'] ?? 'Arriving at this terminal', ENT_QUOTES, 'UTF-8'); ?></h6>
      <span class="section-count" id="homeArrivingCount" hidden>0</span>
    </div>
    <div id="homeArriving" class="pending-list" aria-live="polite">
      <div class="loading"><?php echo htmlspecialchars($translations['common.loading'] ?? 'Loading...', ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
  </div>

  <div class="section-card">
    <div class="section-head">
      <h6><i class="bi bi-bell"></i> <?php echo htmlspecialchars($translations['home.alerts_title'] ?? 'Alerts', ENT_QUOTES, 'UTF-8'); ?></h6>
      <span class="section-count" id="homeAlertsCount" hidden>0</span>
    </div>
    <div id="homeAlerts" class="alert-list" aria-live="polite">
      <div class="loading"><?php echo htmlspecialchars($translations['common.loading'] ?? 'Loading...', ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
  </div>

  <div class="queue-updated-row" style="padding-left:0;padding-right:0;">
    <span class="queue-updated" id="homeUpdatedLabel" aria-live="polite"></span>
    <button type="button" class="btn-refresh" onclick="loadHome({force:true})"><i class="bi bi-arrow-clockwise"></i> <?php echo htmlspecialchars($translations['common.refresh'] ?? 'Refresh', ENT_QUOTES, 'UTF-8'); ?></button>
  </div>
</div>
