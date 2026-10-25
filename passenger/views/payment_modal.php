<?php

require_once $_SERVER["DOCUMENT_ROOT"] . "/app/Core/Database.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/passenger/services/PaymongoConfig.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/passenger/services/OneTime/OneTimeService.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/passenger/services/Companion/CompanionService.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/passenger/services/Companion/RegularPartyService.php";

use App\Core\Database;

$pmInput = json_decode((string) file_get_contents("php://input"), true);
if (!is_array($pmInput)) {
    $pmInput = [];
}

$id = (int) ($pmInput["id"] ?? ($_GET["id"] ?? 0));
$receiptToken = trim((string) ($pmInput["token"] ?? ($_GET["t"] ?? "")));

if (!isset($_SESSION["passenger_id"]) && $id > 0 && $receiptToken !== "") {
    try {
        $dbConn = Database::tryGet();
        $recovered = $dbConn ? OneTimeService::tripBySubmissionToken($dbConn, $id, $receiptToken) : null;
        if ($recovered && !empty($recovered["passenger_id"])) {
            $_SESSION["passenger_id"] = (int) $recovered["passenger_id"];
            $_SESSION["passenger_name"] = (string) ($recovered["passenger_name"] ?? "");
            $_SESSION["is_one_time"] = true;
        }
    } catch (\Throwable $e) {
    }
}

if (!isset($_SESSION["passenger_id"])) {
    echo "<div class='text-center text-danger py-4'>Session expired. Please login again.</div>";
    exit();
}

if ($id <= 0) {
    echo "Invalid trip ID";
    exit();
}

$conn = Database::tryGet();
if (!$conn) {
    echo "<div class='text-center text-danger py-4'>Service temporarily unavailable. Please try again.</div>";
    exit();
}

$stmt = $conn->prepare("
    SELECT
        pt.*,
        p.full_name AS name,
        p.discount_type, p.registration_type,
        bt.route_name,
        d.full_name AS driver_name
    FROM passenger_trips pt
    LEFT JOIN passengers p
        ON pt.passenger_id = p.id
    LEFT JOIN bus_trips bt
        ON pt.trip_no = bt.id
    LEFT JOIN drivers d
        ON pt.driver_id = d.driver_id
    WHERE pt.id = ?
    AND pt.passenger_id = ?
    LIMIT 1
");
$stmt->execute([$id, $_SESSION["passenger_id"]]);
$passenger = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$passenger) {
    echo "Trip not found";
    exit();
}

$name = htmlspecialchars($passenger["name"] ?? "Passenger");
$bus = htmlspecialchars($passenger["bus_no"] ?? "—");
$tripNo = htmlspecialchars($passenger["id"] ?? "—");
$driverName = htmlspecialchars($passenger["driver_name"] ?? "—");
$routeName = htmlspecialchars($passenger["route_name"] ?? "—");
$boarding = htmlspecialchars($passenger["boarding_point"] ?? "—");
$destination = htmlspecialchars($passenger["destination"] ?? "—");

$fare = (float) ($passenger["fare"] ?? 0);
$payment_method = $passenger["payment_method"] ?? "Not selected";
$payment_status = $passenger["payment_status"] ?? "Pending";
$mode = strtolower($payment_method);
$paymongoAvailable = PaymongoConfig::canCheckout();
$isPaid = strtolower($payment_status) === "paid";

$paymentMethodLabel = match ($mode) {
    "gcash" => "Online Payment",
    "hybrid" => "Online Payment + Cash",
    "cash" => "Cash",
    default => ucfirst((string) $payment_method),
};

$qrImage = (string) ($passenger["paymongo_qr_image"] ?? "");
$qrExpiresAtRaw = (string) ($passenger["paymongo_qr_expires_at"] ?? "");
$qrExpiresTs = $qrExpiresAtRaw !== "" ? strtotime($qrExpiresAtRaw) : 0;
$qrValid = $qrImage !== "" && $qrExpiresTs > time();

$hybridCash = 0;
$hybridGcash = 0;
if ($mode === "hybrid") {
    if (!empty($passenger["hybrid_payment_details"])) {
        $details = json_decode($passenger["hybrid_payment_details"], true);
        if (is_array($details)) {
            $hybridCash = (float) ($details["cash"] ?? 0);
            $hybridGcash = (float) ($details["gcash"] ?? 0);
        }
    }
    if ($hybridCash <= 0 && $hybridGcash <= 0) {
        $hybridCash = (float) ($passenger["cash_amount"] ?? 0);
        $hybridGcash = (float) ($passenger["gcash_amount"] ?? 0);
    }
}

