<?php

namespace App\Http\Requests\Team;

use App\Support\TeamTimezones;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWorkingHoursRequest extends FormRequest
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
            'timezone' => ['required', Rule::in(TeamTimezones::ALL)],
            'days' => ['required', 'array', 'size:7'],
            'days.*.open' => ['required', 'boolean'],
            'days.*.from' => ['required', 'date_format:H:i'],
            'days.*.to' => ['required', 'date_format:H:i'],
            'auto_reply_enabled' => ['required', 'boolean'],
            'auto_reply_message' => ['required_if_accepted:auto_reply_enabled', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * An open day has to end after it starts; a closed day keeps whatever
     * times it had so they come back when it reopens.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('days', []) as $day => $hours) {
                    if (($hours['open'] ?? false) && ($hours['to'] ?? '') <= ($hours['from'] ?? '')) {
                        $validator->errors()->add("days.{$day}.to", __('app.team.hours.end_after_start'));
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'auto_reply_message.required_if_accepted' => __('app.team.hours.reply_required'),
        ];
    }
}
