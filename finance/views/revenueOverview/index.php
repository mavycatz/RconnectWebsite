<?php header('Cache-Control: no-store, no-cache, must-revalidate'); header('Pragma: no-cache'); ?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/public/login/custom-alert.css">
<?php if (!defined('APP_LOADED')) exit('No direct script access allowed'); ?>
<?php require_once __DIR__ . '/../layout/navbarfinance.php'; ?>
<div class="main-content" id="mainContent" style="font-family:'Poppins',sans-serif;background:#eceed9;padding:20px;">
    <?php require_once __DIR__ . '/../layout/_headernav.php'; ?>
    <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;">
        <?php require __DIR__ . '/_header.php'; ?>
        <?php require __DIR__ . '/_stats.php'; ?>
        <?php require __DIR__ . '/_tabs.php'; ?>
        <?php require __DIR__ . '/_Busperformance.php'; ?>
        <?php require __DIR__ . '/_RouteProfit.php'; ?>
        <?php require __DIR__ . '/_Locationinsights.php'; ?>
        <?php require __DIR__ . '/_Timebasedtrends.php'; ?>
        <?php require __DIR__ . '/_aibutton.php'; ?>


<div id="customAlertOverlay" class="custom-alert-overlay">
  <div class="custom-alert-card">
    <div class="custom-alert-header">
      <div class="custom-alert-icon"></div>
      <div class="custom-alert-title"></div>
    </div>
    <div class="custom-alert-body">
      <div class="custom-alert-message"></div>
      <div class="custom-alert-actions">
        <button class="custom-alert-btn"></button>
        <button class="custom-alert-btn-cancel"></button>
      </div>
    </div>
  </div>
</div>

    </div>
</div>
<script src="/public/login/custom-alert.js"></script>

<style>
:root {
    --ro-green: #05590c;
    --ro-green-2: #198754;
    --ro-green-3: #81c784;
    --ro-gold: #d9a441;
    --ro-red: #c62828;
    --ro-cream: #faf9e5;
    --ro-cream-2: #f4f3dc;
    --ro-border: #e7e3af;
    --ro-border-soft: #eeecd4;
    --ro-ink: #1f2937;
    --ro-muted: #8a9a8a;
    --ro-shadow: 0 1px 2px rgba(41, 87, 56, 0.05);
}
.ro-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: nowrap;
    margin-bottom: 18px;
}
.ro-header-title {
    flex: 0 1 auto;
    min-width: 0;
}
.ro-title {
    font-weight: 800;
    font-size: 1.4rem;
    color: #295738;
    margin: 0 0 2px;
    line-height: 1.15;
    font-family: "Poppins", sans-serif;
}
.ro-subtitle {
    font-weight: 500;
    font-size: 0.82rem;
    color: #8a9a8a;
    margin: 0;
    line-height: 1.3;
    max-width: 420px;
}
.ro-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
    flex: 0 1 auto;
    min-width: 0;
}
.ro-control {
    height: 34px;
    border-radius: 20px;
    border: 1.5px solid #e5e0a4;
    background: #faf9e5;
    padding: 0 14px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #295738;
    outline: none;
    font-family: "Poppins", sans-serif;
    box-sizing: border-box;
    transition:
        border-color 0.2s,
        box-shadow 0.2s;
}
.ro-control:hover {
    border-color: #d8d29a;
}
.ro-control:focus {
    border-color: var(--ro-green-2);
    box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.12);
}
select.ro-control {
    appearance: none;
    -webkit-appearance: none;
    padding-right: 30px;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23295738'%3E%3Cpath d='M8 11L3 6h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 11px center;
    background-size: 10px;
    cursor: pointer;
}
.ro-control-date {
    width: 120px;
    height: 28px;
    padding: 0 6px;
    font-size: 0.75rem;
    border: 1px solid #e3eabb;
    background: #fff;
    border-radius: 8px;
}
.ro-dates {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #faf9e5;
    border: 1.5px solid #e5e0a4;
    border-radius: 12px;
    padding: 0 10px;
    height: 34px;
    box-sizing: border-box;
}
.ro-dates .ro-control {
    border: 1px solid #e3eabb;
    background: #fff;
    height: 28px;
    box-shadow: none;
}
.ro-dates .ro-control:focus {
    box-shadow: none;
    border-color: var(--ro-green-2);
}
.ro-dash {
    color: #8a9a8a;
    font-size: 0.72rem;
    font-weight: 600;
}
.ro-control-sm {
    width: 84px;
    text-align: center;
    padding: 0 10px;
    border-radius: 10px;
    border: 1.5px solid #e5e0a4;
    background: #fff;
    font-size: 0.78rem;
    font-weight: 500;
}
.ro-control-md {
    width: 108px;
    text-align: center;
    padding: 0 10px;
    border-radius: 10px;
    border: 1.5px solid #e5e0a4;
    background: #fff;
    font-size: 0.78rem;
    font-weight: 500;
}
.ro-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    border: 1.5px solid #e5e0a4;
    background: #fff;
    color: #295738;
    cursor: pointer;
    font-size: 0.85rem;
    padding: 0;
    transition: 0.2s;
    flex: 0 0 auto;
    font-family: "Poppins", sans-serif;
}
.ro-icon-btn:hover {
    background: #faf9e5;
    color: var(--ro-green-2);
    border-color: #d8d29a;
}
.ro-icon-btn:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.12);
}

