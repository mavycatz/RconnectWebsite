# Bus status and archive restore

This document describes the Bus Management lifecycle status rules and the
Archived Buses restore form. Both are Admin-only behaviors.

## Lifecycle status vs operational status

- The **displayed status** of a bus comes only from the stored `buses.status`
  value. Configured values are Active, Inactive, Maintenance, and Archived;
  any other stored value (for example a legacy `Pending QR`) is displayed as
  stored.
- **Operational status** (`buses.operational_status`: Available, On Trip,
  Under Maintenance, Out of Service, or a legacy value) is presented
  separately and never replaces the displayed status. An Active bus with any
  operational value still displays, filters, and counts as Active.
- Missing or empty stored statuses display as Inactive.

## Surfaces that show status

| Surface | Shows |
|---|---|
| Directory table Status column | The stored lifecycle status |
| Directory table Operational column | The operational status as stored; "Not recorded" when empty |
| Status filter | Lifecycle values only (Active, Inactive, Maintenance, Archived) |
| Summary cards | Total, Active, Inactive, Maintenance, Archived |
| Bus profile | Lifecycle status banner plus a separate Operational Status field |
| CSV export | Status column carries the lifecycle status and honors the status filter |

Archived buses remain outside the default listing, status counts, and default
export; they are listed only through the Archived Buses surface.

## Archived Buses restore form

The Archived Buses surface restores a bus through an editable details form:

1. Choose **Restore** on an archived bus (Archived Buses list or the Archived
   status filter).
2. The restore form opens pre-filled with the bus's stored details. The Bus ID
   and QR credential are shown read-only, and the lifecycle/operational status
   fields are not part of this form.
3. Edit any detail to correct and continue to the review step.
4. **Save details & restore** asks for the mandatory reason (at least five
   characters) and the Security Authorization Code.
5. On success the entered details are stored, the bus returns to its recorded
   prior lifecycle status, and it leaves the Archived Buses listing.

Preservation guarantees:

- Fields that are not supplied or not changed keep their stored values.
- Driver assignments, routes, terminal IDs, schedules, QR credentials and
  history, archive history, and every other bus are untouched.
- `operational_status` never changes through the restore form or the save.
- A bus archived from an Inactive state returns to Inactive; no other archived
  or inactive bus is reactivated.

Authorization and failure behavior:

- The restore requires the existing single-use Security Authorization Code
  bound to the actor, the bus, and the exact payload (details and reason
  included); the token is consumed once.
- The detail write, status restore, and archive record run in one transaction:
  a rejected or failed save leaves the bus archived with its previous details.

Verification:

- `php admin/tests/bus_directory_test.php` — lifecycle classification, SQL
  buckets, and schema tolerance.
- `php admin/tests/bus_listing_csv_test.php` — CSV status column.
- `php admin/tests/bus_restore_details_test.php` — restore detail application,
  preservation, prior-state handling, and rollback.
- `php admin/tests/role_security_test.php` — payload-bound authorization.
- `node admin/tests/bus-management_ui_test.cjs` — table, filter, summary,
  profile, and restore form flows.
