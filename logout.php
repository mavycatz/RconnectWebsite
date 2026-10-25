<?php
require_once __DIR__ . '/app/auth/Session.php';
require_once __DIR__ . '/app/auth/Auth.php';

Session::start();
Auth::logout();

header('Location: /public/login/');
exit;
