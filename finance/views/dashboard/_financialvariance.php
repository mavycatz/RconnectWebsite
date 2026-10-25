<div class="fin-panel" id="financialVariancePanel">
    <div class="fin-panel-head">
        <div>
            <h2 class="fin-panel-title"><i class="bi bi-bar-chart-line"></i> Financial Variance</h2>
            <p class="fin-panel-sub" id="fvSub">Expected vs actual remittance and period-over-period movement</p>
        </div>
        <div class="fin-panel-meta">
            <span class="fin-pill" id="fvRangePill" hidden></span>
        </div>
    </div>

    <div class="fv-state" id="fvLoading">Loading variance data...</div>
    <div class="fv-state fv-state-error" id="fvError" hidden><i class="bi bi-exclamation-circle"></i>Couldn't load variance data. Select a period to retry.</div>
    <div class="fv-state" id="fvNoActivity" hidden><i class="bi bi-inbox"></i>No remittance activity for this period.</div>

    <div id="fvBody" hidden>
        <div class="fv-cash">
            <div class="fv-cash-row">
                <span class="fv-cash-label">Expected Remittance</span>
                <span class="fv-cash-value" id="fvExpected">&mdash;</span>
            </div>
            <div class="fv-cash-row">
                <span class="fv-cash-label">Actual Remittance</span>
                <span class="fv-cash-value" id="fvActual">&mdash;</span>
            </div>
            <div class="fv-cash-row is-variance">
                <span class="fv-cash-label">Variance</span>
                <span class="fv-cash-value" id="fvVariance">&mdash;</span>
            </div>
        </div>

        <div class="fv-block">
            <div class="fv-block-title">Completed vs Pending</div>
            <div class="fv-split">
                <div class="fv-mini">
                    <div class="fv-mini-label">Completed</div>
                    <div class="fv-mini-value" id="fvCompletedCount">0</div>
                    <div class="fv-mini-sub" id="fvCompletedAmount">&mdash;</div>
                </div>
                <div class="fv-mini">
                    <div class="fv-mini-label">Pending</div>
                    <div class="fv-mini-value" id="fvPendingCount">0</div>
                    <div class="fv-mini-sub" id="fvPendingAmount">&mdash;</div>
                </div>
            </div>
        </div>

        <div class="fv-block">
            <div class="fv-block-title">Current vs Previous</div>

            <div class="fv-compare">
                <div class="fv-compare-head">
                    <span class="fv-compare-name">Estimated Profit</span>
                    <span class="fv-compare-change" id="fvProfitChange"></span>
                </div>
                <div class="fv-bar-row">
                    <span class="fv-bar-label">Current</span>
                    <span class="fv-bar"><span class="fv-bar-fill" id="fvProfitCurrentBar"></span></span>
                    <span class="fv-bar-value" id="fvProfitCurrent">&mdash;</span>
                </div>
                <div class="fv-bar-row">
                    <span class="fv-bar-label">Previous</span>
                    <span class="fv-bar"><span class="fv-bar-fill is-previous" id="fvProfitPreviousBar"></span></span>
                    <span class="fv-bar-value" id="fvProfitPrevious">&mdash;</span>
                </div>
            </div>

            <div class="fv-compare">
                <div class="fv-compare-head">
                    <span class="fv-compare-name">Fuel Cost</span>
                    <span class="fv-compare-change" id="fvFuelChange"></span>
                </div>
                <div class="fv-bar-row">
                    <span class="fv-bar-label">Current</span>
                    <span class="fv-bar"><span class="fv-bar-fill" id="fvFuelCurrentBar"></span></span>
                    <span class="fv-bar-value" id="fvFuelCurrent">&mdash;</span>
                </div>
                <div class="fv-bar-row">
                    <span class="fv-bar-label">Previous</span>
                    <span class="fv-bar"><span class="fv-bar-fill is-previous" id="fvFuelPreviousBar"></span></span>
                    <span class="fv-bar-value" id="fvFuelPrevious">&mdash;</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#financialVariancePanel .fin-panel-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
#financialVariancePanel .fin-panel-title {
    font-size: 1rem;
    font-weight: 700;
    color: #2e4836;
    margin: 0 0 2px;
}
#financialVariancePanel .fin-panel-title i {
    color: #295738;
    margin-right: 6px;
}
#financialVariancePanel .fin-panel-sub {
    font-size: 0.72rem;
    color: #8a9389;
    margin: 0;
}
#financialVariancePanel .fin-panel-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
#financialVariancePanel .fin-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 999px;
    padding: 4px 12px;
    font-size: 0.7rem;
    font-weight: 700;
    background: #f9f4da;
    color: #2e4836;
    border: 1px solid #cdd9b5;
}
#financialVariancePanel .fv-state {
    padding: 18px;
    text-align: center;
    font-size: 0.78rem;
    color: #8a9389;
}
#financialVariancePanel .fv-state i {
    display: block;
    font-size: 1.4rem;
    margin-bottom: 6px;
    color: #b0b9a8;
}
#financialVariancePanel .fv-state-error {
    color: #b3261e;
}
#financialVariancePanel .fv-state-error i {
    color: #e0a7a2;
}

