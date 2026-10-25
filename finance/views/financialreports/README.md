# Generated Financial Reports

The active view directory is `finance/views/financialreports/`. The route remains `financial-reports`; its existing report browsing, imports, expense entry, AI analysis, archive/restore and exports are retained. The main page contains report selection, metadata, filters and generation/export controls only.

## On-demand generated report preview

`_analytics.php` is a hidden Bootstrap report-preview modal outside the main page body, not an analytics dashboard. `financialperformance.js` makes no financial-data request and creates no charts when the page opens. Generate Report validates its chosen period, prepares a server-owned snapshot, and posts the token, report category, custom instructions and CSRF token to `financial_performance.php?action=generate`. Generation uses only that snapshot and bypasses the legacy aggregation queries. The generation modal offers Today, This Week, This Month, Last 6 Months, This Year and Custom Date Range without adding more controls to the main page.

The first saved report's text appears automatically when the list loads. Selecting another report immediately displays its text in the main page. Generation automatically opens the preview modal with its dark Bootstrap backdrop, then displays the completed report without another fetch or a View Generated Report click. Charts render only after generation completes. The returned report text also appears directly in the main page. Charts and financial records for saved reports remain available through the corresponding button. Retry after an expired saved preview reloads the stored report, not current financial records.

AI analysis requires the Finance AI key and PHP cURL. Requests use a category-scoped snapshot prompt, a 250–400 word target and a 1,200-token output limit, with a 45-second provider timeout. Provider errors or empty analysis fail generation rather than being saved as report content. Downloads become available only after successful generation or loading a saved report. Empty/non-JSON HTTP responses show actionable errors instead of browser JSON parsing exceptions.

`FinancialReportPresentationService` selects the same content for preview, PDF, CSV and AI input. Expense reports contain expense metrics/categories/records; revenue reports contain fare revenue, payment breakdown and fare records; fleet reports contain bus performance; driver reports contain driver performance and remittance records; summary and risk reports omit the full transaction appendix. Comprehensive reports use five distinct charts and one date-summary granularity, rather than repeating daily/weekly/monthly/yearly totals. Route reports include the saved analysis and explicitly indicate when the immutable snapshot has no verified route breakdown. New snapshots store `report_type`; older saved reports resolve it from their existing report category.

Reporting APIs share `_reporting_bootstrap.php`, which checks database availability before application initialization and returns JSON on initialization failures. Generation requests have a 75-second client timeout; PDF exports have a 120-second timeout. Both preview and saved-report PDF downloads validate the actual PDF signature and display JSON/server errors instead of downloading an invalid file.

Startup session/login redirects return HTTP 401 JSON; maintenance redirects return HTTP 503. Fatal failures distinguish timeout, memory limit, PHP error, and interrupted startup. Actual failures include a `FIN-...` reference, logged with PHP file/line details to the PHP error log and `storage/logs/finance_reporting.log` when writable. The requested 120-second time limit and 512 MB memory limit apply before application initialization. A small memory reserve allows memory-exhaustion failures to return a JSON explanation.

## Modal presentation

The exact reference is `admin/views/driver-performance-analytics/sections/performance-analytics.php`. The Financial Reports page uses its outer shell and finalized-panel treatment. The generated preview follows the Admin AI report modal specifically: a title/metadata header, compact pale-green overview tiles, stacked flat sections with uppercase divider headings, dashed financial rows, 240 px chart areas, light detail tables, AI analysis near the end, and footer export controls. `.fpr-admin-preview` scopes the copied report styles; the Admin view itself remains the reference. The preview body scrolls at 72vh, and its overview changes from four columns to two on small screens.

All eight Financial Reports modals share `financialreportmodals.css`: soft green headers, consistent inputs and action buttons, responsive layouts, scrollable long forms, and task-specific guidance. Import includes a sample CSV structure and review steps; expense entry explains required fields and date attribution; generation describes snapshot/export behavior. Backdrops use a light sage tint at 16% opacity only while a Financial Reports modal is active. `financialreportmodals.js` tracks modal show/hidden events, including transitions between modals, and removes the scoped backdrop styling when the last modal closes. Bootstrap is loaded once by the Finance navbar; a second bundle can duplicate modal handlers and backdrops.

## Data and filters

