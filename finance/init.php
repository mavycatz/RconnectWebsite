<?php
declare(strict_types=1);

define('APP_LOADED', true);

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/Core/Bootstrap.php';
require_once __DIR__ . '/auth/Session.php';
require_once __DIR__ . '/auth/Auth.php';

use App\Auth\Session;
use App\Auth\Auth;

Session::start();