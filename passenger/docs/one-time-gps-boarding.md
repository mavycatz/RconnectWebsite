# One-Time GPS boarding

The One-Time passenger flow derives the boarding point and the selectable
destinations from the scanned bus's live or simulated position, so a passenger
cannot pick a stop the bus has already passed or that lies behind it.

## Flow

1. **Scan** — `passenger/scan.php` validates the bus QR and the active
   `bus_trips` trip, then records a location snapshot in the session as
   `qr_location_snapshot`: bus id, trip id, coordinates, record timestamp,
   source (`driver_location_history`, `driver_current_location`, or
   `simulation`), freshness, travel direction, and the matched stop. The same
   scan **registers the boarding point** in the session as `ot_boarding_lock`
   (bus, trip, matched fare stop, source, freshness, coordinates, timestamp):
   once the passenger has entered the flow the boarding point is fixed and
   cannot be changed. A new scan replaces the lock; a scan whose position
   cannot resolve a stop clears it so the register page registers the point on
   the first successful resolution instead. The scan itself always succeeds;
   an unusable position is stored with its reason. Each outcome is written to
   `storage/logs/passenger_qr_scan.log` as a `bus_location_snapshot` entry.
2. **Trip info** — `POST /passenger/one-time/trip` (`OneTimeService::tripInfo()`)
   returns the existing payload plus:
   - `bus_position`: availability, usability, reason, source, coordinates,
     freshness, age, and matched distance. For a registered boarding the
     position echoes the scan snapshot and carries `locked: true`;
   - `boarding`: the auto-determined stop (`id`, `stop_id`, `destination`,
     `display_order`, `km_marker`, coordinates) or `null`. When a boarding
     point is registered it is returned verbatim regardless of later bus
     movement;
   - `boarding_locked`: `true` when the boarding point came from the session
     registration;
   - `destinations`: the local option (the boarding stop itself, flagged
     `is_boarding`, same-stop minimum fare) comes first while the bus has not
     passed it, then only the stops that remain ahead of the further of the
     boarding stop and the bus's current stop in the trip's `display_order`.
     Stops the bus has already passed drop out (a missing bus position falls
     back to the registered boarding order). When the bus is at the last fare
     stop nothing remains ahead, so the local option is the only entry, flagged
     `same_as_boarding` for preselection (same-stop, minimum fare);
   - `boarding_error`: `{code, message}` when no boarding point can be
     determined.
3. **Boarding step** — read-only: the app shows the determined stop with its
   source/freshness, labelled "Boarding point locked at scan" when the point
   is registered. When `boarding_error` is present the step shows the error
   and a Retry that re-fetches the trip info (a successful resolution then
   registers the point). The boarding point cannot be changed manually.
4. **Destination step** — lists only `destinations` (never a passed stop).
   While the step is open the list is refreshed every 45 s so stops the bus
   passes disappear without the passenger doing anything. At the last stop the
   `same_as_boarding` entry is preselected so the flow still completes: the
   fare engine's same-stop rule prices it at the minimum fare, and the booking
   is saved with the boarding stop as the destination. The fare preview
   (`POST /passenger/one-time/fare`) prices the selected boarding/destination
   pair from the trip's `fare_pricing` rows through the fare engine and does
   not re-read the live bus position, so a bus that moves while the passenger
   decides cannot turn a valid selection into "Fare unavailable". A stop that
   is not part of the trip, a destination behind the boarding point, or a stop
   the bus has already passed is refused (`DESTINATION_PASSED`).
5. **Registration** — `POST /passenger/one-time/register`
   (`OneTimeService::registerBooking()`) validates against the **registered
   boarding point** when one exists: the submitted boarding id must equal the
   registered stop and the destination must be ahead of it, so a bus that
   moved after the passenger entered cannot change or block the booking. With
   no registration it re-resolves the latest bus position and rejects a
   boarding id that is not the currently determined stop (`INVALID_BOARDING`)
   or a destination that is not ahead (`INVALID_DESTINATION`). Duplicate
   submissions return the original trip before the boarding gate so a retry
   still succeeds.
6. **Persistence** — the scan snapshot is stored on the primary
   `passenger_trips` row (`scan_latitude`, `scan_longitude`,
   `scan_recorded_at`, `scan_stop_id`, `scan_location_source`). Companions
   inherit the primary's bus, trip, boarding point, and destination.

## Entry routing (scan, landing, registration)

`passenger/scan.php` and the One-Time landing/registration controllers route on the shared classification `passenger_entry_session_state()` (`passenger/bootstrap.php`):

| Session state | Outcome |
| --- | --- |
| No identity | Landing with both choices |
| One-Time identity | Landing / registration (login-free) |
| Usable regular identity (`valid`, or `legacy` adopted on use) | Account homepage shortcut |
| Restricted account (suspended / blocked) | Account restriction notice |
| Invalid identity (deleted row, revoked or disabled device session) | Demoted to a visitor: identity cleared, `qr_bus` / `qr_trip` / location snapshot preserved, landing with both choices |
| Unverifiable state (service or query failure) | Served as a visitor for the request without clearing the identity |

Choosing One-Time never requires login; choosing Regular opens `/passenger/login`. The `reason=unavailable` login outcome stays reserved for protected routes whose account verification genuinely fails (`passenger_require_auth()`), never for the choice path.

## Position source and freshness

`passengerBusPositionForTrip()` (`passenger/services/BusPositionService.php`):

1. an active simulation session (`simulation_override_for_pdo`) is treated as
   current, exactly like the admin Trip Dashboard override;
2. the newest usable `driver_location_history` row for the trip's driver, then
   the trip's bus;
3. the `driver_current_location` snapshot as the final fallback.

