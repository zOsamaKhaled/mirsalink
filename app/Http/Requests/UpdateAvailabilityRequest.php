<?php

namespace App\Http\Requests;

use App\Enums\Availability;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Every member sets their own availability; no one sets it for them.
 */
class UpdateAvailabilityRequest extends FormRequest
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
            'availability' => ['required', Rule::enum(Availability::class)],
        ];
    }
}
