<?php
/**
 * Automatically prepended to every admin PHP request.
 * Rewrites all /admin/ URLs in the output to include the secret path.
 */

require_once __DIR__ . '/secret.php';

$adminSecret = ADMIN_SECRET;

ob_start(function (string $html) use ($adminSecret): string {
    /* Replace /admin/ with /admin/<secret>/ everywhere in the output */
    return str_replace('/admin/', "/admin/$adminSecret/", $html);
});
