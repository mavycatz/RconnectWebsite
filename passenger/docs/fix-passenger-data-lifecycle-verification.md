# Passenger Data Lifecycle - Verification Record

Change: `fix-passenger-data-lifecycle` (openspec/changes/fix-passenger-data-lifecycle).
Recorded 2026-10-16 on Windows, PHP 8.5.1, Node (x64), XAMPP MariaDB 10.4 for the
PHP schema tests. Fixture suites drive the real front controller (`passenger/index.php`,
`passenger/scan.php`) and the real controllers against isolated SQLite databases created
under the approved temp root; they never touch the application database.

## Suites run (task 6.1)

| Suite | Result |
| --- | --- |
| `php passenger/tests/passenger_account_test.php` | 48 passed, 0 failed |
| `php passenger/tests/one_time_schema_test.php` | 10 passed, 0 failed |
| `php passenger/tests/route_segments_test.php` | 125 passed, 0 failed |
| `php passenger/tests/one_time_live_trip_test.php` (new) | 14 checks, 0 failures |
| `php passenger/tests/passenger_login_lookup_test.php` (new) | 13 checks, 0 failures |
| `php passenger/tests/bus_availability_test.php` | 16 passed, 0 failed |
| `php passenger/tests/bus_boarding_match_test.php` | 53 passed, 0 failed |
| `php passenger/tests/bus_position_test.php` | 57 passed, 0 failed |
| `php passenger/tests/companion_fare_test.php` | 42 passed, 0 failed |
| `php passenger/tests/fare_base_test.php` | 9 passed, 0 failed |
| `php passenger/tests/id_document_scan_test.php` | 98 passed, 0 failed |
| `php passenger/tests/regular_party_state_test.php` | 17 passed, 0 failed |
| `node passenger/tests/one_time_scan_flow_test.js` | 42 passed |
| `node passenger/tests/one_time_trip_info_test.js` | 67 passed |
| `node passenger/tests/one_time_registration_test.js` | 69 passed |
| `node passenger/tests/one_time_landing_flow_test.js` | 63 passed |
| `node passenger/tests/one_time_boarding_ui_test.js` | 51 passed |
| `node passenger/tests/one_time_e2e_simulation_test.js` | 74 passed |
| `node passenger/tests/one_time_trip_release_test.js` (new) | 27 assertions passed |
| `node passenger/tests/regular_boarding_record_test.js` | 13 passed |
| `node passenger/tests/homepage_party_test.js` | passed |
| `node passenger/tests/active_destination_test.js` | passed |
| `node passenger/tests/regular_companion_lock_test.js` | passed |
| `node passenger/tests/regular_destinations_progress_test.js` | 19 passed |
| `node passenger/tests/destination_dropdown_test.js` | passed |
| `node passenger/tests/companion_auto_submit_test.js` | passed |
| `node passenger/tests/passenger_storage_reset_test.js` (new) | passed |
| `node passenger/tests/passenger_profile_reset_action_test.js` (new) | passed |
| `node passenger/tests/passenger_session_invalidation_test.js` (new) | passed |
| `node passenger/tests/passenger_logout_reset_test.js` (new) | 11 assertions passed |
| `node passenger/tests/passenger_login_switch_reset_test.js` (new) | 9 assertions passed |
| `node passenger/tests/homepage_restore_test.js` (new) | passed |
| `node passenger/tests/passenger_state_headers_test.js` (new) | 4 assertions passed |
| `node passenger/tests/passenger_login_messages_test.js` (new) | 9 assertions passed |
| `node passenger/tests/passenger_reset_flow_test.js` (new) | 14 assertions passed |
| `node passenger/tests/passenger_one_time_identity_test.js` (new) | 8 assertions passed |

### Pre-existing failures outside this change

Two suites fail on the companion lock rule introduced by the in-flight
`fix-regular-companion-fare-and-trip-details` change, before and independently of
this change:

- `php passenger/tests/regular_party_request_test.php` - `COMPANIONS_LOCKED` for trip 20.
- `node passenger/tests/regular_fare_integration_test.js` - `addAttach` refuses while
  `Companion.locked` is true.

Evidence: the rule lives in the untouched `passenger/services/Companion/RegularPartyService.php`
(lines 244 and 396), the fixture's `GET /passenger/companions/party?trip_id=20` reports
`companions_locked: true` because the seeded trip has a destination selected, and
`regular_companion_lock_test.js` (which asserts the lock) passes. No file this change
touched participates in that decision. These suites need updating where the companion
change lands; they are not regressions of this change.

