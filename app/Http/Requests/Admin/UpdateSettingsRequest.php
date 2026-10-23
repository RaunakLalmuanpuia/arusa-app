<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-admin');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'whatsapp_number' => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'cod_enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_phone.regex' => 'Enter a 10-digit mobile number without +91.',
            'whatsapp_number.regex' => 'Enter a 10-digit mobile number without +91.',
        ];
    }
}
