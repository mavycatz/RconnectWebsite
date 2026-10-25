<?php

namespace Admin\Services;

/**
 * =========================
 * FACEBOOK SERVICE
 * =========================
 *
 * Handles posting announcements to a Facebook Page
 * via the Graph API using cURL.
 */
class FacebookService
{
    private string $pageId;
    private string $accessToken;
    private string $apiVersion;

    private const TOKEN_EXPIRED_CODES = [190];
    private const TOKEN_EXPIRED_MESSAGES = [
        'Session has expired',
        'access token has expired',
        'access token is invalid',
        'Error validating access token',
    ];

    private const PERMISSION_ERROR_CODES = [200];
    private const PERMISSION_ERROR_SUBCODES = [33];
    private const PERMISSION_ERROR_MESSAGES = [
        'pages_manage_posts',
        'pages_read_engagement',
    ];

    private const RATE_LIMIT_ERROR_CODES = [4, 613, 80001];
    private const RATE_LIMIT_ERROR_MESSAGES = [
        'too many calls',
    ];

    public function __construct(string $pageId, string $accessToken, string $apiVersion = 'v23.0')
    {
        $this->pageId      = $pageId;
        $this->accessToken = $accessToken;
        $this->apiVersion  = $apiVersion;
    }

    /**
     * -----------------------------------------
     * POST TEXT-ONLY ANNOUNCEMENT TO PAGE FEED
     * -----------------------------------------
     *
     * @param string $message The feed post content
     * @return array ['success' => bool, 'facebook_post_id' => string|null, 'error' => string|null]
     */
    public function postText(string $message): array
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->pageId}/feed";

        $params = [
            'message'     => $message,
            'published'   => 'true',
            'access_token' => $this->accessToken,
        ];

        $result = $this->callGraphApi($url, $params, 'POST');

        if (!$result['success']) {
            return $result;
        }

        return $this->verifyAndSettle($result['facebook_post_id'], $this->pageId);
    }

    /**
     * -----------------------------------------
     * POST IMAGE TO PAGE TIMELINE
     * -----------------------------------------
     *
     * Creates a normal, published Page feed post that contains the message
     * and the image together, visible on the Page timeline to everyone.
     * Posting straight to /{pageId}/photos only adds a Photo (shown under
     * the Photos tab) and does not produce a proper timeline post.
     *
     * @param string $message  The feed post content
     * @param string $imageUrl Publicly accessible URL of the image
     * @return array ['success' => bool, 'facebook_post_id' => string|null, 'error' => string|null]
     */
    public function postImage(string $message, string $imageUrl): array
    {
        // Step 1: upload the image privately (no story) so we can attach it to a feed post.
        $upload = $this->uploadPhoto($imageUrl);
        if (!$upload['success']) {
            return $upload;
        }
        $photoId = $upload['facebook_post_id'];

        // Step 2: create a normal, published Page timeline post with the image attached.
        $feedParams = [
            'message'        => $message,
            'published'      => 'true',
            'attached_media' => json_encode([['media_fbid' => (string)$photoId]]),
            'access_token'   => $this->accessToken,
        ];

        $result = $this->callGraphApi(
            "https://graph.facebook.com/{$this->apiVersion}/{$this->pageId}/feed",
            $feedParams,
            'POST'
        );

        if (!$result['success']) {
            // Best-effort cleanup so a failed post does not leave an orphan unpublished photo.
            $cleanup = $this->deletePost($photoId);
            error_log("FacebookService: feed post failed, unpublished photo {$photoId} cleanup " . ($cleanup['success'] ? 'ok' : 'failed') . " — " . $result['error']);

            return $result;
        }

        return $this->verifyAndSettle($result['facebook_post_id'], $this->pageId, $photoId);
    }

    /**
     * -----------------------------------------
     * VALIDATE PAGE TOKEN (DIAGNOSTICS)
     * -----------------------------------------
     *
     * Confirms the stored token is a valid Page token for the configured
     * Page by reading the Page's identity back. Never returns the token.
     *
     * @param string $pageId The configured Page ID
     * @return array ['valid' => bool, 'error_type' => string, 'page_id' => string|null, 'page_name' => string|null]
     */
    public function validateToken(string $pageId): array
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$pageId}";

        $params = [
            'fields'       => 'id,name,is_published',
            'access_token' => $this->accessToken,
        ];

        $result = $this->callGraphApi($url, $params, 'GET');

        if ($result['success']) {
            $data        = $result['data'];
            $confirmedId = $data['id'] ?? null;
            $pageName    = $data['name'] ?? null;

            if ((string)$confirmedId === (string)$pageId && !empty($pageName)) {
                return [
                    'valid'      => true,
                    'error_type' => 'ok',
                    'page_id'    => $pageId,
                    'page_name'  => $pageName,
                ];
            }

            return [
                'valid'      => false,
                'error_type' => 'invalid',
                'page_id'    => $pageId,
                'page_name'  => $pageName ?: null,
            ];
        }

        $errorType = $result['error_type'] ?? 'other';
        if ($errorType === 'token_expired') {
            $errorType = 'token_expired';
        } elseif (
            in_array($result['error_code'] ?? null, self::PERMISSION_ERROR_CODES, true)
            || stripos((string)($result['error'] ?? ''), 'oauth') !== false
            || stripos((string)($result['error'] ?? ''), 'access token') !== false
        ) {
            $errorType = 'invalid';
        } else {
            $errorType = 'other';
        }

        return [
            'valid'      => false,
            'error_type' => $errorType,
            'page_id'    => $pageId,
            'page_name'  => null,
        ];
    }

    /**
     * -----------------------------------------
     * UPLOAD IMAGE PRIVATELY (NO STORY)
     * -----------------------------------------
     *
     * Uploads the image with published=false so no photo story is created;
     * it exists only to be attached to a feed post via media_fbid.
     *
     * @param string $imageUrl Publicly accessible URL of the image
     * @return array ['success' => bool, 'facebook_post_id' => string|null, 'error' => string|null]
     */
    private function uploadPhoto(string $imageUrl): array
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->pageId}/photos";

        $params = [
            'url'          => $imageUrl,
            'published'    => 'false',
            'access_token' => $this->accessToken,
        ];

        return $this->callGraphApi($url, $params);
    }

    /**
     * -----------------------------------------
     * DELETE A FACEBOOK POST
     * -----------------------------------------
     *
     * @param string $facebookPostId The ID of the post to delete
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function deletePost(string $facebookPostId): array
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$facebookPostId}";

        $params = [
            'access_token' => $this->accessToken,
        ];

        $ch = curl_init($url . '?' . http_build_query($params));
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT       => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($curlError) {
            return [
                'success'    => false,
                'error'      => "cURL error: {$curlError}",
                'error_type' => 'other',
            ];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($decoded['success']) && $decoded['success'] === true) {
            return [
                'success'    => true,
                'error'      => null,
                'error_type' => null,
            ];
        }

        $errorMsg = $decoded['error']['message'] ?? "HTTP {$httpCode}: " . substr($response, 0, 200);
        $errorType = $this->isTokenExpired($decoded) ? 'token_expired' : 'other';
        return [
            'success'    => false,
            'error'      => $errorMsg,
            'error_type' => $errorType,
        ];
    }

    /**
     * -----------------------------------------
     * DETECT TOKEN EXPIRATION ERROR
     * -----------------------------------------
     */
    public function isTokenExpired(array $response): bool
    {
        $code = $response['error']['code'] ?? null;
        if ($code !== null && in_array($code, self::TOKEN_EXPIRED_CODES, true)) {
            return true;
        }

        $message = $response['error']['message'] ?? '';
        foreach (self::TOKEN_EXPIRED_MESSAGES as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * -----------------------------------------
     * CLASSIFY A GRAPH API ERROR
     * -----------------------------------------
     *
     * @param array $response A decoded Graph API error body
     * @return string 'token_expired' | 'permission' | 'rate_limit' | 'other'
     */
    public function classifyError(array $response): string
    {
        if ($this->isTokenExpired($response)) {
            return 'token_expired';
        }

        $code    = $response['error']['code'] ?? null;
        $subcode = $response['error']['error_subcode'] ?? null;
        $message = $response['error']['error_user_msg'] ?? $response['error']['message'] ?? '';

        if ($code !== null && in_array($code, self::PERMISSION_ERROR_CODES, true)) {
            return 'permission';
        }
        if ($subcode !== null && in_array($subcode, self::PERMISSION_ERROR_SUBCODES, true)) {
            return 'permission';
        }
        foreach (self::PERMISSION_ERROR_MESSAGES as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return 'permission';
            }
        }

        if ($code !== null && in_array($code, self::RATE_LIMIT_ERROR_CODES, true)) {
            return 'rate_limit';
        }
        foreach (self::RATE_LIMIT_ERROR_MESSAGES as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return 'rate_limit';
            }
        }

        return 'other';
    }

    /**
     * -----------------------------------------
     * VERIFY A CREATED FEED POST BY READ-BACK
     * -----------------------------------------
     *
     * Reads the post back and confirms it is a genuinely published, visible
     * feed post. Retries up to 3 times with ~2s backoff to absorb propagation.
     *
     * @param string $postId The composite {pageId}_{postId} returned by the create call
     * @param string $pageId The configured Page ID
     * @return array ['success' => bool, 'facebook_post_id' => string|null, 'error' => string|null, ...]
     */
    private function verifyPost(string $postId, string $pageId): array
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$postId}";

        $params = [
            'fields'       => 'id,is_published,is_hidden,scheduled_publish_time,status_type',
            'access_token' => $this->accessToken,
        ];

        $lastError        = 'read-back did not verify';
        $lastErrorType    = 'other';
        $lastErrorCode    = null;
        $lastErrorSubcode = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $result = $this->callGraphApi($url, $params, 'GET');

            if ($result['success']) {
                $problems = $this->verificationProblems($postId, $pageId, $result['data']);

                if ($problems === []) {
                    return [
                        'success'          => true,
                        'facebook_post_id' => $postId,
                        'error'            => null,
                    ];
                }

                $lastError        = 'Facebook accepted the post but created it as ' . implode('; ', $problems) . ' — it is not publicly visible.';
                $lastErrorType    = 'other';
                $lastErrorCode    = null;
                $lastErrorSubcode = null;
            } else {
                $lastError        = $result['error'];
                $lastErrorType    = $result['error_type'] ?? 'other';
                $lastErrorCode    = $result['error_code'] ?? null;
                $lastErrorSubcode = $result['error_subcode'] ?? null;
            }

            if ($attempt < 3) {
                sleep(2);
            }
        }

        return [
            'success'          => false,
            'facebook_post_id' => null,
            'error'            => $lastError,
            'error_type'       => $lastErrorType,
            'error_code'       => $lastErrorCode,
            'error_subcode'    => $lastErrorSubcode,
        ];
    }

    /**
     * -----------------------------------------
     * CHECK A READ-BACK BODY AGAINST THE
     * "PUBLICLY VISIBLE FEED POST" CRITERIA
     * -----------------------------------------
     *
     * A "publicly visible feed post" must: carry the composite {pageId}_{postId}
     * id, have is_published=true, is_hidden=false, no scheduled_publish_time,
     * and a status_type string. status_type labels the attachment kind (status,
     * photo, link, mobile_status_update, ...) — it does not reveal visibility,
     * so any non-empty value is accepted.
     *
     * @return string[] List of problems, empty when the post verifies
     */
    private function verificationProblems(string $postId, string $pageId, array $data): array
    {
        $problems = [];

        $id = $data['id'] ?? null;
        if ($id === null || (string)$id !== $postId || !preg_match('/^\d+_\d+$/', (string)$id)) {
            $problems[] = 'id=' . ($id === null ? 'missing' : var_export($id, true)) . ' (expected ' . $postId . ', form {pageId}_{postId})';
        }

        if (!isset($data['is_published']) || $data['is_published'] !== true) {
            $problems[] = 'is_published=' . ($data['is_published'] ?? 'missing');
        }

        if (!isset($data['is_hidden']) || $data['is_hidden'] !== false) {
            $problems[] = 'is_hidden=' . ($data['is_hidden'] ?? 'missing');
        }

        if (array_key_exists('scheduled_publish_time', $data) && $data['scheduled_publish_time'] !== null) {
            $problems[] = 'scheduled_publish_time=' . var_export($data['scheduled_publish_time'], true);
        }

        $statusType = $data['status_type'] ?? null;
        if (!is_string($statusType) || $statusType === '') {
            $problems[] = 'status_type=' . ($statusType === null ? 'missing' : var_export($statusType, true));
        }

        return $problems;
    }

    /**
     * -----------------------------------------
     * VERIFY A CREATE AND SETTLE THE VERDICT
     * -----------------------------------------
     *
     * On verification failure, best-effort deletes the created post (and, for
     * image posts, the uploaded unpublished photo) and logs each outcome.
     *
     * @param string      $postId  The composite {pageId}_{postId} to verify
     * @param string      $pageId  The configured Page ID
     * @param string|null $photoId Optional uploaded photo ID to clean up too
     * @return array ['success' => bool, 'facebook_post_id' => string|null, 'error' => string|null, 'error_type' => string|null, 'error_code' => int|null, 'error_subcode' => int|null]
     */
    private function verifyAndSettle(string $postId, string $pageId, ?string $photoId = null): array
    {
        error_log("FacebookService: verifying post {$postId} ...");

        $verification = $this->verifyPost($postId, $pageId);

        if ($verification['success']) {
            error_log("FacebookService: verification OK for post {$postId}");
            return [
                'success'          => true,
                'facebook_post_id' => $postId,
                'error'            => null,
                'error_type'       => null,
                'error_code'       => null,
                'error_subcode'    => null,
            ];
        }

        error_log("FacebookService: verification FAILED for post {$postId} — " . ($verification['error'] ?? 'unknown') . " (type=" . ($verification['error_type'] ?? 'other') . ")");

        $cleanupResults = [];

        $postCleanup = $this->deletePost($postId);
        $cleanupResults['post'] = $postCleanup['success'] ? 'ok' : 'failed';
        if ($postCleanup['error']) {
            $cleanupResults['post'] .= " ({$postCleanup['error']})";
        }

        if ($photoId !== null) {
            $photoCleanup = $this->deletePost($photoId);
            $cleanupResults['photo'] = $photoCleanup['success'] ? 'ok' : 'failed';
            if ($photoCleanup['error']) {
                $cleanupResults['photo'] .= " ({$photoCleanup['error']})";
            }
        }

        error_log("FacebookService: cleanup for failed post {$postId} — " . json_encode($cleanupResults));

        return [
            'success'          => false,
            'facebook_post_id' => null,
            'error'            => $verification['error'],
            'error_type'       => $verification['error_type'],
            'error_code'       => $verification['error_code'],
            'error_subcode'    => $verification['error_subcode'],
        ];
    }

    /**
     * -----------------------------------------
     * CORE CURL REQUEST
     * -----------------------------------------
     */
    private function callGraphApi(string $url, array $params, string $method = 'POST'): array
    {
        $method     = strtoupper($method);
        $requestUrl = $url;
        if ($method === 'GET') {
            $requestUrl .= '?' . http_build_query($params);
        }

        $ch = curl_init($requestUrl);

        // Log the request without ever writing the access token into the logs.
        $logParams = $params;
        if (array_key_exists('access_token', $logParams)) {
            $logParams['access_token'] = '[REDACTED]';
        }

        error_log("===== FACEBOOK REQUEST =====");
        error_log("METHOD: " . $method);
        error_log("URL: " . $url);
        error_log("HTTP BODY: " . http_build_query($logParams));

        $curlOpts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        if ($method === 'POST') {
            $curlOpts[CURLOPT_POST]        = true;
            $curlOpts[CURLOPT_POSTFIELDS]  = http_build_query($params);
        } else {
            $curlOpts[CURLOPT_HTTPGET]     = true;
        }

        curl_setopt_array($ch, $curlOpts);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        error_log("HTTP CODE: " . $httpCode);
        error_log("FACEBOOK RESPONSE: " . $response);
        $curlError = curl_error($ch);

        if ($curlError) {
            return [
                'success'           => false,
                'facebook_post_id'  => null,
                'data'              => null,
                'error'             => "cURL error: {$curlError}",
                'error_type'        => 'other',
                'error_code'        => null,
                'error_subcode'     => null,
            ];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($decoded['id'])) {
            return [
                'success'           => true,
                'facebook_post_id'  => $decoded['id'],
                'data'              => $decoded,
                'error'             => null,
                'error_type'        => null,
                'error_code'        => null,
                'error_subcode'     => null,
            ];
        }

        $errorMsg = $decoded['error']['error_user_msg'] ?? $decoded['error']['message'] ?? "HTTP {$httpCode}: " . substr($response, 0, 200);
        $errorCode = $decoded['error']['code'] ?? null;
        $errorSubcode = $decoded['error']['error_subcode'] ?? null;
        if ($errorCode || $errorSubcode) {
            $errorMsg .= " (code={$errorCode}, subcode={$errorSubcode})";
        }
        return [
            'success'           => false,
            'facebook_post_id'  => null,
            'data'              => $decoded,
            'error'             => $errorMsg,
            'error_type'        => $this->classifyError($decoded),
            'error_code'        => $errorCode,
            'error_subcode'     => $errorSubcode,
        ];
    }
}
