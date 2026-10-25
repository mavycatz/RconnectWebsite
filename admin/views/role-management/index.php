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
if (!in_array(strtolower((string)($user['role'] ?? '')), ['admin', 'super_admin'], true)) {
    header('Location: /admin/');
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT']);

$rmgRoleLabels = [
    'driver' => 'Driver',
    'inspector' => 'Inspector',
    'dispatcher' => 'Dispatcher',
    'finance_officer' => 'Finance Officer',
    'finance_manager' => 'Finance Manager',
    'admin' => 'Admin',
    'super_admin' => 'Super Admin',
];
$rmgInitialRole = strtolower(trim((string)($_GET['role'] ?? 'all')));
if ($rmgInitialRole !== 'all' && !isset($rmgRoleLabels[$rmgInitialRole])) {
    $rmgInitialRole = 'all';
}
$rmgOpenProfile = trim((string)($_GET['open_profile'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
    <title>Role Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/admin/assets/css/navbaradmin.css">
    <link rel="stylesheet" href="/public/login/custom-alert.css">
    <link rel="stylesheet" href="/admin/assets/css/role-management.css?v=<?= time() ?>">
    <style>
        html, body { background-color: #eceed9 !important; }

        /* Soft green ambience behind the main container, matching the theme */
        #mainContent {
            background:
                radial-gradient(900px 380px at 12% -8%, rgba(125, 164, 83, .20), rgba(125, 164, 83, 0) 60%),
                radial-gradient(900px 420px at 88% 0%, rgba(41, 87, 56, .10), rgba(41, 87, 56, 0) 55%),
                linear-gradient(180deg, #e9eeda 0%, #e1e8cd 100%);
        }
    </style>
</head>

<body>

<?php require ROOT_PATH . '/admin/assets/layout/navbaradmin.php'; ?>

<div class="main-content" id="mainContent">
    <div class="rmg-shell">
        <?php require ROOT_PATH . '/admin/views/role-management/sections/directory.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.RMG_CONFIG = <?= json_encode([
        'roles' => $rmgRoleLabels,
        'statuses' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'pending' => 'Pending',
            'rejected' => 'Rejected',
            'archived' => 'Archived',
        ],
        'initialRole' => $rmgInitialRole,
        'openProfile' => $rmgOpenProfile,
        'isSuper' => strtolower((string)($user['role'] ?? '')) === 'super_admin',
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<?php require __DIR__ . '/../layout/custom-alert.php'; ?>
<script src="/public/login/custom-alert.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/management-alert.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/role-management.js?v=<?= time() ?>"></script>

</body>
</html>
