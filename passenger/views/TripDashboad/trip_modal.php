<?php

if (!isset($_SESSION["passenger_id"])) {
    echo "<div style='color:red;'>Session expired. Please login again.</div>";
    exit();
}

require_once $_SERVER["DOCUMENT_ROOT"] . "/app/Core/Database.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/passenger/services/Companion/CompanionService.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/passenger/services/Companion/RegularPartyService.php";

use App\Core\Database;

$conn = Database::get();

$tripModalInput = json_decode((string) file_get_contents("php://input"), true);
if (!is_array($tripModalInput)) {
    $tripModalInput = [];
}
$id = $tripModalInput["id"] ?? ($_GET["id"] ?? null);

if (!$id) {
    echo "Invalid trip ID";
    exit();
}

$stmt = $conn->prepare("
    SELECT
        pt.*,
        p.full_name,
        p.discount_type,
        p.registration_type,
        bt.route_name,
        d.full_name AS driver_name
    FROM passenger_trips pt
    JOIN passengers p
        ON pt.passenger_id = p.id
    LEFT JOIN bus_trips bt
        ON pt.trip_no = bt.id
    LEFT JOIN drivers d
        ON pt.driver_id = d.driver_id
    WHERE pt.id = ?
    AND pt.passenger_id = ?
");

$stmt->execute([$id, $_SESSION["passenger_id"]]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    echo "Trip not found";
    exit();
}

$isOneTime = strtolower((string) ($trip['registration_type'] ?? '')) === 'one_time';
$companions = $isOneTime ? CompanionService::companionRows($conn, (int) $trip['id']) : RegularPartyService::travellerRows($conn, (int) $trip['id']);
$partyBreakdown = $isOneTime ? CompanionService::partyBreakdown($conn, $trip, $companions) : RegularPartyService::storedQuote($conn, $trip, $companions)['breakdown'];
$partyCompanions = count($companions);

$name = htmlspecialchars($trip["full_name"] ?? "Passenger");
$bus = htmlspecialchars($trip["bus_no"] ?? "—");
$tripNo = htmlspecialchars($trip["trip_no"] ?? "—");
$driverName = htmlspecialchars($trip["driver_name"] ?? "—");
$routeName = htmlspecialchars($trip["route_name"] ?? "—");
$boarding = htmlspecialchars($trip["boarding_point"] ?? "—");
$destination = htmlspecialchars($trip["destination"] ?? "—");

$fare = $partyCompanions > 0 && !$partyBreakdown
    ? htmlspecialchars(passenger_t('home.fare_unavailable', 'Fare unavailable'), ENT_QUOTES, 'UTF-8')
    : '₱' . number_format((float) ($partyBreakdown['party_total'] ?? $trip['fare'] ?? 0), 2);
$status = ucfirst($trip["payment_status"] ?? "—");
$methodRaw = strtolower((string) ($trip["payment_method"] ?? "cash"));
$method = match ($methodRaw) {
    "gcash" => "Online Payment",
    "hybrid" => "Online Payment + Cash",
    "cash" => "Cash",
    default => ucfirst($methodRaw),
};

$cash = number_format($trip["cash_amount"] ?? 0, 2);
$gcash = number_format($trip["gcash_amount"] ?? 0, 2);

$boardedAt = $trip["boarding_time"] ?? "—";
$droppedAt = $trip["dropoff_time"] ?? "—";
?>

<style>

@media print {
    body * { visibility: hidden; }
    #tripModalRoot, #tripModalRoot * { visibility: visible; }
    #tripModalRoot {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        max-width: 100%;
        height: auto;
        max-height: none;
        box-shadow: none;
        border: none;
        border-radius: 0;
    }
}
</style>

