<?php
if (!isset($_SESSION["passenger_id"])) {
    header("Location: /passenger/login");
    exit();
}

require_once $_SERVER["DOCUMENT_ROOT"] . "/passenger/controllers/ProfilePage/ProfileController.php";

$controller = new ProfileController();
$user = $controller->getProfile($_SESSION["passenger_id"]);

$name = $_SESSION["passenger_name"] ?? "Guest";

// Saved default payment method (set from Settings here or from the homepage
// "Yes, use it next time" confirmation).
$savedDefaultPaymentMethod = strtolower(trim((string) ($user["default_payment_method"] ?? "")));
$hasDefaultPaymentMethod = in_array($savedDefaultPaymentMethod, ["cash", "gcash", "hybrid"], true);

$defaultPaymentMethodLabels = [
    "cash" => ["home.cash", "Cash"],
    "gcash" => ["home.online_payment", "Online Payment"],
    "hybrid" => ["home.hybrid", "Online Payment + Cash"],
];
$defaultPaymentMethodLabel = $hasDefaultPaymentMethod
    ? passenger_t(
        $defaultPaymentMethodLabels[$savedDefaultPaymentMethod][0],
        $defaultPaymentMethodLabels[$savedDefaultPaymentMethod][1],
    )
    : passenger_t("profile.default_payment_method_none", "No payment method chosen");

// Short labels keep the settings dropdown row compact.
$defaultPaymentMethodShortLabels = [
    "cash" => passenger_t("home.cash", "Cash"),
    "gcash" => passenger_t("profile.payment_short_online", "Online"),
    "hybrid" => passenger_t("profile.payment_short_hybrid", "Hybrid"),
];
$defaultPaymentMethodShortLabel = $hasDefaultPaymentMethod
    ? $defaultPaymentMethodShortLabels[$savedDefaultPaymentMethod]
    : passenger_t("profile.default_payment_method_none_short", "Not set");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
<title><?= htmlspecialchars(passenger_t('profile.page_title', 'Profile'), ENT_QUOTES, 'UTF-8') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php include __DIR__ . "/../layout/csrf.php"; ?>
<script src="/passenger/public/js/profile-preview.js?v=20261016"></script>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
body{
    font-family:'Poppins', sans-serif;
    background:#fef8f3;
    margin-bottom:20px;
}

.header-section{
    position:relative;
    width:100%;
}

.header-card{
    position:absolute;
    top:130%;
    left:50%;
    transform:translate(-50%,-50%);
    width:95%;
    background:#fef8f3;
    border-radius:15px;
    text-align:center;
    box-shadow:0 4px 15px rgba(0,0,0,0.2);
    padding:25px 20px;
    z-index:3;
}

.info-card{
    position:absolute;
    top:165%;
    left:50%;
    transform:translate(-50%,-50%);
    width:55%;
    background:#fcf5dd;
    border:1px solid #c6d48c;
    border-radius:15px;
    padding:5px 10px;
    text-align:center;
    font-size:13px;
    font-weight:600;
    z-index:3;
}

.green-strip{
    position:absolute;
    top:160%;
    left:50%;
    transform:translateX(-50%);
    width:95%;
    background:#e3eabb;
    padding:30px 0;
    z-index:1;
}

.trip-wrapper{
    position:relative;
    z-index:5;
    width:95%;
    margin:120px auto 40px auto;
    background:#fef8f3;
    border:2px solid #c6d48c;
    border-radius:20px;
    padding:10px 0;
}

.trip-container{
    width:95%;
    margin:auto;
}

.trip-card{
    background:#e3ebbc;
    border-radius:20px;
    padding:40px 0 0 0;
    position:relative;
    border:1.5px solid #000;

}

.inner-card{
    background:#fef8f3;
    border-top-left-radius:30px;
    border-top-right-radius:30px;
    border-bottom-left-radius:18px;
    border-bottom-right-radius:18px;

    position:relative;
    padding:18px;
    padding-top:60px; 
    margin-top:40px;
}

.profile-pic{
    position:absolute;
    top:0;
    left:20px;
    transform:translateY(-50%);
    z-index:10;
}

.profile-pic img{
    width:100px;
    height:100px;
    border-radius:50%;
    object-fit:cover;

    border:4px solid #fef8f3;
    background:#fff;
}

.settings-btn{
    position:absolute;
    top:12px;
    right:12px;

    background:#e5e3be;
    border:2px solid #c2b636;
    border-radius:20px;

    padding:6px 10px;
    font-size:14px;
    cursor:pointer;

    display:flex;
    align-items:center;
    justify-content:center;
}

.settings-wrapper{
    position:absolute;
    top:12px;
    right:12px;
    z-index:10;
}

.settings-btn{
    background:#e5e3be;
    border:2px solid #c2b636;
    border-radius:20px;
    padding:4px 8px;
    font-size:13px;
    line-height:1.3;
    cursor:pointer;

    display:flex;
    align-items:center;
    gap:6px;

    color:#2e4836;
    font-weight:bold;
    white-space:nowrap;
    max-width:calc(100vw - 32px);
}

.settings-btn i{
    font-size:14px;
    flex:0 0 auto;
}

.settings-dropdown{
    display:none;
    position:absolute;
    top:45px;
    right:0;

    background:#fef8f3;
    border:1.5px solid #c2b636;
    border-radius:12px;

    width:max-content;
    min-width:190px;
    max-width:calc(100vw - 48px);
    padding:8px 0;

    box-shadow:0 4px 10px rgba(0,0,0,0.1);
}

.settings-dropdown div{
    padding:10px 14px;
    font-size:13px;
    line-height:1.35;
    cursor:pointer;

    display:flex;
    align-items:flex-start;
    gap:8px;
    white-space:normal;
    overflow-wrap:break-word;
}

.settings-dropdown i{
    font-size:15px;
    flex:0 0 auto;
    line-height:1.35;
}

.settings-dropdown div:hover{
    background:#f3f1d2;
}

.info-row{
    margin:8px 0;
}

.info-title{
    font-weight:600;
    color:#24513c;
}

.info-value{
    color:#333;
}

.status-pill{
    display:inline-block;
    padding:4px 12px;
    border-radius:10px;
    font-size:11px;
    font-weight:600;
    color:#fff;
    background:#47a64b;
}

.profile-info{
    margin-top:10px;
}

.info-item{
    display:flex;
    justify-content:space-between;
    align-items:center;

    padding:12px 0;
    border-bottom:1px solid #e5e0a4;
}

.info-left{
    font-size:13px;
    color:#6c757d;
}

