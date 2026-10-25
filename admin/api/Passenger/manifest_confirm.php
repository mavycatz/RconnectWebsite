<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/PassengerManifestFields.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/PassengerTripManifestService.php';

use App\Auth\Auth;
use App\Core\Database;
use Admin\Services\PassengerManifestFields;
use Admin\Services\PassengerTripManifestService;

/**
 * Manifest confirm/import endpoint (Import Trips — final step).
 *
 * Receives the admin-edited draft { trip, passengers[] } and:
 *   1. Re-validates every record server-side (never trusts the client).
 *   2. Flags possible duplicates and skips them unless the admin confirmed.
 *   3. Resolves internal fields (driver_id, trip_id, archived/archived_at).
 *   4. Inserts confirmed, valid rows into passenger_trip_history in one
 *      transaction (only the columns that already exist — no schema change).
 *   5. Reports imported-vs-skipped counts.
 *
 * Invalid rows are NEVER imported; review rows are imported only when the
 * admin explicitly confirmed them after correcting values.
 */

if (!Auth::check() || !in_array(Auth::user()['role'] ?? '', ['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input) || !isset($input['trip']) || !isset($input['passengers'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data.']);
    exit;
}

$tripRaw = is_array($input['trip']) ? $input['trip'] : [];
$passengersRaw = is_array($input['passengers']) ? $input['passengers'] : [];

if (empty($passengersRaw)) {
    echo json_encode(['success' => false, 'message' => 'No passengers to import.']);
    exit;
}

$db = Database::get();
$service = new PassengerTripManifestService($db);

// Normalize the trip header.
$trip = PassengerManifestFields::toCanonicalTrip($tripRaw);
$tripDate = $service->normalizeDate((string)($trip['trip_date'] ?? '')) ?? date('Y-m-d');
$driverName = trim((string)($trip['driver'] ?? ''));
$driverId = $service->resolveDriverId($driverName);
$busNumber = trim((string)($trip['bus_number'] ?? ''));
$paymentMethod = PassengerManifestFields::normalizePayment((string)($trip['payment_method'] ?? 'cash')) ?? 'cash';

// Resolve trip_id from the extracted trip number, else generate TRIP-<n>.
$tripId = trim((string)($trip['trip_number'] ?? ''));
if ($tripId === '') {
    $tripId = $service->generateTripId();
}

$existingTripRow = [
    'trip_date'       => $tripDate,
    'route'           => trim((string)($trip['route'] ?? '')),
    'driver'          => $driverName,
    'driver_id'       => $driverId,
    'bus_number'      => $busNumber,
    'trip_number'     => $tripId,
    'payment_method'  => $paymentMethod,
    'remarks'         => $trip['remarks'] ?? null,
];

$imported = 0;
$skipped = 0;
$skippedReasons = [];
$toInsert = [];

foreach ($passengersRaw as $index => $pRaw) {
    if (!is_array($pRaw)) {
        continue;
    }

    // Canonicalize the row; fill default payment from the trip.
    $p = PassengerManifestFields::toCanonicalPassenger($pRaw);
    if (($p['payment_method'] ?? null) === null) {
        $p['payment_method'] = $paymentMethod;
    }

    $valid = $service->validatePassenger($p, $existingTripRow);
    $p['passenger_type'] = PassengerManifestFields::normalizePassengerType((string)($p['passenger_type'] ?? '')) ?? $p['passenger_type'];

    $confirmed = !empty($pRaw['_confirmed']);
    $status = $valid['status'];

    // Invalid rows are never imported.
    if ($status === PassengerManifestFields::STATUS_INVALID) {
        $skipped++;
        $skippedReasons[] = "Row " . ($index + 1) . " ({$p['passenger_name']}): " . implode('; ', $valid['reasons']);
        continue;
    }

    // Duplicate protection: skip unless admin explicitly confirmed this row.
    if ($service->isPossibleDuplicate($p, $existingTripRow) && !$confirmed) {
        $skipped++;
        $skippedReasons[] = "Row " . ($index + 1) . " ({$p['passenger_name']}): possible duplicate record";
        continue;
    }

    // Review rows require explicit admin confirmation.
    if ($status === PassengerManifestFields::STATUS_REVIEW && !$confirmed) {
        $skipped++;
        $skippedReasons[] = "Row " . ($index + 1) . " ({$p['passenger_name']}): " . implode('; ', $valid['reasons']);
        continue;
    }

    $toInsert[] = [
        'passenger_name'  => trim((string)($p['passenger_name'] ?? '')),
        'passenger_type'  => $p['passenger_type'] ?? 'Regular',
        'student_id'      => ($p['student_id'] ?? null) !== null ? (string)$p['student_id'] : null,
        'boarding_point'  => trim((string)($p['boarding_point'] ?? '')),
        'destination'     => trim((string)($p['destination'] ?? '')),
        'fare'            => (float)($p['fare'] ?? 0),
        'payment_method'  => PassengerManifestFields::normalizePayment((string)($p['payment_method'] ?? $paymentMethod)) ?? $paymentMethod,
    ];
}

if (empty($toInsert)) {
    echo json_encode([
        'success' => false,
        'message' => 'No records imported (all skipped or unconfirmed).',
        'imported' => 0,
        'skipped' => $skipped,
        'skipped_reasons' => $skippedReasons,
    ]);
    exit;
}

try {
    $db->beginTransaction();

    $sql = "INSERT INTO passenger_trip_history (
        passenger_name, passenger_type, student_id,
        boarding_point, destination, drivers_full_name,
        driver_id, bus_number, trip_id, fare, payment_method,
        trip_date, remarks, archived, archived_at, type, space_occupied
    ) VALUES (
        :passenger_name, :passenger_type, :student_id,
        :boarding_point, :destination, :drivers_full_name,
        :driver_id, :bus_number, :trip_id, :fare, :payment_method,
        :trip_date, :remarks, 0, :archived_at, :type, 1
    )";

    $stmt = $db->prepare($sql);
    $archivedAt = date('Y-m-d H:i:s');
    $tripRemarks = isset($trip['remarks']) && trim((string)$trip['remarks']) !== '' ? trim((string)$trip['remarks']) : null;

    foreach ($toInsert as $row) {
        $stmt->execute([
            ':passenger_name'  => $row['passenger_name'],
            ':passenger_type'  => $row['passenger_type'],
            ':student_id'      => $row['student_id'],
            ':boarding_point'  => $row['boarding_point'],
            ':destination'     => $row['destination'],
            ':drivers_full_name' => $driverName,
            ':driver_id'       => $driverId,
            ':bus_number'      => $busNumber,
            ':trip_id'         => $tripId,
            ':fare'            => $row['fare'],
            ':payment_method'  => $row['payment_method'],
            ':trip_date'       => $tripDate,
            ':remarks'         => $tripRemarks,
            ':archived_at'     => $archivedAt,
            ':type'            => 'passenger',
        ]);
        $imported++;
    }

    $db->commit();
} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Import failed: ' . $e->getMessage(),
        'imported' => 0,
        'skipped' => $skipped,
    ]);
    exit;
}

$parts = [];
$parts[] = "$imported passenger record(s) imported successfully";
if ($skipped > 0) {
    $parts[] = "$skipped record(s) skipped";
}

echo json_encode([
    'success' => true,
    'message' => implode(', ', $parts),
    'imported' => $imported,
    'skipped' => $skipped,
    'skipped_reasons' => $skippedReasons,
]);
