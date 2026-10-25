# Fix passenger homepage error: verification record

Change: `fix-passenger-homepage-error` (OpenSpec, spec-driven)

## Root cause

`passenger/views/layout/account-notifications.php` (included by
`passenger/views/Homepage/Index.php`) ran an unguarded query against
`passenger_account_notifications`. When the table was missing or unreadable the
PDOException escaped the page and the unhandled-exception handler replaced the
response with the generic "Something went wrong. Please refresh and try again."
page, taking down the whole homepage for a valid regular session.

`PassengerAccountSchema::ensure()` could not repair it: its readiness probe
never checked `passenger_account_notifications`, so a database where the account
migration ran before that table existed passed the probe and never received the
table.

## Changes

- `passenger/views/layout/account-notifications.php` - notification read is
  wrapped in `try/catch (\Throwable)`, logged, and degrades to "no
  notifications".
- `passenger/controllers/Account/RestrictionController.php` - the dismiss
  `UPDATE` is guarded the same way; the route always redirects.
- `passenger/views/Homepage/Index.php` - greeting reads
  `$_SESSION['passenger_name']` defensively with a translation fallback.
- `passenger/services/PassengerAccountSchema.php` - readiness probe covers
  `passenger_account_notifications`, `passenger_account_appeals`, and
  `passenger_identity_claims`; the additive path adds missing account-table
  columns per table, best-effort.
- Tests: `passenger/tests/passenger_homepage_render_test.js` (new),
  `passenger/tests/passenger_otp_flow_test.js` (new),
  `passenger/tests/passenger_account_schema_test.php` (new), and fixture
  support in `passenger/tests/fixtures/passenger_router_http.php` /
  `passenger/tests/fixtures/one_time_test_db.php`.
- Docs: `passenger/docs/passenger-account-storage.md` (new).

## OTP flow follow-up (same reported symptom)

The same error page was reported on the OTP screens. The OTP flows depend on
the same account tables (registration completion writes `passenger_identity_claims`
and, for verified documents, `passenger_account_notifications`), and several
auxiliary calls on the login/reset paths were unguarded:

- `passenger/controllers/Login_Account/LoginController.php` - trusted-device
  token creation after a successful OTP login no longer fails the login; the
  audit writes on the restricted/locked/invalid-credential paths are guarded
  (they keep their redirect response and log the failure).
- `app/auth/RememberAuth.php` - the `loginFromPassengerCookie` remember-cookie
  reissue is guarded.
- `passenger/controllers/ForgotPassword/ForgotPasswordController.php` and
  `passenger/controllers/Auth/ForgotPasswordController.php` - reset lookups and
  mailer construction degrade to the flow's non-disclosing acknowledgment or a
  friendly JSON error.
- `passenger/views/Create_Account/assets/scripts.php` - the registration OTP
  modal validates the fetched fragment (status + form marker) and shows a
  friendly message instead of injecting the error page.

OTP verification record (`node passenger/tests/passenger_otp_flow_test.js`,
27 checks):

- Login OTP completion redirects to the homepage and issues the
  trusted-device cookie; with the remember-token storage replaced by an
  incompatible view it still redirects with no error page and logs the failure.
- A wrong-password login with the audit table dropped still redirects to
  `/passenger/login` and logs the audit failure.
- Registration OTP verification completes with a passenger code and a stored
  passenger row; with the identity-claims table unrepairable it returns the
  clean JSON failure, leaves no passenger row behind, and never shows the
  generic error page.
- A login lookup failure (passengers table moved aside) redirects to the login
  page with a friendly message, logs the failure, and never shows the generic
  error page.

## Login lookup parameter binding (production HY093)

The production error log showed every `POST /passenger/login` failing with:

```
POST /passenger/login | SQLSTATE[HY093]: Invalid parameter number
@ .../passenger/models/Login_Account/loginmodel.php:81
```

`LoginModel::findPassenger` reused the named placeholder `:raw` twice in one
statement (`email = :raw` and `phone = :raw`). SQLite (the test fixture)
accepts a reused named parameter; native non-emulated MySQL prepared
statements — the production default — reject it with HY093, so the lookup
threw on every login and the unhandled exception produced the generic error
page.

Fix and hardening:

