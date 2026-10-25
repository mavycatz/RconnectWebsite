<?php
require __DIR__ . "/checkAccess.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/app/Core/Database.php";

use App\Core\Database;

$token = filter_input(INPUT_GET, "token", FILTER_SANITIZE_STRING);

if (!$token) {
    header("HTTP/1.1 400 Bad Request");
    echo "Invalid request: token missing.";
    exit();
}

$conn = Database::get();

$stmt = $conn->prepare("
    SELECT 
        bs.bus_id, 
        bs.seat_number,
        bs.for_pwd,
        bs.for_senior,
        bs.for_pregnant,
        bst.token
    FROM bus_seat_tokens bst
    JOIN bus_seats bs ON bst.bus_seat_id = bs.id
    WHERE bst.token = :token
    LIMIT 1
");
$stmt->execute(["token" => $token]);
$seatData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seatData) {
    header("HTTP/1.1 404 Not Found");
    echo "Invalid or expired token.";
    exit();
}

$busId = $seatData["bus_id"];
$seatNumber = $seatData["seat_number"];

$isReserved = false;
$reservedType = "";

$seatValue = trim(strtolower($seatNumber));

if ($seatValue === "pwd") {
    $isReserved = true;
    $reservedType = "PWD";
} elseif ($seatValue === "senior") {
    $isReserved = true;
    $reservedType = "Senior Citizen";
} elseif ($seatValue === "pregnant") {
    $isReserved = true;
    $reservedType = "Pregnant Woman";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
    <title>Passenger Logging</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include __DIR__ . "/layout/csrf.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/passenger/passengerlog.css">
    <link rel="stylesheet" href="/public/login/custom-alert.css">
</head>
<body>

<?php include __DIR__ . "/header.php"; ?>

<div style="position: relative; width: 100%; margin: 0; padding: 0;">
    <img src="/assets/images/passenger/header-banner.png" alt="Header Image" style="width:100%; display:block; object-fit:cover;">

    <div style="
        position: absolute;
        top: 140%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 100%;
        max-width: 800px;
        background: #fef8f3;
        border-radius: 15px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        padding: 25px 20px;
    ">
        <h2 style="margin:0; font-family:'Poppins', sans-serif; font-weight:700; font-size:26px;">Passenger Logging</h2>
        <p style="margin-top:10px; font-family:'Poppins', sans-serif; font-size:12px; color:#6c757d;">
            Please fill out correctly. Ang maling impormasyon ay pananagutan ng pasahero.
        </p>
    </div>

    <div style="
        position: absolute;
        top: 180%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 55%;
        max-width: 450px;
        background: #fcf5dd;
        border: 1px solid #c6d48c;  
        border-radius: 15px;
        padding: 5px 2px;
        display: flex;
        align-items: center;
        gap: 20px;
        z-index: 2;
    ">
        <div style="
            flex-shrink: 0;
            width: 35px;
            height: 35px;
            background: linear-gradient(90deg, #306449, #b3be5b);
            border-radius: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        ">
            <img src="/assets/images/passenger/passenger-icon.png" alt="Passenger Icon" style="height:25px;">
        </div>

        <div style="flex-grow:1; display:flex; flex-direction:column; justify-content:center;">
            <h3 style="margin:0; font-family:'Poppins', sans-serif; font-weight:600; font-size:14px; color:#000;">
                Seat Number: <?= htmlspecialchars($seatNumber) ?>
            </h3>
        </div>
    </div>

    <div style="
        position: absolute;
        top: 180%;
        left: 50%;
        transform: translateX(-50%);
        width: 100%;
        background: #e3eabb;
        text-align: center;
        padding: 30px 0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        z-index: 1;
    "></div>
</div>

<div style="
    position: relative;
    width: 100%;
    margin-top: 150px;
    display: flex;
    justify-content: center;
    background: #fef8f3;
    border: 2px solid #c6d48c;
    border-radius: 20px;
    padding: 10px 0;
    z-index: 10;
">
    <div style="
        width: 95%;
        max-width: 650px;
        background: #fef8f3;
        border-radius: 20px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        padding: 30px 20px;
    ">
        <form method="POST" action="/passenger/logging" enctype="multipart/form-data">

            <input type="hidden" name="bus_id" value="<?= htmlspecialchars($busId) ?>">
            <input type="hidden" name="seat_number" value="<?= htmlspecialchars($seatNumber) ?>">

            <div class="mb-3">
                <label for="passenger-name" class="form-label">Name:</label>
                <input type="text" id="passenger-name" name="name" class="form-control" placeholder="Paki-lagay ang iyong pangalan" required autocomplete="name">
            </div>

            <div class="mb-3">
                <label for="boarding-point" class="form-label">Boarding Point:</label>
                <input type="text" id="boarding-point" name="boarding_point" list="boarding-list" class="form-control" autocomplete="off" placeholder="Saan sumakay?">
                <datalist id="boarding-list"></datalist>
            </div>

            <div class="mb-3">
                <label for="destination-point" class="form-label">Destination:</label>
                <input type="text" id="destination-point" name="destination" list="destination-list" class="form-control" autocomplete="off" placeholder="Saan papunta?">
                <datalist id="destination-list"></datalist>
            </div>

            <div class="mb-3">
                <label class="form-label">Passenger Type:</label>
                <span style="display:block; color:#6c757d; font-size:12px; margin-top:3px;">Anong uri kang pasahero?</span>

                <div class="passenger-type-container" style="display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin-top:10px;">
                    <?php
                    $types = ["Regular", "Student", "Senior", "Teacher", "Nurse"];
                    foreach ($types as $type): ?>
                        <input type="radio" name="passenger_type" value="<?= $type ?>" id="<?= strtolower(
    $type,
) ?>" hidden <?= $type === "Regular" ? "checked" : "" ?>>
                        <label for="<?= strtolower($type) ?>" class="passenger-type-label" style="
                            text-align:center;
                            padding:12px 0;
                            border-radius:50px;
                            background-color:<?= $type === "Regular" ? "#4b794d" : "#fef8f3" ?>;
                            color:<?= $type === "Regular" ? "#fff" : "#4b794d" ?>;
                            border:2px solid #4b794d;
                            cursor:pointer;
                            font-family:'Poppins', sans-serif;
                            font-weight:600;
                            transition: all 0.3s ease;
                        ">
                            <?= $type ?>
                        </label>
                    <?php endforeach;
                    ?>
                </div>

                <div id="student-number-container" style="margin-top:15px; display:none;">
                    <div style="border:2px solid #ededed; border-radius:15px; padding:15px; background-color:#fef8f3;">
                        <label for="student-number" class="form-label">Student Number:</label>
                        <input type="text" id="student-number" name="student_id" class="form-control" placeholder="Ilagay ang iyong student number" autocomplete="student-number">
                    </div>
                </div>

                <div id="license-container" style="margin-top:15px; display:none;">
                    <div style="border:2px solid #ededed; border-radius:15px; padding:15px; background-color:#fef8f3;">
                        <label for="license-number" class="form-label">License / ID Number:</label>
                        <input type="text" id="license-number" name="license_number" class="form-control" placeholder="Ilagay ang iyong License o ID Number" autocomplete="off">
                    </div>
                </div>
            </div>

<div id="id-image-container" style="margin-top:15px; display:none;">
    <div style="border:2px solid #ededed; border-radius:15px; padding:15px; background-color:#fef8f3;">
        <label for="id-image" class="form-label">Upload ID (Student / Teacher / Nurse / Senior):</label>

        <input type="file" 
               id="id-image" 
               name="id_image" 
               class="form-control" 
               accept="image/*">

        <small style="color:#6c757d; font-size:11px;">
            Mag-upload ng malinaw na larawan ng iyong ID
        </small>
    </div>
</div>

            <div class="mb-3" id="fare-container" style="display:none;">
                <label for="calculated-fare" class="form-label">Calculated Fare:</label>
                <input type="text" id="calculated-fare" class="form-control" placeholder="₱0.00" readonly>
            </div>

            <div class="mb-3">
                <label class="form-label">Mode of Payment:</label>
                <span style="display:block; color:#6c757d; font-size:12px; margin-top:0px;">
                    Pumili ng paraan ng pagbabayad
                </span>
                <div style="display:flex; gap:15px; margin-top:10px; justify-content:center; flex-wrap:wrap;">
                    <?php
                    $payments = [
                        "Cash" => "Sa driver magbabayad",
                        "Gcash" => "Gcash Payment",
                        "Hybrid" => "Halo ang pagbabayad (Gcash + Cash)",
                    ];
                    foreach ($payments as $pay => $desc): ?>
                        <div style="text-align:center; flex:1 1 0; position:relative;">
                            <input type="radio" name="payment_mode" value="<?= $pay ?>" id="<?= strtolower(
    $pay,
) ?>" style="position:absolute; opacity:0;" required autocomplete="off">
                            <label for="<?= strtolower($pay) ?>" style="
                                display:block;
                                padding:12px 0;
                                border-radius:50px;
                                background-color:<?= $pay === "Cash"
                                    ? "#4b794d"
                                    : ($pay === "Hybrid"
                                        ? "#fff4d3"
                                        : "transparent") ?>;
                                color:<?= $pay === "Cash" ? "#fff" : "#000" ?>;
                                border:<?= $pay === "Gcash"
                                    ? "2px solid #4b794d"
                                    : ($pay === "Hybrid"
                                        ? "2px solid #b3be5b"
                                        : "2px solid #ccc") ?>;
                                cursor:pointer;
                                font-family:'Poppins', sans-serif;
                                font-weight:600;
                                transition: all 0.3s ease;
                            "><?= $pay ?></label>
                            <small style="display:block; color:#6c757d; font-size:11px; margin-top:3px;">
                                <?= $desc ?>
                            </small>
                        </div>
                    <?php endforeach;
                    ?>
                </div>

                <div id="hybrid-inputs" style="display:none; margin-top:10px; gap:10px; justify-content:center; flex-wrap:wrap;">
                    <input type="number" name="hybrid_cash" placeholder="For Cash:" min="0" style="padding:8px; border-radius:5px; border:1px solid #ccc; width:120px;">
                    <input type="number" name="hybrid_gcash" placeholder="For Gcash:" min="0" style="padding:8px; border-radius:5px; border:1px solid #ccc; width:120px;">
                </div>
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input green-check" id="agree" name="agree" required autocomplete="off">
                <label class="form-check-label" for="agree">I Agree / Sumasang-ayon</label>
            </div>

            <p style="font-size:12px; line-height:1.4; text-align: justify; text-justify: inter-word; margin-bottom: 10px; max-width: 500px; color:#6c757d;">
                Sa pagpuno ng form na ito, pinatutunayan ng pasahero na ang lahat ng impormasyong ibinigay ay tama at totoo. Ang datos ay poprotektahan alinsunod sa Republic Act No. 10173 (Data Privacy Act of 2012).
            </p>

            <button type="button" class="btn btn-primary w-100" id="confirmSubmit">
                Proceed
            </button>

        </form>
    </div>
