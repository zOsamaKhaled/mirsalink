<?php

namespace App\Support;

use App\Models\Template;

/**
 * A template as the pages draw it: the form, the list and the preview in
 * the composer all read this shape.
 */
class TemplatePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(Template $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'language' => $template->language,
            'category' => $template->category->value,
            'header_type' => $template->header_type->value,
            'header_text' => $template->header_text,
            'header_example' => $template->header_example,
            'body' => $template->body,
            'body_examples' => $template->body_examples ?? [],
            'footer' => $template->footer,
            'buttons' => $template->buttons ?? [],
            'status' => $template->status->value,
            'rejection_reason' => $template->rejection_reason,
            'submitted_at' => $template->submitted_at?->toIso8601String(),
            'decided_at' => $template->decided_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
            'variables' => [
                'body' => $template->bodyVariableCount(),
                'header' => $template->headerHasVariable(),
                'button' => $template->linkButtonsWithVariable() !== [],
            ],
        ];
    }
}
