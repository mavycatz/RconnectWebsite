<?php

namespace Finance\Controllers;

require_once __DIR__ . '/../middleware/validate_request.php';
require_once __DIR__ . '/../services/DiscrepancyCaseService.php';
require_once __DIR__ . '/../services/DiscrepancyLedgerService.php';
require_once __DIR__ . '/../services/DiscrepancyMetricsService.php';
require_once __DIR__ . '/../services/DiscrepancyAuthorizationService.php';
require_once __DIR__ . '/../models/AuditTrailModel.php';
require_once __DIR__ . '/../models/ExportAuditModel.php';

use Finance\Middleware\AuthMiddleware;
use Finance\Models\DiscrepancyCaseModel;
use Finance\Services\DiscrepancyAuthorizationService;
use Finance\Services\DiscrepancyCaseService;
use Finance\Services\DiscrepancyLedgerService;
use Finance\Services\DiscrepancyMetricsService;
use Finance\Models\AuditTrailModel;
use Finance\Models\ExportAuditModel;
use App\Auth\Auth;

/**
 * Discrepancy investigation and resolution workflow endpoints.
 *
 * The page itself is served by index(); every other action is a JSON endpoint
 * guarded by validateFinanceRequest (auth, rate limit, CSRF) with material
 * financial actions additionally checked by DiscrepancyAuthorizationService.
 */
class DiscrepancyController
{
    private function caseService(): DiscrepancyCaseService
    {
        return new DiscrepancyCaseService();
    }

    private function ledgerService(): DiscrepancyLedgerService
    {
        return new DiscrepancyLedgerService();
    }

    private function metricsService(): DiscrepancyMetricsService
    {
        return new DiscrepancyMetricsService();
    }

    private function authorizationService(): DiscrepancyAuthorizationService
    {
        return new DiscrepancyAuthorizationService();
    }

    private function role(): ?string
    {
        $user = $_SESSION['user'] ?? Auth::user() ?? [];
        return $user['role'] ?? null;
    }

    private function actor(): array
    {
        return DiscrepancyCaseService::currentActor();
    }

    private function respond(array $payload, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($payload);
        exit;
    }

    private function fail(\Throwable $e): void
    {
        $status = $e instanceof \UnexpectedValueException ? 403 : 400;
        $this->respond(['success' => false, 'message' => $e->getMessage()], $status);
    }

    private function guardJson(): void
    {
        validateFinanceRequest('standard', true, true);
    }

    /**
     * Filters shared by the list, filters summary, and exports.
     */
    private function filtersFromRequest(): array
    {
        return [
            'status' => $_GET['status'] ?? null,
            'case_type' => $_GET['type'] ?? null,
            'driver' => $_GET['driver'] ?? null,
            'business_from' => $_GET['business_from'] ?? null,
            'business_to' => $_GET['business_to'] ?? null,
            'reviewer' => $_GET['reviewer'] ?? null,
            'age' => $_GET['age'] ?? null,
            'overdue_investigation' => $_GET['overdue'] ?? null,
        ];
    }

    /* =========================================================
     * PAGE
     * ========================================================= */

    public function index()
    {
        AuthMiddleware::requireFinanceAccess();

        try {
            $filters = $this->filtersFromRequest();
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = (int) ($_GET['per_page'] ?? 25);

            $metrics = $this->metricsService()->summary($filters);
            $list = $this->caseService()->listCases($filters, $page, $perPage);

            require __DIR__ . '/../views/discrepancies/index.php';
        } catch (\Throwable $e) {
            $hint = '';
            if (stripos($e->getMessage(), 'discrepancy_cases') !== false
                && stripos($e->getMessage(), 'exist') !== false
            ) {
                $hint = ' The workflow tables could not be created automatically. Apply '
                    . 'finance/database/migrations/004_discrepancy_resolution_columns.sql and '
                    . 'finance/database/migrations/005_discrepancy_workflow_tables.sql, then reload.';
            }
            die('Unable to load discrepancies: ' . $e->getMessage() . $hint);
        }
    }

