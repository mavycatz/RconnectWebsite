<div class="page-title-row">
  <h1 class="page-title"><i class="bi bi-person-check"></i> <?php echo htmlspecialchars($translations['nav.attendance'] ?? 'Attendance', ENT_QUOTES, 'UTF-8'); ?></h1>
</div>
<div class="page-wrap">
  <div class="queue-updated-row" style="padding-left:0;padding-right:0;">
    <span class="queue-updated" id="attendanceUpdatedLabel" aria-live="polite"></span>
    <button type="button" class="btn-refresh" onclick="loadAttendance({force:true})"><i class="bi bi-arrow-clockwise"></i> <?php echo htmlspecialchars($translations['common.refresh'] ?? 'Refresh', ENT_QUOTES, 'UTF-8'); ?></button>
  </div>
  <div id="attendanceList" aria-live="polite"></div>
</div>