Live positions classified STALE (> 120 s, or clock-skewed) block boarding
selection; a missing or foreign-bus position is never substituted with the
passenger's own location.

## Route matching

Coordinates come from the existing centralized reference
`PASSENGER_DESTINATION_ROUTE_SEGMENTS` in
`passenger/services/DestinationRouteGeometry.php` (the same table the
destination alerts use). `passengerMatchBusPositionToOrderedStops()` measures
point-to-segment distance to each ordered fare stop's segment (falling back to
the stop's own `route_stops` coordinate when the reference does not cover it)
and accepts the nearest within `BUS_SEGMENT_MATCH_MAX_KM` (default 2.0).
Ahead/behind always follows the trip's `display_order`, so the reference's
non-monotonic segments (Mataas na Kahoy / Gen. Ricarte) cannot flip the order.

## Error codes

| Code | Meaning |
| --- | --- |
| `BUS_LOCATION_UNAVAILABLE` | No usable live or simulated position for the bus |
| `BUS_LOCATION_STALE` | Newest position is older than the accepted window |
| `BUS_OFF_ROUTE` | Position is farther than `BUS_SEGMENT_MATCH_MAX_KM` from every segment |
| `ROUTE_UNSUPPORTED` | Trip route is not one of the known corridors |
| `NO_ROUTE_STOPS` | The route has no fare stops |
| `INVALID_BOARDING` | Fare quote: boarding id is not a stop of the trip. Registration: submitted boarding id is not the registered boarding point (when one is locked) or not the currently determined stop |
| `INVALID_DESTINATION` | Fare quote: destination id is not a stop of the trip or is behind the boarding point. Registration: destination is not in the remaining-ahead list or is behind the boarding point |
| `DESTINATION_PASSED` | Destination selection or registration: the stop is ahead of the boarding point but the bus has already passed it |

## Verification procedure

Automated (from the project root):

```bash
php passenger/tests/route_segments_test.php
php passenger/tests/bus_boarding_match_test.php
php passenger/tests/bus_position_test.php
node passenger/tests/one_time_scan_flow_test.js
node passenger/tests/one_time_landing_flow_test.js
node passenger/tests/one_time_trip_info_test.js
node passenger/tests/one_time_boarding_ui_test.js
node passenger/tests/one_time_registration_test.js
```

Manual / integration, with a simulated bus (Simulation Console) at each
position; the passenger app should show the listed boarding point and offer
only the destinations after it:

| Bus position | Boarding point (Cabanatuan→Rizal) | First destination |
| --- | --- | --- |
| Cabanatuan terminal | Cabanatuan Central Terminal–Daang Sarile | Valdefuente |
| Pinagpanaan | Pinagpanaan, Talavera | Baluga/Sto. Niño |
| Homestead 1 | Homestead 1, Talavera | Homestead 2 |
| Del Pilar | Del Pilar, Rizal | Rizal/Pob. Sur Terminal |
| Rizal/Pob. Sur Terminal | Rizal/Pob. Sur Terminal | (none – no destinations remain) |

Reverse direction (Rizal→Cabanatuan bus):

| Bus position | Boarding point | First destination |
| --- | --- | --- |
| Rizal/Pob Sur Terminal | Rizal/Pob Sur Terminal | Del Pilar, Rizal |
| Del Pilar, Rizal | Del Pilar, Rizal | Aglipay, Rizal |
| Aglipay, Rizal | Aglipay, Rizal | Mangahan, Rizal |
| Cabanatuan Central Terminal | Cabanatuan Central Terminal | (none – no destinations remain) |

The reverse reference starts at the Rizal terminal origin (Rizal/Pob Sur
Terminal → Del Pilar), so a bus still at the departure terminal matches the
route instead of failing with `BUS_OFF_ROUTE`. A fare list that starts at
Aglipay (no terminal stop of its own) still matches the origin position and
attributes it to Aglipay through the leading origin segments.

Stale/unavailable check: disable the bus's simulation session and let the live
GPS rows age out; the boarding step must show the error card with Retry, and no
booking can be submitted.

## Regular passenger flow parity

The regular homepage flow uses the same bus-position model:

- **Boarding recording** — `/passenger/scan/store` (`ScanController`) now
  records the boarding stop from the bus's current (simulated or live) position
  through `passengerResolveBusBoarding()`, not from the passenger's phone
  location. The existing phone-GPS `matchRouteStop()` remains the fallback for a
  trip with no usable bus position. Once recorded the boarding is fixed:
  `RegularPartyService::lockBoardingOwner` returns the existing record on every
  later scan, and refreshes restore it from the active trip.
- **Adaptive destinations** — `/passenger/api/destinations`
  (`DestinationController`) keeps the recorded boarding order as the floor and
  raises the threshold to the bus's current fare-stop order, so stops the driver
  has already passed are not offered. The recorded boarding stop is flagged
  `is_boarding`, the bus's current stop `is_current`, and `progress_order` /
  `current_stop_order` report the thresholds. The homepage refreshes the list
  during its active-trip poll (throttled, and it never clears the last good list
  on a background failure) until the destination is locked.
- **Selection enforcement** — `/passenger/trip/select-destination`
  (`SelectDestinationController`) applies the same order-based check: a stop
  behind the further of the recorded boarding and the bus's current stop is
  refused with `DESTINATION_PASSED`, so the restriction cannot be bypassed with
  a stale client list or a direct request. The pass/not-pass decision always
  comes from the trip's `route_stops` / `fare_pricing.display_order` sequence
  (the GPS position selects the stop, never a raw proximity threshold), and an
  already saved destination can still be re-confirmed to restore its fare.
- **Fare** — unchanged: the amount still comes from the trip's `fare_pricing`
  rows through the existing fare engine for the recorded boarding/destination
  pair.
