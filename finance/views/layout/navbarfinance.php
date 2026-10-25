<?php
use App\auth\Auth;

if (!Auth::check()) {
    $portalKey = file_exists($_SERVER['DOCUMENT_ROOT'] . '/public/config/portal_key.php')
        ? require $_SERVER['DOCUMENT_ROOT'] . '/public/config/portal_key.php'
        : '';
    if ($portalKey) {
        $secret = bin2hex(random_bytes(8));
        header("Location: /$portalKey?_=$secret&portal=$portalKey");
    } else {
        header("Location: /public/login/");
    }
    exit;
}

$user = Auth::user();
$userName = $user['username'] ?? 'User';
$userRole = $user['role'] ?? 'Role';

$initials = '';
$nameParts = explode(' ', trim($userName));
foreach ($nameParts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

$routeParam = $_GET['route'] ?? '';
if ($routeParam) {
    $currentRoute = $routeParam;
} else {
    $uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $currentRoute = str_replace('.php', '', basename($uriPath));
}

$token = $_GET['token'] ?? $token ?? '';
$finUrl = function (string $page) use ($token): string {
    return $token ? "/finance/$page/$token" : "/finance/$page.php";
};

$portalKey = file_exists($_SERVER['DOCUMENT_ROOT'] . '/public/config/portal_key.php')
    ? require $_SERVER['DOCUMENT_ROOT'] . '/public/config/portal_key.php'
    : '';
$loginSecret = bin2hex(random_bytes(8));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<title>Finance Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
<link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
<link rel="stylesheet" href="/finance/assets/css/navbarfinance.css?v=<?= time() ?>">
<link rel="stylesheet" href="/assets/css/site-transitions.css">
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="d-flex align-items-center mb-1">
            <img src="/assets/images/passenger/logoR.png" alt="R-Connect" class="sidebar-logo" width="40" height="40">
            <span class="sidebar-brand">R-Connect</span>
        </div>
        <div class="sidebar-subtitle">Finance Management</div>
    </div>

    <div class="sidebar-nav" id="navLinks">

        <div class="section-group">
            <ul class="nav nav-pills flex-column">
                <li class="section-title">
                    <i class="bi bi-speedometer2"></i> Main
                </li>
                <li class="nav-item">
                    <a href="<?= $finUrl('dashboard') ?>"
                       class="nav-link <?= ($currentRoute === 'dashboard') ? 'active' : ''; ?>"
                       data-tooltip="Dashboard">
                       <i class="bi bi-speedometer2 icon-when-collapsed"></i><span class="nav-label">Dashboard</span>
                    </a>
                </li>
                <!-- Finance Analytics removed from navigation (kept routed for direct access).
                <li class="nav-item">
                    <a href="<?= $finUrl('analytics-dashboard') ?>"
                       class="nav-link <?= ($currentRoute === 'analytics-dashboard') ? 'active' : ''; ?>"
                       data-tooltip="Finance Analytics">
                       <i class="bi bi-bar-chart-line-fill icon-when-collapsed"></i><span class="nav-label">Finance Analytics</span>
                    </a>
                </li>
                -->
                <li class="nav-item">
                    <a href="<?= $finUrl('predictive-analysis') ?>"
                       class="nav-link <?= ($currentRoute === 'predictive-analysis') ? 'active' : ''; ?>"
                       data-tooltip="Predictive Analysis">
                       <i class="bi bi-graph-up-arrow icon-when-collapsed"></i><span class="nav-label">Predictive Analysis</span>
                    </a>
                </li>
            </ul>

            <ul class="nav nav-pills flex-column">
                <li class="section-title">
                    <i class="bi bi-cash-stack"></i> Transactions
                </li>
                <li class="nav-item">
                    <a href="<?= $finUrl('remittance') ?>"
                       class="nav-link <?= ($currentRoute === 'remittance') ? 'active' : ''; ?>"
                       data-tooltip="Remittance">
                       <i class="bi bi-cash-stack icon-when-collapsed"></i><span class="nav-label">Remittance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $finUrl('discrepancies') ?>"
                       class="nav-link <?= ($currentRoute === 'discrepancies') ? 'active' : ''; ?>"
                       data-tooltip="Discrepancies">
                       <i class="bi bi-exclamation-triangle-fill icon-when-collapsed"></i><span class="nav-label">Discrepancies</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $finUrl('history') ?>"
                       class="nav-link <?= ($currentRoute === 'history') ? 'active' : ''; ?>"
                       data-tooltip="Remittance History">
                       <i class="bi bi-clock-history icon-when-collapsed"></i><span class="nav-label">Remittance History</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="section-group">
            <ul class="nav nav-pills flex-column">
                <li class="section-title">
                    <i class="bi bi-graph-up-arrow"></i> Financials
                </li>
                <li class="nav-item">
                    <a href="<?= $finUrl('financial-intelligence') ?>"
                       class="nav-link <?= ($currentRoute === 'financial-intelligence') ? 'active' : ''; ?>"
                       data-tooltip="Financial Intelligence">
                       <i class="bi bi-clipboard-data-fill icon-when-collapsed"></i><span class="nav-label">Financial Intelligence</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $finUrl('fuel-cost-analytics') ?>"
                       class="nav-link <?= ($currentRoute === 'fuel-cost-analytics') ? 'active' : ''; ?>"
                       data-tooltip="Fuel Cost">
                       <i class="bi bi-fuel-pump-fill icon-when-collapsed"></i><span class="nav-label">Fuel Cost</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $finUrl('financial-reports') ?>"
                       class="nav-link <?= ($currentRoute === 'financial-reports') ? 'active' : ''; ?>"
                       data-tooltip="Financial Reports">
                       <i class="bi bi-file-earmark-text-fill icon-when-collapsed"></i><span class="nav-label">Financial Reports</span>
                    </a>
                </li>
            </ul>

            <ul class="nav nav-pills flex-column">
                <li class="section-title">
                    <i class="bi bi-link-45deg"></i> Quick Links
                </li>
                <li class="nav-item">
                    <a href="<?= $finUrl('exports-audits') ?>"
                       class="nav-link <?= ($currentRoute === 'exports-audits') ? 'active' : ''; ?>"
                       data-tooltip="Exports and Audits">
                       <i class="bi bi-file-earmark-arrow-down-fill icon-when-collapsed"></i><span class="nav-label">Exports and Audits</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $finUrl('documentation') ?>"
                       class="nav-link <?= ($currentRoute === 'documentation') ? 'active' : ''; ?>"
                       data-tooltip="Documentation">
                       <i class="bi bi-journal-text icon-when-collapsed"></i><span class="nav-label">Documentation</span>
                    </a>
                </li>
            </ul>
        </div>

    </div>

    <div class="sidebar-footer">
        <hr>
        <a href="<?= $finUrl('financial-cost-settings') ?>" class="sidebar-footer-link <?= in_array($currentRoute, ['financial-cost-settings', 'financial_cost_settings'], true) ? 'active' : ''; ?>" data-tooltip="Financial Cost Settings">
            <i class="bi bi-calculator-fill me-2 icon-when-collapsed"></i><span class="nav-label">Financial Cost Settings</span>
        </a>
        <a href="<?= $finUrl('profile') ?>" class="sidebar-footer-link" data-tooltip="Profile">
            <i class="bi bi-person-circle-fill me-2 icon-when-collapsed"></i><span class="nav-label">Profile</span>
        </a>
        <a href="javascript:void(0)" onclick="showPrivacyPolicy()" class="sidebar-footer-link" data-tooltip="Privacy Policy">
            <i class="bi bi-shield-check me-2 icon-when-collapsed"></i><span class="nav-label">Privacy Policy</span>
        </a>
        <a href="/public/logout/logout.php?_=<?= $loginSecret ?>&portal=<?= $portalKey ?>" class="sidebar-footer-link logout" data-tooltip="Logout">
            <i class="bi bi-box-arrow-right me-2 icon-when-collapsed"></i><span class="nav-label">Logout</span>
        </a>
        <small class="sidebar-footer-version">R-Connect TMS v1.0.0</small>
    </div>
</div>

<div id="sidebarOverlay"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function () {
    if (document.cookie.indexOf("rconnect_logout=1") !== -1) {
        document.cookie = "rconnect_logout=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
        window.location.replace(window.location.href);
        return;
    }
    if (window.performance && window.performance.navigation.type === 2) {
        window.location.replace(window.location.href);
    }
})();
window.addEventListener("pageshow", function (event) {
    if (event.persisted) {
        window.location.replace(window.location.href);
    }
});
window.addEventListener("unload", function () {});
</script>

