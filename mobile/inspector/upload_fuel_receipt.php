<?php
/**
 * upload_fuel_receipt.php
 * Accepts a fuel receipt image (camera capture or gallery pick from the
 * Inspector app), validates it, stores it under backend/mobile/inspector/receipts/,
 * and returns the accessible relative URL for persistence on the fuel record.
 *
 * Usage (multipart/form-data):
 *   - "receipt" (file)  : the image file, OR
 *   - "receiptBase64"   : "data:image/...;base64,...."
 *   - "bus_id" (optional): used only to prefix the filename for traceability
 *
 * Response envelope:
 *   { success, receipt_url, message }
 */

header("Content-Type: application/json");
require_once __DIR__ . "/../config/database.php";

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit;
}

$allowedMimeTypes = [
    "image/jpeg",
    "image/jpg",
    "image/png",
    "image/heic",
    "image/heif",
    "image/webp",
    "image/gif",
    "image/bmp",
];

$uploadDir = __DIR__ . "/receipts/";

try {
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileContent = null;
    $ext = "jpg";

    // --- File upload branch ---
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $tmpPath = $_FILES['receipt']['tmp_name'];
        $fileSize = (int)$_FILES['receipt']['size'];
        if ($fileSize > 8 * 1024 * 1024) {
            echo json_encode(["success" => false, "message" => "Receipt image is too large. Please use an image under 8 MB."]);
            exit;
        }

        // Validate real mime type, not just extension.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMimeTypes, true)) {
            echo json_encode(["success" => false, "message" => "Unsupported image format. Please use a JPG or PNG photo."]);
            exit;
        }

        $fileContent = file_get_contents($tmpPath);
        $ext = $mime === "image/png" ? "png" : ($mime === "image/webp" ? "webp" : "jpg");
    }

    // --- Base64 branch (used by apps that encode before upload) ---
    if ($fileContent === null && isset($_POST['receiptBase64'])) {
        $base64 = trim((string)$_POST['receiptBase64']);
        if (strpos($base64, 'base64,') !== false) {
            $base64 = substr($base64, strpos($base64, 'base64,') + 7);
        }
        $base64 = str_replace(' ', '+', $base64);
        if ($base64 === '' || strlen($base64) % 4 !== 0) {
            echo json_encode(["success" => false, "message" => "The receipt image data is invalid. Please try again."]);
            exit;
        }
        $decoded = base64_decode($base64, true);
        if ($decoded === false || $decoded === '') {
            echo json_encode(["success" => false, "message" => "The receipt image could not be read. Please try again."]);
            exit;
        }
        if (strlen($decoded) > 8 * 1024 * 1024) {
            echo json_encode(["success" => false, "message" => "Receipt image is too large. Please use an image under 8 MB."]);
            exit;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_buffer($finfo, $decoded);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMimeTypes, true)) {
            echo json_encode(["success" => false, "message" => "Unsupported image format. Please use a JPG or PNG photo."]);
            exit;
        }

        $fileContent = $decoded;
        $ext = $mime === "image/png" ? "png" : ($mime === "image/webp" ? "webp" : "jpg");
    }

    if ($fileContent === null) {
        echo json_encode(["success" => false, "message" => "No receipt image was provided. Please attach or take a receipt photo."]);
        exit;
    }

    // Simple rate/path guard: timeout check to throttle spam uploads.
    $rateFile = __DIR__ . "/receipts/.last_upload";
    $now = time();
    if (is_file($rateFile)) {
        $last = (int)file_get_contents($rateFile);
        if (($now - $last) < 2) {
            echo json_encode(["success" => false, "message" => "Please wait a moment before uploading another receipt."]);
            exit;
        }
    }
    @file_put_contents($rateFile, (string)$now);

    $busId = preg_replace('/[^A-Za-z0-9\-_]/', '', trim($_POST['bus_id'] ?? ''));
    $prefix = $busId !== '' ? strtolower($busId) . "_" : "";
    $filename = $prefix . "receipt_" . date("Ymd_His") . "_" . bin2hex(random_bytes(4)) . "." . $ext;
    $destination = $uploadDir . $filename;

    if (!file_put_contents($destination, $fileContent)) {
        echo json_encode(["success" => false, "message" => "Could not save the receipt image. Please try again."]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "message" => "Receipt uploaded successfully",
        "receipt_url" => "receipts/" . $filename,
    ]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Could not upload the receipt. Please try again."]);
}
