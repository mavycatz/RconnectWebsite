<?php
declare(strict_types = 1);

require_once __DIR__ . '/../../../app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;

Session::start();

if (!Auth::check()) {
    header('Location: /public/login/index.php');
    exit;
}

$user = Auth::user();
if (!in_array($user['role'], ['admin', 'super_admin'])) {
    header('Location: /admin/');
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
    <title>Reports Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/admin/assets/css/navbaradmin.css">
    <style>
        html, body { background: #eceed9 !important; }
        .admin-reports-page #mainContent {
            background:
                radial-gradient(900px 380px at 12% -8%, rgba(125,164,83,.20), transparent 60%),
                radial-gradient(900px 420px at 88% 0%, rgba(41,87,56,.10), transparent 55%),
                linear-gradient(180deg, #e9eeda, #e1e8cd);
        }
        .admin-reports-page .reports-shell { background:#fef8f3; border:1px solid #aaa8b1; border-radius:24px; padding:24px; box-shadow:0 16px 38px rgba(41,87,56,.12); min-width:0; }
        @media(max-width:575px) { .admin-reports-page .reports-shell { padding:14px; border-radius:16px; } }
    </style>
</head>

<body class="admin-reports-page">

<?php require ROOT_PATH . '/admin/assets/layout/navbaradmin.php'; ?>

<div class="main-content" id="mainContent">
    <div class="reports-shell">
        <?php require ROOT_PATH . '/admin/views/reports/sections/library.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php require ROOT_PATH . '/admin/views/reports/layout/footer.php'; ?>
