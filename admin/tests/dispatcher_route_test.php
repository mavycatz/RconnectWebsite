<?php declare(strict_types=1);

/**
 * Dispatcher automatic route assignment checks: the default route label follows
 * the dispatcher's existing convention (the first word of each terminal name
 * joined with a dash, so Rizal / Poblacion Terminal + Cabanatuan Central
 * Terminal becomes "Rizal-Cabanatuan"), and nothing is invented when a
 * terminal cannot be named.
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

check(dispatcher_terminal_short_name('Rizal / Poblacion Terminal') === 'Rizal', 'Rizal / Poblacion Terminal shortens to its first word');
check(dispatcher_terminal_short_name('Cabanatuan Central Terminal') === 'Cabanatuan', 'Cabanatuan Central Terminal shortens to its first word');
check(dispatcher_terminal_short_name('   ') === '', 'A blank terminal name has no short form');

check(
    dispatcher_route_from_terminal_names('Rizal / Poblacion Terminal', 'Cabanatuan Central Terminal') === 'Rizal-Cabanatuan',
    'The Rizal to Cabanatuan direction builds the Rizal-Cabanatuan route label'
);
check(
    dispatcher_route_from_terminal_names('Cabanatuan Central Terminal', 'Rizal / Poblacion Terminal') === 'Cabanatuan-Rizal',
    'The Cabanatuan to Rizal direction builds the Cabanatuan-Rizal route label'
);
check(dispatcher_route_from_terminal_names('', 'Cabanatuan Central Terminal') === '', 'No origin terminal name yields no route label');
check(dispatcher_route_from_terminal_names('Rizal / Poblacion Terminal', '') === '', 'No destination terminal name yields no route label');

echo "$checks dispatcher route checks passed.\n";