- `passenger/models/Login_Account/loginmodel.php` - the raw arms bind as
  `:raw_email` / `:raw_phone`; every placeholder appears exactly once. A scan
  of `passenger/` and `app/` sources found no other reused named placeholders.
- `passenger/controllers/Login_Account/LoginController.php` - the lookup is
  wrapped so a lookup/database failure redirects to the login page with a
  friendly message and is logged, never the generic error page.
- `passenger/tests/passenger_login_lookup_test.php` - the test connection is
  now a native-prepare guard that rejects reused named placeholders (mirroring
  MySQL), every existing resolution case runs through it, and a direct check
  pins the guard's rejection behavior. 14 checks, 0 failures. The suite fails
  on the pre-fix query.

## Profile edit "Update Failed" (same HY093 defect class)

The profile edit reported "Update Failed" and never saved. The duplicate
identity check in `ProfileController::update` reused the named placeholders
`:email` and `:phone` twice in one statement, which native MySQL prepared
statements reject with HY093; the controller's catch turned that into the
friendly-but-wrong "Unable to update your profile right now." error.

Fix and hardening:

- `passenger/controllers/ProfilePage/ProfileController.php` - the duplicate
  check now uses positional placeholders (`?`), matching the sibling
  duplicate checks in registration and OTP verification.
- `passenger/tests/fixtures/one_time_test_db.php` - the shared fixture
  connection now rejects a named placeholder reused within one statement,
  mirroring MySQL, so every fixture-backed suite fails on this class instead
  of tolerating it. The full suite set still passes.
- `passenger/tests/passenger_profile_update_test.js` (new) - a valid edit
  persists to the stored row and shows "Profile Updated"; a duplicate email
  shows "Update Failed" and leaves the stored row unchanged. 10 checks, 0
  failures.
- A corrected source scan (`prepare(` preceded by any character, not only
  `(`/space) found this query; the first scan had missed `->prepare(`. After
  the fix, no runtime SQL in `passenger/` or `app/` reuses a named placeholder;
  the remaining scan hits were docblock types (`array{id:int,...}`),
  `PDO::FETCH_ASSOC`, and `self::method` references.

Follow-up hardening (the failure was a dead end for the passenger):

