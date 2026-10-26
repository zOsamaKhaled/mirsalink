<?php

namespace App\Http\Requests\Contacts;

use App\Models\Contact;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding or editing a contact. Every team member may; the number is read
 * into its international form before it is checked.
 */
class SaveContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'typed_phone' => $this->input('phone'),
            'phone' => PhoneNumber::normalize($this->string('phone')->toString()),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Contact|null $contact */
        $contact = $this->route('contact');

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                Rule::unique('contacts', 'phone')->ignore($contact?->id)->whereNull('deleted_at'),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'opted_out' => ['boolean'],
            'tags' => ['array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => $this->filled('typed_phone')
                ? __('app.contacts.errors.bad_phone')
                : __('validation.required', ['attribute' => __('validation.attributes.phone')]),
            'phone.unique' => __('app.contacts.errors.phone_taken'),
        ];
    }
}
