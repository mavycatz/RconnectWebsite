<link rel="stylesheet" href="/admin/assets/css/navbaradmin.css">
<style>
    html, body {
        background-color: #eceed9 !important;
    }
    body {
        margin: 0;
        display: flex;
        min-height: 100vh;
    }
    * {
        font-family: 'Poppins', sans-serif;
    }
    .main-content {
        flex-grow: 1;
        padding: 20px;
        margin-left: 0;
        margin-top: 20px;
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
        transition: left 0.3s ease;
        z-index: 1001;
    }
    body {
        background: #eceed9;
        margin: 0;
        min-height: 100vh;
    }
    .content, .main-content {
        flex-grow: 1;
        padding: 20px;
        transition: margin-left 0.3s ease;
    }
    canvas { display: block; width: 100%; }

    #toggleFilter {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 9999;
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    :root {
        --rc-cream: #fef8f3;
        --rc-dark-green: #2e4836;
        --rc-green: #295738;
        --rc-sage: #cdd9b5;
        --rc-light-yellow: #f9f4da;
        --rc-yellow: #c4b417;
    }

    #mainContent {
        overflow-x: hidden;
    }

    #mainContent h1,
    #mainContent h2,
    #mainContent h3,
    #mainContent h4,
    #mainContent h5 {
        color: var(--rc-dark-green);
    }

    #mainContent .dash-panel,
    #mainContent .stat-item {
        min-width: 0;
    }

    @media(max-width:991px) {
        #mainContent {
            padding: 10px !important;
        }

        #mainContent .dash-panel {
            padding: 14px !important;
            border-radius: 14px !important;
        }
    }
</style>