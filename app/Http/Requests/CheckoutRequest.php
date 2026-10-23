<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Address;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'line1' => ['required', 'string', 'max:200'],
            'locality' => ['required', 'string', 'max:100'],
            'district' => ['required', Rule::in(Address::DISTRICTS)],
            'pincode' => ['required', 'digits:6'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)->only([PaymentMethod::Cod])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a 10-digit mobile number, without +91.',
        ];
    }
}
