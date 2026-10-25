<?php

function getAccessToken(): string
{
    return require __DIR__ . '/config/access_token.php';
}

require_once __DIR__ . '/../app/auth/Session.php';

Session::start();

if (!isset($_SESSION['user']['id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: /public/login/index.php');
    exit;
}

if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 900) {
    Session::destroy();
    header('Location: /public/login/index.php');
    exit;
}

$_SESSION['last_activity'] = time();
