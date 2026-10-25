<?php

if (!isset($_SESSION["passenger_id"])) {
    header("Location: /passenger/login");
    exit();
}

require_once $_SERVER["DOCUMENT_ROOT"] . "/passenger/controllers/TripDashboard/TripController.php";

$controller = new TripController();

$rejectedOnly = ($_GET["rejected_only"] ?? "") === "1";

if ($rejectedOnly) {
    $trips = $controller->getRejectedTrips($_SESSION["passenger_id"]);
} else {
    $trips = $controller->getTrips($_SESSION["passenger_id"]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
<title><?= htmlspecialchars(passenger_t('trips.page_title', 'My Trips'), ENT_QUOTES, 'UTF-8') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php include __DIR__ . "/../layout/csrf.php"; ?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

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
    max-width:800px;
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
    max-width:450px;
    background:#fcf5dd;
    border:1px solid #c6d48c;
    border-radius:15px;
    padding:5px 10px;
    text-align:center;
    font-size:11px;
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
    max-width:900px;
    margin:120px auto 40px auto;
    background:#fef8f3;
    border:2px solid #c6d48c;
    border-radius:20px;
    padding:10px 0;
}

.trip-container{
    width:95%;
    max-width:650px;
    margin:auto;
    display:flex;
    flex-direction:column;
    gap:12px;
}

.trip-card{
    background:#faf9e5;
    border:2px solid #e5e0a4;
    border-radius:20px;
    padding:14px;
    box-shadow:0 3px 8px rgba(0,0,0,0.05);
}

.status-badge{
    padding:4px 10px;
    border-radius:8px;
    font-size:11px;
    font-weight:600;
    color:#fff;
}

.status-completed{
    background:#47a64b;
}

.status-ongoing{
    background:#ff3131;
}

.details-link{
    font-size:12px;
    color:#4b794d;
    cursor:pointer;
}

.empty-state{
    text-align:center;
    padding:40px 20px;
    color:#6c757d;
}
.empty-state i{
    font-size:40px;
    color:#c6d48c;
}
</style>
</head>

<body>

<?php include __DIR__ . "/../layout/header.php"; ?>

<div class="header-section">

    <img src="/assets/images/passenger/header-banner.png" style="width:100%; object-fit:cover;">

    <div class="header-card">
        <h2 style="margin:0; font-weight:700; font-size:20px; color:#24513c;">
            <?= htmlspecialchars($rejectedOnly ? passenger_t('trips.title_unpaid', 'Unpaid Trips') : passenger_t('trips.title', 'Your Trip History'), ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p style="margin-top:5px; font-size:12px; color:#6c757d;">
            <?= htmlspecialchars($rejectedOnly ? passenger_t('trips.subtitle_unpaid', 'Trips with rejected payments — settle now') : passenger_t('trips.subtitle', 'View all your recent rides'), ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>

    <div class="info-card">
        <?= htmlspecialchars($rejectedOnly ? passenger_t('trips.hint_unpaid', 'Pay your unpaid trips below') : passenger_t('trips.hint', 'Tap any trip to see full details'), ENT_QUOTES, 'UTF-8') ?>
    </div>

    <div class="green-strip"></div>

</div>

<div class="trip-wrapper">
<div class="trip-container">

<?php if (empty($trips)): ?>

    <div class="empty-state">
        <i class="bi bi-clock-history"></i>
        <div style="margin-top:10px; font-weight:600;">
            <?= htmlspecialchars(passenger_t('trips.empty_title', 'No trip history yet'), ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div style="font-size:12px;">
            <?= htmlspecialchars(passenger_t('trips.empty_body', 'Your completed and ongoing trips will appear here.'), ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>

<?php else: ?>

<?php foreach ($trips as $trip): ?>

<?php
$status = strtolower($trip["payment_status"] ?? "");

if ($status === "paid") {
    $statusText = passenger_t('trips.status_completed', 'Completed');
    $statusClass = "status-completed";
} elseif ($status === "rejected") {
    $statusText = passenger_t('trips.status_rejected', 'Rejected');
    $statusClass = "status-ongoing";
} else {
    $statusText = passenger_t('trips.status_ongoing', 'Ongoing');
    $statusClass = "status-ongoing";
}

$origin = $trip["boarding_point"] ?? passenger_t('trips.auto_detected', 'Auto-detected');
$destination = $trip["destination"] ?? passenger_t('trips.unknown', 'Unknown');
$date = $trip["boarding_time"] ?? null;
$driverName = $trip["driver_name"] ?? null;
$companionCount = (int) ($trip["companion_count"] ?? 0);
$tripFare = (float) ($trip["fare"] ?? 0) + (float) ($trip["companions_fare"] ?? 0);
$partyBreakdown = $trip['breakdown'] ?? null;
?>

<div class="trip-card">

    <div style="display:flex; align-items:center;">
        <span style="font-size:12px; color:#6c757d;">
            <?php echo $date ? date("m/d/Y", strtotime($date)) : "N/A"; ?>
        </span>

        <span class="status-badge <?php echo $statusClass; ?>" style="margin-left:auto;">
            <?php echo $statusText; ?>
        </span>
    </div>

    <div style="margin:6px 0; font-weight:600;">
        <?php echo htmlspecialchars($origin); ?> →
        <?php echo htmlspecialchars($destination); ?>
    </div>

    <?php if ($driverName): ?>
    <div style="font-size:12px; color:#6c757d; margin-bottom:6px;">
        <?= htmlspecialchars(passenger_t('trips.driver', 'Driver'), ENT_QUOTES, 'UTF-8') ?>: <?php echo htmlspecialchars($driverName); ?>
    </div>
    <?php endif; ?>

<?php if ($companionCount > 0 && $partyBreakdown): ?>
    <?php include __DIR__ . '/party_breakdown.php'; ?>
<?php endif; ?>

    <div style="display:flex; justify-content:space-between; align-items:center;">
        <span style="font-weight:700; color:#24513c;">
            <?php echo $companionCount > 0 && !$partyBreakdown ? htmlspecialchars(passenger_t('home.fare_unavailable', 'Fare unavailable'), ENT_QUOTES, 'UTF-8') : '₱' . number_format($tripFare, 2); ?>
<?php if ($companionCount > 0): ?>
            <span style="display:block;font-size:10px;font-weight:600;color:#7b8a74;">
                <?= htmlspecialchars(passenger_t('trips.includes_companions', 'Includes :count companion(s)', ['count' => $companionCount]), ENT_QUOTES, 'UTF-8') ?>
            </span>
<?php endif; ?>
        </span>

<?php if ($status === "rejected"): ?>
    <a href="javascript:void(0);" onclick="openPaymentModal(<?php echo $trip["id"]; ?>)"
       style="background:#e74c3c;color:#fff;padding:5px 18px;border-radius:20px;text-decoration:none;font-size:12px;font-weight:600;transition:.2s;"
       onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
       <?= htmlspecialchars(passenger_t('trips.pay_now', 'Pay Now'), ENT_QUOTES, 'UTF-8') ?>
    </a>
<?php else: ?>
    <a class="details-link" onclick="openTripModal(<?php echo $trip["id"]; ?>)">
        <?= htmlspecialchars(passenger_t('trips.tap_details', 'Tap for details →'), ENT_QUOTES, 'UTF-8') ?>
    </a>
<?php endif; ?>
    </div>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>
</div>

<?php include __DIR__ . "/../layout/navbar.php"; ?>

<div class="modal fade" id="tripModal" tabindex="-1">

    <div
        class="modal-dialog modal-dialog-centered"
        style="
            width:95vw;
            max-width:95vw;
            margin:auto;
        "
    >

        <div
            class="modal-content border-0 shadow-lg"
            style="
                height:75vh;
                border-radius:20px;
                overflow:hidden;
                background:#faf9e5;
            "
        >

            <div
                class="modal-header border-0"
                style="
                    background:
                    linear-gradient(
                        135deg,
                        #47613b,
                        #5e7a4f
                    );
                    color:#fff;
                    padding:10px 14px;
                "
            >

                <div>

                    <h6
                        class="modal-title mb-0"
                        style="
                            font-weight:600;
                        "
                    >
                        <?= htmlspecialchars(passenger_t('trips.modal_title', 'Trip Details'), ENT_QUOTES, 'UTF-8') ?>
                    </h6>

                    <small
                        style="
                            opacity:.8;
                            font-size:11px;
                        "
                    >
                        <?= htmlspecialchars(passenger_t('trips.modal_sub', 'Passenger Receipt'), ENT_QUOTES, 'UTF-8') ?>
                    </small>

                </div>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                ></button>

            </div>

            <div
                class="modal-body p-0"
                id="tripModalBody"
                style="
                    height:100%;
                    overflow-y:auto;
                    background:
                    linear-gradient(
                        to bottom,
                        #faf9e5,
                        #ffffff
                    );
                "
            >

                <div
                    class="d-flex align-items-center justify-content-center"
                    style="
                        width:100%;
                        height:100%;
                    "
                >

                    <div class="text-center">

                        <div
                            class="spinner-border spinner-border-sm text-success"
                            role="status"
                        ></div>

                        <div
                            class="mt-2 text-muted"
                            style="
                                font-size:12px;
                            "
                        >
                            <?= htmlspecialchars(passenger_t('trips.loading', 'Loading trip details...'), ENT_QUOTES, 'UTF-8') ?>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const TRIPS_I18N = {
    loadingShort: <?= json_encode(passenger_t('trips.loading_short', 'Loading...'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    failed: <?= json_encode(passenger_t('trips.failed', 'Failed to load trip details'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    failedPayment: <?= json_encode(passenger_t('trips.failed_payment', 'Failed to load payment details'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};

async function openTripModal(id){

    const modalEl = document.getElementById('tripModal');
    const modal = new bootstrap.Modal(modalEl);

    const body = document.getElementById("tripModalBody");

    body.innerHTML = TRIPS_I18N.loadingShort;
    modal.show();

    try{

        const res = await fetch('/passenger/trip/modal', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id})
        });

        if(!res.ok){
            throw new Error('Failed to load');
        }

        const text = await res.text();

        console.log("RESPONSE:", text);

        body.innerHTML = text;

        body.querySelectorAll('script').forEach(function(s){
            const ns = document.createElement('script');
            ns.textContent = s.textContent;
            s.parentNode.replaceChild(ns, s);
        });

    }catch(e){
        console.error(e);

        if (window.PassengerSession && window.PassengerSession.isInvalidated) {
            return;
        }

        body.innerHTML = `
            <div style="color:#7b8a74; text-align:center; padding:30px 15px;">
                ${TRIPS_I18N.failed}
            </div>
        `;
    }
}

async function openPaymentModal(id){

    const modalEl = document.getElementById('tripModal');
    const modal = new bootstrap.Modal(modalEl);

    const body = document.getElementById("tripModalBody");

    body.innerHTML = TRIPS_I18N.loadingShort;
    modal.show();

    try{

        const res = await fetch('/passenger/paymongo/modal', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id})
        });

        if(!res.ok){
            throw new Error('Failed to load');
        }

        const text = await res.text();

        body.innerHTML = text;

        body.querySelectorAll('script').forEach(function(s){
            const ns = document.createElement('script');
            ns.textContent = s.textContent;
            s.parentNode.replaceChild(ns, s);
        });

    }catch(e){
        console.error(e);

        if (window.PassengerSession && window.PassengerSession.isInvalidated) {
            return;
        }

        body.innerHTML = `
            <div style="color:#7b8a74; text-align:center; padding:30px 15px;">
                ${TRIPS_I18N.failedPayment}
            </div>
        `;
    }
}
</script>
</body>
</html>
