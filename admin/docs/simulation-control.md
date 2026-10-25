# Shared GPS Route Simulation - Runbook

The simulation console at `simulation.php` (website root) moves one or more
buses along their routes without a device on the road. Each bus has its own
session and its own Start/Pause/Resume/Reset/Disable controls; running sessions
advance together while the console page stays open. The driver app, the
inspector app, and the admin dashboard follow the same coordinates per bus.
Real GPS tables are never written while a simulation is active.

This is a demo/defense tool. Delete or rename `simulation.php` before
production use; nothing else in the feature needs to run for normal tracking.

## Files

| Purpose | Path |
| --- | --- |
| Controller console (main file, no login) | `simulation.php` |
| Shared read endpoint | `mobile/simulation/state.php` |
| Engine (movement math + persistence) | `mobile/simulation/lib/simulation_engine.php` |
| Hard-coded coordinates (edit to tune a demo) | `mobile/simulation/lib/simulation_routes.php` |
| Additive DDL | `mobile/simulation/scripts/simulation_tables.sql` |
| Engine test (no database) | `mobile/simulation/tests/simulation_engine_test.php` |
| Integration test (throwaway database) | `mobile/simulation/tests/simulation_integration_test.php` |

## 1. Deploy the DDL first

Run once on the live database. `CREATE TABLE IF NOT EXISTS` only - no existing
table is altered and no existing row is touched.

```bash
mysql -u <user> -p <db> < mobile/simulation/scripts/simulation_tables.sql
```

Verify:

```sql
SHOW TABLES LIKE 'simulation%';   -- simulation_sessions, simulation_stop_events
DESCRIBE simulation_sessions;
DESCRIBE simulation_stop_events;
```

Rollback: `DROP TABLE simulation_stop_events; DROP TABLE simulation_sessions;`
The tables can also simply be left in place; nothing reads them unless the
feature is deployed.

## 2. Deploy the backend before the apps

1. `simulation.php` (website root), `mobile/simulation/*`.
2. Edited read paths: `mobile/inspector/live_map.php`,
   `admin/api/TripDashboard/getDriverLocations.php`,
   `mobile/driver/getDriverRoute.php`, `mobile/driver/getRouteCoordinates.php`,
   `mobile/inspector/get_trip_summary.php`,
   `dispatcher/api/helpers.php`.
3. Edited write path: `mobile/driver/updateLocation.php` (refuses uploads while
   simulated).
4. Mirrored copies (same edits, byte-identical for the new `simulation/` tree):
   - `Rconnect-Drive/scripts/simulation/*` plus the edited
     `scripts/getDriverRoute.php` and `scripts/updateLocation.php`.
   - `Rconnect-Inspect/backend/mobile/simulation/*` plus the edited
     `backend/mobile/inspector/live_map.php` and `get_trip_summary.php`.

All response changes are additive (`simulation: true`), so older app builds
keep parsing the same fields.

Rollback: revert the code files in the same order (apps can stay on the
additive contract), then either `Disable` every session (state stops being
reported immediately) or delete the session rows. Dropping the two
`simulation_*` tables is safe once no code references them; leaving them in
place changes nothing.

## 3. Start a demo

1. Open `https://<site>/simulation.php` - no login, no cookies needed.
2. Pick a bus whose trip is `Scheduled` or `Departed` for today and press
   `Enable` -> creates that bus's session at the first waypoint (`idle`).
   Repeat for every bus you want in the demo; enabling one bus never touches
   the other sessions. A trip whose bus already has a session for that same
   trip shows `simulated` in the picker and its `Enable` is blocked (the
   engine reuses the session). If the bus's session belongs to an older trip
   (for example after the trips are regenerated with new ids), the picker
   shows `replaces earlier session`; enabling resets that old session - which
   clears the old trip's `SIM-` boardings - disables it, and creates the new
   trip's session, so simulated riders are always saved under the trip the
   apps are querying.
3. Press `Start` on a session row -> that bus goes `running`. Leave the page
   open: it polls `sessions` every 2 s and sends `tick` for every running
   session, so all simulated buses advance together; the server computes each
   position from elapsed time, so every client sees the same coordinates.
