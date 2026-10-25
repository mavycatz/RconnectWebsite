<?php
require_once __DIR__ . '/../app/init.php';

use App\Auth\Auth;

$user = Auth::user();
if (!in_array($user['role'] ?? '', ['finance_manager', 'finance_officer'])) {
    header('Location: /public/login/index.php');
    exit;
}

require __DIR__ . '/views/profile/index.php';
