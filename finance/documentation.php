<?php

$GLOBALS['finance_token'] = $_GET['token'] ?? '';

error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/controllers/DocumentationController.php';

use App\Auth\Auth;
use Finance\Controllers\DocumentationController;

if (!Auth::check()) {
    header('Location: /public/login/index.php');
    exit;
}

(new DocumentationController())->index();