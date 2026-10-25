<link rel="stylesheet" href="/admin/assets/css/navbaradmin.css">
<style>

    html, body {
        background-color: #eceed9 !important;
    }
    .report-card {
        background: #faf9e5;
        border: 2px solid #e5e0a4;
        border-radius: 20px;
        padding: 20px;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
    }

    .report-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: #295738;
        margin-bottom: 20px;
    }

    .report-number {
        font-size: 2rem;
        font-weight: 700;
        color: #096d11;
    }

    .status-valid {
        color: #096d11;
        font-weight: 600;
    }

    .status-expired {
        color: #b00020;
        font-weight: 600;
    }

    .modal {
        z-index: 999999 !important;
    }

    .modal-dialog {
        z-index: 1000000 !important;
        position: relative !important;
    }

    .modal-content {
        z-index: 1000001 !important;
        position: relative !important;
        background: #fff !important;
    }


    .modal-backdrop {
        z-index: 999998 !important;
    }


    .main-content,
    .row,
    .col-lg-4,
    .col-lg-8,
    .col-md-6,
    .col-lg-3 {
        overflow: visible !important;
        position: static !important;
        transform: none !important;
    }


    .report-card {
        position: relative !important;
        z-index: 1 !important;
        transform: none !important;
    }


    body.modal-open {
        overflow: hidden !important;
        padding-right: 0 !important;
    }

    .swal2-container {
        z-index: 2000000 !important;
    }

    .swal2-popup {
        z-index: 2000001 !important;
    }

    @media(max-width:991px) {
        body { overflow-x: hidden !important; width: 100% !important; }
        .header { left: 0 !important; padding: 0 10px !important; }
        .main-content { padding: 10px !important; margin-top: 60px !important; }
        .header-left input { width: 100% !important; max-width: 200px !important; }
        .btn { min-height: 40px !important; }
    }
</style>
</head>