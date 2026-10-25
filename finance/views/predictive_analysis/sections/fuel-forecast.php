<div class="fpa-section">
    <div class="fpa-section-head">
        <div>
            <div class="fpa-section-title"><i class="bi bi-fuel-pump-fill"></i> Fuel Requirement Forecast</div>
            <div class="fpa-section-sub" id="fpaFuelSub">Estimated fuel needed for the forecast period</div>
            <div class="fpa-section-sub" id="fpaFuelRangeLabel">&nbsp;</div>
        </div>
    </div>

    <div class="fpa-fuel-layout">
        <div class="fpa-fuel-stats" id="fpaFuelStats">
            <div class="fpa-fuel-stat">
                <div class="fpa-fuel-stat-label">Expected Distance</div>
                <div class="fpa-fuel-stat-value" id="fpaFuelDistance">&ndash;</div>
            </div>
            <div class="fpa-fuel-stat">
                <div class="fpa-fuel-stat-label">Estimated KPL</div>
                <div class="fpa-fuel-stat-value" id="fpaFuelKpl">&ndash;</div>
            </div>
            <div class="fpa-fuel-stat fpa-fuel-stat-primary">
                <div class="fpa-fuel-stat-label">Estimated Fuel</div>
                <div class="fpa-fuel-stat-value" id="fpaFuelLiters">&ndash;</div>
            </div>
            <div class="fpa-fuel-stat">
                <div class="fpa-fuel-stat-label">Projected Trips</div>
                <div class="fpa-fuel-stat-value" id="fpaFuelTrips">&ndash;</div>
            </div>
        </div>
        <div class="fpa-chart-wrap fpa-chart-fuel" id="fpaFuelChartWrap">
            <div class="fpa-chart-skeleton"></div>
            <div class="fpa-chart-empty" id="fpaFuelEmpty">Fuel requirements cannot be estimated for the selected filters.</div>
            <canvas id="fpaFuelChart" aria-label="Fuel requirement forecast chart" role="img"></canvas>
        </div>
    </div>
    <div class="fpa-fuel-context" id="fpaFuelContext">&nbsp;</div>
</div>
