<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/finance/models/AuditTrailModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/finance/services/TripHistoryCsvImportService.php';

use App\Auth\Auth;
use App\Core\Database;
use Finance\Models\AuditTrailModel;
use Finance\Services\TripHistoryCsvImportService;

header('Content-Type: application/json');

if (!Auth::check() || !in_array(Auth::user()['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {

    if (!isset($_FILES['csv_files']) || empty($_FILES['csv_files']['name'][0])) {
        throw new Exception('Please upload at least one valid CSV file.');
    }

    $db = Database::get();

    $totalRows = 0;
    $imported = 0;
    $skipped = 0;
    $failed = 0;
    $errors = [];
    $fileCount = count($_FILES['csv_files']['name']);

    /* The parsing, validation, duplicate check and inserts live in the shared
       TripHistoryCsvImportService so this endpoint and the Financial Reports
       "Import Data" component always accept the same trip-history format. */
    for ($f = 0; $f < $fileCount; $f++) {
        $file = [
            'name' => $_FILES['csv_files']['name'][$f],
            'tmp_name' => $_FILES['csv_files']['tmp_name'][$f],
            'error' => $_FILES['csv_files']['error'][$f],
            'size' => $_FILES['csv_files']['size'][$f],
        ];

        try {
            [$tmpPath, $fileName] = TripHistoryCsvImportService::validateUpload($file);
            $result = TripHistoryCsvImportService::import($db, $tmpPath, $fileName);

            $imported += (int) $result['imported'];
            $skipped += (int) $result['skipped'];
            $failed += (int) $result['failed'];
            $totalRows += (int) ($result['total'] ?? 0);

            foreach (($result['errors'] ?? []) as $error) {
                $errors[] = "File \"$fileName\": $error";
            }
        } catch (Throwable $e) {
            $failed++;
            $errors[] = "File \"" . $file['name'] . '": ' . $e->getMessage();
        }
    }

    $parts = [];
    $parts[] = "$fileCount file(s) processed";
    if ($imported > 0) $parts[] = "$imported rows imported";
    if ($skipped > 0) $parts[] = "$skipped duplicate rows skipped";
    if ($failed > 0) $parts[] = "$failed rows are invalid";

    $audit = new AuditTrailModel();
    $audit->log('Passenger', 'Import', "Imported $imported trip records from $fileCount CSV file(s) ($failed failed, $skipped duplicates skipped)",
        Auth::userId(), Auth::username(), Auth::role()
    );

    echo json_encode([
        'success' => $imported > 0,
        'message' => implode(', ', $parts),
        'imported' => $imported,
        'skipped' => $skipped,
        'failed' => $failed,
        'errors' => array_slice($errors, 0, 20)
    ]);

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
