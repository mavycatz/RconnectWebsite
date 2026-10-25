<?php

namespace Finance\Controllers;

require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../models/AuditTrailModel.php';

use App\Core\Database;
use App\Auth\Auth;
use Finance\Models\AuditTrailModel;
use PDO;

class OnlinePaymentVerificationController
{
    /**
     * =========================
     * NEW COLUMN REGISTRY
     * =========================
     */
    private const NEW_COLUMNS = [
        'rejection_reason',
        'rejection_notes',
        'gcash_reference_no',
        'original_gcash_amount',
        'original_gcash_screenshot',
        'previous_rejection_reason',
        'previous_rejection_notes',
    ];

    private const REJECTION_REASONS = [
        'Invalid Proof',
        'Incorrect Amount',
        'Duplicate Payment',
        'Unclear Screenshot',
        'Reference Number Not Found',
        'Other',
    ];

    /**
     * =========================
     * COLUMN CACHE (per request)
     * =========================
     */
    private static array $columnCache = [];

    private function hasColumn(PDO $db, string $column): bool
    {
        if (isset(self::$columnCache[$column])) {
            return self::$columnCache[$column];
        }
        $exists = false;
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'passenger_trips'
                  AND COLUMN_NAME = :col
            ");
            $stmt->execute([':col' => $column]);
            $exists = (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            $exists = false;
        }
        self::$columnCache[$column] = $exists;
        return $exists;
    }

    /**
     * =========================
     * SELECT FRAGMENT for new columns
     * (NULL AS fallbacks when columns are missing)
     * =========================
     */
    private function newColumnsSql(PDO $db): string
    {
        $fragments = [];
        foreach (self::NEW_COLUMNS as $col) {
            $fragments[] = $this->hasColumn($db, $col) ? "pt.$col" : "NULL AS $col";
        }
        return implode(",\n                ", $fragments);
    }

    /**
     * =========================
     * SELF-HEAL SCHEMA (idempotent, guarded)
     * =========================
     */
    private function ensureColumns(PDO $db): void
    {
        $statements = [
            "ALTER TABLE passenger_trips ADD COLUMN IF NOT EXISTS gcash_screenshot VARCHAR(255) NULL DEFAULT NULL AFTER gcash_amount",
            "ALTER TABLE passenger_trips ADD COLUMN IF NOT EXISTS resubmitted_at DATETIME NULL DEFAULT NULL AFTER verified_by",
            "ALTER TABLE passenger_trips ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(100) NULL DEFAULT NULL AFTER verified_by",
            "ALTER TABLE passenger_trips ADD COLUMN IF NOT EXISTS rejection_notes TEXT NULL DEFAULT NULL AFTER rejection_reason",
            "ALTER TABLE passenger_trips ADD COLUMN IF NOT EXISTS gcash_reference_no VARCHAR(50) NULL DEFAULT NULL AFTER gcash_amount",
            "ALTER TABLE passenger_trips ADD COLUMN IF NOT EXISTS original_gcash_amount DECIMAL(10,2) NULL DEFAULT NULL AFTER gcash_reference_no",
            "ALTER TABLE passenger_trips ADD COLUMN IF NOT EXISTS original_gcash_screenshot VARCHAR(255) NULL DEFAULT NULL AFTER original_gcash_amount",
            "ALTER TABLE passenger_trips ADD COLUMN IF NOT EXISTS previous_rejection_reason VARCHAR(100) NULL DEFAULT NULL AFTER original_gcash_screenshot",
            "ALTER TABLE passenger_trips ADD COLUMN IF NOT EXISTS previous_rejection_notes TEXT NULL DEFAULT NULL AFTER previous_rejection_reason",
        ];
        foreach ($statements as $sql) {
            try {
                $db->exec($sql);
            } catch (\Throwable $e) {
                // column already exists or ALTER unsupported — ignore
            }
        }
    }

    /**
     * =========================
     * AMOUNT RULE (shared by summary + exports)
     * GCash portion when recorded, otherwise the fare.
     * =========================
     */
    private function amountExpr(): string
    {
        return 'COALESCE(NULLIF(pt.gcash_amount, 0), pt.fare)';
    }

    private function amountOf(array $row): float
    {
        $gcash = (float) ($row['gcash_amount'] ?? 0);
        return $gcash !== 0.0 ? $gcash : (float) ($row['fare'] ?? 0);
    }

