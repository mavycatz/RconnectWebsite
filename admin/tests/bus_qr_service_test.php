<?php declare(strict_types=1);

/**
 * BusQrService checks on SQLite: idempotent schema, one active credential per
 * bus, stable tokens, replacement/revocation history, and token uniqueness.
 */

require_once __DIR__ . '/../services/Bus/BusQrService.php';

use Admin\Services\Bus\BusQrService;

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function qrDb(): PDO
{
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $db;
}

$db = qrDb();
BusQrService::ensureSchema($db);
BusQrService::ensureSchema($db);
check(true, 'Schema creation is idempotent');

$service = new BusQrService($db);
$token = $service->generateToken();
check(strlen($token) === 32 && ctype_xdigit($token), 'Tokens are 32 hex characters');

$payload = BusQrService::buildPayloadUrl('https://rconnect.example/', 'BUS-004', $token);
check(strpos($payload, '/passenger/scan.php?bus=BUS-004&t=') !== false, 'Payload keeps the compatible bus deep link');
check(strpos($payload, $token) !== false, 'Payload carries the opaque token');
check(strpos($payload, 'plate') === false && strpos($payload, 'driver') === false, 'Payload embeds no sensitive data');

$first = $service->ensureForBus(4, 'BUS-004', 'https://rconnect.example', 'superadmin');
check($first['bus_ref'] == 4, 'Credential is bound to the internal bus reference');
check($first['status'] === BusQrService::STATUS_ACTIVE, 'First credential is active');
check($first['created_by'] === 'superadmin', 'Creating actor is recorded');

$again = $service->ensureForBus(4, 'BUS-004', 'https://rconnect.example', 'superadmin');
check($again['token'] === $first['token'], 'Repeated ensure never creates a second credential');
check(count($service->historyFor(4)) === 1, 'Only one credential row exists after ensure');

$other = $service->ensureForBus(7, 'BUS-007', 'https://rconnect.example', null);
check($other['token'] !== $first['token'], 'Different buses receive different tokens');

check($service->resolveByToken($first['token']) !== null, 'Active token resolves');
check($service->resolveByToken('deadbeef') === null, 'Unknown tokens do not resolve');
check($service->tokenExists($first['token']) === true, 'tokenExists finds used tokens');
check($service->tokenExists('deadbeef') === false, 'tokenExists rejects unused tokens');

check($service->resolveForBus($first['token'], 4) !== null, 'resolveForBus matches the owning bus');
check($service->resolveForBus($first['token'], 7) === null, 'resolveForBus refuses a token from another bus');

/* ------------------------------ replacement ------------------------------ */

$replacement = $service->replace(4, 'BUS-004', 'https://rconnect.example', 'superadmin', 'Code damaged on the bus');
check($replacement['token'] !== $first['token'], 'Replacement issues a new token');
check($replacement['status'] === BusQrService::STATUS_ACTIVE, 'Replacement credential is active');
check($replacement['reason'] === 'Code damaged on the bus', 'Replacement reason is stored');
check($service->resolveByToken($first['token']) === null, 'The retired token stops resolving');
check($service->resolveByToken($replacement['token']) !== null, 'The new token resolves');
check($service->tokenExists($first['token']) === true, 'Retired tokens stay in history and are never reused');

$history = $service->historyFor(4);
check(count($history) === 2, 'Replacement history keeps both credentials');
$retired = null;
foreach ($history as $row) {
    if ($row['token'] === $first['token']) {
        $retired = $row;
    }
}
check($retired !== null && $retired['status'] === BusQrService::STATUS_REVOKED, 'The old credential is revoked');
check($retired !== null && !empty($retired['replaced_at']), 'The retirement timestamp is recorded');

/* -------------------------------- revocation -------------------------------- */

check($service->revoke(4, 'superadmin', 'Bus retired from service') === true, 'Revoking an active credential succeeds');
check($service->resolveByToken($replacement['token']) === null, 'A revoked token stops resolving');
check($service->activeFor(4) === null, 'A revoked bus has no active credential');
check($service->revoke(4, 'superadmin', 'again') === false, 'Revoking without an active credential reports nothing done');
check(count($service->historyFor(4)) === 2, 'Revocation does not add duplicate rows');

$renewed = $service->ensureForBus(4, 'BUS-004', 'https://rconnect.example', 'superadmin');
check($renewed['token'] !== $first['token'] && $renewed['token'] !== $replacement['token'], 'A renewed credential never reuses a retired token');
check(count($service->historyFor(4)) === 3, 'Renewal appends to the credential history');

/* ------------------------------ transaction safety ------------------------------ */

$db->beginTransaction();
$inTx = $service->ensureForBus(11, 'BUS-011', 'https://rconnect.example', 'superadmin');
$db->commit();
check($inTx['status'] === BusQrService::STATUS_ACTIVE, 'Credentials can be created inside the caller transaction');

$imagePath = '/uploads/qr/QR-BUS-011-1001.png';
$service->markImage($inTx['credential_id'], $imagePath);
check($service->activeFor(11)['image_path'] === $imagePath, 'Image path is stored on the credential');

echo "$checks bus QR service checks passed.\n";
