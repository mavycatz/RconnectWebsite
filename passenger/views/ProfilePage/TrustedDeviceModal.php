<?php

if (!isset($_SESSION["passenger_id"])) {
    echo "<div style='color:#a93226;padding:16px;text-align:center;font-family:Poppins,sans-serif;'>Session expired</div>";
    exit();
}

require_once $_SERVER["DOCUMENT_ROOT"] . "/passenger/controllers/ProfilePage/ProfileController.php";

$controller = new ProfileController();
$trustedDevice = $controller->getTrustedDevice($_SESSION["passenger_id"]);

$trustedSelector = (string) ($trustedDevice["selector"] ?? "");
$deviceLabel = "";
$isCurrentServer = false;

if (!empty($trustedDevice)) {
    $deviceLabel = \App\Passenger\PassengerSessionService::friendlyDeviceLabel(
        $trustedDevice["user_agent"] ?? null,
        $trustedDevice["device_label"] ?? null
    );
    if ($deviceLabel === "") {
        $deviceLabel = passenger_t("trusted.unknown_device", "Unknown device");
    }

    $cookieToken = (string) ($_COOKIE["remember_passenger"] ?? "");
    $cookieSelector = $cookieToken !== "" ? (string) explode(":", $cookieToken)[0] : "";
    $isCurrentServer = $trustedSelector !== "" && $cookieSelector !== "" && hash_equals($trustedSelector, $cookieSelector);
}

$lastUsed = !empty($trustedDevice["last_used_at"])
    ? date("M j, Y g:i A", strtotime($trustedDevice["last_used_at"]))
    : passenger_t("trusted.never_used", "Not yet used");
$expires = !empty($trustedDevice["expires"])
    ? date("M j, Y", strtotime($trustedDevice["expires"]))
    : passenger_t("trusted.not_available", "—");

$tThisTitle = passenger_t("trusted.this_device_title", "This device is trusted");
$tThisBody = passenger_t("trusted.this_device_body", "Email OTP is skipped when you log in on this device.");
$tOtherTitle = passenger_t("trusted.other_device_title", "A different device is trusted");
$tOtherBody = passenger_t("trusted.other_device_body", "Logging in on this device will still ask for an email OTP. After you verify, this device becomes the trusted one.");
$tThisLabel = passenger_t("trusted.device_this", "This device");
$tOtherLabel = passenger_t("trusted.device_other", "Other device");
?>
<style>
#trustedDeviceModal.td-overlay{position:fixed;inset:0;background:rgba(46,72,54,.35);display:flex;align-items:center;justify-content:center;z-index:9999999;padding:10px;}
#trustedDeviceModal .td-box{position:relative;width:100%;max-width:380px;background:#fef8f3;border:2px solid #c7c28a;border-radius:16px;box-shadow:0 6px 18px rgba(0,0,0,.08);padding:24px;font-family:'Poppins',sans-serif;}
#trustedDeviceModal .td-close{position:absolute;top:10px;right:15px;font-size:20px;cursor:pointer;color:#2e4836;line-height:1;}
#trustedDeviceModal .td-title{font-size:17px;font-weight:700;color:#2e4836;text-align:center;}
#trustedDeviceModal .td-sub{font-size:12px;color:#6b7d6b;text-align:center;margin:6px 0 16px;}
#trustedDeviceModal .td-status{display:flex;gap:8px;align-items:flex-start;border-radius:12px;padding:10px 12px;font-size:11.5px;line-height:1.4;margin-bottom:12px;}
#trustedDeviceModal .td-status i{font-size:14px;margin-top:1px;}
#trustedDeviceModal .td-status strong{font-size:12px;}
#trustedDeviceModal .td-status-ok{background:#e8f5e9;border:1px solid #b7d8b0;color:#1b5e20;}
#trustedDeviceModal .td-status-warn{background:#fff8e1;border:1px solid #f0d060;color:#8d6e00;}
#trustedDeviceModal .td-row{display:flex;justify-content:space-between;gap:10px;background:#fff;border:1px solid #e5e0a4;border-radius:10px;padding:9px 12px;font-size:12px;margin-bottom:8px;}
#trustedDeviceModal .td-row .td-label{color:#6b7d6b;}
#trustedDeviceModal .td-row .td-value{color:#24513c;font-weight:600;text-align:right;word-break:break-word;}
#trustedDeviceModal .td-empty{font-size:12px;color:#6b7d6b;text-align:center;padding:10px 0 4px;line-height:1.5;}
#trustedDeviceModal .td-btn{width:100%;border:none;border-radius:999px;padding:11px;font-family:'Poppins';font-size:13px;font-weight:700;cursor:pointer;}
#trustedDeviceModal .td-btn-revoke{background:#fbe8e8;color:#a93226;}
#trustedDeviceModal .td-btn-danger{background:#a93226;color:#fff;}
#trustedDeviceModal .td-btn-cancel{background:#fff;border:1px solid #cdd9b5;color:#57694d;font-weight:600;}
#trustedDeviceModal .td-confirm{border:1px solid #f0c2c2;background:#fff7f7;border-radius:12px;padding:12px;margin-top:10px;}
#trustedDeviceModal .td-confirm-title{font-size:13px;font-weight:700;color:#a93226;margin-bottom:4px;}
#trustedDeviceModal .td-confirm-body{font-size:11.5px;color:#6b7d6b;margin-bottom:10px;}
#trustedDeviceModal .td-actions{display:flex;gap:8px;}
#trustedDeviceModal .td-hidden{display:none;}
</style>

