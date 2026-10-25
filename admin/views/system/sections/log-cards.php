<div class="sys-panel p-3">
    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom" style="border-color:#f0ecce !important;">
        <h5 class="sys-panel-title">
            <i class="bi bi-card-list"></i> Detailed System Audit Feed
        </h5>
        <div class="d-flex align-items-center gap-2">
            <span style="font-size:0.72rem;color:#7e8f7e;">
                <i class="bi bi-info-circle"></i> Compact scan view
            </span>
        </div>
    </div>

    <div id="systemLogCards" class="d-flex flex-column">
        <div class="sys-empty-state">
            <div class="spinner-border spinner-border-sm text-success mb-2" role="status"></div>
            <p class="mb-0" style="font-size:0.8rem;">Loading system log feed...</p>
        </div>
    </div>

    <div id="logPaginationContainer" class="text-center mt-3 pt-2 border-top" style="border-color:#f0ecce !important; display:none;">
        <button type="button"
                id="btnShowMoreLogs"
                class="sys-btn-clear px-4 py-2"
                onclick="toggleShowMoreLogs()">
            <i class="bi bi-chevron-down"></i> Show More Logs
        </button>
    </div>
</div>
