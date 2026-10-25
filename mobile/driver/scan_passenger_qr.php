<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Manila');

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Turn uncaught PHP fatals (which bypass the Exception handler below and
// produce an empty 500) into a JSON response that names the real cause.
register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return;
    }
    error_log("[scan_passenger_qr] FATAL {$err['message']} in {$err['file']}:{$err['line']}");
    if (!headers_sent()) {
        http_response_code(500);
        header("Content-Type: application/json");
    }
    echo json_encode([
        "success" => false,
        "code" => "SERVER_FATAL",
        "message" => $err['message'],
        "file" => $err['file'],
        "line" => $err['line'],
    ]);
});

try {

    require_once __DIR__ . "/../config/database.php";

    $db = new Database();
    $conn = $db->connect();
    if (!$conn) throw new Exception("Database connection failed");

    $input = json_decode(file_get_contents("php://input"), true);

    $action       = strtolower(trim($input['action'] ?? ''));
    $txId         = trim($input['tx_id'] ?? '');
    $driverId     = trim($input['driver_id'] ?? '');
    $token        = trim($input['token'] ?? '');
    $passengerCode = trim($input['passenger_code'] ?? '');
    $fullName     = trim($input['full_name'] ?? '');
    $passengerType = trim($input['passenger_type'] ?? '');
    // The request type is only a hint. Track where the final type came from:
    // 'qr' (request hint) until the registered passenger record is read, then
    // 'registered'. 'fallback' only when neither source had a usable value.
    $typeSource    = $passengerType === '' ? 'fallback' : 'qr';
    if ($passengerType === '') {
        $passengerType = 'regular';
    }
    $busNo        = trim($input['bus_no'] ?? '');
    $tripNo       = trim($input['trip_no'] ?? '');
    $boardingPoint = trim($input['boarding_point'] ?? '');
    $latitude     = isset($input['latitude']) && $input['latitude'] !== null && $input['latitude'] !== '' ? (float)$input['latitude'] : null;
    $longitude    = isset($input['longitude']) && $input['longitude'] !== null && $input['longitude'] !== '' ? (float)$input['longitude'] : null;
    $unverified   = (int)($input['unverified'] ?? 0) === 1 ? 1 : 0;
    $passengerPhotoDataUri = trim($input['passenger_photo'] ?? '');

    // Early drop-off inputs. The distance is never accepted from the client.
    $reason        = trim($input['reason'] ?? '');
    $dropoffPoint  = trim($input['dropoff_point'] ?? '');
    $locationStatus = strtolower(trim($input['location_status'] ?? ''));
    if (!in_array($locationStatus, ['captured', 'pending'], true)) {
        $locationStatus = 'pending';
    }

    $identificationSource = trim($input['identification_source'] ?? 'qr');
    $allowedSources = ['qr', 'code_search', 'name_search', 'unverified', 'missed_boarding'];
    if (!in_array($identificationSource, $allowedSources, true)) {
        $identificationSource = 'qr';
    }

    require_once __DIR__ . '/lib/driver_auth.php';
    require_driver_auth($conn, $driverId, $token);

    require_once __DIR__ . '/lib/passenger_photo.php';

    if ($action !== 'board' && $action !== 'complete' && $action !== 'early_dropoff') {
        echo json_encode(["success" => false, "message" => "action must be 'board', 'complete', or 'early_dropoff'"]);
        exit;
    }

    if (empty($passengerCode)) {
        echo json_encode(["success" => false, "message" => "passenger_code is required"]);
        exit;
    }

    require_once __DIR__ . '/lib/qr_fare.php';
    require_once __DIR__ . '/lib/short_distance.php';

    //--------------------------------------------------------------------------
    // IDEMPOTENCY LEDGER
    //--------------------------------------------------------------------------
    if (!empty($txId)) {
        $checkTx = $conn->prepare("SELECT id, action FROM qr_scan_transactions WHERE tx_id = ? LIMIT 1");
        if (!$checkTx) throw new Exception("qr_scan_transactions query failed: " . $conn->error);
        $checkTx->bind_param("s", $txId);
        $checkTx->execute();
        $existingTx = $checkTx->get_result()->fetch_assoc();
        $checkTx->close();

        if ($existingTx) {
            echo json_encode([
                "success" => true,
                "message" => "Transaction already processed",
                "already_recorded" => true,
                "action" => $existingTx['action'],
                "tx_id" => $txId,
            ]);
            exit;
        }
    }

    // Resolve the driver's ACTIVE trip from the authenticated driver. The
    // client may submit a trip_no, but it is only validated against this
    // resolved trip: the backend is the source of truth and a stale or
    // mismatched trip_no must never cause a false "no active trip" error.
    require_once __DIR__ . '/lib/active_trip.php';
    $activeTrip = resolve_active_trip($conn, $driverId);
    if (!$activeTrip) {
        echo json_encode([
            "success" => false,
            "code" => "NO_ACTIVE_TRIP",
            "message" => "No active trip found",
        ]);
        exit;
    }
    $submittedTripNo = trim($input['trip_no'] ?? '');
    if ($submittedTripNo !== '' && !trip_matches_identifier($submittedTripNo, $activeTrip)) {
        error_log("[scan_passenger_qr] Ignoring stale trip_no '$submittedTripNo' for driver $driverId; using active trip {$activeTrip['id']}");
    }
    $tripNo = (string)$activeTrip['id'];
    $routeName = $activeTrip['route_name'];
    if (empty($busNo) && !empty($activeTrip['bus_id'])) {
        $busNo = $activeTrip['bus_id'];
    }

    $typeLower = strtolower($passengerType);

    // The short-distance early drop-off columns arrive with the
    // add_short_distance_early_dropoff migration. Detect them once so the
    // endpoint keeps working (without the extra column) if the code is
    // deployed before the migration runs.
    $hasBoardingLocationStatus = false;
    $chkPtCols = $conn->query("SHOW COLUMNS FROM passenger_trips");
    if ($chkPtCols) {
        while ($c = $chkPtCols->fetch_assoc()) {
            if ($c['Field'] === 'boarding_location_status') $hasBoardingLocationStatus = true;
        }
    }

    if ($action === 'board') {

        if (empty($fullName) || empty($busNo) || empty($boardingPoint)) {
            echo json_encode([
                "success" => false,
                "message" => "full_name, bus_no, and boarding_point are required for boarding",
            ]);
            exit;
        }

        //--------------------------------------------------------------------------
        // DUPLICATE BOARDING GUARD
        //--------------------------------------------------------------------------
        if ($unverified) {
            // An unverified board has a fresh synthetic code per attempt; guard on
            // any active unverified boarding for this driver+trip instead.
            $dupStmt = $conn->prepare(
                "SELECT id FROM passenger_trips
                 WHERE trip_no = ? AND driver_id = ? AND unverified = 1
                   AND (dropoff_time IS NULL OR dropoff_time = '') AND archived_status = 'no'
                 ORDER BY id ASC LIMIT 1"
            );
            if (!$dupStmt) throw new Exception("passenger_trips duplicate check failed: " . $conn->error);
            $dupStmt->bind_param("ss", $tripNo, $driverId);
        } else {
            $dupStmt = $conn->prepare(
                "SELECT id FROM passenger_trips
                 WHERE passenger_code = ? AND trip_no = ? AND driver_id = ?
                   AND (dropoff_time IS NULL OR dropoff_time = '') AND archived_status = 'no'
                 ORDER BY id DESC LIMIT 1"
            );
            if (!$dupStmt) throw new Exception("passenger_trips duplicate check failed: " . $conn->error);
            $dupStmt->bind_param("sss", $passengerCode, $tripNo, $driverId);
        }
        $dupStmt->execute();
        $dupRow = $dupStmt->get_result()->fetch_assoc();
        $dupStmt->close();

        if ($dupRow) {
            echo json_encode([
                "success" => false,
                "code" => "ALREADY_BOARDED",
                "message" => $unverified ? "Unverified passenger already boarded" : "Passenger already boarded",
                "state" => "boarding_active",
            ]);
            exit;
        }

        //--------------------------------------------------------------------------
        // RESOLVE passenger account + server-side type verification.
        // The QR/search passenger type is only a hint; the registered type in the
        // `passengers` table is the authority for fare discounts. Skipped for the
        // Unverified exception (no permanent account, passenger_id stays NULL).
        //--------------------------------------------------------------------------
        $passengerId = null;
        if (!$unverified) {
            $pRow = null;
            $pStmt = $conn->prepare("SELECT id, type FROM passengers WHERE LOWER(passenger_code) = LOWER(?) LIMIT 1");
            if (!$pStmt) throw new Exception("passengers query failed: " . $conn->error);
            $pStmt->bind_param("s", $passengerCode);
            $pStmt->execute();
            $pRow = $pStmt->get_result()->fetch_assoc();
            $pStmt->close();

            if (!$pRow) {
                // Fall back by name so we never create a duplicate account.
                $pStmt = $conn->prepare("SELECT id, type FROM passengers WHERE full_name = ? LIMIT 1");
                if (!$pStmt) throw new Exception("passengers query failed: " . $conn->error);
                $pStmt->bind_param("s", $fullName);
                $pStmt->execute();
                $pRow = $pStmt->get_result()->fetch_assoc();
                $pStmt->close();
            }

            if ($pRow) {
                $passengerId = (int)$pRow['id'];
                if (!empty($pRow['type'])) {
                    // Registered type is the authority; the QR/request hint is ignored.
                    $passengerType = $pRow['type'];
                    $typeSource = 'registered';
                }
            } else {
                $insP = $conn->prepare(
                    "INSERT INTO passengers (full_name, passenger_code, type, space_occupied) VALUES (?, ?, ?, 1)"
                );
                if (!$insP) throw new Exception("passengers insert failed: " . $conn->error);
                $insP->bind_param("sss", $fullName, $passengerCode, $passengerType);
                if (!$insP->execute()) throw new Exception("passengers insert failed: " . $insP->error);
                $passengerId = $conn->insert_id;
                $insP->close();
            }

            // Registered type becomes the authority used for the fare.
            $typeLower = strtolower($passengerType);
        }

        error_log("[scan_passenger_qr] passenger_type resolved code=$passengerCode type=$passengerType source=$typeSource action=board");

        //--------------------------------------------------------------------------
        // FARE (boarding point + driver GPS + passenger type) via shared rules
        //--------------------------------------------------------------------------
        $fareResult = resolve_qr_fare($conn, $routeName, $boardingPoint, $latitude, $longitude, $typeLower);
        if (!$fareResult) {
            echo json_encode([
                "success" => false,
                "code" => "NO_FARE_PRICING",
                "message" => "No fare pricing available for this route",
            ]);
            exit;
        }

        //--------------------------------------------------------------------------
        // INSERT passenger_trips
        //--------------------------------------------------------------------------
        $conn->begin_transaction();

        try {
        if ($hasBoardingLocationStatus) {
$insStmt = $conn->prepare(
            "INSERT INTO passenger_trips
             (passenger_id, passenger_code, bus_no, trip_no, driver_id,
              boarding_point, destination, latitude, longitude, fare,
              payment_method, payment_status, boarding_time,
              identification_source, unverified, boarding_location_status)
             VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, 'Cash', 'Pending', NOW(), ?, ?, ?)"
        );
        if (!$insStmt) throw new Exception("passenger_trips prepare failed: " . $conn->error);
        $insStmt->bind_param(
            "isssssdddsis",
            $passengerId,
            $passengerCode,
            $busNo,
            $tripNo,
            $driverId,
            $boardingPoint,
            $latitude,
            $longitude,
            $fareResult['fare'],
            $identificationSource,
            $unverified,
            $locationStatus
        );
        } else {
$insStmt = $conn->prepare(
            "INSERT INTO passenger_trips
             (passenger_id, passenger_code, bus_no, trip_no, driver_id,
              boarding_point, destination, latitude, longitude, fare,
              payment_method, payment_status, boarding_time,
              identification_source, unverified)
             VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, 'Cash', 'Pending', NOW(), ?, ?)"
        );
        if (!$insStmt) throw new Exception("passenger_trips prepare failed: " . $conn->error);
        $insStmt->bind_param(
            "isssssdddsi",
            $passengerId,
            $passengerCode,
            $busNo,
            $tripNo,
            $driverId,
            $boardingPoint,
            $latitude,
            $longitude,
            $fareResult['fare'],
            $identificationSource,
            $unverified
        );
        }
            if (!$insStmt->execute()) throw new Exception("passenger_trips insert failed: " . $insStmt->error);
            $recordId = $conn->insert_id;
            $insStmt->close();

            if (!empty($txId)) {
                $ledger = $conn->prepare("INSERT INTO qr_scan_transactions (tx_id, action, passenger_code, trip_no, record_id) VALUES (?, ?, ?, ?, ?)");
                if ($ledger) {
                    $ledger->bind_param("ssssi", $txId, $action, $passengerCode, $tripNo, $recordId);
                    $ledger->execute();
                    $ledger->close();
                }
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

        // Best-effort verification photo storage (never fails the board).
        if ($passengerPhotoDataUri !== '') {
            $photoUrl = save_passenger_photo($passengerPhotoDataUri, (string)$recordId);
            if ($photoUrl !== null) {
                $phStmt = $conn->prepare("UPDATE passenger_trips SET passenger_photo = ? WHERE id = ?");
                if ($phStmt) {
                    $phStmt->bind_param("si", $photoUrl, $recordId);
                    $phStmt->execute();
                    $phStmt->close();
                }
            }
        }

        echo json_encode([
            "success" => true,
            "message" => $unverified ? "Unverified passenger boarded" : "Passenger boarded",
            "action" => "board",
            "state" => "boarding_active",
            "passenger_code" => $passengerCode,
            "full_name" => $fullName,
            "passenger_type" => $passengerType,
            "type_source" => $typeSource,
            "boarding_point" => $fareResult['boarding'],
            "destination" => $fareResult['destination'],
            "fare" => $fareResult['fare'],
            "tx_id" => $txId,
            "unverified" => $unverified,
            "identification_source" => $identificationSource,
            "boarding_location_status" => $locationStatus,
        ]);

        exit;
    }

    //--------------------------------------------------------------------------
    // ACTION = EARLY DROPOFF (short-distance passenger cancellation)
    //
    // The distance is calculated on the server from the recorded boarding GPS
    // and the actual drop-off GPS; the client never supplies it. Within the
    // configurable threshold the fare is waived (₱0 / No Fare); otherwise the
    // existing fare rules apply. The drop-off is finalized either way so an
    // offline-confirmed event always converges instead of stranding the
    // passenger onboard.
    //--------------------------------------------------------------------------

    if ($action === 'early_dropoff') {

        if (!$hasBoardingLocationStatus) {
            echo json_encode([
                "success" => false,
                "code" => "MIGRATION_REQUIRED",
                "message" => "Early drop-off is not available yet. Please contact the administrator.",
            ]);
            exit;
        }

        if ($reason === '') {
            $reason = 'Passenger requested early drop-off';
        }

        if ($unverified) {
            $earlyStmt = $conn->prepare(
                "SELECT pt.id, pt.passenger_id, pt.passenger_code, pt.boarding_point, pt.destination,
                        pt.fare, pt.payment_status, pt.latitude, pt.longitude,
                        pt.boarding_location_status, pt.dropoff_time, pt.archived_status,
                        COALESCE(p.type, 'regular') AS passenger_type
                 FROM passenger_trips pt
                 LEFT JOIN passengers p ON pt.passenger_id = p.id
                 WHERE pt.trip_no = ? AND pt.driver_id = ? AND pt.unverified = 1
                   AND (pt.dropoff_time IS NULL OR pt.dropoff_time = '') AND pt.archived_status = 'no'
                 ORDER BY pt.id ASC LIMIT 1"
            );
            if (!$earlyStmt) throw new Exception("passenger_trips query failed: " . $conn->error);
            $earlyStmt->bind_param("ss", $tripNo, $driverId);
        } else {
            // The app may send the passenger_code or, when that is absent from
            // the onboard list, the numeric passenger_id; match either so an
            // onboard passenger is never reported as missing.
            $earlyStmt = $conn->prepare(
                "SELECT pt.id, pt.passenger_id, pt.passenger_code, pt.boarding_point, pt.destination,
                        pt.fare, pt.payment_status, pt.latitude, pt.longitude,
                        pt.boarding_location_status, pt.dropoff_time, pt.archived_status,
                        COALESCE(p.type, 'regular') AS passenger_type
                 FROM passenger_trips pt
                 LEFT JOIN passengers p ON pt.passenger_id = p.id
                 WHERE (pt.passenger_code = ? OR pt.passenger_id = ?)
                   AND pt.trip_no = ? AND pt.driver_id = ?
                   AND (pt.dropoff_time IS NULL OR pt.dropoff_time = '') AND pt.archived_status = 'no'
                 ORDER BY pt.id DESC LIMIT 1"
            );
            if (!$earlyStmt) throw new Exception("passenger_trips query failed: " . $conn->error);
            $earlyStmt->bind_param("siss", $passengerCode, $passengerCode, $tripNo, $driverId);
        }
        $earlyStmt->execute();
        $earlyRow = $earlyStmt->get_result()->fetch_assoc();
        $earlyStmt->close();

        if (!$earlyRow) {
            if ($unverified) {
                $doneStmt = $conn->prepare(
                    "SELECT id FROM passenger_trips
                     WHERE trip_no = ? AND driver_id = ? AND unverified = 1
                       AND ((dropoff_time IS NOT NULL AND dropoff_time <> '') OR archived_status = 'yes')
                     LIMIT 1"
                );
            } else {
                $doneStmt = $conn->prepare(
                    "SELECT id FROM passenger_trips
                     WHERE (passenger_code = ? OR passenger_id = ?)
                       AND trip_no = ? AND driver_id = ?
                       AND ((dropoff_time IS NOT NULL AND dropoff_time <> '') OR archived_status = 'yes')
                     LIMIT 1"
                );
            }
            $state = "completed";
            if ($doneStmt) {
                if ($unverified) {
                    $doneStmt->bind_param("ss", $tripNo, $driverId);
                } else {
                    $doneStmt->bind_param("siss", $passengerCode, $passengerCode, $tripNo, $driverId);
                }
                $doneStmt->execute();
                $doneRow = $doneStmt->get_result()->fetch_assoc();
                $doneStmt->close();
                $state = $doneRow ? "completed" : "none";
            }
            echo json_encode([
                "success" => false,
                "code" => $state === 'completed' ? "ALREADY_COMPLETED" : "NO_ACTIVE_BOARDING",
                "message" => $state === 'completed'
                    ? "Passenger trip already completed"
                    : "No active boarding found for this passenger",
                "state" => $state,
            ]);
            exit;
        }

        $boardingPointForFare = $earlyRow['boarding_point'];
        $codeForLedger = $earlyRow['passenger_code'] ?? $passengerCode;
        $historyName = $fullName !== '' ? $fullName : $codeForLedger;
        $fareBefore = $earlyRow['fare'] !== null ? (float)$earlyRow['fare'] : null;
        if (!$unverified && !empty($earlyRow['passenger_type'])) {
            $passengerType = $earlyRow['passenger_type'];
            $typeSource = 'registered';
        }
        $typeForFare = strtolower($earlyRow['passenger_type'] ?? $passengerType);

        $thresholdKm = short_distance_get_threshold_km($conn);
        $dropoffLocation = short_distance_resolve_dropoff_location(
            $conn,
            $driverId,
            $latitude,
            $longitude,
            $locationStatus
        );
        $boardingLocationStatus = $earlyRow['boarding_location_status'] ?? null;
        $evaluation = short_distance_evaluate(
            $earlyRow['latitude'] ?? null,
            $earlyRow['longitude'] ?? null,
            $boardingLocationStatus,
            $dropoffLocation['latitude'],
            $dropoffLocation['longitude'],
            $dropoffLocation['location_status'],
            $thresholdKm
        );

        $eligible = (bool)$evaluation['eligible'];
        $distanceKm = $evaluation['distance_km'];

        $normalFare = null;
        $finalDestination = $earlyRow['destination'];
        if ($eligible) {
            $finalFare = 0.0;
            $newPaymentStatus = 'No Fare';
            $dropoffType = 'early_short_distance';
            $cancellationStatus = 'EARLY_DROP_OFF';
            $verificationStatus = $evaluation['verification_status'];
        } else {
            // Existing fare rules decide; the fare is never forced to ₱0 here.
            $normalFare = resolve_qr_fare(
                $conn,
                $routeName,
                $boardingPointForFare,
                $dropoffLocation['latitude'],
                $dropoffLocation['longitude'],
                $typeForFare
            );
            if ($normalFare) {
                $finalDestination = $normalFare['destination'];
                $finalFare = (float)$normalFare['fare'];
            } else {
                $finalFare = $fareBefore !== null ? $fareBefore : 15.0;
            }
            $newPaymentStatus = !empty($earlyRow['payment_status']) ? $earlyRow['payment_status'] : 'Pending';
            $dropoffType = 'normal';
            $cancellationStatus = $evaluation['both_endpoints_untrusted'] ? 'NEEDS_VERIFICATION' : 'BEYOND_LIMIT';
            $verificationStatus = $evaluation['both_endpoints_untrusted']
                ? 'needs_review'
                : $evaluation['verification_status'];
        }

        $actualDropoffPoint = $dropoffPoint;
        if ($actualDropoffPoint === '') {
            $actualDropoffPoint = $normalFare['destination'] ?? ($earlyRow['destination'] ?? '');
        }

        error_log(
            "[scan_passenger_qr] early_dropoff code=$codeForLedger distance="
            . ($distanceKm === null ? 'n/a' : $distanceKm) . " threshold=$thresholdKm status=$cancellationStatus"
        );

        $conn->begin_transaction();

        try {
            $earlyRecordId = (int)$earlyRow['id'];

            if ($eligible) {
                $upStmt = $conn->prepare(
                    "UPDATE passenger_trips
                     SET fare = 0.00, payment_status = 'No Fare', dropoff_time = NOW(),
                         dropoff_type = 'early_short_distance',
                         actual_dropoff_point = ?, dropoff_latitude = ?, dropoff_longitude = ?,
                         distance_traveled_km = ?, dropoff_threshold_km = ?, dropoff_reason = ?,
                         dropoff_location_status = ?
                     WHERE id = ?"
                );
                if (!$upStmt) throw new Exception("passenger_trips update failed: " . $conn->error);
                $upStmt->bind_param(
                    "sddddssi",
                    $actualDropoffPoint,
                    $dropoffLocation['latitude'],
                    $dropoffLocation['longitude'],
                    $distanceKm,
                    $thresholdKm,
                    $reason,
                    $dropoffLocation['location_status'],
                    $earlyRecordId
                );
            } else {
                $upStmt = $conn->prepare(
                    "UPDATE passenger_trips
                     SET destination = ?, fare = ?, dropoff_time = NOW(),
                         identification_source = ?, unverified = ?,
                         dropoff_type = 'normal',
                         actual_dropoff_point = ?, dropoff_latitude = ?, dropoff_longitude = ?,
                         distance_traveled_km = ?, dropoff_threshold_km = ?, dropoff_reason = ?,
                         dropoff_location_status = ?
                     WHERE id = ?"
                );
                if (!$upStmt) throw new Exception("passenger_trips update failed: " . $conn->error);
                $updateIdentificationSource = $unverified ? 'unverified' : $identificationSource;
                $upStmt->bind_param(
                    "sdsisddddssi",
                    $finalDestination,
                    $finalFare,
                    $updateIdentificationSource,
                    $unverified,
                    $actualDropoffPoint,
                    $dropoffLocation['latitude'],
                    $dropoffLocation['longitude'],
                    $distanceKm,
                    $thresholdKm,
                    $reason,
                    $dropoffLocation['location_status'],
                    $earlyRecordId
                );
            }
            if (!$upStmt->execute()) throw new Exception("passenger_trips update failed: " . $upStmt->error);
            $upStmt->close();

            // Best-effort finalized record for statistics/remittance.
            try {
                $historyDestination = ($finalDestination !== null && $finalDestination !== '')
                    ? $finalDestination
                    : $actualDropoffPoint;
                $hStmt = $conn->prepare(
                    "INSERT INTO passenger_trip_history
                     (driver_id, trip_id, trip_date, bus_number, boarding_point, destination,
                      passenger_name, passenger_code, fare, payment_method, dropoff_time)
                     VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, 'Cash', NOW())"
                );
                if ($hStmt) {
                    $hStmt->bind_param(
                        "sssssssd",
                        $driverId,
                        $tripNo,
                        $busNo,
                        $boardingPointForFare,
                        $historyDestination,
                        $historyName,
                        $codeForLedger,
                        $finalFare
                    );
                    $hStmt->execute();
                    $hStmt->close();
                }
            } catch (Exception $e) {
                // history insert is optional; ignore schema drift
            }

            short_distance_write_audit($conn, [
                'passenger_trip_id' => $earlyRecordId,
                'passenger_id' => $earlyRow['passenger_id'] ?? null,
                'passenger_code' => $codeForLedger,
                'trip_no' => $tripNo,
                'bus_no' => $busNo,
                'driver_id' => $driverId,
                'boarding_point' => $boardingPointForFare,
                'original_destination' => $earlyRow['destination'] ?? null,
                'current_destination' => $earlyRow['destination'] ?? null,
                'actual_dropoff_point' => $actualDropoffPoint,
                'boarding_latitude' => $earlyRow['latitude'] ?? null,
                'boarding_longitude' => $earlyRow['longitude'] ?? null,
                'boarding_location_status' => $boardingLocationStatus,
                'dropoff_latitude' => $dropoffLocation['latitude'],
                'dropoff_longitude' => $dropoffLocation['longitude'],
                'dropoff_location_status' => $dropoffLocation['location_status'],
                'distance_traveled_km' => $distanceKm,
                'threshold_km' => $thresholdKm,
                'reason' => $reason,
                'fare_before' => $fareBefore,
                'final_fare' => $finalFare,
                'cancellation_status' => $cancellationStatus,
                'gps_fallback_used' => $evaluation['gps_fallback_used'],
                'verification_status' => $verificationStatus,
            ]);

            if (!empty($txId)) {
                $ledger = $conn->prepare("INSERT INTO qr_scan_transactions (tx_id, action, passenger_code, trip_no, record_id) VALUES (?, ?, ?, ?, ?)");
                if ($ledger) {
                    $ledger->bind_param("ssssi", $txId, $action, $codeForLedger, $tripNo, $earlyRecordId);
                    $ledger->execute();
                    $ledger->close();
                }
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

        $responseDistanceKm = $distanceKm !== null ? round((float)$distanceKm, 3) : null;
        echo json_encode([
            "success" => true,
            "message" => $eligible
                ? "Early drop-off recorded. No fare charged."
                : ($cancellationStatus === 'BEYOND_LIMIT'
                    ? "Passenger has traveled beyond the short-distance cancellation limit."
                    : "Drop-off recorded; short-distance eligibility needs verification."),
            "action" => "early_dropoff",
            "state" => "completed",
            "eligible" => $eligible,
            "status" => $cancellationStatus,
            "dropoff_type" => $dropoffType,
            "distance_km" => $evaluation['both_endpoints_untrusted'] ? null : $responseDistanceKm,
            "threshold_km" => $thresholdKm,
            "fare" => $finalFare,
            "payment_status" => $newPaymentStatus,
            "passenger_code" => $codeForLedger,
            "full_name" => $historyName,
            "passenger_type" => $passengerType,
            "type_source" => $typeSource,
            "boarding_point" => $boardingPointForFare,
            "destination" => $finalDestination,
            "actual_dropoff_point" => $actualDropoffPoint,
            "gps_fallback_used" => $evaluation['gps_fallback_used'],
            "verification_status" => $verificationStatus,
            "tx_id" => $txId,
            "unverified" => $unverified,
            "identification_source" => $unverified ? 'unverified' : $identificationSource,
        ]);

        exit;
    }

    //--------------------------------------------------------------------------
    // ACTION = COMPLETE (drop-off)
    //--------------------------------------------------------------------------

    if ($unverified) {
        // Unverified completion: no registered code to match. Finalize the
        // EARLIEST active unverified boarding for this driver+trip (FIFO).
        $activeStmt = $conn->prepare(
            "SELECT pt.id, pt.passenger_code, pt.boarding_point, pt.fare, pt.dropoff_time, pt.archived_status,
                    COALESCE(p.type, 'regular') AS passenger_type
             FROM passenger_trips pt
             LEFT JOIN passengers p ON pt.passenger_id = p.id
             WHERE pt.trip_no = ? AND pt.driver_id = ? AND pt.unverified = 1
               AND (pt.dropoff_time IS NULL OR pt.dropoff_time = '') AND pt.archived_status = 'no'
             ORDER BY pt.id ASC LIMIT 1"
        );
        if (!$activeStmt) throw new Exception("passenger_trips query failed: " . $conn->error);
        $activeStmt->bind_param("ss", $tripNo, $driverId);
    } else {
        // The app may send the passenger_code or, when that is absent from the
        // onboard list (manually added / one-time passengers store
        // passenger_code = NULL), the numeric passenger_id; match either so an
        // onboard passenger is never reported as missing. Mirrors the
        // early_dropoff lookup.
        $activeStmt = $conn->prepare(
            "SELECT pt.id, pt.passenger_code, pt.boarding_point, pt.fare, pt.dropoff_time, pt.archived_status,
                    COALESCE(p.type, 'regular') AS passenger_type
             FROM passenger_trips pt
             LEFT JOIN passengers p ON pt.passenger_id = p.id
             WHERE (pt.passenger_code = ? OR pt.passenger_id = ?) AND pt.trip_no = ? AND pt.driver_id = ?
               AND (pt.dropoff_time IS NULL OR pt.dropoff_time = '') AND pt.archived_status = 'no'
             ORDER BY pt.id DESC LIMIT 1"
        );
        if (!$activeStmt) throw new Exception("passenger_trips query failed: " . $conn->error);
        $activeStmt->bind_param("siss", $passengerCode, $passengerCode, $tripNo, $driverId);
    }
    $activeStmt->execute();
    $activeRow = $activeStmt->get_result()->fetch_assoc();
    $activeStmt->close();

    if (!$activeRow) {
        if ($unverified) {
            $doneStmt = $conn->prepare(
                "SELECT id FROM passenger_trips
                 WHERE trip_no = ? AND driver_id = ? AND unverified = 1
                   AND ((dropoff_time IS NOT NULL AND dropoff_time <> '') OR archived_status = 'yes')
                 LIMIT 1"
            );
        } else {
            $doneStmt = $conn->prepare(
                "SELECT id FROM passenger_trips
                 WHERE (passenger_code = ? OR passenger_id = ?) AND trip_no = ? AND driver_id = ?
                   AND ((dropoff_time IS NOT NULL AND dropoff_time <> '') OR archived_status = 'yes')
                 LIMIT 1"
            );
        }
        $state = "completed";
        if ($doneStmt) {
            if ($unverified) {
                $doneStmt->bind_param("ss", $tripNo, $driverId);
            } else {
                $doneStmt->bind_param("siss", $passengerCode, $passengerCode, $tripNo, $driverId);
            }
            $doneStmt->execute();
            $doneRow = $doneStmt->get_result()->fetch_assoc();
            $doneStmt->close();
            $state = $doneRow ? "completed" : "none";
        }

        $message = $unverified
            ? ($state === 'completed'
                ? "Unverified passenger trip already completed"
                : "No active unverified boarding found for this driver")
            : ($state === 'completed' ? "Passenger trip already completed" : "No active boarding found for this passenger");
        echo json_encode([
            "success" => false,
            "code" => $state === 'completed' ? "ALREADY_COMPLETED" : "NO_ACTIVE_BOARDING",
            "message" => $message,
            "state" => $state,
        ]);
        exit;
    }

    $boardingPointForFare = $activeRow['boarding_point'];
    $codeForLedger = $activeRow['passenger_code'] ?? $passengerCode;
    // The registered type read at boarding is the authority for the drop-off
    // response and fare; never echo the request hint back as the resolved type.
    if (!$unverified && !empty($activeRow['passenger_type'])) {
        $passengerType = $activeRow['passenger_type'];
        $typeSource = 'registered';
    }
    $typeForFare = strtolower($activeRow['passenger_type'] ?? $passengerType);

    error_log("[scan_passenger_qr] passenger_type resolved code=$codeForLedger type=$passengerType source=$typeSource action=complete");

    $fareResult = resolve_qr_fare($conn, $routeName, $boardingPointForFare, $latitude, $longitude, $typeForFare);
    if (!$fareResult) {
        echo json_encode([
            "success" => false,
            "code" => "NO_FARE_PRICING",
            "message" => "No fare pricing available for this route",
        ]);
        exit;
    }

    $conn->begin_transaction();

    try {
        $upStmt = $conn->prepare(
            "UPDATE passenger_trips
             SET destination = ?, fare = ?, dropoff_time = NOW(),
                 identification_source = ?, unverified = ?
             WHERE id = ?"
        );
        if (!$upStmt) throw new Exception("passenger_trips update failed: " . $conn->error);
        // bind_param takes variables by reference, so expressions must be
        // assigned first (PHP 8 rejects inline ternary/cast expressions).
        $updateIdentificationSource = $unverified ? 'unverified' : $identificationSource;
        $activeRecordId = (int)$activeRow['id'];
        $upStmt->bind_param(
            "sdsii",
            $fareResult['destination'],
            $fareResult['fare'],
            $updateIdentificationSource,
            $unverified,
            $activeRecordId
        );
        if (!$upStmt->execute()) throw new Exception("passenger_trips update failed: " . $upStmt->error);
        $upStmt->close();

        // Best-effort finalized record for statistics/remittance. Never fails the scan.
        try {
            $hStmt = $conn->prepare(
                "INSERT INTO passenger_trip_history
                 (driver_id, trip_id, trip_date, bus_number, boarding_point, destination,
                  passenger_name, passenger_code, fare, payment_method, dropoff_time)
                 VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, 'Cash', NOW())"
            );
            if ($hStmt) {
                $hStmt->bind_param(
                    "sssssssd",
                    $driverId,
                    $tripNo,
                    $busNo,
                    $boardingPointForFare,
                    $fareResult['destination'],
                    $fullName,
                    $codeForLedger,
                    $fareResult['fare']
                );
                $hStmt->execute();
                $hStmt->close();
            }
        } catch (Exception $e) {
            // history insert is optional; ignore schema drift
        }

        if (!empty($txId)) {
            $ledger = $conn->prepare("INSERT INTO qr_scan_transactions (tx_id, action, passenger_code, trip_no, record_id) VALUES (?, ?, ?, ?, ?)");
            if ($ledger) {
                $ledgerRecordId = (int)$activeRow['id'];
                $ledger->bind_param("ssssi", $txId, $action, $codeForLedger, $tripNo, $ledgerRecordId);
                $ledger->execute();
                $ledger->close();
            }
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

    // Best-effort verification photo storage (never fails the completion).
    if ($passengerPhotoDataUri !== '') {
        $photoUrl = save_passenger_photo($passengerPhotoDataUri, (string)$activeRow['id']);
        if ($photoUrl !== null) {
            $phStmt = $conn->prepare("UPDATE passenger_trips SET passenger_photo = ? WHERE id = ?");
            if ($phStmt) {
                $recId = (int)$activeRow['id'];
                $phStmt->bind_param("si", $photoUrl, $recId);
                $phStmt->execute();
                $phStmt->close();
            }
        }
    }

    echo json_encode([
        "success" => true,
        "message" => $unverified ? "Unverified passenger trip completed" : "Passenger trip completed",
        "action" => "complete",
        "state" => "completed",
        "passenger_code" => $codeForLedger,
        "full_name" => $fullName,
        "passenger_type" => $passengerType,
        "type_source" => $typeSource,
        "boarding_point" => $boardingPointForFare,
        "destination" => $fareResult['destination'],
        "fare" => $fareResult['fare'],
        "tx_id" => $txId,
        "unverified" => $unverified,
        "identification_source" => $unverified ? 'unverified' : $identificationSource,
    ]);

    $conn->close();

} catch (Exception $e) {
    error_log("[scan_passenger_qr] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "code" => "SERVER_ERROR",
        "message" => "Something went wrong. Please try again.",
        "detail" => $e->getMessage(),
    ]);
}