<?php if (!defined('APP_LOADED')) exit; ?>

<div id="route" class="revenue-section ro-section">
    <div class="ro-section-head">
        <div>
            <h2>Route Profitability</h2>
            <p>Route-level profit, trips, fares, and hourly patterns for the selected period.</p>
        </div>
    </div>

    <div class="ro-section-error" id="routeError"></div>

    <div class="ro-grid">
        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-cash-coin"></i></span>
                    <span class="ro-card-text">Total Profit per Route</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="routeRevenue"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>

        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-check2-circle"></i></span>
                    <span class="ro-card-text">Completed Trips per Route</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="routeTrips"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>
    </div>

    <div class="ro-grid">
        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-graph-up"></i></span>
                    <span class="ro-card-text">Average Profit per Trip</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="routeAvgTrip"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>

        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-people"></i></span>
                    <span class="ro-card-text">Passengers per Route</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="routePassengers"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>
    </div>

    <div class="ro-grid">
        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-tag"></i></span>
                    <span class="ro-card-text">Average Fare by Route</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="routeAvgFare"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>

        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-clock-history"></i></span>
                    <span class="ro-card-text">Peak Profit per Hour</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="routePeakHour"></canvas>
            </div>
            <div class="ro-empty">No hourly data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>
    </div>

    <div class="ro-grid">
        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-calendar-check"></i></span>
                    <span class="ro-card-text">Highest Profit per Day</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="routeHighestDay"></canvas>
            </div>
            <div class="ro-empty">No daily data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>
    </div>
</div>
