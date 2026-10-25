<?php

require_once $_SERVER["DOCUMENT_ROOT"] . "/app/Core/Database.php";

use App\Core\Database;

if (!isset($_SESSION["passenger_id"])) {
    header("Location: /passenger/login");
    exit();
}

$name = trim((string) ($_SESSION["passenger_name"] ?? ""));
if ($name === "") {
    // Sessions can carry an identity without a stored name; fall back to a
    // neutral label instead of warning and rendering a broken greeting.
    $name = passenger_t('trips.passenger', 'Passenger');
}

$qrBus = $_SESSION["qr_bus"] ?? "";

$qrTrip = $_SESSION["qr_trip"] ?? "";

$conn = Database::tryGet();
if (!$conn) {
    if (function_exists('passenger_render_db_error_page')) {
        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo passenger_render_db_error_page('DB_OFFLINE');
        exit();
    }
    http_response_code(503);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Service Unavailable</title></head><body style="font-family:sans-serif;text-align:center;padding:40px;"><h3>Service Temporarily Unavailable</h3><p>We are having trouble connecting. Please refresh and try again shortly.</p><button onclick="window.location.reload()" style="padding:10px 20px;border-radius:10px;background:#47613b;color:#fff;border:none;cursor:pointer;">Try Again</button></body></html>';
    exit();
}

if ($qrBus !== "" || $qrTrip !== "") {
    $qrFresh = isset($_SESSION["qr_scanned_at"]) && time() - (int) $_SESSION["qr_scanned_at"] < 1800;

    $hasActiveTrip = false;
    try {
        $chk = $conn->prepare(
            "SELECT id FROM passenger_trips WHERE passenger_id = ? AND dropoff_time IS NULL ORDER BY id DESC LIMIT 1",
        );
        $chk->execute([$_SESSION["passenger_id"]]);
        $hasActiveTrip = (bool) $chk->fetchColumn();
    } catch (\Throwable $e) {
        $hasActiveTrip = false;
    }

    if (!$qrFresh || $hasActiveTrip) {
        unset($_SESSION["qr_bus"], $_SESSION["qr_trip"], $_SESSION["qr_scanned_at"]);
        $qrBus = "";
        $qrTrip = "";
    }
}

$gcashNumber = "0994567894";
$gcashName = "Maria Teresa";
$gcashQr = "";

try {
    $stmt = $conn->query(
        "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('gcash_number','gcash_name','gcash_qr')",
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row["setting_key"] === "gcash_number") {
            $gcashNumber = $row["setting_value"];
        }
        if ($row["setting_key"] === "gcash_name") {
            $gcashName = $row["setting_value"];
        }
        if ($row["setting_key"] === "gcash_qr") {
            $gcashQr = $row["setting_value"];
        }
    }
} catch (\Exception $e) {
}

$isOneTimeSession = !empty($_SESSION["is_one_time"]);
$passengerQr = null;
$passengerQrIntro = false;

if (!$isOneTimeSession && is_file($_SERVER["DOCUMENT_ROOT"] . "/passenger/services/PassengerQRSchema.php")) {
    require_once $_SERVER["DOCUMENT_ROOT"] . "/passenger/services/PassengerQRSchema.php";
    if (function_exists("ensurePassengerQrSchema")) {
        try {
            ensurePassengerQrSchema($conn);
        } catch (\Exception $e) {
        }
    }

    try {
        $stmt = $conn->prepare(
            "SELECT id, full_name, discount_type, discount_status, passenger_code, personal_qr_seen FROM passengers WHERE id = ? LIMIT 1",
        );
        $stmt->execute([$_SESSION["passenger_id"]]);
        $passengerQr = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($passengerQr && empty($passengerQr["passenger_code"]) && is_file($_SERVER["DOCUMENT_ROOT"] . "/passenger/services/PassengerCodeService.php")) {
            require_once $_SERVER["DOCUMENT_ROOT"] . "/passenger/services/PassengerCodeService.php";
            try {
                $assigned = PassengerCodeService::assignIfMissing(
                    $conn,
                    (int)$passengerQr["id"],
                    (string)($passengerQr["discount_type"] ?? "")
                );
                if (is_string($assigned) && $assigned !== "") {
                    $passengerQr["passenger_code"] = $assigned;
                }
            } catch (\Throwable $e) {
            }
        }
    } catch (\Exception $e) {
        $passengerQr = null;
    }
}

if (!$isOneTimeSession && $passengerQr) {
    $passengerQrIntro =
        ((int) ($passengerQr["personal_qr_seen"] ?? 0)) === 0 &&
        is_file(__DIR__ . "/../PassengerQr/passenger_qr_card.php");
}

// Saved payment-method preference: set when the passenger confirmed a method
// with "Yes, use it next time". It is preselected below; an active trip's own
// method still overrides it for the current trip.
$defaultPaymentMethod = "cash";
$hasSavedPaymentDefaultMethod = false;
try {
    $stmt = $conn->prepare("SELECT default_payment_method FROM passengers WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION["passenger_id"]]);
    $savedDefaultMethod = strtolower(trim((string) $stmt->fetchColumn()));
    if (in_array($savedDefaultMethod, ["cash", "gcash", "hybrid"], true)) {
        $defaultPaymentMethod = $savedDefaultMethod;
        $hasSavedPaymentDefaultMethod = true;
    }
} catch (\Throwable $e) {
    // The column is lazily added; fall back to cash when it is unavailable.
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
<title><?= htmlspecialchars(passenger_t('home.title', 'Passenger Home'), ENT_QUOTES, 'UTF-8') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php include __DIR__ . "/../layout/csrf.php"; ?>
<script>
/* Owning passenger id for the local caches this page writes; a cache from a
   different passenger session is dropped instead of rendered. */
window.PASSENGER_SESSION_ID = <?= json_encode(isset($_SESSION["passenger_id"]) ? (int) $_SESSION["passenger_id"] : 0) ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="manifest" href="/passenger/manifest.json">
<link rel="apple-touch-icon" href="/assets/images/logo/logoR.png">
<meta name="theme-color" content="#47613b">
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
body{ padding-bottom:90px; }
.camera-card{
    position:relative;
    border:1.5px solid #c6d48c;
    border-radius:20px;
    background:#fef8f3;
    padding:16px;
    box-shadow:0 6px 20px rgba(0,0,0,0.15);
}
.scanner-viewport-wrap{
    position:relative;
    width:100%;
    max-width:360px;
    margin:0 auto;
    border-radius:16px;
    overflow:hidden;
}
#reader{
    width:100% !important;
    max-width:360px !important;
    height:320px !important;
    min-height:320px !important;
    max-height:320px !important;
    margin:0 auto !important;
    border-radius:16px;
    overflow:hidden;
    background:#182216;
}
#reader[style*="display: none"],
#reader[style*="display:none"]{
    height:0 !important;
    min-height:0 !important;
    max-height:0 !important;
}
#reader video,
#reader canvas{
    width:100% !important;
    height:100% !important;
    max-height:320px !important;
    object-fit:cover !important;
    border-radius:16px;
}
#reader__scan_region{
    width:100% !important;
    height:100% !important;
    min-height:320px !important;
    max-height:320px !important;
    display:flex !important;
    align-items:center !important;
    justify-content:center !important;
}
#reader__scan_region video{
    width:100% !important;
    height:100% !important;
    object-fit:cover !important;
}
.scan-box{
    position:absolute;
    width:220px;
    height:220px;
    top:50%;
    left:50%;
    transform:translate(-50%, -50%);
    border:2px solid rgba(198,212,140,0.85);
    border-radius:16px;
    box-shadow:0 0 0 4000px rgba(0,0,0,0.22);
    pointer-events:none;
    z-index:2;
}
#reader__dashboard_section,
#reader__dashboard{
    display:none !important;
}
@media (max-width:380px){
    .scanner-viewport-wrap,
    #reader{
        max-width:280px !important;
        height:260px !important;
        min-height:260px !important;
        max-height:260px !important;
    }
    #reader video,
    #reader canvas,
    #reader__scan_region,
    #reader__scan_region video{
        max-height:260px !important;
    }
    .scan-box{
        width:190px;
        height:190px;
    }
}
.payment-option{
    flex:1 1 0%;
    min-width:0;
    border:1px solid #c6d48c;
    background:#fff;
    color:#24513c;
    border-radius:14px;
    padding:10px 6px;
    cursor:pointer;

    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:6px;

    font-size:13px;
    font-weight:600;
    min-height:52px;
    transition:.2s;
}

.payment-option i{
    font-size:19px;
}

.payment-option.active{
    background:#47613b;
    color:#fff;
    border-color:#47613b;
    box-shadow:0 3px 10px rgba(71,97,59,.22);
}

.payment-option:hover{
    transform:translateY(-1px);
}

.payment-option:disabled{
    opacity:.55;
    cursor:not-allowed;
    transform:none;
    box-shadow:none;
}

.scan-toast{
    position:fixed;
    top:calc(12px + env(safe-area-inset-top, 0px));
    left:0;
    right:0;
    margin:0 auto;
    z-index:10000;
    width:max-content;
    max-width:92vw;
    display:flex;
    align-items:center;
    gap:8px;
    padding:10px 16px;
    border-radius:12px;
    font-family:'Poppins',sans-serif;
    font-size:12px;
    font-weight:600;
    line-height:1.4;
    box-shadow:0 6px 18px rgba(0,0,0,0.15);
    text-align:left;
    animation:toastDrop 0.25s ease;
}

.scan-toast i{
    font-size:15px;
    flex-shrink:0;
}

.scan-toast-neutral{
    background:#fef8f3;
    border:1.5px solid #c6d48c;
    color:#24513c;
}

.scan-toast-neutral i{
    color:#7b8a74;
}

.scan-toast-warning{
    background:#fff4d6;
    border:1.5px solid #eecb6f;
    color:#b7791f;
}

.scan-toast-warning i{
    color:#b7791f;
}

.scan-toast-error{
    background:#fdecea;
    border:1.5px solid #f0b4a8;
    color:#c0392b;
}

.scan-toast-error i{
    color:#dc3545;
}

.scan-toast-success{
    background:#e8f5e9;
    border:1.5px solid #b7e4c7;
    color:#2e7d32;
}

.scan-toast-success i{
    color:#2e7d32;
}

.scan-toast-hide{
    animation:toastFadeOut 0.25s ease forwards;
}

@keyframes toastDrop{
    from{ opacity:0; transform:translateY(-16px); }
    to{ opacity:1; transform:translateY(0); }
}

@keyframes toastFadeOut{
    from{ opacity:1; transform:translateY(0); }
    to{ opacity:0; transform:translateY(-8px); }
}

</style>
</head>

<body>

<div id="scanToast" class="scan-toast scan-toast-neutral" style="display:none;">
    <i id="scanToastIcon" class="bi bi-info-circle"></i>
    <span id="scanToastMsg"></span>
</div>

<?php include __DIR__ . "/../layout/header.php"; ?>

