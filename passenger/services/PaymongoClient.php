<?php

require_once __DIR__ . '/PaymongoConfig.php';

/**
 * PaymongoApiException
 *
 * Typed error for non-2xx PayMongo API responses. Carries the PayMongo
 * error code (e.g. parameter_invalid) and detail for logging; the message
 * must be mapped to a customer-friendly string by the caller before it is
 * ever shown to a passenger.
 */
class PaymongoApiException extends RuntimeException
{
    /** @var string */
    private $errorCode;

    /** @var array */
    private $errors;

    public function __construct(string $errorCode, string $message, array $errors = [], int $httpStatus = 0)
    {
        parent::__construct($message, $httpStatus);
        $this->errorCode = $errorCode;
        $this->errors = $errors;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

/**
 * PaymongoClient
 *
 * Minimal cURL-based client for the PayMongo API (TEST mode). Uses HTTP
 * Basic auth with the server-side secret key. Supports Idempotency-Key
 * headers so retried intent creation never duplicates a charge.
 */
class PaymongoClient
{
    /** @var string */
    private $secretKey;

    /** @var string */
    private $baseUrl;

    /** @var int */
    private $timeout;

    public function __construct(?string $secretKey = null)
    {
        $this->secretKey = $secretKey ?? PaymongoConfig::secretKey();
        $this->baseUrl = PaymongoConfig::apiBaseUrl();
        $this->timeout = 20;
    }

    /**
     * Create a PaymentIntent (server-side, secret key).
     *
     * @param int    $amountCentavos Amount in centavos (PHP * 100).
     * @param array  $opts           ['description'=>string, 'statement_descriptor'=>string,
     *                               'metadata'=>array, 'payment_method_allowed'=>array,
     *                               'idempotency_key'=>string]
     * @return array PayMongo resource array (data).
     */
    public function createPaymentIntent(int $amountCentavos, array $opts = []): array
    {
        $attributes = [
            'amount' => $amountCentavos,
            'currency' => 'PHP',
            'payment_method_allowed' => $opts['payment_method_allowed'] ?? ['gcash'],
        ];

        if (!empty($opts['description'])) {
            $attributes['description'] = (string) $opts['description'];
        }
        if (!empty($opts['statement_descriptor'])) {
            $attributes['statement_descriptor'] = (string) $opts['statement_descriptor'];
        }
        if (!empty($opts['metadata']) && is_array($opts['metadata'])) {
            $attributes['metadata'] = $opts['metadata'];
        }

        return $this->request('POST', '/v1/payment_intents', [
            'data' => ['attributes' => $attributes],
        ], $opts['idempotency_key'] ?? null);
    }

    /**
     * Create a PaymentMethod for an e-wallet (GCash) or QR Ph.
     *
     * @param string $type Payment method type ('gcash', 'qrph', ...).
     * @param array  $opts ['metadata'=>array, 'idempotency_key'=>?string,
     *                      'expiry_seconds'=>?int] expiry_seconds is honored for
     *                      QR Ph (and ShopeePay) only.
     */
    public function createPaymentMethod(string $type = 'gcash', array $opts = []): array
    {
        $attributes = [
            'type' => $type,
            'metadata' => $opts['metadata'] ?? [],
        ];

        $expiry = isset($opts['expiry_seconds']) ? (int) $opts['expiry_seconds'] : 0;
        if ($expiry > 0) {
            $attributes['expiry_seconds'] = $expiry;
        }

        return $this->request('POST', '/v1/payment_methods', [
            'data' => [
                'attributes' => $attributes,
            ],
        ], $opts['idempotency_key'] ?? null);
    }

    /**
     * Attach a PaymentMethod to a PaymentIntent.
     *
     * For redirect-based methods (gcash, maya, shopee_pay, ...) return_url
     * is required; the response carries attributes.next_action.redirect.url
     * which is where the passenger must be sent. QR Ph uses NO redirect and
     * no return_url — attach with return_url empty and read
     * attributes.next_action.code.image_url instead.
     */
    public function attachPaymentMethod(string $intentId, string $paymentMethodId, ?string $returnUrl = null, array $opts = []): array
    {
        $attributes = [
            'payment_method' => $paymentMethodId,
        ];
        if ($returnUrl !== null && $returnUrl !== '') {
            $attributes['return_url'] = $returnUrl;
        }

        return $this->request('POST', '/v1/payment_intents/' . rawurlencode($intentId) . '/attach', [
            'data' => [
                'attributes' => $attributes,
            ],
        ], $opts['idempotency_key'] ?? null);
    }

    /**
     * Retrieve a PaymentIntent (reconciliation before trusting events).
     */
    public function retrievePaymentIntent(string $intentId): array
    {
        return $this->request('GET', '/v1/payment_intents/' . rawurlencode($intentId));
    }

    /**
     * Register a webhook endpoint (used once during setup; the response
     * contains the endpoint's signing secret_key used to verify events).
     */
    public function registerWebhook(string $url, array $events): array
    {
        return $this->request('POST', '/v1/webhooks', [
            'data' => [
                'attributes' => [
                    'url' => $url,
                    'events' => $events,
                ],
            ],
        ]);
    }

    /**
     * Full GCash checkout: intent + payment method + attach.
     *
     * @param array $params [
     *   'amount_centavos' => int,
     *   'return_url'      => string (passenger returns here after auth/cancel),
     *   'description'     => string,
     *   'statement_descriptor' => ?string,
     *   'metadata'        => array,
     *   'idempotency_key' => ?string,
     * ]
     * @return array ['intent_id'=>string, 'checkout_url'=>string, 'payment_method_id'=>string]
     * @throws PaymongoApiException
     */
    public function createGcashCheckout(array $params): array
    {
        $intent = $this->createPaymentIntent((int) $params['amount_centavos'], [
            'description' => $params['description'] ?? null,
            'statement_descriptor' => $params['statement_descriptor'] ?? null,
            'metadata' => $params['metadata'] ?? [],
            'idempotency_key' => $params['idempotency_key'] ?? null,
        ]);

        $intentId = $intent['data']['id'] ?? '';
        if ($intentId === '') {
            throw new PaymongoApiException('intent_missing_id', 'Payment intent created without an id.', $intent);
        }

        $paymentMethod = $this->createPaymentMethod('gcash', [
            'metadata' => $params['metadata'] ?? [],
        ]);
        $paymentMethodId = $paymentMethod['data']['id'] ?? '';
        if ($paymentMethodId === '') {
            throw new PaymongoApiException('payment_method_missing_id', 'Payment method created without an id.', $paymentMethod);
        }

        $attached = $this->attachPaymentMethod($intentId, $paymentMethodId, (string) $params['return_url']);

        $nextAction = $attached['data']['attributes']['next_action'] ?? [];
        $checkoutUrl = $nextAction['redirect']['url'] ?? '';
        if ($checkoutUrl === '') {
            throw new PaymongoApiException('checkout_url_missing', 'Attach did not return a redirect URL.', $attached);
        }

        return [
            'intent_id' => $intentId,
            'payment_method_id' => $paymentMethodId,
            'checkout_url' => $checkoutUrl,
        ];
    }

    /**
     * Full QR Ph checkout: intent + qrph payment method + attach (no redirect).
     *
     * @param array $params [
     *   'amount_centavos'  => int,
     *   'expiry_seconds'   => int (60-9000, QR code lifetime),
     *   'description'      => string,
     *   'metadata'         => array,
     *   'idempotency_key'  => ?string,
     * ]
     * @return array ['intent_id'=>string, 'payment_method_id'=>string, 'qr_image'=>string,
     *                'client_key'=>string, 'expiry_seconds'=>int]
     * @throws PaymongoApiException
     */
    public function createQrPhCheckout(array $params): array
    {
        $intent = $this->createPaymentIntent((int) $params['amount_centavos'], [
            'payment_method_allowed' => ['qrph'],
            'description' => $params['description'] ?? null,
            'metadata' => $params['metadata'] ?? [],
            'idempotency_key' => $params['idempotency_key'] ?? null,
        ]);

        $intentId = $intent['data']['id'] ?? '';
        if ($intentId === '') {
            throw new PaymongoApiException('intent_missing_id', 'Payment intent created without an id.', $intent);
        }

        $clientKey = (string) ($intent['data']['attributes']['client_key'] ?? '');

        // PayMongo's QR Ph minimum is 60 seconds; anything lower is clamped.
        $expirySeconds = (int) ($params['expiry_seconds'] ?? 60);
        if ($expirySeconds < 60) {
            $expirySeconds = 60;
        }
        if ($expirySeconds > 9000) {
            $expirySeconds = 9000;
        }

        $paymentMethod = $this->createPaymentMethod('qrph', [
            'metadata' => $params['metadata'] ?? [],
            'expiry_seconds' => $expirySeconds,
        ]);
        $paymentMethodId = $paymentMethod['data']['id'] ?? '';
        if ($paymentMethodId === '') {
            throw new PaymongoApiException('payment_method_missing_id', 'Payment method created without an id.', $paymentMethod);
        }

        // QR Ph has no redirect: attach with NO return_url, read the Base64 QR.
        $attached = $this->attachPaymentMethod($intentId, $paymentMethodId);

        $nextAction = $attached['data']['attributes']['next_action'] ?? [];
        $qrImage = (string) ($nextAction['code']['image_url'] ?? '');
        if ($qrImage === '') {
            throw new PaymongoApiException('qr_image_missing', 'Attach did not return a QR code image.', $attached);
        }

        return [
            'intent_id' => $intentId,
            'payment_method_id' => $paymentMethodId,
            'client_key' => $clientKey,
            'qr_image' => $qrImage,
            'expiry_seconds' => $expirySeconds,
        ];
    }

    /**
     * Perform an authenticated request against the PayMongo API.
     *
     * @return array decoded JSON (the full PayMongo resource envelope)
     * @throws PaymongoApiException on transport errors or non-2xx responses
     */
    private function request(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): array
    {
        if ($this->secretKey === '') {
            throw new PaymongoApiException('not_configured', 'PayMongo secret key is not configured.', [], 0);
        }

        $url = $this->baseUrl . $path;
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $auth = base64_encode($this->secretKey . ':');
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        $ch = curl_init();
        $curlOpts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->secretKey . ':',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        // Some shared hosts (e.g. Hostinger) ship PHP without curl.cainfo /
        // openssl.cafile, which makes TLS verification fail with "unable to
        // get local issuer certificate". Fall back to the bundled Mozilla CA
        // bundle so verification stays ON and works everywhere.
        $caBundle = __DIR__ . '/../../app/config/cacert.pem';
        if (is_file($caBundle)) {
            $curlOpts[CURLOPT_CAINFO] = $caBundle;
        }

        curl_setopt_array($ch, $curlOpts);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        } elseif (strtoupper($method) !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // Note: curl_close() is intentionally omitted — deprecated since PHP 8.0.

        if ($raw === false || $errno !== 0) {
            throw new PaymongoApiException('transport_error', 'PayMongo request failed: ' . $error, [], $status);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new PaymongoApiException('invalid_response', 'PayMongo returned a non-JSON response.', ['raw' => $raw], $status);
        }

        if ($status < 200 || $status >= 300) {
            $firstError = $decoded['errors'][0] ?? [];
            $code = (string) ($firstError['code'] ?? 'api_error');
            $message = (string) ($firstError['message'] ?? $firstError['detail'] ?? 'PayMongo API error');
            throw new PaymongoApiException($code, $message, $decoded['errors'] ?? [], $status);
        }

        return $decoded;
    }
}