4. Per row: `Pause` freezes that bus at its current position, `Resume`
   continues it, `Reset` returns it to the start and clears its arrivals,
   `Disable` ends its session and real GPS tracking for that bus resumes.
   Acting on one row never changes another row's status or coordinates.
5. `Pickup` on a row arms pickup at that inspector's last reported position
   (from `inspectors_location`), wherever it is; the bus keeps driving and the
   console pauses it when it reaches the inspector, so the inspector can enter
   it and run the checklist. When the inspector has not reported a usable
   position yet, the Pinagpanaan (7/11) stop is used as the fallback. Other
   running buses keep moving. The inspector id input applies to whichever
   row's `Pickup` is pressed (blank = the inspector assigned to that bus).

Tuning the demo: edit waypoints/speeds in
`mobile/simulation/lib/simulation_routes.php`. Nothing is written back to
`route_points`; the stored route data stays authoritative and is only used for
the sequence and stop landmarks.

## 4. Safety rules

- While a session is active for a driver/bus, `updateLocation.php` refuses real
  uploads (`success: true, simulation_active: true`) and writes no row. The
  driver app also stops sending samples. Fares, passengers, payments,
  remittances and dispatch records are never touched by the simulation.
- Do not run two sessions for the same bus; the console reuses the existing
  session on `Enable` (and replaces it when it belongs to a different trip,
  so simulated boardings always land on the trip the apps query).
- When the page is closed the running buses freeze at their last position (same
  as `Pause`). Reopen and `Start`/`Resume` each row to continue; `Reset`
  rebuilds that row from the first waypoint.
- The console sends one `tick`/`state` request per session every 2 s, so the
  page's request count grows with the number of simulated buses; keep the demo
  fleet small (a handful of buses).
- The controller URL is unauthenticated by design. Keep it out of links and
  search indexes (`X-Robots-Tag: noindex` is sent); remove it after the demo.

## 5. Tests

Engine (pure math, no database):

```bash
php mobile/simulation/tests/simulation_engine_test.php        # 30 checks
```

Integration (creates and drops its own `rconnect_sim_test` database; skips
with exit 3 when the MySQL user cannot create a test database):

```bash
php mobile/simulation/tests/simulation_integration_test.php   # 37 checks
```

The integration test proves: invalid bus/trip refusals, status transitions,
tick persistence, stop arrivals, pause/resume/reset, completion, and that
`driver_location_history` / `driver_current_location` stay untouched.

## 6. Verification record

Recorded 2026-10-15 against the `localhost` database used by
`mobile/config/db_credentials.php` and `php -S` (PHP 8.5):

- DDL applied: `simulation_sessions` and `simulation_stop_events` present by
  `SHOW TABLES` / `DESCRIBE`; no other table altered (additive DDL only).
- `php -l` clean on `simulation.php`, `state.php`, `simulation_engine.php`,
  `simulation_routes.php`, and every edited endpoint.
- `simulation_engine_test.php`: 30 checks, 0 failures.
- `simulation_integration_test.php`: 40 checks, 0 failures (includes the PDO
  override used by the admin Trip Dashboard and the upload-refusal guard).
