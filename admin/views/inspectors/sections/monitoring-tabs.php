<div class="um-section" id="unifiedMonitoringSection">
    <style>
        .um-section { font-family: 'Poppins', sans-serif; margin-top: 24px; }
        .um-section h2, .um-section h3, .um-section p { font-family: 'Poppins', sans-serif; }
        .um-panel { background: #fffdf8; border: 1px solid #e5e0a4; border-radius: 17px; padding: 1.1rem 1.25rem; box-shadow: 0 3px 10px rgba(0, 0, 0, .04); }
        .um-bar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
        .um-toggle { display: inline-flex; background: #f1ecdd; border: 1px solid #cdd9b5; border-radius: 10px; padding: 3px; gap: 3px; flex-wrap: wrap; max-width: 100%; }
        .um-tab-btn { border: none; background: transparent; color: #2e4836; font-family: 'Poppins', sans-serif; font-size: .76rem; font-weight: 600; padding: 7px 14px; border-radius: 8px; cursor: pointer; }
        .um-tab-btn.is-active { background: #295738; color: #fff; }
        .um-meta { display: flex; align-items: center; gap: 10px; font-size: .72rem; color: #8a9a8a; }
        .um-tab-panel[hidden] { display: none; }
    </style>

    <div class="um-panel">
        <div class="um-bar">
            <div class="um-toggle" role="tablist" aria-label="Monitoring view">
                <button type="button" class="um-tab-btn is-active" id="umTabInspectorBtn" data-tab="inspector" role="tab" aria-selected="true">
                    <i class="bi bi-clipboard2-check"></i> Inspector Monitoring
                </button>
                <button type="button" class="um-tab-btn" id="umTabDispatcherBtn" data-tab="dispatcher" role="tab" aria-selected="false">
                    <i class="bi bi-broadcast"></i> Dispatcher Monitoring
                </button>
            </div>
            <div class="um-meta">
                <span>Last updated <strong id="umUpdated">—</strong></span>
                <button type="button" id="umRefresh" class="dm-btn dm-btn-ghost" style="border:1px solid #cdd9b5;background:#f9f4da;color:#2e4836;border-radius:8px;font-size:.72rem;font-weight:600;padding:6px 12px;cursor:pointer;"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
            </div>
        </div>

        <div class="um-tab-panel" id="umTabInspector" role="tabpanel">
        <?php
$inspectorMonitoringSection = ROOT_PATH . '/admin/views/inspectors/sections/inspector-monitoring.php';
if (is_file($inspectorMonitoringSection)) {
    require $inspectorMonitoringSection;
} else {
    error_log('[inspectors] Missing section: ' . $inspectorMonitoringSection);
    echo '<div class="dm-empty" style="padding:18px;text-align:center;color:#8a9389;">Inspector Monitoring is unavailable.</div>';
}
        ?>
    </div>

    <div class="um-tab-panel" id="umTabDispatcher" role="tabpanel" hidden>
        <?php
$dispatcherMonitoringSection = ROOT_PATH . '/admin/views/inspectors/sections/dispatcher-monitoring.php';
if (is_file($dispatcherMonitoringSection)) {
    require $dispatcherMonitoringSection;
} else {
    error_log('[inspectors] Missing section: ' . $dispatcherMonitoringSection);
    echo '<div class="dm-empty" style="padding:18px;text-align:center;color:#8a9389;">Dispatcher Monitoring is unavailable.</div>';
}
        ?>
    </div>
    </div>

    <script>
        (function () {
            "use strict";

            function $(id) { return document.getElementById(id); }

            var params = new URLSearchParams(window.location.search);
            var requested = (params.get("monitoring") || "").toLowerCase();
            var tab = "inspector";
            if (requested === "dispatcher" || requested === "inspector") {
                tab = requested;
            } else if (params.get("dispatcher_discrepancy") || params.get("dispatcher_trip")) {
                tab = "dispatcher";
            } else if (params.get("inspector_item")) {
                tab = "inspector";
            }

            window.umActiveTab = tab;
            window.umIsTabActive = function (name) {
                return window.umActiveTab === name;
            };
            window.umSetUpdated = function (stamp) {
                var el = $("umUpdated");
                if (el && stamp) {
                    el.textContent = stamp;
                }
            };

            function applyTab(next, updateUrl) {
                window.umActiveTab = next;
                var inspectorPanel = $("umTabInspector");
                var dispatcherPanel = $("umTabDispatcher");
                if (inspectorPanel) inspectorPanel.hidden = next !== "inspector";
                if (dispatcherPanel) dispatcherPanel.hidden = next !== "dispatcher";

                var buttons = document.querySelectorAll(".um-tab-btn");
                buttons.forEach(function (btn) {
                    var active = btn.dataset.tab === next;
                    btn.classList.toggle("is-active", active);
                    btn.setAttribute("aria-selected", active ? "true" : "false");
                });

                if (updateUrl) {
                    var url = new URL(window.location.href);
                    url.searchParams.set("monitoring", next);
                    window.history.replaceState({}, "", url.toString());
                }

                if (window.umLoaders && typeof window.umLoaders[next] === "function") {
                    window.umLoaders[next]();
                }
                window.dispatchEvent(new CustomEvent("um:tab", { detail: { tab: next } }));
            }

            document.addEventListener("DOMContentLoaded", function () {
                if (!$("unifiedMonitoringSection")) return;
                applyTab(tab, false);
                document.querySelectorAll(".um-tab-btn").forEach(function (btn) {
                    btn.addEventListener("click", function () {
                        applyTab(btn.dataset.tab, true);
                    });
                });
                var refresh = $("umRefresh");
                if (refresh) {
                    refresh.addEventListener("click", function () {
                        if (window.umLoaders && typeof window.umLoaders[window.umActiveTab] === "function") {
                            window.umLoaders[window.umActiveTab]();
                        }
                    });
                }
            });
        })();
    </script>
    </div>
