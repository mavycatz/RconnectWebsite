# Discrepancy Investigation Workflow — Implementation Summary

Change: `add-discrepancy-investigation-workflow`
Scope: Finance discrepancy cases, recovery/adjustment ledger, approvals, reporting, and remittance integration.

## Modified files

| File | Change |
| --- | --- |
| `finance/discrepancies.php` | Action router expanded to the full workflow (list/view/assign/status/reclassify/findings/evidence/ledger_propose/approval_decide/ledger_post/ledger_void/rules_get/rules_save/audit/print/export_pdf/export_excel). |
| `finance/controllers/DiscrepancyController.php` | Rewritten: server-side JSON endpoints with auth/rate-limit/CSRF guards, authorization checks, real Dompdf and PhpSpreadsheet exports, export audit logging. |
| `finance/views/discrepancies/index.php` | Rewritten case workspace: five KPI metrics, server-side filters and pagination, case modal with amounts/trips/buses/QR Ph breakdown/references/findings/evidence/ledger/approvals/history, authorization-rules modal, custom-alert-only feedback. |
| `finance/views/discrepancies/export_pdf.php` | New report markup shared by PDF export and print. |
| `finance/models/DiscrepancyCaseModel.php` | New: case/history/evidence schema and queries, filters, trip/QR Ph breakdown, payment references. |
| `finance/services/DiscrepancyCaseService.php` | New: case lifecycle, transition map, assignment, findings, evidence, details/timeline, idempotent legacy backfill, legacy status mirroring. |
| `finance/models/DiscrepancyLedgerModel.php` | New: ledger entries and approval requests with transactional row locking. |
| `finance/services/DiscrepancyLedgerService.php` | New: propose/decide/post/void, balance, deduction governance, case-status orchestration. |
| `finance/services/DiscrepancyMetricsService.php` | New: the five distinct metrics and export rows shared by page and exports. |
| `finance/services/DiscrepancyAuthorizationService.php` | New: configurable rules in `system_settings.discrepancy_auth_rules`, role checks, rules validation. |
| `finance/services/RemittanceService.php` | Overage accepted (`classifyAmounts`), case sync inside the submission transaction, overdue cases created by flagging, late processing reclassifies the same case. |
| `finance/validators/RemittanceValidator.php` | `validateCashAgainstExpected()` no longer rejects an actual above expected; negatives still refused. |
| `finance/models/AuditTrailModel.php` | `initTables()`, `log()`, `record()`, and `getByRecordId()` accept an optional PDO (backward-compatible) for fixture tests. |
| `finance/views/remittance/index.php` | Overage block and inline error removed; excess reconciliation state and review summary added; all feedback stays in the custom alert. |
| `finance/tools/backfill_discrepancy_cases.php` | New CLI backfill for legacy remittance rows. |
| `finance/tests/*` | New fixture helper, fixture sample, case tests, ledger tests, acceptance matrix, HTTP fixture router, HTTP suite. |

## Schema changes

`remittance_logs` (additive columns, were previously only runtime assumptions):
`resolution_type`, `resolution_notes`, `resolved_by`, `resolved_at`, `supporting_document`.

New tables (all additive, MySQL InnoDB, utf8mb4):

- `discrepancy_cases` — one row per `remittance_log_id` (UNIQUE), snapshots (`expected_amount`, `actual_amount`, `discrepancy_amount`, `excess_amount`), `case_type`, `status`, reviewer/due date, findings/reason, timestamps.
- `discrepancy_status_history` — append-only lifecycle and event log (`status_change`, `reopened`, `reclassified`, `assigned`, `findings`, `evidence`, `mismatch_established`, `legacy_import`).
- `discrepancy_evidence` — append-only evidence files with uploader metadata.
- `discrepancy_ledger_entries` — instruments, classes, signed effect, state machine, proposal/decision/posting metadata, `void_reason`.
- `discrepancy_approvals` — one pending approval per case, decision metadata and `config_reference`.

## Migration / rollback SQL

