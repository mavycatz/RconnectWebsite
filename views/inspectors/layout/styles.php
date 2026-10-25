<style>
    html, body {
        background-color: #eceed9 !important;
    }

    .swal2-popup { border-radius: 20px !important; padding: 24px !important; border: 2px solid #cdd9b5 !important; font-family: 'Poppins', sans-serif !important; }
    .swal2-title { color: #2e4836 !important; font-weight: 700 !important; font-size: 18px !important; }
    .swal2-html-container { color: #555 !important; font-size: 13px !important; }
    .swal2-confirm { border-radius: 12px !important; font-weight: 600 !important; padding: 10px 28px !important; font-size: 13px !important; }
    .swal2-cancel { border-radius: 12px !important; font-weight: 500 !important; padding: 10px 28px !important; font-size: 13px !important; }
    .swal2-icon { border-color: transparent !important; }
    .swal2-success-ring { border-color: #2e4836 !important; }
    .swal2-success-line-tip, .swal2-success-line-long { background-color: #2e4836 !important; }
    .swal2-error-line { background-color: #dc2626 !important; }
    .swal2-x-mark-line-left, .swal2-x-mark-line-right { background-color: #dc2626 !important; }
    .swal2-timer-progress-bar { background: linear-gradient(90deg, #2e4836, #6a8f5a) !important; }
    .swal2-styled.swal2-confirm:focus { box-shadow: none !important; }

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
        min-width: 0;
        box-sizing: border-box;
        transition: margin-left 0.3s ease;
    }

    .main-content.sidebar-collapsed {
        margin-left: 0;
    }
    canvas {
        display: block;
        width: 100%;
    }
    .header {
        position: fixed;
        top: 0;
        left: 220px;
        right: 0;
        height: 60px;
        background: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 20px;
        border-bottom: 1px solid rgba(0, 0, 0, .1);
        z-index: 1001;
    }
    .header-left input { width: 700px; }
    .icon-container {
        width: 40px;
        height: 40px;
        border: 2px solid #86858f;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .icon { color: #295738; font-size: 20px; }
    #map { position: relative; z-index: 1; }
    .hamburger {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        width: 30px;
        height: 22px;
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
    }
    .hamburger span {
        display: block;
        height: 3px;
        width: 100%;
        background-color: #6f6d75;
        border-radius: 2px;
    }

    .pro-modal {
        border-radius: 18px;
        overflow: hidden;
        border: none;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
    }
    .pro-header {
        background: linear-gradient(90deg, #b8e405, #005a15);
        color: white;
        padding: 20px;
        border: none;
    }
    .pro-section {
        background: #f8faf4;
        padding: 20px;
        border-radius: 14px;
        margin-bottom: 20px;
        border: 1px solid #e3ecd9;
    }
    .section-title {
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        color: #295738;
    }
    .pro-input {
        border-radius: 10px;
        border: 1px solid #d5e2c8;
    }
    .pro-input:focus {
        border-color: #005a15;
        box-shadow: 0 0 0 0.2rem rgba(0, 90, 21, 0.15);
    }
    .pro-footer {
        background: #f1f5eb;
        border: none;
    }
    .pro-submit {
        background: linear-gradient(90deg, #b8e405, #005a15);
        border: none;
        color: white;
    }

    @media(max-width:768px) {
        .header { left: 0 !important; padding: 0 10px !important; }
        #mainContent { padding: 10px !important; margin-top: 60px !important; }
        #mainContent > div[style*="padding:24px"] { padding: 12px !important; border-radius: 14px !important; border-left: none !important; border-right: none !important; }
        .row.g-3.mb-4 > .col-md-3 { width: 100% !important; flex: 0 0 100% !important; max-width: 100% !important; }
        .row.g-3.mb-4 > .col-md-3 > div { padding: 8px !important; }
        .row.g-3.mb-4 i.bi { font-size: 1.4rem !important; }
        .row.g-3.mb-4 div[id$="Inspectors"] { font-size: 1.1rem !important; }
        .row.g-3.mb-4 [style*="font-size"][style*=".75rem"] { font-size: 0.6rem !important; }
        .p-4.mb-4[style*="border:1px solid #e8e8e8"] { padding: 10px !important; }
        .p-4.mb-4[style*="border:1px solid #e8e8e8"] h5 { font-size: 0.85rem !important; }
        .input-group { width: 100% !important; max-width: 100% !important; }
        #inspectorSearch { max-width: 100% !important; }
        #inspectorsContainer > div { width: 100% !important; flex: 0 0 100% !important; max-width: 100% !important; }
        #inspectorsContainer .p-4 { padding: 10px !important; }
        #inspectorsContainer [style*="width:360px"] { width: 100% !important; max-width: 100% !important; }
        #inspectorsContainer [style*="width:85px"] { width: 55px !important; height: 55px !important; }
        #inspectorsContainer h6 { font-size: 0.8rem !important; }
        #inspectorsContainer .small, #inspectorsContainer .text-muted { font-size: 0.7rem !important; }
        #inspectorsContainer .btn { min-height: 38px !important; font-size: 0.7rem !important; padding: 5px 10px !important; }
        .card.p-3[style*="background-color:#faf9e5"] { padding: 10px !important; }
        .card.p-3[style*="background-color:#faf9e5"] table { font-size: 0.65rem !important; }
        .card.p-3[style*="background-color:#faf9e5"] .btn { font-size: 0.65rem !important; padding: 5px 8px !important; min-height: 34px !important; }
        .container.my-5.p-4[style*="background:#faf9e5"] { padding: 10px !important; margin: 0.5rem 0 !important; }
        .container.my-5.p-4[style*="background:#faf9e5"] h5 { font-size: 0.85rem !important; }
        .container.my-5.p-4[style*="background:#faf9e5"] table { font-size: 0.6rem !important; }
        .perm-btn { width: 30px !important; height: 30px !important; font-size: 0.7rem !important; }
        .modal-dialog.modal-xl { margin: 0.5rem !important; max-width: calc(100vw - 1rem) !important; }
        .modal-content h4, .modal-content h5 { font-size: 0.85rem !important; }
        .modal-content input, .modal-content select { font-size: 0.7rem !important; padding: 5px 8px !important; }
        .modal-content label { font-size: 0.65rem !important; }
        .modal-content .btn { font-size: 0.65rem !important; padding: 5px 8px !important; }
        .header-left input { width: 100% !important; max-width: 200px !important; }
        .btn:not(.hamburger), button:not(.hamburger) { min-height: 40px !important; }
    }
    .perm-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: none;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.2s;
        background: #f0f0f0;
        color: #333;
    }
    .perm-btn.active {
        background: linear-gradient(90deg, #b8e405 0%, #005a15 100%);
        color: white;
    }
    .perm-btn:hover {
        transform: scale(1.1);
    }
    .table td, .table th {
        vertical-align: middle;
    }

    .customArchivePopup {
        border-radius: 18px !important;
        padding: 12px !important;
        box-shadow:
        0 10px 25px rgba(0, 0, 0, .12) !important;
    }

    .customArchiveConfirm {
        background:
        linear-gradient(
        90deg,
        #b8e405,
        #005a15
        ) !important;

        color: white !important;
        border: none !important;
        border-radius: 50px !important;

        padding:
        8px 20px !important;

        font-size: 14px !important;
        font-weight: 600 !important;

        margin-left: 4px !important;
    }

    .customArchiveCancel {
        background: #f4f4f4 !important;

        color: #555 !important;
        border: none !important;
        border-radius: 50px !important;

        padding:
        8px 20px !important;

        font-size: 14px !important;
        font-weight: 600 !important;

        margin-right: 4px !important;
    }

    .swal2-title {
        margin: 0 !important;
        padding: 0 !important;
    }

    .swal2-html-container {
        margin: 8px 0 !important;
        padding: 0 !important;
        line-height: 1.4 !important;
    }

    .swal2-icon {
        margin: 8px auto !important;
    }

    .swal2-actions {
        margin-top: 10px !important;
        gap: 6px !important;
    }

</style>
</head>