.info-right{
    font-size:13px;
    font-weight:600;
    color:#2e4836;
    text-align:right;
}

.status-pill{
    display:inline-block;
    padding:4px 10px;
    border-radius:10px;
    font-size:11px;
    font-weight:600;
    color:#fff;
    background:#47a64b;
}
</style>
</head>

<body>

<?php include __DIR__ . "/../layout/header.php"; ?>
<link rel="stylesheet" href="/public/login/custom-alert.css">

<div class="header-section">
    <img src="/assets/images/passenger/header-banner.png" style="width:100%; object-fit:cover;">

    <div class="header-card">

<?php if (isset($_SESSION["profile_update_status"])): ?>
<script>
document.addEventListener("DOMContentLoaded", function(){

    <?php if ($_SESSION["profile_update_status"] === "success"): ?>
        showAlert('success', <?= json_encode(passenger_t('profile.updated_title', 'Profile Updated')) ?>, <?= json_encode(passenger_t('profile.updated_body', 'Your profile has been updated successfully.')) ?>);
    <?php else: ?>
        <?php
        $profileUpdateError = trim((string) ($_SESSION["profile_update_error"] ?? ''));
        ?>
        showAlert('error', <?= json_encode(passenger_t('profile.update_failed', 'Update Failed')) ?>, <?= json_encode($profileUpdateError !== '' ? $profileUpdateError : passenger_t('profile.try_again', 'Please try again')) ?>);
    <?php endif; ?>

});
</script>
<?php unset($_SESSION["profile_update_status"], $_SESSION["profile_update_error"]); ?>
<?php endif; ?>
        <h2 style="margin:0; font-weight:700; font-size:20px; color:#24513c;">
            <?= htmlspecialchars(passenger_t('profile.title', 'Profile Screen'), ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p style="margin-top:5px; font-size:12px; color:#6c757d;">
            <?= htmlspecialchars(passenger_t('profile.subtitle', 'You may see your details here'), ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>

    <div class="info-card"><?= htmlspecialchars(passenger_t('profile.about', 'About the User'), ENT_QUOTES, 'UTF-8') ?></div>
    <div class="green-strip"></div>
</div>

<div class="trip-wrapper">
<div class="trip-container">

<div class="trip-card">

<div class="inner-card">

    <?php if ($outstanding["total"] > 0): ?>
    <div style="background:#fde8e8;border-left:4px solid #e74c3c;border-radius:6px;padding:8px 12px;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:6px;">
            <i class="bi bi-exclamation-circle-fill" style="color:#e74c3c;font-size:.85rem;"></i>
            <span style="font-size:.75rem;color:#a93226;font-weight:600;">
                <?= htmlspecialchars(passenger_t('profile.unpaid_trips', ':count unpaid trip(s) — settle now', ['count' => (int) ($outstanding["count"] ?? 0)]), ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
        <a href="/passenger/trips" style="font-size:.7rem;color:#e74c3c;font-weight:600;text-decoration:underline;"><?= htmlspecialchars(passenger_t('profile.view_trips', 'View Trips'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
    <?php endif; ?>

<div class="settings-wrapper">

    <div class="settings-btn" onclick="toggleSettings()">
        <i class="bi bi-gear-fill"></i>
        <span><?= htmlspecialchars(passenger_t('profile.settings', 'Settings'), ENT_QUOTES, 'UTF-8') ?></span>
    </div>

<div class="settings-dropdown" id="settingsDropdown">

    <div onclick="openEditProfileModal()">
        <i class="bi bi-pencil-square"></i> <?= htmlspecialchars(passenger_t('profile.edit_info', 'Edit Information'), ENT_QUOTES, 'UTF-8') ?>
    </div>

    <div onclick="openForgotPassword()">
        <i class="bi bi-lock-fill"></i> <?= htmlspecialchars(passenger_t('profile.change_password', 'Change Password'), ENT_QUOTES, 'UTF-8') ?>
    </div>

    <div onclick="openPassengerQr()">
        <i class="bi bi-qr-code"></i> <?= htmlspecialchars(passenger_t('profile.my_qr', 'My Passenger QR'), ENT_QUOTES, 'UTF-8') ?>
    </div>

    <div onclick="openTrustedDeviceModal()">
        <i class="bi bi-shield-lock-fill"></i> <?= htmlspecialchars(passenger_t("trusted.title", "Trusted device"), ENT_QUOTES, "UTF-8") ?>
    </div>

    <div onclick="openLanguageModal()">
        <i class="bi bi-translate"></i> <?= htmlspecialchars(passenger_t("login.language", "Language"), ENT_QUOTES, "UTF-8") ?>
    </div>

    <div onclick="openPaymentMethodModal()" style="align-items:center;white-space:nowrap;">
        <i class="bi bi-credit-card-2-front-fill"></i>
        <span style="min-width:0;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars(passenger_t("home.payment_method", "Payment Method"), ENT_QUOTES, "UTF-8") ?></span>
        <span id="settingsPaymentMethodValue" style="margin-left:auto;flex:0 0 auto;font-size:10.5px;font-weight:700;color:#47613b;background:#eef4e6;border:1px solid #dce8cd;border-radius:999px;padding:2px 9px;white-space:nowrap;"><?= htmlspecialchars($defaultPaymentMethodShortLabel, ENT_QUOTES, "UTF-8") ?></span>
    </div>

    <div onclick="clearCachedAppData()">
        <i class="bi bi-eraser-fill"></i> <?= htmlspecialchars(passenger_t('profile.clear_cache', 'Clear cached app data'), ENT_QUOTES, 'UTF-8') ?>
    </div>

    <form method="POST" action="/passenger/logout" style="margin:0;">
        <?php if (function_exists("passenger_csrf_token")): ?>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(passenger_csrf_token(), ENT_QUOTES, "UTF-8") ?>">
        <?php endif; ?>
    <div onclick="logoutPassenger(this.closest('form'))">
        <i class="bi bi-box-arrow-right"></i> <?= htmlspecialchars(passenger_t('profile.logout', 'Logout'), ENT_QUOTES, 'UTF-8') ?>
    </div>
    </form>

</div>
</div>

    <div class="profile-pic">
        <img src="<?php echo !empty($user["id_image"])
            ? "/uploads/" . $user["id_image"]
            : "/assets/images/passenger/profile.png"; ?>">
    </div>

<div class="profile-info">

<div class="info-item">
    <div class="info-left"><?= htmlspecialchars(passenger_t('profile.name', 'Passenger Name'), ENT_QUOTES, 'UTF-8') ?></div>
    <div class="info-right">
        <?php echo htmlspecialchars($user["full_name"] ?? "N/A"); ?>
    </div>
</div>

    <div class="info-item">
        <div class="info-left"><?= htmlspecialchars(passenger_t('profile.code', 'Passenger Code'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="info-right">AX<?php echo $user["id"] ?? ""; ?></div>
    </div>

    <div class="info-item">
        <div class="info-left"><?= htmlspecialchars(passenger_t('profile.email', 'Email'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="info-right"><?php echo htmlspecialchars($user["email"] ?? ""); ?></div>
    </div>

    <div class="info-item">
        <div class="info-left"><?= htmlspecialchars(passenger_t('profile.phone', 'Phone'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="info-right"><?php echo htmlspecialchars($user["phone"] ?? ""); ?></div>
    </div>

    <div class="info-item" onclick="openPaymentMethodModal()" style="cursor:pointer;">
        <div class="info-left"><?= htmlspecialchars(passenger_t('profile.default_payment_method', 'Default Payment Method'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="info-right" style="display:flex;align-items:center;gap:6px;justify-content:flex-end;">
            <span id="infoPaymentMethodValue"><?= htmlspecialchars($defaultPaymentMethodLabel, ENT_QUOTES, 'UTF-8') ?></span>
            <i class="bi bi-chevron-right" style="font-size:11px;color:#96a394;"></i>
        </div>
    </div>

    <?php if ($outstanding["total"] > 0): ?>
    <div class="info-item">
        <div class="info-left"><?= htmlspecialchars(passenger_t('profile.balance_due', 'Balance Due'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="info-right">
            <span style="display:inline-flex;align-items:center;gap:4px;background:#fde8e8;color:#a93226;padding:2px 12px;border-radius:20px;font-size:.75rem;font-weight:600;">
                <i class="bi bi-exclamation-circle-fill" style="font-size:.7rem;"></i>
                ₱<?= number_format($outstanding["total"], 2) ?>
                <span style="font-weight:500;opacity:.8;">(<?= (int) ($outstanding["count"] ?? 0) ?> trip<?= ((int) ($outstanding["count"] ?? 0)) === 1 ? "" : "s" ?>)</span>
            </span>
        </div>
    </div>
    <?php endif; ?>

    <div class="info-item">
        <div class="info-left"><?= htmlspecialchars(passenger_t('profile.discount_type', 'Discount Type'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="info-right"><?php echo htmlspecialchars($user["discount_type"] ?? passenger_t('profile.none', 'None'), ENT_QUOTES, 'UTF-8'); ?></div>
    </div>

    <div class="info-item">
        <div class="info-left"><?= htmlspecialchars(passenger_t('profile.discount_status', 'Discount Status'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="info-right">
            <?php if (!empty($user["discount_status"])): ?>
            <?php $verificationStatus = strtolower(trim((string) $user['discount_status']));
            $verificationLabel = match ($verificationStatus) {
                'verified', 'approved' => 'Verified',
                'needs_review', 'needs review', 'pending' => 'Pending Review',
                'expired' => 'Expired — Pending Review',
                'rejected' => 'Rejected',
                default => ucfirst(str_replace('_', ' ', $verificationStatus)),
            }; ?>
            <span class="status-pill" style="background:<?= in_array($verificationStatus, ['verified', 'approved'], true)
                ? "#e8f0e0;color:#2e4836"
                : ($verificationStatus === 'rejected'
                    ? "#fbe8e8;color:#b34040"
                    : "#f9f4da;color:#8a7a3a") ?>;padding:2px 12px;border-radius:20px;font-size:.75rem;font-weight:600;"><?= htmlspecialchars(
    $verificationLabel,
) ?></span>
            <?php else: ?>
            <span class="status-pill"><?= htmlspecialchars(passenger_t('profile.not_applied', 'Not Applied'), ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="info-item">
        <div class="info-left"><?= htmlspecialchars(passenger_t('profile.location_status', 'Location Status'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="info-right">
            <span class="status-pill"><?= htmlspecialchars(passenger_t('profile.approved', 'Approved'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>

</div>
</div>

<div id="editProfileModalContainer"></div>
<?php include __DIR__ . "/../layout/navbar.php"; ?>
<?php include __DIR__ . "/../layout/otp-input.php"; ?>
<script>
const PROFILE_I18N = {
    enterEmail: <?= json_encode(passenger_t('profile.enter_email', 'Enter your email'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    sending: <?= json_encode(passenger_t('profile.sending', 'Sending...'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    sendCode: <?= json_encode(passenger_t('profile.send_code', 'Send Code'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    enterVerificationCode: <?= json_encode(passenger_t('profile.enter_verification_code', 'Enter the verification code'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    enterNewPassword: <?= json_encode(passenger_t('profile.enter_new_password', 'Enter new password'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    currentPasswordRequired: <?= json_encode(passenger_t('profile.current_password_required', 'Please enter your current password.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    requirementsTitle: <?= json_encode(passenger_t('profile.password_requirements', 'Password Requirements'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    requirementsBody: <?= json_encode(passenger_t('profile.password_requirements_body', 'Use 8-72 characters with at least one letter and one number.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    passwordsMatch: <?= json_encode(passenger_t('profile.passwords_match', 'Passwords match.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    passwordsNoMatchHint: <?= json_encode(passenger_t('profile.passwords_no_match_hint', 'Passwords do not match.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    updating: <?= json_encode(passenger_t('profile.updating', 'Updating...'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    verifyCode: <?= json_encode(passenger_t('profile.verify_code', 'Verify Code'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    verifying: <?= json_encode(passenger_t('profile.verifying', 'Verifying...'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    updatePassword: <?= json_encode(passenger_t('profile.update_password', 'Update Password'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    invalidCode: <?= json_encode(passenger_t('profile.invalid_code', 'Invalid code'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    requestFailed: <?= json_encode(passenger_t('profile.request_failed', 'Request failed'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    requestFailedTitle: <?= json_encode(passenger_t('profile.request_failed_title', 'Request Failed'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    missingFields: <?= json_encode(passenger_t('profile.missing_fields', 'Missing Fields'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    fillAllFields: <?= json_encode(passenger_t('profile.fill_all_fields', 'Please fill in all fields'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    mismatch: <?= json_encode(passenger_t('profile.mismatch', 'Mismatch'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    passwordsNoMatch: <?= json_encode(passenger_t('profile.passwords_no_match', 'Passwords do not match'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    success: <?= json_encode(passenger_t('profile.success', 'Success'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    passwordUpdated: <?= json_encode(passenger_t('profile.password_updated', 'Password updated successfully'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    somethingWrong: <?= json_encode(passenger_t('profile.something_wrong', 'Something went wrong'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    tryAgain: <?= json_encode(passenger_t('profile.try_again', 'Please try again'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    updatedTitle: <?= json_encode(passenger_t('profile.updated_title', 'Profile Updated'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    updatedBody: <?= json_encode(passenger_t('profile.updated_body', 'Your profile has been updated successfully.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    updateFailedTitle: <?= json_encode(passenger_t('profile.update_failed', 'Update Failed'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    clearCacheTitle: <?= json_encode(passenger_t('profile.clear_cache_title', 'Clear cached app data'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    clearCacheBody: <?= json_encode(passenger_t('profile.clear_cache_body', 'This removes locally cached trip and payment information on this device. Your account, trip history, and payment records are kept.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    clearCacheConfirm: <?= json_encode(passenger_t('profile.clear_cache_confirm', 'Clear data'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};

function toggleSettings(){
    const dropdown = document.getElementById("settingsDropdown");
    dropdown.style.display =
        dropdown.style.display === "block" ? "none" : "block";
}

/* Clear cached app data: removes passenger-scoped local keys through the
   shared PassengerStorage registry and clears this passenger's server-side
   trip state. Server records (account, trips, payments) are preserved. */
function clearCachedAppData(){
    const run = function () {
        try {
            if (window.PassengerStorage && typeof window.PassengerStorage.reset === 'function') {
                window.PassengerStorage.reset();
            }
        } catch (e) {}

        fetch('/passenger/trip/clear-state', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: '{}'
        }).catch(function () {}).then(function () {
            window.location.reload();
        });
    };

    if (typeof window.showConfirm === 'function') {
        window.showConfirm(
            PROFILE_I18N.clearCacheTitle,
            PROFILE_I18N.clearCacheBody,
            run,
            null,
            PROFILE_I18N.clearCacheConfirm,
            null
        );
    } else {
        run();
    }
}
/* end clearCachedAppData */

/* Logout: clear every passenger-scoped local key through the shared reset
   routine before posting, so nothing of this passenger survives the sign-out
   on this device. Server side (logout route) revokes this browser's session
   and trusted-device token. */
function logoutPassenger(formEl){
    try {
        if (window.PassengerStorage && typeof window.PassengerStorage.reset === 'function') {
            window.PassengerStorage.reset();
        }
    } catch (e) {}

    if (formEl && typeof formEl.submit === 'function') {
        formEl.submit();
    }
}
/* end logoutPassenger */

document.addEventListener("click", function(e){
    const wrapper = document.querySelector(".settings-wrapper");
    if(!wrapper.contains(e.target)){
        document.getElementById("settingsDropdown").style.display = "none";
    }
});

function openEditProfileModal() {
    fetch('/passenger/profile/edit-modal')
    .then(res => res.text())
    .then(data => {
        document.getElementById('editProfileModalContainer').innerHTML = data;
        document.body.classList.add("modal-open");
    });
}

function closeEditProfileModal() {
    document.body.classList.remove("modal-open");
    document.getElementById('editProfileModalContainer').innerHTML = '';
}

function openTrustedDeviceModal(){
    fetch('/passenger/profile/trusted-device-modal')
        .then(res => res.text())
        .then(data => {
            document.getElementById('editProfileModalContainer').innerHTML = data;
            document.body.classList.add("modal-open");
            initTrustedDeviceModal();
        });
}

function initTrustedDeviceModal(){
    const modal = document.getElementById('trustedDeviceModal');
    if(!modal) return;

    const storedSelector = modal.getAttribute('data-trusted-selector') || '';
    const deviceValue = document.getElementById('tdDeviceValue');
    const status = document.getElementById('tdStatus');
    if(!storedSelector || !deviceValue || !status) return;

    let currentSelector = '';
    try {
        const token = localStorage.getItem('remember_passenger') || '';
        currentSelector = token.split(':')[0] || '';
    } catch(e) {}

    const isCurrent = currentSelector !== '' && currentSelector === storedSelector;
    const label = deviceValue.getAttribute('data-label') || deviceValue.textContent || '';
    const thisLabel = modal.getAttribute('data-this-label') || 'This device';
    const otherLabel = modal.getAttribute('data-other-label') || 'Other device';
    const thisTitle = modal.getAttribute('data-this-title') || '';
    const thisBody = modal.getAttribute('data-this-body') || '';
    const otherTitle = modal.getAttribute('data-other-title') || '';
    const otherBody = modal.getAttribute('data-other-body') || '';

    status.className = 'td-status ' + (isCurrent ? 'td-status-ok' : 'td-status-warn');
    status.innerHTML = '<i class="bi ' + (isCurrent ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill') + '"></i>'
        + '<div><strong>' + (isCurrent ? thisTitle : otherTitle) + '</strong>'
        + '<div>' + (isCurrent ? thisBody : otherBody) + '</div></div>';
    deviceValue.textContent = (isCurrent ? thisLabel : otherLabel) + ' — ' + label;
}

function closeTrustedDeviceModal(e){
    if(e && e.target !== e.currentTarget) return;
    const modal = document.getElementById('trustedDeviceModal');
    if(modal) modal.remove();
    document.body.classList.remove('modal-open');
}

function openLanguageModal(){
    fetch('/passenger/profile/language-modal')
        .then(res => res.text())
        .then(data => {
            document.getElementById('editProfileModalContainer').innerHTML = data;
            document.body.classList.add("modal-open");
        });
}

function closeLanguageModal(e){
    if(e && e.target !== e.currentTarget) return;
    const modal = document.getElementById('languageModal');
    if(modal) modal.remove();
    document.body.classList.remove('modal-open');
}

window.passengerChangeLanguage = function(lang){
    try { localStorage.setItem('passenger_lang', lang); } catch(e) {}
    const url = new URL(window.location.href);
    url.searchParams.set('lang', lang);
    window.location.href = url.toString();
};

const PROFILE_PAYMENT_METHODS = {
    current: <?= json_encode($hasDefaultPaymentMethod ? $savedDefaultPaymentMethod : '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    labels: {
        cash: <?= json_encode(passenger_t('home.cash', 'Cash'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        gcash: <?= json_encode(passenger_t('home.online_payment', 'Online Payment'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        hybrid: <?= json_encode(passenger_t('home.hybrid', 'Online Payment + Cash'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    },
    shortLabels: {
        cash: <?= json_encode(passenger_t('home.cash', 'Cash'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        gcash: <?= json_encode(passenger_t('profile.payment_short_online', 'Online'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        hybrid: <?= json_encode(passenger_t('profile.payment_short_hybrid', 'Hybrid'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    },
    noneLabel: <?= json_encode(passenger_t('profile.default_payment_method_none', 'No payment method chosen'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    noneShortLabel: <?= json_encode(passenger_t('profile.default_payment_method_none_short', 'Not set'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    saveLabel: <?= json_encode(passenger_t('profile.save', 'Save'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    savingLabel: <?= json_encode(passenger_t('profile.saving', 'Saving...'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    savedTitle: <?= json_encode(passenger_t('profile.default_payment_method_saved_title', 'Payment Method Updated'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    savedBody: <?= json_encode(passenger_t('profile.default_payment_method_saved_body', 'Your default payment method was updated.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    failedTitle: <?= json_encode(passenger_t('profile.default_payment_method_failed_title', 'Update Failed'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    failedBody: <?= json_encode(passenger_t('profile.default_payment_method_failed_body', 'We could not update your default payment method. Please try again.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};

let selectedDefaultPaymentMethod = '';

function updateDefaultPaymentMethodModalUI(){
    document.querySelectorAll('#paymentMethodModal .pm-option').forEach(function(btn){
        btn.classList.toggle('pm-active', btn.dataset.method === selectedDefaultPaymentMethod);
    });

    const saveBtn = document.getElementById('paymentMethodSaveBtn');
    if(saveBtn){
        saveBtn.disabled = !selectedDefaultPaymentMethod;
    }
}

function openPaymentMethodModal(){
    selectedDefaultPaymentMethod = PROFILE_PAYMENT_METHODS.current || '';
    updateDefaultPaymentMethodModalUI();

    const modal = document.getElementById('paymentMethodModal');
    if(modal){ modal.style.display = 'flex'; }
}

function closePaymentMethodModal(e){
    if(e && e.target !== e.currentTarget) return;

    const modal = document.getElementById('paymentMethodModal');
    if(modal){ modal.style.display = 'none'; }
}

function selectDefaultPaymentMethod(method){
    selectedDefaultPaymentMethod = method;
    updateDefaultPaymentMethodModalUI();
}

function applyDefaultPaymentMethodLabel(method){
    const label = PROFILE_PAYMENT_METHODS.labels[method] || PROFILE_PAYMENT_METHODS.noneLabel;
    const shortLabel = PROFILE_PAYMENT_METHODS.shortLabels[method] || PROFILE_PAYMENT_METHODS.noneShortLabel;

    const infoEl = document.getElementById('infoPaymentMethodValue');
    if(infoEl){ infoEl.textContent = label; }

    const settingsEl = document.getElementById('settingsPaymentMethodValue');
    if(settingsEl){ settingsEl.textContent = shortLabel; }
}

async function saveDefaultPaymentMethod(){
    if(!selectedDefaultPaymentMethod){ return; }

    const saveBtn = document.getElementById('paymentMethodSaveBtn');
    const method = selectedDefaultPaymentMethod;

    if(saveBtn){
        saveBtn.disabled = true;
        saveBtn.textContent = PROFILE_PAYMENT_METHODS.savingLabel;
    }

    try{
        const res = await fetch('/passenger/profile/default-payment-method', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ payment_method: method })
        });
        const data = await res.json();

        if(data && data.success){
            PROFILE_PAYMENT_METHODS.current = method;
            applyDefaultPaymentMethodLabel(method);
            closePaymentMethodModal();
            showAlert('success', PROFILE_PAYMENT_METHODS.savedTitle, PROFILE_PAYMENT_METHODS.savedBody);
            return;
        }

        var errMsg = (data && data.message) ? data.message : PROFILE_PAYMENT_METHODS.failedBody;
        if (window.PassengerSession && typeof window.PassengerSession.sanitizeErrorMessage === 'function') {
            errMsg = window.PassengerSession.sanitizeErrorMessage(errMsg, PROFILE_PAYMENT_METHODS.failedBody);
        }
        showAlert(
            'error',
            PROFILE_PAYMENT_METHODS.failedTitle,
            errMsg
        );
    }catch(err){
        showAlert('error', PROFILE_PAYMENT_METHODS.failedTitle, PROFILE_PAYMENT_METHODS.failedBody);
    }finally{
        if(saveBtn){
            saveBtn.disabled = false;
            saveBtn.textContent = PROFILE_PAYMENT_METHODS.saveLabel;
        }
    }
}

// Only the most recently requested modal fragment may be inserted. Without
// this, a slow earlier response (double tap / close + reopen while loading)
// can append a second #forgotModal: the user types into the visible one while
// getElementById('email') reads the empty first one, leaving the flow stuck
// on Email. Every helper below also works on the live (last) modal.
let forgotModalRequestSeq = 0;

function forgotModalRoot(){
    const modals = document.querySelectorAll('#forgotModal');
    return modals.length ? modals[modals.length - 1] : document;
}

// POST that always resolves with {status, ok, data, text} so unexpected
// (non-JSON) responses surface with their HTTP status instead of silently
// leaving the modal on the same step.
function fpPost(url, body){
    return fetch(url, {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: body
    }).then(function(res){
        return res.text().then(function(text){
            var data = null;
            try {
                data = JSON.parse(text);
            } catch(e) {
                data = null;
            }
            return { status: res.status, ok: res.ok, data: data, text: text };
        });
    });
}

function openForgotPassword(){

    const existing = document.getElementById('forgotModal');
    if(existing) existing.remove();

    const requestId = ++forgotModalRequestSeq;

    // Cache-busted URL: a previously cached fragment (old design) can never be
    // served once the page script has been updated.
    fetch('/passenger/forgot-password/modal?_fp=' + Date.now(), { cache: 'no-store' })
        .then(res => res.text())
        .then(html => {
            if(requestId !== forgotModalRequestSeq) return;

            const stale = document.getElementById('forgotModal');
            if(stale) stale.remove();

            document.body.insertAdjacentHTML('beforeend', html);

            if(window.PassengerOtp){
                PassengerOtp.init({
                    ids: ['modalOtp1', 'modalOtp2', 'modalOtp3', 'modalOtp4', 'modalOtp5', 'modalOtp6'],
                    combined: 'modalOtpCombined',
                    autofocus: false
                });
            }

            initResetPasswordValidation();
        })
        .catch(err => {
            console.error('Unable to load the reset-password modal:', err);
        });
}
document.body.classList.add('modal-open');

function closeForgotModal(e){
    if(e && e.target !== e.currentTarget) return;

    // Invalidate any in-flight open so it cannot re-insert the modal.
    forgotModalRequestSeq++;

    const modal = document.getElementById('forgotModal');
    if(modal) modal.remove();

    document.body.classList.remove('modal-open');
}

function nextStep(current, next, text){
    const root = forgotModalRoot();

    const currentEl = root.querySelector('#' + current);
    const nextEl = root.querySelector('#' + next);
    if(currentEl) currentEl.classList.add('hidden');
    if(nextEl) nextEl.classList.remove('hidden');

    const subtitle = root.querySelector('#stepText');
    if(subtitle) subtitle.textContent = text;

    const target = parseInt(String(next).replace('step',''), 10) || 1;
    root.querySelectorAll('#fpSteps .fp-step').forEach(function(item){
        const step = parseInt(item.getAttribute('data-step'), 10);
        item.classList.toggle('active', step === target);
        item.classList.toggle('done', step < target);
    });
}

function sendCode(){
    const root = forgotModalRoot();
    const emailInput = root.querySelector('#email');
    const email = emailInput ? emailInput.value.trim() : '';
    const btn = root.querySelector('#btnSend');

    if(!email){
        showAlert('warning', PROFILE_I18N.missingFields, PROFILE_I18N.enterEmail);
        return;
    }

    if(btn && btn.disabled) return;

    if(btn){
        btn.disabled = true;
        btn.innerText = PROFILE_I18N.sending;
    }

    fpPost('/passenger/forgot-password/send', 'email=' + encodeURIComponent(email))
    .then(reply => {
        const data = reply.data;

        if(!data){
            console.error('Unexpected OTP send response', reply.status, reply.text);
            showAlert('error', PROFILE_I18N.requestFailedTitle, PROFILE_I18N.requestFailed + ' (HTTP ' + reply.status + '). Please refresh the page and try again.');
            return;
        }

        if(data.status === 'success'){
            nextStep('step1','step2', PROFILE_I18N.enterVerificationCode);
            setTimeout(function(){
                const first = root.querySelector('#modalOtp1');
                if(first) first.focus();
            }, 100);
        }else{
            var msg = (data && data.message) ? data.message : PROFILE_I18N.requestFailed;
            if (window.PassengerSession && typeof window.PassengerSession.sanitizeErrorMessage === 'function') {
                msg = window.PassengerSession.sanitizeErrorMessage(msg, PROFILE_I18N.requestFailed);
            }
            showAlert('error', PROFILE_I18N.requestFailedTitle, msg);
        }
    })
    .catch(err=>{
        console.error(err);
        showAlert('error', PROFILE_I18N.requestFailedTitle, PROFILE_I18N.requestFailed);
    })
    .finally(()=>{
        if(btn){
            btn.disabled = false;
            btn.innerText = PROFILE_I18N.sendCode;
        }
    });
}

function verifyCode(){
    const root = forgotModalRoot();
    const btn = root.querySelector('#btnVerifyOtp');

    let code = '';
    for(let i = 1; i <= 6; i++){
        const digit = root.querySelector('#modalOtp' + i);
        code += digit ? (digit.value || '') : '';
    }

    if(code.length !== 6){
        showAlert('warning', PROFILE_I18N.missingFields, PROFILE_I18N.invalidCode);
        return;
    }

    if(btn && btn.disabled) return;

    if(btn){
        btn.disabled = true;
        btn.innerText = PROFILE_I18N.verifying;
    }

    fpPost('/passenger/forgot-password/verify', 'code=' + encodeURIComponent(code))
    .then(reply => {
        const data = reply.data;

        if(!data){
            console.error('Unexpected OTP verify response', reply.status, reply.text);
            showAlert('error', PROFILE_I18N.requestFailedTitle, PROFILE_I18N.requestFailed + ' (HTTP ' + reply.status + '). Please refresh the page and try again.');
            return;
        }

        if(data.status === 'success'){
            nextStep('step2','step3', PROFILE_I18N.enterNewPassword);
            const cp = root.querySelector('#currentPass');
            if(cp) cp.focus();
        }else{
            var msg = (data && data.message) ? data.message : PROFILE_I18N.invalidCode;
            if (window.PassengerSession && typeof window.PassengerSession.sanitizeErrorMessage === 'function') {
                msg = window.PassengerSession.sanitizeErrorMessage(msg, PROFILE_I18N.invalidCode);
            }
            showAlert('error', PROFILE_I18N.requestFailedTitle, msg);
        }
    })
    .catch(function(err){
        console.error(err);
        showAlert('error', PROFILE_I18N.requestFailedTitle, PROFILE_I18N.invalidCode);
    })
    .finally(function(){
        if(btn){
            btn.disabled = false;
            btn.innerText = PROFILE_I18N.verifyCode;
        }
    });
}

function togglePasswordVisibility(inputId, btn){
    const input = (btn && btn.closest('.pw-field') ? btn.closest('.pw-field').querySelector('input') : null)
        || document.getElementById(inputId);
    if(!input) return;

    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';

    if(btn){
        const icon = btn.querySelector('i');
        if(icon){
            icon.classList.toggle('bi-eye', show);
            icon.classList.toggle('bi-eye-slash', !show);
        }
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    }
}

function initResetPasswordValidation(){
    const root = forgotModalRoot();
    const pass = root.querySelector('#pass');
    const confirm = root.querySelector('#confirm');
    if(!pass || !confirm) return;

    const matchEl = root.querySelector('#pwMatch');

    function updateRequirements(){
        const value = pass.value || '';
        const checks = {
            length: value.length >= 8,
            max: value.length > 0 && value.length <= 72,
            letter: /[A-Za-z]/.test(value),
            number: /[0-9]/.test(value)
        };

        root.querySelectorAll('#pwReqs li').forEach(function(item){
            const met = !!checks[item.getAttribute('data-req')];
            item.classList.toggle('met', met);
            item.classList.toggle('unmet', !met);
        });
    }

    function updateMatch(){
        if(!matchEl) return;

        const value = pass.value || '';
        const confirmation = confirm.value || '';

        if(!confirmation){
            matchEl.textContent = '';
            matchEl.className = 'pw-match';
        }else if(value && confirmation === value){
            matchEl.textContent = PROFILE_I18N.passwordsMatch;
            matchEl.className = 'pw-match ok';
        }else{
            matchEl.textContent = PROFILE_I18N.passwordsNoMatchHint;
            matchEl.className = 'pw-match bad';
        }
    }

    pass.addEventListener('input', function(){
        updateRequirements();
        updateMatch();
    });

    confirm.addEventListener('input', updateMatch);

    updateRequirements();
    updateMatch();
}

function resetPassword(){
    const root = forgotModalRoot();
    const currentPassInput = root.querySelector('#currentPass');
    const passInput = root.querySelector('#pass');
    const confirmInput = root.querySelector('#confirm');
    const btn = root.querySelector('#btnUpdatePassword');

    const currentPass = currentPassInput ? currentPassInput.value : '';
    const pass = passInput ? passInput.value : '';
    const confirm = confirmInput ? confirmInput.value : '';

    if(!currentPass){
        showAlert('warning', PROFILE_I18N.missingFields, PROFILE_I18N.currentPasswordRequired);
        return;
    }

    if(!pass || !confirm){
        showAlert('warning', PROFILE_I18N.missingFields, PROFILE_I18N.fillAllFields);
        return;
    }

    if(pass.length < 8 || pass.length > 72 || !/[A-Za-z]/.test(pass) || !/[0-9]/.test(pass)){
        showAlert('warning', PROFILE_I18N.requirementsTitle, PROFILE_I18N.requirementsBody);
        return;
    }

    if(pass !== confirm){
        showAlert('error', PROFILE_I18N.mismatch, PROFILE_I18N.passwordsNoMatch);
        return;
    }

    if(btn){
        btn.disabled = true;
        btn.textContent = PROFILE_I18N.updating;
    }

    fpPost('/passenger/forgot-password/update', 'current_password='+encodeURIComponent(currentPass)+'&password='+encodeURIComponent(pass)+'&confirm='+encodeURIComponent(confirm))
    .then(reply => {
        const data = reply.data;

        if(!data){
            console.error('Unexpected password update response', reply.status, reply.text);
            showAlert('error', PROFILE_I18N.requestFailedTitle, PROFILE_I18N.requestFailed + ' (HTTP ' + reply.status + '). Please refresh the page and try again.');
            return;
        }

        if(data.status === 'success'){

            closeForgotModal();

            showAlert('success', PROFILE_I18N.success, PROFILE_I18N.passwordUpdated, function(){
                window.location.href = "/passenger/login";
            });

        }else{
            var msg = (data && data.message) ? data.message : PROFILE_I18N.somethingWrong;
            if (window.PassengerSession && typeof window.PassengerSession.sanitizeErrorMessage === 'function') {
                msg = window.PassengerSession.sanitizeErrorMessage(msg, PROFILE_I18N.somethingWrong);
            }
            showAlert('error', PROFILE_I18N.requestFailedTitle, msg);

        }
    })
    .catch(function(){

        showAlert('error', PROFILE_I18N.requestFailedTitle, PROFILE_I18N.tryAgain);
    })
    .finally(function(){
        if(btn){
            btn.disabled = false;
            btn.textContent = PROFILE_I18N.updatePassword;
        }
    });
}
function confirmCloseModal(){

    showConfirm('Discard changes?', 'Unsaved changes will not be saved.', function(){
        document.getElementById("editProfileModalContainer").innerHTML = '';
    });

}
</script>

<?php
$passengerQrCardHtml =
    '<div style="text-align:center;color:#7b8a74;font-family:\'Poppins\',sans-serif;font-size:12px;padding:10px 0;">'
    . htmlspecialchars(passenger_t('qr.unavailable', 'Passenger QR is not available yet.'), ENT_QUOTES, 'UTF-8')
    . '</div>';
$passenger = $user;
$passengerQrContainerId = "passengerQrProfileCanvas";
if (is_file(__DIR__ . "/../PassengerQr/passenger_qr_card.php")) {
    ob_start();
    include __DIR__ . "/../PassengerQr/passenger_qr_card.php";
    $passengerQrCardHtml = ob_get_clean();
}
$passengerQrProfileType = "Regular";
$passengerQrProfileCode = !empty($user["passenger_code"]) ? $user["passenger_code"] : "";
if (isset($passengerQrIdentity)) {
    $passengerQrProfileType = $passengerQrIdentity["passenger_type"] ?? $passengerQrProfileType;
    $passengerQrProfileCode = $passengerQrIdentity["passenger_code"] ?? $passengerQrProfileCode;
}
?>
<div id="passengerQrOverlay" style="display:none" onclick="if(event.target==this)this.style.display='none'">
<div id="passengerQrBox">
    <div id="passengerQrHeader">
        <div style="width:36px;height:36px;border-radius:9px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-qr-code" style="font-size:17px;color:#fff;"></i>
        </div>
        <div style="text-align:left;">
            <div style="font-size:16px;font-weight:700;font-family:'Poppins',sans-serif;"><?= htmlspecialchars(passenger_t('profile.my_qr', 'My Passenger QR'), ENT_QUOTES, 'UTF-8') ?></div>
            <div style="font-size:11px;opacity:.85;font-family:'Poppins',sans-serif;"><?= htmlspecialchars(passenger_t('qr.subtitle', 'Your offline passenger identification'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <button id="passengerQrClose" onclick="document.getElementById('passengerQrOverlay').style.display='none'">&times;</button>
    </div>
    <div id="passengerQrBody">
        <div style="background:#fbfcf9;border:1px solid #eef2ea;border-radius:12px;padding:10px 14px;margin-bottom:14px;display:flex;flex-direction:column;gap:8px;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:12px;color:#7b8a74;"><?= htmlspecialchars(passenger_t('profile.code', 'Passenger Code'), ENT_QUOTES, 'UTF-8') ?></span>
                <strong style="font-size:13px;color:#2e4836;"><?= htmlspecialchars($passengerQrProfileCode) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:12px;color:#7b8a74;"><?= htmlspecialchars(passenger_t('home.passenger_type', 'Passenger Type'), ENT_QUOTES, 'UTF-8') ?></span>
                <strong style="font-size:13px;color:#2e4836;"><?= htmlspecialchars($passengerQrProfileType) ?></strong>
            </div>
        </div>
        <?= $passengerQrCardHtml ?>
        <p style="margin:12px 0 0;font-size:11px;color:#96a394;text-align:center;font-family:'Poppins',sans-serif;">
            <?= htmlspecialchars(passenger_t('qr.note', 'This QR remains available here even if you skipped it at first login.'), ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
</div>
</div>
<style>
#passengerQrOverlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;align-items:center;justify-content:center;padding:10px;}
#passengerQrBox{width:100%;max-width:400px;background:#fef8f3;border:1.5px solid #cdd9b5;border-radius:20px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,.2);display:flex;flex-direction:column;max-height:94vh;overflow-y:auto;}
#passengerQrHeader{background:linear-gradient(135deg,#47613b,#5d8550);color:#fff;padding:16px 18px 14px;position:relative;display:flex;align-items:center;gap:12px;}
#passengerQrClose{position:absolute;top:12px;right:12px;width:32px;height:32px;border-radius:50%;border:none;background:rgba(255,255,255,.15);color:#fff;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.2s;}
#passengerQrClose:hover{background:rgba(255,255,255,.25);}
#passengerQrBody{padding:20px 18px 18px;}
@media(max-width:480px){#passengerQrOverlay{padding:6px;}#passengerQrBody{padding:16px 14px 14px;}}
</style>

<style>
#paymentMethodModal.pm-overlay{display:none;position:fixed;inset:0;background:rgba(46,72,54,.35);align-items:center;justify-content:center;z-index:9999999;padding:10px;}
#paymentMethodModal .pm-box{position:relative;width:100%;max-width:360px;background:#fef8f3;border:2px solid #c7c28a;border-radius:16px;box-shadow:0 6px 18px rgba(0,0,0,.08);padding:24px;font-family:'Poppins',sans-serif;}
#paymentMethodModal .pm-close{position:absolute;top:10px;right:15px;font-size:20px;cursor:pointer;color:#2e4836;line-height:1;}
#paymentMethodModal .pm-title{font-size:17px;font-weight:700;color:#2e4836;text-align:center;}
#paymentMethodModal .pm-sub{font-size:12px;color:#6b7d6b;text-align:center;margin:6px 0 16px;line-height:1.5;}
#paymentMethodModal .pm-options{display:flex;flex-direction:column;gap:8px;}
#paymentMethodModal .pm-option{display:flex;align-items:center;gap:10px;width:100%;border:1px solid #e5e0a4;background:#fff;color:#24513c;border-radius:12px;padding:12px 14px;font-family:'Poppins';font-size:13px;font-weight:600;line-height:1.35;cursor:pointer;text-align:left;}
#paymentMethodModal .pm-option:hover{background:#faf9e5;}
#paymentMethodModal .pm-option.pm-active{border-color:#47613b;background:#e8f0e0;color:#1b5e20;}
#paymentMethodModal .pm-option span{flex:1 1 auto;min-width:0;overflow-wrap:anywhere;}
#paymentMethodModal .pm-option .pm-check{display:none;font-size:15px;color:#2e7d32;flex:0 0 auto;}
#paymentMethodModal .pm-option.pm-active .pm-check{display:inline;}
#paymentMethodModal .pm-save{margin-top:16px;width:100%;padding:12px;border:none;border-radius:30px;font-family:'Poppins',sans-serif;font-weight:800;font-size:13px;color:#fff;background:linear-gradient(90deg,#7bbf5e,#5aa943);cursor:pointer;transition:opacity .15s ease;}
#paymentMethodModal .pm-save:disabled{opacity:.55;cursor:not-allowed;}
</style>

<div class="pm-overlay" id="paymentMethodModal" onclick="closePaymentMethodModal(event)">
    <div class="pm-box" onclick="event.stopPropagation()">
        <div class="pm-close" onclick="closePaymentMethodModal()">&times;</div>
        <div class="pm-title"><?= htmlspecialchars(passenger_t('profile.default_payment_method', 'Default Payment Method'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="pm-sub"><?= htmlspecialchars(passenger_t('profile.default_payment_method_sub', 'Choose how you want to pay by default. You can still change it per trip.'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="pm-options">
            <button type="button" class="pm-option" data-method="cash" onclick="selectDefaultPaymentMethod('cash')">
                <i class="bi bi-cash-stack"></i>
                <span><?= htmlspecialchars(passenger_t('home.cash', 'Cash'), ENT_QUOTES, 'UTF-8') ?></span>
                <i class="bi bi-check-circle-fill pm-check"></i>
            </button>
            <button type="button" class="pm-option" data-method="gcash" onclick="selectDefaultPaymentMethod('gcash')">
                <i class="bi bi-wallet2"></i>
                <span><?= htmlspecialchars(passenger_t('home.online_payment', 'Online Payment'), ENT_QUOTES, 'UTF-8') ?></span>
                <i class="bi bi-check-circle-fill pm-check"></i>
            </button>
            <button type="button" class="pm-option" data-method="hybrid" onclick="selectDefaultPaymentMethod('hybrid')">
                <i class="bi bi-arrow-left-right"></i>
                <span><?= htmlspecialchars(passenger_t('home.hybrid', 'Online Payment + Cash'), ENT_QUOTES, 'UTF-8') ?></span>
                <i class="bi bi-check-circle-fill pm-check"></i>
            </button>
        </div>
        <button type="button" class="pm-save" id="paymentMethodSaveBtn" onclick="saveDefaultPaymentMethod()">
            <?= htmlspecialchars(passenger_t('profile.save', 'Save'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>
</div>

<div id="customAlertOverlay" class="custom-alert-overlay">
    <div class="custom-alert-card">
        <div class="custom-alert-header">
            <div class="custom-alert-icon"></div>
            <h3 class="custom-alert-title"></h3>
        </div>
        <div class="custom-alert-body">
            <p class="custom-alert-message"></p>
            <div class="custom-alert-actions">
                <button class="custom-alert-btn"></button>
                <button class="custom-alert-btn-cancel"></button>
            </div>
        </div>
    </div>
</div>
<script src="/public/login/custom-alert.js"></script>
<script>
window.PASSENGER_QR_STRINGS = {
    alt: <?= json_encode(passenger_t('qr.alt', 'Passenger QR'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    title: <?= json_encode(passenger_t('qr.print_title', 'R-CONNECT Passenger QR'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    head: <?= json_encode(passenger_t('qr.print_head', 'R-CONNECT Passenger ID'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};
</script>
<script src="/assets/js/passenger/passenger-qr.js"></script>
<script>
function openPassengerQr(){
    var overlay = document.getElementById('passengerQrOverlay');
    var payloadEl = document.getElementById('passengerQrProfileCanvas');
    if(payloadEl){
        renderPassengerQr(payloadEl.getAttribute('data-payload'), 'passengerQrProfileCanvas');
    }
    if(overlay){
        overlay.style.display = 'flex';
    }
}
</script>
</body>
</html>
