<?php

use App\WhatsApp\SimulatorGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway
    |--------------------------------------------------------------------------
    |
    | Who carries messages to and from WhatsApp. "simulator" plays WhatsApp's
    | part inside the platform, with its own page for typing customer
    | messages; "cloud" (phase 9) talks to Meta's WhatsApp Cloud API.
    |
    */

    'gateway' => env('WHATSAPP_GATEWAY', 'simulator'),

    'gateways' => [
        'simulator' => SimulatorGateway::class,
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
