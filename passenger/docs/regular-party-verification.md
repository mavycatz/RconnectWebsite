# Regular party verification — 2026-10-13

## Completed automated checks

- Existing PHP passenger suites: 199 checks passed.
- `regular_party_state_test.php`: 17 checks passed for owner scope, stable profile identity, revision checks, duplicate/size validation, empty selection, and rollback.
- `regular_party_request_test.php`: 61 checks passed using the actual request dispatcher, SQLite fixtures, shared fare engine, party services, history model, and receipt template.
- `homepage_party_test.js`: inline script syntax and DOM/API interaction checks passed for add/remove/type edit, PHP 54 total, rejected writes, boarding wait, restore, and delayed stale responses.

The request suite covers a PHP 30 primary plus PHP 24 Child or Senior = PHP 54;
individual stored fares remain individual. It verifies primary/companion boarding
rollback, destination rollback, restored party state, grouping, historical fare
stability for snapshotted trips, hybrid balance, paid-companion exclusion, and
driver cargo exclusion from passenger quotes and settlement.

These are automated fixtures, not a live browser, production database, or
PayMongo payment confirmation. Driver baggage files were not changed.

## Pending checkout decision

The existing client creates legacy `/v1/payment_intents` + QR Ph payment methods
and only exposes creation, attachment, and retrieval. It has no matching
invalidation/replacement operation for an already-issued QR. The documented
`/v3/qr/{id}/expire` endpoint uses a separate QR resource ID, which this flow does
not create or store; it cannot be substituted for an intent ID.

The regular party write path currently rejects edits while an issued QR is
active (`CHECKOUT_ACTIVE`). Expired checkouts use an amount/intent-specific
idempotency key on regeneration. This guard is interim: tasks 1.3 and 5.1 remain
unfinished until the user chooses the supported live-checkout behaviour or
authorizes compatible provider replacement integration. Do not count the guard
as completion of the requested editable unpaid-party checkout behaviour.

## Remaining verification

- One-Time quote/save regression coverage and full smoke flow.
- Automatic boarding entry-point coverage and completed dropoff/archive checks.
- Live regular browser matrix and narrow-screen rendering.
- End-to-end provider checkout replacement and payment verification.