<div class="container-fluid d-flex justify-content-center px-2 py-0 mt-2">

    <div
        class="card border-0 shadow-sm"
        id="tripModalRoot"
        style="
            width:95%;
            max-width:700px;
            max-height:78vh;
            border-radius:16px;
            overflow:hidden;
            display:flex;
            flex-direction:column;
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
                <?= htmlspecialchars(passenger_t('trips.receipt_title', 'Trip Receipt'), ENT_QUOTES, 'UTF-8') ?>
            </h6>

            <small class="opacity-75">
                <?= htmlspecialchars(passenger_t('trips.reference_no', 'Reference #'), ENT_QUOTES, 'UTF-8') ?><?php echo $tripNo; ?>
            </small>

        </div>

        <div
            class="card-body overflow-auto px-3 py-2"
            style="min-height:0;"
        >

            <div class="mb-3">

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
                    <span><?= htmlspecialchars(passenger_t('trips.driver', 'Driver'), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?php echo $driverName; ?></strong>
                </div>

                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span><?= htmlspecialchars(passenger_t('trips.route', 'Route'), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?php echo $routeName; ?></strong>
                </div>

            </div>

            <div class="mb-3">

                <div class="small text-muted mb-1">
                    <?= htmlspecialchars(passenger_t('trips.trip_details_section', 'TRIP DETAILS'), ENT_QUOTES, 'UTF-8') ?>
                </div>

                <div class="d-flex justify-content-between py-1 border-bottom" style="gap:12px;">
                    <span class="flex-shrink-0"><?= htmlspecialchars(passenger_t('trips.from', 'From'), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong class="flex-grow-1 text-end" style="min-width:0;overflow-wrap:anywhere;word-break:break-word;"><?php echo $boarding; ?></strong>
                </div>

                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span><?= htmlspecialchars(passenger_t('trips.to', 'To'), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?php echo $destination; ?></strong>
                </div>

                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span><?= htmlspecialchars(passenger_t('trips.boarded', 'Boarded'), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?php echo $boardedAt; ?></strong>
                </div>

                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span><?= htmlspecialchars(passenger_t('trips.dropped', 'Dropped'), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?php echo $droppedAt; ?></strong>
                </div>

            </div>

            <div class="mb-2">

                <div class="small text-muted mb-1">
                    <?= htmlspecialchars(passenger_t('trips.payment_section', 'PAYMENT'), ENT_QUOTES, 'UTF-8') ?>
                </div>

                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span><?= htmlspecialchars(passenger_t('trips.method', 'Method'), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?php echo htmlspecialchars($method); ?></strong>
                </div>

                <?php if ($cash > 0): ?>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span><?= htmlspecialchars(passenger_t('home.cash', 'Cash'), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong>₱<?php echo $cash; ?></strong>
                </div>
                <?php endif; ?>

                <?php if ($gcash > 0): ?>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span><?= htmlspecialchars(passenger_t('home.online_payment', 'Online Payment'), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong>₱<?php echo $gcash; ?></strong>
                </div>
                <?php endif; ?>

            </div>

<?php if ($partyCompanions > 0): ?>
            <?php include __DIR__ . '/party_breakdown.php'; ?>
<?php endif; ?>
        </div>

        <div
            class="px-3 py-2"
            style="
                background:#faf9e5;
                border-top:1px solid #ece7c6;
                flex-shrink:0;
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
                        <?php echo $fare; ?>
                    </div>

                </div>

                <span
                    class="badge rounded-pill px-2 py-1"
                    style="
                        background:#47613b;
                        font-size:11px;
                    "
                >
                    <?php
                    $statusKey = strtolower((string) $status);
                    $statusLabel = $statusKey === 'paid'
                        ? passenger_t('trips.status_completed', 'Completed')
                        : ($statusKey === 'rejected'
                            ? passenger_t('trips.status_rejected', 'Rejected')
                            : passenger_t('trips.status_ongoing', 'Ongoing'));
                    echo htmlspecialchars(strtoupper($statusLabel), ENT_QUOTES, 'UTF-8');
                    ?>
                </span>

            </div>

<?php if (strtolower($trip["payment_status"] ?? "") === "paid"): ?>
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
                <i class="bi bi-printer"></i> <?= htmlspecialchars(passenger_t('trips.print_receipt', 'Print Receipt'), ENT_QUOTES, 'UTF-8') ?>
            </button>

            <button
                onclick="if(window.confirmTripReceipt){window.confirmTripReceipt();}"
                style="
                    width:100%;
                    margin-top:8px;
                    padding:10px;
                    border:none;
                    border-radius:30px;
                    font-weight:700;
                    font-size:13px;
                    color:#fff;
                    background:#47613b;
                    cursor:pointer;
                "
            >
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars(passenger_t('trips.confirm_scan_again', 'Confirm & Scan Again'), ENT_QUOTES, 'UTF-8') ?>
            </button>
<?php endif; ?>

        </div>

    </div>

</div>

<script>
function printReceipt(){
    var root = document.getElementById('tripModalRoot') || document.getElementById('pmModalRoot');
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
