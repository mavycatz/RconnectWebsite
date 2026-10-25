<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="sys-panel h-100 d-flex flex-column">
            <div class="sys-panel-header">
                <h5 class="sys-panel-title">
                    <i class="bi bi-clock-history"></i> Latest System Activity
                </h5>
                <span style="font-size:0.72rem;color:#7e8f7e;">
                    <span class="sys-live-dot" style="width:6px;height:6px;"></span> Live feed &middot; newest first
                </span>
            </div>

            <div class="p-0 flex-grow-1 sys-table-scroll">
                <table class="sys-table sys-table-compact">
                    <thead>
                        <tr>
                            <th style="width:14%;">Time</th>
                            <th style="width:13%;">Status</th>
                            <th style="width:15%;">Module</th>
                            <th style="width:40%;">Activity</th>
                            <th style="width:18%;">User</th>
                        </tr>
                    </thead>
                    <tbody id="latestActivityTableBody">
                        <tr>
                            <td colspan="5" class="text-center py-3 text-muted">
                                <div class="spinner-border spinner-border-sm text-success me-2" role="status"></div>
                                Loading latest events...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="sys-panel h-100 d-flex flex-column">
            <div class="sys-panel-header">
                <h5 class="sys-panel-title">
                    <i class="bi bi-people-fill"></i> Active Sessions
                    <span id="activeSessionCount" style="font-size:0.72rem;font-weight:600;background:#eef6ef;border:1px solid #cce8d3;color:#198754;padding:2px 8px;border-radius:12px;">
                        0 Active
                    </span>
                </h5>
                <div id="activeSessionsLiveBadge" class="d-flex align-items-center gap-1 d-none">
                    <span class="sys-live-dot"></span>
                    <span style="font-size:0.7rem;font-weight:600;color:#198754;">LIVE</span>
                </div>
            </div>

            <div class="sys-panel-body flex-grow-1 d-flex flex-column p-2 sys-sessions-scroll">
                <div id="activeSessionsContainer" class="flex-grow-1 d-flex flex-column">
                    <div class="sys-empty-state my-auto">
                        <div class="spinner-border spinner-border-sm text-success mb-2" role="status"></div>
                        <p class="mb-0" style="font-size:0.78rem;">Loading active sessions...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
