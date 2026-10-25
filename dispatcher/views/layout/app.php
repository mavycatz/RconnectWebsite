<?php
/* Dispatcher app shell. Expects the page context built by dispatcher/index.php:
   $activeTab, $pageScript, $pageHtml, $dashBase, $logoutUrl, $apiBase, $token, $csrf,
   $terminal_name, $name, $lang, $translations, $languages, $langKeys, $terminalList,
   $defaultRoute, $defaultDestTerminalId. */
require_once __DIR__ . '/../../../passenger/utils/i18n.php';
?>
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
<link rel="stylesheet" href="/public/login/custom-alert.css">
<link rel="stylesheet" href="/dispatcher/assets/css/dispatcher.css">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Dispatcher">
<meta name="theme-color" content="#47613b">
</head>
<body>
<div class="app-shell">

<?php include __DIR__ . '/../../../passenger/views/layout/header.php'; ?>

<div class="header-bar">
  <div class="info">
    <h5><i class="bi bi-building"></i> <?php echo htmlspecialchars($terminal_name, ENT_QUOTES, 'UTF-8'); ?></h5>
    <div class="sub"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></div>
  </div>
  <div class="header-actions">
    <select id="languageSelect" class="lang-select" onchange="changeLanguage(this.value)" aria-label="<?php echo htmlspecialchars($translations['app.language'] ?? 'Language', ENT_QUOTES, 'UTF-8'); ?>">
      <?php foreach ($languages as $code => $label): ?>
      <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $code === $lang ? ' selected' : ''; ?>><?php echo htmlspecialchars($translations[$langKeys[$code] ?? 'app.english'] ?? $label, ENT_QUOTES, 'UTF-8'); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div class="clock-row">
  <span id="liveClock"></span>
  <span class="clock-end"><span id="liveDate"></span></span>
</div>

<main class="app-main">
<?php echo $pageHtml; ?>
</main>

<?php include __DIR__ . '/bottom-nav.php'; ?>

<div class="install-banner" id="installBanner">
  <button class="close-btn" onclick="this.parentElement.style.display='none';">&times;</button>
  <div class="text" id="installText"><strong>R-Connect Dispatcher</strong><br>Install for quick access</div>
  <button class="install-btn" id="installBtn">Install App</button>
</div>

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

</div><!-- /app-shell -->

<script>
window.DISPATCHER_CONFIG = {
  apiBase: <?php echo json_encode($apiBase); ?>,
  token: <?php echo json_encode($token); ?>,
  csrfToken: <?php echo json_encode($csrf); ?>,
  terminalId: <?php echo (int)$terminal_id; ?>,
  activeTab: <?php echo json_encode($activeTab); ?>,
  lang: <?php echo json_encode($lang); ?>,
  dashBase: <?php echo json_encode($dashBase); ?>,
  logoutUrl: <?php echo json_encode($logoutUrl); ?>,
  i18n: <?php echo json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
  terminals: <?php echo json_encode($terminalList); ?>,
  defaultRoute: <?php echo json_encode($defaultRoute); ?>,
  defaultDestTerminalId: <?php echo (int)$defaultDestTerminalId; ?>
};
</script>
<script src="/public/login/custom-alert.js"></script>
<script src="/dispatcher/assets/js/core.js"></script>
<script src="/dispatcher/assets/js/<?php echo htmlspecialchars($pageScript, ENT_QUOTES, 'UTF-8'); ?>.js"></script>
</body>
</html>