.ro-kpis {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 18px;
    align-items: stretch;
}
.ro-kpi {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
    border: 1.5px solid #e3eabb;
    border-radius: 16px;
    padding: 15px 16px 12px;
    position: relative;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
    transition: 0.2s;
}
.ro-kpi:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.ro-kpi-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
}
.ro-kpi-head h6 {
    font-size: 10px;
    font-weight: 700;
    color: #8a9389;
    margin: 4px 0 3px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    line-height: 1.3;
}
.ro-kpi-icon-chip {
    width: 28px;
    height: 28px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #eef4e6;
    flex: 0 0 auto;
}
.ro-kpi-icon {
    width: 16px;
    height: 16px;
    object-fit: contain;
}
.ro-kpi-value {
    font-size: clamp(20px, 1.65vw, 26px);
    font-weight: 800;
    color: #2e4836;
    margin: 0;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ro-kpi-value-sm {
    font-size: clamp(18px, 1.4vw, 22px);
}
.ro-kpi-desc {
    font-size: 10.5px;
    color: #8a9389;
    margin-top: 4px;
    line-height: 1.3;
    flex: 1 1 auto;
}
.ro-kpi-foot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    padding-top: 10px;
    border-top: 1px solid #eef0e6;
}
.ro-period {
    display: inline-flex;
    align-items: center;
    height: 20px;
    font-size: 10px;
    font-weight: 700;
    color: #6b8e6b;
    background: #f4f7ee;
    border-radius: 6px;
    padding: 0 7px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 60%;
}
.ro-trend {
    font-size: 11px;
    font-weight: 600;
    color: #8a9389;
    white-space: nowrap;
}
.ro-trend:empty {
    padding: 0;
    background: none;
}
.ro-trend.up {
    color: #16a34a;
}
.ro-trend.down {
    color: #dc2626;
}
.ro-trend.flat {
    color: #8a9389;
}
.ro-kpi.is-loading .ro-kpi-value {
    color: transparent;
    background: linear-gradient(90deg, #eef1e9, #e1e8d7, #eef1e9);
    background-size: 200% 100%;
    border-radius: 6px;
    animation: roShimmer 1.3s linear infinite;
}
@keyframes roShimmer {
    from {
        background-position: 200% 0;
    }
    to {
        background-position: -200% 0;
    }
}
@media (max-width: 1100px) {
    .ro-kpis {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 575px) {
    .ro-kpis {
        grid-template-columns: 1fr;
    }
}

.ro-section-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 12px;
    margin: 2px 0 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--ro-border-soft);
}
.ro-section-head h2 {
    font-size: 1rem;
    font-weight: 800;
    color: #295738;
    margin: 0;
    line-height: 1.2;
}
.ro-section-head p {
    font-size: 0.75rem;
    color: var(--ro-muted);
    margin: 3px 0 0;
    font-weight: 500;
}
.ro-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 14px;
    margin-bottom: 14px;
    align-items: start;
}
.ro-grid:last-child {
    margin-bottom: 0;
}
.ro-card {
    background: linear-gradient(180deg, #fffdf2 0%, var(--ro-cream) 100%);
    border: 2px solid var(--ro-border);
    border-radius: 16px;
    padding: 15px 16px 16px;
    position: relative;
    box-shadow: var(--ro-shadow);
}
.ro-card-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}
.ro-card-title-left {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}
.ro-card-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 8px;
    background: #eef0d8;
    color: var(--ro-green);
    font-size: 0.72rem;
    flex: 0 0 auto;
}
.ro-card-text {
    font-weight: 700;
    color: var(--ro-green);
    font-size: 0.8rem;
    line-height: 1.25;
}
.ro-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #eef0d8;
    border: 1px solid var(--ro-border);
    color: var(--ro-green-2);
    font-size: 0.68rem;
    font-weight: 700;
    cursor: pointer;
    padding: 4px 10px;
    border-radius: 999px;
    font-family: "Poppins", sans-serif;
    transition: 0.2s;
    white-space: nowrap;
}
.ro-link:hover {
    background: #e4e7c9;
}
.ro-chart-wrap {
    position: relative;
    height: 240px;
}
.ro-chart-wrap--sm {
    height: 210px;
}
.ro-chart-wrap canvas {
    width: 100% !important;
    max-width: 100%;
}
.ro-empty {
    display: none;
    min-height: 200px;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 16px;
    color: var(--ro-muted);
    font-weight: 600;
    font-size: 0.8rem;
    border: 1.5px dashed var(--ro-border);
    border-radius: 12px;
    background: rgba(255, 253, 242, 0.6);
}
.ro-card.is-empty .ro-chart-wrap {
    display: none;
}
.ro-card.is-empty .ro-empty {
    display: flex;
}
.ro-card.is-empty .ro-loading {
    display: none;
}
.ro-loading {
    position: absolute;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: rgba(250, 249, 229, 0.8);
    border-radius: 14px;
    color: var(--ro-muted);
    font-weight: 600;
    font-size: 0.76rem;
    z-index: 3;
}
.ro-loading::before {
    content: "";
    width: 15px;
    height: 15px;
    border: 2px solid #ddd9ae;
    border-top-color: var(--ro-green-2);
    border-radius: 50%;
    animation: roSpin 0.8s linear infinite;
}
@keyframes roSpin {
    to {
        transform: rotate(360deg);
    }
}
.ro-card.is-loading .ro-loading {
    display: flex;
}
.ro-section-error {
    display: none;
    margin-bottom: 14px;
    padding: 10px 14px;
    border-radius: 12px;
    background: #fdecea;
    border: 1px solid #f5c6c2;
    color: var(--ro-red);
    font-size: 0.78rem;
    font-weight: 600;
}

