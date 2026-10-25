<?php if (!defined('APP_LOADED')) exit; ?>

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
    gap: 14px;
    flex-wrap: nowrap;
    margin-bottom: 20px;
}
.ro-header-title {
    flex: 0 1 auto;
    min-width: 0;
}
.ro-title {
    font-weight: 800;
    font-size: 1.45rem;
    color: #295738;
    margin: 0;
    line-height: 1.1;
    font-family: "Poppins", sans-serif;
    letter-spacing: -0.01em;
}
.ro-subtitle {
    font-weight: 500;
    font-size: 0.82rem;
    color: var(--ro-muted);
    margin: 3px 0 0;
    line-height: 1.3;
    max-width: 420px;
}
.ro-toolbar {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: flex-end;
    flex: 0 1 auto;
    min-width: 0;
    background: #fffdf2;
    border: 1.5px solid var(--ro-border);
    border-radius: 14px;
    padding: 5px;
    box-shadow: var(--ro-shadow);
}
.ro-filter-group {
    display: flex;
    align-items: center;
    gap: 2px;
    background: var(--ro-cream-2);
    border: 1px solid var(--ro-border);
    border-radius: 10px;
    padding: 0 3px;
    height: 32px;
    min-width: 0;
}
.ro-divider {
    width: 1px;
    height: 16px;
    background: #ddd9ae;
    margin: 0 3px;
    flex: 0 0 auto;
}
.ro-control {
    height: 30px;
    border: none;
    border-radius: 8px;
    background: transparent;
    padding: 0 8px;
    font-size: 0.73rem;
    font-weight: 600;
    color: #295738;
    outline: none;
    font-family: "Poppins", sans-serif;
    box-sizing: border-box;
    transition:
        color 0.2s,
        box-shadow 0.2s;
}
.ro-control:hover {
    color: #0d6b3f;
}
.ro-control:focus {
    box-shadow: 0 0 0 2px rgba(25, 135, 84, 0.16);
}
select.ro-control {
    appearance: none;
    -webkit-appearance: none;
    padding-right: 26px;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23295738'%3E%3Cpath d='M8 11L3 6h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    background-size: 9px;
    cursor: pointer;
}
.ro-control-date {
    width: 110px;
    padding: 0 2px;
    font-size: 0.69rem;
}
.ro-dates {
    display: flex;
    align-items: center;
    gap: 2px;
    height: 30px;
}
.ro-dash {
    color: var(--ro-muted);
    font-size: 0.66rem;
}
.ro-control-sm,
.ro-control-md {
    height: 32px;
    background: var(--ro-cream-2);
    border: 1px solid var(--ro-border);
    border-radius: 10px;
    text-align: center;
}
.ro-control-sm {
    width: 78px;
}
.ro-control-md {
    width: 102px;
}
.ro-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    border: 1px solid var(--ro-border);
    background: var(--ro-cream-2);
    color: #295738;
    cursor: pointer;
    font-size: 0.82rem;
    padding: 0;
    transition: 0.2s;
    flex: 0 0 auto;
    font-family: "Poppins", sans-serif;
}
.ro-icon-btn:hover {
    background: #eef0d8;
    color: var(--ro-green-2);
    border-color: #d8d39a;
}
.ro-icon-btn:focus-visible {
    outline: none;
    box-shadow: 0 0 0 2px rgba(25, 135, 84, 0.2);
}

