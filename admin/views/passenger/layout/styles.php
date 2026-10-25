<style>
    @layer passenger-legacy {
    html, body {
        background-color: #eceed9 !important;
    }
    canvas { display: block; width: 100%; }
    .perm-btn {
        width: 40px; height: 40px; border-radius: 50%; border: none;
        font-weight: bold; cursor: pointer; transition: all 0.2s;
        background: #f0f0f0; color: #333;
    }
    .perm-btn.active {
        background: linear-gradient(90deg, #b8e405 0%, #005a15 100%);
        color: white;
    }
    .perm-btn:hover { transform: scale(1.1); }
    .table td, .table th { vertical-align: middle; }

    }
</style>
<?php
// Use the same shell offsets and management components as Role/Bus pages.
$navbarCssPath = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/admin/assets/css/navbaradmin.css';
$navbarCssVersion = is_file($navbarCssPath) ? (string) filemtime($navbarCssPath) : '1';
?>
<link rel="stylesheet" href="/admin/assets/css/navbaradmin.css?v=<?= rawurlencode($navbarCssVersion) ?>">
<link rel="stylesheet" href="/admin/assets/css/role-management.css?v=<?= time() ?>">
<link rel="stylesheet" href="/admin/assets/css/passenger-management.css?v=<?= time() ?>">
</head>
