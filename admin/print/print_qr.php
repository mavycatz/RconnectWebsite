<?php
require_once __DIR__ . '/../../app/Models/QR_Bus_Passenger.php';

$bus_id = $_GET['bus_id'] ?? '';
if (!$bus_id) die("Bus ID missing");

$model = new QR_Bus_Passenger();
$passengers = $model->getPassengersByBus($bus_id);
$totalSeats = $_GET['totalSeats'] ?? count($passengers);
$standingCapacity = $_GET['standingCapacity'] ?? 10;
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Print QR Codes - <?= htmlspecialchars($bus_id) ?></title>
<style>
    body { font-family: sans-serif; background: #fdfcf6; margin: 0; padding: 0; }
    .page { width: 100%; height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; page-break-after: always; }
    .qr-box { text-align: center; padding: 20px; border: 2px solid #a3d9a5; border-radius: 12px; background: #fff; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); width: 250px; }
    .qr-box p { margin: 10px 0 0; font-weight: 600; color: #2f4f2f; }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>

<?php
for ($seat = 2; $seat <= $totalSeats-3; $seat++): ?>
<div class="page">
    <div class="qr-box" id="seat-<?= $seat ?>"></div>
<p>Seat <?= $seat ?> - Bus <?= htmlspecialchars($bus_id) ?></p>
</div>
<?php endfor; ?>

<?php
$specialSeats = [
['seat' => 'PWD', 'label' => 'PWD Seat'],
['seat' => 'Senior', 'label' => 'Senior Seat'],
['seat' => 'Pregnant', 'label' => 'Pregnant Seat']
];
foreach ($specialSeats as $s): ?>
<div class="page">
    <div class="qr-box" id="<?= $s['seat'] ?>"></div>
<p><?= $s['label'] ?> - Bus <?= htmlspecialchars($bus_id) ?></p>
</div>
<?php endforeach; ?>

<div class="page">
    <div class="qr-box" id="Standing"></div>
    <p>Standing Seat - Bus <?= htmlspecialchars($bus_id) ?></p>
</div>

<script>
function generateQR(id, text) {
  new QRCode(document.getElementById(id), { text, width:200, height:200 });
}

// Normal seats
<?php for ($seat = 2; $seat <= $totalSeats-3; $seat++): ?>
generateQR('seat-<?= $seat ?>', 'https://yourdomain.com/booking.php?bus_id=<?= $bus_id ?>&seat_number=<?= $seat ?>');
<?php endfor; ?>

// Special seats
<?php foreach ($specialSeats as $s): ?>
generateQR('<?= $s['seat'] ?>', 'https://yourdomain.com/booking.php?bus_id=<?= $bus_id ?>&seat_number=<?= $s['seat'] ?>');
<?php endforeach; ?>

// Standing seat
generateQR('Standing', 'https://yourdomain.com/booking.php?bus_id=<?= $bus_id ?>&seat_number=Standing');
</script>

</body>
</html>
