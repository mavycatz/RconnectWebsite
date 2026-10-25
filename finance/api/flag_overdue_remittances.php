<?php declare(strict_types=1);

/**
 * Daily closing: flag eligible outstanding remittances as Overdue.
 *
 * Intended to run at 23:59 Asia/Manila, for example with a Hostinger cron:
 *   php /home/<account>/domains/<domain>/public_html/finance/api/flag_overdue_remittances.php
 *
 * The Remittance page also runs the same idempotent flagging lazily, so the
 * feature works even when this scheduler is not configured.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/services/RemittanceService.php';

use Finance\Services\RemittanceService;

try {
    $created = RemittanceService::flagOverdueRemittances();
    echo '[' . date('Y-m-d H:i:s') . '] Overdue remittance flagging completed: '
        . $created . " record(s) created.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Overdue remittance flagging failed: ' . $e->getMessage() . "\n");
    exit(1);
}
