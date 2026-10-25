<?php header('Cache-Control: no-store, no-cache, must-revalidate'); header('Pragma: no-cache'); ?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<?php require_once __DIR__ . '/../layout/navbarfinance.php'; ?>
<?php
$finDashPartial = function (string $file, bool $placeholder = true) use ($finUrl): void {
    $path = __DIR__ . '/' . $file;

    if (!is_file($path)) {
        error_log('[finance-dashboard] Missing partial: finance/views/dashboard/' . $file);

        if ($placeholder) {
            echo '<div class="fin-missing"><i class="bi bi-exclamation-circle"></i><p>This section is temporarily unavailable.</p></div>';
        }

        return;
    }

    include $path;
};
?>


<style>
#finLoadingOverlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 999999;
    background: #fdfbf6;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.25s ease;
}
#finLoadingOverlay.fin-hide {
    opacity: 0;
    pointer-events: none;
}
.fin-loader {
    text-align: center;
}
.fin-spinner {
    width: 46px;
    height: 46px;
    margin: 0 auto 14px;
    border: 4px solid #ece7d8;
    border-top-color: #2f6f4f;
    border-radius: 50%;
    animation: finSpin 0.8s linear infinite;
}
@keyframes finSpin {
    to {
        transform: rotate(360deg);
    }
}
.fin-loader-text {
    font-size: 0.95rem;
    font-weight: 600;
    color: #2f4f2f;
}
.fin-loader-sub {
    font-size: 0.75rem;
    color: #94a3b8;
    margin-top: 4px;
}
</style>
<div id="finLoadingOverlay">
    <div class="fin-loader">
        <div class="fin-spinner"></div>
        <div class="fin-loader-text">Loading dashboard...</div>
        <div class="fin-loader-sub">Preparing all data...</div>
    </div>
</div>
<script>
(function () {
    window.__dashLoads = 0;
    window.__dashStart = 0;

    window.__dashLoadBegin = function () {
        window.__dashLoads = (window.__dashLoads || 0) + 1;
        if (!window.__dashStart) window.__dashStart = Date.now();
    };

    window.__dashLoadEnd = function () {
        window.__dashLoads = Math.max(0, (window.__dashLoads || 0) - 1);
        if (window.__dashLoads > 0) return;
        var wait = Math.max(0, 500 - (Date.now() - window.__dashStart));
        setTimeout(window.__dashHide, wait);
    };

    window.__dashHide = function () {
        var ov = document.getElementById("finLoadingOverlay");
        if (!ov) return;
        ov.classList.add("fin-hide");
        setTimeout(function () {
            ov.style.display = "none";
        }, 280);
    };

    setTimeout(window.__dashHide, 15000);
})();
</script>

<div class="main-content" id="mainContent" 
     style="font-family:'Poppins', sans-serif; background:#eceed9; padding:20px;">

    
    <div
        style="
            background:#fef8f3;
            border:1px solid #aaa8b1;
            border-radius:24px;
            padding:24px;
        "
    >

        <style>
        .fin-dash-panel {
            background: #fdfbf6;
            border: 1px solid #e3eabb;
            border-radius: 16px;
            padding: 16px 18px;
            height: 100%;
        }
        @media (max-width: 575px) {
            .fin-dash-panel {
                padding: 13px 14px;
            }
        }
        .fin-missing {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 120px;
            padding: 24px 16px;
            text-align: center;
            color: #8a9389;
            font-size: 0.8rem;
            font-weight: 500;
            background: #fdfbf6;
            border: 1px dashed #e3eabb;
            border-radius: 14px;
        }
        .fin-missing i {
            font-size: 1.35rem;
            color: #b0b9a8;
        }
        .fin-missing p {
            margin: 0;
        }
        </style>

        
        <?php require_once __DIR__ . '/../layout/_headernav.php'; ?>

        <?php $finDashPartial('_header.php'); ?>
        <?php $finDashPartial('_stats.php'); ?>

        
        <div class="row g-3 mb-4">
            <div class="col-12 d-flex">
                <div class="fin-dash-panel w-100"><?php $finDashPartial('_driverperformance.php'); ?></div>
            </div>
        </div>

        
        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-7 d-flex">
                <div class="fin-dash-panel w-100"><?php $finDashPartial('_financialtrends.php'); ?></div>
            </div>
            <div class="col-12 col-lg-5 d-flex">
                <div class="fin-dash-panel w-100"><?php $finDashPartial('_financialvariance.php'); ?></div>
            </div>
        </div>

        
        <div class="row g-3 mb-4">
            <div class="col-12 d-flex">
                <div class="fin-dash-panel w-100"><?php $finDashPartial('_fuelconsumption.php'); ?></div>
            </div>
        </div>

        
        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-5 d-flex">
                <div class="fin-dash-panel w-100"><?php $finDashPartial('_remittancestatus.php'); ?></div>
            </div>
            <div class="col-12 col-lg-7 d-flex">
                <div class="fin-dash-panel w-100"><?php $finDashPartial('_recentremittances.php'); ?></div>
            </div>
        </div>

        <script>
        window.__aiPageData = window.__aiPageData || {};
        window.__aiPageFilters = window.__aiPageFilters || {};
        window.__setAiPageData = function (key, value) {
            if (value !== undefined && value !== null) {
                var arr = Array.isArray(value) ? value : [value];
                var filtered = arr.filter(function (x) {
                    return x !== null && x !== undefined;
                });
                window.__aiPageData[key] = filtered;
            }
        };
        window.__setAiPageFilters = function (filters) {
            if (filters && typeof filters === "object") {
                Object.keys(filters).forEach(function (k) {
                    if (filters[k] !== "" && filters[k] !== null && filters[k] !== undefined) {
                        window.__aiPageFilters[k] = filters[k];
                    }
                });
            }
        };
        window.__showChartNoData = function (canvasId, message) {
            var canvas = document.getElementById(canvasId);
            if (!canvas) return;
            var wrap = canvas.parentElement;
            if (!wrap) return;
            if (!wrap.style.position || wrap.style.position === "static") {
                wrap.style.position = "relative";
            }
            var overlayId = canvasId + "_noDataOverlay";
            var overlay = document.getElementById(overlayId);
            if (!overlay) {
                overlay = document.createElement("div");
                overlay.id = overlayId;
                overlay.style.position = "absolute";
                overlay.style.top = "0";
                overlay.style.left = "0";
                overlay.style.right = "0";
                overlay.style.bottom = "0";
                overlay.style.display = "flex";
                overlay.style.alignItems = "center";
                overlay.style.justifyContent = "center";
                overlay.style.background = "transparent";
                overlay.style.zIndex = "5";
                overlay.style.borderRadius = "8px";
                overlay.innerHTML =
                    '<span style="display:inline-flex;align-items:center;justify-content:center;width:100%;height:100%;color:#a3ae9f;font-size:0.78rem;font-weight:500;text-align:center;padding:0 12px;line-height:1.5;"></span>';
                wrap.appendChild(overlay);
            }
            var msg = overlay.querySelector("span");
            if (msg) msg.textContent = message || "No data available for the selected period.";
            canvas.style.display = "none";
        };
        window.__hideChartNoData = function (canvasId) {
            var canvas = document.getElementById(canvasId);
            if (!canvas) return;
            var wrap = canvas.parentElement;
            if (!wrap) return;
            var overlay = document.getElementById(canvasId + "_noDataOverlay");
            if (overlay) overlay.remove();
            canvas.style.display = "";
        };
        </script>

        <?php $finDashPartial('_aibutton.php', false); ?>


        

    </div>
    

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>