<?php

namespace App\Actions\Codes;

use App\Codes\CodeSettings;
use App\Enums\TemplateStatus;
use App\Models\Template;
use App\WhatsApp\Gateway;

/**
 * Hands Meta the authentication template in every language that has none
 * yet, or whose last one was refused. Approved and pending ones are left
 * alone.
 */
class SubmitCodeTemplates
{
    public function __construct(private Gateway $gateway) {}

    /**
     * @return array{submitted: list<string>, refused: array<string, string>}
     */
    public function handle(): array
    {
        $settings = CodeSettings::current();
        $submitted = [];
        $refused = [];

        foreach (Template::LANGUAGES as $language) {
            if (! in_array($settings->status($language), [TemplateStatus::Draft, TemplateStatus::Rejected], true)) {
                continue;
            }

            $result = $this->gateway->submitCodeTemplate($language);

            if ($result->accepted) {
                CodeSettings::recordSubmission($language, (string) $result->externalId);
                $submitted[] = $language;
            } else {
                $refused[$language] = (string) $result->errorMessage;
            }
        }

        return ['submitted' => $submitted, 'refused' => $refused];
    }
}
