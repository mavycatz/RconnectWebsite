<?php

/**
 * =========================
 * FACEBOOK CONFIG
 * =========================
 *
 * Page Access Token is stored OUTSIDE the public web root
 * for security. The page ID can be stored in the database
 * via system_settings so it's editable from the admin panel.
 *
 * To generate a long-lived Page Access Token:
 *   1. Go to https://developers.facebook.com/tools/explorer/
 *   2. Select your app and get a short-lived User Token
 *   3. Exchange it: GET /oauth/access_token?grant_type=fb_exchange_token
 *      &client_id={app-id}&client_secret={app-secret}&fb_exchange_token={short-lived-token}
 *   4. Use /me/accounts to get a Page Access Token (never expires unless revoked)
 *
 * The token loaded here is the fallback if the DB setting is empty.
 * The preferered storage is system_settings (editable via admin panel).
 */

return [

    /*
     * Default Page ID (overridden by system_settings 'facebook_page_id')
     */
    'page_id' => '',

    /*
     * Default Page Access Token (overridden by system_settings 'facebook_page_access_token')
     * Keep this file outside public_html in production.
     */
    'access_token' => '',

    /*
     * API version
     */
    'api_version' => 'v23.0',
];
