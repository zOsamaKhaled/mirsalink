<?php

use App\Actions\Broadcasts\RunBroadcasts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mirsalink:broadcasts', function (RunBroadcasts $run) {
    $this->info("Campaign messages sent this minute: {$run->handle()}");
})->purpose('Send the next batch of every campaign that is due');

// Campaigns go out a batch a minute; run `php artisan schedule:work` locally.
Schedule::command('mirsalink:broadcasts')->everyMinute()->withoutOverlapping();
