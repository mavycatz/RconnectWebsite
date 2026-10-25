<div class="fpa-section fpa-section-main">
    <div class="fpa-section-head">
        <div>
            <div class="fpa-section-title"><i class="bi bi-activity"></i> Passenger Demand Forecast</div>
            <div class="fpa-section-sub" id="fpaForecastRangeLabel">Historical and predicted passenger counts</div>
        </div>
        <div class="fpa-view-bar" id="fpaViewBar" role="group" aria-label="Chart aggregation">
            <button type="button" class="fpa-view-btn active" data-view="daily">Daily</button>
            <button type="button" class="fpa-view-btn" data-view="weekly">Weekly</button>
            <button type="button" class="fpa-view-btn" data-view="monthly">Monthly</button>
        </div>
    </div>
    <div class="fpa-chart-wrap fpa-chart-main">
        <div class="fpa-chart-skeleton"></div>
        <div class="fpa-chart-empty" id="fpaForecastEmpty">No passenger history for the selected filters yet.</div>
        <canvas id="fpaForecastChart" aria-label="Passenger demand forecast chart" role="img"></canvas>
    </div>
    <div class="fpa-legend">
        <span class="fpa-legend-item"><span class="fpa-legend-swatch fpa-legend-history"></span> Historical</span>
        <span class="fpa-legend-item"><span class="fpa-legend-swatch fpa-legend-forecast"></span> Forecast</span>
        <span class="fpa-legend-item"><span class="fpa-legend-swatch fpa-legend-band"></span> Predicted range</span>
    </div>
</div>
