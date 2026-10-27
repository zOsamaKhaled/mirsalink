<?php

use App\Actions\Assistant\EmbedKnowledgeEntry;
use App\Actions\Broadcasts\RunBroadcasts;
use App\Actions\WhatsApp\ProcessWebhookEvent;
use App\Assistant\Brain;
use App\Models\KnowledgeEntry;
use App\Models\WebhookEvent;
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

Artisan::command('mirsalink:embed-knowledge {--all : Embed again even entries the current model already embedded}', function (EmbedKnowledgeEntry $embed, Brain $brain) {
    $model = $brain->embeddingModel();

    if ($model === null) {
        $this->warn('The current assistant driver makes no embeddings; knowledge is matched by its words.');

        return;
    }

    $entries = KnowledgeEntry::query()
        ->when(! $this->option('all'), fn ($query) => $query->where(fn ($pending) => $pending->whereNull('embedding_model')->orWhere('embedding_model', '!=', $model)))
        ->get();

    $done = $entries->filter(fn (KnowledgeEntry $entry): bool => $embed->handle($entry))->count();

    $this->info("Embedded {$done} of {$entries->count()} knowledge entries with {$model}.");
})->purpose('Embed knowledge entries with the assistant\'s embedding model');

Artisan::command('mirsalink:webhooks', function (ProcessWebhookEvent $process) {
    $events = WebhookEvent::query()
        ->whereNull('processed_at')
        ->where('attempts', '<', WebhookEvent::MAX_ATTEMPTS)
        ->where('created_at', '<=', now()->subMinute())
        ->orderBy('id')
        ->limit(200)
        ->get();

    $read = $events->filter(fn (WebhookEvent $event): bool => $process->handle($event))->count();

    $this->info("Read {$read} of {$events->count()} waiting WhatsApp notifications.");
})->purpose('Read again the WhatsApp notifications that failed the first time');

Schedule::command('mirsalink:webhooks')->everyMinute()->withoutOverlapping();
