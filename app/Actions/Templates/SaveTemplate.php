<?php

namespace App\Actions\Templates;

use App\Enums\TemplateStatus;
use App\Models\Template;
use App\Models\User;

/**
 * Writes a template from the form. A refused one comes back as a draft,
 * its old reason cleared, ready to be sent for review again.
 */
class SaveTemplate
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Template $template = null, ?User $actor = null): Template
    {
        $template ??= new Template(['created_by' => $actor?->id]);

        $template->fill([
            'name' => $data['name'],
            'language' => $data['language'],
            'category' => $data['category'],
            'header_type' => $data['header_type'],
            'header_text' => $data['header_text'] ?? null,
            'header_example' => $data['header_example'] ?? null,
            'body' => trim((string) $data['body']),
            'body_examples' => $data['body_examples'] === [] ? null : $data['body_examples'],
            'footer' => filled($data['footer'] ?? null) ? $data['footer'] : null,
            'buttons' => $data['buttons'] === [] ? null : $data['buttons'],
            'status' => TemplateStatus::Draft,
            'rejection_reason' => null,
        ])->save();

        return $template;
    }
}
