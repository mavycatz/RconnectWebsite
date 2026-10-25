<?php

header("Content-Type: application/json");
require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

$data = json_decode(file_get_contents("php://input"), true);

$inspector_id = trim($data['inspector_id'] ?? '');
$bus_id = trim($data['bus_id'] ?? '');
$status = trim($data['status'] ?? '');

if (!$inspector_id || !$bus_id || !$status) {

    echo json_encode([
        "success" => false,
        "message" => "Missing parameters"
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| GET LATEST ACTIVE TRIP
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        route_name
    FROM bus_trips
    WHERE bus_id = ?
    AND status = 'Departed'
    ORDER BY id DESC
    LIMIT 1
");

$stmt->bind_param("s", $bus_id);
$stmt->execute();

$trip = $stmt->get_result()->fetch_assoc();

if (!$trip) {

    echo json_encode([
        "success" => false,
        "message" => "No active trip found"
    ]);

    exit;
}

$trip_id = (int)$trip['id'];

/*
|--------------------------------------------------------------------------
| CHECK LATEST ASSIGNMENT FOR THIS TRIP
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        status
    FROM inspector_assignments
    WHERE inspector_id = ?
    AND bus_id = ?
    AND trip_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$stmt->bind_param(
    "ssi",
    $inspector_id,
    $bus_id,
    $trip_id
);

$stmt->execute();

$assignment =
    $stmt->get_result()->fetch_assoc();

/*
|--------------------------------------------------------------------------
| CHECK-IN
|--------------------------------------------------------------------------
*/

if ($status === 'Checked-In') {

    if (
        !$assignment ||
        $assignment['status'] === 'Checked-Out'
    ) {

        $stmt = $conn->prepare("
            INSERT INTO inspector_assignments
            (
                inspector_id,
                bus_id,
                trip_id,
                status,
                assigned_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                'Checked-In',
                NOW()
            )
        ");

        $stmt->bind_param(
            "ssi",
            $inspector_id,
            $bus_id,
            $trip_id
        );

        $stmt->execute();

        echo json_encode([
            "success" => true,
            "action" => "checked_in",
            "assignment_id" => $conn->insert_id,
            "trip_id" => $trip_id,
            "status" => "Checked-In"
        ]);

        exit;
    }

    echo json_encode([
        "success" => true,
        "action" => "already_checked_in",
        "trip_id" => $trip_id,
        "status" => "Checked-In"
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| CHECK-OUT
|--------------------------------------------------------------------------
*/

if ($status === 'Checked-Out') {

    if (!$assignment) {

        echo json_encode([
            "success" => false,
            "message" => "No assignment found"
        ]);

        exit;
    }

    $stmt = $conn->prepare("
        UPDATE inspector_assignments
        SET
            status = 'Checked-Out'
        WHERE id = ?
    ");

    $stmt->bind_param(
        "i",
        $assignment['id']
    );

    $stmt->execute();

    echo json_encode([
        "success" => true,
        "action" => "checked_out",
        "trip_id" => $trip_id,
        "status" => "Checked-Out"
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| INVALID STATUS
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => false,
    "message" => "Invalid status value"
]);

$conn->close();

?>