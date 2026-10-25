# Admin change verification

Change: `fix-admin-announcements-and-archive-restore`.

## Passed

- 377 PHP assertions across role security, role CSV, bus directory/CSV,
  assignments, QR service/backfill, announcement schema/posting, and archive
  lifecycle fixtures.
- Concurrent archive workers leave one transition/event and preserve the bus/QR.
- Six JavaScript suites: bus-management UI, role-management export/modal UI,
  QR-generation/export UI, custom-alert integration, required-reason dialog,
  and archived-user directory UI.
- PHP lint for changed Admin PHP files and `node --check` for changed Admin JS.
- Strict OpenSpec validation.

Announcement posting fixtures deliberately omit secondary notification/audit
tables; the controller reports their best-effort failures without failing or
duplicating the local post. Facebook success/failure is simulated without
network publication. These fixtures are not production DB/browser verification.

## Resolved shared-model regression

The user authorized repairing `app/Models/Bus.php` outside the original Admin-only
boundary. It now loads Database, accepts optional injected PDO, and uses the same
BusFields insert/update helpers as the Admin model. The existing test was not
removed or relaxed: all 12 bus-writer checks now pass. The complete scoped
regression suite and syntax checks were rerun successfully; task 6.1 is complete.

## Pending manual verification

Task 6.2 remains unchecked. In a browser with the existing security code
configured, verify:

1. Post public/private announcements; confirm custom result/confirmation alerts.
2. Open Role Management/Bus Management and enter a mandatory archive reason.
3. Cancel at the reason or authorization step and confirm no record changes.
4. Complete code approval and confirm archived records leave default lists.
5. Open Archived Users/Buses; verify reason, actual archive date, and admin.
6. Restore records; confirm original IDs/relationships/QR and prior activation or
   operational state remain and the appropriate directory refreshes.
7. Reopen the pages, test search/pagination/export filtering, and check preserved
   bus QR/export features.

No live database migrations, external posts, or browser checks were executed by
these automated fixtures. Application edits are in Admin plus the explicitly
authorized shared `app/Models/Bus.php` exception.
