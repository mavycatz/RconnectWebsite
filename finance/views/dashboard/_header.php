<?php
if (!class_exists(\Finance\Services\DateFilterService::class)) {
    require_once __DIR__ . '/../../services/DateFilterService.php';
}

$financePeriodResolved = \Finance\Services\DateFilterService::normalizeRequest($_GET, null, \Finance\Services\DateFilterService::TODAY);
$financePeriodConfig = \Finance\Services\DateFilterService::config(
    $financePeriodResolved['period'],
    $financePeriodResolved['from'],
    $financePeriodResolved['to']
);

$financeLegacyRange = [
    \Finance\Services\DateFilterService::TODAY => 'today',
    \Finance\Services\DateFilterService::MONTHLY => 'month',
    \Finance\Services\DateFilterService::QUARTERLY => 'custom',
    \Finance\Services\DateFilterService::SEMI_ANNUALLY => 'custom',
    \Finance\Services\DateFilterService::ANNUALLY => 'year',
    \Finance\Services\DateFilterService::CUSTOM => 'custom',
][$financePeriodResolved['period']] ?? 'custom';

$financeUsesCustomDates = in_array($financePeriodResolved['period'], [
    \Finance\Services\DateFilterService::QUARTERLY,
    \Finance\Services\DateFilterService::SEMI_ANNUALLY,
    \Finance\Services\DateFilterService::CUSTOM,
], true);
?>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:18px;">

    <div>
        <h1 style="font-weight:800; font-size:1.4rem; color:#295738; margin:0 0 2px;">
            Dashboard
        </h1>
        <p style="font-weight:500; font-size:0.82rem; color:#8a9a8a; margin:0;">
            Overview of ongoing trips, bus routes, and performance metrics
        </p>
    </div>

    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">

        <?php require __DIR__ . '/../layout/_period_filter.php'; ?>

        <!-- Hidden legacy fields keep the existing dashboard panel loaders working.
             These are inputs (not single-option selects) so every period the
             filter panel can produce is representable after a change. -->
        <div style="display:none;" aria-hidden="true">
            <input type="hidden" id="filterRange" value="<?= htmlspecialchars($financeLegacyRange, ENT_QUOTES) ?>">
            <input type="hidden" id="filterMonth" value="<?= date('m') ?>">
            <input type="hidden" id="filterYear" value="<?= date('Y') ?>">
            <input type="hidden" id="filterFromDate"
                   value="<?= $financeUsesCustomDates ? htmlspecialchars($financePeriodResolved['start'], ENT_QUOTES) : '' ?>">
            <input type="hidden" id="filterToDate"
                   value="<?= $financeUsesCustomDates ? htmlspecialchars($financePeriodResolved['end'], ENT_QUOTES) : '' ?>">
        </div>

    </div>

</div>

<script>
(function () {
    "use strict";

    var panel = document.querySelector("[data-fin-date-filter]");
    if (!panel) {
        return;
    }

    var config = {};
    try {
        var configNode = panel.querySelector("[data-fin-config]");
        config = JSON.parse(configNode ? configNode.textContent || "{}" : "{}");
    } catch (e) {
        config = {};
    }

    var LEGACY_RANGE = {
        today: "today",
        monthly: "month",
        quarterly: "custom",
        semi_annually: "custom",
        annually: "year",
        custom: "custom",
    };

    var rangeEl = document.getElementById("filterRange");
    var monthEl = document.getElementById("filterMonth");
    var yearEl = document.getElementById("filterYear");
    var fromEl = document.getElementById("filterFromDate");
    var toEl = document.getElementById("filterToDate");

    function legacyState(detail) {
        var period = detail.period || "monthly";
        var start = detail.start || "";
        var end = detail.end || "";
        var state = {
            range: LEGACY_RANGE[period] || "custom",
            from: "",
            to: "",
            month: "",
            year: "",
        };

        if (period === "today") {
            state.from = start;
            state.to = end;
            state.month = start ? String(Number(start.slice(5, 7))) : "";
            state.year = start ? start.slice(0, 4) : "";
        } else if (period === "monthly") {
            state.month = start ? String(Number(start.slice(5, 7))) : "";
            state.year = start ? start.slice(0, 4) : "";
        } else if (period === "annually") {
            state.year = start ? start.slice(0, 4) : "";
        } else {
            state.from = start;
            state.to = end;
        }

        return state;
    }

    function buildQuery(detail, legacy) {
        var params = [];
        params.push("period=" + encodeURIComponent(detail.period || "monthly"));
        if (detail.start) {
            params.push("from=" + encodeURIComponent(detail.start));
        }
        if (detail.end) {
            params.push("to=" + encodeURIComponent(detail.end));
        }

        // Legacy-compatible fields for the Admin-backed dashboard panels.
        params.push("range=" + legacy.range);
        if (legacy.range === "today") {
            if (legacy.from) params.push("fromDate=" + legacy.from);
            if (legacy.to) params.push("toDate=" + legacy.to);
            if (legacy.month) params.push("month=" + legacy.month);
            if (legacy.year) params.push("year=" + legacy.year);
        } else if (legacy.range === "custom") {
            if (legacy.from) params.push("fromDate=" + legacy.from);
            if (legacy.to) params.push("toDate=" + legacy.to);
        } else if (legacy.range === "month") {
            if (legacy.month) params.push("month=" + legacy.month);
            if (legacy.year) params.push("year=" + legacy.year);
        } else if (legacy.range === "year") {
            if (legacy.year) params.push("year=" + legacy.year);
        }

        return params.join("&");
    }

    function applyDetail(detail, submit) {
        var legacy = legacyState(detail);

        if (rangeEl) {
            rangeEl.value = legacy.range;
        }
        if (monthEl && legacy.month) {
            monthEl.value = legacy.month;
        }
        if (yearEl && legacy.year) {
            yearEl.value = legacy.year;
        }
        if (fromEl) {
            fromEl.value = legacy.from;
        }
        if (toEl) {
            toEl.value = legacy.to;
        }

        var query = buildQuery(detail, legacy);
        window.__financeFilterQuery = query;
        window.__setAiPageFilters &&
            window.__setAiPageFilters({
                range: legacy.range,
                month: legacy.month,
                year: legacy.year,
                fromDate: legacy.from,
                toDate: legacy.to,
                period: detail.period,
            });

        if (!submit) {
            return;
        }

        if (typeof window.loadFinanceStats === "function") window.loadFinanceStats(query);
        if (typeof window.loadFinancialTrends === "function") window.loadFinancialTrends(query);
        if (typeof window.loadFinancialVariance === "function") window.loadFinancialVariance(query);
        if (typeof window.loadDriverStats === "function") window.loadDriverStats(query);
        if (typeof window.loadDriverInsights === "function") window.loadDriverInsights(query);
        if (typeof window.loadFuelConsumption === "function") window.loadFuelConsumption(query);
        if (typeof window.loadRemittanceStatus === "function") window.loadRemittanceStatus(query);
        if (typeof window.loadRecentRemittances === "function") window.loadRecentRemittances(query);
    }

    applyDetail(
        {
            period: config.selected || "monthly",
            start: config.start || "",
            end: config.end || "",
        },
        false,
    );

    panel.addEventListener("financedatefilter:change", function (event) {
        applyDetail(event.detail || {}, true);
    });

    setTimeout(function () {
        applyDetail(
            {
                period: config.selected || "monthly",
                start: config.start || "",
                end: config.end || "",
            },
            true,
        );
    }, 250);
})();
</script>
