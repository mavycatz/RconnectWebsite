<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once "../config/database.php";

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$idToken = $input['idToken'] ?? null;

if (!$idToken) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing idToken'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| GOOGLE WEB CLIENT ID
|--------------------------------------------------------------------------
| Replace with your actual Web Client ID from Google Cloud Console
|--------------------------------------------------------------------------
*/
$clientId = '453326175580-sebpe5pda3v9ifr53l1q3ihvi69nfvsm.apps.googleusercontent.com';

$verifyUrl =
    "https://oauth2.googleapis.com/tokeninfo?id_token="
    . urlencode($idToken);

$ch = curl_init($verifyUrl);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);

$httpCode =
    curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid Google token'
    ]);

    exit;
}

$payload =
    json_decode(
        $response,
        true
    );

if (
    !isset($payload['aud']) ||
    $payload['aud'] !== $clientId
) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Token audience mismatch'
    ]);

    exit;
}

$googleEmail =
    $payload['email'] ?? '';

$googleName =
    $payload['name'] ?? '';

$googleSub =
    $payload['sub'] ?? '';

try {

    $stmt =
        $conn->prepare("
            SELECT
                id,
                driver_id,
                name,
                email
            FROM drivers
            WHERE google_sub = ?
            LIMIT 1
        ");

    $stmt->execute([
        $googleSub
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if ($row) {

        $token =
            bin2hex(
                random_bytes(32)
            );

        $update =
            $conn->prepare("
                UPDATE drivers
                SET token = ?
                WHERE id = ?
            ");

        $update->execute([
            $token,
            $row['id']
        ]);

        $driver = $row;

    } else {

        $driverId =
            'DRV-' .
            strtoupper(
                substr(
                    preg_replace('/[^A-Za-z0-9]/', '', $googleSub),
                    0,
                    8
                )
            );

        $token =
            bin2hex(
                random_bytes(32)
            );

        $insert =
            $conn->prepare("
                INSERT INTO drivers
                (
                    driver_id,
                    name,
                    email,
                    google_sub,
                    token
                )
                VALUES
                (
                    ?, ?, ?, ?, ?
                )
            ");

        $insert->execute([
            $driverId,
            $googleName,
            $googleEmail,
            $googleSub,
            $token
        ]);

        $driver = [
            'id' => $conn->lastInsertId(),
            'driver_id' => $driverId,
            'name' => $googleName,
            'email' => $googleEmail
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'token' => $token,
            'driver' => $driver
        ]
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}