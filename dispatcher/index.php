<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/utils/i18n.php';

// âœ… MOBILE-ONLY GATE  [RE-ENABLED â€” dispatcher is mobile only]
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

// âœ… OBFUSCATED URL ROUTER
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

// If no hash matched and request is not the root index, send the visitor to
// the dispatcher root: a rotated, expired, or bookmarked hash recovers onto
// the current obfuscated route instead of dead-ending on a 404 page.
$isRoot = ($relativePath === '' || $relativePath === 'index.php');
if (!$isRoot && $matchedHash === 'none') {
    dispatcher_log("ROUTE_NOT_MATCHED: $relativePath -> redirecting to dispatcher root");
    $target = ($basePath !== '' ? $basePath : '/dispatcher') . '/';
    header('Location: ' . $target, true, 302);
    exit;
}

dispatcher_log("MATCHED_HASH: $matchedHash");

// Initialize session via bootstrap
dispatcher_init_session();
// If the PHP session file was lost, silently rebuild it from the remember cookie
dispatcher_restore_session();

$route = $_GET['route'] ?? '';
$dash = $_GET['d'] ?? '';

// Not logged in â†’ redirect to obfuscated login
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

<?php if (!$terminal_id): ?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
<title>R-Connect Dispatcher</title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="manifest" href="/dispatcher/manifest.json">
<link rel="stylesheet" href="/dispatcher/assets/css/dispatcher.css">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#47613b">
</head>
<body>
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
</body>
</html>
<?php exit; endif; ?>

<?php
/* Tab routing: allowlisted server-rendered screens on the dashboard URL */
$apiBase = '/dispatcher/api_' . $urls['api'] . '/';
$dashboardSecret = $_SESSION['dashboard_secret'] ?? '';
$dashBase = '/dispatcher/' . $urls['dashboard'] . '?d=' . urlencode($dashboardSecret) . '&tab=';
$logoutUrl = '/dispatcher/?d=' . urlencode($dashboardSecret) . '&route=logout';
$allowedTabs = ['home', 'attendance', 'queue', 'trips', 'more'];
$activeTab = strtolower(trim((string)($_GET['tab'] ?? '')));
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'home';
}
$csrf = $_SESSION['csrf_token'] ?? '';
$pageScript = $activeTab;

ob_start();
include __DIR__ . '/views/pages/' . $activeTab . '.php';
$pageHtml = ob_get_clean();

include __DIR__ . '/views/layout/app.php';
