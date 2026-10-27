<?php

use App\WhatsApp\CloudGateway;
use App\WhatsApp\SimulatorGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway
    |--------------------------------------------------------------------------
    |
    | Who carries messages to and from WhatsApp. "simulator" plays WhatsApp's
    | part inside the platform, with its own page for typing customer
    | messages; "cloud" talks to Meta's WhatsApp Cloud API with the keys
    | below.
    |
    */

    'gateway' => env('WHATSAPP_GATEWAY', 'simulator'),

    'gateways' => [
        'simulator' => SimulatorGateway::class,
        'cloud' => CloudGateway::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta's WhatsApp Cloud API
    |--------------------------------------------------------------------------
    |
    | From the Meta app and its system user. The token and the app secret
    | live in .env on the server and nowhere else. The verify token is any
    | long random text, typed the same here and in the app's webhook
    | settings; the app secret checks that each notification came from Meta.
    |
    */

    'cloud' => [
        'graph_url' => env('WHATSAPP_GRAPH_URL', 'https://graph.facebook.com'),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'timeout_seconds' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    |
    | Where files sent and received are kept. The disk is private: the team
    | opens them through the platform, never through a public address.
    |
    */

    'media_disk' => env('WHATSAPP_MEDIA_DISK', 'local'),

    'max_upload_kb' => 16 * 1024,

];