$partyFare = $fare;
$partyTotal = $fare;
$partyCompanions = 0;
$regularParty = strtolower((string) ($passenger['registration_type'] ?? '')) !== 'one_time';
try {
    if ($regularParty) {
        $rows = RegularPartyService::travellerRows($conn, (int) $passenger['id']);
        $passenger['passenger_name'] = $passenger['name'];
        $quote = RegularPartyService::storedQuote($conn, $passenger, $rows);
        $partyCompanions = count($rows);
        $partyFare = ($quote['online_amount_centavos'] ?? 0) / 100;
        $partyTotal = $quote['party_total'];
        $partyBreakdown = $quote['breakdown'];
        if (!$quote['quote_available']) { $paymongoAvailable = false; $qrValid = false; }
    } else {
    $partyCompanions = count(CompanionService::companionRows($conn, (int) ($passenger["id"] ?? 0)));
    $onlineCents = CompanionService::partyOnlineAmountCentavos($conn, $passenger);
    if (is_int($onlineCents) && $onlineCents > 0) {
        $partyFare = round($onlineCents / 100, 2);
    }
    $partyBreakdown = CompanionService::partyBreakdown(
        $conn,
        $passenger,
        CompanionService::companionRows($conn, (int) ($passenger["id"] ?? 0))
    );
    if (isset($partyBreakdown["party_total"])) {
        $partyTotal = (float) $partyBreakdown["party_total"];
    }
    }
} catch (\Throwable $e) {
    if ($regularParty) {
        $partyFare = 0; $partyTotal = null; $partyBreakdown = null;
        $paymongoAvailable = false; $qrValid = false;
        error_log('Regular party payment summary failed: ' . $e->getMessage());
    } else {
    $partyFare = $fare;
    $partyTotal = $fare;
    $partyCompanions = 0;
    }
}

$pmOutcomeNote = "";
$pmHistory = (string) ($passenger["paymongo_history"] ?? "");
if (!$isPaid && $pmHistory !== "") {
    $h = strtolower($pmHistory);
    if (strpos($h, "amount_mismatch") !== false) {
        $pmOutcomeNote = "Amount paid does not match the fare. Please contact support.";
    } elseif (strpos($h, "not_settled") !== false || strpos($h, "pending:") !== false) {
        $pmOutcomeNote = "Payment is still being confirmed by PayMongo.";
    } elseif (strpos($h, "failed") !== false) {
        $pmOutcomeNote = 'Payment failed. Tap "Pay Online" to try again.';
    } elseif (strpos($h, "cancelled") !== false) {
        $pmOutcomeNote = 'Payment cancelled. Tap "Pay Online" to try again.';
    } elseif (strpos($h, "expired") !== false) {
        $pmOutcomeNote = "The payment code has expired. Generate a new one to try again.";
    } elseif (strpos($h, "livemode_mismatch") !== false) {
        $pmOutcomeNote = "Payment could not be verified. Please contact support.";
    }
}

$statusLabel = strtoupper(htmlspecialchars($payment_status));

$compact =
    (!empty($pmInput["compact"]) && (string) $pmInput["compact"] !== "0") ||
    (isset($_GET["compact"]) && (string) $_GET["compact"] !== "" && (string) $_GET["compact"] !== "0");
?>

<style>

@media print {
    body * { visibility: hidden; }
    #pmModalRoot, #pmModalRoot * { visibility: visible; }
    #pmModalRoot {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        max-width: 100%;
        max-height: none;
        box-shadow: none;
        border: none;
        border-radius: 0;
    }
}
</style>

<?php if ($compact): ?>
<style>

.pm-compact-wrap{
    padding:0 !important;
    margin:0 !important;
}
#pmModalRoot{
    width:100% !important;
    max-width:100% !important;
    max-height:none !important;
    border:none !important;
    border-radius:0 !important;
    box-shadow:none !important;
    background:transparent !important;
}
#pmModalRoot > div:first-child,
#pmModalRoot > div:last-child{
    display:none !important;
}
#pmModalRoot > div:nth-child(2){
    padding:0 !important;
    overflow:visible !important;
}
.pm-details,
.pm-payable{
    display:none !important;
}
.pm-pay-box{
    background:transparent !important;
    border:none !important;
    padding:0 !important;
}
#pmCheckBtn{
    background:transparent !important;
    border:none !important;
    color:#7b8a74 !important;
    font-size:11.5px !important;
    text-decoration:underline;
    padding:10px 6px !important;
}
</style>
<?php endif; ?>

<div class="container-fluid d-flex justify-content-center px-2 py-0 mt-2<?php echo $compact
    ? " pm-compact-wrap"
    : ""; ?>">

    <div
        class="card border-0 shadow-sm"
        id="pmModalRoot"
        style="
            width:95%;
            max-width:700px;
            max-height:78vh;
            border-radius:16px;
            overflow:hidden;
            background:#fffef8;
            font-size:13px;
        "
    >

        <div
            class="text-white text-center py-2 px-3"
            style="
                background:linear-gradient(
                    135deg,
                    #47613b,
                    #5e7b50
                );
            "
        >

            <h6 class="mb-0 fw-bold">
                <?= htmlspecialchars(passenger_t('payment.title', 'Payment Details'), ENT_QUOTES, 'UTF-8') ?>
            </h6>

            <small class="opacity-75">
                <?= htmlspecialchars(passenger_t('trips.reference_no', 'Reference #'), ENT_QUOTES, 'UTF-8') ?><?php echo $tripNo; ?>
            </small>

        </div>

        <div
            class="card-body overflow-auto px-3 py-2<?php echo $compact ? " pm-compact" : ""; ?>"
        >

