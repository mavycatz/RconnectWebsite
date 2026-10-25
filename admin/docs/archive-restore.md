# Admin archive and restore

Role Management and Bus Management reuse the existing custom alert overlay,
Admin reason-entry adapter, Security Authorization Code settings, verification
tokens, original records, and `audit_trail` storage. There is no new archive
table, copied record, or email OTP system.

## Action contract

Archive/restore requires an authenticated allowed admin, a confirmed reason of
at least five trimmed characters, and the existing single-use token bound to
actor/action/target/exact payload. Privileged administrator targets require a
Super Admin. The shared guard delegates archive/restore to the Admin lifecycle
service after consuming authorization; legacy endpoint handlers do not perform
a second mutation or second audit event.

Changes use the existing sources:

| Source | Original status record | Security target/reference |
|---|---|---|
| Driver | `drivers` | `driver:<drivers.id>` |
| Inspector/Dispatcher | `inspector_accounts`, reached through `inspectors.account_id` | `inspector:<inspectors.inspector_pk>` |
| Finance Officer/Manager | `users` | `finance:<users.id>` |
| Admin/Super Admin | `users` | existing privileged target and `users.id` |
| Bus | `buses` | `bus:<buses.id>` |

Existing status enums are extended only when Archived is missing, preserving
accepted values, nullability, and defaults. This readiness step precedes data
transactions. Archive preserves original IDs/relationships and writes Archived
plus a disabled `is_active` where that existing field is present.

The same transaction stores archive/restore events in existing audit history;
a failed event rolls back the status mutation. Structured events use canonical
`source:ref`, reason, prior status/activation state, and actor identity. Legacy
Drivers/Inspectors singular/plural module names and exact numeric references,
or exact Bus IDs, are supported. Never infer ownership from substring/name matches.

## Views and restore

Default role/bus directories and their exports exclude archived rows. Role
Management's Archived Users view reuses role filters, pagination and directory
rows. Archived Buses reuses the existing modal/list. Both show reason, actual
archive date and admin; missing legacy metadata appears as Unavailable.

Restore updates the original status row. Recorded prior status/activation flags
are restored, so an inactive user is not silently activated. Bus operational
status, IDs, QR credentials, assignments, and historical trips are retained.
For older archives without prior-state metadata, current role defaults apply:
Driver/Bus → Active; inspector/user accounts → active, and `is_active` → 1 if
present. A repeated transition returns existing-state information, not a duplicate.
The existing explicit Reactivate action for ordinary inactive users is preserved;
it requires the same reason and authorization and does not classify them as
archived. An inactive user is not activated merely by restoring an archive with
a recorded inactive prior state.

## Deployment and legacy reconciliation

The existing audit schema must be present and readable/writable before archive
actions. No new competing audit migration is added. With the Admin application
changes deployed, run the existing announcement migrations documented in
`announcement-schema.md`, then optionally:

```powershell
php admin/migrations/reconcile_legacy_archives.php
```

This idempotent routine reclassifies only inactive Admin/Finance user records
with an attributable latest successful archive event. Ordinary inactive users
remain unchanged. Existing explicit Archived driver/inspector/bus records need
no reclassification. Missing history is not fabricated.

Rollback application changes together; retain additive status values, original
records and audit events. Do not erase history or copy archived entities.

## Verification

- `php admin/tests/announcement_schema_test.php`
- `php admin/tests/announcement_posting_test.php`
- `php admin/tests/archive_lifecycle_test.php`
- `php admin/tests/role_security_test.php`
- `node admin/tests/admin_alert_integration_test.cjs`
- `node admin/tests/archive_reason_dialog_test.cjs`
- Existing bus/role directory, CSV, writers, assignment, QR and UI regression tests.

The user authorized one shared-model exception: `app/Models/Bus.php` now loads
Database, accepts optional PDO injection, and reuses the same BusFields writer
helpers as the Admin bus model. Other non-Admin application files are untouched.

Live browser verification and live MySQL/permission checks are distinct from
the isolated fixtures. Do not mark browser verification complete from unit/UI
fixture results alone.
