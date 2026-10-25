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

try {

    require_once __DIR__ . "/../config/database.php";

    $db = new Database();
    $conn = $db->connect();

    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);

    if (!$data) {
        throw new Exception("Invalid JSON payload");
    }

    $inspector_id   = trim($data['inspector_id'] ?? '');
    $bus_no         = trim($data['bus_no'] ?? '');
    $trip_no        = trim($data['trip_no'] ?? '');
    $driver_id      = trim($data['driver_id'] ?? '');
    $full_name      = trim($data['name'] ?? $data['full_name'] ?? '');
    $passenger_type = trim($data['type'] ?? $data['passenger_type'] ?? 'Regular');
    $discount_id    = trim($data['discount_id'] ?? $data['discountId'] ?? '');
    $passenger_code = trim($data['passenger_code'] ?? '');
    $boarding_point = trim($data['boarding'] ?? $data['boarding_point'] ?? '');
    $destination    = trim($data['destination'] ?? '');
    $fare           = floatval($data['fare'] ?? 0);
    $payment_method = trim($data['payment_method'] ?? 'Cash');
    $cash_amount    = floatval($data['cash_amount'] ?? $fare);
    $gcash_amount   = floatval($data['gcash_amount'] ?? 0);
    $photo_data_uri = trim($data['passenger_photo'] ?? $data['photoUri'] ?? '');
    $updated_by     = trim($data['updated_by'] ?? $inspector_id);

    if (empty($full_name)) {
        throw new Exception("Passenger full name is required");
    }

    if (empty($bus_no)) {
        throw new Exception("Bus number is required");
    }

    if (empty($boarding_point)) {
        throw new Exception("Boarding point is required");
    }

    if (empty($destination)) {
        throw new Exception("Destination is required");
    }

    // Generate unique passenger code if missing
    if (empty($passenger_code)) {
        $timePart = strtoupper(base_convert((string)time(), 10, 36));
        $randPart = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 4));
        $passenger_code = "RCP-{$timePart}-{$randPart}";
    }

    //--------------------------------------------------------------------------
    // SAVE PASSENGER PHOTO TO DISK
    //--------------------------------------------------------------------------
    $saved_photo_path = null;
    if (!empty($photo_data_uri) && str_starts_with($photo_data_uri, 'data:image/')) {
        $prefixEnd = strpos($photo_data_uri, ';base64,');
        if ($prefixEnd !== false) {
            $base64Data = substr($photo_data_uri, $prefixEnd + 8);
            $photoBytes = base64_decode($base64Data);
            if ($photoBytes !== false && strlen($photoBytes) > 0) {
                $uploadDir = __DIR__ . '/../passenger_photos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $safeCode = preg_replace('/[^a-zA-Z0-9_-]/', '-', $passenger_code);
                $fileName = "{$safeCode}_" . time() . ".jpg";
                $filePath = $uploadDir . $fileName;
                if (file_put_contents($filePath, $photoBytes) !== false) {
                    $saved_photo_path = "passenger_photos/{$fileName}";
                }
            }
        }
    }

    //--------------------------------------------------------------------------
    // RESOLVE OR INSERT PASSENGER IN CURRENT `passengers` TABLE
    //--------------------------------------------------------------------------
    $passenger_id = null;

    // Check if passenger exists by passenger_code
    $checkStmt = $conn->prepare("SELECT id, type, full_name FROM passengers WHERE passenger_code = ? LIMIT 1");
    if ($checkStmt) {
        $checkStmt->bind_param("s", $passenger_code);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        if ($existing) {
            $passenger_id = (int)$existing['id'];
        }
    }

    if (!$passenger_id) {
        // Fall back check by exact full_name
        $checkName = $conn->prepare("SELECT id FROM passengers WHERE full_name = ? LIMIT 1");
        if ($checkName) {
            $checkName->bind_param("s", $full_name);
            $checkName->execute();
            $existingName = $checkName->get_result()->fetch_assoc();
            $checkName->close();
            if ($existingName) {
                $passenger_id = (int)$existingName['id'];
                $updStmt = $conn->prepare("UPDATE passengers SET passenger_code = ? WHERE id = ? AND (passenger_code IS NULL OR passenger_code = '')");
                if ($updStmt) {
                    $updStmt->bind_param("si", $passenger_code, $passenger_id);
                    $updStmt->execute();
                    $updStmt->close();
                }
            }
        }
    }

    if (!$passenger_id) {
        // Insert into existing passengers table using standard columns
        $insStmt = $conn->prepare("
            INSERT INTO passengers (full_name, passenger_code, type, space_occupied)
            VALUES (?, ?, ?, 1)
        ");
        if ($insStmt) {
            $insStmt->bind_param("sss", $full_name, $passenger_code, $passenger_type);
            if ($insStmt->execute()) {
                $passenger_id = $conn->insert_id;
            }
            $insStmt->close();
        }

        // Fallback insert if passenger_code column is not in older structure
        if (!$passenger_id) {
            $basicStmt = $conn->prepare("INSERT INTO passengers (full_name, type, space_occupied) VALUES (?, ?, 1)");
            if ($basicStmt) {
                $basicStmt->bind_param("ss", $full_name, $passenger_type);
                if ($basicStmt->execute()) {
                    $passenger_id = $conn->insert_id;
                }
                $basicStmt->close();
            }
        }
    }

    //--------------------------------------------------------------------------
    // INSERT INTO CURRENT `passenger_trips` TABLE
    //--------------------------------------------------------------------------
    $trip_id = null;

    $tripStmt = $conn->prepare("
        INSERT INTO passenger_trips
        (
            passenger_id,
            passenger_code,
            inspector_id,
            bus_no,
            trip_no,
            driver_id,
            boarding_point,
            destination,
            fare,
            payment_method,
            payment_status,
            cash_amount,
            gcash_amount,
            boarding_time,
            passenger_photo,
            identification_source,
            updated_by
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'Pending',
            ?,
            ?,
            NOW(),
            ?,
            'inspector_registration',
            ?
        )
    ");

    if ($tripStmt) {
        $tripStmt->bind_param(
            "isssssssdsddss",
            $passenger_id,
            $passenger_code,
            $inspector_id,
            $bus_no,
            $trip_no,
            $driver_id,
            $boarding_point,
            $destination,
            $fare,
            $payment_method,
            $cash_amount,
            $gcash_amount,
            $saved_photo_path,
            $updated_by
        );
        if ($tripStmt->execute()) {
            $trip_id = $conn->insert_id;
        }
        $tripStmt->close();
    }

    if (!$trip_id) {
        // Fallback trip insert with core columns
        $fbTrip = $conn->prepare("
            INSERT INTO passenger_trips
            (passenger_id, inspector_id, bus_no, trip_no, driver_id, boarding_point, destination, fare, payment_method, payment_status, cash_amount, gcash_amount, boarding_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, NOW())
        ");
        if ($fbTrip) {
            $fbTrip->bind_param("issssssdsdd", $passenger_id, $inspector_id, $bus_no, $trip_no, $driver_id, $boarding_point, $destination, $fare, $payment_method, $cash_amount, $gcash_amount);
            if ($fbTrip->execute()) {
                $trip_id = $conn->insert_id;
            }
            $fbTrip->close();
        }
    }

    $qrPayload = json_encode([
        "v" => 1,
        "app" => "rconnect-passenger",
        "passenger_code" => $passenger_code,
        "full_name" => $full_name,
        "passenger_type" => $passenger_type,
    ]);

    echo json_encode([
        "success"        => true,
        "passenger_id"   => $passenger_id,
        "passenger_code" => $passenger_code,
        "trip_id"        => $trip_id,
        "full_name"      => $full_name,
        "type"           => $passenger_type,
        "fare"           => $fare,
        "boarding_point" => $boarding_point,
        "destination"    => $destination,
        "photo_url"      => $saved_photo_path,
        "qr_payload"     => $qrPayload,
        "message"        => "Passenger registered and boarded successfully"
    ]);

    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "code"    => "SERVER_ERROR",
        "message" => $e->getMessage()
    ]);
}
