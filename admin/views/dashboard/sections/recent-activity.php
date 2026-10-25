<div class="dash-panel" id="recentActivityPanel">
    <div class="dash-panel-head">
        <div>
            <h2 class="dash-panel-title"><i class="bi bi-clock-history"></i> Recent System Activity</h2>
            <p class="dash-panel-sub">Latest activity and login records</p>
        </div>
    </div>

    <div class="activity-list" id="recentActivityList">
        <div class="activity-loading">Loading recent activity...</div>
    </div>
</div>

<style>
    .activity-list {
        display: flex;
        flex-direction: column;
        gap: 6px;
        max-height: 320px;
        overflow-y: auto;
    }

    .activity-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        border-radius: 10px;
        border: 1px solid #eae3d4;
        background: #fef8f3;
    }

    .activity-avatar {
        width: 32px;
        height: 32px;
        min-width: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #cdd9b5;
        color: #2e4836;
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .activity-body {
        flex: 1;
        min-width: 0;
    }

    .activity-name {
        font-size: 0.78rem;
        font-weight: 700;
        color: #2e4836;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .activity-meta {
        font-size: 0.66rem;
        color: #8a9389;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .activity-time {
        font-size: 0.66rem;
        font-weight: 600;
        color: #6f7d6f;
        white-space: nowrap;
        text-align: right;
    }

    .activity-loading,
    .activity-empty {
        padding: 18px;
        text-align: center;
        font-size: 0.76rem;
        color: #8a9389;
    }

    .activity-empty i {
        font-size: 1.5rem;
        color: #8a9389;
    }

    .activity-empty.is-error i {
        color: #b3261e;
    }
</style>

<script>
    (function () {
        "use strict";

        var requestSeq = 0;

        function initials(name) {
            var parts = String(name || "").trim().split(/\s+/).filter(Boolean);

            if (parts.length === 0) {
                return "?";
            }

            return (parts[0].charAt(0) + (parts.length > 1 ? parts[parts.length - 1].charAt(0) : "")).toUpperCase();
        }

        function roleLabel(role) {
            if (!role) {
                return "";
            }

            return String(role)
            .replace(/_/g, " ")
            .replace(/\b\w/g, function (c) { return c.toUpperCase(); });
        }

        function formatAt(value) {
            if (!value) {
                return "";
            }

            var date = new Date(String(value).replace(" ", "T"));

            if (isNaN(date.getTime())) {
                return String(value);
            }

            return date.toLocaleString("en-US", {
                month: "short",
                day: "numeric",
                hour: "numeric",
                minute: "2-digit"
            });
        }

        function render(entries) {
            var list = document.getElementById("recentActivityList");
            if (!list) {
                return;
            }

            if (!entries.length) {
                list.innerHTML = '<div class="activity-empty"><i class="bi bi-inbox"></i>No recent activity recorded.</div>';
                return;
            }

            list.innerHTML = "";

            entries.forEach(function (row) {
                var wrap = document.createElement("div");
                wrap.className = "activity-item";

                var avatar = document.createElement("div");
                avatar.className = "activity-avatar";
                avatar.textContent = initials(row.name);

                var body = document.createElement("div");
                body.className = "activity-body";

                var name = document.createElement("div");
                name.className = "activity-name";
                name.textContent = row.name || "Unknown user";

                var meta = document.createElement("div");
                meta.className = "activity-meta";
                meta.textContent = [roleLabel(row.role), row.activity].filter(Boolean).join(" • ");

                body.appendChild(name);
                body.appendChild(meta);

                var time = document.createElement("div");
                time.className = "activity-time";
                time.textContent = formatAt(row.at);

                wrap.appendChild(avatar);
                wrap.appendChild(body);
                wrap.appendChild(time);
                list.appendChild(wrap);
            });
        }

        function renderError() {
            var list = document.getElementById("recentActivityList");
            if (list) {
                list.innerHTML = '<div class="activity-empty is-error"><i class="bi bi-exclamation-circle"></i>Couldn\'t load recent activity.</div>';
            }
        }

        function loadRecentActivity(query) {
            var requestId = ++requestSeq;

            if (window.__dashLoadBegin) {
                window.__dashLoadBegin();
            }

            fetch("/admin/api/dashboard/recent-activity.php?" + (query || ""), { cache: "no-store" })
            .then(function (res) { return res.json(); })
            .then(function (payload) {
                if (requestId !== requestSeq) {
                    return;
                }

                if (!payload || !payload.success) {
                    renderError();
                    return;
                }

                render(payload.entries || []);
            })
            .catch (function () {
                if (requestId !== requestSeq) {
                    return;
                }
                renderError();
            })
            .finally(function () {
                if (window.__dashLoadEnd) {
                    window.__dashLoadEnd();
                }
            });
        }

        window.loadRecentActivity = loadRecentActivity;

        document.addEventListener("DOMContentLoaded", function () {
            if (!window.adminFilterQuery) {
                loadRecentActivity();
            }
        });
    })();
</script>
