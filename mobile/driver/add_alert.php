<?php
header("Content-Type: application/json");
require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

$type = $_POST['type'] ?? '';
$title = $_POST['title'] ?? '';
$message = $_POST['message'] ?? '';
$reported_by = $_POST['reported_by'] ?? '';
$driver_name = $_POST['driver_name'] ?? '';
$driver_id = $_POST['driver_id'] ?? '';
$token = $_POST['token'] ?? '';

require_once __DIR__ . '/lib/driver_auth.php';
require_driver_auth($conn, $driver_id, $token);

if (empty($type) || empty($title) || empty($message) || empty($reported_by)) {
    echo json_encode([
        "success" => false,
        "message" => "Missing required fields."
    ]);
    exit;
}

$sql = "INSERT INTO announcements (type, title, message, reported_by, driver_name)
        VALUES (?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssss", $type, $title, $message, $reported_by, $driver_name);

if (!$stmt->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to save alert."
    ]);
    exit;
}

$alertId = $conn->insert_id;
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

    if (!in_array($mime, $allowedMimeTypes)) {
        echo json_encode([
            "success" => true,
            "id" => $alertId,
            "recording_url" => null
        ]);
        exit;
    }

    $uploadDir = __DIR__ . "/recordings/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename = "alert_" . $alertId . ".m4a";
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['recording']['tmp_name'], $destination)) {
        echo json_encode([
            "success" => true,
            "id" => $alertId,
            "recording_url" => null
        ]);
        exit;
    }

    $recordingUrl = "recordings/" . $filename;

    $update = $conn->prepare("UPDATE announcements SET recording_url = ? WHERE id = ?");
    $update->bind_param("si", $recordingUrl, $alertId);
    $update->execute();
    $update->close();
}

$stmt->close();

// Notify inspectors via push notification (optional — must never break the save response)
$pushHelperPaths = [
    $_SERVER['DOCUMENT_ROOT'] . '/mobile/inspector/push_helper.php',
    __DIR__ . '/inspector/push_helper.php',
    __DIR__ . '/push_helper.php'
];

$pushHelper = null;
foreach ($pushHelperPaths as $path) {
    if (file_exists($path)) {
        $pushHelper = $path;
        break;
    }
}

if ($pushHelper) {
    try {
        require_once $pushHelper;
        if (function_exists('sendPushToAllInspectors')) {
            sendPushToAllInspectors(
                $title,
                $message,
                ['type' => 'alert', 'alert_id' => (string)$alertId, 'recording_url' => $recordingUrl]
            );
        }
    } catch (Throwable $e) {
        // Push notification failure must not affect the saved announcement response.
    }
}

echo json_encode([
    "success" => true,
    "id" => $alertId,
    "recording_url" => $recordingUrl
]);