- `passenger/views/ProfilePage/index.php` - a rejected edit now shows the
  specific recorded reason (`profile_update_error`) instead of a bare
  "Update Failed / Please try again". Validation failures name the rule (for
  example "Please enter your full name…" or "Please enter a valid Philippine
  mobile number…"), and a server-side failure reports its own message; the
  state is cleared after rendering like the status flag.
- `passenger/tests/passenger_profile_update_test.js` - extended with the
  invalid-name and invalid-phone cases; 17 checks, 0 failures.
- Live deployment check at the time of writing: a wrong-credentials probe
  against `https://rconnecttms.online/passenger/login` returned 302 to the
  login page with no error page, confirming the fixed login lookup is deployed;
  the profile fix must be deployed the same way for the modal to save.
- Profile photo preview: the edit modal's `#profilePreview` element was never
  updated when a file was chosen. `passenger/public/js/profile-preview.js`
  (loaded by the profile page) delegates the change event and shows the
  selected image immediately, replacing and releasing the previous object URL.
  `node passenger/tests/passenger_profile_preview_test.js` - 8 checks
  (selection, replacement with revoke, ignored unrelated/empty inputs);
  `node passenger/tests/passenger_profile_update_test.js` - 20 checks
  including the modal's preview element and the page's script include.

## Dispatcher receiving terminal (3 km arrival radius)

Reported: the receiving terminal did not receive completed buses.

Findings:

- `mobile/driver/getTripStatus.php` reported the GPS arrival only when the
  driver was within **300 m** of the destination terminal coordinates
  (`$distanceM < 300`); with terminal-coordinate and phone-GPS drift the report
  rarely fired, so the receiving terminal never learned the bus had arrived.
- The dispatcher's terminal-presence check used **0.5 km**
  (`dispatcher_queue_proximity_km()`), a different radius from the driver app.
- Confirming an arrival for a trip whose `to_terminal_id` is missing skipped
  the bus and queue movement entirely (`dispatcher_sync_bus_after_arrival` and
  `dispatcher_sync_queue_after_arrival` return early for `0`), so the bus never
  joined the receiving terminal's queue.

Fix (one shared 3 km radius):

- `mobile/driver/lib/arrival_report.php` - `driver_arrival_report_distance_m()`
  returns 3000 m; `mobile/driver/getTripStatus.php` uses it instead of the
  hard-coded 300 m.
- `dispatcher/api/helpers.php` - `dispatcher_queue_proximity_km()` is 3.0;
  added the pure `dispatcher_nearest_terminal_within_km()` resolver plus the
  schema-adaptive `dispatcher_terminals_with_coordinates()` and
  `dispatcher_arrival_terminal_from_location()` helpers (driver's latest
  reported location via the existing location map).
- `dispatcher/api/update_dispatch.php` - the arrival terminal is the trip's
  destination; when that is missing it is resolved from the driver's latest
  location within 3 km, falling back to the confirming dispatcher's terminal.
  A missing trip destination is written back, the bus and its operating-date
  queue entry move to that terminal, and the resolution
  (`arrival_terminal_id` / `arrival_terminal_source`) is recorded in the
  Trip Completed audit.

Verification: `php dispatcher/tests/dispatcher_arrival_radius_test.php` -
13 checks (both radii are 3 km, the endpoint uses the shared helper and the old
threshold is gone, nearest-terminal resolution incl. boundary/beyond-radius,
invalid and coordinate-less rows). `php -l` is clean on all four touched PHP
files.

Follow-up reported: simulated trips completed, but the receiving terminal had
no Arrive button to click.

- `dispatcher/api/assign_dispatch.php` created trips with **no terminal
  columns at all** (neither origin nor destination), so the trip belonged to
  no terminal's list and no arrival action could ever appear. It now records
  the session terminal as origin and a destination derived from the bus's
  current terminal (falling back to the configured opposite terminal), and
  logs both in the audit trail.
- `dispatcher/api/get_dispatches.php` only listed trips where
  `from_terminal_id` or `to_terminal_id` matched, hiding destination-less
  trips from the receiving terminal entirely. Destination-less on-route
  (Departed) trips are now visible to every terminal and carry a server-side
  `is_inbound` flag (`dispatcher_trip_is_inbound()`); `dispatcher/assets/js/
  trips.js` uses the flag so the Arrive/Confirm buttons render.
- Combined with the confirmation-side terminal resolution, a simulated or
  assigned trip can now always be received: the receiving terminal sees it
  while on route, clicks Arrive, and the bus moves into that terminal's queue.

Verification: `php dispatcher/tests/dispatcher_arrival_receipt_test.php` -
15 checks (inbound classification incl. destination-less cases, DB-backed
driver-location resolution within 3 km, assignment and list source
assertions). Existing dispatcher suites still pass:
`dispatcher_arrival_confirmation_test.php` 86, `dispatcher_odometer_arrival_
workflow_test.php` 42, `dispatcher_terminal_sync_test.php` 77,
`dispatcher_queue_synchronization_test.php` 63.

Second follow-up reported: there was still no Arrive button where the
dispatcher works (the arrival actions lived only on the Trips screen).

- `dispatcher/views/pages/home.php` + `dispatcher/assets/js/home.js` - the
  Home screen (the receiving terminal's working screen) now has an "Arriving
  at this terminal" section listing every inbound Departed trip with its
  action: on-route buses offer **Arrived** (reports the arrival), and reported
  arrivals offer **Confirm Arrival**, which hands the trip to the Trips
  screen and opens the odometer confirmation dialog (`arrive_trip`).
- `dispatcher/assets/js/core.js` - the inbound rule is shared
  (`tripIsInboundTo`), matching the server-side `dispatcher_trip_is_inbound`.
- `dispatcher/assets/js/trips.js` - opens the confirmation dialog when handed
  a trip from Home.
- Translations added for the new section title (en/tl/ilo).

Verification: `php dispatcher/tests/dispatcher_arrival_receipt_test.php` now
runs 21 checks including the Home-screen source assertions; `node --check`
passes on `home.js`, `core.js`, and `trips.js`.

## Historical KPL empty while fuel records exist

Reported: the dispatch/arrival screens show no historical KPL for a bus even
though the bus has `fuel_logs` (9) and `fuel_refuels` (7) rows.

Why it can be empty:

- `dispatcher_bus_historical_kpl()` only accepts a `fuel_logs` row with
  `kpl > 0` **and** measurement evidence (a recorded ending odometer, a
  recorded distance > 0, recorded liters, or an expected consumption). Rows
  written by the old dispatch form with the 9.0 fallback and no evidence, or
  rows from a dispatch still awaiting its arrival odometer, deliberately do
  not count.
- The trip-history generator only operated a pinned demo-window bus list
  (`FLEET_SCOPE_BUSES`, 8 buses) on the September-October 2026 window days;
  every other bus was skipped (`fleetScopeAllowsBus` false), so it never
  received generated trips and trip-level fuel logs with a real KPL.

Fix:

- `trip-history-generator.php` - `applyDefaultFleetScope()` now allows every
  `buses.bus_id` on window days when the demo-window fleet field is left
  blank (`generateHistory()` applies it before building the simulation
  context; an explicit `window_buses` pin still wins). The generator page
  field/placeholder documents "blank = all buses in the fleet".

Verification: `php admin/tests/fleet_scope_profile_test.php` - 31 checks
(window bounds, allow-list matching, pin/clear behavior, and the generator
source wiring). After re-running the generator with a blank fleet field, every
bus receives history and a readable historical KPL.

Follow-up: a January 2026 run produced 333 fuel logs / 332 refuels for the 19
operating buses ("missing: none" for operating buses), but the fleet has 26
buses. A rank simulation over the 31 window days proved the per-day driver
rotation covers every bus, so the seven never-operated buses are excluded for
another reason - almost certainly their `buses.status` / `operational_status`
(inactive, maintenance, archived, retired, out of service). The run summary
now answers this directly: `fuel_coverage.never_operated` lists each fleet bus
that produced no trips with its recorded status, and the page shows a
"Never operated (n of 26): BUS-x (status), ..." line. `php -l` clean and the
fleet-scope test now runs 33 checks (including the diagnostic wiring).

The dispatcher's historical KPL also showed a dash for buses with measurements
when the trip was viewed at the origin terminal: `get_dispatches.php` attached
`historical_kpl` only to inbound rows. It now attaches the bus's latest
qualifying KPL to every trip row (cached per bus), so the Trips cards and the
detail's "BUS KPL" show the reference at either terminal. The bus historical
KPL still resolves only from a qualifying measurement; a bus with none shows
the "No historical KPL available" state by design.
`dispatcher/tests/dispatcher_arrival_receipt_test.php` now runs 23 checks
(including the every-row attachment), with the odometer suites (42/86) still
green.

The dispatch form's Historical KPL stayed on its initial "—" because the fuel
reference call failed silently (`onBusSelected` only rendered on a successful
response). `dispatcher/assets/js/queue.js` now loads the reference through
`loadBusFuelInfo()`, recovers a rotated app session on 404/410, and shows
"Could not load - reopen the app" when the call fails - so the field states
which side is broken. `dispatcher/tests/dispatcher_historical_kpl_test.php`
inserts BUS-2107-shaped production rows (`fuel_log_id`, all odo aliases,
`distance_km`, `expected_fuel`) and proves the latest qualifying measurement
(7.38 KM/L, 2026-01-12) resolves - 9 checks including the form-wiring source
assertions.

## Publishing resolves bus and driver terminals

Reported: the admin queue shows `bus_dispatch_queue.terminal_id` NULL for some
buses when published, and the dispatcher then refuses the driver with "Driver
X is not assigned to this terminal. Assign the driver to this terminal before
dispatching."

Why the NULLs: `terminal_id` is only written when the administrator assigns a
bus to a terminal (`assign_terminal.php`); buses published without an
assignment keep NULL and the dispatcher falls back to trip data or the
configured origin. The driver check compares the driver's per-date assignment
(`driver_terminal_assignments`) with the dispatching terminal, so a missing or
stale assignment blocks the dispatch even when the driver is physically with
the bus.

Fix (`DispatchQueueService::syncTerminalsAtPublish`, run by `publish()`):
- an unassigned bus defaults to its validated configured home terminal
  (`buses.from_terminal_id`); explicit assignments are preserved and an
  unresolvable bus stays unassigned;
- the driver bound to a bus (`driver_bus_assignment`) is scoped to the bus's
  terminal for the operating date - fill when missing, re-sync when mismatched;
- every resolved change is recorded as a `Terminal Assignments Synchronized`
  audit event with counts. The confirmed-arrival flow still moves both the bus
  and its driver to the receiving terminal.

Verification: `admin/tests/dispatcher_queue_synchronization_test.php` - 69
checks (defaulting, driver-follows, republish re-sync, audit event);
`admin/tests/dispatch_queue_test.php` - 209 checks (now runs the
confirmed-arrival sync before asserting the receiving-terminal scope);
`dispatcher_terminal_sync_test.php` 77 and
`dispatcher_arrival_confirmation_test.php` 86 stay green.

Admin queue table layout: the Actions column was cut off because its fixed
budget (108px) clipped the four row buttons (~130px) behind the table wrap's
`overflow: hidden`. `admin/assets/css/dispatch-queue.css` now budgets 152px for
the actions column and the wrap scrolls (`overflow-x: auto`) instead of
clipping, so every row control stays reachable; the two containment checks are
pinned in `admin/tests/dispatch_queue_layout_test.php` (53 checks).

Follow-up: the assign-terminal modal still offered a separate "Driver
terminal" select, and it defaulted to the driver's existing (stale) terminal -
so choosing the bus terminal kept the mismatch. The field is removed:
`directory.php` renders only the bus terminal, `dispatch-queue.js` posts only
`bus_id`/`terminal_id`/`driver_id` (no `driver_terminal_id`), and the service
scopes the driver to the bus terminal. `admin/tests/dispatch_queue_layout_test.php`
asserts the field is gone (52 checks) and
`node admin/tests/dispatch_queue_ui_test.cjs` asserts no separate driver
terminal is posted and the released driver follows the bus terminal.

Routes: the admin queue was rendering stored `buses.route_name` values -
placeholders (`TBD`), id-pair garbage (`2 - 1`), stale wrong-direction names,
or nothing (`No route`). It also produced nonsense same-terminal labels
(`Cabanatuan-Cabanatuan`) for buses whose configured terminals are identical
or missing - yes, it was the bus records: the old display derived the
direction from those columns. The queue payload now derives it from the bus's
**current terminal** (its Terminal column): the active trip's route while on
route, else a route already recorded for that direction, else the
terminal-pair label (first words joined with a dash, e.g. `Cabanatuan-Rizal`);
the receiving terminal is the opposite of the Rizal <-> Cabanatuan pair, a
terminal is never its own destination, and placeholder, id-pair, and
wrong-direction stored values are never displayed. **Nothing is written onto
the bus record** - the dispatcher chooses the route at dispatch time, and
`dispatcher/api/get_routes.php` offers the direction's recorded/default route
without the `TBD`/`Unassigned` placeholders.
`admin/tests/dispatcher_queue_synchronization_test.php` - 77 checks (recorded
preference, `Cabanatuan-Rizal` default, garbage `2 - 1` ignored, stale
wrong-direction ignored, identical configured terminals, missing configured
destination, untouched bus records).

Dispatcher route list: the dispatch form's route dropdown was built from
`buses.route_name` and therefore offered the stored `TBD` placeholder.
`dispatcher/api/get_routes.php` now excludes `TBD`/`Unassigned` and still adds
the automatic route for the dispatcher's terminal direction (recorded for that
direction first, otherwise the terminal-pair default such as
`Cabanatuan-Rizal`), so the dispatcher always has the correct choices and
their selection becomes the trip's preferred route.
`dispatcher/tests/dispatcher_arrival_receipt_test.php` - 24 checks.

## Admin Settings page cleanup

The Settings page exposed hardcoded, non-functional controls: Security Settings
(password expiry, login attempt limit, 2FA - none enforced), Appearance (theme,
primary color - never applied), Quick Preferences (notifications, auto backup -
no consumers), and Quick Actions (dead restart/backup/maintenance buttons), plus
orphaned handlers (`save_facebook`, `system_restart_requested`, `last_backup`).

Only enforced settings remain:
- **General Settings** edits just `system_name` (read by
  `admin/api/Driver/ai-driver-report.php`);
- **Maintenance Mode** shows the stored state and toggles the flag the bootstrap
  gate enforces (Super-Admins exempt, everyone else sent to the maintenance
  page);
- System Overview / System Health / Activity Summary / Admin Info stay as
  read-only status (the overview no longer shows the unconsumed
  environment/version values).

`admin/tests/settings_cleanup_test.php` - 26 checks (removed files and registry
entries, kept-control wiring, no dead handlers or orphan preferences, and the
still-enforced consumers).

## End-to-end proof (fixture server, valid regular session)

Command: a fixture-server proof script seeding passenger 9 with a valid device
session and requesting `GET /passenger/home` against the real front controller
and isolated SQLite fixture.

```
notifications object before: table
full schema -> status: 200 | length: 321565 | greeting: true | error page: false
notifications object after drop: null
table dropped -> status: 200 | length: 321565 | greeting: true | error page: false
notifications object after the request that heals it: table
```

- Full schema: homepage renders 200 with the greeting, no error page.
- Notification table dropped: homepage still renders 200 with the greeting and
  no error page.
- After that request the dropped table is present again (recreated by the
  readiness check that same request).

## Suites (0 failures)

| Suite | Result |
| --- | --- |
| `node passenger/tests/passenger_homepage_render_test.js` | 22 checks passed (valid render, broken-storage resilience, dismiss redirect, drop-and-heal) |
| `node passenger/tests/passenger_otp_flow_test.js` | 27 checks passed (login OTP completion, remember-token and audit resilience, registration completion and clean failure, lookup-failure redirect, modal loader validation) |
| `node passenger/tests/passenger_profile_update_test.js` | 10 checks passed (edit persists and reports success, duplicate identity rejected without data change) |
| `node passenger/tests/one_time_landing_flow_test.js` | 63 checks passed |
| `node passenger/tests/one_time_scan_flow_test.js` | 42 checks passed |
| `node passenger/tests/homepage_restore_test.js` | passed |
| `node passenger/tests/passenger_state_headers_test.js` | 4 assertions passed |
| `node passenger/tests/passenger_login_messages_test.js` | 9 assertions passed |
| `node passenger/tests/passenger_login_switch_reset_test.js` | 9 assertions passed |
| `node passenger/tests/passenger_logout_reset_test.js` | 11 assertions passed |
| `node passenger/tests/passenger_session_invalidation_test.js` | passed |
| `node passenger/tests/passenger_reset_flow_test.js` | 14 assertions passed |
| `php passenger/tests/passenger_account_test.php` | 48 checks passed |
| `php passenger/tests/passenger_login_lookup_test.php` | 13 checks, 0 failures |
| `php passenger/tests/one_time_schema_test.php` | skipped by design (no application database connection in this environment; the suite reports a skip and exits 0) |
| `php passenger/tests/passenger_account_schema_test.php` | 12 checks passed (own isolated SQLite fixture) |

The two `PassengerAccountSchema column provisioning skipped...` log lines during
the schema test are the expected best-effort logs for the deliberate
missing-table and unrepairable-view cases.

## Scope boundary (task 3.3)

- Changed files are confined to `passenger/` plus `app/auth/RememberAuth.php`
  (the guard on the passenger remember-cookie reissue) and this change's
  OpenSpec artifacts (listed above); no Driver, Inspector, Dispatcher, Admin,
  or Finance files were touched.
- No new migration file. `006_passenger_account_management.sql` remains the
  schema definition of record and is executed unchanged; the added per-table
  column provisioning mirrors its column set (added columns are nullable so
  the heal cannot fail on a non-empty legacy table).
- The healing path contains only `CREATE TABLE IF NOT EXISTS` and
  `ALTER TABLE ... ADD COLUMN` statements; no `DROP`, `DELETE`, or `TRUNCATE`
  is reachable from it. The unrepairable-storage test confirms an incompatible
  object is left intact.
- No application data was modified while producing this record; the fixture
  database is an isolated temporary SQLite file.

## Follow-up

- Deploying this code to an affected database heals it on the first passenger
  request; no operator-run migration is required.
- Rollback is a code revert; the added tables/columns are inert and already
  part of the intended schema.