## Acceptance tests (task 6.2)

1. **Clearing local passenger data does not delete server-side accounts or trip history** -
   `passenger_storage_reset_test.js` (reset removes only the allow-listed passenger keys),
   `passenger_profile_reset_action_test.js` (the reset action posts only
   `/passenger/trip/clear-state`), `one_time_trip_release_test.js` (no row counts change
   on uncertain or failed lookups; history rows survive a new booking).
2. **Reopening after a legitimate reset does not restore stale state** -
   `passenger_storage_reset_test.js` plus `homepage_restore_test.js` (server `no active
   trip` removes both the trip and payment-progress caches; a mismatched cache is dropped).
3. **Logout and account switch do not expose the previous passenger** -
   `passenger_logout_reset_test.js` (remember cookie expired server-side, only that
   browser's token row revoked, session revoked), `passenger_login_switch_reset_test.js`
   (the login redirect runs `PassengerStorage.reset()` before writing the new token),
   `passenger_session_invalidation_test.js` (401/403 cleanup removes the real keys),
   `homepage_restore_test.js` (B never renders A's cache).
4. **A One-Time passenger completes a trip and scans a different bus** -
   `one_time_trip_release_test.js` (completed/cancelled/archived/dropoff/ended all reach
   `one-time/landing`; a fresh booking on another bus succeeds afterwards).
5. **Refresh during an active trip does not reset it** - `one_time_trip_release_test.js`
   (a live row keeps `OT_RESUME_TRIP_ID = <id>` at boot; the same-trip scan still opens
   the receipt) and the unchanged `one_time_e2e_simulation_test.js`.
6. **Repeated scanning/submission creates no duplicate trips or payments** -
   `one_time_registration_test.js` (replayed submission token returns the original trip)
   and `one_time_trip_release_test.js` (checkout on a paid trip is refused, no duplicate
   row).
7. **A different valid `trip_id` permits a new trip while same-trip duplicates stay
   blocked** - `one_time_trip_release_test.js` cases 1-3 and
   `one_time_live_trip_test.php` (id-primary matching; the id-colliding trip_number can
   never match another trip).
8. **An existing phone or email resolves through the intended lookup** -
   `passenger_login_lookup_test.php` (`09`/`63`/`+63`, spaced and dashed stored formats,
   padded/mixed-case email) and `passenger_login_messages_test.js` through the real
   controller.
9. **Missing profile data, invalid credentials, and nonexistent accounts are handled
   accurately and securely** - `passenger_login_messages_test.js` (byte-identical generic
   text for unknown vs wrong password; archived/locked messages only after verification;
   deleted accounts resolve as nonexistent) and `passenger_reset_flow_test.js` (uniform
   acknowledgment for known/unknown; no OTP session for unknown; invalid code never
   dead-ends with "Session expired"; resend available and uniform).
10. **Network failures and stale responses do not stick** - `homepage_restore_test.js`
    (fetch failure shows a retry affordance, renders no cached card, recovers on the next
    success) and `passenger_session_invalidation_test.js`.

## Audit query verification (task 5.6)

The two read-only audit queries in `passenger/docs/passenger-local-state.md` were
extracted from the document and executed against fixture-shaped SQLite data (with MySQL's
`REGEXP` mapped): 1 contact-only row and 1 non-canonical format row were reported and the
row count was unchanged before/after. The queries are SELECT-only; no repair or backfill
is part of this change.

## Scope boundary (task 6.3)

All modified/added files are inside `passenger/`. No Driver, Inspector, Dispatcher,
Admin, or Finance file was touched (`app/auth/RememberAuth.php` was read-only reference
material; the logout change lives in `passenger/index.php`).

**Dependency note (not changed here):** the admin passenger directory
(`admin/api/Passenger/PassengerQuery.php`) searches `email`/`phone` only and does not
project or search `contact_number`, so one-time registrations remain invisible to that
search until the admin module is updated. This change documents the divergence and keeps
one-time numbers out of passenger login by design.

## Environment limits recorded honestly

- Verification ran against isolated SQLite fixtures through the real controllers and
  routes; no interactive device/browser session was driven. The Node DOM harnesses cover
  the client behaviors (storage reset, restore, invalidation, cache ownership).
- The public one-time pages and lookup fixtures exercise the real PHP code paths; SMTP is
  unavailable in the environment, so mailer failures are exercised (and intentionally
  masked) rather than successful email delivery.
- The MySQL-only REGEXP audit query was verified through the SQLite translation with a
  registered `regexp` function; re-run both queries against the production database
  before deciding on any data work.
