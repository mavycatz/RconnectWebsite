<?php
declare(strict_types=1);

/**
 * Guarded cleanup of placeholder/dummy rows from the announcements table.
 *
 * Candidate rows are only the ones whose title/message match obvious
 * placeholder patterns (test, dummy, sample, placeholder, lorem, asdf,
 * qwerty, foo). Nothing is deleted automatically: the operator reviews the
 * candidate list and confirms the exact ids to delete at the prompt.
 *
 * Usage:
 *   php admin/migrations/cleanup_dummy_announcements.php --dry-run   List candidates only
 *   php admin/migrations/cleanup_dummy_announcements.php             List and confirm ids to delete
 *
 * If no candidates exist the script is a no-op. Deleting a row also removes
 * its uploaded image file when present.
 */

require_once __DIR__ . '/../../app/Core/Database.php';

use App\Core\Database;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$db = Database::get();

$sql = "SELECT id, title, image_path, created_at, LEFT(message, 100) AS snippet
        FROM announcements
        WHERE title LIKE '%test%'
           OR title LIKE '%dummy%'
           OR title LIKE '%sample%'
           OR title LIKE '%placeholder%'
           OR title LIKE '%lorem%'
           OR title LIKE '%asdf%'
           OR title LIKE '%qwerty%'
           OR title LIKE '%foo%'
           OR message LIKE '%lorem ipsum%'
        ORDER BY id ASC";

$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "No dummy announcement candidates found. Nothing to do.\n";
    exit(0);
}

echo "Candidate placeholder announcements (review before deleting):\n\n";
foreach ($rows as $row) {
    echo sprintf("#%-6s %s  (%s)\n", $row['id'], $row['title'], $row['created_at']);
    $snippet = trim(preg_replace('/\s+/', ' ', (string)$row['snippet']));
    if ($snippet !== '') {
        echo '        ' . $snippet . "\n";
    }
}
echo "\n";

if ($dryRun) {
    echo "Dry run: " . count($rows) . " candidate(s) listed, nothing deleted.\n";
    exit(0);
}

echo 'Enter the ids to DELETE, comma-separated (leave blank to abort): ';
$line = trim((string)fgets(STDIN));
if ($line === '') {
    echo "Aborted. No rows deleted.\n";
    exit(0);
}

$requested = array_map('intval', array_filter(explode(',', $line), static fn ($part) => trim($part) !== ''));
$candidateIds = array_map('intval', array_column($rows, 'id'));
$ids = array_values(array_intersect($requested, $candidateIds));

if (!$ids) {
    echo "No valid candidate ids provided. No rows deleted.\n";
    exit(0);
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

$info = $db->prepare("SELECT id, title, image_path FROM announcements WHERE id IN ($placeholders)");
$info->execute($ids);
$toDelete = $info->fetchAll(PDO::FETCH_ASSOC);

$projectRoot = dirname(__DIR__, 2);

foreach ($toDelete as $row) {
    if (!empty($row['image_path'])) {
        $file = $projectRoot . '/' . ltrim((string)$row['image_path'], '/');
        if (is_file($file)) {
            @unlink($file);
            echo "Removed image {$row['image_path']}\n";
        }
    }
}

$del = $db->prepare("DELETE FROM announcements WHERE id IN ($placeholders)");
$del->execute($ids);

echo "Deleted " . $del->rowCount() . " announcement row(s).\n";
echo "Done.\n";
