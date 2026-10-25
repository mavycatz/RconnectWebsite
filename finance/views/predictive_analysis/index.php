<?php header('Cache-Control: no-store, no-cache, must-revalidate'); header('Pragma: no-cache'); ?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<?php require_once __DIR__ . '/../layout/navbarfinance.php'; ?>
<style>
#mainContent {
    --fpa-dark: #2e4836;
    --fpa-accent: #2f6f4f;
    --fpa-gold: #b3913a;
    --fpa-border: #cdd9b5;
    --fpa-soft: #f9f4da;
    --fpa-skel: linear-gradient(90deg, #eceed9 25%, #f9f4da 50%, #eceed9 75%);
}
.fpa-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 8px 16px;
    margin-bottom: 12px;
}
.fpa-title {
    font-weight: 800;
    font-size: 1.25rem;
    color: #2e4836;
    margin: 0 0 2px;
    letter-spacing: -0.01em;
}
.fpa-sub {
    font-weight: 500;
    font-size: 0.76rem;
    color: #8a9a8a;
    margin: 0;
}
.fpa-last-updated {
    font-size: 0.7rem;
    font-weight: 500;
    color: #8a9a8a;
    white-space: nowrap;
}

.fpa-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 8px 10px;
    background: #fff;
    border: 1px solid #cdd9b5;
    border-radius: 14px;
    padding: 10px 12px;
    margin-bottom: 12px;
}
.fpa-period-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    background: #f9f4da;
    border: 1px solid #cdd9b5;
    border-radius: 10px;
    padding: 4px;
}
.fpa-period-btn {
    border: 1px solid transparent;
    background: transparent;
    color: #2e4836;
    font-weight: 600;
    font-size: 0.76rem;
    padding: 6px 11px;
    border-radius: 8px;
    cursor: pointer;
    transition: 0.2s ease;
    line-height: 1.2;
    font-family: inherit;
}
.fpa-period-btn:hover {
    background: #eceed9;
}
.fpa-period-btn.active {
    background: #2e4836;
    color: #fff;
}
.fpa-select-wrap {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}
.fpa-select-label {
    font-size: 0.62rem;
    font-weight: 700;
    color: #8a9a8a;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.fpa-select {
    border: 1px solid #cdd9b5;
    border-radius: 9px;
    padding: 6px 9px;
    font-size: 0.76rem;
    font-weight: 500;
    color: #2e4836;
    background: #fff;
    min-width: 140px;
    max-width: 210px;
    font-family: inherit;
}
.fpa-select:focus {
    outline: none;
    border-color: #2f6f4f;
    box-shadow: 0 0 0 3px rgba(47, 111, 79, 0.12);
}
.fpa-refresh {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #2e4836;
    color: #fff;
    border: 1px solid #2e4836;
    border-radius: 9px;
    padding: 7px 13px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s ease;
    font-family: inherit;
    margin-left: auto;
}
.fpa-refresh:hover {
    background: #3c6e4f;
}
.fpa-refresh:disabled {
    opacity: 0.6;
    cursor: default;
}

.fpa-loading-banner {
    display: none;
    align-items: center;
    gap: 12px;
    background: #fef8f3;
    border: 1px solid #cdd9b5;
    border-left: 4px solid var(--fpa-dark);
    border-radius: 14px;
    padding: 10px 13px;
    margin-bottom: 10px;
    box-shadow: 0 2px 8px rgba(46, 72, 54, 0.08);
}
.fpa-content.is-loading .fpa-loading-banner {
    display: flex;
}
.fpa-spinner {
    flex: 0 0 22px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 3px solid #cdd9b5;
    border-top-color: var(--fpa-dark);
    animation: fpaSpin 0.8s linear infinite;
}
.fpa-loading-title {
    font-weight: 700;
    font-size: 0.82rem;
    color: var(--fpa-dark);
    line-height: 1.3;
}
.fpa-loading-sub {
    font-weight: 500;
    font-size: 0.72rem;
    color: #5f7b64;
    line-height: 1.35;
}
.fpa-nodata-banner {
    display: none;
    align-items: flex-start;
    gap: 10px;
    background: #fff3cd;
    border: 1px solid #e7d79a;
    border-radius: 14px;
    padding: 10px 13px;
    margin-bottom: 10px;
    color: #8a6d1a;
    font-size: 0.8rem;
    font-weight: 600;
    line-height: 1.5;
}
.fpa-nodata-banner i {
    font-size: 1rem;
    margin-top: 1px;
}
.fpa-error-banner {
    display: none;
    align-items: flex-start;
    gap: 10px;
    background: #fce8e8;
    border: 1px solid #e3b1b1;
    border-radius: 14px;
    padding: 10px 13px;
    margin-bottom: 10px;
    color: #a83232;
    font-size: 0.8rem;
    font-weight: 600;
    line-height: 1.5;
}
.fpa-error-banner i {
    font-size: 1rem;
    margin-top: 1px;
}

.fpa-cards {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 12px;
}
.fpa-card {
    background: #fff;
    border: 1px solid #cdd9b5;
    border-radius: 14px;
    padding: 12px 14px;
    box-shadow: 0 1px 3px rgba(46, 72, 54, 0.05);
    min-width: 0;
}
.fpa-card-peak {
    border-color: #2f6f4f;
    background: linear-gradient(180deg, #ffffff 0%, #f3f8ec 100%);
}
.fpa-card-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}
.fpa-card-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #eceed9;
    color: #2e4836;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.82rem;
    flex-shrink: 0;
}
.fpa-card-peak .fpa-card-icon {
    background: #dcebce;
    color: #0b6b2f;
}
.fpa-card-label {
    font-size: 0.62rem;
    font-weight: 700;
    color: #8a9a8a;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.fpa-card-value {
    font-size: clamp(1.05rem, 1.3vw, 1.4rem);
    font-weight: 800;
    color: #2e4836;
    line-height: 1.2;
    margin-bottom: 4px;
    font-variant-numeric: tabular-nums;
    overflow-wrap: anywhere;
}
.fpa-card-sub {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    font-size: 0.72rem;
    color: #5f7b64;
    font-weight: 500;
}
.fpa-card-note {
    font-size: 0.72rem;
    color: #5f7b64;
    font-weight: 600;
}
.fpa-change {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-weight: 700;
    font-size: 0.76rem;
    padding: 2px 9px;
    border-radius: 50px;
}
.fpa-change.up {
    background: #dcebce;
    color: #0b6b2f;
}
.fpa-change.down {
    background: #fff3cd;
    color: #8a6d1a;
}
.fpa-change.stable {
    background: #eceed9;
    color: #5f7b64;
}
.fpa-pill {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 50px;
    font-size: 0.68rem;
    font-weight: 700;
    white-space: nowrap;
    line-height: 1.4;
}
.fpa-pill.high {
    background: #dcebce;
    color: #0b6b2f;
}
.fpa-pill.medium {
    background: #fff3cd;
    color: #8a6d1a;
}
.fpa-pill.low {
    background: #eceed9;
    color: #5f7b64;
}

.fpa-section {
    background: #fff;
    border: 1px solid #cdd9b5;
    border-radius: 14px;
    padding: 13px 14px;
    margin-bottom: 12px;
    min-width: 0;
}
.fpa-section-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 10px;
}
.fpa-section-title {
    font-weight: 700;
    color: #2e4836;
    font-size: 0.88rem;
}
.fpa-section-title i {
    color: #2f6f4f;
    margin-right: 6px;
}
.fpa-section-sub {
    font-size: 0.7rem;
    color: #8a9a8a;
    font-weight: 500;
    margin-top: 2px;
}
.fpa-view-bar {
    display: flex;
    gap: 4px;
    background: #f9f4da;
    border: 1px solid #cdd9b5;
    border-radius: 9px;
    padding: 3px;
}
.fpa-view-btn {
    border: 1px solid transparent;
    background: transparent;
    color: #2e4836;
    font-weight: 600;
    font-size: 0.72rem;
    padding: 5px 10px;
    border-radius: 7px;
    cursor: pointer;
    font-family: inherit;
    transition: 0.2s ease;
}
.fpa-view-btn:hover {
    background: #eceed9;
}
.fpa-view-btn.active {
    background: #2e4836;
    color: #fff;
}
.fpa-grid-2 {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 12px;
}
.fpa-grid-2 .fpa-section {
    margin-bottom: 12px;
}
.fpa-inline-empty {
    color: #8a9a8a;
    font-weight: 600;
    font-size: 0.78rem;
    padding: 8px 2px;
}

