<?php

namespace App\Http\Requests\Inbox;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A reply or an internal note from the composer. A reply needs text or a
 * file; a note is text only.
 */
class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => ['boolean'],
            'body' => ['nullable', 'string', 'max:4096', 'required_without:file', 'required_if_accepted:note'],
            'file' => ['nullable', 'file', 'max:'.config('whatsapp.max_upload_kb'), 'prohibited_if_accepted:note'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => __('app.inbox.composer.body_attribute'),
            'file' => __('app.inbox.composer.file_attribute'),
        ];
    }
}
