<?php

namespace App\WhatsApp;

use App\Enums\TemplateHeader;
use App\Models\Message;
use App\Models\Template;

/**
 * A template in Meta's shape: as components with examples for review, and
 * as parameters when a message is sent from it.
 */
class TemplatePayload
{
    /**
     * @return array<string, mixed>
     */
    public static function forReview(Template $template): array
    {
        $components = [];

        if ($template->header_type === TemplateHeader::Text) {
            $components[] = array_filter([
                'type' => 'HEADER',
                'format' => 'TEXT',
                'text' => $template->header_text,
                'example' => $template->headerHasVariable() ? ['header_text' => [$template->header_example]] : null,
            ]);
        } elseif ($template->header_type !== TemplateHeader::None) {
            $components[] = ['type' => 'HEADER', 'format' => strtoupper($template->header_type->value)];
        }

        $components[] = array_filter([
            'type' => 'BODY',
            'text' => $template->body,
            'example' => $template->bodyVariableCount() > 0 ? ['body_text' => [array_values($template->body_examples ?? [])]] : null,
        ]);

        if (filled($template->footer)) {
            $components[] = ['type' => 'FOOTER', 'text' => $template->footer];
        }

        if (! empty($template->buttons)) {
            $components[] = ['type' => 'BUTTONS', 'buttons' => array_map(fn (array $button): array => match ($button['type']) {
                'url' => array_filter([
                    'type' => 'URL',
                    'text' => $button['text'],
                    'url' => $button['url'],
                    'example' => Template::variablesIn($button['url'] ?? null) !== []
                        ? [Template::withValues($button['url'], [(string) ($button['example'] ?? '')])]
                        : null,
                ]),
                'phone' => ['type' => 'PHONE_NUMBER', 'text' => $button['text'], 'phone_number' => $button['phone']],
                default => ['type' => 'QUICK_REPLY', 'text' => $button['text']],
            }, $template->buttons)];
        }

        return [
            'name' => $template->name,
            'language' => $template->language,
            'category' => strtoupper($template->category->value),
            'components' => $components,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forSending(Message $message): array
    {
        $template = $message->template;
        $params = $message->template_params ?? [];
        $text = fn (string $value): array => ['type' => 'text', 'text' => $value];
        $components = [];

        if ($template->headerHasVariable()) {
            $components[] = ['type' => 'header', 'parameters' => [$text((string) ($params['header'] ?? ''))]];
        }

        if (($params['body'] ?? []) !== []) {
            $components[] = ['type' => 'body', 'parameters' => array_map($text, $params['body'])];
        }

        foreach ($template->linkButtonsWithVariable() as $position) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => (string) $position,
                'parameters' => [$text((string) ($params['button'] ?? ''))],
            ];
        }

        return array_filter([
            'name' => $template->name,
            'language' => ['code' => $template->language],
            'components' => $components === [] ? null : $components,
        ]);
    }
}
