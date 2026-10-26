<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pace
    |--------------------------------------------------------------------------
    |
    | Campaigns go out a batch each minute (php artisan mirsalink:broadcasts,
    | on the scheduler). The daily limit follows the number's Meta tier: a
    | new number may start 1,000 conversations in any 24 hours, and Meta
    | raises it as quality holds.
    |
    */

    'per_minute' => (int) env('BROADCASTS_PER_MINUTE', 60),

    'daily_limit' => (int) env('BROADCASTS_DAILY_LIMIT', 1000),

    /*
    |--------------------------------------------------------------------------
    | Replies and stopping
    |--------------------------------------------------------------------------
    |
    | A customer's message within this many hours of a campaign counts as a
    | reply to it. A message that is only one of the stop words (or the
    | stop button a marketing template must carry) ends their broadcasts.
    |
    */

    'reply_window_hours' => 72,

    'stop_words' => ['إيقاف', 'ايقاف', 'إلغاء', 'الغاء', 'إلغاء الاشتراك', 'الغاء الاشتراك', 'stop', 'unsubscribe'],

];
