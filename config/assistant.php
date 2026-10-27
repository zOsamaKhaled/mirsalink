<?php

use App\Assistant\OllamaBrain;
use App\Assistant\SimulatedBrain;

return [

    /*
    |--------------------------------------------------------------------------
    | Brain
    |--------------------------------------------------------------------------
    |
    | Who reads customer messages. "ollama" is the model on your own server,
    | so conversations never leave it; "simulator" matches keywords against
    | the tags and the knowledge base, for trying the platform without one.
    | Whether the assistant answers at all is set on its settings page.
    |
    */

    'driver' => env('ASSISTANT_DRIVER', 'simulator'),

    'drivers' => [
        'simulator' => SimulatedBrain::class,
        'ollama' => OllamaBrain::class,
    ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
        'chat_model' => env('OLLAMA_CHAT_MODEL', 'qwen2.5:7b-instruct'),
        'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'bge-m3'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Judgement
    |--------------------------------------------------------------------------
    |
    | Below this confidence the assistant hands the customer to a person.
    | A model slower than the timeout counts as down: the customer goes to a
    | person without an automatic reply. History is how many earlier
    | messages it reads; sources is how many knowledge entries it is shown,
    | each at least min_similarity close to the question.
    |
    */

    'confidence_threshold' => (float) env('ASSISTANT_CONFIDENCE', 0.6),

    'timeout_seconds' => (int) env('ASSISTANT_TIMEOUT', 20),

    'history' => 10,

    'sources' => 4,

    'min_similarity' => 0.55,

];
