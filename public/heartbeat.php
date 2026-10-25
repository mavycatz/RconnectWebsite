<?php
require_once __DIR__ . '/../app/Core/Bootstrap.php';

use App\Auth\Session;

Session::start();

echo json_encode(['status' => 'alive']);