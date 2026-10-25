<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/utils/i18n.php';

// ✅ MOBILE-ONLY GATE  [RE-ENABLED — dispatcher is mobile only]
$mobilePassed = _isMobile();
dispatcher_log("MOBILE_CHECK: ua=" . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . " passed=" . ($mobilePassed ? 'true' : 'false'));
if (!$mobilePassed) {
    $blockedFile = __DIR__ . '/views/blocked.php';
    if (file_exists($blockedFile)) {
        require $blockedFile;
    } else {
        echo '<!DOCTYPE html><html><body><h2>Mobile Only</h2><p>Please access from a mobile device.</p></body></html>';
    }
    exit;
}

// ✅ OBFUSCATED URL ROUTER
$urls = require __DIR__ . '/utils/urls.php';
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// Strip base path to get the relative path
$relativePath = '';
if ($basePath && strpos($requestPath, $basePath) === 0) {
    $relativePath = substr($requestPath, strlen($basePath) + 1);
} else {
    $relativePath = ltrim(substr($requestPath, strlen($basePath)), '/');
}

// Fallback: if relative path is empty but dispatcher_route was passed via query string (from .htaccess)
if ($relativePath === '' && isset($_GET['dispatcher_route'])) {
    $relativePath = $_GET['dispatcher_route'];
}

dispatcher_log("ROUTE: uri={$_SERVER['REQUEST_URI']} basePath=$basePath relativePath=$relativePath queryRoute=" . ($_GET['dispatcher_route'] ?? 'none'));

// Check API prefix: /api_<hash>/<endpoint>
if (preg_match('#^api_([a-f0-9]{32})/(.+)$#', $relativePath, $m)) {
    $apiHash = $m[1];
    $apiEndpoint = $m[2];
    if ($apiHash === $urls['api']) {
        $apiFile = __DIR__ . '/api/' . $apiEndpoint . '.php';
        if (file_exists($apiFile)) {
            require $apiFile;
            exit;
        }
    }
    foreach ($urls['previous'] ?? [] as $prev) {
        if (isset($prev['api']) && $apiHash === $prev['api'] && $prev['expires_at'] >= time()) {
            $apiFile = __DIR__ . '/api/' . $apiEndpoint . '.php';
            if (file_exists($apiFile)) {
                require $apiFile;
                exit;
            }
        }
    }
    /* Unknown or expired API route: a long-lived page (e.g., a resumed mobile
       PWA) is calling a rotated hash. Answer with JSON so the client can
       recognize the stale app and reload itself onto the current URLs. */
    http_response_code(410);
    header("Content-Type: application/json");
    echo json_encode([
        'success' => false,
        'code' => 'stale_api_route',
        'message' => 'This app session is out of date. Reloading...',
    ]);
    exit;
}

// Check login hash
$matchedHash = 'none';
if ($relativePath === $urls['login']) {
    $matchedHash = 'login';
    require __DIR__ . '/login.php';
    exit;
}

// Check previous login hashes (grace period)
foreach ($urls['previous'] ?? [] as $prev) {
    if (isset($prev['login']) && $relativePath === $prev['login'] && $prev['expires_at'] >= time()) {
        $matchedHash = 'login_prev';
        require __DIR__ . '/login.php';
        exit;
    }
}

// Check dashboard hash
if ($relativePath === $urls['dashboard']) {
    $matchedHash = 'dashboard';
    $_GET['_dash'] = '1';
}

// Check previous dashboard hashes (grace period)
foreach ($urls['previous'] ?? [] as $prev) {
    if (isset($prev['dashboard']) && $relativePath === $prev['dashboard'] && $prev['expires_at'] >= time()) {
        $matchedHash = 'dashboard_prev';
        $_GET['_dash'] = '1';
        break;
    }
}

// If no hash matched and request is not the root index, 404
$isRoot = ($relativePath === '' || $relativePath === 'index.php');
if (!$isRoot && $matchedHash === 'none') {
    http_response_code(404);
    echo '<h2 style="text-align:center;margin-top:50px;font-family:sans-serif;">404 Not Found</h2>';
    exit;
}

dispatcher_log("MATCHED_HASH: $matchedHash");

// Initialize session via bootstrap
dispatcher_init_session();
// If the PHP session file was lost, silently rebuild it from the remember cookie
dispatcher_restore_session();

$route = $_GET['route'] ?? '';
$dash = $_GET['d'] ?? '';

// Not logged in → redirect to obfuscated login
if (!isset($_SESSION['inspector_id'])) {
    header("Location: /dispatcher/" . $urls['login']);
    exit;
}

// Generate dashboard secret if needed
if (!isset($_SESSION['dashboard_secret'])) {
    $_SESSION['dashboard_secret'] = bin2hex(random_bytes(8));
}

// CSRF token for state-changing requests (session-authenticated)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle routes BEFORE dashboard redirect
if ($route === 'set-terminal' && isset($_GET['terminal_id'])) {
    $_SESSION['terminal_id'] = (int)$_GET['terminal_id'];
    $_SESSION['terminal_name'] = $_GET['terminal_name'] ?? '';
    header("Location: /dispatcher/" . $urls['dashboard']);
    exit;
}

if ($route === 'clear-terminal') {
    unset($_SESSION['terminal_id'], $_SESSION['terminal_name']);
    header("Location: /dispatcher/" . $urls['dashboard']);
    exit;
}

if ($route === 'logout') {
    session_destroy();
    $cookieParams = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $cookieParams['path'], $cookieParams['domain'] ?? '', $cookieParams['secure'] ?? false, $cookieParams['httponly'] ?? true);
    header("Location: /dispatcher/" . $urls['login']);
    exit;
}

// Obfuscate dashboard URL: if no valid `d` param, redirect
if ($dash !== $_SESSION['dashboard_secret'] && !isset($_GET['_dash'])) {
    $qs = $_GET;
    unset($qs['d'], $qs['route']);
    $extra = $qs ? '&' . http_build_query($qs) : '';
    header("Location: /dispatcher/" . $urls['dashboard'] . '?d=' . $_SESSION['dashboard_secret'] . '&route=' . urlencode($route) . $extra);
    exit;
}

$token = $_SESSION['dispatcher_token'] ?? '';
$name = $_SESSION['inspector_name'] ?? 'Dispatcher';
$terminal_id = $_SESSION['terminal_id'] ?? 0;
$terminal_name = $_SESSION['terminal_name'] ?? '';
$lang = dispatcher_current_language();
$translations = dispatcher_translations($lang);
$languages = dispatcher_available_languages();
$langKeys = ['en' => 'app.english', 'tl' => 'app.tagalog', 'ilo' => 'app.ilocano'];

// Pre-load terminals for dropdown and determine route
$terminalList = [];
$defaultRoute = '';
$defaultDestTerminalId = 0;
if ($terminal_id) {
    require_once __DIR__ . '/../mobile/config/database.php';
    $tdb = new Database();
    $tconn = $tdb->connect();
    $tres = $tconn->query("SELECT id, terminal_code, terminal_name, city, province FROM terminals WHERE status = 'Active' ORDER BY terminal_name ASC");
    while ($trw = $tres->fetch_assoc()) {
        $terminalList[] = ['id' => (int)$trw['id'], 'terminal_name' => $trw['terminal_name']];
        if ((int)$trw['id'] !== $terminal_id && $defaultDestTerminalId === 0) {
            $defaultDestTerminalId = (int)$trw['id'];
        }
    }
    // Build route name from terminal names
    $currentName = '';
    $destName = '';
    foreach ($terminalList as $t) {
        if ($t['id'] === $terminal_id) $currentName = $t['terminal_name'];
        if ($t['id'] === $defaultDestTerminalId) $destName = $t['terminal_name'];
    }
    $parts = explode(' ', trim($currentName));
    $currentShort = $parts[0] ?? '';
    $parts = explode(' ', trim($destName));
    $destShort = $parts[0] ?? '';
    $defaultRoute = trim($currentShort) . '-' . trim($destShort);
    $tconn->close();
}