- `finance/database/migrations/004_discrepancy_resolution_columns.sql` — idempotent `ADD COLUMN IF NOT EXISTS` for the five resolution columns. Rollback drops the five columns; harmless to keep.
- `finance/database/migrations/005_discrepancy_workflow_tables.sql` — idempotent `CREATE TABLE IF NOT EXISTS` for the five workflow tables. Rollback drops the five tables; `remittance_logs` data is never touched.
- Runtime ensures mirror both files: `DiscrepancyCaseModel::ensureResolutionColumns()` (information_schema-guarded) and `DiscrepancyCaseModel::initTables()` (MySQL-only, `CREATE TABLE IF NOT EXISTS`).
- Backfill: `php finance/tools/backfill_discrepancy_cases.php` (dry run) and `--apply`. Idempotent via the UNIQUE `remittance_log_id`; a second run inserts nothing. Mapping: `discrepancy_amount > 0` or overage → Cash Shortage/Excess Remittance, `Overdue` → Overdue Remittance, legacy `Resolved` → case Resolved with legacy findings/reason.

## Authorization defaults

Stored as JSON in `system_settings.discrepancy_auth_rules` (edited only from the manager-only Authorization Rules modal); defaults apply when absent:

| Action | Propose roles | Approve roles | Requires approval | Self-approval |
| --- | --- | --- | --- | --- |
| Additional payment | finance_officer, finance_manager | finance_manager | yes | no |
| Source-record correction | finance_officer, finance_manager | finance_manager | yes | no |
| Accounting adjustment | finance_officer, finance_manager | finance_manager | yes | no |
| Payroll recovery (authorized recovery) | finance_officer, finance_manager | finance_manager | yes | no |
| Waiver | finance_officer, finance_manager | finance_manager | yes | no |
| Reopen / amend | finance_officer, finance_manager | — (performed directly) | no | — |
| Reclassify | finance_officer, finance_manager | — (performed directly) | no | — |

`propose_amount_limit` defaults to 0 (unlimited). One pending approval per case; rejected proposals never post; payroll recoveries require an authorization reference and are recorded only as ledger entries (no automatic deduction).

## Test results

All suites pass on Windows, PHP 8.5.1, in-memory SQLite fixtures (no MySQL server available locally):

| Suite | Command | Result |
| --- | --- | --- |
| Fixture sample | `php finance/tests/discrepancy_fixture_sample_test.php` | OK (seeded counts) |
| Case lifecycle | `php finance/tests/discrepancy_case_test.php` | 99 checks |
| Ledger, approvals, audit | `php finance/tests/discrepancy_ledger_test.php` | 89 checks |
| Acceptance matrix | `php finance/tests/discrepancy_acceptance_test.php` | 41 checks |
| HTTP endpoints | `php finance/tests/discrepancy_http_test.php` | 49 checks |
| Existing financial performance (regression) | `php finance/tests/financial_performance_test.php` | 79 checks |
| Existing predictive analysis (regression) | `php finance/tests/predictive_analysis_regression_test.php` | 5 checks |

HTTP suite covers filters (status/type/driver/date range/reviewer/age/combined), pagination, case details, findings, propose → approve → post over HTTP, CSRF 403, forbidden role 403, unauthenticated 401, rules get/save, PDF and XLSX exports (magic bytes + content types), export audit rows, and the workspace page rendering.

Covered acceptance scenarios: partial recoveries, incorrect payment records (QR Ph mismatch reclassification), duplicate submissions/proposals, approved waivers, failed approvals, late remittances (original business date preserved), reopened cases, and excess remittances (no driver debt, no revenue inflation).

## Investigation and approval workflow (role separation)

Lifecycle: **Open → Under Investigation → Awaiting Driver Response (optional) → Findings Submitted → Pending Manager Approval → Resolved / Closed**, with **Pending Manager Approval → Returned for Further Investigation → Under Investigation** when more work is needed. The legacy ledger states (`Awaiting Approval`, `Awaiting Recovery`) remain for recovery transactions.

