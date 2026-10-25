<div class="fpa-filters" id="fpaFilters">
    <div class="fpa-period-bar" id="fpaPeriodBar" role="group" aria-label="Forecast period">
        <button type="button" class="fpa-period-btn active" data-period="7d">7 Days</button>
        <button type="button" class="fpa-period-btn" data-period="30d">30 Days</button>
        <button type="button" class="fpa-period-btn" data-period="90d">90 Days</button>
    </div>

    <div class="fpa-select-wrap">
        <label class="fpa-select-label" for="fpaRoute">Route</label>
        <select class="fpa-select" id="fpaRoute">
            <option value="">All Routes</option>
        </select>
    </div>

    <div class="fpa-select-wrap">
        <label class="fpa-select-label" for="fpaBus">Bus</label>
        <select class="fpa-select" id="fpaBus">
            <option value="">All Buses</option>
        </select>
    </div>

    <div class="fpa-select-wrap">
        <label class="fpa-select-label" for="fpaTimePeriod">Time Period</label>
        <select class="fpa-select" id="fpaTimePeriod">
            <option value="all">All Day</option>
        </select>
    </div>

    <button type="button" class="fpa-refresh" id="fpaRefresh">
        <i class="bi bi-arrow-clockwise"></i> Refresh
    </button>
</div>