`FinancialPerformanceService` reads actual records with schema-aware queries. Paid live fares (excluding archived live copies) and archived fares are the income sources. Remittance cash is reported separately as settlement; bus-trip revenue summaries are not added again. Expenses use posted `expenses` and `financial_expenses` entries. If no fuel expense is posted in the selected scope, actual refuel purchases and legacy positive-liter fuel purchase records supply fuel costs; a stored receipt identifier can identify copies across the two sources. Fuel estimates and monthly fixed-cost estimates are not added to posted expenses again. Source precedence and missing sources are displayed in the methodology section and PDF.

Driver/bus filters use record identifiers, including numeric IDs resolved to department codes. Missing bus attribution can use a unique date/driver/trip match in `bus_trips`; ambiguous or missing assignments stay Unassigned. Rankings include buses with recorded activity, not fabricated zero-revenue performance for inactive buses.

Today, This Week (Monday–Sunday), This Month, Last 6 Months (current month plus five preceding calendar months), This Year and validated Custom Date Range all use Philippine calendar dates and inclusive day bounds. Daily/weekly/monthly/yearly summary buckets are partial where the selected range cuts a period. Prior-period growth is unavailable when its base is zero. Peso amounts aggregate in integer centavos before display rounding.

## Report snapshots and PDFs

The analytics API retains up to three server-owned snapshots per session for 30 minutes. Generate Financial Report saves its text in the existing report archive and persists its complete snapshot in `financial_performance_snapshots`, so archived reports continue exporting their original data and charts. The table is initialized using the existing create-if-absent model pattern; migration `003_financial_performance_snapshots.sql` is also provided for managed deployments.

Both the generated-preview charts and PDF diagrams use `FinancialPerformancePdfService::charts()` definitions. PDF export does not recompute records, accept client-supplied amounts or depend on external chart-rendering services. The layout reference is the Admin Driver Performance Analytics **Export PDF** button's `printAiReport()` template: A4 portrait, 16 mm/14 mm margins, system/title/category on the left, period/generation metadata on the right, compact overview tiles, uppercase thin-divider section headings, light tables and green/gold charts. `FinancialPerformancePdfService::document()` adapts its flex/grid layout to Dompdf-compatible tables. DejaVu Sans provides embedded peso-symbol support. This textual-header layout does not use a PNG logo or require GD for one. The PDF includes only the selected report category's sections and records.

To avoid Dompdf's per-cell CSS/frame memory growth, PDF export renders the overview and relevant charts first. Category tables and records follow in independent batches of at most 100 rows (also bounded by approximately 128 KB of table HTML) on one shared CPDF canvas. The saved AI analysis follows the details, matching the Admin print section order. Batch DOMs are released between renders. The title and metadata header appear once; page numbering spans all portrait pages. Reports without table appendices flow naturally in the initial document instead of forcing another page. No additional PDF-merging dependency is required.

## Verification

Run from the project root:

```powershell
php -d extension=gd finance/tests/financial_performance_test.php "C:\Users\MAVHIL~1\AppData\Local\Temp\opencode\financial-performance-preview.pdf" "C:\Users\MAVHIL~1\AppData\Local\Temp\opencode\financial-performance-fixture.json"
node finance/tests/financial_performance_ui_test.cjs "C:\Users\MAVHIL~1\AppData\Local\Temp\opencode\financial-performance-fixture.json"
php finance/tests/predictive_analysis_regression_test.php
node finance/tests/predictive_analysis_request_test.cjs
php finance/tests/reporting_failure_test.php
php -d extension=gd -d memory_limit=128M finance/tests/financial_performance_pdf_memory_test.php "C:\Users\MAVHIL~1\AppData\Local\Temp\opencode\financial-performance-fixture.json"
```

`-d extension=gd` is only needed if GD is installed but disabled in the CLI configuration. Enable GD in the hosting PHP configuration as appropriate.

On a running database-backed Finance session, compare totals and records for every preset and a cross-year custom range, driver and bus scopes, and an empty range. Generate, archive/restore and re-download a report after changing filters to verify snapshot persistence. Check mobile wrapping, tooltip/legend interaction, export pagination, logo, charts, repeated table headers and page breaks. The isolated fixtures are test-only; no fixture values are used by the application.
