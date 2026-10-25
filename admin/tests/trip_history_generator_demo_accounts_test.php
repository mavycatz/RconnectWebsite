<?php
declare(strict_types=1);

/**
 * Regression checks for demo-account reuse in the trip history generator.
 *
 * Reproduces the live failure where a re-run tried to insert a cargo demo
 * account whose derived Gmail address already existed, hitting the unique
 * email index, because cargo addresses are not part of the passenger pool
 * preload. The registered accounts must be reused instead, and an address
 * owned by a non-demo passenger must be skipped instead of aborting the run.
 *
 * Run: php admin/tests/trip_history_generator_demo_accounts_test.php
 */

namespace App\Core {
    final class Database
    {
        public static ?\PDO $testPdo = null;

        public static function get(): \PDO
        {
            return self::$testPdo;
        }

        public static function tryGet(): ?\PDO
        {
            return self::$testPdo;
        }

        public static function reconnect(): \PDO
        {
            return self::$testPdo;
        }
    }
}

namespace {
    error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
    putenv('APP_ENV=development');
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['REQUEST_URI'] = '/trip-history-generator.php';
    $_SESSION = [];
    session_save_path(sys_get_temp_dir());
    ini_set('session.use_cookies', '0');

    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('CREATE TABLE passengers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name TEXT, name TEXT, passenger_code TEXT, passenger_type TEXT,
        type TEXT, email TEXT UNIQUE, discount_type TEXT, discount_status TEXT,
        space_occupied INTEGER, status TEXT, account_status TEXT, created_at TEXT
    )');
    $db->exec('CREATE TABLE audit_trail (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER, username TEXT, user_role TEXT, module TEXT, action TEXT,
        record_id TEXT, description TEXT, previous_values TEXT, new_values TEXT,
        ip_address TEXT, user_agent TEXT, status TEXT, is_read INTEGER, created_at TEXT
    )');
    $db->exec('CREATE TABLE passenger_code_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        passenger_code TEXT, passenger_id INTEGER, classification TEXT, retired_at TEXT
    )');
    $db->exec('CREATE TABLE passenger_id_sequences (prefix TEXT PRIMARY KEY, last_number INTEGER)');
    \App\Core\Database::$testPdo = $db;

    ob_start();
    require dirname(__DIR__, 2) . '/trip-history-generator.php';
    ob_end_clean();

    /* The page turns error display off outside development; a failing check
       must still be visible when this test runs on any environment. */
    error_reporting(E_ALL);
    ini_set('display_errors', '1');

    $checks = 0;
    $check = static function (bool $ok, string $message) use (&$checks): void {
        if (!$ok) {
            throw new RuntimeException($message);
        }
        $checks++;
    };

    $ctx = [
        'demoMarker' => ['mode' => 'email', 'column' => 'email'],
        'passengerRequiredDefaults' => [],
    ];

    /* Scenario 1: registered cargo account from a previous run must be reused. */
    $db->exec("INSERT INTO passengers (id, full_name, name, passenger_code, passenger_type, type, email)
        VALUES (501, 'Baggage record', 'Baggage record', 'OTH-001', 'other', 'other', 'roxasfeliciana606@gmail.com')");
    $db->exec("INSERT INTO audit_trail (user_id, username, user_role, module, action, record_id, description, user_agent, status, is_read, created_at)
        VALUES (NULL, 'Data Simulator', 'System', 'Passenger', 'History Demo Account', '501', 'Generated demo passenger account', 'trip-history-generator', 'Success', 0, '2026-10-01 00:00:00')");

    $preload = buildDemoAccountCache($db, $ctx['demoMarker'], []);
    $check(isset($preload['roxasfeliciana606@gmail.com']), 'Registered demo accounts are preloaded by their stored email');
    $check((int)$preload['roxasfeliciana606@gmail.com']['id'] === 501, 'Preload maps the existing cargo account');

    $acct = ['cache' => [], 'preload' => $preload, 'seq' => [], 'ids' => [], 'created' => 0, 'reused' => 0, 'skipped' => 0];
    $account = ensureDemoAccount(
        $db,
        $ctx,
        'c:TRIP-1|cargo|0',
        'roxasfeliciana606@gmail.com',
        ['full_name' => 'Baggage record', 'name' => 'Baggage record', 'type' => 'other'],
        $acct
    );
    $check((int)$account['id'] === 501 && $account['created'] === false, 'A re-run reuses the registered cargo account instead of inserting a duplicate');
    $check($acct['reused'] === 1 && $acct['created'] === 0, 'Reuse is counted, not creation');
    $check(
        (int)$db->query("SELECT COUNT(*) FROM passengers WHERE email = 'roxasfeliciana606@gmail.com'")->fetchColumn() === 1,
        'No duplicate passenger row is created'
    );

    /* Scenario 2: an address this tool did not create is skipped, not hijacked. */
    $db->exec("INSERT INTO passengers (id, full_name, name, passenger_code, passenger_type, type, email)
        VALUES (502, 'Feliciana Roxas', 'Feliciana Roxas', 'REG-999', 'Regular', 'Regular', 'roxasfeliciana123@gmail.com')");
    $acct2 = ['cache' => [], 'preload' => $preload, 'seq' => [], 'ids' => [], 'created' => 0, 'reused' => 0, 'skipped' => 0];
    $account2 = ensureDemoAccount(
        $db,
        $ctx,
        'c:TRIP-2|cargo|0',
        'roxasfeliciana123@gmail.com',
        ['full_name' => 'Cargo', 'name' => 'Cargo', 'type' => 'other'],
        $acct2
    );
    $check($account2['id'] === '' && !empty($account2['skipped']), 'A non-registered address is skipped rather than hijacked or aborting');
    $check($acct2['skipped'] === 1 && $acct2['created'] === 0, 'Skipped rides are counted, not created');
    $check(
        (int)$db->query("SELECT COUNT(*) FROM audit_trail WHERE action = 'History Demo Account Skipped'")->fetchColumn() === 1,
        'The skip is recorded in the audit trail'
    );

    /* Scenario 3: a genuinely new address inserts, and the next run reuses it. */
    $acct3 = ['cache' => [], 'preload' => $preload, 'seq' => [], 'ids' => [], 'created' => 0, 'reused' => 0, 'skipped' => 0];
    $account3 = ensureDemoAccount(
        $db,
        $ctx,
        'p:New Person',
        'newperson777@gmail.com',
        ['full_name' => 'New Person', 'name' => 'New Person', 'type' => 'Regular'],
        $acct3
    );
    $check((int)$account3['id'] > 0 && $account3['created'] === true, 'A new address still creates the account');
    $check($acct3['reused'] === 0, 'New accounts are not counted as reuses');

    $preload2 = buildDemoAccountCache($db, $ctx['demoMarker'], []);
    $acct4 = ['cache' => [], 'preload' => $preload2, 'seq' => [], 'ids' => [], 'created' => 0, 'reused' => 0, 'skipped' => 0];
    $account4 = ensureDemoAccount(
        $db,
        $ctx,
        'p:New Person',
        'newperson777@gmail.com',
        ['full_name' => 'New Person', 'name' => 'New Person', 'type' => 'Regular'],
        $acct4
    );
    $check((int)$account4['id'] === (int)$account3['id'] && $account4['created'] === false, 'The next run reuses the account created by the previous run');
    $check(
        (int)$db->query("SELECT COUNT(*) FROM passengers WHERE email = 'newperson777@gmail.com'")->fetchColumn() === 1,
        'The next run does not duplicate the new account'
    );

    echo "$checks demo account reuse checks passed.\n";
}
