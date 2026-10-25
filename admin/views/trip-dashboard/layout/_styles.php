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
        background-color: #eceed9 !important;
    }

    .main-content {
        flex-grow: 1;
        padding: 20px;
        margin-left: 0;
        margin-top: 76px;
        max-width: 100%;
        box-sizing: border-box;
        transition: margin-left 0.3s ease;
    }

    .main-content.sidebar-collapsed {
        margin-left: 0;
    }

    @media(max-width:991px) {
        .header { left: 0 !important; padding: 0 10px !important; }
        #mainContent { padding: 10px !important; margin-top: 76px !important; }
        #mainContent > div[style*="padding:24px"] {
            padding: 14px !important;
            border-radius: 14px !important;
        }
    }


    .ops-page-title {
        font-size: 24px;
        font-weight: 700;
        color: #295738;
        line-height: 1.2;
        letter-spacing: -0.3px;
    }

    .ops-page-subtitle {
        font-size: 12px;
        font-weight: 400;
        color: #6b7280;
    }

    .ops-section-title {
        font-size: 16px;
        font-weight: 600;
        color: #295738;
        letter-spacing: -0.2px;
    }

    .ops-label-compact {
        font-size: 11px;
        font-weight: 600;
        color: #8a9a8a;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: block;
    }

    .ops-val-compact {
        font-size: 13px;
        font-weight: 600;
        color: #2e4836;
        line-height: 1.3;
    }

    .ops-meta-sub {
        font-size: 11px;
        font-weight: 400;
        color: #6b7280;
        line-height: 1.4;
    }

    .ops-bus-title {
        font-size: 16px;
        font-weight: 700;
        color: #295738;
    }

    .ops-plate-compact {
        font-size: 11px;
        font-weight: 500;
        color: #4b5563;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        padding: 1px 6px;
        border-radius: 4px;
    }

    .ops-hr {
        height: 1px;
        background: #e8e6d9;
        border: none;
        margin: 6px 0;
    }

    .ops-dot-sep {
        color: #9ca3af;
        margin: 0 4px;
    }

    .live-data-indicator {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #e8f4ec;
        color: #2e7d32;
        border: 1px solid #c2e0cb;
        border-radius: 12px;
        padding: 2px 8px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .ops-live-clock {
        font-size: 11px;
        font-weight: 400;
        color: #8a9a8a;
        background: #f9f4da;
        border: 1px solid #e5e0a4;
        padding: 4px 10px;
        border-radius: 8px;
    }

    .pulse-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #2e7d32;
        display: inline-block;
        box-shadow: 0 0 0 0 rgba(46, 125, 50, 0.7);
        animation: opsPulse 1.8s infinite cubic-bezier(0.66, 0, 0, 1);
    }

    @keyframes opsPulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(46, 125, 50, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(46, 125, 50, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(46, 125, 50, 0); }
    }

    .stat-card-group {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
        width: 100%;
        max-width: none;
        margin-bottom: 20px;
    }

    .stat-item {
        width: 100%;
        min-width: 0;
        min-height: 124px;

        background: #fff;
        border: 1.5px solid #cdd9b5;
        border-radius: 18px;

        padding: 18px 20px;

        display: flex;
        align-items: center;
        gap: 16px;

        transition: .2s;
        cursor: default;

        box-shadow: 0 1px 4px rgba(46, 72, 54, 0.05);

        container-type: inline-size;
    }

    .stat-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(46, 72, 54, 0.09);
    }

    .stat-icon-box {
        width: 52px;
        height: 52px;
        min-width: 52px;

        border-radius: 14px;

        display: flex;
        align-items: center;
        justify-content: center;

        flex-shrink: 0;

        font-size: 24px;
    }

    .stat-info {
        flex: 1;
        min-width: 0;

        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .stat-label {
        font-size: 11.5px;
        font-weight: 600;

        color: #6f7d6f;

        text-transform: uppercase;
        letter-spacing: .5px;

        margin-bottom: 5px;

        line-height: 1.2;
    }

    .stat-value {
        font-size: clamp(18px, 8cqi, 26px);
        font-weight: 700;

        color: #2e4836;

        line-height: 1.15;

        font-variant-numeric: tabular-nums;

        white-space: nowrap;
        overflow: hidden;
        overflow-wrap: normal;
        word-break: normal;
    }

    .stat-desc {
        font-size: 11px;

        color: #8a9389;

        margin-top: 4px;

        line-height: 1.2;
    }

    .skeleton-block {
        display: inline-block;

        width: 50px;
        height: 1.05em;

        border-radius: 6px;

        background: linear-gradient(
        90deg,
        #eef1e9 25%,
        #e1e8d7 37%,
        #eef1e9 63%
        );

        background-size: 400% 100%;

        animation: skeletonShimmer 1.3s ease-in-out infinite;

        vertical-align: middle;
    }

    @keyframes skeletonShimmer {
        0% {
            background-position: 100% 0;
        }

        100% {
            background-position: 0 0;
        }
    }

    @media(max-width:1100px) {
        .stat-card-group {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .stat-item {
            min-height: 110px;
            padding: 16px;
        }
    }

    @media(max-width:575px) {
        .stat-card-group {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .stat-item {
            min-height: 100px;
            padding: 14px 16px;

            border-radius: 14px;

            gap: 12px;
        }

        .stat-icon-box {
            width: 46px;
            height: 46px;
            min-width: 46px;

            border-radius: 12px;

            font-size: 20px;
        }

        .stat-label {
            font-size: 10.5px;
        }

        .stat-desc {
            font-size: 10px;
        }
    }

    .ops-map-wrapper {
        background: #fef8f3;
        border: 1.5px solid #cdd9b5;
        border-radius: 16px;
        overflow: hidden;
        height: 580px;
        position: relative;
        z-index: 0;
        isolation: isolate;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
    }

    #map {
        height: 100%;
        width: 100%;
        z-index: 0;
    }

    #map.leaflet-container {
        height: 100% !important;
        width: 100% !important;
        background: #fef8f3 !important;
    }

    .ops-map-controls {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 500;
    }

    .ops-mode-group {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(6px);
        border: 1px solid #cdd9b5;
        border-radius: 8px;
        padding: 2px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .ops-mode-btn {
        border: none !important;
        border-radius: 6px !important;
        font-size: 11px !important;
        font-weight: 500 !important;
        padding: 4px 10px !important;
        color: #4b5563 !important;
        background: transparent !important;
        transition: all 0.15s ease !important;
        -webkit-tap-highlight-color: transparent;
        --bs-btn-color: #4b5563;
        --bs-btn-bg: transparent;
        --bs-btn-border-color: transparent;
        --bs-btn-hover-color: #1f422b;
        --bs-btn-hover-bg: transparent;
        --bs-btn-hover-border-color: transparent;
        --bs-btn-active-color: #1f422b;
        --bs-btn-active-bg: transparent;
        --bs-btn-active-border-color: transparent;
        --bs-btn-focus-shadow-rgb: 41, 87, 56;
    }

    .ops-mode-btn:not(.active):hover {
        color: #1f422b !important;
        background: #edf3e4 !important;
    }

    .ops-mode-btn.active {
        background: #295738 !important;
        color: #ffffff !important;
    }

    .ops-floating-card {
        position: absolute;
        top: 10px;
        left: 10px;
        z-index: 500;
        background: rgba(254, 248, 243, 0.95);
        backdrop-filter: blur(6px);
        border: 1px solid #cdd9b5;
        border-radius: 10px;
        padding: 8px 12px;
        min-width: 200px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.07);
    }

    .ops-live-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 10px;
        font-weight: 700;
        color: #2e7d32;
        background: #e8f4ec;
        padding: 1px 6px;
        border-radius: 6px;
    }

    .ops-floating-select {
        border: none;
        background: transparent;
        font-size: 12px;
        font-weight: 600;
        color: #295738;
        cursor: pointer;
        outline: none;
        max-width: 140px;
    }

    .ops-floating-route {
        font-size: 11px;
        font-weight: 500;
        color: #4b5563;
    }

    .ops-floating-metrics {
        display: flex;
        align-items: center;
        font-size: 11px;
        color: #4b5563;
    }

    .ops-map-footer {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(254, 248, 243, 0.95);
        backdrop-filter: blur(6px);
        border-top: 1px solid #cdd9b5;
        padding: 6px 12px;
        z-index: 999;
    }

    .ops-legend-bar {
        font-size: 11px;
        font-weight: 500;
        color: #2e4836;
    }

    .ops-legend-item {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .legend-bus-icon {
        font-size: 13px;
        line-height: 1;
        color: #295738;
    }

    .legend-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }
    .legend-dot.completed { background: #2e4836; }
    .legend-dot.current { background: #cdd9b5; border: 1.5px solid #2e4836; }
    .legend-dot.upcoming { background: transparent; border: 1px solid #9ca3af; }

    .legend-line {
        display: inline-block;
        width: 16px;
        height: 3px;
        border-radius: 1px;
    }
    .legend-line.traveled { background: #2e4836; }
    .legend-line.remaining {
        background: repeating-linear-gradient(90deg, #2e4836, #2e4836 3px, transparent 3px, transparent 6px);
    }

    .map-provider-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 10px;
        font-weight: 500;
        color: #4b5563;
        background: #f9f4da;
        border: 1px solid #e5e0a4;
        padding: 2px 6px;
        border-radius: 6px;
    }

    .ops-details-card {
        background: #ffffff;
        border: 1.5px solid #cdd9b5;
        border-radius: 16px;
        padding: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .ops-status-badge {
        font-size: 10px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        letter-spacing: 0.3px;
    }
    .ops-status-badge.in-progress { background: #e8f4ec; color: #2e7d32; border: 1px solid #c2e0cb; }
    .ops-status-badge.scheduled { background: #fef9e7; color: #b45309; border: 1px solid #fde68a; }
    .ops-status-badge.completed { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }

    .ops-gps-badge {
        font-size: 11px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .ops-gps-badge.online { color: #2e7d32; }
    .ops-gps-badge.delayed { color: #b45309; }
    .ops-gps-badge.offline { color: #9ca3af; }

    .ops-gps-dot {
        display: inline-block;
        font-size: 10px;
    }
    .ops-gps-dot.online { color: #2e7d32; }
    .ops-gps-dot.delayed { color: #b45309; }
    .ops-gps-dot.offline { color: #9ca3af; }

    .ops-inspector-badge {
        font-size: 11px;
        font-weight: 600;
    }
    .ops-inspector-badge.on-board { color: #2e7d32; }
    .ops-inspector-badge.assigned { color: #4b5563; }
    .ops-inspector-badge.not-assigned { color: #8a9a8a; }

    .ops-progress-bar-sm {
        height: 6px;
        background: #e8e6d9;
        border-radius: 4px;
        overflow: hidden;
    }
    .ops-progress-bar-sm .progress-bar {
        background: #295738;
        border-radius: 4px;
        transition: width 0.3s ease;
    }

    .ops-progress-bar-xs {
        height: 4px;
        background: #e8e6d9;
        border-radius: 3px;
        overflow: hidden;
    }
    .ops-progress-bar-xs .progress-bar {
        background: #295738;
        border-radius: 3px;
        transition: width 0.3s ease;
    }

    .ops-btn-primary {
        background: linear-gradient(135deg, #295738 0%, #1a3c24 100%) !important;
        color: #ffffff !important;
        border: 1.5px solid #14301d !important;
        border-radius: 12px;
        padding: 10px 18px;
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.25px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        box-shadow: 0 4px 14px rgba(41, 87, 56, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.22) !important;
        transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        cursor: pointer;
        text-decoration: none;
        width: 100%;
        -webkit-tap-highlight-color: transparent;
    }
    .ops-btn-primary:hover {
        background: linear-gradient(135deg, #2e6240 0%, #1e4529 100%) !important;
        color: #ffffff !important;
        transform: translateY(-2px);
        box-shadow: 0 8px 22px rgba(41, 87, 56, 0.38), inset 0 1px 0 rgba(255, 255, 255, 0.3) !important;
    }
    .ops-btn-primary:active {
        background: linear-gradient(135deg, #21482f 0%, #16311f 100%) !important;
        transform: translateY(0);
        box-shadow: 0 2px 6px rgba(41, 87, 56, 0.2) !important;
    }
    .ops-btn-primary:focus-visible {
        outline: 3px solid rgba(41, 87, 56, 0.4);
        outline-offset: 2px;
    }
    .ops-btn-icon-bubble {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        background: rgba(255, 255, 255, 0.18);
        border-radius: 8px;
        font-size: 13px;
        color: #ffffff;
        flex-shrink: 0;
    }
    .ops-btn-arrow {
        font-size: 15px;
        opacity: 0.9;
        transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease;
    }
    .ops-btn-primary:hover .ops-btn-arrow {
        transform: translateX(4px);
        opacity: 1;
    }

    .ops-btn-details {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        background: linear-gradient(180deg, #fbfdf9 0%, #edf3e4 100%) !important;
        color: #244b30 !important;
        border: 1.5px solid #bdd0a4 !important;
        border-radius: 8px;
        padding: 5px 12px;
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: 0.2px;
        line-height: 1.2;
        box-shadow: 0 1px 3px rgba(41, 87, 56, 0.08), inset 0 1px 0 #ffffff;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        cursor: pointer;
        text-decoration: none;
        white-space: nowrap;
        -webkit-tap-highlight-color: transparent;
    }
    .ops-btn-details i {
        font-size: 12.5px;
        color: #295738;
        transition: all 0.18s ease;
    }
    .ops-btn-details:hover {
        background: linear-gradient(180deg, #2a5a3a 0%, #1d4329 100%) !important;
        color: #ffffff !important;
        border-color: #1a3d25 !important;
        transform: translateY(-1.5px);
        box-shadow: 0 4px 10px rgba(41, 87, 56, 0.28);
    }
    .ops-btn-details:hover i {
        color: #ffffff !important;
        transform: scale(1.1);
    }
    .ops-btn-details:active {
        background: linear-gradient(180deg, #245033 0%, #1a3d25 100%) !important;
        color: #ffffff !important;
        border-color: #1a3d25 !important;
        transform: translateY(0);
        box-shadow: 0 1px 3px rgba(41, 87, 56, 0.15);
    }
    .ops-btn-details:focus-visible {
        outline: 3px solid rgba(41, 87, 56, 0.4);
        outline-offset: 2px;
    }

    .ops-btn-secondary {
        background: linear-gradient(180deg, #fbfdf9 0%, #edf3e4 100%);
        color: #244b30;
        border: 1.5px solid #bdd0a4;
        border-radius: 9px;
        padding: 6px 14px;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), inset 0 1px 0 #ffffff;
        transition: all 0.2s ease;
        cursor: pointer;
        text-decoration: none;
        -webkit-tap-highlight-color: transparent;
        --bs-btn-color: #244b30;
        --bs-btn-bg: transparent;
        --bs-btn-border-color: #bdd0a4;
        --bs-btn-hover-color: #ffffff;
        --bs-btn-hover-bg: #295738;
        --bs-btn-hover-border-color: #295738;
        --bs-btn-active-color: #ffffff;
        --bs-btn-active-bg: #21482f;
        --bs-btn-active-border-color: #21482f;
        --bs-btn-focus-shadow-rgb: 41, 87, 56;
    }
    .ops-btn-secondary:hover {
        background: #295738;
        color: #ffffff !important;
        border-color: #295738;
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(41, 87, 56, 0.2);
    }
    .ops-btn-secondary:hover i {
        color: #ffffff;
    }
    .ops-btn-secondary:active,
    .ops-btn-secondary:focus {
        background: #21482f;
        color: #ffffff !important;
        border-color: #21482f;
        transform: translateY(0);
        box-shadow: 0 2px 6px rgba(41, 87, 56, 0.22);
    }
    .ops-btn-secondary:active i,
    .ops-btn-secondary:focus i {
        color: #ffffff;
    }
    .ops-btn-secondary:focus:not(:focus-visible) {
        outline: none;
    }


    .ops-fleet-card, .ops-activity-card {
        background: #ffffff;
        border: 1.5px solid #cdd9b5;
        border-radius: 16px;
        padding: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        height: 100%;
        display: flex;
        flex-direction: column;
        box-sizing: border-box;
    }

    @media(min-width:1200px) {
        #fleet-activity-section {
            align-items: stretch !important;
        }
        #fleet-activity-section > [class*="col-"] {
            display: flex !important;
            flex-direction: column !important;
        }
        .ops-fleet-card, .ops-activity-card {
            height: 100% !important;
            min-height: 460px;
            max-height: 480px;
        }
    }

    @media(max-width:1199.98px) {
        .ops-fleet-card, .ops-activity-card {
            min-height: 380px;
            max-height: 440px;
            height: 400px;
        }
    }

    .ops-tabs-group {
        display: inline-flex;
        background: #f9f4da;
        border: 1.5px solid #cdd9b5;
        border-radius: 999px;
        padding: 3px;
        gap: 3px;
    }

    .ops-tab-btn {
        background: transparent;
        border: none;
        border-radius: 999px;
        padding: 5px 14px;
        font-size: 11.5px;
        font-weight: 600;
        color: #295738;
        transition: all 0.18s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
    }

    .ops-tab-btn:hover {
        background: #efe9c9;
        color: #1f422b;
    }

    .ops-tab-btn:active {
        background: #e8e3bd;
        color: #1f422b;
    }

    .ops-tab-btn:focus-visible {
        outline: 2px solid #295738;
        outline-offset: 2px;
    }

    .ops-tab-btn.active {
        background: #295738;
        color: #ffffff;
        box-shadow: 0 2px 6px rgba(41, 87, 56, 0.25);
    }

    .ops-tab-badge {
        background: rgba(255, 255, 255, 0.25);
        color: #ffffff;
        font-size: 10px;
        font-weight: 700;
        border-radius: 999px;
        padding: 1px 7px;
    }

    .ops-tab-btn:not(.active) .ops-tab-badge {
        background: #e8e3bd;
        color: #295738;
    }

    .ops-filter-bar {
        background: linear-gradient(180deg, #fcfaf4 0%, #f5efe2 100%);
        border: 1.5px solid #d9d4bd;
        border-radius: 14px;
        padding: 9px 12px;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 2px 6px rgba(46, 72, 54, 0.04);
    }

    .ops-search-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        min-width: 200px;
        background: #ffffff;
        border: 1.5px solid #cdd9b5;
        border-radius: 10px;
        height: 38px;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(46, 72, 54, 0.04), inset 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .ops-search-wrapper:focus-within {
        border-color: #295738;
        box-shadow: 0 0 0 3px rgba(41, 87, 56, 0.14), inset 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .ops-search-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        background: #edf3e4;
        border-radius: 7px;
        color: #295738;
        font-size: 12px;
        margin-left: 6px;
        margin-right: 2px;
        flex-shrink: 0;
    }

    .ops-search-input {
        border: none;
        outline: none;
        background: transparent;
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 500;
        color: #2e4836;
        width: 100%;
        height: 100%;
    }

    .ops-search-input::placeholder {
        color: #7d8b80;
        font-size: 11.5px;
    }

    .ops-search-clear {
        border: none;
        background: transparent;
        color: #9ca3af;
        padding: 0 10px;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        transition: color 0.15s ease;
        -webkit-tap-highlight-color: transparent;
    }

    .ops-search-clear:hover {
        color: #dc2626;
    }

    .ops-search-clear:focus-visible {
        outline: 2px solid #295738;
        outline-offset: -2px;
        border-radius: 6px;
    }

    .ops-filter-item {
        display: flex;
        align-items: center;
    }

    .ops-filter-group {
        display: flex;
        align-items: center;
        background: #ffffff;
        border: 1.5px solid #cdd9b5;
        border-radius: 10px;
        height: 38px;
        padding-left: 6px;
        padding-right: 6px;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(46, 72, 54, 0.04);
    }

    .ops-filter-group:hover,
    .ops-filter-group:focus-within {
        border-color: #295738;
        box-shadow: 0 0 0 3px rgba(41, 87, 56, 0.12);
    }

    .ops-filter-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        background: #edf3e4;
        border-radius: 6px;
        color: #295738;
        font-size: 11.5px;
        margin-right: 4px;
        flex-shrink: 0;
    }

    .ops-select {
        border: none;
        outline: none;
        background: transparent;
        padding: 6px 22px 6px 4px;
        font-size: 12px;
        font-weight: 600;
        color: #244b30;
        height: 100%;
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%23295738' stroke-width='2.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 6px center;
    }

    .ops-filter-reset-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #ffffff;
        color: #556358;
        border: 1.5px solid #cdd9b5;
        border-radius: 10px;
        height: 38px;
        padding: 0 14px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        white-space: nowrap;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        -webkit-tap-highlight-color: transparent;
    }

    .ops-filter-reset-btn i {
        font-size: 13px;
        transition: transform 0.25s ease;
    }

    .ops-filter-reset-btn:hover {
        border-color: #295738;
        color: #295738;
        background: #fbfdf9;
    }

    .ops-filter-reset-btn:hover i {
        transform: rotate(-90deg);
    }

    .ops-filter-reset-btn:active {
        border-color: #295738;
        color: #295738;
        background: #edf3e4;
        box-shadow: inset 0 1px 3px rgba(41, 87, 56, 0.12);
    }

    .ops-filter-reset-btn:focus-visible {
        outline: 2px solid #295738;
        outline-offset: 2px;
    }

    .ops-filter-reset-btn.active {
        background: linear-gradient(135deg, #295738 0%, #1e4529 100%);
        color: #ffffff !important;
        border-color: #163620;
        box-shadow: 0 3px 8px rgba(41, 87, 56, 0.28);
    }

    .ops-filter-reset-btn.active i {
        color: #ffffff;
    }

    .ops-table-container {
        flex: 1 1 0px;
        min-height: 0;
        max-height: none;
        overflow-y: auto;
    }

    .ops-table {
        font-size: 12px;
    }

    .ops-table thead th {
        background: #f9f8f0;
        color: #295738;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 9px 12px;
        border-bottom: 2px solid #cdd9b5;
        position: sticky;
        top: 0;
        z-index: 2;
    }

    .ops-table tbody td {
        padding: 10px 12px;
        border-bottom: 1px solid #f0eee2;
        color: #2e4836;
    }

    .ops-table tbody tr {
        cursor: pointer;
        transition: background 0.15s ease;
    }

    .ops-table tbody tr:hover {
        background: #faf7eb;
    }

    .ops-table tbody tr.selected-row {
        background: #eef5e7 !important;
        box-shadow: inset 3px 0 0 #295738;
    }

    .ops-timeline-container {
        position: relative;
        padding: 4px 6px 4px 10px;
        flex: 1 1 0px;
        min-height: 0;
        max-height: none;
        overflow-y: auto;
    }

    .ops-timeline-item {
        position: relative;
        padding-left: 18px;
        padding-bottom: 11px;
        transition: background 0.12s ease;
        border-radius: 6px;
    }

    .ops-timeline-item:hover {
        background: #fafaf5;
    }

    .ops-timeline-item.active-bus-activity {
        background: #f4f8ee !important;
        border-left: 3px solid #295738 !important;
        padding-left: 14px;
        border-radius: 6px;
        margin-bottom: 4px;
        padding-top: 4px;
        padding-bottom: 6px;
        box-shadow: 0 1px 3px rgba(41, 87, 56, 0.08);
    }

    .ops-activity-footer {
        font-size: 11px;
        color: #4b5563;
        font-weight: 500;
        background: #fafaf5;
        border-radius: 8px;
        padding: 6px 10px;
    }

    .ops-timeline-item:last-child {
        padding-bottom: 2px;
    }

    .ops-timeline-node {
        position: absolute;
        left: 0;
        top: 4px;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #2e7d32;
        border: 1.5px solid #ffffff;
        box-shadow: 0 0 0 1.5px #c2e0cb;
        z-index: 2;
    }
    .ops-timeline-node.boarding { background: #295738; }
    .ops-timeline-node.dropoff { background: #e67e22; box-shadow: 0 0 0 1.5px #fed7aa; }
    .ops-timeline-node.inspector { background: #b45309; box-shadow: 0 0 0 1.5px #fde68a; }
    .ops-timeline-node.departure { background: #1e40af; box-shadow: 0 0 0 1.5px #bfdbfe; }
    .ops-timeline-node.transit { background: #0d9488; box-shadow: 0 0 0 1.5px #99f6e4; }
    .ops-timeline-node.inspection { background: #d97706; box-shadow: 0 0 0 1.5px #fde68a; }

    .ops-timeline-line {
        position: absolute;
        left: 3px;
        top: 13px;
        bottom: -3px;
        width: 1.5px;
        background: #e8e6d9;
        z-index: 1;
    }

    .ops-timeline-item:last-child .ops-timeline-line {
        display: none;
    }

    .ops-timeline-time {
        font-size: 10px;
        font-weight: 500;
        color: #8a9a8a;
        display: block;
        line-height: 1;
        margin-bottom: 2px;
    }

    .ops-timeline-title {
        font-size: 12px;
        font-weight: 600;
        color: #295738;
        line-height: 1.3;
        margin-bottom: 1px;
    }

    .ops-timeline-desc {
        font-size: 11px;
        color: #6b7280;
        line-height: 1.3;
    }

    .ops-overview-card {
        background: #ffffff;
        border: 1.5px solid #cdd9b5;
        border-radius: 16px;
        padding: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
    }

    .ops-progress-percent {
        font-size: 14px;
        font-weight: 700;
        color: #295738;
    }

    .ops-overview-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        background: #fafaf5;
        border: 1px solid #e8e6d9;
        border-radius: 10px;
        padding: 10px 14px;
    }

    .ops-overview-stat-col {
        display: flex;
        flex-direction: column;
    }

    .leaflet-bus-icon {
        background: transparent;
        border: none;
    }

    .custom-bus-popup .leaflet-popup-content-wrapper {
        border-radius: 14px;
        padding: 0;
        overflow: hidden;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
    }

    .custom-bus-popup .leaflet-popup-content {
        margin: 0;
        line-height: 1.4;
    }

    ::-webkit-scrollbar {
        width: 5px;
        height: 5px;
    }
    ::-webkit-scrollbar-track {
        background: #fdfaf6;
    }
    ::-webkit-scrollbar-thumb {
        background: #cdd9b5;
        border-radius: 4px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: #2e4836;
    }

    @media(max-width:991px) {
        .ops-map-wrapper {
            height: 440px;
        }
        .ops-overview-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media(max-width:575px) {
        .ops-overview-stats-grid {
            grid-template-columns: 1fr;
        }
    }

    .gm-err-container, .gm-err-content, .gm-err-message, .gm-err-title, .gm-style-pbc, .dismissButton {
        display: none !important;
        visibility: hidden !important;
        pointer-events: none !important;
        opacity: 0 !important;
    }
</style>