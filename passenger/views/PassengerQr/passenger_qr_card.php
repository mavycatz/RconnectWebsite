<?php

if (!function_exists("passengerQrIdentity")) {
    function passengerQrIdentity($passenger)
    {
        $code = !empty($passenger["passenger_code"]) ? $passenger["passenger_code"] : "";
        $type = "Regular";
        $raw = trim((string) ($passenger["discount_type"] ?? ($passenger["passenger_type"] ?? "")));
        // Only a verified classification is shown/priced as discounted. When
        // the caller supplies the verification state and it is not yet
        // verified/approved, the card presents the passenger as Regular.
        if (array_key_exists("discount_status", $passenger)
            && !in_array(strtolower(trim((string) $passenger["discount_status"])), ["verified", "approved"], true)
            && in_array(strtolower(str_replace(["_", "-"], " ", $raw)), ["student", "senior", "pwd", "senior citizen", "person with disability"], true)) {
            $raw = "";
        }
        if ($raw !== "" && strtolower($raw) !== "none" && strtolower($raw) !== "regular") {
            $type = ucwords(str_replace(["_", "-"], " ", strtolower($raw)));
        }
        return [
            "v" => 1,
            "app" => "rconnect-passenger",
            "passenger_code" => $code,
            "full_name" => (string) ($passenger["full_name"] ?? ""),
            "passenger_type" => $type,
        ];
    }
}

$passengerQrContainerId = $passengerQrContainerId ?? "passengerQrCanvas";
$passengerQrShowActions = $passengerQrShowActions ?? true;
$passenger = $passenger ?? [];

$passengerQrIdentity = passengerQrIdentity($passenger);
$passengerQrPayload = json_encode($passengerQrIdentity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$passengerQrName = $passengerQrIdentity["full_name"];
$passengerQrCode = $passengerQrIdentity["passenger_code"];
$passengerQrType = $passengerQrIdentity["passenger_type"];
?>

<style>
.passenger-qr-card{
    background:#fff;
    border:1.5px solid #c6d48c;
    border-radius:18px;
    padding:18px 16px;
    text-align:center;
    max-width:320px;
    margin:0 auto;
}
.passenger-qr-code{
    width:min(210px,100%);
    aspect-ratio:1/1;
    margin:0 auto 12px;
    padding:20px;
    box-sizing:border-box;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#fff;
}
/* qrcodejs renders both a canvas and a fallback <img> and shows exactly one
   through inline styles. Never force `display` with !important here, or the
   hidden element becomes visible, overflows the box, and clips the QR. */
.passenger-qr-code canvas,
.passenger-qr-code img{
    width:100%;
    height:100%;
    max-width:100%;
    max-height:100%;
    aspect-ratio:1/1;
    object-fit:contain;
    margin:0 auto;
}
.passenger-qr-name{
    font-family:'Poppins',sans-serif;
    font-size:15px;
    font-weight:700;
    color:#24513c;
    line-height:1.3;
    overflow-wrap:anywhere;
    word-break:break-word;
}
.passenger-qr-code-text{
    font-family:'Poppins',sans-serif;
    font-size:12px;
    font-weight:600;
    color:#7b8a74;
    margin-top:4px;
}
.passenger-qr-type{
    display:inline-block;
    font-family:'Poppins',sans-serif;
    font-size:11px;
    font-weight:600;
    color:#2f6b43;
    background:#eef4e6;
    border:1px solid #d5e4c6;
    border-radius:999px;
    padding:3px 14px;
    margin-top:10px;
}
.passenger-qr-actions{
    display:flex;
    gap:10px;
    margin-top:16px;
}
.passenger-qr-btn{
    flex:1 1 0%;
    min-width:0;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    border-radius:30px;
    font-family:'Poppins',sans-serif;
    font-size:13px;
    font-weight:600;
    padding:11px 6px;
    cursor:pointer;
    touch-action:manipulation;
    transition:.2s;
}
.passenger-qr-btn-primary{
    border:none;
    color:#fff;
    background:linear-gradient(135deg,#47613b,#5d8550);
    box-shadow:0 4px 12px rgba(71,97,59,.25);
}
.passenger-qr-btn-secondary{
    border:1.5px solid #cdd9b5;
    color:#24513c;
    background:#fff;
}
</style>

<div class="passenger-qr-card">
    <div
        id="<?= htmlspecialchars($passengerQrContainerId) ?>"
        class="passenger-qr-code"
        data-payload="<?= htmlspecialchars($passengerQrPayload, ENT_QUOTES) ?>"
        data-name="<?= htmlspecialchars($passengerQrName, ENT_QUOTES) ?>"
        data-code="<?= htmlspecialchars($passengerQrCode, ENT_QUOTES) ?>"
        data-type="<?= htmlspecialchars($passengerQrType, ENT_QUOTES) ?>"
    ></div>

    <div class="passenger-qr-name"><?= htmlspecialchars($passengerQrName) ?></div>
    <div class="passenger-qr-code-text"><?= htmlspecialchars($passengerQrCode !== '' ? $passengerQrCode : passenger_t('qr.not_assigned', 'Not assigned')) ?></div>
    <div class="passenger-qr-type"><?= htmlspecialchars($passengerQrType) ?></div>

    <?php if ($passengerQrShowActions): ?>
    <div class="passenger-qr-actions">
        <button type="button" class="passenger-qr-btn passenger-qr-btn-secondary" data-action="print">
            <i class="bi bi-printer"></i> <?= htmlspecialchars(passenger_t('qr.print', 'Print QR'), ENT_QUOTES, 'UTF-8') ?>
        </button>
        <button type="button" class="passenger-qr-btn passenger-qr-btn-primary" data-action="download">
            <i class="bi bi-download"></i> <?= htmlspecialchars(passenger_t('qr.download', 'Download QR'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>
    <?php endif; ?>
</div>
