<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/auth/Auth.php';
require_once __DIR__ . '/../services/DateFilterService.php';

use Finance\Services\DateFilterService;

define('APP_LOADED', true);
date_default_timezone_set('Asia/Manila');
$_SESSION = ['user' => ['id' => 1, 'username' => 'Finance Test', 'role' => 'finance_manager', 'logged_in' => true]];
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
$_SERVER['REQUEST_URI'] = '/finance/history.php';

// Render real views, including their embedded config and inline initialization,
// without a database connection. History's rows are supplied by its controller.
$render = static function (string $page, array $request): string {
    $_GET = $request;
    $history = [];
    $totals = [];
    ob_start();
    require __DIR__ . '/../views/' . $page;
    return (string) ob_get_clean();
};
$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException($message);
    $checks++;
};
$pages = ['dashboard/_header.php', 'history/index.php', 'revenueOverview/_header.php'];
$requests = [
    'default' => [],
    'monthly' => ['period' => 'monthly'],
    'custom' => ['period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-15'],
];
$rendered = [];
foreach ($pages as $page) {
    foreach ($requests as $name => $request) {
        $html = $render($page, $request);
        $check(preg_match('/<script type="application\/json" data-fin-config>(.*?)<\/script>/s', $html, $matches) === 1, "$page embeds its config");
        $config = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $expected = DateFilterService::normalizeRequest($request, null, DateFilterService::TODAY);
        $check($config['selected'] === $expected['period'], "$page $name selected period");
        $check($config['start'] === $expected['start'] && $config['end'] === $expected['end'], "$page $name date bounds");
        $check(preg_match('/data-period="' . preg_quote($config['selected'], '/') . '"\s+aria-pressed="true"/', $html) === 1, "$page $name active control");
        $check(count($config['options']) === 6, "$page preserves all period options");
        if ($name === 'default') {
            $check($config['start'] === date('Y-m-d') && $config['end'] === date('Y-m-d'), "$page uses the current Manila day");
        }
        if ($page === 'history/index.php') {
            $check(str_contains($html, 'action=export_pdf&amp;period=' . $config['selected']), 'History PDF link follows selection');
            $check(str_contains($html, 'action=export_excel&amp;period=' . $config['selected']), 'History Excel link follows selection');
        }
        $rendered[$page][$name] = ['html' => $html, 'config' => $config];
    }
}
if (in_array('--json', $argv, true)) {
    echo json_encode($rendered, JSON_THROW_ON_ERROR);
} else {
    echo "$checks Finance Today view checks passed.\n";
}
