<?php
/**
 * Dispatcher token signing secret.
 * Production deployments MUST override DISPATCHER_TOKEN_SECRET with a strong
 * random value before upload (e.g., define it in this file or a site-level
 * config). The fallback keeps existing sessions/tokens valid during rollout.
 */
if (!defined('DISPATCHER_TOKEN_SECRET')) {
    define('DISPATCHER_TOKEN_SECRET', 'RCONNECT_DISPATCHER_SECRET');
}
