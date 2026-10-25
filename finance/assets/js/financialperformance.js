(function () {
    'use strict';
    const root = document.getElementById('financialPerformance');
    if (!root) return;
    const $ = id => document.getElementById(id);
    const API = '/finance/api/financial_performance.php';
    const money = v => '₱' + Number(v || 0).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const number = v => Number(v || 0).toLocaleString('en-PH');
    const esc = v => String(v == null ? '' : v).replace(/[&<>"']/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));
    let data = null, snapshot = '', complete = false, request = 0, page = 1, bucket = 'daily', charts = [], controller, lastQuery = null;
    async function requestFinance(url, options = {}, timeout = 75000) {
        const timeoutController = new AbortController();
        const timer = options.signal ? null : setTimeout(() => timeoutController.abort(), timeout);
        try {
            return await fetch(url, {credentials: 'same-origin', ...options, signal: options.signal || timeoutController.signal});
        } catch (error) {
            if (error.name === 'AbortError') throw new Error('The Finance request timed out. Please retry with a shorter reporting period.');
            if (error.name === 'TypeError') throw new Error('Unable to reach the Finance reporting server. Check your connection, reload this page, and retry.');
            throw error;
        } finally { if (timer) clearTimeout(timer); }
    }
    async function readJson(response) {
        const body = await response.text();
        if (!body.trim()) throw new Error('The reporting server returned an empty response' + (response.status ? ' (HTTP ' + response.status + ')' : '') + '. Please retry.');
        let result;
        try { result = JSON.parse(body); }
        catch (_) { throw new Error('The reporting server returned an invalid response. Your session may have expired or the request timed out. Reload the page and retry.'); }
        if (!result || typeof result !== 'object' || Array.isArray(result)) throw new Error('The reporting server returned an invalid report response. Please retry.');
        return result;
    }
    function formatNarrative(value) {
        const narrative = String(value || '').trim();
        return narrative.split(/\n\s*\n/).map(block => {
            const lines = block.split('\n').map(line => line.trim()).filter(Boolean);
            return lines.map(line => {
                if (/^[-•]\s+/.test(line)) return '<p class="fpr-analysis-point">' + esc(line) + '</p>';
                if (line.length < 90 && /^(?:executive|revenue|expense|operating|profit|fleet|driver|route|risk|recommendation|conclusion|financial|cost|key|summary|cash)/i.test(line) && !/[.!?₱]/.test(line)) return '<h4>' + esc(line) + '</h4>';
                return '<p>' + esc(line) + '</p>';
            }).join('');
        }).join('');
    }
    function renderNarrative() {
        $('fprNarrativePanel').hidden = !data.narrative;
        $('fprNarrative').innerHTML = formatNarrative(data.narrative);
    }
    function showProgress(message, error = false) {
        const content = $('financialReportContent');
        if (content) content.innerHTML = '<div class="' + (error ? 'fpr-error' : 'fpr-status') + '" role="' + (error ? 'alert' : 'status') + '" aria-live="polite">' + esc(message) + '</div>';
    }
    function params() {
        const form = $('financialFilter');
        const result = new URLSearchParams();
        if ($('reportType')) result.set('report_type', $('reportType').value || 'full');
        for (const key of ['report_period', 'from', 'to', 'driver', 'bus']) {
            const field = form && form.elements.namedItem(key);
            if (field && field.value) result.set(key, field.value);
        }
        const period = $('fprGenerationPeriod');
        if (period) {
            result.set('report_period', period.value || 'this_month');
            result.delete('from'); result.delete('to');
            if (period.value === 'custom') {
                const from = $('fprGenerationFrom'); const to = $('fprGenerationTo');
                if (from && from.value) result.set('from', from.value);
                if (to && to.value) result.set('to', to.value);
            }
        }
        return result;
    }
    function getSnapshot() {
        return snapshot && data ? snapshot : '';
    }
    function showPreview() {
        const modal = bootstrap.Modal.getOrCreateInstance($('financialReportPreview'), {backdrop: true});
        const generator = $('generateReportModal');
        if (generator && generator.classList.contains('show')) {
            generator.addEventListener('hidden.bs.modal', () => modal.show(), {once: true});
            bootstrap.Modal.getOrCreateInstance(generator).hide();
        } else modal.show();
    }
    async function prepare() {
        showPreview();
        showProgress('Preparing financial data for your report…');
        const ready = await load(params(), false);
        if (ready) {
            $('fprResults').hidden = true;
            setBusy(true, 'Generating the financial report from this verified snapshot…');
            showProgress('Financial data is ready. Writing the AI analysis…');
        }
        return ready;
    }
    function showGenerated(result) {
        if (!data || !snapshot) return;
        data.narrative = result.report || '';
        if (result.presentation) data.presentation = result.presentation;
        if (result.report_type) data.report_type = result.report_type;
        if (result.charts) data.charts = result.charts;
        if (result.insights) data.insights = result.insights;
        complete = true;
        if (result.report_id) lastQuery = new URLSearchParams({action: 'preview', id: String(result.report_id)});
        $('fprTitle').textContent = result.title || 'Generated Financial Report';
        render();
        $('fprResults').hidden = false;
        showPreview();
        setBusy(false);
        charts.forEach(chart => chart.resize());
    }
    function showError(message) {
        $('fprError').textContent = message;
        $('fprError').hidden = false;
        $('fprResults').hidden = !data;
        setBusy(false);
        showProgress(message, true);
    }
    async function openSaved(id) {
        showPreview();
        await load(new URLSearchParams({action: 'preview', id: String(id)}));
    }
    window.FinanceFinancialPerformance = {params, getSnapshot, prepare, showGenerated, showError, openSaved, readJson, requestFinance, formatNarrative, downloadSavedPdf, getFilters: () => data && data.filters};
    function table(rows, firstLabel = 'Period / Name') {
        const metrics = data && data.presentation ? data.presentation.metrics : ['revenue', 'expenses', 'profit'];
        const labels = {revenue: 'Revenue', expenses: 'Expenses', profit: 'Net profit / loss'};
        return '<table><thead><tr><th>' + esc(firstLabel) + '</th>' + metrics.map(key => '<th class="fpr-money">' + labels[key] + '</th>').join('') + '</tr></thead><tbody>' +
            (rows.length ? rows.map(r => '<tr><td>' + esc(r.name) + '</td>' + metrics.map(key => '<td class="fpr-money ' + (r[key] < 0 ? 'fpr-loss' : '') + '">' + money(r[key]) + '</td>').join('') + '</tr>').join('') : '<tr><td colspan="' + (metrics.length + 1) + '">No matching records.</td></tr>') + '</tbody></table>';
    }
    function records() {
        if (!data) return;
        const search = $('fprRecordSearch').value.toLowerCase().trim();
        const rows = data.records.filter(r => !search || [r.reference, r.driver, r.bus, r.category, r.description, r.date].join(' ').toLowerCase().includes(search));
        const pages = Math.max(1, Math.ceil(rows.length / 25));
        page = Math.max(1, Math.min(page, pages));
        $('fprRecords').innerHTML = '<table><thead><tr><th>Date / Reference</th><th>Driver</th><th>Bus</th><th>Type / Category</th><th class="fpr-money">Amount</th><th>Description</th></tr></thead><tbody>' +
            (rows.length ? rows.slice((page - 1) * 25, page * 25).map(r => '<tr><td>' + esc(r.date) + '<br><small>' + esc(r.reference) + '</small></td><td>' + esc(r.driver || 'Unassigned') + '</td><td>' + esc(r.bus || 'Unassigned') + '</td><td>' + esc(r.kind + ' / ' + r.category) + '</td><td class="fpr-money">' + money(r.amount) + '</td><td>' + esc(r.description) + '</td></tr>').join('') : '<tr><td colspan="6">No matching financial records.</td></tr>') + '</tbody></table>';
        $('fprPageInfo').textContent = `Page ${page} of ${pages} · ${number(rows.length)} records`;
        $('fprPrev').disabled = page === 1; $('fprNext').disabled = page === pages;
    }
    function buses() {
        if (!data) return;
        const rows = data.breakdowns.buses.slice();
        if ($('fprBusSort').value === 'low') rows.sort((a, b) => a.revenue - b.revenue);
        $('fprBuses').innerHTML = table(rows, 'Bus');
    }
    function render() {
        const t = data.totals;
        const scope = data.presentation;
        const categoryLabels = {full: 'Comprehensive', revenue: 'Revenue', expenses: 'Expenses', fleet: 'Fleet', drivers: 'Drivers', routes: 'Routes', summary: 'Summary', risk: 'Risk assessment'};
        $('fprCategoryBadge').textContent = 'Finance Department · ' + (categoryLabels[data.report_type] || 'Comprehensive');
        if (scope) {
            bucket = scope.bucket;
            $('fprCsv').hidden = !scope.records;
            root.querySelectorAll('[data-fpr-bucket]').forEach(button => {const active = button.dataset.fprBucket === bucket; button.classList.toggle('active', active); button.setAttribute('aria-pressed', String(active));});
            for (const [id, visible] of [['fprProfitPanel', scope.profit], ['fprSummariesPanel', scope.tables.includes(scope.bucket)], ['fprBusesPanel', scope.tables.includes('buses')], ['fprDriversPanel', scope.tables.includes('drivers')], ['fprPaymentsPanel', scope.payments], ['fprRecordsPanel', scope.records], ['fprCategoriesPanel', scope.tables.includes('categories')]]) $(id).hidden = !visible;
        }
        $('fprPeriod').textContent = data.filters.label;
        $('fprSnapshotMeta').textContent = 'Data snapshot: ' + data.generated_at + ' (Philippine time)';
        const cards = [['Total Revenue', money(t.revenue), 'Paid fares + archived fare revenue'], ['Total Expenses', money(t.expenses), 'Recorded ledger and fuel costs'], ['Net Profit / Loss', money(t.profit), t.margin === null ? 'Margin unavailable: no revenue' : `Profit margin ${t.margin.toFixed(1)}%`], ['Fuel Expenses', money(t.fuel), 'Recorded fuel costs'], ['Operational Costs', money(t.operational_costs), 'Non-fuel posted expenses'], ['Passenger Revenue', money(t.passenger_revenue), `${number(t.passengers)} passenger fare records`], ['Trips with Fare Collections', number(t.trips), 'Distinct date / driver / bus / trip'], ['Revenue Growth', data.growth.revenue === null ? 'N/A' : `${data.growth.revenue.toFixed(1)}%`, `Compared with ${data.previous.from} – ${data.previous.to}`]];
        $('fprCards').innerHTML = cards.filter((_, index) => !scope || scope.cards.includes(index)).map(c => '<div class="fpr-overview-item"><div class="fpr-overview-label">' + esc(c[0]) + '</div><div class="fpr-overview-value">' + esc(c[1]) + '</div><div class="fpr-overview-sub">' + esc(c[2]) + '</div></div>').join('');
        $('fprProfit').innerHTML = '<table class="fpr-fin-table"><tbody><tr><td>Total Revenue</td><td class="fpr-money">' + money(t.revenue) + '</td></tr><tr><td>Total Expenses</td><td class="fpr-money">' + money(t.expenses) + '</td></tr><tr class="fpr-fin-net"><td>Net Profit / Loss</td><td class="fpr-money">' + money(t.profit) + '</td></tr></tbody></table>' + '<p class="fpr-muted">Comparison period: ' + esc(data.previous.from + ' – ' + data.previous.to) + '. Revenue growth: ' + (data.growth.revenue === null ? 'No comparison base' : data.growth.revenue.toFixed(1) + '%') + '.</p>';
        $('fprInsights').innerHTML = data.insights.map(v => '<li>' + esc(v) + '</li>').join('');
        $('fprNotes').innerHTML = data.notes.map(v => '<li>' + esc(v) + '</li>').join('');
        $('fprSummaries').innerHTML = table(data.series[bucket]);
        buses(); $('fprDrivers').innerHTML = table(data.breakdowns.drivers, 'Driver');
        $('fprCategories').innerHTML = table(data.breakdowns.categories, 'Expense category');
        $('fprPayments').innerHTML = table(data.breakdowns.payments, 'Payment method');
        $('fprPayments').insertAdjacentHTML('beforeend', '<p class="fpr-muted">Remitted cash (settlement, not additional revenue): ' + money(t.collections) + '.</p>');
        charts.forEach(c => c.destroy()); charts = [];
        const hasChartData = c => c.datasets.some(d => d.data.some(v => Number(v) !== 0));
        $('fprCharts').innerHTML = data.charts.map(c => '<section class="fpr-panel fpr-chart-panel"><h3>' + esc(c.title) + '</h3><div class="fpr-chart">' + (hasChartData(c) ? '<canvas id="fprChart-' + esc(c.id) + '" role="img" aria-label="' + esc(c.title) + '"></canvas>' : '<div class="fpr-chart-empty"><strong>No chart data</strong><span>No matching records in the selected period.</span></div>') + '</div></section>').join('');
        if (typeof Chart !== 'function') throw new Error('Charts could not load. Refresh the page to retry.');
        data.charts.forEach(c => {
            if (!hasChartData(c)) return;
            const circular = ['pie', 'doughnut'].includes(c.type);
            const sets = c.datasets.map(d => ({...d, pointRadius: c.labels.length > 62 ? 0 : 2, borderWidth: 2, ...(d.fill ? {backgroundColor: 'rgba(41,87,56,0.16)'} : {})}));
            charts.push(new Chart($('fprChart-' + c.id), {type: c.type, data: {labels: c.labels, datasets: sets}, options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {legend: {position: 'bottom', labels: {boxWidth: 12, font: {family: 'Poppins', size: 10}}}, tooltip: {callbacks: {label: ctx => (ctx.dataset.label || ctx.label) + ': ' + money(typeof ctx.parsed === 'number' ? ctx.parsed : ctx.parsed.y)}}},
                ...(circular ? {} : {scales: {x: {stacked: c.stacked, grid: {display: false}, ticks: {maxTicksLimit: 8, font: {size: 10}}}, y: {stacked: c.stacked, ticks: {callback: v => '₱' + number(v)}, grid: {color: '#e3eabb'}}}})
            }}));
        });
        records();
        renderNarrative();
    }
    function setBusy(on, message) {
        root.setAttribute('aria-busy', String(on));
        $('fprStatus').hidden = !on;
        if (message) $('fprStatus').textContent = message;
        for (const id of ['fprPdf', 'fprCsv']) $(id).disabled = on || !snapshot || !complete;
    }
    async function load(query, renderResults = true) {
        const id = ++request;
        const requestedQuery = new URLSearchParams(query);
        lastQuery = requestedQuery;
        if (controller) controller.abort();
        controller = new AbortController();
        const activeController = controller;
        const timer = setTimeout(() => activeController.abort(), 60000);
        snapshot = ''; data = null; complete = false;
        $('fprTitle').textContent = 'Generated Financial Report';
        $('fprNarrativePanel').hidden = true;
        $('fprReload').hidden = true;
        $('fprResults').hidden = true; $('fprError').hidden = true; $('fprEmpty').hidden = true;
        setBusy(true, 'Loading verified financial data…');
        try {
            const response = await requestFinance(API + '?' + requestedQuery, {signal: activeController.signal, cache: 'no-store'});
            const result = await readJson(response);
            if (id !== request) return false;
            if (!response.ok || !result.success) throw new Error(result.message || 'Financial analytics could not be loaded.');
            data = result.data; snapshot = result.snapshot; page = 1;
            complete = requestedQuery.get('action') === 'preview';
            if (renderResults) render();
            $('fprTitle').textContent = result.title || 'Generated Financial Report';
            $('fprResults').hidden = !renderResults; $('fprEmpty').hidden = !data.empty;
            return true;
        } catch (error) {
            if (id !== request) return false;
            snapshot = '';
            data = null;
            $('fprError').textContent = error.name === 'AbortError' ? 'The report request timed out. Click Retry loading report to retry.' : error.message;
            $('fprError').hidden = false;
            $('fprReload').hidden = false;
            if (!renderResults) showProgress($('fprError').textContent, true);
            return false;
        } finally { clearTimeout(timer); if (id === request) setBusy(false); }
    }
    async function download(action) {
        const activeSnapshot = getSnapshot();
        if (!activeSnapshot || !complete) {
            $('fprError').textContent = 'Generate or open a saved financial report before downloading.';
            $('fprError').hidden = false;
            return;
        }
        setBusy(true, action === 'pdf' ? 'Rendering the full PDF with charts and tables…' : 'Preparing financial records…');
        $('fprError').hidden = true;
        try {
            const response = await requestFinance(API + '?' + new URLSearchParams({action, snapshot: activeSnapshot}), {cache: 'no-store'}, 120000);
            if (!response.ok) {
                if (response.status === 409) { snapshot = ''; $('fprReload').hidden = false; }
                const result = await readJson(response); throw new Error(result.message || 'Download failed.');
            }
            const contentType = response.headers && response.headers.get('Content-Type') || '';
            if (contentType.includes('application/json')) {
                const result = await readJson(response); throw new Error(result.message || 'The export could not be prepared.');
            }
            const blob = await response.blob();
            if (action === 'pdf' && await blob.slice(0, 5).text() !== '%PDF-') throw new Error('The server did not return a valid PDF. Reload the report and retry.');
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a'); link.href = url; link.download = action === 'pdf' ? 'financial-performance.pdf' : 'financial-records.csv';
            document.body.appendChild(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch (error) { $('fprError').textContent = error.message; $('fprError').hidden = false; }
        finally { setBusy(false); }
    }
    async function downloadSavedPdf(id) {
        const response = await requestFinance('/finance/api/financial_reports_api.php?' + new URLSearchParams({action: 'export_report_pdf', id}), {cache: 'no-store'}, 120000);
        const type = response.headers && response.headers.get('Content-Type') || '';
        if (!response.ok || type.includes('application/json')) {
            const result = await readJson(response);
            throw new Error(result.message || 'PDF export failed.');
        }
        const blob = await response.blob();
        if (await blob.slice(0, 5).text() !== '%PDF-') throw new Error('The reporting server did not return a valid PDF. Please retry.');
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a'); link.href = url; link.download = 'financial-report-' + id + '.pdf';
        document.body.appendChild(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
    }
    $('fprPdf').addEventListener('click', () => download('pdf'));
    $('fprCsv').addEventListener('click', () => download('csv'));
    $('fprReload').addEventListener('click', () => { if (lastQuery) load(lastQuery); });
    $('financialReportPreview').addEventListener('shown.bs.modal', () => charts.forEach(chart => chart.resize()));
    $('fprBusSort').addEventListener('change', buses);
    $('fprRecordSearch').addEventListener('input', () => {page = 1; records();});
    $('fprPrev').addEventListener('click', () => {page--; records();});
    $('fprNext').addEventListener('click', () => {page++; records();});
    root.querySelectorAll('[data-fpr-bucket]').forEach(button => button.addEventListener('click', () => {
        bucket = button.dataset.fprBucket;
        root.querySelectorAll('[data-fpr-bucket]').forEach(b => {b.classList.toggle('active', b === button); b.setAttribute('aria-pressed', String(b === button));});
        if (data) $('fprSummaries').innerHTML = table(data.series[bucket]);
    }));
})();
