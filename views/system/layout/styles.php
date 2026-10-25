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
        overflow-x: hidden;
        width: 100%;
        max-width: 100%;
        color: #2c3e2e;
    }
    .main-content {
        flex-grow: 1;
        padding: 16px 20px;
        margin-left: 0;
        margin-top: 60px;
        max-width: 100%;
        box-sizing: border-box;
        transition: margin-left 0.3s ease;
    }

    .main-content.sidebar-collapsed {
        margin-left: 0;
    }

    .sys-live-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background-color: #198754;
        box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7);
        animation: sysPulse 2s infinite;
        vertical-align: middle;
    }
    @keyframes sysPulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7); }
        70% { transform: scale(1.1); box-shadow: 0 0 0 6px rgba(25, 135, 84, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(25, 135, 84, 0); }
    }

    .sys-panel {
        background: #ffffff;
        border: 1px solid #e5e0a4;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .sys-panel:hover {
        border-color: #d8d292;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }
    .sys-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        border-bottom: 1px solid #f0ecce;
    }
    .sys-panel-title {
        font-size: 0.88rem;
        font-weight: 700;
        color: #295738;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 7px;
    }
    .sys-panel-body {
        padding: 14px 16px;
    }

    .sys-kpi-card {
        border-radius: 10px;
        padding: 12px 14px;
        position: relative;
        border: 1px solid transparent;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .sys-kpi-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
    }
    .sys-kpi-label {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .sys-kpi-value {
        font-size: 1.45rem;
        font-weight: 800;
        line-height: 1.2;
        margin: 0;
    }
    .sys-kpi-trend {
        font-size: 0.72rem;
        font-weight: 500;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .sys-kpi-error {
        background: #fdf3f2;
        border-color: #f1cfc8;
        color: #dc3545;
    }
    .sys-kpi-error .sys-kpi-value { color: #dc3545; }
    .sys-kpi-error .sys-kpi-trend { color: #a93226; }

    .sys-kpi-success {
        background: #f2f8f3;
        border-color: #c9e2cf;
        color: #198754;
    }
    .sys-kpi-success .sys-kpi-value { color: #198754; }
    .sys-kpi-success .sys-kpi-trend { color: #157347; }

    .sys-kpi-warning {
        background: #fefaf0;
        border-color: #edd99e;
        color: #b8860b;
    }
    .sys-kpi-warning .sys-kpi-value { color: #b8860b; }
    .sys-kpi-warning .sys-kpi-trend { color: #8a6508; }

    .sys-kpi-monitor {
        background: #f2f6fa;
        border-color: #c8d8e8;
        color: #0d6efd;
    }
    .sys-kpi-monitor .sys-kpi-value { color: #0d6efd; }
    .sys-kpi-monitor .sys-kpi-trend { color: #0b5ed7; }

    .sys-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.8rem;
    }
    .sys-table th {
        background: #f8f7eb;
        color: #617060;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 8px 10px;
        border-bottom: 1px solid #e7e3af;
        white-space: nowrap;
    }
    .sys-table th:first-child { border-top-left-radius: 6px; }
    .sys-table th:last-child { border-top-right-radius: 6px; }
    .sys-table td {
        padding: 8px 10px;
        border-bottom: 1px solid #f0ede0;
        vertical-align: middle;
        color: #2c3e2e;
    }
    .sys-table tbody tr:last-child td {
        border-bottom: none;
    }
    .sys-table tbody tr:hover td {
        background: #faf9f2;
    }

    .sys-table-compact {
        font-size: 0.74rem;
    }
    .sys-table-compact th {
        padding: 7px 10px;
        font-size: 0.66rem;
    }
    .sys-table-compact td {
        padding: 10px;
    }
    .sys-table-compact .sys-status-text {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.3px;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .sys-table-compact .sys-status-text .dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: currentColor;
        flex-shrink: 0;
    }
    .sys-table-compact .sys-status-text.status-success { color: #198754; }
    .sys-table-compact .sys-status-text.status-warning { color: #b8860b; }
    .sys-table-compact .sys-status-text.status-error { color: #dc3545; }
    .sys-table-compact .sys-status-text.status-info { color: #0d6efd; }
    .sys-table-compact .sys-cell-time {
        font-size: 0.72rem;
        font-weight: 600;
        color: #5a6e5a;
        white-space: nowrap;
    }
    .sys-table-compact .sys-cell-module {
        font-size: 0.72rem;
        font-weight: 600;
        color: #617060;
    }
    .sys-table-compact .sys-cell-activity {
        color: #2c3e2e;
    }
    .sys-table-compact .sys-cell-user {
        font-weight: 600;
        color: #295738;
    }

    .sys-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.68rem;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 4px;
        letter-spacing: 0.3px;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .sys-badge-success { background: #e6f6ec; color: #198754; border: 1px solid #bce6cb; }
    .sys-badge-warning { background: #fff8e6; color: #b8860b; border: 1px solid #f6e09e; }
    .sys-badge-error { background: #fde8e8; color: #dc3545; border: 1px solid #f8bcbc; }
    .sys-badge-info { background: #e8f2fe; color: #0d6efd; border: 1px solid #b8d7fb; }

    .sys-session-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        border-radius: 8px;
        background: #fbfbf6;
        border: 1px solid #edeac5;
        margin-bottom: 7px;
        transition: background 0.15s ease;
    }
    .sys-session-item:hover {
        background: #f4f3ea;
    }
    .sys-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #295738;
        color: #ffffff;
        font-size: 0.75rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border: 2px solid #ffffff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    .sys-avatar.is-self {
        background: linear-gradient(135deg, #1a6b3c 0%, #295738 100%);
        box-shadow: 0 0 0 2px #198754;
    }
    .sys-session-info {
        flex: 1;
        min-width: 0;
    }
    .sys-session-name {
        font-size: 0.8rem;
        font-weight: 700;
        color: #295738;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .sys-session-role {
        font-size: 0.68rem;
        font-weight: 600;
        color: #607260;
        background: #eef3eb;
        padding: 1px 5px;
        border-radius: 3px;
    }
    .sys-session-activity {
        font-size: 0.72rem;
        color: #556655;
        margin: 1px 0 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sys-session-time {
        font-size: 0.7rem;
        color: #839183;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .sys-stat-pill {
        background: #faf9f2;
        border: 1px solid #e7e3af;
        border-radius: 8px;
        padding: 8px 12px;
        height: 100%;
    }
    .sys-stat-label {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        color: #6a7c6a;
        letter-spacing: 0.4px;
        margin-bottom: 2px;
    }
    .sys-stat-val {
        font-size: 1.05rem;
        font-weight: 700;
        color: #295738;
        margin: 0;
    }
    .sys-progress-bar {
        height: 6px;
        border-radius: 3px;
        background: #e2e8df;
        display: flex;
        overflow: hidden;
        margin-top: 6px;
    }
    .sys-progress-seg {
        height: 100%;
        transition: width 0.3s ease;
    }

    .sys-sparkline-wrap {
        display: flex;
        align-items: flex-end;
        gap: 4px;
        height: 38px;
        padding-top: 4px;
    }
    .sys-spark-bar {
        flex: 1;
        background: #3da35d;
        border-radius: 2px 2px 0 0;
        min-height: 4px;
        position: relative;
        opacity: 0.85;
        transition: height 0.25s ease, opacity 0.15s ease;
        cursor: default;
    }
    .sys-spark-bar:hover {
        opacity: 1;
        background: #1a6b3c;
    }

    .sys-table-scroll {
        max-height: 300px;
        overflow-y: auto;
        overflow-x: auto;
    }
    .sys-table-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 2;
    }
    .sys-sessions-scroll {
        max-height: 300px;
        overflow-y: auto;
    }

    .sys-monitor-strip {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
        padding: 7px 14px;
        background: #ffffff;
        border: 1px solid #e5e0a4;
        border-radius: 10px;
        font-size: 0.72rem;
        color: #6a7c6a;
    }
    .sys-monitor-metric {
        display: flex;
        align-items: center;
        gap: 5px;
        min-width: 0;
    }
    .sys-monitor-metric strong {
        color: #295738;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 160px;
    }
    .sys-sparkline-mini {
        height: 22px;
        gap: 3px;
        padding-top: 0;
        min-width: 70px;
        flex: 0 1 130px;
    }

    .sys-filter-input,
    .sys-filter-select {
        background: #ffffff;
        border: 1px solid #dcd79e;
        border-radius: 8px;
        padding: 6px 12px;
        font-size: 0.82rem;
        color: #295738;
        outline: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .sys-filter-input:focus,
    .sys-filter-select:focus {
        border-color: #295738;
        box-shadow: 0 0 0 3px rgba(41, 87, 56, 0.1);
    }
    .sys-btn-clear {
        background: #faf8ec;
        border: 1px solid #dcd79e;
        color: #607260;
        font-size: 0.78rem;
        font-weight: 600;
        border-radius: 8px;
        padding: 6px 12px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.15s ease;
    }
    .sys-btn-clear:hover {
        background: #eef3eb;
        color: #295738;
        border-color: #295738;
    }

    .sys-log-card {
        background: #ffffff;
        border: 1px solid #e7e3af;
        border-radius: 8px;
        padding: 10px 14px;
        margin-bottom: 8px;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        position: relative;
    }
    .sys-log-card:hover {
        transform: translateX(2px);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
    }
    .sys-log-card.status-success {
        border-left: 4px solid #198754;
    }
    .sys-log-card.status-warning {
        border-left: 4px solid #d4a017;
    }
    .sys-log-card.status-error,
    .sys-log-card.status-failed {
        border-left: 4px solid #dc3545;
    }
    .sys-log-card.status-info,
    .sys-log-card.status-monitor {
        border-left: 4px solid #0d6efd;
    }

    .sys-log-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 4px;
    }
    .sys-log-title {
        font-size: 0.8rem;
        font-weight: 700;
        color: #295738;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .sys-log-desc {
        font-size: 0.82rem;
        color: #223322;
        margin: 2px 0 6px;
        line-height: 1.45;
    }
    .sys-log-meta {
        font-size: 0.72rem;
        color: #728272;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }
    .sys-entity-badge {
        background: #f0f5ee;
        border: 1px solid #d0ded0;
        color: #1a5c2d;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 1px 6px;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }

    .sys-empty-state {
        text-align: center;
        padding: 30px 15px;
        color: #889988;
    }
    .sys-empty-icon {
        font-size: 1.8rem;
        color: #aab5a8;
        margin-bottom: 6px;
    }

    @media(max-width:991px) {
        #mainContent { padding: 10px !important; margin-top: 60px !important; }
        .sys-table { font-size: 0.75rem; }
        .sys-log-meta { gap: 4px; }
    }
</style>