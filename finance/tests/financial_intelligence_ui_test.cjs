const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');

const root = 'C:/Code2025/WebsiteRconnect';
const payload = JSON.parse(
    execFileSync('php', [path.join(root, 'finance/tests/financial_intelligence_ui_payload.php')], {
        cwd: root,
        maxBuffer: 64 * 1024 * 1024,
    }).toString()
);

const source = fs.readFileSync(path.join(root, 'finance/views/financial_intelligence/index.php'), 'utf8');
const scriptStart = source.lastIndexOf('<script>');
const scriptEnd = source.lastIndexOf('</script>');
assert.ok(scriptStart > -1 && scriptEnd > scriptStart, 'page inline script is locatable');
const js = source
    .slice(scriptStart + 8, scriptEnd)
    .replace(/<\?=.*?\?>/gs, 'null')
    .replace(/<\?php.*?\?>/gs, '');

let checks = 0;
function check(ok, message) {
    assert.ok(ok, message);
    checks++;
}

const elements = new Map();
function element(id) {
    if (!elements.has(id)) {
        elements.set(id, {
            id,
            hidden: false,
            disabled: false,
            value: '',
            textContent: '',
            innerHTML: '',
            className: '',
            style: {},
            classList: { add() {}, remove() {}, toggle() {} },
            listeners: {},
            addEventListener(name, fn) { this.listeners[name] = fn; },
            setAttribute() {},
            getContext() { return { __canvasId: id }; },
            querySelectorAll() { return []; },
            closest() { return null; },
        });
    }
    return elements.get(id);
}

const chartConfigs = [];
class Chart {
    constructor(canvas, config) {
        this.config = config;
        chartConfigs.push({ canvas, config });
    }
    destroy() {}
}

const requests = [];
let failNext = null;
let variantMode = false;
let partialMode = false;
let previousFalseMode = false;
let forecastUnavailableMode = false;
let delayMode = false;
const windowObj = {
    location: { search: '', pathname: '/finance/financial-intelligence' },
    history: { replaceState() {} },
    alert() {},
};

const buildData = (section, query) => {
    if (section === 'options') {
        return { periods: [], years: [], routes: [], buses: [], passenger_types: [], fare_routes: [] };
    }
    let data = payload[section];
    if (section === 'overview' && data) {
        data = JSON.parse(JSON.stringify(data));
        if (variantMode) {
            data.actual.collections = query.get('period') === 'today' ? 111 : 222;
        }
        if (previousFalseMode) {
            data.previous_available = false;
        }
        if (partialMode) {
            data.partial = true;
            data.source_errors = [{ source: 'passenger_trip_history', operation: 'read passenger_trip_history', message: 'unreadable' }];
        }
    }
    if (section === 'forecast' && data && forecastUnavailableMode) {
        data = JSON.parse(JSON.stringify(data));
        data.available = false;
        data.message = 'Prediction unavailable due to insufficient historical data.';
    }
    return data;
};

const context = {
    window: windowObj,
    document: {
        getElementById: element,
        querySelectorAll() { return []; },
        addEventListener() {},
    },
    Chart,
    fetch: async (url) => {
        const query = new URLSearchParams(String(url).split('?')[1]);
        const section = query.get('section');
        requests.push(query.toString());
        if (failNext && section === failNext) {
            failNext = null;
            return { ok: false, status: 500, text: async () => JSON.stringify({ success: false, message: 'Simulated service failure' }) };
        }
        if (delayMode && section === 'overview') {
            await new Promise((resolve) => setTimeout(resolve, query.get('period') === 'today' ? 80 : 10));
        }
        const data = buildData(section, query);
        return { ok: true, status: 200, text: async () => JSON.stringify({ success: true, section, period: payload.overview.period, data }) };
    },
    URLSearchParams,
    console,
    Promise,
    setTimeout,
    clearTimeout,
    Number,
    String,
    Math,
    Object,
    Array,
    JSON,
    isNaN,
    parseInt,
    parseFloat,
};
vm.createContext(context);
vm.runInContext(js, context);

