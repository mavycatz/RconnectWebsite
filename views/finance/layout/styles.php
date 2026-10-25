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
    .header-left input {
        width: 700px;
    }
    .icon-container {
        width: 40px;
        height: 40px;
        border: 2px solid #86858f;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .icon {
        color: #295738;
        font-size: 20px;
    }
    .employee-scroll {
        display: flex;
        gap: 16px;
        overflow-x: auto;
        padding-bottom: 16px;
        -webkit-overflow-scrolling: touch;
        scroll-behavior: smooth;
    }
    .employee-scroll::-webkit-scrollbar { display: none; }
    .employee-card {
        flex: 0 0 auto;
        width: 320px;
        background: #f1f3f8;
        border: 1px solid #c5cbe3;
        border-radius: .8rem;
        box-shadow: 0 3px 8px rgba(0, 0, 0, .12);
        padding: 16px;
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
    .perm-btn:hover { transform: scale(1.1); }
    .table td, .table th { vertical-align: middle; }

    @media(max-width:768px) {
        .header { left: 0 !important; padding: 0 10px !important; }
        #mainContent { padding: 10px !important; margin-top: 60px !important; }
        #mainContent > div[style*="padding:24px"] { padding: 12px !important; border-radius: 14px !important; border-left: none !important; border-right: none !important; }
        .input-group { width: 100% !important; max-width: 100% !important; }
        .employee-scroll { flex-wrap: wrap !important; overflow-x: visible !important; }
        .employee-card { width: 100% !important; }
        .p-4.mb-4[style*="border:1px solid #e8e8e8"] { padding: 12px !important; }
        .header-left input { width: 100% !important; max-width: 200px !important; }
        .btn { min-height: 40px !important; font-size: 0.8rem !important; }
        .modal-dialog.modal-xl { margin: 0.5rem !important; max-width: calc(100vw - 1rem) !important; }
    }
</style>
</head>