    /* =========================================================
     * LIST (JSON)
     * ========================================================= */

    public function list(): void
    {
        $this->guardJson();

        try {
            $filters = $this->filtersFromRequest();
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = (int) ($_GET['per_page'] ?? 25);

            $metrics = $this->metricsService()->summary($filters);
            $list = $this->caseService()->listCases($filters, $page, $perPage);

            $this->respond([
                'success' => true,
                'metrics' => $metrics,
                'cases' => $list['data'],
                'pagination' => [
                    'page' => $list['page'],
                    'perPage' => $list['perPage'],
                    'total' => $list['total'],
                    'pages' => $list['pages'],
                ],
                'filters_description' => $this->metricsService()->describeFilters($filters),
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /* =========================================================
     * CASE DETAILS (JSON)
     * ========================================================= */

    public function view(): void
    {
        $this->guardJson();

        try {
            $caseId = (int) ($_GET['case_id'] ?? $_GET['id'] ?? 0);
            if ($caseId <= 0) {
                throw new \InvalidArgumentException('Invalid discrepancy case id.');
            }

            $details = $this->caseService()->getDetails($caseId, $this->role());
            if (!$details) {
                $this->respond(['success' => false, 'message' => 'Discrepancy case not found.'], 404);
            }

            $details['ledger_totals'] = $this->ledgerService()->totalsForCase($caseId);
            $details['permissions'] = array_merge(
                [
                    'role' => $this->role(),
                    'can_propose' => $this->authorizationService()->canPropose('additional_payment', 0, $this->role()),
                    'can_approve' => $this->authorizationService()->canApprove('additional_payment', 0, $this->role()),
                    'can_reopen' => DiscrepancyCaseService::isManagerRole($this->role()),
                    'can_edit_rules' => $this->authorizationService()->canEditRules($this->role()),
                ],
                $details['workflow_permissions'] ?? []
            );
            unset($details['workflow_permissions']);
            $details['entry_types'] = DiscrepancyLedgerService::ENTRY_TYPES;

            $this->respond(['success' => true, 'details' => $details]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /* =========================================================
     * LIFECYCLE ACTIONS (POST)
     * ========================================================= */

    public function assign(): void
    {
        $this->guardJson();

        try {
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $reviewer = trim((string) ($_POST['reviewer'] ?? ''));
            $dueDate = trim((string) ($_POST['due_date'] ?? ''));

            $case = $this->caseService()->assign($caseId, $reviewer, $dueDate !== '' ? $dueDate : null);

            $this->respond([
                'success' => true,
                'message' => 'Case assigned.',
                'case' => $case,
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function status(): void
    {
        $this->guardJson();

        try {
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $to = trim((string) ($_POST['status'] ?? ''));
            $reason = (string) ($_POST['reason'] ?? '');

            $case = $this->caseService()->model()->getById($caseId);
            if (!$case) {
                throw new \InvalidArgumentException('Discrepancy case not found.');
            }

            $reopening = in_array($case['status'], [
                \Finance\Models\DiscrepancyCaseModel::STATUS_RESOLVED,
                \Finance\Models\DiscrepancyCaseModel::STATUS_CLOSED,
            ], true) && !in_array($to, [
                \Finance\Models\DiscrepancyCaseModel::STATUS_RESOLVED,
                \Finance\Models\DiscrepancyCaseModel::STATUS_CLOSED,
            ], true);

            if ($reopening && !DiscrepancyCaseService::isManagerRole($this->role())) {
                AuditTrailModel::record(
                    'Finance',
                    'Discrepancy Case Reopened',
                    'Refused: role ' . ($this->role() ?? 'unknown') . ' may not reopen a closed case.',
                    $this->caseService()->refFor($caseId),
                    null,
                    null,
                    'Failed'
                );
                throw new \UnexpectedValueException('Your role is not authorized to reopen a closed case.');
            }

            $updated = $this->caseService()->transition($caseId, $to, $reason);

            $this->respond([
                'success' => true,
                'message' => 'Status updated to ' . $updated['status'] . '.',
                'case' => $updated,
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function reclassify(): void
    {
        $this->guardJson();

        try {
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $type = trim((string) ($_POST['case_type'] ?? ''));
            $reason = (string) ($_POST['reason'] ?? '');

            if (!$this->authorizationService()->canPerform('reclassify', 0, $this->role())) {
                AuditTrailModel::record(
                    'Finance',
                    'Discrepancy Case Reclassified',
                    'Refused: role ' . ($this->role() ?? 'unknown') . ' may not reclassify a case.',
                    $this->caseService()->refFor($caseId),
                    null,
                    null,
                    'Failed'
                );
                throw new \UnexpectedValueException('Your role is not authorized to reclassify a case.');
            }

            $case = $this->caseService()->reclassify($caseId, $type, $reason);

            $this->respond([
                'success' => true,
                'message' => 'Case classified as ' . $case['case_type'] . '.',
                'case' => $case,
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function findings(): void
    {
        $this->guardJson();

        try {
            $caseId = (int) ($_POST['case_id'] ?? 0);
            $findings = (string) ($_POST['findings'] ?? '');
            $reason = (string) ($_POST['reason'] ?? '');

            $case = $this->caseService()->saveFindings($caseId, $findings, $reason);

            $this->respond([
                'success' => true,
                'message' => 'Findings recorded.',
                'case' => $case,
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * Finance Officer submits the completed investigation, recommended
     * resolution, and remarks for Finance Manager review.
     */
    public function submitFindings(): void
    {
        $this->guardJson();

        try {
            $case = $this->caseService()->submitFindingsForApproval(
                (int) ($_POST['case_id'] ?? 0),
                (string) ($_POST['findings'] ?? ''),
                (string) ($_POST['recommendation'] ?? ''),
                (string) ($_POST['remarks'] ?? '')
            );

            $this->respond([
                'success' => true,
                'message' => 'Findings submitted for Finance Manager review.',
                'case' => $case,
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * Finance Manager picks up submitted findings for approval review.
     */
    public function managerReview(): void
    {
        $this->guardJson();

        try {
            $caseId = (int) ($_POST['case_id'] ?? 0);

            if (!DiscrepancyCaseService::isManagerRole($this->role())) {
                AuditTrailModel::record(
                    'Finance',
                    'Discrepancy Manager Review',
                    'Refused: role ' . ($this->role() ?? 'unknown') . ' may not start the approval review.',
                    $this->caseService()->refFor($caseId),
                    null,
                    null,
                    'Failed'
                );
                throw new \UnexpectedValueException('Only a Finance Manager can start the approval review.');
            }

            $case = $this->caseService()->transition(
                $caseId,
                DiscrepancyCaseModel::STATUS_PENDING_MANAGER_APPROVAL,
                'Manager review started.',
                null
            );

            $this->respond([
                'success' => true,
                'message' => 'Approval review started.',
                'case' => $case,
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * Finance Manager records the final decision: approve, return, or reject.
     */
    public function managerDecision(): void
    {
        $this->guardJson();

        try {
            $case = $this->caseService()->managerDecision(
                (int) ($_POST['case_id'] ?? 0),
                (string) ($_POST['decision'] ?? ''),
                (string) ($_POST['reason'] ?? '')
            );

            $this->respond([
                'success' => true,
                'message' => 'Decision recorded.',
                'case' => $case,
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function evidence(): void
    {
        $this->guardJson();

        try {
            $caseId = (int) ($_POST['case_id'] ?? 0);

            $file = $_FILES['evidence'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('Attach a PDF, PNG, or JPG file to upload.');
            }

            $service = $this->caseService();
            $path = $service->storeEvidenceFile($file);
            $service->recordEvidence($caseId, $path, [
                'original_name' => $file['name'] ?? null,
                'mime' => mime_content_type($file['tmp_name']) ?: ($file['type'] ?? null),
                'size' => $file['size'] ?? null,
            ]);

            $this->respond([
                'success' => true,
                'message' => 'Evidence uploaded.',
                'evidence' => $service->model()->getEvidence($caseId),
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /* =========================================================
     * LEDGER ACTIONS (POST)
     * ========================================================= */

    public function ledgerPropose(): void
    {
        $this->guardJson();

        try {
            $result = $this->ledgerService()->propose([
                'case_id' => (int) ($_POST['case_id'] ?? 0),
                'entry_type' => trim((string) ($_POST['entry_type'] ?? '')),
                'amount' => (float) ($_POST['amount'] ?? 0),
                'direction' => (string) ($_POST['direction'] ?? 'reduce'),
                'reference' => (string) ($_POST['reference'] ?? ''),
                'authorization_reference' => (string) ($_POST['authorization_reference'] ?? ''),
                'notes' => (string) ($_POST['notes'] ?? ''),
                'reason' => (string) ($_POST['reason'] ?? ''),
            ]);

            $this->respond([
                'success' => true,
                'message' => 'Resolution proposed and sent for approval.',
                'entry' => $result,
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function approvalDecide(): void
    {
        $this->guardJson();

        try {
            $result = $this->ledgerService()->decide(
                (int) ($_POST['approval_id'] ?? 0),
                (string) ($_POST['decision'] ?? ''),
                (string) ($_POST['reason'] ?? '')
            );

            $this->respond([
                'success' => true,
                'message' => 'Approval ' . $result['decision'] . '.',
                'entry' => $result,
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function ledgerPost(): void
    {
        $this->guardJson();

        try {
            $result = $this->ledgerService()->post((int) ($_POST['entry_id'] ?? 0));

            $this->respond([
                'success' => true,
                'message' => 'Resolution posted to the ledger.',
                'entry' => $result,
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function ledgerVoid(): void
    {
        $this->guardJson();

        try {
            $result = $this->ledgerService()->void(
                (int) ($_POST['entry_id'] ?? 0),
                (string) ($_POST['reason'] ?? '')
            );

            $this->respond([
                'success' => true,
                'message' => 'Ledger entry voided.',
                'entry' => $result,
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /* =========================================================
     * AUTHORIZATION RULES
     * ========================================================= */

    public function rulesGet(): void
    {
        $this->guardJson();

        try {
            $service = $this->authorizationService();

            $this->respond([
                'success' => true,
                'rules' => $service->rules(),
                'labels' => DiscrepancyAuthorizationService::ACTION_LABELS,
                'can_edit' => $service->canEditRules($this->role()),
                'stored' => $this->rulesAreStored(),
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function rulesSave(): void
    {
        $this->guardJson();

        try {
            $raw = (string) ($_POST['rules'] ?? '');
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new \InvalidArgumentException('The rules payload is not valid JSON.');
            }

            $saved = $this->authorizationService()->saveRules($decoded);

            $this->respond([
                'success' => true,
                'message' => 'Authorization rules saved.',
                'rules' => $saved,
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    private function rulesAreStored(): bool
    {
        try {
            $stmt = \App\Core\Database::get()->prepare(
                "SELECT COUNT(*) FROM system_settings WHERE setting_key = ?"
            );
            $stmt->execute([DiscrepancyAuthorizationService::SETTING_KEY]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /* =========================================================
     * LEGACY IMPORT
     * ========================================================= */

    /**
     * One-time (idempotent) import of existing shortage, excess, and overdue
     * remittances into investigation cases. Manager-only; the same operation
     * as finance/tools/backfill_discrepancy_cases.php --apply.
     */
    public function backfill(): void
    {
        $this->guardJson();

        try {
            $role = $this->role();
            if (!in_array($role, ['finance_manager', 'admin', 'super_admin'], true)) {
                AuditTrailModel::record(
                    'Finance',
                    'Discrepancy Backfill',
                    'Refused: role ' . ($role ?? 'unknown') . ' may not import existing discrepancies.',
                    'DIS-BACKFILL',
                    null,
                    null,
                    'Failed'
                );
                throw new \UnexpectedValueException('Only a finance manager can import existing discrepancies.');
            }

            $result = $this->caseService()->backfillLegacyCases(true);

            AuditTrailModel::record(
                'Finance',
                'Discrepancy Backfill',
                'Imported legacy discrepancies into cases: ' . $result['created'] . ' created, '
                    . $result['existing'] . ' already present.',
                'DIS-BACKFILL',
                null,
                [
                    'scanned' => $result['scanned'],
                    'created' => $result['created'],
                    'existing' => $result['existing'],
                    'by_status' => $result['by_status'],
                ],
                'Success'
            );

            $this->respond([
                'success' => true,
                'message' => 'Imported ' . $result['created'] . ' case(s) from existing remittances'
                    . ($result['existing'] > 0 ? ' (' . $result['existing'] . ' already present)' : '') . '.',
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /* =========================================================
     * ASSIGNABLE FINANCE USERS
     * ========================================================= */

    /**
     * Finance users that a case may be assigned to. Only the columns the
     * users table is guaranteed to have are required; optional name columns
     * (full_name, first_name/last_name) are used when present.
     */
    public function financeUsers(): void
    {
        $this->guardJson();

        try {
            $this->respond([
                'success' => true,
                'users' => $this->fetchFinanceUsers(),
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    private function fetchFinanceUsers(): array
    {
        $db = \App\Core\Database::get();

        $sql = "SELECT u.id, u.username, u.role %s
                FROM users u
                WHERE LOWER(REPLACE(TRIM(COALESCE(u.role, '')), ' ', '_')) IN ('finance_manager', 'finance_officer')";

        $nameExpressions = [
            ", COALESCE(NULLIF(TRIM(u.full_name), ''), '') AS full_name",
            ", COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), '') AS full_name",
            ", '' AS full_name",
        ];

        $rows = [];
        foreach ($nameExpressions as $expression) {
            try {
                $stmt = $db->query(sprintf($sql, $expression) . " ORDER BY u.role ASC, u.username ASC");
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                break;
            } catch (\Throwable $e) {
                // users table may lack this name column; try the next shape
            }
        }

        return array_map(static function (array $user): array {
            $fullName = trim((string) ($user['full_name'] ?? ''));
            return [
                'id' => (int) ($user['id'] ?? 0),
                'username' => (string) ($user['username'] ?? ''),
                'role' => (string) ($user['role'] ?? ''),
                'display_name' => $fullName !== '' ? $fullName : (string) ($user['username'] ?? ''),
            ];
        }, $rows);
    }

    /* =========================================================
     * AUDIT TRAIL (READ ONLY)
     * ========================================================= */

    public function auditTrail(): void
    {
        $this->guardJson();

        try {
            $recordId = $_GET['record_id'] ?? $_GET['recordId'] ?? '';
            if ($recordId === '') {
                throw new \InvalidArgumentException('Record id is required.');
            }

            $rows = (new AuditTrailModel())->getByRecordId($recordId);

            $this->respond(['success' => true, 'records' => $rows]);
        } catch (\Throwable $e) {
            $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
                'records' => [],
            ], 400);
        }
    }

    /* =========================================================
     * EXPORTS
     * ========================================================= */

    private function exportContext(): array
    {
        validateFinanceRequest('standard', true, false);

        $filters = $this->filtersFromRequest();
        $metrics = $this->metricsService();

        return [
            'filters' => $filters,
            'summary' => $metrics->summary($filters),
            'rows' => $metrics->exportRows($filters),
            'description' => $metrics->describeFilters($filters),
            'generated_at' => DiscrepancyCaseService::manilaNow()->format('Y-m-d H:i:s'),
        ];
    }

    private function logExport(string $format, array $context, ?string $fileName = null): void
    {
        $user = $_SESSION['user'] ?? Auth::user() ?? [];

        AuditTrailModel::record(
            'Finance',
            'Discrepancy Exported',
            'Exported discrepancy cases (' . $format . ') with filters: ' . $context['description'],
            'DIS-EXPORT',
            null,
            [
                'format' => $format,
                'filters' => $context['description'],
                'record_count' => count($context['rows']),
                'summary' => $context['summary'],
            ],
            'Success'
        );

        try {
            (new ExportAuditModel())->initTables();
            (new ExportAuditModel())->logExport(
                'Discrepancy Investigation Report',
                'discrepancies',
                $context['description'],
                $format,
                $user['username'] ?? 'System',
                $user['role'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $fileName
            );
        } catch (\Throwable $e) {
            error_log('Discrepancy export log failed: ' . $e->getMessage());
        }
    }

    public function exportPdf(): void
    {
        try {
            $context = $this->exportContext();

            ob_start();
            require __DIR__ . '/../views/discrepancies/export_pdf.php';
            $html = ob_get_clean();

            require_once __DIR__ . '/../../vendor/autoload.php';

            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            $this->logExport('PDF', $context, 'discrepancy-investigation.pdf');

            $dompdf->stream('discrepancy-investigation.pdf', ['Attachment' => true]);
        } catch (\Throwable $e) {
            die('Export failed: ' . $e->getMessage());
        }
        exit;
    }

    public function exportExcel(): void
    {
        try {
            $context = $this->exportContext();

            require_once __DIR__ . '/../../vendor/autoload.php';

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Discrepancies');

            $sheet->setCellValue('A1', 'Discrepancy Investigation Report');
            $sheet->setCellValue('A2', 'Filters: ' . $context['description']);
            $sheet->setCellValue('A3', 'Generated: ' . $context['generated_at']);

            $summary = $context['summary'];
            $sheet->setCellValue('A5', 'Case Count');
            $sheet->setCellValue('B5', $summary['case_count']);
            $sheet->setCellValue('A6', 'Original Discrepancy');
            $sheet->setCellValue('B6', $summary['original_discrepancy']);
            $sheet->setCellValue('A7', 'Recovered');
            $sheet->setCellValue('B7', $summary['recovered']);
            $sheet->setCellValue('A8', 'Approved Adjustments');
            $sheet->setCellValue('B8', $summary['approved_adjustments']);
            $sheet->setCellValue('A9', 'Outstanding Balance');
            $sheet->setCellValue('B9', $summary['outstanding_balance']);

            $headers = [
                'Case Ref', 'Business Date', 'Driver', 'Type', 'Status',
                'Expected', 'Actual', 'Shortage', 'Excess', 'Outstanding',
                'Reviewer', 'Age (days)', 'Due Date', 'Next Action',
            ];
            $sheet->fromArray($headers, null, 'A11');

            $rowIndex = 12;
            foreach ($context['rows'] as $row) {
                $sheet->fromArray([
                    $row['case_ref'] ?? '',
                    $row['business_date'] ?? '',
                    $row['driver_name'] ?? $row['driver_id'] ?? '',
                    $row['case_type'] ?? '',
                    $row['status'] ?? '',
                    (float) ($row['expected_amount'] ?? 0),
                    $row['actual_amount'] !== null ? (float) $row['actual_amount'] : '',
                    (float) ($row['discrepancy_amount'] ?? 0),
                    (float) ($row['excess_amount'] ?? 0),
                    (float) ($row['outstanding_balance'] ?? 0),
                    $row['assigned_reviewer'] ?? '',
                    (int) ($row['age_days'] ?? 0),
                    $row['due_date'] ?? '',
                    $row['next_action'] ?? '',
                ], null, 'A' . $rowIndex);
                $rowIndex++;
            }

            $this->logExport('Excel', $context, 'discrepancy-investigation.xlsx');

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="discrepancy-investigation.xlsx"');
            header('Cache-Control: max-age=0');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        } catch (\Throwable $e) {
            die('Export failed: ' . $e->getMessage());
        }
        exit;
    }

    public function print(): void
    {
        try {
            $context = $this->exportContext();
            require __DIR__ . '/../views/discrepancies/export_pdf.php';
        } catch (\Throwable $e) {
            die('Print failed: ' . $e->getMessage());
        }
        exit;
    }
}
