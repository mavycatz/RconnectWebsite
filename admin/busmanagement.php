<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Bootstrap.php';

use App\Auth\Session;
use App\auth\Auth;
use App\Models\Terminal; // <-- import Terminal

Session::start();

// Redirect if not logged in
if (!Auth::check()) {
    header('Location: /public/login/index.php');
    exit;
}

// Redirect if user is not admin
if (!in_array(Auth::role(), ['admin', 'super_admin'])) {
    header('Location: /public/login/index.php'); // or any page for non-admins
    exit;
}

// Initialize Terminal model
$terminal = new Terminal();
$activeTerminals = $terminal->getActive();

// Pass to view
require_once __DIR__ . '/../app/Views/admin/busmanagement_view.php';



require_once __DIR__ . '/../app/Views/admin/busmanagement_view.php';
?>
