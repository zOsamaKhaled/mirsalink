<?php

namespace App\Http\Requests\Templates;

use App\Enums\TemplateCategory;
use App\Enums\TemplateHeader;
use App\Models\Template;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A template as written by a supervisor, checked against the rules Meta
 * refuses templates for, so a draft that passes here is not sent back for
 * its form: numbered variables in order with an example each, none at the
 * very start or end of the text or side by side, short header and footer,
 * and at most ten buttons with two links and one phone number.
 */
class SaveTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-team') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $buttons = collect($this->input('buttons', []))
            ->filter(fn ($button): bool => is_array($button))
            ->map(fn (array $button): array => [
                'type' => $button['type'] ?? null,
                'text' => trim((string) ($button['text'] ?? '')),
                'url' => ($button['type'] ?? null) === 'url' ? trim((string) ($button['url'] ?? '')) : null,
                'example' => ($button['type'] ?? null) === 'url' ? (trim((string) ($button['example'] ?? '')) ?: null) : null,
                'phone' => ($button['type'] ?? null) === 'phone' ? PhoneNumber::normalize((string) ($button['phone'] ?? '')) : null,
            ])
            ->values()
            ->all();

        $this->merge([
            'name' => Str::of((string) $this->input('name'))->trim()->lower()->replaceMatches('/[\s-]+/', '_')->toString(),
            'header_text' => $this->input('header_type') === TemplateHeader::Text->value ? $this->input('header_text') : null,
            'header_example' => $this->input('header_type') === TemplateHeader::Text->value ? $this->input('header_example') : null,
            'body_examples' => array_values(array_map(fn ($value): string => trim((string) $value), (array) $this->input('body_examples', []))),
            'buttons' => $buttons,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Template|null $template */
        $template = $this->route('template');

        return [
            'name' => [
                'required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('templates')->where('language', $this->input('language'))->ignore($template?->id),
            ],
            'language' => ['required', Rule::in(Template::LANGUAGES)],
            'category' => ['required', Rule::enum(TemplateCategory::class)],
            'header_type' => ['required', Rule::enum(TemplateHeader::class)],
            'header_text' => ['nullable', 'required_if:header_type,text', 'string', 'max:60'],
            'header_example' => ['nullable', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:1024'],
            'body_examples' => ['array'],
            'body_examples.*' => ['required', 'string', 'max:200'],
            'footer' => ['nullable', 'string', 'max:60'],
            'buttons' => ['array', 'max:10'],
            'buttons.*.type' => ['required', Rule::in(['quick_reply', 'url', 'phone'])],
            'buttons.*.text' => ['required', 'string', 'max:25'],
            'buttons.*.url' => ['nullable', 'required_if:buttons.*.type,url', 'string', 'max:2000', 'starts_with:https://'],
            'buttons.*.example' => ['nullable', 'string', 'max:200'],
            'buttons.*.phone' => ['nullable', 'required_if:buttons.*.type,phone', 'string'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        // Each part is checked unless its own fields already failed, so a
        // supervisor sees every problem at once.
        return [function (Validator $validator): void {
            $failed = fn (string $prefix): bool => collect($validator->errors()->keys())
                ->contains(fn (string $key): bool => str_starts_with($key, $prefix));

            if (! $validator->errors()->has('body')) {
                $this->checkBody($validator);
            }

            if (! $failed('header')) {
                $this->checkHeader($validator);
            }

            if (! $failed('footer')) {
                $this->checkFooter($validator);
            }

            if (! $failed('buttons')) {
                $this->checkButtons($validator);
            }
        }];
    }

    private function checkBody(Validator $validator): void
    {
        $body = trim((string) $this->input('body'));
        $variables = Template::variablesIn($body);
        $numbers = array_values(array_unique($variables));
        sort($numbers);

        if ($numbers !== [] && $numbers !== range(1, count($numbers))) {
            $validator->errors()->add('body', __('app.templates.rules.sequence'));

            return;
        }

        if (preg_match('/^\{\{\s*\d+\s*\}\}|\{\{\s*\d+\s*\}\}$/u', $body)) {
            $validator->errors()->add('body', __('app.templates.rules.edges'));
        }

        if (preg_match('/\}\}\s*\{\{/', $body)) {
            $validator->errors()->add('body', __('app.templates.rules.adjacent'));
        }

        if (count((array) $this->input('body_examples')) !== count($numbers)) {
            $validator->errors()->add('body_examples', __('app.templates.rules.examples'));
        }
    }

    private function checkHeader(Validator $validator): void
    {
        $variables = Template::variablesIn($this->input('header_text'));

        if ($variables !== [] && $variables !== [1]) {
            $validator->errors()->add('header_text', __('app.templates.rules.header_variable'));
        }

        if ($variables !== [] && blank($this->input('header_example'))) {
            $validator->errors()->add('header_example', __('app.templates.rules.examples'));
        }
    }

    private function checkFooter(Validator $validator): void
    {
        if (Template::variablesIn($this->input('footer')) !== []) {
            $validator->errors()->add('footer', __('app.templates.rules.footer_variable'));
        }
    }

    private function checkButtons(Validator $validator): void
    {
        $buttons = collect((array) $this->input('buttons'));

        if ($buttons->where('type', 'url')->count() > 2 || $buttons->where('type', 'phone')->count() > 1) {
            $validator->errors()->add('buttons', __('app.templates.rules.button_limits'));
        }

        foreach ($buttons as $position => $button) {
            if ($button['type'] === 'phone' && $button['phone'] === null) {
                $validator->errors()->add("buttons.{$position}.phone", __('app.templates.rules.button_phone'));
            }

            if ($button['type'] !== 'url') {
                continue;
            }

            $variables = Template::variablesIn($button['url']);

            if ($variables !== [] && ($variables !== [1] || ! preg_match('/\{\{\s*1\s*\}\}$/', (string) $button['url']))) {
                $validator->errors()->add("buttons.{$position}.url", __('app.templates.rules.url_variable'));
            }

            if ($variables !== [] && blank($button['example'])) {
                $validator->errors()->add("buttons.{$position}.example", __('app.templates.rules.examples'));
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('app.templates.form.name'),
            'body' => __('app.templates.form.body'),
            'header_text' => __('app.templates.form.header_text'),
            'footer' => __('app.templates.form.footer'),
            'body_examples.*' => __('app.templates.form.example'),
            'buttons.*.text' => __('app.templates.form.button_text'),
            'buttons.*.url' => __('app.templates.form.button_url'),
            'buttons.*.phone' => __('app.templates.form.button_phone'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => __('app.templates.rules.name'),
            'name.unique' => __('app.templates.rules.name_taken'),
            'buttons.*.url.starts_with' => __('app.templates.rules.https'),
            'buttons.*.url.required_if' => __('app.templates.rules.url_required'),
            'buttons.*.phone.required_if' => __('app.templates.rules.button_phone'),
            'header_text.required_if' => __('app.templates.rules.header_required'),
        ];
    }
}
