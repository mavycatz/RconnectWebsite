<?php if (!defined('APP_LOADED')) exit; ?>

<div id="station" class="revenue-section ro-section">
    <div class="ro-section-head">
        <div>
            <h2>Location Insights</h2>
            <p>Boarding-point profit and passenger distribution for the selected period.</p>
        </div>
    </div>

    <div class="ro-section-error" id="stationError"></div>

    <div class="ro-grid">
        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-geo-alt"></i></span>
                    <span class="ro-card-text">Profit per Station</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="stationRevenue"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>

        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-people-fill"></i></span>
                    <span class="ro-card-text">Passenger Volume per Station</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="stationPassengers"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>
    </div>

    <div class="ro-grid">
        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-list-ol"></i></span>
                    <span class="ro-card-text">Top Boarding Locations</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="stationTop5"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>

        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-bar-chart-line"></i></span>
                    <span class="ro-card-text">Profit Distribution</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="stationDistribution"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>
    </div>

    <div class="ro-grid">
        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-tag-fill"></i></span>
                    <span class="ro-card-text">Average Fare per Station</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="stationAvgFare"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>
    </div>
</div>
