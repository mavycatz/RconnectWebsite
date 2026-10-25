<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST allowed, received: ' . $_SERVER['REQUEST_METHOD']);
    }

    $debug = [
        'files_keys' => array_keys($_FILES),
        'post_keys' => array_keys($_POST),
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
        'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 0,
    ];

    if (!isset($_FILES['audio'])) {
        throw new Exception('No "audio" field in upload. Debug: ' . json_encode($debug));
    }

    if ($_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [0 => 'OK', 1 => 'INI_SIZE', 2 => 'FORM_SIZE', 3 => 'PARTIAL', 4 => 'NO_FILE', 5 => 'NO_TMP_DIR', 6 => 'CANT_WRITE', 7 => 'EXTENSION'];
        $errMsg = $uploadErrors[$_FILES['audio']['error']] ?? 'UNKNOWN_' . $_FILES['audio']['error'];
        throw new Exception('Upload error: ' . $errMsg);
    }

    $apiKey = getenv('DEEPGRAM_API_KEY');
    if (!$apiKey) {
        $configFile = __DIR__ . '/../config.php';
        if (file_exists($configFile)) { include $configFile; }
        if (defined('DEEPGRAM_API_KEY')) { $apiKey = DEEPGRAM_API_KEY; }
    }
    if (!$apiKey) {
        $apiKey = '0be85eb5148b412cef679d4591b4a14b8f00f31b';
    }
    if (!$apiKey) {
        throw new Exception('No API key found');
    }

    $audioFile = $_FILES['audio']['tmp_name'];
    $fileSize = filesize($audioFile);
    if ($fileSize === 0) { throw new Exception('Audio file is empty (0 bytes)'); }
    if ($fileSize > 10000000) { throw new Exception('Audio file too large: ' . $fileSize . ' bytes (max 10MB)'); }

    $ch = curl_init('https://api.deepgram.com/v1/listen?model=nova-2&language=en&smart_format=true');
    if ($ch === false) { throw new Exception('Failed to initialize cURL'); }

    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Token ' . $apiKey],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => file_get_contents($audioFile),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) { throw new Exception('cURL error: ' . ($curlError ?: 'unknown')); }
    if ($httpCode !== 200) { throw new Exception("Deepgram returned HTTP $httpCode: " . substr($response, 0, 500)); }

    $data = json_decode($response, true);
    if (!$data || !isset($data['results']['channels'][0]['alternatives'][0]['transcript'])) {
        throw new Exception('Unexpected response: ' . substr($response, 0, 300));
    }

    $transcript = $data['results']['channels'][0]['alternatives'][0]['transcript'];
    echo json_encode(['success' => true, 'transcript' => $transcript, 'file_size' => $fileSize]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage(), 'line' => $e->getLine()]);
}
