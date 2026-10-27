<?php

namespace App\WhatsApp;

use RuntimeException;

/**
 * Meta refused a request, or could not be reached. Built from Meta's error
 * code and message only, never from the request, so the token can't end
 * up in a log line or on a page.
 */
class CloudApiException extends RuntimeException
{
    public function __construct(public readonly string $metaCode, string $message)
    {
        parent::__construct(mb_substr($message, 0, 300));
    }

    /**
     * @param  array<string, mixed>  $error  Meta's "error" object
     */
    public static function fromMeta(array $error, int $status): self
    {
        $code = (string) ($error['code'] ?? $status);
        $details = $error['error_data']['details'] ?? null;

        return new self($code, trim(($error['message'] ?? "HTTP {$status}").($details ? " ({$details})" : '')));
    }

    public static function unreachable(string $reason): self
    {
        return new self('unreachable', "Meta could not be reached: {$reason}");
    }

    public static function notConfigured(): self
    {
        return new self('not_configured', 'The WhatsApp Cloud API keys are missing from .env.');
    }

    /**
     * What the team reads in the thread: Meta's well-known refusals in
     * their language, anything else as Meta wrote it.
     */
    public function forPeople(): string
    {
        $key = "app.whatsapp.errors.{$this->metaCode}";

        return __($key) === $key ? $this->getMessage() : __($key);
    }
}