<div class="td-overlay" id="trustedDeviceModal"
     data-trusted-selector="<?= htmlspecialchars($trustedSelector, ENT_QUOTES, "UTF-8") ?>"
     data-this-title="<?= htmlspecialchars($tThisTitle, ENT_QUOTES, "UTF-8") ?>"
     data-this-body="<?= htmlspecialchars($tThisBody, ENT_QUOTES, "UTF-8") ?>"
     data-other-title="<?= htmlspecialchars($tOtherTitle, ENT_QUOTES, "UTF-8") ?>"
     data-other-body="<?= htmlspecialchars($tOtherBody, ENT_QUOTES, "UTF-8") ?>"
     data-this-label="<?= htmlspecialchars($tThisLabel, ENT_QUOTES, "UTF-8") ?>"
     data-other-label="<?= htmlspecialchars($tOtherLabel, ENT_QUOTES, "UTF-8") ?>"
     onclick="closeTrustedDeviceModal(event)">
    <div class="td-box" onclick="event.stopPropagation()">
        <div class="td-close" onclick="closeTrustedDeviceModal()">&times;</div>
        <div class="td-title"><?= htmlspecialchars(passenger_t("trusted.title", "Trusted device"), ENT_QUOTES, "UTF-8") ?></div>
        <div class="td-sub"><?= htmlspecialchars(passenger_t("trusted.subtitle", "This device can skip the email OTP the next time you log in."), ENT_QUOTES, "UTF-8") ?></div>

        <?php if (!empty($trustedDevice)): ?>
        <div id="tdStatus" class="td-status <?= $isCurrentServer ? "td-status-ok" : "td-status-warn" ?>">
            <i class="bi <?= $isCurrentServer ? "bi-check-circle-fill" : "bi-exclamation-triangle-fill" ?>"></i>
            <div>
                <strong><?= htmlspecialchars($isCurrentServer ? $tThisTitle : $tOtherTitle, ENT_QUOTES, "UTF-8") ?></strong>
                <div><?= htmlspecialchars($isCurrentServer ? $tThisBody : $tOtherBody, ENT_QUOTES, "UTF-8") ?></div>
            </div>
        </div>

        <div class="td-row">
            <span class="td-label"><?= htmlspecialchars(passenger_t("trusted.device", "Device"), ENT_QUOTES, "UTF-8") ?></span>
            <span class="td-value" id="tdDeviceValue" data-label="<?= htmlspecialchars($deviceLabel, ENT_QUOTES, "UTF-8") ?>"><?= htmlspecialchars(($isCurrentServer ? $tThisLabel : $tOtherLabel) . " — " . $deviceLabel, ENT_QUOTES, "UTF-8") ?></span>
        </div>
        <div class="td-row">
            <span class="td-label"><?= htmlspecialchars(passenger_t("trusted.last_used", "Last used"), ENT_QUOTES, "UTF-8") ?></span>
            <span class="td-value"><?= htmlspecialchars($lastUsed, ENT_QUOTES, "UTF-8") ?></span>
        </div>
        <div class="td-row">
            <span class="td-label"><?= htmlspecialchars(passenger_t("trusted.expires", "Expires"), ENT_QUOTES, "UTF-8") ?></span>
            <span class="td-value"><?= htmlspecialchars($expires, ENT_QUOTES, "UTF-8") ?></span>
        </div>

        <div id="tdConfirm" class="td-confirm td-hidden">
            <div class="td-confirm-title"><?= htmlspecialchars(passenger_t("trusted.revoke_confirm_title", "Revoke trusted device?"), ENT_QUOTES, "UTF-8") ?></div>
            <div class="td-confirm-body"><?= htmlspecialchars(passenger_t("trusted.revoke_confirm_body", "You will need an email OTP the next time you log in on this device."), ENT_QUOTES, "UTF-8") ?></div>
            <div class="td-actions">
                <button type="button" class="td-btn td-btn-cancel" onclick="document.getElementById('tdConfirm').classList.add('td-hidden')"><?= htmlspecialchars(passenger_t("trusted.cancel", "Cancel"), ENT_QUOTES, "UTF-8") ?></button>
                <button type="button" class="td-btn td-btn-danger" onclick="document.getElementById('trustedRevokeForm').submit()"><?= htmlspecialchars(passenger_t("trusted.confirm", "Revoke"), ENT_QUOTES, "UTF-8") ?></button>
            </div>
        </div>

        <form id="trustedRevokeForm" method="POST" action="/passenger/profile/trusted-device/revoke" style="margin-top:12px;">
            <?php if (function_exists("passenger_csrf_token")): ?>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(passenger_csrf_token(), ENT_QUOTES, "UTF-8") ?>">
            <?php endif; ?>
            <button type="button" class="td-btn td-btn-revoke" onclick="document.getElementById('tdConfirm').classList.remove('td-hidden')">
                <i class="bi bi-shield-slash-fill"></i> <?= htmlspecialchars(passenger_t("trusted.revoke", "Revoke trusted device"), ENT_QUOTES, "UTF-8") ?>
            </button>
        </form>
        <?php else: ?>
        <div class="td-empty"><?= htmlspecialchars(passenger_t("trusted.empty", "No trusted device yet. After your next successful email OTP login, this device becomes trusted."), ENT_QUOTES, "UTF-8") ?></div>
        <?php endif; ?>
    </div>
</div>
