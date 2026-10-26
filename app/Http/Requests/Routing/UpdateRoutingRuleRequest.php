<?php

namespace App\Http\Requests\Routing;

use App\Enums\RoutingMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Who takes one tag's conversations (or, with no tag, everything else),
 * and how they take turns. No members means the whole team.
 */
class UpdateRoutingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-team') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tag_id' => ['nullable', 'integer', Rule::exists('tags', 'id')],
            'method' => ['required', Rule::enum(RoutingMethod::class)],
            'members' => ['array'],
            'members.*' => ['integer', Rule::exists('users', 'id')->whereNull('deactivated_at')],
        ];
    }
}