.fpa-chart-wrap {
    position: relative;
    width: 100%;
    min-height: 180px;
}
.fpa-chart-main {
    min-height: 260px;
}
.fpa-chart-fuel {
    min-height: 150px;
    flex: 1 1 280px;
    min-width: 0;
}
.fpa-chart-wrap canvas {
    display: block;
    max-width: 100%;
}
.fpa-chart-skeleton {
    display: none;
    position: absolute;
    inset: 0;
    border-radius: 10px;
    background: var(--fpa-skel);
    background-size: 200% 100%;
    animation: fpaShimmer 1.4s ease-in-out infinite;
}
.fpa-content.is-loading .fpa-chart-skeleton {
    display: block;
}
.fpa-content.is-loading .fpa-chart-wrap canvas {
    visibility: hidden;
}
.fpa-chart-empty {
    display: none;
    position: absolute;
    inset: 0;
    background: rgba(254, 248, 243, 0.9);
    color: #8a9a8a;
    font-weight: 600;
    font-size: 0.85rem;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 0 20px;
    border-radius: 10px;
}
.fpa-legend {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: center;
    margin-top: 6px;
    font-size: 0.68rem;
    color: #5f7b64;
    font-weight: 500;
}
.fpa-legend-swatch {
    display: inline-block;
    width: 18px;
    height: 3px;
    border-radius: 2px;
    margin-right: 6px;
    vertical-align: middle;
}
.fpa-legend-history {
    background: #2f6f4f;
}
.fpa-legend-forecast {
    background: #b3913a;
}
.fpa-legend-band {
    background: rgba(179, 145, 58, 0.3);
    height: 10px;
    border-radius: 3px;
}

