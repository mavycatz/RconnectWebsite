<link rel="stylesheet" href="/admin/assets/css/navbaradmin.css">

<style>
    html, body {
        background-color: #eceed9 !important;
    }

    body {
        font-family: 'Poppins', sans-serif;
        overflow-x: hidden;
        width: 100%;
        max-width: 100%;
        margin: 0;
        min-height: 100vh;
    }

    .content-wrapper {
        background: #fef8f3;
        border: 1px solid #aaa8b1;
        border-radius: 24px;
        padding: 24px;
    }

    .main-content {
        max-width: 100%;
        box-sizing: border-box;
        padding: 20px;
        margin-top: 60px;
        transition: margin-left 0.3s ease;
    }

    .scroll-cards {
        display: flex;
        gap: 1rem;
        overflow-x: auto;
        padding: 1rem 0;
    }

    .scroll-cards::-webkit-scrollbar {
        height: 8px;
    }

    .scroll-cards::-webkit-scrollbar-thumb {
        background: #cdd9b5;
        border-radius: 4px;
    }

    .scroll-cards::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .scroll-cards .card {
        flex: 0 0 auto;
        width: 280px;
        background: #fef8f3;
        border: 2px solid #e7e3af;
        border-radius: 16px;
        padding: 1rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 380px;
    }

    .table th,
    .table td {
        vertical-align: middle;
    }

    @media(max-width:991px) {
        body { overflow-x: hidden !important; width: 100% !important; }
        .header { left: 0 !important; padding: 0 10px !important; }
        #mainContent { margin-left: 0 !important; width: 100% !important; padding: 10px !important; margin-top: 60px !important; }
        .content-wrapper { padding: 12px !important; border-radius: 14px !important; border-left: none !important; border-right: none !important; }
        .header-left input { width: 100% !important; max-width: 200px !important; }
        .input-group[style*="width:350px"] { width: 100% !important; max-width: 100% !important; }
        .scroll-cards .card { width: 100% !important; min-height: auto !important; }
        .scroll-cards { flex-wrap: wrap !important; overflow-x: visible !important; }
        .btn { min-height: 40px !important; }
    }
</style>