<?php

namespace App\Actions\WhatsApp;

use App\Actions\Templates\RecordTemplateDecision;
use App\Enums\TemplateStatus;
use App\Models\Template;
use App\WhatsApp\CloudApi;
use App\WhatsApp\CloudApiException;

/**
 * Asks Meta where each template stands, for verdicts a missed webhook
 * never brought. Templates are matched by Meta's id, else by name and
 * language; ones that exist only at Meta are left alone.
 */
class SyncTemplates
{
    public function __construct(
        private CloudApi $api,
        private RecordTemplateDecision $decision,
    ) {}

    /**
     * @return int How many templates changed.
     *
     * @throws CloudApiException
     */
    public function handle(): int
    {
        $remote = $this->api->get("{$this->api->businessAccountId()}/message_templates", [
            'fields' => 'id,name,language,status,rejected_reason',
            'limit' => 250,
        ])['data'] ?? [];

        $changed = 0;

        foreach ((array) $remote as $item) {
            $status = TemplateStatus::fromMeta((string) ($item['status'] ?? ''));
            $template = Template::query()->where('external_id', (string) ($item['id'] ?? ''))->first()
                ?? Template::query()->where('name', $item['name'] ?? '')->where('language', $item['language'] ?? '')->first();

            if ($status === null || $template === null) {
                continue;
            }

            if ($template->external_id === null) {
                $template->forceFill(['external_id' => (string) $item['id']])->save();
            }

            if ($template->status !== $status) {
                $reason = $item['rejected_reason'] ?? null;
                $this->decision->handle($template, $status, $reason === 'NONE' ? null : $reason);
                $changed++;
            }
        }

        return $changed;
    }
}
