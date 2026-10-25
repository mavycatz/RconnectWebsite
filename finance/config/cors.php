<?php

$allowedOrigins = [];

$envOrigin = getenv('FINANCE_CORS_ORIGIN');
if ($envOrigin) {
    $allowedOrigins = array_map('trim', explode(',', $envOrigin));
}

if (empty($allowedOrigins)) {
    $allowedOrigins = [
        'http://localhost',
        'https://localhost',
        'http://127.0.0.1',
    ];
}

function setCorsHeaders(): void
{
    global $allowedOrigins;

    if (headers_sent()) {
        return;
    }

    if (!is_array($allowedOrigins)) {
        $allowedOrigins = ['http://localhost', 'https://localhost', 'http://127.0.0.1'];
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } elseif (in_array('*', $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: *');
    } else {
        header('Access-Control-Allow-Origin: ' . ($allowedOrigins[0] ?? ''));
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');
    header('Access-Control-Allow-Credentials: true');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