#financialVariancePanel .fv-cash {
    background: #fdfbf6;
    border: 1px solid #e3eabb;
    border-radius: 14px;
    padding: 12px 14px;
    margin-bottom: 12px;
}
#financialVariancePanel .fv-cash-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 6px 0;
    border-bottom: 1px dashed #eef0e6;
}
#financialVariancePanel .fv-cash-row:last-child {
    border-bottom: 0;
}
#financialVariancePanel .fv-cash-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: #8a9389;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
#financialVariancePanel .fv-cash-value {
    font-size: 0.95rem;
    font-weight: 800;
    color: #2e4836;
    font-variant-numeric: tabular-nums;
}
#financialVariancePanel .fv-cash-row.is-variance .fv-cash-value {
    font-size: 1.05rem;
}
#financialVariancePanel .fv-cash-value.is-not-available {
    font-size: 0.78rem;
    font-weight: 600;
    color: #a3ae9f;
}
#financialVariancePanel .fv-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border-radius: 999px;
    padding: 2px 9px;
    margin-left: 8px;
    font-size: 0.66rem;
    font-weight: 800;
    background: #f9f4da;
    color: #2e4836;
    border: 1px solid #cdd9b5;
}
#financialVariancePanel .fv-pill.is-up {
    color: #1c7c3f;
    border-color: #bfe3c6;
    background: #eef8f0;
}
#financialVariancePanel .fv-pill.is-down {
    color: #b3261e;
    border-color: #f0c8c4;
    background: #fdf1f0;
}

#financialVariancePanel .fv-block {
    margin-bottom: 12px;
}
#financialVariancePanel .fv-block:last-child {
    margin-bottom: 0;
}
#financialVariancePanel .fv-block-title {
    font-size: 0.68rem;
    font-weight: 800;
    color: #295738;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    margin-bottom: 8px;
}

#financialVariancePanel .fv-split {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}
#financialVariancePanel .fv-mini {
    background: #fdfbf6;
    border: 1px solid #e3eabb;
    border-radius: 12px;
    padding: 10px 12px;
}
#financialVariancePanel .fv-mini-label {
    font-size: 0.64rem;
    font-weight: 700;
    color: #8a9389;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
#financialVariancePanel .fv-mini-value {
    font-size: 1.15rem;
    font-weight: 800;
    color: #2e4836;
    line-height: 1.25;
}
#financialVariancePanel .fv-mini-sub {
    font-size: 0.74rem;
    font-weight: 600;
    color: #8a9389;
    font-variant-numeric: tabular-nums;
}

#financialVariancePanel .fv-compare {
    padding: 8px 0 2px;
}
#financialVariancePanel .fv-compare + .fv-compare {
    border-top: 1px dashed #eef0e6;
    margin-top: 6px;
}
#financialVariancePanel .fv-compare-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 6px;
}
#financialVariancePanel .fv-compare-name {
    font-size: 0.76rem;
    font-weight: 700;
    color: #2e4836;
}
#financialVariancePanel .fv-bar-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 5px;
}
#financialVariancePanel .fv-bar-label {
    flex: 0 0 58px;
    font-size: 0.64rem;
    font-weight: 700;
    color: #8a9389;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
#financialVariancePanel .fv-bar {
    flex: 1 1 auto;
    height: 10px;
    background: #f4f4ef;
    border-radius: 999px;
    overflow: hidden;
}
#financialVariancePanel .fv-bar-fill {
    display: block;
    height: 100%;
    width: 0;
    border-radius: 999px;
    background: #295738;
    transition: width 0.3s ease;
}
#financialVariancePanel .fv-bar-fill.is-previous {
    background: #cdd9b5;
}
#financialVariancePanel .fv-bar-value {
    flex: 0 0 auto;
    font-size: 0.72rem;
    font-weight: 700;
    color: #2e4836;
    font-variant-numeric: tabular-nums;
}
@media (max-width: 575px) {
    #financialVariancePanel .fv-split {
        grid-template-columns: 1fr;
    }
    #financialVariancePanel .fv-bar-label {
        flex-basis: 52px;
    }
}
</style>

