<link rel="stylesheet" href="/admin/assets/css/navbaradmin.css">


<style>
    html, body {
        background-color: #eceed9 !important;
    }
    body {
        margin: 0;
        display: flex;
        min-height: 100vh;
        font-family: 'Poppins', sans-serif;
    }
    .main-content {
        flex-grow: 1;
        padding: 20px;
        margin-left: 0;
        margin-top: 60px;
        transition: margin-left 0.3s ease;
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
        transition: left 0.3s ease;
        z-index: 1001;
    }
    .header.sidebar-collapsed {
        left: 0;
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
    .admin-scroll {
        display: flex;
        gap: 16px;
        overflow-x: auto;
        padding-bottom: 16px;
        -webkit-overflow-scrolling: touch;
        scroll-behavior: smooth;
    }
    .admin-scroll::-webkit-scrollbar { display: none; }
    .admin-card {
        flex: 0 0 auto;
        width: 320px;
        background: #f1f3f8;
        border: 1px solid #c5cbe3;
        border-radius: .8rem;
        box-shadow: 0 3px 8px rgba(0, 0, 0, .12);
        padding: 16px;
    }

</style>
</head>