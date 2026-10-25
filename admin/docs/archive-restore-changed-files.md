# Files changed: fix-admin-announcements-and-archive-restore

This list covers the edits made for this OpenSpec change, including its
authorized shared-model repair. It excludes earlier passenger work.

## Application files modified

- `app/Models/Bus.php` — authorized shared-model repair: Database loading, optional PDO injection, existing BusFields helpers.
- `admin/Controllers/Announcement/AnnouncementController.php` — schema readiness and testable external-publication seam.
- `admin/migrations/add_announcement_author_role.php` — existing migration made reusable/idempotent.
- `admin/migrations/add_announcement_visibility.php` — existing audience migration made reusable/idempotent.
- `admin/api/RoleManagement/_security_guard.php` — mandatory reason and guarded atomic archive/restore dispatch.
- `admin/api/RoleManagement/History.php` — existing history includes canonical archive event references.
- `admin/api/Bus/History.php` — existing bus history includes canonical archive event references.
- `admin/models/RoleManagement/RoleDirectoryModel.php` — separate archived/inactive states, default exclusion, metadata.
- `admin/models/Bus/BusDirectoryModel.php` — default archive exclusion and archive metadata.
- `admin/views/announcement/index.php` — existing custom alerts instead of SweetAlert.
- `admin/views/role-management/index.php` — existing alert integration and archived status option.
- `admin/views/role-management/sections/directory.php` — Archived Users entry and heading.
- `admin/views/bus/index.php` — existing alert/reason adapter loading.
- `admin/assets/js/role-management.js` — archive view, reasons, restore feedback, removal of alternate alert fallbacks.
- `admin/assets/js/bus-management.js` — archive details, existing-overlay reason input, removal of alternate alert fallbacks.

## Application files added

- `admin/migrations/reconcile_legacy_archives.php` — evidence-only legacy reconciliation.
- `admin/services/RoleManagement/ArchiveMetadata.php` — existing audit history adapter.
- `admin/services/RoleManagement/ArchiveLifecycle.php` — original-record transitions and atomic audit persistence.
- `admin/views/layout/custom-alert.php` — consolidation of the existing Admin overlay markup.
- `admin/assets/js/management-alert.js` — reason input/validation inside the existing overlay.

## Tests modified

- `admin/tests/role_security_test.php`
- `admin/tests/bus-management_ui_test.cjs`
- `admin/tests/role-management_export_modal_test.cjs`

## Tests added

- `admin/tests/announcement_schema_test.php`
- `admin/tests/announcement_posting_test.php`
- `admin/tests/archive_lifecycle_test.php`
- `admin/tests/archive_race_test.php`
- `admin/tests/admin_alert_integration_test.cjs`
- `admin/tests/archive_reason_dialog_test.cjs`
- `admin/tests/archived_directory_ui_test.cjs`

## Documentation added

- `admin/docs/announcement-schema.md`
- `admin/docs/archive-restore.md`
- `admin/docs/archive-restore-verification.md`
- `admin/docs/archive-restore-changed-files.md` (this list)

## OpenSpec artifacts created/updated

Under `openspec/changes/fix-admin-announcements-and-archive-restore/`:

- `.openspec.yaml` — CLI-created change scaffold.
- `proposal.md`
- `design.md`
- `tasks.md`
- `specs/admin-announcement-publication/spec.md`
- `specs/admin-record-archival/spec.md`

`admin/tests/bus_writers_test.php` was executed unchanged. The shared alert
library, shared audit implementation, and Passenger/Finance/Driver application
files were not modified by this change.