.revenue-section {
    display: none;
}
.revenue-section.active {
    display: block;
    animation: roFade 0.25s ease;
}
@keyframes roFade {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}
.revenue-tabs {
    display: flex;
    gap: 6px;
    padding: 6px;
    border: 1.5px solid var(--ro-border);
    border-radius: 14px;
    background: var(--ro-cream-2);
    width: 100%;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 20px;
    box-sizing: border-box;
}
.revenue-tab-btn {
    background: transparent;
    border: 1.5px solid transparent;
    padding: 9px 14px;
    font-weight: 600;
    color: #6b7c6b;
    cursor: pointer;
    border-radius: 10px;
    transition: 0.2s;
    flex: 1;
    min-width: 0;
    text-align: center;
    font-size: 0.8rem;
    white-space: normal;
    line-height: 1.2;
    font-family: "Poppins", sans-serif;
}
.revenue-tab-btn:hover {
    background: rgba(255, 253, 242, 0.9);
    color: #295738;
}
.revenue-tab-btn.active {
    font-weight: 700;
    background: #fffdf2;
    color: var(--ro-green);
    border-color: var(--ro-green-2);
    box-shadow: 0 2px 6px rgba(41, 87, 56, 0.1);
}

.ro-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(31, 41, 55, 0.45);
    backdrop-filter: blur(4px);
    align-items: center;
    justify-content: center;
}
.ro-modal.open {
    display: flex;
}
.ro-modal-card {
    background: #fffdf7;
    border: 2px solid var(--ro-border);
    border-radius: 20px;
    max-width: 520px;
    width: 92%;
    max-height: 80vh;
    overflow: auto;
    padding: 24px;
    position: relative;
    box-shadow: 0 24px 70px rgba(0, 0, 0, 0.2);
}
.ro-modal-close {
    position: absolute;
    top: 12px;
    right: 16px;
    background: none;
    border: none;
    font-size: 1.5rem;
    line-height: 1;
    cursor: pointer;
    color: #9aa79a;
}
.ro-modal-close:hover {
    color: var(--ro-red);
}
.ro-modal-title {
    color: var(--ro-green);
    font-weight: 800;
    font-size: 1.05rem;
    margin: 0 0 16px;
}
.ro-modal-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 9px 0;
    border-bottom: 1px solid var(--ro-border-soft);
    font-size: 0.8rem;
    color: #556;
}
.ro-modal-row:last-child {
    border-bottom: none;
}
.ro-modal-row span:last-child {
    font-weight: 700;
    color: var(--ro-green);
    white-space: nowrap;
}

@media (max-width: 1400px) {
    .ro-subtitle {
        max-width: 280px;
    }
    .ro-control-date {
        width: 104px;
    }
    .ro-control-sm {
        width: 76px;
    }
    .ro-control-md {
        width: 98px;
    }
}
@media (max-width: 1200px) {
    .ro-subtitle {
        max-width: 240px;
    }
    .ro-control-date {
        width: 96px;
    }
    .ro-control-sm {
        width: 68px;
    }
    .ro-control-md {
        width: 88px;
    }
}
@media (max-width: 992px) {
    .ro-kpi-value {
        font-size: 1.4rem;
    }
}
@media (max-width: 768px) {
    .ro-header {
        flex-wrap: wrap;
        align-items: flex-start;
        margin-bottom: 16px;
    }
    .ro-toolbar {
        width: 100%;
        justify-content: flex-start;
    }
    .ro-subtitle {
        max-width: 100%;
    }
    .ro-chart-wrap {
        height: 215px;
    }
    .ro-chart-wrap--sm {
        height: 195px;
    }
    .ro-kpi-value {
        font-size: 1.3rem;
    }
}
@media (max-width: 576px) {
    .ro-control-sm,
    .ro-control-md {
        flex: 1 1 120px;
        width: auto;
    }
    .ro-dates {
        flex: 1 1 100%;
        justify-content: center;
    }
    .revenue-tab-btn {
        flex: 1 1 calc(50% - 6px);
    }
}
@media (max-width: 480px) {
    .ro-grid {
        grid-template-columns: 1fr;
    }
    .ro-control-sm,
    .ro-control-md {
        width: 100%;
        text-align: left;
    }
    .ro-icon-btn {
        flex: 1 1 auto;
    }
    .revenue-tab-btn {
        flex: 1 1 100%;
    }
    .ro-kpi-desc {
        min-height: 0;
    }
    .ro-section-head {
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
    }
}
</style>

