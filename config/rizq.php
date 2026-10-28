<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The link with Rizq
    |--------------------------------------------------------------------------
    |
    | Rizq and the platform talk over a private, signed connection: each
    | request carries an HMAC of its time, method, path and body made with
    | the shared secret, which is the same long random text in both apps'
    | .env (RIZQ_SHARED_SECRET here, MIRSALINK_SHARED_SECRET in Rizq). A
    | request older than the tolerance, or seen before, is refused.
    |
    */

    'url' => env('RIZQ_URL'),

    'secret' => env('RIZQ_SHARED_SECRET'),

    'timeout_seconds' => 5,

    /*
    |--------------------------------------------------------------------------
    | Verification codes
    |--------------------------------------------------------------------------
    |
    | Rizq's sign-in and sign-up codes, sent on WhatsApp with Meta's
    | authentication template (its wording is Meta's own, in each language).
    | The expiry matches Rizq's codes. A number asking for more codes than
    | the hourly limit is refused, and Rizq falls back to email.
    |
    */

    'codes' => [
        'template' => env('CODES_TEMPLATE', 'verification_code'),
        'expiry_minutes' => 5,
        'per_phone_per_hour' => 5,
        'path' => '/webhooks/mirsalink/codes',
    ],

];
