/**
 * Regression test for the finance dashboard date filter plumbing.
 *
 * Runs the inline script of finance/views/dashboard/_header.php against a
 * miniature DOM and verifies that every dashboard loader is invoked with the
 * same query for all six periods (Today, Monthly, Quarterly, Semi-Annually,
 * Annually, Custom Range): the canonical period/from/to plus the legacy
 * range/fromDate/toDate/month/year fields the dashboard endpoints read.
 *
 * Run: node finance/tests/finance_dashboard_filter_test.cjs
 */
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const root = 'C:/Code2025/WebsiteRconnect';
const source = fs.readFileSync(
    path.join(root, 'finance/views/dashboard/_header.php'),
    'utf8',
);

const scriptStart = source.lastIndexOf('<script>');
const scriptEnd = source.lastIndexOf('</script>');
assert.ok(scriptStart > -1 && scriptEnd > scriptStart, 'header inline script is locatable');
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
            value: '',
            textContent: '',
            innerHTML: '',
            hidden: false,
            listeners: {},
            addEventListener(name, fn) {
                this.listeners[name] = fn;
            },
            querySelector() {
                return null;
            },
            classList: { add() {}, remove() {}, toggle() {} },
            setAttribute() {},
            getAttribute() {
                return null;
            },
        });
    }
    return elements.get(id);
}

const CONFIG = {
    selected: 'today',
    start: '2026-10-15',
    end: '2026-10-15',
    presets: {},
};

const panelListeners = {};
const configNode = { textContent: JSON.stringify(CONFIG) };
const panel = {
    querySelector(selector) {
        return selector === '[data-fin-config]' ? configNode : null;
    },
    addEventListener(name, fn) {
        panelListeners[name] = fn;
    },
    classList: { add() {}, remove() {}, toggle() {} },
    setAttribute() {},
    getAttribute() {
        return null;
    },
};

const calls = [];
const LOADERS = [
    'loadFinanceStats',
    'loadFinancialTrends',
    'loadFinancialVariance',
    'loadDriverStats',
    'loadDriverInsights',
    'loadFuelConsumption',
    'loadRemittanceStatus',
    'loadRecentRemittances',
];

const windowObj = {
    __financeFilterQuery: undefined,
    __setAiPageFilters() {},
    history: { replaceState() {} },
};
for (const name of LOADERS) {
    windowObj[name] = (query) => calls.push({ name, query: String(query || '') });
}

const context = {
    window: windowObj,
    document: {
        getElementById: element,
        querySelector: (selector) =>
            selector === '[data-fin-date-filter]' ? panel : null,
        querySelectorAll() {
            return [];
        },
        addEventListener() {},
    },
    URLSearchParams,
    setTimeout() {
        return 0;
    },
    console,
    Number,
    String,
    JSON,
};
vm.createContext(context);
vm.runInContext(js, context);

function dispatch(period, start, end) {
    const handler = panelListeners['financedatefilter:change'];
    assert.ok(typeof handler === 'function', 'header listens for filter changes');
    handler({ detail: { period, start, end } });
}

function lastCall(name) {
    const filtered = calls.filter((call) => call.name === name);
    return filtered.length ? filtered[filtered.length - 1].query : '';
}

function paramsOf(query) {
    return new URLSearchParams(query);
}

const PERIODS = [
    {
        period: 'today',
        start: '2026-10-15',
        end: '2026-10-15',
        legacyRange: 'today',
        expectMonth: '10',
        expectYear: '2026',
    },
    {
        period: 'monthly',
        start: '2026-10-01',
        end: '2026-10-31',
        legacyRange: 'month',
        expectMonth: '10',
        expectYear: '2026',
    },
    {
        period: 'quarterly',
        start: '2026-10-01',
        end: '2026-12-31',
        legacyRange: 'custom',
    },
    {
        period: 'semi_annually',
        start: '2026-07-01',
        end: '2026-12-31',
        legacyRange: 'custom',
    },
    {
        period: 'annually',
        start: '2026-01-01',
        end: '2026-12-31',
        legacyRange: 'year',
        expectYear: '2026',
    },
    {
        period: 'custom',
        start: '2026-09-05',
        end: '2026-09-18',
        legacyRange: 'custom',
    },
];

for (const scenario of PERIODS) {
    calls.length = 0;
    dispatch(scenario.period, scenario.start, scenario.end);

    for (const loader of LOADERS) {
        check(lastCall(loader) !== '', `${loader} runs for ${scenario.period}`);
    }

    const reference = paramsOf(lastCall('loadFinanceStats'));
    check(
        reference.get('period') === scenario.period,
        `canonical period is forwarded for ${scenario.period}`,
    );
    check(
        reference.get('from') === scenario.start && reference.get('to') === scenario.end,
        `canonical dates are forwarded for ${scenario.period}`,
    );
    check(
        reference.get('range') === scenario.legacyRange,
        `legacy range is forwarded for ${scenario.period}`,
    );

    if (scenario.legacyRange === 'custom') {
        check(
            reference.get('fromDate') === scenario.start &&
                reference.get('toDate') === scenario.end,
            `custom date fields are forwarded for ${scenario.period}`,
        );
    }
    if (scenario.expectMonth) {
        check(
            reference.get('month') === scenario.expectMonth,
            `month is forwarded for ${scenario.period}`,
        );
    }
    if (scenario.expectYear) {
        check(
            reference.get('year') === scenario.expectYear,
            `year is forwarded for ${scenario.period}`,
        );
    }

    for (const loader of LOADERS) {
        check(
            lastCall(loader) === lastCall('loadFinanceStats'),
            `${loader} receives the same query as the KPI fetch for ${scenario.period}`,
        );
    }
}

/* A later period must replace earlier state without stale legacy fields. */
dispatch('custom', '2026-09-05', '2026-09-18');
dispatch('monthly', '2026-10-01', '2026-10-31');
const monthlyAfterCustom = paramsOf(lastCall('loadFinanceStats'));
check(
    monthlyAfterCustom.get('range') === 'month' &&
        monthlyAfterCustom.get('month') === '10' &&
        monthlyAfterCustom.get('year') === '2026',
    'switching from custom to monthly replaces the legacy state',
);

console.log(`${checks} finance dashboard filter checks passed.`);
