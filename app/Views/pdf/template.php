<?php

function renderPdfHtml(
    string $title,
    string $bodyContent,
    string $orgSub = 'Transportation Management System &mdash; Intelligence Report',
    array $meta = []
): string {
    $logoHtml = '';
    $logoPath = $_SERVER['DOCUMENT_ROOT'] . '/assets/images/logo/logoR.png';
    if (file_exists($logoPath)) {
        $logoData = @file_get_contents($logoPath);
        if ($logoData !== false) {
            $logoBase64 = base64_encode($logoData);
            $mime = 'image/png';
            $logoHtml = '<img src="data:' . $mime . ';base64,' . $logoBase64 . '" class="logo">';
        }
    }

    $metaHtml = '';
    if (!empty($meta)) {
        $parts = [];
        foreach ($meta as $label => $value) {
            if (is_int($label)) {
                $parts[] = htmlspecialchars((string)$value);
            } else {
                $parts[] = htmlspecialchars((string)$label) . ': ' . htmlspecialchars((string)$value);
            }
        }
        $metaHtml = '<div class="meta-row">' . implode(' &nbsp;|&nbsp; ', $parts) . '</div>';
    }

    return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
@page { margin:32px 36px; }
body { font-family:DejaVu Sans, sans-serif; color:#222; font-size:11px; line-height:1.6; }

.letterhead { border-bottom:3px solid #05590c; padding-bottom:15px; margin-bottom:22px; }
.letterhead table { width:100%; border-collapse:collapse; }
.letterhead .logo-col { width:75px; text-align:center; vertical-align:middle; }
.letterhead .logo { width:55px; }
.letterhead .org-name { font-size:22px; font-weight:800; color:#05590c; letter-spacing:1px; }
.letterhead .org-sub { font-size:10px; color:#666; margin-top:4px; }
.letterhead .doc-title { font-size:16px; font-weight:700; color:#222; margin-top:8px; }

.meta-row { font-size:10px; color:#666; margin-bottom:20px; text-align:center; border:1px solid #eee; border-radius:4px; padding:8px 12px; background:#fafafa; }

.section { margin-bottom:18px; }
.section-title { font-size:13px; font-weight:bold; color:#05590c; margin-bottom:8px; border-left:4px solid #05590c; padding-left:10px; }

.summary-box { background:#f6f9f6; border:1px solid #c8e6c9; padding:12px 15px; border-radius:6px; font-size:11px; }

.summary-box table { width:100%; border-collapse:collapse; margin-top:8px; }
.summary-box table td { border:none; padding:4px 8px; font-size:11px; }

.stats-grid { width:100%; margin-top:10px; }
.stats-card { width:23%; display:inline-block; vertical-align:top; border:1px solid #d5d5d5; border-radius:6px; padding:10px; margin-right:1%; background:#fafafa; box-sizing:border-box; }
.stats-title { font-size:10px; color:#666; margin-bottom:5px; text-transform:uppercase; letter-spacing:0.5px; }
.stats-value { font-size:16px; font-weight:700; color:#05590c; }

table { width:100%; border-collapse:collapse; margin-top:8px; }
th { background:#e8f5e9; border:1px solid #c8e6c9; padding:8px 10px; text-align:left; font-size:10px; font-weight:600; color:#222; }
td { border:1px solid #ddd; padding:7px 10px; font-size:10px; vertical-align:top; }

.analysis { white-space:pre-line; text-align:justify; line-height:1.65; font-size:11px; }

.footer { margin-top:25px; font-size:9px; color:#999; text-align:center; border-top:1px solid #ddd; padding-top:12px; }

.green { color:#05590c; font-weight:600; }
.red { color:#c62828; font-weight:600; }
</style>
</head>
<body>

<div class="letterhead">
    <table>
        <tr>
            <td class="logo-col">
                ' . $logoHtml . '
            </td>
            <td>
                <div class="org-name">RPMVFMPC</div>
                <div class="org-sub">' . $orgSub . '</div>
                <div class="doc-title">' . htmlspecialchars($title) . '</div>
            </td>
        </tr>
    </table>
</div>

' . $metaHtml . '

' . $bodyContent . '

<div class="footer">
    RPMVFMPC &mdash; This document is confidential and intended solely for authorized personnel.
</div>

</body>
</html>';
}