// Server-side terminal list for selection screen
$terminals = [];
if (!$terminal_id) {
    require_once __DIR__ . '/../mobile/config/database.php';
    $db = new Database();
    $conn = $db->connect();
    $result = $conn->query("SELECT id, terminal_code, terminal_name, city, province FROM terminals WHERE status = 'Active' ORDER BY terminal_name ASC");
    while ($row = $result->fetch_assoc()) $terminals[] = $row;
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
<title>R-Connect Dispatcher Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="manifest" href="/dispatcher/manifest.json">
<link rel="stylesheet" href="/public/login/custom-alert.css">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Dispatcher">
<meta name="theme-color" content="#47613b">
<style>
:root{--primary:#47613b;--primary-dark:#24513c;--primary-light:#5b774d;--accent:#c6d48c;--accent-light:#e3eabb;--bg-cream:#fef8f3;--bg-card:#faf9e5;--border-card:#e5e0a4;--border-light:#c6d48c;--text-primary:#24513c;--text-muted:#6c757d;--font:'Poppins',sans-serif;--radius-card:20px;--radius-sm:12px;--shadow:0 4px 15px rgba(0,0,0,0.08);--gradient:linear-gradient(90deg,#306449,#b3be5b);}
*{box-sizing:border-box;}
body{font-family:var(--font);background:var(--bg-cream);margin:0;min-height:100vh;}
.custom-alert-message{white-space:pre-line;}

/* ── Terminal Selection ── */
.terminal-select{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100dvh;padding:clamp(16px,4vw,40px);text-align:center;background:linear-gradient(145deg,#fef8f3,#f5efe6);}
.terminal-select .brand{display:flex;align-items:center;gap:12px;margin-bottom:6px;padding:10px 24px;background:#fff;border:2px solid #c6d48c;border-radius:100px;box-shadow:0 2px 8px rgba(0,0,0,0.06);}
.terminal-select .brand img{height:28px;}
.terminal-select .brand span{font-size:16px;font-weight:700;color:#2e4836;}
.terminal-select h2{color:#2e4836;font-weight:700;margin:16px 0 4px;font-size:clamp(20px,5vw,28px);}
.terminal-select .sub{color:#6b7280;font-size:13px;margin-bottom:20px;}
.terminal-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;width:100%;max-width:680px;margin-top:4px;}
.terminal-card{background:#fff;border:2px solid #e3eabb;border-radius:16px;padding:20px 14px;text-align:center;cursor:pointer;transition:.25s;box-shadow:0 2px 8px rgba(0,0,0,0.04);position:relative;overflow:hidden;height:165px;display:flex;flex-direction:column;align-items:center;justify-content:center;}
.terminal-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#47613b,#6a8f5a);opacity:0;transition:.25s;}
.terminal-card:hover{border-color:#47613b;transform:translateY(-4px);box-shadow:0 8px 24px rgba(71,97,59,0.12);}
.terminal-card:hover::before{opacity:1;}
.terminal-card .icon-wrap{width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,#f9f4da,#fef8f3);border:1.5px solid #e3eabb;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;}
.terminal-card .icon-wrap i{font-size:24px;color:#47613b;}
.terminal-card h5{margin:0;font-size:14px;font-weight:600;color:#2e4836;}
.terminal-card .code{display:inline-block;margin-top:6px;padding:2px 10px;background:#f9f4da;border:1px solid #e3eabb;border-radius:999px;font-size:10px;font-weight:600;color:#6b7280;}
.terminal-card p{margin:6px 0 0;font-size:11px;color:#9ca3af;}

/* ── Header ── */
.header-bar{background:var(--bg-cream);border-bottom:1px solid var(--border-card);padding:clamp(6px,1.5vw,14px) clamp(8px,2vw,20px);position:sticky;top:0;z-index:100;display:flex;justify-content:space-between;align-items:center;gap:6px;}
.header-bar .info{min-width:0;overflow:hidden;}
.header-bar h5{font-weight:700;color:var(--text-primary);margin:0;font-size:clamp(12px,2.2vw,18px);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.header-bar .sub{font-size:clamp(8px,1.3vw,11px);color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.header-actions{display:flex;gap:clamp(4px,0.8vw,10px);align-items:center;flex-shrink:0;}
.hdr-btn{display:flex;align-items:center;justify-content:center;width:clamp(32px,5vw,38px);height:clamp(32px,5vw,38px);border-radius:50%;border:1px solid var(--border-card);background:#fff;color:var(--text-muted);font-size:clamp(13px,2vw,16px);cursor:pointer;transition:.2s;text-decoration:none;}
.hdr-btn .lbl{display:none;font-size:11px;font-weight:600;font-family:var(--font);}
.hdr-btn:hover{transform:translateY(-1px);box-shadow:var(--shadow);}
.hdr-print:hover{background:#e8f5e9;border-color:#a5d6a7;color:#2e7d32;}
.hdr-switch:hover{background:#fff8e1;border-color:#f0d060;color:#8d6e00;}
.hdr-logout:hover{background:#fce4ec;border-color:#f48fb1;color:#c62828;}
@media(max-width:480px){
.header-bar{gap:6px;padding:10px 12px;}
.header-actions{gap:6px;}
.hdr-btn{width:34px;height:34px;font-size:14px;}
.header-bar h5{font-size:15px;}
.header-bar .sub{font-size:10px;}
.search-row input{font-size:15px;padding:12px 16px;}
.search-row .btn-dispatch{width:auto;height:44px;font-size:14px;padding:0 14px;}
.filter-chip{padding:8px 14px;font-size:12px;}
.btn-action{padding:10px 16px;font-size:13px;min-height:40px;}
.form-field{font-size:15px;padding:14px;}
.btn-primary-app{font-size:16px;padding:15px;}
.install-banner{padding:12px 16px;}
.install-banner .text{font-size:13px;}
.install-banner .install-btn{padding:10px 20px;font-size:13px;}
}

/* ── Clock ── */
.clock-row{display:flex;justify-content:space-between;align-items:center;padding:clamp(6px,1.5vw,10px) clamp(10px,2.5vw,20px);font-size:clamp(11px,2vw,13px);color:var(--text-muted);flex-wrap:nowrap;gap:8px;}
.clock-row > span{min-width:0;}
.clock-end{display:flex;align-items:center;gap:10px;flex:none;white-space:nowrap;}
#liveClock{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}

/* ── Stats Grid ── */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(90px,150px));justify-content:center;gap:clamp(6px,1vw,12px);padding:0 clamp(10px,2.5vw,20px) clamp(10px,1.5vw,16px);}
.stat-card{background:var(--bg-card);border:1px solid var(--border-card);border-radius:var(--radius-sm);padding:clamp(10px,1.5vw,16px) 6px;text-align:center;box-shadow:var(--shadow);cursor:pointer;transition:.2s;}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(71,97,59,0.18);}
.stat-card .icon{font-size:clamp(15px,2.5vw,20px);color:var(--primary);margin-bottom:1px;}
.stat-card .num{font-size:clamp(18px,4vw,28px);font-weight:700;color:var(--text-primary);line-height:1.2;}
.stat-card .label{font-size:clamp(7px,1.2vw,9px);color:var(--text-muted);text-transform:uppercase;letter-spacing:0.3px;margin-top:2px;font-weight:500;}
.stat-card.primary{border-width:1.5px;background:linear-gradient(160deg,#faf9e5,#fdf6e3);}
.stat-card.primary .num{font-size:clamp(22px,5vw,32px);}
.stat-card.primary .label{font-weight:700;color:var(--primary-dark);}
.stat-card.secondary{opacity:.85;}
.stat-card.secondary .num{font-size:clamp(15px,3vw,22px);}

/* ── Search ── */
.search-row{display:flex;gap:6px;padding:0 clamp(10px,2.5vw,20px) 8px;}
.search-row input{flex:1;padding:clamp(10px,2vw,14px) clamp(12px,2.5vw,18px);border:1px solid var(--border-card);border-radius:14px;font-size:clamp(13px,2.5vw,15px);background:#fff;-webkit-appearance:none;min-width:0;}
.search-row input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 2px rgba(71,97,59,0.15);}
.search-row .btn-dispatch{border:none;background:var(--gradient);color:#fff;border-radius:14px;padding:0 clamp(12px,2.5vw,20px);display:flex;align-items:center;justify-content:center;gap:6px;font-size:clamp(12px,2vw,14px);font-weight:700;font-family:var(--font);flex-shrink:0;cursor:pointer;transition:.2s;box-shadow:var(--shadow);white-space:nowrap;}
.search-row .btn-dispatch:hover{opacity:.92;transform:translateY(-1px);}
@media(max-width:400px){
.search-row .btn-dispatch span.txt{display:none;}
.search-row .btn-dispatch{width:clamp(44px,12vw,52px);}
}

/* ── Filter Chips ── */
.filter-row{display:flex;justify-content:center;flex-wrap:wrap;gap:6px;margin-top:clamp(4px,1vw,8px);padding:clamp(2px,0.5vw,4px) clamp(10px,2.5vw,20px) 8px;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;}
.filter-row::-webkit-scrollbar{display:none;}
.filter-chip{border:1px solid var(--border-light);border-radius:999px;padding:clamp(5px,1.2vw,8px) clamp(10px,2vw,16px);font-size:clamp(10px,1.8vw,12px);font-weight:600;color:var(--text-primary);background:#fff;cursor:pointer;white-space:nowrap;transition:.2s;flex-shrink:0;}
.filter-chip.active{background:var(--accent);border-color:var(--primary);color:var(--primary-dark);box-shadow:0 2px 10px rgba(71,97,59,0.35);transform:translateY(1px);}

/* ── Operations: attention strip ── */
.attention-row{display:flex;gap:6px;padding:0 clamp(10px,2.5vw,20px) 8px;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;flex-wrap:nowrap;}
.attention-row::-webkit-scrollbar{display:none;}
.attention-chip{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--border-card);background:#fff;border-radius:12px;padding:clamp(5px,1vw,8px) clamp(10px,1.8vw,14px);font-size:clamp(10px,1.6vw,12px);font-weight:600;color:var(--text-primary);white-space:nowrap;flex-shrink:0;box-shadow:var(--shadow);}
.attention-chip i{color:var(--primary);}
.attention-chip.attn-hot{border-color:#f0d060;background:#fff8e1;color:#8d6e00;}
.attention-chip.attn-hot i{color:#b7791f;}

/* ── Dispatch Cards ── */
.dispatch-list{max-width:800px;margin:0 auto;padding:0 clamp(8px,2vw,16px);}
.dispatch-card{background:#fff;border:1px solid var(--border-card);border-radius:var(--radius-card);padding:clamp(12px,2vw,16px);margin-bottom:clamp(8px,1.5vw,12px);box-shadow:var(--shadow);}
.dispatch-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;gap:8px;}
.dispatch-id{font-weight:700;color:var(--text-primary);font-size:clamp(13px,2.2vw,15px);}
.dispatch-busrow{display:flex;flex-wrap:wrap;gap:4px clamp(14px,3vw,24px);margin-top:2px;}
.dispatch-busrow .bus-line{font-size:clamp(13px,2.4vw,16px);font-weight:700;color:var(--text-primary);display:inline-flex;align-items:center;gap:5px;}
.dispatch-busrow .driver-line{font-size:clamp(12px,2.2vw,14px);color:#444;display:inline-flex;align-items:center;gap:5px;}
.dispatch-busrow i{color:var(--primary);font-size:clamp(12px,2.2vw,15px);}
.dispatch-route{margin-top:5px;font-size:clamp(12px,2.2vw,14px);font-weight:600;color:var(--primary-dark);display:flex;align-items:center;gap:5px;}
.dispatch-route i{color:var(--primary);}
.dispatch-meta{display:flex;flex-wrap:wrap;gap:4px clamp(12px,2.5vw,20px);margin-top:6px;font-size:clamp(11px,1.9vw,13px);color:#555;}
.dispatch-meta span{display:inline-flex;align-items:center;gap:4px;}
.dispatch-meta i{color:var(--primary);font-size:clamp(11px,1.9vw,13px);}
.dispatch-actions{display:flex;gap:6px;margin-top:clamp(8px,1.4vw,12px);flex-wrap:wrap;align-items:center;}
.dispatch-next{display:flex;align-items:center;gap:5px;font-size:clamp(10px,1.6vw,12px);font-weight:600;color:var(--primary-dark);background:var(--bg-card);border:1px dashed var(--border-light);border-radius:999px;padding:4px 10px;white-space:nowrap;}
.dispatch-next i{color:var(--primary);}
.btn-view{background:#fff;border:1.5px solid var(--primary-light);color:var(--primary-dark);display:inline-flex;align-items:center;gap:7px;}
.btn-view .btn-ico{width:24px;height:24px;border-radius:50%;background:var(--accent-light);color:var(--primary-dark);display:inline-flex;align-items:center;justify-content:center;font-size:11px;transition:.2s;}
.btn-view:hover{background:var(--accent-light);border-color:var(--primary);box-shadow:0 2px 8px rgba(71,97,59,0.18);}
.btn-view:hover .btn-ico{transform:scale(1.1);}

/* ── Status Badges ── */
.status-badge{padding:clamp(2px,0.6vw,4px) clamp(6px,1.2vw,10px);border-radius:999px;font-size:clamp(8px,1.3vw,10px);font-weight:600;text-transform:uppercase;letter-spacing:0.3px;white-space:nowrap;}
.status-Scheduled{background:#fff4d6;color:#b7791f;}
.status-Departed{background:#cce5ff;color:#004085;}
.status-Completed{background:#d4edda;color:#155724;}
.status-Cancelled{background:#f8d7da;color:#721c24;}
.status-waiting{background:#fff4d6;color:#b7791f;}
.status-acknowledged{background:#cce5ff;color:#004085;}
.status-resolved{background:#d4edda;color:#155724;}
.status-Interrupted{background:#fde2e2;color:#9b1c1c;}
.status-available{background:#d4edda;color:#155724;}
.bpr-detail{margin-top:6px;padding:8px 10px;background:var(--bg-card);border:1px solid var(--border-card);border-radius:10px;font-size:11.5px;color:var(--text-primary);word-break:break-word;}
.bpr-meta{font-size:11px;color:var(--text-muted);display:flex;flex-wrap:wrap;gap:4px 10px;margin-top:6px;}
.bpr-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;}
.bpr-actions .btn-action{flex:1;min-width:120px;min-height:40px;justify-content:center;border:none;color:#fff;box-shadow:0 2px 6px rgba(0,0,0,.08);}
.bpr-btn-primary{background:var(--gradient);}
.bpr-btn-primary:hover{opacity:.9;}
.bpr-btn-secondary{background:var(--primary-dark);}
.bpr-btn-secondary:hover{opacity:.9;}

/* ── Buttons ── */
.btn-action{border:none;border-radius:999px;padding:clamp(7px,1.2vw,10px) clamp(12px,2vw,18px);font-size:clamp(11px,1.8vw,13px);font-weight:600;cursor:pointer;transition:.2s;min-height:36px;}
.btn-arrive{background:var(--gradient);color:#fff;}
.btn-cancel{background:#f8d7da;color:#721c24;}
.btn-sm{background:var(--gradient);color:#fff;border:none;border-radius:999px;padding:clamp(5px,1vw,8px) clamp(8px,1.5vw,14px);font-size:clamp(9px,1.4vw,11px);font-weight:600;cursor:pointer;}

/* ── Form ── */
.form-field{width:100%;padding:clamp(10px,2vw,14px);border:1px solid var(--border-light);border-radius:14px;font-size:clamp(13px,2.5vw,15px);font-family:var(--font);background:#fff;margin-bottom:clamp(8px,1.5vw,12px);-webkit-appearance:none;-moz-appearance:none;appearance:none;transition:.2s;color:var(--text-primary);background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236c757d' d='M6 8L0 0h12z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;background-size:12px 8px;padding-right:36px;}
.form-field:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 2px rgba(71,97,59,0.15);}
.form-field option{color:var(--text-primary);background:#fff;}
.btn-primary-app{width:100%;padding:clamp(12px,2vw,16px);border:none;border-radius:12px;background:var(--gradient);color:#fff;font-weight:700;font-size:clamp(14px,2.5vw,16px);cursor:pointer;transition:.2s;}
.btn-primary-app:hover{opacity:.9;}

/* ── Install Banner ── */
.install-banner{position:fixed;bottom:0;left:0;right:0;background:var(--bg-cream);border-top:2px solid var(--border-card);padding:clamp(10px,2vw,16px) clamp(12px,2.5vw,20px);z-index:9998;display:flex;justify-content:space-between;align-items:center;gap:12px;box-shadow:0 -4px 15px rgba(0,0,0,0.08);}
.install-banner .text{font-size:clamp(12px,2vw,14px);color:var(--text-primary);line-height:1.3;}
.install-banner .text strong{color:var(--primary-dark);}
.install-banner .close-btn{background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;padding:4px;flex-shrink:0;}
.install-banner .install-btn{border:none;border-radius:12px;background:var(--gradient);color:#fff;padding:clamp(8px,1.5vw,12px) clamp(16px,3vw,24px);font-size:clamp(12px,2vw,14px);font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0;}

/* ── States ── */
.loading{text-align:center;padding:30px;color:var(--text-muted);font-size:13px;}
.empty-state{text-align:center;padding:clamp(24px,5vw,48px) 16px;color:var(--text-muted);}
.empty-state i{font-size:clamp(32px,6vw,48px);color:var(--accent);margin-bottom:10px;}
.empty-state p{font-size:clamp(12px,2vw,14px);margin:0;}

/* ── Modals ── */
.modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.4);z-index:10000;display:none;justify-content:center;align-items:flex-end;padding:0;}
body.modal-open .bottom-action-bar{display:none !important;}
.modal-overlay.show{display:flex;}
.modal-content{width:100%;max-width:520px;max-height:clamp(70vh,85vh,90vh);overflow-y:auto;background:var(--bg-cream);border-radius:var(--radius-card) var(--radius-card) 0 0;padding:clamp(16px,3vw,24px);animation:slideUp .25s ease;position:relative;}
@keyframes slideUp{from{transform:translateY(100%);}to{transform:translateY(0);}}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:clamp(10px,2vw,16px);padding-bottom:clamp(8px,1.5vw,12px);border-bottom:1px solid var(--border-card);}
.modal-header h6{margin:0;font-weight:700;color:var(--text-primary);font-size:clamp(15px,2.5vw,18px);}
.modal-close{width:clamp(30px,5vw,36px);height:clamp(30px,5vw,36px);border-radius:50%;border:1px solid var(--border-card);background:#fff;color:var(--text-muted);font-size:clamp(14px,2.5vw,18px);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.2s;flex-shrink:0;}
.modal-close:hover{background:#ffe5e5;border-color:#f8d7da;color:#721c24;}
.modal-time-box{background:var(--bg-card);border:1px solid var(--border-card);border-radius:var(--radius-sm);padding:clamp(8px,1.5vw,12px);margin:clamp(6px,1vw,10px) 0;text-align:center;font-weight:600;font-size:clamp(12px,2vw,14px);color:var(--text-primary);}

/* ── Desktop ── */
@media(min-width:768px){
  .modal-overlay{align-items:center;}
  .modal-content{border-radius:var(--radius-card);animation:fadeUp .25s ease;}
  @keyframes fadeUp{from{transform:translateY(20px);opacity:0;}to{transform:translateY(0);opacity:1;}}
  .stats-row{grid-template-columns:repeat(3,1fr);max-width:520px;margin:0 auto;}
  .stat-card{padding:clamp(14px,2vw,20px) 8px;}
  .filter-row{justify-content:center;}
  .hdr-btn{width:auto;height:auto;border-radius:999px;padding:8px 14px;gap:6px;}
  .hdr-btn .lbl{display:inline;}
}

/* ── Trip Detail Modal ── */
.trip-hero{background:var(--gradient);border-radius:var(--radius-sm);padding:clamp(14px,2.5vw,20px);color:#fff;text-align:center;margin-bottom:12px;}
.trip-hero-top{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px;}
.trip-hero-id{font-size:clamp(13px,2.2vw,15px);font-weight:700;display:inline-flex;align-items:center;gap:5px;}
.trip-hero-id i{color:var(--accent-light);}
.trip-hero .status-badge{background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.35);}
.trip-hero-route{font-size:clamp(15px,2.6vw,19px);font-weight:700;display:flex;align-items:center;justify-content:center;gap:7px;line-height:1.35;}
.trip-hero-route i{color:var(--accent-light);font-size:clamp(13px,2.2vw,16px);}
.trip-hero-date{font-size:clamp(10px,1.7vw,12px);opacity:.85;margin-top:5px;display:flex;align-items:center;justify-content:center;gap:5px;}
.trip-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;}
.trip-info-card{background:#fff;border:1px solid var(--border-light);border-radius:var(--radius-sm);padding:clamp(10px,1.8vw,14px);display:flex;flex-direction:column;align-items:center;text-align:center;gap:2px;}
.trip-info-card .tic-icon{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;margin-bottom:4px;}
.trip-info-card .tic-icon.bus{background:var(--accent-light);color:var(--primary-dark);}
.trip-info-card .tic-icon.driver{background:#e8f5e9;color:#2e7d32;}
.trip-info-card .tic-label{font-size:clamp(9px,1.4vw,11px);color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;font-weight:500;}
.trip-info-card .tic-value{font-size:clamp(12px,2vw,14px);font-weight:700;color:var(--text-primary);}
.trip-detail-grid{display:flex;flex-direction:column;gap:10px;}
.trip-detail-row{display:flex;justify-content:space-between;align-items:center;gap:10px;background:#fff;border:1px solid var(--border-light);border-radius:var(--radius-sm);padding:clamp(10px,1.8vw,14px);}
.trip-detail-row .td-label{font-size:clamp(10px,1.6vw,12px);color:var(--text-muted);display:flex;align-items:center;gap:6px;font-weight:500;}
.trip-detail-row .td-label i{color:var(--primary);}
.trip-detail-row .td-value{font-size:clamp(12px,2vw,14px);font-weight:600;color:var(--text-primary);text-align:right;}

/* ── History Bar ── */
.hist-bar{display:none;align-items:center;gap:6px;padding:0 clamp(10px,2.5vw,20px) 10px;}
.hist-bar .hb-label{font-size:clamp(11px,1.8vw,13px);font-weight:600;color:var(--text-primary);white-space:nowrap;flex-shrink:0;}
.hist-bar .hb-count{margin-left:auto;font-size:clamp(9px,1.3vw,11px);color:var(--text-muted);background:var(--bg-card);padding:2px 8px;border-radius:999px;border:1px solid var(--border-card);white-space:nowrap;flex-shrink:0;}
.hist-bar .hb-dates{display:flex;align-items:center;gap:4px;flex:1;min-width:0;}
.hist-bar .hb-dates input{-webkit-appearance:none;appearance:none;flex:1;min-width:0;padding:clamp(6px,1.2vw,10px) clamp(8px,1.2vw,12px);border:1px solid var(--border-light);border-radius:10px;font-size:clamp(10px,1.5vw,12px);font-family:var(--font);background:#fff;color:var(--text-primary);}
.hist-bar .hb-dates input:focus{outline:none;border-color:var(--primary);}
.hist-bar .hb-dates .hb-sep{color:var(--text-muted);font-size:11px;font-weight:500;flex-shrink:0;}
.hist-bar .hb-go{flex-shrink:0;width:clamp(34px,6vw,40px);height:clamp(34px,6vw,40px);border:none;border-radius:50%;background:var(--gradient);color:#fff;display:flex;align-items:center;justify-content:center;font-size:clamp(14px,2.5vw,18px);cursor:pointer;}

/* ── Pagination ── */
.pg-row{display:none;justify-content:center;align-items:center;gap:12px;padding:8px 0 4px;}
.pg-row .pg-btn{background:none;border:none;color:var(--primary);font-size:clamp(12px,1.8vw,14px);font-weight:600;cursor:pointer;padding:6px 4px;display:flex;align-items:center;gap:3px;font-family:var(--font);}
.pg-row .pg-btn:disabled{color:var(--text-muted);cursor:default;opacity:.4;}
.pg-row .pg-info{font-size:clamp(11px,1.6vw,13px);color:var(--text-muted);font-weight:500;}

/* ── Small Screens ── */
@media(max-width:480px){
  .clock-row{flex-direction:row;flex-wrap:nowrap;gap:8px;align-items:center;font-size:13px;padding:10px 12px;}
  .stats-row{grid-template-columns:repeat(3,1fr);gap:4px;padding-left:8px;padding-right:8px;}
  .stat-card{padding:8px 2px 6px;}
  .stat-card .icon{font-size:14px;}
  .stat-card .num{font-size:14px;}
  .stat-card.primary .num{font-size:19px;}
  .stat-card .label{font-size:6.5px;letter-spacing:0.1px;}
  .dispatch-card{padding:14px;margin:0 8px 10px;}
  .dispatch-id{font-size:14px;}
  .dispatch-meta{font-size:12px;gap:4px 10px;}
  .hist-bar{gap:5px;}
  .hist-bar .hb-dates{gap:3px;}
  .hist-bar .hb-dates input{padding:7px 8px;font-size:11px;}
}

/* ── Queue Control Panel ── */
.queue-view{max-width:900px;margin:0 auto;}
.next-dispatch{background:#fff;border:2px solid var(--border-card);border-radius:var(--radius-card);box-shadow:var(--shadow);padding:clamp(14px,2.5vw,20px);margin:0 clamp(8px,2vw,16px) clamp(10px,2vw,14px);}
.next-dispatch .nd-kicker{font-size:clamp(9px,1.5vw,11px);font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--primary-dark);display:flex;align-items:center;gap:6px;margin-bottom:8px;}
.next-dispatch .nd-bus{font-size:clamp(20px,5vw,30px);font-weight:700;color:var(--text-primary);display:flex;align-items:center;gap:8px;line-height:1.2;flex-wrap:wrap;}
.next-dispatch .nd-route{font-size:clamp(13px,2.6vw,17px);font-weight:600;color:var(--primary-dark);margin-top:4px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.next-dispatch .nd-driver{font-size:clamp(12px,2.2vw,14px);color:#444;margin-top:2px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.nd-rows{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:12px 0;}
.nd-row{background:var(--bg-card);border:1px solid var(--border-card);border-radius:var(--radius-sm);padding:8px 10px;}
.nd-row .nd-label{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.4px;color:var(--text-muted);font-weight:600;}
.nd-row .nd-value{font-size:clamp(12px,2vw,14px);font-weight:600;color:var(--text-primary);}
.nd-note{display:flex;gap:8px;align-items:flex-start;background:#fff8e1;border:1px solid #f0d060;border-radius:var(--radius-sm);padding:8px 10px;font-size:clamp(11px,1.9vw,13px);color:#8d6e00;margin-bottom:10px;}
.nd-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
.nd-actions .btn-action{min-height:44px;padding:10px 18px;font-size:clamp(12px,2vw,14px);}
.btn-dispatch-queue{background:var(--gradient);color:#fff;border:none;display:inline-flex;align-items:center;gap:7px;}
.btn-dispatch-queue:hover{opacity:.92;}
.btn-skip{background:#fff8e1;color:#8d6e00;border:1.5px solid #f0d060;display:inline-flex;align-items:center;gap:7px;}
.btn-skip:hover{background:#fff3c4;}
.next-dispatch .nd-loading{text-align:center;color:var(--text-muted);padding:20px 0;}

.queue-controls{padding:0 clamp(8px,2vw,16px);}
.queue-tools{display:flex;gap:6px;padding:0 clamp(10px,2.5vw,20px) 8px;flex-wrap:wrap;}
.queue-tools input{flex:1;min-width:150px;padding:clamp(10px,2vw,14px) clamp(12px,2.5vw,18px);border:1px solid var(--border-card);border-radius:14px;font-size:clamp(13px,2.5vw,15px);background:#fff;-webkit-appearance:none;}
.queue-tools input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 2px rgba(71,97,59,.15);}
.queue-tools .route-filter{flex:0 1 170px;min-width:130px;margin-bottom:0;padding:clamp(10px,2vw,14px) 36px clamp(10px,2vw,14px) 12px;background-position:right 12px center;}
.queue-tools .btn-dispatch{width:auto;height:auto;border:none;background:var(--gradient);color:#fff;border-radius:14px;padding:clamp(10px,2vw,14px) clamp(12px,2.5vw,18px);display:flex;align-items:center;justify-content:center;gap:6px;font-size:clamp(12px,2vw,14px);font-weight:700;font-family:var(--font);cursor:pointer;box-shadow:var(--shadow);white-space:nowrap;}
.queue-updated-row{display:flex;align-items:center;gap:8px;padding:0 clamp(10px,2.5vw,20px) 8px;flex-wrap:wrap;}
.queue-updated{font-size:clamp(10px,1.6vw,12px);color:var(--text-muted);}
#queueFilterRow .filter-chip{font-family:var(--font);display:inline-flex;align-items:center;gap:5px;}
.chip-count{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:var(--bg-card);border:1px solid var(--border-card);font-size:9.5px;font-weight:700;color:var(--primary-dark);}
#queueFilterRow .filter-chip.active .chip-count{background:#fff;border-color:var(--primary-light);}
.qc-sched{font-size:clamp(9px,1.5vw,11px);font-weight:600;color:var(--text-muted);background:#fff;border:1px solid var(--border-card);border-radius:999px;padding:3px 9px;}
.queue-stale{display:inline-flex;align-items:center;gap:4px;font-size:clamp(10px,1.6vw,12px);font-weight:600;color:#8d6e00;background:#fff8e1;border:1px solid #f0d060;border-radius:999px;padding:2px 8px;}
.btn-refresh{background:#fff;border:1px solid var(--border-light);color:var(--primary-dark);border-radius:999px;padding:5px 12px;font-size:clamp(10px,1.7vw,12px);font-weight:600;font-family:var(--font);cursor:pointer;display:inline-flex;align-items:center;gap:5px;min-height:32px;}
.btn-refresh:hover{background:var(--accent-light);}

.queue-grid{max-width:900px;margin:0 auto;padding:0 clamp(8px,2vw,16px) 12px;display:grid;grid-template-columns:1fr;gap:clamp(8px,1.5vw,12px);}
.queue-card{background:#fff;border:1px solid var(--border-card);border-radius:var(--radius-card);padding:clamp(12px,2vw,16px);box-shadow:var(--shadow);display:flex;flex-direction:column;gap:6px;border-left:5px solid var(--border-light);}
.queue-card.q-ready{border-left-color:#2e7d32;}
.queue-card.q-waiting_for_driver{border-left-color:#8d6e00;}
.queue-card.q-driver_assigned{border-left-color:#d97706;}
.queue-card.q-checked_in{border-left-color:#5b8f4e;}
.queue-card.q-departed{border-left-color:#004085;opacity:.94;}
.qc-top{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;}
.qc-pos{font-size:clamp(10px,1.7vw,12px);font-weight:700;letter-spacing:.4px;color:var(--primary-dark);background:var(--bg-card);border:1px solid var(--border-card);border-radius:999px;padding:3px 10px;}
.qc-next{font-size:9px;font-weight:700;letter-spacing:.3px;text-transform:uppercase;color:var(--primary-dark);background:var(--accent-light);border:1px solid var(--primary-light);border-radius:999px;padding:2px 8px;white-space:nowrap;}
.queue-status-pill{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:3px 10px;font-size:clamp(9px,1.5vw,11px);font-weight:700;letter-spacing:.3px;white-space:nowrap;}
.queue-status-pill.q-ready{background:#d4edda;color:#155724;}
.queue-status-pill.q-waiting_for_driver{background:#fff3cd;color:#8d6e00;}
.queue-status-pill.q-driver_assigned{background:#ffe8cc;color:#9a4d00;}
.queue-status-pill.q-checked_in{background:#e8f5e9;color:#1b5e20;}
.queue-status-pill.q-departed{background:#cce5ff;color:#004085;}
.qc-cycle{font-size:9px;font-weight:700;letter-spacing:.3px;text-transform:uppercase;color:#8d6e00;background:#fff8e1;border:1px solid #f0d060;border-radius:999px;padding:2px 8px;white-space:nowrap;}
.page-title-row{max-width:900px;margin:0 auto;padding:clamp(4px,1vw,10px) clamp(10px,2.5vw,20px) 0;}
.page-title{margin:0;font-size:clamp(18px,4vw,24px);font-weight:700;color:var(--text-primary);display:flex;align-items:center;gap:8px;}
.page-title i{color:var(--primary);}
.lang-select{border:1px solid var(--border-card);background:#fff;color:var(--text-primary);border-radius:999px;padding:6px 10px;font-family:var(--font);font-size:clamp(10px,1.7vw,12px);font-weight:600;min-height:34px;max-width:110px;}
.why-not-card{background:#fff8e1;border:1px solid #f0d060;border-radius:var(--radius-card);padding:clamp(10px,2vw,14px);margin:0 clamp(8px,2vw,16px) clamp(8px,1.5vw,12px);box-shadow:var(--shadow);}
.why-not-card .wn-title{font-size:clamp(10px,1.7vw,12px);font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:#8d6e00;display:flex;align-items:center;gap:6px;margin-bottom:4px;}
.why-not-card .wn-bus{font-size:clamp(13px,2.4vw,16px);font-weight:700;color:var(--text-primary);}
.why-not-card .wn-reason{font-size:clamp(11px,1.9vw,13px);color:#6b5a10;margin-top:2px;}
.why-not-card .wn-next{font-size:clamp(11px,1.9vw,13px);font-weight:600;color:var(--primary-dark);margin-top:6px;}
.checkin-box{background:var(--bg-card);border:1px solid var(--border-card);border-radius:var(--radius-sm);padding:10px 12px;margin-bottom:10px;}
.checkin-ok{display:flex;flex-direction:column;gap:2px;font-size:clamp(11px,1.9vw,13px);color:#1b5e20;}
.checkin-ok i{color:#2e7d32;}
.checkin-pending{display:flex;align-items:center;gap:6px;font-size:clamp(11px,1.9vw,13px);color:#9a4d00;margin-bottom:8px;}
.btn-checkin{width:100%;min-height:46px;border:none;border-radius:12px;background:var(--gradient);color:#fff;font-family:var(--font);font-weight:700;font-size:clamp(13px,2.2vw,15px);display:flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;}
.btn-checkin:hover{opacity:.92;}
.btn-checkin:disabled{opacity:.7;}
.bottom-action-bar{position:fixed;left:0;right:0;bottom:0;z-index:9997;display:flex;align-items:center;justify-content:space-between;gap:10px;background:var(--bg-cream);border-top:2px solid var(--border-card);padding:10px clamp(12px,2.5vw,20px);box-shadow:0 -4px 15px rgba(0,0,0,.08);}
.bottom-action-bar .bab-info{font-size:clamp(11px,1.9vw,13px);font-weight:600;color:var(--text-primary);min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.bottom-action-bar .bab-btn{flex-shrink:0;min-height:46px;border:none;border-radius:12px;background:var(--gradient);color:#fff;font-family:var(--font);font-weight:700;font-size:clamp(12px,2vw,14px);padding:0 clamp(14px,3vw,22px);display:inline-flex;align-items:center;gap:7px;cursor:pointer;}
body.has-bottom-bar{padding-bottom:88px;}
.qc-bus{font-size:clamp(15px,2.8vw,19px);font-weight:700;color:var(--text-primary);display:flex;align-items:center;gap:6px;}
.qc-bus i,.qc-driver i,.qc-route i{color:var(--primary);}
.qc-driver{font-size:clamp(12px,2.1vw,14px);color:#444;display:flex;align-items:center;gap:6px;}
.qc-route{font-size:clamp(12px,2.1vw,14px);font-weight:600;color:var(--primary-dark);display:flex;align-items:center;gap:6px;}
.qc-reason{font-size:clamp(11px,1.9vw,13px);color:var(--text-muted);background:var(--bg-cream);border-radius:10px;padding:6px 10px;}
.qc-skip{font-size:clamp(11px,1.9vw,13px);color:#8d6e00;background:#fff8e1;border:1px solid #f0d060;border-radius:10px;padding:6px 10px;display:flex;align-items:center;gap:6px;}
.qc-actions{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:2px;}
.qc-actions .btn-action{min-height:44px;padding:9px 14px;font-size:clamp(11px,1.9vw,13px);}
.btn-danger-app{background:#f8d7da;color:#721c24;border:none;border-radius:999px;padding:10px 18px;font-size:clamp(12px,2vw,14px);font-weight:700;font-family:var(--font);cursor:pointer;min-height:44px;}
.btn-danger-app:hover{background:#f5c2c7;}
.btn-cancel-app{background:#fff;border:1px solid var(--border-card);color:var(--text-muted);border-radius:999px;padding:10px 18px;font-size:clamp(12px,2vw,14px);font-weight:600;font-family:var(--font);cursor:pointer;min-height:44px;}
.skeleton-card{min-height:120px;justify-content:center;}
.sk-line{height:12px;border-radius:6px;background:linear-gradient(90deg,#eee 25%,#f7f7f7 50%,#eee 75%);background-size:200% 100%;animation:skShimmer 1.3s infinite;margin-bottom:8px;}
.sk-line.sk-lg{height:18px;width:55%;}
.sk-line.sk-sm{width:35%;}
@keyframes skShimmer{0%{background-position:200% 0;}100%{background-position:-200% 0;}}

.history-section{max-width:900px;margin:0 auto;padding:0 clamp(8px,2vw,16px) 16px;}
.history-head{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;}
.history-head h6{margin:0;font-weight:700;color:var(--text-primary);font-size:clamp(13px,2.2vw,15px);display:flex;align-items:center;gap:6px;}
.btn-history-toggle{background:#fff;border:1px solid var(--border-light);color:var(--primary-dark);border-radius:999px;padding:6px 12px;font-size:clamp(10px,1.7vw,12px);font-weight:600;font-family:var(--font);cursor:pointer;min-height:36px;}
.dispatch-history{display:flex;flex-direction:column;gap:6px;}
.history-item{display:flex;align-items:center;gap:10px;background:#fff;border:1px solid var(--border-card);border-radius:14px;padding:10px 12px;cursor:pointer;text-align:left;font-family:var(--font);width:100%;}
.history-item:hover{background:var(--bg-card);}
.hi-order{font-size:clamp(11px,1.9vw,13px);font-weight:700;color:#fff;background:var(--primary);border-radius:10px;padding:3px 8px;min-width:34px;text-align:center;}
.hi-main{display:flex;flex-direction:column;flex:1;min-width:0;}
.hi-main strong{font-size:clamp(12px,2.1vw,14px);color:var(--text-primary);}
.hi-main small{font-size:clamp(10px,1.7vw,12px);color:var(--text-muted);}
.hi-time{font-size:clamp(11px,1.9vw,13px);font-weight:600;color:var(--primary-dark);}
.hi-queue{font-size:clamp(9px,1.5vw,11px);color:var(--text-muted);}
.hi-note{font-size:9px;font-weight:700;color:#8d6e00;background:#fff8e1;border:1px solid #f0d060;border-radius:999px;padding:2px 7px;white-space:nowrap;}
.history-empty{font-size:clamp(11px,1.9vw,13px);color:var(--text-muted);padding:10px 2px;}

.checklist{display:flex;flex-direction:column;gap:6px;margin-bottom:10px;}
.check-item{display:flex;gap:8px;align-items:flex-start;background:#fff;border:1px solid var(--border-light);border-radius:10px;padding:8px 10px;}
.check-item .ci-icon{flex-shrink:0;font-size:14px;line-height:1.4;}
.check-item.ok .ci-icon{color:#2e7d32;}
.check-item.warn .ci-icon{color:#b7791f;}
.check-item.bad .ci-icon{color:#c62828;}
.check-item .ci-text{display:flex;flex-direction:column;min-width:0;}
.check-item .ci-text strong{font-size:clamp(11px,1.9vw,13px);color:var(--text-primary);}
.check-item .ci-text small{font-size:clamp(10px,1.7vw,12px);color:var(--text-muted);}
.issue-groups{display:flex;flex-direction:column;gap:8px;margin-bottom:10px;}
.issue-group{background:#fff8e1;border:1px solid #f0d060;border-radius:10px;padding:8px 10px;}
.issue-group .ig-area{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#8d6e00;margin-bottom:4px;}
.issue-group ul{margin:0;padding-left:18px;}
.issue-group li{font-size:clamp(11px,1.9vw,13px);color:#6b5a10;}

.confirm-actions{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;}
.confirm-actions .btn-primary-app,.confirm-actions .btn-danger-app,.confirm-actions .btn-cancel-app{flex:1;min-width:120px;}
.modal-sticky-content{display:flex;flex-direction:column;overflow:hidden;}
.modal-sticky-scroll{flex:1 1 auto;min-height:0;overflow-y:auto;-webkit-overflow-scrolling:touch;padding-right:2px;}
.modal-sticky-actions{flex:0 0 auto;background:var(--bg-cream);border-top:1px solid var(--border-light);margin-top:12px;padding-top:10px;}
.modal-action-hint{font-size:11px;color:var(--text-muted);text-align:center;margin-bottom:8px;}
.bpr-bell{position:relative;display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;background:#fff;border:1px solid var(--border-light);border-radius:50%;color:var(--primary-dark);cursor:pointer;font-family:var(--font);flex:none;}
.bpr-bell:hover{background:var(--accent-light);border-color:var(--primary);}
.bpr-bell.active{background:var(--primary);color:#fff;border-color:var(--primary);}
.bpr-count{position:absolute;top:-5px;right:-5px;background:#d32f2f;color:#fff;font-size:10px;font-weight:700;min-width:18px;height:18px;line-height:18px;border-radius:999px;text-align:center;padding:0 4px;border:2px solid var(--bg-cream);}
.bpr-count[hidden]{display:none;}
.bpr-flyout{position:fixed;top:clamp(50px,7.5vh,70px);right:clamp(8px,2vw,20px);width:min(460px,94vw);max-height:80vh;background:var(--bg-cream);border:1px solid var(--border-light);border-radius:var(--radius-md,12px);box-shadow:0 12px 34px rgba(0,0,0,.22);z-index:1001;display:flex;flex-direction:column;overflow:hidden;}
.bpr-flyout[hidden]{display:none;}
.bpr-flyout-head{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 12px;background:#fff;border-bottom:1px solid var(--border-light);flex:none;}
.bpr-flyout-head h6{margin:0;font-size:13px;color:var(--primary-dark);display:inline-flex;align-items:center;gap:6px;}
.bpr-flyout-count{font-size:10px;font-weight:700;background:var(--accent-light);color:var(--primary-dark);border-radius:999px;padding:1px 7px;}
.bpr-flyout-count[hidden]{display:none;}
.bpr-flyout-body{flex:1 1 auto;min-height:0;overflow-y:auto;-webkit-overflow-scrolling:touch;padding:6px 8px 12px;}
.bpr-flyout-body .filter-row{margin-top:2px;padding:2px 2px 6px;justify-content:flex-start;}
.bpr-flyout-body .queue-tools{padding:0 2px 8px;flex-wrap:nowrap;}
.bpr-flyout-body .queue-tools input{min-width:0;flex:1;}
.bpr-flyout-body .queue-tools .btn-dispatch .txt{display:none;}
.confirm-warning{display:flex;gap:8px;align-items:flex-start;background:#fff8e1;border:1px solid #f0d060;border-radius:var(--radius-sm);padding:8px 10px;font-size:clamp(11px,1.9vw,13px);color:#8d6e00;margin:8px 0;}
.form-error{font-size:clamp(11px,1.9vw,13px);color:#c62828;background:#fdecea;border:1px solid #f5c2c7;border-radius:10px;padding:7px 10px;margin-bottom:8px;}
.queue-readiness-box{background:var(--bg-card);border:1px solid var(--border-card);border-radius:var(--radius-sm);padding:10px 12px;margin-bottom:10px;}
.qrb-head{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;}
.qrb-pos{font-size:10px;font-weight:700;color:var(--primary-dark);background:#fff;border:1px solid var(--border-card);border-radius:999px;padding:2px 8px;}
.qrb-reason{font-size:clamp(11px,1.9vw,13px);color:var(--text-primary);margin-bottom:8px;}
.qrb-items{display:flex;flex-wrap:wrap;gap:4px 10px;}
.qrb-item{display:inline-flex;align-items:center;gap:4px;font-size:clamp(10px,1.7vw,12px);font-weight:600;}
.qrb-item.ok{color:#2e7d32;}
.qrb-item.warn{color:#8d6e00;}
.qrb-item.bad{color:#c62828;}
.skip-title{font-size:clamp(15px,2.6vw,18px);font-weight:700;color:var(--text-primary);}
.skip-info{font-size:clamp(12px,2.1vw,14px);color:#444;margin:2px 0 4px;}
.skip-reason{font-size:clamp(11px,1.9vw,13px);color:var(--text-muted);background:var(--bg-cream);border-radius:10px;padding:6px 10px;margin-bottom:10px;}
.skip-fieldset{border:1px solid var(--border-light);border-radius:var(--radius-sm);padding:10px 12px;margin-bottom:10px;}
.skip-fieldset legend{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text-muted);padding:0 4px;}
.skip-option{display:flex;align-items:center;gap:8px;padding:7px 2px;font-size:clamp(12px,2.1vw,14px);color:var(--text-primary);min-height:40px;}
.skip-option input{width:18px;height:18px;accent-color:var(--primary);}
.skip-label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text-muted);margin-bottom:4px;}
.skip-notes{min-height:70px;background-image:none;padding-right:12px;}
.overtake-text{font-size:clamp(12px,2.1vw,14px);color:#444;margin:0 0 8px;}
.overtake-text strong{color:var(--text-primary);}

.detail-summary{background:var(--gradient);border-radius:var(--radius-sm);padding:clamp(12px,2.2vw,18px);color:#fff;margin-bottom:12px;}
.detail-summary .ds-top{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;}
.detail-summary .ds-bus{font-size:clamp(16px,3vw,20px);font-weight:700;display:flex;align-items:center;gap:6px;}
.detail-summary .ds-route{font-size:clamp(12px,2.2vw,14px);margin-top:4px;display:flex;align-items:center;gap:6px;}
.detail-summary .queue-status-pill{background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.35);}
.detail-sub{font-size:clamp(11px,1.9vw,13px);color:var(--text-muted);margin-bottom:10px;display:flex;align-items:center;gap:6px;}
.queue-detail-row{display:flex;justify-content:space-between;gap:10px;background:#fff;border:1px solid var(--border-light);border-radius:var(--radius-sm);padding:9px 12px;font-size:clamp(11px,1.9vw,13px);margin-bottom:6px;}
.queue-detail-row .qd-label{color:var(--text-muted);display:flex;align-items:center;gap:6px;}
.queue-detail-row .qd-value{font-weight:600;color:var(--text-primary);text-align:right;}

button:focus-visible,a:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible,.history-item:focus-visible{outline:3px solid var(--primary);outline-offset:2px;}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important;}}
@media(min-width:600px){
  .queue-grid{grid-template-columns:1fr 1fr;}
  .next-dispatch .nd-bus{font-size:clamp(22px,3.4vw,30px);}
}
</style>
</head>
<body>

<?php if (!$terminal_id): ?>

<div class="terminal-select">
    <div class="brand">
        <img src="/assets/images/passenger/buslogo.png" alt="">
        <span>R-Connect Dispatcher</span>
    </div>
    <h2>Select Terminal</h2>
    <p class="sub">Choose your assigned terminal to start managing dispatches</p>
    <div class="terminal-grid">
        <?php if (empty($terminals)): ?>
        <p style="grid-column:1/-1;color:#6b7280;padding:40px;">No terminals found</p>
        <?php endif; ?>
        <?php foreach ($terminals as $t): ?>
        <a href="/dispatcher/?d=<?php echo $_SESSION['dashboard_secret'] ?? ''; ?>&route=set-terminal&terminal_id=<?php echo $t['id']; ?>&terminal_name=<?php echo urlencode($t['terminal_name']); ?>" style="text-decoration:none;">
        <div class="terminal-card">
            <div class="icon-wrap">
                <i class="bi bi-building"></i>
            </div>
            <h5><?php echo htmlspecialchars($t['terminal_name'], ENT_QUOTES, 'UTF-8'); ?></h5>
            <?php if (!empty($t['terminal_code'])): ?>
            <span class="code"><?php echo htmlspecialchars($t['terminal_code'], ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
            <p><?php echo htmlspecialchars(trim(($t['city']??'').($t['city']&&$t['province']?', ':'').($t['province']??'')), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php else: ?>

<?php
// The shared branding header calls passenger_t(); load its helper before the
// include so the dashboard cannot fatal with an undefined function.
require_once __DIR__ . '/../passenger/utils/i18n.php';
include __DIR__ . '/../passenger/views/layout/header.php';
?>

<div class="header-bar">
  <div class="info">
    <h5><i class="bi bi-building"></i> <?php echo htmlspecialchars($terminal_name); ?></h5>
    <div class="sub"><?php echo htmlspecialchars($name); ?></div>
  </div>
  <div class="header-actions">
    <select id="languageSelect" class="lang-select" onchange="changeLanguage(this.value)" aria-label="<?php echo htmlspecialchars($translations['app.language'] ?? 'Language'); ?>">
      <?php foreach ($languages as $code => $label): ?>
      <option value="<?php echo htmlspecialchars($code); ?>"<?php echo $code === $lang ? ' selected' : ''; ?>><?php echo htmlspecialchars($translations[$langKeys[$code] ?? 'app.english'] ?? $label); ?></option>
      <?php endforeach; ?>
    </select>
    <button class="hdr-btn hdr-print" onclick="printReport()" title="Print Report" aria-label="Print Report"><i class="bi bi-printer"></i><span class="lbl">Print</span></button>
    <a href="/dispatcher/?d=<?php echo $_SESSION['dashboard_secret'] ?? ''; ?>&route=clear-terminal" class="hdr-btn hdr-switch" title="Switch Terminal" aria-label="Switch Terminal"><i class="bi bi-arrow-left-right"></i><span class="lbl">Switch Terminal</span></a>
    <button class="hdr-btn hdr-logout" onclick="logout()" title="Logout" aria-label="Logout"><i class="bi bi-box-arrow-right"></i><span class="lbl">Logout</span></button>
  </div>
</div>

<div class="clock-row">
  <span id="liveClock"></span>
  <span class="clock-end">
    <span id="liveDate"></span>
    <button type="button" class="bpr-bell" id="bprBellBtn" onclick="toggleProblemReports()" aria-haspopup="true" aria-expanded="false" aria-label="<?php echo htmlspecialchars($translations['problems.title'] ?? 'Bus Problem Reports'); ?>">
      <i class="bi bi-bell"></i>
      <span class="bpr-count" id="bprBellCount" hidden>0</span>
    </button>
  </span>
</div>

<div class="bpr-flyout" id="problemReportsPanel" hidden aria-label="<?php echo htmlspecialchars($translations['problems.title'] ?? 'Bus Problem Reports'); ?>">
  <div class="bpr-flyout-head">
    <h6><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($translations['problems.title'] ?? 'Bus Problem Reports'); ?> <span class="bpr-flyout-count" id="bprFlyoutCount" hidden>0</span></h6>
    <button class="modal-close" onclick="toggleProblemReports()" aria-label="<?php echo htmlspecialchars($translations['common.close'] ?? 'Close'); ?>"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="bpr-flyout-body">
    <div class="filter-row" id="problemFilterRow" role="group" aria-label="<?php echo htmlspecialchars($translations['problems.title'] ?? 'Bus Problem Reports'); ?>">
      <button type="button" class="filter-chip active" onclick="setProblemStatusFilter(this,'')"><?php echo htmlspecialchars($translations['summary.all'] ?? 'All'); ?></button>
      <button type="button" class="filter-chip" onclick="setProblemStatusFilter(this,'waiting')"><?php echo htmlspecialchars($translations['problems.waiting'] ?? 'Waiting'); ?></button>
      <button type="button" class="filter-chip" onclick="setProblemStatusFilter(this,'acknowledged')"><?php echo htmlspecialchars($translations['problems.acknowledged'] ?? 'Acknowledged'); ?></button>
      <button type="button" class="filter-chip" onclick="setProblemStatusFilter(this,'resolved')"><?php echo htmlspecialchars($translations['problems.resolved'] ?? 'Resolved'); ?></button>
    </div>
    <div class="queue-tools">
      <input type="text" id="problemSearchInput" placeholder="<?php echo htmlspecialchars($translations['problems.search_placeholder'] ?? 'Search driver, bus, trip, or type...'); ?>" onkeydown="if(event.key==='Enter'){loadProblemReports();}" aria-label="<?php echo htmlspecialchars($translations['problems.search_placeholder'] ?? 'Search problem reports'); ?>">
      <button type="button" class="btn-dispatch" onclick="loadProblemReports()" aria-label="<?php echo htmlspecialchars($translations['common.refresh'] ?? 'Refresh'); ?>"><i class="bi bi-arrow-clockwise"></i><span class="txt"><?php echo htmlspecialchars($translations['common.refresh'] ?? 'Refresh'); ?></span></button>
    </div>
    <div class="dispatch-list" id="problemReportsList" aria-live="polite"></div>
  </div>
</div>

<div class="page-title-row">
  <h1 class="page-title"><i class="bi bi-list-check"></i> <?php echo htmlspecialchars($translations['app.title'] ?? 'Manage Trip Queues'); ?></h1>
</div>

<div class="queue-view" id="queueView">
  <div class="filter-row" id="queueFilterRow" role="group" aria-label="<?php echo htmlspecialchars($translations['app.title'] ?? 'Manage Trip Queues'); ?>">
    <button type="button" class="filter-chip active" data-status="all" data-label="<?php echo htmlspecialchars($translations['summary.all'] ?? 'All'); ?>" onclick="setQueueFilter(this,'all')"><?php echo htmlspecialchars($translations['summary.all'] ?? 'All'); ?></button>
    <button type="button" class="filter-chip" data-status="waiting_for_driver" data-label="<?php echo htmlspecialchars($translations['summary.waiting_for_driver'] ?? 'Waiting for Driver'); ?>" onclick="setQueueFilter(this,'waiting_for_driver')"><?php echo htmlspecialchars($translations['summary.waiting_for_driver'] ?? 'Waiting for Driver'); ?></button>
    <button type="button" class="filter-chip" data-status="driver_assigned" data-label="<?php echo htmlspecialchars($translations['summary.driver_assigned'] ?? 'Driver Assigned'); ?>" onclick="setQueueFilter(this,'driver_assigned')"><?php echo htmlspecialchars($translations['summary.driver_assigned'] ?? 'Driver Assigned'); ?></button>
    <button type="button" class="filter-chip" data-status="checked_in" data-label="<?php echo htmlspecialchars($translations['summary.checked_in'] ?? 'Checked In'); ?>" onclick="setQueueFilter(this,'checked_in')"><?php echo htmlspecialchars($translations['summary.checked_in'] ?? 'Checked In'); ?></button>
    <button type="button" class="filter-chip" data-status="ready" data-label="<?php echo htmlspecialchars($translations['summary.ready'] ?? 'Ready to Dispatch'); ?>" onclick="setQueueFilter(this,'ready')"><?php echo htmlspecialchars($translations['summary.ready'] ?? 'Ready to Dispatch'); ?></button>
    <button type="button" class="filter-chip" data-status="departed" data-label="<?php echo htmlspecialchars($translations['summary.on_route'] ?? 'On Route'); ?>" onclick="setQueueFilter(this,'departed')"><?php echo htmlspecialchars($translations['summary.on_route'] ?? 'On Route'); ?></button>
  </div>

  <div class="next-dispatch" id="nextDispatchPanel" aria-live="polite"></div>

  <div class="why-not-card" id="whyNotPanel" style="display:none;" aria-live="polite"></div>

  <div class="queue-tools">
    <input type="text" id="searchInput" placeholder="<?php echo htmlspecialchars($translations['common.search_placeholder'] ?? 'Search bus, driver, or trip...'); ?>" oninput="onSearch()" aria-label="<?php echo htmlspecialchars($translations['common.search_placeholder'] ?? 'Search bus, driver, or trip...'); ?>">
    <select id="routeFilter" class="form-field route-filter" onchange="onRouteFilter()" aria-label="<?php echo htmlspecialchars($translations['common.all_routes'] ?? 'All Routes'); ?>"><option value=""><?php echo htmlspecialchars($translations['common.all_routes'] ?? 'All Routes'); ?></option></select>
    <button class="btn-dispatch" onclick="showCreateModal()" aria-label="<?php echo htmlspecialchars($translations['common.dispatch_bus'] ?? 'Dispatch Bus'); ?>"><i class="bi bi-send"></i><span class="txt"><?php echo htmlspecialchars($translations['common.dispatch_bus'] ?? 'Dispatch Bus'); ?></span></button>
  </div>

  <div class="queue-updated-row">
    <span class="queue-updated" id="queueUpdatedLabel" aria-live="polite"></span>
    <span class="queue-stale" id="queueStaleLabel" style="display:none;"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($translations['common.stale'] ?? 'Queue may be outdated'); ?></span>
    <button type="button" class="btn-refresh" onclick="loadQueue({force:true})" aria-label="<?php echo htmlspecialchars($translations['common.refresh'] ?? 'Refresh'); ?>"><i class="bi bi-arrow-clockwise"></i> <?php echo htmlspecialchars($translations['common.refresh'] ?? 'Refresh'); ?></button>
  </div>

  <div class="queue-grid" id="queueList" aria-live="polite"></div>

  <div class="history-section">
    <div class="history-head">
      <h6><i class="bi bi-send-check"></i> <?php echo htmlspecialchars($translations['history.title'] ?? "Today's Actual Dispatch"); ?></h6>
      <button type="button" class="btn-history-toggle" id="historySearchBtn" onclick="toggleHistorySearch()" aria-expanded="false" aria-controls="historySearchPanel"><i class="bi bi-search"></i> <?php echo htmlspecialchars($translations['history.search'] ?? 'Search Older Trips'); ?></button>
    </div>
    <div class="dispatch-history" id="dispatchHistoryList"></div>
    <div id="historySearchPanel" style="display:none;">
      <div class="hist-bar" id="historyControls">
        <span class="hb-label"><i class="bi bi-clock-history"></i> History</span>
        <div class="hb-dates">
          <input type="date" id="histDateFrom" aria-label="History from date">
          <span class="hb-sep">—</span>
          <input type="date" id="histDateTo" aria-label="History to date">
        </div>
        <button class="hb-go" onclick="loadHistory()" aria-label="Search history"><i class="bi bi-search"></i></button>
        <span class="hb-count" id="histResultBadge">all</span>
      </div>
      <div class="pg-row" id="paginationControls">
        <button class="pg-btn" id="prevPageBtn" onclick="changePage(-1)"><i class="bi bi-chevron-left"></i> Prev</button>
        <span class="pg-info" id="pageInfo">Page 1</span>
        <button class="pg-btn" id="nextPageBtn" onclick="changePage(1)">Next <i class="bi bi-chevron-right"></i></button>
      </div>

      <div class="dispatch-list" id="dispatchList"></div>
    </div>
  </div>

  </div>

<div class="bottom-action-bar" id="bottomBar" style="display:none;">
  <div class="bab-info" id="babLabel"></div>
  <button type="button" class="bab-btn" id="babDispatchBtn" onclick="dispatchNextFromBar()"><i class="bi bi-send"></i> <?php echo htmlspecialchars($translations['bottom.dispatch'] ?? 'DISPATCH BUS'); ?></button>
</div>

<div class="modal-overlay" id="createModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6><i class="bi bi-send"></i> <?php echo htmlspecialchars($translations['common.dispatch_bus'] ?? 'Dispatch Bus'); ?></h6>
      <button class="modal-close" onclick="closeModal('createModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <label class="skip-label" for="newBus"><?php echo htmlspecialchars($translations['details.bus'] ?? 'Bus'); ?></label>
    <select id="newBus" class="form-field"><option value=""><?php echo htmlspecialchars($translations['common.loading_queue'] ?? 'Loading...'); ?></option></select>
    <label class="skip-label" for="newDriver"><?php echo htmlspecialchars($translations['details.driver'] ?? 'Driver'); ?></label>
    <select id="newDriver" class="form-field"><option value=""><?php echo htmlspecialchars($translations['common.loading_queue'] ?? 'Loading...'); ?></option></select>
    <div id="busQueueBadge" class="queue-readiness-box" style="display:none;"></div>
    <label class="skip-label" for="newRoute"><?php echo htmlspecialchars($translations['details.route'] ?? 'Route'); ?></label>
    <select id="newRoute" class="form-field"><option value=""><?php echo htmlspecialchars($translations['common.loading_queue'] ?? 'Loading...'); ?></option></select>
    <label class="skip-label" for="newDestTerminal"><?php echo htmlspecialchars($translations['details.destination'] ?? 'Destination'); ?></label>
    <select id="newDestTerminal" class="form-field"><option value=""><?php echo htmlspecialchars($translations['common.loading_queue'] ?? 'Loading...'); ?></option></select>
    <label class="skip-label" for="newPassengerCount"><?php echo htmlspecialchars($translations['details.passengers'] ?? 'Passengers Onboard'); ?></label>
    <input type="number" id="newPassengerCount" class="form-field" placeholder="<?php echo htmlspecialchars($translations['details.passengers'] ?? 'Passengers Onboard'); ?>" min="0">

    <!-- Dedicated Fuel & Odometer Information Section -->
    <div class="fuel-section" style="margin:4px 0 12px;border:1px solid var(--border-light);border-radius:var(--radius-sm);padding:12px;background:#fff;">
      <div style="font-weight:700;color:var(--text-primary);font-size:13px;margin-bottom:8px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;">
        <span><i class="bi bi-fuel-pump" style="color:var(--primary);margin-right:5px;"></i>Fuel & Odometer Information</span>
        <span id="fuelPriceBadge" style="font-size:11px;font-weight:600;color:var(--primary-dark);background:var(--accent-light);padding:2px 8px;border-radius:999px;">Diesel: Loading...</span>
      </div>

      <div id="fuelDispatchBadge" style="font-size:11px;color:var(--text-muted);margin-bottom:8px;padding:6px 10px;background:var(--bg-card);border-radius:10px;border:1px dashed var(--border-card);display:none;">
        <i class="bi bi-check-circle text-success"></i> Bus: <strong id="fuelLinkedBus">-</strong> &bull; Driver: <strong id="fuelLinkedDriver">-</strong>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
        <div>
          <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Starting ODO (KM)</label>
          <input type="number" step="any" id="newStartingOdo" class="form-field" style="margin-bottom:0;" placeholder="e.g. 1001">
        </div>
        <div>
          <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Bus KPL (KM/L)</label>
          <input type="number" step="0.01" id="newKpl" class="form-field" style="margin-bottom:0;" placeholder="e.g. 9" value="9">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div>
          <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Current Fuel / Liters</label>
          <input type="number" step="0.01" id="newActualLiters" class="form-field" style="margin-bottom:0;" placeholder="e.g. 50">
          <div id="recentFuelHint" style="font-size:9.5px;color:var(--text-muted);margin-top:3px;"></div>
        </div>
        <div>
          <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Ending ODO</label>
          <input type="text" id="newEndingOdo" class="form-field" style="margin-bottom:0;background:#f5f5f5;color:#888;cursor:not-allowed;" value="Not yet recorded" disabled readonly>
        </div>
      </div>
    </div>

    <div class="modal-time-box"><i class="bi bi-clock"></i> Dispatch Time: <span id="dispatchTimeDisplay">--:--:--</span></div>
    <button class="btn-primary-app" onclick="createDispatch()"><i class="bi bi-send"></i> Dispatch Now</button>
  </div>
</div>

<div class="modal-overlay" id="arriveModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6><i class="bi bi-flag"></i> Record Arrival</h6>
      <button class="modal-close" onclick="closeModal('arriveModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <p style="font-size:14px;color:var(--text-muted);margin-bottom:8px;">Confirm arrival for this trip?</p>

    <div id="arriveFuelBox" style="margin-bottom:12px;padding:12px;background:#fff;border:1px solid var(--border-light);border-radius:var(--radius-sm);">
      <div style="font-weight:600;font-size:12px;color:var(--text-primary);margin-bottom:4px;"><i class="bi bi-speedometer2" style="color:var(--primary);"></i> Fuel & Odometer Completion</div>
      <div style="font-size:11px;color:var(--text-muted);margin-bottom:8px;" id="arriveTripOdoInfo">Starting ODO: - KM &bull; KPL: -</div>
      <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Ending ODO (KM)</label>
      <input type="number" step="any" id="arriveEndingOdo" class="form-field" placeholder="e.g. 1076" oninput="onArriveOdoChange()">
      
      <div id="arriveCalcPreview" style="display:none;margin-top:8px;padding:8px 10px;background:var(--bg-card);border:1px solid var(--border-card);border-radius:10px;font-size:11px;color:var(--text-primary);">
        <div style="display:flex;justify-content:space-between;margin-bottom:2px;">
          <span>Distance Traveled:</span> <strong id="arriveDistText">-</strong>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span>Expected Fuel Consumption:</span> <strong id="arriveExpFuelText">-</strong>
        </div>
      </div>
    </div>

    <p style="font-size:12px;color:var(--text-muted);margin:6px 0 12px;"><i class="bi bi-info-circle"></i> Arrival time and passenger count will be auto-calculated.</p>
    <button class="btn-primary-app" id="arriveConfirmBtn" onclick="recordArrival()"><i class="bi bi-check-circle"></i> Confirm Arrival</button>
  </div>
</div>

<div class="modal-overlay" id="tripDetailModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6 id="tripDetailTitle"><i class="bi bi-info-circle"></i> Trip Details</h6>
      <button class="modal-close" onclick="closeModal('tripDetailModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="trip-detail-grid" id="tripDetailBody"></div>
    <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;" id="tripDetailActions"></div>
  </div>
</div>

<div class="modal-overlay" id="resolveProblemModal">
  <div class="modal-content modal-sticky-content">
    <div class="modal-header">
      <h6><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($translations['problems.resolve'] ?? 'Resolve Problem Report'); ?></h6>
      <button class="modal-close" onclick="closeModal('resolveProblemModal')" aria-label="<?php echo htmlspecialchars($translations['common.close'] ?? 'Close'); ?>"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-sticky-scroll">
      <div id="resolveProblemSummary" style="margin-bottom:12px;padding:10px 12px;background:#fff;border:1px solid var(--border-light);border-radius:var(--radius-sm);font-size:12px;color:var(--text-primary);"></div>
      <label class="skip-option">
        <input type="checkbox" id="resolveReplacementRequired">
        <span><?php echo htmlspecialchars($translations['problems.replacement_required'] ?? 'Trip requires bus replacement'); ?></span>
      </label>
    </div>
    <div class="modal-sticky-actions">
      <div class="confirm-actions">
        <button type="button" class="btn-action btn-view" style="flex:1;min-height:44px;" onclick="closeModal('resolveProblemModal')"><i class="bi bi-x-lg"></i> <?php echo htmlspecialchars($translations['common.cancel'] ?? 'Cancel'); ?></button>
        <button type="button" class="btn-primary-app" id="resolveProblemConfirmBtn" style="flex:1;" onclick="resolveProblemReport()"><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($translations['problems.resolve_action'] ?? 'RESOLVE'); ?></button>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="replacementBusModal">
  <div class="modal-content modal-sticky-content">
    <div class="modal-header">
      <h6><i class="bi bi-arrow-left-right"></i> <?php echo htmlspecialchars($translations['problems.assign_replacement'] ?? 'Assign Replacement Bus'); ?></h6>
      <button class="modal-close" onclick="closeModal('replacementBusModal')" aria-label="<?php echo htmlspecialchars($translations['common.close'] ?? 'Close'); ?>"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-sticky-scroll">
      <div id="replacementReportSummary" style="margin-bottom:8px;padding:10px 12px;background:#fff;border:1px solid var(--border-light);border-radius:var(--radius-sm);font-size:12px;color:var(--text-primary);"></div>
      <div id="replacementBusList"></div>
    </div>
    <div class="modal-sticky-actions">
      <div id="replacementDriverRow" style="display:none;margin-bottom:10px;">
        <label class="skip-label" for="replacementDriverSelect"><i class="bi bi-person-fill-gear"></i> <?php echo htmlspecialchars($translations['problems.replacement_driver'] ?? 'Replacement Driver'); ?></label>
        <select id="replacementDriverSelect" class="form-field"></select>
        <div style="font-size:10.5px;color:var(--text-muted);margin-top:3px;"><?php echo htmlspecialchars($translations['problems.replacement_driver_hint'] ?? 'Defaults to the bus\u2019s assigned driver when left empty.'); ?></div>
      </div>
      <div class="modal-action-hint" id="replacementSelectedLabel"><?php echo htmlspecialchars($translations['problems.select_bus_hint'] ?? 'Select a bus to continue'); ?></div>
      <div class="confirm-actions">
        <button type="button" class="btn-action btn-view" style="flex:1;min-height:44px;" onclick="closeModal('replacementBusModal')"><i class="bi bi-x-lg"></i> <?php echo htmlspecialchars($translations['common.cancel'] ?? 'Cancel'); ?></button>
        <button type="button" class="btn-primary-app" id="replacementAssignBtn" style="flex:1;" disabled onclick="assignReplacementBus()"><i class="bi bi-arrow-left-right"></i> <?php echo htmlspecialchars($translations['problems.assign_action'] ?? 'ASSIGN'); ?></button>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="editFuelModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6><i class="bi bi-fuel-pump"></i> Fuel & Odometer Record</h6>
      <button class="modal-close" onclick="closeModal('editFuelModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    
    <div id="editFuelTripBadge" style="margin-bottom:12px;padding:8px 12px;background:var(--bg-card);border:1px solid var(--border-card);border-radius:var(--radius-sm);font-size:12px;color:var(--text-primary);">
      <div><strong>Trip #<span id="editFuelTripNo">-</span></strong> &bull; <span id="editFuelRoute">-</span></div>
      <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">Bus: <span id="editFuelBus">-</span> &bull; Driver: <span id="editFuelDriver">-</span></div>
    </div>

    <input type="hidden" id="editFuelTripId" value="">
    <input type="hidden" id="editFuelLogId" value="">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
      <div>
        <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Starting ODO (KM)</label>
        <input type="number" step="any" id="editStartingOdo" class="form-field" placeholder="1001" oninput="onEditFuelCalc()">
      </div>
      <div>
        <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Ending ODO (KM) <span style="color:#c62828;">*</span></label>
        <input type="number" step="any" id="editEndingOdo" class="form-field" placeholder="1076" oninput="onEditFuelCalc()">
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
      <div>
        <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Bus KPL (KM/L)</label>
        <input type="number" step="0.01" id="editKpl" class="form-field" placeholder="9.0" oninput="onEditFuelCalc()">
      </div>
      <div>
        <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Actual Liters (L)</label>
        <input type="number" step="0.01" id="editActualLiters" class="form-field" placeholder="50" oninput="onEditFuelCalc()">
      </div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 10px;background:#fff;border:1px solid var(--border-light);border-radius:10px;font-size:11px;color:var(--text-muted);margin-bottom:10px;">
      <span>Fuel Price (Diesel):</span>
      <strong id="editFuelPriceText" style="color:var(--primary-dark);">₱--/L</strong>
    </div>

    <div style="background:var(--bg-card);border:1.5px solid var(--border-card);border-radius:var(--radius-sm);padding:10px 12px;margin-bottom:14px;font-size:12px;">
      <div style="font-weight:700;color:var(--primary-dark);margin-bottom:6px;font-size:11px;text-transform:uppercase;letter-spacing:0.3px;"><i class="bi bi-calculator"></i> System Calculations</div>
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
        <span style="color:var(--text-muted);">Distance Traveled:</span>
        <strong id="calcDistanceText" style="color:var(--text-primary);">-</strong>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
        <span style="color:var(--text-muted);">Expected Fuel Consumption:</span>
        <strong id="calcExpectedFuelText" style="color:var(--text-primary);">-</strong>
      </div>
      <div style="display:flex;justify-content:space-between;">
        <span style="color:var(--text-muted);">Total Fuel Cost:</span>
        <strong id="calcFuelCostText" style="color:#2e7d32;">-</strong>
      </div>
    </div>

    <div style="margin-bottom:12px;">
      <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Reason for Edit <span style="color:#c62828;">*</span></label>
      <input type="text" id="editFuelReason" class="form-field" placeholder="Why is this record being corrected?">
    </div>

    <button class="btn-primary-app" id="saveFuelBtn" onclick="saveTripFuelEdit()"><i class="bi bi-check-circle"></i> Save Fuel Record</button>
  </div>
</div>

<div class="modal-overlay" id="skipModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6><i class="bi bi-slash-circle"></i> <?php echo htmlspecialchars($translations['skip.title'] ?? 'SKIP BUS'); ?></h6>
      <button class="modal-close" onclick="closeModal('skipModal')" aria-label="<?php echo htmlspecialchars($translations['common.close'] ?? 'Close'); ?>"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="skip-title" id="skipBusTitle"></div>
    <div class="skip-info" id="skipBusInfo"></div>
    <div class="skip-reason" id="skipBusReason"></div>
    <fieldset class="skip-fieldset">
      <legend><?php echo htmlspecialchars($translations['skip.reason'] ?? 'Reason'); ?></legend>
      <label class="skip-option"><input type="radio" name="skipReason" value="bus_not_present"> <?php echo htmlspecialchars($translations['reasons.bus_not_present'] ?? 'Bus not present'); ?></label>
      <label class="skip-option"><input type="radio" name="skipReason" value="driver_unavailable"> <?php echo htmlspecialchars($translations['reasons.driver_unavailable'] ?? 'Driver unavailable'); ?></label>
      <label class="skip-option"><input type="radio" name="skipReason" value="inspection_issue"> <?php echo htmlspecialchars($translations['reasons.inspection_issue'] ?? 'Inspection issue'); ?></label>
      <label class="skip-option"><input type="radio" name="skipReason" value="fuel_issue"> <?php echo htmlspecialchars($translations['reasons.fuel_issue'] ?? 'Fuel issue'); ?></label>
      <label class="skip-option"><input type="radio" name="skipReason" value="operational_delay"> <?php echo htmlspecialchars($translations['reasons.operational_delay'] ?? 'Operational delay'); ?></label>
      <label class="skip-option"><input type="radio" name="skipReason" value="other"> <?php echo htmlspecialchars($translations['reasons.other'] ?? 'Other'); ?></label>
    </fieldset>
    <label class="skip-label" for="skipNotes"><?php echo htmlspecialchars($translations['skip.notes'] ?? 'Notes (optional)'); ?></label>
    <textarea id="skipNotes" class="form-field skip-notes" rows="2" maxlength="500" placeholder="<?php echo htmlspecialchars($translations['skip.notes_placeholder'] ?? 'Add context for this decision...'); ?>"></textarea>
    <div class="form-error" id="skipError" style="display:none;"></div>
    <div class="confirm-actions">
      <button type="button" class="btn-cancel-app" onclick="closeModal('skipModal')"><?php echo htmlspecialchars($translations['common.cancel'] ?? 'Cancel'); ?></button>
      <button type="button" class="btn-danger-app" id="skipConfirmBtn" onclick="submitSkip()"><i class="bi bi-slash-circle"></i> <?php echo htmlspecialchars($translations['skip.button'] ?? 'SKIP BUS'); ?></button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="overtakeModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($translations['overtake.title'] ?? 'OUT-OF-ORDER DISPATCH'); ?></h6>
      <button class="modal-close" onclick="closeModal('overtakeModal')" aria-label="<?php echo htmlspecialchars($translations['common.close'] ?? 'Close'); ?>"><i class="bi bi-x-lg"></i></button>
    </div>
    <p class="overtake-text" id="overtakeReady"></p>
    <p class="overtake-text"><?php echo htmlspecialchars($translations['overtake.body'] ?? ':bus is Queue #:position.'); ?> <strong id="overtakeTarget"></strong></p>
    <label class="skip-label" for="overtakeReason"><?php echo htmlspecialchars($translations['overtake.reason'] ?? 'Reason'); ?></label>
    <select id="overtakeReason" class="form-field">
      <option value=""><?php echo htmlspecialchars($translations['overtake.select'] ?? 'Select reason'); ?></option>
      <option value="<?php echo htmlspecialchars($translations['overtake.reason.operational_need'] ?? 'Operational need'); ?>"><?php echo htmlspecialchars($translations['overtake.reason.operational_need'] ?? 'Operational need'); ?></option>
      <option value="<?php echo htmlspecialchars($translations['overtake.reason.passenger_load'] ?? 'Passenger load'); ?>"><?php echo htmlspecialchars($translations['overtake.reason.passenger_load'] ?? 'Passenger load'); ?></option>
      <option value="<?php echo htmlspecialchars($translations['overtake.reason.route_demand'] ?? 'Route demand'); ?>"><?php echo htmlspecialchars($translations['overtake.reason.route_demand'] ?? 'Route demand'); ?></option>
      <option value="<?php echo htmlspecialchars($translations['overtake.reason.vehicle_swap'] ?? 'Vehicle swap'); ?>"><?php echo htmlspecialchars($translations['overtake.reason.vehicle_swap'] ?? 'Vehicle swap'); ?></option>
      <option value="<?php echo htmlspecialchars($translations['overtake.reason.other'] ?? 'Other'); ?>"><?php echo htmlspecialchars($translations['overtake.reason.other'] ?? 'Other'); ?></option>
    </select>
    <div class="form-error" id="overtakeError" style="display:none;"></div>
    <div class="confirm-actions">
      <button type="button" class="btn-cancel-app" onclick="closeModal('overtakeModal')"><?php echo htmlspecialchars($translations['common.cancel'] ?? 'Cancel'); ?></button>
      <button type="button" class="btn-primary-app" onclick="submitOvertake()"><?php echo htmlspecialchars($translations['overtake.continue'] ?? 'CONTINUE'); ?></button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="confirmDispatchModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6><i class="bi bi-send"></i> <?php echo htmlspecialchars($translations['confirm.title'] ?? 'CONFIRM DISPATCH'); ?></h6>
      <button class="modal-close" onclick="closeModal('confirmDispatchModal')" aria-label="<?php echo htmlspecialchars($translations['common.close'] ?? 'Close'); ?>"><i class="bi bi-x-lg"></i></button>
    </div>
    <div id="confirmDispatchBody"></div>
    <div class="confirm-warning" id="confirmDispatchWarning" style="display:none;"></div>
    <p class="overtake-text" style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars($translations['confirm.note'] ?? 'Dispatching this trip will record the official dispatch time and actual dispatch order.'); ?></p>
    <div class="confirm-actions">
      <button type="button" class="btn-cancel-app" onclick="closeModal('confirmDispatchModal')"><?php echo htmlspecialchars($translations['common.cancel'] ?? 'Cancel'); ?></button>
      <button type="button" class="btn-primary-app" id="confirmDispatchBtn" onclick="confirmQueueDispatch()"><i class="bi bi-send"></i> <?php echo htmlspecialchars($translations['confirm.button'] ?? 'CONFIRM DISPATCH'); ?></button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="assignDriverModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6 id="assignDriverTitle"><i class="bi bi-person-plus"></i> <?php echo htmlspecialchars($translations['assign.title'] ?? 'ASSIGN DRIVER'); ?></h6>
      <button class="modal-close" onclick="closeModal('assignDriverModal')" aria-label="<?php echo htmlspecialchars($translations['common.close'] ?? 'Close'); ?>"><i class="bi bi-x-lg"></i></button>
    </div>
    <div id="assignDriverBody"></div>
    <label class="skip-label" for="assignDriverSelect"><?php echo htmlspecialchars($translations['assign.driver'] ?? 'Driver'); ?></label>
    <select id="assignDriverSelect" class="form-field"><option value=""><?php echo htmlspecialchars($translations['assign.select'] ?? 'Select driver'); ?></option></select>
    <div class="form-error" id="assignDriverError" style="display:none;"></div>
    <div class="confirm-actions">
      <button type="button" class="btn-cancel-app" onclick="closeModal('assignDriverModal')"><?php echo htmlspecialchars($translations['common.cancel'] ?? 'Cancel'); ?></button>
      <button type="button" class="btn-primary-app" id="assignDriverConfirmBtn" onclick="submitAssignDriver()"><i class="bi bi-person-check"></i> <?php echo htmlspecialchars($translations['assign.confirm'] ?? 'ASSIGN DRIVER'); ?></button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="prepareModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6 id="prepareTitle"><i class="bi bi-clipboard-check"></i> <?php echo htmlspecialchars($translations['prepare.title'] ?? 'PREPARE TRIP'); ?></h6>
      <button class="modal-close" onclick="closeModal('prepareModal')" aria-label="<?php echo htmlspecialchars($translations['common.close'] ?? 'Close'); ?>"><i class="bi bi-x-lg"></i></button>
    </div>
    <div id="prepareBody"></div>
    <label class="skip-label" for="preparePassengerCount"><?php echo htmlspecialchars($translations['prepare.passengers'] ?? 'Passengers Onboard'); ?></label>
    <input type="number" id="preparePassengerCount" class="form-field" min="0" value="0">
    <div class="fuel-section" style="margin:4px 0 12px;border:1px solid var(--border-light);border-radius:var(--radius-sm);padding:12px;background:#fff;">
      <div style="font-weight:700;color:var(--text-primary);font-size:13px;margin-bottom:8px;"><i class="bi bi-fuel-pump" style="color:var(--primary);margin-right:5px;"></i>Fuel &amp; Odometer Information</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
        <div>
          <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Starting ODO (KM)</label>
          <input type="number" step="any" id="prepareStartingOdo" class="form-field" style="margin-bottom:0;" placeholder="e.g. 1001">
        </div>
        <div>
          <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Bus KPL (KM/L)</label>
          <input type="number" step="0.01" id="prepareKpl" class="form-field" style="margin-bottom:0;" placeholder="e.g. 9" value="9">
        </div>
      </div>
      <label style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;display:block;">Fuel / Liters</label>
      <input type="number" step="0.01" id="prepareLiters" class="form-field" style="margin-bottom:0;" placeholder="e.g. 50">
    </div>
    <div class="form-error" id="prepareError" style="display:none;"></div>
    <div class="confirm-actions">
      <button type="button" class="btn-cancel-app" onclick="closeModal('prepareModal')"><?php echo htmlspecialchars($translations['common.cancel'] ?? 'Cancel'); ?></button>
      <button type="button" class="btn-primary-app" id="prepareDispatchBtn" onclick="dispatchFromPrepare()"><i class="bi bi-send"></i> <?php echo htmlspecialchars($translations['action.dispatch'] ?? 'Dispatch'); ?></button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="dispatchSuccessModal">
  <div class="modal-content">
    <div class="modal-header">
      <h6><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($translations['success.title'] ?? 'TRIP DISPATCHED'); ?></h6>
      <button class="modal-close" onclick="closeModal('dispatchSuccessModal')" aria-label="<?php echo htmlspecialchars($translations['common.close'] ?? 'Close'); ?>"><i class="bi bi-x-lg"></i></button>
    </div>
    <div id="dispatchSuccessBody"></div>
    <div class="confirm-actions">
      <button type="button" class="btn-action btn-view" id="successViewTripBtn" style="flex:1;min-height:44px;"><i class="bi bi-eye"></i> <?php echo htmlspecialchars($translations['success.view_trip'] ?? 'VIEW TRIP'); ?></button>
      <button type="button" class="btn-primary-app" style="flex:1;" onclick="closeModal('dispatchSuccessModal')"><?php echo htmlspecialchars($translations['success.back'] ?? 'BACK TO QUEUE'); ?></button>
    </div>
  </div>
</div>

<script>
const API = '/dispatcher/api_<?php echo $urls['api']; ?>/';
const TOKEN = '<?php echo $token; ?>';
const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
const TERMINAL_ID = <?php echo $terminal_id; ?>;
const I18N = <?php echo json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const LANG = '<?php echo htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'); ?>';
function t(key, fallback, replace){
  let text = (I18N && Object.prototype.hasOwnProperty.call(I18N, key)) ? I18N[key] : (fallback !== undefined ? fallback : key);
  if(replace){
    Object.keys(replace).forEach(function(k){ text = text.split(':' + k).join(String(replace[k])); });
  }
  return text;
}
function changeLanguage(lang){
  try { localStorage.setItem('dispatcher_lang', lang); } catch(e) {}
  const url = new URL(window.location.href);
  url.searchParams.set('lang', lang);
  window.location.href = url.toString();
}
(function(){
  try {
    const stored = localStorage.getItem('dispatcher_lang');
    const params = new URLSearchParams(window.location.search);
    if(stored && stored !== LANG && !params.get('lang')){
      params.set('lang', stored);
      window.location.replace(window.location.pathname + '?' + params.toString());
    }
  } catch(e) {}
})();
let currentStatusFilter = '';
let arriveId = null;
let arriveStartOdo = null;
let cacheBuses = [], cacheDrivers = [], cacheRoutes = [];
const cacheTerminals = <?php echo json_encode($terminalList); ?>;
const defaultRoute = '<?php echo $defaultRoute; ?>';
const defaultDestTerminalId = <?php echo $defaultDestTerminalId; ?>;
let currentFuelPrice = 65.55;
let isDispatchLoading = false;
let isRecordingArrival = false;
let isSavingFuel = false;

// Fallback showAlert if /public/login/custom-alert.js is unavailable
if (typeof window.showAlert !== 'function') {
  window.showAlert = function(type, title, message, onConfirm) {
    const overlay = document.getElementById('customAlertOverlay');
    if (!overlay) {
      console.log('[' + type.toUpperCase() + '] ' + title + ': ' + message);
      if (typeof onConfirm === 'function') onConfirm();
      return;
    }
    const header = overlay.querySelector('.custom-alert-header');
    const iconEl = overlay.querySelector('.custom-alert-icon');
    const titleEl = overlay.querySelector('.custom-alert-title');
    const msgEl = overlay.querySelector('.custom-alert-message');
    const btn = overlay.querySelector('.custom-alert-btn');
    const btnCancel = overlay.querySelector('.custom-alert-btn-cancel');
    const actions = overlay.querySelector('.custom-alert-actions');

    if (header) header.className = 'custom-alert-header ' + type;
    if (type === 'success') {
      if (iconEl) iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#2e4836" stroke-width="3"/><path d="M16 24l6 6 10-10" stroke="#2e4836" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>';
      if (btnCancel) btnCancel.style.display = 'none';
      if (actions) actions.style.display = '';
      if (btn) {
        btn.className = 'custom-alert-btn success';
        btn.textContent = 'Continue';
      }
    } else {
      if (iconEl) iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#fff" stroke-width="3"/><path d="M18 18l12 12M30 18l-12 12" stroke="#fff" stroke-width="3" stroke-linecap="round"/></svg>';
      if (btnCancel) btnCancel.style.display = 'none';
      if (actions) actions.style.display = '';
      if (btn) {
        btn.className = 'custom-alert-btn error';
        btn.textContent = 'Close';
      }
    }
    if (titleEl) titleEl.textContent = title || '';
    if (msgEl) msgEl.textContent = message || '';
    if (btn) {
      btn.onclick = function() {
        overlay.classList.remove('active');
        if (typeof onConfirm === 'function') onConfirm();
      };
    }
    overlay.classList.add('active');
  };
}

// Robust API response parser: handles pure JSON, text with BOM, whitespace, or PHP output/notices
async function parseApiResponse(r) {
  let text = '';
  try {
    text = await r.text();
  } catch (e) {
    console.error('Failed to read response text:', e);
    return null;
  }
  if (!text || typeof text !== 'string') return null;

  try {
    return JSON.parse(text);
  } catch (_) {}

  const clean = text.replace(/^\uFEFF/, '').trim();
  try {
    return JSON.parse(clean);
  } catch (_) {}

  // Scan through possible JSON objects using brace-depth matching
  for (let start = 0; start < clean.length; start++) {
    if (clean[start] !== '{') continue;
    let depth = 0, inString = false, escape = false;
    for (let i = start; i < clean.length; i++) {
      const ch = clean[i];
      if (escape) { escape = false; continue; }
      if (ch === '\\' && inString) { escape = true; continue; }
      if (ch === '"') { inString = !inString; continue; }
      if (!inString) {
        if (ch === '{') depth++;
        else if (ch === '}') {
          depth--;
          if (depth === 0) {
            try {
              const res = JSON.parse(clean.substring(start, i + 1));
              if (res && typeof res === 'object') return res;
            } catch (_) {}
            break;
          }
        }
      }
    }
  }

  console.error('Non-JSON API response:', text);
  return null;
}

// Validate backend confirmed success: checks HTTP status and response payload
function isResponseSuccess(r, j) {
  if (!r) return false;
  const isHttpOk = r.ok || (r.status >= 200 && r.status < 300);
  if (!isHttpOk) return false;

  if (j && typeof j === 'object') {
    if (j.success === true || j.success === 'true' || j.status === 'success') {
      return true;
    }
    if (j.success !== false && !j.error && (j.arrival_time || j.fuel_data || j.data || (j.message && !/failed|error|invalid|forbidden/i.test(j.message)))) {
      return true;
    }
    return false;
  }
  return false;
}

// Extract user-friendly error message without raw HTTP status codes or technical details
function extractErrorMessage(r, j, defaultMsg) {
  if (j && j.message && typeof j.message === 'string') {
    const m = j.message.trim();
    if (!/SQL|Database connection|exception|prepare failed|error_log|Stack trace|PDO/i.test(m)) {
      return m;
    }
  }
  if (r) {
    if (r.status === 401) return 'Session expired. Please log in again.';
    if (r.status === 403) return (j && j.message) ? j.message : 'You are not authorized to perform this action.';
    if (r.status === 404) return 'The requested trip record was not found.';
  }
  return defaultMsg || 'An error occurred. Please try again.';
}

function apiHeaders() { return {'Content-Type':'application/json','Authorization':'Bearer '+TOKEN}; }
function apiPostHeaders() { return {'Content-Type':'application/json','Authorization':'Bearer '+TOKEN,'X-CSRF-Token':CSRF_TOKEN}; }
function closeModal(id){document.getElementById(id).classList.remove('show'); if(!isAnyModalOpen()) document.body.classList.remove('modal-open');}
function showModal(id){document.getElementById(id).classList.add('show'); document.body.classList.add('modal-open');}
function esc(s){return String(s).replace(/[&<>"']/g,'')}

function getPHTDate(){
  const d=new Date();
  return new Date(d.toLocaleString('en-US',{timeZone:'Asia/Manila'}));
}
function formatPHT(d){
  return d.toLocaleString('en-PH',{timeZone:'Asia/Manila',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});
}
function getPHTDateStr(){
  const d=getPHTDate();
  return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
}
function liveClock(){
  const d=getPHTDate();
  document.getElementById('liveClock').textContent=formatPHT(d)+' (PHT)';
  document.getElementById('liveDate').textContent=d.toLocaleDateString('en-PH',{timeZone:'Asia/Manila',weekday:'long',year:'numeric',month:'long',day:'numeric'});
}
setInterval(liveClock,1000);
liveClock();

const queueState = {
  entries: [],
  history: [],
  summary: {},
  availableDrivers: [],
  filter: 'all',
  route: '',
  search: '',
  lastSuccess: 0,
  lastPayload: '',
  loading: false,
  started: false
};
let pollTimer = null;
let dispatchInFlight = false;
let skipTargetBus = null;
let pendingDispatch = null;
let overtakeContinue = null;

function setQueueFilter(el, status){
  queueState.filter = status || 'all';
  document.querySelectorAll('#queueFilterRow .filter-chip').forEach(c=>c.classList.remove('active'));
  if(el) el.classList.add('active');
  renderQueue();
}
function onRouteFilter(){
  queueState.route = document.getElementById('routeFilter').value || '';
  renderQueue();
}
function findQueueEntry(busId){
  return queueState.entries.find(e => String(e.bus_id) === String(busId)) || null;
}
function isAnyModalOpen(){
  return document.querySelector('.modal-overlay.show') !== null;
}

const tripDetailCache = {};

function statusLabel(v){
  return v.status==='Scheduled'?'Pending':v.status==='Departed'?'On Route':v.status==='Completed'?'Completed':v.status==='Interrupted'?t('status.interrupted','Interrupted'):v.status;
}

function routeArrow(name){
  return String(name||'').replace(/\s*-\s*/g,' → ');
}

function ordinal(n){
  const num = Number(n);
  if(!num || num < 1) return '—';
  const mod100 = num % 100;
  if(mod100 >= 11 && mod100 <= 13) return num + 'th';
  switch(num % 10){ case 1: return num + 'st'; case 2: return num + 'nd'; case 3: return num + 'rd'; default: return num + 'th'; }
}
function formatClock(t){
  if(!t) return '--:--';
  const s = String(t);
  const m = s.match(/(\d{2}):(\d{2})/);
  return m ? (m[1] + ':' + m[2]) : s;
}
function formatClock12(value, withSeconds){
  if(!value) return '';
  const s = String(value);
  const m = s.match(/(\d{2}):(\d{2})(?::(\d{2}))?/);
  if(!m) return s;
  let hours = parseInt(m[1], 10);
  const suffix = hours >= 12 ? 'PM' : 'AM';
  hours = hours % 12;
  if(hours === 0) hours = 12;
  return hours + ':' + m[2] + ((withSeconds && m[3]) ? (':' + m[3]) : '') + ' ' + suffix;
}
function statusPill(status){
  const map = {
    waiting_for_driver: {cls:'q-waiting_for_driver', icon:'bi-person-dash', label:t('status.waiting_for_driver','WAITING FOR DRIVER')},
    driver_assigned: {cls:'q-driver_assigned', icon:'bi-person-check', label:t('status.driver_assigned','DRIVER ASSIGNED')},
    checked_in: {cls:'q-checked_in', icon:'bi-clipboard-check', label:t('status.checked_in','CHECKED IN')},
    ready: {cls:'q-ready', icon:'bi-check-circle-fill', label:t('status.ready','READY TO DISPATCH')},
    departed: {cls:'q-departed', icon:'bi-send-check-fill', label:t('status.on_route','ON ROUTE')}
  };
  const m = map[status] || map.waiting_for_driver;
  return '<span class="queue-status-pill ' + m.cls + '"><i class="bi ' + m.icon + '"></i> ' + m.label + '</span>';
}
function statusWord(entry){
  const map = {
    waiting_for_driver: t('summary.waiting_for_driver','Waiting for Driver'),
    driver_assigned: t('summary.driver_assigned','Driver Assigned'),
    checked_in: t('summary.checked_in','Checked In'),
    ready: t('summary.ready','Ready to Dispatch'),
    departed: t('summary.on_route','On Route')
  };
  if(!entry) return String(map.waiting_for_driver).toLowerCase();
  return String(map[entry.status] || map.waiting_for_driver).toLowerCase();
}
function nextActionMeta(entry){
  if(!entry) return null;
  const action = entry.next_action;
  if(action === 'assign_driver') return {action:action, icon:'bi-person-plus', label:t('action.assign_driver','Assign Driver')};
  if(action === 'check_in') return {action:action, icon:'bi-person-check', label:t('action.check_in','Check In')};
  if(action === 'prepare') return {action:action, icon:'bi-clipboard-check', label:t('action.prepare_trip','Prepare Trip')};
  if(action === 'dispatch') return {action:action, icon:'bi-send', label:t('action.dispatch','Dispatch')};
  return null;
}
function runNextAction(busId){
  const e = findQueueEntry(busId);
  if(!e) return;
  switch(e.next_action){
    case 'assign_driver': openAssignDriverModal(busId); break;
    case 'check_in': checkInDriver(busId); break;
    case 'prepare': openPrepareTrip(busId); break;
    case 'dispatch': openPrepareTrip(busId); break;
    default: openQueueDetail(busId);
  }
}
function isDispatchable(entry){
  return !!entry && entry.status === 'ready';
}
function canAdvance(entry){
  return !!entry && entry.status !== 'departed' && !(entry.skip && entry.skip.active);
}
function byQueuePosition(a, b){
  return (Number(a.queue_position) || 0) - (Number(b.queue_position) || 0);
}
function sortQueueEntries(list){
  const rank = {ready:0, checked_in:1, driver_assigned:2, waiting_for_driver:3, departed:4};
  return list.slice().sort((a, b) => {
    const ra = rank[a.status] != null ? rank[a.status] : 5;
    const rb = rank[b.status] != null ? rank[b.status] : 5;
    if(ra !== rb) return ra - rb;
    return byQueuePosition(a, b);
  });
}
function nextEligibleEntry(){
  const list = queueState.entries.filter(canAdvance).sort(byQueuePosition);
  return list[0] || null;
}
function earliestReadyEntry(){
  const list = queueState.entries.filter(isDispatchable).sort(byQueuePosition);
  return list[0] || null;
}
function earliestActiveEntry(){
  const active = queueState.entries.filter(e => e.status !== 'departed').sort((a, b) => (Number(a.queue_position) || 0) - (Number(b.queue_position) || 0));
  return active[0] || null;
}
function filteredQueueEntries(){
  let list = queueState.entries.slice();
  if(queueState.filter === 'all') list = list.filter(e => e.status !== 'departed');
  else list = list.filter(e => e.status === queueState.filter);
  if(queueState.route) list = list.filter(e => String(e.route_name || '') === queueState.route);
  if(queueState.search){
    const q = queueState.search.toLowerCase();
    list = list.filter(e => {
      const tripNo = e.trip && e.trip.trip_number != null ? String(e.trip.trip_number) : '';
      return String(e.bus_id || '').toLowerCase().indexOf(q) !== -1
        || String(e.driver_name || '').toLowerCase().indexOf(q) !== -1
        || String(e.plate_number || '').toLowerCase().indexOf(q) !== -1
        || tripNo.toLowerCase().indexOf(q) !== -1;
    });
  }
  return sortQueueEntries(list);
}
function queueCardHTML(e){
  const skipLine = e.skip && e.skip.active
    ? '<div class="qc-skip"><i class="bi bi-slash-circle"></i> ' + esc(t('card.skipped','Temporarily skipped: :reason', {reason: e.skip.reason_label || t('card.skipped_reason','reason recorded')})) + '</div>'
    : '';
  const actions = ['<button type="button" class="btn-action btn-view" onclick="openQueueDetail(\'' + esc(e.bus_id) + '\')" aria-label="' + esc(t('common.view_details','View Details')) + ' ' + esc(e.bus_id) + '"><span class="btn-ico"><i class="bi bi-eye"></i></span> ' + esc(t('common.view_details','View Details')) + '</button>'];
  if(e.status === 'departed' && e.trip && e.trip.id){
    actions.push('<button type="button" class="btn-action btn-view" onclick="openQueuedTrip(' + e.trip.id + ',\'' + esc(e.bus_id) + '\')" aria-label="' + esc(t('action.view_trip','View Trip')) + '"><i class="bi bi-info-circle"></i> ' + esc(t('action.view_trip','View Trip')) + '</button>');
  } else if(e.skip && e.skip.active){
    actions.push('<button type="button" class="btn-action btn-view" onclick="clearSkip(\'' + esc(e.bus_id) + '\')" aria-label="' + esc(t('action.clear_skip','Clear Skip')) + '"><i class="bi bi-arrow-counterclockwise"></i> ' + esc(t('action.clear_skip','Clear Skip')) + '</button>');
  } else {
    const meta = nextActionMeta(e);
    if(meta) actions.push('<button type="button" class="btn-action btn-dispatch-queue" onclick="runNextAction(\'' + esc(e.bus_id) + '\')" aria-label="' + esc(meta.label) + ' ' + esc(e.bus_id) + '"><i class="bi ' + meta.icon + '"></i> ' + esc(meta.label) + '</button>');
  }
  const eligible = nextEligibleEntry();
  const isNext = canAdvance(e) && eligible && String(eligible.bus_id) === String(e.bus_id);
  const nextBadge = isNext ? '<span class="qc-next">' + esc(t('card.next_eligible','Next step')) + '</span>' : '';
  const cycleChip = (Number(e.cycle) > 1) ? '<span class="qc-cycle">' + esc(t('card.trip_n','Trip :cycle', {cycle: Number(e.cycle)})) + '</span>' : '';
  return '<article class="queue-card q-' + e.status + '">'
    + '<div class="qc-top"><span style="display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap;"><span class="qc-pos">' + esc(t('card.queue','QUEUE #:position', {position: e.queue_position})) + '</span>' + cycleChip + '</span><span style="display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap;">' + nextBadge + statusPill(e.status) + '</span></div>'
    + '<div class="qc-bus"><i class="bi bi-bus-front"></i> ' + esc(e.bus_id) + '</div>'
    + '<div class="qc-driver"><i class="bi bi-person"></i> ' + esc(e.driver_name || t('card.no_driver','No driver assigned')) + '</div>'
    + '<div class="qc-route"><i class="bi bi-geo-alt"></i> ' + esc(routeArrow(e.route_name || t('card.no_route','No route assigned'))) + '</div>'
    + '<div class="qc-reason">' + esc(e.short_reason || '') + '</div>'
    + skipLine
    + '<div class="qc-actions">' + actions.join('') + '</div>'
    + '</article>';
}
function renderNextDispatch(){
  const panel = document.getElementById('nextDispatchPanel');
  if(!queueState.entries.length){
    panel.style.display = 'none';
    panel.innerHTML = '';
    return;
  }
  panel.style.display = '';
  const eligible = nextEligibleEntry();
  if(!eligible){
    panel.innerHTML = '<div class="nd-kicker"><i class="bi bi-send"></i> ' + esc(t('next.kicker','NEXT ACTION')) + '</div>'
      + '<div class="empty-state" style="padding:12px 4px;"><i class="bi bi-hourglass-split"></i><p><strong>' + esc(t('next.no_action_title','NO ACTION AVAILABLE')) + '</strong></p><p>' + esc(t('next.no_action_body','The next action will appear when a bus is available. Clear a skip or check the On Route list.')) + '</p></div>';
    return;
  }
  const meta = nextActionMeta(eligible);
  const presence = eligible.presence && eligible.presence.at_terminal ? t('next.at_terminal','At Terminal') : (eligible.presence && eligible.presence.available ? t('next.away','Away from terminal') : t('next.presence_unavailable','Presence unavailable'));
  panel.innerHTML = '<div class="nd-kicker"><i class="bi bi-send"></i> ' + esc(t('next.kicker','NEXT ACTION')) + '</div>'
    + '<div class="nd-bus"><i class="bi bi-bus-front"></i> ' + esc(eligible.bus_id) + ' ' + statusPill(eligible.status) + '</div>'
    + '<div class="nd-route"><i class="bi bi-signpost-split"></i> ' + esc(routeArrow(eligible.route_name || t('card.no_route','No route assigned'))) + '</div>'
    + '<div class="nd-driver"><i class="bi bi-person-badge"></i> ' + esc(eligible.driver_name || t('card.no_driver','No driver assigned')) + '</div>'
    + '<div class="nd-rows">'
    +   '<div class="nd-row"><span class="nd-label">' + esc(t('details.queue_position','Queue Position')) + '</span><span class="nd-value">#' + eligible.queue_position + '</span></div>'
    +   '<div class="nd-row"><span class="nd-label">' + esc(t('next.action','Next Action')) + '</span><span class="nd-value">' + esc(meta ? meta.label : '—') + '</span></div>'
    +   '<div class="nd-row"><span class="nd-label">' + esc(t('next.bus_presence','Bus Presence')) + '</span><span class="nd-value">' + esc(presence) + '</span></div>'
    +   '<div class="nd-row"><span class="nd-label">' + esc(t('next.schedule','Schedule')) + '</span><span class="nd-value">' + esc(eligible.schedule || '—') + '</span></div>'
    + '</div>'
    + '<div class="nd-actions">'
    +   '<button type="button" class="btn-action btn-view" onclick="openQueueDetail(\'' + esc(eligible.bus_id) + '\')" aria-label="' + esc(t('common.view_details','View Details')) + '"><span class="btn-ico"><i class="bi bi-eye"></i></span> ' + esc(t('common.view_details','View Details')) + '</button>'
    +   (meta ? '<button type="button" class="btn-action btn-dispatch-queue" onclick="runNextAction(\'' + esc(eligible.bus_id) + '\')" aria-label="' + esc(meta.label) + '"><i class="bi ' + meta.icon + '"></i> ' + esc(meta.label) + '</button>' : '')
    + '</div>';
}
function renderWhyNotPanel(){
  const panel = document.getElementById('whyNotPanel');
  if(!panel) return;
  const eligible = nextEligibleEntry();
  const earliest = earliestActiveEntry();
  if(!eligible || !earliest || String(earliest.bus_id) === String(eligible.bus_id)){
    panel.style.display = 'none';
    panel.innerHTML = '';
    return;
  }
  panel.innerHTML = '<div class="wn-title"><i class="bi bi-info-circle"></i> ' + esc(t('next.why_not','WHY NOT QUEUE #:position?', {position: earliest.queue_position})) + '</div>'
    + '<div class="wn-bus">' + esc(earliest.bus_id) + '</div>'
    + '<div class="wn-reason">' + esc(earliest.short_reason || '') + '</div>'
    + '<div class="wn-next">' + esc(t('next.why_not_next_action',':eligible has the next available action.', {eligible: eligible.bus_id})) + '</div>';
  panel.style.display = 'block';
}
function renderBottomBar(){
  const bar = document.getElementById('bottomBar');
  if(!bar) return;
  const eligible = nextEligibleEntry();
  if(!eligible){
    bar.style.display = 'none';
    document.body.classList.remove('has-bottom-bar');
    return;
  }
  bar.style.display = 'flex';
  document.body.classList.add('has-bottom-bar');
  const meta = nextActionMeta(eligible);
  document.getElementById('babLabel').textContent = t('bottom.next_action','Next: :bus (#:position) — :action', {bus: eligible.bus_id, position: eligible.queue_position, action: meta ? meta.label : ''});
  const babBtn = document.getElementById('babDispatchBtn');
  babBtn.innerHTML = '<i class="bi ' + (meta ? meta.icon : 'bi-list') + '"></i> ' + esc((meta ? meta.label : t('common.view_details','View Details')).toUpperCase());
  babBtn.onclick = function(){ runNextAction(eligible.bus_id); };
  updateBottomBarOffset();
}
function updateBottomBarOffset(){
  const bar = document.getElementById('bottomBar');
  if(!bar || bar.style.display === 'none') return;
  const banner = document.getElementById('installBanner');
  const bannerVisible = !!banner && banner.style.display !== 'none' && window.getComputedStyle(banner).display !== 'none';
  bar.style.bottom = bannerVisible ? '76px' : '0';
}
function dispatchNextFromBar(){
  const eligible = nextEligibleEntry();
  if(eligible) runNextAction(eligible.bus_id);
}
function updateRouteFilter(){
  const sel = document.getElementById('routeFilter');
  if(!sel) return;
  const routes = [];
  queueState.entries.forEach(e => { if(e.route_name && routes.indexOf(e.route_name) === -1) routes.push(e.route_name); });
  routes.sort();
  const current = queueState.route;
  sel.innerHTML = '<option value="">All Routes</option>' + routes.map(r => '<option value="' + esc(r) + '">' + esc(r) + '</option>').join('');
  if(current && routes.indexOf(current) !== -1) sel.value = current;
  else { sel.value = ''; queueState.route = ''; }
}
function renderDispatchHistory(){
  const el = document.getElementById('dispatchHistoryList');
  if(!queueState.history.length){
    el.innerHTML = '<div class="history-empty">' + esc(t('history.empty','No dispatches recorded yet today.')) + '</div>';
    return;
  }
  el.innerHTML = queueState.history.map(h => {
    const orderLabel = ordinal(h.order);
    const queueLabel = h.queue_position != null ? ('#' + h.queue_position) : esc(t('history.not_in_queue','Not in planned queue'));
    const note = h.overtake_reason ? '<span class="hi-note">' + esc(t('history.out_of_order','Out of order')) + '</span>' : '';
    return '<button type="button" class="history-item" onclick="openQueuedTrip(' + h.trip_id + ',\'' + esc(h.bus_id) + '\')" aria-label="Open dispatched trip for ' + esc(h.bus_id) + '">'
      + '<span class="hi-order">' + orderLabel + '</span>'
      + '<span class="hi-main"><strong>' + esc(h.bus_id) + '</strong><small>' + esc(h.driver_name || '') + ' &bull; ' + queueLabel + '</small></span>'
      + '<span class="hi-time">' + formatClock(h.departure_time) + '</span>'
      + note
      + '</button>';
  }).join('');
}
function updateFilterChips(){
  const s = queueState.summary || {};
  const counts = {
    all: s.active || 0,
    waiting_for_driver: s.waiting_for_driver || 0,
    driver_assigned: s.driver_assigned || 0,
    checked_in: s.checked_in || 0,
    ready: s.ready || 0,
    departed: s.dispatched || 0
  };
  document.querySelectorAll('#queueFilterRow .filter-chip').forEach(chip => {
    const key = chip.dataset.status || 'all';
    const label = chip.dataset.label || key;
    chip.innerHTML = esc(label) + ' <span class="chip-count">' + (counts[key] || 0) + '</span>';
  });
}
function renderQueue(){
  const scrollY = window.scrollY;
  updateRouteFilter();
  renderNextDispatch();
  renderWhyNotPanel();
  updateFilterChips();
  renderDispatchHistory();
  renderBottomBar();
  const el = document.getElementById('queueList');
  const list = filteredQueueEntries();
  let html;
  if(!queueState.entries.length){
    html = '<div class="empty-state" style="grid-column:1/-1;"><i class="bi bi-bus-front"></i><p><strong>' + esc(t('state.no_buses_title','NO BUSES ASSIGNED')) + '</strong></p><p>' + esc(t('state.no_buses_body','No active buses are assigned to this terminal. Assign buses to the terminal to start dispatching.')) + '</p></div>';
  } else if(!list.length){
    html = '<div class="empty-state" style="grid-column:1/-1;"><i class="bi bi-funnel"></i><p>' + esc(t('state.no_match','No buses match this filter.')) + '</p></div>';
  } else {
    html = list.map(queueCardHTML).join('');
  }
  el.innerHTML = html;
  window.scrollTo(0, scrollY);
}

function durationLabel(v){
  if(v.duration_minutes) return v.duration_minutes+' min';
  if(v.departure_time&&v.arrival_time){
    const t1=new Date('1970-01-01T'+v.departure_time+':00'),t2=new Date('1970-01-01T'+v.arrival_time+':00');
    const df=(t2-t1)/60000;
    return df>0?Math.round(df)+' min':'';
  }
  return '';
}

function nextActionFor(v){
  if(v.status==='Scheduled') return {icon:'bi-send', text:'Next: waiting for dispatch'};
  if(v.status==='Departed' && Number(v.to_terminal_id)===TERMINAL_ID) return {icon:'bi-flag', text:'Next: record arrival'};
  if(v.status==='Completed') return {icon:'bi-check-circle', text:'Trip arrived'};
  if(v.status==='Interrupted') return {icon:'bi-exclamation-triangle', text:'Next: trip problem report pending'};
  return null;
}

function cardHTML(v, historyMode){
  const st=statusLabel(v);
  const dur=durationLabel(v);
  const route=routeArrow(v.route_name);
  const next=nextActionFor(v);
  tripDetailCache[v.id]=v;
  let html=`<div class="dispatch-card">
    <div class="dispatch-header">
      <span class="dispatch-id"><i class="bi bi-hash"></i> Trip ${v.trip_number||v.id}</span>
      <span class="status-badge status-${v.status}">${st}</span>
    </div>
    <div class="dispatch-busrow">
      <span class="bus-line"><i class="bi bi-bus-front"></i> ${esc(v.bus_id)}</span>
      <span class="driver-line"><i class="bi bi-person"></i> ${esc(v.driver_name||v.driver_id||'')}</span>
    </div>
    <div class="dispatch-route"><i class="bi bi-geo-alt"></i> ${esc(route)}</div>
    <div class="dispatch-meta">
      <span><i class="bi bi-clock"></i> ${v.departure_time||'-'}</span>
      ${v.arrival_time?`<span><i class="bi bi-flag"></i> ${v.arrival_time}</span>`:''}
      <span><i class="bi bi-people"></i> ${v.total_passengers||'0'} ${v.total_passengers==1?'passenger':'passengers'}</span>
      ${dur?`<span><i class="bi bi-hourglass"></i> ${dur}</span>`:''}
      ${v.starting_odo !== null && v.starting_odo !== undefined && v.starting_odo !== '' ? `<span><i class="bi bi-speedometer2"></i> ${v.starting_odo}${v.ending_odo ? '→'+v.ending_odo : ''} KM</span>` : ''}
      ${v.fuel_liters !== null && v.fuel_liters !== undefined && parseFloat(v.fuel_liters) > 0 ? `<span><i class="bi bi-fuel-pump"></i> ${parseFloat(v.fuel_liters).toFixed(1)}L</span>` : ''}
    </div>
    <div class="dispatch-actions">
      <button class="btn-action btn-view" onclick="openTripDetail(${v.id})"><span class="btn-ico"><i class="bi bi-eye"></i></span> View Trip</button>
      <button class="btn-action btn-view" onclick="openEditFuelModal(${v.id})" title="Fuel & ODO Record"><span class="btn-ico"><i class="bi bi-fuel-pump"></i></span> Fuel/ODO</button>
      ${v.status==='Departed' && Number(v.to_terminal_id)===TERMINAL_ID?`<button class="btn-action btn-arrive" onclick="openArrive(${v.id})"><i class="bi bi-check-circle"></i> Arrive</button>`:''}
      ${v.status==='Scheduled'?`<button class="btn-action btn-cancel" onclick="quickAction(${v.id},'cancel')">Cancel</button>`:''}
      ${next?`<span class="dispatch-next"><i class="bi ${next.icon}"></i> ${next.text}</span>`:''}
    </div>
  </div>`;
  return html;
}

function renderQueueLoading(){
  const el = document.getElementById('queueList');
  el.innerHTML = Array.from({length:3}).map(() => '<div class="queue-card skeleton-card"><div class="sk-line sk-lg"></div><div class="sk-line"></div><div class="sk-line sk-sm"></div></div>').join('');
  const panel = document.getElementById('nextDispatchPanel');
  panel.innerHTML = '<div class="nd-kicker"><i class="bi bi-send"></i> ' + esc(t('next.kicker','NEXT BUS TO DISPATCH')) + '</div><div class="nd-loading"><span class="spinner-border spinner-border-sm me-2" role="status"></span>' + esc(t('common.loading_queue','Loading current queue...')) + '</div>';
}
function renderQueueError(msg, sessionExpired){
  const panel = document.getElementById('nextDispatchPanel');
  panel.style.display = 'none';
  panel.innerHTML = '';
  document.getElementById('dispatchHistoryList').innerHTML = '';
  const bar = document.getElementById('bottomBar');
  if(bar){ bar.style.display = 'none'; document.body.classList.remove('has-bottom-bar'); }
  const el = document.getElementById('queueList');
  el.innerHTML = '<div class="empty-state queue-error" style="grid-column:1/-1;"><i class="bi bi-exclamation-triangle" style="color:#d32f2f;"></i>'
    + '<p><strong>' + esc(t('state.error_title','WE COULDN\'T LOAD THE QUEUE')) + '</strong></p>'
    + '<p>' + esc(msg || t('state.error_body','Your current queue information could not be retrieved.')) + '</p>'
    + (sessionExpired
        ? '<button type="button" class="btn-primary-app" style="max-width:180px;margin:10px auto;padding:8px 12px;font-size:13px;" onclick="logout()">' + esc(t('state.log_in_again','Log In Again')) + '</button>'
        : '<button type="button" class="btn-primary-app" style="max-width:180px;margin:10px auto;padding:8px 12px;font-size:13px;" onclick="loadQueue({force:true})">' + esc(t('common.try_again','Try Again')) + '</button>')
    + '</div>';
}
function recoverStaleApp(){
  try {
    const last = parseInt(sessionStorage.getItem('dispatcher_stale_reload') || '0', 10);
    if (Date.now() - last < 30000) {
      if(!queueState.started) renderQueueError(t('state.reload_required','The app has been updated. Please close and reopen the dispatcher app.'));
      return;
    }
    sessionStorage.setItem('dispatcher_stale_reload', String(Date.now()));
  } catch(e) {}
  window.location.replace('/dispatcher/?lang=' + encodeURIComponent(LANG));
}
let queueRequestSeq = 0;
async function loadQueue(opts){
  opts = opts || {};
  if(queueState.loading && !opts.force) return;
  const reqId = ++queueRequestSeq;
  queueState.loading = true;
  if(!queueState.started) renderQueueLoading();
  try{
    const r = await fetch(API + 'get_trip_queue', {headers:apiHeaders()});
    const j = await parseApiResponse(r);
    if(reqId !== queueRequestSeq) return;
    if(!j && (r.status === 404 || r.status === 410)){
      console.warn('Queue API route is stale; reloading the dispatcher app.', r.status);
      recoverStaleApp();
      return;
    }
    if(j && j.code === 'stale_api_route'){
      console.warn('Queue API route is stale; reloading the dispatcher app.');
      recoverStaleApp();
      return;
    }
    if(r.status === 401){
      renderQueueError(t('state.session_expired','Your session has expired. Please log in again.'), true);
      return;
    }
    if(!j || !j.success){
      if(!queueState.started){
        renderQueueError(extractErrorMessage(r, j, t('state.error_body','Your current queue information could not be retrieved.')));
      } else {
        console.warn('Queue refresh failed:', (j && j.message) || r.status);
      }
      return;
    }
    const d = j.data || {};
    const payload = JSON.stringify(d);
    const changed = payload !== queueState.lastPayload;
    queueState.lastPayload = payload;
    queueState.entries = Array.isArray(d.entries) ? d.entries : [];
    queueState.history = Array.isArray(d.history) ? d.history : [];
    queueState.summary = d.summary || {};
    queueState.availableDrivers = Array.isArray(d.available_drivers) ? d.available_drivers : [];
    queueState.lastSuccess = Date.now();
    queueState.started = true;
    if(changed || opts.force) renderQueue();
    updateQueueUpdatedLabel();
    loadTodayTripCache();
  }catch(e){
    console.error('loadQueue error:', e);
    if(reqId === queueRequestSeq && !queueState.started) renderQueueError(t('state.network_error','Network error. Please check your connection and try again.'));
  }finally{
    if(reqId === queueRequestSeq) queueState.loading = false;
  }
}
async function loadTodayTripCache(){
  try{
    const r = await fetch(API + 'get_dispatches?date=' + getPHTDateStr() + '&terminal_id=' + TERMINAL_ID, {headers:apiHeaders()});
    const j = await parseApiResponse(r);
    if(j && j.success && Array.isArray(j.data)){
      j.data.forEach(v => { tripDetailCache[v.id] = v; });
    }
  }catch(e){
    console.error('loadTodayTripCache error:', e);
  }
}
async function ensureTripCached(tripId, busId){
  if(tripDetailCache[tripId]) return tripDetailCache[tripId];
  try{
    const r = await fetch(API + 'get_dispatches?date=' + getPHTDateStr() + '&search=' + encodeURIComponent(busId || '') + '&terminal_id=' + TERMINAL_ID, {headers:apiHeaders()});
    const j = await parseApiResponse(r);
    if(j && j.success && Array.isArray(j.data)){
      j.data.forEach(v => { tripDetailCache[v.id] = v; });
    }
  }catch(e){
    console.error('ensureTripCached error:', e);
  }
  return tripDetailCache[tripId] || null;
}
function updateQueueUpdatedLabel(){
  const label = document.getElementById('queueUpdatedLabel');
  const stale = document.getElementById('queueStaleLabel');
  if(!label) return;
  if(!queueState.lastSuccess){
    label.textContent = '';
    if(stale) stale.style.display = 'none';
    return;
  }
  const secs = Math.max(0, Math.floor((Date.now() - queueState.lastSuccess) / 1000));
  label.textContent = secs < 5 ? t('common.updated_just_now','Queue updated just now') : t('common.updated_ago','Queue updated :seconds s ago', {seconds: secs});
  if(stale) stale.style.display = secs > 60 ? 'inline-flex' : 'none';
}
function startQueuePolling(){
  if(pollTimer) clearInterval(pollTimer);
  pollTimer = setInterval(function(){
    if(document.hidden) return;
    if(isAnyModalOpen()) return;
    const active = document.activeElement;
    if(active && (active.tagName === 'INPUT' || active.tagName === 'SELECT' || active.tagName === 'TEXTAREA')) return;
    loadQueue();
  }, 20000);
}
function initQueue(){
  loadQueue({force:true});
  startQueuePolling();
  setInterval(updateQueueUpdatedLabel, 1000);
}
document.addEventListener('visibilitychange', function(){
  updateQueueUpdatedLabel();
  if(!document.hidden && queueState.started) loadQueue();
});
window.addEventListener('pageshow', function(e){
  if(e.persisted && queueState.started) loadQueue({force:true});
});
async function openQueuedTrip(tripId, busId){
  const v = await ensureTripCached(tripId, busId);
  if(!v){ showAlert('error', t('alert.trip_unavailable','Trip Unavailable'), t('state.trip_unavailable','Trip details are not available right now. Please refresh and try again.')); return; }
  openTripDetail(tripId);
}
async function openQueuedFuel(tripId, busId){
  const v = await ensureTripCached(tripId, busId);
  if(!v){ showAlert('error', t('alert.trip_unavailable','Trip Unavailable'), t('state.trip_unavailable','Trip details are not available right now. Please refresh and try again.')); return; }
  openEditFuelModal(tripId);
}
async function openQueuedArrive(tripId, busId){
  const v = await ensureTripCached(tripId, busId);
  if(!v){ showAlert('error', t('alert.trip_unavailable','Trip Unavailable'), t('state.trip_unavailable','Trip details are not available right now. Please refresh and try again.')); return; }
  openArrive(tripId);
}
function openQueueDetail(busId){
  const e = findQueueEntry(busId);
  if(!e) return;
  document.getElementById('tripDetailTitle').innerHTML = '<i class="bi bi-info-circle"></i> ' + esc(e.bus_id) + ' — #' + e.queue_position;
  let html = '';
  html += '<div class="detail-summary"><div class="ds-top"><span class="ds-bus"><i class="bi bi-bus-front"></i> ' + esc(e.bus_id) + '</span>' + statusPill(e.status) + '</div>'
    + '<div class="ds-route"><i class="bi bi-signpost-split"></i> ' + esc(routeArrow(e.route_name || t('card.no_route','No route assigned'))) + '</div></div>';
  html += '<div class="detail-sub"><i class="bi bi-person-badge"></i> ' + esc(e.driver_name || t('card.no_driver','No driver assigned')) + (e.schedule ? ' &bull; <i class="bi bi-clock"></i> ' + esc(e.schedule) : '') + '</div>';
  html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-list-ol"></i> ' + esc(t('details.queue_position','Queue Position')) + '</span><span class="qd-value">#' + e.queue_position + '</span></div>';
  html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-geo"></i> ' + esc(t('details.bus_presence','Bus Presence')) + '</span><span class="qd-value">' + esc(e.presence && e.presence.detail ? e.presence.detail : t('details.unavailable','Unavailable')) + '</span></div>';
  const checkInOk = !!(e.presence && e.presence.checked_in);
  const checkInAt = e.presence && (e.presence.checked_in_at || e.presence.updated_at);
  let checkInText;
  if(checkInOk && e.presence && e.presence.checked_in_by === 'dispatcher'){
    checkInText = t('details.check_in_by_dispatcher','Checked in by Dispatcher :name at :time', {name: e.presence.checked_in_by_name || t('details.dispatcher','Dispatcher'), time: formatClock12(checkInAt)});
  } else if(checkInOk){
    checkInText = t('details.check_in_ok','Checked in at :time', {time: formatClock12(checkInAt)});
  } else {
    checkInText = t('details.check_in_pending','Pending — driver must check in');
  }
  html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-person-check"></i> ' + esc(t('details.driver_check_in','Driver Check-In')) + '</span><span class="qd-value">' + esc(checkInText) + '</span></div>';
  html += '<div class="checkin-box">';
  if(checkInOk){
    html += '<div class="checkin-ok"><i class="bi bi-check-circle-fill"></i> <strong>' + esc(t('details.check_in_success','Driver Checked In')) + '</strong>'
      + '<div>' + esc(e.driver_name || '') + '</div>'
      + (e.presence && e.presence.checked_in_by === 'dispatcher' ? '<div>' + esc(t('details.checked_in_by','Checked in by: Dispatcher :name', {name: e.presence.checked_in_by_name || t('details.dispatcher','Dispatcher')})) + '</div>' : '')
      + '<div>' + esc(t('details.checked_in_time','Time: :time', {time: formatClock12(checkInAt)})) + '</div></div>';
  } else {
    html += '<div class="checkin-pending"><i class="bi bi-exclamation-triangle-fill"></i> ' + esc(t('details.check_in_pending','Pending — driver must check in')) + '</div>';
    if(e.driver_id){
      html += '<button type="button" class="btn-checkin" id="checkInBtn" onclick="checkInDriver(\'' + esc(e.bus_id) + '\')"><i class="bi bi-person-check"></i> ' + esc(t('action.check_in_driver','CHECK IN DRIVER')) + '</button>';
    }
  }
  html += '</div>';
  html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-signpost"></i> ' + esc(t('details.destination','Destination')) + '</span><span class="qd-value">' + esc(e.destination && e.destination.terminal_name ? e.destination.terminal_name : t('details.not_set','Not set')) + '</span></div>';
  html += '<div class="detail-sub" style="margin-top:10px;"><i class="bi bi-clipboard-check"></i> <strong>' + esc(t('details.dispatch_readiness','DISPATCH READINESS')) + '</strong></div>';
  html += '<div class="checklist">' + (e.readiness || []).map(item => {
    const cls = item.ok ? 'ok' : (item.blocking ? 'bad' : 'warn');
    const icon = item.ok ? 'bi-check-circle-fill' : (item.blocking ? 'bi-x-octagon-fill' : 'bi-exclamation-triangle-fill');
    return '<div class="check-item ' + cls + '"><span class="ci-icon"><i class="bi ' + icon + '"></i></span><span class="ci-text"><strong>' + esc(item.label) + '</strong>' + (item.detail ? '<small>' + esc(item.detail) + '</small>' : '') + '</span></div>';
  }).join('') + '</div>';
  if(e.reasons && e.reasons.length){
    html += '<div class="issue-groups">' + e.reasons.map(g => {
      return '<div class="issue-group"><div class="ig-area">' + esc(g.area) + '</div><ul>' + g.items.map(i => '<li>' + esc(i) + '</li>').join('') + '</ul></div>';
    }).join('') + '</div>';
  }
  if(e.status === 'departed' && e.trip){
    html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-send"></i> ' + esc(t('details.dispatch_order','Dispatch Order')) + '</span><span class="qd-value">' + (e.trip.dispatch_order ? ordinal(e.trip.dispatch_order) : '—') + '</span></div>';
    html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-clock"></i> ' + esc(t('details.dispatch_time','Dispatch Time')) + '</span><span class="qd-value">' + esc(formatClock12(e.trip.departure_time)) + '</span></div>';
    html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-person-check"></i> ' + esc(t('details.dispatched_by','Dispatched By')) + '</span><span class="qd-value">' + esc(e.trip.dispatcher_name || '—') + '</span></div>';
    if(e.trip.overtake_reason) html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-exclamation-triangle"></i> ' + esc(t('details.out_of_order_reason','Out-of-order Reason')) + '</span><span class="qd-value">' + esc(e.trip.overtake_reason) + '</span></div>';
    if(e.trip.total_passengers != null) html += '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-people"></i> ' + esc(t('details.passengers','Passengers')) + '</span><span class="qd-value">' + e.trip.total_passengers + '</span></div>';
  }
  document.getElementById('tripDetailBody').innerHTML = html;
  const actions = [];
  if(e.status === 'waiting_for_driver') actions.push('<button type="button" class="btn-action btn-dispatch-queue" onclick="closeModal(\'tripDetailModal\');openAssignDriverModal(\'' + esc(e.bus_id) + '\')"><i class="bi bi-person-plus"></i> ' + esc(t('action.assign_driver','Assign Driver')) + '</button>');
  else if(e.status === 'driver_assigned') actions.push('<button type="button" class="btn-action btn-dispatch-queue" onclick="closeModal(\'tripDetailModal\');checkInDriver(\'' + esc(e.bus_id) + '\')"><i class="bi bi-person-check"></i> ' + esc(t('action.check_in','Check In')) + '</button>');
  else if(e.status === 'checked_in') actions.push('<button type="button" class="btn-action btn-dispatch-queue" onclick="closeModal(\'tripDetailModal\');openPrepareTrip(\'' + esc(e.bus_id) + '\')"><i class="bi bi-clipboard-check"></i> ' + esc(t('action.prepare_trip','Prepare Trip')) + '</button>');
  else if(isDispatchable(e)) actions.push('<button type="button" class="btn-action btn-dispatch-queue" onclick="closeModal(\'tripDetailModal\');openPrepareTrip(\'' + esc(e.bus_id) + '\')"><i class="bi bi-send"></i> ' + esc(t('action.dispatch','Dispatch')) + '</button>');
  if(e.driver_id) actions.push('<button type="button" class="btn-action btn-view" onclick="closeModal(\'tripDetailModal\');openAssignDriverModal(\'' + esc(e.bus_id) + '\', true)"><i class="bi bi-arrow-left-right"></i> ' + esc(t('action.change_assignment','Change Bus Assignment')) + '</button>');
  if(e.status !== 'departed') actions.push('<button type="button" class="btn-action btn-skip" onclick="closeModal(\'tripDetailModal\');openSkipModal(\'' + esc(e.bus_id) + '\')"><i class="bi bi-slash-circle"></i> ' + esc(t('action.skip','Skip')) + '</button>');
  if(e.skip && e.skip.active) actions.push('<button type="button" class="btn-action btn-view" onclick="closeModal(\'tripDetailModal\');clearSkip(\'' + esc(e.bus_id) + '\')"><i class="bi bi-arrow-counterclockwise"></i> ' + esc(t('action.clear_skip','Clear Skip')) + '</button>');
  if(e.status === 'departed' && e.trip && e.trip.id){
    actions.push('<button type="button" class="btn-action btn-view" onclick="closeModal(\'tripDetailModal\');openQueuedTrip(' + e.trip.id + ',\'' + esc(e.bus_id) + '\')"><i class="bi bi-eye"></i> ' + esc(t('action.view_trip','View Trip')) + '</button>');
    actions.push('<button type="button" class="btn-action btn-view" onclick="closeModal(\'tripDetailModal\');openQueuedFuel(' + e.trip.id + ',\'' + esc(e.bus_id) + '\')"><i class="bi bi-fuel-pump"></i> ' + esc(t('action.fuel','Fuel/ODO')) + '</button>');
    if(Number(e.destination && e.destination.terminal_id) === Number(TERMINAL_ID)) actions.push('<button type="button" class="btn-action btn-arrive" onclick="closeModal(\'tripDetailModal\');openQueuedArrive(' + e.trip.id + ',\'' + esc(e.bus_id) + '\')"><i class="bi bi-flag"></i> ' + esc(t('action.arrive','Arrive')) + '</button>');
  }
  document.getElementById('tripDetailActions').innerHTML = actions.join('');
  showModal('tripDetailModal');
}
async function checkInDriver(busId){
  const btn = document.getElementById('checkInBtn');
  if(btn){ btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + esc(t('action.check_in_driver','CHECK IN DRIVER')); }
  try{
    const r = await fetch(API + 'check_in_driver', {
      method:'POST',
      headers:apiPostHeaders(),
      body:JSON.stringify({bus_id: busId, csrf_token: CSRF_TOKEN})
    });
    const j = await parseApiResponse(r);
    if(isResponseSuccess(r, j)){
      closeModal('tripDetailModal');
      await loadQueue({force:true});
      showAlert('success', t('checkin.success_title','Driver Checked In'), t('checkin.success_body','You checked in driver :driver.', {driver: (j && j.data && j.data.driver_name) || busId}));
    } else {
      showAlert('error', t('alert.action_failed','Action Failed'), extractErrorMessage(r, j, t('checkin.error','The driver check-in could not be saved. Please try again.')));
    }
  }catch(error){
    console.error('checkInDriver error:', error);
    showAlert('error', t('alert.action_failed','Action Failed'), t('state.network_error','Network error. Please check your connection and try again.'));
  }finally{
    if(btn){ btn.disabled = false; }
  }
}
function startQueueDispatch(busId, opts){
  const e = findQueueEntry(busId);
  if(!e) return;
  if(!isDispatchable(e)) return;
  opts = opts || {};
  const readyEarlier = earliestReadyEntry();
  if(!opts.overtakeReason && readyEarlier && String(readyEarlier.bus_id) !== String(e.bus_id) && Number(e.queue_position) > Number(readyEarlier.queue_position)){
    openOvertakeModal(e, readyEarlier, function(reason){
      openConfirmDispatchModal(e.bus_id, Object.assign({}, opts, {overtakeReason: reason}));
    });
    return;
  }
  openConfirmDispatchModal(e.bus_id, opts);
}
function openConfirmDispatchModal(busId, opts){
  const e = findQueueEntry(busId);
  if(!e) return;
  opts = opts || {};
  if(opts.override) opts.overrideNote = e.short_reason || 'Pending requirements acknowledged';
  pendingDispatch = {busId: busId, opts: opts};
  const passengerCount = (opts.passengerCount !== undefined && opts.passengerCount !== null) ? opts.passengerCount : 0;
  document.getElementById('confirmDispatchBody').innerHTML =
    '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-bus-front"></i> ' + esc(t('details.bus','Bus')) + '</span><span class="qd-value">' + esc(e.bus_id) + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-person"></i> ' + esc(t('details.driver','Driver')) + '</span><span class="qd-value">' + esc(e.driver_name || '—') + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-signpost-split"></i> ' + esc(t('details.route','Route')) + '</span><span class="qd-value">' + esc(routeArrow(e.route_name || '—')) + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-hash"></i> ' + esc(t('prepare.trip_number','Trip Number')) + '</span><span class="qd-value">' + esc(e.next_trip_number || e.cycle || 1) + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-people"></i> ' + esc(t('details.passengers','Passengers Onboard')) + '</span><span class="qd-value">' + esc(passengerCount) + '</span></div>'
    + ((opts.startingOdo !== undefined && opts.startingOdo !== null && opts.startingOdo !== '') ? '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-speedometer2"></i> ' + esc(t('prepare.starting_odo','Starting ODO')) + '</span><span class="qd-value">' + esc(opts.startingOdo) + ' KM' + ((opts.actualLiters !== undefined && opts.actualLiters !== null && opts.actualLiters !== '') ? ' • ' + esc(opts.actualLiters) + ' L' : '') + '</span></div>' : '')
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-list-ol"></i> ' + esc(t('details.queue_position','Queue Position')) + '</span><span class="qd-value">#' + e.queue_position + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-activity"></i> ' + esc(t('confirm.status','Status')) + '</span><span class="qd-value">' + esc(e.status_label || '') + '</span></div>';
  const warn = document.getElementById('confirmDispatchWarning');
  const warnings = [];
  if(opts.overtakeReason) warnings.push(esc(t('confirm.overtake_warning','Queue order: dispatching out of order (:reason).', {reason: opts.overtakeReason})));
  if(opts.overrideNote) warnings.push(esc(t('confirm.override_warning','Pending requirements: :note', {note: opts.overrideNote})));
  if(warnings.length){
    warn.innerHTML = '<i class="bi bi-exclamation-triangle"></i><span>' + warnings.join('<br>') + '</span>';
    warn.style.display = 'flex';
  } else {
    warn.style.display = 'none';
    warn.innerHTML = '';
  }
  showModal('confirmDispatchModal');
}
function confirmQueueDispatch(){
  if(!pendingDispatch) return;
  const e = findQueueEntry(pendingDispatch.busId);
  if(!e){ closeModal('confirmDispatchModal'); return; }
  submitQueueDispatch(e, pendingDispatch.opts || {});
}
async function submitQueueDispatch(e, opts){
  if(dispatchInFlight) return;
  opts = opts || {};
  dispatchInFlight = true;
  const btn = document.getElementById('confirmDispatchBtn');
  if(btn){ btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + esc(t('confirm.dispatching','Dispatching...')); }
  try{
    const r = await fetch(API + 'create_dispatch', {
      method:'POST',
      headers:apiPostHeaders(),
      body:JSON.stringify({
        bus_id: e.bus_id,
        driver_id: e.driver_id,
        route_name: e.route_name,
        to_terminal_id: e.destination ? e.destination.terminal_id : 0,
        passenger_count: (opts.passengerCount !== undefined && opts.passengerCount !== null) ? opts.passengerCount : 0,
        starting_odo: (opts.startingOdo !== undefined && opts.startingOdo !== null) ? opts.startingOdo : '',
        kpl: (opts.kpl !== undefined && opts.kpl !== null) ? opts.kpl : '',
        actual_liters: (opts.actualLiters !== undefined && opts.actualLiters !== null) ? opts.actualLiters : '',
        terminal_id: TERMINAL_ID,
        queue_position: e.queue_position,
        overtake_reason: opts.overtakeReason || '',
        override_note: opts.overrideNote || ''
      })
    });
    const j = await parseApiResponse(r);
    if(isResponseSuccess(r, j)){
      closeModal('confirmDispatchModal');
      pendingDispatch = null;
      await loadQueue({force:true});
      showDispatchSuccess(e, j);
    } else if(r.status === 409 && j && j.code === 'queue_overtake_required'){
      closeModal('confirmDispatchModal');
      openOvertakeModal(e, {bus_id:j.data.ready_bus_id, queue_position:j.data.ready_position}, function(reason){
        submitQueueDispatch(e, Object.assign({}, opts, {overtakeReason: reason}));
      });
    } else if(r.status === 409 && j && (j.code === 'bus_already_dispatched' || j.code === 'driver_already_dispatched')){
      closeModal('confirmDispatchModal');
      pendingDispatch = null;
      const when = j.data && j.data.departure_time ? (' ' + formatClock12(j.data.departure_time)) : '';
      const who = j.code === 'bus_already_dispatched' ? ('Bus ' + e.bus_id) : t('details.driver','Driver');
      showAlert('error', t('alert.queue_updated','Queue Updated'), t('alert.already_dispatched',':who was already dispatched:when by another dispatcher. The queue has been refreshed.', {who: who, when: when}));
      await loadQueue({force:true});
    } else {
      const msg = extractErrorMessage(r, j, t('alert.dispatch_error','The trip could not be dispatched. The queue may have changed. Please refresh and try again.'));
      showAlert('error', t('alert.dispatch_failed','Dispatch Failed'), msg);
    }
  }catch(err){
    console.error('submitQueueDispatch error:', err);
    showAlert('error', t('alert.dispatch_failed','Dispatch Failed'), t('state.network_error','Network error. Please check your connection and try again.'));
  }finally{
    dispatchInFlight = false;
    if(btn){ btn.disabled = false; btn.innerHTML = '<i class="bi bi-send"></i> ' + esc(t('confirm.button','CONFIRM DISPATCH')); }
  }
}
function showDispatchSuccess(e, j){
  const order = j && j.dispatch_order ? ordinal(j.dispatch_order) : '—';
  const time = j && j.dispatch_time ? j.dispatch_time : '';
  const tripId = j && j.dispatch_id ? j.dispatch_id : (e.trip ? e.trip.id : null);
  document.getElementById('dispatchSuccessBody').innerHTML =
    '<div class="detail-summary"><div class="ds-top"><span class="ds-bus"><i class="bi bi-check-circle"></i> ' + esc(e.bus_id) + '</span></div>'
    + '<div class="ds-route">' + esc(routeArrow(e.route_name || '')) + '</div></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-list-ol"></i> ' + esc(t('success.order','Actual Dispatch Order')) + '</span><span class="qd-value">' + order + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-clock"></i> ' + esc(t('success.time','Dispatch Time')) + '</span><span class="qd-value">' + esc(formatClock12(time) || '—') + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-list-ol"></i> ' + esc(t('details.original_queue','Original Queue')) + '</span><span class="qd-value">#' + e.queue_position + '</span></div>';
  const viewBtn = document.getElementById('successViewTripBtn');
  if(tripId){
    viewBtn.style.display = '';
    viewBtn.onclick = function(){ closeModal('dispatchSuccessModal'); openQueuedTrip(tripId, e.bus_id); };
  } else {
    viewBtn.style.display = 'none';
    viewBtn.onclick = null;
  }
  showModal('dispatchSuccessModal');
}
function openSkipModal(busId){
  const e = findQueueEntry(busId);
  if(!e) return;
  skipTargetBus = busId;
  document.getElementById('skipBusTitle').textContent = t('skip.title','SKIP BUS') + ' — #' + e.queue_position;
  document.getElementById('skipBusInfo').textContent = e.bus_id + ' • ' + (e.driver_name || t('card.no_driver','No driver assigned')) + ' • ' + routeArrow(e.route_name || t('card.no_route','No route assigned'));
  document.getElementById('skipBusReason').textContent = e.short_reason || '';
  document.querySelectorAll('input[name="skipReason"]').forEach(r => { r.checked = false; });
  document.getElementById('skipNotes').value = '';
  document.getElementById('skipError').style.display = 'none';
  showModal('skipModal');
}
async function submitSkip(){
  const e = findQueueEntry(skipTargetBus);
  if(!e) return;
  const selected = document.querySelector('input[name="skipReason"]:checked');
  const err = document.getElementById('skipError');
  if(!selected){
    err.textContent = t('skip.error_reason','Please select a reason before skipping this trip.');
    err.style.display = 'block';
    return;
  }
  const notes = document.getElementById('skipNotes').value.trim();
  const btn = document.getElementById('skipConfirmBtn');
  if(btn){ btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + esc(t('skip.skipping','Skipping...')); }
  try{
    const r = await fetch(API + 'skip_queue_bus', {
      method:'POST',
      headers:apiPostHeaders(),
      body:JSON.stringify({bus_id:e.bus_id, action:'skip', reason_category:selected.value, notes:notes, csrf_token:CSRF_TOKEN})
    });
    const j = await parseApiResponse(r);
    if(isResponseSuccess(r, j)){
      closeModal('skipModal');
      await loadQueue({force:true});
      showAlert('success', t('alert.queue_updated','Queue Updated'), t('skip.success','Bus :bus was temporarily skipped.', {bus: e.bus_id}));
    } else {
      err.textContent = extractErrorMessage(r, j, t('skip.error_reason','Please select a reason before skipping this trip.'));
      err.style.display = 'block';
    }
  }catch(error){
    console.error('submitSkip error:', error);
    err.textContent = t('state.network_error','Network error. Please check your connection and try again.');
    err.style.display = 'block';
  }finally{
    if(btn){ btn.disabled = false; btn.innerHTML = '<i class="bi bi-slash-circle"></i> ' + esc(t('skip.button','SKIP BUS')); }
  }
}
function clearSkip(busId){
  const e = findQueueEntry(busId);
  if(!e) return;
  showConfirm(t('action.clear_skip','Clear Skip'), e.bus_id + ' (#' + e.queue_position + ')', async function(){
    try{
      const r = await fetch(API + 'skip_queue_bus', {
        method:'POST',
        headers:apiPostHeaders(),
        body:JSON.stringify({bus_id:e.bus_id, action:'clear', csrf_token:CSRF_TOKEN})
      });
      const j = await parseApiResponse(r);
      if(isResponseSuccess(r, j)){
        await loadQueue({force:true});
        showAlert('success', t('alert.queue_updated','Queue Updated'), e.bus_id + ' — ' + t('action.clear_skip','Clear Skip'));
      } else {
        showAlert('error', t('alert.action_failed','Action Failed'), extractErrorMessage(r, j, t('state.network_error','Network error. Please check your connection and try again.')));
      }
    }catch(error){
      console.error('clearSkip error:', error);
      showAlert('error', t('alert.action_failed','Action Failed'), t('state.network_error','Network error. Please check your connection and try again.'));
    }
  });
}
let assignDriverTarget = null;
let assignDriverIsChange = false;
let prepareTargetBus = null;

function openAssignDriverModal(busId, isChange){
  const e = findQueueEntry(busId);
  if(!e) return;
  assignDriverTarget = busId;
  assignDriverIsChange = !!isChange || (!!e.driver_id && e.next_action !== 'assign_driver');
  const title = document.getElementById('assignDriverTitle');
  if(title) title.innerHTML = '<i class="bi bi-person-plus"></i> ' + esc(t(assignDriverIsChange ? 'assign.change_title' : 'assign.title', assignDriverIsChange ? 'CHANGE BUS ASSIGNMENT' : 'ASSIGN DRIVER'));
  const body = document.getElementById('assignDriverBody');
  const currentLine = '<div class="queue-detail-row"><span class="qd-label">' + esc(t('assign.current','Current driver')) + '</span><span class="qd-value">' + esc(e.driver_name || t('card.no_driver','No driver assigned')) + '</span></div>';
  body.innerHTML = '<div class="queue-detail-row"><span class="qd-label">' + esc(t('details.bus','Bus')) + '</span><span class="qd-value">' + esc(e.bus_id) + '</span></div>' + currentLine;
  const select = document.getElementById('assignDriverSelect');
  const drivers = (queueState.availableDrivers || []).filter(d => !assignDriverIsChange || String(d.driver_id) !== String(e.driver_id || ''));
  let html = '<option value="">' + esc(t('assign.select','Select driver')) + '</option>';
  drivers.forEach(d => { html += '<option value="' + esc(d.driver_id) + '">' + esc((d.full_name || d.driver_id) + ' (' + d.driver_id + ')') + '</option>'; });
  select.innerHTML = html;
  select.disabled = drivers.length === 0;
  const btn = document.getElementById('assignDriverConfirmBtn');
  btn.disabled = drivers.length === 0;
  btn.innerHTML = '<i class="bi bi-person-check"></i> ' + esc(t(assignDriverIsChange ? 'assign.change_confirm' : 'assign.confirm', assignDriverIsChange ? 'CHANGE ASSIGNMENT' : 'ASSIGN DRIVER'));
  const err = document.getElementById('assignDriverError');
  err.style.display = 'none';
  err.textContent = '';
  if(!drivers.length){
    err.textContent = t('assign.no_drivers','No available drivers right now. All active drivers are already assigned to a bus.');
    err.style.display = 'block';
  }
  showModal('assignDriverModal');
}
async function submitAssignDriver(){
  const e = findQueueEntry(assignDriverTarget);
  if(!e) return;
  const select = document.getElementById('assignDriverSelect');
  const driverId = select.value;
  const err = document.getElementById('assignDriverError');
  if(!driverId){
    err.textContent = t('assign.error_select','Please select a driver.');
    err.style.display = 'block';
    return;
  }
  const btn = document.getElementById('assignDriverConfirmBtn');
  const original = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + esc(t('assign.saving','Saving...'));
  try{
    const r = await fetch(API + 'assign_driver', {
      method:'POST',
      headers:apiPostHeaders(),
      body:JSON.stringify({bus_id:e.bus_id, driver_id:driverId, action: assignDriverIsChange ? 'change' : 'assign', csrf_token:CSRF_TOKEN})
    });
    const j = await parseApiResponse(r);
    if(isResponseSuccess(r, j)){
      closeModal('assignDriverModal');
      await loadQueue({force:true});
      await preloadDropdowns();
      const name = (j && j.data && j.data.driver_name) ? j.data.driver_name : driverId;
      showAlert('success', t('alert.queue_updated','Queue Updated'), assignDriverIsChange
        ? t('assign.change_success','Bus :bus is now assigned to :driver.', {bus:e.bus_id, driver:name})
        : t('assign.success',':driver is now assigned to Bus :bus.', {driver:name, bus:e.bus_id}));
    } else {
      err.textContent = extractErrorMessage(r, j, t('assign.error','The driver assignment could not be saved. Please try again.'));
      err.style.display = 'block';
    }
  }catch(error){
    console.error('submitAssignDriver error:', error);
    err.textContent = t('state.network_error','Network error. Please check your connection and try again.');
    err.style.display = 'block';
  }finally{
    btn.disabled = false;
    btn.innerHTML = original;
  }
}
function openPrepareTrip(busId){
  const e = findQueueEntry(busId);
  if(!e) return;
  prepareTargetBus = busId;
  const ready = e.status === 'ready';
  const titleEl = document.getElementById('prepareTitle');
  if(titleEl) titleEl.innerHTML = '<i class="bi ' + (ready ? 'bi-send' : 'bi-clipboard-check') + '"></i> ' + esc(t(ready ? 'prepare.dispatch_title' : 'prepare.title', ready ? 'DISPATCH TRIP' : 'PREPARE TRIP'));
  const fuel = e.fuel_reference || {};
  const presence = e.presence || {};
  const dest = e.destination && e.destination.terminal_name ? e.destination.terminal_name : t('details.not_set','Not set');
  document.getElementById('prepareBody').innerHTML =
      '<div class="detail-summary"><div class="ds-top"><span class="ds-bus"><i class="bi bi-bus-front"></i> ' + esc(e.bus_id) + '</span>' + statusPill(e.status) + '</div>'
    + '<div class="ds-route"><i class="bi bi-signpost-split"></i> ' + esc(routeArrow(e.route_name || t('card.no_route','No route assigned'))) + '</div></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-person-badge"></i> ' + esc(t('details.driver','Driver')) + '</span><span class="qd-value">' + esc(e.driver_name || '—') + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-hash"></i> ' + esc(t('prepare.trip_number','Trip Number')) + '</span><span class="qd-value">' + esc(e.next_trip_number || e.cycle || 1) + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-geo"></i> ' + esc(t('details.destination','Destination')) + '</span><span class="qd-value">' + esc(dest) + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-geo-alt"></i> ' + esc(t('prepare.presence','Bus Presence')) + '</span><span class="qd-value">' + esc(presence.detail || '—') + '</span></div>'
    + '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-speedometer2"></i> ' + esc(t('prepare.fuel','Fuel / Odometer')) + '</span><span class="qd-value">' + esc(fuel.detail || '—') + (fuel.starting_odo ? ' (' + fuel.starting_odo + ' KM)' : '') + '</span></div>'
    + ((e.prepared && e.prepared.ready) ? '<div class="queue-detail-row"><span class="qd-label"><i class="bi bi-clipboard-check"></i> ' + esc(t('prepare.prepared','Prepared')) + '</span><span class="qd-value">' + esc((e.prepared.by_name || '') + (e.prepared.at ? ' • ' + formatClock12(e.prepared.at) : '')) + '</span></div>' : '')
    + (ready ? '' : '<div class="confirm-warning" style="display:flex;"><i class="bi bi-exclamation-triangle"></i><span>' + esc(e.short_reason || t('prepare.not_ready','Complete the required pre-trip items before dispatching.')) + '</span></div>');
  const input = document.getElementById('preparePassengerCount');
  input.value = (e.passenger_count !== null && e.passenger_count !== undefined) ? e.passenger_count : 0;
  const odoInput = document.getElementById('prepareStartingOdo');
  if(odoInput) odoInput.value = (fuel.starting_odo !== null && fuel.starting_odo !== undefined && fuel.starting_odo !== '') ? fuel.starting_odo : '';
  const kplInput = document.getElementById('prepareKpl');
  if(kplInput && !parseFloat(kplInput.value)) kplInput.value = 9;
  const litersInput = document.getElementById('prepareLiters');
  if(litersInput) litersInput.value = '';
  const prepared = !!(e.prepared && e.prepared.ready);
  const btn = document.getElementById('prepareDispatchBtn');
  if(ready){
    btn.disabled = false;
    btn.style.opacity = '';
    btn.innerHTML = '<i class="bi bi-send"></i> ' + esc(t('action.dispatch','Dispatch'));
    btn.onclick = dispatchFromPrepare;
  } else if(!prepared){
    btn.disabled = false;
    btn.style.opacity = '';
    btn.innerHTML = '<i class="bi bi-clipboard-check"></i> ' + esc(t('prepare.mark','MARK AS PREPARED'));
    btn.onclick = submitPrepareTrip;
  } else {
    btn.disabled = true;
    btn.style.opacity = '.55';
    btn.innerHTML = '<i class="bi bi-send"></i> ' + esc(t('action.dispatch','Dispatch'));
    btn.onclick = dispatchFromPrepare;
  }
  const err = document.getElementById('prepareError');
  err.style.display = 'none';
  err.textContent = '';
  showModal('prepareModal');
}
let isPreparingTrip = false;
async function submitPrepareTrip(){
  const e = findQueueEntry(prepareTargetBus);
  if(!e || isPreparingTrip) return;
  isPreparingTrip = true;
  const btn = document.getElementById('prepareDispatchBtn');
  const original = btn.innerHTML;
  let reopened = false;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + esc(t('prepare.saving','Preparing...'));
  const err = document.getElementById('prepareError');
  err.style.display = 'none';
  try{
    const r = await fetch(API + 'prepare_trip', {
      method:'POST',
      headers:apiPostHeaders(),
      body:JSON.stringify({bus_id:e.bus_id, csrf_token:CSRF_TOKEN})
    });
    const j = await parseApiResponse(r);
    if(isResponseSuccess(r, j)){
      await loadQueue({force:true});
      reopened = true;
      openPrepareTrip(e.bus_id);
      return;
    }
    err.textContent = extractErrorMessage(r, j, t('prepare.error','The trip preparation could not be saved. Please try again.'));
    err.style.display = 'block';
  }catch(error){
    console.error('submitPrepareTrip error:', error);
    err.textContent = t('state.network_error','Network error. Please check your connection and try again.');
    err.style.display = 'block';
  }finally{
    isPreparingTrip = false;
    if(!reopened){
      btn.disabled = false;
      btn.innerHTML = original;
    }
  }
}
function dispatchFromPrepare(){
  const e = findQueueEntry(prepareTargetBus);
  if(!e) return;
  if(e.status !== 'ready'){
    const err = document.getElementById('prepareError');
    err.textContent = e.short_reason || t('prepare.not_ready','Complete the required pre-trip items before dispatching.');
    err.style.display = 'block';
    return;
  }
  const passengers = parseInt(document.getElementById('preparePassengerCount').value, 10);
  const startingOdo = document.getElementById('prepareStartingOdo') ? document.getElementById('prepareStartingOdo').value : '';
  const kpl = document.getElementById('prepareKpl') ? document.getElementById('prepareKpl').value : '';
  const liters = document.getElementById('prepareLiters') ? document.getElementById('prepareLiters').value : '';
  closeModal('prepareModal');
  startQueueDispatch(e.bus_id, {
    passengerCount: isNaN(passengers) ? 0 : Math.max(0, passengers),
    startingOdo: startingOdo,
    kpl: kpl,
    actualLiters: liters
  });
}
function openOvertakeModal(target, ready, onContinue){
  overtakeContinue = onContinue;
  const readyPosition = ready.queue_position || ready.ready_position || '—';
  document.getElementById('overtakeReady').textContent = t('overtake.ready_body',':bus is Queue #:position and is also ready.', {bus: ready.bus_id || '', position: readyPosition});
  document.getElementById('overtakeTarget').textContent = t('overtake.body',':bus is Queue #:position.', {bus: target.bus_id, position: (target.queue_position || '—')});
  document.getElementById('overtakeReason').value = '';
  document.getElementById('overtakeError').style.display = 'none';
  showModal('overtakeModal');
}
function submitOvertake(){
  const reason = document.getElementById('overtakeReason').value;
  const err = document.getElementById('overtakeError');
  if(!reason){
    err.textContent = t('overtake.error','Please select a reason to continue.');
    err.style.display = 'block';
    return;
  }
  closeModal('overtakeModal');
  const cb = overtakeContinue;
  overtakeContinue = null;
  if(typeof cb === 'function') cb(reason);
}
function toggleHistorySearch(){
  const panel = document.getElementById('historySearchPanel');
  const btn = document.getElementById('historySearchBtn');
  const show = panel.style.display === 'none';
  if(show){
    panel.style.display = 'block';
    document.getElementById('historyControls').style.display = 'flex';
    btn.setAttribute('aria-expanded', 'true');
    btn.innerHTML = '<i class="bi bi-arrow-left"></i> ' + esc(t('history.hide_search','Hide Search'));
    loadHistory();
  } else {
    panel.style.display = 'none';
    document.getElementById('historyControls').style.display = 'none';
    document.getElementById('paginationControls').style.display = 'none';
    btn.setAttribute('aria-expanded', 'false');
    btn.innerHTML = '<i class="bi bi-search"></i> ' + esc(t('history.search','Search Older Trips'));
  }
}

function openTripDetail(id){
  const v=tripDetailCache[id];
  if(!v) return;
  const st=statusLabel(v);
  const dur=durationLabel(v);
  const dist = v.distance_traveled !== null && v.distance_traveled !== undefined 
    ? v.distance_traveled + ' KM' 
    : (v.starting_odo !== null && v.ending_odo !== null && v.starting_odo !== undefined && v.ending_odo !== undefined 
        ? (v.ending_odo - v.starting_odo).toFixed(1) + ' KM' 
        : '-');
  const expFuel = v.expected_fuel !== null && v.expected_fuel !== undefined ? v.expected_fuel + ' L' : '-';

  document.getElementById('tripDetailBody').innerHTML=
    `<div class="trip-hero">
       <div class="trip-hero-top">
         <span class="trip-hero-id"><i class="bi bi-hash"></i> Trip ${v.trip_number||v.id}</span>
         <span class="status-badge status-${v.status}">${st}</span>
       </div>
       <div class="trip-hero-route"><i class="bi bi-signpost-split"></i> ${esc(routeArrow(v.route_name))}</div>
       <div class="trip-hero-date"><i class="bi bi-calendar3"></i> ${v.trip_date||'-'}</div>
     </div>
     <div class="trip-info-grid">
       <div class="trip-info-card">
         <div class="tic-icon bus"><i class="bi bi-bus-front"></i></div>
         <div class="tic-label">Bus</div>
         <div class="tic-value">${esc(v.bus_id)}</div>
       </div>
       <div class="trip-info-card">
         <div class="tic-icon driver"><i class="bi bi-person-badge"></i></div>
         <div class="tic-label">Driver</div>
         <div class="tic-value">${esc(v.driver_name||v.driver_id||'-')}</div>
       </div>
     </div>
     <div class="trip-detail-grid">
       <div class="trip-detail-row"><span class="td-label"><i class="bi bi-person-check"></i> Dispatched By</span><span class="td-value">${esc(v.dispatcher_name||v.dispatcher_id||'-')}</span></div>
       <div class="trip-detail-row"><span class="td-label"><i class="bi bi-send"></i> Dispatch Time</span><span class="td-value">${v.departure_time||'-'}</span></div>
       ${(v.from_terminal_name||v.to_terminal_name)?`<div class="trip-detail-row"><span class="td-label"><i class="bi bi-signpost-split"></i> Terminal</span><span class="td-value">${esc(v.from_terminal_name||'-')} → ${esc(v.to_terminal_name||'-')}</span></div>`:''}
       ${v.arrival_by?`<div class="trip-detail-row"><span class="td-label"><i class="bi bi-person-check"></i> Arrived By</span><span class="td-value">${esc(v.arrival_by)}${v.arrival_recorded_at?' • '+v.arrival_recorded_at:''}</span></div>`:''}
       ${v.arrival_time?`<div class="trip-detail-row"><span class="td-label"><i class="bi bi-flag"></i> Arrival</span><span class="td-value">${v.arrival_time}</span></div>`:''}
       ${dur?`<div class="trip-detail-row"><span class="td-label"><i class="bi bi-hourglass"></i> Duration</span><span class="td-value">${dur}</span></div>`:''}
       <div class="trip-detail-row"><span class="td-label"><i class="bi bi-people"></i> Passengers</span><span class="td-value">${v.total_passengers||'0'}</span></div>
       <div class="trip-detail-row" style="flex-direction:column;align-items:stretch;gap:8px;background:#faf9e5;border-color:#e5e0a4;">
         <div style="display:flex;justify-content:space-between;align-items:center;font-weight:700;color:var(--text-primary);font-size:12px;">
           <span><i class="bi bi-fuel-pump" style="color:var(--primary);"></i> Fuel & Odometer Information</span>
           <span style="font-size:11px;color:var(--text-muted);">${v.price_per_liter ? 'Diesel: ₱'+Number(v.price_per_liter).toFixed(2)+'/L' : ''}</span>
         </div>
         <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:12px;">
           <div><span style="color:var(--text-muted);font-size:10px;display:block;">STARTING ODO</span><strong>${v.starting_odo !== null && v.starting_odo !== undefined ? v.starting_odo + ' KM' : '-'}</strong></div>
           <div><span style="color:var(--text-muted);font-size:10px;display:block;">ENDING ODO</span><strong>${v.ending_odo !== null && v.ending_odo !== undefined ? v.ending_odo + ' KM' : '<em style="color:#888;">Not yet recorded</em>'}</strong></div>
           <div><span style="color:var(--text-muted);font-size:10px;display:block;">DISTANCE TRAVELED</span><strong>${dist}</strong></div>
           <div><span style="color:var(--text-muted);font-size:10px;display:block;">BUS KPL</span><strong>${v.kpl ? v.kpl + ' KM/L' : '-'}</strong></div>
           <div><span style="color:var(--text-muted);font-size:10px;display:block;">ACTUAL FUEL</span><strong>${v.fuel_liters ? v.fuel_liters + ' L' : '-'}</strong></div>
           <div><span style="color:var(--text-muted);font-size:10px;display:block;">EXPECTED CONSUMPTION</span><strong>${expFuel}</strong></div>
         </div>
       </div>
     </div>`;
  const actions=[];
  actions.push(`<button class="btn-action btn-view" onclick="closeModal('tripDetailModal');openEditFuelModal(${v.id})"><i class="bi bi-fuel-pump"></i> Edit Fuel & ODO</button>`);
  if(v.status==='Departed' && Number(v.to_terminal_id)===TERMINAL_ID) actions.push(`<button class="btn-action btn-arrive" onclick="closeModal('tripDetailModal');openArrive(${v.id})"><i class="bi bi-check-circle"></i> Arrive</button>`);
  if(v.status==='Scheduled') actions.push(`<button class="btn-action btn-cancel" onclick="quickAction(${v.id},'cancel')"><i class="bi bi-x-circle"></i> Cancel Trip</button>`);
  document.getElementById('tripDetailActions').innerHTML=actions.join('');
  showModal('tripDetailModal');
}

// Pre-load dropdown data on page load
async function fetchJson(url,opts){
  try{
    const r=await fetch(url,opts);
    const text=await r.text();
    try{ return JSON.parse(text); }catch(e){ console.error('JSON parse error from '+url+':', text); return{success:false,data:[]}; }
  }catch(e){
    console.error('fetch error for '+url+':', e);
    return{success:false,data:[]};
  }
}
async function preloadDropdowns(){
  const [b,d,r]=await Promise.all([
    fetchJson(API+'get_available_buses',{headers:apiHeaders()}),
    fetchJson(API+'get_available_drivers',{headers:apiHeaders()}),
    fetchJson(API+'get_routes',{headers:apiHeaders()})
  ]);
  cacheBuses=b.data||[]; cacheDrivers=d.data||[]; cacheRoutes=r.data||[];
  return true;
}
preloadDropdowns();

function setSelectIfOption(selectId, value){
  const sel = document.getElementById(selectId);
  if(!sel) return false;
  for(let i=0;i<sel.options.length;i++){
    if(String(sel.options[i].value) === String(value)){ sel.value = sel.options[i].value; return true; }
  }
  return false;
}
function resolveDriverName(driverId){
  const drv = cacheDrivers.find(d => String(d.driver_id) === String(driverId));
  if(drv) return drv.full_name;
  const queueDrv = queueState.entries.find(e => String(e.driver_id) === String(driverId));
  if(queueDrv && queueDrv.driver_name) return queueDrv.driver_name;
  return String(driverId);
}
async function populateDropdowns(){
  if(cacheBuses.length===0 || cacheDrivers.length===0) await preloadDropdowns();
  const activeEntries = queueState.entries.filter(e => e.status !== 'departed');
  const queueBusIds = activeEntries.map(e => String(e.bus_id));
  let bs='<option value="">Select Bus</option>';
  if(activeEntries.length){
    bs += '<optgroup label="Terminal Queue">';
    activeEntries.forEach(e => {
      const sw = statusWord(e);
      const statusWordText = sw.charAt(0).toUpperCase() + sw.slice(1);
      const pos = e.queue_position ? (' · Queue #'+e.queue_position) : '';
      const label = e.bus_id + (e.plate_number ? (' - '+e.plate_number) : '') + ' · ' + statusWordText + pos;
      bs += '<option value="'+esc(e.bus_id)+'"'+(e.status==='ready'?'':' disabled')+'>'+esc(label)+'</option>';
    });
    bs += '</optgroup>';
  }
  const otherBuses = cacheBuses.filter(b => queueBusIds.indexOf(String(b.bus_id)) === -1);
  if(otherBuses.length){
    bs += '<optgroup label="Other Available Buses">';
    otherBuses.forEach(b => { bs += '<option value="'+esc(b.bus_id)+'">'+esc(b.bus_id + (b.plate_number ? (' - '+b.plate_number) : '') + ' · Not in queue')+'</option>'; });
    bs += '</optgroup>';
  }
  document.getElementById('newBus').innerHTML=bs;

  let ds='<option value="">Select Driver</option>';
  const queueDrivers = [];
  activeEntries.forEach(e => {
    if(e.driver_id && !queueDrivers.some(d => String(d.driver_id) === String(e.driver_id))){
      queueDrivers.push({driver_id:e.driver_id, name:e.driver_name, checked_in: !!(e.presence && e.presence.checked_in)});
    }
  });
  if(queueDrivers.length){
    ds += '<optgroup label="Queue Drivers">';
    queueDrivers.forEach(d => { ds += '<option value="'+esc(d.driver_id)+'">'+esc((d.name||d.driver_id)+' ('+d.driver_id+') · '+(d.checked_in?'Checked in':'Not checked in'))+'</option>'; });
    ds += '</optgroup>';
  }
  const queueDriverIds = queueDrivers.map(d => String(d.driver_id));
  const otherDrivers = cacheDrivers.filter(d => queueDriverIds.indexOf(String(d.driver_id)) === -1);
  if(otherDrivers.length){
    ds += '<optgroup label="Other Available Drivers">';
    otherDrivers.forEach(d => { ds += '<option value="'+esc(d.driver_id)+'">'+esc(d.full_name+' ('+d.driver_id+')')+'</option>'; });
    ds += '</optgroup>';
  }
  document.getElementById('newDriver').innerHTML=ds;

  let rs='<option value="">Select Route</option>';
  cacheRoutes.forEach(r=>{rs+=`<option value="${r}">${r}</option>`;});
  document.getElementById('newRoute').innerHTML=rs;
  let ts='<option value="">Dest. Terminal</option>';
  cacheTerminals.forEach(t=>{ts+=`<option value="${t.id}">${t.terminal_name}</option>`;});
  document.getElementById('newDestTerminal').innerHTML=ts;
}

function renderBusQueueInfo(entry){
  const box = document.getElementById('busQueueBadge');
  if(!box) return;
  const submitBtn = document.querySelector('#createModal .btn-primary-app');
  if(!entry){
    box.style.display = 'none';
    box.innerHTML = '';
    if(submitBtn) submitBtn.disabled = false;
    return;
  }
  const items = (entry.readiness || []).map(item => {
    const cls = item.ok ? 'ok' : 'warn';
    const icon = item.ok ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
    return '<span class="qrb-item '+cls+'"><i class="bi '+icon+'"></i> '+esc(item.label)+'</span>';
  }).join('');
  box.innerHTML = '<div class="qrb-head">'+statusPill(entry.status)+'<span class="qrb-pos">Queue #'+entry.queue_position+'</span></div>'
    + '<div class="qrb-reason">'+esc(entry.short_reason || '')+'</div>'
    + '<div class="qrb-items">'+items+'</div>';
  box.style.display = 'block';
  if(submitBtn) submitBtn.disabled = entry.status !== 'ready';
}

function updateFuelLinkedBadge(bus, driverName){
  const badge = document.getElementById('fuelDispatchBadge');
  if(!badge) return;
  if(!bus && !driverName){
    badge.style.display = 'none';
    return;
  }
  const busTxt = bus ? (bus.bus_id + (bus.plate_number ? ' (' + bus.plate_number + ')' : '')) : 'Not selected';
  const drvTxt = driverName ? driverName : 'Not selected';
  document.getElementById('fuelLinkedBus').textContent = busTxt;
  document.getElementById('fuelLinkedDriver').textContent = drvTxt;
  badge.style.display = 'block';
}

async function onBusSelected(busId){
  if(!busId){
    document.getElementById('fuelDispatchBadge').style.display='none';
    document.getElementById('recentFuelHint').textContent='';
    renderBusQueueInfo(null);
    const emptyDriverSelect = document.getElementById('newDriver');
    if(emptyDriverSelect) emptyDriverSelect.disabled = false;
    return;
  }
  const bus = cacheBuses.find(b => String(b.bus_id) === String(busId));
  const entry = findQueueEntry(busId);
  renderBusQueueInfo(entry);
  if(entry){
    if(entry.route_name) setSelectIfOption('newRoute', entry.route_name);
    if(entry.destination && entry.destination.terminal_id) setSelectIfOption('newDestTerminal', String(entry.destination.terminal_id));
  }

  // Auto-fill the driver assigned to this bus whenever the current choice does
  // not match it: queue entry driver first, then the driver_bus_assignment
  // pairing (assigned_driver_id) resolved by the buses API.
  const driverSelect = document.getElementById('newDriver');
  const assignedDriverId = (entry && entry.driver_id)
    ? String(entry.driver_id)
    : (bus && bus.assigned_driver_id ? String(bus.assigned_driver_id) : '');
  if(driverSelect && assignedDriverId && String(driverSelect.value) !== assignedDriverId){
    setSelectIfOption('newDriver', assignedDriverId);
  }
  if(driverSelect) driverSelect.disabled = !!(entry && entry.driver_id);
  let driverName = '';
  if(driverSelect && driverSelect.value){
    driverName = resolveDriverName(driverSelect.value);
  }
  updateFuelLinkedBadge(bus, driverName);

  try {
    const res = await fetch(API + 'get_bus_fuel_info?bus_id=' + encodeURIComponent(busId), {headers: apiHeaders()});
    const j = await res.json();
    if(j.success && j.data){
      currentFuelPrice = Number(j.data.price_per_liter) || 65.55;
      document.getElementById('fuelPriceBadge').textContent = 'Diesel: ₱' + currentFuelPrice.toFixed(2) + '/L';
      if(j.data.suggested_starting_odo !== null && j.data.suggested_starting_odo !== undefined){
        document.getElementById('newStartingOdo').value = j.data.suggested_starting_odo;
      }
      if(j.data.suggested_kpl){
        document.getElementById('newKpl').value = j.data.suggested_kpl;
      }
      if(j.data.today_summary && j.data.today_summary.total_liters > 0){
        document.getElementById('recentFuelHint').textContent = 'Logged today: ' + j.data.today_summary.total_liters + ' L';
        document.getElementById('newActualLiters').placeholder = j.data.today_summary.total_liters;
      } else if(j.data.last_log && j.data.last_log.liters > 0){
        document.getElementById('recentFuelHint').textContent = 'Last log: ' + j.data.last_log.liters + ' L';
        document.getElementById('newActualLiters').placeholder = j.data.last_log.liters;
      } else {
        document.getElementById('recentFuelHint').textContent = '';
        document.getElementById('newActualLiters').placeholder = 'e.g. 50';
      }
    }
  } catch(e){}
}

function onDriverSelected(){
  const busSelect = document.getElementById('newBus');
  const bus = cacheBuses.find(b => String(b.bus_id) === String(busSelect.value));
  const driverSelect = document.getElementById('newDriver');
  updateFuelLinkedBadge(bus, driverSelect.value ? resolveDriverName(driverSelect.value) : '');
}

async function showCreateModal(){
  showModal('createModal');
  const now=getPHTDate();
  document.getElementById('dispatchTimeDisplay').textContent=formatPHT(now);
  
  // Reset fuel fields
  document.getElementById('newStartingOdo').value = '';
  document.getElementById('newKpl').value = '9';
  document.getElementById('newActualLiters').value = '';
  document.getElementById('recentFuelHint').textContent = '';
  document.getElementById('newEndingOdo').value = 'Not yet recorded';
  document.getElementById('fuelDispatchBadge').style.display = 'none';
  renderBusQueueInfo(null);

  if(!queueState.entries.length){ await loadQueue({force:true}); }
  // Refresh buses/drivers so the bus's current assignment (assigned_driver_id)
  // is always the option the modal offers.
  await preloadDropdowns();
  await populateDropdowns();
  if(defaultRoute){
    const rs=document.getElementById('newRoute');
    for(let i=0;i<rs.options.length;i++){if(rs.options[i].value===defaultRoute){rs.value=defaultRoute;break;}}
  }
  if(defaultDestTerminalId){
    const ts=document.getElementById('newDestTerminal');
    for(let i=0;i<ts.options.length;i++){if(ts.options[i].value==defaultDestTerminalId){ts.value=defaultDestTerminalId;break;}}
  }

  // Pre-fetch current fuel price for badge
  try {
    const pRes = await fetch(API + 'get_bus_fuel_info?bus_id=' + encodeURIComponent(cacheBuses[0]?.bus_id || 'BUS-01'), {headers: apiHeaders()});
    const pj = await pRes.json();
    if(pj.success && pj.data && pj.data.price_per_liter){
      currentFuelPrice = Number(pj.data.price_per_liter);
      document.getElementById('fuelPriceBadge').textContent = 'Diesel: ₱' + currentFuelPrice.toFixed(2) + '/L';
    }
  } catch(e){}
}

document.getElementById('newBus')?.addEventListener('change', function(){
  onBusSelected(this.value);
});
document.getElementById('newDriver')?.addEventListener('change', function(){
  onDriverSelected();
});

async function createDispatch(){
  const bus_id=document.getElementById('newBus').value;
  const driver_id=document.getElementById('newDriver').value;
  const route_name=document.getElementById('newRoute').value;
  const to_terminal_id=document.getElementById('newDestTerminal').value;
  const passenger_count=document.getElementById('newPassengerCount').value||0;
  const starting_odo=document.getElementById('newStartingOdo').value;
  const kpl=document.getElementById('newKpl').value;
  const actual_liters=document.getElementById('newActualLiters').value;

  if(!bus_id||!driver_id||!route_name){
    showAlert('error', t('alert.incomplete_form','Incomplete Form'), t('alert.incomplete_form_body','Please select bus, driver, and route.'));
    return;
  }
  const entry = findQueueEntry(bus_id);
  if(entry && !isDispatchable(entry)){
    showAlert('error', t('alert.dispatch_blocked','Dispatch Unavailable'), entry.short_reason || t('alert.prepare_required','Complete the required pre-trip items before dispatching this bus.'));
    return;
  }
  submitAdhocDispatch({
    bus_id,
    driver_id,
    route_name,
    to_terminal_id,
    passenger_count,
    terminal_id:TERMINAL_ID,
    starting_odo,
    kpl,
    actual_liters,
    queue_position: entry ? entry.queue_position : undefined,
    override_note: ''
  });
}

async function submitAdhocDispatch(payload, overtakeReason){
  if(dispatchInFlight) return;
  dispatchInFlight = true;
  const btn = document.querySelector('#createModal .btn-primary-app');
  if(btn){ btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' + esc(t('confirm.dispatching','Dispatching...')); }
  try{
    if(overtakeReason) payload.overtake_reason = overtakeReason;
    const r=await fetch(API+'create_dispatch',{
      method:'POST',
      headers:apiPostHeaders(),
      body:JSON.stringify(payload)
    });
    const j=await parseApiResponse(r);
    if(isResponseSuccess(r, j)){
      closeModal('createModal');
      await loadQueue({force:true});
      const order = j && j.dispatch_order ? (' ' + t('success.order','Actual Dispatch Order') + ': ' + ordinal(j.dispatch_order)) : '';
      showAlert('success', t('alert.bus_dispatched','Bus Dispatched'), t('alert.bus_dispatched_body','Trip created and bus dispatched successfully.') + order);
    } else if(r.status===409 && j && j.code==='queue_overtake_required'){
      openOvertakeModal(
        {bus_id:payload.bus_id, queue_position:j.data.target_position},
        {bus_id:j.data.ready_bus_id, queue_position:j.data.ready_position},
        function(reason){ submitAdhocDispatch(payload, reason); }
      );
    } else {
      const errMsg = extractErrorMessage(r, j, t('alert.dispatch_error','The trip could not be dispatched. The queue may have changed. Please refresh and try again.'));
      showAlert('error', t('alert.dispatch_failed','Dispatch Failed'), errMsg);
    }
  }catch(e){
    console.error('submitAdhocDispatch error:', e);
    showAlert('error', t('alert.dispatch_failed','Dispatch Failed'), t('state.network_error','Network error. Please check your connection and try again.'));
  }finally{
    dispatchInFlight = false;
    if(btn){ btn.disabled = false; btn.innerHTML = '<i class="bi bi-send"></i> ' + esc(t('common.dispatch_bus','Dispatch Bus')); }
  }
}

async function openArrive(id){
  arriveId=id;
  const v = tripDetailCache[id];
  let startOdo = v ? (v.starting_odo !== null && v.starting_odo !== undefined ? Number(v.starting_odo) : null) : null;
  // Start EDO Meter: prefer the opposite/arrival terminal's last recorded reading (D1/D4).
  if(v && v.bus_id){
    try{
      const res = await fetch(API + 'get_opposite_terminal_odo?bus_id=' + encodeURIComponent(v.bus_id) + '&terminal_id=' + encodeURIComponent(v.to_terminal_id || TERMINAL_ID), {headers: apiHeaders()});
      const j = await res.json();
      if(j && j.success && j.data && j.data.start_edo !== null && j.data.start_edo !== undefined){
        startOdo = Number(j.data.start_edo);
      }
    }catch(e){
      console.error('Opposite-terminal Start EDO fetch failed, falling back to trip record:', e);
    }
  }
  arriveStartOdo = startOdo;
  const kpl = v ? (Number(v.kpl) || 9) : 9;
  const infoEl = document.getElementById('arriveTripOdoInfo');
  if(infoEl){
    infoEl.textContent = 'Start EDO: ' + (startOdo !== null ? startOdo + ' KM' : 'Not recorded') + ' • Bus KPL: ' + kpl + ' KM/L';
  }
  const endInput = document.getElementById('arriveEndingOdo');
  if(endInput){
    endInput.value = (v && v.ending_odo !== null && v.ending_odo !== undefined) ? v.ending_odo : '';
  }
  onArriveOdoChange();
  showModal('arriveModal');
}

function onArriveOdoChange(){
  const v = tripDetailCache[arriveId];
  const kpl = v ? (Number(v.kpl) || 9) : 9;
  const endVal = parseFloat(document.getElementById('arriveEndingOdo').value);
  const preview = document.getElementById('arriveCalcPreview');
  if(!isNaN(endVal) && arriveStartOdo !== null && arriveStartOdo !== undefined && endVal >= arriveStartOdo){
    const dist = (endVal - arriveStartOdo).toFixed(1);
    const expFuel = kpl > 0 ? (dist / kpl).toFixed(2) : '-';
    document.getElementById('arriveDistText').textContent = dist + ' KM';
    document.getElementById('arriveExpFuelText').textContent = expFuel + ' L';
    preview.style.display = 'block';
  } else {
    preview.style.display = 'none';
  }
}

async function recordArrival(){
  if(!arriveId || isRecordingArrival) return;
  const confirmBtn = document.getElementById('arriveConfirmBtn');
  const ending_odo = document.getElementById('arriveEndingOdo')?.value || '';
  const now = new Date();
  const pad2 = n => String(n).padStart(2, '0');
  const arrival_time = pad2(now.getHours()) + ':' + pad2(now.getMinutes()) + ':' + pad2(now.getSeconds());
  const body = {dispatch_id:arriveId, action:'arrive', arrival_time, ending_odo:ending_odo, csrf_token:CSRF_TOKEN};
  if(arriveStartOdo !== null && arriveStartOdo !== undefined){
    body.start_edo = arriveStartOdo;
  }

  isRecordingArrival = true;
  if(confirmBtn){
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Confirming Arrival...';
  }

  try{
    const r=await fetch(API+'update_dispatch',{
      method:'POST',
      headers:apiPostHeaders(),
      body:JSON.stringify(body)
    });
    const j=await parseApiResponse(r);
    const confirmed = isResponseSuccess(r, j);

    if(confirmed){
      closeModal('arriveModal');
      arriveId=null;
      arriveStartOdo=null;

      // Ensure the trip list/card is refreshed only once after success
      await loadQueue({force:true});

      let msg = 'Trip arrival recorded successfully.';
      if(j && j.total_passengers !== undefined && j.duration_minutes !== undefined){
        msg += '\nPassengers: ' + j.total_passengers + ' • Duration: ' + j.duration_minutes + ' min';
      }
      if(j && j.fuel_data && j.fuel_data.distance_traveled !== null && j.fuel_data.distance_traveled !== undefined){
        msg += '\nDistance: ' + j.fuel_data.distance_traveled + ' KM • Expected Fuel: ' + (j.fuel_data.expected_fuel ?? '-') + ' L';
      }
      showAlert('success', 'Arrival Recorded', msg);
    }
    else {
      console.error('recordArrival failed:', r ? r.status : 'no-response', j);
      const errMsg = extractErrorMessage(r, j, 'Unable to record arrival. Please try again.');
      showAlert('error', 'Arrival Failed', errMsg);
    }
  }catch(e){
    console.error('recordArrival error:', e);
    showAlert('error', 'Arrival Failed', 'Network connection issue. Please check your connection and try again.');
  }finally{
    isRecordingArrival = false;
    if(confirmBtn){
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = '<i class="bi bi-check-circle"></i> Confirm Arrival';
    }
  }
}

let editFuelTrip = null;

function openEditFuelModal(id){
  const v = tripDetailCache[id];
  if(!v) return;
  editFuelTrip = v;
  document.getElementById('editFuelTripId').value = v.id;
  document.getElementById('editFuelLogId').value = v.fuel_log_id || '';
  document.getElementById('editFuelTripNo').textContent = v.trip_number || v.id;
  document.getElementById('editFuelRoute').textContent = routeArrow(v.route_name);
  document.getElementById('editFuelBus').textContent = v.bus_id + (v.plate_number ? ' (' + v.plate_number + ')' : '');
  document.getElementById('editFuelDriver').textContent = v.driver_name || v.driver_id || '-';
  
  document.getElementById('editStartingOdo').value = (v.starting_odo !== null && v.starting_odo !== undefined) ? v.starting_odo : '';
  document.getElementById('editEndingOdo').value = (v.ending_odo !== null && v.ending_odo !== undefined) ? v.ending_odo : '';
  document.getElementById('editKpl').value = v.kpl || 9.0;
  document.getElementById('editActualLiters').value = v.fuel_liters || '';
  document.getElementById('editFuelReason').value = '';
  
  const price = Number(v.price_per_liter || currentFuelPrice || 65.55);
  document.getElementById('editFuelPriceText').textContent = '₱' + price.toFixed(2) + '/L';
  
  onEditFuelCalc();
  showModal('editFuelModal');
}

function onEditFuelCalc(){
  const start = parseFloat(document.getElementById('editStartingOdo').value);
  const end = parseFloat(document.getElementById('editEndingOdo').value);
  const kpl = parseFloat(document.getElementById('editKpl').value) || 9.0;
  const liters = parseFloat(document.getElementById('editActualLiters').value) || 0;
  const price = editFuelTrip && editFuelTrip.price_per_liter ? Number(editFuelTrip.price_per_liter) : (currentFuelPrice || 65.55);

  if(!isNaN(start) && !isNaN(end) && end >= start){
    const dist = (end - start).toFixed(1);
    const expFuel = kpl > 0 ? (dist / kpl).toFixed(2) : '-';
    document.getElementById('calcDistanceText').textContent = dist + ' KM';
    document.getElementById('calcExpectedFuelText').textContent = expFuel + ' L';
  } else if(!isNaN(start) && !isNaN(end) && end < start){
    document.getElementById('calcDistanceText').textContent = 'Ending ODO must be ≥ Starting ODO';
    document.getElementById('calcExpectedFuelText').textContent = '-';
  } else {
    document.getElementById('calcDistanceText').textContent = '-';
    document.getElementById('calcExpectedFuelText').textContent = '-';
  }

  if(liters > 0){
    document.getElementById('calcFuelCostText').textContent = '₱' + (liters * price).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
  } else {
    document.getElementById('calcFuelCostText').textContent = '-';
  }
}

async function saveTripFuelEdit(){
  if(isSavingFuel) return;
  const trip_id = document.getElementById('editFuelTripId').value;
  const fuel_log_id = document.getElementById('editFuelLogId').value;
  const starting_odo = document.getElementById('editStartingOdo').value;
  const ending_odo = document.getElementById('editEndingOdo').value;
  const kpl = document.getElementById('editKpl').value;
  const actual_liters = document.getElementById('editActualLiters').value;
  const reason = document.getElementById('editFuelReason').value.trim();
  const saveBtn = document.getElementById('saveFuelBtn');

  if(ending_odo !== '' && starting_odo !== '' && parseFloat(ending_odo) < parseFloat(starting_odo)){
    showAlert('error', 'Invalid Odometer', 'Ending ODO cannot be less than Starting ODO.');
    return;
  }
  if(reason === ''){
    showAlert('error', 'Reason Required', 'Please enter a reason for editing this fuel record.');
    return;
  }

  isSavingFuel = true;
  if(saveBtn){
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving Record...';
  }

  try{
    const r = await fetch(API + 'update_trip_fuel', {
      method: 'POST',
      headers: apiPostHeaders(),
      body: JSON.stringify({
        trip_id,
        fuel_log_id,
        starting_odo,
        ending_odo,
        kpl,
        actual_liters,
        reason,
        csrf_token: CSRF_TOKEN
      })
    });
    const j = await parseApiResponse(r);
    const confirmed = isResponseSuccess(r, j);

    if(confirmed){
      closeModal('editFuelModal');
      if(tripDetailCache[trip_id] && j && j.data){
        const d = j.data;
        Object.assign(tripDetailCache[trip_id], {
          starting_odo: d.starting_odo,
          ending_odo: d.ending_odo,
          kpl: d.kpl,
          distance_traveled: d.distance_traveled,
          expected_fuel: d.expected_fuel,
          fuel_liters: d.actual_liters,
          fuel_cost: d.fuel_cost
        });
      }

      // Ensure the trip list/card is refreshed only once after success
      await loadQueue({force:true});

      showAlert('success', 'Record Saved', 'Fuel & odometer record saved successfully.');
    } else {
      const errMsg = extractErrorMessage(r, j, 'Unable to save fuel record. Please try again.');
      showAlert('error', 'Save Failed', errMsg);
    }
  }catch(e){
    console.error('saveTripFuelEdit error:', e);
    showAlert('error', 'Save Failed', 'Network connection issue. Please check your connection and try again.');
  }finally{
    isSavingFuel = false;
    if(saveBtn){
      saveBtn.disabled = false;
      saveBtn.innerHTML = '<i class="bi bi-check-circle"></i> Save Fuel Record';
    }
  }
}

async function quickAction(id,action){
  try{
    const r=await fetch(API+'update_dispatch',{method:'POST',headers:apiPostHeaders(),body:JSON.stringify({dispatch_id:id,action,csrf_token:CSRF_TOKEN})});
    const j=await parseApiResponse(r);
    if(isResponseSuccess(r, j)){
      await loadQueue({force:true});
      const actionName = action === 'cancel' ? 'Trip Cancelled' : 'Action Completed';
      const actionMsg = action === 'cancel' ? 'The trip has been cancelled successfully.' : 'Action completed successfully.';
      showAlert('success', actionName, actionMsg);
    } else {
      const errMsg = extractErrorMessage(r, j, 'Action could not be completed. Please try again.');
      showAlert('error', 'Action Failed', errMsg);
    }
  }catch(e){
    console.error('quickAction error:', e);
    showAlert('error', 'Action Failed', 'Network error. Please try again.');
  }
}

function printReport(){window.open(API+'print_report?date='+getPHTDateStr(),'_blank','width=900,height=700');}
function logout(){window.location.href='/dispatcher/?d=<?php echo $_SESSION['dashboard_secret'] ?? ''; ?>&route=logout';}

function onSearch(){
  queueState.search = document.getElementById('searchInput').value.trim();
  renderQueue();
}

// ── History View ──
let historyPage = 1;
let historyStatusFilter = '';

async function loadHistory(){
  const el=document.getElementById('dispatchList');
  el.innerHTML='<div class="loading">Loading history...</div>';
  let params='page='+historyPage+'&limit=20';
  const df=document.getElementById('histDateFrom').value;
  const dt=document.getElementById('histDateTo').value;
  if(df) params+='&date_from='+encodeURIComponent(df);
  if(dt) params+='&date_to='+encodeURIComponent(dt);
  if(historyStatusFilter) params+='&status='+encodeURIComponent(historyStatusFilter);
  const q=document.getElementById('searchInput').value.trim();
  if(q) params+='&bus_id='+encodeURIComponent(q);
  try{
    const r=await fetch(API+'get_dispatch_history?'+params,{headers:apiHeaders()});
    const j=await parseApiResponse(r);

    if(r.status===401){
      el.innerHTML='<div class="empty-state"><i class="bi bi-shield-lock" style="color:#f57c00;"></i><p>Session expired. Please log in again.</p></div>';
      return;
    }
    if(!j || !j.success){
      const msg = extractErrorMessage(r, j, 'Failed to load history. Please try again.');
      el.innerHTML=`<div class="empty-state"><i class="bi bi-exclamation-triangle" style="color:#d32f2f;"></i><p>${esc(msg)}</p><button class="btn-primary-app" style="max-width:140px;margin:10px auto;padding:6px 12px;font-size:12px;" onclick="loadHistory()">Retry</button></div>`;
      return;
    }
    const d=j.data||[];
    el.innerHTML='';
    if(!d.length){el.innerHTML='<div class="empty-state"><i class="bi bi-clock-history"></i><p>No trips found in this date range</p></div>';return;}

    // Deduplicate history trips by id
    const seen = new Set();
    const uniqueHistory = [];
    for(const item of d){
      const tripKey = item.id || item.trip_number;
      if(tripKey && !seen.has(tripKey)){
        seen.add(tripKey);
        uniqueHistory.push(item);
      }
    }

    const totalLabel=(j.total ?? uniqueHistory.length)+' trip'+((j.total ?? uniqueHistory.length)!==1?'s':'');
    document.getElementById('histResultBadge').textContent=totalLabel;
    el.innerHTML=uniqueHistory.map(v=>cardHTML(v,true)).join('');
    document.getElementById('paginationControls').style.display='flex';
    document.getElementById('pageInfo').textContent='Page '+j.page+' of '+j.pages;
    document.getElementById('prevPageBtn').disabled=j.page<=1;
    document.getElementById('nextPageBtn').disabled=j.page>=j.pages;
  }catch(e){
    console.error('loadHistory error:', e);
    el.innerHTML=`<div class="empty-state"><i class="bi bi-wifi-off" style="color:#d32f2f;"></i><p>Network error loading history: ${esc(e.message||'Server unreachable')}</p><button class="btn-primary-app" style="max-width:140px;margin:10px auto;padding:6px 12px;font-size:12px;" onclick="loadHistory()">Retry</button></div>`;
  }
}

function changePage(dir){
  historyPage+=dir;
  if(historyPage<1) historyPage=1;
  loadHistory();
}

// ── Bus Problem Reports ──
let problemStatusFilter = '';
let problemReportsCache = [];
let resolveProblemId = null;

function toggleProblemReports(){
  const panel = document.getElementById('problemReportsPanel');
  const btn = document.getElementById('bprBellBtn');
  const open = panel.hidden;
  if(open){
    panel.hidden = false;
    btn.setAttribute('aria-expanded','true');
    btn.classList.add('active');
    loadProblemReports();
  } else {
    closeProblemFlyout();
  }
}

function closeProblemFlyout(){
  const panel = document.getElementById('problemReportsPanel');
  const btn = document.getElementById('bprBellBtn');
  if(panel) panel.hidden = true;
  if(btn){ btn.setAttribute('aria-expanded','false'); btn.classList.remove('active'); }
}

function updateProblemBadge(reports){
  const el = document.getElementById('bprBellCount');
  const fc = document.getElementById('bprFlyoutCount');
  if(!el) return;
  const active = (reports || []).filter(r => r.status === 'waiting' || r.status === 'acknowledged').length;
  el.hidden = active === 0;
  el.textContent = active > 99 ? '99+' : String(active);
  if(fc){ fc.hidden = active === 0; fc.textContent = String(active); }
}

async function refreshProblemBadge(){
  try{
    const r = await fetch(API + 'get_bus_problem_reports', {headers: apiHeaders()});
    if(r.status !== 200) return;
    const j = await parseApiResponse(r);
    if(j && j.success) updateProblemBadge(j.reports || []);
  }catch(_){}
}

(function initProblemReportsFlyout(){
  document.addEventListener('click', e => {
    const panel = document.getElementById('problemReportsPanel');
    if(!panel || panel.hidden) return;
    const btn = document.getElementById('bprBellBtn');
    if(panel.contains(e.target)) return;
    if(btn && (e.target === btn || btn.contains(e.target))) return;
    closeProblemFlyout();
  });
  document.addEventListener('keydown', e => { if(e.key === 'Escape') closeProblemFlyout(); });
})();

function setProblemStatusFilter(chip, status){
  problemStatusFilter = status;
  document.querySelectorAll('#problemFilterRow .filter-chip').forEach(c=>c.classList.remove('active'));
  chip.classList.add('active');
  loadProblemReports();
}

function fmtReportTime(v){
  if(!v) return '-';
  return String(v).replace('T',' ').substring(0,16);
}

function problemReportCard(r){
  const status = esc(r.status || '');
  const badge = '<span class="status-badge status-'+status+'">'+status.toUpperCase()+'</span>';
  const tripLabel = r.trip_no ? ('Trip #'+esc(r.trip_no)) : (r.trip_id ? ('Trip #'+esc(r.trip_id)) : esc(t('problems.no_trip','No trip')));
  const route = r.route_name ? ' &bull; '+esc(r.route_name) : '';
  const driverName = r.driver_name ? esc(r.driver_name)+' <span style="color:var(--text-muted);">('+esc(r.driver_id||'')+')</span>' : esc(r.driver_id || '-');
  const busLabel = r.plate_number ? esc(r.plate_number)+' <span style="color:var(--text-muted);">('+esc(r.bus_id||'')+')</span>' : esc(r.bus_id || '-');

  let html = '<div class="dispatch-card">';
  html += '<div style="display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap;">'
        + '<strong><i class="bi bi-exclamation-triangle"></i> #'+esc(r.id)+' &bull; '+tripLabel+route+'</strong>'+badge+'</div>';
  html += '<div class="bpr-meta">'
        + '<span><i class="bi bi-person"></i> '+driverName+'</span>'
        + '<span><i class="bi bi-bus-front"></i> '+busLabel+'</span>'
        + '<span><i class="bi bi-people"></i> '+esc(r.passenger_count ?? 0)+' '+esc(t('problems.pax','pax'))+'</span>'
        + '</div>';
  html += '<div style="margin-top:6px;font-size:12px;"><strong>'+esc(r.problem_type || '-')+'</strong></div>';
  // problem_detail: shown for 'Other' and any additional context; tolerated when null/empty (backward compatible)
  if(r.problem_detail && String(r.problem_detail).trim() !== ''){
    html += '<div class="bpr-detail"><i class="bi bi-chat-left-text"></i> '+esc(r.problem_detail)+'</div>';
  }
  if(r.remarks && String(r.remarks).trim() !== ''){
    html += '<div class="bpr-detail"><i class="bi bi-card-text"></i> '+esc(r.remarks)+'</div>';
  }
  const hasLat = r.latitude !== null && r.latitude !== undefined && r.latitude !== '';
  const hasLng = r.longitude !== null && r.longitude !== undefined && r.longitude !== '';
  if(hasLat || hasLng || r.location_status){
    const coords = (hasLat && hasLng) ? (esc(r.latitude)+', '+esc(r.longitude)) : esc(t('problems.location_unavailable','Location unavailable'));
    const locStatus = r.location_status ? ' &bull; '+esc(r.location_status) : '';
    html += '<div class="bpr-meta"><span><i class="bi bi-geo"></i> '+coords+locStatus+'</span></div>';
  }
  html += '<div class="bpr-meta"><span><i class="bi bi-clock"></i> '+fmtReportTime(r.created_at)
        + (r.updated_at && r.updated_at !== r.created_at ? ' &bull; '+esc(t('problems.updated','Updated'))+' '+fmtReportTime(r.updated_at) : '')
        + '</span></div>';
  const hasReplacement = r.replacement_bus_id && String(r.replacement_bus_id).trim() !== '';
  if(hasReplacement){
    html += '<div class="bpr-detail" style="border-color:#5b8f4e;background:#f2f9f4;">'
          + '<i class="bi bi-arrow-left-right"></i> <strong>'+esc(t('problems.replacement_label','Replacement Bus'))+': '+esc(r.replacement_bus_id)+'</strong>'
          + (r.replacement_driver_id ? ' &bull; '+esc(t('problems.driver_label','Driver'))+': '+esc(r.replacement_driver_id) : '')
          + (r.replacement_assigned_at ? '<div style="font-size:11px;color:var(--text-muted);margin-top:2px;">'+esc(t('problems.assigned_at','Assigned'))+' '+fmtReportTime(r.replacement_assigned_at)+'</div>' : '')
          + '</div>';
  }
  html += '<div class="bpr-actions">';
  if(r.status === 'waiting'){
    html += '<button type="button" class="btn-action bpr-btn-primary" onclick="acknowledgeProblemReport('+parseInt(r.id,10)+')"><i class="bi bi-check2-circle"></i> '+esc(t('problems.acknowledge','Acknowledge'))+'</button>';
  }
  if(r.status === 'acknowledged'){
    html += '<button type="button" class="btn-action bpr-btn-primary" onclick="openReplacementBusModal('+parseInt(r.id,10)+')"><i class="bi bi-arrow-left-right"></i> '
          + esc(hasReplacement ? t('problems.change_replacement','Change Replacement Bus') : t('problems.assign_replacement','Assign Replacement Bus'))+'</button>';
  }
  if(r.status === 'waiting' || r.status === 'acknowledged'){
    html += '<button type="button" class="btn-action bpr-btn-secondary" onclick="openResolveProblemModal('+parseInt(r.id,10)+')"><i class="bi bi-check-circle"></i> '+esc(t('problems.resolve_action','Resolve'))+'</button>';
  }
  html += '</div></div>';
  return html;
}

async function loadProblemReports(){
  const el = document.getElementById('problemReportsList');
  if(!el) return;
  el.innerHTML = '<div class="loading">'+esc(t('problems.loading','Loading problem reports...'))+'</div>';
  let params = '';
  if(problemStatusFilter) params += 'status=' + encodeURIComponent(problemStatusFilter);
  const q = (document.getElementById('problemSearchInput').value || '').trim();
  if(q) params += (params ? '&' : '') + 'search=' + encodeURIComponent(q);
  try{
    const r = await fetch(API + 'get_bus_problem_reports' + (params ? '?'+params : ''), {headers: apiHeaders()});
    const j = await parseApiResponse(r);
    if(r.status === 401){
      el.innerHTML = '<div class="empty-state"><i class="bi bi-shield-lock" style="color:#f57c00;"></i><p>Session expired. Please log in again.</p></div>';
      return;
    }
    if(!j || !j.success){
      const msg = extractErrorMessage(r, j, 'Failed to load problem reports. Please try again.');
      el.innerHTML = '<div class="empty-state"><i class="bi bi-exclamation-triangle" style="color:#d32f2f;"></i><p>'+esc(msg)+'</p><button class="btn-primary-app" style="max-width:140px;margin:10px auto;padding:6px 12px;font-size:12px;" onclick="loadProblemReports()">'+esc(t('common.retry','Retry'))+'</button></div>';
      return;
    }
    const reports = j.reports || [];
    problemReportsCache = reports;
    updateProblemBadge(reports);
    if(!reports.length){
      el.innerHTML = '<div class="empty-state"><i class="bi bi-check-circle"></i><p>'+esc(t('problems.empty','No problem reports found'))+'</p></div>';
      return;
    }
    el.innerHTML = reports.map(problemReportCard).join('');
  }catch(e){
    console.error('loadProblemReports error:', e);
    el.innerHTML = '<div class="empty-state"><i class="bi bi-wifi-off" style="color:#d32f2f;"></i><p>'+esc(t('problems.network_error','Network error loading problem reports')+': '+(e.message||'Server unreachable'))+'</p><button class="btn-primary-app" style="max-width:140px;margin:10px auto;padding:6px 12px;font-size:12px;" onclick="loadProblemReports()">'+esc(t('common.retry','Retry'))+'</button></div>';
  }
}

function acknowledgeProblemReport(id){
  showConfirm(t('problems.ack_title','Acknowledge Report'), t('problems.ack_message','Mark this problem report as acknowledged?'), async function(){
    try{
      const r = await fetch(API + 'acknowledge_bus_problem_report', {
        method: 'POST',
        headers: apiPostHeaders(),
        body: JSON.stringify({report_id: id, csrf_token: CSRF_TOKEN})
      });
      const j = await parseApiResponse(r);
      if(!isResponseSuccess(r, j)){
        showAlert('error', t('problems.ack_failed','Acknowledge Failed'), extractErrorMessage(r, j, 'Failed to acknowledge the report.'));
        return;
      }
      showAlert('success', t('problems.ack_done','Report Acknowledged'), t('problems.ack_done_msg','The report status is now acknowledged.'));
      loadProblemReports();
    }catch(e){
      showAlert('error', t('problems.ack_failed','Acknowledge Failed'), t('problems.network','Network error')+': '+(e.message||'Server unreachable'));
    }
  });
}

function openResolveProblemModal(id){
  resolveProblemId = id;
  const rep = problemReportsCache.find(x => parseInt(x.id,10) === parseInt(id,10));
  document.getElementById('resolveReplacementRequired').checked = false;
  let summary = '<strong>#'+esc(id)+'</strong>';
  if(rep){
    summary = '<strong>#'+esc(rep.id)+' &bull; '+(rep.trip_no ? 'Trip #'+esc(rep.trip_no) : (rep.trip_id ? 'Trip #'+esc(rep.trip_id) : t('problems.no_trip','No trip')))+'</strong>';
    summary += '<div style="margin-top:4px;">'+esc(rep.problem_type || '-')
             + (rep.problem_detail && String(rep.problem_detail).trim() !== '' ? ': '+esc(rep.problem_detail) : '')
             + '</div>';
  }
  document.getElementById('resolveProblemSummary').innerHTML = summary;
  document.getElementById('resolveProblemConfirmBtn').disabled = false;
  showModal('resolveProblemModal');
}

async function resolveProblemReport(){
  const btn = document.getElementById('resolveProblemConfirmBtn');
  const replacement = document.getElementById('resolveReplacementRequired').checked ? 1 : 0;
  btn.disabled = true;
  try{
    const r = await fetch(API + 'resolve_bus_problem_report', {
      method: 'POST',
      headers: apiPostHeaders(),
      body: JSON.stringify({report_id: resolveProblemId, replacement_required: replacement, csrf_token: CSRF_TOKEN})
    });
    const j = await parseApiResponse(r);
    if(!isResponseSuccess(r, j)){
      btn.disabled = false;
      showAlert('error', t('problems.resolve_failed','Resolve Failed'), extractErrorMessage(r, j, 'Failed to resolve the report.'));
      return;
    }
    closeModal('resolveProblemModal');
    showAlert('success', t('problems.resolve_done','Report Resolved'),
      replacement ? t('problems.resolve_done_replacement','The report is resolved and the trip was flagged for bus replacement.')
                  : t('problems.resolve_done_msg','The report status is now resolved.'));
    loadProblemReports();
  }catch(e){
    btn.disabled = false;
    showAlert('error', t('problems.resolve_failed','Resolve Failed'), t('problems.network','Network error')+': '+(e.message||'Server unreachable'));
  }
}

// ── Replacement Bus Pickup ──
let replacementReportId = null;
let replacementSelectedBus = null;
let replacementCandidates = [];

function fmtTimeOnly(v){
  if(!v) return '-';
  const s = String(v);
  const m = s.match(/(\d{1,2}):(\d{2})(?::(\d{2}))?/);
  return m ? String(m[1]).padStart(2,'0')+':'+m[2] : s.replace('T',' ').substring(0,16);
}

function openReplacementBusModal(reportId){
  replacementReportId = reportId;
  replacementSelectedBus = null;
  const rep = problemReportsCache.find(x => parseInt(x.id,10) === parseInt(reportId,10));
  let summary = '<strong>#'+esc(reportId)+'</strong>';
  if(rep){
    summary = '<strong>#'+esc(rep.id)+' &bull; '+(rep.trip_no ? 'Trip #'+esc(rep.trip_no) : (rep.trip_id ? 'Trip #'+esc(rep.trip_id) : t('problems.no_trip','No trip')))+' &bull; '+esc(t('problems.affected_bus','Affected bus'))+': '+esc(rep.bus_id||'-')+(rep.plate_number ? ' ('+esc(rep.plate_number)+')' : '')+'</strong>';
    summary += '<div style="margin-top:4px;">'+esc(rep.problem_type || '-')
             + (rep.problem_detail && String(rep.problem_detail).trim() !== '' ? ': '+esc(rep.problem_detail) : '')
             + '</div>';
    const meta = [];
    if(rep.driver_name || rep.driver_id){
      meta.push('<span><i class="bi bi-person"></i> '+esc(t('problems.driver_label','Driver'))+': '+(rep.driver_name ? esc(rep.driver_name) : esc(rep.driver_id))+(rep.driver_name && rep.driver_id && String(rep.driver_id) !== String(rep.driver_name) ? ' ('+esc(rep.driver_id)+')' : '')+'</span>');
    }
    if(rep.route_name) meta.push('<span><i class="bi bi-signpost"></i> '+esc(t('problems.route','Route'))+': '+esc(rep.route_name)+'</span>');
    if(rep.departure_time) meta.push('<span><i class="bi bi-clock"></i> '+esc(t('problems.departure','Departure'))+': <b>'+fmtTimeOnly(rep.departure_time)+'</b></span>');
    meta.push('<span><i class="bi bi-people"></i> '+esc(rep.passenger_count ?? 0)+' '+esc(t('problems.pax','pax'))+'</span>');
    summary += '<div class="bpr-meta" style="margin-top:5px;">'+meta.join('')+'</div>';
    if(rep.replacement_bus_id && String(rep.replacement_bus_id).trim() !== ''){
      summary += '<div class="bpr-detail" style="border-color:#5b8f4e;background:#f2f9f4;margin-top:5px;"><i class="bi bi-arrow-left-right"></i>'
              + ' <strong>'+esc(t('problems.replacement_label','Replacement Bus'))+': '+esc(rep.replacement_bus_id)+'</strong>'
              + (rep.replacement_driver_id ? ' &bull; '+esc(t('problems.driver_label','Driver'))+': '+esc(rep.replacement_driver_id) : '')
              + '</div>';
    }
  }
  document.getElementById('replacementReportSummary').innerHTML = summary;
  document.getElementById('replacementAssignBtn').disabled = true;
  document.getElementById('replacementSelectedLabel').textContent = t('problems.select_bus_hint','Select a bus to continue');
  document.getElementById('replacementBusList').innerHTML = '<div class="loading">'+esc(t('problems.loading_buses','Loading available buses...'))+'</div>';
  showModal('replacementBusModal');
  loadReplacementBuses();
}

async function loadReplacementBuses(){
  const el = document.getElementById('replacementBusList');
  if(!el) return;
  try{
    const r = await fetch(API + 'get_replacement_bus_candidates?report_id=' + encodeURIComponent(replacementReportId), {headers: apiHeaders()});
    const j = await parseApiResponse(r);
    if(r.status === 401){
      el.innerHTML = '<div class="empty-state"><i class="bi bi-shield-lock" style="color:#f57c00;"></i><p>Session expired. Please log in again.</p></div>';
      return;
    }
    if(!j || !j.success){
      const msg = extractErrorMessage(r, j, 'Failed to load available buses.');
      el.innerHTML = '<div class="empty-state"><i class="bi bi-exclamation-triangle" style="color:#d32f2f;"></i><p>'+esc(msg)+'</p><button class="btn-primary-app" style="max-width:140px;margin:10px auto;padding:6px 12px;font-size:12px;" onclick="loadReplacementBuses()">'+esc(t('common.retry','Retry'))+'</button></div>';
      return;
    }
    const buses = j.data || [];
    replacementCandidates = buses;
    replacementSelectedBus = null;
    document.getElementById('replacementAssignBtn').disabled = true;
    document.getElementById('replacementSelectedLabel').textContent = t('problems.select_bus_hint','Select a bus to continue');
    if(!buses.length){
      el.innerHTML = '<div class="empty-state"><i class="bi bi-bus-front"></i><p>'+esc(t('problems.no_candidates','No available replacement buses right now'))+'</p></div>';
      return;
    }
    el.innerHTML = buses.map(replacementBusRow).join('');
  }catch(e){
    console.error('loadReplacementBuses error:', e);
    el.innerHTML = '<div class="empty-state"><i class="bi bi-wifi-off" style="color:#d32f2f;"></i><p>'+esc(t('problems.network_error','Network error loading problem reports')+': '+(e.message||'Server unreachable'))+'</p><button class="btn-primary-app" style="max-width:140px;margin:10px auto;padding:6px 12px;font-size:12px;" onclick="loadReplacementBuses()">'+esc(t('common.retry','Retry'))+'</button></div>';
  }
}

function replacementBusRow(b){
  const loc = (b.location_status === 'located' && b.latitude !== null && b.latitude !== undefined && b.longitude !== null && b.longitude !== undefined)
    ? (esc(b.latitude)+', '+esc(b.longitude))
    : esc(t('problems.location_unavailable','Location unavailable'));
  const capacity = (b.seats !== null && b.seats !== undefined)
    ? (esc(b.seats)+' '+esc(t('problems.seats','seats')))
    : esc(t('problems.capacity_unknown','Capacity unknown'));
  const driver = b.driver_name ? esc(b.driver_name) : (b.driver_id ? esc(b.driver_id) : esc(t('problems.no_driver','No driver assigned')));
  let tripInfo = '';
  if(b.next_trip && b.next_trip.trip_no){
    tripInfo = '<span><i class="bi bi-clipboard-check"></i> '+esc(t('problems.next_trip','Next trip'))+': #'+esc(b.next_trip.trip_no)
             + (b.next_trip.departure_time ? ' &bull; '+fmtTimeOnly(b.next_trip.departure_time) : '')
             + (b.next_trip.status ? ' &bull; '+esc(b.next_trip.status) : '')+'</span>';
  } else {
    tripInfo = '<span><i class="bi bi-check2-circle"></i> '+esc(t('problems.no_trips_today','No trips scheduled today'))+'</span>';
  }
  return '<div class="dispatch-card bpr-candidate" id="cand_'+esc(b.bus_id)+'" role="button" tabindex="0" onclick="selectReplacementBus(\''+esc(b.bus_id)+'\')">'
    + '<div style="display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap;">'
    + '<strong><i class="bi bi-bus-front"></i> '+esc(b.bus_id)+' &bull; '+esc(b.plate_number)+'</strong>'
    + '<span class="status-badge status-available">'+esc(b.status)+'</span></div>'
    + '<div class="bpr-meta">'
    + '<span><i class="bi bi-person"></i> '+driver+'</span>'
    + (b.route_name ? '<span><i class="bi bi-signpost"></i> '+esc(b.route_name)+'</span>' : '')
    + '<span><i class="bi bi-people"></i> '+capacity+'</span>'
    + '</div>'
    + '<div class="bpr-meta">'
    + tripInfo
    + '<span><i class="bi bi-geo"></i> '+loc+'</span>'
    + '</div></div>';
}

function selectReplacementBus(busId){
  replacementSelectedBus = replacementCandidates.find(b => b.bus_id === busId) || null;
  document.querySelectorAll('#replacementBusList .bpr-candidate').forEach(el => { el.style.borderColor = ''; el.style.boxShadow = ''; });
  const el = document.getElementById('cand_'+busId);
  if(el){ el.style.borderColor = 'var(--primary)'; el.style.boxShadow = '0 2px 10px rgba(71,97,59,.3)'; }
  document.getElementById('replacementAssignBtn').disabled = !replacementSelectedBus;
  const label = document.getElementById('replacementSelectedLabel');
  if(label){
    label.textContent = replacementSelectedBus
      ? t('problems.selected_bus','Selected')+': '+replacementSelectedBus.bus_id+(replacementSelectedBus.plate_number ? ' • '+replacementSelectedBus.plate_number : '')
      : t('problems.select_bus_hint','Select a bus to continue');
  }
  const driverRow = document.getElementById('replacementDriverRow');
  const driverSel = document.getElementById('replacementDriverSelect');
  if(!replacementSelectedBus){
    if(driverRow) driverRow.style.display = 'none';
    return;
  }
  const drivers = (replacementSelectedBus.drivers && replacementSelectedBus.drivers.length) ? replacementSelectedBus.drivers : [];
  if(driverSel){
    if(drivers.length > 1){
      let opts = '<option value="">'+esc(t('problems.driver_auto','— Use assigned driver ('+esc(replacementSelectedBus.driver_id)+') —'))+'</option>';
      drivers.forEach(d => {
        const dname = d.driver_name ? (d.driver_name+' ('+d.driver_id+')') : d.driver_id;
        opts += '<option value="'+esc(d.driver_id)+'"'+(String(d.driver_id) === String(replacementSelectedBus.driver_id) ? ' selected' : '')+'>'+esc(dname)+'</option>';
      });
      driverSel.innerHTML = opts;
      if(driverRow) driverRow.style.display = 'block';
    } else if(drivers.length === 1){
      driverSel.innerHTML = '<option value="'+esc(drivers[0].driver_id)+'" selected>'+esc((drivers[0].driver_name ? (drivers[0].driver_name+' ('+drivers[0].driver_id+')') : drivers[0].driver_id))+ '</option>';
      if(driverRow) driverRow.style.display = 'none';
    } else {
      driverSel.innerHTML = '';
      if(driverRow) driverRow.style.display = 'none';
    }
  }
}

async function assignReplacementBus(){
  if(!replacementSelectedBus) return;
  const btn = document.getElementById('replacementAssignBtn');
  const selected = replacementSelectedBus;
  const driverSel = document.getElementById('replacementDriverSelect');
  const driverId = driverSel ? driverSel.value.trim() : '';
  btn.disabled = true;
  try{
    const r = await fetch(API + 'assign_replacement_bus', {
      method: 'POST',
      headers: apiPostHeaders(),
      body: JSON.stringify({
        report_id: replacementReportId,
        bus_id: selected.bus_id,
        driver_id: driverId,
        csrf_token: CSRF_TOKEN
      })
    });
    const j = await parseApiResponse(r);
    if(!isResponseSuccess(r, j)){
      btn.disabled = false;
      showAlert('error', t('problems.assign_failed','Assignment Failed'), extractErrorMessage(r, j, 'Failed to assign the replacement bus.'));
      return;
    }
    closeModal('replacementBusModal');
    if(driverId){
      showAlert('success', t('problems.assign_done','Replacement Bus Assigned'), t('problems.assign_done_msg', selected.bus_id+' is now the replacement bus, driven by '+driverId+'.'));
    } else {
      showAlert('success', t('problems.assign_done','Replacement Bus Assigned'), t('problems.assign_done_msg', selected.bus_id+' is now the replacement bus.'));
    }
    loadProblemReports();
  }catch(e){
    btn.disabled = false;
    showAlert('error', t('problems.assign_failed','Assignment Failed'), t('problems.network','Network error')+': '+(e.message||'Server unreachable'));
  }
}

initQueue();
refreshProblemBadge();
setInterval(refreshProblemBadge, 60000);

if('serviceWorker' in navigator){navigator.serviceWorker.register('/dispatcher/sw.js');}
</script>

<div class="install-banner" id="installBanner">
  <button class="close-btn" onclick="this.parentElement.style.display='none';if(typeof updateBottomBarOffset==='function')updateBottomBarOffset();">&times;</button>
  <div class="text" id="installText"><strong>R-Connect Dispatcher</strong><br>Install for quick access</div>
  <button class="install-btn" id="installBtn">Install App</button>
</div>

<script>
!function(){
  const isIOS=/iPad|iPhone|iPod/.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
  let dP=null;
  const b=document.getElementById('installBanner'),ib=document.getElementById('installBtn');
  if(window.matchMedia('(display-mode: standalone)').matches){if(b)b.style.display='none';if(typeof updateBottomBarOffset==='function')updateBottomBarOffset();return;}
  window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();dP=e;});
  ib?.addEventListener('click',async()=>{
    if(dP){dP.prompt();await dP.userChoice;dP=null;if(b)b.style.display='none';if(typeof updateBottomBarOffset==='function')updateBottomBarOffset();return;}
    if(isIOS) showAlert('success', 'Install App', 'To install:\n\n1. Tap the Share icon at the bottom of Safari\n2. Scroll down and tap "Add to Home Screen"\n3. Tap "Add" in the top right');
    else showAlert('success', 'Install App', 'To install:\n\nOpen this page in Chrome or Edge and look for "Install" in the browser menu.');
  });
  window.addEventListener('appinstalled',()=>{if(b)b.style.display='none';});
}();
</script>

<div id="customAlertOverlay" class="custom-alert-overlay">
  <div class="custom-alert-card">
    <div class="custom-alert-header">
      <div class="custom-alert-icon"></div>
      <div class="custom-alert-title"></div>
    </div>
    <div class="custom-alert-body">
      <div class="custom-alert-message"></div>
      <div class="custom-alert-actions">
        <button class="custom-alert-btn"></button>
        <button class="custom-alert-btn-cancel" style="display:none;"></button>
      </div>
    </div>
  </div>
</div>
<script src="/public/login/custom-alert.js"></script>

<?php endif; ?>
</body>
</html>
