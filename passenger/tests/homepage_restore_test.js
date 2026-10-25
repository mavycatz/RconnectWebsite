/* Homepage active-trip restore: server-authoritative caches, no stale card on
   failure (retry instead), recovery on the next successful fetch, and
   passenger-scoped caches that are dropped on an owner mismatch. */
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../views/Homepage/Index.php'), 'utf8');

const sliceStart = source.indexOf('const HOMEPAGE_STATE_KEY =');
const sliceEnd = source.indexOf('function restoreHomepageState() {');
assert.ok(sliceStart !== -1 && sliceEnd !== -1, 'The homepage cache/restore block is present');
const code = source.slice(sliceStart, sliceEnd);

function makeStorage(seed) {
    const data = Object.assign({}, seed);
    return {
        get length() { return Object.keys(data).length; },
        key(i) { return Object.keys(data)[i] ?? null; },
        getItem(k) { return Object.prototype.hasOwnProperty.call(data, k) ? data[k] : null; },
        setItem(k, v) { data[k] = String(v); },
        removeItem(k) { delete data[k]; },
        clear() { for (const k of Object.keys(data)) delete data[k]; }
    };
}

function makeContext(options) {
    const elements = {};
    ['busInfo', 'activeBusNo', 'driverInfo', 'activeDriver', 'tripNoInfo', 'passengerType',
        'activeBoarding', 'activeDestination', 'tripDetails', 'tripArrow'].forEach((id) => {
        elements[id] = { innerText: '—', style: { display: '' }, textContent: '' };
    });

    const body = {
        children: [],
        appendChild(node) { this.children.push(node); if (node.id) { elements[node.id] = node; } }
    };

    const documentStub = {
        getElementById: (id) => elements[id] || null,
        querySelector: () => null,
        createElement: () => ({
            id: '', style: {}, innerHTML: '', _listeners: {},
            addEventListener(type, fn) { this._listeners[type] = fn; }
        }),
        body
    };

    const fetchCalls = [];
    const sandbox = {
        window: {
            PASSENGER_SESSION_ID: options.passengerId,
            location: { reload: () => {} }
        },
        document: documentStub,
        fetch: (url) => {
            fetchCalls.push(String(url));
            if (options.fetchError) { return Promise.reject(new Error('offline')); }
            return Promise.resolve({ json: () => Promise.resolve(options.server || { hasActiveTrip: false }) });
        },
        localStorage: makeStorage(options.storage || {}),
        console,
        Promise,
        Date
    };

    vm.runInNewContext(code + '\nthis.__handleActiveTripSnapshot = handleActiveTripSnapshot;\nthis.__restoreActiveTripInfo = restoreActiveTripInfo;\nthis.__loadHomepageState = loadHomepageState;', sandbox);
    sandbox.__elements = elements;
    sandbox.__fetchCalls = fetchCalls;
    return sandbox;
}

(async () => {
    const TRIP_KEY = 'passenger_active_trip_info';
    const STATE_KEY = 'passenger_homepage_state';
    const activeCache = JSON.stringify({
        owner_passenger_id: 7, active: true, bus_no: 'CachedBus', driver_name: 'CachedDriver',
        boarding_point: 'CachedStop', destination: 'CachedDest', trip_id: 20
    });
    const stateCache = JSON.stringify({ owner_passenger_id: 7, paymentMethod: 'gcash' });

    /* 1. Server reports no active trip: caches removed, nothing rendered. */
    {
        const ctx = makeContext({
            passengerId: 7,
            storage: { [TRIP_KEY]: activeCache, [STATE_KEY]: stateCache },
            server: { hasActiveTrip: false }
        });
        await ctx.__restoreActiveTripInfo();
        assert.equal(ctx.localStorage.getItem(TRIP_KEY), null, 'server-no-trip removes the cached trip');
        assert.equal(ctx.localStorage.getItem(STATE_KEY), null, 'server-no-trip removes the cached payment progress');
        assert.equal(ctx.__elements.activeBusNo.innerText, '—', 'no cached bus value is rendered');
    }

    /* 2. Fetch failure: no stale card, cached trip kept but not shown, retry offered. */
    {
        const ctx = makeContext({
            passengerId: 7,
            storage: { [TRIP_KEY]: activeCache, [STATE_KEY]: stateCache },
            fetchError: true
        });
        await ctx.__restoreActiveTripInfo();
        assert.equal(ctx.localStorage.getItem(TRIP_KEY), activeCache, 'failure keeps the cache for later recovery');
        assert.equal(ctx.__elements.activeBusNo.innerText, '—', 'failure renders no cached bus value');
        assert.equal(ctx.__elements.activeDestination.innerText, '—', 'failure renders no cached destination');
        const retry = ctx.__elements.tripStatusRetry;
        assert.ok(retry, 'a retry affordance is shown');
        assert.match(retry.innerHTML, /Retry/, 'the retry affordance offers a Retry action');
    }

    /* 3. Recovery: the next successful fetch wins over the cache. */
    {
        const ctx = makeContext({
            passengerId: 7,
            storage: { [TRIP_KEY]: activeCache, [STATE_KEY]: stateCache },
            server: {
                hasActiveTrip: true, bus_no: 'ServerBus', driver_name: 'ServerDriver',
                boarding_point: 'ServerStop', destination: 'ServerDest', discount_type: 'Regular'
            }
        });
        await ctx.__restoreActiveTripInfo();
        assert.equal(ctx.__elements.activeBusNo.innerText, 'ServerBus', 'server bus value replaces the cache');
        assert.equal(ctx.__elements.activeDestination.innerText, 'ServerDest', 'server destination replaces the cache');
        assert.equal(ctx.__fetchCalls.length, 1, 'the active-trip endpoint is queried');
    }

    /* 4. Owner mismatch: another passenger's cached trip is dropped, never fetched. */
    {
        const ctx = makeContext({
            passengerId: 9,
            storage: { [TRIP_KEY]: activeCache, [STATE_KEY]: stateCache },
            server: { hasActiveTrip: false }
        });
        await ctx.__restoreActiveTripInfo();
        assert.equal(ctx.localStorage.getItem(TRIP_KEY), null, "another passenger's cached trip is dropped");
        assert.equal(ctx.__fetchCalls.length, 0, 'a mismatched cache is not used for a server check');
        assert.equal(ctx.__loadHomepageState(), null, "another passenger's payment progress is not loaded");
        assert.equal(ctx.localStorage.getItem(STATE_KEY), null, "another passenger's payment progress is dropped");
    }

    console.log('Homepage restore and cache-owner checks passed.');
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