<div style="position: relative; width: 100%; margin: 0; padding: 0;">

    <img src="/assets/images/passenger/header-banner.png" style="width:100%; display:block; object-fit:cover;">

    <div style="
        position: absolute;
        top: 130%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 95%;
        max-width: 800px;
        background: #fef8f3;
        border-radius: 15px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        padding: 25px 20px;
    ">
        <h2 style="margin:0; font-family:'Poppins'; font-weight:700; font-size:20px; color:#24513c;">
            <?= htmlspecialchars(passenger_t('home.greeting', 'Hello, :name!', ['name' => $name]), ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p style="margin-top:5px; font-size:12px; color:#6c757d;">
            <?= htmlspecialchars(passenger_t('home.scan_hint', 'Please scan the code here before getting on the bus.'), ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>

    <div style="
        position: absolute;
        top: 160%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 55%;
        max-width: 450px;
        background: #fcf5dd;
        border: 1px solid #c6d48c;
        border-radius: 15px;
        padding: 10px;
        text-align: center;
        z-index: 2;
    ">
        <h3 style="margin:0; font-size:13px; font-weight:600;">
            <?= htmlspecialchars(passenger_t('home.scan_qr_here', 'Scan your QR here.'), ENT_QUOTES, 'UTF-8') ?>
        </h3>
    </div>

    <div style="
        position: absolute;
        top: 160%;
        left: 50%;
        transform: translateX(-50%);
        width: 95%;
        background: #e3eabb;
        padding: 30px 0;
        z-index: 1;
    "></div>

</div>

<div style="
    position: relative;
    width: 95%;
    max-width: 900px;
    margin: 120px auto 0 auto;
    display: flex;
    justify-content: center;
    background: #fef8f3;
    border: 2px solid #c6d48c;
    border-radius: 20px;
    padding: 10px 0;
    z-index: 10;
">

<div style="width:95%; max-width:650px; display:flex; flex-direction:column; gap:10px;">

<div id="companionPromptCard" style="display:none;background:#ffffff;border:1px solid #c6d48c;border-radius:16px;padding:14px 16px;box-shadow:0 2px 10px rgba(36,81,60,.06);">
    <div style="display:flex;align-items:flex-start;gap:11px;">
        <span style="flex-shrink:0;width:40px;height:40px;border-radius:12px;background:#eef4e4;color:#47613b;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-people-fill" style="font-size:18px;"></i>
        </span>
        <div style="min-width:0;flex:1;">
            <div id="companionCardTitle" style="font-size:14px;font-weight:800;color:#24513c;line-height:1.3;"><?= htmlspecialchars(passenger_t('companion.card_title', 'Travelling with a companion?'), ENT_QUOTES, 'UTF-8') ?></div>
            <div id="companionCardSub" style="font-size:11.5px;color:#7b8a74;margin-top:2px;line-height:1.5;">
                <?= htmlspecialchars(passenger_t('companion.card_sub', "Add a child or senior who is riding without a cellphone."), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div id="companionCardMeta" style="margin-top:9px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                <button type="button" id="companionOpenBtn" style="flex:1;min-width:0;border:0;border-radius:11px;padding:11px 14px;background:#47613b;color:#ffffff;font-family:'Poppins',sans-serif;font-size:12.5px;font-weight:800;cursor:pointer;">
                    <i class="bi bi-person-plus-fill"></i> <span id="companionOpenLabel"><?= htmlspecialchars(passenger_t('companion.add_companion', 'Add Companion'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
                <button type="button" id="companionDismissBtn" style="padding:11px 14px;border:1px solid #d8e2d0;border-radius:11px;background:#ffffff;color:#5c6f57;font-family:'Poppins',sans-serif;font-size:12.5px;font-weight:700;cursor:pointer;">
                    <?= htmlspecialchars(passenger_t('companion.not_now', 'Not Now'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="camera-card qr-trip-shell">

<div class="qr-trip-head" style="margin-bottom:12px;">
    <span class="qr-trip-badge"><i class="bi bi-bus-front"></i></span>
    <div>
        <div class="qr-trip-title"><?= htmlspecialchars(passenger_t('home.trip_info', 'Trip Information'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="qr-trip-sub"><?= htmlspecialchars(passenger_t('home.on_board', 'Currently on board'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <span id="tripStatus" class="qr-trip-status"><?= htmlspecialchars(passenger_t('home.pending', 'Pending'), ENT_QUOTES, 'UTF-8') ?></span>
</div>

<div id="tripToggle" class="qr-trip-bus" style="margin-top:0;cursor:pointer;">
    <div class="qr-trip-bus-label"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars(passenger_t('home.boarding_point', 'Boarding Point'), ENT_QUOTES, 'UTF-8') ?></div>
    <div style="display:flex;align-items:center;gap:8px;">
        <div id="boardingPoint" class="qr-trip-block-value" style="flex:1;min-width:0;font-size:13px;"><?= htmlspecialchars(passenger_t('home.not_boarded', 'Not boarded yet'), ENT_QUOTES, 'UTF-8') ?></div>
        <span id="tripArrow" class="qr-trip-arrow">▼</span>
    </div>
</div>

<div id="tripDetails" style="
    display:none;
    margin-top:12px;
">

    <div class="qr-trip-bus">
        <div class="qr-trip-bus-label"><i class="bi bi-bus-front"></i> <?= htmlspecialchars(passenger_t('home.bus', 'Bus'), ENT_QUOTES, 'UTF-8') ?></div>
        <div id="busInfo" class="qr-trip-bus-value">—</div>
    </div>

    <div class="qr-trip-grid">
        <div class="qr-trip-block">
            <div class="qr-trip-block-label"><i class="bi bi-person-badge"></i> <?= htmlspecialchars(passenger_t('home.driver', 'Driver'), ENT_QUOTES, 'UTF-8') ?></div>
            <div id="driverInfo" class="qr-trip-block-value">—</div>
        </div>
        <div class="qr-trip-block">
            <div class="qr-trip-block-label"><i class="bi bi-ticket-detailed"></i> <?= htmlspecialchars(passenger_t('home.trip', 'Trip'), ENT_QUOTES, 'UTF-8') ?></div>
            <div id="tripNoInfo" class="qr-trip-block-value">—</div>
        </div>
    </div>

    <div class="qr-trip-block" style="margin-top:8px;">
        <div class="qr-trip-block-label"><i class="bi bi-person-vcard"></i> <?= htmlspecialchars(passenger_t('home.passenger_type', 'Passenger Type'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="qr-trip-block-value">
            <span id="passengerType" class="qr-trip-chip">—</span>
        </div>
    </div>

<div style="margin:16px 0 10px;">
    <strong style="font-size:14px; color:#24513c;"><?= htmlspecialchars(passenger_t('home.payment_method', 'Payment Method'), ENT_QUOTES, 'UTF-8') ?></strong>
</div>

<div id="paymentMethods" style="
    display:flex;
    flex-wrap:wrap;
    gap:8px;
">

    <button type="button"
        class="payment-option<?= $defaultPaymentMethod === 'gcash' ? ' active' : '' ?>"
        data-method="gcash">
        <i class="bi bi-wallet2"></i>
        <span><?= htmlspecialchars(passenger_t('home.online_payment', 'Online Payment'), ENT_QUOTES, 'UTF-8') ?></span>
    </button>

    <button type="button"
        class="payment-option<?= $defaultPaymentMethod === 'cash' ? ' active' : '' ?>"
        data-method="cash">
        <i class="bi bi-cash-stack"></i>
        <span><?= htmlspecialchars(passenger_t('home.cash', 'Cash'), ENT_QUOTES, 'UTF-8') ?></span>
    </button>

    <button type="button"
        class="payment-option<?= $defaultPaymentMethod === 'hybrid' ? ' active' : '' ?>"
        data-method="hybrid">
        <i class="bi bi-arrow-left-right"></i>
        <span><?= htmlspecialchars(passenger_t('home.hybrid', 'Online Payment + Cash'), ENT_QUOTES, 'UTF-8') ?></span>
    </button>

    <div id="hybridBreakdown" style="display:none;flex:0 0 100%;">

    <div style="margin-top:12px;">
        <label style="font-size:13px; color:#555;"><?= htmlspecialchars(passenger_t('home.cash_amount', 'Cash Amount'), ENT_QUOTES, 'UTF-8') ?></label>
        <input
            id="cashAmountInput"
            type="number"
            step="0.01"
            min="0"
            value="0"
            style="
                width:100%;
                padding:12px;
                border-radius:10px;
                border:1px solid #c6d48c;
                font-size:15px;
                min-height:48px;
                margin-top:6px;
            "
        >
    </div>

    <div style="margin-top:12px;">
        <label style="font-size:13px; color:#555;"><?= htmlspecialchars(passenger_t('home.online_amount', 'Online Payment Amount'), ENT_QUOTES, 'UTF-8') ?></label>
        <input
            id="gcashAmountInput"
            type="number"
            step="0.01"
            min="0"
            value="0"
            style="
                width:100%;
                padding:12px;
                border-radius:10px;
                border:1px solid #c6d48c;
                font-size:15px;
                min-height:48px;
                margin-top:6px;
            "
        >
    </div>

</div>

</div>

<button type="button" id="paymentMethodConfirmBtn" style="
    width:100%;
    margin-top:12px;
    padding:13px 16px;
    border:0;
    border-radius:12px;
    background:linear-gradient(135deg,#47613b,#5d8550);
    color:#ffffff;
    font-family:'Poppins',sans-serif;
    font-size:14px;
    font-weight:800;
    cursor:pointer;
    touch-action:manipulation;
    box-shadow:0 4px 12px rgba(71,97,59,.18);
    transition:opacity .15s ease;
">
    <?= htmlspecialchars(passenger_t('home.confirm_payment_method', 'Confirm Payment Method'), ENT_QUOTES, 'UTF-8') ?>
</button>

<button type="button" id="paymentMethodReadyPayBtn" style="
    display:none;
    width:100%;
    margin-top:12px;
    padding:13px 16px;
    border:0;
    border-radius:12px;
    background:linear-gradient(135deg,#47613b,#5d8550);
    color:#ffffff;
    font-family:'Poppins',sans-serif;
    font-size:14px;
    font-weight:800;
    cursor:pointer;
    touch-action:manipulation;
    box-shadow:0 4px 12px rgba(71,97,59,.18);
">
    <?= htmlspecialchars(passenger_t('home.ready_to_pay', 'Ready to Pay?'), ENT_QUOTES, 'UTF-8') ?>
</button>

<div id="paymentMethodConfirmStatus" style="
    display:none;
    align-items:center;
    gap:6px;
    font-size:12.5px;
    font-weight:600;
    color:#2f6b43;
    margin-top:8px;
">
    <i id="paymentMethodConfirmStatusIcon" class="bi bi-check-circle-fill" style="font-size:13px;flex-shrink:0;"></i>
    <span id="paymentMethodConfirmStatusText"></span>
</div>

<div id="paymentMethodLockedHint" style="
    display:none;
    align-items:center;
    gap:5px;
    font-size:12px;
    color:#2f6b43;
    margin-top:10px;
">
    <i class="bi bi-lock-fill" style="font-size:12px;flex-shrink:0;"></i>
    <span>Payment method locked &mdash; this trip is already paid.</span>
</div>

<div id="paymentMethodDefaultLockedHint" style="
    display:none;
    align-items:center;
    gap:5px;
    font-size:12px;
    color:#2f6b43;
    margin-top:10px;
">
    <i class="bi bi-lock-fill" style="font-size:12px;flex-shrink:0;"></i>
    <span><?= htmlspecialchars(passenger_t('home.payment_method_locked_settings', 'Payment method locked. Change it in Profile → Settings.'), ENT_QUOTES, 'UTF-8') ?></span>
</div>

<div id="paymentMethodError" style="
    color:#dc3545;
    font-size:13px;
    margin-top:8px;
    display:none;
"></div>

<input
    type="hidden"
    id="paymentMethod"
    value="<?= htmlspecialchars($defaultPaymentMethod, ENT_QUOTES, 'UTF-8') ?>">

    <hr style="margin:16px 0 12px; border:0; border-top:1px solid #eef2ec;">

    <div style="margin-bottom:6px;">
        <strong style="font-size:14px; color:#24513c;">Destination</strong>
    </div>

    <style>
    #destinationSelect{
        box-sizing:border-box;
        display:block;
        width:100%;
        max-width:100%;
        padding:12px 42px 12px 14px;
        border-radius:12px;
        border:1.5px solid #c6d48c;
        font-size:15px;
        font-weight:600;
        color:#24513c;
        min-height:48px;
        line-height:1.3;
        background-color:#fff;
        background-image:url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%2347613b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' d='M4 6l4 4 4-4'/%3E%3C/svg%3E");
        background-repeat:no-repeat;
        background-position:right 14px center;
        background-size:16px 16px;
        appearance:none;
        -webkit-appearance:none;
        -moz-appearance:none;
        text-overflow:ellipsis;
        white-space:nowrap;
        overflow:hidden;
        cursor:pointer;
        outline:none;
        transition:border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }
    #destinationSelect:focus{
        border-color:#7bbf5e;
        box-shadow:0 0 0 3px rgba(123,191,94,.18);
    }
    #destinationSelect:disabled{
        background-color:#f4f6f1;
        border-color:#dde5d4;
        color:#8a9a8a;
        cursor:not-allowed;
        opacity:.85;
    }
    #destinationSelect option{
        color:#24513c;
        font-weight:500;
        background:#fff;
    }
    #destinationSearch{
        box-sizing:border-box;
        display:block;
        width:100%;
        max-width:100%;
        padding:11px 14px;
        border-radius:12px;
        border:1.5px solid #e3eabb;
        font-size:14px;
        font-weight:500;
        color:#24513c;
        min-height:44px;
        line-height:1.3;
        background:#fff;
        margin-bottom:8px;
        outline:none;
        transition:border-color .15s ease, box-shadow .15s ease;
    }
    #destinationSearch:focus{
        border-color:#7bbf5e;
        box-shadow:0 0 0 3px rgba(123,191,94,.18);
    }
    #destinationSearch:disabled{
        background-color:#f4f6f1;
        border-color:#dde5d4;
        color:#8a9a8a;
        cursor:not-allowed;
    }
    </style>

    <div id="destinationDropdown" style="position:relative;">
    <button type="button" id="destinationDropdownToggle" aria-haspopup="listbox" aria-expanded="false" aria-controls="destinationDropdownPanel" style="width:100%;padding:12px 14px;border:1.5px solid #c6d48c;border-radius:12px;background:#fff;color:#24513c;font-size:15px;font-weight:600;text-align:left;">
        <span id="destinationDropdownLabel">Select Destination</span><i class="bi bi-chevron-down" style="float:right;"></i>
    </button>
    <div id="destinationDropdownPanel" hidden style="position:absolute;left:0;right:0;top:calc(100% + 5px);z-index:1100;background:#fff;border:1.5px solid #c6d48c;border-radius:12px;padding:10px;box-shadow:0 8px 24px rgba(36,81,60,.18);">
        <input type="search" id="destinationSearch" aria-label="Search destinations" placeholder="<?= htmlspecialchars(passenger_t('home.search_destination', 'Search destination...'), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
        <div id="destinationDropdownOptions" role="listbox" aria-label="Destinations" style="max-height:240px;overflow:auto;"></div>
    </div>
    <select id="destinationSelect" aria-label="Destination" tabindex="-1" style="display:none;" aria-hidden="true">
        <option value="">Loading destinations...</option>
    </select>
    </div>

    <button type="button" id="destinationRetry" style="
        display:none;
        width:100%;
        margin-top:8px;
        padding:10px 14px;
        border-radius:12px;
        border:1.5px dashed #c6d48c;
        background:#f8faf7;
        color:#47613b;
        font-size:13px;
        font-weight:600;
        cursor:pointer;
        touch-action:manipulation;
    ">
        <?= htmlspecialchars(passenger_t('home.destinations_unavailable', 'Destinations unavailable — tap to retry'), ENT_QUOTES, 'UTF-8') ?>
    </button>

    <div id="destinationError" style="
        color:#dc3545;
        font-size:13px;
        margin-top:8px;
        display:none;
    ">
        Please select your destination before arriving.
    </div>

    <div style="
        display:flex;
        align-items:center;
        gap:5px;
        font-size:12px;
        color:#96a394;
        margin-top:8px;
    ">
        <i class="bi bi-info-circle" style="font-size:12px;flex-shrink:0;"></i>
        <span>Choose carefully &mdash; your destination can only be set once and cannot be changed.</span>
    </div>

    <!-- ========================================================
         [DISABLED] "YOUR ROUTE" SECTION (START)
         This section has been cleanly commented out/disabled per requirements.
         To re-enable, uncomment this block and the routeMap JS functions below.
    <div id="routeMapCard" style="display:none;margin-top:14px;">
        <style>
            #routeMapCanvas .leaflet-control-zoom{ display:none; }
            .route-map-place-tooltip{
                background:rgba(255,255,255,.94);
                border:1px solid #dde5d4;
                color:#5f6f58;
                font-size:10px;
                font-weight:600;
                padding:1px 6px;
                border-radius:8px;
                box-shadow:0 1px 3px rgba(0,0,0,.12);
            }
            .route-map-place-tooltip:before{ display:none; }
            #routeMapCard.route-map-fullscreen{
                position:fixed;
                top:0;
                left:0;
                right:0;
                bottom:80px;
                z-index:2000;
                margin:0;
                padding:0;
                background:#ffffff;
                border-radius:0;
                display:flex !important;
                flex-direction:column;
                overflow:hidden;
            }
            #routeMapCard.route-map-fullscreen > div:first-of-type{
                order:1;
                margin:0;
                padding:10px 12px 8px;
                box-shadow:0 1px 0 #eef2ec;
            }
            #routeMapCard.route-map-fullscreen #routeMapMapWrap{
                order:3;
                flex:1 1 auto;
                min-height:0;
            }
            #routeMapCard.route-map-fullscreen #routeMapCanvas{
                position:absolute !important;
                inset:0 !important;
                height:100% !important;
                border-radius:0 !important;
                border:0 !important;
            }
            #routeMapCard.route-map-fullscreen #routeMapCanvas .leaflet-control-zoom{
                display:block;
            }
            #routeMapCaptionRow,
            #routeMapPrompt,
            #routeMapAreaStats,
            #routeMapInfoPanel{
                display:none;
            }
            #routeMapCard.route-map-fullscreen #routeMapTitleLabel{
                display:none;
            }
            #routeMapCard.route-map-fullscreen #routeMapCaptionRow,
            #routeMapCard.route-map-fullscreen #routeMapAreaStats,
            #routeMapCard.route-map-fullscreen #routeMapInfoPanel{
                display:block;
            }
            #routeMapCard.route-map-fullscreen #routeMapCaptionRow{
                display:flex;
            }
            #routeMapSearchWrap{ display:none; }
            #routeMapCard.route-map-fullscreen #routeMapSearchWrap{ display:block; }
            #routeMapCard.route-map-fullscreen{
                background:#f4f8ef;
            }
            #routeMapCard.route-map-fullscreen > div:first-of-type{
                background:linear-gradient(135deg,#47613b,#5b774d);
                color:#ffffff;
                border-bottom:0;
                box-shadow:0 2px 10px rgba(0,0,0,.18);
            }
            #routeMapCard.route-map-fullscreen #routeMapCaptionRow i{
                color:#c6d48c;
            }
            #routeMapCard.route-map-fullscreen #routeMapCaption{
                color:#ffffff !important;
            }
            #routeMapCard.route-map-fullscreen #routeMapFullscreenBtn{
                background:rgba(255,255,255,.16) !important;
                border-color:rgba(255,255,255,.38) !important;
                color:#ffffff !important;
            }
            #routeMapCard.route-map-fullscreen #routeMapAreaStats{
                display:inline-block;
                margin-top:8px;
                padding:3px 10px;
                border-radius:999px;
                background:rgba(255,255,255,.14);
                color:#ffffff !important;
                font-size:11px;
            }
            #routeMapCard.route-map-fullscreen #routeMapInfoPanel{
                margin-top:8px !important;
            }
            #routeMapCard.route-map-fullscreen #routeMapTripInfo{
                display:flex !important;
                flex-wrap:wrap;
                gap:6px 8px;
                border:0;
                background:transparent;
                padding:0;
            }
            #routeMapCard.route-map-fullscreen #routeMapTripInfo > span{
                background:rgba(255,255,255,.14);
                border-radius:999px;
                padding:3px 10px;
                color:#ffffff;
                font-size:11px;
            }
            #routeMapCard.route-map-fullscreen #routeMapTripInfo > span > span:first-child{
                color:rgba(255,255,255,.75) !important;
            }
            #routeMapCard.route-map-fullscreen #routeMapMessage{
                top:64px;
            }
        </style>
        <div style="margin-bottom:8px;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">
                <div style="min-width:0;">
                    <span id="routeMapTitleLabel" style="display:block;font-size:11px;font-weight:600;color:#7b8a74;letter-spacing:.4px;text-transform:uppercase;"><?= htmlspecialchars(passenger_t('home.route_map_title', 'Your Route'), ENT_QUOTES, 'UTF-8') ?></span>
                    <div id="routeMapCaptionRow" style="display:flex;align-items:center;gap:6px;min-width:0;">
                        <i class="bi bi-geo-alt-fill" style="flex-shrink:0;font-size:13px;"></i>
                        <div id="routeMapCaption" style="font-size:17px;font-weight:700;color:#24513c;line-height:1.25;overflow-wrap:anywhere;">-</div>
                    </div>
                </div>
                <button type="button" id="routeMapFullscreenBtn" style="flex-shrink:0;padding:5px 10px;border:1px solid #dde5d4;border-radius:10px;background:#f8faf7;color:#47613b;font-size:11px;">
                    <?= htmlspecialchars(passenger_t('home.route_map_fullscreen', 'Full screen'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
            <div id="routeMapPrompt" style="margin-top:3px;font-size:12px;color:#5f6f58;"><?= htmlspecialchars(passenger_t('home.route_map_choose_dropoff', 'Choose where you want to get off'), ENT_QUOTES, 'UTF-8') ?></div>
            <div id="routeMapAreaStats" style="margin-top:4px;">-</div>
            <div id="routeMapInfoPanel" style="margin-top:4px;">
                <div id="routeMapTripInfo" style="display:flex;flex-wrap:wrap;gap:6px 16px;font-size:12px;color:#24513c;overflow-wrap:anywhere;"></div>
            </div>
        </div>
        <div id="routeMapMapWrap" style="position:relative;display:none;">
            <div id="routeMapCanvas" style="height:360px;border-radius:12px;overflow:hidden;border:1px solid #dde5d4;background:#f4f8ef;"></div>
            <div id="routeMapSearchWrap" style="position:absolute;top:10px;left:10px;right:56px;z-index:1100;">
                <input type="search" id="routeMapSearchInput" autocomplete="off"
                    placeholder="<?= htmlspecialchars(passenger_t('home.route_map_search_placeholder', 'Search places in this destination'), ENT_QUOTES, 'UTF-8') ?>"
                    style="width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #dde5d4;border-radius:10px;font-size:13px;color:#24513c;background:rgba(255,255,255,.97);box-shadow:0 4px 14px rgba(0,0,0,.12);">
                <div id="routeMapSearchResults" style="display:none;position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:1100;background:#ffffff;border:1px solid #dde5d4;border-radius:10px;box-shadow:0 8px 20px rgba(0,0,0,.12);max-height:40vh;overflow:auto;"></div>
            </div>
            <div id="routeMapDropoffCard" style="position:absolute;left:8px;right:8px;bottom:8px;z-index:1000;background:rgba(255,255,255,.97);border:1px solid #dde5d4;border-radius:12px;padding:10px 12px;box-shadow:0 6px 18px rgba(0,0,0,.14);">
                <div id="routeMapDropoffCardTitle" style="font-size:10px;font-weight:700;color:#7b8a74;letter-spacing:.4px;text-transform:uppercase;"><?= htmlspecialchars(passenger_t('home.route_map_where_get_off', 'Where will you get off?'), ENT_QUOTES, 'UTF-8') ?></div>
                <div id="routeMapDropoffCardValue" style="margin-top:3px;font-size:13px;font-weight:700;color:#24513c;overflow-wrap:anywhere;"><?= htmlspecialchars(passenger_t('home.route_map_choose_hint', 'Choose a point along the highlighted route.'), ENT_QUOTES, 'UTF-8') ?></div>
                <div id="routeMapDropoffCardSub" style="display:none;margin-top:1px;font-size:11px;color:#7b8a74;"><?= htmlspecialchars(passenger_t('home.route_map_selected_route_point', 'Selected route point'), ENT_QUOTES, 'UTF-8') ?></div>
                <button type="button" id="routeMapDropoffConfirmBtn" disabled style="margin-top:8px;width:100%;padding:10px 12px;border:0;border-radius:10px;background:#47613b;color:#ffffff;font-size:13px;font-weight:600;opacity:.55;">
                    <?= htmlspecialchars(passenger_t('home.route_map_select_dropoff', 'Select a drop-off point'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
            <div id="routeMapMessage" style="position:absolute;left:12px;right:12px;top:10px;z-index:1200;display:none;padding:8px 12px;border-radius:10px;background:rgba(255,255,255,.97);border:1px solid #dde5d4;color:#47613b;font-size:12px;font-weight:600;text-align:center;box-shadow:0 4px 14px rgba(0,0,0,.14);"></div>
        </div>
        <div id="routeMapPlaceholder" style="display:none;padding:14px;border-radius:12px;background:#f8faf7;border:1px dashed #dde5d4;color:#7b8a74;font-size:12px;text-align:center;">
            <?= htmlspecialchars(passenger_t('home.route_map_no_destination', 'Select a destination to see the map'), ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>
         [DISABLED] "YOUR ROUTE" SECTION (END)
         ======================================================== -->

        <hr style="margin:16px 0 14px; border:0; border-top:1px solid #eef2ec;">

<div style="
    margin:0 0 8px;
">

    <div id="fareCard" style="
        background:linear-gradient(
            135deg,
            #47613b,
            #5b774d
        );
        color:#fff;
        border-radius:14px;
        padding:10px 14px;
        display:flex;
        flex-wrap:wrap;
        gap:4px 12px;
        justify-content:space-between;
        align-items:center;
        box-shadow:0 4px 12px rgba(71,97,59,.15);
        min-height:46px;
    ">

        <div style="
            font-size:11px;
            opacity:.85;
            text-transform:uppercase;
            letter-spacing:1px;
            white-space:nowrap;
        ">
            Total Fare
        </div>

        <div id="fareRate" style="
            font-size:15px;
            font-weight:700;
            line-height:1.25;
            min-width:0;
            flex:1 1 auto;
            text-align:right;
            overflow-wrap:anywhere;
        ">
            --
        </div>
        <div id="regularFareBreakdown" style="display:none;flex:0 0 100%;border-top:1px solid rgba(255,255,255,.25);margin-top:6px;padding-top:8px;font-size:11px;"></div>
        <div id="regularPartyPayment" role="status" style="display:none;flex:0 0 100%;font-size:12px;line-height:1.5;padding-top:8px;"></div>

    </div>

</div>
</div>
</div>

<div id="scanStatus" style="display:none;text-align:center;font-weight:600;margin-bottom:8px;">
    Scanning...
</div>

<div style="position:relative;">

<style>
.qr-trip-card{
    display:none;
    background:#fff;
    border:1.5px solid #c6d48c;
    border-radius:16px;
    padding:13px;
    margin:8px 0;
    box-shadow:0 4px 14px rgba(71,97,59,.08);
    font-family:'Poppins',sans-serif;
}
.qr-trip-head{display:flex;align-items:center;gap:8px;}
.qr-trip-badge{
    width:30px;height:30px;border-radius:10px;
    background:#eef4e6;border:1px solid #d5e4c6;color:#2f6b43;
    display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;
}
.qr-trip-title{font-size:13px;font-weight:800;color:#24513c;line-height:1.15;}
.qr-trip-sub{font-size:10px;color:#7b8a74;font-weight:600;margin-top:1px;}
.qr-trip-check{
    margin-left:auto;width:22px;height:22px;border-radius:50%;
    background:#2f6b43;color:#fff;display:flex;align-items:center;justify-content:center;
    font-size:11px;flex-shrink:0;
}
.qr-trip-bus{
    margin-top:10px;background:#f4f8ef;border:1px solid #dce8d2;
    border-radius:12px;padding:9px 11px;
}
.qr-trip-bus-label{
    display:flex;align-items:center;gap:5px;
    font-size:10px;font-weight:700;color:#6b8e6b;
    text-transform:uppercase;letter-spacing:.5px;
}
.qr-trip-bus-value{
    margin-top:2px;font-size:18px;font-weight:800;color:#24513c;
    letter-spacing:-.01em;line-height:1.15;overflow-wrap:anywhere;
}
.qr-trip-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;}
.qr-trip-block{
    background:#fbfcf9;border:1px solid #eef2ea;border-radius:10px;
    padding:8px 10px;min-width:0;
}
.qr-trip-block-label{
    display:flex;align-items:center;gap:4px;
    font-size:9.5px;font-weight:700;color:#8a9a8a;
    text-transform:uppercase;letter-spacing:.4px;
}
.qr-trip-block-value{
    margin-top:2px;font-size:12px;font-weight:700;color:#24513c;
    line-height:1.3;overflow-wrap:anywhere;word-break:break-word;
}
.qr-trip-chip{
    display:inline-block;font-size:11px;font-weight:700;color:#24513c;
    background:#eef4e6;border:1px solid #dce8cd;border-radius:999px;
    padding:3px 12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;
}
.camera-card.qr-trip-shell{
    background:#fff;
    border:1.5px solid #c6d48c;
    border-radius:16px;
    padding:13px;
    box-shadow:0 4px 14px rgba(71,97,59,.08);
}
.qr-trip-status{
    margin-left:auto;font-size:10.5px;font-weight:700;color:#2f6b43;
    background:#eef4e6;border:1px solid #d5e4c6;border-radius:999px;
    padding:4px 10px;white-space:nowrap;flex-shrink:0;
}
.qr-trip-arrow{
    font-size:12px;line-height:1;flex-shrink:0;width:24px;height:24px;
    display:flex;align-items:center;justify-content:center;border-radius:50%;
    background:#eef2e8;border:1px solid #dfe8d4;color:#5d8550;transition:.25s;
}
.qr-trip-divider{height:1px;background:#eef2ec;margin:10px 0 8px;}
.qr-trip-note{
    display:flex;gap:5px;font-size:10.5px;font-weight:600;
    color:#6b8a6b;line-height:1.45;
}
.qr-trip-note i{margin-top:1px;flex-shrink:0;}
.qr-trip-cta{
    margin-top:10px;width:100%;border:none;border-radius:10px;padding:10px;
    background:linear-gradient(135deg,#47613b,#5d8550);color:#fff;
    font-family:'Poppins',sans-serif;font-size:13px;font-weight:800;
    display:inline-flex;align-items:center;justify-content:center;gap:6px;
    cursor:pointer;box-shadow:0 4px 12px rgba(71,97,59,.18);
}
.qr-trip-cta:active{transform:scale(.99);}
</style>

<div id="readyBoardCard" class="qr-trip-card">

    <div class="qr-trip-head">
        <span class="qr-trip-badge"><i class="bi bi-qr-code"></i></span>
        <div>
    <div class="qr-trip-title"><?= htmlspecialchars(passenger_t('home.trip_detected', 'Trip Detected'), ENT_QUOTES, 'UTF-8') ?></div>
    <div class="qr-trip-sub"><?= htmlspecialchars(passenger_t('home.qr_success', 'QR scan successful'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <span class="qr-trip-check"><i class="bi bi-check-lg"></i></span>
    </div>

    <div class="qr-trip-bus">
        <div class="qr-trip-bus-label"><i class="bi bi-bus-front"></i> Bus</div>
        <div id="readyBus" class="qr-trip-bus-value">—</div>
    </div>

    <div class="qr-trip-grid">
        <div class="qr-trip-block">
            <div class="qr-trip-block-label"><i class="bi bi-person-badge"></i> Driver</div>
            <div id="readyDriver" class="qr-trip-block-value">—</div>
        </div>
        <div class="qr-trip-block">
            <div class="qr-trip-block-label"><i class="bi bi-ticket-detailed"></i> Trip</div>
            <div id="readyTrip" class="qr-trip-block-value">—</div>
        </div>
    </div>

    <div class="qr-trip-divider"></div>
    <div class="qr-trip-note">
        <i class="bi bi-info-circle"></i>
        <span>Select your destination and payment method to continue.</span>
    </div>

    <button id="boardNowBtn" class="qr-trip-cta">
        <i class="bi bi-check2-circle"></i> Board Now
    </button>

</div>

<div id="paymentLockCard" class="qr-trip-card" style="display:none;">

    <div class="qr-trip-head">
        <span class="qr-trip-badge" style="background:#fdecec;border:1px solid #f5c6cb;color:#a93226;"><i class="bi bi-wallet2"></i></span>
        <div>
    <div class="qr-trip-title"><?= htmlspecialchars(passenger_t('home.balance_limit', 'Balance Limit Reached'), ENT_QUOTES, 'UTF-8') ?></div>
    <div class="qr-trip-sub" id="paymentLockSub"><?= htmlspecialchars(passenger_t('home.settle_balance', 'Settle your balance to board again'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>

    <div class="qr-trip-note" style="margin-top:10px;">
        <i class="bi bi-info-circle"></i>
        <span id="paymentLockNote">You cannot board another bus until your balance is settled.</span>
    </div>

    <button id="paymentLockBtn" class="qr-trip-cta">
        <i class="bi bi-wallet2"></i> Pay Now
    </button>

</div>

<div id="balanceNoticeCard" class="qr-trip-card" style="display:none;background:#fffdf5;border-color:#f0dca8;">

    <div class="qr-trip-head">
        <span class="qr-trip-badge" style="background:#fff4e0;border:1px solid #f0dca8;color:#c0812b;"><i class="bi bi-exclamation-circle"></i></span>
        <div>
    <div class="qr-trip-title"><?= htmlspecialchars(passenger_t('home.unpaid_balance', 'Unpaid Balance'), ENT_QUOTES, 'UTF-8') ?></div>
    <div class="qr-trip-sub" id="balanceNoticeSub"><?= htmlspecialchars(passenger_t('home.settle_soon', 'Settle your fare soon'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>

    <div class="qr-trip-note" style="margin-top:10px;">
        <i class="bi bi-info-circle"></i>
        <span id="balanceNoticeText">You have an unpaid trip. Please settle it soon.</span>
    </div>

    <button id="balanceNoticeBtn" class="qr-trip-cta">
        <i class="bi bi-wallet2"></i> Pay Now
    </button>

</div>

    <div id="onBoardCard" style="
    display:none;
    flex-direction:column;
    align-items:stretch;
    background:#fff;
    border:1px solid #c6d48c;
    border-radius:14px;
    padding:14px;
    margin-top:14px;
    box-shadow:0 1px 4px rgba(0,0,0,.05);
">
        <div style="
            display:flex;
            flex-direction:column;
            align-items:center;
            text-align:center;
        ">
            <div style="
                width:30px;
                height:30px;
                flex-shrink:0;
                border-radius:50%;
                background:linear-gradient(135deg,#47613b,#6a8f5a);
                display:flex;
                align-items:center;
                justify-content:center;
            ">
                <i class="bi bi-bus-front-fill" style="font-size:14px;color:#fff;line-height:1;"></i>
            </div>
    <div style="font-size:14px;font-weight:800;color:#24513c;line-height:1.2;margin-top:8px;"><?= htmlspecialchars(passenger_t('home.trip_active', 'Trip Active'), ENT_QUOTES, 'UTF-8') ?></div>
    <div style="font-size:11px;color:#7b8a74;margin-top:2px;"><?= htmlspecialchars(passenger_t('home.currently_onboard', 'You are currently on board'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    <div style="margin-top:12px;">
        <div style="
            font-size:10px;
            font-weight:600;
            letter-spacing:1.5px;
            color:#96a394;
            text-transform:uppercase;
            padding:0 0 4px;
        "><?= htmlspecialchars(passenger_t('home.current_trip', 'Current Trip'), ENT_QUOTES, 'UTF-8') ?></div>
        <div style="display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-top:1px solid #eef2ec;">
            <i class="bi bi-bus-front" style="font-size:13px;color:#83966a;flex-shrink:0;width:20px;text-align:center;margin-top:2px;"></i>
            <span style="font-size:11px;color:#96a394;flex:0 0 80px;padding-top:2px;">Bus</span>
            <span id="activeBusNo" style="flex:1;min-width:0;text-align:right;font-size:14px;font-weight:700;color:#24513c;line-height:1.35;overflow-wrap:anywhere;word-break:break-word;">—</span>
        </div>
        <div style="display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-top:1px solid #eef2ec;">
            <i class="bi bi-person-badge" style="font-size:13px;color:#83966a;flex-shrink:0;width:20px;text-align:center;margin-top:2px;"></i>
            <span style="font-size:11px;color:#96a394;flex:0 0 80px;padding-top:2px;">Driver</span>
            <span id="activeDriver" style="flex:1;min-width:0;text-align:right;font-size:14px;font-weight:700;color:#24513c;line-height:1.35;overflow-wrap:anywhere;word-break:break-word;">—</span>
        </div>
        <div style="display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-top:1px solid #eef2ec;">
            <i class="bi bi-geo-alt" style="font-size:13px;color:#83966a;flex-shrink:0;width:20px;text-align:center;margin-top:2px;"></i>
            <span style="font-size:11px;color:#96a394;flex:0 0 80px;padding-top:2px;">Boarding</span>
            <span id="activeBoarding" style="flex:1;min-width:0;text-align:right;font-size:14px;font-weight:700;color:#24513c;line-height:1.35;overflow-wrap:anywhere;word-break:break-word;">—</span>
        </div>
        <div style="display:flex;align-items:flex-start;gap:12px;padding:12px 0 0;border-top:1px solid #eef2ec;">
            <i class="bi bi-flag" style="font-size:13px;color:#83966a;flex-shrink:0;width:20px;text-align:center;margin-top:2px;"></i>
            <span style="font-size:11px;color:#96a394;flex:0 0 80px;padding-top:2px;">Destination</span>
            <span id="activeDestination" style="flex:1;min-width:0;text-align:right;font-size:14px;font-weight:700;color:#24513c;line-height:1.35;overflow-wrap:anywhere;word-break:break-word;">—</span>
        </div>
    </div>
    <div style="margin-top:12px;display:flex;gap:6px;align-items:flex-start;font-size:11px;color:#7b8a74;line-height:1.45;">
        <i class="bi bi-info-circle" style="font-size:12px;flex-shrink:0;margin-top:1px;color:#83966a;"></i>
        <span>Select your destination above &mdash; your fare is calculated automatically. The driver handles your drop-off and payment.</span>
    </div>
    </div>
    <div class="scanner-viewport-wrap">
        <div id="reader"></div>
        <div class="scan-box"></div>
    </div>
    <button id="cameraToggle" style="
        display:none;
        width:100%;
        margin-top:12px;
        border:1px solid #c6d48c;
        border-radius:10px;
        background:#fff;
        color:#24513c;
        font-weight:600;
        font-size:13px;
        padding:10px;
        cursor:pointer;
        touch-action:manipulation;
    ">
        <i class="bi bi-camera-reverse-fill" style="margin-right:6px;color:#47613b;"></i><?= htmlspecialchars(passenger_t('home.switch_camera', 'Switch camera'), ENT_QUOTES, 'UTF-8') ?>
    </button>
    <div id="noCameraFallback" style="display:none;flex-direction:column;align-items:center;justify-content:center;padding:40px 20px;text-align:center;background:#f8faf7;border-radius:15px;">
        <i class="bi bi-camera-video-off" style="font-size:48px;color:#bbb;margin-bottom:12px;"></i>
        <div style="font-size:16px;font-weight:700;color:#555;margin-bottom:4px;"><?= htmlspecialchars(passenger_t('home.camera_unavailable', 'Camera Not Available'), ENT_QUOTES, 'UTF-8') ?></div>
        <div style="font-size:12px;color:#999;margin-bottom:16px;"><?= htmlspecialchars(passenger_t('home.camera_unavailable_body', 'Unable to access your camera. You can enter the bus code manually.'), ENT_QUOTES, 'UTF-8') ?></div>
        <div style="display:flex;gap:8px;width:100%;max-width:320px;">
            <input type="text" id="manualBusCode" class="form-control form-control-sm" placeholder="<?= htmlspecialchars(passenger_t('home.enter_bus', 'Enter bus number'), ENT_QUOTES, 'UTF-8') ?>" style="text-align:center;font-weight:600;font-size:14px;">
            <button id="manualBoardBtn" class="btn btn-success btn-sm" style="white-space:nowrap;flex-shrink:0;"><?= htmlspecialchars(passenger_t('home.board_bus', 'Board Bus'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
        <div id="manualError" style="font-size:11px;color:#dc3545;margin-top:6px;display:none;"><?= htmlspecialchars(passenger_t('home.enter_bus_error', 'Please enter a bus number'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
</div>
<div id="tripModal" style="
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.4);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:9999;
">

    <div style="
        position:relative;
        width:100%;
        max-width:720px;
    ">

        <button id="closeModalBtn" aria-label="Close receipt" style="
            position:absolute;
            top:6px;
            right:8px;
            z-index:20;
            width:34px;
            height:34px;
            border:none;
            border-radius:50%;
            background:rgba(0,0,0,.4);
            color:#fff;
            font-size:15px;
            font-weight:700;
            line-height:1;
            cursor:pointer;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            box-shadow:0 2px 6px rgba(0,0,0,.2);
            touch-action:manipulation;
        ">
            <i class="bi bi-x-lg"></i>
        </button>

        <div id="tripModalContent" style="width:100%;"></div>

    </div>
</div>
<div id="destinationWarningModal" style="
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.45);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:10050;
">

    <div style="
        width:85%;
        max-width:380px;
        background:#fffef8;
        border:2px solid #c6d48c;
        border-radius:20px;
        padding:22px;
        box-shadow:0 8px 20px rgba(0,0,0,0.15);
        text-align:center;
    ">

        <div style="
            width:54px;
            height:54px;
            border-radius:50%;
            background:#fff4e0;
            border:1px solid #f0dca8;
            color:#c0812b;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:24px;
            margin:0 auto 12px;
        ">
            <i class="bi bi-exclamation-triangle-fill"></i>
        </div>

        <div style="font-size:16px;font-weight:800;color:#24513c;margin-bottom:6px;">
            Select Destination Once
        </div>

        <div style="font-size:13px;color:#555;line-height:1.5;margin-bottom:14px;">
            Your destination can only be set once. Once saved, it cannot be changed.
        </div>

        <label style="display:flex;align-items:flex-start;gap:8px;text-align:left;font-size:12.5px;color:#47613b;line-height:1.45;margin-bottom:10px;cursor:pointer;">
            <input type="checkbox" id="destinationAgreementCheck" style="margin-top:2px;flex-shrink:0;">
            <span><?= htmlspecialchars(passenger_t('home.destination_agreement', 'I understand that if I get off before reaching my selected destination, I will still pay the full fare.'), ENT_QUOTES, 'UTF-8') ?></span>
        </label>
        <div id="destinationAgreementError" style="display:none;color:#c0392b;font-size:12px;font-weight:600;margin-bottom:10px;">
            <?= htmlspecialchars(passenger_t('home.destination_agreement_error', 'Please tick the agreement to continue.'), ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div style="display:flex;gap:8px;">
            <button id="destinationWarningCancel" style="
                flex:1;
                padding:11px;
                border:1px solid #cdd9b5;
                border-radius:30px;
                font-weight:700;
                font-size:13px;
                color:#47613b;
                background:#fff;
                cursor:pointer;
            ">
                Cancel
            </button>
            <button id="destinationWarningContinue" style="
                flex:1;
                padding:11px;
                border:none;
                border-radius:30px;
                font-weight:700;
                font-size:13px;
                color:#fff;
                background:linear-gradient(90deg,#7bbf5e,#5aa943);
                cursor:pointer;
            ">
                Continue
            </button>
        </div>

    </div>

</div>

<div id="paymentReadyModal" style="
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.45);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:10050;
">

    <div style="
        width:85%;
        max-width:380px;
        background:#fffef8;
        border:2px solid #c6d48c;
        border-radius:20px;
        padding:22px;
        box-shadow:0 8px 20px rgba(0,0,0,0.15);
        text-align:center;
    ">

        <div style="
            width:54px;
            height:54px;
            border-radius:50%;
            background:#eef4e6;
            border:1px solid #d5e4c6;
            color:#2f6b43;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:24px;
            margin:0 auto 12px;
        ">
            <i class="bi bi-qr-code-scan"></i>
        </div>

        <div style="font-size:16px;font-weight:800;color:#24513c;margin-bottom:6px;">
            <?= htmlspecialchars(passenger_t('home.ready_to_pay', 'Ready to Pay?'), ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div style="font-size:13px;color:#555;line-height:1.5;margin-bottom:18px;">
            Pay your fare now with a QR Ph code while you are on board.
        </div>

        <div style="display:flex;gap:8px;">
            <button id="paymentReadyLater" style="
                flex:1;
                padding:11px;
                border:1px solid #cdd9b5;
                border-radius:30px;
                font-weight:700;
                font-size:13px;
                color:#47613b;
                background:#fff;
                cursor:pointer;
            ">
                Not now
            </button>
            <button id="paymentReadyNow" style="
                flex:1;
                padding:11px;
                border:none;
                border-radius:30px;
                font-weight:700;
                font-size:13px;
                color:#fff;
                background:linear-gradient(90deg,#7bbf5e,#5aa943);
                cursor:pointer;
            ">
                Pay Now
            </button>
        </div>

    </div>

</div>

<div id="paymentMethodConfirmModal" style="
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.45);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:10055;
">

    <div style="
        width:85%;
        max-width:380px;
        background:#fffef8;
        border:2px solid #c6d48c;
        border-radius:20px;
        padding:22px;
        box-shadow:0 8px 20px rgba(0,0,0,0.15);
        text-align:center;
    ">

        <div style="
            width:54px;
            height:54px;
            border-radius:50%;
            background:#eef4e6;
            border:1px solid #d5e4c6;
            color:#2f6b43;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:24px;
            margin:0 auto 12px;
        ">
            <i class="bi bi-wallet2"></i>
        </div>

        <div style="font-size:16px;font-weight:800;color:#24513c;margin-bottom:6px;">
            <?= htmlspecialchars(passenger_t('home.payment_method_prompt_title', 'Use this payment method for your next transactions?'), ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div style="font-size:13px;color:#555;line-height:1.5;margin-bottom:18px;">
            <?= htmlspecialchars(passenger_t('home.payment_method_prompt_body', 'Selected payment method'), ENT_QUOTES, 'UTF-8') ?>:
            <strong id="paymentMethodConfirmChoice" style="color:#24513c;">—</strong>
        </div>

        <div style="display:flex;flex-direction:column;gap:8px;">
            <button id="paymentMethodConfirmYes" style="
                width:100%;
                padding:12px;
                border:none;
                border-radius:30px;
                font-weight:800;
                font-size:13px;
                color:#fff;
                background:linear-gradient(90deg,#7bbf5e,#5aa943);
                cursor:pointer;
            ">
                <?= htmlspecialchars(passenger_t('home.payment_method_prompt_yes', 'Yes, use it next time'), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button id="paymentMethodConfirmNo" style="
                width:100%;
                padding:12px;
                border:1px solid #cdd9b5;
                border-radius:30px;
                font-weight:700;
                font-size:13px;
                color:#47613b;
                background:#fff;
                cursor:pointer;
            ">
                <?= htmlspecialchars(passenger_t('home.payment_method_prompt_no', 'No, only for this transaction'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>

    </div>

</div>

<div id="activeQrModal" style="
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.45);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:10060;
">

    <div style="
        width:85%;
        max-width:380px;
        background:#fffef8;
        border:2px solid #c6d48c;
        border-radius:20px;
        padding:22px;
        box-shadow:0 8px 20px rgba(0,0,0,0.15);
        text-align:center;
    ">

        <div style="
            width:54px;
            height:54px;
            border-radius:50%;
            background:#eef4e6;
            border:1px solid #d5e4c6;
            color:#2f6b43;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:24px;
            margin:0 auto 12px;
        ">
            <i class="bi bi-qr-code"></i>
        </div>

        <div style="font-size:16px;font-weight:800;color:#24513c;margin-bottom:6px;">
            You Have an Active QR Ph Code
        </div>

        <div style="font-size:13px;color:#555;line-height:1.5;margin-bottom:18px;">
            This trip is already set to Online Payment and its QR Ph code is still active. Pay it with your app, or wait for it to expire before switching to Cash.
        </div>

        <div style="display:flex;gap:8px;">
            <button id="activeQrKeep" style="
                flex:1;
                padding:11px;
                border:1px solid #cdd9b5;
                border-radius:30px;
                font-weight:700;
                font-size:13px;
                color:#47613b;
                background:#fff;
                cursor:pointer;
            ">
                Keep Online Payment
            </button>
            <button id="activeQrView" style="
                flex:1;
                padding:11px;
                border:none;
                border-radius:30px;
                font-weight:700;
                font-size:13px;
                color:#fff;
                background:linear-gradient(90deg,#7bbf5e,#5aa943);
                cursor:pointer;
            ">
                View QR Code
            </button>
        </div>

    </div>

</div>

<div id="installPrompt" style="
    position:fixed;
    left:15px;
    right:15px;
    bottom:75px;
    padding:12px 14px;
    padding-right:40px;
    background:rgba(71,97,59,.92);
    backdrop-filter:blur(12px);
    -webkit-backdrop-filter:blur(12px);
    border:1px solid rgba(255,255,255,.12);
    border-radius:16px;
    box-shadow:0 8px 25px rgba(0,0,0,.18);
    color:#fff;
    z-index:99999;
    display:none;
">

    <button id="closeInstallPrompt" style="
        position:absolute;
        top:10px;
        right:10px;
        width:22px;
        height:22px;
        border:none;
        border-radius:50%;
        background:rgba(255,255,255,.12);
        color:#fff;
        font-size:12px;
        font-weight:bold;
        cursor:pointer;
        z-index:2;
    ">
        ✕
    </button>

    <div style="
        display:flex;
        align-items:center;
        gap:10px;
    ">

        <div style="
            width:40px;
            height:40px;
            min-width:40px;
            border-radius:12px;
            background:rgba(255,255,255,.15);
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:18px;
        ">
            📱
        </div>

        <div style="flex:1;">

            <div style="
                font-size:13px;
                font-weight:600;
            ">
                Add to Home Screen
            </div>

            <div style="
                font-size:11px;
                opacity:.85;
                margin-top:2px;
            ">
                Open the passenger app instantly for faster boarding.
            </div>

        </div>

        <button id="installBtn" style="
            border:none;
            background:#fff;
            color:#47613b;
            font-size:11px;
            font-weight:700;
            padding:8px 14px;
            border-radius:999px;
            cursor:pointer;
            white-space:nowrap;
        ">
            Install
        </button>

    </div>

</div>
<style>
@keyframes fadeUp {
    from { transform: translateY(20px); opacity:0; }
    to { transform: translateY(0); opacity:1; }
}
</style>
</div>
</div>
</div>

<?php if ($passengerQrIntro): ?>

<div id="passengerQrIntroModal" style="
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.5);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:99999;
    padding:16px;
">
    <div style="
        width:100%;
        max-width:400px;
        background:#fef8f3;
        border:2px solid #c6d48c;
        border-radius:20px;
        padding:24px 18px;
        box-shadow:0 8px 20px rgba(0,0,0,0.08);
        animation:fadeUp 0.25s ease;
        text-align:center;
        max-height:92vh;
        overflow-y:auto;
    ">
        <div style="display:flex;justify-content:center;margin-bottom:10px;">
            <div style="width:54px;height:54px;border-radius:14px;background:linear-gradient(135deg,#47613b,#6a8f5a);display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-qr-code" style="font-size:24px;color:#fff;"></i>
            </div>
        </div>

        <div style="font-size:19px;font-weight:700;color:#24513c;font-family:'Poppins',sans-serif;line-height:1.3;">
            <?= htmlspecialchars(passenger_t('qr.intro_title', 'Your Passenger QR is Ready'), ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div style="font-size:13px;color:#6c757d;margin:8px auto 6px;max-width:300px;line-height:1.5;font-family:'Poppins',sans-serif;">
            <?= htmlspecialchars(passenger_t('qr.intro_body', 'Please download or print this QR code. You can use it as your passenger identification when boarding, even when you are offline.'), ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div style="
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:6px;
            background:#eef4e6;
            border:1px solid #d5e4c6;
            border-radius:30px;
            padding:8px 14px;
            margin:0 auto 16px;
            max-width:300px;
            font-size:12px;
            color:#2f6b43;
            font-family:'Poppins',sans-serif;
            line-height:1.4;
        ">
            <i class="bi bi-person-badge" style="font-size:13px;flex-shrink:0;"></i>
            <span><?= htmlspecialchars(passenger_t('qr.intro_note', 'You can also view your QR code anytime from your Profile.'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <?php
        $passengerQrContainerId = "passengerQrIntroCanvas";
        $passenger = $passengerQr;
        include __DIR__ . "/../PassengerQr/passenger_qr_card.php";
        ?>

        <button id="passengerQrContinueBtn" type="button" style="
            width:100%;
            margin-top:16px;
            padding:13px 16px;
            border:none;
            border-radius:30px;
            font-family:'Poppins',sans-serif;
            font-size:14px;
            font-weight:700;
            color:#fff;
            background:linear-gradient(135deg,#47613b,#5d8550);
            cursor:pointer;
            box-shadow:0 4px 12px rgba(71,97,59,.25);
            touch-action:manipulation;
        ">
            <?= htmlspecialchars(passenger_t('qr.continue', 'Continue to Homepage'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . "/../layout/navbar.php"; ?>
<?php if (!empty($conn) && $conn instanceof PDO) { $passengerNotificationAlert = true; include __DIR__ . "/../layout/account-notifications.php"; } ?>

<script>
window.PASSENGER_DEST_ALERT_STRINGS = {
    nearTitle: <?= json_encode(passenger_t('dest_alert.near_title', 'Destination Nearby'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    nearLead: <?= json_encode(passenger_t('dest_alert.near_lead', 'Your destination is nearby.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    nearBody: <?= json_encode(passenger_t('dest_alert.near_body', 'Please prepare to get off at:'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    reachedTitle: <?= json_encode(passenger_t('dest_alert.reached_title', 'Destination Reached'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    reachedBody: <?= json_encode(passenger_t('dest_alert.reached_body', 'The bus is now near your destination:'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    reachedHint: <?= json_encode(passenger_t('dest_alert.reached_hint', 'Please prepare to get off.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    passedTitle: <?= json_encode(passenger_t('dest_alert.passed_title', 'Destination May Have Been Passed'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    passedBody: <?= json_encode(passenger_t('dest_alert.passed_body', 'Your selected destination was:'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    passedLead: <?= json_encode(passenger_t('dest_alert.passed_lead', 'The bus may have passed your selected destination.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    passedHint: <?= json_encode(passenger_t('dest_alert.passed_hint', 'Please notify the driver if you still need to get off.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    notifyNear: <?= json_encode(passenger_t('dest_alert.notify_near', 'Your destination is nearby.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    notifyReached: <?= json_encode(passenger_t('dest_alert.notify_reached', 'You are near your destination.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    notifyPassed: <?= json_encode(passenger_t('dest_alert.notify_passed', 'Your destination may have been passed.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    continueLabel: <?= json_encode(passenger_t('home.continue_journey', 'Continue Journey'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    dismissLabel: <?= json_encode(passenger_t('dest_alert.dismiss', 'Dismiss'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    unknownDestination: <?= json_encode(passenger_t('dest_alert.unknown_destination', 'Your selected destination'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};
</script>
<script src="/assets/js/passenger/destination-alerts.js"></script>

<script type="module">

let html5QrCode = null;
let isProcessing = false;
let scannerRunning = false;

let cameraList = [];
let cameraIndex = 0;

let userLat = null;
let userLng = null;
let userAccuracy = null;
let currentBoardingPoint = '';
let lockedBoardingPoint = null;

let activeDiscountType = 'regular';

let onBoard = false;
let scanCooldownUntil = 0;

// Boarding is locked while a finished trip is still unpaid.
let paymentLocked = false;
let paymentLockedTripId = null;
let paymentLockedMethod = '';
let paymentLockedInfo = null;
let paymentLockedPayableTripId = null;
let paymentLockTimer = null;
let balanceNoticeShown = false;
let balanceNoticeInfo = null;

if (window.PassengerSession) {
    window.PassengerSession.onInvalidate(function() {
        try { stopLocationTracking(); } catch(e) {}
        try { stopActiveTripPolling(); } catch(e) {}
        if (paymentLockTimer) {
            clearInterval(paymentLockTimer);
            paymentLockTimer = null;
        }
        if (typeof hideCameraToggle === 'function') {
            hideCameraToggle();
        }
        if (typeof html5QrCode !== 'undefined' && html5QrCode && scannerRunning) {
            try { html5QrCode.stop(); } catch(e) {}
            scannerRunning = false;
        }
        isProcessing = false;
    });
}

const HOMEPAGE_STATE_KEY = (window.PassengerStorage && window.PassengerStorage.KEYS && window.PassengerStorage.KEYS.homepageState) || 'passenger_homepage_state';
let restoringHomepageState = true;
let _restoreGuardUntil = Date.now() + 15000;

function saveHomepageState() {
    if (restoringHomepageState && Date.now() < _restoreGuardUntil) {
        return;
    }
    restoringHomepageState = false;
    const state = {
        owner_passenger_id: Number(window.PASSENGER_SESSION_ID || 0),
        paymentMethod: document.getElementById('paymentMethod')?.value || 'cash',
        cashAmount: document.getElementById('cashAmountInput')?.value || '0',
        gcashAmount: document.getElementById('gcashAmountInput')?.value || '0',

        tripDetailsOpen: (document.getElementById('tripDetails')?.style.display === 'block')
    };
    try {
        localStorage.setItem(HOMEPAGE_STATE_KEY, JSON.stringify(state));
    } catch (err) {
        console.error('saveHomepageState failed:', err);
    }
}

function loadHomepageState() {
    try {
        const raw = localStorage.getItem(HOMEPAGE_STATE_KEY);
        if (!raw) {
            return null;
        }
        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object') {
            return null;
        }
        /* A cache from a different passenger session is dropped, never shown. */
        if (Number(parsed.owner_passenger_id || 0) !== Number(window.PASSENGER_SESSION_ID || 0)) {
            clearHomepageState();
            return null;
        }
        return parsed;
    } catch (err) {
        clearHomepageState();
        return null;
    }
}

function clearHomepageState() {
    try {
        localStorage.removeItem(HOMEPAGE_STATE_KEY);
    } catch (err) {
        console.error('clearHomepageState failed:', err);
    }
}

const TRIP_INFO_KEY = (window.PassengerStorage && window.PassengerStorage.KEYS && window.PassengerStorage.KEYS.activeTripInfo) || 'passenger_active_trip_info';

function formatPassengerTypeLabel(value){
    if(value === null || value === undefined || value === ''){
        return null;
    }
    const t = String(value).trim();
    if(!t){
        return null;
    }
    return t.toLowerCase()
        .split(/[\s_]+/)
        .filter(w => w.length > 0)
        .map(w => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

function formatPaymentMethodLabel(value){
    const method = String(value || '').trim().toLowerCase();
    if(!method){
        return null;
    }
    if(method === 'gcash'){
        return 'Online Payment';
    }
    if(method === 'hybrid'){
        return 'Online Payment + Cash';
    }
    if(method === 'cash'){
        return 'Cash';
    }
    return value;
}

function saveActiveTripInfo(info){
    if(!info){
        return;
    }
    const data = {
        owner_passenger_id: Number(window.PASSENGER_SESSION_ID || 0),
        bus_no: info.bus_no || info.busNo || null,
        driver_name: info.driver_name || info.driverName || null,
        trip_no: info.trip_no || info.tripNo || null,
        discount_type: info.discount_type || info.passenger_type || null,
        boarding_point: info.boarding_point || info.boardingPoint || null,
        boarding_stop_id: info.boarding_stop_id || null,
        destination: info.selected_destination || info.destination || null,
        trip_id: info.trip_id || info.tripId || null,
        payment_method: info.payment_method || null,
        active: (info.type === 'boarding' || info.active === true)
            ? true
            : (info.type === 'dropoff' ? false : undefined),
        savedAt: Date.now()
    };
    if(data.bus_no === null){
        return;
    }
    try{
        localStorage.setItem(TRIP_INFO_KEY, JSON.stringify(data));
    }catch(err){
        console.error('saveActiveTripInfo failed:', err);
    }
}

window.regularTripDestinationUpdate = function (info) {
    if (!info) { return; }
    const route = info.route || {};
    const destination = String(info.selected_destination || info.destination || route.destination || '').trim();
    if (!destination) { return; }
    const tripId = info.trip_id || window._activeTripId;
    if (window._activeTripId && tripId && String(tripId) !== String(window._activeTripId)) { return; }
    const destinationElement = document.getElementById('activeDestination');
    if (destinationElement) { destinationElement.innerText = destination; }
    try {
        const saved = JSON.parse(localStorage.getItem(TRIP_INFO_KEY) || 'null');
        if (saved && String(saved.trip_id) === String(tripId)) {
            saved.destination = destination;
            saved.selected_destination = destination;
            saved.destination_id = info.selected_destination_id || info.destination_id || route.destination_id || saved.destination_id;
            saved.selected_destination_id = saved.destination_id;
            saved.savedAt = Date.now();
            localStorage.setItem(TRIP_INFO_KEY, JSON.stringify(saved));
        }
    } catch (error) { console.error('Active destination cache update failed:', error); }
};

/* Applies the server's answer for "do I have an active trip?" to the local
   caches. `null` means the server could not be reached: cached trip data must
   not render, and a retry affordance is shown instead. */
function handleActiveTripSnapshot(tripData){
    if(tripData === null){
        showTripStatusRetry();
        return 'unknown';
    }

    if(tripData.hasActiveTrip){
        return 'live';
    }

    /* The server confirmed there is no active trip: drop the cached trip and
       payment-progress values instead of showing stale data. */
    try{ localStorage.removeItem(TRIP_INFO_KEY); }catch(err){}
    try{ clearHomepageState(); }catch(err){}
    return 'none';
}

/* Small retry affordance for a failed active-trip check. */
function showTripStatusRetry(){
    let box = document.getElementById('tripStatusRetry');
    if(box){
        box.style.display = 'block';
        return;
    }
    box = document.createElement('div');
    box.id = 'tripStatusRetry';
    box.style.cssText = 'margin:10px auto;text-align:center;font-size:12.5px;color:#8a3b3b;';
    box.innerHTML = 'Could not check your trip status. ' +
        '<button type="button" id="tripStatusRetryBtn" style="margin-left:6px;padding:4px 10px;border-radius:8px;border:1px solid #d9c2c2;background:#fff;color:#8a3b3b;font-weight:700;cursor:pointer;">Retry</button>';
    const mount = document.querySelector('.scan-box') || document.body;
    mount.appendChild(box);
    const btn = document.getElementById('tripStatusRetryBtn');
    if(btn){
        btn.addEventListener('click', function(){
            box.style.display = 'none';
            window.location.reload();
        });
    }
}

async function restoreActiveTripInfo(){
    let tripData = null;
    try{
        const raw = localStorage.getItem(TRIP_INFO_KEY);
        if(raw){
            tripData = JSON.parse(raw);
        }
    }catch(err){
        tripData = null;
    }
    if(!tripData){
        return;
    }

    /* A trip cached by a different passenger session is dropped, not shown. */
    if(Number(tripData.owner_passenger_id || 0) !== Number(window.PASSENGER_SESSION_ID || 0)){
        try{ localStorage.removeItem(TRIP_INFO_KEY); }catch(err){}
        return;
    }

    if(tripData.active === false){
        try{ localStorage.removeItem(TRIP_INFO_KEY); }catch(err){}
        return;
    }

    try{
        const res = await fetch('/passenger/trip/active');
        const data = await res.json();
        if(!data || !data.hasActiveTrip){
            /* Server confirms no active trip: drop trip AND payment-progress
               caches through the shared snapshot handler. */
            handleActiveTripSnapshot(data || { hasActiveTrip: false });
            return;
        }
        tripData = Object.assign({}, tripData, data);
    }catch(err){
        /* Network failure: never render cached trip data as current server
           truth; the retry affordance covers the recovery path. */
        showTripStatusRetry();
        return;
    }
    ['busInfo','activeBusNo'].forEach(id => {
        const el = document.getElementById(id);
        if(el && (el.innerText === '—' || el.innerText === '-' || el.innerText === '')){
            el.innerText = tripData.bus_no || el.innerText;
        }
    });
    ['driverInfo','activeDriver'].forEach(id => {
        const el = document.getElementById(id);
        if(el && (el.innerText === '—' || el.innerText === '-' || el.innerText === '')){
            el.innerText = tripData.driver_name || el.innerText;
        }
    });
    const tripNoEl = document.getElementById('tripNoInfo');
    if(tripNoEl && (tripNoEl.innerText === '—' || tripNoEl.innerText === '-' || tripNoEl.innerText === '')){
        tripNoEl.innerText = tripData.trip_no || tripNoEl.innerText;
    }
    const typeEl = document.getElementById('passengerType');
    if(typeEl){
        const serverType = formatPassengerTypeLabel(tripData.discount_type);
        if(serverType){
            typeEl.innerText = serverType;
        }else if(typeEl.innerText === '—' || typeEl.innerText === '-' || typeEl.innerText === ''){
            typeEl.innerText = 'Regular';
        }
    }
    const boardEl = document.getElementById('activeBoarding');
    if(boardEl && (boardEl.innerText === '—' || boardEl.innerText === '-' || boardEl.innerText === '')){
        boardEl.innerText = tripData.boarding_point || boardEl.innerText;
    }
    const destEl = document.getElementById('activeDestination');
    if(destEl){

        const destName = tripData.selected_destination || tripData.destination || '';
        if(destName){ destEl.innerText = destName; }
    }

    if(tripData.bus_no){
        const tdEl = document.getElementById('tripDetails');
        if(tdEl && tdEl.style.display !== 'block'){
            tdEl.style.display = 'block';
        }
        const arrowEl = document.getElementById('tripArrow');
        if(arrowEl){ arrowEl.textContent = '▲'; }
    }
}

function restoreHomepageState() {
    const saved = loadHomepageState();
    if (!saved) {
        return;
    }

    if (saved.paymentMethod && !paymentMethodPreferenceLocked) {
        // Restored as a draft selection only: it still needs confirmation
        // before it is applied to boarding or payment.
        setPaymentMethodSelection(saved.paymentMethod);
    }

    const cashEl = document.getElementById('cashAmountInput');
    if (cashEl && saved.cashAmount !== undefined && saved.cashAmount !== null) {
        cashEl.value = saved.cashAmount;
    }

    const gcashEl = document.getElementById('gcashAmountInput');
    if (gcashEl && saved.gcashAmount !== undefined && saved.gcashAmount !== null) {
        gcashEl.value = saved.gcashAmount;
    }

    if (saved.tripDetailsOpen === true) {
        const details = document.getElementById('tripDetails');
        const arrow = document.getElementById('tripArrow');
        if (details) {
            details.style.display = 'block';
        }
        if (arrow) {
            arrow.textContent = '▲';
        }
    }

    updatePaymentMethodLockState();
}

let _saveStateTimer = null;
async function saveTripState(stateObj) {
    try {
        await fetch('/passenger/trip/save-state', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                state: stateObj,
                version: Date.now()
            })
        });
    } catch (err) {
        console.error('saveTripState failed:', err);
    }
}
function debouncedSaveTripState(stateObj, ms = 300) {
    if (_saveStateTimer) clearTimeout(_saveStateTimer);
    _saveStateTimer = setTimeout(() => saveTripState(stateObj), ms);
}

async function applyRestoredState() {
    try {
        const res = await fetch('/passenger/trip/restore-state');
        const data = await res.json();
        if (data.success && data.hasState && data.state) {
            const s = data.state;
            if (s.payment_method && !paymentMethodPreferenceLocked) {
                setPaymentMethodSelection(s.payment_method);
            }
            if (s.trip_details_open === 'true') {
                document.getElementById('tripDetails').style.display = 'block';
                document.getElementById('tripArrow').textContent = '▲';
            }
            if (s.destination_id) {
                const ds = document.getElementById('destinationSelect');
                if (ds) ds.value = s.destination_id;
            }
            if (s.cash_amount) {
                const el = document.getElementById('cashAmountInput');
                if (el) el.value = s.cash_amount;
            }
            if (s.gcash_amount) {
                const el = document.getElementById('gcashAmountInput');
                if (el) el.value = s.gcash_amount;
            }
            if (s.continuation_token) {
                // Restore the in-flight continuation idempotency token so a
                // reload/reconnect retries the same event instead of creating
                // a second continuation.
                try {
                    localStorage.setItem('rconnect_continuation_token', s.continuation_token);
                } catch (err) {}
            }
            updatePaymentMethodLockState();
            return;
        }
    } catch (err) {}

}

window.PASSENGER_QR_SCAN_DEBUG =
    <?= passenger_qr_scan_debug_enabled() ? 'true' : 'false' ?>;

const QR_BUS =
    <?= json_encode($qrBus) ?>;

const QR_TRIP =
    <?= json_encode($qrTrip) ?>;

if(
    QR_BUS
){

    window.addEventListener(
        "DOMContentLoaded",
        ()=>{

            const card =
                document.getElementById(
                    "readyBoardCard"
                );

            if(card){

                card.style.display =
                    "block";

            }

            const boardBtnEl =
                document.getElementById('boardNowBtn');

            if(boardBtnEl){

                boardBtnEl.disabled = false;

            }

            document.getElementById(
                "readyBus"
            ).textContent =
                QR_BUS;

            const tripNo =
                String(QR_TRIP || '').trim();

            document.getElementById(
                "readyTrip"
            ).textContent =
                tripNo === ''
                    ? '—'
                    : (/^\d+$/.test(tripNo) ? "#" + tripNo : tripNo);

            fetch(
                '/passenger/trip/active',
                {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({bus_no: QR_BUS})
                }
            )
                .then(function(r){ return r.json(); })
                .then(function(info){
                    var driverEl =
                        document.getElementById('readyDriver');
                    if(driverEl && info && info.driver_name){
                        driverEl.textContent = info.driver_name;
                    }
                })
                .catch(function(){});

        }
    );

}

let scanToastTimer = null;

function setStatus(msg, color = "#333") {
    const el = document.getElementById("scanStatus");

    if (el) {
        el.innerText = msg;
        el.style.color = color;
    }

    const toast = document.getElementById("scanToast");

    if (!toast) {
        return;
    }

    const hideToast = () => {
        clearTimeout(scanToastTimer);
        toast.classList.add("scan-toast-hide");
        setTimeout(() => {
            toast.style.display = "none";
            toast.classList.remove("scan-toast-hide");
        }, 250);
    };

    if (!msg) {
        if (toast.style.display !== "none") {
            hideToast();
        }
        return;
    }

    const tones = {
        red: "scan-toast-error",
        orange: "scan-toast-warning",
        green: "scan-toast-success"
    };

    const icons = {
        red: "bi-x-circle",
        orange: "bi-exclamation-triangle",
        green: "bi-check-circle"
    };

    const tone = tones[color] || "scan-toast-neutral";
    const icon = icons[color] || "bi-info-circle";

    toast.classList.remove(
        "scan-toast-neutral",
        "scan-toast-warning",
        "scan-toast-error",
        "scan-toast-success"
    );

    toast.classList.add(tone);

    const iconEl = document.getElementById("scanToastIcon");

    if (iconEl) {
        iconEl.className = "bi " + icon;
    }

    const msgEl = document.getElementById("scanToastMsg");

    if (msgEl) {
        msgEl.innerText = msg;
    }

    toast.style.display = "flex";
    toast.classList.remove("scan-toast-hide");

    clearTimeout(scanToastTimer);
    scanToastTimer = setTimeout(hideToast, 3500);
}

// Builds the status text for a failed scan. In production (flag off) this is
// exactly the server message; with diagnostics on it appends the failed step,
// HTTP status, and exception/SQL detail so the real cause is visible.
function formatScanFailure(result, httpStatus, fallback){

    result = result || {};

    let message =
        result.message ||
        fallback ||
        "Scan failed";

    if (window.PASSENGER_QR_SCAN_DEBUG) {
        console.debug("Scan failure diagnostic:", { result: result, status: httpStatus });
    }

    if (window.PassengerSession && typeof window.PassengerSession.sanitizeErrorMessage === 'function') {
        message = window.PassengerSession.sanitizeErrorMessage(message, fallback || "Scan failed. Please try again.");
    }

    return message;
}

function debug(message){
    return;
}

async function getLocationName(lat, lng){

    try{

        const response =
            await fetch(
                `https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&lat=${lat}&lon=${lng}`
            );

        if(!response.ok){

            throw new Error(
                "Location lookup failed"
            );

        }

        const data =
            await response.json();

        const address =
            data.address || {};

        const barangay =

            address.hamlet ||

            address.village ||

            address.suburb ||

            address.neighbourhood ||

            address.quarter ||

            address.city_district ||

            address.residential ||

            "";

        const city =

            address.city ||

            address.municipality ||

            address.town ||

            address.county ||

            "";

        if(
            barangay &&
            city
        ){

            return `${barangay.trim()}, ${city.trim()}`;

        }

        if(city){

            return city.trim();

        }

        if(barangay){

            return barangay.trim();

        }

        return (
            data.display_name ||
            "Unknown location"
        );

    }catch(err){

        console.error(err);

        return "Unknown location";

    }

}

const DEFAULT_FALLBACK_LOCATION = { lat: 15.47222600, lng: 120.96918200 };
const DEFAULT_FALLBACK_LOCATION_NAME = "Cabanatuan City Terminal (Main terminal)";
let userLocationIsFallback = false;

function applyDefaultLocationFallback(reason){
    userLat = DEFAULT_FALLBACK_LOCATION.lat;
    userLng = DEFAULT_FALLBACK_LOCATION.lng;
    userAccuracy = 99999;
    userLocationIsFallback = true;
    currentBoardingPoint = DEFAULT_FALLBACK_LOCATION_NAME;
    const bpEl = document.getElementById("boardingPoint");
    if(bpEl){
        bpEl.innerText = DEFAULT_FALLBACK_LOCATION_NAME;
    }
    updateFareDisplay();
    if(typeof reason === "string" && reason){
        setStatus(reason, "orange");
    }
}

async function requestLocation(){

    if(
        userLat &&
        userLng
    ){
        return true;
    }

    if(
        !navigator.geolocation
    ){

        applyDefaultLocationFallback(
            "Location not supported — using default location"
        );

        return true;
    }

    return new Promise(
        (resolve)=>{

            navigator.geolocation.getCurrentPosition(

async (pos)=>{

                    userLat =
                        pos.coords.latitude;

                    userLng =
                        pos.coords.longitude;

                    userAccuracy =
                        pos.coords.accuracy
                        ?? null;

                    userLocationIsFallback =
                        false;

                    // A valid GPS fix stands even when the place-name
                    // lookup fails; falling back here would silently
                    // disable destination monitoring.
                    let place =
                        null;

                    try{

                        place =
                            await getLocationName(
                                userLat,
                                userLng
                            );

                    }catch(placeError){

                        place =
                            null;

                    }

                    if(
                        place &&
                        String(place).trim()
                    ){

                        currentBoardingPoint =
                            place;

                    }else if(!currentBoardingPoint){

                        currentBoardingPoint =
                            "Unknown location";

                    }

                    updateFareDisplay();

                    resolve(
                        true
                    );

                },

()=>{

                    applyDefaultLocationFallback(
                        "Location access unavailable — using default location"
                    );

                    resolve(
                        true
                    );
                },

                {
                    enableHighAccuracy:
                        true,

                    maximumAge:
                        300000,

                    timeout:
                        10000
                }

            );

        }
    );

}

let locationWatchId = null;
let locationPollId = null;
let busAlertPollId = null;
let lastLocationSentAt = 0;

// Destination movement comes from the bus GPS (existing Rconnect-Drive
// pipeline), so alerts must keep working when this phone's location is
// unavailable. This poll runs independently of the passenger watcher.
function startBusAlertPolling(){

    if(
        busAlertPollId !== null
    ){

        clearInterval(
            busAlertPollId
        );

        busAlertPollId =
            null;

    }

    refreshBusDestinationAlert();

    busAlertPollId =
        setInterval(
            ()=>{

                if(
                    onBoard === false
                ){

                    stopLocationTracking();

                    return;

                }

                refreshBusDestinationAlert();

            },
            10000
        );

}

async function refreshBusDestinationAlert(){

    try{

        const res =
            await fetch(
                '/passenger/trip/bus-location',
                {
                    headers:{
                        'Accept':
                            'application/json'
                    }
                }
            );

        if(!res.ok){
            return;
        }

        const data =
            await res.json().catch(() => null);

        if(!data || data.success !== true || data.has_active_trip === false){
            return;
        }

        handleDestinationAlert(
            data.destination_alert || null,
            destinationAlertFixFromBus(data)
        );

    }catch(err){

        // Best effort: the location post still evaluates the bus position.

    }

}

function startLocationTracking(){

    // A missed destination recorded while the app was closed must reopen the
    // continuation screen even when GPS is unavailable, so check state before
    // the GPS guards below.
    checkContinuationState();

    if(
        onBoard === false
    ){
        return;
    }

    // Alert state is keyed to the active trip: a new trip resets it and
    // hides any warning left from the previous trip.
    syncDestinationAlertTrip(window._activeTripId || null);

    // Bus-location alerts do not need this phone's GPS.
    startBusAlertPolling();

    if(
        !navigator.geolocation
    ){
        return;
    }

    // One watcher only: clear any previous watcher (and its polling
    // companion) before starting its replacement.
    if(
        locationWatchId !== null
    ){

        try{

            navigator.geolocation.clearWatch(
                locationWatchId
            );

        }catch(clearWatchError){

        }

        locationWatchId =
            null;

    }

    if(
        locationPollId !== null
    ){

        clearInterval(
            locationPollId
        );

        locationPollId =
            null;

    }

    const sendLocation =
        async (pos)=>{

            // A real watch fix always reports. The default-location
            // fallback only affects the boarding UI; it must never suppress
            // destination monitoring for the rest of the session.
            userLocationIsFallback =
                false;

            const now =
                Date.now();

            if(
                now - lastLocationSentAt < 3000
            ){
                return;
            }

            lastLocationSentAt =
                now;

            userLat =
                pos.coords.latitude;

            userLng =
                pos.coords.longitude;

            userAccuracy =
                pos.coords.accuracy
                ?? null;

            // Keep the map's "you are here" marker in sync with the
            // existing tracking, without adding a second watcher.
            updateRouteMapLocation();

            try{

                const res =
                    await fetch(
                        '/passenger/trip/location',
                        {
                            method:'POST',

                            headers:{
                                'Content-Type':
                                'application/json'
                            },

                            body:
                                JSON.stringify({
                                    latitude:
                                        pos.coords.latitude,

                                    longitude:
                                        pos.coords.longitude,

                                    accuracy:
                                        pos.coords.accuracy
                                        ?? null
                                })
                        }
                    );

                if(!res.ok){
                    console.warn(
                        "[location] HTTP",
                        res.status
                    );
                }else{

                    // The server claims each proximity/passed alert once per
                    // destination and reports only the newly claimed ones.
                    // The same response also feeds the developer diagnostic
                    // panel, so it is handled even when no alert fired.
                    const data =
                        await res.json().catch(() => null);

                    if(data){
                        handleDestinationAlert(
                            data.destination_alert || null,
                            destinationAlertFixFromResponse(data, pos)
                        );
                    }

                }

            }catch(error){
                console.warn(
                    "[location] send failed",
                    error
                );

            }

        };

    locationWatchId =
        navigator.geolocation.watchPosition(
            sendLocation,
            null,
            {
                enableHighAccuracy:
                    true,

                maximumAge:
                    30000,

                timeout:
                    15000
            }
        );

    locationPollId =
        setInterval(
            ()=>{

                if(
                    onBoard === false
                ){

                    stopLocationTracking();

                    return;

                }

                navigator.geolocation.getCurrentPosition(
                    sendLocation,
                    null,
                    {
                        enableHighAccuracy:
                            true,

                        maximumAge:
                            2000,

                        timeout:
                            10000
                    }
                );

            },
            3000
        );

}

function stopLocationTracking(){

    if(
        locationWatchId !== null
    ){

        navigator.geolocation.clearWatch(
            locationWatchId
        );

        locationWatchId =
            null;

    }

    if(
        locationPollId !== null
    ){

        clearInterval(
            locationPollId
        );

        locationPollId =
            null;

    }

    if(
        busAlertPollId !== null
    ){

        clearInterval(
            busAlertPollId
        );

        busAlertPollId =
            null;

    }

    lastLocationSentAt =
        0;

    syncDestinationAlertTrip(null);

}

function showRetryButton(){

    let btn =
        document.getElementById(
            "retryLocation"
        );

    if(btn){
        return;
    }

    btn =
        document.createElement(
            "button"
        );

    btn.id =
        "retryLocation";

    btn.innerText =
        "Retry Location";

    btn.style.marginTop =
        "10px";

    btn.onclick = async ()=>{

        btn.remove();

        try{

            const ok =
                await requestLocation();

            if(ok){

                startScanner();

            }

        }catch(error){

            return;

        }

    };

    const tripInfo =
        document.getElementById(
            "tripInfo"
        );

    if(!tripInfo){
        return;
    }

    tripInfo.appendChild(
        btn
    );

}

async function fetchActiveTrips(){

    // While on board, the destination list is owned by the active-trip flow
    // (fetched with the boarding stop so behind-stops are filtered). Do not
    // overwrite it with an unfiltered list.
    if(onBoard){
        return;
    }

    try{

        const res =
            await fetch(
                '/passenger/api/active-trips'
            );

        const data =
            await res.json();

        if(
            data.success &&
            Array.isArray(data.trips) &&
            data.trips.length
        ){

            const tripId =
                data.trips[0].id;

            fetchDestinations(
                tripId
            );

        }

    }catch(error){

        console.error(
            "fetchActiveTrips error:",
            error
        );

    }

}

let destinationsCache = [];
let lastDestinationFetch = null;

async function fetchDestinations(tripNo, boardingStopId, boardingPoint, quiet){

    try{

        if(!tripNo){
            return null;
        }

        lastDestinationFetch = {
            tripNo: tripNo,
            boardingStopId: boardingStopId || 0,
            boardingPoint: boardingPoint || ''
        };

        const res =
            await fetch(
                '/passenger/api/destinations',
                {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        trip_no: tripNo,
                        boarding_stop_id: boardingStopId || 0,
                        boarding_point: boardingPoint || ''
                    })
                }
            );

        const data =
            await res.json();

        if(
            data.success &&
            Array.isArray(
                data.destinations
            )
        ){

            destinationsCache =
                data.destinations;

            populateDestinations();

            hideDestinationRetry();

            return data;

        }else{

            if(quiet){
                // A background refresh must never wipe options the passenger
                // can still use; keep the last good list until the next try.
                return null;
            }

            console.error(
                data.message ||
                "No destinations returned."
            );

            // Never leave another trip's stops selectable: the options on
            // screen must belong to the active trip. Clear them and lock the
            // select until a list for this trip loads.
            destinationsCache = [];

            populateDestinations();

            const select =
                document.getElementById(
                    "destinationSelect"
                );

            if(select){

                select.disabled = true;

                if(select.options.length){

                    select.options[0].text =
                        HOME_DESTINATIONS_UNAVAILABLE;

                }

            }

            showDestinationRetry(destinationUnavailableLabel(data));

        }

    }catch(error){

        if(quiet){
            // Keep the last good list on a background refresh failure.
            return null;
        }

        console.error(error);

        destinationsCache = [];

        populateDestinations();

        const select =
            document.getElementById(
                "destinationSelect"
            );

        if(select){

            select.disabled = true;

            if(select.options.length){

                select.options[0].text =
                    HOME_DESTINATIONS_UNAVAILABLE;

            }

        }

        showDestinationRetry();

    }

}

function showDestinationRetry(message){

    const retry =
        document.getElementById('destinationRetry');

    if(!retry){ return; }

    retry.textContent =
        message || HOME_DESTINATIONS_UNAVAILABLE;
    retry.style.display = 'block';

}

function destinationUnavailableLabel(data){

    const code =
        String((data && data.code) || '');

    const message =
        String((data && data.message) || '');

    // Known actionable conditions (for example a trip without a route) are
    // shown as-is so the passenger knows what to do; everything else gets the
    // retryable generic label.
    if(
        message &&
        (
            code === 'TRIP_ROUTE_MISSING' ||
            code === 'TRIP_ID_REQUIRED'
        )
    ){
        return message;
    }

    return HOME_DESTINATIONS_UNAVAILABLE;

}

function hideDestinationRetry(){

    const retry =
        document.getElementById('destinationRetry');

    if(retry){ retry.style.display = 'none'; }

}

document.getElementById('destinationRetry')?.addEventListener('click', ()=>{

    const last = lastDestinationFetch;

    if(!last || !last.tripNo){
        return;
    }

    hideDestinationRetry();

    fetchDestinations(
        last.tripNo,
        last.boardingStopId,
        last.boardingPoint
    );

});

const HOME_LOCAL_STOP = <?= json_encode(passenger_t('home.local_stop', 'Local / Current Stop'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const HOME_DESTINATIONS_UNAVAILABLE = <?= json_encode(passenger_t('home.destinations_unavailable', 'Destinations unavailable — tap to retry'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function populateDestinations(){

    const select =
        document.getElementById(
            "destinationSelect"
        );

    if(!select){
        return;
    }

    const searchInput =
        document.getElementById('destinationSearch');

    // The search filters the custom menu, not the underlying selected option.
    const term = '';

    const previousValue =
        select.value;

    select.innerHTML = "";

    const defaultOption =
        document.createElement(
            "option"
        );

    defaultOption.value = "";

    if(
        !destinationsCache.length
    ){

        defaultOption.textContent =
            onBoard
                ? "No stops ahead of your boarding point"
                : "Select Destination";

        select.appendChild(
            defaultOption
        );

        return;
    }

    const filtered =
        term
            ? destinationsCache.filter(
                (stop) =>
                    String(stop.destination || '')
                        .toLowerCase()
                        .indexOf(term) !== -1
            )
            : destinationsCache;

    if(
        !filtered.length
    ){

        defaultOption.textContent =
            <?= json_encode(passenger_t('home.no_matching_destinations', 'No matching destinations'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        select.appendChild(
            defaultOption
        );

        return;
    }

    defaultOption.textContent =
        "Select Destination";

    select.appendChild(
        defaultOption
    );

    filtered.forEach(
        (stop)=>{

            const opt =
                document.createElement(
                    "option"
                );

            opt.value =
                stop.id;

            opt.textContent =
                stop.destination +
                (stop.is_boarding ? ' (' + HOME_LOCAL_STOP + ')' : '');

            opt.dataset.destination =
                stop.destination;

            select.appendChild(
                opt
            );

        }
    );

    // Keep a saved destination locked even when the list is reloaded.
    select.disabled =
        select.dataset.locked === '1';

    if(searchInput){
        searchInput.disabled = select.disabled;
    }

    if(
        onBoard &&
        previousValue &&
        Array.from(
            select.options
        ).some(
            o => o.value === previousValue
        )
    ){

        select.value =
            previousValue;

    }

    select.dataset.prevValue =
        select.value || '';

}

function updateTripUI(result){

    try{

        const busInfo =
            document.getElementById(
                "busInfo"
            );

        const tripNoInfo =
            document.getElementById(
                "tripNoInfo"
            );

        const passengerType =
            document.getElementById(
                "passengerType"
            );

        const boardingPoint =
            document.getElementById(
                "boardingPoint"
            );

        const destinationSelect =
            document.getElementById(
                "destinationSelect"
            );

        const tripStatus =
            document.getElementById(
                "tripStatus"
            );

        if(busInfo){

            busInfo.innerText =
                result.bus_no || "-";

        }

        const driverInfo =
            document.getElementById(
                "driverInfo"
            );

        if(driverInfo){

            driverInfo.innerText =
                result.driver_name || "-";

        }

        if(tripNoInfo){

            tripNoInfo.innerText =
                result.trip_no || "-";

        }
        if(result.trip_no){

            fetchDestinations(
                result.trip_no,
                result.boarding_stop_id || 0,
                result.boarding_point ||
                    currentBoardingPoint ||
                    lockedBoardingPoint ||
                    ''
            );

        }

        if(result.discount_type){
            activeDiscountType =
                String(result.discount_type).toLowerCase().trim() || 'regular';
        }

        if(passengerType){

            const passengerTypeLabel =
                formatPassengerTypeLabel(result.discount_type);

            if(passengerTypeLabel){
                passengerType.innerText = passengerTypeLabel;
            }else if(
                !passengerType.innerText ||
                passengerType.innerText === '-' ||
                passengerType.innerText === '—'
            ){
                passengerType.innerText = 'Regular';
            }

        }

        if(result.type === 'boarding' && result.trip_id){
            window._activeTripId = result.trip_id;
        }

        updateFareDisplay();

        if(
            result.type ===
            "boarding"
        ){

            if(boardingPoint){

                boardingPoint.innerText =
                    result.boarding_point ||
                    currentBoardingPoint ||
                    "Boarded";

            }

            if(destinationSelect){

                destinationSelect.disabled =
                    false;

                if(
                    destinationSelect.options &&
                    destinationSelect.options.length
                ){

                    destinationSelect.options[0].text =
                        "Select Destination";

                }

            }

            if(tripStatus){

                tripStatus.innerText =
                    "Ongoing";

                tripStatus.style.background =
                    "#ffe58f";

            }

        }

        if(
            result.type ===
            "dropoff"
        ){

            const destError =
                document.getElementById(
                    "destinationError"
                );

            if(destError){

                destError.style.display =
                    "none";

            }

            if(tripStatus){

                tripStatus.innerText =
                    "Completed";

                tripStatus.style.background =
                    "#b7e4c7";

            }

            if(destinationSelect){

                if(
                    result.destination
                ){

                    destinationSelect.value =
                        result.destination;

                }

                destinationSelect.disabled =
                    true;

            }

            const paymentSummary =
                document.getElementById(
                    "paymentSummary"
                );

            if(paymentSummary){

                paymentSummary.style.display =
                    "block";

            }

            const paymentMethod =
                document.getElementById(
                    "paymentMethod"
                );

            if(paymentMethod){

                paymentMethod.innerText =
                    formatPaymentMethodLabel(result.payment_method) ||
                    "-";

            }

            const cashAmount =
                document.getElementById(
                    "cashAmount"
                );

            if(cashAmount){

                cashAmount.innerText =
                    "₱" +
                    Number(
                        result.cash_amount || 0
                    ).toFixed(2);

            }

            const gcashAmount =
                document.getElementById(
                    "gcashAmount"
                );

            if(gcashAmount){

                gcashAmount.innerText =
                    "₱" +
                    Number(
                        result.gcash_amount || 0
                    ).toFixed(2);

            }

            const hybridBreakdown =
                document.getElementById(
                    "hybridBreakdown"
                );

            if(hybridBreakdown){

                hybridBreakdown.style.display =
                    result.payment_method ===
                    "hybrid"
                        ? "block"
                        : "none";

            }

            const paymentStatus =
                document.getElementById(
                    "paymentStatus"
                );

            if(paymentStatus){

                paymentStatus.innerText =
                    result.payment_status ||
                    "-";

            }

            const baseFare =
                document.getElementById(
                    "baseFare"
                );

            if(baseFare){

                baseFare.innerText =
                    "₱" +
                    Number(
                        result.base_fare || 0
                    ).toFixed(2);

            }

            const discountAmount =
                document.getElementById(
                    "discountAmount"
                );

            if(discountAmount){

                discountAmount.innerText =
                    "-₱" +
                    Number(
                        result.discount_amount || 0
                    ).toFixed(2);

            }

            const totalFare =
                document.getElementById(
                    "totalFare"
                );

            if(totalFare){

                totalFare.innerText =
                    "₱" +
                    Number(
                        result.fare || 0
                    ).toFixed(2);

            }

            showTripModal(
                result
            );

        }

        saveActiveTripInfo(result);

    }catch(error){

        setStatus(
            "UI Update Failed",
            "red"
        );

    }

}

function pickBackCameraIndex(cameras){

    for(
        let i = 0;
        i < cameras.length;
        i++
    ){

        const label =
            (
                cameras[i].label ||
                ''
            ).toLowerCase();

        if(
            label.indexOf('environment') !== -1 ||
            label.indexOf('back') !== -1 ||
            label.indexOf('rear') !== -1
        ){
            return i;
        }

    }

    return 0;

}

function showCameraToggle(){

    const el =
        document.getElementById(
            'cameraToggle'
        );

    if(!el){
        return;
    }

    el.style.display =
        cameraList.length > 1
        ? ''
        : 'none';

}

function hideCameraToggle(){

    const el =
        document.getElementById(
            'cameraToggle'
        );

    if(!el){
        return;
    }

    el.style.display =
        'none';

}

async function switchCamera(){

    if(
        !scannerRunning ||
        cameraList.length < 2
    ){
        return;
    }

    try{
        await html5QrCode.stop();
    }catch(error){}

    try{
        html5QrCode.clear();
    }catch(error){}

    html5QrCode = null;

    scannerRunning = false;

    cameraIndex =
        (
            cameraIndex + 1
        ) % cameraList.length;

    await startScanner(
        cameraIndex
    );

}

document
    .getElementById('cameraToggle')
    .addEventListener(
        'click',
        switchCamera
    );

function resumeScannerAfterTrip(){

    scanCooldownUntil = Date.now() + 5000;

    const readerEl = document.getElementById('reader');
    if(readerEl){ readerEl.style.display = 'block'; }

    const scanBoxEl = document.querySelector('.scan-box');
    if(scanBoxEl){ scanBoxEl.style.display = 'block'; }

    const fallbackEl = document.getElementById('noCameraFallback');
    if(fallbackEl){ fallbackEl.style.display = 'none'; }

    const readyCardEl = document.getElementById('readyBoardCard');
    if(readyCardEl){ readyCardEl.style.display = 'none'; }

    const boardBtnEl = document.getElementById('boardNowBtn');
    if(boardBtnEl){ boardBtnEl.disabled = false; }

    setStatus("Trip completed. Scan a bus QR to board again.", "green");

    if(scannerRunning && html5QrCode){
        return;
    }

    try{ startScanner(); }catch(e){}

}

let activeTripPollTimer = null;
const ACTIVE_TRIP_POLL_MS = 15000;
let completionCheckInFlight = false;

let lastRemainingRefreshAt = 0;

// While the passenger is on board and has not locked a destination yet, the
// remaining-ahead list follows the bus: stops the driver has already passed
// drop out. The refresh is throttled and never clobbers the last good list.
function refreshRemainingDestinations(){

    try{

        const select =
            document.getElementById('destinationSelect');

        if(
            !select ||
            select.dataset.locked === '1'
        ){
            return;
        }

        const now =
            Date.now();

        if(now - lastRemainingRefreshAt < 45000){
            return;
        }

        const cached =
            storedTripInfo();

        if(
            !cached ||
            !cached.trip_no
        ){
            return;
        }

        lastRemainingRefreshAt =
            now;

        fetchDestinations(
            cached.trip_no,
            cached.boarding_stop_id || 0,
            cached.boarding_point || '',
            true
        );

    }catch(err){}

}

function startActiveTripPolling(){

    if(activeTripPollTimer){
        return;
    }

    activeTripPollTimer =
        setInterval(
            checkActiveTripCompletion,
            ACTIVE_TRIP_POLL_MS
        );

}

function stopActiveTripPolling(){

    if(activeTripPollTimer){

        clearInterval(
            activeTripPollTimer
        );

        activeTripPollTimer = null;

    }

}

function storedTripInfo(){

    try{

        const raw =
            localStorage.getItem(
                TRIP_INFO_KEY
            );

        if(raw){

            const parsed =
                JSON.parse(raw);

            if(parsed && typeof parsed === 'object'){
                return parsed;
            }

        }

    }catch(err){}

    return null;

}

async function checkActiveTripCompletion(){

    if(isProcessing || completionCheckInFlight || !onBoard){
        return;
    }

    const cached = storedTripInfo();

    const tripId =
        window._activeTripId ||
        (cached && cached.trip_id ? cached.trip_id : null);

    if(!tripId){
        return;
    }

    completionCheckInFlight = true;

    let stillActive = true;

    try{

        const res =
            await fetch(
                '/passenger/trip/active'
            );

        const data =
            await res.json();

        stillActive =
            !!(data && data.hasActiveTrip);

    }catch(err){

        completionCheckInFlight = false;

        return;

    }

    completionCheckInFlight = false;

    if(stillActive){

        // A trip that is still running keeps the passenger on board, even
        // when it was already paid online. The receipt appears once the trip
        // ends (driver drop-off / bus trip completion).
        refreshRemainingDestinations();

        return;

    }

    stopActiveTripPolling();

    stopLocationTracking();

    onBoard = false;

    window._activeTripId = null;

    const method =
        String(
            (cached && cached.payment_method) || ''
        ).toLowerCase();

    resetTripState();

    openCompletedTripPayment(tripId, method);

}

async function openCompletedTripPayment(tripId, method){

    let status = '';

    try{

        const res =
            await fetch(
                '/passenger/paymongo/status',
                {
                    method:'POST',

                    headers:{
                        'Content-Type':
                        'application/json'
                    },

                    body:
                        JSON.stringify({
                            trip_id: tripId
                        })
                }
            );

        const data =
            await res.json();

        status =
            String(
                (data && data.payment_status) || ''
            ).toLowerCase();

    }catch(err){}

    if(status === 'paid'){
        setPaymentLock(false);
        openTripReceipt(tripId);
        return;
    }

    // The trip is finished but the payment is still pending: ask the
    // passenger to settle it right away. Online Payment trips open the QR Ph
    // modal; cash trips show the receipt for the driver to collect.
    if(method === 'gcash' || method === 'hybrid'){
        openPaymentModal(tripId);
    }else{
        openTripReceipt(tripId);
    }

    // The unpaid amount also counts toward the balance limit, so lock once
    // the limit is reached.
    const locked = await refreshOutstandingLock();

    if(locked){
        startPaymentLockPolling();
    }

}

function setPaymentLock(locked, tripId, method, info){

    paymentLocked = !!locked;
    paymentLockedTripId = locked ? (tripId || null) : null;
    paymentLockedMethod = locked ? String(method || '').toLowerCase() : '';
    paymentLockedInfo = locked ? (info || paymentLockedInfo) : null;

    if(paymentLocked){
        updatePaymentLockCard();
        showPaymentLock();
    }else{
        hidePaymentLock();
    }

}

function showBalanceNotice(outstanding){

    balanceNoticeShown = true;
    balanceNoticeInfo = outstanding || null;

    const info = outstanding || {};
    const count = Number(info.count || 0);
    const total = Number(info.total || 0);

    const sub = document.getElementById('balanceNoticeSub');
    if(sub){
        sub.textContent = count + ' unpaid trip' + (count === 1 ? '' : 's');
    }

    const text = document.getElementById('balanceNoticeText');
    if(text){
        text.textContent = 'You have ' + count + ' unpaid trip' + (count === 1 ? '' : 's')
            + ' (₱' + total.toFixed(2) + '). Please settle it soon.';
    }

    const card = document.getElementById('balanceNoticeCard');
    if(card){ card.style.display = 'block'; }

}

function hideBalanceNotice(){

    balanceNoticeShown = false;
    balanceNoticeInfo = null;

    const card = document.getElementById('balanceNoticeCard');
    if(card){ card.style.display = 'none'; }

}

function payOutstandingTrip(info){

    const trips = Array.isArray(info && info.trips) ? info.trips : [];
    if(!trips.length){ return; }

    const sorted = trips.slice().sort(function(a, b){
        return Number(b.trip_id || 0) - Number(a.trip_id || 0);
    });

    const online = sorted.find(function(t){
        const m = String((t && t.payment_method) || '').toLowerCase();
        return m === 'gcash' || m === 'hybrid';
    }) || null;

    if(online){
        openPaymentModal(Number(online.trip_id));
        return;
    }

    openTripReceipt(Number(sorted[0].trip_id));

}

function updatePaymentLockCard(){

    const info = paymentLockedInfo || {};
    const count = Number(info.count || 0);
    const total = Number(info.total || 0);
    const trips = Array.isArray(info.trips) ? info.trips : [];

    const sub = document.getElementById('paymentLockSub');
    if(sub){
        sub.textContent = count > 0
            ? count + ' unpaid trip' + (count === 1 ? '' : 's')
            : 'Settle your balance to board again';
    }

    const note = document.getElementById('paymentLockNote');
    if(note){
        note.textContent = count > 0
            ? 'You have ' + count + ' unpaid trip' + (count === 1 ? '' : 's')
                + ' (₱' + total.toFixed(2) + '). Please settle your balance to board again.'
            : 'You cannot board another bus until your balance is settled.';
    }

    // Online payment only applies to Online Payment / hybrid trips.
    const payable = trips.find(function(t){
        const m = String((t && t.payment_method) || '').toLowerCase();
        return m === 'gcash' || m === 'hybrid';
    }) || null;

    paymentLockedPayableTripId = payable ? Number(payable.trip_id) : null;

    const btn = document.getElementById('paymentLockBtn');
    if(btn){
        btn.innerHTML = payable
            ? '<i class="bi bi-wallet2"></i> Pay Now'
            : '<i class="bi bi-receipt"></i> View Receipt';
    }

}

async function refreshOutstandingLock(){

    try{

        const res = await fetch('/passenger/trip/active');
        const data = await res.json();
        const out = data && data.outstanding_payment;
        const count = out ? Number(out.count || 0) : 0;
        const limit = out ? Number(out.limit || 2) : 2;

        if(out && count >= limit){
            hideBalanceNotice();
            setPaymentLock(true, out.oldest_trip_id, out.oldest_payment_method, out);
            return true;
        }

        setPaymentLock(false);

        if(out && count > 0){
            showBalanceNotice(out);
        }else{
            hideBalanceNotice();
        }

        return false;

    }catch(err){
        return paymentLocked;
    }

}

function showPaymentLock(){

    const reader = document.getElementById('reader');
    if(reader){ reader.style.display = 'none'; }

    const scanBox = document.querySelector('.scan-box');
    if(scanBox){ scanBox.style.display = 'none'; }

    const fallback = document.getElementById('noCameraFallback');
    if(fallback){ fallback.style.display = 'none'; }

    const readyCard = document.getElementById('readyBoardCard');
    if(readyCard){ readyCard.style.display = 'none'; }

    const card = document.getElementById('paymentLockCard');
    if(card){ card.style.display = 'block'; }

}

function hidePaymentLock(){

    const card = document.getElementById('paymentLockCard');
    if(card){ card.style.display = 'none'; }

}

function stopPaymentLockPolling(){

    if(paymentLockTimer){
        clearInterval(paymentLockTimer);
        paymentLockTimer = null;
    }

}

function startPaymentLockPolling(){

    stopPaymentLockPolling();

    paymentLockTimer = setInterval(async ()=>{

        const wasLocked = paymentLocked;

        const stillLocked = await refreshOutstandingLock();

        if(wasLocked && !stillLocked && !onBoard){
            startScanner();
        }

        if(!stillLocked && !balanceNoticeShown){
            stopPaymentLockPolling();
        }

    }, 5000);

}

const paymentLockBtn = document.getElementById('paymentLockBtn');
if(paymentLockBtn){
    paymentLockBtn.addEventListener('click', ()=>{
        if(paymentLockedPayableTripId){
            openPaymentModal(paymentLockedPayableTripId);
            return;
        }
        if(paymentLockedTripId){
            openTripReceipt(paymentLockedTripId);
        }
    });
}

const balanceNoticeBtn = document.getElementById('balanceNoticeBtn');
if(balanceNoticeBtn){
    balanceNoticeBtn.addEventListener('click', ()=>{
        payOutstandingTrip(balanceNoticeInfo);
    });
}

async function startScanner(useCameraIndex = -1){

    if(onBoard || paymentLocked){
        return;
    }

    document.getElementById('reader').style.display =
        'block';

    document.querySelector('.scan-box').style.display =
        'block';

    var abc =
        document.getElementById(
            'onBoardCard'
        );

    if(abc){
        abc.style.display = 'none';
    }

    var fb =
        document.getElementById(
            'noCameraFallback'
        );

    if(fb){
        fb.style.display = 'none';
    }

    if(scannerRunning){
        return;
    }

    const ok =
        await requestLocation();

    if(!ok){

        setStatus(
            "Location required to scan",
            "red"
        );

        return;
    }

    if(typeof Html5Qrcode === "undefined"){

        setStatus(
            "Scanner not loaded",
            "red"
        );

        return;

    }

    try{

        const cameras =
            await Html5Qrcode.getCameras();

        if(
            cameras.length === 0
        ){

            throw new Error(
                'No camera found'
            );

        }

        cameraList =
            cameras;

        const useIdx =
            (
                useCameraIndex >= 0 &&
                useCameraIndex < cameras.length
            )
            ? useCameraIndex
            : pickBackCameraIndex(
                cameras
            );

        cameraIndex =
            useIdx;

        html5QrCode =
            new Html5Qrcode(
                "reader"
            );

        scannerRunning =
            true;

        showCameraToggle();

        setStatus(
            "Starting camera...",
            "orange"
        );

        await html5QrCode.start(

            cameraList[
                cameraIndex
            ].id,

            {
                fps: 10,
                qrbox: { width: 220, height: 220 },
                aspectRatio: 1.0
            },

            async (decodedText)=>{

                if(
                    Date.now() < scanCooldownUntil
                ){
                    return;
                }

                if(
                    isProcessing
                ){
                    return;
                }

                isProcessing =
                    true;

let data;

try{

    data =
        JSON.parse(
            decodedText
        );

}catch(error){

    try{

        const url =
            new URL(
                decodedText
            );

        const busNo =
            url.searchParams.get(
                'bus'
            );

        const tripNo =
            url.searchParams.get(
                'trip'
            );

        if(
            !busNo
        ){

            throw new Error(
                'Missing bus'
            );

        }

        data = {

            bus_no:
                busNo,

            trip_no:
                tripNo || "0"

        };

    }catch(urlError){

        setStatus(
            "Invalid QR format",
            "red"
        );

        isProcessing =
            false;

        return;

    }

}

                try{

                    const payload = {

                        bus_no:
                            data.bus_no ||
                            data.busNo ||
                            data.bus_id ||
                            data.busId ||
                            data.bus ||
                            '',

                        trip_no:
                            data.trip_no ||
                            data.tripNo ||
                            data.trip_id ||
                            data.tripId ||
                            data.trip ||
                            '0',

                        latitude:
                            userLat,

                        longitude:
                            userLng,

                        accuracy:
                            userAccuracy,

                        payment_method:
                            document.getElementById(
                                'paymentMethod'
                            )?.value || 'cash',

                        cash_amount:
                            Number(
                                document.getElementById(
                                    'cashAmountInput'
                                )?.value || 0
                            ),

                        gcash_amount:
                            Number(
                                document.getElementById(
                                    'gcashAmountInput'
                                )?.value || 0
                            )

                    };

                    const res =
                        await fetch(

                            '/passenger/scan/store',

                            {
                                method:'POST',

                                headers:{
                                    'Content-Type':
                                        'application/json'
                                },

                                body:
                                    JSON.stringify(
                                        payload
                                    )
                            }

);

                        const responseText =
                            await res.text();

                    let result;

                    try{

                        const cleanedResponse =
                            responseText.trim();

                        if(
                            !cleanedResponse
                        ){

                            throw new Error(
                                'Empty server response'
                            );

                        }

                        const firstBrace =
                            cleanedResponse.indexOf('{');

                        const firstBracket =
                            cleanedResponse.indexOf('[');

                        let jsonStart =
                            -1;

                        if(
                            firstBrace >= 0 &&
                            firstBracket >= 0
                        ){

                            jsonStart =
                                Math.min(
                                    firstBrace,
                                    firstBracket
                                );

                        }else if(
                            firstBrace >= 0
                        ){

                            jsonStart =
                                firstBrace;

                        }else if(
                            firstBracket >= 0
                        ){

                            jsonStart =
                                firstBracket;

                        }

                        if(
                            jsonStart < 0
                        ){

                            throw new Error(
                                'Invalid JSON response'
                            );

                        }

                        result =
                            JSON.parse(
                                cleanedResponse.substring(
                                    jsonStart
                                )
                            );

                    }catch(error){

                        if (window.PASSENGER_QR_SCAN_DEBUG) {
                            console.error('Scan error:', error, 'HTTP status:', res ? res.status : 'none');
                        }

                        setStatus(
                            'Unable to process scan. Please try again.',
                            'red'
                        );

                        isProcessing =
                            false;

                        return;
                    }

                    if(
                        !result.success
                    ){

                        setStatus(
                            formatScanFailure(
                                result,
                                res.status
                            ),
                            "red"
                        );

                        isProcessing =
                            false;

                        return;
                    }

                    if(
                        result.hasActiveTrip === true
                    ){
                        setStatus(
                            result.message ||
                            "Active trip in progress",
                            "orange"
                        );

                        try{
                            const tripRes =
                                await fetch(
                                    '/passenger/trip/active'
                                );

                            const tripData =
                                await tripRes.json();

                            if(
                                tripData.hasActiveTrip
                            ){
                                onBoard =
                                    true;

                                lockedBoardingPoint =
                                    tripData.boarding_point ||
                                    null;

                                document.getElementById(
                                    'activeBusNo'
                                ).innerText =
                                    tripData.bus_no || '-';

                                document.getElementById(
                                    'activeDriver'
                                ).innerText =
                                    tripData.driver_name || '-';

                                document.getElementById(
                                    'activeBoarding'
                                ).innerText =
                                    tripData.boarding_point || '-';

                                document.getElementById(
                                    'activeDestination'
                                ).innerText =
                                    tripData.destination || '-';

                                document.getElementById(
                                    'onBoardCard'
                                ).style.display =
                                    'flex';

                                document.getElementById(
                                    'reader'
                                ).style.display =
                                    'none';

                                document.querySelector(
                                    '.scan-box'
                                ).style.display =
                                    'none';

                                const fb =
                                    document.getElementById(
                                        'noCameraFallback'
                                    );

                                if(fb){
                                    fb.style.display =
                                        'none';
                                }

                                try{
                                    if(
                                        html5QrCode
                                    ){
                                        await html5QrCode.stop();

                                        await html5QrCode.clear();
                                    }
                                }catch(e){}

                                html5QrCode =
                                    null;

                                scannerRunning =
                                    false;

                                hideCameraToggle();

                                window._activeTripId =
                                    tripData.trip_id ||
                                    window._activeTripId;

                                startActiveTripPolling();

                                refreshRouteMap();
                            }
                        }catch(error){

                        }

                        isProcessing =
                            false;

                        return;
                    }

                    if(
                        result.type ===
                        "boarding"
                    ){

                        resetTripState();

                    }

                    updateTripUI(
                        result
                    );

                    if(
                        result.type ===
                        "boarding"
                    ){

                        onBoard =
                            true;

                        ['hp_payment_method','hp_destination_id','hp_trip_details_open'].forEach(
                            k => sessionStorage.removeItem(k)
                        );

                        document.getElementById('tripDetails').style.display = 'block';
                        const _ta = document.getElementById('tripArrow');
                        if(_ta){ _ta.textContent = '▲'; }
                        saveActiveTripInfo(result);

                        currentBoardingPoint =
                            result.boarding_point || '';
                        lockedBoardingPoint =
                            currentBoardingPoint;

                        setStatus(
                            "",
                            "green"
                        );

                        document.getElementById(
                            'reader'
                        ).style.display = 'none';

                        document.querySelector(
                            '.scan-box'
                        ).style.display = 'none';

                        document.getElementById(
                            'noCameraFallback'
                        ).style.display = 'none';

                        document.getElementById(
                            'onBoardCard'
                        ).style.display =
                            'flex';

                        document.getElementById(
                            'activeBusNo'
                        ).innerText =
                            result.bus_no || '-';

                        document.getElementById(
                            'activeDriver'
                        ).innerText =
                            result.driver_name || '-';

                        document.getElementById(
                            'activeBoarding'
                        ).innerText =
                            result.boarding_point || '-';

                        document.getElementById(
                            'activeDestination'
                        ).innerText =
                            result.destination || '-';

                        window._activeTripId = result.trip_id || null;
                        if(window.companionSetTrip){ window.companionSetTrip(result.trip_id || null); }

                        startLocationTracking();

                        const boardCard =
                            document.getElementById(
                                "readyBoardCard"
                            );

                        if(boardCard){

                            boardCard.style.display =
                                "none";

                        }

                        const boardBtn =
                            document.getElementById(
                                "boardNowBtn"
                            );

                        if(boardBtn){

                            boardBtn.disabled =
                                true;

                        }

                        try{

                            if(
                                html5QrCode
                            ){

                                await html5QrCode.stop();

                                await html5QrCode.clear();

                            }

                        }catch(error){}

                        html5QrCode =
                            null;

                        scannerRunning =
                            false;

                        hideCameraToggle();

                        startActiveTripPolling();

                        refreshRouteMap();

                        isProcessing =
                            false;

                        return;
                    }

                    if(
                        result.type ===
                        "dropoff"
                    ){

                        onBoard =
                            false;

                        stopLocationTracking();

                        window._activeTripId =
                            null;

                        try{ localStorage.removeItem(TRIP_INFO_KEY); }catch(e){}
                        resetTripState();

                        resumeScannerAfterTrip();

                        isProcessing =
                            false;

                        return;
                    }

                }catch(error){

                    setStatus(
                        window.PASSENGER_QR_SCAN_DEBUG && error && error.message
                            ? "Scan Failed [" + error.message + "]"
                            : "Scan Failed",
                        "red"
                    );

                }

                isProcessing =
                    false;

            },

            ()=>{}

        );

        setStatus(
            "Scanning...",
            "green"
        );

    }catch(err){

        scannerRunning =
            false;

        html5QrCode =
            null;

        document.getElementById('reader').style.display =
            'none';

        document.querySelector('.scan-box').style.display =
            'none';

        var fallback =
            document.getElementById(
                'noCameraFallback'
            );

        fallback.style.display =
            'flex';

        setStatus(
            'Camera error: ' + (err && err.message ? err.message : err),
            "red"
        );

    }

}

document.addEventListener(
    'change',
    (e) => {

        if(
            !e.target ||
            e.target.id !== 'destinationSelect'
        ){
            return;
        }

        const destSelect = e.target;

        const destError =
            document.getElementById(
                'destinationError'
            );

        if(
            destError &&
            destSelect.value
        ){

            destError.style.display =
                'none';

        }

        // The destination is locked once saved.
        if(destSelect.dataset.locked === '1'){
            return;
        }

        // Fallback path (keyboard/edge cases): warn before saving. The styled
        // warning is normally shown when the passenger taps the dropdown.
        if(destSelect.value && !destinationWarningAcknowledged){

            destSelect.dataset.pendingValue = destSelect.value;

            destSelect.value =
                destSelect.dataset.prevValue || '';

            showDestinationWarning();

            return;

        }

        saveHomepageState();

        if(destSelect.value){
            debouncedSaveTripState({ destination_id: destSelect.value });
        }

        updateFareDisplay();

    }
);

let destinationWarningAcknowledged = false;

function showDestinationWarning(){

    const modal = document.getElementById('destinationWarningModal');
    if(modal){ modal.style.display = 'flex'; }

    // Each time the warning opens the agreement must be given again.
    const agree = document.getElementById('destinationAgreementCheck');
    if(agree){ agree.checked = false; }

    const agreeError = document.getElementById('destinationAgreementError');
    if(agreeError){ agreeError.style.display = 'none'; }

    syncDestinationAgreementButton();

}

function syncDestinationAgreementButton(){

    const agree = document.getElementById('destinationAgreementCheck');
    const button = document.getElementById('destinationWarningContinue');
    if(!button){ return; }

    const canContinue = !agree || agree.checked;

    button.disabled = !canContinue;
    button.style.opacity = canContinue ? '1' : '0.55';
    button.style.cursor = canContinue ? 'pointer' : 'not-allowed';

}

function hideDestinationWarning(){

    const modal = document.getElementById('destinationWarningModal');
    if(modal){ modal.style.display = 'none'; }

}

function acknowledgeDestinationWarning(){

    const agree = document.getElementById('destinationAgreementCheck');
    if(agree && !agree.checked){

        const agreeError = document.getElementById('destinationAgreementError');
        if(agreeError){ agreeError.style.display = 'block'; }

        return;

    }

    const agreeError = document.getElementById('destinationAgreementError');
    if(agreeError){ agreeError.style.display = 'none'; }

    destinationWarningAcknowledged = true;

    hideDestinationWarning();

    const destSelect = document.getElementById('destinationSelect');
    if(!destSelect){ return; }

    const pending = destSelect.dataset.pendingValue || '';
    destSelect.dataset.pendingValue = '';

    if(pending){

        destSelect.value = pending;

        if(destSelect.value){
            saveHomepageState();
            debouncedSaveTripState({ destination_id: destSelect.value });
            updateFareDisplay();
        }

        return;

    }

    window.openDestinationDropdown();

}

window.openDestinationDropdown = function () {
    const select = document.getElementById('destinationSelect');
    const panel = document.getElementById('destinationDropdownPanel');
    if (!select || !panel || select.disabled || select.dataset.locked === '1') { return; }
    panel.hidden = false;
    document.getElementById('destinationDropdownToggle').setAttribute('aria-expanded', 'true');
    document.getElementById('destinationSearch').value = '';
    window.renderDestinationDropdown();
    document.getElementById('destinationSearch').focus();
};
window.closeDestinationDropdown = function () {
    const panel = document.getElementById('destinationDropdownPanel');
    if (panel) { panel.hidden = true; }
    document.getElementById('destinationDropdownToggle')?.setAttribute('aria-expanded', 'false');
};
window.renderDestinationDropdown = function () {
    const select = document.getElementById('destinationSelect');
    const options = document.getElementById('destinationDropdownOptions');
    const toggle = document.getElementById('destinationDropdownToggle');
    if (!select || !options || !toggle) { return; }
    toggle.disabled = select.disabled || select.dataset.locked === '1';
    toggle.style.opacity = toggle.disabled ? '.7' : '1';
    const current = select.options[select.selectedIndex];
    document.getElementById('destinationDropdownLabel').textContent = current ? current.textContent : 'Select Destination';
    if (toggle.disabled) { window.closeDestinationDropdown(); }
    const term = document.getElementById('destinationSearch').value.trim().toLowerCase();
    options.innerHTML = '';
    Array.from(select.options).filter(option => option.value && option.textContent.toLowerCase().includes(term)).forEach(option => {
        const button = document.createElement('button');
        button.type = 'button'; button.setAttribute('role', 'option');
        button.setAttribute('aria-selected', option.value === select.value ? 'true' : 'false');
        button.style.cssText = 'display:block;width:100%;padding:11px 10px;border:0;border-radius:8px;background:#fff;color:#24513c;text-align:left;font-size:14px;';
        button.textContent = option.textContent;
        button.addEventListener('click', function () {
            if (select.disabled || select.dataset.locked === '1') { return; }
            select.value = option.value;
            window.closeDestinationDropdown();
            window.renderDestinationDropdown();
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });
        options.appendChild(button);
    });
    if (!options.children.length) { options.textContent = 'No matching destinations'; }
};
document.getElementById('destinationDropdownToggle')?.addEventListener('click', function () {
    if (!destinationWarningAcknowledged) { showDestinationWarning(); return; }
    const panel = document.getElementById('destinationDropdownPanel');
    if (panel.hidden) { window.openDestinationDropdown(); } else { window.closeDestinationDropdown(); }
});
document.getElementById('destinationSearch')?.addEventListener('input', window.renderDestinationDropdown);
document.getElementById('destinationDropdown')?.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') { window.closeDestinationDropdown(); document.getElementById('destinationDropdownToggle').focus(); }
});
document.addEventListener('click', function (event) {
    const dropdown = document.getElementById('destinationDropdown');
    if (dropdown && !dropdown.contains(event.target)) { window.closeDestinationDropdown(); }
});
document.addEventListener('DOMContentLoaded', function () {
    window.renderDestinationDropdown();
    new MutationObserver(window.renderDestinationDropdown).observe(document.getElementById('destinationSelect'),
        { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'data-locked'] });
});

document.getElementById('destinationWarningCancel')?.addEventListener('click', ()=>{

    hideDestinationWarning();

    const destSelect = document.getElementById('destinationSelect');
    if(destSelect){
        destSelect.dataset.pendingValue = '';
        destSelect.value = destSelect.dataset.prevValue || '';
    }

});

document.getElementById('destinationWarningContinue')?.addEventListener('click', ()=>{
    acknowledgeDestinationWarning();
});

document.getElementById('destinationAgreementCheck')?.addEventListener('change', (e)=>{
    if(e.target.checked){
        const agreeError = document.getElementById('destinationAgreementError');
        if(agreeError){ agreeError.style.display = 'none'; }
    }
    syncDestinationAgreementButton();
});

(function(){
    const destSelect = document.getElementById('destinationSelect');
    if(!destSelect){ return; }

    ['mousedown','touchstart'].forEach(function(evt){
        destSelect.addEventListener(evt, function(e){
            if(destSelect.dataset.locked === '1'){
                return;
            }
            if(destinationWarningAcknowledged){
                return;
            }
            e.preventDefault();
            showDestinationWarning();
        }, {passive:false});
    });
})();

document
.getElementById(
    "boardNowBtn"
)
?.addEventListener(
    "click",
    async ()=>{

        if(isProcessing){
            return;
        }

        isProcessing = true;

        const destSelect =
            document.getElementById(
                "destinationSelect"
            );

        const payload = {

            bus_no:
                QR_BUS,

            trip_no:
                QR_TRIP || "0",

            latitude:
                userLat,

            longitude:
                userLng,

            accuracy:
                userAccuracy,

            payment_method:
                document.getElementById(
                    "paymentMethod"
                )?.value || "cash",

            cash_amount:
                Number(
                    document.getElementById(
                        "cashAmountInput"
                    )?.value || 0
                ),

            gcash_amount:
                Number(
                    document.getElementById(
                        "gcashAmountInput"
                    )?.value || 0
                )

        };

        try{

            const res =
                await fetch(
                    '/passenger/scan/store',
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/json'
                        },

                        body:
                            JSON.stringify(
                                payload
                            )
                    }
                );

            const result =
                await res.json();

            if(
                result.success
            ){

                if(
                    result.type === "boarding"
                ){
                    resetTripState();
                }

                updateTripUI(
                    result
                );

                if(window.companionSetTrip && result.trip_id){ window.companionSetTrip(result.trip_id); }

                if(
                    result.type === "boarding"
                ){

                    document.getElementById('tripDetails').style.display = 'block';
                    const _ta = document.getElementById('tripArrow');
                    if(_ta){ _ta.textContent = '▲'; }
                    saveActiveTripInfo(result);
                    startActiveTripPolling();
                }

                document.getElementById(
                    "readyBoardCard"
                ).style.display =
                    "none";

            }else{

                console.error(result);

                setStatus(
                    formatScanFailure(
                        result,
                        res.status,
                        "Boarding failed."
                    ),
                    "red"
                );

            }

        }catch(error){

            console.error(error);

            setStatus(
                window.PASSENGER_QR_SCAN_DEBUG && error && error.message
                    ? "Unable to connect to the server. [" + error.message + "]"
                    : "Unable to connect to the server.",
                "red"
            );

        }

        isProcessing = false;

    }
);

document.addEventListener(
    "DOMContentLoaded",
    async ()=>{

        // True while a paid trip's receipt is shown on load: the scanner must
        // stay off until the passenger confirms the receipt.
        let paidTripAwaitingReceipt = false;

        try{

            const tripRes =
                await fetch(
                    '/passenger/trip/active'
                );

            const tripData =
                await tripRes.json();

            /* Server-authoritative: a confirmed "no active trip" drops the
               cached trip and payment-progress values. */
            handleActiveTripSnapshot(tripData);

            if(
                tripData.hasActiveTrip
            ){

                // A running trip always shows the on-board card, even when it
                // was already paid online; the receipt waits for trip end.
                onBoard =
                    true;

                if(
                    tripData.boarding_point
                ){

                    lockedBoardingPoint =
                        tripData.boarding_point;

                }

                document.getElementById(
                    'activeBusNo'
                ).innerText =
                    tripData.bus_no || '-';

                document.getElementById(
                    'activeDriver'
                ).innerText =
                    tripData.driver_name || '-';

                document.getElementById(
                    'activeBoarding'
                ).innerText =
                    tripData.boarding_point || '-';

                document.getElementById(
                    'activeDestination'
                ).innerText =
                    tripData.destination || '-';

                document.getElementById(
                    'onBoardCard'
                ).style.display =
                    'flex';

                document.getElementById(
                    'reader'
                ).style.display =
                    'none';

                document.querySelector(
                    '.scan-box'
                ).style.display =
                    'none';

                const fb =
                    document.getElementById(
                        'noCameraFallback'
                    );

                if(fb){
                    fb.style.display = 'none';
                }

                window._activeTripId =
                    tripData.trip_id || null;

                // A locked Online Payment default shows its Ready to Pay?
                // action only while a trip is active.
                updatePaymentMethodLockState();

                startLocationTracking();

                startActiveTripPolling();

                updateTripUI({
                    bus_no: tripData.bus_no,
                    trip_no: tripData.trip_no,
                    driver_name: tripData.driver_name,
                    boarding_point: tripData.boarding_point,
                    boarding_stop_id: tripData.boarding_stop_id || 0,
                    discount_type: tripData.discount_type,
                    type: 'boarding'
                });

                refreshRouteMap();

                saveActiveTripInfo(Object.assign({}, tripData, { type: 'boarding' }));

                if(
                    tripData.trip_no
                ){

                    try{

                        const destRes =
                            await fetch(
                                '/passenger/api/destinations',
                                {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/json'},
                                    body: JSON.stringify({
                                        trip_no: tripData.trip_no,
                                        boarding_stop_id: tripData.boarding_stop_id || 0,
                                        boarding_point: tripData.boarding_point || ''
                                    })
                                }
                            );

                        const destData =
                            await destRes.json();

                        if(
                            destData.success &&
                            Array.isArray(
                                destData.destinations
                            )
                        ){

                            destinationsCache =
                                destData.destinations;

                            populateDestinations();

                        }

                    }catch(error){

                    }

                }

                const activeDestId =
                    tripData.selected_destination_id ||
                    tripData.destination_id;

                if(
                    activeDestId
                ){

                    const destSelect =
                        document.getElementById(
                            "destinationSelect"
                        );

                    if(destSelect){

                        destSelect.value =
                            activeDestId;

                        const destinationLocked =
                            tripData.destination_locked
                            === true ||
                            tripData.destination_locked
                            === "1";

                        destSelect.dataset.locked =
                            destinationLocked ? '1' : '';
                        destSelect.dataset.prevValue =
                            destSelect.value || '';

                        destSelect.disabled =
                            destinationLocked;

                        // The trip already has the destination saved: show
                        // its recorded estimate first (or resolve it from
                        // the fare-pricing source) and only re-validate
                        // with the server when no fare could be shown.
                        const shown =
                            await showRecordedFare(tripData, activeDestId);

                        if(!shown && destSelect.value){

                            updateFareDisplay();

                        }

                    }

                }else if(
                    tripData.selected_destination ||
                    tripData.destination
                ){

                    // The active trip already records a destination (shown
                    // in the trip container) even though no fare id was
                    // saved: show the stored estimate or resolve it from the
                    // fare-pricing source instead of leaving the fare card
                    // blank.
                    await showRecordedFare(tripData, 0);

                }

                if(
                    tripData.payment_method
                ){

                    // The trip's own method is what is in effect for this
                    // trip, so it takes precedence over the account default.
                    const method =
                        normalizePaymentMethod(tripData.payment_method);

                    appliedPaymentMethod = method;
                    applyPaymentMethodToUI(method);
                    updatePaymentMethodConfirmUI();

                    setPaymentMethodLocked(
                        String(tripData.payment_status || '').toLowerCase() === 'paid'
                    );

                }

            }else if(
                tripData.outstanding_payment &&
                Number(tripData.outstanding_payment.count || 0) > 0
            ){

                const outstanding =
                    tripData.outstanding_payment;

                const unpaidCount =
                    Number(outstanding.count || 0);

                const unpaidLimit =
                    Number(outstanding.limit || 2);

                if(unpaidCount >= unpaidLimit){

                    paidTripAwaitingReceipt = true;

                    setPaymentLock(
                        true,
                        outstanding.oldest_trip_id,
                        outstanding.oldest_payment_method,
                        outstanding
                    );

                }else{

                    // Pending payment below the limit: alert the passenger
                    // without blocking boarding.
                    showBalanceNotice(outstanding);

                }

                startPaymentLockPolling();

            }

        }catch(error){

            /* The active-trip check failed (offline or server error): do not
               render cached trip data as current; offer a retry instead. */
            handleActiveTripSnapshot(null);
        }

        try{
            await fetchActiveTrips();

            if(onBoard){
                await applyRestoredState();

                restoreHomepageState();
            }else{
                restoreHomepageState();
            }
        }finally{

            restoringHomepageState = false;
        }

        restoreActiveTripInfo();

        const ok =
            await requestLocation();

        if(!ok){
            return;
        }

        try{

            const resolveRes =
                await fetch(
                    '/passenger/api/resolve-stop',
                    {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({lat: userLat, lng: userLng})
                    }
                );

            const resolveData =
                await resolveRes.json();

            if(
                resolveData.success &&
                resolveData.stop
            ){

                currentBoardingPoint =
                    resolveData.stop.destination;

                const bpEl =
                    document.getElementById(
                        "boardingPoint"
                    );

                if(bpEl){

                    bpEl.innerText =
                        currentBoardingPoint;

                }

            }else{

                applyDefaultLocationFallback(
                    "No nearby stop found — using default location"
                );

            }

        }catch(error){

            applyDefaultLocationFallback();
        }

        if(!onBoard && !paidTripAwaitingReceipt && !paymentLocked){
            startScanner();
        }

    }
);

window.addEventListener(
    "pageshow",
    (event)=>{

        if(event.persisted || document.visibilityState === 'visible'){

            if(html5QrCode){
                try{
                    html5QrCode.stop();
                }catch(e){}
                try{
                    html5QrCode.clear();
                }catch(e){}
                html5QrCode = null;
                scannerRunning = false;
                hideCameraToggle();
            }

            restoringHomepageState = true;
            _restoreGuardUntil = Date.now() + 3000;
            (async () => {
                try {
                    if(onBoard){
                        await applyRestoredState();

                        restoreHomepageState();
                    }else{
                        restoreHomepageState();
                    }
                    restoreActiveTripInfo();
                } finally {
                    restoringHomepageState = false;
                }
            })();

            if(onBoard){
                checkActiveTripCompletion();
                checkContinuationState();
            }

        }

    }
);
function showTripModal(result){

    const payMethod = (result.payment_method || '').toLowerCase();
    const tripId = result.trip_id || '';

    if(payMethod === 'gcash' || payMethod === 'hybrid'){

        if(tripId){
            openPaymentModal(tripId);
        } else {
            const tm = document.getElementById('tripModal');
            if(tm){
                tm.style.display = 'flex';
            }
        }
        return;
    }

    if(!tripId){
        const tm = document.getElementById('tripModal');
        if(tm){
            tm.style.display = 'flex';
        }
        return;
    }

    openTripReceipt(tripId);

}

async function openTripReceipt(id){

    const modal = document.getElementById('tripModal');
    const content = document.getElementById('tripModalContent');

    if(!modal || !content){
        return;
    }

    // Remember which trip this receipt belongs to so confirming it can close
    // the trip on the server and it never reappears.
    window._receiptTripId = id || null;

    modal.style.display = 'flex';

    content.innerHTML =
        '<div class="text-center py-5" style="width:95%;max-width:700px;margin:0 auto;">' +
            '<div class="spinner-border text-success" role="status"></div>' +
            '<div class="mt-2 text-muted" style="font-size:12px;">Loading trip details...</div>' +
        '</div>';

    try{

        const res = await fetch(
            '/passenger/trip/modal',
            {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            }
        );

        if(!res.ok){
            throw new Error('Failed to load');
        }

        const text = await res.text();

        content.innerHTML = text;

        content.querySelectorAll('script').forEach(function(s){
            const ns = document.createElement('script');
            ns.textContent = s.textContent;
            s.parentNode.replaceChild(ns, s);
        });

    }catch(error){

        console.error(error);

        if (window.PassengerSession && window.PassengerSession.isInvalidated) {
            return;
        }

        content.innerHTML =
            '<div class="text-center" style="color:#7b8a74;padding:40px 20px;">' +
                'Unable to load trip details right now.<br>' +
                '<small style="color:#8a9a8a;">Please try again later.</small>' +
            '</div>';

    }

}

async function openPaymentModal(id){

    const modal = document.getElementById('tripModal');
    const content = document.getElementById('tripModalContent');

    if(!modal || !content){
        return;
    }

    // A payment modal is not a receipt: closing it must not confirm/close the
    // trip on the server.
    window._receiptTripId = null;

    modal.style.display = 'flex';

    content.innerHTML =
        '<div class="text-center py-5" style="width:95%;max-width:700px;margin:0 auto;">' +
            '<div class="spinner-border text-success" role="status"></div>' +
            '<div class="mt-2 text-muted" style="font-size:12px;">Loading payment details...</div>' +
        '</div>';

    try{

        const res = await fetch(
            '/passenger/paymongo/modal',
            {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            }
        );

        if(!res.ok){
            throw new Error('Failed to load');
        }

        const text = await res.text();

        content.innerHTML = text;

        content.querySelectorAll('script').forEach(function(s){
            const ns = document.createElement('script');
            ns.textContent = s.textContent;
            s.parentNode.replaceChild(ns, s);
        });

    }catch(error){

        console.error(error);

        if (window.PassengerSession && window.PassengerSession.isInvalidated) {
            return;
        }

        content.innerHTML =
            '<div class="text-center" style="color:#7b8a74;padding:40px 20px;">' +
                'Unable to load payment details right now.<br>' +
                '<small style="color:#8a9a8a;">Please try again later.</small>' +
            '</div>';

    }

}

var pmRecoveryInFlight = false;
function tryRestorePaymentModal(){
    if(pmRecoveryInFlight){
        return;
    }
    pmRecoveryInFlight = true;

    try{

        const openModal = document.getElementById('tripModal');
        if(openModal && openModal.style.display === 'flex'){
            return;
        }

        let cached = null;
        try{
            const raw = localStorage.getItem(TRIP_INFO_KEY);
            if(raw){ cached = JSON.parse(raw); }
        }catch(err){}

        if(!cached || !cached.trip_id || cached.active === true){
            return;
        }

        const method = String(cached.payment_method || '').toLowerCase();
        if(method !== 'gcash' && method !== 'hybrid'){
            return;
        }

        if(Date.now() - (cached.savedAt || 0) > 4 * 60 * 60 * 1000){
            return;
        }

        const tripId = cached.trip_id;

        fetch('/passenger/paymongo/status', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({trip_id: tripId})
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if(openModal && openModal.style.display === 'flex'){
                return;
            }
            const s = String(data.payment_status || '').toLowerCase();
            if(s === 'paid'){

                openTripReceipt(tripId);
                try{ localStorage.removeItem(TRIP_INFO_KEY); }catch(err){}
            }else{
                openPaymentModal(tripId);
            }
        })
        .catch(function(){
            if(openModal && openModal.style.display === 'flex'){
                return;
            }
            openPaymentModal(tripId);
        });

    }finally{
        pmRecoveryInFlight = false;
    }
}

document.addEventListener('DOMContentLoaded', function(){
    tryRestorePaymentModal();
});

window.addEventListener('pageshow', function(event){
    if(event.persisted || document.visibilityState === 'visible'){
        tryRestorePaymentModal();
    }
});
function resetTripState() {

    if (_saveStateTimer) {
        clearTimeout(_saveStateTimer);
        _saveStateTimer = null;
    }

    onBoard = false;
    lockedBoardingPoint = null;
    currentBoardingPoint = "";
    window._activeTripId = null;
    isProcessing = false;

    // A new trip gets a fresh, unlocked destination selector.
    const destSelectReset =
        document.getElementById('destinationSelect');
    destinationWarningAcknowledged = false;
    onlinePayPrompted = false;

    if(destSelectReset){
        destSelectReset.dataset.locked = '';
        destSelectReset.dataset.prevValue = '';
        destSelectReset.dataset.pendingValue = '';
        destSelectReset.disabled = false;
    }

    stopActiveTripPolling();

    stopLocationTracking();

    try { localStorage.removeItem(TRIP_INFO_KEY); } catch (e) {}
    clearHomepageState();

    ['hp_payment_method','hp_destination_id','hp_trip_details_open'].forEach(
        k => sessionStorage.removeItem(k)
    );

    try {
        fetch('/passenger/trip/clear-state', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'}
        }).catch(() => {});
    } catch (e) {}

    document.getElementById("boardingPoint").innerText = "Not boarded yet";
    document.getElementById("busInfo").innerText = "—";
    document.getElementById("driverInfo").innerText = "—";
    document.getElementById("tripNoInfo").innerText = "—";
    document.getElementById("passengerType").innerText = "—";

    ['activeBusNo','activeDriver','activeBoarding','activeDestination'].forEach((id) => {
        const el = document.getElementById(id);
        if(el){ el.innerText = '—'; }
    });

    const onBoardContainer = document.getElementById('onBoardCard');
    if(onBoardContainer){ onBoardContainer.style.display = 'none'; }

    const tripStatus = document.getElementById("tripStatus");
    tripStatus.innerText = "Pending";
    tripStatus.style.background = "#fff4d6";
    tripStatus.style.color = "#b7791f";

    destinationsCache = [];

    const destSelect = document.getElementById("destinationSelect");
    destSelect.disabled = true;
    destSelect.value = "";
    destSelect.innerHTML = '<option value="">' + <?= json_encode(passenger_t('home.board_first', 'Board first before selecting destination'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> + '</option>';

    const destError = document.getElementById("destinationError");
    if(destError){ destError.style.display = "none"; }

    document.getElementById("fareRate").innerText = "--";

    // A finished trip resets the selection to the account's saved default.
    appliedPaymentMethod = accountDefaultPaymentMethod;
    paymentMethodConfirmedScope = '';
    applyPaymentMethodToUI(accountDefaultPaymentMethod);
    updatePaymentMethodLockState();

    const cashEl = document.getElementById('cashAmountInput');
    if(cashEl){ cashEl.value = '0'; }

    const gcashEl = document.getElementById('gcashAmountInput');
    if(gcashEl){ gcashEl.value = '0'; }

    // The hybrid breakdown visibility is owned by applyPaymentMethodToUI()
    // above so a saved hybrid default still shows its amount fields.

    const manualEl = document.getElementById('manualBusCode');
    if(manualEl){ manualEl.value = ''; }

    const tripDetails = document.getElementById("tripDetails");
    tripDetails.style.display = "none";
    document.getElementById("tripArrow").textContent = "▼";

    clearRouteMap();

    lastDestinationFetch = null;
    hideDestinationRetry();
}

// Confirm a receipt: close the modal, close the paid trip on the server so it
// never reappears, clear the local trip state, and return to the scanner.
async function closeTripReceipt(){

    const modal = document.getElementById('tripModal');
    if(modal){ modal.style.display = 'none'; }

    const tripId = window._receiptTripId || null;
    window._receiptTripId = null;

    // Still on board: the modal is a payment/receipt preview only. Closing it
    // must not clear the trip or unlock scanning.
    if(onBoard){
        return;
    }

    if(tripId){
        try{
            const res = await fetch('/passenger/trip/confirm-receipt', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ trip_id: tripId })
            });
            const data = await res.json();

            if(data && (data.completed === true || (!data.code && data.success))){
                // Trip closed (or already closed): refresh the balance lock.
                await refreshOutstandingLock();
            }else if(data && data.code === 'PAYMENT_PENDING'){
                // Finished but unpaid: lock only when the balance limit is
                // reached.
                const locked = await refreshOutstandingLock();

                if(locked){
                    startPaymentLockPolling();
                }
            }
        }catch(err){}
    }

    resetTripState();

    if(!onBoard && !paymentLocked){
        startScanner();
    }
}

document.getElementById("closeModalBtn").addEventListener("click", ()=>{
    closeTripReceipt();
});

// "Confirm & Scan Again" on a paid receipt.
window.confirmTripReceipt = function(){
    closeTripReceipt();
};

document.getElementById("tripModal").addEventListener("click", (e)=>{
    if(e.target.id === "tripModal"){
        closeTripReceipt();
    }
});
document.addEventListener('DOMContentLoaded', () => {

    const toggle = document.getElementById('tripToggle');
    const details = document.getElementById('tripDetails');
    const arrow = document.getElementById('tripArrow');

    toggle.addEventListener('click', () => {

        const opened = details.style.display === 'block';

        details.style.display = opened ? 'none' : 'block';
        arrow.textContent = opened ? '▼' : '▲';

        saveHomepageState();

        saveTripState({ trip_details_open: opened ? 'false' : 'true' });

    });

});

document.addEventListener('input', (e) => {

    if(
        !e.target ||
        (e.target.id !== 'cashAmountInput' && e.target.id !== 'gcashAmountInput')
    ){
        return;
    }

    saveHomepageState();

    const cash = document.getElementById('cashAmountInput')?.value || '0';
    const gcash = document.getElementById('gcashAmountInput')?.value || '0';
    debouncedSaveTripState({ cash_amount: cash, gcash_amount: gcash });

});

const PAYMENT_METHOD_STRINGS = {
    selectedUnconfirmed: <?= json_encode(passenger_t('home.payment_method_selected_unconfirmed', 'Selected: :method — not yet confirmed.', ['method' => '__METHOD__']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    selectedNoDefault: <?= json_encode(passenger_t('home.payment_method_selected_no_default', 'Selected: :method — not saved as default.', ['method' => '__METHOD__']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    confirmedDefault: <?= json_encode(passenger_t('home.payment_method_confirmed_default', 'Confirmed: :method — saved as your default.', ['method' => '__METHOD__']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    confirmedTransaction: <?= json_encode(passenger_t('home.payment_method_confirmed_transaction', 'Confirmed: :method — for this transaction only.', ['method' => '__METHOD__']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    current: <?= json_encode(passenger_t('home.payment_method_current', 'Current: :method', ['method' => '__METHOD__']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    savedDefault: <?= json_encode(passenger_t('home.payment_method_default_label', 'Default: :method', ['method' => '__METHOD__']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    confirmLabel: <?= json_encode(passenger_t('home.confirm_payment_method', 'Confirm Payment Method'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    confirmedLabel: <?= json_encode(passenger_t('home.payment_method_confirmed_label', 'Payment Method Confirmed'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    confirmFailed: <?= json_encode(passenger_t('home.payment_method_confirm_failed', 'We could not save your payment method right now. Please try again.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    connectionFailed: <?= json_encode(passenger_t('home.payment_method_connection_failed', 'We could not update your payment method right now. Please check your connection and try again.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};

let accountDefaultPaymentMethod = <?= json_encode($defaultPaymentMethod, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let hasSavedPaymentDefault = <?= $hasSavedPaymentDefaultMethod ? 'true' : 'false' ?>;
let appliedPaymentMethod = accountDefaultPaymentMethod;
let selectedPaymentMethod = appliedPaymentMethod;
let paymentMethodConfirmedScope = '';
let paymentMethodLocked = false;
// A saved default locks the homepage section: it can only be changed in
// Profile > Settings, so the confirm flow is removed entirely.
let paymentMethodPreferenceLocked = hasSavedPaymentDefault;

function normalizePaymentMethod(method){

    method = String(method || 'cash').toLowerCase();

    return (method === 'gcash' || method === 'hybrid') ? method : 'cash';

}

// The selection is used for the current trip right away (the hidden input
// feeds boarding/payment). Confirming only decides whether it also becomes
// the saved default.
function setPaymentMethodSelection(method){

    selectedPaymentMethod = normalizePaymentMethod(method);

    document.querySelectorAll('.payment-option').forEach(function(b){
        b.classList.toggle('active', b.dataset.method === selectedPaymentMethod);
    });

    const hybridBreakdown = document.getElementById('hybridBreakdown');
    if(hybridBreakdown){
        hybridBreakdown.style.display = selectedPaymentMethod === 'hybrid' ? 'block' : 'none';
    }

    const pmEl = document.getElementById('paymentMethod');
    if(pmEl){ pmEl.value = selectedPaymentMethod; }

}

// Apply a method everywhere the selection is shown/used.
function applyPaymentMethodToUI(method){

    setPaymentMethodSelection(method);

}

function updatePaymentMethodLockState(){

    const disabled = paymentMethodLocked || paymentMethodPreferenceLocked;

    document.querySelectorAll('.payment-option').forEach(function(b){
        b.disabled = disabled;
    });

    const paidHint = document.getElementById('paymentMethodLockedHint');
    if(paidHint){
        paidHint.style.display = paymentMethodLocked ? 'flex' : 'none';
    }

    const preferenceHint = document.getElementById('paymentMethodDefaultLockedHint');
    if(preferenceHint){
        preferenceHint.style.display = (paymentMethodPreferenceLocked && !paymentMethodLocked) ? 'flex' : 'none';
    }

    updatePaymentMethodConfirmUI();

}

function updatePaymentMethodConfirmUI(){

    const confirmBtn = document.getElementById('paymentMethodConfirmBtn');
    const status = document.getElementById('paymentMethodConfirmStatus');
    const statusText = document.getElementById('paymentMethodConfirmStatusText');
    const readyBtn = document.getElementById('paymentMethodReadyPayBtn');
    if(!confirmBtn){ return; }

    // A paid trip locks everything: nothing can be changed or paid again.
    if(paymentMethodLocked){
        confirmBtn.style.display = 'none';
        if(status){ status.style.display = 'none'; }
        if(readyBtn){ readyBtn.style.display = 'none'; }
        return;
    }

    // A saved default locks the section (changed only in Profile > Settings).
    // When that default is Online Payment, the confirm flow is replaced by a
    // direct "Ready to Pay?" action while the passenger is on board.
    if(paymentMethodPreferenceLocked){
        confirmBtn.style.display = 'none';
        if(status){ status.style.display = 'none'; }
        if(readyBtn){
            const onTrip = !!window._activeTripId || onBoard;
            const onlineDefault = accountDefaultPaymentMethod === 'gcash';
            readyBtn.style.display = (onlineDefault && onTrip) ? 'block' : 'none';
        }
        return;
    }

    confirmBtn.style.display = '';
    if(readyBtn){ readyBtn.style.display = 'none'; }

    const selected = selectedPaymentMethod;
    const label = formatPaymentMethodLabel(selected) || selected;
    const forThisTripOnly = paymentMethodConfirmedScope === 'transaction';

    const text = forThisTripOnly
        ? PAYMENT_METHOD_STRINGS.confirmedTransaction.replace('__METHOD__', label)
        : PAYMENT_METHOD_STRINGS.selectedNoDefault.replace('__METHOD__', label);

    confirmBtn.disabled = false;
    confirmBtn.style.opacity = '1';
    confirmBtn.style.cursor = 'pointer';
    confirmBtn.textContent = PAYMENT_METHOD_STRINGS.confirmLabel;

    if(status){
        status.style.display = text ? 'flex' : 'none';
        status.style.color = forThisTripOnly ? '#2f6b43' : '#7b8a74';
    }
    if(statusText){
        statusText.textContent = text;
    }

}

function showPaymentMethodConfirmPrompt(){

    const method = selectedPaymentMethod;
    const choice = document.getElementById('paymentMethodConfirmChoice');
    if(choice){ choice.textContent = formatPaymentMethodLabel(method) || method; }

    const modal = document.getElementById('paymentMethodConfirmModal');
    if(modal){ modal.style.display = 'flex'; }

}

function hidePaymentMethodConfirmPrompt(){

    const modal = document.getElementById('paymentMethodConfirmModal');
    if(modal){ modal.style.display = 'none'; }

}

async function confirmSelectedPaymentMethod(saveAsDefault){

    hidePaymentMethodConfirmPrompt();

    const method = selectedPaymentMethod;
    const methodError = document.getElementById('paymentMethodError');
    if(methodError){ methodError.style.display = 'none'; }

    try{

        const res = await fetch('/passenger/trip/payment-method', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({
                payment_method: method,
                save_as_default: !!saveAsDefault
            })
        });

        const data = await res.json();

        if(!data || !data.success){

            // The choice is already applied to this trip, so a failed save
            // only means the default was not updated.
            if(data && data.code === 'ACTIVE_QR'){
                showActiveQrModal();
                updatePaymentMethodConfirmUI();
                return;
            }

            if(methodError){
                methodError.textContent = friendlyPaymentMethodError(data);
                methodError.style.display = 'block';
            }
            updatePaymentMethodConfirmUI();
            return;

        }

        appliedPaymentMethod = method;
        paymentMethodConfirmedScope = (data.saved_default && saveAsDefault) ? 'default' : 'transaction';

        if(data.saved_default && saveAsDefault){
            accountDefaultPaymentMethod = method;
            hasSavedPaymentDefault = true;
            // From now on the homepage section is locked and the method can
            // only be changed from Profile > Settings.
            paymentMethodPreferenceLocked = true;
        }

        applyPaymentMethodToUI(method);

        saveHomepageState();
        saveTripState({ payment_method: method });
        updatePaymentMethodLockState();

        // Continue payment: the Ready to Pay offer only appears after the
        // method is confirmed (never while the passenger is still choosing).
        maybePromptOnlinePayment(false);

    }catch(err){

        if(methodError){
            methodError.textContent = PAYMENT_METHOD_STRINGS.connectionFailed;
            methodError.style.display = 'block';
        }
        updatePaymentMethodConfirmUI();

    }

}

document.getElementById('paymentMethodConfirmBtn')?.addEventListener('click', showPaymentMethodConfirmPrompt);

document.getElementById('paymentMethodConfirmYes')?.addEventListener('click', function(){
    confirmSelectedPaymentMethod(true);
});

document.getElementById('paymentMethodConfirmNo')?.addEventListener('click', function(){
    confirmSelectedPaymentMethod(false);
});

document.getElementById('paymentMethodConfirmModal')?.addEventListener('click', function(e){
    if(e.target.id === 'paymentMethodConfirmModal'){
        hidePaymentMethodConfirmPrompt();
    }
});

// Locked Online Payment default: pay directly with the QR Ph code.
document.getElementById('paymentMethodReadyPayBtn')?.addEventListener('click', function(){
    if(window._activeTripId){
        openPaymentModal(window._activeTripId);
    }
});

document.getElementById('paymentMethods')
.addEventListener('click', async (e) => {

    const btn = e.target.closest('.payment-option');

    if(!btn || btn.disabled){
        return;
    }

    const methodError =
        document.getElementById('paymentMethodError');

    if(methodError){ methodError.style.display = 'none'; }

    const previousMethod = selectedPaymentMethod;

    // A fresh choice invalidates the previous confirmation feedback.
    paymentMethodConfirmedScope = '';

    // The choice applies to the current trip right away; no alert or
    // confirmation is shown while selecting. Confirming only decides whether
    // it also becomes the saved default.
    setPaymentMethodSelection(btn.dataset.method);

    saveHomepageState();

    updatePaymentMethodConfirmUI();

    // Persist the choice on the active trip so the driver feed and the
    // payment modal use it (this trip only; the saved default is unchanged).
    if(window._activeTripId){

        try{

            const res = await fetch('/passenger/trip/payment-method', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({
                    payment_method: btn.dataset.method,
                    save_as_default: false
                })
            });

            const data = await res.json();

            if(!data || !data.success){

                setPaymentMethodSelection(previousMethod);
                updatePaymentMethodConfirmUI();

                // An active QR Ph code must not be switched away from (the
                // passenger could pay twice): explain it in a designed modal.
                if(data && data.code === 'ACTIVE_QR'){
                    showActiveQrModal();
                    return;
                }

                if(methodError){
                    methodError.textContent = friendlyPaymentMethodError(data);
                    methodError.style.display = 'block';
                }

                return;

            }

            appliedPaymentMethod = btn.dataset.method;

        }catch(err){

            setPaymentMethodSelection(previousMethod);
            updatePaymentMethodConfirmUI();

            if(methodError){
                methodError.textContent = PAYMENT_METHOD_STRINGS.connectionFailed;
                methodError.style.display = 'block';
            }

            return;

        }

    }

});

updatePaymentMethodLockState();

let onlinePayPrompted = false;

function setPaymentMethodLocked(locked){

    paymentMethodLocked = !!locked;

    updatePaymentMethodLockState();

}

// Called when the payment modal detects that the trip is paid.
window.onTripPaid = function(){
    setPaymentMethodLocked(true);
};

function friendlyPaymentMethodError(data){

    const code = (data && data.code) || '';

    if(code === 'ALREADY_PAID'){
        return 'This trip is already paid, so the payment method can no longer be changed.';
    }

    if(code === 'NO_ACTIVE_TRIP'){
        return 'Your trip has ended. Please refresh the page.';
    }

    return 'We could not update your payment method right now. Please try again.';

}

function showActiveQrModal(){

    const modal = document.getElementById('activeQrModal');
    if(modal){ modal.style.display = 'flex'; }

}

function hideActiveQrModal(){

    const modal = document.getElementById('activeQrModal');
    if(modal){ modal.style.display = 'none'; }

}

document.getElementById('activeQrKeep')?.addEventListener('click', ()=>{
    hideActiveQrModal();
});

document.getElementById('activeQrView')?.addEventListener('click', ()=>{

    hideActiveQrModal();

    if(window._activeTripId){
        openPaymentModal(window._activeTripId);
    }

});

function showPaymentReadyPrompt(){

    const modal = document.getElementById('paymentReadyModal');
    if(modal){ modal.style.display = 'flex'; }

}

function hidePaymentReadyPrompt(){

    const modal = document.getElementById('paymentReadyModal');
    if(modal){ modal.style.display = 'none'; }

}

function maybePromptOnlinePayment(force){

    const method =
        String(document.getElementById('paymentMethod')?.value || 'cash').toLowerCase();

    if(method !== 'gcash' && method !== 'hybrid'){
        return;
    }

    const destSelect = document.getElementById('destinationSelect');
    if(!destSelect || !destSelect.value){
        return;
    }

    if(!window._activeTripId){
        return;
    }

    if(onlinePayPrompted && !force){
        return;
    }

    onlinePayPrompted = true;

    showPaymentReadyPrompt();

}

document.getElementById('paymentReadyLater')?.addEventListener('click', ()=>{
    hidePaymentReadyPrompt();
});

document.getElementById('paymentReadyNow')?.addEventListener('click', ()=>{

    hidePaymentReadyPrompt();

    if(window._activeTripId){
        openPaymentModal(window._activeTripId);
    }

});

const HOME_FARE_RETRY = <?= json_encode(passenger_t('home.fare_update_retry', 'We could not update your fare right now. Please try again.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

const HOME_FARE_UNAVAILABLE = <?= json_encode(passenger_t('home.fare_unavailable', 'Fare unavailable'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function normalizeStopNameForFare(value){

    return String(value || '')
        .toLowerCase()
        .replace(/,\s*nueva ecija|,\s*cabanatuan city|,\s*cabanatuan/g, '')
        .replace(/\bcity\b/g, '')
        .replace(/[^a-z0-9 ]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

}

// Find the fare-pricing stop whose name matches the given location name.
// Matching mirrors the fare engine's findFareByDestination(): exact normalized
// match, then containment, then word-level matches, then the Cabanatuan
// terminal fallback.
function findFarePricingStopByName(name){

    if(!Array.isArray(destinationsCache) || !destinationsCache.length){
        return null;
    }

    const target =
        normalizeStopNameForFare(name);

    if(!target){
        return null;
    }

    const stops =
        destinationsCache.filter(
            (stop)=> stop && stop.destination
        );

    const exact =
        stops.find(
            (stop)=> normalizeStopNameForFare(stop.destination) === target
        );

    if(exact){
        return exact;
    }

    const partial =
        stops.find(
            (stop)=>{
                const stopName =
                    normalizeStopNameForFare(stop.destination);
                return stopName && (
                    target.indexOf(stopName) !== -1 ||
                    stopName.indexOf(target) !== -1
                );
            }
        );

    if(partial){
        return partial;
    }

    const wordMatch =
        stops.find(
            (stop)=> normalizeStopNameForFare(stop.destination)
                .split(' ')
                .some(
                    (word)=> word.length >= 4 && target.indexOf(word) !== -1
                )
        );

    if(wordMatch){
        return wordMatch;
    }

    const reverseWordMatch =
        stops.find(
            (stop)=>{
                const stopName =
                    normalizeStopNameForFare(stop.destination);
                return target
                    .split(' ')
                    .some(
                        (word)=> word.length >= 4 && stopName.indexOf(word) !== -1
                    );
            }
        );

    if(reverseWordMatch){
        return reverseWordMatch;
    }

    if(target.indexOf('cabanatuan') !== -1){
        const terminal =
            stops.find(
                (stop)=> normalizeStopNameForFare(stop.destination)
                    .indexOf('cabanatuan central terminal') !== -1
            );
        if(terminal){
            return terminal;
        }
    }

    return null;

}

// Resolve the boarding stop's fare_pricing row from the destination list
// already fetched for this trip (the fare-pricing source). The list flags the
// boarding stop when the server could map it; otherwise the boarding point
// name is matched against the same fare-pricing stop names.
function resolveFarePricingBoardingStop(){

    if(!Array.isArray(destinationsCache) || !destinationsCache.length){
        return null;
    }

    const flagged =
        destinationsCache.find(
            (stop)=> stop && stop.is_boarding === true
        );

    if(flagged){
        return flagged;
    }

    const boardingName =
        String(
            currentBoardingPoint ||
            lockedBoardingPoint ||
            (document.getElementById('activeBoarding')?.innerText || '')
        ).trim();

    if(
        !boardingName ||
        boardingName === '—' ||
        boardingName === '-'
    ){
        return null;
    }

    return findFarePricingStopByName(boardingName);

}

// Fare for the selected boarding point + destination from the existing
// fare-pricing endpoint (fare_pricing rows plus the passenger's discount).
// Returns null when no fare can be resolved.
async function fetchFareFromPricing(destinationId){

    const boardingStop =
        resolveFarePricingBoardingStop();

    if(!boardingStop || !boardingStop.id){
        return null;
    }

    try{

        const response =
            await fetch(
                '/passenger/api/calculate-fare',
                {
                    method:'POST',
                    headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({
                        boarding_id: boardingStop.id,
                        destination_id: destinationId
                    })
                }
            );

        const data =
            await response.json();

        const fare =
            Number(data && data.success ? data.fare : 0);

        return fare > 0 ? fare : null;

    }catch(error){

        return null;

    }

}

// Mirror of the fare engine's rule against the fare-pricing rows already
// fetched for the trip: boarding at the terminal (display_order 1) charges the
// destination fare; otherwise the difference with the ₱15 minimum.
function computeFareFromPricingRows(boardingStop, destinationStop, discountType){

    if(!boardingStop || !destinationStop){
        return null;
    }

    const type =
        String(discountType || 'regular').toLowerCase().trim();

    const fareColumn =
        ['student', 'senior', 'senior citizen', 'pwd'].indexOf(type) !== -1
            ? 'student_fare'
            : (['teacher', 'nurse', 'special'].indexOf(type) !== -1
                ? 'special_fare'
                : 'regular_fare');

    const minimumFare = 15;

    const destinationRaw =
        destinationStop[fareColumn];

    const destinationFare =
        (destinationRaw === null || destinationRaw === undefined)
            ? minimumFare
            : Number(destinationRaw);

    if(!(destinationFare > 0)){
        return null;
    }

    if(Number(boardingStop.display_order) === 1){
        return destinationFare;
    }

    const boardingRaw =
        boardingStop[fareColumn];

    const boardingFare =
        (boardingRaw === null || boardingRaw === undefined)
            ? minimumFare
            : Number(boardingRaw);

    return Math.max(destinationFare - boardingFare, minimumFare);

}

// Fare for a destination without depending on the destination-save endpoint:
// compute from the fare-pricing rows already fetched for the trip, falling
// back to the fare-pricing endpoint. Returns null when unresolvable.
async function resolveFallbackFare(destinationId, destinationName, discountType){

    const id =
        Number(destinationId || 0);

    let destinationStop = null;

    if(id && Array.isArray(destinationsCache)){
        destinationStop =
            destinationsCache.find(
                (stop)=> Number(stop.id) === id
            ) || null;
    }

    const name =
        String(
            destinationName ||
            (destinationStop && destinationStop.destination) ||
            ''
        ).trim();

    if(!destinationStop && name){
        destinationStop =
            findFarePricingStopByName(name);
    }

    const boardingStop =
        resolveFarePricingBoardingStop();

    if(boardingStop && destinationStop){

        const localFare =
            computeFareFromPricingRows(
                boardingStop,
                destinationStop,
                discountType
            );

        if(localFare !== null){
            return localFare;
        }

    }

    if(id){
        return await fetchFareFromPricing(id);
    }

    return null;

}

// The trip can already carry a selected destination (shown in the active trip
// container) before the dropdown is populated with fare ids. Show the trip's
// stored estimate, or compute the fare from the fare-pricing rows already
// fetched for the trip, instead of leaving the fare card blank.
async function showRecordedFare(tripData, destinationId){
    // Restore the same authoritative party quote used by companion/payment UI.
    if (window.regularPartyApply && tripData && tripData.party && window.regularPartyApply(tripData.party)) { return true; }
    if (window.regularPartyRefresh && await window.regularPartyRefresh()) { return true; }

    const fareEl =
        document.getElementById('fareRate');

    if(!fareEl){
        return false;
    }

    const storedFare =
        Number(tripData && tripData.estimated_fare);

    if(storedFare > 0){

        fareEl.textContent =
            '₱' + storedFare.toFixed(2);

        return true;

    }

    const destinationName =
        String(
            (tripData && (
                tripData.selected_destination ||
                tripData.destination
            )) || ''
        ).trim();

    if(!destinationName && !Number(destinationId || 0)){
        return false;
    }

    const fare =
        await resolveFallbackFare(
            destinationId,
            destinationName,
            (tripData && tripData.discount_type) || activeDiscountType
        );

    if(fare !== null){

        fareEl.textContent =
            '₱' + fare.toFixed(2);

        return true;

    }

    fareEl.textContent =
        HOME_FARE_UNAVAILABLE;

    return false;

}

async function updateFareDisplay(promptOnlinePayment = false){
    const fareRequest = window._regularFareRequest = (window._regularFareRequest || 0) + 1;

    const fareEl =
        document.getElementById(
            'fareRate'
        );

    if(!fareEl){
        return;
    }

    const destSelect =
        document.getElementById(
            'destinationSelect'
        );

    const destId =
        destSelect?.value || '';

    const destOption =
        destSelect && destSelect.selectedIndex >= 0
            ? destSelect.options[destSelect.selectedIndex]
            : null;

    const destName =
        (destOption && destOption.dataset && destOption.dataset.destination) || '';

    if(!destId){

        fareEl.textContent =
            'Select destination';

        return;

    }

    const activeTripId =
        window._activeTripId || null;

    if(!activeTripId){

        fareEl.textContent =
            'Board first to view estimate';

        return;

    }

    fareEl.textContent =
        'Calculating...';

    try{

        const response = window.regularPartySaveDestination
            ? await window.regularPartySaveDestination(destId)
            : await fetch(
                '/passenger/trip/select-destination',
                {
                    method:'POST',

                    headers:{
                        'Content-Type':
                        'application/json'
                    },

                    body:
                        JSON.stringify({
                            destination_id:
                                destId
                        })
                }
            );

        const text =
            await response.text();

        let result;

        try{

            const cleanedResponse =
                text.trim();

            const firstBrace =
                cleanedResponse.indexOf('{');

            if(firstBrace < 0){

                throw new Error(
                    'No JSON in response'
                );

            }

            result =
                JSON.parse(
                    cleanedResponse.substring(
                        firstBrace
                    )
                );

        }catch(error){

            console.error(
                'Fare estimate: invalid JSON response:',
                text.slice(0, 500)
            );

            const fallbackFare =
                await resolveFallbackFare(
                    destId,
                    destName,
                    activeDiscountType
                );

            fareEl.textContent =
                fallbackFare !== null
                    ? '₱' + fallbackFare.toFixed(2)
                    : HOME_FARE_RETRY;

            return;

        }

        if (fareRequest !== window._regularFareRequest || activeTripId !== window._activeTripId) { return; }
        if(!result.success){

            const failCode =
                String(result.code || '');

            // The destination was already saved: lock the dropdown and keep
            // the stored selection.
            if(failCode === 'DESTINATION_LOCKED'){

                const lockedSelect =
                    document.getElementById('destinationSelect');

                let lockedFareRefresh = false;

                if(lockedSelect){

                    if(result.selected_destination_id){
                        lockedSelect.value =
                            String(result.selected_destination_id);
                        lockedFareRefresh = true;
                    }

                    lockedSelect.dataset.locked = '1';
                    lockedSelect.dataset.prevValue =
                        lockedSelect.value || '';
                    lockedSelect.disabled = true;

                }

                // Re-estimate against the saved destination: re-submitting
                // the locked selection is allowed and restores the fare
                // instead of leaving the card stuck on "Calculating...".
                if(lockedFareRefresh){
                    updateFareDisplay();
                }

                return;

            }

            // The server could not estimate a fare for this selection: fall
            // back to the fare-pricing source for the same boarding point and
            // destination, and surface an explicit unavailable state when
            // neither can produce an amount.
            if(
                failCode === 'FARE_UNAVAILABLE' ||
                failCode === 'FARE_CALC_FAILED' ||
                failCode === 'BOARDING_ZONE_UNAVAILABLE'
            ){

                const fallbackFare =
                    await resolveFallbackFare(
                        destId,
                        destName,
                        activeDiscountType
                    );

                fareEl.textContent =
                    fallbackFare !== null
                        ? '₱' + fallbackFare.toFixed(2)
                        : HOME_FARE_UNAVAILABLE;

                return;

            }

            // The list on screen no longer matches the active trip (for
            // example after the trip changed). Reload it for the current
            // trip so the passenger can pick a valid stop again.
            if(failCode === 'ROUTE_MISMATCH'){

                try{

                    const tripRes =
                        await fetch(
                            '/passenger/trip/active'
                        );

                    const tripData =
                        await tripRes.json();

                    if(
                        tripData &&
                        tripData.hasActiveTrip &&
                        tripData.trip_no
                    ){

                        await fetchDestinations(
                            tripData.trip_no,
                            tripData.boarding_stop_id || 0,
                            tripData.boarding_point || ''
                        );

                        fareEl.textContent =
                            'Select destination';

                    }

                }catch(refreshError){

                    console.error(
                        'Destination refresh failed:',
                        refreshError
                    );

                }

            }

            // Never surface the opaque global handler text for a destination
            // action: server/database failures and unstructured bodies get a
            // retryable message, known actionable codes keep their message.
            if(
                failCode === 'DB_UNAVAILABLE' ||
                failCode === 'SERVER_ERROR' ||
                failCode === ''
            ){

                // The service could not re-confirm the estimate right now.
                // The fare-pricing rows already loaded for this trip still
                // let us show the correct amount instead of a retry text.
                const fallbackFare =
                    await resolveFallbackFare(
                        destId,
                        destName,
                        activeDiscountType
                    );

                fareEl.textContent =
                    fallbackFare !== null
                        ? '₱' + fallbackFare.toFixed(2)
                        : HOME_FARE_RETRY;

            }else{
                fareEl.textContent = result.message || HOME_FARE_RETRY;
            }

            return;

        }

        let fare =
            Number(
                (result.party && result.party.quote_available ? result.party.party_total : 0) ||
                result.estimated_fare ||
                result.fare ||
                0
            );

        if(!(fare > 0)){

            // The destination was saved but no estimate came back: resolve
            // the amount from the fare-pricing source before showing it as
            // unavailable.
            const fallbackFare =
                await resolveFallbackFare(
                    destId,
                    destName,
                    activeDiscountType
                );

            fare = fallbackFare !== null ? fallbackFare : 0;

        }

        if (result.party && window.regularPartyApply) {
            // A companion write may have completed while this destination
            // response was in flight. Never paint an older party/subtotal first.
            if (!window.regularPartyApply(result.party) && window.regularPartyRefresh) { await window.regularPartyRefresh(); }
        } else {
            fareEl.textContent = fare > 0 ? '₱' + fare.toFixed(2) : HOME_FARE_UNAVAILABLE;
        }

        if (window.regularTripDestinationUpdate) { window.regularTripDestinationUpdate(result); }
        // Destination saved: lock the dropdown so it cannot be changed.
        if(destSelect){

            const destinationLocked = result.destination_locked !== false;
            destSelect.dataset.locked = destinationLocked ? '1' : '';
            destSelect.dataset.prevValue = destSelect.value || '';
            destSelect.disabled = destinationLocked;

        }

        // The stored destination changed: redraw the visual route guide.
        refreshRouteMap();

        // Offer to pay now when Online Payment is the chosen method.
        if(promptOnlinePayment){
            maybePromptOnlinePayment(false);
        }

    }catch(error){

        console.error(
            'Fare estimate request failed:',
            error
        );

        const fallbackFare =
            await resolveFallbackFare(
                destId,
                destName,
                activeDiscountType
            );

        fareEl.textContent =
            fallbackFare !== null
                ? '₱' + fallbackFare.toFixed(2)
                : HOME_FARE_RETRY;

    }finally{
        if (window.regularPartyRefresh) { await window.regularPartyRefresh(); }
    }

}

const installPrompt =
    document.getElementById(
        'installPrompt'
    );

const installBtn =
    document.getElementById(
        'installBtn'
    );

const closeInstallBtn =
    document.getElementById(
        'closeInstallPrompt'
    );

let deferredPrompt = null;

if('serviceWorker' in navigator){

    navigator.serviceWorker
        .register('/passenger/sw.js')
        .then(() => {

            console.log(
                'SW Registered'
            );

        })
        .catch(err => {

            console.error(
                'SW Error:',
                err
            );

        });

}

const isIOS =
    /iphone|ipad|ipod/i.test(
        navigator.userAgent
    );

const isStandalone =

    window.matchMedia(
        '(display-mode: standalone)'
    ).matches ||

    window.navigator.standalone;

if(
    localStorage.getItem(
        'appInstalled'
    ) === 'true' ||

    isStandalone
){

    installPrompt.style.display =
        'none';

}else{

    if(isIOS){

        installPrompt.style.display =
            'block';

    }

}

window.addEventListener(
    'beforeinstallprompt',
    (e)=>{

        e.preventDefault();

        deferredPrompt = e;

        if(
            localStorage.getItem(
                'hideInstallPrompt'
            ) !== 'true'
        ){

            installPrompt.style.display =
                'block';

        }

    }
);

installBtn.addEventListener(
    'click',
    async ()=>{

        if(
            isIOS &&
            !deferredPrompt
        ){

            if (window.PassengerSession) {
                window.PassengerSession.showModal({
                    icon: 'info',
                    title: 'Install App',
                    text: 'Tap Share → Add to Home Screen',
                    buttonText: 'OK'
                });
            }

            return;

        }

        if(!deferredPrompt){

            if (window.PassengerSession) {
                window.PassengerSession.showModal({
                    icon: 'info',
                    title: 'Install App',
                    text: 'Install option not available yet.',
                    buttonText: 'OK'
                });
            }

            return;

        }

        deferredPrompt.prompt();

        const result =
            await deferredPrompt.userChoice;

        if(
            result.outcome ===
            'accepted'
        ){

            localStorage.setItem(
                (window.PassengerStorage && window.PassengerStorage.KEYS && window.PassengerStorage.KEYS.appInstalled) || 'appInstalled',
                'true'
            );

            installPrompt.style.display =
                'none';

        }

        deferredPrompt = null;

    }
);

window.addEventListener(
    'appinstalled',
    ()=>{

        localStorage.setItem(
            (window.PassengerStorage && window.PassengerStorage.KEYS && window.PassengerStorage.KEYS.appInstalled) || 'appInstalled',
            'true'
        );

        installPrompt.style.display =
            'none';

    }
);

if(closeInstallBtn){

    closeInstallBtn.addEventListener(
        'click',
        ()=>{

            localStorage.setItem(
                (window.PassengerStorage && window.PassengerStorage.KEYS && window.PassengerStorage.KEYS.hideInstallPrompt) || 'hideInstallPrompt',
                'true'
            );

            installPrompt.style.display =
                'none';

        }
    );

}

console.log(
    'appInstalled:',
    localStorage.getItem(
        'appInstalled'
    )
);

console.log(
    'hideInstallPrompt:',
    localStorage.getItem(
        'hideInstallPrompt'
    )
);

document.getElementById('manualBoardBtn').addEventListener('click', function(){
var busCode = document.getElementById('manualBusCode').value.trim();
var errorEl = document.getElementById('manualError');
if(!busCode){
errorEl.style.display = 'block';
return;
}
errorEl.style.display = 'none';
var fallback = document.getElementById('noCameraFallback');
fallback.style.display = 'none';
document.getElementById('reader').style.display = 'block';
document.querySelector('.scan-box').style.display = 'block';
var e = new CustomEvent('manualBusCode', {detail: busCode});
document.dispatchEvent(e);
});

document.getElementById('manualBusCode').addEventListener('keydown', function(e){
if(e.key === 'Enter'){
document.getElementById('manualBoardBtn').click();
}
});

document.addEventListener('manualBusCode', async function(ev){
var busCode = String(ev.detail || '').trim();
if(!busCode || isProcessing){
return;
}

isProcessing = true;

var ok = await requestLocation();

if(!ok){
setStatus("Location required to board", "red");
isProcessing = false;
return;
}

var payload = {
bus_no: busCode,
trip_no: "0",
latitude: userLat,
longitude: userLng,
accuracy: userAccuracy,
payment_method: document.getElementById('paymentMethod')?.value || 'cash',
cash_amount: Number(document.getElementById('cashAmountInput')?.value || 0),
gcash_amount: Number(document.getElementById('gcashAmountInput')?.value || 0)
};

try{

const res = await fetch('/passenger/scan/store', {
method:'POST',
headers:{'Content-Type':'application/json'},
body: JSON.stringify(payload)
});

const responseText = await res.text();

let result;

try{

const cleanedResponse = responseText.trim();

if(!cleanedResponse){
throw new Error('Empty server response');
}

const firstBrace = cleanedResponse.indexOf('{');
const firstBracket = cleanedResponse.indexOf('[');

let jsonStart = -1;

if(firstBrace >= 0 && firstBracket >= 0){
jsonStart = Math.min(firstBrace, firstBracket);
}else if(firstBrace >= 0){
jsonStart = firstBrace;
}else if(firstBracket >= 0){
jsonStart = firstBracket;
}

if(jsonStart < 0){
throw new Error('Invalid JSON response');
}

result = JSON.parse(cleanedResponse.substring(jsonStart));

}catch(error){

if (window.PASSENGER_QR_SCAN_DEBUG) {
    console.error('Board scan error:', error, 'HTTP status:', res ? res.status : 'none');
}

setStatus(
    'Unable to process boarding. Please try again.',
    'red'
);
isProcessing = false;
return;

}

if(!result.success){

setStatus(formatScanFailure(result, res.status, "Board failed"), "red");
isProcessing = false;
return;

}

if(result.hasActiveTrip === true){

setStatus(result.message || "Active trip in progress", "orange");

try{

const tripRes = await fetch('/passenger/trip/active');
const tripData = await tripRes.json();

if(tripData.hasActiveTrip){
onBoard = true;
lockedBoardingPoint = tripData.boarding_point || null;

document.getElementById('activeBusNo').innerText = tripData.bus_no || '-';
document.getElementById('activeDriver').innerText = tripData.driver_name || '-';
document.getElementById('activeBoarding').innerText = tripData.boarding_point || '-';
document.getElementById('activeDestination').innerText = tripData.destination || '-';
document.getElementById('onBoardCard').style.display = 'flex';
document.getElementById('reader').style.display = 'none';
document.querySelector('.scan-box').style.display = 'none';

const fb = document.getElementById('noCameraFallback');
if(fb){
fb.style.display = 'none';
}
}

}catch(error){

}

isProcessing = false;
return;

}

if(result.type === "boarding"){
    resetTripState();
}

updateTripUI(result);

if(result.type === "boarding"){

onBoard = true;

['hp_payment_method','hp_destination_id','hp_trip_details_open'].forEach(
k => sessionStorage.removeItem(k)
);

document.getElementById('tripDetails').style.display = 'block';
const _ta = document.getElementById('tripArrow');
if(_ta){ _ta.textContent = '▲'; }
saveActiveTripInfo(result);

currentBoardingPoint = result.boarding_point || '';
lockedBoardingPoint = currentBoardingPoint;

setStatus("", "green");

document.getElementById('reader').style.display = 'none';
document.querySelector('.scan-box').style.display = 'none';
document.getElementById('noCameraFallback').style.display = 'none';
document.getElementById('onBoardCard').style.display = 'flex';
document.getElementById('activeBusNo').innerText = result.bus_no || '-';
document.getElementById('activeDriver').innerText = result.driver_name || '-';
document.getElementById('activeBoarding').innerText = result.boarding_point || '-';
document.getElementById('activeDestination').innerText = result.destination || '-';

window._activeTripId = result.trip_id || null;

if(window.companionSetTrip){ window.companionSetTrip(result.trip_id || null); }

startLocationTracking();

startActiveTripPolling();

refreshRouteMap();

const boardCard = document.getElementById("readyBoardCard");
if(boardCard){
boardCard.style.display = "none";
}

const boardBtn = document.getElementById("boardNowBtn");
if(boardBtn){
boardBtn.disabled = true;
}

isProcessing = false;
return;

}

if(result.type === "dropoff"){

onBoard = false;
stopLocationTracking();
window._activeTripId = null;

try{ localStorage.removeItem(TRIP_INFO_KEY); }catch(e){}
resetTripState();

resumeScannerAfterTrip();

isProcessing = false;
return;

}

isProcessing = false;

}catch(error){

setStatus(
window.PASSENGER_QR_SCAN_DEBUG && error && error.message
? "Scan Failed [" + error.message + "]"
: "Scan Failed",
"red"
);
isProcessing = false;

}
});

// ============================================================
// Destination route map (visual guide only). The map never sets
// or changes the passenger's destination, fare, or payment: the
// Select a Destination field stays the only destination control.
// ============================================================

const ROUTE_MAP_STRINGS = {
    noDestination: <?= json_encode(passenger_t('home.route_map_no_destination', 'Select a destination to see the map'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    unavailable: <?= json_encode(passenger_t('home.route_map_unavailable', 'Route map unavailable right now'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    loading: <?= json_encode(passenger_t('home.route_map_loading', 'Loading map...'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    coveredStops: <?= json_encode(passenger_t('home.route_map_covered_stops', 'covered stops'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    coveredStop: <?= json_encode(passenger_t('home.route_map_covered_stop', 'covered stop'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    dropoffSaved: <?= json_encode(passenger_t('home.route_map_dropoff_saved', 'Drop-off point saved'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    dropoffFailed: <?= json_encode(passenger_t('home.route_map_dropoff_failed', 'Could not save the get-off point'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    fullscreen: <?= json_encode(passenger_t('home.route_map_fullscreen', 'Full screen'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    fullscreenClose: <?= json_encode(passenger_t('home.route_map_fullscreen_close', 'Close'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    infoBus: <?= json_encode(passenger_t('home.route_map_info_bus', 'Bus'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    infoTrip: <?= json_encode(passenger_t('home.route_map_info_trip', 'Trip'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    infoFare: <?= json_encode(passenger_t('home.route_map_info_fare', 'Fare'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    dropoffLabel: <?= json_encode(passenger_t('home.route_map_dropoff_label', 'DROP-OFF'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    whereGetOff: <?= json_encode(passenger_t('home.route_map_where_get_off', 'Where will you get off?'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    dropoffPoint: <?= json_encode(passenger_t('home.route_map_dropoff_point', 'Drop-off point'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    chooseHint: <?= json_encode(passenger_t('home.route_map_choose_hint', 'Choose a point along the highlighted route.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    selectedRoutePoint: <?= json_encode(passenger_t('home.route_map_selected_route_point', 'Selected route point'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    selectDropoff: <?= json_encode(passenger_t('home.route_map_select_dropoff', 'Select a drop-off point'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    confirmDropoff: <?= json_encode(passenger_t('home.route_map_confirm_dropoff', 'Confirm Drop-Off Point'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    invalidDropoff: <?= json_encode(passenger_t('home.route_map_invalid_dropoff', 'Please select a drop-off point along the highlighted route.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    dropoffAlready: <?= json_encode(passenger_t('home.route_map_dropoff_already', 'You already selected a drop-off point.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    areaSuffix: <?= json_encode(passenger_t('home.route_map_area_suffix', 'destination area'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    noPlaces: <?= json_encode(passenger_t('home.route_map_no_places', 'No places found'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};

// ========================================================
// [DISABLED] "YOUR ROUTE" MAP LOGIC (START)
// Stub functions prevent errors and avoid all route map requests
function initRouteMap(){ return null; }
function updateRouteMapLocation(){ /* Route section disabled */ }
async function refreshRouteMap(){ /* Route section disabled */ return; }
function clearRouteMap(){ /* Route section disabled */ }
function setRouteMapFullscreen(){ /* Route section disabled */ }
function hideRouteMapCard(){ /* Route section disabled */ }
function escapeHtml(value){ return String(value === null || value === undefined ? '' : value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }

/* --- ORIGINAL ROUTE MAP IMPLEMENTATION COMMENTED OUT (START) ---
let routeMap = null;
let routeMapLayer = null;
let routeMapYouMarker = null;
let routeMapRequestId = 0;
let routeMapDropoffMarker = null;
let routeMapHighlightMarker = null;
let routeMapPlaces = [];
let routeMapCandidates = [];
let routeMapSelectedDropoff = null;
let routeMapConfirmedDropoff = false;
let routeMapDestinationKey = '';
let routeMapDestinationName = '';
let routeMapHasDestination = false;
let routeMapFullscreen = false;
let routeMapMessageTimer = null;
let routeMapLastBoundsPoints = [];

function escapeHtml(value){

    return String(value === null || value === undefined ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

}

function routeMapCoordinate(source, latKey, lngKey){

    if(!source){ return null; }

    const rawLat = source[latKey || 'latitude'];
    const rawLng = source[lngKey || 'longitude'];
    if(
        rawLat === null || rawLat === undefined || rawLat === ''
        || rawLng === null || rawLng === undefined || rawLng === ''
    ){
        return null;
    }

    const lat = Number(rawLat);
    const lng = Number(rawLng);
    if(!isFinite(lat) || !isFinite(lng)){ return null; }

    return [lat, lng];

}

function formatRouteMapDistance(meters){

    const value = Number(meters);
    if(!isFinite(value) || value <= 0){ return ''; }
    if(value >= 1000){ return (value / 1000).toFixed(1) + ' km'; }
    return Math.round(value) + ' m';

}

function routeMapPathLengthMeters(latlngs){

    if(!Array.isArray(latlngs) || latlngs.length < 2){ return 0; }

    let total = 0;
    for(let i = 0; i < latlngs.length - 1; i++){
        total += L.latLng(latlngs[i]).distanceTo(L.latLng(latlngs[i + 1]));
    }

    return total;

}

function routeMapDownsamplePoints(latlngs, minMeters){

    if(!Array.isArray(latlngs) || latlngs.length <= 2){ return Array.isArray(latlngs) ? latlngs.slice() : []; }

    const minimum = (typeof minMeters === 'number' && minMeters > 0) ? minMeters : 80;
    const result = [latlngs[0]];
    let last = latlngs[0];

    for(let i = 1; i < latlngs.length - 1; i++){
        if(L.latLng(last).distanceTo(L.latLng(latlngs[i])) >= minimum){
            result.push(latlngs[i]);
            last = latlngs[i];
        }
    }

    result.push(latlngs[latlngs.length - 1]);

    return result;

}

function routeMapDropoffSelectedIcon(label){

    return L.divIcon({
        className: '',
        html: '<span style="display:flex;flex-direction:column;align-items:center;white-space:nowrap;">'
            + '<span style="background:#1f7a3d;color:#ffffff;font-size:9px;font-weight:700;letter-spacing:.5px;padding:2px 6px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.3);">' + escapeHtml(ROUTE_MAP_STRINGS.dropoffLabel) + '</span>'
            + '<span style="margin-top:2px;background:#ffffff;color:#24513c;font-size:11px;font-weight:700;padding:2px 6px;border-radius:6px;border:1px solid #bcd6b4;max-width:180px;overflow:hidden;text-overflow:ellipsis;">' + escapeHtml(label) + '</span>'
            + '<span style="width:2px;height:5px;background:#1f7a3d;"></span>'
            + '<span style="width:13px;height:13px;border-radius:50%;background:#1f7a3d;border:2.5px solid #ffffff;box-shadow:0 1px 4px rgba(0,0,0,.4);"></span>'
            + '</span>',
        iconSize: [190, 58],
        iconAnchor: [95, 58]
    });

}

function routeMapYouIcon(){

    return L.divIcon({
        className: '',
        html: '<span style="display:block;width:14px;height:14px;border-radius:50%;background:#2f80ed;border:2.5px solid #fff;box-shadow:0 0 0 2px rgba(47,128,237,.35), 0 1px 4px rgba(0,0,0,.4);"></span>',
        iconSize: [14, 14],
        iconAnchor: [7, 7]
    });

}

function hideRouteMapCard(){

    const card = document.getElementById('routeMapCard');
    if(card){ card.style.display = 'none'; }

}

function showRouteMapPlaceholder(message){

    const card = document.getElementById('routeMapCard');
    if(!card){ return; }

    if(routeMapFullscreen){ setRouteMapFullscreen(false); }

    routeMapHasDestination = false;
    routeMapPlaces = [];
    routeMapLastBoundsPoints = [];

    card.style.display = 'block';

    const placeholder = document.getElementById('routeMapPlaceholder');
    const canvas = document.getElementById('routeMapCanvas');
    const mapWrap = document.getElementById('routeMapMapWrap');
    const caption = document.getElementById('routeMapCaption');
    const areaStats = document.getElementById('routeMapAreaStats');

    if(placeholder){
        placeholder.textContent = message || ROUTE_MAP_STRINGS.unavailable;
        placeholder.style.display = 'block';
    }
    if(mapWrap){ mapWrap.style.display = 'none'; }
    if(canvas){ canvas.style.display = 'none'; }
    if(caption){ caption.textContent = ''; }
    if(areaStats){ areaStats.textContent = ''; }
    const tripInfoBox = document.getElementById('routeMapTripInfo');
    if(tripInfoBox){ tripInfoBox.innerHTML = ''; }
    const searchInput = document.getElementById('routeMapSearchInput');
    if(searchInput){ searchInput.value = ''; }
    hideRouteMapSearchResults();
    resetRouteMapDropoffSelection();
    showRouteMapMessage('');

}

function initRouteMap(){

    if(routeMap){ return routeMap; }
    if(typeof L === 'undefined'){ return null; }

    const canvas = document.getElementById('routeMapCanvas');
    if(!canvas){ return null; }

    try{

        routeMap = L.map(canvas, {
            // The zoom control is hidden in the small card and shown in the
            // fullscreen view (see the card styles).
            zoomControl: false,
            attributionControl: false,
            dragging: true,
            scrollWheelZoom: false
        });

        L.control.zoom({ position: 'topright' }).addTo(routeMap);

        // No {s} subdomain: the passenger CSP allows the single tile host.
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19
        }).addTo(routeMap);

        routeMapLayer = L.layerGroup().addTo(routeMap);

        // Tapping the map selects the nearest valid drop-off point on the
        // highlighted route (or explains that the tap is not on the route).
        // It never changes the official destination.
        routeMap.on('click', handleRouteMapClick);

        const fullscreenBtn = document.getElementById('routeMapFullscreenBtn');
        if(fullscreenBtn){
            fullscreenBtn.addEventListener('click', function(){ setRouteMapFullscreen(!routeMapFullscreen); });
        }

        const confirmBtn = document.getElementById('routeMapDropoffConfirmBtn');
        if(confirmBtn){
            confirmBtn.addEventListener('click', confirmRouteMapDropoff);
        }

        const searchInput = document.getElementById('routeMapSearchInput');
        if(searchInput){
            searchInput.addEventListener('input', function(){ renderRouteMapSearchResults(searchInput.value); });
            searchInput.addEventListener('focus', function(){
                if(searchInput.value.trim() !== ''){ renderRouteMapSearchResults(searchInput.value); }
            });
        }

        document.addEventListener('click', function(event){
            const wrap = document.getElementById('routeMapSearchWrap');
            if(wrap && !wrap.contains(event.target)){ hideRouteMapSearchResults(); }
        });

        document.addEventListener('keydown', function(event){
            if(routeMapFullscreen && event && event.key === 'Escape'){ setRouteMapFullscreen(false); }
        });

    }catch(err){
        routeMap = null;
        routeMapLayer = null;
        return null;
    }

    return routeMap;

}

function updateRouteMapLocation(){

    if(!routeMap || !routeMapLayer){ return; }
    if(userLocationIsFallback){ return; }
    if(typeof userLat !== 'number' || typeof userLng !== 'number'){ return; }
    if(!isFinite(userLat) || !isFinite(userLng)){ return; }

    const latlng = [userLat, userLng];

    if(routeMapYouMarker){
        routeMapYouMarker.setLatLng(latlng);
        return;
    }

    routeMapYouMarker = L.marker(latlng, {
        icon: routeMapYouIcon(),
        interactive: false,
        keyboard: false
    });

    routeMapYouMarker.addTo(routeMapLayer);

}

function routeMapNearestCandidate(latlng, maxMeters){

    if(!Array.isArray(routeMapCandidates) || routeMapCandidates.length === 0){ return null; }

    const limit = (typeof maxMeters === 'number' && maxMeters > 0) ? maxMeters : 60;
    let best = null;
    let bestDistance = Infinity;

    for(let i = 0; i < routeMapCandidates.length; i++){
        const distance = L.latLng(latlng).distanceTo(L.latLng(routeMapCandidates[i]));
        if(distance < bestDistance){
            bestDistance = distance;
            best = routeMapCandidates[i];
        }
    }

    return (best && bestDistance <= limit) ? best : null;

}

function routeMapPlaceIcon(){

    return L.divIcon({
        className: '',
        html: '<span style="display:block;width:14px;height:14px;border-radius:50%;background:#2f80ed;border:2.5px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4);"></span>',
        iconSize: [14, 14],
        iconAnchor: [7, 7]
    });

}

function routeMapPlaceLabelIcon(){

    return L.divIcon({
        className: '',
        html: '<span style="display:block;width:7px;height:7px;border-radius:50%;background:#8aa87a;border:1.5px solid #ffffff;box-shadow:0 1px 2px rgba(0,0,0,.25);"></span>',
        iconSize: [7, 7],
        iconAnchor: [3.5, 3.5]
    });

}

function routeMapPlaceMatches(place, query){

    const needle = String(query || '').trim().toLowerCase();
    if(needle === ''){ return false; }

    return String((place && place.name) || '').toLowerCase().indexOf(needle) !== -1;

}

function hideRouteMapSearchResults(){

    const results = document.getElementById('routeMapSearchResults');
    if(results){ results.style.display = 'none'; results.innerHTML = ''; }

}

function renderRouteMapSearchResults(query){

    const results = document.getElementById('routeMapSearchResults');
    if(!results){ return; }

    const needle = String(query || '').trim();
    if(needle === ''){
        hideRouteMapSearchResults();
        return;
    }

    const matches = routeMapPlaces.filter(function(place){
        return routeMapPlaceMatches(place, needle);
    }).slice(0, 12);

    results.innerHTML = '';

    if(matches.length === 0){
        const empty = document.createElement('div');
        empty.style.cssText = 'padding:9px 10px;font-size:12px;color:#7b8a74;';
        empty.textContent = ROUTE_MAP_STRINGS.noPlaces;
        results.appendChild(empty);
        results.style.display = 'block';
        return;
    }

    matches.forEach(function(place){

        const item = document.createElement('button');
        item.type = 'button';
        item.style.cssText = 'display:block;width:100%;box-sizing:border-box;text-align:left;padding:9px 10px;border:0;border-bottom:1px solid #eef2ec;background:#ffffff;color:#24513c;font-size:12px;line-height:1.35;white-space:normal;overflow-wrap:anywhere;cursor:pointer;';
        item.textContent = place.name;
        item.addEventListener('click', function(){ focusRouteMapPlace(place); });
        results.appendChild(item);

    });

    results.style.display = 'block';

}

function focusRouteMapPlace(place){

    if(!routeMap || !place){ return; }

    const lat = Number(place.latitude);
    const lng = Number(place.longitude);
    if(!isFinite(lat) || !isFinite(lng)){ return; }

    routeMap.setView([lat, lng], Math.max(routeMap.getZoom(), 17));

    if(routeMapHighlightMarker){
        routeMapHighlightMarker.setLatLng([lat, lng]);
    } else {
        routeMapHighlightMarker = L.marker([lat, lng], {
            icon: routeMapPlaceIcon(),
            interactive: false,
            keyboard: false,
            zIndexOffset: 700
        }).addTo(routeMapLayer);
    }

    hideRouteMapSearchResults();

}

function showRouteMapMessage(message){

    const box = document.getElementById('routeMapMessage');
    if(!box){ return; }

    if(routeMapMessageTimer){ window.clearTimeout(routeMapMessageTimer); routeMapMessageTimer = null; }

    if(!message){
        box.style.display = 'none';
        box.textContent = '';
        return;
    }

    box.textContent = message;
    box.style.display = 'block';
    routeMapMessageTimer = window.setTimeout(function(){
        box.style.display = 'none';
        box.textContent = '';
    }, 3500);

}

function setRouteMapDropoffMarker(latlng){

    if(!routeMapLayer || !latlng){ return; }

    if(routeMapDropoffMarker){
        routeMapDropoffMarker.setLatLng(latlng);
        return;
    }

    // The selected drop-off marker is a planned point only: it never changes
    // the passenger's official destination, fare, or drop-off behavior.
    routeMapDropoffMarker = L.marker(latlng, {
        icon: routeMapDropoffSelectedIcon(routeMapDestinationName || ROUTE_MAP_STRINGS.dropoffPoint),
        interactive: false,
        keyboard: false,
        zIndexOffset: 800
    });

    routeMapDropoffMarker.addTo(routeMapLayer);

}

function renderRouteMapDropoffCard(){

    const title = document.getElementById('routeMapDropoffCardTitle');
    const value = document.getElementById('routeMapDropoffCardValue');
    const sub = document.getElementById('routeMapDropoffCardSub');
    const button = document.getElementById('routeMapDropoffConfirmBtn');
    if(!title || !value || !button){ return; }

    if(routeMapSelectedDropoff === null){
        title.textContent = ROUTE_MAP_STRINGS.whereGetOff;
        value.textContent = ROUTE_MAP_STRINGS.chooseHint;
        if(sub){ sub.style.display = 'none'; }
        button.disabled = true;
        button.textContent = ROUTE_MAP_STRINGS.selectDropoff;
        button.style.opacity = '0.55';
        return;
    }

    title.textContent = ROUTE_MAP_STRINGS.dropoffPoint;
    value.textContent = routeMapDestinationName || ROUTE_MAP_STRINGS.dropoffPoint;
    if(sub){ sub.style.display = 'block'; sub.textContent = ROUTE_MAP_STRINGS.selectedRoutePoint; }
    button.disabled = routeMapConfirmedDropoff;
    button.textContent = routeMapConfirmedDropoff ? ROUTE_MAP_STRINGS.dropoffSaved : ROUTE_MAP_STRINGS.confirmDropoff;
    button.style.opacity = routeMapConfirmedDropoff ? '0.55' : '1';

}

function selectRouteMapDropoff(candidate, markConfirmed){

    if(!candidate){ return; }

    routeMapSelectedDropoff = [Number(candidate[0]), Number(candidate[1])];
    routeMapConfirmedDropoff = !!markConfirmed;

    setRouteMapDropoffMarker(routeMapSelectedDropoff);
    renderRouteMapDropoffCard();

}

function resetRouteMapDropoffSelection(){

    routeMapSelectedDropoff = null;
    routeMapConfirmedDropoff = false;
    routeMapDropoffMarker = null;
    routeMapCandidates = [];

    renderRouteMapDropoffCard();

}

function handleRouteMapClick(e){

    if(!routeMapHasDestination || !e || !e.latlng){ return; }

    const candidate = routeMapNearestCandidate(e.latlng, 60);
    if(!candidate){
        // Once a point is confirmed, remind the passenger instead of asking
        // them to select again; otherwise guide them back to the route.
        showRouteMapMessage(routeMapConfirmedDropoff
            ? ROUTE_MAP_STRINGS.dropoffAlready
            : ROUTE_MAP_STRINGS.invalidDropoff);
        return;
    }

    routeMapConfirmedDropoff = false;
    selectRouteMapDropoff(candidate, false);
    showRouteMapMessage('');

}

async function confirmRouteMapDropoff(){

    if(!routeMapSelectedDropoff){ return false; }

    const button = document.getElementById('routeMapDropoffConfirmBtn');
    if(button){ button.disabled = true; }

    try{

        const res = await fetch('/passenger/trip/dropoff-point', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                latitude: routeMapSelectedDropoff[0],
                longitude: routeMapSelectedDropoff[1]
            })
        });

        const data = await res.json();

        if(data && data.success && data.planned_dropoff){
            selectRouteMapDropoff(routeMapSelectedDropoff, true);
            showRouteMapMessage(ROUTE_MAP_STRINGS.dropoffSaved);
            return true;
        }

        renderRouteMapDropoffCard();
        showRouteMapMessage((data && data.message) ? data.message : ROUTE_MAP_STRINGS.dropoffFailed);
        return false;

    }catch(err){

        renderRouteMapDropoffCard();
        showRouteMapMessage(ROUTE_MAP_STRINGS.dropoffFailed);
        return false;

    }

}

function routeMapFitBounds(){

    if(!routeMap || !Array.isArray(routeMapLastBoundsPoints) || routeMapLastBoundsPoints.length === 0){ return; }

    const bounds = L.latLngBounds(routeMapLastBoundsPoints);
    if(!bounds.isValid()){ return; }

    // The drop-off card floats over the bottom of the map in both views and
    // the search bar floats over the top in fullscreen, so leave room for
    // them while keeping the destination area as large as possible.
    const isFullscreen = routeMapFullscreen;
    const sidePadding = isFullscreen ? 24 : 40;
    const topPadding = isFullscreen ? 84 : 30;
    const bottomPadding = isFullscreen ? 150 : 140;

    routeMap.fitBounds(bounds, {
        paddingTopLeft: [sidePadding, topPadding],
        paddingBottomRight: [sidePadding, bottomPadding],
        maxZoom: isFullscreen ? 18 : 17
    });

}

function setRouteMapFullscreen(on){

    const card = document.getElementById('routeMapCard');
    const btn = document.getElementById('routeMapFullscreenBtn');
    if(!card){ return; }

    routeMapFullscreen = !!on;

    if(routeMapFullscreen){
        card.classList.add('route-map-fullscreen');
    } else {
        card.classList.remove('route-map-fullscreen');
    }

    // Zooming is available in the fullscreen view: the zoom control is shown
    // by the card styles and wheel zoom is enabled here (pinch/double-tap zoom
    // stay available on touch devices in both views).
    if(routeMap && routeMap.scrollWheelZoom){
        if(routeMapFullscreen){ routeMap.scrollWheelZoom.enable(); } else { routeMap.scrollWheelZoom.disable(); }
    }

    if(btn){ btn.textContent = routeMapFullscreen ? ROUTE_MAP_STRINGS.fullscreenClose : ROUTE_MAP_STRINGS.fullscreen; }

    window.setTimeout(function(){
        if(routeMap){ routeMap.invalidateSize(); routeMapFitBounds(); }
    }, 60);

}

function setRouteMapTripInfo(tripInfo){

    const box = document.getElementById('routeMapTripInfo');
    if(!box){ return; }

    box.innerHTML = '';

    const info = tripInfo || {};
    const fareValue = (info.fare !== null && info.fare !== undefined && info.fare !== '')
        ? '\u20B1' + Number(info.fare).toFixed(2)
        : '';

    // Destination and boarding are shown in the header and route summary, so
    // the fullscreen panel keeps only the remaining trip identifiers.
    const rows = [
        [ROUTE_MAP_STRINGS.infoBus, info.bus_no || ''],
        [ROUTE_MAP_STRINGS.infoTrip, info.trip_number || ''],
        [ROUTE_MAP_STRINGS.infoFare, fareValue]
    ];

    rows.forEach(function(row){

        const value = row[1];
        if(value === null || value === undefined || value === ''){ return; }

        const pair = document.createElement('span');
        pair.style.cssText = 'display:inline-flex;align-items:baseline;gap:4px;';

        const label = document.createElement('span');
        label.style.color = '#7b8a74';
        label.textContent = row[0];

        const text = document.createElement('span');
        text.style.fontWeight = '600';
        text.style.overflowWrap = 'anywhere';
        text.textContent = String(value);

        pair.appendChild(label);
        pair.appendChild(text);
        box.appendChild(pair);

    });

    box.style.display = box.children.length ? 'flex' : 'none';

}

function renderRouteMap(data){

    if(!initRouteMap()){
        showRouteMapPlaceholder(ROUTE_MAP_STRINGS.unavailable);
        return;
    }

    const destinationName = (data.destination && data.destination.stop_name) ? data.destination.stop_name : '';
    const destinationKey = destinationName + '|' + ((data.destination && data.destination.stop_id) ? String(data.destination.stop_id) : '');
    routeMapDestinationName = destinationName;

    // A destination change resets the previous destination's drop-off choice:
    // a point selected under Del Pilar never survives a switch to Aglipay.
    let destinationChanged = false;
    if(routeMapDestinationKey !== '' && routeMapDestinationKey !== destinationKey){
        destinationChanged = true;
        routeMapSelectedDropoff = null;
        routeMapConfirmedDropoff = false;
    }
    routeMapDestinationKey = destinationKey;

    routeMapLayer.clearLayers();
    routeMapYouMarker = null;
    routeMapDropoffMarker = null;
    routeMapHighlightMarker = null;
    routeMapHasDestination = true;
    routeMapPlaces = Array.isArray(data.area_places) ? data.area_places : [];

    // Destination coverage: every reference coordinate of the selected
    // destination. Used for the map fit and only as a fallback for the frame;
    // it is never drawn as a circle or outline.
    const areaPoints = [];
    (Array.isArray(data.destination_area) ? data.destination_area : []).forEach(function(point){
        const latlng = routeMapCoordinate(point, 'lat', 'lng');
        if(latlng){ areaPoints.push(latlng); }
    });
    if(areaPoints.length === 0){
        const destinationLatLng = routeMapCoordinate(data.destination);
        if(destinationLatLng){ areaPoints.push(destinationLatLng); }
    }

    // Highlighted street route: the existing route_points filtered server-side
    // to the selected destination's area. Each contiguous run gets a white
    // casing plus a strong line so it is the primary visual element.
    const routePoints = Array.isArray(data.route_points) ? data.route_points : [];
    const runs = {};
    const runOrder = [];

    routePoints.forEach(function(point){

        const latlng = routeMapCoordinate(point);
        if(!latlng){ return; }

        const runKey = (point.run === undefined || point.run === null) ? 0 : Number(point.run);
        if(!runs[runKey]){ runs[runKey] = []; runOrder.push(runKey); }
        runs[runKey].push(latlng);

    });

    const drawnRouteLatLngs = [];
    runOrder.forEach(function(runKey){

        const runPoints = runs[runKey];
        if(runPoints.length < 2){ return; }

        L.polyline(runPoints, {
            color: '#ffffff',
            weight: 9,
            opacity: 0.9,
            interactive: false
        }).addTo(routeMapLayer);

        L.polyline(runPoints, {
            color: '#47613b',
            weight: 5,
            opacity: 0.95,
            interactive: false
        }).addTo(routeMapLayer);

        runPoints.forEach(function(latlng){ drawnRouteLatLngs.push(latlng); });

    });

    // Selectable drop-off points: existing route points on the highlighted
    // route, drawn as small open circles. The trace is downsampled so dense
    // street traces stay readable and tappable; the map tap selects the
    // nearest candidate within the tap tolerance.
    routeMapCandidates = routeMapDownsamplePoints(drawnRouteLatLngs, 60);

    routeMapCandidates.forEach(function(latlng){

        L.circleMarker(latlng, {
            radius: 4,
            color: '#47613b',
            weight: 2,
            opacity: 0.9,
            fillColor: '#ffffff',
            fillOpacity: 1,
            interactive: false
        }).addTo(routeMapLayer);

    });

    // Named stops and landmarks inside the destination area get a small label
    // so the street section is easy to read ("which part of Del Pilar is this").
    routeMapPlaces.forEach(function(place){

        const latlng = routeMapCoordinate(place);
        if(!latlng || !place.name){ return; }

        L.marker(latlng, {
            icon: routeMapPlaceLabelIcon(),
            interactive: false,
            keyboard: false,
            zIndexOffset: 200
        })
            .bindTooltip(place.name, {
                permanent: true,
                direction: 'top',
                offset: [0, -5],
                className: 'route-map-place-tooltip'
            })
            .addTo(routeMapLayer);

    });

    // Header: the destination name is the strongest text; the stats line reads
    // "N covered stops • X km destination area".
    const caption = document.getElementById('routeMapCaption');
    if(caption){ caption.textContent = destinationName || '-'; }

    const areaStats = document.getElementById('routeMapAreaStats');
    if(areaStats){

        const statsParts = [];
        if(routeMapPlaces.length){
            const coveredLabel = routeMapPlaces.length === 1 ? ROUTE_MAP_STRINGS.coveredStop : ROUTE_MAP_STRINGS.coveredStops;
            statsParts.push(routeMapPlaces.length + ' ' + coveredLabel);
        }
        const distanceText = formatRouteMapDistance(routeMapPathLengthMeters(drawnRouteLatLngs));
        if(distanceText){ statsParts.push(distanceText + ' ' + ROUTE_MAP_STRINGS.areaSuffix); }

        areaStats.textContent = statsParts.join(' \u00B7 ') || '-';

    }

    setRouteMapTripInfo(data.trip_info || null);

    const card = document.getElementById('routeMapCard');
    const canvas = document.getElementById('routeMapCanvas');
    const mapWrap = document.getElementById('routeMapMapWrap');
    const placeholder = document.getElementById('routeMapPlaceholder');

    if(card){ card.style.display = 'block'; }
    if(mapWrap){ mapWrap.style.display = 'block'; }
    if(canvas){ canvas.style.display = 'block'; }
    if(placeholder){ placeholder.style.display = 'none'; }
    showRouteMapMessage('');

    const searchInput = document.getElementById('routeMapSearchInput');
    if(searchInput){ searchInput.value = ''; }
    hideRouteMapSearchResults();

    // Keep an unconfirmed local selection across refreshes; otherwise restore
    // the passenger's confirmed drop-off point when there is one.
    if(routeMapSelectedDropoff !== null){
        setRouteMapDropoffMarker(routeMapSelectedDropoff);
    } else if(
        !destinationChanged
        && data.planned_dropoff
        && data.planned_dropoff.latitude !== null && data.planned_dropoff.latitude !== undefined
        && data.planned_dropoff.longitude !== null && data.planned_dropoff.longitude !== undefined
    ){
        selectRouteMapDropoff([Number(data.planned_dropoff.latitude), Number(data.planned_dropoff.longitude)], true);
    }

    renderRouteMapDropoffCard();

    updateRouteMapLocation();

    routeMap.invalidateSize();

    // Fit the selected destination only: the filtered route geometry plus the
    // destination's reference coordinates. The boarding point and the rest of
    // the route are not part of the frame, so the destination area fills the
    // available map.
    const boundsPoints = [];
    drawnRouteLatLngs.forEach(function(latlng){ boundsPoints.push(latlng); });
    areaPoints.forEach(function(latlng){ boundsPoints.push(latlng); });

    routeMapLastBoundsPoints = boundsPoints;
    routeMapFitBounds();

}

async function refreshRouteMap(){

    if(!onBoard){
        clearRouteMap();
        return;
    }

    const card = document.getElementById('routeMapCard');
    if(!card){ return; }

    const requestId = ++routeMapRequestId;
    showRouteMapPlaceholder(ROUTE_MAP_STRINGS.loading);

    try{

        const res = await fetch('/passenger/trip/route-map');
        const data = await res.json();

        if(requestId !== routeMapRequestId){ return; }

        if(data && data.success){
            renderRouteMap(data);
            return;
        }

        if(data && data.code === 'NO_DESTINATION'){
            showRouteMapPlaceholder(ROUTE_MAP_STRINGS.noDestination);
            return;
        }

        showRouteMapPlaceholder(ROUTE_MAP_STRINGS.unavailable);

    }catch(err){

        if(requestId === routeMapRequestId){
            showRouteMapPlaceholder(ROUTE_MAP_STRINGS.unavailable);
        }

    }

}

function clearRouteMap(){

    routeMapRequestId++;

    if(routeMapFullscreen){ setRouteMapFullscreen(false); }

    routeMapHasDestination = false;
    routeMapPlaces = [];
    routeMapDropoffMarker = null;
    routeMapLastBoundsPoints = [];

    if(routeMapLayer){ routeMapLayer.clearLayers(); }
    routeMapYouMarker = null;

    resetRouteMapDropoffSelection();

    const mapWrap = document.getElementById('routeMapMapWrap');
    if(mapWrap){ mapWrap.style.display = 'none'; }
    const areaStats = document.getElementById('routeMapAreaStats');
    if(areaStats){ areaStats.textContent = ''; }
    const tripInfoBox = document.getElementById('routeMapTripInfo');
    if(tripInfoBox){ tripInfoBox.innerHTML = ''; }
    const searchInput = document.getElementById('routeMapSearchInput');
    if(searchInput){ searchInput.value = ''; }
    hideRouteMapSearchResults();
    showRouteMapMessage('');

    hideRouteMapCard();

}
--- ORIGINAL ROUTE MAP IMPLEMENTATION COMMENTED OUT (END) --- */
// [DISABLED] "YOUR ROUTE" MAP LOGIC (END)
// ========================================================

// =========================
// DESTINATION ALERTS + CONTINUATION JOURNEY
// =========================
const CONTINUATION_STRINGS = {
    voiceNear: <?= json_encode(passenger_t('home.voice_near', 'You are near your destination. Please prepare to get off.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    voiceArrival: <?= json_encode(passenger_t('home.voice_arrival', 'You have arrived at your destination.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    youPassed: <?= json_encode(passenger_t('home.you_passed', 'You passed :destination.', ['destination' => '__DEST__']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    continueLabel: <?= json_encode(passenger_t('home.continue_journey', 'Continue Journey'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    continueStarted: <?= json_encode(passenger_t('home.continuation_started', 'Your continuation journey has started.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    continueFailed: <?= json_encode(passenger_t('home.continuation_failed', 'We could not start your continuation journey. Please try again.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    chooseFirst: <?= json_encode(passenger_t('home.continuation_choose_first', 'Please choose your new destination first.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    starting: <?= json_encode(passenger_t('home.continuation_starting', 'Starting...'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};

const CONTINUATION_TOKEN_KEY = (window.PassengerStorage && window.PassengerStorage.KEYS && window.PassengerStorage.KEYS.continuationToken) || 'rconnect_continuation_token';
let continuationData = null;
let continuationSelectedId = null;
let continuationSelectedFare = null;
let destinationBannerTimer = null;

function speakDestinationAlert(text){
    try{
        if(!text || !('speechSynthesis' in window) || typeof SpeechSynthesisUtterance === 'undefined'){
            return;
        }
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'en-US';
        window.speechSynthesis.cancel();
        window.speechSynthesis.speak(utterance);
    }catch(err){
        // Voice is best effort; the banner is the guaranteed channel.
    }
}

function showDestinationBanner(kind, destinationName){
    const bannerId = kind === 'arrival' ? 'destinationArrivalBanner' : 'destinationNearBanner';
    const nameId = kind === 'arrival' ? 'destinationArrivalName' : 'destinationNearName';
    const banner = document.getElementById(bannerId);
    if(!banner){ return; }

    const nameEl = document.getElementById(nameId);
    if(nameEl){ nameEl.textContent = destinationName || ''; }

    banner.style.display = 'block';
    clearTimeout(destinationBannerTimer);
    destinationBannerTimer = setTimeout(function(){
        banner.style.display = 'none';
    }, kind === 'arrival' ? 10000 : 8000);
}

function syncDestinationAlertTrip(tripId){
    if(!window.RConnectDestinationAlerts){ return; }

    if(tripId){
        window.RConnectDestinationAlerts.setTrip(Number(tripId));
    }else{
        window.RConnectDestinationAlerts.reset();
    }
}

// Destination alerts are shared with the one-time page; this page supplies
// the existing banner/voice experience and the continuation screen action.
if(window.RConnectDestinationAlerts){
    window.RConnectDestinationAlerts.configure({
        strings: window.PASSENGER_DEST_ALERT_STRINGS || null,

        getTripId: function(){
            return window._activeTripId || null;
        },

        showBanner: function(kind, name){
            showDestinationBanner(kind, name);
        },

        speak: function(kind, name){
            if(kind === 'near'){
                speakDestinationAlert(CONTINUATION_STRINGS.voiceNear);
            }else if(kind === 'arrival'){
                speakDestinationAlert(CONTINUATION_STRINGS.voiceArrival);
            }else if(kind === 'passed'){
                speakDestinationAlert(
                    CONTINUATION_STRINGS.youPassed.replace('__DEST__', name || '')
                );
            }
        },

        onContinueJourney: function(){
            checkContinuationState();
        }
    });
}

function handleDestinationAlert(alert, fix){
    if(!window.RConnectDestinationAlerts){ return; }

    window.RConnectDestinationAlerts.handleAlert(
        (alert && typeof alert === 'object') ? alert : null,
        (fix && typeof fix === 'object') ? fix : null
    );
}

// The bus fix is the movement source. A stale or foreign bus still carries
// diagnostic coordinates, so it is passed to the panel even when unusable.
function destinationAlertFixFromBus(data){
    const bus = (data && data.bus_location) || null;

    if(bus && bus.latitude !== undefined && bus.latitude !== null){
        return {
            latitude: bus.latitude,
            longitude: bus.longitude,
            accuracy: null,
            source: 'driver'
        };
    }

    return null;
}

// Passenger coordinates are display-only fallback when no bus fix exists.
function destinationAlertFixFromResponse(data, position){
    const busFix = destinationAlertFixFromBus(data);
    if(busFix){ return busFix; }

    if(position && position.coords){
        return {
            latitude: (data && data.latitude) ?? position.coords.latitude,
            longitude: (data && data.longitude) ?? position.coords.longitude,
            accuracy: (data && data.accuracy) ?? position.coords.accuracy ?? null,
            source: 'passenger'
        };
    }

    return null;
}

function getOrCreateContinuationToken(){
    let token = '';
    try{ token = localStorage.getItem(CONTINUATION_TOKEN_KEY) || ''; }catch(err){}

    if(!token){
        token = 'cont_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);
        try{ localStorage.setItem(CONTINUATION_TOKEN_KEY, token); }catch(err){}
        saveTripState({ continuation_token: token });
    }

    return token;
}

function clearContinuationToken(){
    try{ localStorage.removeItem(CONTINUATION_TOKEN_KEY); }catch(err){}
    saveTripState({ continuation_token: '' });
}

async function checkContinuationState(){
    if(!onBoard){ return; }

    try{
        const res = await fetch('/passenger/trip/continuation');
        const data = await res.json();

        if(data && data.success && data.needs_continuation){
            openContinuationScreen(data);
        }
    }catch(err){
        // The screen can be reopened from the passed alert or next load.
    }
}

function openContinuationScreen(data){
    continuationData = data || {};
    continuationSelectedId = null;
    continuationSelectedFare = null;

    const overlay = document.getElementById('continuationOverlay');
    if(!overlay){ return; }

    const missed = continuationData.missed_destination || '';

    const passedEl = document.getElementById('continuationPassedText');
    if(passedEl){
        passedEl.textContent = CONTINUATION_STRINGS.youPassed.replace('__DEST__', missed);
    }

    const boardingEl = document.getElementById('continuationBoardingPoint');
    if(boardingEl){
        boardingEl.textContent = continuationData.new_boarding_point || missed || '—';
    }

    const prev = Number(continuationData.previous_outstanding || 0);
    const prevEl = document.getElementById('continuationPrevOutstanding');
    if(prevEl){ prevEl.textContent = '₱' + prev.toFixed(2); }

    const summary = document.getElementById('continuationSummary');
    if(summary){ summary.style.display = 'block'; }

    const fareEl = document.getElementById('continuationFare');
    if(fareEl){ fareEl.textContent = '—'; }
    const totalEl = document.getElementById('continuationTotalDue');
    if(totalEl){ totalEl.textContent = '—'; }

    renderContinuationDestinations();

    const errorEl = document.getElementById('continuationError');
    if(errorEl){ errorEl.style.display = 'none'; errorEl.textContent = ''; }

    const submitBtn = document.getElementById('continuationSubmitBtn');
    if(submitBtn){
        submitBtn.disabled = true;
        submitBtn.style.opacity = '.55';
        submitBtn.textContent = CONTINUATION_STRINGS.continueLabel;
    }

    overlay.style.display = 'block';
}

function renderContinuationDestinations(){
    const container = document.getElementById('continuationDestinations');
    const noStops = document.getElementById('continuationNoStops');
    if(!container){ return; }

    container.innerHTML = '';

    const destinations = Array.isArray(continuationData && continuationData.destinations)
        ? continuationData.destinations
        : [];

    if(noStops){ noStops.style.display = destinations.length ? 'none' : 'block'; }

    destinations.forEach(function(stop){
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.destinationId = String(stop.id);
        button.style.cssText = 'border:1.5px solid #dde5d4;background:#f8faf7;color:#24513c;border-radius:999px;padding:9px 14px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;touch-action:manipulation;';
        button.textContent = stop.destination;

        const fareTag = document.createElement('span');
        fareTag.style.cssText = 'display:block;font-size:10.5px;font-weight:600;color:#7b8a74;margin-top:1px;';
        fareTag.textContent = '₱' + Number(stop.fare || 0).toFixed(2);
        button.appendChild(fareTag);

        button.addEventListener('click', function(){
            selectContinuationDestination(stop, button);
        });

        container.appendChild(button);
    });
}

function selectContinuationDestination(stop, button){
    continuationSelectedId = Number(stop.id);
    continuationSelectedFare = Number(stop.fare || 0);

    document.querySelectorAll('#continuationDestinations button').forEach(function(el){
        el.style.borderColor = '#dde5d4';
        el.style.background = '#f8faf7';
        el.style.color = '#24513c';
    });

    if(button){
        button.style.borderColor = '#47613b';
        button.style.background = '#eef4e6';
        button.style.color = '#1f4d2e';
    }

    const fareEl = document.getElementById('continuationFare');
    if(fareEl){ fareEl.textContent = '₱' + continuationSelectedFare.toFixed(2); }

    const prev = Number((continuationData && continuationData.previous_outstanding) || 0);
    const totalEl = document.getElementById('continuationTotalDue');
    if(totalEl){ totalEl.textContent = '₱' + (prev + continuationSelectedFare).toFixed(2); }

    const submitBtn = document.getElementById('continuationSubmitBtn');
    if(submitBtn){
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
    }
}

async function submitContinuation(){
    const submitBtn = document.getElementById('continuationSubmitBtn');
    const errorEl = document.getElementById('continuationError');

    if(!continuationSelectedId){
        if(errorEl){
            errorEl.textContent = CONTINUATION_STRINGS.chooseFirst;
            errorEl.style.display = 'block';
        }
        return;
    }

    if(submitBtn){
        submitBtn.disabled = true;
        submitBtn.style.opacity = '.55';
        submitBtn.textContent = CONTINUATION_STRINGS.starting;
    }
    if(errorEl){ errorEl.style.display = 'none'; errorEl.textContent = ''; }

    try{
        const token = getOrCreateContinuationToken();
        const res = await fetch('/passenger/trip/continue', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                new_destination_id: continuationSelectedId,
                continuation_token: token
            })
        });
        const data = await res.json();

        if(data && data.success){
            clearContinuationToken();

            const trip = data.data || {};
            window._activeTripId = trip.trip_id || window._activeTripId;
            syncDestinationAlertTrip(window._activeTripId);

            const destEl = document.getElementById('activeDestination');
            if(destEl && trip.new_destination){ destEl.innerText = trip.new_destination; }

            const overlay = document.getElementById('continuationOverlay');
            if(overlay){ overlay.style.display = 'none'; }

            setStatus(CONTINUATION_STRINGS.continueStarted, 'green');
            restoreActiveTripInfo();
            refreshRouteMap();
            return;
        }

        if(errorEl){
            errorEl.textContent = (data && data.message) ? data.message : CONTINUATION_STRINGS.continueFailed;
            errorEl.style.display = 'block';
        }
    }catch(err){
        if(errorEl){
            errorEl.textContent = CONTINUATION_STRINGS.continueFailed;
            errorEl.style.display = 'block';
        }
    }finally{
        if(submitBtn){
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            submitBtn.textContent = CONTINUATION_STRINGS.continueLabel;
        }
    }
}

document.getElementById('continuationSubmitBtn')?.addEventListener('click', submitContinuation);

</script>

<script>
window.PASSENGER_QR_STRINGS = {
    alt: <?= json_encode(passenger_t('qr.alt', 'Passenger QR'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    title: <?= json_encode(passenger_t('qr.print_title', 'R-CONNECT Passenger QR'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    head: <?= json_encode(passenger_t('qr.print_head', 'R-CONNECT Passenger ID'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};
</script>
<script src="/assets/js/passenger/passenger-qr.js"></script>
<?php if ($passengerQrIntro): ?>
<script>
(function(){
    var payloadEl = document.getElementById('passengerQrIntroCanvas');
    if(!payloadEl){ return; }

    renderPassengerQr(payloadEl.getAttribute('data-payload'), 'passengerQrIntroCanvas');

    function markSeen(){
        return fetch('/passenger/qr/mark-seen', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({seen: 1})
        }).then(function(res){ return res.json(); })
          .then(function(data){ return data && data.success; })
          .catch(function(){ return false; });
    }

    var modal = document.getElementById('passengerQrIntroModal');

    function openModal(){
        modal.style.display = 'flex';

        markSeen();
    }

    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', openModal);
    } else {
        openModal();
    }

    document.getElementById('passengerQrContinueBtn').addEventListener('click', function(){
        markSeen().finally(function(){
            modal.style.display = 'none';
        });
    });

    window.__passengerQrActionDone = function(action){
        if(action === 'download' || action === 'print'){
            markSeen();
        }
    };
})();
</script>
<?php endif; ?>

<!-- =========================
     Destination proximity alerts (non-blocking banners)
     ========================= -->
<div id="destinationNearBanner" style="display:none;position:fixed;left:12px;right:12px;top:12px;z-index:10040;background:#fffdf5;border:1.5px solid #f0dca8;border-radius:14px;padding:12px 14px;box-shadow:0 6px 20px rgba(0,0,0,.16);font-family:'Poppins',sans-serif;">
    <div style="display:flex;align-items:flex-start;gap:8px;">
        <i class="bi bi-geo-alt-fill" style="color:#c0812b;font-size:16px;flex-shrink:0;margin-top:1px;"></i>
        <div style="min-width:0;flex:1;">
            <div style="font-size:13px;font-weight:800;color:#7a5a17;"><?= htmlspecialchars(passenger_t('dest_alert.near_title', 'Destination Nearby'), ENT_QUOTES, 'UTF-8') ?></div>
            <div style="font-size:12px;color:#7b8a74;margin-top:2px;"><?= htmlspecialchars(passenger_t('dest_alert.near_lead', 'Your destination is nearby.'), ENT_QUOTES, 'UTF-8') ?></div>
            <div style="font-size:12px;color:#7b8a74;margin-top:2px;"><?= htmlspecialchars(passenger_t('dest_alert.near_body', 'Please prepare to get off at:'), ENT_QUOTES, 'UTF-8') ?></div>
            <div id="destinationNearName" style="font-size:14px;font-weight:700;color:#24513c;margin-top:2px;overflow-wrap:anywhere;"></div>
        </div>
        <button type="button" aria-label="Close" onclick="document.getElementById('destinationNearBanner').style.display='none'" style="border:0;background:transparent;color:#b09a63;font-size:18px;line-height:1;padding:0 2px;flex-shrink:0;">&times;</button>
    </div>
</div>

<div id="destinationArrivalBanner" style="display:none;position:fixed;left:12px;right:12px;top:12px;z-index:10041;background:#eef8ef;border:1.5px solid #b7e4c7;border-radius:14px;padding:12px 14px;box-shadow:0 6px 20px rgba(0,0,0,.16);font-family:'Poppins',sans-serif;">
    <div style="display:flex;align-items:flex-start;gap:8px;">
        <i class="bi bi-check-circle-fill" style="color:#2f6b43;font-size:16px;flex-shrink:0;margin-top:1px;"></i>
        <div style="min-width:0;flex:1;">
            <div style="font-size:13px;font-weight:800;color:#2f6b43;"><?= htmlspecialchars(passenger_t('dest_alert.reached_title', 'Destination Reached'), ENT_QUOTES, 'UTF-8') ?></div>
            <div style="font-size:12px;color:#7b8a74;margin-top:2px;"><?= htmlspecialchars(passenger_t('dest_alert.reached_body', 'You are now near:'), ENT_QUOTES, 'UTF-8') ?></div>
            <div id="destinationArrivalName" style="font-size:14px;font-weight:700;color:#24513c;margin-top:2px;overflow-wrap:anywhere;"></div>
            <div style="font-size:12px;color:#7b8a74;margin-top:4px;"><?= htmlspecialchars(passenger_t('dest_alert.reached_hint', 'Please prepare to get off.'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <button type="button" aria-label="Close" onclick="document.getElementById('destinationArrivalBanner').style.display='none'" style="border:0;background:transparent;color:#7fae8f;font-size:18px;line-height:1;padding:0 2px;flex-shrink:0;">&times;</button>
    </div>
</div>

<!-- =========================
     Missed destination -> continuation journey
     ========================= -->
<div id="continuationOverlay" style="display:none;position:fixed;inset:0;z-index:10050;background:rgba(24,34,22,.55);font-family:'Poppins',sans-serif;">
    <div style="position:absolute;left:0;right:0;bottom:0;max-height:92vh;overflow:auto;background:#ffffff;border-radius:20px 20px 0 0;padding:18px 16px 24px;box-shadow:0 -8px 30px rgba(0,0,0,.25);">

        <div style="display:flex;align-items:center;gap:9px;">
            <span style="width:34px;height:34px;border-radius:50%;background:#fdecec;color:#a93226;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-flag-fill" style="font-size:15px;"></i>
            </span>
            <div style="min-width:0;">
                <div style="font-size:17px;font-weight:800;color:#24513c;line-height:1.2;"><?= htmlspecialchars(passenger_t('home.destination_passed_title', 'Destination Passed'), ENT_QUOTES, 'UTF-8') ?></div>
                <div style="font-size:11px;color:#7b8a74;margin-top:1px;"><?= htmlspecialchars(passenger_t('home.continue_your_journey', 'Continue Your Journey'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>

        <div id="continuationPassedText" style="margin-top:12px;font-size:14px;font-weight:700;color:#a93226;overflow-wrap:anywhere;"></div>
        <div style="margin-top:6px;font-size:12.5px;color:#7b8a74;line-height:1.5;">
            <?= htmlspecialchars(passenger_t('home.destination_passed_hint', 'If you still need to continue your journey, set a new destination from your current location.'), ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div style="margin-top:14px;background:#f4f8ef;border:1px solid #dce8d2;border-radius:12px;padding:10px 12px;">
            <div style="font-size:10px;font-weight:700;color:#6b8e6b;letter-spacing:.5px;text-transform:uppercase;"><?= htmlspecialchars(passenger_t('home.new_boarding_point', 'New Boarding Point'), ENT_QUOTES, 'UTF-8') ?></div>
            <div id="continuationBoardingPoint" style="font-size:16px;font-weight:800;color:#24513c;margin-top:2px;overflow-wrap:anywhere;">&mdash;</div>
        </div>

        <div style="margin-top:14px;font-size:12px;font-weight:700;color:#24513c;"><?= htmlspecialchars(passenger_t('home.choose_new_destination', 'Choose your new destination'), ENT_QUOTES, 'UTF-8') ?></div>
        <div id="continuationDestinations" style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;"></div>
        <div id="continuationNoStops" style="display:none;margin-top:8px;font-size:12.5px;color:#7b8a74;"><?= htmlspecialchars(passenger_t('home.no_remaining_destinations', 'No stops remain ahead on this route.'), ENT_QUOTES, 'UTF-8') ?></div>

        <div id="continuationSummary" style="display:none;margin-top:16px;border:1px solid #eef2ec;border-radius:12px;padding:12px;">
            <div style="font-size:11px;font-weight:700;color:#7b8a74;letter-spacing:.5px;text-transform:uppercase;"><?= htmlspecialchars(passenger_t('home.payment_summary', 'Payment Summary'), ENT_QUOTES, 'UTF-8') ?></div>
            <div style="display:flex;justify-content:space-between;gap:10px;margin-top:8px;font-size:13px;color:#24513c;">
                <span><?= htmlspecialchars(passenger_t('home.previous_outstanding', 'Previous Outstanding'), ENT_QUOTES, 'UTF-8') ?></span>
                <span id="continuationPrevOutstanding" style="font-weight:700;">&#8369;0.00</span>
            </div>
            <div style="display:flex;justify-content:space-between;gap:10px;margin-top:6px;font-size:13px;color:#24513c;">
                <span><?= htmlspecialchars(passenger_t('home.continuation_fare', 'Continuation Fare'), ENT_QUOTES, 'UTF-8') ?></span>
                <span id="continuationFare" style="font-weight:700;">&mdash;</span>
            </div>
            <div style="border-top:1px dashed #dde5d4;margin-top:8px;padding-top:8px;display:flex;justify-content:space-between;gap:10px;font-size:14px;font-weight:800;color:#24513c;">
                <span><?= htmlspecialchars(passenger_t('home.total_due', 'TOTAL DUE'), ENT_QUOTES, 'UTF-8') ?></span>
                <span id="continuationTotalDue">&mdash;</span>
            </div>
        </div>

        <div id="continuationError" style="display:none;margin-top:10px;font-size:12.5px;font-weight:600;color:#a93226;"></div>

        <button type="button" id="continuationSubmitBtn" disabled style="margin-top:16px;width:100%;border:0;border-radius:12px;padding:14px;background:#47613b;color:#ffffff;font-family:'Poppins',sans-serif;font-size:14px;font-weight:800;opacity:.55;">
            <?= htmlspecialchars(passenger_t('home.continue_journey', 'Continue Journey'), ENT_QUOTES, 'UTF-8') ?>
        </button>

    </div>
</div>

<!-- ============================================
     Companion registration channel
     ============================================ -->
<div id="companionModal" style="display:none;position:fixed;inset:0;z-index:10060;background:rgba(24,34,22,.55);align-items:center;justify-content:center;padding:16px;font-family:'Poppins',sans-serif;">
    <div style="width:100%;max-width:430px;max-height:92vh;overflow:auto;background:#ffffff;border-radius:20px;padding:18px 16px 20px;box-shadow:0 8px 30px rgba(0,0,0,.25);">
        <div style="display:flex;align-items:center;gap:9px;">
            <span style="width:34px;height:34px;border-radius:50%;background:#eef4e4;color:#47613b;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="bi bi-people-fill" style="font-size:15px;"></i></span>
            <div style="min-width:0;flex:1;">
                <div style="font-size:17px;font-weight:800;color:#24513c;line-height:1.2;">Companions</div>
                <div id="companionModalTripLabel" style="font-size:11px;color:#7b8a74;margin-top:1px;">Add a travelling companion</div>
            </div>
            <button type="button" id="companionModalClose" style="width:30px;height:30px;border-radius:50%;border:0;background:#f2f4f0;color:#5c6f57;font-size:15px;line-height:30px;cursor:pointer;" aria-label="Close">&times;</button>
        </div>

        <div id="companionNoTripHint" style="display:none;margin-top:12px;background:#f4f8ef;border:1px solid #dce8d2;border-radius:12px;padding:10px 12px;font-size:12.5px;color:#5c6f57;line-height:1.5;">
            <i class="bi bi-qr-code-scan"></i> Your selected companions are saved and will board with you when you scan the Bus QR.
        </div>

        <div id="companionActiveSummary" style="display:none;margin-top:12px;background:#f4f8ef;border:1px solid #dbe7d1;border-radius:12px;padding:10px 12px;font-size:12.5px;color:#47613b;font-weight:600;">
            <span id="companionActiveSummaryText"></span>
        </div>

        <div id="companionBreakdown" style="display:none;margin-top:12px;background:#f8faf6;border:1px solid #e6ecdf;border-radius:12px;padding:12px;">
            <div style="font-size:11px;font-weight:700;color:#7b8a74;letter-spacing:.5px;text-transform:uppercase;"><?= htmlspecialchars(passenger_t('companion.breakdown_title', 'Fare breakdown'), ENT_QUOTES, 'UTF-8') ?></div>
            <div id="companionBreakdownList" style="margin-top:8px;display:flex;flex-direction:column;gap:7px;"></div>
            <div id="companionBreakdownTotal" style="margin-top:10px;padding-top:9px;border-top:1px dashed #dbe4d2;display:flex;justify-content:space-between;align-items:center;font-size:13px;font-weight:800;color:#24513c;"></div>
        </div>

        <div id="companionSavedBlock" style="display:none;margin-top:14px;">
            <div style="font-size:11px;font-weight:700;color:#7b8a74;letter-spacing:.5px;text-transform:uppercase;">Saved companions</div>
            <div id="companionSavedList" style="margin-top:6px;display:flex;flex-direction:column;gap:6px;"></div>
        </div>

        <div id="companionEntry" style="border-top:1px dashed #dde5d4;margin-top:14px;padding-top:12px;">
            <div style="font-size:11px;font-weight:700;color:#7b8a74;letter-spacing:.5px;text-transform:uppercase;">Add now</div>
            <input id="companionNameInput" type="text" maxlength="80" placeholder="Full name" autocomplete="off" style="margin-top:8px;width:100%;box-sizing:border-box;border:1px solid #d3ddcb;border-radius:12px;padding:12px;font-family:'Poppins',sans-serif;font-size:13.5px;color:#24513c;outline:none;" />
            <div id="companionTypeRow" style="margin-top:9px;display:flex;gap:8px;">
            <button type="button" data-ctype="child" class="companionTypeBtn" style="flex:1;border:1.5px solid #47613b;background:#eef4e4;color:#24513c;border-radius:11px;padding:9px 0;font-family:'Poppins',sans-serif;font-size:12.5px;font-weight:800;cursor:pointer;" title="Child fare (discounted)">Child</button>
            <button type="button" data-ctype="senior" class="companionTypeBtn" style="flex:1;border:1.5px solid #d3ddcb;background:#ffffff;color:#5c6f57;border-radius:11px;padding:9px 0;font-family:'Poppins',sans-serif;font-size:12.5px;font-weight:700;cursor:pointer;" title="Senior Citizen fare (discounted)">Senior Citizen</button>
            <button type="button" data-ctype="pwd" class="companionTypeBtn" style="flex:1;border:1.5px solid #d3ddcb;background:#fff;color:#5c6f57;border-radius:11px;padding:9px 0;font-size:12.5px;" title="PWD fare (discounted)">PWD</button>
            <button type="button" data-ctype="other" class="companionTypeBtn" style="flex:1;border:1.5px solid #d3ddcb;background:#ffffff;color:#5c6f57;border-radius:11px;padding:9px 0;font-family:'Poppins',sans-serif;font-size:12.5px;font-weight:700;cursor:pointer;" title="Regular fare (standard)">Regular</button>
            </div>
            <div id="companionIdWrap" style="display:none;margin-top:9px;">
                <input id="companionIdInput" type="text" maxlength="32" placeholder="Senior Citizen ID number" autocomplete="off" style="width:100%;box-sizing:border-box;border:1px solid #d3ddcb;border-radius:12px;padding:12px;font-family:'Poppins',sans-serif;font-size:13.5px;color:#24513c;outline:none;" />
                <div style="margin-top:4px;font-size:10.5px;color:#7b8a74;">Optional &mdash; Senior Citizen fare applies from the declared type. The ID number is kept for on-board verification.</div>
            </div>
            <div style="margin-top:10px;font-size:11px;color:#7b8a74;">Press Enter or finish these fields to save your companion.</div>
        </div>

        <div id="companionAttachWrap" style="display:none;margin-top:14px;">
            <div style="font-size:11px;font-weight:700;color:#7b8a74;letter-spacing:.5px;text-transform:uppercase;">On this trip (<span id="companionAttachCount">0</span>/5)</div>
            <div id="companionAttachList" style="margin-top:6px;display:flex;flex-direction:column;gap:6px;"></div>
        </div>

        <div id="companionError" style="display:none;margin-top:10px;font-size:12.5px;font-weight:600;color:#a93226;"></div>
        <div id="companionSuccess" style="display:none;margin-top:10px;font-size:12.5px;font-weight:700;color:#47613b;"></div>

        <div style="display:flex;gap:8px;margin-top:16px;">
            <button type="button" id="companionCancelBtn" style="flex:1;border:1px solid #d3ddcb;border-radius:12px;padding:13px;background:#ffffff;color:#5c6f57;font-family:'Poppins',sans-serif;font-size:13px;font-weight:700;cursor:pointer;">Cancel</button>
            <button type="button" id="companionAttachBtn" style="flex:1.6;border:0;border-radius:12px;padding:13px;background:#47613b;color:#ffffff;font-family:'Poppins',sans-serif;font-size:13px;font-weight:800;cursor:pointer;">Done</button>
        </div>
    </div>
</div>

<script>
(function () {
    if (window.__companionChannelInit) { return; }
    window.__companionChannelInit = true;

    function el(id) { return document.getElementById(id); }

    var Companion = {
        tripId: window._activeTripId || null,
        active: [],
        profiles: [],
        attachList: [],
        breakdown: null,
        partyTotal: null,
        revision: 0,
        requestId: 0,
        readId: 0,
        restored: false,
        locked: false,
        route: null,
        paymentMethod: 'cash',
        paymentStatus: 'pending',
        onlineAmount: null,
        mutationEpoch: 0,
        writeError: null,
        queue: Promise.resolve(),
        pending: false
    };

    function hide(id) { var n = el(id); if (n) { n.style.display = 'none'; } }
    function show(id) { var n = el(id); if (n) { n.style.display = 'block'; } }
    function setErr(msg) {
        var e = el('companionError');
        if (e) { e.textContent = msg || ''; e.style.display = msg ? 'block' : 'none'; }
    }
    function setOk(msg) {
        var s = el('companionSuccess');
        if (s) {
            s.textContent = msg || '';
            s.style.display = msg ? 'block' : 'none';
            if (msg) { setTimeout(function () { if (s) { s.style.display = 'none'; } }, 5000); }
        }
    }
    function peso(v) {
        var n = (typeof v === 'number' && isFinite(v)) ? v : 0;
        return '\u20B1' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function titleCase(s) {
        return String(s || '').replace(/\w\S*/g, function (w) {
            return w.charAt(0).toUpperCase() + w.substr(1).toLowerCase();
        });
    }
    function typeShort(t) {
        t = String(t || '').toLowerCase();
        if (t === 'child') { return 'Child'; }
        if (t === 'senior' || t === 'senior citizen') { return 'Senior Citizen'; }
        if (t === 'regular' || t === 'other') { return 'Regular'; }
        return String(t || '');
    }
    function typeFareNote(t) {
        t = String(t || '').toLowerCase();
        return (t === 'child' || t === 'senior' || t === 'senior citizen' || t === 'student' || t === 'pwd' || t === 'special')
            ? 'Discounted fare' : 'Standard fare';
    }

    function activeName(p) {
        return p && p.full_name ? p.full_name : (p && p.name ? p.name : '');
    }
    function attachName(a) {
        return (a && a.full_name) ? a.full_name : (a && a.name ? a.name : '');
    }

    function resolveTripNow() {
        if (Companion.tripId) { return Promise.resolve(Companion.tripId); }
        if (window._activeTripId) { Companion.tripId = window._activeTripId; return Promise.resolve(Companion.tripId); }
        return fetch('/passenger/trip/active', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: '{}'
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (d && d.success) {
                Companion.tripId = d.trip_id || d.id || null;
            }
            return Companion.tripId;
        }).catch(function () { return Companion.tripId; });
    }

    function loadProfiles() {
        return fetch('/passenger/companions', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (d) {
            Companion.profiles = (d && d.success && Array.isArray(d.profiles)) ? d.profiles : [];
            return Companion.profiles;
        }).catch(function () { Companion.profiles = []; return Companion.profiles; });
    }

    function loadAttached() {
        var requestId = Companion.requestId;
        var readId = ++Companion.readId;
        var query = new URLSearchParams();
        if (Companion.tripId) { query.set('trip_id', Companion.tripId); }
        var context = partyQuoteContext();
        Object.keys(context).forEach(function (key) {
            if (context[key] !== null && context[key] !== '') { query.set(key, context[key]); }
        });
        return fetch('/passenger/companions/party?' + query.toString(), {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d || !d.success) { throw new Error((d && d.message) || 'Unable to restore companions.'); }
            if (requestId !== Companion.requestId || readId !== Companion.readId || Companion.pending) { return Companion.active; }
            acceptParty(d);
            return Companion.active;
        }).catch(function (error) {
            if (requestId !== Companion.requestId || readId !== Companion.readId || Companion.pending) { return Companion.active; }
            Companion.breakdown = null;
            Companion.partyTotal = null;
            setErr(error.message);
            renderPartyFare();
            return Companion.active;
        });
    }

    function renderPartyFare() {
        var fare = el('fareRate');
        if (fare && Companion.attachList.length) {
            fare.textContent = Companion.partyTotal !== null ? peso(Companion.partyTotal) : 'Fare unavailable';
        } else if (fare && Companion.partyTotal !== null && Companion.tripId) {
            fare.textContent = peso(Companion.partyTotal);
        }
        renderBreakdown();
        var payment = el('regularPartyPayment');
        if (payment) {
            payment.style.display = Companion.partyTotal !== null ? 'block' : 'none';
            var paid = Companion.paymentStatus === 'paid';
            var online = Companion.paymentMethod === 'gcash' || Companion.paymentMethod === 'hybrid';
            payment.textContent = (online ? 'Online Payment (PayMongo QRPh)' : 'Cash') + ' · ' + (paid ? 'Paid' : 'Payment pending') + '. '
                + (paid ? 'Your payment is confirmed.' : online ? 'Use Ready to Pay to scan and pay the PayMongo QRPh code. Payment is confirmed after the provider settles it.' : 'Pay the total fare to the driver. Payment stays pending until confirmed.');
        }
        var summary = el('regularFareBreakdown');
        if (summary) {
            summary.innerHTML = '';
            var bd = Companion.breakdown;
            summary.style.display = bd && Array.isArray(bd.lines) ? 'block' : 'none';
            if (bd && Array.isArray(bd.lines)) {
                bd.lines.forEach(function (line) {
                    var row = document.createElement('div');
                    row.style.cssText = 'display:flex;justify-content:space-between;gap:10px;padding:4px 0;';
                    var details = document.createElement('div');
                    details.style.cssText = 'flex:1;min-width:0;overflow-wrap:anywhere;';
                    var name = document.createElement('div');
                    name.textContent = (line.name || 'Traveller') + ' · ' + (line.type_label || 'Regular') + (line.role === 'primary' ? ' · You' : '');
                    var amounts = document.createElement('div');
                    amounts.style.opacity = '.85';
                    amounts.textContent = 'Base ' + peso(Number(line.base_fare)) + ' − Discount ' + peso(Number(line.discount_amount));
                    details.appendChild(name); details.appendChild(amounts);
                    var fare = document.createElement('strong'); fare.style.whiteSpace = 'nowrap';
                    fare.textContent = peso(Number(line.final_fare));
                    row.appendChild(details); row.appendChild(fare); summary.appendChild(row);
                    (line.extra_charges || []).forEach(function (charge) {
                        var extra = document.createElement('div');
                        extra.textContent = (charge.label || 'Additional charge') + ': ' + peso(Number(charge.amount));
                        summary.appendChild(extra);
                    });
                });
                var note = document.createElement('div');
                note.style.cssText = 'opacity:.85;padding-top:5px;';
                note.textContent = Companion.locked ? 'Destination selected — companion details are locked.' : 'Select your companions before choosing your destination.';
                summary.appendChild(note);
            }
        }
    }

    function acceptParty(d) {
        Companion.restored = true;
        Companion.locked = d.companions_locked === true;
        el('companionNameInput').disabled = Companion.locked;
        el('companionIdInput').disabled = Companion.locked;
        document.querySelectorAll('.companionTypeBtn').forEach(function (button) { button.disabled = Companion.locked; });
        Companion.tripId = d.trip_id || null;
        Companion.revision = d.revision;
        Companion.attachList = d.companions || [];
        Companion.active = d.mode === 'trip' ? Companion.attachList : [];
        Companion.breakdown = d.quote_available ? d.breakdown : null;
        Companion.partyTotal = d.quote_available ? Number(d.party_total) : null;
        Companion.route = d.route || Companion.route;
        if (window.regularTripDestinationUpdate && d.mode === 'trip') { window.regularTripDestinationUpdate(d); }
        Companion.paymentMethod = d.payment_method || (el('paymentMethod') && el('paymentMethod').value) || 'cash';
        Companion.paymentStatus = d.payment_status || 'pending';
        Companion.onlineAmount = d.online_amount_centavos;
        var select = el('destinationSelect');
        if (select && typeof d.editable === 'boolean') {
            select.dataset.locked = d.editable ? '' : '1';
            select.disabled = !d.editable;
            if (!d.editable && d.route && d.route.destination_id) { select.value = String(d.route.destination_id); }
        }
        if (window.renderDestinationDropdown) { window.renderDestinationDropdown(); }
        renderAttachList(); renderSaved(); updateCard(); renderModalSummary(); renderPartyFare();
    }

    function partyQuoteContext() {
        var selected = el('destinationSelect');
        var option = selected && selected.options && selected.selectedIndex >= 0 ? selected.options[selected.selectedIndex] : null;
        var fetched = typeof lastDestinationFetch !== 'undefined' && lastDestinationFetch ? lastDestinationFetch : {};
        var route = Companion.route || {};
        return { trip_no: fetched.tripNo || route.trip_no || (typeof QR_TRIP !== 'undefined' ? QR_TRIP : 0),
            boarding_point: fetched.boardingPoint || route.boarding_point || '',
            boarding_stop_id: fetched.boardingStopId || route.boarding_stop_id || null,
            destination: option && option.dataset ? option.dataset.destination || '' : route.destination || '',
            destination_id: selected && selected.value ? Number(selected.value) : route.destination_id || null };
    }

    function persistSelection() {
        if (Companion.locked) { setErr('Destination selected — companion details cannot be changed.'); return Companion.queue; }
        var selection = JSON.parse(JSON.stringify(Companion.attachList));
        var requestId = ++Companion.requestId;
        var epoch = Companion.mutationEpoch;
        Companion.writeError = null;
        Companion.pending = true;
        if (el('fareRate')) { el('fareRate').textContent = 'Calculating...'; }
        Companion.queue = Companion.queue.then(function () {
            if (epoch !== Companion.mutationEpoch) { return; }
            return fetch('/passenger/companions/party', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(Object.assign({}, partyQuoteContext(), { trip_id: Companion.tripId, revision: Companion.revision, companions: selection }))
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (!d || !d.success) { throw new Error((d && d.message) || 'Unable to save companions.'); }
                Companion.revision = d.revision;
                if (requestId === Companion.requestId) { acceptParty(d); setErr(''); }
            });
        }).catch(function (error) {
            Companion.mutationEpoch++;
            Companion.writeError = error;
            Companion.pending = false;
            Companion.restored = false;
            el('companionNameInput').disabled = true;
            setErr(error.message);
            return loadAttached();
        }).then(function () {
            if (requestId === Companion.requestId) { Companion.pending = false; }
        });
        return Companion.queue;
    }

    window.companionBeforeBoard = function () {
        if (!submitCompanionEntry()) { return Promise.reject(new Error('Please correct the companion details before boarding.')); }
        return Companion.queue.then(function () {
            if (Companion.writeError) { throw Companion.writeError; }
        });
    };
    window.regularPartyRefresh = function () {
        return Companion.queue.then(loadAttached).then(function () {
            renderPartyFare();
            return Companion.partyTotal !== null || Companion.attachList.length > 0;
        });
    };
    window.regularPartyApply = function (d) {
        if (Companion.pending || !d || !d.success || d.revision < Companion.revision) { return false; }
        acceptParty(d);
        return d.quote_available || Companion.attachList.length > 0;
    };
    window.regularPartySaveDestination = function (destinationId) {
        if (!submitCompanionEntry()) { return Promise.reject(new Error('Please correct the companion details before selecting a destination.')); }
        // Serialize destination commits with companion writes, just as One-Time
        // quotes the current route and complete companion list together.
        Companion.queue = Companion.queue.then(function () {
            return fetch('/passenger/trip/select-destination', { method: 'POST',
                headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ destination_id: destinationId }) });
        });
        return Companion.queue;
    };

    function renderAttachList() {
        var wrap = el('companionAttachWrap');
        var list = el('companionAttachList');
        var count = el('companionAttachCount');
        if (!wrap || !list) { return; }
        if (count) { count.textContent = String(Companion.attachList.length); }
        list.innerHTML = '';
        if (Companion.attachList.length === 0) { hide('companionAttachWrap'); return; }
        show('companionAttachWrap');
        Companion.attachList.forEach(function (item, idx) {
            var row = document.createElement('div');
            row.style.cssText = 'display:flex;align-items:center;gap:8px;background:#f8faf6;border:1px solid #e6ecdf;border-radius:11px;padding:9px 11px;';
            var badge = document.createElement('span');
            badge.style.cssText = 'flex-shrink:0;font-size:10px;font-weight:800;letter-spacing:.4px;text-transform:uppercase;color:#24513c;background:#e5eddA;border-radius:7px;padding:3px 7px;';
            badge.textContent = typeShort(item.companion_type);
            var typeEdit = document.createElement('select');
            typeEdit.style.cssText = 'max-width:105px;min-width:0;border:1px solid #d3ddcb;border-radius:7px;font-size:11px;background:#fff;color:#24513c;';
            typeEdit.setAttribute('aria-label', 'Type of ' + attachName(item));
            [['child', 'Child'], ['senior', 'Senior'], ['pwd', 'PWD'], ['other', 'Regular']].forEach(function (choice) {
                var opt = document.createElement('option'); opt.value = choice[0]; opt.textContent = choice[1]; typeEdit.appendChild(opt);
            });
            typeEdit.value = item.companion_type;
            typeEdit.disabled = Companion.locked;
            typeEdit.addEventListener('change', function () {
                item.companion_type = typeEdit.value;
                if (typeEdit.value !== 'senior') { item.declared_id_number = ''; }
                renderAttachList(); persistSelection();
            });
            var name = document.createElement('span');
            name.style.cssText = 'flex:1;min-width:0;font-size:12.5px;font-weight:700;color:#24513c;overflow-wrap:anywhere;';
            name.textContent = attachName(item);
            var rem = document.createElement('button');
            rem.type = 'button';
            rem.style.cssText = 'flex-shrink:0;border:0;background:transparent;color:#a93226;font-size:15px;cursor:pointer;line-height:1;';
            rem.textContent = '\u00D7';
            rem.disabled = Companion.locked;
            rem.setAttribute('aria-label', 'Remove ' + attachName(item));
            rem.addEventListener('click', function () { removeAttach(idx); });
            row.appendChild(name); row.appendChild(typeEdit); row.appendChild(rem);
            list.appendChild(row);
        });
        el('companionAttachBtn').disabled = Companion.attachList.length === 0;
        el('companionAttachBtn').style.opacity = Companion.attachList.length === 0 ? '.55' : '1';
    }

    function renderSaved() {
        var block = el('companionSavedBlock');
        var list = el('companionSavedList');
        if (!block || !list) { return; }
        if (!Companion.profiles.length) { hide('companionSavedBlock'); return; }
        show('companionSavedBlock');
        list.innerHTML = '';
        Companion.profiles.forEach(function (p) {
            var row = document.createElement('label');
            row.style.cssText = 'display:flex;align-items:center;gap:9px;background:#fafbf8;border:1px solid #e9eee2;border-radius:11px;padding:9px 11px;cursor:pointer;';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.style.cssText = 'width:17px;height:17px;accent-color:#47613b;flex-shrink:0;';
            cb.checked = Companion.attachList.some(function (a) { return attachName(a) === activeName(p); });
            cb.disabled = Companion.locked;
            cb.addEventListener('change', function () {
                if (cb.checked) {
                    addAttach(activeName(p), p.companion_type || 'child', p.declared_id_number || '', true, p.id);
                } else {
                    removeAttachByName(activeName(p));
                }
                renderSaved();
            });
            var text = document.createElement('span');
            text.style.cssText = 'flex:1;min-width:0;font-size:12.5px;font-weight:700;color:#24513c;overflow-wrap:anywhere;';
            text.textContent = activeName(p);
            var tag = document.createElement('span');
            tag.style.cssText = 'flex-shrink:0;font-size:10px;font-weight:800;letter-spacing:.4px;text-transform:uppercase;color:#7b8a74;';
            tag.textContent = typeShort(p.companion_type || 'child');
            row.appendChild(cb); row.appendChild(text); row.appendChild(tag);
            list.appendChild(row);
        });
    }

    function addAttach(name, ctype, declared, silent, profileId) {
        if (Companion.locked) { setErr('Destination selected — companion details cannot be changed.'); return false; }
        if (!Companion.restored) { setErr('Please wait for your saved companions to finish restoring.'); return false; }
        var clean = titleCase(String(name || '').trim());
        if (clean.length < 2 || clean.length > 100 || !/^[\p{L}\p{M} .'\-]+$/u.test(clean)) { if (!silent) { setErr('Please enter a valid full name.'); } return false; }
        var type = ['child', 'senior', 'pwd', 'other'].indexOf(ctype) !== -1 ? ctype : 'child';
        var declaredValue = String(declared || '').trim();
        if (type === 'senior' && declaredValue && (!/^[A-Za-z0-9][A-Za-z0-9 .\-\/]*$/.test(declaredValue) || (declaredValue.match(/\d/g) || []).length < 3 || declaredValue.length > 50)) {
            setErr('Please enter a valid Senior Citizen ID number or leave the optional field empty.'); return false;
        }
        if (Companion.attachList.some(function (a) { return attachName(a).toLowerCase() === clean.toLowerCase(); })) {
            if (!silent) { setErr(clean + ' is already on the list.'); }
            return false;
        }
        if (Companion.attachList.length >= 5) { setErr('A maximum of 5 companions is allowed per trip.'); return false; }
        Companion.attachList.push({ profile_id: profileId || null, full_name: clean, companion_type: type, declared_id_number: String(declared || '').trim() });
        setErr('');
        renderAttachList();
        persistSelection().then(function () {
            if (!Companion.writeError && Companion.attachList.some(function (c) { return attachName(c).toLowerCase() === clean.toLowerCase(); })) {
                setOk(clean + ' added. ' + (Companion.attachList.length + 1) + ' passengers in this party.');
            }
        });
        return true;
    }

    function removeAttach(idx) {
        if (Companion.locked) { return; }
        if (idx >= 0 && idx < Companion.attachList.length) {
            Companion.attachList.splice(idx, 1);
            renderAttachList();
            renderSaved();
            persistSelection();
        }
    }

    function removeAttachByName(name) {
        if (Companion.locked) { return; }
        Companion.attachList = Companion.attachList.filter(function (a) {
            return attachName(a).toLowerCase() !== String(name || '').toLowerCase();
        });
        renderAttachList();
        renderSaved();
        persistSelection();
    }

    function updateCard() {
        var card = el('companionPromptCard');
        if (!card) { return; }
        if (window.__companionDismissed) { hide('companionPromptCard'); return; }
        show('companionPromptCard');
        var title = el('companionCardTitle');
        var sub = el('companionCardSub');
        var label = el('companionOpenLabel');
        if (Companion.tripId && Companion.active.length) {
            title.textContent = Companion.active.length + (Companion.active.length === 1 ? ' companion' : ' companions') + ' on this trip';
            sub.textContent = Companion.locked ? 'Destination selected. Companion details are locked.' : 'Attached to your ride. Select companions before choosing your destination.';
            label.textContent = 'Manage companions';
        } else if (Companion.tripId) {
            title.textContent = 'Add companions for this trip';
            sub.textContent = 'Add a child or senior riding with you and they get their own ticket.';
            label.textContent = 'Add companion';
        } else {
            title.textContent = 'Travelling with a companion?';
            sub.textContent = 'Add a child or senior who is riding without a cellphone.';
            label.textContent = 'Add Companion';
        }
    }

    function refreshAll() {
        return Companion.queue.then(function () {
            companionResolveHint();
            return loadAttached().then(function () { updateCard(); renderModalSummary(); });
        });
    }

    function renderBreakdown() {
        var block = el('companionBreakdown');
        var list = el('companionBreakdownList');
        var total = el('companionBreakdownTotal');
        if (!block || !list || !total) { return; }

        var bd = Companion.breakdown;
        if (!bd || !Array.isArray(bd.lines) || bd.lines.length === 0) {
            hide('companionBreakdown');
            return;
        }

        list.innerHTML = '';
        bd.lines.forEach(function (line) {
            var row = document.createElement('div');
            row.style.cssText = 'display:flex;align-items:flex-start;gap:8px;';

            var left = document.createElement('div');
            left.style.cssText = 'flex:1;min-width:0;';

            var nm = document.createElement('div');
            nm.style.cssText = 'font-size:12.5px;font-weight:700;color:#24513c;overflow-wrap:anywhere;';
            nm.textContent = line.name || 'Traveller';

            var typeLabel = line.type_label || typeShort(line.type);
            var route = '';
            if (line.boarding_point || line.destination) {
                route = String(line.boarding_point || '') + (line.destination ? '  \u2192  ' + String(line.destination) : '');
            }
            var meta = document.createElement('div');
            meta.style.cssText = 'font-size:11px;color:#7b8a74;margin-top:1px;';
            meta.textContent = (line.role === 'primary' ? typeLabel + '  \u2022  You' : typeLabel)
                + (route ? '  \u2022  ' + route : '');

            left.appendChild(nm);
            left.appendChild(meta);

            var base = Number(line.base_fare) || 0;
            var discount = Number(line.discount_amount) || 0;
            if (base > 0) {
                var fareMeta = document.createElement('div');
                fareMeta.style.cssText = 'font-size:11px;color:#7b8a74;margin-top:1px;';
                fareMeta.textContent = 'Base ' + peso(base) + '  \u2022  Discount ' + peso(discount);
                left.appendChild(fareMeta);
            }

            var extras = Array.isArray(line.extra_charges) ? line.extra_charges : [];
            extras.forEach(function (extra) {
                var ex = document.createElement('div');
                ex.style.cssText = 'font-size:11px;color:#7b8a74;margin-top:1px;';
                ex.textContent = (extra.label || 'Extra charge') + '  ' + peso(extra.amount);
                left.appendChild(ex);
            });

            var right = document.createElement('div');
            right.style.cssText = 'flex-shrink:0;font-size:12.5px;font-weight:800;color:#24513c;';
            right.textContent = peso(line.final_fare);

            row.appendChild(left);
            row.appendChild(right);
            list.appendChild(row);
        });

        total.innerHTML = '';
        var tl = document.createElement('span');
        tl.textContent = 'Party total';
        var tr = document.createElement('span');
        tr.textContent = peso(bd.party_total);
        total.appendChild(tl);
        total.appendChild(tr);

        show('companionBreakdown');
    }

    function renderModalSummary() {
        el('companionAttachBtn').disabled = false;
        el('companionAttachBtn').style.opacity = '1';
        var tripLabel = el('companionModalTripLabel');
        if (Companion.tripId) {
            tripLabel.textContent = 'Trip #' + Companion.tripId;
            hide('companionNoTripHint');
            if (Companion.active.length) {
                var sum = el('companionActiveSummaryText');
                sum.textContent = Companion.active.length + (Companion.active.length === 1 ? ' companion' : ' companions') + ' added to this trip.';
                show('companionActiveSummary');
            } else {
                hide('companionActiveSummary');
            }
        } else {
            tripLabel.textContent = 'Add a travelling companion';
            show('companionNoTripHint');
            hide('companionActiveSummary');
        }
        renderBreakdown();
    }

    function companionResolveHint() {
        var modal = el('companionModal');
        var open = el('companionOpenBtn');
        if (!modal || !open) { return; }
        if (Companion.tripId) {
            open.style.background = '#47613b';
            show('companionAttachBtn');
        } else {
            show('companionNoTripHint');
        }
    }

    window.companionSetTrip = function (tripId) {
        Companion.tripId = tripId || null;
        if (!Companion.tripId) { Companion.locked = false; Companion.active = []; Companion.attachList = []; Companion.breakdown = null; Companion.partyTotal = null; Companion.revision = 0; renderAttachList(); renderPartyFare(); updateCard(); return; }
        companionResolveHint();
        Companion.queue.then(loadAttached).then(function () { updateCard(); renderModalSummary(); });
    };

    function openModal() {
        setErr(''); setOk('');
        show('companionModal');
        el('companionModal').style.display = 'flex';
        refreshAll();
        loadProfiles().then(function () { renderSaved(); });
    }

    // ---- wiring ----
    el('companionOpenBtn').addEventListener('click', openModal);
    el('companionModalClose').addEventListener('click', function () { hide('companionModal'); });
    el('companionCancelBtn').addEventListener('click', function () { hide('companionModal'); });
    el('companionModal').addEventListener('click', function (e) {
        if (e.target && e.target.id === 'companionModal') { hide('companionModal'); }
    });

    el('companionDismissBtn').addEventListener('click', function () {
        window.__companionDismissed = true;
        hide('companionPromptCard');
        try { localStorage.setItem((window.PassengerStorage && window.PassengerStorage.KEYS && window.PassengerStorage.KEYS.companionDismissed) || 'rconnect_companion_dismissed', '1'); } catch (e) {}
    });

    var selectedType = 'child';
    var typeBtns = document.querySelectorAll('.companionTypeBtn');
    typeBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            selectedType = btn.getAttribute('data-ctype') || 'child';
            typeBtns.forEach(function (b) {
                var activeSel = b.getAttribute('data-ctype') === selectedType;
                b.style.border = '1.5px solid ' + (activeSel ? '#47613b' : '#d3ddcb');
                b.style.background = activeSel ? '#eef4e4' : '#ffffff';
                b.style.color = activeSel ? '#24513c' : '#5c6f57';
                b.style.fontWeight = activeSel ? '800' : '700';
            });
            if (selectedType === 'senior') { show('companionIdWrap'); } else { hide('companionIdWrap'); }
        });
    });

    function submitCompanionEntry() {
        var name = el('companionNameInput').value;
        if (!String(name || '').trim()) { return true; }
        var declared = selectedType === 'senior' ? el('companionIdInput').value : '';
        if (addAttach(name, selectedType, declared)) {
            el('companionNameInput').value = '';
            el('companionIdInput').value = '';
            return true;
        }
        return false;
    }

    el('companionNameInput').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); submitCompanionEntry(); }
    });
    el('companionIdInput').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); submitCompanionEntry(); }
    });
    el('companionEntry').addEventListener('focusout', function (e) {
        if (e.relatedTarget && el('companionEntry').contains(e.relatedTarget)) { return; }
        submitCompanionEntry();
    });

    el('companionAttachBtn').addEventListener('click', function () {
        if (!submitCompanionEntry()) { return; }
        Companion.queue.then(function () { hide('companionModal'); });
    });

    // Activation: after a successful Bus QR boarding, the mode unlocks.
    window.addEventListener('DOMContentLoaded', function () {
        var dismissed = false;
        try { dismissed = (localStorage.getItem((window.PassengerStorage && window.PassengerStorage.KEYS && window.PassengerStorage.KEYS.companionDismissed) || 'rconnect_companion_dismissed') === '1'); } catch (e) {}
        // Restore even when the prompt card was dismissed: its party still exists.
        Companion.queue = loadAttached();
        loadProfiles().then(renderSaved);
    });
})();
</script>

</body>
</html>
