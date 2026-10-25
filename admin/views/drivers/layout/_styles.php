<style>
    canvas {
        display: block;
        width: 100%;
    }

    #map {
        position: relative;
        z-index: 1;
    }

    .driver-scroll::-webkit-scrollbar {
        display: none;
    }

    .driver-card {
        flex: 0 0 auto;
        width: 340px;
        background: #faf9e5;
        border: 2px solid #e5e0a4;
        border-radius: 20px;
        box-shadow: 0 8px 22px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        transition: all 0.25s ease;
    }

    .driver-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 14px 35px rgba(0, 0, 0, 0.12);
    }

    .driver-header {
        background: linear-gradient(90deg, #b8e405 0%, #005a15 100%);
        color: white;
        padding: 12px 18px;
        font-weight: 600;
        font-size: 0.85rem;
        letter-spacing: 1px;
    }

    .driver-body {
        padding: 20px;
    }

    .driver-avatar {
        width: 78px;
        height: 78px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #e5e0a4;
    }

    .driver-name {
        font-weight: 700;
        font-size: 1rem;
        margin-bottom: 3px;
    }

    .driver-id {
        font-size: 0.8rem;
        color: #6c757d;
    }

    .driver-info {
        font-size: 0.85rem;
        margin-bottom: 6px;
    }

    .driver-label {
        font-weight: 700;
        color: #295738;
    }

    .status-badge {
        font-size: 0.7rem;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 600;
    }

    .status-active {
        background: rgba(25, 135, 84, 0.15);
        color: #198754;
    }

    .driver-actions .btn {
        font-size: 0.75rem;
        padding: 6px 16px;
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
        margin-bottom: 15px;
    }

    .pro-input {
        border-radius: 10px;
        border: 1px solid #d5e2c8;
        transition: 0.2s;
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
        #mainContent { padding: 10px !important; margin-top: 60px !important; }
        #mainContent > div[style*="padding:24px"] { padding: 12px !important; border-radius: 14px !important; border-left: none !important; border-right: none !important; }
        .row.g-3.mb-4 > .col-md-3 { width: 100% !important; }
        [id="totalDrivers"], [id="activeDrivers"], [id="inactiveDrivers"], [id="pendingDrivers"] { font-size: 1.3rem !important; }
        .stats-card i { font-size: 1.8rem !important; }

        .driver-card { width: 100% !important; }
        .driver-scroll { flex-wrap: wrap !important; overflow-x: visible !important; }
        .driver-cards-section { padding: 14px !important; }
        .driver-cards-section table { font-size: 0.7rem !important; }
        .driver-cards-section .btn { font-size: 0.65rem !important; padding: 3px 6px !important; }
        .driver-cards-section input, .driver-cards-section select { font-size: 0.75rem !important; padding: 4px 8px !important; }

        #driverSearch { max-width: 100% !important; }
        .input-group[style*="width:350px"] { width: 100% !important; max-width: 100% !important; }
        .driver-cards-section .d-flex.flex-wrap.gap-2 .btn { font-size: 0.75rem !important; padding: 8px 14px !important; min-height: 44px !important; }

        [class*="col-md-4 d-flex justify-content-center"] > div[style*="width:360px"] { width: 100% !important; max-width: 100% !important; }
        #driverContainer .row.g-4 > [class*="col-md-4"] { width: 100% !important; }

        .schedule-section h5 { font-size: 1rem !important; }
        .schedule-section .card { padding: 10px !important; }
        .schedule-section table { font-size: 0.65rem !important; }
        .schedule-section th, .schedule-section td { padding: 3px 1px !important; }
        .schedule-section .btn { font-size: 0.65rem !important; padding: 3px 6px !important; }

        .access-section { padding: 12px !important; margin: 0.5rem 0 !important; }
        .access-section h5 { font-size: 0.9rem !important; }
        .access-section table { font-size: 0.65rem !important; }
        .access-section .perm-btn { font-size: 0.65rem !important; padding: 2px 4px !important; min-width: 32px !important; min-height: 36px !important; }

        .modal-dialog.modal-xl { margin: 0.5rem !important; max-width: calc(100vw - 1rem) !important; }
        .modal-content h4, .modal-content h5 { font-size: 0.9rem !important; }
        .modal-content input, .modal-content select { font-size: 0.75rem !important; padding: 5px 8px !important; }
        .modal-content label { font-size: 0.7rem !important; }
        .modal-content .btn { font-size: 0.7rem !important; padding: 5px 10px !important; }
        .modal-content .table { font-size: 0.65rem !important; }

        .modal-content .col-lg-4 { width: 100% !important; min-height: auto !important; border-right: none !important; border-bottom: 1px solid #e7e1c9 !important; }
        .modal-content .col-lg-4 .p-5 { padding: 1.5rem !important; }
        .modal-content .col-lg-8 { width: 100% !important; }
        .modal-content .col-lg-8 .modal-header.px-5 { padding: 1rem 1.25rem !important; }
        .modal-content .col-lg-8 .modal-body.px-5 { padding: 1rem 1.25rem !important; }
        .modal-content .col-lg-8 .modal-footer.px-5 { padding: 1rem 1.25rem !important; }
        .modal-content .col-lg-8 [style*="padding:28px"] { padding: 16px !important; }

        .sidebar .nav-link { min-height: 44px; }
    }
</style>