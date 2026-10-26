<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Long wait
    |--------------------------------------------------------------------------
    |
    | Minutes a customer may wait for a first answer before supervisors see
    | the conversation flagged as waiting too long.
    |
    */

    'long_wait_minutes' => (int) env('INBOX_LONG_WAIT_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Refresh
    |--------------------------------------------------------------------------
    |
    | How often an open inbox asks for new messages, in seconds, and how many
    | conversations and thread lines one screen loads.
    |
    */

    'poll_seconds' => 5,

    'list_size' => 50,

    'thread_size' => 150,

];