- **Finance Officer**: investigate, record findings/remarks/evidence, request a driver explanation, submit findings with a recommended resolution. Cannot resolve, close, decide, or reopen.
- **Finance Manager**: start the review, approve (finalize Resolved), return, or reject with a mandatory reason, close resolved cases, and reopen finalized cases with a reason. Managers cannot decide findings they submitted themselves.
- Role separation is enforced in `DiscrepancyCaseService::assertRoleCanTransition()` and `managerDecision()`, not only in the interface; refusals are audited with `Failed`.
- New case columns (additive, migration `006_discrepancy_case_approval_columns.sql`): `findings_submitted_by`, `findings_submitted_at`, `manager_decision`, `manager_decision_by`, `manager_decision_at`, `manager_decision_reason`.
- Endpoints: `submit_findings`, `manager_review`, `manager_decision` on `finance/discrepancies.php`.
- The approval decision is recorded separately from the recovery ledger: resolving/approving a case never posts money; recovery, deductions, and adjustments continue as traceable ledger transactions.
- Original expected, actual, shortage, and excess amounts remain immutable through the workflow.

## Files changed (design + workflow update)

| File | Change |
| --- | --- |
| `finance/views/discrepancies/index.php` | Main page restyled to the Remittance design (rm summary cards, rm table, rm buttons/inputs); role-aware action labels; modal investigation/approval controls (save draft, request driver explanation, submit findings, start review, approve/return/reject) while keeping the Role Management modal design. |
| `finance/services/DiscrepancyCaseService.php` | New statuses and transition map, role guards, `submitFindingsForApproval()`, `managerDecision()`, role-filtered transitions and workflow permissions in `getDetails()`. |
| `finance/models/DiscrepancyCaseModel.php` | New status constants/role lists; approval columns in the schema ensure (`ensureCaseColumns()`) and `updateCase` whitelist. |
| `finance/controllers/DiscrepancyController.php` | New `submitFindings()`, `managerReview()`, `managerDecision()` endpoints; role-aware permissions payload; manager-only reopen. |
| `finance/discrepancies.php` | Routes for `submit_findings`, `manager_review`, `manager_decision`. |
| `finance/database/migrations/006_discrepancy_case_approval_columns.sql` | Documented additive approval columns. |
| `finance/tests/*` | New `discrepancy_workflow_test.php`; HTTP fixture/router and tests extended; case tests updated for manager-only resolution. |

## Test results (workflow update)

| Suite | Command | Result |
| --- | --- | --- |
| Investigation/approval workflow | `php finance/tests/discrepancy_workflow_test.php` | 47 checks |
| HTTP endpoints (incl. role enforcement) | `php finance/tests/discrepancy_http_test.php` | 67 checks |
| Case lifecycle | `php finance/tests/discrepancy_case_test.php` | 103 checks |
| Ledger, approvals, audit | `php finance/tests/discrepancy_ledger_test.php` | 89 checks |
| Acceptance matrix | `php finance/tests/discrepancy_acceptance_test.php` | 41 checks |

## Operational notes

- Workflow tables are created with, and existing ones are automatically aligned to, the legacy `utf8mb4_unicode_ci` collation. On servers whose utf8mb4 default is a UCA 14.0.0 collation (MariaDB 11+, `utf8mb4_uca1400_ai_ci`), the runtime ensure converts tables created before this fix; manual conversion statements are documented in migration 005.
- MySQL DDL could not be executed against a live MySQL server in this environment; the migration files follow the repository's verified idempotent patterns and the same logical schema is exercised end-to-end on SQLite. Run the backfill dry run on a database copy first (`php finance/tools/backfill_discrepancy_cases.php`).
- The in-flight `simplify-remittance-reconciliation` delta spec, proposal, and task 2.2 were updated to accept above-expected remittances so the two changes no longer contradict at archive time; `openspec validate simplify-remittance-reconciliation --type change` passes.
- No files under `passenger/`, `mobile/`, or `dispatcher/` were modified; History, dashboard summary, analytics, AI prompt data, and the remittance page still read `remittance_logs` statuses, and case resolution mirrors `Resolved` back to the legacy row (reopening restores `Discrepancy`/`Overdue`).
