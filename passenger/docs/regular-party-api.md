# Regular passenger party API

`GET` and `POST /passenger/companions/party` use the authenticated regular
passenger's identity. They never accept an owner ID from the body. One-Time
passengers continue using the existing One-Time endpoints.

## Draft selection

GET without `trip_id` discovers an active trip first; otherwise it returns the
persisted draft, not the saved-profile catalogue. A late draft POST after boarding
returns 409 `ACTIVE_TRIP`, prompting a fresh read before changing the trip party.
POST replaces the selected list (an empty list removes all selected companions):

```json
{"revision":0,"companions":[{"full_name":"Child Rider","companion_type":"child"}]}
```

The response has `success`, `mode: "draft"`, `trip_id: null`, `revision`, and
`companions`. Each accepted companion includes a stable `profile_id`,
`full_name`, `companion_type` (`child`, `senior`, or `other`), and
`declared_id_number`. Senior ID is optional. Selecting an existing profile uses
its `profile_id`; foreign profile IDs are rejected. Declared type can be edited
without changing the identity or a completed trip's declaration.

The selection is JSON in the existing owner-scoped `passenger_trip_state` table.
Writes require the revision returned by the latest read. Stale revisions return
409 `STALE_PARTY`; validation failures return 422; foreign profiles return 403.
Duplicate identities/names and more than five companions are rejected atomically.
An identical accepted write is idempotent. Revision advances when selection changes.

## Quote

For a pre-boarding quote pass `trip_no`, `boarding_point`, `destination`, and
optionally `boarding_fare_id`, `boarding_stop_id`, and `destination_id`.
Pricing uses the backend fare engine and the owner's stored passenger type;
client fare/discount/total fields are not read. For Route X fixture stops A → C,
the primary's standard fare is PHP 30 and Child is PHP 24:

```json
{"quote_available":true,"party_total":54,"online_amount_centavos":5400}
```

`breakdown.lines` contains the primary and companions with route,
base/discount/final fare and declared companion labels. Missing/unresolvable
route data returns `quote_available: false`, with null breakdown and amounts.
Driver cargo/baggage is not part of this API or passenger checkout.

## Trip-owned selection

GET with `trip_id` reads the linked trip travellers; it does not merge an old
draft. POST includes `trip_id`, `revision`, and the full replacement selection.
The response uses `mode: "trip"` and that trip ID, preserving individual trip
fares instead of replacing the primary fare with a party total. Changes are
transactional. Missing/foreign/One-Time trips return 404. Completed, cancelled,
paid, or verified-cash-settled parties return 409 `PARTY_SETTLED`.

An active provider QR returns 409 `CHECKOUT_ACTIVE` until checkout replacement
is integrated; this is an interim guard, not completion of task 5.1. It prevents
changing the amount behind an already-issued payment QR.

The full `party_total` is distinct from `online_amount_centavos` (remaining online
balance, excluding previously paid companions or verified hybrid cash). Cash-only
trips return zero online balance. Settled trips retain their full fare in details.

## Verification

Run `php passenger/tests/regular_party_state_test.php` and
`php passenger/tests/regular_party_request_test.php`. Request fixtures call the
same validation/dispatch method used by the HTTP route, with SQLite records and
the real fare engine. Live MySQL, session/CSRF routing, provider replacement,
boarding integration, and browser checks are separately tracked in OpenSpec.
