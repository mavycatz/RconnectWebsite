<?php

require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/sanitize.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/cors.php';

use Finance\Middleware\RateLimit;
use Finance\Middleware\Sanitize;
use Finance\Middleware\Csrf;
use Finance\Middleware\AuthMiddleware;

function validateFinanceRequest(string $endpointGroup = 'standard', bool $requireAuth = true, bool $requireCsrf = false): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    setCorsHeaders();

    if ($requireAuth) {
        AuthMiddleware::requireFinanceAccess();
    }

    if (!RateLimit::check($endpointGroup)) {
        $retryAfter = RateLimit::retryAfter();
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Rate limit exceeded. Try again in ' . $retryAfter . ' seconds.',
        ]);
        exit;
    }

    if ($requireCsrf && $_SERVER['REQUEST_METHOD'] !== 'GET') {
        Csrf::validateOrFail();
    }
}

function setRequestTimeout(int $seconds = 30): void
{
    set_time_limit($seconds);
}
