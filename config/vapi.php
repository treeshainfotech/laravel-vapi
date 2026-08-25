<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Vapi API Key
    |--------------------------------------------------------------------------
    |
    | The private API key used to authenticate against the Vapi API. You can
    | find it in the Vapi dashboard under Organization Settings > API Keys.
    |
    */

    'api_key' => env('VAPI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The Vapi API endpoint. Only change this if you are routing through a
    | proxy or targeting a non-default Vapi environment.
    |
    */

    'base_url' => env('VAPI_BASE_URL', 'https://api.vapi.ai'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Behaviour
    |--------------------------------------------------------------------------
    |
    | The request timeout is expressed in seconds. Retries apply to 408, 429,
    | and 5xx responses, with exponential backoff handled by the Vapi SDK.
    |
    */

    'timeout' => env('VAPI_TIMEOUT'),

    'max_retries' => env('VAPI_MAX_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Additional Headers
    |--------------------------------------------------------------------------
    |
    | Extra headers sent with every request to the Vapi API.
    |
    */

    'headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | When enabled, the package registers a POST route that receives Vapi
    | server messages, verifies them, and turns them into Laravel events.
    |
    | `secret` is compared against both the legacy `x-vapi-secret` header
    | and an `Authorization: Bearer` token (Vapi's credential-based bearer
    | auth). `hmac` verifies signature-based credentials instead; its
    | header and algorithm must match your Vapi dashboard configuration.
    | Configure at least one of the two.
    |
    */

    'webhook' => [
        'enabled' => (bool) env('VAPI_WEBHOOK_ENABLED', false),
        'path' => env('VAPI_WEBHOOK_PATH', 'webhooks/vapi'),
        'secret' => env('VAPI_WEBHOOK_SECRET'),
        'hmac' => [
            'secret' => env('VAPI_WEBHOOK_HMAC_SECRET'),
            'header' => env('VAPI_WEBHOOK_HMAC_HEADER', 'x-vapi-signature'),
            'algorithm' => env('VAPI_WEBHOOK_HMAC_ALGORITHM', 'sha256'),
        ],
    ],

];
