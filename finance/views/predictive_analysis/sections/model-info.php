<div class="fpa-section fpa-section-compact">
    <div class="fpa-section-head">
        <div>
            <div class="fpa-section-title"><i class="bi bi-info-circle"></i> Prediction Model</div>
            <div class="fpa-section-sub">What the forecast is based on</div>
        </div>
    </div>

    <div class="fpa-model-grid">
        <div class="fpa-model-item">
            <div class="fpa-model-label">Model</div>
            <div class="fpa-model-value" id="fpaModelName">&ndash;</div>
        </div>
        <div class="fpa-model-item">
            <div class="fpa-model-label">Historical Records</div>
            <div class="fpa-model-value" id="fpaModelRecords">&ndash;</div>
        </div>
        <div class="fpa-model-item">
            <div class="fpa-model-label">Completed Trips</div>
            <div class="fpa-model-value" id="fpaModelTrips">&ndash;</div>
        </div>
        <div class="fpa-model-item">
            <div class="fpa-model-label">Forecast Period</div>
            <div class="fpa-model-value" id="fpaModelPeriod">&ndash;</div>
        </div>
        <div class="fpa-model-item">
            <div class="fpa-model-label">Forecast Reliability</div>
            <div class="fpa-model-value"><span class="fpa-reliability unavailable" id="fpaModelReliability">&ndash;</span></div>
        </div>
    </div>

    <div class="fpa-model-reason" id="fpaModelReason" style="display:none;"></div>

    <div class="fpa-model-note" id="fpaModelInsufficient" style="display:none;">
        <i class="bi bi-exclamation-circle"></i>
        <span id="fpaModelInsufficientText">Prediction unavailable due to insufficient or inconsistent historical data.</span>
    </div>
</div>
