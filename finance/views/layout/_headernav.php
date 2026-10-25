<?php

use App\Auth\Auth;
use App\Core\Database;

$user = Auth::user();
$userName = $user['username'] ?? 'User';
$rawRole = $user['role'] ?? '';
$roleMap = ['finance_manager' => 'Finance Manager', 'finance_officer' => 'Finance Officer'];
$userRole = $roleMap[$rawRole] ?? ucwords(str_replace('_', ' ', $rawRole)) ?: 'Role';

$profilePhoto = '';
try {
    $stmt = Database::get()->prepare("SELECT profile_photo FROM users WHERE id = ?");
    $stmt->execute([(int)($user['id'] ?? 0)]);
    $photoRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $profilePhoto = !empty($photoRow['profile_photo']) ? '/uploads/' . $photoRow['profile_photo'] : '';
} catch (\Throwable $e) {}

$initials = '';
$nameParts = explode(' ', trim($userName));
foreach ($nameParts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);
?>

<div class="header d-flex justify-content-between align-items-center px-3">

    <div class="d-flex align-items-center gap-2 header-left">
        <button class="hamburger" id="sidebarToggler" aria-label="Toggle sidebar navigation" aria-controls="sidebar">
            <span></span><span></span><span></span>
        </button>
        <div style="position:relative;" class="header-search-wrap">
            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#999;font-size:0.85rem;pointer-events:none;"></i>
            <input type="text" id="globalSearch" class="form-control header-search" placeholder="Search notifications...">
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">

        <div class="notification-wrapper position-relative">
            <div class="icon-container position-relative" id="notificationToggle" style="cursor:pointer;" role="button" aria-label="Toggle notifications">
                <i class="bi bi-bell"></i>
                <span id="notificationBadge" class="notification-badge" style="display:none;">0</span>
            </div>
            <div class="notification-dropdown" id="notificationDropdown">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0 fw-bold" style="color:#1a6b3c;">Notifications</h6>
                    <span id="markAllRead" class="mark-all-read">Mark all as read</span>
                </div>
                <div id="notificationsList" class="notifications-list">
                    <div class="notification-skeleton">
                        <div class="skeleton-line w-75"></div>
                        <div class="skeleton-line w-50 mt-2"></div>
                        <div class="skeleton-line w-25 mt-1"></div>
                    </div>
                </div>
                <div class="notification-empty" id="notificationEmpty" style="display:none;">
                    <i class="bi bi-inbox" style="font-size:2rem;color:#ccc;"></i>
                    <p class="mt-2 mb-0" style="color:#999;">No notifications</p>
                </div>
            </div>
        </div>

        <div class="profile-wrapper position-relative" id="profileWrapper">
            <div class="icon-container" id="profileToggle" style="cursor:pointer;padding:2px;" role="button" aria-label="Toggle profile menu">
                <?php if ($profilePhoto): ?>
                    <img src="<?= $profilePhoto ?>?t=<?= time() ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid #cdd9b5;">
                <?php else: ?>
                    <i class="bi bi-person"></i>
                <?php endif; ?>
            </div>
            <div class="profile-dropdown" id="profileDropdown">
                <div class="profile-info">
                    <?php if ($profilePhoto): ?>
                        <img src="<?= $profilePhoto ?>?t=<?= time() ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #cdd9b5;flex-shrink:0;">
                    <?php else: ?>
                        <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                    <?php endif; ?>
                    <div>
                        <div class="profile-name"><?= htmlspecialchars($userName) ?></div>
                        <div class="profile-role"><?= htmlspecialchars($userRole) ?></div>
                    </div>
                </div>
                <hr>
                <a href="/finance/profile/<?= $_GET['token'] ?>"><i class="bi bi-person-circle me-2"></i> Profile</a>
                <a href="/finance/settings/<?= $_GET['token'] ?>"><i class="bi bi-gear me-2"></i> Settings</a>
                <hr>
                <a href="/public/logout/logout.php" class="logout"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
            </div>
        </div>

    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.getElementById("sidebar");
    const header = document.querySelector(".header");
    const main = document.querySelector(".main-content");
    const overlay = document.getElementById("sidebarOverlay");

    const sidebarToggler = document.getElementById("sidebarToggler");
    const profileToggle = document.getElementById("profileToggle");
    const profileDropdown = document.getElementById("profileDropdown");

    const notificationToggle = document.getElementById("notificationToggle");
    const notificationDropdown = document.getElementById("notificationDropdown");

    const markAllRead = document.getElementById("markAllRead");
    const notificationBadge = document.getElementById("notificationBadge");
    const notificationsList = document.getElementById("notificationsList");
    const notificationEmpty = document.getElementById("notificationEmpty");

    const globalSearch = document.getElementById("globalSearch");

    const sidebarNav = document.querySelector(".sidebar-nav");
    const isTouchOnly =
        window.matchMedia && window.matchMedia("(hover: none), (pointer: coarse)").matches;

    const railTooltip = document.createElement("div");
    railTooltip.className = "sidebar-tooltip";
    railTooltip.setAttribute("role", "tooltip");
    railTooltip.setAttribute("aria-hidden", "true");
    document.body.appendChild(railTooltip);

    function railActive() {
        return (
            !!sidebar &&
            !isTouchOnly &&
            window.innerWidth > 992 &&
            sidebar.classList.contains("collapsed")
        );
    }

    function showRailTooltip(link) {
        if (!railActive()) return;
        let label = link.getAttribute("data-tooltip");
        if (!label) {
            const span = link.querySelector(".nav-label");
            label = span ? span.textContent.trim() : "";
        }
        if (!label) return;
        railTooltip.textContent = label;
        railTooltip.classList.add("show");
        railTooltip.setAttribute("aria-hidden", "false");
        const rect = link.getBoundingClientRect();
        const railRight = sidebar.getBoundingClientRect().right;
        let top = rect.top + rect.height / 2;
        const tipHeight = railTooltip.offsetHeight;
        const minTop = 8 + tipHeight / 2;
        const maxTop = window.innerHeight - 8 - tipHeight / 2;
        if (top < minTop) top = minTop;
        if (top > maxTop) top = maxTop;
        railTooltip.style.left = railRight + 10 + "px";
        railTooltip.style.top = top + "px";
    }

    function hideRailTooltip() {
        railTooltip.classList.remove("show");
        railTooltip.setAttribute("aria-hidden", "true");
    }

    function setTogglerExpanded() {
        if (!sidebarToggler || !sidebar) return;
        const expanded =
            window.innerWidth <= 992
                ? sidebar.classList.contains("show")
                : !sidebar.classList.contains("collapsed");
        sidebarToggler.setAttribute("aria-expanded", expanded ? "true" : "false");
    }

    function syncSidebarState() {
        if (!sidebar) return;
        const mobile = window.innerWidth <= 992;
        sidebar.classList.remove(mobile ? "collapsed" : "show");
        const collapsed = !mobile && sidebar.classList.contains("collapsed");
        if (header) header.classList.toggle("sidebar-collapsed", collapsed);
        if (main) main.classList.toggle("sidebar-collapsed", collapsed);
        if (overlay) overlay.classList.toggle("show", mobile && sidebar.classList.contains("show"));
        hideRailTooltip();
        setTogglerExpanded();
    }

    function closeMobileSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove("show");
        syncSidebarState();
    }

    document
        .querySelectorAll(
            ".sidebar .nav-link[data-tooltip], .sidebar .sidebar-footer-link[data-tooltip]",
        )
        .forEach(function (link) {
            const label = link.getAttribute("data-tooltip");
            if (label && !link.getAttribute("aria-label")) link.setAttribute("aria-label", label);
            if (!isTouchOnly) {
                link.addEventListener("mouseenter", function () {
                    showRailTooltip(link);
                });
                link.addEventListener("mouseleave", hideRailTooltip);
                link.addEventListener("focus", function () {
                    showRailTooltip(link);
                });
                link.addEventListener("blur", hideRailTooltip);
                link.addEventListener("click", hideRailTooltip);
            }
        });
    if (sidebarNav) sidebarNav.addEventListener("scroll", hideRailTooltip, { passive: true });
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") hideRailTooltip();
    });
    syncSidebarState();

    if (sidebarToggler && sidebar) {
        sidebarToggler.addEventListener("click", function () {
            sidebar.classList.toggle(window.innerWidth <= 992 ? "show" : "collapsed");
            syncSidebarState();
        });
    }

    if (overlay) {
        overlay.addEventListener("click", closeMobileSidebar);
    }

    document
        .querySelectorAll(".sidebar .nav-link, .sidebar .sidebar-footer-link")
        .forEach(function (link) {
            link.addEventListener("click", function () {
                if (window.innerWidth <= 992) {
                    closeMobileSidebar();
                }
            });
        });

    function hideAllDropdowns() {
        if (profileDropdown) profileDropdown.classList.remove("show-dropdown");
        if (notificationDropdown) notificationDropdown.classList.remove("show-dropdown");
    }

    if (profileToggle && profileDropdown) {
        profileToggle.addEventListener("click", function (e) {
            e.stopPropagation();
            if (notificationDropdown) notificationDropdown.classList.remove("show-dropdown");
            profileDropdown.classList.toggle("show-dropdown");
        });
    }

    if (notificationToggle && notificationDropdown) {
        notificationToggle.addEventListener("click", function (e) {
            e.stopPropagation();
            if (profileDropdown) profileDropdown.classList.remove("show-dropdown");
            var wasOpen = notificationDropdown.classList.contains("show-dropdown");
            notificationDropdown.classList.toggle("show-dropdown");
            if (!wasOpen) fetchNotifications();
        });
    }

    document.addEventListener("click", function (e) {
        hideAllDropdowns();
        if (window.innerWidth <= 992 && sidebar && sidebar.classList.contains("show")) {
            if (sidebar.contains(e.target) || (sidebarToggler && sidebarToggler.contains(e.target)))
                return;
            closeMobileSidebar();
        }
    });

    if (profileDropdown) {
        profileDropdown.addEventListener("click", function (e) {
            e.stopPropagation();
        });
    }
    if (notificationDropdown) {
        notificationDropdown.addEventListener("click", function (e) {
            e.stopPropagation();
        });
    }

    window.addEventListener("resize", syncSidebarState);

    if (markAllRead) {
        markAllRead.addEventListener("click", function () {
            fetch("/finance/api/notifications_api.php", {
                method: "POST",
                body: new URLSearchParams({ action: "mark_read" }),
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (res) {
                    if (res.success) {
                        if (notificationBadge) notificationBadge.style.display = "none";
                        if (notificationsList) notificationsList.innerHTML = "";
                        if (notificationEmpty) notificationEmpty.style.display = "";
                    }
                })
                .catch(function () {});
        });
    }

    if (globalSearch) {
        globalSearch.addEventListener("input", function () {
            const value = this.value.toLowerCase().trim();
            document.querySelectorAll(".search-item").forEach(function (item) {
                item.style.display = item.textContent.toLowerCase().includes(value) ? "" : "none";
            });
        });
    }

    function relativeTime(dateStr) {
        var now = new Date();
        var date = new Date(dateStr.replace(" ", "T") + "Z");
        var diffMs = now - date;
        var diffMin = Math.floor(diffMs / 60000);
        if (diffMin < 1) return "Just now";
        if (diffMin < 60) return diffMin + "m ago";
        var diffHr = Math.floor(diffMin / 60);
        if (diffHr < 24) return diffHr + "h ago";
        var diffDay = Math.floor(diffHr / 24);
        if (diffDay < 7) return diffDay + "d ago";
        return date.toLocaleDateString();
    }

    function escapeHtml(str) {
        var div = document.createElement("div");
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function fetchNotifications() {
        fetch("/finance/api/notifications_api.php")
            .then(function (r) {
                return r.json();
            })
            .then(function (res) {
                if (!res.success) return;
                var items = (res.data || []).filter(function (n) {
                    var t = ((n.title || "") + " " + (n.description || "")).toLowerCase();
                    return t.indexOf("ratelimit") === -1 && t.indexOf("rate limit") === -1;
                });
                var count = items.length;
                if (notificationBadge) {
                    notificationBadge.textContent = count;
                    notificationBadge.style.display = count > 0 ? "" : "none";
                }
                if (items.length === 0) {
                    if (notificationsList) notificationsList.innerHTML = "";
                    if (notificationEmpty) notificationEmpty.style.display = "";
                    return;
                }
                if (notificationEmpty) notificationEmpty.style.display = "none";
                if (notificationsList) {
                    notificationsList.innerHTML = items
                        .map(function (n) {
                            var bg =
                                n.type === "error"
                                    ? "#fce8e8"
                                    : n.type === "warning"
                                      ? "#fff3cd"
                                      : n.type === "success"
                                        ? "#d4edda"
                                        : "#eceed9";
                            return (
                                '<div class="notification-item search-item" style="padding:12px;border-radius:12px;background:' +
                                bg +
                                ';margin-bottom:10px;">' +
                                '<div style="font-weight:600;color:#1a6b3c;">' +
                                escapeHtml(n.title) +
                                "</div>" +
                                '<div style="font-size:.85rem;color:#555;margin-top:3px;">' +
                                escapeHtml(n.description || "") +
                                "</div>" +
                                '<div style="font-size:.75rem;color:#777;margin-top:6px;">' +
                                relativeTime(n.created_at) +
                                "</div></div>"
                            );
                        })
                        .join("");
                }
            })
            .catch(function () {});
    }

    var pollInterval = setInterval(function () {
        if (notificationDropdown && notificationDropdown.classList.contains("show-dropdown")) {
            fetchNotifications();
        }
    }, 30000);

    document.addEventListener("visibilitychange", function () {
        if (document.hidden) {
            clearInterval(pollInterval);
        } else {
            pollInterval = setInterval(function () {
                if (
                    notificationDropdown &&
                    notificationDropdown.classList.contains("show-dropdown")
                ) {
                    fetchNotifications();
                }
            }, 30000);
        }
    });
});
</script>
