<?php if (!defined('APP_LOADED')) exit; ?>

<div id="hour" class="revenue-section ro-section">
    <div class="ro-section-head">
        <div>
            <h2>Time-Based Trends</h2>
            <p>Profit across hours, days, weeks, and months for the selected period.</p>
        </div>
    </div>

    <div class="ro-section-error" id="hourError"></div>

    <div class="ro-grid">
        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-clock"></i></span>
                    <span class="ro-card-text">Profit by Hour</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="revenueByHour"></canvas>
            </div>
            <div class="ro-empty">No hourly data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>

        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-calendar-week"></i></span>
                    <span class="ro-card-text">Profit by Day</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="revenueByDay"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>
    </div>

    <div class="ro-grid">
        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-graph-up-arrow"></i></span>
                    <span class="ro-card-text">Weekly Profit Trend</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="weeklyTrend"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>

        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-graph-up"></i></span>
                    <span class="ro-card-text">Monthly Profit Trend</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="monthlyTrend"></canvas>
            </div>
            <div class="ro-empty">No profit data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>
    </div>

    <div class="ro-grid">
        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-sunrise"></i></span>
                    <span class="ro-card-text">Peak Profit Trend</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="peakRevenueTrend"></canvas>
            </div>
            <div class="ro-empty">No hourly data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>

        <div class="ro-card">
            <div class="ro-card-title">
                <span class="ro-card-title-left">
                    <span class="ro-card-icon"><i class="bi bi-star"></i></span>
                    <span class="ro-card-text">Highest Earning Day</span>
                </span>
            </div>
            <div class="ro-chart-wrap">
                <canvas id="highestEarningDay"></canvas>
            </div>
            <div class="ro-empty">No daily data available for this period.</div>
            <div class="ro-loading">Loading&hellip;</div>
        </div>
    </div>

</div>
