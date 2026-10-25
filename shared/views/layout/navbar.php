<?php
use App\auth\Auth;

$user = Auth::user();
$userName = $user['username'] ?? 'User';
$userRole = $user['role'] ?? 'Role';
$userId = $user['id'] ?? 0;

$initials = '';
$nameParts = explode(' ', trim($userName));
foreach ($nameParts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

$sidebarWidth = $navbarConfig['sidebar_width'] ?? 250;
$cssFile = $navbarConfig['css_file'] ?? '/admin/assets/css/navbaradmin.css';
$navLinks = $navbarConfig['nav_links'] ?? [];
$profileUrl = $navbarConfig['profile_url'] ?? '/admin/views/profile/index';
$settingsUrl = $navbarConfig['settings_url'] ?? '/admin/views/settings/index';
$section = $navbarConfig['section'] ?? 'admin';
$sidebarBottom = $navbarConfig['sidebar_bottom'] ?? '';

$uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$currentRoute = $section === 'admin' ? basename(dirname($uriPath)) : basename($uriPath);
?>
<link rel="stylesheet" href="<?= htmlspecialchars($cssFile) ?>">
<style>
.sidebar{position:fixed;top:0;left:0;height:100vh;width:<?= (int)$sidebarWidth ?>px;background:#f8f6ea;color:#333;display:flex;flex-direction:column;box-shadow:2px 0 8px rgba(0,0,0,0.06);z-index:999;overflow-y:auto;-ms-overflow-style:none;scrollbar-width:none;transition:transform .4s ease}
.sidebar.collapsed{transform:translateX(-<?= (int)$sidebarWidth+5 ?>px)}
#sidebarOverlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.35);z-index:998;opacity:0;transition:opacity .3s ease}
#sidebarOverlay.show{display:block;opacity:1}
.header{position:fixed;top:0;left:<?= (int)$sidebarWidth ?>px;right:0;height:60px;background:#fff;display:flex;justify-content:space-between;align-items:center;padding:0 20px;border-bottom:1px solid rgba(0,0,0,0.08);z-index:998;transition:left .4s ease,width .4s ease}
.header.sidebar-collapsed{left:0;width:100%}
.main-content{margin-left:<?= (int)$sidebarWidth ?>px;margin-top:60px;padding:20px;transition:margin-left .4s ease;min-height:calc(100vh - 60px)}
.main-content.sidebar-collapsed{margin-left:0}
.profile-dropdown,.notification-dropdown{display:none}
.profile-dropdown.show-dropdown{display:flex;position:absolute;top:55px;right:0;width:220px;background:#fff;border:1px solid #e0e0e0;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.1);flex-direction:column;padding:10px 0;z-index:999}
.notification-dropdown.show-dropdown{display:block;position:absolute;right:0;top:50px;width:380px;background:#fff;border:2px solid #e8f5e9;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,0.1);padding:18px;z-index:999}
@media(max-width:992px){.sidebar{transform:translateX(-<?= (int)$sidebarWidth ?>px)}.sidebar.show{transform:translateX(0)}.header{left:0}.main-content{margin-left:0;margin-top:70px}}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}}
</style>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header p-3 mb-4">
        <div class="d-flex align-items-center mb-1">
            <img src="/assets/images/passenger/logoR.png" alt="R-Connect" class="sidebar-logo" width="40" height="40">
            <span class="sidebar-brand">R-Connect</span>
        </div>
        <div class="sidebar-subtitle">Transportation Management System</div>
    </div>

    <ul class="nav nav-pills flex-column mb-auto" id="navLinks">
        <?php foreach ($navLinks as $link): ?>
            <?php if ($link['type'] === 'section'): ?>
                <li class="section-title">
                    <i class="bi bi-<?= htmlspecialchars($link['icon'] ?? 'circle') ?>"></i>
                    <?= htmlspecialchars($link['label']) ?>
                </li>
            <?php elseif ($link['type'] === 'divider'): ?>
                <hr>
            <?php elseif ($link['type'] === 'link'): ?>
                <?php if (!empty($link['children'])): ?>
                    <?php
                    $childRoutes = $link['children_routes'] ?? [];
                    $isChildActive = in_array($currentRoute, $childRoutes);
                    $accordionId = $link['accordion_id'] ?? ('collapse_' . uniqid());
                    ?>
                    <li class="nav-item">
                        <a href="javascript:void(0)"
                           class="nav-link d-flex justify-content-between align-items-center accordion-toggle <?= $isChildActive ? 'active' : '' ?>"
                           data-bs-toggle="collapse"
                           data-bs-target="#<?= $accordionId ?>"
                           aria-expanded="<?= $isChildActive ? 'true' : 'false' ?>">
                            <?php if (!empty($link['icon'])): ?><i class="bi bi-<?= htmlspecialchars($link['icon']) ?> me-2"></i><?php endif; ?>
                            <span><?= htmlspecialchars($link['label']) ?></span>
                            <i class="bi bi-chevron-down accordion-icon <?= $isChildActive ? 'rotated' : '' ?>"></i>
                        </a>
                        <div class="collapse <?= $isChildActive ? 'show' : '' ?>" id="<?= $accordionId ?>">
                            <ul class="nav flex-column collapse-inner ps-3">
                                <?php foreach ($link['children'] as $child): ?>
                                    <li>
                                        <a href="<?= htmlspecialchars($child['url']) ?>"
                                           class="nav-link <?= ($currentRoute === $child['route']) ? 'active' : '' ?>">
                                            <?php if (!empty($child['icon'])): ?><i class="bi bi-<?= htmlspecialchars($child['icon']) ?> me-2"></i><?php endif; ?>
                                            <?= htmlspecialchars($child['label']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars($link['url']) ?>"
                           class="nav-link <?= (isset($link['route']) && $currentRoute === $link['route']) ? 'active' : '' ?>">
                            <?php if (!empty($link['icon'])): ?><i class="bi bi-<?= htmlspecialchars($link['icon']) ?> me-2"></i><?php endif; ?>
                            <?= htmlspecialchars($link['label']) ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>

    <?php if (!empty($sidebarBottom)): ?>
        <hr style="opacity:.2;">
        <div class="sidebar-bottom">
            <?= $sidebarBottom ?>
        </div>
    <?php endif; ?>
</div>

<div id="sidebarOverlay"></div>

<div class="header d-flex justify-content-between align-items-center px-3">
    <div class="d-flex align-items-center gap-2">
        <button class="hamburger" id="sidebarToggler" aria-label="Toggle sidebar navigation">
            <span></span><span></span><span></span>
        </button>
        <input type="text" id="globalSearch" class="form-control header-search" placeholder="Search notifications..." style="width:300px;max-width:50vw;">
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
            <div class="icon-container" id="profileToggle" style="cursor:pointer;" role="button" aria-label="Toggle profile menu">
                <i class="bi bi-person"></i>
            </div>
            <div class="profile-dropdown" id="profileDropdown">
                <div class="profile-info">
                    <div class="profile-avatar"><?= $initials ?></div>
                    <div>
                        <div class="profile-name"><?= htmlspecialchars($userName) ?></div>
                        <div class="profile-role"><?= htmlspecialchars($userRole) ?></div>
                    </div>
                </div>
                <hr>
                <a href="<?= htmlspecialchars($profileUrl) ?>"><i class="bi bi-person-circle me-2"></i> Profile</a>
                <a href="<?= htmlspecialchars($settingsUrl) ?>"><i class="bi bi-gear me-2"></i> Settings</a>
                <hr>
                <a href="/public/logout/logout.php" class="logout"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var sidebar = document.getElementById('sidebar');
    var header = document.querySelector('.header');
    var main = document.querySelector('.main-content');
    var overlay = document.getElementById('sidebarOverlay');
    var profileToggle = document.getElementById('profileToggle');
    var profileDropdown = document.getElementById('profileDropdown');
    var notificationToggle = document.getElementById('notificationToggle');
    var notificationDropdown = document.getElementById('notificationDropdown');
    var markAllRead = document.getElementById('markAllRead');
    var notificationsList = document.getElementById('notificationsList');
    var notificationEmpty = document.getElementById('notificationEmpty');
    var notificationBadge = document.getElementById('notificationBadge');
    var searchInput = document.getElementById('globalSearch');

    var toggler = document.getElementById('sidebarToggler');
    if (toggler) {
        toggler.onclick = function() {
            if (window.innerWidth <= 992) {
                sidebar.classList.toggle('show');
                if (overlay) overlay.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                if (header) header.classList.toggle('sidebar-collapsed');
                if (main) main.classList.toggle('sidebar-collapsed');
            }
        };
    }

    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    document.querySelectorAll('.sidebar .nav-link').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 992) {
                sidebar.classList.remove('show');
                if (overlay) overlay.classList.remove('show');
            }
        });
    });

    function hideAllDropdowns() {
        if (profileDropdown) profileDropdown.classList.remove('show-dropdown');
        if (notificationDropdown) notificationDropdown.classList.remove('show-dropdown');
    }

    if (profileToggle && profileDropdown) {
        profileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            if (notificationDropdown) notificationDropdown.classList.remove('show-dropdown');
            profileDropdown.classList.toggle('show-dropdown');
        });
    }

    if (notificationToggle && notificationDropdown) {
        notificationToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            if (profileDropdown) profileDropdown.classList.remove('show-dropdown');
            var wasOpen = notificationDropdown.classList.contains('show-dropdown');
            notificationDropdown.classList.toggle('show-dropdown');
            if (!wasOpen) fetchNotifications();
        });
    }

    document.addEventListener('click', function(e) {
        hideAllDropdowns();
        if (window.innerWidth <= 992 && sidebar && sidebar.classList.contains('show')) {
            if (sidebar.contains(e.target) || (toggler && toggler.contains(e.target))) return;
            sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
        }
    });

    if (profileDropdown) {
        profileDropdown.addEventListener('click', function(e) { e.stopPropagation(); });
    }
    if (notificationDropdown) {
        notificationDropdown.addEventListener('click', function(e) { e.stopPropagation(); });
    }

    window.addEventListener('resize', function() {
        if (window.innerWidth > 992) {
            sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
        }
    });

    function relativeTime(dateStr) {
        var now = new Date();
        var date = new Date(dateStr.replace(' ', 'T') + 'Z');
        var diffMs = now - date;
        var diffMin = Math.floor(diffMs / 60000);
        if (diffMin < 1) return 'Just now';
        if (diffMin < 60) return diffMin + 'm ago';
        var diffHr = Math.floor(diffMin / 60);
        if (diffHr < 24) return diffHr + 'h ago';
        var diffDay = Math.floor(diffHr / 24);
        if (diffDay < 7) return diffDay + 'd ago';
        return date.toLocaleDateString();
    }

    function fetchNotifications() {
        fetch('/api/notifications.php')
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res.success) return;
                var items = res.data || [];
                var count = res.count || 0;
                if (notificationBadge) {
                    notificationBadge.textContent = count;
                    notificationBadge.style.display = count > 0 ? '' : 'none';
                }
                if (items.length === 0) {
                    if (notificationsList) notificationsList.innerHTML = '';
                    if (notificationEmpty) notificationEmpty.style.display = '';
                    return;
                }
                if (notificationEmpty) notificationEmpty.style.display = 'none';
                if (notificationsList) {
                    notificationsList.innerHTML = items.map(function(n) {
                        var bg = n.type === 'error' ? '#fce8e8' :
                                 n.type === 'warning' ? '#fff3cd' :
                                 n.type === 'success' ? '#d4edda' : '#eceed9';
                        return '<div class="notification-item search-item" style="padding:12px;border-radius:12px;background:' + bg + ';margin-bottom:10px;">' +
                            '<div style="font-weight:600;color:#1a6b3c;">' + escapeHtml(n.title) + '</div>' +
                            '<div style="font-size:.85rem;color:#555;margin-top:3px;">' + escapeHtml(n.description || '') + '</div>' +
                            '<div style="font-size:.75rem;color:#777;margin-top:6px;">' + relativeTime(n.created_at) + '</div>' +
                            '</div>';
                    }).join('');
                }
            })
            .catch(function() {});
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    if (markAllRead) {
        markAllRead.addEventListener('click', function() {
            fetch('/api/notifications/read-all.php', { method: 'POST' })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        if (notificationBadge) notificationBadge.style.display = 'none';
                        if (notificationsList) notificationsList.innerHTML = '';
                        if (notificationEmpty) notificationEmpty.style.display = '';
                    }
                })
                .catch(function() {});
        });
    }

    var pollInterval = setInterval(function() {
        if (notificationDropdown && notificationDropdown.classList.contains('show-dropdown')) {
            fetchNotifications();
        }
    }, 30000);

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(pollInterval);
        } else {
            pollInterval = setInterval(function() {
                if (notificationDropdown && notificationDropdown.classList.contains('show-dropdown')) {
                    fetchNotifications();
                }
            }, 30000);
        }
    });

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var value = this.value.toLowerCase();
            document.querySelectorAll('.search-item').forEach(function(item) {
                item.style.display = item.textContent.toLowerCase().includes(value) ? '' : 'none';
            });
        });
    }

    document.querySelectorAll('.accordion-toggle').forEach(function(toggle) {
        var icon = toggle.querySelector('.accordion-icon');
        var targetId = toggle.getAttribute('data-bs-target');
        if (!targetId) return;
        var target = document.querySelector(targetId);
        if (target && target.classList.contains('show') && icon) {
            icon.classList.add('rotated');
        }
        if (target) {
            toggle.addEventListener('click', function() {
                setTimeout(function() {
                    if (icon) icon.classList.toggle('rotated', target.classList.contains('show'));
                }, 150);
            });
        }
    });
});
</script>
