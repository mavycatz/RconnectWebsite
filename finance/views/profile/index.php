<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;
use App\Core\Database;
use PDO;

Session::start();

if (!Auth::check()) {
    header('Location: /public/login/index.php');
    exit;
}

$sessionUser = Auth::user();

if (!$sessionUser || !isset($sessionUser['username'])) {
    header('Location: /public/login/index.php');
    exit;
}

$db = Database::get();

$stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$sessionUser['username']]);
$profileUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profileUser) {
    header('Location: /public/login/index.php');
    exit;
}
?>

<?php include __DIR__ . '/../layout/navbarfinance.php'; ?>

<div class="main-content" id="mainContent">
<div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;">
<div class="container-fluid p-0">
        <?php require_once __DIR__ . '/../layout/_headernav.php'; ?>

<?php include __DIR__ . '/layout/header.php'; ?>
<?php if (!empty($_SESSION['success'])): ?>
    <div style="background:#d4edda;color:#155724;padding:10px;border-radius:8px;margin-bottom:15px;">
        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/layout/style.php'; ?>


<div class="container-fluid">

    <div class="row g-2 align-items-stretch">

        
        <div class="col-lg-4 d-flex">
            <div class="w-100">
                <?php include __DIR__ . '/sections/profile_card.php'; ?>
            </div>
        </div>

        
        <div class="col-lg-8 d-flex">
            <div class="w-100 d-flex flex-column" style="gap:15px;">
                <?php include __DIR__ . '/sections/account_status.php'; ?>
                <?php include __DIR__ . '/sections/edit_form.php'; ?>
            </div>
        </div>

    </div>

</div>

<?php include __DIR__ . '/layout/footer.php'; ?>

</div>
</div>