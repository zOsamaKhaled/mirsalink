<?php

namespace App\Codes;

use App\Enums\TemplateStatus;
use App\Models\Setting;

/**
 * Whether Rizq's verification codes go out on WhatsApp, and where Meta's
 * authentication template stands in each language. The template is kept
 * here rather than with the team's templates: its wording is Meta's, it is
 * never sent from the inbox or a campaign, and nobody edits it.
 */
final class CodeSettings
{
    public const string SETTING = 'codes';

    /**
     * @param  array<string, array{status: string, external_id: string|null, reason: string|null, submitted_at: string|null, decided_at: string|null}>  $templates  language => its state with Meta
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly array $templates,
    ) {}

    public static function current(): self
    {
        $stored = Setting::read(self::SETTING) ?? [];

        return new self(
            enabled: (bool) ($stored['enabled'] ?? true),
            templates: (array) ($stored['templates'] ?? []),
        );
    }

    public static function templateName(): string
    {
        return (string) config('rizq.codes.template');
    }

    public function status(string $language): TemplateStatus
    {
        return TemplateStatus::tryFrom((string) ($this->templates[$language]['status'] ?? '')) ?? TemplateStatus::Draft;
    }

    /**
     * The language a code goes out in: the one asked for if Meta approved
     * it there, else any approved one, else none and Rizq uses email.
     */
    public function languageFor(string $language): ?string
    {
        if (! $this->enabled) {
            return null;
        }

        $approved = array_keys(array_filter(
            $this->templates,
            fn (array $template): bool => ($template['status'] ?? null) === TemplateStatus::Approved->value,
        ));

        return in_array($language, $approved, true) ? $language : ($approved[0] ?? null);
    }

    public static function setEnabled(bool $enabled): void
    {
        self::store(['enabled' => $enabled]);
    }

    public static function recordSubmission(string $language, string $externalId): void
    {
        self::recordTemplate($language, [
            'status' => TemplateStatus::Pending->value,
            'external_id' => $externalId,
            'reason' => null,
            'submitted_at' => now()->toIso8601String(),
            'decided_at' => null,
        ]);
    }

    public static function recordDecision(string $language, TemplateStatus $status, ?string $reason = null): void
    {
        self::recordTemplate($language, [
            'status' => $status->value,
            'reason' => $status === TemplateStatus::Rejected ? $reason : null,
            'decided_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private static function recordTemplate(string $language, array $changes): void
    {
        $templates = self::current()->templates;
        $templates[$language] = [...($templates[$language] ?? ['external_id' => null, 'submitted_at' => null]), ...$changes];

        self::store(['templates' => $templates]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private static function store(array $changes): void
    {
        $current = self::current();

        Setting::write(self::SETTING, [
            'enabled' => $current->enabled,
            'templates' => $current->templates,
            ...$changes,
        ]);
    }
}