<?php if (strtolower($payment_status) === "rejected"): ?>
            <div class="rounded px-3 py-2 mb-3" style="background:#fde8e8;border:1px solid #f5c6cb;color:#a93226;">
                <div style="font-weight:700;font-size:12px;margin-bottom:2px;"><?= htmlspecialchars(passenger_t('payment.rejected_title', 'Payment Rejected'), ENT_QUOTES, 'UTF-8') ?></div>
                <div style="font-size:11px;line-height:1.5;"><?= htmlspecialchars(passenger_t('payment.rejected_body', 'Your payment was not completed. Tap Pay Online below to try again.'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
<?php endif; ?>

<?php if ($pmOutcomeNote): ?>
            <div class="rounded px-3 py-2 mb-3" style="background:#fff8e1;border:1px solid #f0dca8;color:#8a6d3b;">
                <div style="font-size:11px;line-height:1.5;"><?php echo htmlspecialchars($pmOutcomeNote); ?></div>
            </div>
<?php endif; ?>

            <div class="mb-3 pm-details">

                <div class="small text-muted mb-1">
                    <?= htmlspecialchars(passenger_t('trips.passenger_details', 'PASSENGER DETAILS'), ENT_QUOTES, 'UTF-8') ?>
                </div>

                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span><?= htmlspecialchars(passenger_t('trips.passenger', 'Passenger'), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?php echo $name; ?></strong>
                </div>

                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span><?= htmlspecialchars(passenger_t('trips.bus_no', 'Bus No'), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?php echo $bus; ?></strong>
                </div>

                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span><?= htmlspecialchars(passenger_t('trips.route', 'Route'), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?php echo $routeName; ?></strong>
                </div>

            </div>

            <div class="mb-3 pm-details">

                <div class="small text-muted mb-1">
                    <?= htmlspecialchars(passenger_t('trips.trip_details_section', 'TRIP DETAILS'), ENT_QUOTES, 'UTF-8') ?>
                </div>

                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span><?= htmlspecialchars(passenger_t('trips.from', 'From'), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?php echo $boarding; ?></strong>
                </div>

                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span><?= htmlspecialchars(passenger_t('trips.to', 'To'), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?php echo $destination; ?></strong>
                </div>

            </div>

            <div class="mb-2 pm-details">

                <div class="small text-muted mb-1">
                    <?= htmlspecialchars(passenger_t('trips.payment_section', 'PAYMENT'), ENT_QUOTES, 'UTF-8') ?>
                </div>

                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span><?= htmlspecialchars(passenger_t('trips.method', 'Method'), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?php echo htmlspecialchars($paymentMethodLabel); ?></strong>
                </div>

<?php if ($mode === "hybrid"): ?>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span><?= htmlspecialchars(passenger_t('payment.cash_to_driver', 'Cash (to driver)'), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong>₱<?php echo number_format($hybridCash, 2); ?></strong>
                </div>

                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span><?= htmlspecialchars(passenger_t('payment.online_via_paymongo', 'Online Payment (via PayMongo)'), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong>₱<?php echo number_format($hybridGcash, 2); ?></strong>
                </div>
<?php endif; ?>

            </div>

<?php if (in_array($mode, ["gcash", "hybrid"], true)): ?>

<?php if ($isPaid): ?>
            <div class="rounded px-3 py-3 mb-2 text-center" style="background:#e8f5e9;border:1px solid #589657;">
                <i class="bi bi-check-circle-fill" style="color:#589657;font-size:1.1rem;"></i>
                <div style="font-weight:700;color:#24513c;font-size:13px;margin-top:4px;"><?= htmlspecialchars(passenger_t('payment.confirmed', 'Payment Confirmed'), ENT_QUOTES, 'UTF-8') ?></div>
<?php if (!empty($passenger["paymongo_payment_id"])): ?>
                <div style="font-size:10px;color:#6b7280;margin-top:3px;"><?= htmlspecialchars(passenger_t('payment.paymongo_ref', 'PayMongo Ref:'), ENT_QUOTES, 'UTF-8') ?> <?php echo htmlspecialchars(
                    $passenger["paymongo_payment_id"],
                ); ?></div>
<?php endif; ?>
            </div>
<?php elseif (!$paymongoAvailable): ?>
            <div class="rounded px-3 py-3 mb-2 text-center" style="background:#fef0f0;border:1px solid #f5c6cb;">
                <div style="font-weight:700;color:#a93226;font-size:13px;"><?= htmlspecialchars(passenger_t('payment.unavailable', 'Online payment is temporarily unavailable.'), ENT_QUOTES, 'UTF-8') ?></div>
                <div style="font-size:11px;color:#555;margin-top:3px;"><?= htmlspecialchars(passenger_t('payment.pay_cash', 'Please pay the fare in cash to the driver for this trip.'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
<?php else: ?>
            <div class="rounded px-3 py-3 mb-2 pm-pay-box" style="background:#f8f9fa;border:1px dashed #cdd9b5;">

                <div class="text-center pm-payable" style="margin-bottom:14px;">
                    <div style="font-size:10px;font-weight:700;letter-spacing:.7px;color:#8a9a8a;text-transform:uppercase;">
                        <?= htmlspecialchars(passenger_t('payment.amount_due', 'Amount Due'), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div style="font-size:28px;font-weight:800;color:#24513c;line-height:1.1;margin-top:3px;">
                        ₱<?php echo number_format($partyFare, 2); ?>
                    </div>
<?php if ($partyCompanions > 0): ?>
                    <div style="font-size:11px;color:#7b8a74;margin-top:4px;">
                        <?= htmlspecialchars(passenger_t('payment.includes_party', 'Includes travelling companions'), ENT_QUOTES, 'UTF-8') ?>
                        (<?php echo (int) $partyCompanions; ?>)
                    </div>
<?php endif; ?>
                </div>

<?php if (isset($partyBreakdown["lines"]) && is_array($partyBreakdown["lines"]) && count($partyBreakdown["lines"]) > 1): ?>
                <div style="margin:0 0 12px;">
<?php foreach ($partyBreakdown["lines"] as $pline): ?>
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;padding:4px 0;border-bottom:1px solid #eef1ea;font-size:11.5px;">
                        <div style="min-width:0;">
                            <div style="font-weight:700;color:#24513c;overflow-wrap:anywhere;"><?php echo htmlspecialchars(
                                (string) ($pline["name"] ?? "Traveller"),
                                ENT_QUOTES,
                                "UTF-8",
                            ); ?></div>
                            <div style="color:#7b8a74;">
                                <?php echo htmlspecialchars(
                                    (string) ($pline["type_label"] ?? "Regular"),
                                    ENT_QUOTES,
                                    "UTF-8",
                                ); ?><?php if ((float) ($pline["discount_amount"] ?? 0) > 0): ?> &middot; <?= htmlspecialchars(
    passenger_t("payment.saved", "Saved"),
    ENT_QUOTES,
    "UTF-8",
) ?> ₱<?php echo number_format((float) $pline["discount_amount"], 2); ?><?php endif; ?>
                            </div>
                        </div>
                        <div style="flex-shrink:0;font-weight:700;color:#24513c;">₱<?php echo number_format((float) ($pline["final_fare"] ?? 0), 2); ?></div>
                    </div>
<?php endforeach; ?>
                </div>
<?php endif; ?>

                <div id="pmStatusLine" style="font-size:11px;color:#555;margin-bottom:10px;display:none;text-align:center;"></div>

<?php if ($qrValid): ?>
                <div id="pmQrBox" class="text-center" style="margin-bottom:14px;">
                    <div style="background:#fff;border:1px solid #e2e8dc;border-radius:18px;padding:16px 14px 14px;box-shadow:0 6px 18px rgba(71,97,59,.08);">
                        <div style="display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;letter-spacing:.6px;color:#2f6b43;text-transform:uppercase;margin-bottom:12px;">
                            <i class="bi bi-qr-code"></i> QR Ph
                        </div>
                        <div>
                            <img id="pmQrImg" src="<?php echo htmlspecialchars(
                                $qrImage,
                                ENT_QUOTES,
                                "UTF-8",
                            ); ?>" alt="QR Ph payment code" style="width:210px;height:210px;display:block;margin:0 auto;border-radius:10px;">
                        </div>
                        <div style="font-size:11px;color:#6b7a68;line-height:1.5;margin-top:12px;">
                            <?= passenger_t('payment.scan_hint', 'Scan with any bank or e-wallet app (GCash, Maya, BPI, BDO, ...)') ?>
                        </div>
                        <div id="pmQrCountdown" style="display:none;margin-top:10px;font-size:11px;font-weight:600;color:#c0812b;background:#fff7e6;border:1px solid #f0dca8;border-radius:999px;padding:3px 12px;"></div>
                    </div>
                    <button
                        onclick="pmDownloadQr()"
                        style="
                            margin-top:10px;
                            padding:9px 20px;
                            border:1.5px solid #c6d48c;
                            background:#f6f9ef;
                            border-radius:999px;
                            font-weight:700;
                            font-size:12px;
                            color:#24513c;
                            cursor:pointer;
                            display:inline-flex;
                            align-items:center;
                            gap:6px;
                            box-shadow:0 2px 8px rgba(71,97,59,.10);
                        "
                    >
                        <i class="bi bi-download"></i> <?= htmlspecialchars(passenger_t('qr.download', 'Download QR'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
<?php else: ?>
                <div id="pmQrBox" class="text-center" style="display:none;margin-bottom:14px;">
                    <div style="background:#fff;border:1px solid #e2e8dc;border-radius:18px;padding:16px 14px 14px;box-shadow:0 6px 18px rgba(71,97,59,.08);">
                        <div style="display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;letter-spacing:.6px;color:#2f6b43;text-transform:uppercase;margin-bottom:12px;">
                            <i class="bi bi-qr-code"></i> QR Ph
                        </div>
                        <div>
                            <img id="pmQrImg" src="" alt="QR Ph payment code" style="width:210px;height:210px;display:block;margin:0 auto;border-radius:10px;">
                        </div>
                        <div style="font-size:11px;color:#6b7a68;line-height:1.5;margin-top:12px;">
                            <?= passenger_t('payment.scan_hint', 'Scan with any bank or e-wallet app (GCash, Maya, BPI, BDO, ...)') ?>
                        </div>
                        <div id="pmQrCountdown" style="display:none;margin-top:10px;font-size:11px;font-weight:600;color:#c0812b;background:#fff7e6;border:1px solid #f0dca8;border-radius:999px;padding:3px 12px;"></div>
                    </div>
                    <button
                        onclick="pmDownloadQr()"
                        style="
                            margin-top:10px;
                            padding:9px 20px;
                            border:1.5px solid #c6d48c;
                            background:#f6f9ef;
                            border-radius:999px;
                            font-weight:700;
                            font-size:12px;
                            color:#24513c;
                            cursor:pointer;
                            display:inline-flex;
                            align-items:center;
                            gap:6px;
                            box-shadow:0 2px 8px rgba(71,97,59,.10);
                        "
                    >
                        <i class="bi bi-download"></i> <?= htmlspecialchars(passenger_t('qr.download', 'Download QR'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
<?php endif; ?>

                <button
                    id="pmPayBtn"
                    onclick="startPaymongoCheckout()"
                    style="
                        width:100%;
                        padding:12px;
                        border:none;
                        border-radius:30px;
                        font-weight:700;
                        font-size:13px;
                        color:#fff;
                        background:linear-gradient(90deg,#7bbf5e,#5aa943);
                        cursor:pointer;
                        box-shadow:0 4px 12px rgba(71,97,59,.18);
                    "
                >
                    <i class="bi bi-qr-code-scan"></i> <?= htmlspecialchars(passenger_t('payment.pay_online', 'Pay Online'), ENT_QUOTES, 'UTF-8') ?>
                </button>

                <button
                    id="pmCheckBtn"
                    onclick="pmReconcile()"
                    style="
                        width:100%;
                        margin-top:8px;
                        padding:9px;
                        border:none;
                        background:transparent;
                        font-weight:600;
                        font-size:11.5px;
                        color:#6b8a6b;
                        text-decoration:underline;
                        cursor:pointer;
                    "
                >
                    <i class="bi bi-arrow-repeat"></i> <?= htmlspecialchars(passenger_t('payment.check_status', 'Check payment status'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
<?php endif; ?>

            <input type="hidden" id="pmTripId" value="<?php echo htmlspecialchars($passenger["id"]); ?>">
            <input type="hidden" id="pmReceiptToken" value="<?php echo htmlspecialchars($receiptToken); ?>">
<?php if (!$isPaid): ?>
            <input type="hidden" id="pmPaymentStatus" value="<?php echo htmlspecialchars(
                strtolower($payment_status),
            ); ?>">
            <input type="hidden" id="pmHistory" value="<?php echo htmlspecialchars($pmHistory); ?>">
<?php endif; ?>
            <input type="hidden" id="pmQrExpiresAt" value="<?php echo $qrValid
                ? htmlspecialchars(date("c", $qrExpiresTs), ENT_QUOTES, "UTF-8")
                : ""; ?>">

<?php endif; ?>

        </div>

        <div
            class="px-3 py-2"
            style="
                background:#faf9e5;
                border-top:1px solid #ece7c6;
            "
        >

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <div class="text-muted small">
                        <?= htmlspecialchars(passenger_t('trips.total_fare', 'Total Fare'), ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <div
                        style="
                            font-size:22px;
                            font-weight:700;
                            color:#47613b;
                            line-height:1;
                        "
                    >
                        <?php echo $partyTotal === null ? htmlspecialchars(passenger_t('home.fare_unavailable', 'Fare unavailable'), ENT_QUOTES, 'UTF-8') : '₱' . number_format($partyTotal, 2); ?>
                    </div>

                </div>

                <span
                    class="badge rounded-pill px-2 py-1"
                    style="
                        background:<?php echo $isPaid ? "#47613b" : "#c0812b"; ?>;
                        font-size:11px;
                    "
                >
                    <?php echo $statusLabel; ?>
                </span>

            </div>

<?php if ($isPaid): ?>
            <button
                onclick="printReceipt()"
                style="
                    width:100%;
                    margin-top:10px;
                    padding:8px;
                    border:1px solid #cdd9b5;
                    border-radius:30px;
                    font-weight:600;
                    font-size:12px;
                    color:#47613b;
                    background:#fff;
                    cursor:pointer;
                "
            >
                <i class="bi bi-printer"></i> Print Receipt
            </button>
<?php endif; ?>

        </div>

    </div>

</div>

<script>
function printReceipt(){
    var root = document.getElementById('pmModalRoot') || document.getElementById('tripModalRoot');
    if(!root){ window.print(); return; }
    var clone = root.cloneNode(true);
    clone.querySelectorAll('script').forEach(function(s){ s.remove(); });
    clone.querySelectorAll('style').forEach(function(s){ s.remove(); });
    clone.querySelectorAll('[id]').forEach(function(el){ el.removeAttribute('id'); });
    var win = window.open('', '_blank');
    if(!win){ window.print(); return; }
    win.document.open();
    win.document.write(
        '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>R-CONNECT Receipt</title>' +
        '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">' +
        '<style>body{font-family:Poppins,Arial,sans-serif;background:#fff;margin:0;padding:24px;}' +
        '.card{border-radius:16px;box-shadow:none!important;max-width:700px;margin:0 auto;}' +
        '@media print{body{padding:0;}}</style></head><body>' + clone.outerHTML + '</body></html>'
    );
    win.document.close();
    win.focus();
    setTimeout(function(){ win.print(); }, 300);
}
</script>

<?php if (in_array($mode, ["gcash", "hybrid"], true) && !$isPaid): ?>
<script>
var pmPollTimer = null;
function pmReceiptTokenValue() {
    var el = document.getElementById('pmReceiptToken');
    return el ? String(el.value || '') : '';
}
function pmTokenQuery() {
    var t = pmReceiptTokenValue();
    return t ? ('&t=' + encodeURIComponent(t)) : '';
}
function pmReloadContent() {
    var tripId = document.getElementById('pmTripId');
    if (!tripId) return;
    fetch('/passenger/paymongo/modal', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: tripId.value, token: pmReceiptTokenValue()})
    })
    .then(function(r){ return r.text(); })
    .then(function(text){
        var root = document.getElementById('pmModalRoot');
        if (root && root.parentElement) {
            root.parentElement.innerHTML = text;

            var doc = new DOMParser().parseFromString(text, 'text/html');
            var scripts = doc.querySelectorAll('script');
            for (var i = 0; i < scripts.length; i++) {
                var ns = document.createElement('script');
                if (scripts[i].src) { ns.src = scripts[i].src; }
                else { ns.textContent = scripts[i].textContent; }
                document.body.appendChild(ns);
                document.body.removeChild(ns);
            }
        }
        else { window.location.reload(); }
    })
    .catch(function(){ window.location.reload(); });
}
function pmClearConsumedTrip() {
    try {
        var key = (typeof TRIP_INFO_KEY !== 'undefined') ? TRIP_INFO_KEY : 'passenger_active_trip_info';
        var tid = document.getElementById('pmTripId');
        if (!tid) return;
        var info = JSON.parse(localStorage.getItem(key) || 'null');
        if (info && String(info.trip_id) === String(tid.value)) {
            localStorage.removeItem(key);
        }
    } catch (e) {}
}
var pmQrCountdownTimer = null;
function pmRenderQr(src, expiresAt) {
    var img = document.getElementById('pmQrImg');
    var box = document.getElementById('pmQrBox');
    var cd = document.getElementById('pmQrCountdown');
    var btn = document.getElementById('pmPayBtn');
    if (img) { img.src = src; }
    if (box) { box.style.display = 'block'; }
    if (cd && expiresAt) {
        cd.style.display = 'inline-block';
        var end = new Date(expiresAt).getTime();
        if (pmQrCountdownTimer) { clearInterval(pmQrCountdownTimer); }
        function tick(){
            var left = end - Date.now();
            if (left > 0) {
                var s = Math.floor(left / 1000);
                var m = Math.floor(s / 60); s = s % 60;
                cd.textContent = 'Code expires in ' + m + ':' + String(s).padStart(2, '0');
            } else {
                cd.textContent = 'Code expired.';
                if (pmQrCountdownTimer) { clearInterval(pmQrCountdownTimer); }
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-qr-code-scan"></i> Get a new code'; }
            }
        }
        tick();
        pmQrCountdownTimer = setInterval(tick, 1000);
    }
}
var PM_QR_META = <?= json_encode([
    'fare' => number_format($partyFare, 2),
    'bus' => (string) ($passenger['bus_no'] ?? ''),
    'route' => (string) ($passenger['route_name'] ?? ''),
    'passenger' => (string) ($passenger['name'] ?? ''),
    'trip' => (string) ($passenger['id'] ?? ''),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function pmRoundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
}

function pmDownloadQr() {
    var img = document.getElementById('pmQrImg');
    if (!img || !img.src) return;
    var tripEl = document.getElementById('pmTripId');
    var tripId = tripEl ? tripEl.value : 'payment';

    function triggerDownload(href, filename) {
        var a = document.createElement('a');
        a.href = href;
        a.download = filename;
        a.rel = 'noopener';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    function rawDownload() {
        if (img.src.indexOf('data:') === 0) {
            try {
                var parts = img.src.split(',');
                var b64 = parts.length > 1 ? parts[1] : '';
                var raw = atob(b64);
                var mimeMatch = parts[0].match(/^data:([^;]+);/);
                var mime = (mimeMatch && mimeMatch[1]) ? mimeMatch[1] : 'image/png';
                var bytes = new Uint8Array(raw.length);
                for (var i = 0; i < raw.length; i++) {
                    bytes[i] = raw.charCodeAt(i);
                }
                var blob = new Blob([bytes], { type: mime });
                var url = URL.createObjectURL(blob);
                triggerDownload(url, 'R-CONNECT-QR-' + tripId + '.png');
                setTimeout(function(){ URL.revokeObjectURL(url); }, 3000);
                return;
            } catch (e) {

            }
        }
        triggerDownload(img.src, 'R-CONNECT-QR-' + tripId + '.png');
    }

    // Compose a branded payment card around the QR so the downloaded file
    // carries the R-Connect identity and the trip/fare details.
    function compose(src, revokeUrl) {
    var qr = new Image();

    qr.onload = function () {
        try {
            var W = 760;
            var H = 1080;
            var canvas = document.createElement('canvas');
            canvas.width = W;
            canvas.height = H;
            var ctx = canvas.getContext('2d');

            ctx.fillStyle = '#fef8f3';
            ctx.fillRect(0, 0, W, H);

            ctx.fillStyle = '#47613b';
            ctx.fillRect(0, 0, W, 150);
            ctx.textAlign = 'center';
            ctx.fillStyle = '#ffffff';
            ctx.font = '700 46px Poppins, sans-serif';
            ctx.fillText('R-CONNECT', W / 2, 68);
            ctx.font = '500 24px Poppins, sans-serif';
            ctx.fillStyle = '#d9e6c9';
            ctx.fillText('QR Ph Payment', W / 2, 110);

            ctx.fillStyle = '#ffffff';
            pmRoundRect(ctx, 50, 190, W - 100, 640, 30);
            ctx.fill();
            ctx.strokeStyle = '#e3eabb';
            ctx.lineWidth = 2;
            pmRoundRect(ctx, 50, 190, W - 100, 640, 30);
            ctx.stroke();

            var qrSize = 430;
            ctx.drawImage(qr, (W - qrSize) / 2, 230, qrSize, qrSize);

            ctx.fillStyle = '#24513c';
            ctx.font = '700 44px Poppins, sans-serif';
            ctx.fillText('\u20B1' + PM_QR_META.fare, W / 2, 720);
            ctx.fillStyle = '#6b7a68';
            ctx.font = '500 22px Poppins, sans-serif';
            ctx.fillText('Scan with any bank or e-wallet app', W / 2, 762);
            ctx.font = '500 20px Poppins, sans-serif';
            ctx.fillText('Bus ' + PM_QR_META.bus + '  \u2022  Trip #' + PM_QR_META.trip, W / 2, 800);

            ctx.fillStyle = '#47613b';
            ctx.font = '600 20px Poppins, sans-serif';
            ctx.fillText(PM_QR_META.passenger, W / 2, 905);
            ctx.fillStyle = '#96a394';
            ctx.font = '500 18px Poppins, sans-serif';
            ctx.fillText('R-Connect Transportation Management System', W / 2, 1005);
            ctx.fillText('RPMVFMPC', W / 2, 1035);

            canvas.toBlob(function (blob) {
                if (revokeUrl) { URL.revokeObjectURL(revokeUrl); }
                if (!blob) { rawDownload(); return; }
                var url = URL.createObjectURL(blob);
                triggerDownload(url, 'R-CONNECT-QR-' + tripId + '.png');
                setTimeout(function(){ URL.revokeObjectURL(url); }, 3000);
            }, 'image/png');
        } catch (e) {
            if (revokeUrl) { URL.revokeObjectURL(revokeUrl); }
            rawDownload();
        }
    };

    qr.onerror = function () {
        if (revokeUrl) { URL.revokeObjectURL(revokeUrl); }
        rawDownload();
    };
    qr.src = src;
    }

    // Prefer the same-origin proxy so the canvas is never tainted by
    // PayMongo's remote image URL; fall back to the raw image otherwise.
    var token = (typeof pmReceiptTokenValue === 'function') ? pmReceiptTokenValue() : '';
    fetch('/passenger/paymongo/qr-image?trip_id=' + encodeURIComponent(tripId) + '&token=' + encodeURIComponent(token), { credentials: 'same-origin' })
        .then(function (r) {
            if (!r.ok) { throw new Error('qr-image'); }
            return r.blob();
        })
        .then(function (blob) {
            var objectUrl = URL.createObjectURL(blob);
            compose(objectUrl, objectUrl);
        })
        .catch(function () {
            compose(img.src, null);
        });
}
function startPaymongoCheckout() {
    var btn = document.getElementById('pmPayBtn');
    var statusEl = document.getElementById('pmStatusLine');
    if (!btn) return;
    btn.disabled = true;
    btn.innerHTML = 'Generating code...';
    if (statusEl) { statusEl.style.display = 'block'; statusEl.textContent = 'Preparing your QR Ph code...'; }
    fetch('/passenger/paymongo/checkout', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify({ trip_id: document.getElementById('pmTripId').value, token: pmReceiptTokenValue() })
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if (data.success && data.qr_image) {
            if (statusEl) { statusEl.textContent = 'Scan the code to pay.'; }
            btn.innerHTML = '<i class="bi bi-qr-code-scan"></i> Get a new code';
            pmRenderQr(data.qr_image, data.qr_expires_at);
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-qr-code-scan"></i> Pay Online';
            var payMsg = (data && data.message) ? data.message : 'Could not start payment. Please try again.';
            if (window.PassengerSession && typeof window.PassengerSession.sanitizeErrorMessage === 'function') {
                payMsg = window.PassengerSession.sanitizeErrorMessage(payMsg, 'Could not start payment. Please try again.');
            }
            if (statusEl) { statusEl.style.display = 'block'; statusEl.textContent = payMsg; }
        }
    })
    .catch(function(){
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-qr-code-scan"></i> Pay Online';
        if (statusEl) { statusEl.style.display = 'block'; statusEl.textContent = 'Network error. Please try again.'; }
    });
}
function pmRefreshStatus() {
    if (window.PassengerSession && window.PassengerSession.isInvalidated) {
        if (pmPollTimer && pmPollTimer !== 'busy') {
            clearInterval(pmPollTimer);
            pmPollTimer = null;
        }
        return;
    }
    var tripId = document.getElementById('pmTripId');
    if (!tripId || pmPollTimer === 'busy') return;
    pmPollTimer = 'busy';
    fetch('/passenger/paymongo/status', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({trip_id: tripId.value, token: pmReceiptTokenValue()})
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if (data.success) {
            var s = String(data.payment_status || '').toLowerCase();
            var history = String(data.paymongo_history || '');
            var statusLine = document.getElementById('pmStatusLine');
            if (s === 'paid') {

                if (typeof window.onTripPaid === 'function') { window.onTripPaid(); }
                if (typeof openTripReceipt === 'function') {
                    openTripReceipt(tripId.value);
                } else {
                    pmReloadContent();
                }
                pmClearConsumedTrip();
            } else if (statusLine && history.indexOf('amount_mismatch') !== -1) {
                statusLine.style.display = 'block';
                statusLine.textContent = 'Payment amount mismatch. Please contact support.';
            } else if (statusLine && (history.indexOf('not_settled') !== -1 || history.indexOf('pending:') !== -1)) {
                statusLine.style.display = 'block';
                statusLine.textContent = 'Payment is still being confirmed by PayMongo.';
            } else if (statusLine && history.indexOf('expired') !== -1) {
                statusLine.style.display = 'block';
                statusLine.textContent = 'The code has expired. Tap "Pay Online" to get a new one.';
            } else if (statusLine && history.indexOf('failed') !== -1) {
                statusLine.style.display = 'block';
                statusLine.textContent = 'Payment failed. Tap "Pay Online" to retry.';
            } else if (statusLine && history.indexOf('cancelled') !== -1) {
                statusLine.style.display = 'block';
                statusLine.textContent = 'Payment cancelled. Tap "Pay Online" to retry.';
            }
        }
    })
    .catch(function(){})
    .finally(function(){ pmPollTimer = null; });
}
function pmReconcile() {
    var tripId = document.getElementById('pmTripId');
    if (!tripId || pmPollTimer === 'busy') return;
    pmPollTimer = 'busy';
    var statusEl = document.getElementById('pmStatusLine');
    if (statusEl) { statusEl.style.display = 'block'; statusEl.textContent = 'Checking your payment with PayMongo...'; }
    fetch('/passenger/paymongo/reconcile', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify({ trip_id: tripId.value, token: pmReceiptTokenValue() })
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if (data.success) {
            var s = String(data.payment_status || '').toLowerCase();
            if (s === 'paid') {
                if (typeof window.onTripPaid === 'function') { window.onTripPaid(); }
                if (typeof openTripReceipt === 'function') {
                    openTripReceipt(tripId.value);
                } else {
                    pmReloadContent();
                }
                pmClearConsumedTrip();
                return;
            }
            if (statusEl) {
                statusEl.style.display = 'block';
                statusEl.textContent = 'Payment is not confirmed by PayMongo yet. You can try again in a moment.';
            }
        }
    })
    .catch(function(){})
    .finally(function(){ pmPollTimer = null; });
}
(function(){
    var status = document.getElementById('pmPaymentStatus');
    var history = document.getElementById('pmHistory');
    var statusLine = document.getElementById('pmStatusLine');
    if (status && history && statusLine) {
        var h = String(history.value);
        if (h.indexOf('failed') !== -1) {
            statusLine.style.display = 'block';
            statusLine.textContent = 'Payment failed. Tap "Pay Online" to retry.';
        } else if (h.indexOf('cancelled') !== -1) {
            statusLine.style.display = 'block';
            statusLine.textContent = 'Payment cancelled. Tap "Pay Online" to retry.';
        } else if (h.indexOf('expired') !== -1) {
            statusLine.style.display = 'block';
            statusLine.textContent = 'The code has expired. Tap "Pay Online" to get a new one.';
        } else if (h.indexOf('not_settled') !== -1 || h.indexOf('pending:') !== -1) {
            statusLine.style.display = 'block';
            statusLine.textContent = 'Payment is still being confirmed by PayMongo.';
        }
    }

    var storedImg = document.getElementById('pmQrImg');
    var storedExpiry = document.getElementById('pmQrExpiresAt');
    var hasStoredQr = storedImg && storedImg.getAttribute('src') && storedExpiry && storedExpiry.value;
    if (hasStoredQr) {
        pmRenderQr(storedImg.src, storedExpiry.value);
        var payBtn = document.getElementById('pmPayBtn');
        if (payBtn) { payBtn.disabled = false; payBtn.innerHTML = '<i class="bi bi-qr-code-scan"></i> Get a new code'; }
    }

    // Show the QR Ph code right away so the passenger can pay without an
    // extra tap. A still-valid code is reused by the server.
    var autoBtn = document.getElementById('pmPayBtn');
    if (!hasStoredQr && autoBtn && !autoBtn.disabled) {
        startPaymongoCheckout();
    }

    pmRefreshStatus();
    pmPollTimer = setInterval(pmRefreshStatus, 4000);
    setTimeout(pmReconcile, 3000);

    if (window.PassengerSession) {
        window.PassengerSession.onInvalidate(function() {
            if (pmPollTimer) {
                clearInterval(pmPollTimer);
                pmPollTimer = null;
            }
            if (typeof pmQrCountdownTimer !== 'undefined' && pmQrCountdownTimer) {
                clearInterval(pmQrCountdownTimer);
                pmQrCountdownTimer = null;
            }
        });
    }
})();
</script>
<?php endif; ?>