</div>

<?php include __DIR__ . "/passengerModal.php"; ?>

<script>
    window.faresJson = <?= isset($faresJson) ? $faresJson : "[]" ?>;
</script>

<script type="module" src="/assets/js/passenger/passengerlog.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    <?php if ($isReserved): ?>
    showAlert('warning', 'Reserved Seat', 'This seat is reserved for <?= htmlspecialchars(
        $reservedType,
    ) ?>. Please choose another seat, or proceed if you qualify.');
    <?php endif; ?>

    populateStopDatalists();

});

async function populateStopDatalists(){

    try{

        const res =
            await fetch(
                '/passenger/api/active-trips'
            );

        const data =
            await res.json();

        let stops = [];

        if(
            data.success &&
            Array.isArray(data.trips) &&
            data.trips.length
        ){

            const tripId =
                data.trips[0].id;

            const destRes =
                await fetch(
                    '/passenger/api/destinations?trip_no=' +
                    tripId
                );

            const destData =
                await destRes.json();

            if(
                destData.success &&
                Array.isArray(destData.destinations)
            ){

                stops =
                    destData.destinations;

            }

        }

        const destList =
            document.getElementById(
                "destination-list"
            );

        const boardList =
            document.getElementById(
                "boarding-list"
            );

        if(stops.length){

            const fragment1 =
                document.createDocumentFragment();

            const fragment2 =
                document.createDocumentFragment();

            stops.forEach(
                (s)=>{

                    const opt1 =
                        document.createElement(
                            "option"
                        );

                    opt1.value =
                        s.destination;

                    fragment1.appendChild(
                        opt1
                    );

                    const opt2 =
                        opt1.cloneNode(
                            true
                        );

                    fragment2.appendChild(
                        opt2
                    );

                }
            );

            destList.appendChild(
                fragment1
            );

            boardList.appendChild(
                fragment2
            );

        }

    }catch(error){

        console.error(
            "Failed to load stops:",
            error
        );

    }

}
</script>

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
</body>
</html>
