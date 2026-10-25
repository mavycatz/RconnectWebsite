<?php

ini_set('display_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../middleware/validate_request.php';

validateFinanceRequest('export', true, true);
setRequestTimeout(30);

$content =
    $_POST['content']
    ?? 'No AI report content.';

$content =
    strip_tags($content);

header(
    'Content-Type: application/octet-stream'
);

header(
    'Content-Disposition: attachment;
    filename="ai_transportation_report.txt"'
);

echo $content;