<?php
// ── Dispatcher session diagnostic ──────────────────────────────
// Upload to: /dispatcher/debug_session.php
// Open in the SAME browser you use for the dispatcher screen.
// Step 1: visit  ?step=1   → writes a timestamp into the session
// Step 2: visit  ?step=2   → reads it back
// The output tells us exactly why the session dies.
require_once __DIR__ . '/bootstrap.php';
dispatcher_init_session();

header('Content-Type: text/plain; charset=utf-8');

echo "=== DISPATCHER SESSION DIAGNOSTIC ===\n\n";
echo "PHP version      : " . phpversion() . "\n";
echo "PHP SAPI         : " . PHP_SAPI . "\n";
echo "Server software  : " . ($_SERVER['SERVER_SOFTWARE'] ?? 'n/a') . "\n";
echo "Server time      : " . date('Y-m-d H:i:s') . "\n\n";

// 1) Is the NEW bootstrap.php live on the server?
$marker = __DIR__ . '/../storage/logs/.startup_marker';
if (is_file($marker)) {
    $lines = file($marker, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $last = end($lines);
    echo "bootstrap marker : " . $last . "  <-- every page load appends here\n";
} else {
    echo "bootstrap marker : NOT WRITABLE (storage/logs missing or read-only)\n";
}

// 2) Session settings actually in effect
$path = ini_get('session.save_path');
echo "\nsave_path        : " . $path . "\n";
echo "save_path in use : " . session_save_path() . "\n";
echo "save_path writable: " . (is_writable(session_save_path()) ? 'YES' : 'NO') . "\n";
echo "gc_maxlifetime   : " . ini_get('session.gc_maxlifetime') . " sec\n";
echo "cookie_lifetime  : " . ini_get('session.cookie_lifetime') . " sec\n";
echo "session name     : " . session_name() . "\n";
echo "session id NOW   : " . session_id() . "\n";
echo "cookie PHPSESSID : " . ($_COOKIE[session_name()] ?? '(not sent by browser)') . "\n";
echo "cookie dispatcher_remember: " . (isset($_COOKIE['dispatcher_remember']) ? 'present' : '(absent)') . "\n\n";

// 3) Session file on disk
$sessFile = session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . session_id();
if (is_file($sessFile)) {
    echo "session file     : " . $sessFile . "\n";
    echo "file mtime       : " . date('Y-m-d H:i:s', filemtime($sessFile)) . "\n";
    echo "file size        : " . filesize($sessFile) . " bytes\n\n";
} else {
    echo "session file     : NOT FOUND on disk -> " . $sessFile . "\n";
    echo "                 : (if the browser sent a cookie but no file exists, the host wipes the session dir)\n\n";
}

// 4) Write / read persistence test
$step = $_GET['step'] ?? '';
if ($step === '1') {
    $_SESSION['diag_ts'] = time();
    echo "STEP 1 DONE: timestamp " . date('Y-m-d H:i:s') . " written to session.\n";
    echo "Now open: ?step=2  (same browser, no login needed)\n";
} elseif ($step === '2') {
    if (isset($_SESSION['diag_ts'])) {
        $elapsed = time() - $_SESSION['diag_ts'];
        echo "STEP 2 RESULT: session SURVIVED " . $elapsed . " seconds.\n";
        echo "Session data written at: " . date('Y-m-d H:i:s', $_SESSION['diag_ts']) . "\n";
    } else {
        echo "STEP 2 RESULT: session data is GONE.\n";
        echo "Session id now : " . session_id() . "\n";
        echo "Session id before: " . ($_COOKIE[session_name()] ?? '(no cookie)') . "\n";
        echo "-> If the ids differ, the browser did NOT keep the session cookie.\n";
        echo "-> If the ids match but data is gone, the host deleted the session file between requests.\n";
    }
} else {
    echo "Open ?step=1 first, then ?step=2.\n";
}

echo "\n=== END ===\n";