- Cold request (no cookies) `GET /simulation.php` -> 200, console renders.
- `?action=options` -> `{"success":true,...,"data":[]}` (no Scheduled/Departed
  trips today; the query filters by today's date and active statuses).
- `?action=state` -> `{"success":true,...,"active":false}`.
- Unknown action -> HTTP 400; `?action=tick` without a session ->
  `{"success":false,"message":"No active simulation session"}`.
- `mobile/simulation/state.php` -> `{"success":true,...,"active":false}`.
- `mobile/inspector/live_map.php?bus_id=<bus>` with a temporary session ->
  `simulation: true`, `driver_lat/driver_lng` and `driver_location` equal to
  the session's coordinates/speed; after disabling the session ->
  `simulation: false` and the stored-row path resumes. The temporary session
  was deleted afterwards.
- `dispatcher_queue_location_map('TRV-3')` with an active session -> the row
  carries the simulated coordinates and `simulation => true`; an unknown
  driver yields no row.
- Drive tests: `node tests/run_tests.js` exit 0 (all suites, including the new
  `simulation_status_test.ts`); `npx tsc --noEmit` clean.
- Inspect tests: `node tests/run_tests.js` exit 0 (982 assertions);
  `php tests/run_php_tests.php` 528 passed, 0 failed; `npx tsc --noEmit` clean.
- Mirrors: every file under `mobile/simulation/` is SHA-256 identical in
  `Rconnect-Drive/scripts/simulation/` and
  `Rconnect-Inspect/backend/mobile/simulation/`.

Environment limits recorded honestly: the local database is a partial mirror
(no `routes`/`route_points`, no `driver_current_location`), so the full admin
dashboard JSON and a device-to-device demo were not run here. The shared
engine, both override paths (mysqli and PDO), and the mirrored contracts are
covered by the suites above; re-run section 5 against the real database before
a defense.

### Multi-bus console update (2026-10-15)

The console now lists and drives every session at once. Verified with `php -S`
(PHP 8.5) against the same local partial-mirror database:

- `php -l simulation.php` clean; cold `GET /simulation.php` -> 200.
- `?action=sessions` -> `{success:true,data:[]}` with no sessions; with
  temporary rows a `disabled` session was excluded and a `completed` session
  stayed listed (the list does not depend on today's Scheduled/Departed trips).
- Two temporary sessions (opposite directions) started, ticked, paused, and
  disabled independently by `session_id` through the JSON actions: each
  advanced along its own route, the paused one stayed at its coordinates while
  the other kept moving, and disabling one left the other active. The
  temporary rows were deleted afterwards.
- The shipped console script was driven headlessly (Node + DOM shim) against
  the live endpoints - 16/16 checks: both rows render and advance per poll; the
  armed row auto-pauses at Pinagpanaan while the other keeps running; resume,
  pause, and disable act on their own row only.
- Picker flow - 13/13 checks with temporary trips and a temporary session:
  `data-simulated` flags match, `Enable` is blocked only for the simulated
  trip, and the `enable` request carries the selected trip/bus with no
  `session_id`, while `start`/`reset` carry the row's `session_id`.
- `simulation_engine_test.php`: 30 checks, 0 failures;
  `simulation_integration_test.php`: 40 checks, 0 failures (both re-run
  unchanged).
- Mirrors: the six files under `mobile/simulation/` remain SHA-256 identical in
  `Rconnect-Drive/scripts/simulation/` and
  `Rconnect-Inspect/backend/mobile/simulation/`.

Environment limits recorded honestly: the local mirror has no `routes` /
`route_points` / inspector tables and no Scheduled/Departed trips today, so the
successful `Enable` path and the `inspectors_location` write could not be
exercised locally (the headless picker run stubbed only the `enable` call, and
no temporary rows remain); re-run section 3 on the real database before a
defense.

## 7. End-to-end checklist

Start a simulation from the website, then confirm:

| Check | Verified by |
| --- | --- |
| (a) Driver app and inspector app show the same coordinates | Both read the same session: inspector through `live_map.php` (HTTP-tested), driver through `mobile/simulation/state.php` + `simulationStatus.ts` (unit-tested); feeds the existing `applyGpsFix` map path |
| (b) The admin map shows the simulated bus | Per-row override in `getDriverLocations.php` sharing the engine's payload; PDO path covered by the integration test |
| (c) Arrivals fire at designated `route_stops` | Integration test records the terminal and the Mayapyap landmark arrivals from `route_stops` |
| (d) Real uploads are refused | `updateLocation.php` guard; integration test proves blocked while active and unblocked after disable; Drive delivery treats the acknowledgement as accepted so the queue stays healthy |
| (e) Disabling restores real tracking | Live HTTP check after disable (`simulation: false`), integration test, and the Drive test proving delivery resumes |
| (f) Several buses simulate at once | `?action=sessions` lists each session; per-row controls post that row's `session_id`; headless console checks recorded under section 6 |
