<?php

declare(strict_types=1);

$validToken = require __DIR__ . '/../../config/access_token.php';

$token = $_GET['token'] ?? '';

if ($token !== $validToken) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}
