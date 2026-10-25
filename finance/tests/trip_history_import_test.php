<?php declare(strict_types=1);

/**
 * Trip-history CSV import service: the shared rules used by the admin Trip
 * History import and the Financial Reports "Import Data" component.
 */

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once __DIR__ . '/../services/TripHistoryCsvImportService.php';

use Finance\Services\TripHistoryCsvImportService;

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec("
    CREATE TABLE passenger_trip_history (
        history_id INTEGER PRIMARY KEY AUTOINCREMENT,
        passenger_name TEXT NOT NULL,
        passenger_type TEXT,
        student_id TEXT,
        boarding_point TEXT,
        destination TEXT,
        drivers_full_name TEXT,
        driver_id TEXT,
        bus_number TEXT,
        trip_id INTEGER,
        fare REAL DEFAULT 0,
        payment_method TEXT,
        date_booked TEXT,
        trip_date TEXT,
        senior_pwd_id TEXT,
        beneficiary_id TEXT,
        coop_disc_id TEXT,
        remarks TEXT,
        archived INTEGER NOT NULL DEFAULT 0,
        archived_at TEXT,
        time_of_departure TEXT,
        time_of_arrival TEXT
    );
    CREATE TABLE drivers (driver_id TEXT PRIMARY KEY, full_name TEXT, assigned_buses TEXT);
    INSERT INTO drivers (driver_id, full_name, assigned_buses) VALUES ('D1', 'Driver One', 'Bus-1,Bus-2');
    INSERT INTO passenger_trip_history (passenger_name, passenger_type, boarding_point, destination, drivers_full_name, bus_number, trip_id, fare, payment_method, date_booked, trip_date)
        VALUES ('Juan Dela Cruz', 'Regular', 'Cabanatuan', 'Gapan', 'Driver One', 'Bus-1', 101, 30, 'cash', '2026-10-05', '2026-10-05');
");

$temporary = sys_get_temp_dir() . '/opencode';
if (!is_dir($temporary)) {
    mkdir($temporary, 0777, true);
}
$csvPath = $temporary . '/trip_history_import_test.csv';
file_put_contents($csvPath, implode("\n", [
    'passenger_name,passenger_type,boarding_point,destination,drivers_full_name,bus_number,fare,payment_method,date_booked,trip_date,time_of_departure,time_of_arrival,trip_id,benefeciary_id,history_id',
    'Maria Santos,Student,Cabanatuan,Rizal,Driver One,,45.50,cash,10/01/2026,2026-10-01,08:00,09:30,202,BEN-1,',
    'Juan Dela Cruz,Regular,Cabanatuan,Gapan,Driver One,Bus-1,30,cash,2026-10-05,10/5/2026,,,101,,',
    'Pedro Reyes,Regular,Cabanatuan,Rizal,Driver Two,Bus-2,abc,cash,2026-10-02,2026-10-02,,,203,,',
    'Ana Cruz,Regular,Cabanatuan,Rizal,Driver Two,Bus-2,20,bitcoin,2026-10-03,2026-10-03,,,204,,',
    'Luis Tan,Regular,Cabanatuan,,Driver Two,Bus-2,20,cash,2026-10-04,2026-10-04,,,205,,',
    'Rose Lim,Regular,Cabanatuan,Rizal,Driver Two,Bus-2,20,cash,not-a-date,2026-10-05,,,206,,',
    'Ana Lopez,Student,Cabanatuan,Bitas,Driver Two,Bus-2,21,cash,5/25/2026,5/25/2026,08:10,09:10,TRIP-089,,',
    '',
]));

/* ---- Preview: validation, duplicates, and counts ------------------------- */
$preview = TripHistoryCsvImportService::preview($db, $csvPath, 'trip_history_import_test.csv');
check($preview['success'] === true, 'Preview succeeds');
check($preview['missing_required'] === [], 'All required columns are found');
check($preview['counts']['total'] === 7, 'Preview counts every data row');
check($preview['counts']['valid'] === 2, 'Preview counts the valid rows');
check($preview['counts']['duplicate'] === 1, 'Preview counts the duplicate row');
check($preview['counts']['error'] === 4, 'Preview counts the invalid rows');
check(in_array('history_id', $preview['ignored_headers'], true), 'Unknown headers are reported as ignored');

$beneficiaryColumn = null;
foreach ($preview['known_columns'] as $column) {
    if ($column['name'] === 'beneficiary_id') {
        $beneficiaryColumn = $column;
    }
}
check($beneficiaryColumn !== null && $beneficiaryColumn['found'] === true, 'A known optional column is found through its common spelling alias');

$statusByLine = [];
foreach ($preview['rows'] as $row) {
    $statusByLine[$row['line']] = $row['status'];
}
check($statusByLine[2] === 'valid', 'A complete row is valid');
check($statusByLine[3] === 'duplicate', 'An existing passenger + trip id + trip date is a duplicate');
check($statusByLine[4] === 'error', 'A non-numeric fare is an error');
check($statusByLine[5] === 'error', 'An unknown payment method is an error');
check($statusByLine[6] === 'error', 'A missing required destination is an error');
check($statusByLine[7] === 'error', 'An unparseable date is an error');
check($statusByLine[8] === 'valid', 'A non-numeric trip id such as TRIP-089 is valid');

/* ---- Import: valid rows inserted, duplicates and invalid ones counted ---- */
$result = TripHistoryCsvImportService::import($db, $csvPath, 'trip_history_import_test.csv');
check($result['imported'] === 2, 'Only the valid rows are imported');
check($result['skipped'] === 1, 'The duplicate row is skipped');
check($result['failed'] === 4, 'The invalid rows are counted as failed');
check($result['date_from'] === '2026-05-25' && $result['date_to'] === '2026-10-01', 'The import reports the covered date range');

$imported = $db->query("SELECT * FROM passenger_trip_history WHERE passenger_name = 'Maria Santos'")->fetch(PDO::FETCH_ASSOC);
check($imported !== false, 'The imported row is stored');
check(abs((float) $imported['fare'] - 45.50) < 0.001, 'The fare is stored');
check($imported['payment_method'] === 'cash', 'The payment method is stored');
check($imported['trip_date'] === '2026-10-01' && $imported['date_booked'] === '2026-10-01', 'US-style dates are normalised');
check($imported['time_of_departure'] === '08:00:00' && $imported['time_of_arrival'] === '09:30:00', 'Times are normalised');
check($imported['driver_id'] === 'D1', 'The driver is resolved from the full name');
check($imported['bus_number'] === 'Bus-1', 'The assigned bus fills a blank bus number');
check($imported['beneficiary_id'] === 'BEN-1', 'The alias column keeps its value');
check((int) $imported['archived'] === 0, 'Imported rows start active');

$stringTrip = $db->query("SELECT * FROM passenger_trip_history WHERE passenger_name = 'Ana Lopez'")->fetch(PDO::FETCH_ASSOC);
check($stringTrip !== false && $stringTrip['trip_id'] === 'TRIP-089', 'A text trip id is stored as-is');

$rowCount = (int) $db->query('SELECT COUNT(*) FROM passenger_trip_history')->fetchColumn();
check($rowCount === 3, 'Exactly two new history rows exist');

/* ---- Re-importing the same file cannot duplicate records ----------------- */
$second = TripHistoryCsvImportService::import($db, $csvPath, 'trip_history_import_test.csv');
check($second['imported'] === 0, 'A repeated import inserts nothing');
check($second['skipped'] === 3, 'Prior and newly imported rows are all skipped as duplicates');
check((int) $db->query('SELECT COUNT(*) FROM passenger_trip_history')->fetchColumn() === 3, 'No duplicates were written');

/* ---- Missing required columns are refused before inserting ---------------- */
$missingPath = $temporary . '/trip_history_missing_column.csv';
file_put_contents($missingPath, "passenger_name,boarding_point,destination\nMaria Santos,Cabanatuan,Rizal\n");
$missingPreview = TripHistoryCsvImportService::preview($db, $missingPath, 'missing.csv');
check(in_array('drivers_full_name', $missingPreview['missing_required'], true), 'A missing required column is reported');

$threw = false;
try {
    TripHistoryCsvImportService::import($db, $missingPath, 'missing.csv');
} catch (RuntimeException $e) {
    $threw = true;
    check(str_contains($e->getMessage(), 'drivers_full_name'), 'The refusal names the missing column');
}
check($threw, 'Import refuses a file without the required columns');
check((int) $db->query('SELECT COUNT(*) FROM passenger_trip_history')->fetchColumn() === 3, 'The refused import wrote nothing');

/* ---- Upload validation ---------------------------------------------------- */
$badUpload = false;
try {
    TripHistoryCsvImportService::validateUpload(['error' => UPLOAD_ERR_NO_FILE]);
} catch (RuntimeException $e) {
    $badUpload = true;
}
check($badUpload, 'A missing upload is refused');

@unlink($csvPath);
@unlink($missingPath);

echo "$checks trip history import checks passed.\n";
