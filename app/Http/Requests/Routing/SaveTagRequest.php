<?php

namespace App\Http\Requests\Routing;

use App\Enums\TagColor;
use App\Models\Tag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-team') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim($this->string('name')->toString())]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Tag|null $tag */
        $tag = $this->route('tag');

        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('tags', 'name')->ignore($tag?->id)],
            'color' => ['required', Rule::enum(TagColor::class)],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('app.routing.tags.errors.name_taken'),
        ];
    }
}
