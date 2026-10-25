<?php
// Use the existing navbar without embedding its second HTML document.
ob_start();
require $_SERVER['DOCUMENT_ROOT'] . '/admin/assets/layout/navbaradmin.php';
$navbarHtml = ob_get_clean();
if (preg_match_all('~<style\b[^>]*>.*?</style>~is', $navbarHtml, $matches)) {
    echo implode("\n", $matches[0]);
}
echo preg_match('~<body\b[^>]*>(.*)</body>~is', $navbarHtml, $matches) ? $matches[1] : $navbarHtml;
