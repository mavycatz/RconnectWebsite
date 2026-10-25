const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../views/Homepage/Index.php'), 'utf8');
let cached = JSON.stringify({ trip_id: 20, bus_no: 'Bus-1', driver_name: 'Driver', destination: null });
const destination = { innerText: '—' };
const context = { window: { _activeTripId: 20 }, TRIP_INFO_KEY: 'trip', console,
    document: { getElementById: () => destination },
    localStorage: { getItem: () => cached, setItem: (key, value) => { cached = value; } } };
vm.runInNewContext(source.slice(source.indexOf('window.regularTripDestinationUpdate ='), source.indexOf('async function restoreActiveTripInfo(){')), context);
context.window.regularTripDestinationUpdate({ trip_id: 20, selected_destination: 'Stop C', selected_destination_id: 200 });
assert.equal(destination.innerText, 'Stop C', 'Destination success updates active container immediately');
assert.equal(JSON.parse(cached).destination, 'Stop C', 'Selected destination survives cached reopening');
assert.equal(JSON.parse(cached).bus_no, 'Bus-1', 'Destination-only response does not wipe existing trip details');
destination.innerText = 'Old destination';
context.window.regularTripDestinationUpdate({ trip_id: 20, mode: 'trip', route: { destination: 'Stop C', destination_id: 200 } });
assert.equal(destination.innerText, 'Stop C', 'Restored backend party corrects a stale active destination');
context.window.regularTripDestinationUpdate({ trip_id: 99, destination: 'Wrong trip' });
assert.equal(destination.innerText, 'Stop C', 'Old trip response cannot overwrite active trip');
console.log('Active Trip destination selection, restoration, cache preservation, and stale-trip checks passed.');
