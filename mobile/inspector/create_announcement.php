<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../config/database.php";

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo json_encode([
        "success" => false,
        "error" => "Database connection failed"
    ]);
    exit;
}

// Accept both multipart/form-data (used by the app, required for voice uploads)
// and raw JSON bodies.
$input = $_POST;
if (empty($input)) {
    $rawInput = file_get_contents("php://input");
    if (!empty($rawInput)) {
        $decoded = json_decode($rawInput, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $input = $decoded;
        }
    }
}

$type = $input['type'] ?? 'Inspector Report';
$title = trim($input['title'] ?? '');
$message = trim($input['message'] ?? '');
$bus_id = $input['bus_id'] ?? null;
$driver_name = $input['driver_name'] ?? null;
$reported_by = $input['reported_by'] ?? 'System';
$priority = $input['priority'] ?? 'Normal';
$status = 'Active';

if (empty($title) || empty($message)) {
    echo json_encode([
        "success" => false,
        "message" => "Missing required fields."
    ]);
    exit;
}

$stmt = $conn->prepare(
    "INSERT INTO announcements
     (type, title, message, bus_id, driver_name, reported_by, priority, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "error" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param(
    "ssssssss",
    $type,
    $title,
    $message,
    $bus_id,
    $driver_name,
    $reported_by,
    $priority,
    $status
);

if (!$stmt->execute()) {
    echo json_encode([
        "success" => false,
        "error" => "Execute failed: " . $stmt->error
    ]);
    exit;
}

$announcementId = $conn->insert_id;
$stmt->close();

$recordingUrl = null;

if (isset($_FILES['recording']) && $_FILES['recording']['error'] === UPLOAD_ERR_OK) {

    $allowedMimeTypes = [
        "audio/mp4",
        "audio/x-m4a",
        "audio/aac",
        "audio/mpeg",
        "audio/mp3",
        "audio/wav",
        "audio/x-wav",
        "audio/wave"
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['recording']['tmp_name']);
    finfo_close($finfo);

    if (in_array($mime, $allowedMimeTypes)) {

        $uploadDir = __DIR__ . "/recordings/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filename = "alert_" . $announcementId . ".m4a";
        $destination = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['recording']['tmp_name'], $destination)) {
            $recordingUrl = "recordings/" . $filename;

            $update = $conn->prepare(
                "UPDATE announcements SET recording_url = ? WHERE id = ?"
            );
            $update->bind_param("si", $recordingUrl, $announcementId);
            $update->execute();
            $update->close();
        }
    }
}

echo json_encode([
    "success" => true,
    "message" => "Announcement created successfully",
    "id" => $announcementId,
    "recording_url" => $recordingUrl
]);