<script>
/* Financial Cost Settings is a footer link, so enforce its active state from the
   real URL on every load. This keeps it green ONLY on the Financial Cost Settings
   page (and corrects any stale/restored render). */
(function () {
    function syncCostSettingsActive() {
        var link = document.querySelector('.sidebar-footer-link[data-tooltip="Financial Cost Settings"]');
        if (!link) {
            return;
        }
        var path = (window.location.pathname || "").toLowerCase();
        if (/financial[-_]cost[-_]settings/.test(path)) {
            link.classList.add("active");
        } else {
            link.classList.remove("active");
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", syncCostSettingsActive);
    } else {
        syncCostSettingsActive();
    }
    window.addEventListener("pageshow", syncCostSettingsActive);
})();
</script>

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

<div id="helpModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.4);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:20px;max-width:500px;width:90%;max-height:80vh;overflow-y:auto;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,0.15);position:relative;">
        <button onclick="hideHelpSupport()" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:1.5rem;cursor:pointer;color:#888;line-height:1;">&times;</button>
        <div style="text-align:center;margin-bottom:20px;">
            <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#1a6b3c,#2d8a4e);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                <i class="bi bi-question-circle" style="font-size:26px;color:#fff;"></i>
            </div>
            <h2 style="color:#1a6b3c;font-weight:700;font-size:1.3rem;margin:0;">Help & Support</h2>
            <p style="color:#888;font-size:0.85rem;margin-top:4px;">We're here to help you</p>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <div style="background:#f8f6ea;border-radius:14px;padding:16px;border:1px solid rgba(26,107,60,0.12);">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#1a6b3c,#2d8a4e);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-envelope" style="font-size:16px;color:#fff;"></i>
                    </div>
                    <div>
                        <div style="font-weight:600;color:#1a6b3c;font-size:0.9rem;">Email Support</div>
                        <div style="font-size:0.8rem;color:#555;">support@rconnecttms.online</div>
                    </div>
                </div>
            </div>
            <div style="background:#f8f6ea;border-radius:14px;padding:16px;border:1px solid rgba(26,107,60,0.12);">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#1a6b3c,#2d8a4e);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-telephone" style="font-size:16px;color:#fff;"></i>
                    </div>
                    <div>
                        <div style="font-weight:600;color:#1a6b3c;font-size:0.9rem;">Phone Support</div>
                        <div style="font-size:0.8rem;color:#555;">(044) 123-4567</div>
                    </div>
                </div>
            </div>
            <div style="background:#f8f6ea;border-radius:14px;padding:16px;border:1px solid rgba(26,107,60,0.12);">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#1a6b3c,#2d8a4e);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-clock" style="font-size:16px;color:#fff;"></i>
                    </div>
                    <div>
                        <div style="font-weight:600;color:#1a6b3c;font-size:0.9rem;">Operating Hours</div>
                        <div style="font-size:0.8rem;color:#555;">Monday - Saturday, 6:00 AM - 8:00 PM</div>
                    </div>
                </div>
            </div>
            <div style="background:#f8f6ea;border-radius:14px;padding:16px;border:1px solid rgba(26,107,60,0.12);">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#1a6b3c,#2d8a4e);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-globe" style="font-size:16px;color:#fff;"></i>
                    </div>
                    <div>
                        <div style="font-weight:600;color:#1a6b3c;font-size:0.9rem;">Website</div>
                        <div style="font-size:0.8rem;color:#555;">rconnecttms.online</div>
                    </div>
                </div>
            </div>
        </div>
        <button onclick="hideHelpSupport()" style="margin-top:20px;width:100%;padding:12px;background:#1a6b3c;color:#fff;border:none;border-radius:50px;font-weight:600;font-size:0.85rem;cursor:pointer;">Close</button>
    </div>
</div>

<script>
function showPrivacyPolicy() {
    document.getElementById("privacyModal").style.display = "flex";
}
function hidePrivacyPolicy() {
    document.getElementById("privacyModal").style.display = "none";
}
document.getElementById("privacyModal")?.addEventListener("click", function (e) {
    if (e.target === this) hidePrivacyPolicy();
});

function showHelpSupport() {
    document.getElementById("helpModal").style.display = "flex";
}
function hideHelpSupport() {
    document.getElementById("helpModal").style.display = "none";
}
document.getElementById("helpModal")?.addEventListener("click", function (e) {
    if (e.target === this) hideHelpSupport();
});
</script>
<script src="/assets/js/site-transitions.js"></script>


