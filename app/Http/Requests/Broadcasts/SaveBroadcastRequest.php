<?php

namespace App\Http\Requests\Broadcasts;

use App\Enums\AudienceType;
use App\Enums\TemplateStatus;
use App\Models\Broadcast;
use App\Models\Template;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A campaign as written: its approved template, its audience, and where
 * each of the template's variables takes its value from. Every variable
 * needs a source; a fixed text needs the text, and a CSV column needs a CSV
 * audience.
 */
class SaveBroadcastRequest extends FormRequest
{
    public const array SOURCES = ['name', 'company', 'phone', 'column', 'text'];

    public function authorize(): bool
    {
        return $this->user()?->can('manage-team') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // The header's and the button's only when the template has them.
        $variable = fn (string $key, ?string $optional = null): array => [
            "{$key}.source" => [$optional === null ? 'required' : "required_with:{$optional}", Rule::in(self::SOURCES)],
            "{$key}.value" => ['nullable', 'string', 'max:200'],
            "{$key}.fallback" => ['nullable', 'string', 'max:200'],
        ];

        return [
            'name' => ['required', 'string', 'max:120'],
            'template_id' => ['required', 'integer', Rule::exists('templates', 'id')->where('status', TemplateStatus::Approved->value)],
            'audience_type' => ['required', Rule::enum(AudienceType::class)],
            'audience_tags' => ['nullable', 'array', 'required_if:audience_type,tags'],
            'audience_tags.*' => ['integer', 'exists:tags,id'],
            'file' => ['nullable', 'file', 'mimes:csv,txt', 'max:5120'],
            'variables' => ['array'],
            'variables.body' => ['array'],
            ...$variable('variables.body.*'),
            'variables.header' => ['nullable', 'array'],
            ...$variable('variables.header', 'variables.header'),
            'variables.button' => ['nullable', 'array'],
            ...$variable('variables.button', 'variables.button'),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Broadcast|null $broadcast */
            $broadcast = $this->route('broadcast');
            $csv = $this->input('audience_type') === AudienceType::Csv->value;

            if ($csv && $this->file('file') === null && $broadcast?->audience_file === null) {
                $validator->errors()->add('file', __('app.broadcasts.rules.file'));
            }

            $template = Template::query()->findOrFail($this->integer('template_id'));
            $variables = (array) $this->input('variables', []);

            if (count((array) ($variables['body'] ?? [])) !== $template->bodyVariableCount()
                || $template->headerHasVariable() !== isset($variables['header'])
                || ($template->linkButtonsWithVariable() !== []) !== isset($variables['button'])) {
                $validator->errors()->add('variables', __('app.broadcasts.rules.variables'));

                return;
            }

            $specs = [];

            foreach ((array) ($variables['body'] ?? []) as $position => $spec) {
                $specs["variables.body.{$position}"] = $spec;
            }

            $specs['variables.header'] = $variables['header'] ?? null;
            $specs['variables.button'] = $variables['button'] ?? null;

            foreach ($specs as $key => $spec) {
                if ($spec === null) {
                    continue;
                }

                if (in_array($spec['source'], ['text', 'column'], true) && blank($spec['value'] ?? null)) {
                    $validator->errors()->add("{$key}.value", __('app.broadcasts.rules.value'));
                }

                if ($spec['source'] === 'column' && ! $csv) {
                    $validator->errors()->add("{$key}.source", __('app.broadcasts.rules.column'));
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('app.broadcasts.form.name'),
            'template_id' => __('app.broadcasts.form.template'),
            'audience_tags' => __('app.broadcasts.form.tags'),
            'file' => __('app.broadcasts.form.file'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'template_id.exists' => __('app.broadcasts.rules.template_not_approved'),
            'audience_tags.required_if' => __('app.broadcasts.rules.tags'),
        ];
    }
}
