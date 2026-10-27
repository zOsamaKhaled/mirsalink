<?php

namespace App\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Meta's Graph API for WhatsApp, with the keys from config('whatsapp.cloud').
 * Every failure becomes a CloudApiException carrying Meta's code.
 */
class CloudApi
{
    public function configured(): bool
    {
        return collect(['token', 'phone_number_id', 'business_account_id', 'app_secret', 'verify_token'])
            ->every(fn (string $key): bool => filled(config("whatsapp.cloud.{$key}")));
    }

    public function phoneNumberId(): string
    {
        return (string) config('whatsapp.cloud.phone_number_id');
    }

    public function businessAccountId(): string
    {
        return (string) config('whatsapp.cloud.business_account_id');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload): array
    {
        return $this->call(fn (PendingRequest $request) => $request->post($path, $payload));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->call(fn (PendingRequest $request) => $request->get($path, $query));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function delete(string $path, array $query = []): array
    {
        return $this->call(fn (PendingRequest $request) => $request->delete($path.'?'.http_build_query($query)));
    }

    /**
     * Uploads a file for sending and returns Meta's media id.
     */
    public function uploadMedia(string $contents, string $filename, string $mime): string
    {
        $response = $this->call(fn (PendingRequest $request) => $request
            ->attach('file', $contents, $filename, ['Content-Type' => $mime])
            ->post("{$this->phoneNumberId()}/media", ['messaging_product' => 'whatsapp', 'type' => $mime]));

        return (string) ($response['id'] ?? throw new CloudApiException('no_media_id', 'Meta returned no media id.'));
    }

    /**
     * A received file's bytes. Meta's link to it expires within minutes,
     * so it is fetched as soon as the message arrives.
     *
     * @return array{contents: string, mime: string, size: int}
     */
    public function downloadMedia(string $mediaId): array
    {
        $info = $this->get($mediaId);
        $url = (string) ($info['url'] ?? throw new CloudApiException('no_media_url', 'Meta returned no link for the file.'));

        try {
            $response = $this->request()->baseUrl('')->get($url);
        } catch (ConnectionException $exception) {
            throw CloudApiException::unreachable($exception->getMessage());
        }

        if ($response->failed()) {
            throw new CloudApiException((string) $response->status(), "The file could not be downloaded (HTTP {$response->status()}).");
        }

        return [
            'contents' => $response->body(),
            'mime' => (string) ($info['mime_type'] ?? $response->header('Content-Type')),
            'size' => (int) ($info['file_size'] ?? strlen($response->body())),
        ];
    }

    /**
     * @param  callable(PendingRequest): Response  $send
     * @return array<string, mixed>
     */
    private function call(callable $send): array
    {
        if (blank(config('whatsapp.cloud.token'))) {
            throw CloudApiException::notConfigured();
        }

        try {
            $response = $send($this->request());
        } catch (ConnectionException $exception) {
            throw CloudApiException::unreachable($exception->getMessage());
        }

        if ($response->failed()) {
            throw CloudApiException::fromMeta((array) $response->json('error', []), $response->status());
        }

        return (array) $response->json();
    }

    private function request(): PendingRequest
    {
        $version = trim((string) config('whatsapp.cloud.graph_version'), '/');

        return Http::baseUrl(rtrim((string) config('whatsapp.cloud.graph_url'), '/')."/{$version}/")
            ->withToken((string) config('whatsapp.cloud.token'))
            ->acceptJson()
            ->timeout((int) config('whatsapp.cloud.timeout_seconds'));
    }
}
