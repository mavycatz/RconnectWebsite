<?php

$store = __DIR__ . '/.portal_key.php';

if (!file_exists($store)) {
    $value = bin2hex(random_bytes(16));
    file_put_contents($store, '<?php return ' . var_export($value, true) . ';', LOCK_EX);
    return $value;
}

return require $store;
