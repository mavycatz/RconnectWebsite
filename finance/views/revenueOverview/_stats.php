<?php if (!defined('APP_LOADED')) exit; ?>

<div class="ro-kpis" id="roKpis">
    <div class="ro-kpi">
        <div class="ro-kpi-head">
            <h6>Total Profit</h6>
            <span class="ro-kpi-icon-chip"><img src="/assets/images/finance/revenueOverview/OrevenueTotal.png" alt="" class="ro-kpi-icon"></span>
        </div>
        <p class="ro-kpi-value" id="kpiRevenue">—</p>
        <div class="ro-kpi-desc">Profit for the selected period</div>
        <div class="ro-kpi-foot">
            <span class="ro-period" data-period>—</span>
            <span class="ro-trend" id="kpiRevenueTrend"></span>
        </div>
    </div>

    <div class="ro-kpi">
        <div class="ro-kpi-head">
            <h6>Average Profit / Trip</h6>
            <span class="ro-kpi-icon-chip"><img src="/assets/images/finance/revenueOverview/OrevenueAvg.png" alt="" class="ro-kpi-icon"></span>
        </div>
        <p class="ro-kpi-value" id="kpiAvgTrip">—</p>
        <div class="ro-kpi-desc">Profit ÷ completed trips</div>
        <div class="ro-kpi-foot">
            <span class="ro-period" data-period>—</span>
            <span class="ro-trend" id="kpiAvgTripTrend"></span>
        </div>
    </div>

    <div class="ro-kpi">
        <div class="ro-kpi-head">
            <h6>Active Drivers</h6>
            <span class="ro-kpi-icon-chip"><img src="/assets/images/finance/revenueOverview/OrevenueBus.png" alt="" class="ro-kpi-icon"></span>
        </div>
        <p class="ro-kpi-value" id="kpiActiveDrivers">—</p>
        <div class="ro-kpi-desc">Drivers with recognized activity</div>
        <div class="ro-kpi-foot">
            <span class="ro-period" data-period>—</span>
            <span class="ro-trend" id="kpiActiveDriversTrend"></span>
        </div>
    </div>

    <div class="ro-kpi">
        <div class="ro-kpi-head">
            <h6>Highest Earning Hour</h6>
            <span class="ro-kpi-icon-chip"><img src="/assets/images/finance/revenueOverview/OrevenuePeak.png" alt="" class="ro-kpi-icon"></span>
        </div>
        <p class="ro-kpi-value ro-kpi-value-sm" id="kpiPeakHour">—</p>
        <div class="ro-kpi-desc">Top earning hour from live records</div>
        <div class="ro-kpi-foot">
            <span class="ro-period" data-period>—</span>
            <span class="ro-trend" id="kpiPeakHourTrend"></span>
        </div>
    </div>
</div>
