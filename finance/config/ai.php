<?php

require_once __DIR__ . '/Env.php';

use Finance\Config\Env;

return [
    'api_key' => Env::get('FINANCE_OPENAI_KEY', ''),
    'model' => Env::get('FINANCE_OPENAI_MODEL', 'gpt-4o-mini'),
    'api_url' => Env::get('FINANCE_OPENAI_URL', 'https://api.openai.com/v1/chat/completions'),
];