.fpa-time-head {
    display: grid;
    grid-template-columns: 76px minmax(0, 1fr) 78px 58px;
    gap: 8px;
    font-size: 0.58rem;
    font-weight: 700;
    color: #a3b3a0;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 5px;
    padding: 0 2px;
}
.fpa-time-head span:nth-child(3),
.fpa-time-head span:nth-child(4) {
    text-align: right;
}
.fpa-time-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.fpa-time-row {
    display: grid;
    grid-template-columns: 76px minmax(0, 1fr) 78px 58px;
    gap: 8px;
    align-items: center;
    min-width: 0;
    padding: 0 2px;
}
.fpa-time-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: #5f7b64;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.fpa-time-track {
    position: relative;
    height: 14px;
    background: #eceed9;
    border-radius: 50px;
    overflow: hidden;
    min-width: 0;
}
.fpa-time-fill {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    border-radius: 50px;
    background: linear-gradient(90deg, #4e8d68, #2f6f4f);
    transition: width 0.4s ease;
}
.fpa-time-row.is-peak .fpa-time-track {
    box-shadow: 0 0 0 2px rgba(179, 145, 58, 0.55);
}
.fpa-time-row.is-peak .fpa-time-fill {
    background: linear-gradient(90deg, #d6b34a, #b3913a);
}
.fpa-time-value {
    font-size: 0.72rem;
    font-weight: 700;
    color: #2e4836;
    text-align: right;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.fpa-time-level {
    font-size: 0.66rem;
    font-weight: 700;
    text-align: right;
    white-space: nowrap;
}
.fpa-time-level.high {
    color: #0b6b2f;
}
.fpa-time-level.medium {
    color: #8a6d1a;
}
.fpa-time-level.low {
    color: #8a9a8a;
}
.fpa-time-level.peak {
    color: #b3913a;
}

.fpa-daily-scroll {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    padding: 4px 2px 6px;
    align-items: flex-end;
    min-height: 140px;
}
.fpa-day-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    flex: 0 0 36px;
    min-width: 0;
}
.fpa-day-value {
    font-size: 0.6rem;
    font-weight: 700;
    color: #2e4836;
    font-variant-numeric: tabular-nums;
}
.fpa-day-track {
    width: 18px;
    height: 76px;
    background: #eceed9;
    border-radius: 7px;
    display: flex;
    align-items: flex-end;
    overflow: hidden;
}
.fpa-day-bar {
    width: 100%;
    background: linear-gradient(180deg, #4e8d68, #2f6f4f);
    border-radius: 7px;
    transition: height 0.3s ease;
}
.fpa-day-col.is-peak .fpa-day-bar {
    background: linear-gradient(180deg, #d6b34a, #b3913a);
}
.fpa-day-col.is-peak .fpa-day-value {
    color: #8a6d1a;
}
.fpa-day-col.is-forecast .fpa-day-bar {
    background: linear-gradient(180deg, #d6b34a, #b3913a);
}
.fpa-day-col.is-forecast .fpa-day-value {
    color: #8a6d1a;
}
.fpa-day-col.is-forecast .fpa-day-label {
    color: #8a6d1a;
    font-weight: 700;
}
.fpa-day-label {
    font-size: 0.56rem;
    font-weight: 600;
    color: #8a9a8a;
    text-align: center;
    line-height: 1.2;
    white-space: nowrap;
}
.fpa-day-label span {
    display: block;
    font-weight: 500;
    color: #a3b3a0;
}
.fpa-daily-scroll.is-dense .fpa-day-col {
    flex: 0 0 30px;
}
.fpa-daily-scroll.is-dense .fpa-day-track {
    width: 16px;
}
.fpa-daily-scroll.is-dense .fpa-day-label span {
    display: none;
}
.fpa-daily-scroll.is-dense .fpa-day-value {
    font-size: 0.55rem;
}

.fpa-reliability {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 11px;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 700;
}
.fpa-reliability.good {
    background: #dcebce;
    color: #0b6b2f;
}
.fpa-reliability.moderate {
    background: #fff3cd;
    color: #8a6d1a;
}
.fpa-reliability.limited {
    background: #fce8e8;
    color: #a83232;
}
.fpa-reliability.unavailable {
    background: #eceed9;
    color: #5f7b64;
}
.fpa-reliability.unknown {
    background: #f0efe0;
    color: #6f6d75;
    font-weight: 600;
}
.fpa-nodata-detail {
    display: block;
    font-weight: 500;
    margin-top: 4px;
    opacity: 0.85;
}

.fpa-fuel-layout {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: stretch;
}
.fpa-fuel-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(120px, 1fr));
    gap: 8px;
    flex: 0 1 340px;
}
.fpa-fuel-stat {
    background: #fbf9ec;
    border: 1px solid #e7e3af;
    border-radius: 11px;
    padding: 9px 11px;
    min-width: 0;
}
.fpa-fuel-stat-label {
    font-size: 0.6rem;
    font-weight: 700;
    color: #8a9a8a;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.fpa-fuel-stat-value {
    font-size: 0.95rem;
    font-weight: 800;
    color: #2e4836;
    margin-top: 2px;
    font-variant-numeric: tabular-nums;
    overflow-wrap: anywhere;
}
.fpa-fuel-stat-primary {
    background: #f3f8ec;
    border-color: #2f6f4f;
}
.fpa-fuel-stat-primary .fpa-fuel-stat-value {
    color: #0b6b2f;
}
.fpa-fuel-context {
    margin-top: 8px;
    font-size: 0.7rem;
    color: #8a9a8a;
    font-weight: 500;
    line-height: 1.5;
}

.fpa-section-compact .fpa-section-head {
    margin-bottom: 9px;
}
.fpa-model-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 8px;
}
.fpa-model-item {
    background: #fbf9ec;
    border: 1px solid #e7e3af;
    border-radius: 11px;
    padding: 8px 10px;
    min-width: 0;
}
.fpa-model-label {
    font-size: 0.58rem;
    font-weight: 700;
    color: #8a9a8a;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.fpa-model-value {
    font-size: 0.76rem;
    font-weight: 600;
    color: #2e4836;
    margin-top: 2px;
    overflow-wrap: anywhere;
    line-height: 1.4;
}
.fpa-model-note {
    display: none;
    align-items: center;
    gap: 8px;
    margin-top: 9px;
    background: #fff3cd;
    color: #8a6d1a;
    border-radius: 11px;
    padding: 9px 11px;
    font-size: 0.76rem;
    font-weight: 600;
    line-height: 1.45;
}
.fpa-model-reason {
    margin-top: 7px;
    font-size: 0.7rem;
    color: #8a9a8a;
    font-weight: 500;
    line-height: 1.45;
}

.fpa-insights {
    display: flex;
    flex-direction: column;
    gap: 7px;
}
.fpa-insight {
    display: flex;
    gap: 9px;
    align-items: flex-start;
    background: #f3f8ec;
    border: 1px solid #cdd9b5;
    border-left: 4px solid #2f6f4f;
    border-radius: 11px;
    padding: 9px 11px;
    color: #3d4940;
    font-size: 0.78rem;
    line-height: 1.45;
    font-weight: 500;
}
.fpa-insight i {
    color: #b3913a;
    font-size: 0.9rem;
    margin-top: 1px;
    flex-shrink: 0;
}

@keyframes fpaSpin {
    to {
        transform: rotate(360deg);
    }
}
@keyframes fpaShimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}
.fpa-content.is-loading .fpa-card-value,
.fpa-content.is-loading .fpa-fuel-stat-value,
.fpa-content.is-loading .fpa-model-value {
    color: transparent;
    background: var(--fpa-skel);
    background-size: 200% 100%;
    border-radius: 6px;
    animation: fpaShimmer 1.4s ease-in-out infinite;
}
.fpa-content.is-loading .fpa-time-list,
.fpa-content.is-loading .fpa-daily-scroll,
.fpa-content.is-loading .fpa-insights {
    opacity: 0.5;
}
@keyframes fpaReveal {
    from {
        opacity: 0.4;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.fpa-content.fpa-loaded {
    animation: fpaReveal 0.2s ease-out;
}

@media (prefers-reduced-motion: reduce) {
    .fpa-spinner,
    .fpa-chart-skeleton,
    .fpa-content.is-loading .fpa-card-value,
    .fpa-content.is-loading .fpa-fuel-stat-value,
    .fpa-content.is-loading .fpa-model-value {
        animation: none;
    }
    .fpa-content.fpa-loaded {
        animation: none;
    }
}

@media (max-width: 1100px) {
    .fpa-cards {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .fpa-grid-2 {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 760px) {
    .fpa-cards {
        grid-template-columns: 1fr;
    }
    .fpa-time-head,
    .fpa-time-row {
        grid-template-columns: 64px minmax(0, 1fr) 62px 48px;
        gap: 6px;
    }
    .fpa-time-label,
    .fpa-time-value {
        font-size: 0.66rem;
    }
    .fpa-time-level {
        font-size: 0.6rem;
    }
    .fpa-day-col {
        flex-basis: 32px;
    }
    .fpa-day-track {
        width: 16px;
        height: 64px;
    }
    .fpa-fuel-stats {
        grid-template-columns: 1fr 1fr;
        flex-basis: 100%;
    }
    .fpa-select {
        min-width: 120px;
        max-width: 100%;
    }
    .fpa-refresh {
        margin-left: 0;
    }
}
</style>
<div class="main-content" id="mainContent" style="font-family:'Poppins', sans-serif; background:#eceed9; padding:20px;">

    <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;min-width:0;">

        <?php require_once __DIR__ . '/../layout/_headernav.php'; ?>

        <div class="fpa-header">
            <div>
                <h1 class="fpa-title">Predictive Analysis</h1>
                <p class="fpa-sub">Forecast passenger demand, peak demand periods, and fuel requirements from recorded R-Connect data.</p>
            </div>
            <span class="fpa-last-updated" id="fpaLastUpdated">Last updated: &mdash;</span>
        </div>

        <div class="fpa-content" id="fpaContent">
            <div class="fpa-loading-banner" role="status" aria-live="polite">
                <div class="fpa-spinner" aria-hidden="true"></div>
                <div>
                    <div class="fpa-loading-title">Computing predictions&hellip;</div>
                    <div class="fpa-loading-sub">Aggregating historical trips, passengers, and fuel records.</div>
                </div>
            </div>

            <div class="fpa-nodata-banner" id="fpaNoDataBanner">
                <i class="bi bi-exclamation-circle"></i>
                <span>
                    <span id="fpaNoDataText">Prediction unavailable due to insufficient or inconsistent historical data.</span>
                    <span class="fpa-nodata-detail" id="fpaNoDataDetail" style="display:none;"></span>
                </span>
            </div>

            <div class="fpa-error-banner" id="fpaErrorBanner">
                <i class="bi bi-x-circle"></i>
                <span id="fpaErrorText">Unable to compute predictions right now.</span>
            </div>

            <?php require __DIR__ . '/sections/filters.php'; ?>
            <?php require __DIR__ . '/sections/summary-cards.php'; ?>
            <?php require __DIR__ . '/sections/passenger-forecast.php'; ?>

            <div class="fpa-grid-2">
                <?php require __DIR__ . '/sections/demand-by-time.php'; ?>
                <?php require __DIR__ . '/sections/fuel-cost-forecast.php'; ?>
            </div>

            <?php require __DIR__ . '/sections/fuel-forecast.php'; ?>
            <?php require __DIR__ . '/sections/model-info.php'; ?>
            <?php require __DIR__ . '/sections/insights.php'; ?>
        </div>

    </div>
</div>

<script>
(function () {
    "use strict";

    var API = "/finance/api/analytics/predictive_analysis.php";
    var state = {
        period: "7d",
        route: "",
        bus: "",
        time_period: "all",
        view: "daily",
        fuelCostView: "day",
    };
    var charts = {};
    var lastPoints = [];
    var lastFuelCost = null;
    var lastIncompleteDates = [];
    var loadReqId = 0;
    var chartPoppins = "'Poppins', sans-serif";
    var tickColor = "#8a9a8a";
    var gridColor = "rgba(46,72,54,0.07)";

    function $(id) {
        return document.getElementById(id);
    }
    function dash() {
        return '<span style="color:#a3b3a0;">\u2013</span>';
    }
    function esc(s) {
        var d = document.createElement("div");
        d.appendChild(document.createTextNode(String(s == null ? "" : s)));
        return d.innerHTML;
    }
    function fmtInt(v) {
        return Number(v || 0).toLocaleString("en-PH");
    }
    function fmtMoney(v) {
        return (
            "\u20b1" +
            Number(v || 0).toLocaleString("en-PH", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })
        );
    }
    function shortDate(iso) {
        var s = String(iso || "").slice(0, 10);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
        var parts = s.split("-");
        var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return d.toLocaleDateString("en-US", { month: "short", day: "numeric" });
    }
    function longDate(iso) {
        var s = String(iso || "").slice(0, 10);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
        var parts = s.split("-");
        var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return d.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
    }
    function monthShort(iso) {
        var s = String(iso || "").slice(0, 10);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) return "";
        var parts = s.split("-");
        var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return d.toLocaleDateString("en-US", { month: "short" });
    }
    function compactRange(startIso, endIso) {
        var start = String(startIso || "").slice(0, 10);
        var end = String(endIso || "").slice(0, 10);
        var hasStart = /^\d{4}-\d{2}-\d{2}$/.test(start);
        var hasEnd = /^\d{4}-\d{2}-\d{2}$/.test(end);
        if (!hasStart) return hasEnd ? longDate(end) : "";
        if (!hasEnd || start === end) return longDate(start);
        var parts = start.split("-");
        var startDate = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        parts = end.split("-");
        var endDate = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        if (startDate.getFullYear() === endDate.getFullYear()) {
            if (startDate.getMonth() === endDate.getMonth()) {
                return (
                    monthShort(start) +
                    " " +
                    startDate.getDate() +
                    "\u2013" +
                    endDate.getDate() +
                    ", " +
                    endDate.getFullYear()
                );
            }
            return (
                monthShort(start) +
                " " +
                startDate.getDate() +
                " \u2013 " +
                monthShort(end) +
                " " +
                endDate.getDate() +
                ", " +
                endDate.getFullYear()
            );
        }
        return longDate(start) + " \u2013 " + longDate(end);
    }
    function monthLabel(key) {
        var parts = String(key || "").split("-");
        if (parts.length < 2) return key;
        var d = new Date(Number(parts[0]), Number(parts[1]) - 1, 1);
        return d.toLocaleDateString("en-US", { month: "short", year: "numeric" });
    }
    function isoWeekStart(iso) {
        var parts = String(iso).slice(0, 10).split("-");
        var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        var day = (d.getDay() + 6) % 7;
        d.setDate(d.getDate() - day);
        var m = String(d.getMonth() + 1);
        var dd = String(d.getDate());
        return (
            d.getFullYear() +
            "-" +
            (m.length < 2 ? "0" + m : m) +
            "-" +
            (dd.length < 2 ? "0" + dd : dd)
        );
    }
    function destroyChart(id) {
        if (charts[id]) {
            try {
                charts[id].destroy();
            } catch (e) {}
            charts[id] = null;
        }
    }
    function toggleEmpty(id, hasData) {
        var el = $(id);
        if (el) el.style.display = hasData ? "none" : "flex";
    }

    function setLoading(on) {
        var content = $("fpaContent");
        if (!content) return;
        if (on) {
            content.classList.add("is-loading");
            content.classList.remove("fpa-loaded");
            content.setAttribute("aria-busy", "true");
        } else {
            content.classList.remove("is-loading");
            content.removeAttribute("aria-busy");
        }
    }
    function reveal() {
        var content = $("fpaContent");
        if (!content) return;
        content.classList.remove("fpa-loaded");
        void content.offsetWidth;
        content.classList.add("fpa-loaded");
    }
    function showError(message) {
        $("fpaErrorText").textContent = message || "Unable to compute predictions right now.";
        $("fpaErrorBanner").style.display = "flex";
        $("fpaNoDataBanner").style.display = "none";
        clearPredictions();
        var content = $("fpaContent");
        if (content) content.classList.remove("is-loading");
    }
    function hideError() {
        $("fpaErrorBanner").style.display = "none";
    }
    function showNoData(message, detail) {
        $("fpaNoDataText").textContent =
            message ||
            "Prediction unavailable due to insufficient or inconsistent historical data.";
        var detailEl = $("fpaNoDataDetail");
        if (detailEl) {
            detailEl.textContent = detail || "";
            detailEl.style.display = detail ? "block" : "none";
        }
        $("fpaNoDataBanner").style.display = "flex";
        $("fpaErrorBanner").style.display = "none";
    }
    function hideNoData() {
        $("fpaNoDataBanner").style.display = "none";
    }

    function clearPredictions() {
        ["fpaCardPassengersValue", "fpaCardPeakValue", "fpaCardFuelValue"].forEach(function (id) {
            if ($(id)) $(id).innerHTML = dash();
        });
        ["fpaCardPassengersChange", "fpaCardFuelChange", "fpaCardPeakLevel"].forEach(function (id) {
            if ($(id)) $(id).style.display = "none";
        });
        ["fpaCardPassengersPeriod", "fpaCardFuelPeriod", "fpaCardPeakVolume"].forEach(
            function (id) {
                if ($(id)) $(id).innerHTML = "&nbsp;";
            },
        );
        ["fpaFuelDistance", "fpaFuelKpl", "fpaFuelLiters", "fpaFuelTrips"].forEach(function (id) {
            if ($(id)) $(id).innerHTML = dash();
        });
        if ($("fpaTimeList"))
            $("fpaTimeList").innerHTML =
                '<div class="fpa-inline-empty">No demand profile available.</div>';
        lastIncompleteDates = [];
        if ($("fpaFuelCostScroll")) {
            $("fpaFuelCostScroll").classList.remove("is-dense");
            $("fpaFuelCostScroll").innerHTML =
                '<div class="fpa-inline-empty">No fuel cost forecast available.</div>';
        }
        if ($("fpaFuelCostSub"))
            $("fpaFuelCostSub").textContent = "Projected fuel expenses for the forecast horizon";
        if ($("fpaFuelCostContext")) $("fpaFuelCostContext").innerHTML = "&nbsp;";
        lastFuelCost = null;
        if ($("fpaInsights"))
            $("fpaInsights").innerHTML =
                '<div class="fpa-inline-empty">No insights available.</div>';
        if ($("fpaModelRecords")) $("fpaModelRecords").innerHTML = dash();
        if ($("fpaModelTrips")) $("fpaModelTrips").innerHTML = dash();
        if ($("fpaModelPeriod")) $("fpaModelPeriod").innerHTML = dash();
        if ($("fpaModelReliability")) {
            $("fpaModelReliability").className = "fpa-reliability unavailable";
            $("fpaModelReliability").textContent = "\u2013";
        }
        if ($("fpaModelReason")) $("fpaModelReason").style.display = "none";
        ["fpaForecastChart", "fpaFuelChart"].forEach(destroyChart);
        toggleEmpty("fpaForecastEmpty", false);
        toggleEmpty("fpaFuelEmpty", false);
    }

    function fillSelect(el, items, current, allLabel) {
        if (!el) return;
        var html = '<option value="">' + esc(allLabel) + "</option>";
        (items || []).forEach(function (item) {
            var value = typeof item === "string" ? item : item.value;
            var label = typeof item === "string" ? item : item.label;
            html += '<option value="' + esc(value) + '">' + esc(label) + "</option>";
        });
        el.innerHTML = html;
        el.value = current || "";
        if (el.value !== (current || "")) el.value = "";
    }

    function applyFilters(filters) {
        if (!filters) return;
        state.period = filters.period || state.period;
        state.route = filters.route || "";
        state.bus = filters.bus || "";
        state.time_period = filters.time_period || "all";

        document.querySelectorAll(".fpa-period-btn").forEach(function (btn) {
            btn.classList.toggle("active", btn.getAttribute("data-period") === state.period);
        });

        fillSelect($("fpaRoute"), filters.routes, state.route, "All Routes");
        fillSelect($("fpaBus"), filters.buses, state.bus, "All Buses");

        var timeSel = $("fpaTimePeriod");
        if (timeSel) {
            var html = "";
            (filters.time_periods || [{ value: "all", label: "All Day" }]).forEach(function (item) {
                html += '<option value="' + esc(item.value) + '">' + esc(item.label) + "</option>";
            });
            timeSel.innerHTML = html;
            timeSel.value = state.time_period;
        }
    }

    function renderSummary(summary) {
        summary = summary || {};
        var pax = summary.passengers || {};
        if (pax.range_low != null && pax.range_high != null) {
            $("fpaCardPassengersValue").textContent =
                fmtInt(pax.range_low) + "\u2013" + fmtInt(pax.range_high);
        } else {
            $("fpaCardPassengersValue").innerHTML = dash();
        }
        var change = $("fpaCardPassengersChange");
        if (change) {
            if (pax.has_change && pax.change_pct != null) {
                change.style.display = "";
                change.className = "fpa-change " + (pax.change_direction || "stable");
                change.textContent =
                    (pax.change_pct >= 0 ? "\u2191 " : "\u2193 ") + Math.abs(pax.change_pct) + "%";
                change.title = "Versus the previous period";
            } else {
                change.style.display = "none";
            }
        }
        $("fpaCardPassengersPeriod").textContent = pax.period_label || "";

        var peak = summary.peak;
        if (peak) {
            $("fpaCardPeakValue").textContent = peak.label || "\u2013";
            $("fpaCardPeakVolume").textContent =
                peak.avg_per_trip != null
                    ? "~" + peak.avg_per_trip + " passengers/trip"
                    : peak.avg_daily != null
                      ? "~" + fmtInt(peak.avg_daily) + " passengers/day"
                      : "";
            var pill = $("fpaCardPeakLevel");
            pill.style.display = "";
            pill.className = "fpa-pill " + String(peak.level || "Low").toLowerCase();
            pill.textContent = peak.level || "";
        } else {
            $("fpaCardPeakValue").innerHTML = dash();
            $("fpaCardPeakVolume").innerHTML = "&nbsp;";
            $("fpaCardPeakLevel").style.display = "none";
        }

        var fuel = summary.fuel || {};
        if (fuel.available && fuel.liters != null) {
            $("fpaCardFuelValue").textContent = "~" + fmtInt(fuel.liters) + " L";
        } else {
            $("fpaCardFuelValue").innerHTML = dash();
        }
        var fuelChange = $("fpaCardFuelChange");
        if (fuelChange) {
            if (fuel.has_change && fuel.change_pct != null) {
                fuelChange.style.display = "";
                fuelChange.className = "fpa-change " + (fuel.change_direction || "stable");
                fuelChange.textContent =
                    (fuel.change_pct >= 0 ? "\u2191 " : "\u2193 ") +
                    Math.abs(fuel.change_pct) +
                    "%";
                fuelChange.title = "Versus the previous period";
            } else {
                fuelChange.style.display = "none";
            }
        }
        $("fpaCardFuelPeriod").textContent = fuel.period_label || "";
    }

    function isIncompleteDate(date) {
        return lastIncompleteDates.indexOf(String(date).slice(0, 10)) !== -1;
    }

    function aggregate(points, view) {
        if (view === "daily") {
            var labels = [],
                hist = [],
                fc = [],
                lower = [],
                upper = [],
                lastHist = -1,
                bridge = -1;
            points.forEach(function (p, i) {
                labels.push(shortDate(p.date));
                if (p.type === "historical") {
                    hist.push(p.value);
                    fc.push(null);
                    lastHist = i;
                    if (!isIncompleteDate(p.date)) {
                        bridge = i;
                    }
                } else {
                    hist.push(null);
                    fc.push(p.value);
                }
                lower.push(p.type === "forecast" ? p.lower : null);
                upper.push(p.type === "forecast" ? p.upper : null);
            });
            var bridgeIndex = bridge >= 0 ? bridge : lastHist;
            if (bridgeIndex >= 0) {
                fc[bridgeIndex] = hist[bridgeIndex];
                lower[bridgeIndex] = null;
                upper[bridgeIndex] = null;
            }
            return { labels: labels, hist: hist, fc: fc, lower: lower, upper: upper };
        }

        var order = [],
            labelMap = {},
            histMap = {},
            fcMap = {},
            lowMap = {},
            upMap = {},
            lastHistKey = null;
        points.forEach(function (p) {
            if (isIncompleteDate(p.date)) {
                return;
            }
            var key = view === "weekly" ? isoWeekStart(p.date) : String(p.date).slice(0, 7);
            if (order.indexOf(key) === -1) {
                order.push(key);
                labelMap[key] = view === "weekly" ? "Wk " + shortDate(key) : monthLabel(key);
            }
            if (p.type === "historical") {
                histMap[key] = (histMap[key] || 0) + p.value;
                lastHistKey = key;
            } else {
                fcMap[key] = (fcMap[key] || 0) + p.value;
                lowMap[key] = (lowMap[key] || 0) + p.lower;
                upMap[key] = (upMap[key] || 0) + p.upper;
            }
        });

        var hist = [],
            fc = [],
            lower = [],
            upper = [];
        order.forEach(function (key) {
            hist.push(Object.prototype.hasOwnProperty.call(histMap, key) ? histMap[key] : null);
            if (Object.prototype.hasOwnProperty.call(fcMap, key)) {
                fc.push(fcMap[key]);
                lower.push(lowMap[key]);
                upper.push(upMap[key]);
            } else if (key === lastHistKey) {
                fc.push(histMap[key]);
                lower.push(null);
                upper.push(null);
            } else {
                fc.push(null);
                lower.push(null);
                upper.push(null);
            }
        });
        return {
            labels: order.map(function (k) {
                return labelMap[k];
            }),
            hist: hist,
            fc: fc,
            lower: lower,
            upper: upper,
        };
    }

    function renderForecast(forecast, model) {
        forecast = forecast || {};
        var points = [];
        (forecast.history || []).forEach(function (h) {
            points.push({
                date: h.date,
                value: Number(h.passengers) || 0,
                type: "historical",
                trips: Number(h.trips) || 0,
            });
        });
        (forecast.points || []).forEach(function (p) {
            points.push({
                date: p.date,
                value: Number(p.value) || 0,
                lower: p.lower != null ? Number(p.lower) : null,
                upper: p.upper != null ? Number(p.upper) : null,
                type: "forecast",
            });
        });

        lastPoints = points;
        lastIncompleteDates = (forecast.incomplete_dates || []).slice();

        var history = forecast.history || [];
        var label = $("fpaForecastRangeLabel");
        if (label && history.length) {
            var days = Number(model && model.forecast_period ? model.forecast_period.days : 0);
            label.textContent =
                "History " +
                compactRange(history[0].date, history[history.length - 1].date) +
                " \u00b7 Forecast " +
                compactRange(forecast.forecast_start, forecast.forecast_end) +
                (days > 0 ? " (" + days + " days)" : "");
        }

        if (!points.length) {
            destroyChart("fpaForecastChart");
            toggleEmpty("fpaForecastEmpty", false);
            return;
        }
        toggleEmpty("fpaForecastEmpty", true);
        drawForecastChart(points);
    }

    var forecastStartPlugin = {
        id: "fpaForecastStart",
        afterDatasetsDraw: function (chart) {
            var forecastData = chart.data.datasets[3] ? chart.data.datasets[3].data : null;
            var meta = chart.getDatasetMeta(3);
            if (!forecastData || !meta || !meta.data) return;
            var startIndex = -1;
            for (var i = 0; i < forecastData.length; i++) {
                if (forecastData[i] != null) {
                    startIndex = i;
                    break;
                }
            }
            if (startIndex < 0 || !meta.data[startIndex]) return;
            var area = chart.chartArea;
            var x = meta.data[startIndex].x;
            if (typeof x !== "number" || isNaN(x)) return;

            var ctx = chart.ctx;
            ctx.save();
            ctx.beginPath();
            ctx.setLineDash([5, 4]);
            ctx.strokeStyle = "rgba(179,145,58,0.7)";
            ctx.lineWidth = 1.5;
            ctx.moveTo(x, area.top);
            ctx.lineTo(x, area.bottom);
            ctx.stroke();
            ctx.setLineDash([]);
            ctx.fillStyle = "#8a6d1a";
            ctx.font = "600 10px 'Poppins', sans-serif";
            ctx.textAlign = "left";
            ctx.fillText("Forecast", Math.min(x + 5, area.right - 50), area.top + 10);
            ctx.restore();
        },
    };

    function drawForecastChart(points) {
        destroyChart("fpaForecastChart");
        var canvas = $("fpaForecastChart");
        if (!canvas || typeof Chart === "undefined") return;

        var agg = aggregate(points, state.view);

        charts.fpaForecastChart = new Chart(canvas, {
            type: "line",
            plugins: [forecastStartPlugin],
            data: {
                labels: agg.labels,
                datasets: [
                    {
                        label: "Range upper",
                        data: agg.upper,
                        borderWidth: 0,
                        borderColor: "rgba(179,145,58,0)",
                        backgroundColor: "rgba(179,145,58,0.10)",
                        pointRadius: 0,
                        pointHitRadius: 0,
                        fill: "+1",
                        tension: 0.35,
                        order: 3,
                    },
                    {
                        label: "Range lower",
                        data: agg.lower,
                        borderWidth: 0,
                        borderColor: "rgba(179,145,58,0)",
                        backgroundColor: "rgba(179,145,58,0.10)",
                        pointRadius: 0,
                        pointHitRadius: 0,
                        fill: false,
                        tension: 0.35,
                        order: 3,
                    },
                    {
                        label: "Historical",
                        data: agg.hist,
                        borderColor: "#2f6f4f",
                        backgroundColor: "rgba(47,111,79,0.14)",
                        borderWidth: 2.5,
                        tension: 0.35,
                        pointRadius: 1.5,
                        pointHoverRadius: 5,
                        pointHitRadius: 10,
                        pointBackgroundColor: "#2f6f4f",
                        fill: true,
                        order: 1,
                    },
                    {
                        label: "Forecast",
                        data: agg.fc,
                        borderColor: "#b3913a",
                        backgroundColor: "rgba(179,145,58,0.08)",
                        borderWidth: 2.5,
                        borderDash: [6, 4],
                        tension: 0.35,
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        pointHitRadius: 10,
                        pointBackgroundColor: "#b3913a",
                        fill: false,
                        spanGaps: true,
                        order: 0,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: "index", intersect: false },
                layout: { padding: { top: 8, right: 8, bottom: 4, left: 4 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: "rgba(46,72,54,0.92)",
                        padding: 10,
                        cornerRadius: 8,
                        filter: function (item) {
                            return item.datasetIndex >= 2;
                        },
                        callbacks: {
                            label: function (ctx) {
                                var value = ctx.parsed.y;
                                if (value == null) return null;
                                var text =
                                    ctx.dataset.label +
                                    ": " +
                                    Number(value).toLocaleString("en-PH") +
                                    " passengers";
                                if (ctx.dataset.label === "Forecast") {
                                    var upper = ctx.chart.data.datasets[0].data[ctx.dataIndex];
                                    var lower = ctx.chart.data.datasets[1].data[ctx.dataIndex];
                                    if (lower != null && upper != null) {
                                        text +=
                                            " (range " +
                                            Number(lower).toLocaleString("en-PH") +
                                            "\u2013" +
                                            Number(upper).toLocaleString("en-PH") +
                                            ")";
                                    }
                                }
                                return text;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: tickColor,
                            font: { family: chartPoppins, size: 11 },
                            maxTicksLimit: 12,
                            maxRotation: 0,
                        },
                        border: { color: "#cdd9b5" },
                    },
                    y: {
                        beginAtZero: false,
                        grace: "6%",
                        grid: { color: gridColor },
                        ticks: {
                            color: tickColor,
                            font: { family: chartPoppins, size: 11 },
                            maxTicksLimit: 5,
                            precision: 0,
                        },
                        border: { display: false },
                    },
                },
            },
        });
    }

    function renderTime(buckets) {
        var list = $("fpaTimeList");
        if (!list) return;
        var hasData = (buckets || []).some(function (b) {
            return Number(b.passengers) > 0;
        });
        if (!hasData) {
            list.innerHTML =
                '<div class="fpa-inline-empty">No demand recorded for the selected filters.</div>';
            return;
        }
        var max = 1;
        (buckets || []).forEach(function (b) {
            max = Math.max(max, Number(b.avg_daily) || 0);
        });

        list.innerHTML = buckets
            .map(function (b) {
                var pct = Math.max(4, Math.round(((Number(b.avg_daily) || 0) / max) * 100));
                var level = String(b.level || "Low").toLowerCase();
                var levelText = b.is_peak ? "Peak" : b.level || "Low";
                var levelClass = b.is_peak ? "peak" : level;
                var title =
                    b.label +
                    ": " +
                    fmtInt(b.avg_daily) +
                    " passengers/day" +
                    (b.avg_per_trip != null ? " (" + b.avg_per_trip + "/trip)" : "");
                return (
                    '<div class="fpa-time-row' +
                    (b.is_peak ? " is-peak" : "") +
                    '" title="' +
                    esc(title) +
                    '">' +
                    '<div class="fpa-time-label">' +
                    esc(b.label) +
                    "</div>" +
                    '<div class="fpa-time-track"><div class="fpa-time-fill" style="width:' +
                    pct +
                    '%"></div></div>' +
                    '<div class="fpa-time-value">' +
                    fmtInt(b.avg_daily) +
                    "</div>" +
                    '<div class="fpa-time-level ' +
                    levelClass +
                    '">' +
                    esc(levelText) +
                    "</div>" +
                    "</div>"
                );
            })
            .join("");
    }

    function fmtMoneyShort(v) {
        var n = Number(v || 0);
        if (Math.abs(n) >= 1000000) return "\u20b1" + (n / 1000000).toFixed(1) + "M";
        if (Math.abs(n) >= 1000) return "\u20b1" + (n / 1000).toFixed(1) + "k";
        return "\u20b1" + n.toFixed(0);
    }

    function groupCostPoints(rows, groupBy) {
        if (groupBy === "day") {
            return rows.map(function (r) {
                var d = new Date(String(r.date) + "T00:00:00");
                return {
                    label: d.toLocaleDateString("en-US", { weekday: "short" }),
                    sub: d.toLocaleDateString("en-US", { month: "short", day: "numeric" }),
                    full: d.toLocaleDateString("en-US", {
                        weekday: "short",
                        month: "short",
                        day: "numeric",
                    }),
                    dayNum: String(d.getDate()),
                    value: Number(r.cost) || 0,
                    liters: Number(r.liters) || 0,
                    trips: Number(r.trips) || 0,
                    forecast: false,
                };
            });
        }

        var order = [];
        var grouped = {};
        rows.forEach(function (r) {
            var d = new Date(String(r.date) + "T00:00:00");
            var key, label, sub;
            if (groupBy === "week") {
                var monday = new Date(d);
                monday.setDate(d.getDate() - ((d.getDay() + 6) % 7));
                key =
                    monday.getFullYear() +
                    "-" +
                    String(monday.getMonth() + 1).padStart(2, "0") +
                    "-" +
                    String(monday.getDate()).padStart(2, "0");
                label = "Wk";
                sub = monday.toLocaleDateString("en-US", { month: "short", day: "numeric" });
            } else {
                key = String(r.date).slice(0, 7);
                label = d.toLocaleDateString("en-US", { month: "short" });
                sub = String(d.getFullYear());
            }
            if (!grouped[key]) {
                grouped[key] = {
                    label: label,
                    sub: sub,
                    full: groupBy === "week" ? "Week of " + sub : label + " " + sub,
                    dayNum: sub,
                    value: 0,
                    liters: 0,
                    trips: 0,
                    forecast: false,
                };
                order.push(key);
            }
            grouped[key].value += Number(r.cost) || 0;
            grouped[key].liters += Number(r.liters) || 0;
            grouped[key].trips += Number(r.trips) || 0;
        });
        return order.map(function (key) {
            return grouped[key];
        });
    }

    function renderFuelCost(fuel, model) {
        fuel = fuel || {};
        var wrap = $("fpaFuelCostScroll");
        if (!wrap) return;

        var rows = (fuel.daily_cost || []).filter(function (r) {
            return String((r && r.date) || "").slice(0, 10) !== "";
        });
        var horizonDays =
            Number(model && model.forecast_period ? model.forecast_period.days : 0) ||
            Number(fuel.period_days) ||
            0;
        var estimatedCost = Number(fuel.estimated_cost);
        var hasForecast =
            !!fuel.cost_available && isFinite(estimatedCost) && estimatedCost > 0 && horizonDays > 0;

        var sub = $("fpaFuelCostSub");
        var context = $("fpaFuelCostContext");

        if (!rows.length && !hasForecast) {
            wrap.classList.remove("is-dense");
            wrap.innerHTML =
                '<div class="fpa-inline-empty">' +
                esc(
                    fuel.message ||
                        "Fuel cost forecast unavailable until fuel, distance, and diesel price records exist for this scope.",
                ) +
                "</div>";
            if (sub) sub.textContent = "Projected fuel expenses for the forecast horizon";
            if (context) context.innerHTML = "&nbsp;";
            lastFuelCost = null;
            return;
        }
        lastFuelCost = { fuel: fuel, model: model };

        var groupBy = state.fuelCostView || "day";
        var groups = groupCostPoints(rows, groupBy);
        var perUnit = null;
        if (hasForecast) {
            var unitDays = groupBy === "week" ? 7 : groupBy === "month" ? 30 : 1;
            perUnit = (estimatedCost / horizonDays) * unitDays;
            groups.push({
                label: groupBy === "week" ? "Next wk" : groupBy === "month" ? "Next mo" : "Next day",
                sub: "Forecast",
                full:
                    "Projected cost per " +
                    (groupBy === "week" ? "week" : groupBy === "month" ? "month" : "day") +
                    " over the next " +
                    horizonDays +
                    " days",
                dayNum: "FC",
                value: perUnit,
                liters: 0,
                trips: 0,
                forecast: true,
            });
        }

        var historyTotal = 0;
        groups.forEach(function (g) {
            if (!g.forecast) historyTotal += g.value;
        });

        if (sub) {
            var pieces = [];
            pieces.push(
                hasForecast
                    ? "Projected " +
                          fmtMoney(estimatedCost) +
                          " for the next " +
                          horizonDays +
                          " days"
                    : "No projected cost for this horizon",
            );
            if (perUnit != null) {
                pieces.push(
                    "~" +
                        fmtMoney(perUnit) +
                        " per " +
                        (groupBy === "week" ? "week" : groupBy === "month" ? "month" : "day"),
                );
            }
            if (historyTotal > 0) {
                pieces.push(fmtMoney(historyTotal) + " recorded in the history window");
            }
            sub.textContent = pieces.join(" \u00b7 ");
        }
        if (context) {
            var bits = [];
            if (Number(fuel.avg_price) > 0) {
                bits.push("Diesel " + fmtMoney(fuel.avg_price) + "/L (recorded period price)");
            }
            if (fuel.cost_change_pct != null && Number(fuel.previous_cost) > 0) {
                bits.push(
                    (Number(fuel.cost_change_pct) >= 0 ? "\u2191 " : "\u2193 ") +
                        Math.abs(Number(fuel.cost_change_pct)) +
                        "% vs previous period (" +
                        fmtMoney(fuel.previous_cost) +
                        ")",
                );
            } else if (Number(fuel.previous_cost) > 0) {
                bits.push("Previous period " + fmtMoney(fuel.previous_cost));
            } else {
                bits.push("No comparable previous period recorded");
            }
            context.innerHTML = bits.join(" \u00b7 ");
        }

        var dense = groupBy === "day" && groups.length > 21;
        wrap.classList.toggle("is-dense", dense);

        var max = 1;
        groups.forEach(function (g) {
            max = Math.max(max, g.value);
        });

        wrap.innerHTML = groups
            .map(function (g) {
                var heightPct = Math.max(6, Math.round((g.value / max) * 100));
                var labelText = dense ? g.dayNum : g.label;
                var subText = dense ? "" : g.sub;
                var title =
                    (g.forecast ? g.full : g.label + (g.sub ? " " + g.sub : "")) +
                    ": " +
                    fmtMoney(g.value) +
                    (g.forecast
                        ? " (projected average)"
                        : g.liters > 0
                          ? " \u00b7 " +
                            Number(g.liters).toLocaleString("en-PH", {
                                maximumFractionDigits: 1,
                            }) +
                            " L \u00b7 " +
                            fmtInt(g.trips) +
                            " trips"
                          : "");

                return (
                    '<div class="fpa-day-col' +
                    (g.forecast ? " is-forecast" : "") +
                    '" title="' +
                    esc(title) +
                    '">' +
                    '<div class="fpa-day-value">' +
                    fmtMoneyShort(g.value) +
                    "</div>" +
                    '<div class="fpa-day-track"><div class="fpa-day-bar" style="height:' +
                    heightPct +
                    '%"></div></div>' +
                    '<div class="fpa-day-label">' +
                    esc(labelText) +
                    "<span>" +
                    esc(subText) +
                    "</span></div>" +
                    "</div>"
                );
            })
            .join("");
    }

    function renderFuel(fuel, forecast, model) {
        fuel = fuel || {};
        var chartWrap = $("fpaFuelChartWrap");
        if (!fuel.available) {
            ["fpaFuelDistance", "fpaFuelKpl", "fpaFuelLiters", "fpaFuelTrips"].forEach(
                function (id) {
                    if ($(id)) $(id).innerHTML = dash();
                },
            );
            var empty = $("fpaFuelEmpty");
            if (empty) {
                empty.textContent =
                    fuel.message ||
                    "Fuel requirements cannot be estimated for the selected filters.";
                empty.style.display = "flex";
            }
            $("fpaFuelContext").innerHTML = "&nbsp;";
            if ($("fpaFuelRangeLabel")) {
                $("fpaFuelRangeLabel").textContent =
                    "Fuel estimate unavailable for the selected filters";
            }
            destroyChart("fpaFuelChart");
            if (chartWrap) chartWrap.style.display = "none";
            return;
        }

        $("fpaFuelDistance").textContent =
            fmtInt(Math.round(Number(fuel.expected_distance) || 0)) + " km";
        $("fpaFuelKpl").textContent =
            Number(fuel.estimated_kpl) > 0 ? Number(fuel.estimated_kpl) + " km/L" : "\u2013";
        $("fpaFuelLiters").textContent =
            "~" + fmtInt(Math.round(Number(fuel.estimated_liters) || 0)) + " L";
        $("fpaFuelTrips").textContent = fmtInt(fuel.projected_trips);

        var rangeLabel = $("fpaFuelRangeLabel");
        if (rangeLabel) {
            var windowText = compactRange(
                forecast && forecast.forecast_start,
                forecast && forecast.forecast_end
            );
            var horizonDays = Number(model && model.forecast_period ? model.forecast_period.days : 0);
            rangeLabel.textContent = windowText
                ? "Projected " + windowText + (horizonDays > 0 ? " (" + horizonDays + " days)" : "")
                : fuel.period_label
                  ? "Projected \u00b7 " + fuel.period_label
                  : "Projected period";
        }

        var pieces = [esc(fuel.period_label || "")];
        if (fuel.bus_context) pieces.push(esc(fuel.bus_context));
        if (fuel.route_context) pieces.push(esc(fuel.route_context));
        pieces.push(fuel.rate_scope === "bus" ? "selected bus history" : "fleet average");
        $("fpaFuelContext").innerHTML = pieces.join(" \u00b7 ");

        var supportsChart = Number(fuel.previous_liters) > 0 && Number(fuel.estimated_liters) > 0;
        if (chartWrap) chartWrap.style.display = supportsChart ? "" : "none";
        if (supportsChart) {
            toggleEmpty("fpaFuelEmpty", true);
            drawFuelChart(fuel);
        } else {
            destroyChart("fpaFuelChart");
        }
    }

    function drawFuelChart(fuel) {
        destroyChart("fpaFuelChart");
        var canvas = $("fpaFuelChart");
        if (!canvas || typeof Chart === "undefined") return;

        var labels = [];
        var values = [];
        var colors = [];
        if (Number(fuel.previous_liters) > 0) {
            labels.push("Previous period");
            values.push(Number(fuel.previous_liters));
            colors.push("#8a9a8a");
        }
        labels.push("Projected");
        values.push(Number(fuel.estimated_liters) || 0);
        colors.push("#2f6f4f");

        charts.fpaFuelChart = new Chart(canvas, {
            type: "bar",
            data: {
                labels: labels,
                datasets: [
                    {
                        label: "Fuel (L)",
                        data: values,
                        backgroundColor: colors,
                        borderRadius: 6,
                        maxBarThickness: 52,
                        borderWidth: 0,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 8, right: 8, bottom: 4, left: 4 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: "rgba(46,72,54,0.92)",
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function (ctx) {
                                return Number(ctx.parsed.y).toLocaleString("en-PH") + " L";
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: tickColor, font: { family: chartPoppins, size: 11 } },
                        border: { color: "#cdd9b5" },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: {
                            color: tickColor,
                            font: { family: chartPoppins, size: 11 },
                            maxTicksLimit: 6,
                        },
                        border: { display: false },
                    },
                },
            },
        });
    }

    function reliabilityClass(level) {
        var value = String(level || "").toLowerCase();
        if (value === "good" || value === "moderate" || value === "limited") return value;
        return "unavailable";
    }

    function renderModel(model, unavailable) {
        model = model || {};
        $("fpaModelName").textContent = model.name || "\u2013";
        $("fpaModelRecords").textContent =
            model.passenger_records != null ? fmtInt(model.passenger_records) : "\u2013";
        if ($("fpaModelTrips")) {
            $("fpaModelTrips").textContent =
                model.completed_trips != null ? fmtInt(model.completed_trips) : "\u2013";
        }

        if (model.forecast_period) {
            $("fpaModelPeriod").textContent =
                longDate(model.forecast_period.start) +
                " \u2013 " +
                longDate(model.forecast_period.end) +
                " (" +
                model.forecast_period.days +
                " days)";
        } else {
            $("fpaModelPeriod").textContent = "\u2013";
        }

        var reliability = model.reliability || {};
        var badge = $("fpaModelReliability");
        if (badge) {
            badge.className = "fpa-reliability " + reliabilityClass(reliability.level);
            badge.textContent =
                reliability.level === "Unknown"
                    ? "Forecast reliability cannot be determined yet."
                    : reliability.label || reliability.level || "\u2013";
            badge.title = reliability.reason || "";
        }

        var reasonEl = $("fpaModelReason");
        if (reasonEl) {
            var reasonText =
                reliability.reason && reliability.level !== "Good" ? reliability.reason : "";
            reasonEl.textContent = reasonText;
            reasonEl.style.display = reasonText ? "block" : "none";
        }

        $("fpaModelInsufficient").style.display = unavailable ? "flex" : "none";
    }

    function renderInsights(insights, insufficient) {
        var box = $("fpaInsights");
        if (!box) return;
        if (!insights || !insights.length) {
            box.innerHTML =
                '<div class="fpa-inline-empty">No insights available for the selected filters.</div>';
            return;
        }
        var icons = [
            "bi-lightbulb",
            "bi-graph-up-arrow",
            "bi-signpost-split",
            "bi-calendar-event",
            "bi-fuel-pump",
        ];
        box.innerHTML = insights
            .map(function (text, i) {
                return (
                    '<div class="fpa-insight"><i class="bi ' +
                    icons[i % icons.length] +
                    '"></i><span>' +
                    esc(text) +
                    "</span></div>"
                );
            })
            .join("");
    }

    function renderAll(payload) {
        hideNoData();
        renderSummary(payload.summary);
        renderForecast(payload.forecast, payload.model);
        renderTime(payload.by_time);
        renderFuelCost(payload.fuel, payload.model);
        renderFuel(payload.fuel, payload.forecast, payload.model);
        renderModel(payload.model, false);
        renderInsights(payload.insights, false);
    }

    function renderUnavailable(payload) {
        var quality = payload.data_quality || {};
        var message =
            quality.message ||
            "Prediction unavailable due to insufficient or inconsistent historical data.";
        showNoData(message, quality.detail || "");
        clearPredictions();
        renderModel(payload.model, true);
        renderInsights(payload.insights, true);
        if ($("fpaForecastRangeLabel")) {
            $("fpaForecastRangeLabel").textContent = "Historical and predicted passenger counts";
        }
        if ($("fpaFuelRangeLabel")) {
            $("fpaFuelRangeLabel").textContent = "Forecast unavailable for the selected filters";
        }
        $("fpaForecastEmpty").textContent = message;
        toggleEmpty("fpaForecastEmpty", false);
        $("fpaFuelEmpty").textContent = message;
        toggleEmpty("fpaFuelEmpty", false);
        if ($("fpaFuelCostScroll")) {
            $("fpaFuelCostScroll").innerHTML =
                '<div class="fpa-inline-empty">' + esc(message) + "</div>";
        }
        if ($("fpaFuelCostSub"))
            $("fpaFuelCostSub").textContent = "Projected fuel expenses for the forecast horizon";
        if ($("fpaFuelCostContext")) $("fpaFuelCostContext").innerHTML = "&nbsp;";
        if ($("fpaModelInsufficientText")) $("fpaModelInsufficientText").textContent = message;
    }

    function buildUrl() {
        var params = new URLSearchParams({ horizon: state.period });
        if (state.route) params.set("route", state.route);
        if (state.bus) params.set("bus", state.bus);
        if (state.time_period) params.set("time_period", state.time_period);
        return API + "?" + params.toString();
    }

    async function load() {
        var reqId = ++loadReqId;
        setLoading(true);
        hideError();
        var refresh = $("fpaRefresh");
        if (refresh) refresh.disabled = true;
        var timer;

        try {
            var controller = new AbortController();
            timer = setTimeout(function () {
                controller.abort();
            }, 75000);
            var response = await fetch(buildUrl(), {
                signal: controller.signal,
                headers: { Accept: "application/json" },
                cache: "no-store",
                credentials: "same-origin",
            });
            var body = await response.text();
            var payload;
            try { payload = JSON.parse(body); }
            catch (_) {
                throw new Error(response.status === 401 || response.status === 403
                    ? 'Your Finance session has expired. Sign in again to load predictions.'
                    : 'The prediction service returned an invalid or empty response (HTTP ' + response.status + '). Please retry.');
            }
            if (reqId !== loadReqId) return;

            if (!response.ok || !payload || payload.success !== true) {
                showError(
                    payload && payload.message
                        ? payload.message
                        : "Unable to compute predictions right now.",
                );
                return;
            }

            if (!payload.filters) {
                payload.filters = {
                    period: state.period,
                    route: state.route,
                    bus: state.bus,
                    time_period: state.time_period,
                };
            }
            applyFilters(payload.filters);

            if (payload.data_quality && payload.data_quality.unavailable) {
                renderUnavailable(payload);
            } else {
                renderAll(payload);
            }

            $("fpaLastUpdated").textContent =
                "Last updated: " + ((payload.model && payload.model.computed_at) || "\u2014");
            reveal();
        } catch (error) {
            if (reqId !== loadReqId) return;
            showError(
                error && error.name === "AbortError"
                    ? "The prediction request timed out. Please try again."
                    : error && error.name !== 'TypeError' && error.message
                        ? error.message
                        : "Unable to reach the prediction service. Check your connection and reload the page.",
            );
        } finally {
            clearTimeout(timer);
            if (reqId === loadReqId) {
                setLoading(false);
                if (refresh) refresh.disabled = false;
            }
        }
    }

    document.querySelectorAll(".fpa-period-btn").forEach(function (btn) {
        btn.addEventListener("click", function () {
            state.period = btn.getAttribute("data-period") || "7d";
            document.querySelectorAll(".fpa-period-btn").forEach(function (b) {
                b.classList.toggle("active", b === btn);
            });
            state.fuelCostView = "day";
            var fuelCostBar = $("fpaFuelCostViewBar");
            if (fuelCostBar) {
                fuelCostBar.querySelectorAll(".fpa-view-btn").forEach(function (b) {
                    b.classList.toggle("active", b.getAttribute("data-view") === "day");
                });
            }
            load();
        });
    });

    var chartViewBar = $("fpaViewBar");
    if (chartViewBar) {
        chartViewBar.querySelectorAll(".fpa-view-btn").forEach(function (btn) {
            btn.addEventListener("click", function () {
                state.view = btn.getAttribute("data-view") || "daily";
                chartViewBar.querySelectorAll(".fpa-view-btn").forEach(function (b) {
                    b.classList.toggle("active", b === btn);
                });
                if (lastPoints.length) drawForecastChart(lastPoints);
            });
        });
    }

    var fuelCostViewBar = $("fpaFuelCostViewBar");
    if (fuelCostViewBar) {
        fuelCostViewBar.querySelectorAll(".fpa-view-btn").forEach(function (btn) {
            btn.addEventListener("click", function () {
                state.fuelCostView = btn.getAttribute("data-view") || "day";
                fuelCostViewBar.querySelectorAll(".fpa-view-btn").forEach(function (b) {
                    b.classList.toggle("active", b === btn);
                });
                if (lastFuelCost) renderFuelCost(lastFuelCost.fuel, lastFuelCost.model);
            });
        });
    }

    var routeSel = $("fpaRoute");
    if (routeSel)
        routeSel.addEventListener("change", function () {
            state.route = routeSel.value;
            load();
        });
    var busSel = $("fpaBus");
    if (busSel)
        busSel.addEventListener("change", function () {
            state.bus = busSel.value;
            load();
        });
    var timeSel = $("fpaTimePeriod");
    if (timeSel)
        timeSel.addEventListener("change", function () {
            state.time_period = timeSel.value;
            load();
        });
    var refreshBtn = $("fpaRefresh");
    if (refreshBtn) refreshBtn.addEventListener("click", load);

    load();
})();
</script>
