<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Bootstrap.php';
require_once __DIR__ . '/../services/Bus/BusQrService.php';

use Admin\Services\Bus\BusQrService;
use App\Auth\Session;
use App\Auth\Auth;
use App\Core\Database;

Session::start();
header('Content-Type: application/json');

if (!Auth::check() || !in_array(Auth::user()['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

try {

    $input = json_decode(file_get_contents('php://input'), true);

    $busId  = trim($input['bus_id'] ?? '');
    $qrCode = trim($input['qr_code'] ?? '');
    $qrData = trim($input['qr_data'] ?? '');
    $image  = $input['image'] ?? '';

    if (
        $busId === '' ||
        $qrCode === '' ||
        $qrData === '' ||
        $image === ''
    ) {
        throw new Exception('Missing required data.');
    }

    // Remove Base64 header
    $image = preg_replace('#^data:image/\w+;base64,#i', '', $image);
    $image = str_replace(' ', '+', $image);

    $binary = base64_decode($image);

    if ($binary === false) {
        throw new Exception('Invalid QR image.');
    }

    // Create uploads directory if it doesn't exist
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/qr/';

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new Exception('Unable to create QR upload directory.');
        }
    }

    // Save QR image
    $filePath = $uploadDir . $qrCode;

    if (file_put_contents($filePath, $binary) === false) {
        throw new Exception('Failed to save QR image.');
    }

    // Relative path stored in database
    $relativePath = '/uploads/qr/' . $qrCode;

    $db = Database::get();

    // The bus's stable QR credential owns the image path. It is created on
    // demand here only for legacy buses that predate automatic creation;
    // existing credentials are never replaced by saving an image.
    $credential = null;
    $origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $actorName = (string)($_SESSION['user']['username'] ?? '');
    try {
        $lookup = $db->prepare('SELECT id FROM buses WHERE bus_id = ? LIMIT 1');
        $lookup->execute([$busId]);
        $busRef = (int)($lookup->fetchColumn() ?: 0);
        if ($busRef > 0) {
            BusQrService::ensureSchema($db);
            $qrService = new BusQrService($db);
            $credential = $qrService->ensureForBus($busRef, $busId, $origin, $actorName);
            $qrService->markImage((string)$credential['credential_id'], $relativePath);
        }
    } catch (Throwable $e) {
        error_log('save_bus_qr credential link failed: ' . $e->getMessage());
    }

    $stmt = $db->prepare("
        UPDATE buses
        SET
            qr_code = :qr_code,
            qr_data = :qr_data,
            qr_status = :qr_status
        WHERE bus_id = :bus_id
    ");

    $stmt->execute([
        ':qr_code'   => $relativePath,
        ':qr_data'   => $qrData,
        ':qr_status' => 'Generated',
        ':bus_id'    => $busId
    ]);

    echo json_encode([
        'success'   => true,
        'message'   => 'QR generated and saved successfully.',
        'qr_code'   => $relativePath,
        'qr_data'   => $qrData,
        'qr_status' => 'Generated',
        'qr'        => $credential === null ? null : [
            'token' => $credential['token'] ?? '',
            'payload_url' => $credential['payload_url'] ?? '',
            'status' => $credential['status'] ?? 'active',
            'created_at' => $credential['created_at'] ?? '',
        ],
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

}