    /**
     * =========================
     * ROW LOADERS (shared by page + exports)
     * =========================
     */
    private function loadRows(PDO $db, string $type, string $filterBus = '', string $filterDate = ''): array
    {
        $newCols = $this->newColumnsSql($db);

        $params = [];
        $conditions = ["pt.payment_method IN ('gcash', 'hybrid')"];
        $dateColumn = null;

        if ($filterBus) {
            $conditions[] = "pt.bus_no = :bus";
            $params[':bus'] = $filterBus;
        }

        if ($type === 'pending') {
            $conditions[] = "pt.payment_status = 'pending'";
            $conditions[] = "pt.resubmitted_at IS NULL";
            $dateColumn = 'boarding_time';
            $orderBy = "pt.id DESC";
        } elseif ($type === 'resubmissions') {
            $conditions[] = "pt.payment_status = 'pending'";
            $conditions[] = "pt.resubmitted_at IS NOT NULL";
            $dateColumn = 'resubmitted_at';
            $orderBy = "pt.resubmitted_at DESC";
        } else {
            // history (verified)
            $conditions[] = "pt.payment_status IN ('paid', 'rejected')";
            $conditions[] = "pt.verified_at IS NOT NULL";
            $dateColumn = 'verified_at';
            $orderBy = "pt.verified_at DESC";
        }

        if ($filterDate && $dateColumn) {
            $conditions[] = "DATE(pt.$dateColumn) = :date";
            $params[':date'] = $filterDate;
        }

        $where = implode(' AND ', $conditions);

        $sql = "
            SELECT
                pt.id,
                pt.passenger_id,
                p.full_name AS name,
                pt.bus_no AS bus_id,
                pt.driver_id,
                pt.boarding_point,
                pt.destination,
                pt.fare,
                pt.payment_method,
                pt.payment_status,
                pt.gcash_amount,
                pt.gcash_screenshot,
                pt.boarding_time,
                pt.resubmitted_at,
                pt.verified_at,
                pt.verified_by,
                $newCols
            FROM passenger_trips pt
            LEFT JOIN passengers p ON pt.passenger_id = p.id
            WHERE $where
            ORDER BY $orderBy
            LIMIT 100
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function loadActiveBuses(PDO $db): array
    {
        return $db->query("
            SELECT DISTINCT bt.bus_id AS bus_no, d.full_name AS driver_name
            FROM bus_trips bt
            LEFT JOIN drivers d ON bt.driver_id = d.driver_id
            WHERE bt.status = 'Departed'
              AND bt.trip_date = CURDATE()
            ORDER BY bt.bus_id
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * =========================
     * SUMMARY DATA for cards and charts
     * Aggregated in SQL over the full dataset (no row-cap),
     * honoring the active bus/date filters.
     * =========================
     */
    private function loadSummary(PDO $db, string $filterBus = '', string $filterDate = ''): array
    {
        $amount = $this->amountExpr();
        $params = [];
        $conditions = [
            "pt.payment_method IN ('gcash', 'hybrid')",
            "pt.payment_status IN ('pending', 'paid', 'rejected')",
        ];

        if ($filterBus) {
            $conditions[] = "pt.bus_no = :bus";
            $params[':bus'] = $filterBus;
        }
        if ($filterDate) {
            $conditions[] = "DATE(CASE WHEN pt.payment_status = 'pending' THEN pt.boarding_time ELSE pt.verified_at END) = :date";
            $params[':date'] = $filterDate;
        }

        $stmt = $db->prepare("
            SELECT
                pt.payment_status AS st,
                COUNT(*) AS cnt,
                COALESCE(SUM($amount), 0) AS amount
            FROM passenger_trips pt
            WHERE " . implode(' AND ', $conditions) . "
            GROUP BY pt.payment_status
        ");
        $stmt->execute($params);

        $agg = [
            'pending' => ['cnt' => 0, 'amount' => 0.0],
            'paid' => ['cnt' => 0, 'amount' => 0.0],
            'rejected' => ['cnt' => 0, 'amount' => 0.0],
        ];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $agg[$row['st']] = ['cnt' => (int) $row['cnt'], 'amount' => (float) $row['amount']];
        }
        return $agg;
    }

    private function buildSummary(array $agg): array
    {
        $pendingCount = (int) ($agg['pending']['cnt'] ?? 0);
        $pendingAmount = (float) ($agg['pending']['amount'] ?? 0);

        $approvedCount = (int) ($agg['paid']['cnt'] ?? 0);
        $approvedAmount = (float) ($agg['paid']['amount'] ?? 0);

        $rejectedCount = (int) ($agg['rejected']['cnt'] ?? 0);
        $rejectedAmount = (float) ($agg['rejected']['amount'] ?? 0);

        $totalCount = $pendingCount + $approvedCount + $rejectedCount;
        $totalAmount = $pendingAmount + $approvedAmount + $rejectedAmount;

        $pct = function (int $count) use ($totalCount): int {
            return $totalCount > 0 ? (int) round($count / $totalCount * 100) : 0;
        };

        return [
            'pending_count' => $pendingCount,
            'pending_amount' => round($pendingAmount, 2),
            'approved_count' => $approvedCount,
            'approved_amount' => round($approvedAmount, 2),
            'rejected_count' => $rejectedCount,
            'rejected_amount' => round($rejectedAmount, 2),
            'total_count' => $totalCount,
            'total_amount' => round($totalAmount, 2),
            'donut' => [
                ['label' => 'Pending', 'count' => $pendingCount, 'amount' => round($pendingAmount, 2), 'pct' => $pct($pendingCount), 'color' => '#eab308'],
                ['label' => 'Approved', 'count' => $approvedCount, 'amount' => round($approvedAmount, 2), 'pct' => $pct($approvedCount), 'color' => '#16a34a'],
                ['label' => 'Rejected', 'count' => $rejectedCount, 'amount' => round($rejectedAmount, 2), 'pct' => $pct($rejectedCount), 'color' => '#dc2626'],
            ],
            'bar' => [
                ['label' => 'Pending', 'amount' => round($pendingAmount, 2), 'color' => '#eab308'],
                ['label' => 'Approved', 'amount' => round($approvedAmount, 2), 'color' => '#16a34a'],
                ['label' => 'Rejected', 'amount' => round($rejectedAmount, 2), 'color' => '#dc2626'],
            ],
        ];
    }

    /**
     * =========================
     * LOAD PAGE
     * =========================
     */
    public function index()
    {
        $db = Database::get();

        try {
            $this->ensureColumns($db);
        } catch (\Throwable $e) {}

        $filterBus = $_GET['bus'] ?? '';
        $filterDate = $_GET['date'] ?? '';

        $pending = $this->loadRows($db, 'pending', $filterBus, $filterDate);
        $resubmissions = $this->loadRows($db, 'resubmissions', $filterBus, $filterDate);
        $verified = $this->loadRows($db, 'history', $filterBus, $filterDate);
        $activeBuses = $this->loadActiveBuses($db);

        $summary = $this->buildSummary($this->loadSummary($db, $filterBus, $filterDate));

        $hasRef = $this->hasColumn($db, 'gcash_reference_no');
        $today = date('Y-m-d');
        $filterDate = htmlspecialchars($filterDate);

        require __DIR__ . '/../views/online-payment-verification/index.php';
    }

    /**
     * =========================
     * SCREENSHOT STREAM
     * =========================
     */
    public function getScreenshot()
    {
        $id = (int)($_GET['id'] ?? 0);

        if (!$id) {
            http_response_code(400);
            exit;
        }

        $db = Database::get();
        $col = 'gcash_screenshot';
        if (($_GET['type'] ?? '') === 'original' && $this->hasColumn($db, 'original_gcash_screenshot')) {
            $col = 'original_gcash_screenshot';
        }
        $stmt = $db->prepare("SELECT $col FROM passenger_trips WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !$row[$col]) {
            http_response_code(404);
            exit;
        }

        $path = $_SERVER['DOCUMENT_ROOT'] . '/uploads/gcash/' . $row[$col];

        if (!file_exists($path)) {
            http_response_code(404);
            exit;
        }

        $ext = strtolower(pathinfo($row['gcash_screenshot'], PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /**
     * =========================
     * VERIFY (approve / reject)
     * =========================
     */
    public function verify()
    {
        header('Content-Type: application/json');

        try {
            $db = Database::get();

            try {
                $db->exec("ALTER TABLE passenger_trips MODIFY COLUMN payment_status ENUM('pending','ongoing','paid','rejected') NOT NULL DEFAULT 'pending'");
            } catch (\Throwable $e) {}

            $id = (int)($_POST['id'] ?? 0);
            $action = $_POST['action'] ?? '';
            $reason = trim((string)($_POST['rejection_reason'] ?? ''));
            $notes = trim((string)($_POST['rejection_notes'] ?? ''));

            if (!$id) {
                throw new \Exception('Invalid trip ID.');
            }

            if (!in_array($action, ['approve', 'reject'], true)) {
                throw new \Exception('Invalid action.');
            }

            if ($action === 'reject' && !in_array($reason, self::REJECTION_REASONS, true)) {
                throw new \Exception('A valid rejection reason is required.');
            }

            $user = Auth::user();
            $username = $user['username'] ?? 'Finance';

            $set = "payment_status = :status, verified_at = NOW(), verified_by = :verified_by";
            $params = [
                ':status' => $action === 'approve' ? 'paid' : 'rejected',
                ':verified_by' => $username,
                ':id' => $id,
            ];

            if ($action === 'reject') {
                if ($this->hasColumn($db, 'rejection_reason')) {
                    $set .= ", rejection_reason = :reason";
                    $params[':reason'] = $reason;
                }
                if ($this->hasColumn($db, 'rejection_notes')) {
                    $set .= ", rejection_notes = :notes";
                    $params[':notes'] = $notes ?: null;
                }
            }

            $stmt = $db->prepare("UPDATE passenger_trips SET $set WHERE id = :id");
            $stmt->execute($params);

            try {
                $audit = new AuditTrailModel();
                $audit->log(
                    'Finance',
                    $action === 'approve' ? 'Approve Payment' : 'Reject Payment',
                    $action === 'approve'
                        ? "Approved GCash payment #$id by $username."
                        : "Rejected GCash payment #$id by $username. Reason: $reason",
                    (int) ($user['id'] ?? 0),
                    $username,
                    $user['role'] ?? null,
                    (string) $id
                );
            } catch (\Throwable $e) {
                error_log('Online payment audit failed: ' . $e->getMessage());
            }

            echo json_encode([
                'success' => true,
                'message' => $action === 'approve'
                    ? 'Payment approved successfully.'
                    : 'Payment rejected.',
            ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }

        exit;
    }

    /**
     * =========================
     * EXPORT PDF
     * =========================
     */
    public function exportPdf()
    {
        try {
            require_once __DIR__ . '/../../vendor/autoload.php';

            $db = Database::get();
            $tab = $_GET['tab'] ?? 'pending';
            $filterBus = $_GET['bus'] ?? '';
            $filterDate = $_GET['date'] ?? '';
            $rows = $this->loadRows($db, $this->tabToType($tab), $filterBus, $filterDate);

            $tabLabel = $this->tabLabel($tab);
            $totalAmount = 0.0;
            foreach ($rows as $r) {
                $totalAmount += $this->amountOf($r);
            }

            ob_start();
            require __DIR__ . '/../views/online-payment-verification/export_pdf.php';
            $html = ob_get_clean();

            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            $dompdf->stream('online-payment-verification.pdf', ['Attachment' => true]);

        } catch (\Exception $e) {
            die('Export failed: ' . $e->getMessage());
        }
        exit;
    }

    /**
     * =========================
     * EXPORT EXCEL
     * =========================
     */
    public function exportExcel()
    {
        try {
            $db = Database::get();
            $tab = $_GET['tab'] ?? 'pending';
            $filterBus = $_GET['bus'] ?? '';
            $filterDate = $_GET['date'] ?? '';
            $rows = $this->loadRows($db, $this->tabToType($tab), $filterBus, $filterDate);

            $tabLabel = $this->tabLabel($tab);

            $totalAmount = 0.0;
            foreach ($rows as $r) {
                $totalAmount += $this->amountOf($r);
            }

            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="online-payment-verification.xls"');
            header('Cache-Control: max-age=0');

            require __DIR__ . '/../views/online-payment-verification/export_excel.php';

        } catch (\Exception $e) {
            die('Export failed: ' . $e->getMessage());
        }
        exit;
    }

    private function tabToType(string $tab): string
    {
        return in_array($tab, ['pending', 'resubmissions', 'history'], true) ? $tab : 'pending';
    }

    private function tabLabel(string $tab): string
    {
        return ['pending' => 'Pending', 'resubmissions' => 'Resubmissions', 'history' => 'History'][$this->tabToType($tab)];
    }
}
?>
