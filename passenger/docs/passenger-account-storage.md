# Passenger account storage: readiness and self-healing

The passenger account surfaces (homepage notifications, restriction notice, appeals,
identity claims) read and write tables that were added by
`passenger/database/migrations/006_passenger_account_management.sql`. Not every
database in the field received that migration in full, so storage readiness is
verified and repaired at runtime instead of assuming a provisioning step ran.

## Readiness probe

`PassengerAccountSchema::ensure($db)` runs on every passenger request
(`passenger_ensure_schema_once()` in `passenger/bootstrap.php`) and first checks
that every table and column the account surfaces depend on exists:

- `passengers`: `account_status`, `suspension_starts_at`, `suspension_ends_at`,
  `restriction_remarks`, `login_locked_until`
- `passenger_id_sequences` (`prefix`)
- `passenger_code_history` (`id`)
- `passenger_account_events` (`id`)
- `passenger_account_notifications` (`id`, `passenger_id`, `read_at`)
- `passenger_account_appeals` (`id`, `passenger_id`, `status`)
- `passenger_identity_claims` (`identity_kind`, `identity_value`, `passenger_id`)

The probes are metadata-only (`SELECT ... LIMIT 0`); when they all pass the
result is cached for the rest of the request and no schema-changing statement
runs.

## Self-healing (additive, idempotent)

When any probe fails, `ensure()` runs the additive path:

1. Missing `passengers` columns are added (one `ALTER TABLE ... ADD COLUMN` per
   missing column).
2. Missing columns on the account tables are added per table
   (`passenger_account_notifications`: `event_id`, `message`, `read_at`;
   `passenger_account_appeals`: `status`, `review_note`, `reviewed_by`,
   `reviewed_at`).
3. `006_passenger_account_management.sql` executes statement by statement; its
   `CREATE TABLE IF NOT EXISTS` statements create any table that is missing
   entirely. The migration file remains the schema definition of record; no
   inline DDL duplicates it.

Every statement is additive. Nothing in this path drops a table, removes a
column, or deletes a row.

## Failure behavior

Provisioning is best-effort. A database that rejects the DDL (permissions,
locks, read-only replicas, engine differences) produces a logged message and
the request continues. The failure is logged as:

- `PassengerAccountSchema column provisioning skipped for <table>: <message>`
  (per-table column adds)
- `PassengerAccountSchema migration failed: <message>` (the 006 statement pass)

`RuntimeException('Passenger account storage is not ready...')` is only raised
when `ensure()` is called inside an open transaction; callers that create
accounts or change status run the check before starting their transaction.

## Guarded reads

Readiness can still fail or race, so every optional read over this storage is
guarded and degrades instead of failing the page:

- `passenger/views/layout/account-notifications.php` wraps the notification
  read in `try/catch (\Throwable)`: a missing or unreadable table renders the
  page with no notifications and logs
  `Passenger account notifications read failed: <message>`.
- `passenger/controllers/Account/RestrictionController.php` wraps the dismiss
  `UPDATE` the same way and always redirects; a failed write logs
  `Passenger account notification dismiss failed: <message>`.

The homepage's own data reads (system settings, passenger QR record, saved
payment method) follow the same pattern: optional widget failures must never
replace the page with the generic service error.

## Verification

- `php passenger/tests/passenger_account_schema_test.php` - probe readiness,
  missing-table heal, legacy-shape column adds with row preservation,
  idempotency, and best-effort behavior against the isolated SQLite fixture.
- `node passenger/tests/passenger_homepage_render_test.js` - homepage renders
  (200) with readable, broken, and dropped notification storage; the dropped
  table is recreated by the request that needs it.
