# Passenger Local State and Cache Reset

The passenger app persists a small, enumerated set of values in the browser and
mirrors some trip state to the server so a reload does not lose progress. This
document is the contract for those keys: what is stored, how long it lives, how
it is cleared, and what a reset must never touch.

Implementation source of truth: `views/layout/csrf.php` (`PassengerStorage`
registry). The registry's `PASSENGER_KEYS`, `PREFERENCE_KEYS`, and
`PASSENGER_KEY_PREFIXES` lists must stay in sync with this document; every
writer reads its key from `window.PassengerStorage.KEYS` so the lists cannot
drift.

## Key registry

### localStorage

| Key | Group | Written by | Removed by reset |
| --- | --- | --- | --- |
| `remember_passenger` | identity token | `LoginController` (trusted-device login redirect) | yes |
| `passenger_lang` | preference | `views/layout/language.php`, Profile language modal | no (survives) |
| `passenger_homepage_state` | payment progress | Homepage `saveHomepageState()` | yes |
| `passenger_active_trip_info` | active trip | Homepage `saveActiveTripInfo()` + destination update | yes |
| `rconnect_continuation_token` | trip resume | Homepage / One-Time continuation sync | yes |
| `rconnect_companion_dismissed` | trip UI | Homepage companion prompt | yes |
| `rconnect_ot_trip` | one-time trip | `OneTime/register.php` `rememberOneTimeTrip()` | yes |
| `rconnect_ot_trip_history` | one-time history (max 20) | `OneTime/register.php` | yes |
| `appInstalled` | preference (install flag) | Homepage PWA install handler | no (survives) |
| `hideInstallPrompt` | preference (install flag) | Homepage PWA install handler | no (survives) |
| `passenger_cached_trip`, `active_trip_info` | legacy | nothing (retained for old browsers) | yes |

### sessionStorage

| Key | Group | Removed by reset |
| --- | --- | --- |
| `ot_receipt_token_<tripId>` | one-time receipt token | yes (`sessionStorage.clear()`), also matched by prefix |
| `registered_passenger_code` | dead key (server uses the PHP session) | yes (`sessionStorage.clear()`) |

### Server-side state mirrored for restore

| Store | Keyed by | Cleared when |
| --- | --- | --- |
| `passenger_trip_state` (DB) | authenticated `passenger_id` | explicit "Clear cached app data" action (`POST /passenger/trip/clear-state`); regular drop-off flow |
| PHP session (`$_SESSION`) | browser session | logout, session invalidation |
| `passenger_sessions` / `passenger_remember_tokens` (DB) | passenger | logout revokes only that browser's token row and session |

## Reset paths

`PassengerStorage.reset()` is the single routine; it removes every
passenger-scoped key, every key matching `PASSENGER_KEY_PREFIXES`, and calls
`sessionStorage.clear()`. Preference keys survive. It runs:

1. **Logout** — the client reset runs before the logout POST; the server clears
   the `remember_passenger` cookie and revokes the current browser's remember
   token row (only that one).
2. **Session invalidation / expiry** — `PassengerSession.handleSessionInvalidated()`
   and `handleSessionExpired()` call `clearSession()`, which delegates to
   `PassengerStorage.reset()`.
3. **Account switch** — the incoming login starts from the same reset so the
   previous passenger's values cannot render.
4. **Explicit reset** — Profile → Settings → "Clear cached app data": shows a
   confirmation that states server records are preserved, runs the reset, POSTs
   `/passenger/trip/clear-state` for the authenticated passenger's own row, and
   reloads.

## What a reset never does

- It never deletes or modifies `passengers`, `passenger_trips`,
  `passenger_trip_history`, payment records, or production registration rows.
- It never touches another passenger's `passenger_trip_state` row, sessions, or
  remember tokens.
- It never removes preference keys (`passenger_lang`, `appInstalled`,
  `hideInstallPrompt`) or unrelated application data.

## Restore rules

- Server responses replace local caches on every load; a cached active trip is
  only rendered when the server fetch fails, never when the server reports no
  active trip.
- When the server reports no active trip (or a terminal one), the active-trip
  and payment-progress keys are removed instead of displayed.
- Cache entries are scoped to the owning passenger; a mismatch results in the
  cache being dropped, not rendered.

## Data audit (read-only)

One-time registrations store their number in `passengers.contact_number` and
intentionally have no password, so they are not login identities. The queries
below only report; nothing is modified. Run them manually before deciding on
any data work:

```sql
-- Rows whose number exists only in contact_number (one-time / walk-in style)
SELECT id, full_name, phone, contact_number, registration_type, status
FROM passengers
WHERE (phone IS NULL OR phone = '')
  AND contact_number IS NOT NULL AND contact_number <> '';
```

```sql
-- Rows whose stored phone is not in the canonical 09XXXXXXXXX form
SELECT id, full_name, phone,
       REPLACE(REPLACE(REPLACE(REPLACE(phone, '+63', '0'), ' ', ''), '-', ''), '(', '') AS folded
FROM passengers
WHERE phone IS NOT NULL AND phone <> ''
  AND REPLACE(REPLACE(REPLACE(REPLACE(phone, '+63', '0'), ' ', ''), '-', ''), '(', '') NOT REGEXP '^09[0-9]{9}$';
```

No automatic repair or backfill is part of this contract; the lookup paths fold
these formats at match time, so divergent rows still resolve.
