<?php if (!defined('APP_LOADED')) exit; ?>

<div id="bus" class="revenue-section ro-section active">
    <div class="ro-section-head">
        <div>
            <h2>Driver Performance</h2>
            <p>Profit, trips, and averages by driver for the selected period.</p>
        </div>
    </div>

    <div class="ro-section-error" id="busError"></div>

    <div class="ro-grid">
        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-cash-stack"></i></span>
                    <span class="ro-card-text">Profit per Driver</span>
                </span>
                <button type="button" class="ro-link" id="driverViewAll">View All</button>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="revenuePerDriver"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>

        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-graph-up-arrow"></i></span>
                    <span class="ro-card-text">Profit per Trip by Driver</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="revenuePerTripDriver"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>
    </div>

    <div class="ro-grid">
        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-signpost-split"></i></span>
                    <span class="ro-card-text">Trips per Driver</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="tripsPerDriver"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>

        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-calculator"></i></span>
                    <span class="ro-card-text">Average Profit per Driver</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="avgRevenueDriver"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>
    </div>

    <div class="ro-grid">
        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-trophy"></i></span>
                    <span class="ro-card-text">Highest Earning Driver</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="highestDriver"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>

        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-pie-chart"></i></span>
                    <span class="ro-card-text">Contribution per Driver</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="contributionDriver"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>
    </div>
</div>

<div id="driverModal" class="ro-modal">
    <div class="ro-modal-card">
        <button type="button" class="ro-modal-close" id="driverModalClose">&times;</button>
        <h3 class="ro-modal-title">Profit per Driver</h3>
        <div class="ro-modal-body" id="driverModalBody"></div>
    </div>
</div>
