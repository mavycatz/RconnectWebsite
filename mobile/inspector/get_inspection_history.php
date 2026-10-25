<?php
/**
 * get_inspection_history.php
 * Fetches read-only inspection history and audit records for an inspector or specific inspection.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Manila');

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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

    $inspectionId = trim($_GET['inspection_id'] ?? '');
    $inspectorId = trim($_GET['inspector_id'] ?? '');
    $busNo = trim($_GET['bus_no'] ?? '');
    $tripNo = trim($_GET['trip_no'] ?? '');

    // Check if table exists
    $tblCheck = $conn->query("SHOW TABLES LIKE 'inspector_inspections'");
    if (!$tblCheck || $tblCheck->num_rows === 0) {
        echo json_encode([
            "success" => true,
            "inspections" => [],
            "message" => "No inspections recorded yet"
        ]);
        exit;
    }

    if (!empty($inspectionId)) {
        // Fetch single inspection with detailed passenger records
        $stmt = $conn->prepare("
            SELECT * FROM `inspector_inspections`
            WHERE `inspection_id` = ? LIMIT 1
        ");
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param("s", $inspectionId);
        $stmt->execute();
        $inspection = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$inspection) {
            echo json_encode(["success" => false, "message" => "Inspection not found"]);
            exit;
        }

        if (!empty($inspection['audit_summary'])) {
            $inspection['audit_summary'] = json_decode($inspection['audit_summary'], true);
        }

        // Fetch passenger items
        $itemStmt = $conn->prepare("
            SELECT * FROM `inspector_inspection_items`
            WHERE `inspection_id` = ?
            ORDER BY id ASC
        ");
        $items = [];
        if ($itemStmt) {
            $itemStmt->bind_param("s", $inspectionId);
            $itemStmt->execute();
            $itemRes = $itemStmt->get_result();
            while ($row = $itemRes->fetch_assoc()) {
                if (!empty($row['checklist_results'])) {
                    $row['checklist_results'] = json_decode($row['checklist_results'], true);
                }
                if (!empty($row['discrepancy_reasons'])) {
                    $row['discrepancy_reasons'] = json_decode($row['discrepancy_reasons'], true);
                }
                $items[] = $row;
            }
            $itemStmt->close();
        }

        $inspection['passenger_records'] = $items;

        echo json_encode([
            "success" => true,
            "inspection" => $inspection
        ]);
        exit;
    }

    // Fetch list of inspections
    $where = [];
    $types = "";
    $params = [];

    if (!empty($inspectorId)) {
        $where[] = "inspector_id = ?";
        $types .= "s";
        $params[] = $inspectorId;
    }
    if (!empty($busNo)) {
        $where[] = "bus_no = ?";
        $types .= "s";
        $params[] = $busNo;
    }
    if (!empty($tripNo)) {
        $where[] = "trip_no = ?";
        $types .= "s";
        $params[] = $tripNo;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    $sql = "SELECT id, inspection_id, bus_no, trip_no, route_name, inspector_id, inspector_name,
                   total_passengers, checked_count, validated_count, needs_review_count,
                   status, submitted_at, created_at, audit_summary
            FROM `inspector_inspections`
            $whereClause
            ORDER BY submitted_at DESC
            LIMIT 50";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }

    $list = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['audit_summary'])) {
                $row['audit_summary'] = json_decode($row['audit_summary'], true);
            }
            $list[] = $row;
        }
    }

    echo json_encode([
        "success" => true,
        "count" => count($list),
        "inspections" => $list
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
