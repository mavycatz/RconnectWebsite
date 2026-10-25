const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('finance/views/predictive_analysis/index.php', 'utf8');
let mode = 'database', message = '', rendered = false, loading = false;
const nodes = {fpaRefresh: {disabled: false}, fpaLastUpdated: {textContent: ''}};
const context = {
    API: '/finance/api/analytics/predictive_analysis.php', state: {period: '30d'}, loadReqId: 0,
    URLSearchParams, AbortController, setTimeout, clearTimeout,
    $: id => nodes[id], setLoading: value => {loading = value;}, hideError() {},
    showError: value => {message = value;}, applyFilters() {}, renderUnavailable() {},
    renderAll: () => {rendered = true;}, reveal() {},
    fetch: async (url, options) => {
        assert.equal(options.credentials, 'same-origin');
        if (mode === 'network') throw new TypeError('Failed to fetch');
        if (mode === 'expired') return {ok: false, status: 401, text: async () => '<html>Sign in</html>'};
        if (mode === 'empty') return {ok: false, status: 502, text: async () => ''};
        if (mode === 'database') return {ok: false, status: 503, text: async () => JSON.stringify({success: false, message: 'The Finance database is unavailable.'})};
        return {ok: true, status: 200, text: async () => JSON.stringify({success: true, filters: {}, model: {computed_at: '2026-10-05'}})};
    }
};
vm.createContext(context);
const start = source.indexOf('    function buildUrl()');
vm.runInContext(source.slice(start, source.indexOf('    document.querySelectorAll(".fpa-period-btn")', start)), context);
(async () => {
    await context.load(); assert.match(message, /database is unavailable/);
    mode = 'expired'; await context.load(); assert.match(message, /session has expired/);
    mode = 'empty'; await context.load(); assert.match(message, /invalid or empty response.*502/);
    mode = 'network'; await context.load(); assert.match(message, /Check your connection/);
    mode = 'success'; await context.load(); assert.equal(rendered, true);
    assert.equal(loading, false); assert.equal(nodes.fpaRefresh.disabled, false);
    console.log('Predictive Analysis request checks passed: database, session, empty response, network failure and recovery.');
})().catch(error => {console.error(error); process.exitCode = 1;});
