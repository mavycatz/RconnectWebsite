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
    if (!$conn) throw new Exception("Database connection failed");

    $input = json_decode(file_get_contents("php://input"), true);

    $driverId = trim($input['driver_id'] ?? '');
    $token    = trim($input['token'] ?? '');
    $query    = trim($input['query'] ?? '');
    $mode     = strtolower(trim($input['mode'] ?? 'code'));

    require_once __DIR__ . '/lib/driver_auth.php';
    require_driver_auth($conn, $driverId, $token);

    if ($mode !== 'code' && $mode !== 'name') {
        echo json_encode(["success" => false, "message" => "mode must be 'code' or 'name'"]);
        exit;
    }

    if ($query === '') {
        echo json_encode(["success" => false, "message" => "query is required"]);
        exit;
    }

    $passengers = [];

    if ($mode === 'code') {
        // Preferred manual method: unique code. Exact match first; if nothing
        // matches, fall back to a prefix match so "AX1" finds "AX1…" passengers.
        if (strlen($query) < 2) {
            echo json_encode(["success" => true, "passengers" => [], "message" => "Enter at least 2 characters"]);
            exit;
        }

        $rows = [];

        $stmt = $conn->prepare(
            "SELECT passenger_code, full_name, type
             FROM passengers
             WHERE passenger_code IS NOT NULL AND LOWER(passenger_code) = LOWER(?)
             LIMIT 1"
        );
        if (!$stmt) throw new Exception("passengers query failed: " . $conn->error);
        $stmt->bind_param("s", $query);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $rows[] = $row;
        } else {
            $like = strtolower($query) . '%';
            $stmt = $conn->prepare(
                "SELECT passenger_code, full_name, type
                 FROM passengers
                 WHERE passenger_code IS NOT NULL AND LOWER(passenger_code) LIKE ?
                 ORDER BY passenger_code ASC
                 LIMIT 10"
            );
            if (!$stmt) throw new Exception("passengers query failed: " . $conn->error);
            $stmt->bind_param("s", $like);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();

            while ($r = $result->fetch_assoc()) {
                $rows[] = $r;
            }
        }

        foreach ($rows as $row) {
            $passengers[] = [
                "passenger_code" => $row['passenger_code'],
                "full_name"      => $row['full_name'],
                "passenger_type" => normalizePassengerType($row['type']),
            ];
        }
    } else {
        // Secondary backup: case-insensitive partial match on Full Name.
        if (strlen($query) < 3) {
            echo json_encode(["success" => true, "passengers" => [], "message" => "Enter at least 3 characters"]);
            exit;
        }
        $like = '%' . $query . '%';
        $stmt = $conn->prepare(
            "SELECT passenger_code, full_name, type
             FROM passengers
             WHERE full_name IS NOT NULL AND LOWER(full_name) LIKE LOWER(?)
             ORDER BY full_name ASC
             LIMIT 20"
        );
        if (!$stmt) throw new Exception("passengers query failed: " . $conn->error);
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        while ($row = $result->fetch_assoc()) {
            $passengers[] = [
                "passenger_code" => $row['passenger_code'] ?? '',
                "full_name"      => $row['full_name'],
                "passenger_type" => normalizePassengerType($row['type']),
            ];
        }
    }

    echo json_encode([
        "success"    => true,
        "mode"       => $mode,
        "passengers" => $passengers,
    ]);

    $conn->close();

} catch (Exception $e) {
    error_log("[search_passenger] " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "code" => "SERVER_ERROR", "message" => "Something went wrong. Please try again."]);
}

/**
 * Maps the raw passengers.type value to the backend-consistent passenger
 * type label. Walk-in/system rows ("passenger", "other", empty) map to
 * "Regular"; registered types are normalized to their display casing.
 */
function normalizePassengerType($type)
{
    $key = strtolower(trim((string)$type));
    switch ($key) {
        case 'pwd':    return 'PWD';
        case 'student': return 'Student';
        case 'senior':  return 'Senior';
        case 'special': return 'Special';
        case 'teacher': return 'Teacher';
        case 'nurse':   return 'Nurse';
        case 'regular': return 'Regular';
        default:        return 'Regular';
    }
}