<?php declare(strict_types=1);

/**
 * Dispatcher destination resolution checks: the next trip's destination is the
 * opposite terminal of the bus's current terminal for the configured
 * Rizal / Poblacion <-> Cabanatuan Central pair, with the bus's configured
 * destination as the fallback. Nothing is guessed when neither resolves.
 */

if (!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

require_once __DIR__ . '/../../dispatcher/api/helpers.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

$pair = ['rizal' => 1, 'cabanatuan' => 2];

check(dispatcher_destination_from_pair($pair, 1, 0) === 2, 'A bus at Rizal / Poblacion returns to Cabanatuan Central');
check(dispatcher_destination_from_pair($pair, 2, 0) === 1, 'A bus at Cabanatuan Central returns to Rizal / Poblacion');
check(dispatcher_destination_from_pair($pair, 1, 2) === 2, 'The stored destination cannot contradict the current terminal');
check(dispatcher_destination_from_pair($pair, 2, 2) === 1, 'A stale stored destination is replaced by the opposite terminal');
check(dispatcher_destination_from_pair($pair, 0, 2) === 2, 'Without a verifiable origin the configured destination is used');
check(dispatcher_destination_from_pair($pair, 3, 2) === 2, 'A terminal outside the pair falls back to the configured destination');
check(dispatcher_destination_from_pair($pair, 3, 0) === 0, 'A terminal outside the pair without a configured destination cannot dispatch');
check(dispatcher_destination_from_pair(null, 1, 2) === 2, 'Without the terminal pair the configured destination still applies');
check(dispatcher_destination_from_pair(null, 1, 0) === 0, 'Without the terminal pair and no configured destination nothing is guessed');
check(dispatcher_destination_from_pair(null, 0, 0) === 0, 'No origin and no configured destination resolves to nothing');
check(dispatcher_destination_from_pair($pair, 1, 1) === 2, 'A destination equal to the origin is never returned');

echo "$checks dispatcher destination checks passed.\n";