(async () => {
    const settle = () => new Promise((resolve) => setTimeout(resolve, 25));
    await settle();

    /* Boot: options fetch then overview fetch through the real render path. */
    check(requests.some((url) => url.includes('section=options')), 'boot loads filter options');
    check(requests.some((url) => url.includes('section=overview')), 'boot renders the overview section');

    const api = windowObj.FinanceIntelligence;
    check(!!api, 'page exposes its render functions for verification');
    check(api.state.filters.period === 'today', 'initial state selects Today');
    check(element('fiPeriod').value === 'today', 'options loading selects Today in the control');
    check(requests.filter((query) => query.includes('section=overview')).every((query) => new URLSearchParams(query).get('period') === 'today'), 'initial overview requests use Today');

    const overviewKpis = element('fiOverviewKpis').innerHTML;
    check((overviewKpis.match(/class="fi-kpi"/g) || []).length === 4, 'overview renders exactly four priority KPI cards');
    check(overviewKpis.includes('\u20b1722.00'), 'overview KPI shows actual collections');
    check(
        overviewKpis.includes('Fare Collections') && overviewKpis.includes('Recorded Expenses') &&
        overviewKpis.includes('Estimated Operating Profit') && overviewKpis.includes('Completed Trips'),
        'the four priority KPI labels are present'
    );
    check(overviewKpis.includes('>Actual<') && overviewKpis.includes('>Estimate<'), 'KPI cards label actual and estimated values');
    check(overviewKpis.includes('Baggage'), 'overview KPI notes baggage as a separate category');
    check(overviewKpis.includes('configured estimate'), 'estimated profit distinguishes configured estimates');
    check(overviewKpis.includes('-\u20b13,396.42'), 'overview KPI shows the estimated operating profit');
    check(overviewKpis.includes('vs '), 'KPI footers state the period each value is compared against');

    /* KPI footers spell out the compared period pair without repeating it. */
    const yearlyOverview = JSON.parse(JSON.stringify(payload.overview));
    yearlyOverview.period.label = '2026 vs 2025';
    yearlyOverview.period.from = '2026-01-01';
    yearlyOverview.period.to = '2026-12-31';
    yearlyOverview.period.previous_from = '2025-01-01';
    yearlyOverview.period.previous_to = '2025-12-31';
    yearlyOverview.previous_available = true;
    api.renderOverview(yearlyOverview);
    const yearlyKpis = element('fiOverviewKpis').innerHTML;
    check((yearlyKpis.match(/2026 vs 2025/g) || []).length === 4, 'Yearly KPI footers show the year pair on every card');
    check(!yearlyKpis.includes('vs 2025 \u00b7 vs 2025'), 'Yearly KPI footers do not repeat the comparison');
    api.renderOverview(payload.overview);

    /* Compact Cash vs QRPh payment breakdown from the recorded methods. */
    check(element('fiPaymentSub').textContent.includes('722.00'), 'payment sub-caption reconciles with fare collections');
    const paymentBreakdown = element('fiPaymentBreakdown').innerHTML;
    check(paymentBreakdown.includes('Cash'), 'payment breakdown lists Cash');
    check(paymentBreakdown.includes('QRPh'), 'payment breakdown labels online collections as QRPh');
    check(paymentBreakdown.includes('fi-paymix-bar'), 'payment breakdown renders compact share bars');
    check(paymentBreakdown.includes('fi-paymix-icon'), 'payment breakdown shows method icon chips');
    check(paymentBreakdown.includes('fi-paymix-total'), 'payment breakdown states the recorded total');

    /* Route performance table. */
    check(element('fiOverviewRoutes').innerHTML.includes('Cabanatuan - Rizal'), 'overview route table lists routes');
    check(
        element('fiOverviewRoutes').innerHTML.includes('Trips') &&
        element('fiOverviewRoutes').innerHTML.includes('Collections') &&
        element('fiOverviewRoutes').innerHTML.includes('Expenses') &&
        element('fiOverviewRoutes').innerHTML.includes('Estimated profit'),
        'route table exposes trips, collections, expenses and estimated profit'
    );
    check(element('fiRouteMeta').innerHTML.includes('route(s)'), 'route performance states the route count summary');
    check(element('fiCompletenessBadge').innerHTML.includes('Actual records'), 'complete records show the actual badge');

    /* The overview keeps only the Collections vs Expenses chart. */
    check(chartConfigs.some((c) => c.canvas.__canvasId === 'fiChartTrend'), 'collections versus expenses chart renders');

    /* Scope limitation notes are rendered visibly. */
    const scopedPayload = JSON.parse(JSON.stringify(payload.overview));
    scopedPayload.scope = {
        filters: { passenger_type: 'Student' },
        not_applicable: [{ filter: 'passenger_type', value: 'Student', message: 'The passenger-type filter applies to fare revenue and passenger metrics; recorded expenses are not passenger-specific and remain in full.' }]
    };
    api.renderOverview(scopedPayload);
    check(element('fiScopeNotes').innerHTML.includes('not passenger-specific'), 'scope limitation notes are rendered for filtered requests');

    /* Incomplete route costs are flagged in the route table. */
    const partialRoutePayload = JSON.parse(JSON.stringify(payload.overview));
    partialRoutePayload.by_route = [{ route: 'Test Route', trips: 2, passengers: 5, collections: 100, expenses: 0, profit: 0, margin: null, cost_complete: false }];
    api.renderOverview(partialRoutePayload);
    check(element('fiOverviewRoutes').innerHTML.includes('incomplete costs'), 'incomplete route cost records are flagged in the route table');

    /* Missing previous windows render explicit unavailable states. */
    const noPreviousPayload = JSON.parse(JSON.stringify(payload.overview));
    noPreviousPayload.previous = null;
    noPreviousPayload.previous_available = false;
    noPreviousPayload.change = null;
    api.renderOverview(noPreviousPayload);
    check(element('fiOverviewKpis').innerHTML.includes('No previous data'), 'missing previous windows show an explicit unavailable message');

    /* Empty selections produce themed empty states and no chart configs. */
    const emptyPayload = JSON.parse(JSON.stringify(payload.overview));
    emptyPayload.series = { daily: [], monthly: [] };
    emptyPayload.payment_methods = [];
    emptyPayload.previous = null;
    emptyPayload.previous_available = false;
    emptyPayload.change = null;
    const chartsBefore = chartConfigs.length;
    api.renderOverview(emptyPayload);
    check(element('fiPaymentBreakdown').innerHTML.includes('No collections by payment method'), 'empty payment mix shows a themed empty state');
    check(element('fiChartTrendEmpty').hidden === false, 'empty trend chart shows an empty state');
    check(
        !chartConfigs.slice(chartsBefore).some((c) => ['fiChartTrend'].includes(c.canvas.__canvasId)),
        'no chart configs are drawn for empty series'
    );
    api.renderOverview(payload.overview);

    /* Estimated badge when records are incomplete. */
    const estimatedPayload = JSON.parse(JSON.stringify(payload.overview));
    estimatedPayload.expense_completeness = { estimated: true, has_expense_records: false, has_fuel_records: false, notes: ['No expense records'] };
    api.renderOverview(estimatedPayload);
    check(element('fiCompletenessBadge').innerHTML.includes('Estimated'), 'incomplete records show the estimated badge');

    /* Passenger types. */
    api.renderPassengers(payload.passengers);
    check(element('fiPassengerKpis').innerHTML.includes('74.68'), 'passenger KPIs show the estimated discount impact');
    check(element('fiPassengerTable').innerHTML.includes('Senior Citizen'), 'passenger table lists supported categories');
    check(element('fiPassengerTable').innerHTML.includes('Baggage &amp; extra fares'), 'passenger table keeps baggage separate');
    check(chartConfigs.some((c) => c.canvas.__canvasId === 'fiChartPaxAvgFare'), 'average realized fare chart renders');

    /* Fare matrix. */
    api.renderFare(payload.fare);
    check(element('fiFareKpis').innerHTML.includes('100.0%'), 'fare KPIs show matrix coverage');
    check(element('fiFareSegments').innerHTML.includes('Cabanatuan - Kalikid'), 'fare table lists matrix segments');
    check(element('fiFareAppliedTable').innerHTML.includes('Cabanatuan - Rizal'), 'matrix alignment table lists matched routes');
    check(element('fiFareAppliedTable').innerHTML.includes('data-sort'), 'matrix alignment table is sortable');
    check(element('fiFareChangeTracking').innerHTML.includes('Average realized fare change'), 'fare change tracking renders before/after');

    /* Fuel. */
    api.renderFuel(payload.fuel);
    check(element('fiFuelKpis').innerHTML.includes('1,200.00'), 'fuel KPIs show purchase cost');
    check(element('fiFuelKpis').innerHTML.includes('3.00'), 'fuel KPIs show cost per kilometer from the diesel price and measured km/L');
    check(element('fiFuelKpis').innerHTML.includes('15.00 / L') && element('fiFuelKpis').innerHTML.includes('5.00 km/L'), 'cost per kilometer states its diesel price and measured km/L');
    check(!element('fiFuelKpis').innerHTML.includes('trips measured'), 'per-trip cost KPI is removed');
    check(element('fiFuelBusTable').innerHTML.includes('Bus B1') || element('fiFuelBusTable').innerHTML.includes('B1'), 'fuel bus table lists buses');
    check(chartConfigs.some((c) => c.canvas.__canvasId === 'fiChartFuelCostKm'), 'monthly fuel cost per km chart renders');

    /* Routes and fleet. */
    api.renderFleet(payload.fleet);
    check(element('fiFleetRoutesTable').innerHTML.includes('Cabanatuan - Rizal'), 'fleet route table lists routes');
    check(element('fiFleetKpis').innerHTML.includes('Cabanatuan - Rizal'), 'fleet KPIs name the highest-cost route');
    check(chartConfigs.some((c) => c.canvas.__canvasId === 'fiChartFleetCostMix'), 'route cost composition chart renders');

    /* Remittance. */
    api.renderRemittance(payload.remittance);
    check(element('fiRemittanceKpis').innerHTML.includes('\u20b130.00'), 'remittance KPIs show the shortage');
    check(element('fiRemitCases').innerHTML.includes('DIS-00001'), 'discrepancy table lists open cases');

    /* Forecast. */
    api.renderForecast(payload.forecast);
    check(element('fiForecastKpis').innerHTML.includes('5,163.55'), 'forecast KPIs show the estimated operating profit');
    check(element('fiForecastAssumptions').innerHTML.includes('average realized fare'), 'forecast assumptions are rendered');
    check(element('fiForecastDisclaimer').textContent.includes('not actual results'), 'forecast disclaimer is rendered');

    /* What-if. */
    api.renderWhatIf(payload.whatif);
    check(element('fiWhatIfKpis').innerHTML.includes('-\u20b13,499.51'), 'what-if KPIs show the projected profit after configured costs');
    api.renderWhatIf(payload.whatif_route);
    check(element('fiWhatIfRoutes').innerHTML.includes('Cabanatuan - Rizal'), 'what-if route table renders');
    check(element('fiWhatIfRoutes').innerHTML.includes('432.00'), 'what-if route table shows the route-specific projection');

    /* Sortable table interaction on the real table state. */
    api.renderTable('fiFleetRoutesTable');
    check(element('fiFleetRoutesTable').innerHTML.includes('data-sort'), 'tables expose sortable headers');

    /* Automatic filters: a control change re-applies after a short debounce. */
    const settleFilters = () => new Promise((resolve) => setTimeout(resolve, 450));

    element('fiPeriod').value = 'monthly';
    element('fiPeriod').listeners.change();
    await settleFilters();
    check(api.state.filters.period === 'monthly', 'explicit Monthly overrides Today');
    check(new URLSearchParams(requests.at(-1)).get('period') === 'monthly', 'the automatic refresh requests the explicit Monthly period');
    check(element('fiReset').disabled === false, 'Reset is re-enabled after the request completes');

    /* Period-dependent controls show and hide consistently. */
    element('fiPeriod').value = 'quarter';
    element('fiPeriod').listeners.change();
    check(element('fiYearField').hidden === false && element('fiQuarterField').hidden === false, 'quarter reveals the year and quarter controls');
    element('fiPeriod').value = 'custom';
    element('fiPeriod').listeners.change();
    check(element('fiFromField').hidden === false && element('fiToField').hidden === false, 'custom reveals the date inputs');
    element('fiPeriod').value = 'today';
    element('fiPeriod').listeners.change();
    check(element('fiYearField').hidden === true && element('fiFromField').hidden === true, 'today hides the year and date controls');
    await settleFilters();

    /* Rapid changes collapse into a single request carrying the final set. */
    const requestsBeforeRapid = requests.length;
    element('fiPeriod').value = 'monthly';
    element('fiPeriod').listeners.change();
    element('fiRoute').value = 'Cabanatuan - Rizal';
    element('fiRoute').listeners.change();
    element('fiBus').value = 'B1';
    element('fiBus').listeners.change();
    element('fiPassengerType').value = 'Student';
    element('fiPassengerType').listeners.change();
    await settleFilters();
    const rapidQueries = requests.slice(requestsBeforeRapid);
    check(rapidQueries.length === 1, 'rapid filter changes collapse into one request');
    const lastQuery = new URLSearchParams(rapidQueries[0]);
    check(lastQuery.get('period') === 'monthly', 'the collapsed request keeps the explicit period');
    check(lastQuery.get('route') === 'Cabanatuan - Rizal', 'the collapsed request carries the selected route');
    check(lastQuery.get('bus') === 'B1', 'the collapsed request carries the selected bus');
    check(lastQuery.get('passenger_type') === 'Student', 'the collapsed request carries the selected passenger type');

    /* Reset restores the defaults and reapplies them. */
    element('fiReset').listeners.click();
    check(api.state.filters.period === 'today', 'Reset restores Today');
    check(api.state.filters.route === '' && api.state.filters.bus === '' && api.state.filters.passenger_type === '', 'Reset clears route, bus and passenger filters');
    await settle();
    const resetQuery = new URLSearchParams(requests.at(-1));
    check(resetQuery.get('period') === 'today', 'Reset reapplies the default period');
    check(!resetQuery.get('route') && !resetQuery.get('bus') && !resetQuery.get('passenger_type'), 'Reset drops the previous filter values from the request');

    /* An invalid custom range is refused inline without a request. */
    const requestsBeforeCustom = requests.length;
    element('fiPeriod').value = 'custom';
    element('fiPeriod').listeners.change();
    await settleFilters();
    check(requests.length === requestsBeforeCustom, 'an incomplete custom range is not requested');
    check(element('fiFilterError').style.display === 'block', 'an incomplete custom range shows an inline error');
    check(element('fiFilterError').textContent.includes('custom start'), 'the inline error names the missing custom dates');
    element('fiPeriod').value = 'today';
    element('fiPeriod').listeners.change();
    await settleFilters();

    /* A failed request shows a readable error with a Retry that reloads it. */
    failNext = 'passengers';
    api.retrySection('passengers');
    await settle();
    check(element('fiPassengersStatus').innerHTML.includes('Simulated service failure'), 'failed requests show the service message');
    check(element('fiPassengersStatus').innerHTML.includes('data-retry="passengers"'), 'failed requests offer a Retry');
    check(element('fiPassengersBody').hidden === true, 'failed requests hide stale section data');
    check(element('fiPeriodSummary').innerHTML.includes('could not be loaded'), 'the range summary does not claim success after a failure');

    api.retrySection('passengers');
    await settle();
    check(element('fiPassengersStatus').innerHTML === '', 'retry clears the error');
    check(element('fiPassengersBody').hidden === false, 'retry renders the section data');
    check(element('fiPassengerKpis').innerHTML.includes('74.68'), 'retry renders the passenger KPIs');

    /* An older response cannot overwrite a newer filter selection. */
    variantMode = true;
    delayMode = true;
    api.state.filters.period = 'today';
    api.retrySection('overview');
    api.state.filters.period = 'monthly';
    api.retrySection('overview');
    await settle();
    check(element('fiOverviewKpis').innerHTML.includes('\u20b1222.00'), 'the newer selection renders while an older request is still in flight');
    check(element('fiOverviewStatus').innerHTML === '', 'loading ends when the owning request settles, not when a stale one does');
    await new Promise((resolve) => setTimeout(resolve, 120));
    check(element('fiOverviewKpis').innerHTML.includes('\u20b1222.00'), 'a superseded response cannot overwrite the newer results');
    check(!element('fiOverviewKpis').innerHTML.includes('\u20b1111.00'), 'the stale response never reaches the DOM');
    check(element('fiOverviewBody').hidden === false, 'the settled render leaves the section body visible');
    variantMode = false;
    delayMode = false;

    /* previousAvailable is honored where the payload carries it. */
    previousFalseMode = true;
    api.retrySection('overview');
    await settle();
    check(element('fiPeriodSummary').innerHTML.includes('no comparable previous window'), 'an explicitly unavailable comparison shows its disclosure');
    previousFalseMode = false;
    api.activateTab('passengers');
    await settle();
    check(!element('fiPeriodSummary').innerHTML.includes('no comparable previous window'), 'a section without a comparison flag does not claim one');
    check(new URLSearchParams(requests.at(-1)).get('period') === 'monthly', 'an inactive tab loads with the current filters when opened');

    /* Partial payloads are disclosed instead of looking empty. */
    partialMode = true;
    api.retrySection('overview');
    await settle();
    check(element('fiOverviewStatus').innerHTML.includes('fi-partial-note'), 'partial data renders a partial indicator');
    check(element('fiOverviewStatus').innerHTML.includes('passenger_trip_history'), 'the partial indicator names the failed source');
    check(element('fiOverviewBody').hidden === false, 'partial data still shows the section body');
    partialMode = false;

    /* Forecast unavailability is a data notice, not an empty state. */
    forecastUnavailableMode = true;
    api.activateTab('forecast');
    await settle();
    check(element('fiForecastStatus').innerHTML.includes('fi-notice'), 'forecast unavailability renders as a data notice');
    check(element('fiForecastStatus').innerHTML.includes('insufficient historical data'), 'the notice explains the data limitation');
    check(element('fiForecastBody').hidden === true, 'the forecast body stays hidden when unavailable');
    forecastUnavailableMode = false;

    /* What-If projections carry the estimate indicator. */
    api.retrySection('whatif');
    await settle();
    check(element('fiWhatIfKpis').innerHTML.includes('fi-kpi-tag estimate'), 'what-if projections are labelled as estimates');

    /* Filter changes refresh the active tab with the new values. */
    api.activateTab('overview');
    await settle();
    const requestsBeforeYearly = requests.length;
    element('fiPeriod').value = 'yearly';
    element('fiPeriod').listeners.change();
    await settleFilters();
    const yearlyQueries = requests.slice(requestsBeforeYearly);
    check(
        yearlyQueries.some((query) => query.includes('section=overview') && new URLSearchParams(query).get('period') === 'yearly'),
        'changing filters immediately refreshes the active tab with the new values'
    );

    /* An invalid range states that nothing was applied. */
    element('fiPeriod').value = 'custom';
    element('fiPeriod').listeners.change();
    element('fiFrom').value = '';
    element('fiTo').value = '';
    await settleFilters();
    check(element('fiPeriodSummary').innerHTML.includes('Filters were not applied'), 'an invalid range states that the filters were not applied');

    console.log(`${checks} financial intelligence UI checks passed: real service payloads render into KPIs, tables, and charts.`);
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});

