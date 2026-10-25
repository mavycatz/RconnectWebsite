<style>
    html, body {
        background-color: #eceed9 !important;
    }
    body {
        margin: 0;
        display: flex;
        min-height: 100vh;
        font-family: 'Poppins', sans-serif;
        overflow-x: hidden;
        width: 100%;
        max-width: 100%;
    }
    .main-content {
        flex-grow: 1;
        padding: 20px;
        margin-left: 0;
        margin-top: 60px;
        max-width: 100%;
        box-sizing: border-box;
        transition: margin-left 0.3s ease;
    }

    .main-content.sidebar-collapsed {
        margin-left: 0;
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

    @media(max-width:991px) {
        .header { left: 0 !important; padding: 0 10px !important; }
        #mainContent { padding: 10px !important; margin-top: 60px !important; }
        #mainContent > div[style*="padding:24px"] { padding: 12px !important; border-radius: 14px !important; border-left: none !important; border-right: none !important; }
        .header-left input { width: 100% !important; max-width: 200px !important; }
        .modal-dialog.modal-xl { margin: 0.5rem !important; max-width: calc(100vw - 1rem) !important; }
        .btn { min-height: 40px !important; font-size: 0.8rem !important; }
    }
</style>
<?php
// The admin shell (sidebar/header) styles come from navbaradmin.css. It must
// load after this file's base styles (as it did originally from inside the
// body) so its .main-content / .header offsets win the cascade; loading it in
// <head> keeps it render-blocking so the shell never paints unstyled.
$navbarCssPath = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/admin/assets/css/navbaradmin.css';
$navbarCssVersion = is_file($navbarCssPath) ? (string) filemtime($navbarCssPath) : '1';
?>
<link rel="stylesheet" href="/admin/assets/css/navbaradmin.css?v=<?= rawurlencode($navbarCssVersion) ?>">
</head>
