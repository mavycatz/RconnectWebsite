# Announcement schema readiness

Admin posting reuses these existing idempotent migrations:

```powershell
php admin/migrations/add_announcement_author_role.php
php admin/migrations/add_announcement_visibility.php
```

The same migration functions are called before an announcement is inserted.
`author_role` is derived from the authenticated session. Existing author values,
publication dates, attachments, audiences, and optional Facebook behaviour are
preserved; legacy authors remain null and legacy audiences default to public.

If the database user lacks ALTER permission, the posting request reports which
existing migration to run. Have the deployment database owner run that migration
with ALTER permission, then retry posting. A denied upgrade does not insert an
announcement or trigger notification/Facebook side effects. No duplicate table
or competing migration is required.

Do not execute the upgrades inside a data transaction: MySQL DDL can implicitly
commit. On rollback of application changes, retain these additive columns and
their existing data.

Verification: `php admin/tests/announcement_schema_test.php` covers old/current
schemas, repeated readiness, preserved legacy data, and denied ALTER permissions.
