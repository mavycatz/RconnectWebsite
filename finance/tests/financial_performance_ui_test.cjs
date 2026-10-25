const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const fixture = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const nodes = new Map();
const element = id => {
    if (!nodes.has(id)) nodes.set(id, {
        id, hidden: false, disabled: false, value: '', textContent: '', innerHTML: '', listeners: {}, dataset: {},
        classList: {toggle() {}, contains(name) {return name === 'show' && !!nodes.get(id).shown;}}, setAttribute() {}, remove() {}, click() {},
        addEventListener(name, fn) { this.listeners[name] = fn; },
        insertAdjacentHTML(position, text) { this.innerHTML += text; }
    });
    return nodes.get(id);
};
const root = element('financialPerformance'); root.dataset.csrf = 'test-csrf';
const tabs = ['daily', 'weekly', 'monthly', 'yearly'].map(key => {
    const node = element(key); node.dataset.fprBucket = key; return node;
});
root.querySelectorAll = () => tabs;
const fields = {report_period: {value: 'custom'}, from: {value: '2026-10-01'}, to: {value: '2026-10-11'}, driver: {value: ''}, bus: {value: ''}};
element('financialFilter').elements = {namedItem: key => fields[key]};
element('fprBusSort').value = 'high';
element('fprGenerationPeriod').value = 'custom';
element('fprGenerationFrom').value = '2026-10-01';
element('fprGenerationTo').value = '2026-10-11';
element('reportType').value = 'full';
element('reportDescription').value = 'Fixture financial findings';
element('financialReportPreview').hidden = true;
element('fprResults').hidden = true;
let mode = 'normal', charts = [], requests = [], openedReport = null;
class Chart {
    constructor(canvas, definition) { charts.push(definition); }
    destroy() {}
    resize() {}
}
element('modalBackdrop').hidden = true;
const bootstrap = {Modal: {getOrCreateInstance: (node, options = {}) => ({
    show() {node.hidden = false; node.shown = true; element('modalBackdrop').hidden = options.backdrop === false; if (node.listeners['shown.bs.modal']) node.listeners['shown.bs.modal']();},
    hide() {node.hidden = true; node.shown = false; element('modalBackdrop').hidden = true; if (node.listeners['hidden.bs.modal']) node.listeners['hidden.bs.modal']();}
})}};
const jsonResponse = (data, ok = true, status = ok ? 200 : 503) => ({ok, status, text: async () => JSON.stringify(data)});
async function fetch(url, options = {}) {
    requests.push({url: String(url), options});
    const q = options.body ? options.body : new URLSearchParams(String(url).split('?')[1]);
    if (q.get('action') === 'generate') {
        assert.equal(element('financialReportPreview').hidden, false, 'Preview stays open while generating');
        assert.equal(element('modalBackdrop').hidden, false, 'Generating preview has a dark backdrop');
        assert.equal(element('fprResults').hidden, true, 'Charts remain deferred until generation completes');
        assert.equal(options.method, 'POST');
        assert.equal(q.get('_csrf_token'), 'test-csrf');
        assert.equal(q.get('snapshot'), 'server-owned-snapshot');
        assert.equal(q.get('report_type'), 'full');
        assert.equal(q.get('description'), 'Fixture financial findings');
        if (mode === 'generation-empty') return {ok: false, status: 502, text: async () => ''};
        if (mode === 'generation-network') throw new TypeError('Failed to fetch');
        return jsonResponse({success: true, report_id: 42, title: 'Generated Fixture Report', report: fixture.narrative});
    }
    if (q.get('action') === 'get_report') return jsonResponse({success: true, has_financial_snapshot: true, report: {title: 'Generated Fixture Report', report_data: fixture.narrative, generated_at: fixture.generated_at}});
    if (q.get('action') === 'pdf') {
        if (mode === 'pdf-json') return {...jsonResponse({success: false, message: 'PDF rendering requires PHP GD.'}), headers: {get: () => 'application/json'}};
        if (mode === 'pdf-html') return {ok: true, blob: async () => new Blob(['<html>Sign in</html>'])};
        if (mode === 'expired') return jsonResponse({success: false, message: 'Report preview expired'}, false, 409);
        return {ok: true, blob: async () => new Blob(['%PDF-test'])};
    }
    if (mode === 'error') return jsonResponse({success: false, message: 'Financial data unavailable'}, false);
    if (mode === 'invalid') return {ok: false, status: 502, text: async () => '<html>Gateway timeout</html>'};
    const data = structuredClone(fixture.test_category_previews && fixture.test_category_previews[mode] || fixture);
    if (mode === 'empty') {
        data.empty = true; data.records = [];
        for (const key of Object.keys(data.totals)) data.totals[key] = key === 'margin' ? null : 0;
        for (const key of Object.keys(data.breakdowns)) data.breakdowns[key] = [];
        for (const rows of Object.values(data.series)) rows.forEach(r => {r.revenue = r.expenses = r.profit = 0;});
        data.charts.forEach(c => c.datasets.forEach(d => {d.data = d.data.map(() => 0);}));
    }
    return jsonResponse({success: true, snapshot: 'server-owned-snapshot', data});
}
const context = {
    document: {getElementById: element, createElement: () => element('download'), body: {appendChild() {}}, querySelectorAll: () => [], querySelector: () => null},
    window: {}, fetch, Chart, URLSearchParams, URL: {createObjectURL: () => 'blob:test', revokeObjectURL() {}},
    AbortController, Blob, setTimeout, clearTimeout, console, bootstrap,
    cleanText: value => value, parseReportData: () => ({}), formatReportDate: value => value, showAlert() {},
    loadReportById: id => { openedReport = id; }, loadReportsList() {}, buildFilterQuery: () => ''
};
vm.createContext(context);
vm.runInContext(fs.readFileSync('finance/assets/js/financialperformance.js', 'utf8'), context);
const actionsSource = fs.readFileSync('finance/views/financialreports/_financial_actions.php', 'utf8');
vm.runInContext(actionsSource.slice(actionsSource.indexOf("document.getElementById('fprGenerationPeriod')"), actionsSource.lastIndexOf('</script>')), context);
const contentsSource = fs.readFileSync('finance/views/financialreports/_contents.php', 'utf8');
vm.runInContext('let currentReportText="",currentReportId=0,currentReportParsed=null,currentReportHasSnapshot=false;\n' + contentsSource.slice(contentsSource.indexOf('async function loadReportById('), contentsSource.indexOf('function renderReportChart()')), context);
const realSelectReport = context.loadReportById;
context.loadReportById = (id, openPreview, suppliedReport) => {openedReport = id; return realSelectReport(id, openPreview, suppliedReport);};
async function settle() {for (let i = 0; i < 8; i++) await new Promise(resolve => setImmediate(resolve));}
// Exercise the actual scoped backdrop lifecycle independently of Bootstrap's mock.
const modalEvents = {}, bodyClasses = new Set();
vm.runInNewContext(fs.readFileSync('finance/assets/js/financialreportmodals.js', 'utf8'), {
    document: {
        addEventListener: (name, callback) => {modalEvents[name] = callback;},
        body: {classList: {add: name => bodyClasses.add(name), remove: name => bodyClasses.delete(name)}}
    }
});
const modalTarget = financial => ({classList: {contains: name => financial && name === 'fin-report-modal'}});
const importModal = modalTarget(true), expenseModal = modalTarget(true), otherModal = modalTarget(false);
modalEvents['show.bs.modal']({target: otherModal});
assert.equal(bodyClasses.size, 0, 'Unrelated modals do not receive financial backdrop styling');
modalEvents['show.bs.modal']({target: importModal});
assert.ok(bodyClasses.has('fin-reports-modal-open'), 'Import enables the softer backdrop');
modalEvents['show.bs.modal']({target: expenseModal});
modalEvents['hidden.bs.modal']({target: importModal});
assert.ok(bodyClasses.has('fin-reports-modal-open'), 'A modal handoff retains backdrop styling');
modalEvents['hidden.bs.modal']({target: expenseModal});
assert.equal(bodyClasses.size, 0, 'Closing the final financial modal clears backdrop styling');
(async () => {
    await settle();
    assert.equal(requests.length, 0, 'Page load must not request financial analytics');
    assert.equal(charts.length, 0, 'Page load must not create any charts');
    assert.equal(element('financialReportPreview').hidden, true);
    await context.loadReportById(42, false); await settle();
    assert.match(element('financialReportContent').innerHTML, /Fixture financial findings/, 'Initial saved-report selection displays the report text automatically');
    assert.equal(element('financialReportPreview').hidden, true);
    const selectionsBeforeGeneration = requests.filter(r => r.url.includes('action=get_report')).length;
    bootstrap.Modal.getOrCreateInstance(element('generateReportModal')).show();
    await element('generateReportSubmit').listeners.click.call(element('generateReportSubmit'));
    await settle();
    assert.equal(element('generateReportModal').hidden, true, 'Generator closes before opening the preview');
    assert.equal(element('financialReportPreview').hidden, false, 'Finished report preview opens automatically');
    assert.equal(element('modalBackdrop').hidden, false, 'Preview backdrop remains visible after generation');
    assert.equal(charts.length, 5, 'Distinct comprehensive charts render after generation completes');
    assert.equal(requests.filter(r => r.url.includes('action=get_report')).length, selectionsBeforeGeneration, 'Generated text must use the response without another selection request');
    assert.match(element('financialReportContent').innerHTML, /Fixture financial findings/);
    assert.match(element('financialReportContent').innerHTML, /&lt;script&gt;/);
    assert.doesNotMatch(element('financialReportContent').innerHTML, /<script>|View Generated Report|<canvas/);
    assert.equal(element('financialReportPreview').hidden, false);
    assert.equal(element('fprResults').hidden, false);
    assert.equal(element('fprPdf').disabled, false);
    assert.equal(charts.length, 5);
    assert.match(element('fprCards').innerHTML, /₱150\.35/);
    assert.match(element('fprCards').innerHTML, /₱120\.30/);
    assert.equal(context.window.FinanceFinancialPerformance.getSnapshot(), 'server-owned-snapshot');
    assert.equal(context.window.FinanceFinancialPerformance.params().get('from'), '2026-10-01');
    fields.bus.value = 'B1';
    assert.equal(context.window.FinanceFinancialPerformance.getSnapshot(), 'server-owned-snapshot', 'Preview remains bound to its generated snapshot, not the main-page filters');
    fields.bus.value = '';
    tabs[2].listeners.click();
    assert.match(element('fprSummaries').innerHTML, /2026-10/);
    element('fprRecordSearch').value = 'settlement'; element('fprRecordSearch').listeners.input();
    assert.match(element('fprRecords').innerHTML, /settlement:1/);
    assert.doesNotMatch(element('fprRecords').innerHTML, /fare:1/);
    assert.equal(openedReport, 42);
    assert.doesNotMatch(element('financialReportContent').innerHTML, /View Generated Report/);
    assert.doesNotMatch(element('financialReportContent').innerHTML, /<canvas|fpr-cards/, 'Main-page report text does not create a charts dashboard');
    assert.match(element('fprNarrative').innerHTML, /Fixture financial findings/);
    assert.match(element('fprNarrative').innerHTML, /&lt;script&gt;/);
    assert.doesNotMatch(element('fprNarrative').innerHTML, /<script>/);
    assert.equal(element('fprNarrativePanel').hidden, false);
    await element('fprPdf').listeners.click(); await settle();
    assert.ok(requests.some(r => r.url.includes('action=pdf') && r.url.includes('snapshot=server-owned-snapshot')));
    mode = 'pdf-json'; await element('fprPdf').listeners.click(); await settle();
    assert.match(element('fprError').textContent, /PHP GD/);
    mode = 'pdf-html'; await element('fprPdf').listeners.click(); await settle();
    assert.match(element('fprError').textContent, /valid PDF/);
    mode = 'normal';
    bootstrap.Modal.getOrCreateInstance(element('financialReportPreview')).hide();
    assert.equal(element('modalBackdrop').hidden, true, 'Closing the preview removes its backdrop');
    await context.loadReportById(42); await settle();
    assert.equal(element('financialReportPreview').hidden, true, 'Selecting a saved report displays text inline');
    assert.match(element('financialReportContent').innerHTML, /Fixture financial findings/);
    assert.match(element('fprNarrative').innerHTML, /Fixture financial findings/, 'Saved report retains its analysis');
    await context.window.FinanceFinancialPerformance.openSaved(42); await settle();
    assert.ok(requests.some(r => r.url.includes('action=preview') && r.url.includes('id=42')));
    mode = 'expired'; await element('fprPdf').listeners.click(); await settle();
    assert.equal(element('fprReload').hidden, false);
    mode = 'normal'; element('fprReload').listeners.click(); await settle();
    assert.ok(requests[requests.length - 1].url.includes('action=preview'), 'Expired saved preview must reload its saved snapshot, not recompute financial data');
    mode = 'empty'; await context.window.FinanceFinancialPerformance.prepare(); context.window.FinanceFinancialPerformance.showGenerated({title: 'Empty Generated Report', report: ''}); await settle();
    assert.equal(element('fprEmpty').hidden, false);
    mode = 'error'; element('fprReload').listeners.click(); await settle();
    assert.equal(element('fprError').hidden, false);
    assert.match(element('fprError').textContent, /Financial data unavailable/);
    assert.equal(element('fprResults').hidden, true);
    assert.equal(element('fprPdf').disabled, true);
    assert.equal(context.window.FinanceFinancialPerformance.getSnapshot(), '');
    mode = 'invalid'; await context.window.FinanceFinancialPerformance.prepare();
    assert.match(element('fprError').textContent, /invalid response/);
    mode = 'generation-empty';
    await element('generateReportSubmit').listeners.click.call(element('generateReportSubmit'));
    assert.match(element('fprError').textContent, /empty response.*HTTP 502/);
    assert.equal(element('fprPdf').disabled, true, 'Incomplete generation must not enable snapshot downloads');
    assert.equal(element('generateReportSubmit').disabled, false, 'Generation can be retried after an empty response');
    mode = 'generation-network';
    await element('generateReportSubmit').listeners.click.call(element('generateReportSubmit'));
    assert.match(element('fprError').textContent, /Unable to reach the Finance reporting server/);
    assert.equal(element('generateReportSubmit').disabled, false);
    mode = 'expenses';
    const chartsBeforeExpense = charts.length;
    await context.window.FinanceFinancialPerformance.openSaved(43); await settle();
    assert.equal(charts.length - chartsBeforeExpense, 1, 'Expense preview renders one relevant chart');
    assert.equal(element('fprProfitPanel').hidden, true);
    assert.equal(element('fprDriversPanel').hidden, true);
    assert.equal(element('fprCategoriesPanel').hidden, false);
    assert.doesNotMatch(element('fprCards').innerHTML, /Total Revenue|Net Profit/);
    element('fprRecordSearch').value = ''; element('fprRecordSearch').listeners.input();
    assert.match(element('fprRecords').innerHTML, /expense:1/);
    assert.doesNotMatch(element('fprRecords').innerHTML, /fare:1|settlement:1/);
    mode = 'summary'; await context.window.FinanceFinancialPerformance.openSaved(44); await settle();
    assert.equal(element('fprRecordsPanel').hidden, true, 'Summary preview omits the transaction appendix');
    assert.equal(element('fprBusesPanel').hidden, true);
    mode = 'fleet'; await context.window.FinanceFinancialPerformance.openSaved(45); await settle();
    assert.equal(element('fprBusesPanel').hidden, false);
    assert.equal(element('fprDriversPanel').hidden, true);
    const mainSource = fs.readFileSync('finance/views/financialreports/index.php', 'utf8');
    assert.doesNotMatch(mainSource, /bootstrap[^"']*bundle[^"']*\.js/, 'Financial Reports must not load Bootstrap twice');
    const navbarSource = fs.readFileSync('finance/views/layout/navbarfinance.php', 'utf8');
    assert.equal((navbarSource.match(/bootstrap[^"']*bundle[^"']*\.js/g) || []).length, 1, 'The navbar supplies the single Bootstrap bundle');
    assert.ok(mainSource.indexOf("require __DIR__ . '/_analytics.php'") > mainSource.indexOf('customAlertOverlay'), 'Report preview must be outside the main page body');
    const previewSource = fs.readFileSync('finance/views/financialreports/_analytics.php', 'utf8');
    assert.ok(previewSource.indexOf('id="financialReportPreview"') < previewSource.indexOf('id="financialPerformance"'), 'Financial content must be enclosed in the hidden modal');
    assert.match(previewSource, /aria-hidden="true"/);
    assert.match(previewSource, /data-bs-backdrop="true"/);
    console.log('Financial Reports UI checks passed: automatic generation preview and backdrop, modal handoff/close, inline report text, no redundant fetch, charts deferred until completion, CSRF POST, escaped analysis, snapshot/PDF and error states.');
})().catch(error => {console.error(error); process.exitCode = 1;});
