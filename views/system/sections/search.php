<div class="sys-panel p-3 mb-3">
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md-4 col-lg-3">
            <div style="position:relative;">
                <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#295738;font-size:0.85rem;"></i>
                <input type="text"
                       id="systemLogSearch"
                       placeholder="Search logs, actions, trips, IP..."
                       oninput="handleSearchInput()"
                       class="sys-filter-input w-100"
                       style="padding-left:32px;">
            </div>
        </div>

        <div class="col-6 col-md-2 col-lg-2">
            <select id="systemLogModule" onchange="filterSystemLogs()" class="sys-filter-select w-100">
                <option value="">All Modules</option>
            </select>
        </div>

        <div class="col-6 col-md-2 col-lg-2">
            <select id="systemLogStatus" onchange="filterSystemLogs()" class="sys-filter-select w-100">
                <option value="">All Levels</option>
                <option value="Success">Success</option>
                <option value="Warning">Warning</option>
                <option value="Error">Error / Failed</option>
                <option value="Monitor">Monitor / Info</option>
            </select>
        </div>

        <div class="col-6 col-md-2 col-lg-2">
            <select id="systemLogUser" onchange="filterSystemLogs()" class="sys-filter-select w-100">
                <option value="">All Users</option>
            </select>
        </div>

        <div class="col-6 col-md-2 col-lg-2">
            <select id="systemLogDate" onchange="filterSystemLogs()" class="sys-filter-select w-100">
                <option value="all">All Time</option>
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="7days">Last 7 Days</option>
                <option value="30days">Last 30 Days</option>
            </select>
        </div>

        <div class="col-12 col-md-auto col-lg-1 d-flex">
            <button type="button" class="sys-btn-clear w-100 justify-content-center" onclick="clearSystemFilters()" title="Reset all filters">
                <i class="bi bi-x-circle"></i> Clear
            </button>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mt-2 pt-2 border-top" style="border-color:#f0ecce !important;">
        <div class="d-flex align-items-center gap-2">
            <span id="sysResultCounter" style="font-size:0.78rem;font-weight:700;color:#295738;">
                Loading event counts...
            </span>
            <span id="activeFilterTags" class="d-inline-flex gap-1 flex-wrap"></span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span style="font-size:0.72rem;color:#7e8f7e;">
                <i class="bi bi-shield-check"></i> Audit log integrity verified
            </span>
        </div>
    </div>
</div>
