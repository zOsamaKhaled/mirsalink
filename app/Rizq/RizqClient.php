<?php

namespace App\Rizq;

use App\Support\SignedRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Calls into Rizq over the signed internal connection, with the address
 * and shared secret from config('rizq').
 */
class RizqClient
{
    public function configured(): bool
    {
        return filled(config('rizq.url')) && filled(config('rizq.secret'));
    }

    /**
     * Posts to Rizq and says whether it took it. A Rizq that is down or
     * slow counts as not taking it; nothing here waits longer than the
     * timeout.
     *
     * @param  array<string, mixed>  $payload
     */
    public function post(string $path, array $payload): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $url = rtrim((string) config('rizq.url'), '/').'/'.ltrim($path, '/');
        $body = (string) json_encode($payload);

        try {
            return Http::withHeaders(SignedRequest::headers('POST', $url, $body, (string) config('rizq.secret')))
                ->withBody($body, 'application/json')
                ->acceptJson()
                ->timeout((int) config('rizq.timeout_seconds'))
                ->post($url)
                ->successful();
        } catch (ConnectionException $exception) {
            report($exception);

            return false;
        }
    }
}