.ro-kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}
.ro-kpi {
    background: linear-gradient(180deg, #fffdf2 0%, var(--ro-cream) 100%);
    border: 2px solid var(--ro-border);
    border-radius: 16px;
    padding: 15px 16px;
    position: relative;
    transition:
        transform 0.22s,
        box-shadow 0.22s;
    box-shadow: var(--ro-shadow);
}
.ro-kpi:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 22px rgba(41, 87, 56, 0.1);
}
.ro-kpi-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}
.ro-kpi-head h6 {
    font-size: 0.68rem;
    color: var(--ro-green);
    font-weight: 700;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    line-height: 1.3;
}
.ro-kpi-icon-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 12px;
    background: #eef0d8;
    border: 1px solid var(--ro-border);
    flex: 0 0 auto;
}
.ro-kpi-icon {
    width: 19px;
    height: 19px;
    object-fit: contain;
}
.ro-kpi-value {
    font-size: 1.55rem;
    font-weight: 800;
    color: var(--ro-ink);
    margin: 10px 0 0;
    line-height: 1.1;
    letter-spacing: -0.01em;
}
.ro-kpi-value-sm {
    font-size: 1.08rem;
}
.ro-kpi-desc {
    font-size: 0.69rem;
    color: var(--ro-muted);
    margin-top: 7px;
    font-weight: 500;
    line-height: 1.35;
    min-height: 2.6em;
}
.ro-kpi-foot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
}
.ro-period {
    font-size: 0.64rem;
    font-weight: 700;
    color: #6b7c6b;
    background: #eef0d8;
    border-radius: 999px;
    padding: 3px 9px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 68%;
}
.ro-trend {
    font-size: 0.66rem;
    font-weight: 700;
    color: var(--ro-muted);
    white-space: nowrap;
    border-radius: 999px;
    padding: 3px 8px;
    background: #f0efd0;
}
.ro-trend:empty {
    padding: 0;
    background: none;
}
.ro-trend.up {
    color: #1e7e34;
    background: #e6f4ea;
}
.ro-trend.down {
    color: var(--ro-red);
    background: #fdecea;
}
.ro-trend.flat {
    color: #6b7c6b;
    background: #f0efd0;
}
.ro-kpi.is-loading .ro-kpi-value {
    color: transparent;
    background: linear-gradient(90deg, #f0efd0, #e7e3af, #f0efd0);
    background-size: 200% 100%;
    border-radius: 8px;
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

@media (max-width: 1366px) {
    .ro-subtitle {
        max-width: 320px;
    }
    .ro-control-date {
        width: 102px;
    }
    .ro-control-sm {
        width: 72px;
    }
    .ro-control-md {
        width: 94px;
    }
}
@media (max-width: 1100px) {
    .ro-control-date {
        width: 96px;
    }
    .ro-control-sm {
        width: 66px;
    }
    .ro-control-md {
        width: 86px;
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
    .ro-filter-group {
        flex: 1 1 auto;
        justify-content: space-between;
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
    var PERIOD_LABELS = {
        all: "All Time",
        today: "Today",
        this_week: "This Week",
        this_month: "This Month",
        this_year: "This Year",
        custom: "Custom Range",
    };
    var COLORS = {
        green: "#05590c",
        soft: "#198754",
        light: "#81c784",
        gold: "#d9a441",
        red: "#c62828",
    };

    var state = {
        filters: { period: "all", from: "", to: "", bus: "", route: "" },
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
        return [f.period, dates, String(f.bus).trim(), String(f.route).trim()].join("|");
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
        if (String(state.filters.bus).trim()) {
            params.set("bus", String(state.filters.bus).trim());
        }
        if (String(state.filters.route).trim()) {
            params.set("route", String(state.filters.route).trim());
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
            data.peak_hour && data.peak_hour.label ? data.peak_hour.label : "—";

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
                setCardEmpty(id, true, "No revenue data available for this period.");
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
            "Revenue",
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
            "Revenue per Trip",
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
                        label: "Average Revenue per Trip",
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
                        label: "Revenue",
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
                "Revenue",
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
                "Average Revenue per Trip",
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
                setCardEmpty(id, true, "No revenue data available for this period.");
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
                "Revenue",
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
                "Revenue",
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
                setCardEmpty(id, true, "No revenue data available for this period.");
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
            "Revenue",
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
            "Revenue",
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
            "Revenue",
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
                "Revenue",
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
                "Revenue",
                COLORS.green,
            );
        } else {
            setCardEmpty("revenueByDay", true, "No revenue data available for this period.");
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
                "Weekly Revenue",
                COLORS.soft,
            );
        } else {
            setCardEmpty("weeklyTrend", true, "No revenue data available for this period.");
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
                "Monthly Revenue",
                COLORS.green,
            );
        } else {
            setCardEmpty("monthlyTrend", true, "No revenue data available for this period.");
        }

        var peak = data.peak || null;
        if (hourly.length && peak) {
            barChart(
                "peakRevenueTrend",
                ["Morning", "Afternoon", "Evening"],
                [peak.morning || 0, peak.afternoon || 0, peak.evening || 0],
                "Revenue",
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
                "Revenue",
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
        if (state.filters.period && state.filters.period !== "all") {
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
        if (String(state.filters.bus).trim()) {
            params.set("bus", String(state.filters.bus).trim());
        }
        if (String(state.filters.route).trim()) {
            params.set("route", String(state.filters.route).trim());
        }
        var query = params.toString();
        window.history.replaceState(
            null,
            "",
            window.location.pathname + (query ? "?" + query : ""),
        );
    }

    function applyFilters() {
        var period = $("roPeriod").value || "all";

        state.filters.period = period;
        if (period === "custom") {
            state.filters.from = $("roFrom").value || "";
            state.filters.to = $("roTo").value || "";
        } else {
            state.filters.from = "";
            state.filters.to = "";
            $("roFrom").value = "";
            $("roTo").value = "";
        }
        state.filters.bus = $("roBus").value || "";
        state.filters.route = $("roRoute").value || "";

        syncUrl();
        loadKpis(false);
        loadTab(state.activeTab, false);
    }

    function readUrl() {
        var params = new URLSearchParams(window.location.search);
        var period = params.get("period") || "all";
        var from = params.get("from") || "";
        var to = params.get("to") || "";

        if ((from || to) && period === "all") {
            period = "custom";
        }

        state.filters = {
            period: period,
            from: from,
            to: to,
            bus: params.get("bus") || "",
            route: params.get("route") || "",
        };
    }

    function syncControls() {
        var select = $("roPeriod");
        if (select) {
            select.value = state.filters.period;
            if (select.value !== state.filters.period) {
                select.value = "custom";
            }
        }
        if ($("roFrom")) {
            $("roFrom").value = state.filters.from;
        }
        if ($("roTo")) {
            $("roTo").value = state.filters.to;
        }
        if ($("roBus")) {
            $("roBus").value = state.filters.bus;
        }
        if ($("roRoute")) {
            $("roRoute").value = state.filters.route;
        }
    }

    function bindControls() {
        $("roPeriod").addEventListener("change", applyFilters);

        $("roFrom").addEventListener("change", function () {
            $("roPeriod").value = "custom";
            applyFilters();
        });
        $("roTo").addEventListener("change", function () {
            $("roPeriod").value = "custom";
            applyFilters();
        });

        var timer = null;
        function bindText(id) {
            var input = $(id);
            if (!input) {
                return;
            }
            input.addEventListener("input", function () {
                window.clearTimeout(timer);
                timer = window.setTimeout(applyFilters, 500);
            });
        }
        bindText("roBus");
        bindText("roRoute");

        $("roRefresh").addEventListener("click", function () {
            state.cache = {};
            state.rendered = {};
            loadKpis(true);
            loadTab(state.activeTab, true);
        });

        $("roClear").addEventListener("click", function () {
            $("roPeriod").value = "all";
            $("roFrom").value = "";
            $("roTo").value = "";
            $("roBus").value = "";
            $("roRoute").value = "";
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
        readUrl();
        syncControls();
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
