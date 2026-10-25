<?php
use App\auth\Auth;
use App\Core\Database;

$user = Auth::user();
$userName = $user['username'] ?? 'User';
$userRole = $user['role'] ?? 'Role';

$profilePhoto = '';
try {
    $stmt = Database::get()->prepare("SELECT profile_photo FROM users WHERE id = ?");
    $stmt->execute([(int)($user['id'] ?? 0)]);
    $photoRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $profilePhoto = !empty($photoRow['profile_photo']) ? '/uploads/' . $photoRow['profile_photo'] : '';
} catch (\Throwable $e) { }

$initials = '';
$nameParts = explode(' ', trim($userName));
foreach ($nameParts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

$uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$currentRoute = basename(dirname($uriPath));

$token = $_GET['token'] ?? $token ?? '';
$adminUrl = function (string $page) use ($token): string {
    return $token ? "/admin/$page/$token" : "/admin/views/$page/index";
};
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Bus Dashboard</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/chart.js">        </script>
        <link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
        <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
        <link rel="stylesheet" href="/admin/assets/css/navbaradmin.css?v=<?= time() ?>">
<link rel="stylesheet" href="/assets/css/site-transitions.css">
<style>
    .sidebar .nav-link, .sidebar .nav-link:visited, .sidebar .nav-link:focus { font-weight: 500 !important; font-size: 0.875rem !important; color: #333 !important; text-decoration: none !important; }
    .sidebar .nav-link i { color: #6f6d75 !important; }
    :root { --accent-gradient: linear-gradient(135deg, rgba(26, 107, 60, 0.15) 0%, rgba(45, 138, 78, 0.12) 50%, rgba(61, 163, 93, 0.08) 100%); --sidebar-width: 280px; }
    .nav-link.active { background: linear-gradient(135deg, rgba(26, 107, 60, 0.15) 0%, rgba(45, 138, 78, 0.12) 50%, rgba(61, 163, 93, 0.08) 100%) !important; backdrop-filter: blur(8px) !important; -webkit-backdrop-filter: blur(8px) !important; color: #1a6b3c !important; border: 1px solid rgba(26, 107, 60, 0.2) !important; box-shadow: 0 4px 16px rgba(26, 107, 60, 0.12) !important; animation: glassPulse 3s ease-in-out infinite !important; }
    .nav-link.active i { color: #1a6b3c !important; }
    .sidebar-footer a, .sidebar-footer a:visited, .sidebar-footer a:focus { color: #6f6d75 !important; text-decoration: none !important; }
    .sidebar-footer-link { display: flex !important; align-items: center !important; padding: 8px 10px !important; font-size: 0.8rem !important; font-weight: 500 !important; border-radius: 0.5rem !important; margin-bottom: 2px !important; }
    .sidebar-footer-link:hover { background: #f0efe0 !important; color: #333 !important; }
    .sidebar-footer-link.active { background: linear-gradient(135deg, #1a6b3c 0%, #2d8a4e 50%, #3da35d 100%) !important; color: #fff !important; font-weight: 600 !important; }
    .sidebar-nav { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 0 12px 12px; }
    .sidebar-nav::-webkit-scrollbar { width: 5px; } .sidebar-nav::-webkit-scrollbar-track { background: transparent; } .sidebar-nav::-webkit-scrollbar-thumb { background: linear-gradient(180deg, rgba(26, 107, 60, 0.25), rgba(61, 163, 93, 0.15)); border-radius: 10px; }
    .sidebar-footer { flex-shrink: 0; padding: 10px 16px 14px; background: linear-gradient(180deg, #f8f6ea 0%, #f0ede0 100%); border-top: 2px solid rgba(26, 107, 60, 0.15); }
    .section-group { background: rgba(255, 255, 255, 0.55); border: 1px solid rgba(0, 0, 0, 0.06); border-radius: 14px; padding: 10px 8px; margin-bottom: 14px; }
    @keyframes glassPulse { 0%, 100% { box-shadow: 0 4px 16px rgba(26, 107, 60, 0.12); border-color: rgba(26, 107, 60, 0.2); } 50% { box-shadow: 0 4px 24px rgba(26, 107, 60, 0.2); border-color: rgba(26, 107, 60, 0.3); } }
    body { animation: fadeIn 0.3s ease forwards; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>
</head>
<body>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="d-flex align-items-center mb-1">
                <img src="/assets/images/passenger/logoR.png" alt="R-Connect" class="sidebar-logo" width="40" height="40">
                <span class="sidebar-brand">R-Connect</span>
            </div>
            <div class="sidebar-subtitle">Transportation Management System</div>
        </div>

        <div class="sidebar-nav" id="navLinks">

            <div class="section-group">
                <ul class="nav nav-pills flex-column">
                    <li class="section-title">
                        <i class="bi bi-house-door-fill"></i> Main
                    </li>
                    <li class="nav-item">
                        <a href="<?= $adminUrl('dashboard') ?>"
                           class="nav-link <?= ($currentRoute === 'dashboard') ? 'active' : ''; ?>"
                           data-tooltip="Dashboard">
                            <i class="bi bi-speedometer2 icon-when-collapsed"></i><span class="nav-label">Dashboard</span>
                        </a>
                    </li>
                </ul>

                <ul class="nav nav-pills flex-column">
                    <li class="section-title">
                        <i class="bi bi-activity"></i> Monitoring
                    </li>
                    <li class="nav-item">
                        <a href="<?= $adminUrl('trip-dashboard') ?>"
                           class="nav-link <?= ($currentRoute === 'trip-dashboard') ? 'active' : ''; ?>"
                           data-tooltip="Trips">
                            <i class="bi bi-map-fill icon-when-collapsed"></i><span class="nav-label">Trips</span>
                        </a>
                    </li>
                </ul>

                <ul class="nav nav-pills flex-column">
                    <li class="section-title">
                        <i class="bi bi-megaphone-fill"></i> Announcements
                    </li>
                    <li class="nav-item">
                        <a href="<?= $adminUrl('announcement') ?>"
                           class="nav-link <?= ($currentRoute === 'announcement') ? 'active' : ''; ?>"
                           data-tooltip="Announcements">
                            <i class="bi bi-megaphone-fill icon-when-collapsed"></i><span class="nav-label">Announcements</span>
                        </a>
                    </li>
                </ul>

                <?php if ($userRole === 'super_admin'): ?>
                <ul class="nav nav-pills flex-column">
                    <li class="section-title">
                        <i class="bi bi-folder-fill"></i> Documents
                    </li>
                    <li class="nav-item">
                        <a href="<?= $adminUrl('driver-performance-analytics') ?>"
                           class="nav-link <?= ($currentRoute === 'driver-performance-analytics') ? 'active' : ''; ?>"
                           data-tooltip="Driver Performance">
                            <i class="bi bi-graph-up-arrow icon-when-collapsed"></i><span class="nav-label">Driver Performance</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $adminUrl('reports') ?>"
                           class="nav-link <?= ($currentRoute === 'reports') ? 'active' : ''; ?>"
                           data-tooltip="Reports">
                            <i class="bi bi-file-earmark-bar-graph-fill icon-when-collapsed"></i><span class="nav-label">Reports</span>
                        </a>
                    </li>
                </ul>
                <?php endif; ?>
            </div>

        <div class="section-group">
            <ul class="nav nav-pills flex-column">
                <li class="section-title">
                    <i class="bi bi-gear-fill"></i> System Management
                </li>

                <?php
$roleModules = ['drivers', 'inspectors', 'finance', 'admin', 'role-management'];
$isRoleActive = in_array($currentRoute, $roleModules);
                ?>

                <li class="nav-item">
                    <a href="<?= $adminUrl('role-management') ?>"
                       class="nav-link <?= $isRoleActive ? 'active' : ''; ?>"
                       data-tooltip="Role Management">
                        <i class="bi bi-people-fill icon-when-collapsed"></i><span class="nav-label">Role Management</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= $adminUrl('passenger') ?>"
                       class="nav-link <?= ($currentRoute === 'passenger') ? 'active' : ''; ?>"
                       data-tooltip="Passenger Management">
                        <i class="bi bi-people-fill<?= $userRole === 'super_admin' ? ' icon-when-collapsed' : '' ?>"></i><span class="nav-label">Passenger Management</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= $adminUrl('bus') ?>"
                       class="nav-link <?= ($currentRoute === 'bus') ? 'active' : ''; ?>"
                       data-tooltip="Bus Management">
                        <i class="bi bi-bus-front-fill<?= $userRole === 'super_admin' ? ' icon-when-collapsed' : '' ?>"></i><span class="nav-label">Bus Management</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= $adminUrl('dispatch-queue') ?>"
                       class="nav-link <?= ($currentRoute === 'dispatch-queue') ? 'active' : ''; ?>"
                       data-tooltip="Dispatch Queue">
                        <i class="bi bi-list-ol<?= $userRole === 'super_admin' ? ' icon-when-collapsed' : '' ?>"></i><span class="nav-label">Dispatch Queue</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= $adminUrl('system-configuration') ?>"
                       class="nav-link <?= ($currentRoute === 'system-configuration') ? 'active' : ''; ?>"
                       data-tooltip="System Configuration">
                        <i class="bi bi-sliders2 icon-when-collapsed"></i><span class="nav-label">System Configuration</span>
                    </a>
                </li>

                <?php if ($userRole === 'super_admin'): ?>
                <li class="nav-item">
                    <a href="<?= $adminUrl('system') ?>"
                       class="nav-link <?= ($currentRoute === 'system') ? 'active' : ''; ?>"
                       data-tooltip="System Logs">
                        <i class="bi bi-journal-text icon-when-collapsed"></i><span class="nav-label">System Logs</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            </div>

            </div>

            <div class="sidebar-footer">
                <hr>
                <a href="<?= $adminUrl('settings') ?>" class="sidebar-footer-link <?= ($currentRoute === 'settings') ? 'active' : ''; ?>" data-tooltip="Settings">
                    <i class="bi bi-sliders me-2"></i><span class="nav-label">Settings</span>
                </a>
                <a href="<?= $adminUrl('profile') ?>" class="sidebar-footer-link <?= ($currentRoute === 'profile') ? 'active' : ''; ?>" data-tooltip="Profile">
                    <i class="bi bi-person-circle me-2"></i><span class="nav-label">Profile</span>
                </a>
                <a href="javascript:void(0)" onclick="showPrivacyPolicy()" class="sidebar-footer-link" data-tooltip="Privacy Policy">
                    <i class="bi bi-shield-check me-2"></i><span class="nav-label">Privacy Policy</span>
                </a>
<small class="sidebar-footer-version">R-Connect TMS v1.0.0</small>
</div>
</div>

<div id="privacyModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.4);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:20px;max-width:640px;width:90%;max-height:80vh;overflow-y:auto;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,0.15);position:relative;">
        <button onclick="hidePrivacyPolicy()" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:1.5rem;cursor:pointer;color:#888;line-height:1;">&times;</button>
        <h2 style="color:#1a6b3c;font-weight:700;font-size:1.4rem;margin-bottom:4px;">Privacy Policy</h2>
        <div style="color:#888;font-size:0.8rem;margin-bottom:20px;">Last updated: July 8, 2026</div>
        <div style="font-size:0.85rem;line-height:1.7;color:#555;">
            <p>R-Connect Transportation Management System is committed to protecting your privacy.</p>
            <h4 style="color:#1a6b3c;font-weight:600;font-size:0.95rem;margin-top:20px;margin-bottom:6px;">1. Information We Collect</h4>
            <p>We collect personal data (name, email, contact number), usage data (login timestamps, trip records, activity logs), and device data (IP address, browser type) for security monitoring.</p>
            <h4 style="color:#1a6b3c;font-weight:600;font-size:0.95rem;margin-top:20px;margin-bottom:6px;">2. How We Use Your Information</h4>
            <p>To operate the system, authenticate users, generate reports, audit activity, and send notifications.</p>
            <h4 style="color:#1a6b3c;font-weight:600;font-size:0.95rem;margin-top:20px;margin-bottom:6px;">3. Data Sharing</h4>
            <p>We do not sell your data. Information is shared only with authorized personnel based on role-based access and with service providers under confidentiality agreements.</p>
            <h4 style="color:#1a6b3c;font-weight:600;font-size:0.95rem;margin-top:20px;margin-bottom:6px;">4. Data Security</h4>
            <p>We use encryption, secure session management, and regular audits to protect your data.</p>
            <h4 style="color:#1a6b3c;font-weight:600;font-size:0.95rem;margin-top:20px;margin-bottom:6px;">5. Contact</h4>
            <p>For questions, please contact your system administrator.</p>
        </div>
        <button onclick="hidePrivacyPolicy()" style="margin-top:20px;padding:10px 24px;background:#1a6b3c;color:#fff;border:none;border-radius:50px;font-weight:500;font-size:0.85rem;cursor:pointer;">Close</button>
    </div>
</div>

<div id="sidebarOverlay"></div>

<div class="header d-flex justify-content-between align-items-center px-3">
    <div class="d-flex align-items-center gap-2">
        <button class="hamburger" id="sidebarToggler" aria-label="Toggle sidebar navigation">
            <span></span><span></span><span></span>
        </button>
        <div style="position:relative;">
            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#999;font-size:0.85rem;pointer-events:none;"></i>
            <input type="text"
                   id="globalSearch"
                   class="form-control header-search"
                   placeholder="Search notifications...">
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
                        <img src="<?= $profilePhoto ?>?t=<?= time() ?>" style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid #cdd9b5;flex-shrink:0;">
                    <?php else: ?>
                        <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                    <?php endif; ?>
                    <div>
                        <div class="profile-name"><?= htmlspecialchars($userName) ?></div>
<div class="profile-role"><?= htmlspecialchars($userRole) ?></div>
</div>
</div>
<hr>
<a href="<?= $adminUrl('profile') ?>"><i class="bi bi-person-circle me-2"></i> Profile</a>
<a href="<?= $adminUrl('settings') ?>"><i class="bi bi-gear me-2"></i> Settings</a>
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
    var sidebarNav = document.querySelector('.sidebar-nav');
    var isTouchOnly = window.matchMedia && window.matchMedia('(hover: none), (pointer: coarse)').matches;

    // One floating tooltip lives on <body>; position:fixed keeps it clear of
    // any sidebar overflow container and above other elements.
    var railTooltip = document.createElement('div');
    railTooltip.className = 'sidebar-tooltip';
    railTooltip.setAttribute('role', 'tooltip');
    railTooltip.setAttribute('aria-hidden', 'true');
    document.body.appendChild(railTooltip);

    function railActive() {
        return !!sidebar && !isTouchOnly && window.innerWidth > 992 && sidebar.classList.contains('collapsed');
    }

    function showRailTooltip(link) {
        if (!railActive()) return;
        var label = link.getAttribute('data-tooltip');
        if (!label) { var span = link.querySelector('.nav-label'); label = span ? span.textContent.trim() : ''; }
        if (!label) return;
        railTooltip.textContent = label;
        railTooltip.classList.add('show');
        railTooltip.setAttribute('aria-hidden', 'false');
        var rect = link.getBoundingClientRect();
        var railRight = sidebar.getBoundingClientRect().right;
        var top = rect.top + rect.height / 2;
        var tipHeight = railTooltip.offsetHeight;
        var minTop = 8 + tipHeight / 2;
        var maxTop = window.innerHeight - 8 - tipHeight / 2;
        if (top < minTop) top = minTop;
        if (top > maxTop) top = maxTop;
        railTooltip.style.left = (railRight + 10) + 'px';
        railTooltip.style.top = top + 'px';
    }

    function hideRailTooltip() {
        railTooltip.classList.remove('show');
        railTooltip.setAttribute('aria-hidden', 'true');
    }

    function setTogglerExpanded() {
        if (!toggler || !sidebar) return;
        var expanded = window.innerWidth <= 992 ? sidebar.classList.contains('show') : !sidebar.classList.contains('collapsed');
        toggler.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    document.querySelectorAll('.sidebar .nav-link[data-tooltip], .sidebar .sidebar-footer-link[data-tooltip]').forEach(function (link) {
        var label = link.getAttribute('data-tooltip');
        if (label && !link.getAttribute('aria-label')) link.setAttribute('aria-label', label);
        // Hover tooltips only where hover exists; focus tooltips cover keyboard use.
        if (!isTouchOnly) {
            link.addEventListener('mouseenter', function () { showRailTooltip(link); });
            link.addEventListener('mouseleave', hideRailTooltip);
            link.addEventListener('focus', function () { showRailTooltip(link); });
            link.addEventListener('blur', hideRailTooltip);
            link.addEventListener('click', hideRailTooltip);
        }
    });
    if (sidebarNav) sidebarNav.addEventListener('scroll', hideRailTooltip, { passive: true });
    window.addEventListener('resize', hideRailTooltip);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hideRailTooltip(); });
    setTogglerExpanded();

    if (toggler) {
        toggler.onclick = function() {
            hideRailTooltip();
            if (window.innerWidth <= 992) {
                sidebar.classList.remove('collapsed');
                if (header) header.classList.remove('sidebar-collapsed');
                if (main) main.classList.remove('sidebar-collapsed');
                sidebar.classList.toggle('show');
                if (overlay) overlay.classList.toggle('show');
            } else {
                sidebar.classList.remove('show');
                if (overlay) overlay.classList.remove('show');
                sidebar.classList.toggle('collapsed');
                if (header) header.classList.toggle('sidebar-collapsed');
                if (main) main.classList.toggle('sidebar-collapsed');
            }
            setTogglerExpanded();
        };
    }

    // In the icon rail the Role Management submenu is hidden; clicking it
    // expands the sidebar and opens the submenu instead of doing nothing.
    document.querySelectorAll('.sidebar .accordion-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function (e) {
            if (window.innerWidth > 992 && sidebar.classList.contains('collapsed')) {
                e.preventDefault();
                e.stopPropagation();
                sidebar.classList.remove('collapsed');
                if (header) header.classList.remove('sidebar-collapsed');
                if (main) main.classList.remove('sidebar-collapsed');
                hideRailTooltip();
                setTogglerExpanded();
                var panel = document.querySelector(toggle.getAttribute('data-bs-target') || '');
                if (panel && window.bootstrap && bootstrap.Collapse) {
                    bootstrap.Collapse.getOrCreateInstance(panel).show();
                }
            }
        });
    });

    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            hideRailTooltip();
            setTogglerExpanded();
        });
    }

    document.querySelectorAll('.sidebar .nav-link').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 992) {
                sidebar.classList.remove('show');
                if (overlay) overlay.classList.remove('show');
                hideRailTooltip();
                setTogglerExpanded();
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

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function notificationUrl(n) {
        var pages = {
            'announcement': 'announcement',
            'trip': 'trip-dashboard',
            'remittance': 'finances',
            'admin_user': 'admin',
            'finance_user': 'finance',
            'inspector_user': 'inspectors',
            'dispatcher_user': 'inspectors',
            'driver_user': 'drivers',
            'bus_problem': 'inspectors'
        };
        var page = pages[n.reference_type] || '';
        if (!page) return '#';
        var base = '<?= htmlspecialchars($token) ?>'
            ? '/admin/' + page + '/<?= htmlspecialchars($token) ?>'
            : '/admin/views/' + page + '/index';
        if (n.reference_type === 'trip' && n.reference_id) {
            base += (base.indexOf('?') === -1 ? '?' : '&')
                + 'open_trip=' + encodeURIComponent(n.reference_id);
        }
        if (n.reference_type === 'announcement' && n.reference_id) {
            base += (base.indexOf('?') === -1 ? '?' : '&')
                + 'open_announcement=' + encodeURIComponent(n.reference_id);
        }
        if (n.reference_type === 'bus_problem') {
            // Bus problems are managed in Inspector Management → Dispatcher Monitoring.
            base += (base.indexOf('?') === -1 ? '?' : '&')
                + 'monitoring=dispatcher';
        }
        if (['admin_user', 'finance_user', 'inspector_user', 'dispatcher_user', 'driver_user'].indexOf(n.reference_type) !== -1 && n.reference_id) {
            base += (base.indexOf('?') === -1 ? '?' : '&')
                + 'open_profile=' + encodeURIComponent(n.reference_id);
        }
        return base;
    }

    function markOneRead(id, url) {
        fetch('/api/notifications/mark-read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        }).then(function() {
            var item = document.querySelector('.notification-item[data-id="' + id + '"]');
            if (item) {
                item.style.opacity = '0.55';
                item.style.filter = 'grayscale(1)';
                item.setAttribute('aria-disabled', 'true');
            }
            if (notificationBadge) {
                var badgeCount = parseInt(notificationBadge.textContent || '0', 10);
                badgeCount = Math.max(0, badgeCount - 1);
                notificationBadge.textContent = badgeCount;
                notificationBadge.style.display = badgeCount > 0 ? '' : 'none';
            }
        }).catch(function() {})
          .finally(function() {
            if (url && url !== '#') {
                window.location.href = url;
            }
        });
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
                        var url = notificationUrl(n);
                        var readStyle = n.is_read ? 'opacity:.55;filter:grayscale(1);' : '';
                        return '<a href="' + url + '" class="notification-item search-item" data-id="' + n.id + '" style="display:block;text-decoration:none;padding:12px;border-radius:12px;background:' + bg + ';margin-bottom:10px;cursor:pointer;' + readStyle + '">' +
                            '<div style="font-weight:600;color:#1a6b3c;">' + escapeHtml(n.title) + '</div>' +
                            '<div style="font-size:.85rem;color:#555;margin-top:3px;">' + escapeHtml(n.description || '') + '</div>' +
                            '<div style="font-size:.75rem;color:#777;margin-top:6px;">' + relativeTime(n.created_at) + '</div>' +
                            '</a>';
                    }).join('');
                    notificationsList.querySelectorAll('.notification-item').forEach(function(item) {
                        item.addEventListener('click', function(e) {
                            e.preventDefault();
                            markOneRead(item.getAttribute('data-id'), item.getAttribute('href'));
                        });
                    });
                }
            })
            .catch(function() {});
    }

    if (markAllRead) {
        markAllRead.addEventListener('click', function() {
            fetch('/api/notifications/read-all.php', { method: 'POST' })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        fetchNotifications();
                    }
                })
                .catch(function() {});
        });
    }

    // initial load so the badge/list is ready without opening the bell
    fetchNotifications();

    // keep the badge/list fresh without a page refresh
    var pollInterval = setInterval(fetchNotifications, 10000);

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(pollInterval);
        } else {
            fetchNotifications();
            pollInterval = setInterval(fetchNotifications, 10000);
        }
    });

    // other scripts can trigger an immediate refresh (e.g. after adding a user)
    window.addEventListener('notifications-refresh', fetchNotifications);

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var value = this.value.toLowerCase();
            document.querySelectorAll('.search-item').forEach(function(item) {
                item.style.display = item.textContent.toLowerCase().includes(value) ? '' : 'none';
            });
        });
    }

    var accordionToggles = document.querySelectorAll('.accordion-toggle');
    accordionToggles.forEach(function(toggle) {
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

function showPrivacyPolicy() {
    document.getElementById('privacyModal').style.display = 'flex';
}
function hidePrivacyPolicy() {
    document.getElementById('privacyModal').style.display = 'none';
}
document.getElementById('privacyModal')?.addEventListener('click', function(e) {
    if (e.target === this) hidePrivacyPolicy();
});
</script>
<script src="/assets/js/site-transitions.js"></script>

</body>
</html>
<?php
?>