<script>
(function () {
    "use strict";

    var requestSeq = 0;
    var NOT_AVAILABLE = "Not available";

    function money(value) {
        return (
            "\u20B1" +
            Number(value || 0).toLocaleString("en-US", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })
        );
    }

    function signedMoney(value) {
        var n = Number(value || 0);
        return (n > 0 ? "+" : n < 0 ? "-" : "") + money(Math.abs(n));
    }

    function pctChange(current, previous) {
        var c = Number(current || 0);
        var p = Number(previous || 0);
        if (!isFinite(c) || !isFinite(p) || Math.abs(p) < 0.00001) {
            return null;
        }
        return ((c - p) / Math.abs(p)) * 100;
    }

    function pill(percent, invert) {
        if (percent === null || percent === undefined) {
            return "";
        }
        var pct = Number(percent);
        if (!isFinite(pct)) {
            return "";
        }
        var up = pct >= 0;
        var cls = invert ? (up ? "is-down" : "is-up") : up ? "is-up" : "is-down";
        return (
            '<span class="fv-pill ' +
            cls +
            '">' +
            (up ? "\u2191" : "\u2193") +
            " " +
            Math.abs(pct).toFixed(1) +
            "% vs previous</span>"
        );
    }

    function setValue(id, html) {
        var el = document.getElementById(id);
        if (el) {
            el.innerHTML = html;
        }
    }

    function setCashValue(id, value) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        if (value === null || value === undefined) {
            el.textContent = NOT_AVAILABLE;
            el.classList.add("is-not-available");
            return;
        }
        el.textContent = money(value);
        el.classList.remove("is-not-available");
    }

    function setBars(currentBarId, previousBarId, current, previous) {
        var c = Math.abs(Number(current) || 0);
        var p = Math.abs(Number(previous) || 0);
        var max = Math.max(c, p, 1);
        var cEl = document.getElementById(currentBarId);
        var pEl = document.getElementById(previousBarId);
        if (cEl) {
            cEl.style.width = (c > 0 ? Math.max((c / max) * 100, 2) : 0) + "%";
        }
        if (pEl) {
            pEl.style.width = (p > 0 ? Math.max((p / max) * 100, 2) : 0) + "%";
        }
    }

    function showState(state) {
        var loading = document.getElementById("fvLoading");
        var error = document.getElementById("fvError");
        var empty = document.getElementById("fvNoActivity");
        var body = document.getElementById("fvBody");
        if (loading) {
            loading.hidden = state !== "loading";
        }
        if (error) {
            error.hidden = state !== "error";
        }
        if (empty) {
            empty.hidden = state !== "empty";
        }
        if (body) {
            body.hidden = state !== "ready";
        }
    }

    function render(data) {
        var expected = data.expected || {};
        var actual = data.actual || {};
        var variance = data.variance || {};
        var completed = data.completed || {};
        var pending = data.pending || {};
        var profit = data.profit || {};
        var discrepancy = data.discrepancy || {};
        var noRemit = data.no_remit || {};

        var activity =
            Number(completed.count || 0) +
            Number(pending.count || 0) +
            Number(discrepancy.count || 0) +
            Number(noRemit.count || 0);
        var hasCash =
            (expected.current !== null && expected.current !== undefined) ||
            (actual.current !== null && actual.current !== undefined);

        if (activity === 0 && !hasCash) {
            showState("empty");
            return;
        }

        setCashValue("fvExpected", expected.current);
        setCashValue("fvActual", actual.current);

        var varianceEl = document.getElementById("fvVariance");
        if (varianceEl) {
            if (variance.current === null || variance.current === undefined) {
                varianceEl.textContent = NOT_AVAILABLE;
                varianceEl.classList.add("is-not-available");
            } else {
                varianceEl.classList.remove("is-not-available");
                varianceEl.innerHTML = signedMoney(variance.current) + pill(variance.percent);
            }
        }

        setValue("fvCompletedCount", String(Number(completed.count || 0)));
        setValue("fvCompletedAmount", money(completed.amount));
        setValue("fvPendingCount", String(Number(pending.count || 0)));
        setValue("fvPendingAmount", money(pending.amount));

        setValue("fvProfitChange", pill(profit.percent));
        setValue("fvProfitCurrent", money(profit.current));
        setValue("fvProfitPrevious", money(profit.previous));
        setBars("fvProfitCurrentBar", "fvProfitPreviousBar", profit.current, profit.previous);

        setValue("fvFuelChange", pill(pctChange(profit.fuel, profit.previous_fuel), true));
        setValue("fvFuelCurrent", money(profit.fuel));
        setValue("fvFuelPrevious", money(profit.previous_fuel));
        setBars("fvFuelCurrentBar", "fvFuelPreviousBar", profit.fuel, profit.previous_fuel);

        var rangePill = document.getElementById("fvRangePill");
        if (rangePill && data.range && data.range.label) {
            rangePill.textContent = data.range.label;
            rangePill.hidden = false;
        } else if (rangePill) {
            rangePill.hidden = true;
        }

        showState("ready");
    }

    function loadFinancialVariance(query) {
        var requestId = ++requestSeq;

        showState("loading");
        if (window.__dashLoadBegin) window.__dashLoadBegin();

        fetch("/finance/api/dashboard/finance_summary.php?" + (query || ""), { cache: "no-store" })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                if (requestId !== requestSeq) {
                    return;
                }
                if (!data || !data.success) {
                    showState("error");
                    return;
                }
                render(data);
            })
            .catch(function () {
                if (requestId !== requestSeq) {
                    return;
                }
                showState("error");
            })
            .finally(function () {
                if (window.__dashLoadEnd) window.__dashLoadEnd();
            });
    }

    window.loadFinancialVariance = loadFinancialVariance;

    document.addEventListener("DOMContentLoaded", function () {
        if (!window.__financeFilterQuery) {
            loadFinancialVariance("range=year");
        }
    });
})();
</script>
