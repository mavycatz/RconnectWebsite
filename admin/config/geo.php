<?php

/**
 * Address (PSGC) API configuration.
 *
 * The official PSA classification API supplies provinces, cities and
 * barangays. The bundled PSGC snapshot in admin/assets/data is used
 * automatically whenever the API is unreachable or returns no records.
 *
 * Move the key to the environment (PSA_PSGC_API_KEY) on production servers;
 * the value here is the project's assigned key and is sent server-side only.
 */

return [
    'base_url' => getenv('PSA_PSGC_BASE_URL') ?: 'https://classification.psa.gov.ph/psgc/',
    'api_key' => getenv('PSA_PSGC_API_KEY') ?: '23f085d6-725f-48f9-82c0-1495ca969a83',
    'timeout' => 8,
    'cache_ttl' => 2592000,   // 30 days for API results
    'fallback_ttl' => 3600,   // 1 hour for bundled-fallback results
];
