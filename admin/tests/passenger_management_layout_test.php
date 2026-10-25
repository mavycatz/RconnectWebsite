<?php declare(strict_types=1);
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
$_SESSION['passenger_management_csrf'] = 'fixture-token';
$adminUrl = static fn(string $page): string => '/admin/' . $page;
ob_start();
require __DIR__ . '/../views/passenger/layout/header.php';
require __DIR__ . '/../views/passenger/layout/styles.php';
echo '<body class="passenger-management-page"><main><div class="rmg-shell"><section class="rmg passenger-management">';
require __DIR__ . '/../views/passenger/sections/stats.php';
require __DIR__ . '/../views/passenger/sections/passenger-cards.php';
echo '</section></div></main>';
require __DIR__ . '/../views/layout/custom-alert.php';
echo '</body></html>';
$html = ob_get_clean();
$doc = new DOMDocument();
@$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($doc);
$checks = 0;
function check(bool $ok, string $message): void { global $checks; if (!$ok) { throw new RuntimeException($message); } $checks++; }
check($xpath->query('//h1')->length === 1, 'Passenger Management has one heading, not duplicate page headers');
check($xpath->query('//link[contains(@href,"/admin/assets/css/role-management.css")]')->length === 1, 'Actual shared management design is loaded');
check($xpath->query('//div[contains(concat(" ",normalize-space(@class)," ")," rmg-sum-card ")]')->length === 4, 'Summary cards reuse existing management component');
check($xpath->query('//table[contains(concat(" ",normalize-space(@class)," ")," rmg-table ")]')->length === 1, 'Passenger directory reuses shared responsive table');
check($xpath->query('//*[@id="psgrDetailsPanel"]//div[contains(@class,"modal-dialog-centered")]')->length === 1, 'Passenger details use the same centered modal layout');
check($xpath->query('//*[@id="psgrDetailsPanel"]//div[contains(@class,"rmg-detail-modal")]')->length === 1, 'Profile uses shared detail component');
foreach (['searchInput','filterDiscount','filterVerification','filterStatus','filterDateFrom','filterDateTo','psgrSortSelect','psgrPerPageSel',
    'filterPayment','filterAccount','filterTripActivity','filterBalanceMin','filterBalanceMax','filterBoardedToday',
    'psgrBody','psgrPag','psgrCount','archivedBtn','panelTabProfile','panelTabTrips','panelTabPayments','panelActions'] as $id) {
    check($xpath->query('//*[@id="' . $id . '"]')->length === 1, 'Existing function control has exactly one ID: ' . $id);
}
check($xpath->query('//*[@id="customAlertOverlay"]')->length === 1, 'Existing custom alert overlay is not duplicated');
check(!str_contains($html, 'bootstrap.Offcanvas'), 'Profile controller and markup consistently use Modal');
check(str_contains($html, 'exportPassengers()') && str_contains($html, 'openManifestImport()') && str_contains($html, 'openTripSheetModal()'), 'Export, import and trip-sheet controls remain present');
echo "$checks passenger management layout checks passed.\n";