<script>
(function () {
    "use strict";

    var API = "/finance/api/analytics/revenue_overview.php";
    var SECTION_BY_TAB = { bus: "drivers", route: "route", station: "location", hour: "time" };
    var FIN_CONFIG = (function () {
        var node = document.querySelector("[data-fin-config]");
        if (!node) {
            return { selected: "monthly", options: [], from: "", to: "" };
        }
        try {
            return JSON.parse(node.textContent || "{}");
        } catch (e) {
            return { selected: "monthly", options: [], from: "", to: "" };
        }
    })();
    var PERIOD_LABELS = {};
    (FIN_CONFIG.options || []).forEach(function (option) {
        PERIOD_LABELS[option.value] = option.label;
    });
    var COLORS = {
        green: "#05590c",
        soft: "#198754",
        light: "#81c784",
        gold: "#d9a441",
        red: "#c62828",
    };

    var state = {
        filters: {
            period: FIN_CONFIG.selected || "monthly",
            from: FIN_CONFIG.from || "",
            to: FIN_CONFIG.to || "",
        },
        activeTab: "bus",
        cache: {},
        inflight: {},
        charts: {},
        rendered: {},
        drivers: [],
    };

    function $(id) {
        return document.getElementById(id);
    }

    function signature() {
        var f = state.filters;
        var dates = f.period === "custom" ? f.from + "~" + f.to : "";
        return [f.period, dates].join("|");
    }

    function peso(value) {
        return (
            "₱" +
            Number(value || 0).toLocaleString("en-PH", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })
        );
    }

    function whole(value) {
        return Number(value || 0).toLocaleString("en-PH");
    }

    function shortLabel(value, max) {
        var text = String(value === null || value === undefined ? "" : value);
        if (text.length <= max) {
            return text;
        }
        return text.slice(0, Math.max(1, max - 1)) + "…";
    }

    function formatHourRange(label) {
        var parts = String(label === null || label === undefined ? "" : label).split(" - ");
        if (parts.length !== 2) {
            return String(label || "—");
        }
        function to12(value) {
            var bits = String(value).split(":");
            var hour = Number(bits[0]);
            if (isNaN(hour)) {
                return value;
            }
            var suffix = hour >= 12 ? "PM" : "AM";
            var display = hour % 12;
            if (display === 0) {
                display = 12;
            }
            return display + ":" + (bits[1] || "00") + " " + suffix;
        }
        return to12(parts[0]) + " – " + to12(parts[1]);
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? "" : value).replace(
            /[&<>"']/g,
            function (ch) {
                return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[ch];
            },
        );
    }

    function request(section, force) {
        var key = signature() + "::" + section;

        if (!force && state.cache[key]) {
            return Promise.resolve(state.cache[key]);
        }
        if (state.inflight[key]) {
            return state.inflight[key];
        }

        var params = new URLSearchParams();
        params.set("section", section);
        params.set("period", state.filters.period);
        if (state.filters.period === "custom") {
            if (state.filters.from) {
                params.set("from", state.filters.from);
            }
            if (state.filters.to) {
                params.set("to", state.filters.to);
            }
        }

        state.inflight[key] = fetch(API + "?" + params.toString(), {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
        })
            .then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || !payload.success) {
                        throw new Error(
                            payload && payload.message ? payload.message : "Request failed.",
                        );
                    }
                    state.cache[key] = payload;
                    return payload;
                });
            })
            .finally(function () {
                delete state.inflight[key];
            });

        return state.inflight[key];
    }

    function chart(canvasId, config) {
        var canvas = $(canvasId);
        if (!canvas || typeof window.Chart === "undefined") {
            return null;
        }
        if (state.charts[canvasId]) {
            state.charts[canvasId].destroy();
            delete state.charts[canvasId];
        }
        state.charts[canvasId] = new window.Chart(canvas.getContext("2d"), config);
        return state.charts[canvasId];
    }

    function setCardEmpty(canvasId, isEmpty, message) {
        var canvas = $(canvasId);
        if (!canvas) {
            return;
        }
        var card = canvas.closest(".ro-card");
        if (!card) {
            return;
        }
        card.classList.toggle("is-empty", !!isEmpty);
        var empty = card.querySelector(".ro-empty");
        if (empty && message) {
            empty.textContent = message;
        }
    }

    function setKpisLoading(isLoading) {
        var wrap = $("roKpis");
        if (!wrap) {
            return;
        }
        wrap.querySelectorAll(".ro-kpi").forEach(function (card) {
            card.classList.toggle("is-loading", !!isLoading);
        });
    }

    function setSectionLoading(tab, isLoading) {
        var section = $(tab);
        if (!section) {
            return;
        }
        section.querySelectorAll(".ro-card").forEach(function (card) {
            card.classList.toggle("is-loading", !!isLoading);
        });
        if (isLoading) {
            var error = section.querySelector(".ro-section-error");
            if (error) {
                error.style.display = "none";
                error.textContent = "";
            }
        }
    }

    function showSectionError(tab, message) {
        var error = $(tab + "Error");
        if (error) {
            error.textContent = message || "Failed to load this section.";
            error.style.display = "block";
        }
    }

    function periodText(payload) {
        var period = payload.period || "all";
        if (period === "custom") {
            var range = payload.range || {};
            if (range.from || range.to) {
                return (range.from || "…") + " – " + (range.to || "…");
            }
        }
        return PERIOD_LABELS[period] || "All Time";
    }

    function setTrend(id, current, previous) {
        var el = $(id);
        if (!el) {
            return;
        }
        el.className = "ro-trend";
        el.textContent = "";

        if (previous === null || previous === undefined) {
            return;
        }

        var now = Number(current || 0);
        var before = Number(previous || 0);

        if (before === 0) {
            if (now > 0) {
                el.classList.add("up");
                el.textContent = "New";
            }
            return;
        }

        var percent = ((now - before) / before) * 100;
        if (Math.abs(percent) < 0.05) {
            el.classList.add("flat");
            el.textContent = "0%";
            return;
        }
        el.classList.add(percent > 0 ? "up" : "down");
        el.textContent = (percent > 0 ? "▲ " : "▼ ") + Math.abs(percent).toFixed(1) + "%";
    }

    function baseScales(currency) {
        return {
            y: {
                beginAtZero: true,
                grid: { color: "rgba(0,0,0,0.05)" },
                ticks: {
                    color: "#8a9a8a",
                    font: { size: 10 },
                    callback: function (value) {
                        if (currency) {
                            return "₱" + Number(value).toLocaleString("en-PH");
                        }
                        return Number(value).toLocaleString("en-PH");
                    },
                },
            },
            x: {
                grid: { display: false },
                ticks: {
                    color: "#8a9a8a",
                    font: { size: 10 },
                    maxRotation: 0,
                    autoSkip: true,
                    autoSkipPadding: 12,
                },
            },
        };
    }

    function horizontalScales(currency) {
        return {
            x: {
                beginAtZero: true,
                grid: { color: "rgba(0,0,0,0.05)" },
                ticks: {
                    color: "#8a9a8a",
                    font: { size: 10 },
                    callback: function (value) {
                        if (currency) {
                            return "₱" + Number(value).toLocaleString("en-PH");
                        }
                        return Number(value).toLocaleString("en-PH");
                    },
                },
            },
            y: {
                grid: { display: false },
                ticks: { color: "#8a9a8a", font: { size: 10 }, autoSkip: false },
            },
        };
    }

    function tooltipTitle(items) {
        var item = items[0];
        var full = item && item.dataset ? item.dataset.roFull : null;
        if (full && full[item.dataIndex] !== undefined) {
            return full[item.dataIndex];
        }
        return item ? item.label : "";
    }

    function barChart(canvasId, labels, values, label, color, fullLabels, currency) {
        setCardEmpty(canvasId, false);
        return chart(canvasId, {
            type: "bar",
            data: {
                labels: labels,
                datasets: [
                    {
                        label: label,
                        data: values,
                        backgroundColor: color || COLORS.soft,
                        borderRadius: 6,
                        maxBarThickness: 30,
                        roFull: fullLabels || labels,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { title: tooltipTitle } },
                },
                scales: baseScales(currency !== false),
            },
        });
    }

    function horizontalBarChart(canvasId, labels, values, label, color, fullLabels, currency) {
        setCardEmpty(canvasId, false);
        return chart(canvasId, {
            type: "bar",
            data: {
                labels: labels,
                datasets: [
                    {
                        label: label,
                        data: values,
                        backgroundColor: color || COLORS.soft,
                        borderRadius: 6,
                        maxBarThickness: 22,
                        roFull: fullLabels || labels,
                    },
                ],
            },
            options: {
                indexAxis: "y",
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { title: tooltipTitle } },
                },
                scales: horizontalScales(currency !== false),
            },
        });
    }

    function lineChart(canvasId, labels, values, label, color) {
        setCardEmpty(canvasId, false);
        return chart(canvasId, {
            type: "line",
            data: {
                labels: labels,
                datasets: [
                    {
                        label: label,
                        data: values,
                        borderColor: color || COLORS.green,
                        backgroundColor: "rgba(25,135,84,0.12)",
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2,
                        pointBackgroundColor: color || COLORS.green,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: baseScales(true),
            },
        });
    }

    function renderKpis(payload) {
        var data = payload.data || {};
        var previous = data.previous || null;
        var period = periodText(payload);

        $("kpiRevenue").textContent = peso(data.revenue);
        $("kpiAvgTrip").textContent = peso(data.avg_revenue_per_trip);
        $("kpiActiveDrivers").textContent = whole(data.active_drivers);
        $("kpiPeakHour").textContent =
            data.peak_hour && data.peak_hour.label ? formatHourRange(data.peak_hour.label) : "—";

        document.querySelectorAll("#roKpis .ro-period").forEach(function (el) {
            el.textContent = period;
        });

        setTrend("kpiRevenueTrend", data.revenue, previous ? previous.revenue : null);
        setTrend(
            "kpiAvgTripTrend",
            data.avg_revenue_per_trip,
            previous ? previous.avg_revenue_per_trip : null,
        );
        setTrend(
            "kpiActiveDriversTrend",
            data.active_drivers,
            previous ? previous.active_drivers : null,
        );
        setTrend("kpiPeakHourTrend", null, null);
    }

    function loadKpis(force) {
        setKpisLoading(true);
        return request("kpis", force)
            .then(function (payload) {
                setKpisLoading(false);
                if (payload.summary && window.FinanceDateFilter) {
                    window.FinanceDateFilter.setSummary(payload.summary);
                }
                renderKpis(payload);
            })
            .catch(function () {
                setKpisLoading(false);
                $("kpiRevenue").textContent = peso(0);
                $("kpiAvgTrip").textContent = peso(0);
                $("kpiActiveDrivers").textContent = whole(0);
                $("kpiPeakHour").textContent = "—";
            });
    }

    function renderDrivers(payload) {
        var data = payload.data || {};
        var list = Array.isArray(data.drivers) ? data.drivers : [];
        var topN = Number(data.top_n) || 10;
        var ids = [
            "revenuePerDriver",
            "revenuePerTripDriver",
            "tripsPerDriver",
            "avgRevenueDriver",
            "highestDriver",
            "contributionDriver",
        ];

        state.drivers = list;

        if (!list.length) {
            ids.forEach(function (id) {
                setCardEmpty(id, true, "No profit data available for this period.");
            });
            var body = $("driverModalBody");
            if (body) {
                body.innerHTML =
                    '<div class="ro-modal-row"><span>No data</span><span>—</span></div>';
            }
            return;
        }

        var top = list.slice(0, topN);
        var topLabels = top.map(function (row) {
            return shortLabel(row.name, 16);
        });
        var topFull = top.map(function (row) {
            return row.name;
        });

        barChart(
            "revenuePerDriver",
            topLabels,
            top.map(function (row) {
                return row.revenue;
            }),
            "Profit",
            COLORS.green,
            topFull,
            true,
        );
        barChart(
            "revenuePerTripDriver",
            topLabels,
            top.map(function (row) {
                return row.revenue_per_trip;
            }),
            "Profit per Trip",
            COLORS.gold,
            topFull,
            true,
        );
        barChart(
            "tripsPerDriver",
            topLabels,
            top.map(function (row) {
                return row.trips;
            }),
            "Trips",
            COLORS.soft,
            topFull,
            false,
        );

        var totalRow = data.totals || {};
        setCardEmpty("avgRevenueDriver", false);
        chart("avgRevenueDriver", {
            type: "bar",
            data: {
                labels: ["Fleet Average"],
                datasets: [
                    {
                        label: "Average Profit per Trip",
                        data: [Number(totalRow.revenue_per_trip || 0)],
                        backgroundColor: COLORS.soft,
                        borderRadius: 6,
                        maxBarThickness: 60,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: baseScales(true),
            },
        });

        var highest = list[0];
        setCardEmpty("highestDriver", false);
        chart("highestDriver", {
            type: "bar",
            data: {
                labels: [shortLabel(highest.name, 18)],
                datasets: [
                    {
                        label: "Profit",
                        data: [highest.revenue],
                        backgroundColor: COLORS.green,
                        borderRadius: 6,
                        maxBarThickness: 60,
                        roFull: [highest.name],
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { title: tooltipTitle } },
                },
                scales: baseScales(true),
            },
        });

        var share = list.slice(0, 8);
        var labels = share.map(function (row) {
            return shortLabel(row.name, 14);
        });
        var values = share.map(function (row) {
            return row.revenue;
        });
        var shared = values.reduce(function (sum, value) {
            return sum + value;
        }, 0);
        var total = Number(totalRow.revenue || shared);
        var other = Math.max(0, total - shared);
        if (other > 0.005) {
            labels.push("Other");
            values.push(Number(other.toFixed(2)));
        }
        var palette = [
            COLORS.green,
            COLORS.soft,
            COLORS.light,
            COLORS.gold,
            COLORS.red,
            "#2e7d32",
            "#66bb6a",
            "#8d6e63",
            "#78909c",
        ];
        setCardEmpty("contributionDriver", false);
        chart("contributionDriver", {
            type: "doughnut",
            data: {
                labels: labels,
                datasets: [
                    {
                        data: values,
                        backgroundColor: palette.slice(0, labels.length),
                        borderWidth: 2,
                        borderColor: "#faf9e5",
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "58%",
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: {
                            color: "#8a9a8a",
                            font: { size: 10 },
                            padding: 8,
                            usePointStyle: true,
                            pointStyle: "circle",
                            boxWidth: 8,
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: function (item) {
                                return item.label + ": " + peso(item.raw);
                            },
                        },
                    },
                },
            },
        });

        var modalBody = $("driverModalBody");
        if (modalBody) {
            modalBody.innerHTML = list
                .map(function (row) {
                    return (
                        '<div class="ro-modal-row"><span>' +
                        escapeHtml(row.name) +
                        "</span><span>" +
                        peso(row.revenue) +
                        "</span></div>"
                    );
                })
                .join("");
        }
    }

    function renderRoute(payload) {
        var data = payload.data || {};
        var routes = Array.isArray(data.routes) ? data.routes : [];

        if (routes.length) {
            var labels = routes.map(function (row) {
                return shortLabel(row.label, 20);
            });
            var full = routes.map(function (row) {
                return row.label;
            });

            barChart(
                "routeRevenue",
                labels,
                routes.map(function (row) {
                    return row.revenue;
                }),
                "Profit",
                COLORS.green,
                full,
                true,
            );
            barChart(
                "routeTrips",
                labels,
                routes.map(function (row) {
                    return row.trips;
                }),
                "Trips",
                COLORS.soft,
                full,
                false,
            );
            barChart(
                "routeAvgTrip",
                labels,
                routes.map(function (row) {
                    return row.revenue_per_trip;
                }),
                "Average Profit per Trip",
                COLORS.gold,
                full,
                true,
            );
            barChart(
                "routePassengers",
                labels,
                routes.map(function (row) {
                    return row.passengers;
                }),
                "Passengers",
                COLORS.light,
                full,
                false,
            );
            barChart(
                "routeAvgFare",
                labels,
                routes.map(function (row) {
                    return row.avg_fare;
                }),
                "Average Fare",
                COLORS.soft,
                full,
                true,
            );
        } else {
            [
                "routeRevenue",
                "routeTrips",
                "routeAvgTrip",
                "routePassengers",
                "routeAvgFare",
            ].forEach(function (id) {
                setCardEmpty(id, true, "No profit data available for this period.");
            });
        }

        var hourly = Array.isArray(data.hourly) ? data.hourly : [];
        if (hourly.length) {
            barChart(
                "routePeakHour",
                hourly.map(function (row) {
                    return row.label;
                }),
                hourly.map(function (row) {
                    return row.revenue;
                }),
                "Profit",
                COLORS.soft,
                null,
                true,
            );
        } else {
            setCardEmpty("routePeakHour", true, "No hourly data available for this period.");
        }

        var dow = Array.isArray(data.dow) ? data.dow : [];
        if (dow.length) {
            var highest = dow[0];
            dow.forEach(function (row) {
                if (row.revenue > highest.revenue) {
                    highest = row;
                }
            });
            barChart(
                "routeHighestDay",
                [highest.label],
                [highest.revenue],
                "Profit",
                COLORS.green,
                null,
                true,
            );
        } else {
            setCardEmpty("routeHighestDay", true, "No daily data available for this period.");
        }
    }

    function renderLocation(payload) {
        var data = payload.data || {};
        var stations = Array.isArray(data.stations) ? data.stations : [];
        var ids = [
            "stationRevenue",
            "stationPassengers",
            "stationTop5",
            "stationDistribution",
            "stationAvgFare",
        ];

        if (!stations.length) {
            ids.forEach(function (id) {
                setCardEmpty(id, true, "No profit data available for this period.");
            });
            return;
        }

        var labels = stations.map(function (row) {
            return shortLabel(row.name, 18);
        });
        var full = stations.map(function (row) {
            return row.name;
        });

        barChart(
            "stationRevenue",
            labels,
            stations.map(function (row) {
                return row.revenue;
            }),
            "Profit",
            COLORS.green,
            full,
            true,
        );
        barChart(
            "stationPassengers",
            labels,
            stations.map(function (row) {
                return row.passengers;
            }),
            "Passengers",
            COLORS.soft,
            full,
            false,
        );
        barChart(
            "stationAvgFare",
            labels,
            stations.map(function (row) {
                return row.avg_fare;
            }),
            "Average Fare",
            COLORS.gold,
            full,
            true,
        );

        var top5 = stations.slice(0, 5);
        barChart(
            "stationTop5",
            top5.map(function (row) {
                return shortLabel(row.name, 16);
            }),
            top5.map(function (row) {
                return row.revenue;
            }),
            "Profit",
            COLORS.light,
            top5.map(function (row) {
                return row.name;
            }),
            true,
        );

        var distribution = stations.slice(0, 12);
        horizontalBarChart(
            "stationDistribution",
            distribution.map(function (row) {
                return shortLabel(row.name, 18);
            }),
            distribution.map(function (row) {
                return row.revenue;
            }),
            "Profit",
            COLORS.soft,
            distribution.map(function (row) {
                return row.name;
            }),
            true,
        );
    }

    function renderTime(payload) {
        var data = payload.data || {};

        var hourly = Array.isArray(data.hourly) ? data.hourly : [];
        if (hourly.length) {
            lineChart(
                "revenueByHour",
                hourly.map(function (row) {
                    return row.label;
                }),
                hourly.map(function (row) {
                    return row.revenue;
                }),
                "Profit",
                COLORS.soft,
            );
        } else {
            setCardEmpty("revenueByHour", true, "No hourly data available for this period.");
        }

        var dow = Array.isArray(data.dow) ? data.dow : [];
        if (dow.length) {
            lineChart(
                "revenueByDay",
                dow.map(function (row) {
                    return row.label;
                }),
                dow.map(function (row) {
                    return row.revenue;
                }),
                "Profit",
                COLORS.green,
            );
        } else {
            setCardEmpty("revenueByDay", true, "No profit data available for this period.");
        }

        var weekly = Array.isArray(data.weekly) ? data.weekly : [];
        if (weekly.length) {
            lineChart(
                "weeklyTrend",
                weekly.map(function (row) {
                    return row.label;
                }),
                weekly.map(function (row) {
                    return row.revenue;
                }),
                "Weekly Profit",
                COLORS.soft,
            );
        } else {
            setCardEmpty("weeklyTrend", true, "No profit data available for this period.");
        }

        var monthly = Array.isArray(data.monthly) ? data.monthly : [];
        if (monthly.length) {
            lineChart(
                "monthlyTrend",
                monthly.map(function (row) {
                    return row.label;
                }),
                monthly.map(function (row) {
                    return row.revenue;
                }),
                "Monthly Profit",
                COLORS.green,
            );
        } else {
            setCardEmpty("monthlyTrend", true, "No profit data available for this period.");
        }

        var peak = data.peak || null;
        if (hourly.length && peak) {
            barChart(
                "peakRevenueTrend",
                ["Morning", "Afternoon", "Evening"],
                [peak.morning || 0, peak.afternoon || 0, peak.evening || 0],
                "Profit",
                COLORS.soft,
                null,
                true,
            );
        } else {
            setCardEmpty("peakRevenueTrend", true, "No hourly data available for this period.");
        }

        if (data.highest_day) {
            barChart(
                "highestEarningDay",
                [data.highest_day.label],
                [data.highest_day.revenue],
                "Profit",
                COLORS.green,
                null,
                true,
            );
        } else {
            setCardEmpty("highestEarningDay", true, "No daily data available for this period.");
        }
    }

    function renderSection(tab, payload) {
        if (tab === "bus") {
            renderDrivers(payload);
        } else if (tab === "route") {
            renderRoute(payload);
        } else if (tab === "station") {
            renderLocation(payload);
        } else if (tab === "hour") {
            renderTime(payload);
        }
    }

    function loadTab(tab, force) {
        var section = SECTION_BY_TAB[tab];
        if (!section) {
            return Promise.resolve();
        }

        setSectionLoading(tab, true);
        return request(section, force)
            .then(function (payload) {
                setSectionLoading(tab, false);
                renderSection(tab, payload);
                state.rendered[signature() + "::" + tab] = true;
            })
            .catch(function (error) {
                setSectionLoading(tab, false);
                showSectionError(
                    tab,
                    error && error.message ? error.message : "Failed to load this section.",
                );
            });
    }

    function activateTab(tab) {
        if (!SECTION_BY_TAB[tab]) {
            tab = "bus";
        }
        state.activeTab = tab;

        document.querySelectorAll("#revenueTabs .revenue-tab-btn").forEach(function (btn) {
            btn.classList.toggle("active", btn.dataset.type === tab);
        });
        document.querySelectorAll(".ro-section").forEach(function (section) {
            section.classList.toggle("active", section.id === tab);
        });

        if (!state.rendered[signature() + "::" + tab]) {
            loadTab(tab, false);
        }
    }

    function syncUrl() {
        var params = new URLSearchParams();
        if (state.filters.period) {
            params.set("period", state.filters.period);
        }
        if (state.filters.period === "custom") {
            if (state.filters.from) {
                params.set("from", state.filters.from);
            }
            if (state.filters.to) {
                params.set("to", state.filters.to);
            }
        }
        var query = params.toString();
        window.history.replaceState(
            null,
            "",
            window.location.pathname + (query ? "?" + query : ""),
        );
    }

    function applyFilters() {
        syncUrl();
        loadKpis(false);
        loadTab(state.activeTab, false);
    }

    function readUrl() {}

    function bindControls() {
        document.addEventListener("financedatefilter:change", function (event) {
            var detail = event.detail || {};
            state.filters.period = detail.period || state.filters.period;
            state.filters.from = detail.from || "";
            state.filters.to = detail.to || "";
            applyFilters();
        });

        $("roRefresh").addEventListener("click", function () {
            state.cache = {};
            state.rendered = {};
            loadKpis(true);
            loadTab(state.activeTab, true);
        });

        $("roClear").addEventListener("click", function () {
            var defaultPeriodBtn = document.querySelector('.fin-period-btn[data-period="monthly"]');
            if (defaultPeriodBtn) defaultPeriodBtn.click();
            applyFilters();
        });
    }

    function bindTabs() {
        document.querySelectorAll("#revenueTabs .revenue-tab-btn").forEach(function (btn) {
            btn.addEventListener("click", function () {
                activateTab(btn.dataset.type);
            });
        });
    }

    function bindModal() {
        var modal = $("driverModal");
        if (!modal) {
            return;
        }
        $("driverViewAll").addEventListener("click", function () {
            modal.classList.add("open");
        });
        $("driverModalClose").addEventListener("click", function () {
            modal.classList.remove("open");
        });
        modal.addEventListener("click", function (event) {
            if (event.target === modal) {
                modal.classList.remove("open");
            }
        });
        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                modal.classList.remove("open");
            }
        });
    }

    function init() {
        bindControls();
        bindTabs();
        bindModal();
        loadKpis(false);
        activateTab(state.activeTab || "bus");
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
</script>

